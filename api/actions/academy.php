<?php
/**
 * Mentor Academy (includes/academy.php): admins keep the categories and the
 * learning materials (Management Admin > Mentor Academy,
 * assets/js/views/admin-academy.js); members open materials and mark them
 * completed (Backoffice > Mentor Academy, assets/js/views/bo-academy.js).
 * Loaded by api.php.
 */
declare(strict_types=1);

$academyUpload = static fn (string $field): ?array => is_array($_FILES[$field] ?? null) ? $_FILES[$field] : null;

return [
    /* ---------------- Admins: categories ---------------- */
    'admin.academyCategorySave' => static function (array $in): array {
        $c = academy_category_save(api_admin(), $in);
        return ['category' => $c, 'message' => (empty($in['id']) ? 'Added the category ' : 'Saved ') . $c['name'] . '.'];
    },
    'admin.academyCategoryMove' => static function (array $in): array {
        academy_category_move(api_admin(), str_in($in, 'id', 40), (int) ($in['dir'] ?? 1));
        return [];
    },
    'admin.academyCategoryDelete' => static function (array $in): array {
        return ['message' => 'Deleted the category ' . academy_category_delete(api_admin(), str_in($in, 'id', 40)) . '.'];
    },

    /* ---------------- Admins: materials ---------------- */
    // Add or edit (multipart form so files can come along: "file" and "cover").
    'admin.academySave' => static function (array $in) use ($academyUpload): array {
        $m = academy_material_save(api_admin(), $in, $academyUpload('file'), $academyUpload('cover'));
        return ['material' => $m, 'message' => (empty($in['id']) ? 'Added ' : 'Saved ') . $m['title'] . ($m['published'] ? '.' : ' (hidden from members).')];
    },
    'admin.academyPublish' => static function (array $in): array {
        $published = !empty($in['published']) && $in['published'] !== 'false' && $in['published'] !== '0';
        $m = academy_material_set_published(api_admin(), str_in($in, 'id', 40), $published);
        return ['material' => $m, 'message' => $m['title'] . ($published ? ' is published to members.' : ' is hidden from members.')];
    },
    'admin.academyDelete' => static function (array $in): array {
        return ['message' => 'Deleted ' . academy_material_delete(api_admin(), str_in($in, 'id', 40)) . '.'];
    },

    /* ---------------- Members ---------------- */
    'academy.view' => static function (array $in): array {
        academy_record_view(api_user(), str_in($in, 'id', 40));
        return [];
    },
    'academy.complete' => static function (array $in): array {
        $done = !empty($in['done']) && $in['done'] !== 'false' && $in['done'] !== '0';
        $at = academy_set_completed(api_user(), str_in($in, 'id', 40), $done);
        return ['completedAt' => $at];
    },
];
