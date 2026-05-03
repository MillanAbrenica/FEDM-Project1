<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

/**
 * Build a PDO connection using environment variables with XAMPP-friendly defaults.
 * Returns null if the database is unavailable so the app can continue with session-only mode.
 */
function getDbConnection(): ?PDO
{
    static $pdo = null;
    static $attempted = false;

    if ($attempted) {
        return $pdo;
    }

    $attempted = true;

    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $port = getenv('DB_PORT') ?: '3306';
    $dbName = getenv('DB_NAME') ?: 'dataclean_pro';
    $user = getenv('DB_USER') ?: 'root';
    $pass = getenv('DB_PASS') ?: '';

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $dbName);

    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (Throwable $exception) {
        $pdo = null;
    }

    return $pdo;
}

/**
 * Resolve or create a lightweight guest user for tracking activity and uploads.
 */
function getOrCreateSessionUserId(): ?int
{
    if (!empty($_SESSION['db_user_id'])) {
        return (int) $_SESSION['db_user_id'];
    }

    $pdo = getDbConnection();
    if ($pdo === null) {
        return null;
    }

    $sessionKey = session_id();
    $suffix = substr(hash('sha256', $sessionKey), 0, 10);
    $username = 'guest_' . $suffix;
    $email = $username . '@local.dataclean';

    $selectStmt = $pdo->prepare('SELECT id FROM users WHERE username = :username LIMIT 1');
    $selectStmt->execute(['username' => $username]);
    $existingId = $selectStmt->fetchColumn();

    if ($existingId !== false) {
        $_SESSION['db_user_id'] = (int) $existingId;
        return (int) $existingId;
    }

    $insertStmt = $pdo->prepare(
        'INSERT INTO users (username, email, password_hash) VALUES (:username, :email, :password_hash)'
    );
    $insertStmt->execute([
        'username' => $username,
        'email' => $email,
        'password_hash' => password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
    ]);

    $userId = (int) $pdo->lastInsertId();
    $_SESSION['db_user_id'] = $userId;
    return $userId;
}

/**
 * Insert an upload record and return its primary key.
 */
function persistUploadRecord(int $userId, string $originalFilename, string $fileType, int $rowCount, int $colCount): ?int
{
    $pdo = getDbConnection();
    if ($pdo === null) {
        return null;
    }

    $storedFilename = date('Ymd_His') . '_' . bin2hex(random_bytes(5)) . '_' . basename($originalFilename);
    $stmt = $pdo->prepare(
        'INSERT INTO uploads (user_id, original_filename, stored_filename, file_type, row_count, col_count, upload_status)
         VALUES (:user_id, :original_filename, :stored_filename, :file_type, :row_count, :col_count, :upload_status)'
    );
    $stmt->execute([
        'user_id' => $userId,
        'original_filename' => $originalFilename,
        'stored_filename' => $storedFilename,
        'file_type' => $fileType,
        'row_count' => $rowCount,
        'col_count' => $colCount,
        'upload_status' => 'profiled',
    ]);

    return (int) $pdo->lastInsertId();
}

/**
 * Store original/cleaned dataset snapshots.
 */
function persistDataset(int $uploadId, string $datasetType, array $headers, array $rows, ?int $cleaningJobId = null): void
{
    $pdo = getDbConnection();
    if ($pdo === null) {
        return;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO datasets (upload_id, cleaning_job_id, dataset_type, column_headers, row_data, row_count)
         VALUES (:upload_id, :cleaning_job_id, :dataset_type, :column_headers, :row_data, :row_count)'
    );
    $stmt->execute([
        'upload_id' => $uploadId,
        'cleaning_job_id' => $cleaningJobId,
        'dataset_type' => $datasetType,
        'column_headers' => json_encode($headers, JSON_UNESCAPED_UNICODE),
        'row_data' => json_encode($rows, JSON_UNESCAPED_UNICODE),
        'row_count' => count($rows),
    ]);
}

/**
 * Store column profiling metrics from the current dataset snapshot.
 */
function persistColumnProfiles(int $uploadId, array $headers, array $rows, array $dataTypes): void
{
    $pdo = getDbConnection();
    if ($pdo === null) {
        return;
    }

    $stats = computeStats($rows, $headers, $dataTypes);
    $profile = buildProfile($rows, $headers, $dataTypes);
    $profileByColumn = [];
    foreach ($profile['columns'] as $columnProfile) {
        $profileByColumn[$columnProfile['name']] = $columnProfile;
    }

    $deleteStmt = $pdo->prepare('DELETE FROM column_profiles WHERE upload_id = :upload_id');
    $deleteStmt->execute(['upload_id' => $uploadId]);

    $insertStmt = $pdo->prepare(
        'INSERT INTO column_profiles (
            upload_id,
            column_name,
            detected_type,
            null_count,
            non_null_count,
            unique_count,
            min_value,
            max_value,
            mean_value,
            median_value,
            std_dev,
            sample_values
        ) VALUES (
            :upload_id,
            :column_name,
            :detected_type,
            :null_count,
            :non_null_count,
            :unique_count,
            :min_value,
            :max_value,
            :mean_value,
            :median_value,
            :std_dev,
            :sample_values
        )'
    );

    foreach ($headers as $header) {
        $columnMetrics = $profileByColumn[$header] ?? [
            'null_count' => 0,
            'non_null' => 0,
            'unique_count' => 0,
            'samples' => [],
        ];
        $columnStats = $stats[$header] ?? [
            'min' => null,
            'max' => null,
            'mean' => null,
            'median' => null,
            'std_dev' => null,
        ];

        $insertStmt->execute([
            'upload_id' => $uploadId,
            'column_name' => $header,
            'detected_type' => mapDataTypeToSchemaEnum((string) ($dataTypes[$header] ?? 'unknown')),
            'null_count' => (int) $columnMetrics['null_count'],
            'non_null_count' => (int) $columnMetrics['non_null'],
            'unique_count' => (int) $columnMetrics['unique_count'],
            'min_value' => $columnStats['min'] !== null ? (string) $columnStats['min'] : null,
            'max_value' => $columnStats['max'] !== null ? (string) $columnStats['max'] : null,
            'mean_value' => $columnStats['mean'],
            'median_value' => $columnStats['median'],
            'std_dev' => $columnStats['std_dev'],
            'sample_values' => json_encode($columnMetrics['samples'], JSON_UNESCAPED_UNICODE),
        ]);
    }
}

/**
 * Persist cleaning job summary and return its identifier.
 */
function persistCleaningJob(
    int $uploadId,
    array $operations,
    int $rowsBefore,
    int $rowsAfter,
    array $summary,
    float $qualityScore
): ?int {
    $pdo = getDbConnection();
    if ($pdo === null) {
        return null;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO cleaning_jobs (
            upload_id,
            operations_applied,
            rows_before,
            rows_after,
            nulls_filled,
            duplicates_removed,
            values_modified,
            quality_score
        ) VALUES (
            :upload_id,
            :operations_applied,
            :rows_before,
            :rows_after,
            :nulls_filled,
            :duplicates_removed,
            :values_modified,
            :quality_score
        )'
    );

    $stmt->execute([
        'upload_id' => $uploadId,
        'operations_applied' => json_encode($operations, JSON_UNESCAPED_UNICODE),
        'rows_before' => $rowsBefore,
        'rows_after' => $rowsAfter,
        'nulls_filled' => (int) ($summary['values_filled'] ?? 0),
        'duplicates_removed' => (int) ($summary['duplicates_dropped'] ?? 0),
        'values_modified' => (int) ($summary['values_filled'] ?? 0),
        'quality_score' => round($qualityScore, 2),
    ]);

    return (int) $pdo->lastInsertId();
}

/**
 * Persist activity events for auditability.
 */
function persistActivityLog(int $userId, ?int $uploadId, string $action, ?string $detail = null): void
{
    $pdo = getDbConnection();
    if ($pdo === null) {
        return;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO activity_log (user_id, upload_id, action, detail) VALUES (:user_id, :upload_id, :action, :detail)'
    );
    $stmt->execute([
        'user_id' => $userId,
        'upload_id' => $uploadId,
        'action' => $action,
        'detail' => $detail,
    ]);
}

/**
 * Update upload status if a DB record exists for the current session workflow.
 */
function updateUploadStatus(int $uploadId, string $status): void
{
    $pdo = getDbConnection();
    if ($pdo === null) {
        return;
    }

    $allowedStatuses = ['pending', 'profiled', 'cleaned', 'analyzed'];
    if (!in_array($status, $allowedStatuses, true)) {
        return;
    }

    $stmt = $pdo->prepare('UPDATE uploads SET upload_status = :upload_status WHERE id = :id');
    $stmt->execute([
        'upload_status' => $status,
        'id' => $uploadId,
    ]);
}

/**
 * Map app-inferred type names to the database enum constraints.
 */
function mapDataTypeToSchemaEnum(string $type): string
{
    $normalized = strtolower(trim($type));
    $allowed = ['integer', 'float', 'string', 'date', 'boolean', 'unknown'];

    return in_array($normalized, $allowed, true) ? $normalized : 'unknown';
}

/**
 * Parse CSV file into normalized tabular data.
 * Input: Absolute temporary CSV file path.
 * Output: Array with keys headers (string[]) and rows (associative row arrays with __row_id).
 */
function parseCSV(string $filePath): array
{
    if (!is_readable($filePath)) {
        throw new RuntimeException('Uploaded CSV file cannot be read.');
    }

    $handle = fopen($filePath, 'r');
    if ($handle === false) {
        throw new RuntimeException('Failed to open CSV file.');
    }

    $rawHeaders = fgetcsv($handle);
    if ($rawHeaders === false) {
        fclose($handle);
        throw new RuntimeException('CSV file appears to be empty.');
    }

    $headers = normalizeHeaders($rawHeaders);
    $rows = [];
    $rowId = 0;

    while (($data = fgetcsv($handle)) !== false) {
        if (count(array_filter($data, static fn($value) => trim((string) $value) !== '')) === 0) {
            continue;
        }

        $normalizedRow = [];
        foreach ($headers as $index => $header) {
            $value = $data[$index] ?? null;
            $normalizedRow[$header] = normalizeValue($value);
        }
        $normalizedRow['__row_id'] = $rowId;
        $rows[] = $normalizedRow;
        $rowId++;
    }

    fclose($handle);

    return [
        'headers' => $headers,
        'rows' => $rows,
    ];
}

/**
 * Parse XLSX file via PhpSpreadsheet into normalized tabular data.
 * Input: Absolute temporary XLSX file path.
 * Output: Array with keys headers (string[]) and rows (associative row arrays with __row_id).
 */
function parseExcel(string $filePath): array
{
    $ioFactoryClass = '\\PhpOffice\\PhpSpreadsheet\\IOFactory';

    if (!class_exists($ioFactoryClass)) {
        throw new RuntimeException('PhpSpreadsheet is not installed. Run composer install first.');
    }

    if (!is_readable($filePath)) {
        throw new RuntimeException('Uploaded Excel file cannot be read.');
    }

    $spreadsheet = $ioFactoryClass::load($filePath);
    $sheet = $spreadsheet->getActiveSheet();
    $rowsData = $sheet->toArray(null, true, true, false);

    if (empty($rowsData)) {
        throw new RuntimeException('Excel file appears to be empty.');
    }

    $rawHeaders = array_shift($rowsData);
    $headers = normalizeHeaders($rawHeaders);

    $rows = [];
    $rowId = 0;
    foreach ($rowsData as $row) {
        if (count(array_filter($row, static fn($value) => trim((string) $value) !== '')) === 0) {
            continue;
        }

        $normalizedRow = [];
        foreach ($headers as $index => $header) {
            $value = $row[$index] ?? null;
            $normalizedRow[$header] = normalizeValue($value);
        }
        $normalizedRow['__row_id'] = $rowId;
        $rows[] = $normalizedRow;
        $rowId++;
    }

    return [
        'headers' => $headers,
        'rows' => $rows,
    ];
}

/**
 * Detect semantic data type per column from non-null cell values.
 * Input: Row arrays and column headers.
 * Output: Associative map of column => integer|float|string|date|boolean.
 */
function detectDataTypes(array $rows, array $headers): array
{
    $result = [];

    foreach ($headers as $header) {
        $values = [];
        foreach ($rows as $row) {
            $value = $row[$header] ?? null;
            if ($value === null || $value === '') {
                continue;
            }
            $values[] = trim((string) $value);
        }

        if ($values === []) {
            $result[$header] = 'string';
            continue;
        }

        $isInt = true;
        $isFloat = true;
        $isBool = true;
        $isDate = true;

        foreach ($values as $value) {
            $lower = strtolower($value);

            if (!preg_match('/^-?\d+$/', $value)) {
                $isInt = false;
            }

            if (!is_numeric($value)) {
                $isFloat = false;
            }

            if (!in_array($lower, ['true', 'false', 'yes', 'no', '1', '0'], true)) {
                $isBool = false;
            }

            if (strtotime($value) === false) {
                $isDate = false;
            }
        }

        if ($isInt) {
            $result[$header] = 'integer';
        } elseif ($isFloat) {
            $result[$header] = 'float';
        } elseif ($isBool) {
            $result[$header] = 'boolean';
        } elseif ($isDate) {
            $result[$header] = 'date';
        } else {
            $result[$header] = 'string';
        }
    }

    return $result;
}

/**
 * Compute numeric descriptive statistics per numeric column.
 * Input: Row arrays, headers, and detected types.
 * Output: Map of numeric column => min|max|mean|median|std_dev.
 */
function computeStats(array $rows, array $headers, array $dataTypes): array
{
    $stats = [];

    foreach ($headers as $header) {
        if (!in_array($dataTypes[$header] ?? 'string', ['integer', 'float'], true)) {
            continue;
        }

        $values = [];
        foreach ($rows as $row) {
            $raw = $row[$header] ?? null;
            if ($raw === null || $raw === '' || !is_numeric((string) $raw)) {
                continue;
            }
            $values[] = (float) $raw;
        }

        if ($values === []) {
            $stats[$header] = [
                'min' => null,
                'max' => null,
                'mean' => null,
                'median' => null,
                'std_dev' => null,
            ];
            continue;
        }

        sort($values);
        $count = count($values);
        $mean = array_sum($values) / $count;
        $median = computeMedian($values);

        $variance = 0.0;
        foreach ($values as $value) {
            $variance += ($value - $mean) ** 2;
        }
        $variance /= $count;

        $stats[$header] = [
            'min' => min($values),
            'max' => max($values),
            'mean' => $mean,
            'median' => $median,
            'std_dev' => sqrt($variance),
        ];
    }

    return $stats;
}

/**
 * Build row-level and column-level profile metrics.
 * Input: Row arrays, headers, and detected types.
 * Output: Dataset profile arrays for summary cards and columns table.
 */
function buildProfile(array $rows, array $headers, array $dataTypes): array
{
    $totalRows = count($rows);
    $totalColumns = count($headers);
    $totalCells = max(1, $totalRows * max(1, $totalColumns));

    $missingCount = 0;
    $duplicateCount = 0;
    $seenRows = [];
    $columns = [];

    foreach ($rows as $row) {
        $signaturePayload = [];
        foreach ($headers as $header) {
            $value = $row[$header] ?? null;
            if ($value === null || $value === '') {
                $missingCount++;
            }
            $signaturePayload[] = (string) ($value ?? '');
        }

        $signature = implode('|', $signaturePayload);
        if (isset($seenRows[$signature])) {
            $duplicateCount++;
        }
        $seenRows[$signature] = true;
    }

    foreach ($headers as $header) {
        $nonNull = 0;
        $nullCount = 0;
        $uniqueValues = [];
        $samples = [];

        foreach ($rows as $row) {
            $value = $row[$header] ?? null;
            if ($value === null || $value === '') {
                $nullCount++;
                continue;
            }

            $nonNull++;
            $stringValue = (string) $value;
            $uniqueValues[$stringValue] = true;
            if (count($samples) < 3 && !in_array($stringValue, $samples, true)) {
                $samples[] = $stringValue;
            }
        }

        $columns[] = [
            'name' => $header,
            'type' => $dataTypes[$header] ?? 'string',
            'non_null' => $nonNull,
            'null_count' => $nullCount,
            'unique_count' => count($uniqueValues),
            'samples' => $samples,
        ];
    }

    return [
        'summary' => [
            'total_rows' => $totalRows,
            'total_columns' => $totalColumns,
            'missing_values' => $missingCount,
            'missing_percent' => ($missingCount / $totalCells) * 100,
            'duplicate_rows' => $duplicateCount,
        ],
        'columns' => $columns,
    ];
}

/**
 * Apply selected cleaning operations while preserving original row mapping.
 * Input: Original rows, headers, cleaning options, and detected data types.
 * Output: Cleaned rows plus summary counts and diff metadata for comparison view.
 */
function applyCleaning(array $rows, array $headers, array $options, array $dataTypes): array
{
    $workingRows = array_values($rows);
    $changedCells = [];
    $removedRowIds = [];

    $summary = [
        'rows_removed' => 0,
        'values_filled' => 0,
        'duplicates_dropped' => 0,
        'null_rows_dropped' => 0,
        'invalid_rows_removed' => 0,
    ];

    if (!empty($options['drop_null_rows'])) {
        $kept = [];
        foreach ($workingRows as $row) {
            $hasNull = false;
            foreach ($headers as $header) {
                if (($row[$header] ?? null) === null || $row[$header] === '') {
                    $hasNull = true;
                    break;
                }
            }

            if ($hasNull) {
                $removedRowIds[] = (int) $row['__row_id'];
                $summary['rows_removed']++;
                $summary['null_rows_dropped']++;
                continue;
            }
            $kept[] = $row;
        }
        $workingRows = $kept;
    }

    if (!empty($options['fill_mean'])) {
        $means = [];
        foreach ($headers as $header) {
            if (!in_array($dataTypes[$header] ?? 'string', ['integer', 'float'], true)) {
                continue;
            }

            $values = [];
            foreach ($workingRows as $row) {
                $value = $row[$header] ?? null;
                if ($value !== null && $value !== '' && is_numeric((string) $value)) {
                    $values[] = (float) $value;
                }
            }

            if ($values !== []) {
                $means[$header] = array_sum($values) / count($values);
            }
        }

        foreach ($workingRows as &$row) {
            foreach ($means as $header => $mean) {
                if (($row[$header] ?? null) === null || $row[$header] === '') {
                    $newValue = ($dataTypes[$header] ?? 'float') === 'integer' ? (string) (int) round($mean) : (string) round($mean, 4);
                    markChangedCell($changedCells, (int) $row['__row_id'], $header, $row[$header] ?? null, $newValue, $summary);
                    $row[$header] = $newValue;
                }
            }
        }
        unset($row);
    }

    if (!empty($options['fill_mode'])) {
        $modes = [];
        foreach ($headers as $header) {
            if (in_array($dataTypes[$header] ?? 'string', ['integer', 'float'], true)) {
                continue;
            }

            $frequency = [];
            foreach ($workingRows as $row) {
                $value = $row[$header] ?? null;
                if ($value === null || $value === '') {
                    continue;
                }
                $key = (string) $value;
                $frequency[$key] = ($frequency[$key] ?? 0) + 1;
            }

            if ($frequency !== []) {
                arsort($frequency);
                $modes[$header] = array_key_first($frequency);
            }
        }

        foreach ($workingRows as &$row) {
            foreach ($modes as $header => $mode) {
                if (($row[$header] ?? null) === null || $row[$header] === '') {
                    markChangedCell($changedCells, (int) $row['__row_id'], $header, $row[$header] ?? null, $mode, $summary);
                    $row[$header] = $mode;
                }
            }
        }
        unset($row);
    }

    if (!empty($options['fill_custom']) && is_array($options['fill_custom'])) {
        foreach ($workingRows as &$row) {
            foreach ($options['fill_custom'] as $header => $customValue) {
                if (!in_array($header, $headers, true)) {
                    continue;
                }

                if ($customValue === '' || $customValue === null) {
                    continue;
                }

                if (($row[$header] ?? null) === null || $row[$header] === '') {
                    $newValue = (string) $customValue;
                    markChangedCell($changedCells, (int) $row['__row_id'], $header, $row[$header] ?? null, $newValue, $summary);
                    $row[$header] = $newValue;
                }
            }
        }
        unset($row);
    }

    if (!empty($options['remove_duplicates'])) {
        $seen = [];
        $deduped = [];

        foreach ($workingRows as $row) {
            $signatureData = [];
            foreach ($headers as $header) {
                $signatureData[] = (string) ($row[$header] ?? '');
            }
            $signature = implode('|', $signatureData);

            if (isset($seen[$signature])) {
                $removedRowIds[] = (int) $row['__row_id'];
                $summary['rows_removed']++;
                $summary['duplicates_dropped']++;
                continue;
            }

            $seen[$signature] = true;
            $deduped[] = $row;
        }

        $workingRows = $deduped;
    }

    if (!empty($options['cast_types']) && is_array($options['cast_types'])) {
        foreach ($workingRows as &$row) {
            foreach ($options['cast_types'] as $header => $targetType) {
                if (!in_array($header, $headers, true) || $targetType === '') {
                    continue;
                }

                $oldValue = $row[$header] ?? null;
                if ($oldValue === null || $oldValue === '') {
                    continue;
                }

                $newValue = castValue((string) $oldValue, (string) $targetType);
                if ($newValue !== $oldValue) {
                    markChangedCell($changedCells, (int) $row['__row_id'], $header, $oldValue, $newValue, $summary, false);
                    $row[$header] = $newValue;
                }
            }
        }
        unset($row);
    }

    if (!empty($options['trim_strings'])) {
        foreach ($workingRows as &$row) {
            foreach ($headers as $header) {
                if (($row[$header] ?? null) === null) {
                    continue;
                }

                $oldValue = (string) $row[$header];
                $newValue = trim($oldValue);
                if ($newValue !== $oldValue) {
                    markChangedCell($changedCells, (int) $row['__row_id'], $header, $oldValue, $newValue, $summary, false);
                    $row[$header] = $newValue;
                }
            }
        }
        unset($row);
    }

    if (!empty($options['string_case']) && in_array($options['string_case'], ['lower', 'upper', 'title'], true)) {
        foreach ($workingRows as &$row) {
            foreach ($headers as $header) {
                $value = $row[$header] ?? null;
                if ($value === null || $value === '') {
                    continue;
                }

                if (($dataTypes[$header] ?? 'string') !== 'string') {
                    continue;
                }

                $oldValue = (string) $value;
                $newValue = match ($options['string_case']) {
                    'lower' => mb_strtolower($oldValue),
                    'upper' => mb_strtoupper($oldValue),
                    default => mb_convert_case($oldValue, MB_CASE_TITLE),
                };

                if ($newValue !== $oldValue) {
                    markChangedCell($changedCells, (int) $row['__row_id'], $header, $oldValue, $newValue, $summary, false);
                    $row[$header] = $newValue;
                }
            }
        }
        unset($row);
    }

    if (!empty($options['standardize_date'])) {
        foreach ($workingRows as &$row) {
            foreach ($headers as $header) {
                $value = $row[$header] ?? null;
                if ($value === null || $value === '') {
                    continue;
                }

                if (($dataTypes[$header] ?? 'string') !== 'date') {
                    continue;
                }

                $timestamp = strtotime((string) $value);
                if ($timestamp === false) {
                    continue;
                }

                $oldValue = (string) $value;
                $newValue = date('Y-m-d', $timestamp);
                if ($newValue !== $oldValue) {
                    markChangedCell($changedCells, (int) $row['__row_id'], $header, $oldValue, $newValue, $summary, false);
                    $row[$header] = $newValue;
                }
            }
        }
        unset($row);
    }

    if (!empty($options['numeric_ranges']) && is_array($options['numeric_ranges'])) {
        $filtered = [];

        foreach ($workingRows as $row) {
            $remove = false;
            foreach ($options['numeric_ranges'] as $header => $range) {
                if (!in_array($header, $headers, true)) {
                    continue;
                }

                $value = $row[$header] ?? null;
                if ($value === null || $value === '' || !is_numeric((string) $value)) {
                    continue;
                }

                $min = $range['min'] ?? '';
                $max = $range['max'] ?? '';

                if ($min !== '' && is_numeric((string) $min) && (float) $value < (float) $min) {
                    $remove = true;
                    break;
                }

                if ($max !== '' && is_numeric((string) $max) && (float) $value > (float) $max) {
                    $remove = true;
                    break;
                }
            }

            if ($remove) {
                $removedRowIds[] = (int) $row['__row_id'];
                $summary['rows_removed']++;
                $summary['invalid_rows_removed']++;
                continue;
            }

            $filtered[] = $row;
        }

        $workingRows = $filtered;
    }

    if (!empty($options['regex_pattern'])) {
        $pattern = (string) $options['regex_pattern'];
        $validPattern = @preg_match($pattern, '') !== false;

        if ($validPattern) {
            $filtered = [];
            foreach ($workingRows as $row) {
                $remove = false;
                foreach ($headers as $header) {
                    $value = (string) ($row[$header] ?? '');
                    if ($value !== '' && preg_match($pattern, $value) === 1) {
                        $remove = true;
                        break;
                    }
                }

                if ($remove) {
                    $removedRowIds[] = (int) $row['__row_id'];
                    $summary['rows_removed']++;
                    $summary['invalid_rows_removed']++;
                    continue;
                }

                $filtered[] = $row;
            }
            $workingRows = $filtered;
        }
    }

    $removedRowIds = array_values(array_unique($removedRowIds));

    return [
        'rows' => array_values($workingRows),
        'summary' => $summary,
        'changed_cells' => $changedCells,
        'removed_row_ids' => $removedRowIds,
    ];
}

/**
 * Compute top N frequent values for categorical columns.
 * Input: Rows, headers, types, and top limit.
 * Output: Map column => [value => frequency].
 */
function getTopFrequencies(array $rows, array $headers, array $dataTypes, int $limit = 5): array
{
    $result = [];

    foreach ($headers as $header) {
        if (in_array($dataTypes[$header] ?? 'string', ['integer', 'float'], true)) {
            continue;
        }

        $freq = [];
        foreach ($rows as $row) {
            $value = $row[$header] ?? null;
            if ($value === null || $value === '') {
                continue;
            }
            $key = (string) $value;
            $freq[$key] = ($freq[$key] ?? 0) + 1;
        }

        if ($freq === []) {
            continue;
        }

        arsort($freq);
        $result[$header] = array_slice($freq, 0, $limit, true);
    }

    return $result;
}

/**
 * Detect trend direction for each date + numeric column pair using slope sign.
 * Input: Rows, headers, and detected types.
 * Output: List of pair-wise trend objects with slope and direction.
 */
function detectTrends(array $rows, array $headers, array $dataTypes): array
{
    $dateColumns = [];
    $numericColumns = [];

    foreach ($headers as $header) {
        $type = $dataTypes[$header] ?? 'string';
        if ($type === 'date') {
            $dateColumns[] = $header;
        }
        if (in_array($type, ['integer', 'float'], true)) {
            $numericColumns[] = $header;
        }
    }

    $trends = [];

    foreach ($dateColumns as $dateCol) {
        foreach ($numericColumns as $numCol) {
            $pairs = [];
            foreach ($rows as $row) {
                $dateValue = $row[$dateCol] ?? null;
                $numValue = $row[$numCol] ?? null;
                if ($dateValue === null || $dateValue === '' || $numValue === null || $numValue === '' || !is_numeric((string) $numValue)) {
                    continue;
                }

                $timestamp = strtotime((string) $dateValue);
                if ($timestamp === false) {
                    continue;
                }

                $pairs[] = [
                    'time' => $timestamp,
                    'value' => (float) $numValue,
                ];
            }

            if (count($pairs) < 2) {
                continue;
            }

            usort($pairs, static fn($a, $b) => $a['time'] <=> $b['time']);

            $xValues = [];
            $yValues = [];
            foreach ($pairs as $index => $pair) {
                $xValues[] = (float) $index;
                $yValues[] = $pair['value'];
            }

            $slope = linearRegressionSlope($xValues, $yValues);
            $direction = 'stable';
            $symbol = '→';

            if ($slope > 0.01) {
                $direction = 'increasing';
                $symbol = '↑';
            } elseif ($slope < -0.01) {
                $direction = 'decreasing';
                $symbol = '↓';
            }

            $trends[] = [
                'date_column' => $dateCol,
                'numeric_column' => $numCol,
                'slope' => $slope,
                'direction' => $direction,
                'symbol' => $symbol,
            ];
        }
    }

    return $trends;
}

/**
 * Calculate dataset quality score as complete rows percentage.
 * Input: Rows and headers.
 * Output: Percentage score between 0 and 100.
 */
function calculateDataQualityScore(array $rows, array $headers): float
{
    $total = count($rows);
    if ($total === 0) {
        return 0.0;
    }

    $complete = 0;
    foreach ($rows as $row) {
        $isComplete = true;
        foreach ($headers as $header) {
            $value = $row[$header] ?? null;
            if ($value === null || $value === '') {
                $isComplete = false;
                break;
            }
        }

        if ($isComplete) {
            $complete++;
        }
    }

    return ($complete / $total) * 100;
}

/**
 * Convert row array data into downloadable CSV string.
 * Input: Rows and headers.
 * Output: CSV content as string.
 */
function generateCsvString(array $rows, array $headers): string
{
    $handle = fopen('php://temp', 'r+');
    fputcsv($handle, $headers);

    foreach ($rows as $row) {
        $line = [];
        foreach ($headers as $header) {
            $line[] = $row[$header] ?? '';
        }
        fputcsv($handle, $line);
    }

    rewind($handle);
    $csv = stream_get_contents($handle);
    fclose($handle);

    return (string) $csv;
}

/**
 * Normalize and uniquify incoming header labels.
 * Input: Raw header row values.
 * Output: Sanitized unique header names.
 */
function normalizeHeaders(array $rawHeaders): array
{
    $headers = [];
    $used = [];

    foreach ($rawHeaders as $index => $header) {
        $base = trim((string) $header);
        if ($base === '') {
            $base = 'Column_' . ($index + 1);
        }

        $candidate = $base;
        $counter = 1;
        while (isset($used[$candidate])) {
            $candidate = $base . '_' . $counter;
            $counter++;
        }

        $used[$candidate] = true;
        $headers[] = $candidate;
    }

    return $headers;
}

/**
 * Normalize scalar input values from parser into nullable strings.
 * Input: Mixed cell value.
 * Output: Null for blank values, otherwise trimmed string.
 */
function normalizeValue(mixed $value): ?string
{
    if ($value === null) {
        return null;
    }

    $stringValue = trim((string) $value);
    return $stringValue === '' ? null : $stringValue;
}

/**
 * Mark a cell as changed for comparison rendering and update summary counts.
 * Input: Diff map reference, row id, column, old/new values, summary reference.
 * Output: Mutates diff map and summary in place.
 */
function markChangedCell(array &$changedCells, int $rowId, string $column, mixed $oldValue, mixed $newValue, array &$summary, bool $countAsFill = true): void
{
    if ((string) ($oldValue ?? '') === (string) ($newValue ?? '')) {
        return;
    }

    $changedCells[$rowId][$column] = true;
    if ($countAsFill) {
        $summary['values_filled']++;
    }
}

/**
 * Cast a cell value into the selected primitive target type.
 * Input: Raw string and target type.
 * Output: Casted scalar value string or null for invalid conversion.
 */
function castValue(string $value, string $targetType): ?string
{
    return match ($targetType) {
        'string' => $value,
        'integer' => is_numeric($value) ? (string) (int) round((float) $value) : null,
        'float' => is_numeric($value) ? (string) (float) $value : null,
        'date' => strtotime($value) !== false ? date('Y-m-d', strtotime($value)) : null,
        default => $value,
    };
}

/**
 * Compute median value from sorted numeric array.
 * Input: Sorted numeric values.
 * Output: Median as float.
 */
function computeMedian(array $sortedValues): float
{
    $count = count($sortedValues);
    $middle = intdiv($count, 2);

    if ($count % 2 === 0) {
        return ($sortedValues[$middle - 1] + $sortedValues[$middle]) / 2;
    }

    return (float) $sortedValues[$middle];
}

/**
 * Compute linear regression slope for paired numeric arrays.
 * Input: X values and Y values.
 * Output: Slope coefficient.
 */
function linearRegressionSlope(array $xValues, array $yValues): float
{
    $n = count($xValues);
    if ($n === 0 || $n !== count($yValues)) {
        return 0.0;
    }

    $sumX = array_sum($xValues);
    $sumY = array_sum($yValues);
    $sumXY = 0.0;
    $sumXX = 0.0;

    for ($i = 0; $i < $n; $i++) {
        $sumXY += $xValues[$i] * $yValues[$i];
        $sumXX += $xValues[$i] * $xValues[$i];
    }

    $denominator = ($n * $sumXX) - ($sumX ** 2);
    if (abs($denominator) < 0.0000001) {
        return 0.0;
    }

    return (($n * $sumXY) - ($sumX * $sumY)) / $denominator;
}
