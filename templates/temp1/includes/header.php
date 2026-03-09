<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? $settings->getSetting('site_name', 'Multi Template Ecommerce'); ?></title>
    <meta name="description" content="<?php echo $page_description ?? $settings->getSetting('site_description', 'A powerful multi-template ecommerce solution'); ?>">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="templates/<?php echo $activeTemplate; ?>/assets/css/style.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: <?php echo $settings->getSetting('primary_color', '#007bff'); ?>;
            --secondary-color: <?php echo $settings->getSetting('secondary_color', '#6c757d'); ?>;
        }
    </style>
    
    <?php
    require_once __DIR__ . '/../../../config/plugin_helper.php';
    $pluginHelper = new PluginHelper();
    
    // Google Analytics
    if ($pluginHelper->isPluginActive('google_analytics')) {
        $gaConfig = $pluginHelper->getPluginConfig('google_analytics');
        $gaTrackingId = $gaConfig['ga_tracking_id'] ?? '';
        if (!empty($gaTrackingId)): ?>
        <!-- Google Analytics -->
        <script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo htmlspecialchars($gaTrackingId); ?>"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());
            gtag('config', '<?php echo htmlspecialchars($gaTrackingId); ?>');
        </script>
        <?php endif;
    }
    
    // Facebook Pixel
    if ($pluginHelper->isPluginActive('facebook_pixel')) {
        $fbConfig = $pluginHelper->getPluginConfig('facebook_pixel');
        $fbPixelId = $fbConfig['fb_pixel_id'] ?? '';
        if (!empty($fbPixelId)): ?>
        <!-- Facebook Pixel Code -->
        <script>
            !function(f,b,e,v,n,t,s)
            {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
            n.callMethod.apply(n,arguments):n.queue.push(arguments)};
            if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
            n.queue=[];t=b.createElement(e);t.async=!0;
            t.src=v;s=b.getElementsByTagName(e)[0];
            s.parentNode.insertBefore(t,s)}(window, document,'script',
            'https://connect.facebook.net/en_US/fbevents.js');
            fbq('init', '<?php echo htmlspecialchars($fbPixelId); ?>');
            fbq('track', 'PageView');
        </script>
        <noscript><img height="1" width="1" style="display:none"
            src="https://www.facebook.com/tr?id=<?php echo htmlspecialchars($fbPixelId); ?>&ev=PageView&noscript=1"
        /></noscript>
        <?php endif;
    }
    
    // reCAPTCHA
    if ($pluginHelper->isPluginActive('recaptcha')): ?>
    <!-- reCAPTCHA -->
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <?php endif; ?>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <?php 
                $logo = $settings->getSetting('logo', '');
                $logoPath = $logo ? (__DIR__ . '/../../../' . ltrim($logo, '/\\')) : '';
                if (!empty($logo) && $logoPath && file_exists($logoPath)): ?>
                    <img src="<?php echo htmlspecialchars($logo); ?>" alt="<?php echo htmlspecialchars($settings->getSetting('site_name', 'Ecommerce Store')); ?>" height="40" class="me-2">
                <?php else: ?>
                    <i class="fas fa-store"></i>
                <?php endif; ?>
                <?php echo $settings->getSetting('site_name', 'Ecommerce Store'); ?>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link <?php echo $page == 'home' ? 'active' : ''; ?>" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $page == 'shop' ? 'active' : ''; ?>" href="index.php?page=shop">Shop</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $page == 'about' ? 'active' : ''; ?>" href="index.php?page=about">About</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $page == 'contact' ? 'active' : ''; ?>" href="index.php?page=contact">Contact</a>
                    </li>
                </ul>
                
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php?page=cart">
                            <i class="fas fa-shopping-cart"></i> Cart
                            <span class="badge bg-primary" id="cart-count">0</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
