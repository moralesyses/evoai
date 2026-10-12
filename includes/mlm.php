<?php
/**
 * 88MMM binary compensation plan: accounts (heads), genealogy, wallet,
 * encashments, activation codes, member shop, notifications and logs.
 */
declare(strict_types=1);

/** The standard binary plan; Management Admin > Binary Complan can change the bonuses and pair limits. */
const MLM_COMPLAN_DEFAULTS = [
    'entryFee'            => 2000,
    'directReferralBonus' => 200,
    'pairingBonus'        => 300,
    'maxPairsPerCycle'    => 8,
    'cyclesPerDay'        => 2,
];
/** cms_settings key that holds the edited plan (kept by "Reset CMS"). */
const MLM_COMPLAN_KEY = 'mlm_complan';

/**
 * The binary compensation plan in force: the saved values over the defaults,
 * plus pairsPerDay, maxCyclePairingIncome and maxDailyPairingIncome.
 */
function mlm_complan(bool $fresh = false): array
{
    static $plan = null;
    if ($plan === null || $fresh) {
        $plan = MLM_COMPLAN_DEFAULTS;
        $saved = json_decode((string) db_value('SELECT setting_value FROM cms_settings WHERE setting_key = ?', [MLM_COMPLAN_KEY]), true);
        foreach (['directReferralBonus', 'pairingBonus', 'maxPairsPerCycle', 'cyclesPerDay'] as $key) {
            if (is_array($saved) && isset($saved[$key]) && is_numeric($saved[$key])) {
                $plan[$key] = (int) $saved[$key];
            }
        }
        $plan['pairsPerDay'] = $plan['maxPairsPerCycle'] * $plan['cyclesPerDay'];
        $plan['maxCyclePairingIncome'] = $plan['maxPairsPerCycle'] * $plan['pairingBonus'];
        $plan['maxDailyPairingIncome'] = $plan['pairsPerDay'] * $plan['pairingBonus'];
    }
    return $plan;
}

/** Save the plan from Management Admin. Returns [plan, errors (field => message)]. */
function mlm_save_complan(array $admin, array $in): array
{
    $rules = [
        'directReferralBonus' => [0, 1000000, 'Direct referral bonus'],
        'pairingBonus'        => [0, 1000000, 'Pairing bonus'],
        'maxPairsPerCycle'    => [1, 1000, 'Pairs per cycle'],
        'cyclesPerDay'        => [1, 2, 'Cycles per day'],
    ];
    $values = [];
    $errors = [];
    foreach ($rules as $key => [$min, $max, $label]) {
        $raw = is_scalar($in[$key] ?? null) ? trim((string) $in[$key]) : '';
        if ($raw === '' || !preg_match('/^\d+$/', $raw) || (int) $raw < $min || (int) $raw > $max) {
            $errors[$key] = $key === 'cyclesPerDay' ? 'Choose 1 or 2 cycles per day.' : $label . ' must be a whole number from ' . number_format($min) . ' to ' . number_format($max) . '.';
            continue;
        }
        $values[$key] = (int) $raw;
    }
    if ($errors) {
        return [mlm_complan(), $errors];
    }
    db_run('INSERT INTO cms_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
        [MLM_COMPLAN_KEY, json_encode($values)]);
    $plan = mlm_complan(true);
    add_audit(sprintf('Binary compensation plan updated: ₱%s direct referral, ₱%s pairing, %d pairs × %d cycle%s = %d pairs/day (max ₱%s/day)',
        num($plan['directReferralBonus']), num($plan['pairingBonus']), $plan['maxPairsPerCycle'], $plan['cyclesPerDay'], $plan['cyclesPerDay'] === 1 ? '' : 's',
        $plan['pairsPerDay'], num($plan['maxDailyPairingIncome'])), $admin['id']);
    return [$plan, []];
}

/** "Cycle 1 (12:00 AM – 11:59 AM)" for the plan's cycles per day. */
function mlm_cycle_label(int $cycle): string
{
    if (mlm_complan()['cyclesPerDay'] === 1) {
        return 'Daily Cycle (12:00 AM – 11:59 PM)';
    }
    return $cycle === 1 ? 'Cycle 1 (12:00 AM – 11:59 AM)' : 'Cycle 2 (12:00 PM – 11:59 PM)';
}
const MLM_PAYOUT_METHODS = ['gcash', 'maya', 'bank_transfer', 'cheque'];
/** Member Shop: the member's available MLM balance, or Maya Checkout (the only outside payment). */
const MLM_SHOP_METHODS = ['balance' => 'Available MLM Balance', 'maya' => 'Maya'];
const MLM_TAX_RATE = 0.10;
const MLM_PROCESSING_FEE = 50;
const MLM_MIN_ENCASHMENT = 500;
// Where a new downline goes under a member: a leg, or the leg with fewer downlines (mlm_leg_placement).
const MLM_PLACEMENTS = ['auto' => 'Auto: leg with fewer downlines', 'left' => 'Left leg', 'right' => 'Right leg'];

/* ---------------------------------------------------------------------
 * Row mappers
 * ------------------------------------------------------------------- */
function mlm_account_to_array(array $r): array
{
    return [
        'id' => $r['id'], 'userId' => $r['user_id'], 'username' => $r['username'], 'accountName' => $r['account_name'],
        'headNumber' => (int) $r['head_number'], 'sponsorId' => $r['sponsor_id'], 'sponsorName' => $r['sponsor_name'],
        'placementId' => $r['placement_id'], 'legPosition' => $r['leg_position'],
        'signupPlacement' => $r['signup_placement'] ?? 'auto', 'packageType' => $r['package_type'],
        'entryDate' => $r['entry_date'], 'status' => $r['status'],
        'availableBalance' => (float) $r['available_balance'], 'totalIncome' => (float) $r['total_income'],
        'totalDirectBonus' => (float) $r['total_direct_bonus'], 'totalPairingBonus' => (float) $r['total_pairing_bonus'],
        'totalEncashment' => (float) $r['total_encashment'],
        'leftPoints' => (int) $r['left_points'], 'rightPoints' => (int) $r['right_points'],
        'leftCount' => (int) $r['left_count'], 'rightCount' => (int) $r['right_count'],
        'sponsoredCount' => (int) $r['sponsored_count'],
        'cycle1PairsToday' => (int) $r['cycle1_pairs_today'], 'cycle2PairsToday' => (int) $r['cycle2_pairs_today'],
        'flushCountToday' => (int) $r['flush_count_today'],
    ];
}

function mlm_tx_to_array(array $r): array
{
    return [
        'id' => $r['id'], 'accountId' => $r['account_id'], 'type' => $r['type'], 'title' => $r['title'],
        'amount' => (float) $r['amount'], 'direction' => $r['direction'], 'status' => $r['status'],
        'referenceNumber' => $r['reference_number'], 'timestamp' => to_ms($r['created_at']),
        'details' => (string) $r['details'], 'sourceMember' => $r['source_member'],
    ];
}

function mlm_encashment_to_array(array $r): array
{
    return [
        'id' => $r['id'], 'accountId' => $r['account_id'], 'accountName' => $r['account_name'],
        'amount' => (float) $r['amount'], 'grossAmount' => (float) $r['gross_amount'], 'taxDeduction' => (float) $r['tax_deduction'],
        'processingFee' => (float) $r['processing_fee'], 'netAmount' => (float) $r['net_amount'],
        'payoutMethod' => $r['payout_method'], 'accountDetails' => $r['account_details'], 'status' => $r['status'],
        'requestedAt' => to_ms($r['requested_at']), 'processedAt' => $r['processed_at'] ? to_ms($r['processed_at']) : null,
        'referenceNumber' => $r['reference_number'],
    ];
}

function mlm_code_to_array(array $r): array
{
    return [
        'code' => $r['code'], 'serial' => code_serial((int) $r['serial']), 'pin' => $r['pin'], 'package' => $r['package'], 'packageType' => $r['package_type'], 'value' => (float) $r['value'],
        'status' => $r['status'], 'usedBy' => $r['used_by'], 'usedAt' => $r['used_at'] ? to_ms($r['used_at']) : null,
        'generatedDate' => $r['generated_date'],
    ];
}

function mlm_product_to_array(array $r): array
{
    return [
        'id' => $r['id'], 'name' => $r['name'], 'category' => $r['category'], 'price' => (float) $r['price'],
        'memberPrice' => (float) $r['member_price'], 'points' => (int) $r['points'], 'image' => media_url($r['image']),
        'description' => (string) $r['description'], 'stock' => (int) $r['stock'],
    ];
}

/* ---------------------------------------------------------------------
 * Accounts / heads
 * ------------------------------------------------------------------- */
/** Reset the per-cycle pair counters when a new day starts. */
function mlm_roll_cycles(): void
{
    db_run('UPDATE mlm_accounts SET cycle1_pairs_today = 0, cycle2_pairs_today = 0, flush_count_today = 0, cycle_date = CURDATE() WHERE cycle_date IS NULL OR cycle_date < CURDATE()');
}

/** Accounts visible to the user: admins manage every head, members their own. */
function mlm_accounts_for_user(array $user): array
{
    mlm_roll_cycles();
    $rows = is_admin($user)
        ? db_all('SELECT * FROM mlm_accounts ORDER BY (user_id = ?) DESC, created_at, id', [$user['id']])
        : db_all('SELECT * FROM mlm_accounts WHERE user_id = ? ORDER BY head_number, id', [$user['id']]);
    if (!$rows && !is_admin($user)) {
        $profile = get_member_profile($user['id']);
        mlm_provision_account($user['id'], $user['name'], $user['email'], 'MMM-88001', 'Mike Maranan', false);
        unset($profile);
        $rows = db_all('SELECT * FROM mlm_accounts WHERE user_id = ? ORDER BY head_number, id', [$user['id']]);
    }
    return array_map('mlm_account_to_array', $rows);
}

/** One member's own heads, for the superadmin's read-only view (never creates one). */
function mlm_member_accounts(string $userId): array
{
    mlm_roll_cycles();
    return array_map('mlm_account_to_array', db_all('SELECT * FROM mlm_accounts WHERE user_id = ? ORDER BY head_number, id', [$userId]));
}

function mlm_can_access_account(array $user, string $accountId): bool
{
    if (is_admin($user)) {
        return (bool) db_value('SELECT 1 FROM mlm_accounts WHERE id = ?', [$accountId]);
    }
    return (bool) db_value('SELECT 1 FROM mlm_accounts WHERE id = ? AND user_id = ?', [$accountId, $user['id']]);
}

/** Currently selected head for the user (stored in the session). */
function mlm_active_account(array $user): array
{
    $accounts = mlm_accounts_for_user($user);
    $selected = $_SESSION['mlm_account'] ?? null;
    foreach ($accounts as $a) {
        if ($a['id'] === $selected) {
            return $a;
        }
    }
    // Default: the user's own main head, otherwise the first visible one.
    foreach ($accounts as $a) {
        if ($a['userId'] === $user['id']) {
            return $a;
        }
    }
    return $accounts[0];
}

function mlm_set_active_account(array $user, string $accountId): void
{
    if (!mlm_can_access_account($user, $accountId)) {
        throw new InvalidArgumentException('You do not have access to that account.');
    }
    $_SESSION['mlm_account'] = $accountId;
}

function mlm_get_account(string $id): array
{
    $row = db_one('SELECT * FROM mlm_accounts WHERE id = ?', [$id]);
    if (!$row) {
        throw new InvalidArgumentException('Account not found.');
    }
    return mlm_account_to_array($row);
}

/**
 * The next MMM- id. Locking reads: inside a transaction, a second caller waits
 * for the first to commit, so two sign-ups at once never get the same id.
 */
function mlm_next_id(): string
{
    $max = (int) db_value("SELECT MAX(CAST(SUBSTRING(id, 5) AS UNSIGNED)) FROM mlm_genealogy WHERE id LIKE 'MMM-%' FOR UPDATE");
    $max2 = (int) db_value("SELECT MAX(CAST(SUBSTRING(id, 5) AS UNSIGNED)) FROM mlm_accounts WHERE id LIKE 'MMM-%' FOR UPDATE");
    return 'MMM-' . (max($max, $max2, 88100) + 1);
}

function mlm_unique_username(string $base): string
{
    $base = preg_replace('/[^a-z0-9_]/', '', strtolower($base)) ?: 'member';
    $name = $base;
    $i = 1;
    while (db_value('SELECT 1 FROM mlm_genealogy WHERE username = ? UNION SELECT 1 FROM mlm_accounts WHERE username = ?', [$name, $name])) {
        $name = $base . (++$i);
    }
    return $name;
}

/**
 * A new member's main head: placed in the sponsor's binary tree on the leg
 * the sponsor chose for sign-ups (mlm_signup_placement), with the sponsor
 * paid like a Register Downline. $pay = false places it without commissions.
 * Call inside db_tx().
 */
function mlm_provision_account(string $userId, string $name, string $email, string $sponsorId, string $sponsorName, bool $pay = true): string
{
    $id = mlm_next_id();
    $username = mlm_unique_username(explode('@', $email)[0]);
    [$placementId, $position] = mlm_signup_placement($sponsorId);
    mlm_join_binary($sponsorId, $id, $username, $name, $placementId, $position, false, $pay);
    db_run('INSERT INTO mlm_accounts (id, user_id, username, account_name, head_number, sponsor_id, sponsor_name, placement_id, leg_position, entry_date, cycle_date) VALUES (?, ?, ?, ?, 1, ?, ?, ?, ?, CURDATE(), CURDATE())',
        [$id, $userId, $username, $name . ' (Main Head)', $sponsorId, $sponsorName . ' (Sponsor)', $placementId, $position]);
    return $id;
}

/** Increment directs (first parent only) and team counts up the sponsor chain. */
function mlm_bump_unilevel_upline(string $parentId, bool $direct): void
{
    if ($direct) {
        db_run('UPDATE mlm_unilevel SET directs_count = directs_count + 1 WHERE id = ?', [$parentId]);
    }
    $guard = 0;
    $id = $parentId;
    while ($id && $guard++ < 50) {
        db_run('UPDATE mlm_unilevel SET total_team_count = total_team_count + 1 WHERE id = ?', [$id]);
        $id = db_value('SELECT parent_id FROM mlm_unilevel WHERE id = ?', [$id]);
    }
}

/* ---------------------------------------------------------------------
 * Trees
 * ------------------------------------------------------------------- */
function mlm_genealogy_tree(string $rootId, int $maxDepth = 6): ?array
{
    $rows = db_all('SELECT * FROM mlm_genealogy');
    $byId = [];
    $children = [];
    foreach ($rows as $r) {
        $byId[$r['id']] = $r;
        if ($r['placement_id'] && $r['position']) {
            $children[$r['placement_id']][$r['position']] = $r['id'];
        }
    }
    $build = function (?string $id, int $depth) use (&$build, $byId, $children, $maxDepth) {
        if (!$id || !isset($byId[$id]) || $depth > $maxDepth) {
            return null;
        }
        $r = $byId[$id];
        return [
            'id' => $r['id'], 'username' => $r['username'], 'fullName' => $r['full_name'], 'package' => $r['package'],
            'sponsorId' => $r['sponsor_id'], 'placementId' => $r['placement_id'], 'position' => $r['position'],
            'entryDate' => $r['entry_date'], 'status' => $r['status'],
            'leftPoints' => (int) $r['left_points'], 'rightPoints' => (int) $r['right_points'],
            'leftCount' => (int) $r['left_count'], 'rightCount' => (int) $r['right_count'], 'level' => (int) $r['level'],
            'leftChild' => $build($children[$id]['left'] ?? null, $depth + 1),
            'rightChild' => $build($children[$id]['right'] ?? null, $depth + 1),
        ];
    };
    return $build($rootId, 1);
}

function mlm_unilevel_tree(string $rootId): ?array
{
    $rows = db_all('SELECT * FROM mlm_unilevel ORDER BY sort_order, entry_date, id');
    $byId = [];
    $kids = [];
    foreach ($rows as $r) {
        $byId[$r['id']] = $r;
        if ($r['parent_id']) {
            $kids[$r['parent_id']][] = $r['id'];
        }
    }
    if (!isset($byId[$rootId])) {
        return null;
    }
    $rootGen = (int) $byId[$rootId]['generation'];
    $build = function (string $id, int $depth) use (&$build, $byId, $kids, $rootGen) {
        $r = $byId[$id];
        return [
            'id' => $r['id'], 'username' => $r['username'], 'fullName' => $r['full_name'],
            'generation' => (int) $r['generation'] - $rootGen, 'entryDate' => $r['entry_date'],
            'directsCount' => (int) $r['directs_count'], 'totalTeamCount' => (int) $r['total_team_count'],
            'children' => $depth >= 10 ? [] : array_map(fn ($c) => $build($c, $depth + 1), $kids[$id] ?? []),
        ];
    };
    return $build($rootId, 0);
}

/** True when $nodeId sits inside the binary subtree rooted at $rootId. */
/** One genealogy row as a tree node (no children). */
function mlm_tree_node(array $r): array
{
    return [
        'id' => $r['id'], 'username' => $r['username'], 'fullName' => $r['full_name'], 'package' => $r['package'],
        'position' => $r['position'], 'entryDate' => $r['entry_date'], 'status' => $r['status'],
        'leftPoints' => (int) $r['left_points'], 'rightPoints' => (int) $r['right_points'],
        'leftCount' => (int) $r['left_count'], 'rightCount' => (int) $r['right_count'],
    ];
}

/** A member's two direct downlines in the binary tree (null for an empty slot). */
function mlm_genealogy_children(string $id): array
{
    $out = ['left' => null, 'right' => null];
    foreach (db_all('SELECT * FROM mlm_genealogy WHERE placement_id = ?', [$id]) as $r) {
        if (array_key_exists((string) $r['position'], $out)) {
            $out[$r['position']] = mlm_tree_node($r);
        }
    }
    return $out;
}

/** Admins may open any part of the tree; a member only what is below their own heads. */
function mlm_can_view_node(array $user, string $nodeId): bool
{
    if (is_admin($user)) {
        return (bool) db_value('SELECT 1 FROM mlm_genealogy WHERE id = ?', [$nodeId]);
    }
    foreach (db_all('SELECT id FROM mlm_accounts WHERE user_id = ?', [$user['id']]) as $a) {
        if (mlm_in_subtree($a['id'], $nodeId)) {
            return true;
        }
    }
    return false;
}

function mlm_in_subtree(string $rootId, string $nodeId): bool
{
    $guard = 0;
    $id = $nodeId;
    while ($id && $guard++ < 200) {
        if ($id === $rootId) {
            return true;
        }
        $id = db_value('SELECT placement_id FROM mlm_genealogy WHERE id = ?', [$id]);
    }
    return false;
}

/* ---------------------------------------------------------------------
 * Lists for the backoffice
 * ------------------------------------------------------------------- */
function mlm_transactions(string $accountId): array
{
    return array_map('mlm_tx_to_array', db_all('SELECT * FROM mlm_transactions WHERE account_id = ? ORDER BY created_at DESC, id DESC', [$accountId]));
}

function mlm_encashments(string $accountId): array
{
    return array_map('mlm_encashment_to_array', db_all('SELECT * FROM mlm_encashments WHERE account_id = ? ORDER BY requested_at DESC', [$accountId]));
}

function mlm_codes(string $accountId): array
{
    return array_map('mlm_code_to_array', db_all('SELECT * FROM mlm_codes WHERE owner_account_id = ? ORDER BY serial DESC', [$accountId]));
}

function mlm_products(): array
{
    return array_map('mlm_product_to_array', db_all('SELECT * FROM mlm_products ORDER BY sort_order, name'));
}

function mlm_cart(string $accountId): array
{
    $rows = db_all('SELECT c.quantity, p.* FROM mlm_cart_items c JOIN mlm_products p ON p.id = c.product_id WHERE c.account_id = ? ORDER BY c.added_at', [$accountId]);
    return array_map(fn ($r) => ['product' => mlm_product_to_array($r), 'quantity' => (int) $r['quantity']], $rows);
}

function mlm_orders(string $accountId): array
{
    // Maya orders show once paid (awaiting_payment / cancelled ones were never paid).
    $orders = db_all('SELECT * FROM mlm_orders WHERE account_id = ? AND status NOT IN ("awaiting_payment", "cancelled") ORDER BY created_at DESC, id DESC', [$accountId]);
    $out = [];
    foreach ($orders as $o) {
        $items = db_all('SELECT product_id, name, quantity, price FROM mlm_order_items WHERE order_id = ? ORDER BY id', [$o['id']]);
        $out[] = [
            'id' => $o['id'], 'orderNumber' => $o['order_number'],
            'items' => array_map(fn ($i) => ['productId' => $i['product_id'], 'name' => $i['name'], 'quantity' => (int) $i['quantity'], 'price' => (float) $i['price']], $items),
            'totalAmount' => (float) $o['total_amount'], 'totalPoints' => (int) $o['total_points'], 'status' => $o['status'],
            'paymentMethod' => $o['payment_method'], 'shippingAddress' => $o['shipping_address'], 'date' => $o['order_date'],
        ];
    }
    return $out;
}

function mlm_notifications(string $accountId): array
{
    return array_map(fn ($r) => [
        'id' => $r['id'], 'title' => $r['title'], 'message' => $r['message'], 'type' => $r['type'],
        'timestamp' => to_ms($r['created_at']), 'read' => (bool) $r['is_read'],
    ], db_all('SELECT * FROM mlm_notifications WHERE account_id = ? ORDER BY created_at DESC', [$accountId]));
}

/** Security & audit log entries for a backoffice account / its owner. */
function mlm_logs(array $account, int $limit = 20): array
{
    $limit = max(1, min(200, $limit));
    return array_map('log_to_array', db_all(
        "SELECT * FROM audit_logs WHERE account_id = ? OR (account_id IS NULL AND user_id = ?) ORDER BY created_at DESC LIMIT $limit",
        [$account['id'], $account['userId']]
    ));
}

/* ---------------------------------------------------------------------
 * Wallet helpers
 * ------------------------------------------------------------------- */
function mlm_add_tx(string $accountId, string $type, string $title, float $amount, string $direction, string $status, string $ref, string $details, ?string $source = null, int $offsetMs = 0): void
{
    db_run('INSERT INTO mlm_transactions (id, account_id, type, title, amount, direction, status, reference_number, details, source_member, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, DATE_ADD(NOW(3), INTERVAL ? MICROSECOND))',
        [gen_id('TXN-' . strtoupper(substr($type, 0, 4)) . '-'), $accountId, $type, $title, $amount, $direction, $status, $ref, $details, $source, $offsetMs * 1000]);
}

function mlm_notify(string $accountId, string $title, string $message, string $type): void
{
    db_run('INSERT INTO mlm_notifications (id, account_id, title, message, type, is_read) VALUES (?, ?, ?, ?, ?, 0)', [gen_id('notif_'), $accountId, $title, $message, $type]);
}

function mlm_log(array $account, string $action): void
{
    add_audit($action, $account['userId'], $account['id']);
}

/** Lock and re-read an account row inside a transaction. */
function mlm_lock_account(string $id): array
{
    $row = db_one('SELECT * FROM mlm_accounts WHERE id = ? FOR UPDATE', [$id]);
    if (!$row) {
        throw new InvalidArgumentException('Account not found.');
    }
    return mlm_account_to_array($row);
}

/** Pairs go to cycle 1 before noon and cycle 2 afterwards. */
/** The pairing cycle now: 1 all day on a 1-cycle plan; otherwise 1 before noon, 2 after. */
function mlm_current_cycle(): int
{
    if (mlm_complan()['cyclesPerDay'] === 1) {
        return 1;
    }
    return (int) date('G') < 12 ? 1 : 2;
}

/* ---------------------------------------------------------------------
 * Actions
 * ------------------------------------------------------------------- */
function mlm_verify_pin(string $userId, string $pin): void
{
    if (!preg_match('/^\d{4,6}$/', $pin)) {
        throw new InvalidArgumentException('Please enter your 4–6 digit security PIN.');
    }
    $hash = db_value('SELECT security_pin_hash FROM member_account_details WHERE user_id = ?', [$userId]);
    if ($hash && !password_verify($pin, (string) $hash)) {
        throw new InvalidArgumentException('Incorrect security PIN.');
    }
}

function mlm_submit_encashment(array $account, array $in): array
{
    $amount = round(float_in($in, 'amount'), 2);
    $method = str_in($in, 'payoutMethod', 20);
    $details = str_in($in, 'accountDetails', 190);
    if ($amount < MLM_MIN_ENCASHMENT) {
        return ['success' => false, 'message' => 'Minimum encashment amount is Php 500.00'];
    }
    if (!in_array($method, MLM_PAYOUT_METHODS, true)) {
        return ['success' => false, 'message' => 'Please choose a payout channel.'];
    }
    if ($details === '') {
        return ['success' => false, 'message' => 'Please enter the payout account details.'];
    }
    mlm_verify_pin($account['userId'], str_in($in, 'pin', 10));

    return db_tx(function () use ($account, $amount, $method, $details) {
        $acc = mlm_lock_account($account['id']);
        if ($amount > $acc['availableBalance']) {
            return ['success' => false, 'message' => 'Requested amount exceeds your available balance.'];
        }
        $tax = round($amount * MLM_TAX_RATE);
        $net = $amount - $tax - MLM_PROCESSING_FEE;
        $upper = strtoupper($method);
        $id = 'ENC-' . ms_tail(5) . random_int(10, 99);
        $ref = 'REQ-' . $upper . '-' . ms_tail(6);
        db_run('UPDATE mlm_accounts SET available_balance = available_balance - ?, total_encashment = total_encashment + ? WHERE id = ?', [$amount, $amount, $acc['id']]);
        db_run('INSERT INTO mlm_encashments (id, account_id, account_name, amount, gross_amount, tax_deduction, processing_fee, net_amount, payout_method, account_details, status, reference_number) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "pending", ?)',
            [$id, $acc['id'], $acc['accountName'], $amount, $amount, $tax, MLM_PROCESSING_FEE, $net, $method, $details, $ref]);
        mlm_add_tx($acc['id'], 'encashment', 'Encashment Request via ' . $upper, $amount, 'out', 'pending', $ref,
            sprintf('Net amount ₱%s to %s (10%% Tax: ₱%s, Fee: ₱50).', num($net), $details, num($tax)));
        mlm_notify($acc['id'], 'Encashment Submitted: ₱' . num($amount) . ' PHP',
            'Your encashment request for ₱' . num($amount) . ' is currently pending finance payout clearance.', 'encashment');
        mlm_log($acc, 'Encashment Request: ₱' . num($amount) . ' (' . $upper . ')');
        return ['success' => true, 'message' => 'Encashment request for ₱' . num($amount) . ' submitted successfully. Net: ₱' . num($net)];
    });
}

function mlm_buy_codes(array $account, int $qty): array
{
    if ($qty < 1 || $qty > 20) {
        return ['success' => false, 'message' => 'You can generate between 1 and 20 codes at a time.', 'codes' => []];
    }
    return db_tx(function () use ($account, $qty) {
        $acc = mlm_lock_account($account['id']);
        $cost = $qty * mlm_complan()['entryFee'];
        if ($acc['availableBalance'] < $cost) {
            return ['success' => false, 'message' => 'Insufficient available balance to purchase activation codes.', 'codes' => []];
        }
        $codes = [];
        for ($i = 0; $i < $qty; $i++) {
            do {
                $code = 'EVO88-EXE-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 5));
            } while (db_value('SELECT 1 FROM mlm_codes WHERE code = ?', [$code]));
            $pin = rand_digits(4);
            db_run('INSERT INTO mlm_codes (code, pin, owner_account_id, value, status, generated_date) VALUES (?, ?, ?, ?, "active", CURDATE())', [$code, $pin, $acc['id'], mlm_complan()['entryFee']]);
            $codes[] = $code;
        }
        db_run('UPDATE mlm_accounts SET available_balance = available_balance - ? WHERE id = ?', [$cost, $acc['id']]);
        mlm_add_tx($acc['id'], 'code_purchase', "Generated {$qty}x Executive Activation Code(s)", $cost, 'out', 'completed', 'CODE-' . ms_tail(6),
            "Generated {$qty} code(s) @ Php 2,000 each using available balance.");
        mlm_log($acc, "Generated {$qty} activation code(s) (₱" . num($cost) . ')');
        return ['success' => true, 'message' => 'Generated ' . $qty . ' activation code(s)!', 'codes' => $codes];
    });
}

function mlm_transfer_code(array $account, string $code, string $username): array
{
    $username = ltrim(trim($username), '@');
    if ($username === '') {
        return ['success' => false, 'message' => 'Please enter the recipient username.'];
    }
    $row = db_one('SELECT * FROM mlm_codes WHERE code = ? AND owner_account_id = ? AND status = "active"', [$code, $account['id']]);
    if (!$row) {
        return ['success' => false, 'message' => 'Invalid, inactive or already used code.'];
    }
    $recipient = db_one('SELECT id FROM mlm_accounts WHERE username = ?', [strtolower($username)]);
    if (!$recipient) {
        return ['success' => false, 'message' => "No backoffice account found for @{$username}."];
    }
    if ($recipient['id'] === $account['id']) {
        return ['success' => false, 'message' => 'You already own this code.'];
    }
    db_run('UPDATE mlm_codes SET owner_account_id = ?, used_by = ?, used_at = NOW() WHERE code = ?', [$recipient['id'], 'Transferred to ' . $username, $code]);
    mlm_log($account, "Transferred activation code {$code} to {$username}");
    return ['success' => true, 'message' => "Code {$code} successfully transferred to @{$username}!"];
}

/**
 * Place a new member in the binary tree using an activation code/PIN.
 * The member goes under placementId (default: the member's own head) on the
 * chosen leg: "left", "right" or "auto" (see mlm_leg_placement).
 * Pays the direct referral bonus and, when the opposite leg has volume,
 * a pairing bonus (capped at the plan's pairs per cycle).
 */
function mlm_register_downline(array $account, array $in): array
{
    $placementId = str_in($in, 'placementId', 20) ?: $account['id'];
    $choice = str_in($in, 'position', 5);
    $username = strtolower(str_in($in, 'username', 60));
    $fullName = str_in($in, 'fullName', 150);
    $codeIn = strtoupper(str_in($in, 'activationCode', 40));
    $pinIn = str_in($in, 'activationPin', 10);

    if ($fullName === '') {
        return ['success' => false, 'message' => 'Please enter the new member\'s full name.'];
    }
    if (!preg_match('/^[a-z0-9_]{3,30}$/', $username)) {
        return ['success' => false, 'message' => 'Username must be 3–30 characters (letters, numbers, underscore).'];
    }
    if (!$codeIn || !$pinIn) {
        return ['success' => false, 'message' => 'Please select an active activation code and PIN.'];
    }
    if (!isset(MLM_PLACEMENTS[$choice])) {
        return ['success' => false, 'message' => 'Choose where to place the new member: Auto, Left or Right.'];
    }
    if (!mlm_in_subtree($account['id'], $placementId)) {
        return ['success' => false, 'message' => 'Placement must be inside your own binary genealogy.'];
    }

    return db_tx(function () use ($account, $placementId, $choice, $username, $fullName, $codeIn, $pinIn) {
        $code = db_one('SELECT * FROM mlm_codes WHERE code = ? AND pin = ? AND status = "active" AND owner_account_id = ? FOR UPDATE', [$codeIn, $pinIn, $account['id']]);
        if (!$code) {
            return ['success' => false, 'message' => 'Invalid, inactive or already consumed activation code/PIN.'];
        }
        // The binary entry is the Executive package; a Chairman's Circle code is for a sign-up.
        if ($code['package'] !== 'membership_executive') {
            return ['success' => false, 'message' => "This is a Chairman's Circle code. Downlines are placed with Executive codes; give this code to a new member to sign up with."];
        }
        if (db_value('SELECT 1 FROM mlm_genealogy WHERE username = ? UNION SELECT 1 FROM mlm_accounts WHERE username = ?', [$username, $username])) {
            return ['success' => false, 'message' => "Username @{$username} is already taken."];
        }
        [$parentId, $position, $leg] = mlm_leg_placement($placementId, $choice);
        $newId = mlm_next_id();
        ['direct' => $direct, 'pairing' => $pairing] = mlm_join_binary($account['id'], $newId, $username, $fullName, $parentId, $position);
        db_run('UPDATE mlm_codes SET status = "used", used_by = ?, used_at = NOW() WHERE code = ?', [$username, $codeIn]);

        $names = array_column(db_all('SELECT id, full_name FROM mlm_genealogy WHERE id IN (?, ?)', [$placementId, $parentId]), 'full_name', 'id');
        $whose = $placementId === $account['id'] ? 'your' : ($names[$placementId] ?? $placementId) . '\'s';
        $where = "on {$whose} " . strtoupper($leg) . ' leg' . ($choice === 'auto' ? ' (auto: fewer downlines)' : '')
            . ', under ' . ($names[$parentId] ?? $parentId) . " ({$parentId}), {$position} slot";
        return [
            'success' => true,
            'message' => "Downline {$fullName} successfully registered {$where}. Earned ₱{$direct} Direct Referral" . ($pairing > 0 ? " and ₱{$pairing} Pairing Match!" : '.'),
            'newDownlineId' => $newId,
            'placementId' => $parentId,
            'position' => $position,
            'leg' => $leg,
        ];
    });
}

/**
 * Where a downline placed under $nodeId goes. $choice is a leg of $nodeId:
 * "left", "right", or "auto" for the leg with fewer downlines (left on a tie).
 * The member takes that leg's slot when it is free, otherwise the first free
 * spot further down the same leg, level by level and left before right.
 * Returns [placement id, slot position, leg of $nodeId]. Call inside db_tx().
 */
function mlm_leg_placement(string $nodeId, string $choice): array
{
    $leg = $choice;
    if ($choice === 'auto') {
        $counts = db_one('SELECT left_count, right_count FROM mlm_genealogy WHERE id = ? FOR UPDATE', [$nodeId]);
        $leg = (int) ($counts['left_count'] ?? 0) <= (int) ($counts['right_count'] ?? 0) ? 'left' : 'right';
    }
    $top = db_value('SELECT id FROM mlm_genealogy WHERE placement_id = ? AND position = ? FOR UPDATE', [$nodeId, $leg]);
    if (!$top) {
        return [$nodeId, $leg, $leg];
    }
    [$parentId, $position] = mlm_auto_placement((string) $top);
    return [$parentId, $position, $leg];
}

/**
 * Where a sign-up with $sponsorId as sponsor goes: under the sponsor, on the
 * leg the sponsor chose for sign-ups (Auto when the sponsor has no backoffice
 * account). Returns [placement id, slot position, leg]. Call inside db_tx().
 */
function mlm_signup_placement(string $sponsorId): array
{
    $choice = (string) db_value('SELECT signup_placement FROM mlm_accounts WHERE id = ?', [$sponsorId]);
    return mlm_leg_placement($sponsorId, isset(MLM_PLACEMENTS[$choice]) ? $choice : 'auto');
}

/** The sign-up form's line under the sponsor ID: where the new member will go. */
function mlm_signup_placement_text(string $sponsorName, string $choice): string
{
    $leg = $choice === 'left' || $choice === 'right' ? "the {$choice} leg" : 'the leg with fewer downlines';
    return "You'll join {$sponsorName}'s team on {$leg}.";
}

/** A member's choice of where sign-ups with their ID go (Backoffice > Network). */
function mlm_set_signup_placement(array $account, string $choice): array
{
    if (!isset(MLM_PLACEMENTS[$choice])) {
        return ['success' => false, 'message' => 'Choose Auto, Left or Right.'];
    }
    db_run('UPDATE mlm_accounts SET signup_placement = ? WHERE id = ?', [$choice, $account['id']]);
    mlm_log($account, 'Sign-up placement set to ' . MLM_PLACEMENTS[$choice]);
    $where = $choice === 'auto' ? 'on your leg with fewer downlines' : "on your {$choice} leg";
    return ['success' => true, 'message' => "New members who sign up with your ID {$account['id']} will be placed {$where}.", 'placement' => $choice];
}

/**
 * Put a member into the binary tree under $placementId on $position and pay
 * the sponsor: the direct referral bonus, and a pairing bonus when the
 * sponsor's opposite leg has points (capped at the plan's pairs per cycle).
 * Volume (counts and points) goes to every upline node. Call inside db_tx().
 *
 * $nodeExists: the genealogy row is already there (an earlier sign-up being
 * placed); otherwise it is created. $pay = false places without commissions.
 * Returns ['direct' => ₱, 'pairing' => ₱, 'sponsorLeg' => 'left'|'right'].
 */
function mlm_join_binary(string $sponsorId, string $newId, string $username, string $fullName, string $placementId, string $position, bool $nodeExists = false, bool $pay = true): array
{
    $level = ((int) db_value('SELECT level FROM mlm_genealogy WHERE id = ?', [$placementId])) + 1;
    if ($nodeExists) {
        db_run('UPDATE mlm_genealogy SET placement_id = ?, position = ?, level = ? WHERE id = ?', [$placementId, $position, $level, $newId]);
    } else {
        db_run('INSERT INTO mlm_genealogy (id, username, full_name, package, sponsor_id, placement_id, position, entry_date, status, level) VALUES (?, ?, ?, "Php 2,000 Executive", ?, ?, ?, CURDATE(), "active", ?)',
            [$newId, $username, $fullName, $sponsorId, $placementId, $position, $level]);
    }

    // Volume to every upline node; remember which of the sponsor's legs it landed on.
    $sponsorLeg = null;
    $childPos = $position;
    $parent = $placementId;
    $guard = 0;
    while ($parent && $guard++ < 500) {
        $col = $childPos === 'left' ? 'left' : 'right';
        db_run("UPDATE mlm_genealogy SET {$col}_count = {$col}_count + 1, {$col}_points = {$col}_points + 1 WHERE id = ?", [$parent]);
        if ($parent !== $sponsorId) {
            db_run("UPDATE mlm_accounts SET {$col}_count = {$col}_count + 1, {$col}_points = {$col}_points + 1 WHERE id = ?", [$parent]);
        } else {
            $sponsorLeg = $childPos;
        }
        $row = db_one('SELECT placement_id, position FROM mlm_genealogy WHERE id = ?', [$parent]);
        $childPos = $row['position'] ?? null;
        $parent = $row['placement_id'] ?? null;
        if (!$childPos) {
            break;
        }
    }
    $sponsorLeg = $sponsorLeg ?? $position;

    // Unilevel: under the sponsor (an earlier sign-up already has its row).
    $uParent = db_one('SELECT generation FROM mlm_unilevel WHERE id = ?', [$sponsorId]);
    if (!db_value('SELECT 1 FROM mlm_unilevel WHERE id = ?', [$newId])) {
        db_run('INSERT INTO mlm_unilevel (id, parent_id, username, full_name, generation, entry_date, sort_order) VALUES (?, ?, ?, ?, ?, CURDATE(), 99)',
            [$newId, $uParent ? $sponsorId : null, $username, $fullName, $uParent ? ((int) $uParent['generation']) + 1 : 0]);
        if ($uParent) {
            mlm_bump_unilevel_upline($sponsorId, true);
        }
    }

    // The sponsor's wallet. A sponsor that is only a tree node (no backoffice account) earns nothing.
    if (!db_value('SELECT 1 FROM mlm_accounts WHERE id = ?', [$sponsorId])) {
        return ['direct' => 0, 'pairing' => 0, 'sponsorLeg' => $sponsorLeg];
    }
    $acc = mlm_lock_account($sponsorId);
    $plan = mlm_complan();
    $direct = $pay ? $plan['directReferralBonus'] : 0;
    $cycle = mlm_current_cycle();
    $cycleField = $cycle === 1 ? 'cycle1PairsToday' : 'cycle2PairsToday';
    $opposite = $sponsorLeg === 'left' ? $acc['rightPoints'] : $acc['leftPoints'];
    $pair = ($pay && $opposite > 0 && $acc[$cycleField] < $plan['maxPairsPerCycle']) ? 1 : 0;
    $pairing = $pair * $plan['pairingBonus'];
    $credit = $direct + $pairing;
    // A pair uses one point from each leg: the new member's and one waiting on the other side.
    $left = $acc['leftPoints'] + ($sponsorLeg === 'left' ? 1 : 0) - $pair;
    $right = $acc['rightPoints'] + ($sponsorLeg === 'right' ? 1 : 0) - $pair;
    db_run("UPDATE mlm_accounts SET available_balance = available_balance + ?, total_income = total_income + ?, total_direct_bonus = total_direct_bonus + ?,
            total_pairing_bonus = total_pairing_bonus + ?, sponsored_count = sponsored_count + 1,
            left_count = left_count + ?, right_count = right_count + ?, left_points = ?, right_points = ?,
            cycle{$cycle}_pairs_today = cycle{$cycle}_pairs_today + ? WHERE id = ?",
        [$credit, $credit, $direct, $pairing, $sponsorLeg === 'left' ? 1 : 0, $sponsorLeg === 'right' ? 1 : 0, max(0, $left), max(0, $right), $pair, $acc['id']]);
    // Keep the sponsor's genealogy node in step with its wallet volume (pair flush included).
    db_run('UPDATE mlm_genealogy SET left_points = ?, right_points = ? WHERE id = ?', [max(0, $left), max(0, $right), $acc['id']]);

    // The sponsor's leg the member landed in (a deep spot can be a left slot inside the right leg).
    $legUpper = strtoupper($sponsorLeg);
    if ($direct > 0) {
        mlm_add_tx($acc['id'], 'direct_referral', 'Direct Referral Commission', $direct, 'in', 'completed', 'DIR-' . ms_tail(6),
            "Sponsored {$fullName} (@{$username}) on {$legUpper} leg.", $fullName);
    }
    if ($pairing > 0) {
        // Stamped 10ms later so it lists above the direct bonus, as in the reference.
        mlm_add_tx($acc['id'], 'binary_pairing', 'Binary Pairing Match Bonus', $pairing, 'in', 'completed', 'PAIR-' . ms_tail(6),
            '1 Left : 1 Right match triggered @ Php ' . num($plan['pairingBonus']) . '. Cycle ' . $cycle . ' progress: ' . ($acc[$cycleField] + 1) . '/' . $plan['maxPairsPerCycle'] . ' pairs.', null, 10);
    }
    if ($credit > 0) {
        mlm_notify($acc['id'], 'New Downline Registered: ' . $fullName,
            "Registered on {$legUpper} leg. Earned ₱" . num($direct) . ' Direct Bonus' . ($pairing > 0 ? ' + ₱' . num($pairing) . ' Pairing Match!' : '!'), 'bonus');
    }
    mlm_log($acc, "Registered Downline @{$username} ({$fullName}) on {$legUpper} leg");
    return ['direct' => $direct, 'pairing' => $pairing, 'sponsorLeg' => $sponsorLeg];
}

/**
 * The first free slot under $sponsorId: its left slot, then the right, then
 * the first free slot below, level by level and left before right (used for
 * the spot inside a chosen leg, see mlm_leg_placement). The slots looked at
 * are locked until the transaction ends.
 */
function mlm_auto_placement(string $sponsorId): array
{
    $queue = [$sponsorId];
    for ($i = 0; $i < count($queue) && $i < 100000; $i++) {
        $kids = db_all('SELECT id, position FROM mlm_genealogy WHERE placement_id = ? FOR UPDATE', [$queue[$i]]);
        $byLeg = array_column($kids, 'id', 'position');
        if (!isset($byLeg['left'])) {
            return [$queue[$i], 'left'];
        }
        if (!isset($byLeg['right'])) {
            return [$queue[$i], 'right'];
        }
        $queue[] = $byLeg['left'];
        $queue[] = $byLeg['right'];
    }
    throw new RuntimeException('No free placement found under ' . $sponsorId . '.');
}

/**
 * Earlier sign-ups were recorded with their sponsor but never placed in the
 * binary tree or paid for. Place them now, oldest first, and pay their
 * sponsors as a sign-up does today. Returns one line per account.
 */
function mlm_place_unplaced_signups(): array
{
    $rows = db_all("SELECT a.id, a.username, a.sponsor_id, g.full_name FROM mlm_accounts a JOIN mlm_genealogy g ON g.id = a.id
                    WHERE g.placement_id IS NULL AND a.sponsor_id NOT IN ('', 'SYSTEM') AND a.id <> a.sponsor_id
                    ORDER BY a.created_at, a.id");
    $done = [];
    foreach ($rows as $r) {
        [$placementId, $position] = mlm_signup_placement($r['sponsor_id']);
        $paid = mlm_join_binary($r['sponsor_id'], $r['id'], $r['username'], $r['full_name'], $placementId, $position, true);
        db_run('UPDATE mlm_accounts SET placement_id = ?, leg_position = ? WHERE id = ?', [$placementId, $position, $r['id']]);
        $done[] = sprintf('%s @%s -> under %s (%s); sponsor %s earned direct P%s, pairing P%s',
            $r['id'], $r['username'], $placementId, $position, $r['sponsor_id'], num($paid['direct']), num($paid['pairing']));
    }
    return $done;
}

/** Credit one 1:1 pairing match (demo tool shown on the dashboard). */
function mlm_simulate_pairing(array $account): array
{
    return db_tx(function () use ($account) {
        $acc = mlm_lock_account($account['id']);
        $cycle = mlm_current_cycle();
        $field = $cycle === 1 ? 'cycle1PairsToday' : 'cycle2PairsToday';
        if ($acc[$field] >= mlm_complan()['maxPairsPerCycle']) {
            $max = mlm_complan()['maxPairsPerCycle'];
            return ['success' => false, 'message' => 'Daily Cycle Safety Net reached! Max ' . $max . ' pair' . ($max === 1 ? '' : 's') . ' per cycle. Wait for next cycle reset.'];
        }
        $bonus = mlm_complan()['pairingBonus'];
        db_run("UPDATE mlm_accounts SET available_balance = available_balance + ?, total_income = total_income + ?, total_pairing_bonus = total_pairing_bonus + ?, cycle{$cycle}_pairs_today = cycle{$cycle}_pairs_today + 1 WHERE id = ?",
            [$bonus, $bonus, $bonus, $acc['id']]);
        mlm_add_tx($acc['id'], 'binary_pairing', 'Binary Pairing Match Bonus', $bonus, 'in', 'completed', 'PAIR-' . ms_tail(6),
            '1 Left : 1 Right pairing match. Cycle ' . $cycle . ' count: ' . ($acc[$field] + 1) . '/' . mlm_complan()['maxPairsPerCycle'] . ' pairs.');
        mlm_notify($acc['id'], 'Binary Pair Match: +₱' . num($bonus) . ' PHP', 'Matched 1 pair (Left:Right) @ Php ' . num($bonus) . '. Credited to available balance.', 'pairing');
        return ['success' => true, 'message' => 'Binary pair matched: +₱' . num($bonus) . ' credited to available balance.'];
    });
}

/* ---------------------------------------------------------------------
 * Member shop
 * ------------------------------------------------------------------- */
function mlm_cart_set(array $account, string $productId, int $qty, bool $add = false): void
{
    $product = db_one('SELECT id, stock FROM mlm_products WHERE id = ?', [$productId]);
    if (!$product) {
        throw new InvalidArgumentException('Product not found.');
    }
    if ($add) {
        $qty += (int) db_value('SELECT quantity FROM mlm_cart_items WHERE account_id = ? AND product_id = ?', [$account['id'], $productId]);
    }
    if ($qty <= 0) {
        db_run('DELETE FROM mlm_cart_items WHERE account_id = ? AND product_id = ?', [$account['id'], $productId]);
        return;
    }
    $qty = min($qty, max(1, (int) $product['stock']), 99);
    db_run('INSERT INTO mlm_cart_items (account_id, product_id, quantity) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE quantity = VALUES(quantity)', [$account['id'], $productId, $qty]);
}

function mlm_cart_clear(array $account): void
{
    db_run('DELETE FROM mlm_cart_items WHERE account_id = ?', [$account['id']]);
}

/** The cart's lines with its total and PV, checked against stock. Returns [$cart, $total, $points]. */
function mlm_cart_checkout_lines(string $accountId): array
{
    $cart = mlm_cart($accountId);
    if (!$cart) {
        throw new InvalidArgumentException('Your shopping cart is empty.');
    }
    $total = 0.0;
    $points = 0;
    foreach ($cart as $line) {
        if ($line['quantity'] > $line['product']['stock']) {
            throw new InvalidArgumentException('Not enough stock for ' . $line['product']['name'] . '.');
        }
        $total += $line['product']['memberPrice'] * $line['quantity'];
        $points += $line['product']['points'] * $line['quantity'];
    }
    return [$cart, $total, $points];
}

/** Store an order and its items ("paid", or "awaiting_payment" for Maya). Returns [id, order number]. */
function mlm_write_order(array $account, array $cart, float $total, int $points, string $method, string $address, string $status): array
{
    $orderId = 'ORD-' . ms_tail(5) . random_int(10, 99);
    $orderNumber = 'ORD-' . ms_tail(6);
    db_run('INSERT INTO mlm_orders (id, account_id, order_number, total_amount, total_points, status, payment_method, shipping_address, order_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURDATE())',
        [$orderId, $account['id'], $orderNumber, $total, $points, $status, $method, $address]);
    foreach ($cart as $line) {
        db_run('INSERT INTO mlm_order_items (order_id, product_id, name, quantity, price) VALUES (?, ?, ?, ?, ?)',
            [$orderId, $line['product']['id'], $line['product']['name'], $line['quantity'], $line['product']['memberPrice']]);
    }
    return [$orderId, $orderNumber];
}

/** A paid order takes its items out of stock and out of the member's cart. */
function mlm_order_fulfil(string $orderId, string $accountId): void
{
    foreach (db_all('SELECT product_id, quantity FROM mlm_order_items WHERE order_id = ?', [$orderId]) as $i) {
        db_run('UPDATE mlm_products SET stock = GREATEST(0, stock - ?) WHERE id = ?', [$i['quantity'], $i['product_id']]);
        db_run('UPDATE mlm_cart_items SET quantity = quantity - ? WHERE account_id = ? AND product_id = ?', [$i['quantity'], $accountId, $i['product_id']]);
    }
    db_run('DELETE FROM mlm_cart_items WHERE account_id = ? AND quantity <= 0', [$accountId]);
}

function mlm_shop_address(string $address): string
{
    $address = mb_substr(trim($address), 0, 255);
    if ($address === '') {
        throw new InvalidArgumentException('Please enter a shipping address.');
    }
    return $address;
}

/** Pay for the cart with the member's available MLM balance (Maya: mlm_checkout_maya()). */
function mlm_checkout_cart(array $account, string $methodKey, string $address): array
{
    if ($methodKey !== 'balance') {
        throw new InvalidArgumentException('Please choose a payment method.');
    }
    $address = mlm_shop_address($address);
    return db_tx(function () use ($account, $address) {
        [$cart, $total, $points] = mlm_cart_checkout_lines($account['id']);
        $acc = mlm_lock_account($account['id']);
        if ($acc['availableBalance'] < $total) {
            throw new InvalidArgumentException('Insufficient MLM Available Balance');
        }
        db_run('UPDATE mlm_accounts SET available_balance = available_balance - ? WHERE id = ?', [$total, $acc['id']]);
        mlm_add_tx($acc['id'], 'shop_purchase', '88MMM Member Shop Purchase', $total, 'out', 'completed', 'SHOP-' . ms_tail(6),
            'Purchased ' . count($cart) . ' item(s) via MLM wallet balance.');
        $method = MLM_SHOP_METHODS['balance'];
        [$orderId, $orderNumber] = mlm_write_order($account, $cart, $total, $points, $method, $address, 'paid');
        mlm_order_fulfil($orderId, $account['id']);
        mlm_log($account, 'Member Shop Order Placed: ₱' . num($total) . " ({$orderNumber})");
        return ['orderNumber' => $orderNumber, 'totalAmount' => $total, 'totalPoints' => $points, 'paymentMethod' => $method];
    });
}

/**
 * Pay for the cart with Maya Checkout: the order waits as "awaiting_payment"
 * (hidden from the order list) until maya_sync() hears from Maya that it is
 * paid (mlm_shop_order_paid()). The cart stays as it is until then. Returns
 * Maya's payment page.
 */
function mlm_checkout_maya(array $account, array $user, string $address): string
{
    $address = mlm_shop_address($address);
    if (!maya_enabled()) {
        throw new InvalidArgumentException('Paying with Maya isn\'t available yet. Pay with your MLM balance, or contact the 88MMM administrator.');
    }
    [$orderId, $orderNumber, $total] = db_tx(function () use ($account, $address) {
        [$cart, $total, $points] = mlm_cart_checkout_lines($account['id']);
        return [...mlm_write_order($account, $cart, $total, $points, MLM_SHOP_METHODS['maya'], $address, 'awaiting_payment'), $total];
    });
    $phone = get_account_details($user['id'])['phone'] ?: (string) (get_member_profile($user['id'])['phone'] ?? '');
    $req = [
        'itemType' => 'shop_order', 'item' => ['name' => '88MMM Member Shop order ' . $orderNumber, 'price' => $total], 'invoice' => null,
        'name' => $user['name'], 'email' => $user['email'], 'phone' => mb_substr($phone, 0, 60),
    ];
    try {
        return maya_start_checkout($req, $user, $orderId)['redirectUrl'];
    } catch (Throwable $e) {
        db_run('UPDATE mlm_orders SET status = "cancelled" WHERE id = ? AND status = "awaiting_payment"', [$orderId]);
        throw $e;
    }
}

/**
 * Maya confirmed a shop order's payment (called by maya_sync() inside its
 * transaction): the order is paid, and stock and cart are updated. Does
 * nothing for an order that was already marked paid.
 */
function mlm_shop_order_paid(string $orderId, string $note): void
{
    $o = db_one('SELECT * FROM mlm_orders WHERE id = ? FOR UPDATE', [$orderId]);
    if (!$o || !in_array($o['status'], ['awaiting_payment', 'cancelled'], true)) {
        return;
    }
    db_run('UPDATE mlm_orders SET status = "paid", payment_note = ? WHERE id = ?', [mb_substr($note, 0, 255), $orderId]);
    mlm_order_fulfil($orderId, $o['account_id']);
    $account = db_one('SELECT id, user_id AS userId FROM mlm_accounts WHERE id = ?', [$o['account_id']]);
    if ($account) {
        mlm_log($account, 'Member Shop Order Placed: ₱' . num((float) $o['total_amount']) . ' (' . $o['order_number'] . '), paid with Maya');
    }
}

/**
 * Back from Maya to the shop (?maya=<rrn>): ask Maya, then report the
 * order's state ('paid', 'pending' or 'failed'), its number and, when it
 * failed, why. Null when the reference isn't one of this account's orders.
 */
function mlm_shop_maya_return(array $account, string $rrn, string $result): ?array
{
    if (!preg_match('/^MAYA-[0-9A-F]{24}$/', $rrn)
        || !db_value('SELECT 1 FROM maya_checkouts c JOIN mlm_orders o ON o.id = c.order_id WHERE c.rrn = ? AND o.account_id = ?', [$rrn, $account['id']])) {
        return null;
    }
    $row = maya_sync($rrn);
    $state = maya_state($row);
    // Maya's page sends cancel / failure before its status changes: nothing was paid.
    if ($state === 'pending' && in_array($result, ['cancel', 'failure'], true)) {
        $state = 'failed';
    }
    $status = $row['status'] !== 'CREATED' && !str_starts_with($row['status'], 'PENDING') ? $row['status'] : ($result === 'cancel' ? 'PAYMENT_CANCELLED' : 'PAYMENT_FAILED');
    return [
        'state' => $state,
        'rrn' => $rrn,
        'orderNumber' => (string) db_value('SELECT order_number FROM mlm_orders WHERE id = ?', [$row['order_id']]),
        'message' => $state === 'failed' ? maya_failure_message($status) : '',
    ];
}

function mlm_mark_notification_read(array $account, string $id): void
{
    db_run('UPDATE mlm_notifications SET is_read = 1 WHERE id = ? AND account_id = ?', [$id, $account['id']]);
}

function mlm_clear_notifications(array $account): void
{
    db_run('DELETE FROM mlm_notifications WHERE account_id = ?', [$account['id']]);
}

/* ---------------------------------------------------------------------
 * My Account details & security
 * ------------------------------------------------------------------- */
function get_account_details(string $userId): array
{
    $r = db_one('SELECT * FROM member_account_details WHERE user_id = ?', [$userId]) ?? [];
    return [
        'birthday' => $r['birthday'] ?? '', 'gender' => $r['gender'] ?? '', 'tinNumber' => $r['tin_number'] ?? '',
        'occupation' => $r['occupation'] ?? '', 'phone' => $r['phone'] ?? '', 'whatsapp' => $r['whatsapp'] ?? '',
        'telegram' => $r['telegram'] ?? '', 'street' => $r['street'] ?? '', 'barangay' => $r['barangay'] ?? '',
        'city' => $r['city'] ?? '', 'province' => $r['province'] ?? '', 'zipCode' => $r['zip_code'] ?? '',
        'country' => $r['country'] ?? '', 'hasPin' => !empty($r['security_pin_hash']),
        'twoFactorEnabled' => isset($r['two_factor_enabled']) ? (bool) $r['two_factor_enabled'] : true,
    ];
}

function save_account_details(array $user, array $in): void
{
    $birthday = str_in($in, 'birthday', 10);
    if ($birthday !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthday)) {
        throw new InvalidArgumentException('Birthday must be a valid date.');
    }
    $email = str_in($in, 'email', 190);
    if ($email !== '' && !valid_email($email)) {
        throw new InvalidArgumentException('Please enter a valid email address.');
    }
    $fields = [
        'birthday' => $birthday ?: null, 'gender' => str_in($in, 'gender', 20), 'tin_number' => str_in($in, 'tinNumber', 40),
        'occupation' => str_in($in, 'occupation', 190), 'phone' => str_in($in, 'phone', 60), 'whatsapp' => str_in($in, 'whatsapp', 60),
        'telegram' => str_in($in, 'telegram', 60), 'street' => str_in($in, 'street', 190), 'barangay' => str_in($in, 'barangay', 120),
        'city' => str_in($in, 'city', 120), 'province' => str_in($in, 'province', 120), 'zip_code' => str_in($in, 'zipCode', 20),
        'country' => str_in($in, 'country', 80),
    ];
    $cols = implode(', ', array_keys($fields));
    $ph = implode(', ', array_fill(0, count($fields), '?'));
    $upd = implode(', ', array_map(fn ($c) => "$c = VALUES($c)", array_keys($fields)));
    db_run("INSERT INTO member_account_details (user_id, $cols) VALUES (?, $ph) ON DUPLICATE KEY UPDATE $upd", [$user['id'], ...array_values($fields)]);

    $fullName = str_in($in, 'fullName', 150);
    if ($fullName !== '' || $email !== '') {
        db_run('UPDATE users SET name = COALESCE(NULLIF(?, ""), name), email = COALESCE(NULLIF(?, ""), email) WHERE id = ?', [$fullName, strtolower($email), $user['id']]);
    }
    add_audit('Backoffice profile details updated', $user['id']);
}

function save_security_settings(array $user, array $in): void
{
    $current = (string) ($in['currentPassword'] ?? '');
    $new = (string) ($in['newPassword'] ?? '');
    $confirm = (string) ($in['confirmPassword'] ?? '');
    $pin = str_in($in, 'pin', 10);
    $twoFactor = !empty($in['twoFactor']) ? 1 : 0;

    if ($new !== '' || $confirm !== '') {
        if ($new !== $confirm) {
            throw new InvalidArgumentException('Passwords do not match');
        }
        if (mb_strlen($new) < 6) {
            throw new InvalidArgumentException('New password must be at least 6 characters.');
        }
        $row = db_one('SELECT password_hash FROM users WHERE id = ?', [$user['id']]);
        if (!$row || !password_verify($current, $row['password_hash'])) {
            throw new InvalidArgumentException('Current password is incorrect.');
        }
        db_run('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $user['id']]);
        refresh_password_stamp($user['id']);
        add_audit('Account password changed', $user['id']);
    }
    $pinHash = null;
    if ($pin !== '') {
        if (!preg_match('/^\d{4,6}$/', $pin)) {
            throw new InvalidArgumentException('Security PIN must be 4–6 digits.');
        }
        $pinHash = password_hash($pin, PASSWORD_DEFAULT);
    }
    db_run('INSERT INTO member_account_details (user_id, security_pin_hash, two_factor_enabled) VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE security_pin_hash = COALESCE(VALUES(security_pin_hash), security_pin_hash), two_factor_enabled = VALUES(two_factor_enabled)',
        [$user['id'], $pinHash, $twoFactor]);
    add_audit('Security settings updated (2FA ' . ($twoFactor ? 'enabled' : 'disabled') . ')', $user['id']);
}
