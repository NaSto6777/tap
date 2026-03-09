<?php
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['super_admin_logged_in']) || $_SESSION['super_admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$db = new Database();
$conn = $db->getConnection();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $store_id = (int)($_POST['store_id'] ?? 0);
    if ($action === 'suspend' && $store_id) {
        $stmt = $conn->prepare("UPDATE stores SET status = 'suspended' WHERE id = ?");
        $stmt->execute([$store_id]);
        header('Location: index.php?msg=suspended');
        exit;
    }
    if ($action === 'activate' && $store_id) {
        $stmt = $conn->prepare("UPDATE stores SET status = 'active' WHERE id = ?");
        $stmt->execute([$store_id]);
        header('Location: index.php?msg=activated');
        exit;
    }
    if ($action === 'extend_subscription' && $store_id) {
        $months = (int)($_POST['months'] ?? 1);
        $stmt = $conn->prepare("UPDATE subscriptions SET status = 'active', current_period_start = CURDATE(), current_period_end = DATE_ADD(CURDATE(), INTERVAL ? MONTH) WHERE store_id = ?");
        $stmt->execute([$months, $store_id]);
        $conn->prepare("UPDATE stores SET status = 'active' WHERE id = ?")->execute([$store_id]);
        header('Location: index.php?msg=extended');
        exit;
    }
    if ($action === 'extend_30' && $store_id) {
        $stmt = $conn->prepare("UPDATE subscriptions SET status = 'active', current_period_start = CURDATE(), current_period_end = DATE_ADD(CURDATE(), INTERVAL 30 DAY) WHERE store_id = ?");
        $stmt->execute([$store_id]);
        $conn->prepare("UPDATE stores SET status = 'active' WHERE id = ?")->execute([$store_id]);
        header('Location: index.php?msg=extended');
        exit;
    }
    if ($action === 'extend_365' && $store_id) {
        $stmt = $conn->prepare("UPDATE subscriptions SET status = 'active', current_period_start = CURDATE(), current_period_end = DATE_ADD(CURDATE(), INTERVAL 365 DAY) WHERE store_id = ?");
        $stmt->execute([$store_id]);
        $conn->prepare("UPDATE stores SET status = 'active' WHERE id = ?")->execute([$store_id]);
        header('Location: index.php?msg=extended');
        exit;
    }
    if ($action === 'top_up_orders' && $store_id) {
        $add = (int)($_POST['add_orders'] ?? 0);
        if ($add > 0) {
            try {
                $stmt = $conn->prepare("UPDATE stores SET order_view_allowance = COALESCE(order_view_allowance, 0) + ? WHERE id = ?");
                $stmt->execute([$add, $store_id]);
                header('Location: index.php?msg=topped');
                exit;
            } catch (PDOException $e) {
                header('Location: index.php?msg=topup_fail');
                exit;
            }
        }
    }
    if ($action === 'add_order_credits' && $store_id) {
        $add = (int)($_POST['add_credits'] ?? 0);
        if ($add > 0) {
            try {
                $stmt = $conn->prepare("UPDATE stores SET order_credits = COALESCE(order_credits, 0) + ? WHERE id = ?");
                $stmt->execute([$add, $store_id]);
                header('Location: index.php?msg=credits_added');
                exit;
            } catch (PDOException $e) {
                header('Location: index.php?msg=credits_fail');
                exit;
            }
        }
    }
}

// Stats
$stmt = $conn->query("SELECT COUNT(*) FROM stores");
$total_stores = $stmt->fetchColumn();
$stmt = $conn->query("SELECT COUNT(*) FROM stores WHERE status = 'active'");
$active_stores = $stmt->fetchColumn();
$stmt = $conn->query("SELECT COUNT(*) FROM subscriptions WHERE status IN ('active','trial') AND (current_period_end IS NULL OR current_period_end >= CURDATE())");
$active_subs = $stmt->fetchColumn();

// List stores with subscription, plan, and order allowance (sell-by-order)
$stores = [];
try {
    $query = "SELECT s.id, s.name, s.subdomain, s.status as store_status, s.created_at, s.order_view_allowance, s.order_credits,
              sub.status as sub_status, sub.current_period_end, sub.current_period_start,
              p.name as plan_name
              FROM stores s
              LEFT JOIN subscriptions sub ON s.subscription_id = sub.id
              LEFT JOIN subscription_plans p ON sub.plan_id = p.id
              ORDER BY s.id ASC";
    $stores = $conn->query($query)->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $query = "SELECT s.id, s.name, s.subdomain, s.status as store_status, s.created_at,
              sub.status as sub_status, sub.current_period_end, sub.current_period_start,
              p.name as plan_name
              FROM stores s
              LEFT JOIN subscriptions sub ON s.subscription_id = sub.id
              LEFT JOIN subscription_plans p ON sub.plan_id = p.id
              ORDER BY s.id ASC";
    $stores = $conn->query($query)->fetchAll(PDO::FETCH_ASSOC);
    foreach ($stores as &$row) { $row['order_view_allowance'] = null; $row['order_credits'] = 0; }
}

// Per-store analytics: orders (count), revenue (paid only), products count
$ordersByStore = [];
$revenueByStore = [];
$productsByStore = [];
$lastLoginByStore = [];
$hasLastLoginColumn = false;

try {
    $stmt = $conn->query("SELECT store_id, COUNT(*) as order_count, COALESCE(SUM(total_amount), 0) as revenue FROM orders WHERE payment_status = 'paid' GROUP BY store_id");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $ordersByStore[(int)$row['store_id']] = (int)$row['order_count'];
        $revenueByStore[(int)$row['store_id']] = (float)$row['revenue'];
    }
} catch (PDOException $e) { /* ignore */ }

try {
    $stmt = $conn->query("SELECT store_id, COUNT(*) as product_count FROM products GROUP BY store_id");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $productsByStore[(int)$row['store_id']] = (int)$row['product_count'];
    }
} catch (PDOException $e) { /* ignore */ }

try {
    $stmt = $conn->query("SELECT store_id, MAX(last_login) as last_login FROM admin_users WHERE last_login IS NOT NULL GROUP BY store_id");
    $hasLastLoginColumn = true;
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $lastLoginByStore[(int)$row['store_id']] = $row['last_login'];
    }
} catch (PDOException $e) {
    $hasLastLoginColumn = false;
}

// Platform totals
$platformTotalOrders = array_sum($ordersByStore);
$platformTotalRevenue = array_sum($revenueByStore);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin - Stores</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <nav class="navbar navbar-dark bg-dark">
        <div class="container-fluid">
            <span class="navbar-brand">Super Admin</span>
            <a href="index.php" class="btn btn-outline-light btn-sm me-2">Stores</a>
            <a href="plans.php" class="btn btn-outline-light btn-sm me-2">Plans</a>
            <span class="text-white me-2"><?php echo htmlspecialchars($_SESSION['super_admin_email'] ?? ''); ?></span>
            <a href="logout.php" class="btn btn-outline-light btn-sm">Logout</a>
        </div>
    </nav>
    <div class="container py-4">
        <?php if (!empty($_GET['msg'])): ?>
        <div class="alert alert-success"><?php
            $m = $_GET['msg'];
            echo $m === 'topped' ? 'Order allowance topped up.' : ($m === 'topup_fail' ? 'Top-up failed (run migration_order_allowance.sql).' : ($m === 'credits_added' ? 'Order credits added.' : ($m === 'credits_fail' ? 'Order credits update failed (run migration_billing_plans.sql).' : 'Action completed.')));
        ?></div>
        <?php endif; ?>
        <h1 class="mb-4">Stores</h1>
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Total Stores</h5>
                        <p class="display-6"><?php echo (int)$total_stores; ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Active Stores</h5>
                        <p class="display-6"><?php echo (int)$active_stores; ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Active Subscriptions</h5>
                        <p class="display-6"><?php echo (int)$active_subs; ?></p>
                    </div>
                </div>
            </div>
        </div>
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card border-primary">
                    <div class="card-body">
                        <h5 class="card-title text-muted">Platform total orders</h5>
                        <p class="display-6 mb-0"><?php echo (int)$platformTotalOrders; ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card border-success">
                    <div class="card-body">
                        <h5 class="card-title text-muted">Platform total revenue</h5>
                        <p class="display-6 mb-0"><?php echo number_format($platformTotalRevenue, 2); ?> <small class="text-muted">(paid)</small></p>
                    </div>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-body">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Subdomain</th>
                            <th>Orders</th>
                            <th>Revenue</th>
                            <th>Products</th>
                            <th>Can see</th>
                            <th>Credits</th>
                            <th>Views</th>
                            <th>Last login</th>
                            <th>Plan</th>
                            <th>Subscription</th>
                            <th>Period End</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stores as $s): ?>
                        <tr>
                            <td><?php echo (int)$s['id']; ?></td>
                            <td><?php echo htmlspecialchars($s['name']); ?></td>
                            <td><code><?php echo htmlspecialchars($s['subdomain']); ?></code></td>
                            <td><?php echo (int)($ordersByStore[$s['id']] ?? 0); ?></td>
                            <td><?php echo number_format($revenueByStore[$s['id']] ?? 0, 2); ?></td>
                            <td><?php echo (int)($productsByStore[$s['id']] ?? 0); ?></td>
                            <td><?php
                                $allow = isset($s['order_view_allowance']) && $s['order_view_allowance'] !== null && $s['order_view_allowance'] !== '' ? (int)$s['order_view_allowance'] : null;
                                echo $allow !== null ? $allow . ' orders' : '—';
                            ?></td>
                            <td><?php echo (int)($s['order_credits'] ?? 0); ?></td>
                            <td title="Per-store views when analytics are scoped by store">—</td>
                            <td><?php
                                if ($hasLastLoginColumn && !empty($lastLoginByStore[$s['id']])) {
                                    echo htmlspecialchars(date('Y-m-d H:i', strtotime($lastLoginByStore[$s['id']])));
                                } else {
                                    echo '—';
                                }
                            ?></td>
                            <td><?php echo htmlspecialchars($s['plan_name'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($s['sub_status'] ?? '-'); ?></td>
                            <td><?php echo $s['current_period_end'] ? htmlspecialchars($s['current_period_end']) : '-'; ?></td>
                            <td>
                                <span class="badge bg-<?php echo $s['store_status'] === 'active' ? 'success' : ($s['store_status'] === 'suspended' ? 'warning' : 'secondary'); ?>">
                                    <?php echo htmlspecialchars($s['store_status']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($s['store_status'] === 'active'): ?>
                                <form method="post" class="d-inline" onsubmit="return confirm('Suspend this store?');">
                                    <input type="hidden" name="action" value="suspend">
                                    <input type="hidden" name="store_id" value="<?php echo $s['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-warning">Suspend</button>
                                </form>
                                <?php elseif ($s['store_status'] === 'suspended'): ?>
                                <form method="post" class="d-inline">
                                    <input type="hidden" name="action" value="activate">
                                    <input type="hidden" name="store_id" value="<?php echo $s['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-success">Activate</button>
                                </form>
                                <?php endif; ?>
                                <form method="post" class="d-inline">
                                    <input type="hidden" name="action" value="extend_subscription">
                                    <input type="hidden" name="store_id" value="<?php echo $s['id']; ?>">
                                    <input type="number" name="months" value="1" min="1" max="24" class="form-control form-control-sm d-inline-block w-auto">
                                    <button type="submit" class="btn btn-sm btn-outline-primary">Extend (months)</button>
                                </form>
                                <form method="post" class="d-inline">
                                    <input type="hidden" name="action" value="extend_30">
                                    <input type="hidden" name="store_id" value="<?php echo $s['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-info">+30 days</button>
                                </form>
                                <form method="post" class="d-inline">
                                    <input type="hidden" name="action" value="extend_365">
                                    <input type="hidden" name="store_id" value="<?php echo $s['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-info">+365 days</button>
                                </form>
                                <form method="post" class="d-inline" title="Add orders they can view (sell by order)">
                                    <input type="hidden" name="action" value="top_up_orders">
                                    <input type="hidden" name="store_id" value="<?php echo $s['id']; ?>">
                                    <input type="number" name="add_orders" value="20" min="1" max="9999" class="form-control form-control-sm d-inline-block w-auto" style="width:4em" aria-label="Orders to add">
                                    <button type="submit" class="btn btn-sm btn-outline-success">+ Orders</button>
                                </form>
                                <form method="post" class="d-inline" title="Add extra order placement credits">
                                    <input type="hidden" name="action" value="add_order_credits">
                                    <input type="hidden" name="store_id" value="<?php echo $s['id']; ?>">
                                    <input type="number" name="add_credits" value="50" min="1" max="9999" class="form-control form-control-sm d-inline-block w-auto" style="width:4em" aria-label="Credits to add">
                                    <button type="submit" class="btn btn-sm btn-outline-secondary">+ Order credits</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <p class="mt-3 text-muted small">Mark as paid: use "Extend (months)" to set subscription period end. Run config/migration_multitenant.sql first. Create first super admin via SQL: <code>INSERT INTO super_admin_users (email, password, name) VALUES ('your@email.com', '&lt;hash&gt;', 'Name');</code> Generate hash in PHP: <code>password_hash('yourpassword', PASSWORD_DEFAULT)</code>.</p>
    </div>
</body>
</html>
