<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Get theme from request
$input = json_decode(file_get_contents('php://input'), true);
$theme = $input['theme'] ?? '';

// Validate theme
if (!in_array($theme, ['light', 'dark'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid theme']);
    exit();
}

// Save theme to session
$_SESSION['admin_theme'] = $theme;

// Return success response
echo json_encode([
    'success' => true, 
    'message' => 'Theme saved successfully',
    'theme' => $theme
]);
?>
