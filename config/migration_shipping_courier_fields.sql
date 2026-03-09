-- Shipping / Courier fields on orders
-- Run after migration_multitenant.sql
-- If a column already exists you may see a duplicate column error; it is safe to ignore.

ALTER TABLE orders
    ADD COLUMN courier_name VARCHAR(100) NULL,
    ADD COLUMN courier_tracking_number VARCHAR(191) NULL,
    ADD COLUMN courier_status_code INT NULL,
    ADD INDEX idx_courier_tracking (courier_tracking_number);

