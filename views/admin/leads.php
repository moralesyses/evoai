<?php
/**
 * Management Admin > Global Leads CRM (reference component "Fn").
 * Uses $leads from views/admin.php (createdAt in ms).
 */
$leadSelectBase = 'text-[10px] font-bold uppercase rounded-lg px-2 py-1 bg-zinc-900 border';
$leadSelectClass = static function (string $status): string {
    return $status === 'converted' ? 'border-emerald-500/50 text-emerald-400' : ($status === 'contacted' ? 'border-sky-500/50 text-sky-400' : 'border-amber-500/50 text-amber-400');
};
$leadFilterOn = 'bg-[#f3bd35] text-black font-bold shadow-sm';
$leadFilterOff = 'text-zinc-400 hover:text-white';
?>
<div class="space-y-4" id="admin-leads">
    <div class="flex flex-col sm:flex-row items-center justify-between gap-3 p-4 rounded-3xl bg-[#111111] border border-[#f3bd35]/20">
        <div class="relative w-full sm:w-72">
            <?= icon('search', 'w-4 h-4 text-zinc-400 absolute left-3 top-1/2 -translate-y-1/2') ?>
            <input type="text" data-lead-search placeholder="Search leads by name, firm, email..." aria-label="Search leads" class="w-full pl-9 pr-3 py-2 rounded-xl bg-zinc-900 border border-zinc-700 text-xs text-white placeholder-zinc-500 focus:outline-none focus:border-[#f3bd35]">
        </div>
        <div class="flex items-center gap-2 w-full sm:w-auto justify-end">
            <div class="flex items-center p-1 bg-zinc-900 rounded-xl border border-zinc-800 text-xs" role="group" aria-label="Filter leads by status">
                <?php foreach (['all', 'new', 'contacted', 'converted'] as $leadF): ?>
                    <button type="button" data-lead-filter="<?= e($leadF) ?>" data-on="<?= $leadFilterOn ?>" data-off="<?= $leadFilterOff ?>" aria-pressed="<?= $leadF === 'all' ? 'true' : 'false' ?>" class="px-2.5 py-1 rounded-lg capitalize transition-colors <?= $leadF === 'all' ? $leadFilterOn : $leadFilterOff ?>"><?= e($leadF) ?></button>
                <?php endforeach; ?>
            </div>
            <button type="button" data-lead-export class="flex items-center gap-1.5 px-3 py-2 bg-zinc-800 hover:bg-zinc-700 text-zinc-200 text-xs font-semibold rounded-xl border border-zinc-700 transition-colors whitespace-nowrap">
                <?= icon('download', 'w-3.5 h-3.5') ?><span>Export CSV</span>
            </button>
        </div>
    </div>
    <div class="rounded-3xl bg-[#111111] border border-[#f3bd35]/20 overflow-hidden shadow-xl">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-[#181818] border-b border-zinc-800 text-zinc-400 uppercase text-[10px] tracking-wider">
                    <tr>
                        <th class="py-3 px-4">Contact</th>
                        <th class="py-3 px-4">Company</th>
                        <th class="py-3 px-4">Tier Interest</th>
                        <th class="py-3 px-4">Message Note</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-800/80">
                    <?php foreach ($leads as $leadRow):
                        $leadPhone = (string) ($leadRow['phone'] ?? '');
                        $leadEmail = (string) ($leadRow['email'] ?? '');
                        $leadSearch = implode("\n", array_map(static fn ($v) => mb_strtolower((string) $v), [$leadRow['name'], $leadRow['company'], $leadEmail])); ?>
                        <tr class="hover:bg-zinc-900/50 transition-colors" data-lead-row="<?= e($leadRow['id']) ?>" data-status="<?= e($leadRow['status']) ?>" data-search="<?= e($leadSearch) ?>">
                            <td class="py-3 px-4">
                                <div class="font-bold text-white"><?= e($leadRow['name']) ?></div>
                                <div class="text-[11px] text-zinc-400"><?= e($leadEmail) ?></div>
                                <div class="text-[10px] text-[#ffda72] font-mono"><?= e($leadPhone) ?></div>
                            </td>
                            <td class="py-3 px-4 text-zinc-300 font-medium"><?= e(($leadRow['company'] ?? '') !== '' ? $leadRow['company'] : '—') ?></td>
                            <td class="py-3 px-4">
                                <span class="px-2 py-0.5 rounded-full bg-[#f3bd35]/15 border border-[#f3bd35]/30 text-[#f3bd35] font-semibold text-[10px]"><?= e($leadRow['interest']) ?></span>
                            </td>
                            <td class="py-3 px-4 text-zinc-400 max-w-xs truncate"><?= e(($leadRow['message'] ?? '') !== '' ? $leadRow['message'] : '—') ?></td>
                            <td class="py-3 px-4">
                                <select data-lead-status="<?= e($leadRow['id']) ?>" data-current="<?= e($leadRow['status']) ?>" aria-label="Lead status for <?= e($leadRow['name']) ?>" class="<?= $leadSelectBase ?> <?= $leadSelectClass((string) $leadRow['status']) ?>">
                                    <option value="new"<?= $leadRow['status'] === 'new' ? ' selected' : '' ?>>New</option>
                                    <option value="contacted"<?= $leadRow['status'] === 'contacted' ? ' selected' : '' ?>>Contacted</option>
                                    <option value="converted"<?= $leadRow['status'] === 'converted' ? ' selected' : '' ?>>Converted</option>
                                </select>
                            </td>
                            <td class="py-3 px-4 text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    <?php if ($leadPhone !== ''): ?>
                                        <a href="https://api.whatsapp.com/send?phone=<?= e(preg_replace('/[^0-9]/', '', $leadPhone)) ?>" target="_blank" rel="noreferrer" title="WhatsApp Direct" aria-label="WhatsApp <?= e($leadRow['name']) ?>" class="p-1.5 rounded-lg bg-zinc-800 hover:bg-emerald-600/30 text-emerald-400 transition-colors"><?= icon('phone', 'w-3.5 h-3.5') ?></a>
                                    <?php endif; ?>
                                    <?php if ($leadEmail !== ''): ?>
                                        <a href="mailto:<?= e($leadEmail) ?>" title="Send Email" aria-label="Email <?= e($leadRow['name']) ?>" class="p-1.5 rounded-lg bg-zinc-800 hover:bg-sky-600/30 text-sky-400 transition-colors"><?= icon('mail', 'w-3.5 h-3.5') ?></a>
                                    <?php endif; ?>
                                    <button type="button" data-lead-chat="<?= e($leadRow['name']) ?>" title="Start Secure E2EE Chat" aria-label="Start secure chat with <?= e($leadRow['name']) ?>" class="p-1.5 rounded-lg bg-zinc-800 hover:bg-[#f3bd35]/30 text-[#f3bd35] transition-colors"><?= icon('message-square', 'w-3.5 h-3.5') ?></button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
