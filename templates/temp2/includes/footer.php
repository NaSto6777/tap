<?php
// Assumptions:
// - $settings, $activeTemplate are available from index.php
?>
    </main>

    <footer class="sf-footer border-top">
        <div class="container py-5">
            <div class="row g-4">
                <!-- Brand / Description -->
                <div class="col-12 col-md-4">
                    <h5 class="sf-footer-title">
                        <?php echo htmlspecialchars($settings->getSetting('site_name', 'Storefront')); ?>
                    </h5>
                    <p class="sf-footer-text">
                        <?php echo htmlspecialchars($settings->getSetting('site_description', 'A curated selection of modern products.')); ?>
                    </p>
                </div>

                <!-- Links -->
                <div class="col-6 col-md-4">
                    <h6 class="sf-footer-subtitle">Explore</h6>
                    <ul class="list-unstyled sf-footer-links">
                        <li><a href="index.php">Home</a></li>
                        <li><a href="index.php?page=shop">Shop</a></li>
                        <li><a href="index.php?page=about">About</a></li>
                        <li><a href="index.php?page=contact">Contact</a></li>
                        <li><a href="index.php?page=terms">Terms &amp; Conditions</a></li>
                        <li><a href="index.php?page=privacy">Privacy Policy</a></li>
                    </ul>
                </div>

                <!-- Newsletter / Social -->
                <div class="col-6 col-md-4">
                    <h6 class="sf-footer-subtitle">Stay in the loop</h6>
                    <form id="sf-newsletter-form" class="sf-newsletter-form">
                        <div class="input-group mb-2">
                            <input
                                type="email"
                                class="form-control sf-newsletter-input"
                                name="email"
                                required
                                placeholder="Your email address"
                                autocomplete="email"
                            >
                            <button class="btn btn-outline-light sf-newsletter-btn" type="submit">
                                <span class="sf-newsletter-btn-text">Join</span>
                                <span class="sf-newsletter-btn-spinner d-none">
                                    <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                                </span>
                            </button>
                        </div>
                        <small class="sf-footer-text text-muted d-block">
                            Get curated drops, stories, and exclusive offers.
                        </small>
                        <div id="sf-newsletter-feedback" class="sf-newsletter-feedback mt-2" role="status" aria-live="polite"></div>
                    </form>

                    <div class="sf-footer-social mt-3">
                        <?php if ($settings->getSetting('facebook_url', '')): ?>
                            <a href="<?php echo htmlspecialchars($settings->getSetting('facebook_url', '')); ?>" target="_blank" rel="noreferrer" class="sf-social-link">
                                <i class="fab fa-facebook-f"></i>
                            </a>
                        <?php endif; ?>
                        <?php if ($settings->getSetting('instagram_url', '')): ?>
                            <a href="<?php echo htmlspecialchars($settings->getSetting('instagram_url', '')); ?>" target="_blank" rel="noreferrer" class="sf-social-link">
                                <i class="fab fa-instagram"></i>
                            </a>
                        <?php endif; ?>
                        <?php if ($settings->getSetting('twitter_url', '')): ?>
                            <a href="<?php echo htmlspecialchars($settings->getSetting('twitter_url', '')); ?>" target="_blank" rel="noreferrer" class="sf-social-link">
                                <i class="fab fa-x-twitter"></i>
                            </a>
                        <?php endif; ?>
                        <?php if ($settings->getSetting('linkedin_url', '')): ?>
                            <a href="<?php echo htmlspecialchars($settings->getSetting('linkedin_url', '')); ?>" target="_blank" rel="noreferrer" class="sf-social-link">
                                <i class="fab fa-linkedin-in"></i>
                            </a>
                        <?php endif; ?>
                        <?php if ($settings->getSetting('youtube_url', '')): ?>
                            <a href="<?php echo htmlspecialchars($settings->getSetting('youtube_url', '')); ?>" target="_blank" rel="noreferrer" class="sf-social-link">
                                <i class="fab fa-youtube"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="d-flex flex-column flex-md-row justify-content-between align-items-center pt-4 mt-4 border-top sf-footer-bottom">
                <p class="mb-2 mb-md-0 sf-footer-text">
                    &copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($settings->getSetting('site_name', 'Storefront')); ?>. All rights reserved.
                </p>
                <p class="mb-0 sf-footer-text text-muted small">
                    Crafted for a modern, multi-tenant ecommerce experience.
                </p>
            </div>
        </div>
    </footer>

    <!-- Toast container for global notifications (cart, errors, etc.) -->
    <div class="sf-toast-container position-fixed top-0 start-50 translate-middle-x p-3" style="z-index: 1080;"></div>

    <!-- Cart side drawer (AJAX summary) -->
    <div class="sf-cart-drawer-backdrop" id="sf-cart-drawer-backdrop"></div>
    <aside class="sf-cart-drawer" id="sf-cart-drawer" aria-hidden="true">
        <div class="sf-cart-drawer-header d-flex justify-content-between align-items-center">
            <div>
                <h2 class="h6 mb-0 text-white">Your bag</h2>
                <p class="small text-secondary mb-0">Recently added items</p>
            </div>
            <button class="sf-cart-drawer-close" type="button" id="sf-cart-drawer-close" aria-label="Close cart">
                <i class="fas fa-xmark"></i>
            </button>
        </div>
        <div class="sf-cart-drawer-body" id="sf-cart-drawer-body">
            <!-- Filled via AJAX -->
            <div class="sf-cart-drawer-empty text-center py-4">
                <p class="text-secondary mb-1">Your bag is empty.</p>
                <a href="index.php?page=shop" class="sf-btn-outline mt-2">Start shopping</a>
            </div>
        </div>
        <div class="sf-cart-drawer-footer" id="sf-cart-drawer-footer">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="small text-secondary">Subtotal</span>
                <span class="fw-semibold text-white" id="sf-cart-drawer-subtotal">0</span>
            </div>
            <a href="index.php?page=cart" class="btn btn-light w-100 rounded-pill mb-2">
                View full cart
            </a>
            <a href="index.php?page=checkout" class="btn btn-primary w-100 rounded-pill">
                Checkout
            </a>
        </div>
    </aside>

    <!-- Sticky mobile bottom bar -->
    <nav class="sf-mobile-bar d-md-none">
        <a href="index.php" class="sf-mobile-bar-item <?php echo ($page ?? '') === 'home' ? 'active' : ''; ?>">
            <i class="fas fa-house"></i>
            <span>Home</span>
        </a>
        <a href="index.php?page=shop" class="sf-mobile-bar-item <?php echo ($page ?? '') === 'shop' ? 'active' : ''; ?>">
            <i class="fas fa-grid-2"></i>
            <span>Shop</span>
        </a>
        <button class="sf-mobile-bar-item" type="button" id="sf-mobile-search-toggle">
            <i class="fas fa-magnifying-glass"></i>
            <span>Search</span>
        </button>
        <button class="sf-mobile-bar-item" type="button" id="sf-mobile-cart-toggle">
            <span class="position-relative d-inline-flex">
                <i class="fas fa-bag-shopping"></i>
                <span class="sf-mobile-cart-badge" id="sf-mobile-cart-count">0</span>
            </span>
            <span>Cart</span>
        </button>
    </nav>

    <!-- WhatsApp / Floating widgets (reuse existing if needed) -->
    <?php
    $whatsappWidgetPath = __DIR__ . '/whatsapp_widget.php';
    if (file_exists($whatsappWidgetPath)) {
        include $whatsappWidgetPath;
    }
    ?>

    <!-- Bootstrap JS bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Template JS -->
    <script src="templates/<?php echo htmlspecialchars($activeTemplate); ?>/assets/js/script.js"></script>

    <!-- Analytics Tracker -->
    <?php include __DIR__ . '/../../../config/analytics_tracker.php'; ?>
</body>
</html>
