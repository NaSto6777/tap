<?php
if (!isset($pageEngagement) || !is_array($pageEngagement) || count($pageEngagement) < 1) {
  $pageEngagement = [
    ['page_url' => '/index.php', 'page_title' => 'Home', 'view_count' => 865, 'unique_views' => 642, 'avg_time_on_page' => 78],
    ['page_url' => '/index.php?page=shop', 'page_title' => 'Shop', 'view_count' => 523, 'unique_views' => 401, 'avg_time_on_page' => 92],
    ['page_url' => '/index.php?page=product&id=12', 'page_title' => 'Product Detail', 'view_count' => 312, 'unique_views' => 244, 'avg_time_on_page' => 64],
    ['page_url' => '/index.php?page=cart', 'page_title' => 'Cart', 'view_count' => 205, 'unique_views' => 181, 'avg_time_on_page' => 48],
    ['page_url' => '/index.php?page=checkout', 'page_title' => 'Checkout', 'view_count' => 142, 'unique_views' => 119, 'avg_time_on_page' => 132],
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

if (!function_exists('analytics_format_duration')) {
  function analytics_format_duration($seconds) {
    $seconds = (int) $seconds;
    if ($seconds <= 0) {
      return '00:00';
    }
    $minutes = floor($seconds / 60);
    $remaining = $seconds % 60;
    return sprintf('%02d:%02d', $minutes, $remaining);
  }
}

$maxViews = 0;
foreach ($pageEngagement as $entry) {
  $maxViews = max($maxViews, (int)($entry['view_count'] ?? 0));
}
$maxViews = max($maxViews, 1);
?>
<div class="data-card page-engagement-card">
  <div class="data-card-header">
    <h3><?php echo $t('page_engagement', 'Page Engagement'); ?></h3>
    <span class="card-subtitle"><?php echo $t('traffic_vs_time', 'Traffic vs. average time on each page'); ?></span>
  </div>
  <div class="data-card-body">
    <div class="table-responsive">
      <table class="analytics-table page-engagement-table">
        <thead>
          <tr>
            <th><?php echo $t('page', 'Page'); ?></th>
            <th><?php echo $t('visitors', 'Visitors'); ?></th>
            <th><?php echo $t('unique', 'Unique'); ?></th>
            <th><?php echo $t('avg_stay', 'Avg stay'); ?></th>
            <th><?php echo $t('engagement', 'Engagement'); ?></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($pageEngagement as $entry):
          $views = (int)($entry['view_count'] ?? 0);
          $unique = (int)($entry['unique_views'] ?? 0);
          $avgTimeRaw = (float)($entry['avg_time_on_page'] ?? 0);
          $avgTimeSeconds = max(0, (int) round($avgTimeRaw / 1000));
          $title = trim($entry['page_title'] ?? '') ?: 'Unknown page';
          $url = trim($entry['page_url'] ?? '');
          $engagementPercent = min(100, max(5, ($avgTimeSeconds / 120) * 100));
          $trafficPercent = min(100, ($views / $maxViews) * 100);
        ?>
          <tr>
            <td>
              <div class="page-cell">
                <span class="page-title"><?php echo htmlspecialchars($title); ?></span>
                <?php if ($url): ?><span class="page-url"><?php echo htmlspecialchars($url); ?></span><?php endif; ?>
              </div>
            </td>
            <td>
              <div class="metric">
                <span class="metric-value"><?php echo number_format($views); ?></span>
                <span class="metric-label"><?php echo $t('views', 'Views'); ?></span>
                <div class="metric-bar traffic"><span style="width: <?php echo round($trafficPercent, 1); ?>%"></span></div>
              </div>
            </td>
            <td>
              <div class="metric">
                <span class="metric-value"><?php echo number_format($unique); ?></span>
                <span class="metric-label"><?php echo $t('unique', 'Unique'); ?></span>
              </div>
            </td>
            <td>
              <div class="metric">
                <span class="metric-value">
                  <?php echo analytics_format_duration($avgTimeSeconds); ?>
                </span>
                <span class="metric-label">mm:ss</span>
              </div>
            </td>
            <td>
              <div class="metric">
                <span class="metric-label"><?php echo $t('stay', 'Stay'); ?></span>
                <div class="metric-bar stay"><span style="width: <?php echo round($engagementPercent, 1); ?>%"></span></div>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
