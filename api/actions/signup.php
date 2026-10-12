<?php
/**
 * Sign-up (Create Account on the main portal), paid online first (includes/signup.php)
 * or with an activation code (includes/activation_codes.php).
 * Loaded by api.php.
 */
declare(strict_types=1);

return [
    // Validate the sign-up and return Maya's page for the membership payment.
    'auth.registerStart' => static function (array $in): array {
        return ['redirectUrl' => signup_start($in)];
    },

    // Or pay with an activation code + PIN: the account is created and signed in now.
    'auth.registerWithCode' => static function (array $in): array {
        $user = signup_with_code($in);
        login_user($user, true);
        add_audit('Successful portal sign-in', $user['id']);
        return ['user' => user_public($user), 'csrf' => csrf_token()];
    },
];
