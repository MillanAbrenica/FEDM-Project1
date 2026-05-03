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

$profile = buildProfile($rows, $headers, $dataTypes);
$stats = computeStats($rows, $headers, $dataTypes);

$pageTitle = 'Profile';
require_once __DIR__ . '/includes/header.php';
$current_step = 2;
require_once __DIR__ . '/includes/step_indicator.php';
?>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="section-card h-100">
            <div class="text-secondary small">Total Rows</div>
            <div class="h4 mb-0"><?php echo number_format($profile['summary']['total_rows']); ?></div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="section-card h-100">
            <div class="text-secondary small">Total Columns</div>
            <div class="h4 mb-0"><?php echo number_format($profile['summary']['total_columns']); ?></div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="section-card h-100">
            <div class="text-secondary small">Missing Values</div>
            <div class="h4 mb-0"><?php echo number_format($profile['summary']['missing_values']); ?></div>
            <span class="badge text-bg-warning mt-2"><?php echo number_format($profile['summary']['missing_percent'], 2); ?>%</span>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="section-card h-100">
            <div class="text-secondary small">Duplicate Rows</div>
            <div class="h4 mb-0"><?php echo number_format($profile['summary']['duplicate_rows']); ?></div>
            <span class="badge text-bg-danger mt-2">Quality Risk</span>
        </div>
    </div>
</div>

<div class="section-card mb-4">
    <h2 class="h6 mb-3">Columns Profile</h2>
    <div class="table-wrap">
        <table class="table table-sm table-striped table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Column Name</th>
                    <th>Data Type</th>
                    <th>Non-Null Count</th>
                    <th>Null Count</th>
                    <th>Unique Values</th>
                    <th>Sample Values (first 3)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($profile['columns'] as $column): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($column['name']); ?></td>
                        <td><span class="badge text-bg-secondary"><?php echo htmlspecialchars($column['type']); ?></span></td>
                        <td><?php echo number_format($column['non_null']); ?></td>
                        <td><?php echo number_format($column['null_count']); ?></td>
                        <td><?php echo number_format($column['unique_count']); ?></td>
                        <td><?php echo htmlspecialchars(implode(', ', $column['samples'])); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="section-card mb-4">
    <h2 class="h6 mb-3">Basic Statistics (Numeric Columns)</h2>
    <div class="table-wrap">
        <table class="table table-sm table-striped table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Column</th>
                    <th>Min</th>
                    <th>Max</th>
                    <th>Mean</th>
                    <th>Median</th>
                    <th>Std Dev</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($stats === []): ?>
                    <tr>
                        <td colspan="6" class="text-center text-secondary">No numeric columns detected.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($stats as $column => $stat): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($column); ?></td>
                            <td><?php echo $stat['min'] !== null ? number_format((float) $stat['min'], 4) : '-'; ?></td>
                            <td><?php echo $stat['max'] !== null ? number_format((float) $stat['max'], 4) : '-'; ?></td>
                            <td><?php echo $stat['mean'] !== null ? number_format((float) $stat['mean'], 4) : '-'; ?></td>
                            <td><?php echo $stat['median'] !== null ? number_format((float) $stat['median'], 4) : '-'; ?></td>
                            <td><?php echo $stat['std_dev'] !== null ? number_format((float) $stat['std_dev'], 4) : '-'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="d-flex flex-wrap gap-2">
    <a href="index.php" class="btn btn-outline-primary">Back to Upload</a>
    <a href="clean.php" class="btn btn-primary">Proceed to Clean</a>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>