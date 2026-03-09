<?php
require_once __DIR__ . '/settings.php';

class PluginHelper {
    private $settings;
    
    public function __construct($conn = null) {
        try {
            $this->settings = new Settings($conn);
        } catch (Exception $e) {
            // Fallback to prevent errors
            $this->settings = null;
        }
    }
    
    /**
     * Check if a plugin is active
     */
    public function isPluginActive($pluginName) {
        if (!$this->settings) return false;
        return $this->settings->getSetting("plugin_{$pluginName}_status", 'inactive') === 'active';
    }
    
    /**
     * Get plugin configuration
     */
    public function getPluginConfig($pluginName) {
        if (!$this->settings) return [];
        $config = $this->settings->getSetting("plugin_{$pluginName}_config", '{}');
        return json_decode($config, true) ?: [];
    }
    
    /**
     * Verify reCAPTCHA response
     */
    public function verifyRecaptcha($token) {
        if (!$this->isPluginActive('recaptcha')) {
            return true; // Skip verification if plugin not active
        }
        
        $config = $this->getPluginConfig('recaptcha');
        $secretKey = $config['recaptcha_secret_key'] ?? '';
        
        if (empty($secretKey)) {
            return false;
        }
        
        $response = file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret={$secretKey}&response={$token}");
        $result = json_decode($response, true);
        
        return $result['success'] ?? false;
    }
    
    /**
     * Track analytics event
     */
    public function trackAnalyticsEvent($eventName, $eventData = []) {
        // This will be called from JavaScript, so we'll return the event data
        // for the frontend to handle
        return [
            'event' => $eventName,
            'data' => $eventData,
            'ga_active' => $this->isPluginActive('google_analytics'),
            'fb_active' => $this->isPluginActive('facebook_pixel')
        ];
    }
    
    /**
     * Get active payment methods
     */
    public function getActivePaymentMethods() {
        $methods = [];
        
        // Cash on Delivery is always available
        $methods['cod'] = 'Cash on Delivery';
        
        // Check if payment plugins are active
        if ($this->isPluginActive('stripe')) {
            $methods['stripe'] = 'Credit Card (Stripe)';
        }
        
        if ($this->isPluginActive('paypal')) {
            $methods['paypal'] = 'PayPal';
        }
        
        if ($this->isPluginActive('flouci')) {
            $methods['flouci'] = 'Flouci Payment';
        }
        
        return $methods;
    }

    /**
     * Get SMTP configuration
     */
    public function getSmtpConfig() {
        if (!$this->settings) return [];
        if (!$this->isPluginActive('smtp')) {
            return [];
        }
        return $this->getPluginConfig('smtp');
    }
    
    /**
     * Get Mailchimp configuration
     */
    public function getMailchimpConfig() {
        if (!$this->isPluginActive('mailchimp')) {
            return null;
        }
        
        return $this->getPluginConfig('mailchimp');
    }
    
    /**
     * Get WhatsApp configuration
     */
    public function getWhatsappConfig() {
        if (!$this->isPluginActive('whatsapp')) {
            return null;
        }
        
        return $this->getPluginConfig('whatsapp');
    }
    
    /**
     * Get Flouci configuration
     */
    public function getFlouciConfig() {
        if (!$this->isPluginActive('flouci')) {
            return null;
        }
        
        return $this->getPluginConfig('flouci');
    }
    
    /**
     * Get Google Analytics configuration
     */
    public function getGoogleAnalyticsConfig() {
        if (!$this->isPluginActive('google_analytics')) {
            return null;
        }
        
        return $this->getPluginConfig('google_analytics');
    }
    
    /**
     * Get Facebook Pixel configuration
     */
    public function getFacebookPixelConfig() {
        if (!$this->isPluginActive('facebook_pixel')) {
            return null;
        }
        
        return $this->getPluginConfig('facebook_pixel');
    }
}

// Global helper functions for easy access
function isPluginActive($pluginName) {
    static $helper = null;
    if ($helper === null) {
        $helper = new PluginHelper();
    }
    return $helper->isPluginActive($pluginName);
}

function getPluginConfig($pluginName) {
    static $helper = null;
    if ($helper === null) {
        $helper = new PluginHelper();
    }
    return $helper->getPluginConfig($pluginName);
}

function verifyRecaptcha($token) {
    static $helper = null;
    if ($helper === null) {
        $helper = new PluginHelper();
    }
    return $helper->verifyRecaptcha($token);
}
