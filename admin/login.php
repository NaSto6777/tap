<?php
session_start();
require_once '../config/database.php';
require_once '../config/StoreContext.php';
require_once '../config/settings.php';

if (!defined('PLATFORM_BASE_DOMAIN')) {
    define('PLATFORM_BASE_DOMAIN', 'localhost');
}

if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: index.php');
    exit();
}

// Resolve store from subdomain so login is scoped to this store
$resolved = StoreContext::resolveFromRequest(PLATFORM_BASE_DOMAIN);
if (!$resolved) {
    header('HTTP/1.1 404 Not Found');
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Store not found</title></head><body><h1>Store not found</h1><p>This store does not exist or is not active.</p></body></html>';
    exit;
}
StoreContext::set($resolved['id'], $resolved['store']);
$store_id = StoreContext::getId();

$error = '';

if ($_POST) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (!empty($username) && !empty($password)) {
        $database = new Database();
        $conn = $database->getConnection();
        
        $query = "SELECT id, username, password FROM admin_users WHERE username = ? AND store_id = ? AND is_active = 1";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(1, $username);
        $stmt->bindParam(2, $store_id, PDO::PARAM_INT);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if (password_verify($password, $user['password'])) {
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_user_id'] = $user['id'];
                $_SESSION['admin_username'] = $user['username'];
                $_SESSION['admin_store_id'] = $store_id;
                try {
                    $conn->prepare("UPDATE admin_users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);
                } catch (PDOException $e) {
                    // last_login column may not exist yet (migration_super_admin_analytics.sql not run)
                }
                header('Location: index.php');
                exit();
            } else {
                $error = 'Invalid username or password';
            }
        } else {
            $error = 'Invalid username or password';
        }
    } else {
        $error = 'Please fill in all fields';
    }
}

// Get site settings for logo and branding (scoped to current store)
$database = new Database();
$conn = $database->getConnection();
$settings = new Settings($conn, $store_id);
$site_name = $settings->getSetting('site_name', 'Ecommerce Store');
$logo = $settings->getSetting('logo', '');
$primary_color = $settings->getSetting('primary_color', '#007bff');
$secondary_color = $settings->getSetting('secondary_color', '#6c757d');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Admin Login - <?php echo htmlspecialchars($site_name); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: <?php echo htmlspecialchars($primary_color); ?>;
            --primary-hover: <?php echo htmlspecialchars($primary_color); ?>;
            --secondary: <?php echo htmlspecialchars($secondary_color); ?>;
            --accent: #06b6d4;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --dark: #1f2937;
            --light: #f8fafc;
            --glass: rgba(255, 255, 255, 0.1);
            --glass-border: rgba(255, 255, 255, 0.2);
            --shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            min-height: 100vh;
            overflow-x: hidden;
            background: linear-gradient(135deg, <?php echo htmlspecialchars($primary_color); ?> 0%, <?php echo htmlspecialchars($secondary_color); ?> 100%);
            position: relative;
        }
        
        /* Animated background */
        .bg-animation {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, <?php echo htmlspecialchars($primary_color); ?> 0%, <?php echo htmlspecialchars($secondary_color); ?> 50%, <?php echo htmlspecialchars($primary_color); ?> 100%);
            background-size: 400% 400%;
            animation: gradientShift 15s ease infinite;
            z-index: 1;
        }
        
        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        /* Floating elements */
        .floating-elements {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 2;
        }
        
        .floating-element {
            position: absolute;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            animation: float 20s infinite linear;
        }
        
        @keyframes float {
            0% {
                transform: translateY(100vh) rotate(0deg);
                opacity: 0;
            }
            10% {
                opacity: 1;
            }
            90% {
                opacity: 1;
            }
            100% {
                transform: translateY(-100vh) rotate(360deg);
                opacity: 0;
            }
        }
        
        /* Main container */
        .login-container {
            position: relative;
            z-index: 10;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        
        .login-wrapper {
            width: 100%;
            max-width: 1200px;
            background: var(--glass);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 24px;
            box-shadow: var(--shadow);
            overflow: hidden;
            display: grid;
            grid-template-columns: 1fr 1fr;
            min-height: 600px;
        }
        
        /* Left side - Branding */
        .brand-section {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.2) 0%, rgba(255, 255, 255, 0.1) 100%);
            padding: 4rem 3rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .brand-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.1) 0%, rgba(255, 255, 255, 0.05) 100%);
            pointer-events: none;
        }
        
        .logo-container {
            position: relative;
            z-index: 1;
            margin-bottom: 2rem;
        }
        
        .logo {
            height: 120px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
        }
        
        .logo:hover {
            transform: translateY(-5px) scale(1.05);
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.2);
        }
        
        .logo img {
            height: 50px;
            width: auto;
            object-fit: contain;
        }
        
        .logo i {
            font-size: 4rem;
            color: white;
        }
        
        .brand-title {
            color: white;
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 1rem;
            text-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            position: relative;
            z-index: 1;
        }
        
        .brand-subtitle {
            color: rgba(255, 255, 255, 0.9);
            font-size: 1.2rem;
            font-weight: 500;
            margin-bottom: 2rem;
            position: relative;
            z-index: 1;
        }
        
        .brand-features {
            position: relative;
            z-index: 1;
            list-style: none;
            text-align: left;
        }
        
        .brand-features li {
            color: rgba(255, 255, 255, 0.8);
            font-size: 1rem;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .brand-features i {
            color: var(--accent);
            font-size: 1.1rem;
        }
        
        /* Right side - Login Form */
        .form-section {
            background: rgba(255, 255, 255, 0.95);
            padding: 4rem 3rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
        }
        
        .form-header {
            margin-bottom: 2.5rem;
        }
        
        .form-title {
            color: var(--dark);
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .form-subtitle {
            color: #6b7280;
            font-size: 1rem;
            font-weight: 400;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            display: block;
            color: var(--dark);
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .input-wrapper {
            position: relative;
        }
        
        .form-input {
            width: 100%;
            padding: 1rem 1rem 1rem 3rem;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 1rem;
            color: var(--dark);
            background: white;
            transition: var(--transition);
        }
        
        .form-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }
        
        .form-input::placeholder {
            color: #9ca3af;
        }
        
        .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6b7280;
            font-size: 1.1rem;
            z-index: 2;
            pointer-events: none;
            transition: var(--transition);
        }
        
        .form-input:focus + .input-icon,
        .input-wrapper:focus-within .input-icon {
            color: var(--primary);
        }
        
        /* Ensure icons are always visible */
        .input-wrapper {
            position: relative;
        }
        
        .input-wrapper .input-icon {
            display: block !important;
            visibility: visible !important;
            opacity: 1 !important;
        }
        
        .remember-forgot {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        
        .remember-me {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .checkbox {
            width: 18px;
            height: 18px;
            border: 2px solid #d1d5db;
            border-radius: 4px;
            cursor: pointer;
            transition: var(--transition);
            position: relative;
        }
        
        .checkbox:checked {
            background: var(--primary);
            border-color: var(--primary);
        }
        
        .checkbox:checked::after {
            content: '✓';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: white;
            font-size: 0.7rem;
            font-weight: bold;
        }
        
        .checkbox-label {
            color: var(--dark);
            font-size: 0.9rem;
            cursor: pointer;
        }
        
        .forgot-link {
            color: var(--primary);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            transition: var(--transition);
        }
        
        .forgot-link:hover {
            color: var(--primary-hover);
        }
        
        .btn-login {
            width: 100%;
            background: linear-gradient(135deg, var(--primary), var(--primary-hover));
            color: white;
            border: none;
            border-radius: 12px;
            padding: 1rem 2rem;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(99, 102, 241, 0.4);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .btn-login:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }
        
        .alert {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            border-radius: 12px;
            color: #dc2626;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.9rem;
        }
        
        .alert i {
            font-size: 1rem;
        }
        
        .form-footer {
            text-align: center;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid #e5e7eb;
        }
        
        .form-footer small {
            color: #6b7280;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        
        /* Loading animation */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Responsive design */
        
        /* Large tablets and small desktops */
        @media (max-width: 1024px) {
            .login-container {
                padding: 1.5rem;
            }
            
            .login-wrapper {
                max-width: 900px;
            }
            
            .brand-section {
                padding: 3rem 2.5rem;
            }
            
            .form-section {
                padding: 3rem 2.5rem;
            }
        }
        
        /* Tablets */
        @media (max-width: 768px) {
            .login-wrapper {
                grid-template-columns: 1fr;
                max-width: 500px;
                margin: 0 auto;
            }
            
            .brand-section {
                padding: 3rem 2rem;
                min-height: 300px;
                text-align: center;
            }
            
            .form-section {
                padding: 3rem 2rem;
            }
            
            .brand-title {
                font-size: 2rem;
                margin-bottom: 0.5rem;
            }
            
            .brand-subtitle {
                font-size: 1rem;
            }
            
            .logo {
                height: 100px;
                margin: 0 auto 1.5rem;
            }
            
            .logo img {
                height: 100px;
            }
            
            .logo i {
                font-size: 3rem;
            }
            
            .form-title {
                font-size: 1.75rem;
            }
            
            .form-subtitle {
                font-size: 0.9rem;
            }
        }
        
        /* Mobile landscape */
        @media (max-width: 640px) {
            .login-container {
                padding: 1rem;
            }
            
            .brand-section,
            .form-section {
                padding: 2rem 1.5rem;
            }
            
            .brand-title {
                font-size: 1.75rem;
            }
            
            .form-title {
                font-size: 1.5rem;
            }
            
            .input-group {
                margin-bottom: 1rem;
            }
            
            .input-group input {
                padding: 0.75rem 1rem;
                font-size: 0.9rem;
            }
            
            .input-icon {
                font-size: 1rem;
            }
            
            .btn-submit {
                padding: 0.75rem 1.5rem;
                font-size: 0.9rem;
            }
        }
        
        /* Mobile portrait */
        @media (max-width: 480px) {
            .login-container {
                padding: 0.5rem;
                min-height: 100vh;
            }
            
            .login-wrapper {
                max-width: 100%;
                margin: 0;
                border-radius: 0;
                box-shadow: none;
            }
            
            .brand-section {
                padding: 2rem 1rem;
                min-height: 250px;
            }
            
            .form-section {
                padding: 2rem 1rem;
            }
            
            .brand-title {
                font-size: 1.5rem;
                margin-bottom: 0.25rem;
            }
            
            .brand-subtitle {
                font-size: 0.9rem;
            }
            
            .logo {
                height: auto;
                width: 75%;
                margin: 0 auto 1rem;
            }
            
            .logo img {
                height: auto;
                width: 100%;
            }
            
            .logo i {
                font-size: 2.5rem;
            }
            
            .form-title {
                font-size: 1.25rem;
                margin-bottom: 0.25rem;
            }
            
            .form-subtitle {
                font-size: 0.8rem;
                margin-bottom: 1.5rem;
            }
            
            .input-group {
                margin-bottom: 0.75rem;
            }
            
            .input-group input {
                padding: 0.6rem 0.8rem;
                font-size: 0.85rem;
            }
            
            .input-icon {
                font-size: 0.9rem;
            }
            
            .form-options {
                flex-direction: column;
                gap: 0.5rem;
                margin-bottom: 1rem;
            }
            
            .btn-submit {
                padding: 0.6rem 1.25rem;
                font-size: 0.85rem;
            }
            
            .form-footer small {
                font-size: 0.75rem;
            }
        }
        
        /* Extra small devices */
        @media (max-width: 360px) {
            .brand-section {
                padding: 1.5rem 0.75rem;
                min-height: 200px;
            }
            
            .form-section {
                padding: 1.5rem 0.75rem;
            }
            
            .brand-title {
                font-size: 1.25rem;
            }
            
            .form-title {
                font-size: 1.1rem;
            }
            
            .logo {
                height: auto;
                width: 60%;
                margin: 0 auto 0.5rem;
            }
            
            .logo img {
                height: auto;
                width: 100%;
            }
            
            .logo i {
                font-size: 2rem;
            }
            
            .input-group input {
                padding: 0.5rem 0.6rem;
                font-size: 0.8rem;
            }
            
            .btn-submit {
                padding: 0.5rem 1rem;
                font-size: 0.8rem;
            }
        }
        
        /* Landscape orientation for mobile */
        @media (max-height: 500px) and (orientation: landscape) {
            .login-container {
                padding: 0.5rem;
            }
            
            .brand-section {
                min-height: 150px;
                padding: 1rem;
            }
            
            .form-section {
                padding: 1rem;
            }
            
            .logo {
                height: auto;
                width: 75%;
                margin: 0 auto 1rem;
            }
            
            .logo img {
                height: auto;
                width: 100%;
            }
            
            .logo i {
                font-size: 2rem;
            }
            
            .brand-title {
                font-size: 1.25rem;
                margin-bottom: 0.25rem;
            }
            
            .form-title {
                font-size: 1.25rem;
                margin-bottom: 0.25rem;
            }
        }
    </style>
</head>
<body>
    <!-- Animated background -->
    <div class="bg-animation"></div>
    
    <!-- Floating elements -->
    <div class="floating-elements" id="floatingElements"></div>
    
    <div class="login-container">
        <div class="login-wrapper">
            <!-- Left side - Branding -->
            <div class="brand-section">
                <div class="logo-container">
                    <div class="logo">
                        <?php if (!empty($logo) && file_exists('../' . $logo)): ?>
                            <img src="../<?php echo htmlspecialchars($logo); ?>" alt="<?php echo htmlspecialchars($site_name); ?>">
                        <?php else: ?>
                            <i class="fas fa-shield-alt"></i>
                        <?php endif; ?>
                    </div>
                </div>
                <h1 class="brand-title"><?php echo htmlspecialchars($site_name); ?></h1>
                <p class="brand-subtitle">Admin Dashboard</p>
                <ul class="brand-features">
                    <li><i class="fas fa-chart-line"></i> Real-time Analytics</li>
                    <li><i class="fas fa-users"></i> User Management</li>
                    <li><i class="fas fa-shopping-cart"></i> Order Processing</li>
                    <li><i class="fas fa-cog"></i> System Settings</li>
                </ul>
            </div>
            
            <!-- Right side - Login Form -->
            <div class="form-section">
                <div class="form-header">
                    <h2 class="form-title">Welcome Back</h2>
                    <p class="form-subtitle">Sign in to your admin account</p>
                </div>
                
                <?php if ($error): ?>
                    <div class="alert">
                        <i class="fas fa-exclamation-triangle"></i>
                        <div><?php echo htmlspecialchars($error); ?></div>
                    </div>
                <?php endif; ?>
                
                <form method="POST" id="loginForm">
                    <div class="form-group">
                        <label class="form-label" for="username">Username</label>
                        <div class="input-wrapper">
                            <i class="fas fa-user input-icon"></i>
                            <input type="text" class="form-input" id="username" name="username" placeholder="Enter your username" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="password">Password</label>
                        <div class="input-wrapper">
                            <i class="fas fa-lock input-icon"></i>
                            <input type="password" class="form-input" id="password" name="password" placeholder="Enter your password" required>
                        </div>
                    </div>
                    
                    <div class="remember-forgot">
                    <div class="remember-me">
                            <input type="checkbox" class="checkbox" id="remember" name="remember">
                            <label class="checkbox-label" for="remember">Remember me</label>
                        </div>
                        <a href="#" class="forgot-link">Forgot password?</a>
                    </div>
                    
                    <button type="submit" class="btn-login" id="loginBtn">
                        <span class="btn-text">Sign In</span>
                    </button>
                </form>
                
                <div class="form-footer">
                    <small>
                        <i class="fas fa-info-circle"></i>
                        Default: admin / admin123
                    </small>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Create floating elements
        function createFloatingElements() {
            const container = document.getElementById('floatingElements');
            const elementCount = 20;
            
            for (let i = 0; i < elementCount; i++) {
                const element = document.createElement('div');
                element.className = 'floating-element';
                
                const size = Math.random() * 80 + 20;
                const left = Math.random() * 100;
                const delay = Math.random() * 20;
                
                element.style.width = size + 'px';
                element.style.height = size + 'px';
                element.style.left = left + '%';
                element.style.animationDelay = delay + 's';
                element.style.animationDuration = (Math.random() * 15 + 20) + 's';
                
                container.appendChild(element);
            }
        }
        
        // Form submission with loading state
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const btn = document.getElementById('loginBtn');
            const btnText = btn.querySelector('.btn-text');
            
            btn.disabled = true;
            btnText.innerHTML = '<div class="loading"></div> Signing In...';
        });
        
        // Initialize floating elements
        createFloatingElements();
        
        // Add focus effects to inputs
        const inputs = document.querySelectorAll('.form-input');
        inputs.forEach(input => {
            input.addEventListener('focus', function() {
                this.style.transform = 'scale(1.02)';
            });
            
            input.addEventListener('blur', function() {
                this.style.transform = 'scale(1)';
            });
        });
    </script>
</body>
</html>