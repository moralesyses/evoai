<?php
/**
 * 1EVOAI public digital card, laid out like the reference portal's card view
 * (component "wn"): profile card with Save / Chat / Share / QR, the 88MMM
 * Circle story from the Portal CMS, membership tiers and the mobile card bar.
 * Modals (QR, share, send vCard, membership application) are in
 * partials/card_modals.php. Pay buttons open the payment page (views/pay.php),
 * where Maya Checkout and the receipt live.
 *
 *   (no parameter)  the signed-in member's own card; visitors are asked to sign in
 *                   (no member's card is shown by default)
 *   ?profile=usr_x  a member's card
 *   ?card=slug      a WeServe member (<id>-<code>) or a member's card link name; the
 *                   address is /<slug> (.htaccess), and the other forms redirect there
 */
$cms = get_cms();
$profiles = all_member_profiles();
$fallbackAvatar = 'assets/images/mike_maranan_executive_portrait.svg';

// ?card= is a WeServe member link (<id>-<code>) or a portal member's custom slug.
$weserve = null;
// No card asked for: a signed-in member sees their own card; a visitor is asked to sign in, so no
// member's card shows by default. A card opened by its own link (/<name>, QR, NFC, ?profile=) stays public.
$cardAsked = (is_string($_GET['profile'] ?? null) && $_GET['profile'] !== '') || (is_string($_GET['card'] ?? null) && $_GET['card'] !== '');
if (!$user && !$cardAsked) {
    $gateTitle = 'Sign in to view your 1EVOAI card';
    $gateText = 'Members see their own digital card here once signed in. To see someone\'s card, open the link or QR code they shared with you.';
    $pageMeta = ['title' => 'Sign in · 1EVOAI Card', 'description' => 'Sign in to view your 1EVOAI digital card.', 'noindex' => true];
    require __DIR__ . '/partials/gate.php';
    return;
}
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
if (!$weserve && !isset($profiles[$profileId])) {
    // An unknown member: say so, rather than showing someone else's card.
    http_response_code(404);
    $pageMeta = ['title' => 'Card not found', 'description' => 'There is no digital card at this address.', 'noindex' => true];
    require __DIR__ . '/partials/card_not_found.php';
    return;
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
    $qrUrl = $shareUrl;
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
    // The QR code always opens the card here on 1EVOAI (e.g. /juan-dela-cruz), not the member's domain.
    $qrUrl = card_share_url($cardProfile, false);
    $vcardUrl = base_path() . '/vcard.php?id=' . rawurlencode($profileId);
    $manifestQuery = ['profile' => $profileId];
    // Back to the member's own website (My Website), when they have one.
    $siteBack = site_card_back((string) $cardProfile['userId'], $user);
}
// The card's own address is /<link name> (e.g. /juan-dela-cruz; .htaccess); the other ways in
// (?view=card&profile=…, ?card=…) are sent there. A member's own .com domain keeps its address.
$cardSlug = $weserve ? $weserve['slug'] : card_slug_clean((string) ($cardProfile['customSlug'] ?? ''));
$cardPath = base_path() . '/' . $cardSlug;
if ($cardSlug !== '' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET'
    && parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) !== $cardPath
    && domain_owner_for_host((string) ($_SERVER['HTTP_HOST'] ?? '')) === null) {
    $keep = array_diff_key($_GET, ['view' => 1, 'profile' => 1, 'card' => 1]);
    // Permanent for a named card; the plain ?view=card depends on who is signed in.
    header('Location: ' . $cardPath . ($keep ? '?' . http_build_query($keep) : ''), true, isset($_GET['profile']) || isset($_GET['card']) ? 301 : 302);
    return;
}
// The member's own design (Backoffice > Digital Card CMS > Card Design) and products (My Products), includes/cards.php.
$cardOwnerId = $weserve ? '' : (string) $cardProfile['userId'];
$design = $cardOwnerId !== '' ? card_design($cardOwnerId) : card_design_default();
[$cardAccent, $cardLight] = card_design_colors($design);
$cardThemed = $design['accent'] !== '' || $design['theme'] !== 'gold';
$cardShow = card_design_blocks($design);
$cardProducts = $cardOwnerId !== '' && in_array('products', $cardShow, true) ? card_products($cardOwnerId, true) : [];
$cardMaya = maya_enabled();
// Back from Maya after buying one of the member's products (?maya=<rrn>): paid, waiting or not paid.
$cardOrderBack = $cardOwnerId !== '' && is_string($_GET['maya'] ?? null)
    ? card_order_return($_GET['maya'], is_string($_GET['result'] ?? null) ? $_GET['result'] : '', $cardOwnerId) : null;
$cardFirst = explode(' ', trim($name))[0];
$quote = ($member['quote'] ?? null) ?: $cms['heroQuote'];
$subtitle = ($member['subtitle'] ?? null) ?: $cms['heroSubtitle'];
$visionaryImage = $isMember ? $avatar : media_url($cms['visionaryImageUrl'] ?: ($cms['avatarUrl'] ?: $fallbackAvatar));
$showAbout = !$isMember || $bio !== '' || $avatar !== '';
$videoThumb = media_url($cms['featuredVideoThumbnailUrl'] ?: 'assets/images/circle_executive_summit.svg');
$digitalImage = media_url($cms['digitalImageUrl'] ?: 'assets/images/nfc_smart_card_executive.svg');
$execPrice = num($cms['executivePrice']);
$chairPrice = num($cms['chairmansPrice']);
// Signing up from a card goes to the sign-up form (pay with Maya Checkout, or an activation code and
// PIN), with the card owner's Referrer ID and the package filled in. WeServe cards have no account here.
$cardRef = $cardOwnerId !== '' ? (string) db_value('SELECT id FROM mlm_accounts WHERE user_id = ? ORDER BY head_number, id LIMIT 1', [$cardOwnerId]) : '';
$cardSignup = static fn (string $package): string => url('main', array_filter(['ref' => $cardRef, 'package' => $package])) . '#register';
// And this browser remembers the owner, so Create Account uses their Referrer ID even when the visitor
// reaches it another way (the logo, a later visit to the portal).
if ($cardRef !== '') {
    signup_remember_referrer($cardRef);
}
$payExecutive = $cardSignup('membership_executive');
$payChairmans = $cardSignup('membership_chairmans');
$cardPayNote = $cardMaya ? 'Sign up and pay with Maya (card or wallet) or with an activation code and its PIN.' : 'Sign up with an activation code and its PIN. ' . MAYA_UNAVAILABLE . '.';

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
    'qrUrl' => $qrUrl,
    'vcardUrl' => $vcardUrl,
    'payUrls' => ['membership_executive' => $payExecutive, 'membership_chairmans' => $payChairmans],
    'headlineEn' => [$cms['headlinePart1'] ?: 'CONNECT.', $cms['headlinePart2'] ?: 'GROW.', $cms['headlinePart3'] ?: 'PROSPER.'],
    'i18n' => $translations,
    'videoUrl' => $cms['featuredVideoUrl'] ?: 'https://www.youtube.com/embed/r9XLnqrPZh4',
    'heroCtaText' => $cms['heroCtaText'],
    'products' => array_map(fn ($p) => ['id' => $p['id'], 'name' => $p['name'], 'price' => $p['price'], 'image' => $p['image'], 'stock' => $p['stock']], $cardProducts),
    'orderWait' => $cardOrderBack && $cardOrderBack['state'] === 'pending' ? $cardOrderBack['rrn'] : null,
];
$actionBtn = 'flex flex-col items-center justify-center p-2.5 rounded-2xl bg-gradient-to-b from-[#1a1a1a] to-[#121212] border border-[#f3bd35]/20 hover:border-[#f3bd35] active:scale-95 transition-all text-center group';
$actionIcon = 'w-10 h-10 rounded-full bg-gradient-to-br from-[#ffda72] to-[#f3bd35] flex items-center justify-center text-black mb-1.5 shadow-md group-hover:scale-105 transition-transform';
$socialLink = 'px-3 py-1.5 rounded-xl bg-zinc-900 hover:bg-zinc-800 text-[11px] font-medium text-zinc-300 border border-zinc-800 transition-colors';
$bottomBtn = 'flex flex-col items-center justify-center min-w-[54px] min-h-[44px] text-zinc-400 hover:text-[#f3bd35] transition-colors';
?>
<div class="evo-card min-h-screen bg-[#070707] text-[#f7f5ef] pb-24 md:pb-12 selection:bg-[#f3bd35]/30" data-font="<?= e($design['font']) ?>"<?= $cardThemed ? ' data-themed style="--ca: ' . e($cardAccent) . '; --cl: ' . e($cardLight) . ';"' : '' ?>>
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
            <a href="<?= e($payExecutive) ?>" title="<?= e($cardPayNote) ?>" class="flex items-center gap-1.5 px-3 py-1 rounded-full bg-gradient-to-r from-emerald-600 to-teal-600 text-white text-[11px] font-bold shadow hover:brightness-110active:scale-95 transition-all">
                <?= icon('credit-card', 'w-3 h-3 text-emerald-200') ?><span class="hidden sm:inline">Sign Up</span><span class="sm:hidden">Join</span>
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
        <?php if ($cardOrderBack): $cob = $cardOrderBack['order']; ?>
            <?php if ($cardOrderBack['state'] === 'paid'): ?>
                <div id="card-order" data-card-order="paid" role="status" class="mb-4 p-4 rounded-2xl bg-emerald-950/40 border border-emerald-500/40 text-xs text-emerald-200 flex items-start gap-3">
                    <?= icon('circle-check-big', 'w-5 h-5 shrink-0 text-emerald-400') ?>
                    <div>
                        <span class="block font-bold text-white">Thank you, <?= e(explode(' ', $cob['buyerName'])[0]) ?>! Your payment is confirmed.</span>
                        <span class="block mt-0.5"><?= e($cob['product']) ?> × <?= (int) $cob['quantity'] ?> · <?= peso($cob['amount']) ?> · order <span class="font-mono"><?= e($cob['id']) ?></span></span>
                        <span class="block mt-0.5 text-zinc-300"><?= e($cardFirst) ?> will contact you at <?= e($cob['buyerPhone']) ?> or <?= e($cob['buyerEmail']) ?> about delivery.</span>
                    </div>
                </div>
            <?php elseif ($cardOrderBack['state'] === 'pending'): ?>
                <div id="card-order" data-card-order="pending" role="status" class="mb-4 p-4 rounded-2xl bg-amber-950/40 border border-amber-600/40 text-xs text-amber-200 flex items-center gap-3">
                    <span class="w-4 h-4 shrink-0 rounded-full border-2 border-amber-300 border-t-transparent animate-spin"></span>
                    <div>
                        <span class="block font-bold">Waiting for Maya to confirm your payment (order <?= e($cob['id']) ?>)</span>
                        <span class="block text-zinc-400">This page updates by itself.</span>
                    </div>
                </div>
            <?php else: ?>
                <div id="card-order" data-card-order="failed" role="alert" class="mb-4 p-4 rounded-2xl bg-rose-950/40 border border-rose-500/40 text-xs text-rose-200 flex items-start gap-3">
                    <?= icon('circle-alert', 'w-5 h-5 shrink-0 text-rose-400') ?>
                    <div>
                        <span class="block font-bold">Your order for <?= e($cob['product']) ?> wasn't paid</span>
                        <span class="block mt-0.5 text-zinc-300"><?= e($cardOrderBack['message']) ?> You can try again below.</span>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        <div class="relative rounded-3xl bg-gradient-to-b from-[#141414] via-[#0d0d0d] to-[#070707] border border-[#f3bd35]/30 p-6 shadow-2xl text-center overflow-hidden mb-6">
            <div class="absolute -top-20 left-1/2 -translate-x-1/2 w-64 h-64 bg-[#f3bd35]/15 rounded-full blur-3xl pointer-events-none"></div>
            <?php if ($design['cover'] !== ''): ?>
                <div class="evo-card-cover"><img src="<?= e(media_url($design['cover'])) ?>" alt=""></div>
            <?php endif; ?>
            <div class="relative inline-block mb-4<?= $design['cover'] !== '' ? ' evo-card-avatar' : '' ?>">
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

        <?php /* The blocks below are captured and shown in the member's order (Card Design). */ $cardBlockHtml = []; ob_start(); ?>
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
                        <?= icon('credit-card', 'w-4 h-4 text-emerald-200') ?><span>Sign Up (₱<?= $execPrice ?>)</span>
                    </a>
                </div>
                <a href="#what-is-mmmc" class="block w-full py-3 border border-[#f3bd35]/30 text-[#ffda72] font-bold text-xs rounded-2xl hover:bg-[#f3bd35]/10 transition-colors"><?= e($cms['heroSecondaryCtaText'] ?: 'DISCOVER 88MMMC') ?></a>
            </div>
        </section>

        <?php $cardBlockHtml['community'] = ob_get_clean(); ob_start(); ?>
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
        <?php $cardBlockHtml['about'] = ob_get_clean(); ob_start(); ?>

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

        <?php $cardBlockHtml['video'] = ob_get_clean(); ob_start(); ?>
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

        <?php $cardBlockHtml['circle'] = ob_get_clean(); ob_start(); ?>
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
                            <?= icon('credit-card', 'w-3.5 h-3.5 text-emerald-200') ?><span>Sign Up · ₱<?= $execPrice ?></span>
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
                            <?= icon('credit-card', 'w-4 h-4 text-emerald-200') ?><span>Sign Up · ₱<?= $chairPrice ?></span>
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
                    <?= icon('credit-card', 'w-3.5 h-3.5 text-emerald-200') ?><span>Sign Up Now</span>
                </a>
            </div>
            <p class="text-[11px] text-zinc-500 max-w-sm mx-auto mt-3" data-card-pay-note><?= e($cardPayNote) ?></p>
        </section>

        <?php $cardBlockHtml['membership'] = ob_get_clean(); ob_start(); ?>
        <?php if ($cardProducts): ?>
        <section id="my-products" class="mb-6">
            <div class="rounded-3xl bg-[#111111] border border-[#f3bd35]/25 p-5 shadow-xl">
                <div class="text-[11px] font-bold tracking-wider text-[#f3bd35] uppercase mb-1">My Products</div>
                <h3 class="text-xl font-bold text-white mb-4">Shop with <?= e($cardFirst) ?></h3>
                <div class="grid grid-cols-2 gap-3">
                    <?php foreach ($cardProducts as $cp): $cpOut = $cp['stock'] === 0; ?>
                        <div class="rounded-2xl bg-zinc-900 border border-zinc-800 overflow-hidden flex flex-col">
                            <img src="<?= e($cp['image']) ?>" alt="<?= e($cp['name']) ?>" loading="lazy" class="w-full aspect-square object-cover bg-zinc-950">
                            <div class="p-3 flex flex-col flex-1 gap-1">
                                <span class="text-xs font-bold text-white line-clamp-2"><?= e($cp['name']) ?></span>
                                <span class="text-sm font-black text-[#ffda72] font-mono"><?= peso($cp['price']) ?></span>
                                <?php if ($cp['description'] !== ''): ?><span class="text-[11px] text-zinc-400 line-clamp-3"><?= e($cp['description']) ?></span><?php endif; ?>
                                <?php if ($cp['stock'] !== null && $cp['stock'] > 0 && $cp['stock'] <= 5): ?><span class="text-[10px] text-amber-300">Only <?= (int) $cp['stock'] ?> left</span><?php endif; ?>
                                <button type="button" data-card-buy="<?= e($cp['id']) ?>" class="mt-auto w-full py-2 rounded-xl bg-gradient-to-r from-[#ffda72] to-[#f3bd35] text-black text-[11px] font-extrabold disabled:opacity-50"<?= $cpOut ? ' disabled' : '' ?>><?= $cpOut ? 'Sold out' : 'Buy now' ?></button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if (!$cardMaya): ?><p class="mt-3 text-[11px] text-zinc-400"><?= e(MAYA_UNAVAILABLE) ?>. Contact <?= e($cardFirst) ?> to order.</p><?php endif; ?>
            </div>
        </section>
        <?php endif; ?>
        <?php $cardBlockHtml['products'] = ob_get_clean(); ?>
        <?php foreach ($cardShow as $cardBlockId) { echo $cardBlockHtml[$cardBlockId] ?? ''; } ?>

        <footer class="pt-8 pb-4 text-center text-[10px] text-zinc-500 border-t border-zinc-900">
            <p class="mb-2"><?= e($cms['footerText'] ?: "MIKE MARANAN MILLIONAIRE'S CIRCLE • CONNECT. GROW. PROSPER.") ?></p>
            <div class="flex flex-wrap items-center justify-center gap-3 text-zinc-400">
                <span>Powered by 1EVOAI &amp; EVO Card Technology</span>
                <span>·</span>
                <a href="<?= e(url('backoffice')) ?>" class="text-[#ffda72] hover:underline font-semibold">Member Backoffice &amp; Rewards Plan</a>
                <?php if ($user && is_admin($user)): ?>
                    <span>·</span>
                    <a href="<?= e(url('admin')) ?>" class="text-[#f3bd35] hover:underline font-semibold">Management Admin</a>
                <?php endif; ?>
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

    <?php if ($cardProducts): ?>
    <div data-modal="card-buy" hidden class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4" role="dialog" aria-modal="true" aria-labelledby="card-buy-title">
        <div class="w-full max-w-md bg-[#141414] border border-[#f3bd35]/40 rounded-3xl p-6 shadow-2xl space-y-4 text-xs text-left max-h-[92vh] overflow-y-auto">
            <div class="flex items-center justify-between pb-3 border-b border-zinc-800">
                <h4 class="font-bold text-white text-sm" id="card-buy-title">Buy from <?= e($cardFirst) ?></h4>
                <button type="button" data-close-modal class="text-zinc-400 hover:text-white" aria-label="Close"><?= icon('x', 'w-4 h-4') ?></button>
            </div>
            <form id="card-buy-form" class="space-y-3" novalidate>
                <input type="hidden" name="productId" value="">
                <input type="text" name="company_website" value="" tabindex="-1" autocomplete="off" aria-hidden="true" class="evo-hp">
                <div class="flex items-center gap-3">
                    <img data-buy-image src="" alt="" class="w-16 h-16 rounded-xl object-cover bg-zinc-950 border border-zinc-800 shrink-0">
                    <div class="min-w-0">
                        <span data-buy-name class="block font-bold text-white text-sm"></span>
                        <span data-buy-price class="block text-[#ffda72] font-mono font-bold"></span>
                    </div>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <label for="card-buy-qty" class="text-[11px] font-semibold text-zinc-300">Quantity</label>
                    <div class="flex items-center gap-1.5">
                        <button type="button" data-buy-step="-1" class="w-8 h-8 rounded-lg bg-zinc-900 border border-zinc-700 text-zinc-200" aria-label="One less">−</button>
                        <input id="card-buy-qty" name="quantity" type="number" min="1" value="1" class="w-14 px-2 py-1.5 rounded-lg bg-zinc-900 border border-zinc-700 text-center text-white font-mono">
                        <button type="button" data-buy-step="1" class="w-8 h-8 rounded-lg bg-zinc-900 border border-zinc-700 text-zinc-200" aria-label="One more">+</button>
                    </div>
                </div>
                <div><label for="card-buy-name" class="block text-[11px] font-semibold text-zinc-300 mb-1">Your name *</label><input id="card-buy-name" name="name" type="text" required maxlength="150" autocomplete="name" value="<?= e($user['name'] ?? '') ?>" class="w-full px-3 py-2 rounded-xl bg-zinc-900 border border-zinc-700 text-white focus:outline-none focus:border-[#f3bd35]"></div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div><label for="card-buy-email" class="block text-[11px] font-semibold text-zinc-300 mb-1">Email *</label><input id="card-buy-email" name="email" type="email" required maxlength="190" autocomplete="email" value="<?= e($user['email'] ?? '') ?>" class="w-full px-3 py-2 rounded-xl bg-zinc-900 border border-zinc-700 text-white focus:outline-none focus:border-[#f3bd35]"></div>
                    <div><label for="card-buy-phone" class="block text-[11px] font-semibold text-zinc-300 mb-1">Mobile *</label><input id="card-buy-phone" name="phone" type="tel" required maxlength="60" autocomplete="tel" placeholder="+63 917..." class="w-full px-3 py-2 rounded-xl bg-zinc-900 border border-zinc-700 text-white focus:outline-none focus:border-[#f3bd35]"></div>
                </div>
                <div><label for="card-buy-delivery" class="block text-[11px] font-semibold text-zinc-300 mb-1">Delivery address or notes</label><textarea id="card-buy-delivery" name="delivery" rows="2" maxlength="500" placeholder="Where should <?= e($cardFirst) ?> send it?" class="w-full px-3 py-2 rounded-xl bg-zinc-900 border border-zinc-700 text-white focus:outline-none focus:border-[#f3bd35]"></textarea></div>
                <div class="flex items-center justify-between pt-2 border-t border-zinc-800"><span class="text-zinc-400">Total</span><span data-buy-total class="text-base font-black text-[#ffda72] font-mono"></span></div>
                <p data-buy-error hidden role="alert" class="p-3 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-300"></p>
                <button type="submit"<?= $cardMaya ? '' : ' disabled' ?> class="w-full py-3 bg-gradient-to-r from-[#ffda72] to-[#f3bd35] text-black font-extrabold rounded-xl text-xs flex items-center justify-center gap-1.5 disabled:opacity-50"><?= icon('lock', 'w-3.5 h-3.5') ?><span data-buy-submit>Pay with Maya</span></button>
                <p class="text-[10px] text-zinc-500 text-center"><?= $cardMaya ? 'You pay on Maya’s secure page with a card or your Maya wallet, then come back here.' : 'Online payment isn’t available yet. Contact ' . e($cardFirst) . ' to order.' ?></p>
            </form>
        </div>
    </div>
    <?php endif; ?>
    <?php require __DIR__ . '/partials/card_modals.php'; ?>
</div>
