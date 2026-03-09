<?php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/settings.php';
if (file_exists(__DIR__ . '/plan_helper.php')) {
    require_once __DIR__ . '/plan_helper.php';
}

class AnalyticsHelper {
    private $conn;
    private $settings;
    private $storeId;
    private $orderLimit;
    private static $hasStoreIdAnalyticsEvents = null;
    private static $hasStoreIdUserFunnel = null;
    private static $hasStoreIdSearchAnalytics = null;

    private function analyticsEventsHasStoreId() {
        if (self::$hasStoreIdAnalyticsEvents === null) {
            try {
                $stmt = $this->conn->query("SHOW COLUMNS FROM analytics_events LIKE 'store_id'");
                self::$hasStoreIdAnalyticsEvents = (bool) $stmt->fetch();
            } catch (Exception $e) {
                self::$hasStoreIdAnalyticsEvents = false;
            }
        }
        return self::$hasStoreIdAnalyticsEvents;
    }

    private function userFunnelHasStoreId() {
        if (self::$hasStoreIdUserFunnel === null) {
            try {
                $stmt = $this->conn->query("SHOW COLUMNS FROM user_funnel LIKE 'store_id'");
                self::$hasStoreIdUserFunnel = (bool) $stmt->fetch();
            } catch (Exception $e) {
                self::$hasStoreIdUserFunnel = false;
            }
        }
        return self::$hasStoreIdUserFunnel;
    }

    public function __construct($conn = null, $storeId = null) {
        if ($conn) {
            $this->conn = $conn;
        } else {
            $database = new Database();
            $this->conn = $database->getConnection();
        }
        $this->storeId = $storeId !== null ? (int) $storeId : (class_exists('StoreContext') && StoreContext::isResolved() ? StoreContext::getId() : 1);
        $this->settings = new Settings($this->conn, $this->storeId);
        $this->orderLimit = class_exists('PlanHelper') ? PlanHelper::getOrderLimit($this->conn, $this->storeId) : null;
    }
    
    /**
     * Get database connection
     */
    public function getConnection() {
        return $this->conn;
    }

    /**
     * Get recent orders for the current store (for dashboard), respecting plan order_limit.
     */
    public function getRecentOrders($limit = 6) {
        $limit = (int) $limit;
        if ($this->orderLimit !== null) {
            $sub = "SELECT id FROM (SELECT id FROM orders WHERE store_id = ? ORDER BY created_at DESC LIMIT " . (int) $this->orderLimit . ") t";
            $query = "SELECT id, order_number, customer_name, customer_email, total_amount, status, payment_status, created_at
                      FROM orders
                      WHERE store_id = ? AND id IN ($sub)
                      ORDER BY created_at DESC
                      LIMIT $limit";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$this->storeId, $this->storeId]);
        } else {
            $query = "SELECT id, order_number, customer_name, customer_email, total_amount, status, payment_status, created_at
                      FROM orders
                      WHERE store_id = ?
                      ORDER BY created_at DESC
                      LIMIT $limit";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$this->storeId]);
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Track a general event
     */
    public function trackEvent($eventType, $eventData = [], $sessionId = null, $userIdentifier = null, $customPageUrl = null) {
        try {
            $sessionId = $sessionId ?: session_id();
            $pageUrl = $customPageUrl ?: $_SERVER['REQUEST_URI'] ?? '';
            $referrer = $_SERVER['HTTP_REFERER'] ?? '';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $ipAddress = $this->getClientIP();
            
            if ($this->analyticsEventsHasStoreId()) {
                $query = "INSERT INTO analytics_events (store_id, event_type, event_data, session_id, user_identifier, page_url, referrer, user_agent, ip_address) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $this->conn->prepare($query);
                $stmt->execute([
                    $this->storeId,
                    $eventType,
                    json_encode($eventData),
                    $sessionId,
                    $userIdentifier,
                    $pageUrl,
                    $referrer,
                    $userAgent,
                    $ipAddress
                ]);
            } else {
                $query = "INSERT INTO analytics_events (event_type, event_data, session_id, user_identifier, page_url, referrer, user_agent, ip_address) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $this->conn->prepare($query);
                $stmt->execute([
                    $eventType,
                    json_encode($eventData),
                    $sessionId,
                    $userIdentifier,
                    $pageUrl,
                    $referrer,
                    $userAgent,
                    $ipAddress
                ]);
            }
            
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            error_log("Analytics tracking error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Track product view
     */
    public function trackProductView($productId, $sessionId = null) {
        $this->trackEvent('product_view', ['product_id' => $productId], $sessionId);
        $this->updateProductAnalytics($productId, 'view');
    }
    
    /**
     * Track add to cart
     */
    public function trackAddToCart($productId, $quantity = 1, $sessionId = null) {
        $this->trackEvent('add_to_cart', [
            'product_id' => $productId,
            'quantity' => $quantity
        ], $sessionId);
        $this->updateProductAnalytics($productId, 'add_to_cart');
        $this->trackFunnelStep('add_to_cart', $sessionId);
    }
    
    /**
     * Track search query
     */
    public function trackSearch($searchTerm, $resultsCount = 0, $sessionId = null) {
        $this->trackEvent('search', [
            'search_term' => $searchTerm,
            'results_count' => $resultsCount
        ], $sessionId);
        
        // Store in search analytics (store-scoped when column exists)
        try {
            if (self::$hasStoreIdSearchAnalytics === null) {
                $chk = $this->conn->query("SHOW COLUMNS FROM search_analytics LIKE 'store_id'");
                self::$hasStoreIdSearchAnalytics = (bool) $chk->fetch();
            }
            if (self::$hasStoreIdSearchAnalytics) {
                $query = "INSERT INTO search_analytics (store_id, search_term, results_count, session_id) VALUES (?, ?, ?, ?)";
                $stmt = $this->conn->prepare($query);
                $stmt->execute([$this->storeId, $searchTerm, $resultsCount, $sessionId ?: session_id()]);
            } else {
                $query = "INSERT INTO search_analytics (search_term, results_count, session_id) VALUES (?, ?, ?)";
                $stmt = $this->conn->prepare($query);
                $stmt->execute([$searchTerm, $resultsCount, $sessionId ?: session_id()]);
            }
        } catch (PDOException $e) {
            error_log("Search analytics insert error: " . $e->getMessage());
        }
    }
    
    /**
     * Track funnel step
     */
    public function trackFunnelStep($step, $sessionId = null) {
        $sessionId = $sessionId ?: session_id();
        
        // Map step names to column names
        $columnMap = [
            'product_view' => 'product_view',
            'add_to_cart' => 'add_to_cart',
            'checkout_start' => 'checkout_start',
            'payment_start' => 'payment_start',
            'purchase_complete' => 'purchase_complete'
        ];
        
        $columnName = $columnMap[$step] ?? null;
        if (!$columnName) {
            return; // Unknown step, do nothing
        }
        
        $useStoreId = $this->userFunnelHasStoreId();
        $checkParams = [$sessionId];
        if ($useStoreId) {
            $checkQuery = "SELECT id FROM user_funnel WHERE session_id = ? AND store_id = ?";
            $checkParams[] = $this->storeId;
        } else {
            $checkQuery = "SELECT id FROM user_funnel WHERE session_id = ?";
        }
        $checkStmt = $this->conn->prepare($checkQuery);
        $checkStmt->execute($checkParams);

        if ($checkStmt->rowCount() > 0) {
            switch ($step) {
                case 'product_view':
                    $updateQuery = "UPDATE user_funnel SET product_view = TRUE, updated_at = CURRENT_TIMESTAMP WHERE session_id = ?" . ($useStoreId ? " AND store_id = ?" : "");
                    break;
                case 'add_to_cart':
                    $updateQuery = "UPDATE user_funnel SET add_to_cart = TRUE, updated_at = CURRENT_TIMESTAMP WHERE session_id = ?" . ($useStoreId ? " AND store_id = ?" : "");
                    break;
                case 'checkout_start':
                    $updateQuery = "UPDATE user_funnel SET checkout_start = TRUE, updated_at = CURRENT_TIMESTAMP WHERE session_id = ?" . ($useStoreId ? " AND store_id = ?" : "");
                    break;
                case 'payment_start':
                    $updateQuery = "UPDATE user_funnel SET payment_start = TRUE, updated_at = CURRENT_TIMESTAMP WHERE session_id = ?" . ($useStoreId ? " AND store_id = ?" : "");
                    break;
                case 'purchase_complete':
                    $updateQuery = "UPDATE user_funnel SET purchase_complete = TRUE, updated_at = CURRENT_TIMESTAMP WHERE session_id = ?" . ($useStoreId ? " AND store_id = ?" : "");
                    break;
                default:
                    return;
            }
            $updateParams = [$sessionId];
            if ($useStoreId) $updateParams[] = $this->storeId;
            $updateStmt = $this->conn->prepare($updateQuery);
            $updateStmt->execute($updateParams);
        } else {
            if ($useStoreId) {
                $insertQuery = "INSERT INTO user_funnel (store_id, session_id, product_view, add_to_cart, checkout_start, payment_start, purchase_complete) 
                               VALUES (?, ?, ?, ?, ?, ?, ?)";
                $values = [
                    $this->storeId,
                    $sessionId,
                    $step === 'product_view' ? 1 : 0,
                    $step === 'add_to_cart' ? 1 : 0,
                    $step === 'checkout_start' ? 1 : 0,
                    $step === 'payment_start' ? 1 : 0,
                    $step === 'purchase_complete' ? 1 : 0
                ];
            } else {
                $insertQuery = "INSERT INTO user_funnel (session_id, product_view, add_to_cart, checkout_start, payment_start, purchase_complete) 
                               VALUES (?, ?, ?, ?, ?, ?)";
                $values = [
                    $sessionId,
                    $step === 'product_view' ? 1 : 0,
                    $step === 'add_to_cart' ? 1 : 0,
                    $step === 'checkout_start' ? 1 : 0,
                    $step === 'payment_start' ? 1 : 0,
                    $step === 'purchase_complete' ? 1 : 0
                ];
            }
            $insertStmt = $this->conn->prepare($insertQuery);
            $insertStmt->execute($values);
        }
    }
    
    /**
     * Track button click
     */
    public function trackButtonClick($buttonLabel, $buttonSelector = null, $sessionId = null) {
        $this->trackEvent('button_click', [
            'button_label' => $buttonLabel,
            'button_selector' => $buttonSelector
        ], $sessionId);
        
        // Store in button analytics
        $query = "INSERT INTO button_analytics (button_label, button_selector, page_url, session_id) VALUES (?, ?, ?, ?)";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([
            $buttonLabel,
            $buttonSelector,
            $_SERVER['REQUEST_URI'] ?? '',
            $sessionId ?: session_id()
        ]);
    }
    
    /**
     * Track page view
     */
    public function trackPageView($pageUrl, $pageTitle = null, $sessionId = null) {
        $sessionId = $sessionId ?: session_id();
        
        // Normalize the URL for consistent tracking
        $normalizedUrl = $this->normalizeUrl($pageUrl);
        if ($this->isNonPageUrl($normalizedUrl)) return false;

        $this->trackEvent('page_view', [
            'page_url' => $normalizedUrl,
            'page_title' => $pageTitle
        ], $sessionId, null, $normalizedUrl);
        
        // Update page analytics with proper unique views tracking
        $query = "INSERT INTO page_analytics (page_url, page_title, view_count, unique_views, avg_time_on_page) 
                  VALUES (?, ?, 1, 1, 0) 
                  ON DUPLICATE KEY UPDATE 
                  view_count = view_count + 1,
                  unique_views = (SELECT COUNT(DISTINCT session_id) FROM analytics_events WHERE event_type = 'page_view' AND page_url = ?),
                  last_updated = CURRENT_TIMESTAMP";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$normalizedUrl, $pageTitle, $normalizedUrl]);
    }
    
    /**
     * Update page time on page
     */
    public function updatePageTime($pageUrl, $timeOnPage, $sessionId = null) {
        $sessionId = $sessionId ?: session_id();
        
        // Normalize URL to match the format used by trackPageView
        $normalizedUrl = $this->normalizeUrl($pageUrl);
        
        // FIRST: Store the event in analytics_events with the correct URL
        $this->trackEvent('time_on_page', [
            'page_url' => $normalizedUrl,
            'time_on_page' => $timeOnPage
        ], $sessionId, null, $normalizedUrl);
        
        // Then ensure the page exists in page_analytics
        $checkQuery = "INSERT INTO page_analytics (page_url, page_title, view_count, unique_views, avg_time_on_page) 
                       VALUES (?, 'Unknown Page', 0, 0, 0) 
                       ON DUPLICATE KEY UPDATE page_url = page_url";
        $checkStmt = $this->conn->prepare($checkQuery);
        $checkStmt->execute([$normalizedUrl]);
        
        // Update average time on page
        $query = "UPDATE page_analytics 
                  SET avg_time_on_page = (
                      SELECT AVG(JSON_EXTRACT(event_data, '$.time_on_page')) 
                      FROM analytics_events 
                      WHERE event_type = 'time_on_page' 
                      AND page_url = ? 
                      AND JSON_EXTRACT(event_data, '$.time_on_page') IS NOT NULL
                  )
                  WHERE page_url = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$normalizedUrl, $normalizedUrl]);
    }
    
    /**
     * Whether the URL is an API/ajax endpoint, not a real page (should not be tracked or shown in Page Engagement).
     */
    private function isNonPageUrl($url) {
        if ($url === null || $url === '') return true;
        $u = (string) $url;
        $nonPagePatterns = ['get_cart_count', 'action=get_cart_count', 'newsletter_subscribe', 'action=newsletter'];
        foreach ($nonPagePatterns as $pattern) {
            if (stripos($u, $pattern) !== false) return true;
        }
        return false;
    }

    /**
     * Normalize URL to match the format used by trackPageView
     */
    private function normalizeUrl($url) {
        // If it's a full URL, extract the path and query
        if (strpos($url, 'http') === 0) {
            $parsed = parse_url($url);
            $url = $parsed['path'] . (isset($parsed['query']) ? '?' . $parsed['query'] : '');
        }
        
        // Normalize home page URLs to a consistent format
        if ($url === '/' || $url === '/index.php' || $url === '/ecomerce_multi_temp/index.php' || 
            $url === '/ecomerce_multi_temp/index.php?page=home' || 
            strpos($url, '/index.php?page=home') !== false) {
            return '/ecomerce_multi_temp/index.php?page=home';
        }
        
        // Normalize shop page URLs (remove category parameters)
        if (strpos($url, '/index.php?page=shop') !== false) {
            // Extract just the page parameter, ignore category and other filters
            if (strpos($url, 'page=shop') !== false) {
                return '/ecomerce_multi_temp/index.php?page=shop';
            }
        }
        
        // Normalize other page URLs (remove unnecessary parameters)
        if (strpos($url, '/index.php?page=') !== false) {
            // Extract just the main page parameter
            preg_match('/\/index\.php\?page=([^&]+)/', $url, $matches);
            if (isset($matches[1])) {
                $page = $matches[1];
                return "/ecomerce_multi_temp/index.php?page={$page}";
            }
        }
        
        return $url;
    }
    
    /**
     * Update product analytics
     */
    private function updateProductAnalytics($productId, $action) {
        // First, ensure the record exists
        $query = "INSERT INTO product_analytics (product_id, views_count, add_to_cart_count, purchase_count, revenue) 
                  VALUES (?, 0, 0, 0, 0.00) 
                  ON DUPLICATE KEY UPDATE product_id = product_id";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$productId]);
        
        // Then update the specific column based on action
        switch ($action) {
            case 'view':
                $updateQuery = "UPDATE product_analytics 
                                SET views_count = views_count + 1, 
                                    last_updated = CURRENT_TIMESTAMP 
                                WHERE product_id = ?";
                break;
            case 'add_to_cart':
                $updateQuery = "UPDATE product_analytics 
                                SET add_to_cart_count = add_to_cart_count + 1, 
                                    last_updated = CURRENT_TIMESTAMP 
                                WHERE product_id = ?";
                break;
            case 'purchase':
                $updateQuery = "UPDATE product_analytics 
                                SET purchase_count = purchase_count + 1, 
                                    last_updated = CURRENT_TIMESTAMP 
                                WHERE product_id = ?";
                break;
            default:
                return; // Unknown action, do nothing
        }
        
        $updateStmt = $this->conn->prepare($updateQuery);
        $updateStmt->execute([$productId]);
    }
    
    /**
     * Track abandoned cart (store-scoped when store_id column exists)
     */
    public function trackAbandonedCart($sessionId, $cartData, $customerEmail = null, $customerPhone = null, $customerName = null) {
        $cartValue = 0;
        if (is_array($cartData)) {
            foreach ($cartData as $item) {
                $cartValue += ($item['price'] ?? 0) * ($item['quantity'] ?? 0);
            }
        }
        $storeId = (int) $this->storeId;
        try {
            $chk = $this->conn->query("SHOW COLUMNS FROM abandoned_carts LIKE 'store_id'");
            $hasStoreId = (bool) $chk->fetch();
        } catch (PDOException $e) { $hasStoreId = false; }
        if ($hasStoreId) {
            $query = "INSERT INTO abandoned_carts (store_id, session_id, customer_email, customer_phone, customer_name, cart_data, cart_value) 
                      VALUES (?, ?, ?, ?, ?, ?, ?) 
                      ON DUPLICATE KEY UPDATE 
                      cart_data = VALUES(cart_data),
                      cart_value = VALUES(cart_value),
                      customer_email = VALUES(customer_email),
                      customer_phone = VALUES(customer_phone),
                      customer_name = VALUES(customer_name)";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$storeId, $sessionId, $customerEmail, $customerPhone, $customerName, json_encode($cartData), $cartValue]);
        } else {
            $query = "INSERT INTO abandoned_carts (session_id, customer_email, customer_phone, customer_name, cart_data, cart_value) 
                      VALUES (?, ?, ?, ?, ?, ?) 
                      ON DUPLICATE KEY UPDATE 
                      cart_data = VALUES(cart_data),
                      cart_value = VALUES(cart_value),
                      customer_email = VALUES(customer_email),
                      customer_phone = VALUES(customer_phone),
                      customer_name = VALUES(customer_name)";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$sessionId, $customerEmail, $customerPhone, $customerName, json_encode($cartData), $cartValue]);
        }
    }
    
    /**
     * Mark cart as completed (store-scoped when store_id exists)
     */
    public function markCartCompleted($sessionId) {
        try {
            $chk = $this->conn->query("SHOW COLUMNS FROM abandoned_carts LIKE 'store_id'");
            $hasStoreId = (bool) $chk->fetch();
        } catch (PDOException $e) { $hasStoreId = false; }
        if ($hasStoreId) {
            try {
                $schk = $this->conn->query("SHOW COLUMNS FROM abandoned_carts LIKE 'status'");
                $hasStatus = (bool) $schk->fetch();
            } catch (PDOException $e) { $hasStatus = false; }
            $setClause = $hasStatus ? "completed_at = CURRENT_TIMESTAMP, status = 'completed'" : "completed_at = CURRENT_TIMESTAMP";
            $query = "UPDATE abandoned_carts SET $setClause WHERE store_id = ? AND session_id = ? AND completed_at IS NULL";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([(int) $this->storeId, $sessionId]);
        } else {
            $query = "UPDATE abandoned_carts SET completed_at = CURRENT_TIMESTAMP WHERE session_id = ? AND completed_at IS NULL";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$sessionId]);
        }
    }
    
    /**
     * Get abandoned carts (store-scoped, excludes completed, marks >30min as abandoned)
     */
    public function getAbandonedCarts($days = 1, $limit = 50) {
        try {
            $chk = $this->conn->query("SHOW COLUMNS FROM abandoned_carts LIKE 'store_id'");
            $hasStoreId = (bool) $chk->fetch();
        } catch (PDOException $e) { $hasStoreId = false; }
        $storeId = (int) $this->storeId;
        $limit = (int) $limit;
        try {
            if ($hasStoreId) {
                $query = "SELECT * FROM abandoned_carts 
                          WHERE store_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY) 
                          AND (completed_at IS NULL AND (status IS NULL OR status != 'completed'))
                          ORDER BY created_at DESC 
                          LIMIT " . $limit;
                $stmt = $this->conn->prepare($query);
                $stmt->execute([$storeId, $days]);
            } else {
                $query = "SELECT * FROM abandoned_carts 
                          WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY) 
                          AND completed_at IS NULL 
                          ORDER BY created_at DESC 
                          LIMIT " . $limit;
                $stmt = $this->conn->prepare($query);
                $stmt->execute([$days]);
            }
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }
    
    public function updateCustomerInfo($cartId, $email, $phone = null, $name = null) {
        try {
            $query = "UPDATE abandoned_carts 
                      SET customer_email = ?, customer_phone = ?, customer_name = ?, updated_at = CURRENT_TIMESTAMP 
                      WHERE id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$email, $phone, $name, $cartId]);
            return true;
        } catch (PDOException $e) {
            throw new Exception("Failed to update customer info: " . $e->getMessage());
        }
    }
    
    public function convertCartToOrder($cartId) {
        try {
            // Get cart details
            $query = "SELECT * FROM abandoned_carts WHERE id = ? AND completed_at IS NULL";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$cartId]);
            $cart = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$cart) {
                throw new Exception("Cart not found or already completed");
            }
            
            // Create order record (scoped to store)
            $orderQuery = "INSERT INTO orders (store_id, customer_email, customer_phone, customer_name, cart_data, total_amount, status, created_at) 
                          VALUES (?, ?, ?, ?, ?, ?, 'pending', CURRENT_TIMESTAMP)";
            $orderStmt = $this->conn->prepare($orderQuery);
            $orderStmt->execute([
                $this->storeId,
                $cart['customer_email'],
                $cart['customer_phone'],
                $cart['customer_name'] ?? null,
                $cart['cart_data'],
                $cart['cart_value']
            ]);
            
            $orderId = $this->conn->lastInsertId();
            
            // Mark cart as completed
            $this->markCartCompleted($cart['session_id']);
            
            return $orderId;
        } catch (PDOException $e) {
            throw new Exception("Failed to convert cart to order: " . $e->getMessage());
        }
    }
    
    /**
     * Get daily stats
     */
    public function getDailyStats($startDate, $endDate) {
        $query = "SELECT * FROM daily_stats 
                  WHERE stat_date BETWEEN ? AND ? 
                  ORDER BY stat_date DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$startDate, $endDate]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get product analytics
     */
    public function getProductAnalytics($productId = null, $limit = 50) {
        try {
            if ($productId) {
                $query = "SELECT pa.*, p.name, p.sku, p.price 
                          FROM product_analytics pa 
                          JOIN products p ON pa.product_id = p.id AND p.store_id = ?
                          WHERE pa.product_id = ?";
                $stmt = $this->conn->prepare($query);
                $stmt->execute([$this->storeId, $productId]);
                return $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $query = "SELECT pa.*, p.name, p.sku, p.price 
                          FROM product_analytics pa 
                          JOIN products p ON pa.product_id = p.id AND p.store_id = ?
                          ORDER BY pa.views_count DESC 
                          LIMIT " . intval($limit);
                $stmt = $this->conn->prepare($query);
                $stmt->execute([$this->storeId]);
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (PDOException $e) {
            return $productId ? null : [];
        }
    }
    
    /**
     * Get top search terms (store-scoped when search_analytics has store_id)
     */
    public function getTopSearchTerms($limit = 20, $days = 30) {
        try {
            if (self::$hasStoreIdSearchAnalytics === null) {
                $chk = $this->conn->query("SHOW COLUMNS FROM search_analytics LIKE 'store_id'");
                self::$hasStoreIdSearchAnalytics = (bool) $chk->fetch();
            }
            $whereStore = self::$hasStoreIdSearchAnalytics ? " AND store_id = ?" : "";
            $params = [$days];
            if (self::$hasStoreIdSearchAnalytics) $params[] = $this->storeId;
            $query = "SELECT search_term, COUNT(*) as search_count, AVG(results_count) as avg_results 
                      FROM search_analytics 
                      WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY) $whereStore
                      GROUP BY search_term 
                      ORDER BY search_count DESC 
                      LIMIT " . intval($limit);
            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Get page engagement for dashboard (store-scoped from analytics_events when store_id exists; else from page_analytics)
     */
    public function getPageEngagement($limit = 8, $days = 30) {
        $limit = (int) $limit;
        $days = (int) $days;
        try {
            if ($this->analyticsEventsHasStoreId()) {
                $query = "SELECT page_url,
                          COALESCE(JSON_UNQUOTE(JSON_EXTRACT(MIN(CASE WHEN event_data IS NOT NULL AND event_data != '' THEN event_data END), '$.page_title')), 'Unknown') as page_title,
                          COUNT(*) as view_count,
                          COUNT(DISTINCT session_id) as unique_views,
                          0 as avg_time_on_page
                          FROM analytics_events
                          WHERE store_id = ? AND event_type = 'page_view' AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                          AND (page_url NOT LIKE '%get_cart_count%' AND page_url NOT LIKE '%action=get_cart_count%' AND page_url NOT LIKE '%newsletter_subscribe%')
                          GROUP BY page_url
                          ORDER BY view_count DESC
                          LIMIT " . $limit;
                $stmt = $this->conn->prepare($query);
                $stmt->execute([$this->storeId, $days]);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                return array_values(array_filter($rows, function ($row) {
                    return !$this->isNonPageUrl($row['page_url'] ?? '');
                }));
            }
            $query = "SELECT page_url, page_title, view_count, unique_views, COALESCE(avg_time_on_page, 0) as avg_time_on_page
                      FROM page_analytics
                      WHERE (page_url NOT LIKE '%get_cart_count%' AND page_url NOT LIKE '%action=get_cart_count%' AND page_url NOT LIKE '%newsletter_subscribe%')
                      ORDER BY view_count DESC
                      LIMIT " . $limit;
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return array_values(array_filter($rows, function ($row) {
                return !$this->isNonPageUrl($row['page_url'] ?? '');
            }));
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Get conversion funnel data
     */
    public function getConversionFunnel($days = 30) {
        try {
            $whereStore = $this->userFunnelHasStoreId() ? " AND store_id = ?" : "";
            $query = "SELECT 
                        COUNT(*) as total_sessions,
                        SUM(CASE WHEN product_view = TRUE THEN 1 ELSE 0 END) as product_views,
                        SUM(CASE WHEN add_to_cart = TRUE THEN 1 ELSE 0 END) as add_to_cart,
                        SUM(CASE WHEN checkout_start = TRUE THEN 1 ELSE 0 END) as checkout_start,
                        SUM(CASE WHEN payment_start = TRUE THEN 1 ELSE 0 END) as payment_start,
                        SUM(CASE WHEN purchase_complete = TRUE THEN 1 ELSE 0 END) as purchase_complete
                      FROM user_funnel 
                      WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY) $whereStore";
            $stmt = $this->conn->prepare($query);
            if ($this->userFunnelHasStoreId()) {
                $stmt->execute([$days, $this->storeId]);
            } else {
                $stmt->execute([$days]);
            }
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // Return empty data if table doesn't exist
            return [
                'total_sessions' => 0,
                'product_views' => 0,
                'add_to_cart' => 0,
                'checkout_start' => 0,
                'payment_start' => 0,
                'purchase_complete' => 0
            ];
        }
    }
    
    /**
     * Get revenue metrics (scoped to store, and to plan order_limit when set).
     * Uses payment_status = 'paid' when the column exists; otherwise counts all orders (backward compatibility).
     */
    public function getRevenueMetrics($startDate, $endDate) {
        $visibleSql = '';
        $params = [$this->storeId, $startDate, $endDate];
        if ($this->orderLimit !== null) {
            $visibleSql = " AND id IN (SELECT id FROM (SELECT id FROM orders WHERE store_id = ? ORDER BY created_at DESC LIMIT " . (int) $this->orderLimit . ") t) ";
            $params[] = $this->storeId;
        }
        $hasPaymentStatus = false;
        try {
            $chk = $this->conn->query("SHOW COLUMNS FROM orders LIKE 'payment_status'");
            $hasPaymentStatus = (bool) $chk->fetch();
        } catch (PDOException $e) {}
        $paidFilter = $hasPaymentStatus ? " AND payment_status = 'paid'" : "";
        $query = "SELECT 
                    COUNT(*) as total_orders,
                    COALESCE(SUM(total_amount), 0) as total_revenue,
                    AVG(total_amount) as avg_order_value,
                    COUNT(DISTINCT customer_email) as unique_customers
                  FROM orders 
                  WHERE store_id = ? AND created_at BETWEEN ? AND ? $paidFilter $visibleSql";
        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get overview stats for dashboard
     */
    public function getOverviewStats($days = 30) {
        $startDate = date('Y-m-d', strtotime("-{$days} days"));
        $endDate = date('Y-m-d');
        
        // Revenue metrics
        $revenue = $this->getRevenueMetrics($startDate, $endDate);
        
        // Conversion funnel
        $funnel = $this->getConversionFunnel($days);
        
        // Calculate conversion rate
        $conversionRate = 0;
        if ($funnel['product_views'] > 0) {
            $conversionRate = ($funnel['purchase_complete'] / $funnel['product_views']) * 100;
        }
        
        return [
            'total_revenue' => $revenue['total_revenue'] ?? 0,
            'total_orders' => $revenue['total_orders'] ?? 0,
            'avg_order_value' => $revenue['avg_order_value'] ?? 0,
            'conversion_rate' => round($conversionRate, 2),
            'total_sessions' => $funnel['total_sessions'] ?? 0,
            'product_views' => $funnel['product_views'] ?? 0,
            'add_to_cart' => $funnel['add_to_cart'] ?? 0,
            'checkout_start' => $funnel['checkout_start'] ?? 0,
            'purchase_complete' => $funnel['purchase_complete'] ?? 0
        ];
    }
    
    /**
     * Generate daily stats aggregation
     */
    public function generateDailyStats($date = null) {
        $date = $date ?: date('Y-m-d', strtotime('-1 day'));
        
        // Get revenue data for the day (scoped to store)
        $revenueQuery = "SELECT 
                           COUNT(*) as total_orders,
                           SUM(total_amount) as total_revenue,
                           AVG(total_amount) as avg_order_value,
                           COUNT(DISTINCT customer_email) as unique_customers
                         FROM orders 
                         WHERE store_id = ? AND DATE(created_at) = ? AND payment_status = 'paid'";
        $stmt = $this->conn->prepare($revenueQuery);
        $stmt->execute([$this->storeId, $date]);
        $revenue = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get page views and unique visitors (scoped to store when store_id column exists)
        $viewsWhereStore = $this->analyticsEventsHasStoreId() ? " AND store_id = ?" : "";
        $viewsQuery = "SELECT 
                         COUNT(*) as page_views,
                         COUNT(DISTINCT session_id) as unique_visitors
                       FROM analytics_events 
                       WHERE DATE(created_at) = ? AND event_type = 'page_view' $viewsWhereStore";
        $stmt = $this->conn->prepare($viewsQuery);
        if ($this->analyticsEventsHasStoreId()) {
            $stmt->execute([$date, $this->storeId]);
        } else {
            $stmt->execute([$date]);
        }
        $views = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Calculate conversion rate
        $conversionRate = 0;
        if ($views['page_views'] > 0) {
            $conversionRate = (($revenue['total_orders'] ?? 0) / $views['page_views']) * 100;
        }
        
        // Insert or update daily stats
        $insertQuery = "INSERT INTO daily_stats 
                        (stat_date, total_revenue, total_orders, avg_order_value, 
                         new_customers, returning_customers, conversion_rate, page_views, unique_visitors) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?) 
                        ON DUPLICATE KEY UPDATE 
                        total_revenue = VALUES(total_revenue),
                        total_orders = VALUES(total_orders),
                        avg_order_value = VALUES(avg_order_value),
                        new_customers = VALUES(new_customers),
                        returning_customers = VALUES(returning_customers),
                        conversion_rate = VALUES(conversion_rate),
                        page_views = VALUES(page_views),
                        unique_visitors = VALUES(unique_visitors)";
        
        $stmt = $this->conn->prepare($insertQuery);
        $stmt->execute([
            $date,
            $revenue['total_revenue'] ?? 0,
            $revenue['total_orders'] ?? 0,
            $revenue['avg_order_value'] ?? 0,
            $revenue['unique_customers'] ?? 0,
            0, // returning customers - would need more complex logic
            $conversionRate,
            $views['page_views'] ?? 0,
            $views['unique_visitors'] ?? 0
        ]);
        
        return $stmt->rowCount() > 0;
    }
    
    /**
     * Get client IP address
     */
    private function getClientIP() {
        $ipKeys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Clean up old analytics data
     */
    public function cleanupOldData($retentionDays = 90) {
        $cutoffDate = date('Y-m-d', strtotime("-{$retentionDays} days"));
        
        // Clean up old events
        if ($this->analyticsEventsHasStoreId()) {
            $query = "DELETE FROM analytics_events WHERE store_id = ? AND created_at < ?";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$this->storeId, $cutoffDate]);
        } else {
            $query = "DELETE FROM analytics_events WHERE created_at < ?";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$cutoffDate]);
        }
        return $stmt->rowCount();
    }
    
    /**
     * Populate page analytics from analytics_events
     */
    public function populatePageAnalytics($days = 30) {
        try {
            $whereStore = $this->analyticsEventsHasStoreId() ? " AND store_id = ?" : "";
            $params = [$days];
            if ($this->analyticsEventsHasStoreId()) $params[] = $this->storeId;
            $query = "INSERT INTO page_analytics (page_url, page_title, view_count, unique_views, avg_time_on_page)
                      SELECT 
                          page_url,
                          JSON_UNQUOTE(JSON_EXTRACT(event_data, '$.page_title')) as page_title,
                          COUNT(*) as view_count,
                          COUNT(DISTINCT session_id) as unique_views,
                          AVG(JSON_EXTRACT(event_data, '$.time_on_page')) as avg_time_on_page
                      FROM analytics_events 
                      WHERE event_type = 'page_view' 
                      AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY) $whereStore
                      GROUP BY page_url, JSON_UNQUOTE(JSON_EXTRACT(event_data, '$.page_title'))
                      ON DUPLICATE KEY UPDATE
                      view_count = VALUES(view_count),
                      unique_views = VALUES(unique_views),
                      avg_time_on_page = VALUES(avg_time_on_page),
                      last_updated = CURRENT_TIMESTAMP";
            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (Exception $e) {
            error_log("Error populating page analytics: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Generate test analytics data for development
     */
    public function generateTestData() {
        try {
            $testPages = [
                ['/index.php', 'Home Page'],
                ['/index.php?page=shop', 'Shop'],
                ['/index.php?page=about', 'About Us'],
                ['/index.php?page=contact', 'Contact'],
                ['/index.php?page=product_view&id=1', 'Product View - Item 1'],
                ['/index.php?page=product_view&id=2', 'Product View - Item 2'],
                ['/index.php?page=cart', 'Shopping Cart'],
                ['/index.php?page=checkout', 'Checkout']
            ];
            
            $inserted = 0;
            foreach ($testPages as $page) {
                $views = rand(5, 50);
                $uniqueViews = rand(3, $views);
                $avgTime = rand(30, 300);
                
                $query = "INSERT INTO page_analytics (page_url, page_title, view_count, unique_views, avg_time_on_page) 
                          VALUES (?, ?, ?, ?, ?) 
                          ON DUPLICATE KEY UPDATE 
                          view_count = view_count + VALUES(view_count),
                          unique_views = unique_views + VALUES(unique_views),
                          avg_time_on_page = (avg_time_on_page + VALUES(avg_time_on_page)) / 2,
                          last_updated = CURRENT_TIMESTAMP";
                
                $stmt = $this->conn->prepare($query);
                $stmt->execute([$page[0], $page[1], $views, $uniqueViews, $avgTime]);
                $inserted++;
            }
            
            return $inserted;
        } catch (Exception $e) {
            error_log("Error generating test data: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Fix existing page analytics data
     */
    public function fixPageAnalytics() {
        try {
            $query = "UPDATE page_analytics pa
                      SET 
                          unique_views = (
                              SELECT COUNT(DISTINCT session_id) 
                              FROM analytics_events 
                              WHERE event_type = 'page_view' 
                              AND page_url = pa.page_url
                          ),
                          avg_time_on_page = COALESCE((
                              SELECT AVG(JSON_EXTRACT(event_data, '$.time_on_page')) 
                              FROM analytics_events 
                              WHERE event_type = 'time_on_page' 
                              AND page_url = pa.page_url
                              AND JSON_EXTRACT(event_data, '$.time_on_page') IS NOT NULL
                          ), 0)
                      WHERE pa.page_url IS NOT NULL";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            
            return $stmt->rowCount();
        } catch (Exception $e) {
            error_log("Error fixing page analytics: " . $e->getMessage());
            return 0;
        }
    }
}
?>
