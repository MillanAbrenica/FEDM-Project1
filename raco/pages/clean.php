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

$stmt = $pdo->prepare('SELECT id, original_filename, row_count FROM uploads WHERE id = :id AND user_id = :user_id');
$stmt->execute(['id' => $uploadId, 'user_id' => (int) $_SESSION['user_id']]);
$upload = $stmt->fetch();
if (!$upload) {
  header('Location: projects.php');
  exit;
}

$rows = raco_get_dataset_rows($pdo, $uploadId, 'original');
if (!$rows) {
  header('Location: profile.php?upload_id=' . $uploadId);
  exit;
}
$headers = $rows ? array_keys($rows[0]) : [];
$preview = array_slice($rows, 0, 10);

$pageTitle = 'Clean';
$activePage = 'projects';
require __DIR__ . '/../includes/header.php';
?>
<div class="app-shell">
  <?php require __DIR__ . '/../includes/sidebar.php'; ?>
  <main class="main-shell">
    <?php require __DIR__ . '/../includes/ribbon.php'; ?>
    <div class="page-wrap">
      <h1 class="page-title">Data Cleaning</h1>
      <p class="page-subtitle">Configure cleaning operations for <?php echo htmlspecialchars((string) $upload['original_filename']); ?></p>

      <div class="grid-2">
        <section class="card">
          <form id="cleanForm" method="post" action="../actions/clean_handler.php" class="stack">
            <input type="hidden" name="upload_id" value="<?php echo $uploadId; ?>">
            <h3>Operations Panel</h3>

            <div>
              <label><input type="checkbox" id="remove_duplicates" name="remove_duplicates" value="1"> Remove duplicates</label>
              <label><input type="checkbox" id="trim_whitespace" name="trim_whitespace" value="1"> Trim whitespace</label>
              <label><input type="checkbox" id="standardize_dates" name="standardize_dates" value="1"> Standardize dates (YYYY-MM-DD)</label>
            </div>

            <div>
              <label>String Case</label>
              <select name="string_case">
                <option value="none">None</option>
                <option value="upper">UPPER</option>
                <option value="lower">lower</option>
              </select>
            </div>

            <h3>Per Column Rules</h3>
            <div class="table-wrap">
              <table>
                <thead>
                  <tr>
                    <th>Column</th>
                    <th>Missing Value</th>
                    <th>Custom Fill</th>
                    <th>Cast Type</th>
                    <th>Min</th>
                    <th>Max</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($headers as $h): ?>
                    <tr>
                      <td><?php echo htmlspecialchars($h); ?></td>
                      <td>
                        <select name="missing_strategy[<?php echo htmlspecialchars($h); ?>]">
                          <option value="none">None</option>
                          <option value="drop">Drop row</option>
                          <option value="mean">Fill mean</option>
                          <option value="median">Fill median</option>
                          <option value="mode">Fill mode</option>
                          <option value="custom">Custom</option>
                        </select>
                      </td>
                      <td><input type="text" name="missing_custom[<?php echo htmlspecialchars($h); ?>]" placeholder="Custom value"></td>
                      <td>
                        <select name="cast_type[<?php echo htmlspecialchars($h); ?>]">
                          <option value="">No cast</option>
                          <option value="string">string</option>
                          <option value="integer">integer</option>
                          <option value="float">float</option>
                          <option value="date">date</option>
                        </select>
                      </td>
                      <td><input type="number" step="any" name="range_min[<?php echo htmlspecialchars($h); ?>]"></td>
                      <td><input type="number" step="any" name="range_max[<?php echo htmlspecialchars($h); ?>]"></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>

            <div>
              <label>Regex pattern filter (keep rows matching this pattern)</label>
              <input type="text" name="regex_pattern" placeholder="/pattern/i">
            </div>

            <button type="submit" class="btn btn-primary">Apply Cleaning</button>
          </form>
        </section>

        <section class="card">
          <h3>Original Data Preview (Top 10 Rows)</h3>
          <div class="table-wrap" style="margin-top:10px;">
            <table data-interactive="1">
              <thead>
                <tr><?php foreach ($headers as $h): ?><th><?php echo htmlspecialchars($h); ?></th><?php endforeach; ?></tr>
              </thead>
              <tbody>
                <?php foreach ($preview as $row): ?>
                  <tr>
                    <?php foreach ($headers as $h): ?>
                      <td><?php echo htmlspecialchars((string) ($row[$h] ?? '')); ?></td>
                    <?php endforeach; ?>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </section>
      </div>
    </div>
  </main>
</div>
<script src="../assets/js/table.js"></script>
<script src="../assets/js/clean.js"></script>
</body>

</html>