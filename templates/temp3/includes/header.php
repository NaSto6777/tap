<?php
/**
 * TEMP3 — Header
 * High-conversion mobile-first boutique
 *
 * Assumptions:
 * - $settings is a Settings instance (store-scoped)
 * - $activeTemplate and $page are defined in index.php
 */

$siteName   = $settings->getSetting('site_name', 'Boutique');
$siteTagline = $settings->getSetting('site_tagline', 'Curated pieces for modern living.');
$logo_src   = ImageHelper::getSiteLogo();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="description" content="<?php echo htmlspecialchars($settings->getSetting('meta_description', $siteTagline)); ?>">
    <title><?php echo htmlspecialchars($settings->getSetting('meta_title', $siteName)); ?></title>

    <!-- Google Font: Outfit -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
      tailwind.config = {
        theme: {
          extend: {
            fontFamily: {
              sans: ['Outfit', 'ui-sans-serif', 'system-ui', 'sans-serif'],
            },
            colors: {
              brand: {
                50:  '#f9fafb',
                100: '#f3f4f6',
                200: '#e5e7eb',
                300: '#d1d5db',
                400: '#9ca3af',
                500: '#6b7280',
                600: '#4b5563',
                700: '#374151',
                800: '#1f2933',
                900: '#111827',
              },
              accent: '#111827',
            },
            boxShadow: {
              'soft': '0 18px 45px rgba(15,23,42,0.16)',
            }
          }
        }
      }
    </script>

    <!-- Small utility overrides -->
    <style>
      body {
        font-family: 'Outfit', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
      }
      .bt-safe-area-bottom {
        padding-bottom: env(safe-area-inset-bottom);
      }
    </style>
</head>
<body class="min-h-screen bg-brand-50 text-brand-900 flex flex-col">

<!-- Top bar / desktop header -->
<header class="border-b border-brand-100 bg-white/90 backdrop-blur-sm">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-3 flex items-center justify-between gap-4">
        <div class="flex items-center gap-2">
            <?php if ($logo_src): ?>
                <img src="<?php echo htmlspecialchars($logo_src); ?>"
                     alt="<?php echo htmlspecialchars($siteName); ?>"
                     class="h-8 w-8 rounded-full object-cover border border-brand-200">
            <?php endif; ?>
            <div class="leading-tight">
                <div class="text-sm font-semibold tracking-tight"><?php echo htmlspecialchars($siteName); ?></div>
                <div class="text-[11px] text-brand-500"><?php echo htmlspecialchars($siteTagline); ?></div>
            </div>
        </div>

        <nav class="hidden sm:flex items-center gap-6 text-xs font-medium text-brand-500">
            <a href="index.php" class="<?php echo ($page ?? '') === 'home' ? 'text-brand-900' : 'hover:text-brand-800'; ?>">Home</a>
            <a href="index.php?page=shop" class="<?php echo ($page ?? '') === 'shop' ? 'text-brand-900' : 'hover:text-brand-800'; ?>">Shop</a>
            <a href="index.php?page=about" class="<?php echo ($page ?? '') === 'about' ? 'text-brand-900' : 'hover:text-brand-800'; ?>">About</a>
            <a href="index.php?page=contact" class="<?php echo ($page ?? '') === 'contact' ? 'text-brand-900' : 'hover:text-brand-800'; ?>">Contact</a>
        </nav>

        <a href="index.php?page=cart"
           class="relative inline-flex items-center justify-center rounded-full border border-brand-200 px-3 py-1.5 text-xs font-medium text-brand-800 hover:border-brand-300 hover:bg-brand-50 transition">
            <span class="mr-2">Cart</span>
            <span id="bt-cart-count"
                  class="inline-flex h-5 min-w-[20px] items-center justify-center rounded-full bg-black text-[11px] font-semibold text-white">
              0
            </span>
        </a>
    </div>
</header>

<main class="flex-1 w-full max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 pt-6 pb-20 sm:pb-10">
