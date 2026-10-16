<?php
/**
 * A member's own profile picture (Backoffice > Digital Card CMS,
 * assets/js/views/bo-digital-card.js; includes/repo.php). Loaded by api.php.
 */
declare(strict_types=1);

return [
    // Upload a new profile picture (multipart form, field "image"); it shows on the card at once.
    'profile.avatarUpload' => static function (array $in): array {
        $r = profile_avatar_upload(api_user(), is_array($_FILES['image'] ?? null) ? $_FILES['image'] : []);
        return $r + ['message' => 'Profile picture updated.'];
    },

    // Remove it: the card shows the member's initials again.
    'profile.avatarRemove' => static function (array $in): array {
        profile_avatar_remove(api_user());
        return ['message' => 'Profile picture removed.'];
    },
];
