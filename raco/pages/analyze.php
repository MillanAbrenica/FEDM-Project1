<?php

declare(strict_types=1);
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/data.php';
raco_require_login();
$pdo = raco_pdo();

$uploadId = (int) ($_GET['upload_id'] ?? 0);
if ($uploadId <= 0) {
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

$origDs = raco_first_dataset_file($pdo, $uploadId, 'original');
$cleanDs = raco_first_dataset_file($pdo, $uploadId, 'cleaned');
if (!$origDs || !$cleanDs) {
  header('Location: clean.php?upload_id=' . $uploadId);
  exit;
}

$origRows = raco_decode_dataset_rows_from_record($origDs);
$cleanRows = raco_decode_dataset_rows_from_record($cleanDs);
$headers = $origRows ? array_keys($origRows[0]) : ($cleanRows ? array_keys($cleanRows[0]) : []);

$stmt = $pdo->prepare('SELECT rows_before, rows_after, nulls_filled, duplicates_removed, values_modified, quality_score, operations_applied, cleaned_at FROM cleaning_jobs WHERE upload_id = :upload_id ORDER BY id DESC LIMIT 1');
$stmt->execute(['upload_id' => $uploadId]);
$job = $stmt->fetch();

$flags = [];
foreach ($headers as $h) {
  $vals = array_map(static fn($r) => $r[$h] ?? null, $cleanRows);
  $nulls = count(array_filter($vals, static fn($v) => $v === null || $v === ''));
  $uniq = count(array_unique(array_map(static fn($v) => (string) $v, array_filter($vals, static fn($v) => $v !== null && $v !== ''))));
  if (count($cleanRows) > 0 && $nulls / count($cleanRows) > 0.10) $flags[] = $h . ' has >10% null values';
  if ($uniq > max(20, (int) floor(count($cleanRows) * 0.5))) $flags[] = $h . ' has high cardinality';
}

raco_log_activity($pdo, (int) $_SESSION['user_id'], $uploadId, 'analyze', 'Viewed analysis page for upload #' . $uploadId);

$pageTitle = 'Analyze';
$activePage = 'projects';
require __DIR__ . '/../includes/header.php';
?>
<div class="app-shell">
  <?php require __DIR__ . '/../includes/sidebar.php'; ?>
  <main class="main-shell">
    <?php require __DIR__ . '/../includes/ribbon.php'; ?>
    <div class="page-wrap">
      <h1 class="page-title">Analysis & Comparison</h1>
      <p class="page-subtitle">Original vs cleaned summary for <?php echo htmlspecialchars((string) $upload['original_filename']); ?></p>

      <div class="grid-4" style="margin-bottom:16px;">
        <div class="card">
          <div class="kpi"><?php echo (int) ($job['rows_before'] ?? count($origRows)); ?></div>
          <div class="kpi-label">Rows Before</div>
        </div>
        <div class="card">
          <div class="kpi"><?php echo (int) ($job['rows_after'] ?? count($cleanRows)); ?></div>
          <div class="kpi-label">Rows After</div>
        </div>
        <div class="card">
          <div class="kpi"><?php echo (int) ($job['nulls_filled'] ?? 0); ?></div>
          <div class="kpi-label">Nulls Filled</div>
        </div>
        <div class="card">
          <div class="kpi"><?php echo htmlspecialchars((string) ($job['quality_score'] ?? 0)); ?>%</div>
          <div class="kpi-label">Quality Score</div>
        </div>
      </div>

      <div class="grid-2">
        <section class="card">
          <h3>Column Diff (Top 30)</h3>
          <div class="table-wrap" style="margin-top:10px;">
            <table data-interactive="1">
              <thead>
                <tr>
                  <th>Column</th>
                  <th>Original Sample</th>
                  <th>Cleaned Sample</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach (array_slice($headers, 0, 30) as $h): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($h); ?></td>
                    <td><?php echo htmlspecialchars((string) ($origRows[0][$h] ?? '')); ?></td>
                    <td><?php echo htmlspecialchars((string) ($cleanRows[0][$h] ?? '')); ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </section>

        <section class="card">
          <h3>Trend Detection & Flags</h3>
          <ul style="padding-left:18px; margin-top:10px;">
            <?php foreach ($flags as $f): ?><li><?php echo htmlspecialchars($f); ?></li><?php endforeach; ?>
            <?php if (!$flags): ?><li>No strong data quality flags detected.</li><?php endif; ?>
          </ul>

          <h3 style="margin-top:16px;">Operations Applied</h3>
          <pre style="white-space:pre-wrap; font-size:12px; background:#f8f9fc; border:1px solid var(--line); padding:8px; border-radius:8px;"><?php echo htmlspecialchars((string) ($job['operations_applied'] ?? '{}')); ?></pre>

          <a class="btn btn-primary" href="visualize.php?upload_id=<?php echo $uploadId; ?>">Go to Visualization</a>
        </section>
      </div>
    </div>
  </main>
</div>
<script src="../assets/js/table.js"></script>
</body>

</html>