<?php
/**
 * Plan limits for the current store (multi-tenant).
 * Use after StoreContext is set (e.g. in admin or storefront).
 */
class PlanHelper {
    private static $orderLimitCache = [];

    /**
     * Returns the max number of (most recent) orders the store is allowed to see.
     * NULL = unlimited. Integer = only the N most recent orders are visible.
     *
     * @param PDO $conn
     * @param int|null $storeId Store ID (default: StoreContext::getId())
     * @return int|null
     */
    public static function getOrderLimit($conn, $storeId = null) {
        if ($storeId === null && class_exists('StoreContext')) {
            $storeId = StoreContext::getId();
        }
        $storeId = (int) $storeId;
        if ($storeId <= 0) {
            return null;
        }
        if (isset(self::$orderLimitCache[$storeId])) {
            return self::$orderLimitCache[$storeId];
        }
        try {
            $stmt = $conn->prepare("
                SELECT s.order_view_allowance, p.order_limit 
                FROM stores s 
                LEFT JOIN subscriptions sub ON s.subscription_id = sub.id 
                LEFT JOIN subscription_plans p ON sub.plan_id = p.id 
                WHERE s.id = ?
            ");
            $stmt->execute([$storeId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $limit = null;
            if ($row) {
                $allowance = isset($row['order_view_allowance']) && $row['order_view_allowance'] !== null && $row['order_view_allowance'] !== '' ? (int) $row['order_view_allowance'] : null;
                $planLimit = isset($row['order_limit']) && $row['order_limit'] !== null && $row['order_limit'] !== '' ? (int) $row['order_limit'] : null;
                if ($allowance !== null && $allowance > 0) {
                    $limit = $allowance;
                } elseif ($planLimit !== null && $planLimit > 0) {
                    $limit = $planLimit;
                }
            }
            self::$orderLimitCache[$storeId] = $limit;
            return $limit;
        } catch (Exception $e) {
            try {
                $stmt = $conn->prepare("SELECT p.order_limit FROM stores s LEFT JOIN subscriptions sub ON s.subscription_id = sub.id LEFT JOIN subscription_plans p ON sub.plan_id = p.id WHERE s.id = ?");
                $stmt->execute([$storeId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $limit = null;
                if ($row && isset($row['order_limit']) && $row['order_limit'] !== null && (int)$row['order_limit'] > 0) {
                    $limit = (int) $row['order_limit'];
                }
                self::$orderLimitCache[$storeId] = $limit;
                return $limit;
            } catch (Exception $e2) {
                self::$orderLimitCache[$storeId] = null;
                return null;
            }
        }
    }

    /**
     * Returns SQL subquery condition to restrict orders to the plan's visible set.
     * When order_limit is null, returns empty string (no extra condition).
     * Otherwise returns " AND o.id IN (SELECT id FROM (SELECT id FROM orders WHERE store_id = ? ORDER BY created_at DESC LIMIT ?) t) "
     * and the two params to append: [$store_id, $order_limit].
     *
     * @param PDO $conn
     * @param int $storeId
     * @param string $orderAlias Table alias for orders (e.g. 'o')
     * @return array ['sql' => string, 'params' => array]
     */
    public static function orderVisibilityCondition($conn, $storeId, $orderAlias = 'o') {
        $limit = self::getOrderLimit($conn, $storeId);
        if ($limit === null) {
            return ['sql' => '', 'params' => []];
        }
        $a = $orderAlias;
        return [
            'sql' => " AND $a.id IN (SELECT id FROM (SELECT id FROM orders WHERE store_id = ? ORDER BY created_at DESC LIMIT " . (int) $limit . ") t) ",
            'params' => [$storeId]
        ];
    }

    /**
     * Check if an order ID is in the visible set for the store (by plan).
     *
     * @param PDO $conn
     * @param int $storeId
     * @param int $orderId
     * @return bool
     */
    public static function canViewOrder($conn, $storeId, $orderId) {
        $limit = self::getOrderLimit($conn, $storeId);
        if ($limit === null) {
            $stmt = $conn->prepare("SELECT 1 FROM orders WHERE id = ? AND store_id = ? LIMIT 1");
            $stmt->execute([$orderId, $storeId]);
            return (bool) $stmt->fetch();
        }
        $limit = (int) $limit;
        $stmt = $conn->prepare("
            SELECT 1 FROM (
                SELECT id FROM orders WHERE store_id = ? ORDER BY created_at DESC LIMIT " . $limit . "
            ) t WHERE id = ?
        ");
        $stmt->execute([$storeId, $orderId]);
        return (bool) $stmt->fetch();
    }
}
