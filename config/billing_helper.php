<?php
/**
 * Billing & subscription locking: expiry, order placement limit, credits, order masking.
 */
require_once __DIR__ . '/database.php';

class BillingHelper {
    private static $conn;

    public static function init($conn = null) {
        self::$conn = $conn ?? (new Database())->getConnection();
    }

    private static function c() {
        if (self::$conn === null) self::init();
        return self::$conn;
    }

    /**
     * True if the store's subscription period has ended (current_period_end < today) or no subscription.
     */
    public static function isExpired($store_id) {
        $s = self::c()->prepare("SELECT sub.current_period_end FROM stores s LEFT JOIN subscriptions sub ON s.subscription_id = sub.id WHERE s.id = ?");
        $s->execute([(int) $store_id]);
        $r = $s->fetch(PDO::FETCH_ASSOC);
        return !$r || $r['current_period_end'] === null || strtotime($r['current_period_end']) < strtotime('today');
    }

    /**
     * True if the store can accept a new order: period order count < plan order_limit OR order_credits > 0.
     */
    public static function canAcceptOrder($store_id) {
        $c = self::c();
        $store_id = (int) $store_id;
        $s = $c->prepare("SELECT sub.current_period_start, sub.current_period_end, sub.plan_id, s.order_credits FROM stores s LEFT JOIN subscriptions sub ON s.subscription_id = sub.id WHERE s.id = ?");
        $s->execute([$store_id]);
        $r = $s->fetch(PDO::FETCH_ASSOC);
        if (!$r) return false;
        if ((int)($r['order_credits'] ?? 0) > 0) return true;
        $start = $r['current_period_start'] ?? date('Y-m-01');
        $end   = $r['current_period_end'] ?? date('Y-m-t');
        $plan_id = (int)($r['plan_id'] ?? 0);
        $limit = 0;
        if ($plan_id > 0) {
            try {
                $s = $c->prepare("SELECT order_limit FROM subscription_plans WHERE id = ?");
                $s->execute([$plan_id]);
                $plan = $s->fetch(PDO::FETCH_ASSOC);
                $limit = (int)($plan['order_limit'] ?? 0);
            } catch (PDOException $e) { $limit = 0; }
        }
        if ($limit <= 0) return true;
        $s = $c->prepare("SELECT COUNT(*) FROM orders WHERE store_id = ? AND created_at >= ? AND created_at <= ?");
        $s->execute([$store_id, $start, $end]);
        return (int) $s->fetchColumn() < $limit;
    }

    /**
     * True if this order should be locked: store is expired AND order was placed after current_period_end.
     */
    public static function shouldLockOrder($store_id, $order_date) {
        if (!self::isExpired($store_id)) return false;
        $s = self::c()->prepare("SELECT sub.current_period_end FROM stores s LEFT JOIN subscriptions sub ON s.subscription_id = sub.id WHERE s.id = ?");
        $s->execute([(int) $store_id]);
        $r = $s->fetch(PDO::FETCH_ASSOC);
        if (!$r || $r['current_period_end'] === null) return true;
        return strtotime($order_date) > strtotime($r['current_period_end'] . ' 23:59:59');
    }

    /**
     * Decrement order_credits by 1 (call after placing an order that used a credit).
     */
    public static function consumeCredit($store_id) {
        self::c()->prepare("UPDATE stores SET order_credits = GREATEST(0, COALESCE(order_credits, 0) - 1) WHERE id = ?")->execute([(int) $store_id]);
    }

    /**
     * Returns ['allowed' => bool, 'use_credit' => bool]. use_credit is true when the next order would consume a credit (count >= limit and credits > 0).
     */
    public static function getPlacementState($store_id) {
        $c = self::c();
        $store_id = (int) $store_id;
        $s = $c->prepare("SELECT sub.current_period_start, sub.current_period_end, sub.plan_id, s.order_credits FROM stores s LEFT JOIN subscriptions sub ON s.subscription_id = sub.id WHERE s.id = ?");
        $s->execute([$store_id]);
        $r = $s->fetch(PDO::FETCH_ASSOC);
        $allowed = false;
        $use_credit = false;
        if (!$r) return ['allowed' => false, 'use_credit' => false];
        $credits = (int)($r['order_credits'] ?? 0);
        $start = $r['current_period_start'] ?? date('Y-m-01');
        $end   = $r['current_period_end'] ?? date('Y-m-t');
        $plan_id = (int)($r['plan_id'] ?? 0);
        $limit = 0;
        if ($plan_id > 0) {
            try {
                $s = $c->prepare("SELECT order_limit FROM subscription_plans WHERE id = ?");
                $s->execute([$plan_id]);
                $plan = $s->fetch(PDO::FETCH_ASSOC);
                $limit = (int)($plan['order_limit'] ?? 0);
            } catch (PDOException $e) { $limit = 0; }
        }
        $s = $c->prepare("SELECT COUNT(*) FROM orders WHERE store_id = ? AND created_at >= ? AND created_at <= ?");
        $s->execute([$store_id, $start, $end]);
        $count = (int) $s->fetchColumn();
        if ($credits > 0) {
            $allowed = true;
            $use_credit = ($limit > 0 && $count >= $limit);
        } elseif ($limit <= 0 || $count < $limit) {
            $allowed = true;
        }
        return ['allowed' => $allowed, 'use_credit' => $use_credit];
    }
}
