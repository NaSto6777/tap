<?php
require_once __DIR__ . '/../../config/StoreContext.php';
require_once __DIR__ . '/../../config/settings.php';
require_once __DIR__ . '/../../config/language.php';
require_once __DIR__ . '/../../config/CsrfHelper.php';

$store_id = StoreContext::getId();
$settings = new Settings(null, $store_id);

// Initialize language
Language::init();
$t = function($key, $default = null) {
    return Language::t($key, $default);
};

$database = new Database();
$conn = $database->getConnection();

// Get currency and symbol
$currency = $settings->getSetting('currency', 'USD');
$custom_currency = $settings->getSetting('custom_currency', '');
if ($currency === 'CUSTOM' && !empty($custom_currency)) {
    $currency_symbol = $custom_currency;
} else {
    $currency_symbol = $currency === 'USD' ? '$' : ($currency === 'EUR' ? '€' : ($currency === 'GBP' ? '£' : ($currency === 'TND' ? 'TND' : $currency . ' ')));
}
$currency_position = $settings->getSetting('currency_position', 'left');

// Enable error reporting for debugging
$save_success = false;
$save_error = '';

// Ensure variants table exists
try {
    $conn->exec("CREATE TABLE IF NOT EXISTS product_variants (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        label VARCHAR(255) NOT NULL,
        sku VARCHAR(100) DEFAULT NULL,
        price DECIMAL(10,2) DEFAULT NULL,
        stock_quantity INT DEFAULT 0,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {
    // ignore if no permission; admin may run migrations separately
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Simple "Create New Product" handler (Bootstrap modal)
    if ($action === 'create_basic_product') {
        try {
            if (!$store_id) {
                throw new Exception('Invalid store context.');
            }

            $name           = trim($_POST['name'] ?? '');
            $price          = $_POST['price'] !== '' ? (float)$_POST['price'] : 0;
            $cost_price     = $_POST['cost_price'] !== '' ? (float)$_POST['cost_price'] : null;
            $stock_quantity = $_POST['stock_quantity'] !== '' ? (int)$_POST['stock_quantity'] : 0;
            $category_id    = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
            $description    = trim($_POST['description'] ?? '');
            $is_active      = isset($_POST['is_active']) ? 1 : 0;

            if ($name === '') {
                throw new Exception('Product name is required.');
            }

            // Generate slug from name and ensure uniqueness per store
            $base_slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $name), '-'));
            if ($base_slug === '') {
                $base_slug = 'product-' . time();
            }
            $slug = $base_slug;
            $counter = 1;
            while (true) {
                $check_query = "SELECT id FROM products WHERE store_id = ? AND slug = ?";
                $check_stmt = $conn->prepare($check_query);
                $check_stmt->execute([$store_id, $slug]);
                if (!$check_stmt->fetch()) {
                    break;
                }
                $slug = $base_slug . '-' . $counter;
                $counter++;
            }

            // Auto SKU
            $sku = 'SKU-' . date('YmdHis') . '-' . random_int(100, 999);

            $short_description = mb_substr(strip_tags($description), 0, 160);
            $meta_title        = $name;
            $meta_description  = $short_description;
            $sale_price        = null;
            $featured          = 0;

            $insert = $conn->prepare(
                "INSERT INTO products (
                    store_id, name, sku, slug, price, sale_price, cost_price,
                    stock_quantity, short_description, description,
                    meta_title, meta_description, is_active, featured
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                )"
            );

            $insert->execute([
                $store_id,
                $name,
                $sku,
                $slug,
                $price,
                $sale_price,
                $cost_price,
                $stock_quantity,
                $short_description,
                $description,
                $meta_title,
                $meta_description,
                $is_active,
                $featured,
            ]);

            $product_id = (int)$conn->lastInsertId();

            // Attach single category if provided
            if ($product_id && $category_id > 0) {
                $catStmt = $conn->prepare("INSERT INTO product_categories (store_id, product_id, category_id) VALUES (?, ?, ?)");
                $catStmt->execute([$store_id, $product_id, $category_id]);
            }

            // Handle primary image upload
            if ($product_id && isset($_FILES['product_image']) && is_array($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['product_image']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg','jpeg','png','gif','webp'], true)) {
                    $upload_dir = __DIR__ . "/../../uploads/stores/" . $store_id . "/products/" . $product_id . "/";
                    if (!is_dir($upload_dir)) {
                        @mkdir($upload_dir, 0755, true);
                    }
                    $target = $upload_dir . 'main.' . $ext;
                    @move_uploaded_file($_FILES['product_image']['tmp_name'], $target);

                    // Normalize to main.jpg for compatibility with frontend helpers
                    $main_jpg = $upload_dir . 'main.jpg';
                    if (file_exists($main_jpg)) {
                        @unlink($main_jpg);
                    }
                    @copy($target, $main_jpg);
                }
            }

            header('Location: ?page=products&success=' . urlencode($t('created_successfully')));
            exit;
        } catch (Exception $e) {
            $save_error = $e->getMessage();
        }
    }

    if ($action === 'add' || $action === 'edit') {
        try {
            $product_id = !empty($_POST['product_id']) ? (int)$_POST['product_id'] : null;
            $name = $_POST['name'] ?? '';
            $sku = $_POST['sku'] ?? '';
            $slug = $_POST['slug'] ?? '';
            $price = $_POST['price'] ?? 0;
            $sale_price = !empty($_POST['sale_price']) ? $_POST['sale_price'] : null;
            $cost_price = !empty($_POST['cost_price']) ? $_POST['cost_price'] : null;
            $stock_quantity = $_POST['stock_quantity'] ?? 0;
            $short_description = $_POST['short_description'] ?? '';
            $description = $_POST['description'] ?? '';
            // Auto SEO generation
            $auto_seo_enabled = $settings->getSetting('auto_seo', '0') === '1';
            if ($auto_seo_enabled) {
                // Generate SEO from product name and short description
                $meta_title = $name;
                $meta_description = !empty($short_description) ? $short_description : substr(strip_tags($description), 0, 160);
            } else {
                // Use manual SEO fields
            $meta_title = !empty($_POST['meta_title']) ? $_POST['meta_title'] : $name;
            $meta_description = !empty($_POST['meta_description']) ? $_POST['meta_description'] : $short_description;
            }
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            $featured = isset($_POST['featured']) ? 1 : 0;
            
            // Validation
            if (empty($name) || empty($sku) || empty($price)) {
                throw new Exception('Please fill in all required fields (Name, SKU, Price)');
            }
            
            // Make slug unique if duplicate exists
            $original_slug = $slug;
            $counter = 1;
            while (true) {
                $check_query = "SELECT id FROM products WHERE store_id = ? AND slug = ? AND id != ?";
                $check_stmt = $conn->prepare($check_query);
                $check_stmt->execute([$store_id, $slug, $product_id ?? 0]);
                if (!$check_stmt->fetch()) {
                    break; // Slug is unique
                }
                $slug = $original_slug . '-' . $counter;
                $counter++;
            }
            
            if ($action === 'add') {
                $query = "INSERT INTO products (store_id, name, sku, slug, price, sale_price, cost_price, stock_quantity, 
                          short_description, description, meta_title, meta_description, is_active, featured) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($query);
                $stmt->execute([$store_id, $name, $sku, $slug, $price, $sale_price, $cost_price, $stock_quantity, 
                              $short_description, $description, $meta_title, $meta_description, $is_active, $featured]);
                $product_id = $conn->lastInsertId();
                $save_success = true;
            } else {
                if (empty($product_id)) {
                    throw new Exception('Product ID is required for update');
                }
                $query = "UPDATE products SET name=?, sku=?, slug=?, price=?, sale_price=?, cost_price=?, 
                          stock_quantity=?, short_description=?, description=?, meta_title=?, meta_description=?, 
                          is_active=?, featured=? WHERE id=? AND store_id=?";
                $stmt = $conn->prepare($query);
                $stmt->execute([$name, $sku, $slug, $price, $sale_price, $cost_price, $stock_quantity, 
                              $short_description, $description, $meta_title, $meta_description, $is_active, $featured, $product_id, $store_id]);
                $save_success = true;
            }
        } catch (Exception $e) {
            $save_error = $e->getMessage();
        }

        // Save uploaded images (drag & drop order + main image)
        $upload_dir = __DIR__ . "/../../uploads/stores/" . $store_id . "/products/" . $product_id . "/";
        if (!is_dir($upload_dir)) { @mkdir($upload_dir, 0755, true); }
        if (!empty($_FILES['product_images']) && is_array($_FILES['product_images']['name'])) {
            $files = $_FILES['product_images'];
            $count = count($files['name']);
            $order = isset($_POST['images_order']) && $_POST['images_order'] !== '' ? array_map('intval', explode(',', $_POST['images_order'])) : range(0, $count - 1);
            $main_index = isset($_POST['main_image_index']) ? (int)$_POST['main_image_index'] : 0;
            $saved = [];
            foreach ($order as $pos => $i) {
                if (!isset($files['error'][$i]) || $files['error'][$i] !== UPLOAD_ERR_OK) { continue; }
                $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg','jpeg','png','gif','webp'])) { continue; }
                $target = $upload_dir . uniqid('', true) . '.' . $ext;
                @move_uploaded_file($files['tmp_name'][$i], $target);
                $saved[] = $target;
            }
            if (!empty($saved)) {
                $main_file = $saved[min(max($main_index, 0), count($saved)-1)];
                $main_path = $upload_dir . 'main.jpg';
                if (file_exists($main_path)) { @unlink($main_path); }
                @copy($main_file, $main_path);
            }
        }
        
        // Handle categories (only if product was saved successfully)
        if ($save_success && $product_id && isset($_POST['categories']) && is_array($_POST['categories'])) {
            try {
                // Delete existing categories
                $query = "DELETE FROM product_categories WHERE product_id = ?";
                $stmt = $conn->prepare($query);
                $stmt->execute([$product_id]);
                
                // Insert new categories
                foreach ($_POST['categories'] as $category_id) {
                    $category_id = (int)$category_id;
                    if ($category_id > 0) {
                        $query = "INSERT INTO product_categories (store_id, product_id, category_id) VALUES (?, ?, ?)";
                        $stmt = $conn->prepare($query);
                        $stmt->execute([$store_id, $product_id, $category_id]);
                    }
                }
            } catch (Exception $e) {
                $save_error .= ' Categories: ' . $e->getMessage();
            }
        }

        // Handle variants
        if ($save_success && $product_id) {
            try {
                // Delete existing variants
                $stmt = $conn->prepare("DELETE FROM product_variants WHERE product_id = ?");
                $stmt->execute([$product_id]);

                // Insert variants from form arrays
                $labels = $_POST['variant_label'] ?? [];
                $skus = $_POST['variant_sku'] ?? [];
                $prices = $_POST['variant_price'] ?? [];
                $stocks = $_POST['variant_stock'] ?? [];
                if (is_array($labels)) {
                    $insert = $conn->prepare("INSERT INTO product_variants (store_id, product_id, label, sku, price, stock_quantity, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)");
                    $count = count($labels);
                    for ($i = 0; $i < $count; $i++) {
                        $label = trim((string)($labels[$i] ?? ''));
                        if ($label === '') { continue; }
                        $sku = trim((string)($skus[$i] ?? '')) ?: null;
                        $price = ($prices[$i] ?? '') !== '' ? (float)$prices[$i] : null;
                        $stock = (int)($stocks[$i] ?? 0);
                        $insert->execute([$store_id, $product_id, $label, $sku, $price, $stock]);
                    }
                }
            } catch (Exception $e) {
                $save_error .= ' Variants: ' . $e->getMessage();
            }
        }
        
        // Redirect after successful save (from shell modal: close overlay and refresh main frame)
        if ($save_success && empty($save_error)) {
            $msg = $action === 'add' ? $t('created_successfully') : $t('updated_successfully');
            if (!empty($_POST['from_shell_modal'])) {
                header('Location: index.php?content=1&modal_close=1&success=' . urlencode($msg));
            } else {
                header('Location: ?page=products&success=' . urlencode($msg));
            }
            exit;
        }
    }
    if ($action === 'delete') {
        $product_id = $_POST['product_id'];
        $query = "DELETE FROM products WHERE id = ? AND store_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->execute([$product_id, $store_id]);
    }
    
    if ($action === 'toggle_status') {
        header('Content-Type: application/json');
        $product_id = $_POST['product_id'];
        $is_active = $_POST['is_active'];
        $query = "UPDATE products SET is_active = ? WHERE id = ? AND store_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->execute([$is_active, $product_id, $store_id]);
        echo json_encode(['success' => true]);
        exit;
    }
    
    if ($action === 'quick_update_product') {
        header('Content-Type: application/json');
        try {
            $product_id = $_POST['product_id'] ?? 0;
            $field = $_POST['field'] ?? '';
            $value = $_POST['value'] ?? '';
            
            if (!$product_id || !$field) {
                echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
                exit;
            }
            
            // Validate field name to prevent SQL injection
            $allowed_fields = ['name', 'sku', 'price', 'sale_price', 'cost_price', 'stock_quantity', 'short_description', 'is_active', 'featured'];
            if (!in_array($field, $allowed_fields)) {
                echo json_encode(['success' => false, 'message' => 'Invalid field name']);
                exit;
            }
            
            // Prepare value based on field type
            if ($field === 'price' || $field === 'sale_price' || $field === 'cost_price') {
                $value = $value !== '' ? (float)$value : null;
            } elseif ($field === 'stock_quantity') {
                $value = (int)$value;
            } elseif ($field === 'is_active' || $field === 'featured') {
                $value = (int)$value;
            } else {
                $value = trim($value);
            }
            
            // Build update query
            if ($value === null && ($field === 'sale_price' || $field === 'cost_price')) {
                $query = "UPDATE products SET {$field} = NULL WHERE id = ?";
                $stmt = $conn->prepare($query);
                $stmt->execute([$product_id]);
            } else {
                $query = "UPDATE products SET {$field} = ? WHERE id = ?";
                $stmt = $conn->prepare($query);
                $stmt->execute([$value, $product_id]);
            }
            
            echo json_encode(['success' => true, 'message' => 'Field updated successfully']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error updating field: ' . $e->getMessage()]);
        }
        exit;
    }
    
    if ($action === 'get_product') {
        // Ensure no prior output breaks JSON
        while (function_exists('ob_get_level') && ob_get_level() > 0) { @ob_end_clean(); }
        header('Content-Type: application/json');
        try {
            $product_id = $_POST['product_id'] ?? $_GET['product_id'] ?? 0;
            
            // Get product data (scoped to store)
            $query = "SELECT * FROM products WHERE id = ? AND store_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->execute([$product_id, $store_id]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($product) {
                // Get product categories
                $query = "SELECT category_id FROM product_categories WHERE product_id = ?";
                $stmt = $conn->prepare($query);
                $stmt->execute([$product_id]);
                $product_categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
                $product['categories'] = $product_categories ?: [];

                // Get product variants
                try {
                    $query = "SELECT id, label, sku, price, stock_quantity, is_active FROM product_variants WHERE product_id = ? ORDER BY id";
                    $stmt = $conn->prepare($query);
                    $stmt->execute([$product_id]);
                    $product['variants'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                } catch (Exception $e) {
                    $product['variants'] = [];
                }
                
                // Get product images
                $upload_dir = __DIR__ . "/../../uploads/products/" . $product_id . "/";
                $images = [];
                if (is_dir($upload_dir)) {
                    $files = glob($upload_dir . "*.*");
                    foreach ($files as $file) {
                        if (basename($file) !== 'main.jpg') {
                            $images[] = str_replace(__DIR__ . "/../../", "../", $file);
                        }
                    }
                }
                
                // Check for main image
                $main_image = "../uploads/products/{$product_id}/main.jpg";
                if (file_exists(__DIR__ . "/../../" . str_replace("../", "", $main_image))) {
                    $product['main_image'] = $main_image;
                }
                $product['images'] = $images;
                
                echo json_encode(['success' => true, 'product' => $product]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Product not found']);
            }
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'message' => 'Exception', 'error' => $e->getMessage()]);
        }
        exit;
    }
}

// Get filters
$search = $_GET['search'] ?? '';
$category_filter = $_GET['category'] ?? '';
$status_filter = $_GET['status'] ?? '';
$sort_by = $_GET['sort'] ?? 'created_at';
$sort_order = $_GET['order'] ?? 'DESC';
$page = $_GET['p'] ?? 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Build query (scoped to store)
$where = ["p.store_id = ?"];
$params = [$store_id];

if ($search) {
    $where[] = "(p.name LIKE ? OR p.sku LIKE ? OR p.description LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if ($status_filter === 'active') {
    $where[] = "p.is_active = 1";
} elseif ($status_filter === 'inactive') {
    $where[] = "p.is_active = 0";
}

if ($category_filter) {
    $where[] = "pc.category_id = ?";
    $params[] = $category_filter;
}

$where_clause = implode(' AND ', $where);

// Get total products
$query = "SELECT COUNT(DISTINCT p.id) as total FROM products p
          LEFT JOIN product_categories pc ON p.id = pc.product_id
          WHERE $where_clause";
$stmt = $conn->prepare($query);
$stmt->execute($params);
$total_products = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_products / $per_page);

// Get products
$query = "SELECT p.*, 
          GROUP_CONCAT(DISTINCT c.name SEPARATOR ', ') as categories
          FROM products p
          LEFT JOIN product_categories pc ON p.id = pc.product_id
          LEFT JOIN categories c ON pc.category_id = c.id
          WHERE $where_clause
          GROUP BY p.id
          ORDER BY p.$sort_by $sort_order
          LIMIT $per_page OFFSET $offset";
$stmt = $conn->prepare($query);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all categories only if enabled in settings (scoped to store)
$settingsInstance = new Settings(null, $store_id);
$categories_enabled = $settingsInstance->getSetting('categories_enabled', '1');
$categories = [];
if ($categories_enabled === '1') {
$query = "SELECT * FROM categories WHERE store_id = ? ORDER BY name";
$stmt = $conn->prepare($query);
$stmt->execute([$store_id]);
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get statistics (scoped to store)
$stats_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
    SUM(CASE WHEN stock_quantity = 0 THEN 1 ELSE 0 END) as out_of_stock,
    SUM(CASE WHEN stock_quantity <= 10 AND stock_quantity > 0 THEN 1 ELSE 0 END) as low_stock
    FROM products WHERE store_id = ?";
$stmt = $conn->prepare($stats_query);
$stmt->execute([$store_id]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);
$stats = is_array($stats) ? array_merge(['total' => 0, 'active' => 0, 'low_stock' => 0, 'out_of_stock' => 0], $stats) : ['total' => 0, 'active' => 0, 'low_stock' => 0, 'out_of_stock' => 0];
$stats['total'] = (int)($stats['total'] ?? 0);
$stats['active'] = (int)($stats['active'] ?? 0);
$stats['low_stock'] = (int)($stats['low_stock'] ?? 0);
$stats['out_of_stock'] = (int)($stats['out_of_stock'] ?? 0);
?>

<?php
$products_modal_only = isset($products_modal_only) && $products_modal_only;
$modal_open = $products_modal_only;
if (!$products_modal_only):
?>
<!-- Modern Product Management Interface -->
<div class="product-management-container">
    
    <!-- Success/Error Messages -->
    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            <?php echo htmlspecialchars($_GET['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($save_error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i>
            <strong><?php echo $t('error'); ?>:</strong> <?php echo htmlspecialchars($save_error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <!-- Statistics Cards (Argon-style) -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-card-body">
                <div class="stat-card-row">
                    <div class="stat-card-main">
                        <h5 class="stat-card-label"><?php echo $t('total_products'); ?></h5>
                        <span class="stat-card-value"><?php echo (int)($stats['total'] ?? 0); ?></span>
                        <p class="stat-card-footer positive"><i class="fas fa-arrow-up"></i> <span><?php echo $t('all_products'); ?></span></p>
                    </div>
                    <div class="stat-card-icon-wrap">
                        <div class="stat-card-icon primary"><i class="fas fa-boxes"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-card-body">
                <div class="stat-card-row">
                    <div class="stat-card-main">
                        <h5 class="stat-card-label"><?php echo $t('active_products'); ?></h5>
                        <span class="stat-card-value"><?php echo (int)($stats['active'] ?? 0); ?></span>
                        <p class="stat-card-footer positive"><i class="fas fa-arrow-up"></i> <span><?php echo $stats['total'] > 0 ? round(($stats['active'] / $stats['total']) * 100, 1) : 0; ?>%</span></p>
                    </div>
                    <div class="stat-card-icon-wrap">
                        <div class="stat-card-icon success"><i class="fas fa-check-circle"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-card-body">
                <div class="stat-card-row">
                    <div class="stat-card-main">
                        <h5 class="stat-card-label"><?php echo $t('low_stock'); ?></h5>
                        <span class="stat-card-value"><?php echo (int)($stats['low_stock'] ?? 0); ?></span>
                        <p class="stat-card-footer <?php echo $stats['low_stock'] > 0 ? 'negative' : 'positive'; ?>"><i class="fas fa-<?php echo $stats['low_stock'] > 0 ? 'exclamation' : 'check'; ?>"></i> <span><?php echo $stats['low_stock'] > 0 ? $t('needs_attention') : $t('all_good'); ?></span></p>
                    </div>
                    <div class="stat-card-icon-wrap">
                        <div class="stat-card-icon warning"><i class="fas fa-exclamation-triangle"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-card-body">
                <div class="stat-card-row">
                    <div class="stat-card-main">
                        <h5 class="stat-card-label"><?php echo $t('out_of_stock'); ?></h5>
                        <span class="stat-card-value"><?php echo (int)($stats['out_of_stock'] ?? 0); ?></span>
                        <p class="stat-card-footer <?php echo $stats['out_of_stock'] > 0 ? 'negative' : 'positive'; ?>"><i class="fas fa-<?php echo $stats['out_of_stock'] > 0 ? 'times' : 'check'; ?>"></i> <span><?php echo $stats['out_of_stock'] > 0 ? $t('restock_needed') : $t('in_stock_status'); ?></span></p>
                    </div>
                    <div class="stat-card-icon-wrap">
                        <div class="stat-card-icon danger"><i class="fas fa-times-circle"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters and Search Bar -->
    <div class="filters-section">
        <form method="GET" class="filters-form">
            <input type="hidden" name="page" value="products">
            
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" name="search" placeholder="<?php echo $t('search_products_placeholder'); ?>" 
                       value="<?php echo htmlspecialchars($search); ?>">
            </div>
            
            <div class="filter-group">
                <select name="category" class="filter-select" onchange="this.form.submit()">
                    <option value=""><?php echo $t('all_categories'); ?></option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>" <?php echo $category_filter == $cat['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <select name="status" class="filter-select" onchange="this.form.submit()">
                    <option value=""><?php echo $t('all_status'); ?></option>
                    <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>><?php echo $t('active'); ?></option>
                    <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>><?php echo $t('inactive'); ?></option>
                </select>
            </div>
            
            <div class="filter-group">
                <select name="sort" class="filter-select" onchange="this.form.submit()">
                    <option value="created_at" <?php echo $sort_by === 'created_at' ? 'selected' : ''; ?>><?php echo $t('newest_first'); ?></option>
                    <option value="name" <?php echo $sort_by === 'name' ? 'selected' : ''; ?>><?php echo $t('name_az'); ?></option>
                    <option value="price" <?php echo $sort_by === 'price' ? 'selected' : ''; ?>><?php echo $t('price_sort'); ?></option>
                    <option value="stock_quantity" <?php echo $sort_by === 'stock_quantity' ? 'selected' : ''; ?>><?php echo $t('stock_sort'); ?></option>
                </select>
            </div>
            
            <button type="submit" class="btn-filter">
                <i class="fas fa-filter"></i>
                <?php echo $t('apply'); ?>
            </button>
            
            <?php if ($search || $category_filter || $status_filter): ?>
                <a href="?page=products" class="btn-clear">
                    <i class="fas fa-times"></i>
                    <?php echo $t('clear'); ?>
                </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Products Grid/Table -->
    <div class="products-container">
        <div class="products-header">
            <div class="results-info">
                <?php echo $t('showing_products'); ?> <?php echo count($products); ?> <?php echo $t('of_products'); ?> <?php echo $total_products; ?> <?php echo $t('products'); ?>
            </div>
            <div class="d-flex align-items-center gap-2">
                <button type="button"
                        class="btn btn-primary"
                        onclick="openProductModal()">
                    <i class="fas fa-plus me-1"></i> <?php echo $t('add_new_product'); ?>
                </button>
                <div class="view-toggle">
                    <button class="view-btn active" data-view="grid" onclick="switchView('grid')">
                        <i class="fas fa-th-large"></i>
                    </button>
                    <button class="view-btn" data-view="list" onclick="switchView('list')">
                        <i class="fas fa-list"></i>
                    </button>
                </div>
            </div>
        </div>

        <div class="products-grid" id="productsGrid">
            <?php foreach ($products as $product): 
                $stock_status = $product['stock_quantity'] == 0 ? 'out' : ($product['stock_quantity'] <= 10 ? 'low' : 'good');
                $discount = $product['sale_price'] ? round((($product['price'] - $product['sale_price']) / $product['price']) * 100) : 0;
            ?>
                <div class="product-card" data-id="<?php echo $product['id']; ?>">
                    <div class="product-image">
                        <?php
                        $image_path = "../uploads/products/{$product['id']}/main.jpg";
                        $fallback_path = "../uploads/placeholder.jpg";
                        $display_path = file_exists($image_path) ? $image_path : $fallback_path;
                        ?>
                        <img src="<?php echo $display_path; ?>" 
                             onerror="this.src='../uploads/placeholder.jpg'" 
                             alt="<?php echo htmlspecialchars($product['name']); ?>">
                        
                        <?php if ($discount > 0): ?>
                            <div class="discount-badge">-<?php echo $discount; ?>%</div>
                        <?php endif; ?>
                        
                        <?php if ($product['featured']): ?>
                            <div class="featured-badge">
                                <i class="fas fa-star"></i>
                            </div>
                        <?php endif; ?>
                        
                        <div class="product-overlay">
                            <button class="overlay-btn" onclick="quickView(<?php echo $product['id']; ?>)" title="<?php echo $t('quick_view'); ?>">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="overlay-btn" onclick="editProduct(<?php echo $product['id']; ?>)" title="<?php echo $t('edit'); ?>">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="overlay-btn delete" onclick="deleteProduct(<?php echo $product['id']; ?>)" title="<?php echo $t('delete'); ?>">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="product-details">
                        <div class="product-header">
                            <h3 class="product-name" title="<?php echo htmlspecialchars($product['name']); ?>">
                                <?php echo htmlspecialchars($product['name']); ?>
                            </h3>
                            <div class="product-status-toggle">
                                <label class="switch">
                                    <input type="checkbox" 
                                           <?php echo $product['is_active'] ? 'checked' : ''; ?>
                                           onchange="toggleProductStatus(<?php echo $product['id']; ?>, this.checked)">
                                    <span class="slider"></span>
                                </label>
                            </div>
                        </div>
                        
                        <div class="product-sku">
                            <i class="fas fa-barcode"></i>
                            <?php echo htmlspecialchars($product['sku']); ?>
                        </div>
                        
                        <div class="product-price">
                            <?php if ($product['sale_price']): ?>
                                <span class="original-price"><?php echo $currency_position === 'left' ? $currency_symbol . number_format($product['price'], 2) : number_format($product['price'], 2) . ' ' . $currency_symbol; ?></span>
                                <span class="sale-price"><?php echo $currency_position === 'left' ? $currency_symbol . number_format($product['sale_price'], 2) : number_format($product['sale_price'], 2) . ' ' . $currency_symbol; ?></span>
                            <?php else: ?>
                                <span class="current-price"><?php echo $currency_position === 'left' ? $currency_symbol . number_format($product['price'], 2) : number_format($product['price'], 2) . ' ' . $currency_symbol; ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="product-meta">
                            <div class="stock-indicator stock-<?php echo $stock_status; ?>">
                                <i class="fas fa-circle"></i>
                                <?php echo $product['stock_quantity']; ?> <?php echo $t('in_stock'); ?>
                            </div>
                            
                            <?php if ($product['categories']): ?>
                                <div class="product-categories">
                                    <i class="fas fa-tag"></i>
                                    <?php echo htmlspecialchars($product['categories']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=products&p=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category_filter; ?>&status=<?php echo $status_filter; ?>" 
                       class="page-btn">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                <?php endif; ?>
                
                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <a href="?page=products&p=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category_filter; ?>&status=<?php echo $status_filter; ?>" 
                       class="page-btn <?php echo $i == $page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=products&p=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category_filter; ?>&status=<?php echo $status_filter; ?>" 
                       class="page-btn">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Product Modal (Add & Edit) -->
<div class="modal-overlay<?php echo $modal_open ? ' active' : ''; ?>" id="productModal">
    <div class="modal-container">
        <div class="modal-header">
            <h2><i class="fas fa-box"></i> <span id="modalTitle"><?php echo $t('add_new_product'); ?></span></h2>
            <button class="modal-close" onclick="closeProductModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form method="POST" id="productForm" enctype="multipart/form-data">
            <?php echo CsrfHelper::getTokenField(); ?>
            <?php if ($modal_open): ?><input type="hidden" name="from_shell_modal" value="1"><?php endif; ?>
            <div class="modal-body">
                <input type="hidden" name="action" value="add" id="formAction">
                <input type="hidden" name="product_id" id="productId">
                <input type="hidden" name="images_order" id="images_order">
                <input type="hidden" name="main_image_index" id="main_image_index" value="0">
                
                <!-- Tab Navigation -->
                <div class="tabs-nav">
                    <button type="button" class="tab-btn active" data-tab="basic">
                        <i class="fas fa-info-circle"></i> <?php echo $t('basic_info'); ?>
                    </button>
                    <button type="button" class="tab-btn" data-tab="pricing">
                        <i class="fas fa-dollar-sign"></i> <?php echo $t('pricing'); ?>
                    </button>
                    <button type="button" class="tab-btn" data-tab="variants">
                        <i class="fas fa-sitemap"></i> <?php echo $t('variants'); ?>
                    </button>
                    <button type="button" class="tab-btn" data-tab="images">
                        <i class="fas fa-images"></i> <?php echo $t('images'); ?>
                    </button>
                    <button type="button" class="tab-btn" data-tab="description">
                        <i class="fas fa-align-left"></i> <?php echo $t('description'); ?>
                    </button>
                    <?php if ($settings->getSetting('auto_seo', '0') !== '1'): ?>
                    <button type="button" class="tab-btn" data-tab="seo">
                        <i class="fas fa-search"></i> <?php echo $t('seo'); ?>
                    </button>
                    <?php endif; ?>
                </div>
                
                <!-- Tab Content -->
                <div class="tabs-content">
                    <!-- Basic Info Tab -->
                    <div class="tab-pane active" id="tab-basic">
                        <div class="form-row">
                            <div class="form-group col-8">
                                <label><?php echo $t('product_name'); ?> *</label>
                                <input type="text" name="name" id="name" class="form-input" required>
                            </div>
                            <div class="form-group col-4">
                                <label>
                                    <?php echo $t('sku'); ?> *
                                    <span class="badge-generate" onclick="generateSKU()">
                                        <i class="fas fa-magic"></i> <?php echo $t('create'); ?>
                                    </span>
                                </label>
                                <input type="text" name="sku" id="sku" class="form-input" required readonly>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label><?php echo $t('slug'); ?></label>
                            <input type="text" name="slug" id="slug" class="form-input" readonly>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group col-6">
                                <label><?php echo $t('stock_quantity'); ?></label>
                                <input type="number" name="stock_quantity" id="stock_quantity" class="form-input" value="0">
                            </div>
                            <?php if ($categories_enabled === '1'): ?>
                            <div class="form-group col-6">
                                <label><?php echo $t('categories_tab'); ?></label>
                                <select name="categories[]" id="categories" class="form-input" multiple>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group col-6">
                                <div class="checkbox-group">
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="is_active" id="is_active" checked>
                                        <span><?php echo $t('is_active'); ?></span>
                                    </label>
                                </div>
                            </div>
                            <div class="form-group col-6">
                                <div class="checkbox-group">
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="featured" id="featured">
                                        <span><?php echo $t('featured'); ?></span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Pricing Tab -->
                    <div class="tab-pane" id="tab-pricing">
                        <div class="pricing-calculator">
                            <div class="form-row">
                                <div class="form-group col-4">
                                    <label><?php echo $t('regular_price'); ?> *</label>
                                    <div class="input-icon">
                                        <i class="fas fa-dollar-sign"></i>
                                        <input type="number" name="price" id="price" class="form-input" step="0.01" required>
                                    </div>
                                </div>
                                <div class="form-group col-4">
                                    <label><?php echo $t('sale_price'); ?></label>
                                    <div class="input-icon">
                                        <i class="fas fa-tag"></i>
                                        <input type="number" name="sale_price" id="sale_price" class="form-input" step="0.01">
                                    </div>
                                </div>
                                <div class="form-group col-4">
                                    <label><?php echo $t('cost_price'); ?></label>
                                    <div class="input-icon">
                                        <i class="fas fa-coins"></i>
                                        <input type="number" name="cost_price" id="cost_price" class="form-input" step="0.01">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="profit-calculator" id="profitCalculator">
                                <!-- Auto-calculated profit info will appear here -->
                            </div>
                        </div>
                    </div>

                    <!-- Variants Tab -->
                    <div class="tab-pane" id="tab-variants">
                        <div class="variants-header">
                            <div>
                                <h4><i class="fas fa-sitemap"></i> Product Variants</h4>
                                <small>Add variations like Size or Color with their own stock and price.</small>
                            </div>
                            <button type="button" class="btn-secondary" onclick="addVariantRow()">
                                <i class="fas fa-plus"></i> Add Variant
                            </button>
                        </div>
                        <div id="variantsContainer" class="variants-container">
                            <!-- Variant rows will be added here -->
                        </div>
                        <div class="variants-hint">
                            <small>
                                Example labels: "XL", "Red", or "Red / XL". Leave price empty to use base price.
                            </small>
                        </div>
                    </div>
                    
                    <!-- Images Tab -->
                    <div class="tab-pane" id="tab-images">
                        <div class="upload-area" id="uploadZone">
                            <i class="fas fa-cloud-upload-alt fa-3x"></i>
                            <h3>Drag & Drop Images Here</h3>
                            <p>or click to browse files</p>
                            <input type="file" id="productImages" name="product_images[]" accept="image/*" multiple style="display: none;">
                            <button type="button" class="btn-secondary" onclick="document.getElementById('productImages').click()">
                                <i class="fas fa-folder-open"></i> Choose Files
                            </button>
                        </div>
                        
                        <div id="imagePreviewContainer" class="image-previews-container">
                            <!-- Images will be added here -->
                        </div>
                    </div>
                    
                    <!-- Description Tab -->
                    <div class="tab-pane" id="tab-description">
                        <div class="form-group">
                            <label><?php echo $t('short_description'); ?></label>
                            <textarea name="short_description" id="short_description" class="form-textarea" rows="3"></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label><?php echo $t('full_description'); ?></label>
                            <textarea name="description" id="description" class="form-textarea" rows="8"></textarea>
                        </div>
                    </div>
                    
                    <!-- SEO Tab -->
                    <?php if ($settings->getSetting('auto_seo', '0') !== '1'): ?>
                    <div class="tab-pane" id="tab-seo">
                        <div class="form-group">
                            <label><?php echo $t('meta_title'); ?></label>
                            <input type="text" name="meta_title" id="meta_title" class="form-input">
                        </div>
                        
                        <div class="form-group">
                            <label><?php echo $t('meta_description'); ?></label>
                            <textarea name="meta_description" id="meta_description" class="form-textarea" rows="3"></textarea>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeProductModal()">
                    <i class="fas fa-times"></i> <?php echo $t('cancel'); ?>
                </button>
                <button type="submit" class="btn-primary" id="productPrimaryAction">
                    <i class="fas fa-save"></i> <?php echo $t('save'); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Pass translations to JavaScript
window.translations = {
    save: <?php echo json_encode($t('save')); ?>,
    cancel: <?php echo json_encode($t('cancel')); ?>,
    edit_product: <?php echo json_encode($t('edit_product')); ?>,
    add_new_product: <?php echo json_encode($t('add_new_product')); ?>,
    are_you_sure: <?php echo json_encode($t('are_you_sure')); ?>,
    delete_product: <?php echo json_encode($t('delete_product')); ?>
};

// Tab switching
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const tab = this.dataset.tab;
        
        // Update active tab button
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        
        // Update active tab content
        document.querySelectorAll('.tab-pane').forEach(pane => pane.classList.remove('active'));
        document.getElementById('tab-' + tab).classList.add('active');

        // Update primary action depending on tab and mode
        updateProductPrimaryAction();
    });
});

// Check for product ID from quick view
document.addEventListener('DOMContentLoaded', function() {
    const editProductId = sessionStorage.getItem('editProductId');
    if (editProductId) {
        sessionStorage.removeItem('editProductId');
        setTimeout(() => {
            editProduct(parseInt(editProductId));
        }, 100);
    }
    // When loaded in shell modal iframe: init modal (add or edit by product_id in URL)
    if (isInShellModalFrame()) {
        var m = window.location.search.match(/product_id=(\d+)/);
        openProductModal(m ? parseInt(m[1], 10) : null);
    }
});

// Detect if this page is loaded in the shell's full-window modal iframe (outside main content iframe)
function isInShellModalFrame() {
    return window.parent !== window && window.location.search.indexOf('modal=1') !== -1;
}
function isInMainContentFrame() {
    return window.parent !== window && window.location.search.indexOf('modal=1') === -1;
}

// Modal scroll lock (for iframe: keep modal fixed to viewport, prevent background scroll)
var _modalScrollY = 0;
function _lockModalScroll() {
    _modalScrollY = window.scrollY || document.documentElement.scrollTop;
    document.documentElement.classList.add('modal-open');
    document.body.classList.add('modal-open');
    document.body.style.position = 'fixed';
    document.body.style.top = '-' + _modalScrollY + 'px';
    document.body.style.left = '0';
    document.body.style.right = '0';
    document.body.style.overflow = 'hidden';
}
function _unlockModalScroll() {
    document.documentElement.classList.remove('modal-open');
    document.body.classList.remove('modal-open');
    document.body.style.position = '';
    document.body.style.top = '';
    document.body.style.left = '';
    document.body.style.right = '';
    document.body.style.overflow = '';
    window.scrollTo(0, _modalScrollY);
}

// Modal functions
function openProductModal(productId = null) {
    if (isInMainContentFrame()) {
        window.parent.postMessage({ type: 'open_product_modal', productId: productId != null ? productId : null }, '*');
        return;
    }
    _lockModalScroll();
    document.getElementById('productModal').classList.add('active');
    
    if (!productId) {
        document.getElementById('productForm').reset();
        document.getElementById('modalTitle').textContent = window.translations.add_new_product;
        document.getElementById('formAction').value = 'add';
        productImages = [];
        renderImagePreviews();
        generateSKU();
        // Reset to first tab
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelector('.tab-btn[data-tab="basic"]').classList.add('active');
        document.querySelectorAll('.tab-pane').forEach(pane => pane.classList.remove('active'));
        document.getElementById('tab-basic').classList.add('active');
        // Set primary action to Next
        const primaryBtn = document.getElementById('productPrimaryAction');
        if (primaryBtn) {
            primaryBtn.type = 'button';
            primaryBtn.innerHTML = '<i class="fas fa-arrow-right"></i> Next';
            primaryBtn.onclick = goToNextProductTab;
        }
        // Clear variants
        clearVariantRows();
    } else {
        document.getElementById('modalTitle').textContent = window.translations.edit_product;
        document.getElementById('formAction').value = 'edit';
        document.getElementById('productId').value = productId;
        // In edit mode, keep Save visible from the start
        const primaryBtn = document.getElementById('productPrimaryAction');
        if (primaryBtn) {
            primaryBtn.type = 'submit';
            primaryBtn.innerHTML = '<i class="fas fa-save"></i> ' + window.translations.save;
            primaryBtn.onclick = null;
        }
        
        // Load product data via AJAX (use content=1 when in iframe so response is JSON, not full shell)
        var fetchUrl = (window.parent !== window) ? (window.location.pathname + '?content=1&page=products') : '?page=products';
        var csrfMeta = document.querySelector('meta[name="csrf-token"]');
        var csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';
        var body = 'action=get_product&product_id=' + encodeURIComponent(productId);
        if (csrfToken) body += '&csrf_token=' + encodeURIComponent(csrfToken);
        fetch(fetchUrl, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: body
        })
        .then(async (response) => {
            const text = await response.text();
            try { return JSON.parse(text); } catch (e) {
                console.error('Non-JSON response from server:', text);
                throw new Error('Invalid JSON');
            }
        })
        .then(data => {
            if (data.success && data.product) {
                const prod = data.product;
                
                // Populate basic info
                document.getElementById('name').value = prod.name || '';
                document.getElementById('sku').value = prod.sku || '';
                document.getElementById('slug').value = prod.slug || '';
                document.getElementById('stock_quantity').value = prod.stock_quantity || 0;
                document.getElementById('is_active').checked = prod.is_active == 1;
                document.getElementById('featured').checked = prod.featured == 1;
                
                // Populate pricing
                document.getElementById('price').value = prod.price || '';
                document.getElementById('sale_price').value = prod.sale_price || '';
                document.getElementById('cost_price').value = prod.cost_price || '';
                calculateProfit();
                
                // Populate description
                document.getElementById('short_description').value = prod.short_description || '';
                document.getElementById('description').value = prod.description || '';
                
                // Populate SEO (only if auto SEO is disabled)
                if (!autoSeoEnabled) {
                    const metaTitleField = document.getElementById('meta_title');
                    const metaDescField = document.getElementById('meta_description');
                    if (metaTitleField) metaTitleField.value = prod.meta_title || '';
                    if (metaDescField) metaDescField.value = prod.meta_description || '';
                }
                
                // Populate categories (only if categories feature and field exist)
                const categoriesSelect = document.getElementById('categories');
                if (categoriesSelect && prod.categories && prod.categories.length > 0) {
                    Array.from(categoriesSelect.options).forEach(option => {
                        option.selected = prod.categories.includes(parseInt(option.value));
                    });
                }

                // Populate variants
                clearVariantRows();
                if (prod.variants && prod.variants.length > 0) {
                    prod.variants.forEach(v => addVariantRow({
                        label: v.label || '',
                        sku: v.sku || '',
                        price: v.price !== null ? v.price : '',
                        stock: v.stock_quantity || 0
                    }));
                }
                
                // Show existing images
                productImages = [];
                const container = document.getElementById('imagePreviewContainer');
                if (prod.main_image || (prod.images && prod.images.length > 0)) {
                    let imagesHTML = `
                        <div class="images-header">
                            <h4><i class="fas fa-images"></i> Current Product Images</h4>
                            <small>Upload new images to replace existing ones</small>
                        </div>
                        <div class="images-grid">
                    `;
                    
                    if (prod.main_image) {
                        imagesHTML += `
                            <div class="image-item main">
                                <img src="${prod.main_image}?t=${Date.now()}" alt="Main Image">
                                <span class="main-badge">Main</span>
                            </div>
                        `;
                    }
                    
                    if (prod.images && prod.images.length > 0) {
                        prod.images.forEach((img, index) => {
                            imagesHTML += `
                                <div class="image-item">
                                    <img src="${img}?t=${Date.now()}" alt="Product Image ${index + 1}">
                                </div>
                            `;
                        });
                    }
                    
                    imagesHTML += '</div>';
                    container.innerHTML = imagesHTML;
                } else {
                    container.innerHTML = '';
                }
            } else {
                alert(<?php echo json_encode($t('error')); ?>);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert(<?php echo json_encode($t('error')); ?>);
            closeProductModal();
        });
    }
}

// Primary action behavior for Add flow
const autoSeoEnabled = <?php echo $settings->getSetting('auto_seo', '0') === '1' ? 'true' : 'false'; ?>;
const productTabOrder = autoSeoEnabled ? ['basic','pricing','variants','images','description'] : ['basic','pricing','variants','images','description','seo'];

function getActiveProductTabIndex() {
    for (let i = 0; i < productTabOrder.length; i++) {
        const pane = document.getElementById('tab-' + productTabOrder[i]);
        if (pane && pane.classList.contains('active')) return i;
    }
    return 0;
}

function goToNextProductTab() {
    const idx = getActiveProductTabIndex();
    if (idx < productTabOrder.length - 1) {
        const nextKey = productTabOrder[idx + 1];
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        const nextBtn = document.querySelector(`.tab-btn[data-tab="${nextKey}"]`);
        if (nextBtn) nextBtn.classList.add('active');
        document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
        const nextPane = document.getElementById('tab-' + nextKey);
        if (nextPane) nextPane.classList.add('active');
        updateProductPrimaryAction();
    }
}

function updateProductPrimaryAction() {
    const primaryBtn = document.getElementById('productPrimaryAction');
    if (!primaryBtn) return;
    const mode = document.getElementById('formAction').value; // 'add' or 'edit'
    if (mode === 'edit') {
        primaryBtn.type = 'submit';
        primaryBtn.innerHTML = '<i class="fas fa-save"></i> Save Product';
        primaryBtn.onclick = null;
        return;
    }
    const idx = getActiveProductTabIndex();
    const onLast = idx === productTabOrder.length - 1;
    if (onLast) {
        primaryBtn.type = 'submit';
        primaryBtn.innerHTML = '<i class="fas fa-save"></i> Save Product';
        primaryBtn.onclick = null;
    } else {
        primaryBtn.type = 'button';
        primaryBtn.innerHTML = '<i class="fas fa-arrow-right"></i> Next';
        primaryBtn.onclick = goToNextProductTab;
    }
}

// Variants UI
function clearVariantRows() {
    const container = document.getElementById('variantsContainer');
    if (container) container.innerHTML = '';
}

function addVariantRow(data = {}) {
    const container = document.getElementById('variantsContainer');
    if (!container) return;
    const row = document.createElement('div');
    row.className = 'variant-row';
    row.innerHTML = `
        <div class="form-group">
            <label>Label</label>
            <input type="text" name="variant_label[]" class="form-input" placeholder="e.g. Red / XL" value="${(data.label || '').toString().replace(/"/g,'&quot;')}">
        </div>
        <div class="form-group">
            <label>SKU</label>
            <input type="text" name="variant_sku[]" class="form-input" placeholder="Optional SKU" value="${(data.sku || '').toString().replace(/"/g,'&quot;')}">
        </div>
        <div class="form-group">
            <label>Price</label>
            <input type="number" step="0.01" name="variant_price[]" class="form-input" placeholder="e.g. 120" value="${data.price !== undefined && data.price !== null ? data.price : ''}">
        </div>
        <div class="form-group">
            <label>Stock</label>
            <input type="number" name="variant_stock[]" class="form-input" placeholder="e.g. 3" value="${data.stock !== undefined && data.stock !== null ? data.stock : 0}">
        </div>
        <div class="variant-actions">
            <button type="button" class="img-action-btn delete" title="Remove" onclick="this.closest('.variant-row').remove()">
                <i class="fas fa-trash"></i>
            </button>
        </div>
    `;
    container.appendChild(row);
}

function closeProductModal() {
    if (isInShellModalFrame()) {
        window.parent.postMessage({ type: 'close_product_modal', refresh: false }, '*');
        return;
    }
    document.getElementById('productModal').classList.remove('active');
    _unlockModalScroll();
    document.getElementById('productForm').reset();
    productImages = [];
    renderImagePreviews();
}

// Close modal on overlay click
document.getElementById('productModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeProductModal();
    }
});

// SKU Generation
function generateSKU() {
    const timestamp = Date.now().toString().slice(-8);
    const random = Math.floor(Math.random() * 1000).toString().padStart(3, '0');
    document.getElementById('sku').value = `SKU-${timestamp}${random}`;
}

// Auto-generate slug
document.getElementById('name').addEventListener('input', function() {
    const slug = this.value.toLowerCase()
        .replace(/[^a-z0-9 -]/g, '')
        .replace(/\s+/g, '-')
        .replace(/-+/g, '-');
    document.getElementById('slug').value = slug;
    
    if (!document.getElementById('sku').value) {
        generateSKU();
    }
});

// Profit calculator
['price', 'sale_price', 'cost_price'].forEach(id => {
    const input = document.getElementById(id);
    if (input) {
        input.addEventListener('input', calculateProfit);
    }
});

function calculateProfit() {
    const price = parseFloat(document.getElementById('price').value) || 0;
    const salePrice = parseFloat(document.getElementById('sale_price').value) || price;
    const costPrice = parseFloat(document.getElementById('cost_price').value) || 0;
    
    const profit = salePrice - costPrice;
    const margin = salePrice > 0 ? ((profit / salePrice) * 100).toFixed(2) : 0;
    const discount = price > 0 && salePrice < price ? (((price - salePrice) / price) * 100).toFixed(2) : 0;
    
    const currencySymbol = '<?php echo addslashes($currency_symbol); ?>';
    const currencyPosition = '<?php echo $currency_position; ?>';
    const profitFormatted = currencyPosition === 'left' ? currencySymbol + profit.toFixed(2) : profit.toFixed(2) + ' ' + currencySymbol;
    
    const calculator = document.getElementById('profitCalculator');
    calculator.innerHTML = `
        <div class="profit-stats">
            <div class="profit-stat">
                <span class="stat-label">Profit</span>
                <span class="stat-value ${profit >= 0 ? 'positive' : 'negative'}">${profitFormatted}</span>
            </div>
            <div class="profit-stat">
                <span class="stat-label">Margin</span>
                <span class="stat-value">${margin}%</span>
            </div>
            ${discount > 0 ? `
                <div class="profit-stat">
                    <span class="stat-label">Discount</span>
                    <span class="stat-value discount">${discount}%</span>
                </div>
            ` : ''}
        </div>
    `;
}

// Image Upload
let productImages = [];
let dtFiles = new DataTransfer();

const uploadZone = document.getElementById('uploadZone');
['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
    uploadZone.addEventListener(eventName, e => {
        e.preventDefault();
        e.stopPropagation();
    });
});

['dragenter', 'dragover'].forEach(eventName => {
    uploadZone.addEventListener(eventName, () => uploadZone.classList.add('drag-over'));
});

['dragleave', 'drop'].forEach(eventName => {
    uploadZone.addEventListener(eventName, () => uploadZone.classList.remove('drag-over'));
});

uploadZone.addEventListener('drop', e => {
    const files = e.dataTransfer.files;
    handleFiles(files);
});

uploadZone.addEventListener('click', () => {
    document.getElementById('productImages').click();
});

document.getElementById('productImages').addEventListener('change', e => {
    handleFiles(e.target.files);
});

function handleFiles(files) {
    [...files].forEach(file => {
        if (!file.type.startsWith('image/')) return;
        dtFiles.items.add(file);
        const reader = new FileReader();
        reader.onload = e => {
            productImages.push({
                dataUrl: e.target.result,
                isMain: productImages.length === 0
            });
            document.getElementById('productImages').files = dtFiles.files;
            renderImagePreviews();
        };
        reader.readAsDataURL(file);
    });
}

function renderImagePreviews() {
    const container = document.getElementById('imagePreviewContainer');
    if (productImages.length === 0) {
        container.innerHTML = '';
        return;
    }
    
    container.innerHTML = `
        <div class="images-header">
            <h4><i class="fas fa-images"></i> Uploaded Images (${productImages.length})</h4>
            <small>Drag to reorder | Click star to set main image</small>
        </div>
        <div class="images-grid">
            ${productImages.map((img, index) => `
                <div class="image-item ${img.isMain ? 'main' : ''}" draggable="true" data-index="${index}">
                    <img src="${img.dataUrl}" alt="Product ${index + 1}">
                    <div class="image-actions">
                        <button type="button" class="img-action-btn ${img.isMain ? 'active' : ''}" 
                                onclick="setMainImage(${index})">
                            <i class="fas fa-star"></i>
                        </button>
                        <button type="button" class="img-action-btn delete" onclick="removeImage(${index})">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                    ${img.isMain ? '<span class="main-badge">Main</span>' : ''}
                </div>
            `).join('')}
        </div>
    `;
    
    // Add drag and drop listeners
    document.querySelectorAll('.image-item').forEach(item => {
        item.addEventListener('dragstart', handleDragStart);
        item.addEventListener('dragover', handleDragOver);
        item.addEventListener('drop', handleImageDrop);
        item.addEventListener('dragend', handleDragEnd);
    });
}

function setMainImage(index) {
    productImages.forEach((img, i) => img.isMain = i === index);
    document.getElementById('main_image_index').value = String(index);
    renderImagePreviews();
}

function removeImage(index) {
    productImages.splice(index, 1);
    const newDT = new DataTransfer();
    [...dtFiles.files].forEach((f, i) => { if (i !== index) newDT.items.add(f); });
    dtFiles = newDT;
    document.getElementById('productImages').files = dtFiles.files;
    if (productImages.length > 0 && !productImages.some(img => img.isMain)) {
        productImages[0].isMain = true;
        document.getElementById('main_image_index').value = '0';
    }
    renderImagePreviews();
}

let draggedIndex = null;

function handleDragStart(e) {
    draggedIndex = parseInt(e.target.dataset.index);
    e.target.classList.add('dragging');
}

function handleDragOver(e) {
    e.preventDefault();
}

function handleImageDrop(e) {
    e.preventDefault();
    const dropIndex = parseInt(e.currentTarget.dataset.index);
    
    if (draggedIndex !== null && draggedIndex !== dropIndex) {
        const draggedItem = productImages.splice(draggedIndex, 1)[0];
        productImages.splice(dropIndex, 0, draggedItem);
        const filesArr = [...dtFiles.files];
        const f = filesArr.splice(draggedIndex, 1)[0];
        filesArr.splice(dropIndex, 0, f);
        const newDT = new DataTransfer();
        filesArr.forEach(ff => newDT.items.add(ff));
        dtFiles = newDT;
        document.getElementById('productImages').files = dtFiles.files;
        renderImagePreviews();
    }
}

function handleDragEnd(e) {
    e.target.classList.remove('dragging');
    draggedIndex = null;
}

// Persist order before submit
document.getElementById('productForm').addEventListener('submit', function() {
    const order = productImages.map((_, i) => i);
    document.getElementById('images_order').value = order.join(',');
});

// View switching
function switchView(view) {
    document.querySelectorAll('.view-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelector(`[data-view="${view}"]`).classList.add('active');
    
    const grid = document.getElementById('productsGrid');
    grid.className = view === 'grid' ? 'products-grid' : 'products-list';
}

// Product actions
function editProduct(id) {
    openProductModal(id);
}

function deleteProduct(id) {
    if (confirm(<?php echo json_encode($t('are_you_sure') . ' ' . $t('delete_product') . '?'); ?>)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="product_id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function toggleProductStatus(id, isActive) {
    var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).getAttribute?.('content') || '';
    var body = 'action=toggle_status&product_id=' + encodeURIComponent(id) + '&is_active=' + (isActive ? 1 : 0);
    if (csrf) body += '&csrf_token=' + encodeURIComponent(csrf);
    fetch('?page=products', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: body
    });
}

function quickView(id) {
    var isFrame = window.self !== window.top;
    var params = new URLSearchParams(window.location.search);
    params.set('page', 'product_quick_view');
    params.set('id', String(id));
    if (isFrame) params.set('content', '1');
    window.location.href = (window.location.pathname || 'index.php') + '?' + params.toString();
}

function exportProducts() {
    alert('Export functionality coming soon!');
}
</script>

<style>
/* Modern Product Management Styles */
:root {
    --primary: #000000;
    --success: #10b981;
    --danger: #ef4444;
    --warning: #f59e0b;
    --info: #3b82f6;
}

.product-management-container {
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
    color: #166534;
}

.alert-danger {
    background: var(--color-error-light);
    color: #991b1b;
}

.alert .btn-close {
    margin-left: auto;
    opacity: 0.5;
}

.alert .btn-close:hover {
    opacity: 1;
}

.btn-primary {
    padding: 0 2rem;
    height: 48px;
    border-radius: 6px;
    border: none;
    background: white;
    color: var(--color-primary-db);
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-primary:hover {
    box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
}

/* Filters Section */
.filters-section {
    background: var(--bg-card);
    border-radius: 6px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.filters-form {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
}

.search-box {
    flex: 1;
    min-width: 300px;
    position: relative;
}

.search-box i {
    position: absolute;
    left: 1.25rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-tertiary);
}

.search-box input {
    width: 100%;
    padding: 0.875rem 1rem 0.875rem 3rem;
    border: 2px solid var(--border-primary);
    border-radius: 6px;
    font-size: 0.9375rem;
    background: var(--bg-card);
    color: var(--text-primary);
    transition: all 0.3s ease;
}

.search-box input:focus {
    outline: none;
    border-color: var(--color-primary-db);
    box-shadow: 0 0 0 3px var(--color-primary-db-light);
}

.search-box input::placeholder {
    color: var(--text-tertiary);
}

.filter-group {
    min-width: 180px;
}

.filter-select {
    width: 100%;
    padding: 0.875rem 1rem;
    border: 2px solid var(--border-primary);
    border-radius: 6px;
    font-size: 0.9375rem;
    background: var(--bg-card);
    color: var(--text-primary);
    cursor: pointer;
    transition: all 0.3s ease;
}

.filter-select:focus {
    outline: none;
    border-color: var(--color-primary-db);
    box-shadow: 0 0 0 3px var(--color-primary-db-light);
}

.btn-filter, .btn-clear {
    padding: 0 1.5rem;
    height: 48px;
    border-radius: 6px;
    border: none;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
}

.btn-filter {
    background: var(--color-primary-db);
    color: var(--text-inverse);
}

.btn-filter:hover {
    background: var(--color-primary-db-hover);
}

.btn-clear {
    background: var(--bg-tertiary);
    color: var(--text-secondary);
}

.btn-clear:hover {
    background: var(--border-primary);
}

/* Products Container */
.products-container {
    background: var(--bg-card);
    border-radius: 6px;
    padding: 1.5rem;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.products-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid #f1f5f9;
}

.results-info {
    font-size: 0.9375rem;
    color: var(--text-secondary);
    font-weight: 500;
}

.view-toggle {
    display: flex;
    gap: 0.5rem;
    background: var(--bg-tertiary);
    padding: 0.25rem;
    border-radius: 6px;
}

.view-btn {
    width: 40px;
    height: 40px;
    border: none;
    border-radius: 6px;
    background: transparent;
    color: var(--text-secondary);
    cursor: pointer;
    transition: all 0.3s ease;
}

.view-btn.active {
    background: var(--bg-card);
    color: var(--primary);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}

/* Products Grid */
.products-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 1.5rem;
}

.products-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.products-list .product-card {
    flex-direction: row;
    align-items: center;
}

.products-list .product-image {
    width: 120px;
    height: 120px;
    flex-shrink: 0;
}

.products-list .product-details {
    flex: 1;
}

.product-card {
    background: var(--bg-card);
    border: 1px solid var(--border-primary);
    border-radius: 6px;
    overflow: hidden;
    transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease;
    display: flex;
    flex-direction: column;
}

.product-card:hover {
    border-color: var(--color-primary-light);
    box-shadow: 0 18px 32px -18px rgba(15, 23, 42, 0.25);
    transform: translateY(-4px);
}

.product-image {
    position: relative;
    width: 100%;
    height: 240px;
    overflow: hidden;
    background: var(--bg-secondary);
}

.product-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.35s ease;
}

.product-card:hover .product-image img {
    transform: scale(1.05);
}

.discount-badge {
    position: absolute;
    top: 12px;
    left: 12px;
    background: #ef4444;
    color: var(--text-inverse);
    padding: 0.25rem 0.75rem;
    border-radius: 6px;
    font-size: 0.875rem;
    font-weight: 700;
}

.featured-badge {
    position: absolute;
    top: 12px;
    right: 12px;
    background: #fbbf24;
    color: var(--text-inverse);
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.product-overlay {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: flex-end;
    justify-content: center;
    gap: 0.5rem;
    padding: 1rem;
    background: linear-gradient(180deg, rgba(15, 23, 42, 0) 45%, rgba(15, 23, 42, 0.75) 100%);
    opacity: 0;
    transition: opacity 0.25s ease;
}

.product-card:hover .product-overlay {
    opacity: 1;
}

.overlay-btn {
    width: 44px;
    height: 44px;
    border-radius: 6px;
    border: none;
    background: rgba(255, 255, 255, 0.12);
    color: var(--text-inverse);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: background 0.25s ease, transform 0.25s ease;
}

.overlay-btn:hover {
    transform: translateY(-2px);
    background: rgba(255, 255, 255, 0.22);
}

.overlay-btn.delete {
    background: rgba(239, 68, 68, 0.85);
}

.overlay-btn.delete:hover {
    background: rgba(220, 38, 38, 0.95);
}

.product-details {
    padding: 1.25rem;
}

.product-header {
    display: flex;
    justify-content: space-between;
    align-items: start;
    gap: 1rem;
    margin-bottom: 0.75rem;
}

.product-name {
    font-size: 1.125rem;
    font-weight: 600;
    margin: 0;
    color: var(--text-primary);
    display: -webkit-box;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

/* Toggle Switch */
.switch {
    position: relative;
    display: inline-block;
    width: 44px;
    height: 24px;
    flex-shrink: 0;
}

.switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #cbd5e1;
    transition: 0.3s;
    border-radius: 6px;
}

.slider:before {
    position: absolute;
    content: "";
    height: 18px;
    width: 18px;
    left: 3px;
    bottom: 3px;
    background-color: var(--bg-card);
    transition: 0.3s;
    border-radius: 50%;
}

input:checked + .slider {
    background-color: var(--success);
}

input:checked + .slider:before {
    transform: translateX(20px);
}

.product-sku {
    font-size: 0.8125rem;
    color: var(--text-secondary);
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.75rem;
}

.product-price {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.original-price {
    text-decoration: line-through;
    color: var(--text-tertiary);
    font-size: 1rem;
}

.sale-price {
    font-size: 1.5rem;
    font-weight: 700;
    color: #ef4444;
}

.current-price {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--primary);
}

.product-meta {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    padding-top: 1rem;
    border-top: 1px solid #f1f5f9;
}

.stock-indicator {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.875rem;
    font-weight: 600;
}

.stock-indicator i {
    font-size: 0.5rem;
}

.stock-good {
    color: var(--success);
}

.stock-low {
    color: var(--warning);
}

.stock-out {
    color: var(--danger);
}

.product-categories {
    font-size: 0.8125rem;
    color: var(--text-secondary);
    display: flex;
    align-items: center;
    gap: 0.5rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Pagination */
.pagination {
    display: flex;
    justify-content: center;
    gap: 0.5rem;
    margin-top: 2rem;
    padding-top: 2rem;
    border-top: 2px solid #f1f5f9;
}

.page-btn {
    min-width: 40px;
    height: 40px;
    padding: 0 0.75rem;
    border-radius: 6px;
    border: 2px solid #e2e8f0;
    background: var(--bg-card);
    color: var(--text-secondary);
    display: flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
}

.page-btn:hover {
    border-color: var(--primary);
    color: var(--primary);
}

.page-btn.active {
    background: var(--primary);
    border-color: var(--primary);
    color: var(--text-inverse);
}

/* Modal (iframe-friendly: full viewport overlay, scroll lock, high z-index) */
.modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.65);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    z-index: 2147483646;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 1.5rem;
    box-sizing: border-box;
}

html.modal-open,
body.modal-open {
    overflow: hidden !important;
    height: 100%;
}

.modal-overlay.active {
    display: flex;
}

.modal-container {
    background: var(--bg-card);
    border-radius: 10px;
    width: 100%;
    max-width: 920px;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4), 0 0 0 1px var(--border-primary);
    animation: modalSlideIn 0.25s ease-out;
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

.modal-body::-webkit-scrollbar {
    width: 6px;
}

.modal-body::-webkit-scrollbar-thumb {
    background: var(--border-secondary);
    border-radius: 3px;
}

/* Tabs */
.tabs-nav {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 2rem;
    padding: 0.5rem;
    background: var(--bg-secondary);
    border-radius: 6px;
    overflow-x: auto;
}

.tab-btn {
    flex: 1;
    min-width: fit-content;
    padding: 0.875rem 1.5rem;
    border: none;
    border-radius: 6px;
    background: transparent;
    color: var(--text-secondary);
    font-weight: 600;
    font-size: 0.9375rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    cursor: pointer;
    transition: all 0.3s ease;
    white-space: nowrap;
}

.tab-btn:hover {
    background: var(--bg-tertiary);
}

.tab-btn.active {
    background: var(--bg-card);
    color: var(--color-primary);
    box-shadow: var(--shadow-sm);
}

.tab-pane {
    display: none;
}

.tab-pane.active {
    display: block;
    animation: fadeIn 0.3s ease-in;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Form Elements */
.form-row {
    display: flex;
    gap: 1rem;
    margin-bottom: 1rem;
}

.form-group {
    flex: 1;
    margin-bottom: 1rem;
}

.form-group.col-4 {
    flex: 0 0 calc(33.333% - 0.667rem);
}

.form-group.col-6 {
    flex: 0 0 calc(50% - 0.5rem);
}

.form-group.col-8 {
    flex: 0 0 calc(66.667% - 0.334rem);
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: var(--text-primary);
    font-size: 0.9375rem;
}

.form-input,
.form-textarea {
    width: 100%;
    padding: 0.875rem 1rem;
    border: 2px solid var(--border-primary);
    border-radius: 6px;
    font-size: 0.9375rem;
    background: var(--bg-card);
    color: var(--text-primary);
    transition: all 0.3s ease;
}

.form-input:focus,
.form-textarea:focus,
.form-input select:focus,
select.form-input:focus {
    outline: none;
    border-color: var(--color-primary);
    box-shadow: 0 0 0 3px var(--color-primary-light);
}

.form-textarea {
    resize: vertical;
    font-family: inherit;
    background: var(--bg-card);
    color: var(--text-primary);
    border: 2px solid var(--border-primary);
}

select.form-input,
.form-input select {
    cursor: pointer;
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%236366f1' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 1rem center;
    padding-right: 2.5rem;
}

[data-theme="dark"] select.form-input,
[data-theme="dark"] .form-input select {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23a5b4fc' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
}

.input-icon {
    position: relative;
}

.input-icon i {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-tertiary);
}

.input-icon input {
    padding-left: 2.75rem;
}

.badge-generate {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.25rem 0.75rem;
    background: var(--color-info-light);
    color: var(--color-info-dark);
    border-radius: 6px;
    font-size: 0.8125rem;
    cursor: pointer;
    transition: all 0.3s ease;
    margin-left: 0.5rem;
}

.badge-generate:hover {
    background: var(--color-info);
    color: var(--text-inverse);
}

.checkbox-group {
    display: flex;
    align-items: center;
    padding: 1rem;
    background: var(--bg-secondary);
    border-radius: 6px;
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

/* Clear on/off indicator for checkboxes */
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

/* Upload Area */
.upload-area {
    border: 3px dashed var(--border-secondary);
    border-radius: 6px;
    padding: 3rem 2rem;
    text-align: center;
    background: var(--bg-secondary);
    transition: all 0.3s ease;
    cursor: pointer;
}

.upload-area:hover,
.upload-area.drag-over {
    border-color: var(--color-primary);
    background: var(--bg-card);
}

.upload-area i {
    color: var(--text-tertiary);
    margin-bottom: 1rem;
}

.upload-area h3 {
    margin: 0 0 0.5rem 0;
    color: var(--text-primary);
}

.upload-area p {
    margin: 0 0 1.5rem 0;
    color: var(--text-secondary);
}

.btn-secondary {
    padding: 0.875rem 2rem;
    border-radius: 6px;
    border: 2px solid var(--border-primary);
    background: var(--bg-card);
    color: var(--color-primary);
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 0.75rem;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-secondary:hover {
    border-color: var(--color-primary);
    background: var(--color-primary);
    color: var(--text-inverse);
}

/* Image Previews */
.image-previews-container {
    margin-top: 2rem;
}

.images-header {
    margin-bottom: 1rem;
}

.images-header h4 {
    margin: 0 0 0.25rem 0;
    font-size: 1.125rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.images-header small {
    color: var(--text-secondary);
}

.images-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 1rem;
}

.image-item {
    position: relative;
    border-radius: 6px;
    overflow: hidden;
    background: var(--bg-secondary);
    border: 2px solid var(--border-primary);
    transition: all 0.3s ease;
    cursor: move;
}

.image-item:hover {
    border-color: var(--color-primary);
    box-shadow: var(--shadow-md);
}

.image-item.main {
    border-color: var(--color-warning);
    border-width: 3px;
    box-shadow: 0 0 0 3px var(--color-warning-light);
}

.image-item.dragging {
    opacity: 0.5;
}

.image-item img {
    width: 100%;
    height: 150px;
    object-fit: cover;
    display: block;
}

.image-actions {
    position: absolute;
    top: 0.5rem;
    right: 0.5rem;
    display: flex;
    gap: 0.5rem;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.image-item:hover .image-actions {
    opacity: 1;
}

.img-action-btn {
    width: 32px;
    height: 32px;
    border-radius: 6px;
    border: none;
    background: var(--bg-overlay);
    color: var(--text-inverse);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
}

.img-action-btn:hover {
    transform: scale(1.1);
}

.img-action-btn.active {
    background: var(--color-warning);
    color: var(--text-inverse);
}

.img-action-btn.delete {
    background: var(--color-error);
}

.main-badge {
    position: absolute;
    bottom: 0.5rem;
    left: 0.5rem;
    padding: 0.25rem 0.75rem;
    background: var(--color-warning);
    color: var(--text-inverse);
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 700;
}

/* Variants */
.variants-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 1rem;
}
.variants-container {
    display: grid;
    gap: 1rem;
}
.variant-row {
    display: grid;
    grid-template-columns: 2fr 1.5fr 1fr 1fr auto;
    gap: 0.75rem;
    align-items: end;
    padding: 1rem;
    border: 2px solid var(--border-primary);
    border-radius: 6px;
    background: var(--bg-secondary);
}
.variant-actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
@media (max-width: 768px) {
    .variant-row {
        grid-template-columns: 1fr;
    }
}

/* Profit Calculator */
.profit-stats {
    display: flex;
    gap: 2rem;
    padding: 1.5rem;
    background: var(--bg-secondary);
    border-radius: 6px;
    margin-top: 1.5rem;
}

.profit-stat {
    flex: 1;
    text-align: center;
}

.stat-label {
    display: block;
    font-size: 0.875rem;
    color: var(--text-secondary);
    margin-bottom: 0.5rem;
}

.stat-value {
    display: block;
    font-size: 1.75rem;
    font-weight: 700;
    color: var(--text-primary);
}

.stat-value.positive {
    color: var(--color-success);
}

.stat-value.negative {
    color: var(--color-error);
}

.stat-value.discount {
    color: var(--color-warning);
}

/* Modal Footer */
.modal-footer {
    padding: 1.5rem 2rem;
    border-top: 2px solid var(--border-primary);
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
}

/* Responsive Design */

/* Large Desktop (1400px+) */
@media (min-width: 1400px) {
    .stats-grid {
        grid-template-columns: repeat(4, 1fr);
    }
}

/* Desktop (1200px - 1399px) */
@media (max-width: 1399px) and (min-width: 1200px) {
    .stats-grid {
        grid-template-columns: repeat(4, 1fr);
    }
}

/* Large Tablet (992px - 1199px) */
@media (max-width: 1199px) and (min-width: 992px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

/* Tablet (768px - 991px) */
@media (max-width: 991px) and (min-width: 768px) {
    .btn-primary {
        flex: 1;
        min-width: 200px;
        justify-content: center;
    }
}

/* Mobile Large (576px - 767px) */
@media (max-width: 767px) and (min-width: 576px) {
    .btn-primary {
        width: 100%;
        height: 44px;
        justify-content: center;
        font-size: 0.875rem;
    }
}

/* Mobile Small (up to 575px) */
@media (max-width: 575px) {
    .btn-primary {
        width: 100%;
        height: 40px;
        justify-content: center;
        font-size: 0.8125rem;
        padding: 0 1rem;
    }
}

/* Additional Mobile Styles */
@media (max-width: 768px) {
    .filters-form {
        flex-direction: column;
    }
    
    .search-box {
        min-width: 100%;
    }
    
    .products-grid {
        grid-template-columns: 1fr;
    }
    
    .form-row {
        flex-direction: column;
    }
    
    .form-group.col-4,
    .form-group.col-6,
    .form-group.col-8 {
        flex: 1;
    }
    
    .tabs-nav {
        overflow-x: scroll;
    }
    
    .modal-container {
        max-height: 100vh;
        border-radius: 0;
    }
    
    .profit-stats {
        flex-direction: column;
        gap: 1rem;
    }
}
</style>
