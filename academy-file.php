<?php
/**
 * Mentor Academy files (includes/academy.php) for signed-in users only:
 *   academy-file.php?id=<material id>             PDFs, videos, audio and pictures open in the browser
 *   academy-file.php?id=<material id>&download=1  any file, saved under its own name
 * Members get published materials; admins also hidden ones. The files live in
 * storage/academy/, which the web server never serves directly. Videos and
 * audio can be skipped through (byte ranges).
 */
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');

$m = academy_file_for(current_user(), is_string($_GET['id'] ?? null) ? $_GET['id'] : '');
if (!$m) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    echo 'Not found. Sign in to the Member Backoffice to open Mentor Academy materials.';
    exit;
}
// Nothing more is needed from the session; let the member's other requests run while this streams.
session_write_close();

$path = APP_ROOT . '/' . $m['file_path'];
$size = (int) filesize($path);
$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$spec = ACADEMY_FILE_TYPES[$ext] ?? null;
$inline = $spec !== null && $spec['view'] !== 'download' && empty($_GET['download']);
$name = (string) ($m['file_name'] ?: 'material.' . $ext);
$ascii = preg_replace('/[^A-Za-z0-9._ -]+/', '_', $name) ?: 'material.' . $ext;

header('Content-Type: ' . ($spec['type'] ?? 'application/octet-stream'));
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($name));
header('Cache-Control: private, max-age=3600');
header('Accept-Ranges: bytes');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', (int) filemtime($path)) . ' GMT');

// One byte range ("bytes=500-", "bytes=500-999" or "bytes=-500"), for skipping through videos.
$start = 0;
$end = $size - 1;
$range = (string) ($_SERVER['HTTP_RANGE'] ?? '');
if ($range !== '' && preg_match('/^bytes=(\d*)-(\d*)$/', trim($range), $r) && ($r[1] !== '' || $r[2] !== '')) {
    if ($r[1] === '') {
        $start = max(0, $size - (int) $r[2]);
    } else {
        $start = (int) $r[1];
        $end = $r[2] !== '' ? min((int) $r[2], $size - 1) : $size - 1;
    }
    if ($start > $end || $start >= $size) {
        http_response_code(416);
        header('Content-Range: bytes */' . $size);
        exit;
    }
    http_response_code(206);
    header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
}
header('Content-Length: ' . ($end - $start + 1));
if (request_method() === 'HEAD') {
    exit;
}

while (ob_get_level() > 0) {
    ob_end_clean();
}
set_time_limit(0);
$fp = fopen($path, 'rb');
fseek($fp, $start);
$left = $end - $start + 1;
while ($left > 0 && !feof($fp) && !connection_aborted()) {
    $chunk = (string) fread($fp, (int) min(1024 * 1024, $left));
    if ($chunk === '') {
        break;
    }
    echo $chunk;
    $left -= strlen($chunk);
    flush();
}
fclose($fp);
