<?php
/**
 * Management Admin > Shop Products (includes/products.php): every Member Shop
 * product with its picture, prices, PV, stock and sales; add and edit
 * products (picture upload) in a form, show / hide them in the shop, and
 * delete them. Actions go through api/actions/admin_products.php
 * (assets/js/views/admin-products.js). Below: members' own products sold on
 * their cards (includes/cards.php): the platform fee, totals, and recording
 * refunds made in Maya (api/actions/card.php).
 */
$prProducts = shop_admin_products();
$prShown = count(array_filter($prProducts, fn ($p) => $p['active']));
$pageData['adminProducts'] = ['products' => $prProducts];
$prInput = 'w-full px-3 py-2 rounded-xl bg-zinc-900 border border-zinc-700 text-xs text-white focus:outline-none focus:border-[#f3bd35]';
$prLabel = 'block text-[11px] font-semibold text-zinc-300 mb-1';
$prSmall = 'px-2.5 py-1 bg-zinc-900 hover:bg-zinc-800 border border-zinc-700 text-zinc-300 hover:text-white text-[11px] font-medium rounded-lg transition-colors flex items-center gap-1';
?>
<div class="space-y-4" id="admin-products">
    <div class="p-5 rounded-3xl bg-[#111111] border border-[#f3bd35]/20 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h3 class="text-sm font-bold text-white flex items-center gap-2"><?= icon('shopping-bag', 'w-4 h-4 text-[#f3bd35]') ?>Member Shop Products</h3>
            <p class="text-xs text-zinc-400 mt-0.5">Products members buy in Backoffice → Shop with their wallet balance or Maya. <?= $prShown ?> shown in the shop<?= count($prProducts) - $prShown ? ', ' . (count($prProducts) - $prShown) . ' hidden' : '' ?>.</p>
        </div>
        <button type="button" data-prod-add class="px-5 py-2 bg-[#f3bd35] hover:bg-[#ffda72] text-black text-xs font-extrabold rounded-xl shadow-md transition-colors flex items-center justify-center gap-1.5"><?= icon('plus', 'w-3.5 h-3.5') ?><span>Add product</span></button>
    </div>

    <div class="rounded-3xl bg-[#121212] border border-zinc-800 overflow-hidden">
        <?php if (!$prProducts): ?>
            <p class="p-6 text-center text-xs text-zinc-400">No products yet. Press <strong class="text-zinc-200">Add product</strong> to put the first one in the shop.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-zinc-900/80 border-b border-zinc-800 text-zinc-400 uppercase text-[10px] tracking-wider">
                        <tr>
                            <th class="py-3 px-4">Product</th>
                            <th class="py-3 px-4 text-right">Member / Regular</th>
                            <th class="py-3 px-4 text-right">Points</th>
                            <th class="py-3 px-4 text-right">Stock</th>
                            <th class="py-3 px-4 text-right">Sold</th>
                            <th class="py-3 px-4">Shop</th>
                            <th class="py-3 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800/80">
                        <?php foreach ($prProducts as $p): ?>
                            <tr data-prod-row="<?= e($p['id']) ?>" class="hover:bg-zinc-900/40 transition-colors<?= $p['active'] ? '' : ' opacity-60' ?>">
                                <td class="py-3 px-4">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <img src="<?= e($p['image']) ?>" alt="" loading="lazy" class="w-12 h-12 rounded-xl object-cover bg-zinc-950 border border-zinc-800 shrink-0">
                                        <div class="min-w-0">
                                            <span class="block font-bold text-white truncate max-w-xs"><?= e($p['name']) ?></span>
                                            <span class="block text-[10px] text-zinc-500"><?= e($p['category']) ?> · <span class="font-mono"><?= e($p['id']) ?></span></span>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3 px-4 text-right font-mono whitespace-nowrap"><span class="text-white font-bold"><?= peso($p['memberPrice']) ?></span><span class="block text-[10px] text-zinc-500"><?= peso($p['price']) ?></span></td>
                                <td class="py-3 px-4 text-right font-mono text-emerald-400 font-bold">+<?= (int) $p['points'] ?></td>
                                <td class="py-3 px-4 text-right font-mono <?= $p['stock'] > 0 ? 'text-zinc-200' : 'text-rose-400 font-bold' ?>"><?= number_format($p['stock']) ?></td>
                                <td class="py-3 px-4 text-right font-mono text-zinc-300"><?= number_format($p['sold']) ?></td>
                                <td class="py-3 px-4">
                                    <?php if ($p['active']): ?>
                                        <span class="text-[9px] px-2 py-0.5 rounded-full bg-emerald-500/15 text-emerald-400 font-bold uppercase">Shown</span>
                                    <?php else: ?>
                                        <span class="text-[9px] px-2 py-0.5 rounded-full bg-zinc-800 text-zinc-400 font-bold uppercase">Hidden</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <button type="button" data-prod-edit="<?= e($p['id']) ?>" class="<?= $prSmall ?>"><?= icon('pen-line', 'w-3 h-3') ?><span>Edit</span></button>
                                        <button type="button" data-prod-active="<?= e($p['id']) ?>" data-to="<?= $p['active'] ? '0' : '1' ?>" class="<?= $prSmall ?>" title="<?= $p['active'] ? 'Hide from the shop' : 'Show in the shop' ?>"><?= icon($p['active'] ? 'eye-off' : 'eye', 'w-3 h-3') ?><span><?= $p['active'] ? 'Hide' : 'Show' ?></span></button>
                                        <button type="button" data-prod-delete="<?= e($p['id']) ?>" data-name="<?= e($p['name']) ?>" data-carts="<?= (int) $p['inCarts'] ?>" class="<?= $prSmall ?> hover:text-rose-300" title="Delete" aria-label="Delete <?= e($p['name']) ?>"><?= icon('trash-2', 'w-3 h-3') ?></button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <?php
    // Members' own products sold on their cards (includes/cards.php).
    $csSales = card_sales_admin();
    $csFee = card_sales_settings()['feePercent'];
    $csStat = 'p-4 rounded-2xl bg-[#121212] border border-zinc-800';
    ?>
    <section class="space-y-3 pt-2" aria-labelledby="cs-title">
        <div class="p-5 rounded-3xl bg-[#111111] border border-[#f3bd35]/20 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="min-w-0">
                <h3 id="cs-title" class="text-sm font-bold text-white flex items-center gap-2"><?= icon('tag', 'w-4 h-4 text-[#f3bd35]') ?>Members' card sales</h3>
                <p class="text-xs text-zinc-400 mt-0.5">Members sell their own products on their 1EVOAI cards and in their websites' Shop section (Backoffice → My Products), and buyers pay with Maya. The fee is kept from each sale; the rest goes to the member's Available Balance once Maya confirms the payment.</p>
                <p class="text-[11px] text-zinc-500 mt-1">To refund a buyer, refund the payment in Maya Business Manager first, then press <strong class="text-zinc-300">Record refund</strong> here. The member's balance gives back what the sale added, which can take it below zero.</p>
            </div>
            <form data-cs-fee-form class="flex items-end gap-2 shrink-0" novalidate>
                <div>
                    <label for="cs-fee" class="block text-[11px] font-semibold text-zinc-300 mb-1">Platform fee (%)</label>
                    <input id="cs-fee" name="feePercent" type="number" min="0" max="50" step="0.01" value="<?= e(rtrim(rtrim(number_format($csFee, 2, '.', ''), '0'), '.')) ?>" class="w-24 px-3 py-2 rounded-xl bg-zinc-900 border border-zinc-700 text-xs text-white font-mono focus:outline-none focus:border-[#f3bd35]">
                </div>
                <button type="submit" class="px-4 py-2 bg-[#f3bd35] hover:bg-[#ffda72] text-black text-xs font-extrabold rounded-xl disabled:opacity-60">Save fee</button>
            </form>
        </div>
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            <div class="<?= $csStat ?>"><span class="text-[11px] text-zinc-400">Paid sales</span><div class="text-xl font-black text-white font-mono mt-1"><?= number_format($csSales['totals']['count']) ?></div></div>
            <div class="<?= $csStat ?>"><span class="text-[11px] text-zinc-400">Buyers paid</span><div class="text-xl font-black text-[#ffda72] font-mono mt-1"><?= peso($csSales['totals']['gross']) ?></div></div>
            <div class="<?= $csStat ?>"><span class="text-[11px] text-zinc-400">Fees kept</span><div class="text-xl font-black text-white font-mono mt-1"><?= peso($csSales['totals']['fees']) ?></div></div>
            <div class="<?= $csStat ?>"><span class="text-[11px] text-zinc-400">Paid to members</span><div class="text-xl font-black text-emerald-400 font-mono mt-1"><?= peso($csSales['totals']['net']) ?></div></div>
        </div>
        <div class="rounded-3xl bg-[#121212] border border-zinc-800 overflow-hidden">
            <?php if (!$csSales['orders']): ?>
                <p class="p-6 text-center text-xs text-zinc-400">No card sales yet.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-zinc-900/80 border-b border-zinc-800 text-zinc-400 uppercase text-[10px] tracking-wider">
                            <tr>
                                <th class="py-3 px-4">Order</th>
                                <th class="py-3 px-4">Member</th>
                                <th class="py-3 px-4">Buyer</th>
                                <th class="py-3 px-4 text-right">Paid / Fee / Member</th>
                                <th class="py-3 px-4">Status</th>
                                <th class="py-3 px-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-800/80">
                            <?php foreach ($csSales['orders'] as $o): ?>
                                <tr data-cs-row="<?= e($o['id']) ?>">
                                    <td class="py-3 px-4">
                                        <span class="block font-bold text-white"><?= e($o['product']) ?> × <?= (int) $o['quantity'] ?></span>
                                        <span class="block text-[10px] text-zinc-500 font-mono"><?= e($o['id']) ?> · <?= e(date('M j, Y', (int) (($o['paidAt'] ?? $o['createdAt']) / 1000))) ?> · <?= e(CARD_ORDER_SOURCES[$o['source']] ?? 'Card') ?></span>
                                    </td>
                                    <td class="py-3 px-4 text-zinc-300"><?= e($o['sellerName']) ?></td>
                                    <td class="py-3 px-4 text-zinc-300"><span class="block"><?= e($o['buyerName']) ?></span><span class="block text-[10px] text-zinc-500 break-all"><?= e($o['buyerEmail']) ?></span></td>
                                    <td class="py-3 px-4 text-right font-mono whitespace-nowrap"><span class="block text-white font-bold"><?= peso($o['amount']) ?></span><span class="block text-[10px] text-zinc-500"><?= peso($o['fee']) ?></span><span class="block text-[10px] text-emerald-400"><?= peso($o['net']) ?></span></td>
                                    <td class="py-3 px-4">
                                        <?php if ($o['status'] === 'refunded'): ?>
                                            <span class="text-[9px] px-2 py-0.5 rounded-full bg-rose-500/15 text-rose-300 font-bold uppercase" title="<?= e($o['refundNote']) ?>">Refunded</span>
                                        <?php else: ?>
                                            <span class="text-[9px] px-2 py-0.5 rounded-full bg-emerald-500/15 text-emerald-400 font-bold uppercase">Paid</span>
                                            <span class="block text-[10px] text-zinc-500 mt-1"><?= e(CARD_FULFILLMENT[$o['fulfillment']]) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 px-4 text-right">
                                        <?php if ($o['status'] === 'paid'): ?>
                                            <button type="button" data-cs-refund="<?= e($o['id']) ?>" data-amount="<?= e(peso($o['amount'])) ?>" data-net="<?= e(peso($o['net'])) ?>" class="<?= $prSmall ?> ml-auto hover:text-rose-300"><?= icon('rotate-ccw', 'w-3 h-3') ?><span>Record refund</span></button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <div data-modal="admin-product" hidden class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4" role="dialog" aria-modal="true" aria-labelledby="prod-form-title">
        <div class="w-full max-w-lg bg-[#141414] border border-[#f3bd35]/40 rounded-3xl p-6 shadow-2xl space-y-4 text-xs max-h-[92vh] overflow-y-auto">
            <div class="flex items-center justify-between pb-3 border-b border-zinc-800">
                <h4 class="font-bold text-white text-sm" id="prod-form-title" data-prod-title>Add product</h4>
                <button type="button" data-close-modal class="text-zinc-400 hover:text-white" aria-label="Close"><?= icon('x', 'w-4 h-4') ?></button>
            </div>
            <form id="prod-form" class="space-y-3" novalidate>
                <input type="hidden" name="id" value="">
                <div class="flex items-center gap-4">
                    <div class="w-24 h-24 rounded-2xl bg-zinc-950 border border-zinc-800 overflow-hidden shrink-0 flex items-center justify-center text-zinc-600">
                        <img data-prod-preview alt="" hidden class="w-full h-full object-cover">
                        <span data-prod-noimage><?= icon('package', 'w-8 h-8') ?></span>
                    </div>
                    <div class="min-w-0 flex-1">
                        <label for="prod-image" class="<?= $prLabel ?>">Picture <span data-prod-image-req>*</span></label>
                        <input id="prod-image" name="image" type="file" accept="image/jpeg,image/png,image/webp,image/gif" class="evo-file block w-full text-[11px] text-zinc-400">
                        <span class="block text-[10px] text-zinc-500 mt-1">JPEG, PNG, WebP or GIF, up to 5 MB. A square picture looks best.</span>
                    </div>
                </div>
                <div>
                    <label for="prod-name" class="<?= $prLabel ?>">Product name *</label>
                    <input id="prod-name" name="name" type="text" required maxlength="190" class="<?= $prInput ?>">
                </div>
                <div>
                    <label for="prod-category" class="<?= $prLabel ?>">Category *</label>
                    <input id="prod-category" name="category" type="text" required maxlength="80" list="prod-categories" placeholder="e.g. Wellness & Daily Repurchase" class="<?= $prInput ?>">
                    <datalist id="prod-categories">
                        <?php foreach (shop_categories() as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?>
                    </datalist>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="prod-member-price" class="<?= $prLabel ?>">Member price (₱) *</label>
                        <input id="prod-member-price" name="memberPrice" type="number" required min="0.01" step="0.01" class="<?= $prInput ?> font-mono">
                    </div>
                    <div>
                        <label for="prod-price" class="<?= $prLabel ?>">Regular price (₱) *</label>
                        <input id="prod-price" name="price" type="number" required min="0.01" step="0.01" class="<?= $prInput ?> font-mono">
                    </div>
                </div>
                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label for="prod-points" class="<?= $prLabel ?>">Points</label>
                        <input id="prod-points" name="points" type="number" min="0" step="1" value="0" class="<?= $prInput ?> font-mono">
                    </div>
                    <div>
                        <label for="prod-stock" class="<?= $prLabel ?>">Stock</label>
                        <input id="prod-stock" name="stock" type="number" min="0" step="1" value="0" class="<?= $prInput ?> font-mono">
                    </div>
                    <div>
                        <label for="prod-sort" class="<?= $prLabel ?>">Order in shop</label>
                        <input id="prod-sort" name="sortOrder" type="number" step="1" value="0" title="Lower numbers come first" class="<?= $prInput ?> font-mono">
                    </div>
                </div>
                <div>
                    <label for="prod-description" class="<?= $prLabel ?>">Description</label>
                    <textarea id="prod-description" name="description" rows="3" maxlength="2000" class="<?= $prInput ?>"></textarea>
                </div>
                <label class="flex items-center gap-2 text-zinc-300">
                    <input type="checkbox" name="active" value="1" checked class="evo-check">
                    <span>Show in the Member Shop</span>
                </label>
                <p data-prod-error hidden role="alert" class="p-3 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-300"></p>
                <button type="submit" class="w-full py-2.5 bg-[#f3bd35] hover:bg-[#ffda72] text-black font-extrabold rounded-xl text-xs flex items-center justify-center gap-1.5 disabled:opacity-60"><?= icon('save', 'w-3.5 h-3.5') ?><span>Save product</span></button>
            </form>
        </div>
    </div>
</div>
