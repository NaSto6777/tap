<?php
// temp2 Product View: self-contained controller + view

require_once __DIR__ . '/../../config/plugin_helper.php';
require_once __DIR__ . '/../../config/analytics_helper.php';

$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($product_id <= 0) {
    header('Location: index.php?page=shop');
    exit;
}

$store_id = StoreContext::getId();
$db       = new Database();
$conn     = $db->getConnection();
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
$query = "SELECT p.*, c.name AS category_name
          FROM products p
          LEFT JOIN categories c ON p.category_id = c.id
          WHERE p.id = ? AND p.store_id = ? AND p.is_active = 1";
$stmt  = $conn->prepare($query);
$stmt->execute([$product_id, $store_id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    header('Location: index.php?page=shop');
    exit;
}

// Analytics: track view
$analytics = new AnalyticsHelper();
$analytics->trackProductView($product_id, session_id());

// Images
$main_image     = ImageHelper::getProductImage($product['id']);
$gallery_images = ImageHelper::getProductGallery($product['id']);
if (!is_array($gallery_images)) {
    $gallery_images = [];
}
if ($main_image && !in_array($main_image, $gallery_images, true)) {
    array_unshift($gallery_images, $main_image);
}

// Pricing
$basePrice = (float)$product['price'];
$salePrice = isset($product['sale_price']) && $product['sale_price'] !== '' ? (float)$product['sale_price'] : null;
$in_stock  = !isset($product['stock_quantity']) || (int)$product['stock_quantity'] > 0;
$stock_qty = (int)($product['stock_quantity'] ?? 99);

$displayPrice = $salePrice !== null && $salePrice > 0 ? $salePrice : $basePrice;

$formatPrice = function (float $amount) use ($currency_symbol, $currency_position): string {
    return $currency_position === 'left'
        ? $currency_symbol . number_format($amount, 2)
        : number_format($amount, 2) . ' ' . $currency_symbol;
};

// Related products (simple): same category then latest
$related_products = [];
if (!empty($product['category_id'])) {
    $q = "SELECT id, name, price, sale_price
          FROM products
          WHERE store_id = ? AND category_id = ? AND is_active = 1 AND id != ?
          ORDER BY created_at DESC
          LIMIT 4";
    $st = $conn->prepare($q);
    $st->execute([$store_id, $product['category_id'], $product_id]);
    $related_products = $st->fetchAll(PDO::FETCH_ASSOC);
}

if (count($related_products) < 4) {
    $q = "SELECT id, name, price, sale_price
          FROM products
          WHERE store_id = ? AND is_active = 1 AND id != ?
          ORDER BY created_at DESC
          LIMIT 4";
    $st = $conn->prepare($q);
    $st->execute([$store_id, $product_id]);
    $extra = $st->fetchAll(PDO::FETCH_ASSOC);
    foreach ($extra as $row) {
        $exists = false;
        foreach ($related_products as $rp) {
            if ((int)$rp['id'] === (int)$row['id']) {
                $exists = true;
                break;
            }
        }
        if (!$exists) {
            $related_products[] = $row;
            if (count($related_products) >= 4) {
                break;
            }
        }
    }
}
?>

<div class="container py-4 py-md-5">
    <!-- Breadcrumb -->
    <nav aria-label="Breadcrumb" class="breadcrumb-temp2 mb-3 mb-md-4">
        <a href="index.php">Home</a>
        <span class="sep" aria-hidden="true">/</span>
        <a href="index.php?page=shop">Shop</a>
        <?php if (!empty($product['category_name'])): ?>
            <span class="sep" aria-hidden="true">/</span>
            <span class="current"><?php echo htmlspecialchars($product['category_name']); ?></span>
        <?php endif; ?>
        <span class="sep" aria-hidden="true">/</span>
        <span class="current"><?php echo htmlspecialchars($product['name']); ?></span>
    </nav>

    <div class="product-view-layout">
        <!-- Left: gallery -->
        <div>
            <div class="gallery-main mb-3">
                <img
                    src="<?php echo htmlspecialchars($main_image); ?>"
                    alt="<?php echo htmlspecialchars($product['name']); ?>"
                    id="gallery-main-img"
                >
            </div>
            <?php if (count($gallery_images) > 1): ?>
                <div class="gallery-thumbs" role="list" aria-label="Product image gallery">
                    <?php foreach ($gallery_images as $index => $gimg): ?>
                        <button
                            class="gallery-thumb <?php echo $index === 0 ? 'active' : ''; ?>"
                            type="button"
                            data-src="<?php echo htmlspecialchars($gimg); ?>"
                            role="listitem"
                        >
                            <img
                                src="<?php echo htmlspecialchars($gimg); ?>"
                                alt="<?php echo htmlspecialchars($product['name']); ?> thumbnail <?php echo $index + 1; ?>"
                            >
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Right: details -->
        <div>
            <h1 class="product-detail-title mb-2"><?php echo htmlspecialchars($product['name']); ?></h1>
            <?php if (!empty($product['short_description'])): ?>
                <p class="text-secondary mb-3">
                    <?php echo nl2br(htmlspecialchars($product['short_description'])); ?>
                </p>
            <?php endif; ?>

            <div class="product-detail-price mb-3">
                <span class="h4 mb-0 text-white"><?php echo $formatPrice($displayPrice); ?></span>
                <?php if ($salePrice !== null && $salePrice > 0): ?>
                    <span class="text-secondary text-decoration-line-through small ms-2">
                        <?php echo $formatPrice($basePrice); ?>
                    </span>
                <?php endif; ?>
            </div>

            <div class="product-meta-row mb-3">
                <div class="product-meta-item">
                    <div class="meta-key">Availability</div>
                    <div class="meta-val" style="color:<?php echo $in_stock ? '#22c55e' : '#f97373'; ?>;">
                        <?php echo $in_stock ? ($stock_qty <= 5 ? 'Only ' . $stock_qty . ' left' : 'In stock') : 'Out of stock'; ?>
                    </div>
                </div>
            </div>

            <form
                method="POST"
                action="index.php?page=cart"
                onsubmit="event.preventDefault(); if (window.sfAddToCart) { sfAddToCart(this); } else { this.submit(); }"
                class="mt-3"
            >
                <input type="hidden" name="product_id" value="<?php echo (int)$product['id']; ?>">
                <input type="hidden" name="action" value="add">

                <div class="d-flex align-items-center gap-3 flex-wrap mb-3">
                    <div class="qty-control" role="group" aria-label="Quantity">
                        <button type="button" class="qty-btn" data-dir="down">−</button>
                        <input
                            type="number"
                            name="quantity"
                            class="qty-input"
                            value="1"
                            min="1"
                            max="<?php echo $stock_qty > 0 ? $stock_qty : 99; ?>"
                        >
                        <button type="button" class="qty-btn" data-dir="up">+</button>
                    </div>

                    <button type="submit" class="sf-btn-primary" <?php echo $in_stock ? '' : 'disabled'; ?>>
                        <i class="fas fa-bag-shopping me-2"></i>
                        <?php echo $in_stock ? 'Add to cart' : 'Out of stock'; ?>
                    </button>
                </div>
            </form>

            <?php if (!empty($product['description'])): ?>
                <div class="mt-4">
                    <h2 class="h6 text-white mb-2">Details</h2>
                    <div class="text-secondary small" style="line-height:1.8;">
                        <?php echo nl2br(htmlspecialchars($product['description'])); ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($related_products)): ?>
        <hr class="border-secondary-subtle my-5">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h5 text-white mb-0">You might also like</h2>
            <a href="index.php?page=shop" class="text-secondary small text-decoration-none">
                View all <i class="fas fa-arrow-right ms-1"></i>
            </a>
        </div>
        <div class="row g-3 g-md-4">
            <?php foreach ($related_products as $rp): ?>
                <?php
                $rpImage = ImageHelper::getProductImage((int)$rp['id']);
                $rpBase  = (float)$rp['price'];
                $rpSale  = isset($rp['sale_price']) && $rp['sale_price'] !== '' ? (float)$rp['sale_price'] : null;
                $rpDisplay = $rpSale !== null && $rpSale > 0 ? $rpSale : $rpBase;
                ?>
                <div class="col-6 col-md-3">
                    <article class="sf-card h-100 d-flex flex-column">
                        <a href="index.php?page=product_view&id=<?php echo (int)$rp['id']; ?>" class="text-decoration-none text-reset">
                            <div class="ratio ratio-4x5 rounded-4 overflow-hidden mb-2">
                                <img
                                    src="<?php echo htmlspecialchars($rpImage); ?>"
                                    alt="<?php echo htmlspecialchars($rp['name']); ?>"
                                    class="w-100 h-100 sf-skeleton-img"
                                    style="object-fit: cover;"
                                    onerror="this.src='<?php echo htmlspecialchars(ImageHelper::getPlaceholder()); ?>';"
                                >
                            </div>
                        </a>
                        <div class="px-3 pb-3 d-flex flex-column flex-grow-1">
                            <a href="index.php?page=product_view&id=<?php echo (int)$rp['id']; ?>" class="text-decoration-none">
                                <h3 class="h6 text-white mb-1 text-truncate"><?php echo htmlspecialchars($rp['name']); ?></h3>
                            </a>
                            <div class="mt-auto">
                                <span class="fw-semibold text-white"><?php echo $formatPrice($rpDisplay); ?></span>
                            </div>
                        </div>
                    </article>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

