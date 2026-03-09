<?php
session_start();
require_once 'config/database.php';
require_once 'config/StoreContext.php';

if (!defined('PLATFORM_BASE_DOMAIN')) {
    define('PLATFORM_BASE_DOMAIN', 'localhost');
}

// Router: main domain → Landing page; subdomain → Ecommerce storefront
if (StoreContext::isMainDomain(PLATFORM_BASE_DOMAIN)) {
    require_once 'config/settings.php';
    include 'landing.php';
    exit;
}

// Subdomain: resolve store and load storefront
require_once 'config/settings.php';
require_once 'config/image_helper.php';

$resolved = StoreContext::resolveFromRequest(PLATFORM_BASE_DOMAIN);
if (!$resolved) {
    header('HTTP/1.1 404 Not Found');
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Store not found</title></head><body><h1>Store not found</h1><p>This store does not exist or is not active.</p></body></html>';
    exit;
}

StoreContext::set($resolved['id'], $resolved['store']);
$settings = new Settings();
$activeTemplate = $settings->getSetting('active_template', 'temp1');
// Fallback to temp1 only if the configured template directory does not exist
if (!is_dir(__DIR__ . "/templates/{$activeTemplate}")) {
    $activeTemplate = 'temp1';
}

// Handle cart actions before any output (AJAX or full form submit)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['page'] ?? '') === 'cart') {
    $action = $_POST['action'] ?? '';
    if (in_array($action, ['add', 'update', 'remove', 'clear'], true)) {
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
        $store_id = StoreContext::getId();
        $product_id = (int)($_POST['product_id'] ?? 0);
        $quantity = max(1, (int)($_POST['quantity'] ?? 1));
        $variant_id = isset($_POST['variant_id']) && $_POST['variant_id'] !== '' ? (int)$_POST['variant_id'] : null;
        $variant_label = $_POST['variant_label'] ?? null;

        if ($action === 'clear') {
            $_SESSION['cart'] = [];
        } elseif ($action === 'add') {
            // Verify product belongs to current store before adding
            if ($product_id <= 0) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Invalid product']);
                exit;
            }
            try {
                $db = new Database();
                $conn = $db->getConnection();
                $stmt = $conn->prepare("SELECT id FROM products WHERE id = ? AND store_id = ? AND is_active = 1");
                $stmt->execute([$product_id, $store_id]);
                if (!$stmt->fetch()) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => 'Product not found']);
                    exit;
                }
                if ($variant_id) {
                    $vstmt = $conn->prepare("SELECT id FROM product_variants WHERE id = ? AND product_id = ? AND store_id = ?");
                    $vstmt->execute([$variant_id, $product_id, $store_id]);
                    if (!$vstmt->fetch()) {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => false, 'message' => 'Variant not found']);
                        exit;
                    }
                }
            } catch (Exception $e) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Error']);
                exit;
            }
            $key = $variant_id ? ($product_id . ':' . $variant_id) : (string)$product_id;
            if (isset($_SESSION['cart'][$key])) {
                $_SESSION['cart'][$key]['quantity'] += $quantity;
            } else {
                $_SESSION['cart'][$key] = [
                    'product_id' => $product_id,
                    'variant_id' => $variant_id,
                    'variant_label' => $variant_label,
                    'quantity' => $quantity
                ];
            }
        } elseif ($action === 'update') {
            $key = $_POST['key'] ?? $_POST['cart_key'] ?? (string)$product_id;
            if ($quantity > 0 && isset($_SESSION['cart'][$key])) {
                $_SESSION['cart'][$key]['quantity'] = (int)$quantity;
            } else {
                unset($_SESSION['cart'][$key]);
            }
        } elseif ($action === 'remove') {
            $key = $_POST['key'] ?? $_POST['cart_key'] ?? (string)$product_id;
            unset($_SESSION['cart'][$key]);
        }
        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
        } else {
            header('Location: index.php?page=cart');
        }
        exit;
    }
}

// Cart count endpoint
if (isset($_GET['action']) && $_GET['action'] === 'get_cart_count') {
    $count = 0;
    if (!empty($_SESSION['cart'])) {
        foreach ($_SESSION['cart'] as $item) {
            $count += (int)($item['quantity'] ?? 0);
        }
    }
    header('Content-Type: application/json');
    echo json_encode(['count' => $count]);
    exit;
}

$page = $_GET['page'] ?? 'home';
$page_title = '';
switch ($page) {
    case 'home': $page_title = 'Home'; break;
    case 'shop': $page_title = 'Shop'; break;
    case 'about': $page_title = 'About Us'; break;
    case 'contact': $page_title = 'Contact'; break;
    case 'product_view': $page_title = 'Product View'; break;
    case 'cart': $page_title = 'Shopping Cart'; break;
    case 'checkout': $page_title = 'Checkout'; break;
    case 'checkout_success': $page_title = 'Order Confirmation'; break;
    case 'privacy': $page_title = 'Privacy Policy'; break;
    case 'terms': $page_title = 'Terms & Conditions'; break;
    default: $page_title = ucfirst(str_replace('_', ' ', $page));
}

include "templates/{$activeTemplate}/includes/header.php";
$pageFile = "templates/{$activeTemplate}/{$page}.php";
if (file_exists($pageFile)) {
    include $pageFile;
} else {
    include "templates/{$activeTemplate}/home.php";
}
include "templates/{$activeTemplate}/includes/footer.php";
