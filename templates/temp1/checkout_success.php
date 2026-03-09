<?php
require_once __DIR__ . '/../../config/plugin_helper.php';

$order_number = $_GET['order'] ?? '';

if (empty($order_number)) {
    header('Location: index.php');
    exit();
}

$database = new Database();
$conn = $database->getConnection();
$pluginHelper = new PluginHelper();

// Get order details
$query = "SELECT * FROM orders WHERE order_number = ?";
$stmt = $conn->prepare($query);
$stmt->execute([$order_number]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header('Location: index.php');
    exit();
}

// Get order items
$query = "SELECT oi.*, p.name as product_name, p.sku as product_sku 
          FROM order_items oi 
          LEFT JOIN products p ON oi.product_id = p.id 
          WHERE oi.order_id = ?";
$stmt = $conn->prepare($query);
$stmt->execute([$order['id']]);
$order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body text-center">
                    <div class="mb-4">
                        <i class="fas fa-check-circle fa-5x text-success"></i>
                    </div>
                    
                    <h1 class="text-success mb-3">Order Placed Successfully!</h1>
                    <p class="lead">Thank you for your order. We'll send you a confirmation email shortly.</p>
                    
                    <div class="alert alert-info">
                        <h5>Order #<?php echo htmlspecialchars($order['order_number']); ?></h5>
                        <p class="mb-0">Total: <strong>$<?php echo number_format($order['total_amount'], 2); ?></strong></p>
                    </div>
                    
                    <div class="row mt-4">
                        <div class="col-md-6">
                            <h6>What's Next?</h6>
                            <ul class="list-unstyled text-start">
                                <li><i class="fas fa-envelope text-primary me-2"></i> Confirmation email sent</li>
                                <li><i class="fas fa-box text-primary me-2"></i> Order processing</li>
                                <li><i class="fas fa-shipping-fast text-primary me-2"></i> Shipping notification</li>
                                <li><i class="fas fa-truck text-primary me-2"></i> Delivery tracking</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6>Order Summary</h6>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <tbody>
                                        <?php foreach ($order_items as $item): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                                                <td><?php echo $item['quantity']; ?>x</td>
                                                <td>$<?php echo number_format($item['total'], 2); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <a href="index.php" class="btn btn-primary btn-lg me-3">
                            <i class="fas fa-home"></i> Continue Shopping
                        </a>
                        <a href="index.php?page=shop" class="btn btn-outline-primary btn-lg">
                            <i class="fas fa-shopping-bag"></i> Browse Products
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($pluginHelper->isPluginActive('google_analytics') || $pluginHelper->isPluginActive('facebook_pixel')): ?>
<script>
// Track purchase completion
<?php if ($pluginHelper->isPluginActive('google_analytics')): ?>
gtag('event', 'purchase', {
    transaction_id: '<?php echo $order['order_number']; ?>',
    currency: 'USD',
    value: <?php echo $order['total_amount']; ?>,
    items: [
        <?php foreach ($order_items as $item): ?>
        {
            item_id: '<?php echo $item['product_id']; ?>',
            item_name: '<?php echo addslashes($item['product_name']); ?>',
            category: 'Product',
            price: <?php echo $item['price']; ?>,
            quantity: <?php echo $item['quantity']; ?>
        }<?php echo $item !== end($order_items) ? ',' : ''; ?>
        <?php endforeach; ?>
    ]
});
<?php endif; ?>

<?php if ($pluginHelper->isPluginActive('facebook_pixel')): ?>
fbq('track', 'Purchase', {
    content_type: 'product',
    content_ids: [<?php echo implode(',', array_map(function($item) { return "'" . $item['product_id'] . "'"; }, $order_items)); ?>],
    value: <?php echo $order['total_amount']; ?>,
    currency: 'USD'
});
<?php endif; ?>
</script>
<?php endif; ?>
