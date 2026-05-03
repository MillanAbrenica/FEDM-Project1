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

$stats = computeStats($rows, $headers, $dataTypes);
$frequencies = getTopFrequencies($rows, $headers, $dataTypes, 5);
$trends = detectTrends($rows, $headers, $dataTypes);

$uploadId = isset($_SESSION['upload_id']) ? (int) $_SESSION['upload_id'] : 0;
if ($uploadId > 0) {
    updateUploadStatus($uploadId, 'analyzed');

    $userId = getOrCreateSessionUserId();
    if ($userId !== null && empty($_SESSION['analysis_logged_for_upload_' . $uploadId])) {
        persistActivityLog($userId, $uploadId, 'analyze_dataset', 'Analysis view generated for cleaned dataset.');
        $_SESSION['analysis_logged_for_upload_' . $uploadId] = true;
    }
}

$pageTitle = 'Analyze';
require_once __DIR__ . '/includes/header.php';
$current_step = 4;
require_once __DIR__ . '/includes/step_indicator.php';
?>

<div class="section-card mb-4">
    <h1 class="h6 mb-3">Summary Statistics (Cleaned Data)</h1>
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
                        <td colspan="6" class="text-center text-secondary">No numeric columns available.</td>
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

<div class="section-card mb-4">
    <h2 class="h6 mb-3">Top 5 Frequent Values (Categorical)</h2>
    <?php if ($frequencies === []): ?>
        <p class="text-secondary mb-0">No categorical columns detected.</p>
    <?php else: ?>
        <div class="row g-3">
            <?php foreach ($frequencies as $column => $freq): ?>
                <?php
                $maxFrequency = max(array_values($freq));
                ?>
                <div class="col-12 col-lg-6">
                    <div class="surface-light p-3 h-100">
                        <h3 class="h6 mb-3"><?php echo htmlspecialchars($column); ?></h3>
                        <div class="table-wrap">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Value</th>
                                        <th>Count</th>
                                        <th style="min-width: 140px;">Relative</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($freq as $value => $count): ?>
                                        <?php
                                        $width = $maxFrequency > 0 ? ((int) round(((float) $count / (float) $maxFrequency) * 100)) : 0;
                                        ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars((string) $value); ?></td>
                                            <td><?php echo number_format((int) $count); ?></td>
                                            <td>
                                                <div class="progress" style="height: 8px; background-color: #e7eef7;">
                                                    <div class="progress-bar" role="progressbar" style="width: <?php echo $width; ?>%;"></div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<div class="section-card mb-4">
    <h2 class="h6 mb-3">Trend Detection (Date + Numeric Pairs)</h2>
    <div class="table-wrap">
        <table class="table table-sm table-striped table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Date Column</th>
                    <th>Numeric Column</th>
                    <th>Direction</th>
                    <th>Slope</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($trends === []): ?>
                    <tr>
                        <td colspan="4" class="text-center text-secondary">No date + numeric pairs available for trend analysis.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($trends as $trend): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($trend['date_column']); ?></td>
                            <td><?php echo htmlspecialchars($trend['numeric_column']); ?></td>
                            <td>
                                <?php if ($trend['direction'] === 'increasing'): ?>
                                    <span class="badge text-bg-success"><?php echo htmlspecialchars($trend['symbol'] . ' increasing'); ?></span>
                                <?php elseif ($trend['direction'] === 'decreasing'): ?>
                                    <span class="badge text-bg-danger"><?php echo htmlspecialchars($trend['symbol'] . ' decreasing'); ?></span>
                                <?php else: ?>
                                    <span class="badge text-bg-warning"><?php echo htmlspecialchars($trend['symbol'] . ' stable'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo number_format((float) $trend['slope'], 6); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="section-card mb-4">
    <h2 class="h6 mb-3">Auto Interpretations</h2>
    <ul class="mb-0">
        <?php if ($stats === []): ?>
            <li>No numeric interpretation available because numeric columns were not detected.</li>
        <?php else: ?>
            <?php foreach ($stats as $column => $stat): ?>
                <?php
                if ($stat['mean'] === null || $stat['std_dev'] === null) {
                    continue;
                }
                $mean = (float) $stat['mean'];
                $stdDev = (float) $stat['std_dev'];
                $varianceLabel = $stdDev > (abs($mean) * 0.5) ? 'high variance' : 'moderate variance';
                ?>
                <li>
                    Column <?php echo htmlspecialchars($column); ?> has a mean of <?php echo number_format($mean, 4); ?>
                    with <?php echo $varianceLabel; ?> (std dev <?php echo number_format($stdDev, 4); ?>).
                </li>
            <?php endforeach; ?>
        <?php endif; ?>
    </ul>
</div>

<div class="d-flex gap-2">
    <a href="dashboard.php" class="btn btn-primary">View Dashboard</a>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>