-- Billing & Plan Management: order credits for placement limit top-up.
-- Run after migration_multitenant.sql and migration_order_allowance.sql (and migration_plan_order_limit.sql for order_limit on plans).

ALTER TABLE stores ADD COLUMN order_credits INT NOT NULL DEFAULT 0 COMMENT 'Extra orders (placement); consumed when period limit reached';
