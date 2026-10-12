<?php
/**
 * Set a portal account's password from the command line, e.g. the founder's
 * password before launch. Type the password here, never in a chat or a file:
 *
 *   C:\xampp\php\php.exe bin\set-password.php mike@mmmcircle.com
 *
 * Uses the database in config/config.php. The new password also signs the
 * account out of every session that is already open.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
// Checked before anything reads input: PHP warns when it is asked after buffering.
define('STDIN_IS_TERMINAL', stream_isatty(STDIN));

$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_SERVER['SCRIPT_NAME'] = '/' . basename(dirname(__DIR__)) . '/index.php';

require dirname(__DIR__) . '/includes/bootstrap.php';

$email = strtolower(trim((string) ($argv[1] ?? '')));
if ($email === '') {
    fwrite(STDERR, "Usage: php bin/set-password.php <account email>\n");
    exit(1);
}
$user = db_one('SELECT id, name, role, status FROM users WHERE email = ?', [$email]);
if (!$user) {
    fwrite(STDERR, "No account uses $email (database: " . config('db.name') . ").\n");
    exit(1);
}

/** Read one line, masking the typing in a terminal (PowerShell on Windows, stty elsewhere). */
function read_secret(string $prompt): string
{
    fwrite(STDOUT, $prompt);
    if (STDIN_IS_TERMINAL) {
        if (DIRECTORY_SEPARATOR === '\\') {
            $out = shell_exec('powershell -NoProfile -Command "[Console]::OutputEncoding = [Text.Encoding]::UTF8; $s = Read-Host -AsSecureString; [Runtime.InteropServices.Marshal]::PtrToStringBSTR([Runtime.InteropServices.Marshal]::SecureStringToBSTR($s))"');
            if (is_string($out)) {
                return rtrim($out, "\r\n");
            }
        } elseif (shell_exec('stty -echo 2>/dev/null') !== null) {
            $line = fgets(STDIN);
            shell_exec('stty echo 2>/dev/null');
            fwrite(STDOUT, "\n");
            return rtrim((string) $line, "\r\n");
        }
        fwrite(STDOUT, '(typing will show) ');
    }
    return rtrim((string) fgets(STDIN), "\r\n");
}

fwrite(STDOUT, "Account: {$user['name']} ({$user['role']}, {$user['status']}) in database " . config('db.name') . "\n");
$password = read_secret('New password (at least 6 characters; 12 or more recommended): ');
if (mb_strlen($password) < 6) {
    fwrite(STDERR, "Too short; nothing was changed.\n");
    exit(1);
}
if (read_secret('Type it again: ') !== $password) {
    fwrite(STDERR, "The two entries don't match; nothing was changed.\n");
    exit(1);
}

db_run('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $user['id']]);
db_run('UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL', [$user['id']]);
add_audit('Password set from the command line', $user['id']);
fwrite(STDOUT, "Password updated for $email.\n");
