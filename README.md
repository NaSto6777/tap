# Multi-Tenant Ecommerce Platform

PHP + MySQL ecommerce SaaS: multiple stores per installation, each with its own subdomain, data, and admin. One codebase; store-scoped products, orders, settings, and uploads.

## What’s included

- **Multi-tenant stores** – Each store has a subdomain (e.g. `mystore.myplatform.com`). Data is isolated by `store_id`.
- **Store admin** – Per-store dashboard: products, categories, orders, settings, finance, analytics, templates, plugins. Login is per store (e.g. `mystore.myplatform.com/admin/`).
- **Super Admin** – Platform owner panel: list stores, subscription status, suspend/activate stores, extend subscription (mark as paid). Separate login at `/super-admin/`.
- **Signup** – New merchants register at `/signup/`: email, password, store name, subdomain, plan. System creates the store, subscription, default settings, and first store admin.
- **Subscriptions & plans** – Tables: `subscription_plans`, `subscriptions`. Optional cron to mark expired subscriptions and suspend stores.
- **Multi-template frontend** – Two storefront themes (temp1, temp2): home, shop, product, cart, checkout, about, contact, terms, privacy. Store chooses active template in settings.
- **Per-store features** – Categories (unlimited levels), products with variants and images, orders and order items, settings (logo, colors, hero, currency, tax, shipping, SEO, social links), finance (revenue, costs, profit), analytics dashboard, plugins (e.g. Google Analytics, Meta Pixel), emails (order confirmation, status updates).

## Requirements

- PHP 7.4+
- MySQL 5.7+
- Web server (Apache/Nginx) with document root at project root

## Installation

### 1. Project and database

- Put the project in your web root (e.g. XAMPP `htdocs`).
- Create a MySQL database and set credentials in `config/database.php`.

### 2. Database schema (multi-tenant)

Run the migration once:

```bash
mysql -u user -p your_database < config/migration_multitenant.sql
```

This creates:

- `subscription_plans`, `stores`, `subscriptions`, `super_admin_users`
- Adds `store_id` to: `settings`, `categories`, `products`, `product_categories`, `product_images`, `product_variants`, `orders`, `order_items`, `admin_users`, `additional_costs`
- Seeds a default store (subdomain `default`) and two plans (Basic, Pro)

If the migration fails on `ALTER TABLE settings DROP INDEX setting_key`, run `SHOW INDEX FROM settings;` and drop the existing unique index on `setting_key`, then add the new unique key `(store_id, setting_key)` manually.

Optional: for "Last login" per store in the Super Admin dashboard, run:

```bash
mysql -u user -p your_database < config/migration_super_admin_analytics.sql
```

Optional: **sell by order** (no subscription) — store can only see the last N orders until you top up:

```bash
mysql -u user -p your_database < config/migration_order_allowance.sql
```

New signups get 20 orders (configurable in `signup/submit.php`). In Super Admin use **"+ Orders"** to add more when they pay. `stores.order_view_allowance` overrides any plan limit.

Optional: per-plan order limit (if you use subscriptions): run `config/migration_plan_order_limit.sql` and set `subscription_plans.order_limit`.

### 3. Super Admin

1. Open **`/super-admin/create_super_admin.php`** in the browser.
2. Enter email, password, and optional name; submit.
3. Log in at **`/super-admin/login.php`**.
4. Optionally delete or restrict `create_super_admin.php` after use.

### 4. Platform domain (optional)

For subdomain resolution in production, define the main domain before any output (e.g. in `index.php` and `admin/index.php`):

```php
define('PLATFORM_BASE_DOMAIN', 'myplatform.com');
```

If not set, it defaults to `localhost` and the default store uses subdomain `default`.

### 5. Store admin (default store)

- **URL**: `http://localhost/your-project/admin/` (or `http://default.myplatform.com/admin/` with wildcard DNS).
- Create the first store admin user in the database, e.g.:

  ```sql
  INSERT INTO admin_users (store_id, username, password, is_active)
  VALUES (1, 'admin', '$2y$10$...', 1);
  ```
  Use `password_hash('yourpassword', PASSWORD_DEFAULT)` in PHP to generate the hash.

- Then log in and configure the store (products, settings, template, etc.).

## Project structure

```
├── admin/                 # Store admin panel (per-store)
│   ├── assets/            # CSS, JS
│   ├── pages/             # Dashboard, products, categories, orders, settings, finance, analytics, templates, plugins
│   ├── index.php          # Admin entry (resolve store from subdomain)
│   ├── login.php
│   ├── logout.php
│   └── upload_handler.php
├── config/
│   ├── database.php       # DB connection
│   ├── settings.php       # Store-scoped settings (store_id)
│   ├── StoreContext.php   # Resolve store from subdomain, current store_id
│   ├── migration_multitenant.sql
│   ├── subscription_cron.php   # Run daily: past_due + suspend
│   ├── image_helper.php   # Store-scoped image paths
│   ├── language.php       # En/Ar
│   ├── email_helper.php
│   ├── plugin_helper.php
│   ├── analytics_*.php
│   ├── languages/
│   └── email_templates/
├── super-admin/           # Platform owner
│   ├── index.php          # List stores, suspend/activate, extend subscription
│   ├── login.php
│   ├── logout.php
│   └── create_super_admin.php
├── signup/                # New store registration
│   └── index.php
├── templates/
│   ├── temp1/             # Theme 1 (includes, assets, home, shop, cart, checkout, etc.)
│   └── temp2/             # Theme 2
├── uploads/               # Legacy and placeholder
│   └── stores/{store_id}/ # Per-store: products/, categories/, site/
├── index.php              # Storefront entry (resolve store, load template)
├── analytics_api.php
└── .htaccess
```

## URLs

| Purpose        | URL (example) |
|----------------|----------------|
| Storefront     | `http://default.myplatform.com/` or `http://mystore.myplatform.com/` |
| Store admin    | `http://mystore.myplatform.com/admin/` |
| Super Admin    | `https://myplatform.com/super-admin/login.php` |
| Create Super Admin | `https://myplatform.com/super-admin/create_super_admin.php` |
| Signup (new store) | `https://myplatform.com/signup/` |

## Subscriptions and cron

- **Plans**: Stored in `subscription_plans` (name, slug, price_monthly, price_yearly, billing_interval).
- **Per store**: One row in `subscriptions` (plan_id, status, current_period_start, current_period_end). Store has `subscription_id`.
- **Mark as paid**: In Super Admin, use “Extend (months)” for a store to set `current_period_end`.
- **Cron**: Run daily to mark expired subscriptions and suspend stores:

  ```bash
  php config/subscription_cron.php
  ```
  Example: `0 2 * * * php /path/to/config/subscription_cron.php`

## Security notes

- Store admin and Super Admin use separate sessions and tables (`admin_users` vs `super_admin_users`).
- Passwords hashed with `password_hash(..., PASSWORD_DEFAULT)`.
- Queries use prepared statements and store_id scoping.
- Remove or protect `super-admin/create_super_admin.php` after creating the first Super Admin.

## License

MIT.
