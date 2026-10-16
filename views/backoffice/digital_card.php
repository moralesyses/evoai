<?php
/**
 * Backoffice tab 7 — Digital Card CMS (reference component "jn"): the member's
 * own 1EVOAI card editor with live preview, QR modal, share link, vCard,
 * the renewal / VIP upgrade checkout (shared partials/checkout_modal.php), and
 * the member's .com domains (domains.php).
 *
 * Uses: $user, $profile, $shareUrl (see views/backoffice.php).
 */
$dcThemes = [
    'obsidian' => 'from-[#1c1c1c] via-[#0d0d0d] to-[#050505] border-zinc-700 text-zinc-100',
    'platinum' => 'from-[#2a2d34] via-[#1a1c23] to-[#0e1015] border-slate-400 text-white',
    'rosegold' => 'from-[#2e1d1b] via-[#191110] to-[#0a0707] border-[#d49a89] text-[#f7e3dd]',
    'gold'     => 'from-[#1c180e] via-[#121212] to-[#090909] border-[#f3bd35]/50 text-[#ffda72]',
];
$dcCardBase = 'p-5 rounded-3xl bg-gradient-to-br border-2 shadow-2xl relative overflow-hidden text-left transition-all';
$dcTheme = (string) ($profile['cardTheme'] ?? '');
$dcThemeCls = $dcThemes[$dcTheme] ?? $dcThemes['gold'];
$dcSocials = is_array($profile['socials'] ?? null) ? $profile['socials'] : [];
$dcServices = is_array($profile['services'] ?? null) ? array_values($profile['services']) : [];
$dcAvatar = (string) ($profile['avatar'] ?? '');
$dcName = (string) $profile['name'];
$dcInitial = mb_substr($dcName, 0, 1);
$dcMetrics = $profile['metrics'];
$dcInput = 'w-full px-3 py-2 rounded-xl bg-zinc-900 border border-zinc-700 text-xs text-white focus:outline-none focus:border-[#f3bd35]';
$dcLabel = 'block text-[11px] font-semibold text-zinc-300 mb-1';
$dcPreviewUrl = url('card', ['profile' => $user['id']]);
$dcVcardUrl = base_path() . '/vcard.php?id=' . rawurlencode($user['id']);
// The QR code opens the card on 1EVOAI (e.g. /juan-dela-cruz), not the member's own domain.
$dcQrUrl = card_share_url($profile, false);
$dcNfcAction = (string) ($profile['nfcAction'] ?? '') ?: 'card';

// Real QR code for the share link (vendor/qrcode.js); the reference's static pattern is the fallback.
if (!in_array('assets/js/vendor/qrcode.js', $pageScripts, true)) {
    $pageScripts[] = 'assets/js/vendor/qrcode.js';
}
?>
<div id="bo-digital-card">
<script type="application/json" id="dc-data"><?= js_json([
    'themes' => $dcThemes,
    'cardBase' => $dcCardBase,
    'socials' => (object) $dcSocials,
    'services' => $dcServices,
    'previewUrl' => $dcPreviewUrl,
    'shareUrl' => $shareUrl,
    'qrUrl' => $dcQrUrl,
    'qrFile' => preg_replace('/\s+/', '_', $dcName) . '_88MMM_QR.png',
]) ?></script>
<?php /* Modals live outside the space-y-6 stack so they get no sibling margins (Tailwind v4 :not(:last-child)). */ ?>
<div class="max-w-5xl mx-auto space-y-6">

    <div class="p-5 rounded-3xl bg-gradient-to-r from-[#16140d] via-[#101010] to-[#0d0d0d] border border-[#f3bd35]/30 shadow-xl flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
        <div>
            <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-[#f3bd35]/15 border border-[#f3bd35]/30 text-[#f3bd35] text-[10px] font-bold tracking-widest uppercase mb-1.5">
                <?= icon('credit-card', 'w-3.5 h-3.5') ?>
                Member Self-Service CMS
            </div>
            <h2 class="text-xl font-black text-white flex items-center gap-2">
                My Digital NFC Card &amp; Identity
                <?= icon('shield-check', 'w-5 h-5 text-[#f3bd35]') ?>
            </h2>
            <p class="text-xs text-zinc-400 mt-0.5">
                Customise your executive profile, contact details, social links, NFC hardware action, and live presence.
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2 w-full md:w-auto">
            <button type="button" data-dc-preview class="flex-1 md:flex-initial flex items-center justify-center gap-1.5 px-3.5 py-2 bg-[#f3bd35] hover:bg-[#ffda72] text-black text-xs font-bold rounded-xl shadow-md transition-all active:scale-95">
                <?= icon('eye', 'w-3.5 h-3.5') ?>
                <span>Preview Live Card</span>
            </button>
            <button type="button" data-open-modal="dc-qr" class="flex items-center justify-center gap-1.5 px-3 py-2 bg-zinc-900 hover:bg-zinc-800 border border-[#f3bd35]/30 text-xs font-semibold text-[#ffda72] rounded-xl transition-colors">
                <?= icon('qr-code', 'w-3.5 h-3.5') ?>
                <span>My QR</span>
            </button>
            <button type="button" data-copy="<?= e($shareUrl) ?>" data-copied-html="<?= e(icon('check', 'w-3.5 h-3.5 text-emerald-400') . '<span>Copied</span>') ?>" class="flex items-center justify-center gap-1.5 px-3 py-2 bg-zinc-900 hover:bg-zinc-800 border border-zinc-700 text-xs font-semibold text-zinc-200 rounded-xl transition-colors">
                <?= icon('link', 'w-3.5 h-3.5 text-[#f3bd35]') ?>
                <span>Share</span>
            </button>
        </div>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="p-3.5 rounded-2xl bg-[#111111] border border-zinc-800 flex flex-col justify-between">
            <span class="text-[11px] text-zinc-400 font-medium">Card Impressions</span>
            <div class="text-xl font-black text-white tabular-nums mt-1"><?= num($dcMetrics['views']) ?></div>
            <span class="text-[10px] text-emerald-400 mt-1 flex items-center gap-0.5">
                <?= icon('trending-up', 'w-3 h-3') ?> +14% this month
            </span>
        </div>
        <div class="p-3.5 rounded-2xl bg-[#111111] border border-zinc-800 flex flex-col justify-between">
            <span class="text-[11px] text-zinc-400 font-medium">Physical NFC Taps</span>
            <div class="text-xl font-black text-[#f3bd35] tabular-nums mt-1"><?= num($dcMetrics['taps']) ?></div>
            <span class="text-[10px] text-zinc-500 mt-1">Hardware Card</span>
        </div>
        <div class="p-3.5 rounded-2xl bg-[#111111] border border-zinc-800 flex flex-col justify-between">
            <span class="text-[11px] text-zinc-400 font-medium">Contacts Saved</span>
            <div class="text-xl font-black text-white tabular-nums mt-1"><?= num($dcMetrics['downloads']) ?></div>
            <span class="text-[10px] text-zinc-500 mt-1">vCard downloads</span>
        </div>
        <div class="p-3.5 rounded-2xl bg-[#111111] border border-zinc-800 flex flex-col justify-between">
            <span class="text-[11px] text-zinc-400 font-medium">Active Inquiries</span>
            <div class="text-xl font-black text-white tabular-nums mt-1"><?= num($dcMetrics['connectionsCount']) ?></div>
            <span class="text-[10px] text-[#ffda72] mt-1">Syndicate Leads</span>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="space-y-4">
            <!-- Live card preview -->
            <div data-pv="card" class="<?= e($dcCardBase . ' ' . $dcThemeCls) ?>">
                <div class="absolute top-0 right-0 w-36 h-36 bg-white/5 rounded-full blur-2xl pointer-events-none"></div>
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-1.5">
                        <div class="w-6 h-6 rounded-full bg-[#f3bd35] text-black font-black text-[10px] flex items-center justify-center">88</div>
                        <span class="text-[10px] font-bold tracking-widest uppercase">MMMC EXECUTIVE</span>
                    </div>
                    <span class="text-[9px] px-2 py-0.5 rounded-full bg-white/10 font-semibold uppercase border border-current"><?= e($profile['tier']) ?></span>
                </div>
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-14 h-14 rounded-full p-[2px] bg-gradient-to-tr from-[#ffda72] to-[#8f6510] flex-shrink-0 overflow-hidden">
                        <img data-pv="avatar" src="<?= e(media_url($dcAvatar)) ?>" alt="<?= e($dcName) ?>" class="w-full h-full object-cover rounded-full"<?= $dcAvatar === '' ? ' hidden' : '' ?>>
                        <div data-pv="initial" class="w-full h-full rounded-full bg-zinc-900 flex items-center justify-center font-bold text-base text-[#ffda72]"<?= $dcAvatar === '' ? '' : ' hidden' ?>><?= e($dcInitial) ?></div>
                    </div>
                    <div class="min-w-0">
                        <h4 class="font-extrabold text-white text-base truncate" data-pv="name"><?= e($dcName) ?></h4>
                        <p class="text-[11px] text-[#ffda72] truncate" data-pv="title"><?= e($profile['title']) ?></p>
                        <p class="text-[10px] text-zinc-400 truncate" data-pv="company"><?= e($profile['company']) ?></p>
                        <p class="text-[9px] text-zinc-500 truncate flex items-center gap-0.5 mt-0.5" data-pv="location-row"<?= (string) $profile['location'] === '' ? ' hidden' : '' ?>>
                            <?= icon('map-pin', 'w-2.5 h-2.5') ?> <span data-pv="location"><?= e($profile['location']) ?></span>
                        </p>
                    </div>
                </div>
                <p class="text-[10px] italic text-zinc-300 border-l-2 border-[#f3bd35] pl-2 my-2 line-clamp-2" data-pv="quote"<?= (string) $profile['quote'] === '' ? ' hidden' : '' ?>>"<?= e($profile['quote']) ?>"</p>
                <div class="flex flex-wrap gap-1 my-3" data-pv="services"<?= $dcServices ? '' : ' hidden' ?>>
                    <?php foreach (array_slice($dcServices, 0, 3) as $svc): ?>
                        <span class="px-2 py-0.5 bg-black/40 rounded-md text-[9px] text-zinc-300"><?= e($svc) ?></span>
                    <?php endforeach; ?>
                </div>
                <div class="pt-3 border-t border-white/10 text-[10px] text-zinc-400 flex items-center justify-between">
                    <div>
                        <span class="block text-[8px] uppercase tracking-wider text-zinc-500">NFC Serial</span>
                        <span class="font-mono text-zinc-200"><?= e($profile['nfcCardId']) ?></span>
                    </div>
                    <div class="text-right">
                        <span class="block text-[8px] uppercase tracking-wider text-zinc-500">Theme</span>
                        <span class="text-zinc-200 capitalize" data-pv="theme"><?= e($dcTheme !== '' ? $dcTheme : 'Gold') ?></span>
                    </div>
                </div>
            </div>
            <div class="p-4 rounded-2xl bg-[#111111] border border-zinc-800 space-y-2 text-xs">
                <button type="button" data-dc-preview class="w-full flex items-center justify-center gap-1.5 py-2.5 bg-gradient-to-r from-[#ffda72] to-[#f3bd35] text-black font-extrabold rounded-xl shadow-md transition-all active:scale-95">
                    <?= icon('eye', 'w-3.5 h-3.5') ?>
                    <span>Preview Live Digital Card</span>
                </button>
                <a href="<?= e($dcVcardUrl) ?>" class="w-full flex items-center justify-center gap-1.5 py-2.5 bg-zinc-900 hover:bg-zinc-800 border border-zinc-700 text-zinc-200 font-semibold rounded-xl transition-colors">
                    <?= icon('download', 'w-3.5 h-3.5 text-[#f3bd35]') ?>
                    <span>Download My vCard File (.vcf)</span>
                </a>
                <a href="<?= e(url('chat')) ?>" class="w-full flex items-center justify-center gap-1.5 py-2.5 bg-zinc-900 hover:bg-zinc-800 border border-zinc-700 text-zinc-200 font-semibold rounded-xl transition-colors">
                    <?= icon('shield-check', 'w-3.5 h-3.5 text-emerald-400') ?>
                    <span>Go to E2EE Secure Messaging</span>
                </a>
            </div>
        </div>

        <div class="lg:col-span-2">
            <form id="dc-form" class="p-5 rounded-3xl bg-[#111111] border border-[#f3bd35]/25 space-y-5">
                <div class="flex items-center justify-between pb-2 border-b border-zinc-800">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-[#f3bd35] flex items-center gap-1.5">
                        <?= icon('user', 'w-4 h-4') ?> Edit My Personal Digital Business Card
                    </h3>
                    <span class="flex items-center gap-1 text-[11px] font-semibold text-emerald-400" data-dc-saved hidden role="status">
                        <?= icon('check', 'w-3.5 h-3.5') ?> Saved &amp; Live!
                    </span>
                </div>

                <div class="space-y-3">
                    <h4 class="text-[11px] font-bold text-zinc-400 uppercase tracking-wider">1. Executive Identity</h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label for="dc-name" class="<?= $dcLabel ?>">Full Name</label>
                            <input id="dc-name" name="name" type="text" required maxlength="150" value="<?= e($dcName) ?>" class="<?= $dcInput ?>">
                        </div>
                        <div>
                            <label for="dc-title" class="<?= $dcLabel ?>">Executive Title</label>
                            <input id="dc-title" name="title" type="text" required maxlength="190" value="<?= e($profile['title']) ?>" class="<?= $dcInput ?>">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label for="dc-company" class="<?= $dcLabel ?>">Company / Organization</label>
                            <input id="dc-company" name="company" type="text" required maxlength="190" value="<?= e($profile['company']) ?>" class="<?= $dcInput ?>">
                        </div>
                        <div>
                            <label for="dc-location" class="<?= $dcLabel ?>">Headquarters / Location</label>
                            <input id="dc-location" name="location" type="text" maxlength="190" value="<?= e($profile['location']) ?>" placeholder="e.g. BGC, Taguig, Philippines" class="<?= $dcInput ?>">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label for="dc-slug" class="<?= $dcLabel ?>">Your Card Link</label>
                            <input id="dc-slug" name="customSlug" type="text" maxlength="60" value="<?= e($profile['customSlug']) ?>" placeholder="e.g. juan-dela-cruz" autocomplete="off" spellcheck="false" aria-describedby="dc-slug-help" class="<?= $dcInput ?>">
                            <?php $dcCardLink = card_share_url($profile, false); ?>
                            <span id="dc-slug-help" class="block text-[10px] text-zinc-400 mt-1">Your card opens at <a href="<?= e($dcCardLink) ?>" target="_blank" rel="noopener" class="text-[#ffda72] hover:text-white break-all"><?= e(preg_replace('#^https?://#', '', $dcCardLink)) ?></a>. Letters, numbers and dashes; leave it empty to use your name. Changing it changes the link.</span>
                            <?php if (($dcDomain = domain_card_domain($user['id'])) !== null): ?>
                                <span class="block text-[10px] text-emerald-300 mt-1">Your card is shared at <?= e($dcDomain) ?> (Your .com Domain, below). This link still works too.</span>
                            <?php endif; ?>
                        </div>
                        <div data-dc-photo-box>
                            <span class="<?= $dcLabel ?>">Profile Picture</span>
                            <div class="flex items-center gap-3">
                                <div class="w-14 h-14 rounded-full bg-zinc-900 border border-[#f3bd35]/40 overflow-hidden flex items-center justify-center text-[#ffda72] font-bold text-base shrink-0">
                                    <img data-dc-photo src="<?= e(media_url($dcAvatar)) ?>" alt="Your profile picture" class="w-full h-full object-cover"<?= $dcAvatar === '' ? ' hidden' : '' ?>>
                                    <span data-dc-photo-initial<?= $dcAvatar === '' ? '' : ' hidden' ?>><?= e(mb_strtoupper(mb_substr($dcName, 0, 1))) ?></span>
                                </div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <button type="button" data-dc-photo-pick class="px-3 py-1.5 rounded-lg bg-[#f3bd35] hover:bg-[#ffda72] text-black text-[11px] font-bold transition-colors disabled:opacity-50">Upload photo</button>
                                    <button type="button" data-dc-photo-remove class="px-3 py-1.5 rounded-lg bg-zinc-900 hover:bg-zinc-800 border border-zinc-700 text-zinc-300 text-[11px] font-semibold transition-colors disabled:opacity-50"<?= $dcAvatar === '' ? ' hidden' : '' ?>>Remove</button>
                                    <input type="file" data-dc-photo-file accept="image/jpeg,image/png,image/webp,image/gif" aria-label="Choose a profile picture" hidden>
                                </div>
                            </div>
                            <span class="block text-[10px] text-zinc-500 mt-1">JPEG, PNG, WebP or GIF, up to 5 MB. It shows on your card straight away; a square photo of your face looks best.</span>
                            <details class="mt-1.5">
                                <summary class="text-[10px] text-zinc-400 hover:text-white cursor-pointer">Or use a picture's web address</summary>
                                <input id="dc-avatar" name="avatar" type="text" maxlength="500" value="<?= e($dcAvatar) ?>" placeholder="https://..." aria-label="Profile picture web address" class="<?= $dcInput ?> mt-1.5">
                            </details>
                        </div>
                    </div>
                    <div>
                        <label for="dc-quote" class="<?= $dcLabel ?>">Personal Motto / Quote</label>
                        <input id="dc-quote" name="quote" type="text" maxlength="255" value="<?= e($profile['quote']) ?>" placeholder="e.g. Investing in Southeast Asian pioneers..." class="<?= $dcInput ?>">
                    </div>
                    <div>
                        <label for="dc-bio" class="<?= $dcLabel ?>">Executive Bio / Professional Summary</label>
                        <textarea id="dc-bio" name="bio" rows="3" maxlength="5000" class="<?= $dcInput ?>"><?= e($profile['bio']) ?></textarea>
                    </div>
                </div>

                <div class="pt-3 border-t border-zinc-800 space-y-3">
                    <h4 class="text-[11px] font-bold text-zinc-400 uppercase tracking-wider flex items-center gap-1.5">
                        <?= icon('sparkles', 'w-3.5 h-3.5 text-[#f3bd35]') ?> 2. Personal Card Banner Headline
                    </h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label for="dc-headline" class="<?= $dcLabel ?>">Card Hero Headline</label>
                            <input id="dc-headline" name="headline" type="text" maxlength="255" value="<?= e($profile['headline']) ?>" placeholder="e.g. INNOVATE. INVEST. SCALE." class="<?= $dcInput ?>">
                        </div>
                        <div>
                            <label for="dc-subtitle" class="<?= $dcLabel ?>">Card Hero Subtitle</label>
                            <input id="dc-subtitle" name="subtitle" type="text" maxlength="255" value="<?= e($profile['subtitle']) ?>" placeholder="e.g. Early-stage venture capitalist in fintech..." class="<?= $dcInput ?>">
                        </div>
                    </div>
                </div>

                <div class="pt-3 border-t border-zinc-800 space-y-3">
                    <h4 class="text-[11px] font-bold text-zinc-400 uppercase tracking-wider flex items-center gap-1.5">
                        <?= icon('phone', 'w-3.5 h-3.5 text-[#f3bd35]') ?> 3. Direct Contact Details
                    </h4>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label for="dc-phone" class="<?= $dcLabel ?>">Direct Mobile Phone</label>
                            <input id="dc-phone" name="phone" type="text" maxlength="60" value="<?= e($profile['phone']) ?>" class="<?= $dcInput ?>">
                        </div>
                        <div>
                            <label for="dc-email" class="<?= $dcLabel ?>">Official Email</label>
                            <input id="dc-email" name="email" type="email" maxlength="190" value="<?= e($profile['email']) ?>" class="<?= $dcInput ?>">
                        </div>
                        <div>
                            <label for="dc-whatsapp" class="<?= $dcLabel ?>">WhatsApp Direct</label>
                            <input id="dc-whatsapp" name="whatsapp" type="text" maxlength="60" value="<?= e($profile['whatsapp']) ?>" class="<?= $dcInput ?>">
                        </div>
                    </div>
                </div>

                <div class="pt-3 border-t border-zinc-800 space-y-3">
                    <h4 class="text-[11px] font-bold text-zinc-400 uppercase tracking-wider flex items-center gap-1.5">
                        <?= icon('globe', 'w-3.5 h-3.5 text-[#f3bd35]') ?> 4. Online Channels &amp; Profiles
                    </h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <input name="social_linkedin" type="text" maxlength="255" value="<?= e($dcSocials['linkedin'] ?? '') ?>" placeholder="LinkedIn Profile URL" aria-label="LinkedIn Profile URL" class="<?= $dcInput ?>">
                        <input name="social_website" type="text" maxlength="255" value="<?= e($dcSocials['website'] ?? '') ?>" placeholder="Company / Portfolio Website URL" aria-label="Company / Portfolio Website URL" class="<?= $dcInput ?>">
                        <input name="social_twitter" type="text" maxlength="255" value="<?= e($dcSocials['twitter'] ?? '') ?>" placeholder="X (Twitter) URL" aria-label="X (Twitter) URL" class="<?= $dcInput ?>">
                        <input name="social_telegram" type="text" maxlength="255" value="<?= e($dcSocials['telegram'] ?? '') ?>" placeholder="Telegram Username / Link" aria-label="Telegram Username / Link" class="<?= $dcInput ?>">
                    </div>
                </div>

                <div class="pt-3 border-t border-zinc-800 space-y-3">
                    <h4 class="text-[11px] font-bold text-zinc-400 uppercase tracking-wider flex items-center gap-1.5">
                        <?= icon('award', 'w-3.5 h-3.5 text-[#f3bd35]') ?> 5. Services &amp; Specialties Tags
                    </h4>
                    <div class="flex flex-wrap gap-2 mb-2" data-dc-tags>
                        <?php foreach ($dcServices as $svc): ?>
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-zinc-900 border border-zinc-700 text-xs text-zinc-200" data-tag="<?= e($svc) ?>">
                                <span><?= e($svc) ?></span>
                                <button type="button" data-tag-remove class="text-zinc-500 hover:text-red-400" aria-label="Remove <?= e($svc) ?>">
                                    <?= icon('trash-2', 'w-3 h-3') ?>
                                </button>
                            </span>
                        <?php endforeach; ?>
                    </div>
                    <div class="flex gap-2">
                        <input type="text" data-tag-input maxlength="60" placeholder="Add specialty (e.g. Series A Advisory, Angel Syndicate)..." aria-label="Add specialty" class="flex-1 px-3 py-2 rounded-xl bg-zinc-900 border border-zinc-700 text-xs text-white focus:outline-none focus:border-[#f3bd35]">
                        <button type="button" data-tag-add class="px-4 py-2 bg-zinc-800 hover:bg-[#f3bd35] hover:text-black text-white text-xs font-semibold rounded-xl transition-colors">
                            Add Tag
                        </button>
                    </div>
                </div>

                <div class="pt-3 border-t border-zinc-800 space-y-3">
                    <h4 class="text-[11px] font-bold text-zinc-400 uppercase tracking-wider flex items-center gap-1.5">
                        <?= icon('palette', 'w-3.5 h-3.5 text-[#f3bd35]') ?> 6. Physical NFC Card Hardware &amp; Theme
                    </h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label for="dc-theme" class="<?= $dcLabel ?>">Card Metallic Theme</label>
                            <select id="dc-theme" name="cardTheme" class="<?= $dcInput ?>">
                                <?php foreach (['gold' => 'Gold Foil Luxury (Flagship)', 'obsidian' => 'Obsidian Matte Black', 'platinum' => 'Platinum Brushed Titanium', 'rosegold' => 'Rose Gold Metal'] as $v => $label): ?>
                                    <option value="<?= e($v) ?>"<?= ($dcTheme ?: 'gold') === $v ? ' selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="dc-nfc" class="<?= $dcLabel ?>">NFC Physical Tap Destination</label>
                            <select id="dc-nfc" name="nfcAction" class="<?= $dcInput ?>">
                                <?php foreach (['card' => 'Interactive 1EVOAI Card Profile', 'vcard' => 'Instant Save vCard File to Phonebook', 'whatsapp' => 'Open WhatsApp Direct Message'] as $v => $label): ?>
                                    <option value="<?= e($v) ?>"<?= $dcNfcAction === $v ? ' selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="p-3.5 rounded-2xl bg-zinc-950 border border-zinc-800 flex items-center justify-between text-xs">
                        <div class="flex items-center gap-2">
                            <?= icon('smartphone', 'w-4 h-4 text-[#f3bd35]') ?>
                            <div>
                                <span class="font-semibold text-white block">Physical NFC Smart Card</span>
                                <span class="text-[10px] text-zinc-400">Hardware Serial: <?= e($profile['nfcCardId']) ?></span>
                            </div>
                        </div>
                        <span class="px-2 py-0.5 rounded-full bg-emerald-500/15 border border-emerald-500/30 text-emerald-400 text-[10px] font-bold">
                            <?= e($profile['membershipStatus']) ?>
                        </span>
                    </div>
                </div>

                <div class="pt-3 border-t border-zinc-800 space-y-3">
                    <div class="flex items-center justify-between">
                        <h4 class="text-[11px] font-bold text-zinc-400 uppercase tracking-wider flex items-center gap-1.5">
                            <?= icon('credit-card', 'w-3.5 h-3.5 text-[#f3bd35]') ?> 7. Online Membership Payments &amp; Society Renewal
                        </h4>
                        <span class="text-[10px] text-emerald-400 bg-emerald-500/10 border border-emerald-500/20 px-2 py-0.5 rounded-full font-semibold">
                            Instant Activation
                        </span>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                        <div class="p-3 rounded-2xl bg-zinc-950 border border-zinc-800/80 hover:border-[#f3bd35]/40 transition-all flex flex-col justify-between">
                            <div>
                                <div class="flex items-center justify-between mb-1">
                                    <span class="text-xs font-bold text-white">Annual Society Renewal</span>
                                    <span class="text-xs font-black text-zinc-300 font-mono">₱2,000</span>
                                </div>
                                <p class="text-[11px] text-zinc-400 mb-3">Extends 1 year of verified digital credentials &amp; breakfast syndicate access.</p>
                            </div>
                            <button type="button" data-checkout="renewal" class="w-full py-2 bg-zinc-900 hover:bg-zinc-800 text-zinc-200 font-bold text-xs rounded-xl transition-all flex items-center justify-center gap-1.5 border border-zinc-700">
                                <?= icon('credit-card', 'w-3.5 h-3.5 text-[#f3bd35]') ?>
                                <span>Pay Renewal (₱2,000)</span>
                            </button>
                        </div>
                        <div class="p-3 rounded-2xl bg-zinc-950 border border-[#f3bd35]/30 hover:border-[#f3bd35] transition-all flex flex-col justify-between">
                            <div>
                                <div class="flex items-center justify-between mb-1">
                                    <span class="text-xs font-bold text-white">Upgrade to Chairman VIP</span>
                                    <span class="text-xs font-black text-[#ffda72] font-mono">₱14,000</span>
                                </div>
                                <p class="text-[11px] text-zinc-400 mb-3">7 NFC cards, 7 custom portals, mastermind dinners &amp; VIP access.</p>
                            </div>
                            <button type="button" data-checkout="membership_chairmans" class="w-full py-2 bg-gradient-to-r from-[#ffda72] to-[#f3bd35] text-black font-extrabold text-xs rounded-xl transition-all flex items-center justify-center gap-1.5 shadow">
                                <?= icon('credit-card', 'w-3.5 h-3.5') ?>
                                <span>VIP Upgrade (₱14,000)</span>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="pt-2 flex flex-col sm:flex-row items-center gap-3">
                    <button type="submit" class="w-full sm:flex-1 flex items-center justify-center gap-2 py-3 bg-gradient-to-r from-[#ffda72] to-[#f3bd35] text-black font-extrabold text-xs rounded-xl shadow-lg hover:brightness-105 active:scale-95 transition-all disabled:opacity-50">
                        <?= icon('save', 'w-4 h-4 text-black') ?>
                        <span data-dc-save-label>Save My Member Card Changes</span>
                    </button>
                    <button type="button" data-dc-preview class="w-full sm:w-auto px-6 py-3 bg-zinc-900 hover:bg-zinc-800 border border-zinc-700 text-zinc-200 font-bold text-xs rounded-xl transition-colors flex items-center justify-center gap-2">
                        <?= icon('eye', 'w-4 h-4 text-[#f3bd35]') ?>
                        <span>Preview Live</span>
                    </button>
                </div>
            </form>

            <?php
            // Card Design (includes/cards.php, assets/js/views/bo-card-design.js): colours, font, cover photo, blocks.
            $ddDesign = card_design($user['id']);
            [$ddAccent] = card_design_colors($ddDesign);
            $pageData['cardDesign'] = ['design' => $ddDesign];
            ?>
            <form id="dc-design-form" class="mt-6 p-5 rounded-3xl bg-[#111111] border border-[#f3bd35]/25 space-y-5" novalidate>
                <div class="flex items-center justify-between gap-3 pb-2 border-b border-zinc-800">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-[#f3bd35] flex items-center gap-1.5"><?= icon('palette', 'w-4 h-4') ?> Card Design</h3>
                    <a href="<?= e($dcQrUrl) ?>" target="_blank" rel="noopener" class="text-[11px] text-[#ffda72] hover:text-white flex items-center gap-1"><?= icon('external-link', 'w-3 h-3') ?><span>Open my card</span></a>
                </div>
                <p class="text-xs text-zinc-400">Make your 1EVOAI card your own: its colour, font and cover photo, and which parts show in what order.</p>

                <div class="space-y-2">
                    <span class="block text-[11px] font-bold text-zinc-400 uppercase tracking-wider">Colour</span>
                    <input type="hidden" name="theme" value="<?= e($ddDesign['theme']) ?>">
                    <div class="flex flex-wrap gap-2" role="radiogroup" aria-label="Colour theme">
                        <?php foreach (CARD_THEMES as $tid => [$tAccent, $tLight, $tName]): $tOn = $ddDesign['accent'] === '' && $ddDesign['theme'] === $tid; ?>
                            <button type="button" data-dd-theme="<?= e($tid) ?>" role="radio" aria-checked="<?= $tOn ? 'true' : 'false' ?>" class="evo-swatch<?= $tOn ? ' is-on' : '' ?>">
                                <span class="evo-swatch-dot" style="background: linear-gradient(135deg, <?= e($tLight) ?>, <?= e($tAccent) ?>)"></span>
                                <span><?= e($tName) ?></span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                    <label class="flex items-center gap-2 text-xs text-zinc-300">
                        <input type="checkbox" data-dd-own<?= $ddDesign['accent'] !== '' ? ' checked' : '' ?> class="evo-check">
                        <span>Use my own colour</span>
                        <input type="color" name="accent" value="<?= e($ddDesign['accent'] !== '' ? $ddDesign['accent'] : $ddAccent) ?>" aria-label="My own colour" class="w-10 h-7 rounded-md bg-zinc-900 border border-zinc-700 cursor-pointer disabled:opacity-40"<?= $ddDesign['accent'] !== '' ? '' : ' disabled' ?>>
                    </label>
                    <span class="block text-[10px] text-zinc-500">A very dark colour is lightened a little so buttons stay readable.</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="dd-font" class="<?= $dcLabel ?>">Font</label>
                        <select id="dd-font" name="font" class="<?= $dcInput ?>">
                            <?php foreach (CARD_FONTS as $fid => $fName): ?>
                                <option value="<?= e($fid) ?>"<?= $ddDesign['font'] === $fid ? ' selected' : '' ?>><?= e($fName) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div data-dd-cover-box>
                        <span class="<?= $dcLabel ?>">Cover Photo</span>
                        <div class="h-16 rounded-xl overflow-hidden bg-zinc-950 border border-zinc-800 flex items-center justify-center text-[10px] text-zinc-500">
                            <img data-dd-cover src="<?= e(media_url($ddDesign['cover'])) ?>" alt="Your cover photo" class="w-full h-full object-cover"<?= $ddDesign['cover'] === '' ? ' hidden' : '' ?>>
                            <span data-dd-nocover<?= $ddDesign['cover'] === '' ? '' : ' hidden' ?>>No cover photo</span>
                        </div>
                        <div class="flex items-center gap-2 mt-2">
                            <button type="button" data-dd-cover-pick class="px-3 py-1.5 rounded-lg bg-[#f3bd35] hover:bg-[#ffda72] text-black text-[11px] font-bold transition-colors disabled:opacity-50">Upload cover</button>
                            <button type="button" data-dd-cover-remove class="px-3 py-1.5 rounded-lg bg-zinc-900 hover:bg-zinc-800 border border-zinc-700 text-zinc-300 text-[11px] font-semibold transition-colors disabled:opacity-50"<?= $ddDesign['cover'] === '' ? ' hidden' : '' ?>>Remove</button>
                            <input type="file" data-dd-cover-file accept="image/jpeg,image/png,image/webp,image/gif" aria-label="Choose a cover photo" hidden>
                        </div>
                        <span class="block text-[10px] text-zinc-500 mt-1">A wide photo (about 3:1) across the top of your card. Saved straight away.</span>
                    </div>
                </div>

                <div class="space-y-2">
                    <span class="block text-[11px] font-bold text-zinc-400 uppercase tracking-wider">Card Sections</span>
                    <span class="block text-[10px] text-zinc-500">Tick what shows under your profile, and use the arrows to put it in your order.</span>
                    <ol data-dd-blocks class="rounded-2xl border border-zinc-800 divide-y divide-zinc-800 overflow-hidden">
                        <?php foreach ($ddDesign['blocks'] as $bi => $b): ?>
                            <li data-dd-block="<?= e($b['id']) ?>" class="flex items-center justify-between gap-3 px-3 py-2 bg-zinc-950 text-xs">
                                <label class="flex items-center gap-2 text-zinc-200 min-w-0">
                                    <input type="checkbox" data-dd-show<?= $b['show'] ? ' checked' : '' ?> class="evo-check">
                                    <span class="truncate"><?= e(CARD_BLOCKS[$b['id']]) ?></span>
                                </label>
                                <span class="flex items-center gap-1 shrink-0">
                                    <button type="button" data-dd-up class="px-2 py-0.5 rounded-md bg-zinc-900 border border-zinc-700 text-zinc-300 hover:text-white disabled:opacity-40" aria-label="Move up">↑</button>
                                    <button type="button" data-dd-down class="px-2 py-0.5 rounded-md bg-zinc-900 border border-zinc-700 text-zinc-300 hover:text-white disabled:opacity-40" aria-label="Move down">↓</button>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                </div>

                <button type="submit" class="w-full sm:w-auto px-6 py-3 bg-gradient-to-r from-[#ffda72] to-[#f3bd35] text-black font-extrabold text-xs rounded-xl shadow-lg hover:brightness-105 flex items-center justify-center gap-2 disabled:opacity-60"><?= icon('save', 'w-4 h-4') ?><span>Save Card Design</span></button>
            </form>
        </div>
    </div>

    <?php require __DIR__ . '/domains.php'; ?>
</div>

    <!-- My QR modal (reference component "hn") -->
    <div data-modal="dc-qr" hidden class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-md p-4 animate-fadeIn" role="dialog" aria-modal="true" aria-label="Scan to Connect">
        <div class="w-full max-w-sm rounded-3xl bg-[#121212] border border-[#f3bd35]/40 p-6 shadow-2xl text-center relative overflow-hidden">
            <div class="absolute top-0 left-1/2 -translate-x-1/2 w-48 h-48 bg-[#f3bd35]/10 rounded-full blur-3xl pointer-events-none"></div>
            <button type="button" data-close-modal class="absolute top-4 right-4 text-zinc-400 hover:text-white p-1 rounded-full transition-colors" aria-label="Close">
                <?= icon('x', 'w-5 h-5') ?>
            </button>
            <div class="mb-4">
                <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-[#f3bd35]/15 border border-[#f3bd35]/30 text-[#f3bd35] text-[11px] font-semibold tracking-wider uppercase mb-2">
                    <?= icon('shield-check', 'w-3.5 h-3.5') ?>
                    Verified NFC Digital Card
                </div>
                <h3 class="text-lg font-bold text-white tracking-wide">Scan to Connect</h3>
                <p class="text-xs text-zinc-400 mt-1"><?= e($dcName) ?> · 88MMM Circle</p>
            </div>
            <div class="p-4 bg-white rounded-2xl mx-auto shadow-inner flex flex-col items-center justify-center my-4">
                <svg id="public-qr-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200" class="w-48 h-48" role="img" aria-label="QR code for <?= e($dcQrUrl) ?>">
                    <rect width="200" height="200" fill="#ffffff"/>
                    <g data-qr-modules>
                        <rect x="15" y="15" width="45" height="45" rx="6" fill="#090909"/>
                        <rect x="23" y="23" width="29" height="29" rx="4" fill="#ffffff"/>
                        <rect x="29" y="29" width="17" height="17" rx="3" fill="#090909"/>
                        <rect x="140" y="15" width="45" height="45" rx="6" fill="#090909"/>
                        <rect x="148" y="23" width="29" height="29" rx="4" fill="#ffffff"/>
                        <rect x="154" y="29" width="17" height="17" rx="3" fill="#090909"/>
                        <rect x="15" y="140" width="45" height="45" rx="6" fill="#090909"/>
                        <rect x="23" y="148" width="29" height="29" rx="4" fill="#ffffff"/>
                        <rect x="29" y="154" width="17" height="17" rx="3" fill="#090909"/>
                        <g fill="#181818">
                            <?php foreach ([[70,20],[85,20],[100,20],[115,20],[70,35],[100,35],[125,35],[20,70],[35,70],[50,70],[70,70],[90,70],[120,70],[140,70],[160,70],[20,90],[50,90],[70,90],[140,90],[170,90],[35,110],[70,110],[110,110],[150,110],[70,130],[90,130],[130,130],[160,130],[70,150],[110,150],[140,150],[80,170],[100,170],[120,170],[150,170]] as $q): ?>
                                <rect x="<?= $q[0] ?>" y="<?= $q[1] ?>" width="8" height="8" rx="2"/>
                            <?php endforeach; ?>
                        </g>
                    </g>
                    <circle cx="100" cy="100" r="18" fill="#090909" stroke="#f3bd35" stroke-width="2"/>
                    <text x="100" y="105" font-size="11" font-weight="900" fill="#f3bd35" text-anchor="middle" font-family="sans-serif">88</text>
                </svg>
            </div>
            <p class="text-[11px] text-zinc-400 mb-5 break-all font-mono"><?= e($dcQrUrl) ?></p>
            <button type="button" data-qr-download class="w-full flex items-center justify-center gap-2 py-3 bg-gradient-to-r from-[#ffda72] to-[#f3bd35] text-black font-bold text-xs rounded-xl shadow-lg hover:brightness-105 active:scale-95 transition-transform">
                <?= icon('download', 'w-4 h-4') ?>
                Download QR Image
            </button>
        </div>
    </div>

    <?php require __DIR__ . '/../partials/checkout_modal.php'; ?>
</div>
