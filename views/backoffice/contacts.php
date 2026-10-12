<?php
/**
 * Backoffice tab 8 — Contacts: contact manager, auto responder and outbox
 * (includes/crm.php), with AI drafts (includes/ai.php: Groq or Claude).
 *
 * Sub-tabs (client-side, [data-tab-group="ct"], URL param "ct"):
 *   contacts  — search / stage filter (GET ct_q, ct_stage, ct_page), add, edit,
 *               delete; a contact's window shows their emails and writes a new
 *               one (AI follow-up draft, send now, copy)
 *   responder — auto responder messages sent N days after a contact is added
 *               (AI draft, placeholders, preview, on / off)
 *   outbox    — scheduled, sent, failed and cancelled emails (GET ob_status, ob_page)
 * Behaviour lives in assets/js/views/bo-contacts.js; actions in api/actions/crm.php.
 *
 * Uses: $user, $account, $boViewing. Local variables are prefixed with $ct.
 */
$ctOwner = $user['id'];
$ctTabs = ['contacts' => ['Contacts', 'users'], 'responder' => ['Auto Responder', 'send'], 'outbox' => ['Outbox', 'mail']];
$ctTab = (string) ($_GET['ct'] ?? 'contacts');
if (!isset($ctTabs[$ctTab])) {
    $ctTab = 'contacts';
}
$ctOn = 'bg-gradient-to-r from-[#ffda72] to-[#f3bd35] text-black shadow-md';
$ctOff = 'text-zinc-400 hover:text-white';
$ctInput = 'w-full px-3 py-2 rounded-xl bg-zinc-900 border border-zinc-700 text-white text-xs focus:outline-none focus:border-[#f3bd35]';
$ctLabel = 'block text-zinc-300 font-semibold mb-1 text-[11px]';
$ctBtn = 'px-4 py-2 rounded-xl bg-gradient-to-r from-[#ffda72] to-[#f3bd35] text-black font-extrabold text-xs shadow hover:brightness-105 transition-all flex items-center justify-center gap-1.5 disabled:opacity-60';
$ctBtn2 = 'px-3 py-2 rounded-xl bg-zinc-900 hover:bg-zinc-800 border border-zinc-700 text-zinc-200 font-semibold text-xs transition-colors flex items-center justify-center gap-1.5 disabled:opacity-60';
$ctSmall = 'px-2.5 py-1 bg-zinc-900 hover:bg-zinc-800 border border-zinc-700 text-zinc-300 hover:text-white text-[11px] font-medium rounded-lg transition-colors';
$ctStagePill = [
    'new' => 'bg-blue-500/15 text-blue-300', 'contacted' => 'bg-zinc-800 text-zinc-300', 'interested' => 'bg-amber-500/15 text-amber-300',
    'joined' => 'bg-emerald-500/15 text-emerald-400', 'not_interested' => 'bg-rose-500/15 text-rose-300',
];

// Contacts: filters and page.
$ctQ = mb_substr(trim(is_string($_GET['ct_q'] ?? null) ? $_GET['ct_q'] : ''), 0, 100);
$ctStage = is_string($_GET['ct_stage'] ?? null) && isset(CRM_STAGES[$_GET['ct_stage']]) ? $_GET['ct_stage'] : '';
$ctPage = max(1, (int) ($_GET['ct_page'] ?? 1));
[$ctRows, $ctTotal] = crm_list_contacts($ctOwner, $ctQ, $ctStage, $ctPage);
$ctPages = max(1, (int) ceil($ctTotal / CRM_PER_PAGE));
$ctCounts = crm_stage_counts($ctOwner);
$ctAll = array_sum($ctCounts);
$ctUrl = fn (array $p): string => url('backoffice', array_filter(['tab' => 'contacts', 'member' => $boViewing['id'] ?? '', 'head' => $boViewing ? $account['id'] : ''] + $p, fn ($v) => $v !== '' && $v !== null && $v !== 1));

// Auto responder and outbox.
$ctResponders = crm_responders($ctOwner);
$obStatus = is_string($_GET['ob_status'] ?? null) && in_array($_GET['ob_status'], ['scheduled', 'sent', 'failed', 'cancelled'], true) ? $_GET['ob_status'] : '';
$obPage = max(1, (int) ($_GET['ob_page'] ?? 1));
[$obRows, $obTotal] = crm_list_messages($ctOwner, $obStatus, $obPage);
$obPages = max(1, (int) ceil($obTotal / CRM_PER_PAGE));
$obStatusText = ['scheduled' => 'Scheduled', 'sending' => 'Sending', 'sent' => 'Sent', 'failed' => 'Failed', 'cancelled' => 'Cancelled'];
$obStatusPill = ['scheduled' => 'text-amber-300', 'sending' => 'text-amber-300', 'sent' => 'text-emerald-400', 'failed' => 'text-rose-300', 'cancelled' => 'text-zinc-500'];

$ctMail = mail_transport();
$ctAi = ai_enabled();
$ctMember = crm_member($ctOwner);
$pageData['contacts'] = [
    'rows' => array_column(array_map('crm_contact_to_array', $ctRows), null, 'id'),
    'responders' => array_column($ctResponders, null, 'id'),
    'placeholders' => CRM_PLACEHOLDERS,
    'stages' => CRM_STAGES,
    'sources' => CRM_SOURCES,
    'stagePill' => $ctStagePill,
    'member' => $ctMember,
    'aiEnabled' => $ctAi,
    'readOnlyMember' => $boViewing['id'] ?? null,
];
?>
<div class="space-y-5" data-bo-contacts>
    <div class="flex items-center gap-1.5 p-1.5 bg-[#121212] rounded-2xl border border-zinc-800 overflow-x-auto text-xs" data-tab-group="ct" data-param="ct" role="tablist">
        <?php foreach ($ctTabs as $ctId => [$ctText, $ctIcon]): ?>
            <button type="button" data-tab="<?= $ctId ?>" data-group="ct" data-on="<?= $ctOn ?>" data-off="<?= $ctOff ?>" aria-selected="<?= $ctId === $ctTab ? 'true' : 'false' ?>" class="flex items-center gap-2 px-4 py-2 rounded-xl font-bold whitespace-nowrap transition-all <?= $ctId === $ctTab ? $ctOn : $ctOff ?>">
                <?= icon($ctIcon, 'w-4 h-4') ?>
                <span><?= e($ctText) ?><?= $ctId === 'contacts' ? ' (' . number_format($ctAll) . ')' : '' ?></span>
            </button>
        <?php endforeach; ?>
    </div>

    <?php if ($ctMail === 'off'): ?>
        <div class="p-3 rounded-2xl bg-amber-950/40 border border-amber-600/40 text-amber-200 text-xs flex items-start gap-2" role="status">
            <?= icon('circle-alert', 'w-4 h-4 shrink-0 mt-0.5') ?>
            <span>Email sending isn't set up yet. Auto responder and personal emails wait in your Outbox as <strong>Scheduled</strong> and go out once the administrator sets up email.</span>
        </div>
    <?php elseif ($ctMail === 'log'): ?>
        <div class="p-3 rounded-2xl bg-zinc-900 border border-zinc-700 text-zinc-300 text-xs flex items-start gap-2" role="status">
            <?= icon('file-code', 'w-4 h-4 shrink-0 mt-0.5 text-[#f3bd35]') ?>
            <span>Development mail mode: emails are written to the server's mail log (storage/mail/mail.log) instead of being delivered.</span>
        </div>
    <?php endif; ?>

    <!-- Contacts -->
    <div class="space-y-4" data-tab-panel="contacts" data-group="ct"<?= $ctTab === 'contacts' ? '' : ' hidden' ?>>
        <div class="p-5 rounded-3xl bg-[#121212] border border-zinc-800 space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                    <h3 class="text-base font-bold text-white flex items-center gap-2"><?= icon('users', 'w-5 h-5 text-[#f3bd35]') ?>Contact Manager</h3>
                    <p class="text-xs text-zinc-400 mt-0.5">Your prospects and contacts. Open one to see the emails they got and to write a new one, with AI help.</p>
                </div>
                <button type="button" data-ct-add class="<?= $ctBtn ?> shrink-0"><?= icon('user-plus', 'w-4 h-4') ?>Add Contact</button>
            </div>
            <div class="flex flex-wrap gap-1.5 text-[11px]">
                <a href="<?= e($ctUrl(['ct_q' => $ctQ])) ?>" class="px-3 py-1.5 rounded-full border font-semibold <?= $ctStage === '' ? 'border-[#f3bd35] text-[#ffda72] bg-[#f3bd35]/10' : 'border-zinc-700 text-zinc-400 hover:text-white' ?>">All <?= number_format($ctAll) ?></a>
                <?php foreach (CRM_STAGES as $ctKey => $ctText): ?>
                    <a href="<?= e($ctUrl(['ct_q' => $ctQ, 'ct_stage' => $ctKey])) ?>" class="px-3 py-1.5 rounded-full border font-semibold <?= $ctStage === $ctKey ? 'border-[#f3bd35] text-[#ffda72] bg-[#f3bd35]/10' : 'border-zinc-700 text-zinc-400 hover:text-white' ?>"><?= e($ctText) ?> <?= number_format($ctCounts[$ctKey]) ?></a>
                <?php endforeach; ?>
            </div>
            <form method="get" action="<?= e(base_path()) ?>/index.php" role="search" class="flex flex-col sm:flex-row gap-2">
                <input type="hidden" name="view" value="backoffice">
                <input type="hidden" name="tab" value="contacts">
                <?php if ($boViewing): ?>
                    <input type="hidden" name="member" value="<?= e($boViewing['id']) ?>">
                    <input type="hidden" name="head" value="<?= e($account['id']) ?>">
                <?php endif; ?>
                <?php if ($ctStage !== ''): ?><input type="hidden" name="ct_stage" value="<?= e($ctStage) ?>"><?php endif; ?>
                <div class="relative flex-1">
                    <?= icon('search', 'w-4 h-4 text-zinc-500 absolute left-3 top-2.5') ?>
                    <input type="search" name="ct_q" value="<?= e($ctQ) ?>" maxlength="100" aria-label="Search contacts" placeholder="Search name, email, phone, company or tag" class="<?= $ctInput ?> pl-9">
                </div>
                <button type="submit" class="<?= $ctBtn2 ?>">Search</button>
                <?php if ($ctQ !== '' || $ctStage !== ''): ?>
                    <a href="<?= e($ctUrl([])) ?>" class="px-3 py-2 text-xs text-zinc-400 hover:text-white text-center">Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="rounded-3xl bg-[#121212] border border-zinc-800 overflow-hidden shadow-xl">
            <?php if ($ctRows): ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-zinc-900/80 border-b border-zinc-800 text-zinc-400 uppercase text-[10px] tracking-wider">
                            <tr>
                                <th class="py-3 px-4" scope="col">Name</th>
                                <th class="py-3 px-4" scope="col">Email / Phone</th>
                                <th class="py-3 px-4" scope="col">Stage</th>
                                <th class="py-3 px-4" scope="col">Source</th>
                                <th class="py-3 px-4" scope="col">Next email</th>
                                <th class="py-3 px-4" scope="col">Last contacted</th>
                                <th class="py-3 px-4 text-right" scope="col" aria-label="Actions"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-800/80">
                            <?php foreach ($ctRows as $ctRow): ?>
                                <tr class="hover:bg-zinc-900/40 transition-colors" data-ct-row="<?= (int) $ctRow['id'] ?>">
                                    <td class="py-3 px-4">
                                        <button type="button" data-ct-open="<?= (int) $ctRow['id'] ?>" class="font-bold text-white hover:text-[#ffda72] text-left"><?= e($ctRow['name']) ?></button>
                                        <?php if ($ctRow['company'] !== ''): ?><div class="text-[10px] text-zinc-500"><?= e($ctRow['company']) ?></div><?php endif; ?>
                                    </td>
                                    <td class="py-3 px-4 text-zinc-300">
                                        <div><?= $ctRow['email'] !== '' ? e($ctRow['email']) : '<span class="text-zinc-600">No email</span>' ?><?= $ctRow['unsubscribed_at'] ? ' <span class="text-[9px] uppercase font-bold text-rose-300">Unsubscribed</span>' : '' ?></div>
                                        <?php if ($ctRow['phone'] !== ''): ?><div class="text-[10px] text-zinc-500"><?= e($ctRow['phone']) ?></div><?php endif; ?>
                                    </td>
                                    <td class="py-3 px-4 whitespace-nowrap"><span class="text-[10px] px-2 py-0.5 rounded-full font-bold <?= $ctStagePill[$ctRow['stage']] ?>"><?= e(CRM_STAGES[$ctRow['stage']]) ?></span></td>
                                    <td class="py-3 px-4 text-zinc-400 whitespace-nowrap"><?= e(CRM_SOURCES[$ctRow['source']]) ?></td>
                                    <td class="py-3 px-4 text-zinc-400 whitespace-nowrap"><?= $ctRow['next_send_at'] ? e(fmt_date($ctRow['next_send_at'])) : '<span class="text-zinc-600">—</span>' ?></td>
                                    <td class="py-3 px-4 text-zinc-400 whitespace-nowrap"><?= $ctRow['last_contacted_at'] ? e(fmt_date($ctRow['last_contacted_at'])) : '<span class="text-zinc-600">Never</span>' ?></td>
                                    <td class="py-3 px-4 text-right whitespace-nowrap">
                                        <button type="button" data-ct-open="<?= (int) $ctRow['id'] ?>" class="<?= $ctSmall ?>">Open</button>
                                        <button type="button" data-ct-edit="<?= (int) $ctRow['id'] ?>" class="<?= $ctSmall ?>">Edit</button>
                                        <button type="button" data-ct-delete="<?= (int) $ctRow['id'] ?>" class="<?= $ctSmall ?> hover:text-rose-300">Delete</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($ctPages > 1): ?>
                    <div class="px-4 py-3 border-t border-zinc-800 flex items-center justify-between text-[11px] text-zinc-400">
                        <span><?= number_format($ctTotal) ?> contacts · page <?= $ctPage ?> of <?= $ctPages ?></span>
                        <div class="flex gap-1.5">
                            <?php if ($ctPage > 1): ?><a href="<?= e($ctUrl(['ct_q' => $ctQ, 'ct_stage' => $ctStage, 'ct_page' => $ctPage - 1])) ?>" class="<?= $ctSmall ?>">Previous</a><?php endif; ?>
                            <?php if ($ctPage < $ctPages): ?><a href="<?= e($ctUrl(['ct_q' => $ctQ, 'ct_stage' => $ctStage, 'ct_page' => $ctPage + 1])) ?>" class="<?= $ctSmall ?>">Next</a><?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="px-4 py-12 text-center text-xs text-zinc-500">
                    <?= $ctQ !== '' || $ctStage !== '' ? 'No contacts match.' : 'No contacts yet. Add the people you meet, and your auto responder follows up with them.' ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Auto responder -->
    <div class="space-y-4" data-tab-panel="responder" data-group="ct"<?= $ctTab === 'responder' ? '' : ' hidden' ?>>
        <div class="p-5 rounded-3xl bg-[#121212] border border-zinc-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <h3 class="text-base font-bold text-white flex items-center gap-2"><?= icon('send', 'w-5 h-5 text-[#f3bd35]') ?>Auto Responder</h3>
                <p class="text-xs text-zinc-400 mt-0.5">Emails that go out by themselves to every contact you add with an email address: day 0 right away, then the follow-ups on the days you choose. They stop when a contact unsubscribes or you mark them Joined or Not interested.</p>
            </div>
            <button type="button" data-ar-add class="<?= $ctBtn ?> shrink-0"><?= icon('plus', 'w-4 h-4') ?>New Message</button>
        </div>
        <?php if ($ctResponders): ?>
            <div class="space-y-3">
                <?php foreach ($ctResponders as $ctR): ?>
                    <div class="p-4 rounded-2xl bg-[#121212] border <?= $ctR['active'] ? 'border-[#f3bd35]/30' : 'border-zinc-800' ?> flex flex-col md:flex-row md:items-center gap-3" data-ar-row="<?= $ctR['id'] ?>">
                        <div class="shrink-0 w-16 h-16 rounded-2xl <?= $ctR['active'] ? 'bg-[#f3bd35]/15 text-[#ffda72]' : 'bg-zinc-900 text-zinc-500' ?> flex flex-col items-center justify-center font-black">
                            <span class="text-[9px] uppercase tracking-wider">Day</span>
                            <span class="text-xl leading-tight"><?= $ctR['delayDays'] ?></span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-bold text-white text-sm"><?= e($ctR['name']) ?></span>
                                <span class="text-[9px] px-2 py-0.5 rounded-full uppercase font-bold <?= $ctR['active'] ? 'bg-emerald-500/15 text-emerald-400' : 'bg-zinc-800 text-zinc-400' ?>"><?= $ctR['active'] ? 'On' : 'Off' ?></span>
                                <span class="text-[10px] text-zinc-500"><?= $ctR['source'] === 'all' ? 'All new contacts' : 'Contacts from: ' . e(CRM_SOURCES[$ctR['source']]) ?></span>
                            </div>
                            <?php if ($ctR['writtenBy'] === 'ai'): ?>
                                <div class="text-xs text-zinc-300 mt-1 truncate"><?= icon('sparkles', 'w-3 h-3 inline-block text-[#ffda72]', ['style' => 'vertical-align:-1px']) ?> AI writes each email · <?= e(CRM_AI_RESPONDER_GOALS[$ctR['aiGoal']] ?? '') ?></div>
                                <div class="text-[11px] text-zinc-500 mt-0.5 truncate"><?= e((CRM_AI_TONES[$ctR['aiTone']] ?? '') . ' · ' . explode(' (', CRM_AI_LANGUAGES[$ctR['aiLanguage']] ?? '')[0] . ($ctR['aiInstructions'] !== '' ? ' · ' . $ctR['aiInstructions'] : '')) ?></div>
                            <?php else: ?>
                                <div class="text-xs text-zinc-300 mt-1 truncate">Subject: <?= e($ctR['subject']) ?></div>
                                <div class="text-[11px] text-zinc-500 mt-0.5 truncate"><?= e(mb_substr(preg_replace('/\s+/', ' ', $ctR['body']), 0, 160)) ?></div>
                            <?php endif; ?>
                            <div class="text-[10px] text-zinc-500 mt-1"><?= number_format($ctR['scheduled']) ?> scheduled · <?= number_format($ctR['sent']) ?> sent</div>
                        </div>
                        <div class="flex flex-wrap gap-1.5 shrink-0">
                            <button type="button" data-ar-active="<?= $ctR['id'] ?>" data-to="<?= $ctR['active'] ? '0' : '1' ?>" class="<?= $ctSmall ?>"><?= $ctR['active'] ? 'Turn off' : 'Turn on' ?></button>
                            <button type="button" data-ar-edit="<?= $ctR['id'] ?>" class="<?= $ctSmall ?>">Edit</button>
                            <button type="button" data-ar-delete="<?= $ctR['id'] ?>" class="<?= $ctSmall ?> hover:text-rose-300">Delete</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="p-6 rounded-3xl bg-[#121212] border border-dashed border-zinc-700 text-center text-xs text-zinc-400">
                No auto responder messages yet. Add a welcome email for day 0, then follow-ups on later days<?= $ctAi ? ', or let AI write them for you' : '' ?>.
            </div>
        <?php endif; ?>
    </div>

    <!-- Outbox -->
    <div class="space-y-4" data-tab-panel="outbox" data-group="ct"<?= $ctTab === 'outbox' ? '' : ' hidden' ?>>
        <div class="flex flex-wrap gap-1.5 text-[11px]">
            <?php foreach (['' => 'All', 'scheduled' => 'Scheduled', 'sent' => 'Sent', 'failed' => 'Failed', 'cancelled' => 'Cancelled'] as $obKey => $obText): ?>
                <a href="<?= e($ctUrl(['ct' => 'outbox', 'ob_status' => $obKey])) ?>" class="px-3 py-1.5 rounded-full border font-semibold <?= $obStatus === $obKey ? 'border-[#f3bd35] text-[#ffda72] bg-[#f3bd35]/10' : 'border-zinc-700 text-zinc-400 hover:text-white' ?>"><?= $obText ?></a>
            <?php endforeach; ?>
        </div>
        <div class="rounded-3xl bg-[#121212] border border-zinc-800 overflow-hidden shadow-xl">
            <?php if ($obRows): ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-zinc-900/80 border-b border-zinc-800 text-zinc-400 uppercase text-[10px] tracking-wider">
                            <tr>
                                <th class="py-3 px-4" scope="col">To</th>
                                <th class="py-3 px-4" scope="col">Subject</th>
                                <th class="py-3 px-4" scope="col">Type</th>
                                <th class="py-3 px-4" scope="col">Status</th>
                                <th class="py-3 px-4" scope="col">When</th>
                                <th class="py-3 px-4 text-right" scope="col" aria-label="Actions"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-800/80">
                            <?php foreach ($obRows as $obRow): $obM = crm_message_to_array($obRow); ?>
                                <tr data-ob-row="<?= $obM['id'] ?>">
                                    <td class="py-3 px-4">
                                        <button type="button" data-ct-open="<?= $obM['contactId'] ?>" class="font-semibold text-white hover:text-[#ffda72] text-left"><?= e($obM['contactName']) ?></button>
                                        <div class="text-[10px] text-zinc-500"><?= e($obM['toEmail']) ?></div>
                                    </td>
                                    <td class="py-3 px-4 text-zinc-300"><?= e($obM['subject']) ?></td>
                                    <td class="py-3 px-4 text-zinc-400 whitespace-nowrap"><?= $obM['type'] === 'auto' ? 'Auto: ' . e((string) $obM['responderName']) : 'Personal' ?></td>
                                    <td class="py-3 px-4 whitespace-nowrap">
                                        <span class="font-semibold <?= $obStatusPill[$obM['status']] ?>"><?= $obStatusText[$obM['status']] ?></span>
                                        <?php if ($obM['note'] !== ''): ?><div class="text-[10px] text-zinc-500 max-w-xs" style="white-space:normal"><?= e($obM['note']) ?></div><?php endif; ?>
                                    </td>
                                    <td class="py-3 px-4 text-zinc-400 whitespace-nowrap"><?= e($obM['when']) ?></td>
                                    <td class="py-3 px-4 text-right whitespace-nowrap">
                                        <?php if ($obM['status'] === 'scheduled'): ?><button type="button" data-ob-cancel="<?= $obM['id'] ?>" class="<?= $ctSmall ?>">Cancel</button><?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($obPages > 1): ?>
                    <div class="px-4 py-3 border-t border-zinc-800 flex items-center justify-between text-[11px] text-zinc-400">
                        <span><?= number_format($obTotal) ?> emails · page <?= $obPage ?> of <?= $obPages ?></span>
                        <div class="flex gap-1.5">
                            <?php if ($obPage > 1): ?><a href="<?= e($ctUrl(['ct' => 'outbox', 'ob_status' => $obStatus, 'ob_page' => $obPage - 1])) ?>" class="<?= $ctSmall ?>">Previous</a><?php endif; ?>
                            <?php if ($obPage < $obPages): ?><a href="<?= e($ctUrl(['ct' => 'outbox', 'ob_status' => $obStatus, 'ob_page' => $obPage + 1])) ?>" class="<?= $ctSmall ?>">Next</a><?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="px-4 py-12 text-center text-xs text-zinc-500"><?= $obStatus !== '' ? 'No emails with this status.' : 'No emails yet. Scheduled auto responder emails and the emails you send appear here.' ?></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// The AI controls shared by the follow-up and auto responder windows. $saved:
// the choices are form fields saved with the message (aiGoal, aiTone, ...).
$ctAdmin = is_admin($boAdmin);
$ctAiPanel = function (string $prefix, array $goals, bool $saved = false) use ($ctAi, $ctAdmin, $ctInput, $ctLabel, $ctBtn2): void {
    $name = fn (string $field): string => $saved ? ' name="' . $field . '"' : ''; ?>
    <div class="p-3 rounded-2xl bg-[#f3bd35]/5 border border-[#f3bd35]/30 space-y-2.5" data-ai-panel="<?= $prefix ?>">
        <div class="flex items-center gap-1.5 text-[11px] font-bold text-[#ffda72] uppercase tracking-wider"><?= icon('sparkles', 'w-3.5 h-3.5') ?>Write with AI<?= $ctAi ? ' · ' . e(ai_provider_name()) : '' ?></div>
        <?php if (!$ctAi): ?>
            <?php if ($ctAdmin): ?>
                <p class="text-[11px] text-zinc-400">AI assistance is off. To turn it on, add an API key in <span class="font-mono text-zinc-300">config/config.php</span> under <span class="font-mono text-zinc-300">'ai'</span>: <span class="font-mono text-zinc-300">'groq' =&gt; ['api_key' =&gt; 'gsk_…']</span> with <span class="font-mono text-zinc-300">'provider' =&gt; 'groq'</span> (console.groq.com), or an Anthropic key for Claude.</p>
            <?php else: ?>
                <p class="text-[11px] text-zinc-400">AI assistance is off. Ask the 88MMM administrator to turn it on.</p>
            <?php endif; ?>
        <?php else: ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                <div style="grid-column:1/-1">
                    <label for="<?= $prefix ?>-goal" class="<?= $ctLabel ?>">Goal</label>
                    <select id="<?= $prefix ?>-goal" data-ai="goal"<?= $name('aiGoal') ?> class="<?= $ctInput ?>">
                        <?php foreach ($goals as $k => $v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="<?= $prefix ?>-tone" class="<?= $ctLabel ?>">Tone</label>
                    <select id="<?= $prefix ?>-tone" data-ai="tone"<?= $name('aiTone') ?> class="<?= $ctInput ?>">
                        <?php foreach (CRM_AI_TONES as $k => $v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="<?= $prefix ?>-language" class="<?= $ctLabel ?>">Language</label>
                    <select id="<?= $prefix ?>-language" data-ai="language"<?= $name('aiLanguage') ?> class="<?= $ctInput ?>">
                        <?php // Short names here; the AI gets the full description. ?>
                        <?php foreach (CRM_AI_LANGUAGES as $k => $v): ?><option value="<?= e($k) ?>" title="<?= e($v) ?>"><?= e(explode(' (', $v)[0]) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div style="grid-column:1/-1">
                    <label for="<?= $prefix ?>-extra" class="<?= $ctLabel ?>">Anything to add? (optional)</label>
                    <input id="<?= $prefix ?>-extra" type="text" data-ai="instructions"<?= $name('aiInstructions') ?> maxlength="600" placeholder="e.g. mention the Saturday 2 PM Zoom" class="<?= $ctInput ?>">
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" data-ai-draft="<?= $prefix ?>" class="<?= $ctBtn2 ?> border-[#f3bd35]/50 text-[#ffda72]"><?= icon('sparkles', 'w-3.5 h-3.5') ?><span data-ai-draft-label>Draft with AI</span></button>
                <span class="text-[10px] text-zinc-500" data-ai-hint>AI writes a draft into the fields below. Check it before you save or send.</span>
            </div>
        <?php endif; ?>
    </div>
<?php }; ?>

<!-- Add / edit contact -->
<div data-modal="bo-ct-edit" hidden class="fixed inset-0 z-50 flex items-center justify-center bg-black/85 backdrop-blur-md p-4 animate-in fade-in" role="dialog" aria-modal="true" aria-labelledby="bo-ct-edit-title">
    <div class="w-full max-w-lg rounded-3xl bg-[#141414] border border-[#f3bd35]/40 p-6 shadow-2xl relative text-left max-h-[92vh] overflow-y-auto">
        <button type="button" data-close-modal aria-label="Close" class="absolute top-4 right-4 text-zinc-400 hover:text-white p-1 rounded-full"><?= icon('x', 'w-5 h-5') ?></button>
        <h3 id="bo-ct-edit-title" class="text-lg font-bold text-white mb-4" data-ct-edit-title>Add Contact</h3>
        <form data-ct-form class="space-y-3 text-xs" novalidate>
            <input type="hidden" name="id" value="">
            <div data-ct-form-error hidden role="alert" class="p-3 rounded-xl bg-rose-950/40 border border-rose-500/40 text-rose-300"></div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div class="sm:col-span-2">
                    <label for="ct-f-name" class="<?= $ctLabel ?>">Name *</label>
                    <input id="ct-f-name" name="name" type="text" required maxlength="150" autocomplete="off" class="<?= $ctInput ?>">
                </div>
                <div>
                    <label for="ct-f-email" class="<?= $ctLabel ?>">Email</label>
                    <input id="ct-f-email" name="email" type="email" maxlength="190" autocomplete="off" class="<?= $ctInput ?>">
                </div>
                <div>
                    <label for="ct-f-phone" class="<?= $ctLabel ?>">Phone</label>
                    <input id="ct-f-phone" name="phone" type="tel" maxlength="60" autocomplete="off" class="<?= $ctInput ?>">
                </div>
                <div class="sm:col-span-2">
                    <label for="ct-f-company" class="<?= $ctLabel ?>">Company / Work</label>
                    <input id="ct-f-company" name="company" type="text" maxlength="190" autocomplete="off" class="<?= $ctInput ?>">
                </div>
                <div>
                    <label for="ct-f-stage" class="<?= $ctLabel ?>">Stage</label>
                    <select id="ct-f-stage" name="stage" class="<?= $ctInput ?>">
                        <?php foreach (CRM_STAGES as $k => $v): ?><option value="<?= $k ?>"><?= e($v) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="ct-f-source" class="<?= $ctLabel ?>">How you met</label>
                    <select id="ct-f-source" name="source" class="<?= $ctInput ?>">
                        <?php foreach (CRM_SOURCES as $k => $v): ?><option value="<?= $k ?>"><?= e($v) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label for="ct-f-tags" class="<?= $ctLabel ?>">Tags (separate with commas)</label>
                    <input id="ct-f-tags" name="tags" type="text" maxlength="255" autocomplete="off" placeholder="e.g. Batangas, business owner" class="<?= $ctInput ?>">
                </div>
                <div class="sm:col-span-2">
                    <label for="ct-f-notes" class="<?= $ctLabel ?>">Notes</label>
                    <textarea id="ct-f-notes" name="notes" rows="3" maxlength="5000" placeholder="What you talked about, what they're looking for…" class="<?= $ctInput ?>"></textarea>
                </div>
            </div>
            <label class="flex items-start gap-2 p-3 rounded-xl bg-zinc-950 border border-zinc-800 cursor-pointer">
                <input type="checkbox" name="autoFollow" checked class="mt-0.5" style="accent-color:#f3bd35">
                <span><span class="font-semibold text-white">Send my auto responder emails</span><span class="block text-[10px] text-zinc-500 mt-0.5">They're scheduled when a contact with an email address is added. Untick to stop them for this contact.</span></span>
            </label>
            <div data-ct-unsub-note hidden class="p-3 rounded-xl bg-rose-950/30 border border-rose-500/30 text-rose-300 text-[11px]">This contact unsubscribed, so they get no more emails from you.</div>
            <div class="flex justify-end gap-2 pt-1">
                <button type="button" data-close-modal class="<?= $ctBtn2 ?>">Cancel</button>
                <button type="submit" class="<?= $ctBtn ?>">Save Contact</button>
            </div>
        </form>
    </div>
</div>

<!-- One contact: details, email history and a new email -->
<div data-modal="bo-ct-detail" hidden class="fixed inset-0 z-50 flex items-center justify-center bg-black/85 backdrop-blur-md p-4 animate-in fade-in" role="dialog" aria-modal="true" aria-labelledby="bo-ct-detail-name">
    <div class="w-full max-w-4xl rounded-3xl bg-[#141414] border border-[#f3bd35]/40 p-6 shadow-2xl relative text-left max-h-[92vh] overflow-y-auto">
        <button type="button" data-close-modal aria-label="Close" class="absolute top-4 right-4 text-zinc-400 hover:text-white p-1 rounded-full"><?= icon('x', 'w-5 h-5') ?></button>
        <div data-cd-loading class="py-12 text-center text-xs text-zinc-400">Loading…</div>
        <div data-cd-body hidden class="grid grid-cols-1 md:grid-cols-2 gap-6 text-xs">
            <div class="space-y-4">
                <div>
                    <h3 id="bo-ct-detail-name" class="text-lg font-bold text-white" data-cd="name" style="padding-right:2rem"></h3>
                    <div class="flex flex-wrap items-center gap-2 mt-1">
                        <span class="text-[10px] px-2 py-0.5 rounded-full font-bold" data-cd="stage"></span>
                        <span class="text-[10px] text-zinc-500" data-cd="meta"></span>
                        <button type="button" data-cd-edit class="<?= $ctSmall ?>">Edit</button>
                    </div>
                </div>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-3 p-3.5 rounded-2xl bg-zinc-950 border border-zinc-800 text-[11px]">
                    <div><dt class="text-zinc-500">Email</dt><dd class="text-zinc-200 break-words" data-cd="email"></dd></div>
                    <div><dt class="text-zinc-500">Phone</dt><dd class="text-zinc-200" data-cd="phone"></dd></div>
                    <div><dt class="text-zinc-500">Company</dt><dd class="text-zinc-200" data-cd="company"></dd></div>
                    <div><dt class="text-zinc-500">Tags</dt><dd class="text-zinc-200" data-cd="tags"></dd></div>
                    <div class="sm:col-span-2"><dt class="text-zinc-500">Notes</dt><dd class="text-zinc-200 whitespace-pre-line" data-cd="notes"></dd></div>
                </dl>
                <div>
                    <h4 class="text-[11px] font-bold text-zinc-300 uppercase tracking-wider mb-2">Emails</h4>
                    <ul class="space-y-2" data-cd="history"></ul>
                </div>
            </div>
            <div class="space-y-3">
                <h4 class="text-sm font-bold text-white flex items-center gap-2"><?= icon('mail', 'w-4 h-4 text-[#f3bd35]') ?><span>Write to <span data-cd="first"></span></span></h4>
                <?php $ctAiPanel('cd-ai', CRM_AI_FOLLOWUP_GOALS); ?>
                <p data-cd-tip hidden class="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 text-[11px]"></p>
                <div>
                    <label for="cd-subject" class="<?= $ctLabel ?>">Subject</label>
                    <input id="cd-subject" type="text" maxlength="200" data-cd-subject class="<?= $ctInput ?>">
                </div>
                <div>
                    <label for="cd-body" class="<?= $ctLabel ?>">Message</label>
                    <textarea id="cd-body" rows="9" maxlength="10000" data-cd-message class="<?= $ctInput ?>"></textarea>
                </div>
                <p data-cd-blocked hidden class="text-[11px] text-rose-300"></p>
                <div class="flex flex-wrap gap-2 justify-end">
                    <button type="button" data-cd-copy class="<?= $ctBtn2 ?>"><?= icon('copy', 'w-3.5 h-3.5') ?>Copy</button>
                    <button type="button" data-cd-send class="<?= $ctBtn ?>"><?= icon('send', 'w-3.5 h-3.5') ?><span>Send Email</span></button>
                </div>
                <p class="text-[10px] text-zinc-500">Copy it to paste into Messenger or Viber instead. Emails include a line that lets the contact unsubscribe.</p>
            </div>
        </div>
    </div>
</div>

<!-- Add / edit an auto responder message -->
<div data-modal="bo-ct-responder" hidden class="fixed inset-0 z-50 flex items-center justify-center bg-black/85 backdrop-blur-md p-4 animate-in fade-in" role="dialog" aria-modal="true" aria-labelledby="bo-ct-responder-title">
    <div class="w-full max-w-4xl rounded-3xl bg-[#141414] border border-[#f3bd35]/40 p-6 shadow-2xl relative text-left max-h-[92vh] overflow-y-auto">
        <button type="button" data-close-modal aria-label="Close" class="absolute top-4 right-4 text-zinc-400 hover:text-white p-1 rounded-full"><?= icon('x', 'w-5 h-5') ?></button>
        <h3 id="bo-ct-responder-title" class="text-lg font-bold text-white mb-4" data-ar-title>New Auto Responder Message</h3>
        <form data-ar-form class="grid grid-cols-1 md:grid-cols-2 gap-6 text-xs" novalidate>
            <input type="hidden" name="id" value="">
            <div class="space-y-3">
                <div data-ar-error hidden role="alert" class="p-3 rounded-xl bg-rose-950/40 border border-rose-500/40 text-rose-300"></div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div class="sm:col-span-2">
                        <label for="ar-f-name" class="<?= $ctLabel ?>">Name (only you see it)</label>
                        <input id="ar-f-name" name="name" type="text" maxlength="120" placeholder="e.g. Welcome" class="<?= $ctInput ?>">
                    </div>
                    <div>
                        <label for="ar-f-delay" class="<?= $ctLabel ?>">Send on day</label>
                        <input id="ar-f-delay" name="delayDays" type="number" min="0" max="365" value="0" class="<?= $ctInput ?>">
                    </div>
                    <div style="grid-column:1/-1">
                        <label for="ar-f-source" class="<?= $ctLabel ?>">Send to</label>
                        <select id="ar-f-source" name="source" class="<?= $ctInput ?>">
                            <option value="all">All new contacts</option>
                            <?php foreach (CRM_SOURCES as $k => $v): ?><option value="<?= $k ?>">Contacts from: <?= e($v) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div style="grid-column:1/-1">
                        <label for="ar-f-writer" class="<?= $ctLabel ?>">Who writes it</label>
                        <select id="ar-f-writer" name="writtenBy" class="<?= $ctInput ?>">
                            <option value="ai"<?= $ctAi ? '' : ' disabled' ?>>AI writes a personal email for each contact<?= $ctAi ? '' : ' (AI is off)' ?></option>
                            <option value="member">I write one message for everyone</option>
                        </select>
                    </div>
                </div>
                <div data-ar-by-ai hidden class="p-3 rounded-xl bg-[#f3bd35]/5 border border-[#f3bd35]/30 text-zinc-300 text-[11px] space-y-1.5">
                    <p>AI writes each email when it's due, for that contact: it uses their name, company, how you met, your notes and the emails they already got, with the goal, tone and language you choose on the right.</p>
                    <p>Nobody reads it before it goes out, so add anything it must say (or avoid) under "Anything to add?". Every email it sends is kept in your Outbox. <strong class="text-white">Show an example</strong> lets you see one first.</p>
                </div>
                <div class="space-y-3" data-ar-mine>
                    <div>
                        <label for="ar-f-subject" class="<?= $ctLabel ?>">Subject *</label>
                        <input id="ar-f-subject" name="subject" type="text" maxlength="200" data-ar-field class="<?= $ctInput ?>">
                    </div>
                    <div>
                        <label for="ar-f-body" class="<?= $ctLabel ?>">Message *</label>
                        <textarea id="ar-f-body" name="body" rows="10" maxlength="10000" data-ar-field class="<?= $ctInput ?>"></textarea>
                    </div>
                    <div>
                        <span class="<?= $ctLabel ?>">Insert a placeholder</span>
                        <div class="flex flex-wrap gap-1">
                            <?php foreach (CRM_PLACEHOLDERS as $k => $v): ?>
                                <button type="button" data-ar-insert="<?= e($k) ?>" title="<?= e($v) ?>" class="px-2 py-1 rounded-lg bg-zinc-900 border border-zinc-700 hover:border-[#f3bd35] text-[10px] font-mono text-zinc-300"><?= e($k) ?></button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="active" checked style="accent-color:#f3bd35">
                    <span class="font-semibold text-white">On: send it to new contacts</span>
                </label>
            </div>
            <div class="space-y-3">
                <?php $ctAiPanel('ar-ai', CRM_AI_RESPONDER_GOALS, true); ?>
                <div class="p-3 rounded-2xl bg-zinc-950 border border-zinc-800">
                    <div class="text-[11px] font-bold text-zinc-300 uppercase tracking-wider mb-2" data-ar-preview-title>Preview for a contact named Juan Dela Cruz</div>
                    <div class="text-white font-semibold" data-ar-preview-subject></div>
                    <div class="mt-2 text-zinc-300 whitespace-pre-line" data-ar-preview-body></div>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" data-close-modal class="<?= $ctBtn2 ?>">Cancel</button>
                    <button type="submit" class="<?= $ctBtn ?>">Save Message</button>
                </div>
            </div>
        </form>
    </div>
</div>
