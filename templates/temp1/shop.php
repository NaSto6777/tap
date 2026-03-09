<?php
$store_id = StoreContext::getId();
$database = new Database();
$conn = $database->getConnection();

// Get currency settings
$currency = $settings->getSetting('currency', 'USD');
$currency_symbol = $currency === 'USD' ? '$' : ($currency === 'EUR' ? '€' : ($currency === 'GBP' ? '£' : ($currency === 'TND' ? 'TND' : $currency . ' ')));
$rightPositionCurrencies = ['TND','AED','SAR','QAR','KWD','BHD','OMR','MAD','DZD','EGP'];
$price_position_right = in_array($currency, $rightPositionCurrencies, true);
$price_prefix = $price_position_right ? '' : ($currency_symbol);
$price_suffix = $price_position_right ? (' ' . $currency_symbol) : '';

// Categories setting
$categories_enabled = $settings->getSetting('categories_enabled', '1');

// Get categories only if enabled
$categories = [];
if ($categories_enabled === '1') {
    $query = "SELECT * FROM categories WHERE store_id = ? AND is_active = 1 AND parent_id IS NULL ORDER BY sort_order, name";
    $stmt = $conn->prepare($query);
    $stmt->execute([$store_id]);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get products with pagination (scoped to store)
$page = $_GET['p'] ?? 1;
$limit = 12;
$offset = ($page - 1) * $limit;

$category_id = $_GET['category'] ?? '';
$search = $_GET['search'] ?? '';

$where_conditions = ["p.store_id = ?", "p.is_active = 1"];
$params = [$store_id];

if (!empty($category_id)) {
    $where_conditions[] = "pc.category_id = ?";
    $params[] = $category_id;
}

if (!empty($search)) {
    $where_conditions[] = "(p.name LIKE ? OR p.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_clause = implode(" AND ", $where_conditions);

// Count total products
$count_query = "SELECT COUNT(DISTINCT p.id) as total 
                FROM products p 
                LEFT JOIN product_categories pc ON p.id = pc.product_id 
                WHERE $where_clause";
$count_stmt = $conn->prepare($count_query);
$count_stmt->execute($params);
$total_products = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_products / $limit);

// Get products
$query = "SELECT DISTINCT p.*, pi.image_path as main_image
          FROM products p 
          LEFT JOIN product_categories pc ON p.id = pc.product_id 
          LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = 1
          WHERE $where_clause 
          ORDER BY p.created_at DESC 
          LIMIT $limit OFFSET $offset";
$stmt = $conn->prepare($query);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container py-5">
    <div class="row">
        <?php if ($categories_enabled === '1'): ?>
        <div class="col-md-3">
            <!-- Categories Sidebar -->
            <div class="card">
                <div class="card-header">
                    <h5>Categories</h5>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item">
                            <a href="index.php?page=shop" class="text-decoration-none <?php echo empty($category_id) ? 'fw-bold' : ''; ?>">
                                All Products
                            </a>
                        </li>
                        <?php foreach ($categories as $category): ?>
                            <li class="list-group-item">
                                <a href="index.php?page=shop&category=<?php echo $category['id']; ?>" 
                                   class="text-decoration-none <?php echo $category_id == $category['id'] ? 'fw-bold' : ''; ?>">
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="<?php echo $categories_enabled === '1' ? 'col-md-9' : 'col-12'; ?>">
            <!-- Search and Filters -->
            <div class="row mb-4">
                <div class="col-md-8">
                    <form method="GET" class="d-flex">
                        <input type="hidden" name="page" value="shop">
                        <input type="hidden" name="category" value="<?php echo htmlspecialchars($category_id); ?>">
                        <input type="text" name="search" class="form-control me-2" 
                               placeholder="Search products..." value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i>
                        </button>
                    </form>
                </div>
                <div class="col-md-4 text-end">
                    <span class="text-muted">Showing <?php echo count($products); ?> of <?php echo $total_products; ?> products</span>
                </div>
            </div>
            
            <!-- Products Grid -->
            <div class="row">
                <?php if (!empty($products)): ?>
                    <?php foreach ($products as $product): ?>
                        <div class="col-lg-4 col-md-6 mb-4">
                            <div class="card h-100">
                                <?php
                                $product_image = "uploads/products/{$product['id']}/main.jpg";
                                $image_exists = file_exists($product_image);
                                $display_image = $image_exists ? $product_image : 'uploads/placeholder.jpg';
                                ?>
                                <img src="<?php echo $display_image; ?>" loading="lazy" 
                                     class="card-img-top" alt="<?php echo htmlspecialchars($product['name']); ?>"
                                     onerror="this.src='uploads/placeholder.jpg'"
                                     style="height: 250px; object-fit: cover;">
                                <div class="card-body d-flex flex-column">
                                    <h5 class="card-title"><?php echo htmlspecialchars($product['name']); ?></h5>
                                    <p class="card-text"><?php echo htmlspecialchars(substr($product['short_description'] ?? '', 0, 100)); ?><?php echo strlen($product['short_description'] ?? '') > 100 ? '...' : ''; ?></p>
                                    <div class="mt-auto">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <?php if ($product['sale_price']): ?>
                                                <div>
                                                    <span class="text-muted text-decoration-line-through small"><?php echo $price_prefix; ?><?php echo number_format($product['price'], 2); ?><?php echo $price_suffix; ?></span>
                                                    <span class="h5 text-danger"><?php echo $price_prefix; ?><?php echo number_format($product['sale_price'], 2); ?><?php echo $price_suffix; ?></span>
                                                </div>
                                            <?php else: ?>
                                                <span class="h5 text-primary"><?php echo $price_prefix; ?><?php echo number_format($product['price'], 2); ?><?php echo $price_suffix; ?></span>
                                            <?php endif; ?>
                                            <a href="index.php?page=product_view&id=<?php echo $product['id']; ?>" 
                                               class="btn btn-primary">View</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12 text-center py-5">
                        <i class="fas fa-search fa-3x text-muted mb-3"></i>
                        <h4>No products found</h4>
                        <p>Try adjusting your search or filter criteria</p>
                        <a href="index.php?page=shop" class="btn btn-primary">View All Products</a>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Products pagination" class="mt-5">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=shop&p=<?php echo $page - 1; ?>&category=<?php echo $category_id; ?>&search=<?php echo urlencode($search); ?>">Previous</a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=shop&p=<?php echo $i; ?>&category=<?php echo $category_id; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=shop&p=<?php echo $page + 1; ?>&category=<?php echo $category_id; ?>&search=<?php echo urlencode($search); ?>">Next</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.card {
    transition: transform 0.3s ease;
}

.card:hover {
    transform: translateY(-5px);
}

.pagination .page-link {
    color: var(--primary-color);
}

.pagination .page-item.active .page-link {
    background-color: var(--primary-color);
    border-color: var(--primary-color);
}
</style>
