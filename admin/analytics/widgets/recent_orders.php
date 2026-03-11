<?php
if (!isset($recentOrders) || !is_array($recentOrders) || count($recentOrders)<1) {
  $recentOrders = [
    ['order_id'=>1012, 'customer'=>'Amal B.', 'date'=>'2025-10-30', 'total'=>279.35, 'status'=>'Paid'],
    ['order_id'=>1011, 'customer'=>'Nidal S.', 'date'=>'2025-10-29', 'total'=>89.90, 'status'=>'Pending'],
    ['order_id'=>1010, 'customer'=>'Alex','date'=>'2025-10-28', 'total'=>148.20, 'status'=>'Paid'],
    ['order_id'=>1009, 'customer'=>'Anonymous','date'=>'2025-10-28', 'total'=>49.99, 'status'=>'Refunded']
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

function st_badge($status) {
  $status = strtolower($status);
  $color = $status=='paid' ? 'success' : ($status=='pending'?'warning':($status=='refunded'?'secondary':'info'));
  return '<span class="badge bg-'.$color.'">'.ucfirst($status).'</span>';
}
?>
<div class="data-card recent-orders-card">
  <div class="data-card-header">
    <div>
      <h3><?php echo $t('recent_orders', 'Recent Orders'); ?></h3>
      <p class="card-subtitle"><?php echo $t('latest_activity', 'Latest activity across your store'); ?></p>
    </div>
  </div>
  <div class="data-card-body">
    <div class="recent-orders-stats" id="recentOrdersStats">
      <div class="stat">
        <span class="stat-label"><?php echo $t('paid', 'Paid'); ?></span>
        <span class="stat-value">--</span>
      </div>
      <div class="stat">
        <span class="stat-label"><?php echo $t('pending'); ?></span>
        <span class="stat-value">--</span>
      </div>
      <div class="stat">
        <span class="stat-label"><?php echo $t('total'); ?></span>
        <span class="stat-value">--</span>
      </div>
    </div>
    <div class="orders-table-wrapper">
      <div class="table-responsive" id="recentOrdersContainer">
        <div class="empty-state"><?php echo $t('loading_recent_orders', 'Loading recent orders...'); ?></div>
      </div>
    </div>
  </div>
</div>
