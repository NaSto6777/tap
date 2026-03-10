<?php
require_once __DIR__ . '/../../config/StoreContext.php';
require_once __DIR__ . '/../../config/settings.php';
require_once __DIR__ . '/../../config/language.php';

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

// Get product ID
$product_id = $_GET['id'] ?? 0;

if (!$product_id) {
    $back = '?page=products';
    if (isset($_GET['content']) && $_GET['content'] === '1') $back .= '&content=1';
    header('Location: ' . $back);
    exit;
}

// Get product data (scoped to store)
$query = "SELECT * FROM products WHERE id = ? AND store_id = ?";
$stmt = $conn->prepare($query);
$stmt->execute([$product_id, $store_id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    $back = '?page=products&error=' . urlencode($t('product_not_found'));
    if (isset($_GET['content']) && $_GET['content'] === '1') $back .= '&content=1';
    header('Location: ' . $back);
    exit;
}

// Get product categories
$query = "SELECT c.id, c.name FROM categories c 
          INNER JOIN product_categories pc ON c.id = pc.category_id 
          WHERE pc.product_id = ?";
$stmt = $conn->prepare($query);
$stmt->execute([$product_id]);
$product_categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get product variants
$variants = [];
try {
    $query = "SELECT id, label, sku, price, stock_quantity, is_active FROM product_variants WHERE product_id = ? ORDER BY id";
    $stmt = $conn->prepare($query);
    $stmt->execute([$product_id]);
    $variants = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $variants = [];
}

// Get product images
$upload_dir = __DIR__ . "/../../uploads/products/" . $product_id . "/";
$images = [];
$main_image = "../uploads/placeholder.jpg";

if (is_dir($upload_dir)) {
    $files = glob($upload_dir . "*.*");
    foreach ($files as $file) {
        if (basename($file) !== 'main.jpg') {
            $images[] = str_replace(__DIR__ . "/../../", "../", $file);
        }
    }
    
    // Check for main image
    $main_image_path = $upload_dir . 'main.jpg';
    if (file_exists($main_image_path)) {
        $main_image = "../uploads/products/{$product_id}/main.jpg";
    }
}

$stock_status = $product['stock_quantity'] == 0 ? 'out' : ($product['stock_quantity'] <= 10 ? 'low' : 'good');
$discount = $product['sale_price'] ? round((($product['price'] - $product['sale_price']) / $product['price']) * 100) : 0;
?>

<!-- Product Quick View Page -->
<div class="quick-view-container">
    <!-- Header Section -->
    <div class="quick-view-header">
        <div class="header-left">
            <a href="?page=products<?php echo (isset($_GET['content']) && $_GET['content'] === '1') ? '&content=1' : ''; ?>" class="back-btn">
                <i class="fas fa-arrow-left"></i> <?php echo $t('back_to_products'); ?>
            </a>
            <div class="product-title-section">
                <h1 class="product-title" id="productNameDisplay"><?php echo htmlspecialchars($product['name']); ?></h1>
                <div class="product-meta-header">
                    <span class="sku-display" id="skuDisplay">
                        <i class="fas fa-barcode"></i> <?php echo htmlspecialchars($product['sku']); ?>
                    </span>
                    <?php if ($product['featured']): ?>
                        <span class="badge featured-badge-header">
                            <i class="fas fa-star"></i> <?php echo $t('featured'); ?>
                        </span>
                    <?php endif; ?>
                    <span class="badge status-badge-header <?php echo $product['is_active'] ? 'active' : 'inactive'; ?>">
                        <?php echo $product['is_active'] ? $t('active') : $t('inactive'); ?>
                    </span>
                </div>
            </div>
        </div>
        <div class="header-actions">
            <button class="btn-action secondary" onclick="editProduct(<?php echo $product['id']; ?>)">
                <i class="fas fa-edit"></i> <?php echo $t('edit_full'); ?>
            </button>
            <button class="btn-action danger" onclick="deleteProduct(<?php echo $product['id']; ?>)">
                <i class="fas fa-trash"></i> <?php echo $t('delete'); ?>
            </button>
        </div>
    </div>

    <!-- Main Content -->
    <div class="quick-view-content">
        <!-- Left Column: Images -->
        <div class="quick-view-images">
            <div class="main-image-container">
                <img src="<?php echo $main_image; ?>" 
                     onerror="this.src='../uploads/placeholder.jpg'" 
                     alt="<?php echo htmlspecialchars($product['name']); ?>"
                     id="mainProductImage">
                <?php if ($discount > 0): ?>
                    <div class="discount-badge-overlay">-<?php echo $discount; ?>%</div>
                <?php endif; ?>
            </div>
            <?php if (!empty($images)): ?>
                <div class="image-thumbnails">
                    <?php foreach ($images as $img): ?>
                        <div class="thumbnail-item" onclick="changeMainImage('<?php echo htmlspecialchars($img); ?>')">
                            <img src="<?php echo htmlspecialchars($img); ?>" alt="Thumbnail">
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Right Column: Details -->
        <div class="quick-view-details">
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
                        <label><?php echo $t('product_name'); ?></label>
                        <div class="editable-field" data-field="name" data-original="<?php echo htmlspecialchars($product['name']); ?>">
                            <span class="field-value"><?php echo htmlspecialchars($product['name']); ?></span>
                            <input type="text" class="field-input" value="<?php echo htmlspecialchars($product['name']); ?>" style="display: none;">
                        </div>
                    </div>
                    <div class="field-group">
                        <label>SKU</label>
                        <div class="editable-field" data-field="sku" data-original="<?php echo htmlspecialchars($product['sku']); ?>">
                            <span class="field-value"><?php echo htmlspecialchars($product['sku']); ?></span>
                            <input type="text" class="field-input" value="<?php echo htmlspecialchars($product['sku']); ?>" style="display: none;">
                        </div>
                    </div>
                    <div class="field-group">
                        <label><?php echo $t('url_slug'); ?></label>
                        <div class="readonly-field">
                            <span><?php echo htmlspecialchars($product['slug']); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pricing -->
            <div class="detail-section">
                <div class="section-header">
                    <h2><i class="fas fa-dollar-sign"></i> <?php echo $t('pricing'); ?></h2>
                    <button class="edit-toggle-btn" onclick="toggleEdit('pricing')">
                        <i class="fas fa-edit"></i> <?php echo $t('edit'); ?>
                    </button>
                </div>
                <div class="section-content">
                    <div class="field-group">
                        <label><?php echo $t('regular_price'); ?></label>
                        <div class="editable-field" data-field="price" data-original="<?php echo $product['price']; ?>">
                            <span class="field-value"><?php echo $currency_position === 'left' ? $currency_symbol . number_format($product['price'], 2) : number_format($product['price'], 2) . ' ' . $currency_symbol; ?></span>
                            <input type="number" step="0.01" class="field-input" value="<?php echo $product['price']; ?>" style="display: none;">
                        </div>
                    </div>
                    <div class="field-group">
                        <label><?php echo $t('sale_price'); ?></label>
                        <div class="editable-field" data-field="sale_price" data-original="<?php echo $product['sale_price'] ?? ''; ?>">
                            <span class="field-value"><?php echo $product['sale_price'] ? ($currency_position === 'left' ? $currency_symbol . number_format($product['sale_price'], 2) : number_format($product['sale_price'], 2) . ' ' . $currency_symbol) : '—'; ?></span>
                            <input type="number" step="0.01" class="field-input" value="<?php echo $product['sale_price'] ?? ''; ?>" style="display: none;">
                        </div>
                    </div>
                    <div class="field-group">
                        <label><?php echo $t('cost_price'); ?></label>
                        <div class="editable-field" data-field="cost_price" data-original="<?php echo $product['cost_price'] ?? ''; ?>">
                            <span class="field-value"><?php echo $product['cost_price'] ? ($currency_position === 'left' ? $currency_symbol . number_format($product['cost_price'], 2) : number_format($product['cost_price'], 2) . ' ' . $currency_symbol) : '—'; ?></span>
                            <input type="number" step="0.01" class="field-input" value="<?php echo $product['cost_price'] ?? ''; ?>" style="display: none;">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stock & Status -->
            <div class="detail-section">
                <div class="section-header">
                    <h2><i class="fas fa-box"></i> <?php echo $t('stock_status'); ?></h2>
                    <button class="edit-toggle-btn" onclick="toggleEdit('stock')">
                        <i class="fas fa-edit"></i> <?php echo $t('edit'); ?>
                    </button>
                </div>
                <div class="section-content">
                    <div class="field-group">
                        <label><?php echo $t('stock_quantity'); ?></label>
                        <div class="editable-field" data-field="stock_quantity" data-original="<?php echo $product['stock_quantity']; ?>">
                            <span class="field-value stock-<?php echo $stock_status; ?>"><?php echo $product['stock_quantity']; ?> <?php echo $t('in_stock'); ?></span>
                            <input type="number" class="field-input" value="<?php echo $product['stock_quantity']; ?>" style="display: none;">
                        </div>
                    </div>
                    <div class="field-group">
                        <label><?php echo $t('status'); ?></label>
                        <div class="toggle-group">
                            <label class="switch">
                                <input type="checkbox" id="isActiveToggle" <?php echo $product['is_active'] ? 'checked' : ''; ?> onchange="updateToggle('is_active', this.checked)">
                                <span class="slider"></span>
                            </label>
                            <span><?php echo $t('product_is_active'); ?></span>
                        </div>
                    </div>
                    <div class="field-group">
                        <label><?php echo $t('featured'); ?></label>
                        <div class="toggle-group">
                            <label class="switch">
                                <input type="checkbox" id="featuredToggle" <?php echo $product['featured'] ? 'checked' : ''; ?> onchange="updateToggle('featured', this.checked)">
                                <span class="slider"></span>
                            </label>
                            <span><?php echo $t('featured_product'); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Short Description -->
            <div class="detail-section">
                <div class="section-header">
                    <h2><i class="fas fa-align-left"></i> <?php echo $t('short_description'); ?></h2>
                    <button class="edit-toggle-btn" onclick="toggleEdit('short_description')">
                        <i class="fas fa-edit"></i> <?php echo $t('edit'); ?>
                    </button>
                </div>
                <div class="section-content">
                    <div class="editable-field" data-field="short_description" data-original="<?php echo htmlspecialchars($product['short_description'] ?? ''); ?>">
                        <span class="field-value"><?php echo htmlspecialchars($product['short_description'] ?? $t('no_description')); ?></span>
                        <textarea class="field-input" style="display: none;"><?php echo htmlspecialchars($product['short_description'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bottom Sections -->
    <div class="quick-view-bottom">
        <!-- Full Description -->
        <div class="detail-section full-width">
            <div class="section-header">
                <h2><i class="fas fa-file-alt"></i> <?php echo $t('full_description'); ?></h2>
            </div>
            <div class="section-content">
                <div class="description-content" id="descriptionContent">
                    <?php echo $product['description'] ? nl2br(htmlspecialchars($product['description'])) : '<em>' . $t('no_description_available') . '</em>'; ?>
                </div>
            </div>
        </div>

        <!-- Variants -->
        <?php if (!empty($variants)): ?>
        <div class="detail-section full-width">
            <div class="section-header">
                <h2><i class="fas fa-list"></i> <?php echo $t('product_variants'); ?></h2>
                <button class="btn-action secondary" onclick="editProduct(<?php echo $product['id']; ?>)">
                    <i class="fas fa-edit"></i> <?php echo $t('edit_variants'); ?>
                </button>
            </div>
            <div class="section-content">
                <div class="variants-table">
                    <table>
                        <thead>
                            <tr>
                                <th><?php echo $t('variant_label'); ?></th>
                                <th><?php echo $t('sku'); ?></th>
                                <th><?php echo $t('price'); ?></th>
                                <th><?php echo $t('stock'); ?></th>
                                <th><?php echo $t('status'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($variants as $variant): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($variant['label']); ?></td>
                                    <td><?php echo htmlspecialchars($variant['sku'] ?? '—'); ?></td>
                                    <td><?php echo $variant['price'] ? ($currency_position === 'left' ? $currency_symbol . number_format($variant['price'], 2) : number_format($variant['price'], 2) . ' ' . $currency_symbol) : '—'; ?></td>
                                    <td><?php echo $variant['stock_quantity']; ?></td>
                                    <td>
                                        <span class="badge <?php echo $variant['is_active'] ? 'active' : 'inactive'; ?>">
                                            <?php echo $variant['is_active'] ? $t('active') : $t('inactive'); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Categories -->
        <?php if (!empty($product_categories)): ?>
        <div class="detail-section full-width">
            <div class="section-header">
                <h2><i class="fas fa-tags"></i> <?php echo $t('categories_tab'); ?></h2>
            </div>
            <div class="section-content">
                <div class="categories-list">
                    <?php foreach ($product_categories as $cat): ?>
                        <span class="category-tag"><?php echo htmlspecialchars($cat['name']); ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- SEO & Metadata -->
        <div class="detail-section full-width">
            <div class="section-header">
                <h2><i class="fas fa-search"></i> <?php echo $t('seo_metadata'); ?></h2>
            </div>
            <div class="section-content">
                <div class="metadata-grid">
                    <div class="metadata-item">
                        <label><?php echo $t('meta_title'); ?></label>
                        <span><?php echo htmlspecialchars($product['meta_title'] ?? '—'); ?></span>
                    </div>
                    <div class="metadata-item">
                        <label><?php echo $t('meta_description'); ?></label>
                        <span><?php echo htmlspecialchars($product['meta_description'] ?? '—'); ?></span>
                    </div>
                    <div class="metadata-item">
                        <label><?php echo $t('created'); ?></label>
                        <span><?php echo date('M j, Y g:i A', strtotime($product['created_at'])); ?></span>
                    </div>
                    <div class="metadata-item">
                        <label><?php echo $t('last_updated'); ?></label>
                        <span><?php echo date('M j, Y g:i A', strtotime($product['updated_at'])); ?></span>
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
    delete_product: <?php echo json_encode($t('delete_product')); ?>,
    active: <?php echo json_encode($t('active')); ?>,
    inactive: <?php echo json_encode($t('inactive')); ?>,
    in_stock: <?php echo json_encode($t('in_stock')); ?>
};

const productId = <?php echo $product['id']; ?>;
const currencySymbol = '<?php echo addslashes($currency_symbol); ?>';
const currencyPosition = '<?php echo $currency_position; ?>';
let editingField = null;

function changeMainImage(src) {
    document.getElementById('mainProductImage').src = src;
}

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
                inputEl.value = original;
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
    const originalValue = valueEl.textContent;
    valueEl.textContent = window.translations.saving;
    
    var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).getAttribute?.('content') || '';
    var body = 'action=quick_update_product&product_id=' + productId + '&field=' + encodeURIComponent(fieldName) + '&value=' + encodeURIComponent(value);
    if (csrf) body += '&csrf_token=' + encodeURIComponent(csrf);
    fetch('?page=products', { method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: body })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update display value
            if (fieldName.includes('price') || fieldName === 'cost_price' || fieldName === 'sale_price') {
                const numValue = parseFloat(value) || 0;
                valueEl.textContent = currencyPosition === 'left' 
                    ? currencySymbol + numValue.toFixed(2) 
                    : numValue.toFixed(2) + ' ' + currencySymbol;
            } else if (fieldName === 'stock_quantity') {
                const stockStatus = value == 0 ? 'out' : (value <= 10 ? 'low' : 'good');
                valueEl.textContent = value + ' ' + window.translations.in_stock;
                valueEl.className = 'field-value stock-' + stockStatus;
            } else if (fieldName === 'name') {
                valueEl.textContent = value;
                document.getElementById('productNameDisplay').textContent = value;
            } else {
                valueEl.textContent = value || '—';
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
            valueEl.textContent = originalValue;
            showNotification(data.message || window.translations.failed_to_update_field, 'error');
        }
    })
    .catch(error => {
        valueEl.textContent = originalValue;
        showNotification(window.translations.error_updating_field, 'error');
        console.error('Error:', error);
    });
}

function updateToggle(field, value) {
    var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).getAttribute?.('content') || '';
    var body = 'action=quick_update_product&product_id=' + productId + '&field=' + encodeURIComponent(field) + '&value=' + (value ? 1 : 0);
    if (csrf) body += '&csrf_token=' + encodeURIComponent(csrf);
    fetch('?page=products', { method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: body })
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

function editProduct(id) {
    sessionStorage.setItem('editProductId', id);
    var url = '?page=products';
    if (window.self !== window.top) url += '&content=1';
    window.location.href = url;
}

function deleteProduct(id) {
    if (confirm(window.translations.are_you_sure + ' ' + window.translations.delete_product + '?')) {
        var action = '?page=products';
        if (window.self !== window.top) action += '&content=1';
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = action;
        var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).getAttribute?.('content') || '';
        form.innerHTML = '<input type="hidden" name="action" value="delete"><input type="hidden" name="product_id" value="' + id + '">' +
            (csrf ? '<input type="hidden" name="csrf_token" value="' + csrf.replace(/"/g, '&quot;') + '">' : '');
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
.quick-view-container {
    padding: 2rem;
    max-width: 1400px;
    margin: 0 auto;
}

.quick-view-header {
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

.product-title-section h1 {
    margin: 0 0 0.5rem 0;
    font-size: 2rem;
    font-weight: 700;
    color: var(--text-primary);
}

.product-meta-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
}

.sku-display {
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

.quick-view-content {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2rem;
    margin-bottom: 2rem;
}

.quick-view-images {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    position: sticky;
    top: 1rem;
    align-self: start;
    max-height: calc(100vh - 2rem);
    overflow-y: auto;
}

.main-image-container {
    position: relative;
    width: 100%;
    aspect-ratio: 1;
    border-radius: 6px;
    overflow: hidden;
    background: var(--bg-secondary);
    border: 1px solid var(--border-primary);
}

.main-image-container img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.discount-badge-overlay {
    position: absolute;
    top: 1rem;
    left: 1rem;
    background: var(--color-error);
    color: var(--text-inverse);
    padding: 0.5rem 1rem;
    border-radius: 6px;
    font-weight: 700;
    font-size: 0.875rem;
}

.image-thumbnails {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
    gap: 0.75rem;
}

.thumbnail-item {
    aspect-ratio: 1;
    border-radius: 6px;
    overflow: hidden;
    cursor: pointer;
    border: 2px solid var(--border-primary);
    transition: border-color 0.2s ease;
}

.thumbnail-item:hover {
    border-color: var(--color-primary);
}

.thumbnail-item img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.quick-view-details {
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

.field-value.stock-good {
    color: var(--color-success);
}

.field-value.stock-low {
    color: var(--color-warning);
}

.field-value.stock-out {
    color: var(--color-error);
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
    color: var(--text-secondary);
    font-size: 0.9rem;
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

.quick-view-bottom {
    display: grid;
    gap: 1.5rem;
}

.description-content {
    padding: 1rem;
    border-radius: 6px;
    background: var(--bg-secondary);
    color: var(--text-primary);
    line-height: 1.6;
}

.variants-table {
    overflow-x: auto;
}

.variants-table table {
    width: 100%;
    border-collapse: collapse;
}

.variants-table th,
.variants-table td {
    padding: 0.75rem;
    text-align: left;
    border-bottom: 1px solid var(--border-primary);
}

.variants-table th {
    font-weight: 600;
    color: var(--text-secondary);
    background: var(--bg-secondary);
}

.variants-table td {
    color: var(--text-primary);
}

.categories-list {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.category-tag {
    padding: 0.5rem 1rem;
    border-radius: 6px;
    background: var(--color-primary-light);
    color: var(--color-primary-dark);
    font-size: 0.875rem;
    font-weight: 500;
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
    .quick-view-content {
        grid-template-columns: 1fr;
    }
    
    .quick-view-header {
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

