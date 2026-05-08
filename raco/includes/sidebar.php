<?php
require_once __DIR__ . '/../config/db.php';
$user = raco_current_user();
if (!$user) {
  header('Location: ../login.php');
  exit;
}
$initial = strtoupper(substr((string) $user['username'], 0, 1));
$mainItems = [
  'workspace' => ['label' => 'Workspace', 'icon' => 'fa-house', 'href' => '../pages/workspace.php'],
  'visualize' => ['label' => 'Data Visualization', 'icon' => 'fa-chart-column', 'href' => '../pages/visualize.php'],
  'reports' => ['label' => 'Reports', 'icon' => 'fa-file-lines', 'href' => '../pages/reports.php'],
  'projects' => ['label' => 'Projects', 'icon' => 'fa-folder-open', 'href' => '../pages/projects.php'],
  'settings' => ['label' => 'Settings', 'icon' => 'fa-gear', 'href' => '../pages/settings.php'],
];
$dataTools = [
  ['label' => 'Data Clean', 'href' => '../pages/clean.php', 'icon' => 'fa-broom'],
  ['label' => 'Data Preparation', 'href' => '../pages/clean.php', 'icon' => 'fa-sliders'],
  ['label' => 'Data Validation', 'href' => '../pages/clean.php', 'icon' => 'fa-check-double'],
  ['label' => 'Data Import', 'href' => '../pages/import.php', 'icon' => 'fa-upload'],
  ['label' => 'Data Export', 'href' => '../pages/projects.php', 'icon' => 'fa-download'],
];
// Resources section removed per UI simplification request
?>
<aside class="sidebar">
  <div class="logo-area">
    <div class="logo-icon">R</div>
    <div class="logo-text">RACO</div>
  </div>

  <nav class="nav-section">
    <?php foreach ($mainItems as $key => $item): ?>
      <a class="nav-item <?php echo $activePage === $key ? 'active' : ''; ?>" href="<?php echo $item['href']; ?>">
        <i class="fa-solid <?php echo $item['icon']; ?> nav-icon"></i>
        <span><?php echo htmlspecialchars($item['label']); ?></span>
      </a>
    <?php endforeach; ?>
  </nav>

  <div class="nav-section">
    <div class="nav-title">DATA TOOLS</div>
    <?php foreach ($dataTools as $item): ?>
      <a class="nav-item" href="<?php echo $item['href']; ?>">
        <i class="fa-solid <?php echo $item['icon']; ?> nav-icon"></i>
        <span><?php echo htmlspecialchars($item['label']); ?></span>
      </a>
    <?php endforeach; ?>
  </div>

  <!-- Resources section removed -->

  <div class="user-profile">
    <div class="user-avatar"><?php echo $initial; ?></div>
    <div class="user-meta">
      <div class="user-name"><?php echo htmlspecialchars($user['username']); ?></div>
      <div class="user-email"><?php echo htmlspecialchars($user['email']); ?></div>
    </div>
    <a class="logout-link" href="../logout.php" title="Logout"><i class="fa-solid fa-arrow-right-from-bracket"></i></a>
  </div>
</aside>