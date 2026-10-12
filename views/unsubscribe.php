<?php
/**
 * Unsubscribe page for a member's contacts (?view=unsubscribe&t=<token>; the
 * link is at the bottom of every email from Backoffice > Contacts). Opening
 * the link only asks; the button confirms (POST), so email link scanners
 * can't unsubscribe anyone.
 */
$unToken = is_string($_POST['t'] ?? null) ? $_POST['t'] : (is_string($_GET['t'] ?? null) ? $_GET['t'] : '');
$unContact = crm_contact_by_token($unToken);
if ($unContact && request_method() === 'POST' && $unContact['unsubscribed_at'] === null) {
    crm_unsubscribe($unContact);
    $unContact['unsubscribed_at'] = date('Y-m-d H:i:s');
}
$unMember = $unContact ? $unContact['member_name'] : '';
$pageMeta = ['title' => 'Unsubscribe · 88MMM Circle', 'description' => 'Stop emails from a 88MMM Circle member.', 'noindex' => true];
?>
<div class="max-w-md mx-auto py-12 px-1">
    <div class="p-6 rounded-3xl bg-[#121212] border border-[#f3bd35]/30 text-center space-y-4 shadow-2xl">
        <?php if (!$unContact): ?>
            <?= icon('circle-alert', 'w-10 h-10 text-amber-300 mx-auto') ?>
            <h1 class="text-lg font-bold text-white">This link doesn't work</h1>
            <p class="text-xs text-zinc-400">It may be incomplete. Copy the whole link from the bottom of the email and open it again.</p>
        <?php elseif ($unContact['unsubscribed_at'] !== null): ?>
            <?= icon('circle-check', 'w-10 h-10 text-emerald-400 mx-auto') ?>
            <h1 class="text-lg font-bold text-white">You're unsubscribed</h1>
            <p class="text-xs text-zinc-400"><?= e($unMember) ?> won't send you any more emails through 88MMM Circle.</p>
        <?php else: ?>
            <?= icon('mail', 'w-10 h-10 text-[#f3bd35] mx-auto') ?>
            <h1 class="text-lg font-bold text-white">Stop emails from <?= e($unMember) ?>?</h1>
            <p class="text-xs text-zinc-400">You'll get no more emails that <?= e($unMember) ?> sends you through 88MMM Circle.</p>
            <form method="post" action="<?= e(base_path()) ?>/index.php?view=unsubscribe">
                <input type="hidden" name="t" value="<?= e($unToken) ?>">
                <button type="submit" class="w-full py-3 rounded-xl bg-gradient-to-r from-[#ffda72] to-[#f3bd35] text-black font-extrabold text-sm shadow hover:brightness-105 transition-all">Unsubscribe</button>
            </form>
        <?php endif; ?>
    </div>
</div>
