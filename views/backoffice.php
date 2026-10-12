<?php
/**
 * Member Backoffice & CMS (reference component "Mn").
 *
 * Loads every dataset once and renders the nine tabs; tab switching is
 * client-side ([data-tab-group="bo"], URL param "tab"). Tab 8, Contacts
 * (views/backoffice/contacts.php), and tab 9, My Website
 * (views/backoffice/website.php), load their own data.
 *
 * ?member=usr_x (superadmin only, from Management Admin > Users) shows that
 * member's backoffice read-only: the tab partials get the member as $user,
 * and assets/js/views/bo-readonly.js blocks every change. The admin's own session, header and
 * selected head are untouched; $user is restored at the end of this file.
 *
 * Variables available to tab partials in views/backoffice/*.php:
 *   $user, $account, $accounts, $complan, $profile, $shareUrl, $details,
 *   $transactions, $encashments, $codes, $notifications, $logs,
 *   $genealogy, $unilevel, $products, $cart, $orders, $cms, $boTab
 */
if (!$user) {
    $gateTitle = 'Sign in to access the Member Backoffice';
    $gateText = 'Your binary compensation network, genealogy tree, commission wallet and 1EVOAI smart card hub are available after executive sign-in.';
    require __DIR__ . '/partials/gate.php';
    return;
}

$boAdmin = $user;
$boViewing = null;
if (is_admin($user) && is_string($_GET['member'] ?? null) && $_GET['member'] !== $user['id']) {
    $boViewing = db_one('SELECT * FROM users WHERE id = ?', [$_GET['member']]);
    $boAccounts = $boViewing ? mlm_member_accounts($boViewing['id']) : [];
    if (!$boViewing || !$boAccounts) {
        $gateTitle = $boViewing ? $boViewing['name'] . ' has no backoffice account yet' : 'This user doesn\'t exist';
        $gateText = $boViewing ? 'A backoffice account (head) is created when the member signs up or first opens the Member Backoffice.' : 'The account may have been deleted. Go back to Management Admin → Users.';
        require __DIR__ . '/partials/gate.php';
        return;
    }
}

if ($boViewing) {
    // Read-only view of a member: their heads, profile and data.
    $user = $boViewing;
    $accounts = $boAccounts;
    $headParam = is_string($_GET['head'] ?? null) ? $_GET['head'] : '';
    $account = $accounts[0];
    foreach ($accounts as $a) {
        if ($a['id'] === $headParam) {
            $account = $a;
        }
    }
    $boHeadUrl = fn (string $head): string => url('backoffice', ['member' => $boViewing['id'], 'head' => $head]);
} else {
    $accounts = mlm_accounts_for_user($user);
    $account = mlm_active_account($user);
}
$complan = mlm_complan();
$profile = get_member_profile($user['id']);
$shareUrl = card_share_url($profile);
$details = get_account_details($user['id']);
$transactions = mlm_transactions($account['id']);
$encashments = mlm_encashments($account['id']);
$codes = mlm_codes($account['id']);
$notifications = mlm_notifications($account['id']);
$logs = mlm_logs($account, 20);
$genealogy = mlm_genealogy_tree($account['id']);
$unilevel = mlm_unilevel_tree($account['id']);
// Back from Maya with a shop order (?maya=<rrn>): ask Maya first, so stock, cart and orders are current.
$shopMaya = isset($_GET['maya']) && is_string($_GET['maya']) ? mlm_shop_maya_return($account, $_GET['maya'], isset($_GET['result']) && is_string($_GET['result']) ? $_GET['result'] : '') : null;
$products = mlm_products();
$cart = mlm_cart($account['id']);
$orders = mlm_orders($account['id']);
$cms = get_cms();

$boTabs = [
    ['id' => 'dashboard', 'label' => '1. Dashboard', 'icon' => 'layout-dashboard'],
    ['id' => 'my_account', 'label' => '2. My Account', 'icon' => 'user-check'],
    ['id' => 'network', 'label' => '3. Network', 'icon' => 'git-fork', 'badge' => (string) ($account['leftCount'] + $account['rightCount'])],
    ['id' => 'transactions', 'label' => '4. Transactions', 'icon' => 'arrow-up-down'],
    ['id' => 'reports', 'label' => '5. Reports', 'icon' => 'chart-column'],
    ['id' => 'shop', 'label' => '6. Shop', 'icon' => 'shopping-bag'],
    ['id' => 'digital_card', 'label' => '7. Digital Card CMS', 'icon' => 'credit-card', 'badge' => 'NFC'],
    ['id' => 'contacts', 'label' => '8. Contacts', 'icon' => 'users', 'badge' => number_format((int) db_value('SELECT COUNT(*) FROM crm_contacts WHERE user_id = ?', [$user['id']]))],
    ['id' => 'website', 'label' => '9. My Website', 'icon' => 'monitor', 'badge' => !site_unlocked($user['id']) ? 'Locked' : (site_is_live($user['id']) ? 'Live' : 'Draft')],
];
$boTab = (string) ($_GET['tab'] ?? 'dashboard');
if (!in_array($boTab, array_column($boTabs, 'id'), true)) {
    $boTab = 'dashboard';
}
$tabOn = 'bg-gradient-to-r from-[#ffda72] to-[#f3bd35] text-black shadow-md';
$tabOff = 'text-zinc-400 hover:text-white hover:bg-zinc-900';
$badgeOn = 'bg-black/25 text-black';
$badgeOff = 'bg-[#f3bd35]/20 text-[#f3bd35]';

$pageData['backoffice'] = [
    'account' => $account,
    'complan' => $complan,
    'profile' => $profile,
    'shareUrl' => $shareUrl,
    'cart' => $cart,
    'transactions' => $transactions,
    // Viewing a member: bo-readonly.js blocks every change and turns head switching into links.
    'readOnly' => $boViewing ? ['name' => $boViewing['name'], 'member' => $boViewing['id'], 'head' => $account['id']] : null,
];
if ($boViewing) {
    $pageScripts[] = 'assets/js/views/bo-readonly.js'; // first: guards every later script
}
foreach (['dashboard', 'my-account', 'network', 'transactions', 'reports', 'shop', 'digital-card', 'domains', 'contacts', 'website'] as $js) {
    if (is_file(APP_ROOT . '/assets/js/views/bo-' . $js . '.js')) {
        $pageScripts[] = 'assets/js/views/bo-' . $js . '.js';
    }
}
$pageScripts[] = 'assets/js/views/backoffice.js';
$pageScripts[] = 'assets/js/checkout.js';
?>
<div class="max-w-7xl mx-auto space-y-6">
    <?php if ($boViewing): ?>
        <div class="p-4 rounded-2xl bg-amber-950/40 border border-amber-600/40 text-amber-200 text-xs flex flex-col md:flex-row md:items-center justify-between gap-3" role="status">
            <p class="flex items-start gap-2">
                <?= icon('eye', 'w-4 h-4 shrink-0 mt-0.5') ?>
                <span>You're viewing <strong class="text-white"><?= e($boViewing['name']) ?></strong>'s Member Backoffice (<?= e($boViewing['email']) ?>) as Management Admin. This view is read-only: nothing can be changed here.</span>
            </p>
            <div class="flex items-center gap-2 shrink-0">
                <a href="<?= e(url('admin', ['tab' => 'users'])) ?>" class="px-3 py-1.5 rounded-xl bg-zinc-900 hover:bg-zinc-800 border border-zinc-700 text-zinc-200 font-semibold transition-colors flex items-center gap-1"><?= icon('user-check', 'w-3.5 h-3.5') ?><span>Back to Users</span></a>
                <a href="<?= e(url('backoffice')) ?>" class="px-3 py-1.5 rounded-xl bg-[#f3bd35] hover:bg-[#ffda72] text-black font-bold transition-colors">My Backoffice</a>
            </div>
        </div>
    <?php endif; ?>
    <div class="p-5 rounded-3xl bg-gradient-to-r from-[#18130a] via-[#120f09] to-[#0c0c0c] border border-[#f3bd35]/40 shadow-2xl flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 flex-wrap mb-1.5">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-[#f3bd35]/20 border border-[#f3bd35]/40 text-[#f3bd35] text-[10px] font-extrabold tracking-widest uppercase">
                    <?= icon('zap', 'w-3.5 h-3.5') ?>Binary Complan (₱<?= e(rtrim(rtrim(number_format($complan['entryFee'] / 1000, 1), '0'), '.')) ?>k Entry · ₱<?= num($complan['directReferralBonus']) ?> Direct · ₱<?= num($complan['pairingBonus']) ?> Pair)
                </span>
                <span class="text-[10px] px-2 py-0.5 rounded-full bg-emerald-500/15 border border-emerald-500/30 text-emerald-400 font-bold"><?= $complan['maxPairsPerCycle'] ?> Pairs/Cycle · <?= $complan['cyclesPerDay'] ?> Cycle<?= $complan['cyclesPerDay'] === 1 ? '' : 's' ?>/Day</span>
            </div>
            <h2 class="text-xl sm:text-2xl font-black text-white flex items-center gap-2">88MMM Member Backoffice &amp; CMS<?= icon('shield-check', 'w-5 h-5 text-[#f3bd35]') ?></h2>
            <p class="text-xs text-zinc-400 mt-0.5">Binary compensation network, geneology tree, commission encashments, and 1EVOAI smart card hub.</p>
        </div>
        <div class="flex items-center gap-3">
            <div class="relative" data-dropdown>
                <button type="button" data-dropdown-toggle aria-expanded="false" class="flex items-center gap-2 px-3 py-1.5 rounded-xl bg-zinc-900 border border-[#f3bd35]/40 hover:border-[#f3bd35] text-xs text-white transition-all shadow">
                    <div class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></div>
                    <div class="text-left">
                        <span class="text-[10px] text-zinc-400 block -mb-0.5">Active Head</span>
                        <span class="font-bold text-[#ffda72] font-mono"><?= e($account['id']) ?></span>
                    </div>
                    <?= icon('chevron-down', 'w-3.5 h-3.5 text-zinc-400 ml-1') ?>
                </button>
                <div data-dropdown-menu hidden class="absolute right-0 mt-2 w-64 bg-[#141414] border border-[#f3bd35]/40 rounded-2xl shadow-2xl p-2 z-50 animate-in fade-in">
                    <div class="text-[10px] font-bold uppercase tracking-wider text-zinc-400 px-2.5 py-1 border-b border-zinc-800 mb-1">Switch Active Account / Head</div>
                    <?php foreach ($accounts as $a): ?>
                        <?php if ($boViewing): ?>
                            <a href="<?= e($boHeadUrl($a['id'])) ?>" class="w-full text-left px-3 py-2 rounded-xl text-xs flex items-center justify-between transition-colors <?= $a['id'] === $account['id'] ? 'bg-[#f3bd35] text-black font-bold' : 'text-zinc-200 hover:bg-zinc-800' ?>">
                                <div>
                                    <div class="font-bold"><?= e($a['accountName']) ?></div>
                                    <div class="text-[10px] opacity-75 font-mono"><?= e($a['id']) ?> · Bal: <?= peso($a['availableBalance']) ?></div>
                                </div>
                            </a>
                        <?php else: ?>
                        <button type="button" data-set-account="<?= e($a['id']) ?>" class="w-full text-left px-3 py-2 rounded-xl text-xs flex items-center justify-between transition-colors <?= $a['id'] === $account['id'] ? 'bg-[#f3bd35] text-black font-bold' : 'text-zinc-200 hover:bg-zinc-800' ?>">
                            <div>
                                <div class="font-bold"><?= e($a['accountName']) ?></div>
                                <div class="text-[10px] opacity-75 font-mono"><?= e($a['id']) ?> · Bal: <?= peso($a['availableBalance']) ?></div>
                            </div>
                        </button>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="p-2 px-3 rounded-xl bg-zinc-950 border border-zinc-800 text-right hidden sm:block">
                <span class="text-[9px] uppercase font-bold text-zinc-500 block">Available Wallet</span>
                <span class="text-xs font-black text-emerald-400 font-mono"><?= peso($account['availableBalance']) ?></span>
            </div>
        </div>
    </div>

    <div class="flex items-center gap-1.5 p-1.5 bg-[#101010] border border-[#f3bd35]/25 rounded-2xl overflow-x-auto shadow-md" data-tab-group="bo" data-param="tab" role="tablist">
        <?php foreach ($boTabs as $t): $on = $t['id'] === $boTab; ?>
            <button type="button" data-tab="<?= e($t['id']) ?>" data-group="bo" data-on="<?= $tabOn ?>" data-off="<?= $tabOff ?>" class="flex items-center gap-2 px-3.5 py-2.5 rounded-xl text-xs font-bold whitespace-nowrap transition-all <?= $on ? $tabOn : $tabOff ?>">
                <?= icon($t['icon'], 'w-4 h-4') ?>
                <span><?= e($t['label']) ?></span>
                <?php if (!empty($t['badge'])): ?>
                    <span data-on="<?= $badgeOn ?>" data-off="<?= $badgeOff ?>" data-tab-badge class="text-[9px] px-1.5 py-0.2 rounded-full uppercase font-bold <?= $on ? $badgeOn : $badgeOff ?>"><?= e($t['badge']) ?></span>
                <?php endif; ?>
            </button>
        <?php endforeach; ?>
    </div>

    <div class="pt-1">
        <?php foreach ($boTabs as $t): ?>
            <section data-tab-panel="<?= e($t['id']) ?>" data-group="bo"<?= $t['id'] === $boTab ? '' : ' hidden' ?>>
                <?php require __DIR__ . '/backoffice/' . $t['id'] . '.php'; ?>
            </section>
        <?php endforeach; ?>
    </div>
</div>
<?php
// The page header and EVO.user belong to the signed-in admin again.
$user = $boAdmin;
