<?php
/**
 * Shown (HTTP 404) when ?card= or ?profile= matches no member card.
 */
?>
<div class="max-w-xl mx-auto px-4 py-8">
    <div class="relative rounded-3xl bg-gradient-to-b from-[#141414] via-[#0d0d0d] to-[#070707] border border-[#f3bd35]/30 p-6 sm:p-8 shadow-2xl text-center overflow-hidden">
        <div class="absolute -top-20 left-1/2 -translate-x-1/2 w-64 h-64 bg-[#f3bd35]/15 rounded-full blur-3xl pointer-events-none"></div>
        <div class="relative w-12 h-12 rounded-full bg-[#f3bd35]/15 text-[#f3bd35] flex items-center justify-center mx-auto mb-3"><?= icon('credit-card', 'w-5 h-5') ?></div>
        <h1 class="relative text-xl font-bold text-white mb-2">Card not found</h1>
        <p class="relative text-xs text-zinc-400 max-w-sm mx-auto mb-5 leading-relaxed">There is no digital card at this address.</p>
        <a href="<?= e(url('main')) ?>" class="relative inline-flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-[#ffda72] to-[#f3bd35] text-black font-extrabold text-xs rounded-xl shadow-lg hover:brightness-105 transition-all"><?= icon('arrow-left', 'w-3.5 h-3.5') ?><span>Back to Main Portal</span></a>
    </div>
</div>
