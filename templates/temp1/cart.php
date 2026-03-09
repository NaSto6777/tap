<?php
require_once __DIR__ . '/../../config/plugin_helper.php';
require_once __DIR__ . '/../../config/StoreContext.php';

$store_id = StoreContext::getId();

// Initialize cart if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$pluginHelper = new PluginHelper();

// Get cart products
$cart_products = [];
$total = 0;

if (!empty($_SESSION['cart'])) {
    $database = new Database();
    $conn = $database->getConnection();
    
    $items = $_SESSION['cart'];
    $productIds = [];
    $variantIds = [];
    foreach ($items as $key => $item) {
        $productIds[] = (int)$item['product_id'];
        if (!empty($item['variant_id'])) { $variantIds[] = (int)$item['variant_id']; }
    }
    $productIds = array_unique($productIds);
    $variantIds = array_unique($variantIds);

    if (!empty($productIds)) {
        $placeholders = implode(',', array_fill(0, count($productIds), '?'));
        $query = "SELECT p.*, pi.image_path as main_image FROM products p LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = 1 WHERE p.id IN ($placeholders) AND p.store_id = ? AND p.is_active = 1";
        $stmt = $conn->prepare($query);
        $stmt->execute(array_merge(array_values($productIds), [$store_id]));
        $products = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $p) { $products[$p['id']] = $p; }
    }

    $variants = [];
    if (!empty($variantIds)) {
        $placeholders = implode(',', array_fill(0, count($variantIds), '?'));
        $vstmt = $conn->prepare("SELECT id, product_id, label, price, stock_quantity FROM product_variants WHERE id IN ($placeholders) AND store_id = ?");
        $vstmt->execute(array_merge(array_values($variantIds), [$store_id]));
        foreach ($vstmt->fetchAll(PDO::FETCH_ASSOC) as $v) { $variants[$v['id']] = $v; }
    }

    foreach ($items as $key => $item) {
        $product = $products[$item['product_id']] ?? null;
        if (!$product) { continue; }
        $quantity = (int)$item['quantity'];
        $variant = $item['variant_id'] ? ($variants[$item['variant_id']] ?? null) : null;
        $unitPrice = $variant && $variant['price'] !== null ? (float)$variant['price'] : ($product['sale_price'] ?: $product['price']);
        $subtotal = $unitPrice * $quantity;
        $total += $subtotal;
        $cart_products[] = [
            'key' => $key,
            'product' => $product,
            'variant' => $variant,
            'variant_label' => $item['variant_label'] ?? ($variant['label'] ?? null),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'subtotal' => $subtotal
        ];
    }
}

// Currency formatting (shared by rows and summary)
$currency = $settings->getSetting('currency', 'USD');
$currency_symbol = $currency === 'USD' ? '$' : ($currency === 'EUR' ? '€' : ($currency === 'GBP' ? '£' : ($currency === 'TND' ? 'TND' : $currency . ' ')));
$rightPositionCurrencies = ['TND','AED','SAR','QAR','KWD','BHD','OMR','MAD','DZD','EGP'];
$price_position_right = in_array($currency, $rightPositionCurrencies, true);
$price_prefix = $price_position_right ? '' : ($currency_symbol);
$price_suffix = $price_position_right ? (' ' . $currency_symbol) : '';
?>

<div class="container py-5">
    <div class="row">
        <div class="col-12">
            <h1 class="mb-4">Shopping Cart</h1>
        </div>
    </div>
    
    <?php if (!empty($cart_products)): ?>
        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Price</th>
                                        <th>Quantity</th>
                                        <th>Total</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($cart_products as $item): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <?php
                                                    $cart_item_image = "uploads/products/{$item['product']['id']}/main.jpg";
                                                    $cart_item_exists = file_exists($cart_item_image);
                                                    $cart_display = $cart_item_exists ? $cart_item_image : 'uploads/placeholder.jpg';
                                                    ?>
                                                    <img src="<?php echo $cart_display; ?>" loading="lazy" 
                                                         class="me-3" style="width: 60px; height: 60px; object-fit: cover; border-radius: 8px;" 
                                                         onerror="this.src='uploads/placeholder.jpg'"
                                                         alt="<?php echo htmlspecialchars($item['product']['name']); ?>">
                                                    <div>
                                                        <h6 class="mb-0"><?php echo htmlspecialchars($item['product']['name']); ?></h6>
                                                        <small class="text-muted">SKU: <?php echo htmlspecialchars($item['product']['sku']); ?></small>
                                                        <?php if (!empty($item['variant_label'])): ?>
                                                            <div><small class="badge bg-light text-dark">Variant: <?php echo htmlspecialchars($item['variant_label']); ?></small></div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if ($item['product']['sale_price']): ?>
                                                    <span class="text-muted text-decoration-line-through small"><?php echo $price_prefix; ?><?php echo number_format($item['product']['price'], 2); ?><?php echo $price_suffix; ?></span>
                                                    <strong class="text-danger"><?php echo $price_prefix; ?><?php echo number_format($item['product']['sale_price'], 2); ?><?php echo $price_suffix; ?></strong>
                                                <?php else: ?>
                                                    <?php echo $price_prefix; ?><?php echo number_format($item['product']['price'], 2); ?><?php echo $price_suffix; ?>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="update">
                                                    <input type="hidden" name="product_id" value="<?php echo $item['product']['id']; ?>">
                                    <div class="input-group" style="width: 120px;">
                                                        <input type="number" name="quantity" class="form-control" 
                                                               value="<?php echo $item['quantity']; ?>" min="1" max="99">
                                                        <input type="hidden" name="key" value="<?php echo htmlspecialchars($item['key']); ?>">
                                                        <button type="submit" class="btn btn-outline-secondary btn-sm">Update</button>
                                                    </div>
                                                </form>
                                            </td>
                                            <td><?php echo $price_prefix; ?><?php echo number_format($item['subtotal'], 2); ?><?php echo $price_suffix; ?></td>
                                            <td>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="remove">
                                                    <input type="hidden" name="key" value="<?php echo htmlspecialchars($item['key']); ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="text-end">
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="action" value="clear">
                                <button type="submit" class="btn btn-outline-danger" 
                                        onclick="return confirm('Are you sure you want to clear the cart?')">
                                    Clear Cart
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5>Order Summary</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Subtotal:</span>
                            <span><?php echo $price_prefix; ?><?php echo number_format($total, 2); ?><?php echo $price_suffix; ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Tax:</span>
                            <span><?php echo $price_prefix; ?><?php echo number_format($total * ($settings->getSetting('tax_rate', 0) / 100), 2); ?><?php echo $price_suffix; ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Shipping:</span>
                            <span><?php echo $price_prefix; ?><?php echo number_format($settings->getSetting('shipping_price', 0), 2); ?><?php echo $price_suffix; ?></span>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between">
                            <strong>Total:</strong>
                            <strong><?php echo $price_prefix; ?><?php echo number_format($total + ($total * ($settings->getSetting('tax_rate', 0) / 100)) + $settings->getSetting('shipping_price', 0), 2); ?><?php echo $price_suffix; ?></strong>
                        </div>
                        
                        <div class="d-grid gap-2 mt-3">
                            <a href="index.php?page=checkout" class="btn btn-primary btn-lg">
                                Proceed to Checkout
                            </a>
                            <a href="index.php?page=shop" class="btn btn-outline-primary">
                                Continue Shopping
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="row">
            <div class="col-12 text-center py-5">
                <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                <h3>Your cart is empty</h3>
                <p>Add some products to get started!</p>
                <a href="index.php?page=shop" class="btn btn-primary btn-lg">Start Shopping</a>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php if ($pluginHelper->isPluginActive('google_analytics') || $pluginHelper->isPluginActive('facebook_pixel')): ?>
<script>
// Track cart view
<?php if ($pluginHelper->isPluginActive('google_analytics')): ?>
gtag('event', 'view_cart', {
    currency: '<?php echo $currency; ?>',
    value: <?php echo $total; ?>,
    items: [
        <?php foreach ($cart_products as $item): ?>
        {
            item_id: '<?php echo $item['product']['id']; ?>',
            item_name: '<?php echo addslashes($item['product']['name']); ?>',
            category: '<?php echo addslashes($item['product']['categories'] ?? ''); ?>',
            price: <?php echo $item['product']['sale_price'] ?: $item['product']['price']; ?>,
            quantity: <?php echo $item['quantity']; ?>
        }<?php echo $item !== end($cart_products) ? ',' : ''; ?>
        <?php endforeach; ?>
    ]
});
<?php endif; ?>

<?php if ($pluginHelper->isPluginActive('facebook_pixel')): ?>
fbq('track', 'ViewCart', {
    content_type: 'product',
    content_ids: [<?php echo implode(',', array_map(function($item) { return "'" . $item['product']['id'] . "'"; }, $cart_products)); ?>],
    value: <?php echo $total; ?>,
    currency: '<?php echo $currency; ?>'
});
<?php endif; ?>
</script>
<?php endif; ?>
