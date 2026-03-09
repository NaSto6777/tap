<?php
try {
    require_once __DIR__ . '/../../config/database.php';
    require_once __DIR__ . '/../../config/StoreContext.php';
    require_once __DIR__ . '/../../config/settings.php';
    require_once __DIR__ . '/../../config/plugin_helper.php';
    require_once __DIR__ . '/../../config/email_helper.php';
    require_once __DIR__ . '/../../config/language.php';
    require_once __DIR__ . '/../../config/plan_helper.php';
    require_once __DIR__ . '/../../config/billing_helper.php';
    require_once __DIR__ . '/../../config/shipping_helper.php';
    require_once __DIR__ . '/../../config/FirstDeliveryPlugin.php';
    require_once __DIR__ . '/../../config/ColissimoPlugin.php';

    $store_id = StoreContext::getId();
    $database = new Database();
    $conn = $database->getConnection();
    $settings = new Settings($conn, $store_id);
    $pluginHelper = new PluginHelper($conn);
    $emailService = new EmailService($settings);
    
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
    
} catch (Exception $e) {
    die('<h1>Configuration Error</h1><p>' . htmlspecialchars($e->getMessage()) . '</p><pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>');
}

// Detect optional columns (e.g., payment_status) to avoid fatal errors on older schemas
function ordersColumnExists($conn, $column) {
    try {
        $stmt = $conn->prepare("SHOW COLUMNS FROM orders LIKE ?");
        $stmt->execute([$column]);
        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return false;
    }
}

$hasPaymentStatus   = ordersColumnExists($conn, 'payment_status');
$hasCourierTracking = ordersColumnExists($conn, 'courier_tracking_number');
$hasCourierStatus   = ordersColumnExists($conn, 'courier_status_code');

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_status') {
        header('Content-Type: application/json');
            $order_id = $_POST['order_id'] ?? 0;
            $status = $_POST['status'] ?? '';
            $payment_status = $_POST['payment_status'] ?? '';
            
            if ($order_id && $status) {
                // Get order details before updating (scoped to store)
                $query = "SELECT * FROM orders WHERE id = ? AND store_id = ?";
                $stmt = $conn->prepare($query);
                $stmt->execute([$order_id, $store_id]);
                $order = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Update order status (scoped to store)
                if ($hasPaymentStatus) {
                    $query = "UPDATE orders SET status = ?, payment_status = ? WHERE id = ? AND store_id = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->execute([$status, $payment_status, $order_id, $store_id]);
                } else {
                    $query = "UPDATE orders SET status = ? WHERE id = ? AND store_id = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->execute([$status, $order_id, $store_id]);
                }
                
                // Send email notification if status changed
                if ($order && $order['status'] !== $status) {
                    $emailService->sendOrderStatusUpdate($order, $order['customer_email'], $status);
                }
                
            echo json_encode(['success' => true, 'message' => $t('order_status_updated_successfully', 'Order status updated successfully!')]);
        } else {
            echo json_encode(['success' => false, 'message' => $t('invalid_data', 'Invalid data')]);
        }
        exit;
    }

    if ($action === 'ship_firstdelivery') {
        header('Content-Type: application/json');
        $order_id = isset($_POST['order_id']) ? (int) $_POST['order_id'] : 0;

        if (!$order_id) {
            echo json_encode(['success' => false, 'message' => $t('invalid_data', 'Invalid data')]);
            exit;
        }

        if (!$pluginHelper->isPluginActive('firstdelivery')) {
            echo json_encode(['success' => false, 'message' => 'First Delivery plugin is not active']);
            exit;
        }

        try {
            $plugin = new FirstDeliveryPlugin($conn, $store_id);
            $res = $plugin->syncOrder($order_id);
            echo json_encode($res);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'refresh_firstdelivery') {
        header('Content-Type: application/json');
        $order_id = isset($_POST['order_id']) ? (int) $_POST['order_id'] : 0;

        if (!$order_id) {
            echo json_encode(['success' => false, 'message' => $t('invalid_data', 'Invalid data')]);
            exit;
        }

        if (!$pluginHelper->isPluginActive('firstdelivery')) {
            echo json_encode(['success' => false, 'message' => 'First Delivery plugin is not active']);
            exit;
        }

        try {
            $plugin = new FirstDeliveryPlugin($conn, $store_id);
            $res = $plugin->updateStatusByOrderId($order_id);
            echo json_encode($res);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'ship_colissimo') {
        header('Content-Type: application/json');
        $order_id = isset($_POST['order_id']) ? (int) $_POST['order_id'] : 0;

        if (!$order_id) {
            echo json_encode(['success' => false, 'message' => $t('invalid_data', 'Invalid data')]);
            exit;
        }

        if (!$pluginHelper->isPluginActive('colissimo')) {
            echo json_encode(['success' => false, 'message' => 'Colissimo plugin is not active']);
            exit;
        }

        try {
            $plugin = new ColissimoPlugin($conn, $store_id);
            $res = $plugin->createShipment($order_id);
            echo json_encode($res);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'refresh_colissimo') {
        header('Content-Type: application/json');
        $order_id = isset($_POST['order_id']) ? (int) $_POST['order_id'] : 0;

        if (!$order_id) {
            echo json_encode(['success' => false, 'message' => $t('invalid_data', 'Invalid data')]);
            exit;
        }

        if (!$pluginHelper->isPluginActive('colissimo')) {
            echo json_encode(['success' => false, 'message' => 'Colissimo plugin is not active']);
            exit;
        }

        try {
            $stmt = $conn->prepare("SELECT courier_tracking_number FROM orders WHERE id = ? AND store_id = ?");
            $stmt->execute([$order_id, $store_id]);
            $barcode = $stmt->fetchColumn();
            if (!$barcode) {
                echo json_encode(['success' => false, 'message' => 'No tracking number for this order']);
            } else {
                $plugin = new ColissimoPlugin($conn, $store_id);
                $res = $plugin->trackOrder($barcode);
                echo json_encode($res);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'ship_firstdelivery') {
        header('Content-Type: application/json');
        $order_id = isset($_POST['order_id']) ? (int) $_POST['order_id'] : 0;

        if (!$order_id || !$hasCourierTracking) {
            echo json_encode(['success' => false, 'message' => $t('invalid_data', 'Invalid data')]);
            exit;
        }

        if (!$pluginHelper->isPluginActive('firstdelivery')) {
            echo json_encode(['success' => false, 'message' => 'First Delivery plugin is not active']);
            exit;
        }

        try {
            $plugin = new FirstDeliveryPlugin($conn, $store_id);
            $res = $plugin->syncOrder($order_id);
            echo json_encode($res);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'refresh_firstdelivery') {
        header('Content-Type: application/json');
        $order_id = isset($_POST['order_id']) ? (int) $_POST['order_id'] : 0;

        if (!$order_id || !$hasCourierTracking) {
            echo json_encode(['success' => false, 'message' => $t('invalid_data', 'Invalid data')]);
            exit;
        }

        if (!$pluginHelper->isPluginActive('firstdelivery')) {
            echo json_encode(['success' => false, 'message' => 'First Delivery plugin is not active']);
            exit;
        }

        try {
            $plugin = new FirstDeliveryPlugin($conn, $store_id);
            $res = $plugin->updateStatusByOrderId($order_id);
            echo json_encode($res);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
}

// Get filters
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$payment_filter = $_GET['payment'] ?? '';
$sort_by = $_GET['sort'] ?? 'created_at';
$sort_order = $_GET['order'] ?? 'DESC';
$page = $_GET['p'] ?? 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Build query (scoped to store)
$where_conditions = ["o.store_id = ?"];
$params = [$store_id];

if ($search) {
    $where_conditions[] = "(order_number LIKE ? OR customer_name LIKE ? OR customer_email LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if ($status_filter) {
    $where_conditions[] = "o.status = ?";
    $params[] = $status_filter;
}

if ($payment_filter && $hasPaymentStatus) {
    $where_conditions[] = "o.payment_status = ?";
    $params[] = $payment_filter;
}

$visibility = PlanHelper::orderVisibilityCondition($conn, $store_id, 'o');
if ($visibility['sql'] !== '') {
    $where_conditions[] = ltrim(trim($visibility['sql']), ' AND');
    $params = array_merge($params, $visibility['params']);
}

$where_clause = implode(' AND ', $where_conditions);

// Get total orders
$count_query = "SELECT COUNT(*) as total FROM orders o WHERE $where_clause";
$count_stmt = $conn->prepare($count_query);
$count_stmt->execute($params);
$total_orders = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_orders / $per_page);

// Get orders
$query = "SELECT o.*, COUNT(oi.id) as item_count 
          FROM orders o 
          LEFT JOIN order_items oi ON o.id = oi.order_id 
          WHERE $where_clause 
          GROUP BY o.id 
          ORDER BY o.$sort_by $sort_order 
          LIMIT $per_page OFFSET $offset";
$stmt = $conn->prepare($query);
$stmt->execute($params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics (restricted by plan order visibility when applicable)
$stats_visibility = PlanHelper::orderVisibilityCondition($conn, $store_id, 'orders');
$stats_where = "store_id = ?" . ($stats_visibility['sql'] !== '' ? trim($stats_visibility['sql']) : '');
$stats_params = array_merge([$store_id], $stats_visibility['params']);
try {
    if ($hasPaymentStatus) {
        $stats_query = "SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing,
            SUM(CASE WHEN status = 'shipped' THEN 1 ELSE 0 END) as shipped,
            SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered,
            SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as paid
            FROM orders WHERE $stats_where";
    } else {
        $stats_query = "SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing,
            SUM(CASE WHEN status = 'shipped' THEN 1 ELSE 0 END) as shipped,
            SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered,
            0 as paid
            FROM orders WHERE $stats_where";
    }
    $stmt = $conn->prepare($stats_query);
    $stmt->execute($stats_params);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats = is_array($stats) ? array_merge(['total' => 0, 'pending' => 0, 'processing' => 0, 'shipped' => 0, 'delivered' => 0, 'paid' => 0], $stats) : ['total' => 0, 'pending' => 0, 'processing' => 0, 'shipped' => 0, 'delivered' => 0, 'paid' => 0];
    foreach (['total', 'pending', 'processing', 'shipped', 'delivered', 'paid'] as $k) {
        $stats[$k] = (int)($stats[$k] ?? 0);
    }
} catch (Exception $e) {
    $stats = ['total' => 0, 'pending' => 0, 'processing' => 0, 'shipped' => 0, 'delivered' => 0, 'paid' => 0];
}

BillingHelper::init($conn);
?>


<!-- Modern Orders Management Interface -->
<div class="orders-management-container">
    
    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-card-header">
                <div class="stat-card-icon primary">
                    <i class="fas fa-shopping-cart"></i>
                </div>
            </div>
            <div class="stat-card-content">
                <div class="stat-card-value"><?php echo (int)($stats['total'] ?? 0); ?></div>
                <div class="stat-card-label"><?php echo $t('total_orders', 'Total Orders'); ?></div>
                <div class="stat-card-change positive">
                    <i class="fas fa-arrow-up"></i>
                    <span><?php echo $t('all_orders', 'All Orders'); ?></span>
                </div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-card-header">
                <div class="stat-card-icon warning">
                    <i class="fas fa-clock"></i>
                </div>
            </div>
            <div class="stat-card-content">
                <div class="stat-card-value"><?php echo (int)($stats['pending'] ?? 0); ?></div>
                <div class="stat-card-label"><?php echo $t('pending_orders', 'Pending Orders'); ?></div>
                <div class="stat-card-change <?php echo $stats['pending'] > 0 ? 'negative' : 'positive'; ?>">
                    <i class="fas fa-<?php echo $stats['pending'] > 0 ? 'exclamation' : 'check'; ?>"></i>
                    <span><?php echo $stats['pending'] > 0 ? $t('needs_attention', 'Needs Attention') : $t('all_processed', 'All Processed'); ?></span>
                </div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-card-header">
                <div class="stat-card-icon info">
                    <i class="fas fa-cog"></i>
                </div>
            </div>
            <div class="stat-card-content">
                <div class="stat-card-value"><?php echo (int)($stats['processing'] ?? 0); ?></div>
                <div class="stat-card-label"><?php echo $t('processing'); ?></div>
                <div class="stat-card-change positive">
                    <i class="fas fa-arrow-up"></i>
                    <span><?php echo $t('in_progress', 'In Progress'); ?></span>
                </div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-card-header">
                <div class="stat-card-icon success">
                    <i class="fas fa-check-circle"></i>
                </div>
            </div>
            <div class="stat-card-content">
                <div class="stat-card-value"><?php echo (int)($stats['delivered'] ?? 0); ?></div>
                <div class="stat-card-label"><?php echo $t('delivered'); ?></div>
                <div class="stat-card-change positive">
                    <i class="fas fa-check"></i>
                    <span><?php echo $stats['total'] > 0 ? round(($stats['delivered'] / $stats['total']) * 100, 1) : 0; ?>% <?php echo $t('complete', 'Complete'); ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters and Search Bar -->
    <div class="filters-section">
        <form method="GET" class="filters-form">
            <input type="hidden" name="page" value="orders">
            
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" name="search" placeholder="<?php echo $t('search_orders_placeholder', 'Search orders...'); ?>" 
                       value="<?php echo htmlspecialchars($search); ?>">
            </div>
            
            <div class="filter-group">
                <select name="status" class="filter-select" onchange="this.form.submit()">
                    <option value=""><?php echo $t('all_status'); ?></option>
                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>><?php echo $t('pending'); ?></option>
                    <option value="processing" <?php echo $status_filter === 'processing' ? 'selected' : ''; ?>><?php echo $t('processing'); ?></option>
                    <option value="shipped" <?php echo $status_filter === 'shipped' ? 'selected' : ''; ?>><?php echo $t('shipped', 'Shipped'); ?></option>
                    <option value="delivered" <?php echo $status_filter === 'delivered' ? 'selected' : ''; ?>><?php echo $t('delivered'); ?></option>
                    <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>><?php echo $t('cancelled', 'Cancelled'); ?></option>
                </select>
            </div>
            
            <div class="filter-group">
                <select name="payment" class="filter-select" onchange="this.form.submit()">
                    <option value=""><?php echo $t('all_payment', 'All Payment'); ?></option>
                    <option value="pending" <?php echo $payment_filter === 'pending' ? 'selected' : ''; ?>><?php echo $t('pending'); ?></option>
                    <option value="paid" <?php echo $payment_filter === 'paid' ? 'selected' : ''; ?>><?php echo $t('paid', 'Paid'); ?></option>
                    <option value="failed" <?php echo $payment_filter === 'failed' ? 'selected' : ''; ?>><?php echo $t('failed', 'Failed'); ?></option>
                    <option value="refunded" <?php echo $payment_filter === 'refunded' ? 'selected' : ''; ?>><?php echo $t('refunded', 'Refunded'); ?></option>
                </select>
            </div>
            
            <div class="filter-group">
                <select name="sort" class="filter-select" onchange="this.form.submit()">
                    <option value="created_at" <?php echo $sort_by === 'created_at' ? 'selected' : ''; ?>><?php echo $t('newest_first'); ?></option>
                    <option value="order_number" <?php echo $sort_by === 'order_number' ? 'selected' : ''; ?>><?php echo $t('order_number'); ?></option>
                    <option value="total_amount" <?php echo $sort_by === 'total_amount' ? 'selected' : ''; ?>><?php echo $t('amount', 'Amount'); ?></option>
                    <option value="customer_name" <?php echo $sort_by === 'customer_name' ? 'selected' : ''; ?>><?php echo $t('customer', 'Customer'); ?></option>
            </select>
    </div>
            
            <button type="submit" class="btn-filter">
                <i class="fas fa-filter"></i>
                <?php echo $t('apply'); ?>
            </button>
            
            <button class="btn-clear" id="clearFiltersBtn" style="display:none;" onclick="clearAllFilters()">
                <i class="fas fa-times"></i> <?php echo $t('clear_all_filters', 'Clear All Filters'); ?>
            </button>
            
            <?php if ($search || $status_filter || $payment_filter): ?>
                <a href="?page=orders" class="btn-clear">
                    <i class="fas fa-times"></i>
                    <?php echo $t('clear_server_filters', 'Clear Server Filters'); ?>
                </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Orders Container -->
    <div class="orders-container">
        <div class="orders-header">
            <div class="results-info">
                <label class="select-all-checkbox">
                    <input type="checkbox" id="selectAllOrders" onchange="toggleSelectAll()" aria-label="<?php echo $t('select_all_orders', 'Select all orders'); ?>">
                    <span class="checkbox-label"><?php echo $t('select_all'); ?></span>
                </label>
                <span class="results-text">
                    <?php echo $t('showing'); ?> <span id="visibleCount"><?php echo count($orders); ?></span> <?php echo $t('of'); ?> <span id="totalCount"><?php echo $total_orders; ?></span> <?php echo $t('orders', 'orders'); ?>
                </span>
            </div>
        </div>

        <div class="orders-list">
                    <?php foreach ($orders as $order):
                        $order_locked = BillingHelper::shouldLockOrder($store_id, $order['created_at']);
                        $LOCKED = '*** LOCKED ***';
                    ?>
                <div class="order-card" data-order-id="<?php echo $order['id']; ?>">
                    <?php if ($order_locked): ?>
                    <div class="alert alert-warning upgrade-banner" style="margin:0 0 0.75rem 0; padding:0.5rem 0.75rem;">
                        <strong><?php echo $t('upgrade_required', 'Upgrade Required'); ?></strong> — <?php echo $t('order_placed_after_expiry', 'This order was placed after your subscription ended.'); ?> <a href="#" class="btn btn-sm btn-primary"><?php echo $t('pay_to_unlock', 'Pay to Unlock'); ?></a>
                    </div>
                    <?php endif; ?>
                    <div class="order-checkbox-wrapper">
                        <input type="checkbox" 
                               class="order-checkbox" 
                               id="order-checkbox-<?php echo $order['id']; ?>" 
                               data-order-id="<?php echo $order['id']; ?>"
                               onchange="toggleOrderSelection(<?php echo $order['id']; ?>)"
                               aria-label="Select order <?php echo htmlspecialchars($order['order_number']); ?>">
                    </div>
                    <div class="order-header">
                        <div class="order-info">
                            <h3 class="order-number">#<?php echo htmlspecialchars($order['order_number']); ?></h3>
                            <div class="order-meta">
                                <span class="order-date">
                                    <i class="fas fa-calendar"></i>
                                    <?php echo date('M j, Y g:i A', strtotime($order['created_at'])); ?>
                                </span>
                                <span class="order-items">
                                    <i class="fas fa-box"></i>
                                    <?php echo $order['item_count']; ?> <?php echo $t('items'); ?>
                                </span>
                            </div>
                                </div>
                        <div class="order-badges">
                            <span class="status-badge status-<?php echo $order['status']; ?>">
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
                            <?php if ($hasPaymentStatus): ?>
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
                    </div>
                    
                    <div class="order-body">
                        <div class="order-customer">
                            <div class="customer-icon">
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="customer-details">
                                <div class="customer-name <?php echo $order_locked ? '' : 'clickable-filter'; ?>" <?php if (!$order_locked): ?>onclick="filterByCustomer('<?php echo htmlspecialchars($order['customer_name']); ?>')" title="<?php echo $t('click_to_filter', 'Click to filter by this customer'); ?>"<?php endif; ?>>
                                    <?php echo $order_locked ? $LOCKED : htmlspecialchars($order['customer_name']); ?>
                                </div>
                                <div class="customer-email <?php echo $order_locked ? '' : 'clickable-filter'; ?>" <?php if (!$order_locked): ?>onclick="filterByCustomer('<?php echo htmlspecialchars($order['customer_email']); ?>')" title="<?php echo $t('click_to_filter', 'Click to filter by this email'); ?>"<?php endif; ?>>
                                    <?php echo $order_locked ? $LOCKED : htmlspecialchars($order['customer_email']); ?>
                                </div>
                                <?php if (!empty($order['customer_phone']) || $order_locked): ?>
                                    <div class="customer-phone <?php echo $order_locked ? '' : 'clickable-filter'; ?>" <?php if (!$order_locked && !empty($order['customer_phone'])): ?>onclick="filterByCustomer('<?php echo htmlspecialchars($order['customer_phone']); ?>')" title="<?php echo $t('click_to_filter', 'Click to filter'); ?>"<?php endif; ?>>
                                        <?php echo $order_locked ? $LOCKED : htmlspecialchars($order['customer_phone'] ?? ''); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="order-amount">
                            <div class="amount-label"><?php echo $t('total_amount', 'Total Amount'); ?></div>
                            <div class="amount-value"><?php echo $order_locked ? $LOCKED : ($currency_position === 'left' ? $currency_symbol . number_format($order['total_amount'], 2) : number_format($order['total_amount'], 2) . ' ' . $currency_symbol); ?></div>
                        </div>

                        <?php if ($hasCourierTracking && ($pluginHelper->isPluginActive('firstdelivery') || $pluginHelper->isPluginActive('colissimo'))): ?>
                        <div class="order-shipping">
                            <div class="shipping-label"><?php echo $t('shipping', 'Shipping'); ?></div>
                            <?php
                                $tracking = $order['courier_tracking_number'] ?? '';
                                $code     = $order['courier_status_code'] ?? null;
                                $courier  = $order['courier_name'] ?? '';
                                $rawText  = $order['courier_status_text'] ?? null;
                            ?>
                            <?php if (empty($tracking)): ?>
                                <?php if ($pluginHelper->isPluginActive('firstdelivery')): ?>
                                    <button type="button"
                                            class="btn-action secondary"
                                            onclick="shipWithFirstDelivery(<?php echo (int) $order['id']; ?>)">
                                        <i class="fas fa-truck"></i>
                                        <?php echo $t('ship_with_first_delivery', 'Ship with First Delivery'); ?>
                                    </button>
                                <?php endif; ?>
                                <?php if ($pluginHelper->isPluginActive('colissimo')): ?>
                                    <button type="button"
                                            class="btn-action secondary"
                                            onclick="shipWithColissimo(<?php echo (int) $order['id']; ?>)">
                                        <i class="fas fa-truck-loading"></i>
                                        Ship with Colissimo
                                    </button>
                                <?php endif; ?>
                            <?php else: ?>
                                <?php
                                    $sourceCode = $rawText !== null ? $rawText : ($code ?? '');
                                    $map = ShippingHelper::mapStatus($courier ?: 'first_delivery', $sourceCode);
                                    $badgeClass = ShippingHelper::getBadgeClass($map['internal']);
                                ?>
                                <div class="shipping-info">
                                    <div>
                                        <small class="text-muted"><?php echo $t('tracking_number', 'Tracking Number'); ?></small><br>
                                        <strong><?php echo htmlspecialchars($tracking); ?></strong>
                                    </div>
                                    <div>
                                        <small class="text-muted"><?php echo $t('shipping_status', 'Shipping Status'); ?></small><br>
                                        <span class="shipping-badge <?php echo $badgeClass; ?>">
                                            <?php echo htmlspecialchars($map['label']); ?>
                                        </span>
                                    </div>
                                    <div>
                                        <?php if ($courier === 'first_delivery' || $courier === '' || $courier === null): ?>
                                            <button type="button"
                                                    class="btn-action secondary"
                                                    onclick="refreshFirstDeliveryStatus(<?php echo (int) $order['id']; ?>)">
                                                <i class="fas fa-sync-alt"></i>
                                                <?php echo $t('refresh_shipping_status', 'Refresh Status'); ?>
                                            </button>
                                        <?php elseif ($courier === 'colissimo'): ?>
                                            <button type="button"
                                                    class="btn-action secondary"
                                                    onclick="refreshColissimoStatus(<?php echo (int) $order['id']; ?>)">
                                                <i class="fas fa-sync-alt"></i>
                                                <?php echo $t('refresh_shipping_status', 'Refresh Status'); ?>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="order-footer">
                        <button class="btn-action primary" onclick="viewOrder(<?php echo $order['id']; ?>)">
                                        <i class="fas fa-eye"></i>
                            <?php echo $t('view_details', 'View Details'); ?>
                        </button>
                        <button class="btn-action secondary" onclick="editOrderStatus(<?php echo $order['id']; ?>, '<?php echo $order['status']; ?>', '<?php echo $hasPaymentStatus ? $order['payment_status'] : 'pending'; ?>')">
                                        <i class="fas fa-edit"></i>
                            <?php echo $t('update_status', 'Update Status'); ?>
                                    </button>
                                </div>
                </div>
                    <?php endforeach; ?>
        </div>
        
        <!-- Bulk Actions Toolbar -->
        <div class="bulk-actions-toolbar" id="bulkActionsToolbar" style="display: none;">
            <div class="bulk-actions-content">
                <div class="bulk-actions-info">
                    <i class="fas fa-check-circle"></i>
                    <span id="selectedCount">0</span> <span id="selectedText"><?php echo $t('orders_selected', 'orders selected'); ?></span>
                </div>
                <div class="bulk-actions-buttons">
                    <button class="btn-bulk-action primary" onclick="printSelectedInvoices()" id="printInvoicesBtn">
                        <i class="fas fa-print"></i> <?php echo $t('print_invoices'); ?>
                    </button>
                    <button class="btn-bulk-action secondary" onclick="clearSelection()">
                        <i class="fas fa-times"></i> <?php echo $t('clear_selection', 'Clear Selection'); ?>
                    </button>
                </div>
            </div>
        </div>
            
            <?php if (empty($orders)): ?>
                <div class="empty-state">
                    <i class="fas fa-shopping-bag fa-4x"></i>
                    <h3><?php echo $t('no_orders'); ?></h3>
                    <p><?php echo $t('no_orders_match_filters', 'No orders match your current filters'); ?></p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                    <?php if ($page > 1): ?>
                    <a href="?page=orders&p=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&payment=<?php echo $payment_filter; ?>" 
                       class="page-btn">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <a href="?page=orders&p=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&payment=<?php echo $payment_filter; ?>" 
                       class="page-btn <?php echo $i == $page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                    <a href="?page=orders&p=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&payment=<?php echo $payment_filter; ?>" 
                       class="page-btn">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                    <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Order Details Modal -->
<div class="modal-overlay" id="orderDetailsModal">
    <div class="modal-container large">
            <div class="modal-header">
            <h2><i class="fas fa-shopping-bag"></i> <span id="orderModalTitle"><?php echo $t('order_details'); ?></span></h2>
            <button class="modal-close" onclick="closeOrderModal()">
                <i class="fas fa-times"></i>
            </button>
            </div>
        
        <div class="modal-body" id="orderDetailsContent">
            <!-- Content will be loaded here -->
                    </div>
                    </div>
                </div>
                
<!-- Update Status Modal -->
<div class="modal-overlay" id="updateStatusModal">
    <div class="modal-container">
        <div class="modal-header">
            <h2><i class="fas fa-edit"></i> <?php echo $t('update_order_status', 'Update Order Status'); ?></h2>
            <button class="modal-close" onclick="closeStatusModal()">
                <i class="fas fa-times"></i>
            </button>
                </div>
                
        <form id="statusForm" onsubmit="return updateOrderStatus(event)">
            <div class="modal-body">
                    <?php echo CsrfHelper::getTokenField(); ?>
                    <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="order_id" id="statusOrderId">
                
                <div class="form-group">
                    <label><?php echo $t('order_status'); ?></label>
                    <select name="status" id="statusSelect" class="form-input">
                        <option value="pending"><?php echo $t('pending'); ?></option>
                        <option value="processing"><?php echo $t('processing'); ?></option>
                        <option value="shipped"><?php echo $t('shipped', 'Shipped'); ?></option>
                        <option value="delivered"><?php echo $t('delivered'); ?></option>
                        <option value="cancelled"><?php echo $t('cancelled', 'Cancelled'); ?></option>
                            </select>
                        </div>
                
                <div class="form-group">
                    <label><?php echo $t('payment_status', 'Payment Status'); ?></label>
                    <select name="payment_status" id="paymentStatusSelect" class="form-input">
                        <option value="pending"><?php echo $t('pending'); ?></option>
                        <option value="paid"><?php echo $t('paid', 'Paid'); ?></option>
                        <option value="failed"><?php echo $t('failed', 'Failed'); ?></option>
                        <option value="refunded"><?php echo $t('refunded', 'Refunded'); ?></option>
                            </select>
                        </div>
                    </div>
                    
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeStatusModal()">
                    <i class="fas fa-times"></i> <?php echo $t('cancel'); ?>
                </button>
                <button type="submit" class="btn-primary">
                    <i class="fas fa-save"></i> <?php echo $t('update_status', 'Update Status'); ?>
                </button>
                    </div>
                </form>
            </div>
        </div>

<script>
// Pass translations to JavaScript
window.translations = {
    orders_selected: <?php echo json_encode($t('orders_selected', 'orders selected')); ?>,
    order_selected: <?php echo json_encode($t('order_selected', 'order selected')); ?>,
    please_select_order: <?php echo json_encode($t('please_select_order', 'Please select at least one order to print.')); ?>,
    preparing: <?php echo json_encode($t('preparing', 'Preparing...')); ?>,
    allow_popups: <?php echo json_encode($t('allow_popups', 'Please allow popups for this site to print invoices.')); ?>,
    error_printing: <?php echo json_encode($t('error_printing', 'An error occurred while printing invoices. Please try again.')); ?>,
    order_status_updated_successfully: <?php echo json_encode($t('order_status_updated_successfully', 'Order status updated successfully!')); ?>,
    error_updating_status: <?php echo json_encode($t('error_updating_status', 'Error updating order status')); ?>,
    ship_with_first_delivery: <?php echo json_encode($t('ship_with_first_delivery', 'Ship with First Delivery')); ?>,
    refresh_shipping_status: <?php echo json_encode($t('refresh_shipping_status', 'Refresh Status')); ?>
};

// Debounce function for performance
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

function postOrdersAction(data) {
    return fetch('index.php?page=orders', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
        body: new URLSearchParams(data)
    }).then(function (r) { return r.json(); });
}

function shipWithFirstDelivery(orderId) {
    if (!confirm(window.translations.ship_with_first_delivery + ' ?')) return;
    postOrdersAction({action: 'ship_firstdelivery', order_id: orderId})
        .then(function (res) {
            if (!res || !res.success) {
                alert(res && res.message ? res.message : 'Error');
                return;
            }
            location.reload();
        })
        .catch(function () { alert('Error'); });
}

function refreshFirstDeliveryStatus(orderId) {
    postOrdersAction({action: 'refresh_firstdelivery', order_id: orderId})
        .then(function (res) {
            if (!res || !res.success) {
                alert(res && res.message ? res.message : 'Error');
                return;
            }
            location.reload();
        })
        .catch(function () { alert('Error'); });
}

function shipWithColissimo(orderId) {
    if (!confirm('Ship with Colissimo ?')) return;
    postOrdersAction({action: 'ship_colissimo', order_id: orderId})
        .then(function (res) {
            if (!res || !res.success) {
                alert(res && res.message ? res.message : 'Error');
                return;
            }
            location.reload();
        })
        .catch(function () { alert('Error'); });
}

function refreshColissimoStatus(orderId) {
    postOrdersAction({action: 'refresh_colissimo', order_id: orderId})
        .then(function (res) {
            if (!res || !res.success) {
                alert(res && res.message ? res.message : 'Error');
                return;
            }
            location.reload();
        })
        .catch(function () { alert('Error'); });
}

// Real-time search functionality
document.addEventListener('DOMContentLoaded', function() {
    // Check if we need to open status modal from order details page
    const editOrderStatusId = sessionStorage.getItem('editOrderStatusId');
    if (editOrderStatusId) {
        const currentStatus = sessionStorage.getItem('editOrderStatus') || 'pending';
        const currentPayment = sessionStorage.getItem('editOrderPayment') || 'pending';
        sessionStorage.removeItem('editOrderStatusId');
        sessionStorage.removeItem('editOrderStatus');
        sessionStorage.removeItem('editOrderPayment');
        // Open the modal
        setTimeout(() => {
            editOrderStatus(parseInt(editOrderStatusId), currentStatus, currentPayment);
        }, 100);
    }
    
    const searchInput = document.querySelector('.search-box input');
    const clearBtn = document.getElementById('clearFiltersBtn');
    
    if (searchInput) {
        searchInput.addEventListener('input', debounce(function(e) {
            const searchTerm = e.target.value.toLowerCase();
            let visibleCount = 0;
            
            document.querySelectorAll('.order-card').forEach(card => {
                const text = card.textContent.toLowerCase();
                const isVisible = text.includes(searchTerm);
                card.style.display = isVisible ? '' : 'none';
                if (isVisible) visibleCount++;
            });
            
            updateResultsCount(visibleCount);
            toggleClearButton(searchTerm.length > 0);
        }, 300));
    }
});

// Filter by customer details
function filterByCustomer(value) {
    const searchInput = document.querySelector('.search-box input');
    if (searchInput) {
        searchInput.value = value;
        searchInput.dispatchEvent(new Event('input'));
    }
}

// Update results counter
function updateResultsCount(visibleCount = null) {
    const totalCount = document.querySelectorAll('.order-card').length;
    const visible = visibleCount !== null ? visibleCount : document.querySelectorAll('.order-card:not([style*="display: none"])').length;
    
    const totalElement = document.getElementById('totalCount');
    const visibleElement = document.getElementById('visibleCount');
    
    if (totalElement) totalElement.textContent = totalCount;
    if (visibleElement) visibleElement.textContent = visible;
}

// Toggle clear button visibility
function toggleClearButton(show) {
    const clearBtn = document.getElementById('clearFiltersBtn');
    if (clearBtn) {
        clearBtn.style.display = show ? 'flex' : 'none';
    }
}

// Clear all filters
function clearAllFilters() {
    const searchInput = document.querySelector('.search-box input');
    if (searchInput) {
        searchInput.value = '';
        searchInput.dispatchEvent(new Event('input'));
    }
    
    // Reset all dropdowns
    document.querySelectorAll('.filter-select').forEach(select => {
        select.selectedIndex = 0;
    });
    
    toggleClearButton(false);
}

// Bulk Actions Functions
let selectedOrders = new Set();

function toggleOrderSelection(orderId) {
    const checkbox = document.getElementById(`order-checkbox-${orderId}`);
    const orderCard = checkbox.closest('.order-card');
    
    if (checkbox.checked) {
        selectedOrders.add(orderId);
        orderCard.classList.add('selected');
    } else {
        selectedOrders.delete(orderId);
        orderCard.classList.remove('selected');
    }
    
    updateBulkActionsBar();
    updateSelectAllCheckbox();
}

function toggleSelectAll() {
    const selectAllCheckbox = document.getElementById('selectAllOrders');
    const isChecked = selectAllCheckbox.checked;
    
    document.querySelectorAll('.order-checkbox').forEach(checkbox => {
        const orderCard = checkbox.closest('.order-card');
        // Only select visible orders
        if (orderCard && orderCard.style.display !== 'none') {
            checkbox.checked = isChecked;
            const orderId = parseInt(checkbox.dataset.orderId);
            
            if (isChecked) {
                selectedOrders.add(orderId);
                orderCard.classList.add('selected');
            } else {
                selectedOrders.delete(orderId);
                orderCard.classList.remove('selected');
            }
        }
    });
    
    updateBulkActionsBar();
}

function updateSelectAllCheckbox() {
    const selectAllCheckbox = document.getElementById('selectAllOrders');
    const visibleCheckboxes = Array.from(document.querySelectorAll('.order-checkbox')).filter(cb => {
        const orderCard = cb.closest('.order-card');
        return orderCard && orderCard.style.display !== 'none';
    });
    const checkedCheckboxes = visibleCheckboxes.filter(cb => cb.checked);
    
    if (visibleCheckboxes.length === 0) {
        selectAllCheckbox.indeterminate = false;
        selectAllCheckbox.checked = false;
    } else if (checkedCheckboxes.length === visibleCheckboxes.length) {
        selectAllCheckbox.indeterminate = false;
        selectAllCheckbox.checked = true;
    } else if (checkedCheckboxes.length > 0) {
        selectAllCheckbox.indeterminate = true;
        selectAllCheckbox.checked = false;
    } else {
        selectAllCheckbox.indeterminate = false;
        selectAllCheckbox.checked = false;
    }
}

function updateBulkActionsBar() {
    const toolbar = document.getElementById('bulkActionsToolbar');
    const selectedCount = document.getElementById('selectedCount');
    const selectedText = document.getElementById('selectedText');
    
    const count = selectedOrders.size;
    
    if (count > 0) {
        toolbar.style.display = 'flex';
        selectedCount.textContent = count;
        selectedText.textContent = count === 1 ? window.translations.order_selected : window.translations.orders_selected;
    } else {
        toolbar.style.display = 'none';
    }
}

function clearSelection() {
    selectedOrders.clear();
    document.querySelectorAll('.order-checkbox').forEach(checkbox => {
        checkbox.checked = false;
        const orderCard = checkbox.closest('.order-card');
        if (orderCard) {
            orderCard.classList.remove('selected');
        }
    });
    document.getElementById('selectAllOrders').checked = false;
    document.getElementById('selectAllOrders').indeterminate = false;
    updateBulkActionsBar();
}

function getSelectedOrders() {
    return Array.from(selectedOrders);
}

async function printSelectedInvoices() {
    const selectedIds = getSelectedOrders();
    
    if (selectedIds.length === 0) {
        alert(window.translations.please_select_order);
        return;
    }
    
    // Show loading state
    const printBtn = document.getElementById('printInvoicesBtn');
    const originalText = printBtn.innerHTML;
    printBtn.disabled = true;
    printBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ' + window.translations.preparing;
    
    try {
        // Open bulk print page with all selected order IDs
        const orderIdsParam = selectedIds.join(',');
        const printWindow = window.open(`?page=order_bulk_print&ids=${orderIdsParam}`, '_blank', 'width=1200,height=800');
        
        if (!printWindow) {
            alert(window.translations.allow_popups);
            return;
        }
        
        // The print will be triggered automatically in the print view
    } catch (error) {
        console.error('Error printing invoices:', error);
        alert(window.translations.error_printing);
    } finally {
        // Restore button state after a delay
        setTimeout(() => {
            printBtn.disabled = false;
            printBtn.innerHTML = originalText;
        }, 1000);
    }
}

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Focus search with /
    if (e.key === '/' && !e.target.matches('input, textarea')) {
        e.preventDefault();
        const searchInput = document.querySelector('.search-box input');
        if (searchInput) searchInput.focus();
    }
    
    // Clear filters with Escape
    if (e.key === 'Escape') {
        clearAllFilters();
    }
    
    // Refresh with R
    if (e.key === 'r' && e.ctrlKey) {
        e.preventDefault();
        window.location.reload();
    }
});

function viewOrder(orderId) {
    // Navigate to order details page
    window.location.href = '?page=order_view_details&id=' + orderId;
}

function closeOrderModal() {
    document.getElementById('orderDetailsModal').classList.remove('active');
    document.body.style.overflow = '';
}

function editOrderStatus(orderId, currentStatus, currentPayment) {
    document.getElementById('statusOrderId').value = orderId;
    document.getElementById('statusSelect').value = currentStatus;
    document.getElementById('paymentStatusSelect').value = currentPayment;
    document.getElementById('updateStatusModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeStatusModal() {
    document.getElementById('updateStatusModal').classList.remove('active');
    document.body.style.overflow = '';
}

function updateOrderStatus(event) {
    event.preventDefault();
    const formData = new FormData(event.target);
    
    fetch('?page=orders', {
        method: 'POST',
        body: new URLSearchParams(formData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeStatusModal();
            location.reload();
        } else {
            alert('<?php echo $t('error'); ?>: ' + (data.message || '<?php echo $t('failed_to_update_status'); ?>'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert(window.translations.error_updating_status);
    });
    
    return false;
}

function exportOrders() {
    alert('Export functionality coming soon!');
}

// Close modals on overlay click
document.getElementById('orderDetailsModal').addEventListener('click', function(e) {
    if (e.target === this) closeOrderModal();
});

document.getElementById('updateStatusModal').addEventListener('click', function(e) {
    if (e.target === this) closeStatusModal();
});
</script>

<style>
/* Modern Orders Management Styles */
:root {
    --primary: #000000;
    --success: #10b981;
    --danger: #ef4444;
    --warning: #f59e0b;
    --info: #3b82f6;
    --purple: #8b5cf6;
}

.orders-management-container {
    padding: 0;
}

/* Statistics Cards Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: var(--bg-card);
    border-radius: 6px;
    padding: 1.5rem;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
    border: 1px solid var(--border-primary);
}

.stat-card:hover {
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--color-primary-db), var(--color-accent));
    border-radius: 16px 16px 0 0;
}

.stat-card-header {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    margin-bottom: 0;
    min-height: 0;
    pointer-events: none;
}

.stat-card-icon {
    width: 48px;
    height: 48px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    color: white;
    flex-shrink: 0;
    position: absolute;
    top: 1.25rem;
    right: 1.25rem;
}

.stat-card-icon.primary {
    background: linear-gradient(135deg, var(--color-primary-db), var(--color-primary-db-hover));
}

.stat-card-icon.success {
    background: linear-gradient(135deg, #10b981, #059669);
}

.stat-card-icon.warning {
    background: linear-gradient(135deg, #f59e0b, #d97706);
}

.stat-card-icon.info {
    background: linear-gradient(135deg, #3b82f6, #2563eb);
}

.stat-card-content {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    padding-right: 4.5rem;
}

.stat-card-value {
    font-size: 2rem;
    font-weight: 700;
    color: var(--text-primary);
    line-height: 1;
}

.stat-card-label {
    color: var(--text-secondary);
    font-size: 0.875rem;
    font-weight: 500;
}

.stat-card-change {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.875rem;
    font-weight: 500;
}

.stat-card-change.positive {
    color: #10b981;
}

.stat-card-change.negative {
    color: #ef4444;
}

.stat-card-change i {
    font-size: 0.75rem;
}

/* Filters Section */
.filters-section {
    background: var(--bg-card);
    border-radius: 6px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.filters-form {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
}

.search-box {
    flex: 1;
    min-width: 300px;
    position: relative;
}

.search-box i {
    position: absolute;
    left: 1.25rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-tertiary);
}

.search-box input {
    width: 100%;
    padding: 0.875rem 1rem 0.875rem 3rem;
    border: 2px solid var(--border-primary);
    border-radius: 6px;
    font-size: 0.9375rem;
    background: var(--bg-card);
    color: var(--text-primary);
    transition: all 0.3s ease;
}

.search-box input:focus {
    outline: none;
    border-color: var(--color-primary-db);
    box-shadow: 0 0 0 3px var(--color-primary-db-light);
}

.search-box input::placeholder {
    color: var(--text-tertiary);
}

.filter-group {
    min-width: 180px;
}

.filter-select {
    width: 100%;
    padding: 0.875rem 1rem;
    border: 2px solid var(--border-primary);
    border-radius: 6px;
    font-size: 0.9375rem;
    background: var(--bg-card);
    color: var(--text-primary);
    cursor: pointer;
    transition: all 0.3s ease;
}

.filter-select:focus {
    outline: none;
    border-color: var(--color-primary-db);
    box-shadow: 0 0 0 3px var(--color-primary-db-light);
}

.btn-filter, .btn-clear {
    padding: 0 1.5rem;
    height: 48px;
    border-radius: 6px;
    border: none;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
}

.btn-filter {
    background: var(--color-primary-db);
    color: var(--text-inverse);
}

.btn-filter:hover {
    background: var(--color-primary-db-hover);
}

.btn-clear {
    background: var(--bg-tertiary);
    color: var(--text-secondary);
}

.btn-clear:hover {
    background: var(--border-primary);
}

/* Orders Container */
.orders-container {
    background: var(--bg-card);
    border-radius: 6px;
    padding: 1.5rem;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.orders-header {
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid #f1f5f9;
}

.results-info {
    font-size: 0.9375rem;
    color: var(--text-secondary);
    font-weight: 500;
}

/* Orders List */
.orders-list {
    display: grid;
    gap: 1.5rem;
}

.order-card {
    background: var(--bg-card);
    border: 2px solid #f1f5f9;
    border-radius: 6px;
    overflow: hidden;
    transition: all 0.3s ease;
    position: relative;
    padding-left: 2.5rem;
}

.order-card:hover {
    border-color: var(--color-primary-db);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
    transform: translateY(-2px);
}

.order-header {
    padding: 1.5rem;
    background: var(--bg-secondary);
    border-bottom: 2px solid #f1f5f9;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
}

.order-number {
    font-size: 1.25rem;
    font-weight: 700;
    margin: 0 0 0.5rem 0;
    color: var(--text-primary);
}

.order-meta {
    display: flex;
    gap: 1.5rem;
    font-size: 0.875rem;
    color: var(--text-secondary);
}

.order-meta span {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.order-badges {
    display: flex;
    gap: 0.75rem;
}

.status-badge, .payment-badge {
    padding: 0.5rem 1rem;
    border-radius: 6px;
    font-size: 0.8125rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.status-badge.status-pending {
    background: var(--color-warning-light);
    color: #92400e;
}

.status-badge.status-processing {
    background: var(--color-info-light);
    color: #1e40af;
}

.status-badge.status-shipped {
    background: var(--color-primary-light);
    color: #3730a3;
}

.status-badge.status-delivered {
    background: var(--color-success-light);
    color: #166534;
}

.status-badge.status-cancelled {
    background: var(--color-error-light);
    color: #991b1b;
}

.payment-badge.payment-pending {
    background: var(--color-warning-light);
    color: #92400e;
}

.payment-badge.payment-paid {
    background: var(--color-success-light);
    color: #166534;
}

.payment-badge.payment-failed {
    background: var(--color-error-light);
    color: #991b1b;
}

.payment-badge.payment-refunded {
    background: var(--color-accent-light);
    color: #6b21a8;
}

.order-body {
    padding: 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 2rem;
}

.order-customer {
    display: flex;
    gap: 1rem;
    flex: 1;
}

.customer-icon {
    width: 48px;
    height: 48px;
    border-radius: 6px;
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    color: var(--text-inverse);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    flex-shrink: 0;
}

.customer-name {
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 0.25rem;
}

.customer-email {
    font-size: 0.875rem;
    color: var(--text-secondary);
    margin-bottom: 0.25rem;
}

.customer-phone {
    font-size: 0.875rem;
    color: var(--text-secondary);
}

/* Clickable filter elements */
.clickable-filter {
    cursor: pointer;
    transition: all 0.2s ease;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    margin: -0.25rem -0.5rem;
}

.clickable-filter:hover {
    background: rgba(245, 158, 11, 0.1);
    color: #d97706;
    transform: translateX(2px);
}

.clickable-filter:active {
    transform: translateX(0);
}

.order-amount {
    text-align: right;
}

.amount-label {
    font-size: 0.875rem;
    color: var(--text-secondary);
    margin-bottom: 0.25rem;
}

.amount-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--text-primary);
}

.order-footer {
    padding: 1.5rem;
    background: var(--bg-secondary);
    border-top: 2px solid #f1f5f9;
    display: flex;
    gap: 1rem;
}

.btn-action {
    flex: 1;
    padding: 0.875rem 1.5rem;
    border-radius: 6px;
    border: none;
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-action.primary {
    background: var(--color-primary-db);
    color: var(--text-inverse);
}

.btn-action.primary:hover {
    background: var(--color-primary-db-hover);
    transform: translateY(-2px);
}

.btn-action.secondary {
    background: var(--bg-card);
    color: var(--text-secondary);
    border: 2px solid var(--border-primary);
}

.btn-action.secondary:hover {
    border-color: var(--color-primary-db);
    color: var(--color-primary-db);
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    color: var(--text-tertiary);
}

.empty-state i {
    margin-bottom: 1.5rem;
}

.empty-state h3 {
    margin: 0 0 0.5rem 0;
    color: var(--text-secondary);
}

.empty-state p {
    margin: 0;
    color: var(--text-tertiary);
}

/* Pagination */
.pagination {
    display: flex;
    justify-content: center;
    gap: 0.5rem;
    margin-top: 2rem;
    padding-top: 2rem;
    border-top: 2px solid #f1f5f9;
}

.page-btn {
    min-width: 40px;
    height: 40px;
    padding: 0 0.75rem;
    border-radius: 6px;
    border: 2px solid var(--border-primary);
    background: var(--bg-card);
    color: var(--text-secondary);
    display: flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
}

.page-btn:hover {
    border-color: var(--color-primary-db);
    color: var(--color-primary-db);
}

.page-btn.active {
    background: var(--color-primary-db);
    border-color: var(--color-primary-db);
    color: var(--text-inverse);
}

/* Modal */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.7);
    backdrop-filter: blur(4px);
    z-index: 10000;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 2rem;
}

.modal-overlay.active {
    display: flex;
}

.modal-container {
    background: var(--bg-card);
    border-radius: 6px;
    width: 100%;
    max-width: 600px;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    animation: modalSlideIn 0.3s ease-out;
}

.modal-container.large {
    max-width: 900px;
}

@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: translateY(-50px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.modal-header {
    padding: 2rem;
    border-bottom: 2px solid #f1f5f9;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h2 {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.modal-close {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    border: none;
    background: var(--bg-tertiary);
    color: var(--text-secondary);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
}

.modal-close:hover {
    background: var(--border-primary);
    color: #f59e0b;
}

.modal-body {
    padding: 2rem;
    overflow-y: auto;
    flex: 1;
}

.modal-footer {
    padding: 1.5rem 2rem;
    border-top: 2px solid #f1f5f9;
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
}

/* Form Elements */
.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: var(--text-primary);
    font-size: 0.9375rem;
}

.form-input {
    width: 100%;
    padding: 0.875rem 1rem;
    border: 2px solid var(--border-primary);
    border-radius: 6px;
    font-size: 0.9375rem;
    background: var(--bg-card);
    color: var(--text-primary);
    transition: all 0.3s ease;
}

.form-input:focus {
    outline: none;
    border-color: var(--color-primary-db);
    box-shadow: 0 0 0 3px var(--color-primary-db-light);
}

.btn-primary, .btn-secondary {
    padding: 0.875rem 2rem;
    border-radius: 6px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 0.75rem;
    cursor: pointer;
    transition: all 0.3s ease;
    border: none;
}

.btn-primary {
    background: var(--color-primary-db);
    color: var(--text-inverse);
}

.btn-primary:hover {
    background: var(--color-primary-db-hover);
}

.btn-secondary {
    background: var(--bg-tertiary);
    color: var(--text-secondary);
}

.btn-secondary:hover {
    background: var(--border-primary);
}

.alert {
    padding: 1rem 1.5rem;
    border-radius: 6px;
    margin-bottom: 1.5rem;
}

.alert-info {
    background: var(--color-info-light);
    color: #1e40af;
}

.order-detail-loading {
    text-align: center;
    padding: 3rem;
    color: var(--text-tertiary);
}

.order-detail-loading i {
    margin-bottom: 1rem;
}

/* Responsive Design */

/* Large Desktop (1400px+) */
@media (min-width: 1400px) {
    .stats-grid {
        grid-template-columns: repeat(4, 1fr);
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
}

/* Tablet (768px - 991px) */
@media (max-width: 991px) and (min-width: 768px) {
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
}

/* Mobile Large (576px - 767px) */
@media (max-width: 767px) and (min-width: 576px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 0.5rem;
    }
    
    .stat-card {
        padding: 1rem;
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
        justify-content: flex-start;
    }
    
    .stat-card-change i {
        font-size: 0.625rem;
    }
}

/* Mobile Small (up to 575px) */
@media (max-width: 575px) {
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
        margin-bottom: 0;
        justify-content: flex-end;
    }
    
    .stat-card-icon {
        width: 32px;
        height: 32px;
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
}

/* Extra Small Mobile (up to 375px) */
@media (max-width: 375px) {
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
}

/* Bulk Actions Styles */
.select-all-checkbox {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-right: 1.5rem;
    cursor: pointer;
    user-select: none;
}

.select-all-checkbox input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
    accent-color: var(--color-primary-db);
}

.checkbox-label {
    font-weight: 600;
    color: var(--text-primary);
    font-size: 0.9375rem;
}

.results-text {
    color: var(--text-secondary);
}

.order-checkbox-wrapper {
    position: absolute;
    top: 1rem;
    left: 1rem;
    z-index: 10;
}

.order-checkbox {
    width: 20px;
    height: 20px;
    cursor: pointer;
    accent-color: var(--color-primary-db);
    transition: transform 0.2s ease;
}

.order-checkbox:hover {
    transform: scale(1.1);
}

.order-card {
    position: relative;
    transition: all 0.3s ease;
}

.order-card.selected {
    border-color: var(--color-primary-db);
    box-shadow: 0 0 0 2px var(--color-primary-db-light);
    background: var(--color-primary-db-light);
}

.bulk-actions-toolbar {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    background: var(--bg-card);
    border-top: 2px solid var(--border-primary);
    box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.1);
    z-index: 1000;
    padding: 1rem 2rem;
    display: flex;
    align-items: center;
    justify-content: center;
    animation: slideUp 0.3s ease;
}

@keyframes slideUp {
    from {
        transform: translateY(100%);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

.bulk-actions-content {
    max-width: 1400px;
    width: 100%;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 2rem;
}

.bulk-actions-info {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-weight: 600;
    color: var(--text-primary);
    font-size: 1rem;
}

.bulk-actions-info i {
    color: var(--color-primary-db);
    font-size: 1.25rem;
}

.bulk-actions-info #selectedCount {
    color: var(--color-primary-db);
    font-size: 1.125rem;
}

.bulk-actions-buttons {
    display: flex;
    gap: 1rem;
}

.btn-bulk-action {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 6px;
    font-weight: 600;
    font-size: 0.9375rem;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
}

.btn-bulk-action.primary {
    background: var(--color-primary-db);
    color: white;
}

.btn-bulk-action.primary:hover:not(:disabled) {
    background: var(--color-primary-db-hover);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.btn-bulk-action.primary:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.btn-bulk-action.secondary {
    background: var(--bg-secondary);
    color: var(--text-primary);
    border: 1px solid var(--border-primary);
}

.btn-bulk-action.secondary:hover {
    background: var(--bg-tertiary);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

/* Adjust content padding when toolbar is visible */
body:has(.bulk-actions-toolbar[style*="flex"]) .content {
    padding-bottom: 80px;
}

/* Additional Mobile Styles */
@media (max-width: 768px) {
    
    .filters-form {
        flex-direction: column;
    }
    
    .search-box {
        min-width: 100%;
    }
    
    .order-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .order-body {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .order-amount {
        text-align: left;
    }
    
    .order-footer {
        flex-direction: column;
    }
    
    .bulk-actions-toolbar {
        padding: 1rem;
    }
    
    .bulk-actions-content {
        flex-direction: column;
        gap: 1rem;
    }
    
    .bulk-actions-buttons {
        width: 100%;
        flex-direction: column;
    }
    
    .btn-bulk-action {
        width: 100%;
        justify-content: center;
    }
    
    .select-all-checkbox {
        margin-right: 1rem;
    }
    
    .results-info {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
}
</style>
