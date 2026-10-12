<?php
/**
 * Superadmin user management (Management Admin > Users).
 * Search by name, email or ID; sort by date joined; 25 per page; edit
 * account fields (only changed fields are written, with optimistic locking on
 * updated_at); delete with confirmation.
 */
declare(strict_types=1);

const USERS_PER_PAGE = 25;
const USER_SORTS = [
    'newest' => ['Date joined: newest to oldest', 'u.created_at DESC, u.id DESC'],
    'oldest' => ['Date joined: oldest to newest', 'u.created_at ASC, u.id ASC'],
    'name'   => ['Name: A to Z', 'u.name ASC, u.id ASC'],
];
const USER_STATUSES = ['active' => 'Active', 'suspended' => 'Suspended'];

/** One page of users (with whether they have a card profile) and the total that match. */
function admin_search_users(string $search, int $page, string $sort = 'newest'): array
{
    $where = '';
    $params = [];
    if ($search !== '') {
        $like = '%' . addcslashes($search, '%_\\') . '%';
        $where = 'WHERE (u.id = ? OR u.name LIKE ? OR u.email LIKE ?)';
        $params = [$search, $like, $like];
    }
    $total = (int) db_value("SELECT COUNT(*) FROM users u $where", $params);
    $offset = (max(1, $page) - 1) * USERS_PER_PAGE;
    $order = (USER_SORTS[$sort] ?? USER_SORTS['newest'])[1];
    $rows = db_all(
        "SELECT u.id, u.name, u.email, u.role, u.title, u.status, u.verified, u.joined_date, u.last_login_at, u.created_at,
                (p.user_id IS NOT NULL) AS has_card
         FROM users u LEFT JOIN member_profiles p ON p.user_id = u.id
         $where ORDER BY $order LIMIT " . USERS_PER_PAGE . " OFFSET $offset",
        $params
    );
    return [$rows, $total];
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
