<?php
/**
 * Domain market value, from GoDaddy's API (keys in .env; read-only calls):
 *   - GoValue appraisals: an estimated resale value for names in members'
 *     searches and for their own domains (GET /v1/domains/govalues), kept for
 *     30 days per name in domain_valuations;
 *   - GoDaddy's .com registration price (GET /v1/domains/available), which
 *     the daily price refresh compares with Porkbun's (domain_price_check).
 * Without GODADDY_TOKEN or GODADDY_API_KEY + GODADDY_API_SECRET both are
 * skipped. Every call is logged in registrar_log (driver "godaddy"),
 * without the keys.
 */
declare(strict_types=1);

const GODADDY_HOSTS = ['production' => 'https://api.godaddy.com', 'ote' => 'https://api.ote-godaddy.com'];
/** Keep an appraisal this long before asking again. */
const DOMAIN_VALUE_TTL = 30 * 86400;
/** GoValue calls per day when GoDaddy doesn't say (GODADDY_DAILY_LIMIT overrides). */
const GODADDY_DEFAULT_DAILY_LIMIT = 500;
/** Exchange-rate sources may differ this much (percent) before admins are warned. */
const DOMAIN_FX_TOLERANCE = 2.0;
/** Porkbun's .com price / GoDaddy's must stay within this range, or admins are warned. */
const DOMAIN_REGISTRAR_RATIO = [0.4, 1.5];

function godaddy_enabled(): bool
{
    return (string) env('GODADDY_TOKEN', '') !== '' || ((string) env('GODADDY_API_KEY', '') !== '' && (string) env('GODADDY_API_SECRET', '') !== '');
}

/**
 * Whether names are appraised and priced at their market value: GoDaddy keys
 * set and DOMAIN_APPRAISAL not off. Otherwise kept estimates are ignored
 * too, so every name costs the registration cost.
 */
function domain_appraisal_enabled(): bool
{
    return godaddy_enabled() && strtolower((string) env('DOMAIN_APPRAISAL', 'on')) !== 'off';
}

/** "production" (api.godaddy.com) or "ote" (GoDaddy's test environment). */
function godaddy_env(): string
{
    return env('GODADDY_ENV', 'production') === 'ote' ? 'ote' : 'production';
}

function godaddy_usage_file(): string
{
    return APP_ROOT . '/storage/cache/godaddy-usage.json';
}

/** Today's GoValue usage: ['date', 'used', 'max', 'blocked' => reason or '']. */
function godaddy_usage(): array
{
    $u = is_file(godaddy_usage_file()) ? json_decode((string) file_get_contents(godaddy_usage_file()), true) : null;
    $limit = (int) env('GODADDY_DAILY_LIMIT', GODADDY_DEFAULT_DAILY_LIMIT);
    if (!is_array($u) || ($u['date'] ?? '') !== date('Y-m-d')) {
        $u = ['date' => date('Y-m-d'), 'used' => 0, 'max' => $limit, 'blocked' => ''];
    }
    return $u;
}

function godaddy_usage_save(array $u): void
{
    if (!is_dir(dirname(godaddy_usage_file()))) {
        @mkdir(dirname(godaddy_usage_file()), 0775, true);
    }
    file_put_contents(godaddy_usage_file(), json_encode($u), LOCK_EX);
}

/**
 * Several GET calls at once: $paths is key => path with query string.
 * Returns key => ['status' => HTTP status (0: no answer), 'json' => array|null].
 */
function godaddy_get_many(array $paths, string $action): array
{
    $base = rtrim((string) env('GODADDY_BASE_URL', '') ?: GODADDY_HOSTS[godaddy_env()], '/');
    $auth = (string) env('GODADDY_TOKEN', '') !== ''
        ? 'Bearer ' . env('GODADDY_TOKEN')
        : 'sso-key ' . env('GODADDY_API_KEY') . ':' . env('GODADDY_API_SECRET');
    $mh = curl_multi_init();
    $handles = [];
    $started = microtime(true);
    foreach ($paths as $key => $path) {
        $ch = curl_init($base . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_USERAGENT => '88MMM-Portal/1.0',
            CURLOPT_HTTPHEADER => ['Accept: application/json', 'Authorization: ' . $auth],
        ]);
        curl_multi_add_handle($mh, $ch);
        $handles[$key] = $ch;
    }
    do {
        $status = curl_multi_exec($mh, $running);
        if ($running) {
            curl_multi_select($mh, 1.0);
        }
    } while ($running && $status === CURLM_OK);
    $out = [];
    foreach ($handles as $key => $ch) {
        $raw = (string) curl_multi_getcontent($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $json = json_decode($raw, true);
        $out[$key] = ['status' => $code, 'json' => is_array($json) ? $json : null];
        $error = $code === 200 ? '' : ($code === 0 ? 'Network error: ' . curl_error($ch) : 'HTTP ' . $code . (is_array($json) ? ': ' . mb_substr((string) ($json['message'] ?? $json['code'] ?? ''), 0, 200) : ''));
        $path = (string) $paths[$key];
        registrar_log_write('godaddy', $action, 'GET', (string) parse_url($path, PHP_URL_PATH), ['query' => (string) parse_url($path, PHP_URL_QUERY)], $code ?: null,
            mb_substr($raw, 0, 4000), $error, 1, (int) ((microtime(true) - $started) * 1000));
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
    }
    curl_multi_close($mh);
    return $out;
}

/** A number from "1200", 1200 or "$1,200"; null when there is none. */
function godaddy_number($v): ?float
{
    if (is_int($v) || is_float($v)) {
        return (float) $v;
    }
    if (is_string($v) && preg_match('/-?\d[\d,]*(\.\d+)?/', $v, $m)) {
        return (float) str_replace(',', '', $m[0]);
    }
    return null;
}

/** The first value under one of $names anywhere in $json (case-insensitive keys). */
function godaddy_find(array $json, array $names)
{
    $want = array_map('strtolower', $names);
    foreach ($json as $k => $v) {
        if (is_string($k) && in_array(strtolower($k), $want, true) && !is_array($v)) {
            return $v;
        }
    }
    foreach ($json as $v) {
        if (is_array($v) && ($found = godaddy_find($v, $names)) !== null) {
            return $found;
        }
    }
    return null;
}

/** Read a GoValue answer: ['valueUsd', 'minUsd', 'maxUsd', 'listUsd', 'wholesaleUsd'] (USD), or null without a value. */
function godaddy_parse_govalue(array $json): ?array
{
    $value = godaddy_number(godaddy_find($json, ['goValue']));
    if ($value === null || $value < 0) {
        return null;
    }
    return [
        'valueUsd' => round($value, 2),
        'minUsd' => godaddy_number(godaddy_find($json, ['minPrice'])),
        'maxUsd' => godaddy_number(godaddy_find($json, ['maxPrice'])),
        'listUsd' => godaddy_number(godaddy_find($json, ['listPrice'])),
        'wholesaleUsd' => godaddy_number(godaddy_find($json, ['goValueWholesale'])),
    ];
}

/**
 * GoValue appraisals for $domains: domain => ['valueUsd', 'minUsd', 'maxUsd',
 * 'listUsd', 'wholesaleUsd', 'fetchedAt'] or null (none). Uses appraisals up
 * to 30 days old; asks GoDaddy for the rest when $mayFetch, the keys are set
 * and today's quota allows.
 */
function domain_appraise(array $domains, bool $mayFetch = true): array
{
    $domains = array_values(array_unique(array_filter($domains, 'is_string')));
    $out = array_fill_keys($domains, null);
    if (!$domains || !domain_appraisal_enabled()) {
        return $out;
    }
    $in = implode(',', array_fill(0, count($domains), '?'));
    $fresh = []; // Appraised in the last 30 days (with or without a value).
    foreach (db_all("SELECT * FROM domain_valuations WHERE domain IN ($in)", $domains) as $r) {
        if ($r['value_usd'] !== null) {
            $out[$r['domain']] = [
                'valueUsd' => (float) $r['value_usd'], 'minUsd' => $r['min_usd'] !== null ? (float) $r['min_usd'] : null,
                'maxUsd' => $r['max_usd'] !== null ? (float) $r['max_usd'] : null, 'listUsd' => $r['list_usd'] !== null ? (float) $r['list_usd'] : null,
                'wholesaleUsd' => $r['wholesale_usd'] !== null ? (float) $r['wholesale_usd'] : null, 'fetchedAt' => strtotime($r['fetched_at']),
            ];
        }
        if (strtotime($r['fetched_at']) > time() - DOMAIN_VALUE_TTL) {
            $fresh[$r['domain']] = true;
        }
    }
    $ask = array_values(array_filter($domains, fn ($d) => !isset($fresh[$d])));
    if (!$ask || !$mayFetch) {
        return $out;
    }
    $u = godaddy_usage();
    if ($u['blocked'] !== '' || $u['used'] >= $u['max']) {
        return $out;
    }
    $ask = array_slice($ask, 0, max(0, $u['max'] - $u['used']));
    $paths = [];
    foreach ($ask as $d) {
        $paths[$d] = '/v1/domains/govalues?domainName=' . rawurlencode($d);
    }
    $answers = godaddy_get_many($paths, 'govalue');
    $u['used'] += count($ask);
    foreach ($answers as $d => $a) {
        if ($a['status'] === 429 || $a['status'] === 401 || $a['status'] === 403) {
            $u['blocked'] = $a['status'] === 429 ? 'daily limit reached' : 'GoDaddy refused the keys (HTTP ' . $a['status'] . ')';
            continue;
        }
        if ($a['status'] !== 200 || $a['json'] === null) {
            continue; // No answer: asked again next time.
        }
        $max = godaddy_number(godaddy_find($a['json'], ['maxDailyUsage']));
        $used = godaddy_number(godaddy_find($a['json'], ['dailyUsage']));
        if ($max !== null && $max > 0) {
            $u['max'] = min((int) env('GODADDY_DAILY_LIMIT', GODADDY_DEFAULT_DAILY_LIMIT), (int) $max); // The lower of ours and GoDaddy's.
        }
        if ($used !== null) {
            $u['used'] = max($u['used'], (int) $used);
        }
        $v = godaddy_parse_govalue($a['json']);
        db_run('INSERT INTO domain_valuations (domain, value_usd, min_usd, max_usd, list_usd, wholesale_usd, source, fetched_at, raw)
                VALUES (?, ?, ?, ?, ?, ?, "godaddy", NOW(), ?)
                ON DUPLICATE KEY UPDATE value_usd = VALUES(value_usd), min_usd = VALUES(min_usd), max_usd = VALUES(max_usd), list_usd = VALUES(list_usd),
                    wholesale_usd = VALUES(wholesale_usd), source = VALUES(source), fetched_at = VALUES(fetched_at), raw = VALUES(raw)',
            [$d, $v['valueUsd'] ?? null, $v['minUsd'] ?? null, $v['maxUsd'] ?? null, $v['listUsd'] ?? null, $v['wholesaleUsd'] ?? null,
             mb_substr(json_encode($a['json'], JSON_UNESCAPED_SLASHES) ?: '', 0, 4000)]);
        $out[$d] = $v === null ? null : $v + ['fetchedAt' => time()];
    }
    godaddy_usage_save($u);
    return $out;
}

/** GoDaddy's price in USD for a .com (a made-up free name is priced), or null. */
function godaddy_com_price(): ?float
{
    if (!godaddy_enabled()) {
        return null;
    }
    $probe = 'evoai-price-check-' . bin2hex(random_bytes(4)) . '.com';
    $a = godaddy_get_many(['p' => '/v1/domains/available?checkType=FAST&domain=' . rawurlencode($probe)], 'price')['p'];
    if ($a['status'] !== 200 || !is_array($a['json']) || !isset($a['json']['price'])) {
        return null;
    }
    $period = max(1, (int) ($a['json']['period'] ?? 1));
    $usd = (float) $a['json']['price'] / 1000000 / $period;
    return ($a['json']['currency'] ?? 'USD') === 'USD' && $usd > 0 ? round($usd, 2) : null;
}

/**
 * Check the market price against other sources: the exchange rates from
 * every source that answered ($rates host => rate) and, with GoDaddy keys,
 * GoDaddy's .com price. Returns ['status' => verified | partial | mismatch,
 * 'fx' => $rates, 'registrars' => name => USD, 'issues' => [], 'notes' => []].
 */
function domain_price_check(float $porkbunUsd, array $rates): array
{
    $issues = [];
    $notes = [];
    if (count($rates) >= 2) {
        $vals = array_values($rates);
        $diff = abs($vals[0] - $vals[1]) / min($vals[0], $vals[1]) * 100;
        if ($diff > DOMAIN_FX_TOLERANCE) {
            $issues[] = sprintf('The exchange-rate sources disagree by %.1f%% (%s).', $diff, implode(' vs ', array_map(fn ($h, $r) => $h . ' ' . number_format($r, 2), array_keys($rates), $rates)));
        }
    } else {
        $notes[] = 'Only one exchange-rate source answered.';
    }
    $registrars = ['Porkbun' => $porkbunUsd];
    $gd = godaddy_com_price();
    if ($gd !== null) {
        $registrars['GoDaddy'] = $gd;
        $ratio = $porkbunUsd / $gd;
        if ($ratio < DOMAIN_REGISTRAR_RATIO[0] || $ratio > DOMAIN_REGISTRAR_RATIO[1]) {
            $issues[] = sprintf("Porkbun's .com price ($%.2f) is far from GoDaddy's ($%.2f): check for a sale or a price change before relying on it.", $porkbunUsd, $gd);
        }
    } else {
        $notes[] = godaddy_enabled() ? "GoDaddy's .com price could not be read." : 'Add GoDaddy API keys to compare with a second registrar.';
    }
    return [
        'status' => $issues ? 'mismatch' : ($notes ? 'partial' : 'verified'),
        'fx' => $rates, 'registrars' => $registrars, 'issues' => $issues, 'notes' => $notes,
    ];
}

/** Pesos for a USD amount at the current market rate (rounded up to the peso), or null without one. */
function domain_usd_to_php(?float $usd): ?float
{
    $m = domain_market(false, false);
    return $usd === null || $m === null ? null : ceil(round($usd * (float) $m['rate'], 2));
}

/**
 * What a member pays for a new .com name: its market value (the GoDaddy
 * GoValue estimate in pesos at today's rate), never less than the
 * registration cost (domain_price). A name GoDaddy gives no value for, or
 * without GoDaddy keys, costs the registration cost. $appraisal: the name's
 * domain_appraise() result when already at hand. Returns ['price', 'basis'
 * => value | registration, 'valueUsd', 'valuePhp', 'cost', 'note'].
 */
function domain_name_price(string $domain, ?array $appraisal = null, bool $mayFetch = true, bool $haveAppraisal = false): array
{
    $cost = domain_price('register', $mayFetch);
    $v = $haveAppraisal ? $appraisal : (domain_appraise([$domain], $mayFetch)[$domain] ?? null);
    $valuePhp = $v !== null && $v['valueUsd'] > 0 ? domain_usd_to_php($v['valueUsd']) : null;
    if ($valuePhp !== null && $valuePhp > $cost) {
        $m = domain_market(false, false);
        return ['price' => $valuePhp, 'basis' => 'value', 'valueUsd' => $v['valueUsd'], 'valuePhp' => $valuePhp, 'cost' => $cost,
            'note' => sprintf('Market value: GoDaddy GoValue $%s at ₱%s', number_format($v['valueUsd'], 2), number_format((float) $m['rate'], 2))];
    }
    return ['price' => $cost, 'basis' => 'registration', 'valueUsd' => $v['valueUsd'] ?? null, 'valuePhp' => $valuePhp, 'cost' => $cost,
        'note' => 'Registration cost' . ($valuePhp !== null ? ' (its GoValue estimate, ' . peso($valuePhp) . ', is lower)' : ($v === null && godaddy_enabled() ? ' (no GoValue estimate)' : ''))];
}
