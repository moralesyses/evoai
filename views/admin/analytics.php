<?php
/**
 * Admin tab "System Analytics" (reference component "Ln").
 * KPI cards, 7-day NFC tap / view velocity chart and the latest leads.
 */
$anaKpis = [
    ['label' => 'Profile Impressions', 'value' => $analytics['profileViews'] ?? 0, 'change' => '+18.4%', 'icon' => 'users'],
    ['label' => 'NFC Card Taps', 'value' => $analytics['nfcTaps'] ?? 0, 'change' => '+24.1%', 'icon' => 'smartphone'],
    ['label' => 'vCard Downloads', 'value' => $analytics['vcardDownloads'] ?? 0, 'change' => '+15.2%', 'icon' => 'download'],
    ['label' => 'E2EE Messages Exchanged', 'value' => $analytics['messagesExchanged'] ?? 0, 'change' => '+31.8%', 'icon' => 'shield-check'],
];
$anaHistory = $analytics['history'] ?? [];
// Chart scale: the highest daily view count, but never below 3,000.
$anaMax = max(3000, ...array_map(fn ($d) => (int) $d['views'], $anaHistory ?: [['views' => 0]]));
$anaPct = fn ($v): int => (int) floor(((int) $v / $anaMax) * 100 + 0.5); // JS Math.round
?>
<div class="space-y-6">
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
        <?php foreach ($anaKpis as $kpi): ?>
            <div class="p-4 rounded-3xl bg-[#111111] border border-[#f3bd35]/20 shadow-md flex flex-col justify-between">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-[11px] font-medium text-zinc-400"><?= e($kpi['label']) ?></span>
                    <div class="w-8 h-8 rounded-full bg-[#f3bd35]/15 flex items-center justify-center text-[#f3bd35]">
                        <?= icon($kpi['icon'], 'w-4 h-4') ?>
                    </div>
                </div>
                <div>
                    <div class="text-xl sm:text-2xl font-black text-white tabular-nums tracking-tight"><?= e(num($kpi['value'])) ?></div>
                    <div class="flex items-center gap-1 text-[10px] text-emerald-400 font-semibold mt-1">
                        <?= icon('trending-up', 'w-3 h-3') ?>
                        <span><?= e($kpi['change']) ?> vs last month</span>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="p-5 rounded-3xl bg-[#111111] border border-[#f3bd35]/20 shadow-xl">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="text-sm font-bold text-white">NFC Tap &amp; Digital Card Velocity</h3>
                <p class="text-xs text-zinc-400">7-Day Engagement Trend</p>
            </div>
            <div class="flex items-center gap-3 text-xs">
                <span class="flex items-center gap-1 text-[#f3bd35]">
                    <span class="w-2.5 h-2.5 rounded-full bg-[#f3bd35]"></span>
                    Views
                </span>
                <span class="flex items-center gap-1 text-emerald-400">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-400"></span>
                    NFC Taps
                </span>
            </div>
        </div>
        <div class="h-44 w-full flex items-end justify-between gap-2 pt-6 pb-2 px-2">
            <?php foreach ($anaHistory as $day): ?>
                <div class="flex-1 flex flex-col items-center h-full justify-end group">
                    <div class="w-full flex items-end justify-center gap-1 h-32 relative">
                        <div class="opacity-0 group-hover:opacity-100 absolute -top-8 bg-black text-[#ffda72] text-[10px] px-2 py-0.5 rounded border border-[#f3bd35]/40 pointer-events-none whitespace-nowrap transition-opacity z-20"><?= e($day['views']) ?> views · <?= e($day['taps']) ?> taps</div>
                        <div style="height: <?= $anaPct($day['views']) ?>%" class="w-3 sm:w-5 bg-gradient-to-t from-[#8f6510] to-[#f3bd35] rounded-t-md transition-all group-hover:brightness-125"></div>
                        <div style="height: <?= $anaPct($day['taps']) ?>%" class="w-2 sm:w-3 bg-gradient-to-t from-emerald-700 to-emerald-400 rounded-t-md transition-all group-hover:brightness-125"></div>
                    </div>
                    <span class="text-[11px] font-semibold text-zinc-400 mt-2"><?= e($day['date']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="p-5 rounded-3xl bg-[#111111] border border-[#f3bd35]/20 shadow-xl">
        <div class="flex items-center justify-between mb-3">
            <div>
                <h3 class="text-sm font-bold text-white">Recent Circle Inquiries</h3>
                <p class="text-xs text-zinc-400">Leads captured from NFC card taps</p>
            </div>
            <span class="text-xs px-2.5 py-1 rounded-full bg-[#f3bd35]/15 text-[#f3bd35] font-semibold"><?= count($leads) ?> Active Leads</span>
        </div>
        <div class="space-y-2">
            <?php foreach (array_slice($leads, 0, 3) as $lead): ?>
                <div class="p-3 rounded-2xl bg-zinc-900/80 border border-zinc-800 flex items-center justify-between hover:border-[#f3bd35]/30 transition-colors">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-bold text-white"><?= e($lead['name']) ?></span>
                            <span class="text-[10px] text-zinc-400 font-mono">· <?= e($lead['company']) ?></span>
                        </div>
                        <p class="text-[11px] text-[#ffda72] mt-0.5">Tier: <?= e($lead['interest']) ?></p>
                    </div>
                    <span class="text-[10px] px-2 py-0.5 rounded-full uppercase font-bold tracking-wider bg-zinc-800 text-zinc-300"><?= e($lead['status']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
