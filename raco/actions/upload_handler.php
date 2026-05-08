<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/data.php';

raco_start_session();
if (empty($_SESSION['user_id'])) {
    raco_json_response(['error' => 'Unauthorized'], 401);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    raco_json_response(['error' => 'Method not allowed'], 405);
}
if (!isset($_FILES['dataset_file']) || $_FILES['dataset_file']['error'] !== UPLOAD_ERR_OK) {
    raco_json_response(['error' => 'No file uploaded'], 400);
}

$file = $_FILES['dataset_file'];
$originalName = (string) ($file['name'] ?? '');
$tmp = (string) ($file['tmp_name'] ?? '');
$ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
if (!in_array($ext, ['csv', 'xlsx'], true)) {
    raco_json_response(['error' => 'Only CSV and XLSX files are supported'], 400);
}

$safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($originalName));
$storedFilename = uniqid('orig_', true) . '_' . $safeName;
$storagePath = __DIR__ . '/../uploads/' . $storedFilename;
if (!move_uploaded_file($tmp, $storagePath)) {
    raco_json_response(['error' => 'Failed to store uploaded file'], 500);
}

try {
    $parsed = $ext === 'csv' ? raco_parse_csv_file($storagePath) : raco_parse_xlsx_file($storagePath);
    $headers = $parsed['headers'];
    $rows = $parsed['rows'];
} catch (Throwable $e) {
    @unlink($storagePath);
    raco_json_response(['error' => $e->getMessage()], 400);
}

if (!$headers) {
    @unlink($storagePath);
    raco_json_response(['error' => 'File has no headers'], 400);
}

$rowCount = count($rows);
$columnCount = count($headers);
$profiles = raco_build_profiles($headers, $rows);

$pdo = raco_pdo();
$pdo->beginTransaction();

try {
    $countCol = raco_upload_column_count_name($pdo);

    $sql = "INSERT INTO uploads (user_id, original_filename, file_type, row_count, {$countCol}, upload_status) VALUES (:user_id, :original_filename, :file_type, :row_count, :column_count, :upload_status)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'user_id' => (int) $_SESSION['user_id'],
        'original_filename' => $originalName,
        'file_type' => $ext,
        'row_count' => $rowCount,
        'column_count' => $columnCount,
        'upload_status' => 'uploaded',
    ]);

    $uploadId = (int) $pdo->lastInsertId();

    $stmtDataset = $pdo->prepare('INSERT INTO datasets (upload_id, cleaning_job_id, dataset_type, column_headers, row_data, row_count) VALUES (:upload_id, NULL, :dataset_type, :column_headers, :row_data, :row_count)');
    $stmtDataset->execute([
        'upload_id' => $uploadId,
        'dataset_type' => 'original',
        'column_headers' => json_encode($headers, JSON_UNESCAPED_UNICODE),
        'row_data' => json_encode($rows, JSON_UNESCAPED_UNICODE),
        'row_count' => $rowCount,
    ]);

    $stmtProfile = $pdo->prepare('INSERT INTO column_profiles (upload_id, column_name, detected_type, null_count, non_null_count, unique_count, min_value, max_value, mean_value, median_value, std_dev, sample_values) VALUES (:upload_id, :column_name, :detected_type, :null_count, :non_null_count, :unique_count, :min_value, :max_value, :mean_value, :median_value, :std_dev, :sample_values)');

    foreach ($profiles as $p) {
        $stmtProfile->execute([
            'upload_id' => $uploadId,
            'column_name' => $p['column_name'],
            'detected_type' => $p['detected_type'],
            'null_count' => $p['null_count'],
            'non_null_count' => $p['non_null_count'],
            'unique_count' => $p['unique_count'],
            'min_value' => $p['min_value'],
            'max_value' => $p['max_value'],
            'mean_value' => $p['mean_value'],
            'median_value' => $p['median_value'],
            'std_dev' => $p['std_dev'],
            'sample_values' => json_encode($p['sample_values'], JSON_UNESCAPED_UNICODE),
        ]);
    }

    $pdo->prepare('UPDATE uploads SET upload_status = :status WHERE id = :id')->execute([
        'status' => 'profiled',
        'id' => $uploadId,
    ]);

    raco_log_activity($pdo, (int) $_SESSION['user_id'], $uploadId, 'upload', 'Uploaded and profiled ' . $originalName);
    $pdo->commit();

    raco_json_response([
        'success' => true,
        'message' => 'Upload complete',
        'redirect' => '../pages/profile.php?upload_id=' . $uploadId,
    ]);
} catch (Throwable $e) {
    $pdo->rollBack();
    @unlink($storagePath);
    raco_json_response(['error' => 'Upload failed: ' . $e->getMessage()], 500);
}
