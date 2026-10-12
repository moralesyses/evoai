<?php
/**
 * "Sign in required" panel shown in place of members-only views.
 * Expects: $gateTitle, $gateText (optional).
 */
$gateTitle = $gateTitle ?? 'Sign in to continue';
$gateText = $gateText ?? 'This area of the 88MMM Executive Portal is reserved for verified members.';
?>
<div class="max-w-xl mx-auto py-8">
    <div class="relative rounded-3xl bg-[#111111]/95 border border-[#f3bd35]/35 p-6 sm:p-8 shadow-2xl shadow-black/80 text-center">
        <div class="absolute top-0 left-1/4 right-1/4 h-[1px] bg-gradient-to-r from-transparent via-[#f3bd35] to-transparent"></div>
        <div class="w-12 h-12 rounded-full bg-[#f3bd35]/15 text-[#f3bd35] flex items-center justify-center mx-auto mb-3"><?= icon('lock', 'w-5 h-5') ?></div>
        <div class="text-[11px] font-bold tracking-wider text-[#f3bd35] uppercase mb-1">Members Only · E2EE Secured</div>
        <h2 class="text-xl font-bold text-white mb-2"><?= e($gateTitle) ?></h2>
        <p class="text-xs text-zinc-400 max-w-sm mx-auto mb-5 leading-relaxed"><?= e($gateText) ?></p>
        <div class="flex flex-col sm:flex-row items-center justify-center gap-2 max-w-sm mx-auto">
            <a href="<?= e(url('main')) ?>#auth-gateway-card" class="w-full sm:w-auto px-6 py-3 bg-gradient-to-r from-[#ffda72] to-[#f3bd35] text-black font-extrabold text-xs rounded-xl shadow-lg hover:brightness-105 transition-all flex items-center justify-center gap-2"><?= icon('key', 'w-3.5 h-3.5') ?><span>Executive Sign In</span></a>
            <a href="<?= e(url('card')) ?>" class="w-full sm:w-auto px-6 py-3 bg-zinc-900 hover:bg-zinc-800 border border-zinc-700 text-white font-bold text-xs rounded-xl transition-all flex items-center justify-center gap-2"><?= icon('credit-card', 'w-3.5 h-3.5 text-[#f3bd35]') ?><span>View Public Card</span></a>
        </div>
    </div>
</div>
