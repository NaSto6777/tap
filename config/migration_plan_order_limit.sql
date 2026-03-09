-- Plan-based order visibility: how many most recent orders a store can access.
-- Run after migration_multitenant.sql (and migration_super_admin_analytics.sql if used).
-- order_limit = NULL means unlimited; integer = max number of most recent orders visible.

ALTER TABLE subscription_plans ADD COLUMN order_limit INT NULL COMMENT 'Max visible orders (recent first); NULL = unlimited';

-- Example: Basic = last 100 orders, Pro = unlimited
UPDATE subscription_plans SET order_limit = 100 WHERE id = 1;
UPDATE subscription_plans SET order_limit = NULL WHERE id = 2;
