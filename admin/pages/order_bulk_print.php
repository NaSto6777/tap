<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/StoreContext.php';
require_once __DIR__ . '/../../config/settings.php';
require_once __DIR__ . '/../../config/plan_helper.php';

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

// Get order IDs from query string
$order_ids = isset($_GET['ids']) ? explode(',', $_GET['ids']) : [];
if (empty($order_ids)) {
    header('Location: ?page=orders');
    exit;
}

// Get site settings for invoice
$site_name = $settings->getSetting('site_name', 'Ecommerce Store');
$site_description = $settings->getSetting('site_description', '');
$logo_path = $settings->getSetting('logo', '');

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

// Fetch all orders (only those visible by plan)
$orders_data = [];
foreach ($order_ids as $order_id) {
    $order_id = (int)trim($order_id);
    if ($order_id <= 0) continue;
    if (!PlanHelper::canViewOrder($conn, $store_id, $order_id)) continue;
    
    // Get order data (scoped to store)
    $query = "SELECT * FROM orders WHERE id = ? AND store_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->execute([$order_id, $store_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) continue;
    
    // Get order items
    $query = "SELECT * FROM order_items WHERE order_id = ? ORDER BY id";
    $stmt = $conn->prepare($query);
    $stmt->execute([$order_id]);
    $order['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $orders_data[] = $order;
}

if (empty($orders_data)) {
    header('Location: ?page=orders&error=No valid orders found');
    exit;
}

// This is a standalone print page, exit after output
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Invoices Print</title>
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Arial', 'Helvetica', sans-serif;
            font-size: 10pt;
            line-height: 1.4;
            color: #333;
            background: #fff;
            padding: 10px;
        }
        .invoices-wrapper {
            display: flex;
            flex-direction: column;
            gap: 0;
        }
        .invoice-pair {
            display: flex;
            flex-direction: column;
            gap: 0;
            page-break-inside: avoid;
        }
        .invoice-container {
            background: #fff;
            border: 1px solid #ddd;
            padding: 10px;
            page-break-inside: avoid;
            height: 50vh;
            min-height: 50vh;
            max-height: 50vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .invoice-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 8px;
            padding-bottom: 6px;
            border-bottom: 2px solid #333;
        }
        .company-info {
            flex: 1;
        }
        .company-logo {
            max-width: 100px;
            max-height: 50px;
            margin-bottom: 4px;
            object-fit: contain;
        }
        .company-info h1 {
            font-size: 14pt;
            margin-bottom: 2px;
            color: #333;
        }
        .company-info p {
            color: #666;
            font-size: 9pt;
        }
        .invoice-info {
            text-align: right;
        }
        .invoice-info h2 {
            font-size: 14pt;
            margin-bottom: 3px;
            color: #333;
        }
        .invoice-info p {
            color: #666;
            font-size: 8pt;
            margin: 1px 0;
        }
        .barcode-container {
            text-align: center;
            margin-top: auto;
            padding: 3px;
            background: #f9f9f9;
        }
        .barcode-container svg {
            max-width: 100%;
            height: 35px;
        }
        .barcode-text {
            font-size: 7pt;
            color: #666;
            margin-top: 1px;
        }
        .invoice-section {
            margin-bottom: 6px;
        }
        .invoice-section h3 {
            font-size: 9pt;
            margin-bottom: 2px;
            color: #333;
            border-bottom: 1px solid #ddd;
            padding-bottom: 1px;
        }
        .two-columns {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 6px;
            margin-bottom: 6px;
        }
        .address-box {
            background: #f9f9f9;
            padding: 3px 6px;
            border-radius: 2px;
            font-size: 7pt;
            line-height: 1.2;
        }
        .address-box strong {
            display: block;
            margin-bottom: 1px;
            color: #333;
            font-size: 8pt;
        }
        .address-box p {
            color: #666;
            white-space: pre-line;
            margin: 1px 0;
            line-height: 1.2;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 6px 0;
            font-size: 8pt;
        }
        table thead {
            background: #333;
            color: #fff;
        }
        table th {
            padding: 4px 6px;
            text-align: left;
            font-weight: 600;
            font-size: 8pt;
        }
        table td {
            padding: 3px 6px;
            border-bottom: 1px solid #ddd;
        }
        .totals-section {
            margin-top: 6px;
            margin-left: auto;
            width: 220px;
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 2px 0;
            border-bottom: 1px solid #ddd;
            font-size: 8pt;
        }
        .total-row.final {
            border-top: 2px solid #333;
            border-bottom: 2px solid #333;
            margin-top: 4px;
            padding: 4px 0;
            font-size: 10pt;
            font-weight: bold;
        }
            @media print {
            body {
                padding: 0;
            }
            .invoice-pair {
                page-break-after: always;
            }
            .invoice-pair:last-child {
                page-break-after: auto;
            }
            .invoice-container {
                border: none;
                padding: 8px;
                height: 50vh;
                min-height: 50vh;
                max-height: 50vh;
                overflow: hidden;
            }
            @page {
                margin: 1cm;
                size: A4;
            }
        }
    </style>
</head>
<body>
    <div class="invoices-wrapper">
        <?php 
        // Group orders in pairs of 2
        $pairs = array_chunk($orders_data, 2);
        foreach ($pairs as $pair): 
        ?>
        <div class="invoice-pair">
            <?php foreach ($pair as $order): ?>
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
                        <h2>INVOICE</h2>
                        <p><strong>Invoice #:</strong> <?php echo htmlspecialchars($order['order_number']); ?></p>
                        <p><strong>Date:</strong> <?php echo date('F j, Y', strtotime($order['created_at'])); ?></p>
                        <p><strong>Status:</strong> <?php echo ucfirst($order['status']); ?></p>
                    </div>
                </div>

                <div class="two-columns">
                    <div class="invoice-section">
                        <h3>Bill To</h3>
                        <div class="address-box">
                            <strong><?php echo htmlspecialchars($order['customer_name']); ?></strong>
                            <p><?php echo nl2br(htmlspecialchars($order['billing_address'] ?: $order['shipping_address'])); ?></p>
                            <p style="margin-top: 1px; font-size: 7pt;">
                                <?php echo htmlspecialchars($order['customer_email']); ?><?php if ($order['customer_phone']): ?> | <?php echo htmlspecialchars($order['customer_phone']); ?><?php endif; ?>
                            </p>
                        </div>
                    </div>
                    <div class="invoice-section">
                        <h3>Ship To</h3>
                        <div class="address-box">
                            <strong><?php echo htmlspecialchars($order['customer_name']); ?></strong>
                            <p><?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?></p>
                        </div>
                    </div>
                </div>

                <div class="invoice-section">
                    <h3>Order Items</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>SKU</th>
                                <th style="text-align: center;">Qty</th>
                                <th style="text-align: right;">Unit Price</th>
                                <th style="text-align: right;">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($order['items'] as $item): ?>
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
                        <span>Subtotal:</span>
                        <span><?php echo formatCurrency($order['subtotal'], $currency_symbol, $currency_position); ?></span>
                    </div>
                    <?php if ($order['tax_amount'] > 0): ?>
                    <div class="total-row">
                        <span>Tax:</span>
                        <span><?php echo formatCurrency($order['tax_amount'], $currency_symbol, $currency_position); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($order['shipping_amount'] > 0): ?>
                    <div class="total-row">
                        <span>Shipping:</span>
                        <span><?php echo formatCurrency($order['shipping_amount'], $currency_symbol, $currency_position); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="total-row final">
                        <span>Total:</span>
                        <span><?php echo formatCurrency($order['total_amount'], $currency_symbol, $currency_position); ?></span>
                    </div>
                </div>

                <!-- Barcode -->
                <div class="barcode-container">
                    <svg id="barcode-<?php echo $order['id']; ?>"></svg>
                    <div class="barcode-text"><?php echo htmlspecialchars($order['order_number']); ?></div>
                </div>
            </div>
            <?php endforeach; ?>
            
        </div>
        <?php endforeach; ?>
    </div>
    
    <script>
        // Generate barcodes for all orders
        window.onload = function() {
            <?php foreach ($orders_data as $order): ?>
            JsBarcode("#barcode-<?php echo $order['id']; ?>", "<?php echo htmlspecialchars($order['order_number']); ?>", {
                format: "CODE128",
                width: 2,
                height: 40,
                displayValue: false,
                margin: 5
            });
            <?php endforeach; ?>
            
            // Auto-print after a short delay
            setTimeout(function() {
                window.print();
            }, 500);
        };
    </script>
</body>
</html>
<?php
// Exit to prevent admin layout from loading
exit;
?>

