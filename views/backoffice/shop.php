<?php
/**
 * Backoffice tab 6 — Member Shop (reference component "An").
 *
 * Sub-tabs store / cart / orders ([data-tab-group="shop"], URL param "shop").
 * Cart changes and checkout go through the JSON API (mlm.cart*, mlm.checkout)
 * and reload; after a checkout the page returns with ?shop=orders&order=<no.>
 * which renders the "Order Confirmed!" banner (auto-hidden after 4s, like the reference).
 * Paying: the member's MLM balance, or Maya Checkout (the only outside payment);
 * Maya sends the member back with ?maya=<rrn> ($shopMaya: paid, waiting or not paid).
 *
 * Uses: $account, $products, $cart, $orders, $shopMaya (see views/backoffice.php).
 */
$shopTabs = ['store', 'cart', 'orders'];
$shopTab = (string) ($_GET['shop'] ?? 'store');
if (!in_array($shopTab, $shopTabs, true)) {
    $shopTab = 'store';
}
// Not paid on Maya: back to the cart, which still holds the items.
if (($shopMaya['state'] ?? '') === 'failed') {
    $shopTab = 'cart';
}

$cartTotal = 0.0;   // v
$cartPoints = 0;    // y
$cartCount = 0;     // S
foreach ($cart as $line) {
    $cartTotal += $line['product']['memberPrice'] * $line['quantity'];
    $cartPoints += $line['product']['points'] * $line['quantity'];
    $cartCount += $line['quantity'];
}

// Order confirmation banner (only for an order that belongs to this account).
$confirmedOrder = null;
$orderParam = ($shopMaya['state'] ?? '') === 'paid' ? $shopMaya['orderNumber'] : (string) ($_GET['order'] ?? '');
if ($orderParam !== '') {
    foreach ($orders as $o) {
        if ($o['orderNumber'] === $orderParam) {
            $confirmedOrder = $o['orderNumber'];
            break;
        }
    }
}

$subOn = 'bg-gradient-to-r from-[#ffda72] to-[#f3bd35] text-black shadow-md';
$subOff = 'text-zinc-400 hover:text-white';
// The member's own balance, or Maya (card or Maya wallet on Maya's page): no other way to pay.
$shopMayaOn = maya_enabled();
$payMethods = [
    'balance' => ['on' => 'bg-[#18140c] border-[#f3bd35] text-[#ffda72] font-bold', 'icon' => 'wallet', 'iconCls' => 'w-4 h-4 mx-auto mb-1 text-[#f3bd35]', 'label' => 'Wallet Balance (' . peso($account['availableBalance']) . ')'],
    'maya'    => ['on' => 'bg-emerald-950/40 border-emerald-500 text-emerald-300 font-bold', 'icon' => 'credit-card', 'iconCls' => 'w-4 h-4 mx-auto mb-1 text-emerald-400', 'label' => $shopMayaOn ? 'Maya (card or wallet)' : 'Maya (' . lcfirst(MAYA_UNAVAILABLE) . ')'],
];
$payOff = 'bg-zinc-900 border-zinc-800 text-zinc-400';
$defaultAddress = '6th floor Frabelle Alabang Building, Madrigal Business Park, Ayala Alabang, Muntinlupa City, Metro Manila';
?>
<div class="space-y-6" id="bo-shop">
    <div class="flex items-center justify-between p-1.5 bg-[#121212] rounded-2xl border border-zinc-800 text-xs">
        <div class="flex items-center gap-1.5 overflow-x-auto" data-tab-group="shop" data-param="shop" role="tablist">
            <button type="button" data-tab="store" data-group="shop" data-on="<?= $subOn ?>" data-off="<?= $subOff ?>" class="flex items-center gap-2 px-4 py-2 rounded-xl font-bold transition-all <?= cls($shopTab === 'store', $subOn, $subOff) ?>">
                <?= icon('shopping-bag', 'w-4 h-4') ?>
                <span>Store Products</span>
            </button>
            <button type="button" data-tab="cart" data-group="shop" data-on="<?= $subOn ?>" data-off="<?= $subOff ?>" class="flex items-center gap-2 px-4 py-2 rounded-xl font-bold transition-all relative <?= cls($shopTab === 'cart', $subOn, $subOff) ?>">
                <?= icon('shopping-cart', 'w-4 h-4') ?>
                <span>Shopping Cart</span>
                <?php if ($cartCount > 0): ?>
                    <span class="px-1.5 py-0.2 rounded-full bg-emerald-500 text-black font-extrabold text-[10px]" data-cart-count><?= (int) $cartCount ?></span>
                <?php endif; ?>
            </button>
            <button type="button" data-tab="orders" data-group="shop" data-on="<?= $subOn ?>" data-off="<?= $subOff ?>" class="flex items-center gap-2 px-4 py-2 rounded-xl font-bold transition-all <?= cls($shopTab === 'orders', $subOn, $subOff) ?>">
                <?= icon('package', 'w-4 h-4') ?>
                <span>My Orders (<?= count($orders) ?>)</span>
            </button>
        </div>
        <div class="text-right hidden sm:block pr-3">
            <span class="text-[11px] text-zinc-400">Available Wallet: </span>
            <strong class="text-emerald-400 font-mono text-xs"><?= peso($account['availableBalance']) ?> PHP</strong>
        </div>
    </div>

    <?php if (($shopMaya['state'] ?? '') === 'pending'): ?>
        <div class="p-4 rounded-2xl bg-amber-950/40 border border-amber-600/40 text-amber-200 text-xs flex items-center gap-3" data-shop-maya-wait="<?= e($shopMaya['rrn']) ?>" role="status">
            <span class="w-4 h-4 shrink-0 rounded-full border-2 border-amber-300 border-t-transparent animate-spin"></span>
            <div>
                <span class="font-bold block">Waiting for Maya to confirm your payment (order <?= e($shopMaya['orderNumber']) ?>)</span>
                <span class="text-zinc-400">This page updates by itself. If you didn't finish paying, your cart still has your items.</span>
            </div>
        </div>
    <?php elseif (($shopMaya['state'] ?? '') === 'failed'): ?>
        <div class="p-4 rounded-2xl bg-rose-950/40 border border-rose-500/40 text-rose-200 text-xs flex items-center gap-3" data-shop-maya-result role="alert">
            <?= icon('circle-alert', 'w-5 h-5 shrink-0 text-rose-400') ?>
            <div>
                <span class="font-bold block">Order <?= e($shopMaya['orderNumber']) ?> wasn't paid</span>
                <span class="text-zinc-300"><?= e($shopMaya['message']) ?> Your cart still has your items, so you can try again.</span>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($confirmedOrder !== null): ?>
        <div class="p-4 rounded-2xl bg-emerald-950/40 border border-emerald-500/40 text-emerald-300 text-xs flex items-center justify-between" data-order-banner<?= $shopMaya ? ' data-shop-maya-result' : '' ?> role="status">
            <div class="flex items-center gap-2">
                <?= icon('circle-check', 'w-5 h-5 text-emerald-400') ?>
                <div>
                    <span class="font-bold block">Order Confirmed! Ref #<?= e($confirmedOrder) ?></span>
                    <span class="text-zinc-400">Your order has been paid and queued for logistics dispatch.</span>
                </div>
            </div>
            <button type="button" data-shop-go="orders" class="px-3 py-1 bg-emerald-500 text-black font-bold text-xs rounded-lg">
                View Orders
            </button>
        </div>
    <?php endif; ?>

    <?php /* Panels share one wrapper so the visible one never gets a space-y sibling margin (Tailwind v4 :not(:last-child)). */ ?>
    <div>
    <!-- Store -->
    <div class="space-y-4" data-tab-panel="store" data-group="shop"<?= $shopTab === 'store' ? '' : ' hidden' ?>>
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <div>
                <h3 class="text-base font-bold text-white flex items-center gap-2">
                    <?= icon('shopping-bag', 'w-5 h-5 text-[#f3bd35]') ?>
                    88MMM Executive Shop &amp; Product Points
                </h3>
                <p class="text-xs text-zinc-400 mt-0.5">
                    Order executive activation packages, replacement smart NFC hardware, and re-order health supplements with member discount pricing.
                </p>
            </div>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <?php foreach ($products as $p): ?>
                <div class="rounded-3xl bg-[#121212] border border-zinc-800 hover:border-[#f3bd35]/40 transition-all overflow-hidden shadow-xl flex flex-col justify-between">
                    <div>
                        <div class="h-44 w-full bg-zinc-950 overflow-hidden relative">
                            <img src="<?= e($p['image']) ?>" alt="<?= e($p['name']) ?>" referrerpolicy="no-referrer" loading="lazy" class="w-full h-full object-cover hover:scale-105 transition-transform duration-300">
                            <div class="absolute top-2.5 right-2.5 px-2 py-0.5 rounded-full bg-black/80 backdrop-blur-md border border-[#f3bd35]/40 text-[#ffda72] text-[10px] font-mono font-bold">
                                +<?= (int) $p['points'] ?> pts
                            </div>
                            <div class="absolute top-2.5 left-2.5 px-2 py-0.5 rounded-full bg-zinc-900/90 text-zinc-300 text-[9px] uppercase font-bold">
                                <?= e($p['category']) ?>
                            </div>
                        </div>
                        <div class="p-4 space-y-2">
                            <h4 class="text-sm font-bold text-white line-clamp-1"><?= e($p['name']) ?></h4>
                            <p class="text-[11px] text-zinc-400 line-clamp-2 leading-relaxed"><?= e($p['description']) ?></p>
                            <div class="pt-2 flex items-baseline justify-between">
                                <div>
                                    <span class="text-[10px] text-zinc-500 uppercase block">Member Price</span>
                                    <span class="text-base font-black text-[#ffda72] font-mono"><?= peso($p['memberPrice']) ?></span>
                                </div>
                                <span class="text-[10px] text-zinc-500 line-through"><?= peso($p['price']) ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="p-4 pt-0">
                        <button type="button" data-cart-add="<?= e($p['id']) ?>" class="w-full py-2.5 rounded-xl bg-zinc-900 hover:bg-[#f3bd35] hover:text-black border border-zinc-700 text-zinc-200 font-bold text-xs transition-all flex items-center justify-center gap-1.5 shadow">
                            <?= icon('plus', 'w-3.5 h-3.5') ?>
                            <span>Add to Cart</span>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Cart -->
    <div class="max-w-3xl mx-auto space-y-6" data-tab-panel="cart" data-group="shop"<?= $shopTab === 'cart' ? '' : ' hidden' ?>>
        <div class="p-5 rounded-3xl bg-[#121212] border border-zinc-800 shadow-xl space-y-4">
            <h3 class="text-base font-bold text-white flex items-center justify-between">
                <span class="flex items-center gap-2">
                    <?= icon('shopping-cart', 'w-5 h-5 text-[#f3bd35]') ?>
                    Your Executive Cart (<?= (int) $cartCount ?> items)
                </span>
                <button type="button" data-shop-go="store" class="text-xs text-[#f3bd35] hover:underline font-semibold">
                    ← Back to Store
                </button>
            </h3>
            <?php if (!$cart): ?>
                <div class="py-12 text-center text-zinc-500 text-xs">Your cart is currently empty. Visit the Store Products to add items.</div>
            <?php else: ?>
                <form id="shop-checkout-form" class="space-y-4">
                    <div class="divide-y divide-zinc-800">
                        <?php foreach ($cart as $line): $cp = $line['product']; ?>
                            <div class="py-3 flex items-center justify-between gap-4 text-xs">
                                <div class="flex items-center gap-3">
                                    <img src="<?= e($cp['image']) ?>" alt="<?= e($cp['name']) ?>" referrerpolicy="no-referrer" class="w-12 h-12 rounded-xl object-cover bg-zinc-950">
                                    <div>
                                        <div class="font-bold text-white"><?= e($cp['name']) ?></div>
                                        <div class="text-[10px] text-zinc-400">
                                            <?= peso($cp['memberPrice']) ?> · +<?= (int) $cp['points'] ?> pts each
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3">
                                    <div class="flex items-center bg-zinc-900 border border-zinc-700 rounded-lg p-0.5">
                                        <button type="button" data-cart-qty="<?= e($cp['id']) ?>" data-qty="<?= (int) $line['quantity'] - 1 ?>" class="p-1 hover:text-white text-zinc-400" aria-label="Decrease quantity of <?= e($cp['name']) ?>">
                                            <?= icon('minus', 'w-3 h-3') ?>
                                        </button>
                                        <span class="px-2 font-mono text-white text-xs"><?= (int) $line['quantity'] ?></span>
                                        <button type="button" data-cart-qty="<?= e($cp['id']) ?>" data-qty="<?= (int) $line['quantity'] + 1 ?>" class="p-1 hover:text-white text-zinc-400" aria-label="Increase quantity of <?= e($cp['name']) ?>">
                                            <?= icon('plus', 'w-3 h-3') ?>
                                        </button>
                                    </div>
                                    <span class="font-mono font-bold text-white w-20 text-right">
                                        <?= peso($cp['memberPrice'] * $line['quantity']) ?>
                                    </span>
                                    <button type="button" data-cart-remove="<?= e($cp['id']) ?>" class="text-zinc-500 hover:text-rose-400 p-1" aria-label="Remove <?= e($cp['name']) ?>">
                                        <?= icon('trash-2', 'w-4 h-4') ?>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="p-4 rounded-2xl bg-zinc-950 border border-zinc-800 space-y-3 text-xs">
                        <div>
                            <label for="shop-address" class="block text-zinc-400 mb-1 font-semibold">Delivery Address *</label>
                            <input id="shop-address" name="address" type="text" required maxlength="255" value="<?= e($defaultAddress) ?>" class="w-full px-3 py-2 rounded-xl bg-zinc-900 border border-zinc-700 text-white focus:outline-none focus:border-[#f3bd35]">
                        </div>
                        <div>
                            <span class="block text-zinc-400 mb-1 font-semibold" id="shop-method-label">Payment Method *</span>
                            <input type="hidden" name="method" value="balance">
                            <div class="grid grid-cols-2 gap-2" role="radiogroup" aria-labelledby="shop-method-label">
                                <?php foreach ($payMethods as $key => $m): $on = $key === 'balance'; ?>
                                    <button type="button" data-pay-method="<?= e($key) ?>" data-on="<?= e($m['on']) ?>" data-off="<?= $payOff ?>" role="radio" aria-checked="<?= $on ? 'true' : 'false' ?>"<?= $key === 'maya' && !$shopMayaOn ? ' disabled' : '' ?> class="p-2.5 rounded-xl border text-center transition-all disabled:opacity-50 <?= e($on ? $m['on'] : $payOff) ?>">
                                        <?= icon($m['icon'], $m['iconCls']) ?>
                                        <span><?= e($m['label']) ?></span>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="pt-2 border-t border-zinc-800 flex justify-between items-center text-sm">
                            <div>
                                <span class="text-zinc-400 block text-xs">Total Points Earned:</span>
                                <strong class="text-emerald-400 font-mono">+<?= (int) $cartPoints ?> Points</strong>
                            </div>
                            <div class="text-right">
                                <span class="text-zinc-400 block text-xs">Total Amount:</span>
                                <strong class="text-white font-mono text-base"><?= peso($cartTotal) ?> PHP</strong>
                            </div>
                        </div>
                    </div>
                    <button type="submit" data-idle-text="<?= e('Complete Order (' . peso($cartTotal) . ')') ?>" data-label-balance="<?= e('Complete Order (' . peso($cartTotal) . ')') ?>" data-label-maya="<?= e('Continue to Maya (' . peso($cartTotal) . ')') ?>" class="w-full py-3.5 rounded-xl bg-gradient-to-r from-[#ffda72] to-[#f3bd35] text-black font-extrabold text-xs shadow-lg hover:brightness-105 active:scale-95 transition-all">
                        Complete Order (<?= peso($cartTotal) ?>)
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Orders -->
    <div class="space-y-4" data-tab-panel="orders" data-group="shop"<?= $shopTab === 'orders' ? '' : ' hidden' ?>>
        <div class="flex items-center justify-between">
            <h3 class="text-base font-bold text-white flex items-center gap-2">
                <?= icon('package', 'w-5 h-5 text-[#f3bd35]') ?>
                Order History &amp; Logistics Tracking
            </h3>
            <button type="button" data-shop-go="store" class="text-xs text-[#f3bd35] hover:underline font-semibold">
                + Place New Order
            </button>
        </div>
        <div class="rounded-3xl bg-[#121212] border border-zinc-800 overflow-hidden shadow-xl">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-zinc-900/80 border-b border-zinc-800 text-zinc-400 uppercase text-[10px] tracking-wider">
                        <tr>
                            <th class="py-3 px-4">Order #</th>
                            <th class="py-3 px-4">Date</th>
                            <th class="py-3 px-4">Items Ordered</th>
                            <th class="py-3 px-4">Points</th>
                            <th class="py-3 px-4">Payment Method</th>
                            <th class="py-3 px-4 text-right">Total</th>
                            <th class="py-3 px-4 text-right">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800/80">
                        <?php foreach ($orders as $o): ?>
                            <tr class="hover:bg-zinc-900/40 transition-colors">
                                <td class="py-3.5 px-4 font-mono font-bold text-white"><?= e($o['orderNumber']) ?></td>
                                <td class="py-3.5 px-4 text-zinc-400 text-[11px]"><?= e($o['date']) ?></td>
                                <td class="py-3.5 px-4 text-zinc-200"><?= e(implode(', ', array_map(fn ($i) => $i['name'] . ' (x' . $i['quantity'] . ')', $o['items']))) ?></td>
                                <td class="py-3.5 px-4 font-mono text-emerald-400 font-bold">+<?= (int) $o['totalPoints'] ?> pts</td>
                                <td class="py-3.5 px-4 text-zinc-400"><?= e($o['paymentMethod']) ?></td>
                                <td class="py-3.5 px-4 font-mono font-bold text-white text-right"><?= peso($o['totalAmount']) ?> PHP</td>
                                <td class="py-3.5 px-4 text-right">
                                    <span class="text-[9px] px-2.5 py-0.5 rounded-full bg-emerald-500/15 text-emerald-400 font-bold uppercase">
                                        <?= e($o['status']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    </div>
</div>
