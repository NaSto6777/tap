<?php
use_helper_shop:
// Shop page for temp2

$store_id = \StoreContext::getId();
$db       = new \Database();
$conn     = $db->getConnection();

// Currency formatting
$currency       = $settings->getSetting('currency', 'USD');
$customCurrency = $settings->getSetting('custom_currency', '');
if ($currency === 'CUSTOM' && !empty($customCurrency)) {
    $currency_symbol = $customCurrency;
} else {
    $currency_symbol = $currency === 'USD' ? '$'
        : ($currency === 'EUR' ? '€'
        : ($currency === 'GBP' ? '£'
        : ($currency === 'TND' ? 'TND' : $currency . ' ')));
}
$currency_position = $settings->getSetting('currency_position', 'left');

// Filters
$page      = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$per_page  = 12;
$offset    = ($page - 1) * $per_page;
$categoryId = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$search    = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build WHERE clause
$where  = ['p.store_id = ? AND p.is_active = 1'];
$params = [$store_id];

if ($categoryId > 0) {
    $where[]  = 'pc.category_id = ?';
    $params[] = $categoryId;
}

if ($search !== '') {
    $where[]  = '(p.name LIKE ? OR p.description LIKE ?)';
    $searchLike = '%' . $search . '%';
    $params[] = $searchLike;
    $params[] = $searchLike;
}

$whereSql = implode(' AND ', $where);

// Total count
$countSql = "
    SELECT COUNT(DISTINCT p.id) AS total
    FROM products p
    LEFT JOIN product_categories pc ON pc.product_id = p.id
    WHERE {$whereSql}
";
$stmt = $conn->prepare($countSql);
$stmt->execute($params);
$total_products = (int)($stmt->fetchColumn() ?: 0);
$total_pages    = $total_products > 0 ? (int)ceil($total_products / $per_page) : 1;

// Product list
$productSql = "
    SELECT DISTINCT p.id, p.name, p.price, p.sale_price, p.short_description
    FROM products p
    LEFT JOIN product_categories pc ON pc.product_id = p.id
    WHERE {$whereSql}
    ORDER BY p.created_at DESC
    LIMIT {$per_page} OFFSET {$offset}
";
$stmt = $conn->prepare($productSql);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Categories (sidebar)
$categories = [];
if ($settings->getSetting('categories_enabled', '1') === '1') {
    $catStmt = $conn->prepare(
        "SELECT id, name 
         FROM categories 
         WHERE store_id = ? AND is_active = 1 
         ORDER BY name ASC"
    );
    $catStmt->execute([$store_id]);
    $categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);
}

// Helper: build pagination URL
function temp2_shop_url($page, $categoryId, $search) {
    $params = ['page' => 'shop'];
    if ($page > 1) {
        $params['p'] = $page;
    }
    if ($categoryId > 0) {
        $params['category'] = $categoryId;
    }
    if ($search !== '') {
        $params['search'] = $search;
    }
    return 'index.php?' . http_build_query($params);
}
?>

<section class="sf-section">
    <div class="container">
        <div class="row g-4">
            <!-- Sidebar / Filters -->
            <aside class="col-lg-3">
                <div class="sf-card-ghost p-3 p-md-4 mb-3">
                    <h2 class="h6 text-white mb-3 d-flex align-items-center justify-content-between">
                        <span>Filters</span>
                        <?php if ($categoryId || $search !== ''): ?>
                            <a href="index.php?page=shop" class="small text-secondary text-decoration-none">
                                Clear
                            </a>
                        <?php endif; ?>
                    </h2>

                    <form method="GET" class="mb-3">
                        <input type="hidden" name="page" value="shop">
                        <?php if ($categoryId): ?>
                            <input type="hidden" name="category" value="<?php echo (int)$categoryId; ?>">
                        <?php endif; ?>
                        <div class="mb-2">
                            <label class="form-label text-secondary small mb-1">Search</label>
                            <div class="position-relative">
                                <span class="position-absolute top-50 start-0 translate-middle-y ps-3 text-secondary">
                                    <i class="fas fa-magnifying-glass"></i>
                                </span>
                                <input
                                    type="text"
                                    name="search"
                                    class="form-control ps-5 bg-transparent border-secondary text-white"
                                    value="<?php echo htmlspecialchars($search); ?>"
                                    placeholder="Product name"
                                >
                            </div>
                        </div>
                        <button type="submit" class="btn btn-outline-light btn-sm w-100 mt-2 rounded-pill">
                            Apply
                        </button>
                    </form>

                    <?php if (!empty($categories)): ?>
                        <div class="mb-2">
                            <label class="form-label text-secondary small mb-1">Categories</label>
                            <div class="list-group list-group-flush small">
                                <a
                                    href="<?php echo htmlspecialchars(temp2_shop_url(1, 0, $search)); ?>"
                                    class="list-group-item list-group-item-action bg-transparent border-0 px-0 py-1 <?php echo $categoryId === 0 ? 'text-white fw-semibold' : 'text-secondary'; ?>"
                                >
                                    All
                                </a>
                                <?php foreach ($categories as $cat): ?>
                                    <a
                                        href="<?php echo htmlspecialchars(temp2_shop_url(1, (int)$cat['id'], $search)); ?>"
                                        class="list-group-item list-group-item-action bg-transparent border-0 px-0 py-1 <?php echo $categoryId === (int)$cat['id'] ? 'text-white fw-semibold' : 'text-secondary'; ?>"
                                    >
                                        <?php echo htmlspecialchars($cat['name']); ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </aside>

            <!-- Main grid -->
            <div class="col-lg-9">
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
                    <div class="small text-secondary">
                        <?php
                        $shownFrom = $total_products ? $offset + 1 : 0;
                        $shownTo   = min($offset + count($products), $total_products);
                        ?>
                        Showing
                        <span class="text-white fw-semibold"><?php echo $shownFrom; ?></span>
                        –
                        <span class="text-white fw-semibold"><?php echo $shownTo; ?></span>
                        of
                        <span class="text-white fw-semibold"><?php echo $total_products; ?></span>
                        products
                    </div>
                </div>

                <?php if (!empty($products)): ?>
                    <div class="row g-3 g-md-4">
                        <?php foreach ($products as $product): ?>
                            <?php
                            $imageUrl = \ImageHelper::getProductImage((int)$product['id']);
                            $hasSale  = $product['sale_price'] !== null && $product['sale_price'] !== '' && (float)$product['sale_price'] > 0;
                            $basePrice = (float)$product['price'];
                            $salePrice = $hasSale ? (float)$product['sale_price'] : null;
                            $displayPrice = $hasSale ? $salePrice : $basePrice;

                            $priceFormatted = $currency_position === 'left'
                                ? $currency_symbol . number_format($displayPrice, 2)
                                : number_format($displayPrice, 2) . ' ' . $currency_symbol;
                            ?>
                            <div class="col-6 col-md-4">
                                <article class="sf-card h-100 d-flex flex-column">
                                    <a href="index.php?page=product_view&id=<?php echo (int)$product['id']; ?>" class="text-decoration-none text-reset">
                                        <div class="ratio ratio-4x5 rounded-4 overflow-hidden mb-3">
                                            <img
                                                src="<?php echo htmlspecialchars($imageUrl); ?>"
                                                alt="<?php echo htmlspecialchars($product['name']); ?>"
                                                class="w-100 h-100"
                                                style="object-fit: cover;"
                                                onerror="this.src='<?php echo htmlspecialchars(ImageHelper::getPlaceholder()); ?>';"
                                            >
                                        </div>
                                    </a>
                                    <div class="px-3 pb-3 d-flex flex-column flex-grow-1">
                                        <a href="index.php?page=product_view&id=<?php echo (int)$product['id']; ?>" class="text-decoration-none">
                                            <h3 class="h6 text-white mb-1 text-truncate">
                                                <?php echo htmlspecialchars($product['name']); ?>
                                            </h3>
                                        </a>
                                        <?php if (!empty($product['short_description'])): ?>
                                            <p class="text-secondary small mb-2">
                                                <?php echo htmlspecialchars(mb_substr($product['short_description'], 0, 70)); ?>
                                                <?php echo mb_strlen($product['short_description']) > 70 ? '…' : ''; ?>
                                            </p>
                                        <?php endif; ?>

                                        <div class="mt-auto d-flex justify-content-between align-items-center">
                                            <div class="d-flex flex-column">
                                                <?php if ($hasSale): ?>
                                                    <span class="text-secondary text-decoration-line-through small">
                                                        <?php
                                                        $originalFormatted = $currency_position === 'left'
                                                            ? $currency_symbol . number_format($basePrice, 2)
                                                            : number_format($basePrice, 2) . ' ' . $currency_symbol;
                                                        echo $originalFormatted;
                                                        ?>
                                                    </span>
                                                    <span class="fw-semibold text-white">
                                                        <?php echo $priceFormatted; ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="fw-semibold text-white">
                                                        <?php echo $priceFormatted; ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>

                                            <form
                                                class="d-inline"
                                                method="POST"
                                                action="index.php?page=cart"
                                                onsubmit="event.preventDefault(); if (window.sfAddToCart) { sfAddToCart(this); } else { this.submit(); }"
                                            >
                                                <input type="hidden" name="product_id" value="<?php echo (int)$product['id']; ?>">
                                                <input type="hidden" name="quantity" value="1">
                                                <input type="hidden" name="action" value="add">
                                                <button type="submit" class="btn btn-sm btn-outline-light rounded-pill px-3">
                                                    <i class="fas fa-plus me-1"></i> Add
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </article>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($total_pages > 1): ?>
                        <nav class="mt-4" aria-label="Products pagination">
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="<?php echo htmlspecialchars(temp2_shop_url($page - 1, $categoryId, $search)); ?>" aria-label="Previous">
                                            <span aria-hidden="true">&laquo;</span>
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <?php
                                $start = max(1, $page - 2);
                                $end   = min($total_pages, $page + 2);
                                for ($i = $start; $i <= $end; $i++): ?>
                                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="<?php echo htmlspecialchars(temp2_shop_url($i, $categoryId, $search)); ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>

                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="<?php echo htmlspecialchars(temp2_shop_url($page + 1, $categoryId, $search)); ?>" aria-label="Next">
                                            <span aria-hidden="true">&raquo;</span>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="text-center py-5">
                        <p class="h6 text-white mb-2">No products found</p>
                        <p class="text-secondary mb-3">
                            Try adjusting your filters or searching for something else.
                        </p>
                        <?php if ($search || $categoryId): ?>
                            <a href="index.php?page=shop" class="sf-btn-outline">
                                Reset filters
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

