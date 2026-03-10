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

// Get currency settings
require_once __DIR__ . '/../../config/settings.php';
$settings = new Settings(null, $store_id);
$currency = $settings->getSetting('currency', 'USD');
$custom_currency = $settings->getSetting('custom_currency', '');
if ($currency === 'CUSTOM' && !empty($custom_currency)) {
    $currency_symbol = $custom_currency;
} else {
    $currency_symbol = $currency === 'USD' ? '$' : ($currency === 'EUR' ? '€' : ($currency === 'GBP' ? '£' : ($currency === 'TND' ? 'TND' : $currency . ' ')));
}
$currency_position = $settings->getSetting('currency_position', 'left');

// Get category ID
$category_id = $_GET['id'] ?? 0;

if (!$category_id) {
    $back = '?page=categories';
    if (isset($_GET['content']) && $_GET['content'] === '1') $back .= '&content=1';
    header('Location: ' . $back);
    exit;
}

// Get category data (scoped to store)
$query = "SELECT c.*, p.name as parent_name, p.slug as parent_slug
          FROM categories c 
          LEFT JOIN categories p ON c.parent_id = p.id AND p.store_id = c.store_id
          WHERE c.id = ? AND c.store_id = ?";
$stmt = $conn->prepare($query);
$stmt->execute([$category_id, $store_id]);
$category = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$category) {
    $back = '?page=categories&error=' . urlencode($t('category_not_found', 'Category not found'));
    if (isset($_GET['content']) && $_GET['content'] === '1') $back .= '&content=1';
    header('Location: ' . $back);
    exit;
}

// Get children categories (scoped to store)
$query = "SELECT id, name, slug, level, is_active, featured, 
          (SELECT COUNT(*) FROM product_categories WHERE category_id = categories.id) as product_count
          FROM categories WHERE parent_id = ? AND store_id = ? ORDER BY sort_order, name";
$stmt = $conn->prepare($query);
$stmt->execute([$category_id, $store_id]);
$children = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get products in this category
$query = "SELECT p.id, p.name, p.sku, p.price, p.sale_price, p.is_active, p.stock_quantity
          FROM products p
          INNER JOIN product_categories pc ON p.id = pc.product_id
          WHERE pc.category_id = ?
          ORDER BY p.name
          LIMIT 20";
$stmt = $conn->prepare($query);
$stmt->execute([$category_id]);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total product count
$query = "SELECT COUNT(*) as total FROM product_categories WHERE category_id = ?";
$stmt = $conn->prepare($query);
$stmt->execute([$category_id]);
$total_products = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Get category image (only for level 1, store-scoped path)
$category_image = null;
if ($category['level'] == 1) {
    $upload_dir = __DIR__ . "/../../uploads/stores/" . $store_id . "/categories/";
    foreach (['jpg', 'jpeg', 'png', 'gif', 'webp'] as $ext) {
        $image_path = $upload_dir . $category_id . '.' . $ext;
        if (file_exists($image_path)) {
            $category_image = "../uploads/stores/{$store_id}/categories/{$category_id}.{$ext}";
            break;
        }
    }
    if (!$category_image) {
        $legacy = __DIR__ . "/../../uploads/categories/{$category_id}.jpg";
        if (file_exists($legacy)) $category_image = "../uploads/categories/{$category_id}.jpg";
    }
}

// Build hierarchy path
$hierarchy_path = [];
$current = $category;
while ($current) {
    array_unshift($hierarchy_path, $current);
    if ($current['parent_id']) {
        $query = "SELECT * FROM categories WHERE id = ? AND store_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->execute([$current['parent_id'], $store_id]);
        $current = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $current = null;
    }
}
?>

<!-- Category View Details Page -->
<div class="category-view-container">
    <!-- Header Section -->
    <div class="category-view-header">
        <div class="header-left">
            <a href="?page=categories<?php echo (isset($_GET['content']) && $_GET['content'] === '1') ? '&content=1' : ''; ?>" class="back-btn">
                <i class="fas fa-arrow-left"></i> <?php echo $t('back_to_categories', 'Back to Categories'); ?>
            </a>
            <div class="category-title-section">
                <div class="title-row">
                    <h1 class="category-title" id="categoryNameDisplay"><?php echo htmlspecialchars($category['name']); ?></h1>
                    <span class="level-badge level-<?php echo $category['level']; ?>">
                        <?php echo $t('level'); ?> <?php echo $category['level']; ?>
                    </span>
                </div>
                <div class="category-meta-header">
                    <span class="slug-display" id="slugDisplay">
                        <i class="fas fa-link"></i> <?php echo htmlspecialchars($category['slug']); ?>
                    </span>
                    <?php if ($category['featured']): ?>
                        <span class="badge featured-badge-header">
                            <i class="fas fa-star"></i> <?php echo $t('featured'); ?>
                        </span>
                    <?php endif; ?>
                    <span class="badge status-badge-header <?php echo $category['is_active'] ? 'active' : 'inactive'; ?>">
                        <?php echo $category['is_active'] ? $t('active') : $t('inactive'); ?>
                    </span>
                </div>
            </div>
        </div>
        <div class="header-actions">
            <button class="btn-action secondary" onclick="editCategory(<?php echo $category['id']; ?>)">
                <i class="fas fa-edit"></i> <?php echo $t('edit_full'); ?>
            </button>
            <?php if (empty($children)): ?>
                <button class="btn-action danger" onclick="deleteCategory(<?php echo $category['id']; ?>)">
                    <i class="fas fa-trash"></i> <?php echo $t('delete'); ?>
                </button>
            <?php else: ?>
                <button class="btn-action danger disabled" title="<?php echo $t('cannot_delete_category_subcategories'); ?>" disabled>
                    <i class="fas fa-lock"></i> <?php echo $t('delete'); ?>
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Main Content -->
    <div class="category-view-content">
        <!-- Left Column: Image & Hierarchy -->
        <div class="category-view-left">
            <?php if ($category_image): ?>
                <div class="category-image-container">
                    <img src="<?php echo $category_image; ?>" 
                         alt="<?php echo htmlspecialchars($category['name']); ?>"
                         id="categoryImage">
                </div>
            <?php endif; ?>
            
            <!-- Hierarchy Tree -->
            <div class="detail-section">
                <div class="section-header">
                    <h2><i class="fas fa-sitemap"></i> <?php echo $t('hierarchy', 'Hierarchy'); ?></h2>
                </div>
                <div class="section-content">
                    <div class="hierarchy-path">
                        <?php foreach ($hierarchy_path as $index => $cat): ?>
                            <a href="?page=category_view_details&id=<?php echo $cat['id']; ?>" class="hierarchy-link">
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </a>
                            <?php if ($index < count($hierarchy_path) - 1): ?>
                                <i class="fas fa-chevron-right hierarchy-separator"></i>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if ($category['parent_id']): ?>
                        <div class="field-group">
                            <label><?php echo $t('parent_category'); ?></label>
                            <div class="readonly-field">
                                <a href="?page=category_view_details&id=<?php echo $category['parent_id']; ?>">
                                    <?php echo htmlspecialchars($category['parent_name']); ?>
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($children)): ?>
                        <div class="field-group">
                            <label><?php echo $t('subcategories'); ?> (<?php echo count($children); ?>)</label>
                            <div class="subcategories-list">
                                <?php foreach ($children as $child): ?>
                                    <a href="?page=category_view_details&id=<?php echo $child['id']; ?>" class="subcategory-item">
                                        <span class="subcategory-name"><?php echo htmlspecialchars($child['name']); ?></span>
                                        <span class="subcategory-meta">
                                            <span class="badge level-badge-small level-<?php echo $child['level']; ?>">L<?php echo $child['level']; ?></span>
                                            <span class="product-count"><?php echo $child['product_count']; ?> <?php echo $t('products', 'products'); ?></span>
                                        </span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right Column: Details -->
        <div class="category-view-details">
            <!-- Basic Information -->
            <div class="detail-section">
                <div class="section-header">
                    <h2><i class="fas fa-info-circle"></i> <?php echo $t('basic_information'); ?></h2>
                    <button class="edit-toggle-btn" onclick="toggleEdit('basic')">
                        <i class="fas fa-edit"></i> <?php echo $t('edit'); ?>
                    </button>
                </div>
                <div class="section-content">
                    <div class="field-group">
                        <label><?php echo $t('category_name'); ?></label>
                        <div class="editable-field" data-field="name" data-original="<?php echo htmlspecialchars($category['name']); ?>">
                            <span class="field-value"><?php echo htmlspecialchars($category['name']); ?></span>
                            <input type="text" class="field-input" value="<?php echo htmlspecialchars($category['name']); ?>" style="display: none;">
                        </div>
                    </div>
                    <div class="field-group">
                        <label><?php echo $t('url_slug'); ?></label>
                        <div class="editable-field" data-field="slug" data-original="<?php echo htmlspecialchars($category['slug']); ?>">
                            <span class="field-value"><?php echo htmlspecialchars($category['slug']); ?></span>
                            <input type="text" class="field-input" value="<?php echo htmlspecialchars($category['slug']); ?>" style="display: none;">
                        </div>
                    </div>
                    <div class="field-group">
                        <label><?php echo $t('sort_order'); ?></label>
                        <div class="editable-field" data-field="sort_order" data-original="<?php echo $category['sort_order']; ?>">
                            <span class="field-value"><?php echo $category['sort_order']; ?></span>
                            <input type="number" class="field-input" value="<?php echo $category['sort_order']; ?>" style="display: none;">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Description -->
            <div class="detail-section">
                <div class="section-header">
                    <h2><i class="fas fa-align-left"></i> <?php echo $t('description'); ?></h2>
                    <button class="edit-toggle-btn" onclick="toggleEdit('description')">
                        <i class="fas fa-edit"></i> <?php echo $t('edit'); ?>
                    </button>
                </div>
                <div class="section-content">
                    <div class="editable-field" data-field="description" data-original="<?php echo htmlspecialchars($category['description'] ?? ''); ?>">
                        <span class="field-value"><?php echo $category['description'] ? nl2br(htmlspecialchars($category['description'])) : '<em>' . $t('no_description_available') . '</em>'; ?></span>
                        <textarea class="field-input" style="display: none;"><?php echo htmlspecialchars($category['description'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Status -->
            <div class="detail-section">
                <div class="section-header">
                    <h2><i class="fas fa-toggle-on"></i> <?php echo $t('status'); ?></h2>
                </div>
                <div class="section-content">
                    <div class="field-group">
                        <label><?php echo $t('active_status', 'Active Status'); ?></label>
                        <div class="toggle-group">
                            <label class="switch">
                                <input type="checkbox" id="isActiveToggle" <?php echo $category['is_active'] ? 'checked' : ''; ?> onchange="updateToggle('is_active', this.checked)">
                                <span class="slider"></span>
                            </label>
                            <span><?php echo $t('is_active'); ?></span>
                        </div>
                    </div>
                    <div class="field-group">
                        <label><?php echo $t('featured'); ?></label>
                        <div class="toggle-group">
                            <label class="switch">
                                <input type="checkbox" id="featuredToggle" <?php echo $category['featured'] ? 'checked' : ''; ?> onchange="updateToggle('featured', this.checked)">
                                <span class="slider"></span>
                            </label>
                            <span><?php echo $t('featured_category'); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bottom Sections -->
    <div class="category-view-bottom">
        <!-- Products in Category -->
        <div class="detail-section full-width">
            <div class="section-header">
                <h2><i class="fas fa-box"></i> <?php echo $t('products_in_category', 'Products in Category'); ?></h2>
                <span class="product-count-badge"><?php echo $total_products; ?> <?php echo $t('products', 'products'); ?></span>
            </div>
            <div class="section-content">
                <?php if (!empty($products)): ?>
                    <div class="products-table">
                        <table>
                            <thead>
                                <tr>
                                    <th><?php echo $t('product_name'); ?></th>
                                    <th><?php echo $t('sku'); ?></th>
                                    <th><?php echo $t('price'); ?></th>
                                    <th><?php echo $t('stock'); ?></th>
                                    <th><?php echo $t('status'); ?></th>
                                    <th><?php echo $t('actions'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($products as $product): ?>
                                    <tr>
                                        <td>
                                            <a href="?page=product_quick_view&id=<?php echo $product['id']; ?>" class="product-link">
                                                <?php echo htmlspecialchars($product['name']); ?>
                                            </a>
                                        </td>
                                        <td><?php echo htmlspecialchars($product['sku']); ?></td>
                                        <td>
                                            <?php 
                                            $price = $product['sale_price'] ? $product['sale_price'] : $product['price'];
                                            echo $currency_position === 'left' ? $currency_symbol . number_format($price, 2) : number_format($price, 2) . ' ' . $currency_symbol;
                                            ?>
                                        </td>
                                        <td>
                                            <span class="stock-badge stock-<?php echo $product['stock_quantity'] == 0 ? 'out' : ($product['stock_quantity'] <= 10 ? 'low' : 'good'); ?>">
                                                <?php echo $product['stock_quantity']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $product['is_active'] ? 'active' : 'inactive'; ?>">
                                                <?php echo $product['is_active'] ? $t('active') : $t('inactive'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="?page=product_quick_view&id=<?php echo $product['id']; ?>" class="btn-link">
                                                <i class="fas fa-eye"></i> <?php echo $t('view'); ?>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php if ($total_products > 20): ?>
                            <div class="table-footer">
                                <p><?php echo $t('showing_products', 'Showing'); ?> 20 <?php echo $t('of_products', 'of'); ?> <?php echo $total_products; ?> <?php echo $t('products', 'products'); ?>. <a href="?page=products&category=<?php echo $category_id; ?>"><?php echo $t('view_all_products_category', 'View all products in this category'); ?></a></p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-box-open"></i>
                        <p><?php echo $t('no_products_category', 'No products in this category yet.'); ?></p>
                        <a href="?page=products" class="btn-action secondary"><?php echo $t('add_products', 'Add Products'); ?></a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- SEO & Metadata -->
        <div class="detail-section full-width">
            <div class="section-header">
                <h2><i class="fas fa-search"></i> <?php echo $t('seo_metadata'); ?></h2>
            </div>
            <div class="section-content">
                <div class="metadata-grid">
                    <div class="metadata-item">
                        <label><?php echo $t('meta_title'); ?></label>
                        <span><?php echo htmlspecialchars($category['meta_title'] ?? '—'); ?></span>
                    </div>
                    <div class="metadata-item">
                        <label><?php echo $t('meta_description'); ?></label>
                        <span><?php echo htmlspecialchars($category['meta_description'] ?? '—'); ?></span>
                    </div>
                    <div class="metadata-item">
                        <label><?php echo $t('created'); ?></label>
                        <span><?php echo date('M j, Y g:i A', strtotime($category['created_at'])); ?></span>
                    </div>
                    <div class="metadata-item">
                        <label><?php echo $t('last_updated'); ?></label>
                        <span><?php echo date('M j, Y g:i A', strtotime($category['updated_at'])); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Pass translations to JavaScript
window.translations = {
    edit: <?php echo json_encode($t('edit')); ?>,
    cancel: <?php echo json_encode($t('cancel')); ?>,
    saving: <?php echo json_encode($t('saving')); ?>,
    field_updated_successfully: <?php echo json_encode($t('field_updated_successfully')); ?>,
    failed_to_update_field: <?php echo json_encode($t('failed_to_update_field')); ?>,
    error_updating_field: <?php echo json_encode($t('error_updating_field')); ?>,
    status_updated_successfully: <?php echo json_encode($t('status_updated_successfully')); ?>,
    failed_to_update_status: <?php echo json_encode($t('failed_to_update_status')); ?>,
    error_updating_status: <?php echo json_encode($t('error_updating_status')); ?>,
    are_you_sure: <?php echo json_encode($t('are_you_sure')); ?>,
    delete_category: <?php echo json_encode($t('delete_category')); ?>,
    active: <?php echo json_encode($t('active')); ?>,
    inactive: <?php echo json_encode($t('inactive')); ?>,
    no_description_available: <?php echo json_encode($t('no_description_available')); ?>
};

const categoryId = <?php echo $category['id']; ?>;
let editingField = null;

function toggleEdit(section) {
    const sectionEl = event.target.closest('.detail-section');
    const editBtn = sectionEl.querySelector('.edit-toggle-btn');
    const fields = sectionEl.querySelectorAll('.editable-field');
    
    if (editingField) {
        cancelEdit();
    }
    
    fields.forEach(field => {
        const valueEl = field.querySelector('.field-value');
        const inputEl = field.querySelector('.field-input');
        
        if (valueEl && inputEl) {
            valueEl.style.display = 'none';
            inputEl.style.display = 'block';
            if (inputEl.tagName === 'INPUT' || inputEl.tagName === 'TEXTAREA') {
                inputEl.focus();
            }
        }
    });
    
    editBtn.innerHTML = '<i class="fas fa-times"></i> ' + window.translations.cancel;
    editBtn.onclick = cancelEdit;
    editingField = section;
}

function cancelEdit() {
    if (!editingField) return;
    
    const sections = document.querySelectorAll('.detail-section');
    sections.forEach(section => {
        const fields = section.querySelectorAll('.editable-field');
        fields.forEach(field => {
            const valueEl = field.querySelector('.field-value');
            const inputEl = field.querySelector('.field-input');
            const original = field.getAttribute('data-original');
            
            if (valueEl && inputEl) {
                if (inputEl.tagName === 'TEXTAREA') {
                    inputEl.value = original;
                    valueEl.innerHTML = original ? original.replace(/\n/g, '<br>') : '<em>' + window.translations.no_description_available + '</em>';
                } else {
                    inputEl.value = original;
                    valueEl.textContent = original;
                }
                valueEl.style.display = 'block';
                inputEl.style.display = 'none';
            }
        });
        
        const editBtn = section.querySelector('.edit-toggle-btn');
        if (editBtn) {
            editBtn.innerHTML = '<i class="fas fa-edit"></i> ' + window.translations.edit;
            editBtn.onclick = function() { toggleEdit(editingField); };
        }
    });
    
    editingField = null;
}

// Save field on Enter or blur
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.field-input').forEach(input => {
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey && this.tagName !== 'TEXTAREA') {
                e.preventDefault();
                saveField(this);
            }
            if (e.key === 'Escape') {
                cancelEdit();
            }
        });
        
        input.addEventListener('blur', function() {
            if (editingField) {
                saveField(this);
            }
        });
    });
});

function saveField(inputEl) {
    const field = inputEl.closest('.editable-field');
    const fieldName = field.getAttribute('data-field');
    const value = inputEl.value;
    const valueEl = field.querySelector('.field-value');
    
    // Show loading
    const originalValue = valueEl.textContent || valueEl.innerHTML;
    valueEl.textContent = window.translations.saving;
    
    fetch('?page=categories', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=quick_update_category&category_id=${categoryId}&field=${fieldName}&value=${encodeURIComponent(value)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update display value
            if (fieldName === 'name') {
                valueEl.textContent = value;
                document.getElementById('categoryNameDisplay').textContent = value;
            } else if (fieldName === 'slug') {
                valueEl.textContent = value;
                document.getElementById('slugDisplay').innerHTML = '<i class="fas fa-link"></i> ' + value;
            } else if (fieldName === 'description') {
                valueEl.innerHTML = value ? value.replace(/\n/g, '<br>') : '<em>' + window.translations.no_description_available + '</em>';
            } else {
                valueEl.textContent = value;
            }
            
            // Update original value
            field.setAttribute('data-original', value);
            
            // Hide input, show value
            inputEl.style.display = 'none';
            valueEl.style.display = 'block';
            
            // Reset edit button
            const section = field.closest('.detail-section');
            const editBtn = section.querySelector('.edit-toggle-btn');
            if (editBtn) {
                editBtn.innerHTML = '<i class="fas fa-edit"></i> ' + window.translations.edit;
                editBtn.onclick = function() { toggleEdit(editingField); };
            }
            
            editingField = null;
            
            showNotification(window.translations.field_updated_successfully, 'success');
        } else {
            if (fieldName === 'description') {
                valueEl.innerHTML = originalValue;
            } else {
                valueEl.textContent = originalValue;
            }
            showNotification(data.message || window.translations.failed_to_update_field, 'error');
        }
    })
    .catch(error => {
        if (fieldName === 'description') {
            valueEl.innerHTML = originalValue;
        } else {
            valueEl.textContent = originalValue;
        }
        showNotification(window.translations.error_updating_field, 'error');
        console.error('Error:', error);
    });
}

function updateToggle(field, value) {
    fetch('?page=categories', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=quick_update_category&category_id=${categoryId}&field=${field}&value=${value ? 1 : 0}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (field === 'is_active') {
                const badge = document.querySelector('.status-badge-header');
                if (badge) {
                    badge.textContent = value ? window.translations.active : window.translations.inactive;
                    badge.className = 'badge status-badge-header ' + (value ? 'active' : 'inactive');
                }
            }
            showNotification(window.translations.status_updated_successfully, 'success');
        } else {
            // Revert toggle
            const toggle = document.getElementById(field + 'Toggle');
            if (toggle) toggle.checked = !value;
            showNotification(data.message || window.translations.failed_to_update_status, 'error');
        }
    })
    .catch(error => {
        const toggle = document.getElementById(field + 'Toggle');
        if (toggle) toggle.checked = !value;
        showNotification(window.translations.error_updating_status, 'error');
        console.error('Error:', error);
    });
}

function editCategory(id) {
    sessionStorage.setItem('editCategoryId', id);
    window.location.href = '?page=categories';
}

function deleteCategory(id) {
    if (confirm(window.translations.are_you_sure + ' ' + window.translations.delete_category + '?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '?page=categories';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="category_id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function showNotification(message, type) {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
        <span>${message}</span>
    `;
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.classList.add('show');
    }, 10);
    
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}
</script>

<style>
.category-view-container {
    padding: 2rem;
    max-width: 1400px;
    margin: 0 auto;
}

.category-view-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 2rem;
    margin-bottom: 2rem;
    padding-bottom: 1.5rem;
    border-bottom: 2px solid var(--border-primary);
}

.header-left {
    flex: 1;
}

.back-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--text-secondary);
    text-decoration: none;
    margin-bottom: 1rem;
    transition: color 0.2s ease;
}

.back-btn:hover {
    color: var(--color-primary);
}

.title-row {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 0.5rem;
}

.category-title-section h1 {
    margin: 0;
    font-size: 2rem;
    font-weight: 700;
    color: var(--text-primary);
}

.level-badge {
    padding: 0.375rem 0.75rem;
    border-radius: 6px;
    font-size: 0.8125rem;
    font-weight: 700;
    color: var(--text-inverse);
}

.level-badge.level-1 {
    background: var(--color-info);
}

.level-badge.level-2 {
    background: var(--color-success);
}

.level-badge.level-3 {
    background: var(--color-warning);
}

.level-badge.level-4 {
    background: var(--color-accent-dark);
}

.category-meta-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
}

.slug-display {
    color: var(--text-secondary);
    font-size: 0.9rem;
}

.badge {
    padding: 0.375rem 0.75rem;
    border-radius: 6px;
    font-size: 0.8125rem;
    font-weight: 600;
}

.featured-badge-header {
    background: var(--color-warning-light);
    color: var(--color-warning-dark);
}

.status-badge-header.active {
    background: var(--color-success-light);
    color: var(--color-success-dark);
}

.status-badge-header.inactive {
    background: var(--color-error-light);
    color: var(--color-error-dark);
}

.header-actions {
    display: flex;
    gap: 0.75rem;
}

.btn-action {
    padding: 0.75rem 1.5rem;
    border-radius: 6px;
    border: none;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.2s ease;
}

.btn-action.secondary {
    background: var(--bg-secondary);
    color: var(--text-primary);
    border: 1px solid var(--border-primary);
}

.btn-action.secondary:hover {
    background: var(--bg-tertiary);
}

.btn-action.danger {
    background: var(--color-error);
    color: var(--text-inverse);
}

.btn-action.danger:hover {
    background: var(--color-error-hover);
}

.btn-action.danger.disabled {
    background: var(--bg-tertiary);
    color: var(--text-secondary);
    cursor: not-allowed;
    opacity: 0.6;
}

.category-view-content {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2rem;
    margin-bottom: 2rem;
}

.category-view-left {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
    position: sticky;
    top: 1rem;
    align-self: start;
    max-height: calc(100vh - 2rem);
    overflow-y: auto;
}

.category-image-container {
    width: 100%;
    aspect-ratio: 1;
    border-radius: 6px;
    overflow: hidden;
    background: var(--bg-secondary);
    border: 1px solid var(--border-primary);
}

.category-image-container img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.category-view-details {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.detail-section {
    background: var(--bg-card);
    border: 1px solid var(--border-primary);
    border-radius: 6px;
    padding: 1.5rem;
}

.detail-section.full-width {
    grid-column: 1 / -1;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--border-primary);
}

.section-header h2 {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.product-count-badge {
    padding: 0.5rem 1rem;
    border-radius: 6px;
    background: var(--color-primary-light);
    color: var(--color-primary-dark);
    font-weight: 600;
    font-size: 0.875rem;
}

.edit-toggle-btn {
    padding: 0.5rem 1rem;
    border-radius: 6px;
    border: 1px solid var(--border-primary);
    background: var(--bg-secondary);
    color: var(--text-primary);
    cursor: pointer;
    font-size: 0.875rem;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.2s ease;
}

.edit-toggle-btn:hover {
    background: var(--bg-tertiary);
    border-color: var(--color-primary);
}

.section-content {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.field-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.field-group label {
    font-weight: 600;
    color: var(--text-secondary);
    font-size: 0.875rem;
}

.editable-field {
    position: relative;
    min-height: 2rem;
}

.field-value {
    display: block;
    padding: 0.75rem;
    border-radius: 6px;
    background: var(--bg-secondary);
    color: var(--text-primary);
    cursor: pointer;
    transition: background 0.2s ease;
}

.field-value:hover {
    background: var(--bg-tertiary);
}

.field-input {
    width: 100%;
    padding: 0.75rem;
    border: 2px solid var(--border-primary);
    border-radius: 6px;
    background: var(--bg-card);
    color: var(--text-primary);
    font-size: 1rem;
    font-family: inherit;
}

.field-input:focus {
    outline: none;
    border-color: var(--color-primary);
    box-shadow: 0 0 0 3px var(--color-primary-light);
}

.readonly-field {
    padding: 0.75rem;
    border-radius: 6px;
    background: var(--bg-secondary);
    color: var(--text-primary);
    font-size: 0.9rem;
}

.readonly-field a {
    color: var(--color-primary);
    text-decoration: none;
}

.readonly-field a:hover {
    text-decoration: underline;
}

.toggle-group {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.switch {
    position: relative;
    display: inline-block;
    width: 44px;
    height: 24px;
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
    background-color: var(--border-secondary);
    transition: 0.3s;
    border-radius: 12px;
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
    background-color: var(--color-success);
}

input:checked + .slider:before {
    transform: translateX(20px);
}

.category-view-bottom {
    display: grid;
    gap: 1.5rem;
}

.hierarchy-path {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
    padding: 1rem;
    border-radius: 6px;
    background: var(--bg-secondary);
}

.hierarchy-link {
    color: var(--color-primary);
    text-decoration: none;
    font-weight: 500;
    transition: color 0.2s ease;
}

.hierarchy-link:hover {
    color: var(--color-primary-dark);
    text-decoration: underline;
}

.hierarchy-separator {
    color: var(--text-tertiary);
    font-size: 0.75rem;
}

.subcategories-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.subcategory-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem;
    border-radius: 6px;
    background: var(--bg-secondary);
    text-decoration: none;
    color: var(--text-primary);
    transition: background 0.2s ease;
}

.subcategory-item:hover {
    background: var(--bg-tertiary);
}

.subcategory-name {
    font-weight: 500;
}

.subcategory-meta {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.level-badge-small {
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--text-inverse);
}

.product-count {
    font-size: 0.875rem;
    color: var(--text-secondary);
}

.products-table {
    overflow-x: auto;
}

.products-table table {
    width: 100%;
    border-collapse: collapse;
}

.products-table th,
.products-table td {
    padding: 0.75rem;
    text-align: left;
    border-bottom: 1px solid var(--border-primary);
}

.products-table th {
    font-weight: 600;
    color: var(--text-secondary);
    background: var(--bg-secondary);
}

.products-table td {
    color: var(--text-primary);
}

.product-link {
    color: var(--color-primary);
    text-decoration: none;
    font-weight: 500;
}

.product-link:hover {
    text-decoration: underline;
}

.stock-badge {
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.875rem;
    font-weight: 600;
}

.stock-badge.stock-good {
    background: var(--color-success-light);
    color: var(--color-success-dark);
}

.stock-badge.stock-low {
    background: var(--color-warning-light);
    color: var(--color-warning-dark);
}

.stock-badge.stock-out {
    background: var(--color-error-light);
    color: var(--color-error-dark);
}

.btn-link {
    color: var(--color-primary);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.875rem;
}

.btn-link:hover {
    text-decoration: underline;
}

.table-footer {
    padding: 1rem;
    text-align: center;
    color: var(--text-secondary);
}

.table-footer a {
    color: var(--color-primary);
    text-decoration: none;
}

.table-footer a:hover {
    text-decoration: underline;
}

.empty-state {
    text-align: center;
    padding: 3rem 2rem;
    color: var(--text-secondary);
}

.empty-state i {
    font-size: 3rem;
    margin-bottom: 1rem;
    color: var(--text-tertiary);
}

.empty-state p {
    margin-bottom: 1.5rem;
}

.metadata-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
}

.metadata-item {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.metadata-item label {
    font-weight: 600;
    color: var(--text-secondary);
    font-size: 0.875rem;
}

.metadata-item span {
    color: var(--text-primary);
    padding: 0.5rem;
    border-radius: 6px;
    background: var(--bg-secondary);
}

.notification {
    position: fixed;
    top: 2rem;
    right: 2rem;
    padding: 1rem 1.5rem;
    border-radius: 6px;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    box-shadow: var(--shadow-lg);
    z-index: 10000;
    opacity: 0;
    transform: translateX(100%);
    transition: all 0.3s ease;
}

.notification.show {
    opacity: 1;
    transform: translateX(0);
}

.notification-success {
    background: var(--color-success);
    color: var(--text-inverse);
}

.notification-error {
    background: var(--color-error);
    color: var(--text-inverse);
}

@media (max-width: 1024px) {
    .category-view-content {
        grid-template-columns: 1fr;
    }
    
    .category-view-header {
        flex-direction: column;
    }
    
    .header-actions {
        width: 100%;
    }
    
    .btn-action {
        flex: 1;
        justify-content: center;
    }
}
</style>

