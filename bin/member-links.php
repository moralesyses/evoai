<?php
/**
 * List every WeServe member's 1EVOAI Card link as CSV (userId, name, link),
 * e.g. for NFC cards, QR codes or a mail merge. Run it from a command prompt:
 *
 *   C:\xampp\php\php.exe bin\member-links.php                 (show on screen)
 *   C:\xampp\php\php.exe bin\member-links.php D:\links.csv    (save; opens in Excel)
 *
 * Save the file outside htdocs. Links use app.base_url from config/config.php;
 * set it to the public address before exporting links you plan to hand out.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

// Build links for this XAMPP folder on localhost unless app.base_url is set.
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_SERVER['SCRIPT_NAME'] = '/' . basename(dirname(__DIR__)) . '/index.php';

require dirname(__DIR__) . '/includes/bootstrap.php';

if (!weserve_enabled()) {
    fwrite(STDERR, "Member cards are off: set card.link_secret in config/config.php and import database/weserve_members.sql.\n");
    exit(1);
}
if ((string) config('app.base_url', '') === '') {
    fwrite(STDERR, "app.base_url is not set in config/config.php, so these links point to http://localhost.\n");
}

$target = $argv[1] ?? null;
$out = fopen($target ?? 'php://stdout', 'w');
if ($out === false) {
    fwrite(STDERR, "Can't write to $target\n");
    exit(1);
}
if ($target !== null) {
    fwrite($out, "\xEF\xBB\xBF"); // Lets Excel read names like "Ñ" correctly.
}

fputcsv($out, ['userId', 'name', 'link']);
$count = 0;
foreach (db_all('SELECT userId FROM UserProfile WHERE ' . weserve_has_network_account() . ' ORDER BY userId') as $row) {
    $member = weserve_load_member((int) $row['userId']);
    if ($member) {
        fputcsv($out, [$member['userId'], $member['name'], $member['shareUrl']]);
        $count++;
    }
}
fclose($out);

fwrite(STDERR, "$count member links" . ($target !== null ? " saved to $target" : '') . ".\n");
