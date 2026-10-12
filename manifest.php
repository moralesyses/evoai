<?php
/**
 * Web app manifest for one digital card, so it can be added to a phone's home
 * screen with the person's name: manifest.php?profile=usr_x or ?card=<slug>.
 * Icon: the person's photo when there is one (PNG/JPEG), otherwise the
 * We Serve emblem.
 */
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$name = '';
$title = '';
$startUrl = base_url() . '/';
$avatar = '';

$weserve = isset($_GET['card']) && is_string($_GET['card']) ? weserve_member_from_slug($_GET['card']) : null;
if ($weserve) {
    $name = $weserve['name'];
    $title = $weserve['title'];
    $startUrl = $weserve['shareUrl'];
} else {
    $profileId = (string) ($_GET['profile'] ?? MASTER_PROFILE_ID);
    if (isset($_GET['card']) && is_string($_GET['card'])) {
        $bySlug = find_profile_by_slug($_GET['card']);
        $profileId = $bySlug['userId'] ?? $profileId;
    }
    if (!preg_match('/^usr_[A-Za-z0-9_]+$/', $profileId) || !db_value('SELECT 1 FROM member_profiles WHERE user_id = ?', [$profileId])) {
        $profileId = MASTER_PROFILE_ID;
    }
    $profile = get_member_profile($profileId);
    $cms = get_cms();
    $isMaster = $profileId === MASTER_PROFILE_ID;
    $name = $isMaster ? $cms['name'] : $profile['name'];
    $title = $isMaster ? trim($cms['title'] . ' ' . $cms['company']) : trim($profile['title'] . ' ' . $profile['company']);
    $avatar = $isMaster ? ($cms['avatarUrl'] ?: (string) $profile['avatar']) : (string) $profile['avatar'];
    $startUrl = card_share_url($profile);
    // A start page must be on the manifest's own site: the member's domain only when the card was opened there.
    $host = strtolower(preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')) ?? '');
    if (strtolower((string) parse_url($startUrl, PHP_URL_HOST)) !== $host) {
        $startUrl = card_share_url($profile, false);
    }
}

$origin = (string) preg_replace('#^(https?://[^/]+).*$#', '$1', base_url());
$icons = [];
if ($avatar !== '' && !preg_match('#^(https?:)?//#i', $avatar)) {
    $file = realpath(APP_ROOT . '/' . ltrim($avatar, '/'));
    $size = $file && preg_match('/\.(png|jpe?g)$/i', $file) ? getimagesize($file) : false;
    if ($size !== false) {
        $icons[] = ['src' => $origin . media_url($avatar), 'sizes' => $size[0] . 'x' . $size[1], 'type' => $size['mime']];
    }
}
if (!$icons) {
    $icons[] = ['src' => $origin . base_path() . '/assets/images/weserve/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png'];
}

header('Content-Type: application/manifest+json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
echo json_encode([
    'name'             => $name,
    'short_name'       => $name,
    'description'      => $title,
    'start_url'        => $startUrl,
    'scope'            => base_path() . '/',
    'display'          => 'standalone',
    'background_color' => '#090909',
    'theme_color'      => '#090909',
    'icons'            => $icons,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
