<?php
/**
 * TEMP3 — Checkout
 * Mobile-first, Tunisian fields (Governorate/City), COD default.
 */

require_once __DIR__ . '/../../config/plugin_helper.php';
require_once __DIR__ . '/../../config/email_helper.php';
require_once __DIR__ . '/../../config/analytics_helper.php';
require_once __DIR__ . '/../../config/billing_helper.php';
require_once __DIR__ . '/../../config/CsrfHelper.php';

// Cart must not be empty
if (empty($_SESSION['cart'])) {
    header('Location: index.php?page=cart');
    exit();
}

$store_id = StoreContext::getId();
$pluginHelper = new PluginHelper();

// Analytics: checkout start
try {
    $analytics = new AnalyticsHelper();
    $analytics->trackFunnelStep('checkout_start', session_id());
} catch (Exception $e) {}

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
$formatPrice = function (float $amount) use ($currency_symbol, $currency_position): string {
    return $currency_position === 'left'
        ? $currency_symbol . number_format($amount, 2)
        : number_format($amount, 2) . ' ' . $currency_symbol;
};

// Store order-limit check (BillingHelper)
$store_limit_reached = false;
try {
    BillingHelper::init();
    if ($store_id && !BillingHelper::canAcceptOrder($store_id)) {
        $store_limit_reached = true;
    }
} catch (Exception $e) {}

$error_message = '';

// Tunisian governorates (simple list)
$governorates = [
    'Ariana','Béja','Ben Arous','Bizerte','Gabès','Gafsa','Jendouba','Kairouan','Kasserine','Kébili',
    'Kef','Mahdia','Manouba','Médenine','Monastir','Nabeul','Sfax','Sidi Bouzid','Siliana','Sousse',
    'Tataouine','Tozeur','Tunis','Zaghouan'
];

// Handle checkout submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CsrfHelper::validateToken($_POST['csrf_token'] ?? '')) {
        $error_message = 'Invalid security token. Please refresh and try again.';
    }

    $customer_name  = trim((string)($_POST['customer_name'] ?? ''));
    $customer_email = trim((string)($_POST['customer_email'] ?? ''));
    $customer_phone = trim((string)($_POST['customer_phone'] ?? ''));
    $shipping_governorate = trim((string)($_POST['shipping_governorate'] ?? ''));
    $shipping_city        = trim((string)($_POST['shipping_city'] ?? ''));
    $shipping_address     = trim((string)($_POST['shipping_address'] ?? ''));
    $payment_method       = 'cod';

    if ($store_limit_reached) {
        $error_message = 'This store is temporarily not accepting new orders.';
    }

    if (empty($error_message)) {
        if ($customer_name === '' || $customer_phone === '' || $shipping_governorate === '' || $shipping_city === '' || $shipping_address === '') {
            $error_message = 'Please fill in all required fields.';
        }
    }

    if (empty($error_message)) {
        $database = new Database();
        $conn     = $database->getConnection();

        // Totals (variant-aware)
        $items = $_SESSION['cart'];
        $productIds = [];
        $variantIds = [];
        foreach ($items as $it) {
            $productIds[] = (int)($it['product_id'] ?? 0);
            if (!empty($it['variant_id'])) $variantIds[] = (int)$it['variant_id'];
        }
        $productIds = array_values(array_unique(array_filter($productIds)));
        $variantIds = array_values(array_unique(array_filter($variantIds)));

        $productsById = [];
        if (!empty($productIds)) {
            $ph = implode(',', array_fill(0, count($productIds), '?'));
            $pstmt = $conn->prepare("SELECT id, name, sku, price, sale_price FROM products WHERE id IN ($ph) AND store_id = ? AND is_active = 1");
            $pstmt->execute(array_merge($productIds, [$store_id]));
            foreach ($pstmt->fetchAll(PDO::FETCH_ASSOC) as $p) {
                $productsById[(int)$p['id']] = $p;
            }
        }
        $variantsById = [];
        if (!empty($variantIds)) {
            $ph = implode(',', array_fill(0, count($variantIds), '?'));
            $vstmt = $conn->prepare("SELECT id, product_id, label, price, stock_quantity FROM product_variants WHERE id IN ($ph) AND store_id = ?");
            $vstmt->execute(array_merge($variantIds, [$store_id]));
            foreach ($vstmt->fetchAll(PDO::FETCH_ASSOC) as $v) {
                $variantsById[(int)$v['id']] = $v;
            }
        }

        $subtotal = 0.0;
        foreach ($items as $it) {
            $pid = (int)($it['product_id'] ?? 0);
            $qty = max(1, (int)($it['quantity'] ?? 1));
            $product = $productsById[$pid] ?? null;
            if (!$product) continue;
            $variant = !empty($it['variant_id']) ? ($variantsById[(int)$it['variant_id']] ?? null) : null;
            $unit = $variant && $variant['price'] !== null ? (float)$variant['price'] : (float)($product['sale_price'] ?: $product['price']);
            $subtotal += $unit * $qty;
        }

        $tax_rate = (float)$settings->getSetting('tax_rate', 0);
        $shipping_price = (float)$settings->getSetting('shipping_price', 0);
        $tax_amount = $subtotal * ($tax_rate / 100.0);
        $total_amount = $subtotal + $tax_amount + $shipping_price;

        $order_number = 'ORD-' . date('Ymd') . '-' . str_pad((string)rand(1, 9999), 4, '0', STR_PAD_LEFT);
        $payment_status = 'pending';

        try {
            $sql = "INSERT INTO orders (
                        store_id, order_number, customer_name, customer_email, customer_phone,
                        shipping_governorate, shipping_city,
                        shipping_address, billing_address,
                        subtotal, tax_amount, shipping_amount, total_amount,
                        payment_method, payment_status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                $store_id, $order_number, $customer_name, $customer_email, $customer_phone,
                $shipping_governorate, $shipping_city,
                $shipping_address, $shipping_address,
                $subtotal, $tax_amount, $shipping_price, $total_amount,
                $payment_method, $payment_status
            ]);
        } catch (PDOException $e) {
            // Fallback without payment columns
            if ($e->getCode() === '42S22') {
                $sql = "INSERT INTO orders (
                            store_id, order_number, customer_name, customer_email, customer_phone,
                            shipping_governorate, shipping_city,
                            shipping_address, billing_address,
                            subtotal, tax_amount, shipping_amount, total_amount
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    $store_id, $order_number, $customer_name, $customer_email, $customer_phone,
                    $shipping_governorate, $shipping_city,
                    $shipping_address, $shipping_address,
                    $subtotal, $tax_amount, $shipping_price, $total_amount
                ]);
            } else {
                throw $e;
            }
        }

        $order_id = (int)$conn->lastInsertId();

        // Insert items
        $itemSql = "INSERT INTO order_items (store_id, order_id, product_id, product_name, product_sku, quantity, price, total)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $itemStmt = $conn->prepare($itemSql);

        $emailItems = [];
        foreach ($items as $it) {
            $pid = (int)($it['product_id'] ?? 0);
            $qty = max(1, (int)($it['quantity'] ?? 1));
            $product = $productsById[$pid] ?? null;
            if (!$product) continue;
            $variant = !empty($it['variant_id']) ? ($variantsById[(int)$it['variant_id']] ?? null) : null;
            $unit = $variant && $variant['price'] !== null ? (float)$variant['price'] : (float)($product['sale_price'] ?: $product['price']);
            $total = $unit * $qty;
            $name = $product['name'] . ($variant ? (' - ' . $variant['label']) : '');
            $sku  = $product['sku'] ?? '';
            $itemStmt->execute([$store_id, $order_id, $pid, $name, $sku, $qty, $unit, $total]);
            $emailItems[] = ['product_name' => $name, 'product_sku' => $sku, 'quantity' => $qty, 'total' => $total];
        }

        // Email (optional)
        if ($customer_email !== '') {
            try {
                $emailService = new EmailService();
                $emailService->sendOrderConfirmation([
                    'order_number' => $order_number,
                    'customer_name' => $customer_name,
                    'customer_email' => $customer_email,
                    'shipping_address' => $shipping_address,
                    'payment_method' => $payment_method,
                    'status' => $payment_status,
                    'subtotal' => $subtotal,
                    'tax_amount' => $tax_amount,
                    'shipping_amount' => $shipping_price,
                    'total_amount' => $total_amount,
                    'created_at' => date('Y-m-d H:i:s'),
                    'items' => $emailItems
                ], $customer_email);
            } catch (Exception $e) {}
        }

        // Clear cart and redirect to success
        $_SESSION['cart'] = [];
        header('Location: index.php?page=checkout_success&order=' . urlencode($order_number));
        exit();
    }
}

// Pre-fill from POST
$v = fn($k) => htmlspecialchars((string)($_POST[$k] ?? ''), ENT_QUOTES, 'UTF-8');
?>

<section class="py-4 md:py-8">
    <div class="flex items-end justify-between gap-4 mb-4 md:mb-6">
        <div>
            <h1 class="text-lg md:text-2xl font-semibold tracking-tight text-brand-900">Checkout</h1>
            <p class="text-xs text-brand-400 mt-1">Fill your details to confirm the order.</p>
        </div>
        <a href="index.php?page=cart" class="text-[11px] font-medium text-brand-500 hover:text-brand-800 transition">Back to cart</a>
    </div>

    <?php if ($error_message): ?>
        <div class="mb-5 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-xs text-red-700">
            <?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-[1fr_320px] gap-4 lg:gap-6">
        <!-- Summary first on mobile so Total is visible -->
        <aside class="order-1 lg:order-2 rounded-2xl border border-brand-100 bg-white/85 p-4 h-fit">
            <h2 class="text-sm font-semibold text-brand-900 mb-3">Summary</h2>
            <?php
            $cartCount = 0;
            foreach ($_SESSION['cart'] as $it) $cartCount += (int)($it['quantity'] ?? 0);
            ?>
            <div class="text-xs text-brand-500 space-y-2">
                <div class="flex justify-between"><span>Items</span><span class="text-brand-900 font-medium"><?php echo $cartCount; ?></span></div>
                <div class="flex justify-between"><span>Tax rate</span><span class="text-brand-900 font-medium"><?php echo number_format((float)$settings->getSetting('tax_rate', 0), 2); ?>%</span></div>
                <div class="flex justify-between"><span>Shipping</span><span class="text-brand-900 font-medium"><?php echo $formatPrice((float)$settings->getSetting('shipping_price', 0)); ?></span></div>
            </div>
            <p class="text-[11px] text-brand-400 mt-4 leading-relaxed">
                After you confirm, we'll contact you to validate the order and delivery details.
            </p>
        </aside>

        <form method="POST" class="order-2 lg:order-1 space-y-4 rounded-2xl border border-brand-100 bg-white/85 p-4 md:p-5">
            <?php echo CsrfHelper::getTokenField(); ?>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-[11px] font-semibold uppercase tracking-[0.18em] text-brand-400 mb-1">Name *</label>
                    <input name="customer_name" value="<?php echo $v('customer_name'); ?>"
                           class="w-full rounded-2xl border border-brand-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-brand-300" required>
                </div>
                <div>
                    <label class="block text-[11px] font-semibold uppercase tracking-[0.18em] text-brand-400 mb-1">Phone *</label>
                    <input name="customer_phone" value="<?php echo $v('customer_phone'); ?>"
                           class="w-full rounded-2xl border border-brand-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-brand-300" required>
                </div>
            </div>

            <div>
                <label class="block text-[11px] font-semibold uppercase tracking-[0.18em] text-brand-400 mb-1">Email (optional)</label>
                <input type="email" name="customer_email" value="<?php echo $v('customer_email'); ?>"
                       class="w-full rounded-2xl border border-brand-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-brand-300">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-[11px] font-semibold uppercase tracking-[0.18em] text-brand-400 mb-1">Governorate *</label>
                    <select name="shipping_governorate"
                            class="w-full rounded-2xl border border-brand-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-brand-300" required>
                        <option value="">Choose…</option>
                        <?php foreach ($governorates as $g): ?>
                            <option value="<?php echo htmlspecialchars($g); ?>" <?php echo ($v('shipping_governorate') === htmlspecialchars($g)) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($g); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] font-semibold uppercase tracking-[0.18em] text-brand-400 mb-1">City *</label>
                    <input name="shipping_city" value="<?php echo $v('shipping_city'); ?>"
                           class="w-full rounded-2xl border border-brand-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-brand-300" required>
                </div>
            </div>

            <div>
                <label class="block text-[11px] font-semibold uppercase tracking-[0.18em] text-brand-400 mb-1">Full address *</label>
                <textarea name="shipping_address" rows="4"
                          class="w-full rounded-2xl border border-brand-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-brand-300" required><?php echo $v('shipping_address'); ?></textarea>
            </div>

            <div class="rounded-2xl border border-brand-100 bg-brand-50 px-4 py-3 text-xs text-brand-600">
                Payment method: <span class="font-semibold text-brand-900">Cash on delivery</span>
            </div>

            <button type="submit"
                    class="inline-flex w-full items-center justify-center rounded-full bg-black text-white px-5 py-2.5 text-xs font-medium shadow-soft hover:bg-brand-900 transition disabled:opacity-40"
                    <?php echo $store_limit_reached ? 'disabled' : ''; ?>>
                Place order
            </button>
        </form>
    </div>
</section>
