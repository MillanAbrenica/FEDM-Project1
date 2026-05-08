<?php

declare(strict_types=1);
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/data.php';
raco_require_login();
$pdo = raco_pdo();

$timeCol = raco_upload_time_column_name($pdo);
$countCol = raco_upload_column_count_name($pdo);

$stmt = $pdo->prepare("SELECT id, original_filename, file_type, row_count, {$countCol} AS column_count, upload_status, {$timeCol} AS uploaded_at FROM uploads WHERE user_id = :user_id ORDER BY {$timeCol} DESC LIMIT 10");
$stmt->execute(['user_id' => (int) $_SESSION['user_id']]);
$recent = $stmt->fetchAll();

$previewRows = [];
$previewHeaders = [];
$dataSourceName = 'No dataset selected';
$dataSourceCount = 0;
if ($recent) {
  $firstUpload = (int) $recent[0]['id'];
  $datasetRows = raco_get_dataset_rows($pdo, $firstUpload, 'original');
  if ($datasetRows) {
    $previewHeaders = array_keys($datasetRows[0]);
    $previewRows = array_slice($datasetRows, 0, 5);
    $dataSourceName = (string) $recent[0]['original_filename'];
    $dataSourceCount = count($datasetRows);
  }
}

$pageTitle = 'Workspace';
$activePage = 'workspace';
require __DIR__ . '/../includes/header.php';
?>
<div class="app-shell">
  <?php require __DIR__ . '/../includes/sidebar.php'; ?>
  <main class="main-shell">
    <?php require __DIR__ . '/../includes/ribbon.php'; ?>
    <div class="page-wrap">
      <div class="space-between">
        <div>
          <h1 class="page-title">Welcome to RACO</h1>
          <p class="page-subtitle">Your data analytics platform. Create a project or import data to begin.</p>
        </div>
      </div>

      <div class="grid-4">
        <a class="quick-card" href="import.php">
          <div class="icon"><i class="fa-solid fa-file-circle-plus"></i></div>
          <h3>New Project</h3>
          <p>Create a new data analysis project from scratch.</p>
        </a>
        <a class="quick-card" href="projects.php">
          <div class="icon"><i class="fa-solid fa-folder-open"></i></div>
          <h3>Open Project</h3>
          <p>Open an existing project from your workspace.</p>
        </a>
        <a class="quick-card" href="import.php">
          <div class="icon"><i class="fa-solid fa-upload"></i></div>
          <h3>Import Data</h3>
          <p>Upload CSV or XLSX files for processing.</p>
        </a>
        <a class="quick-card" href="projects.php">
          <div class="icon"><i class="fa-solid fa-table-cells-large"></i></div>
          <h3>Explore Templates</h3>
          <p>Browse your available project structures.</p>
        </a>
      </div>

      <div class="grid-2 mt-16">
        <section class="card">
          <div class="space-between" style="margin-bottom:10px;">
            <h2>Recent Projects</h2>
            <span class="text-muted"><?php echo count($recent); ?> records</span>
          </div>
          <div class="table-wrap">
            <table data-interactive="1">
              <thead>
                <tr>
                  <th>Original Filename</th>
                  <th>Uploaded At</th>
                  <th>File Type</th>
                  <th>Row Count</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($recent as $r): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($r['original_filename']); ?></td>
                    <td><?php echo htmlspecialchars((string) $r['uploaded_at']); ?></td>
                    <td><?php echo htmlspecialchars((string) $r['file_type']); ?></td>
                    <td><?php echo (int) $r['row_count']; ?></td>
                    <td><span class="badge <?php echo htmlspecialchars((string) $r['upload_status']); ?>"><?php echo htmlspecialchars((string) $r['upload_status']); ?></span></td>
                    <td>
                      <div class="action-links">
                        <a href="profile.php?upload_id=<?php echo (int) $r['id']; ?>">View Profile</a>
                        <a href="clean.php?upload_id=<?php echo (int) $r['id']; ?>">Clean</a>
                        <a href="analyze.php?upload_id=<?php echo (int) $r['id']; ?>">Analyze</a>
                        <form method="post" action="../actions/delete_upload.php" onsubmit="return confirm('Delete this upload?')">
                          <input type="hidden" name="upload_id" value="<?php echo (int) $r['id']; ?>">
                          <button type="submit" class="btn-danger">Delete</button>
                        </form>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
                <?php if (!$recent): ?>
                  <tr>
                    <td colspan="6" class="text-muted">No uploads yet. Go to Import Data.</td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </section>

        <section class="card">
          <div class="panel-tabs">
            <button class="active" type="button">Data</button>
            <button type="button">Visualization</button>
          </div>
          <div class="stack" style="margin-top:12px;">
            <div>
              <div class="kpi"><?php echo htmlspecialchars($dataSourceName); ?></div>
              <div class="kpi-label">Data Source · <?php echo (int) $dataSourceCount; ?> records</div>
            </div>
            <div class="table-wrap">
              <table data-interactive="1">
                <thead>
                  <tr>
                    <?php foreach ($previewHeaders as $h): ?>
                      <th><?php echo htmlspecialchars($h); ?></th>
                    <?php endforeach; ?>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($previewRows as $row): ?>
                    <tr>
                      <?php foreach ($previewHeaders as $h): ?>
                        <td><?php echo htmlspecialchars((string) ($row[$h] ?? '')); ?></td>
                      <?php endforeach; ?>
                    </tr>
                  <?php endforeach; ?>
                  <?php if (!$previewRows): ?>
                    <tr>
                      <td class="text-muted">No preview available.</td>
                    </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
            <a href="visualize.php" class="btn btn-primary">Create Visualization</a>
          </div>
        </section>
      </div>
    </div>
  </main>
</div>
<script src="../assets/js/table.js"></script>
</body>

</html>