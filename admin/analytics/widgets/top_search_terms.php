<?php
if (!isset($topSearchTerms) || !is_array($topSearchTerms) || count($topSearchTerms)<1) {
  $topSearchTerms = [
    ['term'=>'S23 Ultra','count'=>104],
    ['term'=>'Earbuds','count'=>97],
    ['term'=>'Charger','count'=>74],
    ['term'=>'Bluetooth','count'=>37],
    ['term'=>'USB Hub','count'=>21],
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
    <h3><?php echo $t('top_search_terms', 'Top Search Terms'); ?></h3>
  </div>
  <div class="data-card-body">
    <div class="table-responsive">
      <table class="analytics-table">
        <thead>
          <tr>
            <th><?php echo $t('search_term', 'Search Term'); ?></th>
            <th><?php echo $t('count', 'Count'); ?></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($topSearchTerms as $index => $row) { ?>
          <tr>
            <td>
              <span class="rank-icon rank-top-<?php echo ($index < 3) ? ($index + 1) : 'default'; ?>"><?php echo $index + 1; ?></span>
              <?php echo htmlspecialchars($row['term']); ?>
            </td>
            <td><?php echo intval($row['count']); ?></td>
          </tr>
        <?php } ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
