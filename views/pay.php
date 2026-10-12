<?php
/**
 * Online payment page (kept off the digital cards):
 *   ?view=pay&invoice=INV-x    pay an admin-issued invoice
 *   ?view=pay&checkout=<item>  membership / renewal / hardware checkout
 *   ?view=pay&maya=<rrn>       return from Maya Checkout: receipt, waiting or not paid
 *                              (for a pay-first sign-up: welcome + sign-in of this browser)
 */
$checkoutInvoice = null;
$invoiceRef = isset($_GET['invoice']) && is_string($_GET['invoice']) ? trim($_GET['invoice']) : '';
if ($invoiceRef !== '') {
    $checkoutInvoice = find_pending_invoice($invoiceRef);
}
$item = isset($_GET['checkout']) && is_string($_GET['checkout']) && checkout_item($_GET['checkout']) ? $_GET['checkout'] : 'membership_executive';

// Back from Maya: the status always comes from Maya itself (maya_sync), never from ?result=.
$mayaRrn = isset($_GET['maya']) && is_string($_GET['maya']) ? $_GET['maya'] : null;
// A Member Shop order's payment is shown in the backoffice shop.
if ($mayaRrn !== null && db_value('SELECT 1 FROM maya_checkouts WHERE rrn = ? AND order_id IS NOT NULL', [$mayaRrn])) {
    header('Location: ' . url('backoffice', ['tab' => 'shop', 'shop' => 'orders', 'maya' => $mayaRrn, 'result' => isset($_GET['result']) && is_string($_GET['result']) ? $_GET['result'] : '']));
    return;
}
$mayaRow =$mayaRrn !== null && preg_match('/^MAYA-[0-9A-F]{24}$/', $mayaRrn) ? maya_sync($mayaRrn) : null;
$mayaPaid = $mayaRow && $mayaRow['payment_id'] ? payment_to_array(db_one('SELECT * FROM payments WHERE id = ?', [$mayaRow['payment_id']])) : null;
$mayaState = $mayaRow ? maya_state($mayaRow) : null;
$mayaResult = isset($_GET['result']) && is_string($_GET['result']) ? $_GET['result'] : '';
if ($mayaState === 'pending' && in_array($mayaResult, ['cancel', 'failure'], true)) {
    // Maya sent the customer back without a payment. A late success is still
    // recorded by the webhook, but there is nothing to wait for here.
    $mayaState = 'failed';
}
// Pay-first sign-up (includes/signup.php): sign this browser in once the account exists.
$mayaSignup = $mayaRow ? db_one('SELECT * FROM registrations WHERE rrn = ?', [$mayaRow['rrn']]) : null;
$signupSignedIn = false;
if ($mayaSignup && $mayaPaid && signup_sign_in($mayaSignup)) {
    $signupSignedIn = true;
    $user = current_user(); // index.php's $user: the header and EVO.user show the new member.
}
$mayaRetry = null;
if ($mayaSignup && $mayaState === 'failed') {
    $mayaRetry = url('main') . '#register';
} elseif ($mayaRow && $mayaState === 'failed') {
    $mayaInvoice = $mayaRow['invoice_id'] ? db_one('SELECT reference_number FROM payments WHERE id = ? AND status = "pending"', [$mayaRow['invoice_id']]) : null;
    $mayaRetry = $mayaInvoice ? url('pay', ['invoice' => $mayaInvoice['reference_number']]) : url('pay', ['checkout' => $mayaRow['item_type']]);
}
$mayaMessage = '';
if ($mayaState === 'failed') {
    $mayaMessage = maya_failure_message($mayaRow['status'] !== 'CREATED' && !str_starts_with($mayaRow['status'], 'PENDING') ? $mayaRow['status'] : ($mayaResult === 'cancel' ? 'PAYMENT_CANCELLED' : 'PAYMENT_FAILED'));
}

$pageScripts[] = 'assets/js/checkout.js';
$pageScripts[] = 'assets/js/views/pay.js';
$pageMeta = ['title' => '1EVOAI Online Payment Portal', 'description' => 'Secure checkout for 88MMM Circle membership and digital identity activation.', 'noindex' => true];
$payBtnSecondary = 'inline-flex items-center justify-center gap-2 px-6 py-3 bg-zinc-900 hover:bg-zinc-800 border border-zinc-700 text-white font-bold text-xs rounded-xl transition-all';
$payBtnPrimary = 'inline-flex items-center justify-center gap-2 px-6 py-3 bg-gradient-to-r from-[#ffda72] via-[#f3bd35] to-[#f3bd35] text-black font-extrabold text-xs rounded-xl shadow-xl hover:brightness-105 transition-all';
?>
<div class="max-w-xl mx-auto px-4 py-8">
    <div class="relative rounded-3xl bg-[#111111]/95 border border-[#f3bd35]/35 p-6 sm:p-8 shadow-2xl shadow-black/80 text-center">
        <div class="absolute top-0 left-1/4 right-1/4 h-[1px] bg-gradient-to-r from-transparent via-[#f3bd35] to-transparent"></div>
        <?php if ($mayaRrn !== null): ?>
            <?php if (!$mayaRow): ?>
                <h1 class="text-xl font-black text-white mb-1">Payment not found</h1>
                <p class="text-xs text-zinc-400 max-w-sm mx-auto mt-3 leading-relaxed">This Maya payment link is not valid.</p>
                <a href="<?= e(url('main')) ?>" class="<?= $payBtnSecondary ?> mt-5"><?= icon('house', 'w-3.5 h-3.5 text-[#f3bd35]') ?><span>Main Portal</span></a>
            <?php elseif ($mayaPaid): ?>
                <?php if ($mayaSignup): ?>
                    <?php if ($mayaSignup['status'] === 'completed'): ?>
                        <div class="mb-6 p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 text-left text-xs text-emerald-200 space-y-1" role="status">
                            <p class="font-bold text-white text-sm">Welcome to 88MMM Circle, <?= e($mayaSignup['name']) ?>!</p>
                            <?php if ($signupSignedIn || ($user && $user['id'] === $mayaSignup['user_id'])): ?>
                                <p>Your payment is confirmed and your account is ready. You're signed in.</p>
                            <?php else: ?>
                                <p>Your payment is confirmed and your account is ready. Sign in with <strong class="text-white"><?= e($mayaSignup['email']) ?></strong> and the password you chose.</p>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="mb-6 p-4 rounded-2xl bg-amber-950/40 border border-amber-600/40 text-left text-xs text-amber-200" role="alert">
                            <p class="font-bold text-white text-sm mb-1">Payment received, but no new account was created</p>
                            <?php if ($mayaSignup['status'] === 'email_taken'): ?>
                                <p><?= e($mayaSignup['email']) ?> already has an account. Please sign in with it, and contact the 88MMM administrator about this payment (reference <?= e($mayaPaid['referenceNumber']) ?>).</p>
                            <?php else: ?>
                                <p>The sign-up had expired before Maya confirmed the payment. Please contact the 88MMM administrator with reference <?= e($mayaPaid['referenceNumber']) ?>.</p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
                <div class="space-y-6 py-2" data-print-area>
                    <div class="w-16 h-16 rounded-full bg-gradient-to-tr from-[#ffda72] via-[#f3bd35] to-[#8f6510] text-black flex items-center justify-center mx-auto shadow-xl"><?= icon('circle-check-big', 'w-9 h-9') ?></div>
                    <div>
                        <span class="text-[10px] px-2.5 py-0.5 rounded-full bg-[#f3bd35]/15 border border-[#f3bd35]/30 text-[#ffda72] font-bold tracking-widest uppercase">Paid via Maya Checkout</span>
                        <?php if ($mayaRow['environment'] === 'sandbox'): ?>
                            <span class="ml-1 text-[10px] px-2.5 py-0.5 rounded-full bg-amber-950/40 border border-amber-600/40 text-amber-200 font-bold tracking-widest uppercase">Sandbox test</span>
                        <?php endif; ?>
                        <h1 class="text-2xl font-black text-white mt-2">Official Digital Receipt</h1>
                        <p class="text-xs text-zinc-400 mt-0.5">Transaction Reference: <strong class="text-zinc-200 font-mono"><?= e($mayaPaid['referenceNumber']) ?></strong></p>
                    </div>
                    <div class="p-5 rounded-2xl bg-[#0c0c0c] border border-[#f3bd35]/30 text-left space-y-3.5 text-xs">
                        <div class="flex items-center justify-between gap-3 pb-3 border-b border-zinc-800">
                            <div>
                                <span class="text-[10px] uppercase text-zinc-500 font-bold block">Purchased Tier / Item</span>
                                <span class="text-sm font-bold text-white"><?= e($mayaPaid['itemName']) ?></span>
                            </div>
                            <div class="text-right">
                                <span class="text-[10px] uppercase text-zinc-500 font-bold block">Amount Paid</span>
                                <span class="text-base font-black text-[#ffda72] font-mono whitespace-nowrap"><?= peso($mayaPaid['amount']) ?> PHP</span>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3 text-[11px]">
                            <div><span class="text-zinc-500 block">Customer Name</span><span class="text-zinc-200 font-medium"><?= e($mayaPaid['customerName']) ?></span></div>
                            <div><span class="text-zinc-500 block">Contact Endpoint</span><span class="text-zinc-200 font-medium break-all"><?= e($mayaPaid['customerEmail'] ?: $mayaPaid['customerPhone']) ?></span></div>
                            <div><span class="text-zinc-500 block">Payment Method</span><span class="text-zinc-200 font-semibold uppercase flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>Maya</span></div>
                            <div><span class="text-zinc-500 block">NFC Hardware Assigned</span><span class="text-[#f3bd35] font-mono font-bold"><?= e((string) $mayaPaid['nfcCardSerial']) ?></span></div>
                        </div>
                        <div class="pt-2 border-t border-zinc-800/80 text-[10px] text-zinc-400 break-words"><span class="font-semibold text-zinc-300">Auth Note: </span><?= e((string) $mayaPaid['receiptNote']) ?></div>
                    </div>
                    <div class="flex flex-col sm:flex-row items-center gap-2.5">
                        <button type="button" data-pay-print class="w-full sm:flex-1 flex items-center justify-center gap-1.5 py-3 rounded-xl bg-zinc-900 hover:bg-zinc-800 border border-zinc-700 text-xs font-semibold text-zinc-200 transition-colors"><?= icon('printer', 'w-3.5 h-3.5') ?><span>Print / Save Receipt</span></button>
                        <?php if ($mayaSignup && $mayaSignup['status'] === 'completed' && $user && $user['id'] === $mayaSignup['user_id']): ?>
                            <a href="<?= e(url('backoffice')) ?>" class="w-full sm:flex-1 flex items-center justify-center gap-1.5 py-3 rounded-xl bg-gradient-to-r from-[#ffda72] to-[#f3bd35] text-black font-extrabold text-xs shadow-lg hover:brightness-105 transition-all"><span>Open Member Backoffice</span><?= icon('arrow-right', 'w-3.5 h-3.5') ?></a>
                        <?php elseif ($mayaSignup && $mayaSignup['status'] === 'completed'): ?>
                            <a href="<?= e(url('main')) ?>" class="w-full sm:flex-1 flex items-center justify-center gap-1.5 py-3 rounded-xl bg-gradient-to-r from-[#ffda72] to-[#f3bd35] text-black font-extrabold text-xs shadow-lg hover:brightness-105 transition-all"><span>Sign In</span><?= icon('log-in', 'w-3.5 h-3.5') ?></a>
                        <?php else: ?>
                            <a href="<?= e(url('main')) ?>" class="w-full sm:flex-1 flex items-center justify-center gap-1.5 py-3 rounded-xl bg-gradient-to-r from-[#ffda72] to-[#f3bd35] text-black font-extrabold text-xs shadow-lg hover:brightness-105 transition-all"><span>Back to the Portal</span><?= icon('arrow-right', 'w-3.5 h-3.5') ?></a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php elseif ($mayaState === 'pending'): ?>
                <div data-maya-pending="<?= e($mayaRow['rrn']) ?>" class="py-4 space-y-4">
                    <span class="block w-10 h-10 mx-auto rounded-full border-2 border-[#f3bd35] border-t-transparent animate-spin" aria-hidden="true"></span>
                    <h1 class="text-xl font-black text-white">Confirming your payment with Maya…</h1>
                    <p class="text-xs text-zinc-400 max-w-sm mx-auto leading-relaxed" data-maya-wait-text role="status">This usually takes a few seconds. Please keep this page open.</p>
                    <p class="text-[10px] text-zinc-500">Reference <span class="font-mono"><?= e($mayaRow['rrn']) ?></span></p>
                </div>
            <?php else: ?>
                <div class="py-2 space-y-4">
                    <div class="w-14 h-14 rounded-full bg-rose-500/10 border border-rose-500/30 text-rose-300 flex items-center justify-center mx-auto"><?= icon('circle-alert', 'w-7 h-7') ?></div>
                    <h1 class="text-xl font-black text-white">Payment not completed</h1>
                    <p class="text-xs text-zinc-300 max-w-sm mx-auto leading-relaxed" role="alert"><?= e($mayaMessage) ?></p>
                    <p class="text-[10px] text-zinc-500"><?= e($mayaRow['item_name']) ?> · <?= peso($mayaRow['amount']) ?> · Reference <span class="font-mono"><?= e($mayaRow['rrn']) ?></span></p>
                    <div class="flex flex-col sm:flex-row items-center justify-center gap-2.5 pt-2">
                        <?php if ($mayaRow['status'] !== 'MISMATCH'): ?>
                            <a href="<?= e($mayaRetry) ?>" class="<?= $payBtnPrimary ?> w-full sm:w-auto"><?= icon('rotate-ccw', 'w-3.5 h-3.5') ?><span>Try Again</span></a>
                        <?php endif; ?>
                        <a href="<?= e(url('main')) ?>" class="<?= $payBtnSecondary ?> w-full sm:w-auto"><?= icon('house', 'w-3.5 h-3.5 text-[#f3bd35]') ?><span>Main Portal</span></a>
                    </div>
                </div>
            <?php endif; ?>
        <?php else: ?>
        <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-[#f3bd35]/15 border border-[#f3bd35]/30 text-[#f3bd35] text-[10px] font-bold tracking-widest uppercase mb-3"><?= icon('lock', 'w-3.5 h-3.5') ?>Secure 256-Bit Executive Checkout</div>
        <h1 class="text-xl font-black text-white flex items-center justify-center gap-2 mb-1">1EVOAI Online Payment Portal<?= icon('shield-check', 'w-5 h-5 text-[#f3bd35]') ?></h1>
        <?php if ($invoiceRef !== '' && !$checkoutInvoice): ?>
            <p class="text-xs text-zinc-400 max-w-sm mx-auto mt-3 leading-relaxed">This invoice link is no longer valid or has already been paid.</p>
            <a href="<?= e(url('main')) ?>" class="inline-flex items-center gap-2 mt-5 px-6 py-3 bg-zinc-900 hover:bg-zinc-800 border border-zinc-700 text-white font-bold text-xs rounded-xl transition-all"><?= icon('house', 'w-3.5 h-3.5 text-[#f3bd35]') ?><span>Main Portal</span></a>
        <?php elseif ($checkoutInvoice): ?>
            <p class="text-xs text-zinc-400 mb-5">Invoice <span class="font-mono text-[#ffda72]"><?= e($checkoutInvoice['referenceNumber']) ?></span> for <?= e($checkoutInvoice['customerName']) ?></p>
            <div class="p-4 rounded-2xl bg-zinc-950 border border-zinc-800 text-left flex items-center justify-between gap-3 mb-5">
                <span class="text-sm font-bold text-white"><?= e($checkoutInvoice['itemName']) ?></span>
                <span class="text-base font-black text-[#ffda72] font-mono whitespace-nowrap"><?= peso($checkoutInvoice['amount']) ?></span>
            </div>
            <button type="button" data-pay-invoice class="w-full px-6 py-3.5 bg-gradient-to-r from-[#ffda72] via-[#f3bd35] to-[#f3bd35] text-black font-extrabold text-xs rounded-xl shadow-xl hover:brightness-105 active:scale-95 transition-all flex items-center justify-center gap-2"><?= icon('lock', 'w-3.5 h-3.5') ?><span>Pay <?= peso($checkoutInvoice['amount']) ?> Now</span></button>
        <?php else: ?>
            <p class="text-xs text-zinc-400 mb-5">Official checkout for 88MMM Circle executive membership and digital identity activation.</p>
            <button type="button" data-checkout="<?= e($item) ?>" class="w-full px-6 py-3.5 bg-gradient-to-r from-[#ffda72] via-[#f3bd35] to-[#f3bd35] text-black font-extrabold text-xs rounded-xl shadow-xl hover:brightness-105 active:scale-95 transition-all flex items-center justify-center gap-2"><?= icon('credit-card', 'w-3.5 h-3.5') ?><span>Open Checkout</span></button>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/partials/checkout_modal.php'; ?>
