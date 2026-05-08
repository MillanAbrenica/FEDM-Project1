<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

raco_start_session();
if (empty($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/projects.php');
    exit;
}

$uploadId = (int) ($_POST['upload_id'] ?? 0);
if ($uploadId <= 0) {
    header('Location: ../pages/projects.php');
    exit;
}

$pdo = raco_pdo();
$stmt = $pdo->prepare('SELECT id FROM uploads WHERE id = :id AND user_id = :user_id');
$stmt->execute(['id' => $uploadId, 'user_id' => (int) $_SESSION['user_id']]);
if (!$stmt->fetch()) {
    header('Location: ../pages/projects.php');
    exit;
}

$pdo->beginTransaction();
try {
    raco_log_activity($pdo, (int) $_SESSION['user_id'], $uploadId, 'delete', 'Deleted upload #' . $uploadId);
    $stmt = $pdo->prepare('DELETE FROM uploads WHERE id = :id AND user_id = :user_id');
    $stmt->execute(['id' => $uploadId, 'user_id' => (int) $_SESSION['user_id']]);
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
}

header('Location: ../pages/projects.php');
exit;
