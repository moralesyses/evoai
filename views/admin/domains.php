<?php
/**
 * Management Admin > .com Domains (includes/domains.php,
 * assets/js/views/admin-domains.js): the drivers in use, the price (today's
 * .com market price and how it was worked out; Refresh), members' websites
 * (view, suspend, restore), paid orders that
 * still need a registration (Retry), recent orders (Re-check payment),
 * members' domains (Retry DNS) and the registrar log.
 */
$adRegistrar = env('REGISTRAR_DRIVER', 'mock') === 'resellerclub' ? 'resellerclub' : 'mock';
$adPayment = payment_client()->name();
$adRcEnv = (string) env('RESELLERCLUB_ENV', 'test') === 'live' ? 'live' : 'test';
$adServerIp = (string) env('SERVER_IP', '');
$adWarnings = [];
if (!filter_var($adServerIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
    $adWarnings[] = 'SERVER_IP is not set to an IPv4 address in .env, so new domains can\'t be pointed at this server.';
}
if ($adRegistrar === 'resellerclub' && ((string) env('RESELLERCLUB_ID', '') === '' || (string) env('RESELLERCLUB_API_KEY', '') === '')) {
    $adWarnings[] = 'REGISTRAR_DRIVER is resellerclub, but RESELLERCLUB_ID or RESELLERCLUB_API_KEY is missing in .env.';
}
if ($adPayment === 'maya' && !maya_enabled()) {
    $adWarnings[] = 'PAYMENT_DRIVER is maya, but the Maya keys are missing (MAYA_PUBLIC_KEY / MAYA_SECRET_KEY).';
}
$adRealMoney = $adPayment === 'maya' && maya_environment() === 'production';
$adRealDomains = $adRegistrar === 'resellerclub' && $adRcEnv === 'live';
if ($adRealMoney && !$adRealDomains) {
    $adWarnings[] = 'Maya takes real payments (production), but domains would only be registered with ' . ($adRegistrar === 'mock' ? 'the mock registrar' : 'ResellerClub\'s test account') . ': purchases are refused until REGISTRAR_DRIVER=resellerclub and RESELLERCLUB_ENV=live.';
} elseif (!$adRealMoney && $adRealDomains) {
    $adWarnings[] = 'ResellerClub\'s live account registers real domains, but payments are ' . ($adPayment === 'mock' ? 'the mock test page' : 'Maya\'s sandbox') . ': purchases are refused until payments use Maya production.';
}
if ($adPayment === 'mock') {
    $adWarnings[] = 'PAYMENT_DRIVER is mock: a test page stands in for Maya and members aren\'t charged. Set PAYMENT_DRIVER=maya in .env to take real payments.';
}
try {
    $adPriceInfo = domain_price_info('register', false); // Refresh fetches a new one.
    $adPrice = $adPriceInfo['price'];
} catch (InvalidArgumentException $e) {
    $adPriceInfo = ['price' => 0.0, 'source' => 'fixed', 'market' => null];
    $adPrice = 0.0;
    $adWarnings[] = 'DOMAIN_RETAIL_PRICE is not a valid price.';
}
$adMarket = $adPriceInfo['market'];
$adMarkup = max(0.0, (float) env('DOMAIN_MARKUP_PERCENT', 0));
if ($adPriceInfo['source'] === 'fallback') {
    $adCache = is_file(domain_market_file()) ? (json_decode((string) file_get_contents(domain_market_file()), true) ?: []) : [];
    $adWarnings[] = 'The .com market price could not be fetched' . (!empty($adCache['lastError']) ? ' (' . $adCache['lastError'] . ')' : '') . ', so members see DOMAIN_RETAIL_PRICE (' . peso($adPrice) . '). Press Refresh to try again.';
}
if ($adMarket && time() - (int) $adMarket['fetchedAt'] > DOMAIN_PRICE_TTL * 2) {
    $adWarnings[] = 'The market price is from ' . date('M j, g:i A', (int) $adMarket['fetchedAt']) . ': refreshing it has failed since. Is the daily job (bin/domains-daily.php) running?';
}
$adCheck = $adMarket['check'] ?? null;
foreach ($adCheck['issues'] ?? [] as $issue) {
    $adWarnings[] = 'Price check: ' . $issue;
}
$adGd = godaddy_enabled();
$adGdUsage = godaddy_usage();
if ($adGd && $adGdUsage['blocked'] !== '') {
    $adWarnings[] = 'GoDaddy GoValue is paused for today: ' . $adGdUsage['blocked'] . '.';
}
if (strtolower((string) env('DOMAIN_RDAP', 'on')) === 'off') {
    $adWarnings[] = 'DOMAIN_RDAP is off: names are not checked with the .com registry' . ($adRegistrar === 'mock' ? ', and the mock registrar can offer names that are really taken.' : '.');
}

$adAttention = db_all('SELECT * FROM domain_orders WHERE status IN ("paid_failed_registration", "registering", "paid") ORDER BY paid_at');
$adOrders = db_all('SELECT * FROM domain_orders ORDER BY created_at DESC LIMIT 50');
$adDomains = db_all('SELECT * FROM user_domains ORDER BY (dns_ok = 0 AND status = "active") DESC, expires_at LIMIT 200');
$adLog = db_all('SELECT * FROM registrar_log ORDER BY id DESC LIMIT 30');
$adSites = db_all('SELECT w.user_id, w.status, w.published_at, w.views, w.admin_note, w.updated_at, (w.published IS NOT NULL) AS is_published, u.name
                   FROM member_websites w JOIN users u ON u.id = w.user_id ORDER BY (w.status = "published") DESC, w.updated_at DESC LIMIT 200');
$adUserIds = array_values(array_unique(array_column([...$adAttention, ...$adOrders, ...$adDomains], 'user_id')));
$adUsers = $adUserIds ? array_column(db_all('SELECT id, name FROM users WHERE id IN (' . implode(',', array_fill(0, count($adUserIds), '?')) . ')', $adUserIds), null, 'id') : [];
$adWho = static function (string $userId) use ($adUsers): string {
    return isset($adUsers[$userId]) ? $adUsers[$userId]['name'] : $userId;
};
$adTone = [
    'completed' => 'bg-emerald-500/10 border-emerald-500/30 text-emerald-300',
    'paid_failed_registration' => 'bg-rose-500/10 border-rose-500/30 text-rose-300',
    'paid' => 'bg-amber-950/40 border-amber-600/40 text-amber-200',
    'registering' => 'bg-amber-950/40 border-amber-600/40 text-amber-200',
    'pending_payment' => 'bg-zinc-900 border-zinc-700 text-zinc-300',
];
$adPill = 'inline-block text-[10px] px-2 py-0.5 rounded-full font-bold border whitespace-nowrap';
$adBtn = 'px-2.5 py-1 rounded-lg bg-zinc-900 hover:bg-zinc-800 border border-zinc-700 text-zinc-200 font-semibold whitespace-nowrap';
?>
<div class="space-y-6" id="admin-domains">
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="p-3.5 rounded-2xl bg-[#111111] border border-zinc-800">
            <span class="text-[11px] text-zinc-400 font-medium block">Registrar</span>
            <span class="text-sm font-black text-white"><?= $adRegistrar === 'resellerclub' ? 'ResellerClub · ' . $adRcEnv : 'Mock (offline)' ?></span>
        </div>
        <div class="p-3.5 rounded-2xl bg-[#111111] border border-zinc-800">
            <span class="text-[11px] text-zinc-400 font-medium block">Payments</span>
            <span class="text-sm font-black text-white"><?= $adPayment === 'maya' ? 'Maya · ' . e(maya_environment()) : 'Mock (test page)' ?></span>
        </div>
        <div class="p-3.5 rounded-2xl bg-[#111111] border border-zinc-800" data-adom-price>
            <span class="text-[11px] text-zinc-400 font-medium flex items-center justify-between gap-2"><?= domain_appraisal_enabled() ? 'Registration cost (minimum price)' : 'Price per year' ?>
                <?php if (env('DOMAIN_PRICE_SOURCE', 'market') !== 'fixed'): ?>
                    <button type="button" data-adom-refresh-price class="text-[10px] text-[#ffda72] hover:text-white flex items-center gap-1"><?= icon('refresh-cw', 'w-3 h-3') ?><span>Refresh</span></button>
                <?php endif; ?>
            </span>
            <span class="text-sm font-black text-[#ffda72]"><?= $adPrice > 0 ? peso($adPrice) : '—' ?></span>
            <span class="block text-[10px] text-zinc-500 mt-0.5">
                <?php if ($adMarket): ?>
                    Market: <?= e($adMarket['priceSource']) ?> .com $<?= number_format((float) $adMarket['usdRegister'], 2) ?> × ₱<?= number_format((float) $adMarket['rate'], 2) ?> (<?= e($adMarket['rateSource']) ?>)<?= $adMarkup > 0 ? ' + ' . num($adMarkup) . '% markup' : '' ?><?= (float) env('DOMAIN_MIN_PRICE', 0) > 0 ? ', at least ' . peso((float) env('DOMAIN_MIN_PRICE', 0)) : '' ?> · updated <?= e(date('M j, g:i A', (int) $adMarket['fetchedAt'])) ?>
                <?php if ($adCheck): ?>
                    <span class="block mt-0.5 <?= $adCheck['status'] === 'verified' ? 'text-emerald-300' : ($adCheck['status'] === 'mismatch' ? 'text-rose-300' : 'text-zinc-400') ?>">
                        <?= $adCheck['status'] === 'verified' ? 'Verified' : ($adCheck['status'] === 'mismatch' ? 'Sources disagree' : 'Partly checked') ?>:
                        <?= e(implode(' · ', array_map(fn ($n, $p) => $n . ' $' . number_format((float) $p, 2), array_keys($adCheck['registrars']), $adCheck['registrars']))) ?>;
                        USD→PHP <?= e(implode(' / ', array_map(fn ($h, $r) => number_format((float) $r, 2) . ' (' . $h . ')', array_keys($adCheck['fx']), $adCheck['fx']))) ?>
                        <?php foreach ($adCheck['notes'] as $note): ?><span class="block text-zinc-500"><?= e($note) ?></span><?php endforeach; ?>
                    </span>
                <?php endif; ?>
                <?php elseif ($adPriceInfo['source'] === 'fixed'): ?>
                    Fixed: DOMAIN_RETAIL_PRICE
                <?php else: ?>
                    Fallback: DOMAIN_RETAIL_PRICE (no market price yet)
                <?php endif; ?>
            </span>
        </div>
        <div class="p-3.5 rounded-2xl bg-[#111111] border border-zinc-800">
            <span class="text-[11px] text-zinc-400 font-medium block">SERVER_IP (A record)</span>
            <span class="text-sm font-black text-white font-mono"><?= e($adServerIp !== '' ? $adServerIp : 'not set') ?></span>
        </div>
    </div>

    <div class="p-3.5 rounded-2xl bg-[#111111] border border-zinc-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs" data-adom-godaddy>
        <div>
            <span class="text-[11px] text-zinc-400 font-medium block">Domain market value (GoDaddy GoValue)</span>
            <?php if ($adGd): ?>
                <span class="text-white font-semibold">On · <?= e(godaddy_env()) ?></span>
                <span class="text-zinc-400"> · today <?= (int) $adGdUsage['used'] ?> of <?= (int) $adGdUsage['max'] ?> appraisals · <?= number_format((int) db_value('SELECT COUNT(*) FROM domain_valuations WHERE value_usd IS NOT NULL')) ?> names appraised</span>
            <?php else: ?>
                <span class="text-zinc-300">Off. Add GODADDY_TOKEN (or GODADDY_API_KEY and GODADDY_API_SECRET) to .env to show members an estimated value next to each name and compare GoDaddy's .com price.</span>
            <?php endif; ?>
        </div>
        <?php if ($adGd): ?>
            <button type="button" data-adom-test-godaddy class="<?= $adBtn ?> self-start sm:self-auto">Test GoDaddy</button>
        <?php endif; ?>
    </div>

    <?php foreach ($adWarnings as $w): ?>
        <div class="p-3 rounded-2xl bg-amber-950/40 border border-amber-600/40 text-xs text-amber-200" role="note"><?= e($w) ?></div>
    <?php endforeach; ?>

    <div class="p-5 rounded-3xl bg-[#111111] border border-[#f3bd35]/25 space-y-3">
        <h3 class="text-xs font-bold uppercase tracking-wider text-[#f3bd35] flex items-center gap-1.5"><?= icon('shield-alert', 'w-4 h-4') ?> Paid, not registered yet (<?= count($adAttention) ?>)</h3>
        <?php if (!$adAttention): ?>
            <p class="text-xs text-zinc-500">Nothing waiting. Every paid order has its domain.</p>
        <?php endif; ?>
        <?php foreach ($adAttention as $o): ?>
            <div class="p-3.5 rounded-2xl bg-zinc-950 border border-zinc-800 flex flex-col md:flex-row md:items-center justify-between gap-3 text-[11px]">
                <div class="min-w-0 space-y-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-sm font-bold text-white break-all"><?= e($o['domain']) ?></span>
                        <span class="<?= $adPill ?> <?= $adTone[$o['status']] ?? $adTone['pending_payment'] ?>"><?= e(DOMAIN_STATUS_TEXT[$o['status']] ?? $o['status']) ?></span>
                        <span class="text-zinc-500"><?= $o['kind'] === 'renew' ? 'Renewal' : 'Registration' ?> · <?= peso((float) $o['amount']) ?> via <?= e($o['payment_driver']) ?> · <?= e($o['registrar_driver']) ?></span>
                    </div>
                    <p class="text-zinc-400"><?= e($adWho($o['user_id'])) ?> · <span class="font-mono"><?= e($o['id']) ?></span> · paid <?= e($o['paid_at'] ? fmt_datetime($o['paid_at']) : '—') ?> · attempts <?= (int) $o['attempts'] ?></p>
                    <?php if ($o['error'] !== ''): ?><p class="text-rose-300 break-words"><?= e($o['error']) ?></p><?php endif; ?>
                </div>
                <?php if ($o['status'] === 'paid_failed_registration'): ?>
                    <button type="button" data-adom-retry="<?= e($o['id']) ?>" class="flex items-center justify-center gap-1.5 px-3.5 py-2 rounded-xl bg-[#f3bd35] hover:bg-[#ffda72] text-black text-xs font-bold"><?= icon('refresh-cw', 'w-3.5 h-3.5') ?><span>Retry <?= $o['kind'] === 'renew' ? 'renewal' : 'registration' ?></span></button>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        <p class="text-[10px] text-zinc-500">Retry runs the same steps again: registrar customer and contact if missing, register (or renew), DNS. If the error says the member already owns the domain or someone else registered it, refund the payment in Maya Business Manager instead.</p>
    </div>

    <div class="p-5 rounded-3xl bg-[#111111] border border-zinc-800 space-y-3">
        <h3 class="text-xs font-bold uppercase tracking-wider text-[#f3bd35] flex items-center gap-1.5"><?= icon('receipt', 'w-4 h-4') ?> Recent orders</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-[11px]">
                <thead class="text-zinc-500 uppercase text-[10px]">
                    <tr><th class="py-2 pr-3">Order</th><th class="py-2 pr-3">Member</th><th class="py-2 pr-3">Domain</th><th class="py-2 pr-3">Amount</th><th class="py-2 pr-3">Status</th><th class="py-2 pr-3">Provider</th><th class="py-2"></th></tr>
                </thead>
                <tbody class="divide-y divide-zinc-800/80 text-zinc-300">
                    <?php if (!$adOrders): ?>
                        <tr><td colspan="7" class="py-3 text-zinc-500">No domain orders yet.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($adOrders as $o): ?>
                        <tr>
                            <td class="py-2 pr-3 font-mono whitespace-nowrap"><?= e($o['id']) ?><span class="block text-zinc-500 font-sans"><?= e(fmt_datetime($o['created_at'])) ?></span></td>
                            <td class="py-2 pr-3"><?= e($adWho($o['user_id'])) ?></td>
                            <td class="py-2 pr-3 break-all"><?= e($o['domain']) ?><span class="block text-zinc-500"><?= $o['kind'] === 'renew' ? 'renewal' : 'registration' ?></span></td>
                            <td class="py-2 pr-3"><span class="whitespace-nowrap"><?= peso((float) $o['amount']) ?></span><span class="block text-zinc-500"><?= e($o['payment_driver']) ?></span><?php if (($o['price_note'] ?? '') !== ''): ?><span class="block text-[10px] text-zinc-500"><?= e($o['price_note']) ?></span><?php endif; ?></td>
                            <td class="py-2 pr-3"><span class="<?= $adPill ?> <?= $adTone[$o['status']] ?? 'bg-zinc-900 border-zinc-800 text-zinc-500' ?>"><?= e(DOMAIN_STATUS_TEXT[$o['status']] ?? $o['status']) ?></span></td>
                            <td class="py-2 pr-3 font-mono text-[10px] text-zinc-400"><?= e($o['payment_status'] ?: '—') ?></td>
                            <td class="py-2 text-right">
                                <?php if (in_array($o['status'], DOMAIN_UNPAID, true) && $o['checkout_id'] && $o['payment_status'] !== 'MISMATCH'): ?>
                                    <button type="button" data-adom-recheck="<?= e($o['id']) ?>" class="<?= $adBtn ?>">Re-check payment</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="p-5 rounded-3xl bg-[#111111] border border-zinc-800 space-y-3">
        <h3 class="text-xs font-bold uppercase tracking-wider text-[#f3bd35] flex items-center gap-1.5"><?= icon('globe', 'w-4 h-4') ?> Members' domains (<?= count($adDomains) ?>)</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-[11px]">
                <thead class="text-zinc-500 uppercase text-[10px]">
                    <tr><th class="py-2 pr-3">Domain</th><th class="py-2 pr-3">Member</th><th class="py-2 pr-3">Expires</th><th class="py-2 pr-3">Auto-renew</th><th class="py-2 pr-3">DNS</th><th class="py-2"></th></tr>
                </thead>
                <tbody class="divide-y divide-zinc-800/80 text-zinc-300">
                    <?php if (!$adDomains): ?>
                        <tr><td colspan="6" class="py-3 text-zinc-500">No domains registered yet.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($adDomains as $d): ?>
                        <tr>
                            <td class="py-2 pr-3 break-all"><span class="font-semibold text-white"><?= e($d['domain']) ?></span><span class="block text-zinc-500"><?= e($d['registrar']) ?> · <?= e($d['registrar_order_id']) ?></span></td>
                            <td class="py-2 pr-3"><?= e($adWho($d['user_id'])) ?></td>
                            <td class="py-2 pr-3 whitespace-nowrap"><?= e(date('M j, Y', strtotime($d['expires_at']))) ?><?php if ($d['status'] === 'expired'): ?> <span class="<?= $adPill ?> bg-rose-500/10 border-rose-500/30 text-rose-300">Expired</span><?php endif; ?></td>
                            <td class="py-2 pr-3"><?= $d['auto_renew'] ? 'On' : 'Off' ?></td>
                            <td class="py-2 pr-3"><?= $d['dns_ok'] ? '<span class="text-emerald-300">OK</span>' : '<span class="text-rose-300 break-words">' . e($d['dns_note'] !== '' ? $d['dns_note'] : 'Not set') . '</span>' ?></td>
                            <td class="py-2 text-right"><?php if ($d['status'] === 'active'): ?><button type="button" data-adom-dns="<?= (int) $d['id'] ?>" class="<?= $adBtn ?>"><?= $d['dns_ok'] ? 'Re-send DNS' : 'Retry DNS' ?></button><?php endif; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="p-5 rounded-3xl bg-[#111111] border border-zinc-800 space-y-3" data-adom-sites>
        <h3 class="text-xs font-bold uppercase tracking-wider text-[#f3bd35] flex items-center gap-1.5"><?= icon('monitor', 'w-4 h-4') ?> Members' websites (<?= count($adSites) ?>)</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-[11px]">
                <thead class="text-zinc-500 uppercase text-[10px]">
                    <tr><th class="py-2 pr-3">Member</th><th class="py-2 pr-3">Status</th><th class="py-2 pr-3">Published</th><th class="py-2 pr-3">Visits</th><th class="py-2"></th></tr>
                </thead>
                <tbody class="divide-y divide-zinc-800/80 text-zinc-300">
                    <?php if (!$adSites): ?>
                        <tr><td colspan="5" class="py-3 text-zinc-500">No member has started a website yet.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($adSites as $w): ?>
                        <tr data-adom-site="<?= e($w['user_id']) ?>">
                            <td class="py-2 pr-3"><span class="font-semibold text-white"><?= e($w['name']) ?></span><span class="block text-zinc-500 break-all"><?= e(site_live_url($w['user_id']) ?? 'no active domain') ?></span></td>
                            <td class="py-2 pr-3">
                                <span class="<?= $adPill ?> <?= $w['status'] === 'suspended' ? 'bg-rose-500/10 border-rose-500/30 text-rose-300' : ($w['status'] === 'published' ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-300' : 'bg-zinc-900 border-zinc-700 text-zinc-400') ?>"><?= $w['status'] === 'suspended' ? 'Taken offline' : ($w['status'] === 'published' ? 'Live' : 'Draft') ?></span>
                                <?php if ($w['admin_note'] !== ''): ?><span class="block text-rose-300 break-words"><?= e($w['admin_note']) ?></span><?php endif; ?>
                            </td>
                            <td class="py-2 pr-3 whitespace-nowrap"><?= $w['published_at'] ? e(fmt_datetime($w['published_at'])) : '—' ?></td>
                            <td class="py-2 pr-3"><?= number_format((int) $w['views']) ?></td>
                            <td class="py-2 text-right whitespace-nowrap">
                                <a href="<?= e(url('site', ['u' => $w['user_id'], 'preview' => 1])) ?>" target="_blank" rel="noopener" class="<?= $adBtn ?>">View</a>
                                <?php if ($w['status'] === 'suspended'): ?>
                                    <button type="button" data-adom-site-restore="<?= e($w['user_id']) ?>" class="<?= $adBtn ?>">Restore</button>
                                <?php else: ?>
                                    <button type="button" data-adom-site-suspend="<?= e($w['user_id']) ?>" class="<?= $adBtn ?>">Take offline</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="text-[10px] text-zinc-500">Members build their websites in Member Backoffice → My Website once they own a domain. A site taken offline can't be published again until you restore it; the member sees your reason.</p>
    </div>

    <div class="p-5 rounded-3xl bg-[#111111] border border-zinc-800 space-y-3">
        <h3 class="text-xs font-bold uppercase tracking-wider text-[#f3bd35] flex items-center gap-1.5"><?= icon('file-code', 'w-4 h-4') ?> Registrar log (latest 30)</h3>
        <p class="text-[10px] text-zinc-500">Every registrar request and answer, without the API key or passwords.</p>
        <div class="space-y-1.5">
            <?php if (!$adLog): ?>
                <p class="text-xs text-zinc-500">No registrar requests yet.</p>
            <?php endif; ?>
            <?php foreach ($adLog as $l): ?>
                <details class="rounded-xl bg-zinc-950 border border-zinc-800 text-[11px]">
                    <summary class="px-3 py-2 cursor-pointer flex flex-wrap items-center gap-2">
                        <span class="text-zinc-500 whitespace-nowrap"><?= e(fmt_datetime($l['created_at'])) ?></span>
                        <span class="font-mono text-zinc-300"><?= e($l['driver']) ?> · <?= e($l['action']) ?></span>
                        <span class="font-mono text-zinc-500 break-all"><?= e($l['method'] . ' ' . $l['endpoint']) ?></span>
                        <span class="<?= $l['error'] !== '' ? 'text-rose-300' : 'text-emerald-300' ?>"><?= $l['http_status'] !== null ? 'HTTP ' . (int) $l['http_status'] : 'no answer' ?><?= (int) $l['attempt'] > 1 ? ' · attempt ' . (int) $l['attempt'] : '' ?> · <?= (int) $l['duration_ms'] ?> ms</span>
                    </summary>
                    <div class="px-3 pb-3 space-y-1.5">
                        <?php if ($l['error'] !== ''): ?><p class="text-rose-300 break-words"><?= e($l['error']) ?></p><?php endif; ?>
                        <pre class="break-all text-[10px] text-zinc-400 bg-black/40 rounded-lg p-2 max-h-36 overflow-y-auto" style="white-space:pre-wrap"><?= e((string) $l['request']) ?></pre>
                        <pre class="break-all text-[10px] text-zinc-400 bg-black/40 rounded-lg p-2 max-h-36 overflow-y-auto" style="white-space:pre-wrap"><?= e(mb_substr((string) $l['response'], 0, 4000)) ?></pre>
                    </div>
                </details>
            <?php endforeach; ?>
        </div>
    </div>
</div>
