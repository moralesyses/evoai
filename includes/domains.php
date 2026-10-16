<?php
/**
 * .com domains for members' digital cards: search, buy, renew, and the daily
 * job (bin/domains-daily.php).
 *
 *   1. domains.search: own name ideas plus the registrar's suggestions, all
 *      .com, checked in one availability call and with the .com registry
 *      (RDAP); only names that are free are offered. The price is today's
 *      .com market price (domain_price).
 *   2. domains.buy: a domain_orders row (pending_payment) and a checkout
 *      with the PaymentClient (Maya, or the mock page).
 *   3. domain_sync(): asks the payment provider about the order (result page,
 *      webhook and daily job all call it; nothing they were sent is trusted).
 *      Paid → the payment is recorded once → domain_fulfill().
 *   4. domain_fulfill(): registrar customer/contact if missing → register
 *      (or renew) → DNS A record to SERVER_IP → user_domains active until
 *      +1 year. If the registrar fails after payment, the order becomes
 *      paid_failed_registration for an admin to retry; a paid order is
 *      never dropped.
 *   5. The member's first domain becomes their 1EVOAI card's address (share
 *      link, QR code, vCard); they can choose another one or the 1EVOAI link.
 */
declare(strict_types=1);

/** Names checked per search (one availability call). */
const DOMAIN_SEARCH_MAX = 15;
/** Searches per member per DOMAIN_SEARCH_WINDOW seconds. */
const DOMAIN_SEARCH_LIMIT = 30;
const DOMAIN_SEARCH_WINDOW = 600;
const DOMAIN_PREFIXES = ['iam', 'its', 'hello'];
const DOMAIN_SUFFIXES = ['ph', 'official', 'hq'];
/** Name particles kept with the surname: Dela Cruz → delacruz. */
const DOMAIN_NAME_PARTICLES = ['de', 'del', 'dela', 'delos', 'della', 'di', 'da', 'van', 'von', 'san', 'sta', 'santa', 'la', 'le', 'mac', 'mc', 'st'];
/** Another member's unpaid order holds a name this long (a Maya checkout stays open for an hour). */
const DOMAIN_HOLD_MINUTES = 60;
/** Unpaid orders are re-checked by the daily job for this long, then cancelled. */
const DOMAIN_PENDING_HOURS = 48;
/** An expired domain can still be renewed for this many days. */
const DOMAIN_RENEW_GRACE_DAYS = 30;
/** Auto-renew this many days before expiry. */
const DOMAIN_AUTO_RENEW_DAYS = 7;
/** Renewal reminders, in days before expiry. */
const DOMAIN_REMINDER_DAYS = [30, 7, 1];
/** Order statuses before payment; a provider can still report a payment for any of them. */
const DOMAIN_UNPAID = ['pending_payment', 'payment_failed', 'cancelled'];
/** Statuses of an order that has been paid but has no domain yet. */
const DOMAIN_PAID_OPEN = ['paid', 'registering', 'paid_failed_registration'];
const DOMAIN_STATUS_TEXT = [
    'pending_payment' => 'Waiting for payment',
    'payment_failed' => 'Payment failed',
    'cancelled' => 'Cancelled',
    'paid' => 'Paid, registering',
    'registering' => 'Registering',
    'completed' => 'Completed',
    'paid_failed_registration' => 'Paid, registration delayed',
];

/* ---------------------------------------------------------------------
 * Prices and names
 * ------------------------------------------------------------------- */

/** Refresh the market price this often (the daily job does it ahead of time). */
const DOMAIN_PRICE_TTL = 86400;
/** When a refresh fails, keep using the last price for up to this long. */
const DOMAIN_PRICE_STALE = 7 * 86400;
/** After a failed refresh, wait this long before trying again (the sources can be slow). */
const DOMAIN_PRICE_RETRY = 600;
/** Exchange-rate sources, in order: [URL, path to the USD→PHP rate in the answer]. */
const DOMAIN_FX_SOURCES = [
    ['https://open.er-api.com/v6/latest/USD', ['rates', 'PHP']],
    ['https://api.frankfurter.dev/v1/latest?base=USD&symbols=PHP', ['rates', 'PHP']],
];

/** GET (or POST with a JSON body) a public JSON API. Throws RuntimeException. */
function domain_http_json(string $url, ?array $postBody, int $timeout): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_ENCODING => '',
        CURLOPT_USERAGENT => '88MMM-Portal/1.0',
        CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/json'],
    ]);
    if ($postBody !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode((object) $postBody));
    }
    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    if ($raw === false || $status !== 200) {
        throw new RuntimeException(parse_url($url, PHP_URL_HOST) . ': ' . ($raw === false ? curl_error($ch) : 'HTTP ' . $status));
    }
    $json = json_decode((string) $raw, true);
    if (!is_array($json)) {
        throw new RuntimeException(parse_url($url, PHP_URL_HOST) . ': not JSON');
    }
    return $json;
}

/**
 * Today's .com market price, fetched now: Porkbun's public .com price list
 * (USD, no account needed) and the USD→PHP rate. Throws RuntimeException.
 */
function domain_market_fetch(): array
{
    $pb = domain_http_json((string) env('DOMAIN_PRICE_URL', 'https://api.porkbun.com/api/json/v3/pricing/get'), [], 30);
    $com = $pb['pricing']['com'] ?? null;
    $reg = is_array($com) ? (float) ($com['registration'] ?? 0) : 0.0;
    $ren = is_array($com) ? (float) ($com['renewal'] ?? 0) : 0.0;
    if ($reg < 1 || $reg > 100 || $ren < 1 || $ren > 100) {
        throw new RuntimeException('Porkbun: no usable .com price in the answer.');
    }
    // Every exchange-rate source is asked, so they can be compared (domain_price_check).
    $sources = DOMAIN_FX_SOURCES;
    if (env('DOMAIN_FX_URLS') !== null || env('DOMAIN_FX_URL') !== null) { // testing
        $sources = array_map(fn ($u) => [trim($u), ['rates', 'PHP']], explode(',', (string) (env('DOMAIN_FX_URLS') ?? env('DOMAIN_FX_URL'))));
    }
    $rates = [];
    $errors = [];
    foreach ($sources as $i => [$url, $path]) {
        try {
            $value = domain_http_json($url, null, 15);
            foreach ($path as $key) {
                $value = is_array($value) ? ($value[$key] ?? null) : null;
            }
            $rate = (float) $value;
            if ($rate >= 20 && $rate <= 200) {
                $rates[parse_url($url, PHP_URL_HOST) . (isset($rates[parse_url($url, PHP_URL_HOST)]) ? '#' . $i : '')] = $rate;
                continue;
            }
            $errors[] = parse_url($url, PHP_URL_HOST) . ': no USD→PHP rate';
        } catch (RuntimeException $e) {
            $errors[] = $e->getMessage();
        }
    }
    if (!$rates) {
        throw new RuntimeException('Exchange rate unavailable (' . implode('; ', $errors) . ')');
    }
    return ['usdRegister' => $reg, 'usdRenew' => $ren, 'rate' => reset($rates), 'priceSource' => 'Porkbun',
        'rateSource' => (string) key($rates), 'fetchedAt' => time(), 'check' => domain_price_check($reg, $rates) + ['checkedAt' => time()]];
}

function domain_market_file(): string
{
    return APP_ROOT . '/storage/cache/domain-pricing.json';
}

/**
 * The .com market price (domain_market_fetch), cached for a day in
 * storage/cache. $force: fetch now. $mayFetch = false (page views): use the
 * cached price, however old (up to a week), and fetch only when there has
 * never been one, since Porkbun's price list takes 10-15 seconds. Null when
 * there is no usable price: never fetched, or the last one is over a week old
 * and the sources fail.
 */
function domain_market(bool $force = false, bool $mayFetch = true): ?array
{
    $file = domain_market_file();
    $cache = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
    $cache = is_array($cache) ? $cache : [];
    $have = isset($cache['fetchedAt'], $cache['rate'], $cache['usdRegister']);
    $age = $have ? time() - (int) $cache['fetchedAt'] : PHP_INT_MAX;
    $usable = $have && $age < DOMAIN_PRICE_STALE ? $cache : null;
    if (!$force && !$mayFetch && $usable !== null) {
        return $usable;
    }
    if (!$force && ($age < DOMAIN_PRICE_TTL || time() - (int) ($cache['lastAttempt'] ?? 0) < DOMAIN_PRICE_RETRY)) {
        return $usable;
    }
    if (!is_dir(dirname($file))) {
        @mkdir(dirname($file), 0775, true);
    }
    try {
        $fresh = domain_market_fetch();
        file_put_contents($file, json_encode($fresh + ['lastAttempt' => time()], JSON_PRETTY_PRINT), LOCK_EX);
        return $fresh + ['lastAttempt' => time()];
    } catch (Throwable $e) {
        error_log('[domains] market price refresh failed: ' . $e->getMessage());
        file_put_contents($file, json_encode(array_merge($cache, ['lastAttempt' => time(), 'lastError' => mb_substr($e->getMessage(), 0, 300)]), JSON_PRETTY_PRINT), LOCK_EX);
        return $usable;
    }
}

/**
 * Price in pesos for one year, and how it was set: ['price', 'source' =>
 * market | fixed | fallback, 'market' => domain_market() or null].
 *   DOMAIN_PRICE_SOURCE=market (default): the market price in USD × today's
 *   rate, plus DOMAIN_MARKUP_PERCENT, rounded up to the peso, at least
 *   DOMAIN_MIN_PRICE. Without a market price: DOMAIN_RETAIL_PRICE ("fallback").
 *   DOMAIN_PRICE_SOURCE=fixed: DOMAIN_RETAIL_PRICE, and DOMAIN_RENEWAL_PRICE
 *   for renewals (default: the retail price).
 */
function domain_price_info(string $kind = 'register', bool $mayFetch = true): array
{
    $market = env('DOMAIN_PRICE_SOURCE', 'market') === 'fixed' ? null : domain_market(false, $mayFetch);
    if ($market !== null) {
        $usd = $kind === 'renew' ? (float) $market['usdRenew'] : (float) $market['usdRegister'];
        $price = ceil($usd * (float) $market['rate'] * (1 + max(0.0, (float) env('DOMAIN_MARKUP_PERCENT', 0)) / 100));
        $price = max($price, (float) env('DOMAIN_MIN_PRICE', 0));
        return ['price' => $price, 'source' => 'market', 'market' => $market];
    }
    $retail = env('DOMAIN_RETAIL_PRICE', 899);
    $price = round((float) ($kind === 'renew' ? env('DOMAIN_RENEWAL_PRICE', $retail) : $retail), 2);
    if ($price <= 0) {
        throw new InvalidArgumentException('Domain prices are not set up yet. Please try again later.');
    }
    return ['price' => $price, 'source' => env('DOMAIN_PRICE_SOURCE', 'market') === 'fixed' ? 'fixed' : 'fallback', 'market' => null];
}

/**
 * Price in pesos for one year ($kind 'register' or 'renew'); see
 * domain_price_info(). $mayFetch = false for page views (no slow refresh).
 */
function domain_price(string $kind = 'register', bool $mayFetch = true): float
{
    return domain_price_info($kind, $mayFetch)['price'];
}

/** Whether names are checked with the .com registry (DOMAIN_RDAP, on by default). */
function domain_rdap_enabled(): bool
{
    return strtolower((string) env('DOMAIN_RDAP', 'on')) !== 'off';
}

/**
 * Ask the .com registry (RDAP at Verisign, no account needed) whether names
 * are registered, all at once: domain => true (registered), false (free) or
 * null (no answer). DOMAIN_RDAP=off skips it (all null).
 */
function domain_rdap_registered(array $domains): array
{
    $out = array_fill_keys($domains, null);
    if (!$domains || !domain_rdap_enabled()) {
        return $out;
    }
    $base = rtrim((string) env('RDAP_BASE_URL', 'https://rdap.verisign.com/com/v1'), '/');
    $mh = curl_multi_init();
    $handles = [];
    foreach ($domains as $d) {
        $ch = curl_init($base . '/domain/' . rawurlencode($d));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_USERAGENT => '88MMM-Portal/1.0',
            CURLOPT_HTTPHEADER => ['Accept: application/rdap+json'],
        ]);
        curl_multi_add_handle($mh, $ch);
        $handles[$d] = $ch;
    }
    do {
        $status = curl_multi_exec($mh, $running);
        if ($running) {
            curl_multi_select($mh, 1.0);
        }
    } while ($running && $status === CURLM_OK);
    foreach ($handles as $d => $ch) {
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $out[$d] = $code === 200 ? true : ($code === 404 ? false : null);
        if ($out[$d] === null) {
            error_log('[domains] RDAP gave no answer for ' . $d . ': ' . ($code ?: curl_error($ch)));
        }
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
    }
    curl_multi_close($mh);
    return $out;
}

/** A valid .com label (3–63 letters, digits or single hyphens), or '' when $s can't be one. */
function domain_label(string $s): string
{
    $s = trim(preg_replace('/[^a-z0-9-]+/', '', strtolower($s)) ?? '', '-');
    if (strlen($s) < 3 || strlen($s) > 63 || str_contains($s, '--')) {
        return '';
    }
    return $s;
}

/** "example.com" from what a member picked, or InvalidArgumentException. Only .com. */
function domain_normalize(string $domain): string
{
    $d = strtolower(trim($domain));
    if (!preg_match('/^([a-z0-9-]+)\.com$/', $d, $m) || domain_label($m[1]) !== $m[1]) {
        throw new InvalidArgumentException('Please choose a valid .com domain.');
    }
    return $d;
}

/** First name and surname as domain words: "Juan Dela Cruz Jr." → ['juan', 'delacruz']. */
function domain_name_parts(string $name): array
{
    $ascii = function_exists('iconv') ? (iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) ?: $name) : $name;
    $words = preg_split('/[^a-z0-9]+/', strtolower($ascii), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $words = array_values(array_filter($words, fn ($w) => !in_array($w, ['jr', 'sr', 'ii', 'iii', 'iv'], true)));
    if (!$words) {
        return ['', ''];
    }
    $first = array_shift($words);
    if (!$words) {
        return [$first, ''];
    }
    $last = array_pop($words);
    while ($words && in_array(end($words), DOMAIN_NAME_PARTICLES, true)) {
        $last = array_pop($words) . $last;
    }
    return [$first, $last];
}

/**
 * What to search for: ['seed' => label, 'first', 'last']. A typed full name
 * gives the name ideas; otherwise they come from the member's own name.
 * Only .com: a query ending in another extension is refused.
 */
function domain_parse_query(string $query, string $memberName): array
{
    $q = strtolower(trim($query));
    $q = preg_replace(['#^[a-z]+://#', '#/.*$#', '/^www\./'], '', $q) ?? '';
    if (preg_match('/\.([a-z0-9-]+)$/', $q, $m)) {
        if ($m[1] !== 'com') {
            throw new InvalidArgumentException('Only .com domains are available here.');
        }
        $q = substr($q, 0, -4);
    }
    $q = str_replace('.', '', $q);
    $words = preg_split('/\s+/', trim($q), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    [$first, $last] = domain_name_parts(count($words) > 1 ? $q : $memberName);
    $seed = domain_label(implode('', $words));
    if ($seed === '') {
        if ($q !== '') {
            throw new InvalidArgumentException('Please type a name of at least 3 letters or numbers.');
        }
        $seed = domain_label($first . $last);
    }
    return ['seed' => $seed, 'first' => $first, 'last' => $last];
}

/** Our own ideas: the name itself, iam/its/hello + name, name + ph/official/hq, and full-name combinations. */
function domain_candidates(string $seed, string $first, string $last): array
{
    $out = [];
    if ($seed !== '') {
        $out[] = $seed;
        foreach (DOMAIN_PREFIXES as $p) {
            $out[] = $p . $seed;
        }
        foreach (DOMAIN_SUFFIXES as $s) {
            $out[] = $seed . $s;
        }
    }
    if ($first !== '' && $last !== '') {
        array_push($out, $first . $last, $first . '-' . $last, $last . $first, $first . $last . 'ph', 'iam' . $first . $last);
    }
    return array_values(array_unique(array_filter(array_map('domain_label', $out))));
}

/* ---------------------------------------------------------------------
 * Search
 * ------------------------------------------------------------------- */

/**
 * Up to 15 .com names are checked; only the free ones are offered: free at
 * the registrar and at the .com registry (RDAP), not owned here and not in
 * someone else's open checkout. With the mock registrar, a name the registry
 * didn't answer for isn't offered either. Returns ['query', 'price',
 * 'currency', 'results' => [['domain', 'available' => true, 'exact']],
 * 'checked', 'exactTaken', 'exactYours', 'unverified'].
 */
function domain_search(array $user, string $query): array
{
    $bucket = 'domain-search:' . $user['id'];
    if (rate_limited($bucket, DOMAIN_SEARCH_LIMIT, DOMAIN_SEARCH_WINDOW)) {
        throw new InvalidArgumentException('Too many searches. Please wait a few minutes and try again.');
    }
    rate_limit_hit($bucket, DOMAIN_SEARCH_WINDOW);

    $q = domain_parse_query($query, (string) $user['name']);
    if ($q['seed'] === '') {
        throw new InvalidArgumentException('Please type the name you would like for your domain.');
    }
    try {
        $client = registrar_client();
        try {
            $suggested = $client->suggest($q['seed']);
        } catch (RegistrarError $e) {
            $suggested = []; // Our own ideas are enough.
        }
        $own = array_map(fn ($l) => $l . '.com', domain_candidates($q['seed'], '', ''));
        $combos = array_map(fn ($l) => $l . '.com', domain_candidates('', $q['first'], $q['last']));
        $other = [];
        foreach ($suggested as $s) {
            $s = strtolower(trim((string) $s));
            if (preg_match('/^([a-z0-9-]+)\.com$/', $s, $m) && domain_label($m[1]) === $m[1]) {
                $other[] = $s;
            }
        }
        $names = array_slice(array_values(array_unique([...$own, ...array_slice($other, 0, 4), ...$combos, ...array_slice($other, 4)])), 0, DOMAIN_SEARCH_MAX);
        $available = $client->checkAvailability($names);
    } catch (RegistrarError $e) {
        error_log('[domains] search failed: ' . $e->kind . ': ' . $e->getMessage());
        throw new InvalidArgumentException($e->userMessage());
    }

    $in = implode(',', array_fill(0, count($names), '?'));
    $owned = array_column(db_all("SELECT domain, user_id FROM user_domains WHERE domain IN ($in)", $names), 'user_id', 'domain');
    $held = array_column(db_all("SELECT domain, user_id FROM domain_orders WHERE kind = 'register' AND domain IN ($in)
            AND (status IN ('paid', 'registering', 'paid_failed_registration')
                 OR (status = 'pending_payment' AND created_at > NOW() - INTERVAL " . DOMAIN_HOLD_MINUTES . ' MINUTE))', $names), 'user_id', 'domain');
    $candidates = array_values(array_filter($names, fn ($n) => !empty($available[$n]) && !isset($owned[$n]) && !(isset($held[$n]) && $held[$n] !== $user['id'])));
    $registry = domain_rdap_registered($candidates);
    // No answer from the registry: a real registrar has asked it itself; the mock hasn't.
    $trustRegistrar = $client->name() !== 'mock' || !domain_rdap_enabled();
    $results = [];
    $unverified = 0;
    foreach ($candidates as $name) {
        if ($registry[$name] === false || ($registry[$name] === null && $trustRegistrar)) {
            $results[] = ['domain' => $name, 'available' => true, 'exact' => $name === $q['seed'] . '.com'];
        } elseif ($registry[$name] === null) {
            $unverified++;
        }
    }
    // Each free name's own price: its market value (GoDaddy GoValue), at least the registration cost.
    $values = domain_appraise(array_column($results, 'domain'));
    foreach ($results as &$r) {
        $v = $values[$r['domain']] ?? null;
        $np = domain_name_price($r['domain'], $v, true, true);
        $r['price'] = $np['price'];
        $r['priceBasis'] = $np['basis'];
        $r['priceNote'] = $np['note'];
        if ($v !== null && $v['valueUsd'] > 0) {
            $r['valueUsd'] = $v['valueUsd'];
            $r['valuePhp'] = $np['valuePhp'];
            $r['valueRange'] = $v['minUsd'] !== null && $v['maxUsd'] !== null ? [$v['minUsd'], $v['maxUsd']] : null;
        }
    }
    unset($r);
    $exact = $q['seed'] . '.com';
    $check = domain_price_info()['market']['check'] ?? null;
    return [
        'query' => $exact, 'price' => domain_price(), 'cost' => domain_price(), 'currency' => 'PHP', 'results' => $results,
        'checked' => count($names), 'unverified' => $unverified,
        'priceCheck' => $check['status'] ?? null, 'priceSources' => $check ? count($check['registrars']) + count($check['fx']) : 0,
        'appraisal' => domain_appraisal_enabled(),
        'exactYours' => ($owned[$exact] ?? null) === $user['id'],
        'exactTaken' => in_array($exact, $names, true) && !in_array($exact, array_column($results, 'domain'), true) && ($owned[$exact] ?? null) !== $user['id'],
    ];
}

/* ---------------------------------------------------------------------
 * The registrant (WHOIS contact)
 * ------------------------------------------------------------------- */

/** "PH" for "Philippines", "ph" or ""; other 2-letter codes as given. */
function domain_country(string $country): string
{
    $c = strtoupper(trim($country));
    if (preg_match('/^[A-Z]{2}$/', $c)) {
        return $c;
    }
    return ['' => 'PH', 'PHILIPPINES' => 'PH', 'PILIPINAS' => 'PH', 'UNITED STATES' => 'US', 'USA' => 'US', 'SINGAPORE' => 'SG', 'JAPAN' => 'JP', 'CANADA' => 'CA', 'AUSTRALIA' => 'AU', 'UNITED KINGDOM' => 'GB', 'UAE' => 'AE', 'UNITED ARAB EMIRATES' => 'AE', 'HONG KONG' => 'HK'][$c] ?? '';
}

/** [country code, local number] from "+63 917 123 4567", "0917-123-4567" or "9171234567" (Philippine numbers by default). */
function domain_phone(string $phone): array
{
    $digits = preg_replace('/\D+/', '', $phone) ?? '';
    if (str_starts_with($digits, '63') && strlen($digits) >= 11) {
        return ['63', substr($digits, 2)];
    }
    if (str_starts_with(trim($phone), '+') && strlen($digits) > 10) {
        return [substr($digits, 0, strlen($digits) - 10), substr($digits, -10)];
    }
    return ['63', ltrim($digits, '0')];
}

/**
 * The member as registrant (name, company, email, address, city, state, zip,
 * country, phoneCc, phone), from My Account. Registrations need accurate
 * owner details, so nothing is made up: ['person' => array, 'missing' => labels].
 */
function domain_person(string $userId): array
{
    $u = db_one('SELECT name, email FROM users WHERE id = ?', [$userId]) ?? ['name' => '', 'email' => ''];
    $d = get_account_details($userId);
    $profile = get_member_profile($userId);
    [$cc, $phone] = domain_phone((string) ($d['phone'] ?: ($profile['phone'] ?? '')));
    $person = [
        'name' => trim((string) $u['name']),
        'company' => trim((string) ($profile['company'] ?? '')) ?: 'N/A',
        'email' => (string) $u['email'],
        'address' => trim(implode(', ', array_filter([trim($d['street']), trim($d['barangay'])]))),
        'city' => trim($d['city']),
        'state' => trim($d['province']) ?: trim($d['city']),
        'zip' => trim($d['zipCode']),
        'country' => domain_country($d['country']),
        'phoneCc' => $cc,
        'phone' => $phone,
    ];
    $missing = [];
    foreach (['address' => 'street address', 'city' => 'city', 'zip' => 'ZIP code', 'country' => 'country'] as $key => $label) {
        if ($person[$key] === '') {
            $missing[] = $label;
        }
    }
    if (strlen($phone) < 7 || strlen($phone) > 12) {
        $missing[] = 'mobile number';
    }
    return ['person' => $person, 'missing' => $missing];
}

/** The member's registrar customer and contact ids, creating them the first time. */
function domain_registrar_customer(RegistrarClient $client, string $userId): array
{
    $row = db_one('SELECT * FROM registrar_customers WHERE user_id = ? AND registrar = ?', [$userId, $client->account()]);
    if ($row && $row['contact_id'] !== '') {
        return [$row['customer_id'], $row['contact_id']];
    }
    $p = domain_person($userId);
    if ($p['missing']) {
        throw new RegistrarError('config', 'The member\'s details are incomplete: ' . implode(', ', $p['missing']) . '.');
    }
    $customerId = $row['customer_id'] ?? $client->createCustomer($p['person']);
    // Saved before the contact, so a failed contact call never creates a second customer.
    db_run('INSERT INTO registrar_customers (user_id, registrar, customer_id, contact_id) VALUES (?, ?, ?, "")
            ON DUPLICATE KEY UPDATE customer_id = VALUES(customer_id)', [$userId, $client->account(), $customerId]);
    $contactId = $client->createContact($customerId, $p['person']);
    db_run('UPDATE registrar_customers SET contact_id = ? WHERE user_id = ? AND registrar = ?', [$contactId, $userId, $client->account()]);
    return [$customerId, $contactId];
}

/* ---------------------------------------------------------------------
 * Orders
 * ------------------------------------------------------------------- */

function domain_new_order_id(): string
{
    return 'DOM-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(5)));
}

/** The member's main backoffice head (wallet and notifications), or null. */
function domain_main_account(string $userId): ?string
{
    $id = db_value('SELECT id FROM mlm_accounts WHERE user_id = ? ORDER BY head_number, id LIMIT 1', [$userId]);
    return $id === null ? null : (string) $id;
}

/** A notification in the member's backoffice (when they have one). */
function domain_notify(string $userId, string $title, string $message): void
{
    $account = domain_main_account($userId);
    if ($account !== null) {
        mlm_notify($account, mb_substr($title, 0, 190), $message, 'domain');
    }
}

/**
 * Real payments and real registrations go together: Maya production only
 * with ResellerClub's live account, and test payments (the mock page or
 * Maya's sandbox) only with the mock registrar or ResellerClub's test
 * account. Anything else would take money without registering a real domain,
 * or register real domains without payment, so purchases are refused.
 */
function domain_check_drivers(PaymentClient $pay, RegistrarClient $registrar): void
{
    $realMoney = $pay->name() === 'maya' && maya_environment() === 'production';
    $realDomains = $registrar->account() === 'resellerclub:live';
    if ($realMoney !== $realDomains) {
        error_log('[domains] refused: ' . ($realMoney ? 'Maya production payments' : 'test payments (' . $pay->name() . ', ' . maya_environment() . ')')
            . ' with the ' . $registrar->account() . ' registrar.');
        throw new InvalidArgumentException('Domain purchases are not available right now. Please try again later.');
    }
}

/**
 * Start buying ($kind 'register', $target = the domain) or renewing ($kind
 * 'renew', $target = the user_domains id) for one year. A new name costs its
 * market value (domain_name_price); a renewal the renewal cost. $expected:
 * the price the member saw; when it has changed, no order is made and the
 * new price comes back to show them. Returns ['orderId', 'redirectUrl' =>
 * the payment page], or ['priceChanged' => true, 'price', 'message'].
 */
function domain_create_order(array $user, string $kind, string $target, ?float $expected = null): array
{
    // Before anything else: without Maya keys the checkout would only fail later, with a "try again" message.
    if (payment_client()->name() === 'maya' && !maya_enabled()) {
        throw new InvalidArgumentException(MAYA_UNAVAILABLE . ', so domains can\'t be bought or renewed here yet. Nothing was charged.');
    }
    $price = domain_price($kind);
    $priceNote = $kind === 'renew' ? 'Renewal cost' : '';
    try {
        $registrar = registrar_client();
    } catch (RegistrarError $e) {
        error_log('[domains] registrar: ' . $e->getMessage());
        throw new InvalidArgumentException($e->userMessage());
    }
    $pay = payment_client();
    domain_check_drivers($pay, $registrar);

    $p = domain_person($user['id']);
    if ($p['missing']) {
        throw new InvalidArgumentException('Please add your ' . implode(', ', $p['missing']) . ' in My Account first: a domain is registered in its owner\'s name and contact details.');
    }

    $userDomainId = null;
    if ($kind === 'renew') {
        $ud = db_one('SELECT * FROM user_domains WHERE id = ? AND user_id = ?', [(int) $target, $user['id']]);
        if (!$ud) {
            throw new InvalidArgumentException('Domain not found.');
        }
        if ($ud['status'] === 'expired' && strtotime($ud['expires_at']) < strtotime('-' . DOMAIN_RENEW_GRACE_DAYS . ' days')) {
            throw new InvalidArgumentException('This domain expired more than ' . DOMAIN_RENEW_GRACE_DAYS . ' days ago and can no longer be renewed here. Please contact the 88MMM administrator.');
        }
        if ($ud['registrar'] !== $registrar->account()) {
            throw new InvalidArgumentException('This domain was registered through a different registrar account and can\'t be renewed here. Please contact the 88MMM administrator.');
        }
        if (db_value('SELECT 1 FROM domain_orders WHERE user_domain_id = ? AND kind = "renew" AND status IN ("paid", "registering", "paid_failed_registration")', [$ud['id']])) {
            throw new InvalidArgumentException('A renewal of this domain is already being processed.');
        }
        $domain = $ud['domain'];
        $userDomainId = (int) $ud['id'];
    } else {
        $domain = domain_normalize($target);
        $owned = db_one('SELECT * FROM user_domains WHERE domain = ?', [$domain]);
        if ($owned && $owned['user_id'] === $user['id']) {
            throw new InvalidArgumentException('You already own ' . $domain . '.' . ($owned['status'] === 'expired' ? ' Renew it instead.' : ''));
        }
        $takenMsg = (new RegistrarError('taken', ''))->userMessage();
        if (($owned && ($owned['status'] === 'active' || strtotime($owned['expires_at']) > strtotime('-' . DOMAIN_RENEW_GRACE_DAYS . ' days')))
            || db_value('SELECT 1 FROM domain_orders WHERE kind = "register" AND domain = ? AND user_id <> ?
                         AND (status IN ("paid", "registering", "paid_failed_registration") OR (status = "pending_payment" AND created_at > NOW() - INTERVAL ' . DOMAIN_HOLD_MINUTES . ' MINUTE))', [$domain, $user['id']])) {
            throw new InvalidArgumentException($takenMsg);
        }
        // Checked again right before payment: the search may be minutes old.
        try {
            $available = $registrar->checkAvailability([$domain]);
        } catch (RegistrarError $e) {
            error_log('[domains] availability failed: ' . $e->kind . ': ' . $e->getMessage());
            throw new InvalidArgumentException($e->userMessage());
        }
        if (empty($available[$domain])) {
            throw new InvalidArgumentException($takenMsg);
        }
        // And with the .com registry itself.
        $registered = domain_rdap_registered([$domain])[$domain];
        if ($registered === true) {
            throw new InvalidArgumentException($takenMsg);
        }
        if ($registered === null && $registrar->name() === 'mock' && domain_rdap_enabled()) {
            throw new InvalidArgumentException('We couldn\'t confirm that ' . $domain . ' is still free. Please try again in a minute.');
        }
        $np = domain_name_price($domain);
        $price = $np['price'];
        $priceNote = $np['note'];
    }
    if ($expected !== null && $expected > 0 && abs($expected - $price) > 0.005) {
        return ['priceChanged' => true, 'domain' => $domain, 'price' => $price,
            'message' => 'The price of ' . $domain . ' is now ' . peso($price) . ' (it was ' . peso($expected) . '). Click again to buy it for ' . peso($price) . '.'];
    }

    maya_throttle();
    $id = domain_new_order_id();
    db_run('INSERT INTO domain_orders (id, user_id, kind, domain, user_domain_id, years, amount, price_note, currency, status, payment_driver, registrar_driver)
            VALUES (?, ?, ?, ?, ?, 1, ?, ?, "PHP", "pending_payment", ?, ?)',
        [$id, $user['id'], $kind, $domain, $userDomainId, $price, mb_substr($priceNote, 0, 190), $pay->name(), $registrar->name()]);
    // An earlier unpaid checkout for the same domain is replaced (a payment
    // on it would still be recorded: unpaid orders are re-checked).
    db_run('UPDATE domain_orders SET status = "cancelled", error = "Replaced by a newer order." WHERE user_id = ? AND domain = ? AND kind = ? AND status = "pending_payment" AND id <> ?',
        [$user['id'], $domain, $kind, $id]);
    $order = db_one('SELECT * FROM domain_orders WHERE id = ?', [$id]);
    try {
        $checkout = $pay->createCheckout($order, ['name' => $user['name'], 'email' => $user['email'], 'phone' => $p['person']['phoneCc'] === '63' ? '0' . $p['person']['phone'] : '+' . $p['person']['phoneCc'] . $p['person']['phone']]);
    } catch (PaymentError $e) {
        db_run('UPDATE domain_orders SET status = "cancelled", payment_status = "START_FAILED", error = ? WHERE id = ?', [mb_substr('Checkout could not be started: ' . $e->getMessage(), 0, 255), $id]);
        error_log('[domains] checkout failed for ' . $id . ': ' . $e->getMessage());
        throw new InvalidArgumentException('Online payment isn\'t available right now. Nothing was charged; please try again in a few minutes.');
    }
    db_run('UPDATE domain_orders SET checkout_id = ? WHERE id = ?', [$checkout['checkoutId'], $id]);
    add_audit(($kind === 'renew' ? 'Domain renewal' : 'Domain order') . ' started: ' . $domain . ' for ' . peso($price) . ' (' . $id . ', ' . $pay->name() . ')', $user['id']);
    return ['orderId' => $id, 'redirectUrl' => $checkout['redirectUrl']];
}

/**
 * Ask the payment provider about an order and record its payment once; a
 * paid order then goes on to registration. Safe to call any number of times,
 * also at once (result page and webhook). Returns the order row, or null.
 */
function domain_sync(string $orderId): ?array
{
    $order = db_one('SELECT * FROM domain_orders WHERE id = ?', [$orderId]);
    if (!$order) {
        return null;
    }
    if ($order['status'] === 'paid') {
        return domain_fulfill($orderId); // Paid but not started (an interrupted request).
    }
    if (!in_array($order['status'], DOMAIN_UNPAID, true) || empty($order['checkout_id']) || $order['payment_status'] === 'MISMATCH') {
        return $order;
    }
    try {
        $p = payment_client($order['payment_driver'])->getPayment($order);
    } catch (PaymentError $e) {
        error_log('[domains] payment check failed for ' . $orderId . ': ' . $e->getMessage());
        return $order; // Still pending; the page, the webhook or the daily job asks again.
    }

    if ($p['status'] !== 'PAYMENT_SUCCESS') {
        $new = match (true) {
            in_array($p['status'], ['PAYMENT_FAILED', 'AUTH_FAILED', 'PAYMENT_INVALID'], true) => 'payment_failed',
            in_array($p['status'], ['PAYMENT_EXPIRED', 'PAYMENT_CANCELLED', 'VOIDED'], true) => 'cancelled',
            default => null,
        };
        // A failed card can be retried on the same Maya page, so failed orders keep being checked.
        db_run('UPDATE domain_orders SET payment_status = ?, status = COALESCE(?, status) WHERE id = ? AND status IN ("pending_payment", "payment_failed", "cancelled")',
            [$p['status'], $new, $orderId]);
        return db_one('SELECT * FROM domain_orders WHERE id = ?', [$orderId]);
    }

    // Paid: it must be this order, for the full amount, in pesos.
    if ($p['reference'] !== $order['id'] || abs($p['amount'] - (float) $order['amount']) > 0.005 || $p['currency'] !== $order['currency']) {
        db_run('UPDATE domain_orders SET payment_status = "MISMATCH", status = "payment_failed", error = ? WHERE id = ? AND status IN ("pending_payment", "payment_failed", "cancelled")',
            ['The payment did not match this order (reference, amount or currency) and was not accepted. Check it in Maya Business Manager.', $orderId]);
        add_audit('Domain payment for ' . $orderId . ' NOT accepted: reference, amount or currency did not match. Check it in Maya Business Manager.', $order['user_id']);
        return db_one('SELECT * FROM domain_orders WHERE id = ?', [$orderId]);
    }

    $won = db_tx(function () use ($orderId, $p) {
        $locked = db_one('SELECT * FROM domain_orders WHERE id = ? FOR UPDATE', [$orderId]);
        if (!in_array($locked['status'], DOMAIN_UNPAID, true)) {
            return false; // Another request recorded it first.
        }
        $user = db_one('SELECT name, email FROM users WHERE id = ?', [$locked['user_id']]) ?? ['name' => '', 'email' => ''];
        $method = $locked['payment_driver'];
        $note = sprintf('%s via %s%s%s. Payment %s%s. Order %s.',
            $locked['kind'] === 'renew' ? 'Domain renewal' : 'Domain registration',
            $method === 'maya' ? 'Maya Checkout' : 'MOCK payment (testing; no money received)',
            $method === 'maya' && maya_environment() === 'sandbox' ? ' (SANDBOX test payment)' : '',
            $p['source'] !== '' && $method === 'maya' ? ' with ' . $p['source'] : '',
            $p['paymentId'],
            $p['receipt'] !== '' ? ', receipt ' . $p['receipt'] : '',
            $orderId);
        $paymentId = unique_payment_id();
        db_run('INSERT INTO payments (id, user_id, customer_name, customer_email, customer_phone, item_type, item_name, amount, currency, payment_method, status, reference_number, receipt_note)
                VALUES (?, ?, ?, ?, "", ?, ?, ?, ?, ?, "completed", ?, ?)',
            [$paymentId, $locked['user_id'], $user['name'], $user['email'], $locked['kind'] === 'renew' ? 'domain_renewal' : 'domain_registration',
             $locked['domain'] . ' (' . $locked['years'] . ' year' . ($locked['years'] > 1 ? 's' : '') . ')', $locked['amount'], $locked['currency'], $method,
             unique_payment_reference($method), $note]);
        db_run('UPDATE domain_orders SET status = "paid", payment_status = "PAYMENT_SUCCESS", payment_id = ?, paid_at = NOW(), error = "" WHERE id = ?', [$paymentId, $orderId]);
        return true;
    });
    if ($won) {
        add_audit('Domain payment received: ' . $order['domain'] . ' ' . peso((float) $order['amount']) . ' (' . $orderId . ', ' . $order['payment_driver'] . ')', $order['user_id']);
        return domain_fulfill($orderId);
    }
    return db_one('SELECT * FROM domain_orders WHERE id = ?', [$orderId]);
}

/**
 * Register (or renew) a paid order: registrar customer/contact if missing →
 * register → DNS → user_domains active. Only one request works on an order
 * at a time. On a registrar failure the order becomes
 * paid_failed_registration (admin retry). Returns the order row.
 */
function domain_fulfill(string $orderId): ?array
{
    $claimed = db_run('UPDATE domain_orders SET status = "registering", attempts = attempts + 1, error = "" WHERE id = ? AND status IN ("paid", "paid_failed_registration")', [$orderId])->rowCount() === 1;
    $order = db_one('SELECT * FROM domain_orders WHERE id = ?', [$orderId]);
    if (!$claimed || !$order) {
        return $order;
    }
    $domain = $order['domain'];
    try {
        $client = registrar_client($order['registrar_driver']);
        if ($order['kind'] === 'renew') {
            $ud = db_one('SELECT * FROM user_domains WHERE id = ?', [$order['user_domain_id']]);
            if (!$ud) {
                throw new RegistrarError('other', 'The domain to renew is no longer in user_domains.');
            }
            $res = $client->renew($ud['registrar_order_id'], (int) $order['years'], strtotime($ud['expires_at']));
            $expires = $res['expiresAt'] ?? strtotime('+' . $order['years'] . ' year', strtotime($ud['expires_at']));
            db_run('UPDATE user_domains SET status = "active", expires_at = FROM_UNIXTIME(?), reminder_days = NULL WHERE id = ?', [$expires, $ud['id']]);
            if (!$ud['dns_ok']) {
                domain_set_dns((int) $ud['id']);
            }
            $udId = (int) $ud['id'];
        } else {
            $existing = db_one('SELECT * FROM user_domains WHERE domain = ?', [$domain]);
            if ($existing && $existing['status'] === 'active') {
                throw new RegistrarError('taken', $existing['user_id'] === $order['user_id']
                    ? 'The member already owns this domain (paid twice?). Refund the payment, or renew the domain instead.'
                    : 'Another member registered this domain here first. Refund the payment.');
            }
            [$customerId, $contactId] = domain_registrar_customer($client, $order['user_id']);
            $res = $client->register($domain, (int) $order['years'], $customerId, $contactId);
            $expires = $res['expiresAt'] ?? strtotime('+' . $order['years'] . ' year');

            // DNS before the domain goes live; a DNS failure is recorded for an admin, not fatal.
            [$dnsOk, $dnsNote] = domain_try_dns($client, $domain, $res['orderId']);
            $udId = db_tx(function () use ($order, $existing, $client, $res, $expires, $dnsOk, $dnsNote) {
                if ($existing) {
                    // An expired name registered again (by its old owner or someone new).
                    db_run('UPDATE user_domains SET user_id = ?, status = "active", registrar = ?, registrar_order_id = ?, registered_at = NOW(), expires_at = FROM_UNIXTIME(?), auto_renew = 0, dns_ok = ?, dns_note = ?, reminder_days = NULL, use_for_card = 0 WHERE id = ?',
                        [$order['user_id'], $client->account(), $res['orderId'], $expires, $dnsOk, $dnsNote, $existing['id']]);
                    $id = (int) $existing['id'];
                } else {
                    db_run('INSERT INTO user_domains (user_id, domain, status, registrar, registrar_order_id, registered_at, expires_at, dns_ok, dns_note) VALUES (?, ?, "active", ?, ?, NOW(), FROM_UNIXTIME(?), ?, ?)',
                        [$order['user_id'], $order['domain'], $client->account(), $res['orderId'], $expires, $dnsOk, $dnsNote]);
                    $id = (int) db()->lastInsertId();
                }
                db_run('UPDATE domain_orders SET user_domain_id = ? WHERE id = ?', [$id, $order['id']]);
                // The member's first domain becomes their card's address.
                if (!db_value('SELECT 1 FROM user_domains WHERE user_id = ? AND use_for_card = 1 AND id <> ?', [$order['user_id'], $id])) {
                    db_run('UPDATE user_domains SET use_for_card = 1 WHERE id = ?', [$id]);
                }
                return $id;
            });
        }
    } catch (Throwable $e) {
        $kind = $e instanceof RegistrarError ? $e->kind : 'other';
        if (!$e instanceof RegistrarError) {
            error_log('[domains] ' . $orderId . ': ' . $e);
        }
        db_run('UPDATE domain_orders SET status = "paid_failed_registration", error = ? WHERE id = ?', [mb_substr($kind . ': ' . $e->getMessage(), 0, 255), $orderId]);
        add_audit('Domain ' . ($order['kind'] === 'renew' ? 'renewal' : 'registration') . ' FAILED after payment: ' . $domain . ' (' . $orderId . ', ' . $kind . '). Retry it in Admin > Domains.', $order['user_id']);
        domain_notify($order['user_id'], 'Domain payment received: ' . $domain,
            'We received your payment for ' . $domain . '. ' . ($order['kind'] === 'renew' ? 'The renewal' : 'The registration') . ' is taking longer than usual; our team will complete it. There is no need to pay again.');
        return db_one('SELECT * FROM domain_orders WHERE id = ?', [$orderId]);
    }

    db_run('UPDATE domain_orders SET status = "completed", completed_at = NOW(), user_domain_id = ?, error = "" WHERE id = ?', [$udId, $orderId]);
    $until = date('M j, Y', (int) $expires);
    add_audit('Domain ' . ($order['kind'] === 'renew' ? 'renewed' : 'registered') . ': ' . $domain . ' until ' . $until . ' (' . $orderId . ')', $order['user_id']);
    domain_notify($order['user_id'], ($order['kind'] === 'renew' ? 'Domain renewed: ' : 'Domain registered: ') . $domain,
        $domain . ' is yours until ' . $until . '.' . ($order['kind'] === 'renew' ? '' : ' It can take a few hours before it opens your digital card everywhere.'));
    return db_one('SELECT * FROM domain_orders WHERE id = ?', [$orderId]);
}

/** Set the A records to SERVER_IP. Returns [dns_ok 0|1, note]. */
function domain_try_dns(RegistrarClient $client, string $domain, string $registrarOrderId): array
{
    try {
        $client->setDns($domain, $registrarOrderId, (string) env('SERVER_IP', ''));
        return [1, ''];
    } catch (RegistrarError $e) {
        error_log('[domains] DNS for ' . $domain . ' failed: ' . $e->getMessage());
        return [0, mb_substr($e->kind . ': ' . $e->getMessage(), 0, 255)];
    }
}

/** (Re)point a member's domain at SERVER_IP. Returns the updated row. */
function domain_set_dns(int $userDomainId): array
{
    $ud = db_one('SELECT * FROM user_domains WHERE id = ?', [$userDomainId]);
    if (!$ud) {
        throw new InvalidArgumentException('Domain not found.');
    }
    try {
        $client = registrar_client(explode(':', $ud['registrar'])[0]);
        [$ok, $note] = $client->account() === $ud['registrar']
            ? domain_try_dns($client, $ud['domain'], $ud['registrar_order_id'])
            : [0, 'config: registered with ' . $ud['registrar'] . ', but the registrar now is ' . $client->account() . '.'];
    } catch (RegistrarError $e) {
        [$ok, $note] = [0, mb_substr($e->kind . ': ' . $e->getMessage(), 0, 255)];
    }
    db_run('UPDATE user_domains SET dns_ok = ?, dns_note = ? WHERE id = ?', [$ok, $note, $userDomainId]);
    return db_one('SELECT * FROM user_domains WHERE id = ?', [$userDomainId]);
}

/** Admin: try a paid order's registration (or renewal) again. */
function domain_retry(array $admin, string $orderId): array
{
    $order = db_one('SELECT * FROM domain_orders WHERE id = ?', [$orderId]);
    if (!$order) {
        throw new InvalidArgumentException('Order not found.');
    }
    if ($order['status'] !== 'paid_failed_registration') {
        throw new InvalidArgumentException('Only orders waiting for an admin retry can be retried (this one is: ' . (DOMAIN_STATUS_TEXT[$order['status']] ?? $order['status']) . ').');
    }
    add_audit('Admin retried domain order ' . $orderId . ' (' . $order['domain'] . ')', $admin['id']);
    return domain_fulfill($orderId);
}

/**
 * Member: which of their domains is their card's address; 0 for the 1EVOAI
 * link. Only an active domain can be chosen. Returns the card's share link.
 */
function domain_set_card(array $user, int $id): string
{
    if ($id > 0) {
        $ud = db_one('SELECT * FROM user_domains WHERE id = ? AND user_id = ?', [$id, $user['id']]);
        if (!$ud) {
            throw new InvalidArgumentException('Domain not found.');
        }
        if ($ud['status'] !== 'active') {
            throw new InvalidArgumentException($ud['domain'] . ' has expired. Renew it first.');
        }
    }
    db_tx(function () use ($user, $id) {
        db_run('UPDATE user_domains SET use_for_card = 0 WHERE user_id = ?', [$user['id']]);
        if ($id > 0) {
            db_run('UPDATE user_domains SET use_for_card = 1 WHERE id = ? AND user_id = ?', [$id, $user['id']]);
        }
    });
    $domain = $id > 0 ? (string) db_value('SELECT domain FROM user_domains WHERE id = ?', [$id]) : '';
    add_audit('Card address set to ' . ($domain !== '' ? $domain : 'the 1EVOAI link'), $user['id']);
    return card_share_url(get_member_profile($user['id']));
}

/** Member: switch auto-renew (paid from the main account's wallet 7 days before expiry). */
function domain_set_auto_renew(array $user, int $id, bool $on): void
{
    $n = db_run('UPDATE user_domains SET auto_renew = ? WHERE id = ? AND user_id = ?', [$on ? 1 : 0, $id, $user['id']])->rowCount();
    if ($n === 0 && !db_value('SELECT 1 FROM user_domains WHERE id = ? AND user_id = ?', [$id, $user['id']])) {
        throw new InvalidArgumentException('Domain not found.');
    }
    add_audit('Domain auto-renew ' . ($on ? 'on' : 'off') . ' for #' . $id, $user['id']);
}

/** Mock payment page: the member's Pay now / Fail / Cancel. Returns the order row after domain_sync(). */
function domain_mock_pay(array $user, string $orderId, string $result): array
{
    $order = db_one('SELECT * FROM domain_orders WHERE id = ?', [$orderId]);
    if (!$order || ($order['user_id'] !== $user['id'] && !is_admin($user))) {
        throw new InvalidArgumentException('Order not found.');
    }
    if ($order['payment_driver'] !== 'mock') {
        throw new InvalidArgumentException('This order is paid on Maya, not on the test page.');
    }
    $status = ['success' => 'PAYMENT_SUCCESS', 'failure' => 'PAYMENT_FAILED', 'cancel' => 'PAYMENT_CANCELLED'][$result] ?? null;
    if ($status === null) {
        throw new InvalidArgumentException('Unknown result.');
    }
    db_run('UPDATE domain_orders SET payment_status = ? WHERE id = ? AND status IN ("pending_payment", "payment_failed")', [$status, $orderId]);
    return domain_sync($orderId);
}

/* ---------------------------------------------------------------------
 * The daily job (bin/domains-daily.php)
 * ------------------------------------------------------------------- */

/** Renew from the member's wallet. Returns 'renewed', 'failed', 'no_funds' or 'skipped'. */
function domain_auto_renew(array $ud): string
{
    $accountId = domain_main_account($ud['user_id']);
    try {
        $price = domain_price('renew');
        $registrar = registrar_client();
    } catch (Throwable $e) {
        return 'skipped';
    }
    if ($accountId === null || $ud['registrar'] !== $registrar->account()) {
        return 'skipped';
    }
    $orderId = db_tx(function () use ($ud, $accountId, $price, $registrar) {
        $acc = mlm_lock_account($accountId);
        if (db_value('SELECT 1 FROM domain_orders WHERE user_domain_id = ? AND kind = "renew" AND status IN ("paid", "registering", "paid_failed_registration")', [$ud['id']])) {
            return '';
        }
        if ($acc['availableBalance'] < $price) {
            return null;
        }
        $id = domain_new_order_id();
        db_run('UPDATE mlm_accounts SET available_balance = available_balance - ? WHERE id = ?', [$price, $acc['id']]);
        db_run('INSERT INTO domain_orders (id, user_id, kind, domain, user_domain_id, years, amount, price_note, currency, status, payment_driver, registrar_driver, payment_status, paid_at)
                VALUES (?, ?, "renew", ?, ?, 1, ?, "Renewal cost (auto-renew)", "PHP", "paid", "wallet", ?, "WALLET", NOW())',
            [$id, $ud['user_id'], $ud['domain'], $ud['id'], $price, $registrar->name()]);
        mlm_add_tx($acc['id'], 'domain_purchase', 'Domain auto-renewal: ' . $ud['domain'], $price, 'out', 'completed', $id,
            'Renewed ' . $ud['domain'] . ' for 1 year using available balance.');
        return $id;
    });
    if ($orderId === '') {
        return 'skipped';
    }
    if ($orderId === null) {
        return 'no_funds';
    }
    add_audit('Domain auto-renewal paid from wallet: ' . $ud['domain'] . ' ' . peso($price) . ' (' . $orderId . ')', $ud['user_id'], $accountId);
    $order = domain_fulfill($orderId);
    return ($order['status'] ?? '') === 'completed' ? 'renewed' : 'failed';
}

/** Renewal reminder (email and backoffice notification). */
function domain_remind(array $ud, int $days, string $extra = ''): bool
{
    $user = db_one('SELECT name, email FROM users WHERE id = ?', [$ud['user_id']]);
    if (!$user) {
        return false;
    }
    $when = date('F j, Y', strtotime($ud['expires_at']));
    $left = $days === 1 ? 'tomorrow' : 'in ' . $days . ' days';
    $link = base_url() . '/index.php?view=backoffice&tab=digital_card';
    domain_notify($ud['user_id'], 'Domain expires ' . $left . ': ' . $ud['domain'],
        $ud['domain'] . ' expires on ' . $when . '. ' . ($extra !== '' ? $extra : 'Renew it in Digital Card CMS.'));
    $sent = mail_send([
        'to' => $user['email'],
        'toName' => $user['name'],
        'subject' => 'Your domain ' . $ud['domain'] . ' expires ' . $left,
        'body' => "Hi {$user['name']},\n\nYour domain {$ud['domain']} expires on {$when}.\n"
            . ($extra !== '' ? $extra . "\n" : '')
            . "\nRenew it in your backoffice (Digital Card CMS):\n{$link}\n\nIf it expires, your digital card stops opening at {$ud['domain']}.\n",
    ]);
    return $sent['ok'];
}

/**
 * The daily job: re-check unpaid orders, finish stuck ones, expire old
 * domains, auto-renew, and send reminders 30, 7 and 1 days before expiry.
 * Safe to run more often (hourly is fine). Returns counts.
 */
function domain_daily(): array
{
    $out = ['checked' => 0, 'paid' => 0, 'cancelled' => 0, 'fulfilled' => 0, 'stuck' => 0, 'expired' => 0, 'renewed' => 0, 'renew_failed' => 0, 'no_funds' => 0, 'reminders' => 0, 'price' => '', 'values' => 0];

    // 0. Today's .com market price, ahead of members' searches (it takes a few seconds).
    if (env('DOMAIN_PRICE_SOURCE', 'market') !== 'fixed') {
        $m = domain_market();
        if ($m === null || time() - (int) $m['fetchedAt'] > DOMAIN_PRICE_TTL / 2) {
            $m = domain_market(true);
        }
    }
    $info = domain_price_info();
    $out['price'] = peso($info['price']) . ' (' . $info['source'] . ')' . (isset($info['market']['check']) ? ', check: ' . $info['market']['check']['status'] : '');

    // Members' domains: estimated value (GoDaddy GoValue), refreshed monthly.
    $out['values'] = 0;
    if (godaddy_enabled()) {
        $owned = array_column(db_all('SELECT domain FROM user_domains WHERE status = "active" ORDER BY id LIMIT 200'), 'domain');
        $out['values'] = count(array_filter(domain_appraise($owned)));
    }

    // 1. Payments the member never came back from (webhooks can't reach a local XAMPP).
    $ids = db_all('SELECT id FROM domain_orders WHERE status IN ("pending_payment", "payment_failed", "cancelled") AND checkout_id IS NOT NULL
                   AND payment_status NOT IN ("MISMATCH", "START_FAILED") AND created_at > NOW() - INTERVAL ' . DOMAIN_PENDING_HOURS . ' HOUR
                   AND created_at < NOW() - INTERVAL 5 MINUTE ORDER BY created_at LIMIT 200');
    foreach (array_column($ids, 'id') as $id) {
        $order = domain_sync($id);
        $out['checked']++;
        if ($order && !in_array($order['status'], DOMAIN_UNPAID, true)) {
            $out['paid']++;
        }
    }
    $out['cancelled'] = db_run('UPDATE domain_orders SET status = "cancelled", error = "Not paid within ' . DOMAIN_PENDING_HOURS . ' hours." WHERE status = "pending_payment" AND created_at < NOW() - INTERVAL ' . DOMAIN_PENDING_HOURS . ' HOUR')->rowCount();

    // 2. Interrupted work: paid but never started, or stopped while registering.
    foreach (array_column(db_all('SELECT id FROM domain_orders WHERE status = "paid" AND updated_at < NOW() - INTERVAL 10 MINUTE LIMIT 100'), 'id') as $id) {
        domain_fulfill($id);
        $out['fulfilled']++;
    }
    $stuck = db_all('SELECT id, user_id, domain FROM domain_orders WHERE status = "registering" AND updated_at < NOW() - INTERVAL 30 MINUTE');
    foreach ($stuck as $s) {
        db_run('UPDATE domain_orders SET status = "paid_failed_registration", error = "other: interrupted while registering; check the registrar before retrying." WHERE id = ? AND status = "registering"', [$s['id']]);
        add_audit('Domain order ' . $s['id'] . ' (' . $s['domain'] . ') was interrupted while registering. Check it in Admin > Domains.', $s['user_id']);
        $out['stuck']++;
    }

    // 3. Expired domains.
    foreach (db_all('SELECT * FROM user_domains WHERE status = "active" AND expires_at < NOW()') as $ud) {
        db_run('UPDATE user_domains SET status = "expired" WHERE id = ? AND status = "active"', [$ud['id']]);
        domain_notify($ud['user_id'], 'Domain expired: ' . $ud['domain'],
            $ud['domain'] . ' has expired. You can still renew it for ' . DOMAIN_RENEW_GRACE_DAYS . ' days in Digital Card CMS.');
        $out['expired']++;
    }

    // 4. Auto-renew (wallet) and reminders.
    foreach (db_all('SELECT * FROM user_domains WHERE status = "active" AND expires_at < NOW() + INTERVAL 30 DAY ORDER BY expires_at') as $ud) {
        $daysLeft = max(0, (int) ceil((strtotime($ud['expires_at']) - time()) / 86400));
        $renew = '';
        if ($ud['auto_renew'] && $daysLeft <= DOMAIN_AUTO_RENEW_DAYS) {
            $renew = domain_auto_renew($ud);
            if ($renew === 'renewed') {
                $out['renewed']++;
                continue;
            }
            if ($renew === 'failed') {
                $out['renew_failed']++;
                continue; // Paid already: an admin completes it (paid_failed_registration).
            }
            if ($renew === 'no_funds') {
                $out['no_funds']++;
            }
        }
        // The nearest reminder not sent yet: 30, then 7, then 1 day before.
        $due = null;
        foreach (DOMAIN_REMINDER_DAYS as $d) {
            if ($daysLeft <= $d) {
                $due = $d;
            }
        }
        if ($due !== null && ($ud['reminder_days'] === null || (int) $ud['reminder_days'] > $due)) {
            $extra = $renew === 'no_funds'
                ? 'Auto-renew could not pay the ' . peso(domain_price('renew')) . ' renewal: your available balance is too low. Add funds or renew it with Maya.'
                : '';
            if (domain_remind($ud, max(1, $daysLeft), $extra)) {
                $out['reminders']++;
            }
            db_run('UPDATE user_domains SET reminder_days = ? WHERE id = ?', [$due, $ud['id']]);
        }
    }
    return $out;
}

/* ---------------------------------------------------------------------
 * Members' domains on this server, and arrays for the pages
 * ------------------------------------------------------------------- */

/**
 * The domain a member's card uses as its address, e.g. "juandelacruz.com":
 * the one they chose, while it is active and its DNS points here. Null when
 * the card uses the 1EVOAI link.
 */
function domain_card_domain(string $userId): ?string
{
    if ($userId === '') {
        return null;
    }
    $d = db_value('SELECT domain FROM user_domains WHERE user_id = ? AND use_for_card = 1 AND status = "active" AND dns_ok = 1 LIMIT 1', [$userId]);
    return $d === null ? null : (string) $d;
}

/**
 * The card's address on the member's own domain, e.g. https://juandelacruz.com
 * (DOMAIN_URL_SCHEME, plus the site's folder when it runs in one), or null.
 * When the member's website is live there, the card is at ?view=card.
 */
function domain_card_url(string $userId): ?string
{
    $domain = domain_card_domain($userId);
    if ($domain === null) {
        return null;
    }
    $scheme = env('DOMAIN_URL_SCHEME', 'http') === 'https' ? 'https' : 'http';
    if (site_is_live($userId)) {
        return $scheme . '://' . $domain . base_path() . '/?view=card';
    }
    return $scheme . '://' . $domain . (base_path() === '' ? '' : base_path() . '/');
}

/** The member whose active domain $host is (www. and port ignored), or null. */
function domain_owner_for_host(string $host): ?string
{
    $host = strtolower(preg_replace('/:\d+$/', '', trim($host)) ?? '');
    $host = preg_replace('/^www\./', '', $host) ?? '';
    if (!str_ends_with($host, '.com')) {
        return null;
    }
    $id = db_value('SELECT user_id FROM user_domains WHERE domain = ? AND status = "active"', [$host]);
    return $id === null ? null : (string) $id;
}

function domain_order_to_array(array $r, bool $admin = false): array
{
    $out = [
        'id' => $r['id'], 'kind' => $r['kind'], 'domain' => $r['domain'], 'years' => (int) $r['years'],
        'amount' => (float) $r['amount'], 'priceNote' => (string) ($r['price_note'] ?? ''), 'currency' => $r['currency'], 'status' => $r['status'],
        'statusText' => DOMAIN_STATUS_TEXT[$r['status']] ?? $r['status'], 'paymentDriver' => $r['payment_driver'],
        'createdAt' => to_ms($r['created_at']), 'paidAt' => $r['paid_at'] ? to_ms($r['paid_at']) : null,
        'completedAt' => $r['completed_at'] ? to_ms($r['completed_at']) : null,
    ];
    if ($admin) {
        $out += ['userId' => $r['user_id'], 'registrarDriver' => $r['registrar_driver'], 'checkoutId' => $r['checkout_id'],
            'paymentStatus' => $r['payment_status'], 'paymentId' => $r['payment_id'], 'attempts' => (int) $r['attempts'], 'error' => $r['error']];
    }
    return $out;
}

function domain_to_array(array $r): array
{
    $expires = strtotime($r['expires_at']);
    return [
        'id' => (int) $r['id'], 'domain' => $r['domain'], 'status' => $r['status'],
        'registeredAt' => to_ms($r['registered_at']), 'expiresAt' => $expires * 1000,
        'daysLeft' => (int) ceil(($expires - time()) / 86400),
        'renewable' => $r['status'] === 'active' || $expires > strtotime('-' . DOMAIN_RENEW_GRACE_DAYS . ' days'),
        'autoRenew' => (bool) $r['auto_renew'], 'dnsOk' => (bool) $r['dns_ok'], 'registrar' => $r['registrar'],
        'useForCard' => (bool) ($r['use_for_card'] ?? false),
    ];
}

/** Everything the member's Domains card shows. */
function domain_member_state(string $userId): array
{
    $domains = array_map('domain_to_array', db_all('SELECT * FROM user_domains WHERE user_id = ? ORDER BY status, expires_at', [$userId]));
    $values = domain_appraise(array_column($domains, 'domain'), false); // Kept appraisals only; the daily job refreshes them.
    foreach ($domains as &$d) {
        $v = $values[$d['domain']] ?? null;
        $d['valuePhp'] = $v !== null && $v['valueUsd'] > 0 ? domain_usd_to_php($v['valueUsd']) : null;
        $d['valueUsd'] = $v['valueUsd'] ?? null;
    }
    unset($d);
    $orders = array_map(fn ($r) => domain_order_to_array($r), db_all(
        'SELECT * FROM domain_orders WHERE user_id = ? AND (status <> "completed" OR completed_at > NOW() - INTERVAL 7 DAY)
         AND NOT (status = "cancelled" AND payment_status IN ("", "START_FAILED", "PENDING_PAYMENT", "PENDING_TOKEN") AND updated_at < NOW() - INTERVAL 1 DAY)
         ORDER BY created_at DESC LIMIT 10', [$userId]));
    try {
        $price = domain_price('register', false);
        $renewPrice = domain_price('renew', false);
    } catch (InvalidArgumentException $e) {
        $price = $renewPrice = 0.0;
    }
    $person = domain_person($userId);
    return [
        'domains' => $domains, 'orders' => $orders, 'price' => $price, 'renewPrice' => $renewPrice,
        'missing' => $person['missing'], 'paymentDriver' => payment_client()->name(),
        'mayaEnv' => maya_environment(), 'autoRenewDays' => DOMAIN_AUTO_RENEW_DAYS,
        'marketPrice' => env('DOMAIN_PRICE_SOURCE', 'market') !== 'fixed' && domain_market(false, false) !== null,
        'priceCheck' => env('DOMAIN_PRICE_SOURCE', 'market') !== 'fixed' ? (domain_market(false, false)['check'] ?? null) : null,
        'valuePricing' => domain_appraisal_enabled(),
        'cardUrl' => card_share_url(get_member_profile($userId)), 'cardDomain' => domain_card_domain($userId),
    ];
}
