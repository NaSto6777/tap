<?php
require_once __DIR__ . '/../../config/StoreContext.php';
require_once __DIR__ . '/../../config/settings.php';
require_once __DIR__ . '/../../config/language.php';
require_once __DIR__ . '/../../config/plan_helper.php';

$store_id = StoreContext::getId();
$database = new Database();
$conn = $database->getConnection();
$settings = new Settings($conn, $store_id);

$vis_orders = PlanHelper::orderVisibilityCondition($conn, $store_id, 'orders');
$vis_o = PlanHelper::orderVisibilityCondition($conn, $store_id, 'o');
$orders_where = "store_id = ?" . ($vis_orders['sql'] !== '' ? trim($vis_orders['sql']) : '');
$orders_params = array_merge([$store_id], $vis_orders['params']);
$o_where = "o.store_id = ?" . ($vis_o['sql'] !== '' ? trim($vis_o['sql']) : '');
$o_params = array_merge([$store_id], $vis_o['params']);

// Initialize language
Language::init();
$t = function($key, $default = null) {
    return Language::t($key, $default);
};

// Get currency and symbol
$currency = $settings->getSetting('currency', 'USD');
$custom_currency = $settings->getSetting('custom_currency', '');
if ($currency === 'CUSTOM' && !empty($custom_currency)) {
    $currency_symbol = $custom_currency;
} else {
    $currency_symbol = $currency === 'USD' ? '$' : ($currency === 'EUR' ? '€' : ($currency === 'GBP' ? '£' : ($currency === 'TND' ? 'TND' : $currency . ' ')));
}
$currency_position = $settings->getSetting('currency_position', 'left');

// Get statistics (scoped to current store) – normalize to int/float so stat cards never show blank
$stats = ['products' => 0, 'orders' => 0, 'revenue' => 0, 'pending_orders' => 0, 'low_stock' => 0, 'out_of_stock' => 0];

$query = "SELECT COUNT(*) as total FROM products WHERE store_id = ? AND is_active = 1";
$stmt = $conn->prepare($query);
$stmt->execute([$store_id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$stats['products'] = $row ? (int)($row['total'] ?? 0) : 0;

$query = "SELECT COUNT(*) as total FROM orders WHERE $orders_where";
$stmt = $conn->prepare($query);
$stmt->execute($orders_params);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$stats['orders'] = $row ? (int)($row['total'] ?? 0) : 0;

$query = "SELECT SUM(total_amount) as total FROM orders WHERE $orders_where AND payment_status = 'paid'";
$stmt = $conn->prepare($query);
$stmt->execute($orders_params);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$stats['revenue'] = $row ? (float)($row['total'] ?? 0) : 0;

$query = "SELECT COUNT(*) as total FROM orders WHERE $orders_where AND status = 'pending'";
$stmt = $conn->prepare($query);
$stmt->execute($orders_params);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$stats['pending_orders'] = $row ? (int)($row['total'] ?? 0) : 0;

$query = "SELECT COUNT(*) as total FROM products WHERE store_id = ? AND stock_quantity <= 10 AND stock_quantity > 0";
$stmt = $conn->prepare($query);
$stmt->execute([$store_id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$stats['low_stock'] = $row ? (int)($row['total'] ?? 0) : 0;

$query = "SELECT COUNT(*) as total FROM products WHERE store_id = ? AND stock_quantity = 0";
$stmt = $conn->prepare($query);
$stmt->execute([$store_id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$stats['out_of_stock'] = $row ? (int)($row['total'] ?? 0) : 0;

$query = "SELECT o.*, COUNT(oi.id) as item_count 
          FROM orders o 
          LEFT JOIN order_items oi ON o.id = oi.order_id 
          WHERE $o_where
          GROUP BY o.id 
          ORDER BY o.created_at DESC 
          LIMIT 10";
$stmt = $conn->prepare($query);
$stmt->execute($o_params);
$recent_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

$query = "SELECT p.name, p.sku, p.stock_quantity, SUM(oi.quantity) as total_sold, SUM(oi.total) as total_revenue
          FROM order_items oi
          JOIN products p ON oi.product_id = p.id
          JOIN orders o ON oi.order_id = o.id
          WHERE $o_where AND o.payment_status = 'paid'
          GROUP BY p.id, p.name, p.sku, p.stock_quantity
          ORDER BY total_sold DESC
          LIMIT 5";
$stmt = $conn->prepare($query);
$stmt->execute($o_params);
$top_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

$query = "SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            SUM(total_amount) as revenue,
            COUNT(*) as orders
          FROM orders 
          WHERE $orders_where AND payment_status = 'paid' 
          AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
          GROUP BY DATE_FORMAT(created_at, '%Y-%m')
          ORDER BY month";
$stmt = $conn->prepare($query);
$stmt->execute($orders_params);
$monthly_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Net Profit: Order Total - Shipping Cost - Sum(Product Cost Prices)
$net_profit = 0;
$has_cost_price = false;
$has_shipping_cost_actual = false;
try {
    $conn->query("SELECT cost_price FROM products LIMIT 1");
    $has_cost_price = true;
} catch (Exception $e) {}
try {
    $conn->query("SELECT shipping_cost_actual FROM orders LIMIT 1");
    $has_shipping_cost_actual = true;
} catch (Exception $e) {}
if ($has_cost_price && $has_shipping_cost_actual) {
    $profit_query = "SELECT 
        COALESCE(SUM(o.total_amount - COALESCE(o.shipping_cost_actual, o.shipping_amount, 0) - cost_sum.product_costs), 0) as net_profit
        FROM orders o
        INNER JOIN (
            SELECT oi.order_id, SUM(oi.quantity * COALESCE(p.cost_price, 0)) as product_costs
            FROM order_items oi
            JOIN products p ON oi.product_id = p.id
            WHERE oi.store_id = ?
            GROUP BY oi.order_id
        ) cost_sum ON o.id = cost_sum.order_id
        WHERE $o_where AND o.payment_status = 'paid'";
    $profit_stmt = $conn->prepare($profit_query);
    $profit_stmt->execute(array_merge([$store_id], $o_params));
    $net_profit = (float) ($profit_stmt->fetch(PDO::FETCH_ASSOC)['net_profit'] ?? 0);
}

// Monthly profit for chart (revenue + profit per month)
$monthly_profit_data = [];
if ($has_cost_price && $has_shipping_cost_actual) {
    $profit_month_query = "SELECT 
        DATE_FORMAT(o.created_at, '%Y-%m') as month,
        SUM(o.total_amount) as revenue,
        COALESCE(SUM(o.total_amount - COALESCE(o.shipping_cost_actual, o.shipping_amount, 0) - cost_sum.product_costs), 0) as profit
        FROM orders o
        INNER JOIN (
            SELECT oi.order_id, SUM(oi.quantity * COALESCE(p.cost_price, 0)) as product_costs
            FROM order_items oi
            JOIN products p ON oi.product_id = p.id
            WHERE oi.store_id = ?
            GROUP BY oi.order_id
        ) cost_sum ON o.id = cost_sum.order_id
        WHERE $o_where AND o.payment_status = 'paid' AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(o.created_at, '%Y-%m')
        ORDER BY month";
    $profit_month_stmt = $conn->prepare($profit_month_query);
    $profit_month_stmt->execute(array_merge([$store_id], $o_params));
    $monthly_profit_data = $profit_month_stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!-- Modern Dashboard Interface -->
<div class="dashboard-container">
    
    <!-- Statistics Cards Grid (Argon-style) -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-card-body">
                <div class="stat-card-row">
                    <div class="stat-card-main">
                        <h5 class="stat-card-label"><?php echo $t('active_products'); ?></h5>
                        <span class="stat-card-value"><?php echo (int)($stats['products'] ?? 0); ?></span>
                        <p class="stat-card-footer positive"><i class="fas fa-arrow-up"></i> <span>+12% <?php echo $t('from_last_month'); ?></span></p>
                    </div>
                    <div class="stat-card-icon-wrap">
                        <div class="stat-card-icon primary"><i class="fas fa-boxes"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-card-body">
                <div class="stat-card-row">
                    <div class="stat-card-main">
                        <h5 class="stat-card-label"><?php echo $t('total_orders'); ?></h5>
                        <span class="stat-card-value"><?php echo (int)($stats['orders'] ?? 0); ?></span>
                        <p class="stat-card-footer positive"><i class="fas fa-arrow-up"></i> <span>+8% <?php echo $t('from_last_month'); ?></span></p>
                    </div>
                    <div class="stat-card-icon-wrap">
                        <div class="stat-card-icon success"><i class="fas fa-shopping-cart"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-card-body">
                <div class="stat-card-row">
                    <div class="stat-card-main">
                        <h5 class="stat-card-label"><?php echo $t('total_revenue'); ?></h5>
                        <span class="stat-card-value"><?php echo $currency_position === 'left' ? $currency_symbol . number_format((float)($stats['revenue'] ?? 0), 2) : number_format((float)($stats['revenue'] ?? 0), 2) . ' ' . $currency_symbol; ?></span>
                        <p class="stat-card-footer positive"><i class="fas fa-arrow-up"></i> <span>+15% <?php echo $t('from_last_month'); ?></span></p>
                    </div>
                    <div class="stat-card-icon-wrap">
                        <div class="stat-card-icon warning"><i class="fas fa-dollar-sign"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <?php if ($has_cost_price && $has_shipping_cost_actual): ?>
        <div class="stat-card">
            <div class="stat-card-body">
                <div class="stat-card-row">
                    <div class="stat-card-main">
                        <h5 class="stat-card-label"><?php echo $t('net_profit', 'Net Profit'); ?></h5>
                        <span class="stat-card-value"><?php echo $currency_position === 'left' ? $currency_symbol . number_format((float)$net_profit, 2) : number_format((float)$net_profit, 2) . ' ' . $currency_symbol; ?></span>
                        <p class="stat-card-footer positive"><i class="fas fa-chart-line"></i> <span><?php echo $t('after_costs', 'After costs'); ?></span></p>
                    </div>
                    <div class="stat-card-icon-wrap">
                        <div class="stat-card-icon success"><i class="fas fa-coins"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <div class="stat-card">
            <div class="stat-card-body">
                <div class="stat-card-row">
                    <div class="stat-card-main">
                        <h5 class="stat-card-label"><?php echo $t('pending_orders'); ?></h5>
                        <span class="stat-card-value"><?php echo (int)($stats['pending_orders'] ?? 0); ?></span>
                        <p class="stat-card-footer negative"><i class="fas fa-exclamation-circle"></i> <span><?php echo $t('action_needed'); ?></span></p>
                    </div>
                    <div class="stat-card-icon-wrap">
                        <div class="stat-card-icon danger"><i class="fas fa-clock"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Alert Cards -->
    <?php if ($stats['low_stock'] > 0 || $stats['out_of_stock'] > 0): ?>
    <div class="cards-grid cards-grid-2">
        <?php if ($stats['low_stock'] > 0): ?>
        <div class="modern-card card-warning">
            <div class="modern-card-header">
                <div class="modern-card-title">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo $t('low_stock_alert'); ?>
                </div>
            </div>
            <div class="modern-card-body">
                <div class="alert-content">
                    <h4><?php echo $stats['low_stock']; ?> <?php echo $t('products_have_low_stock'); ?></h4>
                    <p><?php echo $t('these_products_need_restocking'); ?></p>
                </div>
            </div>
            <div class="modern-card-footer">
                <a href="?page=products&status=low_stock" class="btn btn-warning btn-sm">
                    <i class="fas fa-eye"></i> <?php echo $t('view_products'); ?>
                </a>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($stats['out_of_stock'] > 0): ?>
        <div class="modern-card card-danger">
            <div class="modern-card-header">
                <div class="modern-card-title">
                    <i class="fas fa-times-circle"></i>
                    <?php echo $t('out_of_stock_alert'); ?>
                </div>
            </div>
            <div class="modern-card-body">
                <div class="alert-content">
                    <h4><?php echo $stats['out_of_stock']; ?> <?php echo $t('products_are_out_of_stock'); ?></h4>
                    <p><?php echo $t('these_products_unavailable'); ?></p>
                </div>
            </div>
            <div class="modern-card-footer">
                <a href="?page=products&status=out_of_stock" class="btn btn-danger btn-sm">
                    <i class="fas fa-eye"></i> <?php echo $t('view_products'); ?>
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Revenue Analytics (top) -->
    <div class="modern-card revenue-analytics-card">
        <div class="modern-card-header">
            <div class="modern-card-title">
                <i class="fas fa-chart-line"></i>
                <?php echo $t('revenue_analytics'); ?>
            </div>
            <div class="chart-controls">
                <select class="chart-period-select">
                    <option value="6"><?php echo $t('last_6_months'); ?></option>
                    <option value="12"><?php echo $t('last_12_months'); ?></option>
                    <option value="24"><?php echo $t('last_2_years'); ?></option>
                </select>
            </div>
        </div>
        <div class="modern-card-body">
            <div class="chart-container">
                <canvas id="revenueChart" width="400" height="200"></canvas>
            </div>
            <div class="chart-stats">
                <div class="chart-stat-item">
                    <div class="chart-stat-value"><?php echo $currency_position === 'left' ? $currency_symbol . number_format($stats['revenue'], 2) : number_format($stats['revenue'], 2) . ' ' . $currency_symbol; ?></div>
                    <div class="chart-stat-label"><?php echo $t('total_revenue'); ?></div>
                </div>
                <div class="chart-stat-item">
                    <div class="chart-stat-value"><?php echo $stats['orders']; ?></div>
                    <div class="chart-stat-label"><?php echo $t('total_orders'); ?></div>
                </div>
                <div class="chart-stat-item">
                    <div class="chart-stat-value"><?php 
                        $avgValue = $stats['orders'] > 0 ? number_format($stats['revenue'] / $stats['orders'], 2) : '0.00';
                        echo $currency_position === 'left' ? $currency_symbol . $avgValue : $avgValue . ' ' . $currency_symbol; 
                    ?></div>
                    <div class="chart-stat-label"><?php echo $t('avg_order_value'); ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Row -->
    <div class="main-content-grid">
        <!-- Recent Orders -->
        <div class="modern-card dashboard-recent-orders-card">
            <div class="modern-card-header">
                <div class="modern-card-title">
                    <i class="fas fa-shopping-bag"></i>
                    <?php echo $t('recent_orders'); ?>
                </div>
                <a href="?page=orders" class="btn btn-outline-primary btn-sm">
                    <i class="fas fa-eye"></i> <?php echo $t('view_all'); ?>
                </a>
            </div>
            <div class="modern-card-body recent-orders-body">
                <div class="recent-orders-table-wrap">
                    <table class="recent-orders-table">
                        <thead>
                            <tr>
                                <th><?php echo $t('order_number_short'); ?></th>
                                <th><?php echo $t('customer'); ?></th>
                                <th><?php echo $t('items'); ?></th>
                                <th><?php echo $t('total'); ?></th>
                                <th><?php echo $t('status'); ?></th>
                                <th><?php echo $t('date'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($recent_orders)): ?>
                                <?php foreach (array_slice($recent_orders, 0, 5) as $order): ?>
                                    <tr>
                                        <td>
                                            <a href="?page=order_view_details&amp;id=<?php echo (int)($order['id'] ?? 0); ?>" class="recent-order-id"><?php echo htmlspecialchars($order['order_number']); ?></a>
                                        </td>
                                        <td>
                                            <div class="recent-order-customer">
                                                <span class="recent-order-customer-name"><?php echo htmlspecialchars($order['customer_name']); ?></span>
                                                <span class="recent-order-customer-email"><?php echo htmlspecialchars($order['customer_email']); ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="recent-order-items"><?php echo (int)($order['item_count'] ?? 0); ?></span>
                                        </td>
                                        <td>
                                            <span class="recent-order-total"><?php echo $currency_position === 'left' ? $currency_symbol . number_format($order['total_amount'], 2) : number_format($order['total_amount'], 2) . ' ' . $currency_symbol; ?></span>
                                        </td>
                                        <td>
                                            <span class="recent-order-status status-<?php echo preg_replace('/[^a-z0-9]/', '', strtolower($order['status'])); ?>"><?php echo ucfirst($order['status']); ?></span>
                                        </td>
                                        <td class="recent-order-date"><?php echo date('M j, Y', strtotime($order['created_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="recent-orders-empty">
                                        <i class="fas fa-shopping-bag"></i>
                                        <p><?php echo $t('no_orders_yet'); ?></p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Top Products -->
        <div class="modern-card">
            <div class="modern-card-header">
                <div class="modern-card-title">
                    <i class="fas fa-chart-line"></i>
                    <?php echo $t('top_selling_products'); ?>
                </div>
            </div>
            <div class="modern-card-body">
                <?php if (!empty($top_products)): ?>
                    <div class="top-products-list">
                        <?php foreach ($top_products as $index => $product): ?>
                            <div class="top-product-item">
                                <div class="product-rank">#<?php echo $index + 1; ?></div>
                                    <div class="product-info">
                                    <div class="product-name"><?php echo htmlspecialchars($product['name']); ?></div>
                                    <div class="product-meta">
                                            <span class="product-sku"><?php echo htmlspecialchars($product['sku']); ?></span>
                                        <span class="product-stock <?php echo $product['stock_quantity'] <= 10 ? 'low' : ''; ?>">
                                                <?php echo $product['stock_quantity']; ?> <?php echo $t('in_stock'); ?>
                                            </span>
                                        </div>
                                    </div>
                                <div class="product-sales">
                                    <div class="sales-count"><?php echo $product['total_sold']; ?> <?php echo $t('sold'); ?></div>
                                    <div class="sales-revenue"><?php echo $currency_position === 'left' ? $currency_symbol . number_format($product['total_revenue'], 2) : number_format($product['total_revenue'], 2) . ' ' . $currency_symbol; ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-chart-bar fa-3x"></i>
                        <p><?php echo $t('no_sales_data'); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Chart data from PHP
    const monthlyData = <?php echo json_encode($monthly_data); ?>;
    
    // Prepare chart data
    const labels = monthlyData.map(item => {
        const date = new Date(item.month + '-01');
        return date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
    });
    
    const revenueData = monthlyData.map(item => parseFloat(item.revenue) || 0);
    const ordersData = monthlyData.map(item => parseInt(item.orders) || 0);
    const monthlyProfitData = <?php echo json_encode($monthly_profit_data); ?>;
    const profitData = monthlyData.map(function(item) {
        const m = monthlyProfitData.find(function(p) { return p.month === item.month; });
        return m ? parseFloat(m.profit) || 0 : 0;
    });
    
    // Chart configuration
    const chartConfig = {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Revenue (<?php echo $currency_symbol; ?>)',
                data: revenueData,
                borderColor: '<?php echo $settings->getSetting('primary_color', '#007bff'); ?>',
                backgroundColor: '<?php echo $settings->getSetting('primary_color', '#007bff'); ?>20',
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '<?php echo $settings->getSetting('primary_color', '#007bff'); ?>',
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
                pointRadius: 6,
                pointHoverRadius: 8,
                pointHoverBackgroundColor: '<?php echo $settings->getSetting('primary_color', '#007bff'); ?>',
                pointHoverBorderColor: '#ffffff',
                pointHoverBorderWidth: 3
            }<?php if (!empty($monthly_profit_data)): ?>,
            {
                label: '<?php echo $t('net_profit', 'Net Profit'); ?> (<?php echo $currency_symbol; ?>)',
                data: profitData,
                borderColor: '#10b981',
                backgroundColor: '#10b98120',
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#10b981',
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
                pointRadius: 6,
                pointHoverRadius: 8
            }
            <?php endif; ?>]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            resizeDelay: 0,
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                    labels: {
                        usePointStyle: true,
                        padding: 20,
                        font: {
                            size: 14,
                            weight: '500'
                        },
                        color: function(context) {
                            return getComputedStyle(document.documentElement).getPropertyValue('--text-primary').trim() || '#000000';
                        }
                    }
                },
                tooltip: {
                    backgroundColor: function(context) {
                        return getComputedStyle(document.documentElement).getPropertyValue('--bg-card').trim() || '#ffffff';
                    },
                    titleColor: function(context) {
                        return getComputedStyle(document.documentElement).getPropertyValue('--text-primary').trim() || '#000000';
                    },
                    bodyColor: function(context) {
                        return getComputedStyle(document.documentElement).getPropertyValue('--text-primary').trim() || '#000000';
                    },
                    borderColor: function(context) {
                        return getComputedStyle(document.documentElement).getPropertyValue('--border-primary').trim() || '#e5e7eb';
                    },
                    borderWidth: 1,
                    cornerRadius: 8,
                    displayColors: true,
                    callbacks: {
                        label: function(context) {
                            return 'Revenue: <?php echo $currency_position === 'left' ? $currency_symbol : ''; ?>' + context.parsed.y.toLocaleString() + '<?php echo $currency_position === 'right' ? ' ' . $currency_symbol : ''; ?>';
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: {
                        color: function(context) {
                            return getComputedStyle(document.documentElement).getPropertyValue('--border-primary').trim() || '#e5e7eb';
                        },
                        drawBorder: false
                    },
                    ticks: {
                        color: function(context) {
                            return getComputedStyle(document.documentElement).getPropertyValue('--text-secondary').trim() || '#6b7280';
                        },
                        font: {
                            size: 12
                        }
                    }
                },
                y: {
                    beginAtZero: true,
                    grid: {
                        color: function(context) {
                            return getComputedStyle(document.documentElement).getPropertyValue('--border-primary').trim() || '#e5e7eb';
                        },
                        drawBorder: false
                    },
                    ticks: {
                        color: function(context) {
                            return getComputedStyle(document.documentElement).getPropertyValue('--text-secondary').trim() || '#6b7280';
                        },
                        font: {
                            size: 12
                        },
                        callback: function(value) {
                            return '$' + value.toLocaleString();
                        }
                    }
                }
            },
            interaction: {
                intersect: false,
                mode: 'index'
            },
            animation: {
                duration: 2000,
                easing: 'easeInOutQuart'
            }
        }
    };
    
    // Create chart
    const ctx = document.getElementById('revenueChart').getContext('2d');
    const revenueChart = new Chart(ctx, chartConfig);
    
    // Function to update chart colors based on theme
    function updateChartColors() {
        const isDarkMode = document.documentElement.getAttribute('data-theme') === 'dark';
        
        // Update legend colors
        revenueChart.options.plugins.legend.labels.color = isDarkMode ? '#ffffff' : '#000000';
        
        // Update tooltip colors
        revenueChart.options.plugins.tooltip.backgroundColor = isDarkMode ? '#1f2937' : '#ffffff';
        revenueChart.options.plugins.tooltip.titleColor = isDarkMode ? '#ffffff' : '#000000';
        revenueChart.options.plugins.tooltip.bodyColor = isDarkMode ? '#ffffff' : '#000000';
        revenueChart.options.plugins.tooltip.borderColor = isDarkMode ? '#374151' : '#e5e7eb';
        
        // Update grid colors
        revenueChart.options.scales.x.grid.color = isDarkMode ? '#374151' : '#e5e7eb';
        revenueChart.options.scales.y.grid.color = isDarkMode ? '#374151' : '#e5e7eb';
        
        // Update tick colors
        revenueChart.options.scales.x.ticks.color = isDarkMode ? '#9ca3af' : '#6b7280';
        revenueChart.options.scales.y.ticks.color = isDarkMode ? '#9ca3af' : '#6b7280';
        
        revenueChart.update();
    }
    
    // Listen for theme changes
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.type === 'attributes' && mutation.attributeName === 'data-theme') {
                updateChartColors();
            }
        });
    });
    
    observer.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['data-theme']
    });
    
    // Handle window resize for mobile responsiveness
    let resizeTimeout;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(function() {
            if (revenueChart) {
                revenueChart.resize();
            }
        }, 100);
    });
    
    // Period selector functionality
    const periodSelect = document.querySelector('.chart-period-select');
    if (periodSelect) {
        periodSelect.addEventListener('change', function() {
            const months = parseInt(this.value);
            // This would typically make an AJAX call to get new data
            // For now, we'll just show a message
            console.log('Period changed to:', months, 'months');
        });
    }
    
    // Add smooth hover effects
    const chartStats = document.querySelectorAll('.chart-stat-item');
    chartStats.forEach(stat => {
        stat.addEventListener('mouseenter', function() {
            this.style.opacity = '0.9';
        });
        
        stat.addEventListener('mouseleave', function() {
            this.style.opacity = '1';
        });
    });
});
</script>

<style>
/* Modern Dashboard Styles */
:root {
    --primary: #000000;
    --success: #10b981;
    --danger: #ef4444;
    --warning: #f59e0b;
    --info: #3b82f6;
    --purple: #8b5cf6;
}

.dashboard-container {
    padding: 0;
    width: 100%;
    max-width: 100%;
}

.btn-primary {
    padding: 0 2rem;
    height: 48px;
    border-radius: 6px;
    border: none;
    background: white;
    color: var(--color-primary-db);
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-primary:hover {
    box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
}

/* Alert Cards */
.alert-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.alert-card {
    background: var(--bg-card);
    border-radius: 6px;
    padding: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    border-left: 4px solid;
}

.alert-card.warning {
    border-left-color: var(--color-warning);
}

.alert-card.danger {
    border-left-color: var(--color-error);
}

.alert-icon {
    width: 48px;
    height: 48px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    flex-shrink: 0;
}

.alert-card.warning .alert-icon {
    background: var(--color-warning-light);
    color: var(--color-warning);
}

.alert-card.danger .alert-icon {
    background: var(--color-error-light);
    color: var(--color-error);
}

.alert-content {
    flex: 1;
}

.alert-content h4 {
    margin: 0 0 0.25rem 0;
    font-size: 1rem;
    font-weight: 600;
    color: var(--text-primary);
}

.alert-content p {
    margin: 0;
    font-size: 0.875rem;
    color: var(--text-secondary);
}

.alert-action {
    padding: 0.5rem 1rem;
    border-radius: 6px;
    background: var(--bg-secondary);
    color: var(--text-secondary);
    font-size: 0.875rem;
    font-weight: 600;
    text-decoration: none;
    white-space: nowrap;
    transition: all 0.3s ease;
}

.alert-action:hover {
    background: var(--border-primary);
    color: var(--text-primary);
}

/* Main Content Grid */
.main-content-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 1.5rem;
    margin-bottom: 2rem;
}

/* Modern Card */
.modern-card {
    background: var(--bg-card);
    border-radius: 6px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    overflow: hidden;
    border: 1px solid var(--border-primary);
}

.modern-card-header {
    padding: 1.5rem;
    border-bottom: 1px solid var(--border-primary);
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: var(--bg-secondary);
}

.modern-card-title {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    color: var(--text-primary);
}

.modern-card-body {
    padding: 1.5rem;
}

.modern-card-footer {
    padding: 1.5rem;
    border-top: 1px solid var(--border-primary);
    background: var(--bg-secondary);
}

/* Modern Table */
.table-container {
    overflow-x: auto;
    border-radius: 6px;
    border: 1px solid var(--border-primary);
}

.modern-table {
    width: 100%;
    border-collapse: collapse;
    background: var(--bg-card);
}

.modern-table thead th {
    text-align: left;
    padding: 1rem 0.75rem;
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--text-primary);
    background: var(--bg-secondary);
    border-bottom: 1px solid var(--border-primary);
}

.modern-table tbody td {
    padding: 1rem 0.75rem;
    border-bottom: 1px solid var(--border-primary);
}

.modern-table tbody tr:hover {
    background: var(--bg-secondary);
}

.modern-table tbody tr:last-child td {
    border-bottom: none;
}

/* Revenue Analytics (top section) */
.revenue-analytics-card {
    margin-bottom: 1.5rem;
}

/* Recent Orders – redesigned table */
.dashboard-recent-orders-card .modern-card-body {
    padding: 0;
}
.recent-orders-body {
    padding: 0 !important;
}
.recent-orders-table-wrap {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}
.recent-orders-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.9375rem;
}
.recent-orders-table thead {
    background: var(--bg-secondary);
}
.recent-orders-table thead th {
    text-align: left;
    padding: 0.875rem 1.25rem;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--text-muted);
    border-bottom: 1px solid var(--border-primary);
    white-space: nowrap;
}
.recent-orders-table tbody td {
    padding: 1rem 1.25rem;
    border-bottom: 1px solid var(--border-primary);
    color: var(--text-secondary);
    vertical-align: middle;
}
.recent-orders-table tbody tr {
    transition: background 0.15s ease;
}
.recent-orders-table tbody tr:hover {
    background: var(--bg-secondary);
}
.recent-orders-table tbody tr:last-child td {
    border-bottom: none;
}
.recent-order-id {
    font-weight: 600;
    color: var(--color-primary-db, var(--text-primary));
    text-decoration: none;
}
.recent-order-id:hover {
    text-decoration: underline;
}
.recent-order-customer {
    display: flex;
    flex-direction: column;
    gap: 0.125rem;
}
.recent-order-customer-name {
    font-weight: 500;
    color: var(--text-primary);
}
.recent-order-customer-email {
    font-size: 0.8125rem;
    color: var(--text-muted);
}
.recent-order-items {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 2rem;
    padding: 0.25rem 0.5rem;
    border-radius: 6px;
    background: var(--bg-secondary);
    font-weight: 600;
    color: var(--text-primary);
    font-size: 0.875rem;
}
.recent-order-total {
    font-weight: 600;
    color: var(--text-primary);
}
.recent-order-date {
    color: var(--text-muted);
    font-size: 0.875rem;
}
.recent-order-status {
    display: inline-block;
    padding: 0.35rem 0.75rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: capitalize;
}
.recent-order-status.status-pending {
    background: var(--color-warning-light, rgba(245, 158, 11, 0.15));
    color: var(--color-warning-dark, #b45309);
}
.recent-order-status.status-processing {
    background: var(--color-info-light, rgba(59, 130, 246, 0.15));
    color: var(--color-info-dark, #1d4ed8);
}
.recent-order-status.status-shipped {
    background: var(--color-primary-light, rgba(99, 102, 241, 0.15));
    color: var(--color-primary-dark, #4338ca);
}
.recent-order-status.status-delivered {
    background: var(--color-success-light, rgba(16, 185, 129, 0.15));
    color: var(--color-success-dark, #047857);
}
.recent-order-status.status-cancelled {
    background: var(--color-error-light, rgba(239, 68, 68, 0.15));
    color: var(--color-error-dark, #b91c1c);
}
.recent-orders-empty {
    text-align: center;
    padding: 3rem 1.5rem !important;
    color: var(--text-muted);
}
.recent-orders-empty i {
    font-size: 2.5rem;
    margin-bottom: 0.75rem;
    opacity: 0.5;
}
.recent-orders-empty p {
    margin: 0;
    font-size: 0.9375rem;
}

.order-number {
    color: var(--text-primary);
    font-weight: 600;
}

.customer-info {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.customer-name {
    font-weight: 500;
    color: var(--text-primary);
}

.customer-email {
    font-size: 0.875rem;
    color: var(--text-secondary);
}

.item-badge {
    display: inline-flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 0.5rem 0.75rem;
    border-radius: 6px;
    background: var(--bg-secondary);
    border: 1px solid var(--border-primary);
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    min-width: 60px;
}

.item-badge .item-count {
    font-size: 1rem;
    font-weight: 700;
    color: var(--text-primary);
    line-height: 1.2;
}

.item-badge .item-label {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--text-primary);
    line-height: 1.2;
    text-transform: lowercase;
}

.price {
    color: var(--text-primary);
    font-weight: 600;
}

.status-badge {
    padding: 0.375rem 0.75rem;
    border-radius: 6px;
    font-size: 0.8125rem;
    font-weight: 600;
}

.status-badge.status-pending {
    background: var(--color-warning-light);
    color: var(--color-warning);
}

.status-badge.status-processing {
    background: var(--color-info-light);
    color: var(--color-info);
}

.status-badge.status-shipped {
    background: var(--color-primary-db-light);
    color: var(--color-primary-db);
}

.status-badge.status-delivered {
    background: var(--color-success-light);
    color: var(--color-success);
}

.status-badge.status-cancelled {
    background: var(--color-error-light);
    color: var(--color-error);
}

.date {
    color: var(--text-secondary);
    font-size: 0.875rem;
}

/* Top Products List */
.top-products-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.top-product-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: var(--bg-secondary);
    border-radius: 6px;
    transition: all 0.3s ease;
    border: 1px solid var(--border-primary);
}

.top-product-item:hover {
    background: var(--bg-tertiary);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.product-rank {
    width: 40px;
    height: 40px;
    border-radius: 6px;
    background: linear-gradient(135deg, var(--color-primary-db), var(--color-primary-db-hover));
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    flex-shrink: 0;
    font-size: 0.875rem;
}

.product-info {
    flex: 1;
    min-width: 0;
}

.product-name {
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 0.25rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.product-meta {
    display: flex;
    gap: 1rem;
    font-size: 0.8125rem;
}

.product-sku {
    color: var(--text-secondary);
}

.product-stock {
    color: var(--color-success);
    font-weight: 600;
}

.product-stock.low {
    color: var(--color-warning);
}

.product-sales {
    text-align: right;
}

.sales-count {
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 0.25rem;
}

.sales-revenue {
    font-size: 0.875rem;
    color: var(--text-secondary);
}

/* Chart Styles */
.chart-container {
    position: relative;
    height: 300px;
    margin-bottom: 1.5rem;
    background: var(--bg-card);
    border-radius: 6px;
    padding: 1rem;
    border: 1px solid var(--border-primary);
    width: 100%;
    max-width: 100%;
    overflow: hidden;
}

.chart-container canvas {
    max-width: 100% !important;
    height: auto !important;
}

/* Dark mode chart adjustments */
[data-theme="dark"] .chart-container {
    background: var(--bg-card);
}

[data-theme="dark"] .chart-container canvas {
    filter: brightness(1.1);
}

/* Ensure chart doesn't break mobile layout */
@media (max-width: 767px) {
    .modern-card {
        overflow-x: hidden;
    }
    
    .chart-container {
        min-width: 0;
        flex-shrink: 1;
    }
    
    .chart-container canvas {
        width: 100% !important;
        max-width: 100% !important;
    }
}

.chart-controls {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.chart-period-select {
    padding: 0.5rem 1rem;
    border: 1px solid var(--border-primary);
    border-radius: 6px;
    background: var(--bg-card);
    color: var(--text-primary);
    font-size: 0.875rem;
    cursor: pointer;
    transition: all 0.3s ease;
}

.chart-period-select:focus {
    outline: none;
    border-color: var(--color-primary-db);
    box-shadow: 0 0 0 3px var(--color-primary-db-light);
}

.chart-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 1rem;
    margin-top: 1rem;
}

.chart-stat-item {
    text-align: center;
    padding: 1rem;
    background: var(--bg-secondary);
    border-radius: 6px;
    border: 1px solid var(--border-primary);
    transition: all 0.3s ease;
}

.chart-stat-item:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.chart-stat-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--color-primary-db);
    margin-bottom: 0.25rem;
}

.chart-stat-label {
    font-size: 0.875rem;
    color: var(--text-secondary);
    font-weight: 500;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 3rem 1rem;
    color: var(--text-tertiary);
}

.empty-state i {
    margin-bottom: 1rem;
}

.empty-state p {
    margin: 0;
    color: var(--text-secondary);
}

/* Responsive Utilities */
.hide-mobile {
    display: block;
}

.show-mobile {
    display: none;
}

@media (max-width: 767px) {
    .hide-mobile {
        display: none;
    }
    
    .show-mobile {
        display: block;
    }
}

/* Touch-friendly improvements */
@media (hover: none) and (pointer: coarse) {
    .btn-primary,
    .stat-card,
    .modern-card,
    .chart-stat-item,
    .top-product-item {
        min-height: 44px;
        min-width: 44px;
    }
    
    .modern-table tbody tr {
        padding: 0.5rem 0;
    }
    
    .nav-link {
        padding: 1rem 0.75rem;
    }
}

/* High DPI displays */
@media (-webkit-min-device-pixel-ratio: 2), (min-resolution: 192dpi) {
    .stat-card-icon,
    .product-rank {
        image-rendering: -webkit-optimize-contrast;
        image-rendering: crisp-edges;
    }
}

/* Responsive Design */

/* Large Desktop (1400px+) */
@media (min-width: 1400px) {
    .dashboard-container {
        max-width: 1400px;
        margin: 0 auto;
    }
    
    .stats-grid {
        grid-template-columns: repeat(4, 1fr);
    }
    
    .main-content-grid {
        grid-template-columns: 2fr 1fr;
    }
}

/* Desktop (1200px - 1399px) */
@media (max-width: 1399px) and (min-width: 1200px) {
    .stats-grid {
        grid-template-columns: repeat(4, 1fr);
    }
}

/* Large Tablet (992px - 1199px) */
@media (max-width: 1199px) and (min-width: 992px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .main-content-grid {
        grid-template-columns: 1fr;
    }
    
}

/* Tablet (768px - 991px) */
@media (max-width: 991px) and (min-width: 768px) {
    .dashboard-container {
        padding: 0 1rem;
    }
    
    .btn-primary {
        flex: 1;
        min-width: 200px;
        justify-content: center;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 1rem;
    }
    
    .stat-card {
        padding: 1rem;
    }
    
    .stat-card-value {
        font-size: 1.75rem;
    }
    
    .main-content-grid {
        grid-template-columns: 1fr;
    }
    
    .modern-card-header {
        padding: 1rem;
        flex-direction: column;
        align-items: stretch;
        gap: 1rem;
    }
    
    .modern-card-title {
        font-size: 1.125rem;
    }
    
    .modern-card-body {
        padding: 1rem;
    }
    
    .chart-container {
        height: 280px;
        padding: 0.75rem;
    }
    
    .chart-stats {
        grid-template-columns: repeat(3, 1fr);
        gap: 0.75rem;
    }
}

/* Mobile Large (576px - 767px) */
@media (max-width: 767px) and (min-width: 576px) {
    .dashboard-container {
        padding: 0 0.75rem;
    }
    
    .btn-primary {
        width: 100%;
        height: 44px;
        justify-content: center;
        font-size: 0.875rem;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 0.5rem;
    }
    
    .stat-card {
        padding: 1rem;
        border-radius: 6px;
    }
    
    .stat-card-header {
        justify-content: flex-end;
    }
    
    .stat-card-icon {
        width: 40px;
        height: 40px;
        font-size: 1rem;
        top: 1rem;
        right: 1rem;
    }
    
    .stat-card-content {
        align-items: flex-start;
        text-align: left;
        padding-right: 4rem;
    }
    
    .stat-card-value {
        font-size: 1.25rem;
        margin-bottom: 0.25rem;
    }
    
    .stat-card-label {
        font-size: 0.75rem;
        margin-bottom: 0.25rem;
    }
    
    .stat-card-change {
        font-size: 0.6875rem;
        justify-content: center;
    }
    
    .stat-card-change i {
        font-size: 0.625rem;
    }
    
    .main-content-grid {
        grid-template-columns: 1fr;
    }
    
    .modern-card {
        border-radius: 6px;
    }
    
    .modern-card-header {
        padding: 1rem;
        flex-direction: column;
        align-items: stretch;
        gap: 0.75rem;
    }
    
    .modern-card-title {
        font-size: 1rem;
        justify-content: center;
    }
    
    .modern-card-body {
        padding: 1rem;
    }
    
    .table-container {
        border-radius: 6px;
    }
    
    .modern-table thead th {
        padding: 0.75rem 0.5rem;
        font-size: 0.8125rem;
    }
    
    .modern-table tbody td {
        padding: 0.75rem 0.5rem;
        font-size: 0.8125rem;
    }
    
    .top-product-item {
        flex-direction: column;
        align-items: stretch;
        text-align: center;
        padding: 0.75rem;
    }
    
    .product-rank {
        width: 36px;
        height: 36px;
        font-size: 0.8125rem;
        align-self: center;
        margin-bottom: 0.5rem;
    }
    
    .product-sales {
        text-align: center;
        margin-top: 0.5rem;
    }
    
    .chart-container {
        height: 250px;
        padding: 0.5rem;
        width: 100%;
        max-width: 100%;
        overflow: hidden;
    }
    
    .chart-container canvas {
        max-width: 100% !important;
        height: auto !important;
    }
    
    .chart-stats {
        grid-template-columns: 1fr;
        gap: 0.5rem;
    }
    
    .chart-stat-item {
        padding: 0.75rem;
    }
    
    .chart-stat-value {
        font-size: 1.25rem;
    }
    
    .chart-stat-label {
        font-size: 0.8125rem;
    }
}

/* Mobile Small (up to 575px) */
@media (max-width: 575px) {
    .dashboard-container {
        padding: 0 0.5rem;
    }
    
    .btn-primary {
        width: 100%;
        height: 40px;
        justify-content: center;
        font-size: 0.8125rem;
        padding: 0 1rem;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 0.375rem;
    }
    
    .stat-card {
        padding: 0.75rem;
        border-radius: 6px;
        min-height: 80px;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }
    
    .stat-card-header {
        justify-content: flex-end;
    }
    
    .stat-card-icon {
        width: 36px;
        height: 36px;
        font-size: 0.875rem;
        top: 0.85rem;
        right: 0.85rem;
    }
    
    .stat-card-content {
        align-items: flex-start;
        text-align: left;
        gap: 0.25rem;
        padding-right: 3.5rem;
    }
    
    .stat-card-value {
        font-size: 1rem;
        line-height: 1.1;
        margin-bottom: 0.125rem;
    }
    
    .stat-card-label {
        font-size: 0.6875rem;
        margin-bottom: 0.125rem;
        line-height: 1.2;
    }
    
    .stat-card-change {
        font-size: 0.625rem;
        justify-content: flex-start;
        line-height: 1.2;
    }
    
    .stat-card-change i {
        font-size: 0.5rem;
    }
    
    .main-content-grid {
        grid-template-columns: 1fr;
    }
    
    .modern-card {
        border-radius: 6px;
    }
    
    .modern-card-header {
        padding: 0.75rem;
        flex-direction: column;
        align-items: stretch;
        gap: 0.5rem;
    }
    
    .modern-card-title {
        font-size: 0.875rem;
        justify-content: center;
    }
    
    .modern-card-body {
        padding: 0.75rem;
    }
    
    .table-container {
        border-radius: 6px;
        overflow-x: auto;
    }
    
    .modern-table {
        min-width: 500px;
    }
    
    .modern-table thead th {
        padding: 0.5rem 0.25rem;
        font-size: 0.75rem;
    }
    
    .modern-table tbody td {
        padding: 0.5rem 0.25rem;
        font-size: 0.75rem;
    }
    
    .customer-info {
        gap: 0.125rem;
    }
    
    .customer-name {
        font-size: 0.75rem;
    }
    
    .customer-email {
        font-size: 0.6875rem;
    }
    
    .item-badge {
        padding: 0.125rem 0.5rem;
        font-size: 0.6875rem;
    }
    
    .status-badge {
        padding: 0.25rem 0.5rem;
        font-size: 0.6875rem;
    }
    
    .top-product-item {
        flex-direction: column;
        align-items: stretch;
        text-align: center;
        padding: 0.5rem;
    }
    
    .product-rank {
        width: 32px;
        height: 32px;
        font-size: 0.75rem;
        align-self: center;
        margin-bottom: 0.25rem;
    }
    
    .product-name {
        font-size: 0.875rem;
        margin-bottom: 0.125rem;
    }
    
    .product-meta {
        flex-direction: column;
        gap: 0.25rem;
        font-size: 0.75rem;
    }
    
    .product-sales {
        text-align: center;
        margin-top: 0.25rem;
    }
    
    .sales-count {
        font-size: 0.75rem;
    }
    
    .sales-revenue {
        font-size: 0.6875rem;
    }
    
    .chart-container {
        height: 200px;
        padding: 0.25rem;
        width: 100%;
        max-width: 100%;
        overflow: hidden;
    }
    
    .chart-container canvas {
        max-width: 100% !important;
        height: auto !important;
    }
    
    .chart-controls {
        flex-direction: column;
        align-items: stretch;
        gap: 0.5rem;
    }
    
    .chart-period-select {
        width: 100%;
        font-size: 0.8125rem;
        padding: 0.375rem 0.75rem;
    }
    
    .chart-stats {
        grid-template-columns: 1fr;
        gap: 0.375rem;
    }
    
    .chart-stat-item {
        padding: 0.5rem;
    }
    
    .chart-stat-value {
        font-size: 1.125rem;
    }
    
    .chart-stat-label {
        font-size: 0.75rem;
    }
}

/* Extra Small Mobile (up to 375px) */
@media (max-width: 375px) {
    .dashboard-container {
        padding: 0 0.25rem;
    }
    .stats-grid {
        grid-template-columns: 1fr;
        gap: 0.25rem;
    }
    
    .stat-card {
        padding: 0.5rem 0.75rem;
        min-height: 60px;
        flex-direction: row;
        align-items: center;
        text-align: left;
    }
    
    .stat-card-header {
        margin-bottom: 0;
        margin-right: 0.75rem;
        justify-content: flex-end;
    }
    
    .stat-card-content {
        align-items: flex-start;
        text-align: left;
        flex: 1;
        padding-right: 3rem;
    }
    
    .stat-card-value {
        font-size: 1rem;
        margin-bottom: 0.125rem;
    }
    
    .stat-card-label {
        font-size: 0.6875rem;
        margin-bottom: 0.125rem;
    }
    
    .stat-card-change {
        font-size: 0.625rem;
        justify-content: flex-start;
    }
    
    
    .btn-primary {
        font-size: 0.75rem;
        padding: 0 0.75rem;
    }
    
    .stat-card-icon {
        width: 28px;
        height: 28px;
        font-size: 0.75rem;
        top: 0.5rem;
        right: 0.5rem;
    }
    
    .stat-card-value {
        font-size: 0.875rem;
        line-height: 1.1;
    }
    
    .stat-card-label {
        font-size: 0.625rem;
        line-height: 1.2;
    }
    
    .stat-card-change {
        font-size: 0.5625rem;
    }
    
    .stat-card-change i {
        font-size: 0.4375rem;
    }
    
    .modern-card-header,
    .modern-card-body {
        padding: 0.5rem;
    }
    
    .chart-container {
        height: 180px;
        padding: 0.125rem;
        width: 100%;
        max-width: 100%;
        overflow: hidden;
    }
    
    .chart-container canvas {
        max-width: 100% !important;
        height: auto !important;
    }
    
    .chart-stat-item {
        padding: 0.375rem;
    }
    
    .chart-stat-value {
        font-size: 1rem;
    }
}
</style>
