<?php
/**
 * Pay-first sign-up: Create Account takes the membership payment through
 * Maya Checkout, and the account exists only once Maya confirms it.
 *
 * 1. auth.registerStart validates the form (registration_request), stores it
 *    in registrations (password as a hash only) and opens Maya for the package.
 * 2. When maya_sync() records the payment it calls signup_complete() in the
 *    same transaction: user, card profile and backoffice head are created.
 * 3. The browser that started the sign-up is signed in on the return page.
 */
declare(strict_types=1);

/** Unpaid sign-ups are cleared after this many hours (Maya checkouts expire after one). */
const SIGNUP_KEEP_HOURS = 24;

/**
 * Someone who opens a member's 1EVOAI card signs up under that member: the card remembers its owner's
 * main account in this browser (views/card.php), and Create Account uses it when the sign-up link has
 * no ?ref= of its own (views/main.php, registration_request()). The last card opened counts.
 */
const SIGNUP_REF_COOKIE = 'evo_ref';
const SIGNUP_REF_DAYS = 30;

/** Remember $accountId (a card owner's main account, MMM-…) as this browser's referrer. */
function signup_remember_referrer(string $accountId): void
{
    if (!preg_match('/^MMM-\d{5,7}$/', $accountId) || headers_sent() || ($_COOKIE[SIGNUP_REF_COOKIE] ?? '') === $accountId) {
        return;
    }
    setcookie(SIGNUP_REF_COOKIE, $accountId, [
        'expires' => time() + SIGNUP_REF_DAYS * 86400,
        'path' => base_path() . '/',
        'secure' => session_get_cookie_params()['secure'],
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    $_COOKIE[SIGNUP_REF_COOKIE] = $accountId;
}

/** The referrer remembered from a card, while that account is still in the team tree; '' otherwise. */
function signup_remembered_referrer(): string
{
    $ref = is_string($_COOKIE[SIGNUP_REF_COOKIE] ?? null) ? strtoupper(trim($_COOKIE[SIGNUP_REF_COOKIE])) : '';
    return preg_match('/^MMM-\d{5,7}$/', $ref) && db_value('SELECT 1 FROM mlm_genealogy WHERE id = ?', [$ref]) ? $ref : '';
}

/** Validate the form, store the sign-up and return Maya's payment page. */
function signup_start(array $in): string
{
    if (!maya_enabled()) {
        throw new InvalidArgumentException(MAYA_UNAVAILABLE . '. Please sign up with an activation code and its PIN, or contact the 88MMM administrator.');
    }
    $reg = registration_request($in);
    $item = checkout_item($reg['package']);

    // Forget unpaid sign-ups' password hashes once they can no longer be paid.
    db_run('UPDATE registrations SET status = "expired", password_hash = "" WHERE status = "pending" AND created_at < NOW() - INTERVAL ' . SIGNUP_KEEP_HOURS . ' HOUR');

    $checkout = maya_start_checkout([
        'itemType' => $reg['package'],
        'item' => $item,
        'invoice' => null,
        'name' => $reg['name'],
        'email' => $reg['email'],
        'phone' => '',
    ], null);
    db_run('INSERT INTO registrations (rrn, name, email, password_hash, sponsor_id, package) VALUES (?, ?, ?, ?, ?, ?)',
        [$checkout['rrn'], $reg['name'], $reg['email'], $reg['passwordHash'], $reg['sponsorId'], $reg['package']]);
    // This browser may sign in to the new account on the return page.
    $_SESSION['signup_rrn'] = $checkout['rrn'];
    return $checkout['redirectUrl'];
}

/**
 * Create the account for a paid sign-up. Called by maya_sync() inside the
 * transaction that records the payment. Returns the new user id, or null when
 * the checkout isn't a pending sign-up (or its email was taken meanwhile).
 */
function signup_complete(string $rrn, string $paymentId): ?string
{
    $reg = db_one('SELECT * FROM registrations WHERE rrn = ? FOR UPDATE', [$rrn]);
    if (!$reg || $reg['status'] !== 'pending' || $reg['password_hash'] === '') {
        if ($reg && $reg['status'] === 'expired') {
            add_audit('Payment ' . $paymentId . ' received for an expired sign-up (' . $reg['email'] . '); no account was created.', null);
        }
        return null;
    }
    if (db_value('SELECT 1 FROM users WHERE email = ?', [$reg['email']])) {
        // Paid twice, or someone registered the address in the meantime: keep the money on record.
        db_run('UPDATE registrations SET status = "email_taken", password_hash = "" WHERE rrn = ?', [$rrn]);
        add_audit('Payment ' . $paymentId . ' received for a sign-up, but ' . $reg['email'] . ' already has an account. Check for a refund.', null);
        return null;
    }
    $userId = create_registered_account([
        'name' => $reg['name'],
        'email' => $reg['email'],
        'passwordHash' => $reg['password_hash'],
        'sponsorId' => $reg['sponsor_id'],
        'package' => $reg['package'],
    ]);
    // The users table holds the hash now; don't keep a second copy.
    db_run('UPDATE registrations SET status = "completed", user_id = ?, password_hash = "" WHERE rrn = ?', [$userId, $rrn]);
    db_run('UPDATE payments SET user_id = ? WHERE id = ?', [$userId, $paymentId]);
    db_run('UPDATE maya_checkouts SET user_id = ? WHERE rrn = ?', [$userId, $rrn]);
    return $userId;
}

/**
 * On the return page: sign in the browser that started this (completed)
 * sign-up. Returns true when it signed someone in.
 */
function signup_sign_in(array $reg): bool
{
    if ($reg['status'] !== 'completed' || ($_SESSION['signup_rrn'] ?? null) !== $reg['rrn'] || !$reg['user_id']) {
        return false;
    }
    $user = db_one('SELECT * FROM users WHERE id = ?', [$reg['user_id']]);
    unset($_SESSION['signup_rrn']);
    if (!$user || $user['status'] !== 'active') {
        return false;
    }
    login_user($user, true);
    add_audit('Signed in after paying for a new account', $user['id']);
    return true;
}
