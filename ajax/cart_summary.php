<?php
session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/StoreContext.php';
require_once __DIR__ . '/../config/image_helper.php';

header('Content-Type: application/json');

try {
    $store = StoreContext::resolveFromRequest(defined('PLATFORM_BASE_DOMAIN') ? PLATFORM_BASE_DOMAIN : 'localhost');
    if (!$store) {
        echo json_encode(['success' => false, 'message' => 'Store not found']);
        exit;
    }
    StoreContext::set($store['id'], $store['store']);

    $store_id = StoreContext::getId();
    if (!$store_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid store']);
        exit;
    }

    if (empty($_SESSION['cart'])) {
        echo json_encode(['success' => true, 'items' => [], 'subtotal' => 0, 'subtotal_formatted' => '0']);
        exit;
    }

    $db   = new Database();
    $conn = $db->getConnection();

    $items      = $_SESSION['cart'];
    $productIds = [];
    $variantIds = [];
    foreach ($items as $row) {
        $productIds[] = (int)($row['product_id'] ?? 0);
        if (!empty($row['variant_id'])) {
            $variantIds[] = (int)$row['variant_id'];
        }
    }
    $productIds = array_values(array_unique(array_filter($productIds)));
    $variantIds = array_values(array_unique(array_filter($variantIds)));

    $products = [];
    if ($productIds) {
        $placeholders = implode(',', array_fill(0, count($productIds), '?'));
        $stmt = $conn->prepare(
            "SELECT id, name, sku, price, sale_price 
             FROM products 
             WHERE id IN ($placeholders) AND store_id = ? AND is_active = 1"
        );
        $stmt->execute(array_merge($productIds, [$store_id]));
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $p) {
            $products[(int)$p['id']] = $p;
        }
    }

    $variants = [];
    if ($variantIds) {
        $placeholders = implode(',', array_fill(0, count($variantIds), '?'));
        $stmt = $conn->prepare(
            "SELECT id, product_id, label, price, stock_quantity 
             FROM product_variants 
             WHERE id IN ($placeholders) AND store_id = ?"
        );
        $stmt->execute(array_merge($variantIds, [$store_id]));
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $v) {
            $variants[(int)$v['id']] = $v;
        }
    }

    // Minimal currency info (no Settings instance to keep this endpoint light)
    $currency_symbol = 'TND';
    $currency_position = 'right';

    $out      = [];
    $subtotal = 0;

    foreach ($items as $row) {
        $product_id = (int)($row['product_id'] ?? 0);
        if (!$product_id || !isset($products[$product_id])) {
            continue;
        }
        $product = $products[$product_id];
        $quantity = max(1, (int)($row['quantity'] ?? 1));

        $variant_id = !empty($row['variant_id']) ? (int)$row['variant_id'] : null;
        $variant    = $variant_id && isset($variants[$variant_id]) ? $variants[$variant_id] : null;

        $unitPrice = $variant && $variant['price'] !== null
            ? (float)$variant['price']
            : ((float)$product['sale_price'] ?: (float)$product['price']);

        $lineTotal = $unitPrice * $quantity;
        $subtotal += $lineTotal;

        $totalFormatted = $currency_position === 'left'
            ? $currency_symbol . number_format($lineTotal, 2)
            : number_format($lineTotal, 2) . ' ' . $currency_symbol;

        $out[] = [
            'name'            => $product['name'],
            'variant'         => $variant ? ($variant['label'] ?? null) : ($row['variant_label'] ?? null),
            'quantity'        => $quantity,
            'total'           => $lineTotal,
            'total_formatted' => $totalFormatted,
            'image'           => ImageHelper::getProductImage($product_id),
        ];
    }

    $subtotalFormatted = $currency_position === 'left'
        ? $currency_symbol . number_format($subtotal, 2)
        : number_format($subtotal, 2) . ' ' . $currency_symbol;

    echo json_encode([
        'success'           => true,
        'items'             => $out,
        'subtotal'          => $subtotal,
        'subtotal_formatted'=> $subtotalFormatted,
    ]);
} catch (Throwable $e) {
    error_log('cart_summary error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}

