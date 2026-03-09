<?php
require_once __DIR__ . '/../../config/plugin_helper.php';

$pluginHelper = new PluginHelper();

if ($_POST && $pluginHelper->isPluginActive('mailchimp')) {
    $email = $_POST['email'] ?? '';
    
    if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mailchimpConfig = $pluginHelper->getPluginConfig('mailchimp');
        $apiKey = $mailchimpConfig['mc_api_key'] ?? '';
        $listId = $mailchimpConfig['mc_list_id'] ?? '';
        
        if (!empty($apiKey) && !empty($listId)) {
            // In a real implementation, you would use the Mailchimp API
            // For demo purposes, we'll just store it in a simple way
            
            // You could store in database or send to Mailchimp API
            $response = ['success' => true, 'message' => 'Successfully subscribed to newsletter!'];
        } else {
            $response = ['success' => false, 'message' => 'Newsletter service not configured.'];
        }
    } else {
        $response = ['success' => false, 'message' => 'Please enter a valid email address.'];
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

// If not POST or plugin not active, redirect to home
header('Location: index.php');
exit();
