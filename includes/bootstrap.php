<?php
/**
 * Shared bootstrap: configuration, error handling, session and helpers.
 * Every entry point (index.php, api.php, vcard.php) requires this file.
 */
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));

$configFile = APP_ROOT . '/config/config.php';
if (!is_file($configFile)) {
    $configFile = APP_ROOT . '/config/config.example.php';
}
$GLOBALS['APP_CONFIG'] = require $configFile;

function config(string $key, $default = null)
{
    $value = $GLOBALS['APP_CONFIG'];
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}

// .env (optional): the .com domain settings, and overrides for the Maya keys.
require APP_ROOT . '/includes/env.php';
env_boot();

date_default_timezone_set((string) config('app.timezone', 'Asia/Manila'));
mb_internal_encoding('UTF-8');

if (config('app.debug')) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
}

// Libraries from Composer (Anthropic SDK, PHPMailer). Without vendor/ the
// portal still runs; AI assistance and SMTP email are then unavailable.
if (is_file(APP_ROOT . '/vendor/autoload.php')) {
    require APP_ROOT . '/vendor/autoload.php';
}

require APP_ROOT . '/includes/db.php';
require APP_ROOT . '/includes/helpers.php';
require APP_ROOT . '/includes/auth.php';
require APP_ROOT . '/includes/repo.php';
require APP_ROOT . '/includes/maya.php';
require APP_ROOT . '/includes/signup.php';
require APP_ROOT . '/includes/activation_codes.php';
require APP_ROOT . '/includes/mlm.php';
require APP_ROOT . '/includes/chat.php';
require APP_ROOT . '/includes/weserve.php';
require APP_ROOT . '/includes/users_admin.php';
require APP_ROOT . '/includes/mailer.php';
require APP_ROOT . '/includes/ai.php';
require APP_ROOT . '/includes/crm.php';
require APP_ROOT . '/includes/assistant.php';
require APP_ROOT . '/includes/registrar.php';
require APP_ROOT . '/includes/payment_clients.php';
require APP_ROOT . '/includes/domains.php';
require APP_ROOT . '/includes/domain_valuation.php';
require APP_ROOT . '/includes/website.php';
require APP_ROOT . '/includes/encashments.php';
foreach (glob(APP_ROOT . '/includes/ext/*.php') ?: [] as $extFile) {
    require $extFile;
}

start_app_session();
