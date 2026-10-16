<?php
/**
 * View + request helpers: escaping, icons, number/date formatting, URLs,
 * CSRF protection and small utilities shared by pages and the JSON API.
 */
declare(strict_types=1);

/** Escape a value for HTML text or attribute context. */
function e($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Render a lucide icon as inline SVG (same markup lucide-react produces). */
function icon(string $name, string $class = '', array $attrs = []): string
{
    static $icons = null;
    if ($icons === null) {
        $icons = require APP_ROOT . '/includes/icons.php';
    }
    $inner = $icons[$name] ?? '';
    $extra = '';
    foreach ($attrs as $k => $v) {
        $extra .= ' ' . e($k) . '="' . e($v) . '"';
    }
    $cls = trim('lucide lucide-' . $name . ' ' . $class);
    return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="' . e($cls) . '" aria-hidden="true"' . $extra . '>' . $inner . '</svg>';
}

/**
 * Format a number the way JavaScript's Number#toLocaleString('en-US') does
 * (thousands separators, up to 3 fraction digits, trailing zeros trimmed).
 */
function num($n): string
{
    $n = (float) $n;
    $s = number_format($n, 3, '.', ',');
    $s = rtrim(rtrim($s, '0'), '.');
    return $s === '-0' ? '0' : $s;
}

/** Peso amount: ₱42,800 */
function peso($n): string
{
    $n = (float) $n;
    // Whole amounts as ₱42,800; the others with two decimals (₱99.50, not ₱99.5).
    return '₱' . (abs($n - round($n)) < 0.005 ? num($n) : number_format($n, 2, '.', ','));
}

function to_ts($value): int
{
    if ($value instanceof DateTimeInterface) {
        return $value->getTimestamp();
    }
    if (is_numeric($value)) {
        $n = (float) $value;
        // Values above ~1973 in milliseconds are JS timestamps.
        return (int) ($n > 100000000000 ? floor($n / 1000) : $n);
    }
    $t = strtotime((string) $value);
    return $t === false ? time() : $t;
}

/** JS milliseconds timestamp for a DB datetime. */
function to_ms($value): int
{
    return to_ts($value) * 1000;
}

/** new Date(x).toLocaleString() in en-US: 10/7/2026, 3:55:58 PM */
function fmt_datetime($value): string
{
    return date('n/j/Y, g:i:s A', to_ts($value));
}

/** toLocaleDateString(): 10/7/2026 */
function fmt_date($value): string
{
    return date('n/j/Y', to_ts($value));
}

/** toLocaleTimeString(): 3:55:58 PM */
function fmt_time($value): string
{
    return date('g:i:s A', to_ts($value));
}

/** Base path of the app (e.g. "/evoai"), detected from the entry script. */
function base_path(): string
{
    static $base = null;
    if ($base === null) {
        $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
        $base = rtrim($dir, '/');
        if ($base === '.') {
            $base = '';
        }
    }
    return $base;
}

/** Absolute origin + base path, used for share links and vCards. */
function base_url(): string
{
    $configured = (string) config('app.base_url', '');
    if ($configured !== '') {
        return rtrim($configured, '/');
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') === '443');
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return ($https ? 'https' : 'http') . '://' . $host . base_path();
}

/** URL to an asset with a cache-busting version. */
function asset(string $path): string
{
    $file = APP_ROOT . '/' . ltrim($path, '/');
    $v = is_file($file) ? (string) filemtime($file) : '1';
    return base_path() . '/' . ltrim($path, '/') . '?v=' . $v;
}

/** Resolve a stored media path (relative to the app or absolute URL). */
function media_url(?string $path): string
{
    $path = (string) $path;
    if ($path === '') {
        return '';
    }
    if (preg_match('#^(https?:)?//#i', $path) || str_starts_with($path, 'data:')) {
        return $path;
    }
    return base_path() . '/' . ltrim($path, '/');
}

/** Link to a portal view: url('backoffice', ['tab' => 'network']) */
function url(string $view = 'main', array $params = []): string
{
    $query = array_merge(['view' => $view], $params);
    if ($view === 'main' && count($params) === 0) {
        return base_path() . '/';
    }
    return base_path() . '/index.php?' . http_build_query($query);
}

function request_method(): string
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
}

function client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

/** Short device label for audit logs, e.g. "Chrome Desktop / Windows". */
function device_label(): string
{
    $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
    $browser = 'Web Client';
    if (stripos($ua, 'Edg/') !== false) {
        $browser = 'Edge';
    } elseif (stripos($ua, 'Chrome') !== false) {
        $browser = 'Chrome';
    } elseif (stripos($ua, 'Firefox') !== false) {
        $browser = 'Firefox';
    } elseif (stripos($ua, 'Safari') !== false) {
        $browser = 'Safari';
    }
    $os = '';
    if (preg_match('/iPhone|iPad|iPod/i', $ua)) {
        $os = 'iOS';
    } elseif (stripos($ua, 'Android') !== false) {
        $os = 'Android';
    } elseif (stripos($ua, 'Windows') !== false) {
        $os = 'Windows';
    } elseif (stripos($ua, 'Mac OS') !== false) {
        $os = 'macOS';
    } elseif (stripos($ua, 'Linux') !== false) {
        $os = 'Linux';
    }
    $mobile = preg_match('/Mobile|Android|iPhone/i', $ua) ? 'Mobile' : 'Desktop';
    return $browser . ' ' . $mobile . ($os !== '' ? ' / ' . $os : '');
}

/** Random numeric string with $digits digits (no leading zero). */
function rand_digits(int $digits): string
{
    return (string) random_int(10 ** ($digits - 1), (10 ** $digits) - 1);
}

/** Unique id with a prefix, e.g. gen_id('lead_') => lead_1791358471541 */
function gen_id(string $prefix): string
{
    return $prefix . (int) floor(microtime(true) * 1000) . random_int(10, 99);
}

/** Last N digits of the current millisecond clock (reference id style). */
function ms_tail(int $n): string
{
    return substr((string) (int) floor(microtime(true) * 1000), -$n);
}

/* ---------------------------------------------------------------------
 * CSRF
 * ------------------------------------------------------------------- */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function verify_csrf(?string $token): bool
{
    return is_string($token) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
}

/* ---------------------------------------------------------------------
 * Validation helpers (server-side)
 * ------------------------------------------------------------------- */
function str_in(array $in, string $key, int $max = 255): string
{
    $v = $in[$key] ?? '';
    if (is_array($v)) {
        return '';
    }
    $v = trim((string) $v);
    return mb_substr($v, 0, $max);
}

function int_in(array $in, string $key, int $default = 0): int
{
    $v = $in[$key] ?? $default;
    return is_numeric($v) ? (int) $v : $default;
}

function float_in(array $in, string $key, float $default = 0.0): float
{
    $v = $in[$key] ?? $default;
    return is_numeric($v) ? (float) $v : $default;
}

function valid_email(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/** Only allow http(s) URLs or app-relative paths for user-supplied links/media. */
function safe_url(string $url, bool $allowRelative = true): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $url)) {
        return filter_var($url, FILTER_VALIDATE_URL) !== false ? $url : '';
    }
    if ($allowRelative && preg_match('#^/?[A-Za-z0-9_\-./]+$#', $url) && strpos($url, '..') === false) {
        return $url;
    }
    return '';
}

/** JSON encode for inline <script> blocks (safe against </script> injection). */
function js_json($data): string
{
    return json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/**
 * One CSV line (RFC 4180 quoting). A cell starting with = + - @ or a tab is
 * prefixed with ' so spreadsheets show it as text instead of running it as a
 * formula (names and other typed-in values end up in exports).
 */
function csv_line(array $cells): string
{
    return implode(',', array_map(static function ($cell): string {
        $cell = (string) ($cell ?? '');
        if ($cell !== '' && strpbrk($cell[0], "=+-@\t\r") !== false) {
            $cell = "'" . $cell;
        }
        return preg_match('/[",\r\n]/', $cell) ? '"' . str_replace('"', '""', $cell) . '"' : $cell;
    }, $cells));
}

/** Merge class lists for a two-state element (active/inactive). */
function cls(bool $cond, string $on, string $off = ''): string
{
    return $cond ? $on : $off;
}
