<?php

declare(strict_types=1);
require_once __DIR__ . '/../config/db.php';
raco_require_login();
$pdo = raco_pdo();

$timeCol = raco_upload_time_column_name($pdo);
$stmt = $pdo->prepare("SELECT al.id, al.action, al.detail, al.performed_at, u.original_filename, us.username FROM activity_log al LEFT JOIN uploads u ON u.id = al.upload_id LEFT JOIN users us ON us.id = al.user_id WHERE al.user_id = :user_id ORDER BY al.performed_at DESC LIMIT 200");
$stmt->execute(['user_id' => (int) $_SESSION['user_id']]);
$activities = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT cj.upload_id, cj.quality_score, cj.cleaned_at, u.original_filename FROM cleaning_jobs cj JOIN uploads u ON u.id = cj.upload_id WHERE u.user_id = :user_id ORDER BY cj.cleaned_at DESC LIMIT 50");
$stmt->execute(['user_id' => (int) $_SESSION['user_id']]);
$jobs = $stmt->fetchAll();

$labels = array_map(static fn($j) => '#' . $j['upload_id'], $jobs);
$scores = array_map(static fn($j) => (float) $j['quality_score'], $jobs);

$pageTitle = 'Reports';
$activePage = 'reports';
require __DIR__ . '/../includes/header.php';
?>
<div class="app-shell">
  <?php require __DIR__ . '/../includes/sidebar.php'; ?>
  <main class="main-shell">
    <?php require __DIR__ . '/../includes/ribbon.php'; ?>
    <div class="page-wrap">
      <h1 class="page-title">Reports</h1>
      <p class="page-subtitle">Activity history and cleaning quality trends</p>

      <div class="grid-2">
        <section class="card">
          <h3>Quality Scores per Upload</h3>
          <canvas id="qualityChart" height="160"></canvas>
        </section>

        <section class="card">
          <h3>Cleaning Summary</h3>
          <div class="table-wrap" style="margin-top:10px;">
            <table data-interactive="1">
              <thead>
                <tr>
                  <th>Upload</th>
                  <th>Filename</th>
                  <th>Quality Score</th>
                  <th>Cleaned At</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($jobs as $j): ?>
                  <tr>
                    <td>#<?php echo (int) $j['upload_id']; ?></td>
                    <td><?php echo htmlspecialchars((string) $j['original_filename']); ?></td>
                    <td><?php echo htmlspecialchars((string) $j['quality_score']); ?>%</td>
                    <td><?php echo htmlspecialchars((string) $j['cleaned_at']); ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </section>
      </div>

      <section class="card mt-16">
        <h3>Activity Log</h3>
        <div class="table-wrap" style="margin-top:10px;">
          <table data-interactive="1">
            <thead>
              <tr>
                <th>ID</th>
                <th>User</th>
                <th>Upload</th>
                <th>Action</th>
                <th>Detail</th>
                <th>Performed At</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($activities as $a): ?>
                <tr>
                  <td><?php echo (int) $a['id']; ?></td>
                  <td><?php echo htmlspecialchars((string) $a['username']); ?></td>
                  <td><?php echo htmlspecialchars((string) ($a['original_filename'] ?? '')); ?></td>
                  <td><?php echo htmlspecialchars((string) $a['action']); ?></td>
                  <td><?php echo htmlspecialchars((string) $a['detail']); ?></td>
                  <td><?php echo htmlspecialchars((string) $a['performed_at']); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
    </div>
  </main>
</div>
<script>
  const qctx = document.getElementById('qualityChart');
  if (qctx) {
    new Chart(qctx.getContext('2d'), {
      type: 'bar',
      data: {
        labels: <?php echo json_encode($labels, JSON_UNESCAPED_UNICODE); ?>,
        datasets: [{
          label: 'Quality Score',
          data: <?php echo json_encode($scores, JSON_UNESCAPED_UNICODE); ?>,
          backgroundColor: '#4f6ef7'
        }]
      },
      options: {
        responsive: true,
        scales: {
          y: {
            beginAtZero: true,
            max: 100
          }
        }
      }
    });
  }
</script>
<script src="../assets/js/table.js"></script>
</body>

</html>