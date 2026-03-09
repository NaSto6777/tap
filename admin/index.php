<?php
session_start();
require_once '../config/database.php';
require_once '../config/StoreContext.php';
require_once '../config/settings.php';
require_once '../config/language.php';
require_once '../config/CsrfHelper.php';

if (!defined('PLATFORM_BASE_DOMAIN')) {
    define('PLATFORM_BASE_DOMAIN', 'localhost');
}

// Handle language change
if (isset($_GET['lang']) && in_array($_GET['lang'], ['en', 'ar'])) {
    Language::setLanguage($_GET['lang']);
    header('Location: ' . str_replace(['?lang=en', '?lang=ar', '&lang=en', '&lang=ar'], '', $_SERVER['REQUEST_URI']));
    exit;
}

// Initialize language
Language::init();
$t = function($key, $default = null) {
    return Language::t($key, $default);
};

// Enable errors during investigation of empty content
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

// Resolve store from subdomain and ensure admin is bound to this store
$resolved = StoreContext::resolveFromRequest(PLATFORM_BASE_DOMAIN);
if (!$resolved) {
    header('HTTP/1.1 404 Not Found');
    echo '<!DOCTYPE html><html><body><h1>Store not found</h1></body></html>';
    exit;
}
StoreContext::set($resolved['id'], $resolved['store']);
$store_id = StoreContext::getId();
if (isset($_SESSION['admin_store_id']) && (int) $_SESSION['admin_store_id'] !== $store_id) {
    session_destroy();
    header('Location: login.php');
    exit();
}

// Initialize database and settings once (scoped to current store)
$database = new Database();
$conn = $database->getConnection();
$settings = new Settings($conn, $store_id);

$logoSetting = $settings->getSetting('logo', '');
$logoPath = '';
if (!empty($logoSetting)) {
    $logoPath = (strpos($logoSetting, 'uploads/') === 0) ? ('../' . $logoSetting) : $logoSetting;
}

// Header: order credits and subscription (for display in header-right)
$header_credits = 0;
$header_subscription_end = null;
$header_subscription_status = null;
try {
    $stmt = $conn->prepare("SELECT s.order_credits, sub.current_period_end, sub.status AS sub_status FROM stores s LEFT JOIN subscriptions sub ON s.subscription_id = sub.id WHERE s.id = ?");
    $stmt->execute([$store_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $header_credits = isset($row['order_credits']) ? (int) $row['order_credits'] : 0;
        $header_subscription_end = !empty($row['current_period_end']) ? $row['current_period_end'] : null;
        $header_subscription_status = $row['sub_status'] ?? null;
    }
} catch (Exception $e) {}

$page = $_GET['page'] ?? 'dashboard';
$content_only = isset($_GET['content']) && $_GET['content'] === '1';

if ($content_only) {
    define('ADMIN_CONTENT_FRAME', true);
}

// Handle POST actions early before any HTML output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Global CSRF validation for all admin POST requests
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!CsrfHelper::validateToken($csrfToken)) {
        http_response_code(400);
        echo '<!DOCTYPE html><html><body><h1>Invalid security token</h1><p>Please refresh the page and try again.</p></body></html>';
        exit;
    }

    $pageFile = "pages/{$page}.php";
    if (file_exists($pageFile)) {
        require_once dirname(__DIR__) . '/config/admin_helpers.php';
        include $pageFile;
        exit();
    }
}

// Content-only response for iframe (shell loads this inside #admin-frame)
if ($content_only) {
    require_once dirname(__DIR__) . '/config/admin_helpers.php';
    $pageFile = "pages/{$page}.php";
    $page_title = ucfirst(str_replace('_', ' ', $page));
    $is_rtl = Language::isRTL();
    header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="<?php echo Language::getCurrentLanguage(); ?>" dir="<?php echo $is_rtl ? 'rtl' : 'ltr'; ?>" data-theme="<?php echo $_SESSION['admin_theme'] ?? 'light'; ?>" class="content-frame-doc">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/admin.css" rel="stylesheet">
    <link href="assets/css/admin-shell.css" rel="stylesheet">
    <?php if ($page === 'categories'): ?><link href="assets/css/categories.css" rel="stylesheet"><?php endif; ?>
    <?php if ($page === 'analytics_dashboard'): ?><link href="assets/css/analytics-dashboard.css" rel="stylesheet"><?php endif; ?>
    <meta name="csrf-token" content="<?php echo htmlspecialchars(CsrfHelper::getToken()); ?>">
    <style>
        :root {
            --primary-color: <?php echo $settings->getSetting('primary_color', '#6366f1'); ?>;
            --primary-color-light: <?php echo $settings->getSetting('primary_color', '#6366f1'); ?>26;

            --color-primary: var(--primary-color);
            --color-primary-hover: var(--primary-color);
            --color-primary-light: var(--primary-color-light);

            --color-primary-db: var(--primary-color);
            --color-primary-db-hover: <?php echo $settings->getSetting('primary_color', '#6366f1'); ?>dd;
            --color-primary-db-light: var(--primary-color-light);
        }
    </style>
</head>
<body class="content-frame">
    <div class="content-frame-inner" role="main">
<?php
    if (file_exists($pageFile)) {
        try {
            include $pageFile;
        } catch (Exception $e) {
            echo '<div class="alert alert-danger">Error loading page: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    } else {
        echo '<div class="alert alert-warning">Page not found.</div>';
        include 'pages/dashboard.php';
    }
?>
    </div>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/admin.js"></script>
    <?php if ($page === 'analytics_dashboard'): ?><script src="https://cdn.jsdelivr.net/npm/chart.js"></script><script src="assets/js/analytics-dashboard.js"></script><?php endif; ?>
    <script src="assets/js/admin-content-frame.js"></script>
</body>
</html>
<?php
    exit;
}

// Full shell (sidebar + header + iframe)
include __DIR__ . '/layout.php';
