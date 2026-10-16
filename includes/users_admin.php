<?php
/**
 * Superadmin user management (Management Admin > Users).
 * Nobody is listed until the admin presses Show users (admin_user_filter()): filters for name (also
 * email, user ID or one of the user's backoffice accounts), package, date activated and items per page;
 * sort by date activated or name. Edit account fields (only changed fields are written, with optimistic
 * locking on updated_at); delete with confirmation.
 */
declare(strict_types=1);

const USERS_PER_PAGE = 25;
const USERS_PER_PAGE_CHOICES = [10, 25, 50, 100];
const USER_SORTS = [
    'newest' => ['Date activated: newest to oldest', 'u.created_at DESC, u.id DESC'],
    'oldest' => ['Date activated: oldest to newest', 'u.created_at ASC, u.id ASC'],
    'name'   => ['Name: A to Z', 'u.name ASC, u.id ASC'],
];
const USER_STATUSES = ['active' => 'Active', 'suspended' => 'Suspended'];
/** Package filter: the tier on the user's card profile (set at sign-up or by an upgrade payment; members can't edit it). */
const USER_PACKAGES = ['executive' => 'Executive Member', 'chairmans' => "Chairman's Circle"];

/**
 * The Users list's filters from the query string, checked. 'show' is false until the admin presses
 * Show users (nobody is listed by default); 'error' explains a filter that can't be used (nothing is
 * listed then). Dates are YYYY-MM-DD, both ends included; "activated" is when the user's account
 * was created (sign-ups are created once paid or activated with a code).
 */
function admin_user_filter(array $get): array
{
    $str = static fn (string $k, int $max): string => mb_substr(trim(is_string($get[$k] ?? null) ? $get[$k] : ''), 0, $max);
    $date = static function (string $v): ?string {
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $v);
        return $d && $d->format('Y-m-d') === $v ? $v : null;
    };
    $f = [
        'show' => ($get['show'] ?? '') === '1',
        'q' => $str('q', 100),
        'package' => isset(USER_PACKAGES[$get['package'] ?? '']) ? (string) $get['package'] : '',
        'per' => in_array((int) ($get['per'] ?? 0), USERS_PER_PAGE_CHOICES, true) ? (int) $get['per'] : USERS_PER_PAGE,
        'from' => $str('from', 10),
        'to' => $str('to', 10),
        'sort' => is_string($get['sort'] ?? null) && isset(USER_SORTS[$get['sort']]) ? $get['sort'] : 'newest',
        'page' => max(1, (int) ($get['page'] ?? 1)),
        'error' => '',
    ];
    foreach (['from' => 'Activated from', 'to' => 'Activated to'] as $k => $label) {
        if ($f[$k] !== '' && $date($f[$k]) === null) {
            $f['error'] = "\"{$label}\" isn't a date. Pick one from the calendar.";
        }
    }
    if ($f['error'] === '' && $f['from'] !== '' && $f['to'] !== '' && $f['from'] > $f['to']) {
        $f['error'] = 'The "Activated from" date is after the "Activated to" date.';
    }
    return $f;
}

/** The filter as query parameters for links (pages, Clear), without defaults. */
function admin_user_filter_query(array $f, array $override = []): array
{
    $f = $override + $f;
    return array_filter([
        'tab' => 'users', 'show' => $f['show'] ? '1' : '', 'q' => $f['q'], 'package' => $f['package'],
        'from' => $f['from'], 'to' => $f['to'], 'per' => $f['per'] !== USERS_PER_PAGE ? (string) $f['per'] : '',
        'sort' => $f['sort'] !== 'newest' ? $f['sort'] : '', 'page' => $f['page'] > 1 ? (string) $f['page'] : '',
    ], fn ($v) => $v !== '');
}

/**
 * One page of users for the filter (admin_user_filter()), with whether they have a card profile and
 * their backoffice accounts, the number of users that match and the number of backoffice accounts
 * those users have. The name filter also finds a user by email, user ID, or one of their accounts: its
 * ID (MMM-88004), name (MMMC3) or username; such accounts come back with 'match' => true.
 */
function admin_search_users(array $f): array
{
    $search = $f['q'];
    $conds = [];
    $params = [];
    if ($search !== '') {
        $like = '%' . addcslashes($search, '%_\\') . '%';
        $conds[] = '(u.id = ? OR u.name LIKE ? OR u.email LIKE ?
                  OR EXISTS (SELECT 1 FROM mlm_accounts a WHERE a.user_id = u.id AND (a.id = ? OR a.account_name LIKE ? OR a.username LIKE ?)))';
        array_push($params, $search, $like, $like, $search, $like, $like);
    }
    if ($f['package'] !== '') {
        $conds[] = 'p.tier = ?';
        $params[] = USER_PACKAGES[$f['package']];
    }
    if ($f['from'] !== '') {
        $conds[] = 'u.created_at >= ?';
        $params[] = $f['from'] . ' 00:00:00';
    }
    if ($f['to'] !== '') {
        $conds[] = 'u.created_at < ? + INTERVAL 1 DAY';
        $params[] = $f['to'];
    }
    $where = $conds ? 'WHERE ' . implode(' AND ', $conds) : '';
    $from = 'FROM users u LEFT JOIN member_profiles p ON p.user_id = u.id';
    $total = (int) db_value("SELECT COUNT(*) $from $where", $params);
    $accountTotal = (int) db_value("SELECT COUNT(*) $from JOIN mlm_accounts ma ON ma.user_id = u.id $where", $params);
    $per = $f['per'];
    $offset = (max(1, $f['page']) - 1) * $per;
    $order = USER_SORTS[$f['sort']][1];
    $rows = db_all(
        "SELECT u.id, u.name, u.email, u.role, u.title, u.status, u.verified, u.joined_date, u.last_login_at, u.created_at,
                (p.user_id IS NOT NULL) AS has_card, p.tier
         $from $where ORDER BY $order LIMIT $per OFFSET $offset",
        $params
    );
    $accounts = admin_users_accounts(array_column($rows, 'id'), $search);
    foreach ($rows as &$row) {
        $row['accounts'] = $accounts[$row['id']] ?? [];
    }
    unset($row);
    return [$rows, $total, $accountTotal];
}

/** The backoffice accounts of these users (main account first), by user ID; 'match': the search found it. */
function admin_users_accounts(array $userIds, string $search = ''): array
{
    if (!$userIds) {
        return [];
    }
    $marks = implode(',', array_fill(0, count($userIds), '?'));
    $out = [];
    foreach (db_all("SELECT id, user_id, username, account_name, head_number, status, entry_date, available_balance, total_income, left_count, right_count
                     FROM mlm_accounts WHERE user_id IN ($marks) ORDER BY head_number, id", array_values($userIds)) as $a) {
        $a['match'] = $search !== '' && (strcasecmp($a['id'], $search) === 0
            || mb_stripos((string) $a['account_name'], $search) !== false || mb_stripos((string) $a['username'], $search) !== false);
        $out[$a['user_id']][] = $a;
    }
    return $out;
}

function admin_find_user(string $userId): ?array
{
    return db_one('SELECT id, name, email, role, title, status, verified, joined_date, last_login_at, created_at, updated_at FROM users WHERE id = ?', [$userId]);
}

/** Editor payload (never includes the password hash). */
function admin_user_for_editor(array $u, array $admin): array
{
    $hasCard = (bool) db_value('SELECT 1 FROM member_profiles WHERE user_id = ?', [$u['id']]);
    return [
        'id' => $u['id'],
        'name' => $u['name'],
        'email' => $u['email'],
        'title' => $u['title'],
        'role' => $u['role'],
        'status' => $u['status'],
        'verified' => (bool) $u['verified'],
        'joinedDate' => $u['joined_date'],
        'createdAt' => $u['created_at'],
        'lastLoginAt' => $u['last_login_at'],
        'updatedAt' => $u['updated_at'],
        'isSelf' => $u['id'] === $admin['id'],
        'isFounder' => $u['id'] === MASTER_PROFILE_ID,
        'cardUrl' => $hasCard ? base_url() . '/index.php?' . http_build_query(['view' => 'card', 'profile' => $u['id']]) : null,
    ];
}

/**
 * Compare submitted values with the stored user.
 * Returns [changes (column => new value), errors (field => message)].
 */
function admin_user_changes(array $user, array $input, array $admin): array
{
    $text = fn (string $key): string => is_scalar($input[$key] ?? null) ? trim((string) $input[$key]) : '';
    $isSelf = $user['id'] === $admin['id'];
    $changes = [];
    $errors = [];

    $name = (string) preg_replace('/\s+/u', ' ', $text('name'));
    if ($name !== $user['name']) {
        if ($name === '') {
            $errors['name'] = 'This is required.';
        } elseif (mb_strlen($name) > 150) {
            $errors['name'] = 'Use at most 150 characters.';
        } else {
            $changes['name'] = $name;
        }
    }

    $email = strtolower($text('email'));
    if ($email !== strtolower($user['email'])) {
        if ($email === '' || !valid_email($email) || mb_strlen($email) > 190) {
            $errors['email'] = 'Enter a valid email address.';
        } elseif (db_value('SELECT 1 FROM users WHERE email = ? AND id <> ?', [$email, $user['id']])) {
            $errors['email'] = 'Another account already uses this email.';
        } else {
            $changes['email'] = $email;
        }
    }

    $title = (string) preg_replace('/\s+/u', ' ', $text('title'));
    if ($title !== (string) $user['title']) {
        if (mb_strlen($title) > 190) {
            $errors['title'] = 'Use at most 190 characters.';
        } else {
            $changes['title'] = $title;
        }
    }

    $role = $text('role');
    if ($role !== $user['role']) {
        if (!array_key_exists($role, ROLE_LABELS)) {
            $errors['role'] = 'Choose a role from the list.';
        } elseif ($isSelf) {
            $errors['role'] = 'You cannot remove your own admin access.';
        } else {
            $changes['role'] = $role;
        }
    }

    $status = $text('status');
    if ($status !== $user['status']) {
        if (!isset(USER_STATUSES[$status])) {
            $errors['status'] = 'Choose a status from the list.';
        } elseif ($isSelf) {
            $errors['status'] = 'You cannot suspend your own account.';
        } else {
            $changes['status'] = $status;
        }
    }

    $verified = !empty($input['verified']) ? 1 : 0;
    if ($verified !== (int) $user['verified']) {
        $changes['verified'] = $verified;
    }

    $password = is_scalar($input['newPassword'] ?? null) ? (string) $input['newPassword'] : '';
    if ($password !== '') {
        if (mb_strlen($password) < 6) {
            $errors['newPassword'] = 'Use at least 6 characters.';
        } else {
            $changes['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        }
    }

    return [$changes, $errors];
}

/** Save changes unless the user changed after $version. Returns false in that case. */
function admin_save_user(string $userId, string $version, array $changes): bool
{
    $set = implode(', ', array_map(fn (string $column) => "`$column` = ?", array_keys($changes)));
    // updated_at is stamped explicitly so the new version is predictable.
    $stmt = db_run("UPDATE users SET $set, updated_at = NOW() WHERE id = ? AND updated_at = ?", [...array_values($changes), $userId, $version]);
    return $stmt->rowCount() === 1;
}

/**
 * Delete a user. Their card profile, account details, recovery passes and
 * backoffice accounts (wallet, transactions, codes, cart, orders,
 * notifications) are removed through the foreign keys. Payments, leads, chat
 * messages, audit entries and network-tree placements are kept as records.
 */
function admin_delete_user(string $userId): bool
{
    return db_run('DELETE FROM users WHERE id = ?', [$userId])->rowCount() === 1;
}
