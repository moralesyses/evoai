<?php
/**
 * Admin actions for Management Admin > Shop Products (includes/products.php,
 * assets/js/views/admin-products.js). Loaded by api.php; every action
 * requires the admin role.
 */
declare(strict_types=1);

return [
    // Add or edit a product. Sent as a form (multipart) so the picture can come along: field "image".
    'admin.productSave' => static function (array $in): array {
        $file = is_array($_FILES['image'] ?? null) ? $_FILES['image'] : null;
        $p = shop_product_save(api_admin(), $in, $file);
        return ['product' => $p, 'message' => (empty($in['id']) ? 'Added ' : 'Saved ') . $p['name'] . ($p['active'] ? '.' : ' (hidden from the shop).')];
    },

    // Show a product in the shop, or hide it.
    'admin.productActive' => static function (array $in): array {
        $active = !empty($in['active']) && $in['active'] !== 'false';
        $p = shop_product_set_active(api_admin(), str_in($in, 'id', 40), $active);
        return ['product' => $p, 'message' => $p['name'] . ($active ? ' is in the shop again.' : ' is hidden from the shop.')];
    },

    // Delete a product (members' carts lose it; orders keep their copy).
    'admin.productDelete' => static function (array $in): array {
        $name = shop_product_delete(api_admin(), str_in($in, 'id', 40));
        return ['message' => 'Deleted ' . $name . '.'];
    },
];
