<?php
/**
 * Members' 1EVOAI cards: their own design and their own products.
 *
 * Design (Backoffice > Digital Card CMS > Card Design; member_profiles.card_design):
 * a colour theme or their own accent colour, a font, a cover photo, and
 * which blocks of the card show, in what order.
 *
 * Products (Backoffice > My Products; member_products): members list what
 * they sell. Visitors buy on the card, or in the Shop section of the member's
 * website (views/site.php), and pay with Maya (includes/maya.php).
 * Once Maya confirms a payment the order is paid, and the amount less the
 * platform fee (Management Admin > Shop Products) goes to the member's
 * Available Balance. Members mark orders shipped / completed; admins record
 * refunds made in Maya, which take the amount back from the member's balance.
 */
declare(strict_types=1);

/** Accent colour themes: [accent, light accent, name]. */
const CARD_THEMES = [
    'gold'     => ['#f3bd35', '#ffda72', 'Executive Gold'],
    'rosegold' => ['#e8a598', '#f6cfc6', 'Rose Gold'],
    'platinum' => ['#cbd5e1', '#f1f5f9', 'Platinum'],
    'emerald'  => ['#34d399', '#a7f3d0', 'Emerald'],
    'sapphire' => ['#60a5fa', '#bfdbfe', 'Sapphire'],
    'ruby'     => ['#f87171', '#fecaca', 'Ruby'],
    'amethyst' => ['#a78bfa', '#ddd6fe', 'Amethyst'],
];
const CARD_FONTS = ['sans' => 'Modern', 'serif' => 'Classic', 'rounded' => 'Rounded'];
/** The blocks under the profile card, in their default order. */
const CARD_BLOCKS = [
    'community'  => 'Join the Circle (headline, motto and join buttons)',
    'about'      => 'About me',
    'products'   => 'My Products',
    'video'      => 'Keynote video',
    'circle'     => 'The 88MMM Circle story',
    'membership' => 'Membership tiers',
];
const CARD_COVER_DIR = 'uploads/covers';
const CARD_PRODUCT_DIR = 'uploads/card-products';
const CARD_PRODUCTS_MAX = 30;
const CARD_ORDER_MAX_QTY = 99;
const CARD_SALES_KEY = 'card_sales'; // cms_settings: {"feePercent": …}
/** The platform fee kept from each card sale until an admin sets another (Management Admin > Shop Products). */
const CARD_SALES_DEFAULT_FEE = 10.0;
const CARD_FULFILLMENT = ['new' => 'New', 'shipped' => 'Shipped', 'completed' => 'Completed'];
/** Where an order was placed (card_orders.source); the buyer returns there from Maya. */
const CARD_ORDER_SOURCES = ['card' => 'Card', 'site' => 'Website'];

/* =====================================================================
 * Design
 * =================================================================== */

function card_design_default(): array
{
    return [
        'theme' => 'gold', 'accent' => '', 'font' => 'sans', 'cover' => '',
        'blocks' => array_map(fn ($id) => ['id' => $id, 'show' => true], array_keys(CARD_BLOCKS)),
    ];
}

/** A clean design from $in; the cover photo only changes through its own upload. */
function card_design_clean(array $in, string $cover = ''): array
{
    $d = card_design_default();
    $d['theme'] = isset(CARD_THEMES[$in['theme'] ?? '']) ? $in['theme'] : 'gold';
    $accent = strtolower(trim((string) ($in['accent'] ?? '')));
    $d['accent'] = preg_match('/^#[0-9a-f]{6}$/', $accent) ? $accent : '';
    $d['font'] = isset(CARD_FONTS[$in['font'] ?? '']) ? $in['font'] : 'sans';
    $d['cover'] = image_upload_is_ours($cover, CARD_COVER_DIR) ? $cover : '';
    $blocks = [];
    foreach (is_array($in['blocks'] ?? null) ? $in['blocks'] : [] as $b) {
        $id = is_array($b) ? (string) ($b['id'] ?? '') : '';
        if (isset(CARD_BLOCKS[$id]) && !isset($blocks[$id])) {
            $blocks[$id] = ['id' => $id, 'show' => !empty($b['show']) && $b['show'] !== 'false'];
        }
    }
    foreach (array_keys(CARD_BLOCKS) as $id) { // a block left out keeps showing, at the end
        $blocks[$id] ??= ['id' => $id, 'show' => true];
    }
    $d['blocks'] = array_values($blocks);
    return $d;
}

/** A member's card design (the default for a card without one). */
function card_design(string $userId): array
{
    $saved = json_decode((string) db_value('SELECT card_design FROM member_profiles WHERE user_id = ?', [$userId]), true);
    return is_array($saved) ? card_design_clean($saved, (string) ($saved['cover'] ?? '')) : card_design_default();
}

function card_design_save(array $user, array $in): array
{
    $old = card_design($user['id']);
    $d = card_design_clean($in, $old['cover']);
    write_member_profile($user['id'], []); // makes sure the card exists
    db_run('UPDATE member_profiles SET card_design = ? WHERE user_id = ?', [json_encode($d, JSON_UNESCAPED_SLASHES), $user['id']]);
    add_audit('Card design saved', $user['id']);
    return $d;
}

/** [accent, light accent] of a design: a theme's, or the member's own colour (made light enough for dark text on it). */
function card_design_colors(array $d): array
{
    if ($d['accent'] === '') {
        return [CARD_THEMES[$d['theme']][0], CARD_THEMES[$d['theme']][1]];
    }
    $rgb = sscanf($d['accent'], '#%02x%02x%02x');
    for ($i = 0; $i < 10 && (0.2126 * $rgb[0] + 0.7152 * $rgb[1] + 0.0722 * $rgb[2]) / 255 < 0.5; $i++) {
        $rgb = array_map(fn ($c) => (int) round($c + (255 - $c) * 0.15), $rgb);
    }
    $light = array_map(fn ($c) => (int) round($c + (255 - $c) * 0.45), $rgb);
    $hex = fn (array $c) => sprintf('#%02x%02x%02x', ...$c);
    return [$hex($rgb), $hex($light)];
}

/** The blocks to show, in order. */
function card_design_blocks(array $d): array
{
    return array_values(array_map(fn ($b) => $b['id'], array_filter($d['blocks'], fn ($b) => $b['show'])));
}

function card_cover_upload(array $user, array $file): array
{
    $d = card_design($user['id']);
    $path = image_upload_store($file, CARD_COVER_DIR);
    $old = $d['cover'];
    $d['cover'] = $path;
    write_member_profile($user['id'], []);
    db_run('UPDATE member_profiles SET card_design = ? WHERE user_id = ?', [json_encode($d, JSON_UNESCAPED_SLASHES), $user['id']]);
    card_image_delete($old, CARD_COVER_DIR);
    add_audit('Card cover photo uploaded', $user['id']);
    return ['cover' => $path, 'url' => media_url($path)];
}

function card_cover_remove(array $user): void
{
    $d = card_design($user['id']);
    $old = $d['cover'];
    $d['cover'] = '';
    db_run('UPDATE member_profiles SET card_design = ? WHERE user_id = ?', [json_encode($d, JSON_UNESCAPED_SLASHES), $user['id']]);
    card_image_delete($old, CARD_COVER_DIR);
    add_audit('Card cover photo removed', $user['id']);
}

/** Delete a picture these pages uploaded once nothing uses it. */
function card_image_delete(string $path, string $dir): void
{
    if (!image_upload_is_ours($path, $dir)) {
        return;
    }
    $like = '%' . $path . '%';
    if (db_value('SELECT 1 FROM member_products WHERE image = ?', [$path]) || db_value('SELECT 1 FROM member_profiles WHERE card_design LIKE ?', [$like])) {
        return;
    }
    if (is_file(APP_ROOT . '/' . $path)) {
        @unlink(APP_ROOT . '/' . $path);
    }
}

/* =====================================================================
 * Members' products
 * =================================================================== */

function card_product_to_array(array $r): array
{
    return [
        'id' => $r['id'], 'name' => $r['name'], 'price' => (float) $r['price'], 'image' => media_url($r['image']),
        'description' => (string) $r['description'], 'stock' => $r['stock'] === null ? null : (int) $r['stock'],
        'sortOrder' => (int) $r['sort_order'], 'active' => (bool) $r['active'],
    ];
}

/** A member's products, in their order ($shownOnly: the ones on the card). */
function card_products(string $userId, bool $shownOnly = false): array
{
    return array_map('card_product_to_array', db_all('SELECT * FROM member_products WHERE user_id = ?' . ($shownOnly ? ' AND active = 1' : '') . ' ORDER BY sort_order, created_at', [$userId]));
}

/** The member's own product, or an error. */
function card_own_product(array $user, string $id): array
{
    $p = db_one('SELECT * FROM member_products WHERE id = ? AND user_id = ?', [$id, $user['id']]);
    if (!$p) {
        throw new InvalidArgumentException('That product no longer exists. Refresh the page.');
    }
    return $p;
}

/** Add ($in['id'] empty) or edit a product. $file: its picture ($_FILES['image']), needed for a new one. */
function card_product_save(array $user, array $in, ?array $file): array
{
    $id = trim((string) ($in['id'] ?? ''));
    $old = $id !== '' ? card_own_product($user, $id) : null;
    if (!$old && (int) db_value('SELECT COUNT(*) FROM member_products WHERE user_id = ?', [$user['id']]) >= CARD_PRODUCTS_MAX) {
        throw new InvalidArgumentException('You can list up to ' . CARD_PRODUCTS_MAX . ' products. Delete one to add another.');
    }
    $name = mb_substr(trim((string) ($in['name'] ?? '')), 0, 150);
    if ($name === '') {
        throw new InvalidArgumentException('Please enter the product name.');
    }
    $price = shop_money($in['price'] ?? '', 'price');
    if ($price < 1 || $price > 500000) {
        throw new InvalidArgumentException('The price must be from ₱1 to ₱500,000.');
    }
    $stockIn = trim((string) ($in['stock'] ?? ''));
    $stock = $stockIn === '' ? null : shop_whole($stockIn, 'stock', 100000);
    $description = mb_substr(trim((string) ($in['description'] ?? '')), 0, 1000);
    $active = !empty($in['active']) && $in['active'] !== 'false' && $in['active'] !== '0';
    $hasFile = $file !== null && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    if (!$old && !$hasFile) {
        throw new InvalidArgumentException('Please choose a picture of the product.');
    }
    $image = $hasFile ? image_upload_store($file, CARD_PRODUCT_DIR) : (string) $old['image'];
    if ($old) {
        db_run('UPDATE member_products SET name = ?, price = ?, image = ?, description = ?, stock = ?, active = ? WHERE id = ?',
            [$name, $price, $image, $description, $stock, $active ? 1 : 0, $id]);
        if ($image !== $old['image']) {
            card_image_delete((string) $old['image'], CARD_PRODUCT_DIR);
        }
    } else {
        $id = 'mp_' . bin2hex(random_bytes(6));
        $sort = (int) db_value('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM member_products WHERE user_id = ?', [$user['id']]);
        db_run('INSERT INTO member_products (id, user_id, name, price, image, description, stock, sort_order, active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$id, $user['id'], $name, $price, $image, $description, $stock, $sort, $active ? 1 : 0]);
    }
    return card_product_to_array(db_one('SELECT * FROM member_products WHERE id = ?', [$id]));
}

function card_product_set_active(array $user, string $id, bool $active): array
{
    card_own_product($user, $id);
    db_run('UPDATE member_products SET active = ? WHERE id = ?', [$active ? 1 : 0, $id]);
    return card_product_to_array(db_one('SELECT * FROM member_products WHERE id = ?', [$id]));
}

function card_product_delete(array $user, string $id): string
{
    $p = card_own_product($user, $id);
    db_run('DELETE FROM member_products WHERE id = ?', [$id]);
    card_image_delete((string) $p['image'], CARD_PRODUCT_DIR);
    return (string) $p['name'];
}

/** Move a product one place up ($dir -1) or down (+1) on the card. */
function card_product_move(array $user, string $id, int $dir): void
{
    card_own_product($user, $id);
    $ids = array_column(db_all('SELECT id FROM member_products WHERE user_id = ? ORDER BY sort_order, created_at', [$user['id']]), 'id');
    $i = array_search($id, $ids, true);
    $j = $i + ($dir < 0 ? -1 : 1);
    if ($i === false || $j < 0 || $j >= count($ids)) {
        return;
    }
    [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
    foreach ($ids as $n => $pid) {
        db_run('UPDATE member_products SET sort_order = ? WHERE id = ?', [$n + 1, $pid]);
    }
}

/* =====================================================================
 * Selling: settings, orders
 * =================================================================== */

/** ['feePercent' => float]: the platform fee, the share of each card sale the company keeps (10% unless an admin set another). */
function card_sales_settings(): array
{
    $saved = json_decode((string) db_value('SELECT setting_value FROM cms_settings WHERE setting_key = ?', [CARD_SALES_KEY]), true);
    $fee = is_array($saved) && is_numeric($saved['feePercent'] ?? null) ? (float) $saved['feePercent'] : CARD_SALES_DEFAULT_FEE;
    return ['feePercent' => max(0.0, min(50.0, $fee))];
}

function card_sales_save_settings(array $admin, array $in): array
{
    $v = str_replace([',', '%', ' '], '', trim((string) ($in['feePercent'] ?? '')));
    if (!is_numeric($v) || (float) $v < 0 || (float) $v > 50) {
        throw new InvalidArgumentException('Please enter the fee as a percentage from 0 to 50.');
    }
    $fee = round((float) $v, 2);
    db_run('INSERT INTO cms_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
        [CARD_SALES_KEY, json_encode(['feePercent' => $fee])]);
    add_audit('Platform fee set to ' . rtrim(rtrim(number_format($fee, 2), '0'), '.') . '%', $admin['id']);
    return card_sales_settings();
}

function card_order_to_array(array $r): array
{
    return [
        'id' => $r['id'], 'sellerId' => $r['seller_id'], 'source' => $r['source'], 'productId' => $r['product_id'], 'product' => $r['product_name'],
        'unitPrice' => (float) $r['unit_price'], 'quantity' => (int) $r['quantity'], 'amount' => (float) $r['amount'],
        'feePercent' => (float) $r['fee_percent'], 'fee' => (float) $r['fee'], 'net' => (float) $r['net_amount'],
        'buyerName' => $r['buyer_name'], 'buyerEmail' => $r['buyer_email'], 'buyerPhone' => $r['buyer_phone'], 'delivery' => $r['delivery'],
        'status' => $r['status'], 'fulfillment' => $r['fulfillment'], 'paymentNote' => (string) $r['payment_note'],
        'refundNote' => (string) $r['refund_note'],
        'createdAt' => to_ms($r['created_at']), 'paidAt' => $r['paid_at'] ? to_ms($r['paid_at']) : null,
    ];
}

/**
 * A visitor buys a product on a member's card, or on their website ($in['from']
 * 'site': the Shop section): the order waits for Maya, and Maya's page is
 * returned. $in: productId, quantity, name, email, phone, delivery, from.
 */
function card_order_start(array $in, ?array $viewer): string
{
    if (!empty($in['company_website'])) { // the spam trap only robots fill in
        throw new InvalidArgumentException('Your order could not be sent.');
    }
    if (!maya_enabled()) {
        throw new InvalidArgumentException(MAYA_UNAVAILABLE . '. Please contact the member directly.');
    }
    $p = db_one('SELECT p.*, u.name AS seller_name, u.status AS seller_status FROM member_products p JOIN users u ON u.id = p.user_id WHERE p.id = ?', [(string) ($in['productId'] ?? '')]);
    if (!$p || !$p['active'] || $p['seller_status'] !== 'active') {
        throw new InvalidArgumentException('This product is no longer available.');
    }
    $account = db_value('SELECT id FROM mlm_accounts WHERE user_id = ? ORDER BY head_number, id LIMIT 1', [$p['user_id']]);
    if ($account === null) {
        throw new InvalidArgumentException('This member can\'t receive payments yet. Please contact them directly.');
    }
    $qty = (int) ($in['quantity'] ?? 1);
    $max = $p['stock'] === null ? CARD_ORDER_MAX_QTY : min(CARD_ORDER_MAX_QTY, (int) $p['stock']);
    if ($max < 1) {
        throw new InvalidArgumentException('Sorry, ' . $p['name'] . ' is sold out.');
    }
    if ($qty < 1 || $qty > $max) {
        throw new InvalidArgumentException('Please choose a quantity from 1 to ' . $max . '.');
    }
    $name = mb_substr(trim((string) ($in['name'] ?? '')), 0, 150);
    $email = strtolower(mb_substr(trim((string) ($in['email'] ?? '')), 0, 190));
    $phone = mb_substr(trim((string) ($in['phone'] ?? '')), 0, 60);
    $delivery = mb_substr(trim((string) ($in['delivery'] ?? '')), 0, 500);
    if ($name === '') {
        throw new InvalidArgumentException('Please enter your name.');
    }
    if (!valid_email($email)) {
        throw new InvalidArgumentException('Please enter a valid email address for your receipt.');
    }
    if (!preg_match('/\d{7,}/', preg_replace('/\D/', '', $phone))) {
        throw new InvalidArgumentException('Please enter your mobile number.');
    }
    $amount = round((float) $p['price'] * $qty, 2);
    $feePercent = card_sales_settings()['feePercent'];
    $fee = round($amount * $feePercent / 100, 2);
    // Bought on the member's website (back there from Maya), while it is still published; otherwise on the card.
    $source = ($in['from'] ?? '') === 'site' && site_is_live((string) $p['user_id']) ? 'site' : 'card';
    $id = 'CO-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
    db_run('INSERT INTO card_orders (id, seller_id, source, account_id, product_id, product_name, unit_price, quantity, amount, fee_percent, fee, net_amount, buyer_name, buyer_email, buyer_phone, delivery)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [$id, $p['user_id'], $source, $account, $p['id'], $p['name'], $p['price'], $qty, $amount, $feePercent, $fee, $amount - $fee, $name, $email, $phone, $delivery]);
    $req = [
        'itemType' => 'card_order', 'item' => ['name' => mb_substr($p['name'] . ' × ' . $qty . ' from ' . $p['seller_name'], 0, 255), 'price' => $amount], 'invoice' => null,
        'name' => $name, 'email' => $email, 'phone' => $phone,
    ];
    try {
        return maya_start_checkout($req, $viewer, null, $id)['redirectUrl'];
    } catch (Throwable $e) {
        db_run('UPDATE card_orders SET status = "cancelled" WHERE id = ? AND status = "awaiting_payment"', [$id]);
        throw $e;
    }
}

/**
 * Where Maya sends a buyer back to, which shows the order's outcome: the
 * member's card, or their website for an order placed there (on the member's
 * domain when the buyer was on it, else the site's portal address).
 */
function card_order_back_url(string $orderId, string $rrn, string $result): string
{
    $o = db_one('SELECT seller_id, source FROM card_orders WHERE id = ?', [$orderId]);
    $seller = (string) ($o['seller_id'] ?? '');
    $query = http_build_query(['maya' => $rrn, 'result' => $result]);
    if (($o['source'] ?? '') === 'site') {
        $host = strtolower(trim((string) ($_SERVER['HTTP_HOST'] ?? '')));
        if (domain_owner_for_host($host) === $seller) { // checked against the member's own domains
            $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') === '443');
            return ($https ? 'https' : 'http') . '://' . $host . base_path() . '/?' . $query . '#site-order';
        }
        return site_portal_url($seller) . '&' . $query . '#site-order';
    }
    $card = card_share_url(get_member_profile($seller), false);
    return $card . (str_contains($card, '?') ? '&' : '?') . $query . '#card-order';
}

/**
 * Maya confirmed a card order (called by maya_sync() inside its transaction):
 * the order is paid, the stock goes down and the member's balance goes up.
 * Returns true when this call recorded it.
 */
function card_order_paid(string $orderId, string $note): bool
{
    $o = db_one('SELECT * FROM card_orders WHERE id = ? FOR UPDATE', [$orderId]);
    if (!$o || !in_array($o['status'], ['awaiting_payment', 'cancelled'], true)) {
        return false;
    }
    db_run('UPDATE card_orders SET status = "paid", paid_at = NOW(), payment_note = ? WHERE id = ?', [mb_substr($note, 0, 255), $orderId]);
    if ($o['product_id'] !== null) {
        db_run('UPDATE member_products SET stock = GREATEST(0, CAST(stock AS SIGNED) - ?) WHERE id = ? AND stock IS NOT NULL', [(int) $o['quantity'], $o['product_id']]);
    }
    $account = $o['account_id'] !== null && db_value('SELECT 1 FROM mlm_accounts WHERE id = ?', [$o['account_id']])
        ? $o['account_id'] : db_value('SELECT id FROM mlm_accounts WHERE user_id = ? ORDER BY head_number, id LIMIT 1', [$o['seller_id']]);
    if ($account !== null) {
        db_run('UPDATE mlm_accounts SET available_balance = available_balance + ? WHERE id = ?', [(float) $o['net_amount'], $account]);
        mlm_add_tx((string) $account, 'card_sale', ($o['source'] === 'site' ? 'Website sale: ' : 'Card sale: ') . mb_substr($o['product_name'], 0, 80) . ' × ' . $o['quantity'], (float) $o['net_amount'], 'in', 'completed', $orderId,
            'Paid by ' . $o['buyer_name'] . ': ₱' . num((float) $o['amount']) . ((float) $o['fee'] > 0 ? ' less ₱' . num((float) $o['fee']) . ' platform fee' : '') . '.');
        if ($account !== $o['account_id']) {
            db_run('UPDATE card_orders SET account_id = ? WHERE id = ?', [$account, $orderId]);
        }
    }
    return true;
}

/** After a card order is paid (outside the payment's transaction): the member is told and the buyer joins their Contacts. */
function card_order_after_paid(string $orderId): void
{
    $o = db_one('SELECT * FROM card_orders WHERE id = ?', [$orderId]);
    if (!$o) {
        return;
    }
    $onSite = $o['source'] === 'site';
    if ($o['account_id'] !== null) {
        mlm_notify((string) $o['account_id'], ($onSite ? 'New website order: ' : 'New card order: ') . mb_substr($o['product_name'], 0, 120),
            $o['buyer_name'] . ' paid ₱' . num((float) $o['amount']) . ' for ' . $o['product_name'] . ' × ' . $o['quantity'] . ' (' . $orderId . '). ₱'
            . num((float) $o['net_amount']) . ' is in your Available Balance. See Backoffice → My Products for delivery details.', 'shop');
    }
    $note = date('M j, Y') . ': bought ' . $o['product_name'] . ' × ' . $o['quantity'] . ' (₱' . num((float) $o['amount']) . ') on my ' . ($onSite ? 'website' : 'card') . ', order ' . $orderId . '.';
    $existing = db_one('SELECT id, notes FROM crm_contacts WHERE user_id = ? AND email = ? ORDER BY id LIMIT 1', [$o['seller_id'], $o['buyer_email']]);
    try {
        if ($existing) {
            db_run('UPDATE crm_contacts SET notes = ?, phone = IF(phone = "", ?, phone) WHERE id = ?',
                [mb_substr(trim($existing['notes'] . "\n\n" . $note), 0, 5000), $o['buyer_phone'], $existing['id']]);
        } else {
            crm_save_contact(['id' => $o['seller_id']], ['name' => $o['buyer_name'], 'email' => $o['buyer_email'], 'phone' => $o['buyer_phone'],
                'source' => $onSite ? 'website' : 'card', 'stage' => 'new', 'notes' => $note, 'autoFollow' => false]);
        }
    } catch (Throwable $e) {
        error_log('[cards] buyer of ' . $orderId . ' not added to contacts: ' . $e->getMessage()); // e.g. the contact list is full
    }
    add_audit(($onSite ? 'Website order paid: ' : 'Card order paid: ') . $orderId . ' (₱' . num((float) $o['amount']) . ')', $o['seller_id']);
}

/**
 * Back on the card or website from Maya (?maya=<rrn>): the order's state
 * ('paid', 'pending' or 'failed') for this member, or null.
 */
function card_order_return(string $rrn, string $result, string $sellerId): ?array
{
    if (!preg_match('/^MAYA-[0-9A-F]{24}$/', $rrn)) {
        return null;
    }
    $link = db_one('SELECT c.card_order_id FROM maya_checkouts c JOIN card_orders o ON o.id = c.card_order_id WHERE c.rrn = ? AND o.seller_id = ?', [$rrn, $sellerId]);
    if (!$link) {
        return null;
    }
    $row = maya_sync($rrn);
    $state = maya_state($row);
    if ($state === 'pending' && in_array($result, ['cancel', 'failure'], true)) {
        $state = 'failed';
    }
    $status = $row['status'] !== 'CREATED' && !str_starts_with($row['status'], 'PENDING') ? $row['status'] : ($result === 'cancel' ? 'PAYMENT_CANCELLED' : 'PAYMENT_FAILED');
    $o = db_one('SELECT * FROM card_orders WHERE id = ?', [$link['card_order_id']]);
    return ['state' => $state, 'rrn' => $rrn, 'order' => card_order_to_array($o), 'message' => $state === 'failed' ? maya_failure_message($status) : ''];
}

/** A member's paid (and refunded) card orders, newest first. */
function card_orders_for(string $userId, int $limit = 100): array
{
    $limit = max(1, min(500, $limit));
    return array_map('card_order_to_array', db_all("SELECT * FROM card_orders WHERE seller_id = ? AND status IN ('paid', 'refunded') ORDER BY paid_at DESC, created_at DESC LIMIT $limit", [$userId]));
}

function card_order_set_fulfillment(array $user, string $id, string $value): array
{
    if (!isset(CARD_FULFILLMENT[$value])) {
        throw new InvalidArgumentException('Choose New, Shipped or Completed.');
    }
    $o = db_one('SELECT * FROM card_orders WHERE id = ? AND seller_id = ? AND status = "paid"', [$id, $user['id']]);
    if (!$o) {
        throw new InvalidArgumentException('That order can\'t be changed. Refresh the page.');
    }
    db_run('UPDATE card_orders SET fulfillment = ? WHERE id = ?', [$value, $id]);
    return card_order_to_array(db_one('SELECT * FROM card_orders WHERE id = ?', [$id]));
}

/** Every member's card sales for Management Admin, newest first, with totals. */
function card_sales_admin(int $limit = 100): array
{
    $limit = max(1, min(500, $limit));
    $rows = db_all("SELECT o.*, u.name AS seller_name FROM card_orders o LEFT JOIN users u ON u.id = o.seller_id WHERE o.status IN ('paid', 'refunded') ORDER BY o.paid_at DESC, o.created_at DESC LIMIT $limit");
    $totals = db_one("SELECT COUNT(*) AS n, COALESCE(SUM(amount), 0) AS gross, COALESCE(SUM(fee), 0) AS fees, COALESCE(SUM(net_amount), 0) AS net FROM card_orders WHERE status = 'paid'");
    return [
        'orders' => array_map(fn ($r) => card_order_to_array($r) + ['sellerName' => (string) ($r['seller_name'] ?? '(deleted member)')], $rows),
        'totals' => ['count' => (int) $totals['n'], 'gross' => (float) $totals['gross'], 'fees' => (float) $totals['fees'], 'net' => (float) $totals['net']],
    ];
}

/**
 * The buyer was refunded in Maya Business Manager: record it. The member's
 * balance gives back what the sale added (it can go below zero; later income
 * makes up for it), and the stock returns.
 */
function card_order_refund(array $admin, string $id, string $note): array
{
    $note = mb_substr(trim($note), 0, 255);
    if (mb_strlen($note) < 3) {
        throw new InvalidArgumentException('Please write why, or the Maya refund reference.');
    }
    $o = db_tx(function () use ($id, $note) {
        $o = db_one('SELECT * FROM card_orders WHERE id = ? FOR UPDATE', [$id]);
        if (!$o || $o['status'] !== 'paid') {
            throw new InvalidArgumentException('Only a paid order can be refunded. Refresh the page.');
        }
        db_run('UPDATE card_orders SET status = "refunded", refunded_at = NOW(), refund_note = ? WHERE id = ?', [$note, $id]);
        if ($o['account_id'] !== null && db_value('SELECT 1 FROM mlm_accounts WHERE id = ?', [$o['account_id']])) {
            db_run('UPDATE mlm_accounts SET available_balance = available_balance - ? WHERE id = ?', [(float) $o['net_amount'], $o['account_id']]);
            mlm_add_tx((string) $o['account_id'], 'card_refund', 'Card sale refunded: ' . mb_substr($o['product_name'], 0, 80), (float) $o['net_amount'], 'out', 'completed', $id . '-R', 'Refunded to ' . $o['buyer_name'] . ': ' . $note);
        }
        if ($o['product_id'] !== null) {
            db_run('UPDATE member_products SET stock = stock + ? WHERE id = ? AND stock IS NOT NULL', [(int) $o['quantity'], $o['product_id']]);
        }
        return db_one('SELECT * FROM card_orders WHERE id = ?', [$id]);
    });
    if ($o['account_id'] !== null) {
        mlm_notify((string) $o['account_id'], 'Card order refunded: ' . mb_substr($o['product_name'], 0, 120),
            $id . ' (' . $o['buyer_name'] . ') was refunded: ₱' . num((float) $o['net_amount']) . ' was taken back from your Available Balance. ' . $note, 'shop');
    }
    add_audit('Card order refunded: ' . $id . ' (₱' . num((float) $o['amount']) . '): ' . $note, $admin['id']);
    return card_order_to_array($o);
}
