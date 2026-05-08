<?php

declare(strict_types=1);
require_once __DIR__ . '/../config/db.php';
raco_require_login();
$pdo = raco_pdo();

$uploadId = (int) ($_GET['upload_id'] ?? 0);
if ($uploadId <= 0) {
  header('Location: projects.php');
  exit;
}

$countCol = raco_upload_column_count_name($pdo);
$timeCol = raco_upload_time_column_name($pdo);
$stmt = $pdo->prepare("SELECT id, original_filename, file_type, row_count, {$countCol} AS column_count, upload_status, {$timeCol} AS uploaded_at FROM uploads WHERE id = :id AND user_id = :user_id");
$stmt->execute(['id' => $uploadId, 'user_id' => (int) $_SESSION['user_id']]);
$upload = $stmt->fetch();
if (!$upload) {
  header('Location: projects.php');
  exit;
}

$stmt = $pdo->prepare('SELECT column_name, detected_type, null_count, non_null_count, unique_count, min_value, max_value, mean_value, median_value, std_dev, sample_values FROM column_profiles WHERE upload_id = :upload_id ORDER BY id ASC');
$stmt->execute(['upload_id' => $uploadId]);
$profiles = $stmt->fetchAll();

$pageTitle = 'Profile';
$activePage = 'projects';
require __DIR__ . '/../includes/header.php';
?>
<div class="app-shell">
  <?php require __DIR__ . '/../includes/sidebar.php'; ?>
  <main class="main-shell">
    <?php require __DIR__ . '/../includes/ribbon.php'; ?>
    <div class="page-wrap">
      <h1 class="page-title">Data Profile</h1>
      <p class="page-subtitle">Upload #<?php echo (int) $upload['id']; ?> · <?php echo htmlspecialchars($upload['original_filename']); ?></p>

      <div class="grid-4" style="margin-bottom: 16px;">
        <div class="card">
          <div class="kpi"><?php echo (int) $upload['row_count']; ?></div>
          <div class="kpi-label">Rows</div>
        </div>
        <div class="card">
          <div class="kpi"><?php echo (int) $upload['column_count']; ?></div>
          <div class="kpi-label">Columns</div>
        </div>
        <div class="card">
          <div class="kpi"><?php echo htmlspecialchars((string) $upload['file_type']); ?></div>
          <div class="kpi-label">File Type</div>
        </div>
        <div class="card">
          <div class="kpi"><?php echo htmlspecialchars((string) $upload['uploaded_at']); ?></div>
          <div class="kpi-label">Uploaded At</div>
        </div>
      </div>

      <section class="card">
        <div class="table-wrap">
          <table data-interactive="1">
            <thead>
              <tr>
                <th>Column</th>
                <th>Detected Type</th>
                <th>Null</th>
                <th>Non-Null</th>
                <th>Unique</th>
                <th>Min</th>
                <th>Max</th>
                <th>Mean</th>
                <th>Std Dev</th>
                <th>Sample Values</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($profiles as $p): ?>
                <tr>
                  <td><?php echo htmlspecialchars((string) $p['column_name']); ?></td>
                  <td><?php echo htmlspecialchars((string) $p['detected_type']); ?></td>
                  <td><?php echo (int) $p['null_count']; ?></td>
                  <td><?php echo (int) $p['non_null_count']; ?></td>
                  <td><?php echo (int) $p['unique_count']; ?></td>
                  <td><?php echo htmlspecialchars((string) ($p['min_value'] ?? '')); ?></td>
                  <td><?php echo htmlspecialchars((string) ($p['max_value'] ?? '')); ?></td>
                  <td><?php echo htmlspecialchars((string) ($p['mean_value'] ?? '')); ?></td>
                  <td><?php echo htmlspecialchars((string) ($p['std_dev'] ?? '')); ?></td>
                  <td><?php echo htmlspecialchars((string) $p['sample_values']); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>

      <div style="margin-top:14px;">
        <a class="btn btn-primary" href="clean.php?upload_id=<?php echo $uploadId; ?>">Proceed to Clean</a>
      </div>
    </div>
  </main>
</div>
<script src="../assets/js/table.js"></script>
</body>

</html>