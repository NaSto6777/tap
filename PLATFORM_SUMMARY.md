# Multi-Tenant SaaS Ecommerce Platform — Comprehensive Summary

**Document Version:** 1.0  
**Date:** March 8, 2026  
**Status:** Production-Ready Implementation

---

## 1. Platform Overview

### Core Architecture

The platform is built on a **single codebase, multi-tenant architecture** where all stores share the same application code and database, with strict data isolation via `store_id`.

| Component | Implementation |
|-----------|----------------|
| **Single Codebase** | One PHP codebase serves all stores. No per-store forks or deployments. |
| **Multi-Tenancy** | Every tenant-scoped table includes `store_id`. Tables: `settings`, `categories`, `products`, `product_categories`, `product_images`, `product_variants`, `orders`, `order_items`, `admin_users`, `additional_costs`, `analytics_events`, `user_funnel`. |
| **Wildcard Subdomain Routing** | Each store is accessed via its subdomain (e.g. `mystore.myplatform.com`). The main domain (`myplatform.com` or `www.myplatform.com`) shows the SaaS landing page and signup. |

### Store Resolution Flow

- **`StoreContext::resolveFromRequest()`** parses `HTTP_HOST`, extracts the subdomain, and looks up the store in `stores` where `subdomain = ?` and `status = 'active'`.
- **`StoreContext::set($storeId, $store)`** stores the resolved context for the request; all subsequent logic uses `StoreContext::getId()`.
- **Main domain** → `landing.php` (marketing + SaaS signup).  
- **Subdomain** → Storefront or Store Admin, both scoped to the resolved store.

### Entry Points

| URL Pattern | Purpose |
|-------------|---------|
| `myplatform.com/` | Landing page (SaaS marketing) |
| `mystore.myplatform.com/` | Storefront (home, shop, cart, checkout) |
| `mystore.myplatform.com/admin/` | Store Admin (per-store login) |
| `myplatform.com/super-admin/` | Super Admin (platform owner) |
| `myplatform.com/signup/` | New merchant registration |

---

## 2. Merchant Features (The Store Admin)

### Management Capabilities

All admin features are **per-store**, enforced via `StoreContext::getId()` and `Settings($conn, $store_id)`.

| Feature | Description |
|---------|-------------|
| **Products** | Full CRUD with `store_id` scoping. Multi-category assignment via `product_categories`. **Variants** (label, SKU, price override, stock) in `product_variants`. **Images** in `uploads/stores/{store_id}/products/{product_id}/` with drag-and-drop ordering and main image selection. Quick edit (AJAX) and toggle for `is_active` and `featured`. |
| **Categories** | Multi-level hierarchy (`level`, `parent_id`). Level-1 categories support image uploads. SEO (meta title, description, slug). Cannot delete categories with children. |
| **Orders** | Listing with status/payment filters, real-time search, bulk invoice print. Status updates with email notifications. **Subscription locking** (see Section 2.3). |
| **Analytics** | Revenue chart (Chart.js, last 6 months), conversion funnel, sales trends, top products, top search terms, page engagement, abandoned carts. All metrics respect **Order View Allowance** (Section 3). |

### Multi-Template System (temp1 vs temp2)

- **Active template** stored in `settings.active_template` (default: `temp1`).
- **temp1**: Classic Bootstrap storefront (home, shop, product, cart, checkout, about, contact, terms, privacy).
- **temp2**: Luxury theme with different layout and styling.
- Switching is done in **Design → Templates**; both templates share the same data layer (`Settings`, `StoreContext`, `BillingHelper`, `AnalyticsHelper`).

### Switchable Settings (Logo, Colors, SEO, etc.)

The **Settings** system (`config/settings.php`) is key-value per `(store_id, setting_key)`. Admin **Settings** page groups:

| Tab | Key Settings |
|-----|--------------|
| **General** | `site_name`, `site_description`, `logo`, `admin_language` (en/ar) |
| **Contact** | Email, phone, address |
| **Ecommerce** | `currency`, `custom_currency`, `currency_position`, `tax_rate`, `shipping_price`, `payment_methods` (COD, Stripe, PayPal, Flouci, etc.) |
| **Appearance** | `primary_color`, `secondary_color`, `menu_type` |
| **Social** | Facebook, Twitter, Instagram, LinkedIn, YouTube URLs |
| **SEO** | `seo_mode`, `auto_seo` |
| **Content** | `about_content`, `mission_statement`, hero texts, feature blocks, hero images (PC/mobile) |
| **Analytics** | `analytics_enabled`, `analytics_debug`, tracking options |

### Subscription Locking: Masked/Locked Orders

When a store’s subscription **expires** (`current_period_end < today` or no subscription):

1. **`BillingHelper::shouldLockOrder($store_id, $order_date)`** returns `true` for orders placed **after** `current_period_end 23:59:59`.
2. In the **Orders** admin UI:
   - Order row remains visible (ID, status, created_at).
   - A yellow **"Upgrade Required"** banner is shown.
   - **Masked fields** (replaced with `*** LOCKED ***`): `customer_name`, `customer_email`, `customer_phone`, `total_amount`.
3. Purpose: Merchants see that orders exist, but sensitive data is hidden until they renew.

---

## 3. The Billing & Order Limit System (The Unique Selling Point)

### Order Credits vs Order View Allowance

| Concept | Table/Column | Purpose |
|---------|--------------|---------|
| **Order Credits** | `stores.order_credits` | **Placement limit** — allows the store to **accept new orders** at checkout when the plan’s `order_limit` is exhausted. Each order placed using a credit decrements `order_credits` by 1. |
| **Order View Allowance** | `stores.order_view_allowance` | **Can See limit** — restricts how many (most recent) orders the merchant can **view** in the admin. Used for "sell by order" model: you top up when they pay. |

**Priority for View Limit:** `order_view_allowance` overrides `subscription_plans.order_limit` when both exist.

### BillingHelper Logic

| Method | Behavior |
|--------|----------|
| **`isExpired($store_id)`** | Returns `true` if no subscription or `current_period_end < today`. |
| **`canAcceptOrder($store_id)`** | Returns `true` if: (a) `order_credits > 0`, or (b) plan has no `order_limit` or `order_limit <= 0`, or (c) order count in current period < `order_limit`. |
| **`getPlacementState($store_id)`** | Returns `['allowed' => bool, 'use_credit' => bool]`. `use_credit` is `true` when the next order would consume a credit (count ≥ limit and credits > 0). |
| **`consumeCredit($store_id)`** | Decrements `order_credits` by 1 (called after placing an order that used a credit). |
| **`shouldLockOrder($store_id, $order_date)`** | Returns `true` if store is expired and order was placed after `current_period_end`. |

### Enforcement on the Storefront

- **Checkout** (`temp1/checkout.php`, `temp2/checkout.php`):
  - At page load: `store_limit_reached = !BillingHelper::canAcceptOrder($store_id)`.
  - If `store_limit_reached`: shows warning *"This store is temporarily not accepting new orders due to high volume."* and disables the checkout form.
  - On POST: re-checks `canAcceptOrder` to prevent bypass.
  - Uses `getPlacementState` to decide if the order consumes a credit; calls `consumeCredit` after insertion when needed.

---

## 4. Super Admin Capabilities

### Plan CRUD

- **Location:** `super-admin/plans.php`
- **Fields:** `name`, `slug`, `price_monthly`, `price_yearly`, `order_limit` (optional), `features` (JSON or line-separated), `is_active`, `sort_order`.
- **Order limit:** When `migration_plan_order_limit.sql` is applied, each plan can have a monthly order limit. `0` or `NULL` = unlimited.
- **Deletion:** Blocked if the plan is in use (`subscriptions.plan_id`).

### Store Management

| Action | Description |
|--------|-------------|
| **Suspend / Activate** | Toggle `stores.status` between `suspended` and `active`. |
| **Extend (months)** | Sets `subscriptions.current_period_start = CURDATE()`, `current_period_end = DATE_ADD(CURDATE(), INTERVAL ? MONTH)`, `status = 'active'`, and `stores.status = 'active'`. |
| **Extend +30 days** | One-click 30-day extension. |
| **Extend +365 days** | One-click 365-day extension. |
| **+ Order Credits** | Increments `stores.order_credits` (placement top-up). |
| **+ Orders (View Allowance)** | Increments `stores.order_view_allowance` (sell-by-order top-up). |

### Dashboard KPIs

At the top of `super-admin/index.php`:

| KPI | Query |
|-----|-------|
| **Total Stores** | `COUNT(*) FROM stores` |
| **Active Stores** | `COUNT(*) FROM stores WHERE status = 'active'` |
| **Active Subscriptions** | `subscriptions.status IN ('active','trial')` and `current_period_end >= CURDATE() OR NULL` |
| **Platform Total Orders** | Sum of paid orders across all stores |
| **Platform Total Revenue** | Sum of `total_amount` where `payment_status = 'paid'` across all stores |

Per-store columns: ID, name, subdomain, order count, paid revenue, product count, view allowance ("Can see N orders"), order credits, plan name, subscription status, period end, store status.

---

## 5. Technical Stack

| Layer | Technology |
|-------|------------|
| **Backend** | PHP 7.4+ (OOP + procedural), no framework. PDO for database access. |
| **Database** | MySQL 5.7+ |
| **Frontend** | Vanilla JS, jQuery 3, Bootstrap 5, Font Awesome 6, Chart.js |
| **Performance** | Lightweight stack for fast load times; no heavy frameworks. |

### Cron Job: Automatic Subscription Expiry

**File:** `config/subscription_cron.php`

**Recommended schedule:** `0 2 * * * php /path/to/config/subscription_cron.php` (daily at 2 AM)

**Actions:**
1. Mark subscriptions as `past_due` when `status = 'active'` and `current_period_end < CURDATE()`.
2. Suspend stores whose subscriptions are `past_due` or `canceled` and store status is still `active`.

---

## 6. Conclusion: Competitive Position in the 2026 Tunisian Market

### Why This Platform Is a Strong Competitor vs Fixed-Fee Platforms (e.g. Converty)

| Factor | This Platform | Fixed-Fee Platforms (e.g. Converty) |
|--------|---------------|-------------------------------------|
| **Pricing Model** | Flexible: subscription + order credits + sell-by-order. Merchants pay for what they use. | Fixed monthly fee regardless of volume. |
| **Order-Based Revenue** | Platform can monetize per order (credits, view allowance). Aligns with merchant success. | Revenue decoupled from merchant sales. |
| **Low Barrier to Entry** | Sell-by-order: new stores get a small order allowance; top up when they pay. No upfront subscription required. | Typically requires subscription from day one. |
| **Scalability** | Order credits and view allowance let merchants scale without changing plans. Super Admin can top up manually. | Often requires plan upgrades for more volume. |
| **Multi-Template** | Two themes (temp1, temp2) with per-store branding (logo, colors, SEO). | Varies; often single theme or limited customization. |
| **Technical Control** | Self-hosted, single codebase, full control over data and billing logic. | Often SaaS-only with less control. |
| **Tunisian Market Fit** | Supports TND, Flouci, COD; Arabic language; lightweight for local hosting. | May lack local payment and language support. |

**Summary:** The platform combines **multi-tenancy**, **flexible billing** (subscriptions + order credits + sell-by-order), **subscription locking**, and **Super Admin tools** into a single, maintainable codebase. For the 2026 Tunisian market, this makes it a strong alternative to fixed-fee solutions by offering usage-based pricing, lower entry barriers, and better alignment with merchant growth.

---

*End of Platform Summary*
