<?php
// Get hero images
$heroImages = json_decode($settings->getSetting('hero_images', '[]'), true);
$heroImagePc = $settings->getSetting('hero_image_pc', '');
$heroImageMobile = $settings->getSetting('hero_image_mobile', '');

// Debug database connection
// $testSetting = $settings->getSetting('site_name', 'DATABASE_NOT_WORKING');
// echo "<!-- DEBUG: site_name = " . htmlspecialchars($testSetting) . " -->";

// Debug: Let's see what we're actually getting
// echo "<!-- DEBUG: heroImagePc = " . htmlspecialchars($heroImagePc) . " -->";
// echo "<!-- DEBUG: heroImageMobile = " . htmlspecialchars($heroImageMobile) . " -->";
// echo "<!-- DEBUG: heroImages = " . htmlspecialchars(print_r($heroImages, true)) . " -->";
// echo "<!-- DEBUG: hasHeroImages = " . ($hasHeroImages ? 'true' : 'false') . " -->";

// Get currency settings
$currency = $settings->getSetting('currency', 'USD');
$currency_symbol = $currency === 'USD' ? '$' : ($currency === 'EUR' ? '€' : ($currency === 'GBP' ? '£' : ($currency === 'TND' ? 'TND' : $currency . ' ')));
$rightPositionCurrencies = ['TND','AED','SAR','QAR','KWD','BHD','OMR','MAD','DZD','EGP'];
$price_position_right = in_array($currency, $rightPositionCurrencies, true);
$price_prefix = $price_position_right ? '' : ($currency_symbol);
$price_suffix = $price_position_right ? (' ' . $currency_symbol) : '';

// Get featured products (scoped to current store)
$store_id = StoreContext::getId();
$database = new Database();
$conn = $database->getConnection();
$query = "SELECT * FROM products WHERE store_id = ? AND featured = 1 AND is_active = 1 ORDER BY created_at DESC LIMIT 8";
$stmt = $conn->prepare($query);
$stmt->execute([$store_id]);
$featured_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- Hero Section -->
<section class="hero-section">
    <?php 
    // Check if we have any hero images
    $hasHeroImages = !empty($heroImagePc) || !empty($heroImageMobile) || !empty($heroImages);
    ?>
    
    <?php if ($hasHeroImages): ?>
        <div id="heroCarousel" class="carousel slide" data-bs-ride="carousel">
            <div class="carousel-indicators">
                <?php 
                // Count total slides (desktop + mobile + additional images)
                $totalSlides = 0;
                if (!empty($heroImagePc)) $totalSlides++;
                if (!empty($heroImageMobile)) $totalSlides++;
                if (!empty($heroImages)) $totalSlides += count($heroImages);
                
                for ($i = 0; $i < $totalSlides; $i++): ?>
                    <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="<?php echo $i; ?>" 
                            <?php echo $i == 0 ? 'class="active"' : ''; ?>></button>
                <?php endfor; ?>
            </div>
            
            <div class="carousel-inner">
                <?php $slideIndex = 0; ?>
                
                <!-- Desktop Hero Image -->
                <?php if (!empty($heroImagePc)): ?>
                    <div class="carousel-item <?php echo $slideIndex == 0 ? 'active' : ''; ?>">
                        <picture>
                            <source media="(max-width: 767px)" srcset="<?php echo htmlspecialchars($heroImageMobile ?: $heroImagePc); ?>">
                            <img src="<?php echo htmlspecialchars($heroImagePc); ?>" class="d-block w-100" alt="Hero Image Desktop" onerror="this.src='uploads/placeholder.jpg'" style="height: 500px; object-fit: cover;">
                        </picture>
                        <div class="carousel-caption d-none d-md-block">
                            <h1><?php echo $settings->getSetting('hero_title', 'Welcome to ' . $settings->getSetting('site_name', 'Our Store')); ?></h1>
                            <p><?php echo $settings->getSetting('hero_subtitle', 'Discover amazing products at great prices'); ?></p>
                            <a href="index.php?page=shop" class="btn btn-primary btn-lg"><?php echo $settings->getSetting('hero_button_text', 'Shop Now'); ?></a>
                        </div>
                        <div class="carousel-caption d-block d-md-none">
                            <h1><?php echo $settings->getSetting('hero_title', 'Welcome to ' . $settings->getSetting('site_name', 'Our Store')); ?></h1>
                            <p><?php echo $settings->getSetting('hero_subtitle', 'Discover amazing products at great prices'); ?></p>
                            <a href="index.php?page=shop" class="btn btn-primary btn-lg"><?php echo $settings->getSetting('hero_button_text', 'Shop Now'); ?></a>
                        </div>
                    </div>
                    <?php $slideIndex++; ?>
                <?php endif; ?>
                
                <!-- Mobile Hero Image (if different from desktop) -->
                <?php if (!empty($heroImageMobile) && $heroImageMobile !== $heroImagePc): ?>
                    <div class="carousel-item <?php echo $slideIndex == 0 ? 'active' : ''; ?>">
                        <img src="<?php echo htmlspecialchars($heroImageMobile); ?>" class="d-block w-100 d-md-none" alt="Hero Image Mobile" onerror="this.src='uploads/placeholder.jpg'">
                        <div class="carousel-caption d-block d-md-none">
                            <h1><?php echo $settings->getSetting('hero_title', 'Welcome to ' . $settings->getSetting('site_name', 'Our Store')); ?></h1>
                            <p><?php echo $settings->getSetting('hero_subtitle', 'Discover amazing products at great prices'); ?></p>
                            <a href="index.php?page=shop" class="btn btn-primary btn-lg"><?php echo $settings->getSetting('hero_button_text', 'Shop Now'); ?></a>
                        </div>
                    </div>
                    <?php $slideIndex++; ?>
                <?php endif; ?>
                
                <!-- Additional Hero Images -->
                <?php if (!empty($heroImages)): ?>
                    <?php foreach ($heroImages as $image): ?>
                        <div class="carousel-item <?php echo $slideIndex == 0 ? 'active' : ''; ?>">
                            <picture>
                                <source media="(max-width: 767px)" srcset="<?php echo htmlspecialchars($heroImageMobile ?: $image); ?>">
                                <img src="<?php echo htmlspecialchars($image); ?>" class="d-block w-100" alt="Hero Image" onerror="this.src='uploads/placeholder.jpg'">
                            </picture>
                            <div class="carousel-caption d-none d-md-block">
                                <h1><?php echo $settings->getSetting('hero_title', 'Welcome to ' . $settings->getSetting('site_name', 'Our Store')); ?></h1>
                                <p><?php echo $settings->getSetting('hero_subtitle', 'Discover amazing products at great prices'); ?></p>
                                <a href="index.php?page=shop" class="btn btn-primary btn-lg"><?php echo $settings->getSetting('hero_button_text', 'Shop Now'); ?></a>
                            </div>
                            <div class="carousel-caption d-block d-md-none">
                                <h1><?php echo $settings->getSetting('hero_title', 'Welcome to ' . $settings->getSetting('site_name', 'Our Store')); ?></h1>
                                <p><?php echo $settings->getSetting('hero_subtitle', 'Discover amazing products at great prices'); ?></p>
                                <a href="index.php?page=shop" class="btn btn-primary btn-lg"><?php echo $settings->getSetting('hero_button_text', 'Shop Now'); ?></a>
                            </div>
                        </div>
                        <?php $slideIndex++; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <?php if ($totalSlides > 1): ?>
            <button class="carousel-control-prev" type="button" data-bs-target="#heroCarousel" data-bs-slide="prev">
                <span class="carousel-control-prev-icon"></span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#heroCarousel" data-bs-slide="next">
                <span class="carousel-control-next-icon"></span>
            </button>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <!-- Fallback when no hero images are set -->
        <div class="hero-placeholder d-flex align-items-center justify-content-center">
            <div class="text-center">
                <h1><?php echo $settings->getSetting('hero_title', 'Welcome to ' . $settings->getSetting('site_name', 'Our Store')); ?></h1>
                <p><?php echo $settings->getSetting('hero_subtitle', 'Discover amazing products at great prices'); ?></p>
                <a href="index.php?page=shop" class="btn btn-primary btn-lg"><?php echo $settings->getSetting('hero_button_text', 'Shop Now'); ?></a>
            </div>
        </div>
    <?php endif; ?>
</section>

<?php 
$categoriesEnabled = $settings->getSetting('categories_enabled', '1');
if ($categoriesEnabled === '1') {
    // Fetch top-level active categories
    $catStmt = $conn->prepare("SELECT id, name, slug, image, level, is_active FROM categories WHERE store_id = ? AND is_active = 1 AND level = 1 ORDER BY sort_order ASC, name ASC");
    $catStmt->execute([$store_id]);
    $categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<?php if (!empty($categories)): ?>
<section class="py-5 bg-light">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <h2 class="text-center mb-5">Shop by Category</h2>
            </div>
        </div>

        <div class="row">
            <?php foreach ($categories as $category): ?>
                <div class="col-lg-3 col-md-4 col-sm-6 mb-4">
                    <a href="index.php?page=shop&category=<?php echo (int)$category['id']; ?>" class="text-decoration-none text-dark">
                        <div class="card h-100 category-card">
                            <?php
                            // Mirror admin logic: check uploads/categories/{id}.{ext}
                            $catDisplay = 'uploads/placeholder.jpg';
                            foreach (['jpg','jpeg','png','gif','webp'] as $ext) {
                                $candidate = "uploads/categories/{$category['id']}.{$ext}";
                                if (file_exists($candidate)) { $catDisplay = $candidate; break; }
                            }
                            // Fallback: if DB image set, normalize path
                            if ($catDisplay === 'uploads/placeholder.jpg' && !empty($category['image'])) {
                                $img = $category['image'];
                                if (strpos($img, 'admin/uploads/') === 0) { $img = substr($img, strlen('admin/')); }
                                if (strpos($img, 'uploads/') !== 0) { $img = "uploads/categories/{$category['id']}/" . ltrim($img, '/'); }
                                $catDisplay = $img;
                            }
                            ?>
                            <img src="<?php echo htmlspecialchars($catDisplay); ?>" loading="lazy"
                                 class="card-img-top" alt="<?php echo htmlspecialchars($category['name']); ?>"
                                 onerror="this.src='uploads/placeholder.jpg'"
                                 style="height: 200px; object-fit: cover;">
                            <div class="card-body d-flex align-items-center justify-content-center">
                                <h5 class="card-title mb-0 text-center"><?php echo htmlspecialchars($category['name']); ?></h5>
                            </div>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Featured Products Section -->
<section class="py-5">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <h2 class="text-center mb-5">Featured Products</h2>
            </div>
        </div>
        
        <div class="row">
            <?php if (!empty($featured_products)): ?>
                <?php foreach ($featured_products as $product): ?>
                    <div class="col-lg-3 col-md-6 mb-4">
                        <div class="card h-100">
                            <?php
                            $product_image = "uploads/products/{$product['id']}/main.jpg";
                            $image_exists = file_exists($product_image);
                            $display_image = $image_exists ? $product_image : 'uploads/placeholder.jpg';
                            ?>
                            <img src="<?php echo $display_image; ?>" loading="lazy" 
                                 class="card-img-top" alt="<?php echo htmlspecialchars($product['name']); ?>"
                                 onerror="this.src='uploads/placeholder.jpg'"
                                 style="height: 250px; object-fit: cover;">
                            <div class="card-body d-flex flex-column">
                                <h5 class="card-title"><?php echo htmlspecialchars($product['name']); ?></h5>
                                <p class="card-text"><?php echo htmlspecialchars(substr($product['short_description'] ?? '', 0, 100)); ?><?php echo strlen($product['short_description'] ?? '') > 100 ? '...' : ''; ?></p>
                                <div class="mt-auto">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <?php if ($product['sale_price']): ?>
                                            <div>
                                                <span class="text-muted text-decoration-line-through"><?php echo $price_prefix; ?><?php echo number_format($product['price'], 2); ?><?php echo $price_suffix; ?></span>
                                                <span class="h5 text-danger"><?php echo $price_prefix; ?><?php echo number_format($product['sale_price'], 2); ?><?php echo $price_suffix; ?></span>
                                            </div>
                                        <?php else: ?>
                                            <span class="h5 text-primary"><?php echo $price_prefix; ?><?php echo number_format($product['price'], 2); ?><?php echo $price_suffix; ?></span>
                                        <?php endif; ?>
                                        <a href="index.php?page=product_view&id=<?php echo $product['id']; ?>" 
                                           class="btn btn-primary">View</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12 text-center">
                    <p>No featured products available at the moment.</p>
                    <a href="index.php?page=shop" class="btn btn-primary">Browse All Products</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Features Section -->
<section class="py-5 bg-light">
    <div class="container">
        <div class="row">
            <div class="col-md-4 text-center mb-4">
                <i class="fas fa-shipping-fast fa-3x text-primary mb-3"></i>
                <h4><?php echo $settings->getSetting('feature1_title', 'Free Shipping'); ?></h4>
                <p><?php 
                    $currency = $settings->getSetting('currency', 'USD');
                    $currency_symbol = $currency === 'USD' ? '$' : ($currency === 'EUR' ? '€' : ($currency === 'GBP' ? '£' : ($currency === 'TND' ? 'TND' : $currency . ' ')));
                    $default_desc = 'Free shipping on orders over ' . $currency_symbol . '50';
                    echo $settings->getSetting('feature1_description', $default_desc); 
                ?></p>
            </div>
            <div class="col-md-4 text-center mb-4">
                <i class="fas fa-undo fa-3x text-primary mb-3"></i>
                <h4><?php echo $settings->getSetting('feature2_title', 'Easy Returns'); ?></h4>
                <p><?php echo $settings->getSetting('feature2_description', '30-day return policy'); ?></p>
            </div>
            <div class="col-md-4 text-center mb-4">
                <i class="fas fa-headset fa-3x text-primary mb-3"></i>
                <h4><?php echo $settings->getSetting('feature3_title', '24/7 Support'); ?></h4>
                <p><?php echo $settings->getSetting('feature3_description', 'Customer support available'); ?></p>
            </div>
        </div>
    </div>
</section>

<style>
.hero-section {
    height: 500px;
    overflow: hidden;
}

.hero-section .carousel-item img {
    height: 500px;
    object-fit: cover;
    width: 100%;
}

.hero-section .carousel-item picture img {
    height: 500px;
    object-fit: cover;
    width: 100%;
}

.hero-placeholder {
    height: 500px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.hero-section .carousel-caption {
    background: rgba(0, 0, 0, 0.5);
    border-radius: 10px;
    padding: 2rem;
    margin-bottom: 2rem;
}

.hero-section .carousel-caption h1 {
    font-size: 3rem;
    font-weight: bold;
    margin-bottom: 1rem;
}

.hero-section .carousel-caption p {
    font-size: 1.2rem;
    margin-bottom: 1.5rem;
}

@media (max-width: 768px) {
    .hero-section {
        height: 400px;
    }
    
    .hero-section .carousel-item img,
    .hero-section .carousel-item picture img {
        height: 400px;
    }
    
    .hero-section .carousel-caption h1 {
        font-size: 2rem;
    }
    
    .hero-section .carousel-caption p {
        font-size: 1rem;
    }
    
    .hero-section .carousel-caption {
        padding: 1rem;
        margin-bottom: 1rem;
    }
}

.card {
    transition: transform 0.3s ease;
}

.card:hover {
    transform: translateY(-5px);
}
</style>
