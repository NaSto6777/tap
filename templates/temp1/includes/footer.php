    <!-- Footer -->
    <footer class="bg-dark text-light py-5 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-4">
                    <h5><i class="fas fa-store"></i> <?php echo $settings->getSetting('site_name', 'Ecommerce Store'); ?></h5>
                    <p><?php echo $settings->getSetting('site_description', 'A powerful multi-template ecommerce solution'); ?></p>
                </div>
                <div class="col-md-4">
                    <h5>Quick Links</h5>
                    <ul class="list-unstyled">
                        <li><a href="index.php" class="text-light">Home</a></li>
                        <li><a href="index.php?page=shop" class="text-light">Shop</a></li>
                        <li><a href="index.php?page=about" class="text-light">About</a></li>
                        <li><a href="index.php?page=contact" class="text-light">Contact</a></li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <h5>Contact Info</h5>
                    <?php if ($settings->getSetting('contact_email', '')): ?>
                        <p><i class="fas fa-envelope"></i> <?php echo $settings->getSetting('contact_email', 'contact@example.com'); ?></p>
                    <?php endif; ?>
                    <?php if ($settings->getSetting('contact_phone', '')): ?>
                        <p><i class="fas fa-phone"></i> <?php echo $settings->getSetting('contact_phone', '+1 234 567 8900'); ?></p>
                    <?php endif; ?>
                    <?php if ($settings->getSetting('contact_address', '')): ?>
                        <p><i class="fas fa-map-marker-alt"></i> <?php echo nl2br(htmlspecialchars($settings->getSetting('contact_address', ''))); ?></p>
                    <?php endif; ?>
                    
                    <!-- Newsletter Signup -->
                    <?php
                    require_once __DIR__ . '/../../../config/plugin_helper.php';
                    $pluginHelper = new PluginHelper();
                    if ($pluginHelper->isPluginActive('mailchimp')): ?>
                    <div class="mt-3">
                        <h6>Newsletter</h6>
                        <form id="newsletter-form" class="d-flex">
                            <input type="email" class="form-control form-control-sm" placeholder="Your email" name="email" required>
                            <button type="submit" class="btn btn-primary btn-sm ms-2">Subscribe</button>
                        </form>
                        <small class="text-muted">Get updates and special offers</small>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Social Media Links -->
                    <div class="mt-3">
                        <?php if ($settings->getSetting('facebook_url', '')): ?>
                            <a href="<?php echo $settings->getSetting('facebook_url'); ?>" target="_blank" class="text-light me-3" title="Facebook">
                                <i class="fab fa-facebook fa-lg"></i>
                            </a>
                        <?php endif; ?>
                        <?php if ($settings->getSetting('twitter_url', '')): ?>
                            <a href="<?php echo $settings->getSetting('twitter_url'); ?>" target="_blank" class="text-light me-3" title="Twitter">
                                <i class="fab fa-twitter fa-lg"></i>
                            </a>
                        <?php endif; ?>
                        <?php if ($settings->getSetting('instagram_url', '')): ?>
                            <a href="<?php echo $settings->getSetting('instagram_url'); ?>" target="_blank" class="text-light me-3" title="Instagram">
                                <i class="fab fa-instagram fa-lg"></i>
                            </a>
                        <?php endif; ?>
                        <?php if ($settings->getSetting('linkedin_url', '')): ?>
                            <a href="<?php echo $settings->getSetting('linkedin_url'); ?>" target="_blank" class="text-light me-3" title="LinkedIn">
                                <i class="fab fa-linkedin fa-lg"></i>
                            </a>
                        <?php endif; ?>
                        <?php if ($settings->getSetting('youtube_url', '')): ?>
                            <a href="<?php echo $settings->getSetting('youtube_url'); ?>" target="_blank" class="text-light" title="YouTube">
                                <i class="fab fa-youtube fa-lg"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <hr class="my-4">
            <div class="row">
                <div class="col-md-6">
                    <p>&copy; <?php echo date('Y'); ?> <?php echo $settings->getSetting('site_name', 'Ecommerce Store'); ?>. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-end">
                    <a href="index.php?page=terms" class="text-light me-3">Terms & Conditions</a>
                    <a href="index.php?page=privacy" class="text-light">Privacy Policy</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- WhatsApp Widget -->
    <?php include __DIR__ . '/whatsapp_widget.php'; ?>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="templates/<?php echo $activeTemplate; ?>/assets/js/script.js"></script>
    
    <!-- Analytics Tracker -->
    <?php include __DIR__ . '/../../../config/analytics_tracker.php'; ?>
    
    <script>
    // Newsletter form handling
    document.addEventListener('DOMContentLoaded', function() {
        const newsletterForm = document.getElementById('newsletter-form');
        if (newsletterForm) {
            newsletterForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                const email = formData.get('email');
                
                fetch('newsletter_subscribe.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Successfully subscribed to newsletter!');
                        this.reset();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred. Please try again.');
                });
            });
        }
    });
    </script>
</body>
</html>
