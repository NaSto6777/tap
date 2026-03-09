<?php
require_once __DIR__ . '/../../config/StoreContext.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/settings.php';
require_once __DIR__ . '/../../config/language.php';
require_once __DIR__ . '/../../config/analytics_helper.php';

$store_id = StoreContext::getId();
$database = new Database();
$conn = $database->getConnection();
$settings = new Settings($conn, $store_id);
$analytics = new AnalyticsHelper($conn, $store_id);

Language::init();
$t = function($key, $default = null) {
    return Language::t($key, $default);
};

$currency = $settings->getSetting('currency', 'USD');
$custom_currency = $settings->getSetting('custom_currency', '');
if ($currency === 'CUSTOM' && !empty($custom_currency)) {
    $currency_symbol = $custom_currency;
} else {
    $currency_symbol = $currency === 'USD' ? '$' : ($currency === 'EUR' ? '€' : ($currency === 'GBP' ? '£' : ($currency === 'TND' ? 'TND' : $currency . ' ')));
}
$currency_position = $settings->getSetting('currency_position', 'left');

$days = isset($_GET['days']) ? (int) $_GET['days'] : 7;
$days = max(1, min(30, $days));

$abandonedRows = $analytics->getAbandonedCarts($days, 100);
$carts = [];
foreach ($abandonedRows as $cart) {
    $itemsCount = 0;
    $itemsList = [];
    if (!empty($cart['cart_data'])) {
        $cartItems = json_decode($cart['cart_data'], true);
        if (is_array($cartItems)) {
            foreach ($cartItems as $item) {
                $qty = (int)($item['quantity'] ?? 0);
                $itemsCount += $qty;
                $productId = (int)($item['product_id'] ?? 0);
                if ($productId) {
                    $pstmt = $conn->prepare("SELECT name FROM products WHERE id = ? AND store_id = ?");
                    $pstmt->execute([$productId, $store_id]);
                    $pname = $pstmt->fetchColumn();
                    $itemsList[] = ($pname ?: 'Product #' . $productId) . ' × ' . $qty;
                }
            }
        }
    }
    $phone = $cart['customer_phone'] ?? '';
    $waNumber = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($waNumber) > 0) {
        if (substr($waNumber, 0, 1) === '0') $waNumber = '213' . substr($waNumber, 1);
        elseif (substr($waNumber, 0, 2) !== '21' && strlen($waNumber) <= 10) $waNumber = '213' . $waNumber;
    }
    $carts[] = [
        'id' => $cart['id'] ?? null,
        'name' => $cart['customer_name'] ?? $t('anonymous', 'Anonymous'),
        'email' => $cart['customer_email'] ?? '',
        'phone' => $phone,
        'wa_link' => $waNumber ? ('https://wa.me/' . $waNumber) : '',
        'cart_value' => (float)($cart['cart_value'] ?? 0),
        'items' => $itemsCount,
        'items_list' => $itemsList,
        'date' => isset($cart['created_at']) ? date('Y-m-d H:i', strtotime($cart['created_at'])) : '',
    ];
}
?>
<div class="dashboard-container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0"><i class="fas fa-shopping-cart"></i> <?php echo $t('abandoned_carts', 'Abandoned Carts'); ?></h1>
        <form method="GET" class="d-flex gap-2 align-items-center">
            <input type="hidden" name="page" value="abandoned_carts">
            <label class="mb-0"><?php echo $t('last_days', 'Last'); ?></label>
            <select name="days" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
                <option value="7" <?php echo $days === 7 ? 'selected' : ''; ?>>7 <?php echo $t('days', 'days'); ?></option>
                <option value="14" <?php echo $days === 14 ? 'selected' : ''; ?>>14 <?php echo $t('days', 'days'); ?></option>
                <option value="30" <?php echo $days === 30 ? 'selected' : ''; ?>>30 <?php echo $t('days', 'days'); ?></option>
            </select>
        </form>
    </div>
    <p class="text-muted mb-4"><?php echo $t('abandoned_carts_desc', 'Contact these customers via WhatsApp to recover lost sales.'); ?></p>

    <?php if (empty($carts)): ?>
    <div class="modern-card">
        <div class="modern-card-body text-center py-5">
            <i class="fas fa-shopping-bag fa-4x text-muted mb-3"></i>
            <h4><?php echo $t('no_abandoned_carts', 'No abandoned carts'); ?></h4>
            <p class="text-muted"><?php echo $t('no_abandoned_carts_desc', 'Carts will appear here when customers start checkout but do not complete.'); ?></p>
        </div>
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="modern-table">
            <thead>
                <tr>
                    <th><?php echo $t('customer', 'Customer'); ?></th>
                    <th><?php echo $t('contact', 'Contact'); ?></th>
                    <th><?php echo $t('items'); ?></th>
                    <th><?php echo $t('total'); ?></th>
                    <th><?php echo $t('date'); ?></th>
                    <th><?php echo $t('actions'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($carts as $c): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($c['name']); ?></strong></td>
                    <td>
                        <?php if ($c['email']): ?>
                            <span class="d-block"><i class="fas fa-envelope text-muted"></i> <?php echo htmlspecialchars($c['email']); ?></span>
                        <?php endif; ?>
                        <?php if ($c['phone']): ?>
                            <span class="d-block"><i class="fas fa-phone text-muted"></i> <?php echo htmlspecialchars($c['phone']); ?></span>
                        <?php endif; ?>
                        <?php if (!$c['email'] && !$c['phone']): ?>
                            <span class="text-muted"><?php echo $t('no_contact', 'No contact'); ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($c['items_list'])): ?>
                            <small><?php echo htmlspecialchars(implode(', ', array_slice($c['items_list'], 0, 3))); ?><?php echo count($c['items_list']) > 3 ? '...' : ''; ?></small>
                        <?php else: ?>
                            <?php echo (int)$c['items']; ?> <?php echo $t('items'); ?>
                        <?php endif; ?>
                    </td>
                    <td><strong><?php echo $currency_position === 'left' ? $currency_symbol . number_format($c['cart_value'], 2) : number_format($c['cart_value'], 2) . ' ' . $currency_symbol; ?></strong></td>
                    <td><?php echo htmlspecialchars($c['date']); ?></td>
                    <td>
                        <?php if ($c['wa_link']): ?>
                            <a href="<?php echo htmlspecialchars($c['wa_link']); ?>" target="_blank" rel="noopener" class="btn btn-success btn-sm">
                                <i class="fab fa-whatsapp"></i> <?php echo $t('contact_whatsapp', 'WhatsApp'); ?>
                            </a>
                        <?php elseif ($c['email']): ?>
                            <a href="mailto:<?php echo htmlspecialchars($c['email']); ?>" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-envelope"></i> <?php echo $t('email'); ?>
                            </a>
                        <?php else: ?>
                            <span class="text-muted"><?php echo $t('add_contact_to_enable', 'Add contact to enable'); ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
