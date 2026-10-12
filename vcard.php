<?php
/**
 * "Save Contact": download a card as a vCard 3.0 (.vcf) file.
 *   vcard.php?id=usr_mike
 * Includes the person's photo (PNG/JPEG) and social links, and records a
 * "vcard_download" analytics event.
 */
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

/** Escape a text value for a vCard line. */
function vcard_text(string $value): string
{
    return strtr(trim($value), ['\\' => '\\\\', "\r\n" => '\n', "\n" => '\n', ',' => '\,', ';' => '\;']);
}

/** Fold a line to at most 75 bytes per line, as the vCard format requires. */
function vcard_fold(string $line): string
{
    $parts = [];
    while (strlen($line) > 75) {
        $chunk = mb_strcut($line, 0, 75, 'UTF-8');
        $parts[] = $chunk;
        $line = ' ' . substr($line, strlen($chunk));
    }
    $parts[] = $line;
    return implode("\r\n", $parts);
}

// WeServe member card (vcard.php?card=<id>-<code>): name and card link only.
if (isset($_GET['card']) && is_string($_GET['card'])) {
    $member = weserve_member_from_slug($_GET['card']);
    if (!$member) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'There is no digital card at this address.';
        exit;
    }
    $v = $member['vcard'];
    $lines = [
        'BEGIN:VCARD',
        'VERSION:3.0',
        'REV:' . gmdate('Y-m-d\TH:i:s\Z'),
        'N;CHARSET=utf-8:' . vcard_text($v['last']) . ';' . vcard_text($v['first']) . ';' . vcard_text($v['middle']) . ';;' . vcard_text($v['suffix']),
        'FN;CHARSET=utf-8:' . vcard_text($member['name']),
        'URL:' . $member['shareUrl'],
        'END:VCARD',
    ];
    track_action('vcard_download', 'ws_' . $member['userId'], current_user()['id'] ?? null);
    $filename = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($member['name'])), '-') ?: $member['slug'];
    header('Content-Type: text/vcard; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.vcf"');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store');
    echo implode("\r\n", array_map('vcard_fold', $lines)) . "\r\n";
    exit;
}

$id = (string) ($_GET['id'] ?? MASTER_PROFILE_ID);
if (!preg_match('/^usr_[A-Za-z0-9_]+$/', $id) || !db_value('SELECT 1 FROM member_profiles WHERE user_id = ?', [$id])) {
    $id = MASTER_PROFILE_ID;
}
$profile = get_member_profile($id);
$cms = get_cms();
$isMaster = $id === MASTER_PROFILE_ID;

$name = $isMaster ? $cms['name'] : $profile['name'];
$company = $isMaster ? $cms['company'] : $profile['company'];
$title = $isMaster ? $cms['title'] : $profile['title'];
$phone = $isMaster ? $cms['phone'] : $profile['phone'];
$email = $isMaster ? $cms['email'] : $profile['email'];
$avatar = $isMaster ? ($cms['avatarUrl'] ?: (string) $profile['avatar']) : (string) $profile['avatar'];
$socials = $isMaster ? ($cms['socials'] ?? []) : ($profile['socials'] ?? []);

$parts = preg_split('/\s+/u', trim($name)) ?: [$name];
$first = $parts[0];
$last = count($parts) > 1 ? $parts[count($parts) - 1] : '';
$middle = count($parts) > 2 ? implode(' ', array_slice($parts, 1, -1)) : '';

$lines = [
    'BEGIN:VCARD',
    'VERSION:3.0',
    'REV:' . gmdate('Y-m-d\TH:i:s\Z'),
    'N;CHARSET=utf-8:' . vcard_text($last) . ';' . vcard_text($first) . ';' . vcard_text($middle) . ';;',
    'FN;CHARSET=utf-8:' . vcard_text($name),
];
if (trim($title) !== '') {
    $lines[] = 'TITLE;CHARSET=utf-8:' . vcard_text($title);
}
if (trim($company) !== '') {
    $lines[] = 'ORG;CHARSET=utf-8:' . vcard_text($company);
}
if (trim($phone) !== '') {
    $lines[] = 'TEL;TYPE=CELL:' . vcard_text($phone);
}
if (trim($email) !== '') {
    $lines[] = 'EMAIL;TYPE=INTERNET:' . vcard_text($email);
}
foreach (['facebook' => 'Facebook', 'instagram' => 'Instagram', 'linkedin' => 'Linkedin', 'twitter' => 'Twitter', 'telegram' => 'Telegram', 'website' => 'Website'] as $key => $label) {
    $url = safe_url((string) ($socials[$key] ?? ''), false);
    if ($url !== '') {
        $lines[] = 'URL;TYPE=' . $label . ':' . $url;
    }
}

// Embed the photo when it is a local PNG/JPEG file (SVG placeholders are skipped).
if ($avatar !== '' && !preg_match('#^(https?:)?//#i', $avatar)) {
    $file = realpath(APP_ROOT . '/' . ltrim($avatar, '/'));
    $images = realpath(APP_ROOT . '/assets/images');
    if ($file && $images && str_starts_with($file, $images) && preg_match('/\.(png|jpe?g)$/i', $file) && filesize($file) <= 2 * 1024 * 1024) {
        $type = preg_match('/\.png$/i', $file) ? 'PNG' : 'JPEG';
        $lines[] = 'PHOTO;ENCODING=b;TYPE=' . $type . ':' . base64_encode((string) file_get_contents($file));
    }
}

$lines[] = 'URL:' . card_share_url($profile);
$lines[] = 'NOTE;CHARSET=utf-8:' . vcard_text($isMaster ? $title . ' ' . $company . ' - 88MMM Circle' : '88MMM Circle Member - ' . $profile['tier']);
$lines[] = 'END:VCARD';

track_action('vcard_download', $id, current_user()['id'] ?? null);

$filename = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($name)), '-') ?: 'contact';
header('Content-Type: text/vcard; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '.vcf"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
echo implode("\r\n", array_map('vcard_fold', $lines)) . "\r\n";
