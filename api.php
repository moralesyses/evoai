<?php
/**
 * JSON API used by the portal's JavaScript.
 *
 *   POST api.php?action=<name>
 *   Headers: Content-Type: application/json, X-CSRF-Token: <token>
 *
 * Responses: {"ok":true, ...data} or {"ok":false,"error":"message"}.
 */
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

final class ApiError extends RuntimeException
{
}

function api_fail(string $message, int $status = 400): never
{
    throw new ApiError($message, $status);
}

function api_user(): array
{
    $user = current_user();
    if (!$user) {
        api_fail('Please sign in to the Executive Portal to continue.', 401);
    }
    return $user;
}

function api_admin(): array
{
    $user = api_user();
    if (!is_admin($user)) {
        api_fail('Administrator access required.', 403);
    }
    return $user;
}

/** Backoffice account the request acts on (the session's active head). */
function api_account(array $user): array
{
    return mlm_active_account($user);
}

$action = (string) ($_GET['action'] ?? '');

try {
    if (request_method() !== 'POST') {
        api_fail('Method not allowed.', 405);
    }
    $raw = file_get_contents('php://input') ?: '';
    $in = [];
    if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
        $in = json_decode($raw, true);
        if (!is_array($in)) {
            $in = [];
        }
    } else {
        $in = $_POST;
    }
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($in['_csrf'] ?? null);
    if (!verify_csrf(is_string($token) ? $token : null)) {
        api_fail('Your session expired. Please refresh the page and try again.', 419);
    }

    // Extension actions: api/actions/*.php each return ['action.name' => fn (array $in): array].
    $extraActions = [];
    foreach (glob(__DIR__ . '/api/actions/*.php') ?: [] as $actionFile) {
        $defs = require $actionFile;
        if (is_array($defs)) {
            $extraActions += $defs;
        }
    }

    $result = isset($extraActions[$action]) ? $extraActions[$action]($in) : match ($action) {
        /* ---------------- Authentication ---------------- */
        'auth.login' => (function () use ($in) {
            $res = attempt_login((string) ($in['email'] ?? ''), (string) ($in['password'] ?? ''));
            if (!$res['ok']) {
                api_fail($res['error']);
            }
            login_user($res['user'], !empty($in['remember']));
            add_audit('Successful portal sign-in', $res['user']['id']);
            return ['user' => user_public($res['user']), 'csrf' => csrf_token()];
        })(),

        'auth.logout' => (function () {
            $u = current_user();
            if ($u) {
                add_audit('Signed out from portal', $u['id']);
            }
            logout_user();
            return [];
        })(),

        'auth.forgot' => (function () use ($in) {
            $email = str_in($in, 'email', 190);
            if (!valid_email($email)) {
                api_fail('Please enter your registered executive email.');
            }
            $pass = issue_recovery_pass($email);
            // Demo mode shows the one-time pass on screen (the reference showed a
            // demo quick pass). Without demo mode nothing is revealed.
            return ['sent' => true, 'demoPass' => demo_mode() ? $pass : null, 'known' => demo_mode() ? $pass !== null : null];
        })(),

        'auth.switch' => (function () use ($in) {
            $me = api_user();
            if (!demo_mode()) { // demo mode only, like the switcher in the header
                api_fail('Account switching is disabled.', 403);
            }
            $target = db_one('SELECT * FROM users WHERE id = ?', [str_in($in, 'userId', 40)]);
            if (!$target || $target['status'] !== 'active' || $target['role'] === 'visitor') {
                api_fail('That account cannot be used to sign in.');
            }
            add_audit('Switched active session to ' . $target['name'], $me['id']);
            login_user($target, !empty($_SESSION['remember']));
            return ['user' => user_public($target), 'csrf' => csrf_token()];
        })(),

        'auth.sponsor' => (function () use ($in) {
            $row = db_one('SELECT g.id, g.full_name, a.signup_placement FROM mlm_genealogy g LEFT JOIN mlm_accounts a ON a.id = g.id WHERE g.id = ?', [strtoupper(str_in($in, 'sponsorId', 20))]);
            // placement: the leg the sponsor chose for sign-ups (mlm_signup_placement).
            return [
                'found' => (bool) $row, 'name' => $row['full_name'] ?? null,
                'placement' => $row ? ($row['signup_placement'] ?? 'auto') : null,
                'placementText' => $row ? mlm_signup_placement_text($row['full_name'], $row['signup_placement'] ?? 'auto') : '',
            ];
        })(),

        'keys.publish' => (function () use ($in) {
            $u = api_user();
            set_user_public_key($u['id'], (string) ($in['publicKey'] ?? ''));
            return [];
        })(),

        /* ---------------- Public card / CRM ---------------- */
        'analytics.track' => (function () use ($in) {
            $profileId = str_in($in, 'profileId', 40) ?: null;
            if ($profileId && !db_value('SELECT 1 FROM member_profiles WHERE user_id = ?', [$profileId])) {
                $profileId = null;
            }
            $tracked = track_action(str_in($in, 'action', 30), $profileId, current_user()['id'] ?? null);
            return ['tracked' => $tracked, 'analytics' => get_analytics()];
        })(),

        'leads.create' => (function () use ($in) {
            if (!empty($in['website_url'])) { // honeypot
                return ['lead' => null];
            }
            return ['lead' => create_lead($in)];
        })(),

        /* ---------------- Member backoffice ---------------- */
        'mlm.setActive' => (function () use ($in) {
            $u = api_user();
            mlm_set_active_account($u, str_in($in, 'accountId', 20));
            return [];
        })(),

        'mlm.encash' => (function () use ($in) {
            $u = api_user();
            return mlm_submit_encashment(api_account($u), $in);
        })(),

        'mlm.buyCodes' => (function () use ($in) {
            $u = api_user();
            return mlm_buy_codes(api_account($u), int_in($in, 'quantity', 1));
        })(),

        'mlm.transferCode' => (function () use ($in) {
            $u = api_user();
            return mlm_transfer_code(api_account($u), str_in($in, 'code', 40), str_in($in, 'username', 60));
        })(),

        'mlm.registerDownline' => (function () use ($in) {
            $u = api_user();
            return mlm_register_downline(api_account($u), $in);
        })(),

        'mlm.cartAdd' => (function () use ($in) {
            $u = api_user();
            mlm_cart_set(api_account($u), str_in($in, 'productId', 40), max(1, int_in($in, 'quantity', 1)), true);
            return [];
        })(),

        'mlm.cartUpdate' => (function () use ($in) {
            $u = api_user();
            mlm_cart_set(api_account($u), str_in($in, 'productId', 40), int_in($in, 'quantity', 0));
            return [];
        })(),

        'mlm.cartRemove' => (function () use ($in) {
            $u = api_user();
            mlm_cart_set(api_account($u), str_in($in, 'productId', 40), 0);
            return [];
        })(),

        'mlm.cartClear' => (function () {
            $u = api_user();
            mlm_cart_clear(api_account($u));
            return [];
        })(),

        // Pay for the cart with the MLM balance (the order is placed now) or Maya (returns Maya's page).
        'mlm.checkout' => (function () use ($in) {
            $u = api_user();
            if (str_in($in, 'method', 20) === 'maya') {
                return ['redirectUrl' => mlm_checkout_maya(api_account($u), $u, str_in($in, 'address', 255))];
            }
            return ['order' => mlm_checkout_cart(api_account($u), str_in($in, 'method', 20), str_in($in, 'address', 255))];
        })(),

        'mlm.notifRead' => (function () use ($in) {
            $u = api_user();
            mlm_mark_notification_read(api_account($u), str_in($in, 'id', 40));
            return [];
        })(),

        'mlm.notifClear' => (function () {
            $u = api_user();
            mlm_clear_notifications(api_account($u));
            return [];
        })(),

        'account.saveDetails' => (function () use ($in) {
            save_account_details(api_user(), $in);
            return ['message' => 'Profile updated successfully!'];
        })(),

        'account.saveSecurity' => (function () use ($in) {
            save_security_settings(api_user(), $in);
            return ['message' => 'Security settings updated successfully!'];
        })(),

        'profile.update' => (function () use ($in) {
            $u = api_user();
            $patch = is_array($in['patch'] ?? null) ? $in['patch'] : [];
            // Members manage their own card; tier and card serial stay admin-controlled.
            unset($patch['tier'], $patch['nfcCardId'], $patch['renewalDate']);
            if (isset($patch['membershipStatus']) && !in_array($patch['membershipStatus'], ['Active', 'Frozen (Card Lost)'], true)) {
                unset($patch['membershipStatus']);
            }
            if (!empty($patch['customSlug'])) {
                $slug = basename(trim((string) $patch['customSlug'], '/ '));
                $taken = db_value('SELECT user_id FROM member_profiles WHERE (LOWER(custom_slug) = ? OR LOWER(custom_slug) LIKE ?) AND user_id <> ?', [strtolower((string) $patch['customSlug']), '%/' . strtolower($slug), $u['id']]);
                if ($taken) {
                    api_fail('That custom card URL is already taken. Please choose another.');
                }
            }
            $profile = update_member_profile($u['id'], $patch);
            add_audit('Digital card profile updated', $u['id']);
            return ['profile' => $profile, 'shareUrl' => card_share_url($profile)];
        })(),

        /* ---------------- Management admin ---------------- */
        'admin.setRole' => (function () use ($in) {
            $a = api_admin();
            update_user_role($a, str_in($in, 'userId', 40), str_in($in, 'role', 20));
            return ['users' => list_users()];
        })(),

        'admin.updateProfile' => (function () use ($in) {
            $a = api_admin();
            $userId = str_in($in, 'userId', 40);
            if (!db_value('SELECT 1 FROM users WHERE id = ?', [$userId])) {
                api_fail('Member not found.');
            }
            $profile = update_member_profile($userId, is_array($in['patch'] ?? null) ? $in['patch'] : []);
            add_audit('Updated member card profile for ' . $profile['name'], $a['id']);
            return ['profile' => $profile];
        })(),

        'admin.leadStatus' => (function () use ($in) {
            api_admin();
            update_lead_status(str_in($in, 'id', 40), str_in($in, 'status', 20));
            return [];
        })(),

        'admin.paymentStatus' => (function () use ($in) {
            $a = api_admin();
            $id = str_in($in, 'id', 40);
            $status = str_in($in, 'status', 20);
            update_payment_status($id, $status);
            add_audit("Payment {$id} marked as {$status}", $a['id']);
            return [];
        })(),

        'admin.invoice' => (function () use ($in) {
            $a = api_admin();
            return ['payment' => create_invoice($in, $a)];
        })(),

        'admin.cmsUpdate' => (function () use ($in) {
            $a = api_admin();
            $cms = update_cms(is_array($in['cms'] ?? null) ? $in['cms'] : []);
            add_audit('Portal CMS content & pricing updated', $a['id']);
            return ['cms' => $cms];
        })(),

        'admin.cmsReset' => (function () {
            $a = api_admin();
            $cms = reset_cms();
            add_audit('Portal CMS reset to factory defaults', $a['id']);
            return ['cms' => $cms];
        })(),

        /* ---------------- E2EE chat ---------------- */
        'chat.poll' => (function () use ($in) {
            return chat_poll(api_user(), str_in($in, 'roomId', 120), max(0, int_in($in, 'since')));
        })(),

        'chat.send' => (function () use ($in) {
            return ['message' => chat_post(api_user(), $in)];
        })(),

        'chat.react' => (function () use ($in) {
            chat_toggle_reaction(api_user(), str_in($in, 'messageId', 64), (string) ($in['emoji'] ?? ''));
            return [];
        })(),

        'chat.typing' => (function () use ($in) {
            chat_set_typing(api_user(), str_in($in, 'roomId', 120), !empty($in['typing']));
            return [];
        })(),

        default => api_fail('Unknown action.', 404),
    };

    echo json_encode(['ok' => true] + (is_array($result) ? $result : []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (ApiError $e) {
    // Validation failures are normal UI outcomes: answer 200 + ok:false so browsers
    // don't log them as network errors. Auth/permission/method errors keep their status.
    $status = $e->getCode() ?: 400;
    header('X-Api-Status: ' . $status);
    http_response_code($status === 400 ? 200 : $status);
    echo json_encode(['ok' => false, 'error' => $e->getMessage(), 'status' => $status], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $e) {
    header('X-Api-Status: 400');
    http_response_code(200);
    echo json_encode(['ok' => false, 'error' => $e->getMessage(), 'status' => 400], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('API error [' . $action . ']: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    $msg = config('app.debug') ? $e->getMessage() : 'Something went wrong. Please try again.';
    echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
}
