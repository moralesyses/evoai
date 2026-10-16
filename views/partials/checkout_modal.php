<?php
/**
 * 1EVOAI Online Payment Portal modal (reference component "yn").
 * Open from JS with EVO.openCheckout('membership_executive' | 'membership_chairmans' | 'renewal' | 'nfc_card_upgrade' | 'hardware_combo').
 *
 * Maya is the only way to pay: the customer goes to Maya Checkout's hosted
 * page (includes/maya.php), where card and wallet details are typed. Without
 * Maya keys (maya.public_key / secret_key) the portal says online checkout
 * is still unavailable (MAYA_UNAVAILABLE) and nothing can be paid.
 * Expects: $user (array|null). Optional: $checkoutInvoice (pending invoice array).
 */
$ckCms = get_cms();
$ckItems = [];
foreach (['membership_executive', 'membership_chairmans', 'nfc_card_upgrade', 'hardware_combo', 'renewal'] as $type) {
    $ckItems[$type] = checkout_item($type, $ckCms);
}
$ckProfile = $user ? get_member_profile($user['id']) : null;
$ckName = $user && $user['name'] !== 'Executive Member' ? $user['name'] : '';
$ckEmail = $user['email'] ?? '';
$ckPhone = $ckProfile['phone'] ?? '';
$ckInvoice = $checkoutInvoice ?? null;
$ckMaya = maya_enabled();
$ckMayaSandbox = maya_environment() === 'sandbox';
$tierOn = 'bg-[#1a170f] border-[#f3bd35] ring-1 ring-[#f3bd35] shadow-md';
$tierOff = 'bg-[#151515] border-zinc-800 hover:border-zinc-700';
$ckReceiptInput = 'w-full px-3 py-2 rounded-xl bg-zinc-950 border border-zinc-800 text-xs text-white focus:outline-none focus:border-[#f3bd35]';
?>
<div data-modal="checkout" hidden class="fixed inset-0 z-50 flex items-center justify-center bg-black/85 backdrop-blur-md p-4 animate-in fade-in duration-200" role="dialog" aria-modal="true" aria-label="1EVOAI Online Payment Portal">
    <script type="application/json" id="checkout-data"><?= js_json(['items' => $ckItems, 'invoice' => $ckInvoice, 'maya' => ['enabled' => $ckMaya, 'sandbox' => $ckMayaSandbox]]) ?></script>
    <div class="w-full max-w-2xl rounded-3xl bg-[#111111] border border-[#f3bd35]/40 p-5 sm:p-7 shadow-2xl text-left relative max-h-[92vh] overflow-y-auto">
        <button type="button" data-close-modal class="absolute top-4 right-4 text-zinc-400 hover:text-white p-1 rounded-full transition-colors z-10" aria-label="Close"><?= icon('x', 'w-5 h-5') ?></button>

        <form id="ck-form" class="space-y-5" novalidate>
            <div>
                <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-[#f3bd35]/15 border border-[#f3bd35]/30 text-[#f3bd35] text-[10px] font-bold tracking-widest uppercase mb-1.5"><?= icon('lock', 'w-3.5 h-3.5') ?>Secure 256-Bit Executive Checkout</div>
                <h2 class="text-xl font-black text-white flex items-center gap-2">1EVOAI Online Payment Portal<?= icon('shield-check', 'w-5 h-5 text-[#f3bd35]') ?></h2>
                <p class="text-xs text-zinc-400 mt-0.5">Official checkout for 88MMM Circle executive membership and digital identity activation.</p>
            </div>
            <input type="hidden" name="itemType" value="membership_executive">
            <input type="hidden" name="invoiceRef" value="">
            <div class="space-y-2">
                <label class="block text-[11px] font-bold uppercase tracking-wider text-[#f3bd35]">1. Select Membership Tier</label>
                <div>
                <div id="ck-tiers" class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                    <?php foreach (['membership_executive', 'membership_chairmans'] as $type): $it = $ckItems[$type]; ?>
                        <button type="button" data-ck-tier="<?= e($type) ?>" data-on="<?= $tierOn ?>" data-off="<?= $tierOff ?>" class="p-3.5 rounded-2xl text-left border transition-all <?= $type === 'membership_executive' ? $tierOn : $tierOff ?>">
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-[9px] px-1.5 py-0.5 rounded-full uppercase font-bold bg-[#f3bd35]/20 text-[#ffda72]"><?= e($it['badge']) ?></span>
                                <span class="text-base font-black text-[#ffda72] font-mono"><?= peso($it['price']) ?></span>
                            </div>
                            <div class="text-sm font-bold text-white truncate"><?= e($it['name']) ?></div>
                            <p class="text-xs text-zinc-400 mt-1 line-clamp-2"><?= e($it['desc']) ?></p>
                        </button>
                    <?php endforeach; ?>
                </div>
                <div id="ck-invoice" hidden class="p-3.5 rounded-2xl text-left border bg-[#1a170f] border-[#f3bd35] ring-1 ring-[#f3bd35] shadow-md">
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-[9px] px-1.5 py-0.5 rounded-full uppercase font-bold bg-[#f3bd35]/20 text-[#ffda72]">INVOICE <span data-inv="ref"></span></span>
                        <span class="text-base font-black text-[#ffda72] font-mono" data-inv="amount"></span>
                    </div>
                    <div class="text-sm font-bold text-white truncate" data-inv="item"></div>
                    <p class="text-xs text-zinc-400 mt-1 line-clamp-2">Issued by the 88MMM Management Admin for <span data-inv="client"></span>.</p>
                </div>
                </div>
            </div>
            <div class="space-y-2">
                <span class="block text-[11px] font-bold uppercase tracking-wider text-[#f3bd35]">2. Payment</span>
                <div class="p-4 rounded-2xl bg-zinc-950 border border-zinc-800 space-y-3">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-xs font-bold text-white flex items-center gap-1.5"><span class="w-2 h-2 rounded-full <?= $ckMaya ? 'bg-emerald-400 animate-pulse' : 'bg-zinc-600' ?>"></span>Maya Checkout</span>
                        <?php if ($ckMaya && $ckMayaSandbox): ?>
                            <span class="text-[9px] px-2 py-0.5 rounded-full bg-amber-950/40 border border-amber-600/40 text-amber-200 font-bold uppercase tracking-wider">Sandbox · Test Mode</span>
                        <?php elseif ($ckMaya): ?>
                            <span class="text-[10px] text-zinc-400">Bangko Sentral ng Pilipinas Accredited</span>
                        <?php endif; ?>
                    </div>
                    <?php if ($ckMaya): ?>
                        <div class="flex items-start gap-3 bg-zinc-900 p-3 rounded-xl border border-zinc-800">
                            <div class="w-9 h-9 shrink-0 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 flex items-center justify-center"><?= icon('shield-check', 'w-4 h-4') ?></div>
                            <div class="text-[11px] text-zinc-400 space-y-1">
                                <span class="font-bold text-white block text-xs">Pay on Maya’s secure page</span>
                                <p>Use your Maya wallet or a credit or debit card. You enter those details on Maya’s page, never here, and come back here for your receipt.</p>
                                <?php if ($ckMayaSandbox): ?>
                                    <p class="text-amber-200">Test mode: no real money moves. Pay with one of Maya’s sandbox test cards or test wallet accounts.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <p class="text-[11px] text-amber-200 bg-amber-950/40 border border-amber-600/40 p-3 rounded-xl" data-ck-unavailable><?= e(MAYA_UNAVAILABLE) ?>. Please contact the 88MMM administrator.</p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="space-y-2">
                <label class="block text-[11px] font-bold uppercase tracking-wider text-[#f3bd35]">3. Executive Member Receipt Details</label>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
                    <div><label class="block text-[10px] text-zinc-400 mb-1" for="ck-name">Full Name *</label><input id="ck-name" name="customerName" type="text" required maxlength="150" value="<?= e($ckName) ?>" placeholder="e.g. Miguel Ramos" autocomplete="name" class="<?= $ckReceiptInput ?>"></div>
                    <div><label class="block text-[10px] text-zinc-400 mb-1" for="ck-email">Email Address *</label><input id="ck-email" name="customerEmail" type="email" required maxlength="190" value="<?= e($ckEmail) ?>" placeholder="name@executive.ph" autocomplete="email" class="<?= $ckReceiptInput ?>"></div>
                    <div><label class="block text-[10px] text-zinc-400 mb-1" for="ck-phone">Mobile Phone *</label><input id="ck-phone" name="customerPhone" type="text" required maxlength="60" value="<?= e($ckPhone) ?>" placeholder="+63 917..." autocomplete="tel" class="<?= $ckReceiptInput ?>"></div>
                </div>
            </div>
            <p id="ck-error" hidden class="text-[11px] text-rose-400" role="alert"></p>
            <div class="pt-2 border-t border-zinc-800 flex flex-col sm:flex-row items-center justify-between gap-3">
                <div>
                    <span class="text-[10px] uppercase text-zinc-500 font-bold block">Grand Total</span>
                    <span class="text-xl font-black text-[#ffda72] font-mono" data-ck-amount-php></span>
                </div>
                <button type="submit"<?= $ckMaya ? '' : ' disabled' ?> class="w-full sm:w-auto px-8 py-3.5 bg-gradient-to-r from-[#ffda72] via-[#f3bd35] to-[#f3bd35] text-black font-extrabold text-xs rounded-xl shadow-xl hover:brightness-105 active:scale-95 transition-all flex items-center justify-center gap-2 disabled:opacity-50">
                    <?= icon('lock', 'w-3.5 h-3.5') ?><span>Pay <span data-ck-amount></span> with Maya</span>
                </button>
            </div>
        </form>
    </div>
</div>
