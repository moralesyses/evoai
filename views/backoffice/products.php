<?php
/**
 * Backoffice tab 10 — My Products (includes/cards.php,
 * assets/js/views/bo-products.js): the member's own products, shown on their
 * 1EVOAI card and in the Shop section of their website, where visitors buy
 * them and pay with Maya, and the paid orders
 * to deliver (New / Shipped / Completed). Each sale, less the platform fee,
 * goes to the member's Available Balance once Maya confirms the payment.
 *
 * Uses: $user, $pageData.
 */
$mpProducts = card_products($user['id']);
$mpOrders = card_orders_for($user['id']);
$mpFee = card_sales_settings()['feePercent'];
$mpFeeText = rtrim(rtrim(number_format($mpFee, 2), '0'), '.') . '%';
$mpCard = card_share_url(get_member_profile($user['id']), false);
$mpShown = count(array_filter($mpProducts, fn ($p) => $p['active']));
$pageData['cardProducts'] = ['products' => $mpProducts, 'max' => CARD_PRODUCTS_MAX];
$mpInput = 'w-full px-3 py-2 rounded-xl bg-zinc-900 border border-zinc-700 text-xs text-white focus:outline-none focus:border-[#f3bd35]';
$mpLabel = 'block text-[11px] font-semibold text-zinc-300 mb-1';
$mpSmall = 'px-2.5 py-1 bg-zinc-900 hover:bg-zinc-800 border border-zinc-700 text-zinc-300 hover:text-white text-[11px] font-medium rounded-lg transition-colors flex items-center gap-1 disabled:opacity-40';
?>
<div class="space-y-5" id="bo-products">
    <div class="p-5 rounded-3xl bg-gradient-to-r from-[#16140d] via-[#101010] to-[#0d0d0d] border border-[#f3bd35]/30 shadow-xl flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="min-w-0">
            <h2 class="text-xl font-black text-white flex items-center gap-2"><?= icon('tag', 'w-5 h-5 text-[#f3bd35]') ?>My Products</h2>
            <p class="text-xs text-zinc-400 mt-1">Sell your own products on your 1EVOAI card<?= site_unlocked($user['id']) ? ' and in the <strong class="text-zinc-200">Shop</strong> section of your website (My Website)' : '' ?>. Visitors pay with Maya, and once a payment is confirmed the amount<?= $mpFee > 0 ? ', less the ' . e($mpFeeText) . ' platform fee,' : '' ?> goes to your <strong class="text-zinc-200">Available Balance</strong>, which you can withdraw. You deliver the order.</p>
            <p class="text-[11px] text-zinc-500 mt-1"><?= $mpShown ?> of <?= count($mpProducts) ?> products on <a href="<?= e($mpCard) ?>#my-products" target="_blank" rel="noopener" class="text-[#ffda72] hover:text-white">your card</a>. Up to <?= CARD_PRODUCTS_MAX ?> products.</p>
        </div>
        <button type="button" data-mp-add class="flex items-center justify-center gap-1.5 px-4 py-2 rounded-xl text-xs font-bold bg-[#f3bd35] hover:bg-[#ffda72] text-black disabled:opacity-50 shrink-0"<?= count($mpProducts) >= CARD_PRODUCTS_MAX ? ' disabled title="You have the most products a card can show."' : '' ?>><?= icon('plus', 'w-3.5 h-3.5') ?><span>Add product</span></button>
    </div>
    <?php if (!maya_enabled()): ?>
        <div class="p-3 rounded-2xl bg-amber-950/40 border border-amber-600/40 text-xs text-amber-200"><?= e(MAYA_UNAVAILABLE) ?>, so visitors can see your products but can't buy them yet.</div>
    <?php endif; ?>

    <div class="rounded-3xl bg-[#121212] border border-zinc-800 overflow-hidden">
        <?php if (!$mpProducts): ?>
            <div class="p-6 text-center text-xs text-zinc-400 space-y-1">
                <p class="text-zinc-200 font-semibold">No products yet</p>
                <p>Press <strong class="text-zinc-200">Add product</strong>: a picture, a name and a price are all you need.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-zinc-900/80 border-b border-zinc-800 text-zinc-400 uppercase text-[10px] tracking-wider">
                        <tr>
                            <th class="py-3 px-4">Product</th>
                            <th class="py-3 px-4 text-right">Price</th>
                            <th class="py-3 px-4 text-right">Stock</th>
                            <th class="py-3 px-4">On card</th>
                            <th class="py-3 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800/80">
                        <?php foreach ($mpProducts as $i => $p): ?>
                            <tr data-mp-row="<?= e($p['id']) ?>" class="hover:bg-zinc-900/40 transition-colors<?= $p['active'] ? '' : ' opacity-60' ?>">
                                <td class="py-3 px-4">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <img src="<?= e($p['image']) ?>" alt="" loading="lazy" class="w-12 h-12 rounded-xl object-cover bg-zinc-950 border border-zinc-800 shrink-0">
                                        <span class="font-bold text-white truncate max-w-xs"><?= e($p['name']) ?></span>
                                    </div>
                                </td>
                                <td class="py-3 px-4 text-right font-mono text-white font-bold whitespace-nowrap"><?= peso($p['price']) ?></td>
                                <td class="py-3 px-4 text-right font-mono <?= $p['stock'] === 0 ? 'text-rose-400 font-bold' : 'text-zinc-300' ?>"><?= $p['stock'] === null ? 'No limit' : number_format($p['stock']) ?></td>
                                <td class="py-3 px-4">
                                    <?php if ($p['active']): ?>
                                        <span class="text-[9px] px-2 py-0.5 rounded-full bg-emerald-500/15 text-emerald-400 font-bold uppercase">Shown</span>
                                    <?php else: ?>
                                        <span class="text-[9px] px-2 py-0.5 rounded-full bg-zinc-800 text-zinc-400 font-bold uppercase">Hidden</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <button type="button" data-mp-move="<?= e($p['id']) ?>" data-dir="-1" class="<?= $mpSmall ?>" aria-label="Move <?= e($p['name']) ?> up"<?= $i === 0 ? ' disabled' : '' ?>>↑</button>
                                        <button type="button" data-mp-move="<?= e($p['id']) ?>" data-dir="1" class="<?= $mpSmall ?>" aria-label="Move <?= e($p['name']) ?> down"<?= $i === count($mpProducts) - 1 ? ' disabled' : '' ?>>↓</button>
                                        <button type="button" data-mp-edit="<?= e($p['id']) ?>" class="<?= $mpSmall ?>"><?= icon('pen-line', 'w-3 h-3') ?><span>Edit</span></button>
                                        <button type="button" data-mp-active="<?= e($p['id']) ?>" data-to="<?= $p['active'] ? '0' : '1' ?>" class="<?= $mpSmall ?>"><?= icon($p['active'] ? 'eye-off' : 'eye', 'w-3 h-3') ?><span><?= $p['active'] ? 'Hide' : 'Show' ?></span></button>
                                        <button type="button" data-mp-delete="<?= e($p['id']) ?>" data-name="<?= e($p['name']) ?>" class="<?= $mpSmall ?> hover:text-rose-300" title="Delete" aria-label="Delete <?= e($p['name']) ?>"><?= icon('trash-2', 'w-3 h-3') ?></button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="space-y-3">
        <h3 class="text-sm font-bold text-white flex items-center gap-2"><?= icon('package', 'w-4 h-4 text-[#f3bd35]') ?>Orders from my card</h3>
        <div class="rounded-3xl bg-[#121212] border border-zinc-800 overflow-hidden">
            <?php if (!$mpOrders): ?>
                <p class="p-6 text-center text-xs text-zinc-400">No orders yet. Paid orders appear here with the buyer's contact and delivery details.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-zinc-900/80 border-b border-zinc-800 text-zinc-400 uppercase text-[10px] tracking-wider">
                            <tr>
                                <th class="py-3 px-4">Order</th>
                                <th class="py-3 px-4">Buyer</th>
                                <th class="py-3 px-4">Deliver to</th>
                                <th class="py-3 px-4 text-right">Paid / You get</th>
                                <th class="py-3 px-4">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-800/80">
                            <?php foreach ($mpOrders as $o): ?>
                                <tr data-mp-order="<?= e($o['id']) ?>">
                                    <td class="py-3 px-4">
                                        <span class="block font-bold text-white"><?= e($o['product']) ?> × <?= (int) $o['quantity'] ?></span>
                                        <span class="block text-[10px] text-zinc-500 font-mono"><?= e($o['id']) ?> · <?= e(date('M j, Y g:i A', (int) ($o['paidAt'] / 1000))) ?> · <?= e(CARD_ORDER_SOURCES[$o['source']] ?? 'Card') ?></span>
                                    </td>
                                    <td class="py-3 px-4 text-zinc-300">
                                        <span class="block text-white font-semibold"><?= e($o['buyerName']) ?></span>
                                        <a href="tel:<?= e(preg_replace('/[^\d+]/', '', $o['buyerPhone'])) ?>" class="block text-[11px] text-[#ffda72] hover:text-white"><?= e($o['buyerPhone']) ?></a>
                                        <a href="mailto:<?= e($o['buyerEmail']) ?>" class="block text-[11px] text-zinc-400 hover:text-white break-all"><?= e($o['buyerEmail']) ?></a>
                                    </td>
                                    <td class="py-3 px-4 text-zinc-300 text-[11px] whitespace-pre-line max-w-xs"><?= e($o['delivery'] !== '' ? $o['delivery'] : '—') ?></td>
                                    <td class="py-3 px-4 text-right font-mono whitespace-nowrap"><span class="text-white font-bold"><?= peso($o['amount']) ?></span><span class="block text-[10px] text-emerald-400"><?= peso($o['net']) ?></span></td>
                                    <td class="py-3 px-4">
                                        <?php if ($o['status'] === 'refunded'): ?>
                                            <span class="text-[9px] px-2 py-0.5 rounded-full bg-rose-500/15 text-rose-300 font-bold uppercase">Refunded</span>
                                        <?php else: ?>
                                            <select data-mp-fulfil="<?= e($o['id']) ?>" aria-label="Delivery status of <?= e($o['id']) ?>" class="px-2 py-1 rounded-lg bg-zinc-900 border border-zinc-700 text-[11px] text-zinc-200 focus:outline-none focus:border-[#f3bd35]">
                                                <?php foreach (CARD_FULFILLMENT as $k => $label): ?>
                                                    <option value="<?= e($k) ?>"<?= $o['fulfillment'] === $k ? ' selected' : '' ?>><?= e($label) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div data-modal="bo-product" hidden class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4" role="dialog" aria-modal="true" aria-labelledby="mp-form-title">
        <div class="w-full max-w-lg bg-[#141414] border border-[#f3bd35]/40 rounded-3xl p-6 shadow-2xl space-y-4 text-xs max-h-[92vh] overflow-y-auto">
            <div class="flex items-center justify-between pb-3 border-b border-zinc-800">
                <h4 class="font-bold text-white text-sm" id="mp-form-title" data-mp-title>Add product</h4>
                <button type="button" data-close-modal class="text-zinc-400 hover:text-white" aria-label="Close"><?= icon('x', 'w-4 h-4') ?></button>
            </div>
            <form id="mp-form" class="space-y-3" novalidate>
                <input type="hidden" name="id" value="">
                <div class="flex items-center gap-4">
                    <div class="w-24 h-24 rounded-2xl bg-zinc-950 border border-zinc-800 overflow-hidden shrink-0 flex items-center justify-center text-zinc-600">
                        <img data-mp-preview alt="" hidden class="w-full h-full object-cover">
                        <span data-mp-noimage><?= icon('package', 'w-8 h-8') ?></span>
                    </div>
                    <div class="min-w-0 flex-1">
                        <label for="mp-image" class="<?= $mpLabel ?>">Picture <span data-mp-image-req>*</span></label>
                        <input id="mp-image" name="image" type="file" accept="image/jpeg,image/png,image/webp,image/gif" class="evo-file block w-full text-[11px] text-zinc-400">
                        <span class="block text-[10px] text-zinc-500 mt-1">JPEG, PNG, WebP or GIF, up to 5 MB. A square picture looks best.</span>
                    </div>
                </div>
                <div>
                    <label for="mp-name" class="<?= $mpLabel ?>">Product name *</label>
                    <input id="mp-name" name="name" type="text" required maxlength="150" class="<?= $mpInput ?>">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="mp-price" class="<?= $mpLabel ?>">Price (₱) *</label>
                        <input id="mp-price" name="price" type="number" required min="1" max="500000" step="0.01" class="<?= $mpInput ?> font-mono">
                    </div>
                    <div>
                        <label for="mp-stock" class="<?= $mpLabel ?>">Stock</label>
                        <input id="mp-stock" name="stock" type="number" min="0" step="1" placeholder="No limit" class="<?= $mpInput ?> font-mono">
                    </div>
                </div>
                <div>
                    <label for="mp-description" class="<?= $mpLabel ?>">Description</label>
                    <textarea id="mp-description" name="description" rows="3" maxlength="1000" placeholder="What it is, sizes, what's included…" class="<?= $mpInput ?>"></textarea>
                </div>
                <label class="flex items-center gap-2 text-zinc-300">
                    <input type="checkbox" name="active" value="1" checked class="evo-check">
                    <span>Show on my card</span>
                </label>
                <p class="text-[10px] text-zinc-500">Buyers pay the price shown with Maya. <?= $mpFee > 0 ? 'A ' . e($mpFeeText) . ' platform fee is kept from each sale; the rest goes to your Available Balance.' : 'The full amount goes to your Available Balance.' ?></p>
                <p data-mp-error hidden role="alert" class="p-3 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-300"></p>
                <button type="submit" class="w-full py-2.5 bg-[#f3bd35] hover:bg-[#ffda72] text-black font-extrabold rounded-xl text-xs flex items-center justify-center gap-1.5 disabled:opacity-60"><?= icon('save', 'w-3.5 h-3.5') ?><span>Save product</span></button>
            </form>
        </div>
    </div>
</div>
