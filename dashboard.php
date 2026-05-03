<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$requiredSessionKeys = ['cleaned_dataset'];
require_once __DIR__ . '/includes/session_guard.php';
require_once __DIR__ . '/includes/functions.php';

$cleaned = $_SESSION['cleaned_dataset'];
$headers = $cleaned['headers'];
$rows = $cleaned['rows'];
$dataTypes = $cleaned['data_types'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'download_csv') {
    $csv = generateCsvString($rows, $headers);
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="cleaned_dataset.csv"');
    echo $csv;
    exit;
}

$numericHeaders = array_values(array_filter($headers, static fn($header) => in_array($dataTypes[$header] ?? 'string', ['integer', 'float'], true)));
$qualityScore = calculateDataQualityScore($rows, $headers);
$scoreClass = $qualityScore >= 90 ? 'text-bg-success' : ($qualityScore >= 70 ? 'text-bg-warning' : 'text-bg-danger');

$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/header.php';
$current_step = 5;
require_once __DIR__ . '/includes/step_indicator.php';
?>

<div class="row g-3">
    <div class="col-12 col-lg-3">
        <div class="section-card h-100">
            <h1 class="h6 mb-3">Chart Controls</h1>

            <h2 class="h6 small text-uppercase text-secondary">X-axis Variable</h2>
            <div data-x-columns class="mb-3">
                <?php foreach ($headers as $index => $header): ?>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="x_column_selector" value="<?php echo htmlspecialchars($header); ?>" id="x_<?php echo md5($header); ?>" <?php echo $index === 0 ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="x_<?php echo md5($header); ?>"><?php echo htmlspecialchars($header); ?></label>
                    </div>
                <?php endforeach; ?>
            </div>

            <h2 class="h6 small text-uppercase text-secondary">Y-axis Variable</h2>
            <div data-y-columns class="mb-3">
                <?php foreach ($headers as $index => $header): ?>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="y_column_selector" value="<?php echo htmlspecialchars($header); ?>" id="y_<?php echo md5($header); ?>" <?php echo $index === 0 ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="y_<?php echo md5($header); ?>"><?php echo htmlspecialchars($header); ?></label>
                    </div>
                <?php endforeach; ?>
            </div>

            <h2 class="h6 small text-uppercase text-secondary">Chart Type</h2>
            <div class="mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="chart_type" id="chartBar" value="bar" checked>
                    <label class="form-check-label" for="chartBar">Bar</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="chart_type" id="chartLine" value="line">
                    <label class="form-check-label" for="chartLine">Line</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="chart_type" id="chartPie" value="pie">
                    <label class="form-check-label" for="chartPie">Pie</label>
                </div>
            </div>

            <form method="POST" class="d-grid">
                <input type="hidden" name="action" value="download_csv">
                <button type="submit" class="btn btn-primary">Download Cleaned CSV</button>
            </form>
        </div>
    </div>

    <div class="col-12 col-lg-9">
        <div class="section-card mb-3">
            <h2 class="h6 mb-3">Interactive Visualization</h2>
            <div style="height: 420px;">
                <canvas id="mainChart"></canvas>
            </div>
        </div>

        <div class="section-card">
            <h2 class="h6 mb-3">Data Quality Score</h2>
            <span class="badge <?php echo $scoreClass; ?> mb-2">
                <?php echo $qualityScore >= 90 ? 'Excellent' : ($qualityScore >= 70 ? 'Fair' : 'Needs Improvement'); ?>
            </span>
            <div class="quality-score"><?php echo number_format($qualityScore, 2); ?>%</div>
            <p class="text-secondary mb-0">Formula: complete rows / total rows × 100</p>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>