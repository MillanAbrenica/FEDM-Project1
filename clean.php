<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$requiredSessionKeys = ['dataset'];
require_once __DIR__ . '/includes/session_guard.php';
require_once __DIR__ . '/includes/functions.php';

$dataset = $_SESSION['dataset'];
$headers = $dataset['headers'];
$rows = $dataset['rows'];
$dataTypes = $dataset['data_types'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fillCustom = $_POST['fill_custom'] ?? [];
    $castTypes = $_POST['cast_types'] ?? [];
    $rangeMin = $_POST['range_min'] ?? [];
    $rangeMax = $_POST['range_max'] ?? [];

    $numericRanges = [];
    foreach ($headers as $header) {
        $minValue = $rangeMin[$header] ?? '';
        $maxValue = $rangeMax[$header] ?? '';
        if ($minValue !== '' || $maxValue !== '') {
            $numericRanges[$header] = [
                'min' => $minValue,
                'max' => $maxValue,
            ];
        }
    }

    $options = [
        'drop_null_rows' => isset($_POST['drop_null_rows']),
        'fill_mean' => isset($_POST['fill_mean']),
        'fill_mode' => isset($_POST['fill_mode']),
        'fill_custom' => is_array($fillCustom) ? $fillCustom : [],
        'remove_duplicates' => isset($_POST['remove_duplicates']),
        'cast_types' => is_array($castTypes) ? $castTypes : [],
        'trim_strings' => isset($_POST['trim_strings']),
        'string_case' => $_POST['string_case'] ?? '',
        'standardize_date' => isset($_POST['standardize_date']),
        'numeric_ranges' => $numericRanges,
        'regex_pattern' => trim((string) ($_POST['regex_pattern'] ?? '')),
    ];

    try {
        $result = applyCleaning($rows, $headers, $options, $dataTypes);
        $cleanedRows = $result['rows'];
        $cleanedTypes = detectDataTypes($cleanedRows, $headers);

        $_SESSION['cleaned_dataset'] = [
            'headers' => $headers,
            'rows' => $cleanedRows,
            'data_types' => $cleanedTypes,
        ];

        $_SESSION['clean_summary'] = $result['summary'];
        $_SESSION['clean_diff'] = [
            'changed_cells' => $result['changed_cells'],
            'removed_row_ids' => $result['removed_row_ids'],
        ];

        $uploadId = isset($_SESSION['upload_id']) ? (int) $_SESSION['upload_id'] : 0;
        if ($uploadId > 0) {
            try {
                $userId = getOrCreateSessionUserId();
                $qualityScore = calculateDataQualityScore($cleanedRows, $headers);
                $cleaningJobId = persistCleaningJob(
                    $uploadId,
                    $options,
                    count($rows),
                    count($cleanedRows),
                    $result['summary'],
                    $qualityScore
                );

                persistDataset($uploadId, 'cleaned', $headers, $cleanedRows, $cleaningJobId);
                persistColumnProfiles($uploadId, $headers, $cleanedRows, $cleanedTypes);
                updateUploadStatus($uploadId, 'cleaned');

                if ($userId !== null) {
                    persistActivityLog($userId, $uploadId, 'clean_dataset', 'Cleaning operations applied and cleaned snapshot saved.');
                }
            } catch (Throwable $dbException) {
                $_SESSION['flash_warning'] = 'Cleaning completed, but database persistence was skipped for this step. Increase MySQL max_allowed_packet (e.g., 64M) for large datasets.';
            }
        }

        $_SESSION['flash_success'] = 'Cleaning operations applied successfully.';
        header('Location: compare.php');
        exit;
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

$pageTitle = 'Clean';
require_once __DIR__ . '/includes/header.php';
$current_step = 3;
require_once __DIR__ . '/includes/step_indicator.php';
?>

<?php if ($error !== ''): ?>
    <div class="alert alert-danger mb-3"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<form method="POST" class="row g-3">
    <div class="col-12 col-lg-4">
        <div class="section-card surface-light">
            <h2 class="h6 mb-3">Cleaning Options</h2>

            <h3 class="h6 small text-uppercase text-secondary">Missing Values</h3>
            <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" id="dropNullRows" name="drop_null_rows">
                <label class="form-check-label" for="dropNullRows">Drop rows with any null</label>
            </div>
            <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" id="fillMean" name="fill_mean">
                <label class="form-check-label" for="fillMean">Fill nulls with mean (numeric columns)</label>
            </div>
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" id="fillMode" name="fill_mode">
                <label class="form-check-label" for="fillMode">Fill nulls with mode (categorical columns)</label>
            </div>

            <h3 class="h6 small text-uppercase text-secondary">Custom Fill Value Per Column</h3>
            <?php foreach ($headers as $header): ?>
                <div class="mb-2">
                    <label class="form-label mb-1" for="fill_custom_<?php echo md5($header); ?>"><?php echo htmlspecialchars($header); ?></label>
                    <input class="form-control form-control-sm" id="fill_custom_<?php echo md5($header); ?>" type="text" name="fill_custom[<?php echo htmlspecialchars($header); ?>]" placeholder="Custom value">
                </div>
            <?php endforeach; ?>

            <hr>
            <h3 class="h6 small text-uppercase text-secondary">Duplicates</h3>
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" id="removeDuplicates" name="remove_duplicates">
                <label class="form-check-label" for="removeDuplicates">Remove exact duplicate rows</label>
            </div>

            <h3 class="h6 small text-uppercase text-secondary">Data Type Conversion</h3>
            <?php foreach ($headers as $header): ?>
                <div class="mb-2">
                    <label class="form-label mb-1" for="cast_<?php echo md5($header); ?>"><?php echo htmlspecialchars($header); ?></label>
                    <select class="form-select form-select-sm" id="cast_<?php echo md5($header); ?>" name="cast_types[<?php echo htmlspecialchars($header); ?>]">
                        <option value="">No cast</option>
                        <option value="string">string</option>
                        <option value="integer">integer</option>
                        <option value="float">float</option>
                        <option value="date">date</option>
                    </select>
                </div>
            <?php endforeach; ?>

            <hr>
            <h3 class="h6 small text-uppercase text-secondary">Format Standardization</h3>
            <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" id="trimStrings" name="trim_strings">
                <label class="form-check-label" for="trimStrings">Trim whitespace from all string values</label>
            </div>
            <div class="mb-2">
                <label class="form-label mb-1">Convert string case</label>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="string_case" id="caseLower" value="lower">
                    <label class="form-check-label" for="caseLower">Lowercase</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="string_case" id="caseUpper" value="upper">
                    <label class="form-check-label" for="caseUpper">Uppercase</label>
                </div>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="radio" name="string_case" id="caseTitle" value="title">
                    <label class="form-check-label" for="caseTitle">Title case</label>
                </div>
            </div>
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" id="standardizeDate" name="standardize_date">
                <label class="form-check-label" for="standardizeDate">Standardize date format to YYYY-MM-DD</label>
            </div>

            <hr>
            <h3 class="h6 small text-uppercase text-secondary">Filter Invalid Data</h3>
            <p class="text-secondary mb-2">Remove rows where numeric column is outside [min, max]</p>
            <?php foreach ($headers as $header): ?>
                <div class="row g-1 mb-2">
                    <div class="col-12"><label class="form-label mb-0"><?php echo htmlspecialchars($header); ?></label></div>
                    <div class="col-6">
                        <input class="form-control form-control-sm" type="number" step="any" name="range_min[<?php echo htmlspecialchars($header); ?>]" placeholder="min">
                    </div>
                    <div class="col-6">
                        <input class="form-control form-control-sm" type="number" step="any" name="range_max[<?php echo htmlspecialchars($header); ?>]" placeholder="max">
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="mb-3">
                <label for="regexPattern" class="form-label mb-1">Remove rows matching regex pattern</label>
                <input type="text" id="regexPattern" class="form-control form-control-sm" name="regex_pattern" placeholder="/pattern/i">
            </div>

            <button type="submit" class="btn btn-primary w-100">Apply Cleaning</button>
        </div>
    </div>

    <div class="col-12 col-lg-8">
        <div class="section-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h6 mb-0">Original Dataset Preview</h2>
                <span class="badge text-bg-secondary"><?php echo number_format(count($rows)); ?> rows</span>
            </div>
            <div class="table-wrap">
                <table class="table table-sm table-striped table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <?php foreach ($headers as $header): ?>
                                <th><?php echo htmlspecialchars($header); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($rows, 0, 25) as $row): ?>
                            <tr>
                                <?php foreach ($headers as $header): ?>
                                    <td><?php echo htmlspecialchars((string) ($row[$header] ?? '')); ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="small text-secondary mt-2 mb-0">Preview limited to first 25 rows.</p>
        </div>
    </div>
</form>

<?php require_once __DIR__ . '/includes/footer.php'; ?>