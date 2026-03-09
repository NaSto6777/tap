<?php
require_once __DIR__ . '/../../config/plugin_helper.php';
require_once __DIR__ . '/../../config/analytics_helper.php';

$product_id = $_GET['id'] ?? 0;

if (!$product_id) {
    header('Location: index.php?page=shop');
    exit();
}

$store_id = StoreContext::getId();
$database = new Database();
$conn = $database->getConnection();
$pluginHelper = new PluginHelper();

// Get currency settings
$currency = $settings->getSetting('currency', 'USD');
$currency_symbol = $currency === 'USD' ? '$' : ($currency === 'EUR' ? '€' : ($currency === 'GBP' ? '£' : ($currency === 'TND' ? 'TND' : $currency . ' ')));
$rightPositionCurrencies = ['TND','AED','SAR','QAR','KWD','BHD','OMR','MAD','DZD','EGP'];
$price_position_right = in_array($currency, $rightPositionCurrencies, true);
$price_prefix = $price_position_right ? '' : ($currency_symbol);
$price_suffix = $price_position_right ? (' ' . $currency_symbol) : '';

// Get product details (scoped to store)
$query = "SELECT p.*, GROUP_CONCAT(c.name) as categories
          FROM products p 
          LEFT JOIN product_categories pc ON p.id = pc.product_id
          LEFT JOIN categories c ON pc.category_id = c.id
          WHERE p.id = ? AND p.store_id = ? AND p.is_active = 1";
$stmt = $conn->prepare($query);
$stmt->execute([$product_id, $store_id]);

if ($stmt->rowCount() == 0) {
    header('Location: index.php?page=shop');
    exit();
}

$product = $stmt->fetch(PDO::FETCH_ASSOC);

// Track product view for analytics
if ($product) {
    $analytics = new AnalyticsHelper();
    $analytics->trackProductView($product_id, session_id());
}

// Get product variants
$query = "SELECT id, label, sku, price, stock_quantity, is_active FROM product_variants WHERE product_id = ? AND is_active = 1 ORDER BY id ASC";
$stmt = $conn->prepare($query);
$stmt->bindParam(1, $product_id);
$stmt->execute();
$variants = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get product images
$query = "SELECT * FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, sort_order ASC";
$stmt = $conn->prepare($query);
$stmt->bindParam(1, $product_id);
$stmt->execute();
$images = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get related products (scoped to store)
$query = "SELECT p.*, pi.image_path as main_image
          FROM products p 
          LEFT JOIN product_categories pc ON p.id = pc.product_id
          LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = 1
          WHERE p.store_id = ? AND pc.category_id IN (
              SELECT category_id FROM product_categories WHERE product_id = ?
          ) AND p.id != ? AND p.is_active = 1
          ORDER BY p.created_at DESC
          LIMIT 4";
$stmt = $conn->prepare($query);
$stmt->execute([$store_id, $product_id, $product_id]);
$related_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container py-5">
    <div class="row">
        <div class="col-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                    <li class="breadcrumb-item"><a href="index.php?page=shop">Shop</a></li>
                    <li class="breadcrumb-item active"><?php echo htmlspecialchars($product['name']); ?></li>
                </ol>
            </nav>
        </div>
    </div>
    
    <div class="row">
        <div class="col-lg-6">
            <!-- Product Images -->
            <?php
            // Check for main product image
            $main_image = "uploads/products/{$product_id}/main.jpg";
            $has_main = file_exists($main_image);
            
            // Get additional images from directory
            $image_dir = "uploads/products/{$product_id}/";
            $product_images = [];
            if (is_dir($image_dir)) {
                $files = glob($image_dir . "*.{jpg,jpeg,png,gif,webp}", GLOB_BRACE);
                foreach ($files as $file) {
                    if (basename($file) !== 'main.jpg') {
                        $product_images[] = $file;
                    }
                }
            }
            // Put main image first
            if ($has_main) {
                array_unshift($product_images, $main_image);
            }
            ?>
            
            <?php if (!empty($product_images)): ?>
                <div id="productCarousel" class="carousel slide" data-bs-ride="carousel">
                    <div class="carousel-indicators">
                        <?php foreach ($product_images as $index => $image): ?>
                            <button type="button" data-bs-target="#productCarousel" data-bs-slide-to="<?php echo $index; ?>" 
                                    <?php echo $index == 0 ? 'class="active"' : ''; ?>></button>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="carousel-inner">
                        <?php foreach ($product_images as $index => $image): ?>
                            <div class="carousel-item <?php echo $index == 0 ? 'active' : ''; ?>">
                                <img src="<?php echo $image; ?>" class="d-block w-100" 
                                     alt="<?php echo htmlspecialchars($product['name']); ?>"
                                     onerror="this.src='uploads/placeholder.jpg'"
                                     style="height: 500px; object-fit: cover; border-radius: 10px;">
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <button class="carousel-control-prev" type="button" data-bs-target="#productCarousel" data-bs-slide="prev">
                        <span class="carousel-control-prev-icon"></span>
                    </button>
                    <button class="carousel-control-next" type="button" data-bs-target="#productCarousel" data-bs-slide="next">
                        <span class="carousel-control-next-icon"></span>
                    </button>
                </div>
            <?php else: ?>
                <img src="uploads/placeholder.jpg" class="img-fluid" alt="<?php echo htmlspecialchars($product['name']); ?>"
                     style="height: 500px; width: 100%; object-fit: cover; border-radius: 10px;">
            <?php endif; ?>
        </div>
        
        <div class="col-lg-6">
            <h1 class="mb-3"><?php echo htmlspecialchars($product['name']); ?></h1>
            
            <?php $categoriesEnabled = $settings->getSetting('categories_enabled', '1'); ?>
            <?php if ($categoriesEnabled === '1' && !empty($product['categories'])): ?>
                <p class="text-muted mb-3">
                    <i class="fas fa-tags"></i> 
                    Categories: <?php echo htmlspecialchars($product['categories']); ?>
                </p>
            <?php endif; ?>
            
            <div class="mb-3">
                <?php if (!empty($variants)): ?>
                    <span class="h5 text-muted">From</span>
                    <?php
                        $variantPrices = array_map(function($v){ return $v['price'] !== null ? (float)$v['price'] : null; }, $variants);
                        $variantPrices = array_filter($variantPrices, function($p){ return $p !== null; });
                        $minVariantPrice = !empty($variantPrices) ? min($variantPrices) : null;
                    ?>
                    <?php if ($minVariantPrice !== null): ?>
                        <span class="h3 text-primary"><?php echo $price_prefix; ?><?php echo number_format($minVariantPrice, 2); ?><?php echo $price_suffix; ?></span>
                    <?php else: ?>
                        <span class="h3 text-primary"><?php echo $price_prefix; ?><?php echo number_format($product['sale_price'] ?: $product['price'], 2); ?><?php echo $price_suffix; ?></span>
                    <?php endif; ?>
                <?php else: ?>
                    <?php if ($product['sale_price']): ?>
                        <span class="h4 text-muted text-decoration-line-through me-2"><?php echo $price_prefix; ?><?php echo number_format($product['price'], 2); ?><?php echo $price_suffix; ?></span>
                        <span class="h3 text-danger"><?php echo $price_prefix; ?><?php echo number_format($product['sale_price'], 2); ?><?php echo $price_suffix; ?></span>
                        <span class="badge bg-danger ms-2">
                            <?php echo round((($product['price'] - $product['sale_price']) / $product['price']) * 100); ?>% OFF
                        </span>
                    <?php else: ?>
                        <span class="h3 text-primary"><?php echo $price_prefix; ?><?php echo number_format($product['price'], 2); ?><?php echo $price_suffix; ?></span>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($product['short_description'])): ?>
                <p class="lead"><?php echo htmlspecialchars($product['short_description']); ?></p>
            <?php endif; ?>
            
            <?php if (!empty($product['description'])): ?>
                <div class="mb-4">
                    <h5>Description</h5>
                    <p><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
                </div>
            <?php endif; ?>
            
            <div class="mb-4">
                <h5>Product Details</h5>
                <ul class="list-unstyled">
                    <li><strong>SKU:</strong> <?php echo htmlspecialchars($product['sku']); ?></li>
                    <li><strong>Stock:</strong> 
                        <span class="<?php echo $product['stock_quantity'] > 0 ? 'text-success' : 'text-danger'; ?>">
                            <?php echo $product['stock_quantity'] > 0 ? $product['stock_quantity'] . ' in stock' : 'Out of stock'; ?>
                        </span>
                    </li>
                </ul>
            </div>
            
            <?php
                $hasStock = $product['stock_quantity'] > 0;
                if (!empty($variants)) {
                    // If any variant has stock, allow purchase
                    foreach ($variants as $v) {
                        if ((int)$v['stock_quantity'] > 0) { $hasStock = true; break; }
                    }
                }

                // Determine sensible max quantity, taking variants into account
                $qtyMax = (int)$product['stock_quantity'];
                if (!empty($variants)) {
                    $qtyMax = 0;
                    foreach ($variants as $v) {
                        $qtyMax += (int)($v['stock_quantity'] ?? 0);
                    }
                    if ($qtyMax <= 0) {
                        $qtyMax = 99;
                    }
                }
            ?>
            <?php if ($hasStock): ?>
                <form method="POST" action="index.php?page=cart">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                    <?php if (!empty($variants)): ?>
                        <div class="mb-3">
                            <label for="variant_id" class="form-label">Choose an option</label>
                            <select class="form-select" id="variant_id" name="variant_id" required>
                                <option value="" disabled selected>Select</option>
                                <?php foreach ($variants as $variant): ?>
                                    <option value="<?php echo $variant['id']; ?>" 
                                            data-price="<?php echo htmlspecialchars($variant['price'] ?? ''); ?>"
                                            data-label="<?php echo htmlspecialchars($variant['label']); ?>"
                                            <?php echo ((int)$variant['stock_quantity'] <= 0 ? 'disabled' : ''); ?>>
                                        <?php echo htmlspecialchars($variant['label']); ?>
                                        <?php if ($variant['price'] !== null): ?> - <?php echo $currency_symbol; ?><?php echo number_format($variant['price'], 2); ?><?php endif; ?>
                                        <?php echo ((int)$variant['stock_quantity'] <= 0 ? ' (Out of stock)' : ''); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <input type="hidden" name="variant_label" id="variant_label_input" value="">
                    <?php endif; ?>
                    
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="quantity" class="form-label">Quantity</label>
                            <input type="number" class="form-control" id="quantity" name="quantity" 
                                   value="1" min="1" max="<?php echo $qtyMax; ?>">
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-cart-plus"></i> Add to Cart
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-lg" onclick="history.back()">
                            <i class="fas fa-arrow-left"></i> Back to Shop
                        </button>
                    </div>
                </form>
            <?php else: ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> This product is currently out of stock.
                </div>
                <button type="button" class="btn btn-outline-secondary btn-lg" onclick="history.back()">
                    <i class="fas fa-arrow-left"></i> Back to Shop
                </button>
            <?php endif; ?>
        </div>
    </div>
    
            <?php if ($settings->getSetting('categories_enabled', '1') === '1' && !empty($related_products)): ?>
        <div class="row mt-5">
            <div class="col-12">
                <h3 class="mb-4">Related Products</h3>
            </div>
            <?php foreach ($related_products as $related): ?>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="card h-100">
                        <?php
                        $related_image = "uploads/products/{$related['id']}/main.jpg";
                        $related_image_exists = file_exists($related_image);
                        $related_display = $related_image_exists ? $related_image : 'uploads/placeholder.jpg';
                        ?>
                        <img src="<?php echo $related_display; ?>" loading="lazy"
                             class="card-img-top" alt="<?php echo htmlspecialchars($related['name']); ?>"
                             onerror="this.src='uploads/placeholder.jpg'"
                             style="height: 250px; object-fit: cover;">
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title"><?php echo htmlspecialchars($related['name']); ?></h5>
                            <p class="card-text"><?php echo htmlspecialchars(substr($related['short_description'] ?? '', 0, 100)); ?><?php echo strlen($related['short_description'] ?? '') > 100 ? '...' : ''; ?></p>
                            <div class="mt-auto">
                                <div class="d-flex justify-content-between align-items-center">
                                    <?php if ($related['sale_price']): ?>
                                        <div>
                                            <span class="text-muted text-decoration-line-through small"><?php echo $currency_symbol; ?><?php echo number_format($related['price'], 2); ?></span>
                                            <span class="h5 text-danger"><?php echo $currency_symbol; ?><?php echo number_format($related['sale_price'], 2); ?></span>
                                        </div>
                                    <?php else: ?>
                                        <span class="h5 text-primary"><?php echo $currency_symbol; ?><?php echo number_format($related['price'], 2); ?></span>
                                    <?php endif; ?>
                                    <a href="index.php?page=product_view&id=<?php echo $related['id']; ?>" 
                                       class="btn btn-primary">View</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<style>
.carousel-item img {
    border-radius: 10px;
}

.card {
    transition: transform 0.3s ease;
}

.card:hover {
    transform: translateY(-5px);
}
</style>

<?php if (!empty($variants)): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const variantSelect = document.getElementById('variant_id');
    const labelInput = document.getElementById('variant_label_input');
    if (variantSelect) {
        variantSelect.addEventListener('change', function () {
            const selectedOption = variantSelect.options[variantSelect.selectedIndex];
            const label = selectedOption.getAttribute('data-label') || '';
            labelInput.value = label;
        });
    }
});

// Analytics tracking
<?php if ($pluginHelper->isPluginActive('google_analytics') || $pluginHelper->isPluginActive('facebook_pixel')): ?>
// Track product view
<?php if ($pluginHelper->isPluginActive('google_analytics')): ?>
gtag('event', 'view_item', {
    currency: '<?php echo $currency; ?>',
    value: <?php echo $product['sale_price'] ?: $product['price']; ?>,
    items: [{
        item_id: '<?php echo $product['id']; ?>',
        item_name: '<?php echo addslashes($product['name']); ?>',
        category: '<?php echo addslashes($product['categories'] ?? ''); ?>',
        price: <?php echo $product['sale_price'] ?: $product['price']; ?>,
        quantity: 1
    }]
});
<?php endif; ?>

<?php if ($pluginHelper->isPluginActive('facebook_pixel')): ?>
fbq('track', 'ViewContent', {
    content_type: 'product',
    content_ids: ['<?php echo $product['id']; ?>'],
    content_name: '<?php echo addslashes($product['name']); ?>',
    value: <?php echo $product['sale_price'] ?: $product['price']; ?>,
    currency: '<?php echo $currency; ?>'
});
<?php endif; ?>
<?php endif; ?>
</script>

<script>
// Track product view with JavaScript
document.addEventListener('DOMContentLoaded', function() {
    if (typeof Analytics !== 'undefined') {
        Analytics.trackProductView(<?php echo $product_id; ?>, '<?php echo addslashes($product['name']); ?>');
    }
});
</script>
<?php endif; ?>
