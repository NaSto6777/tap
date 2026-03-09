<?php
/**
 * TEMP3 — Shop
 * Uses temp1 engine logic, Tailwind design.
 */

$store_id = StoreContext::getId();
$database = new Database();
$conn     = $database->getConnection();

// Currency
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

// Categories
$categories_enabled = $settings->getSetting('categories_enabled', '1');
$categories = [];
if ($categories_enabled === '1') {
    $stmt = $conn->prepare("SELECT * FROM categories WHERE store_id = ? AND is_active = 1 AND parent_id IS NULL ORDER BY sort_order, name");
    $stmt->execute([$store_id]);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Filters & pagination
$currentPage = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$limit       = 12;
$offset      = ($currentPage - 1) * $limit;

$category_id = $_GET['category'] ?? '';
$search      = $_GET['search'] ?? '';

$where   = ["p.store_id = ?", "p.is_active = 1"];
$params  = [$store_id];

if ($category_id !== '') {
    $where[] = "pc.category_id = ?";
    $params[] = $category_id;
}
if ($search !== '') {
    $where[]  = "(p.name LIKE ? OR p.description LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

$whereSql = implode(' AND ', $where);

// Count
$countSql = "SELECT COUNT(DISTINCT p.id) AS total
             FROM products p
             LEFT JOIN product_categories pc ON p.id = pc.product_id
             WHERE {$whereSql}";
$countStmt = $conn->prepare($countSql);
$countStmt->execute($params);
$total_products = (int)($countStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
$total_pages    = $total_products > 0 ? (int)ceil($total_products / $limit) : 1;

// Products
$sql = "SELECT DISTINCT p.*
        FROM products p
        LEFT JOIN product_categories pc ON p.id = pc.product_id
        WHERE {$whereSql}
        ORDER BY p.created_at DESC
        LIMIT {$limit} OFFSET {$offset}";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<section class="pt-4 pb-8 space-y-6">
    <!-- Heading / search -->
    <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4">
        <div>
            <h1 class="text-xl sm:text-2xl font-semibold tracking-tight text-brand-900">Catalog</h1>
            <p class="text-xs text-brand-400 mt-1">
                Showing <?php echo count($products); ?> of <?php echo $total_products; ?> products
            </p>
        </div>

        <form method="GET" class="flex items-center gap-2 w-full sm:w-auto">
            <input type="hidden" name="page" value="shop">
            <?php if ($category_id !== ''): ?>
                <input type="hidden" name="category" value="<?php echo htmlspecialchars($category_id); ?>">
            <?php endif; ?>
            <input
                type="text"
                name="search"
                value="<?php echo htmlspecialchars($search); ?>"
                placeholder="Search products"
                class="flex-1 sm:w-60 rounded-full border border-brand-200 bg-white px-3 py-2 text-xs text-brand-800 placeholder:text-brand-300 focus:outline-none focus:ring-1 focus:ring-brand-300"
            >
            <button
                type="submit"
                class="inline-flex items-center justify-center rounded-full bg-black text-white px-4 py-2 text-[11px] font-medium shadow-soft hover:bg-brand-900 transition"
            >
                Search
            </button>
        </form>
    </div>

    <div class="flex flex-col lg:flex-row gap-6 mt-4">
        <!-- Sidebar categories -->
        <?php if ($categories_enabled === '1' && !empty($categories)): ?>
            <aside class="w-full lg:w-52 shrink-0">
                <div class="rounded-2xl border border-brand-100 bg-white/80 p-3.5">
                    <h2 class="text-[11px] font-semibold uppercase tracking-[0.24em] text-brand-400 mb-3">Categories</h2>
                    <nav class="space-y-1 text-xs">
                        <a href="index.php?page=shop"
                           class="flex items-center justify-between rounded-full px-3 py-1.5 <?php echo $category_id === '' ? 'bg-black text-white' : 'text-brand-700 hover:bg-brand-50'; ?>">
                            <span>All products</span>
                            <?php if ($category_id === ''): ?>
                                <span class="text-[10px] opacity-80">●</span>
                            <?php endif; ?>
                        </a>
                        <?php foreach ($categories as $cat): ?>
                            <?php $active = (string)$category_id === (string)$cat['id']; ?>
                            <a href="index.php?page=shop&category=<?php echo (int)$cat['id']; ?>"
                               class="flex items-center justify-between rounded-full px-3 py-1.5 <?php echo $active ? 'bg-black text-white' : 'text-brand-700 hover:bg-brand-50'; ?>">
                                <span><?php echo htmlspecialchars($cat['name']); ?></span>
                                <?php if ($active): ?>
                                    <span class="text-[10px] opacity-80">●</span>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </nav>
                </div>
            </aside>
        <?php endif; ?>

        <!-- Product grid -->
        <div class="flex-1">
            <?php if (!empty($products)): ?>
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-3 gap-3 sm:gap-4">
                    <?php foreach ($products as $product): ?>
                        <?php
                        $id   = (int)$product['id'];
                        $name = $product['name'] ?? '';
                        $price = isset($product['price']) ? (float)$product['price'] : 0.0;
                        $sale_price = isset($product['sale_price']) && $product['sale_price'] !== '' ? (float)$product['sale_price'] : null;
                        $displayPrice = $sale_price !== null && $sale_price > 0 ? $sale_price : $price;
                        $formatted = $currency_position === 'left'
                            ? $currency_symbol . number_format($displayPrice, 2)
                            : number_format($displayPrice, 2) . ' ' . $currency_symbol;
                        $imageUrl = ImageHelper::getProductImage($id);
                        ?>
                        <article class="group flex flex-col rounded-2xl border border-brand-100 bg-white/80 hover:bg-white shadow-sm hover:shadow-soft transition overflow-hidden">
                            <a href="index.php?page=product_view&id=<?php echo $id; ?>"
                               class="block aspect-[4/5] overflow-hidden">
                                <img
                                    src="<?php echo htmlspecialchars($imageUrl); ?>"
                                    alt="<?php echo htmlspecialchars($name); ?>"
                                    class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-[1.03]"
                                    onerror="this.src='<?php echo htmlspecialchars(ImageHelper::getPlaceholder()); ?>';"
                                >
                            </a>
                            <div class="flex flex-col flex-1 px-3.5 pt-3 pb-3.5">
                                <a href="index.php?page=product_view&id=<?php echo $id; ?>"
                                   class="text-[13px] font-medium text-brand-900 line-clamp-2 mb-1.5">
                                    <?php echo htmlspecialchars($name); ?>
                                </a>

                                <?php if (!empty($product['short_description'])): ?>
                                    <p class="text-[11px] text-brand-400 line-clamp-2 mb-2">
                                        <?php echo htmlspecialchars($product['short_description']); ?>
                                    </p>
                                <?php endif; ?>

                                <div class="mt-auto flex items-end justify-between gap-2">
                                    <div class="flex flex-col">
                                        <span class="text-[11px] uppercase tracking-[0.18em] text-brand-400">
                                            Price
                                        </span>
                                        <span class="text-sm font-semibold text-brand-900">
                                            <?php echo $formatted; ?>
                                        </span>
                                    </div>

                                    <a href="index.php?page=product_view&id=<?php echo $id; ?>"
                                       class="inline-flex items-center justify-center rounded-full border border-brand-200 px-3 py-1.5 text-[11px] font-medium text-brand-800 hover:bg-brand-900 hover:text-white transition">
                                        View
                                    </a>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="mt-6 flex justify-center gap-1 text-[11px]">
                        <?php if ($currentPage > 1): ?>
                            <a href="?page=shop&p=<?php echo $currentPage - 1; ?>&category=<?php echo urlencode($category_id); ?>&search=<?php echo urlencode($search); ?>"
                               class="px-3 py-1 rounded-full border border-brand-200 text-brand-700 hover:bg-brand-50">
                                Prev
                            </a>
                        <?php endif; ?>
                        <?php for ($i = max(1, $currentPage - 2); $i <= min($total_pages, $currentPage + 2); $i++): ?>
                            <a href="?page=shop&p=<?php echo $i; ?>&category=<?php echo urlencode($category_id); ?>&search=<?php echo urlencode($search); ?>"
                               class="px-3 py-1 rounded-full border <?php echo $i === $currentPage ? 'bg-black text-white border-black' : 'border-brand-200 text-brand-700 hover:bg-brand-50'; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        <?php if ($currentPage < $total_pages): ?>
                            <a href="?page=shop&p=<?php echo $currentPage + 1; ?>&category=<?php echo urlencode($category_id); ?>&search=<?php echo urlencode($search); ?>"
                               class="px-3 py-1 rounded-full border border-brand-200 text-brand-700 hover:bg-brand-50">
                                Next
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="rounded-2xl border border-dashed border-brand-200 bg-white/70 px-5 py-8 text-center">
                    <p class="text-sm font-medium text-brand-800 mb-1">
                        No products found
                    </p>
                    <p class="text-xs text-brand-400 max-w-xs mx-auto mb-3">
                        Try adjusting your search or filters, or clear them to see all products.
                    </p>
                    <a href="index.php?page=shop"
                       class="inline-flex items-center justify-center rounded-full border border-brand-200 px-4 py-2 text-[11px] font-medium text-brand-700 hover:bg-brand-50 transition">
                        Clear filters
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

