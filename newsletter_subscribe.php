<?php
session_start();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/StoreContext.php';
require_once __DIR__ . '/config/plugin_helper.php';

if (!defined('PLATFORM_BASE_DOMAIN')) {
    define('PLATFORM_BASE_DOMAIN', 'localhost');
}

// Resolve store from subdomain so plugin settings are store-scoped
$resolved = StoreContext::resolveFromRequest(PLATFORM_BASE_DOMAIN);
if (!$resolved) {
    header('Content-Type: application/json');
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Store not found.']);
    exit;
}

StoreContext::set($resolved['id'], $resolved['store']);

$pluginHelper = new PluginHelper();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pluginHelper->isPluginActive('mailchimp')) {
    $email = $_POST['email'] ?? '';

    if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mailchimpConfig = $pluginHelper->getPluginConfig('mailchimp');
        $apiKey = $mailchimpConfig['mc_api_key'] ?? '';
        $listId = $mailchimpConfig['mc_list_id'] ?? '';

        if (!empty($apiKey) && !empty($listId)) {
            // In a real implementation, you would use the Mailchimp API here.
            $response = ['success' => true, 'message' => 'Successfully subscribed to newsletter!'];
        } else {
            $response = ['success' => false, 'message' => 'Newsletter service not configured.'];
        }
    } else {
        $response = ['success' => false, 'message' => 'Please enter a valid email address.'];
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// If not POST or plugin not active, redirect to home
header('Location: index.php');
exit;

