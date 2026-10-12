<?php
/**
 * Backoffice tab 3 — Network (reference component "Dn").
 *
 * Sub-views ([data-tab-group="net"], URL param "net"):
 *   summary   — binary counters of the active head
 *   genealogy — binary tree: root, legs and leg children like the reference;
 *               clicking a member opens their two downlines below it (deeper
 *               levels load on demand, mlm.genealogyChildren) and clicking
 *               again folds them; the eye icon shows the member's details;
 *               "Register Downline" and free slots open the registration modal;
 *               "Sign-up Placement" saves where sign-ups with the head's ID go
 *               (mlm.setSignupPlacement), also the modal's starting choice
 *   list     — flattened binary tree with search + leg filter (bo-network.js)
 *   unilevel  — sponsorship generations 1 / 2 with client-side expand toggles
 * Modals: member details (bo-net-node) and downline registration (bo-net-register,
 * API mlm.registerDownline): the member picks Auto (the leg with fewer
 * downlines), Left or Right under the head or the clicked member.
 *
 * Everything runs inside a closure so no variable leaks into the other tab partials.
 */
(function (array $account, array $complan, ?array $genealogy, ?array $unilevel, array $codes): void {
    // Binary tree root = active head (fallback for heads without a genealogy row).
    $root = $genealogy ?? [
        'id' => $account['id'], 'username' => $account['username'], 'fullName' => $account['accountName'],
        'package' => (string) ($account['packageType'] ?? ''), 'position' => null, 'entryDate' => $account['entryDate'],
        'status' => $account['status'], 'leftPoints' => $account['leftPoints'], 'rightPoints' => $account['rightPoints'],
        'leftCount' => $account['leftCount'], 'rightCount' => $account['rightCount'],
        'leftChild' => null, 'rightChild' => null,
    ];

    // Unilevel root (heads that are not in the sponsorship table get an empty folder).
    $uni = $unilevel ?? [
        'id' => $root['id'], 'username' => $root['username'], 'fullName' => $root['fullName'],
        'directsCount' => $account['sponsoredCount'], 'totalTeamCount' => $account['leftCount'] + $account['rightCount'],
        'entryDate' => $root['entryDate'], 'children' => [],
    ];

    // Reference "oe": pre-order flatten tagging every node with the root leg it belongs to.
    $flatten = function (array $n, string $tag) use (&$flatten): array {
        $out = [['node' => $n, 'legTag' => $tag]];
        if (!empty($n['leftChild'])) {
            array_push($out, ...$flatten($n['leftChild'], $tag === 'root' ? 'left' : $tag));
        }
        if (!empty($n['rightChild'])) {
            array_push($out, ...$flatten($n['rightChild'], $tag === 'root' ? 'right' : $tag));
        }
        return $out;
    };
    $flat = $flatten($root, 'root');

    // Detail data for the member modal, keyed by account id.
    $nodeData = [];
    foreach ($flat as $f) {
        $n = $f['node'];
        $nodeData[$n['id']] = [
            'id' => $n['id'], 'fullName' => $n['fullName'], 'username' => $n['username'], 'package' => $n['package'],
            'position' => $n['position'], 'leftPoints' => $n['leftPoints'], 'rightPoints' => $n['rightPoints'],
            'leftCount' => (int) ($n['leftCount'] ?? 0), 'rightCount' => (int) ($n['rightCount'] ?? 0),
            'entryDate' => $n['entryDate'], 'status' => $n['status'],
        ];
    }

    // Downlines enter on the Executive package, so only Executive codes are offered here.
    $unused = array_values(array_filter($codes, fn ($c) => $c['status'] === 'active' && $c['package'] === 'membership_executive'));

    $subTabs = [
        ['id' => 'summary', 'icon' => 'layers', 'label' => 'Network Summary'],
        ['id' => 'genealogy', 'icon' => 'git-fork', 'label' => 'Network Genealogy (Tree)'],
        ['id' => 'list', 'icon' => 'users', 'label' => 'Network List (' . count($flat) . ')'],
        ['id' => 'unilevel', 'icon' => 'folder-tree', 'label' => 'Unilevel Sponsorship Tree'],
    ];
    $sel = (string) ($_GET['net'] ?? 'summary');
    if (!in_array($sel, array_column($subTabs, 'id'), true)) {
        $sel = 'summary';
    }
    $subOn = 'bg-gradient-to-r from-[#ffda72] to-[#f3bd35] text-black shadow-md';
    $subOff = 'text-zinc-400 hover:text-white';
    $legOn = 'bg-[#f3bd35] text-black shadow';
    $legOff = 'text-zinc-400 hover:text-white';

    $first = function (string $name): string {
        return explode(' ', $name)[0];
    };

    /*
     * Binary tree slot renderer. Each slot is a column whose connector lines
     * (half of the horizontal bar + a stub down to the card) are drawn with
     * absolutely positioned spans, so they stay joined however wide a branch
     * grows when members are opened below.
     *   depth 1 = the root's legs: member card (its two depth-2 slots shown)
     *             or "+ Add Left/Right Member".
     *   depth 2 = compact card (first name + id), its downlines folded until
     *             clicked, or "+ Empty L/R". bo-network.js draws deeper levels
     *             the same way.
     */
    $line = fn (string $color, string $style): string => '<span aria-hidden="true" style="position:absolute;background:' . $color . ';' . $style . '"></span>';
    $renderSlot = function (array $parent, string $leg, int $depth) use (&$renderSlot, $first, $line): void {
        $child = $parent[$leg . 'Child'] ?? null;
        $isLeft = $leg === 'left';
        $color = $depth === 1 ? 'rgba(243,189,53,0.4)' : '#3f3f46';
        $below = $child ? (int) $child['leftCount'] + (int) $child['rightCount'] : 0;
        ?>
        <div class="relative flex flex-col items-center <?= $depth === 1 ? 'px-4' : 'px-1' ?>" style="padding-top:14px">
            <?= $line($color, ($isLeft ? 'left:50%;right:0' : 'left:0;right:50%') . ';top:0;height:2px') ?>
            <?= $line($color, 'left:50%;top:0;width:2px;height:14px;margin-left:-1px') ?>
            <?php if ($depth === 1): ?>
                <?php if ($child): ?>
                    <div data-net-node="<?= e($child['id']) ?>" data-net-expand="<?= e($child['id']) ?>" aria-expanded="true" role="button" tabindex="0" title="Show or hide <?= e($first($child['fullName'])) ?>'s downlines" class="relative cursor-pointer p-3 rounded-2xl bg-zinc-900 border <?= $isLeft ? 'border-blue-500/50 hover:border-blue-400' : 'border-amber-500/50 hover:border-amber-400' ?> shadow-xl text-center w-44 hover:scale-102 transition-transform">
                        <button type="button" data-net-info="<?= e($child['id']) ?>" title="Member details" aria-label="Details of <?= e($child['fullName']) ?>" class="absolute top-0 right-1 p-1 text-zinc-500 hover:text-white"><?= icon('eye', 'w-3 h-3') ?></button>
                        <span class="text-[9px] px-1.5 py-0.2 rounded-full uppercase font-bold <?= $isLeft ? 'bg-blue-500/20 text-blue-400' : 'bg-amber-500/20 text-amber-400' ?> block mb-0.5"><?= $isLeft ? 'LEFT LEG' : 'RIGHT LEG' ?></span>
                        <div class="font-bold text-white text-xs truncate"><?= e($child['fullName']) ?></div>
                        <div class="text-[9px] text-zinc-400 font-mono">@<?= e($child['username']) ?></div>
                        <div class="mt-1.5 pt-1.5 border-t border-zinc-800 flex justify-around text-[9px] font-mono">
                            <span class="text-blue-400">L: <?= e($child['leftPoints']) ?></span>
                            <span class="text-amber-400">R: <?= e($child['rightPoints']) ?></span>
                        </div>
                    </div>
                <?php else: ?>
                    <button type="button" data-net-place="<?= e($parent['id']) ?>" data-net-leg="<?= e($leg) ?>" class="p-3 rounded-2xl bg-zinc-950 border border-dashed border-emerald-500/50 hover:border-emerald-400 text-emerald-400 hover:bg-emerald-500/10 text-xs font-bold text-center w-44 transition-all">
                        <?= icon('user-plus', 'w-4 h-4 mx-auto mb-1') ?>
                        <span><?= $isLeft ? '+ Add Left Member' : '+ Add Right Member' ?></span>
                    </button>
                <?php endif; ?>
            <?php else: ?>
                <?php if ($child): ?>
                    <div data-net-node="<?= e($child['id']) ?>" data-net-expand="<?= e($child['id']) ?>" aria-expanded="false" role="button" tabindex="0" title="<?= $below ? 'Show ' . e($first($child['fullName'])) . '\'s downlines' : 'No downlines yet' ?>" class="relative cursor-pointer p-2.5 rounded-xl bg-zinc-950 border border-zinc-800 hover:border-zinc-700 text-center w-24 text-[10px]">
                        <button type="button" data-net-info="<?= e($child['id']) ?>" title="Member details" aria-label="Details of <?= e($child['fullName']) ?>" class="absolute top-0 right-1 p-0.5 text-zinc-600 hover:text-white"><?= icon('eye', 'w-3 h-3') ?></button>
                        <div class="font-bold text-white truncate"><?= e($first($child['fullName'])) ?></div>
                        <div class="text-[8px] text-zinc-500 font-mono"><?= e($child['id']) ?></div>
                        <div class="text-[8px] mt-0.5 <?= $below ? 'text-[#f3bd35]' : 'text-zinc-600' ?>" data-net-below><?= $below ? '▾ ' . $below . ' below' : 'No downlines' ?></div>
                    </div>
                <?php else: ?>
                    <button type="button" data-net-place="<?= e($parent['id']) ?>" data-net-leg="<?= e($leg) ?>" class="p-2 rounded-xl bg-zinc-950 border border-dashed border-emerald-500/40 text-emerald-400 text-[10px] font-bold w-24 hover:bg-emerald-500/10"><?= $isLeft ? '+ Empty L' : '+ Empty R' ?></button>
                <?php endif; ?>
            <?php endif; ?>
            <?php if ($child): ?>
                <?php // The member's own two slots: shown under a leg, folded (and loaded on click) further down. ?>
                <div data-net-sub="<?= e($child['id']) ?>" class="relative flex justify-center" style="padding-top:14px"<?= $depth === 1 ? '' : ' hidden' ?>>
                    <?php if ($depth === 1): ?>
                        <?= $line('#3f3f46', 'left:50%;top:0;width:2px;height:14px;margin-left:-1px') ?>
                        <?php $renderSlot($child, 'left', 2); ?>
                        <?php $renderSlot($child, 'right', 2); ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    };
    ?>
<div class="space-y-6" data-bo-network>
    <div class="flex items-center gap-1.5 p-1.5 bg-[#121212] rounded-2xl border border-zinc-800 overflow-x-auto text-xs" data-tab-group="net" data-param="net" role="tablist">
        <?php foreach ($subTabs as $st): ?>
            <button type="button" data-tab="<?= e($st['id']) ?>" data-group="net" data-on="<?= $subOn ?>" data-off="<?= $subOff ?>" aria-selected="<?= $st['id'] === $sel ? 'true' : 'false' ?>" class="flex items-center gap-2 px-4 py-2 rounded-xl font-bold whitespace-nowrap transition-all <?= $st['id'] === $sel ? $subOn : $subOff ?>">
                <?= icon($st['icon'], 'w-4 h-4') ?>
                <span><?= e($st['label']) ?></span>
            </button>
        <?php endforeach; ?>
    </div>

    <div>
        <!-- Network Summary -->
        <div class="space-y-6" data-tab-panel="summary" data-group="net"<?= $sel === 'summary' ? '' : ' hidden' ?>>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="p-5 rounded-3xl bg-[#121212] border border-[#f3bd35]/40 shadow-xl">
                    <span class="text-zinc-400 text-xs font-semibold block mb-1">Directly Sponsored</span>
                    <div class="text-3xl font-black text-white font-mono"><?= e($account['sponsoredCount']) ?></div>
                    <span class="text-[11px] text-emerald-400 font-semibold mt-2 block"><?= peso($account['totalDirectBonus']) ?> Earned (@ ₱<?= num($complan['directReferralBonus']) ?> each)</span>
                </div>
                <div class="p-5 rounded-3xl bg-[#121212] border border-blue-500/30 shadow-xl">
                    <span class="text-zinc-400 text-xs font-semibold block mb-1">Left Downline Team</span>
                    <div class="text-3xl font-black text-blue-400 font-mono"><?= e($account['leftCount']) ?></div>
                    <span class="text-[11px] text-zinc-400 mt-2 block"><?= e($account['leftPoints']) ?> Accumulated Left PV</span>
                </div>
                <div class="p-5 rounded-3xl bg-[#121212] border border-amber-500/30 shadow-xl">
                    <span class="text-zinc-400 text-xs font-semibold block mb-1">Right Downline Team</span>
                    <div class="text-3xl font-black text-amber-400 font-mono"><?= e($account['rightCount']) ?></div>
                    <span class="text-[11px] text-zinc-400 mt-2 block"><?= e($account['rightPoints']) ?> Accumulated Right PV</span>
                </div>
                <div class="p-5 rounded-3xl bg-[#121212] border border-zinc-800 shadow-xl">
                    <span class="text-zinc-400 text-xs font-semibold block mb-1">Total Binary Organization</span>
                    <div class="text-3xl font-black text-[#ffda72] font-mono"><?= e($account['leftCount'] + $account['rightCount']) ?></div>
                    <span class="text-[11px] text-zinc-400 mt-2 block">Total PV: <?= e($account['leftPoints'] + $account['rightPoints']) ?></span>
                </div>
            </div>
            <div class="p-5 rounded-3xl bg-[#121212] border border-zinc-800 shadow-xl space-y-4">
                <h3 class="text-base font-bold text-white flex items-center gap-2">
                    <?= icon('award', 'w-5 h-5 text-[#f3bd35]') ?>
                    Binary Points Overview &amp; Pair Matching Status
                </h3>
                <div class="p-4 rounded-2xl bg-zinc-950 border border-zinc-800 grid grid-cols-1 md:grid-cols-3 gap-4 text-xs">
                    <div>
                        <span class="text-zinc-400 font-semibold block mb-1">Current Strong Leg</span>
                        <span class="font-bold text-blue-400 text-sm">Left Leg (<?= e($account['leftPoints']) ?> PV / <?= e($account['leftCount']) ?> Members)</span>
                        <p class="text-[10px] text-zinc-500 mt-1">Carried forward for future matching.</p>
                    </div>
                    <div>
                        <span class="text-zinc-400 font-semibold block mb-1">Current Pay Leg</span>
                        <span class="font-bold text-amber-400 text-sm">Right Leg (<?= e($account['rightPoints']) ?> PV / <?= e($account['rightCount']) ?> Members)</span>
                        <p class="text-[10px] text-zinc-500 mt-1">Every new 100 PV triggers ₱<?= num($complan['pairingBonus']) ?> bonus.</p>
                    </div>
                    <div>
                        <span class="text-zinc-400 font-semibold block mb-1">Cycle Cap Health</span>
                        <span class="font-bold text-emerald-400 text-sm"><?= e(max(0, $complan['maxPairsPerCycle'] - $account['cycle' . mlm_current_cycle() . 'PairsToday'])) ?> Pairs Available in <?= $complan['cyclesPerDay'] === 1 ? 'Today\'s Cycle' : 'Cycle ' . mlm_current_cycle() ?></span>
                        <p class="text-[10px] text-zinc-500 mt-1">Resets twice daily (12 AM / 12 PM).</p>
                    </div>
                </div>
                <div class="flex flex-col sm:flex-row items-center gap-3 pt-2">
                    <button type="button" data-net-goto="genealogy" class="w-full sm:w-auto px-5 py-2.5 rounded-xl bg-gradient-to-r from-[#ffda72] to-[#f3bd35] text-black font-extrabold text-xs shadow hover:brightness-105 transition-all">Open Visual Genealogy Tree →</button>
                    <button type="button" data-net-goto="unilevel" class="w-full sm:w-auto px-5 py-2.5 rounded-xl bg-zinc-900 border border-zinc-700 hover:bg-zinc-800 text-zinc-200 font-bold text-xs transition-all">Inspect Unilevel Sponsorship →</button>
                </div>
            </div>
        </div>

        <!-- Binary genealogy tree -->
        <div class="space-y-4" data-tab-panel="genealogy" data-group="net"<?= $sel === 'genealogy' ? '' : ' hidden' ?>>
            <div class="p-4 rounded-3xl bg-[#121212] border border-zinc-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                    <h3 class="text-base font-bold text-white flex items-center gap-2">
                        <?= icon('git-fork', 'w-5 h-5 text-[#f3bd35]') ?>
                        Interactive Binary Genealogy Tree
                    </h3>
                    <p class="text-xs text-zinc-400">Click a member to show or hide their downlines below; the members above stay in view. The <?= icon('eye', 'w-3 h-3 inline-block', ['style' => 'vertical-align:-2px']) ?> icon shows a member's points. <strong class="text-white">Register Downline</strong> places a new member on your left or right leg, or Auto picks the leg with fewer downlines; an <strong class="text-emerald-400">empty slot</strong> places them under that member.</p>
                </div>
                <div class="flex items-center gap-3 shrink-0">
                    <span class="text-[11px] text-zinc-400">Unused Codes: <strong class="text-[#ffda72] font-mono"><?= count($unused) ?></strong></span>
                    <button type="button" data-net-register="<?= e($root['id']) ?>" class="flex items-center gap-1.5 px-4 py-2 rounded-xl bg-gradient-to-r from-[#ffda72] to-[#f3bd35] text-black font-extrabold text-xs shadow hover:brightness-105 transition-all whitespace-nowrap">
                        <?= icon('user-plus', 'w-4 h-4') ?>
                        Register Downline
                    </button>
                </div>
            </div>
            <?php
            // Where sign-ups with this head's ID go (mlm_signup_placement); labels as in the registration modal.
            $suLeft = (int) ($root['leftCount'] ?? 0);
            $suRight = (int) ($root['rightCount'] ?? 0);
            $suCount = fn (int $n): string => $n ? $n . ($n === 1 ? ' downline' : ' downlines') : 'empty';
            $suChoices = [
                'auto' => 'Auto: ' . ($suLeft <= $suRight ? 'left' : 'right') . ' leg (' . ($suLeft === $suRight ? 'tie, left first' : 'fewer downlines') . ')',
                'left' => 'Left leg · ' . $suCount($suLeft),
                'right' => 'Right leg · ' . $suCount($suRight),
            ];
            $suSaved = $account['signupPlacement'] ?? 'auto';
            ?>
            <div class="p-4 rounded-3xl bg-[#121212] border border-zinc-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                    <h4 class="text-sm font-bold text-white flex items-center gap-2">
                        <?= icon('user-check', 'w-4 h-4 text-[#f3bd35]') ?>
                        Sign-up Placement
                    </h4>
                    <p class="text-xs text-zinc-400 mt-0.5">New members who enter your ID <strong class="font-mono text-[#ffda72]"><?= e($account['id']) ?></strong> as their sponsor when they sign up are placed here. Register Downline starts on this choice too.</p>
                </div>
                <div class="w-full sm:w-72 shrink-0">
                    <label for="bo-signup-placement" class="block text-[10px] font-bold uppercase tracking-wider text-zinc-500 mb-1">Place new sign-ups on</label>
                    <select id="bo-signup-placement" data-signup-placement data-saved="<?= e($suSaved) ?>" class="w-full px-3 py-2 rounded-xl bg-zinc-900 border border-zinc-700 text-white text-xs focus:outline-none focus:border-[#f3bd35] disabled:opacity-50">
                        <?php foreach ($suChoices as $v => $label): ?>
                            <option value="<?= $v ?>"<?= $v === $suSaved ? ' selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="p-6 rounded-3xl bg-[#0a0a0a] border border-[#f3bd35]/30 overflow-x-auto min-h-[500px] shadow-2xl" data-net-tree>
                <div class="min-w-[800px] mx-auto flex flex-col items-center" style="width:max-content">
                    <div class="flex flex-col items-center">
                        <div data-net-node="<?= e($root['id']) ?>" role="button" tabindex="0" class="cursor-pointer p-4 rounded-2xl bg-gradient-to-b from-[#221c0e] to-[#120f09] border-2 border-[#f3bd35] shadow-2xl hover:scale-105 transition-transform text-center w-52">
                            <div class="text-[10px] font-bold px-2 py-0.5 rounded-full uppercase bg-[#f3bd35] text-black inline-block mb-1">ROOT HEAD</div>
                            <div class="font-bold text-white text-sm"><?= e($root['fullName']) ?></div>
                            <div class="text-[10px] text-zinc-400 font-mono">@<?= e($root['username']) ?></div>
                            <div class="mt-2 pt-2 border-t border-zinc-800/80 flex justify-around text-[10px] font-mono">
                                <span class="text-blue-400 font-bold">L: <?= e($root['leftPoints']) ?> PV</span>
                                <span class="text-amber-400 font-bold">R: <?= e($root['rightPoints']) ?> PV</span>
                            </div>
                        </div>
                        <div class="relative flex justify-center" style="padding-top:24px">
                            <?= $line('rgba(243,189,53,0.4)', 'left:50%;top:0;width:2px;height:24px;margin-left:-1px') ?>
                            <?php $renderSlot($root, 'left', 1); ?>
                            <?php $renderSlot($root, 'right', 1); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Network list -->
        <div class="space-y-4" data-tab-panel="list" data-group="net"<?= $sel === 'list' ? '' : ' hidden' ?>>
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div class="relative flex-1 max-w-md">
                    <?= icon('search', 'w-4 h-4 text-zinc-500 absolute left-3 top-2.5') ?>
                    <input type="text" data-net-search aria-label="Search member by name, username or ID" placeholder="Search member by name, username or ID..." class="w-full pl-9 pr-3 py-2 rounded-xl bg-zinc-900 border border-zinc-700 text-xs text-white placeholder-zinc-500 focus:outline-none focus:border-[#f3bd35]">
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-xs text-zinc-400">Filter Leg:</span>
                    <div class="flex bg-zinc-900 rounded-xl p-1 border border-zinc-800 text-xs">
                        <?php foreach (['all', 'left', 'right'] as $leg): ?>
                            <button type="button" data-net-leg-filter="<?= $leg ?>" data-on="<?= $legOn ?>" data-off="<?= $legOff ?>" aria-pressed="<?= $leg === 'all' ? 'true' : 'false' ?>" class="px-3 py-1 rounded-lg uppercase font-bold text-[10px] <?= $leg === 'all' ? $legOn : $legOff ?>"><?= $leg ?></button>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="rounded-3xl bg-[#121212] border border-zinc-800 overflow-hidden shadow-xl">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-zinc-900/80 border-b border-zinc-800 text-zinc-400 uppercase text-[10px] tracking-wider">
                            <tr>
                                <th class="py-3 px-4">Account ID</th>
                                <th class="py-3 px-4">Member Name</th>
                                <th class="py-3 px-4">Leg Position</th>
                                <th class="py-3 px-4">Package</th>
                                <th class="py-3 px-4">Left PV</th>
                                <th class="py-3 px-4">Right PV</th>
                                <th class="py-3 px-4">Entry Date</th>
                                <th class="py-3 px-4">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-800/80" data-net-rows>
                            <?php foreach ($flat as $f): $n = $f['node']; $tag = $f['legTag']; ?>
                                <tr data-net-node="<?= e($n['id']) ?>" data-leg="<?= e($tag) ?>" data-search="<?= e(mb_strtolower($n['fullName'] . "\n" . $n['username'] . "\n" . $n['id'])) ?>" tabindex="0" class="hover:bg-zinc-900/40 transition-colors cursor-pointer">
                                    <td class="py-3.5 px-4 font-mono font-bold text-[#ffda72]"><?= e($n['id']) ?></td>
                                    <td class="py-3.5 px-4">
                                        <div class="font-bold text-white"><?= e($n['fullName']) ?></div>
                                        <div class="text-[10px] text-zinc-500 font-mono">@<?= e($n['username']) ?></div>
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <span class="text-[9px] px-2 py-0.5 rounded-full font-bold uppercase <?= $tag === 'left' ? 'bg-blue-500/15 text-blue-400' : ($tag === 'right' ? 'bg-amber-500/15 text-amber-400' : 'bg-zinc-800 text-zinc-400') ?>"><?= e($tag) ?></span>
                                    </td>
                                    <td class="py-3.5 px-4 text-zinc-300"><?= e($n['package']) ?></td>
                                    <td class="py-3.5 px-4 font-mono text-blue-400 font-bold"><?= e($n['leftPoints']) ?></td>
                                    <td class="py-3.5 px-4 font-mono text-amber-400 font-bold"><?= e($n['rightPoints']) ?></td>
                                    <td class="py-3.5 px-4 text-zinc-400 text-[11px]"><?= e($n['entryDate']) ?></td>
                                    <td class="py-3.5 px-4">
                                        <span class="text-[9px] px-2 py-0.5 rounded-full bg-emerald-500/15 text-emerald-400 font-bold uppercase"><?= e($n['status']) ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Unilevel sponsorship tree -->
        <div class="space-y-4" data-tab-panel="unilevel" data-group="net"<?= $sel === 'unilevel' ? '' : ' hidden' ?>>
            <div class="p-4 rounded-3xl bg-[#121212] border border-zinc-800">
                <h3 class="text-base font-bold text-white flex items-center gap-2">
                    <?= icon('folder-tree', 'w-5 h-5 text-[#f3bd35]') ?>
                    Unilevel Sponsorship Generation Hierarchy
                </h3>
                <p class="text-xs text-zinc-400 mt-0.5">Folder breakdown of personally enrolled frontliners (Generation 1) and their successive team depths.</p>
            </div>
            <div class="p-5 rounded-3xl bg-[#121212] border border-zinc-800 space-y-3">
                <div class="p-3.5 rounded-2xl bg-zinc-950 border border-[#f3bd35]/40 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="text-xs px-2 py-0.5 rounded-full bg-[#f3bd35] text-black font-extrabold text-[10px]">GEN 0</span>
                        <span class="font-bold text-white text-sm"><?= e($uni['fullName']) ?> (@<?= e($uni['username']) ?>)</span>
                    </div>
                    <span class="text-xs text-[#ffda72] font-semibold"><?= e($uni['directsCount']) ?> Personally Sponsored Frontliners · <?= e($uni['totalTeamCount']) ?> Total Team</span>
                </div>
                <div class="pl-6 space-y-2.5 border-l-2 border-[#f3bd35]/20 ml-4">
                    <?php foreach ($uni['children'] as $g1):
                        // Reference initial expand state: { "MMM-88001": true }.
                        $open = $g1['id'] === 'MMM-88001';
                        $hasKids = count($g1['children']) > 0;
                        ob_start(); ?>
                        <div class="pl-6 space-y-2 border-l-2 border-blue-500/20 ml-4" data-uni-children>
                            <?php foreach ($g1['children'] as $g2): ?>
                                <div class="p-2.5 rounded-xl bg-zinc-950 border border-zinc-800 flex items-center justify-between text-xs">
                                    <div class="flex items-center gap-2">
                                        <span class="text-[9px] px-1.5 py-0.2 rounded-full uppercase font-bold bg-purple-500/20 text-purple-400">GEN 2</span>
                                        <span class="font-semibold text-zinc-200"><?= e($g2['fullName']) ?> (@<?= e($g2['username']) ?>)</span>
                                    </div>
                                    <span class="text-[10px] text-zinc-500"><?= e($g2['entryDate']) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php $kidsHtml = (string) ob_get_clean(); ?>
                        <div class="space-y-2" data-uni-item>
                            <?php if ($hasKids && !$open): ?><template data-uni-tpl><?= $kidsHtml ?></template><?php endif; ?>
                            <div data-uni-toggle role="button" tabindex="0" aria-expanded="<?= $open ? 'true' : 'false' ?>" class="p-3 rounded-2xl bg-zinc-900 border border-zinc-700/80 hover:border-zinc-600 cursor-pointer flex items-center justify-between text-xs">
                                <div class="flex items-center gap-2">
                                    <?php if ($hasKids): ?>
                                        <?= icon('chevron-down', 'w-4 h-4 text-[#f3bd35]', $open ? ['data-uni-open' => ''] : ['data-uni-open' => '', 'hidden' => 'hidden']) ?>
                                        <?= icon('chevron-right', 'w-4 h-4 text-zinc-400', $open ? ['data-uni-closed' => '', 'hidden' => 'hidden'] : ['data-uni-closed' => '']) ?>
                                    <?php else: ?>
                                        <div class="w-4 h-4"></div>
                                    <?php endif; ?>
                                    <span class="text-[9px] px-1.5 py-0.2 rounded-full uppercase font-bold bg-blue-500/20 text-blue-400">GEN 1</span>
                                    <span class="font-bold text-white"><?= e($g1['fullName']) ?> (@<?= e($g1['username']) ?>)</span>
                                </div>
                                <div class="text-zinc-400 text-[11px] flex items-center gap-3">
                                    <span>Directs: <?= e($g1['directsCount']) ?></span>
                                    <span>Team: <?= e($g1['totalTeamCount']) ?></span>
                                    <span class="text-zinc-500"><?= e($g1['entryDate']) ?></span>
                                </div>
                            </div>
                            <?php if ($hasKids && $open): ?><?= $kidsHtml ?><?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script type="application/json" id="bo-net-nodes"><?= js_json($nodeData) ?></script>

<!-- Downline registration (reference state f / ie / ae) -->
<div data-modal="bo-net-register" hidden class="fixed inset-0 z-50 flex items-center justify-center bg-black/85 backdrop-blur-md p-4 animate-in fade-in" role="dialog" aria-modal="true" aria-labelledby="bo-net-register-title">
    <div class="w-full max-w-lg rounded-3xl bg-[#141414] border border-[#f3bd35]/40 p-6 shadow-2xl relative text-left max-h-[92vh] overflow-y-auto">
        <button type="button" data-close-modal aria-label="Close" class="absolute top-4 right-4 text-zinc-400 hover:text-white p-1 rounded-full"><?= icon('x', 'w-5 h-5') ?></button>
        <div class="mb-4">
            <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-[#f3bd35]/15 border border-[#f3bd35]/30 text-[#f3bd35] text-[10px] font-bold uppercase tracking-wider mb-1">
                <?= icon('user-plus', 'w-3.5 h-3.5') ?>
                Binary Downline Placement
            </div>
            <h3 id="bo-net-register-title" class="text-xl font-bold text-white">Enroll New Member</h3>
            <p class="text-xs text-zinc-400 mt-0.5">Placing under <strong class="text-white" data-reg-node></strong> on the <strong class="text-[#ffda72] uppercase font-bold" data-reg-leg>left leg</strong>.</p>
        </div>
        <div data-reg-success hidden class="p-4 rounded-2xl bg-emerald-950/40 border border-emerald-500/40 text-emerald-300 text-xs text-center space-y-2" role="status">
            <?= icon('circle-check', 'w-8 h-8 text-emerald-400 mx-auto') ?>
            <p class="font-bold" data-reg-success-msg></p>
            <p class="text-[11px] text-zinc-400">Genealogy tree and commissions updated automatically.</p>
        </div>
        <form data-reg-form class="space-y-4 text-xs">
            <div data-reg-error hidden role="alert" class="p-3 rounded-xl bg-rose-950/40 border border-rose-500/40 text-rose-300 text-xs"></div>
            <div class="p-3 rounded-2xl bg-zinc-950 border border-[#f3bd35]/30 text-[11px] space-y-1">
                <div class="flex justify-between font-bold text-white">
                    <span>Direct Referral Bonus:</span>
                    <span class="text-emerald-400 font-mono">+₱<?= number_format($complan['directReferralBonus'], 2) ?> PHP</span>
                </div>
                <div class="flex justify-between font-bold text-white">
                    <span>Binary Match Potential:</span>
                    <span class="text-[#ffda72] font-mono">+₱<?= number_format($complan['pairingBonus'], 2) ?> PHP (if opposite leg has points)</span>
                </div>
                <div class="text-[10px] text-zinc-500 pt-1 border-t border-zinc-800">Package Cost: ₱2,000.00 (Consumed via selected activation code).</div>
            </div>
            <div>
                <label for="bo-reg-placement" class="block text-zinc-300 font-semibold mb-1">Placement *</label>
                <?php // bo-network.js adds each leg's downline count to the option labels. ?>
                <select id="bo-reg-placement" name="placement" required class="w-full px-3 py-2 rounded-xl bg-zinc-900 border border-zinc-700 text-white focus:outline-none focus:border-[#f3bd35]">
                    <option value="auto" selected>Auto: leg with fewer downlines</option>
                    <option value="left">Left leg</option>
                    <option value="right">Right leg</option>
                </select>
                <p class="text-[10px] text-zinc-500 mt-2" data-reg-hint></p>
            </div>
            <div>
                <label for="bo-reg-fullname" class="block text-zinc-300 font-semibold mb-1">New Member Full Name *</label>
                <input id="bo-reg-fullname" name="fullName" type="text" required maxlength="150" autocomplete="off" placeholder="e.g. Attorney Roberto Diaz" class="w-full px-3 py-2 rounded-xl bg-zinc-900 border border-zinc-700 text-white focus:outline-none focus:border-[#f3bd35]">
            </div>
            <div>
                <label for="bo-reg-username" class="block text-zinc-300 font-semibold mb-1">Desired Username *</label>
                <input id="bo-reg-username" name="username" type="text" required minlength="3" maxlength="30" autocomplete="off" autocapitalize="none" spellcheck="false" placeholder="e.g. robertodiaz88" class="w-full px-3 py-2 rounded-xl bg-zinc-900 border border-zinc-700 text-white font-mono focus:outline-none focus:border-[#f3bd35]">
            </div>
            <div>
                <label for="bo-reg-code" class="block text-zinc-300 font-semibold mb-1">Select Available Activation Code (₱2,000) *</label>
                <?php if ($unused): ?>
                    <select id="bo-reg-code" name="activationCode" required class="w-full px-3 py-2 rounded-xl bg-zinc-900 border border-zinc-700 text-white font-mono focus:outline-none focus:border-[#f3bd35]">
                        <?php foreach ($unused as $c): ?>
                            <option value="<?= e($c['code']) ?>" data-pin="<?= e($c['pin']) ?>"><?= e($c['code']) ?> (PIN: <?= e($c['pin']) ?> · <?= peso($c['value']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                <?php else: ?>
                    <div class="p-3 rounded-xl bg-rose-950/30 border border-rose-500/30 text-rose-300 text-xs">No unused activation codes in vault. Please purchase an activation code first under 'My Account'.</div>
                <?php endif; ?>
            </div>
            <div class="pt-2">
                <button type="submit" disabled data-reg-submit data-has-codes="<?= $unused ? '1' : '0' ?>" class="w-full py-3 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:brightness-110 text-white font-bold text-xs shadow-lg transition-transform active:scale-95 disabled:opacity-50">Confirm &amp; Place Downline</button>
            </div>
        </form>
    </div>
</div>

<!-- Member details (reference state N / P) -->
<div data-modal="bo-net-node" hidden class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-md p-4 animate-in fade-in" role="dialog" aria-modal="true" aria-labelledby="bo-net-node-name">
    <div class="w-full max-w-sm rounded-3xl bg-[#141414] border border-[#f3bd35]/40 p-6 shadow-2xl relative text-left">
        <button type="button" data-close-modal aria-label="Close" class="absolute top-4 right-4 text-zinc-400 hover:text-white p-1 rounded-full"><?= icon('x', 'w-5 h-5') ?></button>
        <div class="text-center mb-4">
            <div class="w-14 h-14 rounded-full bg-[#f3bd35]/20 text-[#f3bd35] flex items-center justify-center mx-auto mb-2 font-black text-lg" data-nd="initial"></div>
            <h3 id="bo-net-node-name" class="text-base font-bold text-white" data-nd="fullName"></h3>
            <p class="text-xs text-zinc-400 font-mono" data-nd="handle"></p>
        </div>
        <div class="space-y-2 p-3.5 rounded-2xl bg-zinc-950 border border-zinc-800 text-xs mb-4">
            <div class="flex justify-between">
                <span class="text-zinc-400">Package:</span>
                <span class="text-white font-medium" data-nd="package"></span>
            </div>
            <div class="flex justify-between">
                <span class="text-zinc-400">Leg Placement:</span>
                <span class="text-[#ffda72] font-bold uppercase" data-nd="position"></span>
            </div>
            <div class="flex justify-between">
                <span class="text-zinc-400">Left Points:</span>
                <span class="font-mono text-blue-400 font-bold" data-nd="leftPoints"></span>
            </div>
            <div class="flex justify-between">
                <span class="text-zinc-400">Right Points:</span>
                <span class="font-mono text-amber-400 font-bold" data-nd="rightPoints"></span>
            </div>
            <div class="flex justify-between">
                <span class="text-zinc-400">Entry Date:</span>
                <span class="text-zinc-300" data-nd="entryDate"></span>
            </div>
            <div class="flex justify-between">
                <span class="text-zinc-400">Account Status:</span>
                <span class="text-emerald-400 font-bold uppercase" data-nd="status"></span>
            </div>
        </div>
        <button type="button" data-close-modal class="w-full py-2.5 rounded-xl bg-zinc-900 text-zinc-300 font-bold text-xs hover:bg-zinc-800">Close</button>
    </div>
</div>
<?php
})($account, $complan, $genealogy, $unilevel, $codes);
