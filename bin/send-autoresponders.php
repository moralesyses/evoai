<?php
/**
 * Send every member's due auto responder emails (Backoffice > Contacts).
 * Members' own visits to the backoffice send theirs too, but run this every
 * few minutes so emails go out on time: Windows Task Scheduler, or cron.
 *
 *   C:\xampp\php\php.exe bin\send-autoresponders.php             (up to 200 emails)
 *   C:\xampp\php\php.exe bin\send-autoresponders.php --limit=500
 *
 * With mail.transport 'smtp', set app.base_url in config/config.php: emails
 * need the site's full address for their unsubscribe and sign-up links.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_SERVER['SCRIPT_NAME'] = '/' . basename(dirname(__DIR__)) . '/index.php';

require dirname(__DIR__) . '/includes/bootstrap.php';

$limit = 200;
foreach ($argv as $arg) {
    if (preg_match('/^--limit=(\d+)$/', $arg, $m)) {
        $limit = max(1, (int) $m[1]);
    }
}

$transport = mail_transport();
if ($transport === 'smtp' && (string) config('app.base_url', '') === '') {
    fwrite(STDERR, "Set app.base_url in config/config.php first (for example https://yourdomain.com): emails need the full address for their unsubscribe and sign-up links.\n");
    exit(1);
}

$r = crm_send_due(null, $limit);
echo 'Database: ' . config('db.name') . ' · email: ' . $transport . "\n";
if ($transport === 'off') {
    echo $r['waiting'] . " email(s) are due, but email sending is off (mail.transport in config/config.php).\n";
    exit(0);
}
echo "Sent {$r['sent']}, failed {$r['failed']}, cancelled {$r['cancelled']} (contact unsubscribed, marked Joined / Not interested, or the message was turned off).\n";
exit($r['failed'] > 0 ? 2 : 0);
