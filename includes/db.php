<?php
/**
 * PDO connection + tiny query helpers. Every query uses prepared statements.
 */
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $c = config('db');
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $c['host'], (int) $c['port'], $c['name'], $c['charset'] ?? 'utf8mb4');
    try {
        $pdo = new PDO($dsn, $c['user'], $c['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        // Keep MySQL date math aligned with the PHP timezone.
        $pdo->exec("SET time_zone = '" . (new DateTime())->format('P') . "'");
    } catch (PDOException $e) {
        http_response_code(500);
        error_log('Database connection failed: ' . $e->getMessage());
        $isApi = basename($_SERVER['SCRIPT_NAME'] ?? '') === 'api.php';
        if ($isApi) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'Database connection failed. Check config/config.php.']);
        } else {
            echo '<!doctype html><meta charset="utf-8"><title>Database unavailable</title>'
                . '<body style="background:#090909;color:#f7f5ef;font-family:sans-serif;padding:40px">'
                . '<h1 style="color:#f3bd35">Database connection failed</h1>'
                . '<p>Start MySQL in the XAMPP Control Panel, import <code>database/evoai.sql</code> with phpMyAdmin and check the credentials in <code>config/config.php</code>.</p>';
        }
        exit;
    }
    return $pdo;
}

/** Run a statement and return it. */
function db_run(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function db_one(string $sql, array $params = []): ?array
{
    $row = db_run($sql, $params)->fetch();
    return $row === false ? null : $row;
}

function db_all(string $sql, array $params = []): array
{
    return db_run($sql, $params)->fetchAll();
}

function db_value(string $sql, array $params = [])
{
    $v = db_run($sql, $params)->fetchColumn();
    return $v === false ? null : $v;
}

/** Execute $fn inside a transaction and return its result. */
function db_tx(callable $fn)
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $result = $fn($pdo);
        $pdo->commit();
        return $result;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}
