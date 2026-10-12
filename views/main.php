<?php
/**
 * Main Portal: hero, executive sign-in / registration gateway, architecture
 * pillars, leadership council and footer.
 */
$mainPlan = mlm_complan(); // binary plan figures quoted on the page
$demoUsers = [
    ['name' => 'Mike Maranan', 'role' => 'admin', 'roleLabel' => 'Super Admin / CEO', 'email' => 'mike@mmmcircle.com', 'password' => 'admin88', 'avatar' => 'assets/images/mike_maranan_executive_portrait.svg', 'color' => 'from-amber-400 to-yellow-600'],
    ['name' => 'Sofia Cruz', 'role' => 'vip', 'roleLabel' => "Chairman's Circle (VIP)", 'email' => 'sofia.cruz@apexholdings.ph', 'password' => 'vip88', 'avatar' => null, 'color' => 'from-purple-400 to-indigo-600'],
    ['name' => 'David Tan', 'role' => 'member', 'roleLabel' => 'Executive Member', 'email' => 'david.tan@horizoncap.sg', 'password' => 'member88', 'avatar' => null, 'color' => 'from-emerald-400 to-teal-600'],
    ['name' => 'Elena Santos', 'role' => 'member', 'roleLabel' => 'Executive Member', 'email' => 'elena@biotechcorp.ph', 'password' => 'member88', 'avatar' => null, 'color' => 'from-blue-400 to-cyan-600'],
];
$demo = demo_mode();
$pageScripts[] = 'assets/js/views/main.js';
$pageData['demoUsers'] = $demo ? array_map(fn ($d) => ['name' => $d['name'], 'role' => $d['role'], 'roleLabel' => $d['roleLabel'], 'email' => $d['email'], 'password' => $d['password']], $demoUsers) : [];

$authOn = 'bg-gradient-to-r from-[#ffda72] to-[#f3bd35] text-black shadow-md';
$authOff = 'text-zinc-400 hover:text-white';
$pillarOn = 'bg-[#f3bd35] text-black shadow-lg shadow-[#f3bd35]/20';
$pillarOff = 'bg-zinc-900 border border-zinc-800 text-zinc-400 hover:text-white';
$inputLg = 'w-full pl-10 pr-4 py-3 rounded-xl bg-zinc-900 border border-zinc-700 focus:border-[#f3bd35] focus:ring-1 focus:ring-[#f3bd35] text-white text-sm placeholder-zinc-500 transition-all outline-none';
$inputReg = 'w-full pl-10 pr-4 py-2.5 rounded-xl bg-zinc-900 border border-zinc-700 focus:border-[#f3bd35] text-white text-sm placeholder-zinc-500 outline-none';
$pkgOn = 'bg-[#f3bd35]/15 border-[#f3bd35] text-white';
$pkgOff = 'bg-zinc-900 border-zinc-800 text-zinc-400 hover:border-zinc-700';
// Sign-up is paid first: through Maya Checkout (includes/signup.php), at the checkout prices,
// or with an activation code + PIN (includes/activation_codes.php).
$regPrices = ['membership_executive' => checkout_item('membership_executive')['price'], 'membership_chairmans' => checkout_item('membership_chairmans')['price']];
$regMaya = maya_enabled();
?>
<div class="w-full text-[#f7f5ef] pb-16">
    <div class="fixed inset-0 pointer-events-none overflow-hidden z-0">
        <div class="absolute -top-40 left-1/2 -translate-x-1/2 w-[700px] h-[400px] bg-gradient-to-b from-[#f3bd35]/12 via-[#ffda72]/5 to-transparent blur-3xl opacity-70"></div>
        <div class="absolute top-1/3 -left-40 w-80 h-80 bg-amber-600/5 blur-3xl"></div>
        <div class="absolute top-2/3 -right-40 w-96 h-96 bg-[#f3bd35]/5 blur-3xl"></div>
    </div>
    <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-4 sm:pt-8">
        <div class="flex flex-wrap items-center justify-between gap-3 pb-6 border-b border-zinc-800/80">
            <div class="flex items-center gap-2.5">
                <div class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-pulse shadow-sm shadow-emerald-400/50"></div>
                <span class="text-xs font-mono text-zinc-300">1EVOAI INFRASTRUCTURE: <span class="text-emerald-400 font-semibold">ALL SYSTEMS SECURE &amp; OPERATIONAL</span></span>
            </div>
            <div class="flex items-center gap-4 text-xs font-mono text-zinc-400">
                <span class="flex items-center gap-1.5 text-zinc-300"><?= icon('shield', 'w-3.5 h-3.5 text-[#f3bd35]') ?>256-Bit E2EE Cryptography</span>
                <span class="hidden sm:flex items-center gap-1.5 text-zinc-400"><?= icon('cpu', 'w-3.5 h-3.5 text-[#f3bd35]') ?>NFC Contactless Engine</span>
                <span class="flex items-center gap-1.5 text-[#ffda72]"><?= icon('sparkles', 'w-3.5 h-3.5 text-[#f3bd35]') ?>88MMM Network</span>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-12 mt-8 lg:mt-12 items-start">
            <div class="lg:col-span-7 flex flex-col justify-center">
                <div class="inline-flex items-center gap-2.5 px-3.5 py-1.5 rounded-full bg-zinc-900/90 border border-[#f3bd35]/30 backdrop-blur-md mb-6 w-fit shadow-lg shadow-black/40">
                    <span class="w-5 h-5 rounded-full bg-gradient-to-tr from-[#ffda72] to-[#996f13] text-black font-black text-[10px] flex items-center justify-center">88</span>
                    <span class="text-xs font-bold tracking-widest uppercase bg-gradient-to-r from-[#ffda72] via-[#f3bd35] to-amber-200 bg-clip-text text-transparent">88MMM CIRCLE • 1EVOAI PORTAL</span>
                    <span class="text-[10px] px-2 py-0.5 rounded-md bg-[#f3bd35]/20 text-[#ffda72] font-mono font-medium">v2.6</span>
                </div>
                <h1 class="text-3xl sm:text-5xl lg:text-6xl font-extrabold tracking-tight text-white leading-[1.12]">Sovereign Digital Identity &amp; <span class="bg-gradient-to-r from-[#ffda72] via-[#f3bd35] to-amber-400 bg-clip-text text-transparent">Executive Syndicate.</span></h1>
                <p class="mt-4 sm:mt-6 text-base sm:text-lg text-zinc-300 leading-relaxed max-w-2xl font-normal">Empowering global entrepreneurs, investors, and high-net-worth leaders with smart NFC identity cards, zero-knowledge encrypted communications, verified lead generation, and automated binary backoffice management.</p>
                <div class="grid grid-cols-2 sm:grid-cols-2 gap-3 mt-6">
                    <?php foreach ([
                        ['credit-card', 'bg-[#f3bd35]/15 border border-[#f3bd35]/30 text-[#f3bd35]', 'Smart NFC Card & VCard', 'Instant tap-to-share contactless profile & CRM capture'],
                        ['message-square-lock', 'bg-purple-500/15 border border-purple-500/30 text-purple-400', 'E2EE Cryptographic Comms', 'Military-grade AES-GCM & ECDH executive chat'],
                        ['trending-up', 'bg-emerald-500/15 border border-emerald-500/30 text-emerald-400', 'Binary Complan Backoffice', '₱' . num($mainPlan['entryFee']) . ' entry, direct referrals, & ₱' . num($mainPlan['maxDailyPairingIncome']) . ' daily pairing'],
                        ['shield', 'bg-blue-500/15 border border-blue-500/30 text-blue-400', 'Enterprise Management', 'Role permissions, real-time leads, and payment audits'],
                    ] as [$ic, $tone, $title, $desc]): ?>
                        <div class="p-3.5 rounded-2xl bg-zinc-900/60 border border-zinc-800 hover:border-[#f3bd35]/40 transition-colors flex items-start gap-3">
                            <div class="w-8 h-8 rounded-xl <?= $tone ?> flex items-center justify-center shrink-0"><?= icon($ic, 'w-4 h-4') ?></div>
                            <div>
                                <h4 class="text-xs font-bold text-white"><?= e($title) ?></h4>
                                <p class="text-[11px] text-zinc-400 mt-0.5"><?= e($desc) ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="mt-8 flex flex-wrap items-center gap-3">
                    <a href="<?= e(url('card', ['profile' => 'usr_mike'])) ?>" class="inline-flex items-center gap-2 px-5 py-3 rounded-2xl bg-gradient-to-r from-[#ffda72] to-[#f3bd35] text-black font-bold text-xs sm:text-sm hover:brightness-110 shadow-lg shadow-[#f3bd35]/20 transition-all cursor-pointer">
                        <?= icon('user', 'w-4 h-4') ?><span>Explore Mike Maranan 1EVOAI Card</span><?= icon('chevron-right', 'w-4 h-4') ?>
                    </a>
                    <button type="button" id="hero-signin" class="inline-flex items-center gap-2 px-5 py-3 rounded-2xl bg-zinc-900 hover:bg-zinc-800 border border-zinc-700 hover:border-[#f3bd35]/50 text-white font-semibold text-xs sm:text-sm transition-all cursor-pointer">
                        <?= icon('key', 'w-4 h-4 text-[#f3bd35]') ?><span>Executive Sign In</span>
                    </button>
                </div>
                <div class="grid grid-cols-4 gap-2 sm:gap-4 mt-8 pt-6 border-t border-zinc-800/80">
                    <div><div class="text-xl sm:text-2xl font-black text-[#ffda72] tabular-nums">14,280+</div><div class="text-[10px] sm:text-xs text-zinc-400 mt-0.5">Network Views</div></div>
                    <div><div class="text-xl sm:text-2xl font-black text-white tabular-nums">₱12.8M</div><div class="text-[10px] sm:text-xs text-zinc-400 mt-0.5">Syndicate Volume</div></div>
                    <div><div class="text-xl sm:text-2xl font-black text-emerald-400 tabular-nums">99.99%</div><div class="text-[10px] sm:text-xs text-zinc-400 mt-0.5">E2EE Uptime</div></div>
                    <div><div class="text-xl sm:text-2xl font-black text-amber-300 tabular-nums">4,500+</div><div class="text-[10px] sm:text-xs text-zinc-400 mt-0.5">NFC Cards Active</div></div>
                </div>
            </div>

            <div id="auth-gateway-card" class="lg:col-span-5 w-full">
                <div class="relative rounded-3xl bg-[#111111]/95 border border-[#f3bd35]/35 p-6 sm:p-8 shadow-2xl shadow-black/80 backdrop-blur-xl">
                    <div class="absolute top-0 left-1/4 right-1/4 h-[1px] bg-gradient-to-r from-transparent via-[#f3bd35] to-transparent"></div>
                    <?php if ($user): ?>
                        <div class="mb-6 p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/30">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full bg-emerald-500/20 border border-emerald-500/40 flex items-center justify-center text-emerald-400 font-bold text-sm"><?= e(mb_substr($user['name'], 0, 1)) ?></div>
                                    <div>
                                        <div class="text-xs text-emerald-300 font-semibold flex items-center gap-1.5"><?= icon('circle-check', 'w-3.5 h-3.5 text-emerald-400') ?>Authenticated Session Active</div>
                                        <div class="text-sm font-bold text-white"><?= e($user['name']) ?></div>
                                        <div class="text-[10px] text-zinc-400 capitalize"><?= e($user['role']) ?> • <?= e($user['title']) ?></div>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-4 grid grid-cols-2 gap-2">
                                <a href="<?= e(url('backoffice')) ?>" class="w-full py-2.5 px-3 rounded-xl bg-gradient-to-r from-[#ffda72] to-[#f3bd35] text-black font-bold text-xs flex items-center justify-center gap-1.5 shadow-md hover:brightness-105"><?= icon('users', 'w-3.5 h-3.5') ?>Backoffice</a>
                                <?php if ($user['role'] === 'admin'): ?>
                                    <a href="<?= e(url('admin')) ?>" class="w-full py-2.5 px-3 rounded-xl bg-zinc-800 hover:bg-zinc-700 text-white font-bold text-xs flex items-center justify-center gap-1.5 border border-zinc-600"><?= icon('shield', 'w-3.5 h-3.5 text-amber-400') ?>Admin Panel</a>
                                <?php else: ?>
                                    <a href="<?= e(url('chat')) ?>" class="w-full py-2.5 px-3 rounded-xl bg-zinc-800 hover:bg-zinc-700 text-white font-bold text-xs flex items-center justify-center gap-1.5 border border-zinc-600"><?= icon('message-square-lock', 'w-3.5 h-3.5 text-purple-400') ?>E2EE Chat</a>
                                <?php endif; ?>
                            </div>
                            <div class="mt-3 pt-3 border-t border-emerald-500/20 flex items-center justify-between text-xs">
                                <span class="text-zinc-400 text-[11px]">Need to sign into a different account?</span>
                                <button type="button" data-logout class="text-amber-400 hover:text-amber-300 text-xs font-semibold underline underline-offset-2">Sign Out</button>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="flex p-1 bg-zinc-900/90 rounded-2xl border border-zinc-800 mb-6" data-tab-group="auth" role="tablist">
                        <button type="button" data-tab="login" data-group="auth" data-on="<?= $authOn ?>" data-off="<?= $authOff ?>" class="flex-1 py-2 rounded-xl text-xs sm:text-sm font-bold transition-all flex items-center justify-center gap-2 <?= $authOn ?>">
                            <?= icon('key', 'w-3.5 h-3.5') ?><span>Executive Sign In</span>
                        </button>
                        <button type="button" data-tab="register" data-group="auth" data-on="<?= $authOn ?>" data-off="<?= $authOff ?>" class="flex-1 py-2 rounded-xl text-xs sm:text-sm font-bold transition-all flex items-center justify-center gap-2 <?= $authOff ?>">
                            <?= icon('sparkles', 'w-3.5 h-3.5') ?><span>Create Account</span>
                        </button>
                    </div>

                    <div id="login-error" hidden class="mb-4 p-3 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-300 text-xs flex items-start gap-2 animate-shake" role="alert">
                        <?= icon('circle-alert', 'w-4 h-4 shrink-0 mt-0.5 text-rose-400') ?><span data-msg></span>
                    </div>
                    <div id="login-success" hidden class="mb-4 p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 text-xs flex items-center gap-2 animate-fadeIn" role="status">
                        <?= icon('circle-check', 'w-4 h-4 shrink-0 text-emerald-400') ?><span data-msg></span>
                    </div>

                    <form id="login-form" data-tab-panel="login" data-group="auth" class="space-y-4" novalidate>
                        <div>
                            <label for="login-email" class="block text-xs font-semibold text-zinc-300 uppercase tracking-wider mb-1.5">Executive Email / Username</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-zinc-400"><?= icon('mail', 'w-4 h-4') ?></div>
                                <input id="login-email" name="email" type="email" required autocomplete="username" placeholder="e.g. mike@mmmcircle.com" class="<?= $inputLg ?>">
                            </div>
                        </div>
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <label for="login-password" class="block text-xs font-semibold text-zinc-300 uppercase tracking-wider">Security Password</label>
                                <button type="button" id="forgot-open" class="text-[11px] text-[#f3bd35] hover:text-[#ffda72] transition-colors">Forgot password?</button>
                            </div>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-zinc-400"><?= icon('lock', 'w-4 h-4') ?></div>
                                <input id="login-password" name="password" type="password" required autocomplete="current-password" placeholder="Enter your security password" class="w-full pl-10 pr-11 py-3 rounded-xl bg-zinc-900 border border-zinc-700 focus:border-[#f3bd35] focus:ring-1 focus:ring-[#f3bd35] text-white text-sm placeholder-zinc-500 transition-all outline-none">
                                <button type="button" data-toggle-password="login-password" data-title-shown="Hide password" data-title-hidden="Show password" class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-zinc-400 hover:text-zinc-200 transition-colors" title="Show password">
                                    <span data-when-hidden><?= icon('eye', 'w-4 h-4') ?></span><span data-when-shown hidden><?= icon('eye-off', 'w-4 h-4') ?></span>
                                </button>
                            </div>
                        </div>
                        <div class="flex items-center justify-between pt-1 text-xs">
                            <label class="flex items-center gap-2 cursor-pointer text-zinc-300">
                                <input type="checkbox" name="remember" checked class="w-4 h-4 rounded bg-zinc-900 border-zinc-700 text-[#f3bd35] focus:ring-[#f3bd35] focus:ring-offset-0">
                                <span>Keep executive session active</span>
                            </label>
                        </div>
                        <button type="submit" class="w-full mt-2 py-3.5 px-4 rounded-xl bg-gradient-to-r from-[#ffda72] to-[#f3bd35] hover:from-[#ffe085] hover:to-[#e5b02f] text-black font-extrabold text-sm tracking-wide flex items-center justify-center gap-2 shadow-xl shadow-[#f3bd35]/25 transition-all cursor-pointer disabled:opacity-60">
                            <span>Sign In to Executive Portal</span><?= icon('arrow-right', 'w-4 h-4') ?>
                        </button>
                        <?php if ($demo): ?>
                            <div class="pt-5 border-t border-zinc-800">
                                <div class="flex items-center justify-between mb-2.5">
                                    <span class="text-[10px] font-bold tracking-wider text-zinc-400 uppercase">Quick Demo Credentials (One-Click)</span>
                                    <span class="text-[10px] text-zinc-400 font-mono">Instant Access</span>
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <?php foreach ($demoUsers as $i => $d): ?>
                                        <button type="button" data-demo-login="<?= $i ?>" class="flex items-center gap-2 p-2 rounded-xl bg-zinc-900/90 hover:bg-zinc-800 border border-zinc-800 hover:border-[#f3bd35]/40 text-left transition-all group">
                                            <div class="w-7 h-7 rounded-lg bg-gradient-to-br <?= $d['color'] ?> text-black font-black text-xs flex items-center justify-center shrink-0 shadow-sm"><?= e(mb_substr($d['name'], 0, 1)) ?></div>
                                            <div class="min-w-0 flex-1">
                                                <div class="text-xs font-bold text-white group-hover:text-[#ffda72] truncate"><?= e($d['name']) ?></div>
                                                <div class="text-[10px] text-zinc-400 truncate font-mono"><?= e($d['roleLabel']) ?></div>
                                            </div>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </form>

                    <form id="register-form" data-tab-panel="register" data-group="auth" hidden class="space-y-3.5" novalidate>
                        <div id="register-error" hidden class="p-3 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-300 text-xs flex items-start gap-2" role="alert">
                            <?= icon('circle-alert', 'w-4 h-4 shrink-0 mt-0.5 text-rose-400') ?><span data-msg></span>
                        </div>
                        <div>
                            <label for="reg-name" class="block text-xs font-semibold text-zinc-300 uppercase tracking-wider mb-1">Full Executive Name</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-zinc-400"><?= icon('user', 'w-4 h-4') ?></div>
                                <input id="reg-name" name="name" type="text" required maxlength="150" autocomplete="name" placeholder="e.g. Atty. Manuel Ramos" class="<?= $inputReg ?>">
                            </div>
                        </div>
                        <div>
                            <label for="reg-email" class="block text-xs font-semibold text-zinc-300 uppercase tracking-wider mb-1">Corporate / Personal Email</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-zinc-400"><?= icon('mail', 'w-4 h-4') ?></div>
                                <input id="reg-email" name="email" type="email" required maxlength="190" autocomplete="email" placeholder="e.g. manuel@ramoslaw.ph" class="<?= $inputReg ?>">
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label for="reg-password" class="block text-xs font-semibold text-zinc-300 uppercase tracking-wider mb-1">Password</label>
                                <div class="relative">
                                    <input id="reg-password" name="password" type="password" required minlength="6" autocomplete="new-password" placeholder="Min. 6 chars" class="w-full px-3 py-2.5 rounded-xl bg-zinc-900 border border-zinc-700 focus:border-[#f3bd35] text-white text-sm placeholder-zinc-500 outline-none">
                                </div>
                            </div>
                            <div>
                                <label for="reg-confirm" class="block text-xs font-semibold text-zinc-300 uppercase tracking-wider mb-1">Confirm Password</label>
                                <div class="relative">
                                    <input id="reg-confirm" name="confirm" type="password" required autocomplete="new-password" placeholder="Repeat password" class="w-full px-3 py-2.5 rounded-xl bg-zinc-900 border border-zinc-700 focus:border-[#f3bd35] text-white text-sm placeholder-zinc-500 outline-none">
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center justify-between text-xs">
                            <button type="button" id="reg-toggle-pw" data-toggle-password="reg-password,reg-confirm" class="text-zinc-400 hover:text-zinc-200 flex items-center gap-1">
                                <span data-when-hidden class="flex items-center gap-1"><?= icon('eye', 'w-3.5 h-3.5') ?><span>Show passwords</span></span>
                                <span data-when-shown hidden class="flex items-center gap-1"><?= icon('eye-off', 'w-3.5 h-3.5') ?><span>Hide passwords</span></span>
                            </button>
                            <span class="text-zinc-500 text-[11px]">E2EE Key Generated on Signup</span>
                        </div>
                        <?php
                        // A member's sign-up link (?ref=MMM-xxxxx, e.g. {signup_link} in their emails) fills in their ID.
                        $mainSponsorSql = 'SELECT g.id, g.full_name, a.signup_placement FROM mlm_genealogy g LEFT JOIN mlm_accounts a ON a.id = g.id WHERE g.id = ?';
                        $mainRef = is_string($_GET['ref'] ?? null) ? strtoupper(mb_substr(trim($_GET['ref']), 0, 20)) : '';
                        $mainSponsor = ($mainRef !== '' ? db_one($mainSponsorSql, [$mainRef]) : null)
                            ?? db_one($mainSponsorSql, ['MMM-88001'])
                            ?? ['id' => 'MMM-88001', 'full_name' => 'Mike Maranan', 'signup_placement' => 'auto'];
                        ?>
                        <div>
                            <label for="reg-sponsor" class="block text-xs font-semibold text-zinc-300 uppercase tracking-wider mb-1">Sponsor / Referral ID</label>
                            <div class="relative">
                                <input id="reg-sponsor" name="sponsorId" type="text" value="<?= e($mainSponsor['id']) ?>" maxlength="20" placeholder="MMM-88001" class="w-full px-3 py-2 rounded-xl bg-zinc-900 border border-zinc-700 focus:border-[#f3bd35] text-white text-xs font-mono outline-none">
                                <span id="reg-sponsor-status" class="absolute right-3 top-2.5 text-[10px] text-emerald-400 font-mono flex items-center gap-1"><?= icon('circle-check', 'w-3 h-3') ?><span data-sponsor-label>Verified (<?= e($mainSponsor['full_name']) ?>)</span></span>
                            </div>
                            <?php // Where the sponsor places sign-ups (Backoffice > Network > Sign-up Placement); main.js updates it. ?>
                            <p data-sponsor-placement class="text-[10px] text-zinc-500 mt-1"><?= e(mlm_signup_placement_text($mainSponsor['full_name'], (string) ($mainSponsor['signup_placement'] ?? 'auto') ?: 'auto')) ?></p>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-zinc-300 uppercase tracking-wider mb-1.5">Select Membership Package</label>
                            <input type="hidden" name="package" id="reg-package" value="membership_executive">
                            <div class="grid grid-cols-2 gap-2" role="radiogroup">
                                <div role="radio" tabindex="0" aria-checked="true" data-package="membership_executive" data-price="<?= e(peso($regPrices['membership_executive'])) ?>" data-on="<?= $pkgOn ?>" data-off="<?= $pkgOff ?>" class="p-2.5 rounded-xl border cursor-pointer transition-all <?= $pkgOn ?>">
                                    <div class="font-bold text-xs text-white">Executive Member</div>
                                    <div class="text-[#f3bd35] font-black text-xs mt-0.5"><?= peso($regPrices['membership_executive']) ?></div>
                                    <div class="text-[10px] text-zinc-400 mt-0.5">1 Smart NFC Card + Microsite</div>
                                </div>
                                <div role="radio" tabindex="0" aria-checked="false" data-package="membership_chairmans" data-price="<?= e(peso($regPrices['membership_chairmans'])) ?>" data-on="<?= $pkgOn ?>" data-off="<?= $pkgOff ?>" class="p-2.5 rounded-xl border cursor-pointer transition-all <?= $pkgOff ?>">
                                    <div class="font-bold text-xs text-white">Chairman's Circle</div>
                                    <div class="text-[#f3bd35] font-black text-xs mt-0.5"><?= peso($regPrices['membership_chairmans']) ?></div>
                                    <div class="text-[10px] text-zinc-400 mt-0.5">7 Cards + Mastermind VIP</div>
                                </div>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-zinc-300 uppercase tracking-wider mb-1.5">Pay With</label>
                            <input type="hidden" name="payWith" id="reg-pay-with" value="<?= $regMaya ? 'maya' : 'code' ?>">
                            <div class="grid grid-cols-2 gap-2" role="radiogroup" aria-label="Pay with">
                                <div role="radio" tabindex="0" aria-checked="<?= $regMaya ? 'true' : 'false' ?>" aria-disabled="<?= $regMaya ? 'false' : 'true' ?>" data-pay-with="maya" data-on="<?= $pkgOn ?>" data-off="<?= $pkgOff ?>" class="p-2.5 rounded-xl border transition-all <?= $regMaya ? $pkgOn . ' cursor-pointer' : $pkgOff . ' opacity-50 cursor-not-allowed' ?>">
                                    <div class="font-bold text-xs text-white flex items-center gap-1.5"><?= icon('credit-card', 'w-3.5 h-3.5 text-[#f3bd35]') ?>Online Checkout</div>
                                    <div class="text-[10px] text-zinc-400 mt-0.5"><?= $regMaya ? 'Maya wallet or card' : 'Not available yet' ?></div>
                                </div>
                                <div role="radio" tabindex="0" aria-checked="<?= $regMaya ? 'false' : 'true' ?>" data-pay-with="code" data-on="<?= $pkgOn ?>" data-off="<?= $pkgOff ?>" class="p-2.5 rounded-xl border cursor-pointer transition-all <?= $regMaya ? $pkgOff : $pkgOn ?>">
                                    <div class="font-bold text-xs text-white flex items-center gap-1.5"><?= icon('key', 'w-3.5 h-3.5 text-[#f3bd35]') ?>Activation Code</div>
                                    <div class="text-[10px] text-zinc-400 mt-0.5">Code + PIN from your sponsor</div>
                                </div>
                            </div>
                        </div>
                        <div id="reg-code-fields" class="flex gap-2"<?= $regMaya ? ' hidden' : '' ?>>
                            <div class="flex-1 min-w-0">
                                <label for="reg-code" class="block text-xs font-semibold text-zinc-300 uppercase tracking-wider mb-1">Activation Code</label>
                                <input id="reg-code" name="activationCode" type="text" maxlength="40" autocomplete="off" spellcheck="false" placeholder="EVO88-EXE-…" class="w-full px-3 py-2 rounded-xl bg-zinc-900 border border-zinc-700 focus:border-[#f3bd35] text-white text-xs font-mono uppercase placeholder-zinc-500 outline-none">
                            </div>
                            <div class="w-24 shrink-0">
                                <label for="reg-pin" class="block text-xs font-semibold text-zinc-300 uppercase tracking-wider mb-1">PIN</label>
                                <input id="reg-pin" name="activationPin" type="password" maxlength="10" inputmode="numeric" autocomplete="off" placeholder="••••••" class="w-full px-3 py-2 rounded-xl bg-zinc-900 border border-zinc-700 focus:border-[#f3bd35] text-white text-xs font-mono placeholder-zinc-500 outline-none">
                            </div>
                        </div>
                        <div class="pt-1">
                            <label class="flex items-center gap-2 cursor-pointer text-xs text-zinc-300">
                                <input type="checkbox" name="accepted" checked class="w-4 h-4 rounded bg-zinc-900 border-zinc-700 text-[#f3bd35]">
                                <span>I agree to 88MMM Syndicate Bylaws &amp; Security Policy</span>
                            </label>
                        </div>
                        <p class="text-[11px] text-zinc-400 leading-relaxed flex items-start gap-1.5">
                            <?= icon('lock', 'w-3.5 h-3.5 shrink-0 mt-0.5 text-[#f3bd35]') ?>
                            <span data-pay-note="maya"<?= $regMaya ? '' : ' hidden' ?>>You pay for your package on Maya’s secure page first. Your account is created as soon as Maya confirms the payment.<?php if (maya_environment() === 'sandbox'): ?> <span class="text-amber-200">Sandbox: a test payment, no real money.</span><?php endif; ?></span>
                            <span data-pay-note="code"<?= $regMaya ? ' hidden' : '' ?>>Your account is created right away with a valid, active code for the package you selected. Each code works once.</span>
                        </p>
                        <button type="submit" class="w-full mt-2 py-3 px-4 rounded-xl bg-gradient-to-r from-[#ffda72] to-[#f3bd35] text-black font-extrabold text-sm tracking-wide flex items-center justify-center gap-2 shadow-lg shadow-[#f3bd35]/20 cursor-pointer disabled:opacity-60">
                            <span data-pay-label="maya"<?= $regMaya ? '' : ' hidden' ?>>Continue to Payment · <span data-reg-amount><?= peso($regPrices['membership_executive']) ?></span></span>
                            <span data-pay-label="code"<?= $regMaya ? ' hidden' : '' ?>>Activate My Account</span><?= icon('arrow-right', 'w-4 h-4') ?>
                        </button>
                    </form>

                    <div class="mt-5 pt-4 border-t border-zinc-800/80 text-center">
                        <a href="<?= e(url('card', ['profile' => 'usr_mike'])) ?>" class="text-xs text-zinc-400 hover:text-[#f3bd35] transition-colors inline-flex items-center gap-1.5">
                            <span>Don't have credentials yet?</span>
                            <span class="text-[#ffda72] font-semibold underline underline-offset-2">Preview Public Card as Guest</span>
                            <?= icon('external-link', 'w-3 h-3 text-[#ffda72]') ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-20 pt-12 border-t border-zinc-800">
            <div class="text-center max-w-3xl mx-auto mb-10">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-[#f3bd35]/10 border border-[#f3bd35]/30 text-[#f3bd35] text-xs font-bold uppercase tracking-wider mb-3"><?= icon('layers', 'w-3.5 h-3.5') ?>Core Architectural Pillars</div>
                <h2 class="text-2xl sm:text-4xl font-extrabold text-white">Built for Modern Corporate Titans &amp; Network Leaders</h2>
                <p class="mt-3 text-sm sm:text-base text-zinc-400">Explore how each component of the 1EVOAI ecosystem powers high-conviction dealmaking and sovereign operations.</p>
            </div>
            <div class="flex flex-wrap items-center justify-center gap-2 mb-8" data-tab-group="pillars" role="tablist">
                <?php foreach ([['nfc', 'smartphone', 'NFC Smart Card'], ['e2ee', 'message-square-lock', 'E2EE Cryptographic Comms'], ['backoffice', 'trending-up', 'Binary MLM Backoffice'], ['syndicate', 'award', 'Syndicate & Governance']] as $i => [$id, $ic, $label]): ?>
                    <button type="button" data-tab="<?= $id ?>" data-group="pillars" data-on="<?= $pillarOn ?>" data-off="<?= $pillarOff ?>" class="px-4 py-2 rounded-xl text-xs sm:text-sm font-bold transition-all flex items-center gap-2 <?= $i === 0 ? $pillarOn : $pillarOff ?>">
                        <?= icon($ic, 'w-4 h-4') ?><span><?= e($label) ?></span>
                    </button>
                <?php endforeach; ?>
            </div>
            <div class="rounded-3xl bg-zinc-900/80 border border-zinc-800 p-6 sm:p-10 shadow-xl">
                <div data-tab-panel="nfc" data-group="pillars" class="grid grid-cols-1 md:grid-cols-2 gap-8 items-center">
                    <div>
                        <span class="text-xs font-mono text-[#f3bd35] uppercase tracking-wider block mb-2">HARDWARE-INTEGRATED NETWORKING</span>
                        <h3 class="text-2xl sm:text-3xl font-black text-white">Executive Metal &amp; Matte NFC Card</h3>
                        <p class="mt-3 text-sm text-zinc-300 leading-relaxed">A physical manifestation of executive prestige. Engineered with high-coercivity ISO/IEC 14443 Type A microchips that trigger instant iOS/Android web app launches, contact vCard downloads, and lead inquiries with zero app installation required.</p>
                        <ul class="mt-6 space-y-2.5 text-xs text-zinc-300">
                            <li class="flex items-center gap-2"><?= icon('circle-check', 'w-4 h-4 text-emerald-400') ?>Dynamic Cloud Sync: Update profile details anytime without replacing the card</li>
                            <li class="flex items-center gap-2"><?= icon('circle-check', 'w-4 h-4 text-emerald-400') ?>Instant Lead Capture: Direct form feeding into the management CRM</li>
                            <li class="flex items-center gap-2"><?= icon('circle-check', 'w-4 h-4 text-emerald-400') ?>Apple Wallet &amp; Google Wallet .pkpass integration compatible</li>
                        </ul>
                        <div class="mt-6">
                            <a href="<?= e(url('card')) ?>" class="px-5 py-2.5 rounded-xl bg-[#f3bd35] text-black font-bold text-xs hover:brightness-110 inline-flex items-center gap-2"><span>Preview Live Card Experience</span><?= icon('arrow-right', 'w-3.5 h-3.5') ?></a>
                        </div>
                    </div>
                    <div class="relative rounded-2xl overflow-hidden border border-zinc-700 shadow-2xl bg-black aspect-video flex items-center justify-center">
                        <img src="<?= e(media_url('assets/images/nfc_smart_card_executive.svg')) ?>" alt="1EVOAI Executive NFC Smart Card" class="w-full h-full object-cover" loading="lazy">
                        <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-transparent to-transparent flex items-end p-4">
                            <span class="text-xs font-mono text-[#ffda72]">1EVOAI GOLD EMBOSSED NFC METALLIC FINISH</span>
                        </div>
                    </div>
                </div>
                <div data-tab-panel="e2ee" data-group="pillars" hidden class="grid grid-cols-1 md:grid-cols-2 gap-8 items-center">
                    <div>
                        <span class="text-xs font-mono text-purple-400 uppercase tracking-wider block mb-2">ZERO-KNOWLEDGE CIPHERTEXT</span>
                        <h3 class="text-2xl sm:text-3xl font-black text-white">End-to-End Encrypted War Room</h3>
                        <p class="mt-3 text-sm text-zinc-300 leading-relaxed">Designed for sensitive corporate negotiations, M&amp;A discussions, and private syndicate deal flow. Keypairs are generated strictly client-side using browser SubtleCrypto APIs. The server only sees randomized ciphertext and 12-byte initialization vectors.</p>
                        <ul class="mt-6 space-y-2.5 text-xs text-zinc-300">
                            <li class="flex items-center gap-2"><?= icon('circle-check', 'w-4 h-4 text-purple-400') ?>ECDH P-256 Key Exchange with AES-GCM 256-bit symmetric cipher</li>
                            <li class="flex items-center gap-2"><?= icon('circle-check', 'w-4 h-4 text-purple-400') ?>Ephemeral Burn Timers (30s, 5m, 1h) with self-destructing shredding</li>
                            <li class="flex items-center gap-2"><?= icon('circle-check', 'w-4 h-4 text-purple-400') ?>Verifiable SHA-256 Public Key Fingerprints for impersonation defense</li>
                        </ul>
                        <div class="mt-6">
                            <a href="<?= e(url('chat')) ?>" class="px-5 py-2.5 rounded-xl bg-purple-500 text-white font-bold text-xs hover:bg-purple-600 inline-flex items-center gap-2"><span>Open E2EE Chat Module</span><?= icon('arrow-right', 'w-3.5 h-3.5') ?></a>
                        </div>
                    </div>
                    <div class="p-6 rounded-2xl bg-zinc-950 border border-purple-500/30 font-mono text-xs text-purple-300 space-y-3">
                        <div class="text-[11px] text-zinc-400 flex items-center justify-between border-b border-zinc-800 pb-2"><span>SECURITY PROOF CONSOLE</span><span class="text-emerald-400">CIPHER: AES-GCM-256</span></div>
                        <div><span class="text-zinc-500">CLIENT IDENTITY KEY:</span><div class="text-[10px] text-zinc-300 break-all bg-zinc-900 p-2 rounded mt-1">MFkwEwYHKoZIzj0CAQYIKoZIzj0DAQcDQgAE9f2b8...7d2A4qZ</div></div>
                        <div><span class="text-zinc-500">PAYLOAD OVER WIRE (CIPHERTEXT):</span><div class="text-[10px] text-purple-400 break-all bg-zinc-900 p-2 rounded mt-1">7xK9pL02mNQ==|iv:f189a02ce62...|authTag:verified</div></div>
                        <div class="text-[11px] text-emerald-400">STATUS: Server possesses ZERO access to cleartext contents.</div>
                    </div>
                </div>
                <div data-tab-panel="backoffice" data-group="pillars" hidden class="grid grid-cols-1 md:grid-cols-2 gap-8 items-center">
                    <div>
                        <span class="text-xs font-mono text-emerald-400 uppercase tracking-wider block mb-2">WEALTH COMPLAN &amp; NETWORK ENGINE</span>
                        <h3 class="text-2xl sm:text-3xl font-black text-white">88MMM Binary Backoffice</h3>
                        <p class="mt-3 text-sm text-zinc-300 leading-relaxed">Automated affiliate and network growth engine built on a transparent binary compensation structure: ₱<?= num($mainPlan['entryFee']) ?> package entry, ₱<?= num($mainPlan['directReferralBonus']) ?> direct referral, ₱<?= num($mainPlan['pairingBonus']) ?> pairing bonus, and up to ₱<?= num($mainPlan['maxDailyPairingIncome']) ?>/day pairing ceiling across <?= $mainPlan['cyclesPerDay'] === 1 ? '1 cycle' : $mainPlan['cyclesPerDay'] . ' cycles' ?> daily.</p>
                        <ul class="mt-6 space-y-2.5 text-xs text-zinc-300">
                            <li class="flex items-center gap-2"><?= icon('circle-check', 'w-4 h-4 text-emerald-400') ?>Interactive Binary Tree &amp; Unilevel genealogical visualizer</li>
                            <li class="flex items-center gap-2"><?= icon('circle-check', 'w-4 h-4 text-emerald-400') ?>Live Commission Wallet with instant GCash, Maya &amp; Bank encashment</li>
                            <li class="flex items-center gap-2"><?= icon('circle-check', 'w-4 h-4 text-emerald-400') ?>Integrated Merchandise &amp; NFC Hardware Shop with PV/BV tracking</li>
                        </ul>
                        <div class="mt-6">
                            <a href="<?= e(url('backoffice')) ?>" class="px-5 py-2.5 rounded-xl bg-emerald-500 text-black font-bold text-xs hover:bg-emerald-400 inline-flex items-center gap-2"><span>Enter Member Backoffice</span><?= icon('arrow-right', 'w-3.5 h-3.5') ?></a>
                        </div>
                    </div>
                    <div class="p-6 rounded-2xl bg-zinc-950 border border-emerald-500/30 text-xs space-y-4">
                        <div class="text-zinc-400 text-xs font-semibold uppercase tracking-wider border-b border-zinc-800 pb-2">Binary Complan Architecture</div>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="p-3 rounded-xl bg-zinc-900 border border-zinc-800"><div class="text-[11px] text-zinc-400">Entry Package</div><div class="text-lg font-bold text-white mt-0.5">₱2,000 PHP</div><div class="text-[10px] text-emerald-400 mt-0.5">1 Card + Full Portal</div></div>
                            <div class="p-3 rounded-xl bg-zinc-900 border border-zinc-800"><div class="text-[11px] text-zinc-400">Direct Sponsor</div><div class="text-lg font-bold text-white mt-0.5">₱<?= num($mainPlan['directReferralBonus']) ?> PHP</div><div class="text-[10px] text-emerald-400 mt-0.5">Instant credit</div></div>
                            <div class="p-3 rounded-xl bg-zinc-900 border border-zinc-800"><div class="text-[11px] text-zinc-400">Pairing Bonus</div><div class="text-lg font-bold text-white mt-0.5">₱<?= num($mainPlan['pairingBonus']) ?> PHP</div><div class="text-[10px] text-emerald-400 mt-0.5">Per Left + Right Match</div></div>
                            <div class="p-3 rounded-xl bg-zinc-900 border border-zinc-800"><div class="text-[11px] text-zinc-400">Max Daily Pairing</div><div class="text-lg font-bold text-[#ffda72] mt-0.5">₱<?= num($mainPlan['maxDailyPairingIncome']) ?> PHP</div><div class="text-[10px] text-zinc-400 mt-0.5"><?= $mainPlan['maxPairsPerCycle'] ?> Pairs x <?= $mainPlan['cyclesPerDay'] ?> Cycle<?= $mainPlan['cyclesPerDay'] === 1 ? '' : 's' ?></div></div>
                        </div>
                    </div>
                </div>
                <div data-tab-panel="syndicate" data-group="pillars" hidden class="grid grid-cols-1 md:grid-cols-2 gap-8 items-center">
                    <div>
                        <span class="text-xs font-mono text-[#f3bd35] uppercase tracking-wider block mb-2">EXECUTIVE LEADERSHIP &amp; SUMMITS</span>
                        <h3 class="text-2xl sm:text-3xl font-black text-white">88MMM Executive Syndicate &amp; Masterminds</h3>
                        <p class="mt-3 text-sm text-zinc-300 leading-relaxed">Connect directly with vetted industry founders, venture partners, and corporate executives across real estate, biotech, wealth management, and technology. Access weekly closed-door breakfast clubs and VIP quarterly syndication summits.</p>
                        <ul class="mt-6 space-y-2.5 text-xs text-zinc-300">
                            <li class="flex items-center gap-2"><?= icon('circle-check', 'w-4 h-4 text-[#f3bd35]') ?>Weekly Executive Breakfast Club at premier Manila &amp; BGC venues</li>
                            <li class="flex items-center gap-2"><?= icon('circle-check', 'w-4 h-4 text-[#f3bd35]') ?>Syndicated Co-Investment Opportunities with institutional backing</li>
                            <li class="flex items-center gap-2"><?= icon('circle-check', 'w-4 h-4 text-[#f3bd35]') ?>Chairman's Circle Mastermind Dinners with Mike Maranan</li>
                        </ul>
                        <div class="mt-6">
                            <a href="<?= e(url('card')) ?>" class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-[#ffda72] to-[#f3bd35] text-black font-bold text-xs hover:brightness-110 inline-flex items-center gap-2"><span>Review Summit Schedule</span><?= icon('arrow-right', 'w-3.5 h-3.5') ?></a>
                        </div>
                    </div>
                    <div class="relative rounded-2xl overflow-hidden border border-zinc-700 shadow-2xl bg-black aspect-video flex items-center justify-center">
                        <img src="<?= e(media_url('assets/images/circle_executive_summit.svg')) ?>" alt="88MMM Circle Executive Summit" class="w-full h-full object-cover" loading="lazy">
                        <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-transparent to-transparent flex items-end p-4">
                            <span class="text-xs font-mono text-[#ffda72]">ANNUAL 88MMM EXECUTIVE SUMMIT &amp; DEAL SYNDICATION</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-20 pt-12 border-t border-zinc-800">
            <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-8">
                <div>
                    <span class="text-xs font-mono text-[#f3bd35] uppercase tracking-wider block mb-1">EXECUTIVE COUNCIL</span>
                    <h2 class="text-2xl sm:text-3xl font-extrabold text-white">Founding Leadership &amp; Board of Advisors</h2>
                </div>
                <p class="text-xs sm:text-sm text-zinc-400 max-w-md">The visionary minds directing the 88MMM Circle ecosystem and 1EVOAI digital infrastructure.</p>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <?php foreach ($demoUsers as $i => $d): ?>
                    <div class="p-5 rounded-2xl bg-zinc-900/60 border border-zinc-800 hover:border-[#f3bd35]/40 transition-all flex flex-col justify-between group">
                        <div>
                            <div class="flex items-center gap-3 mb-3">
                                <?php if ($d['avatar']): ?>
                                    <img src="<?= e(media_url($d['avatar'])) ?>" alt="<?= e($d['name']) ?>" class="w-12 h-12 rounded-full object-cover border border-[#f3bd35]/40">
                                <?php else: ?>
                                    <div class="w-12 h-12 rounded-full bg-gradient-to-br <?= $d['color'] ?> text-black font-extrabold text-lg flex items-center justify-center"><?= e(mb_substr($d['name'], 0, 1)) ?></div>
                                <?php endif; ?>
                                <div>
                                    <h4 class="text-sm font-bold text-white group-hover:text-[#ffda72] transition-colors"><?= e($d['name']) ?></h4>
                                    <div class="text-[11px] text-zinc-400"><?= e($d['roleLabel']) ?></div>
                                </div>
                            </div>
                            <div class="text-[11px] text-zinc-400 font-mono"><?= e($d['email']) ?></div>
                        </div>
                        <div class="mt-4 pt-3 border-t border-zinc-800/80 flex items-center justify-between">
                            <?php if ($demo): ?>
                                <button type="button" data-demo-login="<?= $i ?>" class="text-xs text-[#f3bd35] hover:text-[#ffda72] font-semibold flex items-center gap-1">
                                    <span>Sign in as <?= e(explode(' ', $d['name'])[0]) ?></span><?= icon('arrow-right', 'w-3 h-3') ?>
                                </button>
                            <?php else: ?>
                                <a href="<?= e(url('card', ['profile' => 'usr_' . strtolower(explode(' ', $d['name'])[0])])) ?>" class="text-xs text-[#f3bd35] hover:text-[#ffda72] font-semibold flex items-center gap-1">
                                    <span>View <?= e(explode(' ', $d['name'])[0]) ?>'s Card</span><?= icon('arrow-right', 'w-3 h-3') ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="mt-20 pt-8 border-t border-zinc-800 text-xs text-zinc-400 flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="flex items-center gap-2">
                <span class="w-5 h-5 rounded-full bg-[#f3bd35] text-black font-black text-[9px] flex items-center justify-center">88</span>
                <span class="text-zinc-300 font-medium">1EVOAI • Mike Maranan Millionaire's Circle (88MMM)</span>
            </div>
            <div class="flex items-center gap-4 text-[11px] text-zinc-400">
                <span>Client-Side AES-GCM Encrypted</span><span>•</span><span>BSP / QR Ph Ready</span><span>•</span><span>Sovereign Identity Protocol</span>
            </div>
        </div>
    </div>

    <div data-modal="forgot" hidden class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-md animate-fadeIn" role="dialog" aria-modal="true" aria-labelledby="forgot-title">
        <div class="relative w-full max-w-md rounded-3xl bg-[#141414] border border-[#f3bd35]/40 p-6 sm:p-8 shadow-2xl">
            <button type="button" data-close-modal class="absolute top-4 right-4 p-2 rounded-xl text-zinc-400 hover:text-white bg-zinc-900 hover:bg-zinc-800" aria-label="Close"><?= icon('x', 'w-4 h-4') ?></button>
            <div class="w-10 h-10 rounded-2xl bg-[#f3bd35]/20 border border-[#f3bd35]/40 flex items-center justify-center text-[#f3bd35] mb-4"><?= icon('key', 'w-5 h-5') ?></div>
            <h3 id="forgot-title" class="text-lg font-bold text-white">Reset Security Password</h3>
            <p class="text-xs text-zinc-400 mt-1">Enter your registered executive email to receive cryptographic password reset credentials.</p>
            <div id="forgot-done" hidden class="mt-5 p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 text-xs space-y-3">
                <div class="flex items-center gap-2 font-bold"><?= icon('circle-check', 'w-4 h-4 text-emerald-400') ?>Password Recovery Dispatched</div>
                <p data-forgot-text>A temporary one-time security token was generated for <strong class="text-white" data-forgot-email></strong>.</p>
                <div data-forgot-pass-wrap hidden class="p-2.5 rounded-xl bg-zinc-900 border border-zinc-800 font-mono text-[11px] text-[#ffda72]">Demo Quick Pass: <span class="text-white font-bold" data-forgot-pass></span></div>
                <button type="button" id="forgot-use" hidden class="w-full py-2 rounded-xl bg-[#f3bd35] text-black font-bold text-xs">Use Demo Password Now</button>
            </div>
            <form id="forgot-form" class="mt-5 space-y-4" novalidate>
                <div>
                    <label for="forgot-email" class="block text-xs font-semibold text-zinc-300 uppercase tracking-wider mb-1">Executive Email</label>
                    <input id="forgot-email" name="email" type="email" required placeholder="e.g. mike@mmmcircle.com" class="w-full px-4 py-2.5 rounded-xl bg-zinc-900 border border-zinc-700 focus:border-[#f3bd35] text-white text-sm outline-none">
                </div>
                <p id="forgot-error" hidden class="text-[11px] text-rose-400"></p>
                <button type="submit" class="w-full py-3 rounded-xl bg-gradient-to-r from-[#ffda72] to-[#f3bd35] text-black font-bold text-xs flex items-center justify-center gap-2">
                    <?= icon('mail', 'w-4 h-4') ?><span>Send Recovery Instructions</span>
                </button>
            </form>
        </div>
    </div>
</div>
