<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/data.php';

raco_start_session();
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    exit('Unauthorized');
}

$uploadId = (int) ($_GET['upload_id'] ?? 0);
$type = strtolower((string) ($_GET['type'] ?? 'csv'));
if ($uploadId <= 0 || !in_array($type, ['csv', 'json'], true)) {
    http_response_code(400);
    exit('Invalid request');
}

$pdo = raco_pdo();
$stmt = $pdo->prepare('SELECT id FROM uploads WHERE id = :id AND user_id = :user_id');
$stmt->execute(['id' => $uploadId, 'user_id' => (int) $_SESSION['user_id']]);
if (!$stmt->fetch()) {
    http_response_code(404);
    exit('Upload not found');
}

$dataset = raco_first_dataset_file($pdo, $uploadId, 'cleaned');
if (!$dataset) {
    http_response_code(404);
    exit('Cleaned dataset not found');
}

$rows = raco_decode_dataset_rows_from_record($dataset);
$filename = 'raco_cleaned_' . $uploadId;

if ($type === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.json"');
    echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
$out = fopen('php://output', 'w');
if ($rows) {
    fputcsv($out, array_keys($rows[0]));
    foreach ($rows as $row) {
        fputcsv($out, array_values($row));
    }
}
fclose($out);
exit;
