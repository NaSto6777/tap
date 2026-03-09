-- Optional: run after migration_multitenant.sql
-- Adds last_login to admin_users for Super Admin store analytics (last login per store).

ALTER TABLE admin_users ADD COLUMN last_login DATETIME NULL;
