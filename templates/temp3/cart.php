<?php
/**
 * TEMP3 — Cart
 * View selected products, update quantity, remove items.
 *
 * Engine:
 * - Uses session cart structure handled by index.php (POST page=cart)
 */

$store_id = StoreContext::getId();

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$items = $_SESSION['cart'];

$database = new Database();
$conn     = $database->getConnection();

// Currency
$currency       = $settings->getSetting('currency', 'USD');
$customCurrency = $settings->getSetting('custom_currency', '');
if ($currency === 'CUSTOM' && !empty($customCurrency)) {
    $currency_symbol = $customCurrency;
} else {
    $currency_symbol = $currency === 'USD' ? '$'
        : ($currency === 'EUR' ? '€'
        : ($currency === 'GBP' ? '£'
        : ($currency === 'TND' ? 'TND' : $currency . ' ')));
}
$currency_position = $settings->getSetting('currency_position', 'left');

$formatPrice = function (float $amount) use ($currency_symbol, $currency_position): string {
    return $currency_position === 'left'
        ? $currency_symbol . number_format($amount, 2)
        : number_format($amount, 2) . ' ' . $currency_symbol;
};

// Build product and variant lookup
$cart_products = [];
$subtotal = 0.0;

if (!empty($items)) {
    $productIds = [];
    $variantIds = [];
    foreach ($items as $key => $item) {
        $productIds[] = (int)($item['product_id'] ?? 0);
        if (!empty($item['variant_id'])) {
            $variantIds[] = (int)$item['variant_id'];
        }
    }
    $productIds = array_values(array_unique(array_filter($productIds)));
    $variantIds = array_values(array_unique(array_filter($variantIds)));

    $productsById = [];
    if (!empty($productIds)) {
        $placeholders = implode(',', array_fill(0, count($productIds), '?'));
        $stmt = $conn->prepare("SELECT id, name, sku, price, sale_price FROM products WHERE id IN ($placeholders) AND store_id = ? AND is_active = 1");
        $stmt->execute(array_merge($productIds, [$store_id]));
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $p) {
            $productsById[(int)$p['id']] = $p;
        }
    }

    $variantsById = [];
    if (!empty($variantIds)) {
        $placeholders = implode(',', array_fill(0, count($variantIds), '?'));
        $stmt = $conn->prepare("SELECT id, product_id, label, price, stock_quantity FROM product_variants WHERE id IN ($placeholders) AND store_id = ?");
        $stmt->execute(array_merge($variantIds, [$store_id]));
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $v) {
            $variantsById[(int)$v['id']] = $v;
        }
    }

    foreach ($items as $key => $item) {
        $pid = (int)($item['product_id'] ?? 0);
        if ($pid <= 0) continue;
        $product = $productsById[$pid] ?? null;
        if (!$product) continue;

        $qty = max(1, (int)($item['quantity'] ?? 1));
        $variant = !empty($item['variant_id']) ? ($variantsById[(int)$item['variant_id']] ?? null) : null;
        $unit = $variant && $variant['price'] !== null ? (float)$variant['price'] : (float)($product['sale_price'] ?: $product['price']);
        $lineTotal = $unit * $qty;
        $subtotal += $lineTotal;

        $cart_products[] = [
            'key' => $key,
            'product' => $product,
            'variant' => $variant,
            'variant_label' => $item['variant_label'] ?? ($variant['label'] ?? null),
            'quantity' => $qty,
            'unit_price' => $unit,
            'total' => $lineTotal
        ];
    }
}

$tax_rate = (float)$settings->getSetting('tax_rate', 0);
$shipping_price = (float)$settings->getSetting('shipping_price', 0);
$tax_amount = $subtotal * ($tax_rate / 100.0);
$grand_total = $subtotal + $tax_amount + $shipping_price;
?>

<section class="py-4 md:py-8">
    <div class="flex items-end justify-between gap-4 mb-4 md:mb-5">
        <div>
            <h1 class="text-lg md:text-2xl font-semibold tracking-tight text-brand-900">Your cart</h1>
            <p class="text-xs text-brand-400 mt-1">Review items, update quantity, or remove products.</p>
        </div>
        <a href="index.php?page=shop" class="text-[11px] font-medium text-brand-500 hover:text-brand-800 transition">
            Continue shopping
        </a>
    </div>

    <?php if (!empty($cart_products)): ?>
        <div class="grid grid-cols-1 lg:grid-cols-[1fr_320px] gap-4 lg:gap-6">
            <!-- Order summary first on mobile so Total is visible without scrolling -->
            <aside class="order-1 lg:order-2 rounded-2xl border border-brand-100 bg-white/85 p-4 h-fit">
                <h2 class="text-sm font-semibold text-brand-900 mb-3">Order summary</h2>
                <div class="space-y-2 text-xs text-brand-500">
                    <div class="flex items-center justify-between">
                        <span>Subtotal</span><span class="text-brand-900 font-medium"><?php echo $formatPrice($subtotal); ?></span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span>Tax</span><span class="text-brand-900 font-medium"><?php echo $formatPrice($tax_amount); ?></span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span>Shipping</span><span class="text-brand-900 font-medium"><?php echo $formatPrice($shipping_price); ?></span>
                    </div>
                    <div class="border-t border-brand-100 pt-3 mt-3 flex items-center justify-between">
                        <span class="text-brand-700 font-semibold">Total</span>
                        <span class="text-brand-900 font-semibold"><?php echo $formatPrice($grand_total); ?></span>
                    </div>
                </div>

                <div class="mt-4 space-y-2">
                    <a href="index.php?page=checkout"
                       class="inline-flex w-full items-center justify-center rounded-full bg-black text-white px-5 py-2.5 text-xs font-medium shadow-soft hover:bg-brand-900 transition">
                        Proceed to checkout
                    </a>
                    <a href="index.php?page=shop"
                       class="inline-flex w-full items-center justify-center rounded-full border border-brand-200 px-5 py-2.5 text-xs font-medium text-brand-700 hover:bg-brand-50 transition">
                        Continue shopping
                    </a>
                </div>
            </aside>

            <div class="order-2 lg:order-1 space-y-3">
                <?php foreach ($cart_products as $item): ?>
                    <?php
                    $p = $item['product'];
                    $pid = (int)$p['id'];
                    $img = ImageHelper::getProductImage($pid);
                    ?>
                    <div class="rounded-2xl border border-brand-100 bg-white/80 p-3 sm:p-4 flex gap-3">
                        <a href="index.php?page=product_view&id=<?php echo $pid; ?>" class="h-20 w-20 rounded-xl overflow-hidden bg-brand-100 shrink-0">
                            <img src="<?php echo htmlspecialchars($img); ?>"
                                 alt="<?php echo htmlspecialchars($p['name']); ?>"
                                 class="h-full w-full object-cover rounded-xl"
                                 onerror="this.src='<?php echo htmlspecialchars(ImageHelper::getPlaceholder()); ?>';">
                        </a>

                        <div class="flex-1 min-w-0">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <a href="index.php?page=product_view&id=<?php echo $pid; ?>"
                                       class="text-sm font-semibold text-brand-900 hover:text-brand-700 transition line-clamp-2">
                                        <?php echo htmlspecialchars($p['name']); ?>
                                    </a>
                                    <?php if (!empty($item['variant_label'])): ?>
                                        <div class="text-[11px] text-brand-400 mt-1">
                                            Variant: <?php echo htmlspecialchars($item['variant_label']); ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="text-[11px] text-brand-400 mt-1">
                                        <?php echo htmlspecialchars($p['sku'] ?? ''); ?>
                                    </div>
                                </div>

                                <div class="text-right shrink-0">
                                    <div class="text-sm font-semibold text-brand-900">
                                        <?php echo $formatPrice((float)$item['total']); ?>
                                    </div>
                                    <div class="text-[11px] text-brand-400">
                                        <?php echo $formatPrice((float)$item['unit_price']); ?> each
                                    </div>
                                </div>
                            </div>

                            <div class="mt-3 flex items-center justify-between gap-3">
                                <form method="POST" action="index.php?page=cart" class="flex items-center gap-2">
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="key" value="<?php echo htmlspecialchars($item['key']); ?>">
                                    <label class="text-[11px] font-medium text-brand-400 uppercase tracking-[0.18em]">Qty</label>
                                    <input type="number"
                                           name="quantity"
                                           min="1"
                                           max="99"
                                           value="<?php echo (int)$item['quantity']; ?>"
                                           class="w-20 rounded-full border border-brand-200 bg-white px-3 py-1.5 text-xs text-brand-900 focus:outline-none focus:ring-1 focus:ring-brand-300">
                                    <button type="submit"
                                            class="rounded-full border border-brand-200 px-3 py-1.5 text-[11px] font-medium text-brand-700 hover:bg-brand-50 transition">
                                        Update
                                    </button>
                                </form>

                                <form method="POST" action="index.php?page=cart">
                                    <input type="hidden" name="action" value="remove">
                                    <input type="hidden" name="key" value="<?php echo htmlspecialchars($item['key']); ?>">
                                    <button type="submit"
                                            class="rounded-full px-3 py-1.5 text-[11px] font-medium text-red-600 hover:bg-red-50 border border-red-100 transition">
                                        Remove
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <form method="POST" action="index.php?page=cart" class="pt-2">
                    <input type="hidden" name="action" value="clear">
                    <button type="submit"
                            class="text-[11px] font-medium text-brand-500 hover:text-brand-900 transition">
                        Clear cart
                    </button>
                </form>
            </div>
        </div>
    <?php else: ?>
        <div class="rounded-2xl border border-dashed border-brand-200 bg-white/70 px-5 py-10 text-center">
            <p class="text-sm font-medium text-brand-800 mb-1">Your cart is empty</p>
            <p class="text-xs text-brand-400 max-w-xs mx-auto mb-4">Add a product to start your order.</p>
            <a href="index.php?page=shop"
               class="inline-flex items-center justify-center rounded-full bg-black text-white px-5 py-2.5 text-xs font-medium shadow-soft hover:bg-brand-900 transition">
                Browse catalog
            </a>
        </div>
    <?php endif; ?>
</section>

