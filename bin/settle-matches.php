<?php
/**
 * Until 2026-10-13 only the sponsor could make a pair, so a member could have
 * points waiting on both teams from members they didn't refer. Every upline
 * pairs now (mlm_add_point); this pays those waiting pairs once: one pairing
 * bonus per point pair, up to what is left of the member's current round.
 *
 *   C:\xampp\php\php.exe bin\settle-matches.php           (dry run: shows what would happen)
 *   C:\xampp\php\php.exe bin\settle-matches.php --apply   (does it)
 *
 * Running it again later pays only pairs still waiting (for example ones held
 * back by a round limit).
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_SERVER['SCRIPT_NAME'] = '/' . basename(dirname(__DIR__)) . '/index.php';

require dirname(__DIR__) . '/includes/bootstrap.php';

$apply = in_array('--apply', $argv, true);
$pdo = db();
$pdo->beginTransaction();
try {
    $lines = mlm_settle_waiting_matches();
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, 'Nothing was changed: ' . $e->getMessage() . "\n");
    exit(1);
}

echo 'Database: ' . config('db.name') . "\n";
if (!$lines) {
    $pdo->rollBack();
    echo "Nothing to settle: no member has points waiting on both teams.\n";
    exit(0);
}
foreach ($lines as $line) {
    echo '  ' . $line . "\n";
}
$paid = count(array_filter($lines, static fn ($l) => str_contains($l, ' match')));
if ($apply) {
    $pdo->commit();
    add_audit('Settled waiting matches for ' . $paid . ' member account(s) (bin/settle-matches.php)', null);
    echo 'Saved: ' . $paid . " account(s) paid.\n";
} else {
    $pdo->rollBack();
    echo "Dry run: nothing was saved. Run again with --apply to do it.\n";
}
