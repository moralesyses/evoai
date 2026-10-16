<?php
/**
 * Management Admin > Encashments (superadmin only; includes/encashments.php,
 * assets/js/views/admin-encashments.js): payout figures, a six-month report,
 * and the requests (25 per page) with filters, totals and CSV download. A
 * pending request is released (after the money is sent, with its payout
 * reference) or rejected (the amount goes back to the member's available
 * balance). Ticked requests are released or rejected together, or downloaded
 * as CSV.
 */
$enF = encash_filters($_GET);
$enPage = max(1, (int) ($_GET['page'] ?? 1));
[$enRows, $enTotals] = encash_list($enF, $enPage);
$enPages = max(1, (int) ceil($enTotals['count'] / ENCASH_PER_PAGE));
if ($enPage > $enPages) {
    $enPage = $enPages;
    [$enRows, $enTotals] = encash_list($enF, $enPage);
}
$enSum = encash_summary();
$enFiltered = $enF['q'] !== '' || $enF['status'] !== '' || $enF['method'] !== '' || $enF['from'] !== '' || $enF['to'] !== '';
$enParams = ['tab' => 'encashments', 'q' => $enF['q'], 'status' => $enF['status'], 'method' => $enF['method'], 'from' => $enF['from'], 'to' => $enF['to']];
$enPageUrl = fn (int $to): string => url('admin', array_filter($enParams + ['page' => $to > 1 ? $to : '']));
$enField = 'px-3 py-2 rounded-xl bg-zinc-900 border border-zinc-700 text-xs text-white focus:outline-none focus:border-[#f3bd35]';
$enCard = 'p-4 rounded-2xl bg-[#111111] border border-zinc-800';
$enStatusPill = [
    'pending' => 'bg-amber-500/15 text-amber-300 border-amber-500/30',
    'released' => 'bg-emerald-500/15 text-emerald-300 border-emerald-500/30',
    'rejected' => 'bg-rose-500/15 text-rose-300 border-rose-500/30',
];
$enMonthName = static fn (string $ym): string => date('M Y', strtotime($ym . '-01'));
$enFirst = $enTotals['count'] ? ($enPage - 1) * ENCASH_PER_PAGE + 1 : 0;
$enLast = ($enPage - 1) * ENCASH_PER_PAGE + count($enRows);
?>
<div id="admin-encashments">
<div class="space-y-5">
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <div class="<?= $enCard ?> border-amber-500/30">
            <span class="text-[11px] text-zinc-400 font-medium flex items-center gap-1"><?= icon('clock', 'w-3.5 h-3.5 text-amber-300') ?>Pending requests</span>
            <div class="text-xl font-black text-white tabular-nums mt-1"><?= number_format((int) $enSum['pending']['n']) ?></div>
            <span class="text-[11px] text-amber-200">To pay out: <strong><?= peso($enSum['pending']['net']) ?></strong></span>
            <span class="block text-[10px] text-zinc-500">Requested <?= peso($enSum['pending']['gross']) ?></span>
        </div>
        <div class="<?= $enCard ?>">
            <span class="text-[11px] text-zinc-400 font-medium flex items-center gap-1"><?= icon('calendar', 'w-3.5 h-3.5 text-emerald-400') ?>Released this month</span>
            <div class="text-xl font-black text-emerald-300 tabular-nums mt-1"><?= peso($enSum['releasedMonth']['net']) ?></div>
            <span class="text-[11px] text-zinc-400"><?= number_format((int) $enSum['releasedMonth']['n']) ?> payout<?= (int) $enSum['releasedMonth']['n'] === 1 ? '' : 's' ?> in <?= e(date('F')) ?></span>
        </div>
        <div class="<?= $enCard ?>">
            <span class="text-[11px] text-zinc-400 font-medium flex items-center gap-1"><?= icon('wallet', 'w-3.5 h-3.5 text-[#f3bd35]') ?>Released, all time</span>
            <div class="text-xl font-black text-white tabular-nums mt-1"><?= peso($enSum['released']['net']) ?></div>
            <span class="text-[11px] text-zinc-400">Tax withheld <?= peso($enSum['released']['tax']) ?> · fees <?= peso($enSum['released']['fee']) ?></span>
        </div>
        <div class="<?= $enCard ?>">
            <span class="text-[11px] text-zinc-400 font-medium flex items-center gap-1"><?= icon('rotate-ccw', 'w-3.5 h-3.5 text-rose-300') ?>Rejected</span>
            <div class="text-xl font-black text-white tabular-nums mt-1"><?= number_format((int) $enSum['rejected']['n']) ?></div>
            <span class="text-[11px] text-zinc-400"><?= peso($enSum['rejected']['gross']) ?> returned to balances</span>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <section class="p-5 rounded-3xl bg-[#111111] border border-[#f3bd35]/20 space-y-3" aria-labelledby="en-channels-title">
            <h3 id="en-channels-title" class="text-sm font-bold text-white flex items-center gap-2"><?= icon('send', 'w-4 h-4 text-[#f3bd35]') ?>Pending by payout channel</h3>
            <div class="space-y-2">
                <?php foreach (ENCASH_METHODS as $enM => $enLabel): $enC = $enSum['pendingByMethod'][$enM] ?? ['count' => 0, 'net' => 0.0]; ?>
                    <a href="<?= e(url('admin', ['tab' => 'encashments', 'status' => 'pending', 'method' => $enM])) ?>" class="flex items-center justify-between gap-3 px-3 py-2.5 rounded-xl bg-zinc-950 border border-zinc-800 hover:border-[#f3bd35]/40 text-xs transition-colors">
                        <span class="font-semibold text-zinc-200"><?= e($enLabel) ?></span>
                        <span class="text-zinc-400"><?= $enC['count'] ?> · <strong class="text-white font-mono"><?= peso($enC['net']) ?></strong></span>
                    </a>
                <?php endforeach; ?>
            </div>
            <p class="text-[10px] text-zinc-500">Net amounts to send. Click a channel to see its queue.</p>
        </section>

        <section class="lg:col-span-2 p-5 rounded-3xl bg-[#111111] border border-[#f3bd35]/20 space-y-3" aria-labelledby="en-months-title">
            <h3 id="en-months-title" class="text-sm font-bold text-white flex items-center gap-2"><?= icon('chart-column', 'w-4 h-4 text-[#f3bd35]') ?>Monthly report (by request date)</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="text-zinc-500 uppercase text-[10px] tracking-wider">
                        <tr><th class="py-2 pr-3" scope="col">Month</th><th class="py-2 pr-3 text-right" scope="col">Requests</th><th class="py-2 pr-3 text-right" scope="col">Requested</th><th class="py-2 pr-3 text-right" scope="col">Released (net)</th><th class="py-2 pr-3 text-right" scope="col">Tax</th><th class="py-2 pr-3 text-right" scope="col">Fees</th><th class="py-2 text-right" scope="col">Pending / Rejected</th></tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800/80 text-zinc-300">
                        <?php if (!$enSum['months']): ?>
                            <tr><td colspan="7" class="py-3 text-zinc-500">No withdrawal requests in the last six months.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($enSum['months'] as $enMo): ?>
                            <tr>
                                <td class="py-2 pr-3 font-semibold text-zinc-200 whitespace-nowrap"><a class="hover:text-[#ffda72]" href="<?= e(url('admin', ['tab' => 'encashments', 'from' => $enMo['ym'] . '-01', 'to' => date('Y-m-t', strtotime($enMo['ym'] . '-01'))])) ?>"><?= e($enMonthName($enMo['ym'])) ?></a></td>
                                <td class="py-2 pr-3 text-right tabular-nums"><?= (int) $enMo['requests'] ?></td>
                                <td class="py-2 pr-3 text-right font-mono whitespace-nowrap"><?= peso($enMo['requested']) ?></td>
                                <td class="py-2 pr-3 text-right font-mono whitespace-nowrap text-emerald-300"><?= peso($enMo['released_net']) ?> <span class="text-zinc-500">(<?= (int) $enMo['released_n'] ?>)</span></td>
                                <td class="py-2 pr-3 text-right font-mono whitespace-nowrap"><?= peso($enMo['tax']) ?></td>
                                <td class="py-2 pr-3 text-right font-mono whitespace-nowrap"><?= peso($enMo['fees']) ?></td>
                                <td class="py-2 text-right tabular-nums whitespace-nowrap"><span class="text-amber-300"><?= (int) $enMo['pending_n'] ?></span> / <span class="text-rose-300"><?= (int) $enMo['rejected_n'] ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <div class="flex flex-col lg:flex-row justify-between gap-3 p-4 rounded-3xl bg-[#111111] border border-[#f3bd35]/20">
        <div>
            <h3 class="text-sm font-bold text-white">Withdrawal Requests</h3>
            <p class="text-[11px] text-zinc-500">Pending requests first (oldest at the top), then released and rejected ones (newest first).</p>
            <p class="text-xs text-zinc-400">The amount left the member's balance when they asked. Send the net payout, then <strong class="text-zinc-200">Release</strong> it with the transaction number; or <strong class="text-zinc-200">Reject</strong> it to return the amount to their balance.</p>
        </div>
        <form method="get" action="<?= e(base_path()) ?>/index.php" role="search" class="flex flex-wrap items-center gap-2" data-en-filters>
            <input type="hidden" name="view" value="admin">
            <input type="hidden" name="tab" value="encashments">
            <div class="relative w-full sm:w-72">
                <?= icon('search', 'w-4 h-4 text-zinc-400 absolute left-3 top-1/2 -translate-y-1/2') ?>
                <input name="q" type="search" value="<?= e($enF['q']) ?>" maxlength="100" placeholder="Member, account ID, reference" aria-label="Search by member, account ID, account details or reference" class="w-full pl-9 pr-3 py-2 rounded-xl bg-zinc-900 border border-zinc-700 text-xs text-white placeholder-zinc-500 focus:outline-none focus:border-[#f3bd35]">
            </div>
            <select name="status" aria-label="Filter by status" class="<?= $enField ?>">
                <option value="">All statuses</option>
                <?php foreach (ENCASH_STATUSES as $enValue => $enText): ?>
                    <option value="<?= e($enValue) ?>"<?= $enValue === $enF['status'] ? ' selected' : '' ?>><?= e($enText) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="method" aria-label="Filter by payout channel" class="<?= $enField ?>">
                <option value="">Any channel</option>
                <?php foreach (ENCASH_METHODS as $enValue => $enText): ?>
                    <option value="<?= e($enValue) ?>"<?= $enValue === $enF['method'] ? ' selected' : '' ?>><?= e($enText) ?></option>
                <?php endforeach; ?>
            </select>
            <input name="from" type="date" value="<?= e($enF['from']) ?>" aria-label="Requested from" class="<?= $enField ?>">
            <input name="to" type="date" value="<?= e($enF['to']) ?>" aria-label="Requested until" class="<?= $enField ?>">
            <button type="submit" class="px-4 py-2 bg-[#f3bd35] hover:bg-[#ffda72] text-black text-xs font-extrabold rounded-xl transition-colors">Apply</button>
            <?php if ($enFiltered): ?>
                <a href="<?= e(url('admin', ['tab' => 'encashments'])) ?>" class="px-3 py-2 text-xs text-zinc-400 hover:text-white text-center">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="rounded-3xl bg-[#111111] border border-[#f3bd35]/20 overflow-hidden shadow-xl" data-en-list data-filter="<?= e(json_encode(['q' => $enF['q'], 'status' => $enF['status'], 'method' => $enF['method'], 'from' => $enF['from'], 'to' => $enF['to']])) ?>">
        <div class="px-4 py-3 border-b border-zinc-800 flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-[11px]">
            <span class="text-zinc-400">
                <?php if ($enTotals['count'] > count($enRows)): ?>Showing <?= number_format($enFirst) ?>–<?= number_format($enLast) ?> of <?php endif; ?><?= number_format($enTotals['count']) ?> <?= $enTotals['count'] === 1 ? 'request' : 'requests' ?><?= $enFiltered ? ' match these filters' : ' in all' ?>
                <?php if ($enTotals['count']): ?> · gross <strong class="text-zinc-200"><?= peso($enTotals['gross']) ?></strong> · tax <?= peso($enTotals['tax']) ?> · fees <?= peso($enTotals['fee']) ?> · net <strong class="text-[#ffda72]"><?= peso($enTotals['net']) ?></strong><?php endif; ?>
            </span>
            <?php if ($enTotals['count']): ?>
                <button type="button" data-en-export class="self-start sm:self-auto px-3 py-1.5 bg-zinc-900 hover:bg-zinc-800 border border-zinc-700 text-zinc-200 text-[11px] font-semibold rounded-lg transition-colors flex items-center gap-1 disabled:opacity-60"><?= icon('download', 'w-3 h-3') ?><span>Download all <?= number_format($enTotals['count']) ?> as CSV</span></button>
            <?php endif; ?>
        </div>
        <div data-en-bulk-bar hidden class="px-4 py-3 border-b border-[#f3bd35]/30 bg-[#f3bd35]/10 flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-xs" role="region" aria-label="Selected requests">
            <div class="text-zinc-200">
                <span class="font-bold text-[#ffda72]" data-en-bulk-count>0</span> selected<span class="text-zinc-400" data-en-bulk-pending></span>
            </div>
            <div class="flex flex-wrap items-center gap-1.5">
                <button type="button" data-en-bulk="release" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-400 text-white text-[11px] font-bold rounded-lg transition-colors flex items-center gap-1 disabled:opacity-40"><?= icon('check', 'w-3 h-3') ?><span>Release selected</span></button>
                <button type="button" data-en-bulk="reject" class="px-3 py-1.5 bg-zinc-900 hover:bg-zinc-800 border border-zinc-700 text-zinc-200 text-[11px] font-semibold rounded-lg transition-colors flex items-center gap-1 disabled:opacity-40"><?= icon('rotate-ccw', 'w-3 h-3') ?><span>Reject selected</span></button>
                <button type="button" data-en-bulk-csv class="px-3 py-1.5 bg-zinc-900 hover:bg-zinc-800 border border-zinc-700 text-zinc-200 text-[11px] font-semibold rounded-lg transition-colors flex items-center gap-1 disabled:opacity-60"><?= icon('download', 'w-3 h-3') ?><span>Download selected CSV</span></button>
                <button type="button" data-en-bulk-clear class="px-2 py-1.5 text-[11px] text-zinc-400 hover:text-white">Clear</button>
            </div>
        </div>
        <?php if ($enRows): ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs" style="min-width:760px">
                    <thead class="bg-[#181818] border-b border-zinc-800 text-zinc-400 uppercase text-[10px] tracking-wider">
                        <tr>
                            <th class="py-3 pl-3.5 pr-1" scope="col">
                                <input type="checkbox" data-en-select-all aria-label="Select every request on this page" class="w-4 h-4 rounded bg-zinc-900 border-zinc-700 cursor-pointer" style="accent-color:#f3bd35">
                            </th>
                            <th class="py-3 px-4" scope="col">Request</th>
                            <th class="py-3 px-4" scope="col">Member</th>
                            <th class="py-3 px-4" scope="col">Pay to</th>
                            <th class="py-3 px-4 text-right" scope="col">Amount</th>
                            <th class="py-3 px-4" scope="col">Status</th>
                            <th class="py-3 px-4 text-right" scope="col" aria-label="Actions"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800/80">
                        <?php foreach ($enRows as $r): ?>
                            <?php
                            $enJson = json_encode([
                                'id' => $r['id'], 'status' => $r['status'], 'member' => $r['account_name'], 'head' => $r['account_id'], 'username' => $r['username'],
                                'channel' => encash_method_label($r['payout_method']), 'details' => $r['account_details'],
                                'gross' => peso($r['amount']), 'tax' => peso($r['tax_deduction']), 'fee' => peso($r['processing_fee']), 'net' => peso($r['net_amount']),
                                'grossValue' => (float) $r['amount'], 'netValue' => (float) $r['net_amount'],
                            ], JSON_UNESCAPED_UNICODE);
                            ?>
                            <tr class="hover:bg-zinc-900/50 transition-colors" data-en-row="<?= e($r['id']) ?>" data-en-item="<?= e($enJson) ?>">
                                <td class="py-3 pl-3.5 pr-1">
                                    <input type="checkbox" data-en-select value="<?= e($r['id']) ?>" aria-label="Select <?= e($r['id']) ?>" class="w-4 h-4 rounded bg-zinc-900 border-zinc-700 cursor-pointer" style="accent-color:#f3bd35">
                                </td>
                                <td class="py-3 px-4 whitespace-nowrap">
                                    <div class="font-mono text-zinc-200"><?= e($r['id']) ?></div>
                                    <div class="font-mono text-[10px] text-zinc-500"><?= e($r['reference_number']) ?></div>
                                    <div class="text-[10px] text-zinc-500"><?= e(fmt_datetime($r['requested_at'])) ?></div>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="text-zinc-200 font-semibold"><?= e($r['account_name']) ?></div>
                                    <div class="text-[10px] text-zinc-500 font-mono"><?= e($r['account_id']) ?> · @<?= e($r['username']) ?></div>
                                    <?php if ($r['member_email']): ?><div class="text-[10px] text-zinc-500 break-all"><?= e($r['member_email']) ?></div><?php endif; ?>
                                </td>
                                <td class="py-3 px-4">
                                    <span class="text-[10px] font-bold uppercase rounded-lg px-2 py-0.5 bg-zinc-900 border border-zinc-700 text-zinc-300"><?= e(encash_method_label($r['payout_method'])) ?></span>
                                    <div class="flex items-center gap-1.5 mt-1">
                                        <span class="text-zinc-200 break-words"><?= e($r['account_details']) ?></span>
                                        <button type="button" data-copy="<?= e($r['account_details']) ?>" data-copied-html="<?= e(icon('check', 'w-3 h-3 text-emerald-400')) ?>" class="text-zinc-500 hover:text-white shrink-0" aria-label="Copy the account details of <?= e($r['id']) ?>"><?= icon('copy', 'w-3 h-3') ?></button>
                                    </div>
                                </td>
                                <td class="py-3 px-4 text-right whitespace-nowrap">
                                    <div class="font-black font-mono text-[#ffda72]"><?= peso($r['net_amount']) ?></div>
                                    <div class="text-[10px] text-zinc-500">of <?= peso($r['amount']) ?> − <?= peso($r['tax_deduction']) ?> tax − <?= peso($r['processing_fee']) ?> fee</div>
                                </td>
                                <td class="py-3 px-4">
                                    <span class="text-[10px] px-2 py-0.5 rounded-full font-bold uppercase border <?= $enStatusPill[$r['status']] ?? '' ?>"><?= e(ENCASH_STATUSES[$r['status']] ?? $r['status']) ?></span>
                                    <?php if ($r['status'] !== 'pending'): ?>
                                        <div class="text-[10px] text-zinc-500 mt-1 whitespace-nowrap"><?= e($r['processed_at'] ? fmt_datetime($r['processed_at']) : '') ?><?= $r['processed_by_name'] ? ' · ' . e($r['processed_by_name']) : '' ?></div>
                                        <?php if ($r['payout_reference'] !== ''): ?><div class="text-[10px] text-zinc-300 font-mono break-all">Ref <?= e($r['payout_reference']) ?></div><?php endif; ?>
                                        <?php if ($r['admin_note'] !== ''): ?><div class="text-[10px] <?= $r['status'] === 'rejected' ? 'text-rose-300' : 'text-zinc-400' ?> break-words max-w-xs"><?= e($r['admin_note']) ?></div><?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-4 text-right whitespace-nowrap">
                                    <?php if ($r['status'] === 'pending'): ?>
                                        <div class="flex items-center justify-end gap-1.5">
                                            <button type="button" data-en-release class="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-400 text-white text-[11px] font-bold rounded-lg transition-colors">Release</button>
                                            <button type="button" data-en-reject class="px-2.5 py-1 bg-zinc-900 hover:bg-zinc-800 border border-zinc-700 text-zinc-300 hover:text-white text-[11px] font-medium rounded-lg transition-colors">Reject</button>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="px-4 py-8 text-center text-xs text-zinc-500"><?= $enFiltered ? 'No withdrawal requests match these filters.' : 'No withdrawal requests yet.' ?></p>
        <?php endif; ?>
    </div>
    <?php if ($enPages > 1): ?>
        <nav class="flex items-center justify-center gap-3 text-xs" aria-label="Pages">
            <?php if ($enPage > 1): ?>
                <a href="<?= e($enPageUrl($enPage - 1)) ?>" class="px-3 py-1.5 rounded-xl bg-zinc-900 hover:bg-zinc-800 border border-zinc-700 text-zinc-200 transition-colors">← Previous</a>
            <?php endif; ?>
            <span class="text-zinc-400">Page <?= $enPage ?> of <?= $enPages ?> · <?= number_format($enFirst) ?>–<?= number_format($enLast) ?> of <?= number_format($enTotals['count']) ?></span>
            <?php if ($enPage < $enPages): ?>
                <a href="<?= e($enPageUrl($enPage + 1)) ?>" class="px-3 py-1.5 rounded-xl bg-zinc-900 hover:bg-zinc-800 border border-zinc-700 text-zinc-200 transition-colors">Next →</a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>

</div>

    <?php /* Release / Reject: filled from the row's data by admin-encashments.js (outside the stack: no sibling margins). */ ?>
    <div data-modal="admin-en-release" hidden class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4" role="dialog" aria-modal="true" aria-labelledby="en-release-title">
        <form data-en-release-form class="w-full max-w-md bg-[#141414] border border-emerald-500/40 rounded-3xl p-6 shadow-2xl space-y-4 text-xs" novalidate>
            <div class="flex items-center justify-between pb-3 border-b border-zinc-800">
                <h4 class="font-bold text-white text-sm" id="en-release-title">Release Withdrawal</h4>
                <button type="button" data-close-modal class="text-zinc-400 hover:text-white" aria-label="Close"><?= icon('x', 'w-4 h-4') ?></button>
            </div>
            <div class="p-4 rounded-2xl bg-zinc-950 border border-zinc-800 space-y-1.5" data-en-summary></div>
            <p class="text-[11px] text-zinc-400">Send the <strong class="text-white">net amount</strong> first, then record its transaction number here. The member is notified and their transaction is marked completed.</p>
            <div>
                <label for="en-payout-ref" class="block text-[11px] font-semibold text-zinc-300 mb-1">Payout reference *</label>
                <input id="en-payout-ref" name="payoutReference" type="text" required maxlength="100" autocomplete="off" placeholder="GCash / Maya / bank transaction no." class="w-full <?= $enField ?> font-mono">
            </div>
            <div>
                <label for="en-release-note" class="block text-[11px] font-semibold text-zinc-300 mb-1">Note (optional, admins only)</label>
                <input id="en-release-note" name="note" type="text" maxlength="255" autocomplete="off" class="w-full <?= $enField ?>">
            </div>
            <p data-en-error hidden role="alert" class="p-3 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-300"></p>
            <button type="submit" class="w-full py-3 bg-emerald-600 hover:bg-emerald-400 text-white font-extrabold rounded-xl text-xs flex items-center justify-center gap-1.5 disabled:opacity-60"><?= icon('check', 'w-3.5 h-3.5') ?><span>Mark as Released</span></button>
        </form>
    </div>
    <div data-modal="admin-en-reject" hidden class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4" role="dialog" aria-modal="true" aria-labelledby="en-reject-title">
        <form data-en-reject-form class="w-full max-w-md bg-[#141414] border border-rose-500/40 rounded-3xl p-6 shadow-2xl space-y-4 text-xs" novalidate>
            <div class="flex items-center justify-between pb-3 border-b border-zinc-800">
                <h4 class="font-bold text-white text-sm" id="en-reject-title">Reject Withdrawal</h4>
                <button type="button" data-close-modal class="text-zinc-400 hover:text-white" aria-label="Close"><?= icon('x', 'w-4 h-4') ?></button>
            </div>
            <div class="p-4 rounded-2xl bg-zinc-950 border border-zinc-800 space-y-1.5" data-en-summary></div>
            <p class="text-[11px] text-zinc-400">The full requested amount, <strong class="text-white" data-en-gross></strong>, goes back to the member's available balance. They see the reason in their notifications and transactions.</p>
            <div>
                <label for="en-reject-reason" class="block text-[11px] font-semibold text-zinc-300 mb-1">Reason for the member *</label>
                <textarea id="en-reject-reason" name="reason" required maxlength="255" rows="3" placeholder="e.g. The GCash number doesn't match your name. Please request again with your own account." class="w-full <?= $enField ?>"></textarea>
            </div>
            <p data-en-error hidden role="alert" class="p-3 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-300"></p>
            <button type="submit" class="w-full py-3 bg-rose-500 hover:brightness-110 text-white font-extrabold rounded-xl text-xs flex items-center justify-center gap-1.5 disabled:opacity-60"><?= icon('rotate-ccw', 'w-3.5 h-3.5') ?><span>Reject &amp; Return to Balance</span></button>
        </form>
    </div>

    <?php /* Bulk Release / Reject of the ticked pending requests (admin-encashments.js fills the lists). */ ?>
    <div data-modal="admin-en-bulk-release" hidden class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4" role="dialog" aria-modal="true" aria-labelledby="en-bulk-release-title">
        <form data-en-bulk-release-form class="w-full max-w-2xl max-h-[90vh] overflow-y-auto bg-[#141414] border border-emerald-500/40 rounded-3xl p-6 shadow-2xl space-y-4 text-xs" novalidate>
            <div class="flex items-center justify-between pb-3 border-b border-zinc-800">
                <h4 class="font-bold text-white text-sm" id="en-bulk-release-title">Release <span data-en-bulk-n></span> Withdrawals</h4>
                <button type="button" data-close-modal class="text-zinc-400 hover:text-white" aria-label="Close"><?= icon('x', 'w-4 h-4') ?></button>
            </div>
            <p class="text-[11px] text-zinc-400">Send each <strong class="text-white">net amount</strong>, then enter its transaction number. For one batch payout, type the batch reference once and click <strong class="text-zinc-200">Use for all</strong>. Each member is notified.</p>
            <p data-en-bulk-skipped hidden class="text-[11px] text-amber-200"></p>
            <div class="flex flex-col sm:flex-row gap-2">
                <input type="text" data-en-bulk-same maxlength="100" autocomplete="off" placeholder="One reference for all (optional)" aria-label="One payout reference for all" class="flex-1 <?= $enField ?> font-mono">
                <button type="button" data-en-bulk-apply class="px-3 py-2 bg-zinc-900 hover:bg-zinc-800 border border-zinc-700 text-zinc-200 text-[11px] font-semibold rounded-xl transition-colors">Use for all</button>
            </div>
            <div class="rounded-2xl border border-zinc-800 divide-y divide-zinc-800" data-en-bulk-list></div>
            <div class="flex items-center justify-between gap-3 px-1">
                <span class="text-zinc-400">Total to send</span>
                <span class="text-base font-black font-mono text-[#ffda72]" data-en-bulk-total></span>
            </div>
            <div>
                <label for="en-bulk-note" class="block text-[11px] font-semibold text-zinc-300 mb-1">Note (optional, admins only)</label>
                <input id="en-bulk-note" name="note" type="text" maxlength="255" autocomplete="off" class="w-full <?= $enField ?>">
            </div>
            <p data-en-error hidden role="alert" class="p-3 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-300"></p>
            <button type="submit" class="w-full py-3 bg-emerald-600 hover:bg-emerald-400 text-white font-extrabold rounded-xl text-xs flex items-center justify-center gap-1.5 disabled:opacity-60"><?= icon('check', 'w-3.5 h-3.5') ?><span>Release <span data-en-bulk-n></span> Requests</span></button>
        </form>
    </div>
    <div data-modal="admin-en-bulk-reject" hidden class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4" role="dialog" aria-modal="true" aria-labelledby="en-bulk-reject-title">
        <form data-en-bulk-reject-form class="w-full max-w-md max-h-[90vh] overflow-y-auto bg-[#141414] border border-rose-500/40 rounded-3xl p-6 shadow-2xl space-y-4 text-xs" novalidate>
            <div class="flex items-center justify-between pb-3 border-b border-zinc-800">
                <h4 class="font-bold text-white text-sm" id="en-bulk-reject-title">Reject <span data-en-bulk-n></span> Withdrawals</h4>
                <button type="button" data-close-modal class="text-zinc-400 hover:text-white" aria-label="Close"><?= icon('x', 'w-4 h-4') ?></button>
            </div>
            <p data-en-bulk-skipped hidden class="text-[11px] text-amber-200"></p>
            <div class="rounded-2xl border border-zinc-800 divide-y divide-zinc-800 max-h-56 overflow-y-auto" data-en-bulk-list></div>
            <p class="text-[11px] text-zinc-400">Each requested amount goes back to its member's available balance, <strong class="text-white" data-en-bulk-total></strong> in all. Every member sees the reason below.</p>
            <div>
                <label for="en-bulk-reason" class="block text-[11px] font-semibold text-zinc-300 mb-1">Reason for the members *</label>
                <textarea id="en-bulk-reason" name="reason" required maxlength="255" rows="3" placeholder="e.g. Payouts are paused this week. Please request again on Monday." class="w-full <?= $enField ?>"></textarea>
            </div>
            <p data-en-error hidden role="alert" class="p-3 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-300"></p>
            <button type="submit" class="w-full py-3 bg-rose-500 hover:brightness-110 text-white font-extrabold rounded-xl text-xs flex items-center justify-center gap-1.5 disabled:opacity-60"><?= icon('rotate-ccw', 'w-3.5 h-3.5') ?><span>Reject <span data-en-bulk-n></span> &amp; Return to Balances</span></button>
        </form>
    </div>
</div>
