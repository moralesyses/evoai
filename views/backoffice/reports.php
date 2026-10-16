<?php
/**
 * Backoffice tab 5 — Reports (reference component "kn").
 *
 * Same markup as the reference (stacked div bar charts). Data rules:
 *  - Income chart: daily (Last 7 / 30 Days) or monthly (This Year) sums of the
 *    active head's real `direct_referral` / `binary_pairing` inflow transactions;
 *    the type filter (All / Pairing / Direct) restricts the series.
 *  - Network chart: binary additions in the active head's subtree, by entry date
 *    and root leg (left / right), same buckets.
 *  - When a period has no real data at all, the reference's static demo series
 *    (and its static card texts) are shown instead.
 *  - "Lifetime Pairing Total" and "Team Ratio Balance" come from the account
 *    (the reference derives them from the same fields); "Pairing Utilization"
 *    stays static (no source data).
 * All period × filter datasets are computed here; bo-reports.js only re-renders.
 */
(function (array $account, array $complan, array $transactions, ?array $genealogy): void {
    $today = new DateTimeImmutable('today');

    // Daily commission income of the active head: 'Y-m-d' => ['direct' => x, 'pairing' => y].
    $daily = [];
    foreach ($transactions as $tx) {
        $kind = ['direct_referral' => 'direct', 'binary_pairing' => 'pairing'][$tx['type']] ?? null;
        if ($tx['direction'] !== 'in' || $kind === null) {
            continue;
        }
        $day = date('Y-m-d', to_ts($tx['timestamp']));
        $daily[$day][$kind] = ($daily[$day][$kind] ?? 0) + (float) $tx['amount'];
    }

    // Binary additions below the active head: [entryDate, root leg].
    $joins = [];
    $walk = function (?array $n, string $leg) use (&$walk, &$joins): void {
        if (!$n) {
            return;
        }
        $joins[] = [(string) $n['entryDate'], $leg];
        $walk($n['leftChild'] ?? null, $leg);
        $walk($n['rightChild'] ?? null, $leg);
    };
    if ($genealogy) {
        $walk($genealogy['leftChild'] ?? null, 'left');
        $walk($genealogy['rightChild'] ?? null, 'right');
    }

    // Periods: buckets (label + date prefix) and the list of days used for the day cards.
    $dayBuckets = function (int $count, bool $weekday) use ($today): array {
        $buckets = [];
        $days = [];
        for ($i = $count - 1; $i >= 0; $i--) {
            $d = $today->modify("-{$i} days");
            $key = $d->format('Y-m-d');
            $buckets[] = ['label' => $d->format($weekday ? 'D' : 'n/j') . ($i === 0 ? ' (Today)' : ''), 'match' => $key];
            $days[] = $key;
        }
        return ['buckets' => $buckets, 'days' => $days, 'dayName' => $weekday ? 'l' : 'M j'];
    };
    $yearDays = [];
    for ($d = $today->setDate((int) $today->format('Y'), 1, 1); $d <= $today; $d = $d->modify('+1 day')) {
        $yearDays[] = $d->format('Y-m-d');
    }
    $yearBuckets = [];
    for ($m = 1; $m <= (int) $today->format('n'); $m++) {
        $md = $today->setDate((int) $today->format('Y'), $m, 1);
        $yearBuckets[] = ['label' => $md->format('M'), 'match' => $md->format('Y-m')];
    }
    $periods = [
        '7days' => $dayBuckets(7, true),
        '30days' => $dayBuckets(30, false),
        'year' => ['buckets' => $yearBuckets, 'days' => $yearDays, 'dayName' => 'M j'],
    ];

    // Reference demo series (component "kn").
    $demoIncome = [
        ['day' => 'Mon', 'direct' => 400, 'pairing' => 1500], ['day' => 'Tue', 'direct' => 600, 'pairing' => 2100],
        ['day' => 'Wed', 'direct' => 200, 'pairing' => 1800], ['day' => 'Thu', 'direct' => 800, 'pairing' => 2400],
        ['day' => 'Fri', 'direct' => 400, 'pairing' => 2100], ['day' => 'Sat', 'direct' => 1000, 'pairing' => 2400],
        ['day' => 'Sun (Today)', 'direct' => 600, 'pairing' => 2400],
    ];
    $demoNetwork = [
        ['day' => 'Mon', 'left' => 4, 'right' => 3], ['day' => 'Tue', 'left' => 6, 'right' => 5],
        ['day' => 'Wed', 'left' => 3, 'right' => 4], ['day' => 'Thu', 'left' => 8, 'right' => 7],
        ['day' => 'Fri', 'left' => 5, 'right' => 6], ['day' => 'Sat', 'left' => 10, 'right' => 8],
        ['day' => 'Sun (Today)', 'left' => 7, 'right' => 6],
    ];

    /**
     * Bar geometry exactly like the reference: n = bar height (% of max total),
     * r = top segment share, i = 100 - r. $rows: [label, top, bottom].
     */
    $bars = function (array $rows, callable $tip, callable $titleTop, callable $titleBottom): array {
        $max = 0;
        foreach ($rows as $row) {
            $max = max($max, $row[1] + $row[2]);
        }
        $out = [];
        foreach ($rows as [$label, $top, $bottom]) {
            $total = $top + $bottom;
            $r = $total > 0 ? (int) round($top / $total * 100) : 0;
            $out[] = [
                'label' => explode(' ', $label)[0], 'tip' => $tip($total),
                'n' => $max > 0 ? (int) round($total / $max * 100) : 0, 'r' => $r, 'i' => $total > 0 ? 100 - $r : 0,
                'titleTop' => $titleTop($top), 'titleBottom' => $titleBottom($bottom),
            ];
        }
        return $out;
    };
    $incomeBars = fn (array $rows) => $bars($rows, fn ($t) => peso($t), fn ($v) => 'Match: ' . peso($v), fn ($v) => 'Referral: ' . peso($v));
    $networkBars = fn (array $rows) => $bars($rows, fn ($t) => '+' . $t . ' Members', fn ($v) => 'Left: +' . $v, fn ($v) => 'Right: +' . $v);

    $demoIncomeSet = [
        'demo' => true,
        'bars' => $incomeBars(array_map(fn ($d) => [$d['day'], $d['pairing'], $d['direct']], $demoIncome)),
        'high' => 'Saturday (₱3,400)', 'highSub' => '8 Matches made (Max round safety cap hit)', 'avg' => '₱2,670 / day',
    ];
    $demoNetworkSet = [
        'demo' => true,
        'bars' => $networkBars(array_map(fn ($d) => [$d['day'], $d['left'], $d['right']], $demoNetwork)),
        'growth' => '+88 New Members', 'growthSub' => '43 Left · 45 Right',
    ];

    $data = ['income' => [], 'network' => []];
    foreach ($periods as $pid => $p) {
        // Income: raw sums per bucket.
        $raw = [];
        $any = 0.0;
        foreach ($p['buckets'] as $b) {
            $direct = 0.0;
            $pairing = 0.0;
            foreach ($daily as $day => $v) {
                if (str_starts_with($day, $b['match'])) {
                    $direct += $v['direct'] ?? 0;
                    $pairing += $v['pairing'] ?? 0;
                }
            }
            $raw[] = [$b['label'], $pairing, $direct];
            $any += $direct + $pairing;
        }
        foreach (['all', 'pairing', 'direct'] as $filter) {
            if ($any <= 0) {
                $data['income'][$pid][$filter] = $demoIncomeSet;
                continue;
            }
            $keepP = $filter !== 'direct';
            $keepD = $filter !== 'pairing';
            $rows = array_map(fn ($r) => [$r[0], $keepP ? $r[1] : 0, $keepD ? $r[2] : 0], $raw);
            // Day cards over every day of the period.
            $best = null;
            $sum = 0.0;
            foreach ($p['days'] as $day) {
                $pv = $keepP ? ($daily[$day]['pairing'] ?? 0) : 0;
                $dv = $keepD ? ($daily[$day]['direct'] ?? 0) : 0;
                $sum += $pv + $dv;
                if ($pv + $dv > 0 && ($best === null || $pv + $dv > $best['total'])) {
                    $best = ['day' => $day, 'total' => $pv + $dv, 'pairing' => $pv];
                }
            }
            if ($best) {
                $pairs = (int) floor($best['pairing'] / $complan['pairingBonus']);
                $high = date($p['dayName'], strtotime($best['day'])) . ' (' . peso($best['total']) . ')';
                $highSub = $pairs . ' Matches made' . ($pairs >= $complan['maxPairsPerCycle'] ? ' (Max round safety cap hit)' : '');
            } else {
                $high = '— (₱0)';
                $highSub = '0 Matches made';
            }
            $data['income'][$pid][$filter] = [
                'demo' => false, 'bars' => $incomeBars($rows), 'high' => $high, 'highSub' => $highSub,
                'avg' => peso(round($sum / max(1, count($p['days'])))) . ' / day',
            ];
        }

        // Network growth: additions per bucket and leg.
        $rows = [];
        $left = 0;
        $right = 0;
        foreach ($p['buckets'] as $b) {
            $l = 0;
            $r = 0;
            foreach ($joins as [$day, $leg]) {
                if (str_starts_with($day, $b['match'])) {
                    $leg === 'left' ? $l++ : $r++;
                }
            }
            $rows[] = [$b['label'], $l, $r];
            $left += $l;
            $right += $r;
        }
        $data['network'][$pid] = ($left + $right) > 0 ? [
            'demo' => false, 'bars' => $networkBars($rows),
            'growth' => '+' . ($left + $right) . ' New Members', 'growthSub' => $left . ' Left · ' . $right . ' Right',
        ] : $demoNetworkSet;
    }

    $ratio = $account['leftCount'] > 0 ? $account['rightCount'] / $account['leftCount'] : 0;
    $income = $data['income']['7days']['all'];
    $network = $data['network']['7days'];

    $report = (string) ($_GET['report'] ?? 'income');
    if (!in_array($report, ['income', 'network'], true)) {
        $report = 'income';
    }
    $typeOn = 'bg-gradient-to-r from-[#ffda72] to-[#f3bd35] text-black shadow-md';
    $typeOff = 'bg-zinc-900 text-zinc-400 hover:text-white border border-zinc-800';
    $periodOn = 'bg-[#f3bd35] text-black shadow';
    $periodOff = 'text-zinc-400 hover:text-white';
    $filterOn = 'bg-zinc-800 text-[#ffda72] border border-[#f3bd35]/40';
    $filterOff = 'text-zinc-400 hover:text-white';

    $renderBars = function (array $bars, string $topCls, string $bottomCls): void {
        foreach ($bars as $b): ?>
            <div class="flex-1 flex flex-col items-center h-full justify-end group">
                <div class="text-[10px] text-zinc-400 font-mono opacity-0 group-hover:opacity-100 transition-opacity mb-1 font-bold"><?= e($b['tip']) ?></div>
                <div class="w-full max-w-[48px] rounded-t-xl overflow-hidden flex flex-col transition-all duration-300 group-hover:brightness-110" style="height: <?= (int) $b['n'] ?>%">
                    <div class="<?= $topCls ?>" style="height: <?= (int) $b['r'] ?>%" title="<?= e($b['titleTop']) ?>"></div>
                    <div class="<?= $bottomCls ?>" style="height: <?= (int) $b['i'] ?>%" title="<?= e($b['titleBottom']) ?>"></div>
                </div>
                <div class="text-[10px] text-zinc-400 font-semibold mt-2 truncate max-w-[50px] text-center"><?= e($b['label']) ?></div>
            </div>
        <?php endforeach;
    };
    $incomeTop = 'w-full bg-gradient-to-t from-[#c59114] to-[#f3bd35]';
    $incomeBottom = 'w-full bg-gradient-to-t from-emerald-600 to-emerald-400';
    $networkTop = 'w-full bg-gradient-to-t from-blue-700 to-blue-500';
    $networkBottom = 'w-full bg-gradient-to-t from-amber-700 to-amber-500';
    ?>
<div class="space-y-6" data-bo-reports>
    <div class="p-5 rounded-3xl bg-[#121212] border border-zinc-800 shadow-xl flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-center gap-2" data-tab-group="rep" data-param="report" role="tablist">
            <button type="button" data-tab="income" data-group="rep" data-on="<?= $typeOn ?>" data-off="<?= $typeOff ?>" aria-selected="<?= $report === 'income' ? 'true' : 'false' ?>" class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold transition-all <?= $report === 'income' ? $typeOn : $typeOff ?>">
                <?= icon('dollar-sign', 'w-4 h-4') ?>
                <span>Income &amp; Rewards Plan Report</span>
            </button>
            <button type="button" data-tab="network" data-group="rep" data-on="<?= $typeOn ?>" data-off="<?= $typeOff ?>" aria-selected="<?= $report === 'network' ? 'true' : 'false' ?>" class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold transition-all <?= $report === 'network' ? $typeOn : $typeOff ?>">
                <?= icon('users', 'w-4 h-4') ?>
                <span>Team Growth Report</span>
            </button>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            <div class="flex bg-zinc-900 rounded-xl p-1 border border-zinc-800 text-xs">
                <?php foreach (['7days' => 'Last 7 Days', '30days' => 'Last 30 Days', 'year' => 'This Year'] as $pid => $plabel): ?>
                    <button type="button" data-rep-period="<?= $pid ?>" data-on="<?= $periodOn ?>" data-off="<?= $periodOff ?>" aria-pressed="<?= $pid === '7days' ? 'true' : 'false' ?>" class="px-3 py-1 rounded-lg uppercase text-[10px] font-bold <?= $pid === '7days' ? $periodOn : $periodOff ?>"><?= $plabel ?></button>
                <?php endforeach; ?>
            </div>
            <div class="flex bg-zinc-900 rounded-xl p-1 border border-zinc-800 text-xs" data-rep-filters<?= $report === 'income' ? '' : ' hidden' ?>>
                <?php foreach (['all' => 'All', 'pairing' => 'Match (₱' . num($complan['pairingBonus']) . ')', 'direct' => 'Referral (₱' . num($complan['directReferralBonus']) . ')'] as $fid => $flabel): ?>
                    <button type="button" data-rep-filter="<?= $fid ?>" data-on="<?= $filterOn ?>" data-off="<?= $filterOff ?>" aria-pressed="<?= $fid === 'all' ? 'true' : 'false' ?>" class="px-2.5 py-1 rounded-lg uppercase text-[10px] font-bold <?= $fid === 'all' ? $filterOn : $filterOff ?>"><?= $flabel ?></button>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div>
        <div class="space-y-6" data-tab-panel="income" data-group="rep"<?= $report === 'income' ? '' : ' hidden' ?>>
            <div class="p-6 rounded-3xl bg-[#121212] border border-zinc-800 shadow-xl space-y-6">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                    <div>
                        <h3 class="text-base font-bold text-white flex items-center gap-2">
                            <?= icon('chart-column', 'w-5 h-5 text-[#f3bd35]') ?>
                            Rewards &amp; Earnings Trend Graph
                        </h3>
                        <p class="text-xs text-zinc-400 mt-0.5">Daily visual performance breakdown of Team Matches vs Direct Referrals.</p>
                    </div>
                    <div class="flex items-center gap-4 text-xs font-semibold">
                        <div class="flex items-center gap-1.5">
                            <span class="w-3 h-3 rounded-full bg-[#f3bd35]"></span>
                            <span class="text-zinc-300">Team Matches (₱<?= num($complan['pairingBonus']) ?>)</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <span class="w-3 h-3 rounded-full bg-emerald-400"></span>
                            <span class="text-zinc-300">Direct Referrals (₱<?= num($complan['directReferralBonus']) ?>)</span>
                        </div>
                    </div>
                </div>
                <div class="h-64 w-full flex items-end justify-between gap-2 sm:gap-4 pt-6 pb-2 border-b border-zinc-800" data-rep-bars="income" data-top="<?= $incomeTop ?>" data-bottom="<?= $incomeBottom ?>">
                    <?php $renderBars($income['bars'], $incomeTop, $incomeBottom); ?>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-2">
                    <div class="p-4 rounded-2xl bg-zinc-950 border border-zinc-800">
                        <span class="text-[11px] text-zinc-400 font-medium">Highest Earning Day</span>
                        <div class="text-xl font-bold text-white font-mono mt-1" data-rep="high"><?= e($income['high']) ?></div>
                        <span class="text-[10px] text-emerald-400" data-rep="highSub"><?= e($income['highSub']) ?></span>
                    </div>
                    <div class="p-4 rounded-2xl bg-zinc-950 border border-zinc-800">
                        <span class="text-[11px] text-zinc-400 font-medium">Average Daily Payout</span>
                        <div class="text-xl font-bold text-[#ffda72] font-mono mt-1" data-rep="avg"><?= e($income['avg']) ?></div>
                        <span class="text-[10px] text-zinc-400">Consistent multi-round matching</span>
                    </div>
                    <div class="p-4 rounded-2xl bg-zinc-950 border border-zinc-800">
                        <span class="text-[11px] text-zinc-400 font-medium">Lifetime Match Total</span>
                        <div class="text-xl font-bold text-white font-mono mt-1"><?= peso($account['totalPairingBonus']) ?> PHP</div>
                        <span class="text-[10px] text-zinc-400"><?= e(round($account['totalPairingBonus'] / $complan['pairingBonus'])) ?> Total team matches made</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="space-y-6" data-tab-panel="network" data-group="rep"<?= $report === 'network' ? '' : ' hidden' ?>>
            <div class="p-6 rounded-3xl bg-[#121212] border border-zinc-800 shadow-xl space-y-6">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                    <div>
                        <h3 class="text-base font-bold text-white flex items-center gap-2">
                            <?= icon('users', 'w-5 h-5 text-blue-400') ?>
                            Team Expansion &amp; Balance Graph
                        </h3>
                        <p class="text-xs text-zinc-400 mt-0.5">Comparison of new member additions on Left Team vs Right Team.</p>
                    </div>
                    <div class="flex items-center gap-4 text-xs font-semibold">
                        <div class="flex items-center gap-1.5">
                            <span class="w-3 h-3 rounded-full bg-blue-500"></span>
                            <span class="text-zinc-300">Left Team Additions</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <span class="w-3 h-3 rounded-full bg-amber-500"></span>
                            <span class="text-zinc-300">Right Team Additions</span>
                        </div>
                    </div>
                </div>
                <div class="h-64 w-full flex items-end justify-between gap-2 sm:gap-4 pt-6 pb-2 border-b border-zinc-800" data-rep-bars="network" data-top="<?= $networkTop ?>" data-bottom="<?= $networkBottom ?>">
                    <?php $renderBars($network['bars'], $networkTop, $networkBottom); ?>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-2">
                    <div class="p-4 rounded-2xl bg-zinc-950 border border-zinc-800">
                        <span class="text-[11px] text-zinc-400 font-medium">Team Ratio Balance</span>
                        <div class="text-xl font-bold text-emerald-400 font-mono mt-1">1 : <?= number_format($ratio, 2) ?> (Optimal)</div>
                        <span class="text-[10px] text-zinc-400">High match conversion efficiency</span>
                    </div>
                    <div class="p-4 rounded-2xl bg-zinc-950 border border-zinc-800">
                        <span class="text-[11px] text-zinc-400 font-medium">Weekly Growth Count</span>
                        <div class="text-xl font-bold text-white font-mono mt-1" data-rep="growth"><?= e($network['growth']) ?></div>
                        <span class="text-[10px] text-blue-400" data-rep="growthSub"><?= e($network['growthSub']) ?></span>
                    </div>
                    <div class="p-4 rounded-2xl bg-zinc-950 border border-zinc-800">
                        <span class="text-[11px] text-zinc-400 font-medium">Match Utilization</span>
                        <div class="text-xl font-bold text-[#ffda72] font-mono mt-1">94.2% Matched</div>
                        <span class="text-[10px] text-zinc-400">Little lost to the 8/round cap</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script type="application/json" id="bo-rep-data"><?= js_json($data) ?></script>
<?php
})($account, $complan, $transactions, $genealogy);
