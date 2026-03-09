<?php
/**
 * Run daily (cron) to mark expired subscriptions as past_due and suspend stores.
 * Example cron: 0 2 * * * php /path/to/config/subscription_cron.php
 */
require_once __DIR__ . '/database.php';

$db = new Database();
$conn = $db->getConnection();

// Mark subscriptions past due when period end has passed
$conn->exec("UPDATE subscriptions SET status = 'past_due' WHERE status = 'active' AND current_period_end IS NOT NULL AND current_period_end < CURDATE()");

// Suspend stores that have been past_due (optional: add grace period)
$conn->exec("UPDATE stores s JOIN subscriptions sub ON s.subscription_id = sub.id SET s.status = 'suspended' WHERE sub.status IN ('past_due','canceled') AND s.status = 'active'");

echo "Subscription cron completed at " . date('Y-m-d H:i:s') . "\n";
