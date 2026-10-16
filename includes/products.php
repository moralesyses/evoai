<?php
/**
 * Member Shop products, managed in Management Admin > Shop Products
 * (views/admin/products.php, assets/js/views/admin-products.js,
 * api/actions/admin_products.php): add, edit, show / hide and delete
 * products, and upload their pictures to uploads/products/.
 *
 * Members only see products that are shown (active = 1); a hidden product
 * can't be added to a cart or checked out. Orders keep their own copy of
 * each product's name and price, so editing or deleting a product never
 * changes an order.
 */
declare(strict_types=1);

const PRODUCT_IMAGE_DIR = 'uploads/products';

/** Every product for the admin page, hidden ones too, with how many were sold. */
function shop_admin_products(): array
{
    $rows = db_all('SELECT p.*,
            (SELECT COALESCE(SUM(i.quantity), 0) FROM mlm_order_items i JOIN mlm_orders o ON o.id = i.order_id
              WHERE i.product_id = p.id AND o.status NOT IN ("awaiting_payment", "cancelled")) AS sold,
            (SELECT COUNT(*) FROM mlm_cart_items c WHERE c.product_id = p.id) AS in_carts
        FROM mlm_products p ORDER BY p.sort_order, p.name');
    return array_map(fn ($r) => mlm_product_to_array($r) + [
        'imagePath' => (string) $r['image'], 'sortOrder' => (int) $r['sort_order'],
        'sold' => (int) $r['sold'], 'inCarts' => (int) $r['in_carts'],
    ], $rows);
}

/** The categories in use, for the form's suggestions. */
function shop_categories(): array
{
    return array_column(db_all('SELECT DISTINCT category FROM mlm_products ORDER BY category'), 'category');
}

/**
 * Add ($in['id'] empty) or update a product. $file: the uploaded picture
 * ($_FILES['image']), required for a new product, optional when editing.
 * Returns the saved product.
 */
function shop_product_save(array $admin, array $in, ?array $file): array
{
    $id = trim((string) ($in['id'] ?? ''));
    $old = $id !== '' ? db_one('SELECT * FROM mlm_products WHERE id = ?', [$id]) : null;
    if ($id !== '' && !$old) {
        throw new InvalidArgumentException('That product no longer exists. Refresh the page.');
    }
    $name = mb_substr(trim((string) ($in['name'] ?? '')), 0, 190);
    $category = mb_substr(trim((string) ($in['category'] ?? '')), 0, 80);
    $description = mb_substr(trim((string) ($in['description'] ?? '')), 0, 2000);
    $price = shop_money($in['price'] ?? '', 'regular price');
    $memberPrice = shop_money($in['memberPrice'] ?? '', 'member price');
    $points = shop_whole($in['points'] ?? 0, 'points', 100000);
    $stock = shop_whole($in['stock'] ?? 0, 'stock', 1000000);
    $sort = (int) ($in['sortOrder'] ?? 0);
    $active = !empty($in['active']) && $in['active'] !== 'false' && $in['active'] !== '0';
    if ($name === '') {
        throw new InvalidArgumentException('Please enter the product name.');
    }
    if ($category === '') {
        throw new InvalidArgumentException('Please enter a category, e.g. Wellness & Daily Repurchase.');
    }
    if ($memberPrice > $price) {
        throw new InvalidArgumentException('The member price can\'t be higher than the regular price.');
    }
    $hasFile = $file !== null && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    if (!$old && !$hasFile) {
        throw new InvalidArgumentException('Please choose a picture for the product.');
    }
    $image = $hasFile ? shop_product_image_upload($file) : (string) $old['image'];

    if ($old) {
        db_run('UPDATE mlm_products SET name = ?, category = ?, price = ?, member_price = ?, points = ?, image = ?, description = ?, stock = ?, sort_order = ?, active = ? WHERE id = ?',
            [$name, $category, $price, $memberPrice, $points, $image, $description, $stock, $sort, $active ? 1 : 0, $id]);
        if ($image !== $old['image']) {
            shop_product_image_delete((string) $old['image']);
        }
        add_audit('Shop product updated: ' . $name . ' (' . $id . ')', $admin['id']);
    } else {
        $base = substr(str_replace('-', '_', card_slug_clean($name)) ?: 'item', 0, 26);
        do {
            $id = 'prod_' . $base . '_' . bin2hex(random_bytes(2));
        } while (db_value('SELECT 1 FROM mlm_products WHERE id = ?', [$id]));
        db_run('INSERT INTO mlm_products (id, name, category, price, member_price, points, image, description, stock, sort_order, active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$id, $name, $category, $price, $memberPrice, $points, $image, $description, $stock, $sort, $active ? 1 : 0]);
        add_audit('Shop product added: ' . $name . ' (' . $id . ')', $admin['id']);
    }
    return mlm_product_to_array(db_one('SELECT * FROM mlm_products WHERE id = ?', [$id]));
}

/** Show a product in the shop, or hide it. */
function shop_product_set_active(array $admin, string $id, bool $active): array
{
    $p = db_one('SELECT * FROM mlm_products WHERE id = ?', [$id]);
    if (!$p) {
        throw new InvalidArgumentException('That product no longer exists. Refresh the page.');
    }
    db_run('UPDATE mlm_products SET active = ? WHERE id = ?', [$active ? 1 : 0, $id]);
    add_audit('Shop product ' . ($active ? 'shown' : 'hidden') . ': ' . $p['name'] . ' (' . $id . ')', $admin['id']);
    return mlm_product_to_array(db_one('SELECT * FROM mlm_products WHERE id = ?', [$id]));
}

/** Delete a product: it leaves members' carts; orders keep their own copy of it. */
function shop_product_delete(array $admin, string $id): string
{
    $p = db_one('SELECT * FROM mlm_products WHERE id = ?', [$id]);
    if (!$p) {
        throw new InvalidArgumentException('That product no longer exists. Refresh the page.');
    }
    db_run('DELETE FROM mlm_products WHERE id = ?', [$id]); // cart lines go with it (ON DELETE CASCADE)
    shop_product_image_delete((string) $p['image']);
    add_audit('Shop product deleted: ' . $p['name'] . ' (' . $id . ')', $admin['id']);
    return (string) $p['name'];
}

/** A price in pesos: more than 0, at most ₱10,000,000, two decimals. */
function shop_money($value, string $label): float
{
    $v = str_replace([',', '₱', ' '], '', trim((string) $value));
    if (!is_numeric($v) || (float) $v <= 0 || (float) $v > 10000000) {
        throw new InvalidArgumentException('Please enter the ' . $label . ' in pesos, e.g. 1350.');
    }
    return round((float) $v, 2);
}

/** A whole number from 0 to $max. */
function shop_whole($value, string $label, int $max): int
{
    $v = trim((string) $value);
    if ($v === '') {
        return 0;
    }
    if (!preg_match('/^\d+$/', $v) || (int) $v > $max) {
        throw new InvalidArgumentException('Please enter the ' . $label . ' as a whole number from 0 to ' . number_format($max) . '.');
    }
    return (int) $v;
}

/** Check and store an uploaded product picture in uploads/products/ (image_upload_store()). */
function shop_product_image_upload(array $file): string
{
    return image_upload_store($file, PRODUCT_IMAGE_DIR);
}

/** Remove a picture this page uploaded once no product uses it (the built-in ones in assets/ stay). */
function shop_product_image_delete(string $path): void
{
    if (!image_upload_is_ours($path, PRODUCT_IMAGE_DIR)) {
        return;
    }
    if (!db_value('SELECT 1 FROM mlm_products WHERE image = ?', [$path]) && is_file(APP_ROOT . '/' . $path)) {
        @unlink(APP_ROOT . '/' . $path);
    }
}
