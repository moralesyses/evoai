<?php
/**
 * A member's website (includes/website.php), printed as its own complete
 * page without the portal layout:
 *   - on their domain (index.php routes it here when it is published);
 *   - at index.php?view=site&u=usr_x (published sites only);
 *   - &preview=1: the draft, for the member themselves or an admin
 *     (the builder's preview frame).
 * Every text is escaped; links, images and videos were checked when saved.
 * The Shop section's buyers pay with Maya (card.buy, includes/cards.php) and
 * come back here with ?maya=<rrn>, which shows how their order went.
 */
$standalone = true;
$siteOwner = is_string($_GET['u'] ?? null) ? $_GET['u'] : '';
$sitePreview = isset($_GET['preview']) && $user && ($user['id'] === $siteOwner || is_admin($user));
$site = null;
if ($siteOwner !== '' && db_value('SELECT 1 FROM users WHERE id = ?', [$siteOwner])) {
    $site = $sitePreview ? site_draft($siteOwner) : site_public($siteOwner);
}
header("Content-Security-Policy: default-src 'self'; img-src 'self' https: http: data:; style-src 'self' 'unsafe-inline'; script-src 'self'; frame-src https://www.youtube-nocookie.com https://player.vimeo.com; connect-src 'self'; form-action 'self'; base-uri 'none'; object-src 'none'; frame-ancestors 'self'");
if ($site === null) {
    http_response_code(404);
    ?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex"><title>Website not available</title>
<link rel="stylesheet" href="<?= e(asset('assets/css/site.css')) ?>"></head>
<body class="site-missing"><main><h1>This website isn't available.</h1><p>It may not be published yet.</p><p><a href="<?= e(url('main')) ?>">88MMM Circle</a></p></main></body></html>
<?php
    return;
}
if (!$sitePreview) {
    db_run('UPDATE member_websites SET views = views + 1 WHERE user_id = ?', [$siteOwner]);
}

$sp = get_member_profile($siteOwner);
$set = $site['settings'];
$theme = SITE_THEMES[$set['theme']] ?? SITE_THEMES['gold'];
$accent = $set['accent'] !== '' ? $set['accent'] : $theme['accent'];
// Text on the accent colour: dark on light accents, white on dark ones.
[$ar, $ag, $ab] = sscanf($accent, '#%02x%02x%02x');
$accentText = (0.299 * $ar + 0.587 * $ag + 0.114 * $ab) > 160 ? '#111111' : '#ffffff';
$font = SITE_FONTS[$set['font']]['stack'] ?? SITE_FONTS['sans']['stack'];
$siteName = $set['siteName'] !== '' ? $set['siteName'] : (string) $sp['name'];
$onDomain = domain_owner_for_host((string) ($_SERVER['HTTP_HOST'] ?? '')) === $siteOwner;
// The card shows a "Back to website" link (site_card_back()); $back is the section to return to.
$cardUrl = static fn (string $back = ''): string => base_path() . ($onDomain ? '/?' : '/index.php?')
    . http_build_query(($onDomain ? ['view' => 'card'] : ['view' => 'card', 'profile' => $siteOwner]) + ['from' => $sitePreview ? 'preview' : 'site'] + ($back !== '' ? ['back' => $back] : []));
$vcardUrl = base_path() . '/vcard.php?id=' . rawurlencode($siteOwner);
$visible = array_values(array_filter($site['sections'], fn ($s) => !$s['hidden']));
// The Shop section sells the products the member shows on their card (Backoffice > My Products, includes/cards.php).
$shopProducts = in_array('shop', array_column($visible, 'type'), true) ? card_products($siteOwner, true) : [];
if (!$shopProducts && !$sitePreview) {
    $visible = array_values(array_filter($visible, fn ($s) => $s['type'] !== 'shop')); // nothing to sell yet
}
$shopMaya = maya_enabled();
// Back from Maya after buying in the Shop (?maya=<rrn>): paid, waiting or not paid.
$siteOrder = !$sitePreview && is_string($_GET['maya'] ?? null)
    ? card_order_return($_GET['maya'], is_string($_GET['result'] ?? null) ? $_GET['result'] : '', $siteOwner) : null;
$shopSection = array_values(array_filter($visible, fn ($s) => $s['type'] === 'shop'))[0]['id'] ?? '';
$firstName = explode(' ', trim((string) $sp['name']))[0];
$schema = site_schema();
$menu = [];
foreach ($visible as $s) {
    $label = $s['data']['title'] ?? '';
    if ($s['type'] !== 'hero' && $s['type'] !== 'cta' && $label !== '') {
        $menu[$s['id']] = $label;
    }
}
/** Paragraphs from a textarea: blank lines split paragraphs, single line breaks stay. */
$paras = static function (string $text): string {
    $out = '';
    foreach (preg_split('/\n\s*\n/', trim($text)) ?: [] as $p) {
        if (trim($p) !== '') {
            $out .= '<p>' . nl2br(e(trim($p)), false) . '</p>';
        }
    }
    return $out;
};
$img = static fn (string $path): string => media_url($path);
/** A section's or item's button (buttonText + buttonLink), or nothing when either is empty. */
$button = static fn (array $d): string => ($d['buttonText'] ?? '') !== '' && ($d['buttonLink'] ?? '') !== ''
    ? '<a class="site-btn" href="' . e($d['buttonLink']) . '"' . (preg_match('#^https?://#i', $d['buttonLink']) ? ' target="_blank" rel="noopener"' : '') . '>' . e($d['buttonText']) . '</a>'
    : '';
/** A web address made safe inside CSS url('…'). */
$cssUrl = static fn (string $u): string => str_replace(["'", '"', '(', ')', '\\', ' '], ['%27', '%22', '%28', '%29', '%5C', '%20'], $u);
$whatsapp = preg_replace('/\D+/', '', (string) ($sp['whatsapp'] ?: $sp['phone']));
$socialLabels = ['linkedin' => 'LinkedIn', 'facebook' => 'Facebook', 'instagram' => 'Instagram', 'twitter' => 'X (Twitter)', 'telegram' => 'Telegram', 'website' => 'Website'];
$socials = array_filter(array_intersect_key((array) ($sp['socials'] ?? []), $socialLabels), fn ($u) => is_string($u) && safe_url($u, false) !== '');
$title = $set['seoTitle'] !== '' ? $set['seoTitle'] : $siteName;
$avatar = (string) ($sp['avatar'] ?? '');
$initials = mb_strtoupper(implode('', array_map(fn ($w) => mb_substr($w, 0, 1), array_slice(preg_split('/\s+/u', trim((string) $sp['name'])) ?: [], 0, 2))));
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?></title>
<?php if ($set['seoDescription'] !== ''): ?><meta name="description" content="<?= e($set['seoDescription']) ?>"><?php endif; ?>
<?php if ($sitePreview): ?><meta name="robots" content="noindex"><?php endif; ?>
<meta property="og:title" content="<?= e($title) ?>">
<?php if ($set['seoDescription'] !== ''): ?><meta property="og:description" content="<?= e($set['seoDescription']) ?>"><?php endif; ?>
<meta property="og:type" content="website">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<link rel="stylesheet" href="<?= e(asset('assets/css/site.css')) ?>">
<style>:root{--bg:<?= e($theme['bg']) ?>;--surface:<?= e($theme['surface']) ?>;--text:<?= e($theme['text']) ?>;--muted:<?= e($theme['muted']) ?>;--accent:<?= e($accent) ?>;--accent-text:<?= e($accentText) ?>;--font:<?= $font ?>}</style>
</head>
<body class="site" data-site="<?= e($siteOwner) ?>" data-api="<?= e(base_path() . '/api.php') ?>"<?= $sitePreview ? ' data-preview="1"' : '' ?>>
<?php if ($sitePreview): ?><div class="site-preview-bar">Preview of your draft<?= site_is_live($siteOwner) ? '' : ' · not published yet' ?></div><?php endif; ?>
<header class="site-header">
    <div class="site-wrap site-header-row">
        <a class="site-brand" href="#top"><?= e($siteName) ?></a>
        <?php if ($set['menu'] && $menu): ?>
            <button type="button" class="site-menu-toggle" aria-expanded="false" aria-controls="site-menu" data-site-menu-toggle>Menu</button>
            <nav id="site-menu" class="site-menu" aria-label="Sections">
                <?php foreach ($menu as $id => $label): ?><a href="#<?= e($id) ?>"><?= e($label) ?></a><?php endforeach; ?>
            </nav>
        <?php endif; ?>
        <?php if ($set['cardButton']): ?><a class="site-btn site-btn-small" href="<?= e($cardUrl()) ?>">Digital card</a><?php endif; ?>
    </div>
</header>
<main id="top">
<?php if ($siteOrder): $so = $siteOrder['order']; $soState = $siteOrder['state']; ?>
    <div id="site-order" class="site-order site-order-<?= e($soState) ?>" role="<?= $soState === 'failed' ? 'alert' : 'status' ?>" data-site-order="<?= e($soState) ?>"<?= $soState === 'pending' ? ' data-site-wait="' . e($siteOrder['rrn']) . '"' : '' ?>>
        <div class="site-wrap">
            <?php if ($soState === 'paid'): ?>
                <strong>Thank you, <?= e(explode(' ', $so['buyerName'])[0]) ?>! Your payment is confirmed.</strong>
                <p><?= e($so['product']) ?> × <?= (int) $so['quantity'] ?> · <?= peso($so['amount']) ?> · order <?= e($so['id']) ?></p>
                <p><?= e($firstName) ?> will contact you at <?= e($so['buyerPhone']) ?> or <?= e($so['buyerEmail']) ?> about delivery.</p>
            <?php elseif ($soState === 'pending'): ?>
                <strong>Waiting for Maya to confirm your payment (order <?= e($so['id']) ?>)</strong>
                <p>This page updates by itself.</p>
            <?php else: ?>
                <strong>Your order for <?= e($so['product']) ?> wasn't paid</strong>
                <p><?= e($siteOrder['message']) ?><?= $shopSection !== '' ? ' <a href="#' . e($shopSection) . '">You can try again in the shop.</a>' : '' ?></p>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
<?php foreach ($visible as $s): $d = $s['data']; ?>
    <?php if ($s['type'] === 'hero'): ?>
        <section id="<?= e($s['id']) ?>" class="site-hero site-hero-<?= e($d['align']) ?><?= $d['image'] !== '' ? ' site-hero-image' : '' ?>"<?= $d['image'] !== '' ? ' style="background-image:url(\'' . e($cssUrl($img($d['image']))) . '\')"' : '' ?>>
            <div class="site-wrap">
                <?php if ($d['heading'] !== ''): ?><h1><?= e($d['heading']) ?></h1><?php endif; ?>
                <?php if ($d['subheading'] !== ''): ?><p class="site-lead"><?= nl2br(e($d['subheading']), false) ?></p><?php endif; ?>
                <?php if ($d['buttonText'] !== '' && $d['buttonLink'] !== ''): ?><a class="site-btn" href="<?= e($d['buttonLink']) ?>"><?= e($d['buttonText']) ?></a><?php endif; ?>
            </div>
        </section>
    <?php elseif ($s['type'] === 'about'): ?>
        <section id="<?= e($s['id']) ?>" class="site-section">
            <div class="site-wrap site-split<?= $d['image'] !== '' ? '' : ' site-split-single' ?>">
                <?php if ($d['image'] !== ''): ?><img class="site-photo" src="<?= e($img($d['image'])) ?>" alt="<?= e($d['title'] ?: $siteName) ?>" loading="lazy"><?php endif; ?>
                <div><?php if ($d['title'] !== ''): ?><h2><?= e($d['title']) ?></h2><?php endif; ?><?= $paras($d['text']) ?></div>
            </div>
        </section>
    <?php elseif ($s['type'] === 'services'): ?>
        <section id="<?= e($s['id']) ?>" class="site-section site-alt">
            <div class="site-wrap">
                <?php if ($d['title'] !== ''): ?><h2><?= e($d['title']) ?></h2><?php endif; ?>
                <?php if ($d['intro'] !== ''): ?><p class="site-lead"><?= nl2br(e($d['intro']), false) ?></p><?php endif; ?>
                <div class="site-grid">
                    <?php foreach ($d['items'] as $it): ?><div class="site-card"><?php if ($it['title'] !== ''): ?><h3><?= e($it['title']) ?></h3><?php endif; ?><?= $paras($it['text']) ?></div><?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php elseif ($s['type'] === 'shop'): ?>
        <section id="<?= e($s['id']) ?>" class="site-section site-alt">
            <div class="site-wrap">
                <?php if ($d['title'] !== ''): ?><h2><?= e($d['title']) ?></h2><?php endif; ?>
                <?php if ($d['intro'] !== ''): ?><p class="site-lead"><?= nl2br(e($d['intro']), false) ?></p><?php endif; ?>
                <?php if ($shopProducts): ?>
                    <div class="site-shop">
                        <?php foreach ($shopProducts as $p): $soldOut = $p['stock'] === 0; ?>
                            <article class="site-card site-product">
                                <img src="<?= e($p['image']) ?>" alt="<?= e($p['name']) ?>" loading="lazy">
                                <div class="site-product-body">
                                    <h3><?= e($p['name']) ?></h3>
                                    <span class="site-price"><?= peso($p['price']) ?></span>
                                    <?php if ($p['description'] !== ''): ?><p class="site-muted"><?= nl2br(e($p['description']), false) ?></p><?php endif; ?>
                                    <?php if ($p['stock'] !== null && $p['stock'] > 0 && $p['stock'] <= 5): ?><span class="site-stock">Only <?= (int) $p['stock'] ?> left</span><?php endif; ?>
                                    <?php if ($shopMaya): ?>
                                        <button type="button" class="site-btn" data-site-buy="<?= e($p['id']) ?>" data-name="<?= e($p['name']) ?>" data-price="<?= e((string) $p['price']) ?>" data-image="<?= e($p['image']) ?>" data-stock="<?= $p['stock'] === null ? '' : (int) $p['stock'] ?>"<?= $soldOut ? ' disabled' : '' ?>><?= $soldOut ? 'Sold out' : 'Buy now' ?></button>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                    <?php if (!$shopMaya): ?><p class="site-muted site-shop-note"><?= e(MAYA_UNAVAILABLE) ?>. Contact <?= e($firstName) ?> to order.</p><?php endif; ?>
                <?php else: ?>
                    <p class="site-muted site-shop-note">Preview: no products yet. Add them in Backoffice → My Products (the ones shown on your card appear here). Visitors don't see this section until then.</p>
                <?php endif; ?>
            </div>
        </section>
    <?php elseif ($s['type'] === 'gallery'): ?>
        <section id="<?= e($s['id']) ?>" class="site-section">
            <div class="site-wrap">
                <?php if ($d['title'] !== ''): ?><h2><?= e($d['title']) ?></h2><?php endif; ?>
                <div class="site-gallery">
                    <?php foreach ($d['images'] as $it): if ($it['image'] === '') { continue; } ?>
                        <figure><img src="<?= e($img($it['image'])) ?>" alt="<?= e($it['caption']) ?>" loading="lazy"><?php if ($it['caption'] !== ''): ?><figcaption><?= e($it['caption']) ?></figcaption><?php endif; ?></figure>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php elseif ($s['type'] === 'testimonials'): ?>
        <section id="<?= e($s['id']) ?>" class="site-section site-alt">
            <div class="site-wrap">
                <?php if ($d['title'] !== ''): ?><h2><?= e($d['title']) ?></h2><?php endif; ?>
                <div class="site-grid">
                    <?php foreach ($d['items'] as $it): ?>
                        <figure class="site-card site-quote"><blockquote><?= nl2br(e($it['quote']), false) ?></blockquote><?php if ($it['name'] !== ''): ?><figcaption><strong><?= e($it['name']) ?></strong><?= $it['role'] !== '' ? ' · ' . e($it['role']) : '' ?></figcaption><?php endif; ?></figure>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php elseif ($s['type'] === 'video'): $embed = site_video_embed($d['url']); ?>
        <section id="<?= e($s['id']) ?>" class="site-section">
            <div class="site-wrap">
                <?php if ($d['title'] !== ''): ?><h2><?= e($d['title']) ?></h2><?php endif; ?>
                <?php if ($embed): ?><div class="site-video"><iframe src="<?= e($embed) ?>" title="<?= e($d['title'] ?: 'Video') ?>" loading="lazy" allow="accelerometer; encrypted-media; gyroscope; picture-in-picture; fullscreen" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe></div><?php endif; ?>
                <?php if ($d['caption'] !== ''): ?><p class="site-muted"><?= e($d['caption']) ?></p><?php endif; ?>
            </div>
        </section>
    <?php elseif ($s['type'] === 'text'): ?>
        <section id="<?= e($s['id']) ?>" class="site-section">
            <div class="site-wrap site-narrow"><?php if ($d['title'] !== ''): ?><h2><?= e($d['title']) ?></h2><?php endif; ?><?= $paras($d['text']) ?></div>
        </section>
    <?php elseif ($s['type'] === 'cta'): ?>
        <section id="<?= e($s['id']) ?>" class="site-section site-cta">
            <div class="site-wrap">
                <?php if ($d['heading'] !== ''): ?><h2><?= e($d['heading']) ?></h2><?php endif; ?>
                <?php if ($d['text'] !== ''): ?><p class="site-lead"><?= nl2br(e($d['text']), false) ?></p><?php endif; ?>
                <?php if ($d['buttonText'] !== '' && $d['buttonLink'] !== ''): ?><a class="site-btn" href="<?= e($d['buttonLink']) ?>"><?= e($d['buttonText']) ?></a><?php endif; ?>
            </div>
        </section>
    <?php elseif ($s['type'] === 'faq'): ?>
        <section id="<?= e($s['id']) ?>" class="site-section">
            <div class="site-wrap site-narrow">
                <?php if ($d['title'] !== ''): ?><h2><?= e($d['title']) ?></h2><?php endif; ?>
                <?php foreach ($d['items'] as $it): ?><details class="site-faq"><summary><?= e($it['q']) ?></summary><?= $paras($it['a']) ?></details><?php endforeach; ?>
            </div>
        </section>
    <?php elseif ($s['type'] === 'contact'): ?>
        <section id="<?= e($s['id']) ?>" class="site-section site-alt">
            <div class="site-wrap site-split<?= $d['form'] ? '' : ' site-split-single' ?>">
                <div>
                    <?php if ($d['title'] !== ''): ?><h2><?= e($d['title']) ?></h2><?php endif; ?>
                    <?= $paras($d['text']) ?>
                    <ul class="site-contact-list">
                        <?php if ($d['showEmail'] && valid_email((string) $sp['email'])): ?><li><a href="mailto:<?= e($sp['email']) ?>"><?= e($sp['email']) ?></a></li><?php endif; ?>
                        <?php if ($d['showPhone'] && trim((string) $sp['phone']) !== ''): ?><li><a href="tel:<?= e(preg_replace('/[^\d+]/', '', (string) $sp['phone'])) ?>"><?= e($sp['phone']) ?></a></li><?php endif; ?>
                        <?php if ($d['showWhatsapp'] && strlen($whatsapp) >= 7): ?><li><a href="https://wa.me/<?= e($whatsapp) ?>" rel="noopener" target="_blank">WhatsApp</a></li><?php endif; ?>
                    </ul>
                </div>
                <?php if ($d['form']): ?>
                    <form class="site-form" data-site-contact novalidate>
                        <label>Name<input name="name" required maxlength="150" autocomplete="name"></label>
                        <label>Email<input name="email" type="email" maxlength="190" autocomplete="email"></label>
                        <label>Phone<input name="phone" maxlength="60" autocomplete="tel"></label>
                        <label>Message<textarea name="message" required maxlength="2000" rows="4"></textarea></label>
                        <label class="site-hp" aria-hidden="true">Leave this empty<input name="company_website" tabindex="-1" autocomplete="off"></label>
                        <button type="submit" class="site-btn">Send message</button>
                        <p class="site-form-status" role="status" data-site-status><?= $sitePreview ? 'Preview: messages are sent once your website is published.' : '' ?></p>
                    </form>
                <?php endif; ?>
            </div>
        </section>
    <?php elseif ($s['type'] === 'card'): ?>
        <section id="<?= e($s['id']) ?>" class="site-section">
            <div class="site-wrap site-narrow site-center">
                <?php if ($d['title'] !== ''): ?><h2><?= e($d['title']) ?></h2><?php endif; ?>
                <div class="site-card site-profile">
                    <?php if ($avatar !== ''): ?><img class="site-avatar" src="<?= e(media_url($avatar)) ?>" alt="<?= e($sp['name']) ?>" loading="lazy"><?php else: ?><span class="site-avatar site-initials"><?= e($initials) ?></span><?php endif; ?>
                    <strong><?= e($sp['name']) ?></strong>
                    <span class="site-muted"><?= e(trim(implode(' · ', array_filter([(string) $sp['title'], (string) $sp['company']])))) ?></span>
                    <?= $paras($d['text']) ?>
                    <div class="site-actions"><a class="site-btn" href="<?= e($cardUrl($s['id'])) ?>">Open my digital card</a><a class="site-btn site-btn-ghost" href="<?= e($vcardUrl) ?>">Save my contact</a></div>
                </div>
            </div>
        </section>
    <?php elseif ($s['type'] === 'social'): ?>
        <section id="<?= e($s['id']) ?>" class="site-section">
            <div class="site-wrap site-center">
                <?php if ($d['title'] !== ''): ?><h2><?= e($d['title']) ?></h2><?php endif; ?>
                <div class="site-actions">
                    <?php foreach ($socials as $key => $u): ?><a class="site-btn site-btn-ghost" href="<?= e($u) ?>" rel="noopener" target="_blank"><?= e($socialLabels[$key]) ?></a><?php endforeach; ?>
                    <?php if (!$socials): ?><p class="site-muted">Add your social links in Digital Card CMS.</p><?php endif; ?>
                </div>
            </div>
        </section>
    <?php elseif ($s['type'] === 'imagetext'): ?>
        <section id="<?= e($s['id']) ?>" class="site-section">
            <div class="site-wrap site-split<?= $d['image'] === '' ? ' site-split-single' : ($d['imageSide'] === 'right' ? ' site-split-right' : '') ?>">
                <?php if ($d['image'] !== ''): ?><img class="site-photo site-photo-wide" src="<?= e($img($d['image'])) ?>" alt="<?= e($d['title'] ?: $siteName) ?>" loading="lazy"><?php endif; ?>
                <div>
                    <?php if ($d['title'] !== ''): ?><h2><?= e($d['title']) ?></h2><?php endif; ?>
                    <?= $paras($d['text']) ?>
                    <?= $button($d) ?>
                </div>
            </div>
        </section>
    <?php elseif ($s['type'] === 'banner'): ?>
        <section id="<?= e($s['id']) ?>" class="site-banner site-banner-<?= e($d['height']) ?> site-hero-<?= e($d['align']) ?><?= $d['image'] !== '' ? ' site-hero-image' : '' ?>"<?= $d['image'] !== '' ? ' style="background-image:url(\'' . e($cssUrl($img($d['image']))) . '\')"' : '' ?>>
            <div class="site-wrap">
                <?php if ($d['heading'] !== ''): ?><h2><?= e($d['heading']) ?></h2><?php endif; ?>
                <?php if ($d['text'] !== ''): ?><p class="site-lead"><?= nl2br(e($d['text']), false) ?></p><?php endif; ?>
                <?= $button($d) ?>
            </div>
        </section>
    <?php elseif ($s['type'] === 'pricing'): ?>
        <section id="<?= e($s['id']) ?>" class="site-section site-alt">
            <div class="site-wrap">
                <?php if ($d['title'] !== ''): ?><h2><?= e($d['title']) ?></h2><?php endif; ?>
                <?php if ($d['intro'] !== ''): ?><p class="site-lead"><?= nl2br(e($d['intro']), false) ?></p><?php endif; ?>
                <div class="site-pricing">
                    <?php foreach ($d['items'] as $it): $features = array_values(array_filter(array_map('trim', explode("\n", $it['features'])), fn ($l) => $l !== '')); ?>
                        <div class="site-card site-plan<?= $it['featured'] ? ' is-featured' : '' ?>">
                            <?php if ($it['badge'] !== ''): ?><span class="site-badge"><?= e($it['badge']) ?></span><?php endif; ?>
                            <?php if ($it['name'] !== ''): ?><h3><?= e($it['name']) ?></h3><?php endif; ?>
                            <?php if ($it['price'] !== '' || $it['period'] !== ''): ?><p class="site-plan-price"><strong><?= e($it['price']) ?></strong><?php if ($it['period'] !== ''): ?> <span class="site-muted"><?= e($it['period']) ?></span><?php endif; ?></p><?php endif; ?>
                            <?php if ($features): ?><ul class="site-plan-list"><?php foreach ($features as $f): ?><li><?= e($f) ?></li><?php endforeach; ?></ul><?php endif; ?>
                            <?= $button($it) ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php elseif ($s['type'] === 'steps'): ?>
        <section id="<?= e($s['id']) ?>" class="site-section">
            <div class="site-wrap">
                <?php if ($d['title'] !== ''): ?><h2><?= e($d['title']) ?></h2><?php endif; ?>
                <?php if ($d['intro'] !== ''): ?><p class="site-lead"><?= nl2br(e($d['intro']), false) ?></p><?php endif; ?>
                <ol class="site-steps">
                    <?php foreach ($d['items'] as $n => $it): ?>
                        <li class="site-card"><span class="site-step-n" aria-hidden="true"><?= $n + 1 ?></span><?php if ($it['title'] !== ''): ?><h3><?= e($it['title']) ?></h3><?php endif; ?><?= $paras($it['text']) ?></li>
                    <?php endforeach; ?>
                </ol>
            </div>
        </section>
    <?php elseif ($s['type'] === 'stats'): ?>
        <section id="<?= e($s['id']) ?>" class="site-section site-alt">
            <div class="site-wrap site-center">
                <?php if ($d['title'] !== ''): ?><h2><?= e($d['title']) ?></h2><?php endif; ?>
                <div class="site-stats">
                    <?php foreach ($d['items'] as $it): ?><div class="site-stat"><strong><?= e($it['value']) ?></strong><span><?= e($it['label']) ?></span></div><?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php elseif ($s['type'] === 'team'): ?>
        <section id="<?= e($s['id']) ?>" class="site-section">
            <div class="site-wrap">
                <?php if ($d['title'] !== ''): ?><h2><?= e($d['title']) ?></h2><?php endif; ?>
                <?php if ($d['intro'] !== ''): ?><p class="site-lead"><?= nl2br(e($d['intro']), false) ?></p><?php endif; ?>
                <div class="site-grid">
                    <?php foreach ($d['items'] as $it): $tInitials = mb_strtoupper(implode('', array_map(fn ($w) => mb_substr($w, 0, 1), array_slice(preg_split('/\s+/u', trim($it['name'])) ?: [], 0, 2)))); ?>
                        <div class="site-card site-person">
                            <?php if ($it['photo'] !== ''): ?><img class="site-avatar" src="<?= e($img($it['photo'])) ?>" alt="<?= e($it['name']) ?>" loading="lazy"><?php else: ?><span class="site-avatar site-initials"><?= e($tInitials !== '' ? $tInitials : '?') ?></span><?php endif; ?>
                            <?php if ($it['name'] !== ''): ?><h3><?= e($it['name']) ?></h3><?php endif; ?>
                            <?php if ($it['role'] !== ''): ?><span class="site-muted"><?= e($it['role']) ?></span><?php endif; ?>
                            <?= $paras($it['text']) ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php elseif ($s['type'] === 'logos'): ?>
        <section id="<?= e($s['id']) ?>" class="site-section">
            <div class="site-wrap site-center">
                <?php if ($d['title'] !== ''): ?><h2><?= e($d['title']) ?></h2><?php endif; ?>
                <div class="site-logos">
                    <?php foreach ($d['items'] as $it): $inner = $it['image'] !== '' ? '<img src="' . e($img($it['image'])) . '" alt="' . e($it['name']) . '" loading="lazy">' : '<span class="site-logo-name">' . e($it['name']) . '</span>'; ?>
                        <?php if ($it['link'] !== ''): ?><a class="site-logo" href="<?= e($it['link']) ?>"<?= preg_match('#^https?://#i', $it['link']) ? ' target="_blank" rel="noopener"' : '' ?>><?= $inner ?></a><?php else: ?><span class="site-logo"><?= $inner ?></span><?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php elseif ($s['type'] === 'location'): ?>
        <section id="<?= e($s['id']) ?>" class="site-section site-alt">
            <div class="site-wrap site-split site-split-even<?= $d['hours'] ? '' : ' site-split-single' ?>">
                <div>
                    <?php if ($d['title'] !== ''): ?><h2><?= e($d['title']) ?></h2><?php endif; ?>
                    <?php if ($d['address'] !== ''): ?><address class="site-address"><?= nl2br(e($d['address']), false) ?></address><?php endif; ?>
                    <?php if ($d['mapLink'] !== ''): ?><a class="site-btn" href="<?= e($d['mapLink']) ?>"<?= preg_match('#^https?://#i', $d['mapLink']) ? ' target="_blank" rel="noopener"' : '' ?>>Get directions</a><?php endif; ?>
                    <?php if ($d['note'] !== ''): ?><div class="site-muted site-location-note"><?= $paras($d['note']) ?></div><?php endif; ?>
                </div>
                <?php if ($d['hours']): ?>
                    <div class="site-card">
                        <h3>Opening hours</h3>
                        <dl class="site-hours"><?php foreach ($d['hours'] as $it): ?><dt><?= e($it['day']) ?></dt><dd><?= e($it['time']) ?></dd><?php endforeach; ?></dl>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    <?php elseif ($s['type'] === 'links'): ?>
        <section id="<?= e($s['id']) ?>" class="site-section">
            <div class="site-wrap site-narrow site-center">
                <?php if ($d['title'] !== ''): ?><h2><?= e($d['title']) ?></h2><?php endif; ?>
                <?php if ($d['intro'] !== ''): ?><p class="site-lead"><?= nl2br(e($d['intro']), false) ?></p><?php endif; ?>
                <div class="site-links">
                    <?php foreach ($d['items'] as $it): ?>
                        <?php if ($it['link'] !== ''): ?><a class="site-btn site-link-btn" href="<?= e($it['link']) ?>"<?= preg_match('#^https?://#i', $it['link']) ? ' target="_blank" rel="noopener"' : '' ?>><?= e($it['label'] !== '' ? $it['label'] : $it['link']) ?></a><?php else: ?><span class="site-btn site-link-btn is-empty"><?= e($it['label']) ?></span><?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>
<?php endforeach; ?>
</main>
<footer class="site-footer">
    <div class="site-wrap"><?= e($set['footer'] !== '' ? $set['footer'] : '© ' . date('Y') . ' ' . $siteName) ?><span class="site-muted"> · <a href="<?= e($cardUrl()) ?>">Digital card</a></span></div>
</footer>
<?php if ($shopProducts && $shopMaya): ?>
<dialog class="site-dialog" id="site-buy" aria-labelledby="site-buy-title">
    <form class="site-form site-buy-form" data-site-buy-form novalidate>
        <div class="site-dialog-head">
            <h3 id="site-buy-title">Buy from <?= e($firstName) ?></h3>
            <button type="button" class="site-dialog-close" data-site-buy-close aria-label="Close">×</button>
        </div>
        <input type="hidden" name="productId" value="">
        <div class="site-buy-item">
            <img data-buy-image src="" alt="">
            <div><strong data-buy-name></strong><span class="site-muted" data-buy-price></span></div>
        </div>
        <div class="site-buy-qty">
            <label for="site-buy-qty">Quantity</label>
            <span class="site-stepper">
                <button type="button" data-buy-step="-1" aria-label="One less">−</button>
                <input id="site-buy-qty" name="quantity" type="number" min="1" value="1" inputmode="numeric">
                <button type="button" data-buy-step="1" aria-label="One more">+</button>
            </span>
        </div>
        <label>Your name<input name="name" required maxlength="150" autocomplete="name" value="<?= e($user['name'] ?? '') ?>"></label>
        <label>Email (for your receipt)<input name="email" type="email" required maxlength="190" autocomplete="email" value="<?= e($user['email'] ?? '') ?>"></label>
        <label>Mobile<input name="phone" type="tel" required maxlength="60" autocomplete="tel" placeholder="+63 917..."></label>
        <label>Delivery address or notes<textarea name="delivery" rows="2" maxlength="500" placeholder="Where should <?= e($firstName) ?> send it?"></textarea></label>
        <label class="site-hp" aria-hidden="true">Leave this empty<input name="company_website" tabindex="-1" autocomplete="off"></label>
        <div class="site-buy-total"><span>Total</span><strong data-buy-total></strong></div>
        <p class="site-form-status<?= $sitePreview ? ' is-err' : '' ?>" role="status" data-buy-status><?= $sitePreview ? 'Preview: visitors can buy once your website is published.' : '' ?></p>
        <button type="submit" class="site-btn" data-buy-submit<?= $sitePreview ? ' disabled' : '' ?>>Pay with Maya</button>
        <p class="site-muted site-buy-note">You pay on Maya's secure page with a card or your Maya wallet, then come back here.</p>
    </form>
</dialog>
<?php endif; ?>
<script src="<?= e(asset('assets/js/site.js')) ?>"></script>
</body>
</html>
