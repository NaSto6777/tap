<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/StoreContext.php';
require_once __DIR__ . '/../../config/settings.php';
require_once __DIR__ . '/../../config/language.php';
require_once __DIR__ . '/../../config/plan_helper.php';
require_once __DIR__ . '/../../config/billing_helper.php';

$store_id = StoreContext::getId();
$database = new Database();
$conn = $database->getConnection();
$settings = new Settings($conn, $store_id);

// Get currency and symbol
$currency = $settings->getSetting('currency', 'USD');
$custom_currency = $settings->getSetting('custom_currency', '');
if ($currency === 'CUSTOM' && !empty($custom_currency)) {
    $currency_symbol = $custom_currency;
} else {
    $currency_symbol = $currency === 'USD' ? '$' : ($currency === 'EUR' ? '€' : ($currency === 'GBP' ? '£' : ($currency === 'TND' ? 'TND' : $currency . ' ')));
}
$currency_position = $settings->getSetting('currency_position', 'left');

// Initialize language
Language::init();
$t = function($key, $default = null) {
    return Language::t($key, $default);
};

// Get order ID
$order_id = $_GET['id'] ?? 0;
$print_mode = isset($_GET['print']) && $_GET['print'] == '1';

if (!$order_id) {
    header('Location: ?page=orders');
    exit;
}

// Get site settings for invoice
$site_name = $settings->getSetting('site_name', 'Ecommerce Store');
$site_description = $settings->getSetting('site_description', '');
$logo_path = $settings->getSetting('logo', '');

// Check if payment_method and payment_transaction_id columns exist
function orderColumnExists($conn, $column) {
    try {
        $stmt = $conn->prepare("SHOW COLUMNS FROM orders LIKE ?");
        $stmt->execute([$column]);
        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return false;
    }
}

$hasPaymentMethod = orderColumnExists($conn, 'payment_method');
$hasPaymentTransaction = orderColumnExists($conn, 'payment_transaction_id');

// Get order data (scoped to store)
$query = "SELECT * FROM orders WHERE id = ? AND store_id = ?";
$stmt = $conn->prepare($query);
$stmt->execute([$order_id, $store_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header('Location: ?page=orders&error=' . urlencode($t('order_not_found', 'Order not found')));
    exit;
}

if (!PlanHelper::canViewOrder($conn, $store_id, $order_id)) {
    header('Location: ?page=orders&error=' . urlencode($t('order_limit_upgrade', 'This order is outside your plan limit. Upgrade to view more orders.')));
    exit;
}

// Get order items
$query = "SELECT * FROM order_items WHERE order_id = ? ORDER BY id";
$stmt = $conn->prepare($query);
$stmt->execute([$order_id]);
$order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

BillingHelper::init($conn);
$order_detail_locked = BillingHelper::shouldLockOrder($store_id, $order['created_at']);
$LOCKED = '*** LOCKED ***';

// Format currency helper
function formatCurrency($amount, $symbol, $position) {
    if ($position === 'left') {
        return $symbol . number_format($amount, 2);
    } else {
        return number_format($amount, 2) . ' ' . $symbol;
    }
}

// Format address helper
function formatAddress($address) {
    if (empty($address)) return 'N/A';
    return nl2br(htmlspecialchars($address));
}
?>

<?php if ($print_mode): ?>
<!-- Print Invoice View -->
<!DOCTYPE html>
<html lang="<?php echo Language::getCurrentLanguage(); ?>" dir="<?php echo Language::isRTL() ? 'rtl' : 'ltr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $t('invoice', 'Invoice'); ?> - <?php echo htmlspecialchars($order['order_number']); ?></title>
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Arial', 'Helvetica', sans-serif;
            font-size: 12pt;
            line-height: 1.6;
            color: #333;
            background: #fff;
            padding: 20px;
        }
        .invoice-container {
            max-width: 800px;
            margin: 0 auto;
            background: #fff;
        }
        .invoice-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 3px solid #333;
        }
        .company-logo {
            max-width: 150px;
            max-height: 80px;
            margin-bottom: 10px;
            object-fit: contain;
        }
        .company-info h1 {
            font-size: 24pt;
            margin-bottom: 5px;
            color: #333;
        }
        .company-info p {
            color: #666;
            font-size: 10pt;
        }
        .barcode-container {
            text-align: center;
            margin-top: 20px;
            padding: 10px;
            background: #f9f9f9;
        }
        .barcode-container svg {
            max-width: 100%;
            height: 50px;
        }
        .barcode-text {
            font-size: 10pt;
            color: #666;
            margin-top: 5px;
        }
        .invoice-info {
            text-align: right;
        }
        .invoice-info h2 {
            font-size: 20pt;
            margin-bottom: 10px;
            color: #333;
        }
        .invoice-info p {
            color: #666;
            font-size: 10pt;
            margin: 3px 0;
        }
        .invoice-section {
            margin-bottom: 25px;
        }
        .invoice-section h3 {
            font-size: 14pt;
            margin-bottom: 10px;
            color: #333;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
        }
        .two-columns {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 25px;
        }
        .address-box {
            background: #f9f9f9;
            padding: 8px 10px;
            border-radius: 4px;
            font-size: 10pt;
            line-height: 1.4;
        }
        .address-box strong {
            display: block;
            margin-bottom: 4px;
            color: #333;
            font-size: 11pt;
        }
        .address-box p {
            color: #666;
            white-space: pre-line;
            margin: 3px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        table thead {
            background: #333;
            color: #fff;
        }
        table th {
            padding: 12px;
            text-align: left;
            font-weight: 600;
        }
        table td {
            padding: 10px 12px;
            border-bottom: 1px solid #ddd;
        }
        table tbody tr:hover {
            background: #f9f9f9;
        }
        .totals-section {
            margin-top: 20px;
            margin-left: auto;
            width: 300px;
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #ddd;
        }
        .total-row.final {
            border-top: 2px solid #333;
            border-bottom: 2px solid #333;
            margin-top: 10px;
            padding: 12px 0;
            font-size: 14pt;
            font-weight: bold;
        }
        @media print {
            body {
                padding: 0;
            }
            .invoice-container {
                max-width: 100%;
            }
            @page {
                margin: 1cm;
            }
        }
    </style>
</head>
<body>
    <div class="invoice-container">
        <div class="invoice-header">
            <div class="company-info">
                <?php if (!empty($logo_path)): 
                    $logo_display = (strpos($logo_path, 'uploads/') === 0) ? '../' . $logo_path : $logo_path;
                ?>
                    <img src="<?php echo htmlspecialchars($logo_display); ?>" alt="Logo" class="company-logo">
                <?php endif; ?>
                <h1><?php echo htmlspecialchars($site_name); ?></h1>
            </div>
            <div class="invoice-info">
                <h2><?php echo strtoupper($t('invoice', 'INVOICE')); ?></h2>
                <p><strong><?php echo $t('invoice_number', 'Invoice #'); ?>:</strong> <?php echo htmlspecialchars($order['order_number']); ?></p>
                <p><strong><?php echo $t('date'); ?>:</strong> <?php echo date('F j, Y', strtotime($order['created_at'])); ?></p>
                <p><strong><?php echo $t('status'); ?>:</strong> <?php 
                    $status_translations = [
                        'pending' => $t('pending'),
                        'processing' => $t('processing'),
                        'shipped' => $t('shipped', 'Shipped'),
                        'delivered' => $t('delivered'),
                        'cancelled' => $t('cancelled', 'Cancelled')
                    ];
                    echo $status_translations[$order['status']] ?? ucfirst($order['status']);
                ?></p>
            </div>
        </div>

        <div class="two-columns">
            <div class="invoice-section">
                <h3><?php echo $t('bill_to', 'Bill To'); ?></h3>
                <div class="address-box">
                    <strong><?php echo $order_detail_locked ? $LOCKED : htmlspecialchars($order['customer_name']); ?></strong>
                    <p><?php echo $order_detail_locked ? $LOCKED : nl2br(htmlspecialchars($order['billing_address'] ?: $order['shipping_address'])); ?></p>
                    <p style="margin-top: 4px;">
                        <?php echo $t('email'); ?>: <?php echo $order_detail_locked ? $LOCKED : htmlspecialchars($order['customer_email']); ?><br>
                        <?php if ($order['customer_phone'] || $order_detail_locked): ?>
                        <?php echo $t('phone', 'Phone'); ?>: <?php echo $order_detail_locked ? $LOCKED : htmlspecialchars($order['customer_phone'] ?? ''); ?>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            <div class="invoice-section">
                <h3><?php echo $t('ship_to', 'Ship To'); ?></h3>
                <div class="address-box">
                    <strong><?php echo $order_detail_locked ? $LOCKED : htmlspecialchars($order['customer_name']); ?></strong>
                    <p><?php echo $order_detail_locked ? $LOCKED : nl2br(htmlspecialchars($order['shipping_address'])); ?></p>
                </div>
            </div>
        </div>

        <div class="invoice-section">
            <h3><?php echo $t('order_items', 'Order Items'); ?></h3>
            <table>
                <thead>
                    <tr>
                        <th><?php echo $t('product'); ?></th>
                        <th><?php echo $t('sku'); ?></th>
                        <th style="text-align: center;"><?php echo $t('quantity'); ?></th>
                        <th style="text-align: right;"><?php echo $t('unit_price', 'Unit Price'); ?></th>
                        <th style="text-align: right;"><?php echo $t('total'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($order_items as $item): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                        <td><?php echo htmlspecialchars($item['product_sku'] ?? 'N/A'); ?></td>
                        <td style="text-align: center;"><?php echo $item['quantity']; ?></td>
                        <td style="text-align: right;"><?php echo formatCurrency($item['price'], $currency_symbol, $currency_position); ?></td>
                        <td style="text-align: right;"><?php echo formatCurrency($item['total'], $currency_symbol, $currency_position); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="totals-section">
            <div class="total-row">
                <span><?php echo $t('subtotal', 'Subtotal'); ?>:</span>
                <span><?php echo formatCurrency($order['subtotal'], $currency_symbol, $currency_position); ?></span>
            </div>
            <?php if ($order['tax_amount'] > 0): ?>
            <div class="total-row">
                <span><?php echo $t('tax', 'Tax'); ?>:</span>
                <span><?php echo formatCurrency($order['tax_amount'], $currency_symbol, $currency_position); ?></span>
            </div>
            <?php endif; ?>
            <?php if ($order['shipping_amount'] > 0): ?>
            <div class="total-row">
                <span><?php echo $t('shipping', 'Shipping'); ?>:</span>
                <span><?php echo formatCurrency($order['shipping_amount'], $currency_symbol, $currency_position); ?></span>
            </div>
            <?php endif; ?>
            <div class="total-row final">
                <span><?php echo $t('total'); ?>:</span>
                    <span><?php echo $order_detail_locked ? $LOCKED : formatCurrency($order['total_amount'], $currency_symbol, $currency_position); ?></span>
                </div>
            </div>

            <!-- Barcode -->
            <div class="barcode-container">
                <svg id="barcode-<?php echo $order['id']; ?>"></svg>
                <div class="barcode-text"><?php echo htmlspecialchars($order['order_number']); ?></div>
            </div>
        </div>
    <script>
        window.onload = function() {
            // Generate barcode
            JsBarcode("#barcode-<?php echo $order['id']; ?>", "<?php echo htmlspecialchars($order['order_number']); ?>", {
                format: "CODE128",
                width: 2,
                height: 50,
                displayValue: false,
                margin: 5
            });
            
            // Auto-print after a short delay
            setTimeout(function() {
                window.print();
            }, 500);
        };
    </script>
</body>
</html>
<?php else: ?>
<!-- Order View Details Page -->
<div class="order-view-container">
    <!-- Header Section -->
    <div class="order-view-header">
        <div class="header-left">
            <a href="?page=orders" class="back-btn">
                <i class="fas fa-arrow-left"></i> <?php echo $t('back_to_orders', 'Back to Orders'); ?>
            </a>
            <div class="order-title-section">
                <div class="title-row">
                    <h1 class="order-title"><?php echo $t('order_number'); ?> #<?php echo htmlspecialchars($order['order_number']); ?></h1>
                    <span class="status-badge status-<?php echo htmlspecialchars($order['status']); ?>">
                        <?php 
                        $status_translations = [
                            'pending' => $t('pending'),
                            'processing' => $t('processing'),
                            'shipped' => $t('shipped', 'Shipped'),
                            'delivered' => $t('delivered'),
                            'cancelled' => $t('cancelled', 'Cancelled')
                        ];
                        echo $status_translations[$order['status']] ?? ucfirst($order['status']);
                        ?>
                    </span>
                    <?php if (isset($order['payment_status'])): ?>
                        <span class="payment-badge payment-<?php echo htmlspecialchars($order['payment_status']); ?>">
                            <?php 
                            $payment_translations = [
                                'pending' => $t('pending'),
                                'paid' => $t('paid', 'Paid'),
                                'failed' => $t('failed', 'Failed'),
                                'refunded' => $t('refunded', 'Refunded')
                            ];
                            echo $payment_translations[$order['payment_status']] ?? ucfirst($order['payment_status']);
                            ?>
                        </span>
                    <?php endif; ?>
                </div>
                <div class="order-meta-header">
                    <span class="date-display">
                        <i class="fas fa-calendar"></i> <?php echo date('M j, Y g:i A', strtotime($order['created_at'])); ?>
                    </span>
                    <span class="items-count">
                        <i class="fas fa-box"></i> <?php echo count($order_items); ?> <?php echo $t('items'); ?>
                    </span>
                    <span class="total-display">
                        <i class="fas fa-dollar-sign"></i> <?php echo $order_detail_locked ? $LOCKED : formatCurrency($order['total_amount'], $currency_symbol, $currency_position); ?>
                    </span>
                </div>
            </div>
        </div>
        <div class="header-actions">
            <button class="btn-action secondary" onclick="editOrderStatus(<?php echo $order['id']; ?>, '<?php echo htmlspecialchars($order['status']); ?>', '<?php echo isset($order['payment_status']) ? htmlspecialchars($order['payment_status']) : 'pending'; ?>')">
                <i class="fas fa-edit"></i> <?php echo $t('update_status', 'Update Status'); ?>
            </button>
            <button class="btn-action primary" onclick="window.print()">
                <i class="fas fa-print"></i> <?php echo $t('print_invoice'); ?>
            </button>
        </div>
    </div>

    <!-- Main Content -->
    <div class="order-view-content">
        <!-- Left Column: Order Summary & Customer Info -->
        <div class="order-view-left">
            <!-- Order Summary -->
            <div class="detail-section">
                <div class="section-header">
                    <h2><i class="fas fa-receipt"></i> <?php echo $t('order_summary', 'Order Summary'); ?></h2>
                </div>
                <div class="section-content">
                    <div class="field-group">
                        <label><?php echo $t('order_number'); ?></label>
                        <div class="readonly-field">
                            <?php echo htmlspecialchars($order['order_number']); ?>
                        </div>
                    </div>
                    <div class="field-group">
                        <label><?php echo $t('order_date'); ?></label>
                        <div class="readonly-field">
                            <?php echo date('F j, Y g:i A', strtotime($order['created_at'])); ?>
                        </div>
                    </div>
                    <?php if ($order['updated_at'] !== $order['created_at']): ?>
                        <div class="field-group">
                            <label><?php echo $t('last_updated'); ?></label>
                            <div class="readonly-field">
                                <?php echo date('F j, Y g:i A', strtotime($order['updated_at'])); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    <div class="field-group">
                        <label><?php echo $t('order_status'); ?></label>
                        <div class="readonly-field">
                            <span class="status-badge status-<?php echo htmlspecialchars($order['status']); ?>">
                                <?php 
                                $status_translations = [
                                    'pending' => $t('pending'),
                                    'processing' => $t('processing'),
                                    'shipped' => $t('shipped', 'Shipped'),
                                    'delivered' => $t('delivered'),
                                    'cancelled' => $t('cancelled', 'Cancelled')
                                ];
                                echo $status_translations[$order['status']] ?? ucfirst($order['status']);
                                ?>
                            </span>
                        </div>
                    </div>
                    <?php if (isset($order['payment_status'])): ?>
                        <div class="field-group">
                            <label><?php echo $t('payment_status', 'Payment Status'); ?></label>
                            <div class="readonly-field">
                                <span class="payment-badge payment-<?php echo htmlspecialchars($order['payment_status']); ?>">
                                    <?php 
                                    $payment_translations = [
                                        'pending' => $t('pending'),
                                        'paid' => $t('paid', 'Paid'),
                                        'failed' => $t('failed', 'Failed'),
                                        'refunded' => $t('refunded', 'Refunded')
                                    ];
                                    echo $payment_translations[$order['payment_status']] ?? ucfirst($order['payment_status']);
                                    ?>
                                </span>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php if ($hasPaymentMethod && !empty($order['payment_method'])): ?>
                        <div class="field-group">
                            <label><?php echo $t('payment_method', 'Payment Method'); ?></label>
                            <div class="readonly-field">
                                <?php echo strtoupper(htmlspecialchars($order['payment_method'])); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php if ($hasPaymentTransaction && !empty($order['payment_transaction_id'])): ?>
                        <div class="field-group">
                            <label><?php echo $t('transaction_id', 'Transaction ID'); ?></label>
                            <div class="readonly-field">
                                <?php echo htmlspecialchars($order['payment_transaction_id']); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($order_detail_locked): ?>
            <div class="alert alert-warning upgrade-banner" style="margin-bottom: 1rem;">
                <strong><?php echo $t('upgrade_required', 'Upgrade Required'); ?></strong> — <?php echo $t('order_placed_after_expiry', 'This order was placed after your subscription ended.'); ?> <a href="#" class="btn btn-sm btn-primary"><?php echo $t('pay_to_unlock', 'Pay to Unlock'); ?></a>
            </div>
            <?php endif; ?>
            <!-- Customer Information -->
            <div class="detail-section">
                <div class="section-header">
                    <h2><i class="fas fa-user"></i> <?php echo $t('customer_information', 'Customer Information'); ?></h2>
                </div>
                <div class="section-content">
                    <div class="field-group">
                        <label><?php echo $t('customer_name', 'Customer Name'); ?></label>
                        <div class="readonly-field">
                            <?php echo $order_detail_locked ? $LOCKED : htmlspecialchars($order['customer_name']); ?>
                        </div>
                    </div>
                    <div class="field-group">
                        <label><?php echo $t('email'); ?></label>
                        <div class="readonly-field">
                            <?php if ($order_detail_locked): ?><?php echo $LOCKED; ?><?php else: ?><a href="mailto:<?php echo htmlspecialchars($order['customer_email']); ?>"><?php echo htmlspecialchars($order['customer_email']); ?></a><?php endif; ?>
                        </div>
                    </div>
                    <?php if (!empty($order['customer_phone']) || $order_detail_locked): ?>
                        <div class="field-group">
                            <label><?php echo $t('phone', 'Phone'); ?></label>
                            <div class="readonly-field">
                                <?php if ($order_detail_locked): ?><?php echo $LOCKED; ?><?php else: ?><a href="tel:<?php echo htmlspecialchars($order['customer_phone']); ?>"><?php echo htmlspecialchars($order['customer_phone']); ?></a><?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Shipping Address -->
            <div class="detail-section">
                <div class="section-header">
                    <h2><i class="fas fa-truck"></i> <?php echo $t('shipping_address', 'Shipping Address'); ?></h2>
                </div>
                <div class="section-content">
                    <div class="address-field">
                        <?php echo $order_detail_locked ? $LOCKED : formatAddress($order['shipping_address']); ?>
                    </div>
                </div>
            </div>

            <!-- Billing Address -->
            <?php if (!empty($order['billing_address']) || $order_detail_locked): ?>
                <div class="detail-section">
                    <div class="section-header">
                        <h2><i class="fas fa-credit-card"></i> <?php echo $t('billing_address', 'Billing Address'); ?></h2>
                    </div>
                    <div class="section-content">
                        <div class="address-field">
                            <?php echo $order_detail_locked ? $LOCKED : formatAddress($order['billing_address']); ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Right Column: Order Items & Totals -->
        <div class="order-view-right">
            <!-- Order Items -->
            <div class="detail-section">
                <div class="section-header">
                    <h2><i class="fas fa-shopping-cart"></i> <?php echo $t('order_items', 'Order Items'); ?> (<?php echo count($order_items); ?>)</h2>
                </div>
                <div class="section-content">
                    <div class="order-items-table">
                        <table>
                            <thead>
                                <tr>
                                    <th><?php echo $t('product'); ?></th>
                                    <th><?php echo $t('sku'); ?></th>
                                    <th><?php echo $t('quantity'); ?></th>
                                    <th><?php echo $t('unit_price', 'Unit Price'); ?></th>
                                    <th><?php echo $t('total'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($order_items as $item): ?>
                                    <tr>
                                        <td>
                                            <div class="product-name-cell">
                                                <?php echo htmlspecialchars($item['product_name']); ?>
                                                <?php if (isset($item['product_id']) && $item['product_id']): ?>
                                                    <a href="?page=product_quick_view&id=<?php echo $item['product_id']; ?>" class="product-link" title="<?php echo $t('view_product', 'View Product'); ?>">
                                                        <i class="fas fa-external-link-alt"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($item['product_sku'] ?? 'N/A'); ?></td>
                                        <td><?php echo $item['quantity']; ?></td>
                                        <td><?php echo formatCurrency($item['price'], $currency_symbol, $currency_position); ?></td>
                                        <td class="total-cell"><?php echo formatCurrency($item['total'], $currency_symbol, $currency_position); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Order Totals -->
            <div class="detail-section">
                <div class="section-header">
                    <h2><i class="fas fa-calculator"></i> <?php echo $t('order_totals', 'Order Totals'); ?></h2>
                </div>
                <div class="section-content">
                    <div class="totals-list">
                        <div class="total-row">
                            <span class="total-label"><?php echo $t('subtotal', 'Subtotal'); ?></span>
                            <span class="total-value"><?php echo formatCurrency($order['subtotal'], $currency_symbol, $currency_position); ?></span>
                        </div>
                        <?php if ($order['tax_amount'] > 0): ?>
                            <div class="total-row">
                                <span class="total-label"><?php echo $t('tax', 'Tax'); ?></span>
                                <span class="total-value"><?php echo formatCurrency($order['tax_amount'], $currency_symbol, $currency_position); ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if ($order['shipping_amount'] > 0): ?>
                            <div class="total-row">
                                <span class="total-label"><?php echo $t('shipping', 'Shipping'); ?></span>
                                <span class="total-value"><?php echo formatCurrency($order['shipping_amount'], $currency_symbol, $currency_position); ?></span>
                            </div>
                        <?php endif; ?>
                        <div class="total-row total-final">
                            <span class="total-label"><?php echo $t('total_amount', 'Total Amount'); ?></span>
                            <span class="total-value"><?php echo $order_detail_locked ? $LOCKED : formatCurrency($order['total_amount'], $currency_symbol, $currency_position); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function editOrderStatus(orderId, currentStatus, currentPayment) {
    // Store order ID and statuses in sessionStorage and navigate to orders page
    sessionStorage.setItem('editOrderStatusId', orderId);
    sessionStorage.setItem('editOrderStatus', currentStatus);
    sessionStorage.setItem('editOrderPayment', currentPayment);
    window.location.href = '?page=orders';
}
</script>

<style>
.order-view-container {
    padding: 2rem;
    max-width: 1400px;
    margin: 0 auto;
}

.order-view-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 2rem;
    padding-bottom: 1.5rem;
    border-bottom: 2px solid var(--border-primary);
}

.header-left {
    flex: 1;
}

.back-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--text-secondary);
    text-decoration: none;
    margin-bottom: 1rem;
    transition: var(--transition-colors);
}

.back-btn:hover {
    color: var(--color-primary);
}

.order-title-section {
    margin-top: 0.5rem;
}

.title-row {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 0.75rem;
}

.order-title {
    font-size: 2rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0;
}

.status-badge,
.payment-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.375rem 0.75rem;
    border-radius: 6px;
    font-size: 0.875rem;
    font-weight: 600;
    text-transform: uppercase;
}

.status-badge.status-pending {
    background: var(--color-warning-light);
    color: var(--color-warning-dark);
}

.status-badge.status-processing {
    background: var(--color-info-light);
    color: var(--color-info-dark);
}

.status-badge.status-shipped {
    background: var(--color-primary-light);
    color: var(--color-primary-dark);
}

.status-badge.status-delivered {
    background: var(--color-success-light);
    color: var(--color-success-dark);
}

.status-badge.status-cancelled {
    background: var(--color-error-light);
    color: var(--color-error-dark);
}

.payment-badge.payment-pending {
    background: var(--color-warning-light);
    color: var(--color-warning-dark);
}

.payment-badge.payment-paid {
    background: var(--color-success-light);
    color: var(--color-success-dark);
}

.payment-badge.payment-failed {
    background: var(--color-error-light);
    color: var(--color-error-dark);
}

.payment-badge.payment-refunded {
    background: var(--color-secondary-light);
    color: var(--color-secondary-dark);
}

.order-meta-header {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    flex-wrap: wrap;
}

.date-display,
.items-count,
.total-display {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--text-secondary);
    font-size: 0.9375rem;
}

.header-actions {
    display: flex;
    gap: 1rem;
}

.btn-action {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 6px;
    font-weight: 600;
    cursor: pointer;
    transition: var(--transition-all);
    text-decoration: none;
}

.btn-action.primary {
    background: var(--color-primary);
    color: white;
}

.btn-action.primary:hover {
    background: var(--color-primary-hover);
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}

.btn-action.secondary {
    background: var(--bg-secondary);
    color: var(--text-primary);
    border: 1px solid var(--border-primary);
}

.btn-action.secondary:hover {
    background: var(--bg-tertiary);
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}

.order-view-content {
    display: grid;
    grid-template-columns: 1fr 1.5fr;
    gap: 2rem;
    margin-top: 2rem;
}

.detail-section {
    background: var(--bg-card);
    border: 1px solid var(--border-primary);
    border-radius: 6px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--border-primary);
}

.section-header h2 {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.section-content {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.field-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.field-group label {
    font-weight: 600;
    color: var(--text-secondary);
    font-size: 0.875rem;
}

.readonly-field {
    color: var(--text-primary);
    font-size: 0.9375rem;
    padding: 0.5rem 0;
}

.readonly-field a {
    color: var(--color-primary);
    text-decoration: none;
}

.readonly-field a:hover {
    text-decoration: underline;
}

.address-field {
    color: var(--text-primary);
    font-size: 0.9375rem;
    line-height: 1.6;
    white-space: pre-line;
}

.order-items-table {
    overflow-x: auto;
}

.order-items-table table {
    width: 100%;
    border-collapse: collapse;
}

.order-items-table thead {
    background: var(--bg-secondary);
}

.order-items-table th {
    padding: 0.75rem;
    text-align: left;
    font-weight: 600;
    color: var(--text-primary);
    font-size: 0.875rem;
    border-bottom: 2px solid var(--border-primary);
}

.order-items-table td {
    padding: 1rem 0.75rem;
    border-bottom: 1px solid var(--border-primary);
    color: var(--text-secondary);
    font-size: 0.9375rem;
}

.order-items-table tbody tr:hover {
    background: var(--bg-secondary);
}

.product-name-cell {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.product-link {
    color: var(--color-primary);
    text-decoration: none;
    opacity: 0.7;
    transition: var(--transition-all);
}

.product-link:hover {
    opacity: 1;
}

.total-cell {
    font-weight: 600;
    color: var(--text-primary);
}

.totals-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.total-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem 0;
    border-bottom: 1px solid var(--border-primary);
}

.total-row:last-child {
    border-bottom: none;
}

.total-row.total-final {
    border-top: 2px solid var(--border-primary);
    padding-top: 1rem;
    margin-top: 0.5rem;
    font-size: 1.125rem;
}

.total-label {
    font-weight: 600;
    color: var(--text-primary);
}

.total-row.total-final .total-label {
    font-size: 1.125rem;
}

.total-value {
    font-weight: 600;
    color: var(--text-primary);
}

.total-row.total-final .total-value {
    font-size: 1.25rem;
    color: var(--color-primary);
}

@media (max-width: 1024px) {
    .order-view-content {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .order-view-container {
        padding: 1rem;
    }

    .order-view-header {
        flex-direction: column;
        gap: 1rem;
    }

    .header-actions {
        width: 100%;
        flex-direction: column;
    }

    .btn-action {
        width: 100%;
        justify-content: center;
    }

    .title-row {
        flex-wrap: wrap;
    }

    .order-meta-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
}

@media print {
    .order-view-header,
    .header-actions,
    .back-btn {
        display: none;
    }

    .order-view-container {
        padding: 0;
    }

    .detail-section {
        page-break-inside: avoid;
    }
}
</style>

<?php endif; ?>
