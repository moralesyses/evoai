<?php
/**
 * Backoffice tab 1 — Dashboard (reference component "Tn").
 *
 * Complan engine, wallet stats, daily cycle progress, NFC card strip, team
 * volume, recent transactions, notifications, audit logs and the encashment
 * modal. Behaviour lives in assets/js/views/bo-dashboard.js.
 *
 * Uses: $user, $account, $complan, $profile, $details, $transactions,
 *       $notifications, $logs (see views/backoffice.php).
 * Local variables are prefixed with $bd to avoid clobbering the wrapper scope.
 */
$bdMax = max(1, (int) $complan['maxPairsPerCycle']);
$bdC1 = (int) $account['cycle1PairsToday'];
$bdC2 = (int) $account['cycle2PairsToday'];
$bdP1 = min(100, (int) round($bdC1 / $bdMax * 100));
$bdP2 = min(100, (int) round($bdC2 / $bdMax * 100));
$bdPair = (int) $complan['pairingBonus'];
// One card per cycle of the plan (1 or 2), each with its pair count, colours and progress.
$bdCycles = [[1, $bdC1, $bdP1, 'text-[#f3bd35]', 'from-amber-500 to-[#f3bd35]']];
if ($complan['cyclesPerDay'] > 1) {
    $bdCycles[] = [2, $bdC2, $bdP2, 'text-[#ffda72]', 'from-emerald-500 to-teal-400'];
}
$bdToday = $complan['cyclesPerDay'] > 1 ? $bdC1 + $bdC2 : $bdC1;

$bdCardId = $profile['nfcCardId'] ?: 'MMC-VIP-88001';
$bdTaps = (int) ($profile['metrics']['taps'] ?? 0) ?: 3840;
$bdFrozen = ($profile['membershipStatus'] ?? '') === 'Frozen (Card Lost)';
$bdSlug = domain_card_domain($user['id']) ?? preg_replace('#^https?://#', '', card_share_url($profile, false)); // their own .com domain when the card uses one
$bdAction = $profile['nfcAction'] ?? 'card';
$bdTapDest = $bdAction === 'vcard'
    ? 'Direct vCard Address Book Download'
    : ($bdAction === 'whatsapp' ? 'Instant WhatsApp VIP Concierge' : 'Executive Digital Card Portal (' . $bdSlug . ')');

$bdRecent = array_slice($transactions, 0, 5);
$bdUnread = count(array_filter($notifications, fn ($n) => !$n['read']));
$bdLogs = array_slice($logs, 0, 3);

// Encashment defaults: the member's own mobile number (PH numbers in local
// 09xx-xxx-xxxx form, e.g. "0917-888-6272 (Mike Maranan)") and demo PIN 8888.
$bdPhoneSrc = trim((string) (($details['phone'] ?? '') ?: ($profile['phone'] ?? '')));
$bdDigits = preg_replace('/\D+/', '', $bdPhoneSrc);
if (strlen($bdDigits) === 12 && str_starts_with($bdDigits, '639')) {
    $bdDigits = '0' . substr($bdDigits, 2);
}
$bdPhone = (strlen($bdDigits) === 11 && str_starts_with($bdDigits, '09'))
    ? substr($bdDigits, 0, 4) . '-' . substr($bdDigits, 4, 3) . '-' . substr($bdDigits, 7)
    : $bdPhoneSrc;
$bdPayoutDetails = trim($bdPhone . ' (' . $user['name'] . ')');
$bdPin = empty($details['hasPin']) ? '8888' : '';
$bdAmount = 5000;
$bdTax = (int) round($bdAmount * 0.1);
$bdNet = max(0, $bdAmount - $bdTax - 50);
$bdBalance = (float) $account['availableBalance'];

$bdChannelOn = 'bg-[#f3bd35] text-black shadow';
$bdChannelOff = 'bg-zinc-900 border border-zinc-800 text-zinc-400 hover:text-white';
?>
<div class="space-y-6" id="bo-dashboard" data-balance="<?= e($bdBalance) ?>">
    <div data-modal="bo-encash" hidden class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-md p-4 animate-in fade-in" role="dialog" aria-modal="true" aria-labelledby="bo-encash-title">
        <div class="w-full max-w-lg rounded-3xl bg-[#141414] border border-[#f3bd35]/40 p-6 shadow-2xl relative text-left max-h-[92vh] overflow-y-auto">
            <button type="button" data-close-modal aria-label="Close" class="absolute top-4 right-4 text-zinc-400 hover:text-white p-1 rounded-full"><?= icon('x', 'w-5 h-5') ?></button>
            <div class="mb-4">
                <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-[#f3bd35]/15 border border-[#f3bd35]/30 text-[#f3bd35] text-[10px] font-bold uppercase tracking-wider mb-1">
                    <?= icon('wallet', 'w-3.5 h-3.5') ?>
                    Executive Payout Request
                </div>
                <h3 id="bo-encash-title" class="text-xl font-bold text-white">Member Withdrawal</h3>
                <p class="text-xs text-zinc-400 mt-0.5">Current Available Balance: <strong class="text-[#ffda72] font-mono"><?= peso($bdBalance) ?> PHP</strong></p>
            </div>
            <div data-bo-encash-success hidden class="p-4 rounded-2xl bg-emerald-950/40 border border-emerald-500/40 text-emerald-300 text-xs text-center space-y-2">
                <?= icon('circle-check', 'w-8 h-8 text-emerald-400 mx-auto') ?>
                <p class="font-bold" data-msg></p>
                <p class="text-[11px] text-zinc-400">Finance will release payout to your selected gateway.</p>
            </div>
            <form id="bo-encash-form" class="space-y-4">
                <div data-bo-encash-error hidden class="p-3 rounded-xl bg-rose-950/40 border border-rose-500/40 text-rose-300 text-xs flex items-center gap-2">
                    <?= icon('circle-alert', 'w-4 h-4 flex-shrink-0') ?>
                    <span data-msg></span>
                </div>
                <div>
                    <label for="bo-encash-amount" class="block text-[11px] font-semibold text-zinc-300 mb-1">Withdrawal Amount (PHP) *</label>
                    <input id="bo-encash-amount" name="amount" type="number" min="500" max="<?= e($bdBalance) ?>" step="100" value="<?= $bdAmount ?>" class="w-full px-3.5 py-2.5 rounded-xl bg-zinc-900 border border-zinc-700 text-white font-mono text-sm focus:outline-none focus:border-[#f3bd35]">
                    <span class="text-[10px] text-zinc-500 mt-1 block">Minimum: ₱500.00</span>
                </div>
                <div>
                    <span class="block text-[11px] font-semibold text-zinc-300 mb-1" id="bo-encash-channel-label">Payout Channel *</span>
                    <input type="hidden" name="payoutMethod" value="gcash">
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2" role="radiogroup" aria-labelledby="bo-encash-channel-label">
                        <?php foreach (MLM_PAYOUT_METHODS as $bdM): $bdOn = $bdM === 'gcash'; ?>
                            <button type="button" role="radio" aria-checked="<?= $bdOn ? 'true' : 'false' ?>" data-bo-channel="<?= e($bdM) ?>" data-on="<?= $bdChannelOn ?>" data-off="<?= $bdChannelOff ?>" class="py-2 px-3 rounded-xl text-xs font-bold uppercase transition-all <?= $bdOn ? $bdChannelOn : $bdChannelOff ?>"><?= e(preg_replace('/_/', ' ', $bdM, 1)) ?></button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div>
                    <label for="bo-encash-details" class="block text-[11px] font-semibold text-zinc-300 mb-1">Account Details / Phone / Bank Account *</label>
                    <input id="bo-encash-details" name="accountDetails" type="text" required maxlength="190" value="<?= e($bdPayoutDetails) ?>" placeholder="e.g. GCash 0917-xxx-xxxx or BDO 00123456789" class="w-full px-3.5 py-2 rounded-xl bg-zinc-900 border border-zinc-700 text-white text-xs focus:outline-none focus:border-[#f3bd35]">
                </div>
                <div>
                    <label for="bo-encash-pin" class="block text-[11px] font-semibold text-zinc-300 mb-1">4-Digit Security / Transaction PIN *</label>
                    <input id="bo-encash-pin" name="pin" type="password" maxlength="6" required inputmode="numeric" autocomplete="off" value="<?= e($bdPin) ?>" placeholder="••••" class="w-full px-3.5 py-2 rounded-xl bg-zinc-900 border border-zinc-700 text-white text-xs font-mono tracking-widest focus:outline-none focus:border-[#f3bd35]">
                </div>
                <div class="p-3.5 rounded-2xl bg-zinc-950 border border-zinc-800 space-y-1.5 text-xs">
                    <div class="flex justify-between text-zinc-400">
                        <span>Gross Amount:</span>
                        <span class="font-mono text-zinc-200" data-bo-gross><?= peso($bdAmount) ?></span>
                    </div>
                    <div class="flex justify-between text-zinc-400">
                        <span>Withholding Tax (10%):</span>
                        <span class="font-mono text-rose-400" data-bo-tax>-<?= peso($bdTax) ?></span>
                    </div>
                    <div class="flex justify-between text-zinc-400">
                        <span>Processing Fee:</span>
                        <span class="font-mono text-rose-400">-₱50</span>
                    </div>
                    <div class="pt-2 border-t border-zinc-800 flex justify-between font-bold text-sm text-white">
                        <span>Net Amount to Receive:</span>
                        <span class="font-mono text-emerald-400" data-bo-net><?= peso($bdNet) ?> PHP</span>
                    </div>
                </div>
                <div class="pt-2">
                    <button type="submit" data-bo-encash-submit<?= ($bdAmount <= 0 || $bdAmount > $bdBalance) ? ' disabled' : '' ?> class="w-full py-3 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:brightness-110 text-white font-bold text-xs shadow-lg transition-transform active:scale-95 disabled:opacity-50">Confirm &amp; Request <?= peso($bdNet) ?> Payout</button>
                </div>
            </form>
        </div>
    </div>
    <div class="p-4 sm:p-5 rounded-3xl bg-gradient-to-r from-[#1c160c] via-[#14120e] to-[#0d0d0d] border border-[#f3bd35]/40 shadow-xl relative overflow-hidden">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 relative z-10">
            <div>
                <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-[#f3bd35]/15 border border-[#f3bd35]/30 text-[#ffda72] text-[10px] font-extrabold uppercase tracking-widest mb-1.5">
                    <?= icon('zap', 'w-3.5 h-3.5 text-[#f3bd35]') ?>
                    Official 88MMM Rewards Plan Rules
                </div>
                <h2 class="text-xl sm:text-2xl font-black text-white flex items-center gap-2">
                    Two-Team Rewards Engine
                    <span class="text-xs px-2 py-0.5 rounded-full bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 font-semibold">Active &amp; Matching</span>
                </h2>
                <p class="text-xs text-zinc-300 mt-1 max-w-2xl leading-relaxed">
                    Entry: <strong class="text-[#ffda72]">₱<?= num($complan['entryFee']) ?> PHP</strong> · Referral Bonus: <strong class="text-emerald-400">₱<?= num($complan['directReferralBonus']) ?> PHP</strong> · Match Bonus: <strong class="text-[#f3bd35]">₱<?= num($complan['pairingBonus']) ?> PHP</strong> · Round Cap: <strong class="text-white"><?= $complan['maxPairsPerCycle'] ?> matches/round (<?= $complan['cyclesPerDay'] ?> round<?= $complan['cyclesPerDay'] === 1 ? '' : 's' ?>/day = ₱<?= num($complan['maxDailyPairingIncome']) ?>/day max)</strong>.
                </p>
            </div>
            <div class="flex items-center gap-2.5 flex-wrap">
                <button type="button" data-open-modal="bo-encash" class="px-3.5 py-2 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:brightness-110 text-white font-extrabold text-xs shadow-lg active:scale-95 transition-all flex items-center gap-1.5">
                    <?= icon('wallet', 'w-3.5 h-3.5') ?>
                    <span>Request Withdrawal</span>
                </button>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="p-5 rounded-3xl bg-[#121212] border border-[#f3bd35]/40 shadow-xl relative overflow-hidden group">
            <div class="absolute top-0 right-0 w-24 h-24 bg-[#f3bd35]/5 rounded-bl-full pointer-events-none"></div>
            <div class="flex items-center justify-between text-zinc-400 text-xs font-semibold mb-2">
                <span>Available Balance</span>
                <div class="w-8 h-8 rounded-full bg-[#f3bd35]/15 text-[#f3bd35] flex items-center justify-center"><?= icon('wallet', 'w-4 h-4') ?></div>
            </div>
            <div class="text-3xl font-black text-white font-mono tabular-nums tracking-tight"><?= peso($account['availableBalance']) ?></div>
            <div class="mt-3 flex items-center justify-between text-[11px]">
                <span class="text-emerald-400 font-semibold flex items-center gap-1"><?= icon('shield-check', 'w-3.5 h-3.5') ?> Ready for Withdrawal</span>
                <button type="button" data-open-modal="bo-encash" class="text-[#ffda72] hover:underline font-bold">Withdraw →</button>
            </div>
        </div>
        <div class="p-5 rounded-3xl bg-[#121212] border border-zinc-800 shadow-xl">
            <div class="flex items-center justify-between text-zinc-400 text-xs font-semibold mb-2">
                <span>Total Earnings</span>
                <div class="w-8 h-8 rounded-full bg-emerald-500/15 text-emerald-400 flex items-center justify-center"><?= icon('trending-up', 'w-4 h-4') ?></div>
            </div>
            <div class="text-3xl font-black text-white font-mono tabular-nums tracking-tight"><?= peso($account['totalIncome']) ?></div>
            <div class="mt-3 text-[11px] text-zinc-400">Total Withdrawn: <strong class="text-zinc-200"><?= peso($account['totalEncashment']) ?></strong></div>
        </div>
        <div class="p-5 rounded-3xl bg-[#121212] border border-zinc-800 shadow-xl">
            <div class="flex items-center justify-between text-zinc-400 text-xs font-semibold mb-2">
                <span>Match Bonus</span>
                <div class="w-8 h-8 rounded-full bg-amber-500/15 text-amber-400 flex items-center justify-center"><?= icon('layers', 'w-4 h-4') ?></div>
            </div>
            <div class="text-3xl font-black text-[#ffda72] font-mono tabular-nums tracking-tight"><?= peso($account['totalPairingBonus']) ?></div>
            <div class="mt-3 text-[11px] text-zinc-400">Team matches @ ₱<?= num($bdPair) ?> per match</div>
        </div>
        <div class="p-5 rounded-3xl bg-[#121212] border border-zinc-800 shadow-xl">
            <div class="flex items-center justify-between text-zinc-400 text-xs font-semibold mb-2">
                <span>Referral Bonus</span>
                <div class="w-8 h-8 rounded-full bg-blue-500/15 text-blue-400 flex items-center justify-center"><?= icon('users', 'w-4 h-4') ?></div>
            </div>
            <div class="text-3xl font-black text-white font-mono tabular-nums tracking-tight"><?= peso($account['totalDirectBonus']) ?></div>
            <div class="mt-3 text-[11px] text-zinc-400"><?= e($account['sponsoredCount']) ?> referred members @ ₱<?= num($complan['directReferralBonus']) ?> each</div>
        </div>
    </div>

    <div class="p-5 rounded-3xl bg-[#121212] border border-zinc-800 shadow-xl space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <div>
                <h3 class="text-base font-bold text-white flex items-center gap-2">
                    <?= icon('clock', 'w-4 h-4 text-[#f3bd35]') ?>
                    Daily Match Rounds &amp; Cap Progress
                </h3>
                <p class="text-xs text-zinc-400 mt-0.5">Strict <?= $complan['maxPairsPerCycle'] ?> matches maximum per round (<?= $complan['cyclesPerDay'] ?> round<?= $complan['cyclesPerDay'] === 1 ? '' : 's' ?> / 24h). Maximum daily payout: ₱<?= number_format($complan['maxDailyPairingIncome'], 2) ?> PHP.</p>
            </div>
            <span class="text-[11px] text-zinc-300 font-mono bg-zinc-900 border border-zinc-700 px-3 py-1 rounded-full self-start sm:self-auto">Today's Total: <?= $bdToday ?> / <?= $complan['pairsPerDay'] ?> matches</span>
        </div>
        <div class="grid grid-cols-1 <?= count($bdCycles) > 1 ? 'md:grid-cols-2' : '' ?> gap-4 pt-1">
            <?php foreach ($bdCycles as [$bdN, $bdCount, $bdPct, $bdText, $bdBar]): ?>
                <div class="p-4 rounded-2xl bg-zinc-950 border border-zinc-800 space-y-2">
                    <div class="flex items-center justify-between text-xs">
                        <span class="font-bold text-white"><?= e(mlm_cycle_label($bdN)) ?></span>
                        <span class="<?= $bdText ?> font-mono font-bold"><?= $bdCount ?> / <?= $complan['maxPairsPerCycle'] ?> matches (₱<?= num($bdCount * $bdPair) ?>)</span>
                    </div>
                    <div class="w-full h-3 bg-zinc-800 rounded-full overflow-hidden p-0.5" role="progressbar" aria-label="Round <?= $bdN ?> matches" aria-valuemin="0" aria-valuemax="<?= $complan['maxPairsPerCycle'] ?>" aria-valuenow="<?= $bdCount ?>">
                        <div class="h-full bg-gradient-to-r <?= $bdBar ?> rounded-full transition-all duration-500" style="width: <?= $bdPct ?>%"></div>
                    </div>
                    <div class="flex items-center justify-between text-[10px] text-zinc-400">
                        <span><?= max(0, $complan['maxPairsPerCycle'] - $bdCount) ?> matches remaining</span>
                        <span class="text-emerald-400 font-semibold">Max Cap: ₱<?= num($complan['maxCyclePairingIncome']) ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="p-5 rounded-3xl bg-gradient-to-r from-[#17130a] via-[#100e09] to-[#0c0c0c] border border-[#f3bd35]/40 shadow-xl flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-start sm:items-center gap-3.5">
            <div class="w-12 h-12 rounded-2xl bg-[#f3bd35]/15 border border-[#f3bd35]/30 flex items-center justify-center text-[#f3bd35] shrink-0"><?= icon('radio', 'w-6 h-6 animate-pulse') ?></div>
            <div>
                <div class="flex items-center gap-2 flex-wrap mb-0.5">
                    <span class="text-[10px] font-mono font-bold text-[#ffda72] px-2 py-0.5 rounded-full bg-[#f3bd35]/20 border border-[#f3bd35]/30"><?= e($bdCardId) ?></span>
                    <?php if ($bdFrozen): ?>
                        <span class="text-[10px] text-rose-400 font-bold px-2 py-0.5 rounded-full bg-rose-500/15 border border-rose-500/30">● Frozen (Card Lost)</span>
                    <?php else: ?>
                        <span class="text-[10px] text-emerald-400 font-bold px-2 py-0.5 rounded-full bg-emerald-500/15 border border-emerald-500/30">● Active &amp; Ready to Tap</span>
                    <?php endif; ?>
                    <span class="text-[10px] text-zinc-400 font-mono"><?= num($bdTaps) ?> Physical Taps Recorded</span>
                </div>
                <h4 class="text-sm font-bold text-white flex items-center gap-1.5">Assigned Physical Smart NFC Card</h4>
                <p class="text-xs text-zinc-400 mt-0.5">Tap destination: <strong class="text-zinc-200"><?= e($bdTapDest) ?></strong></p>
            </div>
        </div>
        <div class="flex items-center gap-2 shrink-0">
            <button type="button" data-bo-manage-nfc class="px-4 py-2.5 rounded-xl bg-gradient-to-r from-[#ffda72] to-[#f3bd35] text-black font-extrabold text-xs shadow hover:brightness-105 active:scale-95 transition-all flex items-center gap-1.5">
                <?= icon('credit-card', 'w-3.5 h-3.5 text-black') ?>
                <span>Manage NFC Card →</span>
            </button>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="p-5 rounded-3xl bg-[#121212] border border-blue-500/20 shadow-xl">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs uppercase font-bold text-blue-400 tracking-wider">Left Team Volume</span>
                <span class="text-xs font-mono bg-blue-500/10 text-blue-300 px-2 py-0.5 rounded-full border border-blue-500/30"><?= e($account['leftCount']) ?> Members</span>
            </div>
            <div class="text-3xl font-black text-white font-mono tabular-nums"><?= e($account['leftPoints']) ?> <span class="text-sm font-normal text-zinc-400">Points</span></div>
            <p class="text-xs text-zinc-400 mt-2">Stronger team accumulated point volume awaiting matching on right team.</p>
        </div>
        <div class="p-5 rounded-3xl bg-[#121212] border border-amber-500/20 shadow-xl">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs uppercase font-bold text-amber-400 tracking-wider">Right Team Volume</span>
                <span class="text-xs font-mono bg-amber-500/10 text-amber-300 px-2 py-0.5 rounded-full border border-amber-500/30"><?= e($account['rightCount']) ?> Members</span>
            </div>
            <div class="text-3xl font-black text-white font-mono tabular-nums"><?= e($account['rightPoints']) ?> <span class="text-sm font-normal text-zinc-400">Points</span></div>
            <p class="text-xs text-zinc-400 mt-2">Matching team point volume. New member placement here triggers instant ₱<?= num($bdPair) ?> match!</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 p-5 rounded-3xl bg-[#121212] border border-zinc-800 shadow-xl space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="text-base font-bold text-white flex items-center gap-2">
                    <?= icon('trending-up', 'w-4 h-4 text-[#f3bd35]') ?>
                    Recent Rewards &amp; Transactions
                </h3>
                <button type="button" data-bo-view-ledger class="text-xs text-[#f3bd35] hover:underline font-semibold flex items-center gap-1">
                    <span>View Full Ledger</span>
                    <?= icon('arrow-right', 'w-3.5 h-3.5') ?>
                </button>
            </div>
            <div class="space-y-2.5">
                <?php foreach ($bdRecent as $bdTx): $bdIn = $bdTx['direction'] === 'in'; ?>
                    <div class="p-3.5 rounded-2xl bg-zinc-950 border border-zinc-800/80 hover:border-zinc-700 transition-colors flex items-center justify-between gap-3 text-xs">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-xl flex items-center justify-center font-bold <?= $bdIn ? 'bg-emerald-500/15 text-emerald-400 border border-emerald-500/30' : 'bg-rose-500/15 text-rose-400 border border-rose-500/30' ?>"><?= $bdIn ? '+' : '-' ?></div>
                            <div>
                                <div class="font-bold text-white"><?= e($bdTx['title']) ?></div>
                                <div class="text-[10px] text-zinc-400 font-mono">Ref: <?= e($bdTx['referenceNumber']) ?> · <?= e(fmt_datetime($bdTx['timestamp'])) ?></div>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="font-black font-mono text-sm <?= $bdIn ? 'text-emerald-400' : 'text-zinc-200' ?>"><?= $bdIn ? '+' : '-' ?><?= peso($bdTx['amount']) ?></div>
                            <span class="text-[9px] px-2 py-0.2 rounded-full uppercase font-bold <?= $bdTx['status'] === 'completed' ? 'bg-emerald-500/10 text-emerald-400' : ($bdTx['status'] === 'rejected' ? 'bg-rose-500/10 text-rose-300' : 'bg-amber-500/10 text-amber-400') ?>"><?= e($bdTx['status']) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="space-y-6">
            <div class="p-5 rounded-3xl bg-[#121212] border border-zinc-800 shadow-xl space-y-3">
                <div class="flex items-center justify-between">
                    <h4 class="text-xs font-bold text-zinc-300 uppercase tracking-wider flex items-center gap-1.5"><?= icon('bell', 'w-3.5 h-3.5 text-[#f3bd35]') ?> Notifications</h4>
                    <span class="text-[10px] bg-[#f3bd35]/20 text-[#ffda72] px-2 py-0.5 rounded-full font-bold"><span data-bo-unread><?= $bdUnread ?></span> New</span>
                </div>
                <div class="space-y-2 max-h-56 overflow-y-auto pr-1">
                    <?php foreach ($notifications as $bdN): ?>
                        <div role="button" tabindex="0" data-bo-notif="<?= e($bdN['id']) ?>" data-read="<?= $bdN['read'] ? '1' : '0' ?>" class="p-2.5 rounded-xl text-xs transition-colors cursor-pointer border <?= $bdN['read'] ? 'bg-zinc-950/60 border-zinc-800/60 text-zinc-400' : 'bg-zinc-900 border-[#f3bd35]/30 text-zinc-200' ?>">
                            <div class="font-semibold text-white text-[11px] flex items-center justify-between">
                                <span><?= e($bdN['title']) ?></span>
                                <?php if (!$bdN['read']): ?><span data-bo-dot class="w-1.5 h-1.5 rounded-full bg-[#f3bd35]"></span><?php endif; ?>
                            </div>
                            <p class="text-[10px] text-zinc-400 mt-0.5 leading-relaxed"><?= e($bdN['message']) ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="p-5 rounded-3xl bg-[#121212] border border-zinc-800 shadow-xl space-y-3">
                <h4 class="text-xs font-bold text-zinc-300 uppercase tracking-wider flex items-center gap-1.5"><?= icon('shield-check', 'w-3.5 h-3.5 text-[#f3bd35]') ?> Security &amp; Audit Logs</h4>
                <div class="space-y-2 text-[11px] text-zinc-400">
                    <?php foreach ($bdLogs as $bdLog): ?>
                        <div class="p-2 rounded-xl bg-zinc-950 border border-zinc-800/80">
                            <div class="text-zinc-200 font-medium"><?= e($bdLog['action']) ?></div>
                            <div class="text-[9px] text-zinc-500 mt-0.5"><?= e($bdLog['ipAddress']) ?> · <?= e(fmt_time($bdLog['timestamp'])) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

</div>
