<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/includes/functions.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_FILES['dataset_file']) || $_FILES['dataset_file']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Please upload a valid CSV or XLSX file.';
    } else {
        $file = $_FILES['dataset_file'];
        $fileName = (string) ($file['name'] ?? '');
        $fileSize = (int) ($file['size'] ?? 0);
        $tmpPath = (string) ($file['tmp_name'] ?? '');
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if (!in_array($extension, ['csv', 'xlsx'], true)) {
            $error = 'Unsupported file type. Only .csv and .xlsx files are allowed.';
        } elseif ($fileSize > 10 * 1024 * 1024) {
            $error = 'File is too large. Maximum size is 10MB.';
        } else {
            try {
                $parsed = $extension === 'csv' ? parseCSV($tmpPath) : parseExcel($tmpPath);
                @unlink($tmpPath);

                $headers = $parsed['headers'];
                $rows = $parsed['rows'];
                $types = detectDataTypes($rows, $headers);

                $uploadId = null;
                try {
                    $userId = getOrCreateSessionUserId();
                    if ($userId !== null) {
                        $uploadId = persistUploadRecord(
                            $userId,
                            $fileName,
                            $extension,
                            count($rows),
                            count($headers)
                        );

                        if ($uploadId !== null) {
                            persistDataset($uploadId, 'original', $headers, $rows);
                            persistColumnProfiles($uploadId, $headers, $rows, $types);
                            persistActivityLog($userId, $uploadId, 'upload_dataset', 'Original dataset uploaded and profiled.');
                        }
                    }
                } catch (Throwable $dbException) {
                    $_SESSION['flash_warning'] = 'Dataset uploaded for processing, but database persistence was skipped. Increase MySQL max_allowed_packet (e.g., 64M) to persist large datasets.';
                }

                $_SESSION['dataset'] = [
                    'headers' => $headers,
                    'rows' => $rows,
                    'data_types' => $types,
                ];
                $_SESSION['upload_id'] = $uploadId;

                unset($_SESSION['cleaned_dataset'], $_SESSION['clean_summary'], $_SESSION['clean_diff']);
                $_SESSION['flash_success'] = 'Dataset uploaded and parsed successfully.';
                header('Location: profile.php');
                exit;
            } catch (Throwable $exception) {
                $error = $exception->getMessage();
            }
        }
    }
}

$pageTitle = 'Upload';
require_once __DIR__ . '/includes/header.php';
$current_step = 1;
require_once __DIR__ . '/includes/step_indicator.php';
?>

<div class="row justify-content-center">
    <div class="col-12 col-md-10 col-lg-8 col-xl-6">
        <div class="section-card mx-auto" style="max-width: 560px;">
            <h1 class="h5 mb-3">Upload Dataset</h1>
            <p class="text-secondary mb-4">Supports CSV and Excel (.xlsx) files</p>

            <?php if ($error !== ''): ?>
                <div class="alert alert-danger mb-3"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="d-grid gap-3">
                <div class="upload-zone" data-upload-zone>
                    <i class="bi bi-cloud-arrow-up fs-4 text-primary"></i>
                    <p class="mb-2 mt-2">Drag and drop your file here or click to browse</p>
                    <input type="file" class="form-control" accept=".csv,.xlsx" name="dataset_file" data-upload-input required>
                </div>
                <button type="submit" class="btn btn-primary">Upload and Continue</button>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>