<?php
/**
 * Members' websites (includes/website.php): the builder in Member
 * Backoffice > My Website (assets/js/views/bo-website.js), the public
 * site's contact form (assets/js/site.js) and Admin > .com Domains.
 * Loaded by api.php.
 */
declare(strict_types=1);

return [
    // Save the draft (the builder saves a moment after each change).
    'site.save' => static function (array $in): array {
        $site = site_save(api_user(), is_array($in['site'] ?? null) ? $in['site'] : []);
        return ['site' => $site, 'savedAt' => time() * 1000];
    },

    // Save and publish: visitors of the member's domain see it.
    'site.publish' => static function (array $in): array {
        $user = api_user();
        $site = site_publish($user, is_array($in['site'] ?? null) ? $in['site'] : null);
        $url = site_live_url($user['id']);
        return ['site' => $site, 'liveUrl' => $url, 'message' => 'Your website is live' . ($url ? ' at ' . $url : '') . '.'];
    },

    'site.unpublish' => static function (array $in): array {
        site_unpublish(api_user());
        return ['message' => 'Your website is taken down. Your domain shows your digital card again.'];
    },

    // Delete the website for good (draft, published copy and uploaded images). confirm: "DELETE".
    'site.delete' => static function (array $in): array {
        if (strtoupper(trim((string) ($in['confirm'] ?? ''))) !== 'DELETE') {
            throw new InvalidArgumentException('Type DELETE to confirm.');
        }
        $images = site_delete(api_user());
        return ['message' => 'Your website was deleted' . ($images ? ' with its ' . $images . ' image' . ($images === 1 ? '' : 's') : '') . '.'];
    },

    // Start again from the card profile.
    'site.reset' => static function (array $in): array {
        return ['site' => site_reset(api_user()), 'message' => 'Started again from your card profile.'];
    },

    // An image for the website (multipart form: field "image").
    'site.upload' => static function (array $in): array {
        $user = api_user();
        return site_upload_image($user, is_array($_FILES['image'] ?? null) ? $_FILES['image'] : []);
    },

    // A visitor's message from a member's website (no sign-in).
    'site.contact' => static function (array $in): array {
        return site_contact($in);
    },

    /* ----- Admin > .com Domains ----- */

    'site.suspend' => static function (array $in): array {
        site_suspend(api_admin(), str_in($in, 'userId', 40), true, str_in($in, 'note', 255));
        return ['message' => 'Website taken offline; the member was told why.'];
    },

    'site.restore' => static function (array $in): array {
        site_suspend(api_admin(), str_in($in, 'userId', 40), false, '');
        return ['message' => 'Website restored.'];
    },
];
