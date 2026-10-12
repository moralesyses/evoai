<?php
/**
 * Payments for .com domain orders (includes/domains.php).
 *
 * PAYMENT_DRIVER (.env) picks the PaymentClient: "maya" (default; Maya
 * Checkout, sandbox or production per MAYA_ENV, the only real way to pay) or
 * "mock" (a local "Pay now" page for testing only; nobody is charged). Either way an order counts as paid only after
 * getPayment() has asked the provider server-side; a redirect or webhook
 * body is never trusted.
 */
declare(strict_types=1);

/** The payment provider failed or answered unexpectedly. The message is for logs and admins. */
final class PaymentError extends RuntimeException
{
}

interface PaymentClient
{
    /** "maya" or "mock"; stored on the order as payment_driver. */
    public function name(): string;

    /**
     * Start a hosted checkout for a domain_orders row. $buyer: name, email,
     * phone. Returns ['checkoutId', 'redirectUrl' => where to send the member].
     */
    public function createCheckout(array $order, array $buyer): array;

    /**
     * The order's payment as the provider reports it now: ['status' =>
     * PAYMENT_SUCCESS, PAYMENT_FAILED, PAYMENT_EXPIRED, PAYMENT_CANCELLED or a
     * pending status, 'amount' => float, 'currency', 'reference' => our order
     * id as the provider recorded it, 'paymentId', 'source' => card / wallet
     * description, 'receipt'].
     */
    public function getPayment(array $order): array;
}

/** Where the provider sends the member back to: the order's result page. */
function domain_result_url(string $orderId, string $result = ''): string
{
    return base_url() . '/index.php?' . http_build_query(array_filter(['view' => 'domain', 'order' => $orderId, 'result' => $result]));
}

/** Maya Checkout (https://developers.maya.ph), sharing maya_request() with the membership checkout. */
final class MayaClient implements PaymentClient
{
    public function name(): string
    {
        return 'maya';
    }

    public function createCheckout(array $order, array $buyer): array
    {
        if (!maya_enabled()) {
            throw new PaymentError('Maya keys are not set (MAYA_PUBLIC_KEY / MAYA_SECRET_KEY).');
        }
        $amount = round((float) $order['amount'], 2);
        [$first, $last] = maya_buyer_name((string) $buyer['name']);
        $body = [
            'totalAmount' => ['value' => $amount, 'currency' => $order['currency']],
            'buyer' => array_filter([
                'firstName' => $first,
                'lastName' => $last,
                'contact' => array_filter(['email' => (string) ($buyer['email'] ?? ''), 'phone' => (string) ($buyer['phone'] ?? '')]),
            ]),
            'items' => [[
                'name' => $order['domain'],
                'quantity' => 1,
                'code' => 'domain_' . $order['kind'],
                'description' => ($order['kind'] === 'renew' ? 'Renewal of ' : 'Registration of ') . $order['domain'] . ' for ' . $order['years'] . ' year' . ($order['years'] > 1 ? 's' : ''),
                'amount' => ['value' => $amount],
                'totalAmount' => ['value' => $amount],
            ]],
            'redirectUrl' => [
                'success' => domain_result_url($order['id'], 'success'),
                'failure' => domain_result_url($order['id'], 'failure'),
                'cancel' => domain_result_url($order['id'], 'cancel'),
            ],
            'requestReferenceNumber' => $order['id'],
        ];
        try {
            $res = maya_request('POST', '/checkout/v1/checkouts', 'public', $body);
        } catch (MayaError $e) {
            throw new PaymentError($e->getMessage());
        }

        $checkoutId = (string) ($res['checkoutId'] ?? '');
        $redirect = (string) ($res['redirectUrl'] ?? '');
        $host = (string) parse_url($redirect, PHP_URL_HOST);
        // Only ever send members to Maya's own pages (a test host when MAYA_BASE_URL is set).
        $trusted = maya_base_override() !== ''
            ? preg_match('#^https?://#', $redirect) === 1
            : str_starts_with($redirect, 'https://') && preg_match('/(^|\.)(maya\.ph|paymaya\.com)$/', $host) === 1;
        if ($checkoutId === '' || !$trusted) {
            throw new PaymentError('Maya returned an unexpected checkout: ' . substr(json_encode($res, JSON_UNESCAPED_SLASHES) ?: '', 0, 200));
        }
        return ['checkoutId' => $checkoutId, 'redirectUrl' => $redirect];
    }

    public function getPayment(array $order): array
    {
        if (empty($order['checkout_id'])) {
            return ['status' => 'NOT_STARTED', 'amount' => 0.0, 'currency' => '', 'reference' => '', 'paymentId' => '', 'source' => '', 'receipt' => ''];
        }
        try {
            // The checkout id is also the payment id (secret key).
            $p = maya_request('GET', '/payments/v1/payments/' . rawurlencode($order['checkout_id']), 'secret');
        } catch (MayaError $e) {
            throw new PaymentError($e->getMessage());
        }
        return [
            'status' => strtoupper(substr((string) ($p['status'] ?? 'UNKNOWN'), 0, 30)),
            'amount' => (float) ($p['amount'] ?? 0),
            'currency' => (string) ($p['currency'] ?? ''),
            'reference' => (string) ($p['requestReferenceNumber'] ?? ''),
            'paymentId' => (string) ($p['id'] ?? $order['checkout_id']),
            'source' => trim((string) ($p['fundSource']['description'] ?? $p['fundSource']['type'] ?? '')),
            'receipt' => (string) ($p['receiptNumber'] ?? ''),
        ];
    }
}

/**
 * Payments for local testing: the "checkout" is index.php?view=domain&mockpay=1,
 * whose Pay now / Fail / Cancel buttons (domains.mockPay) set the order's
 * payment_status; getPayment() reports it back as Maya would.
 */
final class MockPaymentClient implements PaymentClient
{
    public function name(): string
    {
        return 'mock';
    }

    public function createCheckout(array $order, array $buyer): array
    {
        return [
            'checkoutId' => 'MOCKPAY-' . $order['id'],
            'redirectUrl' => base_url() . '/index.php?' . http_build_query(['view' => 'domain', 'order' => $order['id'], 'mockpay' => 1]),
        ];
    }

    public function getPayment(array $order): array
    {
        $status = (string) db_value('SELECT payment_status FROM domain_orders WHERE id = ?', [$order['id']]);
        return [
            'status' => $status !== '' ? $status : 'PENDING_PAYMENT',
            'amount' => (float) $order['amount'],
            'currency' => (string) $order['currency'],
            'reference' => (string) $order['id'],
            'paymentId' => (string) $order['checkout_id'],
            'source' => 'Mock payment',
            'receipt' => '',
        ];
    }
}

/**
 * A payment client: $driver "maya" or "mock", or null for the one chosen by
 * PAYMENT_DRIVER (.env; default "maya"). An order is always checked with the
 * driver it was paid with.
 */
function payment_client(?string $driver = null): PaymentClient
{
    static $clients = [];
    $driver = $driver ?? (env('PAYMENT_DRIVER', 'maya') === 'mock' ? 'mock' : 'maya');
    return $clients[$driver] ??= ($driver === 'maya' ? new MayaClient() : new MockPaymentClient());
}
