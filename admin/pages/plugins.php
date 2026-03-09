<?php
require_once __DIR__ . '/../../config/language.php';

$database = new Database();
$conn = $database->getConnection();
$settings = new Settings();

// Initialize language
Language::init();
$t = function($key, $default = null) {
    return Language::t($key, $default);
};

// Handle plugin actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_plugin') {
        $plugin_name = $_POST['plugin_name'] ?? '';
        $plugin_status = $_POST['plugin_status'] ?? 'inactive';
        
        if (!empty($plugin_name)) {
            // Collect plugin-specific configuration
            $config = [];
            foreach ($_POST as $key => $value) {
                if (!in_array($key, ['action', 'plugin_name', 'plugin_status'])) {
                    $config[$key] = $value;
                }
            }
            
            $settings->setSetting("plugin_{$plugin_name}_status", $plugin_status);
            $settings->setSetting("plugin_{$plugin_name}_config", json_encode($config));
            
            $success_msg = $t('plugin_updated_successfully', 'Plugin updated successfully!');
            header('Location: ?page=plugins&success=' . urlencode($success_msg));
            exit;
        }
    }
}

// Get available plugins
$available_plugins = [
    'google_analytics' => [
        'name' => 'Google Analytics',
        'description' => $t('plugin_ga_description', 'Track website traffic and user behavior with Google Analytics 4'),
        'icon' => 'fab fa-google',
        'category' => $t('analytics', 'Analytics'),
        'status' => $settings->getSetting('plugin_google_analytics_status', 'inactive'),
        'config' => json_decode($settings->getSetting('plugin_google_analytics_config', '{}'), true)
    ],
    'facebook_pixel' => [
        'name' => 'Facebook Pixel',
        'description' => $t('plugin_fb_pixel_description', 'Track conversions and create custom audiences for Facebook ads'),
        'icon' => 'fab fa-facebook',
        'category' => $t('analytics', 'Analytics'),
        'status' => $settings->getSetting('plugin_facebook_pixel_status', 'inactive'),
        'config' => json_decode($settings->getSetting('plugin_facebook_pixel_config', '{}'), true)
    ],
    'mailchimp' => [
        'name' => 'Mailchimp',
        'description' => $t('plugin_mailchimp_description', 'Sync customers and send marketing emails with Mailchimp'),
        'icon' => 'fab fa-mailchimp',
        'category' => $t('marketing', 'Marketing'),
        'status' => $settings->getSetting('plugin_mailchimp_status', 'inactive'),
        'config' => json_decode($settings->getSetting('plugin_mailchimp_config', '{}'), true)
    ],
    'flouci' => [
        'name' => 'Flouci Payment',
        'description' => $t('plugin_flouci_description', 'Accept payments via Flouci gateway (Tunisia & North Africa)'),
        'icon' => 'fas fa-credit-card',
        'category' => $t('payment', 'Payment'),
        'status' => $settings->getSetting('plugin_flouci_status', 'inactive'),
        'config' => json_decode($settings->getSetting('plugin_flouci_config', '{}'), true)
    ],
    'recaptcha' => [
        'name' => 'Google reCAPTCHA',
        'description' => $t('plugin_recaptcha_description', 'Protect forms from spam and abuse with reCAPTCHA'),
        'icon' => 'fas fa-shield-alt',
        'category' => $t('security', 'Security'),
        'status' => $settings->getSetting('plugin_recaptcha_status', 'inactive'),
        'config' => json_decode($settings->getSetting('plugin_recaptcha_config', '{}'), true)
    ],
    'smtp' => [
        'name' => 'SMTP Email',
        'description' => $t('plugin_smtp_description', 'Send emails using custom SMTP server configuration'),
        'icon' => 'fas fa-envelope',
        'category' => $t('communication', 'Communication'),
        'status' => $settings->getSetting('plugin_smtp_status', 'inactive'),
        'config' => json_decode($settings->getSetting('plugin_smtp_config', '{}'), true)
    ],
    'whatsapp' => [
        'name' => 'WhatsApp Business',
        'description' => $t('plugin_whatsapp_description', 'Enable WhatsApp chat support for customers'),
        'icon' => 'fab fa-whatsapp',
        'category' => $t('communication', 'Communication'),
        'status' => $settings->getSetting('plugin_whatsapp_status', 'inactive'),
        'config' => json_decode($settings->getSetting('plugin_whatsapp_config', '{}'), true)
    ],
    'firstdelivery' => [
        'name' => 'First Delivery (Tunisia)',
        'description' => $t('plugin_firstdelivery_description', 'Ship COD orders with First Delivery in Tunisia'),
        'icon' => 'fas fa-truck',
        'category' => $t('shipping', 'Shipping'),
        'status' => $settings->getSetting('plugin_firstdelivery_status', 'inactive'),
        'config' => json_decode($settings->getSetting('plugin_firstdelivery_config', '{}'), true)
    ],
    'colissimo' => [
        'name' => 'Colissimo (Tunisia)',
        'description' => $t('plugin_colissimo_description', 'Ship orders with Colissimo Tunisia'),
        'icon' => 'fas fa-truck-loading',
        'category' => $t('shipping', 'Shipping'),
        'status' => $settings->getSetting('plugin_colissimo_status', 'inactive'),
        'config' => json_decode($settings->getSetting('plugin_colissimo_config', '{}'), true)
    ]
];

// Count active plugins
$active_count = count(array_filter($available_plugins, function($p) { return $p['status'] == 'active'; }));

// Group by category
$grouped_plugins = [];
foreach ($available_plugins as $key => $plugin) {
    $category = $plugin['category'];
    if (!isset($grouped_plugins[$category])) {
        $grouped_plugins[$category] = [];
    }
    $plugin['key'] = $key;
    $grouped_plugins[$category][] = $plugin;
}
?>

<!-- Modern Plugins Management Interface -->
<div class="plugins-container">
    
    <!-- Success Messages -->
    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            <?php echo htmlspecialchars($_GET['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <!-- Statistics Cards -->
    <div class="stats-cards-grid">
        <div class="stat-card blue">
            <div class="stat-icon">
                <i class="fas fa-puzzle-piece"></i>
            </div>
            <div class="stat-content">
                <h3><?php echo count($available_plugins); ?></h3>
                <p><?php echo $t('available_plugins', 'Available Plugins'); ?></p>
            </div>
        </div>
        <div class="stat-card green">
            <div class="stat-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-content">
                <h3><?php echo $active_count; ?></h3>
                <p><?php echo $t('active_plugins', 'Active Plugins'); ?></p>
            </div>
        </div>
        <div class="stat-card purple">
            <div class="stat-icon">
                <i class="fas fa-credit-card"></i>
            </div>
            <div class="stat-content">
                <h3>2</h3>
                <p><?php echo $t('payment_methods', 'Payment Methods'); ?></p>
            </div>
        </div>
        <div class="stat-card orange">
            <div class="stat-icon">
                <i class="fas fa-chart-bar"></i>
            </div>
            <div class="stat-content">
                <h3>2</h3>
                <p><?php echo $t('analytics_tools', 'Analytics Tools'); ?></p>
            </div>
        </div>
    </div>

    <!-- Plugins by Category -->
    <?php foreach ($grouped_plugins as $category => $plugins): ?>
        <div class="plugin-category-section">
            <div class="category-header">
                <h2><?php echo $category . ' ' . $t('plugins', 'Plugins'); ?></h2>
                <span class="category-count"><?php echo count($plugins); ?> <?php echo $t('plugins', 'plugins'); ?></span>
            </div>
            
            <div class="plugins-grid">
                <?php foreach ($plugins as $plugin): ?>
                    <div class="plugin-card <?php echo $plugin['status'] == 'active' ? 'active' : ''; ?>">
                        <div class="plugin-icon-wrapper">
                            <div class="plugin-icon">
                                <i class="<?php echo $plugin['icon']; ?>"></i>
                            </div>
                            <?php if ($plugin['status'] == 'active'): ?>
                                <div class="active-indicator">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="plugin-details">
                            <h3 class="plugin-name"><?php echo $plugin['name']; ?></h3>
                            <p class="plugin-description"><?php echo $plugin['description']; ?></p>
                            
                            <div class="plugin-meta">
                                <span class="plugin-status status-<?php echo $plugin['status']; ?>">
                                    <?php echo $plugin['status'] == 'active' ? $t('active') : $t('inactive'); ?>
                                </span>
                                <span class="plugin-category-badge"><?php echo $category; ?></span>
                            </div>
                        </div>
                        
                        <div class="plugin-actions">
                            <?php if ($plugin['status'] == 'active'): ?>
                                <button class="btn-configure" onclick="configurePlugin('<?php echo $plugin['key']; ?>')">
                                    <i class="fas fa-cog"></i>
                                    <?php echo $t('configure'); ?>
                                </button>
                            <?php else: ?>
                                <button class="btn-activate" onclick="configurePlugin('<?php echo $plugin['key']; ?>')">
                                    <i class="fas fa-power-off"></i>
                                    <?php echo $t('activate'); ?>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>

</div>

<!-- Plugin Configuration Modal -->
<div class="modal-overlay" id="pluginModal">
    <div class="modal-container">
        <div class="modal-header">
            <h2><i class="fas fa-cog"></i> <span id="pluginModalTitle"><?php echo $t('configure_plugin', 'Configure Plugin'); ?></span></h2>
            <button class="modal-close" onclick="closePluginModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form method="POST" id="pluginForm">
            <?php echo CsrfHelper::getTokenField(); ?>
            <div class="modal-body">
                <input type="hidden" name="action" value="update_plugin">
                <input type="hidden" name="plugin_name" id="pluginName">
                
                <div class="form-group">
                    <label><?php echo $t('plugin_status', 'Plugin Status'); ?></label>
                    <div class="status-toggle">
                        <label class="toggle-option">
                            <input type="radio" name="plugin_status" value="inactive" id="statusInactive">
                            <span class="toggle-label inactive">
                                <i class="fas fa-power-off"></i>
                                <?php echo $t('inactive'); ?>
                            </span>
                        </label>
                        <label class="toggle-option">
                            <input type="radio" name="plugin_status" value="active" id="statusActive">
                            <span class="toggle-label active">
                                <i class="fas fa-check-circle"></i>
                                <?php echo $t('active'); ?>
                            </span>
                        </label>
                    </div>
                </div>
                
                <div id="pluginConfigFields">
                    <!-- Plugin-specific configuration will be loaded here -->
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closePluginModal()">
                    <i class="fas fa-times"></i> <?php echo $t('cancel'); ?>
                </button>
                <button type="submit" class="btn-primary">
                    <i class="fas fa-save"></i> <?php echo $t('save_configuration', 'Save Configuration'); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const pluginsData = <?php echo json_encode($available_plugins); ?>;
const translations = {
    configure_plugin: '<?php echo $t('configure_plugin', 'Configure Plugin'); ?>',
    google_analytics_tracking_id: '<?php echo $t('google_analytics_tracking_id', 'Google Analytics Tracking ID'); ?>',
    enter_ga4_measurement_id: '<?php echo $t('enter_ga4_measurement_id', 'Enter your Google Analytics 4 Measurement ID'); ?>',
    facebook_pixel_id: '<?php echo $t('facebook_pixel_id', 'Facebook Pixel ID'); ?>',
    find_pixel_id: '<?php echo $t('find_pixel_id', 'Find your Pixel ID in Facebook Events Manager'); ?>',
    mailchimp_api_key: '<?php echo $t('mailchimp_api_key', 'Mailchimp API Key'); ?>',
    audience_list_id: '<?php echo $t('audience_list_id', 'Audience List ID'); ?>',
    publishable_key: '<?php echo $t('publishable_key', 'Publishable Key'); ?>',
    secret_key: '<?php echo $t('secret_key', 'Secret Key'); ?>',
    paypal_client_id: '<?php echo $t('paypal_client_id', 'PayPal Client ID'); ?>',
    paypal_secret: '<?php echo $t('paypal_secret', 'PayPal Secret'); ?>',
    mode: '<?php echo $t('mode', 'Mode'); ?>',
    sandbox_testing: '<?php echo $t('sandbox_testing', 'Sandbox (Testing)'); ?>',
    live_production: '<?php echo $t('live_production', 'Live (Production)'); ?>',
    app_secret: '<?php echo $t('app_secret', 'App Secret'); ?>',
    app_token: '<?php echo $t('app_token', 'App Token'); ?>',
    get_from_flouci_dashboard: '<?php echo $t('get_from_flouci_dashboard', 'Get this from your Flouci dashboard'); ?>',
    success_url: '<?php echo $t('success_url', 'Success URL'); ?>',
    fail_url: '<?php echo $t('fail_url', 'Fail URL'); ?>',
    url_redirect_success: '<?php echo $t('url_redirect_success', 'URL to redirect after successful payment'); ?>',
    url_redirect_fail: '<?php echo $t('url_redirect_fail', 'URL to redirect after failed payment'); ?>',
    site_key: '<?php echo $t('site_key', 'Site Key'); ?>',
    smtp_host: '<?php echo $t('smtp_host', 'SMTP Host'); ?>',
    port: '<?php echo $t('port', 'Port'); ?>',
    encryption: '<?php echo $t('encryption', 'Encryption'); ?>',
    username: '<?php echo $t('username', 'Username'); ?>',
    password: '<?php echo $t('password', 'Password'); ?>',
    whatsapp_business_phone: '<?php echo $t('whatsapp_business_phone', 'WhatsApp Business Phone Number'); ?>',
    include_country_code: '<?php echo $t('include_country_code', 'Include country code without spaces or special characters'); ?>',
    welcome_message: '<?php echo $t('welcome_message', 'Welcome Message'); ?>',
    no_config_required: '<?php echo $t('no_config_required', 'No additional configuration required for this plugin.'); ?>'
};

function configurePlugin(pluginKey) {
    const plugin = pluginsData[pluginKey];
    
    document.getElementById('pluginName').value = pluginKey;
    document.getElementById('pluginModalTitle').textContent = translations.configure_plugin + ' ' + plugin.name;
    
    // Set status
    if (plugin.status === 'active') {
        document.getElementById('statusActive').checked = true;
    } else {
        document.getElementById('statusInactive').checked = true;
    }
    
    // Load plugin-specific configuration
    loadPluginConfig(pluginKey, plugin);
    
    document.getElementById('pluginModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closePluginModal() {
    document.getElementById('pluginModal').classList.remove('active');
    document.body.style.overflow = '';
}

function loadPluginConfig(pluginKey, plugin) {
    const configDiv = document.getElementById('pluginConfigFields');
    
    switch(pluginKey) {
        case 'google_analytics':
            configDiv.innerHTML = `
                <div class="form-group">
                    <label>${translations.google_analytics_tracking_id}</label>
                    <input type="text" class="form-input" name="ga_tracking_id" 
                           value="${plugin.config.ga_tracking_id || ''}" 
                           placeholder="G-XXXXXXXXXX">
                    <small class="form-hint">
                        <i class="fas fa-info-circle"></i>
                        ${translations.enter_ga4_measurement_id}
                    </small>
                </div>
            `;
            break;
            
        case 'facebook_pixel':
            configDiv.innerHTML = `
                <div class="form-group">
                    <label>${translations.facebook_pixel_id}</label>
                    <input type="text" class="form-input" name="fb_pixel_id" 
                           value="${plugin.config.fb_pixel_id || ''}" 
                           placeholder="123456789012345">
                    <small class="form-hint">
                        <i class="fas fa-info-circle"></i>
                        ${translations.find_pixel_id}
                    </small>
                </div>
            `;
            break;
            
        case 'mailchimp':
            configDiv.innerHTML = `
                <div class="form-group">
                    <label>${translations.mailchimp_api_key}</label>
                    <input type="text" class="form-input" name="mc_api_key" 
                           value="${plugin.config.mc_api_key || ''}" 
                           placeholder="your-api-key-us1">
                </div>
                <div class="form-group">
                    <label>${translations.audience_list_id}</label>
                    <input type="text" class="form-input" name="mc_list_id" 
                           value="${plugin.config.mc_list_id || ''}" 
                           placeholder="1234567890">
                </div>
            `;
            break;
            
        case 'flouci':
            configDiv.innerHTML = `
                <div class="form-group">
                    <label>${translations.app_secret}</label>
                    <input type="password" class="form-input" name="flouci_app_secret" 
                           value="${plugin.config.flouci_app_secret || ''}" 
                           placeholder="your-app-secret">
                    <small class="form-help">${translations.get_from_flouci_dashboard}</small>
                </div>
                <div class="form-group">
                    <label>${translations.app_token}</label>
                    <input type="password" class="form-input" name="flouci_app_token" 
                           value="${plugin.config.flouci_app_token || ''}" 
                           placeholder="your-app-token">
                    <small class="form-help">${translations.get_from_flouci_dashboard}</small>
                </div>
                <div class="form-group">
                    <label>${translations.mode}</label>
                    <select class="form-input" name="flouci_mode">
                        <option value="sandbox" ${plugin.config.flouci_mode === 'sandbox' ? 'selected' : ''}>${translations.sandbox_testing}</option>
                        <option value="live" ${plugin.config.flouci_mode === 'live' ? 'selected' : ''}>${translations.live_production}</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>${translations.success_url}</label>
                    <input type="url" class="form-input" name="flouci_success_url" 
                           value="${plugin.config.flouci_success_url || ''}" 
                           placeholder="https://yoursite.com/success">
                    <small class="form-help">${translations.url_redirect_success}</small>
                </div>
                <div class="form-group">
                    <label>${translations.fail_url}</label>
                    <input type="url" class="form-input" name="flouci_fail_url" 
                           value="${plugin.config.flouci_fail_url || ''}" 
                           placeholder="https://yoursite.com/fail">
                    <small class="form-help">${translations.url_redirect_fail}</small>
                </div>
            `;
            break;
            
        case 'recaptcha':
            configDiv.innerHTML = `
                <div class="form-group">
                    <label>${translations.site_key}</label>
                    <input type="text" class="form-input" name="recaptcha_site_key" 
                           value="${plugin.config.recaptcha_site_key || ''}" 
                           placeholder="6Lc...">
                </div>
                <div class="form-group">
                    <label>${translations.secret_key}</label>
                    <input type="password" class="form-input" name="recaptcha_secret_key" 
                           value="${plugin.config.recaptcha_secret_key || ''}" 
                           placeholder="6Lc...">
                </div>
            `;
            break;
            
        case 'smtp':
            configDiv.innerHTML = `
                <div class="form-group">
                    <label>${translations.smtp_host}</label>
                    <input type="text" class="form-input" name="smtp_host" 
                           value="${plugin.config.smtp_host || ''}" 
                           placeholder="smtp.gmail.com">
                </div>
                <div class="form-row">
                    <div class="form-group col-6">
                        <label>${translations.port}</label>
                        <input type="number" class="form-input" name="smtp_port" 
                               value="${plugin.config.smtp_port || '587'}" 
                               placeholder="587">
                    </div>
                    <div class="form-group col-6">
                        <label>${translations.encryption}</label>
                        <select class="form-input" name="smtp_encryption">
                            <option value="tls" ${plugin.config.smtp_encryption === 'tls' ? 'selected' : ''}>TLS</option>
                            <option value="ssl" ${plugin.config.smtp_encryption === 'ssl' ? 'selected' : ''}>SSL</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>${translations.username}</label>
                    <input type="text" class="form-input" name="smtp_username" 
                           value="${plugin.config.smtp_username || ''}" 
                           placeholder="your@email.com">
                </div>
                <div class="form-group">
                    <label>${translations.password}</label>
                    <input type="password" class="form-input" name="smtp_password" 
                           value="${plugin.config.smtp_password || ''}" 
                           placeholder="your-password">
                </div>
            `;
            break;
            
        case 'whatsapp':
            configDiv.innerHTML = `
                <div class="form-group">
                    <label>${translations.whatsapp_business_phone}</label>
                    <input type="tel" class="form-input" name="whatsapp_phone" 
                           value="${plugin.config.whatsapp_phone || ''}" 
                           placeholder="+1234567890">
                    <small class="form-hint">
                        <i class="fas fa-info-circle"></i>
                        ${translations.include_country_code}
                    </small>
                </div>
                <div class="form-group">
                    <label>${translations.welcome_message}</label>
                    <textarea class="form-textarea" rows="3" name="whatsapp_message" 
                              placeholder="Hi! How can we help you?">${plugin.config.whatsapp_message || ''}</textarea>
                </div>
            `;
            break;

        case 'firstdelivery':
            configDiv.innerHTML = `
                <div class="form-group">
                    <label>Environment URL</label>
                    <input type="text" class="form-input" name="env_url" 
                           value="${plugin.config.env_url || 'https://www.firstdeliverygroup.com/api/v2'}" 
                           placeholder="https://www.firstdeliverygroup.com/api/v2">
                    <small class="form-hint">
                        <i class="fas fa-info-circle"></i>
                        Base API URL for First Delivery (keep default unless they give you another).
                    </small>
                </div>
                <div class="form-group">
                    <label>Access Token</label>
                    <input type="password" class="form-input" name="access_token" 
                           value="${plugin.config.access_token || ''}" 
                           placeholder="Your First Delivery access token">
                    <small class="form-hint">
                        <i class="fas fa-info-circle"></i>
                        Paste the Bearer token provided by First Delivery.
                    </small>
                </div>
            `;
            break;

        case 'colissimo':
            configDiv.innerHTML = `
                <div class="form-group">
                    <label>WSDL URL</label>
                    <input type="text" class="form-input" name="wsdl_url" 
                           value="${plugin.config.wsdl_url || 'http://delivery.colissimo.com.tn/wsColissimoGo/wsColissimoGo.asmx?wsdl'}" 
                           placeholder="http://delivery.colissimo.com.tn/wsColissimoGo/wsColissimoGo.asmx?wsdl">
                </div>
                <div class="form-group">
                    <label>Colis User</label>
                    <input type="text" class="form-input" name="colis_user" 
                           value="${plugin.config.colis_user || ''}" 
                           placeholder="clt_...">
                </div>
                <div class="form-group">
                    <label>Colis Pass</label>
                    <input type="password" class="form-input" name="colis_pass" 
                           value="${plugin.config.colis_pass || ''}" 
                           placeholder="votre_pass">
                </div>
            `;
            break;

        default:
            configDiv.innerHTML = '<p style="color: var(--text-secondary);">' + translations.no_config_required + '</p>';
    }
}

// Close modal on overlay click
document.getElementById('pluginModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closePluginModal();
    }
});

// Auto-dismiss alerts
setTimeout(() => {
    document.querySelectorAll('.alert').forEach(alert => {
        alert.classList.remove('show');
        setTimeout(() => alert.remove(), 300);
    });
}, 5000);
</script>

<style>
/* Modern Plugins Management Styles */
:root {
    --primary: #000000;
    --success: #10b981;
    --danger: #ef4444;
    --warning: #f59e0b;
    --info: #3b82f6;
    --purple: #8b5cf6;
}

.plugins-container {
    padding: 0;
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
    color: var(--color-success-dark);
}

.alert .btn-close {
    margin-left: auto;
    opacity: 0.5;
}

/* Statistics Cards */
.stats-cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-bottom: 3rem;
}

.stat-card {
    background: var(--bg-card);
    border-radius: 6px;
    padding: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1.5rem;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    transition: all 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
}

.stat-icon {
    width: 64px;
    height: 64px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.75rem;
    color: var(--text-inverse);
}

.stat-card.blue .stat-icon {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
}

.stat-card.green .stat-icon {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
}

.stat-card.purple .stat-icon {
    background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
}

.stat-card.orange .stat-icon {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
}

.stat-content h3 {
    font-size: 2rem;
    font-weight: 700;
    margin: 0 0 0.25rem 0;
}

.stat-content p {
    margin: 0;
    color: var(--text-secondary);
    font-size: 0.875rem;
}

/* Plugin Category Section */
.plugin-category-section {
    margin-bottom: 3rem;
}

.category-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}

.category-header h2 {
    font-size: 1.5rem;
    font-weight: 700;
    margin: 0;
    color: var(--text-primary);
}

.category-count {
    padding: 0.5rem 1rem;
    border-radius: 6px;
    background: var(--bg-secondary);
    color: var(--text-secondary);
    font-size: 0.875rem;
    font-weight: 600;
}

/* Plugins Grid */
.plugins-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 1.5rem;
}

.plugin-card {
    background: var(--bg-card);
    border: 2px solid var(--border-primary);
    border-radius: 6px;
    padding: 1.5rem;
    transition: all 0.3s ease;
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.plugin-card:hover {
    border-color: var(--color-primary);
    box-shadow: 0 8px 24px var(--color-primary-light, rgba(59, 130, 246, 0.15));
    transform: translateY(-4px);
}

.plugin-card.active {
    border-color: var(--color-success);
    box-shadow: 0 4px 16px var(--color-success-light, rgba(16, 185, 129, 0.15));
}

.plugin-icon-wrapper {
    position: relative;
    width: fit-content;
}

.plugin-icon {
    width: 72px;
    height: 72px;
    border-radius: 6px;
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    color: var(--text-inverse);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
}

.active-indicator {
    position: absolute;
    top: -8px;
    right: -8px;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: #10b981;
    color: var(--text-inverse);
    display: flex;
    align-items: center;
    justify-content: center;
    border: 3px solid white;
    box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
}

.plugin-name {
    font-size: 1.25rem;
    font-weight: 600;
    margin: 0 0 0.5rem 0;
    color: var(--text-primary);
}

.plugin-description {
    margin: 0 0 1rem 0;
    color: var(--text-secondary);
    font-size: 0.9375rem;
    line-height: 1.6;
}

.plugin-meta {
    display: flex;
    gap: 0.75rem;
}

.plugin-status {
    padding: 0.375rem 0.75rem;
    border-radius: 6px;
    font-size: 0.8125rem;
    font-weight: 600;
    text-transform: uppercase;
}

.plugin-status.status-active {
    background: var(--color-success-light);
    color: var(--color-success-dark);
}

.plugin-status.status-inactive {
    background: var(--bg-tertiary);
    color: var(--text-secondary);
}

.plugin-category-badge {
    padding: 0.375rem 0.75rem;
    border-radius: 6px;
    background: var(--color-primary-light);
    color: var(--color-primary-dark);
    font-size: 0.8125rem;
    font-weight: 600;
}

.plugin-actions {
    margin-top: auto;
}

.btn-configure, .btn-activate {
    width: 100%;
    padding: 0.875rem 1.5rem;
    border-radius: 6px;
    border: none;
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-configure {
    background: var(--color-primary);
    color: var(--text-inverse);
}

.btn-configure:hover {
    background: var(--color-primary-hover);
    transform: translateY(-2px);
}

.btn-activate {
    background: var(--color-success);
    color: var(--text-inverse);
}

.btn-activate:hover {
    background: var(--color-success-hover);
    transform: translateY(-2px);
}

/* Modal */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: var(--bg-modal);
    backdrop-filter: blur(4px);
    z-index: var(--z-modal-backdrop, 1040);
    display: none;
    align-items: center;
    justify-content: center;
    padding: 2rem;
}

.modal-overlay.active {
    display: flex;
}

.modal-container {
    background: var(--bg-card);
    border-radius: 6px;
    width: 100%;
    max-width: 600px;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    box-shadow: 0 20px 60px var(--shadow-xl, rgba(0, 0, 0, 0.3));
    animation: modalSlideIn 0.3s ease-out;
    z-index: var(--z-modal, 1050);
}

@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: translateY(-50px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.modal-header {
    padding: 2rem;
    border-bottom: 2px solid var(--border-primary);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h2 {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: 1rem;
}

.modal-close {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    border: none;
    background: var(--bg-tertiary);
    color: var(--text-secondary);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
}

.modal-close:hover {
    background: var(--border-primary);
    color: var(--color-primary);
}

.modal-body {
    padding: 2rem;
    overflow-y: auto;
    flex: 1;
}

.modal-footer {
    padding: 1.5rem 2rem;
    border-top: 2px solid var(--border-primary);
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
}

/* Form Elements */
.form-row {
    display: flex;
    gap: 1rem;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-group.col-6 {
    flex: 0 0 calc(50% - 0.5rem);
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: var(--text-primary);
    font-size: 0.9375rem;
}

.form-input, .form-textarea, select.form-input {
    width: 100%;
    padding: 0.875rem 1rem;
    border: 2px solid var(--border-primary);
    border-radius: 6px;
    font-size: 0.9375rem;
    background: var(--bg-card);
    color: var(--text-primary);
    transition: all 0.3s ease;
}

select.form-input {
    cursor: pointer;
    appearance: none;
    -webkit-appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%2364748b' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 1rem center;
    padding-right: 2.5rem;
}

[data-theme="dark"] select.form-input {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23cbd5e1' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
}

.form-input:focus, .form-textarea:focus, select.form-input:focus {
    outline: none;
    border-color: var(--border-focus);
    box-shadow: 0 0 0 3px var(--color-primary-light, rgba(99, 102, 241, 0.1));
    background: var(--bg-primary);
}

select.form-input option {
    background: var(--bg-card);
    color: var(--text-primary);
}

.form-textarea {
    resize: vertical;
    font-family: inherit;
}

.form-hint, .form-help {
    display: block;
    margin-top: 0.5rem;
    font-size: 0.8125rem;
    color: var(--text-secondary);
}

/* Status Toggle */
.status-toggle {
    display: flex;
    gap: 1rem;
    padding: 0.5rem;
    background: var(--bg-secondary);
    border-radius: 6px;
}

.toggle-option {
    flex: 1;
    cursor: pointer;
}

.toggle-option input[type="radio"] {
    display: none;
}

.toggle-label {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
    padding: 0.875rem 1.5rem;
    border-radius: 6px;
    font-weight: 600;
    transition: all 0.3s ease;
}

.toggle-label.inactive {
    background: transparent;
    color: var(--text-secondary);
}

.toggle-label.active {
    background: transparent;
    color: var(--text-secondary);
}

.toggle-option input[type="radio"]:checked + .toggle-label {
    background: var(--bg-card);
    box-shadow: var(--shadow-sm, 0 2px 8px rgba(0, 0, 0, 0.05));
}

.toggle-option input[type="radio"]:checked + .toggle-label.inactive {
    color: var(--color-error);
}

.toggle-option input[type="radio"]:checked + .toggle-label.active {
    color: var(--color-success);
}

.btn-primary, .btn-secondary {
    padding: 0.875rem 2rem;
    border-radius: 6px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 0.75rem;
    cursor: pointer;
    transition: all 0.3s ease;
    border: none;
}

.btn-primary {
    background: var(--color-primary);
    color: var(--text-inverse);
}

.btn-primary:hover {
    background: var(--color-primary-hover);
}

.btn-secondary {
    background: var(--bg-tertiary);
    color: var(--text-secondary);
}

.btn-secondary:hover {
    background: var(--border-primary);
}

/* Responsive */
@media (max-width: 768px) {
    .stats-cards-grid {
        grid-template-columns: 1fr;
    }
    
    .plugins-grid {
        grid-template-columns: 1fr;
    }
    
    .form-row {
        flex-direction: column;
    }
    
    .form-group.col-6 {
        flex: 1;
    }
}
</style>
