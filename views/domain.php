<?php
/**
 * .com domain order result: where Maya (or the test payment page) sends the
 * member back to.
 *
 *   ?view=domain&order=DOM-…&result=success|failure|cancel   back from Maya
 *   ?view=domain&order=DOM-…&mockpay=1                        test payment page (PAYMENT_DRIVER=mock)
 *
 * The status always comes from the payment provider (domain_sync), never
 * from ?result=. This is also the fallback when Maya's webhook can't reach
 * the server (local XAMPP): opening the page confirms the payment.
 */
declare(strict_types=1);

$domOrderId = isset($_GET['order']) && is_string($_GET['order']) ? $_GET['order'] : '';
$domOrder = preg_match('/^DOM-\d{6}-[0-9A-F]{10}$/', $domOrderId) ? db_one('SELECT * FROM domain_orders WHERE id = ?', [$domOrderId]) : null;
$domMine = $domOrder && $user && ($domOrder['user_id'] === $user['id'] || is_admin($user));
$domMock = $domMine && isset($_GET['mockpay']) && $domOrder['payment_driver'] === 'mock' && in_array($domOrder['status'], ['pending_payment', 'payment_failed'], true);
if ($domOrder && !$domMock) {
    $domOrder = domain_sync($domOrder['id']) ?? $domOrder;
}
$domResult = isset($_GET['result']) && is_string($_GET['result']) ? $_GET['result'] : '';
$domStatus = $domOrder['status'] ?? '';
// Back from Maya without a payment: nothing to wait for (a late payment is still recorded).
$domGaveUp = $domStatus === 'pending_payment' && in_array($domResult, ['cancel', 'failure'], true);
$domUd = $domOrder && $domOrder['user_domain_id'] ? db_one('SELECT * FROM user_domains WHERE id = ?', [$domOrder['user_domain_id']]) : null;
$domSandbox = $domOrder && $domOrder['payment_driver'] === 'maya' && maya_environment() === 'sandbox';
$domWhat = $domOrder && $domOrder['kind'] === 'renew' ? 'renewal' : 'registration';
$domBack = url('backoffice', ['tab' => 'digital_card']);

$pageScripts[] = 'assets/js/views/domain.js';
$pageMeta = ['title' => 'Your .com Domain', 'description' => 'Domain order status.', 'noindex' => true];
$btnPrimary = 'inline-flex items-center justify-center gap-2 px-6 py-3 bg-gradient-to-r from-[#ffda72] via-[#f3bd35] to-[#f3bd35] text-black font-extrabold text-xs rounded-xl shadow-xl hover:brightness-105 transition-all';
$btnSecondary = 'inline-flex items-center justify-center gap-2 px-6 py-3 bg-zinc-900 hover:bg-zinc-800 border border-zinc-700 text-white font-bold text-xs rounded-xl transition-all';
$btnDanger = 'inline-flex items-center justify-center gap-2 px-6 py-3 bg-zinc-900 hover:bg-rose-500/10 border border-rose-500/40 text-rose-200 font-bold text-xs rounded-xl transition-all';
?>
<div class="max-w-xl mx-auto px-4 py-8">
    <div class="relative rounded-3xl bg-[#111111]/95 border border-[#f3bd35]/35 p-6 sm:p-8 shadow-2xl shadow-black/80 text-center">
        <div class="absolute top-0 left-1/4 right-1/4 h-[1px] bg-gradient-to-r from-transparent via-[#f3bd35] to-transparent"></div>

        <?php if (!$domOrder || !$user): ?>
            <div class="py-2 space-y-4">
                <h1 class="text-xl font-black text-white"><?= $domOrder ? 'Sign in to see your order' : 'Order not found' ?></h1>
                <p class="text-xs text-zinc-400 max-w-sm mx-auto leading-relaxed">
                    <?= $domOrder ? 'Your payment is being confirmed. Sign in to the Executive Portal to see your domain.' : 'This domain order link is not valid.' ?>
                </p>
                <a href="<?= e(url('main')) ?>" class="<?= $btnSecondary ?>"><?= icon('house', 'w-3.5 h-3.5 text-[#f3bd35]') ?><span>Main Portal</span></a>
            </div>

        <?php elseif (!$domMine): ?>
            <div class="py-2 space-y-4">
                <h1 class="text-xl font-black text-white">Order not found</h1>
                <p class="text-xs text-zinc-400 max-w-sm mx-auto leading-relaxed">This domain order belongs to another account.</p>
                <a href="<?= e($domBack) ?>" class="<?= $btnSecondary ?>"><?= icon('arrow-left', 'w-3.5 h-3.5 text-[#f3bd35]') ?><span>Back to Backoffice</span></a>
            </div>

        <?php elseif ($domMock): ?>
            <div class="space-y-5 py-2" data-domain-mock="<?= e($domOrder['id']) ?>">
                <span class="inline-block text-[10px] px-2.5 py-0.5 rounded-full bg-amber-950/40 border border-amber-600/40 text-amber-200 font-bold tracking-widest uppercase">Test payment · no money is charged</span>
                <h1 class="text-xl font-black text-white">Pay for <?= e($domOrder['domain']) ?></h1>
                <div class="p-4 rounded-2xl bg-zinc-950 border border-zinc-800 text-left flex items-center justify-between gap-3">
                    <span class="text-sm font-bold text-white"><?= e($domOrder['domain']) ?> <span class="text-zinc-500 font-medium">· <?= $domOrder['kind'] === 'renew' ? 'renewal' : 'registration' ?>, 1 year</span><?php if (($domOrder['price_note'] ?? '') !== ''): ?><span class="block text-[11px] text-zinc-500 font-medium"><?= e($domOrder['price_note']) ?></span><?php endif; ?></span>
                    <span class="text-base font-black text-[#ffda72] font-mono whitespace-nowrap"><?= peso((float) $domOrder['amount']) ?></span>
                </div>
                <p class="text-[11px] text-zinc-400 leading-relaxed">PAYMENT_DRIVER is <span class="font-mono">mock</span>: this page stands in for Maya Checkout. <strong class="text-zinc-200">Pay now</strong> runs the same steps as a confirmed Maya payment.</p>
                <div class="flex flex-col sm:flex-row items-center justify-center gap-2.5">
                    <button type="button" data-domain-mockpay="success" class="<?= $btnPrimary ?> w-full sm:w-auto"><?= icon('lock', 'w-3.5 h-3.5') ?><span>Pay now</span></button>
                    <button type="button" data-domain-mockpay="failure" class="<?= $btnDanger ?> w-full sm:w-auto"><?= icon('circle-x', 'w-3.5 h-3.5') ?><span>Fail</span></button>
                    <button type="button" data-domain-mockpay="cancel" class="<?= $btnSecondary ?> w-full sm:w-auto"><span>Cancel</span></button>
                </div>
                <p class="text-[10px] text-zinc-500">Order <span class="font-mono"><?= e($domOrder['id']) ?></span></p>
            </div>

        <?php elseif ($domStatus === 'completed'): ?>
            <div class="space-y-5 py-2">
                <div class="w-16 h-16 rounded-full bg-gradient-to-tr from-[#ffda72] via-[#f3bd35] to-[#8f6510] text-black flex items-center justify-center mx-auto shadow-xl"><?= icon('circle-check-big', 'w-9 h-9') ?></div>
                <div>
                    <?php if ($domSandbox): ?><span class="text-[10px] px-2.5 py-0.5 rounded-full bg-amber-950/40 border border-amber-600/40 text-amber-200 font-bold tracking-widest uppercase">Maya sandbox test</span><?php endif; ?>
                    <?php if ($domOrder['payment_driver'] === 'mock'): ?><span class="text-[10px] px-2.5 py-0.5 rounded-full bg-amber-950/40 border border-amber-600/40 text-amber-200 font-bold tracking-widest uppercase">Test payment</span><?php endif; ?>
                    <h1 class="text-2xl font-black text-white mt-2"><?= e($domOrder['domain']) ?> is yours</h1>
                    <?php if ($domUd): ?>
                        <p class="text-xs text-zinc-400 mt-1"><?= $domOrder['kind'] === 'renew' ? 'Renewed' : 'Registered' ?> until <strong class="text-zinc-200"><?= e(date('F j, Y', strtotime($domUd['expires_at']))) ?></strong></p>
                    <?php endif; ?>
                </div>
                <div class="p-4 rounded-2xl bg-[#0c0c0c] border border-[#f3bd35]/30 text-left text-[11px] text-zinc-300 space-y-1.5">
                    <p><span class="text-zinc-500">Paid:</span> <?= peso((float) $domOrder['amount']) ?> PHP<?= ($domOrder['price_note'] ?? '') !== '' ? ' <span class="text-zinc-500">(' . e(strtolower(substr($domOrder['price_note'], 0, 1)) . substr($domOrder['price_note'], 1)) . ')</span>' : '' ?> via <?= $domOrder['payment_driver'] === 'maya' ? 'Maya' : ($domOrder['payment_driver'] === 'wallet' ? 'available balance' : 'test payment') ?></p>
                    <p><span class="text-zinc-500">Order:</span> <span class="font-mono"><?= e($domOrder['id']) ?></span></p>
                    <?php if ($domOrder['kind'] === 'register'): ?>
                        <p class="text-zinc-400 pt-1"><?= $domUd && $domUd['dns_ok'] ? 'Your domain now points to your digital card. It can take a few hours before it opens everywhere.' : 'Your domain is registered. Our team is connecting it to your digital card.' ?></p>
                        <?php if ($domUd && $domUd['use_for_card']): ?>
                            <p class="text-zinc-300"><?= $domUd['dns_ok'] ? 'It is now your card\'s address: your share link, QR code and vCard use' : 'Once it is connected, your share link, QR code and vCard will use' ?> <span class="font-mono text-emerald-300 break-all"><?= e($domUd['dns_ok'] ? (string) domain_card_url($domOrder['user_id']) : $domUd['domain']) ?></span>.</p>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                <a href="<?= e($domBack) ?>" class="<?= $btnPrimary ?>"><span>Back to Digital Card CMS</span><?= icon('arrow-right', 'w-3.5 h-3.5') ?></a>
            </div>

        <?php elseif ($domStatus === 'paid_failed_registration'): ?>
            <div class="space-y-4 py-2">
                <div class="w-14 h-14 rounded-full bg-amber-500/10 border border-amber-500/30 text-amber-300 flex items-center justify-center mx-auto"><?= icon('clock', 'w-7 h-7') ?></div>
                <h1 class="text-xl font-black text-white">Payment received</h1>
                <p class="text-xs text-zinc-300 max-w-sm mx-auto leading-relaxed" role="status">The <?= $domWhat ?> of <strong class="text-white"><?= e($domOrder['domain']) ?></strong> is taking longer than usual. Our team has been notified and will complete it. There is no need to pay again.</p>
                <p class="text-[10px] text-zinc-500">Order <span class="font-mono"><?= e($domOrder['id']) ?></span></p>
                <a href="<?= e($domBack) ?>" class="<?= $btnSecondary ?>"><?= icon('arrow-left', 'w-3.5 h-3.5 text-[#f3bd35]') ?><span>Back to Backoffice</span></a>
            </div>

        <?php elseif (in_array($domStatus, ['paid', 'registering'], true) || ($domStatus === 'pending_payment' && !$domGaveUp)): ?>
            <div data-domain-pending="<?= e($domOrder['id']) ?>" class="py-4 space-y-4">
                <span class="block w-10 h-10 mx-auto rounded-full border-2 border-[#f3bd35] border-t-transparent animate-spin" aria-hidden="true"></span>
                <h1 class="text-xl font-black text-white"><?= $domStatus === 'pending_payment' ? 'Confirming your payment…' : 'Payment confirmed. Completing the ' . $domWhat . '…' ?></h1>
                <p class="text-xs text-zinc-400 max-w-sm mx-auto leading-relaxed" data-domain-wait-text role="status">This usually takes a few seconds. Please keep this page open.</p>
                <p class="text-[10px] text-zinc-500"><?= e($domOrder['domain']) ?> · Order <span class="font-mono"><?= e($domOrder['id']) ?></span></p>
            </div>

        <?php else: ?>
            <?php
            $domMsg = match (true) {
                $domOrder['payment_status'] === 'MISMATCH' => 'The payment didn\'t match this order, so it wasn\'t accepted. Please contact the 88MMM administrator with the order number below.',
                $domStatus === 'payment_failed' => 'The payment didn\'t go through. Nothing was charged; you can try again with another card or your Maya wallet.',
                $domOrder['payment_status'] === 'PAYMENT_EXPIRED' => 'The checkout expired before it was paid. Nothing was charged.',
                $domOrder['payment_status'] === 'START_FAILED' => 'The payment page could not be opened. Nothing was charged.',
                default => 'The payment was cancelled. Nothing was charged.',
            };
            ?>
            <div class="py-2 space-y-4">
                <div class="w-14 h-14 rounded-full bg-rose-500/10 border border-rose-500/30 text-rose-300 flex items-center justify-center mx-auto"><?= icon('circle-alert', 'w-7 h-7') ?></div>
                <h1 class="text-xl font-black text-white">Payment not completed</h1>
                <p class="text-xs text-zinc-300 max-w-sm mx-auto leading-relaxed" role="alert"><?= e($domMsg) ?></p>
                <p class="text-[10px] text-zinc-500"><?= e($domOrder['domain']) ?> · <?= peso((float) $domOrder['amount']) ?> · Order <span class="font-mono"><?= e($domOrder['id']) ?></span></p>
                <div class="flex flex-col sm:flex-row items-center justify-center gap-2.5 pt-2">
                    <?php if ($domOrder['payment_status'] !== 'MISMATCH'): ?>
                        <button type="button" data-domain-again="<?= e($domOrder['kind'] === 'renew' ? 'renew:' . $domOrder['user_domain_id'] : 'register:' . $domOrder['domain']) ?>" data-domain-price="<?= e((string) (float) $domOrder['amount']) ?>" class="<?= $btnPrimary ?> w-full sm:w-auto"><?= icon('rotate-ccw', 'w-3.5 h-3.5') ?><span>Try Again</span></button>
                    <?php endif; ?>
                    <a href="<?= e($domBack) ?>" class="<?= $btnSecondary ?> w-full sm:w-auto"><?= icon('arrow-left', 'w-3.5 h-3.5 text-[#f3bd35]') ?><span>Back to Backoffice</span></a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
