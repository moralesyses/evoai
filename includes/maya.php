<?php
/**
 * Maya Checkout (hosted payment page), https://developers.maya.ph.
 *
 * Maya is the only way to pay online on this site: memberships, renewals and
 * invoices (checkout modal), sign-ups, Member Shop orders and .com domains
 * (includes/payment_clients.php).
 *
 * 1. payments.mayaStart (or mlm.checkout for a shop order) stores the attempt
 *    in maya_checkouts and creates a Maya checkout (public key); the browser
 *    goes to Maya's page.
 * 2. Maya sends the customer back to index.php?view=pay&maya=<rrn> (a shop
 *    order: the backoffice shop), and may also call maya_webhook.php.
 * 3. Either way maya_sync() asks Maya for the payment (secret key) and only
 *    then records it. Nothing in a redirect or webhook body is trusted.
 *
 * Card and wallet details are typed on Maya's page and never reach this server.
 */
declare(strict_types=1);

const MAYA_HOSTS = ['sandbox' => 'https://pg-sandbox.paymaya.com', 'production' => 'https://pg.paymaya.com'];
/** Maya payment statuses after which nothing more will happen. */
const MAYA_UNPAID_FINAL = ['PAYMENT_FAILED', 'PAYMENT_EXPIRED', 'PAYMENT_CANCELLED', 'AUTH_FAILED', 'PAYMENT_INVALID', 'VOIDED', 'REFUNDED', 'ACCOUNT_ABUSE', 'CAPTURE_HOLD_EXPIRED'];
/** Starts allowed per browser session in MAYA_START_WINDOW seconds. */
const MAYA_START_LIMIT = 10;
const MAYA_START_WINDOW = 600;

class MayaError extends RuntimeException
{
}

/** 'sandbox' or 'production'. */
function maya_environment(): string
{
    $env = (string) config('maya.environment', 'sandbox');
    return isset(MAYA_HOSTS[$env]) ? $env : 'sandbox';
}

/** Whether Maya Checkout is configured (both API keys set). */
function maya_enabled(): bool
{
    return (string) config('maya.public_key', '') !== '' && (string) config('maya.secret_key', '') !== '';
}

/** MAYA_BASE_URL (.env): another API host for local tests (a fake Maya); '' normally. */
function maya_base_override(): string
{
    return rtrim((string) env('MAYA_BASE_URL', ''), '/');
}

/**
 * Call the Maya API with the public or secret key. Returns the decoded JSON.
 * Throws MayaError when Maya can't be reached or answers with an error.
 */
function maya_request(string $method, string $path, string $key, ?array $body = null): array
{
    $apiKey = (string) config($key === 'secret' ? 'maya.secret_key' : 'maya.public_key', '');
    $ch = curl_init((maya_base_override() ?: MAYA_HOSTS[maya_environment()]) . $path);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 25,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json', 'Authorization: Basic ' . base64_encode($apiKey . ':')],
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $transportError = curl_error($ch);

    if ($raw === false) {
        throw new MayaError('Maya could not be reached: ' . $transportError);
    }
    $json = json_decode((string) $raw, true);
    if ($status < 200 || $status >= 300 || !is_array($json)) {
        $detail = is_array($json) ? (string) ($json['message'] ?? $json['error'] ?? '') . (isset($json['parameters']) ? ' ' . json_encode($json['parameters']) : '') : substr((string) $raw, 0, 200);
        throw new MayaError("Maya answered HTTP $status: $detail");
    }
    return $json;
}

/** Split "Juan Dela Cruz" into Maya's first and last name. */
function maya_buyer_name(string $name): array
{
    $parts = preg_split('/\s+/u', trim($name)) ?: [];
    $last = count($parts) > 1 ? array_pop($parts) : '';
    return [implode(' ', $parts), $last];
}

/** Refuse a burst of checkout starts from one browser session. */
function maya_throttle(): void
{
    $now = time();
    $recent = array_values(array_filter((array) ($_SESSION['maya_starts'] ?? []), fn ($t) => is_int($t) && $t > $now - MAYA_START_WINDOW));
    if (count($recent) >= MAYA_START_LIMIT) {
        throw new InvalidArgumentException('Too many payment attempts. Please wait a few minutes and try again.');
    }
    $recent[] = $now;
    $_SESSION['maya_starts'] = $recent;
}

/**
 * Start a Maya checkout for a checkout_request(), or for a Member Shop order
 * ($orderId: an mlm_orders row awaiting payment; the customer comes back to
 * the backoffice shop). Returns ['rrn' => the attempt's reference,
 * 'redirectUrl' => Maya's payment page].
 */
function maya_start_checkout(array $req, ?array $user, ?string $orderId = null): array
{
    if (!maya_enabled()) {
        throw new InvalidArgumentException('Maya Checkout is not set up yet.');
    }
    maya_throttle();

    // One reference per attempt (Maya requires them to be unique); also the
    // hard-to-guess key of the result page.
    $rrn = 'MAYA-' . strtoupper(bin2hex(random_bytes(12)));
    $amount = round((float) $req['item']['price'], 2);
    db_run('INSERT INTO maya_checkouts (rrn, environment, user_id, invoice_id, order_id, item_type, item_name, amount, customer_name, customer_email, customer_phone)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [$rrn, maya_environment(), $user['id'] ?? null, $req['invoice']['id'] ?? null, $orderId, $req['itemType'], $req['item']['name'], $amount, $req['name'], $req['email'], $req['phone']]);

    $back = fn (string $result) => base_url() . '/index.php?' . http_build_query($orderId !== null
        ? ['view' => 'backoffice', 'tab' => 'shop', 'shop' => 'orders', 'maya' => $rrn, 'result' => $result]
        : ['view' => 'pay', 'maya' => $rrn, 'result' => $result]);
    [$first, $last] = maya_buyer_name($req['name']);
    try {
        $body = [
            'totalAmount' => ['value' => $amount, 'currency' => 'PHP'],
            'buyer' => array_filter([
                'firstName' => $first,
                'lastName' => $last,
                'contact' => array_filter(['email' => $req['email'], 'phone' => $req['phone']]),
            ]),
            'items' => [[
                'name' => mb_substr($req['item']['name'], 0, 120),
                'quantity' => 1,
                'code' => $req['itemType'],
                'amount' => ['value' => $amount],
                'totalAmount' => ['value' => $amount],
            ]],
            'redirectUrl' => ['success' => $back('success'), 'failure' => $back('failure'), 'cancel' => $back('cancel')],
            'requestReferenceNumber' => $rrn,
        ];
        // Maya rejects an empty metadata ([] in JSON), so only send it when there is something to say.
        if ($req['invoice']) {
            $body['metadata'] = ['invoice' => $req['invoice']['reference_number']];
        }
        $res = maya_request('POST', '/checkout/v1/checkouts', 'public', $body);
    } catch (MayaError $e) {
        db_run('UPDATE maya_checkouts SET status = "START_FAILED" WHERE rrn = ?', [$rrn]);
        error_log('[maya] create checkout failed for ' . $rrn . ': ' . $e->getMessage());
        throw new InvalidArgumentException('Maya Checkout is not available right now. Nothing was charged; please try again in a few minutes.');
    }

    $redirect = (string) ($res['redirectUrl'] ?? '');
    $host = (string) parse_url($redirect, PHP_URL_HOST);
    if (empty($res['checkoutId']) || !str_starts_with($redirect, 'https://') || !preg_match('/(^|\.)(maya\.ph|paymaya\.com)$/', $host)) {
        db_run('UPDATE maya_checkouts SET status = "START_FAILED" WHERE rrn = ?', [$rrn]);
        throw new InvalidArgumentException('Maya Checkout returned an unexpected answer. Please try again.');
    }
    db_run('UPDATE maya_checkouts SET checkout_id = ? WHERE rrn = ?', [$res['checkoutId'], $rrn]);
    add_audit('Maya checkout started: ' . peso($amount) . ' for ' . $req['item']['name'] . ' (' . $rrn . ')', $user['id'] ?? null);
    return ['rrn' => $rrn, 'redirectUrl' => $redirect];
}

/**
 * Whether the attempt's payment is recorded: a membership payment in the
 * ledger (payment_id), or a Member Shop order marked paid (order_id, status
 * PAYMENT_SUCCESS; shop orders aren't in the payments ledger).
 */
function maya_settled(array $row): bool
{
    return $row['payment_id'] !== null || ($row['order_id'] !== null && $row['status'] === 'PAYMENT_SUCCESS');
}

/** 'paid', 'failed' or 'pending' for a maya_checkouts row. */
function maya_state(array $row): string
{
    if (maya_settled($row)) {
        return 'paid';
    }
    if (in_array($row['status'], MAYA_UNPAID_FINAL, true) || in_array($row['status'], ['START_FAILED', 'MISMATCH'], true)) {
        return 'failed';
    }
    return 'pending';
}

/**
 * Ask Maya for the attempt's payment and record it when it is paid. Safe to
 * call any number of times, also at once (redirect and webhook): the payment
 * is recorded only once. Returns the updated row, or null for an unknown rrn.
 */
function maya_sync(string $rrn): ?array
{
    $row = db_one('SELECT * FROM maya_checkouts WHERE rrn = ?', [$rrn]);
    // A declined card can be retried on the same Maya page, so keep asking
    // until it is paid; only a recorded or rejected (MISMATCH) one is settled.
    if (!$row || maya_settled($row) || $row['checkout_id'] === null || $row['status'] === 'MISMATCH') {
        return $row;
    }
    try {
        $p = maya_request('GET', '/payments/v1/payments/' . rawurlencode($row['checkout_id']), 'secret');
    } catch (MayaError $e) {
        error_log('[maya] status check failed for ' . $rrn . ': ' . $e->getMessage());
        return $row; // Still pending; the page or the webhook will ask again.
    }
    $status = strtoupper(substr((string) ($p['status'] ?? 'UNKNOWN'), 0, 30));

    if ($status !== 'PAYMENT_SUCCESS') {
        db_run('UPDATE maya_checkouts SET status = ? WHERE rrn = ? AND payment_id IS NULL', [$status, $rrn]);
        if ($row['order_id'] !== null && in_array($status, MAYA_UNPAID_FINAL, true)) {
            // The shop order stays in the records as cancelled; a later success still marks it paid.
            db_run('UPDATE mlm_orders SET status = "cancelled" WHERE id = ? AND status = "awaiting_payment"', [$row['order_id']]);
        }
        return db_one('SELECT * FROM maya_checkouts WHERE rrn = ?', [$rrn]);
    }

    // Paid: it must be this attempt, for the full amount, in pesos.
    if (($p['requestReferenceNumber'] ?? '') !== $rrn || abs((float) ($p['amount'] ?? 0) - (float) $row['amount']) > 0.005 || ($p['currency'] ?? '') !== 'PHP') {
        db_run('UPDATE maya_checkouts SET status = "MISMATCH" WHERE rrn = ?', [$rrn]);
        add_audit('Maya payment ' . $rrn . ' NOT recorded: amount or reference did not match. Check it in Maya Business Manager.', $row['user_id']);
        return db_one('SELECT * FROM maya_checkouts WHERE rrn = ?', [$rrn]);
    }

    $source = trim((string) ($p['fundSource']['description'] ?? $p['fundSource']['type'] ?? ''));
    $paidWith = sprintf('Paid via Maya Checkout%s%s. Maya payment %s%s.',
        maya_environment() === 'sandbox' ? ' (SANDBOX test payment)' : '',
        $source !== '' ? ' with ' . $source : '',
        $row['checkout_id'],
        !empty($p['receiptNumber']) ? ', receipt ' . $p['receiptNumber'] : '');
    $note = $paidWith . ' Digital identity activated.';

    // [payment id, user id] for the request that recorded it; null for any other.
    $paid = db_tx(function () use ($rrn, $note, $paidWith) {
        $locked = db_one('SELECT * FROM maya_checkouts WHERE rrn = ? FOR UPDATE', [$rrn]);
        if (maya_settled($locked)) {
            return null; // Another request recorded it first.
        }
        if ($locked['order_id'] !== null) {
            // A Member Shop order (not a membership payment: nothing goes in the payments ledger).
            mlm_shop_order_paid($locked['order_id'], $paidWith);
            db_run('UPDATE maya_checkouts SET status = "PAYMENT_SUCCESS" WHERE rrn = ?', [$rrn]);
            return null;
        }
        $invoice = $locked['invoice_id'] ? db_one('SELECT * FROM payments WHERE id = ?', [$locked['invoice_id']]) : null;
        $req = [
            'itemType' => $locked['item_type'],
            'item' => ['name' => $locked['item_name'], 'price' => (float) $locked['amount']],
            'invoice' => $invoice,
            'name' => $locked['customer_name'], 'email' => $locked['customer_email'], 'phone' => $locked['customer_phone'],
        ];
        $id = write_checkout_payment($req, 'maya', $locked['user_id'], $note);
        db_run('UPDATE maya_checkouts SET status = "PAYMENT_SUCCESS", payment_id = ? WHERE rrn = ?', [$id, $rrn]);
        // A sign-up paid with this checkout: its account is created now, with the payment.
        $userId = signup_complete($rrn, $id) ?? $locked['user_id'];
        return [$id, $userId];
    });
    if ($paid !== null) {
        checkout_after_payment(...$paid);
    }
    return db_one('SELECT * FROM maya_checkouts WHERE rrn = ?', [$rrn]);
}

/** Customer-facing explanation for an attempt that wasn't paid. */
function maya_failure_message(string $status): string
{
    return match ($status) {
        'PAYMENT_CANCELLED' => 'You cancelled the payment on Maya. Nothing was charged.',
        'PAYMENT_EXPIRED' => 'The Maya checkout expired before it was paid (it stays open for an hour). Nothing was charged.',
        'START_FAILED' => 'Maya Checkout could not be opened. Nothing was charged.',
        'MISMATCH' => 'Maya reported a payment that doesn\'t match this checkout, so it wasn\'t recorded. Please contact the 88MMM administrator with the reference below.',
        default => 'Maya didn\'t complete the payment. Nothing was charged; you can try again with another card or your Maya wallet.',
    };
}
