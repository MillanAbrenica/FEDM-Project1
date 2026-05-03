<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$requiredSessionKeys = $requiredSessionKeys ?? [];
$missingRequiredData = false;

foreach ($requiredSessionKeys as $key) {
    if (!isset($_SESSION[$key])) {
        $missingRequiredData = true;
        break;
    }
}

if ($missingRequiredData) {
    $_SESSION['flash_error'] = 'Session expired or missing required data. Please upload a file and continue.';
    header('Location: index.php');
    exit;
}
