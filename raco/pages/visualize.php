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
$headers = $rows ? array_keys($rows[0]) : [];
$preview = array_slice($rows, 0, 20);

$numericCols = [];
$typeMap = [];
foreach ($headers as $h) {
  $vals = array_values(array_filter(array_map(static fn($r) => $r[$h] ?? null, $rows), static fn($v) => $v !== null && $v !== ''));
  $isNum = $vals && count(array_filter($vals, static fn($v) => is_numeric((string) $v))) >= max(1, (int) floor(count($vals) * 0.8));
  $isDate = !$isNum && $vals && count(array_filter($vals, static fn($v) => strtotime((string) $v) !== false)) >= max(1, (int) floor(count($vals) * 0.8));
  $typeMap[$h] = $isNum ? '123' : ($isDate ? '📅' : 'Abc');
  if ($isNum) $numericCols[] = $h;
}

$outlierBounds = [];
foreach ($numericCols as $h) {
  $nums = array_map('floatval', array_filter(array_map(static fn($r) => $r[$h] ?? null, $rows), static fn($v) => is_numeric((string) $v)));
  if (!$nums) continue;
  $mean = array_sum($nums) / count($nums);
  $var = 0;
  foreach ($nums as $n) {
    $var += ($n - $mean) * ($n - $mean);
  }
  $std = sqrt($var / count($nums));
  $outlierBounds[$h] = ['low' => $mean - 3 * $std, 'high' => $mean + 3 * $std];
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
      <h1 class="page-title">Visualization</h1>
      <p class="page-subtitle">Build charts from cleaned dataset: <?php echo htmlspecialchars((string) $upload['original_filename']); ?></p>

      <div class="grid-2">
        <section class="card">
          <h3>Chart Builder</h3>
          <div class="form-grid" style="margin-top:10px;">
            <div>
              <label>Chart Type</label>
              <select id="chartType">
                <option value="bar">Bar</option>
                <option value="line">Line</option>
                <option value="pie">Pie</option>
                <option value="scatter">Scatter</option>
                <option value="histogram">Histogram</option>
              </select>
            </div>
            <div>
              <label>X-axis</label>
              <select id="xAxis"><?php foreach ($headers as $h): ?><option value="<?php echo htmlspecialchars($h); ?>"><?php echo htmlspecialchars($h); ?></option><?php endforeach; ?></select>
            </div>
            <div>
              <label>Y-axis</label>
              <select id="yAxis"><?php foreach ($numericCols as $h): ?><option value="<?php echo htmlspecialchars($h); ?>"><?php echo htmlspecialchars($h); ?></option><?php endforeach; ?></select>
            </div>
            <div class="row" style="align-items:end;">
              <button class="btn btn-primary" id="renderChartBtn" type="button">Render Chart</button>
              <button class="btn" type="button" onclick="racoSaveChartPng()">Save as PNG</button>
            </div>
          </div>
          <div style="margin-top:12px;"><canvas id="mainChart" height="180"></canvas></div>
          <div style="margin-top:12px;" class="row">
            <a class="btn" href="../actions/export_handler.php?upload_id=<?php echo $uploadId; ?>&type=csv">Export CSV</a>
            <a class="btn" href="../actions/export_handler.php?upload_id=<?php echo $uploadId; ?>&type=json">Export JSON</a>
          </div>
        </section>

        <section class="card">
          <div class="panel-tabs">
            <button class="active" type="button">Data</button>
            <button type="button">Visualization</button>
          </div>
          <div class="stack" style="margin-top:10px;">
            <div>
              <div class="kpi"><?php echo htmlspecialchars((string) $upload['original_filename']); ?></div>
              <div class="kpi-label">Data Source · <?php echo count($rows); ?> records</div>
            </div>
            <div class="table-wrap">
              <table data-interactive="1" id="vizTable">
                <thead>
                  <tr>
                    <?php foreach ($headers as $h): ?>
                      <th><?php echo htmlspecialchars($h); ?> <span class="text-muted"><?php echo htmlspecialchars($typeMap[$h]); ?></span></th>
                    <?php endforeach; ?>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($preview as $row): ?>
                    <tr>
                      <?php foreach ($headers as $h):
                        $raw = $row[$h] ?? null;
                        $class = 'cell-clean';
                        if ($raw === null || $raw === '') {
                          $class = 'cell-empty';
                        } elseif (isset($outlierBounds[$h]) && is_numeric((string) $raw)) {
                          $v = (float) $raw;
                          if ($v < $outlierBounds[$h]['low'] || $v > $outlierBounds[$h]['high']) {
                            $class = 'cell-outlier';
                          }
                        }
                      ?>
                        <td class="<?php echo $class; ?>" data-editable="1" data-raw="<?php echo htmlspecialchars((string) $raw); ?>"><?php echo htmlspecialchars((string) $raw); ?></td>
                      <?php endforeach; ?>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </section>
      </div>
    </div>
  </main>
</div>

<div id="findModal" class="find-modal">
  <div class="inner">
    <h3>Find & Replace</h3>
    <div class="row"><input type="text" id="findValue" placeholder="Find value"></div>
    <div class="row"><input type="text" id="replaceValue" placeholder="Replace with"></div>
    <div class="row">
      <button id="doReplace" class="btn btn-primary" type="button">Replace All</button>
      <button id="closeFindModal" class="btn" type="button">Close</button>
    </div>
  </div>
</div>

<script>
  window.RACO_DATASET_ROWS = <?php echo json_encode($rows, JSON_UNESCAPED_UNICODE); ?>;
  window.exportCurrent = function(type) {
    window.location.href = '../actions/export_handler.php?upload_id=<?php echo $uploadId; ?>&type=' + encodeURIComponent(type);
  };
  document.getElementById('renderChartBtn').addEventListener('click', function() {
    var type = document.getElementById('chartType').value;
    var x = document.getElementById('xAxis').value;
    var y = document.getElementById('yAxis').value;
    racoRenderChart(window.RACO_DATASET_ROWS, type, x, y);
  });
  if (window.RACO_DATASET_ROWS.length) {
    var y = document.getElementById('yAxis').value;
    var x = document.getElementById('xAxis').value;
    if (x && y) racoRenderChart(window.RACO_DATASET_ROWS, 'bar', x, y);
  }
</script>
<script src="../assets/js/charts.js"></script>
<script src="../assets/js/table.js"></script>
</body>

</html>