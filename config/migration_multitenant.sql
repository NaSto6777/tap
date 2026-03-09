-- Multi-tenant SaaS migration
-- Run this once on your existing database. Backfills existing data to store_id = 1.

-- 1. New platform tables
CREATE TABLE IF NOT EXISTS subscription_plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    price_monthly DECIMAL(10,2) DEFAULT NULL,
    price_yearly DECIMAL(10,2) DEFAULT NULL,
    billing_interval ENUM('monthly','yearly') NOT NULL DEFAULT 'monthly',
    features TEXT,
    is_active TINYINT(1) DEFAULT 1,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS stores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    subdomain VARCHAR(63) NOT NULL UNIQUE,
    status ENUM('active','suspended','closed') NOT NULL DEFAULT 'active',
    subscription_id INT DEFAULT NULL,
    owner_email VARCHAR(255) DEFAULT NULL,
    default_language VARCHAR(10) DEFAULT 'en',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_subdomain (subdomain),
    INDEX idx_status (status)
);

CREATE TABLE IF NOT EXISTS subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    store_id INT NOT NULL,
    plan_id INT NOT NULL,
    status ENUM('trial','active','past_due','canceled','suspended') NOT NULL DEFAULT 'active',
    started_at TIMESTAMP NULL,
    current_period_start DATE NULL,
    current_period_end DATE NULL,
    canceled_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
    FOREIGN KEY (plan_id) REFERENCES subscription_plans(id),
    INDEX idx_store (store_id),
    INDEX idx_period_end (current_period_end)
);

CREATE TABLE IF NOT EXISTS super_admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    name VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 2. Seed default plan and default store
INSERT IGNORE INTO subscription_plans (id, name, slug, price_monthly, price_yearly, billing_interval, is_active, sort_order)
VALUES (1, 'Basic', 'basic-monthly', 29.00, 290.00, 'monthly', 1, 1),
       (2, 'Pro', 'pro-monthly', 79.00, 790.00, 'monthly', 1, 2);

INSERT IGNORE INTO stores (id, name, subdomain, status, default_language)
VALUES (1, 'Default Store', 'default', 'active', 'en');

INSERT IGNORE INTO subscriptions (id, store_id, plan_id, status, started_at, current_period_start, current_period_end)
VALUES (1, 1, 1, 'active', NOW(), CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 YEAR));

UPDATE stores SET subscription_id = 1 WHERE id = 1;
ALTER TABLE stores ADD CONSTRAINT fk_stores_subscription FOREIGN KEY (subscription_id) REFERENCES subscriptions(id) ON DELETE SET NULL;

-- 3. Add store_id to existing tables
-- settings: add store_id, backfill, then unique (store_id, setting_key)
ALTER TABLE settings ADD COLUMN store_id INT NULL AFTER id;
UPDATE settings SET store_id = 1 WHERE store_id IS NULL;
ALTER TABLE settings MODIFY store_id INT NOT NULL DEFAULT 1;
ALTER TABLE settings ADD INDEX idx_store_key (store_id, setting_key);
-- If the next line fails, run: SHOW INDEX FROM settings; then DROP INDEX <the_name_of_setting_key_index>;
ALTER TABLE settings DROP INDEX setting_key;
ALTER TABLE settings ADD UNIQUE KEY unique_store_setting (store_id, setting_key);

-- categories
ALTER TABLE categories ADD COLUMN store_id INT NULL AFTER id;
UPDATE categories SET store_id = 1 WHERE store_id IS NULL;
ALTER TABLE categories MODIFY store_id INT NOT NULL DEFAULT 1;
ALTER TABLE categories ADD INDEX idx_store (store_id);
ALTER TABLE categories ADD FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE;

-- products
ALTER TABLE products ADD COLUMN store_id INT NULL AFTER id;
UPDATE products SET store_id = 1 WHERE store_id IS NULL;
ALTER TABLE products MODIFY store_id INT NOT NULL DEFAULT 1;
ALTER TABLE products ADD INDEX idx_store (store_id);
ALTER TABLE products ADD FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE;

-- product_categories
ALTER TABLE product_categories ADD COLUMN store_id INT NULL;
UPDATE product_categories pc JOIN products p ON pc.product_id = p.id SET pc.store_id = p.store_id;
ALTER TABLE product_categories MODIFY store_id INT NOT NULL DEFAULT 1;
ALTER TABLE product_categories ADD INDEX idx_store (store_id);

-- product_images
ALTER TABLE product_images ADD COLUMN store_id INT NULL AFTER id;
UPDATE product_images pi JOIN products p ON pi.product_id = p.id SET pi.store_id = p.store_id;
ALTER TABLE product_images MODIFY store_id INT NOT NULL DEFAULT 1;
ALTER TABLE product_images ADD INDEX idx_store (store_id);

-- product_variants
ALTER TABLE product_variants ADD COLUMN store_id INT NULL AFTER id;
UPDATE product_variants pv JOIN products p ON pv.product_id = p.id SET pv.store_id = p.store_id;
ALTER TABLE product_variants MODIFY store_id INT NOT NULL DEFAULT 1;
ALTER TABLE product_variants ADD INDEX idx_store (store_id);

-- orders
ALTER TABLE orders ADD COLUMN store_id INT NULL AFTER id;
UPDATE orders SET store_id = 1 WHERE store_id IS NULL;
ALTER TABLE orders MODIFY store_id INT NOT NULL DEFAULT 1;
ALTER TABLE orders ADD INDEX idx_store (store_id);
ALTER TABLE orders ADD FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE;

-- order_items
ALTER TABLE order_items ADD COLUMN store_id INT NULL AFTER id;
UPDATE order_items oi JOIN orders o ON oi.order_id = o.id SET oi.store_id = o.store_id;
ALTER TABLE order_items MODIFY store_id INT NOT NULL DEFAULT 1;
ALTER TABLE order_items ADD INDEX idx_store (store_id);

-- admin_users
ALTER TABLE admin_users ADD COLUMN store_id INT NULL AFTER id;
UPDATE admin_users SET store_id = 1 WHERE store_id IS NULL;
ALTER TABLE admin_users MODIFY store_id INT NOT NULL DEFAULT 1;
ALTER TABLE admin_users ADD INDEX idx_store (store_id);
ALTER TABLE admin_users ADD FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE;

-- additional_costs
ALTER TABLE additional_costs ADD COLUMN store_id INT NULL AFTER id;
UPDATE additional_costs SET store_id = 1 WHERE store_id IS NULL;
ALTER TABLE additional_costs MODIFY store_id INT NOT NULL DEFAULT 1;
ALTER TABLE additional_costs ADD INDEX idx_store (store_id);
