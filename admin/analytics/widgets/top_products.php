<?php
if (!isset($topProducts) || !is_array($topProducts) || count($topProducts)<1) {
  $topProducts = [
    ['name'=>'Wireless Earbuds','sales'=>57,'revenue'=>2847],
    ['name'=>'Smart Watch','sales'=>39,'revenue'=>5850],
    ['name'=>'TWS Headphones','sales'=>31,'revenue'=>1767],
    ['name'=>'Bluetooth Speaker','sales'=>29,'revenue'=>1275],
    ['name'=>'Mini Projector','sales'=>20,'revenue'=>1980],
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
?>
<div class="data-card">
  <div class="data-card-header">
    <h3><?php echo $t('top_performing_products', 'Top Performing Products'); ?></h3>
  </div>
  <div class="data-card-body">
    <div class="table-responsive">
      <table class="analytics-table">
        <thead>
          <tr>
            <th><?php echo $t('product'); ?></th>
            <th><?php echo $t('sales', 'Sales'); ?></th>
            <th><?php echo $t('revenue', 'Revenue'); ?></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($topProducts as $index => $prod) { ?>
          <tr>
            <td>
              <span class="rank-icon rank-top-<?php echo ($index < 3) ? ($index + 1) : 'default'; ?>"><?php echo $index + 1; ?></span>
              <?php echo htmlspecialchars($prod['name']); ?>
            </td>
            <td><?php echo intval($prod['sales']); ?></td>
            <td><?php 
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
              echo $currency_position === 'left' ? $currency_symbol . number_format($prod['revenue'], 2) : number_format($prod['revenue'], 2) . ' ' . $currency_symbol;
            ?></td>
          </tr>
        <?php } ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
