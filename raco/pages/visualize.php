<?php

declare(strict_types=1);
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/data.php';
raco_require_login();
$pdo = raco_pdo();

$uploadId = (int) ($_GET['upload_id'] ?? 0);
if ($uploadId <= 0) {
  $stmt = $pdo->prepare('SELECT id FROM uploads WHERE user_id = :user_id ORDER BY id DESC LIMIT 1');
  $stmt->execute(['user_id' => (int) $_SESSION['user_id']]);
  $first = $stmt->fetch();
  if ($first) {
    header('Location: visualize.php?upload_id=' . (int) $first['id']);
    exit;
  }
  header('Location: projects.php');
  exit;
}

$stmt = $pdo->prepare('SELECT id, original_filename FROM uploads WHERE id = :id AND user_id = :user_id');
$stmt->execute(['id' => $uploadId, 'user_id' => (int) $_SESSION['user_id']]);
$upload = $stmt->fetch();
if (!$upload) {
  header('Location: projects.php');
  exit;
}

$dataset = raco_first_dataset_file($pdo, $uploadId, 'cleaned');
if (!$dataset) {
  header('Location: clean.php?upload_id=' . $uploadId);
  exit;
}

$rows = raco_decode_dataset_rows_from_record($dataset);
$columns = $rows ? array_keys($rows[0]) : [];
$numericCols = [];

foreach ($columns as $col) {
  $vals = array_filter(array_map(static fn($r) => $r[$col] ?? null, $rows), static fn($v) => $v !== null && $v !== '');
  if ($vals && count(array_filter($vals, static fn($v) => is_numeric((string) $v))) >= max(1, (int) floor(count($vals) * 0.8))) {
    $numericCols[] = $col;
  }
}

$pageTitle = 'Visualize';
$activePage = 'visualize';
require __DIR__ . '/../includes/header.php';
?>
<div class="app-shell">
  <?php require __DIR__ . '/../includes/sidebar.php'; ?>
  <main class="main-shell">
    <?php require __DIR__ . '/../includes/ribbon.php'; ?>
    <div class="page-wrap">
      <h1 class="page-title">Data Visualization</h1>
      <p class="page-subtitle">Build interactive charts: <?php echo htmlspecialchars((string) $upload['original_filename']); ?></p>

      <div class="viz-container">
        <!-- LEFT SIDEBAR: Chart Palette -->
        <aside class="viz-sidebar">
          <div class="sidebar-title">Chart Types</div>
          <div class="chart-palette">
            <div class="palette-item" draggable="true" data-chart-type="bar">
              <div class="palette-icon">📊</div>
              <div class="palette-label">Bar</div>
            </div>
            <div class="palette-item" draggable="true" data-chart-type="line">
              <div class="palette-icon">📈</div>
              <div class="palette-label">Line</div>
            </div>
            <div class="palette-item" draggable="true" data-chart-type="pie">
              <div class="palette-icon">🥧</div>
              <div class="palette-label">Pie</div>
            </div>
            <div class="palette-item" draggable="true" data-chart-type="scatter">
              <div class="palette-icon">⚫</div>
              <div class="palette-label">Scatter</div>
            </div>
            <div class="palette-item" draggable="true" data-chart-type="histogram">
              <div class="palette-icon">📶</div>
              <div class="palette-label">Histogram</div>
            </div>
          </div>
        </aside>

        <!-- CENTER: Canvas -->
        <div class="viz-canvas-wrapper">
          <div class="viz-canvas" id="vizCanvas">
            <div class="canvas-placeholder">
              <div class="placeholder-icon">📍</div>
              <div class="placeholder-text">Drag a chart type here to get started</div>
            </div>
          </div>
        </div>

        <!-- RIGHT PANEL: Data & Visualization -->
        <aside class="viz-right-panel">
          <div class="panel-tabs" role="tablist">
            <button class="tab-button active" role="tab" aria-selected="true" aria-controls="data-tab" data-tab="data">
              Data
            </button>
            <button class="tab-button" role="tab" aria-selected="false" aria-controls="viz-tab" data-tab="visualization">
              Visualization
            </button>
          </div>

          <!-- Data Tab -->
          <div class="tab-content active" id="data-tab" role="tabpanel">
            <div class="data-summary">
              <div class="data-summary-row">
                <span class="summary-label">Dataset:</span>
                <span class="summary-value"><?php echo htmlspecialchars((string) $upload['original_filename']); ?></span>
              </div>
              <div class="data-summary-row">
                <span class="summary-label">Records:</span>
                <span class="summary-value"><?php echo count($rows); ?></span>
              </div>
              <div class="data-summary-row">
                <span class="summary-label">Columns:</span>
                <span class="summary-value"><?php echo count($columns); ?></span>
              </div>
            </div>
            <div class="data-table-wrapper">
              <table class="data-table">
                <thead>
                  <tr>
                    <?php foreach ($columns as $col): ?>
                      <th><?php echo htmlspecialchars($col); ?></th>
                    <?php endforeach; ?>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach (array_slice($rows, 0, 50) as $row): ?>
                    <tr>
                      <?php foreach ($columns as $col): ?>
                        <td><?php echo htmlspecialchars((string) ($row[$col] ?? '')); ?></td>
                      <?php endforeach; ?>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Visualization Tab -->
          <div class="tab-content" id="viz-tab" role="tabpanel">
            <div id="vizSummary" class="viz-summary">
              <div class="summary-empty">No charts created yet</div>
            </div>
          </div>
        </aside>
      </div>
    </div>
  </main>
</div>

<link rel="stylesheet" href="../assets/css/visualize.css">
<script>
  window.RACO_UPLOAD_ID = <?php echo (int) $uploadId; ?>;
  window.RACO_COLUMNS = <?php echo json_encode($columns, JSON_UNESCAPED_UNICODE); ?>;
  window.RACO_NUMERIC_COLS = <?php echo json_encode($numericCols, JSON_UNESCAPED_UNICODE); ?>;
  window.RACO_DATASET_ROWS = <?php echo json_encode($rows, JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="../assets/js/visualize.js"></script>

</body>

</html>