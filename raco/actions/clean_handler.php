<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/data.php';
require_once __DIR__ . '/../config/clean_utils.php';

raco_start_session();
if (empty($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/projects.php');
    exit;
}

$uploadId = (int) ($_POST['upload_id'] ?? 0);
if ($uploadId <= 0) {
    header('Location: ../pages/projects.php');
    exit;
}

$pdo = raco_pdo();
$stmt = $pdo->prepare('SELECT id, original_filename, file_type FROM uploads WHERE id = :id AND user_id = :user_id');
$stmt->execute(['id' => $uploadId, 'user_id' => (int) $_SESSION['user_id']]);
$upload = $stmt->fetch();
if (!$upload) {
    header('Location: ../pages/projects.php');
    exit;
}

$original = raco_first_dataset_file($pdo, $uploadId, 'original');
if (!$original) {
    header('Location: ../pages/profile.php?upload_id=' . $uploadId);
    exit;
}

$rows = raco_decode_dataset_rows_from_record($original);
if (!$rows) {
    header('Location: ../pages/clean.php?upload_id=' . $uploadId . '&error=empty');
    exit;
}

$headers = array_keys((array) $rows[0]);
$rowsBefore = count($rows);
$nullsFilled = 0;
$rowsRemoved = 0;
$valuesModified = 0;
$outliersRemoved = 0;

// Parse form inputs
$missingStrategy = $_POST['missing_strategy'] ?? [];
$missingCustom = $_POST['missing_custom'] ?? [];
$castType = $_POST['cast_type'] ?? [];
$decimals = $_POST['decimals'] ?? [];
$rangeMin = $_POST['range_min'] ?? [];
$rangeMax = $_POST['range_max'] ?? [];
$regexPattern = trim((string) ($_POST['regex_pattern'] ?? ''));
$removeDuplicates = isset($_POST['remove_duplicates']);
$trimWhitespace = isset($_POST['trim_whitespace']);
$normalizeSpaces = isset($_POST['normalize_spaces']);
$removeAccents = isset($_POST['remove_accents']);
$stringCase = (string) ($_POST['string_case'] ?? 'none');
$standardizeDates = isset($_POST['standardize_dates']);
$detectOutliers = isset($_POST['detect_outliers']);
$outlierMethod = (string) ($_POST['outlier_method'] ?? 'iqr');
$iqrMultiplier = (float) ($_POST['iqr_multiplier'] ?? 1.5);
$validateEmails = isset($_POST['validate_emails']);
$emailColumns = array_filter(array_map('trim', explode(',', (string) ($_POST['email_columns'] ?? ''))));
$validateUrls = isset($_POST['validate_urls']);
$urlColumns = array_filter(array_map('trim', explode(',', (string) ($_POST['url_columns'] ?? ''))));
$validatePhones = isset($_POST['validate_phones']);
$phoneColumns = array_filter(array_map('trim', explode(',', (string) ($_POST['phone_columns'] ?? ''))));

// ============================================================================
// STEP 1: TEXT NORMALIZATION (Accents, Spaces)
// ============================================================================

if ($removeAccents) {
    foreach ($rows as &$row) {
        foreach ($headers as $h) {
            if (is_string($row[$h] ?? null)) {
                $old = $row[$h];
                $new = raco_remove_accents($old);
                if ($old !== $new) {
                    $row[$h] = $new;
                    $valuesModified++;
                }
            }
        }
    }
    unset($row);
}

if ($normalizeSpaces) {
    foreach ($rows as &$row) {
        foreach ($headers as $h) {
            if (is_string($row[$h] ?? null)) {
                $old = $row[$h];
                $new = raco_normalize_whitespace($old);
                if ($old !== $new) {
                    $row[$h] = $new;
                    $valuesModified++;
                }
            }
        }
    }
    unset($row);
}

if ($trimWhitespace) {
    foreach ($rows as &$row) {
        foreach ($headers as $h) {
            if (is_string($row[$h] ?? null)) {
                $old = $row[$h];
                $new = trim($old);
                if ($old !== $new) {
                    $row[$h] = $new;
                    $valuesModified++;
                }
            }
        }
    }
    unset($row);
}

// ============================================================================
// STEP 2: STRING CASE CONVERSION
// ============================================================================

if ($stringCase === 'upper') {
    foreach ($rows as &$row) {
        foreach ($headers as $h) {
            if (is_string($row[$h] ?? null)) {
                $old = $row[$h];
                $new = strtoupper($old);
                if ($old !== $new) {
                    $row[$h] = $new;
                    $valuesModified++;
                }
            }
        }
    }
    unset($row);
} elseif ($stringCase === 'lower') {
    foreach ($rows as &$row) {
        foreach ($headers as $h) {
            if (is_string($row[$h] ?? null)) {
                $old = $row[$h];
                $new = strtolower($old);
                if ($old !== $new) {
                    $row[$h] = $new;
                    $valuesModified++;
                }
            }
        }
    }
    unset($row);
} elseif ($stringCase === 'title') {
    foreach ($rows as &$row) {
        foreach ($headers as $h) {
            if (is_string($row[$h] ?? null)) {
                $old = $row[$h];
                $new = ucwords(strtolower($old));
                if ($old !== $new) {
                    $row[$h] = $new;
                    $valuesModified++;
                }
            }
        }
    }
    unset($row);
}

// ============================================================================
// STEP 3: DATE STANDARDIZATION
// ============================================================================

if ($standardizeDates) {
    foreach ($rows as &$row) {
        foreach ($headers as $h) {
            $val = $row[$h] ?? null;
            if ($val !== null && $val !== '' && strtotime((string) $val) !== false) {
                $new = date('Y-m-d', strtotime((string) $val));
                if ((string) $val !== $new) {
                    $row[$h] = $new;
                    $valuesModified++;
                }
            }
        }
    }
    unset($row);
}

// ============================================================================
// STEP 4: TYPE CASTING & DECIMAL ROUNDING
// ============================================================================

foreach ($headers as $h) {
    $targetType = (string) ($castType[$h] ?? '');
    if (!in_array($targetType, ['string', 'integer', 'float', 'date'], true)) continue;

    foreach ($rows as &$row) {
        $val = $row[$h] ?? null;
        if ($val === null || $val === '') continue;

        $old = (string) $val;
        $new = $old;

        if ($targetType === 'integer' && is_numeric($old)) {
            $new = (string) ((int) round((float) $old));
        } elseif ($targetType === 'float' && is_numeric($old)) {
            $new = (string) ((float) $old);
        } elseif ($targetType === 'string') {
            $new = (string) $old;
        } elseif ($targetType === 'date' && strtotime($old) !== false) {
            $new = date('Y-m-d', strtotime($old));
        }

        if ($new !== $old) {
            $row[$h] = $new;
            $valuesModified++;
        }
    }
    unset($row);

    // Apply decimal rounding if specified
    $decimalsCount = (int) ($decimals[$h] ?? -1);
    if ($decimalsCount >= 0 && in_array($targetType, ['float', 'integer'], true)) {
        $modified = raco_round_numeric($rows, $h, $decimalsCount);
        $valuesModified += $modified;
    }
}

// ============================================================================
// STEP 5: MISSING VALUE HANDLING
// ============================================================================

foreach ($headers as $h) {
    $strategy = (string) ($missingStrategy[$h] ?? 'none');
    if (!in_array($strategy, ['none', 'mean', 'median', 'mode', 'custom', 'drop'], true)) {
        $strategy = 'none';
    }

    if ($strategy === 'drop') {
        $before = count($rows);
        $rows = array_values(array_filter($rows, static fn($r) => ($r[$h] ?? null) !== null && $r[$h] !== ''));
        $rowsRemoved += ($before - count($rows));
        continue;
    }

    if ($strategy === 'none') {
        continue;
    }

    $nonNull = array_values(array_filter(array_map(static fn($r) => $r[$h] ?? null, $rows), static fn($v) => $v !== null && $v !== ''));
    $fill = null;

    if ($strategy === 'custom') {
        $fill = (string) ($missingCustom[$h] ?? '');
    } elseif ($strategy === 'mode') {
        $freq = [];
        foreach ($nonNull as $v) {
            $key = (string) $v;
            $freq[$key] = ($freq[$key] ?? 0) + 1;
        }
        arsort($freq);
        $fill = (string) (array_key_first($freq) ?? '');
    } else {
        $nums = array_map('floatval', array_filter($nonNull, static fn($v) => is_numeric((string) $v)));
        if ($nums) {
            sort($nums);
            if ($strategy === 'mean') {
                $fill = (string) (array_sum($nums) / count($nums));
            } elseif ($strategy === 'median') {
                $n = count($nums);
                $fill = (string) (($n % 2 === 0) ? (($nums[$n / 2 - 1] + $nums[$n / 2]) / 2) : $nums[(int) floor($n / 2)]);
            }
        }
    }

    if ($fill === null || $fill === '') {
        continue;
    }

    foreach ($rows as &$row) {
        if (($row[$h] ?? null) === null || $row[$h] === '') {
            $row[$h] = $fill;
            $nullsFilled++;
            $valuesModified++;
        }
    }
    unset($row);
}

// ============================================================================
// STEP 6: RANGE FILTERING (Min/Max)
// ============================================================================

foreach ($headers as $h) {
    $min = ($rangeMin[$h] ?? '') !== '' ? (float) $rangeMin[$h] : null;
    $max = ($rangeMax[$h] ?? '') !== '' ? (float) $rangeMax[$h] : null;
    if ($min === null && $max === null) continue;

    $before = count($rows);
    $rows = array_values(array_filter($rows, static function ($row) use ($h, $min, $max) {
        $v = $row[$h] ?? null;
        if ($v === null || $v === '' || !is_numeric((string) $v)) return true;
        $num = (float) $v;
        if ($min !== null && $num < $min) return false;
        if ($max !== null && $num > $max) return false;
        return true;
    }));
    $rowsRemoved += ($before - count($rows));
}

// ============================================================================
// STEP 7: OUTLIER DETECTION & REMOVAL
// ============================================================================

if ($detectOutliers) {
    $before = count($rows);
    $rowsToKeep = [];

    foreach ($rows as $idx => $row) {
        $isOutlier = false;

        foreach ($headers as $h) {
            $val = $row[$h] ?? null;
            if ($val === null || $val === '' || !is_numeric((string) $val)) {
                continue;
            }

            // Collect all numeric values for this column
            $colValues = array_map(
                static fn($r) => $r[$h] ?? null,
                $rows
            );

            if ($outlierMethod === 'zscore') {
                $outlierAnalysis = raco_detect_outliers_zscore($colValues, 3.0);
                if (in_array($idx, $outlierAnalysis['outlier_indices'], true)) {
                    $isOutlier = true;
                    break;
                }
            } else {
                $outlierAnalysis = raco_detect_outliers_iqr($colValues, $iqrMultiplier);
                if (in_array($idx, $outlierAnalysis['outlier_indices'], true)) {
                    $isOutlier = true;
                    break;
                }
            }
        }

        if (!$isOutlier) {
            $rowsToKeep[] = $row;
        }
    }

    $outliersRemoved = $before - count($rowsToKeep);
    $rows = $rowsToKeep;
}

// ============================================================================
// STEP 8: REGEX PATTERN FILTERING
// ============================================================================

if ($regexPattern !== '' && @preg_match($regexPattern, '') !== false) {
    $before = count($rows);
    $rows = array_values(array_filter($rows, static function ($row) use ($regexPattern) {
        $joined = implode(' | ', array_map(static fn($v) => (string) $v, $row));
        return preg_match($regexPattern, $joined) === 1;
    }));
    $rowsRemoved += ($before - count($rows));
}

// ============================================================================
// STEP 9: VALIDATION (Email, URL, Phone)
// ============================================================================

if ($validateEmails && $emailColumns) {
    $before = count($rows);
    $rows = array_values(array_filter($rows, static function ($row) use ($emailColumns) {
        foreach ($emailColumns as $col) {
            if (in_array($col, array_keys($row), true)) {
                $val = $row[$col] ?? '';
                if ($val !== '' && !raco_is_valid_email((string) $val)) {
                    return false;
                }
            }
        }
        return true;
    }));
    $rowsRemoved += ($before - count($rows));
}

if ($validateUrls && $urlColumns) {
    $before = count($rows);
    $rows = array_values(array_filter($rows, static function ($row) use ($urlColumns) {
        foreach ($urlColumns as $col) {
            if (in_array($col, array_keys($row), true)) {
                $val = $row[$col] ?? '';
                if ($val !== '' && !raco_is_valid_url((string) $val)) {
                    return false;
                }
            }
        }
        return true;
    }));
    $rowsRemoved += ($before - count($rows));
}

if ($validatePhones && $phoneColumns) {
    $before = count($rows);
    $rows = array_values(array_filter($rows, static function ($row) use ($phoneColumns) {
        foreach ($phoneColumns as $col) {
            if (in_array($col, array_keys($row), true)) {
                $val = $row[$col] ?? '';
                if ($val !== '' && !raco_is_valid_phone((string) $val)) {
                    return false;
                }
            }
        }
        return true;
    }));
    $rowsRemoved += ($before - count($rows));
}

// ============================================================================
// STEP 10: DUPLICATE REMOVAL
// ============================================================================

if ($removeDuplicates) {
    $seen = [];
    $out = [];
    $dupesRemoved = 0;

    foreach ($rows as $row) {
        $sig = json_encode($row, JSON_UNESCAPED_UNICODE);
        if (isset($seen[$sig])) {
            $dupesRemoved++;
            continue;
        }
        $seen[$sig] = true;
        $out[] = $row;
    }

    $rowsRemoved += $dupesRemoved;
    $rows = $out;
}

// ============================================================================
// FINAL CALCULATIONS & SAVE
// ============================================================================

$rowsAfter = count($rows);
$complete = 0;
foreach ($rows as $row) {
    $ok = true;
    foreach ($headers as $h) {
        if (($row[$h] ?? null) === null || $row[$h] === '') {
            $ok = false;
            break;
        }
    }
    if ($ok) $complete++;
}
$quality = $rowsAfter > 0 ? round(($complete / $rowsAfter) * 100, 2) : 0.0;

$operations = [
    'missing_strategy' => $missingStrategy,
    'remove_duplicates' => $removeDuplicates,
    'cast_type' => $castType,
    'trim_whitespace' => $trimWhitespace,
    'normalize_spaces' => $normalizeSpaces,
    'remove_accents' => $removeAccents,
    'string_case' => $stringCase,
    'standardize_dates' => $standardizeDates,
    'detect_outliers' => $detectOutliers,
    'outlier_method' => $outlierMethod,
    'range_min' => $rangeMin,
    'range_max' => $rangeMax,
    'regex_pattern' => $regexPattern,
    'validate_emails' => $validateEmails,
    'validate_urls' => $validateUrls,
    'validate_phones' => $validatePhones,
];

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare('INSERT INTO cleaning_jobs (upload_id, operations_applied, rows_before, rows_after, nulls_filled, duplicates_removed, values_modified, quality_score) VALUES (:upload_id, :operations_applied, :rows_before, :rows_after, :nulls_filled, :duplicates_removed, :values_modified, :quality_score)');
    $stmt->execute([
        'upload_id' => $uploadId,
        'operations_applied' => json_encode($operations, JSON_UNESCAPED_UNICODE),
        'rows_before' => $rowsBefore,
        'rows_after' => $rowsAfter,
        'nulls_filled' => $nullsFilled,
        'duplicates_removed' => $rowsRemoved + $outliersRemoved,
        'values_modified' => $valuesModified,
        'quality_score' => $quality,
    ]);
    $jobId = (int) $pdo->lastInsertId();

    $stmt = $pdo->prepare('INSERT INTO datasets (upload_id, cleaning_job_id, dataset_type, column_headers, row_data, row_count) VALUES (:upload_id, :cleaning_job_id, :dataset_type, :column_headers, :row_data, :row_count)');
    $stmt->execute([
        'upload_id' => $uploadId,
        'cleaning_job_id' => $jobId,
        'dataset_type' => 'cleaned',
        'column_headers' => json_encode($headers, JSON_UNESCAPED_UNICODE),
        'row_data' => json_encode($rows, JSON_UNESCAPED_UNICODE),
        'row_count' => $rowsAfter,
    ]);

    $pdo->prepare('UPDATE uploads SET upload_status = :status WHERE id = :id')->execute([
        'status' => 'cleaned',
        'id' => $uploadId,
    ]);

    raco_log_activity($pdo, (int) $_SESSION['user_id'], $uploadId, 'clean', 'Applied cleaning operations. Quality score: ' . $quality . '%');
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    header('Location: ../pages/clean.php?upload_id=' . $uploadId . '&error=save');
    exit;
}

header('Location: ../pages/analyze.php?upload_id=' . $uploadId);
exit;
