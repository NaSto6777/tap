<?php
/**
 * Centralized Image Helper (store-scoped for multi-tenant)
 * Paths: uploads/stores/{store_id}/products/, uploads/stores/{store_id}/categories/
 */

class ImageHelper {

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
        $base_url = '/uploads/stores/' . $sid . '/products/' . $product_id . '/';
        $legacy_path = __DIR__ . '/../uploads/products/' . $product_id . '/';
        $legacy_url = '/uploads/products/' . $product_id . '/';

        if ($type === 'main') {
            $file_path = $base_path . 'main.jpg';
            if (file_exists($file_path)) return $base_url . 'main.jpg';
            if (file_exists($legacy_path . 'main.jpg')) return $legacy_url . 'main.jpg';
        }
        return '/uploads/placeholder.jpg';
    }

    /**
     * Get all product images (gallery)
     */
    public static function getProductGallery($product_id, $store_id = null) {
        $sid = self::getStoreId($store_id);
        $base_path = __DIR__ . '/../uploads/stores/' . $sid . '/products/' . $product_id . '/';
        $base_url = '/uploads/stores/' . $sid . '/products/' . $product_id . '/';
        $legacy_path = __DIR__ . '/../uploads/products/' . $product_id . '/';
        $legacy_url = '/uploads/products/' . $product_id . '/';
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
        if (file_exists($path)) return '/uploads/stores/' . $sid . '/categories/' . $category_id . '.jpg';
        if (file_exists($legacy)) return '/uploads/categories/' . $category_id . '.jpg';
        return '/uploads/placeholder.jpg';
    }

    public static function getSiteLogo() {
        if (class_exists('StoreContext') && StoreContext::isResolved()) {
            $sid = StoreContext::getId();
            $path = __DIR__ . '/../uploads/stores/' . $sid . '/site/logo.png';
            if (file_exists($path)) return '/uploads/stores/' . $sid . '/site/logo.png';
        }
        $path = __DIR__ . '/../uploads/site/logo.png';
        if (file_exists($path)) return '/uploads/site/logo.png';
        return '/uploads/placeholder.jpg';
    }

    public static function getPlaceholder() {
        return '/uploads/placeholder.jpg';
    }
}
?>
