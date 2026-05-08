<?php

declare(strict_types=1);
require_once __DIR__ . '/../config/db.php';
raco_require_login();
$pdo = raco_pdo();

$search = trim((string) ($_GET['q'] ?? ''));
$status = trim((string) ($_GET['status'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

$where = ['user_id = :user_id'];
$params = ['user_id' => (int) $_SESSION['user_id']];
if ($search !== '') {
  $where[] = 'original_filename LIKE :q';
  $params['q'] = '%' . $search . '%';
}
if ($status !== '') {
  $where[] = 'upload_status = :status';
  $params['status'] = $status;
}
$whereSql = implode(' AND ', $where);

$countCol = raco_upload_column_count_name($pdo);
$timeCol = raco_upload_time_column_name($pdo);

$stmt = $pdo->prepare("SELECT COUNT(*) FROM uploads WHERE {$whereSql}");
$stmt->execute($params);
$total = (int) $stmt->fetchColumn();
$totalPages = max(1, (int) ceil($total / $limit));

$sql = "SELECT id, original_filename, file_type, row_count, {$countCol} AS column_count, upload_status, {$timeCol} AS uploaded_at FROM uploads WHERE {$whereSql} ORDER BY {$timeCol} DESC LIMIT {$limit} OFFSET {$offset}";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_delete'])) {
  $ids = $_POST['upload_ids'] ?? [];
  if (is_array($ids) && $ids) {
    $pdo->beginTransaction();
    try {
      $del = $pdo->prepare('DELETE FROM uploads WHERE id = :id AND user_id = :user_id');
      foreach ($ids as $id) {
        $del->execute(['id' => (int) $id, 'user_id' => (int) $_SESSION['user_id']]);
      }
      $pdo->commit();
    } catch (Throwable $e) {
      $pdo->rollBack();
    }
  }
  header('Location: projects.php');
  exit;
}

$pageTitle = 'Projects';
$activePage = 'projects';
require __DIR__ . '/../includes/header.php';
?>
<div class="app-shell">
  <?php require __DIR__ . '/../includes/sidebar.php'; ?>
  <main class="main-shell">
    <?php require __DIR__ . '/../includes/ribbon.php'; ?>
    <div class="page-wrap">
      <h1 class="page-title">Projects</h1>
      <p class="page-subtitle">All uploads and data projects</p>

      <section class="card">
        <form method="get" class="row" style="margin-bottom:10px;">
          <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search filename..." style="max-width:320px;">
          <select name="status" style="max-width:200px;">
            <option value="">All statuses</option>
            <option value="uploaded" <?php echo $status === 'uploaded' ? 'selected' : ''; ?>>uploaded</option>
            <option value="profiled" <?php echo $status === 'profiled' ? 'selected' : ''; ?>>profiled</option>
            <option value="cleaned" <?php echo $status === 'cleaned' ? 'selected' : ''; ?>>cleaned</option>
            <option value="analyzed" <?php echo $status === 'analyzed' ? 'selected' : ''; ?>>analyzed</option>
          </select>
          <button type="submit" class="btn btn-primary">Filter</button>
        </form>

        <form method="post">
          <input type="hidden" name="bulk_delete" value="1">
          <div class="table-wrap">
            <table data-interactive="1">
              <thead>
                <tr>
                  <th><input type="checkbox" id="allChecks"></th>
                  <th>ID</th>
                  <th>Filename</th>
                  <th>File Type</th>
                  <th>Rows</th>
                  <th>Columns</th>
                  <th>Status</th>
                  <th>Uploaded At</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($rows as $r): ?>
                  <tr>
                    <td><input type="checkbox" name="upload_ids[]" value="<?php echo (int) $r['id']; ?>"></td>
                    <td><?php echo (int) $r['id']; ?></td>
                    <td><?php echo htmlspecialchars((string) $r['original_filename']); ?></td>
                    <td><?php echo htmlspecialchars((string) $r['file_type']); ?></td>
                    <td><?php echo (int) $r['row_count']; ?></td>
                    <td><?php echo (int) $r['column_count']; ?></td>
                    <td><span class="badge <?php echo htmlspecialchars((string) $r['upload_status']); ?>"><?php echo htmlspecialchars((string) $r['upload_status']); ?></span></td>
                    <td><?php echo htmlspecialchars((string) $r['uploaded_at']); ?></td>
                    <td class="action-links">
                      <a href="profile.php?upload_id=<?php echo (int) $r['id']; ?>">Profile</a>
                      <a href="clean.php?upload_id=<?php echo (int) $r['id']; ?>">Clean</a>
                      <a href="analyze.php?upload_id=<?php echo (int) $r['id']; ?>">Analyze</a>
                      <a href="visualize.php?upload_id=<?php echo (int) $r['id']; ?>">Visualize</a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <div class="row" style="margin-top:10px;">
            <button type="submit" class="btn btn-danger" onclick="return confirm('Delete selected uploads?');">Bulk Delete</button>
          </div>
        </form>

        <div class="row" style="margin-top:10px;">
          <?php if ($page > 1): ?><a class="btn" href="?page=<?php echo $page - 1; ?>&q=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>">Prev</a><?php endif; ?>
          <span class="text-muted">Page <?php echo $page; ?> / <?php echo $totalPages; ?></span>
          <?php if ($page < $totalPages): ?><a class="btn" href="?page=<?php echo $page + 1; ?>&q=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>">Next</a><?php endif; ?>
        </div>
      </section>
    </div>
  </main>
</div>
<script>
  document.getElementById('allChecks')?.addEventListener('change', function() {
    document.querySelectorAll('input[name="upload_ids[]"]').forEach((cb) => cb.checked = this.checked);
  });
</script>
<script src="../assets/js/table.js"></script>
</body>

</html>