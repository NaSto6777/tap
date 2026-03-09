<?php
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['super_admin_logged_in']) || $_SESSION['super_admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$db = new Database();
$conn = $db->getConnection();

$has_order_limit = false;
try {
    $conn->query("SELECT order_limit FROM subscription_plans LIMIT 1");
    $has_order_limit = true;
} catch (PDOException $e) {}

// Handle POST: add, edit, delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'delete') {
        $plan_id = (int)($_POST['plan_id'] ?? 0);
        if ($plan_id > 0) {
            $stmt = $conn->prepare("SELECT COUNT(*) FROM subscriptions WHERE plan_id = ?");
            $stmt->execute([$plan_id]);
            if ((int)$stmt->fetchColumn() > 0) {
                header('Location: plans.php?msg=delete_blocked');
                exit;
            }
            $conn->prepare("DELETE FROM subscription_plans WHERE id = ?")->execute([$plan_id]);
            header('Location: plans.php?msg=deleted');
            exit;
        }
    }
    if ($action === 'save') {
        $plan_id = (int)($_POST['plan_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $price_monthly = $_POST['price_monthly'] !== '' ? (float)$_POST['price_monthly'] : null;
        $price_yearly = $_POST['price_yearly'] !== '' ? (float)$_POST['price_yearly'] : null;
        $order_limit = $has_order_limit && isset($_POST['order_limit']) && $_POST['order_limit'] !== '' ? (int)$_POST['order_limit'] : null;
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $sort_order = (int)($_POST['sort_order'] ?? 0);
        $features_raw = trim($_POST['features'] ?? '');
        $features = $features_raw === '' ? null : $features_raw;
        if (preg_match('/^\[.*\]$/s', $features_raw)) {
            $features = $features_raw;
        } else {
            $lines = array_filter(array_map('trim', explode("\n", $features_raw)));
            $features = $lines ? json_encode($lines) : null;
        }
        if ($name !== '' && $slug !== '') {
            if ($plan_id > 0) {
                if ($has_order_limit) {
                    $stmt = $conn->prepare("UPDATE subscription_plans SET name = ?, slug = ?, price_monthly = ?, price_yearly = ?, order_limit = ?, features = ?, is_active = ?, sort_order = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$name, $slug, $price_monthly, $price_yearly, $order_limit, $features, $is_active, $sort_order, $plan_id]);
                } else {
                    $stmt = $conn->prepare("UPDATE subscription_plans SET name = ?, slug = ?, price_monthly = ?, price_yearly = ?, features = ?, is_active = ?, sort_order = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$name, $slug, $price_monthly, $price_yearly, $features, $is_active, $sort_order, $plan_id]);
                }
                header('Location: plans.php?msg=saved');
            } else {
                if ($has_order_limit) {
                    $stmt = $conn->prepare("INSERT INTO subscription_plans (name, slug, price_monthly, price_yearly, order_limit, features, is_active, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$name, $slug, $price_monthly, $price_yearly, $order_limit, $features, $is_active, $sort_order]);
                } else {
                    $stmt = $conn->prepare("INSERT INTO subscription_plans (name, slug, price_monthly, price_yearly, features, is_active, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$name, $slug, $price_monthly, $price_yearly, $features, $is_active, $sort_order]);
                }
                header('Location: plans.php?msg=added');
            }
            exit;
        }
    }
}

$plans = $conn->query("SELECT id, name, slug, price_monthly, price_yearly, features, is_active, sort_order" . ($has_order_limit ? ", order_limit" : "") . " FROM subscription_plans ORDER BY sort_order ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);

$edit_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$edit_plan = null;
if ($edit_id > 0) {
    foreach ($plans as $p) {
        if ((int)$p['id'] === $edit_id) { $edit_plan = $p; break; }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin - Plans</title>
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
        <div class="alert alert-<?php echo in_array($_GET['msg'], ['delete_blocked'], true) ? 'warning' : 'success'; ?>"><?php
            $m = $_GET['msg'];
            echo $m === 'saved' ? 'Plan updated.' : ($m === 'added' ? 'Plan created.' : ($m === 'deleted' ? 'Plan deleted.' : ($m === 'delete_blocked' ? 'Cannot delete: at least one store uses this plan.' : 'Done.')));
        ?></div>
        <?php endif; ?>
        <h1 class="mb-4">Subscription Plans</h1>

        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title"><?php echo $edit_plan ? 'Edit plan' : 'Add plan'; ?></h5>
                <form method="post" class="row g-3">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="plan_id" value="<?php echo $edit_plan ? (int)$edit_plan['id'] : 0; ?>">
                    <div class="col-md-4">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" class="form-control" required value="<?php echo $edit_plan ? htmlspecialchars($edit_plan['name']) : ''; ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Slug</label>
                        <input type="text" name="slug" class="form-control" required value="<?php echo $edit_plan ? htmlspecialchars($edit_plan['slug']) : ''; ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Price (monthly)</label>
                        <input type="number" name="price_monthly" class="form-control" step="0.01" min="0" placeholder="0" value="<?php echo $edit_plan && $edit_plan['price_monthly'] !== null ? htmlspecialchars($edit_plan['price_monthly']) : ''; ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Price (yearly)</label>
                        <input type="number" name="price_yearly" class="form-control" step="0.01" min="0" placeholder="0" value="<?php echo $edit_plan && $edit_plan['price_yearly'] !== null ? htmlspecialchars($edit_plan['price_yearly']) : ''; ?>">
                    </div>
                    <?php if ($has_order_limit): ?>
                    <div class="col-md-2">
                        <label class="form-label">Order limit</label>
                        <input type="number" name="order_limit" class="form-control" min="0" placeholder="0 = unlimited" value="<?php echo $edit_plan && isset($edit_plan['order_limit']) && $edit_plan['order_limit'] !== null ? (int)$edit_plan['order_limit'] : ''; ?>">
                        <small class="text-muted">0 or empty = unlimited</small>
                    </div>
                    <?php endif; ?>
                    <div class="col-md-2">
                        <label class="form-label">Sort order</label>
                        <input type="number" name="sort_order" class="form-control" value="<?php echo $edit_plan ? (int)$edit_plan['sort_order'] : 0; ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Features (JSON array or one per line)</label>
                        <textarea name="features" class="form-control" rows="3" placeholder='["Feature 1","Feature 2"] or one per line'><?php
                            if ($edit_plan && $edit_plan['features'] !== null && $edit_plan['features'] !== '') {
                                $f = $edit_plan['features'];
                                $dec = json_decode($f);
                                if (is_array($dec)) {
                                    echo htmlspecialchars(implode("\n", $dec));
                                } else {
                                    echo htmlspecialchars($f);
                                }
                            }
                        ?></textarea>
                    </div>
                    <div class="col-12">
                        <div class="form-check">
                            <input type="checkbox" name="is_active" class="form-check-input" id="is_active" value="1" <?php echo ($edit_plan && (int)$edit_plan['is_active']) || !$edit_plan ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="is_active">Active</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary"><?php echo $edit_plan ? 'Update' : 'Create'; ?> plan</button>
                        <?php if ($edit_plan): ?>
                        <a href="plans.php" class="btn btn-outline-secondary">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Plans list</h5>
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Slug</th>
                            <th>Price (mo/yr)</th>
                            <?php if ($has_order_limit): ?><th>Order limit</th><?php endif; ?>
                            <th>Status</th>
                            <th>Sort</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($plans as $p): ?>
                        <tr>
                            <td><?php echo (int)$p['id']; ?></td>
                            <td><?php echo htmlspecialchars($p['name']); ?></td>
                            <td><code><?php echo htmlspecialchars($p['slug']); ?></code></td>
                            <td><?php echo $p['price_monthly'] !== null ? number_format((float)$p['price_monthly'], 2) : '—'; ?> / <?php echo $p['price_yearly'] !== null ? number_format((float)$p['price_yearly'], 2) : '—'; ?></td>
                            <?php if ($has_order_limit): ?>
                            <td><?php echo isset($p['order_limit']) && $p['order_limit'] !== null ? (int)$p['order_limit'] : 'Unlimited'; ?></td>
                            <?php endif; ?>
                            <td><span class="badge bg-<?php echo (int)($p['is_active'] ?? 0) ? 'success' : 'secondary'; ?>"><?php echo (int)($p['is_active'] ?? 0) ? 'Active' : 'Inactive'; ?></span></td>
                            <td><?php echo (int)($p['sort_order'] ?? 0); ?></td>
                            <td>
                                <a href="plans.php?id=<?php echo (int)$p['id']; ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                <form method="post" class="d-inline" onsubmit="return confirm('Delete this plan?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="plan_id" value="<?php echo (int)$p['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
