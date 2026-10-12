<?php
/**
 * 1EVOAI public digital card, laid out like the reference portal's card view
 * (component "wn"): profile card with Save / Chat / Share / QR, the 88MMM
 * Circle story from the Portal CMS, membership tiers and the mobile card bar.
 * Modals (QR, share, send vCard, membership application) are in
 * partials/card_modals.php. Pay buttons open the payment page (views/pay.php),
 * where Maya Checkout and the receipt live.
 *
 *   (no parameter)  the signed-in member's own card; for visitors, the founder's master card
 *   ?profile=usr_x  a member's card
 *   ?card=slug      share link: a WeServe member (<id>-<code>) or a member's custom slug
 */
$cms = get_cms();
$profiles = all_member_profiles();
$fallbackAvatar = 'assets/images/mike_maranan_executive_portrait.svg';

// ?card= is a WeServe member link (<id>-<code>) or a portal member's custom slug.
$weserve = null;
// No card asked for: a signed-in member sees their own card, a visitor the founder's.
$profileId = is_string($_GET['profile'] ?? null) && $_GET['profile'] !== '' ? $_GET['profile'] : ($user['id'] ?? MASTER_PROFILE_ID);
if ($user && $profileId === $user['id'] && !isset($profiles[$profileId])) {
    // Their card profile hasn't been saved yet: show the account's own name and title.
    $profiles[$profileId] = array_merge(default_profile($user['id']), [
        'name' => $user['name'], 'title' => $user['title'], 'email' => $user['email'],
        'company' => '', 'bio' => '', 'phone' => '', 'whatsapp' => '', 'customSlug' => '',
    ]);
}
if (isset($_GET['card']) && is_string($_GET['card'])) {
    $weserve = weserve_member_from_slug($_GET['card']);
    if (!$weserve) {
        $bySlug = find_profile_by_slug($_GET['card']);
        if (!$bySlug) {
            http_response_code(404);
            $pageMeta = ['title' => 'Card not found', 'description' => 'There is no digital card at this address.', 'noindex' => true];
            require __DIR__ . '/partials/card_not_found.php';
            return;
        }
        $profileId = $bySlug['userId'];
    }
}
if (!isset($profiles[$profileId])) {
    $profileId = MASTER_PROFILE_ID;
}

$member = null;
if ($weserve) {
    // WeServe member: name and "Member since" from the copied UserProfile table.
    $profileId = 'ws_' . $weserve['userId'];
    $isMember = true;
    $name = $weserve['name'];
    $title = $weserve['title'];
    $company = '';
    $bio = '';
    $avatar = '';
    $initials = $weserve['initials'];
    $location = '';
    $services = [];
    $socials = [];
    $headline = null;
    $shareUrl = $weserve['shareUrl'];
    $vcardUrl = base_path() . '/vcard.php?card=' . rawurlencode($weserve['slug']);
    $manifestQuery = ['card' => $weserve['slug']];
} else {
    $isMember = $profileId !== MASTER_PROFILE_ID;            // reference: A
    $member = $isMember ? $profiles[$profileId] : null;       // reference: j
    $cardProfile = $member ?? ($profiles[MASTER_PROFILE_ID] ?? get_member_profile(MASTER_PROFILE_ID));
    // The founder's card is managed in the Portal CMS; member cards come from their own profile.
    $name = $member ? $member['name'] : $cms['name'];
    $title = $member ? $member['title'] : $cms['title'];
    $company = $member ? $member['company'] : $cms['company'];
    $bio = $member ? (string) $member['bio'] : $cms['bio'];
    $avatarPath = $member ? (string) ($member['avatar'] ?? '') : ($cms['avatarUrl'] ?: $fallbackAvatar);
    $avatar = $avatarPath !== '' ? media_url($avatarPath) : '';
    $nameParts = preg_split('/\s+/u', trim($name)) ?: [$name];
    $initials = mb_strtoupper(mb_substr($nameParts[0], 0, 1) . (count($nameParts) > 1 ? mb_substr(end($nameParts), 0, 1) : ''));
    // A member's own details only, so nobody's card shows the founder's location or links.
    $location = $member ? (string) ($member['location'] ?? '') : $cms['location'];
    $services = $member['services'] ?? [];
    $socials = $member ? ($member['socials'] ?? []) : ($cms['socials'] ?? []);
    $headline = $member['headline'] ?? null;
    // The member's own .com domain when their card uses one; otherwise the 1EVOAI link.
    $shareUrl = domain_card_url((string) $cardProfile['userId'])
        ?? ((string) ($cardProfile['customSlug'] ?? '') !== '' || $member === null ? card_share_url($cardProfile) : base_url() . '/index.php?' . http_build_query(['view' => 'card', 'profile' => $profileId]));
    $vcardUrl = base_path() . '/vcard.php?id=' . rawurlencode($profileId);
    $manifestQuery = ['profile' => $profileId];
    // Back to the member's own website (My Website), when they have one.
    $siteBack = site_card_back((string) $cardProfile['userId'], $user);
}
$quote = ($member['quote'] ?? null) ?: $cms['heroQuote'];
$subtitle = ($member['subtitle'] ?? null) ?: $cms['heroSubtitle'];
$visionaryImage = $isMember ? $avatar : media_url($cms['visionaryImageUrl'] ?: ($cms['avatarUrl'] ?: $fallbackAvatar));
$showAbout = !$isMember || $bio !== '' || $avatar !== '';
$videoThumb = media_url($cms['featuredVideoThumbnailUrl'] ?: 'assets/images/circle_executive_summit.svg');
$digitalImage = media_url($cms['digitalImageUrl'] ?: 'assets/images/nfc_smart_card_executive.svg');
$execPrice = num($cms['executivePrice']);
$chairPrice = num($cms['chairmansPrice']);
$payExecutive = url('pay', ['checkout' => 'membership_executive']);
$payChairmans = url('pay', ['checkout' => 'membership_chairmans']);

track_action('profile_view', $profileId, $user['id'] ?? null);

// Page title and link previews show the person, like a standalone vCard page.
$pageMeta = [
    'title' => $name,
    'description' => trim($title . ' ' . $company),
    'keywords' => $isMember ? '' : $name . ', ' . $name . ' vcard online',
    'noindex' => $isMember,
    'url' => $shareUrl,
    'type' => 'profile',
    'image' => $avatar !== '' && preg_match('/\.(png|jpe?g|webp)(\?|$)/i', $avatar) ? (preg_match('#^https?://#i', $avatar) ? $avatar : preg_replace('#^(https?://[^/]+).*$#', '$1', base_url()) . $avatar) : '',
    // The We Serve emblem in the browser tab; the person's photo (or the emblem) on the home screen.
    'favicon' => asset('assets/images/weserve/favicon-32.png'),
    'apple_touch_icon' => $avatar !== '' && preg_match('/\.(png|jpe?g)(\?|$)/i', $avatar) ? $avatar : asset('assets/images/weserve/apple-touch-icon.png'),
    'manifest' => base_path() . '/manifest.php?' . http_build_query($manifestQuery),
];

$translations = [
    'en' => ['connect' => 'CONNECT.', 'grow' => 'GROW.', 'prosper' => 'PROSPER.', 'joinBtn' => 'JOIN THE CIRCLE →'],
    'ja' => ['connect' => '繋がる.', 'grow' => '成長する.', 'prosper' => '繁栄する.', 'joinBtn' => 'サークルに参加する →'],
    'zh' => ['connect' => '连接.', 'grow' => '成长.', 'prosper' => '繁荣.', 'joinBtn' => '加入精英圈 →'],
    'es' => ['connect' => 'CONECTAR.', 'grow' => 'CRECER.', 'prosper' => 'PROSPERAR.', 'joinBtn' => 'UNIRSE AL CÍRCULO →'],
    'fr' => ['connect' => 'CONNECTER.', 'grow' => 'GRANDIR.', 'prosper' => 'PROSPÉRER.', 'joinBtn' => 'REJOINDRE LE CERCLE →'],
];
$pageScripts[] = 'assets/js/vendor/qrcode.js';
$pageScripts[] = 'assets/js/views/card.js';
$pageData['card'] = [
    'profileId' => $profileId,
    'name' => $name,
    'shareUrl' => $shareUrl,
    'vcardUrl' => $vcardUrl,
    'payUrls' => ['membership_executive' => $payExecutive, 'membership_chairmans' => $payChairmans],
    'headlineEn' => [$cms['headlinePart1'] ?: 'CONNECT.', $cms['headlinePart2'] ?: 'GROW.', $cms['headlinePart3'] ?: 'PROSPER.'],
    'i18n' => $translations,
    'videoUrl' => $cms['featuredVideoUrl'] ?: 'https://www.youtube.com/embed/r9XLnqrPZh4',
    'heroCtaText' => $cms['heroCtaText'],
];
$actionBtn = 'flex flex-col items-center justify-center p-2.5 rounded-2xl bg-gradient-to-b from-[#1a1a1a] to-[#121212] border border-[#f3bd35]/20 hover:border-[#f3bd35] active:scale-95 transition-all text-center group';
$actionIcon = 'w-10 h-10 rounded-full bg-gradient-to-br from-[#ffda72] to-[#f3bd35] flex items-center justify-center text-black mb-1.5 shadow-md group-hover:scale-105 transition-transform';
$socialLink = 'px-3 py-1.5 rounded-xl bg-zinc-900 hover:bg-zinc-800 text-[11px] font-medium text-zinc-300 border border-zinc-800 transition-colors';
$bottomBtn = 'flex flex-col items-center justify-center min-w-[54px] min-h-[44px] text-zinc-400 hover:text-[#f3bd35] transition-colors';
?>
<div class="min-h-screen bg-[#070707] text-[#f7f5ef] pb-24 md:pb-12 selection:bg-[#f3bd35]/30">
    <header class="sticky top-0 z-40 w-full bg-[#070707]/90 backdrop-blur-md border-b border-[#f3bd35]/15 px-3 sm:px-6 py-2.5 flex items-center justify-between">
        <a href="<?= e(url('main')) ?>" class="flex items-center gap-2.5 cursor-pointer group" title="Return to Main Portal">
            <div class="w-8 h-8 rounded-full bg-gradient-to-br from-[#ffda72] to-[#996f13] p-[1.5px] flex items-center justify-center shadow-sm group-hover:scale-105 transition-transform">
                <div class="w-full h-full bg-[#090909] rounded-full flex items-center justify-center"><span class="text-[#f3bd35] font-black text-xs tracking-tighter">88</span></div>
            </div>
            <div>
                <span class="text-xs font-bold tracking-widest text-[#ffda72] uppercase block group-hover:text-amber-200 transition-colors"><?= e($cms['brandName'] ?: '88MMM CIRCLE') ?></span>
                <span class="text-[10px] text-zinc-400 block -mt-0.5"><?= e($cms['brandTagline'] ?: 'Powered by EVOAI') ?></span>
            </div>
        </a>
        <div class="flex items-center gap-2">
            <div class="relative" data-dropdown>
                <button type="button" data-dropdown-toggle aria-expanded="false" class="flex items-center gap-1.5 px-2.5 py-1 rounded-xl bg-zinc-900 border border-[#f3bd35]/30 text-xs text-zinc-200 hover:text-white transition-colors">
                    <?= icon('user', 'w-3 h-3 text-[#f3bd35]') ?>
                    <span class="font-semibold hidden sm:inline"><?= e($name) ?></span>
                    <span class="font-semibold sm:hidden"><?= e(explode(' ', $name)[0]) ?></span>
                    <?= icon('chevron-down', 'w-3 h-3 text-zinc-400') ?>
                </button>
                <div data-dropdown-menu hidden class="absolute right-0 mt-2 w-64 bg-[#141414] border border-[#f3bd35]/40 rounded-2xl shadow-2xl p-2 z-50 animate-in fade-in slide-in-from-top-2">
                    <div class="text-[10px] font-bold uppercase tracking-wider text-zinc-400 px-2 py-1 border-b border-zinc-800 mb-1 flex items-center justify-between"><span>Switch Digital Card</span><span class="text-[#f3bd35]">1EVOAI</span></div>
                    <a href="<?= e(url('card', ['profile' => MASTER_PROFILE_ID])) ?>" class="w-full text-left px-2.5 py-2 rounded-xl text-xs flex items-center justify-between transition-colors <?= !$isMember ? 'bg-[#f3bd35] text-black font-bold' : 'text-zinc-200 hover:bg-zinc-800' ?>">
                        <div><span class="block font-bold"><?= e($cms['name']) ?></span><span class="text-[10px] opacity-75 block">Founder &amp; Master Card</span></div>
                        <?php if (!$isMember): ?><?= icon('check', 'w-3.5 h-3.5') ?><?php endif; ?>
                    </a>
                    <div class="pt-1 mt-1 border-t border-zinc-800 space-y-1">
                        <span class="text-[9px] font-semibold text-zinc-500 px-2 block">Member Custom Cards:</span>
                        <?php foreach ($profiles as $p): if ($p['userId'] === MASTER_PROFILE_ID) { continue; } $on = $p['userId'] === $profileId; ?>
                            <a href="<?= e(url('card', ['profile' => $p['userId']])) ?>" class="w-full text-left px-2.5 py-1.5 rounded-xl text-xs flex items-center justify-between transition-colors <?= $on ? 'bg-[#f3bd35] text-black font-bold' : 'text-zinc-300 hover:bg-zinc-800' ?>">
                                <div><span class="block font-medium"><?= e($p['name']) ?></span><span class="text-[9px] opacity-75 block"><?= e($p['company']) ?></span></div>
                                <?php if ($on): ?><?= icon('check', 'w-3.5 h-3.5') ?><?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <a href="<?= e($payExecutive) ?>" title="Pay online with Maya (card or Maya wallet)" class="flex items-center gap-1.5 px-3 py-1 rounded-full bg-gradient-to-r from-emerald-600 to-teal-600 text-white text-[11px] font-bold shadow hover:brightness-110 active:scale-95 transition-all">
                <?= icon('credit-card', 'w-3 h-3 text-emerald-200') ?><span class="hidden sm:inline">Pay Online</span><span class="sm:hidden">Pay</span>
            </a>
            <button type="button" id="card-nfc-tap" title="Simulate NFC Tap from physical card" class="flex items-center gap-1 px-2.5 py-1 rounded-full bg-[#f3bd35]/10 border border-[#f3bd35]/30 text-[#f3bd35] text-[11px] font-medium hover:bg-[#f3bd35]/20 active:scale-95 transition-all">
                <?= icon('smartphone', 'w-3 h-3') ?><span class="hidden sm:inline">NFC Tap</span>
            </button>
            <div class="relative flex items-center">
                <?= icon('globe', 'w-3.5 h-3.5 text-zinc-400 absolute left-2 pointer-events-none') ?>
                <select id="card-lang" aria-label="Language" class="bg-[#141414] border border-[#f3bd35]/25 rounded-lg text-zinc-200 text-xs pl-7 pr-2 py-1 focus:outline-none focus:border-[#f3bd35]">
                    <option value="en">EN</option><option value="ja">JA</option><option value="zh">ZH</option><option value="es">ES</option><option value="fr">FR</option>
                </select>
            </div>
        </div>
    </header>

    <div class="max-w-xl mx-auto px-4 pt-6">
        <?php if (!empty($siteBack)): ?>
            <a href="<?= e($siteBack['url']) ?>" data-site-back class="mb-4 flex items-center gap-3 px-4 py-3 rounded-2xl bg-[#141414] border border-[#f3bd35]/30 hover:border-[#f3bd35] transition-colors group">
                <span class="w-9 h-9 rounded-full bg-gradient-to-br from-[#ffda72] to-[#f3bd35] text-black flex items-center justify-center shrink-0 shadow-md group-hover:scale-105 transition-transform"><?= icon($siteBack['back'] ? 'arrow-left' : 'monitor', 'w-4 h-4') ?></span>
                <span class="min-w-0 text-left">
                    <span class="block text-xs font-bold text-white"><?= e($siteBack['label']) ?></span>
                    <?php if ($siteBack['name'] !== ''): ?><span class="block text-[11px] text-zinc-400 truncate"><?= e($siteBack['name']) ?></span><?php endif; ?>
                </span>
            </a>
        <?php endif; ?>
        <div class="relative rounded-3xl bg-gradient-to-b from-[#141414] via-[#0d0d0d] to-[#070707] border border-[#f3bd35]/30 p-6 shadow-2xl text-center overflow-hidden mb-6">
            <div class="absolute -top-20 left-1/2 -translate-x-1/2 w-64 h-64 bg-[#f3bd35]/15 rounded-full blur-3xl pointer-events-none"></div>
            <div class="relative inline-block mb-4">
                <div class="w-32 h-32 rounded-full p-[3px] bg-gradient-to-tr from-[#ffda72] via-[#f3bd35] to-[#8f6510] shadow-xl mx-auto overflow-hidden">
                    <?php if ($avatar !== ''): ?>
                        <img src="<?= e($avatar) ?>" alt="<?= e($name) ?>" referrerpolicy="no-referrer" class="w-full h-full object-cover rounded-full" <?= $isMember ? 'data-initials="' . e($initials) . '"' : 'data-fallback="' . e(media_url($fallbackAvatar)) . '"' ?>>
                    <?php else: ?>
                        <div class="w-full h-full rounded-full bg-[#090909] flex items-center justify-center text-[#f3bd35] font-black text-3xl sm:text-4xl" role="img" aria-label="<?= e($name) ?>"><?= e($initials) ?></div>
                    <?php endif; ?>
                </div>
                <div title="Verified E2EE Digital Identity" class="absolute bottom-1 right-1 w-7 h-7 rounded-full bg-[#090909] border border-[#f3bd35] flex items-center justify-center text-[#f3bd35] shadow-lg"><?= icon('shield-check', 'w-4 h-4') ?></div>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight mb-1"><?= e($name) ?></h1>
            <p class="text-xs sm:text-sm text-[#ffda72] font-semibold <?= $company === '' && !$location ? 'mb-4' : 'mb-1' ?>"><?= e($title) ?></p>
            <?php if ($company !== ''): ?><p class="text-xs text-zinc-400 mb-2"><?= e($company) ?></p><?php endif; ?>
            <?php if ($location): ?>
                <p class="text-[11px] text-zinc-500 flex items-center justify-center gap-1 mb-4"><?= icon('map-pin', 'w-3 h-3 text-[#f3bd35]') ?> <?= e($location) ?></p>
            <?php endif; ?>
            <?php if ($services): ?>
                <div class="flex flex-wrap items-center justify-center gap-1.5 mb-5">
                    <?php foreach ($services as $s): ?><span class="px-2.5 py-0.5 rounded-full bg-zinc-900 border border-zinc-700 text-[10px] text-zinc-300 font-medium"><?= e($s) ?></span><?php endforeach; ?>
                </div>
            <?php endif; ?>
            <div class="grid grid-cols-4 gap-2 mb-6">
                <a href="<?= e($vcardUrl) ?>" class="<?= $actionBtn ?>"><div class="<?= $actionIcon ?>"><?= icon('download', 'w-4 h-4') ?></div><span class="text-[10px] font-semibold text-zinc-200">Save</span></a>
                <a href="<?= e(url('chat')) ?>" class="<?= $actionBtn ?>"><div class="<?= $actionIcon ?>"><?= icon('message-square-lock', 'w-4 h-4') ?></div><span class="text-[10px] font-semibold text-zinc-200">Chat E2EE</span></a>
                <button type="button" data-open-modal="card-share" class="<?= $actionBtn ?>"><div class="<?= $actionIcon ?>"><?= icon('share-2', 'w-4 h-4') ?></div><span class="text-[10px] font-semibold text-zinc-200">Share</span></button>
                <button type="button" data-card-qr class="<?= $actionBtn ?>"><div class="<?= $actionIcon ?>"><?= icon('qr-code', 'w-4 h-4') ?></div><span class="text-[10px] font-semibold text-zinc-200">QR Code</span></button>
            </div>
            <div class="flex flex-wrap items-center justify-center gap-2 pt-2 border-t border-zinc-800/80">
                <?php foreach (['facebook' => 'Facebook', 'instagram' => 'Instagram', 'linkedin' => 'LinkedIn', 'twitter' => 'X (Twitter)', 'website' => 'Website'] as $key => $label): ?>
                    <?php if (!empty($socials[$key])): ?>
                        <a href="<?= e($socials[$key]) ?>" target="_blank" rel="noreferrer" class="<?= $socialLink ?>"><?= e($label) ?></a>
                    <?php endif; ?>
                <?php endforeach; ?>
                <button type="button" data-open-modal="card-vcard" class="px-3 py-1.5 rounded-xl bg-gradient-to-r from-[#ffda72]/20 to-[#f3bd35]/20 hover:bg-[#f3bd35]/30 text-[11px] font-medium text-[#ffda72] border border-[#f3bd35]/30 transition-colors">Send vCard</button>
            </div>
        </div>

        <section class="text-center py-6 mb-6">
            <div class="inline-block px-3 py-1 rounded-full bg-[#f3bd35]/15 border border-[#f3bd35]/30 text-[#f3bd35] text-[10px] font-bold tracking-widest uppercase mb-3"><?= e($cms['communityBadge'] ?: 'The Executive Community') ?></div>
            <h2 class="text-3xl sm:text-4xl font-black tracking-tight text-white mb-2 leading-tight">
                <?php if ($headline): ?>
                    <?= e($headline) ?>
                <?php else: ?>
                    <span data-hl="connect"><?= e($cms['headlinePart1'] ?: 'CONNECT.') ?></span> <br>
                    <span class="text-[#f3bd35]" data-hl="grow"><?= e($cms['headlinePart2'] ?: 'GROW.') ?></span> <br>
                    <span data-hl="prosper"><?= e($cms['headlinePart3'] ?: 'PROSPER.') ?></span>
                <?php endif; ?>
            </h2>
            <p class="text-sm text-zinc-300 max-w-sm mx-auto mb-2"><?= e($subtitle) ?></p>
            <p class="text-xs text-zinc-400 italic mb-6">"<?= e($quote) ?>"</p>
            <div class="space-y-2">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <button type="button" data-join="executive" data-join-label class="w-full py-3.5 bg-gradient-to-r from-[#ffda72] via-[#f3bd35] to-[#f3bd35] text-black font-extrabold text-xs sm:text-sm rounded-2xl shadow-xl hover:brightness-105 active:scale-98 transition-all"><?= e($cms['heroCtaText'] ?: 'JOIN THE CIRCLE →') ?></button>
                    <a href="<?= e($payExecutive) ?>" class="w-full py-3.5 bg-gradient-to-r from-emerald-600 to-teal-600 hover:brightness-110 text-white font-extrabold text-xs sm:text-sm rounded-2xl shadow-xl flex items-center justify-center gap-2 active:scale-98 transition-all">
                        <?= icon('credit-card', 'w-4 h-4 text-emerald-200') ?><span>Pay Online (₱<?= $execPrice ?>)</span>
                    </a>
                </div>
                <a href="#what-is-mmmc" class="block w-full py-3 border border-[#f3bd35]/30 text-[#ffda72] font-bold text-xs rounded-2xl hover:bg-[#f3bd35]/10 transition-colors"><?= e($cms['heroSecondaryCtaText'] ?: 'DISCOVER 88MMMC') ?></a>
            </div>
        </section>

        <?php if ($showAbout): ?>
        <section id="who-is-mike" class="mb-6">
            <div class="rounded-3xl bg-[#111111] border border-[#f3bd35]/25 p-5 shadow-xl">
                <div class="text-[11px] font-bold tracking-wider text-[#f3bd35] uppercase mb-1"><?= e($isMember ? 'Executive Profile' : ($cms['visionaryKicker'] ?: 'The Visionary')) ?></div>
                <h3 class="text-xl font-bold text-white mb-3"><?= e($isMember ? 'About ' . $name : ($cms['visionaryTitle'] ?: 'Who is ' . $cms['name'] . '?')) ?></h3>
                <?php if ($visionaryImage !== ''): ?>
                    <div class="rounded-2xl overflow-hidden mb-4 border border-zinc-800">
                        <img src="<?= e($visionaryImage) ?>" alt="<?= e($name) ?>" referrerpolicy="no-referrer" class="w-full h-56 object-cover object-top" data-fallback="<?= e(media_url($fallbackAvatar)) ?>">
                    </div>
                <?php endif; ?>
                <p class="text-xs text-zinc-300 leading-relaxed whitespace-pre-line"><?= e($bio) ?></p>
            </div>
        </section>
        <?php endif; ?>

        <section class="mb-6">
            <div class="rounded-3xl bg-[#111111] border border-[#f3bd35]/25 p-5 shadow-xl text-center">
                <div class="text-[11px] font-bold tracking-wider text-[#f3bd35] uppercase mb-1"><?= e($cms['featuredVideoKicker'] ?: 'Executive Keynote') ?></div>
                <h3 class="text-xl font-bold text-white mb-3"><?= e($cms['featuredVideoTitle'] ?: 'Inside 88MMM Circle') ?></h3>
                <div class="relative aspect-video rounded-2xl overflow-hidden bg-black border border-zinc-800 group" id="card-video">
                    <button type="button" data-play-video class="w-full h-full cursor-pointer relative flex items-center justify-center bg-cover bg-center" style="background-image: url('<?= e($videoThumb) ?>')" aria-label="Watch Keynote">
                        <div class="absolute inset-0 bg-black/60 group-hover:bg-black/40 transition-colors"></div>
                        <div class="relative z-10 w-16 h-16 rounded-full bg-gradient-to-r from-[#ffda72] to-[#f3bd35] flex items-center justify-center text-black shadow-2xl group-hover:scale-110 transition-transform"><?= icon('play', 'w-7 h-7 fill-black ml-1') ?></div>
                        <span class="absolute bottom-3 left-4 text-xs font-semibold text-white/90">Watch Keynote</span>
                    </button>
                </div>
            </div>
        </section>

        <section id="what-is-mmmc" class="mb-6">
            <div class="rounded-3xl bg-[#111111] border border-[#f3bd35]/25 p-5 shadow-xl">
                <div class="text-[11px] font-bold tracking-wider text-[#f3bd35] uppercase mb-1"><?= e($cms['societyKicker'] ?: 'Executive Society') ?></div>
                <h3 class="text-xl font-bold text-white mb-2"><?= e($cms['societyTitle'] ?: 'More Than A Business Club.') ?></h3>
                <p class="text-xs text-zinc-300 leading-relaxed mb-4"><?= e($cms['societyDescription']) ?></p>
                <div class="grid grid-cols-2 gap-2 text-xs">
                    <?php foreach ($cms['pillars'] ?? [] as $p): ?>
                        <div class="p-3 rounded-2xl bg-zinc-900 border border-zinc-800">
                            <span class="text-lg mb-1 block"><?= e($p['icon']) ?></span>
                            <span class="font-bold text-[#ffda72] block"><?= e($p['title']) ?></span>
                            <span class="text-zinc-400 text-[11px]"><?= e($p['desc']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <section class="mb-6">
            <div class="rounded-3xl bg-[#111111] border border-[#f3bd35]/25 p-5 shadow-xl">
                <div class="text-[11px] font-bold tracking-wider text-[#f3bd35] uppercase mb-1"><?= e($cms['digitalKicker'] ?: 'Hardware & Identity') ?></div>
                <h3 class="text-xl font-bold text-white mb-2"><?= e($cms['digitalTitle'] ?: 'Your Digital Advantage.') ?></h3>
                <div class="rounded-2xl overflow-hidden mb-4 border border-zinc-800">
                    <img src="<?= e($digitalImage) ?>" alt="Executive NFC Card" referrerpolicy="no-referrer" class="w-full h-48 object-cover" data-fallback="<?= e(media_url('assets/images/nfc_smart_card_executive.svg')) ?>">
                </div>
                <p class="text-xs text-zinc-300 leading-relaxed mb-4"><?= e($cms['digitalDescription']) ?></p>
                <ul class="grid grid-cols-2 gap-2 text-xs text-zinc-300">
                    <?php foreach ($cms['digitalFeatures'] ?? [] as $f): ?>
                        <li class="flex items-center gap-1.5 p-2 rounded-xl bg-zinc-900 border border-zinc-800/80"><?= icon('check', 'w-3.5 h-3.5 text-[#f3bd35]') ?><span><?= e($f) ?></span></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </section>

        <?php if (!empty($cms['meetingsList'])): ?>
            <section class="mb-6">
                <div class="rounded-3xl bg-[#111111] border border-[#f3bd35]/25 p-5 shadow-xl">
                    <div class="text-[11px] font-bold tracking-wider text-[#f3bd35] uppercase mb-1"><?= e($cms['meetingsKicker'] ?: 'Weekly Executive Meetings') ?></div>
                    <h3 class="text-lg font-bold text-white mb-3"><?= e($cms['meetingsTitle'] ?: 'Where Relationships Become Opportunities.') ?></h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
                        <?php foreach ($cms['meetingsList'] as $m): ?>
                            <div class="p-3 rounded-2xl bg-zinc-900/90 border border-zinc-800">
                                <div class="flex items-center gap-1.5 font-bold text-[#ffda72] mb-1"><?= icon('calendar', 'w-3.5 h-3.5 text-[#f3bd35]') ?><span><?= e($m['title']) ?></span></div>
                                <p class="text-zinc-400 text-[11px]"><?= e($m['desc']) ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <?php if (!empty($cms['eventsList'])): ?>
            <section class="mb-6">
                <div class="rounded-3xl bg-[#111111] border border-[#f3bd35]/25 p-5 shadow-xl">
                    <div class="text-[11px] font-bold tracking-wider text-[#f3bd35] uppercase mb-1"><?= e($cms['eventsKicker'] ?: 'Exclusive Member Events') ?></div>
                    <h3 class="text-lg font-bold text-white mb-3"><?= e($cms['eventsTitle'] ?: 'Experience The Circle.') ?></h3>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                        <?php foreach ($cms['eventsList'] as $ev): ?>
                            <div class="p-2.5 rounded-xl bg-zinc-900 border border-[#f3bd35]/20 text-center text-xs font-semibold text-zinc-200"><?= e($ev) ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <section class="mb-6">
            <div class="rounded-3xl bg-[#111111] border border-[#f3bd35]/25 p-5 shadow-xl">
                <div class="text-[11px] font-bold tracking-wider text-[#f3bd35] uppercase mb-1"><?= e($cms['educationKicker'] ?: 'Executive Education') ?></div>
                <h3 class="text-lg font-bold text-white mb-2 flex items-center gap-2"><?= icon('graduation-cap', 'w-5 h-5 text-[#f3bd35]') ?><?= e($cms['educationTitle'] ?: 'Learn. Apply. Lead.') ?></h3>
                <p class="text-xs text-zinc-300 leading-relaxed"><?= e($cms['educationDescription']) ?></p>
            </div>
        </section>

        <section class="mb-6">
            <div class="rounded-3xl bg-[#111111] border border-[#f3bd35]/25 p-5 shadow-xl">
                <div class="text-[11px] font-bold tracking-wider text-[#f3bd35] uppercase mb-1"><?= e($cms['networkKicker'] ?: 'Member Network') ?></div>
                <h3 class="text-lg font-bold text-white mb-2"><?= e($cms['networkTitle'] ?: 'Cross-Industry Synergy') ?></h3>
                <p class="text-xs text-zinc-400 mb-4"><?= e($cms['networkDescription'] ?: 'Your next partnership may already be inside the Circle:') ?></p>
                <div class="flex flex-wrap gap-1.5">
                    <?php foreach ($cms['industries'] ?? [] as $ind): ?>
                        <span class="px-3 py-1 rounded-full bg-zinc-900 border border-[#f3bd35]/20 text-[11px] text-zinc-300"><?= e($ind) ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <section id="membership" class="mb-8">
            <div class="text-center mb-5">
                <div class="text-[11px] font-bold tracking-wider text-[#f3bd35] uppercase mb-1"><?= e($cms['membershipKicker'] ?: 'Membership Tiers') ?></div>
                <h3 class="text-2xl font-black text-white"><?= e($cms['membershipTitle'] ?: 'One Circle. Endless Opportunities.') ?></h3>
            </div>
            <div class="space-y-4">
                <div class="rounded-3xl bg-[#121212] border border-[#f3bd35]/30 p-5 shadow-xl relative overflow-hidden">
                    <span class="text-[10px] uppercase font-bold tracking-wider text-zinc-400 block mb-1"><?= e($cms['executiveSubtitle'] ?: 'Individual Professionals') ?></span>
                    <div class="flex items-baseline justify-between mb-2">
                        <h4 class="text-lg font-bold text-white"><?= e($cms['executiveTierName'] ?: 'Executive Member') ?></h4>
                        <div class="text-xl font-black text-[#f3bd35] tabular-nums">₱<?= $execPrice ?></div>
                    </div>
                    <p class="text-xs text-zinc-400 mb-4"><?= e($cms['executiveDescription']) ?></p>
                    <ul class="space-y-1.5 text-xs text-zinc-300 mb-5">
                        <?php foreach ($cms['executivePerks'] ?? [] as $perk): ?>
                            <li class="flex items-center gap-2"><?= icon('check', 'w-3.5 h-3.5 text-[#f3bd35]') ?><span><?= e($perk) ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <button type="button" data-join="executive" class="w-full py-3 bg-zinc-800 hover:bg-[#f3bd35] hover:text-black text-white font-bold text-xs rounded-xl transition-all">BECOME AN <?= e(mb_strtoupper($cms['executiveTierName'] ?: 'Executive Member')) ?></button>
                        <a href="<?= e($payExecutive) ?>" class="w-full py-3 bg-gradient-to-r from-emerald-600 to-teal-600 hover:brightness-110 text-white font-bold text-xs rounded-xl flex items-center justify-center gap-1.5 transition-all shadow">
                            <?= icon('credit-card', 'w-3.5 h-3.5 text-emerald-200') ?><span>Pay ₱<?= $execPrice ?> Online</span>
                        </a>
                    </div>
                </div>
                <div class="rounded-3xl bg-gradient-to-b from-[#18150c] to-[#0f0d07] border-2 border-[#f3bd35] p-5 shadow-2xl relative overflow-hidden">
                    <div class="absolute top-3 right-4 px-2.5 py-0.5 rounded-full bg-[#f3bd35] text-black font-extrabold text-[10px] tracking-wider uppercase">PREMIUM VIP</div>
                    <span class="text-[10px] uppercase font-bold tracking-wider text-[#ffda72] block mb-1"><?= e($cms['chairmansSubtitle'] ?: 'High Net Worth & Founders') ?></span>
                    <div class="flex items-baseline justify-between mb-2">
                        <h4 class="text-lg font-bold text-white"><?= e($cms['chairmansTierName'] ?: "Chairman's Circle") ?></h4>
                        <div class="text-2xl font-black text-[#ffda72] tabular-nums">₱<?= $chairPrice ?></div>
                    </div>
                    <p class="text-xs text-zinc-300 mb-4"><?= e($cms['chairmansDescription']) ?></p>
                    <ul class="space-y-1.5 text-xs text-zinc-200 mb-5">
                        <?php foreach ($cms['chairmansPerks'] ?? [] as $perk): ?>
                            <li class="flex items-center gap-2"><?= icon('check', 'w-3.5 h-3.5 text-[#f3bd35]') ?><span><?= e($perk) ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <button type="button" data-join="chairmans" class="w-full py-3.5 bg-gradient-to-r from-[#ffda72] to-[#f3bd35] text-black font-extrabold text-xs rounded-xl shadow-lg hover:brightness-105 active:scale-98 transition-all">JOIN <?= e(mb_strtoupper($cms['chairmansTierName'] ?: "Chairman's Circle")) ?></button>
                        <a href="<?= e($payChairmans) ?>" class="w-full py-3.5 bg-gradient-to-r from-emerald-600 to-teal-500 hover:brightness-110 text-white font-extrabold text-xs rounded-xl shadow-lg flex items-center justify-center gap-1.5 active:scale-98 transition-all">
                            <?= icon('credit-card', 'w-4 h-4 text-emerald-200') ?><span>VIP Checkout (₱<?= $chairPrice ?>)</span>
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <section class="text-center py-8 border-t border-zinc-800">
            <div class="w-12 h-12 rounded-full bg-[#f3bd35]/15 text-[#f3bd35] flex items-center justify-center mx-auto mb-3"><?= icon('lock', 'w-5 h-5') ?></div>
            <div class="text-[11px] font-bold tracking-wider text-[#f3bd35] uppercase mb-1"><?= e($cms['finalCtaKicker'] ?: 'Your Next Connection Starts Here') ?></div>
            <h3 class="text-xl font-bold text-white mb-2"><?= e($cms['finalCtaTitle'] ?: 'BE PART OF THE CIRCLE.') ?></h3>
            <p class="text-xs text-zinc-400 max-w-sm mx-auto mb-5 leading-relaxed"><?= e($cms['finalCtaDescription']) ?></p>
            <div class="flex flex-col sm:flex-row items-center justify-center gap-2 max-w-sm mx-auto">
                <button type="button" data-join="executive" class="w-full sm:w-auto px-6 py-3 bg-gradient-to-r from-[#ffda72] to-[#f3bd35] text-black font-extrabold text-xs rounded-xl shadow-lg hover:brightness-105 transition-all"><?= e($cms['finalCtaButtonText'] ?: 'JOIN THE CIRCLE →') ?></button>
                <a href="<?= e($payExecutive) ?>" class="w-full sm:w-auto px-6 py-3 bg-gradient-to-r from-emerald-600 to-teal-600 hover:brightness-110 text-white font-extrabold text-xs rounded-xl shadow-lg flex items-center justify-center gap-1.5 transition-all">
                    <?= icon('credit-card', 'w-3.5 h-3.5 text-emerald-200') ?><span>Instant Online Payment</span>
                </a>
            </div>
        </section>

        <footer class="pt-8 pb-4 text-center text-[10px] text-zinc-500 border-t border-zinc-900">
            <p class="mb-2"><?= e($cms['footerText'] ?: "MIKE MARANAN MILLIONAIRE'S CIRCLE • CONNECT. GROW. PROSPER.") ?></p>
            <div class="flex flex-wrap items-center justify-center gap-3 text-zinc-400">
                <span>Powered by 1EVOAI &amp; EVO Card Technology</span>
                <span>·</span>
                <a href="<?= e(url('backoffice')) ?>" class="text-[#ffda72] hover:underline font-semibold">Member Backoffice &amp; Complan</a>
                <span>·</span>
                <a href="<?= e(url('admin')) ?>" class="text-[#f3bd35] hover:underline font-semibold">Management Admin</a>
            </div>
        </footer>
    </div>

    <div class="md:hidden fixed bottom-0 left-0 right-0 z-40 bg-[#0d0d0d]/95 backdrop-blur-md border-t border-[#f3bd35]/20 px-3 py-2 flex items-center justify-around">
        <button type="button" data-scroll-top class="<?= $bottomBtn ?>"><div class="w-5 h-5 flex items-center justify-center"><span class="font-black text-xs text-[#f3bd35]">88</span></div><span class="text-[10px] font-medium mt-0.5">Profile</span></button>
        <a href="<?= e(url('chat')) ?>" class="<?= $bottomBtn ?>"><?= icon('message-square-lock', 'w-5 h-5 text-[#f3bd35]') ?><span class="text-[10px] font-medium mt-0.5">Ask Me</span></a>
        <a href="<?= e($vcardUrl) ?>" class="<?= $bottomBtn ?>"><?= icon('download', 'w-5 h-5 text-[#f3bd35]') ?><span class="text-[10px] font-medium mt-0.5">Save</span></a>
        <button type="button" data-open-modal="card-share" class="<?= $bottomBtn ?>"><?= icon('share-2', 'w-5 h-5 text-[#f3bd35]') ?><span class="text-[10px] font-medium mt-0.5">Share</span></button>
        <button type="button" data-card-qr class="<?= $bottomBtn ?>"><?= icon('qr-code', 'w-5 h-5 text-[#f3bd35]') ?><span class="text-[10px] font-medium mt-0.5">QR</span></button>
    </div>

    <?php require __DIR__ . '/partials/card_modals.php'; ?>
</div>
