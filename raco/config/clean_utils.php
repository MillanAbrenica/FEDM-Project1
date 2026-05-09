<?php

declare(strict_types=1);

/**
 * Enhanced Data Cleaning Utilities
 * Provides advanced cleaning functions for the RACO data cleaning module
 */

// ============================================================================
// VALIDATION FUNCTIONS
// ============================================================================

function raco_is_valid_email(string $value): bool
{
    return (bool) filter_var($value, FILTER_VALIDATE_EMAIL);
}

function raco_is_valid_url(string $value): bool
{
    return (bool) filter_var($value, FILTER_VALIDATE_URL);
}

function raco_is_valid_phone(string $value): bool
{
    // Simple pattern: at least 10 digits, allowing common separators
    $cleaned = preg_replace('/[\D]/', '', $value) ?? '';
    return strlen($cleaned) >= 10;
}

function raco_is_valid_ip(string $value): bool
{
    return (bool) filter_var($value, FILTER_VALIDATE_IP);
}

// ============================================================================
// OUTLIER DETECTION FUNCTIONS
// ============================================================================

/**
 * Detect outliers using Interquartile Range (IQR) method
 * @return array With keys: 'outlier_indices', 'lower_bound', 'upper_bound', 'outlier_count'
 */
function raco_detect_outliers_iqr(array $values, float $multiplier = 1.5): array
{
    $nums = array_map('floatval', array_filter(
        array_values($values),
        static fn($v) => $v !== null && $v !== '' && is_numeric((string) $v)
    ));

    if (count($nums) < 4) {
        return [
            'outlier_indices' => [],
            'lower_bound' => null,
            'upper_bound' => null,
            'outlier_count' => 0,
        ];
    }

    sort($nums);
    $count = count($nums);
    $q1Index = (int) floor($count / 4);
    $q3Index = (int) floor($count * 3 / 4);

    $q1 = $nums[$q1Index];
    $q3 = $nums[$q3Index];
    $iqr = $q3 - $q1;

    $lowerBound = $q1 - ($multiplier * $iqr);
    $upperBound = $q3 + ($multiplier * $iqr);

    $outlierIndices = [];
    foreach ($values as $idx => $val) {
        if ($val === null || $val === '' || !is_numeric((string) $val)) {
            continue;
        }
        $num = (float) $val;
        if ($num < $lowerBound || $num > $upperBound) {
            $outlierIndices[] = $idx;
        }
    }

    return [
        'outlier_indices' => $outlierIndices,
        'lower_bound' => $lowerBound,
        'upper_bound' => $upperBound,
        'outlier_count' => count($outlierIndices),
    ];
}

/**
 * Detect outliers using Z-score method
 */
function raco_detect_outliers_zscore(array $values, float $threshold = 3.0): array
{
    $nums = array_map('floatval', array_filter(
        array_values($values),
        static fn($v) => $v !== null && $v !== '' && is_numeric((string) $v)
    ));

    if (count($nums) < 2) {
        return [
            'outlier_indices' => [],
            'threshold' => $threshold,
            'outlier_count' => 0,
        ];
    }

    $mean = array_sum($nums) / count($nums);
    $variance = 0.0;
    foreach ($nums as $n) {
        $variance += ($n - $mean) ** 2;
    }
    $stdDev = sqrt($variance / count($nums));

    if ($stdDev === 0.0) {
        return [
            'outlier_indices' => [],
            'threshold' => $threshold,
            'outlier_count' => 0,
        ];
    }

    $outlierIndices = [];
    foreach ($values as $idx => $val) {
        if ($val === null || $val === '' || !is_numeric((string) $val)) {
            continue;
        }
        $num = (float) $val;
        $zScore = abs(($num - $mean) / $stdDev);
        if ($zScore > $threshold) {
            $outlierIndices[] = $idx;
        }
    }

    return [
        'outlier_indices' => $outlierIndices,
        'threshold' => $threshold,
        'outlier_count' => count($outlierIndices),
    ];
}

// ============================================================================
// DUPLICATE DETECTION & ANALYSIS
// ============================================================================

/**
 * Find duplicate rows with details
 * @return array With keys: 'duplicate_groups', 'total_duplicates', 'unique_rows'
 */
function raco_find_duplicates(array $rows): array
{
    $groups = [];
    $seen = [];
    $uniqueCount = 0;

    foreach ($rows as $idx => $row) {
        $sig = json_encode($row, JSON_UNESCAPED_UNICODE);
        if (!isset($groups[$sig])) {
            $groups[$sig] = [];
            $uniqueCount++;
        }
        $groups[$sig][] = $idx;
        $seen[$sig] = true;
    }

    // Filter to only groups with duplicates
    $duplicateGroups = array_filter(
        $groups,
        static fn($indices) => count($indices) > 1
    );

    $totalDuplicates = array_sum(array_map(
        static fn($indices) => count($indices) - 1,
        $duplicateGroups
    ));

    return [
        'duplicate_groups' => array_values($duplicateGroups),
        'total_duplicates' => $totalDuplicates,
        'unique_rows' => $uniqueCount,
        'duplicate_count' => count($duplicateGroups),
    ];
}

/**
 * Find near-duplicate rows (similar but not exact)
 */
function raco_find_near_duplicates(array $rows, float $threshold = 0.8): array
{
    $candidates = [];
    $count = count($rows);

    for ($i = 0; $i < $count; $i++) {
        for ($j = $i + 1; $j < $count; $j++) {
            $similarity = raco_calculate_row_similarity($rows[$i], $rows[$j]);
            if ($similarity >= $threshold) {
                $candidates[] = [
                    'row1_index' => $i,
                    'row2_index' => $j,
                    'similarity' => $similarity,
                ];
            }
        }
    }

    usort($candidates, static fn($a, $b) => $b['similarity'] <=> $a['similarity']);

    return [
        'near_duplicates' => $candidates,
        'count' => count($candidates),
    ];
}

/**
 * Calculate similarity between two rows (0-1 scale)
 */
function raco_calculate_row_similarity(array $row1, array $row2): float
{
    $keys = array_unique(array_merge(array_keys($row1), array_keys($row2)));
    if (!$keys) {
        return 1.0;
    }

    $matches = 0;
    foreach ($keys as $key) {
        $v1 = (string) ($row1[$key] ?? '');
        $v2 = (string) ($row2[$key] ?? '');
        if (strcasecmp($v1, $v2) === 0) {
            $matches++;
        }
    }

    return $matches / count($keys);
}

// ============================================================================
// DATA QUALITY ANALYSIS
// ============================================================================

/**
 * Generate comprehensive data quality report
 */
function raco_analyze_data_quality(array $headers, array $rows): array
{
    $report = [
        'total_rows' => count($rows),
        'total_columns' => count($headers),
        'total_cells' => count($rows) * count($headers),
        'null_cells' => 0,
        'duplicate_rows' => 0,
        'columns' => [],
    ];

    // Analyze each column
    foreach ($headers as $colName) {
        $colValues = array_map(static fn($r) => $r[$colName] ?? null, $rows);
        $nullCount = count(array_filter($colValues, static fn($v) => $v === null || $v === ''));
        $nonNull = count(array_filter($colValues, static fn($v) => $v !== null && $v !== ''));
        $unique = count(array_unique(array_filter($colValues, static fn($v) => $v !== null && $v !== '')));

        $report['null_cells'] += $nullCount;

        $report['columns'][$colName] = [
            'null_count' => $nullCount,
            'non_null_count' => $nonNull,
            'unique_count' => $unique,
            'null_percentage' => $nonNull === 0 ? 0 : round(($nullCount / count($colValues)) * 100, 2),
            'cardinality' => $nonNull > 0 ? round(($unique / $nonNull) * 100, 2) : 0,
        ];
    }

    // Check for duplicates
    $dupes = raco_find_duplicates($rows);
    $report['duplicate_rows'] = $dupes['total_duplicates'];
    $report['completeness'] = $report['total_cells'] > 0
        ? round((($report['total_cells'] - $report['null_cells']) / $report['total_cells']) * 100, 2)
        : 0;

    return $report;
}

// ============================================================================
// TEXT NORMALIZATION
// ============================================================================

/**
 * Remove accents from text (é → e, ñ → n, etc.)
 */
function raco_remove_accents(string $text): string
{
    $from = 'àáâãäåçèéêëìíîïñòóôõöùúûüýÿ';
    $to = 'aacaeaceeeeiiinooooouuuyy';

    $from .= 'ÀÁÂÃÄÅÇÈÉÊËÌÍÎÏÑÒÓÔÕÖÙÚÛÜÝŸ';
    $to .= 'AACAEACEEEEIIINOOOOOUUUYY';

    return strtr($text, $from, $to);
}

/**
 * Normalize whitespace (trim, collapse multiple spaces)
 */
function raco_normalize_whitespace(string $text): string
{
    return trim(preg_replace('/\s+/', ' ', $text) ?? $text);
}

/**
 * Remove special characters except specified ones
 */
function raco_remove_special_chars(string $text, string $keep = ''): string
{
    $pattern = '/[^a-zA-Z0-9\s' . preg_quote($keep, '/') . ']/';
    return preg_replace($pattern, '', $text) ?? $text;
}

// ============================================================================
// NUMERIC PRECISION
// ============================================================================

/**
 * Round numeric values in a column
 */
function raco_round_numeric(array &$rows, string $column, int $decimals = 2): int
{
    $modified = 0;
    foreach ($rows as &$row) {
        $val = $row[$column] ?? null;
        if ($val !== null && $val !== '' && is_numeric((string) $val)) {
            $newVal = (string) round((float) $val, $decimals);
            if ((string) $val !== $newVal) {
                $row[$column] = $newVal;
                $modified++;
            }
        }
    }
    unset($row);
    return $modified;
}

// ============================================================================
// COMPARISON & STATISTICS
// ============================================================================

/**
 * Calculate before/after cleaning statistics
 */
function raco_calculate_cleaning_impact(array $headersBefore, array $rowsBefore, array $rowsAfter): array
{
    $qualityBefore = raco_analyze_data_quality($headersBefore, $rowsBefore);
    $qualityAfter = raco_analyze_data_quality($headersBefore, $rowsAfter);

    return [
        'before' => $qualityBefore,
        'after' => $qualityAfter,
        'rows_removed' => count($rowsBefore) - count($rowsAfter),
        'cells_filled' => $qualityBefore['null_cells'] - $qualityAfter['null_cells'],
        'completeness_improvement' => $qualityAfter['completeness'] - $qualityBefore['completeness'],
        'duplicate_reduction' => $qualityBefore['duplicate_rows'] - $qualityAfter['duplicate_rows'],
    ];
}
