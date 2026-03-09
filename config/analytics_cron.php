<?php
/**
 * Analytics Cron Job - Daily data aggregation and cleanup
 * This script should be run daily via system cron or manual trigger
 * 
 * Usage:
 * - Via command line: php config/analytics_cron.php
 * - Via web: config/analytics_cron.php?secret_key=YOUR_SECRET_KEY
 */

// Security check for web access
if (isset($_GET['secret_key'])) {
    $secretKey = $_GET['secret_key'];
    $expectedKey = 'analytics_cron_secret_2024'; // Change this to a secure random string
    
    if ($secretKey !== $expectedKey) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid secret key']);
        exit();
    }
    
    // Set content type for web access
    header('Content-Type: application/json');
}

// Include required files
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/analytics_helper.php';

try {
    $database = new Database();
    $conn = $database->getConnection();
    $analytics = new AnalyticsHelper($conn);
    
    $results = [];
    $startTime = microtime(true);
    
    // 1. Generate daily stats for yesterday
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $results['daily_stats'] = $analytics->generateDailyStats($yesterday);
    
    // 2. Update product analytics
    $productUpdateQuery = "UPDATE product_analytics pa 
                          SET views_count = (
                              SELECT COUNT(*) FROM analytics_events 
                              WHERE event_type = 'product_view' 
                              AND JSON_EXTRACT(event_data, '$.product_id') = pa.product_id
                              AND DATE(created_at) = ?
                          ),
                          add_to_cart_count = (
                              SELECT COUNT(*) FROM analytics_events 
                              WHERE event_type = 'add_to_cart' 
                              AND JSON_EXTRACT(event_data, '$.product_id') = pa.product_id
                              AND DATE(created_at) = ?
                          ),
                          purchase_count = (
                              SELECT COUNT(*) FROM analytics_events 
                              WHERE event_type = 'purchase_complete' 
                              AND JSON_EXTRACT(event_data, '$.product_id') = pa.product_id
                              AND DATE(created_at) = ?
                          )
                          WHERE EXISTS (
                              SELECT 1 FROM products p WHERE p.id = pa.product_id
                          )";
    
    $stmt = $conn->prepare($productUpdateQuery);
    $stmt->execute([$yesterday, $yesterday, $yesterday]);
    $results['product_analytics_updated'] = $stmt->rowCount();
    
    // 3. Update page analytics
    $pageUpdateQuery = "INSERT INTO page_analytics (page_url, page_title, view_count, unique_views) 
                        SELECT 
                            page_url,
                            JSON_EXTRACT(event_data, '$.page_title') as page_title,
                            COUNT(*) as view_count,
                            COUNT(DISTINCT session_id) as unique_views
                        FROM analytics_events 
                        WHERE event_type = 'page_view' 
                        AND DATE(created_at) = ?
                        GROUP BY page_url
                        ON DUPLICATE KEY UPDATE 
                        view_count = view_count + VALUES(view_count),
                        unique_views = unique_views + VALUES(unique_views),
                        last_updated = CURRENT_TIMESTAMP";
    
    $stmt = $conn->prepare($pageUpdateQuery);
    $stmt->execute([$yesterday]);
    $results['page_analytics_updated'] = $stmt->rowCount();
    
    // 4. Update button analytics
    $buttonUpdateQuery = "INSERT INTO button_analytics (button_label, button_selector, page_url, click_count) 
                          SELECT 
                              JSON_EXTRACT(event_data, '$.button_label') as button_label,
                              JSON_EXTRACT(event_data, '$.button_selector') as button_selector,
                              page_url,
                              COUNT(*) as click_count
                          FROM analytics_events 
                          WHERE event_type = 'button_click' 
                          AND DATE(created_at) = ?
                          GROUP BY button_label, button_selector, page_url
                          ON DUPLICATE KEY UPDATE 
                          click_count = click_count + VALUES(click_count)";
    
    $stmt = $conn->prepare($buttonUpdateQuery);
    $stmt->execute([$yesterday]);
    $results['button_analytics_updated'] = $stmt->rowCount();
    
    // 5. Identify abandoned carts (24+ hours old, not completed)
    $abandonedCartQuery = "UPDATE abandoned_carts 
                          SET reminded_at = CURRENT_TIMESTAMP,
                              reminder_count = reminder_count + 1
                          WHERE created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)
                          AND completed_at IS NULL
                          AND reminded_at IS NULL";
    
    $stmt = $conn->prepare($abandonedCartQuery);
    $stmt->execute();
    $results['abandoned_carts_reminded'] = $stmt->rowCount();
    
    // 6. Update customer analytics
    $customerUpdateQuery = "INSERT INTO customer_analytics (customer_email, first_visit, last_visit, total_orders, total_spent, avg_order_value, lifetime_value, is_returning)
                           SELECT 
                               o.customer_email,
                               MIN(o.created_at) as first_visit,
                               MAX(o.created_at) as last_visit,
                               COUNT(*) as total_orders,
                               SUM(o.total_amount) as total_spent,
                               AVG(o.total_amount) as avg_order_value,
                               SUM(o.total_amount) as lifetime_value,
                               CASE WHEN COUNT(*) > 1 THEN TRUE ELSE FALSE END as is_returning
                           FROM orders o
                           WHERE o.payment_status = 'paid'
                           AND DATE(o.created_at) = ?
                           GROUP BY o.customer_email
                           ON DUPLICATE KEY UPDATE
                           last_visit = GREATEST(last_visit, VALUES(last_visit)),
                           total_orders = total_orders + VALUES(total_orders),
                           total_spent = total_spent + VALUES(total_spent),
                           avg_order_value = total_spent / total_orders,
                           lifetime_value = total_spent,
                           is_returning = TRUE,
                           updated_at = CURRENT_TIMESTAMP";
    
    $stmt = $conn->prepare($customerUpdateQuery);
    $stmt->execute([$yesterday]);
    $results['customer_analytics_updated'] = $stmt->rowCount();
    
    // 7. Clean up old analytics data (optional - keep last 90 days by default)
    $retentionDays = 90; // Can be configured in settings
    $cleanupResult = $analytics->cleanupOldData($retentionDays);
    $results['old_data_cleaned'] = $cleanupResult;
    
    // 8. Update search analytics aggregation
    $searchAggQuery = "INSERT INTO search_analytics (search_term, results_count, session_id, created_at)
                       SELECT 
                           JSON_EXTRACT(event_data, '$.search_term') as search_term,
                           JSON_EXTRACT(event_data, '$.results_count') as results_count,
                           session_id,
                           created_at
                       FROM analytics_events 
                       WHERE event_type = 'search' 
                       AND DATE(created_at) = ?
                       ON DUPLICATE KEY UPDATE
                       results_count = VALUES(results_count)";
    
    $stmt = $conn->prepare($searchAggQuery);
    $stmt->execute([$yesterday]);
    $results['search_analytics_updated'] = $stmt->rowCount();
    
    // 9. Generate summary statistics
    $summaryQuery = "SELECT 
                        COUNT(*) as total_events_today,
                        COUNT(DISTINCT session_id) as unique_sessions_today,
                        COUNT(DISTINCT CASE WHEN event_type = 'page_view' THEN session_id END) as page_view_sessions,
                        COUNT(DISTINCT CASE WHEN event_type = 'add_to_cart' THEN session_id END) as cart_sessions,
                        COUNT(DISTINCT CASE WHEN event_type = 'purchase_complete' THEN session_id END) as purchase_sessions
                    FROM analytics_events 
                    WHERE DATE(created_at) = ?";
    
    $stmt = $conn->prepare($summaryQuery);
    $stmt->execute([$yesterday]);
    $summary = $stmt->fetch(PDO::FETCH_ASSOC);
    $results['summary'] = $summary;
    
    // 10. Calculate conversion rates
    $conversionRate = 0;
    if ($summary['page_view_sessions'] > 0) {
        $conversionRate = ($summary['purchase_sessions'] / $summary['page_view_sessions']) * 100;
    }
    $results['conversion_rate'] = round($conversionRate, 2);
    
    $endTime = microtime(true);
    $executionTime = round($endTime - $startTime, 2);
    
    $results['execution_time'] = $executionTime;
    $results['processed_date'] = $yesterday;
    $results['status'] = 'success';
    $results['timestamp'] = date('Y-m-d H:i:s');
    
    // Log the results
    error_log("Analytics cron completed successfully: " . json_encode($results));
    
    // Return results
    if (isset($_GET['secret_key'])) {
        echo json_encode($results);
    } else {
        echo "Analytics cron job completed successfully!\n";
        echo "Processed date: {$yesterday}\n";
        echo "Execution time: {$executionTime} seconds\n";
        echo "Daily stats generated: " . ($results['daily_stats'] ? 'Yes' : 'No') . "\n";
        echo "Product analytics updated: {$results['product_analytics_updated']} products\n";
        echo "Page analytics updated: {$results['page_analytics_updated']} pages\n";
        echo "Button analytics updated: {$results['button_analytics_updated']} buttons\n";
        echo "Abandoned carts reminded: {$results['abandoned_carts_reminded']} carts\n";
        echo "Customer analytics updated: {$results['customer_analytics_updated']} customers\n";
        echo "Old data cleaned: {$results['old_data_cleaned']} records\n";
        echo "Total events today: {$summary['total_events_today']}\n";
        echo "Unique sessions today: {$summary['unique_sessions_today']}\n";
        echo "Conversion rate: {$results['conversion_rate']}%\n";
    }
    
} catch (Exception $e) {
    $error = [
        'status' => 'error',
        'message' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    error_log("Analytics cron error: " . $e->getMessage());
    
    if (isset($_GET['secret_key'])) {
        http_response_code(500);
        echo json_encode($error);
    } else {
        echo "Analytics cron job failed: " . $e->getMessage() . "\n";
    }
    
    exit(1);
}
?>
