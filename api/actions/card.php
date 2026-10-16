<?php
/**
 * Members' card design and their own products (includes/cards.php):
 * Backoffice > Digital Card CMS > Card Design (assets/js/views/bo-digital-card.js),
 * Backoffice > My Products (assets/js/views/bo-products.js), buying on the card
 * (assets/js/views/card.js) and Management Admin > Shop Products (card sales).
 * Loaded by api.php.
 */
declare(strict_types=1);

$cardFile = static fn (): ?array => is_array($_FILES['image'] ?? null) ? $_FILES['image'] : null;
$cardFlag = static fn ($v): bool => !empty($v) && $v !== 'false' && $v !== '0';

return [
    /* ---------------- The member's card design ---------------- */
    'card.designSave' => static function (array $in): array {
        $d = card_design_save(api_user(), $in);
        return ['design' => $d, 'message' => 'Card design saved. Open your card to see it.'];
    },
    // The cover photo (multipart form, field "image").
    'card.coverUpload' => static function (array $in) use ($cardFile): array {
        return card_cover_upload(api_user(), $cardFile() ?? []) + ['message' => 'Cover photo updated.'];
    },
    'card.coverRemove' => static function (array $in): array {
        card_cover_remove(api_user());
        return ['message' => 'Cover photo removed.'];
    },

    /* ---------------- The member's products ---------------- */
    // Add or edit (multipart form so the picture can come along: field "image").
    'card.productSave' => static function (array $in) use ($cardFile): array {
        $p = card_product_save(api_user(), $in, $cardFile());
        return ['product' => $p, 'message' => (empty($in['id']) ? 'Added ' : 'Saved ') . $p['name'] . ($p['active'] ? '.' : ' (hidden from your card).')];
    },
    'card.productActive' => static function (array $in) use ($cardFlag): array {
        $p = card_product_set_active(api_user(), str_in($in, 'id', 40), $cardFlag($in['active'] ?? ''));
        return ['product' => $p, 'message' => $p['name'] . ($p['active'] ? ' is on your card again.' : ' is hidden from your card.')];
    },
    'card.productDelete' => static function (array $in): array {
        return ['message' => 'Deleted ' . card_product_delete(api_user(), str_in($in, 'id', 40)) . '.'];
    },
    'card.productMove' => static function (array $in): array {
        card_product_move(api_user(), str_in($in, 'id', 40), (int) ($in['dir'] ?? 1));
        return [];
    },
    'card.orderFulfil' => static function (array $in): array {
        $o = card_order_set_fulfillment(api_user(), str_in($in, 'id', 40), str_in($in, 'fulfillment', 20));
        return ['order' => $o, 'message' => $o['id'] . ' marked ' . strtolower(CARD_FULFILLMENT[$o['fulfillment']]) . '.'];
    },

    /* ---------------- Buying on a card (visitors too) ---------------- */
    'card.buy' => static function (array $in): array {
        return ['redirectUrl' => card_order_start($in, current_user())];
    },

    /* ---------------- Admins ---------------- */
    'admin.cardSalesSave' => static function (array $in): array {
        $s = card_sales_save_settings(api_admin(), $in);
        return ['settings' => $s, 'message' => 'Platform fee saved: ' . rtrim(rtrim(number_format($s['feePercent'], 2), '0'), '.') . '% of each sale.'];
    },
    // The buyer was refunded in Maya Business Manager: record it.
    'admin.cardOrderRefund' => static function (array $in): array {
        $o = card_order_refund(api_admin(), str_in($in, 'id', 40), str_in($in, 'note', 255));
        return ['order' => $o, 'message' => $o['id'] . ' recorded as refunded; ₱' . num($o['net']) . ' taken back from the member\'s balance.'];
    },
];
