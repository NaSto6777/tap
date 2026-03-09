<?php
require_once __DIR__ . '/../../config/StoreContext.php';
require_once __DIR__ . '/../../config/settings.php';
require_once __DIR__ . '/../../config/language.php';
require_once __DIR__ . '/../../config/plan_helper.php';

$store_id = StoreContext::getId();
$settings = new Settings(null, $store_id);

// Initialize language
Language::init();
$t = function($key, $default = null) {
    return Language::t($key, $default);
};

$database = new Database();
$conn = $database->getConnection();

// Get currency and symbol
$currency = $settings->getSetting('currency', 'USD');
$custom_currency = $settings->getSetting('custom_currency', '');
if ($currency === 'CUSTOM' && !empty($custom_currency)) {
    $currency_symbol = $custom_currency;
} else {
    $currency_symbol = $currency === 'USD' ? '$' : ($currency === 'EUR' ? '€' : ($currency === 'GBP' ? '£' : ($currency === 'TND' ? 'TND' : $currency . ' ')));
}
$currency_position = $settings->getSetting('currency_position', 'left');

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_cost') {
            $name = $_POST['cost_name'] ?? '';
            $amount = $_POST['cost_amount'] ?? 0;
            $description = $_POST['cost_description'] ?? '';
            $date_incurred = $_POST['date_incurred'] ?? date('Y-m-d');
            
            if (!empty($name) && $amount > 0) {
                $query = "INSERT INTO additional_costs (store_id, name, amount, description, date_incurred) VALUES (?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($query);
                $stmt->execute([$store_id, $name, $amount, $description, $date_incurred]);
            $success_msg = $t('cost_added_successfully', 'Cost added successfully!');
            header('Location: ?page=finance&success=' . urlencode($success_msg));
            exit;
        }
            }
            
    if ($action === 'delete_cost') {
            $cost_id = $_POST['cost_id'] ?? 0;
            if ($cost_id) {
                $query = "DELETE FROM additional_costs WHERE id = ? AND store_id = ?";
                $stmt = $conn->prepare($query);
                $stmt->execute([$cost_id, $store_id]);
            $success_msg = $t('cost_deleted_successfully', 'Cost deleted successfully!');
            header('Location: ?page=finance&success=' . urlencode($success_msg));
            exit;
            }
    }
}

// Get date range
$start_date = $_GET['start_date'] ?? date('Y-m-01'); // First day of current month
$end_date = $_GET['end_date'] ?? date('Y-m-t'); // Last day of current month

$vis_orders = PlanHelper::orderVisibilityCondition($conn, $store_id, 'orders');
$vis_o = PlanHelper::orderVisibilityCondition($conn, $store_id, 'o');
$orders_where_extra = $vis_orders['sql'] !== '' ? trim($vis_orders['sql']) : '';
$o_where_extra = $vis_o['sql'] !== '' ? trim($vis_o['sql']) : '';

// Revenue calculations (scoped to store and plan-visible orders)
$query = "SELECT 
            COUNT(*) as total_orders,
            SUM(total_amount) as total_revenue,
            SUM(CASE WHEN payment_status = 'paid' THEN total_amount ELSE 0 END) as paid_revenue,
            AVG(total_amount) as average_order_value
          FROM orders 
          WHERE store_id = ? AND DATE(created_at) BETWEEN ? AND ? $orders_where_extra";
$stmt = $conn->prepare($query);
$stmt->execute(array_merge([$store_id, $start_date, $end_date], $vis_orders['params']));
$revenue_stats = $stmt->fetch(PDO::FETCH_ASSOC);
$revenue_stats = is_array($revenue_stats) ? array_merge(['total_orders' => 0, 'total_revenue' => 0, 'paid_revenue' => 0, 'average_order_value' => 0], $revenue_stats) : ['total_orders' => 0, 'total_revenue' => 0, 'paid_revenue' => 0, 'average_order_value' => 0];
$revenue_stats['total_orders'] = (int)($revenue_stats['total_orders'] ?? 0);
$revenue_stats['paid_revenue'] = (float)($revenue_stats['paid_revenue'] ?? 0);
$revenue_stats['average_order_value'] = (float)($revenue_stats['average_order_value'] ?? 0);

// Product sales and costs (scoped to store and plan-visible orders)
$query = "SELECT 
            SUM(oi.quantity * oi.price) as product_revenue,
            SUM(oi.quantity * p.cost_price) as product_costs,
            SUM(oi.quantity) as units_sold
          FROM order_items oi
          JOIN products p ON oi.product_id = p.id
          JOIN orders o ON oi.order_id = o.id
          WHERE o.store_id = ? AND o.payment_status = 'paid' AND DATE(o.created_at) BETWEEN ? AND ? $o_where_extra";
$stmt = $conn->prepare($query);
$stmt->execute(array_merge([$store_id, $start_date, $end_date], $vis_o['params']));
$product_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Additional costs (scoped to store)
$query = "SELECT SUM(amount) as total_additional_costs 
          FROM additional_costs 
          WHERE store_id = ? AND DATE(date_incurred) BETWEEN ? AND ?";
$stmt = $conn->prepare($query);
$stmt->execute([$store_id, $start_date, $end_date]);
$additional_costs = $stmt->fetch(PDO::FETCH_ASSOC)['total_additional_costs'] ?? 0;

// Get additional costs list (scoped to store)
$query = "SELECT * FROM additional_costs 
          WHERE store_id = ? AND DATE(date_incurred) BETWEEN ? AND ? 
          ORDER BY date_incurred DESC";
$stmt = $conn->prepare($query);
$stmt->execute([$store_id, $start_date, $end_date]);
$costs_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate profit
$gross_profit = ($product_stats['product_revenue'] ?? 0) - ($product_stats['product_costs'] ?? 0);
$net_profit = $gross_profit - $additional_costs;
$profit_margin = $revenue_stats['paid_revenue'] > 0 ? (($net_profit / $revenue_stats['paid_revenue']) * 100) : 0;

// Top selling products (scoped to store and plan-visible orders)
$query = "SELECT p.name, p.sku, SUM(oi.quantity) as total_sold, 
          SUM(oi.total) as total_revenue,
          SUM(oi.quantity * p.cost_price) as total_cost,
          (SUM(oi.total) - SUM(oi.quantity * p.cost_price)) as profit
          FROM order_items oi
          JOIN products p ON oi.product_id = p.id
          JOIN orders o ON oi.order_id = o.id
          WHERE o.store_id = ? AND o.payment_status = 'paid' AND DATE(o.created_at) BETWEEN ? AND ? $o_where_extra
          GROUP BY p.id, p.name, p.sku
          ORDER BY profit DESC
          LIMIT 10";
$stmt = $conn->prepare($query);
$stmt->execute(array_merge([$store_id, $start_date, $end_date], $vis_o['params']));
$top_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- Modern Finance Dashboard Interface -->
<div class="finance-container">
    
    <!-- Success Messages -->
    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            <?php echo htmlspecialchars($_GET['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <!-- Date Filter -->
    <div class="date-filter-section">
        <form method="GET" class="date-filter-form">
            <input type="hidden" name="page" value="finance">
            
            <div class="date-inputs">
                <div class="date-group">
                    <label><?php echo $t('start_date', 'Start Date'); ?></label>
                    <input type="date" name="start_date" class="form-input" value="<?php echo $start_date; ?>">
                </div>
                <div class="date-separator">
                    <i class="fas fa-arrow-right"></i>
                    </div>
                <div class="date-group">
                    <label><?php echo $t('end_date', 'End Date'); ?></label>
                    <input type="date" name="end_date" class="form-input" value="<?php echo $end_date; ?>">
                    </div>
                </div>
            
            <button type="submit" class="btn-filter">
                <i class="fas fa-filter"></i>
                <?php echo $t('apply_filter', 'Apply Filter'); ?>
            </button>
            
            <div class="date-presets">
                <a href="?page=finance&start_date=<?php echo date('Y-m-01'); ?>&end_date=<?php echo date('Y-m-t'); ?>" 
                   class="preset-btn"><?php echo $t('this_month', 'This Month'); ?></a>
                <a href="?page=finance&start_date=<?php echo date('Y-m-01', strtotime('last month')); ?>&end_date=<?php echo date('Y-m-t', strtotime('last month')); ?>" 
                   class="preset-btn"><?php echo $t('last_month', 'Last Month'); ?></a>
                <a href="?page=finance&start_date=<?php echo date('Y-01-01'); ?>&end_date=<?php echo date('Y-12-31'); ?>" 
                   class="preset-btn"><?php echo $t('this_year', 'This Year'); ?></a>
            </div>
        </form>
        
        <div class="period-info">
            <i class="fas fa-calendar-alt"></i>
            <?php echo $t('showing_data_from', 'Showing data from'); ?> <?php echo date('M j', strtotime($start_date)); ?> <?php echo $t('to'); ?> <?php echo date('M j, Y', strtotime($end_date)); ?>
        </div>
    </div>

    <!-- Modern Statistics Grid -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-card-header">
                <div class="stat-card-icon primary">
                    <i class="fas fa-dollar-sign"></i>
                </div>
            </div>
            <div class="stat-card-content">
                <div class="stat-card-value"><?php echo $currency_position === 'left' ? $currency_symbol . number_format($revenue_stats['paid_revenue'] ?? 0, 2) : number_format($revenue_stats['paid_revenue'] ?? 0, 2) . ' ' . $currency_symbol; ?></div>
                <div class="stat-card-label"><?php echo $t('total_revenue', 'Total Revenue'); ?></div>
                <div class="stat-card-change positive">+<?php echo number_format($profit_margin, 1); ?>% <?php echo $t('margin', 'margin'); ?></div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-card-header">
                <div class="stat-card-icon success">
                    <i class="fas fa-chart-line"></i>
                </div>
            </div>
            <div class="stat-card-content">
                <div class="stat-card-value"><?php echo $currency_position === 'left' ? $currency_symbol . number_format($net_profit, 2) : number_format($net_profit, 2) . ' ' . $currency_symbol; ?></div>
                <div class="stat-card-label"><?php echo $t('net_profit', 'Net Profit'); ?></div>
                <div class="stat-card-change <?php echo $net_profit >= 0 ? 'positive' : 'negative'; ?>">
                    <?php echo $net_profit >= 0 ? $t('profitable', 'Profitable') : $t('loss', 'Loss'); ?>
                </div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-card-header">
                <div class="stat-card-icon warning">
                    <i class="fas fa-receipt"></i>
                </div>
            </div>
            <div class="stat-card-content">
                <div class="stat-card-value"><?php echo $currency_position === 'left' ? $currency_symbol . number_format($revenue_stats['average_order_value'] ?? 0, 2) : number_format($revenue_stats['average_order_value'] ?? 0, 2) . ' ' . $currency_symbol; ?></div>
                <div class="stat-card-label"><?php echo $t('avg_order_value', 'Avg Order Value'); ?></div>
                <div class="stat-card-change positive"><?php echo $t('good_aov', 'Good AOV'); ?></div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-card-header">
                <div class="stat-card-icon danger">
                    <i class="fas fa-shopping-cart"></i>
                </div>
            </div>
            <div class="stat-card-content">
                <div class="stat-card-value"><?php echo (int)($revenue_stats['total_orders'] ?? 0); ?></div>
                <div class="stat-card-label"><?php echo $t('total_orders', 'Total Orders'); ?></div>
                <div class="stat-card-change positive"><?php echo $t('active_sales', 'Active Sales'); ?></div>
            </div>
        </div>
    </div>

    <!-- Main Content Row -->
    <div class="finance-row">
    <!-- Cost Breakdown -->
        <div class="finance-col-6">
            <div class="finance-card">
            <div class="card-header">
                    <h3><i class="fas fa-calculator"></i> <?php echo $t('financial_breakdown', 'Financial Breakdown'); ?></h3>
            </div>
            <div class="card-body">
                    <div class="breakdown-list">
                        <div class="breakdown-item revenue">
                            <div class="breakdown-info">
                                <span class="breakdown-label"><?php echo $t('product_revenue', 'Product Revenue'); ?></span>
                                <span class="breakdown-desc"><?php echo $t('sales_from_products', 'Sales from products'); ?></span>
                            </div>
                            <span class="breakdown-value positive"><?php echo $currency_position === 'left' ? $currency_symbol . number_format($product_stats['product_revenue'] ?? 0, 2) : number_format($product_stats['product_revenue'] ?? 0, 2) . ' ' . $currency_symbol; ?></span>
                        </div>
                        
                        <div class="breakdown-item cost">
                            <div class="breakdown-info">
                                <span class="breakdown-label"><?php echo $t('product_costs', 'Product Costs'); ?></span>
                                <span class="breakdown-desc"><?php echo $t('cost_of_goods_sold', 'Cost of goods sold'); ?></span>
                            </div>
                            <span class="breakdown-value negative">-<?php echo $currency_position === 'left' ? $currency_symbol . number_format($product_stats['product_costs'] ?? 0, 2) : number_format($product_stats['product_costs'] ?? 0, 2) . ' ' . $currency_symbol; ?></span>
                        </div>
                        
                        <div class="breakdown-divider"></div>
                        
                        <div class="breakdown-item gross">
                            <div class="breakdown-info">
                                <span class="breakdown-label"><?php echo $t('gross_profit', 'Gross Profit'); ?></span>
                                <span class="breakdown-desc"><?php echo $t('revenue_minus_cogs', 'Revenue minus COGS'); ?></span>
                            </div>
                            <span class="breakdown-value"><?php echo $currency_position === 'left' ? $currency_symbol . number_format($gross_profit, 2) : number_format($gross_profit, 2) . ' ' . $currency_symbol; ?></span>
                        </div>
                        
                        <div class="breakdown-item cost">
                            <div class="breakdown-info">
                                <span class="breakdown-label"><?php echo $t('additional_costs', 'Additional Costs'); ?></span>
                                <span class="breakdown-desc"><?php echo $t('operating_expenses', 'Operating expenses'); ?></span>
                            </div>
                            <span class="breakdown-value negative">-<?php echo $currency_position === 'left' ? $currency_symbol . number_format($additional_costs, 2) : number_format($additional_costs, 2) . ' ' . $currency_symbol; ?></span>
                        </div>
                        
                        <div class="breakdown-divider"></div>
                        
                        <div class="breakdown-item net">
                            <div class="breakdown-info">
                                <span class="breakdown-label"><?php echo $t('net_profit', 'Net Profit'); ?></span>
                                <span class="breakdown-desc"><?php echo $t('final_profit', 'Final profit'); ?></span>
                            </div>
                            <span class="breakdown-value highlight"><?php echo $currency_position === 'left' ? $currency_symbol . number_format($net_profit, 2) : number_format($net_profit, 2) . ' ' . $currency_symbol; ?></span>
                        </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Additional Costs -->
        <div class="finance-col-6">
            <div class="finance-card">
            <div class="card-header">
                    <h3><i class="fas fa-file-invoice-dollar"></i> <?php echo $t('additional_costs', 'Additional Costs'); ?></h3>
                    <button class="add-btn" onclick="openAddCostModal()">
                        <i class="fas fa-plus"></i>
                    </button>
            </div>
            <div class="card-body">
                    <?php if (!empty($costs_list)): ?>
                        <div class="costs-list">
                            <?php foreach (array_slice($costs_list, 0, 10) as $cost): ?>
                                <div class="cost-item">
                                    <div class="cost-info">
                                        <div class="cost-name"><?php echo htmlspecialchars($cost['name']); ?></div>
                                        <?php if ($cost['description']): ?>
                                            <div class="cost-desc"><?php echo htmlspecialchars($cost['description']); ?></div>
                                        <?php endif; ?>
                                        <div class="cost-date">
                                            <i class="fas fa-calendar"></i>
                                            <?php echo date('M j, Y', strtotime($cost['date_incurred'])); ?>
                                        </div>
                                    </div>
                                    <div class="cost-actions">
                                        <div class="cost-amount"><?php echo $currency_position === 'left' ? $currency_symbol . number_format($cost['amount'], 2) : number_format($cost['amount'], 2) . ' ' . $currency_symbol; ?></div>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('<?php echo $t('delete_cost_confirm', 'Delete this cost?'); ?>')">
                                            <?php echo CsrfHelper::getTokenField(); ?>
                                            <input type="hidden" name="action" value="delete_cost">
                                            <input type="hidden" name="cost_id" value="<?php echo $cost['id']; ?>">
                                            <button type="submit" class="delete-btn">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-file-invoice fa-3x"></i>
                            <p><?php echo $t('no_additional_costs', 'No additional costs recorded'); ?></p>
                        </div>
                    <?php endif; ?>
            </div>
        </div>
    </div>
</div>

    <!-- Top Performing Products -->
    <div class="finance-card">
            <div class="card-header">
            <h3><i class="fas fa-trophy"></i> <?php echo $t('top_performing_products_profit', 'Top Performing Products by Profit'); ?></h3>
            </div>
            <div class="card-body">
            <?php if (!empty($top_products)): ?>
                <div class="products-table-container">
                    <table class="modern-table">
                        <thead>
                            <tr>
                                <th><?php echo $t('rank', 'Rank'); ?></th>
                                <th><?php echo $t('product'); ?></th>
                                <th class="hide-mobile"><?php echo $t('sku'); ?></th>
                                <th><?php echo $t('sold', 'Sold'); ?></th>
                                <th class="hide-mobile"><?php echo $t('revenue', 'Revenue'); ?></th>
                                <th class="hide-mobile"><?php echo $t('cost', 'Cost'); ?></th>
                                <th class="hide-mobile"><?php echo $t('profit', 'Profit'); ?></th>
                                <th class="show-mobile"><?php echo $t('financials', 'Financials'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top_products as $index => $product): ?>
                                <tr>
                                    <td>
                                        <div class="rank-badge rank-<?php echo min($index + 1, 3); ?>">
                                            #<?php echo $index + 1; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="product-info">
                                            <strong><?php echo htmlspecialchars($product['name']); ?></strong>
                                            <div class="product-sku show-mobile"><?php echo htmlspecialchars($product['sku']); ?></div>
                                        </div>
                                    </td>
                                    <td class="sku hide-mobile"><?php echo htmlspecialchars($product['sku']); ?></td>
                                    <td><?php echo $product['total_sold']; ?> <?php echo $t('units', 'units'); ?></td>
                                    <td class="revenue hide-mobile"><?php echo $currency_position === 'left' ? $currency_symbol . number_format($product['total_revenue'], 2) : number_format($product['total_revenue'], 2) . ' ' . $currency_symbol; ?></td>
                                    <td class="cost hide-mobile"><?php echo $currency_position === 'left' ? $currency_symbol . number_format($product['total_cost'], 2) : number_format($product['total_cost'], 2) . ' ' . $currency_symbol; ?></td>
                                    <td class="hide-mobile">
                                        <span class="profit <?php echo $product['profit'] >= 0 ? 'positive' : 'negative'; ?>">
                                            <?php echo $currency_position === 'left' ? $currency_symbol . number_format($product['profit'], 2) : number_format($product['profit'], 2) . ' ' . $currency_symbol; ?>
                                        </span>
                                    </td>
                                    <td class="financials-mobile show-mobile">
                                        <div class="financial-stack">
                                            <div class="financial-item">
                                                <span class="financial-label"><?php echo $t('revenue', 'Revenue'); ?>:</span>
                                                <span class="financial-value revenue"><?php echo $currency_position === 'left' ? $currency_symbol . number_format($product['total_revenue'], 2) : number_format($product['total_revenue'], 2) . ' ' . $currency_symbol; ?></span>
                                            </div>
                                            <div class="financial-item">
                                                <span class="financial-label"><?php echo $t('cost', 'Cost'); ?>:</span>
                                                <span class="financial-value cost"><?php echo $currency_position === 'left' ? $currency_symbol . number_format($product['total_cost'], 2) : number_format($product['total_cost'], 2) . ' ' . $currency_symbol; ?></span>
                                            </div>
                                            <div class="financial-item">
                                                <span class="financial-label"><?php echo $t('profit', 'Profit'); ?>:</span>
                                                <span class="financial-value profit <?php echo $product['profit'] >= 0 ? 'positive' : 'negative'; ?>">
                                                    <?php echo $currency_position === 'left' ? $currency_symbol . number_format($product['profit'], 2) : number_format($product['profit'], 2) . ' ' . $currency_symbol; ?>
                                                </span>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-chart-bar fa-3x"></i>
                    <p><?php echo $t('no_sales_data_period', 'No sales data available for the selected period'); ?></p>
            </div>
            <?php endif; ?>
    </div>
    </div>

</div>

<!-- Add Cost Modal -->
<div class="modal-overlay" id="addCostModal">
    <div class="modal-container">
            <div class="modal-header">
            <h2><i class="fas fa-plus-circle"></i> <?php echo $t('add_additional_cost', 'Add Additional Cost'); ?></h2>
            <button class="modal-close" onclick="closeAddCostModal()">
                <i class="fas fa-times"></i>
            </button>
            </div>
        
            <form method="POST">
                <?php echo CsrfHelper::getTokenField(); ?>
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_cost">
                    
                <div class="form-group">
                    <label><?php echo $t('cost_name', 'Cost Name'); ?> *</label>
                    <input type="text" name="cost_name" class="form-input" required
                           placeholder="<?php echo $t('cost_name_placeholder', 'e.g. Advertising, Rent, Utilities'); ?>">
                    </div>
                    
                <div class="form-group">
                    <label><?php echo $t('amount'); ?> *</label>
                    <div class="input-icon">
                        <i class="fas fa-dollar-sign"></i>
                        <input type="number" name="cost_amount" class="form-input" 
                               step="0.01" min="0" required placeholder="0.00">
                    </div>
                    </div>
                    
                <div class="form-group">
                    <label><?php echo $t('description'); ?></label>
                    <textarea name="cost_description" class="form-textarea" rows="3"
                              placeholder="<?php echo $t('cost_description_placeholder', 'Optional details about this cost...'); ?>"></textarea>
                    </div>
                    
                <div class="form-group">
                    <label><?php echo $t('date_incurred', 'Date Incurred'); ?></label>
                    <input type="date" name="date_incurred" class="form-input" 
                               value="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>
            
                <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeAddCostModal()">
                    <i class="fas fa-times"></i> <?php echo $t('cancel'); ?>
                </button>
                <button type="submit" class="btn-primary">
                    <i class="fas fa-save"></i> <?php echo $t('add_cost', 'Add Cost'); ?>
                </button>
                </div>
            </form>
        </div>
    </div>

<script>
function openAddCostModal() {
    document.getElementById('addCostModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeAddCostModal() {
    document.getElementById('addCostModal').classList.remove('active');
    document.body.style.overflow = '';
}

function exportFinance() {
    alert('<?php echo $t('export_coming_soon', 'Export functionality coming soon!'); ?>');
}

// Close modal on overlay click
document.getElementById('addCostModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeAddCostModal();
    }
});

// Auto-dismiss alerts
setTimeout(() => {
    document.querySelectorAll('.alert').forEach(alert => {
        alert.classList.remove('show');
        setTimeout(() => alert.remove(), 300);
    });
}, 5000);
</script>

<style>
/* Modern Finance Dashboard Styles */
:root {
    --primary: #000000;
    --success: #10b981;
    --danger: #ef4444;
    --warning: #f59e0b;
    --info: #3b82f6;
    --purple: #8b5cf6;
}

.finance-container {
    padding: 0;
}

/* Alert Messages */
.alert {
    padding: 1rem 1.5rem;
    border-radius: 6px;
    margin-bottom: 1.5rem;
    border: none;
    display: flex;
    align-items: center;
    font-weight: 500;
    animation: slideInDown 0.3s ease-out;
}

@keyframes slideInDown {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.alert-success {
    background: var(--color-success-light);
    color: #166534;
}

.alert .btn-close {
    margin-left: auto;
    opacity: 0.5;
}

.btn-primary {
    padding: 0 2rem;
    height: 48px;
    border-radius: 6px;
    border: none;
    background: var(--bg-card);
    color: var(--success);
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
}

/* Date Filter Section */
.date-filter-section {
    background: var(--bg-card);
    border-radius: 6px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.date-filter-form {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
    align-items: flex-end;
    margin-bottom: 1rem;
}

.date-inputs {
    display: flex;
    gap: 1rem;
    align-items: flex-end;
}

.date-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: var(--text-primary);
    font-size: 0.875rem;
}

.date-group .form-input {
    min-width: 180px;
}

.date-separator {
    display: flex;
    align-items: center;
    padding-bottom: 0.5rem;
    color: var(--text-tertiary);
}

.btn-filter {
    padding: 0 1.5rem;
    height: 48px;
    border-radius: 6px;
    border: none;
    background: var(--success);
    color: var(--text-inverse);
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-filter:hover {
    background: var(--color-success-dark);
}

.date-presets {
    display: flex;
    gap: 0.5rem;
    margin-left: auto;
}

.preset-btn {
    padding: 0.5rem 1rem;
    border-radius: 6px;
    background: var(--bg-secondary);
    color: var(--text-secondary);
    font-size: 0.875rem;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.3s ease;
}

.preset-btn:hover {
    background: var(--border-primary);
    color: var(--text-primary);
}

.period-info {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem;
    background: var(--color-success-light);
    border-radius: 6px;
    color: #166534;
    font-weight: 500;
    font-size: 0.9375rem;
}

/* Modern Statistics Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: var(--bg-card);
    border-radius: 6px;
    padding: 1.5rem;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
    border: 1px solid var(--border-primary);
}

.stat-card:hover {
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--color-primary-db), var(--color-accent));
    border-radius: 16px 16px 0 0;
}

.stat-card-header {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    margin-bottom: 0;
    min-height: 0;
    pointer-events: none;
}

.stat-card-icon {
    width: 48px;
    height: 48px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    color: white;
    flex-shrink: 0;
    position: absolute;
    top: 1.25rem;
    right: 1.25rem;
}

.stat-card-icon.primary {
    background: linear-gradient(135deg, var(--color-primary-db), var(--color-primary-db-hover));
}

.stat-card-icon.success {
    background: linear-gradient(135deg, #10b981, #059669);
}

.stat-card-icon.warning {
    background: linear-gradient(135deg, #f59e0b, #d97706);
}

.stat-card-icon.danger {
    background: linear-gradient(135deg, #ef4444, #dc2626);
}

.stat-card-content {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    padding-right: 4.5rem;
}

.stat-card-value {
    font-size: 2rem;
    font-weight: 700;
    color: var(--text-primary);
    line-height: 1;
}

.stat-card-label {
    color: var(--text-secondary);
    font-size: 0.875rem;
    font-weight: 500;
}

.stat-card-change {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.875rem;
    font-weight: 500;
}

.stat-card-change.positive {
    color: #10b981;
}

.stat-card-change.negative {
    color: #ef4444;
}

.stat-card-change i {
    font-size: 0.75rem;
}




/* Finance Row */
.finance-row {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.finance-col-6 {
    grid-column: span 1;
}

/* Finance Card */
.finance-card {
    background: var(--bg-card);
    border-radius: 6px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    overflow: hidden;
}

.card-header {
    padding: 1.5rem;
    border-bottom: 2px solid #f1f5f9;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.card-header h3 {
    margin: 0;
    font-size: 1.125rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    color: var(--text-primary);
}

.add-btn {
    width: 36px;
    height: 36px;
    border-radius: 6px;
    border: none;
    background: var(--success);
    color: var(--text-inverse);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
}

.add-btn:hover {
    background: var(--color-success-dark);
    transform: scale(1.05);
}

.card-body {
    padding: 1.5rem;
}

/* Breakdown List */
.breakdown-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.breakdown-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem;
    border-radius: 6px;
    background: var(--bg-secondary);
}

.breakdown-item.net {
    background: linear-gradient(135deg, #dcfce7 0%, #d1fae5 100%);
}

.breakdown-info {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.breakdown-label {
    font-weight: 600;
    color: var(--text-primary);
}

.breakdown-desc {
    font-size: 0.8125rem;
    color: var(--text-secondary);
}

.breakdown-value {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--text-primary);
}

.breakdown-value.positive {
    color: var(--success);
}

.breakdown-value.negative {
    color: var(--danger);
}

.breakdown-value.highlight {
    font-size: 1.5rem;
    color: var(--success);
}

.breakdown-divider {
    height: 2px;
    background: var(--border-primary);
    margin: 0.5rem 0;
}

/* Costs List */
.costs-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.cost-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem;
    background: var(--bg-secondary);
    border-radius: 6px;
    gap: 1rem;
}

.cost-info {
    flex: 1;
}

.cost-name {
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 0.25rem;
}

.cost-desc {
    font-size: 0.875rem;
    color: var(--text-secondary);
    margin-bottom: 0.5rem;
}

.cost-date {
    font-size: 0.8125rem;
    color: var(--text-tertiary);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.cost-actions {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.cost-amount {
    font-size: 1.125rem;
    font-weight: 700;
    color: #ef4444;
}

.delete-btn {
    width: 36px;
    height: 36px;
    border-radius: 6px;
    border: none;
    background: var(--color-error-light);
    color: #ef4444;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
}

.delete-btn:hover {
    background: var(--color-error);
    color: var(--text-inverse);
}

/* Modern Table */
.products-table-container {
    overflow-x: auto;
    border-radius: 6px;
    border: 1px solid var(--border-primary);
    background: var(--bg-card);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.modern-table {
    width: 100%;
    border-collapse: collapse;
    margin: 0;
}

.modern-table thead th {
    text-align: left;
    padding: 1rem 0.75rem;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    color: var(--text-secondary);
    letter-spacing: 0.05em;
    border-bottom: 2px solid var(--border-primary);
    background: var(--bg-secondary);
    position: sticky;
    top: 0;
    z-index: 10;
    white-space: nowrap;
}

.modern-table thead th:first-child {
    border-top-left-radius: 12px;
}

.modern-table thead th:last-child {
    border-top-right-radius: 12px;
}

.modern-table tbody td {
    padding: 1rem 0.75rem;
    border-bottom: 1px solid var(--border-primary);
    vertical-align: middle;
    white-space: nowrap;
}

.modern-table tbody tr:hover {
    background: var(--bg-secondary);
}

.modern-table tbody tr:last-child td {
    border-bottom: none;
}

.modern-table tbody tr:last-child td:first-child {
    border-bottom-left-radius: 12px;
}

.modern-table tbody tr:last-child td:last-child {
    border-bottom-right-radius: 12px;
}

/* Product Info */
.product-info {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.product-sku {
    font-family: monospace;
    color: var(--text-tertiary);
    font-size: 0.75rem;
    font-weight: 500;
}

/* Financial Stack for Mobile */
.financials-mobile {
    display: none;
}

.financial-stack {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.financial-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.25rem 0;
}

.financial-label {
    font-size: 0.75rem;
    color: var(--text-secondary);
    font-weight: 500;
}

.financial-value {
    font-size: 0.875rem;
    font-weight: 600;
}

.financial-value.revenue {
    color: #3b82f6;
}

.financial-value.cost {
    color: var(--text-secondary);
}

.financial-value.profit.positive {
    color: var(--success);
}

.financial-value.profit.negative {
    color: var(--danger);
}

/* Responsive Visibility */
.hide-mobile {
    display: table-cell;
}

.show-mobile {
    display: none;
}

.rank-badge {
    width: 36px;
    height: 36px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.875rem;
    color: var(--text-inverse);
}

.rank-badge.rank-1 {
    background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
}

.rank-badge.rank-2 {
    background: linear-gradient(135deg, #94a3b8 0%, #64748b 100%);
}

.rank-badge.rank-3 {
    background: linear-gradient(135deg, #d97706 0%, #b45309 100%);
}

.rank-badge:not(.rank-1):not(.rank-2):not(.rank-3) {
    background: linear-gradient(135deg, #64748b 0%, #475569 100%);
}

.sku {
    font-family: monospace;
    color: var(--text-secondary);
    font-size: 0.875rem;
}

.revenue {
    color: #3b82f6;
    font-weight: 600;
}

.cost {
    color: var(--text-secondary);
}

.profit.positive {
    color: var(--success);
    font-weight: 700;
}

.profit.negative {
    color: var(--danger);
    font-weight: 700;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 3rem 1rem;
    color: var(--text-tertiary);
}

.empty-state i {
    margin-bottom: 1rem;
}

.empty-state p {
    margin: 0;
    color: var(--text-secondary);
}

/* Modal */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.7);
    backdrop-filter: blur(4px);
    z-index: 10000;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 2rem;
}

.modal-overlay.active {
    display: flex;
}

.modal-container {
    background: var(--bg-card);
    border-radius: 6px;
    width: 100%;
    max-width: 600px;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    animation: modalSlideIn 0.3s ease-out;
}

@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: translateY(-50px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.modal-header {
    padding: 2rem;
    border-bottom: 2px solid #f1f5f9;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h2 {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.modal-close {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    border: none;
    background: var(--bg-tertiary);
    color: var(--text-secondary);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
}

.modal-close:hover {
    background: var(--border-primary);
    color: var(--success);
}

.modal-body {
    padding: 2rem;
    overflow-y: auto;
    flex: 1;
}

.modal-footer {
    padding: 1.5rem 2rem;
    border-top: 2px solid #f1f5f9;
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
}

/* Form Elements */
.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: var(--text-primary);
    font-size: 0.9375rem;
}

.form-input, .form-textarea {
    width: 100%;
    padding: 0.875rem 1rem;
    border: 2px solid var(--border-primary);
    border-radius: 6px;
    font-size: 0.9375rem;
    transition: all 0.3s ease;
    background: var(--bg-card);
    color: var(--text-primary);
}

.form-input:focus, .form-textarea:focus {
    outline: none;
    border-color: var(--border-focus);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
    background: var(--bg-primary);
}

.form-textarea {
    resize: vertical;
    font-family: inherit;
}

.input-icon {
    position: relative;
}

.input-icon i {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-tertiary);
}

.input-icon input {
    padding-left: 2.75rem;
}

.btn-secondary {
    padding: 0.875rem 2rem;
    border-radius: 6px;
    background: var(--bg-tertiary);
    color: var(--text-secondary);
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 0.75rem;
    cursor: pointer;
    transition: all 0.3s ease;
    border: none;
}

.btn-secondary:hover {
    background: var(--border-primary);
}

/* Responsive Design */
@media (max-width: 768px) {
    .btn-primary {
        flex: 1;
        min-width: 200px;
        justify-content: center;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 1rem;
    }
    
    .stat-card {
        padding: 1rem;
    }
    
    .stat-card-value {
        font-size: 1.75rem;
    }
    
    .date-filter-form {
        flex-direction: column;
        align-items: stretch;
    }
    
    .date-inputs {
        flex-direction: column;
    }
    
    .date-separator {
        display: none;
    }
    
    .date-presets {
        margin-left: 0;
    }
    
    /* Table Responsive */
    .products-table-container {
        margin: 0 -0.5rem;
        border-radius: 0;
        border-left: none;
        border-right: none;
    }

    
    .modern-table thead th {
        padding: 0.75rem 0.5rem;
        font-size: 0.7rem;
    }
    
    .modern-table tbody td {
        padding: 0.75rem 0.5rem;
        font-size: 0.875rem;
    }
    
    .rank-badge {
        width: 32px;
        height: 32px;
        font-size: 0.8rem;
    }
    
    .sku {
        font-size: 0.8rem;
    }
}

/* Mobile Large (576px - 767px) */
@media (max-width: 767px) and (min-width: 576px) {
    .btn-primary {
        width: 100%;
        height: 44px;
        justify-content: center;
        font-size: 0.875rem;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 0.5rem;
    }
    
    .stat-card {
        padding: 1rem;
        border-radius: 6px;
        min-height: 80px;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }
    
    .stat-card-header {
        margin-bottom: 0;
        justify-content: flex-end;
    }
    
    .stat-card-icon {
        width: 40px;
        height: 40px;
        font-size: 1rem;
        top: 1rem;
        right: 1rem;
    }
    
    .stat-card-content {
        align-items: flex-start;
        text-align: left;
        padding-right: 4rem;
    }
    
    .stat-card-value {
        font-size: 1.25rem;
        margin-bottom: 0.25rem;
    }
    
    .stat-card-label {
        font-size: 0.75rem;
        margin-bottom: 0.25rem;
    }
    
    .stat-card-change {
        font-size: 0.6875rem;
        justify-content: flex-start;
    }
    
    .stat-card-change i {
        font-size: 0.625rem;
    }
}

/* Mobile Small (up to 575px) */
@media (max-width: 575px) {
    .btn-primary {
        width: 100%;
        height: 40px;
        justify-content: center;
        font-size: 0.8125rem;
        padding: 0 1rem;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 0.375rem;
    }
    
    .stat-card {
        padding: 0.75rem;
        border-radius: 6px;
        min-height: 80px;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }
    
    .stat-card-header {
        margin-bottom: 0;
        justify-content: flex-end;
    }
    
    .stat-card-icon {
        width: 32px;
        height: 32px;
        font-size: 0.875rem;
        top: 0.85rem;
        right: 0.85rem;
    }
    
    .stat-card-content {
        align-items: flex-start;
        text-align: left;
        gap: 0.25rem;
        padding-right: 3.5rem;
    }
    
    .stat-card-value {
        font-size: 1rem;
        line-height: 1.1;
        margin-bottom: 0.125rem;
    }
    
    .stat-card-label {
        font-size: 0.6875rem;
        margin-bottom: 0.125rem;
        line-height: 1.2;
    }
    
    .stat-card-change {
        font-size: 0.625rem;
        justify-content: flex-start;
        line-height: 1.2;
    }
    
    .stat-card-change i {
        font-size: 0.5rem;
    }
}

/* Extra Small Mobile (up to 375px) */
@media (max-width: 375px) {
    .stats-grid {
        grid-template-columns: 1fr;
        gap: 0.25rem;
    }
    
    .stat-card {
        padding: 0.5rem 0.75rem;
        min-height: 60px;
        flex-direction: row;
        align-items: center;
        text-align: left;
    }
    
    .stat-card-header {
        margin-bottom: 0;
        margin-right: 0.75rem;
        justify-content: flex-end;
    }
    
    .stat-card-content {
        align-items: flex-start;
        text-align: left;
        flex: 1;
        padding-right: 3rem;
    }
    
    .stat-card-value {
        font-size: 1rem;
        margin-bottom: 0.125rem;
    }
    
    .stat-card-label {
        font-size: 0.6875rem;
        margin-bottom: 0.125rem;
    }
    
    .stat-card-change {
        font-size: 0.625rem;
        justify-content: flex-start;
    }
    
    .stat-card-icon {
        width: 30px;
        height: 30px;
        font-size: 0.9rem;
        top: 0.5rem;
        right: 0.5rem;
    }
    
    /* Table Mobile - Hide columns and show stacked financials */
    .hide-mobile {
        display: none;
    }
    
    .show-mobile {
        display: table-cell;
    }
    
    .financials-mobile {
        display: table-cell;
    }
    
    .products-table-container {
        margin: 0 -1rem;
        border-radius: 0;
        border-left: none;
        border-right: none;
        box-shadow: none;
    }
    

    
    .modern-table thead th {
        padding: 0.5rem 0.375rem;
        font-size: 0.65rem;
    }
    
    .modern-table tbody td {
        padding: 0.5rem 0.375rem;
        font-size: 0.8rem;
    }
    
    .rank-badge {
        width: 28px;
        height: 28px;
        font-size: 0.75rem;
    }
    
    .product-sku {
        font-size: 0.7rem;
    }
    
    .financial-stack {
        gap: 0.375rem;
    }
    
    .financial-item {
        padding: 0.125rem 0;
    }
    
    .financial-label {
        font-size: 0.7rem;
    }
    
    .financial-value {
        font-size: 0.8rem;
    }
}

@media (max-width: 375px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .stat-card {
        min-height: 60px;
        flex-direction: row;
        align-items: center;
        text-align: left;
    }
    
    .stat-card-header {
        margin-right: 0.75rem;
        margin-bottom: 0;
        justify-content: flex-start;
    }
    
    .stat-card-content {
        align-items: flex-start;
        text-align: left;
    }
    
    .stat-card-icon {
        width: 28px;
        height: 28px;
        font-size: 1rem;
    }
    
    .stat-card-value {
        font-size: 1rem;
    }
    
    .stat-card-label {
        font-size: 0.6875rem;
    }
    
    .stat-card-change {
        font-size: 0.625rem;
    }
    
    /* Table Extra Small Mobile */
    .products-table-container {
        margin: 0 -0.5rem;
    }
    

    
    .modern-table thead th {
        padding: 0.375rem 0.25rem;
        font-size: 0.6rem;
    }
    
    .modern-table tbody td {
        padding: 0.375rem 0.25rem;
        font-size: 0.75rem;
    }
    
    .rank-badge {
        width: 24px;
        height: 24px;
        font-size: 0.7rem;
    }
    
    .product-sku {
        font-size: 0.65rem;
    }
    
    .financial-stack {
        gap: 0.25rem;
    }
    
    .financial-item {
        padding: 0.125rem 0;
    }
    
    .financial-label {
        font-size: 0.65rem;
    }
    
    .financial-value {
        font-size: 0.75rem;
    }
    
    .modern-table tbody td:nth-child(2) {
        max-width: 120px;
        overflow: hidden;
        text-overflow: ellipsis;
    }
}

@media (max-width: 1024px) {
    .finance-row {
        grid-template-columns: 1fr;
    }
}
</style>
