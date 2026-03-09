<div class="container py-5">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <h1 class="text-center mb-5">About Us</h1>
            
            <div class="row mb-5">
                <div class="col-md-6">
                    <?php
                    $about_image = $settings->getSetting('about_image', '');
                    if (empty($about_image) || !file_exists($about_image)) {
                        $about_image = 'uploads/placeholder.jpg';
                    }
                    ?>
                    <img src="<?php echo $about_image; ?>" class="img-fluid rounded" alt="About Us" 
                         onerror="this.src='uploads/placeholder.jpg'"
                         style="width: 100%; height: 400px; object-fit: cover;">
                </div>
                <div class="col-md-6">
                    <h3>Our Story</h3>
                    <p><?php echo nl2br(htmlspecialchars($settings->getSetting('about_content', 'We are a passionate team dedicated to providing high-quality products and exceptional customer service. Our journey began with a simple mission: to make shopping easy, enjoyable, and accessible to everyone.'))); ?></p>
                    <p>With years of experience in the industry, we have built a reputation for reliability, quality, and customer satisfaction. We continuously strive to improve our offerings and expand our product range to meet the diverse needs of our customers.</p>
                </div>
            </div>
            
            <div class="row mb-5">
                <div class="col-12">
                    <h3 class="text-center mb-4">Why Choose Us?</h3>
                </div>
                <div class="col-md-4 text-center mb-4">
                    <i class="fas fa-award fa-3x text-primary mb-3"></i>
                    <h5>Quality Products</h5>
                    <p>We carefully curate our product selection to ensure only the highest quality items reach our customers.</p>
                </div>
                <div class="col-md-4 text-center mb-4">
                    <i class="fas fa-shipping-fast fa-3x text-primary mb-3"></i>
                    <h5>Fast Shipping</h5>
                    <p>Quick and reliable delivery to your doorstep with tracking information provided for all orders.</p>
                </div>
                <div class="col-md-4 text-center mb-4">
                    <i class="fas fa-headset fa-3x text-primary mb-3"></i>
                    <h5>24/7 Support</h5>
                    <p>Our dedicated customer support team is always ready to help you with any questions or concerns.</p>
                </div>
            </div>
            
            <div class="row">
                <div class="col-12">
                    <h3 class="text-center mb-4">Our Mission</h3>
                    <p class="text-center lead"><?php echo $settings->getSetting('mission_statement', 'To provide exceptional products and services that enhance our customers\' lives while maintaining the highest standards of quality and integrity.'); ?></p>
                </div>
            </div>
        </div>
    </div>
</div>
