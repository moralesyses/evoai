<?php
/**
 * App shell (header navigation, mobile bottom navigation). Always the dark theme.
 *
 * Expects: $view, $user (array|null), $content (rendered view HTML),
 *          $pageScripts (list of asset paths), $pageData (array for JS).
 */
$navItems = [
    'main'       => ['icon' => 'house', 'label' => 'Main Portal', 'short' => 'Main', 'bottom' => 'Main'],
    'card'       => ['icon' => 'credit-card', 'label' => '1EVOAI Card', 'short' => 'Card', 'bottom' => 'Card'],
    'backoffice' => ['icon' => 'user-check', 'label' => 'Member Backoffice', 'short' => 'Backoffice', 'bottom' => 'Backoffice'],
    'admin'      => ['icon' => 'shield', 'label' => 'Management Admin', 'short' => 'Admin', 'bottom' => 'Admin'],
    'chat'       => ['icon' => 'message-square-lock', 'label' => 'E2EE Chat', 'short' => 'Chat', 'bottom' => 'E2EE'],
];
// Management Admin is only in the menus of admins; members and visitors don't see it.
if (!$user || !is_admin($user)) {
    unset($navItems['admin']);
}
$navOn = 'bg-gradient-to-r from-[#ffda72] to-[#f3bd35] text-black shadow-md';
$navOff = 'text-zinc-400 hover:text-white';
// The header account switcher (a list of every member) is a demo-mode convenience only: never in production.
$canSwitch = $user && demo_mode();
$switchUsers = $canSwitch ? db_all("SELECT id, name, role, title FROM users WHERE status = 'active' AND role <> 'visitor' ORDER BY created_at, id") : [];
$jsConfig = [
    'csrf' => csrf_token(),
    'base' => base_path(),
    'user' => $user ? user_public($user) : null,
    'demo' => demo_mode(),
    'view' => $view,
];
?><!doctype html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
<?php if (!empty($pageMeta)): /* Per-page meta (e.g. a person's digital card). */ ?>
    <title><?= e($pageMeta['title']) ?></title>
    <meta name="description" content="<?= e($pageMeta['description'] ?? '') ?>">
    <?php if (!empty($pageMeta['keywords'])): ?><meta name="keywords" content="<?= e($pageMeta['keywords']) ?>"><?php endif; ?>
    <?php if (!empty($pageMeta['noindex'])): ?><meta name="robots" content="noindex"><?php endif; ?>
    <?php if (!empty($pageMeta['url'])): ?><link rel="canonical" href="<?= e($pageMeta['url']) ?>"><meta property="og:url" content="<?= e($pageMeta['url']) ?>"><?php endif; ?>
    <meta property="og:type" content="<?= e($pageMeta['type'] ?? 'website') ?>">
    <meta property="og:title" content="<?= e($pageMeta['title']) ?>">
    <meta property="og:description" content="<?= e($pageMeta['description'] ?? '') ?>">
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="<?= e($pageMeta['title']) ?>">
    <meta name="twitter:description" content="<?= e($pageMeta['description'] ?? '') ?>">
    <?php if (!empty($pageMeta['image'])): ?><meta property="og:image" content="<?= e($pageMeta['image']) ?>"><meta name="twitter:image" content="<?= e($pageMeta['image']) ?>"><?php endif; ?>
<?php else: ?>
    <title><?= e(config('app.name')) ?></title>
    <meta name="description" content="Mobile-optimized executive clone of 1EVOAI Mike Maranan portal with real-time end-to-end encrypted messaging, security dashboard, CMS, and admin management.">
    <meta property="og:title" content="1EVOAI Mike Maranan - E2EE Executive Portal &amp; Mobile Hub">
    <meta property="og:description" content="Mobile-optimized executive clone of 1EVOAI Mike Maranan portal with real-time end-to-end encrypted messaging, security dashboard, CMS, and admin management.">
    <meta property="og:type" content="website">
    <meta name="twitter:card" content="summary_large_image">
<?php endif; ?>
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <?php /* The name under the icon when a page is added to an iPhone's home screen: a card's own person. */ ?>
    <meta name="apple-mobile-web-app-title" content="<?= e($pageMeta['title'] ?? 'Mike Maranan') ?>">
    <meta name="theme-color" content="#090909">
<?php if (!empty($pageMeta['favicon'])): /* e.g. the We Serve emblem on digital cards */ ?>
    <link rel="icon" type="image/png" sizes="32x32" href="<?= e($pageMeta['favicon']) ?>">
<?php else: ?>
    <link rel="icon" type="image/svg+xml" href="<?= e(base_path()) ?>/icon.svg">
<?php endif; ?>
    <link rel="apple-touch-icon" href="<?= e($pageMeta['apple_touch_icon'] ?? base_path() . '/icon.svg') ?>">
    <link rel="manifest" href="<?= e($pageMeta['manifest'] ?? base_path() . '/manifest.webmanifest') ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/custom.css')) ?>">
</head>
<body class="bg-[#090909] text-[#f7f5ef] font-sans antialiased selection:bg-[#f3bd35]/30 selection:text-[#ffda72] overflow-x-hidden">
<div id="root">
    <div class="min-h-screen bg-[#070707] text-[#f7f5ef] flex flex-col selection:bg-[#f3bd35]/30">
        <header class="sticky top-0 z-40 bg-[#0c0c0c]/95 backdrop-blur-md border-b border-[#f3bd35]/20 px-3 sm:px-6 py-2.5 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <a href="<?= e(url('main')) ?>" class="cursor-pointer flex items-center gap-2 group" title="Return to Main Portal">
                    <div class="w-8 h-8 rounded-full bg-gradient-to-br from-[#ffda72] to-[#996f13] p-[1.5px] flex items-center justify-center shadow-sm group-hover:scale-105 transition-transform">
                        <div class="w-full h-full bg-[#090909] rounded-full flex items-center justify-center">
                            <span class="text-[#f3bd35] font-black text-xs">88</span>
                        </div>
                    </div>
                    <div class="hidden sm:block">
                        <span class="text-xs font-bold tracking-widest text-[#ffda72] uppercase block group-hover:text-amber-200 transition-colors">88MMM CIRCLE</span>
                        <span class="text-[10px] text-zinc-400 block -mt-0.5">Executive Portal &amp; Hub</span>
                    </div>
                </a>
            </div>
            <?php if ($user): /* Visitors who haven't signed in get no menus: just the logo and Sign In. */ ?>
            <nav class="flex items-center gap-1 p-1 bg-[#121212] rounded-2xl border border-[#f3bd35]/30" aria-label="Primary">
                <?php foreach ($navItems as $key => $item): ?>
                    <a href="<?= e(url($key)) ?>"<?= $view === $key ? ' aria-current="page"' : '' ?> class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold transition-all <?= $view === $key ? $navOn : $navOff ?>">
                        <?= icon($item['icon'], 'w-3.5 h-3.5') ?>
                        <span class="hidden sm:inline"><?= e($item['label']) ?></span>
                        <span class="sm:hidden"><?= e($item['short']) ?></span>
                        <?php if ($key === 'admin'): ?>
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse hidden sm:inline"></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </nav>
            <?php endif; ?>
            <div class="flex items-center gap-2">
                <button type="button" id="pwa-ios" hidden data-open-modal="ios-install" class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-[#f3bd35] border border-[#f3bd35]/40 hover:bg-[#f3bd35]/10 active:scale-95 transition-all rounded-lg whitespace-nowrap">
                    <?= icon('smartphone', 'w-3.5 h-3.5') ?><span>Add to iOS</span>
                </button>
                <?php if ($user): ?>
                    <div class="relative" data-dropdown>
                        <button type="button" data-dropdown-toggle aria-expanded="false" class="flex items-center gap-2 px-2.5 py-1.5 rounded-xl bg-zinc-900 hover:bg-zinc-800 border border-[#f3bd35]/30 text-xs transition-colors">
                            <div class="w-5 h-5 rounded-full bg-[#f3bd35] text-black font-bold text-[10px] flex items-center justify-center"><?= e(mb_substr($user['name'], 0, 1)) ?></div>
                            <span class="font-semibold text-white max-w-[80px] sm:max-w-none truncate"><?= e($user['name']) ?></span>
                            <span class="text-[9px] uppercase px-1.5 py-0.2 rounded-md bg-zinc-800 text-[#f3bd35] font-mono hidden sm:inline"><?= e($user['role']) ?></span>
                            <?= icon('chevron-down', 'w-3 h-3 text-zinc-400') ?>
                        </button>
                        <div data-dropdown-menu hidden class="absolute right-0 mt-2 w-64 rounded-2xl bg-[#141414] border border-[#f3bd35]/30 shadow-2xl p-2 z-50 animate-fadeIn">
                            <?php if ($canSwitch): ?>
                                <div class="px-2 py-1 text-[10px] font-bold uppercase tracking-wider text-zinc-400">Switch Active Account</div>
                                <div class="evo-switch-list">
                                <?php foreach ($switchUsers as $su): ?>
                                    <button type="button" <?= $su['id'] === $user['id'] ? 'aria-current="true"' : 'data-switch-user="' . e($su['id']) . '"' ?> class="w-full flex items-center justify-between p-2 rounded-xl text-left text-xs transition-colors <?= $su['id'] === $user['id'] ? 'bg-[#f3bd35]/15 text-[#ffda72]' : 'hover:bg-zinc-800 text-zinc-200' ?>">
                                        <div>
                                            <div class="font-semibold flex items-center gap-1.5">
                                                <?= e($su['name']) ?>
                                                <?php if ($su['role'] === 'admin'): ?><span class="text-[9px] px-1 py-0.2 rounded bg-amber-500/20 text-amber-300">Admin</span><?php endif; ?>
                                            </div>
                                            <div class="text-[10px] text-zinc-400"><?= e($su['title']) ?></div>
                                        </div>
                                        <?php if ($su['id'] === $user['id']): ?><?= icon('check', 'w-3.5 h-3.5 text-[#f3bd35]') ?><?php endif; ?>
                                    </button>
                                <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <div class="<?= $canSwitch ? 'mt-2 pt-2 border-t border-zinc-800' : '' ?>">
                                <button type="button" data-logout class="w-full flex items-center gap-2 p-2 rounded-xl text-left text-xs text-rose-400 hover:bg-rose-500/10 hover:text-rose-300 transition-colors">
                                    <?= icon('log-out', 'w-3.5 h-3.5') ?><span>Sign Out from Portal</span>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="<?= e(url('main')) ?>#auth-gateway-card" class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-gradient-to-r from-[#ffda72] to-[#f3bd35] hover:brightness-105 text-black font-bold text-xs shadow-md transition-all cursor-pointer">
                        <?= icon('log-in', 'w-3.5 h-3.5') ?><span>Sign In</span>
                    </a>
                <?php endif; ?>
            </div>
        </header>

        <main class="flex-1 w-full p-2 sm:p-6 pb-24 md:pb-6" id="view-<?= e($view) ?>">
            <?= $content ?>
        </main>

        <?php if ($user): ?>
        <nav class="md:hidden fixed bottom-0 left-0 right-0 z-40 bg-[#0d0d0d]/95 backdrop-blur-md border-t border-[#f3bd35]/20 px-2 py-1.5 flex items-center justify-around" aria-label="Mobile">
            <?php foreach ($navItems as $key => $item): ?>
                <a href="<?= e(url($key)) ?>" class="flex flex-col items-center justify-center min-w-[50px] min-h-[44px] transition-colors <?= $view === $key ? 'text-[#f3bd35]' : 'text-zinc-400' ?>">
                    <?= icon($item['icon'], 'w-4 h-4') ?>
                    <span class="text-[10px] font-medium mt-0.5"><?= e($item['bottom']) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
        <?php endif; ?>
    </div>
</div>

<div data-modal="ios-install" hidden class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm p-4">
    <div class="w-full max-w-sm rounded-2xl bg-[#141414] border border-[#f3bd35]/30 p-5 shadow-2xl text-left relative">
        <button type="button" data-close-modal class="absolute top-4 right-4 text-zinc-400 hover:text-white" aria-label="Close"><?= icon('x', 'w-5 h-5') ?></button>
        <h3 class="text-base font-bold text-white mb-2 flex items-center gap-2"><?= icon('smartphone', 'w-5 h-5 text-[#f3bd35]') ?>Install on iPhone / iPad</h3>
        <p class="text-xs text-zinc-300 leading-relaxed space-y-2 mb-4">
            <span>1. In Safari, tap the <strong>Share</strong> button (box with upward arrow) in the toolbar.</span><br>
            <span>2. Scroll down and select <strong>Add to Home Screen</strong>.</span><br>
            <span>3. Tap <strong>Add</strong> to experience the 88MMM Circle executive app full-screen with offline support.</span>
        </p>
        <button type="button" data-close-modal class="w-full py-2 bg-gradient-to-r from-[#ffda72] to-[#f3bd35] text-black font-semibold text-xs rounded-xl shadow-md">Got It</button>
    </div>
</div>

<?php
// 88MMM Assistant chat button (includes/assistant.php) on the portal, backoffice and admin pages, while
// its AI is on. With it off, administrators still see the button, which says which API key is missing.
$assistantOn = assistant_enabled();
$showAssistant = in_array($view, ['main', 'backoffice', 'admin'], true) && ($assistantOn || ($user && is_admin($user)));
if ($showAssistant) {
    require __DIR__ . '/partials/assistant.php';
}
?>
<div id="evo-toasts" aria-live="polite"></div>

<script>window.EVO_CONFIG = <?= js_json($jsConfig) ?>;<?php if (!empty($pageData)): ?> window.EVO_PAGE = <?= js_json($pageData) ?>;<?php endif; ?></script>
<script src="<?= e(asset('assets/js/icons.js')) ?>"></script>
<script src="<?= e(asset('assets/js/crypto.js')) ?>"></script>
<script src="<?= e(asset('assets/js/app.js')) ?>"></script>
<?php foreach ($pageScripts ?? [] as $script): ?>
<script src="<?= e(asset($script)) ?>"></script>
<?php endforeach; ?>
<?php if ($showAssistant): ?>
<script src="<?= e(asset('assets/js/assistant.js')) ?>"></script>
<?php endif; ?>
</body>
</html>
