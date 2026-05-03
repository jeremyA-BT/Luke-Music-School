<?php
/**
 * Session authentication helpers.
 */

function startAdminSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        $cookieName = defined('SESSION_NAME') ? SESSION_NAME : 'lms_admin_sess';
        session_name($cookieName);
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
    }
}

function requireAuth(): void {
    startAdminSession();
    if (empty($_SESSION['admin_id'])) {
        header('Location: ' . getAdminRoot() . '/login.php');
        exit;
    }
}

function isLoggedIn(): bool {
    startAdminSession();
    return !empty($_SESSION['admin_id']);
}

function loginAdmin(array $admin): void {
    session_regenerate_id(true);
    $_SESSION['admin_id']   = $admin['id'];
    $_SESSION['admin_user'] = $admin['username'];
    $_SESSION['admin_role'] = $admin['role'];
    $_SESSION['login_attempts'] = 0;

    // Update last_login timestamp
    try {
        $pdo = getDb();
        $stmt = $pdo->prepare("UPDATE lms_admins SET last_login = NOW() WHERE id = ?");
        $stmt->execute([$admin['id']]);
    } catch (Exception $e) {
        // Non-critical — don't block login
    }
}

function logoutAdmin(): void {
    startAdminSession();
    $_SESSION = [];
    session_destroy();
}

function incrementLoginAttempts(): int {
    startAdminSession();
    $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
    return $_SESSION['login_attempts'];
}

function getLoginAttempts(): int {
    startAdminSession();
    return $_SESSION['login_attempts'] ?? 0;
}

function isLoginLocked(): bool {
    $max = defined('MAX_LOGIN_ATTEMPTS') ? MAX_LOGIN_ATTEMPTS : 5;
    return getLoginAttempts() >= $max;
}

/**
 * Returns the absolute URL path to the admin root (no trailing slash).
 */
function getAdminRoot(): string {
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    // Walk up from the current script to find /admin
    $parts = explode('/', trim($script, '/'));
    $adminIdx = array_search('admin', $parts);
    if ($adminIdx !== false) {
        return '/' . implode('/', array_slice($parts, 0, $adminIdx + 1));
    }
    return '/admin';
}
