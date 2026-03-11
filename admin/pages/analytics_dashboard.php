<?php
require_once __DIR__ . '/../../config/StoreContext.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/analytics_helper.php';
require_once __DIR__ . '/../../config/settings.php';
require_once __DIR__ . '/../../config/language.php';
require_once __DIR__ . '/../../config/plan_helper.php';

$store_id = StoreContext::getId();
if (!isset($conn)) {
    $database = new Database();
    $conn = $database->getConnection();
}
$analytics = new AnalyticsHelper($conn, $store_id);
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

$daysRange = 30;
$periodStart = date('M j, Y', strtotime("-{$daysRange} days"));
$periodEnd = date('M j, Y');

$scriptPath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
$analyticsApiUrl = $scriptPath . '/../analytics_api.php';
?>
<script>
window.ANALYTICS_API_URL = <?php echo json_encode($analyticsApiUrl); ?>;
window.STORE_ID = <?php echo (int) $store_id; ?>;
window.CURRENCY_SYMBOL = <?php echo json_encode($currency_symbol ?? '$'); ?>;
window.CURRENCY_POSITION = <?php echo json_encode($currency_position ?? 'left'); ?>;
window.translations = {
    orders: <?php echo json_encode($t('orders', 'Orders')); ?>,
    revenue: <?php echo json_encode($t('revenue', 'Revenue')); ?>,
    order_number: <?php echo json_encode($t('order_number')); ?>,
    customer: <?php echo json_encode($t('customer', 'Customer')); ?>,
    date: <?php echo json_encode($t('date')); ?>,
    total: <?php echo json_encode($t('total')); ?>,
    status: <?php echo json_encode($t('status')); ?>,
    view: <?php echo json_encode($t('view')); ?>,
    mark_paid: <?php echo json_encode($t('mark_as_paid', 'Mark Paid')); ?>,
    no_orders_found: <?php echo json_encode($t('no_orders_found_period', 'No orders found for this period.')); ?>,
    failed_to_load_orders: <?php echo json_encode($t('failed_to_load_orders', 'Failed to load recent orders')); ?>,
    convert_cart_confirm: <?php echo json_encode($t('convert_cart_confirm', 'Convert this cart into a pending order?')); ?>,
    cart_converted: <?php echo json_encode($t('cart_converted', 'Cart converted to order.')); ?>,
    customer_info_updated: <?php echo json_encode($t('customer_info_updated', 'Customer information updated.')); ?>,
    of_sessions: <?php echo json_encode($t('of_sessions', 'of sessions')); ?>,
    paid: <?php echo json_encode($t('paid', 'Paid')); ?>,
    pending: <?php echo json_encode($t('pending')); ?>,
    guest: <?php echo json_encode($t('guest', 'Guest')); ?>,
    refunded: <?php echo json_encode($t('refunded', 'Refunded')); ?>,
    failed: <?php echo json_encode($t('failed', 'Failed')); ?>,
    actions: <?php echo json_encode($t('actions')); ?>,
    mark_order_paid_confirm: <?php echo json_encode($t('mark_order_paid_confirm', 'Mark this order as paid?')); ?>,
    order_marked_paid: <?php echo json_encode($t('order_marked_paid', 'Order marked as paid.')); ?>,
    customer_name: <?php echo json_encode($t('customer_name', 'Customer Name')); ?>,
    required: <?php echo json_encode($t('required', 'required')); ?>,
    email_required: <?php echo json_encode($t('email_required', 'Email is required to save contact details.')); ?>
};
console.log('Currency settings:', { symbol: window.CURRENCY_SYMBOL, position: window.CURRENCY_POSITION });
</script>
<?php
// Overview stats (merge defaults first so we keep all keys including abandoned_carts set later)
$overviewDefaults = [
    'total_revenue' => 0,
    'total_orders' => 0,
    'conversion_rate' => 0,
    'abandoned_carts' => 0,
];
try {
    $overviewStats = $analytics->getOverviewStats($daysRange);
} catch (Exception $e) {
    $overviewStats = [];
}
if (!is_array($overviewStats)) {
    $overviewStats = [];
}
$overviewStats = array_merge($overviewDefaults, $overviewStats);
// Fallback: when orders show 0 but product_analytics has data (e.g. no payment_status or different source), use product totals so hero matches Top Products
if ((float)($overviewStats['total_revenue'] ?? 0) === 0.0 && (int)($overviewStats['total_orders'] ?? 0) === 0) {
    try {
        $fallback = $conn->prepare("SELECT COALESCE(SUM(pa.revenue), 0) as total_revenue, COALESCE(SUM(pa.purchase_count), 0) as total_orders FROM product_analytics pa INNER JOIN products p ON pa.product_id = p.id WHERE p.store_id = ?");
        $fallback->execute([$store_id]);
        $row = $fallback->fetch(PDO::FETCH_ASSOC);
        if ($row && ((float)$row['total_revenue'] > 0 || (int)$row['total_orders'] > 0)) {
            $overviewStats['total_revenue'] = (float)$row['total_revenue'];
            $overviewStats['total_orders'] = (int)$row['total_orders'];
        }
    } catch (Exception $e) {}
}

// Conversion funnel data
$funnelDefaults = [
    'total_sessions' => 0,
    'product_views' => 0,
    'add_to_cart' => 0,
    'checkout_start' => 0,
    'payment_start' => 0,
    'purchase_complete' => 0,
];
try {
    $funnelData = $analytics->getConversionFunnel($daysRange);
} catch (Exception $e) {
    $funnelData = [];
}
if (!is_array($funnelData)) {
    $funnelData = [];
}
$funnelData = array_merge($funnelDefaults, array_intersect_key($funnelData, $funnelDefaults));

// Sales trends (last 15 days, scoped to store and plan order visibility)
$salesTrendsData = [];
$sales_vis = PlanHelper::orderVisibilityCondition($conn, $store_id, 'orders');
$sales_where_extra = $sales_vis['sql'] !== '' ? trim($sales_vis['sql']) : '';
try {
    $salesQuery = "SELECT DATE(created_at) as order_date, SUM(total_amount) as total_revenue, COUNT(*) as total_orders
                   FROM orders
                   WHERE store_id = ? AND created_at >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
                   AND (payment_status = 'paid' OR payment_status IS NULL) $sales_where_extra
                   GROUP BY DATE(created_at)
                   ORDER BY order_date ASC";
    $salesStmt = $conn->prepare($salesQuery);
    $salesStmt->execute(array_merge([$store_id], $sales_vis['params']));
    $salesRows = $salesStmt->fetchAll(PDO::FETCH_ASSOC);
    $salesMap = [];
    foreach ($salesRows as $row) {
        $salesMap[$row['order_date']] = $row;
    }
    for ($i = 14; $i >= 0; $i--) {
        $day = date('Y-m-d', strtotime("-$i days"));
        $salesTrendsData[] = [
            'date' => $day,
            'revenue' => isset($salesMap[$day]['total_revenue']) ? (float)$salesMap[$day]['total_revenue'] : 0,
            'orders' => isset($salesMap[$day]['total_orders']) ? (int)$salesMap[$day]['total_orders'] : 0,
        ];
    }
} catch (Exception $e) {
    $salesTrendsData = [];
}

// Top products by revenue (scoped to store)
$topProducts = [];
try {
    $productQuery = "SELECT p.name, COALESCE(pa.purchase_count, 0) as purchase_count, COALESCE(pa.revenue, 0) as revenue
                      FROM products p
                      LEFT JOIN product_analytics pa ON pa.product_id = p.id
                      WHERE p.store_id = ?
                      ORDER BY revenue DESC, purchase_count DESC
                      LIMIT 5";
    $productStmt = $conn->prepare($productQuery);
    $productStmt->execute([$store_id]);
    $productRows = $productStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($productRows as $row) {
        $topProducts[] = [
            'name' => $row['name'] ?? 'Unnamed product',
            'sales' => (int)($row['purchase_count'] ?? 0),
            'revenue' => (float)($row['revenue'] ?? 0),
        ];
    }
} catch (Exception $e) {
    $topProducts = [];
}

// Top search terms
$topSearchTerms = [];
try {
    $searchRows = $analytics->getTopSearchTerms(5, $daysRange);
    foreach ($searchRows as $row) {
        $topSearchTerms[] = [
            'term' => $row['search_term'] ?? 'Unknown',
            'count' => (int)($row['search_count'] ?? 0),
        ];
    }
} catch (Exception $e) {
    $topSearchTerms = [];
}

// Page engagement (store-scoped: from analytics_events when store_id exists)
$pageEngagement = [];
try {
    $pageRows = $analytics->getPageEngagement(8, $daysRange);
    foreach ($pageRows as $row) {
        $pageEngagement[] = [
            'page_url' => (function($url) {
                if (strpos($url, 'http') === 0) {
                    $parsed = parse_url($url);
                    return ($parsed['path'] ?? '') . (isset($parsed['query']) ? '?' . $parsed['query'] : '');
                }
                return $url;
            })($row['page_url'] ?? ''),
            'page_title' => $row['page_title'] ?? 'Unknown',
            'view_count' => (int)($row['view_count'] ?? 0),
            'unique_views' => (int)($row['unique_views'] ?? 0),
            'avg_time_on_page' => (int)max(0, $row['avg_time_on_page'] ?? 0)
        ];
    }
} catch (Exception $e) {
    $pageEngagement = [];
}

// Orders by Gouvernorat (most ordered first) – requires shipping_governorate column
$ordersByGouvernorat = [];
try {
    $conn->query("SELECT shipping_governorate FROM orders LIMIT 1");
    $govQuery = "SELECT shipping_governorate AS gouvernorat, COUNT(*) AS order_count
                 FROM orders
                 WHERE store_id = ? AND shipping_governorate IS NOT NULL AND TRIM(shipping_governorate) != ''
                 GROUP BY shipping_governorate
                 ORDER BY order_count DESC
                 LIMIT 24";
    $govStmt = $conn->prepare($govQuery);
    $govStmt->execute([$store_id]);
    foreach ($govStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $ordersByGouvernorat[] = [
            'gouvernorat' => $row['gouvernorat'] ?? '',
            'order_count' => (int)($row['order_count'] ?? 0),
        ];
    }
} catch (Exception $e) {
    $ordersByGouvernorat = [];
}

// Abandoned carts
$abandonedCarts = [];
try {
    $abandonedRows = $analytics->getAbandonedCarts($daysRange, 25);
} catch (Exception $e) {
    $abandonedRows = [];
}
$overviewStats['abandoned_carts'] = is_array($abandonedRows) ? count($abandonedRows) : 0;
if (is_array($abandonedRows) && count($abandonedRows) > 0) {
    foreach ($abandonedRows as $cart) {
        $itemsCount = 0;
        if (!empty($cart['cart_data'])) {
            $cartItems = json_decode($cart['cart_data'], true);
            if (is_array($cartItems)) {
                foreach ($cartItems as $item) {
                    $itemsCount += (int)($item['quantity'] ?? 0);
                }
            }
        }
        $abandonedCarts[] = [
            'name' => $cart['customer_name'] ?? 'Anonymous',
            'email' => $cart['customer_email'] ?? '',
            'phone' => $cart['customer_phone'] ?? '',
            'cart_value' => (float)($cart['cart_value'] ?? 0),
            'items' => $itemsCount,
            'date' => isset($cart['created_at']) ? date('Y-m-d', strtotime($cart['created_at'])) : '',
            'id' => $cart['id'] ?? null,
        ];
    }
}

// Recent orders (scoped to store and plan order visibility)
$recentOrders = [];
$orders_vis = PlanHelper::orderVisibilityCondition($conn, $store_id, 'orders');
$orders_where_extra = $orders_vis['sql'] !== '' ? trim($orders_vis['sql']) : '';
try {
    $ordersQuery = "SELECT id, order_number, customer_name, customer_email, total_amount, status, payment_status, created_at
                    FROM orders
                    WHERE store_id = ? $orders_where_extra
                    ORDER BY created_at DESC
                    LIMIT 6";
    $ordersStmt = $conn->prepare($ordersQuery);
    $ordersStmt->execute(array_merge([$store_id], $orders_vis['params']));
    $orderRows = $ordersStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($orderRows as $order) {
        $recentOrders[] = [
            'order_id' => $order['order_number'] ?? $order['id'],
            'customer' => $order['customer_name'] ?? ($order['customer_email'] ?? 'Guest'),
            'date' => isset($order['created_at']) ? date('Y-m-d', strtotime($order['created_at'])) : '',
            'total' => (float)($order['total_amount'] ?? 0),
            'status' => $order['payment_status'] ?? ($order['status'] ?? 'pending'),
        ];
    }
} catch (Exception $e) {
    $recentOrders = [];
}
?>
<section class="analytics-section">
  <?php include __DIR__ . '/../analytics/widgets/overview_cards.php'; ?>
</section>

<div class="analytics-dashboard-page" data-currency-symbol="<?php echo htmlspecialchars($currency_symbol); ?>" data-currency-position="<?php echo htmlspecialchars($currency_position); ?>">
  <section class="analytics-section analytics-charts-grid">
    <?php include __DIR__ . '/../analytics/widgets/conversion_funnel.php'; ?>
    <?php include __DIR__ . '/../analytics/widgets/sales_trends.php'; ?>
  </section>

  <section class="analytics-section analytics-table-grid">
    <?php include __DIR__ . '/../analytics/widgets/top_products.php'; ?>
    <?php include __DIR__ . '/../analytics/widgets/top_search_terms.php'; ?>
  </section>

  <section class="analytics-section analytics-region-section">
    <div class="analytics-region-grid">
      <div class="data-card">
        <div class="data-card-header">
          <h3><?php echo $t('delivery_by_region', 'Delivery by region'); ?></h3>
        </div>
        <div class="data-card-body" style="padding: 0.75rem;">
          <?php include __DIR__ . '/delivery_by_region_map.php'; ?>
        </div>
      </div>
      <div class="data-card">
        <div class="data-card-header">
          <h3><?php echo $t('orders_by_region', 'Orders by Gouvernorat'); ?></h3>
        </div>
        <div class="data-card-body">
          <div class="table-responsive">
            <table class="analytics-table">
              <thead>
                <tr>
                  <th><?php echo $t('rank', 'Rank'); ?></th>
                  <th><?php echo $t('gouvernorat', 'Gouvernorat'); ?></th>
                  <th><?php echo $t('orders', 'Orders'); ?></th>
                </tr>
              </thead>
              <tbody>
                <?php if (!empty($ordersByGouvernorat)): ?>
                  <?php foreach ($ordersByGouvernorat as $idx => $row): ?>
                    <tr>
                      <td>
                        <span class="rank-icon rank-top-<?php echo ($idx < 3) ? ($idx + 1) : 'default'; ?>"><?php echo $idx + 1; ?></span>
                      </td>
                      <td><?php echo htmlspecialchars($row['gouvernorat']); ?></td>
                      <td><?php echo (int)$row['order_count']; ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="3" style="text-align: center; color: var(--text-secondary); padding: 1.5rem;"><?php echo $t('no_orders_by_region', 'No orders with region data yet.'); ?></td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="analytics-section">
    <?php include __DIR__ . '/../analytics/widgets/page_engagement.php'; ?>
  </section>

  <section class="analytics-section">
    <?php include __DIR__ . '/../analytics/widgets/recent_orders.php'; ?>
  </section>

  <section class="analytics-section">
    <?php include __DIR__ . '/../analytics/widgets/abandoned_carts.php'; ?>
  </section>
</div>
