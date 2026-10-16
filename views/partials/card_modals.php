<?php
/**
 * Modals of the public card: QR code, share, send vCard and membership
 * application. Expects $name, $shareUrl, $qrUrl (the QR code's 1EVOAI card address), $cms from views/card.php.
 */
$shareTargets = [
    ['WhatsApp', 'message-circle', 'bg-emerald-600', 'whatsapp'],
    ['Telegram', 'send', 'bg-sky-500', 'telegram'],
    ['LinkedIn', 'share-2', 'bg-blue-700', 'linkedin'],
    ['X (Twitter)', 'send', 'bg-zinc-800', 'twitter'],
    ['Facebook', 'share-2', 'bg-blue-600', 'facebook'],
];
$tierOn = 'bg-[#f3bd35] text-black shadow-md';
$tierOff = 'text-zinc-400 hover:text-white';
$leadInput = 'w-full px-3 py-2 rounded-xl bg-zinc-900 border border-zinc-700 text-xs text-white placeholder-zinc-500 focus:outline-none focus:border-[#f3bd35]';
?>
<div data-modal="card-qr" hidden class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-md p-4 animate-fadeIn" role="dialog" aria-modal="true" aria-labelledby="qr-title">
    <div class="w-full max-w-sm rounded-3xl bg-[#121212] border border-[#f3bd35]/40 p-6 shadow-2xl text-center relative overflow-hidden">
        <div class="absolute top-0 left-1/2 -translate-x-1/2 w-48 h-48 bg-[#f3bd35]/10 rounded-full blur-3xl pointer-events-none"></div>
        <button type="button" data-close-modal class="absolute top-4 right-4 text-zinc-400 hover:text-white p-1 rounded-full transition-colors" aria-label="Close"><?= icon('x', 'w-5 h-5') ?></button>
        <div class="mb-4">
            <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-[#f3bd35]/15 border border-[#f3bd35]/30 text-[#f3bd35] text-[11px] font-semibold tracking-wider uppercase mb-2"><?= icon('shield-check', 'w-3.5 h-3.5') ?>Verified NFC Digital Card</div>
            <h3 id="qr-title" class="text-lg font-bold text-white tracking-wide">Scan to Connect</h3>
            <p class="text-xs text-zinc-400 mt-1"><?= e($name) ?> · 88MMM Circle</p>
        </div>
        <div class="p-4 bg-white rounded-2xl mx-auto shadow-inner flex flex-col items-center justify-center my-4">
            <svg id="public-qr-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200" class="w-48 h-48" role="img" aria-label="QR code for <?= e($qrUrl) ?>"></svg>
        </div>
        <p class="text-[11px] text-zinc-400 mb-5 break-all font-mono"><?= e($qrUrl) ?></p>
        <button type="button" id="qr-download" class="w-full flex items-center justify-center gap-2 py-3 bg-gradient-to-r from-[#ffda72] to-[#f3bd35] text-black font-bold text-xs rounded-xl shadow-lg hover:brightness-105 active:scale-95 transition-transform"><?= icon('download', 'w-4 h-4') ?>Download QR Image</button>
    </div>
</div>

<div data-modal="card-share" hidden class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-md p-4 animate-fadeIn" role="dialog" aria-modal="true" aria-labelledby="share-title">
    <div class="w-full max-w-sm rounded-3xl bg-[#121212] border border-[#f3bd35]/40 p-6 shadow-2xl text-center relative">
        <button type="button" data-close-modal class="absolute top-4 right-4 text-zinc-400 hover:text-white p-1 rounded-full transition-colors" aria-label="Close"><?= icon('x', 'w-5 h-5') ?></button>
        <h3 id="share-title" class="text-lg font-bold text-white mb-1">Share Digital Card</h3>
        <p class="text-xs text-zinc-400 mb-6">Share <?= e($name) ?>'s verified executive profile with your network</p>
        <div class="grid grid-cols-3 gap-3 mb-6">
            <?php foreach ($shareTargets as [$label, $ic, $color, $key]): ?>
                <button type="button" data-share="<?= e($key) ?>" class="flex flex-col items-center justify-center p-3 rounded-2xl bg-zinc-900 border border-zinc-800 hover:border-[#f3bd35]/40 active:scale-95 transition-all">
                    <div class="w-10 h-10 rounded-full <?= $color ?> flex items-center justify-center text-white mb-2 shadow-md"><?= icon($ic, 'w-5 h-5') ?></div>
                    <span class="text-[11px] font-medium text-zinc-300"><?= e($label) ?></span>
                </button>
            <?php endforeach; ?>
        </div>
        <div class="flex items-center gap-2 p-1.5 bg-zinc-900 rounded-xl border border-zinc-800">
            <input type="text" readonly value="<?= e($shareUrl) ?>" aria-label="Card link" class="flex-1 bg-transparent px-2 text-xs text-zinc-300 outline-none truncate">
            <button type="button" data-copy="<?= e($shareUrl) ?>" data-copied-html="<?= e(icon('check', 'w-3.5 h-3.5') . '<span>Copied</span>') ?>" class="flex items-center gap-1 px-3 py-1.5 bg-[#f3bd35] hover:bg-[#ffda72] text-black text-xs font-semibold rounded-lg transition-colors whitespace-nowrap">
                <?= icon('copy', 'w-3.5 h-3.5') ?><span>Copy</span>
            </button>
        </div>
    </div>
</div>

<div data-modal="card-vcard" hidden class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-md p-4 animate-fadeIn" role="dialog" aria-modal="true" aria-labelledby="vcard-title">
    <div class="w-full max-w-sm rounded-3xl bg-[#141414] border border-[#f3bd35]/40 p-6 shadow-2xl text-left relative">
        <button type="button" data-close-modal class="absolute top-4 right-4 text-zinc-400 hover:text-white p-1 rounded-full transition-colors" aria-label="Close"><?= icon('x', 'w-5 h-5') ?></button>
        <div class="flex items-center gap-2 mb-2">
            <div class="w-8 h-8 rounded-full bg-[#f3bd35]/20 flex items-center justify-center text-[#f3bd35]"><?= icon('smartphone', 'w-4 h-4') ?></div>
            <h3 id="vcard-title" class="text-base font-bold text-white">Send vCard to Phone</h3>
        </div>
        <p class="text-xs text-zinc-400 mb-5 leading-relaxed">Send <?= e($name) ?>'s digital contact card directly to any mobile number via WhatsApp or SMS.</p>
        <form id="vcard-send-form" class="space-y-4">
            <div>
                <label for="vcard-phone" class="block text-xs font-semibold text-zinc-300 mb-1.5">Phone Number (with Country Code)</label>
                <input id="vcard-phone" name="phone" type="tel" required pattern="[0-9+\s()-]{6,20}" placeholder="e.g. 639178886272 or 14155552671" class="w-full px-3.5 py-2.5 rounded-xl bg-zinc-900 border border-zinc-700 text-sm text-white placeholder-zinc-500 focus:outline-none focus:border-[#f3bd35] transition-colors">
                <span class="text-[10px] text-zinc-500 block mt-1">Include country code without the '+' symbol (e.g. 63 for PH, 1 for US)</span>
            </div>
            <button type="submit" class="w-full flex items-center justify-center gap-2 py-3 bg-gradient-to-r from-[#ffda72] to-[#f3bd35] text-black font-bold text-xs rounded-xl shadow-lg hover:brightness-105 active:scale-95 transition-transform"><?= icon('send', 'w-3.5 h-3.5') ?>Send Digital vCard</button>
        </form>
    </div>
</div>

<div data-modal="card-join" hidden class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-md p-4 animate-fadeIn" role="dialog" aria-modal="true" aria-labelledby="join-title">
    <div class="w-full max-w-md rounded-3xl bg-[#141414] border border-[#f3bd35]/40 p-6 shadow-2xl text-left relative max-h-[90vh] overflow-y-auto">
        <button type="button" data-close-modal class="absolute top-4 right-4 text-zinc-400 hover:text-white p-1 rounded-full transition-colors" aria-label="Close"><?= icon('x', 'w-5 h-5') ?></button>
        <div id="join-done" hidden class="text-center py-6">
            <div class="w-16 h-16 rounded-full bg-[#f3bd35]/20 text-[#f3bd35] flex items-center justify-center mx-auto mb-4"><?= icon('circle-check-big', 'w-10 h-10') ?></div>
            <h3 class="text-xl font-bold text-white mb-2">Welcome to the Circle</h3>
            <p class="text-xs text-zinc-300 leading-relaxed max-w-sm mx-auto mb-6">Your application for <span class="text-[#f3bd35] font-semibold" data-join-tier-name>Executive Member</span> has been registered directly in Mike Maranan's executive ledger.</p>
            <div class="space-y-2">
                <button type="button" data-join-pay class="w-full flex items-center justify-center gap-2 py-3 bg-gradient-to-r from-emerald-600 to-teal-600 hover:brightness-110 text-white font-bold text-xs rounded-xl shadow-lg">
                    <?= icon('credit-card', 'w-4 h-4 text-emerald-200') ?><span>Sign Up Now (<span data-join-price></span>): Maya or Activation Code</span>
                </button>
                <a href="<?= e(url('chat')) ?>" class="w-full flex items-center justify-center gap-2 py-3 bg-gradient-to-r from-[#ffda72] to-[#f3bd35] text-black font-bold text-xs rounded-xl shadow-lg"><span>Open Secure E2EE Chat Now</span><?= icon('arrow-right', 'w-4 h-4') ?></a>
                <button type="button" data-close-modal class="w-full py-2.5 bg-zinc-800 text-zinc-300 font-semibold text-xs rounded-xl">Done</button>
            </div>
        </div>
        <div id="join-form-wrap">
            <div class="mb-4">
                <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-[#f3bd35]/15 border border-[#f3bd35]/30 text-[#f3bd35] text-[11px] font-semibold tracking-wider uppercase mb-2"><?= icon('shield', 'w-3.5 h-3.5') ?>88MMM Circle Membership</div>
                <h3 id="join-title" class="text-xl font-bold text-white">Join The Executive Circle</h3>
                <p class="text-xs text-zinc-400 mt-1">Where Millionaires Connect and Legacies Are Built.</p>
            </div>
            <div class="grid grid-cols-2 gap-2 p-1.5 bg-zinc-900 rounded-2xl mb-4 border border-zinc-800">
                <button type="button" data-join-tier="executive" data-on="<?= $tierOn ?>" data-off="<?= $tierOff ?>" class="py-2 px-3 rounded-xl text-xs font-semibold transition-all <?= $tierOn ?>">Executive (₱<?= num($cms['executivePrice']) ?>)</button>
                <button type="button" data-join-tier="chairmans" data-on="<?= $tierOn ?>" data-off="<?= $tierOff ?>" class="py-2 px-3 rounded-xl text-xs font-semibold transition-all <?= $tierOff ?>">Chairman's (₱<?= num($cms['chairmansPrice']) ?>)</button>
            </div>
            <div class="p-3 rounded-xl bg-zinc-900/80 border border-zinc-800 mb-4 text-xs">
                <div class="flex items-center justify-between text-zinc-300 mb-1">
                    <span class="font-semibold" data-join-label>Executive Member</span>
                    <span class="font-bold text-[#f3bd35] text-sm tabular-nums" data-join-price>₱<?= num($cms['executivePrice']) ?></span>
                </div>
                <p class="text-[11px] text-zinc-400" data-join-desc>Includes 1 Smart NFC Executive Card, One-Tap Digital Identity, and Weekly Masterminds.</p>
            </div>
            <form id="join-form" class="space-y-3">
                <input type="hidden" name="tier" value="executive">
                <div class="hidden" aria-hidden="true"><label>Website<input type="text" name="website_url" tabindex="-1" autocomplete="off"></label></div>
                <div>
                    <label for="join-name" class="block text-[11px] font-semibold text-zinc-300 mb-1">Full Name *</label>
                    <input id="join-name" name="name" type="text" required maxlength="150" placeholder="e.g. Atty. Miguel Ramos" class="<?= $leadInput ?>">
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label for="join-email" class="block text-[11px] font-semibold text-zinc-300 mb-1">Email Address *</label>
                        <input id="join-email" name="email" type="email" required maxlength="190" placeholder="name@company.com" class="<?= $leadInput ?>">
                    </div>
                    <div>
                        <label for="join-phone" class="block text-[11px] font-semibold text-zinc-300 mb-1">Phone / Mobile *</label>
                        <input id="join-phone" name="phone" type="tel" required maxlength="60" placeholder="+63 917..." class="<?= $leadInput ?>">
                    </div>
                </div>
                <div>
                    <label for="join-company" class="block text-[11px] font-semibold text-zinc-300 mb-1">Company / Organization</label>
                    <input id="join-company" name="company" type="text" maxlength="190" placeholder="e.g. Ramos Capital Holdings" class="<?= $leadInput ?>">
                </div>
                <div>
                    <label for="join-message" class="block text-[11px] font-semibold text-zinc-300 mb-1">Note / Objectives</label>
                    <textarea id="join-message" name="message" rows="2" maxlength="2000" placeholder="Tell Mike what you look forward to gaining in the Circle..." class="<?= $leadInput ?>"></textarea>
                </div>
                <p id="join-error" hidden class="text-[11px] text-rose-400" role="alert"></p>
                <button type="submit" class="w-full flex items-center justify-center gap-2 py-3 bg-gradient-to-r from-[#ffda72] to-[#f3bd35] text-black font-bold text-xs rounded-xl shadow-lg hover:brightness-105 active:scale-95 transition-transform mt-2 disabled:opacity-50">
                    <?= icon('award', 'w-4 h-4') ?><span data-join-submit>Submit Application (₱<?= num($cms['executivePrice']) ?>)</span>
                </button>
                <div class="pt-2">
                    <div class="relative flex py-1.5 items-center">
                        <div class="flex-grow border-t border-zinc-800"></div>
                        <span class="flex-shrink mx-2 text-[10px] uppercase font-bold text-zinc-500">or sign up now</span>
                        <div class="flex-grow border-t border-zinc-800"></div>
                    </div>
                    <button type="button" data-join-pay class="w-full flex items-center justify-center gap-2 py-2.5 bg-gradient-to-r from-emerald-600 to-teal-600 hover:brightness-110 text-white font-bold text-xs rounded-xl shadow-md transition-all active:scale-95">
                        <?= icon('credit-card', 'w-3.5 h-3.5 text-emerald-200') ?><span>Sign Up Now (<span data-join-price>₱<?= num($cms['executivePrice']) ?></span>): Maya or Activation Code</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
