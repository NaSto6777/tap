<?php
require_once __DIR__ . '/../../config/plugin_helper.php';
require_once __DIR__ . '/../../config/email_helper.php';
require_once __DIR__ . '/../../config/CsrfHelper.php';

$pluginHelper = new PluginHelper();
$emailService = new EmailService();
$success_message = '';
$error_message = '';

// Handle contact form submission
if ($_POST) {
    if (!CsrfHelper::validateToken($_POST['csrf_token'] ?? '')) {
        $error_message = 'Invalid security token. Please refresh the page and try again.';
    }
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $subject = $_POST['subject'] ?? '';
    $message = $_POST['message'] ?? '';
    
    // Verify reCAPTCHA if plugin is active
    if ($pluginHelper->isPluginActive('recaptcha')) {
        $recaptcha_token = $_POST['g-recaptcha-response'] ?? '';
        if (!$pluginHelper->verifyRecaptcha($recaptcha_token)) {
            $error_message = 'Please complete the reCAPTCHA verification.';
        }
    }
    
    if (!empty($name) && !empty($email) && !empty($subject) && !empty($message) && empty($error_message)) {
        // Send email to admin
        $formData = [
            'name' => $name,
            'email' => $email,
            'subject' => $subject,
            'message' => $message
        ];
        
        if ($emailService->sendContactFormNotification($formData)) {
            $success_message = 'Thank you for your message! We will get back to you soon.';
            // Clear form
            $_POST = [];
        } else {
            $error_message = 'Sorry, there was an error sending your message. Please try again.';
        }
    } else {
        $error_message = 'Please fill in all required fields.';
    }
}
?>

<div class="container py-5">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <h1 class="text-center mb-5">Contact Us</h1>
            
            <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo htmlspecialchars($success_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5>Get in Touch</h5>
                            <p>We'd love to hear from you. Send us a message and we'll respond as soon as possible.</p>
                            
                            <div class="mb-3">
                                <i class="fas fa-envelope text-primary me-2"></i>
                                <strong>Email:</strong> <?php echo $settings->getSetting('contact_email', 'contact@example.com'); ?>
                            </div>
                            
                            <div class="mb-3">
                                <i class="fas fa-phone text-primary me-2"></i>
                                <strong>Phone:</strong> <?php echo $settings->getSetting('contact_phone', '+1 234 567 8900'); ?>
                            </div>
                            
                            <div class="mb-3">
                                <i class="fas fa-clock text-primary me-2"></i>
                                <strong>Business Hours:</strong><br>
                                Monday - Friday: 9:00 AM - 6:00 PM<br>
                                Saturday: 10:00 AM - 4:00 PM<br>
                                Sunday: Closed
                            </div>
                            
                            <div class="mb-3">
                                <h6>Follow Us</h6>
                                <div class="d-flex gap-2">
                                    <?php if ($settings->getSetting('facebook_url')): ?>
                                        <a href="<?php echo $settings->getSetting('facebook_url'); ?>" class="btn btn-outline-primary btn-sm">
                                            <i class="fab fa-facebook"></i>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if ($settings->getSetting('twitter_url')): ?>
                                        <a href="<?php echo $settings->getSetting('twitter_url'); ?>" class="btn btn-outline-info btn-sm">
                                            <i class="fab fa-twitter"></i>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if ($settings->getSetting('instagram_url')): ?>
                                        <a href="<?php echo $settings->getSetting('instagram_url'); ?>" class="btn btn-outline-danger btn-sm">
                                            <i class="fab fa-instagram"></i>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if ($settings->getSetting('linkedin_url')): ?>
                                        <a href="<?php echo $settings->getSetting('linkedin_url'); ?>" class="btn btn-outline-primary btn-sm">
                                            <i class="fab fa-linkedin"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5>Send us a Message</h5>
                            <form method="POST">
                                <?php echo CsrfHelper::getTokenField(); ?>
                                <div class="mb-3">
                                    <label for="name" class="form-label">Name *</label>
                                    <input type="text" class="form-control" id="name" name="name" 
                                           value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email *</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="subject" class="form-label">Subject *</label>
                                    <input type="text" class="form-control" id="subject" name="subject" 
                                           value="<?php echo htmlspecialchars($_POST['subject'] ?? ''); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="message" class="form-label">Message *</label>
                                    <textarea class="form-control" id="message" name="message" rows="5" required><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
                                </div>
                                
                                <?php if ($pluginHelper->isPluginActive('recaptcha')): ?>
                                <div class="mb-3">
                                    <div class="g-recaptcha" data-sitekey="<?php echo htmlspecialchars($pluginHelper->getPluginConfig('recaptcha')['recaptcha_site_key'] ?? ''); ?>"></div>
                                </div>
                                <?php endif; ?>
                                
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-paper-plane"></i> Send Message
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

