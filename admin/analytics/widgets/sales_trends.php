<?php
if (!isset($salesTrendsData)) {
  $today = strtotime(date('Y-m-d'));
  $salesTrendsData = [];
  for ($i=14; $i>=0; $i--) {
    $day = date('Y-m-d', $today-($i*86400));
    $salesTrendsData[] = [
      'date' => $day,
      'revenue' => rand(1500,4000),
      'orders'  => rand(8,30)
    ];
  }
}

// Initialize language if not already set
if (!function_exists('t')) {
    require_once __DIR__ . '/../../../config/language.php';
    Language::init();
    $t = function($key, $default = null) {
        return Language::t($key, $default);
    };
}

$labels = array_column($salesTrendsData,'date');
$revenue = array_column($salesTrendsData,'revenue');
$orders  = array_column($salesTrendsData,'orders');
?>
<div class="chart-card">
  <div class="chart-card-header">
    <h3><?php echo $t('sales_trends', 'Sales Trends'); ?></h3>
  </div>
  <div class="chart-card-body">
    <canvas id="salesTrendsChart"></canvas>
    <div id="salesTrendsLegend" class="chart-legend"></div>
  </div>
</div>
<script>
window.SalesTrendsData = {labels:<?php echo json_encode($labels);?>,revenue:<?php echo json_encode($revenue);?>,orders:<?php echo json_encode($orders);?>};
</script>
