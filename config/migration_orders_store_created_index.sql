-- Composite index for orders listing (Admin Order list optimization)
-- Run once: improves ORDER BY created_at DESC with store_id filter

ALTER TABLE orders ADD INDEX idx_store_created (store_id, created_at);
