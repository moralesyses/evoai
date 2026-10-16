<?php
/**
 * Application configuration.
 *
 * Copy this file to config/config.php and adjust the values for your
 * environment. config/config.php is loaded when present; otherwise this
 * example file is used, which matches a default XAMPP installation.
 */
return [
    'db' => [
        'host'    => '127.0.0.1',
        'port'    => 3306,
        'name'    => 'evoai',
        'user'    => 'root',
        'pass'    => '',
        'charset' => 'utf8mb4',
    ],

    'app' => [
        // Shown in the browser title bar.
        'name'     => '1EVOAI Mike Maranan - E2EE Executive Portal & Mobile Hub',
        // PHP timezone used for dates rendered by the server.
        'timezone' => 'Asia/Manila',
        // Public base URL used for share links / QR codes / vCards.
        // Leave empty to detect it from the current request.
        'base_url' => '',
        // Demo mode adds the reference portal's demo conveniences: the header
        // account switcher (any signed-in user can switch to any account) and
        // on-screen one-time recovery passes. Set to false in production.
        'demo_mode' => true,
        // Show PHP errors in the browser (development only).
        'debug'    => false,
    ],

    // 1EVOAI Card (digital vCard) settings.
    'card' => [
        // WeServe member cards (/<id>-<code>) from the UserProfile and
        // NetworkAccount tables (database/weserve_members.sql). The code in each
        // link is signed with this secret; leave empty to turn member cards off.
        // Use the same value as LINK_SECRET in the WeServe vCard app
        // (users/app/config.php) to give members the same link codes there and here.
        // Changing it changes every member's link.
        'link_secret' => '',
        // A member only gets a card with a NetworkAccount in one of these states.
        'network_account_statuses' => ['active', 'inactive', 'expired'],
    ],

    // Maya Checkout (Maya's hosted payment page; https://developers.maya.ph), the
    // only way to pay online: memberships, invoices, sign-ups, the Member Shop and
    // .com domains. Without both keys, online payment is unavailable.
    'maya' => [
        // 'sandbox' (test payments, no real money) or 'production'.
        'environment' => 'sandbox',
        // Sandbox keys: your own from Maya Business Manager, or the shared test
        // keys at https://developers.maya.ph/reference/sandbox-credentials-and-cards
        'public_key' => '',
        'secret_key' => '',
    ],
    // Email for Backoffice > Contacts (auto responder and messages to contacts).
    'mail' => [
        // 'off'  : nothing is sent; messages wait in the outbox as Scheduled.
        // 'log'  : written to storage/mail/mail.log instead of sent (development).
        // 'smtp' : sent through the SMTP server below.
        'transport'  => 'off',
        // Sender address: one your SMTP server may send from (e.g. no-reply@yourdomain.com).
        // Emails show "<member name> via <from_name>", and replies go to the member.
        'from_email' => '',
        'from_name'  => '88MMM Circle',
        'smtp' => [
            'host'       => '',
            'port'       => 587,
            // 'tls' (STARTTLS, usually port 587), 'ssl' (port 465) or '' (none).
            'encryption' => 'tls',
            'username'   => '',
            'password'   => '',
        ],
    ],

    // AI assistance in Backoffice > Contacts: drafts auto responder messages and
    // personal follow-ups, and writes "AI writes it" auto responder emails; and
    // the 88MMM Assistant chat. Without an API key for its provider, each is off.
    'ai' => [
        // Contacts tab: 'groq' (Groq, https://console.groq.com) or 'anthropic' (Claude).
        'provider'      => 'groq',
        // 88MMM Assistant chat: 'anthropic' or 'groq' (empty: the same as 'provider').
        'chat_provider' => 'anthropic',
        'groq' => [
            // API key (gsk_…); the GROQ_API_KEY environment variable also works.
            'api_key' => '',
            // A model with strict JSON output: openai/gpt-oss-120b or openai/gpt-oss-20b.
            'model'   => 'openai/gpt-oss-120b',
        ],
        'anthropic' => [
            // API key from https://console.anthropic.com; ANTHROPIC_API_KEY also works.
            'api_key' => '',
            'model'   => 'claude-opus-5-5',
            // How much Claude thinks before writing: 'low' keeps drafts quick.
            'effort'  => 'low',
        ],
        // AI drafts each member may request per day.
        'daily_limit' => 40,
        // Auto responder emails AI may write per member per day (for messages
        // set to "AI writes it"); the rest wait until the limit resets.
        'daily_auto_limit' => 100,
        // 88MMM Assistant (the "Ask AI" chat): questions per member per day,
        // and per visitor (IP address) per hour.
        'chat_limit'       => 100,
        'chat_guest_limit' => 20,
        // Leave empty (only for an API proxy or testing).
        'base_url'    => '',
    ],

    'session' => [
        'name'          => 'EVOAI_SESSION',
        // "Keep executive session active" keeps the sign-in for this many days.
        'remember_days' => 30,
    ],
];
