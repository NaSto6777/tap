<?php
/**
 * Admin helper functions (redirects, etc.) for use in admin pages.
 * When ADMIN_CONTENT_FRAME is defined (iframe content mode), redirects include content=1.
 */
if (!function_exists('admin_redirect_url')) {
    function admin_redirect_url($page, array $params = []) {
        $content = (defined('ADMIN_CONTENT_FRAME') && ADMIN_CONTENT_FRAME) ? '1' : '0';
        $url = 'index.php?' . ($content === '1' ? 'content=1&' : '') . 'page=' . urlencode($page);
        if (!empty($params)) {
            $url .= '&' . http_build_query($params);
        }
        return $url;
    }
}
