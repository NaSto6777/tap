<?php
require_once __DIR__ . '/plugin_helper.php';

class EmailService {
    private $settings;
    private $smtpConfig;
    private $useSmtp;
    
    public function __construct($settings = null, $pluginHelper = null) {
        $this->settings = $settings ?? new Settings();
        // Default to no SMTP unless we can safely load it
        $this->smtpConfig = [];
        $this->useSmtp = false;
        try {
            $helper = $pluginHelper ?? new PluginHelper();
            // getSmtpConfig() should already respect plugin activation; guard in case of failures
            $config = $helper->getSmtpConfig();
            if (is_array($config) && !empty($config)) {
                $this->smtpConfig = $config;
                $this->useSmtp = true;
            }
        } catch (\Throwable $e) {
            // If plugins/config are misconfigured, silently fallback to PHP mail
            $this->smtpConfig = [];
            $this->useSmtp = false;
        }
    }
    
    /**
     * Send email using SMTP or PHP mail()
     */
    public function send($to, $subject, $body, $isHTML = true) {
        if ($this->useSmtp) {
            return $this->sendViaSmtp($to, $subject, $body, $isHTML);
        } else {
            return $this->sendViaPhpMail($to, $subject, $body, $isHTML);
        }
    }
    
    /**
     * Send email via SMTP using PHPMailer
     */
    private function sendViaSmtp($to, $subject, $body, $isHTML = true) {
        // For now, we'll use a simple SMTP implementation
        // In production, you'd want to use PHPMailer
        $headers = [
            'From: ' . $this->settings->getSetting('contact_email', 'noreply@example.com'),
            'Reply-To: ' . $this->settings->getSetting('contact_email', 'noreply@example.com'),
            'MIME-Version: 1.0',
            'Content-Type: ' . ($isHTML ? 'text/html' : 'text/plain') . '; charset=UTF-8'
        ];
        
        return mail($to, $subject, $body, implode("\r\n", $headers));
    }
    
    /**
     * Send email via PHP mail()
     */
    private function sendViaPhpMail($to, $subject, $body, $isHTML = true) {
        $headers = [
            'From: ' . $this->settings->getSetting('contact_email', 'noreply@example.com'),
            'Reply-To: ' . $this->settings->getSetting('contact_email', 'noreply@example.com'),
            'MIME-Version: 1.0',
            'Content-Type: ' . ($isHTML ? 'text/html' : 'text/plain') . '; charset=UTF-8'
        ];
        
        return mail($to, $subject, $body, implode("\r\n", $headers));
    }
    
    /**
     * Send order confirmation email
     */
    public function sendOrderConfirmation($orderData, $customerEmail) {
        $subject = "Order Confirmation - " . $orderData['order_number'];
        $body = $this->getOrderConfirmationTemplate($orderData);
        
        return $this->send($customerEmail, $subject, $body, true);
    }
    
    /**
     * Send order status update email
     */
    public function sendOrderStatusUpdate($orderData, $customerEmail, $newStatus) {
        $subject = "Order Update - " . $orderData['order_number'];
        $body = $this->getOrderStatusUpdateTemplate($orderData, $newStatus);
        
        return $this->send($customerEmail, $subject, $body, true);
    }
    
    /**
     * Send contact form notification to admin
     */
    public function sendContactFormNotification($formData) {
        $adminEmail = $this->settings->getSetting('contact_email', 'admin@example.com');
        $subject = "New Contact Form Submission";
        $body = $this->getContactFormNotificationTemplate($formData);
        
        return $this->send($adminEmail, $subject, $body, true);
    }
    
    /**
     * Get order confirmation email template
     */
    private function getOrderConfirmationTemplate($orderData) {
        // Make settings and currency formatting available to the template
        $settings = $this->settings;
        $currency = $settings->getSetting('currency', 'USD');
        $currency_symbol = $currency === 'USD' ? '$' : ($currency === 'EUR' ? '€' : ($currency === 'GBP' ? '£' : ($currency === 'TND' ? 'TND' : $currency . ' ')));
        $rightPositionCurrencies = ['TND','AED','SAR','QAR','KWD','BHD','OMR','MAD','DZD','EGP'];
        $price_position_right = in_array($currency, $rightPositionCurrencies, true);
        $price_prefix = $price_position_right ? '' : ($currency_symbol);
        $price_suffix = $price_position_right ? (' ' . $currency_symbol) : '';

        ob_start();
        include __DIR__ . '/email_templates/order_confirmation.php';
        return ob_get_clean();
    }
    
    /**
     * Get order status update email template
     */
    private function getOrderStatusUpdateTemplate($orderData, $newStatus) {
        ob_start();
        include __DIR__ . '/email_templates/order_status_update.php';
        return ob_get_clean();
    }
    
    /**
     * Get contact form notification email template
     */
    private function getContactFormNotificationTemplate($formData) {
        ob_start();
        include __DIR__ . '/email_templates/contact_form_notification.php';
        return ob_get_clean();
    }
}
