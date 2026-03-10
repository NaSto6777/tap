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
?>
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-card-body">
      <div class="stat-card-row">
        <div class="stat-card-main">
          <h5 class="stat-card-label"><?php echo $t('total_revenue', 'Total Revenue'); ?></h5>
          <span class="stat-card-value"><?php echo $currency_position === 'left' ? $currency_symbol . number_format($overviewStats['total_revenue'], 2) : number_format($overviewStats['total_revenue'], 2) . ' ' . $currency_symbol; ?></span>
          <p class="stat-card-footer positive"><i class="fas fa-chart-line"></i> <span><?php echo $t('revenue', 'Revenue'); ?></span></p>
        </div>
        <div class="stat-card-icon-wrap">
          <div class="stat-card-icon primary"><i class="fas fa-dollar-sign"></i></div>
        </div>
      </div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-card-body">
      <div class="stat-card-row">
        <div class="stat-card-main">
          <h5 class="stat-card-label"><?php echo $t('total_orders', 'Total Orders'); ?></h5>
          <span class="stat-card-value"><?php echo number_format($overviewStats['total_orders']); ?></span>
          <p class="stat-card-footer neutral"><?php echo $t('orders', 'Orders'); ?></p>
        </div>
        <div class="stat-card-icon-wrap">
          <div class="stat-card-icon success"><i class="fas fa-shopping-cart"></i></div>
        </div>
      </div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-card-body">
      <div class="stat-card-row">
        <div class="stat-card-main">
          <h5 class="stat-card-label"><?php echo $t('conversion_rate', 'Conversion Rate'); ?></h5>
          <span class="stat-card-value"><?php echo round($overviewStats['conversion_rate']??0, 2); ?>%</span>
          <p class="stat-card-footer neutral"><?php echo $t('conversion', 'Conversion'); ?></p>
        </div>
        <div class="stat-card-icon-wrap">
          <div class="stat-card-icon info"><i class="fas fa-percentage"></i></div>
        </div>
      </div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-card-body">
      <div class="stat-card-row">
        <div class="stat-card-main">
          <h5 class="stat-card-label"><?php echo $t('abandoned_carts', 'Abandoned Carts'); ?></h5>
          <span class="stat-card-value"><?php echo number_format($overviewStats['abandoned_carts']??0); ?></span>
          <p class="stat-card-footer <?php echo ($overviewStats['abandoned_carts']??0) > 0 ? 'negative' : 'positive'; ?>"><i class="fas fa-<?php echo ($overviewStats['abandoned_carts']??0) > 0 ? 'exclamation' : 'check'; ?>"></i> <span><?php echo ($overviewStats['abandoned_carts']??0) > 0 ? $t('needs_attention', 'Needs Attention') : $t('none', 'None'); ?></span></p>
        </div>
        <div class="stat-card-icon-wrap">
          <div class="stat-card-icon warning"><i class="fas fa-shopping-basket"></i></div>
        </div>
      </div>
    </div>
  </div>
</div>
