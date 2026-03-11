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

    if ($action === 'delete_order') {
        header('Content-Type: application/json');
        $order_id = isset($_POST['order_id']) ? (int) $_POST['order_id'] : 0;

        if (!$order_id) {
            echo json_encode(['success' => false, 'message' => $t('invalid_data', 'Invalid data')]);
            exit;
        }

        try {
            // Ensure order belongs to this store and check lock state
            $stmt = $conn->prepare("SELECT created_at FROM orders WHERE id = ? AND store_id = ?");
            $stmt->execute([$order_id, $store_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                echo json_encode(['success' => false, 'message' => $t('order_not_found', 'Order not found')]);
                exit;
            }

            $order_locked = BillingHelper::shouldLockOrder($store_id, $row['created_at']);
            if ($order_locked) {
                echo json_encode(['success' => false, 'message' => $t('cannot_delete_locked_order', 'This order cannot be deleted on your current plan.')]);
                exit;
            }

            // Best-effort cleanup of related items before deleting the order itself
            try {
                $cleanupStmt = $conn->prepare("DELETE FROM order_items WHERE order_id = ?");
                $cleanupStmt->execute([$order_id]);
            } catch (Exception $cleanupEx) {
                // Ignore cleanup failures; main delete may still succeed
            }

            $deleteOrderStmt = $conn->prepare("DELETE FROM orders WHERE id = ? AND store_id = ?");
            $deleteOrderStmt->execute([$order_id, $store_id]);

            echo json_encode(['success' => true, 'message' => $t('order_deleted_successfully', 'Order deleted successfully.')]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $t('error_deleting_order', 'Error deleting order')]);
        }
        exit;
    }
}

// Shell modal: Update Order Status only (no orders list)
if (!empty($order_status_modal_only) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $modal_order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
    $modal_status = isset($_GET['status']) ? preg_replace('/[^a-z_]/', '', (string)$_GET['status']) : 'pending';
    $modal_payment = isset($_GET['payment']) ? preg_replace('/[^a-z]/', '', (string)$_GET['payment']) : 'pending';
    $allowed_status = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
    $allowed_payment = ['pending', 'paid', 'failed', 'refunded'];
    if (!in_array($modal_status, $allowed_status)) $modal_status = 'pending';
    if (!in_array($modal_payment, $allowed_payment)) $modal_payment = 'pending';
    ?><style>
.shell-status-modal .modal-container { margin: 2rem auto; max-width: 420px; }
.shell-status-modal .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--text-primary); font-size: 0.9375rem; }
.shell-status-modal .modal-footer { display: flex; align-items: center; justify-content: flex-end; gap: 1rem; padding: 1rem 1.25rem; border-top: 1px solid var(--border-primary); }
.shell-status-modal .modal-body { padding: 1.25rem 1.5rem; }
.shell-status-modal .modal-header { padding: 1rem 1.5rem; border-bottom: 1px solid var(--border-primary); display: flex; justify-content: space-between; align-items: center; }
.shell-status-modal .modal-header h2 { margin: 0; font-size: 1.25rem; font-weight: 600; display: flex; align-items: center; gap: 0.5rem; }
.shell-status-modal .btn-primary,
.shell-status-modal .btn-secondary { text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem; border-radius: 6px; font-weight: 600; cursor: pointer; border: 1px solid transparent; }
.shell-status-modal .btn-primary { background: var(--color-primary-db, var(--color-primary)); color: #fff; }
.shell-status-modal .btn-secondary { background: var(--bg-tertiary); color: var(--text-primary); border-color: var(--border-primary); }
</style>
<div class="modal-overlay active shell-status-modal" onclick="if(event.target===this) closeShellStatusModal()">
    <div class="modal-container" onclick="event.stopPropagation()">
        <div class="modal-header">
            <h2><i class="fas fa-edit"></i> <?php echo $t('update_order_status', 'Update Order Status'); ?></h2>
            <button type="button" class="modal-close" onclick="closeShellStatusModal()"><i class="fas fa-times"></i></button>
        </div>
        <form id="statusFormShell" onsubmit="return updateOrderStatusShell(event)">
            <div class="modal-body">
                <?php echo CsrfHelper::getTokenField(); ?>
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="order_id" id="statusOrderIdShell" value="<?php echo (int)$modal_order_id; ?>">
                <div class="form-group">
                    <label><?php echo $t('order_status'); ?></label>
                    <select name="status" id="statusSelectShell" class="form-input">
                        <option value="pending"<?php echo $modal_status === 'pending' ? ' selected' : ''; ?>><?php echo $t('pending'); ?></option>
                        <option value="processing"<?php echo $modal_status === 'processing' ? ' selected' : ''; ?>><?php echo $t('processing'); ?></option>
                        <option value="shipped"<?php echo $modal_status === 'shipped' ? ' selected' : ''; ?>><?php echo $t('shipped', 'Shipped'); ?></option>
                        <option value="delivered"<?php echo $modal_status === 'delivered' ? ' selected' : ''; ?>><?php echo $t('delivered'); ?></option>
                        <option value="cancelled"<?php echo $modal_status === 'cancelled' ? ' selected' : ''; ?>><?php echo $t('cancelled', 'Cancelled'); ?></option>
                    </select>
                </div>
                <div class="form-group">
                    <label><?php echo $t('payment_status', 'Payment Status'); ?></label>
                    <select name="payment_status" id="paymentStatusSelectShell" class="form-input">
                        <option value="pending"<?php echo $modal_payment === 'pending' ? ' selected' : ''; ?>><?php echo $t('pending'); ?></option>
                        <option value="paid"<?php echo $modal_payment === 'paid' ? ' selected' : ''; ?>><?php echo $t('paid', 'Paid'); ?></option>
                        <option value="failed"<?php echo $modal_payment === 'failed' ? ' selected' : ''; ?>><?php echo $t('failed', 'Failed'); ?></option>
                        <option value="refunded"<?php echo $modal_payment === 'refunded' ? ' selected' : ''; ?>><?php echo $t('refunded', 'Refunded'); ?></option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeShellStatusModal()"><i class="fas fa-times"></i> <?php echo $t('cancel'); ?></button>
                <button type="submit" class="btn-primary"><i class="fas fa-save"></i> <?php echo $t('update_status', 'Update Status'); ?></button>
            </div>
        </form>
    </div>
</div>
<script>
function closeShellStatusModal() { try { window.parent.postMessage({ type: 'close_product_modal', refresh: false }, '*'); } catch (e) {} }
function updateOrderStatusShell(event) {
    event.preventDefault();
    var form = document.getElementById('statusFormShell');
    var body = new URLSearchParams(new FormData(form));
    fetch('index.php?page=orders', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' }, body: body })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data && data.success) {
                try { window.parent.postMessage({ type: 'close_product_modal', refresh: true, message: data.message || '', variant: 'success' }, '*'); } catch (e) {}
            } else {
                alert(data && data.message ? data.message : '<?php echo $t('error'); ?>');
            }
        })
        .catch(function() { alert('<?php echo $t('error'); ?>'); });
    return false;
}
</script><?php
    exit;
}

// Get filters
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$payment_filter = $_GET['payment'] ?? '';
$sort_by = $_GET['sort'] ?? 'created_at';
$sort_order = $_GET['order'] ?? 'DESC';
$page = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$allowed_limits = [10, 20, 50, 100];
$per_page = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
if (!in_array($per_page, $allowed_limits, true)) {
    $per_page = 20;
}
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

// Preload order items for currently visible orders to build compact product previews
$orderItemsByOrder = [];
$productImageMap   = [];
if (!empty($orders)) {
    $orderIds = array_column($orders, 'id');
    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));

    try {
        // Prefer using product_image when available on the schema
        $itemsQuery = "SELECT order_id, product_id, product_name, product_sku, quantity, price, total, product_image
                       FROM order_items
                       WHERE order_id IN ($placeholders)
                       ORDER BY id";
        $itemsStmt = $conn->prepare($itemsQuery);
        $itemsStmt->execute($orderIds);
    } catch (Exception $e) {
        // Graceful fallback for older schemas without product_image
        $itemsQuery = "SELECT order_id, product_id, product_name, product_sku, quantity, price, total
                       FROM order_items
                       WHERE order_id IN ($placeholders)
                       ORDER BY id";
        $itemsStmt = $conn->prepare($itemsQuery);
        $itemsStmt->execute($orderIds);
    }

    $productIdsForImages = [];

    while ($row = $itemsStmt->fetch(PDO::FETCH_ASSOC)) {
        $oid = (int)($row['order_id'] ?? 0);
        if (!$oid) {
            continue;
        }
        if (!isset($orderItemsByOrder[$oid])) {
        $orderItemsByOrder[$oid] = [];
        }
        $orderItemsByOrder[$oid][] = $row;

        $pid = isset($row['product_id']) ? (int)$row['product_id'] : 0;
        if ($pid > 0) {
            $productIdsForImages[$pid] = true;
        }
    }

    // Resolve product images via products/product_images when possible
    $productIds = array_keys($productIdsForImages);
    if (!empty($productIds)) {
        $pPlaceholders = implode(',', array_fill(0, count($productIds), '?'));
        try {
            $imgQuery = "SELECT p.id, COALESCE(pi.image_path, '') AS main_image
                         FROM products p
                         LEFT JOIN product_images pi 
                            ON p.id = pi.product_id 
                           AND pi.is_primary = 1
                         WHERE p.id IN ($pPlaceholders) AND p.store_id = ?";
            $imgStmt = $conn->prepare($imgQuery);
            $imgStmt->execute(array_merge($productIds, [$store_id]));
            while ($imgRow = $imgStmt->fetch(PDO::FETCH_ASSOC)) {
                $pid = (int)$imgRow['id'];
                if ($pid > 0 && !empty($imgRow['main_image'])) {
                    $productImageMap[$pid] = $imgRow['main_image'];
                }
            }
        } catch (Exception $e) {
            // If anything fails, we silently ignore and keep placeholders
        }

        // Fallback: assume default main image path when none was found via product_images
        foreach ($productIds as $pid) {
            $pid = (int)$pid;
            if ($pid <= 0) {
                continue;
            }
            if (!isset($productImageMap[$pid])) {
                $productImageMap[$pid] = "uploads/products/{$pid}/main.jpg";
            }
        }
    }
}

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
    
    <!-- Statistics Cards (Argon-style) -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-card-body">
                <div class="stat-card-row">
                    <div class="stat-card-main">
                        <h5 class="stat-card-label"><?php echo $t('total_orders', 'Total Orders'); ?></h5>
                        <span class="stat-card-value"><?php echo (int)($stats['total'] ?? 0); ?></span>
                        <p class="stat-card-footer positive"><i class="fas fa-arrow-up"></i> <span><?php echo $t('all_orders', 'All Orders'); ?></span></p>
                    </div>
                    <div class="stat-card-icon-wrap">
                        <div class="stat-card-icon primary"><i class="fas fa-shopping-cart"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-card-body">
                <div class="stat-card-row">
                    <div class="stat-card-main">
                        <h5 class="stat-card-label"><?php echo $t('pending_orders', 'Pending Orders'); ?></h5>
                        <span class="stat-card-value"><?php echo (int)($stats['pending'] ?? 0); ?></span>
                        <p class="stat-card-footer <?php echo $stats['pending'] > 0 ? 'negative' : 'positive'; ?>"><i class="fas fa-<?php echo $stats['pending'] > 0 ? 'exclamation' : 'check'; ?>"></i> <span><?php echo $stats['pending'] > 0 ? $t('needs_attention', 'Needs Attention') : $t('all_processed', 'All Processed'); ?></span></p>
                    </div>
                    <div class="stat-card-icon-wrap">
                        <div class="stat-card-icon warning"><i class="fas fa-clock"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-card-body">
                <div class="stat-card-row">
                    <div class="stat-card-main">
                        <h5 class="stat-card-label"><?php echo $t('processing'); ?></h5>
                        <span class="stat-card-value"><?php echo (int)($stats['processing'] ?? 0); ?></span>
                        <p class="stat-card-footer positive"><i class="fas fa-arrow-up"></i> <span><?php echo $t('in_progress', 'In Progress'); ?></span></p>
                    </div>
                    <div class="stat-card-icon-wrap">
                        <div class="stat-card-icon info"><i class="fas fa-cog"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-card-body">
                <div class="stat-card-row">
                    <div class="stat-card-main">
                        <h5 class="stat-card-label"><?php echo $t('delivered'); ?></h5>
                        <span class="stat-card-value"><?php echo (int)($stats['delivered'] ?? 0); ?></span>
                        <p class="stat-card-footer positive"><i class="fas fa-check"></i> <span><?php echo $stats['total'] > 0 ? round(($stats['delivered'] / $stats['total']) * 100, 1) : 0; ?>% <?php echo $t('complete', 'Complete'); ?></span></p>
                    </div>
                    <div class="stat-card-icon-wrap">
                        <div class="stat-card-icon success"><i class="fas fa-check-circle"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Orders Container -->
    <div class="orders-container">
        <div class="orders-indexbar">
            <div class="orders-indexbar-left">
                <div class="orders-search">
                    <i class="fas fa-search" aria-hidden="true"></i>
                    <input type="text"
                           id="ordersSearchInput"
                           placeholder="<?php echo $t('search_orders_placeholder', 'Search orders...'); ?>"
                           autocomplete="off">
                    <button type="button"
                            class="orders-search-clear"
                            id="ordersSearchClear"
                            onclick="clearOrdersSearch()"
                            aria-label="<?php echo $t('clear_search', 'Clear search'); ?>"
                            style="display:none;">
                        <i class="fas fa-times" aria-hidden="true"></i>
                    </button>
                </div>
            </div>
            <div class="orders-indexbar-right">
                <button type="button" class="indexbar-icon-btn" aria-label="<?php echo $t('filters', 'Filters'); ?>" title="<?php echo $t('filters', 'Filters'); ?>">
                    <i class="fas fa-filter" aria-hidden="true"></i>
                </button>
                <button type="button" class="indexbar-icon-btn" aria-label="<?php echo $t('sort', 'Sort'); ?>" title="<?php echo $t('sort', 'Sort'); ?>">
                    <i class="fas fa-sort" aria-hidden="true"></i>
                </button>
            </div>
        </div>
        <div class="orders-header">
            <div class="results-info">
                <label class="select-all-checkbox select-all-mobile">
                    <input type="checkbox" id="selectAllOrdersMobile" class="select-all-orders-checkbox" onchange="toggleSelectAll(this)" aria-label="<?php echo $t('select_all_orders', 'Select all orders'); ?>">
                    <span class="checkbox-label"><?php echo $t('select_all'); ?></span>
                </label>
                <span class="results-text">
                    <?php echo $t('showing'); ?> <span id="visibleCount"><?php echo count($orders); ?></span> <?php echo $t('of'); ?> <span id="totalCount"><?php echo $total_orders; ?></span> <?php echo $t('orders', 'orders'); ?>
                </span>
            </div>
        </div>

        <div class="orders-table-wrapper">
            <div class="orders-loading-panel" id="ordersLoadingPanel" role="status" aria-live="polite">
                <span class="orders-spinner" aria-hidden="true"></span>
                <span class="orders-loading-text"><?php echo $t('loading_orders', 'Loading orders…'); ?></span>
            </div>
            <table class="orders-table">
                <thead>
                    <tr>
                        <th class="orders-table-checkbox-head">
                            <input type="checkbox"
                                   id="selectAllOrdersTable"
                                   class="select-all-orders-checkbox"
                                   onchange="toggleSelectAll(this)"
                                   aria-label="<?php echo $t('select_all_orders', 'Select all orders'); ?>">
                        </th>
                        <th class="orders-table-heading-order">
                            <span class="table-heading-label"><?php echo $t('id', 'ID'); ?></span>
                            <span class="table-heading-selected"><span id="tableSelectedCount">0</span> <?php echo $t('selected', 'selected'); ?></span>
                        </th>
                        <th><span class="table-heading-label"><?php echo $t('products', 'Products'); ?></span></th>
                        <th><span class="table-heading-label"><?php echo $t('customer', 'Customer'); ?></span></th>
                        <th><span class="table-heading-label"><?php echo $t('date', 'Date'); ?></span></th>
                        <?php if ($hasPaymentStatus): ?>
                        <th><span class="table-heading-label"><?php echo $t('payment', 'Payment'); ?></span></th>
                        <?php endif; ?>
                        <th><span class="table-heading-label"><?php echo $t('status', 'Status'); ?></span></th>
                        <?php if ($hasCourierTracking && ($pluginHelper->isPluginActive('firstdelivery') || $pluginHelper->isPluginActive('colissimo'))): ?>
                        <th><span class="table-heading-label"><?php echo $t('shipping', 'Shipping'); ?></span></th>
                        <?php endif; ?>
                        <th><span class="table-heading-label"><?php echo $t('total_amount', 'Total'); ?></span></th>
                        <th><span class="table-heading-label"><?php echo $t('actions', 'Actions'); ?></span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $rowIndex = $offset + 1;
                    foreach ($orders as $order):
                        $order_locked = BillingHelper::shouldLockOrder($store_id, $order['created_at']);
                        $LOCKED = '*** LOCKED ***';
                    ?>
                    <tr class="order-row" data-order-id="<?php echo $order['id']; ?>">
                        <td>
                            <?php if (!$order_locked): ?>
                            <input type="checkbox" 
                                   class="order-checkbox" 
                                   id="order-checkbox-table-<?php echo $order['id']; ?>" 
                                   data-order-id="<?php echo $order['id']; ?>"
                                   onchange="toggleOrderSelection(this)"
                                   aria-label="Select order <?php echo htmlspecialchars($order['order_number']); ?>">
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="table-order-id"><?php echo $rowIndex++; ?></div>
                        </td>
                        <td>
                            <?php
                                $items = $orderItemsByOrder[$order['id']] ?? [];
                                $visibleItems = array_slice($items, 0, 4);
                                $extraCount = max(0, count($items) - count($visibleItems));
                            ?>
                            <div class="table-products-cell">
                                <div class="products-avatars">
                                    <?php foreach ($visibleItems as $item): 
                                        $name = $item['product_name'] ?? '';
                                        $qty  = (int)($item['quantity'] ?? 0);
                                        $img  = $item['product_image'] ?? null;
                                        $pidForThumb = isset($item['product_id']) ? (int)$item['product_id'] : 0;
                                        if ((empty($img) || $img === '0') && $pidForThumb > 0 && isset($productImageMap[$pidForThumb])) {
                                            $img = $productImageMap[$pidForThumb];
                                        }
                                        // Normalize image path for admin (prefix ../ for site-relative paths)
                                        $imgSrc = null;
                                        if (!empty($img) && $img !== '0') {
                                            if (preg_match('~^https?://~i', $img)) {
                                                $imgSrc = $img;
                                            } else {
                                                $imgSrc = '../' . ltrim($img, '/');
                                            }
                                        }
                                        $initial = $name !== '' ? strtoupper(substr($name, 0, 1)) : '?';
                                    ?>
                                    <div 
                                        class="product-avatar" 
                                        title="<?php echo htmlspecialchars($name); ?>"
                                        data-order-id="<?php echo (int)$order['id']; ?>"
                                        data-product-id="<?php echo $pidForThumb; ?>"
                                        data-raw-img="<?php echo htmlspecialchars($item['product_image'] ?? ''); ?>"
                                        data-map-img="<?php echo htmlspecialchars($pidForThumb > 0 && isset($productImageMap[$pidForThumb]) ? $productImageMap[$pidForThumb] : ''); ?>"
                                        data-final-img="<?php echo htmlspecialchars($imgSrc ?? ''); ?>"
                                    >
                                        <div class="product-avatar-inner">
                                            <?php if (!empty($imgSrc)): ?>
                                                <img 
                                                    src="<?php echo htmlspecialchars($imgSrc); ?>" 
                                                    alt="<?php echo htmlspecialchars($name); ?>"
                                                    onerror="console.log('orders thumb img error', this.src);"
                                                >
                                            <?php else: ?>
                                                <span><?php echo htmlspecialchars($initial); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <span class="product-qty">x<?php echo $qty; ?></span>
                                    </div>
                                    <?php endforeach; ?>
                                    <?php if ($extraCount > 0): ?>
                                        <div class="product-avatar more" title="<?php echo $extraCount; ?> <?php echo $t('more_items', 'more items'); ?>">
                                            <span>+<?php echo $extraCount; ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($items)): ?>
                                <div class="products-tooltip">
                                    <?php foreach ($items as $item): 
                                        $pName = $item['product_name'] ?? '';
                                        $pSku  = $item['product_sku'] ?? '';
                                        $pQty  = (int)($item['quantity'] ?? 0);
                                        $pPrice = isset($item['price']) ? (float)$item['price'] : null;
                                        $pTotal = isset($item['total']) ? (float)$item['total'] : null;
                                        $pImg  = $item['product_image'] ?? null;
                                        $pId   = isset($item['product_id']) ? (int)$item['product_id'] : 0;
                                        if ((empty($pImg) || $pImg === '0') && $pId > 0 && isset($productImageMap[$pId])) {
                                            $pImg = $productImageMap[$pId];
                                        }
                                        $pImgSrc = null;
                                        if (!empty($pImg) && $pImg !== '0') {
                                            if (preg_match('~^https?://~i', $pImg)) {
                                                $pImgSrc = $pImg;
                                            } else {
                                                $pImgSrc = '../' . ltrim($pImg, '/');
                                            }
                                        }
                                    ?>
                                    <a 
                                        class="product-tooltip-card" 
                                        <?php if ($pId): ?>
                                            href="?page=product_quick_view&id=<?php echo $pId; ?>"
                                        <?php else: ?>
                                            href="javascript:void(0)"
                                        <?php endif; ?>
                                        data-order-id="<?php echo (int)$order['id']; ?>"
                                        data-product-id="<?php echo $pId; ?>"
                                        data-raw-img="<?php echo htmlspecialchars($item['product_image'] ?? ''); ?>"
                                        data-map-img="<?php echo htmlspecialchars($pId > 0 && isset($productImageMap[$pId]) ? $productImageMap[$pId] : ''); ?>"
                                        data-final-img="<?php echo htmlspecialchars($pImgSrc ?? ''); ?>"
                                        >
                                        <div class="product-tooltip-media">
                                            <?php if (!empty($pImgSrc)): ?>
                                                <img 
                                                    src="<?php echo htmlspecialchars($pImgSrc); ?>" 
                                                    alt="<?php echo htmlspecialchars($pName); ?>"
                                                    onerror="console.log('orders tooltip img error', this.src);"
                                                >
                                            <?php else: ?>
                                                <div class="product-tooltip-placeholder">
                                                    <?php echo htmlspecialchars(mb_substr($pName !== '' ? $pName : '?', 0, 1)); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="product-tooltip-body">
                                            <div class="product-tooltip-header">
                                                <div class="product-tooltip-title"><?php echo htmlspecialchars($pName); ?></div>
                                                <div class="product-tooltip-qty">
                                                    <?php echo $t('quantity', 'Quantity'); ?>: <?php echo $pQty; ?>
                                                </div>
                                            </div>
                                            <?php if ($pPrice !== null): ?>
                                            <div class="product-tooltip-meta">
                                                <span class="product-tooltip-price">
                                                    <?php echo $currency_position === 'left'
                                                        ? $currency_symbol . number_format($pPrice, 2)
                                                        : number_format($pPrice, 2) . ' ' . $currency_symbol; ?>
                                                </span>
                                            </div>
                                            <?php endif; ?>
                                            <?php if ($pTotal !== null): ?>
                                            <div class="product-tooltip-footer">
                                                <span class="label"><?php echo $t('total', 'Total'); ?></span>
                                                <span class="value">
                                                    <?php echo $currency_position === 'left'
                                                        ? $currency_symbol . number_format($pTotal, 2)
                                                        : number_format($pTotal, 2) . ' ' . $currency_symbol; ?>
                                                </span>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </a>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <div class="table-customer-cell">
                                <div class="table-customer-name">
                                    <?php echo $order_locked ? $LOCKED : htmlspecialchars($order['customer_name']); ?>
                                </div>
                                <?php if (!$order_locked): ?>
                                <div class="customer-hover-card" data-customer-name="<?php echo htmlspecialchars($order['customer_name']); ?>">
                                    <div class="customer-hover-row">
                                        <?php echo htmlspecialchars($order['customer_email']); ?>
                                    </div>
                                    <?php if (!empty($order['customer_phone'])): ?>
                                    <div class="customer-hover-row">
                                        <?php echo htmlspecialchars($order['customer_phone']); ?>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($order['shipping_address'])): ?>
                                    <div class="customer-hover-row">
                                        <?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?>
                                    </div>
                                    <?php elseif (!empty($order['billing_address'])): ?>
                                    <div class="customer-hover-row">
                                        <?php echo nl2br(htmlspecialchars($order['billing_address'])); ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <div class="table-order-date">
                                <?php echo date('M j, Y g:i A', strtotime($order['created_at'])); ?>
                            </div>
                        </td>
                        <?php if ($hasPaymentStatus): ?>
                        <td>
                            <?php 
                                $payment_translations = [
                                    'pending' => $t('pending'),
                                    'paid' => $t('paid', 'Paid'),
                                    'failed' => $t('failed', 'Failed'),
                                    'refunded' => $t('refunded', 'Refunded')
                                ];
                                $payment_label = $order['payment_status'] ?? 'pending';
                            ?>
                            <div class="status-history-cell">
                                <span class="payment-badge payment-<?php echo htmlspecialchars($payment_label); ?>">
                                    <?php echo $payment_translations[$payment_label] ?? ucfirst($payment_label); ?>
                                </span>
                                <div class="status-history-tooltip">
                                    <div class="history-title"><?php echo $t('payment', 'Payment'); ?></div>
                                    <div class="history-row">
                                        <span class="history-label"><?php echo $t('current_status', 'Current status'); ?></span>
                                        <span class="history-value">
                                            <?php echo $payment_translations[$payment_label] ?? ucfirst($payment_label); ?>
                                        </span>
                                    </div>
                                    <div class="history-row">
                                        <span class="history-label"><?php echo $t('last_updated', 'Last updated'); ?></span>
                                        <span class="history-value">
                                            <?php 
                                                $updated = !empty($order['updated_at']) ? $order['updated_at'] : $order['created_at'];
                                                echo date('M j, Y g:i A', strtotime($updated));
                                            ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <?php endif; ?>
                        <td>
                            <?php 
                                $status_translations = [
                                    'pending' => $t('pending'),
                                    'processing' => $t('processing'),
                                    'shipped' => $t('shipped', 'Shipped'),
                                    'delivered' => $t('delivered'),
                                    'cancelled' => $t('cancelled', 'Cancelled')
                                ];
                                $status_label = $order['status'];
                            ?>
                            <div class="status-history-cell">
                                <span class="status-badge status-<?php echo $status_label; ?>">
                                    <?php echo $status_translations[$status_label] ?? ucfirst($status_label); ?>
                                </span>
                                <div class="status-history-tooltip">
                                    <div class="history-title"><?php echo $t('status', 'Status'); ?></div>
                                    <div class="history-row">
                                        <span class="history-label"><?php echo $t('current_status', 'Current status'); ?></span>
                                        <span class="history-value">
                                            <?php echo $status_translations[$status_label] ?? ucfirst($status_label); ?>
                                        </span>
                                    </div>
                                    <div class="history-row">
                                        <span class="history-label"><?php echo $t('created_at', 'Created at'); ?></span>
                                        <span class="history-value">
                                            <?php echo date('M j, Y g:i A', strtotime($order['created_at'])); ?>
                                        </span>
                                    </div>
                                    <?php if (!empty($order['updated_at']) && $order['updated_at'] !== $order['created_at']): ?>
                                    <div class="history-row">
                                        <span class="history-label"><?php echo $t('last_updated', 'Last updated'); ?></span>
                                        <span class="history-value">
                                            <?php echo date('M j, Y g:i A', strtotime($order['updated_at'])); ?>
                                        </span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <?php if ($hasCourierTracking && ($pluginHelper->isPluginActive('firstdelivery') || $pluginHelper->isPluginActive('colissimo'))): ?>
                        <td>
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
                        </td>
                        <?php endif; ?>
                        <td class="amount-cell total-column">
                            <?php echo $order_locked ? $LOCKED : ($currency_position === 'left' ? $currency_symbol . number_format($order['total_amount'], 2) : number_format($order['total_amount'], 2) . ' ' . $currency_symbol); ?>
                        </td>
                        <td>
                            <div class="table-actions icons-only">
                                <button class="icon-action-btn" onclick="viewOrder(<?php echo $order['id']; ?>)" title="<?php echo $t('view_details', 'View Details'); ?>" aria-label="<?php echo $t('view_details', 'View Details'); ?>">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="icon-action-btn" onclick="editOrderStatus(<?php echo $order['id']; ?>, '<?php echo $order['status']; ?>', '<?php echo $hasPaymentStatus ? $order['payment_status'] : 'pending'; ?>')" title="<?php echo $t('update_status', 'Update Status'); ?>" aria-label="<?php echo $t('update_status', 'Update Status'); ?>">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="icon-action-btn danger" onclick="deleteOrder(<?php echo $order['id']; ?>)" title="<?php echo $t('delete_order', 'Delete Order'); ?>" aria-label="<?php echo $t('delete_order', 'Delete Order'); ?>">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
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
                               id="order-checkbox-card-<?php echo $order['id']; ?>" 
                               data-order-id="<?php echo $order['id']; ?>"
                               onchange="toggleOrderSelection(this)"
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
                                <div class="customer-name">
                                    <?php echo $order_locked ? $LOCKED : htmlspecialchars($order['customer_name']); ?>
                                </div>
                                <div class="customer-email">
                                    <?php echo $order_locked ? $LOCKED : htmlspecialchars($order['customer_email']); ?>
                                </div>
                                <?php if (!empty($order['customer_phone']) || $order_locked): ?>
                                    <div class="customer-phone">
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
        
            <!-- Pagination -->
            <div class="pagination">
                <div class="pagination-rows">
                    <span class="rows-label"><?php echo $t('rows_per_page', 'Rows per page'); ?>:</span>
                    <select id="ordersPerPage" class="rows-select" onchange="changeOrdersPerPage(this.value)">
                        <?php foreach ($allowed_limits as $limitOption): ?>
                            <option value="<?php echo $limitOption; ?>" <?php echo $limitOption === $per_page ? 'selected' : ''; ?>>
                                <?php echo $limitOption; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="pagination-pages">
                    <?php if ($total_pages > 1): ?>
                    <?php if ($page > 1): ?>
                        <a href="?page=orders&p=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&payment=<?php echo $payment_filter; ?>&limit=<?php echo $per_page; ?>" 
                           class="page-btn" aria-label="<?php echo $t('previous_page', 'Previous page'); ?>">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <a href="?page=orders&p=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&payment=<?php echo $payment_filter; ?>&limit=<?php echo $per_page; ?>" 
                           class="page-btn <?php echo $i == $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=orders&p=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&payment=<?php echo $payment_filter; ?>&limit=<?php echo $per_page; ?>" 
                           class="page-btn" aria-label="<?php echo $t('next_page', 'Next page'); ?>">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
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

function deleteOrder(orderId) {
    if (!orderId) return;
    if (!confirm('<?php echo $t('confirm_delete_order', 'Are you sure you want to delete this order? This action cannot be undone.'); ?>')) {
        return;
    }

    postOrdersAction({action: 'delete_order', order_id: orderId})
        .then(function (res) {
            if (!res || !res.success) {
                alert(res && res.message ? res.message : '<?php echo $t('error_deleting_order', 'Error deleting order'); ?>');
                return;
            }
            // Show a lightweight confirmation via the shell toast system when possible
            try {
                window.parent.postMessage({ type: 'toast', message: res.message || '<?php echo $t('order_deleted_successfully', 'Order deleted successfully.'); ?>', variant: 'success' }, '*');
            } catch (e) {}
            location.reload();
        })
        .catch(function () { 
            alert('<?php echo $t('error_deleting_order', 'Error deleting order'); ?>'); 
        });
}

// Search + selection UI
document.addEventListener('DOMContentLoaded', function() {
    // Hide initial loading panel shortly after DOM is ready
    const loadingPanel = document.getElementById('ordersLoadingPanel');
    if (loadingPanel) {
        setTimeout(() => {
            loadingPanel.style.display = 'none';
        }, 150);
    }

    // Check if we need to open status modal from order details page
    const editOrderStatusId = sessionStorage.getItem('editOrderStatusId');
    if (editOrderStatusId) {
        const currentStatus = sessionStorage.getItem('editOrderStatus') || 'pending';
        const currentPayment = sessionStorage.getItem('editOrderPayment') || 'pending';
        sessionStorage.removeItem('editOrderStatusId');
        sessionStorage.removeItem('editOrderStatus');
        sessionStorage.removeItem('editOrderPayment');
        setTimeout(() => {
            editOrderStatus(parseInt(editOrderStatusId), currentStatus, currentPayment);
        }, 100);
    }

    const searchInput = document.getElementById('ordersSearchInput');
    if (searchInput) {
        searchInput.addEventListener('input', debounce(function(e) {
            const searchTerm = (e.target.value || '').toLowerCase();
            const elements = getOrderElementsForCurrentView();
            let visibleCount = 0;

            elements.forEach(el => {
                const text = (el.textContent || '').toLowerCase();
                const isVisible = text.includes(searchTerm);
                el.style.display = isVisible ? '' : 'none';
                if (isVisible) visibleCount++;
            });

            syncVisibilityBetweenViews();
            updateResultsCount(visibleCount);
            toggleSearchClearButton(searchTerm.length > 0);
            updateSelectAllCheckbox();
        }, 250));
    }

    // Initial counters and select-all state
    updateResultsCount();
    updateSelectAllCheckbox();
    updateBulkActionsBar();

    // Debug product images in orders table
    try {
        document.querySelectorAll('.product-avatar').forEach(function(el) {
            console.log('orders avatar debug', {
                orderId: el.dataset.orderId,
                productId: el.dataset.productId,
                rawImg: el.dataset.rawImg,
                mapImg: el.dataset.mapImg,
                finalImg: el.dataset.finalImg
            });
        });
        document.querySelectorAll('.product-tooltip-card').forEach(function(el) {
            console.log('orders tooltip debug', {
                orderId: el.dataset.orderId,
                productId: el.dataset.productId,
                rawImg: el.dataset.rawImg,
                mapImg: el.dataset.mapImg,
                finalImg: el.dataset.finalImg
            });
        });

        // Click on customer hover card -> filter by customer name
        document.querySelectorAll('.customer-hover-card').forEach(function(card) {
            card.addEventListener('click', function (e) {
                e.stopPropagation();
                const name = (this.dataset.customerName || '').trim();
                if (!name) return;
                const searchInput = document.getElementById('ordersSearchInput');
                if (!searchInput) return;
                searchInput.value = name;
                searchInput.dispatchEvent(new Event('input', { bubbles: true }));
            });
        });
    } catch (e) {
        console.warn('orders image debug failed', e);
    }
});

function changeOrdersPerPage(limit) {
    limit = parseInt(limit || '0', 10);
    if (!limit) return;
    try {
        const params = new URLSearchParams(window.location.search);
        params.set('p', '1');
        params.set('limit', String(limit));
        if (!params.get('page')) {
            params.set('page', 'orders');
        }
        const base = (window.location.pathname || 'index.php').replace(/\/$/, '') || 'index.php';
        window.location.href = base + '?' + params.toString();
    } catch (e) {
        window.location.search = '?page=orders&p=1&limit=' + encodeURIComponent(String(limit));
    }
}

function isDesktopOrdersTable() {
    return !!(window.matchMedia && window.matchMedia('(min-width: 1024px)').matches);
}

function getOrderElementsForCurrentView() {
    return Array.from(document.querySelectorAll(isDesktopOrdersTable() ? '.order-row' : '.order-card'));
}

function syncVisibilityBetweenViews() {
    // Mirror hidden/shown state between table rows and cards by order id
    const visibleMap = new Map();
    document.querySelectorAll('.order-row').forEach(row => {
        const id = row.dataset.orderId;
        if (id) visibleMap.set(id, row.style.display !== 'none');
    });
    document.querySelectorAll('.order-card').forEach(card => {
        const id = card.dataset.orderId;
        if (!id) return;
        if (visibleMap.has(id)) {
            card.style.display = visibleMap.get(id) ? '' : 'none';
        }
    });
    // If card view is driving, mirror to table
    document.querySelectorAll('.order-card').forEach(card => {
        const id = card.dataset.orderId;
        if (!id) return;
        const row = document.querySelector(`.order-row[data-order-id="${id}"]`);
        if (row) row.style.display = (card.style.display !== 'none') ? '' : 'none';
    });
}

function updateResultsCount(visibleCount = null) {
    const elements = getOrderElementsForCurrentView();
    const totalCount = elements.length;
    const visible = visibleCount !== null ? visibleCount : elements.filter(el => el.style.display !== 'none').length;

    const totalElement = document.getElementById('totalCount');
    const visibleElement = document.getElementById('visibleCount');
    if (totalElement) totalElement.textContent = totalCount;
    if (visibleElement) visibleElement.textContent = visible;
}

function toggleSearchClearButton(show) {
    const clearBtn = document.getElementById('ordersSearchClear');
    if (clearBtn) clearBtn.style.display = show ? 'flex' : 'none';
}

function clearOrdersSearch() {
    const searchInput = document.getElementById('ordersSearchInput');
    if (!searchInput) return;
    searchInput.value = '';
    searchInput.dispatchEvent(new Event('input'));
    toggleSearchClearButton(false);
}

// Bulk Actions Functions
let selectedOrders = new Set();

function updateSelectionHeaderUI() {
    const wrapper = document.querySelector('.orders-table-wrapper');
    const count = selectedOrders.size;
    const countEl = document.getElementById('tableSelectedCount');
    if (countEl) countEl.textContent = String(count);
    if (wrapper) wrapper.classList.toggle('selection-active', count > 0);
}

function setOrderSelectedUI(orderId, isSelected) {
    document.querySelectorAll(`.order-checkbox[data-order-id="${orderId}"]`).forEach(cb => {
        cb.checked = isSelected;
    });
    const row = document.querySelector(`.order-row[data-order-id="${orderId}"]`);
    const card = document.querySelector(`.order-card[data-order-id="${orderId}"]`);
    if (row) row.classList.toggle('selected', isSelected);
    if (card) card.classList.toggle('selected', isSelected);
}

function toggleOrderSelection(checkboxEl) {
    if (!checkboxEl || !checkboxEl.dataset) return;
    const orderId = parseInt(checkboxEl.dataset.orderId);
    if (!orderId) return;
    if (checkboxEl.checked) selectedOrders.add(orderId);
    else selectedOrders.delete(orderId);
    setOrderSelectedUI(orderId, checkboxEl.checked);
    updateBulkActionsBar();
    updateSelectAllCheckbox();
}

function toggleSelectAll(sourceCheckbox) {
    const isChecked = !!(sourceCheckbox && sourceCheckbox.checked);
    const elements = getOrderElementsForCurrentView();

    elements.forEach(el => {
        if (el.style.display === 'none') return;
        const orderId = parseInt(el.dataset.orderId);
        if (!orderId) return;
        // Skip locked orders (no checkbox rendered)
        const anyCheckbox = document.querySelector(`.order-checkbox[data-order-id="${orderId}"]`);
        if (!anyCheckbox) return;

        if (isChecked) selectedOrders.add(orderId);
        else selectedOrders.delete(orderId);
        setOrderSelectedUI(orderId, isChecked);
    });

    updateBulkActionsBar();
    updateSelectAllCheckbox();
}

function updateSelectAllCheckbox() {
    const elements = getOrderElementsForCurrentView().filter(el => el.style.display !== 'none');
    const visibleIds = elements
        .map(el => parseInt(el.dataset.orderId))
        .filter(Boolean)
        .filter(id => document.querySelector(`.order-checkbox[data-order-id="${id}"]`));

    const selectedVisible = visibleIds.filter(id => selectedOrders.has(id));

    document.querySelectorAll('.select-all-orders-checkbox').forEach(selectAllCheckbox => {
        if (visibleIds.length === 0) {
            selectAllCheckbox.indeterminate = false;
            selectAllCheckbox.checked = false;
            return;
        }
        if (selectedVisible.length === visibleIds.length) {
            selectAllCheckbox.indeterminate = false;
            selectAllCheckbox.checked = true;
        } else if (selectedVisible.length > 0) {
            selectAllCheckbox.indeterminate = true;
            selectAllCheckbox.checked = false;
        } else {
            selectAllCheckbox.indeterminate = false;
            selectAllCheckbox.checked = false;
        }
    });
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

    updateSelectionHeaderUI();
}

function clearSelection() {
    selectedOrders.clear();
    document.querySelectorAll('.order-checkbox').forEach(checkbox => {
        checkbox.checked = false;
    });
    document.querySelectorAll('.order-row.selected, .order-card.selected').forEach(el => el.classList.remove('selected'));
    document.querySelectorAll('.select-all-orders-checkbox').forEach(cb => {
        cb.checked = false;
        cb.indeterminate = false;
    });
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
        const printWindow = window.open(`?content=1&page=order_bulk_print&ids=${orderIdsParam}`, '_blank', 'width=1200,height=800');
        
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
        const searchInput = document.getElementById('ordersSearchInput');
        if (searchInput) searchInput.focus();
    }
    
    // Clear search with Escape
    if (e.key === 'Escape') {
        clearOrdersSearch();
    }
    
    // Refresh with R
    if (e.key === 'r' && e.ctrlKey) {
        e.preventDefault();
        window.location.reload();
    }
});

function viewOrder(orderId) {
    // Navigate to order details page; keep content=1 when in iframe so details load in frame
    var isFrame = window.self !== window.top;
    var base = (window.location.pathname || 'index.php').replace(/\/$/, '') || 'index.php';
    var params = new URLSearchParams(window.location.search);
    params.set('page', 'order_view_details');
    params.set('id', String(orderId));
    if (isFrame) params.set('content', '1');
    window.location.href = base + '?' + params.toString();
}

function closeOrderModal() {
    document.getElementById('orderDetailsModal').classList.remove('active');
    document.body.style.overflow = '';
}

function editOrderStatus(orderId, currentStatus, currentPayment) {
    if (window.self !== window.top && window.location.search.indexOf('modal=status') === -1) {
        try { window.parent.postMessage({ type: 'open_order_status_modal', orderId: orderId, status: currentStatus || 'pending', payment: currentPayment || 'pending' }, '*'); } catch (e) {}
        return;
    }
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
    padding: 1.25rem;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.03);
    border: 1px solid var(--border-primary);
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

/* IndexBar (Shopify-like) */
.orders-indexbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    background: var(--bg-card);

    margin-bottom: 1rem;
}

.orders-search {
    position: relative;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    width: min(560px, 100%);
}

.orders-search i {
    position: absolute;
    left: 0.75rem;
    color: var(--text-tertiary);
}

#ordersSearchInput {
    width: 100%;
    height: 40px;
    padding: 0.5rem 2.5rem 0.5rem 2.25rem;
    border: 1px solid var(--border-primary);
    border-radius: 6px;
    background: var(--bg-card);
    color: var(--text-primary);
    outline: none;
}

#ordersSearchInput:focus {
    border-color: var(--color-primary-db);
    box-shadow: 0 0 0 3px var(--color-primary-db-light);
}

.orders-search-clear {
    position: absolute;
    right: 0.5rem;
    height: 30px;
    width: 30px;
    border: none;
    border-radius: 6px;
    background: transparent;
    color: var(--text-secondary);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
}

.orders-search-clear:hover {
    background: rgba(148, 163, 184, 0.15);
}

.orders-indexbar-right {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.indexbar-icon-btn {
    height: 40px;
    width: 40px;
    border-radius: 6px;
    border: 1px solid var(--border-primary);
    background: var(--bg-card);
    color: var(--text-secondary);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
}

.indexbar-icon-btn:hover {
    border-color: var(--color-primary-db);
    color: var(--color-primary-db);
}

/* Desktop Orders Table */
.orders-table-wrapper {
    margin-top: 1rem;
    border-radius: 6px;
    overflow: visible;
    border: 1px solid var(--border-primary);
    background: var(--bg-card);
    display: none;
}

.orders-loading-panel {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.875rem 1rem;
    border-bottom: 1px solid var(--border-primary);
    background: var(--bg-secondary);
}

.orders-spinner {
    width: 16px;
    height: 16px;
    border-radius: 999px;
    border: 2px solid rgba(148, 163, 184, 0.35);
    border-top-color: var(--color-primary-db);
    animation: ordersSpin 0.75s linear infinite;
}

@keyframes ordersSpin {
    to { transform: rotate(360deg); }
}

.orders-loading-text {
    font-size: 0.875rem;
    color: var(--text-secondary);
}

.orders-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
}

.orders-table th,
.orders-table td {
    padding: 0.75rem 1rem;
    border-bottom: 1px solid var(--border-primary);
    text-align: left;
    vertical-align: middle;
}

.orders-table thead th {
    background: var(--bg-secondary);
    font-weight: 600;
    color: var(--text-secondary);
}

.orders-table-checkbox-head {
    width: 44px;
}

.orders-table-heading-order {
    position: relative;
}

.orders-table-checkbox-head input[type="checkbox"] {
    width: 18px;
    height: 18px;
    -webkit-appearance: none;
    appearance: none;
    border-radius: 4px;
    border: 2px solid var(--border-primary);
    background-color: transparent;
    cursor: pointer;
    display: inline-block;
    position: relative;
}

.orders-table-checkbox-head input[type="checkbox"]:checked {
    border-color: var(--color-primary-db);
    background-color: var(--color-primary-db);
}

.orders-table-checkbox-head input[type="checkbox"]:checked::after {
    content: '';
    position: absolute;
    inset: 3px;
    border-bottom: 2px solid #ffffff;
    border-right: 2px solid #ffffff;
    transform: rotate(45deg);
}

.table-heading-selected {
    display: none;
    align-items: center;
    gap: 0.25rem;
    position: absolute;
    left: 0;
    top: 50%;
    transform: translateY(-50%);
    white-space: nowrap;
    font-weight: 600;
    color: var(--text-primary);
}

.orders-table-wrapper.selection-active .table-heading-label {
    opacity: 0;
}

.orders-table-wrapper.selection-active .orders-table-heading-order .table-heading-selected {
    display: inline-flex;
}

.orders-table tbody tr:hover td {
    background: rgba(148, 163, 184, 0.08);
}

.order-row.selected td {
    background: var(--color-primary-db-light);
}

.table-order-number {
    font-weight: 600;
    color: var(--text-primary);
}

.table-order-id {
    font-weight: 500;
    color: var(--text-secondary);
    font-variant-numeric: tabular-nums;
}

.table-order-date,
.table-customer-email,
.table-customer-phone {
    font-size: 0.8125rem;
    color: var(--text-secondary);
}

.table-customer-name {
    font-weight: 500;
    color: var(--text-primary);
    margin-bottom: 0.125rem;
}

.table-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.orders-table td.amount-cell {
    text-align: right;
    font-variant-numeric: tabular-nums;
}

.orders-table td.total-column,
.orders-table th .table-heading-label {
    white-space: nowrap;
}

.orders-table td.total-column {
    min-width: 140px;
}

.table-products-cell {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    position: relative;
}

.products-avatars {
    display: inline-flex;
    align-items: center;
    gap: 0.2rem;
}

.product-avatar {
    position: relative;
    width: 32px;
    height: 32px;
    border-radius: 6px;
    overflow: hidden;
    background: var(--bg-tertiary);
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid var(--border-primary);
}

.product-avatar-inner {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.product-avatar-inner img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.product-avatar-inner span {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--text-primary);
}

.product-avatar.more {
    background: var(--bg-secondary);
    border-style: dashed;
}

.product-avatar.more span {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--text-secondary);
}

.product-qty {
    position: absolute;
    right: 2px;
    bottom: 2px;
    padding: 0 3px;
    border-radius: 4px;
    background: rgba(15, 23, 42, 0.85);
    color: #fff;
    font-size: 0.625rem;
    font-weight: 600;
}

.products-tooltip {
    position: absolute;
    left: 0;
    right: auto;
    bottom: 100%;
    z-index: 20;
    min-width: 260px;
    max-width: 360px;
    padding: 0.75rem 0.85rem;
    border-radius: 8px;
    background: var(--bg-card);
    box-shadow: var(--shadow-lg);
    border: 1px dashed var(--border-primary);
    display: none;
}

.product-tooltip-card {
    display: grid;
    grid-template-columns: 52px minmax(0, 1fr);
    gap: 0.6rem;
    align-items: center;
    padding: 0.45rem 0.55rem;
    border-radius: 6px;
    text-decoration: none;
    color: inherit;
    transition: background 0.15s ease, transform 0.15s ease, box-shadow 0.15s ease;
}

.product-tooltip-card:hover {
    background: var(--bg-secondary);
    box-shadow: var(--shadow-md);
    transform: translateY(-1px);
}

.product-tooltip-media {
    width: 52px;
    height: 52px;
    border-radius: 6px;
    overflow: hidden;
    background: var(--bg-tertiary);
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid var(--border-primary);
}

.product-tooltip-media img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.product-tooltip-placeholder {
    font-size: 0.9rem;
    font-weight: 700;
    color: var(--text-secondary);
}

.product-tooltip-body {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    min-width: 0;
}

.product-tooltip-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 0.5rem;
}

.product-tooltip-title {
    font-size: 0.82rem;
    font-weight: 600;
    color: var(--text-primary);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.product-tooltip-qty {
    font-size: 0.75rem;
    color: var(--text-secondary);
    white-space: nowrap;
}

.product-tooltip-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.75rem;
    font-size: 0.75rem;
    color: var(--text-secondary);
}

.product-tooltip-sku {
    white-space: nowrap;
}

.product-tooltip-price {
    font-weight: 600;
    color: var(--text-primary);
}

.product-tooltip-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.75rem;
    margin-top: 0.15rem;
}

.product-tooltip-footer .label {
    color: var(--text-secondary);
}

.product-tooltip-footer .value {
    font-weight: 600;
    color: var(--color-primary-db);
}

.table-products-cell:hover .products-tooltip,
.products-tooltip:hover {
    display: block;
}

.table-customer-cell {
    position: relative;
    display: inline-flex;
    align-items: center;
    /* Increase the hoverable area so moving up into the card doesn't leave a gap */
    padding-top: 2px;
    padding-bottom: 2px;
}

.customer-hover-card {
    position: absolute;
    left: 0;
    right: auto;
    /* Sit directly above the cell to avoid any dead zone between name and card */
    bottom: calc(100% - 4px);
    min-width: 220px;
    padding: 0.75rem 0.85rem;
    border-radius: 6px;
    background: var(--bg-card);
    box-shadow: var(--shadow-lg);
    border: 1px solid var(--border-primary);
    display: none;
    z-index: 20;
    max-height: 220px;
    overflow-y: auto;
    cursor: pointer;
}

.customer-hover-row {
    font-size: 0.8125rem;
    color: var(--text-secondary);
}

.table-customer-cell:hover .customer-hover-card {
    display: block;
}

.customer-hover-card:hover {
    display: block;
}

.status-history-cell {
    position: relative;
    display: inline-flex;
    align-items: center;
}

.status-history-tooltip {
    position: absolute;
    left: auto;
    right: 0;
    bottom: 120%;
    min-width: 240px;
    padding: 0.75rem 0.85rem;
    border-radius: 6px;
    background: var(--bg-card);
    box-shadow: var(--shadow-lg);
    border: 1px solid var(--border-primary);
    display: none;
    z-index: 20;
}

.history-title {
    font-size: 0.8125rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 0.35rem;
}

.history-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.75rem;
    color: var(--text-secondary);
}

.history-label {
    font-weight: 500;
}

.history-value {
    font-weight: 600;
    color: var(--text-primary);
}

.status-history-cell:hover .status-history-tooltip {
    display: block;
}

.status-history-tooltip:hover {
    display: block;
}

.table-actions.icons-only {
    gap: 0.25rem;
}

.icon-action-btn {
    width: 30px;
    height: 30px;
    border-radius: 5px;
    border: none;
    background: transparent;
    color: var(--text-secondary);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: color 0.2s ease, background-color 0.2s ease, transform 0.2s ease;
}

.icon-action-btn:hover {
    color: var(--color-primary-db);
    background-color: rgba(148, 163, 184, 0.18);
}

.icon-action-btn.danger:hover {
    color: var(--color-error);
    background-color: rgba(239, 68, 68, 0.12);
}

/* Dark mode tweaks for status/payment badges on orders table */
[data-theme="dark"] .orders-table .status-badge.status-pending,
[data-theme="dark"] .order-card .status-badge.status-pending {
    background: rgba(250, 204, 21, 0.18);
    color: #facc15;
}

[data-theme="dark"] .orders-table .status-badge.status-processing,
[data-theme="dark"] .order-card .status-badge.status-processing {
    background: rgba(56, 189, 248, 0.18);
    color: #7dd3fc;
}

[data-theme="dark"] .orders-table .status-badge.status-shipped,
[data-theme="dark"] .order-card .status-badge.status-shipped {
    background: rgba(129, 140, 248, 0.22);
    color: #c7d2fe;
}

[data-theme="dark"] .orders-table .status-badge.status-delivered,
[data-theme="dark"] .order-card .status-badge.status-delivered {
    background: rgba(34, 197, 94, 0.22);
    color: #bbf7d0;
}

[data-theme="dark"] .orders-table .status-badge.status-cancelled,
[data-theme="dark"] .order-card .status-badge.status-cancelled {
    background: rgba(248, 113, 113, 0.22);
    color: #fecaca;
}

[data-theme="dark"] .orders-table .payment-badge.payment-pending,
[data-theme="dark"] .order-card .payment-badge.payment-pending {
    background: rgba(250, 204, 21, 0.18);
    color: #facc15;
}

[data-theme="dark"] .orders-table .payment-badge.payment-paid,
[data-theme="dark"] .order-card .payment-badge.payment-paid {
    background: rgba(34, 197, 94, 0.22);
    color: #bbf7d0;
}

[data-theme="dark"] .orders-table .payment-badge.payment-failed,
[data-theme="dark"] .order-card .payment-badge.payment-failed {
    background: rgba(248, 113, 113, 0.22);
    color: #fecaca;
}

[data-theme="dark"] .orders-table .payment-badge.payment-refunded,
[data-theme="dark"] .order-card .payment-badge.payment-refunded {
    background: rgba(219, 234, 254, 0.18);
    color: #bfdbfe;
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
    padding: 0.35rem 0.75rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: capitalize;
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
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
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

.pagination-rows {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.875rem;
    color: var(--text-secondary);
}

.rows-label {
    font-weight: 500;
}

.rows-select {
    height: 32px;
    border-radius: 6px;
    border: 1px solid var(--border-primary);
    background: var(--bg-card);
    color: var(--text-primary);
    padding: 0 0.75rem;
    font-size: 0.875rem;
}

.pagination-pages {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}

/* Modal */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.65);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
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
    -webkit-appearance: none;
    appearance: none;
    border-radius: 4px;
    border: 2px solid var(--border-primary);
    background-color: transparent;
    cursor: pointer;
    position: relative;
}

.select-all-checkbox input[type="checkbox"]:checked {
    border-color: var(--color-primary-db);
    background-color: var(--color-primary-db);
}

.select-all-checkbox input[type="checkbox"]:checked::after {
    content: '';
    position: absolute;
    inset: 3px;
    border-bottom: 2px solid #ffffff;
    border-right: 2px solid #ffffff;
    transform: rotate(45deg);
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
    -webkit-appearance: none;
    appearance: none;
    border-radius: 4px;
    border: 2px solid var(--border-primary);
    background-color: transparent;
    cursor: pointer;
    position: relative;
    transition: transform 0.2s ease;
}

.order-checkbox:checked {
    border-color: var(--color-primary-db);
    background-color: var(--color-primary-db);
}

.order-checkbox:checked::after {
    content: '';
    position: absolute;
    inset: 4px;
    border-bottom: 2px solid #ffffff;
    border-right: 2px solid #ffffff;
    transform: rotate(45deg);
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

/* Show table on wide screens, keep cards for mobile */
@media (min-width: 1024px) {
    .orders-table-wrapper {
        display: block;
    }
    .orders-list {
        display: none;
    }
    .select-all-mobile {
        display: none;
    }
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
