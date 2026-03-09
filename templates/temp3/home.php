<?php
/**
 * TEMP3 — Home
 * Big typography hero + product bento grid
 *
 * Engine (required):
 * - Uses $products
 * - Uses $settings
 * - Uses ImageHelper::getProductImage()
 */

$store_id = StoreContext::getId();
$database = new Database();
$conn     = $database->getConnection();

// Fetch products for this store and expose them as $products
$stmt = $conn->prepare("SELECT id, name, price, sale_price, short_description, created_at FROM products WHERE store_id = ? AND is_active = 1 ORDER BY created_at DESC LIMIT 24");
$stmt->execute([$store_id]);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Currency formatting based on settings
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

// We'll show up to 8 products on home
$homeProducts = array_slice($products, 0, 8);
?>

<!-- HERO: minimal, typography-first -->
<section class="py-10 sm:py-14">
    <div class="space-y-6 sm:space-y-8">
        <p class="text-xs font-semibold tracking-[0.3em] uppercase text-brand-500">
            <?php echo htmlspecialchars($settings->getSetting('hero_eyebrow', 'New this week')); ?>
        </p>

        <h1 class="text-3xl sm:text-5xl md:text-6xl font-semibold tracking-tight text-brand-900 max-w-3xl">
            <?php echo htmlspecialchars($settings->getSetting('hero_title', 'Objects for people who care about details.')); ?>
        </h1>

        <p class="text-sm sm:text-base text-brand-500 max-w-xl">
            <?php echo htmlspecialchars($settings->getSetting('hero_subtitle', 'A boutique selection of elevated basics and statement pieces, edited for everyday life.')); ?>
        </p>

        <div class="flex flex-wrap items-center gap-3">
            <a href="index.php?page=shop"
               class="inline-flex items-center justify-center rounded-full bg-black text-white px-5 py-2.5 text-xs font-medium shadow-soft hover:bg-brand-900 transition">
                Shop the collection
            </a>
            <button type="button"
                    onclick="window.scrollTo({ top: document.body.scrollHeight * 0.35, behavior: 'smooth' });"
                    class="inline-flex items-center justify-center rounded-full border border-brand-200 px-4 py-2 text-[11px] font-medium text-brand-700 hover:bg-brand-50 transition">
                Browse highlights
            </button>
        </div>
    </div>
</section>

<!-- PRODUCT GRID: bento-style cards -->
<section class="pb-10">
    <?php if (!empty($homeProducts)): ?>
        <div class="flex items-center justify-between mb-4 sm:mb-6">
            <h2 class="text-sm font-semibold tracking-tight text-brand-900">
                Featured pieces
            </h2>
            <a href="index.php?page=shop" class="text-[11px] font-medium text-brand-500 hover:text-brand-800 transition">
                View all
            </a>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 sm:gap-4">
            <?php foreach ($homeProducts as $product): ?>
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

                            <form
                                method="POST"
                                action="index.php?page=cart"
                                class="inline-flex"
                                onsubmit="event.preventDefault(); if (window.btAddToCart) { btAddToCart(this); } else { this.submit(); }"
                                data-bt-cart
                            >
                                <input type="hidden" name="product_id" value="<?php echo $id; ?>">
                                <input type="hidden" name="quantity" value="1">
                                <input type="hidden" name="action" value="add">
                                <button
                                    type="submit"
                                    class="inline-flex items-center justify-center rounded-full border border-brand-200 px-3 py-1.5 text-[11px] font-medium text-brand-800 hover:bg-brand-900 hover:text-white transition"
                                >
                                    Add
                                </button>
                            </form>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="rounded-2xl border border-dashed border-brand-200 bg-white/70 px-5 py-8 text-center">
            <p class="text-sm font-medium text-brand-800 mb-1">
                No products yet
            </p>
            <p class="text-xs text-brand-400 max-w-xs mx-auto mb-3">
                Once you add products for this store in the admin, they’ll appear here automatically.
            </p>
            <a href="index.php?page=shop"
               class="inline-flex items-center justify-center rounded-full border border-brand-200 px-4 py-2 text-[11px] font-medium text-brand-700 hover:bg-brand-50 transition">
                Go to catalog
            </a>
        </div>
    <?php endif; ?>
</section>

<!-- Trust / benefits -->
<section class="pb-10">
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <?php
        $benefits = [
            ['title' => 'Fast delivery', 'desc' => '24–72h dispatch'],
            ['title' => 'Easy returns', 'desc' => 'Simple exchange policy'],
            ['title' => 'Secure checkout', 'desc' => 'Protected payments'],
            ['title' => 'Support', 'desc' => 'Quick responses'],
        ];
        foreach ($benefits as $b):
        ?>
            <div class="rounded-2xl border border-brand-100 bg-white/80 px-4 py-4">
                <div class="text-sm font-semibold text-brand-900"><?php echo htmlspecialchars($b['title']); ?></div>
                <div class="text-xs text-brand-400 mt-1"><?php echo htmlspecialchars($b['desc']); ?></div>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- Floating bottom navigation — mobile-first -->
<nav class="fixed bottom-0 inset-x-0 z-40 bt-safe-area-bottom border-t border-brand-200 bg-white/95 backdrop-blur-sm sm:hidden">
    <div class="max-w-2xl mx-auto flex items-stretch justify-between text-[11px] font-medium text-brand-500">
        <a href="index.php"
           class="flex-1 flex flex-col items-center justify-center py-2 <?php echo ($page ?? '') === 'home' ? 'text-brand-900' : 'hover:text-brand-900'; ?>">
            <span class="mb-0.5">Home</span>
        </a>
        <a href="index.php?page=shop"
           class="flex-1 flex flex-col items-center justify-center py-2 <?php echo ($page ?? '') === 'shop' ? 'text-brand-900' : 'hover:text-brand-900'; ?>">
            <span class="mb-0.5">Categories</span>
        </a>
        <a href="index.php?page=cart"
           class="flex-1 flex flex-col items-center justify-center py-2">
            <span class="mb-0.5">Cart</span>
        </a>
        <a href="index.php?page=account"
           class="flex-1 flex flex-col items-center justify-center py-2">
            <span class="mb-0.5">Profile</span>
        </a>
    </div>
</nav>

