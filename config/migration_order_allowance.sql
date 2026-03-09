-- Sell by order: store can only see the last N orders until you top up (no subscription required).
-- order_view_allowance = how many most recent orders the store can see; NULL = use plan's order_limit if any, else unlimited.

ALTER TABLE stores ADD COLUMN order_view_allowance INT NULL COMMENT 'Max visible orders (sell-by-order); you top up when they pay';
