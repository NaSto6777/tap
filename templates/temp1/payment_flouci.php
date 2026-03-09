<?php
session_start();
require_once __DIR__ . '/../../config/settings.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/plugin_helper.php';

$settings = new Settings();
$pluginHelper = new PluginHelper();

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
$order = null;
$flouci_config = null;

if ($order_id > 0) {
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        // Get order details
        $stmt = $conn->prepare("SELECT * FROM orders WHERE id = ?");
        $stmt->execute([$order_id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($order) {
            // Get Flouci configuration
            $flouci_config = $pluginHelper->getFlouciConfig();
            if (!$flouci_config) {
                throw new Exception('Flouci payment is not configured');
            }
        }
    } catch (Exception $e) {
        $order = null;
    }
}

if (!$order || !$flouci_config): ?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 text-center">
            <h1>Payment Error</h1>
            <p class="text-muted">Order not found or Flouci payment is not configured.</p>
            <a href="index.php?page=shop" class="btn btn-primary">Back to Shop</a>
        </div>
    </div>
</div>
<?php exit; endif; ?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header text-center">
                    <h4>Complete Your Payment</h4>
                </div>
                <div class="card-body text-center">
                    <h5>Order #<?php echo htmlspecialchars($order['order_number']); ?></h5>
                    <p class="text-muted">Total: <?php echo number_format($order['total_amount'], 2); ?> TND</p>
                    
                    <div id="flouci-payment-container">
                        <div class="text-center">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-3">Redirecting to Flouci payment...</p>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <a href="index.php?page=cart" class="btn btn-outline-secondary">Cancel Payment</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Flouci payment integration
    const flouciConfig = <?php echo json_encode($flouci_config); ?>;
    const orderData = <?php echo json_encode($order); ?>;
    
    // Initialize Flouci payment
    if (typeof FlouciPay !== 'undefined') {
        FlouciPay.init({
            public_key: flouciConfig.flouci_app_token,
            amount: orderData.total_amount,
            currency: 'TND',
            success_url: flouciConfig.flouci_success_url || window.location.origin + '/index.php?page=checkout_success&order=' + orderData.order_number,
            fail_url: flouciConfig.flouci_fail_url || window.location.origin + '/index.php?page=checkout&error=payment_failed',
            order_id: orderData.id,
            customer: {
                name: orderData.customer_name,
                email: orderData.customer_email,
                phone: orderData.customer_phone
            }
        });
        
        // Create payment button
        const paymentContainer = document.getElementById('flouci-payment-container');
        paymentContainer.innerHTML = `
            <div class="text-center">
                <button id="flouci-pay-btn" class="btn btn-primary btn-lg">
                    <i class="fas fa-credit-card me-2"></i>
                    Pay with Flouci
                </button>
                <p class="mt-3 text-muted">Secure payment powered by Flouci</p>
            </div>
        `;
        
        // Handle payment button click
        document.getElementById('flouci-pay-btn').addEventListener('click', function() {
            FlouciPay.createPayment();
        });
    } else {
        // Fallback if Flouci script is not loaded
        document.getElementById('flouci-payment-container').innerHTML = `
            <div class="alert alert-warning">
                <h5>Payment Method Not Available</h5>
                <p>Flouci payment service is currently unavailable. Please try again later or choose a different payment method.</p>
                <a href="index.php?page=checkout" class="btn btn-primary">Back to Checkout</a>
            </div>
        `;
    }
});
</script>

<!-- Flouci Payment SDK -->
<script src="https://cdn.flouci.com/flouci-sdk.js"></script>
