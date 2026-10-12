<?php
/**
 * Daily job for members' .com domains (includes/domains.php):
 *   - refreshes today's .com market price (Porkbun's .com price × USD→PHP),
 *     cross-checked with a second exchange rate and, with GoDaddy keys,
 *     GoDaddy's .com price; and members' domains' estimated values (GoValue);
 *   - re-checks unpaid orders with Maya (payments whose webhook never arrived,
 *     e.g. on a local XAMPP) and cancels orders unpaid after 48 hours;
 *   - finishes paid orders that were interrupted, and hands orders stuck
 *     while registering to the admins (Admin > .com Domains);
 *   - marks domains past their expiry as expired;
 *   - auto-renews domains with auto-renew on, 7 days before expiry, from the
 *     member's available balance;
 *   - sends renewal reminders 30, 7 and 1 days before expiry (email and
 *     backoffice notification).
 *
 *   C:\xampp\php\php.exe bin\domains-daily.php
 *
 * Run it once a day with Windows Task Scheduler or cron (more often is fine:
 * every step only does what is due). Set app.base_url in config/config.php so
 * reminder emails link to the site's real address.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_SERVER['SCRIPT_NAME'] = '/' . basename(dirname(__DIR__)) . '/index.php';

require dirname(__DIR__) . '/includes/bootstrap.php';

// One run at a time (a slow registrar must not overlap with the next run).
if ((int) db_value('SELECT GET_LOCK(?, 0)', ['evoai_domains_daily']) !== 1) {
    fwrite(STDERR, "Another domains-daily run is still busy.\n");
    exit(1);
}

$r = domain_daily();
db_value('SELECT RELEASE_LOCK(?)', ['evoai_domains_daily']);

echo 'Database: ' . config('db.name') . ' · registrar: ' . (env('REGISTRAR_DRIVER', 'mock') === 'resellerclub' ? 'resellerclub (' . env('RESELLERCLUB_ENV', 'test') . ')' : 'mock')
    . ' · payments: ' . payment_client()->name() . ' · email: ' . mail_transport() . "\n";
echo "Price per year: {$r['price']}\n";
echo "Domain values (GoDaddy GoValue): " . (godaddy_enabled() ? "{$r['values']} of members' domains have an estimate" : 'off (no GoDaddy keys)') . "\n";
echo "Unpaid orders checked: {$r['checked']} (now paid: {$r['paid']}), cancelled after 48 h: {$r['cancelled']}\n";
echo "Interrupted orders resumed: {$r['fulfilled']}, handed to admins: {$r['stuck']}\n";
echo "Domains expired: {$r['expired']}\n";
echo "Auto-renewed: {$r['renewed']}, failed after payment: {$r['renew_failed']}, balance too low: {$r['no_funds']}\n";
echo "Renewal reminders sent: {$r['reminders']}\n";
exit($r['stuck'] > 0 || $r['renew_failed'] > 0 ? 2 : 0);
