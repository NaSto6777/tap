<?php
try {
    require_once __DIR__ . '/../../config/database.php';
    require_once __DIR__ . '/../../config/StoreContext.php';
    require_once __DIR__ . '/../../config/settings.php';
    require_once __DIR__ . '/../../config/plugin_helper.php';
    require_once __DIR__ . '/../../config/language.php';
    
    $store_id = StoreContext::getId();
    // Initialize language
    Language::init();
    $t = function($key, $default = null) {
        return Language::t($key, $default);
    };

$database = new Database();
$conn = $database->getConnection();
    $settings = new Settings($conn, $store_id);
    $pluginHelper = new PluginHelper($conn);
} catch (Exception $e) {
    die('<h1>Configuration Error</h1><p>' . htmlspecialchars($e->getMessage()) . '</p><pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $updated_settings = [
        'site_name' => $_POST['site_name'] ?? '',
        'site_description' => $_POST['site_description'] ?? '',
        'contact_email' => $_POST['contact_email'] ?? '',
        'contact_phone' => $_POST['contact_phone'] ?? '',
        'contact_address' => $_POST['contact_address'] ?? '',
        'currency' => $_POST['currency'] ?? 'TND',
        'custom_currency' => $_POST['custom_currency'] ?? '',
        'currency_position' => $_POST['currency_position'] ?? 'left',
        'tax_rate' => $_POST['tax_rate'] ?? '0.00',
        'shipping_price' => $_POST['shipping_price'] ?? '0.00',
        'primary_color' => $_POST['primary_color'] ?? '#007bff',
        'secondary_color' => $_POST['secondary_color'] ?? '#6c757d',
        'seo_mode' => $_POST['seo_mode'] ?? 'auto',
        'auto_seo' => isset($_POST['auto_seo']) ? '1' : '0',
        'payment_methods' => json_encode($_POST['payment_methods'] ?? ['cod']),
        'categories_enabled' => isset($_POST['categories_enabled']) ? '1' : '0',
        'menu_type' => $_POST['menu_type'] ?? 'big_menu',
        'facebook_url' => $_POST['facebook_url'] ?? '',
        'twitter_url' => $_POST['twitter_url'] ?? '',
        'instagram_url' => $_POST['instagram_url'] ?? '',
        'linkedin_url' => $_POST['linkedin_url'] ?? '',
        'youtube_url' => $_POST['youtube_url'] ?? '',
        'about_content' => $_POST['about_content'] ?? '',
        'mission_statement' => $_POST['mission_statement'] ?? '',
        'hero_title' => $_POST['hero_title'] ?? '',
        'hero_subtitle' => $_POST['hero_subtitle'] ?? '',
        'hero_button_text' => $_POST['hero_button_text'] ?? '',
        'feature1_title' => $_POST['feature1_title'] ?? '',
        'feature1_description' => $_POST['feature1_description'] ?? '',
        'feature2_title' => $_POST['feature2_title'] ?? '',
        'feature2_description' => $_POST['feature2_description'] ?? '',
        'feature3_title' => $_POST['feature3_title'] ?? '',
        'feature3_description' => $_POST['feature3_description'] ?? '',
        'hero_image_pc' => $_POST['hero_image_pc'] ?? '',
        'hero_image_mobile' => $_POST['hero_image_mobile'] ?? '',
        'hero_images' => $_POST['hero_images'] ?? '[]',
        'logo' => $_POST['logo'] ?? '',
        'about_image' => $_POST['about_image'] ?? '',
        'analytics_enabled' => isset($_POST['analytics_enabled']) ? '1' : '0',
        'analytics_debug' => isset($_POST['analytics_debug']) ? '1' : '0',
        'analytics_track_buttons' => isset($_POST['analytics_track_buttons']) ? '1' : '0',
        'analytics_track_forms' => isset($_POST['analytics_track_forms']) ? '1' : '0',
        'analytics_track_searches' => isset($_POST['analytics_track_searches']) ? '1' : '0',
        'admin_language' => $_POST['admin_language'] ?? 'en'
    ];
    
    // Handle file uploads
    $upload_dir = '../uploads/site/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Handle logo upload
    if (!empty($_FILES['logo_file']['name']) && $_FILES['logo_file']['error'] === UPLOAD_ERR_OK) {
        $logo_file = $upload_dir . 'logo_' . time() . '.' . pathinfo($_FILES['logo_file']['name'], PATHINFO_EXTENSION);
        if (move_uploaded_file($_FILES['logo_file']['tmp_name'], $logo_file)) {
            $updated_settings['logo'] = 'uploads/site/' . basename($logo_file);
        }
    }
    
    // Handle hero image PC upload
    if (!empty($_FILES['hero_image_pc_file']['name']) && $_FILES['hero_image_pc_file']['error'] === UPLOAD_ERR_OK) {
        $hero_pc_file = $upload_dir . 'hero_pc_' . time() . '.' . pathinfo($_FILES['hero_image_pc_file']['name'], PATHINFO_EXTENSION);
        if (move_uploaded_file($_FILES['hero_image_pc_file']['tmp_name'], $hero_pc_file)) {
            $updated_settings['hero_image_pc'] = 'uploads/site/' . basename($hero_pc_file);
        }
    }
    
    // Handle hero image mobile upload
    if (!empty($_FILES['hero_image_mobile_file']['name']) && $_FILES['hero_image_mobile_file']['error'] === UPLOAD_ERR_OK) {
        $hero_mobile_file = $upload_dir . 'hero_mobile_' . time() . '.' . pathinfo($_FILES['hero_image_mobile_file']['name'], PATHINFO_EXTENSION);
        if (move_uploaded_file($_FILES['hero_image_mobile_file']['tmp_name'], $hero_mobile_file)) {
            $updated_settings['hero_image_mobile'] = 'uploads/site/' . basename($hero_mobile_file);
        }
    }
    
    // Handle about image upload
    if (!empty($_FILES['about_image_file']['name']) && $_FILES['about_image_file']['error'] === UPLOAD_ERR_OK) {
        $about_file = $upload_dir . 'about_' . time() . '.' . pathinfo($_FILES['about_image_file']['name'], PATHINFO_EXTENSION);
        if (move_uploaded_file($_FILES['about_image_file']['tmp_name'], $about_file)) {
            $updated_settings['about_image'] = 'uploads/site/' . basename($about_file);
        }
    }
    
    // Handle additional hero images upload
    if (!empty($_FILES['hero_images_files']['name'][0])) {
        $hero_images = [];
        $count = count($_FILES['hero_images_files']['name']);
        for ($i = 0; $i < $count; $i++) {
            if ($_FILES['hero_images_files']['error'][$i] === UPLOAD_ERR_OK) {
                $hero_file = $upload_dir . 'hero_' . time() . '_' . $i . '.' . pathinfo($_FILES['hero_images_files']['name'][$i], PATHINFO_EXTENSION);
                if (move_uploaded_file($_FILES['hero_images_files']['tmp_name'][$i], $hero_file)) {
                    $hero_images[] = 'uploads/site/' . basename($hero_file);
                }
            }
        }
        if (!empty($hero_images)) {
            $updated_settings['hero_images'] = json_encode($hero_images);
        }
    }
    
    foreach ($updated_settings as $key => $value) {
        $settings->setSetting($key, $value);
    }
    
    // Update language if changed
    if (isset($updated_settings['admin_language'])) {
        Language::setLanguage($updated_settings['admin_language']);
    }
    
    header('Location: ?content=1&page=settings&success=' . urlencode($t('saved_successfully', 'Settings updated successfully!')));
    exit;
}

// Get current settings
$current_settings = $settings->getAllSettings();

// Debug: Check if we have settings
if (empty($current_settings)) {
    $current_settings = [];
}

// Get currency and symbol
$currency = $current_settings['currency'] ?? 'TND';
if ($currency === 'CUSTOM' && !empty($current_settings['custom_currency'])) {
    $currency_symbol = $current_settings['custom_currency'];
} else {
    $currency_symbol = $currency === 'USD' ? '$' : ($currency === 'EUR' ? '€' : ($currency === 'GBP' ? '£' : ($currency === 'TND' ? 'TND' : $currency . ' ')));
}
$currency_position = $current_settings['currency_position'] ?? 'left';
?>


<!-- Modern Settings Interface -->
<div class="settings-container">
    
    <!-- Success Messages -->
    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            <?php echo htmlspecialchars($_GET['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
    <?php endif; ?>
    
    <form method="POST" id="settingsForm" enctype="multipart/form-data">
        <?php echo CsrfHelper::getTokenField(); ?>
        <!-- Sticky Header with ScrollSpy nav + Save -->
        <header class="settings-sticky-header" id="settingsStickyHeader">
            <nav class="settings-sticky-nav" aria-label="<?php echo $t('settings_menu', 'Settings Menu'); ?>">
                <div class="mobile-nav-toggle">
                    <button type="button" id="settingsNavToggle" class="nav-toggle-btn" aria-expanded="false" aria-controls="settingsNavContainer">
                        <i class="fas fa-bars"></i>
                        <span><?php echo $t('settings_menu', 'Settings Menu'); ?></span>
                        <i class="fas fa-chevron-down toggle-icon"></i>
                    </button>
                </div>
                <div id="settingsNavContainer" class="settings-nav-container">
                    <div id="settings-nav" class="settings-nav-links">
                        <a href="#general" class="settings-nav-link active" data-section="general"><?php echo $t('general'); ?></a>
                        <a href="#contact" class="settings-nav-link" data-section="contact"><?php echo $t('contact_info', 'Contact Info'); ?></a>
                        <a href="#ecommerce" class="settings-nav-link" data-section="ecommerce"><?php echo $t('ecommerce'); ?></a>
                        <a href="#appearance" class="settings-nav-link" data-section="appearance"><?php echo $t('appearance'); ?></a>
                        <a href="#social" class="settings-nav-link" data-section="social"><?php echo $t('social_media', 'Social Media'); ?></a>
                        <a href="#seo" class="settings-nav-link" data-section="seo"><?php echo $t('seo'); ?></a>
                        <a href="#content" class="settings-nav-link" data-section="content"><?php echo $t('content'); ?></a>
                        <a href="#analytics" class="settings-nav-link" data-section="analytics"><?php echo $t('analytics'); ?></a>
                    </div>
                </div>
                <div class="settings-sticky-actions">
                    <button type="submit" class="btn-save btn-save-inline">
                        <i class="fas fa-save"></i>
                        <?php echo $t('save_all_settings', 'Save All Settings'); ?>
                    </button>
                </div>
            </nav>
        </header>

        <div class="settings-layout">
            <!-- Single scrollable page: all sections visible -->
            <div class="settings-content settings-content-single">
        <!-- General Settings -->
                <section id="general" class="settings-section">
                    <div class="settings-section-card">
                    <div class="pane-header">
                        <h2><?php echo $t('general_settings', 'General Settings'); ?></h2>
                        <p><?php echo $t('general_settings_desc', 'Configure your store\'s basic information like name, logo, and contact details. This information will be displayed across your website.'); ?></p>
                        <div class="quick-links">
                            <a href="#" class="tip-link"><i class="fas fa-question-circle"></i> <?php echo $t('need_help', 'Need help?'); ?></a>
                </div>
                    </div>
                    
                    <div class="form-group">
                        <label><?php echo $t('language'); ?></label>
                        <select name="admin_language" class="form-input" onchange="this.form.submit()">
                            <option value="en" <?php echo ($current_settings['admin_language'] ?? 'en') === 'en' ? 'selected' : ''; ?>><?php echo $t('english'); ?></option>
                            <option value="ar" <?php echo ($current_settings['admin_language'] ?? 'en') === 'ar' ? 'selected' : ''; ?>><?php echo $t('arabic'); ?></option>
                        </select>
                        <small class="form-hint"><?php echo $t('select_language'); ?></small>
                    </div>
                    
                    <div class="form-group">
                        <label><?php echo $t('site_name'); ?> *</label>
                        <input type="text" name="site_name" class="form-input" 
                               value="<?php echo htmlspecialchars($current_settings['site_name'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label><?php echo $t('site_description', 'Site Description'); ?></label>
                        <textarea name="site_description" class="form-textarea" rows="3"><?php echo htmlspecialchars($current_settings['site_description'] ?? ''); ?></textarea>
                        <small class="form-hint"><?php echo $t('brief_store_description', 'A brief description of your store'); ?></small>
                    </div>
                    
                    <div class="form-group">
                        <label><?php echo $t('logo'); ?></label>
                        <div class="file-upload-container">
                            <input type="file" name="logo_file" class="file-input" accept="image/*" id="logoFile">
                            <label for="logoFile" class="file-upload-label">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <span><?php echo $t('choose_logo_file', 'Choose Logo File'); ?></span>
                            </label>
                            <?php if (!empty($current_settings['logo'])): ?>
                                <?php $logoPath = $current_settings['logo']; $logoDisplay = (strpos($logoPath, 'uploads/') === 0) ? ('../' . $logoPath) : $logoPath; ?>
                                <div class="current-image">
                                    <img src="<?php echo htmlspecialchars($logoDisplay); ?>" alt="<?php echo $t('current_logo', 'Current Logo'); ?>" class="preview-image">
                                    <small class="form-hint"><?php echo $t('current'); ?>: <?php echo basename($current_settings['logo']); ?></small>
                    </div>
                            <?php endif; ?>
                    </div>
                        <input type="hidden" name="logo" value="<?php echo htmlspecialchars($current_settings['logo'] ?? ''); ?>">
                    </div>
                    </div>
                </section>

                <!-- Contact Info -->
                <section id="contact" class="settings-section">
                    <div class="settings-section-card">
                    <div class="pane-header">
                        <h2><?php echo $t('contact_information', 'Contact Information'); ?></h2>
                        <p><?php echo $t('how_customers_reach', 'How customers can reach you'); ?></p>
                </div>
                    
                    <div class="form-group">
                        <label><?php echo $t('contact_email', 'Contact Email'); ?></label>
                        <input type="email" name="contact_email" class="form-input" 
                               value="<?php echo htmlspecialchars($current_settings['contact_email'] ?? ''); ?>"
                               placeholder="support@yourstore.com">
                    </div>
                    
                    <div class="form-group">
                        <label><?php echo $t('contact_phone', 'Contact Phone'); ?></label>
                        <input type="tel" name="contact_phone" class="form-input" 
                               value="<?php echo htmlspecialchars($current_settings['contact_phone'] ?? ''); ?>"
                               placeholder="+1 (555) 123-4567">
                    </div>
                    
                    <div class="form-group">
                        <label><?php echo $t('business_address', 'Business Address'); ?></label>
                        <textarea name="contact_address" class="form-textarea" rows="3" 
                                  placeholder="123 Main St, City, State 12345"><?php echo htmlspecialchars($current_settings['contact_address'] ?? ''); ?></textarea>
                    </div>
                    </div>
                </section>

            <!-- Ecommerce Settings -->
                <section id="ecommerce" class="settings-section">
                    <div class="settings-section-card">
                    <div class="pane-header">
                        <h2><?php echo $t('ecommerce_settings', 'Ecommerce Settings'); ?></h2>
                        <p><?php echo $t('configure_pricing_payment', 'Configure pricing and payment options'); ?></p>
                </div>
                    
                    <div class="form-row">
                        <div class="form-group col-4">
                            <label><?php echo $t('currency'); ?></label>
                            <select name="currency" id="currencySelect" class="form-input">
                                <?php
                                $allCurrencies = [
                                    ['code' => 'TND', 'label' => 'TND - Tunisian Dinar'],
                                    ['code' => 'USD', 'label' => 'USD - US Dollar'],
                                    ['code' => 'EUR', 'label' => 'EUR - Euro'],
                                    ['code' => 'GBP', 'label' => 'GBP - British Pound'],
                                    ['code' => 'CAD', 'label' => 'CAD - Canadian Dollar'],
                                    ['code' => 'AUD', 'label' => 'AUD - Australian Dollar'],
                                    ['code' => 'AED', 'label' => 'AED - UAE Dirham'],
                                    ['code' => 'AFN', 'label' => 'AFN - Afghan Afghani'],
                                    ['code' => 'ALL', 'label' => 'ALL - Albanian Lek'],
                                    ['code' => 'AMD', 'label' => 'AMD - Armenian Dram'],
                                    ['code' => 'ANG', 'label' => 'ANG - Netherlands Antillean Guilder'],
                                    ['code' => 'AOA', 'label' => 'AOA - Angolan Kwanza'],
                                    ['code' => 'ARS', 'label' => 'ARS - Argentine Peso'],
                                    ['code' => 'AWG', 'label' => 'AWG - Aruban Florin'],
                                    ['code' => 'AZN', 'label' => 'AZN - Azerbaijani Manat'],
                                    ['code' => 'BAM', 'label' => 'BAM - Bosnia-Herzegovina Convertible Mark'],
                                    ['code' => 'BBD', 'label' => 'BBD - Barbadian Dollar'],
                                    ['code' => 'BDT', 'label' => 'BDT - Bangladeshi Taka'],
                                    ['code' => 'BGN', 'label' => 'BGN - Bulgarian Lev'],
                                    ['code' => 'BHD', 'label' => 'BHD - Bahraini Dinar'],
                                    ['code' => 'BIF', 'label' => 'BIF - Burundian Franc'],
                                    ['code' => 'BMD', 'label' => 'BMD - Bermudian Dollar'],
                                    ['code' => 'BND', 'label' => 'BND - Brunei Dollar'],
                                    ['code' => 'BOB', 'label' => 'BOB - Bolivian Boliviano'],
                                    ['code' => 'BRL', 'label' => 'BRL - Brazilian Real'],
                                    ['code' => 'BSD', 'label' => 'BSD - Bahamian Dollar'],
                                    ['code' => 'BTN', 'label' => 'BTN - Bhutanese Ngultrum'],
                                    ['code' => 'BWP', 'label' => 'BWP - Botswana Pula'],
                                    ['code' => 'BYN', 'label' => 'BYN - Belarusian Ruble'],
                                    ['code' => 'BZD', 'label' => 'BZD - Belize Dollar'],
                                    ['code' => 'CDF', 'label' => 'CDF - Congolese Franc'],
                                    ['code' => 'CHF', 'label' => 'CHF - Swiss Franc'],
                                    ['code' => 'CLP', 'label' => 'CLP - Chilean Peso'],
                                    ['code' => 'CNY', 'label' => 'CNY - Chinese Yuan'],
                                    ['code' => 'COP', 'label' => 'COP - Colombian Peso'],
                                    ['code' => 'CRC', 'label' => 'CRC - Costa Rican Colón'],
                                    ['code' => 'CUP', 'label' => 'CUP - Cuban Peso'],
                                    ['code' => 'CVE', 'label' => 'CVE - Cape Verdean Escudo'],
                                    ['code' => 'CZK', 'label' => 'CZK - Czech Koruna'],
                                    ['code' => 'DJF', 'label' => 'DJF - Djiboutian Franc'],
                                    ['code' => 'DKK', 'label' => 'DKK - Danish Krone'],
                                    ['code' => 'DOP', 'label' => 'DOP - Dominican Peso'],
                                    ['code' => 'DZD', 'label' => 'DZD - Algerian Dinar'],
                                    ['code' => 'EGP', 'label' => 'EGP - Egyptian Pound'],
                                    ['code' => 'ERN', 'label' => 'ERN - Eritrean Nakfa'],
                                    ['code' => 'ETB', 'label' => 'ETB - Ethiopian Birr'],
                                    ['code' => 'FJD', 'label' => 'FJD - Fijian Dollar'],
                                    ['code' => 'FKP', 'label' => 'FKP - Falkland Islands Pound'],
                                    ['code' => 'GEL', 'label' => 'GEL - Georgian Lari'],
                                    ['code' => 'GGP', 'label' => 'GGP - Guernsey Pound'],
                                    ['code' => 'GHS', 'label' => 'GHS - Ghanaian Cedi'],
                                    ['code' => 'GIP', 'label' => 'GIP - Gibraltar Pound'],
                                    ['code' => 'GMD', 'label' => 'GMD - Gambian Dalasi'],
                                    ['code' => 'GNF', 'label' => 'GNF - Guinean Franc'],
                                    ['code' => 'GTQ', 'label' => 'GTQ - Guatemalan Quetzal'],
                                    ['code' => 'GYD', 'label' => 'GYD - Guyanese Dollar'],
                                    ['code' => 'HKD', 'label' => 'HKD - Hong Kong Dollar'],
                                    ['code' => 'HNL', 'label' => 'HNL - Honduran Lempira'],
                                    ['code' => 'HRK', 'label' => 'HRK - Croatian Kuna'],
                                    ['code' => 'HTG', 'label' => 'HTG - Haitian Gourde'],
                                    ['code' => 'HUF', 'label' => 'HUF - Hungarian Forint'],
                                    ['code' => 'IDR', 'label' => 'IDR - Indonesian Rupiah'],
                                    ['code' => 'ILS', 'label' => 'ILS - Israeli New Shekel'],
                                    ['code' => 'IMP', 'label' => 'IMP - Isle of Man Pound'],
                                    ['code' => 'INR', 'label' => 'INR - Indian Rupee'],
                                    ['code' => 'IQD', 'label' => 'IQD - Iraqi Dinar'],
                                    ['code' => 'IRR', 'label' => 'IRR - Iranian Rial'],
                                    ['code' => 'ISK', 'label' => 'ISK - Icelandic Króna'],
                                    ['code' => 'JEP', 'label' => 'JEP - Jersey Pound'],
                                    ['code' => 'JMD', 'label' => 'JMD - Jamaican Dollar'],
                                    ['code' => 'JOD', 'label' => 'JOD - Jordanian Dinar'],
                                    ['code' => 'JPY', 'label' => 'JPY - Japanese Yen'],
                                    ['code' => 'KES', 'label' => 'KES - Kenyan Shilling'],
                                    ['code' => 'KGS', 'label' => 'KGS - Kyrgyzstani Som'],
                                    ['code' => 'KHR', 'label' => 'KHR - Cambodian Riel'],
                                    ['code' => 'KMF', 'label' => 'KMF - Comorian Franc'],
                                    ['code' => 'KPW', 'label' => 'KPW - North Korean Won'],
                                    ['code' => 'KRW', 'label' => 'KRW - South Korean Won'],
                                    ['code' => 'KWD', 'label' => 'KWD - Kuwaiti Dinar'],
                                    ['code' => 'KYD', 'label' => 'KYD - Cayman Islands Dollar'],
                                    ['code' => 'KZT', 'label' => 'KZT - Kazakhstani Tenge'],
                                    ['code' => 'LAK', 'label' => 'LAK - Lao Kip'],
                                    ['code' => 'LBP', 'label' => 'LBP - Lebanese Pound'],
                                    ['code' => 'LKR', 'label' => 'LKR - Sri Lankan Rupee'],
                                    ['code' => 'LRD', 'label' => 'LRD - Liberian Dollar'],
                                    ['code' => 'LSL', 'label' => 'LSL - Lesotho Loti'],
                                    ['code' => 'LYD', 'label' => 'LYD - Libyan Dinar'],
                                    ['code' => 'MAD', 'label' => 'MAD - Moroccan Dirham'],
                                    ['code' => 'MDL', 'label' => 'MDL - Moldovan Leu'],
                                    ['code' => 'MGA', 'label' => 'MGA - Malagasy Ariary'],
                                    ['code' => 'MKD', 'label' => 'MKD - Macedonian Denar'],
                                    ['code' => 'MMK', 'label' => 'MMK - Burmese Kyat'],
                                    ['code' => 'MNT', 'label' => 'MNT - Mongolian Tögrög'],
                                    ['code' => 'MOP', 'label' => 'MOP - Macanese Pataca'],
                                    ['code' => 'MRU', 'label' => 'MRU - Mauritanian Ouguiya'],
                                    ['code' => 'MUR', 'label' => 'MUR - Mauritian Rupee'],
                                    ['code' => 'MVR', 'label' => 'MVR - Maldivian Rufiyaa'],
                                    ['code' => 'MWK', 'label' => 'MWK - Malawian Kwacha'],
                                    ['code' => 'MXN', 'label' => 'MXN - Mexican Peso'],
                                    ['code' => 'MYR', 'label' => 'MYR - Malaysian Ringgit'],
                                    ['code' => 'MZN', 'label' => 'MZN - Mozambican Metical'],
                                    ['code' => 'NAD', 'label' => 'NAD - Namibian Dollar'],
                                    ['code' => 'NGN', 'label' => 'NGN - Nigerian Naira'],
                                    ['code' => 'NIO', 'label' => 'NIO - Nicaraguan Córdoba'],
                                    ['code' => 'NOK', 'label' => 'NOK - Norwegian Krone'],
                                    ['code' => 'NPR', 'label' => 'NPR - Nepalese Rupee'],
                                    ['code' => 'NZD', 'label' => 'NZD - New Zealand Dollar'],
                                    ['code' => 'OMR', 'label' => 'OMR - Omani Rial'],
                                    ['code' => 'PAB', 'label' => 'PAB - Panamanian Balboa'],
                                    ['code' => 'PEN', 'label' => 'PEN - Peruvian Sol'],
                                    ['code' => 'PGK', 'label' => 'PGK - Papua New Guinean Kina'],
                                    ['code' => 'PHP', 'label' => 'PHP - Philippine Peso'],
                                    ['code' => 'PKR', 'label' => 'PKR - Pakistani Rupee'],
                                    ['code' => 'PLN', 'label' => 'PLN - Polish Złoty'],
                                    ['code' => 'PYG', 'label' => 'PYG - Paraguayan Guaraní'],
                                    ['code' => 'QAR', 'label' => 'QAR - Qatari Riyal'],
                                    ['code' => 'RON', 'label' => 'RON - Romanian Leu'],
                                    ['code' => 'RSD', 'label' => 'RSD - Serbian Dinar'],
                                    ['code' => 'RUB', 'label' => 'RUB - Russian Ruble'],
                                    ['code' => 'RWF', 'label' => 'RWF - Rwandan Franc'],
                                    ['code' => 'SAR', 'label' => 'SAR - Saudi Riyal'],
                                    ['code' => 'SBD', 'label' => 'SBD - Solomon Islands Dollar'],
                                    ['code' => 'SCR', 'label' => 'SCR - Seychellois Rupee'],
                                    ['code' => 'SDG', 'label' => 'SDG - Sudanese Pound'],
                                    ['code' => 'SEK', 'label' => 'SEK - Swedish Krona'],
                                    ['code' => 'SGD', 'label' => 'SGD - Singapore Dollar'],
                                    ['code' => 'SHP', 'label' => 'SHP - Saint Helena Pound'],
                                    ['code' => 'SLL', 'label' => 'SLL - Sierra Leonean Leone'],
                                    ['code' => 'SOS', 'label' => 'SOS - Somali Shilling'],
                                    ['code' => 'SRD', 'label' => 'SRD - Surinamese Dollar'],
                                    ['code' => 'SSP', 'label' => 'SSP - South Sudanese Pound'],
                                    ['code' => 'STN', 'label' => 'STN - São Tomé and Príncipe Dobra'],
                                    ['code' => 'SYP', 'label' => 'SYP - Syrian Pound'],
                                    ['code' => 'SZL', 'label' => 'SZL - Swazi Lilangeni'],
                                    ['code' => 'THB', 'label' => 'THB - Thai Baht'],
                                    ['code' => 'TJS', 'label' => 'TJS - Tajikistani Somoni'],
                                    ['code' => 'TMT', 'label' => 'TMT - Turkmenistan Manat'],
                                    ['code' => 'TND', 'label' => 'TND - Tunisian Dinar'],
                                    ['code' => 'TOP', 'label' => 'TOP - Tongan Paʻanga'],
                                    ['code' => 'TRY', 'label' => 'TRY - Turkish Lira'],
                                    ['code' => 'TTD', 'label' => 'TTD - Trinidad and Tobago Dollar'],
                                    ['code' => 'TWD', 'label' => 'TWD - New Taiwan Dollar'],
                                    ['code' => 'TZS', 'label' => 'TZS - Tanzanian Shilling'],
                                    ['code' => 'UAH', 'label' => 'UAH - Ukrainian Hryvnia'],
                                    ['code' => 'UGX', 'label' => 'UGX - Ugandan Shilling'],
                                    ['code' => 'UYU', 'label' => 'UYU - Uruguayan Peso'],
                                    ['code' => 'UZS', 'label' => 'UZS - Uzbekistani Som'],
                                    ['code' => 'VES', 'label' => 'VES - Venezuelan Bolívar'],
                                    ['code' => 'VND', 'label' => 'VND - Vietnamese Đồng'],
                                    ['code' => 'VUV', 'label' => 'VUV - Vanuatu Vatu'],
                                    ['code' => 'WST', 'label' => 'WST - Samoan Tālā'],
                                    ['code' => 'XAF', 'label' => 'XAF - Central African CFA Franc'],
                                    ['code' => 'XCD', 'label' => 'XCD - East Caribbean Dollar'],
                                    ['code' => 'XOF', 'label' => 'XOF - West African CFA Franc'],
                                    ['code' => 'XPF', 'label' => 'XPF - CFP Franc'],
                                    ['code' => 'YER', 'label' => 'YER - Yemeni Rial'],
                                    ['code' => 'ZAR', 'label' => 'ZAR - South African Rand'],
                                    ['code' => 'ZMW', 'label' => 'ZMW - Zambian Kwacha'],
                                    ['code' => 'ZWL', 'label' => 'ZWL - Zimbabwean Dollar'],
                                ];
                                $current = $current_settings['currency'] ?? 'TND';
                                foreach ($allCurrencies as $c) {
                                    $sel = $current == $c['code'] ? 'selected' : '';
                                    echo '<option value="' . htmlspecialchars($c['code']) . '" ' . $sel . '>' . htmlspecialchars($c['label']) . '</option>';
                                }
                                ?>
                                <option value="CUSTOM" <?php echo ($current == 'CUSTOM') ? 'selected' : ''; ?>><?php echo $t('custom_currency', 'Custom Currency'); ?></option>
                            </select>
                        </div>
                        <div class="form-group col-4" id="customCurrencyGroup" style="display: <?php echo ($current == 'CUSTOM') ? 'block' : 'none'; ?>;">
                            <label><?php echo $t('custom_currency_symbol', 'Custom Currency Symbol'); ?></label>
                            <input type="text" name="custom_currency" class="form-input" 
                                   value="<?php echo htmlspecialchars($current_settings['custom_currency'] ?? ''); ?>"
                                   placeholder="e.g., ₽, ₹, ¥, etc." maxlength="10">
                            <small class="form-hint"><?php echo $t('enter_custom_currency', 'Enter your custom currency symbol or code'); ?></small>
                        </div>
                        <div class="form-group col-4">
                            <label><?php echo $t('currency_position', 'Currency Position'); ?></label>
                            <select name="currency_position" class="form-input">
                                <option value="left" <?php echo ($current_settings['currency_position'] ?? 'left') == 'left' ? 'selected' : ''; ?>><?php echo $t('left_of_price', 'Left of Price (e.g., $50)'); ?></option>
                                <option value="right" <?php echo ($current_settings['currency_position'] ?? 'left') == 'right' ? 'selected' : ''; ?>><?php echo $t('right_of_price', 'Right of Price (e.g., 50 TND)'); ?></option>
                            </select>
                        </div>
                        
                        <div class="form-group col-4">
                            <label><?php echo $t('tax_rate', 'Tax Rate'); ?> (%)</label>
                            <input type="number" name="tax_rate" class="form-input" 
                                   step="0.01" value="<?php echo $current_settings['tax_rate'] ?? '0.00'; ?>"
                                   placeholder="0.00">
                        </div>
                        
                        <div class="form-group col-4">
                            <label><?php echo $t('shipping_price', 'Shipping Price'); ?></label>
                            <input type="number" name="shipping_price" class="form-input" 
                                   step="0.01" value="<?php echo $current_settings['shipping_price'] ?? '0.00'; ?>"
                                   placeholder="0.00">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label><?php echo $t('payment_methods', 'Payment Methods'); ?></label>
                        <div class="checkbox-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="payment_methods[]" value="cod" 
                                       <?php echo in_array('cod', json_decode($current_settings['payment_methods'] ?? '["cod"]', true)) ? 'checked' : ''; ?>>
                                <span><?php echo $t('cash_on_delivery', 'Cash on Delivery'); ?></span>
                            </label>
                            <?php if ($settings->getSetting('plugin_stripe_status', 'inactive') === 'active'): ?>
                            <label class="checkbox-label">
                                <input type="checkbox" name="payment_methods[]" value="stripe" 
                                       <?php echo in_array('stripe', json_decode($current_settings['payment_methods'] ?? '["cod"]', true)) ? 'checked' : ''; ?>>
                                <span><?php echo $t('credit_card_stripe', 'Credit Card (Stripe)'); ?></span>
                            </label>
                            <?php endif; ?>
                            <?php if ($settings->getSetting('plugin_paypal_status', 'inactive') === 'active'): ?>
                            <label class="checkbox-label">
                                <input type="checkbox" name="payment_methods[]" value="paypal" 
                                       <?php echo in_array('paypal', json_decode($current_settings['payment_methods'] ?? '["cod"]', true)) ? 'checked' : ''; ?>>
                                <span><?php echo $t('paypal'); ?></span>
                            </label>
                            <?php endif; ?>
                            <?php if ($settings->getSetting('plugin_flouci_status', 'inactive') === 'active'): ?>
                            <label class="checkbox-label">
                                <input type="checkbox" name="payment_methods[]" value="flouci" 
                                       <?php echo in_array('flouci', json_decode($current_settings['payment_methods'] ?? '["cod"]', true)) ? 'checked' : ''; ?>>
                                <span><?php echo $t('flouci_payment', 'Flouci Payment'); ?></span>
                            </label>
                            <?php endif; ?>
                    </div>
                        <small class="form-hint">
                            <i class="fas fa-info-circle"></i>
                            <?php echo $t('only_active_payment_plugins', 'Only payment plugins that are activated will be shown here'); ?>
                        </small>
                </div>
                    
                    <div class="checkbox-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="categories_enabled" 
                                   <?php echo ($current_settings['categories_enabled'] ?? '1') == '1' ? 'checked' : ''; ?>>
                            <span><?php echo $t('enable_product_categories', 'Enable Product Categories'); ?></span>
                        </label>
                    </div>
                    </div>
                </section>

                <!-- Appearance -->
                <section id="appearance" class="settings-section">
                    <div class="settings-section-card">
                    <div class="pane-header">
                        <h2><?php echo $t('appearance'); ?></h2>
                        <p><?php echo $t('customize_look_feel', 'Customize your store\'s look and feel'); ?></p>
                </div>
                    
                    <div class="form-row">
                        <div class="form-group col-6">
                            <label><?php echo $t('primary_color', 'Primary Color'); ?></label>
                            <div class="color-picker-group">
                                <input type="color" name="primary_color" id="primaryColor" class="color-input" 
                                   value="<?php echo $current_settings['primary_color'] ?? '#007bff'; ?>">
                                <input type="text" class="form-input color-value" id="primaryColorValue"
                                       value="<?php echo $current_settings['primary_color'] ?? '#007bff'; ?>" readonly>
                        </div>
                    </div>
                    
                        <div class="form-group col-6">
                            <label><?php echo $t('secondary_color', 'Secondary Color'); ?></label>
                            <div class="color-picker-group">
                                <input type="color" name="secondary_color" id="secondaryColor" class="color-input" 
                                   value="<?php echo $current_settings['secondary_color'] ?? '#6c757d'; ?>">
                                <input type="text" class="form-input color-value" id="secondaryColorValue"
                                       value="<?php echo $current_settings['secondary_color'] ?? '#6c757d'; ?>" readonly>
                    </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label><?php echo $t('menu_type', 'Menu Type'); ?></label>
                        <select name="menu_type" class="form-input">
                            <option value="big_menu" <?php echo ($current_settings['menu_type'] ?? 'big_menu') == 'big_menu' ? 'selected' : ''; ?>><?php echo $t('big_menu_full_width', 'Big Menu (Full Width)'); ?></option>
                            <option value="side_menu" <?php echo ($current_settings['menu_type'] ?? '') == 'side_menu' ? 'selected' : ''; ?>><?php echo $t('side_menu_sidebar', 'Side Menu (Sidebar)'); ?></option>
                            <option value="hamburger_menu" <?php echo ($current_settings['menu_type'] ?? '') == 'hamburger_menu' ? 'selected' : ''; ?>><?php echo $t('hamburger_menu_mobile', 'Hamburger Menu (Mobile Style)'); ?></option>
                        </select>
                    </div>
                    </div>
                </section>

            <!-- Social Media -->
                <section id="social" class="settings-section">
                    <div class="settings-section-card">
                    <div class="pane-header">
                        <h2><?php echo $t('social_media_links', 'Social Media Links'); ?></h2>
                        <p><?php echo $t('connect_social_profiles', 'Connect your social media profiles'); ?></p>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fab fa-facebook"></i> Facebook URL</label>
                        <input type="url" name="facebook_url" class="form-input" 
                               value="<?php echo htmlspecialchars($current_settings['facebook_url'] ?? ''); ?>"
                               placeholder="https://facebook.com/yourpage">
                </div>
                    
                    <div class="form-group">
                        <label><i class="fab fa-twitter"></i> Twitter URL</label>
                        <input type="url" name="twitter_url" class="form-input" 
                               value="<?php echo htmlspecialchars($current_settings['twitter_url'] ?? ''); ?>"
                               placeholder="https://twitter.com/yourhandle">
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fab fa-instagram"></i> Instagram URL</label>
                        <input type="url" name="instagram_url" class="form-input" 
                               value="<?php echo htmlspecialchars($current_settings['instagram_url'] ?? ''); ?>"
                               placeholder="https://instagram.com/yourprofile">
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fab fa-linkedin"></i> LinkedIn URL</label>
                        <input type="url" name="linkedin_url" class="form-input" 
                               value="<?php echo htmlspecialchars($current_settings['linkedin_url'] ?? ''); ?>"
                               placeholder="https://linkedin.com/company/yourcompany">
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fab fa-youtube"></i> YouTube URL</label>
                        <input type="url" name="youtube_url" class="form-input" 
                               value="<?php echo htmlspecialchars($current_settings['youtube_url'] ?? ''); ?>"
                               placeholder="https://youtube.com/@yourchannel">
                    </div>
                    </div>
                </section>

            <!-- SEO Settings -->
                <section id="seo" class="settings-section">
                    <div class="settings-section-card">
                    <div class="pane-header">
                        <h2><?php echo $t('seo_settings', 'SEO Settings'); ?></h2>
                        <p><?php echo $t('seo_options', 'Search engine optimization options'); ?></p>
                </div>
                    
                    <div class="form-group">
                        <label><?php echo $t('seo_mode', 'SEO Mode'); ?></label>
                        <select name="seo_mode" class="form-input">
                            <option value="auto" <?php echo ($current_settings['seo_mode'] ?? 'auto') == 'auto' ? 'selected' : ''; ?>><?php echo $t('seo_auto', 'Automatic - Generate SEO tags automatically'); ?></option>
                            <option value="manual" <?php echo ($current_settings['seo_mode'] ?? '') == 'manual' ? 'selected' : ''; ?>><?php echo $t('seo_manual', 'Manual - Set custom SEO tags'); ?></option>
                        </select>
                        <small class="form-hint">
                            <i class="fas fa-info-circle"></i>
                            <?php echo $t('seo_auto_hint', 'Auto mode generates meta tags from product/page content'); ?>
                        </small>
                    </div>
                    
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="auto_seo" <?php echo ($current_settings['auto_seo'] ?? '0') == '1' ? 'checked' : ''; ?>>
                            <span class="checkbox-custom"></span>
                            <span class="checkbox-text"><?php echo $t('auto_seo_products', 'Auto SEO for Products'); ?></span>
                        </label>
                        <small class="form-hint">
                            <i class="fas fa-info-circle"></i>
                            <?php echo $t('auto_seo_hint', 'When enabled, SEO section will be hidden in product forms and SEO data will be auto-generated from product name and description'); ?>
                        </small>
                    </div>
                    </div>
                </section>

    <!-- Content Settings -->
                <section id="content" class="settings-section">
                    <div class="settings-section-card">
                    <div class="pane-header">
                        <h2><?php echo $t('content_settings', 'Content Settings'); ?></h2>
                        <p><?php echo $t('manage_content_pages', 'Manage your store\'s content and pages'); ?></p>
                    </div>
                    
                    <div class="form-group">
                        <label><?php echo $t('about_us_content', 'About Us Content'); ?></label>
                        <textarea name="about_content" class="form-textarea" rows="6" 
                                  placeholder="<?php echo $t('tell_customers_business', 'Tell customers about your business...'); ?>"><?php echo htmlspecialchars($current_settings['about_content'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label><?php echo $t('mission_statement', 'Mission Statement'); ?></label>
                        <textarea name="mission_statement" class="form-textarea" rows="4" 
                                  placeholder="<?php echo $t('company_mission_values', 'Your company\'s mission and values...'); ?>"><?php echo htmlspecialchars($current_settings['mission_statement'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label><?php echo $t('about_us_image', 'About Us Image'); ?></label>
                        <div class="file-upload-container">
                            <input type="file" name="about_image_file" class="file-input" accept="image/*" id="aboutImageFile">
                            <label for="aboutImageFile" class="file-upload-label">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <span><?php echo $t('choose_about_image', 'Choose About Us Image'); ?></span>
                            </label>
                            <?php if (!empty($current_settings['about_image'])): ?>
                                <?php $aboutPath = $current_settings['about_image']; $aboutDisplay = (strpos($aboutPath, 'uploads/') === 0) ? ('../' . $aboutPath) : $aboutPath; ?>
                                <div class="current-image">
                                    <img src="<?php echo htmlspecialchars($aboutDisplay); ?>" alt="<?php echo $t('current_about_image', 'Current About Image'); ?>" class="preview-image">
                                    <small class="form-hint"><?php echo $t('current'); ?>: <?php echo basename($current_settings['about_image']); ?></small>
                    </div>
                            <?php endif; ?>
                </div>
                        <input type="hidden" name="about_image" value="<?php echo htmlspecialchars($current_settings['about_image'] ?? ''); ?>">
            </div>
                    
                    <div class="form-group">
                        <label><?php echo $t('hero_title', 'Hero Title'); ?></label>
                        <input type="text" name="hero_title" class="form-input" 
                               value="<?php echo htmlspecialchars($current_settings['hero_title'] ?? ''); ?>"
                               placeholder="<?php echo $t('welcome_to_store', 'Welcome to Our Store'); ?>">
        </div>
                    
                    <div class="form-group">
                        <label><?php echo $t('hero_subtitle', 'Hero Subtitle'); ?></label>
                        <input type="text" name="hero_subtitle" class="form-input" 
                               value="<?php echo htmlspecialchars($current_settings['hero_subtitle'] ?? ''); ?>"
                               placeholder="<?php echo $t('discover_products', 'Discover amazing products at great prices'); ?>">
    </div>
    
                    <div class="form-group">
                        <label><?php echo $t('hero_button_text', 'Hero Button Text'); ?></label>
                        <input type="text" name="hero_button_text" class="form-input" 
                               value="<?php echo htmlspecialchars($current_settings['hero_button_text'] ?? ''); ?>"
                               placeholder="<?php echo $t('shop_now', 'Shop Now'); ?>">
                </div>
                    
                    <div class="form-group">
                        <label><?php echo $t('feature_1_title', 'Feature 1 Title'); ?></label>
                        <input type="text" name="feature1_title" class="form-input" 
                               value="<?php echo htmlspecialchars($current_settings['feature1_title'] ?? ''); ?>"
                               placeholder="<?php echo $t('free_shipping', 'Free Shipping'); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label><?php echo $t('feature_1_description', 'Feature 1 Description'); ?></label>
                        <input type="text" name="feature1_description" class="form-input" 
                               value="<?php echo htmlspecialchars($current_settings['feature1_description'] ?? ''); ?>"
                               placeholder="<?php echo htmlspecialchars($t('free_shipping_over', 'Free shipping on orders over') . ' ' . ($currency_position === 'left' ? $currency_symbol . '50' : '50 ' . $currency_symbol)); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label><?php echo $t('feature_2_title', 'Feature 2 Title'); ?></label>
                        <input type="text" name="feature2_title" class="form-input" 
                               value="<?php echo htmlspecialchars($current_settings['feature2_title'] ?? ''); ?>"
                               placeholder="<?php echo $t('easy_returns', 'Easy Returns'); ?>">
                </div>
                    
                    <div class="form-group">
                        <label><?php echo $t('feature_2_description', 'Feature 2 Description'); ?></label>
                        <input type="text" name="feature2_description" class="form-input" 
                               value="<?php echo htmlspecialchars($current_settings['feature2_description'] ?? ''); ?>"
                               placeholder="<?php echo $t('return_policy', '30-day return policy'); ?>">
            </div>
                    
                    <div class="form-group">
                        <label><?php echo $t('feature_3_title', 'Feature 3 Title'); ?></label>
                        <input type="text" name="feature3_title" class="form-input" 
                               value="<?php echo htmlspecialchars($current_settings['feature3_title'] ?? ''); ?>"
                               placeholder="<?php echo $t('support_24_7', '24/7 Support'); ?>">
        </div>
                    
                    <div class="form-group">
                        <label><?php echo $t('feature_3_description', 'Feature 3 Description'); ?></label>
                        <input type="text" name="feature3_description" class="form-input" 
                               value="<?php echo htmlspecialchars($current_settings['feature3_description'] ?? ''); ?>"
                               placeholder="<?php echo $t('customer_support_available', 'Customer support available'); ?>">
    </div>
    
                    <div class="form-group">
                        <label><?php echo $t('hero_image_desktop', 'Hero Image (Desktop)'); ?></label>
                        <div class="file-upload-container">
                            <input type="file" name="hero_image_pc_file" class="file-input" accept="image/*" id="heroPcFile">
                            <label for="heroPcFile" class="file-upload-label">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <span><?php echo $t('choose_desktop_hero', 'Choose Desktop Hero Image'); ?></span>
                            </label>
                            <?php if (!empty($current_settings['hero_image_pc'])): ?>
                                <?php $heroPcPath = $current_settings['hero_image_pc']; $heroPcDisplay = (strpos($heroPcPath, 'uploads/') === 0) ? ('../' . $heroPcPath) : $heroPcPath; ?>
                                <div class="current-image">
                                    <img src="<?php echo htmlspecialchars($heroPcDisplay); ?>" alt="<?php echo $t('current_desktop_hero', 'Current Desktop Hero'); ?>" class="preview-image">
                                    <small class="form-hint"><?php echo $t('current'); ?>: <?php echo basename($current_settings['hero_image_pc']); ?></small>
                </div>
                            <?php endif; ?>
                        </div>
                        <input type="hidden" name="hero_image_pc" value="<?php echo htmlspecialchars($current_settings['hero_image_pc'] ?? ''); ?>">
                        </div>
                    
                    <div class="form-group">
                        <label><?php echo $t('hero_image_mobile', 'Hero Image (Mobile)'); ?></label>
                        <div class="file-upload-container">
                            <input type="file" name="hero_image_mobile_file" class="file-input" accept="image/*" id="heroMobileFile">
                            <label for="heroMobileFile" class="file-upload-label">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <span><?php echo $t('choose_mobile_hero', 'Choose Mobile Hero Image'); ?></span>
                            </label>
                            <?php if (!empty($current_settings['hero_image_mobile'])): ?>
                                <?php $heroMobPath = $current_settings['hero_image_mobile']; $heroMobDisplay = (strpos($heroMobPath, 'uploads/') === 0) ? ('../' . $heroMobPath) : $heroMobPath; ?>
                                <div class="current-image">
                                    <img src="<?php echo htmlspecialchars($heroMobDisplay); ?>" alt="<?php echo $t('current_mobile_hero', 'Current Mobile Hero'); ?>" class="preview-image">
                                    <small class="form-hint"><?php echo $t('current'); ?>: <?php echo basename($current_settings['hero_image_mobile']); ?></small>
                        </div>
                            <?php endif; ?>
                        </div>
                        <input type="hidden" name="hero_image_mobile" value="<?php echo htmlspecialchars($current_settings['hero_image_mobile'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label><?php echo $t('additional_hero_images', 'Additional Hero Images'); ?></label>
                        <div class="file-upload-container">
                            <input type="file" name="hero_images_files[]" class="file-input" accept="image/*" id="heroImagesFiles" multiple>
                            <label for="heroImagesFiles" class="file-upload-label">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <span><?php echo $t('choose_multiple_hero', 'Choose Multiple Hero Images'); ?></span>
                            </label>
                            <small class="form-hint"><?php echo $t('select_multiple_carousel', 'Select multiple images for carousel/slider'); ?></small>
                            <?php 
                            $hero_images = json_decode($current_settings['hero_images'] ?? '[]', true);
                            if (!empty($hero_images)): ?>
                                <div class="current-images">
                                    <small class="form-hint"><?php echo $t('current_images', 'Current images'); ?>:</small>
                                    <?php foreach ($hero_images as $img): ?>
                                        <?php $imgDisplay = (strpos($img, 'uploads/') === 0) ? ('../' . $img) : $img; ?>
                                        <div class="current-image">
                                            <img src="<?php echo htmlspecialchars($imgDisplay); ?>" alt="<?php echo $t('hero_image'); ?>" class="preview-image">
                    </div>
                                    <?php endforeach; ?>
                </div>
                            <?php endif; ?>
                    </div>
                        <input type="hidden" name="hero_images" value="<?php echo htmlspecialchars($current_settings['hero_images'] ?? '[]'); ?>">
                    </div>
                    </div>
                </section>

            <!-- Analytics Settings -->
                <section id="analytics" class="settings-section">
                    <div class="settings-section-card">
                    <div class="pane-header">
                        <h2><?php echo $t('analytics_settings', 'Analytics Settings'); ?></h2>
                    <p><?php echo $t('configure_analytics_tracking', 'Configure analytics tracking and debugging options'); ?></p>
                </div>
                
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="analytics_enabled" 
                               <?php echo ($current_settings['analytics_enabled'] ?? '1') == '1' ? 'checked' : ''; ?>>
                        <span class="checkbox-custom"></span>
                        <span class="checkbox-text"><?php echo $t('enable_analytics_tracking', 'Enable Analytics Tracking'); ?></span>
                    </label>
                    <small class="form-hint">
                        <i class="fas fa-info-circle"></i>
                        <?php echo $t('analytics_tracking_hint', 'When enabled, visitor behavior and page views will be tracked'); ?>
                    </small>
                </div>
                
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="analytics_debug" 
                               <?php echo ($current_settings['analytics_debug'] ?? '0') == '1' ? 'checked' : ''; ?>>
                        <span class="checkbox-custom"></span>
                        <span class="checkbox-text"><?php echo $t('enable_analytics_debug', 'Enable Analytics Debug Mode'); ?></span>
                    </label>
                    <small class="form-hint">
                        <i class="fas fa-info-circle"></i>
                        <?php echo $t('analytics_debug_hint', 'Shows detailed tracking information in page source for troubleshooting'); ?>
                    </small>
                </div>
                
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="analytics_track_buttons" 
                               <?php echo ($current_settings['analytics_track_buttons'] ?? '1') == '1' ? 'checked' : ''; ?>>
                        <span class="checkbox-custom"></span>
                        <span class="checkbox-text"><?php echo $t('track_button_clicks', 'Track Button Clicks'); ?></span>
                    </label>
                    <small class="form-hint">
                        <i class="fas fa-info-circle"></i>
                        <?php echo $t('track_buttons_hint', 'Track clicks on buttons and call-to-action elements'); ?>
                    </small>
                </div>
                
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="analytics_track_forms" 
                               <?php echo ($current_settings['analytics_track_forms'] ?? '1') == '1' ? 'checked' : ''; ?>>
                        <span class="checkbox-custom"></span>
                        <span class="checkbox-text"><?php echo $t('track_form_submissions', 'Track Form Submissions'); ?></span>
                    </label>
                    <small class="form-hint">
                        <i class="fas fa-info-circle"></i>
                        <?php echo $t('track_forms_hint', 'Track contact forms, newsletter signups, and other form interactions'); ?>
                    </small>
                </div>
                
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="analytics_track_searches" 
                               <?php echo ($current_settings['analytics_track_searches'] ?? '1') == '1' ? 'checked' : ''; ?>>
                        <span class="checkbox-custom"></span>
                        <span class="checkbox-text"><?php echo $t('track_search_queries', 'Track Search Queries'); ?></span>
                    </label>
                    <small class="form-hint">
                        <i class="fas fa-info-circle"></i>
                        <?php echo $t('track_searches_hint', 'Track what visitors search for on your site'); ?>
                    </small>
                </div>
                
                <div class="form-group">
                    <div class="alert alert-info">
                        <i class="fas fa-lightbulb"></i>
                        <strong><?php echo $t('analytics_tips', 'Analytics Tips'); ?>:</strong>
                        <ul style="margin: 0.5rem 0 0 1rem;">
                            <li><?php echo $t('analytics_tip_1', 'Enable debug mode to see tracking information in page source'); ?></li>
                            <li><?php echo $t('analytics_tip_2', 'Check the Analytics dashboard to view tracked data'); ?></li>
                            <li><?php echo $t('analytics_tip_3', 'All tracking respects user privacy and doesn\'t collect personal information'); ?></li>
                        </ul>
                    </div>
                </div>
                    </div>
                </section>
            </div>
        </div>
    </form>

</div>

<script>
// Mobile Navigation Toggle
document.addEventListener('DOMContentLoaded', function() {
    const navToggle = document.getElementById('settingsNavToggle');
    const navContainer = document.getElementById('settingsNavContainer');
    const toggleIcon = navToggle ? navToggle.querySelector('.toggle-icon') : null;
    
    if (navToggle && navContainer && toggleIcon) {
        navToggle.addEventListener('click', function() {
            navContainer.classList.toggle('mobile-open');
            toggleIcon.classList.toggle('rotated');
            
            // Update aria-expanded for accessibility
            const isOpen = navContainer.classList.contains('mobile-open');
            navToggle.setAttribute('aria-expanded', isOpen);
        });
        
        // Close navigation when clicking outside on mobile
        document.addEventListener('click', function(e) {
            if (window.innerWidth <= 768) {
                if (!navContainer.contains(e.target) && !navToggle.contains(e.target)) {
                    navContainer.classList.remove('mobile-open');
                    toggleIcon.classList.remove('rotated');
                    navToggle.setAttribute('aria-expanded', 'false');
                }
            }
        });
        
        // Reset navigation state on resize back to desktop
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                navContainer.classList.remove('mobile-open');
                toggleIcon.classList.remove('rotated');
                navToggle.setAttribute('aria-expanded', 'false');
            }
        });
    }
});

// Currency selector toggle
document.addEventListener('DOMContentLoaded', function() {
    const currencySelect = document.getElementById('currencySelect');
    const customCurrencyGroup = document.getElementById('customCurrencyGroup');
    
    if (currencySelect && customCurrencyGroup) {
        function toggleCustomCurrency() {
            if (currencySelect.value === 'CUSTOM') {
                customCurrencyGroup.style.display = 'block';
            } else {
                customCurrencyGroup.style.display = 'none';
            }
        }
        
        currencySelect.addEventListener('change', toggleCustomCurrency);
        toggleCustomCurrency(); // Initialize on page load
    }
});

// ScrollSpy: highlight nav link for the section currently in view
(function() {
    var navLinks = document.querySelectorAll('.settings-nav-link[data-section]');
    var sectionIds = Array.from(navLinks).map(function(link) { return link.getAttribute('data-section'); });

    function setActive(sectionId) {
        navLinks.forEach(function(link) {
            if (link.getAttribute('data-section') === sectionId) {
                link.classList.add('active');
                link.setAttribute('aria-current', 'location');
            } else {
                link.classList.remove('active');
                link.removeAttribute('aria-current');
            }
        });
    }

    function updateActiveFromScroll() {
        var scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        var headerOffset = 140;
        var current = sectionIds[0];
        sectionIds.forEach(function(id) {
            var el = document.getElementById(id);
            if (!el) return;
            if (el.offsetTop <= scrollTop + headerOffset) current = id;
        });
        setActive(current);
    }

    window.addEventListener('scroll', function() {
        requestAnimationFrame(updateActiveFromScroll);
    }, { passive: true });
    updateActiveFromScroll();

    var hash = window.location.hash.slice(1);
    if (hash && document.getElementById(hash)) setActive(hash);
})();

// Smooth scroll + close mobile nav on link click
document.querySelectorAll('.settings-nav-link[href^="#"]').forEach(function(link) {
    link.addEventListener('click', function(e) {
        var id = this.getAttribute('href').slice(1);
        if (!id) return;
        var el = document.getElementById(id);
        if (el) {
            e.preventDefault();
            el.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
        if (window.innerWidth <= 768) {
            var container = document.getElementById('settingsNavContainer');
            var icon = document.querySelector('#settingsNavToggle .toggle-icon');
            if (container && icon) {
                container.classList.remove('mobile-open');
                icon.classList.remove('rotated');
                document.getElementById('settingsNavToggle').setAttribute('aria-expanded', 'false');
            }
        }
    });
});

// Real-time validation feedback
document.querySelectorAll('input[required], input[type="email"]').forEach(input => {
    input.addEventListener('blur', function() {
        if (this.hasAttribute('required') && this.value.trim() === '') {
            this.classList.add('error');
            showError(this, 'This field is required');
        } else if (this.type === 'email' && this.value && !isValidEmail(this.value)) {
            this.classList.add('error');
            showError(this, 'Please enter a valid email address');
        } else {
            this.classList.remove('error');
            this.classList.add('success');
            hideError(this);
        }
    });
    
    input.addEventListener('input', function() {
        this.classList.remove('error', 'success');
        hideError(this);
    });
});

// Email validation
function isValidEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

// Show error message
function showError(input, message) {
    hideError(input);
    const errorDiv = document.createElement('div');
    errorDiv.className = 'field-error';
    errorDiv.textContent = message;
    input.parentNode.appendChild(errorDiv);
}

// Hide error message
function hideError(input) {
    const existingError = input.parentNode.querySelector('.field-error');
    if (existingError) {
        existingError.remove();
    }
}

// Color picker sync
document.getElementById('primaryColor').addEventListener('input', function() {
    document.getElementById('primaryColorValue').value = this.value;
});

document.getElementById('secondaryColor').addEventListener('input', function() {
    document.getElementById('secondaryColorValue').value = this.value;
});

// Auto-dismiss alerts after 5 seconds
setTimeout(() => {
    document.querySelectorAll('.alert').forEach(alert => {
        alert.classList.remove('show');
        setTimeout(() => alert.remove(), 300);
    });
}, 5000);

// File upload preview functionality
document.addEventListener('DOMContentLoaded', function() {
    // Handle single file uploads
    const fileInputs = document.querySelectorAll('input[type="file"]:not([multiple])');
    fileInputs.forEach(input => {
        input.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const label = input.nextElementSibling;
                const container = input.closest('.file-upload-container');
                
                // Update label text
                label.querySelector('span').textContent = `Selected: ${file.name}`;
                
                // Create preview
                const reader = new FileReader();
                reader.onload = function(e) {
                    // Remove existing preview
                    const existingPreview = container.querySelector('.new-preview');
                    if (existingPreview) {
                        existingPreview.remove();
                    }
                    
                    // Create new preview
                    const preview = document.createElement('div');
                    preview.className = 'current-image new-preview';
                    preview.innerHTML = `
                        <img src="${e.target.result}" alt="Preview" class="preview-image">
                        <small class="form-hint">New: ${file.name}</small>
                    `;
                    
                    // Insert after label
                    label.parentNode.insertBefore(preview, label.nextSibling);
                };
                reader.readAsDataURL(file);
            }
        });
    });
    
    // Handle multiple file uploads
    const multipleFileInputs = document.querySelectorAll('input[type="file"][multiple]');
    multipleFileInputs.forEach(input => {
        input.addEventListener('change', function(e) {
            const files = Array.from(e.target.files);
            if (files.length > 0) {
                const label = input.nextElementSibling;
                const container = input.closest('.file-upload-container');
                
                // Update label text
                label.querySelector('span').textContent = `Selected ${files.length} file(s)`;
                
                // Remove existing new previews
                const existingPreviews = container.querySelectorAll('.new-preview');
                existingPreviews.forEach(preview => preview.remove());
                
                // Create previews for new files
                const previewContainer = document.createElement('div');
                previewContainer.className = 'current-images new-preview';
                previewContainer.innerHTML = '<small class="form-hint">New files:</small>';
                
                files.forEach((file, index) => {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const preview = document.createElement('div');
                        preview.className = 'current-image';
                        preview.innerHTML = `
                            <img src="${e.target.result}" alt="Preview ${index + 1}" class="preview-image">
                            <small class="form-hint">${file.name}</small>
                        `;
                        previewContainer.appendChild(preview);
                    };
                    reader.readAsDataURL(file);
                });
                
                // Insert after label
                label.parentNode.insertBefore(previewContainer, label.nextSibling);
            }
        });
    });
});
</script>

<style>
/* Modern Settings Styles */
:root {
    --primary: #000000;
    --success: #10b981;
    --danger: #ef4444;
    --warning: #f59e0b;
    --info: #3b82f6;
    --teal: #14b8a6;
}

.settings-container {
    padding: 0;
    position: relative;
}

/* Alert Messages */
.alert {
    padding: 1rem 1.5rem;
    border-radius: 6px;
    margin-bottom: 1.5rem;
    border: none;
    display: flex;
    align-items: center;
    font-weight: 500;
    animation: slideInDown 0.3s ease-out;
}

@keyframes slideInDown {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.alert-success {
    background: var(--color-success-light);
    color: #166534;
}

.alert-info {
    background: #dbeafe;
    color: #1e40af;
    border: 1px solid #93c5fd;
}

.alert .btn-close {
    margin-left: auto;
    opacity: 0.5;
}

.alert .btn-close:hover {
    opacity: 1;
}

/* Page Header */
.page-header {
    background: linear-gradient(135deg, #14b8a6 0%, #0d9488 100%);
    padding: 2rem;
    margin: -2rem -2rem 2rem -2rem;
    border-radius: 0 0 20px 20px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.header-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 2rem;
}

.header-title-section {
    color: var(--text-inverse);
}

.page-title {
    font-size: 2rem;
    font-weight: 700;
    margin: 0 0 0.5rem 0;
    color: var(--text-inverse);
    display: flex;
    align-items: center;
    gap: 1rem;
}

.page-subtitle {
    margin: 0;
    opacity: 0.9;
    font-size: 1rem;
}

.header-actions {
    display: flex;
    gap: 1rem;
}

.btn-icon {
    width: 48px;
    height: 48px;
    border-radius: 6px;
    border: 2px solid rgba(255, 255, 255, 0.2);
    background: rgba(255, 255, 255, 0.1);
    color: var(--text-inverse);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-icon:hover {
    background: rgba(255, 255, 255, 0.2);
    transform: translateY(-2px);
}

/* Smooth scrolling for anchor links */
html { scroll-behavior: smooth; }

/* Settings Layout - Single scrollable page */
.settings-layout {
    display: block;
    margin-bottom: 2rem;
    position: relative;
}

/* Sticky Header - Polaris-inspired clean bar */
.settings-sticky-header {
    position: sticky;
    top: 0;
    z-index: 100;
    margin-inline: -1rem;
    padding-inline: 1rem;
    padding-block: 0.75rem 0.875rem;
    margin-bottom: 1.5rem;
    background: var(--bg-card);
    border-bottom: 1px solid var(--border-primary);
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
}

[data-theme="dark"] .settings-sticky-header,
.admin-shell[data-theme="dark"] .settings-sticky-header {
    background: var(--bg-card);
    border-bottom-color: var(--border-primary);
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
}

.settings-sticky-nav {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
    min-height: 2.75rem;
}

.settings-nav-container {
    flex: 1;
    overflow-x: auto;
    overflow-y: hidden;
    padding-block: 0.25rem;
}

.settings-nav-container::-webkit-scrollbar {
    height: 5px;
}

.settings-nav-container::-webkit-scrollbar-thumb {
    background: var(--border-primary);
    border-radius: 5px;
}

#settings-nav.settings-nav-links {
    display: flex;
    align-items: center;
    gap: 0.125rem;
    flex-wrap: wrap;
}

.settings-nav-link {
    display: inline-flex;
    align-items: center;
    padding: 0.5rem 0.875rem;
    color: var(--text-secondary);
    font-weight: 500;
    font-size: 0.875rem;
    text-decoration: none;
    border-radius: 8px;
    transition: color 0.15s ease, background 0.15s ease;
    white-space: nowrap;
}

.settings-nav-link:hover {
    color: var(--text-primary);
    background: var(--bg-secondary);
}

.settings-nav-link.active {
    color: var(--color-primary-db, var(--primary-color, #008060));
    font-weight: 600;
    background: rgba(0, 128, 96, 0.08);
}

[data-theme="dark"] .settings-nav-link.active,
.admin-shell[data-theme="dark"] .settings-nav-link.active {
    background: rgba(0, 128, 96, 0.18);
}

.settings-sticky-actions {
    flex-shrink: 0;
    margin-inline-start: auto;
}

.settings-sticky-actions .btn-save-inline {
    margin: 0;
}

/* Section cards - single page flow */
.settings-content.settings-content-single {
    display: block;
    background: transparent;
    padding: 0;
    box-shadow: none;
}

.settings-section {
    scroll-margin-top: 4rem;
}

.settings-section-card {
    background: var(--bg-card);
    border-radius: 8px;
    padding: 2rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    border: 1px solid var(--border-primary);
}

[data-theme="dark"] .settings-section-card,
.admin-shell[data-theme="dark"] .settings-section-card {
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.2);
}

/* Mobile Navigation Toggle */
.mobile-nav-toggle {
    display: none;
    margin-bottom: 0;
}

.nav-toggle-btn {
    width: 100%;
    padding: 0.75rem 1rem;
    background: var(--bg-card);
    border: 2px solid var(--border-primary);
    border-radius: 6px;
    color: var(--text-primary);
    font-weight: 600;
    font-size: 0.9375rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    cursor: pointer;
    transition: all 0.2s ease;
}

.nav-toggle-btn:hover {
    background: var(--bg-secondary);
    border-color: var(--color-primary-db);
}

.nav-toggle-btn i:first-child {
    font-size: 1.1rem;
    color: var(--color-primary-db);
}

.toggle-icon {
    transition: transform 0.3s ease;
    font-size: 0.9rem;
}

.toggle-icon.rotated {
    transform: rotate(180deg);
}

/* RTL: Sticky header and nav */
[dir="rtl"] .settings-sticky-nav {
    flex-direction: row-reverse;
}

[dir="rtl"] #settings-nav.settings-nav-links {
    flex-direction: row-reverse;
}

[dir="rtl"] .settings-sticky-actions {
    margin-inline-end: auto;
    margin-inline-start: 0;
}

.pane-header .quick-links {
    margin-top: 0.5rem;
}

.tip-link {
    color: var(--text-secondary);
    text-decoration: none;
    font-size: 0.875rem;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    transition: color 0.2s ease;
}

.tip-link:hover {
    color: #14b8a6;
}

/* Validation states */
.form-input.error {
    border-color: #ef4444;
    animation: shake 0.3s ease-in-out;
}

.form-input.success {
    border-color: #10b981;
}

.field-error {
    color: #ef4444;
    font-size: 0.75rem;
    margin-top: 0.25rem;
    display: block;
}

@keyframes shake {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-5px); }
    75% { transform: translateX(5px); }
}

/* Settings Content (fallback when not single-page) */
.settings-content {
    background: var(--bg-card);
    border-radius: 6px;
    padding: 2rem;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.pane-header {
    margin-bottom: 2rem;
    padding-bottom: 1.5rem;
    border-bottom: 1px solid var(--border-primary);
}

.pane-header h2 {
    font-size: 1.5rem;
    font-weight: 700;
    margin: 0 0 0.5rem 0;
    color: var(--text-primary);
}

.pane-header p {
    margin: 0;
    color: var(--text-secondary);
}

/* Form Elements */
.form-row {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    margin-bottom: 1rem;
    align-items: flex-start;
}

.form-group {
    flex: 1 1 280px;
    min-width: 220px;
    margin-bottom: 1.5rem;
}

.form-group.col-4 {
    flex: 1 1 240px;
}

.form-group.col-6 {
    flex: 1 1 320px;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: var(--text-primary);
    font-size: 0.9375rem;
}

.form-input, .form-textarea {
    width: 100%;
    padding: 0.875rem 1rem;
    border: 2px solid var(--border-primary);
    border-radius: 6px;
    font-size: 0.9375rem;
    transition: all 0.3s ease;
    background: var(--bg-card);
    color: var(--text-primary);
}

.form-input:focus, .form-textarea:focus {
    outline: none;
    border-color: var(--border-focus);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
    background: var(--bg-primary);
}

.form-textarea {
    resize: vertical;
    font-family: inherit;
}

.form-hint {
    display: block;
    margin-top: 0.5rem;
    font-size: 0.8125rem;
    color: var(--text-secondary);
}

/* Color Picker */
.color-picker-group {
    display: flex;
    gap: 1rem;
    align-items: center;
}

.color-input {
    width: 80px;
    height: 48px;
    border: 2px solid var(--border-primary);
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.3s ease;
    background: var(--bg-card);
}

.color-input:hover {
    border-color: var(--border-focus);
}

.color-value {
    flex: 1;
}

/* Checkbox Group */
.checkbox-group {
    padding: 1rem;
    background: var(--bg-secondary);
    border-radius: 6px;
    margin-bottom: 1.5rem;
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    cursor: pointer;
}

.checkbox-label input[type="checkbox"] {
    width: 20px;
    height: 20px;
    cursor: pointer;
}

/* Modern checkbox design */
.checkbox-label {
    display: flex;
    align-items: center;
    gap: var(--space-3);
    cursor: pointer;
    padding: var(--space-2);
    border-radius: var(--radius-lg);
    transition: var(--transition-all);
}

.checkbox-label:hover {
    background: var(--bg-tertiary);
}

.checkbox-label input[type="checkbox"] {
    width: 20px;
    height: 20px;
    border: 2px solid var(--border-primary);
    border-radius: var(--radius-md);
    background: var(--bg-card);
    cursor: pointer;
    transition: var(--transition-all);
    position: relative;
    appearance: none;
    -webkit-appearance: none;
}

.checkbox-label input[type="checkbox"]:checked {
    background: var(--color-primary);
    border-color: var(--color-primary);
}

.checkbox-label input[type="checkbox"]:checked::after {
    content: '✓';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    color: var(--text-inverse);
    font-size: var(--font-size-xs);
    font-weight: var(--font-weight-bold);
}

.checkbox-label input[type="checkbox"]:focus {
    outline: none;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.checkbox-label span {
    font-weight: var(--font-weight-medium);
    color: var(--text-primary);
}

/* Save Button - Polaris primary action */
.btn-save {
    padding: 0.625rem 1.25rem;
    border-radius: 8px;
    border: none;
    background: var(--color-primary-db, var(--primary-color, #008060));
    color: #fff;
    font-weight: 600;
    font-size: 0.875rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    cursor: pointer;
    transition: background 0.15s ease, box-shadow 0.15s ease;
    box-shadow: 0 1px 0 rgba(0, 0, 0, 0.05);
}

.btn-save:hover {
    background: var(--color-primary-db-hover, #006e52);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
}

.btn-save:active {
    box-shadow: inset 0 1px 0 rgba(0, 0, 0, 0.1);
}

.btn-save i {
    font-size: 0.875rem;
    opacity: 0.95;
}

.btn-save-inline {
    flex-shrink: 0;
    white-space: nowrap;
}

/* Responsive */
@media (max-width: 1024px) {
    .settings-nav-item {
        min-width: 130px;
        white-space: nowrap;
        flex-shrink: 0;
    }
    
    .settings-nav-item small.nav-hint {
        display: none;
    }

    .form-group.col-6 {
        flex: 1 1 280px;
    }
}

@media (max-width: 768px) {
    .settings-sticky-nav {
        flex-wrap: wrap;
    }

    .mobile-nav-toggle {
        display: block;
        width: 100%;
    }

    .settings-nav-container {
        display: none;
        width: 100%;
        max-height: 70vh;
        overflow-y: auto;
        border-radius: 6px;
        order: 3;
    }

    .settings-nav-container.mobile-open {
        display: block;
    }

    #settings-nav.settings-nav-links {
        flex-direction: column;
        align-items: stretch;
        gap: 0.25rem;
    }

    .settings-nav-link {
        padding: 0.75rem 1rem;
        width: 100%;
        white-space: normal;
    }

    .settings-sticky-actions {
        width: 100%;
    }

    .settings-sticky-actions .btn-save-inline {
        width: 100%;
        justify-content: center;
    }

    .settings-layout {
        margin-bottom: 1.5rem;
    }

    .settings-section-card {
        padding: 1.25rem;
    }

    .form-group,
    .form-group.col-4,
    .form-group.col-6 {
        flex: 1 1 100%;
        min-width: 100%;
    }

    [dir="rtl"] .settings-sticky-actions {
        margin-inline-end: 0;
        margin-inline-start: 0;
    }
}

@media (max-width: 480px) {
    .settings-nav-link {
        padding: 0.65rem 0.875rem;
        font-size: 0.9rem;
    }
}

/* File Upload Styles */
.file-upload-container {
    position: relative;
    margin-bottom: 1rem;
}

.file-input {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
    overflow: hidden;
}

.file-upload-label {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    background: linear-gradient(135deg, var(--primary) 0%, #333 100%);
    color: var(--text-inverse);
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.3s ease;
    font-weight: 500;
    border: 2px dashed transparent;
    min-height: 48px;
    justify-content: center;
    width: 100%;
    box-sizing: border-box;
}

.file-upload-label:hover {
    background: linear-gradient(135deg, #333 0%, var(--primary) 100%);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.file-upload-label:active {
    transform: translateY(0);
}

.file-upload-label i {
    font-size: 1.2rem;
}

.current-image, .current-images {
    margin-top: 1rem;
    padding: 1rem;
    background: #f8f9fa;
    border-radius: 6px;
    border: 1px solid #e9ecef;
}

.current-image {
    display: inline-block;
    margin-right: 1rem;
    margin-bottom: 1rem;
    text-align: center;
}

.current-images {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
}

.preview-image {
    max-width: 120px;
    max-height: 80px;
    object-fit: cover;
    border-radius: 6px;
    border: 2px solid #dee2e6;
    display: block;
    margin: 0 auto 0.5rem;
}

.current-images .preview-image {
    max-width: 80px;
    max-height: 60px;
}

.file-input:focus + .file-upload-label {
    outline: 2px solid var(--primary);
    outline-offset: 2px;
}

/* File input change indicator */
.file-input:not(:placeholder-shown) + .file-upload-label {
    background: linear-gradient(135deg, var(--success) 0%, #059669 100%);
}

.file-input:not(:placeholder-shown) + .file-upload-label::after {
    content: " ✓";
    font-weight: bold;
}
</style>
