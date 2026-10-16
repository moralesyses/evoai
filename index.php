<?php
/**
 * Front controller.
 *
 *   index.php?view=main        Main Portal (landing, sign-in, registration)
 *   /<link name>               1EVOAI public digital card, e.g. /juan-dela-cruz (.htaccess:
 *                              index.php?card=<link name>); ?view=card&profile=usr_x and
 *                              ?card=… redirect there (views/card.php)
 *   index.php?view=backoffice  Member Backoffice (signed-in members)
 *   index.php?view=admin       Management Admin (admin role)
 *   index.php?view=chat        E2EE Chat (signed-in members)
 *   index.php?view=pay         Online payment (?invoice=INV-x or ?checkout=<item>)
 *   index.php?view=unsubscribe A member's contact stops their emails (?t=<token>)
 *   index.php?view=domain      .com domain order result / test payment page (?order=DOM-…)
 *   index.php?view=site        a member's website (?u=usr_x; &preview=1: their draft)
 *
 * A member's own .com domain (user_domains, pointed at SERVER_IP) opens
 * their website when they have published one, otherwise their digital
 * card; yourname.com/?view=card always opens the card.
 */
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

$views = ['main', 'card', 'backoffice', 'admin', 'chat', 'pay', 'unsubscribe', 'domain', 'site'];
$aliases = ['public' => 'card', 'member_cms' => 'backoffice'];

$view = (string) ($_GET['view'] ?? 'main');
$view = $aliases[$view] ?? $view;
// juandelacruz.com → Juan's website when published, otherwise his card.
if (in_array($view, ['main', 'card'], true) && !isset($_GET['card']) && ($domainOwner = domain_owner_for_host((string) ($_SERVER['HTTP_HOST'] ?? ''))) !== null) {
    if (!isset($_GET['view']) && site_is_live($domainOwner)) {
        $view = 'site';
        $_GET['u'] = $domainOwner;
    } else {
        $view = 'card';
        $_GET['profile'] = $domainOwner;
    }
}
if (isset($_GET['card'])) {
    $view = 'card';
}
// Payments are kept off the digital cards: invoice and checkout links open the payment page.
if (isset($_GET['invoice']) || ($view === 'card' && isset($_GET['checkout']))) {
    $view = 'pay';
}
if (!in_array($view, $views, true)) {
    $view = 'main';
}

$user = current_user();
$pageScripts = [];
$pageData = [];
$pageMeta = [];

ob_start();
require __DIR__ . '/views/' . $view . '.php';
$content = ob_get_clean();
// A view that prints its own complete page (a member's website) skips the portal layout.
if (!empty($standalone)) {
    echo $content;
    return;
}

require __DIR__ . '/views/layout.php';
