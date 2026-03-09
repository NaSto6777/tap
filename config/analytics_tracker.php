<?php
/**
 * Analytics Tracker Include - Loads and initializes the tracker for any template
 * This file should be included in template footers to enable analytics tracking
 */

// Ensure required classes are loaded
if (!isset($settings)) {
    try {
        require_once __DIR__ . '/settings.php';
        $settings = new Settings();
    } catch (Exception $e) {
        echo "<!-- Analytics Error: Could not load settings - " . htmlspecialchars($e->getMessage()) . " -->";
        return;
    }
}

// Check if analytics is enabled
$analyticsEnabled = $settings->getSetting('analytics_enabled', '1') === '1';

if (!$analyticsEnabled) {
    echo "<!-- Analytics: Disabled in settings -->";
    return; // Exit if analytics is disabled
}

// Get debug mode from settings
$debugMode = $settings->getSetting('analytics_debug', '0') === '1';

// Get analytics configuration
$trackClicks = $settings->getSetting('analytics_track_buttons', '1') === '1';
$trackForms = $settings->getSetting('analytics_track_forms', '1') === '1';
$trackSearches = $settings->getSetting('analytics_track_searches', '1') === '1';
$debugMode = $settings->getSetting('analytics_debug', '0') === '1';

// Get current page context for specific tracking
$currentPage = $_GET['page'] ?? 'home';
$currentUrl = $_SERVER['REQUEST_URI'] ?? '';

// Use the actual current URL for tracking (don't normalize)
$normalizedUrl = $currentUrl;

// Better page title extraction
$currentTitle = '';
if (isset($page_title) && !empty($page_title)) {
    $currentTitle = $page_title;
} else {
    // Generate title based on page
    switch ($currentPage) {
        case 'home':
            $currentTitle = 'Home';
            break;
        case 'shop':
            $currentTitle = 'Shop';
            break;
        case 'about':
            $currentTitle = 'About Us';
            break;
        case 'contact':
            $currentTitle = 'Contact';
            break;
        case 'product_view':
            $productId = $_GET['id'] ?? '';
            $currentTitle = 'Product View' . ($productId ? ' - Item ' . $productId : '');
            break;
        case 'cart':
            $currentTitle = 'Shopping Cart';
            break;
        case 'checkout':
            $currentTitle = 'Checkout';
            break;
        case 'checkout_success':
            $currentTitle = 'Order Confirmation';
            break;
        case 'privacy':
            $currentTitle = 'Privacy Policy';
            break;
        case 'terms':
            $currentTitle = 'Terms & Conditions';
            break;
        default:
            $currentTitle = ucfirst(str_replace('_', ' ', $currentPage));
    }
}

// Get product ID if on product view page
$productId = null;
if ($currentPage === 'product_view' && isset($_GET['id'])) {
    $productId = (int)$_GET['id'];
}

// Get cart data for abandonment tracking
$cartData = [];
if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
    $cartData = $_SESSION['cart'];
}

// Get customer info if available (from session or form data)
$customerEmail = $_SESSION['customer_email'] ?? null;
$customerPhone = $_SESSION['customer_phone'] ?? null;
$customerName = $_SESSION['customer_name'] ?? null;
?>

<!-- Analytics Tracker -->
<script src="config/tracker.js"></script>
<script>
// Analytics Configuration
window.AnalyticsConfig = {
    endpoint: 'config/analytics_endpoint.php',
    sessionId: '<?php echo session_id(); ?>',
    trackClicks: <?php echo $trackClicks ? 'true' : 'false'; ?>,
    trackForms: <?php echo $trackForms ? 'true' : 'false'; ?>,
    trackProducts: true,
    trackSearches: <?php echo $trackSearches ? 'true' : 'false'; ?>,
    debug: <?php echo $debugMode ? 'true' : 'false'; ?>
};

// Track page view on server side (outside JavaScript)
<?php
// Debug: Always show basic info for troubleshooting
echo "<!-- Analytics Tracker: PHP is executing -->";
echo "<!-- Debug Info: page={$currentPage}, url={$normalizedUrl}, title={$currentTitle}, debug={$debugMode} -->";

try {
    // Ensure AnalyticsHelper is available
    if (!class_exists('AnalyticsHelper')) {
        require_once __DIR__ . '/analytics_helper.php';
    }
    
    $analytics = new AnalyticsHelper();
    $result = $analytics->trackPageView($normalizedUrl, $currentTitle);
    echo "<!-- Analytics: Page view tracked for {$normalizedUrl} with title '{$currentTitle}' - Result: " . ($result ? 'SUCCESS' : 'FAILED') . " -->";
} catch (Exception $e) {
    echo "<!-- Analytics Error: " . htmlspecialchars($e->getMessage()) . " -->";
    echo "<!-- Analytics Error Details: " . htmlspecialchars($e->getTraceAsString()) . " -->";
}
?>

<?php
// Page-specific server-side tracking
if ($currentPage === 'product_view' && $productId) {
    try {
        $analytics->trackProductView($productId);
        if ($debugMode) {
            echo "<!-- Analytics: Product view tracked for product ID {$productId} -->";
        }
    } catch (Exception $e) {
        if ($debugMode) {
            echo "<!-- Analytics Error: " . htmlspecialchars($e->getMessage()) . " -->";
        }
    }
}

if ($currentPage === 'cart') {
    try {
        $analytics->trackEvent('cart_view', [
            'cart_items' => count($cartData),
            'cart_data' => $cartData
        ]);
        if ($debugMode) {
            echo "<!-- Analytics: Cart view tracked -->";
        }
    } catch (Exception $e) {
        if ($debugMode) {
            echo "<!-- Analytics Error: " . htmlspecialchars($e->getMessage()) . " -->";
        }
    }
}

if ($currentPage === 'checkout') {
    try {
        $analytics->trackFunnelStep('checkout_start');
        if ($debugMode) {
            echo "<!-- Analytics: Checkout start tracked -->";
        }
    } catch (Exception $e) {
        if ($debugMode) {
            echo "<!-- Analytics Error: " . htmlspecialchars($e->getMessage()) . " -->";
        }
    }
}

if ($currentPage === 'checkout_success') {
    try {
        $analytics->trackFunnelStep('purchase_complete');
        if ($debugMode) {
            echo "<!-- Analytics: Purchase complete tracked -->";
        }
    } catch (Exception $e) {
        if ($debugMode) {
            echo "<!-- Analytics Error: " . htmlspecialchars($e->getMessage()) . " -->";
        }
    }
}

// Track abandoned cart if cart has items
if (!empty($cartData)) {
    try {
            $analytics->trackAbandonedCart(session_id(), $cartData, $customerEmail, $customerPhone, $customerName);
        if ($debugMode) {
            echo "<!-- Analytics: Abandoned cart tracked -->";
        }
    } catch (Exception $e) {
        if ($debugMode) {
            echo "<!-- Analytics Error: " . htmlspecialchars($e->getMessage()) . " -->";
        }
    }
}
?>

// Initialize Analytics
document.addEventListener('DOMContentLoaded', function() {
    if (typeof Analytics !== 'undefined') {
        Analytics.init(window.AnalyticsConfig);
    }
});

// Add tracking data attributes to common elements
document.addEventListener('DOMContentLoaded', function() {
    // Add tracking to product cards
    const productCards = document.querySelectorAll('.card, .product-item, [data-product-id]');
    productCards.forEach(function(card) {
        const productId = card.getAttribute('data-product-id') || 
                         card.querySelector('[data-product-id]')?.getAttribute('data-product-id');
        if (productId) {
            card.setAttribute('data-track-event', 'product_click');
            card.setAttribute('data-track-product-id', productId);
        }
    });
    
    // Add tracking to add to cart buttons
    const addToCartButtons = document.querySelectorAll('button[onclick*="addToCart"], .add-to-cart, [data-action="add-to-cart"]');
    addToCartButtons.forEach(function(button) {
        const productId = button.getAttribute('data-product-id') || 
                         button.closest('[data-product-id]')?.getAttribute('data-product-id');
        if (productId) {
            button.setAttribute('data-track-event', 'add_to_cart');
            button.setAttribute('data-track-product-id', productId);
            button.setAttribute('data-track-label', 'Add to Cart');
        }
    });
    
    // Add tracking to CTA buttons
    const ctaButtons = document.querySelectorAll('.btn-primary, .btn-success, .btn-warning, .cta-button');
    ctaButtons.forEach(function(button) {
        if (!button.getAttribute('data-track-event')) {
            const buttonText = button.textContent.trim() || button.value || 'CTA Button';
            button.setAttribute('data-track-event', 'cta_click');
            button.setAttribute('data-track-label', buttonText);
        }
    });
    
    // Add tracking to navigation links
    const navLinks = document.querySelectorAll('.navbar-nav a, .nav-link');
    navLinks.forEach(function(link) {
        if (!link.getAttribute('data-track-event')) {
            const linkText = link.textContent.trim();
            link.setAttribute('data-track-event', 'nav_click');
            link.setAttribute('data-track-label', linkText);
        }
    });
    
    // Add tracking to search forms
    const searchForms = document.querySelectorAll('form[action*="search"], form input[name*="search"]');
    searchForms.forEach(function(form) {
        form.setAttribute('data-track-event', 'search_submit');
    });
    
    // Add tracking to newsletter forms
    const newsletterForms = document.querySelectorAll('#newsletter-form, .newsletter-form, form[action*="newsletter"]');
    newsletterForms.forEach(function(form) {
        form.setAttribute('data-track-event', 'newsletter_signup');
    });
    
    // Add tracking to contact forms
    const contactForms = document.querySelectorAll('#contact-form, .contact-form, form[action*="contact"]');
    contactForms.forEach(function(form) {
        form.setAttribute('data-track-event', 'contact_form');
    });
});

// Enhanced cart tracking for abandonment
function trackCartChanges() {
    if (typeof Analytics !== 'undefined') {
        // Monitor cart changes
        const originalCartUpdate = window.updateCartCount;
        if (originalCartUpdate) {
            window.updateCartCount = function() {
                originalCartUpdate();
                // Track cart update
                Analytics.track('cart_update', {
                    cart_items: document.getElementById('cart-count')?.textContent || 0
                });
            };
        }
        
        // Track cart actions
        document.addEventListener('click', function(e) {
            if (e.target.matches('[data-action="add-to-cart"], .add-to-cart, button[onclick*="addToCart"]')) {
                setTimeout(function() {
                    Analytics.track('cart_update', {
                        action: 'add_to_cart',
                        cart_items: document.getElementById('cart-count')?.textContent || 0
                    });
                }, 100);
            }
            
            if (e.target.matches('[data-action="remove-from-cart"], .remove-from-cart, button[onclick*="removeFromCart"]')) {
                setTimeout(function() {
                    Analytics.track('cart_update', {
                        action: 'remove_from_cart',
                        cart_items: document.getElementById('cart-count')?.textContent || 0
                    });
                }, 100);
            }
        });
    }
}

// Initialize cart tracking
document.addEventListener('DOMContentLoaded', trackCartChanges);

// Track form submissions with enhanced data
document.addEventListener('submit', function(e) {
    if (typeof Analytics !== 'undefined') {
        const form = e.target;
        const formName = form.name || form.id || 'Unknown Form';
        
        // Extract form data
        const formData = {};
        const formElements = form.elements;
        for (let i = 0; i < formElements.length; i++) {
            const element = formElements[i];
            if (element.name && element.type !== 'password') {
                formData[element.name] = element.value;
            }
        }
        
        // Track specific form types
        if (form.matches('#newsletter-form, .newsletter-form')) {
            Analytics.track('newsletter_signup', formData);
        } else if (form.matches('#contact-form, .contact-form')) {
            Analytics.track('contact_form', formData);
        } else if (form.matches('form[action*="search"]')) {
            Analytics.track('search_submit', formData);
        } else {
            Analytics.trackFormSubmission(formName, formData);
        }
    }
});

// Track page performance
window.addEventListener('load', function() {
    if (typeof Analytics !== 'undefined') {
        const loadTime = performance.timing.loadEventEnd - performance.timing.navigationStart;
        Analytics.track('page_performance', {
            load_time: loadTime,
            page_url: window.location.href,
            page_title: document.title
        });
    }
});

// Track time on page
const startTime = Date.now();
window.addEventListener('beforeunload', function() {
    if (typeof Analytics !== 'undefined') {
        const timeOnPage = Date.now() - startTime;
        Analytics.track('time_on_page', {
            time_on_page: timeOnPage,
            page_url: window.location.href
        });
    }
});
</script>

<!-- Analytics Debug Info (only in debug mode) -->
<?php if ($debugMode): ?>
<script>
console.log('Analytics Debug Info:', {
    page: '<?php echo $currentPage; ?>',
    url: '<?php echo $currentUrl; ?>',
    normalizedUrl: '<?php echo $normalizedUrl; ?>',
    title: '<?php echo addslashes($currentTitle); ?>',
    productId: <?php echo $productId ?: 'null'; ?>,
    cartItems: <?php echo count($cartData); ?>,
    sessionId: '<?php echo session_id(); ?>',
    analyticsEnabled: <?php echo $analyticsEnabled ? 'true' : 'false'; ?>
});
</script>
<?php endif; ?>
