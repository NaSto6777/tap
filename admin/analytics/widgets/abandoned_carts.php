<?php
if (!isset($abandonedCarts) || !is_array($abandonedCarts) || count($abandonedCarts)<1) {
  $abandonedCarts = [
    ['id'=>1,'name'=>'Amal B.','email'=>'amal@test.com','phone'=>'555-1234','cart_value'=>163.49,'items'=>3,'date'=>'2025-10-25'],
    ['id'=>2,'name'=>'Nidal S.','email'=>'nidal@somewhere.com','phone'=>'555-3211','cart_value'=>119.95,'items'=>2,'date'=>'2025-10-29'],
    ['id'=>3,'name'=>'Anonymous','email'=>'','phone'=>'','cart_value'=>89.90,'items'=>1,'date'=>'2025-10-18']
  ];
}

// Initialize language if not already set
if (!function_exists('t')) {
    require_once __DIR__ . '/../../../config/language.php';
    Language::init();
    $t = function($key, $default = null) {
        return Language::t($key, $default);
    };
}

$contactableCarts = array_values(array_filter($abandonedCarts, function ($cart) {
  return !empty($cart['email']) || !empty($cart['phone']);
}));
$anonymousCarts = array_values(array_filter($abandonedCarts, function ($cart) {
  return empty($cart['email']) && empty($cart['phone']);
}));
$totalAnonymous = count($anonymousCarts);
?>
<div class="data-card abandoned-carts-card">
  <div class="data-card-header">
    <div>
      <h3><?php echo $t('abandoned_carts', 'Abandoned Carts'); ?></h3>
      <p class="card-subtitle"><?php echo $t('showing_contactable_carts', 'Showing carts we can reach out to first'); ?></p>
    </div>
    <?php if ($totalAnonymous > 0): ?>
    <span class="badge muted">+<?php echo $totalAnonymous; ?> <?php echo $t('anonymous', 'anonymous'); ?></span>
    <?php endif; ?>
  </div>
  <div class="data-card-body">
    <div class="table-responsive">
      <table class="analytics-table abandoned-table">
        <thead>
          <tr>
            <th><?php echo $t('customer', 'Customer'); ?></th>
            <th><?php echo $t('contact', 'Contact'); ?></th>
            <th><?php echo $t('cart_value', 'Cart Value'); ?></th>
            <th><?php echo $t('items'); ?></th>
            <th><?php echo $t('date'); ?></th>
            <th><?php echo $t('actions'); ?></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($contactableCarts as $cart):
          $email = $cart['email'] ?? '';
          $phone = $cart['phone'] ?? '';
        ?>
          <tr class="cart-row" data-cart-id="<?php echo (int)$cart['id']; ?>">
            <td data-label="<?php echo $t('customer', 'Customer'); ?>">
              <div class="customer-cell">
                <span class="customer-name"><?php echo htmlspecialchars($cart['name'] ?: $t('anonymous', 'Anonymous')); ?></span>
                <?php if ($email): ?><span class="contact-tag contact-email"><?php echo $t('email'); ?></span><?php endif; ?>
                <?php if (!$email && $phone): ?><span class="contact-tag contact-phone"><?php echo $t('phone', 'Phone'); ?></span><?php endif; ?>
              </div>
            </td>
            <td data-label="<?php echo $t('contact', 'Contact'); ?>">
              <?php if ($email): ?>
                <span class="contact-chip email" data-field="email"><?php echo htmlspecialchars($email); ?></span>
              <?php endif; ?>
              <?php if ($phone): ?>
                <span class="contact-chip phone" data-field="phone"><?php echo htmlspecialchars($phone); ?></span>
              <?php endif; ?>
              <?php if (!$email && !$phone): ?>
                <span class="contact-chip muted"><?php echo $t('no_contact', 'No contact'); ?></span>
              <?php endif; ?>
            </td>
            <td data-label="<?php echo $t('cart_value', 'Cart Value'); ?>">
              <span class="value-text"><?php 
                if (!isset($currency_symbol) || !isset($currency_position)) {
                  require_once __DIR__ . '/../../../config/database.php';
                  require_once __DIR__ . '/../../../config/settings.php';
                  $database = new Database();
                  $conn = $database->getConnection();
                  $settings = new Settings($conn);
                  $currency = $settings->getSetting('currency', 'USD');
                  $custom_currency = $settings->getSetting('custom_currency', '');
                  if ($currency === 'CUSTOM' && !empty($custom_currency)) {
                    $currency_symbol = $custom_currency;
                  } else {
                    $currency_symbol = $currency === 'USD' ? '$' : ($currency === 'EUR' ? '€' : ($currency === 'GBP' ? '£' : ($currency === 'TND' ? 'TND' : $currency . ' ')));
                  }
                  $currency_position = $settings->getSetting('currency_position', 'left');
                }
                echo $currency_position === 'left' ? $currency_symbol . number_format($cart['cart_value'], 2) : number_format($cart['cart_value'], 2) . ' ' . $currency_symbol;
              ?></span>
            </td>
            <td data-label="<?php echo $t('items'); ?>"><span class="items-count"><?php echo (int)$cart['items']; ?></span></td>
            <td data-label="<?php echo $t('date'); ?>"><span class="date-text"><?php echo htmlspecialchars($cart['date']); ?></span></td>
            <td class="actions-cell" data-label="<?php echo $t('actions'); ?>">
              <button class="btn btn-sm ghost js-edit-cart"
                data-cart-id="<?php echo (int)$cart['id']; ?>"
                data-cart-name="<?php echo htmlspecialchars($cart['name'] ?? ''); ?>"
                data-cart-email="<?php echo htmlspecialchars($email); ?>"
                data-cart-phone="<?php echo htmlspecialchars($phone); ?>">
                <i class="far fa-edit"></i>
                <span><?php echo $t('edit'); ?></span>
              </button>
              <button class="btn btn-sm success js-convert-cart"
                data-cart-id="<?php echo (int)$cart['id']; ?>">
                <i class="fas fa-shopping-bag"></i>
                <span><?php echo $t('convert', 'Convert'); ?></span>
              </button>
            </td>
          </tr>
        <?php endforeach; ?>

        <?php foreach ($anonymousCarts as $cart): ?>
          <tr class="cart-row cart-row-anonymous" data-cart-id="<?php echo (int)$cart['id']; ?>">
            <td data-label="<?php echo $t('customer', 'Customer'); ?>">
              <div class="customer-cell">
                <span class="customer-name"><?php echo htmlspecialchars($cart['name'] ?: $t('anonymous', 'Anonymous')); ?></span>
                <span class="contact-tag contact-anonymous"><?php echo $t('anonymous', 'Anonymous'); ?></span>
              </div>
            </td>
            <td data-label="<?php echo $t('contact', 'Contact'); ?>">
              <span class="contact-chip muted" data-field="email"><?php echo $t('no_contact', 'No contact'); ?></span>
            </td>
            <td data-label="<?php echo $t('cart_value', 'Cart Value'); ?>"><span class="value-text"><?php 
              if (!isset($currency_symbol) || !isset($currency_position)) {
                require_once __DIR__ . '/../../../config/database.php';
                require_once __DIR__ . '/../../../config/settings.php';
                $database = new Database();
                $conn = $database->getConnection();
                $settings = new Settings($conn);
                $currency = $settings->getSetting('currency', 'USD');
                $custom_currency = $settings->getSetting('custom_currency', '');
                if ($currency === 'CUSTOM' && !empty($custom_currency)) {
                  $currency_symbol = $custom_currency;
                } else {
                  $currency_symbol = $currency === 'USD' ? '$' : ($currency === 'EUR' ? '€' : ($currency === 'GBP' ? '£' : ($currency === 'TND' ? 'TND' : $currency . ' ')));
                }
                $currency_position = $settings->getSetting('currency_position', 'left');
              }
              echo $currency_position === 'left' ? $currency_symbol . number_format($cart['cart_value'], 2) : number_format($cart['cart_value'], 2) . ' ' . $currency_symbol;
            ?></span></td>
            <td data-label="<?php echo $t('items'); ?>"><span class="items-count"><?php echo (int)$cart['items']; ?></span></td>
            <td data-label="<?php echo $t('date'); ?>"><span class="date-text"><?php echo htmlspecialchars($cart['date']); ?></span></td>
            <td class="actions-cell" data-label="<?php echo $t('actions'); ?>">
              <span class="contact-chip muted"><?php echo $t('add_contact_to_enable', 'Add contact to enable'); ?></span>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php if ($totalAnonymous > 0): ?>
    <div class="abandoned-footer">
      <button type="button" class="btn btn-outline-secondary btn-sm" id="toggleAnonymousCarts" data-state="hidden">
        <?php echo $t('show_more', 'Show'); ?> <?php echo $totalAnonymous; ?> <?php echo $t('more', 'more'); ?>
      </button>
    </div>
    <?php endif; ?>
  </div>
</div>
