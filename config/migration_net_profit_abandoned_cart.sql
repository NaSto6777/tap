-- Net Profit Tracking & Abandoned Cart Recovery
-- Run after migration_multitenant.sql
-- If a column already exists, you may see "Duplicate column" - safe to ignore.

-- 1. Add cost_price to products (for net profit calculation)
ALTER TABLE products ADD COLUMN cost_price DECIMAL(10,2) NULL DEFAULT NULL AFTER sale_price;

-- 2. Add shipping_cost_actual to orders (actual shipping cost for profit)
ALTER TABLE orders ADD COLUMN shipping_cost_actual DECIMAL(10,2) NULL DEFAULT NULL COMMENT 'Actual shipping cost for net profit calc';

-- 3. Abandoned carts: create table with full schema (multi-tenant, indexed)
-- If abandoned_carts already exists from analytics, run the ALTER block below manually instead.
CREATE TABLE IF NOT EXISTS abandoned_carts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    store_id INT NOT NULL DEFAULT 1,
    session_id VARCHAR(255) NOT NULL,
    customer_name VARCHAR(255) NULL,
    customer_email VARCHAR(255) NULL,
    customer_phone VARCHAR(100) NULL,
    cart_data TEXT NULL,
    cart_value DECIMAL(10,2) DEFAULT 0,
    status ENUM('pending','abandoned','completed') NOT NULL DEFAULT 'pending',
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_abandoned_store_created (store_id, created_at),
    INDEX idx_abandoned_status (store_id, status),
    UNIQUE KEY unique_store_session (store_id, session_id),
    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- If abandoned_carts existed before (from analytics) without store_id/status, run these:
-- ALTER TABLE abandoned_carts ADD COLUMN store_id INT NOT NULL DEFAULT 1 AFTER id;
-- ALTER TABLE abandoned_carts ADD COLUMN status ENUM('pending','abandoned','completed') NOT NULL DEFAULT 'pending' AFTER cart_value;
-- ALTER TABLE abandoned_carts ADD INDEX idx_abandoned_store_created (store_id, created_at);
-- ALTER TABLE abandoned_carts ADD INDEX idx_abandoned_status (store_id, status);
-- UPDATE abandoned_carts SET store_id = 1 WHERE store_id IS NULL OR store_id = 0;
