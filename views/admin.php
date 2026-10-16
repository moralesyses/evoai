<?php
/**
 * 88MMM Management Admin (reference component "zn").
 *
 * Variables available to tab partials in views/admin/*.php:
 *   $user, $users, $profiles, $payments, $leads, $analytics, $cms, $auditLogs, $adminTab
 *   (views/admin/users.php loads its own page of users.)
 */
if (!$user) {
    $gateTitle = 'Sign in to access Management Admin';
    $gateText = 'The System Administration Console is restricted to the Admin / Founder role. Please sign in with an administrator account.';
    require __DIR__ . '/partials/gate.php';
    return;
}
$isAdmin = is_admin($user);
$adminTabs = [
    ['id' => 'analytics', 'label' => 'System Analytics', 'icon' => 'chart-column'],
    ['id' => 'members', 'label' => 'Member Access & Roles', 'icon' => 'users', 'badge' => 'Roster'],
    ['id' => 'users', 'label' => 'Users', 'icon' => 'user-check', 'badge' => $isAdmin ? number_format((int) db_value('SELECT COUNT(*) FROM users')) : null],
    ['id' => 'codes', 'label' => 'Activation Codes', 'icon' => 'key', 'badge' => $isAdmin ? number_format((int) db_value('SELECT COUNT(*) FROM mlm_codes WHERE status = "active"')) : null],
    ['id' => 'complan', 'label' => 'Rewards Plan', 'icon' => 'git-fork', 'badge' => $isAdmin ? mlm_complan()['pairsPerDay'] . ' matches/day' : null],
    ['id' => 'encashments', 'label' => 'Withdrawals', 'icon' => 'wallet', 'badge' => $isAdmin && ($encashPending = (int) db_value('SELECT COUNT(*) FROM mlm_encashments WHERE status = "pending"')) > 0 ? $encashPending . ' pending' : null],
    ['id' => 'products', 'label' => 'Shop Products', 'icon' => 'shopping-bag', 'badge' => $isAdmin ? number_format((int) db_value('SELECT COUNT(*) FROM mlm_products WHERE active = 1')) : null],
    ['id' => 'academy', 'label' => 'Mentor Academy', 'icon' => 'graduation-cap', 'badge' => $isAdmin ? number_format((int) db_value('SELECT COUNT(*) FROM academy_materials WHERE published = 1')) : null],
    ['id' => 'payments', 'label' => 'Online Payments & Ledger', 'icon' => 'credit-card', 'badge' => 'Live'],
    ['id' => 'domains', 'label' => '.com Domains', 'icon' => 'globe', 'badge' => $isAdmin && ($domainsWaiting = (int) db_value('SELECT COUNT(*) FROM domain_orders WHERE status = "paid_failed_registration"')) > 0 ? $domainsWaiting . ' to retry' : null],
    ['id' => 'leads', 'label' => 'Global Leads CRM', 'icon' => 'file-spreadsheet'],
    ['id' => 'global_cms', 'label' => 'Portal CMS & Pricing', 'icon' => 'sliders-vertical'],
    ['id' => 'security', 'label' => 'Security & Audit Log', 'icon' => 'key-round'],
];
$adminTab = (string) ($_GET['tab'] ?? 'members');
if (!in_array($adminTab, array_column($adminTabs, 'id'), true)) {
    $adminTab = 'members';
}
$tabOn = 'bg-gradient-to-r from-[#ffda72] to-[#f3bd35] text-black shadow-md';
$tabOff = 'text-zinc-400 hover:text-white hover:bg-zinc-900';
$badgeOn = 'bg-black/20 text-black';
$badgeOff = 'bg-[#f3bd35]/20 text-[#f3bd35]';

if ($isAdmin) {
    $users = list_users();
    $profiles = all_member_profiles();
    $payments = list_payments();
    $leads = list_leads();
    $analytics = get_analytics();
    $cms = get_cms();
    $auditLogs = list_audit(50);
    $pageData['admin'] = ['users' => $users, 'profiles' => $profiles, 'payments' => $payments, 'leads' => $leads, 'cms' => $cms];
    foreach (['analytics', 'members', 'users', 'codes', 'complan', 'encashments', 'products', 'academy', 'payments', 'domains', 'leads', 'cms', 'security'] as $js) {
        if (is_file(APP_ROOT . '/assets/js/views/admin-' . $js . '.js')) {
            $pageScripts[] = 'assets/js/views/admin-' . $js . '.js';
        }
    }
}
?>
<div class="max-w-6xl mx-auto space-y-6">
    <div class="p-5 rounded-3xl bg-gradient-to-r from-[#18110b] via-[#120f09] to-[#0c0c0c] border border-[#f3bd35]/40 shadow-2xl flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-[#f3bd35]/20 border border-[#f3bd35]/40 text-[#f3bd35] text-[10px] font-extrabold tracking-widest uppercase mb-1.5"><?= icon('lock', 'w-3.5 h-3.5') ?>System Administration Console</div>
            <h2 class="text-xl font-black text-white flex items-center gap-2">88MMM Management Admin<?= icon('shield-check', 'w-5 h-5 text-[#f3bd35]') ?></h2>
            <p class="text-xs text-zinc-400 mt-0.5">Founder &amp; Master Administration for 88MMM Circle, global portal, and membership governance.</p>
        </div>
        <div class="flex items-center gap-2">
            <span class="px-3 py-1 rounded-full bg-zinc-900 border border-zinc-700 text-xs text-zinc-300">Logged in as: <strong class="text-[#ffda72]"><?= e($user['name']) ?></strong> (<?= e($user['role']) ?>)</span>
        </div>
    </div>

    <?php if (!$isAdmin): ?>
        <div class="p-4 rounded-2xl bg-amber-950/40 border border-amber-600/40 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 text-xs">
            <div class="flex items-center gap-2.5 text-amber-200">
                <?= icon('shield-alert', 'w-5 h-5 text-amber-400 flex-shrink-0') ?>
                <div>
                    <span class="font-bold block">Restricted System Access</span>
                    <span class="text-zinc-400">You are currently viewing as <strong><?= e($user['name']) ?></strong> (<?= e($user['role']) ?>). Management Admin privileges require the Admin/Founder role.</span>
                </div>
            </div>
            <?php if (demo_mode()): ?>
                <button type="button" data-switch-user="usr_mike" class="flex items-center gap-1 px-3 py-1.5 bg-[#f3bd35] hover:bg-[#ffda72] text-black font-bold text-xs rounded-xl shadow whitespace-nowrap transition-transform active:scale-95">
                    <?= icon('user-check', 'w-3.5 h-3.5') ?><span>Switch to Admin (Mike Maranan)</span>
                </button>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="flex items-center gap-1.5 p-1.5 bg-[#101010] border border-[#f3bd35]/20 rounded-2xl overflow-x-auto" data-tab-group="admin" data-param="tab" role="tablist">
            <?php foreach ($adminTabs as $t): $on = $t['id'] === $adminTab; ?>
                <button type="button" data-tab="<?= e($t['id']) ?>" data-group="admin" data-on="<?= $tabOn ?>" data-off="<?= $tabOff ?>" class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-semibold whitespace-nowrap transition-all <?= $on ? $tabOn : $tabOff ?>">
                    <?= icon($t['icon'], 'w-4 h-4') ?>
                    <span><?= e($t['label']) ?></span>
                    <?php if (!empty($t['badge'])): ?>
                        <span data-on="<?= $badgeOn ?>" data-off="<?= $badgeOff ?>" class="text-[9px] px-1.5 py-0.2 rounded-full uppercase font-bold <?= $on ? $badgeOn : $badgeOff ?>"><?= e($t['badge']) ?></span>
                    <?php endif; ?>
                </button>
            <?php endforeach; ?>
        </div>
        <div class="pt-2">
            <?php foreach ($adminTabs as $t): ?>
                <section data-tab-panel="<?= e($t['id']) ?>" data-group="admin"<?= $t['id'] === $adminTab ? '' : ' hidden' ?>>
                    <?php require __DIR__ . '/admin/' . $t['id'] . '.php'; ?>
                </section>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
