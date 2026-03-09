<?php
/**
 * Public signup: create new store + subscription + first admin.
 * Use from main domain (e.g. myplatform.com/signup/), not a store subdomain.
 */
session_start();
require_once __DIR__ . '/../config/database.php';

$error = '';
$success = '';
$plans = [];

try {
    $db = new Database();
    $conn = $db->getConnection();
    $plans = $conn->query("SELECT id, name, slug, price_monthly, price_yearly, billing_interval FROM subscription_plans WHERE is_active = 1 ORDER BY sort_order")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = 'Database not ready. Run migration first.';
}

$reserved_subdomains = ['www', 'admin', 'api', 'super-admin', 'signup', 'mail', 'ftp', 'default', 'app', 'store'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $store_name = trim($_POST['store_name'] ?? '');
    $subdomain = strtolower(trim(preg_replace('/[^a-z0-9\-]/', '', $_POST['subdomain'] ?? '')));
    $plan_id = (int)($_POST['plan_id'] ?? 0);

    if (!$email || !$password || !$store_name || !$subdomain || !$plan_id) {
        $error = 'Please fill all fields.';
    } elseif (strlen($subdomain) < 2) {
        $error = 'Subdomain must be at least 2 characters.';
    } elseif (in_array($subdomain, $reserved_subdomains, true)) {
        $error = 'This subdomain is reserved.';
    } else {
        $stmt = $conn->prepare("SELECT id FROM stores WHERE subdomain = ?");
        $stmt->execute([$subdomain]);
        if ($stmt->fetch()) {
            $error = 'This subdomain is already taken.';
        } else {
            $stmt = $conn->prepare("SELECT id FROM subscription_plans WHERE id = ? AND is_active = 1");
            $stmt->execute([$plan_id]);
            if (!$stmt->fetch()) {
                $error = 'Invalid plan.';
            } else {
                try {
                    $conn->beginTransaction();
                    $period_end = date('Y-m-d', strtotime('+1 month'));
                    $stmt = $conn->prepare("INSERT INTO stores (name, subdomain, status, owner_email) VALUES (?, ?, 'active', ?)");
                    $stmt->execute([$store_name, $subdomain, $email]);
                    $store_id = $conn->lastInsertId();
                    $stmt = $conn->prepare("INSERT INTO subscriptions (store_id, plan_id, status, started_at, current_period_start, current_period_end) VALUES (?, ?, 'active', NOW(), CURDATE(), ?)");
                    $stmt->execute([$store_id, $plan_id, $period_end]);
                    $sub_id = $conn->lastInsertId();
                    $stmt = $conn->prepare("UPDATE stores SET subscription_id = ? WHERE id = ?");
                    $stmt->execute([$sub_id, $store_id]);
                    $defaults = [
                        'site_name' => $store_name,
                        'active_template' => 'temp1',
                        'currency' => 'USD',
                        'categories_enabled' => '1',
                    ];
                    $ins = $conn->prepare("INSERT INTO settings (store_id, setting_key, value) VALUES (?, ?, ?)");
                    foreach ($defaults as $k => $v) {
                        $ins->execute([$store_id, $k, $v]);
                    }
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("INSERT INTO admin_users (store_id, username, password, is_active) VALUES (?, ?, ?, 1)");
                    $stmt->execute([$store_id, $email, $hash]);
                    $conn->commit();
                    $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
                    $store_url = preg_replace('/^([^.]+\.)?/', $subdomain . '.', $base_url);
                    $success = 'Store created. Log in at: ' . $store_url . '/admin/';
                } catch (Exception $e) {
                    $conn->rollBack();
                    $error = 'Could not create store: ' . $e->getMessage();
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign up - Create your store</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-body p-4">
                        <h2 class="mb-4">Create your store</h2>
                        <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        <?php if ($success): ?>
                        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                        <?php else: ?>
                        <form method="post">
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Password</label>
                                <input type="password" name="password" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Store name</label>
                                <input type="text" name="store_name" class="form-control" required value="<?php echo htmlspecialchars($_POST['store_name'] ?? ''); ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Subdomain</label>
                                <div class="input-group">
                                    <input type="text" name="subdomain" class="form-control" required placeholder="mystore" pattern="[a-zA-Z0-9\-]+" value="<?php echo htmlspecialchars($_POST['subdomain'] ?? ''); ?>">
                                    <span class="input-group-text">.myplatform.com</span>
                                </div>
                                <small class="text-muted">Letters, numbers, hyphens only. Your store will be at subdomain.myplatform.com</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Plan</label>
                                <select name="plan_id" class="form-select" required>
                                    <option value="">Choose plan</option>
                                    <?php foreach ($plans as $p): ?>
                                    <option value="<?php echo $p['id']; ?>" <?php echo (isset($_POST['plan_id']) && (int)$_POST['plan_id'] === (int)$p['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($p['name']); ?> — <?php echo $p['price_monthly'] ? '$' . $p['price_monthly'] . '/mo' : ''; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Create store</button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
