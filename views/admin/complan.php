<?php
/**
 * Management Admin > Binary Complan (superadmin only): the bonuses and pair
 * limits used by every downline placement and pairing match (includes/mlm.php,
 * mlm_complan()). Saved through api/actions/admin_complan.php
 * (assets/js/views/admin-complan.js).
 */
$cpPlan = mlm_complan();
$cpInput = 'w-full px-3 py-2 rounded-xl bg-zinc-900 border border-zinc-700 text-sm text-white font-mono focus:outline-none focus:border-[#f3bd35]';
$cpLabel = 'block text-[11px] font-semibold text-zinc-300 mb-1';
$cpError = 'block text-[10px] text-rose-400 mt-1';
$cpHint = 'block text-[10px] text-zinc-500 mt-1';
$cpDefaults = MLM_COMPLAN_DEFAULTS;
?>
<div class="space-y-4" id="admin-complan" data-defaults="<?= e(json_encode($cpDefaults)) ?>">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <section class="lg:col-span-2 p-5 rounded-3xl bg-[#111111] border border-[#f3bd35]/20 space-y-4" aria-labelledby="cp-title">
            <div>
                <h3 id="cp-title" class="text-sm font-bold text-white flex items-center gap-2"><?= icon('git-fork', 'w-4 h-4 text-[#f3bd35]') ?>Binary Compensation Plan</h3>
                <p class="text-xs text-zinc-400 mt-0.5">The bonuses and pair limits used for every downline placement and pairing match from now on. Bonuses already paid stay as they were.</p>
            </div>
            <form id="cp-form" class="space-y-4 text-xs" novalidate>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label for="cp-direct" class="<?= $cpLabel ?>">Direct Referral Bonus (₱)</label>
                        <input id="cp-direct" name="directReferralBonus" type="number" min="0" max="1000000" step="1" required value="<?= (int) $cpPlan['directReferralBonus'] ?>" class="<?= $cpInput ?>">
                        <span class="<?= $cpHint ?>">Paid to the sponsor for each new downline.</span>
                        <span data-cp-error="directReferralBonus" hidden class="<?= $cpError ?>"></span>
                    </div>
                    <div>
                        <label for="cp-pairing" class="<?= $cpLabel ?>">Pairing Bonus (₱ per pair)</label>
                        <input id="cp-pairing" name="pairingBonus" type="number" min="0" max="1000000" step="1" required value="<?= (int) $cpPlan['pairingBonus'] ?>" class="<?= $cpInput ?>">
                        <span class="<?= $cpHint ?>">Paid for each 1 Left : 1 Right match.</span>
                        <span data-cp-error="pairingBonus" hidden class="<?= $cpError ?>"></span>
                    </div>
                    <div>
                        <label for="cp-pairs" class="<?= $cpLabel ?>">Pairs per Cycle</label>
                        <input id="cp-pairs" name="maxPairsPerCycle" type="number" min="1" max="1000" step="1" required value="<?= (int) $cpPlan['maxPairsPerCycle'] ?>" class="<?= $cpInput ?>">
                        <span class="<?= $cpHint ?>">The safety net: matches past this wait for the next cycle.</span>
                        <span data-cp-error="maxPairsPerCycle" hidden class="<?= $cpError ?>"></span>
                    </div>
                    <div>
                        <label for="cp-cycles" class="<?= $cpLabel ?>">Cycles per Day</label>
                        <select id="cp-cycles" name="cyclesPerDay" class="<?= str_replace(' font-mono', '', $cpInput) ?>">
                            <option value="2"<?= $cpPlan['cyclesPerDay'] === 2 ? ' selected' : '' ?>>2 cycles (AM and PM)</option>
                            <option value="1"<?= $cpPlan['cyclesPerDay'] === 1 ? ' selected' : '' ?>>1 cycle (whole day)</option>
                        </select>
                        <span class="<?= $cpHint ?>">2 cycles: 12:00 AM – 11:59 AM and 12:00 PM – 11:59 PM. Counts reset at midnight.</span>
                        <span data-cp-error="cyclesPerDay" hidden class="<?= $cpError ?>"></span>
                    </div>
                </div>
                <p class="text-[11px] text-zinc-400">Package entry stays at <strong class="text-zinc-200">₱<?= num($cpPlan['entryFee']) ?></strong> per activation code.</p>
                <p data-cp-msg hidden role="alert" class="p-3 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-300"></p>
                <div class="flex flex-col sm:flex-row sm:items-center gap-2 pt-1">
                    <button type="submit" class="px-5 py-2 bg-[#f3bd35] hover:bg-[#ffda72] text-black text-xs font-extrabold rounded-xl shadow-md transition-colors flex items-center justify-center gap-1.5 disabled:opacity-60"><?= icon('save', 'w-3.5 h-3.5') ?><span>Save Plan</span></button>
                    <button type="button" data-cp-standard class="px-4 py-2 bg-zinc-900 hover:bg-zinc-800 border border-zinc-700 text-zinc-200 text-xs font-semibold rounded-xl transition-colors">Fill in the standard plan (₱<?= num($cpDefaults['directReferralBonus']) ?> · ₱<?= num($cpDefaults['pairingBonus']) ?> · <?= $cpDefaults['maxPairsPerCycle'] ?> × <?= $cpDefaults['cyclesPerDay'] ?>)</button>
                </div>
            </form>
        </section>

        <aside class="p-5 rounded-3xl bg-gradient-to-b from-[#18150c] to-[#0f0d07] border border-[#f3bd35]/40 space-y-4" aria-labelledby="cp-summary-title" aria-live="polite">
            <h3 id="cp-summary-title" class="text-[11px] font-bold uppercase tracking-wider text-[#f3bd35]">Plan at a glance</h3>
            <div>
                <span class="text-[11px] text-zinc-400 block">Pairs per Day</span>
                <span class="text-3xl font-black text-white tabular-nums" data-cp-out="pairsPerDay"><?= $cpPlan['pairsPerDay'] ?></span>
                <span class="text-xs text-zinc-400" data-cp-out="pairsFormula">(<?= $cpPlan['maxPairsPerCycle'] ?> pairs × <?= $cpPlan['cyclesPerDay'] ?> cycle<?= $cpPlan['cyclesPerDay'] === 1 ? '' : 's' ?>)</span>
            </div>
            <dl class="space-y-2 text-xs">
                <div class="flex items-center justify-between gap-3"><dt class="text-zinc-400">Direct referral</dt><dd class="font-bold text-emerald-400 tabular-nums" data-cp-out="direct">₱<?= num($cpPlan['directReferralBonus']) ?></dd></div>
                <div class="flex items-center justify-between gap-3"><dt class="text-zinc-400">Pairing bonus</dt><dd class="font-bold text-[#ffda72] tabular-nums" data-cp-out="pairing">₱<?= num($cpPlan['pairingBonus']) ?> per pair</dd></div>
                <div class="flex items-center justify-between gap-3"><dt class="text-zinc-400">Max pairing per cycle</dt><dd class="font-bold text-zinc-200 tabular-nums" data-cp-out="perCycle">₱<?= num($cpPlan['maxCyclePairingIncome']) ?></dd></div>
                <div class="flex items-center justify-between gap-3 pt-2 border-t border-zinc-800"><dt class="text-zinc-300 font-semibold">Max daily pairing income</dt><dd class="font-black text-[#f3bd35] tabular-nums" data-cp-out="perDay">₱<?= num($cpPlan['maxDailyPairingIncome']) ?></dd></div>
            </dl>
            <p class="text-[10px] text-zinc-500">Now: <?= e(mlm_cycle_label(mlm_current_cycle())) ?>. Pair counts reset every midnight.</p>
        </aside>
    </div>
</div>
