<?php

declare(strict_types=1);
require_once __DIR__ . '/config/db.php';
raco_start_session();

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

header('Location: pages/workspace.php');
exit;
