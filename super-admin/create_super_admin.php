<?php
/**
 * One-time setup: create the first Super Admin user.
 * Run this once after migration, then log in at login.php.
 * Delete or restrict this file in production.
 */
require_once __DIR__ . '/../config/database.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $name = trim($_POST['name'] ?? 'Super Admin');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        try {
            $db = new Database();
            $conn = $db->getConnection();
            $stmt = $conn->prepare("SELECT id FROM super_admin_users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'A super admin with this email already exists.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO super_admin_users (email, password, name) VALUES (?, ?, ?)");
                $stmt->execute([$email, $hash, $name]);
                $message = 'Super Admin created. You can now <a href="login.php">log in</a>. Consider deleting or protecting this file.';
            }
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage() . '. Make sure you have run config/migration_multitenant.sql first.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Super Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center min-vh-100">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="card shadow">
                    <div class="card-body p-4">
                        <h4 class="card-title mb-4">Create Super Admin</h4>
                        <?php if ($message): ?>
                        <div class="alert alert-success"><?php echo $message; ?></div>
                        <?php elseif ($error): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        <?php if (!$message): ?>
                        <form method="post">
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Password</label>
                                <input type="password" name="password" class="form-control" required minlength="6">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Name (optional)</label>
                                <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($_POST['name'] ?? 'Super Admin'); ?>">
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Create Super Admin</button>
                        </form>
                        <?php endif; ?>
                        <p class="mt-3 small text-muted"><a href="login.php">Back to login</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
