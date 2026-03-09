# Technical & Visual Debugging Report

**Platform:** Multi-Tenant Ecommerce (temp1 storefront + admin)  
**Date:** March 8, 2026  
**Scope:** PHP/Server-side, Frontend/UX, JavaScript, Logic-Design gaps

---

## 1. List of Bugs

### PHP / Server-Side

| File | Line | Description |
|------|------|-------------|
| `templates/temp1/includes/header.php` | 80 | **Logo path:** `file_exists($logo)` uses path from settings; if logo is stored as `uploads/site/logo_xxx.png` but settings return a different format (e.g. full URL or relative path), `file_exists()` may fail depending on CWD. Logo may not display even when configured. |
| `templates/temp1/checkout.php` | 284 | **Undefined variable:** `$store_limit_reached` is used in `<?php if (!empty($store_limit_reached)): ?>` but the variable is set as `$store_limit_reached = false` only when `$store_id` exists. If `StoreContext::getId()` is null, the variable may be undefined in edge cases. |
| `templates/temp1/cart.php` | 158 | **Currency block:** `$currency` and `$price_prefix`/`$price_suffix` are defined inside the loop (lines 158–163) for each row; works but is redundant and could be moved outside the loop. |
| `index.php` | 99–104 | **Cart POST returns JSON for full form submit:** When the product form submits via normal POST (not AJAX), the server returns JSON and exits. The user sees raw `{"success":true}` instead of being redirected to the cart page. |

### AJAX / Endpoints

| File | Line | Description |
|------|------|-------------|
| `templates/temp1/includes/footer.php` | 102 | **Non-existent endpoint:** `fetch('index.php?action=get_cart_count')` — index.php uses `page`, not `action`. Request returns full HTML page; `response.json()` fails; cart count never updates, stays at 0. |
| `templates/temp1/newsletter_subscribe.php` | — | **Wrong URL:** Newsletter form fetches `newsletter_subscribe.php` (relative to current page). That file lives at `templates/temp1/newsletter_subscribe.php`. From root, `/newsletter_subscribe.php` does not exist → 404. Newsletter form never works. |

### JavaScript

| File | Line | Description |
|------|------|-------------|
| `templates/temp1/assets/js/script.js` | 117–124 | **Cart count always 0:** `updateCartCount()` always sets `cartCount.textContent = '0'` and never fetches real count. |
| `templates/temp1/assets/js/script.js` | 139–144 | **Add-to-cart:** Uses `response.text()` instead of `response.json()`. Server returns JSON; script doesn't parse it. No check for `success: false` or error message. |
| `templates/temp1/assets/js/script.js` | 88–94 | **Auto-dismiss alerts:** All `.alert:not(.alert-permanent)` are closed after 5 seconds. Contact/checkout success/error messages may disappear before user reads them. |
| `templates/temp1/product_view.php` | 327–328 | **Variant script:** `variant_id` and `variant_label_input` exist only when product has variants. Script is wrapped in `<?php if (!empty($variants)): ?>` so it only runs when variants exist — **no bug here**. |
| `templates/temp1/product_view.php` | 244 | **Quantity max for variants:** When product has variants, `max="<?php echo $product['stock_quantity']; ?>"` uses main product stock. For variant products, stock is in variants; main product stock can be 0. User may be limited to 0 or wrong max. |

### Incomplete Logic / Default Cases

| File | Line | Description |
|------|------|-------------|
| `templates/temp1/home.php` | — | **Featured products:** Uses `featured = 1` in SQL. Admin uses `featured` column. Schema matches. No issues found. |

---

## 2. Design Failures / "Khayeb" UI

### Storefront (temp1)

| Location | Issue |
|----------|-------|
| **Header cart badge** | Always shows "0" — cart count never updates. |
| **Newsletter form** | Submitting shows "An error occurred" or fails silently. |
| **Product form (full submit)** | User sees raw JSON `{"success":true}` instead of cart page. |
| **Product form (AJAX)** | Shop page uses `.add-to-cart`; product_view uses regular submit. No AJAX on product page — inconsistent. |
| **Alerts** | Success/error messages auto-dismiss after 5s; user may miss them. |
| **Cart table** | Has `table-responsive`; on very narrow screens, columns may still feel cramped. |
| **Gouvernorat/Ville selects** | Use Bootstrap `form-select` and `form-control` — styled correctly. Search inputs are separate; may look slightly redundant. |

### Admin

| Location | Issue |
|----------|-------|
| **Tables** | Many use `overflow-x: auto` and `min-width`; horizontal scroll on mobile. Acceptable but not ideal. |

### Empty States

| Page | Status |
|------|--------|
| Shop (0 products) | ✅ Clean empty message. |
| Cart (empty) | ✅ Clean empty state. |
| Admin dashboard (0 orders) | ✅ Empty state. |
| Admin products (0) | ✅ Empty state. |
| Checkout (empty cart) | ✅ Redirects to cart. |

---

## 3. Quick Fixes

### Fix 1: Cart count endpoint + `updateCartCount` (footer + index.php)

**index.php** — add right after the cart POST block (around line 107, before `$page = ...`):

```php
if (isset($_GET['action']) && $_GET['action'] === 'get_cart_count') {
    header('Content-Type: application/json');
    $count = 0;
    if (!empty($_SESSION['cart'])) {
        foreach ($_SESSION['cart'] as $item) {
            $count += (int)($item['quantity'] ?? 0);
        }
    }
    echo json_encode(['count' => $count]);
    exit;
}
```

**templates/temp1/includes/footer.php** — change fetch URL:

```javascript
fetch('index.php?action=get_cart_count')  // Already correct if action is added to index
```

### Fix 2: Product form redirect (full POST → cart page)

**index.php** — in the cart block, before `echo json_encode`:

```php
// After successful add/update/remove (around line 103)
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) 
    && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if (!$isAjax && $action === 'add') {
    header('Location: index.php?page=cart&added=1');
    exit;
}
header('Content-Type: application/json');
echo json_encode(['success' => true]);
exit;
```

### Fix 3: Newsletter form

**Option A:** Use index.php routing. Add `page=newsletter_subscribe` and handle POST in index.php, then include the newsletter logic.

**Option B:** Create `newsletter_subscribe.php` in project root that includes the logic:

```php
<?php
// Root: newsletter_subscribe.php
session_start();
require_once __DIR__ . '/config/plugin_helper.php';
require_once __DIR__ . '/config/StoreContext.php';
// ... (copy logic from templates/temp1/newsletter_subscribe.php, adapt paths)
```

**Option C:** Change fetch URL in footer to `templates/temp1/newsletter_subscribe.php` (relative to current page — may break if URL structure changes).

**Recommended:** Option B — create `newsletter_subscribe.php` in root.

### Fix 4: Add-to-cart (script.js) — parse JSON and handle errors

**templates/temp1/assets/js/script.js** — replace addToCart fetch block:

```javascript
fetch('index.php?page=cart', {
    method: 'POST',
    body: formData
})
.then(response => response.json())
.then(data => {
    if (data.success) {
        showAlert('Product added to cart!', 'success');
        if (typeof updateCartCount === 'function') updateCartCount();
    } else {
        showAlert(data.message || 'Error adding to cart', 'danger');
    }
    button.innerHTML = originalText;
    button.disabled = false;
})
.catch(error => {
    console.error('Error:', error);
    showAlert('Error adding product to cart', 'danger');
    button.innerHTML = originalText;
    button.disabled = false;
});
```

**Note:** Ensure `updateCartCount` is defined in the global scope (footer defines it). The script.js runs before footer; add a call to `updateCartCount()` after add-to-cart success, or use a custom event. Footer already defines `updateCartCount`; ensure it's called when cart changes. The footer script runs after script.js; both should be on the same page. The footer's `updateCartCount` is called on DOMContentLoaded. After add-to-cart, we need to call it again. The footer script can expose: `window.updateCartCount = updateCartCount;` and script.js can call `window.updateCartCount && window.updateCartCount();`.

### Fix 5: Preserve contact/checkout alerts (don’t auto-dismiss)

**templates/temp1/assets/js/script.js** — exclude important alerts:

```javascript
// Only auto-dismiss alerts that don't have .alert-permanent
const alerts = document.querySelectorAll('.alert:not(.alert-permanent):not(.alert-success):not(.alert-danger)');
```

Or add `alert-permanent` to contact/checkout success/error divs:

```html
<div class="alert alert-success alert-permanent ...">
```

### Fix 6: Product quantity max for variants

**templates/temp1/product_view.php** — around line 244:

```php
<?php
$qtyMax = $product['stock_quantity'];
if (!empty($variants)) {
    $qtyMax = 0;
    foreach ($variants as $v) {
        $qtyMax += (int)($v['stock_quantity'] ?? 0);
    }
    if ($qtyMax <= 0) $qtyMax = 99; // fallback
}
?>
<input type="number" ... max="<?php echo $qtyMax; ?>">
```

### Fix 7: Logo path — use absolute path for `file_exists`

**templates/temp1/includes/header.php** — around line 79:

```php
$logo = $settings->getSetting('logo', '');
$logoPath = $logo ? (strpos($logo, '/') === 0 ? $logo : __DIR__ . '/../../../' . ltrim($logo, '/')) : '';
if (!empty($logo) && file_exists($logoPath)) {
```

### Fix 8: CSS — cart table overflow on mobile

**templates/temp1/assets/css/style.css** — add:

```css
@media (max-width: 576px) {
    .table-responsive {
        font-size: 0.9rem;
    }
    .table-responsive .table td,
    .table-responsive .table th {
        padding: 0.5rem;
    }
    .table-responsive .input-group {
        width: 100% !important;
    }
}
```

---

## 4. Deprecated PHP / PHP 8.x Compatibility

| Check | Result |
|-------|--------|
| `mysql_*` | None found |
| `ereg` | None found |
| `split()` | None found |
| `create_function` | None found |
| `each()` | None found |
| Null coalescing `??` | Used |
| `isset()` / `empty()` | Used appropriately |

**Verdict:** No deprecated functions found. Codebase appears PHP 8.x compatible.

---

## 5. Summary

| Category | Count |
|----------|-------|
| **Critical bugs** | 4 (cart count, newsletter 404, product form JSON, add-to-cart parsing) |
| **Medium bugs** | 3 (logo path, quantity max, alert auto-dismiss) |
| **Design issues** | 2 (cart badge, form UX) |
| **Quick fixes provided** | 8 |

**Priority:** Implement Fix 1 (cart count), Fix 2 (product redirect), Fix 3 (newsletter), and Fix 4 (add-to-cart JSON) first. These have the most visible impact on user experience.
