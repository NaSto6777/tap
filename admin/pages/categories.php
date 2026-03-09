<?php
require_once __DIR__ . '/../../config/StoreContext.php';
require_once __DIR__ . '/../../config/language.php';

$store_id = StoreContext::getId();
$database = new Database();
$conn = $database->getConnection();

// Initialize language
Language::init();
$t = function($key, $default = null) {
    return Language::t($key, $default);
};

// Enable error reporting for debugging
$save_success = false;
$save_error = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add' || $action === 'edit') {
        try {
            $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
            $name = $_POST['name'] ?? '';
            $slug = $_POST['slug'] ?? '';
            $description = $_POST['description'] ?? '';
            $meta_title = !empty($_POST['meta_title']) ? $_POST['meta_title'] : $name;
            $meta_description = !empty($_POST['meta_description']) ? $_POST['meta_description'] : $description;
            $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            $featured = isset($_POST['featured']) ? 1 : 0;
            $sort_order = $_POST['sort_order'] ?? 0;
            
            // Validation
            if (empty($name) || empty($slug)) {
                throw new Exception($t('name') . ', ' . $t('slug') . ' ' . $t('required', 'are required'));
            }
            
            // Calculate level
            $level = 1;
            if ($parent_id) {
                $query = "SELECT level FROM categories WHERE id = ? AND store_id = ?";
                $stmt = $conn->prepare($query);
                $stmt->execute([$parent_id, $store_id]);
                $parent = $stmt->fetch(PDO::FETCH_ASSOC);
                $level = $parent ? $parent['level'] + 1 : 1;
            }
            
            // Make slug unique if duplicate exists
            $original_slug = $slug;
            $counter = 1;
            while (true) {
                $check_query = "SELECT id FROM categories WHERE store_id = ? AND slug = ? AND id != ?";
                $check_stmt = $conn->prepare($check_query);
                $check_stmt->execute([$store_id, $slug, $category_id ?? 0]);
                if (!$check_stmt->fetch()) {
                    break; // Slug is unique
                }
                $slug = $original_slug . '-' . $counter;
                $counter++;
            }
            
            if ($action === 'add') {
                $query = "INSERT INTO categories (store_id, name, slug, description, meta_title, meta_description, 
                          parent_id, level, is_active, featured, sort_order) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($query);
                $stmt->execute([$store_id, $name, $slug, $description, $meta_title, $meta_description, 
                              $parent_id, $level, $is_active, $featured, $sort_order]);
                $category_id = $conn->lastInsertId();
                $save_success = true;
            } else {
                if (empty($category_id)) {
                    throw new Exception('Category ID is required for update');
                }
                $query = "UPDATE categories SET name=?, slug=?, description=?, meta_title=?, meta_description=?,
                          parent_id=?, level=?, is_active=?, featured=?, sort_order=? WHERE id=? AND store_id=?";
                $stmt = $conn->prepare($query);
                $stmt->execute([$name, $slug, $description, $meta_title, $meta_description,
                              $parent_id, $level, $is_active, $featured, $sort_order, $category_id, $store_id]);
                $save_success = true;
            }
        } catch (Exception $e) {
            $save_error = $e->getMessage();
        }

        // Handle category image upload (ONLY for level 1 categories)
        if ($save_success && $category_id && $level == 1 && !empty($_FILES['category_image']) && $_FILES['category_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . "/../../uploads/stores/" . $store_id . "/categories/";
            if (!is_dir($upload_dir)) { @mkdir($upload_dir, 0755, true); }
            
            $ext = strtolower(pathinfo($_FILES['category_image']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
                $target = $upload_dir . $category_id . '.' . $ext;
                
                // Delete old image
                foreach (['jpg', 'jpeg', 'png', 'gif', 'webp'] as $old_ext) {
                    $old_file = $upload_dir . $category_id . '.' . $old_ext;
                    if (file_exists($old_file)) {
                        @unlink($old_file);
                    }
                }
                
                @move_uploaded_file($_FILES['category_image']['tmp_name'], $target);
            }
        }
        
        // Redirect after successful save
        if ($save_success && empty($save_error)) {
            header('Location: ?page=categories&success=' . urlencode($action === 'add' ? $t('category_added_successfully') : $t('category_updated_successfully')));
            exit;
        }
    }
    
    if ($action === 'delete') {
        $category_id = $_POST['category_id'];
        
        // Check if category has children (scoped to store)
        $query = "SELECT COUNT(*) as count FROM categories WHERE parent_id = ? AND store_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->execute([$category_id, $store_id]);
        $has_children = $stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;
        
        if ($has_children) {
            header('Location: ?page=categories&error=' . urlencode($t('cannot_delete_with_subcategories')));
            exit;
        }
        
        $query = "DELETE FROM categories WHERE id = ? AND store_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->execute([$category_id, $store_id]);
        
        // Delete category image (store-scoped path)
        $upload_dir = __DIR__ . "/../../uploads/stores/" . $store_id . "/categories/";
        foreach (['jpg', 'jpeg', 'png', 'gif', 'webp'] as $ext) {
            $file = $upload_dir . $category_id . '.' . $ext;
            if (file_exists($file)) {
                @unlink($file);
            }
        }
        
        header('Location: ?page=categories&success=' . urlencode($t('category_deleted_successfully')));
        exit;
    }
    
    if ($action === 'toggle_status') {
        header('Content-Type: application/json');
        $category_id = (int)($_POST['category_id'] ?? 0);
        $is_active = (int)($_POST['is_active'] ?? 0);
        $query = "UPDATE categories SET is_active = ? WHERE id = ? AND store_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->execute([$is_active, $category_id, $store_id]);
        echo json_encode(['success' => true]);
        exit;
    }
    
    if ($action === 'quick_update_category') {
        header('Content-Type: application/json');
        try {
            $category_id = $_POST['category_id'] ?? 0;
            $field = $_POST['field'] ?? '';
            $value = $_POST['value'] ?? '';
            
            if (!$category_id || !$field) {
                echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
                exit;
            }
            
            // Validate field name to prevent SQL injection
            $allowed_fields = ['name', 'slug', 'description', 'sort_order', 'is_active', 'featured'];
            if (!in_array($field, $allowed_fields)) {
                echo json_encode(['success' => false, 'message' => 'Invalid field name']);
                exit;
            }
            
            // Prepare value based on field type
            if ($field === 'sort_order') {
                $value = (int)$value;
            } elseif ($field === 'is_active' || $field === 'featured') {
                $value = (int)$value;
            } else {
                $value = trim($value);
            }
            
            // Special handling for slug - check uniqueness
            if ($field === 'slug') {
                $original_slug = $value;
                $counter = 1;
                while (true) {
                    $check_query = "SELECT id FROM categories WHERE store_id = ? AND slug = ? AND id != ?";
                    $check_stmt = $conn->prepare($check_query);
                    $check_stmt->execute([$store_id, $value, $category_id]);
                    if (!$check_stmt->fetch()) {
                        break; // Slug is unique
                    }
                    $value = $original_slug . '-' . $counter;
                    $counter++;
                }
            }
            
            // Build update query
            $query = "UPDATE categories SET {$field} = ? WHERE id = ? AND store_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->execute([$value, $category_id, $store_id]);
            
            echo json_encode(['success' => true, 'message' => 'Field updated successfully']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error updating field: ' . $e->getMessage()]);
        }
        exit;
    }
    
    if ($action === 'get_category') {
        header('Content-Type: application/json');
        $category_id = $_POST['category_id'] ?? $_GET['category_id'] ?? 0;
        $query = "SELECT * FROM categories WHERE id = ? AND store_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->execute([$category_id, $store_id]);
        $category = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($category) {
            // Get category image URL
            $image_url = '';
            foreach (['jpg', 'jpeg', 'png', 'gif', 'webp'] as $ext) {
                $temp_path = "../uploads/categories/{$category['id']}.$ext";
                if (file_exists($temp_path)) {
                    $image_url = $temp_path;
                    break;
                }
            }
            $category['image_url'] = $image_url;
            
            echo json_encode(['success' => true, 'category' => $category]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Category not found']);
        }
        exit;
    }
}

// Get filters
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$level_filter = $_GET['level'] ?? '';
$sort_by = $_GET['sort'] ?? 'sort_order';
$sort_order = $_GET['order'] ?? 'ASC';
$page = $_GET['p'] ?? 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Build query (scoped to store)
$where = ["c.store_id = ?"];
$params = [$store_id];

if ($search) {
    $where[] = "(c.name LIKE ? OR c.slug LIKE ? OR c.description LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if ($status_filter === 'active') {
    $where[] = "c.is_active = 1";
} elseif ($status_filter === 'inactive') {
    $where[] = "c.is_active = 0";
}

if ($level_filter) {
    $where[] = "c.level = ?";
    $params[] = $level_filter;
}

$where_clause = implode(' AND ', $where);

// Get total categories
$query = "SELECT COUNT(*) as total FROM categories c WHERE $where_clause";
$stmt = $conn->prepare($query);
$stmt->execute($params);
$total_categories = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_categories / $per_page);

// Get categories with hierarchy (store-scoped)
$query = "SELECT c.*, p.name as parent_name,
          (SELECT COUNT(*) FROM categories WHERE parent_id = c.id AND store_id = c.store_id) as child_count,
          (SELECT COUNT(*) FROM product_categories WHERE category_id = c.id) as product_count
          FROM categories c 
          LEFT JOIN categories p ON c.parent_id = p.id AND p.store_id = c.store_id
          WHERE $where_clause
          ORDER BY c.$sort_by $sort_order
          LIMIT $per_page OFFSET $offset";
$stmt = $conn->prepare($query);
$stmt->execute($params);
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all categories for parent selection (scoped to store)
$query = "SELECT * FROM categories WHERE store_id = ? ORDER BY level, sort_order, name";
$stmt = $conn->prepare($query);
$stmt->execute([$store_id]);
$all_categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics (scoped to store)
$stats_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
    SUM(CASE WHEN level = 1 THEN 1 ELSE 0 END) as root_categories,
    SUM(CASE WHEN featured = 1 THEN 1 ELSE 0 END) as featured
    FROM categories WHERE store_id = ?";
$stmt = $conn->prepare($stats_query);
$stmt->execute([$store_id]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);
$stats = is_array($stats) ? array_merge(['total' => 0, 'active' => 0, 'root_categories' => 0, 'featured' => 0], $stats) : ['total' => 0, 'active' => 0, 'root_categories' => 0, 'featured' => 0];
$stats['total'] = (int)($stats['total'] ?? 0);
$stats['active'] = (int)($stats['active'] ?? 0);
$stats['root_categories'] = (int)($stats['root_categories'] ?? 0);
$stats['featured'] = (int)($stats['featured'] ?? 0);

// Get max level (scoped to store)
$max_level_query = "SELECT MAX(level) as max_level FROM categories WHERE store_id = ?";
$stmt = $conn->prepare($max_level_query);
$stmt->execute([$store_id]);
$max_level = $stmt->fetch(PDO::FETCH_ASSOC)['max_level'] ?? 1;
?>

<!-- Modern Category Management Interface -->
<div class="category-management-container">
    
    <!-- Success/Error Messages -->
    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            <?php echo htmlspecialchars($_GET['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i>
            <?php echo htmlspecialchars($_GET['error']); ?>
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
    
    <!-- Key Metrics -->
    <section class="stats-grid">
        <div class="stat-card">
            <div class="stat-card-header">
                <div class="stat-card-icon primary">
                    <i class="fas fa-folder-open"></i>
                </div>
            </div>
            <div class="stat-card-content">
                <div class="stat-card-value"><?php echo (int)($stats['total'] ?? 0); ?></div>
                <div class="stat-card-label"><?php echo $t('total_categories'); ?></div>
                <div class="stat-card-change positive">
                    <i class="fas fa-arrow-up"></i>
                    <span><?php echo $t('all_categories'); ?></span>
                </div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-card-header">
                <div class="stat-card-icon success">
                    <i class="fas fa-check-circle"></i>
                </div>
            </div>
            <div class="stat-card-content">
                <div class="stat-card-value"><?php echo (int)($stats['active'] ?? 0); ?></div>
                <div class="stat-card-label"><?php echo $t('active_categories'); ?></div>
                <div class="stat-card-change positive">
                    <i class="fas fa-arrow-trend-up"></i>
                    <span><?php echo $stats['total'] > 0 ? round(($stats['active'] / $stats['total']) * 100) : 0; ?>% <?php echo $t('active'); ?></span>
                </div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-card-header">
                <div class="stat-card-icon warning">
                    <i class="fas fa-sitemap"></i>
                </div>
            </div>
            <div class="stat-card-content">
                <div class="stat-card-value"><?php echo (int)($stats['root_categories'] ?? 0); ?></div>
                <div class="stat-card-label"><?php echo $t('root_categories'); ?></div>
                <div class="stat-card-change neutral">
                    <i class="fas fa-sitemap"></i>
                    <span><?php echo $t('level_1_categories'); ?></span>
                </div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-card-header">
                <div class="stat-card-icon danger">
                    <i class="fas fa-star"></i>
                </div>
            </div>
            <div class="stat-card-content">
                <div class="stat-card-value"><?php echo (int)($stats['featured'] ?? 0); ?></div>
                <div class="stat-card-label"><?php echo $t('featured'); ?></div>
                <div class="stat-card-change warning">
                    <i class="fas fa-star"></i>
                    <span><?php echo $t('highlighted_categories'); ?></span>
                </div>
            </div>
        </div>
    </section>

    <!-- Filters -->
    <section class="filters-section">
        <form method="GET" class="filters-form">
            <input type="hidden" name="page" value="categories">
            
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" name="search" placeholder="<?php echo $t('search_categories_placeholder'); ?>" 
                       value="<?php echo htmlspecialchars($search); ?>">
            </div>
            
            <div class="filter-group">
                <select name="level" class="filter-select" onchange="this.form.submit()">
                    <option value=""><?php echo $t('all_levels'); ?></option>
                    <?php for ($i = 1; $i <= $max_level; $i++): ?>
                        <option value="<?php echo $i; ?>" <?php echo $level_filter == $i ? 'selected' : ''; ?>>
                            <?php echo $t('level'); ?> <?php echo $i; ?>
                        </option>
                    <?php endfor; ?>
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
                    <option value="sort_order" <?php echo $sort_by === 'sort_order' ? 'selected' : ''; ?>><?php echo $t('sort_order'); ?></option>
                    <option value="name" <?php echo $sort_by === 'name' ? 'selected' : ''; ?>><?php echo $t('name_az'); ?></option>
                    <option value="level" <?php echo $sort_by === 'level' ? 'selected' : ''; ?>><?php echo $t('level'); ?></option>
                    <option value="created_at" <?php echo $sort_by === 'created_at' ? 'selected' : ''; ?>><?php echo $t('newest_first'); ?></option>
                </select>
            </div>
            
            <button type="submit" class="btn-filter">
                <i class="fas fa-filter"></i>
                <?php echo $t('apply'); ?>
            </button>
            
            <?php if ($search || $status_filter || $level_filter): ?>
                <a href="?page=categories" class="btn-clear">
                    <i class="fas fa-times"></i>
                    <?php echo $t('clear'); ?>
                </a>
            <?php endif; ?>
        </form>
    </section>

    <!-- Results -->
    <section class="categories-container">
        <header class="categories-header">
            <div class="results-info">
                <?php echo $t('showing_categories'); ?> <?php echo count($categories); ?> <?php echo $t('of_categories'); ?> <?php echo $total_categories; ?> <?php echo $t('categories'); ?>
            </div>
            <div class="view-toggle">
                <button class="view-btn active" data-view="grid" onclick="switchView('grid')">
                    <i class="fas fa-th-large"></i>
                </button>
                <button class="view-btn" data-view="list" onclick="switchView('list')">
                    <i class="fas fa-list"></i>
                </button>
            </div>
        </header>

        <div class="categories-grid" id="categoriesGrid">
            <?php foreach ($categories as $category): ?>
                <div class="category-card" data-id="<?php echo $category['id']; ?>">
                    <div class="category-image">
                        <?php
                        $image_found = false;
                        $image_path = '';
                        foreach (['jpg', 'jpeg', 'png', 'gif', 'webp'] as $ext) {
                            $temp_path = "../uploads/categories/{$category['id']}.$ext";
                            if (file_exists($temp_path)) {
                                $image_path = $temp_path;
                                $image_found = true;
                                break;
                            }
                        }
                        $display_path = $image_found ? $image_path : "../uploads/placeholder.jpg";
                        ?>
                        <img src="<?php echo $display_path; ?>" 
                             onerror="this.src='../uploads/placeholder.jpg'" 
                             alt="<?php echo htmlspecialchars($category['name']); ?>">
                        
                        <?php if ($category['featured']): ?>
                            <div class="featured-badge">
                                <i class="fas fa-star"></i>
                            </div>
                        <?php endif; ?>
                        
                        <div class="level-badge level-<?php echo $category['level']; ?>">
                            L<?php echo $category['level']; ?>
                        </div>
                        
                        <div class="category-overlay">
                            <button class="overlay-btn" onclick="viewCategory(<?php echo $category['id']; ?>)" title="<?php echo $t('view'); ?>">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="overlay-btn" onclick="editCategory(<?php echo $category['id']; ?>)" title="<?php echo $t('edit'); ?>">
                                <i class="fas fa-edit"></i>
                            </button>
                            <?php if ($category['child_count'] == 0): ?>
                                <button class="overlay-btn delete" onclick="deleteCategory(<?php echo $category['id']; ?>)" title="<?php echo $t('delete'); ?>">
                                    <i class="fas fa-trash"></i>
                                </button>
                            <?php else: ?>
                                <button class="overlay-btn disabled" title="<?php echo $t('cannot_delete_category_subcategories'); ?>" disabled>
                                    <i class="fas fa-lock"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="category-details">
                        <div class="category-header">
                            <h3 class="category-name" title="<?php echo htmlspecialchars($category['name']); ?>">
                                <?php echo htmlspecialchars($category['name']); ?>
                            </h3>
                            <div class="category-status-toggle">
                                <label class="switch">
                                    <input type="checkbox" 
                                           <?php echo $category['is_active'] ? 'checked' : ''; ?>
                                           onchange="toggleCategoryStatus(<?php echo $category['id']; ?>, this.checked)">
                                    <span class="slider"></span>
                                </label>
                            </div>
                        </div>
                        
                        <div class="category-slug">
                            <i class="fas fa-link"></i>
                            <?php echo htmlspecialchars($category['slug']); ?>
                        </div>
                        
                        <?php if ($category['description']): ?>
                            <div class="category-description">
                                <?php echo htmlspecialchars(substr($category['description'], 0, 100)) . (strlen($category['description']) > 100 ? '...' : ''); ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="category-meta">
                            <?php if ($category['parent_name']): ?>
                                <div class="parent-indicator">
                                    <i class="fas fa-folder"></i>
                                    <?php echo htmlspecialchars($category['parent_name']); ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="category-stats">
                                <?php if ($category['child_count'] > 0): ?>
                                    <span class="stat-badge subcategories">
                                        <i class="fas fa-sitemap"></i>
                                        <?php echo $category['child_count']; ?> <?php echo $t('sub'); ?>
                                    </span>
                                <?php endif; ?>
                                <span class="stat-badge products">
                                    <i class="fas fa-box"></i>
                                    <?php echo $category['product_count']; ?> <?php echo $t('items'); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <nav class="pagination" aria-label="Category pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=categories&p=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&level=<?php echo $level_filter; ?>&status=<?php echo $status_filter; ?>" 
                       class="page-btn">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                <?php endif; ?>
                
                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <a href="?page=categories&p=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&level=<?php echo $level_filter; ?>&status=<?php echo $status_filter; ?>" 
                       class="page-btn <?php echo $i == $page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=categories&p=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&level=<?php echo $level_filter; ?>&status=<?php echo $status_filter; ?>" 
                       class="page-btn">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    </section>
</div>

<!-- Category Modal -->
<div class="modal-overlay" id="categoryModal">
    <div class="modal-container">
        <div class="modal-header">
            <h2><i class="fas fa-folder"></i> <span id="modalTitle"><?php echo $t('add_new_category'); ?></span></h2>
            <button class="modal-close" onclick="closeCategoryModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form method="POST" id="categoryForm" enctype="multipart/form-data">
            <?php echo CsrfHelper::getTokenField(); ?>
            <div class="modal-body">
                <input type="hidden" name="action" value="add" id="formAction">
                <input type="hidden" name="category_id" id="categoryId">
                
                <!-- Tab Navigation -->
                <div class="tabs-nav">
                    <button type="button" class="tab-btn active" data-tab="basic">
                        <i class="fas fa-info-circle"></i> <?php echo $t('basic_info'); ?>
                    </button>
                    <button type="button" class="tab-btn" data-tab="hierarchy">
                        <i class="fas fa-sitemap"></i> <?php echo $t('hierarchy', 'Hierarchy'); ?>
                    </button>
                    <button type="button" class="tab-btn" data-tab="image">
                        <i class="fas fa-image"></i> <?php echo $t('image'); ?>
                    </button>
                    <button type="button" class="tab-btn" data-tab="seo">
                        <i class="fas fa-search"></i> <?php echo $t('seo'); ?>
                    </button>
                </div>
                
                <!-- Tab Content -->
                <div class="tabs-content">
                    <!-- Basic Info Tab -->
                    <div class="tab-pane active" id="tab-basic">
                        <div class="form-row">
                            <div class="form-group col-8">
                                <label><?php echo $t('category_name'); ?> *</label>
                                <input type="text" name="name" id="name" class="form-input" required>
                            </div>
                            <div class="form-group col-4">
                                <label><?php echo $t('sort_order'); ?></label>
                                <input type="number" name="sort_order" id="sort_order" class="form-input" value="0" min="0">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label><?php echo $t('url_slug'); ?> *</label>
                            <input type="text" name="slug" id="slug" class="form-input" required readonly>
                        </div>
                        
                        <div class="form-group">
                            <label><?php echo $t('description'); ?></label>
                            <textarea name="description" id="description" class="form-textarea" rows="4"></textarea>
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
                                        <span><?php echo $t('featured_category'); ?></span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Hierarchy Tab -->
                    <div class="tab-pane" id="tab-hierarchy">
                        <div class="form-group">
                            <label><?php echo $t('parent_category'); ?></label>
                            <select name="parent_id" id="parent_id" class="form-input">
                                <option value=""><?php echo $t('root_category', 'Root Category'); ?> (<?php echo $t('no_parent'); ?>)</option>
                                <?php foreach ($all_categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>">
                                        <?php echo str_repeat('—— ', $cat['level'] - 1); ?>
                                        <?php echo htmlspecialchars($cat['name']); ?>
                                        (<?php echo $t('level'); ?> <?php echo $cat['level']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-hint">
                                <i class="fas fa-info-circle"></i>
                                <?php echo $t('select_parent_category_hint', 'Select a parent category to create a subcategory. Leave empty for root level.'); ?>
                            </small>
                        </div>
                        
                        <div class="hierarchy-preview">
                            <h4><i class="fas fa-sitemap"></i> <?php echo $t('category_structure_preview', 'Category Structure Preview'); ?></h4>
                            <div class="hierarchy-tree">
                                <!-- Will be populated by JavaScript -->
                            </div>
                        </div>
                    </div>
                    
                    <!-- Image Tab -->
                    <div class="tab-pane" id="tab-image">
                        <div id="imageTabContent">
                        <div class="upload-area" id="uploadZone">
                            <i class="fas fa-cloud-upload-alt fa-3x"></i>
                            <h3><?php echo $t('category_image'); ?></h3>
                            <p><?php echo $t('upload_image_hint', 'Upload an image to represent this category'); ?></p>
                                <p class="image-restriction" style="color: #f59e0b; font-weight: 600; margin-top: 1rem;">
                                    <i class="fas fa-info-circle"></i>
                                    <?php echo $t('image_level_restriction', 'Images are only available for root level (Level 1) categories'); ?>
                                </p>
                            <input type="file" id="categoryImage" name="category_image" accept="image/*" style="display: none;">
                            <button type="button" class="btn-secondary" onclick="document.getElementById('categoryImage').click()">
                                <i class="fas fa-folder-open"></i> <?php echo $t('choose_image', 'Choose Image'); ?>
                            </button>
                        </div>
                        
                        <div id="imagePreviewContainer" class="image-preview-container">
                            <!-- Image preview will be shown here -->
                            </div>
                        </div>
                    </div>
                    
                    <!-- SEO Tab -->
                    <div class="tab-pane" id="tab-seo">
                        <div class="form-group">
                            <label><?php echo $t('meta_title'); ?></label>
                            <input type="text" name="meta_title" id="meta_title" class="form-input">
                            <small class="form-hint">
                                <i class="fas fa-info-circle"></i>
                                <?php echo $t('meta_title_hint', 'Leave empty to use category name'); ?>
                            </small>
                        </div>
                        
                        <div class="form-group">
                            <label><?php echo $t('meta_description'); ?></label>
                            <textarea name="meta_description" id="meta_description" class="form-textarea" rows="3"></textarea>
                            <small class="form-hint">
                                <i class="fas fa-info-circle"></i>
                                <?php echo $t('meta_description_hint', 'Recommended: 150-160 characters for optimal SEO'); ?>
                            </small>
                        </div>
                        
                        <div class="seo-preview">
                            <h4><i class="fas fa-search"></i> <?php echo $t('search_engine_preview', 'Search Engine Preview'); ?></h4>
                            <div class="seo-preview-card">
                                <div class="seo-title" id="seoPreviewTitle"><?php echo $t('category_name'); ?></div>
                                <div class="seo-url" id="seoPreviewUrl">yoursite.com/category/slug</div>
                                <div class="seo-description" id="seoPreviewDescription"><?php echo $t('category_description_preview', 'Category description will appear here...'); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeCategoryModal()">
                    <i class="fas fa-times"></i> <?php echo $t('cancel'); ?>
                </button>
                <button type="button" class="btn-primary" id="categoryPrimaryAction">
                    <i class="fas fa-arrow-right"></i> <span id="categoryPrimaryActionText"><?php echo $t('next', 'Next'); ?></span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Pass translations to JavaScript
window.translations = {
    add_new_category: <?php echo json_encode($t('add_new_category')); ?>,
    edit_category: <?php echo json_encode($t('edit_category')); ?>,
    save_category: <?php echo json_encode($t('save_category', 'Save Category')); ?>,
    next: <?php echo json_encode($t('next', 'Next')); ?>,
    previous: <?php echo json_encode($t('previous', 'Previous')); ?>,
    are_you_sure: <?php echo json_encode($t('are_you_sure')); ?>,
    delete_category: <?php echo json_encode($t('delete_category')); ?>,
    error_loading_category: <?php echo json_encode($t('error_loading_category', 'Error loading category data')); ?>,
    images_only_root: <?php echo json_encode($t('image_level_restriction')); ?>,
    current_image: <?php echo json_encode($t('current_image', 'Current image (upload new to replace)')); ?>
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
        updateCategoryPrimaryAction();
    });
});

// Modal functions
function openCategoryModal(categoryId = null) {
    document.getElementById('categoryModal').classList.add('active');
    document.body.style.overflow = 'hidden';
    
    if (!categoryId) {
        document.getElementById('categoryForm').reset();
        document.getElementById('modalTitle').textContent = window.translations.add_new_category;
        document.getElementById('formAction').value = 'add';
        document.getElementById('imagePreviewContainer').innerHTML = '';
        // Reset to first tab
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelector('.tab-btn[data-tab="basic"]').classList.add('active');
        document.querySelectorAll('.tab-pane').forEach(pane => pane.classList.remove('active'));
        document.getElementById('tab-basic').classList.add('active');
        // Set primary action to Next
        const primaryBtn = document.getElementById('categoryPrimaryAction');
        if (primaryBtn) {
            primaryBtn.type = 'button';
            primaryBtn.innerHTML = '<i class="fas fa-arrow-right"></i> ' + window.translations.next;
            primaryBtn.onclick = goToNextCategoryTab;
        }
        updateSEOPreview();
    } else {
        document.getElementById('modalTitle').textContent = window.translations.edit_category;
        document.getElementById('formAction').value = 'edit';
        document.getElementById('categoryId').value = categoryId;
        // In edit mode, keep Save visible from the start
        const primaryBtn = document.getElementById('categoryPrimaryAction');
        if (primaryBtn) {
            primaryBtn.type = 'submit';
            primaryBtn.innerHTML = '<i class="fas fa-save"></i> ' + window.translations.save_category;
            primaryBtn.onclick = null;
        }
        
        // Load category data via AJAX
        fetch('?page=categories', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=get_category&category_id=${categoryId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.category) {
                const cat = data.category;
                
                // Populate form fields
                document.getElementById('name').value = cat.name || '';
                document.getElementById('slug').value = cat.slug || '';
                document.getElementById('description').value = cat.description || '';
                document.getElementById('meta_title').value = cat.meta_title || '';
                document.getElementById('meta_description').value = cat.meta_description || '';
                document.getElementById('parent_id').value = cat.parent_id || '';
                document.getElementById('sort_order').value = cat.sort_order || 0;
                document.getElementById('is_active').checked = cat.is_active == 1;
                document.getElementById('featured').checked = cat.featured == 1;
                
                // Check if this is a root category (level 1) - only root categories can have images
                const isRootCategory = !cat.parent_id || cat.level == 1;
                const imageTabContent = document.getElementById('imageTabContent');
                
                if (!isRootCategory) {
                    // Disable image upload for subcategories
                    imageTabContent.innerHTML = `
                        <div class="alert alert-warning">
                            <i class="fas fa-info-circle"></i>
                            <strong>${window.translations.images_only_root}</strong>
                            <p style="margin: 0.5rem 0 0 0;">This is a Level ${cat.level} category. Only root categories can have images according to the system design.</p>
                        </div>
                    `;
                } else {
                    // Show existing image if available
                    if (cat.image_url) {
                        const container = document.getElementById('imagePreviewContainer');
                        container.innerHTML = `
                            <div class="image-preview-card">
                                <img src="${cat.image_url}" alt="Current Category Image">
                                <div class="image-info">
                                    <i class="fas fa-info-circle"></i>
                                    ${window.translations.current_image}
                                </div>
                            </div>
                        `;
                    }
                }
                
                // Update SEO preview
                updateSEOPreview();
            } else {
                alert(window.translations.error_loading_category);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert(window.translations.error_loading_category);
        });
    }
}

// Primary action behavior for Add flow
const categoryTabOrder = ['basic','hierarchy','image','seo'];

function getActiveCategoryTabIndex() {
    for (let i = 0; i < categoryTabOrder.length; i++) {
        const pane = document.getElementById('tab-' + categoryTabOrder[i]);
        if (pane && pane.classList.contains('active')) return i;
    }
    return 0;
}

function goToNextCategoryTab() {
    const idx = getActiveCategoryTabIndex();
    if (idx < categoryTabOrder.length - 1) {
        const nextKey = categoryTabOrder[idx + 1];
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        const nextBtn = document.querySelector(`.tab-btn[data-tab="${nextKey}"]`);
        if (nextBtn) nextBtn.classList.add('active');
        document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
        const nextPane = document.getElementById('tab-' + nextKey);
        if (nextPane) nextPane.classList.add('active');
        updateCategoryPrimaryAction();
        // Ensure SEO preview reflects current values when entering SEO
        if (nextKey === 'seo') updateSEOPreview();
    }
}

function updateCategoryPrimaryAction() {
    const primaryBtn = document.getElementById('categoryPrimaryAction');
    if (!primaryBtn) return;
    const mode = document.getElementById('formAction').value; // 'add' or 'edit'
    if (mode === 'edit') {
        primaryBtn.type = 'submit';
        primaryBtn.innerHTML = '<i class="fas fa-save"></i> ' + window.translations.save_category;
        primaryBtn.onclick = null;
        return;
    }
    const idx = getActiveCategoryTabIndex();
    const onLast = idx === categoryTabOrder.length - 1;
    if (onLast) {
        primaryBtn.type = 'submit';
        primaryBtn.innerHTML = '<i class="fas fa-save"></i> ' + window.translations.save_category;
        primaryBtn.onclick = null;
    } else {
        primaryBtn.type = 'button';
        primaryBtn.innerHTML = '<i class="fas fa-arrow-right"></i> ' + window.translations.next;
        primaryBtn.onclick = goToNextCategoryTab;
    }
}

function closeCategoryModal() {
    document.getElementById('categoryModal').classList.remove('active');
    document.body.style.overflow = '';
    document.getElementById('categoryForm').reset();
    document.getElementById('imagePreviewContainer').innerHTML = '';
}

// Close modal on overlay click
document.getElementById('categoryModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeCategoryModal();
    }
});

// Auto-generate slug
document.getElementById('name').addEventListener('input', function() {
    const slug = this.value.toLowerCase()
        .replace(/[^a-z0-9 -]/g, '')
        .replace(/\s+/g, '-')
        .replace(/-+/g, '-');
    document.getElementById('slug').value = slug;
    
    // Update SEO preview
    updateSEOPreview();
});

// Monitor parent category selection to show/hide image upload (only level 1 can have images)
document.getElementById('parent_id').addEventListener('change', function() {
    const isRootCategory = this.value === '';
    const imageTabContent = document.getElementById('imageTabContent');
    const uploadZone = document.getElementById('uploadZone');
    const imageInput = document.getElementById('categoryImage');
    
    if (isRootCategory) {
        // Enable image upload for root categories (level 1)
        if (uploadZone) uploadZone.style.opacity = '1';
        if (imageInput) imageInput.disabled = false;
        uploadZone.style.pointerEvents = 'auto';
    } else {
        // Disable image upload for subcategories (level 2+)
        if (uploadZone) uploadZone.style.opacity = '0.5';
        if (imageInput) imageInput.disabled = true;
        uploadZone.style.pointerEvents = 'none';
        imageTabContent.innerHTML = `
            <div class="alert alert-warning">
                <i class="fas fa-info-circle"></i>
                <strong>Images are only available for root level (Level 1) categories.</strong>
                <p style="margin: 0.5rem 0 0 0;">This is a subcategory. Only root categories can have images according to the system design.</p>
            </div>
        `;
    }
});

// Image Upload
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
    if (files.length > 0) {
        document.getElementById('categoryImage').files = files;
        handleImagePreview(files[0]);
    }
});

uploadZone.addEventListener('click', () => {
    document.getElementById('categoryImage').click();
});

document.getElementById('categoryImage').addEventListener('change', e => {
    if (e.target.files.length > 0) {
        handleImagePreview(e.target.files[0]);
    }
});

function handleImagePreview(file) {
    if (!file.type.startsWith('image/')) return;
    
    const reader = new FileReader();
    reader.onload = e => {
        const container = document.getElementById('imagePreviewContainer');
        container.innerHTML = `
            <div class="image-preview-card">
                <img src="${e.target.result}" alt="Category Preview">
                <button type="button" class="remove-image-btn" onclick="removeImage()">
                    <i class="fas fa-times"></i>
                </button>
                <div class="image-info">
                    <i class="fas fa-check-circle"></i>
                    ${window.translations.image_ready_to_upload || 'Image ready to upload'}
                </div>
            </div>
        `;
    };
    reader.readAsDataURL(file);
}

function removeImage() {
    document.getElementById('categoryImage').value = '';
    document.getElementById('imagePreviewContainer').innerHTML = '';
}

// SEO Preview
function updateSEOPreview() {
    const name = document.getElementById('name').value || 'Category Name';
    const slug = document.getElementById('slug').value || 'slug';
    const metaTitle = document.getElementById('meta_title').value || name;
    const metaDesc = document.getElementById('meta_description').value || 
                     document.getElementById('description').value || 
                     'Category description will appear here...';
    
    document.getElementById('seoPreviewTitle').textContent = metaTitle;
    document.getElementById('seoPreviewUrl').textContent = `yoursite.com/category/${slug}`;
    document.getElementById('seoPreviewDescription').textContent = metaDesc.substring(0, 160);
}

// Update SEO preview on input
['meta_title', 'meta_description', 'description'].forEach(id => {
    const element = document.getElementById(id);
    if (element) {
        element.addEventListener('input', updateSEOPreview);
    }
});

// View switching
function switchView(view) {
    document.querySelectorAll('.view-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelector(`[data-view="${view}"]`).classList.add('active');
    
    const grid = document.getElementById('categoriesGrid');
    grid.className = view === 'grid' ? 'categories-grid' : 'categories-list';
}

// Check for category ID from view details
document.addEventListener('DOMContentLoaded', function() {
    const editCategoryId = sessionStorage.getItem('editCategoryId');
    if (editCategoryId) {
        sessionStorage.removeItem('editCategoryId');
        setTimeout(() => {
            editCategory(parseInt(editCategoryId));
        }, 100);
    }
});

// Category actions
function editCategory(id) {
    openCategoryModal(id);
}

function viewCategory(id) {
    window.location.href = '?page=category_view_details&id=' + id;
}

function deleteCategory(id) {
    if (confirm(window.translations.are_you_sure + ' ' + window.translations.delete_category + '?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="category_id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function toggleCategoryStatus(id, isActive) {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    fetch('?page=categories', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=toggle_status&category_id=${id}&is_active=${isActive ? 1 : 0}&csrf_token=${encodeURIComponent(token)}`
    });
}

function exportCategories() {
    alert('Export functionality coming soon!');
}

// Auto-dismiss alerts after 5 seconds
setTimeout(() => {
    document.querySelectorAll('.alert').forEach(alert => {
        alert.classList.remove('show');
        setTimeout(() => alert.remove(), 300);
    });
}, 5000);
</script>

<!-- Category styles moved to admin/assets/css/admin.css -->


