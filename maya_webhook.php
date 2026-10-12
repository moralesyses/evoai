<?php
/**
 * Maya webhook receiver for every Maya Checkout payment on this site:
 * memberships and Member Shop orders (maya_checkouts, MAYA-… references) and .com domain orders
 * (domain_orders, DOM-… references). Register
 * https://<your-domain>/maya_webhook.php in Maya Business Manager for
 * PAYMENT_SUCCESS, PAYMENT_FAILED, PAYMENT_EXPIRED and PAYMENT_CANCELLED
 * (maya-webhook.php, the older address, runs this same file).
 *
 * The body only says which payment changed. maya_sync() / domain_sync() then
 * ask Maya for that payment with the secret key, so a forged call can't mark
 * anything paid, and a repeated event changes nothing. Maya expects 200 OK
 * within 5 seconds and retries otherwise.
 */
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}

$event = json_decode((string) file_get_contents('php://input', false, null, 0, 65536), true);
$rrn = is_array($event) ? (string) ($event['requestReferenceNumber'] ?? '') : '';
$checkoutId = is_array($event) ? (string) ($event['id'] ?? '') : '';
if (strlen($checkoutId) > 64) {
    $checkoutId = '';
}

/** Answer 200 now and keep working: a domain registration can take longer than Maya waits. */
function webhook_ack_early(): void
{
    ignore_user_abort(true);
    set_time_limit(180);
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(200);
    header('Content-Type: text/plain');
    header('Content-Encoding: none');
    header('Content-Length: 2');
    header('Connection: close');
    echo 'OK';
    flush();
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }
}

// Domain orders: found by our reference or by Maya's checkout id. Only an
// order the event's reference agrees with is looked at.
$order = null;
if (preg_match('/^DOM-\d{6}-[0-9A-F]{10}$/', $rrn)) {
    $order = db_one('SELECT id, checkout_id FROM domain_orders WHERE id = ?', [$rrn]);
} elseif ($rrn === '' && $checkoutId !== '') {
    $order = db_one('SELECT id, checkout_id FROM domain_orders WHERE checkout_id = ?', [$checkoutId]);
}
if ($order && $checkoutId !== '' && $order['checkout_id'] !== $checkoutId) {
    error_log('[maya webhook] ' . $order['id'] . ': event checkout ' . $checkoutId . ' is not the order\'s checkout; ignored.');
    $order = null;
}
if ($order) {
    webhook_ack_early();
    domain_sync($order['id']);
    exit;
}

// Membership checkouts.
$row = null;
if (preg_match('/^MAYA-[0-9A-F]{24}$/', $rrn)) {
    $row = db_one('SELECT rrn FROM maya_checkouts WHERE rrn = ?', [$rrn]);
} elseif ($checkoutId !== '') {
    $row = db_one('SELECT rrn FROM maya_checkouts WHERE checkout_id = ?', [$checkoutId]);
}
if ($row) {
    maya_sync($row['rrn']);
}

// Acknowledge every delivery, also for payments made elsewhere on the same
// Maya account, so Maya doesn't keep retrying them.
http_response_code(200);
header('Content-Type: text/plain');
echo 'OK';
