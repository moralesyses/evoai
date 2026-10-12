<?php
/**
 * Domain registrars for the .com domain feature (includes/domains.php).
 * App code depends only on RegistrarClient; registrar_client() picks the
 * driver from REGISTRAR_DRIVER (.env): "resellerclub" (ResellerClub HTTP API,
 * test or live) or "mock" (offline, deterministic: the whole flow works on
 * XAMPP without internet). Only .com names are ever checked or registered.
 * Every registrar call is written to registrar_log (without the API key or
 * passwords).
 */
declare(strict_types=1);

/** A registrar failure. $kind: taken, funds, config, network or other. getMessage() is the raw cause, for logs and admins. */
final class RegistrarError extends RuntimeException
{
    public function __construct(public readonly string $kind, string $message)
    {
        parent::__construct($message);
    }

    /** What a member may be told: never the registrar's own words. */
    public function userMessage(): string
    {
        return match ($this->kind) {
            'taken' => 'That domain is no longer available. Please choose another one.',
            default => 'The domain service isn\'t available right now. Please try again later.',
        };
    }
}

interface RegistrarClient
{
    /** "resellerclub" or "mock". */
    public function name(): string;

    /**
     * The registrar account that customer ids and orders belong to: "mock",
     * "resellerclub:test" or "resellerclub:live" (test ids are unknown on live).
     */
    public function account(): string;

    /**
     * Bulk availability of up to 15 full .com names in one call.
     * @param string[] $domains
     * @return array<string, bool> domain => available
     */
    public function checkAvailability(array $domains): array;

    /** @return string[] suggested .com names for a keyword */
    public function suggest(string $keyword): array;

    /** A registrar customer for a member. $person: name, company, email, address, city, state, zip, country, phoneCc, phone. */
    public function createCustomer(array $person): string;

    /** The customer's contact used as registrant, admin, tech and billing contact. */
    public function createContact(string $customerId, array $person): string;

    /** @return array{orderId: string, expiresAt: ?int} */
    public function register(string $domain, int $years, string $customerId, string $contactId): array;

    /** @return array{expiresAt: ?int} */
    public function renew(string $orderId, int $years, int $currentExpiry): array;

    /** Point the domain (and www) at $ip with an A record. */
    public function setDns(string $domain, string $orderId, string $ip): void;
}

/** One registrar_log row. Secrets are dropped from $request before it is stored. */
function registrar_log_write(string $driver, string $action, string $method, string $endpoint, array $request, ?int $httpStatus, string $response, string $error, int $attempt, int $durationMs): void
{
    unset($request['api-key'], $request['auth-userid']);
    foreach (['passwd', 'password'] as $secret) {
        if (isset($request[$secret])) {
            $request[$secret] = '***';
        }
    }
    try {
        db_run('INSERT INTO registrar_log (driver, action, method, endpoint, request, http_status, response, error, attempt, duration_ms) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$driver, $action, $method, mb_substr($endpoint, 0, 160), json_encode($request, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $httpStatus,
                mb_substr($response, 0, 20000), mb_substr($error, 0, 255), $attempt, $durationMs]);
    } catch (Throwable $e) {
        error_log('registrar_log: ' . $e->getMessage());
    }
}

/* ---------------------------------------------------------------------
 * ResellerClub HTTP API
 * ------------------------------------------------------------------- */

final class ResellerClubClient implements RegistrarClient
{
    private const HOSTS = ['test' => 'https://test.httpapi.com', 'live' => 'https://httpapi.com'];
    /** Live availability checks have their own host. */
    private const CHECK_HOSTS = ['test' => 'https://test.httpapi.com', 'live' => 'https://domaincheck.httpapi.com'];
    private const MAX_RETRIES = 2;
    /** curl errors after which a request is tried again. */
    private const NETWORK_ERRORS = [CURLE_COULDNT_RESOLVE_HOST, CURLE_COULDNT_CONNECT, CURLE_OPERATION_TIMEDOUT, CURLE_SEND_ERROR, CURLE_RECV_ERROR, CURLE_GOT_NOTHING, CURLE_SSL_CONNECT_ERROR];

    /** @param string[] $nameservers empty: the customer's default name servers */
    public function __construct(
        private string $resellerId,
        private string $apiKey,
        private string $env = 'test',
        private int $timeout = 30,
        private string $baseUrl = '',
        private array $nameservers = [],
        private bool $privacy = false,
    ) {
        $this->env = isset(self::HOSTS[$env]) ? $env : 'test';
    }

    public function name(): string
    {
        return 'resellerclub';
    }

    public function account(): string
    {
        return 'resellerclub:' . $this->env;
    }

    /** Query string with repeated keys for lists (domain-name=a&domain-name=b) and true/false for booleans. */
    private static function query(array $params): string
    {
        $parts = [];
        foreach ($params as $key => $value) {
            foreach (is_array($value) ? $value : [$value] as $v) {
                $parts[] = rawurlencode((string) $key) . '=' . rawurlencode(is_bool($v) ? ($v ? 'true' : 'false') : (string) $v);
            }
        }
        return implode('&', $parts);
    }

    /**
     * One API call (parameters in the query string, as the API expects for
     * GET and POST). Network errors are tried again up to twice; every
     * attempt is logged. Returns the decoded answer (an array, or a plain
     * value such as a new customer id). Throws RegistrarError.
     */
    private function call(string $action, string $method, string $path, array $params, bool $checkHost = false)
    {
        $base = rtrim($this->baseUrl !== '' ? $this->baseUrl : ($checkHost ? self::CHECK_HOSTS : self::HOSTS)[$this->env], '/');
        $url = $base . $path . '?' . self::query(['auth-userid' => $this->resellerId, 'api-key' => $this->apiKey] + $params);
        for ($attempt = 1; ; $attempt++) {
            $started = microtime(true);
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => $this->timeout,
                CURLOPT_HTTPHEADER => ['Accept: application/json'],
            ]);
            if ($method === 'POST') {
                curl_setopt($ch, CURLOPT_POSTFIELDS, '');
            }
            $raw = curl_exec($ch);
            $status = $raw === false ? null : (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $errno = curl_errno($ch);
            $json = $raw === false ? null : json_decode((string) $raw, true);
            $failure = match (true) {
                $raw === false => new RegistrarError('network', 'Network error: ' . curl_error($ch)),
                $status >= 400, is_array($json) && (strtoupper((string) ($json['status'] ?? '')) === 'ERROR' || isset($json['error'])) => self::error($json ?? (string) $raw, (int) $status),
                default => null,
            };
            registrar_log_write('resellerclub', $action, $method, $path, $params, $status, $raw === false ? '' : (string) $raw, $failure?->getMessage() ?? '', $attempt, (int) ((microtime(true) - $started) * 1000));
            if ($raw === false && in_array($errno, self::NETWORK_ERRORS, true) && $attempt <= self::MAX_RETRIES) {
                usleep(500000 * $attempt);
                continue;
            }
            break;
        }
        if ($failure !== null) {
            throw $failure;
        }
        return $json ?? trim((string) $raw);
    }

    /** Classify an error answer. */
    private static function error($body, int $status): RegistrarError
    {
        $message = is_array($body) ? (string) ($body['message'] ?? $body['error'] ?? json_encode($body)) : (string) $body;
        $lower = strtolower($message);
        $kind = match (true) {
            str_contains($lower, 'insufficient') || str_contains($lower, 'fund') || str_contains($lower, 'balance') => 'funds',
            str_contains($lower, 'already registered') || str_contains($lower, 'not available') || str_contains($lower, 'unavailable') => 'taken',
            $status === 401 || $status === 403 || str_contains($lower, 'authenticat') || str_contains($lower, 'api-key') || str_contains($lower, 'whitelist') || str_contains($lower, 'ip address') => 'config',
            default => 'other',
        };
        return new RegistrarError($kind, 'HTTP ' . $status . ': ' . mb_substr($message, 0, 220));
    }

    public function checkAvailability(array $domains): array
    {
        $labels = [];
        foreach (array_slice($domains, 0, 15) as $d) {
            if (str_ends_with($d, '.com')) {
                $labels[] = substr($d, 0, -4);
            }
        }
        if (!$labels) {
            return [];
        }
        $res = $this->call('availability', 'GET', '/api/domains/available.json', ['domain-name' => $labels, 'tlds' => ['com']], true);
        $out = [];
        foreach ($labels as $label) {
            $status = is_array($res) ? (string) ($res[$label . '.com']['status'] ?? '') : '';
            $out[$label . '.com'] = $status === 'available';
        }
        return $out;
    }

    public function suggest(string $keyword): array
    {
        $res = $this->call('suggest', 'GET', '/api/domains/v5/suggest-names.json', ['keyword' => $keyword, 'tld-only' => ['com'], 'exact-match' => false, 'adult' => false]);
        $out = [];
        foreach (is_array($res) ? $res : [] as $name => $info) {
            $name = strtolower((string) $name);
            if (!str_contains($name, '.')) {
                // A bare name: only .com was asked for (tld-only).
                $name .= '.' . strtolower((string) (is_array($info) ? ($info['tld'] ?? 'com') : 'com'));
            }
            if (str_ends_with($name, '.com')) {
                $out[] = $name;
            }
        }
        return $out;
    }

    public function createCustomer(array $person): string
    {
        $params = [
            'username' => $person['email'],
            // The member never signs in to ResellerClub; a strong random password.
            'passwd' => 'Ev' . bin2hex(random_bytes(5)) . '!9',
            'name' => $person['name'], 'company' => $person['company'],
            'address-line-1' => $person['address'], 'city' => $person['city'], 'state' => $person['state'],
            'country' => $person['country'], 'zipcode' => $person['zip'],
            'phone-cc' => $person['phoneCc'], 'phone' => $person['phone'], 'lang-pref' => 'en',
        ];
        try {
            return (string) $this->call('customer', 'POST', '/api/customers/v2/signup.json', $params);
        } catch (RegistrarError $e) {
            // A customer with this email already exists (an earlier attempt): use it.
            if (!str_contains(strtolower($e->getMessage()), 'already')) {
                throw $e;
            }
            $found = $this->call('customer-lookup', 'GET', '/api/customers/details.json', ['username' => $person['email']]);
            $id = is_array($found) ? (string) ($found['customerid'] ?? '') : '';
            if ($id === '') {
                throw $e;
            }
            return $id;
        }
    }

    public function createContact(string $customerId, array $person): string
    {
        return (string) $this->call('contact', 'POST', '/api/contacts/add.json', [
            'name' => $person['name'], 'company' => $person['company'], 'email' => $person['email'],
            'address-line-1' => $person['address'], 'city' => $person['city'], 'state' => $person['state'],
            'country' => $person['country'], 'zipcode' => $person['zip'],
            'phone-cc' => $person['phoneCc'], 'phone' => $person['phone'],
            'customer-id' => $customerId, 'type' => 'Contact',
        ]);
    }

    public function register(string $domain, int $years, string $customerId, string $contactId): array
    {
        $ns = $this->nameservers;
        if (!$ns) {
            $res = $this->call('default-ns', 'GET', '/api/domains/customer-default-ns.json', ['customer-id' => $customerId]);
            $ns = is_array($res) ? array_values(array_filter(array_map('strval', $res))) : [];
        }
        try {
            $res = $this->call('register', 'POST', '/api/domains/register.json', [
                'domain-name' => $domain, 'years' => $years, 'ns' => $ns, 'customer-id' => $customerId,
                'reg-contact-id' => $contactId, 'admin-contact-id' => $contactId, 'tech-contact-id' => $contactId, 'billing-contact-id' => $contactId,
                'invoice-option' => 'NoInvoice', 'auto-renew' => false,
                'purchase-privacy' => $this->privacy, 'protect-privacy' => $this->privacy,
            ]);
        } catch (RegistrarError $e) {
            // "Already registered" after a retried (timed-out) request may be our own registration.
            if ($e->kind === 'taken' && ($orderId = $this->ownOrderId($domain, $customerId)) !== null) {
                return ['orderId' => $orderId, 'expiresAt' => $this->expiry($orderId)];
            }
            throw $e;
        }
        $orderId = is_array($res) ? (string) ($res['entityid'] ?? '') : '';
        if ($orderId === '') {
            throw new RegistrarError('other', 'Registration answer without an order id: ' . mb_substr(json_encode($res), 0, 200));
        }
        return ['orderId' => $orderId, 'expiresAt' => $this->expiry($orderId)];
    }

    /** The order id when $domain is registered in our account for $customerId, else null. */
    private function ownOrderId(string $domain, string $customerId): ?string
    {
        try {
            $orderId = (string) $this->call('orderid', 'GET', '/api/domains/orderid.json', ['domain-name' => $domain]);
            $details = $this->call('details', 'GET', '/api/domains/details.json', ['order-id' => $orderId, 'options' => 'OrderDetails']);
            return is_array($details) && (string) ($details['customerid'] ?? '') === $customerId ? $orderId : null;
        } catch (RegistrarError $e) {
            return null;
        }
    }

    /** The order's expiry (Unix time), or null when it can't be read. */
    private function expiry(string $orderId): ?int
    {
        try {
            $details = $this->call('details', 'GET', '/api/domains/details.json', ['order-id' => $orderId, 'options' => 'OrderDetails']);
            $end = is_array($details) ? (int) ($details['endtime'] ?? 0) : 0;
            return $end > 0 ? $end : null;
        } catch (RegistrarError $e) {
            return null;
        }
    }

    public function renew(string $orderId, int $years, int $currentExpiry): array
    {
        $this->call('renew', 'POST', '/api/domains/renew.json', [
            'order-id' => $orderId, 'years' => $years, 'exp-date' => $currentExpiry,
            'invoice-option' => 'NoInvoice', 'auto-renew' => false,
        ]);
        return ['expiresAt' => $this->expiry($orderId)];
    }

    public function setDns(string $domain, string $orderId, string $ip): void
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            throw new RegistrarError('config', 'SERVER_IP is not set to an IPv4 address.');
        }
        // DNS service first (already active is fine), then the A records for the domain and www.
        try {
            $this->call('dns-activate', 'POST', '/api/dns/activate.json', ['order-id' => $orderId]);
        } catch (RegistrarError $e) {
            if (!str_contains(strtolower($e->getMessage()), 'already')) {
                throw $e;
            }
        }
        foreach (['', 'www'] as $host) {
            try {
                $this->call('dns-a-record', 'POST', '/api/dns/manage/add-ipv4-record.json', array_filter(['domain-name' => $domain, 'value' => $ip, 'host' => $host, 'ttl' => 3600], fn ($v) => $v !== ''));
            } catch (RegistrarError $e) {
                if (!str_contains(strtolower($e->getMessage()), 'already exist')) {
                    throw $e;
                }
            }
        }
    }
}

/* ---------------------------------------------------------------------
 * Mock registrar (offline)
 * ------------------------------------------------------------------- */

/**
 * Deterministic stand-in: names of 5 letters or fewer and common words are
 * taken, as are domains already registered here; orders and renewals are
 * fake. For testing failures: a name containing "nofunds" fails as if the
 * reseller balance were empty, "failreg" always fails, "failonce" fails on
 * the first attempt only.
 */
final class MockRegistrarClient implements RegistrarClient
{
    private const TAKEN = ['mike', 'john', 'maria', 'juan', 'jose', 'anna', 'google', 'facebook', 'amazon', 'apple', 'test', 'admin', 'shop', 'store', 'home', 'love', 'money', 'business', 'online', 'mikemaranan'];

    public function name(): string
    {
        return 'mock';
    }

    public function account(): string
    {
        return 'mock';
    }

    private function log(string $action, array $request, $response, string $error = ''): void
    {
        registrar_log_write('mock', $action, 'MOCK', $action, $request, $error === '' ? 200 : 500, is_string($response) ? $response : (string) json_encode($response), $error, 1, 0);
    }

    public function checkAvailability(array $domains): array
    {
        $out = [];
        foreach (array_slice($domains, 0, 15) as $d) {
            if (!str_ends_with($d, '.com')) {
                continue;
            }
            $label = substr($d, 0, -4);
            $out[$d] = strlen($label) > 5 && !in_array($label, self::TAKEN, true)
                && !db_value('SELECT 1 FROM user_domains WHERE domain = ?', [$d]);
        }
        $this->log('availability', ['domains' => array_keys($out)], $out);
        return $out;
    }

    public function suggest(string $keyword): array
    {
        $label = preg_replace('/[^a-z0-9]/', '', strtolower($keyword));
        $out = $label === '' ? [] : [$label . 'online.com', $label . 'pro.com', 'get' . $label . '.com', $label . 'hub.com', 'the' . $label . '.com'];
        $this->log('suggest', ['keyword' => $keyword], $out);
        return $out;
    }

    public function createCustomer(array $person): string
    {
        $id = 'MOCK-C' . substr(md5(strtolower($person['email'])), 0, 8);
        $this->log('customer', ['username' => $person['email'], 'name' => $person['name']], $id);
        return $id;
    }

    public function createContact(string $customerId, array $person): string
    {
        $id = 'MOCK-K' . substr(md5($customerId . strtolower($person['email'])), 0, 8);
        $this->log('contact', ['customer-id' => $customerId, 'name' => $person['name']], $id);
        return $id;
    }

    public function register(string $domain, int $years, string $customerId, string $contactId): array
    {
        $request = ['domain-name' => $domain, 'years' => $years, 'customer-id' => $customerId, 'reg-contact-id' => $contactId];
        $failedBefore = (bool) db_value('SELECT 1 FROM registrar_log WHERE driver = "mock" AND action = "register" AND error <> "" AND request LIKE ?', ['%"' . $domain . '"%']);
        $fail = match (true) {
            str_contains($domain, 'nofunds') => new RegistrarError('funds', 'Mock: insufficient funds in the reseller account.'),
            str_contains($domain, 'failreg') => new RegistrarError('other', 'Mock: the registry did not answer.'),
            str_contains($domain, 'failonce') && !$failedBefore => new RegistrarError('other', 'Mock: the registry did not answer (first attempt).'),
            default => null,
        };
        if ($fail) {
            $this->log('register', $request, '', $fail->getMessage());
            throw $fail;
        }
        $orderId = 'MOCK-' . random_int(10000000, 99999999);
        $expires = strtotime("+{$years} year");
        $this->log('register', $request, ['entityid' => $orderId, 'actionstatus' => 'Success']);
        return ['orderId' => $orderId, 'expiresAt' => $expires];
    }

    public function renew(string $orderId, int $years, int $currentExpiry): array
    {
        $expires = strtotime("+{$years} year", $currentExpiry);
        $this->log('renew', ['order-id' => $orderId, 'years' => $years, 'exp-date' => $currentExpiry], ['actionstatus' => 'Success', 'endtime' => $expires]);
        return ['expiresAt' => $expires];
    }

    public function setDns(string $domain, string $orderId, string $ip): void
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $this->log('dns-a-record', ['domain-name' => $domain, 'value' => $ip], '', 'SERVER_IP is not set to an IPv4 address.');
            throw new RegistrarError('config', 'SERVER_IP is not set to an IPv4 address.');
        }
        $this->log('dns-a-record', ['domain-name' => $domain, 'value' => $ip, 'hosts' => ['@', 'www']], ['status' => 'Success']);
    }
}

/**
 * A registrar: $driver "mock" or "resellerclub", or null for the one chosen
 * by REGISTRAR_DRIVER (.env; default "mock"). An order is always completed by
 * the driver it was placed with. Throws RegistrarError (config) when
 * ResellerClub is chosen without its credentials.
 */
function registrar_client(?string $driver = null): RegistrarClient
{
    static $clients = [];
    $driver = $driver ?? (env('REGISTRAR_DRIVER', 'mock') === 'resellerclub' ? 'resellerclub' : 'mock');
    if (isset($clients[$driver])) {
        return $clients[$driver];
    }
    if ($driver !== 'resellerclub') {
        return $clients['mock'] = new MockRegistrarClient();
    }
    $id = (string) env('RESELLERCLUB_ID', '');
    $key = (string) env('RESELLERCLUB_API_KEY', '');
    if ($id === '' || $key === '') {
        throw new RegistrarError('config', 'RESELLERCLUB_ID and RESELLERCLUB_API_KEY must be set in .env.');
    }
    $ns = array_values(array_filter(array_map('trim', explode(',', (string) env('DOMAIN_NAMESERVERS', '')))));
    return $clients['resellerclub'] = new ResellerClubClient(
        $id,
        $key,
        (string) env('RESELLERCLUB_ENV', 'test'),
        (int) env('RESELLERCLUB_TIMEOUT', 30),
        (string) env('RESELLERCLUB_BASE_URL', ''),
        $ns,
        in_array(strtolower((string) env('DOMAIN_PRIVACY', 'false')), ['1', 'true', 'yes'], true),
    );
}
