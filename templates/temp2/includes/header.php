<?php
/**
 * TEMP2 — Header
 * Glassmorphism fixed navbar · Search overlay · Cart badge
 *
 * Assumptions:
 * - $settings is a Settings instance (store-scoped)
 * - $activeTemplate and $page are defined in index.php
 */

// Basic store info
$siteName    = $settings->getSetting('site_name', 'Luxe Store');
$siteTagline = $settings->getSetting('site_tagline', 'Curated Essentials');
$logo_src    = ImageHelper::getSiteLogo();
$current_page = $_GET['page'] ?? 'home';

// Currency symbol (for header-only contexts that need it)
$currencyCode    = $settings->getSetting('currency', 'USD');
$customCurrency  = $settings->getSetting('custom_currency', '');
if ($currencyCode === 'CUSTOM' && $customCurrency !== '') {
    $currency_symbol = $customCurrency;
} else {
    $currency_symbol = $currencyCode === 'USD' ? '$'
        : ($currencyCode === 'EUR' ? '€'
        : ($currencyCode === 'GBP' ? '£'
        : ($currencyCode === 'TND' ? 'TND' : $currencyCode . ' ')));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <meta name="description" content="<?php echo htmlspecialchars($settings->getSetting('site_description', $siteTagline)); ?>">
  <title><?php echo htmlspecialchars($settings->getSetting('meta_title', $siteName)); ?></title>

  <!-- Favicon -->
  <link rel="icon" href="<?php echo htmlspecialchars($logo_src); ?>" type="image/png">

  <!-- Google Fonts preconnect -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

  <!-- Bootstrap 5 -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">

  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

  <!-- Temp2 Stylesheet -->
  <link rel="stylesheet" href="templates/temp2/assets/css/style.css">
</head>
<body class="sf-body">

<header class="sf-header">
  <nav class="navbar navbar-expand-lg sf-navbar fixed-top">
    <div class="container px-3 px-md-4">
      <a class="navbar-brand d-flex align-items-center gap-2" href="index.php">
        <?php if ($logo_src): ?>
          <img src="<?php echo htmlspecialchars($logo_src); ?>" alt="<?php echo htmlspecialchars($siteName); ?>" class="sf-logo">
        <?php endif; ?>
        <span class="sf-brand-name"><?php echo htmlspecialchars($siteName); ?></span>
      </a>

      <button class="navbar-toggler sf-navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#sfNavbarMain" aria-controls="sfNavbarMain" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>

      <div class="collapse navbar-collapse" id="sfNavbarMain">
        <ul class="navbar-nav me-auto mb-2 mb-lg-0 sf-nav-links">
          <li class="nav-item">
            <a class="nav-link <?php echo ($page ?? '') === 'home' ? 'active' : ''; ?>" href="index.php">Home</a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?php echo ($page ?? '') === 'shop' ? 'active' : ''; ?>" href="index.php?page=shop">Shop</a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?php echo ($page ?? '') === 'about' ? 'active' : ''; ?>" href="index.php?page=about">About</a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?php echo ($page ?? '') === 'contact' ? 'active' : ''; ?>" href="index.php?page=contact">Contact</a>
          </li>
        </ul>

        <div class="d-flex align-items-center gap-3 ms-lg-3 sf-header-actions">
          <!-- Search trigger -->
          <button class="btn btn-link sf-icon-btn" type="button" id="sf-search-toggle" aria-label="Open search">
            <i class="fas fa-magnifying-glass"></i>
          </button>

          <!-- Cart -->
          <a href="index.php?page=cart" class="sf-cart-link position-relative">
            <span class="sf-cart-icon-wrapper">
              <i class="fas fa-bag-shopping"></i>
            </span>
            <span class="sf-cart-count-badge" id="sf-cart-count">0</span>
          </a>
        </div>
      </div>
    </div>
  </nav>

  <!-- Full-screen search overlay -->
  <div class="sf-search-overlay" id="sf-search-overlay" aria-hidden="true">
    <div class="sf-search-overlay-backdrop"></div>
    <div class="sf-search-overlay-content container">
      <button class="sf-search-overlay-close" type="button" id="sf-search-close" aria-label="Close search">
        <i class="fas fa-xmark"></i>
      </button>
      <form class="sf-search-overlay-form" method="GET" action="index.php">
        <input type="hidden" name="page" value="shop">
        <div class="sf-search-overlay-field">
          <span class="sf-search-overlay-icon">
            <i class="fas fa-magnifying-glass"></i>
          </span>
          <input
            type="text"
            name="search"
            class="sf-search-overlay-input"
            placeholder="Search for products, collections, or categories"
            value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>"
            autocomplete="off"
          >
        </div>
      </form>
      <p class="sf-search-overlay-tip">
        Press <kbd>Esc</kbd> to close
      </p>
    </div>
  </div>
</header>

<main class="sf-main">
