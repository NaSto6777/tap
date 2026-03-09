<?php
/**
 * Real-time Abandoned Cart Logger (AJAX)
 * Receives partial checkout form data (debounced from frontend).
 * Security: CSRF token, input sanitization, prepared statements.
 */
session_start();
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/StoreContext.php';

// Resolve store from request (same domain as checkout)
$platformBaseDomain = defined('PLATFORM_BASE_DOMAIN') ? PLATFORM_BASE_DOMAIN : 'localhost';
$resolved = StoreContext::resolveFromRequest($platformBaseDomain);
if (!$resolved) {
    echo json_encode(['success' => false, 'error' => 'Store not found']);
    exit;
}
$store_id = (int) $resolved['id'];

// CSRF validation
$csrf_token = $_POST['csrf_token'] ?? '';
if (empty($csrf_token) || !isset($_SESSION['abandoned_cart_csrf']) || !hash_equals($_SESSION['abandoned_cart_csrf'], $csrf_token)) {
    echo json_encode(['success' => false, 'error' => 'Invalid security token']);
    exit;
}

// Sanitize inputs (prevent SQL injection, limit length)
$customer_name = isset($_POST['customer_name']) ? trim(substr((string) $_POST['customer_name'], 0, 255)) : '';
$customer_email = isset($_POST['customer_email']) ? trim(substr((string) $_POST['customer_email'], 0, 255)) : '';
$customer_phone = isset($_POST['customer_phone']) ? trim(substr((string) $_POST['customer_phone'], 0, 100)) : '';

// Basic email format check (optional - allow partial)
if ($customer_email !== '' && !filter_var($customer_email, FILTER_VALIDATE_EMAIL) && strlen($customer_email) > 5) {
    // Allow partial emails while typing
}

// Require at least one of email or phone to save (otherwise too anonymous)
if ($customer_email === '' && $customer_phone === '') {
    echo json_encode(['success' => true, 'skipped' => true, 'reason' => 'No contact info yet']);
    exit;
}

// Build cart_data from session
$cart_data = [];
$cart_value = 0;
if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $key => $item) {
        $product_id = (int) ($item['product_id'] ?? 0);
        $variant_id = isset($item['variant_id']) ? (int) $item['variant_id'] : null;
        $quantity = (int) ($item['quantity'] ?? 1);
        $cart_data[] = [
            'product_id' => $product_id,
            'variant_id' => $variant_id,
            'quantity' => $quantity,
        ];
    }
    $cart_value = isset($_POST['cart_value']) ? (float) $_POST['cart_value'] : 0;
}

$session_id = session_id();
if (empty($session_id)) {
    echo json_encode(['success' => false, 'error' => 'No session']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Check if abandoned_carts has store_id (migration may not be run)
    $has_store_id = false;
    try {
        $chk = $conn->query("SHOW COLUMNS FROM abandoned_carts LIKE 'store_id'");
        $has_store_id = (bool) $chk->fetch();
    } catch (PDOException $e) {}

    $cart_json = json_encode($cart_data);

    if ($has_store_id) {
        $stmt = $conn->prepare("
            INSERT INTO abandoned_carts (store_id, session_id, customer_name, customer_email, customer_phone, cart_data, cart_value, status, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
            ON DUPLICATE KEY UPDATE
                customer_name = VALUES(customer_name),
                customer_email = VALUES(customer_email),
                customer_phone = VALUES(customer_phone),
                cart_data = VALUES(cart_data),
                cart_value = VALUES(cart_value),
                updated_at = NOW()
        ");
        $stmt->execute([$store_id, $session_id, $customer_name ?: null, $customer_email ?: null, $customer_phone ?: null, $cart_json, $cart_value]);
    } else {
        $stmt = $conn->prepare("
            INSERT INTO abandoned_carts (session_id, customer_name, customer_email, customer_phone, cart_data, cart_value)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                customer_name = VALUES(customer_name),
                customer_email = VALUES(customer_email),
                customer_phone = VALUES(customer_phone),
                cart_data = VALUES(cart_data),
                cart_value = VALUES(cart_value)
        ");
        $stmt->execute([$session_id, $customer_name ?: null, $customer_email ?: null, $customer_phone ?: null, $cart_json, $cart_value]);
    }

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    error_log('Abandoned cart log error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
