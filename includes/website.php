<?php
/**
 * Members' websites. Once a member owns an active .com domain, Member
 * Backoffice > My Website (views/backoffice/website.php,
 * assets/js/views/bo-website.js) lets them build a one-page site from
 * sections, pick a theme and publish it. Visitors of their domain then see
 * the site (views/site.php) and the 1EVOAI card moves to
 * yourname.com/?view=card. Messages from the site's contact form go to the
 * member's Contacts (source "My website").
 *
 * A site is ['settings' => [...], 'sections' => [['id', 'type', 'hidden',
 * 'data' => [...]]]]. site_sanitize() keeps only known fields, limits their
 * length and allows only safe links, images and videos; views escape all
 * text when they print it.
 */
declare(strict_types=1);

const SITE_MAX_SECTIONS = 20;
/** Largest saved site (JSON bytes). */
const SITE_MAX_BYTES = 200000;
const SITE_IMAGE_MAX_BYTES = 5 * 1024 * 1024;
const SITE_IMAGE_MAX_PIXELS = 8000;
/** Images a member may keep. */
const SITE_IMAGE_QUOTA = 100;
const SITE_IMAGE_TYPES = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp', IMAGETYPE_GIF => 'gif'];
const SITE_UPLOAD_DIR = 'uploads/sites';
/** Website contact form: messages per visitor (IP) per 10 minutes, and per site per day. */
const SITE_CONTACT_PER_IP = 5;
const SITE_CONTACT_PER_SITE = 100;

const SITE_THEMES = [
    'gold' => ['label' => 'Executive Gold', 'bg' => '#0b0b0c', 'surface' => '#151517', 'text' => '#f4f4f5', 'muted' => '#a1a1aa', 'accent' => '#f3bd35', 'accentText' => '#111111'],
    'light' => ['label' => 'Clean Light', 'bg' => '#ffffff', 'surface' => '#f4f4f5', 'text' => '#18181b', 'muted' => '#52525b', 'accent' => '#2563eb', 'accentText' => '#ffffff'],
    'ocean' => ['label' => 'Ocean', 'bg' => '#0b1a2b', 'surface' => '#12263d', 'text' => '#e6f0fa', 'muted' => '#9fb3c8', 'accent' => '#2dd4bf', 'accentText' => '#06201c'],
    'rose' => ['label' => 'Rose', 'bg' => '#fff8f6', 'surface' => '#ffffff', 'text' => '#2b1a1a', 'muted' => '#7a5c5c', 'accent' => '#e11d48', 'accentText' => '#ffffff'],
    'forest' => ['label' => 'Forest', 'bg' => '#f5f9f5', 'surface' => '#ffffff', 'text' => '#132a1c', 'muted' => '#4b6354', 'accent' => '#15803d', 'accentText' => '#ffffff'],
];
const SITE_FONTS = [
    'sans' => ['label' => 'Modern', 'stack' => 'system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif'],
    'serif' => ['label' => 'Classic', 'stack' => 'Georgia, "Times New Roman", Times, serif'],
    'rounded' => ['label' => 'Friendly', 'stack' => '"Trebuchet MS", "Segoe UI", system-ui, sans-serif'],
];

/**
 * The editable fields: the site settings and each section type. A field is
 * ['type' => text|textarea|link|image|video|bool|select|color|list, 'label',
 * 'max' (characters), 'options' (select), 'fields' + 'maxItems' (list), 'help'].
 * The builder draws its forms from this too.
 */
function site_schema(): array
{
    $title = ['type' => 'text', 'label' => 'Title', 'max' => 80];
    return [
        'settings' => [
            'siteName' => ['type' => 'text', 'label' => 'Site name', 'max' => 80, 'help' => 'Shown at the top of every page.'],
            'theme' => ['type' => 'select', 'label' => 'Theme', 'options' => array_map(fn ($t) => $t['label'], SITE_THEMES)],
            'accent' => ['type' => 'color', 'label' => 'Accent colour', 'help' => 'Empty: the theme\'s own.'],
            'font' => ['type' => 'select', 'label' => 'Font', 'options' => array_map(fn ($f) => $f['label'], SITE_FONTS)],
            'menu' => ['type' => 'bool', 'label' => 'Show a menu of the sections'],
            'cardButton' => ['type' => 'bool', 'label' => 'Show a "Digital card" button at the top'],
            'footer' => ['type' => 'text', 'label' => 'Footer text', 'max' => 200],
            'seoTitle' => ['type' => 'text', 'label' => 'Search title', 'max' => 70, 'help' => 'The page title in search results and browser tabs.'],
            'seoDescription' => ['type' => 'textarea', 'label' => 'Search description', 'max' => 160],
        ],
        'sections' => [
            'hero' => ['label' => 'Hero banner', 'fields' => [
                'heading' => ['type' => 'text', 'label' => 'Heading', 'max' => 120],
                'subheading' => ['type' => 'textarea', 'label' => 'Subheading', 'max' => 300],
                'buttonText' => ['type' => 'text', 'label' => 'Button text', 'max' => 40],
                'buttonLink' => ['type' => 'link', 'label' => 'Button link', 'help' => 'A web address, #contact (a section), mailto: or tel:.'],
                'image' => ['type' => 'image', 'label' => 'Background image'],
                'align' => ['type' => 'select', 'label' => 'Alignment', 'options' => ['center' => 'Centred', 'left' => 'Left']],
            ]],
            'about' => ['label' => 'About', 'fields' => [
                'title' => $title,
                'text' => ['type' => 'textarea', 'label' => 'Text', 'max' => 2000],
                'image' => ['type' => 'image', 'label' => 'Photo'],
            ]],
            'services' => ['label' => 'Services', 'fields' => [
                'title' => $title,
                'intro' => ['type' => 'textarea', 'label' => 'Introduction', 'max' => 300],
                'items' => ['type' => 'list', 'label' => 'Services', 'maxItems' => 12, 'fields' => [
                    'title' => ['type' => 'text', 'label' => 'Name', 'max' => 80],
                    'text' => ['type' => 'textarea', 'label' => 'Description', 'max' => 300],
                ]],
            ]],
            'gallery' => ['label' => 'Gallery', 'fields' => [
                'title' => $title,
                'images' => ['type' => 'list', 'label' => 'Images', 'maxItems' => 24, 'fields' => [
                    'image' => ['type' => 'image', 'label' => 'Image'],
                    'caption' => ['type' => 'text', 'label' => 'Caption', 'max' => 120],
                ]],
            ]],
            'testimonials' => ['label' => 'Testimonials', 'fields' => [
                'title' => $title,
                'items' => ['type' => 'list', 'label' => 'Testimonials', 'maxItems' => 12, 'fields' => [
                    'quote' => ['type' => 'textarea', 'label' => 'Quote', 'max' => 400],
                    'name' => ['type' => 'text', 'label' => 'Name', 'max' => 80],
                    'role' => ['type' => 'text', 'label' => 'Role or company', 'max' => 80],
                ]],
            ]],
            'video' => ['label' => 'Video', 'fields' => [
                'title' => $title,
                'url' => ['type' => 'video', 'label' => 'YouTube or Vimeo link'],
                'caption' => ['type' => 'text', 'label' => 'Caption', 'max' => 200],
            ]],
            'text' => ['label' => 'Text', 'fields' => [
                'title' => $title,
                'text' => ['type' => 'textarea', 'label' => 'Text', 'max' => 4000],
            ]],
            'cta' => ['label' => 'Call to action', 'fields' => [
                'heading' => ['type' => 'text', 'label' => 'Heading', 'max' => 120],
                'text' => ['type' => 'textarea', 'label' => 'Text', 'max' => 300],
                'buttonText' => ['type' => 'text', 'label' => 'Button text', 'max' => 40],
                'buttonLink' => ['type' => 'link', 'label' => 'Button link'],
            ]],
            'faq' => ['label' => 'Questions & answers', 'fields' => [
                'title' => $title,
                'items' => ['type' => 'list', 'label' => 'Questions', 'maxItems' => 20, 'fields' => [
                    'q' => ['type' => 'text', 'label' => 'Question', 'max' => 200],
                    'a' => ['type' => 'textarea', 'label' => 'Answer', 'max' => 1000],
                ]],
            ]],
            'contact' => ['label' => 'Contact', 'fields' => [
                'title' => $title,
                'text' => ['type' => 'textarea', 'label' => 'Text', 'max' => 300],
                'form' => ['type' => 'bool', 'label' => 'Contact form (messages go to your Contacts)'],
                'showEmail' => ['type' => 'bool', 'label' => 'Show my email'],
                'showPhone' => ['type' => 'bool', 'label' => 'Show my phone number'],
                'showWhatsapp' => ['type' => 'bool', 'label' => 'Show my WhatsApp'],
            ]],
            'card' => ['label' => 'Digital card', 'fields' => [
                'title' => $title,
                'text' => ['type' => 'textarea', 'label' => 'Text', 'max' => 300],
            ]],
            'social' => ['label' => 'Social links', 'fields' => [
                'title' => $title,
            ]],
        ],
    ];
}

/* ---------------------------------------------------------------------
 * Cleaning what the builder sends
 * ------------------------------------------------------------------- */

/** One line of text: no control characters, at most $max characters. */
function site_line($v, int $max): string
{
    $v = is_scalar($v) ? (string) $v : '';
    return mb_substr(trim(preg_replace(['/[\x00-\x1F\x7F]+/u', '/ {2,}/'], ' ', $v) ?? ''), 0, $max);
}

/** Text with line breaks kept. */
function site_text($v, int $max): string
{
    $v = is_scalar($v) ? str_replace(["\r\n", "\r"], "\n", (string) $v) : '';
    return mb_substr(trim(preg_replace('/[\x00-\x09\x0B-\x1F\x7F]+/u', '', $v) ?? ''), 0, $max);
}

/** A link a visitor may follow: https?://, #section, mailto: or tel:. '' otherwise. */
function site_link($v): string
{
    $v = site_line($v, 500);
    if ($v === '') {
        return '';
    }
    if (preg_match('/^#[A-Za-z0-9_-]{1,40}$/', $v)) {
        return $v;
    }
    if (preg_match('/^mailto:(.+)$/i', $v, $m)) {
        return valid_email($m[1]) ? 'mailto:' . $m[1] : '';
    }
    if (preg_match('/^tel:([+0-9 ()-]{3,30})$/i', $v, $m)) {
        return 'tel:' . $m[1];
    }
    if (!preg_match('#^https?://#i', $v) && preg_match('/^[a-z0-9.-]+\.[a-z]{2,}(\/.*)?$/i', $v)) {
        $v = 'https://' . $v; // "example.com/page" typed without https://
    }
    return safe_url($v, false);
}

/** An uploaded image (uploads/sites/…) or an image web address. '' otherwise. */
function site_image($v): string
{
    $v = site_line($v, 500);
    if (preg_match('#^' . SITE_UPLOAD_DIR . '/[a-f0-9]{12}/[a-f0-9]{24}\.(jpg|png|webp|gif)$#', $v)) {
        return $v;
    }
    return safe_url($v, false);
}

/** The embeddable player address for a YouTube or Vimeo link, or null. */
function site_video_embed(string $url): ?string
{
    if (preg_match('#^https?://(?:www\.|m\.)?(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/)|youtu\.be/)([A-Za-z0-9_-]{11})#', $url, $m)) {
        return 'https://www.youtube-nocookie.com/embed/' . $m[1];
    }
    if (preg_match('#^https?://(?:www\.|player\.)?vimeo\.com/(?:video/)?(\d{5,12})#', $url, $m)) {
        return 'https://player.vimeo.com/video/' . $m[1];
    }
    return null;
}

/** Clean one value for its field spec. */
function site_clean_field(array $spec, $v)
{
    switch ($spec['type']) {
        case 'text':
            return site_line($v, $spec['max'] ?? 120);
        case 'textarea':
            return site_text($v, $spec['max'] ?? 1000);
        case 'link':
            return site_link($v);
        case 'image':
            return site_image($v);
        case 'video':
            $v = site_line($v, 300);
            return site_video_embed($v) !== null ? $v : '';
        case 'bool':
            return (bool) $v;
        case 'color':
            return is_string($v) && preg_match('/^#[0-9a-fA-F]{6}$/', $v) ? strtolower($v) : '';
        case 'select':
            return is_string($v) && isset($spec['options'][$v]) ? $v : (string) array_key_first($spec['options']);
        case 'list':
            $items = [];
            foreach (is_array($v) ? array_slice(array_values($v), 0, $spec['maxItems'] ?? 12) : [] as $item) {
                $clean = site_clean_fields($spec['fields'], is_array($item) ? $item : []);
                if (array_filter($clean, fn ($x) => $x !== '' && $x !== false) !== []) {
                    $items[] = $clean;
                }
            }
            return $items;
    }
    return '';
}

function site_clean_fields(array $specs, array $in): array
{
    $out = [];
    foreach ($specs as $key => $spec) {
        $out[$key] = site_clean_field($spec, $in[$key] ?? null);
    }
    return $out;
}

/** A whole site, cleaned: unknown sections and fields dropped, at most 20 sections. */
function site_sanitize(array $site): array
{
    $schema = site_schema();
    $sections = [];
    $ids = [];
    foreach (is_array($site['sections'] ?? null) ? array_values($site['sections']) : [] as $s) {
        if (!is_array($s) || !isset($schema['sections'][$s['type'] ?? ''])) {
            continue;
        }
        $id = is_string($s['id'] ?? null) && preg_match('/^[a-z][a-z0-9-]{1,30}$/', $s['id']) && !isset($ids[$s['id']]) ? $s['id'] : site_new_id($s['type']);
        $ids[$id] = true;
        $sections[] = ['id' => $id, 'type' => $s['type'], 'hidden' => !empty($s['hidden']),
            'data' => site_clean_fields($schema['sections'][$s['type']]['fields'], is_array($s['data'] ?? null) ? $s['data'] : [])];
        if (count($sections) >= SITE_MAX_SECTIONS) {
            break;
        }
    }
    return ['settings' => site_clean_fields($schema['settings'], is_array($site['settings'] ?? null) ? $site['settings'] : []), 'sections' => $sections];
}

/** A section id visitors see in links (#about-3f2a). */
function site_new_id(string $type): string
{
    return $type . '-' . bin2hex(random_bytes(2));
}

/* ---------------------------------------------------------------------
 * The member's site
 * ------------------------------------------------------------------- */

/** Whether the member owns an active domain (the builder is unlocked). */
function site_unlocked(string $userId): bool
{
    return (bool) db_value('SELECT 1 FROM user_domains WHERE user_id = ? AND status = "active" LIMIT 1', [$userId]);
}

/** A first draft from the member's card profile. */
function site_template(string $userId): array
{
    $p = get_member_profile($userId);
    $name = trim((string) $p['name']) ?: 'My website';
    $role = trim(implode(' · ', array_filter([trim((string) $p['title']), trim((string) $p['company'])])));
    $sections = [
        ['type' => 'hero', 'data' => ['heading' => $name, 'subheading' => trim((string) ($p['headline'] ?? '')) ?: $role, 'buttonText' => 'Get in touch', 'buttonLink' => '#contact', 'align' => 'center']],
        ['type' => 'about', 'data' => ['title' => 'About me', 'text' => trim((string) $p['bio']) ?: 'Tell visitors who you are, what you do and how you can help them.', 'image' => (string) ($p['avatar'] ?? '')]],
    ];
    $services = array_values(array_filter(array_map(fn ($s) => trim((string) $s), (array) ($p['services'] ?? []))));
    if ($services) {
        $sections[] = ['type' => 'services', 'data' => ['title' => 'What I do', 'items' => array_map(fn ($s) => ['title' => $s, 'text' => ''], array_slice($services, 0, 12))]];
    }
    $sections[] = ['type' => 'card', 'data' => ['title' => 'My digital card', 'text' => 'Save my contact details or open my 1EVOAI digital card.']];
    if (array_filter((array) ($p['socials'] ?? []))) {
        $sections[] = ['type' => 'social', 'data' => ['title' => 'Follow me']];
    }
    $sections[] = ['type' => 'contact', 'data' => ['title' => 'Contact me', 'text' => 'Send me a message and I will get back to you.', 'form' => true, 'showEmail' => true, 'showPhone' => false, 'showWhatsapp' => false]];
    $site = [
        'settings' => ['siteName' => $name, 'theme' => 'gold', 'accent' => '', 'font' => 'sans', 'menu' => true, 'cardButton' => true,
            'footer' => '', 'seoTitle' => mb_substr($name . ($role !== '' ? ' — ' . $role : ''), 0, 70), 'seoDescription' => mb_substr(trim((string) $p['bio']), 0, 160)],
        'sections' => array_map(fn ($s) => $s + ['id' => $s['type'] === 'contact' ? 'contact' : site_new_id($s['type']), 'hidden' => false], $sections),
    ];
    return site_sanitize($site);
}

function site_row(string $userId): ?array
{
    return db_one('SELECT * FROM member_websites WHERE user_id = ?', [$userId]);
}

/** The draft being edited (the profile template the first time). */
function site_draft(string $userId): array
{
    $row = site_row($userId);
    $site = $row ? json_decode($row['draft'], true) : null;
    return is_array($site) ? site_sanitize($site) : site_template($userId);
}

/** The site visitors see, or null: published, not suspended, and the member owns an active domain. */
function site_public(string $userId): ?array
{
    $row = db_one('SELECT published, status FROM member_websites WHERE user_id = ?', [$userId]);
    if (!$row || $row['status'] !== 'published' || $row['published'] === null || !site_unlocked($userId)) {
        return null;
    }
    $site = json_decode($row['published'], true);
    return is_array($site) ? $site : null;
}

/** Whether visitors of the member's domain see their website (and the card moves to ?view=card). */
function site_is_live(string $userId): bool
{
    return $userId !== '' && (bool) db_value('SELECT 1 FROM member_websites WHERE user_id = ? AND status = "published" AND published IS NOT NULL', [$userId]) && site_unlocked($userId);
}

/** The site's address on the member's domain (the card's domain first), or null without one. */
function site_live_url(string $userId): ?string
{
    $domain = domain_card_domain($userId)
        ?? db_value('SELECT domain FROM user_domains WHERE user_id = ? AND status = "active" ORDER BY dns_ok DESC, id LIMIT 1', [$userId]);
    if ($domain === null) {
        return null;
    }
    $scheme = env('DOMAIN_URL_SCHEME', 'http') === 'https' ? 'https' : 'http';
    return $scheme . '://' . $domain . base_path() . '/';
}

/** The site on this portal (works before the domain's DNS does). */
function site_portal_url(string $userId): string
{
    return base_url() . '/index.php?' . http_build_query(['view' => 'site', 'u' => $userId]);
}

/**
 * The way back from a member's digital card to their website: the published
 * site (the domain's front page when the card is opened there), or the draft
 * when the member or an admin came from the builder's preview. The site's card
 * links add ?from=site|preview, and ?back=<section id> returns the visitor to
 * the section they left. Null when there is no website to go back to.
 *
 * @return array{url: string, label: string, name: string, back: bool}|null
 */
function site_card_back(string $userId, ?array $viewer): ?array
{
    $from = $_GET['from'] ?? '';
    $anchor = is_string($_GET['back'] ?? null) && preg_match('/^[a-z][a-z0-9-]{1,30}$/', $_GET['back']) ? '#' . $_GET['back'] : '';
    if ($from === 'preview' && $viewer && ($viewer['id'] === $userId || is_admin($viewer)) && site_unlocked($userId)) {
        $site = site_draft($userId);
        $url = url('site', ['u' => $userId, 'preview' => 1]);
    } elseif ($userId !== '' && ($site = site_public($userId)) !== null) {
        $url = domain_owner_for_host((string) ($_SERVER['HTTP_HOST'] ?? '')) === $userId ? base_path() . '/' : url('site', ['u' => $userId]);
    } else {
        return null;
    }
    $back = $from === 'site' || $from === 'preview';
    return ['url' => $url . $anchor, 'label' => $back ? 'Back to website' : 'Visit my website', 'name' => (string) ($site['settings']['siteName'] ?? ''), 'back' => $back];
}

function site_require_unlocked(array $user): void
{
    if (!site_unlocked($user['id'])) {
        throw new InvalidArgumentException('Buy a .com domain first: the website builder comes with it.');
    }
}

/** Save the draft. Returns the cleaned site. */
function site_save(array $user, array $site): array
{
    site_require_unlocked($user);
    $clean = site_sanitize($site);
    $json = json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (strlen((string) $json) > SITE_MAX_BYTES) {
        throw new InvalidArgumentException('Your website is too big to save. Remove some sections or text.');
    }
    db_run('INSERT INTO member_websites (user_id, draft) VALUES (?, ?) ON DUPLICATE KEY UPDATE draft = VALUES(draft)', [$user['id'], $json]);
    return $clean;
}

/** Publish the draft ($site: save it first). */
function site_publish(array $user, ?array $site = null): array
{
    site_require_unlocked($user);
    $row = site_row($user['id']);
    if ($row && $row['status'] === 'suspended') {
        throw new InvalidArgumentException('An administrator has taken your website offline' . ($row['admin_note'] !== '' ? ': ' . $row['admin_note'] : '.') . ' Contact the 88MMM administrator.');
    }
    $clean = $site !== null ? site_save($user, $site) : site_draft($user['id']);
    if (!array_filter($clean['sections'], fn ($s) => !$s['hidden'])) {
        throw new InvalidArgumentException('Add at least one visible section before publishing.');
    }
    $json = json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    db_run('INSERT INTO member_websites (user_id, draft, published, published_at, status) VALUES (?, ?, ?, NOW(), "published")
            ON DUPLICATE KEY UPDATE draft = VALUES(draft), published = VALUES(published), published_at = NOW(), status = "published"', [$user['id'], $json, $json]);
    add_audit('Website published', $user['id']);
    return $clean;
}

function site_unpublish(array $user): void
{
    db_run('UPDATE member_websites SET published = NULL, status = IF(status = "suspended", "suspended", "draft") WHERE user_id = ?', [$user['id']]);
    add_audit('Website unpublished', $user['id']);
}

/**
 * Delete the member's website for good: the draft, the published copy, the
 * visit count and every uploaded image (rows and files). Messages from the
 * contact form stay in their Contacts. A site an administrator took offline
 * stays taken offline (with an empty draft), so deleting can't undo that.
 * Works without a domain too, so a member whose domain lapsed can clear it.
 * Returns the number of images deleted.
 */
function site_delete(array $user): int
{
    $paths = array_column(db_all('SELECT path FROM member_website_images WHERE user_id = ?', [$user['id']]), 'path');
    db_tx(function () use ($user): void {
        db_run('DELETE FROM member_website_images WHERE user_id = ?', [$user['id']]);
        if ((site_row($user['id'])['status'] ?? '') === 'suspended') {
            db_run('UPDATE member_websites SET draft = "", published = NULL, published_at = NULL, views = 0 WHERE user_id = ?', [$user['id']]);
        } else {
            db_run('DELETE FROM member_websites WHERE user_id = ?', [$user['id']]);
        }
    });
    $dirs = [];
    foreach ($paths as $path) {
        // Only files the uploader made: uploads/sites/<12 hex>/<24 hex>.<ext>
        if (preg_match('#^' . preg_quote(SITE_UPLOAD_DIR, '#') . '/[0-9a-f]{12}/[0-9a-f]{24}\.(' . implode('|', SITE_IMAGE_TYPES) . ')$#', $path) && is_file(APP_ROOT . '/' . $path)) {
            @unlink(APP_ROOT . '/' . $path);
            $dirs[dirname(APP_ROOT . '/' . $path)] = true;
        }
    }
    foreach (array_keys($dirs) as $dir) {
        if (count(scandir($dir) ?: []) === 2) {
            @rmdir($dir);
        }
    }
    add_audit('Website deleted', $user['id']);
    return count($paths);
}

/** Start again from the profile template (the published site stays until the next Publish). */
function site_reset(array $user): array
{
    site_require_unlocked($user);
    return site_save($user, site_template($user['id']));
}

/** Admin: take a member's site offline (or put it back with $suspend = false). */
function site_suspend(array $admin, string $userId, bool $suspend, string $note): void
{
    $row = site_row($userId);
    if (!$row) {
        throw new InvalidArgumentException('That member has no website.');
    }
    if ($suspend) {
        if (mb_strlen(trim($note)) < 3) {
            throw new InvalidArgumentException('Give the reason; the member sees it.');
        }
        db_run('UPDATE member_websites SET status = "suspended", admin_note = ? WHERE user_id = ?', [mb_substr(trim($note), 0, 255), $userId]);
        domain_notify($userId, 'Website taken offline', 'An administrator took your website offline: ' . trim($note));
    } else {
        db_run('UPDATE member_websites SET status = IF(published IS NULL, "draft", "published"), admin_note = "" WHERE user_id = ?', [$userId]);
        domain_notify($userId, 'Website back online', 'An administrator restored your website.');
    }
    add_audit('Admin ' . ($suspend ? 'suspended' : 'restored') . ' the website of ' . $userId . ($suspend ? ': ' . trim($note) : ''), $admin['id']);
}

/** Everything the builder needs (views/backoffice/website.php). */
function site_builder_state(array $user): array
{
    $row = site_row($user['id']);
    return [
        'unlocked' => site_unlocked($user['id']),
        'site' => site_draft($user['id']),
        'schema' => site_schema(),
        'themes' => SITE_THEMES,
        'status' => $row['status'] ?? 'draft',
        'published' => $row !== null && $row['published'] !== null,
        'publishedAt' => $row && $row['published_at'] ? to_ms($row['published_at']) : null,
        'changed' => $row !== null && $row['published'] !== null && $row['published'] !== json_encode(site_draft($user['id']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'adminNote' => $row['admin_note'] ?? '',
        'views' => (int) ($row['views'] ?? 0),
        'liveUrl' => site_live_url($user['id']),
        'portalUrl' => site_portal_url($user['id']),
        'previewUrl' => url('site', ['u' => $user['id'], 'preview' => 1]),
        'images' => array_map(fn ($r) => ['path' => $r['path'], 'url' => media_url($r['path'])], db_all('SELECT path FROM member_website_images WHERE user_id = ? ORDER BY id DESC LIMIT 60', [$user['id']])),
    ];
}

/* ---------------------------------------------------------------------
 * Images
 * ------------------------------------------------------------------- */

/** Remove the GPS position (and XMP, IPTC, comments) from a JPEG; orientation is kept. */
function site_jpeg_strip(string $bytes): string
{
    if (strncmp($bytes, "\xFF\xD8", 2) !== 0) {
        return $bytes;
    }
    $out = "\xFF\xD8";
    $n = strlen($bytes);
    $i = 2;
    while ($i + 4 <= $n) {
        if ($bytes[$i] !== "\xFF") {
            return $out . substr($bytes, $i); // Unusual layout: keep the rest as it is.
        }
        $marker = ord($bytes[$i + 1]);
        if ($marker === 0xFF) {
            $i++; // fill byte
            continue;
        }
        if ($marker === 0xDA || $marker === 0xD9) {
            return $out . substr($bytes, $i); // Start of the image data.
        }
        $len = unpack('n', substr($bytes, $i + 2, 2))[1];
        if ($len < 2 || $i + 2 + $len > $n) {
            return $out . substr($bytes, $i);
        }
        $seg = substr($bytes, $i, 2 + $len);
        if ($marker === 0xE1 && strncmp(substr($seg, 4, 6), "Exif\0\0", 6) === 0) {
            $out .= site_exif_drop_gps($seg);
        } elseif (!in_array($marker, [0xE1, 0xED, 0xFE], true)) { // other APP1 (XMP), APP13 (IPTC), COM: dropped
            $out .= $seg;
        }
        $i += 2 + $len;
    }
    return $bytes;
}

/** Empty the GPS directory of an EXIF (APP1) segment. Unreadable EXIF is dropped whole. */
function site_exif_drop_gps(string $seg): string
{
    $t = 10; // TIFF header inside the segment
    $le = substr($seg, $t, 2) === 'II';
    if (!$le && substr($seg, $t, 2) !== 'MM') {
        return '';
    }
    $u16 = fn (int $o) => $o + 2 <= strlen($seg) ? unpack($le ? 'v' : 'n', substr($seg, $o, 2))[1] : null;
    $u32 = fn (int $o) => $o + 4 <= strlen($seg) ? unpack($le ? 'V' : 'N', substr($seg, $o, 4))[1] : null;
    $ifd0 = $u32($t + 4);
    $count = $ifd0 !== null ? $u16($t + $ifd0) : null;
    if ($count === null) {
        return '';
    }
    $sizes = [1 => 1, 2 => 1, 3 => 2, 4 => 4, 5 => 8, 6 => 1, 7 => 1, 8 => 2, 9 => 4, 10 => 8, 11 => 4, 12 => 8];
    for ($k = 0; $k < $count; $k++) {
        $e = $t + $ifd0 + 2 + 12 * $k;
        if ($u16($e) !== 0x8825) {
            continue;
        }
        $gps = $u32($e + 8);
        $n = $gps !== null ? $u16($t + $gps) : null;
        if ($n === null || $t + $gps + 2 + 12 * $n > strlen($seg)) {
            return '';
        }
        for ($j = 0; $j < $n; $j++) {
            $g = $t + $gps + 2 + 12 * $j;
            $size = ($sizes[$u16($g + 2)] ?? 1) * (int) $u32($g + 4);
            if ($size > 4) {
                $off = (int) $u32($g + 8);
                if ($t + $off + $size > strlen($seg)) {
                    return '';
                }
                $seg = substr_replace($seg, str_repeat("\0", $size), $t + $off, $size);
            }
            $seg = substr_replace($seg, str_repeat("\0", 12), $g, 12);
        }
        $seg = substr_replace($seg, "\0\0", $t + $gps, 2); // no GPS entries left
    }
    return $seg;
}

/** Remove text and EXIF chunks from a PNG. */
function site_png_strip(string $bytes): string
{
    if (strncmp($bytes, "\x89PNG\r\n\x1a\n", 8) !== 0) {
        return $bytes;
    }
    $out = substr($bytes, 0, 8);
    $i = 8;
    $n = strlen($bytes);
    while ($i + 12 <= $n) {
        $len = unpack('N', substr($bytes, $i, 4))[1];
        $type = substr($bytes, $i + 4, 4);
        if ($i + 12 + $len > $n) {
            return $bytes;
        }
        if (!in_array($type, ['eXIf', 'tEXt', 'zTXt', 'iTXt'], true)) {
            $out .= substr($bytes, $i, 12 + $len);
        }
        $i += 12 + $len;
    }
    return $out;
}

/**
 * Store an uploaded image (the $_FILES entry) for the member's website.
 * Only real JPEG, PNG, WebP and GIF images up to 5 MB are kept, under a new
 * random name; location data is removed from JPEG and PNG.
 * Returns ['path', 'url', 'width', 'height'].
 */
function site_upload_image(array $user, array $file): array
{
    site_require_unlocked($user);
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
        throw new InvalidArgumentException(($file['error'] ?? 0) === UPLOAD_ERR_INI_SIZE || ($file['error'] ?? 0) === UPLOAD_ERR_FORM_SIZE ? 'That image is too big (5 MB at most).' : 'Choose an image to upload.');
    }
    if ((int) $file['size'] > SITE_IMAGE_MAX_BYTES) {
        throw new InvalidArgumentException('That image is too big (5 MB at most).');
    }
    $info = @getimagesize($file['tmp_name']);
    if (!$info || !isset(SITE_IMAGE_TYPES[$info[2]])) {
        throw new InvalidArgumentException('Upload a JPEG, PNG, WebP or GIF image.');
    }
    if ($info[0] < 1 || $info[1] < 1 || $info[0] > SITE_IMAGE_MAX_PIXELS || $info[1] > SITE_IMAGE_MAX_PIXELS) {
        throw new InvalidArgumentException('That image is too large (' . SITE_IMAGE_MAX_PIXELS . ' pixels a side at most).');
    }
    $bytes = (string) file_get_contents($file['tmp_name']);
    if (preg_match('/<\?(php|=)|<script|<html|<svg/i', $bytes)) {
        throw new InvalidArgumentException('That file is not a plain image.');
    }
    if ((int) db_value('SELECT COUNT(*) FROM member_website_images WHERE user_id = ?', [$user['id']]) >= SITE_IMAGE_QUOTA) {
        throw new InvalidArgumentException('You have ' . SITE_IMAGE_QUOTA . ' images, the most a website may keep.');
    }
    $ext = SITE_IMAGE_TYPES[$info[2]];
    $bytes = $ext === 'jpg' ? site_jpeg_strip($bytes) : ($ext === 'png' ? site_png_strip($bytes) : $bytes);
    $dir = SITE_UPLOAD_DIR . '/' . substr(hash('sha256', $user['id'] . '|' . (string) config('app.key', APP_ROOT)), 0, 12);
    if (!is_dir(APP_ROOT . '/' . $dir) && !mkdir(APP_ROOT . '/' . $dir, 0775, true) && !is_dir(APP_ROOT . '/' . $dir)) {
        throw new RuntimeException('Could not create ' . $dir);
    }
    $path = $dir . '/' . bin2hex(random_bytes(12)) . '.' . $ext;
    if (file_put_contents(APP_ROOT . '/' . $path, $bytes) === false) {
        throw new RuntimeException('Could not save ' . $path);
    }
    db_run('INSERT INTO member_website_images (user_id, path, bytes, width, height) VALUES (?, ?, ?, ?, ?)', [$user['id'], $path, strlen($bytes), $info[0], $info[1]]);
    return ['path' => $path, 'url' => media_url($path), 'width' => $info[0], 'height' => $info[1]];
}

/* ---------------------------------------------------------------------
 * The website's contact form
 * ------------------------------------------------------------------- */

/**
 * A visitor's message from a member's website: added to the member's
 * Contacts (source "My website"; an existing contact with the same email
 * gets the message in its notes) and the member is notified.
 */
function site_contact(array $in): array
{
    if (str_in($in, 'company_website', 200) !== '') {
        return ['message' => 'Thank you! Your message was sent.']; // honeypot: a bot
    }
    $ownerId = str_in($in, 'site', 40);
    $site = $ownerId !== '' ? site_public($ownerId) : null;
    $hasForm = $site && array_filter($site['sections'], fn ($s) => $s['type'] === 'contact' && !$s['hidden'] && !empty($s['data']['form']));
    if (!$hasForm) {
        throw new InvalidArgumentException('This website does not take messages.');
    }
    $ipBucket = 'site-contact-ip:' . client_ip();
    $siteBucket = 'site-contact:' . $ownerId;
    if (rate_limited($ipBucket, SITE_CONTACT_PER_IP, 600) || rate_limited($siteBucket, SITE_CONTACT_PER_SITE, 86400)) {
        throw new InvalidArgumentException('Too many messages. Please try again later.');
    }
    $name = site_line($in['name'] ?? '', 150);
    $email = strtolower(site_line($in['email'] ?? '', 190));
    $phone = site_line($in['phone'] ?? '', 60);
    $message = site_text($in['message'] ?? '', 2000);
    if ($name === '') {
        throw new InvalidArgumentException('Please enter your name.');
    }
    if ($email !== '' && !valid_email($email)) {
        throw new InvalidArgumentException('Please enter a valid email address.');
    }
    if ($email === '' && $phone === '') {
        throw new InvalidArgumentException('Please enter your email or phone number so they can reply.');
    }
    if (mb_strlen($message) < 2) {
        throw new InvalidArgumentException('Please write a message.');
    }
    rate_limit_hit($ipBucket, 600);
    rate_limit_hit($siteBucket, 86400);

    $note = '[' . date('M j, Y g:i A') . '] Message from your website: ' . $message;
    $existing = $email !== '' ? db_one('SELECT * FROM crm_contacts WHERE user_id = ? AND email = ? ORDER BY id LIMIT 1', [$ownerId, $email]) : null;
    if ($existing) {
        db_run('UPDATE crm_contacts SET notes = ?, phone = IF(phone = "", ?, phone) WHERE id = ?',
            [mb_substr(trim($note . "\n\n" . (string) $existing['notes']), 0, 5000), $phone, $existing['id']]);
    } else {
        try {
            crm_save_contact(['id' => $ownerId], ['name' => $name, 'email' => $email, 'phone' => $phone, 'source' => 'website', 'stage' => 'new', 'notes' => $note, 'autoFollow' => true]);
        } catch (InvalidArgumentException $e) {
            error_log('[website] contact for ' . $ownerId . ' not saved: ' . $e->getMessage()); // e.g. the member's contact list is full
        }
    }
    domain_notify($ownerId, 'New message from your website: ' . $name, mb_substr($name . ($email !== '' ? ' (' . $email . ')' : '') . ': ' . $message, 0, 500) . ' — it is in your Contacts.');
    return ['message' => 'Thank you! Your message was sent.'];
}
