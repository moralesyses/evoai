<?php
/**
 * Backoffice tab 9 — My Website (includes/website.php,
 * assets/js/views/bo-website.js): once the member owns an active .com
 * domain, they build a one-page website from sections with a live preview,
 * and publish it to their domain. Without a domain the tab explains how to
 * get one.
 *
 * Uses: $user, $pageData.
 */
$wb = site_builder_state($user);
$wbBtn = 'flex items-center justify-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-bold transition-colors';
$wbPill = 'text-[10px] px-2 py-0.5 rounded-full font-bold uppercase tracking-wider border';
// Delete for good: in both states (a member whose domain lapsed can still clear a saved site); not when an admin views the member.
$wbCanDelete = empty($boViewing) && site_row($user['id']) !== null;
$wbDelete = '<button type="button" data-wb-delete class="text-[11px] text-rose-400 hover:text-rose-300 flex items-center gap-1">' . icon('trash-2', 'w-3 h-3') . '<span>Delete my website</span></button>';
if ($wb['unlocked']) {
    $pageData['website'] = $wb;
}
?>
<div id="bo-website" class="space-y-5">
    <?php if (!$wb['unlocked']): ?>
        <div class="p-6 rounded-3xl bg-[#111111] border border-[#f3bd35]/25 text-center space-y-4 max-w-2xl mx-auto">
            <div class="w-14 h-14 rounded-full bg-[#f3bd35]/15 border border-[#f3bd35]/40 text-[#f3bd35] flex items-center justify-center mx-auto"><?= icon('monitor', 'w-7 h-7') ?></div>
            <h2 class="text-xl font-black text-white">Build your own website</h2>
            <p class="text-xs text-zinc-400 leading-relaxed">Your website builder comes with your own .com domain. Buy one in <strong class="text-zinc-200">Digital Card CMS → Your .com Domain</strong>, then come back here: pick a theme, add sections about you and your services, and publish it at yourname.com.</p>
            <button type="button" data-tab="digital_card" data-group="bo" class="<?= $wbBtn ?> mx-auto bg-[#f3bd35] hover:bg-[#ffda72] text-black"><?= icon('globe', 'w-3.5 h-3.5') ?><span>Get my .com domain</span></button>
            <?php if ($wbCanDelete): ?>
                <div class="pt-3 border-t border-zinc-800 flex flex-col items-center gap-2">
                    <p class="text-[11px] text-zinc-500">Your saved website is kept, offline, until you have a domain again.</p>
                    <?= $wbDelete ?>
                </div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="p-5 rounded-3xl bg-gradient-to-r from-[#16140d] via-[#101010] to-[#0d0d0d] border border-[#f3bd35]/30 shadow-xl flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="text-xl font-black text-white flex items-center gap-2"><?= icon('monitor', 'w-5 h-5 text-[#f3bd35]') ?>My Website</h2>
                    <?php if ($wb['status'] === 'suspended'): ?>
                        <span class="<?= $wbPill ?> bg-rose-500/10 border-rose-500/30 text-rose-300">Taken offline</span>
                    <?php elseif ($wb['published']): ?>
                        <span class="<?= $wbPill ?> bg-emerald-500/10 border-emerald-500/30 text-emerald-300">Live</span>
                    <?php else: ?>
                        <span class="<?= $wbPill ?> bg-zinc-900 border-zinc-700 text-zinc-400">Draft</span>
                    <?php endif; ?>
                    <span class="text-[11px] text-zinc-500" data-wb-saved role="status"></span>
                </div>
                <p class="text-xs text-zinc-400 mt-1">
                    <?php if ($wb['published']): ?>
                        Visitors of <?php if ($wb['liveUrl']): ?><a class="text-[#ffda72] hover:text-white break-all" href="<?= e($wb['liveUrl']) ?>" target="_blank" rel="noopener"><?= e($wb['liveUrl']) ?></a><?php else: ?>your domain<?php endif; ?> see this website<?= $wb['views'] ? ' (' . number_format($wb['views']) . ' visits)' : '' ?>; your card is at <span class="font-mono">?view=card</span>. Changes are saved as a draft until you publish again.
                    <?php else: ?>
                        Changes save automatically as a draft. Publish to show your website at <?= $wb['liveUrl'] ? '<span class="text-zinc-200 break-all">' . e($wb['liveUrl']) . '</span>' : 'your domain' ?> instead of your digital card.
                    <?php endif; ?>
                </p>
                <p class="text-[10px] text-zinc-500 mt-1">On this computer it is also at <a class="text-zinc-400 hover:text-white break-all" href="<?= e($wb['portalUrl']) ?>" target="_blank" rel="noopener"><?= e($wb['portalUrl']) ?></a><?= $wb['published'] ? '' : ' once published' ?>.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="<?= e($wb['previewUrl']) ?>" target="_blank" rel="noopener" class="<?= $wbBtn ?> bg-zinc-900 hover:bg-zinc-800 border border-zinc-700 text-zinc-200"><?= icon('eye', 'w-3.5 h-3.5 text-[#f3bd35]') ?><span>Preview</span></a>
                <?php if ($wb['published']): ?>
                    <button type="button" data-wb-unpublish class="<?= $wbBtn ?> bg-zinc-900 hover:bg-rose-500/10 border border-rose-500/40 text-rose-300"><?= icon('eye-off', 'w-3.5 h-3.5') ?><span>Take down website</span></button>
                <?php endif; ?>
                <button type="button" data-wb-publish class="<?= $wbBtn ?> bg-gradient-to-r from-[#ffda72] to-[#f3bd35] text-black shadow-md hover:brightness-105 disabled:opacity-50"<?= $wb['status'] === 'suspended' ? ' disabled' : '' ?>><?= icon('send', 'w-3.5 h-3.5') ?><span><?= $wb['published'] ? 'Publish changes' : 'Publish website' ?></span></button>
            </div>
        </div>
        <?php if ($wb['status'] === 'suspended'): ?>
            <div class="p-3 rounded-2xl bg-rose-500/10 border border-rose-500/30 text-xs text-rose-200" role="alert">An administrator took your website offline<?= $wb['adminNote'] !== '' ? ': ' . e($wb['adminNote']) : '' ?>. Your domain shows your digital card. Contact the 88MMM administrator to restore it.</div>
        <?php elseif ($wb['changed']): ?>
            <div class="p-3 rounded-2xl bg-amber-950/40 border border-amber-600/40 text-xs text-amber-200" data-wb-changed>Your draft has changes that aren't live yet. Press <strong>Publish changes</strong> to show them.</div>
        <?php endif; ?>

        <div class="evo-wb">
            <div class="evo-wb-editor space-y-4">
                <details class="evo-wb-panel" open>
                    <summary>Site settings</summary>
                    <div class="evo-wb-body" data-wb-settings></div>
                </details>
                <div class="evo-wb-panel">
                    <div class="evo-wb-head">
                        <span>Sections</span>
                        <span class="text-[11px] text-zinc-500" data-wb-count></span>
                    </div>
                    <div data-wb-sections></div>
                    <div class="evo-wb-add">
                        <label for="wb-add-type" class="sr-only-wb">Section to add</label>
                        <select id="wb-add-type" data-wb-add-type></select>
                        <button type="button" data-wb-add class="<?= $wbBtn ?> bg-[#f3bd35] hover:bg-[#ffda72] text-black disabled:opacity-50"><?= icon('plus', 'w-3.5 h-3.5') ?><span>Add section</span></button>
                    </div>
                </div>
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <button type="button" data-wb-reset class="text-[11px] text-zinc-500 hover:text-white">Start again from my card profile</button>
                    <?php if ($wbCanDelete): ?><?= $wbDelete ?><?php endif; ?>
                </div>
            </div>
            <div class="evo-wb-preview">
                <div class="evo-wb-preview-bar">
                    <span>Live preview</span>
                    <div class="flex items-center gap-1">
                        <button type="button" data-wb-device="desktop" class="evo-wb-device is-on" aria-pressed="true" title="Desktop"><?= icon('monitor', 'w-3.5 h-3.5') ?></button>
                        <button type="button" data-wb-device="phone" class="evo-wb-device" aria-pressed="false" title="Phone"><?= icon('smartphone', 'w-3.5 h-3.5') ?></button>
                    </div>
                </div>
                <div class="evo-wb-frame-wrap" data-wb-frame-wrap>
                    <iframe data-wb-frame src="<?= e($wb['previewUrl']) ?>" title="Preview of my website"></iframe>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
