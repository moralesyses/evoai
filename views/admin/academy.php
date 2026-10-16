<?php
/**
 * Management Admin > Mentor Academy (includes/academy.php,
 * assets/js/views/admin-academy.js, api/actions/academy.php): the learning
 * materials members see in Backoffice > Mentor Academy. Categories: add,
 * rename, reorder, and delete once empty. Materials: add and edit them (file
 * upload with a progress bar, YouTube / Vimeo link, article text or web
 * link), publish or hide them, delete them, and see how many members opened
 * and completed each. The list filters by category and searches by title.
 */
$acCats = academy_categories();
$acItems = academy_admin_materials();
$acStats = academy_stats();
$acLimit = academy_upload_limit();
$acCatName = array_column($acCats, 'name', 'id');
$acKindIcon = ['video' => 'video', 'document' => 'file-text', 'audio' => 'headphones', 'article' => 'newspaper', 'link' => 'link'];
$pageData['adminAcademy'] = [
    'materials' => $acItems,
    'categories' => array_map(fn ($c) => ['id' => $c['id'], 'name' => $c['name'], 'description' => $c['description'], 'materials' => $c['materials']], $acCats),
    'limit' => $acLimit, 'limitText' => academy_size($acLimit),
    'extensions' => ['video' => academy_extensions('video'), 'document' => academy_extensions('document'), 'audio' => academy_extensions('audio')],
];
$acInput = 'w-full px-3 py-2 rounded-xl bg-zinc-900 border border-zinc-700 text-xs text-white focus:outline-none focus:border-[#f3bd35]';
$acLabel = 'block text-[11px] font-semibold text-zinc-300 mb-1';
$acSmall = 'px-2.5 py-1 bg-zinc-900 hover:bg-zinc-800 border border-zinc-700 text-zinc-300 hover:text-white text-[11px] font-medium rounded-lg transition-colors flex items-center gap-1 disabled:opacity-40';
$acStat = 'p-4 rounded-2xl bg-[#121212] border border-zinc-800';
?>
<div class="space-y-4" id="admin-academy">
    <div class="p-5 rounded-3xl bg-[#111111] border border-[#f3bd35]/20 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="min-w-0">
            <h3 class="text-sm font-bold text-white flex items-center gap-2"><?= icon('graduation-cap', 'w-4 h-4 text-[#f3bd35]') ?>Mentor Academy</h3>
            <p class="text-xs text-zinc-400 mt-0.5">The learning library members open in <strong class="text-zinc-200">Backoffice → 11. Mentor Academy</strong>. Upload videos, documents, slides and audio, write articles or add links, and sort them into categories. Members filter by category, open each material and mark it completed.</p>
            <p class="text-[11px] text-zinc-500 mt-1">Files up to <?= e(academy_size($acLimit)) ?> on this server. For longer videos, upload them to YouTube (as Unlisted) or Vimeo and paste the link.</p>
        </div>
        <div class="flex items-center gap-2 shrink-0">
            <button type="button" data-ac-cat-add class="px-4 py-2 bg-zinc-900 hover:bg-zinc-800 border border-zinc-700 text-zinc-200 text-xs font-bold rounded-xl transition-colors flex items-center justify-center gap-1.5"><?= icon('layers', 'w-3.5 h-3.5') ?><span>Add category</span></button>
            <button type="button" data-ac-add class="px-5 py-2 bg-[#f3bd35] hover:bg-[#ffda72] text-black text-xs font-extrabold rounded-xl shadow-md transition-colors flex items-center justify-center gap-1.5 disabled:opacity-50"<?= $acCats ? '' : ' disabled title="Add a category first."' ?>><?= icon('plus', 'w-3.5 h-3.5') ?><span>Add material</span></button>
        </div>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <div class="<?= $acStat ?>"><span class="text-[11px] text-zinc-400">Materials</span><div class="text-xl font-black text-white font-mono mt-1"><?= number_format($acStats['materials']) ?></div><span class="text-[10px] text-zinc-500"><?= number_format($acStats['published']) ?> published<?= $acStats['materials'] - $acStats['published'] ? ', ' . number_format($acStats['materials'] - $acStats['published']) . ' hidden' : '' ?></span></div>
        <div class="<?= $acStat ?>"><span class="text-[11px] text-zinc-400">Categories</span><div class="text-xl font-black text-white font-mono mt-1"><?= number_format($acStats['categories']) ?></div></div>
        <div class="<?= $acStat ?>"><span class="text-[11px] text-zinc-400">Members learning</span><div class="text-xl font-black text-[#ffda72] font-mono mt-1"><?= number_format($acStats['learners']) ?></div><span class="text-[10px] text-zinc-500">opened at least one material</span></div>
        <div class="<?= $acStat ?>"><span class="text-[11px] text-zinc-400">Completions</span><div class="text-xl font-black text-emerald-400 font-mono mt-1"><?= number_format($acStats['completions']) ?></div><span class="text-[10px] text-zinc-500">materials marked completed</span></div>
    </div>

    <section class="rounded-3xl bg-[#121212] border border-zinc-800 overflow-hidden" aria-labelledby="ac-cats-title">
        <div class="px-5 py-4 border-b border-zinc-800">
            <h4 id="ac-cats-title" class="text-sm font-bold text-white flex items-center gap-2"><?= icon('layers', 'w-4 h-4 text-[#f3bd35]') ?>Categories</h4>
            <p class="text-[11px] text-zinc-400 mt-0.5">Members see them in this order; a category shows to members once it has a published material.</p>
        </div>
        <?php if (!$acCats): ?>
            <p class="p-6 text-center text-xs text-zinc-400">No categories yet. Press <strong class="text-zinc-200">Add category</strong> to make the first one.</p>
        <?php else: ?>
            <ul class="divide-y divide-zinc-800/80">
                <?php foreach ($acCats as $i => $c): ?>
                    <li data-ac-cat-row="<?= e($c['id']) ?>" class="px-5 py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div class="min-w-0">
                            <span class="block text-xs font-bold text-white"><?= e($c['name']) ?></span>
                            <?php if ($c['description'] !== ''): ?><span class="block text-[11px] text-zinc-400"><?= e($c['description']) ?></span><?php endif; ?>
                            <span class="block text-[10px] text-zinc-500 mt-0.5"><?= $c['materials'] ?> material<?= $c['materials'] === 1 ? '' : 's' ?><?= $c['materials'] ? ' · ' . $c['published'] . ' published' : '' ?></span>
                        </div>
                        <div class="flex items-center gap-1.5 shrink-0">
                            <button type="button" data-ac-cat-move="<?= e($c['id']) ?>" data-dir="-1" class="<?= $acSmall ?>" title="Move up" aria-label="Move <?= e($c['name']) ?> up"<?= $i === 0 ? ' disabled' : '' ?>><?= icon('chevron-up', 'w-3 h-3') ?></button>
                            <button type="button" data-ac-cat-move="<?= e($c['id']) ?>" data-dir="1" class="<?= $acSmall ?>" title="Move down" aria-label="Move <?= e($c['name']) ?> down"<?= $i === count($acCats) - 1 ? ' disabled' : '' ?>><?= icon('chevron-down', 'w-3 h-3') ?></button>
                            <button type="button" data-ac-cat-edit="<?= e($c['id']) ?>" class="<?= $acSmall ?>"><?= icon('pen-line', 'w-3 h-3') ?><span>Rename</span></button>
                            <button type="button" data-ac-cat-delete="<?= e($c['id']) ?>" data-name="<?= e($c['name']) ?>" data-count="<?= $c['materials'] ?>" class="<?= $acSmall ?> hover:text-rose-300" title="Delete" aria-label="Delete <?= e($c['name']) ?>"><?= icon('trash-2', 'w-3 h-3') ?></button>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="rounded-3xl bg-[#121212] border border-zinc-800 overflow-hidden" aria-labelledby="ac-items-title">
        <div class="px-5 py-4 border-b border-zinc-800 space-y-3">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <h4 id="ac-items-title" class="text-sm font-bold text-white flex items-center gap-2"><?= icon('book-open', 'w-4 h-4 text-[#f3bd35]') ?>Learning materials</h4>
                <div class="relative sm:w-72">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-zinc-500 pointer-events-none"><?= icon('search', 'w-3.5 h-3.5') ?></span>
                    <input type="search" data-ac-search placeholder="Search by title" aria-label="Search materials by title" class="<?= $acInput ?> pl-8">
                </div>
            </div>
            <?php if ($acItems): ?>
                <div class="flex items-center gap-1.5 overflow-x-auto pb-1" role="group" aria-label="Filter by category">
                    <button type="button" data-ac-filter="" class="evo-chip is-on" aria-pressed="true">All<span class="evo-chip-count"><?= count($acItems) ?></span></button>
                    <?php foreach ($acCats as $c): if (!$c['materials']) { continue; } ?>
                        <button type="button" data-ac-filter="<?= e($c['id']) ?>" class="evo-chip" aria-pressed="false"><?= e($c['name']) ?><span class="evo-chip-count"><?= $c['materials'] ?></span></button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php if (!$acItems): ?>
            <p class="p-6 text-center text-xs text-zinc-400">No materials yet. Press <strong class="text-zinc-200">Add material</strong> to upload the first one.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-zinc-900/80 border-b border-zinc-800 text-zinc-400 uppercase text-[10px] tracking-wider">
                        <tr>
                            <th class="py-3 px-4">Material</th>
                            <th class="py-3 px-4">Category</th>
                            <th class="py-3 px-4 text-right">Opened</th>
                            <th class="py-3 px-4 text-right">Completed</th>
                            <th class="py-3 px-4">Members</th>
                            <th class="py-3 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800/80">
                        <?php foreach ($acItems as $m): ?>
                            <tr data-ac-row="<?= e($m['id']) ?>" data-cat="<?= e($m['categoryId']) ?>" data-text="<?= e(mb_strtolower($m['title'] . ' ' . ($m['file']['name'] ?? ''))) ?>" class="hover:bg-zinc-900/40 transition-colors<?= $m['published'] ? '' : ' opacity-60' ?>">
                                <td class="py-3 px-4">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <div class="w-16 h-10 rounded-lg bg-zinc-950 border border-zinc-800 overflow-hidden shrink-0 flex items-center justify-center text-zinc-500">
                                            <?php if ($m['cover'] !== ''): ?><img src="<?= e($m['cover']) ?>" alt="" loading="lazy" class="w-full h-full object-cover"><?php else: ?><?= icon($acKindIcon[$m['kind']], 'w-4 h-4') ?><?php endif; ?>
                                        </div>
                                        <div class="min-w-0">
                                            <span class="block font-bold text-white truncate max-w-xs"><?= e($m['title']) ?></span>
                                            <span class="block text-[10px] text-zinc-500"><?= e($m['kindLabel']) ?><?php if ($m['file']): ?> · <?= e(strtoupper($m['file']['ext'])) ?> <?= e($m['file']['sizeText']) ?><?php elseif ($m['kind'] === 'video'): ?> · <?= str_contains($m['embed'], 'vimeo') ? 'Vimeo' : 'YouTube' ?><?php endif; ?><?= $m['minutes'] ? ' · ' . $m['minutes'] . ' min' : '' ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3 px-4 text-zinc-300 whitespace-nowrap"><?= e($acCatName[$m['categoryId']] ?? '') ?></td>
                                <td class="py-3 px-4 text-right font-mono text-zinc-300"><?= number_format($m['opened']) ?></td>
                                <td class="py-3 px-4 text-right font-mono text-emerald-400 font-bold"><?= number_format($m['completed']) ?></td>
                                <td class="py-3 px-4">
                                    <?php if ($m['published']): ?>
                                        <span class="text-[9px] px-2 py-0.5 rounded-full bg-emerald-500/15 text-emerald-400 font-bold uppercase">Published</span>
                                    <?php else: ?>
                                        <span class="text-[9px] px-2 py-0.5 rounded-full bg-zinc-800 text-zinc-400 font-bold uppercase">Hidden</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <?php if ($m['file']): ?><a href="<?= e($m['file']['url']) ?>" target="_blank" rel="noopener" class="<?= $acSmall ?>" title="Open the file" aria-label="Open the file of <?= e($m['title']) ?>"><?= icon('external-link', 'w-3 h-3') ?></a><?php endif; ?>
                                        <button type="button" data-ac-edit="<?= e($m['id']) ?>" class="<?= $acSmall ?>"><?= icon('pen-line', 'w-3 h-3') ?><span>Edit</span></button>
                                        <button type="button" data-ac-publish="<?= e($m['id']) ?>" data-to="<?= $m['published'] ? '0' : '1' ?>" class="<?= $acSmall ?>" title="<?= $m['published'] ? 'Hide from members' : 'Publish to members' ?>"><?= icon($m['published'] ? 'eye-off' : 'eye', 'w-3 h-3') ?><span><?= $m['published'] ? 'Hide' : 'Publish' ?></span></button>
                                        <button type="button" data-ac-delete="<?= e($m['id']) ?>" data-name="<?= e($m['title']) ?>" data-done="<?= (int) $m['completed'] ?>" class="<?= $acSmall ?> hover:text-rose-300" title="Delete" aria-label="Delete <?= e($m['title']) ?>"><?= icon('trash-2', 'w-3 h-3') ?></button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p data-ac-none hidden class="p-6 text-center text-xs text-zinc-400">No materials match.</p>
        <?php endif; ?>
    </section>

    <div data-modal="academy-category" hidden class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4" role="dialog" aria-modal="true" aria-labelledby="ac-cat-title">
        <div class="w-full max-w-md bg-[#141414] border border-[#f3bd35]/40 rounded-3xl p-6 shadow-2xl space-y-4 text-xs">
            <div class="flex items-center justify-between pb-3 border-b border-zinc-800">
                <h4 class="font-bold text-white text-sm" id="ac-cat-title" data-ac-cat-title>Add category</h4>
                <button type="button" data-close-modal class="text-zinc-400 hover:text-white" aria-label="Close"><?= icon('x', 'w-4 h-4') ?></button>
            </div>
            <form id="ac-cat-form" class="space-y-3" novalidate>
                <input type="hidden" name="id" value="">
                <div>
                    <label for="ac-cat-name" class="<?= $acLabel ?>">Name *</label>
                    <input id="ac-cat-name" name="name" type="text" required maxlength="80" placeholder="e.g. Leadership & Mentoring" class="<?= $acInput ?>">
                </div>
                <div>
                    <label for="ac-cat-description" class="<?= $acLabel ?>">Short description</label>
                    <input id="ac-cat-description" name="description" type="text" maxlength="300" placeholder="What members learn here" class="<?= $acInput ?>">
                </div>
                <p data-ac-cat-error hidden role="alert" class="p-3 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-300"></p>
                <button type="submit" class="w-full py-2.5 bg-[#f3bd35] hover:bg-[#ffda72] text-black font-extrabold rounded-xl text-xs flex items-center justify-center gap-1.5 disabled:opacity-60"><?= icon('save', 'w-3.5 h-3.5') ?><span>Save category</span></button>
            </form>
        </div>
    </div>

    <div data-modal="academy-material" hidden class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4" role="dialog" aria-modal="true" aria-labelledby="ac-form-title">
        <div class="w-full max-w-lg bg-[#141414] border border-[#f3bd35]/40 rounded-3xl p-6 shadow-2xl space-y-4 text-xs max-h-[92vh] overflow-y-auto">
            <div class="flex items-center justify-between pb-3 border-b border-zinc-800">
                <h4 class="font-bold text-white text-sm" id="ac-form-title" data-ac-title>Add material</h4>
                <button type="button" data-close-modal class="text-zinc-400 hover:text-white" aria-label="Close"><?= icon('x', 'w-4 h-4') ?></button>
            </div>
            <form id="ac-form" class="space-y-3" novalidate>
                <input type="hidden" name="id" value="">
                <div>
                    <label for="ac-title" class="<?= $acLabel ?>">Title *</label>
                    <input id="ac-title" name="title" type="text" required maxlength="150" placeholder="e.g. Your first 30 days in the Circle" class="<?= $acInput ?>">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="ac-category" class="<?= $acLabel ?>">Category *</label>
                        <select id="ac-category" name="categoryId" required class="<?= $acInput ?>">
                            <?php foreach ($acCats as $c): ?><option value="<?= e($c['id']) ?>"><?= e($c['name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="ac-kind" class="<?= $acLabel ?>">Kind *</label>
                        <select id="ac-kind" name="kind" required class="<?= $acInput ?>">
                            <?php foreach (ACADEMY_KINDS as $k => $label): ?><option value="<?= e($k) ?>"><?= e($label) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div data-ac-block="file">
                    <label for="ac-file" class="<?= $acLabel ?>" data-ac-file-label>File *</label>
                    <input id="ac-file" name="file" type="file" class="evo-file block w-full text-[11px] text-zinc-400">
                    <span class="block text-[10px] text-zinc-500 mt-1" data-ac-file-help></span>
                    <span class="block text-[10px] text-emerald-400 mt-1" data-ac-current-file hidden></span>
                </div>
                <div data-ac-block="videoUrl">
                    <label for="ac-video-url" class="<?= $acLabel ?>">Or a YouTube or Vimeo link</label>
                    <input id="ac-video-url" name="videoUrl" type="url" maxlength="300" placeholder="https://www.youtube.com/watch?v=…" class="<?= $acInput ?>">
                    <span class="block text-[10px] text-zinc-500 mt-1">A file you choose above takes the place of the link.</span>
                </div>
                <div data-ac-block="url">
                    <label for="ac-url" class="<?= $acLabel ?>">Web address *</label>
                    <input id="ac-url" name="url" type="url" maxlength="500" placeholder="https://" class="<?= $acInput ?>">
                </div>
                <div data-ac-block="body">
                    <label for="ac-body" class="<?= $acLabel ?>">Article *</label>
                    <textarea id="ac-body" name="body" rows="10" maxlength="<?= ACADEMY_ARTICLE_MAX ?>" class="<?= $acInput ?>"></textarea>
                    <span class="block text-[10px] text-zinc-500 mt-1">A blank line starts a new paragraph. Lines starting with "- " become a list.</span>
                </div>
                <div>
                    <label for="ac-description" class="<?= $acLabel ?>">Short description</label>
                    <textarea id="ac-description" name="description" rows="2" maxlength="2000" placeholder="What members will learn" class="<?= $acInput ?>"></textarea>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="ac-minutes" class="<?= $acLabel ?>">Length (minutes)</label>
                        <input id="ac-minutes" name="minutes" type="number" min="0" max="1000" step="1" class="<?= $acInput ?> font-mono">
                    </div>
                    <div>
                        <label for="ac-sort" class="<?= $acLabel ?>">Order in category</label>
                        <input id="ac-sort" name="sortOrder" type="number" step="1" title="Lower numbers come first; empty puts it last" class="<?= $acInput ?> font-mono">
                    </div>
                </div>
                <div class="flex items-center gap-4">
                    <div class="w-24 h-14 rounded-xl bg-zinc-950 border border-zinc-800 overflow-hidden shrink-0 flex items-center justify-center text-zinc-600">
                        <img data-ac-cover-preview alt="" hidden class="w-full h-full object-cover">
                        <span data-ac-cover-none><?= icon('image', 'w-6 h-6') ?></span>
                    </div>
                    <div class="min-w-0 flex-1">
                        <label for="ac-cover" class="<?= $acLabel ?>">Cover picture</label>
                        <input id="ac-cover" name="cover" type="file" accept="image/jpeg,image/png,image/webp,image/gif" class="evo-file block w-full text-[11px] text-zinc-400">
                        <span class="block text-[10px] text-zinc-500 mt-1">Optional. A wide picture (16:9), up to 5 MB. YouTube videos use their own.</span>
                        <label class="flex items-center gap-2 text-zinc-300 mt-1" data-ac-cover-remove hidden><input type="checkbox" name="removeCover" value="1" class="evo-check"><span>Remove the picture</span></label>
                    </div>
                </div>
                <label class="flex items-center gap-2 text-zinc-300">
                    <input type="checkbox" name="published" value="1" checked class="evo-check">
                    <span>Published: members see it</span>
                </label>
                <div data-ac-progress hidden>
                    <div class="evo-ac-bar"><span data-ac-progress-bar style="width:0%"></span></div>
                    <span class="block text-[10px] text-zinc-400 mt-1" data-ac-progress-text></span>
                </div>
                <p data-ac-error hidden role="alert" class="p-3 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-300"></p>
                <button type="submit" class="w-full py-2.5 bg-[#f3bd35] hover:bg-[#ffda72] text-black font-extrabold rounded-xl text-xs flex items-center justify-center gap-1.5 disabled:opacity-60"><?= icon('save', 'w-3.5 h-3.5') ?><span>Save material</span></button>
            </form>
        </div>
    </div>
</div>
