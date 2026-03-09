<?php
// Assumes $overviewStats is set in parent or provide fallback demo counts below
if (!isset($overviewStats)) $overviewStats = [
  'total_revenue'=>123456,
  'total_orders'=>4321,
  'conversion_rate'=>4.9,
  'abandoned_carts'=>18
];

// Initialize language if not already set
if (!function_exists('t')) {
    require_once __DIR__ . '/../../../config/language.php';
    Language::init();
    $t = function($key, $default = null) {
        return Language::t($key, $default);
    };
}
?>
<div class="overview-cards">
  <div class="overview-card">
    <div class="overview-card-icon revenue"><i class="fas fa-dollar-sign"></i></div>
    <div class="overview-card-content">
      <div class="overview-card-value"><?php 
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
        echo $currency_position === 'left' ? $currency_symbol . number_format($overviewStats['total_revenue'], 2) : number_format($overviewStats['total_revenue'], 2) . ' ' . $currency_symbol;
      ?></div>
      <div class="overview-card-label"><?php echo $t('total_revenue', 'Total Revenue'); ?></div>
    </div>
  </div>
  <div class="overview-card">
    <div class="overview-card-icon orders"><i class="fas fa-shopping-cart"></i></div>
    <div class="overview-card-content">
      <div class="overview-card-value"><?php echo number_format($overviewStats['total_orders']); ?></div>
      <div class="overview-card-label"><?php echo $t('total_orders', 'Total Orders'); ?></div>
    </div>
  </div>
  <div class="overview-card">
    <div class="overview-card-icon conversion"><i class="fas fa-percentage"></i></div>
    <div class="overview-card-content">
      <div class="overview-card-value"><?php echo round($overviewStats['conversion_rate']??0, 2); ?>%</div>
      <div class="overview-card-label"><?php echo $t('conversion_rate', 'Conversion Rate'); ?></div>
    </div>
  </div>
  <div class="overview-card">
    <div class="overview-card-icon abandoned"><i class="fas fa-shopping-basket"></i></div>
    <div class="overview-card-content">
      <div class="overview-card-value"><?php echo number_format($overviewStats['abandoned_carts']??0); ?></div>
      <div class="overview-card-label"><?php echo $t('abandoned_carts', 'Abandoned Carts'); ?></div>
    </div>
  </div>
</div>
