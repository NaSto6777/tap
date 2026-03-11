<?php
/**
 * Analytics Endpoint - API to receive tracking data from JavaScript
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include analytics helper and resolve store from request (never trust client-sent store_id)
require_once __DIR__ . '/analytics_helper.php';
require_once __DIR__ . '/StoreContext.php';
$storeId = null;
if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}
if (!empty($_SESSION['admin_store_id'])) {
    $storeId = (int) $_SESSION['admin_store_id'];
} else {
    $platformBaseDomain = defined('PLATFORM_BASE_DOMAIN') ? PLATFORM_BASE_DOMAIN : 'localhost';
    $resolved = StoreContext::resolveFromRequest($platformBaseDomain);
    if ($resolved) {
        $storeId = (int) $resolved['id'];
    }
}
$analytics = new AnalyticsHelper(null, $storeId);

// Initialize response
$response = [
    'success' => false,
    'message' => 'Invalid request'
];

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if ($method === 'GET') {
        $action = $_GET['action'] ?? '';
        switch ($action) {
            case 'get_recent_orders':
                $orders = $analytics->getRecentOrders(6);

                // Attach a compact products summary per order (first 2 items + " +N")
                $productsByOrderId = [];
                try {
                    $orderIds = array_values(array_filter(array_map(function($o) {
                        return isset($o['id']) ? (int) $o['id'] : 0;
                    }, $orders)));
                    if (!empty($orderIds)) {
                        $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
                        $itemsStmt = $analytics->getConnection()->prepare(
                            "SELECT order_id, product_name, quantity
                             FROM order_items
                             WHERE order_id IN ($placeholders)
                             ORDER BY id ASC"
                        );
                        $itemsStmt->execute($orderIds);
                        while ($row = $itemsStmt->fetch(PDO::FETCH_ASSOC)) {
                            $oid = (int) ($row['order_id'] ?? 0);
                            if (!$oid) continue;
                            if (!isset($productsByOrderId[$oid])) $productsByOrderId[$oid] = [];
                            $name = trim((string)($row['product_name'] ?? ''));
                            $qty  = (int)($row['quantity'] ?? 0);
                            if ($name === '') $name = '—';
                            $productsByOrderId[$oid][] = ['name' => $name, 'qty' => max(1, $qty)];
                        }
                    }
                } catch (Exception $e) {
                    // Best-effort; keep endpoint functional even if schema differs
                    $productsByOrderId = [];
                }

                $normalized = array_map(function($order) use ($productsByOrderId) {
                    $oid = isset($order['id']) ? (int) $order['id'] : 0;
                    $items = $oid && isset($productsByOrderId[$oid]) ? $productsByOrderId[$oid] : [];
                    $visible = array_slice($items, 0, 2);
                    $extraCount = max(0, count($items) - count($visible));
                    $productParts = array_map(function($it) {
                        $n = (string)($it['name'] ?? '—');
                        $q = (int)($it['qty'] ?? 1);
                        return $n . ' × ' . max(1, $q);
                    }, $visible);
                    $productsSummary = trim(implode(', ', $productParts));
                    if ($extraCount > 0) {
                        $productsSummary .= ' +' . $extraCount;
                    }
                    return [
                        'id' => $order['id'],
                        'order_number' => $order['order_number'],
                        'customer_name' => $order['customer_name'],
                        'customer_email' => $order['customer_email'],
                        'total' => (float) $order['total_amount'],
                        'status' => $order['payment_status'] ?? $order['status'],
                        'date' => $order['created_at'] ? date('Y-m-d', strtotime($order['created_at'])) : null,
                        'products' => $productsSummary
                    ];
                }, $orders);

                echo json_encode([
                    'success' => true,
                    'orders' => $normalized,
                ]);
                exit();

            default:
                throw new Exception('Unknown action');
        }
    }

    // Read JSON payload for POST requests
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) {
        $data = $_POST;
    }

    $events = $data['events'] ?? [];
    $response['processed_events'] = 0;
    $response['errors'] = [];

    // Validate required fields
    if (!isset($data['events']) || !is_array($data['events'])) {
        throw new Exception('Events array is required');
    }
    
    if (!isset($data['session_id']) || empty($data['session_id'])) {
        throw new Exception('Session ID is required');
    }
    
    // Process each event
    foreach ($data['events'] as $event) {
        try {
            // Validate event structure
            if (!isset($event['event_type']) || empty($event['event_type'])) {
                $errors[] = 'Event type is required';
                continue;
            }
            
            $eventType = $event['event_type'];
            $eventData = $event['event_data'] ?? [];
            $sessionId = $data['session_id'];
            
            // Sanitize and validate event data
            $eventData = sanitizeEventData($eventData);
            
            // Track the event based on type
            switch ($eventType) {
                case 'page_view':
                    $analytics->trackPageView(
                        $eventData['page_url'] ?? '',
                        $eventData['page_title'] ?? null,
                        $sessionId
                    );
                    break;
                    
                case 'product_view':
                    if (isset($eventData['product_id'])) {
                        $analytics->trackProductView($eventData['product_id'], $sessionId);
                    }
                    break;
                    
                case 'add_to_cart':
                    if (isset($eventData['product_id'])) {
                        $analytics->trackAddToCart(
                            $eventData['product_id'],
                            $eventData['quantity'] ?? 1,
                            $sessionId
                        );
                    }
                    break;
                    
                case 'search':
                    if (isset($eventData['search_term'])) {
                        $analytics->trackSearch(
                            $eventData['search_term'],
                            $eventData['results_count'] ?? 0,
                            $sessionId
                        );
                    }
                    break;
                    
                case 'button_click':
                    $analytics->trackButtonClick(
                        $eventData['button_label'] ?? 'Unknown Button',
                        $eventData['button_selector'] ?? null,
                        $sessionId
                    );
                    break;
                    
                case 'form_submit':
                    $analytics->trackEvent('form_submit', $eventData, $sessionId);
                    break;
                    
                case 'funnel_step':
                    if (isset($eventData['step'])) {
                        $analytics->trackFunnelStep($eventData['step'], $sessionId);
                    }
                    break;
                    
                case 'cart_update':
                    // Track cart abandonment
                    if (isset($eventData['cart_data'])) {
                        $analytics->trackAbandonedCart(
                            $sessionId,
                            $eventData['cart_data'],
                            $eventData['customer_email'] ?? null,
                            $eventData['customer_phone'] ?? null
                        );
                    }
                    break;
                    
                case 'time_on_page':
                    // Update page time analytics
                    if (isset($eventData['time_on_page'])) {
                        // Use the page_url from event_data (sent by JavaScript)
                        $pageUrl = $eventData['page_url'] ?? '';
                        if (!empty($pageUrl)) {
                            $analytics->updatePageTime(
                                $pageUrl,
                                $eventData['time_on_page'],
                                $sessionId
                            );
                        }
                    }
                    break;
                    
                default:
                    // Generic event tracking
                    $analytics->trackEvent($eventType, $eventData, $sessionId);
                    break;
            }
            
            $processedEvents++;
            
        } catch (Exception $e) {
            $errors[] = "Error processing event: " . $e->getMessage();
        }
    }
    
    // Return success response
    echo json_encode([
        'success' => true,
        'processed_events' => $processedEvents,
        'total_events' => count($data['events']),
        'errors' => $errors,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

// Handle other POST requests that don't match the above patterns
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid request format'
    ]);
    exit();
}

// Handle unsupported methods
http_response_code(405);
echo json_encode([
    'success' => false,
    'error' => 'Method not allowed'
]);
exit();

/**
 * Sanitize event data to prevent XSS and ensure data integrity
 */
function sanitizeEventData($data) {
    if (!is_array($data)) {
        return [];
    }
    
    $sanitized = [];
    
    foreach ($data as $key => $value) {
        // Sanitize key
        $key = preg_replace('/[^a-zA-Z0-9_]/', '', $key);
        
        if (is_string($value)) {
            // Sanitize string values
            $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            $value = trim($value);
            
            // Limit string length
            if (strlen($value) > 1000) {
                $value = substr($value, 0, 1000);
            }
        } elseif (is_numeric($value)) {
            // Ensure numeric values are valid
            $value = is_float($value) ? (float)$value : (int)$value;
        } elseif (is_array($value)) {
            // Recursively sanitize arrays
            $value = sanitizeEventData($value);
        } else {
            // Skip other types
            continue;
        }
        
        $sanitized[$key] = $value;
    }
    
    return $sanitized;
}
?>
