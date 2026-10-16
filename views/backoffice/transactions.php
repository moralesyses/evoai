<?php
/**
 * Backoffice tab 4 — Transactions ledger (reference component "On").
 *
 * All rows of the active head are rendered; the type pills and the search box
 * filter them client-side and recompute the inflow / outflow / net totals
 * (assets/js/views/bo-transactions.js). "Print Ledger" calls window.print().
 */
(function (array $transactions): void {
    $types = [
        ['id' => 'all', 'label' => 'All Transactions'],
        ['id' => 'binary_pairing', 'label' => 'Match Bonus (₱' . num(mlm_complan()['pairingBonus']) . ')'],
        ['id' => 'direct_referral', 'label' => 'Referral Bonus (₱' . num(mlm_complan()['directReferralBonus']) . ')'],
        ['id' => 'binary_flush', 'label' => 'Matches Over Limit'],
        ['id' => 'encashment', 'label' => 'Withdrawal Payouts'],
        ['id' => 'code_purchase', 'label' => 'Activation Codes'],
        ['id' => 'shop_purchase', 'label' => 'Shop Purchases'],
        ['id' => 'domain_purchase', 'label' => '.com Domains'],
        ['id' => 'card_sale', 'label' => 'Card Sales'],
        ['id' => 'card_refund', 'label' => 'Card Refunds'],
    ];
    $typeIcons = [
        'binary_pairing' => ['zap', 'w-4 h-4 text-[#f3bd35]'],
        'direct_referral' => ['users', 'w-4 h-4 text-emerald-400'],
        'binary_flush' => ['circle-x', 'w-4 h-4 text-zinc-500'],
        'encashment' => ['wallet', 'w-4 h-4 text-rose-400'],
        'code_purchase' => ['credit-card', 'w-4 h-4 text-purple-400'],
        'shop_purchase' => ['shopping-bag', 'w-4 h-4 text-blue-400'],
        'domain_purchase' => ['globe', 'w-4 h-4 text-sky-400'],
        'card_sale' => ['tag', 'w-4 h-4 text-emerald-400'],
        'card_refund' => ['rotate-ccw', 'w-4 h-4 text-rose-400'],
    ];
    $pillBase = 'px-3 py-2 rounded-xl text-xs font-semibold whitespace-nowrap transition-all';
    $pillOn = 'bg-gradient-to-r from-[#ffda72] to-[#f3bd35] text-black font-bold shadow-md';
    $pillOff = 'bg-zinc-900 border border-zinc-800 text-zinc-400 hover:text-white';

    $in = 0.0;
    $out = 0.0;
    foreach ($transactions as $tx) {
        if ($tx['direction'] === 'in') {
            $in += $tx['amount'];
        } elseif ($tx['direction'] === 'out') {
            $out += $tx['amount'];
        }
    }
    ?>
<div class="space-y-6" data-bo-tx>
    <div class="p-5 rounded-3xl bg-[#121212] border border-zinc-800 shadow-xl space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <h3 class="text-base font-bold text-white flex items-center gap-2">
                    <?= icon('trending-up', 'w-5 h-5 text-[#f3bd35]') ?>
                    Wallet Ledger &amp; Rewards History
                </h3>
                <p class="text-xs text-zinc-400 mt-0.5">Immutable digital record of referral bonuses, team matches, points dropped over the cap, withdrawals, and purchases.</p>
            </div>
            <button type="button" data-tx-print class="flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-zinc-900 border border-zinc-700 text-zinc-200 text-xs font-semibold hover:bg-zinc-800 transition-colors self-start sm:self-auto">
                <?= icon('printer', 'w-3.5 h-3.5 text-[#f3bd35]') ?>
                <span>Print Ledger</span>
            </button>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-1">
            <div class="p-3.5 rounded-2xl bg-zinc-950 border border-emerald-500/30">
                <span class="text-[10px] font-bold uppercase text-emerald-400 tracking-wider">Filtered Total Inflow</span>
                <div class="text-xl font-black text-emerald-400 font-mono mt-0.5" data-tx-in>+<?= peso($in) ?> PHP</div>
                <span class="text-[10px] text-zinc-400">Rewards &amp; bonuses received</span>
            </div>
            <div class="p-3.5 rounded-2xl bg-zinc-950 border border-rose-500/30">
                <span class="text-[10px] font-bold uppercase text-rose-400 tracking-wider">Filtered Total Outflow</span>
                <div class="text-xl font-black text-rose-400 font-mono mt-0.5" data-tx-out>-<?= peso($out) ?> PHP</div>
                <span class="text-[10px] text-zinc-400">Withdrawals &amp; package codes</span>
            </div>
            <div class="p-3.5 rounded-2xl bg-zinc-950 border border-[#f3bd35]/30">
                <span class="text-[10px] font-bold uppercase text-[#ffda72] tracking-wider">Net Balance Movement</span>
                <div class="text-xl font-black text-white font-mono mt-0.5" data-tx-net><?= peso($in - $out) ?> PHP</div>
                <span class="text-[10px] text-zinc-400">Net ledger change</span>
            </div>
        </div>
    </div>

    <div class="flex flex-col md:flex-row gap-3 items-stretch md:items-center justify-between">
        <div class="relative flex-1 max-w-md">
            <?= icon('search', 'w-4 h-4 text-zinc-500 absolute left-3.5 top-3') ?>
            <input type="text" data-tx-search aria-label="Search by reference #, title, or team member name" placeholder="Search by reference #, title, or team member name..." class="w-full pl-10 pr-3.5 py-2.5 rounded-2xl bg-zinc-900 border border-zinc-700 text-xs text-white placeholder-zinc-500 focus:outline-none focus:border-[#f3bd35]">
        </div>
        <div class="flex items-center gap-1.5 overflow-x-auto pb-1 max-w-full">
            <?php foreach ($types as $ty): $on = $ty['id'] === 'all'; ?>
                <button type="button" data-tx-type="<?= e($ty['id']) ?>" data-on="<?= $pillOn ?>" data-off="<?= $pillOff ?>" aria-pressed="<?= $on ? 'true' : 'false' ?>" class="<?= $pillBase ?> <?= $on ? $pillOn : $pillOff ?>"><?= e($ty['label']) ?></button>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="rounded-3xl bg-[#121212] border border-zinc-800 overflow-hidden shadow-xl">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-zinc-900/80 border-b border-zinc-800 text-zinc-400 uppercase text-[10px] tracking-wider">
                    <tr>
                        <th class="py-3 px-4">Transaction Details</th>
                        <th class="py-3 px-4">Reference #</th>
                        <th class="py-3 px-4">Date &amp; Time</th>
                        <th class="py-3 px-4">Type</th>
                        <th class="py-3 px-4 text-right">Amount</th>
                        <th class="py-3 px-4 text-right">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-800/80" data-tx-body>
                    <tr data-tx-empty<?= $transactions ? ' hidden' : '' ?>>
                        <td colspan="6" class="py-8 text-center text-zinc-500 text-xs">No transactions match the selected filter or search query.</td>
                    </tr>
                    <?php foreach ($transactions as $tx):
                        $isIn = $tx['direction'] === 'in';
                        [$ico, $icoCls] = $typeIcons[$tx['type']] ?? ['arrow-up-down', 'w-4 h-4 text-zinc-400'];
                        $ts = to_ts($tx['timestamp']);
                        $search = mb_strtolower($tx['title'] . "\n" . $tx['referenceNumber'] . "\n" . $tx['details'] . "\n" . ($tx['sourceMember'] ?? ''));
                        ?>
                        <tr data-tx-row data-type="<?= e($tx['type']) ?>" data-dir="<?= e($tx['direction']) ?>" data-amount="<?= e($tx['amount']) ?>" data-search="<?= e($search) ?>" class="hover:bg-zinc-900/40 transition-colors">
                            <td class="py-3.5 px-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-xl bg-zinc-900 border border-zinc-800 flex items-center justify-center flex-shrink-0">
                                        <?= icon($ico, $icoCls) ?>
                                    </div>
                                    <div>
                                        <div class="font-bold text-white"><?= e($tx['title']) ?></div>
                                        <?php if ($tx['details'] !== ''): ?>
                                            <div class="text-[11px] text-zinc-400 line-clamp-1"><?= e($tx['details']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3.5 px-4 font-mono text-zinc-400 text-[11px]"><?= e($tx['referenceNumber']) ?></td>
                            <td class="py-3.5 px-4 text-zinc-400 text-[11px]"><?= e(date('n/j/Y', $ts) . ' ' . date('h:i A', $ts)) ?></td>
                            <td class="py-3.5 px-4">
                                <span class="text-[10px] px-2 py-0.5 rounded-full font-bold uppercase bg-zinc-900 border border-zinc-800 text-zinc-300"><?= e(['binary_pairing' => 'match bonus', 'binary_flush' => 'over limit', 'direct_referral' => 'referral bonus', 'encashment' => 'withdrawal'][$tx['type']] ?? preg_replace('/_/', ' ', (string) $tx['type'], 1)) ?></span>
                            </td>
                            <td class="py-3.5 px-4 text-right">
                                <span class="font-black font-mono text-sm <?= (float) $tx['amount'] === 0.0 ? 'text-zinc-500' : ($isIn ? 'text-emerald-400' : 'text-zinc-200') ?>"><?= (float) $tx['amount'] === 0.0 ? '' : ($isIn ? '+' : '-') ?><?= peso($tx['amount']) ?> PHP</span>
                            </td>
                            <td class="py-3.5 px-4 text-right">
                                <span class="text-[9px] px-2 py-0.5 rounded-full uppercase font-bold <?= $tx['status'] === 'completed' ? 'bg-emerald-500/15 text-emerald-400 border border-emerald-500/30' : ($tx['status'] === 'rejected' ? 'bg-rose-500/15 text-rose-300 border border-rose-500/30' : 'bg-amber-500/15 text-amber-400 border border-amber-500/30') ?>"><?= e($tx['status']) ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php
})($transactions);
