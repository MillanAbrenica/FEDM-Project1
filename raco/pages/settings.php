<?php

declare(strict_types=1);
require_once __DIR__ . '/../config/db.php';
raco_require_login();
raco_start_session();

if (!isset($_SESSION['raco_settings'])) {
  $_SESSION['raco_settings'] = [
    'default_null_handling' => 'mean',
    'default_string_case' => 'none',
    'default_date_format' => 'Y-m-d',
  ];
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $_SESSION['raco_settings']['default_null_handling'] = (string) ($_POST['default_null_handling'] ?? 'mean');
  $_SESSION['raco_settings']['default_string_case'] = (string) ($_POST['default_string_case'] ?? 'none');
  $_SESSION['raco_settings']['default_date_format'] = (string) ($_POST['default_date_format'] ?? 'Y-m-d');
  $message = 'Settings saved to session.';
}

$s = $_SESSION['raco_settings'];
$pageTitle = 'Settings';
$activePage = 'settings';
require __DIR__ . '/../includes/header.php';
?>
<div class="app-shell">
  <?php require __DIR__ . '/../includes/sidebar.php'; ?>
  <main class="main-shell">
    <?php require __DIR__ . '/../includes/ribbon.php'; ?>
    <div class="page-wrap">
      <h1 class="page-title">Settings</h1>
      <p class="page-subtitle">Default preferences for cleaning and formatting</p>

      <section class="card" style="max-width:700px;">
        <?php if ($message): ?><div class="alert success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
        <form method="post" class="stack">
          <div>
            <label>Default null handling</label>
            <select name="default_null_handling">
              <option value="mean" <?php echo $s['default_null_handling'] === 'mean' ? 'selected' : ''; ?>>Fill with mean</option>
              <option value="median" <?php echo $s['default_null_handling'] === 'median' ? 'selected' : ''; ?>>Fill with median</option>
              <option value="mode" <?php echo $s['default_null_handling'] === 'mode' ? 'selected' : ''; ?>>Fill with mode</option>
              <option value="drop" <?php echo $s['default_null_handling'] === 'drop' ? 'selected' : ''; ?>>Drop row</option>
            </select>
          </div>

          <div>
            <label>Default string case</label>
            <select name="default_string_case">
              <option value="none" <?php echo $s['default_string_case'] === 'none' ? 'selected' : ''; ?>>None</option>
              <option value="upper" <?php echo $s['default_string_case'] === 'upper' ? 'selected' : ''; ?>>UPPER</option>
              <option value="lower" <?php echo $s['default_string_case'] === 'lower' ? 'selected' : ''; ?>>lower</option>
            </select>
          </div>

          <div>
            <label>Default date format</label>
            <input type="text" name="default_date_format" value="<?php echo htmlspecialchars((string) $s['default_date_format']); ?>">
          </div>

          <button type="submit" class="btn btn-primary">Save Settings</button>
        </form>
      </section>
    </div>
  </main>
</div>
</body>

</html>