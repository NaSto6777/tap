<?php
/**
 * Abandoned Cart Cron
 * 1. Mark carts as 'abandoned' if not completed within 30 minutes
 * 2. Delete abandoned carts older than 30 days (prevent DB bloat)
 * Run daily: 0 3 * * * php /path/to/config/abandoned_cart_cron.php
 */
require_once __DIR__ . '/database.php';

$db = new Database();
$conn = $db->getConnection();

$results = ['marked_abandoned' => 0, 'deleted' => 0];

try {
    $chk = $conn->query("SHOW COLUMNS FROM abandoned_carts LIKE 'status'");
    $hasStatus = (bool) $chk->fetch();
} catch (PDOException $e) {
    $hasStatus = false;
}

if ($hasStatus) {
    // Mark as abandoned: created > 30 min ago, not completed
    $stmt = $conn->prepare("UPDATE abandoned_carts SET status = 'abandoned' 
        WHERE (status IS NULL OR status = 'pending') 
        AND completed_at IS NULL 
        AND created_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE)");
    $stmt->execute();
    $results['marked_abandoned'] = $stmt->rowCount();
}

// Delete abandoned carts older than 30 days
$stmt = $conn->prepare("DELETE FROM abandoned_carts WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
$stmt->execute();
$results['deleted'] = $stmt->rowCount();

echo "Abandoned cart cron completed at " . date('Y-m-d H:i:s') . "\n";
echo "Marked abandoned: {$results['marked_abandoned']}, Deleted (>30 days): {$results['deleted']}\n";
