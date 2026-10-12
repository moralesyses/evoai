<?php
/**
 * Backoffice tab 2 — My Account (reference component "En").
 *
 * Sub-tabs (client-side, [data-tab-group="acct"], URL param "acct"):
 * accounts · profile · settings · codes · nfc_card.
 * Behaviour lives in assets/js/views/bo-my-account.js.
 *
 * Uses: $user, $account, $accounts, $profile, $details, $codes.
 * Local variables are prefixed with $ma to avoid clobbering the wrapper scope.
 */
$maTabs = ['accounts', 'profile', 'settings', 'codes', 'nfc_card'];
$maTab = (string) ($_GET['acct'] ?? 'accounts');
if (!in_array($maTab, $maTabs, true)) {
    $maTab = 'accounts';
}
$maOn = 'bg-gradient-to-r from-[#ffda72] to-[#f3bd35] text-black shadow-md';
$maOff = 'text-zinc-400 hover:text-white';
$maBtn = 'flex items-center gap-2 px-4 py-2 rounded-xl font-bold whitespace-nowrap transition-all';

$maCardId = $profile['nfcCardId'] ?: 'MMC-VIP-88001';
$maUnused = count(array_filter($codes, fn ($c) => $c['status'] === 'active'));
// The code vault shows CODES_PER_PAGE codes per page, newest first.
$maCodePages = max(1, (int) ceil(count($codes) / CODES_PER_PAGE));
$maCodePage = min($maCodePages, max(1, (int) ($_GET['codes_page'] ?? 1)));
$maCodesShown = array_slice($codes, ($maCodePage - 1) * CODES_PER_PAGE, CODES_PER_PAGE);
$maCodesUrl = fn (int $to): string => url('backoffice', array_filter(['tab' => 'my_account', 'acct' => 'codes', 'codes_page' => $to > 1 ? $to : '']));
$maBalance = (float) $account['availableBalance'];

// Profile Details form (stored account details, falling back to the user / card profile).
$maVal = fn (string $k) => (string) ($details[$k] ?? '');
$maFullName = (string) ($user['name'] ?? '');
$maEmail = (string) ($user['email'] ?? '');
$maPhone = $maVal('phone') ?: (string) ($profile['phone'] ?? '');
$maWhatsapp = $maVal('whatsapp') ?: (string) ($profile['whatsapp'] ?? '');
$maOccupation = $maVal('occupation') ?: (string) ($profile['title'] ?? '');
$maGender = $maVal('gender') ?: 'Male';
$maCountry = $maVal('country') ?: 'Philippines';
$maInput = 'w-full px-3 py-2 rounded-xl bg-zinc-900 border border-zinc-700 text-white focus:outline-none focus:border-[#f3bd35]';
$maInputMono = 'w-full px-3 py-2 rounded-xl bg-zinc-900 border border-zinc-700 text-white font-mono focus:outline-none focus:border-[#f3bd35]';

// Security & 2FA.
$maHasPin = !empty($details['hasPin']);
$maTwoFactor = !empty($details['twoFactorEnabled']);

// Smart NFC card.
$maTheme = $profile['cardTheme'] ?: 'gold';
$maAction = $profile['nfcAction'] ?: 'card';
$maFrozen = ($profile['membershipStatus'] ?? '') === 'Frozen (Card Lost)';
$maCardThemes = [
    'gold' => 'bg-gradient-to-br from-[#d4af37] via-[#f7e394] to-[#a3791a] text-black border-amber-300 shadow-amber-500/20',
    'obsidian' => 'bg-gradient-to-br from-[#1c1c1c] via-[#0d0d0d] to-[#000000] text-amber-200 border-[#f3bd35]/50 shadow-black',
    'platinum' => 'bg-gradient-to-br from-[#f0f0f0] via-[#d6d6d6] to-[#999999] text-black border-zinc-300 shadow-zinc-500/20',
    'rosegold' => 'bg-gradient-to-br from-[#f4b6a9] via-[#e89b88] to-[#b35e50] text-black border-rose-300 shadow-rose-500/20',
];
$maCardClass = $maCardThemes[$maTheme] ?? $maCardThemes['rosegold'];
$maMetrics = $profile['metrics'] ?? [];
$maSlug = domain_card_domain($user['id']) ?? ($profile['customSlug'] ?: ('1evoai.com/' . str_replace('usr_', '', $user['id']))); // their own .com domain when the card uses one
$maActions = [
    ['id' => 'card', 'title' => 'Open Executive Digital Card Portal', 'desc' => 'Opens your full responsive interactive portal (' . $maSlug . ') featuring credentials, video, and syndicate links.', 'badge' => 'Recommended', 'icon' => 'smartphone'],
    ['id' => 'vcard', 'title' => 'Direct vCard Address Book Download (.vcf)', 'desc' => 'Instantly downloads your contact card directly into their iPhone / Android contacts with zero friction.', 'badge' => 'High Conversion', 'icon' => 'user'],
    ['id' => 'whatsapp', 'title' => 'Direct Encrypted VIP WhatsApp Chat', 'desc' => 'Opens a private end-to-end encrypted chat with your personal VIP concierge number.', 'badge' => 'Instant Chat', 'icon' => 'send'],
];
$maActOn = 'bg-gradient-to-r from-[#1c170d] to-zinc-900 border-[#f3bd35] ring-1 ring-[#f3bd35]/50';
$maActOff = 'bg-zinc-950/80 border-zinc-800 hover:border-zinc-700';
$maActIconOn = 'bg-[#f3bd35] text-black font-bold';
$maActIconOff = 'bg-zinc-900 text-zinc-400';
$maActBadgeOn = 'bg-[#f3bd35]/20 text-[#ffda72] border border-[#f3bd35]/40';
$maActBadgeOff = 'bg-zinc-900 text-zinc-500';
$maThemeOpts = [
    ['id' => 'gold', 'label' => 'Executive Gold', 'desc' => 'Brushed Brass', 'color' => 'bg-gradient-to-r from-[#d4af37] to-[#aa7c11] text-black'],
    ['id' => 'obsidian', 'label' => 'Obsidian Black', 'desc' => 'Matte Titanium', 'color' => 'bg-gradient-to-r from-zinc-900 to-black text-amber-300 border border-amber-500/40'],
    ['id' => 'platinum', 'label' => 'Sterling Silver', 'desc' => 'Satin Platinum', 'color' => 'bg-gradient-to-r from-zinc-300 to-zinc-500 text-black'],
    ['id' => 'rosegold', 'label' => 'Rose Gold', 'desc' => 'Electrum Alloy', 'color' => 'bg-gradient-to-r from-rose-300 to-rose-500 text-black'],
];
$maThemeOn = 'border-[#f3bd35] ring-2 ring-[#f3bd35]/40 bg-zinc-900';
$maThemeOff = 'border-zinc-800 bg-zinc-950/60 opacity-60 hover:opacity-100';
$maFreezeOn = 'bg-rose-500 text-white border-rose-400 shadow';
$maFreezeOff = 'bg-zinc-900 text-zinc-300 border-zinc-700 hover:text-white';
$maActionLabels = ['card' => 'Opening Digital Card Portal', 'vcard' => 'vCard Direct Address Book'];

$maQtyOn = 'bg-[#f3bd35] text-black shadow';
$maQtyOff = 'bg-zinc-900 text-zinc-400 hover:text-white border border-zinc-800';
?>
<div class="space-y-6" id="bo-my-account" data-profile-id="<?= e($user['id']) ?>" data-balance="<?= e($maBalance) ?>" data-theme="<?= e($maTheme) ?>" data-action="<?= e($maAction) ?>" data-frozen="<?= $maFrozen ? '1' : '0' ?>" data-serial="<?= e($maCardId) ?>">
    <div data-modal="ma-transfer-code" hidden class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-md p-4 animate-in fade-in" role="dialog" aria-modal="true" aria-labelledby="ma-transfer-title">
        <div class="w-full max-w-md rounded-3xl bg-[#141414] border border-[#f3bd35]/40 p-6 shadow-2xl relative text-left">
            <h3 id="ma-transfer-title" class="text-lg font-bold text-white mb-1">Transfer Activation Code</h3>
            <p class="text-xs text-zinc-400 mb-4">Transfer code <strong class="text-[#ffda72] font-mono" data-ma-transfer-code></strong> to a downline or prospect username.</p>
            <div data-ma-transfer-success hidden role="status" class="p-4 rounded-2xl bg-emerald-950/40 border border-emerald-500/40 text-emerald-300 text-xs text-center font-bold"></div>
            <form id="ma-transfer-form" class="space-y-4">
                <input type="hidden" name="code" value="">
                <div>
                    <label for="ma-transfer-username" class="block text-xs font-semibold text-zinc-300 mb-1">Recipient Username *</label>
                    <input id="ma-transfer-username" name="username" type="text" required maxlength="60" autocomplete="off" placeholder="e.g. sofiacruz or prospect88" class="w-full px-3 py-2 rounded-xl bg-zinc-900 border border-zinc-700 text-white text-xs focus:outline-none focus:border-[#f3bd35]">
                </div>
                <div class="flex items-center gap-2 pt-2">
                    <button type="button" data-close-modal class="flex-1 py-2.5 rounded-xl bg-zinc-900 text-zinc-300 text-xs font-semibold hover:bg-zinc-800">Cancel</button>
                    <button type="submit" disabled class="flex-1 py-2.5 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 text-white text-xs font-bold shadow hover:brightness-110 disabled:opacity-50">Send Code</button>
                </div>
            </form>
        </div>
    </div>
    <div data-modal="ma-buy-codes" hidden class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-md p-4 animate-in fade-in" role="dialog" aria-modal="true" aria-labelledby="ma-buy-title">
        <div class="w-full max-w-md rounded-3xl bg-[#141414] border border-[#f3bd35]/40 p-6 shadow-2xl relative text-left">
            <h3 id="ma-buy-title" class="text-lg font-bold text-white mb-1">Buy Executive Activation Codes</h3>
            <p class="text-xs text-zinc-400 mb-4">Cost: <strong class="text-[#ffda72]">₱2,000 PHP per code</strong> · Available Balance: <strong class="text-emerald-400 font-mono"><?= peso($maBalance) ?></strong></p>
            <div class="space-y-4">
                <div>
                    <span class="block text-xs font-semibold text-zinc-300 mb-1" id="ma-qty-label">Quantity</span>
                    <div class="flex items-center gap-2" role="radiogroup" aria-labelledby="ma-qty-label">
                        <?php foreach ([1, 3, 5, 7] as $maQ): ?>
                            <button type="button" role="radio" aria-checked="<?= $maQ === 1 ? 'true' : 'false' ?>" data-ma-qty="<?= $maQ ?>" data-on="<?= $maQtyOn ?>" data-off="<?= $maQtyOff ?>" class="flex-1 py-2 rounded-xl text-xs font-bold transition-all <?= $maQ === 1 ? $maQtyOn : $maQtyOff ?>"><?= $maQ ?>x (₱<?= num($maQ * 2000) ?>)</button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="p-3.5 rounded-2xl bg-zinc-950 border border-zinc-800 text-xs flex justify-between items-center">
                    <span class="text-zinc-400">Total Deducted:</span>
                    <span class="font-mono font-bold text-white text-sm" data-ma-qty-total>₱2,000 PHP</span>
                </div>
                <div class="flex items-center gap-2 pt-2">
                    <button type="button" data-close-modal class="flex-1 py-2.5 rounded-xl bg-zinc-900 text-zinc-300 text-xs font-semibold hover:bg-zinc-800">Cancel</button>
                    <button type="button" data-ma-buy-confirm<?= $maBalance < 2000 ? ' disabled' : '' ?> class="flex-1 py-2.5 rounded-xl bg-gradient-to-r from-[#ffda72] to-[#f3bd35] text-black text-xs font-bold shadow hover:brightness-105 disabled:opacity-50">Confirm Purchase</button>
                </div>
            </div>
        </div>
    </div>
    <div class="flex items-center gap-1.5 p-1.5 bg-[#121212] rounded-2xl border border-zinc-800 overflow-x-auto text-xs" data-tab-group="acct" data-param="acct" role="tablist">
        <button type="button" role="tab" aria-selected="<?= $maTab === 'accounts' ? 'true' : 'false' ?>" data-tab="accounts" data-group="acct" data-on="<?= $maOn ?>" data-off="<?= $maOff ?>" class="<?= $maBtn ?> <?= $maTab === 'accounts' ? $maOn : $maOff ?>">
            <?= icon('users', 'w-4 h-4') ?>
            <span>Account List (<?= count($accounts) ?> Heads)</span>
        </button>
        <button type="button" role="tab" aria-selected="<?= $maTab === 'profile' ? 'true' : 'false' ?>" data-tab="profile" data-group="acct" data-on="<?= $maOn ?>" data-off="<?= $maOff ?>" class="<?= $maBtn ?> <?= $maTab === 'profile' ? $maOn : $maOff ?>">
            <?= icon('user', 'w-4 h-4') ?>
            <span>Profile Details</span>
        </button>
        <button type="button" role="tab" aria-selected="<?= $maTab === 'settings' ? 'true' : 'false' ?>" data-tab="settings" data-group="acct" data-on="<?= $maOn ?>" data-off="<?= $maOff ?>" class="<?= $maBtn ?> <?= $maTab === 'settings' ? $maOn : $maOff ?>">
            <?= icon('settings', 'w-4 h-4') ?>
            <span>Security &amp; 2FA PIN</span>
        </button>
        <button type="button" role="tab" aria-selected="<?= $maTab === 'codes' ? 'true' : 'false' ?>" data-tab="codes" data-group="acct" data-on="<?= $maOn ?>" data-off="<?= $maOff ?>" class="<?= $maBtn ?> <?= $maTab === 'codes' ? $maOn : $maOff ?>">
            <?= icon('credit-card', 'w-4 h-4') ?>
            <span>Activation Codes (<?= $maUnused ?> Available)</span>
        </button>
        <button type="button" role="tab" aria-selected="<?= $maTab === 'nfc_card' ? 'true' : 'false' ?>" data-tab="nfc_card" data-group="acct" data-on="<?= $maOn ?>" data-off="<?= $maOff ?>" class="<?= $maBtn ?> <?= $maTab === 'nfc_card' ? $maOn : $maOff ?>">
            <?= icon('radio', 'w-4 h-4 text-[#f3bd35]') ?>
            <span>Smart NFC Card</span>
            <span class="text-[10px] px-2 py-0.5 rounded-full uppercase font-mono font-bold bg-[#f3bd35]/20 text-[#ffda72] border border-[#f3bd35]/30"><?= e($maCardId) ?></span>
        </button>
    </div>

    <div data-ma-success hidden role="status" class="p-3 rounded-2xl bg-emerald-950/40 border border-emerald-500/40 text-emerald-300 text-xs flex items-center gap-2 animate-in fade-in">
        <?= icon('check', 'w-4 h-4 text-emerald-400') ?>
        <span class="font-semibold" data-msg></span>
    </div>
    <div data-ma-nfc-msg hidden role="status" class="p-3 rounded-2xl bg-gradient-to-r from-amber-950/60 to-black border border-[#f3bd35]/60 text-[#ffda72] text-xs flex items-center gap-2 animate-in fade-in shadow-xl">
        <?= icon('radio', 'w-4 h-4 text-[#f3bd35] animate-pulse') ?>
        <span class="font-bold" data-msg></span>
    </div>

    <?php /* ------------------------------------------------ Account list */ ?>
    <div>
    <div class="space-y-4" data-tab-panel="accounts" data-group="acct"<?= $maTab === 'accounts' ? '' : ' hidden' ?>>
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-lg font-bold text-white flex items-center gap-2">
                    <?= icon('users', 'w-5 h-5 text-[#f3bd35]') ?>
                    Registered Multiple Accounts / Heads
                </h3>
                <p class="text-xs text-zinc-400 mt-0.5">Switch active account to manage genealogy, view independent earnings, and place downlines.</p>
            </div>
            <span class="text-xs font-mono text-[#ffda72] bg-[#f3bd35]/15 border border-[#f3bd35]/30 px-3 py-1 rounded-full font-bold">Current Active: <?= e($account['id']) ?></span>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <?php foreach ($accounts as $maA): $maCur = $maA['id'] === $account['id']; ?>
                <div class="p-5 rounded-3xl border transition-all relative <?= $maCur ? 'bg-gradient-to-b from-[#18140c] to-[#0f0e0b] border-[#f3bd35] shadow-xl ring-1 ring-[#f3bd35]' : 'bg-[#121212] border-zinc-800 hover:border-zinc-700' ?>">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full uppercase bg-[#f3bd35]/20 text-[#ffda72]">Head #<?= e($maA['headNumber']) ?> <?= $maA['headNumber'] === 1 ? '(Main)' : '' ?></span>
                        <span class="text-xs font-mono text-zinc-400 font-bold"><?= e($maA['id']) ?></span>
                    </div>
                    <h4 class="text-base font-bold text-white"><?= e($maA['accountName']) ?></h4>
                    <div class="text-xs text-zinc-400 mb-4 font-mono">@<?= e($maA['username']) ?></div>
                    <div class="space-y-2 p-3 rounded-2xl bg-zinc-950/80 border border-zinc-800/80 text-xs mb-4">
                        <div class="flex justify-between">
                            <span class="text-zinc-400">Available:</span>
                            <span class="font-mono font-bold text-emerald-400"><?= peso($maA['availableBalance']) ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-zinc-400">Total Income:</span>
                            <span class="font-mono text-zinc-200"><?= peso($maA['totalIncome']) ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-zinc-400">Left / Right Volume:</span>
                            <span class="font-mono text-[#f3bd35]"><?= e($maA['leftPoints']) ?> PV / <?= e($maA['rightPoints']) ?> PV</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-zinc-400">Team Size:</span>
                            <span class="text-zinc-300"><?= $maA['leftCount'] + $maA['rightCount'] ?> downlines</span>
                        </div>
                    </div>
                    <button type="button" <?= $maCur ? 'disabled' : 'data-ma-switch="' . e($maA['id']) . '"' ?> class="w-full py-2.5 rounded-xl text-xs font-bold transition-all <?= $maCur ? 'bg-[#f3bd35] text-black shadow cursor-default' : 'bg-zinc-900 hover:bg-zinc-800 text-zinc-200 border border-zinc-700' ?>"><?= $maCur ? '✓ Currently Selected Head' : 'Switch to This Head' ?></button>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <?php /* ------------------------------------------------ Profile details */ ?>
    <form id="ma-profile-form" class="space-y-6" data-tab-panel="profile" data-group="acct"<?= $maTab === 'profile' ? '' : ' hidden' ?>>
        <div class="p-5 rounded-3xl bg-[#121212] border border-zinc-800 shadow-xl space-y-4">
            <h4 class="text-xs font-bold text-[#f3bd35] uppercase tracking-wider flex items-center gap-2"><?= icon('user', 'w-4 h-4') ?> A. Personal Information</h4>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 text-xs">
                <div>
                    <label for="ma-fullName" class="block text-zinc-400 mb-1 font-semibold">Full Legal Name</label>
                    <input id="ma-fullName" name="fullName" type="text" required maxlength="150" autocomplete="name" value="<?= e($maFullName) ?>" class="<?= $maInput ?>">
                </div>
                <div>
                    <label for="ma-username" class="block text-zinc-400 mb-1 font-semibold">Username / Handle</label>
                    <input id="ma-username" type="text" value="<?= e($account['username']) ?>" disabled class="w-full px-3 py-2 rounded-xl bg-zinc-950 border border-zinc-800 text-zinc-400 font-mono">
                </div>
                <div>
                    <label for="ma-birthday" class="block text-zinc-400 mb-1 font-semibold">Birthdate</label>
                    <input id="ma-birthday" name="birthday" type="date" value="<?= e($maVal('birthday')) ?>" class="<?= $maInput ?>">
                </div>
                <div>
                    <label for="ma-gender" class="block text-zinc-400 mb-1 font-semibold">Gender</label>
                    <select id="ma-gender" name="gender" class="<?= $maInput ?>">
                        <?php foreach (['Male', 'Female', 'Other'] as $maG): ?>
                            <option value="<?= $maG ?>"<?= $maGender === $maG ? ' selected' : '' ?>><?= $maG ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="ma-tinNumber" class="block text-zinc-400 mb-1 font-semibold">Tax Identification No. (TIN)</label>
                    <input id="ma-tinNumber" name="tinNumber" type="text" maxlength="40" value="<?= e($maVal('tinNumber')) ?>" class="<?= $maInputMono ?>">
                </div>
                <div>
                    <label for="ma-occupation" class="block text-zinc-400 mb-1 font-semibold">Executive Title / Role</label>
                    <input id="ma-occupation" name="occupation" type="text" maxlength="190" autocomplete="organization-title" value="<?= e($maOccupation) ?>" class="<?= $maInput ?>">
                </div>
            </div>
        </div>
        <div class="p-5 rounded-3xl bg-[#121212] border border-zinc-800 shadow-xl space-y-4">
            <h4 class="text-xs font-bold text-[#f3bd35] uppercase tracking-wider flex items-center gap-2"><?= icon('phone', 'w-4 h-4') ?> B. Contact Endpoints</h4>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                <div>
                    <label for="ma-email" class="block text-zinc-400 mb-1 font-semibold">Official Email</label>
                    <input id="ma-email" name="email" type="email" required maxlength="190" autocomplete="email" value="<?= e($maEmail) ?>" class="<?= $maInput ?>">
                </div>
                <div>
                    <label for="ma-phone" class="block text-zinc-400 mb-1 font-semibold">Mobile Phone (PH)</label>
                    <input id="ma-phone" name="phone" type="text" maxlength="60" autocomplete="tel" value="<?= e($maPhone) ?>" class="<?= $maInputMono ?>">
                </div>
                <div>
                    <label for="ma-whatsapp" class="block text-zinc-400 mb-1 font-semibold">WhatsApp Number</label>
                    <input id="ma-whatsapp" name="whatsapp" type="text" maxlength="60" value="<?= e($maWhatsapp) ?>" class="<?= $maInputMono ?>">
                </div>
                <div>
                    <label for="ma-telegram" class="block text-zinc-400 mb-1 font-semibold">Telegram Handle</label>
                    <input id="ma-telegram" name="telegram" type="text" maxlength="60" value="<?= e($maVal('telegram')) ?>" class="<?= $maInputMono ?>">
                </div>
            </div>
        </div>
        <div class="p-5 rounded-3xl bg-[#121212] border border-zinc-800 shadow-xl space-y-4">
            <h4 class="text-xs font-bold text-[#f3bd35] uppercase tracking-wider flex items-center gap-2"><?= icon('map-pin', 'w-4 h-4') ?> C. Billing &amp; Residential Address</h4>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 text-xs">
                <div class="sm:col-span-2">
                    <label for="ma-street" class="block text-zinc-400 mb-1 font-semibold">Street &amp; Building</label>
                    <input id="ma-street" name="street" type="text" maxlength="190" autocomplete="address-line1" value="<?= e($maVal('street')) ?>" class="<?= $maInput ?>">
                </div>
                <div>
                    <label for="ma-barangay" class="block text-zinc-400 mb-1 font-semibold">Barangay</label>
                    <input id="ma-barangay" name="barangay" type="text" maxlength="120" value="<?= e($maVal('barangay')) ?>" class="<?= $maInput ?>">
                </div>
                <div>
                    <label for="ma-city" class="block text-zinc-400 mb-1 font-semibold">City / Municipality</label>
                    <input id="ma-city" name="city" type="text" maxlength="120" autocomplete="address-level2" value="<?= e($maVal('city')) ?>" class="<?= $maInput ?>">
                </div>
                <div>
                    <label for="ma-province" class="block text-zinc-400 mb-1 font-semibold">Province</label>
                    <input id="ma-province" name="province" type="text" maxlength="120" autocomplete="address-level1" value="<?= e($maVal('province')) ?>" class="<?= $maInput ?>">
                </div>
                <div>
                    <label for="ma-zipCode" class="block text-zinc-400 mb-1 font-semibold">Postal Zip Code</label>
                    <input id="ma-zipCode" name="zipCode" type="text" maxlength="20" autocomplete="postal-code" value="<?= e($maVal('zipCode')) ?>" class="<?= $maInputMono ?>">
                </div>
                <input type="hidden" name="country" value="<?= e($maCountry) ?>">
            </div>
        </div>
        <button type="submit" class="px-6 py-3 rounded-xl bg-gradient-to-r from-[#ffda72] to-[#f3bd35] text-black font-extrabold text-xs shadow-lg hover:brightness-105 active:scale-95 transition-all">Save All Profile Changes</button>
    </form>

    <?php /* ------------------------------------------------ Security & 2FA PIN */ ?>
    <form id="ma-security-form" class="space-y-6" autocomplete="off" data-tab-panel="settings" data-group="acct"<?= $maTab === 'settings' ? '' : ' hidden' ?>>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="p-5 rounded-3xl bg-[#121212] border border-zinc-800 shadow-xl space-y-4">
                <h4 class="text-xs font-bold text-[#f3bd35] uppercase tracking-wider flex items-center gap-2"><?= icon('lock', 'w-4 h-4') ?> A. Change Login Password</h4>
                <div class="space-y-3 text-xs">
                    <div>
                        <label for="ma-currentPassword" class="block text-zinc-400 mb-1 font-semibold">Current Password</label>
                        <input id="ma-currentPassword" name="currentPassword" type="password" autocomplete="current-password" placeholder="••••••••" class="<?= $maInput ?>">
                    </div>
                    <div>
                        <label for="ma-newPassword" class="block text-zinc-400 mb-1 font-semibold">New Password</label>
                        <input id="ma-newPassword" name="newPassword" type="password" minlength="6" autocomplete="new-password" placeholder="Min 8 characters" class="<?= $maInput ?>">
                    </div>
                    <div>
                        <label for="ma-confirmPassword" class="block text-zinc-400 mb-1 font-semibold">Confirm New Password</label>
                        <input id="ma-confirmPassword" name="confirmPassword" type="password" minlength="6" autocomplete="new-password" placeholder="Re-type new password" class="<?= $maInput ?>">
                    </div>
                </div>
            </div>
            <div class="p-5 rounded-3xl bg-[#121212] border border-zinc-800 shadow-xl space-y-4">
                <h4 class="text-xs font-bold text-[#f3bd35] uppercase tracking-wider flex items-center gap-2"><?= icon('key', 'w-4 h-4') ?> B. Transaction PIN &amp; 2-Factor Auth</h4>
                <div class="space-y-4 text-xs">
                    <div>
                        <label for="ma-pin" class="block text-zinc-400 mb-1 font-semibold">4-Digit Transaction Security PIN (Required for Encashments)</label>
                        <input id="ma-pin" name="pin" type="password" maxlength="6" inputmode="numeric" pattern="\d{4,6}" autocomplete="off" value="<?= $maHasPin ? '' : '8888' ?>"<?= $maHasPin ? ' placeholder="••••"' : '' ?> class="w-full px-3 py-2 rounded-xl bg-zinc-900 border border-zinc-700 text-white font-mono tracking-widest text-center text-base focus:outline-none focus:border-[#f3bd35]">
                        <span class="text-[10px] text-zinc-500 mt-1 block">Protects against unauthorized fund encashments or code transfers.</span>
                    </div>
                    <div class="p-3.5 rounded-2xl bg-zinc-950 border border-zinc-800 flex items-center justify-between">
                        <div>
                            <span class="font-bold text-white block" id="ma-2fa-label">Two-Factor Authentication (2FA)</span>
                            <span class="text-[10px] text-zinc-400">Authenticates backoffice logins via SMS/Email OTP</span>
                        </div>
                        <input type="hidden" name="twoFactor" value="<?= $maTwoFactor ? '1' : '0' ?>">
                        <button type="button" data-ma-2fa role="switch" aria-checked="<?= $maTwoFactor ? 'true' : 'false' ?>" aria-labelledby="ma-2fa-label" data-on="bg-emerald-500" data-off="bg-zinc-700" class="w-12 h-6 flex items-center rounded-full p-1 transition-colors <?= $maTwoFactor ? 'bg-emerald-500' : 'bg-zinc-700' ?>">
                            <div data-on="translate-x-6" data-off="translate-x-0" class="bg-white w-4 h-4 rounded-full shadow-md transform transition-transform <?= $maTwoFactor ? 'translate-x-6' : 'translate-x-0' ?>"></div>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <button type="submit" class="px-6 py-3 rounded-xl bg-gradient-to-r from-[#ffda72] to-[#f3bd35] text-black font-extrabold text-xs shadow-lg hover:brightness-105 active:scale-95 transition-all">Update Security Credentials</button>
    </form>

    <?php /* ------------------------------------------------ Activation codes */ ?>
    <div class="space-y-4" data-tab-panel="codes" data-group="acct"<?= $maTab === 'codes' ? '' : ' hidden' ?>>
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <h3 class="text-lg font-bold text-white flex items-center gap-2">
                    <?= icon('credit-card', 'w-5 h-5 text-[#f3bd35]') ?>
                    Activation Codes &amp; Product PIN Vault
                </h3>
                <p class="text-xs text-zinc-400 mt-0.5">Each code activates 1x Executive Membership (₱2,000 PHP) with 100 PV points.</p>
            </div>
            <button type="button" data-open-modal="ma-buy-codes" class="px-4 py-2.5 rounded-xl bg-gradient-to-r from-[#ffda72] to-[#f3bd35] text-black font-extrabold text-xs shadow-md hover:brightness-105 active:scale-95 transition-all flex items-center gap-1.5 self-start sm:self-auto">
                <?= icon('plus', 'w-4 h-4') ?>
                <span>Purchase Activation Codes (₱2,000)</span>
            </button>
        </div>
        <div class="rounded-3xl bg-[#121212] border border-zinc-800 overflow-hidden shadow-xl">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-zinc-900/80 border-b border-zinc-800 text-zinc-400 uppercase text-[10px] tracking-wider">
                        <tr>
                            <th class="py-3 px-4">Activation Code</th>
                            <th class="py-3 px-4">Security PIN</th>
                            <th class="py-3 px-4">Package</th>
                            <th class="py-3 px-4">Status</th>
                            <th class="py-3 px-4">Date / Used By</th>
                            <th class="py-3 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800/80">
                        <?php foreach ($maCodesShown as $maC): $maFree = $maC['status'] === 'active'; $maUsed = $maC['status'] === 'used'; ?>
                            <tr class="hover:bg-zinc-900/40 transition-colors">
                                <td class="py-3.5 px-4 font-mono font-bold text-white flex items-center gap-2">
                                    <span><?= e($maC['code']) ?><span class="block text-[10px] font-normal text-zinc-500"><?= e($maC['serial']) ?></span></span>
                                    <button type="button" data-copy="<?= e($maC['code']) ?>" data-copied-html="<?= e(icon('check', 'w-3.5 h-3.5 text-emerald-400')) ?>" title="Copy Code" aria-label="Copy code <?= e($maC['code']) ?>" class="text-zinc-500 hover:text-[#f3bd35]"><?= icon('copy', 'w-3.5 h-3.5') ?></button>
                                </td>
                                <td class="py-3.5 px-4 font-mono text-[#f3bd35] font-bold"><?= e($maC['pin']) ?></td>
                                <td class="py-3.5 px-4 text-zinc-300"><?= e($maC['packageType']) ?> (<?= peso($maC['value']) ?>)</td>
                                <td class="py-3.5 px-4">
                                    <span class="text-[9px] px-2.5 py-0.5 rounded-full font-bold uppercase <?= $maFree ? 'bg-emerald-500/15 text-emerald-400 border border-emerald-500/30' : ($maUsed ? 'bg-zinc-800 text-zinc-500' : 'bg-amber-950/40 text-amber-300 border border-amber-600/40') ?>"><?= e(CODE_STATUSES[$maC['status']] ?? $maC['status']) ?></span>
                                </td>
                                <td class="py-3.5 px-4 text-zinc-400 text-[11px]"><?= e($maUsed ? ($maC['usedBy'] ?: 'Consumed') : $maC['generatedDate']) ?></td>
                                <td class="py-3.5 px-4 text-right">
                                    <?php if ($maFree): ?>
                                        <button type="button" data-ma-transfer="<?= e($maC['code']) ?>" class="px-2.5 py-1 rounded-lg bg-zinc-900 hover:bg-[#f3bd35] hover:text-black border border-zinc-700 text-zinc-200 text-[11px] font-bold transition-all">Transfer Code</button>
                                    <?php else: ?>
                                        <span class="text-[11px] text-zinc-600">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($codes): ?>
                <div class="px-4 py-3 border-t border-zinc-800 text-[11px] text-zinc-400">
                    Showing <?= number_format(($maCodePage - 1) * CODES_PER_PAGE + 1) ?>–<?= number_format(($maCodePage - 1) * CODES_PER_PAGE + count($maCodesShown)) ?> of <?= number_format(count($codes)) ?> codes
                </div>
            <?php endif; ?>
        </div>
        <?php if ($maCodePages > 1): ?>
            <nav class="flex items-center justify-center gap-3 text-xs" aria-label="Code pages">
                <?php if ($maCodePage > 1): ?>
                    <a href="<?= e($maCodesUrl($maCodePage - 1)) ?>" class="px-3 py-1.5 rounded-xl bg-zinc-900 hover:bg-zinc-800 border border-zinc-700 text-zinc-200 transition-colors">← Previous</a>
                <?php endif; ?>
                <span class="text-zinc-400">Page <?= $maCodePage ?> of <?= $maCodePages ?></span>
                <?php if ($maCodePage < $maCodePages): ?>
                    <a href="<?= e($maCodesUrl($maCodePage + 1)) ?>" class="px-3 py-1.5 rounded-xl bg-zinc-900 hover:bg-zinc-800 border border-zinc-700 text-zinc-200 transition-colors">Next →</a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    </div>

    <?php /* ------------------------------------------------ Smart NFC card */ ?>
    <div class="space-y-6 animate-in fade-in" data-tab-panel="nfc_card" data-group="acct"<?= $maTab === 'nfc_card' ? '' : ' hidden' ?>>
        <div data-modal="ma-nfc-tap" hidden class="fixed inset-0 z-50 flex items-center justify-center bg-black/85 backdrop-blur-md p-4 animate-in fade-in" role="dialog" aria-modal="true" aria-labelledby="ma-nfc-tap-title">
            <div class="w-full max-w-sm rounded-3xl bg-[#141414] border border-[#f3bd35]/50 p-6 shadow-2xl relative text-center space-y-4">
                <div class="w-16 h-16 rounded-full bg-gradient-to-r from-amber-500/20 to-[#f3bd35]/20 border border-[#f3bd35] mx-auto flex items-center justify-center text-[#f3bd35] animate-bounce"><?= icon('radio', 'w-8 h-8') ?></div>
                <div>
                    <span class="text-[10px] font-mono uppercase tracking-widest text-emerald-400 font-bold bg-emerald-950/40 border border-emerald-500/30 px-2.5 py-0.5 rounded-full inline-block mb-1.5">NFC Tag Read Successful · 13.56 MHz</span>
                    <h3 id="ma-nfc-tap-title" class="text-lg font-black text-white">Contactless Wave Detected!</h3>
                    <p class="text-xs text-zinc-300 mt-1">Card Serial: <strong class="text-[#ffda72] font-mono"><?= e($maCardId) ?></strong></p>
                </div>
                <div class="p-4 rounded-2xl bg-zinc-950 border border-zinc-800 text-left text-xs space-y-2">
                    <div class="flex justify-between">
                        <span class="text-zinc-500">Cardholder:</span>
                        <span class="font-bold text-white"><?= e($profile['name'] ?: $user['name']) ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-zinc-500">Action Triggered:</span>
                        <span class="font-bold text-[#ffda72]" data-ma-tap-action><?= e($maActionLabels[$maAction] ?? 'Launching WhatsApp VIP') ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-zinc-500">Recipient UX:</span>
                        <span class="text-emerald-400 font-semibold">Zero App Download Needed</span>
                    </div>
                </div>
                <button type="button" data-close-modal class="w-full py-2.5 rounded-xl bg-gradient-to-r from-[#ffda72] to-[#f3bd35] text-black font-extrabold text-xs shadow-lg hover:brightness-105 active:scale-95 transition-all">Close Tap Simulator</button>
            </div>
        </div>
        <div class="p-5 rounded-3xl bg-gradient-to-r from-[#1b150c] via-[#13100a] to-[#0c0c0c] border border-[#f3bd35]/40 shadow-2xl flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 flex-wrap mb-1.5">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-[#f3bd35]/20 border border-[#f3bd35]/40 text-[#ffda72] text-[10px] font-extrabold uppercase tracking-widest font-mono">
                        <?= icon('radio', 'w-3.5 h-3.5 text-[#f3bd35] animate-pulse') ?>
                        Assigned Hardware ID: <?= e($maCardId) ?>
                    </span>
                    <span class="inline-flex items-center gap-1 text-[10px] px-2.5 py-1 rounded-full bg-emerald-500/15 border border-emerald-500/30 text-emerald-400 font-bold">
                        <?= icon('shield-check', 'w-3.5 h-3.5') ?>
                        E2EE Hardware Verified
                    </span>
                    <span class="text-[10px] px-2.5 py-1 rounded-full bg-zinc-900 border border-zinc-700 text-zinc-300 font-mono">Linked to Head: <?= e($account['id']) ?> (<?= e($account['accountName']) ?>)</span>
                </div>
                <h3 class="text-xl font-black text-white flex items-center gap-2">
                    Executive Smart NFC Card Management
                    <?= icon('sparkles', 'w-5 h-5 text-[#f3bd35]') ?>
                </h3>
                <p class="text-xs text-zinc-300 mt-1 max-w-2xl leading-relaxed">Every 88MMM member receives a laser-etched contactless NFC Smart Card paired directly to your account. Tap any modern smartphone (iOS &amp; Android) to instantly share your executive presence, generate direct leads, or download your vCard with zero application download needed.</p>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" data-ma-tap data-ma-tap-main class="px-4 py-2.5 rounded-xl bg-gradient-to-r from-[#ffda72] to-[#f3bd35] text-black font-extrabold text-xs shadow hover:brightness-105 active:scale-95 transition-all flex items-center gap-2">
                    <?= icon('radio', 'w-4 h-4 text-black') ?>
                    <span>Simulate NFC Tap</span>
                </button>
            </div>
        </div>
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <div class="lg:col-span-5 space-y-4">
                <div class="p-5 rounded-3xl bg-[#121212] border border-zinc-800 shadow-xl space-y-4">
                    <div class="flex items-center justify-between">
                        <h4 class="text-xs font-bold text-white uppercase tracking-wider flex items-center gap-2"><?= icon('credit-card', 'w-4 h-4 text-[#f3bd35]') ?> Physical Card Live Mockup</h4>
                        <span class="text-[10px] font-mono text-zinc-400">NXP NTAG216 · 13.56 MHz</span>
                    </div>
                    <div data-ma-card class="w-full aspect-[1.586/1] rounded-2xl p-5 shadow-2xl relative flex flex-col justify-between overflow-hidden transition-all duration-300 border <?= $maCardClass ?>">
                        <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,_var(--tw-gradient-stops))] from-white/20 via-transparent to-black/30 pointer-events-none"></div>
                        <div class="flex items-start justify-between relative z-10">
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 rounded-xl bg-black/20 backdrop-blur-sm border border-white/20 flex items-center justify-center font-black text-xs">88</div>
                                <div>
                                    <div class="text-[11px] font-black tracking-widest uppercase leading-tight">MMMC CIRCLE</div>
                                    <div class="text-[8px] opacity-75 font-semibold tracking-wider uppercase">Executive Society</div>
                                </div>
                            </div>
                            <div class="flex items-center gap-1.5 opacity-80"><?= icon('wifi', 'w-5 h-5 rotate-90') ?></div>
                        </div>
                        <div class="my-auto relative z-10 flex items-center justify-between">
                            <div class="w-10 h-7 rounded-md bg-gradient-to-br from-amber-200 to-amber-500/80 border border-amber-600/50 shadow-inner flex flex-col justify-around p-1">
                                <div class="h-0.5 bg-amber-800/40 rounded"></div>
                                <div class="h-0.5 bg-amber-800/40 rounded"></div>
                            </div>
                            <div class="text-right">
                                <span class="text-[9px] uppercase tracking-widest font-extrabold px-2 py-0.5 rounded bg-black/25 backdrop-blur-sm"><?= e($profile['tier'] ?: "Chairman's Circle") ?></span>
                            </div>
                        </div>
                        <div class="relative z-10 pt-2 border-t border-black/15">
                            <div class="flex items-end justify-between">
                                <div>
                                    <div class="text-sm font-extrabold tracking-wide uppercase leading-tight"><?= e($profile['name'] ?: $user['name']) ?></div>
                                    <div class="text-[10px] opacity-80 font-medium truncate max-w-[200px]"><?= e($profile['title'] ?: 'President & CEO') ?></div>
                                </div>
                                <div class="text-right">
                                    <div class="text-[8px] uppercase tracking-wider opacity-70">Serial ID</div>
                                    <div class="font-mono text-[10px] font-bold tracking-wider"><?= e($maCardId) ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-2 pt-1">
                        <button type="button" data-ma-tap class="py-2.5 px-3 rounded-xl bg-zinc-900 hover:bg-zinc-800 border border-zinc-700 text-zinc-200 font-bold text-xs flex items-center justify-center gap-2 transition-all">
                            <?= icon('radio', 'w-3.5 h-3.5 text-[#f3bd35]') ?>
                            <span>Test Tap Screen</span>
                        </button>
                        <button type="button" data-ma-copy-serial class="py-2.5 px-3 rounded-xl bg-zinc-900 hover:bg-zinc-800 border border-zinc-700 text-zinc-200 font-bold text-xs flex items-center justify-center gap-2 transition-all">
                            <?= icon('copy', 'w-3.5 h-3.5 text-zinc-400') ?>
                            <span>Copy Serial ID</span>
                        </button>
                    </div>
                    <div class="p-3.5 rounded-2xl bg-zinc-950 border border-zinc-800 grid grid-cols-3 gap-2 text-center text-xs">
                        <div>
                            <span class="text-[10px] text-zinc-400 block uppercase">NFC Taps</span>
                            <span class="font-mono font-black text-white text-sm" data-ma-taps="<?= (int) (($maMetrics['taps'] ?? 0) ?: 3840) ?>"><?= num(($maMetrics['taps'] ?? 0) ?: 3840) ?></span>
                        </div>
                        <div>
                            <span class="text-[10px] text-zinc-400 block uppercase">vCard Saves</span>
                            <span class="font-mono font-black text-[#ffda72] text-sm"><?= num(($maMetrics['downloads'] ?? 0) ?: 1950) ?></span>
                        </div>
                        <div>
                            <span class="text-[10px] text-zinc-400 block uppercase">Network Leads</span>
                            <span class="font-mono font-black text-emerald-400 text-sm"><?= num(($maMetrics['connectionsCount'] ?? 0) ?: 348) ?></span>
                        </div>
                    </div>
                </div>
                <div class="p-4 rounded-3xl bg-[#121212] border border-zinc-800 shadow-xl space-y-3">
                    <div class="flex items-center justify-between">
                        <div>
                            <h5 class="text-xs font-bold text-white flex items-center gap-1.5">
                                <?= icon('lock', 'w-3.5 h-3.5 text-rose-400') ?>
                                Lost Card Protection &amp; Security Lock
                            </h5>
                            <p class="text-[10px] text-zinc-400 mt-0.5">If you temporarily misplace your physical smart card, freeze it to prevent unauthorized taps.</p>
                        </div>
                        <button type="button" data-ma-freeze aria-pressed="<?= $maFrozen ? 'true' : 'false' ?>" data-on="<?= $maFreezeOn ?>" data-off="<?= $maFreezeOff ?>" class="px-3 py-1.5 rounded-xl font-bold text-xs transition-all border <?= $maFrozen ? $maFreezeOn : $maFreezeOff ?>"><?= $maFrozen ? 'Frozen (Locked)' : 'Card Active' ?></button>
                    </div>
                </div>
            </div>
            <div class="lg:col-span-7 space-y-4">
                <form id="ma-nfc-form" class="p-5 rounded-3xl bg-[#121212] border border-zinc-800 shadow-xl space-y-5">
                    <div>
                        <h4 class="text-sm font-bold text-white flex items-center gap-2">
                            <?= icon('smartphone', 'w-4 h-4 text-[#f3bd35]') ?>
                            1. Contactless Physical Tap Action
                        </h4>
                        <p class="text-xs text-zinc-400 mt-0.5">Choose what happens on the recipient's smartphone when they physically tap your card.</p>
                    </div>
                    <div class="space-y-2.5" role="radiogroup" aria-label="Contactless Physical Tap Action">
                        <?php foreach ($maActions as $maOpt): $maSel = $maAction === $maOpt['id']; ?>
                            <div role="radio" tabindex="0" aria-checked="<?= $maSel ? 'true' : 'false' ?>" data-ma-action="<?= e($maOpt['id']) ?>" data-on="<?= $maActOn ?>" data-off="<?= $maActOff ?>" class="p-3.5 rounded-2xl border cursor-pointer transition-all flex items-start gap-3 <?= $maSel ? $maActOn : $maActOff ?>">
                                <div data-on="<?= $maActIconOn ?>" data-off="<?= $maActIconOff ?>" class="w-8 h-8 rounded-xl flex items-center justify-center shrink-0 mt-0.5 <?= $maSel ? $maActIconOn : $maActIconOff ?>"><?= icon($maOpt['icon'], 'w-4 h-4') ?></div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="text-xs font-bold text-white"><?= e($maOpt['title']) ?></span>
                                        <span data-on="<?= $maActBadgeOn ?>" data-off="<?= $maActBadgeOff ?>" class="text-[9px] px-2 py-0.5 rounded-full font-bold uppercase <?= $maSel ? $maActBadgeOn : $maActBadgeOff ?>"><?= e($maOpt['badge']) ?></span>
                                    </div>
                                    <p class="text-[11px] text-zinc-400 mt-0.5 leading-relaxed"><?= e($maOpt['desc']) ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="pt-2 border-t border-zinc-800/80">
                        <h4 class="text-xs font-bold text-white uppercase tracking-wider mb-2 flex items-center gap-1.5">
                            <?= icon('palette', 'w-3.5 h-3.5 text-[#f3bd35]') ?>
                            2. Card Laser Hardware Finish &amp; Theme
                        </h4>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2" role="radiogroup" aria-label="Card Laser Hardware Finish &amp; Theme">
                            <?php foreach ($maThemeOpts as $maOpt): $maSel = $maTheme === $maOpt['id']; ?>
                                <button type="button" role="radio" aria-checked="<?= $maSel ? 'true' : 'false' ?>" data-ma-theme="<?= e($maOpt['id']) ?>" data-on="<?= $maThemeOn ?>" data-off="<?= $maThemeOff ?>" class="p-2.5 rounded-xl text-left transition-all border <?= $maSel ? $maThemeOn : $maThemeOff ?>">
                                    <div class="h-4 rounded-md mb-1.5 shadow <?= $maOpt['color'] ?>"></div>
                                    <div class="text-[11px] font-bold text-white"><?= e($maOpt['label']) ?></div>
                                    <div class="text-[9px] text-zinc-400"><?= e($maOpt['desc']) ?></div>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="pt-3 border-t border-zinc-800 flex items-center justify-between">
                        <div class="text-[11px] text-zinc-400 flex items-center gap-1.5">
                            <?= icon('shield-check', 'w-3.5 h-3.5 text-emerald-400') ?>
                            <span>Real-time cloud sync to your card URL</span>
                        </div>
                        <button type="submit" class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-[#ffda72] to-[#f3bd35] text-black font-extrabold text-xs shadow-lg hover:brightness-105 active:scale-95 transition-all flex items-center gap-1.5">
                            <?= icon('check', 'w-4 h-4 text-black') ?>
                            <span>Save NFC Preferences</span>
                        </button>
                    </div>
                </form>
                <div class="p-4 rounded-3xl bg-zinc-950 border border-zinc-800 shadow-xl flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <h5 class="text-xs font-bold text-white flex items-center gap-1.5">
                            <?= icon('credit-card', 'w-3.5 h-3.5 text-[#f3bd35]') ?>
                            Need an Additional or Replacement Smart Card?
                        </h5>
                        <p class="text-[11px] text-zinc-400 mt-0.5">Order laser-etched spare cards for your team or replacement hardware for only <strong class="text-[#ffda72]">₱1,000 PHP</strong> (Member discounted rate · 25 PV).</p>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <button type="button" data-ma-goto="shop" class="px-3.5 py-2 rounded-xl bg-zinc-900 hover:bg-zinc-800 border border-zinc-700 text-xs text-white font-bold transition-all">Order in Shop →</button>
                        <button type="button" data-ma-goto="digital_card" class="px-3.5 py-2 rounded-xl bg-[#f3bd35]/15 hover:bg-[#f3bd35] hover:text-black border border-[#f3bd35]/40 text-[#ffda72] text-xs font-bold transition-all flex items-center gap-1">
                            <span>Digital Card CMS (Tab 7)</span>
                            <?= icon('external-link', 'w-3 h-3') ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <?php /* ------------------------------------------------ Buy activation codes */ ?>

    <?php /* ------------------------------------------------ Transfer activation code */ ?>
    </div>
</div>
