<?php

declare(strict_types=1);

function raco_pdo(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $port = getenv('DB_PORT') ?: '3306';
    $db = getenv('DB_NAME') ?: 'dataclean_pro';
    $user = getenv('DB_USER') ?: 'root';
    $pass = getenv('DB_PASS') ?: '';

    $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}

function raco_start_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function raco_require_login(): void
{
    raco_start_session();
    if (empty($_SESSION['user_id'])) {
        header('Location: ../login.php');
        exit;
    }
}

function raco_current_user(): ?array
{
    raco_start_session();
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $pdo = raco_pdo();
    $stmt = $pdo->prepare('SELECT id, username, email FROM users WHERE id = :id');
    $stmt->execute(['id' => (int) $_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        $_SESSION = [];
        session_destroy();
        return null;
    }

    return $user;
}

function raco_upload_column_count_name(PDO $pdo): string
{
    static $column = null;
    if ($column !== null) {
        return $column;
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM uploads LIKE 'column_count'");
    $column = $stmt->fetch() ? 'column_count' : 'col_count';
    return $column;
}

function raco_upload_time_column_name(PDO $pdo): string
{
    static $column = null;
    if ($column !== null) {
        return $column;
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM uploads LIKE 'uploaded_at'");
    $column = $stmt->fetch() ? 'uploaded_at' : 'created_at';
    return $column;
}

function raco_json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function raco_log_activity(PDO $pdo, int $userId, ?int $uploadId, string $action, string $detail): void
{
    $stmt = $pdo->prepare('INSERT INTO activity_log (user_id, upload_id, action, detail) VALUES (:user_id, :upload_id, :action, :detail)');
    $stmt->execute([
        'user_id' => $userId,
        'upload_id' => $uploadId,
        'action' => $action,
        'detail' => $detail,
    ]);
}
