<?php
require_once __DIR__ . '/../../config/plugin_helper.php';
require_once __DIR__ . '/../../config/database.php';

$pluginHelper = new PluginHelper();
$database = new Database();
$conn = $database->getConnection();

$order_id = $_GET['order_id'] ?? 0;

if (empty($order_id)) {
    header('Location: index.php?page=cart');
    exit();
}

// Get order details
$query = "SELECT * FROM orders WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->execute([$order_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header('Location: index.php?page=cart');
    exit();
}

// Get Stripe configuration
$stripeConfig = $pluginHelper->getPluginConfig('stripe');
$stripeSecretKey = $stripeConfig['stripe_secret_key'] ?? '';

if (empty($stripeSecretKey)) {
    die('Stripe is not properly configured. Please contact support.');
}

// For now, we'll use a simple redirect approach
// In production, you'd use the Stripe SDK to create a checkout session
$stripePublishableKey = $stripeConfig['stripe_publishable_key'] ?? '';

?>
<!DOCTYPE html>
<html>
<head>
    <title>Payment - Stripe</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://js.stripe.com/v3/"></script>
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h4>Complete Your Payment</h4>
                    </div>
                    <div class="card-body">
                        <p><strong>Order Number:</strong> <?php echo htmlspecialchars($order['order_number']); ?></p>
                        <p><strong>Amount:</strong> $<?php echo number_format($order['total_amount'], 2); ?></p>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            This is a demo payment page. In a real implementation, you would integrate with Stripe's Checkout or Elements.
                        </div>
                        
                        <form id="payment-form">
                            <div class="mb-3">
                                <label for="card-element" class="form-label">Credit or debit card</label>
                                <div id="card-element" class="form-control" style="height: 40px; padding: 10px;">
                                    <!-- Stripe Elements will create form elements here -->
                                </div>
                                <div id="card-errors" role="alert"></div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100" id="submit-button">
                                <span id="button-text">Pay $<?php echo number_format($order['total_amount'], 2); ?></span>
                            </button>
                        </form>
                        
                        <div class="text-center mt-3">
                            <a href="index.php?page=checkout" class="btn btn-link">← Back to Checkout</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // This is a demo implementation
        // In production, you would use Stripe's actual API
        document.getElementById('payment-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Simulate payment processing
            const button = document.getElementById('submit-button');
            const buttonText = document.getElementById('button-text');
            
            button.disabled = true;
            buttonText.textContent = 'Processing...';
            
            setTimeout(() => {
                // In a real implementation, you would:
                // 1. Create a payment intent with Stripe
                // 2. Confirm the payment
                // 3. Update the order status
                // 4. Redirect to success page
                
                // For demo purposes, redirect to success
                window.location.href = 'payment_stripe_success.php?order_id=<?php echo $order_id; ?>&session_id=demo_session_123';
            }, 2000);
        });
    </script>
</body>
</html>
