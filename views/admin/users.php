<?php
/**
 * Management Admin > Users (superadmin only): portal sign-in accounts.
 * Edit and delete go through api/actions/admin_users.php
 * (assets/js/views/admin-users.js).
 */
$usInput = 'w-full px-3 py-2 rounded-xl bg-zinc-900 border border-zinc-700 text-xs text-white focus:outline-none focus:border-[#f3bd35]';
$usLabel = 'block text-[11px] font-semibold text-zinc-300 mb-1';
$usError = 'block text-[10px] text-rose-400 mt-1';
$usRoleBadge = static function (string $role): string {
    return 'text-[10px] font-bold uppercase rounded-lg px-2 py-0.5 bg-zinc-900 border ' . ($role === 'admin' ? 'border-[#f3bd35] text-[#ffda72]' : ($role === 'vip' ? 'border-purple-500/50 text-purple-400' : 'border-zinc-700 text-zinc-300'));
};
$usRoleShort = ['admin' => 'Admin', 'vip' => 'VIP', 'member' => 'Member', 'visitor' => 'Visitor'];

$usQ = mb_substr(trim(is_string($_GET['q'] ?? null) ? $_GET['q'] : ''), 0, 100);
$usSort = is_string($_GET['sort'] ?? null) && isset(USER_SORTS[$_GET['sort']]) ? $_GET['sort'] : 'newest';
$usPage = max(1, (int) ($_GET['page'] ?? 1));
[$usRows, $usTotal] = admin_search_users($usQ, $usPage, $usSort);
$usPages = max(1, (int) ceil($usTotal / USERS_PER_PAGE));
if ($usPage > $usPages) {
    $usPage = $usPages;
    [$usRows, $usTotal] = admin_search_users($usQ, $usPage, $usSort);
}
$usPageUrl = fn (int $to): string => url('admin', array_filter(['tab' => 'users', 'q' => $usQ, 'sort' => $usSort === 'newest' ? '' : $usSort, 'page' => $to > 1 ? $to : '']));

// Shared by the table (640px and up) and the stacked list (phones).
$usIdentity = static function (array $row, bool $self): void { ?>
    <div class="flex items-center gap-2.5 min-w-0">
        <div class="w-8 h-8 shrink-0 rounded-full bg-gradient-to-tr from-[#ffda72] to-[#8f6510] p-[1px] flex items-center justify-center font-bold text-black text-xs"><?= e(mb_substr((string) $row['name'], 0, 1)) ?></div>
        <div class="min-w-0">
            <div class="font-bold text-white flex items-center gap-1.5">
                <span class="truncate"><?= e($row['name']) ?></span>
                <?php if ($self): ?><span class="shrink-0 text-[9px] px-1.5 py-0.5 rounded-full bg-[#f3bd35]/20 text-[#ffda72]">You</span><?php endif; ?>
                <?php if ((int) $row['verified'] === 1): ?><?= icon('shield-check', 'w-3.5 h-3.5 shrink-0 text-emerald-400', ['aria-label' => 'Verified']) ?><?php endif; ?>
            </div>
            <div class="text-[11px] text-zinc-400 truncate"><?= e($row['email']) ?></div>
        </div>
    </div>
<?php };
$usStatus = static function (array $row): void {
    if ($row['status'] === 'active') { ?>
        <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-400"><span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>Active</span>
    <?php } else { ?>
        <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-rose-400"><span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>Suspended</span>
    <?php }
};
$usActions = static function (array $row, bool $self, bool $founder): void { ?>
    <?php if ((int) $row['has_card'] === 1): ?>
        <a href="<?= e(url('card', ['profile' => $row['id']])) ?>" target="_blank" rel="noopener" class="px-2.5 py-1 bg-[#f3bd35]/15 hover:bg-[#f3bd35] hover:text-black border border-[#f3bd35]/40 text-[#ffda72] text-[11px] font-bold rounded-lg transition-colors flex items-center gap-1" title="Open this user's 1EVOAI Card"><?= icon('credit-card', 'w-3 h-3') ?><span>Card</span></a>
    <?php endif; ?>
    <a href="<?= e($self ? url('backoffice') : url('backoffice', ['member' => $row['id']])) ?>" class="px-2.5 py-1 bg-zinc-900 hover:bg-zinc-800 border border-zinc-700 text-zinc-300 hover:text-white text-[11px] font-medium rounded-lg transition-colors flex items-center gap-1" title="View this user's Member Backoffice (read-only)"><?= icon('layout-dashboard', 'w-3 h-3') ?><span>Backoffice</span></a>
    <button type="button" data-us-edit="<?= e($row['id']) ?>" class="px-2.5 py-1 bg-zinc-900 hover:bg-zinc-800 border border-zinc-700 text-zinc-300 hover:text-white text-[11px] font-medium rounded-lg transition-colors flex items-center gap-1" title="Edit user"><?= icon('pen-line', 'w-3 h-3') ?><span>Edit</span></button>
    <?php if ($self || $founder): ?>
        <button type="button" disabled title="<?= $self ? 'You cannot delete your own account' : "The founder's account can't be deleted" ?>" class="px-2.5 py-1 bg-rose-500/10 border border-rose-500/30 text-rose-300 text-[11px] font-semibold rounded-lg flex items-center gap-1 disabled:opacity-40"><?= icon('trash-2', 'w-3 h-3') ?><span>Delete</span></button>
    <?php else: ?>
        <button type="button" data-us-delete="<?= e($row['id']) ?>" data-us-name="<?= e($row['name']) ?>" data-us-email="<?= e($row['email']) ?>" class="px-2.5 py-1 bg-rose-500/10 hover:bg-rose-500 hover:text-white border border-rose-500/30 text-rose-300 text-[11px] font-semibold rounded-lg transition-colors flex items-center gap-1" title="Delete user"><?= icon('trash-2', 'w-3 h-3') ?><span>Delete</span></button>
    <?php endif; ?>
<?php };
?>
<div class="space-y-4" id="admin-users">
    <div data-modal="us-edit" hidden class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="us-edit-title">
        <div class="w-full max-w-2xl bg-[#121212] border border-[#f3bd35]/40 rounded-3xl p-6 shadow-2xl space-y-4 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between pb-3 border-b border-zinc-800">
                <div class="flex items-center gap-2">
                    <?= icon('user-check', 'w-5 h-5 text-[#f3bd35]') ?>
                    <h3 class="text-sm font-bold text-white" id="us-edit-title">Edit user: <span data-us-name></span></h3>
                </div>
                <div class="flex items-center gap-2">
                    <a data-us-card-link href="#" target="_blank" rel="noopener" class="px-2.5 py-1 bg-zinc-900 hover:bg-zinc-800 border border-zinc-700 text-zinc-300 hover:text-white text-[11px] font-medium rounded-lg transition-colors flex items-center gap-1"><?= icon('external-link', 'w-3 h-3') ?><span>View card</span></a>
                    <button type="button" data-close-modal class="text-zinc-400 hover:text-white" aria-label="Close"><?= icon('x', 'w-5 h-5') ?></button>
                </div>
            </div>
            <p data-us-msg hidden role="alert" class="p-3 rounded-xl text-xs"></p>
            <form id="us-edit-form" class="space-y-4 text-xs" novalidate autocomplete="off">
                <input type="hidden" name="userId" value="">
                <input type="hidden" name="version" value="">
                <fieldset class="p-4 rounded-2xl bg-zinc-950 border border-zinc-800">
                    <legend class="px-2 text-[10px] font-bold uppercase tracking-wider text-[#f3bd35]">Account</legend>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label for="us-name" class="<?= $usLabel ?>">Full name <span class="text-[#f3bd35]">*</span></label>
                            <input id="us-name" name="name" type="text" required maxlength="150" class="<?= $usInput ?>">
                            <span data-us-error="name" hidden class="<?= $usError ?>"></span>
                        </div>
                        <div>
                            <label for="us-email" class="<?= $usLabel ?>">Email (sign-in) <span class="text-[#f3bd35]">*</span></label>
                            <input id="us-email" name="email" type="email" required maxlength="190" class="<?= $usInput ?>">
                            <span data-us-error="email" hidden class="<?= $usError ?>"></span>
                        </div>
                        <div class="sm:col-span-2">
                            <label for="us-title" class="<?= $usLabel ?>">Executive title</label>
                            <input id="us-title" name="title" type="text" maxlength="190" class="<?= $usInput ?>">
                            <span data-us-error="title" hidden class="<?= $usError ?>"></span>
                        </div>
                    </div>
                </fieldset>
                <fieldset class="p-4 rounded-2xl bg-zinc-950 border border-zinc-800">
                    <legend class="px-2 text-[10px] font-bold uppercase tracking-wider text-[#f3bd35]">Access</legend>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label for="us-role" class="<?= $usLabel ?>">Access role</label>
                            <select id="us-role" name="role" class="<?= $usInput ?>">
                                <?php foreach (['admin' => 'Admin / Founder', 'vip' => "Chairman's Circle (VIP)", 'member' => 'Executive Member', 'visitor' => 'Visitor (no portal access)'] as $usValue => $usText): ?>
                                    <option value="<?= e($usValue) ?>"><?= e($usText) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <span data-us-error="role" hidden class="<?= $usError ?>"></span>
                        </div>
                        <div>
                            <label for="us-status" class="<?= $usLabel ?>">Account status</label>
                            <select id="us-status" name="status" class="<?= $usInput ?>">
                                <?php foreach (USER_STATUSES as $usValue => $usText): ?>
                                    <option value="<?= e($usValue) ?>"><?= e($usText) ?><?= $usValue === 'suspended' ? ' (cannot sign in)' : '' ?></option>
                                <?php endforeach; ?>
                            </select>
                            <span data-us-error="status" hidden class="<?= $usError ?>"></span>
                        </div>
                        <label class="sm:col-span-2 flex items-center gap-2 cursor-pointer text-zinc-300">
                            <input type="checkbox" name="verified" value="1" class="w-4 h-4 rounded bg-zinc-900 border-zinc-700 text-[#f3bd35]">
                            <span>Verified executive (shows the verified badge)</span>
                        </label>
                    </div>
                </fieldset>
                <fieldset class="p-4 rounded-2xl bg-amber-950/40 border border-amber-600/40">
                    <legend class="px-2 text-[10px] font-bold uppercase tracking-wider text-amber-300 flex items-center gap-1"><?= icon('lock', 'w-3 h-3') ?>Reset password</legend>
                    <label for="us-password" class="<?= $usLabel ?>">New password</label>
                    <input id="us-password" name="newPassword" type="password" minlength="6" autocomplete="new-password" placeholder="Leave blank to keep the current password" class="<?= $usInput ?>">
                    <span class="block text-[10px] text-zinc-500 mt-1">At least 6 characters. The user signs in with it from now on.</span>
                    <span data-us-error="newPassword" hidden class="<?= $usError ?>"></span>
                </fieldset>
                <dl class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-[11px]">
                    <div><dt class="text-zinc-500">User ID</dt><dd class="text-zinc-300 font-mono truncate" data-us-id></dd></div>
                    <div><dt class="text-zinc-500">Joined</dt><dd class="text-zinc-300 font-mono" data-us-created></dd></div>
                    <div><dt class="text-zinc-500">Last sign-in</dt><dd class="text-zinc-300 font-mono" data-us-login></dd></div>
                </dl>
                <div class="flex items-center justify-between gap-3 pt-3 border-t border-zinc-800">
                    <button type="button" data-us-delete-from-edit class="text-[11px] font-semibold text-rose-400 hover:text-rose-300 underline underline-offset-2">Delete user</button>
                    <div class="flex items-center gap-3">
                        <button type="button" data-close-modal class="px-4 py-2 bg-zinc-800 hover:bg-zinc-700 text-zinc-200 text-xs font-semibold rounded-xl transition-colors">Cancel</button>
                        <button type="submit" class="px-5 py-2 bg-[#f3bd35] hover:bg-[#ffda72] text-black text-xs font-extrabold rounded-xl shadow-md transition-colors flex items-center gap-1.5 disabled:opacity-60">
                            <?= icon('save', 'w-3.5 h-3.5') ?><span>Save changes</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div data-modal="us-delete" hidden class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="us-delete-title">
        <div class="w-full max-w-md bg-[#141414] border border-rose-500/40 rounded-3xl p-6 shadow-2xl space-y-4 text-xs">
            <div class="flex items-center justify-between pb-3 border-b border-zinc-800">
                <h3 class="text-sm font-bold text-white flex items-center gap-2" id="us-delete-title"><?= icon('trash-2', 'w-4 h-4 text-rose-400') ?><span>Delete <span data-us-delete-name></span>?</span></h3>
                <button type="button" data-close-modal class="text-zinc-400 hover:text-white" aria-label="Close"><?= icon('x', 'w-4 h-4') ?></button>
            </div>
            <p class="text-zinc-300 leading-relaxed">The account <span class="font-mono text-[#ffda72]" data-us-delete-email></span> will be removed together with its 1EVOAI Card profile and its backoffice accounts (wallet, transactions, activation codes, cart and orders). The user can no longer sign in. <strong class="text-white">This can’t be undone.</strong></p>
            <p class="text-zinc-500 text-[11px]">Payments, leads, chat messages, audit entries and network-tree placements are kept as records.</p>
            <p data-us-delete-msg hidden role="alert" class="p-3 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-300"></p>
            <div class="flex items-center justify-end gap-3 pt-3 border-t border-zinc-800">
                <button type="button" data-close-modal data-us-delete-cancel class="px-4 py-2 bg-zinc-800 hover:bg-zinc-700 text-zinc-200 text-xs font-semibold rounded-xl transition-colors">Cancel</button>
                <button type="button" data-us-delete-confirm class="px-5 py-2 bg-rose-500 hover:brightness-110 text-white text-xs font-extrabold rounded-xl shadow-md transition-colors flex items-center gap-1.5 disabled:opacity-60"><?= icon('trash-2', 'w-3.5 h-3.5') ?><span>Delete permanently</span></button>
            </div>
        </div>
    </div>

    <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 p-4 rounded-3xl bg-[#111111] border border-[#f3bd35]/20">
        <div>
            <h3 class="text-sm font-bold text-white">Users</h3>
            <p class="text-xs text-zinc-400">Everyone who can sign in to the 88MMM Executive Portal. Edit account details, access and passwords, or permanently delete an account.</p>
        </div>
        <form method="get" action="<?= e(base_path()) ?>/index.php" role="search" class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 w-full md:w-auto">
            <input type="hidden" name="view" value="admin">
            <input type="hidden" name="tab" value="users">
            <div class="relative w-full sm:w-72">
                <?= icon('search', 'w-4 h-4 text-zinc-400 absolute left-3 top-1/2 -translate-y-1/2') ?>
                <input id="us-q" name="q" type="search" value="<?= e($usQ) ?>" maxlength="100" placeholder="Name, email or ID" aria-label="Search users by name, email or ID" class="w-full pl-9 pr-3 py-2 rounded-xl bg-zinc-900 border border-zinc-700 text-xs text-white placeholder-zinc-500 focus:outline-none focus:border-[#f3bd35]">
            </div>
            <select id="us-sort" name="sort" aria-label="Sort users" class="px-3 py-2 rounded-xl bg-zinc-900 border border-zinc-700 text-xs text-white focus:outline-none focus:border-[#f3bd35]">
                <?php foreach (USER_SORTS as $usValue => [$usSortLabel]): ?>
                    <option value="<?= e($usValue) ?>"<?= $usValue === $usSort ? ' selected' : '' ?>><?= e($usSortLabel) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="px-4 py-2 bg-[#f3bd35] hover:bg-[#ffda72] text-black text-xs font-extrabold rounded-xl transition-colors">Apply</button>
            <?php if ($usQ !== '' || $usSort !== 'newest'): ?>
                <a href="<?= e(url('admin', ['tab' => 'users'])) ?>" class="px-3 py-2 text-xs text-zinc-400 hover:text-white text-center">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="rounded-3xl bg-[#111111] border border-[#f3bd35]/20 overflow-hidden shadow-xl">
        <div class="px-4 py-3 border-b border-zinc-800 text-[11px] text-zinc-400">
            <?= number_format($usTotal) ?> <?= $usTotal === 1 ? 'user' : 'users' ?><?php if ($usQ !== ''): ?> matching “<span class="text-white"><?= e($usQ) ?></span>”<?php endif; ?>
        </div>
        <?php if ($usRows): ?>
            <div class="hidden sm:block overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-[#181818] border-b border-zinc-800 text-zinc-400 uppercase text-[10px] tracking-wider">
                        <tr>
                            <th class="py-3 px-4" scope="col">User</th>
                            <th class="py-3 px-4" scope="col">Role</th>
                            <th class="py-3 px-4" scope="col">Status</th>
                            <th class="py-3 px-4" scope="col">Joined</th>
                            <th class="py-3 px-4" scope="col">Last sign-in</th>
                            <th class="py-3 px-4 text-right" scope="col" aria-label="Actions"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800/80">
                        <?php foreach ($usRows as $usRow): $usSelf = $usRow['id'] === $user['id']; $usFounder = $usRow['id'] === MASTER_PROFILE_ID; ?>
                            <tr class="hover:bg-zinc-900/50 transition-colors" data-us-row="<?= e($usRow['id']) ?>">
                                <td class="py-3 px-4"><?php $usIdentity($usRow, $usSelf); ?></td>
                                <td class="py-3 px-4"><span class="<?= $usRoleBadge($usRow['role']) ?>"><?= e($usRoleShort[$usRow['role']] ?? $usRow['role']) ?></span></td>
                                <td class="py-3 px-4"><?php $usStatus($usRow); ?></td>
                                <td class="py-3 px-4 text-zinc-400 text-[11px] tabular-nums"><?= e(substr((string) $usRow['created_at'], 0, 10)) ?></td>
                                <td class="py-3 px-4 text-zinc-400 text-[11px] tabular-nums"><?= $usRow['last_login_at'] ? e(fmt_datetime($usRow['last_login_at'])) : '—' ?></td>
                                <td class="py-3 px-4 text-right">
                                    <div class="flex items-center justify-end gap-1.5"><?php $usActions($usRow, $usSelf, $usFounder); ?></div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <ul class="sm:hidden divide-y divide-zinc-800/80 text-xs">
                <?php foreach ($usRows as $usRow): $usSelf = $usRow['id'] === $user['id']; $usFounder = $usRow['id'] === MASTER_PROFILE_ID; ?>
                    <li class="p-4 space-y-3" data-us-item="<?= e($usRow['id']) ?>">
                        <?php $usIdentity($usRow, $usSelf); ?>
                        <div class="flex flex-wrap items-center gap-2 text-[11px] text-zinc-400">
                            <span class="<?= $usRoleBadge($usRow['role']) ?>"><?= e($usRoleShort[$usRow['role']] ?? $usRow['role']) ?></span>
                            <?php $usStatus($usRow); ?>
                            <span class="tabular-nums">Joined <?= e(substr((string) $usRow['created_at'], 0, 10)) ?></span>
                        </div>
                        <div class="flex items-center gap-1.5"><?php $usActions($usRow, $usSelf, $usFounder); ?></div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="px-4 py-8 text-center text-xs text-zinc-500">No users match this search.</p>
        <?php endif; ?>
    </div>
    <?php if ($usPages > 1): ?>
        <nav class="flex items-center justify-center gap-3 text-xs" aria-label="Pages">
            <?php if ($usPage > 1): ?>
                <a href="<?= e($usPageUrl($usPage - 1)) ?>" class="px-3 py-1.5 rounded-xl bg-zinc-900 hover:bg-zinc-800 border border-zinc-700 text-zinc-200 transition-colors">← Previous</a>
            <?php endif; ?>
            <span class="text-zinc-400">Page <?= $usPage ?> of <?= $usPages ?></span>
            <?php if ($usPage < $usPages): ?>
                <a href="<?= e($usPageUrl($usPage + 1)) ?>" class="px-3 py-1.5 rounded-xl bg-zinc-900 hover:bg-zinc-800 border border-zinc-700 text-zinc-200 transition-colors">Next →</a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
</div>
