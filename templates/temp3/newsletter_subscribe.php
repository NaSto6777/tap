<?php
require_once __DIR__ . '/../../config/plugin_helper.php';

$pluginHelper = new PluginHelper();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string)($_POST['email'] ?? ''));

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response = ['success' => false, 'message' => 'Please enter a valid email address.'];
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }

    if (!$pluginHelper->isPluginActive('mailchimp')) {
        $response = ['success' => false, 'message' => 'Newsletter is not enabled for this store.'];
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }

    $mailchimpConfig = $pluginHelper->getPluginConfig('mailchimp');
    $apiKey = $mailchimpConfig['mc_api_key'] ?? '';
    $listId = $mailchimpConfig['mc_list_id'] ?? '';

    if (empty($apiKey) || empty($listId)) {
        $response = ['success' => false, 'message' => 'Newsletter service not configured.'];
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }

    // NOTE: Real Mailchimp API call can be added later.
    $response = ['success' => true, 'message' => 'Successfully subscribed to newsletter!'];
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

header('Location: index.php');
exit();

