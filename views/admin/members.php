<?php
/**
 * Management Admin > Member Access & Roles (reference component "Pn").
 * Uses $user (signed-in admin), $users and $profiles from views/admin.php.
 */
$memRoleBase = 'text-[11px] font-bold uppercase rounded-lg px-2.5 py-1 bg-zinc-900 border';
$memRoleClass = static function (string $role): string {
    return $role === 'admin' ? 'border-[#f3bd35] text-[#ffda72]' : ($role === 'vip' ? 'border-purple-500/50 text-purple-400' : 'border-zinc-700 text-zinc-300');
};
$memRoleOptions = ['admin' => 'Admin / Founder', 'vip' => "Chairman's Circle (VIP)", 'member' => 'Executive Member', 'visitor' => 'Visitor'];
// Roster in join order (the reference lists Mike, Sofia, David, Elena); ties keep the
// list_users() order (usort is stable). Seed users share one created_at timestamp.
$memRoster = $users;
$memJoinTs = static function (array $mu): int {
    $ts = strtotime('1 ' . (string) ($mu['joinedDate'] ?? ''));
    return $ts === false ? PHP_INT_MAX : $ts;
};
usort($memRoster, static fn ($a, $b) => $memJoinTs($a) <=> $memJoinTs($b));
// Card profile per roster member (members without a stored card get the reference defaults).
$memProfiles = [];
foreach ($memRoster as $memUser) {
    $memProfiles[$memUser['id']] = $profiles[$memUser['id']] ?? default_profile($memUser['id']);
}
$memThemes = [
    ['id' => 'gold', 'label' => 'Executive Gold', 'color' => 'bg-gradient-to-r from-[#d4af37] to-[#aa7c11] text-black'],
    ['id' => 'obsidian', 'label' => 'Obsidian Black', 'color' => 'bg-gradient-to-r from-zinc-900 to-black text-amber-300 border border-amber-500/40'],
    ['id' => 'platinum', 'label' => 'Sterling Silver', 'color' => 'bg-gradient-to-r from-zinc-300 to-zinc-500 text-black'],
    ['id' => 'rosegold', 'label' => 'Rose Gold', 'color' => 'bg-gradient-to-r from-rose-300 to-rose-500 text-black'],
];
$memStatuses = [
    ['id' => 'Active', 'label' => 'Active & Verified', 'color' => 'text-emerald-400 border-emerald-500/40 bg-emerald-950/20'],
    ['id' => 'Frozen (Card Lost)', 'label' => 'Frozen (Lost Card)', 'color' => 'text-rose-400 border-rose-500/40 bg-rose-950/20'],
    ['id' => 'Pending Renewal', 'label' => 'Pending Renewal', 'color' => 'text-amber-400 border-amber-500/40 bg-amber-950/20'],
];
$memInput = 'w-full px-3 py-2 rounded-xl bg-zinc-900 border border-zinc-700 text-xs text-white focus:outline-none focus:border-[#f3bd35]';
$memLabel = 'block text-[11px] font-semibold text-zinc-300 mb-1';
?>
<div class="space-y-4" id="admin-members">
    <script type="application/json" id="admin-members-data"><?= js_json($memProfiles) ?></script>
    <div data-modal="admin-member-nfc" hidden class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-md p-4 animate-in fade-in" role="dialog" aria-modal="true" aria-labelledby="mem-nfc-title">
        <div class="w-full max-w-lg rounded-3xl bg-[#141414] border border-[#f3bd35]/40 p-6 shadow-2xl relative text-left max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between pb-4 border-b border-zinc-800 mb-4">
                <div class="flex items-center gap-2.5">
                    <div class="w-10 h-10 rounded-2xl bg-[#f3bd35]/15 border border-[#f3bd35]/30 flex items-center justify-center text-[#f3bd35]"><?= icon('credit-card', 'w-5 h-5') ?></div>
                    <div>
                        <h3 class="text-base font-bold text-white flex items-center gap-2" id="mem-nfc-title">Manage Smart NFC Hardware<?= icon('shield-check', 'w-4 h-4 text-emerald-400') ?></h3>
                        <p class="text-xs text-zinc-400">Member ID: <span class="font-mono text-[#ffda72] font-semibold" data-mem-nfc-id></span></p>
                    </div>
                </div>
                <button type="button" data-close-modal class="text-zinc-400 hover:text-white p-1 rounded-lg" aria-label="Close"><?= icon('x', 'w-5 h-5') ?></button>
            </div>
            <form id="mem-nfc-form" class="space-y-4 text-xs">
                <input type="hidden" name="userId" value="">
                <input type="hidden" name="cardTheme" value="gold">
                <input type="hidden" name="membershipStatus" value="Active">
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label for="mem-nfc-serial" class="text-[11px] font-bold text-zinc-300">Physical NFC Hardware Serial ID *</label>
                        <div class="flex items-center gap-2">
                            <button type="button" data-nfc-serial="exe" class="text-[10px] text-[#ffda72] hover:underline flex items-center gap-1 font-mono"><?= icon('refresh-cw', 'w-2.5 h-2.5') ?> New EXE Serial</button>
                            <button type="button" data-nfc-serial="vip" class="text-[10px] text-amber-400 hover:underline flex items-center gap-1 font-mono"><?= icon('sparkles', 'w-2.5 h-2.5') ?> New VIP Serial</button>
                        </div>
                    </div>
                    <input id="mem-nfc-serial" name="nfcCardId" type="text" required maxlength="255" placeholder="e.g. MMC-VIP-88001 or MMC-EXE-88109" class="w-full px-3 py-2 rounded-xl bg-zinc-900 border border-zinc-700 text-xs text-white font-mono font-bold tracking-wider focus:outline-none focus:border-[#f3bd35]">
                    <p class="text-[10px] text-zinc-500 mt-1">Every 88MMM member has a laser-etched NXP NTAG216 chip paired to their cloud backoffice.</p>
                </div>
                <div>
                    <span class="block text-[11px] font-bold text-zinc-300 mb-1">Card Metallic Finish &amp; Theme</span>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2" role="group" aria-label="Card Metallic Finish &amp; Theme">
                        <?php foreach ($memThemes as $memTheme): ?>
                            <button type="button" data-nfc-theme="<?= e($memTheme['id']) ?>" data-on="border-[#f3bd35] ring-2 ring-[#f3bd35]/50 scale-[1.02]" data-off="border-zinc-800 opacity-60 hover:opacity-100" class="p-2 rounded-xl text-center text-[10px] font-bold transition-all border border-zinc-800 opacity-60 hover:opacity-100">
                                <div class="h-4 rounded-md mb-1.5 shadow <?= e($memTheme['color']) ?>"></div>
                                <span class="text-zinc-200"><?= e($memTheme['label']) ?></span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div>
                    <label for="mem-nfc-action" class="block text-[11px] font-bold text-zinc-300 mb-1">Physical Contactless Tap Behavior</label>
                    <select id="mem-nfc-action" name="nfcAction" class="w-full px-3 py-2 rounded-xl bg-zinc-900 border border-zinc-700 text-xs text-white focus:outline-none focus:border-[#f3bd35]">
                        <option value="card">Open Executive Digital Card Portal (1evoai.com/username)</option>
                        <option value="vcard">Instant Direct Contact Download (.vcf address book)</option>
                        <option value="whatsapp">Launch Direct Encrypted WhatsApp VIP Chat</option>
                    </select>
                    <p class="text-[10px] text-zinc-500 mt-1">When tapped by an iPhone or Android phone, this action executes with 0 friction.</p>
                </div>
                <div>
                    <span class="block text-[11px] font-bold text-zinc-300 mb-1">Hardware Operational Status</span>
                    <div class="grid grid-cols-3 gap-2" role="group" aria-label="Hardware Operational Status">
                        <?php foreach ($memStatuses as $memStatus): ?>
                            <button type="button" data-nfc-status="<?= e($memStatus['id']) ?>" data-on="<?= e($memStatus['color']) ?> ring-1 ring-white/20" data-off="border-zinc-800 text-zinc-500 hover:text-zinc-300" class="p-2 rounded-xl text-center text-[10px] font-bold border transition-all border-zinc-800 text-zinc-500 hover:text-zinc-300"><?= e($memStatus['label']) ?></button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="flex items-center justify-end gap-3 pt-3 border-t border-zinc-800">
                    <button type="button" data-close-modal class="px-4 py-2 bg-zinc-800 hover:bg-zinc-700 text-zinc-200 text-xs font-semibold rounded-xl transition-colors">Cancel</button>
                    <button type="submit" class="px-5 py-2 bg-[#f3bd35] hover:bg-[#ffda72] text-black text-xs font-extrabold rounded-xl shadow-md transition-colors flex items-center gap-1.5">
                        <?= icon('save', 'w-3.5 h-3.5') ?><span>Save NFC Hardware</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
    <div data-modal="admin-member-edit" hidden class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="mem-edit-title">
        <div class="w-full max-w-2xl bg-[#121212] border border-[#f3bd35]/40 rounded-3xl p-6 shadow-2xl space-y-4 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between pb-3 border-b border-zinc-800">
                <div class="flex items-center gap-2">
                    <?= icon('shield', 'w-5 h-5 text-[#f3bd35]') ?>
                    <h3 class="text-sm font-bold text-white" id="mem-edit-title">Management Admin: Edit Member Card (<span data-mem-edit-name></span>)</h3>
                </div>
                <button type="button" data-close-modal class="text-zinc-400 hover:text-white" aria-label="Close"><?= icon('x', 'w-5 h-5') ?></button>
            </div>
            <form id="mem-edit-form" class="space-y-4 text-xs">
                <input type="hidden" name="userId" value="">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label for="mem-edit-name" class="<?= $memLabel ?>">Full Name</label>
                        <input id="mem-edit-name" name="name" type="text" required maxlength="150" class="<?= $memInput ?>">
                    </div>
                    <div>
                        <label for="mem-edit-title-input" class="<?= $memLabel ?>">Executive Title</label>
                        <input id="mem-edit-title-input" name="title" type="text" required maxlength="255" class="<?= $memInput ?>">
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label for="mem-edit-company" class="<?= $memLabel ?>">Company Name</label>
                        <input id="mem-edit-company" name="company" type="text" required maxlength="255" class="<?= $memInput ?>">
                    </div>
                    <div>
                        <label for="mem-edit-slug" class="<?= $memLabel ?>">Card Custom Slug</label>
                        <input id="mem-edit-slug" name="customSlug" type="text" maxlength="255" class="<?= $memInput ?>">
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label for="mem-edit-phone" class="<?= $memLabel ?>">Mobile Phone</label>
                        <input id="mem-edit-phone" name="phone" type="text" maxlength="255" class="<?= $memInput ?>">
                    </div>
                    <div>
                        <label for="mem-edit-email" class="<?= $memLabel ?>">Email</label>
                        <input id="mem-edit-email" name="email" type="email" maxlength="255" class="<?= $memInput ?>">
                    </div>
                    <div>
                        <label for="mem-edit-whatsapp" class="<?= $memLabel ?>">WhatsApp</label>
                        <input id="mem-edit-whatsapp" name="whatsapp" type="text" maxlength="255" class="<?= $memInput ?>">
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label for="mem-edit-tier" class="<?= $memLabel ?>">Membership Tier</label>
                        <select id="mem-edit-tier" name="tier" class="<?= $memInput ?>">
                            <option value="Chairman's Circle">Chairman's Circle (VIP)</option>
                            <option value="Executive Member">Executive Member</option>
                        </select>
                    </div>
                    <div>
                        <label for="mem-edit-nfc" class="<?= $memLabel ?>">Physical NFC Serial ID</label>
                        <input id="mem-edit-nfc" name="nfcCardId" type="text" maxlength="255" class="<?= $memInput ?>">
                    </div>
                </div>
                <div>
                    <label for="mem-edit-bio" class="<?= $memLabel ?>">Executive Bio</label>
                    <textarea id="mem-edit-bio" name="bio" rows="3" maxlength="5000" class="<?= $memInput ?>"></textarea>
                </div>
                <div class="flex items-center justify-end gap-3 pt-3 border-t border-zinc-800">
                    <button type="button" data-close-modal class="px-4 py-2 bg-zinc-800 hover:bg-zinc-700 text-zinc-200 text-xs font-semibold rounded-xl transition-colors">Cancel</button>
                    <button type="submit" class="px-5 py-2 bg-[#f3bd35] hover:bg-[#ffda72] text-black text-xs font-extrabold rounded-xl shadow-md transition-colors flex items-center gap-1.5">
                        <?= icon('save', 'w-3.5 h-3.5') ?><span>Save Changes</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
    <div class="flex items-center justify-between p-4 rounded-3xl bg-[#111111] border border-[#f3bd35]/20">
        <div>
            <h3 class="text-sm font-bold text-white">88MMM Executive Society Members &amp; Access Roles</h3>
            <p class="text-xs text-zinc-400">Control member privileges, Chairman's Circle VIP status, edit member profiles, and cryptographic credentials.</p>
        </div>
    </div>
    <div class="rounded-3xl bg-[#111111] border border-[#f3bd35]/20 overflow-hidden shadow-xl">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-[#181818] border-b border-zinc-800 text-zinc-400 uppercase text-[10px] tracking-wider">
                    <tr>
                        <th class="py-3 px-4">Member</th>
                        <th class="py-3 px-4">Executive Title</th>
                        <th class="py-3 px-4">Access Role</th>
                        <th class="py-3 px-4">Assigned NFC Card</th>
                        <th class="py-3 px-4">Verification</th>
                        <th class="py-3 px-4">Joined</th>
                        <th class="py-3 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-800/80">
                    <?php foreach ($memRoster as $memUser):
                        $memP = $memProfiles[$memUser['id']];
                        $memSelf = $memUser['id'] === $user['id']; ?>
                        <tr class="hover:bg-zinc-900/50 transition-colors" data-member-row="<?= e($memUser['id']) ?>">
                            <td class="py-3 px-4">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-full bg-gradient-to-tr from-[#ffda72] to-[#8f6510] p-[1px] flex items-center justify-center font-bold text-black text-xs"><?= e(mb_substr((string) $memUser['name'], 0, 1)) ?></div>
                                    <div>
                                        <div class="font-bold text-white flex items-center gap-1.5">
                                            <?= e($memUser['name']) ?>
                                            <?php if ($memSelf): ?><span class="text-[9px] px-1.5 py-0.2 rounded-full bg-[#f3bd35]/20 text-[#ffda72]">You</span><?php endif; ?>
                                        </div>
                                        <div class="text-[11px] text-zinc-400"><?= e($memUser['email']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3 px-4 text-zinc-300 font-medium"><?= e($memUser['title']) ?></td>
                            <td class="py-3 px-4">
                                <select data-member-role="<?= e($memUser['id']) ?>" data-current="<?= e($memUser['role']) ?>" aria-label="Access role for <?= e($memUser['name']) ?>" class="<?= $memRoleBase ?> <?= $memRoleClass($memUser['role']) ?>">
                                    <?php foreach ($memRoleOptions as $memRoleId => $memRoleLabel): ?>
                                        <option value="<?= e($memRoleId) ?>"<?= $memUser['role'] === $memRoleId ? ' selected' : '' ?>><?= e($memRoleLabel) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td class="py-3 px-4">
                                <button type="button" data-member-nfc="<?= e($memUser['id']) ?>" class="flex items-center gap-1.5 px-2.5 py-1 rounded-xl bg-zinc-950 border border-[#f3bd35]/30 hover:border-[#f3bd35] transition-all group" title="Click to manage or re-assign NFC card">
                                    <?= icon('credit-card', 'w-3 h-3 text-[#f3bd35]') ?>
                                    <span class="font-mono text-[11px] font-bold text-[#ffda72]"><?= e(($memP['nfcCardId'] ?? '') !== '' && $memP['nfcCardId'] !== null ? $memP['nfcCardId'] : 'MMC-UNASSIGNED') ?></span>
                                    <span class="text-[9px] uppercase px-1.5 py-0.2 rounded-full font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20"><?= e(($memP['cardTheme'] ?? '') !== '' && $memP['cardTheme'] !== null ? $memP['cardTheme'] : 'Gold') ?></span>
                                </button>
                            </td>
                            <td class="py-3 px-4">
                                <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-400"><?= icon('shield-check', 'w-3.5 h-3.5') ?>E2EE Verified</span>
                            </td>
                            <td class="py-3 px-4 text-zinc-400 text-[11px] tabular-nums"><?= e($memUser['joinedDate']) ?></td>
                            <td class="py-3 px-4 text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    <button type="button" data-member-nfc="<?= e($memUser['id']) ?>" class="px-2.5 py-1 bg-[#f3bd35]/15 hover:bg-[#f3bd35] hover:text-black border border-[#f3bd35]/40 text-[#ffda72] text-[11px] font-bold rounded-lg transition-colors flex items-center gap-1" title="Manage Physical NFC Smart Card">
                                        <?= icon('credit-card', 'w-3 h-3') ?><span>NFC Card</span>
                                    </button>
                                    <button type="button" data-member-edit="<?= e($memUser['id']) ?>" class="px-2.5 py-1 bg-zinc-900 hover:bg-zinc-800 border border-zinc-700 text-zinc-300 hover:text-white text-[11px] font-medium rounded-lg transition-colors flex items-center gap-1" title="Edit Member Profile">
                                        <?= icon('pen-line', 'w-3 h-3') ?><span>Edit</span>
                                    </button>
                                    <?php if ($memSelf): ?>
                                        <button type="button" disabled class="px-2.5 py-1 bg-zinc-800 hover:bg-[#f3bd35] hover:text-black text-zinc-200 text-[11px] font-semibold rounded-lg transition-colors disabled:opacity-40">Active</button>
                                    <?php else: ?>
                                        <button type="button" data-switch-user="<?= e($memUser['id']) ?>" class="px-2.5 py-1 bg-zinc-800 hover:bg-[#f3bd35] hover:text-black text-zinc-200 text-[11px] font-semibold rounded-lg transition-colors disabled:opacity-40">Log in as</button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>


    <!-- Edit member card profile -->

    <!-- Manage physical NFC smart card -->
</div>
