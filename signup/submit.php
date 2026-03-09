<?php
/**
 * Signup form handler: validate and create store + admin + default settings.
 * Sell by order: new store gets DEFAULT_ORDER_ALLOWANCE orders they can view; no subscription required.
 * When they use them up, they contact you and you top up order_view_allowance in Super Admin.
 */
session_start();
require_once __DIR__ . '/../config/database.php';
if (file_exists(__DIR__ . '/../config/debug.php')) {
    require_once __DIR__ . '/../config/debug.php';
}
if (!defined('APP_DEBUG')) {
    define('APP_DEBUG', false);
}

const DEFAULT_ORDER_ALLOWANCE = 20; // First N orders the store can see; top up when they pay

$reserved_subdomains = ['www', 'admin', 'superadmin', 'super-admin', 'superadmin', 'api', 'signup', 'mail', 'ftp', 'default', 'app', 'store', 'cdn', 'static', 'blog', 'help', 'support'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_with('error', 'Invalid request.');
    exit;
}

$store_name = trim($_POST['store_name'] ?? '');
$subdomain = strtolower(trim(preg_replace('/[^a-z0-9\-]/', '', $_POST['subdomain'] ?? '')));
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$plan_id = (int)($_POST['plan_id'] ?? 0);

function redirect_with($status, $msg = '', $subdomain = '') {
    $proto = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? (defined('PLATFORM_BASE_DOMAIN') ? PLATFORM_BASE_DOMAIN : 'localhost');
    $base_path = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    if ($base_path === '' || $base_path === '\\') {
        $base_path = '';
    }
    $base = $proto . '://' . $host . $base_path . '/';
    if ($status === 'success') {
        $url = $base . '?signup=success';
        if ($subdomain !== '') {
            $url .= '&store=' . rawurlencode($subdomain);
        }
    } else {
        $url = $base . '?signup=error&msg=' . rawurlencode($msg);
    }
    $url .= '#signup';
    header('Location: ' . $url);
    exit;
}

if (strlen($store_name) < 2) {
    redirect_with('error', 'Store name must be at least 2 characters.');
}
if (strlen($subdomain) < 2) {
    redirect_with('error', 'Subdomain must be at least 2 characters.');
}
if (strlen($subdomain) > 63) {
    redirect_with('error', 'Subdomain is too long.');
}
if (in_array($subdomain, $reserved_subdomains, true)) {
    redirect_with('error', 'This subdomain is reserved. Choose another.');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    redirect_with('error', 'Please enter a valid email address.');
}
if (strlen($password) < 6) {
    redirect_with('error', 'Password must be at least 6 characters.');
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    $stmt = $conn->prepare("SELECT id FROM stores WHERE subdomain = ?");
    $stmt->execute([$subdomain]);
    if ($stmt->fetch()) {
        redirect_with('error', 'This subdomain is already taken.');
    }

    if ($plan_id >= 1) {
        $stmt = $conn->prepare("SELECT id FROM subscription_plans WHERE id = ? AND is_active = 1");
        $stmt->execute([$plan_id]);
        if (!$stmt->fetch()) {
            $plan_id = 0;
        }
    }
    if ($plan_id < 1) {
        $stmt = $conn->query("SELECT id FROM subscription_plans WHERE is_active = 1 ORDER BY sort_order ASC LIMIT 1");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $plan_id = $row ? (int)$row['id'] : 0;
    }

    $conn->beginTransaction();

    $store_insert = "INSERT INTO stores (name, subdomain, status, owner_email) VALUES (?, ?, 'active', ?)";
    $store_params = [$store_name, $subdomain, $email];
    try {
        $stmt = $conn->prepare("SHOW COLUMNS FROM stores LIKE 'order_view_allowance'");
        $stmt->execute();
        if ($stmt->fetch()) {
            $store_insert = "INSERT INTO stores (name, subdomain, status, owner_email, order_view_allowance) VALUES (?, ?, 'active', ?, " . (int) DEFAULT_ORDER_ALLOWANCE . ")";
        }
    } catch (Exception $e) {}
    $stmt = $conn->prepare($store_insert);
    $stmt->execute($store_params);
    $store_id = (int) $conn->lastInsertId();

    if ($plan_id >= 1) {
        $period_end = date('Y-m-d', strtotime('+1 month'));
        $stmt = $conn->prepare("INSERT INTO subscriptions (store_id, plan_id, status, started_at, current_period_start, current_period_end) VALUES (?, ?, 'active', NOW(), CURDATE(), ?)");
        $stmt->execute([$store_id, $plan_id, $period_end]);
        $sub_id = (int) $conn->lastInsertId();
        $stmt = $conn->prepare("UPDATE stores SET subscription_id = ? WHERE id = ?");
        $stmt->execute([$sub_id, $store_id]);
    }

    $default_settings = [
        'site_name' => $store_name,
        'active_template' => 'temp1',
        'currency' => 'USD',
        'categories_enabled' => '1',
    ];
    $ins = $conn->prepare("INSERT INTO settings (store_id, setting_key, value) VALUES (?, ?, ?)");
    foreach ($default_settings as $key => $value) {
        $ins->execute([$store_id, $key, $value]);
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $admin_has_email = false;
    try {
        $chk = $conn->prepare("SHOW COLUMNS FROM admin_users LIKE 'email'");
        $chk->execute();
        $admin_has_email = (bool) $chk->fetch();
    } catch (Exception $e) {}
    if ($admin_has_email) {
        $stmt = $conn->prepare("INSERT INTO admin_users (store_id, username, email, password, is_active) VALUES (?, ?, ?, ?, 1)");
        $stmt->execute([$store_id, $email, $email, $hash]);
    } else {
        $stmt = $conn->prepare("INSERT INTO admin_users (store_id, username, password, is_active) VALUES (?, ?, ?, 1)");
        $stmt->execute([$store_id, $email, $hash]);
    }

    $conn->commit();

    redirect_with('success', '', $subdomain);
} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log('Signup submit error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    $debug = (defined('APP_DEBUG') && APP_DEBUG) || (isset($_POST['_debug']) && $_POST['_debug'] === '1');
    if ($debug) {
        $msg = $e->getMessage() . ' [ ' . basename($e->getFile()) . ':' . $e->getLine() . ' ]';
    } else {
        $msg = 'Could not create store. Please try again or contact support.';
        if (strpos($e->getMessage(), 'Duplicate entry') !== false || strpos($e->getMessage(), 'subdomain') !== false) {
            $msg = 'This subdomain or email is already in use. Choose a different subdomain or email.';
        } elseif (strpos($e->getMessage(), 'foreign key') !== false || strpos($e->getMessage(), 'Foreign key') !== false) {
            $msg = 'Setup error: please ensure all migrations are run (subscription_plans, stores, subscriptions, settings, admin_users).';
        }
    }
    redirect_with('error', $msg);
}
