<?php
/**
 * Digital Card CMS > Your .com Domain (included by digital_card.php): the
 * card's address (one of the member's domains, or the 1EVOAI link), the
 * member's domains with expiry, auto-renew and Renew; orders in progress;
 * and the search with Buy (assets/js/views/bo-domains.js, includes/domains.php).
 *
 * Uses: $user.
 */
$domState = domain_member_state($user['id']);
$domPrice = $domState['price'];
$domTest = $domState['paymentDriver'] === 'mock';
$domSandbox = !$domTest && $domState['mayaEnv'] === 'sandbox';
$domMissing = $domState['missing'];
$domPill = 'text-[10px] px-2 py-0.5 rounded-full font-bold uppercase tracking-wider border';
?>
<section id="bo-domains" class="p-5 rounded-3xl bg-[#111111] border border-[#f3bd35]/25 space-y-5" aria-labelledby="bo-domains-title">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 pb-3 border-b border-zinc-800">
        <div>
            <h3 id="bo-domains-title" class="text-xs font-bold uppercase tracking-wider text-[#f3bd35] flex items-center gap-1.5">
                <?= icon('globe', 'w-4 h-4') ?> Your .com Domain
            </h3>
            <p class="text-xs text-zinc-400 mt-1">Open your digital card at your own name, like <span class="text-zinc-200 font-mono">yourname.com</span>.<?php if ($domPrice > 0 && $domState['valuePricing']): ?> Each name is priced at its market value (GoDaddy GoValue estimate), never below the <?= peso($domPrice) ?> registration cost; renewals are <?= peso($domState['renewPrice']) ?> a year. Paid with Maya.<?php elseif ($domPrice > 0): ?> <?= peso($domPrice) ?> per year<?= $domState['marketPrice'] ? ' (today\'s .com market price' . (($domState['priceCheck']['status'] ?? '') === 'verified' ? ', checked against ' . (count($domState['priceCheck']['registrars']) + count($domState['priceCheck']['fx'])) . ' sources' : '') . ')' : '' ?>, paid with Maya.<?php endif; ?> We only offer names that are still free.</p>
        </div>
        <?php if ($domTest): ?>
            <span class="<?= $domPill ?> self-start bg-amber-950/40 border-amber-600/40 text-amber-200">Test mode · no real payments</span>
        <?php elseif ($domSandbox): ?>
            <span class="<?= $domPill ?> self-start bg-amber-950/40 border-amber-600/40 text-amber-200">Maya sandbox</span>
        <?php endif; ?>
    </div>

    <?php if ($domMissing): ?>
        <div class="p-3 rounded-2xl bg-amber-950/40 border border-amber-600/40 text-xs text-amber-200 flex flex-col sm:flex-row sm:items-center justify-between gap-2" role="note">
            <span>A domain is registered in its owner's name. Please add your <?= e(implode(', ', $domMissing)) ?> in My Account before buying.</span>
            <button type="button" data-tab="my_account" data-group="bo" class="self-start sm:self-auto px-3 py-1.5 rounded-xl bg-zinc-900 hover:bg-zinc-800 border border-zinc-700 text-zinc-200 font-semibold whitespace-nowrap">Open My Account</button>
        </div>
    <?php endif; ?>

    <?php if ($domState['domains']): ?>
        <div class="p-3.5 rounded-2xl <?= $domState['cardDomain'] ? 'bg-emerald-500/10 border-emerald-500/30' : 'bg-zinc-950 border-zinc-800' ?> border flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-xs" data-dom-card-address>
            <div class="min-w-0">
                <span class="text-[11px] text-zinc-400 block">Your card's address<?= $domState['cardDomain'] ? '' : ' (the 1EVOAI link)' ?></span>
                <a href="<?= e($domState['cardUrl']) ?>" target="_blank" rel="noopener" class="font-mono font-bold <?= $domState['cardDomain'] ? 'text-emerald-300' : 'text-zinc-200' ?> hover:text-white break-all"><?= e($domState['cardUrl']) ?></a>
                <span class="text-[10px] text-zinc-500 block mt-0.5">Your share link, QR code and vCard use this address.</span>
            </div>
            <button type="button" data-copy="<?= e($domState['cardUrl']) ?>" data-copied-html="<?= e(icon('check', 'w-3.5 h-3.5 text-emerald-400') . '<span>Copied</span>') ?>" class="self-start sm:self-auto flex items-center gap-1.5 px-3 py-2 rounded-xl bg-zinc-900 hover:bg-zinc-800 border border-zinc-700 text-zinc-200 font-semibold whitespace-nowrap"><?= icon('copy', 'w-3.5 h-3.5 text-[#f3bd35]') ?><span>Copy</span></button>
        </div>
        <div class="space-y-2">
            <?php foreach ($domState['domains'] as $d): ?>
                <?php $soon = $d['status'] === 'active' && $d['daysLeft'] <= 30; ?>
                <div class="p-3.5 rounded-2xl bg-zinc-950 border <?= $d['status'] === 'expired' ? 'border-rose-500/30' : ($soon ? 'border-amber-600/40' : 'border-zinc-800') ?> flex flex-col md:flex-row md:items-center justify-between gap-3" data-dom-row="<?= $d['id'] ?>">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <a href="http://<?= e($d['domain']) ?>" target="_blank" rel="noopener" class="text-sm font-bold text-white hover:text-[#ffda72] break-all flex items-center gap-1"><?= e($d['domain']) ?><?= icon('external-link', 'w-3 h-3 text-zinc-500') ?></a>
                            <?php if ($d['status'] === 'expired'): ?>
                                <span class="<?= $domPill ?> bg-rose-500/10 border-rose-500/30 text-rose-300">Expired</span>
                            <?php else: ?>
                                <span class="<?= $domPill ?> bg-emerald-500/10 border-emerald-500/30 text-emerald-300">Active</span>
                            <?php endif; ?>
                            <?php if (!$d['dnsOk'] && $d['status'] === 'active'): ?>
                                <span class="<?= $domPill ?> bg-zinc-900 border-zinc-700 text-zinc-400" title="Our team is pointing this domain at your card.">Connecting</span>
                            <?php endif; ?>
                            <?php if ($d['useForCard'] && $d['status'] === 'active'): ?>
                                <span class="<?= $domPill ?> bg-[#f3bd35]/15 border-[#f3bd35]/40 text-[#ffda72]"<?= $d['dnsOk'] ? '' : ' title="Used as soon as the domain is connected."' ?>><?= $d['dnsOk'] ? 'Card address' : 'Card address once connected' ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if ($d['valuePhp']): ?>
                            <p class="text-[11px] mt-1 text-[#ffda72]" title="GoDaddy GoValue estimate: $<?= e(number_format((float) $d['valueUsd'])) ?>">Est. value <?= peso($d['valuePhp']) ?> <span class="text-zinc-500">(GoDaddy GoValue)</span></p>
                        <?php endif; ?>
                        <p class="text-[11px] mt-1 <?= $d['status'] === 'expired' || $soon ? 'text-amber-200' : 'text-zinc-400' ?>">
                            <?= $d['status'] === 'expired' ? 'Expired' : 'Expires' ?> <?= e(date('M j, Y', intdiv($d['expiresAt'], 1000))) ?>
                            <?php if ($d['status'] === 'active'): ?>(<?= $d['daysLeft'] <= 1 ? 'within a day' : 'in ' . $d['daysLeft'] . ' days' ?>)<?php endif; ?>
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <?php if ($d['status'] === 'active' && !$d['useForCard']): ?>
                            <button type="button" data-dom-card="<?= $d['id'] ?>" class="flex items-center gap-1.5 px-3 py-2 rounded-xl bg-zinc-900 hover:bg-zinc-800 border border-[#f3bd35]/40 text-[#ffda72] text-[11px] font-semibold transition-colors"><?= icon('credit-card', 'w-3.5 h-3.5') ?><span>Use for my card</span></button>
                        <?php elseif ($d['status'] === 'active'): ?>
                            <button type="button" data-dom-card="0" class="px-3 py-2 rounded-xl text-[11px] text-zinc-400 hover:text-white">Use the 1EVOAI link instead</button>
                        <?php endif; ?>
                        <?php if ($d['status'] === 'active'): ?>
                            <label class="flex items-center gap-2 px-3 py-2 rounded-xl bg-zinc-900 border border-zinc-800 text-[11px] text-zinc-300 cursor-pointer" title="Paid from your main account's available balance <?= $domState['autoRenewDays'] ?> days before expiry.">
                                <input type="checkbox" data-dom-autorenew="<?= $d['id'] ?>"<?= $d['autoRenew'] ? ' checked' : '' ?> style="accent-color:#f3bd35">
                                <span>Auto-renew from balance</span>
                            </label>
                        <?php endif; ?>
                        <?php if ($d['renewable'] && $domState['renewPrice'] > 0): ?>
                            <button type="button" data-dom-renew="<?= $d['id'] ?>" data-domain="<?= e($d['domain']) ?>" class="flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-[#f3bd35] hover:bg-[#ffda72] text-black text-xs font-bold transition-colors">
                                <?= icon('rotate-ccw', 'w-3.5 h-3.5') ?><span>Renew 1 year · <?= peso($domState['renewPrice']) ?></span>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($domState['orders']): ?>
        <div class="space-y-2">
            <h4 class="text-[11px] font-bold text-zinc-400 uppercase tracking-wider">Recent orders</h4>
            <?php foreach ($domState['orders'] as $o): ?>
                <?php
                $oTone = match ($o['status']) {
                    'completed' => 'text-emerald-300',
                    'paid_failed_registration', 'paid', 'registering' => 'text-amber-200',
                    'pending_payment' => 'text-zinc-300',
                    default => 'text-zinc-500',
                };
                $oNote = match ($o['status']) {
                    'paid_failed_registration' => 'Payment received. Our team is completing it; no need to pay again.',
                    'pending_payment' => 'Not paid yet.',
                    default => '',
                };
                ?>
                <div class="px-3.5 py-2.5 rounded-xl bg-zinc-950 border border-zinc-800 flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-[11px]">
                    <div class="min-w-0">
                        <span class="font-semibold text-zinc-200 break-all"><?= e($o['domain']) ?></span>
                        <span class="text-zinc-500">· <?= $o['kind'] === 'renew' ? 'renewal' : 'registration' ?> · <?= peso($o['amount']) ?> · <?= e(date('M j, g:i A', intdiv($o['createdAt'], 1000))) ?></span>
                        <?php if ($oNote !== ''): ?><span class="block text-zinc-400"><?= e($oNote) ?></span><?php endif; ?>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="font-bold <?= $oTone ?>"><?= e($o['statusText']) ?></span>
                        <?php if (in_array($o['status'], ['pending_payment', 'paid', 'registering'], true)): ?>
                            <a href="<?= e(url('domain', array_filter(['order' => $o['id'], 'mockpay' => $o['status'] === 'pending_payment' && $o['paymentDriver'] === 'mock' ? 1 : null]))) ?>" class="px-2.5 py-1 rounded-lg bg-zinc-900 hover:bg-zinc-800 border border-zinc-700 text-zinc-200 font-semibold"><?= $o['status'] === 'pending_payment' && $o['paymentDriver'] === 'mock' ? 'Pay' : 'Check status' ?></a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form data-dom-search class="space-y-3" novalidate>
        <label for="dom-query" class="block text-[11px] font-semibold text-zinc-300"><?= $domState['domains'] ? 'Find another .com domain' : 'Find your .com domain' ?></label>
        <div class="flex flex-col sm:flex-row gap-2">
            <input id="dom-query" name="query" type="text" maxlength="120" autocomplete="off" spellcheck="false" placeholder="Your name or business, e.g. <?= e(domain_parse_query('', (string) $user['name'])['seed'] ?: 'juandelacruz') ?>" class="flex-1 w-full px-3 py-2.5 rounded-xl bg-zinc-900 border border-zinc-700 text-xs text-white focus:outline-none focus:border-[#f3bd35]">
            <button type="submit" class="flex items-center justify-center gap-1.5 px-5 py-2.5 rounded-xl bg-gradient-to-r from-[#ffda72] to-[#f3bd35] text-black font-extrabold text-xs shadow-md hover:brightness-105 active:scale-95 transition-all">
                <?= icon('search', 'w-3.5 h-3.5') ?><span>Search</span>
            </button>
        </div>
        <p class="text-[10px] text-zinc-500">Leave it empty to see ideas from your name. Only .com domains are offered, each checked with the .com registry.</p>
    </form>
    <div data-dom-results class="space-y-2" aria-live="polite"></div>
</section>
