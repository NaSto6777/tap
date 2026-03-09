<?php
/**
 * Admin Shell Layout
 * Static sidebar + header; main content loads in iframe. Never refreshes.
 * Requires: $page, $t, $settings, $logoPath, $header_credits, $header_subscription_end
 */
$content_url = 'index.php?content=1&page=' . urlencode($page);
$is_rtl = Language::isRTL();
?>
<!DOCTYPE html>
<html lang="<?php echo Language::getCurrentLanguage(); ?>" dir="<?php echo $is_rtl ? 'rtl' : 'ltr'; ?>" data-theme="<?php echo $_SESSION['admin_theme'] ?? 'light'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Multi Template Ecommerce</title>
    <link rel="preload" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" as="style">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/admin.css" rel="stylesheet">
    <link href="assets/css/admin-shell.css" rel="stylesheet">
    <meta name="csrf-token" content="<?php echo htmlspecialchars(CsrfHelper::getToken()); ?>">
    <style>
        :root {
            --color-primary-db: <?php echo $settings->getSetting('primary_color', '#007bff'); ?>;
            --color-primary-db-hover: <?php echo $settings->getSetting('primary_color', '#007bff'); ?>dd;
            --color-primary-db-light: <?php echo $settings->getSetting('primary_color', '#007bff'); ?>20;
        }
    </style>
</head>
<body class="admin-shell">
    <a href="#admin-frame" class="skip-link">Skip to main content</a>

    <div class="admin-container admin-shell-container">
        <div class="sidebar-overlay" id="sidebarOverlay" aria-hidden="true"></div>

        <aside class="sidebar sidebar-slim" id="sidebar" role="navigation" aria-label="Main navigation">
            <div class="sidebar-header">
                <a href="index.php" class="sidebar-brand" aria-label="Admin Panel Home">
                    <?php if (!empty($logoPath)): ?>
                        <div class="brand-logo" style="text-align:center; padding:8px 0;">
                            <img src="<?php echo htmlspecialchars($logoPath); ?>" alt="Logo" class="logo-img" style="max-width:100px;height:auto;display:block;margin:auto;object-fit:contain;">
                        </div>
                    <?php else: ?>
                        <i class="fas fa-store fa-lg" aria-hidden="true"></i>
                    <?php endif; ?>
                </a>
            </div>
            <nav class="sidebar-nav" role="navigation">
                <div class="nav-section">
                    <div class="nav-section-title"><?php echo $t('nav_main'); ?></div>
                    <div class="nav-item">
                        <a href="index.php?content=1&page=dashboard" class="nav-link <?php echo $page === 'dashboard' ? 'active' : ''; ?>" data-page="dashboard" aria-current="<?php echo $page === 'dashboard' ? 'page' : 'false'; ?>">
                            <i class="fas fa-tachometer-alt nav-icon-slim" aria-hidden="true"></i>
                            <span><?php echo $t('nav_dashboard'); ?></span>
                        </a>
                    </div>
                </div>
                <div class="nav-section">
                    <div class="nav-section-title"><?php echo $t('nav_ecommerce'); ?></div>
                    <div class="nav-item">
                        <a href="index.php?content=1&page=orders" class="nav-link <?php echo $page === 'orders' ? 'active' : ''; ?>" data-page="orders" aria-current="<?php echo $page === 'orders' ? 'page' : 'false'; ?>">
                            <i class="fas fa-shopping-cart nav-icon-slim" aria-hidden="true"></i>
                            <span><?php echo $t('nav_orders'); ?></span>
                        </a>
                    </div>
                    <div class="nav-item">
                        <a href="index.php?content=1&page=products" class="nav-link <?php echo $page === 'products' ? 'active' : ''; ?>" data-page="products" aria-current="<?php echo $page === 'products' ? 'page' : 'false'; ?>">
                            <i class="fas fa-box nav-icon-slim" aria-hidden="true"></i>
                            <span><?php echo $t('nav_products'); ?></span>
                        </a>
                    </div>
                    <?php if ($settings->getSetting('categories_enabled', '1') === '1'): ?>
                    <div class="nav-item">
                        <a href="index.php?content=1&page=categories" class="nav-link <?php echo $page === 'categories' ? 'active' : ''; ?>" data-page="categories" aria-current="<?php echo $page === 'categories' ? 'page' : 'false'; ?>">
                            <i class="fas fa-list nav-icon-slim" aria-hidden="true"></i>
                            <span><?php echo $t('nav_categories'); ?></span>
                        </a>
                    </div>
                    <?php endif; ?>
                    <div class="nav-item">
                        <a href="index.php?content=1&page=abandoned_carts" class="nav-link <?php echo $page === 'abandoned_carts' ? 'active' : ''; ?>" data-page="abandoned_carts" aria-current="<?php echo $page === 'abandoned_carts' ? 'page' : 'false'; ?>">
                            <i class="fas fa-shopping-bag nav-icon-slim" aria-hidden="true"></i>
                            <span><?php echo $t('abandoned_carts', 'Abandoned Carts'); ?></span>
                        </a>
                    </div>
                    <div class="nav-item">
                        <a href="index.php?content=1&page=analytics_dashboard" class="nav-link <?php echo $page === 'analytics_dashboard' ? 'active' : ''; ?>" data-page="analytics_dashboard" aria-current="<?php echo $page === 'analytics_dashboard' ? 'page' : 'false'; ?>">
                            <i class="fas fa-chart-bar nav-icon-slim" aria-hidden="true"></i>
                            <span><?php echo $t('nav_analytics'); ?></span>
                        </a>
                    </div>
                </div>
                <div class="nav-section">
                    <div class="nav-section-title"><?php echo $t('nav_settings'); ?></div>
                    <div class="nav-item">
                        <a href="index.php?content=1&page=settings" class="nav-link <?php echo $page === 'settings' ? 'active' : ''; ?>" data-page="settings" aria-current="<?php echo $page === 'settings' ? 'page' : 'false'; ?>">
                            <i class="fas fa-cog nav-icon-slim" aria-hidden="true"></i>
                            <span><?php echo $t('nav_settings'); ?></span>
                        </a>
                    </div>
                    <div class="nav-item">
                        <a href="index.php?content=1&page=finance" class="nav-link <?php echo $page === 'finance' ? 'active' : ''; ?>" data-page="finance" aria-current="<?php echo $page === 'finance' ? 'page' : 'false'; ?>">
                            <i class="fas fa-chart-line nav-icon-slim" aria-hidden="true"></i>
                            <span><?php echo $t('nav_finance'); ?></span>
                        </a>
                    </div>
                    <div class="nav-item">
                        <a href="index.php?content=1&page=plugins" class="nav-link <?php echo $page === 'plugins' ? 'active' : ''; ?>" data-page="plugins" aria-current="<?php echo $page === 'plugins' ? 'page' : 'false'; ?>">
                            <i class="fas fa-puzzle-piece nav-icon-slim" aria-hidden="true"></i>
                            <span><?php echo $t('nav_plugins'); ?></span>
                        </a>
                    </div>
                </div>
                <div class="nav-section">
                    <div class="nav-section-title"><?php echo $t('nav_design'); ?></div>
                    <div class="nav-item">
                        <a href="index.php?content=1&page=templates" class="nav-link <?php echo $page === 'templates' ? 'active' : ''; ?>" data-page="templates" aria-current="<?php echo $page === 'templates' ? 'page' : 'false'; ?>">
                            <i class="fas fa-palette nav-icon-slim" aria-hidden="true"></i>
                            <span><?php echo $t('nav_templates'); ?></span>
                        </a>
                    </div>
                </div>
                <div class="nav-section">
                    <div class="nav-item">
                        <a href="logout.php" class="nav-link" aria-label="<?php echo $t('nav_logout'); ?>">
                            <i class="fas fa-sign-out-alt nav-icon-slim" aria-hidden="true"></i>
                            <span><?php echo $t('nav_logout'); ?></span>
                        </a>
                    </div>
                </div>
            </nav>
        </aside>

        <main class="main-content main-content-shell" id="main-content">
            <header class="header" role="banner">
                <div class="header-brand">
                    <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar" aria-expanded="false">
                        <i class="fas fa-bars" aria-hidden="true"></i>
                    </button>
                    <a href="index.php" class="header-logo" aria-label="Admin Panel Home">
                        <?php if (!empty($logoPath)): ?>
                            <img src="<?php echo htmlspecialchars($logoPath); ?>" alt="Logo">
                        <?php else: ?>
                            <span class="header-logo-text">
                                <i class="fas fa-store" aria-hidden="true"></i>
                                <span>Admin Panel</span>
                            </span>
                        <?php endif; ?>
                    </a>
                </div>
                <div class="header-right">
                    <div class="d-flex align-items-center gap-3 header-right-inner" dir="<?php echo $is_rtl ? 'rtl' : 'ltr'; ?>">
                        <div class="user-info">
                            <div class="user-avatar" aria-hidden="true"><?php echo strtoupper(substr($_SESSION['admin_username'] ?? 'A', 0, 1)); ?></div>
                            <span class="user-name"><?php echo $_SESSION['admin_username'] ?? 'Admin'; ?></span>
                        </div>
                        <a href="../index.php" target="_blank" class="btn btn-outline-primary btn-sm" aria-label="View website">
                            <i class="fas fa-external-link-alt" aria-hidden="true"></i>
                            <span class="d-none d-sm-inline"><?php echo $t('view_site', 'View Site'); ?></span>
                        </a>
                        <button class="theme-switcher-btn" id="themeSwitcher" aria-label="Toggle theme">
                            <i class="fas fa-moon" aria-hidden="true"></i>
                            <span class="d-none d-sm-inline"><?php echo $t('theme', 'Theme'); ?></span>
                        </button>
                        <div class="header-subscription" title="<?php echo $t('subscription', 'Subscription'); ?>">
                            <div class="subscription-label"><?php echo $t('subscription', 'Subscription'); ?></div>
                            <div class="subscription-value">
                                <?php if ($header_subscription_end): ?>
                                    <?php $expired = strtotime($header_subscription_end) < strtotime('today'); ?>
                                    <span class="sub-<?php echo $expired ? 'expired' : 'active'; ?>"><?php echo $expired ? $t('expired', 'Expired') : $t('active_until', 'Active until'); ?> <?php echo date('M j, Y', strtotime($header_subscription_end)); ?></span>
                                <?php else: ?>
                                    <span class="sub-none">—</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="header-coin" title="<?php echo $t('order_credits', 'Order credits'); ?>">
                            <div class="coin-count-box"><span class="coin-count"><?php echo number_format($header_credits); ?></span></div>
                            <div class="coin-icon-box"><i class="fas fa-coins"></i></div>
                        </div>
                    </div>
                </div>
            </header>

            <div class="admin-frame-wrapper" role="main">
                <iframe id="admin-frame" name="admin-frame" class="admin-frame" src="<?php echo htmlspecialchars($content_url); ?>" title="Admin content"></iframe>
            </div>
        </main>
    </div>

    <div id="shell-toast-container" class="toast-container toast-container-shell" aria-live="polite" aria-atomic="true"></div>

    <nav class="mobile-bottom-nav" id="mobileBottomNav" role="navigation" aria-label="Mobile navigation">
        <ul class="nav-list">
            <li class="nav-item"><a href="index.php?content=1&page=dashboard" class="nav-link <?php echo $page === 'dashboard' ? 'active' : ''; ?>" data-page="dashboard" aria-label="Dashboard"><i class="fas fa-tachometer-alt nav-icon"></i><span class="nav-label">Dashboard</span></a></li>
            <li class="nav-item"><a href="index.php?content=1&page=orders" class="nav-link <?php echo $page === 'orders' ? 'active' : ''; ?>" data-page="orders" aria-label="Orders"><i class="fas fa-shopping-cart nav-icon"></i><span class="nav-label">Orders</span></a></li>
            <li class="nav-item"><a href="index.php?content=1&page=products" class="nav-link <?php echo $page === 'products' ? 'active' : ''; ?>" data-page="products" aria-label="Products"><i class="fas fa-box nav-icon"></i><span class="nav-label">Products</span></a></li>
            <li class="nav-item"><a href="index.php?content=1&page=settings" class="nav-link <?php echo $page === 'settings' ? 'active' : ''; ?>" data-page="settings" aria-label="Settings"><i class="fas fa-cog nav-icon"></i><span class="nav-label">Settings</span></a></li>
            <li class="nav-item more-dropdown">
                <a href="#" class="nav-link" id="moreMenuToggle" aria-label="More" aria-expanded="false" aria-haspopup="true"><i class="fas fa-ellipsis-h nav-icon"></i><span class="nav-label">More</span></a>
                <div class="more-menu" id="moreMenu" role="menu">
                    <?php if ($settings->getSetting('categories_enabled', '1') === '1'): ?>
                    <a href="index.php?content=1&page=categories" class="nav-link nav-link-more" data-page="categories"><i class="fas fa-list nav-icon"></i><span class="nav-label">Categories</span></a>
                    <?php endif; ?>
                    <a href="index.php?content=1&page=abandoned_carts" class="nav-link" data-page="abandoned_carts"><i class="fas fa-shopping-bag nav-icon"></i><span class="nav-label">Abandoned Carts</span></a>
                    <a href="index.php?content=1&page=analytics_dashboard" class="nav-link" data-page="analytics_dashboard"><i class="fas fa-chart-bar nav-icon"></i><span class="nav-label">Analytics</span></a>
                    <a href="index.php?content=1&page=finance" class="nav-link" data-page="finance"><i class="fas fa-chart-line nav-icon"></i><span class="nav-label">Finance</span></a>
                    <a href="index.php?content=1&page=plugins" class="nav-link" data-page="plugins"><i class="fas fa-puzzle-piece nav-icon"></i><span class="nav-label">Plugins</span></a>
                    <a href="index.php?content=1&page=templates" class="nav-link" data-page="templates"><i class="fas fa-palette nav-icon"></i><span class="nav-label">Templates</span></a>
                    <a href="logout.php" class="nav-link"><i class="fas fa-sign-out-alt nav-icon"></i><span class="nav-label">Logout</span></a>
                </div>
            </li>
        </ul>
    </nav>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/admin.js"></script>
    <script src="assets/js/admin-shell.js"></script>
</body>
</html>
