<?php

declare(strict_types=1);

function raco_parse_csv_file(string $path): array
{
    $handle = fopen($path, 'r');
    if (!$handle) {
        throw new RuntimeException('Cannot read CSV file.');
    }

    $headers = fgetcsv($handle);
    if (!$headers) {
        fclose($handle);
        return ['headers' => [], 'rows' => []];
    }

    $headers = array_map(static function ($h, $idx) {
        $name = trim((string) $h);
        return $name !== '' ? $name : 'Column_' . ($idx + 1);
    }, $headers, array_keys($headers));

    $rows = [];
    while (($line = fgetcsv($handle)) !== false) {
        $row = [];
        foreach ($headers as $i => $key) {
            $val = $line[$i] ?? null;
            $val = is_string($val) ? trim($val) : $val;
            $row[$key] = ($val === '') ? null : $val;
        }
        $rows[] = $row;
    }

    fclose($handle);
    return ['headers' => $headers, 'rows' => $rows];
}

function raco_col_letters_to_index(string $letters): int
{
    $letters = strtoupper($letters);
    $num = 0;
    for ($i = 0; $i < strlen($letters); $i++) {
        $num = $num * 26 + (ord($letters[$i]) - 64);
    }
    return $num - 1;
}

function raco_parse_xlsx_file(string $path): array
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('XLSX import requires ZipArchive extension.');
    }

    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new RuntimeException('Cannot open XLSX file.');
    }

    $shared = [];
    $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($sharedXml !== false) {
        $sx = simplexml_load_string($sharedXml);
        if ($sx && isset($sx->si)) {
            foreach ($sx->si as $si) {
                if (isset($si->t)) {
                    $shared[] = (string) $si->t;
                } elseif (isset($si->r)) {
                    $text = '';
                    foreach ($si->r as $run) {
                        $text .= (string) $run->t;
                    }
                    $shared[] = $text;
                } else {
                    $shared[] = '';
                }
            }
        }
    }

    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    if ($sheetXml === false) {
        $zip->close();
        throw new RuntimeException('XLSX sheet1.xml not found.');
    }

    $sheet = simplexml_load_string($sheetXml);
    if (!$sheet) {
        $zip->close();
        throw new RuntimeException('Invalid XLSX worksheet XML.');
    }

    $rows = [];
    $sheetData = $sheet->sheetData;
    foreach ($sheetData->row as $rowXml) {
        $row = [];
        foreach ($rowXml->c as $c) {
            $ref = (string) $c['r'];
            preg_match('/([A-Z]+)/', $ref, $m);
            $idx = isset($m[1]) ? raco_col_letters_to_index($m[1]) : count($row);
            $type = (string) $c['t'];
            $value = '';
            if (isset($c->v)) {
                $raw = (string) $c->v;
                if ($type === 's') {
                    $sIndex = (int) $raw;
                    $value = $shared[$sIndex] ?? '';
                } else {
                    $value = $raw;
                }
            }
            $row[$idx] = $value;
        }
        if ($row) {
            ksort($row);
            $rows[] = array_values($row);
        }
    }

    $zip->close();

    if (!$rows) {
        return ['headers' => [], 'rows' => []];
    }

    $headers = array_map(static function ($h, $idx) {
        $name = trim((string) $h);
        return $name !== '' ? $name : 'Column_' . ($idx + 1);
    }, $rows[0], array_keys($rows[0]));

    $assoc = [];
    for ($i = 1; $i < count($rows); $i++) {
        $r = [];
        foreach ($headers as $j => $name) {
            $val = $rows[$i][$j] ?? null;
            if (is_string($val)) {
                $val = trim($val);
            }
            $r[$name] = ($val === '') ? null : $val;
        }
        $assoc[] = $r;
    }

    return ['headers' => $headers, 'rows' => $assoc];
}

function raco_detect_column_type(array $values): string
{
    $vals = array_values(array_filter($values, static fn($v) => $v !== null && $v !== ''));
    if (!$vals) {
        return 'unknown';
    }

    $isBool = true;
    $isInt = true;
    $isFloat = true;
    $dateCount = 0;

    foreach ($vals as $v) {
        $s = strtolower(trim((string) $v));
        if (!in_array($s, ['true', 'false', '0', '1', 'yes', 'no'], true)) {
            $isBool = false;
        }
        if (!preg_match('/^-?\d+$/', (string) $v)) {
            $isInt = false;
        }
        if (!is_numeric((string) $v)) {
            $isFloat = false;
        }
        if (strtotime((string) $v) !== false) {
            $dateCount++;
        }
    }

    if ($isBool) return 'boolean';
    if ($isInt) return 'integer';
    if ($isFloat) return 'float';
    if ($dateCount >= (int) ceil(count($vals) * 0.8)) return 'date';
    return 'string';
}

function raco_column_stats(array $values, string $type): array
{
    $vals = array_values(array_filter($values, static fn($v) => $v !== null && $v !== ''));
    $nullCount = count($values) - count($vals);
    $unique = count(array_unique(array_map(static fn($v) => (string) $v, $vals)));

    $min = null;
    $max = null;
    $mean = null;
    $median = null;
    $std = null;

    if (in_array($type, ['integer', 'float'], true)) {
        $nums = array_map('floatval', array_filter($vals, static fn($v) => is_numeric((string) $v)));
        if ($nums) {
            sort($nums);
            $count = count($nums);
            $sum = array_sum($nums);
            $mean = $sum / $count;
            $min = (string) min($nums);
            $max = (string) max($nums);
            $median = ($count % 2 === 0)
                ? ($nums[$count / 2 - 1] + $nums[$count / 2]) / 2
                : $nums[(int) floor($count / 2)];
            $var = 0.0;
            foreach ($nums as $n) {
                $var += ($n - $mean) * ($n - $mean);
            }
            $std = sqrt($var / $count);
        }
    }

    $samples = array_slice(array_values(array_unique(array_map(static fn($v) => (string) $v, $vals))), 0, 5);

    return [
        'null_count' => $nullCount,
        'non_null_count' => count($vals),
        'unique_count' => $unique,
        'min_value' => $min,
        'max_value' => $max,
        'mean_value' => $mean,
        'median_value' => $median,
        'std_dev' => $std,
        'sample_values' => $samples,
    ];
}

function raco_build_profiles(array $headers, array $rows): array
{
    $profiles = [];
    foreach ($headers as $h) {
        $colVals = array_map(static fn($r) => $r[$h] ?? null, $rows);
        $type = raco_detect_column_type($colVals);
        $stats = raco_column_stats($colVals, $type);
        $profiles[] = array_merge(['column_name' => $h, 'detected_type' => $type], $stats);
    }
    return $profiles;
}

function raco_table_has_column(PDO $pdo, string $table, string $column): bool
{
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $stmt = $pdo->prepare('SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :table_name AND column_name = :column_name LIMIT 1');
    $stmt->execute([
        'table_name' => $table,
        'column_name' => $column,
    ]);
    $cache[$key] = (bool) $stmt->fetch();
    return $cache[$key];
}

function raco_decode_dataset_rows_from_record(array $record): array
{
    if (isset($record['row_data']) && $record['row_data'] !== null && $record['row_data'] !== '') {
        $rows = json_decode((string) $record['row_data'], true);
        return is_array($rows) ? $rows : [];
    }

    if (!empty($record['storage_path'])) {
        $path = __DIR__ . '/../' . ltrim((string) $record['storage_path'], '/\\');
        if (is_file($path)) {
            return raco_load_dataset_rows($path);
        }
    }

    return [];
}

function raco_load_dataset_rows(string $path): array
{
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if ($ext === 'csv') {
        return raco_parse_csv_file($path)['rows'];
    }
    if ($ext === 'xlsx') {
        return raco_parse_xlsx_file($path)['rows'];
    }

    $json = file_get_contents($path);
    $rows = json_decode((string) $json, true);
    return is_array($rows) ? $rows : [];
}

function raco_first_dataset_file(PDO $pdo, int $uploadId, string $datasetType): ?array
{
    $hasStorage = raco_table_has_column($pdo, 'datasets', 'storage_path');
    $pathSelect = $hasStorage ? ', storage_path' : '';
    $stmt = $pdo->prepare('SELECT id, upload_id, cleaning_job_id, dataset_type, column_headers, row_data, row_count' . $pathSelect . ', created_at FROM datasets WHERE upload_id = :upload_id AND dataset_type = :dataset_type ORDER BY id DESC LIMIT 1');
    $stmt->execute(['upload_id' => $uploadId, 'dataset_type' => $datasetType]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function raco_get_dataset_rows(PDO $pdo, int $uploadId, string $datasetType): array
{
    $record = raco_first_dataset_file($pdo, $uploadId, $datasetType);
    if (!$record) {
        return [];
    }

    return raco_decode_dataset_rows_from_record($record);
}
