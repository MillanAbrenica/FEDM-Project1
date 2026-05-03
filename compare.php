<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$requiredSessionKeys = ['dataset', 'cleaned_dataset'];
require_once __DIR__ . '/includes/session_guard.php';
require_once __DIR__ . '/includes/functions.php';

$original = $_SESSION['dataset'];
$cleaned = $_SESSION['cleaned_dataset'];
$cleanSummary = $_SESSION['clean_summary'] ?? [];
$cleanDiff = $_SESSION['clean_diff'] ?? ['changed_cells' => [], 'removed_row_ids' => []];

$headers = $original['headers'];
$originalRows = $original['rows'];
$cleanedRows = $cleaned['rows'];

$perPage = 25;
$originalPage = max(1, (int) ($_GET['opage'] ?? 1));
$cleanedPage = max(1, (int) ($_GET['cpage'] ?? 1));

$originalTotalPages = max(1, (int) ceil(count($originalRows) / $perPage));
$cleanedTotalPages = max(1, (int) ceil(count($cleanedRows) / $perPage));

$originalPage = min($originalPage, $originalTotalPages);
$cleanedPage = min($cleanedPage, $cleanedTotalPages);

$originalSlice = array_slice($originalRows, ($originalPage - 1) * $perPage, $perPage);
$cleanedSlice = array_slice($cleanedRows, ($cleanedPage - 1) * $perPage, $perPage);

$removedIds = $cleanDiff['removed_row_ids'] ?? [];
$changedCells = $cleanDiff['changed_cells'] ?? [];

$originalNullCount = buildProfile($originalRows, $headers, $original['data_types'])['summary']['missing_values'];
$cleanedNullCount = buildProfile($cleanedRows, $headers, $cleaned['data_types'])['summary']['missing_values'];

$pageTitle = 'Compare';
require_once __DIR__ . '/includes/header.php';
$current_step = 3;
require_once __DIR__ . '/includes/step_indicator.php';
?>

<div class="section-card mb-4">
    <h1 class="h6 mb-2">Summary of Changes</h1>
    <p class="mb-0">
        <?php echo number_format((int) ($cleanSummary['rows_removed'] ?? 0)); ?> rows removed,
        <?php echo number_format((int) ($cleanSummary['values_filled'] ?? 0)); ?> values filled,
        <?php echo number_format((int) ($cleanSummary['duplicates_dropped'] ?? 0)); ?> duplicates dropped.
    </p>
</div>

<div class="row g-3">
    <div class="col-12 col-xl-6">
        <div class="section-card h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h6 mb-0">Original Dataset</h2>
                <span class="badge text-bg-secondary">Page <?php echo $originalPage; ?>/<?php echo $originalTotalPages; ?></span>
            </div>

            <div class="d-flex flex-wrap gap-2 mb-3">
                <span class="badge text-bg-secondary">Rows: <?php echo number_format(count($originalRows)); ?></span>
                <span class="badge text-bg-secondary">Columns: <?php echo number_format(count($headers)); ?></span>
                <span class="badge text-bg-warning">Nulls: <?php echo number_format($originalNullCount); ?></span>
            </div>

            <div class="table-wrap">
                <table class="table table-sm table-striped table-hover align-middle mb-2">
                    <thead>
                        <tr>
                            <?php foreach ($headers as $header): ?>
                                <th><?php echo htmlspecialchars($header); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($originalSlice as $row): ?>
                            <?php $isRemoved = in_array((int) $row['__row_id'], $removedIds, true); ?>
                            <tr class="<?php echo $isRemoved ? 'removed-row' : ''; ?>">
                                <?php foreach ($headers as $header): ?>
                                    <td><?php echo htmlspecialchars((string) ($row[$header] ?? '')); ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-between">
                <a class="btn btn-outline-secondary btn-sm <?php echo $originalPage <= 1 ? 'disabled' : ''; ?>" href="?opage=<?php echo max(1, $originalPage - 1); ?>&cpage=<?php echo $cleanedPage; ?>">Previous</a>
                <a class="btn btn-outline-secondary btn-sm <?php echo $originalPage >= $originalTotalPages ? 'disabled' : ''; ?>" href="?opage=<?php echo min($originalTotalPages, $originalPage + 1); ?>&cpage=<?php echo $cleanedPage; ?>">Next</a>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-6">
        <div class="section-card h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h6 mb-0">Cleaned Dataset</h2>
                <span class="badge text-bg-primary">Page <?php echo $cleanedPage; ?>/<?php echo $cleanedTotalPages; ?></span>
            </div>

            <div class="d-flex flex-wrap gap-2 mb-3">
                <span class="badge text-bg-secondary">Rows: <?php echo number_format(count($cleanedRows)); ?></span>
                <span class="badge text-bg-secondary">Columns: <?php echo number_format(count($headers)); ?></span>
                <span class="badge text-bg-success">Nulls: <?php echo number_format($cleanedNullCount); ?></span>
            </div>

            <div class="table-wrap">
                <table class="table table-sm table-striped table-hover align-middle mb-2">
                    <thead>
                        <tr>
                            <?php foreach ($headers as $header): ?>
                                <th><?php echo htmlspecialchars($header); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cleanedSlice as $row): ?>
                            <tr>
                                <?php
                                $rowId = (int) ($row['__row_id'] ?? -1);
                                foreach ($headers as $header):
                                    $isChanged = !empty($changedCells[$rowId][$header]);
                                ?>
                                    <td class="<?php echo $isChanged ? 'changed-cell' : ''; ?>"><?php echo htmlspecialchars((string) ($row[$header] ?? '')); ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-between">
                <a class="btn btn-outline-secondary btn-sm <?php echo $cleanedPage <= 1 ? 'disabled' : ''; ?>" href="?opage=<?php echo $originalPage; ?>&cpage=<?php echo max(1, $cleanedPage - 1); ?>">Previous</a>
                <a class="btn btn-outline-secondary btn-sm <?php echo $cleanedPage >= $cleanedTotalPages ? 'disabled' : ''; ?>" href="?opage=<?php echo $originalPage; ?>&cpage=<?php echo min($cleanedTotalPages, $cleanedPage + 1); ?>">Next</a>
            </div>
        </div>
    </div>
</div>

<div class="mt-4">
    <a href="analyze.php" class="btn btn-primary">Proceed to Analyze</a>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>