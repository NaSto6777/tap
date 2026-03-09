<?php
/**
 * TEMP3 — Product View
 * Uses temp1 product engine, Tailwind design, sticky mobile CTA.
 */

require_once __DIR__ . '/../../config/plugin_helper.php';
require_once __DIR__ . '/../../config/analytics_helper.php';

$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($product_id <= 0) {
    header('Location: index.php?page=shop');
    exit();
}

$store_id    = StoreContext::getId();
$database    = new Database();
$conn        = $database->getConnection();
$pluginHelper = new PluginHelper();

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

// Product details (scoped)
$query = "SELECT p.*, GROUP_CONCAT(c.name) as categories
          FROM products p
          LEFT JOIN product_categories pc ON p.id = pc.product_id
          LEFT JOIN categories c ON pc.category_id = c.id
          WHERE p.id = ? AND p.store_id = ? AND p.is_active = 1";
$stmt = $conn->prepare($query);
$stmt->execute([$product_id, $store_id]);
if ($stmt->rowCount() === 0) {
    header('Location: index.php?page=shop');
    exit();
}
$product = $stmt->fetch(PDO::FETCH_ASSOC);

// Track product view
$analytics = new AnalyticsHelper();
$analytics->trackProductView($product_id, session_id());

// Variants
$vstmt = $conn->prepare("SELECT id, label, sku, price, stock_quantity, is_active FROM product_variants WHERE product_id = ? AND is_active = 1 ORDER BY id ASC");
$vstmt->execute([$product_id]);
$variants = $vstmt->fetchAll(PDO::FETCH_ASSOC);

// Images via ImageHelper
$main_image     = ImageHelper::getProductImage($product_id);
$gallery_images = ImageHelper::getProductGallery($product_id);

// Determine price
$basePrice = (float)$product['price'];
$salePrice = isset($product['sale_price']) && $product['sale_price'] !== '' ? (float)$product['sale_price'] : null;
$displayPrice = $salePrice !== null && $salePrice > 0 ? $salePrice : $basePrice;

$formatPrice = function (float $amount) use ($currency_symbol, $currency_position): string {
    return $currency_position === 'left'
        ? $currency_symbol . number_format($amount, 2)
        : number_format($amount, 2) . ' ' . $currency_symbol;
};

// Basic stock flag
$stockQty = (int)($product['stock_quantity'] ?? 0);
$hasStock = $stockQty > 0;
?>

<div class="py-6 sm:py-8">
    <!-- Breadcrumb -->
    <nav class="text-[11px] text-brand-400 mb-4 flex items-center gap-1">
        <a href="index.php" class="hover:text-brand-700">Home</a>
        <span>/</span>
        <a href="index.php?page=shop" class="hover:text-brand-700">Shop</a>
        <span>/</span>
        <span class="text-brand-700 truncate"><?php echo htmlspecialchars($product['name']); ?></span>
    </nav>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 md:gap-10 items-start">
        <!-- Gallery -->
        <div class="space-y-3">
            <div class="aspect-[4/5] rounded-3xl overflow-hidden bg-brand-100">
                <img
                    src="<?php echo htmlspecialchars($main_image); ?>"
                    alt="<?php echo htmlspecialchars($product['name']); ?>"
                    class="h-full w-full object-cover"
                    onerror="this.src='<?php echo htmlspecialchars(ImageHelper::getPlaceholder()); ?>';"
                >
            </div>
            <?php if (is_array($gallery_images) && count($gallery_images) > 1): ?>
                <div class="flex gap-2 overflow-x-auto">
                    <?php foreach ($gallery_images as $g): ?>
                        <button
                            type="button"
                            class="h-16 w-16 rounded-2xl overflow-hidden border border-brand-100 bg-brand-100 shrink-0"
                            onclick="document.getElementById('bt-main-img').src=this.querySelector('img').src"
                            style="display:none"
                        >
                            <img src="<?php echo htmlspecialchars($g); ?>" alt="" class="h-full w-full object-cover">
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Details -->
        <div class="space-y-4">
            <h1 class="text-2xl sm:text-3xl font-semibold tracking-tight text-brand-900">
                <?php echo htmlspecialchars($product['name']); ?>
            </h1>

            <?php if (!empty($product['short_description'])): ?>
                <p class="text-sm text-brand-500 max-w-md">
                    <?php echo htmlspecialchars($product['short_description']); ?>
                </p>
            <?php endif; ?>

            <div class="flex items-baseline gap-3">
                <span class="text-2xl font-semibold text-brand-900">
                    <?php echo $formatPrice($displayPrice); ?>
                </span>
                <?php if ($salePrice !== null && $salePrice > 0): ?>
                    <span class="text-xs text-brand-300 line-through">
                        <?php echo $formatPrice($basePrice); ?>
                    </span>
                <?php endif; ?>
            </div>

            <form
                method="POST"
                action="index.php?page=cart"
                class="space-y-4"
                onsubmit="event.preventDefault(); if (window.btAddToCart) { btAddToCart(this); } else { this.submit(); }"
            >
                <input type="hidden" name="product_id" value="<?php echo (int)$product['id']; ?>">
                <input type="hidden" name="action" value="add">

                <div class="flex items-center gap-3">
                    <label class="text-[11px] font-medium text-brand-500 uppercase tracking-[0.18em]">
                        Quantity
                    </label>
                    <div class="inline-flex items-center rounded-full border border-brand-200 bg-white px-1">
                        <button type="button" class="px-2 text-xs" onclick="
                          var input = this.parentElement.querySelector('input');
                          var v = parseInt(input.value || '1', 10);
                          if (v > 1) input.value = v - 1;
                        ">−</button>
                        <input
                            type="number"
                            name="quantity"
                            value="1"
                            min="1"
                            max="<?php echo max(1, $stockQty); ?>"
                            class="w-10 border-0 text-center text-xs focus:outline-none"
                        >
                        <button type="button" class="px-2 text-xs" onclick="
                          var input = this.parentElement.querySelector('input');
                          var v = parseInt(input.value || '1', 10);
                          var max = parseInt(input.getAttribute('max') || '99', 10);
                          if (v < max) input.value = v + 1;
                        ">+</button>
                    </div>
                    <span class="text-[11px] text-brand-400">
                        <?php echo $hasStock ? 'In stock' : 'Out of stock'; ?>
                    </span>
                </div>

                <?php if (!empty($product['description'])): ?>
                    <div class="border-t border-brand-100 pt-4">
                        <h2 class="text-xs font-semibold text-brand-800 mb-1.5 uppercase tracking-[0.18em]">
                            Details
                        </h2>
                        <div class="prose prose-sm max-w-none text-brand-500">
                            <?php echo nl2br(htmlspecialchars($product['description'])); ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Desktop add to cart -->
                <div class="hidden sm:block">
                    <button
                        type="submit"
                        class="inline-flex w-full items-center justify-center rounded-full bg-black text-white px-5 py-2.5 text-xs font-medium shadow-soft hover:bg-brand-900 transition disabled:opacity-40"
                        <?php echo $hasStock ? '' : 'disabled'; ?>
                    >
                        Add to cart
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Sticky mobile Add to Cart bar -->
<div class="fixed inset-x-0 bottom-0 z-40 sm:hidden bt-safe-area-bottom border-t border-brand-200 bg-white/95 backdrop-blur-sm">
    <form
        method="POST"
        action="index.php?page=cart"
        class="max-w-5xl mx-auto px-4 py-3 flex items-center gap-3"
        onsubmit="event.preventDefault(); if (window.btAddToCart) { btAddToCart(this); } else { this.submit(); }"
    >
        <input type="hidden" name="product_id" value="<?php echo (int)$product['id']; ?>">
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="quantity" value="1">

        <div class="flex-1 text-[11px] text-brand-500">
            <div class="truncate"><?php echo htmlspecialchars($product['name']); ?></div>
            <div class="font-semibold text-brand-900"><?php echo $formatPrice($displayPrice); ?></div>
        </div>

        <button
            type="submit"
            class="inline-flex flex-[1.2] items-center justify-center rounded-full bg-black text-white px-4 py-2.5 text-xs font-medium shadow-soft hover:bg-brand-900 transition disabled:opacity-40"
            <?php echo $hasStock ? '' : 'disabled'; ?>
        >
            Add to cart
        </button>
    </form>
</div>

