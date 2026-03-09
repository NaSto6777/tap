<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (isset($_GET['debug']) && $_GET['debug'] === '1') {
    $_SESSION['signup_debug'] = true;
}
if (isset($_GET['signup_debug']) && $_GET['signup_debug'] === '0') {
    unset($_SESSION['signup_debug']);
}
$signup_success = isset($_GET['signup']) && $_GET['signup'] === 'success';
$signup_store = isset($_GET['store']) ? trim($_GET['store']) : '';
$signup_error = isset($_GET['signup']) && $_GET['signup'] === 'error' ? ($_GET['msg'] ?? 'Signup failed.') : '';
$base_domain = defined('PLATFORM_BASE_DOMAIN') ? PLATFORM_BASE_DOMAIN : 'localhost';
$default_order_allowance = 20; // Match signup/submit.php DEFAULT_ORDER_ALLOWANCE
$signup_debug = !empty($_SESSION['signup_debug']);
// Ensure signup form always posts to the correct URL (works with subfolders and rewrite)
$script_dir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
$base_path = $script_dir === '' || $script_dir === '.' || $script_dir === '\\' ? '' : rtrim(str_replace('\\', '/', $script_dir), '/');
$signup_form_action = $base_path . '/signup/submit.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ecommerce SaaS - Launch your online store</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .hero { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; padding: 4rem 0; }
        .feature-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
        .pricing-card { border: 2px solid #eee; border-radius: 12px; transition: border-color .2s; }
        .pricing-card:hover { border-color: #667eea; }
        .signup-section { background: #f8f9fa; padding: 3rem 0; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="#"><?php echo htmlspecialchars($base_domain); ?></a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="#features">Features</a>
                <a class="nav-link" href="#pricing">Pricing</a>
                <a class="nav-link" href="#signup">Get started</a>
            </div>
        </div>
    </nav>

    <section class="hero text-center">
        <div class="container">
            <h1 class="display-4 fw-bold mb-3">Launch your online store in minutes</h1>
            <p class="lead mb-4">Your store, your subdomain, your brand. Pay per order — no subscription.</p>
            <a class="btn btn-light btn-lg" href="#signup">Create my store</a>
        </div>
    </section>

    <section id="features" class="py-5">
        <div class="container">
            <h2 class="text-center mb-5">Everything you need to sell online</h2>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="d-flex gap-3">
                        <div class="feature-icon bg-primary bg-opacity-10 text-primary"><i class="fas fa-store"></i></div>
                        <div>
                            <h5>Your own store</h5>
                            <p class="text-muted mb-0">Get a dedicated subdomain (e.g. mystore.<?php echo htmlspecialchars($base_domain); ?>) and full storefront.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="d-flex gap-3">
                        <div class="feature-icon bg-success bg-opacity-10 text-success"><i class="fas fa-box"></i></div>
                        <div>
                            <h5>Products & categories</h5>
                            <p class="text-muted mb-0">Unlimited products, categories with multiple levels, variants, and images.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="d-flex gap-3">
                        <div class="feature-icon bg-info bg-opacity-10 text-info"><i class="fas fa-chart-line"></i></div>
                        <div>
                            <h5>Orders & analytics</h5>
                            <p class="text-muted mb-0">Manage orders, track revenue, and view analytics from your admin panel.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="pricing" class="py-5 bg-light">
        <div class="container">
            <h2 class="text-center mb-5">Pay per order</h2>
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="card pricing-card">
                        <div class="card-body text-center p-4">
                            <p class="lead mb-3">No subscription. You get <strong><?php echo (int)$default_order_allowance; ?> orders</strong> to start — you can view and manage them in your admin. When you’ve used them, you can’t see new orders until you contact us to add more.</p>
                            <ul class="list-unstyled text-start d-inline-block">
                                <li><i class="fas fa-check text-success me-2"></i> Your subdomain & storefront</li>
                                <li><i class="fas fa-check text-success me-2"></i> Full admin: products, orders, analytics</li>
                                <li><i class="fas fa-check text-success me-2"></i> First <?php echo (int)$default_order_allowance; ?> orders visible — then contact us to top up</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="signup" class="signup-section">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-6">
                    <h2 class="text-center mb-4">Create your store</h2>
                    <?php if ($signup_success): ?>
                    <?php
                    $admin_host = $signup_store ? ($signup_store . '.' . $base_domain) : '';
                    $admin_url = $admin_host ? ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $admin_host . rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\') . '/admin/') : '';
                    ?>
                    <div class="alert alert-success">
                        Your store has been created. Log in with your email and password at:
                        <?php if ($signup_store): ?>
                        <strong><a href="<?php echo htmlspecialchars($admin_url); ?>"><?php echo htmlspecialchars($signup_store . '.' . $base_domain); ?>/admin/</a></strong>
                        <?php else: ?>
                        your subdomain /admin/
                        <?php endif; ?>
                    </div>
                    <?php elseif ($signup_error): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($signup_error); ?></div>
                    <?php endif; ?>

                    <div class="card shadow">
                        <div class="card-body p-4">
                            <form method="post" action="<?php echo htmlspecialchars($signup_form_action); ?>" id="signup-form">
                                <?php if ($signup_debug): ?><input type="hidden" name="_debug" value="1"><div class="alert alert-warning small">Debug mode: errors will show details. <a href="?signup_debug=0">Turn off</a></div><?php endif; ?>
                                <div class="mb-3">
                                    <label class="form-label">Store name</label>
                                    <input type="text" name="store_name" class="form-control" required placeholder="My Store" maxlength="255">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Desired subdomain</label>
                                    <div class="input-group">
                                        <input type="text" name="subdomain" class="form-control" required placeholder="mystore" pattern="[a-zA-Z0-9\-]+" minlength="2" maxlength="63" id="subdomain">
                                        <span class="input-group-text">.<?php echo htmlspecialchars($base_domain); ?></span>
                                    </div>
                                    <small class="text-muted">Letters, numbers, hyphens only. Your store URL will be subdomain.<?php echo htmlspecialchars($base_domain); ?></small>
                                </div>
                                <input type="hidden" name="plan_id" value="0">
                                <div class="alert alert-info small mb-3">You get <strong><?php echo (int)$default_order_allowance; ?> orders</strong> to view in admin. When you need more, contact us and we’ll add them to your store.</div>
                                <div class="mb-3">
                                    <label class="form-label">Admin email</label>
                                    <input type="email" name="email" class="form-control" required placeholder="you@example.com">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Password</label>
                                    <input type="password" name="password" class="form-control" required minlength="6" placeholder="Min 6 characters">
                                </div>
                                <button type="submit" class="btn btn-primary w-100 btn-lg">Create my store</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <footer class="py-4 text-center text-muted small">
        <div class="container">&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($base_domain); ?></div>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('subdomain').addEventListener('input', function() {
            this.value = this.value.toLowerCase().replace(/[^a-z0-9\-]/g, '');
        });
    </script>
</body>
</html>
