<?php
/**
 * Maya Checkout actions used by assets/js/checkout.js and assets/js/views/pay.js.
 * Loaded by api.php; see includes/maya.php for the flow.
 */
declare(strict_types=1);

return [
    // Validate the checkout, create the Maya checkout and return Maya's page address.
    'payments.mayaStart' => static function (array $in): array {
        $req = checkout_request($in);
        return ['redirectUrl' => maya_start_checkout($req, current_user())['redirectUrl']];
    },

    // Where a returning customer's payment stands ('paid', 'pending' or 'failed').
    'payments.mayaStatus' => static function (array $in): array {
        $rrn = str_in($in, 'rrn', 36);
        $row = preg_match('/^MAYA-[0-9A-F]{24}$/', $rrn) ? maya_sync($rrn) : null;
        if (!$row) {
            api_fail('This Maya payment link is not valid.', 404);
        }
        return ['state' => maya_state($row), 'status' => $row['status']];
    },
];
