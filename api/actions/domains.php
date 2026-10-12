<?php
/**
 * .com domains (includes/domains.php): the member's Digital Card CMS tab
 * (assets/js/views/bo-domains.js), the order result page (views/domain.php)
 * and Admin > Domains (assets/js/views/admin-domains.js). Loaded by api.php.
 */
declare(strict_types=1);

/** The order, if it is the signed-in member's (admins may see any). */
function api_domain_order(array $user, string $id): array
{
    $order = db_one('SELECT * FROM domain_orders WHERE id = ?', [$id]);
    if (!$order || ($order['user_id'] !== $user['id'] && !is_admin($user))) {
        api_fail('Order not found.', 404);
    }
    return $order;
}

return [
    // Up to 15 .com names with availability and the price.
    'domains.search' => static function (array $in): array {
        return domain_search(api_user(), str_in($in, 'query', 120));
    },

    // Start buying a domain at the price the member saw ($in['price']); the
    // browser then goes to redirectUrl (Maya or the test page). A changed
    // price comes back as priceChanged instead.
    'domains.buy' => static function (array $in): array {
        $expected = isset($in['price']) && is_numeric($in['price']) ? (float) $in['price'] : null;
        return domain_create_order(api_user(), 'register', str_in($in, 'domain', 80), $expected);
    },

    // Start renewing one of the member's domains for a year.
    'domains.renew' => static function (array $in): array {
        return domain_create_order(api_user(), 'renew', (string) int_in($in, 'id'));
    },

    // Which domain the member's card uses as its address (id 0: the 1EVOAI link).
    'domains.useForCard' => static function (array $in): array {
        $url = domain_set_card(api_user(), int_in($in, 'id'));
        return ['cardUrl' => $url, 'message' => 'Your card\'s address is now ' . $url];
    },

    'domains.autoRenew' => static function (array $in): array {
        domain_set_auto_renew(api_user(), int_in($in, 'id'), !empty($in['on']));
        return ['ok' => true];
    },

    // The result page asks again while the payment or registration is in progress.
    'domains.status' => static function (array $in): array {
        $user = api_user();
        $order = api_domain_order($user, str_in($in, 'order', 30));
        return domain_order_to_array(domain_sync($order['id']) ?? $order, is_admin($user));
    },

    // Test payments only (PAYMENT_DRIVER=mock): Pay now / Fail / Cancel.
    'domains.mockPay' => static function (array $in): array {
        $order = domain_mock_pay(api_user(), str_in($in, 'order', 30), str_in($in, 'result', 10));
        return domain_order_to_array($order);
    },

    /* ----- Admin > Domains ----- */

    // Try a paid order's registration again.
    'domains.retry' => static function (array $in): array {
        $order = domain_retry(api_admin(), str_in($in, 'order', 30));
        return domain_order_to_array($order, true);
    },

    // Ask the payment provider about an order now.
    'domains.recheck' => static function (array $in): array {
        $admin = api_admin();
        $order = api_domain_order($admin, str_in($in, 'order', 30));
        add_audit('Admin re-checked the payment of domain order ' . $order['id'], $admin['id']);
        return domain_order_to_array(domain_sync($order['id']) ?? $order, true);
    },

    // Fetch today's .com market price now.
    'domains.refreshPrice' => static function (array $in): array {
        $admin = api_admin();
        $m = domain_market(true);
        $info = domain_price_info();
        add_audit('Admin refreshed the .com market price: ' . peso($info['price']) . ' (' . $info['source'] . ')', $admin['id']);
        if ($m === null || time() - (int) $m['fetchedAt'] > 60) {
            api_fail('The market price could not be fetched right now; members see ' . peso($info['price']) . ' (' . ($info['source'] === 'market' ? 'the last market price' : 'DOMAIN_RETAIL_PRICE') . ').');
        }
        return ['price' => $info['price'], 'message' => 'Market price updated: ' . peso($info['price']) . ' per year.'];
    },

    // Try the GoDaddy keys: one fresh GoValue appraisal and GoDaddy's .com price.
    'domains.testGoDaddy' => static function (array $in): array {
        $admin = api_admin();
        if (!godaddy_enabled()) {
            api_fail('Set GODADDY_TOKEN, or GODADDY_API_KEY and GODADDY_API_SECRET, in .env first.');
        }
        $domain = domain_normalize(str_in($in, 'domain', 80) ?: 'example.com');
        $a = godaddy_get_many(['v' => '/v1/domains/govalues?domainName=' . rawurlencode($domain)], 'govalue-test')['v'];
        $v = $a['json'] !== null ? godaddy_parse_govalue($a['json']) : null;
        $price = godaddy_com_price();
        add_audit('Admin tested the GoDaddy API (' . $domain . '): HTTP ' . $a['status'], $admin['id']);
        if ($a['status'] !== 200) {
            api_fail('GoValue answered HTTP ' . $a['status'] . ($a['json'] ? ': ' . mb_substr((string) json_encode($a['json']), 0, 300) : '') . '. Check the keys (and GODADDY_ENV) and the registrar log.');
        }
        return ['domain' => $domain, 'value' => $v, 'comPriceUsd' => $price,
            'message' => $domain . ': ' . ($v ? 'estimated value $' . number_format($v['valueUsd']) : 'no value in the answer (see the registrar log)') . '; GoDaddy .com price: ' . ($price !== null ? '$' . number_format($price, 2) : 'not available')];
    },

    // Point a domain at SERVER_IP again.
    'domains.retryDns' => static function (array $in): array {
        $admin = api_admin();
        $ud = domain_set_dns(int_in($in, 'id'));
        add_audit('Admin re-sent DNS for ' . $ud['domain'] . ($ud['dns_ok'] ? ' (OK)' : ' (failed)'), $admin['id']);
        return ['dnsOk' => (bool) $ud['dns_ok'], 'dnsNote' => $ud['dns_note']];
    },
];
