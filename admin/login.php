<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

startAdminSession();

if (isLoggedIn()) {
    redirect(getAdminRoot() . '/dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isLoginLocked()) {
        $error = 'Too many failed attempts. Please refresh the page and try again.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username === '' || $password === '') {
            $error = 'Please enter your username and password.';
        } else {
            try {
                $pdo  = getDb();
                $stmt = $pdo->prepare("SELECT * FROM lms_admins WHERE username = ? LIMIT 1");
                $stmt->execute([$username]);
                $admin = $stmt->fetch();

                if ($admin && password_verify($password, $admin['password_hash'])) {
                    loginAdmin($admin);
                    redirect(getAdminRoot() . '/dashboard.php');
                } else {
                    incrementLoginAttempts();
                    $error = 'Incorrect username or password.';
                }
            } catch (Exception $e) {
                $error = 'A database error occurred. Please check your configuration.';
            }
        }
    }
}

$appName = defined('APP_NAME') ? APP_NAME : "Luke's Music Lessons";
$root    = getAdminRoot();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — <?= e($appName) ?> Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= $root ?>/assets/admin.css">
</head>
<body>
<div class="login-wrapper">
    <div class="login-card">
        <div class="login-logo">
            <h1><?= e($appName) ?></h1>
            <p>Admin Panel — Sign In</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group" style="margin-bottom:16px;">
                <label for="username">Username</label>
                <input
                    type="text"
                    id="username"
                    name="username"
                    autocomplete="username"
                    value="<?= e($_POST['username'] ?? '') ?>"
                    required
                    autofocus
                >
            </div>

            <div class="form-group" style="margin-bottom:24px;">
                <label for="password">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    autocomplete="current-password"
                    required
                >
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;">
                Sign In
            </button>
        </form>
    </div>
</div>
</body>
</html>
