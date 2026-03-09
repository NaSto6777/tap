<?php
/**
 * Holds the current store for the request (multi-tenant).
 * Set once from subdomain (or default) in index.php / admin bootstrap.
 */
class StoreContext {
    private static $storeId = null;
    private static $store = null;

    public static function set($storeId, array $store = null) {
        self::$storeId = (int) $storeId;
        self::$store = $store;
    }

    public static function getId() {
        return self::$storeId;
    }

    public static function get() {
        return self::$store;
    }

    public static function getSubdomain() {
        return self::$store['subdomain'] ?? null;
    }

    public static function isResolved() {
        return self::$storeId !== null;
    }

    /**
     * Returns true when the request is for the main platform domain (no store subdomain).
     * Main domain = exact host or www.host (e.g. myplatform.tn or www.myplatform.tn).
     */
    public static function isMainDomain($platformBaseDomain = null) {
        $host = isset($_SERVER['HTTP_HOST']) ? strtolower($_SERVER['HTTP_HOST']) : '';
        $host = preg_replace('/:\d+$/', '', $host);
        if ($platformBaseDomain === null) {
            $platformBaseDomain = defined('PLATFORM_BASE_DOMAIN') ? PLATFORM_BASE_DOMAIN : 'localhost';
        }
        $platformBaseDomain = strtolower($platformBaseDomain);
        if ($host === $platformBaseDomain) {
            return true;
        }
        if ($host === 'www.' . $platformBaseDomain) {
            return true;
        }
        return false;
    }

    /**
     * Resolve store from HTTP_HOST (subdomain or 'default' when no subdomain).
     * Platform base domain is read from env or constant (e.g. myplatform.com).
     * Returns [ 'id' => int, 'store' => array ] or null if not found.
     */
    public static function resolveFromRequest($platformBaseDomain = null) {
        $host = isset($_SERVER['HTTP_HOST']) ? strtolower($_SERVER['HTTP_HOST']) : '';
        $host = preg_replace('/:\d+$/', '', $host);
        if ($platformBaseDomain === null) {
            $platformBaseDomain = defined('PLATFORM_BASE_DOMAIN') ? PLATFORM_BASE_DOMAIN : 'localhost';
        }
        $platformBaseDomain = strtolower($platformBaseDomain);

        $subdomain = '';
        if (strpos($host, '.') !== false && $host !== $platformBaseDomain) {
            $parts = explode('.', $host);
            $baseParts = explode('.', $platformBaseDomain);
            $baseCount = count($baseParts);
            $hostParts = count($parts);
            if ($hostParts > $baseCount) {
                $subdomain = $parts[0];
            } elseif ($hostParts === $baseCount && $parts !== $baseParts) {
                $subdomain = '';
            }
        }
        if ($subdomain === '' && $host !== $platformBaseDomain) {
            $subdomain = 'default';
        }
        if ($subdomain === '') {
            $subdomain = 'default';
        }

        try {
            $database = new Database();
            $conn = $database->getConnection();
            $stmt = $conn->prepare("SELECT id, name, subdomain, status, default_language FROM stores WHERE subdomain = ? AND status IN ('active') LIMIT 1");
            $stmt->execute([$subdomain]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                return ['id' => (int) $row['id'], 'store' => $row];
            }
        } catch (Exception $e) {
            error_log('StoreContext::resolveFromRequest: ' . $e->getMessage());
        }
        return null;
    }
}
