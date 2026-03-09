<?php
$siteName = $settings->getSetting('site_name', 'Ecommerce Store');
$siteUrl = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Status Update</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #f8f9fa; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
        .content { background: white; padding: 30px; border: 1px solid #dee2e6; }
        .footer { background: #f8f9fa; padding: 20px; text-align: center; border-radius: 0 0 8px 8px; font-size: 14px; color: #6c757d; }
        .status-badge { display: inline-block; padding: 8px 16px; border-radius: 20px; font-weight: bold; text-transform: uppercase; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-processing { background: #cce5ff; color: #004085; }
        .status-shipped { background: #d4edda; color: #155724; }
        .status-delivered { background: #d1ecf1; color: #0c5460; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        .order-details { background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; }
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
            <h2>Order Status Update</h2>
        </div>
        
        <div class="content">
            <p>Dear <?php echo htmlspecialchars($orderData['customer_name']); ?>,</p>
            
            <p>Your order status has been updated:</p>
            
            <div class="order-details">
                <h3>Order Information</h3>
                <p><strong>Order Number:</strong> <?php echo htmlspecialchars($orderData['order_number']); ?></p>
                <p><strong>New Status:</strong> 
                    <span class="status-badge status-<?php echo strtolower($newStatus); ?>">
                        <?php echo htmlspecialchars($newStatus); ?>
                    </span>
                </p>
                <p><strong>Updated:</strong> <?php echo date('F j, Y \a\t g:i A'); ?></p>
            </div>
            
            <?php if ($newStatus === 'Shipped'): ?>
            <p>Great news! Your order has been shipped and is on its way to you. You should receive it within the next few business days.</p>
            <?php elseif ($newStatus === 'Delivered'): ?>
            <p>Your order has been delivered! We hope you enjoy your purchase. If you have any questions or need assistance, please don't hesitate to contact us.</p>
            <?php elseif ($newStatus === 'Processing'): ?>
            <p>Your order is being processed and will be shipped soon. We'll send you another update when it ships.</p>
            <?php elseif ($newStatus === 'Cancelled'): ?>
            <p>We're sorry to inform you that your order has been cancelled. If you have any questions about this cancellation, please contact our customer service team.</p>
            <?php endif; ?>
            
            <div class="order-details">
                <h3>Shipping Address</h3>
                <p><?php echo nl2br(htmlspecialchars($orderData['shipping_address'])); ?></p>
            </div>
            
            <p>If you have any questions about your order, please don't hesitate to contact us.</p>
            
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
