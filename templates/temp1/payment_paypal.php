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

// Get PayPal configuration
$paypalConfig = $pluginHelper->getPluginConfig('paypal');
$paypalClientId = $paypalConfig['paypal_client_id'] ?? '';
$paypalMode = $paypalConfig['paypal_mode'] ?? 'sandbox';

if (empty($paypalClientId)) {
    die('PayPal is not properly configured. Please contact support.');
}

$paypalUrl = ($paypalMode === 'live') ? 'https://www.paypal.com' : 'https://www.sandbox.paypal.com';

?>
<!DOCTYPE html>
<html>
<head>
    <title>Payment - PayPal</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://www.paypal.com/sdk/js?client-id=<?php echo htmlspecialchars($paypalClientId); ?>&currency=USD"></script>
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
                            This is a demo payment page. In a real implementation, you would integrate with PayPal's SDK.
                        </div>
                        
                        <div id="paypal-button-container"></div>
                        
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
        // In production, you would use PayPal's actual SDK
        paypal.Buttons({
            createOrder: function(data, actions) {
                return actions.order.create({
                    purchase_units: [{
                        amount: {
                            value: '<?php echo $order['total_amount']; ?>'
                        }
                    }]
                });
            },
            onApprove: function(data, actions) {
                // In a real implementation, you would:
                // 1. Capture the payment
                // 2. Update the order status
                // 3. Redirect to success page
                
                // For demo purposes, redirect to success
                window.location.href = 'payment_paypal_success.php?order_id=<?php echo $order_id; ?>&order_id_paypal=' + data.orderID;
            }
        }).render('#paypal-button-container');
    </script>
</body>
</html>
