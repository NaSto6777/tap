<?php
if (!isset($funnelData)) $funnelData = [
  'product_views'=>53,'add_to_cart'=>29,'checkout_start'=>19,'payment_start'=>11,'purchase_complete'=>8
];

// Initialize language if not already set
if (!function_exists('t')) {
    require_once __DIR__ . '/../../../config/language.php';
    Language::init();
    $t = function($key, $default = null) {
        return Language::t($key, $default);
    };
}

$labels = [
  $t('product_views', 'Product Views'),
  $t('add_to_cart', 'Add to Cart'),
  $t('checkout_start', 'Checkout Start'),
  $t('payment_start', 'Payment Start'),
  $t('purchase_complete', 'Purchase Complete')
];
$raw = [
  intval($funnelData['product_views']??0),
  intval($funnelData['add_to_cart']??0),
  intval($funnelData['checkout_start']??0),
  intval($funnelData['payment_start']??0),
  intval($funnelData['purchase_complete']??0)
];
?>
<div class="chart-card">
  <div class="chart-card-header">
    <h3><?php echo $t('conversion_funnel', 'Conversion Funnel'); ?></h3>
  </div>
  <div class="chart-card-body">
    <div class="conversion-funnel-flex">
      <canvas id="conversionFunnelChart"></canvas>
      <div id="conversionFunnelLegend"></div>
    </div>
  </div>
</div>
<script>
window.ConversionFunnelData = {labels:<?php echo json_encode($labels);?>,raw:<?php echo json_encode($raw);?>};
</script>
