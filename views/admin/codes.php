<?php
/**
 * Management Admin > Activation Codes (superadmin only): generate codes,
 * verify a code (and PIN), and the code logs (serial, package, code, PIN,
 * status, used by, date used) where unused codes are activated or
 * deactivated one at a time or in bulk (tick several, or select every
 * matching code), and the selected codes are downloaded as CSV (used ones
 * too). Actions go through api/actions/admin_codes.php
 * (assets/js/views/admin-codes.js).
 */
$cdInput = 'w-full px-3 py-2 rounded-xl bg-zinc-900 border border-zinc-700 text-xs text-white focus:outline-none focus:border-[#f3bd35]';
$cdLabel = 'block text-[11px] font-semibold text-zinc-300 mb-1';
$cdCard = 'p-5 rounded-3xl bg-[#111111] border border-[#f3bd35]/20 space-y-4';
$cdPrimary = 'px-5 py-2 bg-[#f3bd35] hover:bg-[#ffda72] text-black text-xs font-extrabold rounded-xl shadow-md transition-colors flex items-center justify-center gap-1.5 disabled:opacity-60';
$cdSmall = 'px-2.5 py-1 bg-zinc-900 hover:bg-zinc-800 border border-zinc-700 text-zinc-300 hover:text-white text-[11px] font-medium rounded-lg transition-colors flex items-center gap-1';
$cdPrices = ['membership_executive' => checkout_item('membership_executive')['price'], 'membership_chairmans' => checkout_item('membership_chairmans')['price']];
$cdFounder = find_code_owner('MMM-88001');

$cdQ = mb_substr(trim(is_string($_GET['q'] ?? null) ? $_GET['q'] : ''), 0, 100);
$cdStatus = is_string($_GET['status'] ?? null) && isset(CODE_STATUSES[$_GET['status']]) ? $_GET['status'] : '';
$cdPackage = is_string($_GET['package'] ?? null) && isset(CODE_PACKAGES[$_GET['package']]) ? $_GET['package'] : '';
$cdPage = max(1, (int) ($_GET['page'] ?? 1));
[$cdRows, $cdTotal] = admin_list_codes($cdQ, $cdStatus, $cdPackage, $cdPage);
$cdPages = max(1, (int) ceil($cdTotal / CODES_PER_PAGE));
if ($cdPage > $cdPages) {
    $cdPage = $cdPages;
    [$cdRows, $cdTotal] = admin_list_codes($cdQ, $cdStatus, $cdPackage, $cdPage);
}
$cdFiltered = $cdQ !== '' || $cdStatus !== '' || $cdPackage !== '';
$cdPageUrl = fn (int $to): string => url('admin', array_filter(['tab' => 'codes', 'q' => $cdQ, 'status' => $cdStatus, 'package' => $cdPackage, 'page' => $to > 1 ? $to : '']));
?>
<div class="space-y-4" id="admin-codes">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <section class="<?= $cdCard ?>" aria-labelledby="cd-gen-title">
            <div>
                <h3 id="cd-gen-title" class="text-sm font-bold text-white flex items-center gap-2"><?= icon('key', 'w-4 h-4 text-[#f3bd35]') ?>Generate Activation Codes</h3>
                <p class="text-xs text-zinc-400 mt-0.5">New members can pay for their account with a code and its PIN instead of paying online. Codes go into a backoffice account's vault, where the owner can also transfer them.</p>
            </div>
            <form id="cd-gen-form" class="space-y-3 text-xs" novalidate>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label for="cd-package" class="<?= $cdLabel ?>">Package</label>
                        <select id="cd-package" name="package" class="<?= $cdInput ?>">
                            <option value="membership_executive">Executive Member · <?= peso($cdPrices['membership_executive']) ?></option>
                            <option value="membership_chairmans">Chairman’s Circle · <?= peso($cdPrices['membership_chairmans']) ?></option>
                        </select>
                    </div>
                    <div>
                        <label for="cd-quantity" class="<?= $cdLabel ?>">How many (1–<?= CODES_MAX_BATCH ?>)</label>
                        <input id="cd-quantity" name="quantity" type="number" min="1" max="<?= CODES_MAX_BATCH ?>" value="10" required class="<?= $cdInput ?>">
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label for="cd-owner" class="<?= $cdLabel ?>">Put them in this backoffice account</label>
                        <input id="cd-owner" name="owner" type="text" maxlength="60" autocomplete="off" placeholder="MMM-88001 or @username" class="<?= $cdInput ?> font-mono">
                    </div>
                    <div>
                        <label for="cd-status" class="<?= $cdLabel ?>">Status</label>
                        <select id="cd-status" name="status" class="<?= $cdInput ?>">
                            <option value="active">Active (ready to use)</option>
                            <option value="inactive">Inactive (activate later)</option>
                        </select>
                    </div>
                </div>
                <span class="block text-[10px] text-zinc-500">Leave the account empty for the company stock: <?= e($cdFounder ? $cdFounder['user_name'] . ' · @' . $cdFounder['username'] . ' · ' . $cdFounder['id'] : 'MMM-88001') ?>. Inactive codes can't be used or transferred until you activate them in the logs below.</span>
                <p data-cd-gen-msg hidden role="alert" class="p-3 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-300"></p>
                <button type="submit" class="<?= $cdPrimary ?> w-full sm:w-auto"><?= icon('sparkles', 'w-3.5 h-3.5') ?><span>Generate Codes</span></button>
            </form>
            <div data-cd-result hidden class="p-4 rounded-2xl bg-zinc-950 border border-emerald-500/30 space-y-3 text-xs">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                    <p class="font-semibold text-emerald-300 flex items-center gap-1.5"><?= icon('circle-check', 'w-4 h-4') ?><span data-cd-result-title></span></p>
                    <div class="flex items-center gap-1.5">
                        <button type="button" data-cd-copy class="<?= $cdSmall ?>"><?= icon('copy', 'w-3 h-3') ?><span>Copy all</span></button>
                        <button type="button" data-cd-download class="<?= $cdSmall ?>"><?= icon('download', 'w-3 h-3') ?><span>Download CSV</span></button>
                    </div>
                </div>
                <p class="text-[11px] text-amber-200">Send each code with its PIN to the person who will use it. The owner also sees them in their backoffice (My Account → Activation Codes).</p>
                <div class="max-h-56 overflow-y-auto rounded-xl border border-zinc-800">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-[#181818] text-zinc-400 uppercase text-[10px] tracking-wider"><tr><th class="py-2 px-3" scope="col">Serial</th><th class="py-2 px-3" scope="col">Code</th><th class="py-2 px-3" scope="col">PIN</th></tr></thead>
                        <tbody data-cd-result-rows class="divide-y divide-zinc-800/80 font-mono text-zinc-200"></tbody>
                    </table>
                </div>
                <div data-cd-result-pager hidden class="flex items-center justify-center gap-3 text-[11px]">
                    <button type="button" data-cd-result-prev class="px-2.5 py-1 rounded-lg bg-zinc-900 hover:bg-zinc-800 border border-zinc-700 text-zinc-200 disabled:opacity-40">← Previous</button>
                    <span class="text-zinc-400" data-cd-result-page></span>
                    <button type="button" data-cd-result-next class="px-2.5 py-1 rounded-lg bg-zinc-900 hover:bg-zinc-800 border border-zinc-700 text-zinc-200 disabled:opacity-40">Next →</button>
                </div>
                <a href="<?= e(url('admin', ['tab' => 'codes'])) ?>" class="inline-flex items-center gap-1 text-[11px] text-[#ffda72] hover:text-white"><?= icon('refresh-cw', 'w-3 h-3') ?><span>Refresh the logs below</span></a>
            </div>
        </section>

        <section class="<?= $cdCard ?>" aria-labelledby="cd-check-title">
            <div>
                <h3 id="cd-check-title" class="text-sm font-bold text-white flex items-center gap-2"><?= icon('shield-check', 'w-4 h-4 text-[#f3bd35]') ?>Verify a Code</h3>
                <p class="text-xs text-zinc-400 mt-0.5">Check whether a code is genuine, which package it activates, who owns it and whether it has been used. Add the PIN to check that too.</p>
            </div>
            <form id="cd-check-form" class="space-y-3 text-xs" novalidate>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div class="sm:col-span-2">
                        <label for="cd-check-code" class="<?= $cdLabel ?>">Activation code</label>
                        <input id="cd-check-code" name="code" type="text" required maxlength="40" autocomplete="off" spellcheck="false" placeholder="EVO88-EXE-…" class="<?= $cdInput ?> font-mono uppercase">
                    </div>
                    <div>
                        <label for="cd-check-pin" class="<?= $cdLabel ?>">PIN (optional)</label>
                        <input id="cd-check-pin" name="pin" type="password" maxlength="10" inputmode="numeric" autocomplete="off" class="<?= $cdInput ?> font-mono">
                    </div>
                </div>
                <button type="submit" class="<?= $cdPrimary ?> w-full sm:w-auto"><?= icon('search', 'w-3.5 h-3.5') ?><span>Check Code</span></button>
            </form>
            <div data-cd-check-result hidden class="p-4 rounded-2xl bg-zinc-950 border border-zinc-800 space-y-3 text-xs" role="status"></div>
        </section>
    </div>

    <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 p-4 rounded-3xl bg-[#111111] border border-[#f3bd35]/20">
        <div>
            <h3 class="text-sm font-bold text-white">Activation Code Logs</h3>
            <p class="text-xs text-zinc-400">Every code, generated here or bought by members in their backoffice. Unused codes can be deactivated and activated again.</p>
        </div>
        <form method="get" action="<?= e(base_path()) ?>/index.php" role="search" class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 w-full md:w-auto">
            <input type="hidden" name="view" value="admin">
            <input type="hidden" name="tab" value="codes">
            <div class="relative w-full sm:w-72">
                <?= icon('search', 'w-4 h-4 text-zinc-400 absolute left-3 top-1/2 -translate-y-1/2') ?>
                <input name="q" type="search" value="<?= e($cdQ) ?>" maxlength="100" placeholder="Serial, code, owner or user" aria-label="Search codes by serial, code, owner or user" class="w-full pl-9 pr-3 py-2 rounded-xl bg-zinc-900 border border-zinc-700 text-xs text-white placeholder-zinc-500 focus:outline-none focus:border-[#f3bd35]">
            </div>
            <select name="status" aria-label="Filter by status" class="px-3 py-2 rounded-xl bg-zinc-900 border border-zinc-700 text-xs text-white focus:outline-none focus:border-[#f3bd35]">
                <option value="">Any status</option>
                <?php foreach (CODE_STATUSES as $cdValue => $cdText): ?>
                    <option value="<?= e($cdValue) ?>"<?= $cdValue === $cdStatus ? ' selected' : '' ?>><?= e($cdText) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="package" aria-label="Filter by package" class="px-3 py-2 rounded-xl bg-zinc-900 border border-zinc-700 text-xs text-white focus:outline-none focus:border-[#f3bd35]">
                <option value="">Any package</option>
                <?php foreach (CODE_PACKAGES as $cdValue => $cdShort): ?>
                    <option value="<?= e($cdValue) ?>"<?= $cdValue === $cdPackage ? ' selected' : '' ?>><?= e(code_package_label($cdValue)) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="px-4 py-2 bg-[#f3bd35] hover:bg-[#ffda72] text-black text-xs font-extrabold rounded-xl transition-colors">Apply</button>
            <?php if ($cdFiltered): ?>
                <a href="<?= e(url('admin', ['tab' => 'codes'])) ?>" class="px-3 py-2 text-xs text-zinc-400 hover:text-white text-center">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="rounded-3xl bg-[#111111] border border-[#f3bd35]/20 overflow-hidden shadow-xl" data-cd-logs data-filter-q="<?= e($cdQ) ?>" data-filter-status="<?= e($cdStatus) ?>" data-filter-package="<?= e($cdPackage) ?>" data-selectable-total="<?= $cdTotal ?>">
        <div class="px-4 py-3 border-b border-zinc-800 text-[11px] text-zinc-400">
            <?= number_format($cdTotal) ?> <?= $cdTotal === 1 ? 'code' : 'codes' ?><?= $cdFiltered ? ' match these filters' : '' ?>
        </div>
        <div data-cd-bulk-bar hidden class="px-4 py-3 border-b border-[#f3bd35]/30 bg-[#f3bd35]/10 flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-xs" role="region" aria-label="Selected codes">
            <div class="text-zinc-200">
                <span class="font-bold text-[#ffda72]" data-cd-bulk-count>0</span> selected<span data-cd-bulk-scope class="text-zinc-400"></span>
                <button type="button" data-cd-select-matching hidden class="ml-1 font-semibold text-[#ffda72] underline underline-offset-2 hover:text-white">Select all <?= number_format($cdTotal) ?> codes that match</button>
            </div>
            <div class="flex flex-wrap items-center gap-1.5">
                <button type="button" data-cd-bulk="active" class="px-3 py-1.5 bg-[#f3bd35] hover:bg-[#ffda72] text-black text-[11px] font-extrabold rounded-lg transition-colors flex items-center gap-1 disabled:opacity-60"><?= icon('check', 'w-3 h-3') ?><span>Activate selected</span></button>
                <button type="button" data-cd-bulk="inactive" class="px-3 py-1.5 bg-zinc-900 hover:bg-zinc-800 border border-zinc-700 text-zinc-200 text-[11px] font-semibold rounded-lg transition-colors disabled:opacity-60">Deactivate selected</button>
                <button type="button" data-cd-bulk-csv class="px-3 py-1.5 bg-zinc-900 hover:bg-zinc-800 border border-zinc-700 text-zinc-200 text-[11px] font-semibold rounded-lg transition-colors flex items-center gap-1 disabled:opacity-60"><?= icon('download', 'w-3 h-3') ?><span>Download CSV</span></button>
                <button type="button" data-cd-bulk-clear class="px-2 py-1.5 text-[11px] text-zinc-400 hover:text-white">Clear</button>
            </div>
        </div>
        <?php if ($cdRows): ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-[#181818] border-b border-zinc-800 text-zinc-400 uppercase text-[10px] tracking-wider">
                        <tr>
                            <th class="py-3 pl-3.5 pr-1" scope="col">
                                <input type="checkbox" data-cd-select-all aria-label="Select every code on this page" class="w-4 h-4 rounded bg-zinc-900 border-zinc-700 text-[#f3bd35] cursor-pointer disabled:opacity-40">
                            </th>
                            <th class="py-3 px-4" scope="col">Serial</th>
                            <th class="py-3 px-4" scope="col">Package</th>
                            <th class="py-3 px-4" scope="col">Code</th>
                            <th class="py-3 px-4" scope="col">PIN</th>
                            <th class="py-3 px-4" scope="col">Status</th>
                            <th class="py-3 px-4" scope="col">Used By</th>
                            <th class="py-3 px-4" scope="col">Date Used</th>
                            <th class="py-3 px-4 text-right" scope="col" aria-label="Actions"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800/80">
                        <?php foreach ($cdRows as $cdRow): $cdVip = $cdRow['package'] === 'membership_chairmans'; $cdUsed = $cdRow['status'] === 'used'; ?>
                            <tr class="hover:bg-zinc-900/50 transition-colors" data-cd-row="<?= e($cdRow['code']) ?>">
                                <td class="py-3 pl-3.5 pr-1">
                                    <input type="checkbox" data-cd-select value="<?= e($cdRow['code']) ?>" aria-label="Select <?= e(code_serial((int) $cdRow['serial'])) ?>" class="w-4 h-4 rounded bg-zinc-900 border-zinc-700 text-[#f3bd35] cursor-pointer">
                                </td>
                                <td class="py-3 px-4 font-mono text-zinc-300 whitespace-nowrap"><?= e(code_serial((int) $cdRow['serial'])) ?></td>
                                <td class="py-3 px-4 whitespace-nowrap">
                                    <span class="text-[10px] font-bold uppercase rounded-lg px-2 py-0.5 bg-zinc-900 border <?= $cdVip ? 'border-purple-500/50 text-purple-400' : 'border-zinc-700 text-zinc-300' ?>"><?= e($cdVip ? "Chairman's" : 'Executive') ?></span>
                                    <span class="block text-[10px] text-zinc-500 mt-1"><?= peso($cdRow['value']) ?></span>
                                </td>
                                <td class="py-3 px-4 font-mono text-white whitespace-nowrap"><?= e($cdRow['code']) ?></td>
                                <td class="py-3 px-4 whitespace-nowrap">
                                    <button type="button" data-cd-pin="<?= e($cdRow['pin']) ?>" class="font-mono text-zinc-400 hover:text-white" aria-label="Show PIN for <?= e($cdRow['code']) ?>">••••••</button>
                                </td>
                                <td class="py-3 px-4 whitespace-nowrap">
                                    <?php if ($cdRow['status'] === 'active'): ?>
                                        <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-400"><span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>Active</span>
                                    <?php elseif ($cdRow['status'] === 'inactive'): ?>
                                        <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-amber-300"><span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>Inactive</span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-zinc-400"><span class="w-1.5 h-1.5 rounded-full bg-zinc-600"></span>Used</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-4 whitespace-nowrap">
                                    <?php if ($cdUsed): ?>
                                        <div class="text-zinc-200"><?= e((string) $cdRow['used_by']) ?></div>
                                        <?php if ($cdRow['used_by_email']): ?><div class="text-[10px] text-zinc-500"><?= e($cdRow['used_by_email']) ?></div><?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-zinc-600">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-4 text-zinc-400 text-[11px] whitespace-nowrap"><?= $cdUsed && $cdRow['used_at'] ? e(fmt_datetime($cdRow['used_at'])) : '<span class="text-zinc-600">—</span>' ?></td>
                                <td class="py-3 px-4 text-right whitespace-nowrap">
                                    <?php if ($cdRow['status'] === 'active'): ?>
                                        <button type="button" data-cd-status="inactive" data-cd-code="<?= e($cdRow['code']) ?>" class="px-2.5 py-1 bg-zinc-900 hover:bg-zinc-800 border border-zinc-700 text-zinc-300 hover:text-white text-[11px] font-medium rounded-lg transition-colors">Deactivate</button>
                                    <?php elseif ($cdRow['status'] === 'inactive'): ?>
                                        <button type="button" data-cd-status="active" data-cd-code="<?= e($cdRow['code']) ?>" class="px-2.5 py-1 bg-[#f3bd35]/15 hover:bg-[#f3bd35] hover:text-black border border-[#f3bd35]/40 text-[#ffda72] text-[11px] font-bold rounded-lg transition-colors">Activate</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="px-4 py-8 text-center text-xs text-zinc-500"><?= $cdFiltered ? 'No codes match these filters.' : 'No activation codes yet.' ?></p>
        <?php endif; ?>
    </div>
    <?php if ($cdPages > 1): ?>
        <nav class="flex items-center justify-center gap-3 text-xs" aria-label="Pages">
            <?php if ($cdPage > 1): ?>
                <a href="<?= e($cdPageUrl($cdPage - 1)) ?>" class="px-3 py-1.5 rounded-xl bg-zinc-900 hover:bg-zinc-800 border border-zinc-700 text-zinc-200 transition-colors">← Previous</a>
            <?php endif; ?>
            <span class="text-zinc-400">Page <?= $cdPage ?> of <?= $cdPages ?></span>
            <?php if ($cdPage < $cdPages): ?>
                <a href="<?= e($cdPageUrl($cdPage + 1)) ?>" class="px-3 py-1.5 rounded-xl bg-zinc-900 hover:bg-zinc-800 border border-zinc-700 text-zinc-200 transition-colors">Next →</a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
</div>
