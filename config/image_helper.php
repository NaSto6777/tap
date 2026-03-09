<?php
/**
 * Centralized Image Helper (store-scoped for multi-tenant)
 * Paths: uploads/stores/{store_id}/products/, uploads/stores/{store_id}/categories/
 */

class ImageHelper {
    private static $baseUrlPrefix = null;

    /**
     * Compute base URL prefix for subfolder installs (e.g. /tap).
     * Uses DOCUMENT_ROOT + project filesystem path to derive a web path.
     */
    private static function baseUrlPrefix(): string {
        if (self::$baseUrlPrefix !== null) {
            return self::$baseUrlPrefix;
        }

        $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath((string)$_SERVER['DOCUMENT_ROOT']) : false;
        $projectRoot = realpath(__DIR__ . '/..');

        if (!$docRoot || !$projectRoot) {
            self::$baseUrlPrefix = '';
            return self::$baseUrlPrefix;
        }

        $docRootNorm = rtrim(str_replace('\\', '/', $docRoot), '/');
        $projNorm    = rtrim(str_replace('\\', '/', $projectRoot), '/');

        if (strpos($projNorm, $docRootNorm) !== 0) {
            self::$baseUrlPrefix = '';
            return self::$baseUrlPrefix;
        }

        $relative = substr($projNorm, strlen($docRootNorm));
        $relative = $relative === false ? '' : $relative;
        $relative = '/' . ltrim($relative, '/');
        $relative = rtrim($relative, '/');

        self::$baseUrlPrefix = $relative === '/' ? '' : $relative;
        return self::$baseUrlPrefix;
    }

    private static function url(string $absoluteFromWebRoot): string {
        $absoluteFromWebRoot = '/' . ltrim($absoluteFromWebRoot, '/');
        return self::baseUrlPrefix() . $absoluteFromWebRoot;
    }

    private static function getStoreId($store_id = null) {
        if ($store_id !== null) return (int) $store_id;
        if (class_exists('StoreContext') && StoreContext::isResolved()) return StoreContext::getId();
        return 1;
    }

    /**
     * Get product main image URL
     * @param int $product_id
     * @param string $type 'main'
     * @param int|null $store_id optional, defaults to StoreContext or 1
     */
    public static function getProductImage($product_id, $type = 'main', $store_id = null) {
        $sid = self::getStoreId($store_id);
        $base_path = __DIR__ . '/../uploads/stores/' . $sid . '/products/' . $product_id . '/';
        $base_url = self::url('/uploads/stores/' . $sid . '/products/' . $product_id . '/');
        $legacy_path = __DIR__ . '/../uploads/products/' . $product_id . '/';
        $legacy_url = self::url('/uploads/products/' . $product_id . '/');

        if ($type === 'main') {
            $file_path = $base_path . 'main.jpg';
            if (file_exists($file_path)) return $base_url . 'main.jpg';
            if (file_exists($legacy_path . 'main.jpg')) return $legacy_url . 'main.jpg';
        }
        return self::getPlaceholder();
    }

    /**
     * Get all product images (gallery)
     */
    public static function getProductGallery($product_id, $store_id = null) {
        $sid = self::getStoreId($store_id);
        $base_path = __DIR__ . '/../uploads/stores/' . $sid . '/products/' . $product_id . '/';
        $base_url = self::url('/uploads/stores/' . $sid . '/products/' . $product_id . '/');
        $legacy_path = __DIR__ . '/../uploads/products/' . $product_id . '/';
        $legacy_url = self::url('/uploads/products/' . $product_id . '/');
        $images = [];
        foreach ([$base_path, $legacy_path] as $i => $dir) {
            if (!is_dir($dir)) continue;
            $url = $i === 0 ? $base_url : $legacy_url;
            $files = scandir($dir);
            foreach ($files as $file) {
                if (in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                    $images[] = $url . $file;
                }
            }
            if (!empty($images)) break;
        }
        return !empty($images) ? $images : [self::getProductImage($product_id, 'main', $store_id)];
    }

    /**
     * Get category image URL
     */
    public static function getCategoryImage($category_id, $store_id = null) {
        $sid = self::getStoreId($store_id);
        $path = __DIR__ . '/../uploads/stores/' . $sid . '/categories/' . $category_id . '.jpg';
        $legacy = __DIR__ . '/../uploads/categories/' . $category_id . '.jpg';
        if (file_exists($path)) return self::url('/uploads/stores/' . $sid . '/categories/' . $category_id . '.jpg');
        if (file_exists($legacy)) return self::url('/uploads/categories/' . $category_id . '.jpg');
        return self::getPlaceholder();
    }

    public static function getSiteLogo() {
        if (class_exists('StoreContext') && StoreContext::isResolved()) {
            $sid = StoreContext::getId();
            $path = __DIR__ . '/../uploads/stores/' . $sid . '/site/logo.png';
            if (file_exists($path)) return self::url('/uploads/stores/' . $sid . '/site/logo.png');
        }
        $path = __DIR__ . '/../uploads/site/logo.png';
        if (file_exists($path)) return self::url('/uploads/site/logo.png');
        return self::getPlaceholder();
    }

    public static function getPlaceholder() {
        // Prefer SVG placeholder (lightweight), fall back to jpg name
        $svg = __DIR__ . '/../uploads/placeholder.svg';
        if (file_exists($svg)) return self::url('/uploads/placeholder.svg');
        return self::url('/uploads/placeholder.jpg');
    }
}
?>
