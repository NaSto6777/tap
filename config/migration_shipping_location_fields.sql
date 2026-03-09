-- Extra shipping fields for couriers (Colissimo, First Delivery, etc.)
-- Run after migration_multitenant.sql

ALTER TABLE orders
    ADD COLUMN shipping_governorate VARCHAR(100) NULL,
    ADD COLUMN shipping_city VARCHAR(100) NULL,
    ADD COLUMN courier_status_text VARCHAR(255) NULL;

