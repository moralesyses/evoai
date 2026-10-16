<?php
/**
 * WeServe member cards, built from the UserProfile and NetworkAccount tables
 * copied from the WeServe digital vCard app (database/weserve_members.sql).
 *
 * Cards live at /<userId>-<code> (?card=… redirects there); the code is an HMAC of the id signed
 * with card.link_secret, so other members' links can't be guessed. Only the
 * columns in WESERVE_MEMBER_COLUMNS are read — birth date, gender and tax ID
 * never leave the database. Only users with a network account in one of the
 * card.network_account_statuses states get a card.
 */
declare(strict_types=1);

const WESERVE_MEMBER_COLUMNS = 'userId, firstName, middleName, lastName, suffix, createdAt';

/** Whether the member tables exist and a link secret is configured. */
function weserve_enabled(): bool
{
    static $enabled = null;
    if ($enabled === null) {
        $enabled = (string) config('card.link_secret', '') !== ''
            && (int) db_value("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND LOWER(table_name) IN ('userprofile', 'networkaccount')") === 2;
    }
    return $enabled;
}

/** Link name for a member, e.g. "1042-3f9c2a7be1". */
function weserve_member_slug(int $userId): string
{
    return $userId . '-' . substr(hash_hmac('sha256', 'member:' . $userId, (string) config('card.link_secret', '')), 0, 10);
}

/** The member id in a link name, or null when it isn't a genuine member link. */
function weserve_member_id_from_slug(string $slug): ?int
{
    if (!preg_match('/^([1-9]\d{0,9})-[0-9a-f]{10}$/', $slug, $m)) {
        return null;
    }
    $userId = (int) $m[1];
    return hash_equals(weserve_member_slug($userId), $slug) ? $userId : null;
}

/** SQL condition: the profile's user has a network account in an allowed state. */
function weserve_has_network_account(): string
{
    $allowed = array_intersect(['active', 'inactive', 'expired', 'deleted'], (array) config('card.network_account_statuses', ['active', 'inactive', 'expired']));
    if (!$allowed) {
        return 'FALSE';
    }
    return 'EXISTS (SELECT 1 FROM NetworkAccount WHERE NetworkAccount.userId = UserProfile.userId'
        . " AND NetworkAccount.status IN ('" . implode("', '", $allowed) . "'))";
}

/** Trim and collapse spaces; fix names typed in all capitals or all lower case. */
function weserve_tidy_name(string $name): string
{
    $name = trim((string) preg_replace('/\s+/u', ' ', $name));
    if ($name === mb_strtoupper($name) || $name === mb_strtolower($name)) {
        $name = mb_convert_case(mb_strtolower($name), MB_CASE_TITLE);
    }
    return $name;
}

/** A member's public card details, or null when there is no such member. */
function weserve_load_member(int $userId): ?array
{
    if (!weserve_enabled()) {
        return null;
    }
    $row = db_one('SELECT ' . WESERVE_MEMBER_COLUMNS . ' FROM UserProfile WHERE userId = ? AND ' . weserve_has_network_account(), [$userId]);
    if (!$row) {
        return null;
    }
    $first = weserve_tidy_name((string) $row['firstName']);
    $last = weserve_tidy_name((string) $row['lastName']);
    $middle = weserve_tidy_name((string) $row['middleName']);
    // Kept as typed: title case would turn "III" into "Iii".
    $suffix = trim((string) preg_replace('/\s+/u', ' ', (string) $row['suffix']));
    // Middle names help verify identity in the Philippines, so show the initial only.
    $initial = $middle !== '' ? mb_strtoupper(mb_substr($middle, 0, 1)) . '.' : '';
    $slug = weserve_member_slug((int) $row['userId']);

    return [
        'userId'   => (int) $row['userId'],
        'slug'     => $slug,
        'name'     => implode(' ', array_filter([$first, $initial, $last, $suffix], 'strlen')),
        'title'    => 'Member since ' . substr((string) $row['createdAt'], 0, 4),
        'initials' => mb_strtoupper(mb_substr($first, 0, 1) . mb_substr($last, 0, 1)),
        'vcard'    => ['first' => $first, 'middle' => $initial, 'last' => $last, 'suffix' => $suffix],
        'shareUrl' => base_url() . '/' . $slug,
    ];
}

/** The WeServe member for a ?card= value, or null. */
function weserve_member_from_slug(string $slug): ?array
{
    $id = weserve_enabled() ? weserve_member_id_from_slug(strtolower(trim($slug, '/ '))) : null;
    return $id === null ? null : weserve_load_member($id);
}
