<?php
/**
 * Data access for the portal: CMS, member card profiles, analytics,
 * leads CRM, payments ledger, users and the audit log.
 *
 * Arrays returned to views/JS use the same camelCase keys as the
 * reference portal so templates read naturally.
 */
declare(strict_types=1);

/** The founder's master card; CMS identity fields stay in sync with it. */
const MASTER_PROFILE_ID = 'usr_mike';

const LEAD_STATUSES = ['new', 'contacted', 'converted'];
const PAYMENT_STATUSES = ['completed', 'pending', 'refunded'];

/* =====================================================================
 * CMS
 * =================================================================== */
function cms_defaults(): array
{
    static $defaults = null;
    if ($defaults === null) {
        $defaults = require APP_ROOT . '/includes/cms_defaults.php';
    }
    return $defaults;
}

function get_cms(): array
{
    $cms = cms_defaults();
    foreach (db_all('SELECT setting_key, setting_value FROM cms_settings') as $row) {
        if (!array_key_exists($row['setting_key'], $cms)) {
            continue;
        }
        $value = json_decode($row['setting_value'], true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $cms[$row['setting_key']] = $value;
        }
    }
    return $cms;
}

const CMS_URL_FIELDS = ['avatarUrl', 'visionaryImageUrl', 'featuredVideoUrl', 'featuredVideoThumbnailUrl', 'digitalImageUrl'];

/** Sanitize one CMS value according to the type of its default. */
function sanitize_cms_value(string $key, $value, $default)
{
    if (in_array($key, CMS_URL_FIELDS, true)) {
        return safe_url((string) $value);
    }
    if (is_int($default) || is_float($default)) {
        return is_numeric($value) ? max(0, (int) round((float) $value)) : $default;
    }
    if (is_string($default)) {
        $max = in_array($key, ['bio', 'societyDescription', 'educationDescription', 'digitalDescription'], true) ? 5000 : 500;
        return mb_substr(trim((string) $value), 0, $max);
    }
    if ($key === 'socials') {
        $out = [];
        foreach (['facebook', 'instagram', 'linkedin', 'twitter', 'telegram', 'website'] as $k) {
            if (is_array($value) && isset($value[$k])) {
                $url = safe_url((string) $value[$k], false);
                if ($url !== '') {
                    $out[$k] = $url;
                }
            }
        }
        return $out;
    }
    if (!is_array($value)) {
        return $default;
    }
    $value = array_slice(array_values($value), 0, 50);
    if (in_array($key, ['pillars', 'meetingsList'], true)) {
        $fields = $key === 'pillars' ? ['icon', 'title', 'desc'] : ['title', 'desc'];
        $out = [];
        foreach ($value as $item) {
            if (!is_array($item)) {
                continue;
            }
            $row = [];
            foreach ($fields as $f) {
                $row[$f] = mb_substr(trim((string) ($item[$f] ?? '')), 0, 200);
            }
            if ($row['title'] !== '') {
                $out[] = $row;
            }
        }
        return $out;
    }
    // Plain list of strings.
    $out = [];
    foreach ($value as $item) {
        $s = mb_substr(trim((string) (is_scalar($item) ? $item : '')), 0, 200);
        if ($s !== '') {
            $out[] = $s;
        }
    }
    return $out;
}

/** Apply a partial CMS update. Returns the full, updated CMS. */
function update_cms(array $patch): array
{
    $defaults = cms_defaults();
    $clean = [];
    foreach ($patch as $key => $value) {
        if (!is_string($key) || !array_key_exists($key, $defaults)) {
            continue;
        }
        $clean[$key] = sanitize_cms_value($key, $value, $defaults[$key]);
    }
    if (!$clean) {
        return get_cms();
    }
    db_tx(function () use ($clean) {
        $stmt = db()->prepare('INSERT INTO cms_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
        foreach ($clean as $k => $v) {
            $stmt->execute([$k, json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
        }
        // Keep the founder's master card in sync with the CMS identity fields.
        $map = ['name' => 'name', 'title' => 'title', 'company' => 'company', 'bio' => 'bio', 'phone' => 'phone', 'email' => 'email', 'avatarUrl' => 'avatar'];
        $profilePatch = [];
        foreach ($map as $cmsKey => $profileKey) {
            if (array_key_exists($cmsKey, $clean)) {
                $profilePatch[$profileKey] = $clean[$cmsKey];
            }
        }
        if ($profilePatch && db_value('SELECT 1 FROM member_profiles WHERE user_id = ?', [MASTER_PROFILE_ID])) {
            write_member_profile(MASTER_PROFILE_ID, $profilePatch);
        }
    });
    return get_cms();
}

function reset_cms(): array
{
    // The binary compensation plan lives in the same table but isn't CMS content.
    db_run('DELETE FROM cms_settings WHERE setting_key <> ?', [MLM_COMPLAN_KEY]);
    return update_cms(cms_defaults());
}

/* =====================================================================
 * Member card profiles (1EVOAI Card)
 * =================================================================== */
function decode_json_field(?string $json, $fallback)
{
    if ($json === null || $json === '') {
        return $fallback;
    }
    $v = json_decode($json, true);
    return json_last_error() === JSON_ERROR_NONE ? $v : $fallback;
}

function profile_row_to_array(array $r): array
{
    return [
        'userId'           => $r['user_id'],
        'name'             => $r['name'],
        'title'            => $r['title'],
        'company'          => $r['company'],
        'bio'              => (string) $r['bio'],
        'phone'            => $r['phone'],
        'email'            => $r['email'],
        'whatsapp'         => $r['whatsapp'],
        'avatar'           => $r['avatar'],
        'location'         => $r['location'],
        'quote'            => $r['quote'],
        'headline'         => $r['headline'],
        'subtitle'         => $r['subtitle'],
        'services'         => decode_json_field($r['services'], []),
        'socials'          => decode_json_field($r['socials'], []) ?: [],
        'nfcCardId'        => $r['nfc_card_id'],
        'tier'             => $r['tier'],
        'membershipStatus' => $r['membership_status'],
        'renewalDate'      => $r['renewal_date'],
        'customSlug'       => $r['custom_slug'],
        'cardTheme'        => $r['card_theme'],
        'nfcAction'        => $r['nfc_action'],
        'metrics'          => [
            'views'            => (int) $r['views'],
            'taps'             => (int) $r['taps'],
            'downloads'        => (int) $r['downloads'],
            'connectionsCount' => (int) $r['connections_count'],
        ],
    ];
}

/** Fallback profile for a user without a stored card (reference defaults). */
function default_profile(string $userId): array
{
    return [
        'userId' => $userId, 'name' => 'Executive Member', 'title' => 'Business Leader', 'company' => '88MMM Circle',
        'bio' => "Verified member of Mike Maranan Millionaire's Circle.", 'phone' => '+63 900 000 0000',
        'email' => $userId . '@mmmcircle.com', 'whatsapp' => '+639000000000', 'avatar' => null, 'location' => null,
        'quote' => null, 'headline' => null, 'subtitle' => null, 'services' => [], 'socials' => [],
        'nfcCardId' => 'MMC-EXE-' . rand_digits(5), 'tier' => 'Executive Member', 'membershipStatus' => 'Active',
        'renewalDate' => 'Dec 2027', 'customSlug' => '',
        'cardTheme' => 'gold', 'nfcAction' => 'card',
        'metrics' => ['views' => 120, 'taps' => 34, 'downloads' => 18, 'connectionsCount' => 9],
    ];
}

function get_member_profile(string $userId): array
{
    $row = db_one('SELECT * FROM member_profiles WHERE user_id = ?', [$userId]);
    return $row ? profile_row_to_array($row) : default_profile($userId);
}

/** All stored profiles keyed by user id, in roster order. */
function all_member_profiles(): array
{
    $out = [];
    $rows = db_all('SELECT p.* FROM member_profiles p JOIN users u ON u.id = p.user_id ORDER BY u.created_at, u.id');
    foreach ($rows as $r) {
        $out[$r['user_id']] = profile_row_to_array($r);
    }
    return $out;
}

function find_profile_by_slug(string $slug): ?array
{
    $slug = strtolower(trim($slug, '/ '));
    if ($slug === '') {
        return null;
    }
    // The card link name (e.g. "mike"); older databases may still hold "1evoai.com/mike".
    $row = db_one('SELECT * FROM member_profiles WHERE custom_slug = ? LIMIT 1', [$slug])
        ?? db_one('SELECT * FROM member_profiles WHERE LOWER(custom_slug) LIKE ? LIMIT 1', ['%/' . $slug]);
    return $row ? profile_row_to_array($row) : null;
}

/* ---------------------------------------------------------------------
 * Card link names: every card's address is <site>/<link name>, e.g.
 * http://localhost/evoai/juan-dela-cruz (.htaccess sends it to
 * index.php?card=…). Stored in member_profiles.custom_slug, unique.
 * ------------------------------------------------------------------- */

/** Names a card can't use: the site's own folders, files and pages. */
const CARD_SLUG_RESERVED = [
    'admin', 'api', 'app', 'assets', 'backoffice', 'bin', 'card', 'cards', 'chat', 'config', 'contact', 'database',
    'domain', 'domains', 'help', 'home', 'includes', 'index', 'login', 'logout', 'mail', 'main', 'manifest', 'maya-webhook',
    'member', 'members', 'pay', 'payment', 'portal', 'prod', 'public', 'register', 'robots', 'signin', 'signup', 'site',
    'staging', 'storage', 'support', 'sw', 'unsubscribe', 'uploads', 'vcard', 'vendor', 'views', 'webhook', 'www',
];

/** A link name from free text: the part after the last "/", lowercase letters, digits and dashes. */
function card_slug_clean(string $text): string
{
    $s = mb_strtolower(trim($text));
    if (str_contains($s, '/')) {
        $s = substr($s, strrpos($s, '/') + 1); // "1evoai.com/mike" -> "mike"
    }
    $s = strtr($s, ['á' => 'a', 'à' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i', 'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ñ' => 'n', 'ç' => 'c']);
    $s = trim((string) preg_replace('/[^a-z0-9]+/', '-', $s), '-');
    return trim(substr($s, 0, 60), '-');
}

/** Why $slug can't be $userId's card link name, or '' when it can. */
function card_slug_problem(string $slug, ?string $userId = null): string
{
    if (!preg_match('/^[a-z][a-z0-9-]{2,59}$/', $slug)) {
        return 'Your card link needs 3 to 60 letters, numbers or dashes, starting with a letter.';
    }
    if (in_array($slug, CARD_SLUG_RESERVED, true)) {
        return '"' . $slug . '" is reserved for the portal. Please choose another card link.';
    }
    if (db_value('SELECT 1 FROM member_profiles WHERE custom_slug = ? AND user_id <> ?', [$slug, (string) $userId])) {
        return '"' . $slug . '" is already another member\'s card link. Please choose another.';
    }
    return '';
}

/** A free link name made from $text (usually the member's name): "juan-dela-cruz", then "juan-dela-cruz-2", … */
function card_slug_unique(string $text, ?string $userId = null): string
{
    $base = card_slug_clean($text);
    if (!preg_match('/^[a-z]/', $base)) {
        $base = trim('member-' . $base, '-');
    }
    if (strlen($base) < 3 || in_array($base, CARD_SLUG_RESERVED, true)) {
        $base = $base . '-card';
    }
    for ($i = 1; ; $i++) {
        $try = $i === 1 ? $base : trim(substr($base, 0, 55), '-') . '-' . $i;
        if (card_slug_problem($try, $userId) === '') {
            return $try;
        }
    }
}

const PROFILE_FIELD_MAP = [
    'name' => 'name', 'title' => 'title', 'company' => 'company', 'bio' => 'bio', 'phone' => 'phone',
    'email' => 'email', 'whatsapp' => 'whatsapp', 'avatar' => 'avatar', 'location' => 'location',
    'quote' => 'quote', 'headline' => 'headline', 'subtitle' => 'subtitle', 'services' => 'services',
    'socials' => 'socials', 'nfcCardId' => 'nfc_card_id', 'tier' => 'tier', 'membershipStatus' => 'membership_status',
    'renewalDate' => 'renewal_date', 'customSlug' => 'custom_slug', 'cardTheme' => 'card_theme', 'nfcAction' => 'nfc_action',
];

/** Validate a profile patch (camelCase keys) into DB column => value. */
function sanitize_profile_patch(array $patch): array
{
    $cols = [];
    foreach ($patch as $key => $value) {
        if (!isset(PROFILE_FIELD_MAP[$key])) {
            continue;
        }
        $col = PROFILE_FIELD_MAP[$key];
        switch ($key) {
            case 'services':
                $list = [];
                foreach (array_slice(is_array($value) ? array_values($value) : explode(',', (string) $value), 0, 20) as $s) {
                    $s = mb_substr(trim((string) (is_scalar($s) ? $s : '')), 0, 60);
                    if ($s !== '') {
                        $list[] = $s;
                    }
                }
                $cols[$col] = json_encode($list, JSON_UNESCAPED_UNICODE);
                break;
            case 'socials':
                $out = [];
                foreach (['linkedin', 'twitter', 'facebook', 'instagram', 'telegram', 'website'] as $k) {
                    if (is_array($value) && !empty($value[$k])) {
                        $url = safe_url((string) $value[$k], false);
                        if ($url !== '') {
                            $out[$k] = $url;
                        }
                    }
                }
                $cols[$col] = json_encode($out, JSON_UNESCAPED_SLASHES);
                break;
            case 'avatar':
                $cols[$col] = safe_url((string) $value) ?: null;
                break;
            case 'email':
                $email = trim((string) $value);
                if ($email !== '' && !valid_email($email)) {
                    throw new InvalidArgumentException('Please enter a valid email address.');
                }
                $cols[$col] = $email;
                break;
            case 'bio':
                $cols[$col] = mb_substr(trim((string) $value), 0, 5000);
                break;
            case 'name':
                $name = mb_substr(trim((string) $value), 0, 150);
                if ($name === '') {
                    throw new InvalidArgumentException('Display name cannot be empty.');
                }
                $cols[$col] = $name;
                break;
            case 'cardTheme':
                $cols[$col] = in_array($value, ['gold', 'obsidian', 'platinum', 'rosegold'], true) ? $value : 'gold';
                break;
            case 'nfcAction':
                $cols[$col] = in_array($value, ['card', 'vcard', 'whatsapp'], true) ? $value : 'card';
                break;
            case 'customSlug':
                // Checked against the other members in write_member_profile().
                $cols[$col] = card_slug_clean(is_scalar($value) ? (string) $value : '');
                break;
            default:
                $v = mb_substr(trim((string) (is_scalar($value) ? $value : '')), 0, 255);
                $cols[$col] = ($v === '' && in_array($key, ['location', 'quote', 'headline', 'subtitle', 'nfcCardId'], true)) ? null : $v;
        }
    }
    return $cols;
}

/** Low-level upsert of profile columns (patch uses camelCase keys). */
function write_member_profile(string $userId, array $patch): void
{
    $cols = sanitize_profile_patch($patch);
    if (!db_value('SELECT 1 FROM member_profiles WHERE user_id = ?', [$userId])) {
        $base = default_profile($userId);
        $user = db_one('SELECT name, email, title FROM users WHERE id = ?', [$userId]);
        if ($user) {
            $base['name'] = $user['name'];
            $base['email'] = $user['email'];
        }
        create_member_profile($userId, $base);
    }
    if (!$cols) {
        return;
    }
    if (array_key_exists('custom_slug', $cols)) {
        if ($cols['custom_slug'] === '') {
            // Left empty: a link name from the member's name.
            $cols['custom_slug'] = card_slug_unique((string) ($cols['name'] ?? db_value('SELECT name FROM member_profiles WHERE user_id = ?', [$userId])), $userId);
        } elseif (($problem = card_slug_problem($cols['custom_slug'], $userId)) !== '') {
            throw new InvalidArgumentException($problem);
        }
    }
    $sets = implode(', ', array_map(fn ($c) => "`$c` = ?", array_keys($cols)));
    db_run("UPDATE member_profiles SET $sets WHERE user_id = ?", [...array_values($cols), $userId]);
}

function create_member_profile(string $userId, array $data): void
{
    $p = array_merge(default_profile($userId), $data);
    // The card's link name: the one given if it is free, otherwise one from the name.
    $slug = card_slug_clean((string) $p['customSlug']);
    $p['customSlug'] = $slug !== '' && card_slug_problem($slug, $userId) === '' ? $slug : card_slug_unique((string) $p['name'], $userId);
    db_run(
        'INSERT INTO member_profiles (user_id, name, title, company, bio, phone, email, whatsapp, avatar, nfc_card_id, tier, membership_status, renewal_date, custom_slug, socials, views, taps, downloads, connections_count)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, 0, 0)',
        [$userId, $p['name'], $p['title'], $p['company'], $p['bio'], $p['phone'], $p['email'], $p['whatsapp'], $p['avatar'],
         $p['nfcCardId'], $p['tier'], $p['membershipStatus'], $p['renewalDate'], $p['customSlug'], json_encode($p['socials'] ?: new stdClass())]
    );
}

/** Profile photos members upload in Backoffice > Digital Card CMS. */
const AVATAR_DIR = 'uploads/avatars';

/** A member uploads their profile picture: it replaces the current one on their card at once. */
function profile_avatar_upload(array $user, array $file): array
{
    $path = image_upload_store($file, AVATAR_DIR);
    update_member_profile($user['id'], ['avatar' => $path]);
    add_audit('Profile picture uploaded', $user['id']);
    return ['avatar' => $path, 'url' => media_url($path)];
}

/** The member's card goes back to showing their initials. */
function profile_avatar_remove(array $user): void
{
    update_member_profile($user['id'], ['avatar' => '']);
    add_audit('Profile picture removed', $user['id']);
}

/** Delete an uploaded profile photo once no card, website or portal setting uses it. */
function profile_avatar_delete_file(string $path): void
{
    if (!image_upload_is_ours($path, AVATAR_DIR)) {
        return;
    }
    $like = '%' . $path . '%';
    if (db_value('SELECT 1 FROM member_profiles WHERE avatar = ?', [$path])
        || db_value('SELECT 1 FROM cms_settings WHERE setting_value LIKE ?', [$like])
        || db_value('SELECT 1 FROM member_websites WHERE draft LIKE ? OR published LIKE ?', [$like, $like])) {
        return;
    }
    if (is_file(APP_ROOT . '/' . $path)) {
        @unlink(APP_ROOT . '/' . $path);
    }
}

/** Update a member card; the master card also updates the portal CMS. */
function update_member_profile(string $userId, array $patch): array
{
    $oldAvatar = array_key_exists('avatar', $patch) ? (string) db_value('SELECT avatar FROM member_profiles WHERE user_id = ?', [$userId]) : '';
    write_member_profile($userId, $patch);
    $profile = get_member_profile($userId);
    if ($userId === MASTER_PROFILE_ID) {
        $cmsPatch = [];
        foreach (['name' => 'name', 'title' => 'title', 'company' => 'company', 'bio' => 'bio', 'phone' => 'phone', 'email' => 'email', 'whatsapp' => 'whatsapp', 'avatar' => 'avatarUrl'] as $pk => $ck) {
            if (array_key_exists($pk, $patch) && (string) $profile[$pk] !== '') {
                $cmsPatch[$ck] = $profile[$pk];
            }
        }
        if ($cmsPatch) {
            $defaults = cms_defaults();
            $stmt = db()->prepare('INSERT INTO cms_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
            foreach ($cmsPatch as $k => $v) {
                $stmt->execute([$k, json_encode(sanitize_cms_value($k, $v, $defaults[$k]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
            }
        }
    }
    // A replaced or removed uploaded photo is deleted once nothing uses it.
    if ($oldAvatar !== '' && $oldAvatar !== (string) $profile['avatar']) {
        profile_avatar_delete_file($oldAvatar);
    }
    return $profile;
}

/**
 * Public share link for a card: the member's own .com domain when their card
 * uses one (domain_card_url), otherwise its address on this site, e.g.
 * http://localhost/evoai/juan-dela-cruz. $ownDomain = false: always the
 * address on this site.
 */
function card_share_url(array $profile, bool $ownDomain = true): string
{
    if ($ownDomain && ($url = domain_card_url((string) ($profile['userId'] ?? ''))) !== null) {
        return $url;
    }
    $slug = card_slug_clean((string) ($profile['customSlug'] ?? ''));
    return $slug !== ''
        ? base_url() . '/' . $slug
        : base_url() . '/index.php?' . http_build_query(['view' => 'card', 'profile' => (string) ($profile['userId'] ?? '')]);
}

/* =====================================================================
 * Analytics
 * =================================================================== */
const TRACK_ACTIONS = [
    'profile_view'   => ['counter' => 'profileViews', 'profile' => 'views', 'daily' => 'views'],
    'nfc_tap'        => ['counter' => 'nfcTaps', 'profile' => 'taps', 'daily' => 'taps'],
    'vcard_download' => ['counter' => 'vcardDownloads', 'profile' => 'downloads', 'daily' => null],
    'qr_scan'        => ['counter' => 'qrScans', 'profile' => null, 'daily' => null],
];

function get_analytics(): array
{
    $counters = ['profileViews' => 0, 'nfcTaps' => 0, 'vcardDownloads' => 0, 'qrScans' => 0, 'messagesExchanged' => 0];
    foreach (db_all('SELECT metric, value FROM analytics_counters') as $r) {
        $counters[$r['metric']] = (int) $r['value'];
    }
    $history = array_map(fn ($r) => ['date' => $r['day_label'], 'taps' => (int) $r['taps'], 'views' => (int) $r['views']],
        db_all('SELECT day_label, taps, views FROM analytics_daily ORDER BY sort_order, id'));
    return $counters + ['history' => $history];
}

function bump_counter(string $metric, int $by = 1): void
{
    db_run('INSERT INTO analytics_counters (metric, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = value + VALUES(value)', [$metric, $by]);
}

/** Record a card interaction (profile view, NFC tap, vCard download, QR scan). */
function track_action(string $action, ?string $profileId = null, ?string $userId = null): bool
{
    if (!isset(TRACK_ACTIONS[$action])) {
        return false;
    }
    // Light de-duplication of repeated views from the same session.
    if ($action === 'profile_view') {
        $key = 'viewed_' . ($profileId ?? 'master');
        if (!empty($_SESSION[$key]) && $_SESSION[$key] > time() - 1800) {
            return false;
        }
        $_SESSION[$key] = time();
    }
    $def = TRACK_ACTIONS[$action];
    bump_counter($def['counter']);
    if ($profileId && $def['profile']) {
        db_run("UPDATE member_profiles SET `{$def['profile']}` = `{$def['profile']}` + 1 WHERE user_id = ?", [$profileId]);
    }
    if ($def['daily']) {
        db_run("UPDATE analytics_daily SET `{$def['daily']}` = `{$def['daily']}` + 1 WHERE day_label = ?", [date('D')]);
    }
    db_run('INSERT INTO analytics_events (action, profile_id, user_id, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)',
        [$action, $profileId, $userId, client_ip(), mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255)]);
    return true;
}

/* =====================================================================
 * Leads CRM
 * =================================================================== */
function lead_to_array(array $r): array
{
    return [
        'id' => $r['id'], 'name' => $r['name'], 'email' => $r['email'], 'phone' => $r['phone'],
        'company' => $r['company'], 'interest' => $r['interest'], 'message' => (string) $r['message'],
        'createdAt' => to_ms($r['created_at']), 'status' => $r['status'],
    ];
}

function list_leads(): array
{
    return array_map('lead_to_array', db_all('SELECT * FROM leads ORDER BY created_at DESC, id DESC'));
}

function create_lead(array $in): array
{
    $name = str_in($in, 'name', 150);
    $email = str_in($in, 'email', 190);
    if ($name === '') {
        throw new InvalidArgumentException('Please enter your full name.');
    }
    if ($email !== '' && !valid_email($email)) {
        throw new InvalidArgumentException('Please enter a valid email address.');
    }
    $id = gen_id('lead_');
    db_run('INSERT INTO leads (id, name, email, phone, company, interest, message, status) VALUES (?, ?, ?, ?, ?, ?, ?, "new")', [
        $id, $name, $email, str_in($in, 'phone', 60), str_in($in, 'company', 190), str_in($in, 'interest', 190), str_in($in, 'message', 2000),
    ]);
    return lead_to_array(db_one('SELECT * FROM leads WHERE id = ?', [$id]));
}

function update_lead_status(string $id, string $status): void
{
    if (!in_array($status, LEAD_STATUSES, true)) {
        throw new InvalidArgumentException('Unknown lead status.');
    }
    db_run('UPDATE leads SET status = ? WHERE id = ?', [$status, $id]);
}

/* =====================================================================
 * Payments ledger & checkout
 * =================================================================== */
function payment_to_array(array $r): array
{
    return [
        'id' => $r['id'], 'userId' => $r['user_id'], 'customerName' => $r['customer_name'],
        'customerEmail' => $r['customer_email'], 'customerPhone' => $r['customer_phone'],
        'itemType' => $r['item_type'], 'itemName' => $r['item_name'], 'amount' => (float) $r['amount'],
        'currency' => $r['currency'], 'paymentMethod' => $r['payment_method'], 'status' => $r['status'],
        'timestamp' => to_ms($r['created_at']), 'referenceNumber' => $r['reference_number'],
        'nfcCardSerial' => $r['nfc_card_serial'], 'receiptNote' => $r['receipt_note'], 'invoiceLink' => $r['invoice_link'],
    ];
}

function list_payments(): array
{
    return array_map('payment_to_array', db_all('SELECT * FROM payments ORDER BY created_at DESC, id DESC'));
}

/** Purchasable items (prices for the tiers come from the CMS). */
function checkout_item(string $type, ?array $cms = null): ?array
{
    $cms = $cms ?? get_cms();
    switch ($type) {
        case 'membership_chairmans':
            return ['name' => "Chairman's Circle (VIP Tier) - 7 NFC Cards", 'price' => (int) ($cms['chairmansPrice'] ?: 14000),
                'desc' => 'Flagship tier with 7 Executive NFC Cards, 7 Custom Portals, Mastermind Dinners, and Syndicate Introductions.', 'badge' => 'FLAGSHIP VIP'];
        case 'nfc_card_upgrade':
            return ['name' => 'Executive Obsidian NFC Metal Smart Card', 'price' => 1500,
                'desc' => 'Solid laser-etched black matte titanium card with embedded NTAG424 DNA cryptographic chip.', 'badge' => 'HARDWARE'];
        case 'hardware_combo':
            return ['name' => '24K Gold Smart Ring & NFC Monogram Card Combo', 'price' => 3800,
                'desc' => 'Waterproof contactless executive smart ring paired with gold foil executive smart card.', 'badge' => 'LUXURY PACK'];
        case 'renewal':
            return ['name' => 'Annual Society Renewal (1 Year Extended)', 'price' => 2000,
                'desc' => 'Extends verified digital credentials, breakfast club access, and active NFC tap routing.', 'badge' => 'RENEWAL'];
        case 'membership_executive':
            return ['name' => 'Executive Member - 1 NFC Card & Custom Portal', 'price' => (int) ($cms['executivePrice'] ?: 2000),
                'desc' => 'Includes 1 Smart NFC Executive Card, One-Tap Digital Presence, and Weekly Executive Masterminds.', 'badge' => 'STANDARD TIER'];
    }
    return null;
}

function unique_payment_id(): string
{
    do {
        $id = 'TXN-' . rand_digits(5);
    } while (db_value('SELECT 1 FROM payments WHERE id = ?', [$id]));
    return $id;
}

/** A payment reference such as 88MMM-MAYA-123456 that no other payment uses. */
function unique_payment_reference(string $method): string
{
    do {
        $ref = '88MMM-' . substr(strtoupper($method), 0, 5) . '-' . rand_digits(6);
    } while (db_value('SELECT 1 FROM payments WHERE reference_number = ?', [$ref]));
    return $ref;
}

/**
 * Validate a checkout: the item (or pending invoice) and the receipt details.
 * Returns ['itemType', 'item' => ['name', 'price'], 'invoice' (row|null), 'name', 'email', 'phone'].
 */
function checkout_request(array $in): array
{
    $itemType = str_in($in, 'itemType', 40);
    $name = str_in($in, 'customerName', 150);
    $email = str_in($in, 'customerEmail', 190);
    $phone = str_in($in, 'customerPhone', 60);
    $invoiceRef = str_in($in, 'invoiceRef', 60);

    if ($name === '') {
        throw new InvalidArgumentException('Please enter the full name for the receipt.');
    }
    if (!valid_email($email)) {
        throw new InvalidArgumentException('Please enter a valid email address for the receipt.');
    }
    if ($phone === '') {
        throw new InvalidArgumentException('Please enter a mobile phone number.');
    }

    $cms = get_cms();
    $invoice = null;
    if ($invoiceRef !== '') {
        $invoice = db_one('SELECT * FROM payments WHERE reference_number = ? AND status = "pending"', [$invoiceRef]);
        if (!$invoice) {
            throw new InvalidArgumentException('This invoice link is no longer valid or has already been paid.');
        }
        $item = ['name' => $invoice['item_name'], 'price' => (float) $invoice['amount']];
        $itemType = $invoice['item_type'];
    } else {
        $item = checkout_item($itemType, $cms);
        if (!$item) {
            throw new InvalidArgumentException('Please select a membership tier.');
        }
    }

    return ['itemType' => $itemType, 'item' => $item, 'invoice' => $invoice, 'name' => $name, 'email' => $email, 'phone' => $phone];
}

/**
 * Write a completed payment for a checkout_request(); call inside db_tx().
 * Settles the invoice when it is still pending, otherwise adds a new ledger
 * entry (the money was received either way). Returns the payment id.
 */
function write_checkout_payment(array $req, string $method, ?string $userId, string $note): string
{
    $serial = ($req['itemType'] === 'membership_chairmans' ? 'MMC-VIP-' : 'MMC-EXE-') . rand_digits(5);
    if ($req['invoice']) {
        $settled = db_run('UPDATE payments SET user_id = COALESCE(user_id, ?), customer_name = ?, customer_email = ?, customer_phone = ?, payment_method = ?, status = "completed", nfc_card_serial = ?, receipt_note = ? WHERE id = ? AND status = "pending"',
            [$userId, $req['name'], $req['email'], $req['phone'], $method, $serial, $note, $req['invoice']['id']]);
        if ($settled->rowCount() === 1) {
            return $req['invoice']['id'];
        }
        $note .= ' Invoice ' . $req['invoice']['reference_number'] . ' had already been settled, so this is recorded separately.';
    }
    $id = unique_payment_id();
    db_run('INSERT INTO payments (id, user_id, customer_name, customer_email, customer_phone, item_type, item_name, amount, currency, payment_method, status, reference_number, nfc_card_serial, receipt_note)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, "PHP", ?, "completed", ?, ?, ?)',
        [$id, $userId, $req['name'], $req['email'], $req['phone'], $req['itemType'], $req['item']['name'], $req['item']['price'], $method, unique_payment_reference($method), $serial, $note]);
    return $id;
}

/** After a payment is written: activate the member's card, add the CRM lead and audit it. */
function checkout_after_payment(string $paymentId, ?string $userId): array
{
    $payment = payment_to_array(db_one('SELECT * FROM payments WHERE id = ?', [$paymentId]));
    if ($userId && in_array($payment['itemType'], ['membership_executive', 'membership_chairmans', 'renewal'], true)) {
        $patch = ['membershipStatus' => 'Active', 'nfcCardId' => $payment['nfcCardSerial'], 'renewalDate' => date('M Y', strtotime('+1 year'))];
        if ($payment['itemType'] !== 'renewal') {
            $patch['tier'] = $payment['itemType'] === 'membership_chairmans' ? "Chairman's Circle" : 'Executive Member';
        }
        update_member_profile($userId, $patch);
    }
    $method = strtoupper($payment['paymentMethod']);
    create_lead([
        'name' => $payment['customerName'], 'email' => $payment['customerEmail'], 'phone' => $payment['customerPhone'],
        'company' => 'Online Payment Checkout', 'interest' => $payment['itemName'],
        'message' => sprintf('Completed online checkout of PHP %s via %s. Ref: %s', num($payment['amount']), $payment['paymentMethod'], $payment['referenceNumber']),
    ]);
    add_audit('Online payment completed: ' . peso($payment['amount']) . ' via ' . $method . ' (' . $payment['referenceNumber'] . ')', $userId);
    return $payment;
}

function update_payment_status(string $id, string $status): void
{
    if (!in_array($status, PAYMENT_STATUSES, true)) {
        throw new InvalidArgumentException('Unknown payment status.');
    }
    db_run('UPDATE payments SET status = ? WHERE id = ?', [$status, $id]);
}

/** Admin-issued invoice: stored as a pending ledger entry with a pay link. */
function create_invoice(array $in, array $admin): array
{
    $name = str_in($in, 'clientName', 150);
    $email = str_in($in, 'clientEmail', 190);
    $item = str_in($in, 'item', 190);
    $amount = float_in($in, 'amount');
    if ($name === '') {
        throw new InvalidArgumentException('Please enter the client / executive name.');
    }
    if ($email !== '' && !valid_email($email)) {
        throw new InvalidArgumentException('Please enter a valid client email.');
    }
    if ($amount <= 0 || $amount > 10000000) {
        throw new InvalidArgumentException('Please enter a valid invoice amount.');
    }
    $type = stripos($item, 'chairman') !== false ? 'membership_chairmans' : (stripos($item, 'card') !== false ? 'nfc_card_upgrade' : 'membership_executive');
    $ref = 'INV-' . rand_digits(6);
    $link = base_url() . '/index.php?' . http_build_query(['view' => 'pay', 'invoice' => $ref]);
    $id = unique_payment_id();
    db_run('INSERT INTO payments (id, user_id, customer_name, customer_email, item_type, item_name, amount, currency, payment_method, status, reference_number, receipt_note, invoice_link)
            VALUES (?, NULL, ?, ?, ?, ?, ?, "PHP", "invoice", "pending", ?, ?, ?)',
        [$id, $name, $email, $type, $item ?: 'Executive Membership Invoice', $amount, $ref, 'Invoice issued by ' . $admin['name'] . '. Awaiting online payment.', $link]);
    add_audit('Issued payment invoice ' . $ref . ' (' . peso($amount) . ') to ' . $name, $admin['id']);
    return payment_to_array(db_one('SELECT * FROM payments WHERE id = ?', [$id]));
}

function find_pending_invoice(string $ref): ?array
{
    $row = db_one('SELECT * FROM payments WHERE reference_number = ? AND status = "pending"', [$ref]);
    return $row ? payment_to_array($row) : null;
}

/* =====================================================================
 * Users & roles
 * =================================================================== */
function list_users(): array
{
    return array_map('user_public', db_all('SELECT * FROM users ORDER BY created_at, id'));
}

function update_user_role(array $admin, string $userId, string $role): void
{
    if (!array_key_exists($role, ROLE_LABELS)) {
        throw new InvalidArgumentException('Unknown access role.');
    }
    if ($userId === $admin['id'] && $role !== 'admin') {
        throw new InvalidArgumentException('You cannot remove your own admin access.');
    }
    $target = db_one('SELECT id, name FROM users WHERE id = ?', [$userId]);
    if (!$target) {
        throw new InvalidArgumentException('Member not found.');
    }
    db_run('UPDATE users SET role = ? WHERE id = ?', [$role, $userId]);
    add_audit('Access role for ' . $target['name'] . ' set to ' . ROLE_LABELS[$role], $admin['id']);
}

/** Store the member's ECDH P-256 public key (JWK) uploaded by the browser. */
function set_user_public_key(string $userId, string $jwk): void
{
    $data = json_decode($jwk, true);
    if (!is_array($data) || ($data['kty'] ?? '') !== 'EC' || ($data['crv'] ?? '') !== 'P-256' || empty($data['x']) || empty($data['y'])) {
        throw new InvalidArgumentException('Invalid public key.');
    }
    $clean = json_encode(['crv' => 'P-256', 'ext' => true, 'key_ops' => [], 'kty' => 'EC', 'x' => (string) $data['x'], 'y' => (string) $data['y']], JSON_UNESCAPED_SLASHES);
    db_run('UPDATE users SET public_key = ? WHERE id = ?', [$clean, $userId]);
}

/* =====================================================================
 * Audit log
 * =================================================================== */
function add_audit(string $action, ?string $userId = null, ?string $accountId = null): void
{
    db_run('INSERT INTO audit_logs (id, user_id, account_id, action, ip_address, device) VALUES (?, ?, ?, ?, ?, ?)',
        [gen_id('log_'), $userId, $accountId, mb_substr($action, 0, 255), client_ip(), device_label()]);
}

function log_to_array(array $r): array
{
    return ['id' => $r['id'], 'userId' => $r['user_id'], 'accountId' => $r['account_id'], 'action' => $r['action'],
        'ipAddress' => $r['ip_address'], 'device' => $r['device'], 'timestamp' => to_ms($r['created_at'])];
}

function list_audit(int $limit = 50, ?string $userId = null): array
{
    $limit = max(1, min(500, $limit));
    if ($userId) {
        return array_map('log_to_array', db_all("SELECT * FROM audit_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT $limit", [$userId]));
    }
    return array_map('log_to_array', db_all("SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT $limit"));
}
