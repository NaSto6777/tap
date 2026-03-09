<?php
require_once __DIR__ . '/../../config/plugin_helper.php';
require_once __DIR__ . '/../../config/database.php';

$pluginHelper = new PluginHelper();
$database = new Database();
$conn = $database->getConnection();

$order_id = $_GET['order_id'] ?? 0;
$paypal_order_id = $_GET['order_id_paypal'] ?? '';

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

// In a real implementation, you would:
// 1. Verify the payment with PayPal
// 2. Capture the payment
// 3. Update the order status based on payment result

// For demo purposes, mark as paid
$query = "UPDATE orders SET payment_status = 'paid', payment_transaction_id = ? WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->execute([$paypal_order_id, $order_id]);

// Clear cart
$_SESSION['cart'] = [];

// Redirect to success page
header('Location: index.php?page=checkout_success&order=' . $order['order_number']);
exit();
