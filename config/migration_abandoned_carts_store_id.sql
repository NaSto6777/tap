-- Add store_id and status to existing abandoned_carts table (from analytics)
-- Run this if abandoned_carts already existed before migration_net_profit_abandoned_cart.sql
-- Safe to run multiple times: each ALTER will error if column exists - ignore those.

ALTER TABLE abandoned_carts ADD COLUMN store_id INT NOT NULL DEFAULT 1 AFTER id;
ALTER TABLE abandoned_carts ADD COLUMN status ENUM('pending','abandoned','completed') NOT NULL DEFAULT 'pending' AFTER cart_value;
ALTER TABLE abandoned_carts ADD INDEX idx_abandoned_store_created (store_id, created_at);
ALTER TABLE abandoned_carts ADD INDEX idx_abandoned_status (store_id, status);
UPDATE abandoned_carts SET store_id = 1 WHERE store_id IS NULL OR store_id = 0;
