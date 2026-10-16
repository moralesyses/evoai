<?php
/**
 * Give every member card a clean, unique link name, so each card opens at
 * <site>/<link name> (e.g. http://localhost/evoai/juan-dela-cruz).
 *
 *   C:\xampp\php\php.exe bin\card-links.php           (dry run: shows what would change)
 *   C:\xampp\php\php.exe bin\card-links.php --apply   (does it)
 *
 * A valid name is kept, e.g. "1evoai.com/mike" becomes "mike", so links
 * already handed out keep working. Missing, invalid, reserved or duplicate
 * names (the later member of a duplicate) get one made from the member's
 * name. --apply then adds the database's unique index on the link name.
 * Safe to run again.
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
$rows = db_all('SELECT p.user_id, p.name, p.custom_slug FROM member_profiles p LEFT JOIN users u ON u.id = p.user_id ORDER BY u.created_at IS NULL, u.created_at, p.user_id');
$taken = [];
$changes = 0;
foreach ($rows as $r) {
    $old = (string) $r['custom_slug'];
    $slug = card_slug_clean($old);
    // A domain typed in (e.g. "juandelacruz.com") isn't a link name: one is made from the name instead.
    $domainLike = str_contains((string) substr($old, (int) strrpos('/' . $old, '/')), '.');
    $valid = !$domainLike && preg_match('/^[a-z][a-z0-9-]{2,59}$/', $slug) && !in_array($slug, CARD_SLUG_RESERVED, true) && !isset($taken[$slug]);
    if (!$valid) {
        // From the name, avoiding the names kept so far (the database still has the old values).
        $base = card_slug_clean((string) $r['name']);
        $base = preg_match('/^[a-z]/', $base) ? $base : trim('member-' . $base, '-');
        if (strlen($base) < 3 || in_array($base, CARD_SLUG_RESERVED, true)) {
            $base .= '-card';
        }
        for ($i = 1, $slug = $base; isset($taken[$slug]) || db_value('SELECT 1 FROM member_profiles WHERE custom_slug = ? AND user_id <> ?', [$slug, $r['user_id']]); $i++) {
            $slug = trim(substr($base, 0, 55), '-') . '-' . ($i + 1);
        }
    }
    $taken[$slug] = true;
    if ($slug !== $old) {
        $changes++;
        fwrite(STDOUT, sprintf("%-24s %-28s %s -> %s\n", $r['user_id'], mb_substr((string) $r['name'], 0, 28), $old === '' ? '(none)' : $old, $slug));
        if ($apply) {
            db_run('UPDATE member_profiles SET custom_slug = ? WHERE user_id = ?', [$slug, $r['user_id']]);
        }
    }
}
fwrite(STDOUT, count($rows) . " cards, $changes " . ($apply ? 'changed' : 'to change') . ".\n");

$hasIndex = db_value("SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'member_profiles' AND index_name = 'uq_profiles_slug'");
if ($apply && !$hasIndex) {
    db_run('ALTER TABLE member_profiles ADD UNIQUE INDEX uq_profiles_slug (custom_slug)');
    fwrite(STDOUT, "Added the unique index on the link name.\n");
} elseif (!$hasIndex) {
    fwrite(STDOUT, "--apply also adds the unique index on the link name.\n");
}
if (!$apply && $changes) {
    fwrite(STDOUT, "Dry run: nothing was changed. Run again with --apply.\n");
}
