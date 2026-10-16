<?php
/**
 * Session-based authentication and role handling.
 *
 * Roles: admin (Super Admin / CEO), vip (Chairman's Circle), member
 * (Executive Member) and visitor (no portal access).
 */
declare(strict_types=1);

const ROLE_LABELS = [
    'admin'   => 'Super Admin / CEO',
    'vip'     => "Chairman's Circle (VIP)",
    'member'  => 'Executive Member',
    'visitor' => 'Visitor',
];

function start_app_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $rememberDays = (int) config('session.remember_days', 30);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.gc_maxlifetime', (string) max(1440, $rememberDays * 86400));
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_name((string) config('session.name', 'EVOAI_SESSION'));
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => base_path() . '/',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    // Sessions without "Keep executive session active" expire after 12h idle.
    if (!empty($_SESSION['uid']) && empty($_SESSION['remember'])) {
        $last = (int) ($_SESSION['last_activity'] ?? time());
        if (time() - $last > 12 * 3600) {
            logout_user();
            session_start();
        }
    }
    $_SESSION['last_activity'] = time();
}

function demo_mode(): bool
{
    return (bool) config('app.demo_mode', false);
}

/** The signed-in user row (fresh from the DB), or null for guests. */
function current_user(): ?array
{
    static $cache = [];
    $uid = $_SESSION['uid'] ?? null;
    if (!$uid) {
        return null;
    }
    if (!array_key_exists($uid, $cache)) {
        $row = db_one('SELECT * FROM users WHERE id = ?', [$uid]);
        // A password change (by the user or a superadmin) ends sessions opened before it.
        if ($row && isset($_SESSION['pw']) && !hash_equals((string) $_SESSION['pw'], password_stamp_of($row['password_hash']))) {
            $row = null;
        }
        $cache[$uid] = $row;
    }
    $user = $cache[$uid];
    if (!$user || $user['status'] !== 'active' || $user['role'] === 'visitor') {
        return null;
    }
    return $user;
}

/** Short fingerprint of a stored password hash, kept in the session. */
function password_stamp_of(string $hash): string
{
    return substr(hash('sha256', $hash), 0, 16);
}

/** Keep this session signed in after its own user's password changed. */
function refresh_password_stamp(string $userId): void
{
    if (($_SESSION['uid'] ?? null) === $userId) {
        $_SESSION['pw'] = password_stamp_of((string) db_value('SELECT password_hash FROM users WHERE id = ?', [$userId]));
    }
}

function is_admin(?array $user = null): bool
{
    $user = $user ?? current_user();
    return $user !== null && $user['role'] === 'admin';
}

/** Guest identity used when nobody is signed in (mirrors the reference). */
function guest_user(): array
{
    return [
        'id'          => 'usr_guest',
        'name'        => 'Executive Visitor',
        'email'       => 'visitor@mmmcircle.com',
        'role'        => 'visitor',
        'title'       => 'Prospective Member',
        'status'      => 'active',
        'verified'    => 0,
        'joined_date' => date('M Y'),
        'public_key'  => null,
    ];
}

/** Shape a user row for the browser (never includes the password hash). */
function user_public(array $u): array
{
    return [
        'id'         => $u['id'],
        'name'       => $u['name'],
        'email'      => $u['email'],
        'role'       => $u['role'],
        'title'      => $u['title'],
        'status'     => $u['status'],
        'verified'   => (bool) $u['verified'],
        'joinedDate' => $u['joined_date'],
        'publicKey'  => $u['public_key'] ?? null,
    ];
}

function login_user(array $user, bool $remember): void
{
    session_regenerate_id(true);
    $_SESSION['uid'] = $user['id'];
    $_SESSION['remember'] = $remember;
    $_SESSION['last_activity'] = time();
    // Read fresh: signing in with a recovery pass or a rehash may have just changed it.
    refresh_password_stamp($user['id']);
    unset($_SESSION['mlm_account']);
    if ($remember) {
        $days = (int) config('session.remember_days', 30);
        $params = session_get_cookie_params();
        setcookie(session_name(), session_id(), [
            'expires'  => time() + $days * 86400,
            'path'     => $params['path'],
            'secure'   => $params['secure'],
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
    db_run('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$user['id']]);
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', ['expires' => time() - 42000, 'path' => $p['path'], 'secure' => $p['secure'], 'httponly' => true, 'samesite' => 'Lax']);
    }
    session_destroy();
}

/** Simple per-session brute-force throttle for sign-in attempts. */
function login_throttled(): bool
{
    $window = 15 * 60;
    $attempts = array_filter($_SESSION['login_failures'] ?? [], fn ($t) => $t > time() - $window);
    $_SESSION['login_failures'] = array_values($attempts);
    return count($attempts) >= 8;
}

function record_login_failure(): void
{
    $_SESSION['login_failures'][] = time();
}

/**
 * Validate credentials. Accepts the account password or an unexpired
 * one-time recovery pass (which then becomes the account password).
 *
 * @return array{ok:bool,error?:string,user?:array}
 */
function attempt_login(string $email, string $password): array
{
    $email = strtolower(trim($email));
    $password = trim($password);
    if ($email === '') {
        return ['ok' => false, 'error' => 'Please enter your executive email address.'];
    }
    if ($password === '') {
        return ['ok' => false, 'error' => 'Please enter your account password.'];
    }
    if (login_throttled()) {
        return ['ok' => false, 'error' => 'Too many failed sign-in attempts. Please wait 15 minutes and try again.'];
    }
    $user = db_one('SELECT * FROM users WHERE email = ?', [$email]);
    if (!$user) {
        record_login_failure();
        return ['ok' => false, 'error' => 'No executive account found for this email. Please create an account.'];
    }
    $valid = password_verify($password, $user['password_hash']);
    if (!$valid) {
        $resets = db_all('SELECT id, token_hash FROM password_resets WHERE user_id = ? AND used_at IS NULL AND expires_at > NOW() ORDER BY id DESC LIMIT 5', [$user['id']]);
        foreach ($resets as $r) {
            if (password_verify($password, $r['token_hash'])) {
                db_run('UPDATE password_resets SET used_at = NOW() WHERE id = ?', [$r['id']]);
                db_run('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $user['id']]);
                $valid = true;
                break;
            }
        }
    }
    if (!$valid) {
        record_login_failure();
        $hint = demo_mode() ? ' (Hint: use the Quick Demo Credentials below.)' : '';
        return ['ok' => false, 'error' => 'Invalid password. Please verify your security password.' . $hint];
    }
    if ($user['status'] !== 'active' || $user['role'] === 'visitor') {
        return ['ok' => false, 'error' => 'This account does not currently have portal access. Please contact the 88MMM administrator.'];
    }
    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        db_run('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $user['id']]);
    }
    $_SESSION['login_failures'] = [];
    return ['ok' => true, 'user' => $user];
}

/**
 * Validate a sign-up from the Create Account form. The account itself is
 * created only after the membership is paid (includes/signup.php).
 * Returns ['name', 'email', 'passwordHash', 'sponsorId', 'package'];
 * throws InvalidArgumentException with a message for the form.
 */
function registration_request(array $in): array
{
    $name = str_in($in, 'name', 150);
    $email = strtolower(str_in($in, 'email', 190));
    $password = trim((string) ($in['password'] ?? ''));
    $confirm = trim((string) ($in['confirm'] ?? ''));
    $sponsorId = strtoupper(str_in($in, 'sponsorId', 20));
    $package = str_in($in, 'package', 40) === 'membership_chairmans' ? 'membership_chairmans' : 'membership_executive';

    if ($name === '') {
        throw new InvalidArgumentException('Please provide your full legal or executive name.');
    }
    if ($email === '' || !valid_email($email)) {
        throw new InvalidArgumentException('Please enter a valid corporate or personal email.');
    }
    if (mb_strlen($password) < 6) {
        throw new InvalidArgumentException('Password must be at least 6 characters for executive security.');
    }
    if ($password !== $confirm) {
        throw new InvalidArgumentException('Passwords do not match. Please re-enter.');
    }
    if (empty($in['accepted'])) {
        throw new InvalidArgumentException('Please accept the Executive Code of Conduct & NDA.');
    }
    if (db_value('SELECT 1 FROM users WHERE email = ?', [$email])) {
        throw new InvalidArgumentException('An account with this email already exists. Please sign in.');
    }
    // Left empty: the owner of the card this browser came from (signup_remembered_referrer), else MMM-88001.
    $sponsorId = $sponsorId === '' ? (signup_remembered_referrer() ?: 'MMM-88001') : $sponsorId;
    if (!db_value('SELECT 1 FROM mlm_genealogy WHERE id = ?', [$sponsorId])) {
        throw new InvalidArgumentException('Referrer ID not found. Please check the ID or leave the default MMM-88001.');
    }
    return [
        'name' => $name,
        'email' => $email,
        'passwordHash' => password_hash($password, PASSWORD_DEFAULT),
        'sponsorId' => $sponsorId,
        'package' => $package,
    ];
}

/**
 * Create an executive account (+ public card profile + backoffice head) for a
 * paid sign-up. Call inside db_tx(). Returns the new user id.
 */
function create_registered_account(array $reg): string
{
    $sponsor = db_one('SELECT id, full_name FROM mlm_genealogy WHERE id = ?', [$reg['sponsorId']])
        ?? db_one('SELECT id, full_name FROM mlm_genealogy WHERE id = "MMM-88001"');
    $vip = $reg['package'] === 'membership_chairmans';
    $role = $vip ? 'vip' : 'member';
    $title = $vip ? "Chairman's Circle Member" : 'Executive Member';
    do {
        $id = 'usr_' . (int) floor(microtime(true) * 1000) . rand_digits(2);
    } while (db_value('SELECT 1 FROM users WHERE id = ?', [$id]));

    db_run(
        'INSERT INTO users (id, name, email, password_hash, role, title, status, verified, joined_date) VALUES (?, ?, ?, ?, ?, ?, "active", 1, ?)',
        [$id, $reg['name'], $reg['email'], $reg['passwordHash'], $role, $title, date('M Y')]
    );
    create_member_profile($id, [
        'name'      => $reg['name'],
        'title'     => $title,
        'company'   => '88MMM Circle',
        'email'     => $reg['email'],
        'tier'      => $vip ? "Chairman's Circle" : 'Executive Member',
        'nfcCardId' => ($vip ? 'MMC-VIP-' : 'MMC-EXE-') . rand_digits(5),
        // No customSlug: the card's link name comes from the full name (juan-dela-cruz).
    ]);
    mlm_provision_account($id, $reg['name'], $reg['email'], $sponsor['id'], $sponsor['full_name']);
    add_audit('Executive account registered (' . ROLE_LABELS[$role] . ')', $id);
    return $id;
}

/**
 * Issue a one-time recovery pass (valid 30 minutes). Returns the plain pass
 * so demo mode can display it; production deployments should e-mail it.
 */
function issue_recovery_pass(string $email): ?string
{
    $user = db_one('SELECT id FROM users WHERE email = ?', [strtolower(trim($email))]);
    if (!$user) {
        return null;
    }
    $pass = 'EVO-' . strtoupper(bin2hex(random_bytes(3))) . '-' . rand_digits(4);
    db_run(
        'INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))',
        [$user['id'], password_hash($pass, PASSWORD_DEFAULT)]
    );
    add_audit('One-time password recovery pass issued', $user['id']);
    return $pass;
}
