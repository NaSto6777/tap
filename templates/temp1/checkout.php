<?php
require_once __DIR__ . '/../../config/plugin_helper.php';
require_once __DIR__ . '/../../config/email_helper.php';
require_once __DIR__ . '/../../config/analytics_helper.php';
require_once __DIR__ . '/../../config/billing_helper.php';
require_once __DIR__ . '/../../config/StoreContext.php';
require_once __DIR__ . '/../../config/CsrfHelper.php';

// Check if cart is empty
if (empty($_SESSION['cart'])) {
    header('Location: index.php?page=cart');
    exit();
}

$store_id = StoreContext::getId();
$store_limit_reached = false;
if ($store_id) {
    BillingHelper::init();
    if (!BillingHelper::canAcceptOrder($store_id)) {
        $store_limit_reached = true;
    }
}

// Track checkout start for analytics
$analytics = new AnalyticsHelper();
$analytics->trackFunnelStep('checkout_start', session_id());

$pluginHelper = new PluginHelper();
$emailService = new EmailService();

// Get currency settings
$currency = $settings->getSetting('currency', 'USD');
$currency_symbol = $currency === 'USD' ? '$' : ($currency === 'EUR' ? '€' : ($currency === 'GBP' ? '£' : ($currency === 'TND' ? 'TND' : $currency . ' ')));
$rightPositionCurrencies = ['TND','AED','SAR','QAR','KWD','BHD','OMR','MAD','DZD','EGP'];
$price_position_right = in_array($currency, $rightPositionCurrencies, true);
$price_prefix = $price_position_right ? '' : ($currency_symbol);
$price_suffix = $price_position_right ? (' ' . $currency_symbol) : '';

// Handle checkout form submission
if ($_POST) {
    if (!CsrfHelper::validateToken($_POST['csrf_token'] ?? '')) {
        $error_message = 'Invalid security token. Please refresh the page and try again.';
    }
    $customer_name = $_POST['customer_name'] ?? '';
    $customer_email = $_POST['customer_email'] ?? '';
    $customer_phone = $_POST['customer_phone'] ?? '';
    $shipping_governorate = $_POST['shipping_governorate'] ?? '';
    $shipping_city        = $_POST['shipping_city'] ?? '';
    $shipping_address = $_POST['shipping_address'] ?? '';
    $billing_address = $_POST['billing_address'] ?? '';
    $payment_method = $_POST['payment_method'] ?? 'cod';
    $newsletter_signup = isset($_POST['newsletter_signup']);
    
    if (class_exists('StoreContext')) {
        $store_id = StoreContext::getId();
        if ($store_id && !BillingHelper::canAcceptOrder($store_id)) {
            $error_message = 'This store is temporarily not accepting new orders due to high volume.';
        }
    }
    
    // Verify reCAPTCHA if plugin is active
    if ($pluginHelper->isPluginActive('recaptcha') && empty($error_message)) {
        $recaptcha_token = $_POST['g-recaptcha-response'] ?? '';
        if (!$pluginHelper->verifyRecaptcha($recaptcha_token)) {
            $error_message = 'Please complete the reCAPTCHA verification.';
        }
    }
    
    if (!empty($customer_name) && !empty($customer_email) && !empty($shipping_address) && empty($error_message)) {
        $database = new Database();
        $conn = $database->getConnection();
        BillingHelper::init($conn);
        $store_id = StoreContext::getId();
        $placement_state = BillingHelper::getPlacementState($store_id);
        $consume_credit_after = $placement_state['use_credit'];
        
        // Calculate totals with support for variants
        $subtotal = 0;
        $items = $_SESSION['cart'];
        $productIds = [];
        $variantIds = [];
        foreach ($items as $key => $item) {
            $productIds[] = (int)$item['product_id'];
            if (!empty($item['variant_id'])) { $variantIds[] = (int)$item['variant_id']; }
        }
        $productIds = array_unique($productIds);
        $variantIds = array_unique($variantIds);

        $products = [];
        if (!empty($productIds)) {
            $placeholders = implode(',', array_fill(0, count($productIds), '?'));
            $pstmt = $conn->prepare("SELECT id, name, sku, price, sale_price FROM products WHERE id IN ($placeholders) AND store_id = ? AND is_active = 1");
            $pstmt->execute(array_merge(array_values($productIds), [$store_id]));
            foreach ($pstmt->fetchAll(PDO::FETCH_ASSOC) as $p) { $products[$p['id']] = $p; }
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
            $subtotal += $unitPrice * $quantity;
        }
        
        $tax_rate = $settings->getSetting('tax_rate', 0);
        $shipping_price = $settings->getSetting('shipping_price', 0);
        $tax_amount = $subtotal * ($tax_rate / 100);
        $total_amount = $subtotal + $tax_amount + $shipping_price;
        
        // Generate order number
        $order_number = 'ORD-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        // Create order (scoped to current store)
        $store_id = StoreContext::getId();
        $payment_status = ($payment_method === 'cod') ? 'pending' : 'pending';

        try {
            // Newer schema with payment_method & payment_status columns
            $query = "INSERT INTO orders (
                        store_id, order_number, customer_name, customer_email, customer_phone,
                        shipping_governorate, shipping_city,
                        shipping_address, billing_address,
                        subtotal, tax_amount, shipping_amount, total_amount,
                        payment_method, payment_status
                      ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($query);
            $stmt->execute([
                $store_id, $order_number, $customer_name, $customer_email, $customer_phone,
                $shipping_governorate, $shipping_city,
                $shipping_address, $billing_address,
                $subtotal, $tax_amount, $shipping_price, $total_amount,
                $payment_method, $payment_status
            ]);
        } catch (PDOException $e) {
            // Backwards compatibility for schemas without payment_method / payment_status
            if ($e->getCode() === '42S22') {
                $query = "INSERT INTO orders (
                            store_id, order_number, customer_name, customer_email, customer_phone,
                            shipping_governorate, shipping_city,
                            shipping_address, billing_address,
                            subtotal, tax_amount, shipping_amount, total_amount
                          ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($query);
                $stmt->execute([
                    $store_id, $order_number, $customer_name, $customer_email, $customer_phone,
                    $shipping_governorate, $shipping_city,
                    $shipping_address, $billing_address,
                    $subtotal, $tax_amount, $shipping_price, $total_amount
                ]);
            } else {
                throw $e;
            }
        }

        $order_id = $conn->lastInsertId();
        
        // Add order items (variant-aware, scoped to store)
        $query = "INSERT INTO order_items (store_id, order_id, product_id, product_name, product_sku, quantity, price, total) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        $orderItemsForEmail = [];
        foreach ($items as $key => $item) {
            $product = $products[$item['product_id']] ?? null;
            if (!$product) { continue; }
            $quantity = (int)$item['quantity'];
            $variant = $item['variant_id'] ? ($variants[$item['variant_id']] ?? null) : null;
            $unitPrice = $variant && $variant['price'] !== null ? (float)$variant['price'] : ($product['sale_price'] ?: $product['price']);
            $total = $unitPrice * $quantity;
            $name = $product['name'] . ($variant ? (' - ' . $variant['label']) : '');
            $sku = $product['sku'];
            $stmt->execute([$store_id, $order_id, $product['id'], $name, $sku, $quantity, $unitPrice, $total]);

            $orderItemsForEmail[] = [
                'product_name' => $name,
                'product_sku' => $sku,
                'quantity' => $quantity,
                'total' => $total
            ];
        }
        
        if (!empty($consume_credit_after)) {
            BillingHelper::consumeCredit($store_id);
        }
        
        // Send order confirmation email
        $orderData = [
            'order_number' => $order_number,
            'customer_name' => $customer_name,
            'customer_email' => $customer_email,
            'shipping_address' => $shipping_address,
            'payment_method' => $payment_method,
            'status' => $payment_status,
            'subtotal' => $subtotal,
            'tax_amount' => $tax_amount,
            'shipping_amount' => $shipping_price,
            'total_amount' => $total_amount,
            'created_at' => date('Y-m-d H:i:s'),
            'items' => $orderItemsForEmail
        ];
        
        $emailService->sendOrderConfirmation($orderData, $customer_email);
        
        // Mark abandoned cart as completed (so it doesn't show in recovery list)
        try {
            $analytics = new AnalyticsHelper($conn, StoreContext::getId());
            $analytics->markCartCompleted(session_id());
        } catch (Exception $e) {}
        
        // Handle newsletter signup
        if ($newsletter_signup && $pluginHelper->isPluginActive('mailchimp')) {
            // This will be handled by a separate script
            $_SESSION['newsletter_signup'] = $customer_email;
        }
        
        // Handle payment processing
        if ($payment_method === 'stripe') {
            // Redirect to Stripe payment
            header('Location: payment_stripe.php?order_id=' . $order_id);
            exit();
        } elseif ($payment_method === 'paypal') {
            // Redirect to PayPal payment
            header('Location: payment_paypal.php?order_id=' . $order_id);
            exit();
        } elseif ($payment_method === 'flouci') {
            // Redirect to Flouci payment
            header('Location: payment_flouci.php?order_id=' . $order_id);
            exit();
        } else {
            // Cash on Delivery - clear cart and redirect to success
            $_SESSION['cart'] = [];
            header('Location: index.php?page=checkout_success&order=' . $order_number);
            exit();
        }
    }
}

// Get cart products for display (variant-aware)
$cart_products = [];
$subtotal = 0;

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

    $products = [];
    if (!empty($productIds)) {
        $placeholders = implode(',', array_fill(0, count($productIds), '?'));
        $pstmt = $conn->prepare("SELECT * FROM products WHERE id IN ($placeholders) AND store_id = ? AND is_active = 1");
        $pstmt->execute(array_merge(array_values($productIds), [$store_id]));
        foreach ($pstmt->fetchAll(PDO::FETCH_ASSOC) as $p) { $products[$p['id']] = $p; }
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
        $item_total = $unitPrice * $quantity;
        $subtotal += $item_total;
        $cart_products[] = [
            'product' => $product,
            'variant' => $variant,
            'quantity' => $quantity,
            'total' => $item_total
        ];
    }
}

$tax_rate = $settings->getSetting('tax_rate', 0);
$shipping_price = $settings->getSetting('shipping_price', 0);
$tax_amount = $subtotal * ($tax_rate / 100);
$total_amount = $subtotal + $tax_amount + $shipping_price;
?>

<div class="container py-5">
    <div class="row">
        <div class="col-12">
            <h1 class="mb-4">Checkout</h1>
        </div>
    </div>
    <?php if (!empty($store_limit_reached)): ?>
    <div class="alert alert-warning">
        This store is temporarily not accepting new orders due to high volume.
    </div>
    <p><a href="index.php?page=cart" class="btn btn-secondary">Back to cart</a></p>
    <?php else: ?>
    <?php if (!empty($error_message)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>
    <form method="POST">
        <?php echo CsrfHelper::getTokenField(); ?>
        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5>Customer Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="customer_name" class="form-label">Full Name *</label>
                                <input type="text" class="form-control" id="customer_name" name="customer_name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="customer_email" class="form-label">Email Address *</label>
                                <input type="email" class="form-control" id="customer_email" name="customer_email" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="customer_phone" class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" id="customer_phone" name="customer_phone">
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card mt-4">
                    <div class="card-header">
                        <h5>Shipping Address</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="shipping_governorate" class="form-label">Gouvernorat *</label>
                                <select class="form-select" id="shipping_governorate" name="shipping_governorate" required></select>
                                <input type="text" class="form-control form-control-sm mt-1"
                                       id="shipping_governorate_search" placeholder="Type to search...">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="shipping_city" class="form-label">Ville *</label>
                                <select class="form-select" id="shipping_city" name="shipping_city" required></select>
                                <input type="text" class="form-control form-control-sm mt-1"
                                       id="shipping_city_search" placeholder="Type to search...">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="shipping_address" class="form-label">Address *</label>
                            <textarea class="form-control" id="shipping_address" name="shipping_address" rows="3" required></textarea>
                        </div>
                    </div>
                </div>
                
                <div class="card mt-4">
                    <div class="card-header">
                        <h5>Billing Address</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="billing_address" class="form-label">Address</label>
                            <textarea class="form-control" id="billing_address" name="billing_address" rows="3"></textarea>
                            <div class="form-text">Leave blank to use shipping address</div>
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
                        <!-- Order Items -->
                        <?php foreach ($cart_products as $item): ?>
                            <div class="d-flex align-items-center mb-3">
                                <?php
                                $checkout_item_image = "uploads/products/{$item['product']['id']}/main.jpg";
                                $checkout_item_exists = file_exists($checkout_item_image);
                                $checkout_display = $checkout_item_exists ? $checkout_item_image : 'uploads/placeholder.jpg';
                                ?>
                                <img src="<?php echo $checkout_display; ?>" 
                                     class="me-3" style="width: 50px; height: 50px; object-fit: cover; border-radius: 8px;" 
                                     onerror="this.src='uploads/placeholder.jpg'"
                                     alt="<?php echo htmlspecialchars($item['product']['name']); ?>">
                                <div class="flex-grow-1">
                                    <h6 class="mb-0"><?php echo htmlspecialchars($item['product']['name']); ?></h6>
                                    <small class="text-muted">Qty: <?php echo $item['quantity']; ?></small>
                                </div>
                                <span><?php echo $price_prefix; ?><?php echo number_format($item['total'], 2); ?><?php echo $price_suffix; ?></span>
                            </div>
                        <?php endforeach; ?>
                        
                        <hr>
                        
                        <!-- Totals -->
                        <div class="d-flex justify-content-between mb-2">
                            <span>Subtotal:</span>
                            <span><?php echo $price_prefix; ?><?php echo number_format($subtotal, 2); ?><?php echo $price_suffix; ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Tax (<?php echo $tax_rate; ?>%):</span>
                            <span><?php echo $price_prefix; ?><?php echo number_format($tax_amount, 2); ?><?php echo $price_suffix; ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Shipping:</span>
                            <span><?php echo $price_prefix; ?><?php echo number_format($shipping_price, 2); ?><?php echo $price_suffix; ?></span>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between">
                            <strong>Total:</strong>
                            <strong><?php echo $price_prefix; ?><?php echo number_format($total_amount, 2); ?><?php echo $price_suffix; ?></strong>
                        </div>
                    </div>
                </div>
                
                <!-- Payment Method Selection -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5>Payment Method</h5>
                    </div>
                    <div class="card-body">
                        <?php 
                        $paymentMethods = json_decode($settings->getSetting('payment_methods', '["cod"]'), true);
                        $activePaymentMethods = $pluginHelper->getActivePaymentMethods();
                        $availableMethods = array_intersect_key($activePaymentMethods, array_flip($paymentMethods));
                        ?>
                        <?php foreach ($availableMethods as $methodKey => $methodName): ?>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="radio" name="payment_method" id="payment_<?php echo $methodKey; ?>" 
                                   value="<?php echo $methodKey; ?>" <?php echo $methodKey === 'cod' ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="payment_<?php echo $methodKey; ?>">
                                <strong><?php echo htmlspecialchars($methodName); ?></strong>
                                <?php if ($methodKey === 'cod'): ?>
                                    <br><small class="text-muted">Pay when your order is delivered</small>
                                <?php elseif ($methodKey === 'stripe'): ?>
                                    <br><small class="text-muted">Pay securely with your credit card</small>
                                <?php elseif ($methodKey === 'paypal'): ?>
                                    <br><small class="text-muted">Pay with your PayPal account</small>
                                <?php endif; ?>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Newsletter Signup -->
                <?php if ($pluginHelper->isPluginActive('mailchimp')): ?>
                <div class="card mt-4">
                    <div class="card-body">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="newsletter_signup" id="newsletter_signup">
                            <label class="form-check-label" for="newsletter_signup">
                                Subscribe to our newsletter for updates and special offers
                            </label>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- reCAPTCHA -->
                <?php if ($pluginHelper->isPluginActive('recaptcha')): ?>
                <div class="card mt-4">
                    <div class="card-body">
                        <div class="g-recaptcha" data-sitekey="<?php echo htmlspecialchars($pluginHelper->getPluginConfig('recaptcha')['recaptcha_site_key'] ?? ''); ?>"></div>
                    </div>
                </div>
                <?php endif; ?>
                        
                        <div class="d-grid mt-4">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-credit-card"></i> Place Order
                            </button>
                        </div>
                        
                        <div class="text-center mt-3">
                            <small class="text-muted">
                                <i class="fas fa-lock"></i> Your payment information is secure
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
    <?php endif; ?>
</div>

<?php
// CSRF token for abandoned cart logger
if (!isset($_SESSION['abandoned_cart_csrf'])) {
    $_SESSION['abandoned_cart_csrf'] = bin2hex(random_bytes(32));
}
$abandoned_cart_csrf = $_SESSION['abandoned_cart_csrf'];
$abandoned_cart_log_url = 'ajax/abandoned_cart_log.php';
?>
<script>
// Real-time Abandoned Cart Logger (debounce 2s)
(function() {
    const logUrl = '<?php echo htmlspecialchars($abandoned_cart_log_url); ?>';
    const csrfToken = '<?php echo htmlspecialchars($abandoned_cart_csrf); ?>';
    const cartValue = <?php echo json_encode((float)($total_amount ?? 0)); ?>;
    let debounceTimer = null;
    const DEBOUNCE_MS = 2000;

    function getFormData() {
        return {
            customer_name: (document.getElementById('customer_name') || {}).value || '',
            customer_email: (document.getElementById('customer_email') || {}).value || '',
            customer_phone: (document.getElementById('customer_phone') || {}).value || '',
            cart_value: cartValue,
            csrf_token: csrfToken
        };
    }

    function hasContactInfo(data) {
        return (data.customer_email && data.customer_email.length > 2) || (data.customer_phone && data.customer_phone.length > 2);
    }

    function logAbandonedCart() {
        const data = getFormData();
        if (!hasContactInfo(data)) return;

        const formData = new FormData();
        Object.keys(data).forEach(function(k) { formData.append(k, data[k]); });

        fetch(logUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        }).then(function(r) { return r.json(); }).then(function(res) {
            if (res.success && !res.skipped) { /* logged */ }
        }).catch(function() {});
    }

    function scheduleLog() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(logAbandonedCart, DEBOUNCE_MS);
    }

    ['customer_name','customer_email','customer_phone'].forEach(function(id) {
        var el = document.getElementById(id);
        if (el) {
            el.addEventListener('input', scheduleLog);
            el.addEventListener('blur', function() { clearTimeout(debounceTimer); logAbandonedCart(); });
        }
    });
})();
</script>
<?php if (empty($store_limit_reached) && ($pluginHelper->isPluginActive('google_analytics') || $pluginHelper->isPluginActive('facebook_pixel'))): ?>
<script>
// Track checkout initiation
<?php if ($pluginHelper->isPluginActive('google_analytics')): ?>
gtag('event', 'begin_checkout', {
    currency: '<?php echo $currency; ?>',
    value: <?php echo $total_amount; ?>,
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
fbq('track', 'InitiateCheckout', {
    content_type: 'product',
    content_ids: [<?php echo implode(',', array_map(function($item) { return "'" . $item['product']['id'] . "'"; }, $cart_products)); ?>],
    value: <?php echo $total_amount; ?>,
    currency: '<?php echo $currency; ?>'
});
<?php endif; ?>
</script>
<?php endif; ?>

<script>
// Tunisian Gouvernorats and Villes (from Colissimo documentation)
const TN_REGIONS = {
  "Ariana": ["Ariana Ville","Ettadhamen","Kalaat Landlous","La Soukra","Mnihla","Raoued","Sidi Thabet"],
  "Beja": ["Amdoun","Beja Nord","Beja Sud","Goubellat","Mejez El Bab","Nefza","Teboursouk","Testour","Thibar"],
  "Ben Arous": ["Ben Arous","Bou Mhel El Bassatine","El Mourouj","Ezzahra","Fouchana","Hammam Chatt","Hammam Lif","Megrine","Mohamadia","Mornag","Nouvelle Medina","Rades"],
  "Bizerte": ["Bizerte Nord","Bizerte Sud","El Alia","Ghar El Melh","Ghezala","Jarzouna","Joumine","Mateur","Menzel Bourguiba","Menzel Jemil","Ras Jebel","Sejnane","Tinja","Utique","Aousja","La Pecherie"],
  "Gabes": ["El Hamma","El Metouia","Gabes Medina","Gabes Ouest","Gabes Sud","Ghannouche","Mareth","Matmata","Menzel Habib","Nouvelle Matmata"],
  "Gafsa": ["Belkhir","El Guettar","El Ksar","El Mdhilla","Gafsa Nord","Gafsa Sud","Metlaoui","Moulares","Redeyef","Sidi Aich","Sned"],
  "Jendouba": ["Ain Draham","Balta Bou Aouene","Bou Salem","Fernana","Ghardimaou","Jendouba","Jendouba Nord","Oued Mliz","Tabarka"],
  "Kairouan": ["Bou Hajla","Chebika","Cherarda","El Ala","Haffouz","Hajeb El Ayoun","Kairouan Nord","Kairouan Sud","Nasrallah","Oueslatia","Sbikha"],
  "Kasserine": ["El Ayoun","Ezzouhour (Kasserine)","Feriana","Foussana","Haidra","Hassi El Frid","Jediliane","Kasserine Nord","Mejel Bel Abbes","Sbeitla","Sbiba","Thala","Kasserine Sud"],
  "Kebili": ["Douz","El Faouar","Kebili Nord","Kebili Sud","Souk El Ahad"],
  "Le Kef": ["Dahmani","El Ksour","Jerissa","Kalaa El Khasba","Kalaat Sinane","Le Kef Est","Le Kef Ouest","Nebeur","Sakiet Sidi Youssef","Tajerouine","Touiref","Le Sers"],
  "Mahdia": ["Bou Merdes","Chorbane","El Jem","Hbira","Ksour Essaf","La Chebba","Mahdia","Melloulech","Ouled Chamakh","Sidi Alouene","Souassi"],
  "La Manouba": ["Borj El Amri","El Battan","Jedaida","Mannouba","Mornaguia","Oued Ellil","Tebourba","Douar Hicher"],
  "Medenine": ["Ajim","Ben Guerdane","Beni Khedache","Houmet Essouk","Medenine Nord","Medenine Sud","Midoun","Sidi Makhlouf","Zarzis"],
  "Monastir": ["Bekalta","Bembla","Beni Hassen","Jemmal","Ksar Helal","Ksibet El Mediouni","Monastir","Ouerdanine","Sahline","Sayada Lamta Bou Hajar","Teboulba","Zeramdine","Moknine"],
  "Nabeul": ["Beni Khalled","Beni Khiar","Bou Argoub","Dar Chaabane Elfehri","El Haouaria","El Mida","Grombalia","Hammam El Ghezaz","Hammamet","Kelibia","Korba","Menzel Bouzelfa","Menzel Temime","Nabeul","Soliman","Takelsa"],
  "Sfax": ["Agareb","Bir Ali Ben Khelifa","El Amra","El Hencha","Esskhira","Ghraiba","Jebeniana","Kerkenah","Mahras","Menzel Chaker","Sakiet Eddaier","Sakiet Ezzit","Sfax Est","Sfax Sud","Sfax Ville"],
  "Sousse": ["Akouda","Bou Ficha","Enfidha","Hammam Sousse","Hergla","Kalaa El Kebira","Kalaa Essghira","Kondar","Msaken","Sidi Bou Ali","Sidi El Heni","Sousse Jaouhara","Sousse Riadh","Sousse Ville"],
  "Siliana": ["Bargou","Bou Arada","El Aroussa","Gaafour","Kesra","Le Krib","Makthar","Rohia","Sidi Bou Rouis","Siliana Nord","Siliana Sud"],
  "Sidi Bouzid": ["Ben Oun","Bir El Haffey","Cebbala","Jilma","Maknassy","Menzel Bouzaiene","Mezzouna","Ouled Haffouz","Regueb","Sidi Bouzid Est","Sidi Bouzid Ouest","Souk Jedid"],
  "Tataouine": ["Bir Lahmar","Dhehiba","Ghomrassen","Remada","Smar","Tataouine Nord","Tataouine Sud"],
  "Tozeur": ["Degueche","Hezoua","Nefta","Tameghza","Tozeur"],
  "Tunis": ["Bab Bhar","Bab Souika","Carthage","Cite El Khadra","El Hrairia","El Kabbaria","El Kram","El Menzah","El Omrane","El Omrane Superieur","El Ouerdia","Essijoumi","Ettahrir","Ezzouhour (Tunis)","Jebel Jelloud","La Goulette","La Marsa","La Medina","Le Bardo","Sidi El Bechir","Sidi Hassine","Tunis"],
  "Zaghouan": ["Bir Mcherga","El Fahs","Ennadhour","Hammam Zriba","Saouef","Zaghouan"]
};

(function() {
  const govSelect   = document.getElementById('shipping_governorate');
  const citySelect  = document.getElementById('shipping_city');
  const govSearch   = document.getElementById('shipping_governorate_search');
  const citySearch  = document.getElementById('shipping_city_search');

  if (!govSelect || !citySelect || !govSearch || !citySearch) return;

  function populateGovernates() {
    govSelect.innerHTML = '';
    Object.keys(TN_REGIONS).forEach(function(g) {
      const opt = document.createElement('option');
      opt.value = g;
      opt.textContent = g;
      govSelect.appendChild(opt);
    });
  }

  function populateCities(gov) {
    citySelect.innerHTML = '';
    const cities = TN_REGIONS[gov] || [];
    cities.forEach(function(c) {
      const opt = document.createElement('option');
      opt.value = c;
      opt.textContent = c;
      citySelect.appendChild(opt);
    });
  }

  populateGovernates();
  populateCities(govSelect.value);

  govSelect.addEventListener('change', function() {
    populateCities(this.value);
    citySearch.value = '';
  });

  govSearch.addEventListener('input', function() {
    const q = this.value.toLowerCase();
    const match = Object.keys(TN_REGIONS).find(function(g) {
      return g.toLowerCase().indexOf(q) !== -1;
    });
    if (match) {
      govSelect.value = match;
      populateCities(match);
    }
  });

  citySearch.addEventListener('input', function() {
    const q = this.value.toLowerCase();
    const options = Array.from(citySelect.options);
    const match = options.find(function(opt) {
      return opt.textContent.toLowerCase().indexOf(q) !== -1;
    });
    if (match) {
      citySelect.value = match.value;
    }
  });
})();
</script>
