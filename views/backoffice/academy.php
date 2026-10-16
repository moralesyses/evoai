<?php
/**
 * Backoffice tab 11 — Mentor Academy (includes/academy.php,
 * assets/js/views/bo-academy.js): the learning materials admins publish in
 * Management Admin > Mentor Academy, by category. Members filter by
 * category, kind and progress, search by title, open a material in the
 * viewer (video, PDF, audio, picture, article, web link or a download) and
 * mark it completed; their progress shows per category and overall.
 *
 * Uses: $user, $pageData.
 */
$acLib = academy_library($user['id']);
$acKindIcon = ['video' => 'video', 'document' => 'file-text', 'audio' => 'headphones', 'article' => 'newspaper', 'link' => 'link'];
$acKinds = array_values(array_unique(array_merge(...array_map(fn ($c) => array_column($c['materials'], 'kind'), $acLib['categories'] ?: [['materials' => []]]))));
$acPct = $acLib['total'] ? (int) round($acLib['completed'] * 100 / $acLib['total']) : 0;
$pageData['academy'] = [
    'categories' => array_map(fn ($c) => ['id' => $c['id'], 'name' => $c['name']], $acLib['categories']),
    'materials' => array_merge(...array_map(fn ($c) => $c['materials'], $acLib['categories'] ?: [['materials' => []]])),
];
/** The little line under a card's title: length, file type and size. */
$acMeta = static function (array $m): string {
    $parts = [];
    if ($m['minutes']) {
        $parts[] = $m['minutes'] . ' min';
    }
    if ($m['file']) {
        $parts[] = strtoupper($m['file']['ext']) . ' · ' . $m['file']['sizeText'];
    } elseif ($m['kind'] === 'video') {
        $parts[] = str_contains($m['embed'], 'vimeo') ? 'Vimeo' : 'YouTube';
    } elseif ($m['kind'] === 'link') {
        $parts[] = (string) parse_url($m['url'], PHP_URL_HOST);
    }
    return implode(' · ', $parts);
};
?>
<div class="space-y-5" id="bo-academy">
    <div class="p-5 rounded-3xl bg-gradient-to-r from-[#16140d] via-[#101010] to-[#0d0d0d] border border-[#f3bd35]/30 shadow-xl flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="min-w-0">
            <h2 class="text-xl font-black text-white flex items-center gap-2"><?= icon('graduation-cap', 'w-5 h-5 text-[#f3bd35]') ?>Mentor Academy</h2>
            <p class="text-xs text-zinc-400 mt-1">Training from the 88MMM Circle mentors: videos, guides, slides, audio lessons and articles. Choose a category, open a lesson, and mark it completed when you finish.</p>
        </div>
        <?php if ($acLib['total']): ?>
            <div class="w-full md:w-72 shrink-0 p-4 rounded-2xl bg-black/40 border border-zinc-800">
                <div class="flex items-center justify-between text-[11px] text-zinc-400"><span>Your progress</span><span class="font-mono text-[#ffda72] font-bold" data-ac-pct><?= $acPct ?>%</span></div>
                <div class="evo-ac-bar mt-2"><span data-ac-bar style="width:<?= $acPct ?>%"></span></div>
                <div class="text-[11px] text-zinc-300 mt-2"><strong class="text-white" data-ac-done><?= $acLib['completed'] ?></strong> of <?= $acLib['total'] ?> lessons completed</div>
            </div>
        <?php endif; ?>
    </div>

    <?php if (!$acLib['total']): ?>
        <div class="py-8 px-6 rounded-3xl bg-[#121212] border border-zinc-800 text-center">
            <div class="w-12 h-12 mx-auto rounded-2xl bg-[#f3bd35]/10 text-[#f3bd35] flex items-center justify-center"><?= icon('book-open', 'w-6 h-6') ?></div>
            <h3 class="text-sm font-bold text-white mt-3">No lessons yet</h3>
            <p class="text-xs text-zinc-400 mt-1">Your mentors are preparing the first materials. Check back soon.</p>
        </div>
    <?php else: ?>
        <?php if ($acLib['resume']): $r = $acLib['resume']; ?>
            <div class="p-4 rounded-2xl bg-[#121212] border border-[#f3bd35]/25 flex flex-col sm:flex-row sm:items-center justify-between gap-3" data-ac-resume>
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-10 h-10 rounded-xl bg-[#f3bd35]/10 text-[#f3bd35] flex items-center justify-center shrink-0"><?= icon('play', 'w-4 h-4') ?></div>
                    <div class="min-w-0">
                        <span class="block text-[11px] text-zinc-400">Pick up where you left off</span>
                        <span class="block text-sm font-bold text-white truncate"><?= e($r['title']) ?></span>
                    </div>
                </div>
                <button type="button" data-ac-open="<?= e($r['id']) ?>" class="px-4 py-2 rounded-xl text-xs font-bold bg-[#f3bd35] hover:bg-[#ffda72] text-black shrink-0 flex items-center justify-center gap-1.5"><?= icon('play', 'w-3.5 h-3.5') ?><span>Continue</span></button>
            </div>
        <?php endif; ?>

        <div class="p-4 rounded-2xl bg-[#121212] border border-zinc-800 space-y-3">
            <div class="flex items-center gap-1.5 overflow-x-auto pb-1" role="group" aria-label="Categories">
                <button type="button" data-ac-cat="" class="evo-chip is-on" aria-pressed="true">All lessons<span class="evo-chip-count"><?= $acLib['total'] ?></span></button>
                <?php foreach ($acLib['categories'] as $c): ?>
                    <button type="button" data-ac-cat="<?= e($c['id']) ?>" class="evo-chip" aria-pressed="false"><?= e($c['name']) ?><span class="evo-chip-count"><?= count($c['materials']) ?></span></button>
                <?php endforeach; ?>
            </div>
            <div class="flex flex-col sm:flex-row gap-2">
                <div class="relative flex-1">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-zinc-500 pointer-events-none"><?= icon('search', 'w-3.5 h-3.5') ?></span>
                    <input type="search" data-ac-search placeholder="Search lessons" aria-label="Search lessons" class="w-full pl-8 pr-3 py-2 rounded-xl bg-zinc-900 border border-zinc-700 text-xs text-white focus:outline-none focus:border-[#f3bd35]">
                </div>
                <select data-ac-kind aria-label="Kind of lesson" class="px-3 py-2 rounded-xl bg-zinc-900 border border-zinc-700 text-xs text-zinc-200 focus:outline-none focus:border-[#f3bd35]">
                    <option value="">All kinds</option>
                    <?php foreach (['video' => 'Videos', 'document' => 'Documents', 'audio' => 'Audio', 'article' => 'Articles', 'link' => 'Web links'] as $k => $label): if (!in_array($k, $acKinds, true)) { continue; } ?><option value="<?= e($k) ?>"><?= e($label) ?></option><?php endforeach; ?>
                </select>
                <select data-ac-state aria-label="Progress" class="px-3 py-2 rounded-xl bg-zinc-900 border border-zinc-700 text-xs text-zinc-200 focus:outline-none focus:border-[#f3bd35]">
                    <option value="">Any progress</option>
                    <option value="todo">Not completed</option>
                    <option value="done">Completed</option>
                </select>
            </div>
        </div>

        <?php foreach ($acLib['categories'] as $c): $cTotal = count($c['materials']); ?>
            <section data-ac-section="<?= e($c['id']) ?>" class="space-y-3" aria-labelledby="ac-sec-<?= e($c['id']) ?>">
                <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-2">
                    <div class="min-w-0">
                        <h3 id="ac-sec-<?= e($c['id']) ?>" class="text-sm font-bold text-white"><?= e($c['name']) ?></h3>
                        <?php if ($c['description'] !== ''): ?><p class="text-[11px] text-zinc-400"><?= e($c['description']) ?></p><?php endif; ?>
                    </div>
                    <div class="w-full sm:w-72 shrink-0">
                        <div class="text-[10px] text-zinc-400"><span data-ac-sec-done><?= $c['completed'] ?></span> of <?= $cTotal ?> completed</div>
                        <div class="evo-ac-bar mt-1"><span data-ac-sec-bar style="width:<?= (int) round($c['completed'] * 100 / $cTotal) ?>%"></span></div>
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php foreach ($c['materials'] as $m): $done = $m['completedAt'] !== null; ?>
                        <button type="button" data-ac-open="<?= e($m['id']) ?>" data-ac-card data-kind="<?= e($m['kind']) ?>" data-done="<?= $done ? '1' : '0' ?>" data-text="<?= e(mb_strtolower($m['title'] . ' ' . $m['description'])) ?>" class="group text-left rounded-2xl bg-[#121212] border border-zinc-800 hover:border-[#f3bd35]/50 overflow-hidden flex flex-col transition-colors">
                            <div class="relative aspect-video w-full bg-zinc-950 overflow-hidden">
                                <?php if ($m['cover'] !== ''): ?>
                                    <img src="<?= e($m['cover']) ?>" alt="" loading="lazy" class="w-full h-full object-cover transition-all duration-500 group-hover:scale-105">
                                <?php else: ?>
                                    <div class="w-full h-full bg-gradient-to-br from-[#16140d] via-[#121212] to-[#0d0d0d] flex items-center justify-center text-[#f3bd35]"><?= icon($acKindIcon[$m['kind']], 'w-10 h-10') ?></div>
                                <?php endif; ?>
                                <?php if ($m['kind'] === 'video'): ?><span class="absolute inset-0 flex items-center justify-center pointer-events-none"><span class="w-12 h-12 rounded-full bg-black/60 text-white flex items-center justify-center"><?= icon('play', 'w-5 h-5') ?></span></span><?php endif; ?>
                                <span class="absolute top-3 left-3 text-[9px] px-2 py-0.5 rounded-full bg-black/70 text-zinc-100 font-bold uppercase tracking-wider flex items-center gap-1"><?= icon($acKindIcon[$m['kind']], 'w-3 h-3') ?><?= e($m['kindLabel']) ?></span>
                                <span data-ac-done-badge class="absolute top-3 right-3 text-[9px] px-2 py-0.5 rounded-full bg-emerald-500 text-black font-bold uppercase flex items-center gap-1"<?= $done ? '' : ' hidden' ?>><?= icon('check', 'w-3 h-3') ?>Done</span>
                                <?php if ($m['isNew'] && !$done): ?><span data-ac-new class="absolute top-3 right-3 text-[9px] px-2 py-0.5 rounded-full bg-[#f3bd35] text-black font-bold uppercase flex items-center gap-1"><?= icon('sparkles', 'w-3 h-3') ?>New</span><?php endif; ?>
                            </div>
                            <div class="p-4 flex flex-col gap-1 flex-1">
                                <span class="text-sm font-bold text-white line-clamp-2"><?= e($m['title']) ?></span>
                                <?php if ($m['description'] !== ''): ?><span class="text-[11px] text-zinc-400 line-clamp-2"><?= e($m['description']) ?></span><?php endif; ?>
                                <?php $meta = $acMeta($m); if ($meta !== ''): ?><span class="mt-auto pt-2 text-[10px] text-zinc-500 flex items-center gap-1.5"><?= $m['minutes'] ? icon('clock', 'w-3 h-3') : '' ?><?= e($meta) ?></span><?php endif; ?>
                            </div>
                        </button>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>

        <div data-ac-empty hidden class="py-8 px-6 rounded-3xl bg-[#121212] border border-zinc-800 text-center">
            <p class="text-xs text-zinc-400">No lessons match.</p>
            <button type="button" data-ac-reset class="mt-3 px-4 py-2 rounded-xl text-xs font-bold bg-zinc-900 hover:bg-zinc-800 border border-zinc-700 text-zinc-200">Show all lessons</button>
        </div>
    <?php endif; ?>

    <div data-modal="academy-viewer" hidden class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4" role="dialog" aria-modal="true" aria-labelledby="ac-viewer-title">
        <div class="w-full max-w-4xl bg-[#141414] border border-[#f3bd35]/40 rounded-3xl shadow-2xl text-xs max-h-[92vh] overflow-y-auto">
            <div class="flex items-start justify-between gap-3 px-5 py-4 border-b border-zinc-800">
                <div class="min-w-0">
                    <span data-ac-v-meta class="block text-[10px] font-bold tracking-wider text-[#f3bd35] uppercase"></span>
                    <h4 id="ac-viewer-title" data-ac-v-title class="text-base font-bold text-white mt-0.5"></h4>
                </div>
                <button type="button" data-close-modal class="text-zinc-400 hover:text-white shrink-0" aria-label="Close"><?= icon('x', 'w-5 h-5') ?></button>
            </div>
            <div class="p-5 space-y-4">
                <div data-ac-v-content></div>
                <p data-ac-v-desc class="text-xs text-zinc-300 whitespace-pre-line"></p>
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pt-4 border-t border-zinc-800">
                    <div class="flex items-center gap-2">
                        <button type="button" data-ac-step="-1" class="px-3 py-2 rounded-xl text-xs font-semibold bg-zinc-900 hover:bg-zinc-800 border border-zinc-700 text-zinc-200 flex items-center gap-1 disabled:opacity-40"><?= icon('arrow-left', 'w-3.5 h-3.5') ?><span>Previous</span></button>
                        <button type="button" data-ac-step="1" class="px-3 py-2 rounded-xl text-xs font-semibold bg-zinc-900 hover:bg-zinc-800 border border-zinc-700 text-zinc-200 flex items-center gap-1 disabled:opacity-40"><span>Next</span><?= icon('arrow-right', 'w-3.5 h-3.5') ?></button>
                    </div>
                    <button type="button" data-ac-complete class="px-5 py-2.5 rounded-xl text-xs font-extrabold flex items-center justify-center gap-1.5 disabled:opacity-60"></button>
                </div>
            </div>
        </div>
    </div>
</div>
