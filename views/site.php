<?php
/**
 * A member's website (includes/website.php), printed as its own complete
 * page without the portal layout:
 *   - on their domain (index.php routes it here when it is published);
 *   - at index.php?view=site&u=usr_x (published sites only);
 *   - &preview=1: the draft, for the member themselves or an admin
 *     (the builder's preview frame).
 * Every text is escaped; links, images and videos were checked when saved.
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
    <?php endif; ?>
<?php endforeach; ?>
</main>
<footer class="site-footer">
    <div class="site-wrap"><?= e($set['footer'] !== '' ? $set['footer'] : '© ' . date('Y') . ' ' . $siteName) ?><span class="site-muted"> · <a href="<?= e($cardUrl()) ?>">Digital card</a></span></div>
</footer>
<script src="<?= e(asset('assets/js/site.js')) ?>"></script>
</body>
</html>
