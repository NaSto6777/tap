-- Store-scoped analytics: each store sees only its own funnel and events.
-- Run after migration_multitenant.sql. Tables analytics_events and user_funnel must exist.

-- analytics_events: add store_id (nullable; new rows get store_id from tracker)
ALTER TABLE analytics_events ADD COLUMN store_id INT NULL;
UPDATE analytics_events SET store_id = 1 WHERE store_id IS NULL;
ALTER TABLE analytics_events ADD INDEX idx_analytics_events_store (store_id);

-- user_funnel: add store_id (nullable; new rows get store_id from tracker)
ALTER TABLE user_funnel ADD COLUMN store_id INT NULL;
UPDATE user_funnel SET store_id = 1 WHERE store_id IS NULL;
ALTER TABLE user_funnel ADD INDEX idx_user_funnel_store (store_id);
