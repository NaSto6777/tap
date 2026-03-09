<?php
$siteName = $settings->getSetting('site_name', 'Ecommerce Store');
$siteUrl = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmation</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #f8f9fa; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
        .content { background: white; padding: 30px; border: 1px solid #dee2e6; }
        .footer { background: #f8f9fa; padding: 20px; text-align: center; border-radius: 0 0 8px 8px; font-size: 14px; color: #6c757d; }
        .order-details { background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; }
        .order-item { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #dee2e6; }
        .order-item:last-child { border-bottom: none; }
        .total { font-weight: bold; font-size: 18px; color: #007bff; }
        .btn { display: inline-block; padding: 12px 24px; background: #007bff; color: white; text-decoration: none; border-radius: 6px; margin: 10px 0; }
        .btn:hover { background: #0056b3; }
        @media (max-width: 600px) {
            .container { padding: 10px; }
            .content { padding: 20px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><?php echo htmlspecialchars($siteName); ?></h1>
            <h2>Order Confirmation</h2>
        </div>
        
        <div class="content">
            <p>Dear <?php echo htmlspecialchars($orderData['customer_name']); ?>,</p>
            
            <p>Thank you for your order! We've received your order and will process it shortly.</p>
            
            <div class="order-details">
                <h3>Order Details</h3>
                <p><strong>Order Number:</strong> <?php echo htmlspecialchars($orderData['order_number']); ?></p>
                <p><strong>Order Date:</strong> <?php echo date('F j, Y \a\t g:i A', strtotime($orderData['created_at'])); ?></p>
                <p><strong>Payment Method:</strong> <?php echo htmlspecialchars($orderData['payment_method'] ?? 'Cash on Delivery'); ?></p>
                <p><strong>Status:</strong> <?php echo htmlspecialchars($orderData['status'] ?? 'Pending'); ?></p>
            </div>
            
            <div class="order-details">
                <h3>Shipping Address</h3>
                <p><?php echo nl2br(htmlspecialchars($orderData['shipping_address'])); ?></p>
            </div>
            
            <div class="order-details">
                <h3>Order Items</h3>
                <?php foreach ($orderData['items'] as $item): ?>
                <div class="order-item">
                    <div>
                        <strong><?php echo htmlspecialchars($item['product_name']); ?></strong><br>
                        <small>SKU: <?php echo htmlspecialchars($item['product_sku']); ?> | Qty: <?php echo $item['quantity']; ?></small>
                    </div>
                    <div><?php echo $price_prefix . number_format($item['total'], 2) . $price_suffix; ?></div>
                </div>
                <?php endforeach; ?>
                
                <div class="order-item">
                    <div><strong>Subtotal:</strong></div>
                    <div><?php echo $price_prefix . number_format($orderData['subtotal'], 2) . $price_suffix; ?></div>
                </div>
                
                <?php if ($orderData['tax_amount'] > 0): ?>
                <div class="order-item">
                    <div><strong>Tax:</strong></div>
                    <div><?php echo $price_prefix . number_format($orderData['tax_amount'], 2) . $price_suffix; ?></div>
                </div>
                <?php endif; ?>
                
                <?php if ($orderData['shipping_amount'] > 0): ?>
                <div class="order-item">
                    <div><strong>Shipping:</strong></div>
                    <div><?php echo $price_prefix . number_format($orderData['shipping_amount'], 2) . $price_suffix; ?></div>
                </div>
                <?php endif; ?>
                
                <div class="order-item total">
                    <div><strong>Total:</strong></div>
                    <div><?php echo $price_prefix . number_format($orderData['total_amount'], 2) . $price_suffix; ?></div>
                </div>
            </div>
            
            <p>We'll send you another email when your order ships. If you have any questions, please don't hesitate to contact us.</p>
            
            <p>Thank you for choosing <?php echo htmlspecialchars($siteName); ?>!</p>
            
            <div style="text-align: center;">
                <a href="<?php echo $siteUrl; ?>" class="btn">Visit Our Store</a>
            </div>
        </div>
        
        <div class="footer">
            <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($siteName); ?>. All rights reserved.</p>
            <p>This email was sent to <?php echo htmlspecialchars($orderData['customer_email']); ?></p>
        </div>
    </div>
</body>
</html>
