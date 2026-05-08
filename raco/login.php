<?php

declare(strict_types=1);
require_once __DIR__ . '/config/db.php';
raco_start_session();
if (!empty($_SESSION['user_id'])) {
  header('Location: pages/workspace.php');
  exit;
}

$error = '';
$dbError = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    $pdo = raco_pdo();
    header('Content-Type: text/html; charset=utf-8');
    if (!($pdo instanceof PDO)) {
      throw new Exception('DB not available');
    }
  } catch (Exception $e) {
    $error = 'System error. Please try again later.';
    $dbError = true;
  }
  if (!$dbError) {
    /** @var PDO $pdo */
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if (!$username || !$password) {
      $error = 'Please fill in all fields';
    } else {
      try {
        $stmt = $pdo->prepare('SELECT id, username, email, password_hash FROM users WHERE username = ? OR email = ?');
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();

        $isValid = false;
        if ($user) {
          $storedHash = (string)($user['password_hash'] ?? '');
          if ($storedHash !== '' && password_verify($password, $storedHash)) {
            $isValid = true;
          } elseif ($storedHash !== '' && strpos($storedHash, '$2') !== 0 && hash_equals($storedHash, $password)) {
            // Backward compatibility for old plaintext passwords, then upgrade to a secure hash.
            $isValid = true;
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $update = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
            $update->execute([$newHash, (int)$user['id']]);
          }
        }

        if (!$isValid) {
          $error = 'Invalid username or password';
        } else {
          $_SESSION['user_id'] = (int)$user['id'];
          header('Location: pages/workspace.php');
          exit;
        }
      } catch (Throwable $e) {
        $error = 'Login failed due to a server error. Please try again.';
      }
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>RACO - Login</title>
  <link rel="stylesheet" href="assets/css/main.css">
  <style>
    body {
      background: var(--navy);
      display: flex;
      align-items: center;
      justify-content: center;
      height: 100vh;
      margin: 0;
      font-family: 'Segoe UI', system-ui, sans-serif
    }

    .auth-container {
      width: 400px;
      max-width: 90vw
    }

    .auth-card {
      background: var(--white);
      border-radius: 16px;
      padding: 40px 36px;
      box-shadow: 0 20px 60px rgba(0, 0, 0, .3)
    }

    .auth-logo {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      margin-bottom: 32px
    }

    .auth-logo .logo-icon {
      width: 40px;
      height: 40px;
      background: var(--blue);
      border-radius: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 800;
      font-size: 20px;
      color: #fff
    }

    .auth-logo .logo-text {
      font-size: 24px;
      font-weight: 700;
      color: var(--navy);
      letter-spacing: 1px
    }

    .auth-logo .logo-text span {
      color: var(--blue)
    }

    .auth-title {
      font-size: 18px;
      font-weight: 600;
      color: var(--navy);
      margin-bottom: 4px;
      text-align: center
    }

    .auth-subtitle {
      font-size: 13px;
      color: var(--gray-500);
      margin-bottom: 24px;
      text-align: center
    }

    .form-group {
      margin-bottom: 16px
    }

    .form-group label {
      display: block;
      font-size: 12px;
      font-weight: 600;
      color: var(--gray-600);
      margin-bottom: 4px
    }

    .form-group input {
      width: 100%;
      padding: 10px 14px;
      border: 1px solid var(--gray-300);
      border-radius: 8px;
      font-size: 14px;
      font-family: inherit;
      transition: border-color .15s;
      box-sizing: border-box
    }

    .form-group input:focus {
      border-color: var(--blue);
      outline: none;
      box-shadow: 0 0 0 3px var(--blue-light)
    }

    .form-error {
      background: #ffebee;
      color: #c62828;
      padding: 10px 14px;
      border-radius: 8px;
      font-size: 13px;
      margin-bottom: 16px
    }

    .btn-submit {
      width: 100%;
      padding: 12px;
      background: var(--blue);
      color: #fff;
      border: none;
      border-radius: 8px;
      font-size: 15px;
      font-weight: 600;
      cursor: pointer;
      transition: background .15s;
      font-family: inherit
    }

    .btn-submit:hover {
      background: var(--blue-hover)
    }

    .auth-footer {
      text-align: center;
      margin-top: 20px;
      font-size: 13px;
      color: var(--gray-500)
    }

    .auth-footer a {
      color: var(--blue);
      text-decoration: none;
      font-weight: 600
    }

    .auth-footer a:hover {
      text-decoration: underline
    }
  </style>
</head>

<body>
  <div class="auth-container">
    <div class="auth-card">
      <div class="auth-logo">
        <div class="logo-icon">R</div>
        <div class="logo-text">R<span>ACO</span></div>
      </div>
      <div class="auth-title">Welcome back</div>
      <div class="auth-subtitle">Sign in to your account to continue</div>
      <?php if ($error): ?><div class="form-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
      <form method="POST" action="">
        <div class="form-group">
          <label for="username">Username or Email</label>
          <input type="text" id="username" name="username" placeholder="Enter your username or email" required autofocus>
        </div>
        <div class="form-group">
          <label for="password">Password</label>
          <input type="password" id="password" name="password" placeholder="Enter your password" required>
        </div>
        <button type="submit" class="btn-submit">Sign In</button>
      </form>
      <div class="auth-footer">
        Don't have an account? <a href="register.php">Create one</a>
      </div>
    </div>
  </div>
</body>

</html>