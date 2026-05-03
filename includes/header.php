<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$pageTitle = $pageTitle ?? 'DataClean Pro';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - DataClean Pro</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
</head>

<body class="fade-in-page">
    <nav class="navbar navbar-expand-lg sticky-top border-bottom" data-main-nav>
        <div class="container">
            <a class="navbar-brand fw-semibold" href="index.php">DataClean Pro</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                    <li class="nav-item"><a class="nav-link" href="index.php">Upload</a></li>
                    <li class="nav-item"><a class="nav-link" href="profile.php">Profile</a></li>
                    <li class="nav-item"><a class="nav-link" href="clean.php">Clean</a></li>
                    <li class="nav-item"><a class="nav-link" href="analyze.php">Analyze</a></li>
                    <li class="nav-item"><a class="nav-link" href="dashboard.php">Visualize</a></li>
                </ul>
            </div>
        </div>
    </nav>
    <main class="container py-4">
        <?php if (!empty($_SESSION['flash_error'])): ?>
            <div class="alert alert-danger alert-sm mb-3"><?php echo htmlspecialchars((string) $_SESSION['flash_error']); ?></div>
            <?php unset($_SESSION['flash_error']); ?>
        <?php endif; ?>
        <?php if (!empty($_SESSION['flash_warning'])): ?>
            <div class="alert alert-warning alert-sm mb-3"><?php echo htmlspecialchars((string) $_SESSION['flash_warning']); ?></div>
            <?php unset($_SESSION['flash_warning']); ?>
        <?php endif; ?>
        <?php if (!empty($_SESSION['flash_success'])): ?>
            <div class="alert alert-success alert-sm mb-3"><?php echo htmlspecialchars((string) $_SESSION['flash_success']); ?></div>
            <?php unset($_SESSION['flash_success']); ?>
        <?php endif; ?>