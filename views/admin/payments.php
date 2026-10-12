<?php
/**
 * Management Admin > Online Payments & Ledger (reference component "Rn").
 * Uses $payments from views/admin.php (amounts in PHP, timestamps in ms).
 */
$payCompleted = array_values(array_filter($payments, static fn ($p) => $p['status'] === 'completed'));
$payGross = array_sum(array_column($payCompleted, 'amount'));
$payOrders = count($payCompleted);
$payAvg = $payOrders > 0 ? round($payGross / $payOrders) : 0;
$payMethodBadge = static function (string $method): array {
    switch ($method) {
        case 'gcash':
            return ['label' => 'GCash', 'bg' => 'bg-blue-500/20 text-blue-400 border-blue-500/40'];
        case 'maya':
            return ['label' => 'Maya', 'bg' => 'bg-emerald-500/20 text-emerald-400 border-emerald-500/40'];
        case 'card':
            return ['label' => 'Card (Visa/MC)', 'bg' => 'bg-indigo-500/20 text-indigo-400 border-indigo-500/40'];
        case 'bank_transfer':
            return ['label' => 'InstaPay Bank', 'bg' => 'bg-amber-500/20 text-amber-400 border-amber-500/40'];
        case 'mock':
            return ['label' => 'Test (mock)', 'bg' => 'bg-zinc-700/40 text-zinc-300 border-zinc-600'];
        case 'crypto':
            return ['label' => 'Crypto (USDT)', 'bg' => 'bg-yellow-500/20 text-yellow-400 border-yellow-500/40'];
        case 'invoice':
            return ['label' => 'Invoice', 'bg' => 'bg-zinc-700/40 text-zinc-300 border-zinc-600'];
        default:
            return ['label' => '1-Tap Pay', 'bg' => 'bg-zinc-700/40 text-zinc-300 border-zinc-600'];
    }
};
$payInput = 'w-full px-3 py-2 rounded-xl bg-zinc-900 border border-zinc-700 text-xs text-white focus:outline-none focus:border-[#f3bd35]';
// Maya is the only way to pay now; older records may still show the earlier (simulated) methods.
$payMethodsSeen = array_values(array_unique(array_merge(['maya'], array_column($payments, 'paymentMethod'))));
$payMayaOn = maya_enabled();
?>
<div class="space-y-6" id="admin-payments">
    <div data-modal="admin-pay-invoice" hidden class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4" role="dialog" aria-modal="true" aria-labelledby="pay-invoice-title">
        <div class="w-full max-w-md bg-[#141414] border border-[#f3bd35]/40 rounded-3xl p-6 shadow-2xl space-y-4 text-xs">
            <div class="flex items-center justify-between pb-3 border-b border-zinc-800">
                <h4 class="font-bold text-white text-sm" id="pay-invoice-title">Issue Executive Payment Invoice</h4>
                <button type="button" data-close-modal class="text-zinc-400 hover:text-white" aria-label="Close"><?= icon('x', 'w-4 h-4') ?></button>
            </div>
            <div class="space-y-3 py-2" data-invoice-done hidden>
                <span class="text-emerald-400 font-bold block flex items-center gap-1"><?= icon('circle-check-big', 'w-4 h-4') ?> Custom Invoice Link Generated!</span>
                <input readonly data-invoice-link aria-label="Invoice payment link" value="" class="w-full px-3 py-2 rounded-xl bg-zinc-950 border border-zinc-800 text-xs text-[#ffda72] font-mono focus:outline-none">
                <button type="button" data-invoice-copy class="w-full py-2.5 bg-[#f3bd35] text-black font-extrabold rounded-xl text-xs">Copy &amp; Send to Member</button>
            </div>
            <form id="pay-invoice-form" class="space-y-3">
                <input type="hidden" name="item" value="Chairman's Circle (VIP Tier)">
                <div>
                    <label for="pay-inv-name" class="block text-[10px] text-zinc-400 mb-1">Client / Executive Name</label>
                    <input id="pay-inv-name" name="clientName" type="text" required maxlength="150" placeholder="e.g. Atty. Miguel Ramos" class="<?= $payInput ?>">
                </div>
                <div>
                    <label for="pay-inv-email" class="block text-[10px] text-zinc-400 mb-1">Client Email</label>
                    <input id="pay-inv-email" name="clientEmail" type="email" required maxlength="190" placeholder="client@apexholdings.ph" class="<?= $payInput ?>">
                </div>
                <div>
                    <label for="pay-inv-amount" class="block text-[10px] text-zinc-400 mb-1">Amount in PHP (₱)</label>
                    <input id="pay-inv-amount" name="amount" type="number" required min="1" max="10000000" step="any" value="14000" class="w-full px-3 py-2 rounded-xl bg-zinc-900 border border-zinc-700 text-xs text-[#ffda72] font-mono font-bold focus:outline-none focus:border-[#f3bd35]">
                </div>
                <button type="submit" class="w-full py-3 bg-gradient-to-r from-[#ffda72] to-[#f3bd35] text-black font-extrabold rounded-xl text-xs mt-2">Create Payment Link</button>
            </form>
        </div>
    </div>
    <div data-modal="admin-pay-receipt" hidden class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4" role="dialog" aria-modal="true" aria-labelledby="pay-receipt-title">
        <div class="w-full max-w-md bg-[#141414] border border-[#f3bd35]/40 rounded-3xl p-6 shadow-2xl space-y-4 text-xs" data-pay-receipt-box>
            <div class="flex items-center justify-between pb-3 border-b border-zinc-800">
                <div class="flex items-center gap-2">
                    <?= icon('receipt', 'w-4 h-4 text-[#f3bd35]') ?>
                    <h4 class="font-bold text-white text-sm" id="pay-receipt-title">Receipt #<span data-rc="referenceNumber"></span></h4>
                </div>
                <button type="button" data-close-modal class="text-zinc-400 hover:text-white" aria-label="Close"><?= icon('x', 'w-4 h-4') ?></button>
            </div>
            <div class="p-4 rounded-2xl bg-zinc-950 border border-zinc-800 space-y-3">
                <div class="flex justify-between items-baseline">
                    <span class="text-zinc-400">Total Amount:</span>
                    <span class="text-lg font-black text-[#ffda72] font-mono" data-rc="amount"></span>
                </div>
                <div class="text-[11px] space-y-1.5 text-zinc-300">
                    <div class="flex justify-between"><span class="text-zinc-500">Customer:</span><span class="font-semibold text-white" data-rc="customerName"></span></div>
                    <div class="flex justify-between"><span class="text-zinc-500">Contact:</span><span data-rc="contact"></span></div>
                    <div class="flex justify-between"><span class="text-zinc-500">Gateway:</span><span class="uppercase font-bold text-[#f3bd35]" data-rc="paymentMethod"></span></div>
                    <div class="flex justify-between"><span class="text-zinc-500">NFC Serial:</span><span class="font-mono text-zinc-300" data-rc="nfcCardSerial"></span></div>
                    <div class="flex justify-between"><span class="text-zinc-500">Date:</span><span data-rc="date"></span></div>
                </div>
                <p class="text-[10px] text-zinc-400 pt-2 border-t border-zinc-800" data-rc="receiptNote" hidden></p>
            </div>
            <div class="flex gap-2">
                <button type="button" data-pay-print class="flex-1 py-2.5 rounded-xl bg-zinc-900 hover:bg-zinc-800 border border-zinc-700 text-xs font-semibold text-zinc-200 flex items-center justify-center gap-1.5"><?= icon('printer', 'w-3.5 h-3.5') ?> Print Receipt</button>
                <button type="button" data-close-modal class="flex-1 py-2.5 rounded-xl bg-[#f3bd35] hover:bg-[#ffda72] text-black font-extrabold text-xs">Done</button>
            </div>
        </div>
    </div>
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 p-4 rounded-3xl bg-[#111111] border border-[#f3bd35]/20">
        <div>
            <h3 class="text-sm font-bold text-white flex items-center gap-2">Online Payments, Invoicing &amp; Gateway Ledger<?= icon('shield-check', 'w-4 h-4 text-emerald-400') ?></h3>
            <p class="text-xs text-zinc-400">88MMM Circle memberships, renewals and invoices, all paid through Maya Checkout. Older records may show earlier methods.</p>
        </div>
        <button type="button" data-open-modal="admin-pay-invoice" class="flex items-center gap-1.5 px-3.5 py-2 bg-gradient-to-r from-[#ffda72] to-[#f3bd35] text-black font-extrabold text-xs rounded-xl shadow-md hover:brightness-105 active:scale-95 transition-all">
            <?= icon('plus', 'w-3.5 h-3.5') ?><span>Issue Payment Invoice</span>
        </button>
    </div>
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <div class="p-4 rounded-2xl bg-[#121212] border border-[#f3bd35]/25">
            <span class="text-[11px] text-zinc-400 font-medium">Total Gross Revenue</span>
            <div class="text-2xl font-black text-[#ffda72] tabular-nums mt-1 font-mono" data-pay-kpi="gross"><?= e(peso($payGross)) ?></div>
            <span class="text-[10px] text-emerald-400 mt-1 flex items-center gap-1"><?= icon('trending-up', 'w-3 h-3') ?> 100% Collected &amp; Verified</span>
        </div>
        <div class="p-4 rounded-2xl bg-[#121212] border border-zinc-800">
            <span class="text-[11px] text-zinc-400 font-medium">Successful Orders</span>
            <div class="text-2xl font-black text-white tabular-nums mt-1 font-mono" data-pay-kpi="orders"><?= e((string) $payOrders) ?></div>
            <span class="text-[10px] text-zinc-500 mt-1">Paid Memberships &amp; Hardware</span>
        </div>
        <div class="p-4 rounded-2xl bg-[#121212] border border-zinc-800">
            <span class="text-[11px] text-zinc-400 font-medium">Average Order Value</span>
            <div class="text-2xl font-black text-white tabular-nums mt-1 font-mono" data-pay-kpi="avg"><?= e(peso($payAvg)) ?></div>
            <span class="text-[10px] text-[#f3bd35] mt-1">PHP / Transaction</span>
        </div>
        <div class="p-4 rounded-2xl bg-[#121212] border border-zinc-800">
            <span class="text-[11px] text-zinc-400 font-medium">Payment Gateway</span>
            <div class="text-xl font-extrabold <?= $payMayaOn ? 'text-emerald-400' : 'text-amber-300' ?> mt-1"><?= $payMayaOn ? 'Maya' : 'Not set up' ?></div>
            <span class="text-[10px] text-zinc-400 mt-1"><?= $payMayaOn ? 'Maya Checkout' . (maya_environment() === 'sandbox' ? ' (sandbox)' : '') : 'Add the Maya keys to take payments' ?></span>
        </div>
    </div>
    <div class="p-3 rounded-2xl bg-[#111111] border border-zinc-800 flex flex-col md:flex-row items-center justify-between gap-3 text-xs">
        <div class="relative w-full md:w-72">
            <?= icon('search', 'w-3.5 h-3.5 text-zinc-500 absolute left-3 top-1/2 -translate-y-1/2') ?>
            <input type="text" data-pay-search placeholder="Search ref #, name, email..." aria-label="Search payments" class="w-full pl-8 pr-3 py-1.5 rounded-xl bg-zinc-900 border border-zinc-700 text-xs text-white focus:outline-none focus:border-[#f3bd35]">
        </div>
        <div class="flex items-center gap-2 w-full md:w-auto">
            <select data-pay-method aria-label="Filter by gateway" class="px-2.5 py-1.5 rounded-xl bg-zinc-900 border border-zinc-700 text-zinc-300 text-xs focus:outline-none">
                <option value="all">All Gateways</option>
                <?php foreach ($payMethodsSeen as $pm): ?>
                    <option value="<?= e($pm) ?>"><?= e($payMethodBadge($pm)['label']) ?></option>
                <?php endforeach; ?>
            </select>
            <select data-pay-status aria-label="Filter by status" class="px-2.5 py-1.5 rounded-xl bg-zinc-900 border border-zinc-700 text-zinc-300 text-xs focus:outline-none">
                <option value="all">All Statuses</option>
                <option value="completed">Completed</option>
                <option value="pending">Pending</option>
                <option value="refunded">Refunded</option>
            </select>
        </div>
    </div>
    <div class="rounded-3xl bg-[#111111] border border-[#f3bd35]/20 overflow-hidden shadow-xl">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-[#181818] border-b border-zinc-800 text-zinc-400 uppercase text-[10px] tracking-wider">
                    <tr>
                        <th class="py-3 px-4">Transaction / Ref</th>
                        <th class="py-3 px-4">Customer</th>
                        <th class="py-3 px-4">Tier / Item</th>
                        <th class="py-3 px-4">Gateway</th>
                        <th class="py-3 px-4">Amount</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4 text-right">Receipt &amp; Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-800/80">
                    <?php foreach ($payments as $payRow):
                        $payBadge = $payMethodBadge((string) $payRow['paymentMethod']);
                        $paySt = $payRow['status'];
                        $paySearch = implode("\n", array_map(static fn ($v) => mb_strtolower((string) $v), [$payRow['customerName'], $payRow['referenceNumber'], $payRow['customerEmail'], $payRow['itemName']])); ?>
                        <tr class="hover:bg-zinc-900/50 transition-colors" data-pay-row="<?= e($payRow['id']) ?>" data-method="<?= e($payRow['paymentMethod']) ?>" data-status="<?= e($paySt) ?>" data-search="<?= e($paySearch) ?>">
                            <td class="py-3 px-4">
                                <div class="font-mono text-zinc-200 font-bold"><?= e($payRow['referenceNumber']) ?></div>
                                <div class="text-[10px] text-zinc-500"><?= e(fmt_date($payRow['timestamp'])) ?> · <?= e(date('h:i A', to_ts($payRow['timestamp']))) ?></div>
                            </td>
                            <td class="py-3 px-4">
                                <div class="font-bold text-white"><?= e($payRow['customerName']) ?></div>
                                <div class="text-[11px] text-zinc-400"><?= e(($payRow['customerEmail'] ?? '') !== '' ? $payRow['customerEmail'] : $payRow['customerPhone']) ?></div>
                            </td>
                            <td class="py-3 px-4">
                                <div class="font-medium text-zinc-200"><?= e($payRow['itemName']) ?></div>
                                <?php if (!empty($payRow['nfcCardSerial'])): ?><div class="text-[10px] text-[#f3bd35] font-mono">NFC ID: <?= e($payRow['nfcCardSerial']) ?></div><?php endif; ?>
                            </td>
                            <td class="py-3 px-4">
                                <span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-bold border <?= e($payBadge['bg']) ?>"><?= e($payBadge['label']) ?></span>
                            </td>
                            <td class="py-3 px-4">
                                <span class="font-mono font-black text-[#ffda72] text-sm"><?= e(peso($payRow['amount'])) ?></span>
                            </td>
                            <td class="py-3 px-4">
                                <span data-pay-when="completed"<?= $paySt === 'completed' ? '' : ' hidden' ?> class="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-400"><?= icon('circle-check-big', 'w-3.5 h-3.5') ?> Paid</span>
                                <span data-pay-when="pending"<?= $paySt === 'pending' ? '' : ' hidden' ?> class="inline-flex items-center gap-1 text-[11px] font-semibold text-amber-400"><?= icon('clock', 'w-3.5 h-3.5') ?> Pending</span>
                                <span data-pay-when="refunded"<?= $paySt === 'refunded' ? '' : ' hidden' ?> class="inline-flex items-center gap-1 text-[11px] font-semibold text-rose-400"><?= icon('rotate-ccw', 'w-3.5 h-3.5') ?> Refunded</span>
                            </td>
                            <td class="py-3 px-4 text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    <button type="button" data-pay-receipt="<?= e($payRow['id']) ?>" class="px-2.5 py-1 bg-zinc-900 hover:bg-zinc-800 border border-zinc-700 text-zinc-200 text-[11px] font-medium rounded-lg transition-colors flex items-center gap-1">
                                        <?= icon('receipt', 'w-3 h-3 text-[#f3bd35]') ?><span>Receipt</span>
                                    </button>
                                    <button type="button" data-pay-set="refunded" data-pay-when="completed"<?= $paySt === 'completed' ? '' : ' hidden' ?> class="px-2 py-1 text-zinc-500 hover:text-rose-400 text-[10px] transition-colors" title="Refund Payment">Refund</button>
                                    <button type="button" data-pay-set="completed" data-pay-when="pending"<?= $paySt === 'pending' ? '' : ' hidden' ?> class="px-2 py-1 text-emerald-400 hover:text-emerald-300 font-bold text-[10px] transition-colors">Approve</button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Receipt -->

    <!-- Issue invoice -->
</div>
