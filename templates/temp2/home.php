<?php
// temp2 Home: safe, DB-driven hero + product grid

$store_id = StoreContext::getId();
$db       = new Database();
$conn     = $db->getConnection();

// Currency formatting
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

// Pull some products for home (featured first, fallback to latest)
$stmt = $conn->prepare(
    "SELECT id, name, price, sale_price, short_description 
     FROM products 
     WHERE store_id = ? AND is_active = 1 
     ORDER BY featured DESC, created_at DESC 
     LIMIT 8"
);
$stmt->execute([$store_id]);
$homeProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Hero copy
$heroTitle    = $settings->getSetting('hero_title', 'Elevate your everyday essentials');
$heroSubtitle = $settings->getSetting('hero_subtitle', 'A curated selection of products designed for modern life.');
$heroButton   = $settings->getSetting('hero_button_text', 'Shop Now');
?>

<!-- Hero -->
<section class="sf-section py-5 py-md-6">
    <div class="container">
        <div class="row align-items-center g-4">
            <div class="col-lg-6">
                <span class="sf-pill mb-3 d-inline-flex align-items-center gap-2">
                    <span class="rounded-circle d-inline-block" style="width:8px;height:8px;background:#22c55e;"></span>
                    New collection · <?php echo date('Y'); ?>
                </span>
                <h1 class="display-5 fw-semibold text-white mb-3">
                    <?php echo htmlspecialchars($heroTitle); ?>
                </h1>
                <p class="lead text-secondary mb-4">
                    <?php echo htmlspecialchars($heroSubtitle); ?>
                </p>
                <div class="d-flex flex-wrap gap-3 align-items-center">
                    <a href="index.php?page=shop" class="sf-btn-primary">
                        <i class="fas fa-bag-shopping me-2"></i><?php echo htmlspecialchars($heroButton); ?>
                    </a>
                    <a href="index.php?page=about" class="sf-btn-outline">
                        <i class="fas fa-circle-info me-2"></i>Our story
                    </a>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="position-relative">
                    <div class="ratio ratio-4x3 rounded-4 overflow-hidden shadow-lg"
                         style="background: radial-gradient(circle at top, rgba(148,163,184,.25), transparent 55%), radial-gradient(circle at bottom, rgba(0,123,255,.4), transparent 60%);">
                        <div class="position-absolute top-50 start-50 translate-middle text-center px-4">
                            <p class="text-secondary mb-2 text-uppercase small" style="letter-spacing:.18em;">
                                Curated collections
                            </p>
                            <p class="h5 text-white fw-semibold mb-3">
                                Discover pieces that feel tailored to you.
                            </p>
                            <a href="index.php?page=shop" class="btn btn-outline-light btn-sm rounded-pill px-3">
                                Browse the catalog <i class="fas fa-arrow-right ms-2"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Featured / latest products -->
<section class="sf-section pt-0">
    <div class="container">
        <div class="d-flex justify-content-between align-items-end mb-3">
            <div>
                <h2 class="h4 text-white mb-1">Selected products</h2>
                <p class="text-secondary mb-0">
                    A small selection from this store’s catalog.
                </p>
            </div>
            <div>
                <a href="index.php?page=shop" class="text-secondary small text-decoration-none">
                    View all <i class="fas fa-arrow-right ms-1"></i>
                </a>
            </div>
        </div>

        <?php if (!empty($homeProducts)): ?>
            <div class="row g-3 g-md-4">
                <?php foreach ($homeProducts as $product): ?>
                    <?php
                    $imageUrl = ImageHelper::getProductImage((int)$product['id']);
                    $hasSale  = $product['sale_price'] !== null && $product['sale_price'] !== '' && (float)$product['sale_price'] > 0;
                    $basePrice = (float)$product['price'];
                    $salePrice = $hasSale ? (float)$product['sale_price'] : null;
                    $displayPrice = $hasSale ? $salePrice : $basePrice;

                    $priceFormatted = $currency_position === 'left'
                        ? $currency_symbol . number_format($displayPrice, 2)
                        : number_format($displayPrice, 2) . ' ' . $currency_symbol;
                    ?>
                    <div class="col-6 col-md-4 col-lg-3">
                        <article class="sf-card h-100 d-flex flex-column">
                            <a href="index.php?page=product_view&id=<?php echo (int)$product['id']; ?>" class="text-decoration-none text-reset">
                                <div class="ratio ratio-4x5 rounded-4 overflow-hidden mb-3">
                                    <img
                                        src="<?php echo htmlspecialchars($imageUrl); ?>"
                                        alt="<?php echo htmlspecialchars($product['name']); ?>"
                                        class="w-100 h-100 sf-skeleton-img"
                                        style="object-fit: cover;"
                                        onerror="this.src='<?php echo htmlspecialchars(ImageHelper::getPlaceholder()); ?>';"
                                    >
                                </div>
                            </a>
                            <div class="px-3 pb-3 d-flex flex-column flex-grow-1">
                                <a href="index.php?page=product_view&id=<?php echo (int)$product['id']; ?>" class="text-decoration-none">
                                    <h3 class="h6 text-white mb-1 text-truncate">
                                        <?php echo htmlspecialchars($product['name']); ?>
                                    </h3>
                                </a>
                                <?php if (!empty($product['short_description'])): ?>
                                    <p class="text-secondary small mb-2">
                                        <?php echo htmlspecialchars(mb_substr($product['short_description'], 0, 70)); ?>
                                        <?php echo mb_strlen($product['short_description']) > 70 ? '…' : ''; ?>
                                    </p>
                                <?php endif; ?>

                                <div class="mt-auto d-flex justify-content-between align-items-center">
                                    <div class="d-flex flex-column">
                                        <?php if ($hasSale): ?>
                                            <span class="text-secondary text-decoration-line-through small">
                                                <?php
                                                $originalFormatted = $currency_position === 'left'
                                                    ? $currency_symbol . number_format($basePrice, 2)
                                                    : number_format($basePrice, 2) . ' ' . $currency_symbol;
                                                echo $originalFormatted;
                                                ?>
                                            </span>
                                            <span class="fw-semibold text-white">
                                                <?php echo $priceFormatted; ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="fw-semibold text-white">
                                                <?php echo $priceFormatted; ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <form
                                        class="d-inline"
                                        method="POST"
                                        action="index.php?page=cart"
                                        onsubmit="event.preventDefault(); if (window.sfAddToCart) { sfAddToCart(this); } else { this.submit(); }"
                                    >
                                        <input type="hidden" name="product_id" value="<?php echo (int)$product['id']; ?>">
                                        <input type="hidden" name="quantity" value="1">
                                        <input type="hidden" name="action" value="add">
                                        <button type="submit" class="btn btn-sm btn-outline-light rounded-pill px-3">
                                            <i class="fas fa-plus me-1"></i> Add
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </article>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-5">
                <p class="text-secondary mb-2">No products yet.</p>
                <p class="text-secondary small mb-3">
                    As soon as this store adds products, they will appear here.
                </p>
                <a href="index.php?page=shop" class="sf-btn-outline">
                    Go to shop
                </a>
            </div>
        <?php endif; ?>
    </div>
</section>

