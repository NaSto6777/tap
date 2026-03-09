<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in (store admin)
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$store_id = isset($_SESSION['admin_store_id']) ? (int)$_SESSION['admin_store_id'] : 1;
$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['images'])) {
    $product_id = $_POST['product_id'] ?? null;
    
    if (!$product_id) {
        $response['message'] = 'Product ID is required';
        echo json_encode($response);
        exit;
    }
    
    // Store-scoped upload path
    $upload_dir = "../uploads/stores/$store_id/products/$product_id/";
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $uploaded_files = [];
    $files = $_FILES['images'];
    
    // Handle multiple files
    if (is_array($files['name'])) {
        $file_count = count($files['name']);
        
        for ($i = 0; $i < $file_count; $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_OK) {
                $tmp_name = $files['tmp_name'][$i];
                $name = $files['name'][$i];
                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                
                // Validate file type
                if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                    continue;
                }
                
                // Generate unique filename
                $filename = uniqid() . '.' . $ext;
                $filepath = $upload_dir . $filename;
                
                if (move_uploaded_file($tmp_name, $filepath)) {
                    $uploaded_files[] = $filename;
                }
            }
        }
    } else {
        // Single file upload
        if ($files['error'] === UPLOAD_ERR_OK) {
            $tmp_name = $files['tmp_name'];
            $name = $files['name'];
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                $filename = uniqid() . '.' . $ext;
                $filepath = $upload_dir . $filename;
                
                if (move_uploaded_file($tmp_name, $filepath)) {
                    $uploaded_files[] = $filename;
                }
            }
        }
    }
    
    if (!empty($uploaded_files)) {
        $response['success'] = true;
        $response['message'] = 'Images uploaded successfully';
        $response['files'] = $uploaded_files;
    } else {
        $response['message'] = 'No files were uploaded';
    }
} else {
    $response['message'] = 'Invalid request';
}

echo json_encode($response);
?>
