<?php
/**
 * Shared layout helpers — header and footer for admin pages.
 *
 * Usage at the top of a page:
 *   layoutHeader('Page Title');
 *
 * Usage at the bottom of a page:
 *   layoutFooter();
 */

function layoutHeader(string $title, string $activeNav = ''): void {
    $appName = defined('APP_NAME') ? APP_NAME : "Luke's Music Lessons";
    $root = getAdminRoot();
    $user = $_SESSION['admin_user'] ?? 'Admin';

    $nav = [
        ['href' => $root . '/dashboard.php',       'label' => 'Dashboard',  'key' => 'dashboard', 'icon' => '▦'],
        ['href' => $root . '/invoices/index.php',   'label' => 'Invoices',   'key' => 'invoices',  'icon' => '◻'],
        ['href' => $root . '/clients/index.php',    'label' => 'Clients',    'key' => 'clients',   'icon' => '◎'],
        ['href' => $root . '/presets/index.php',    'label' => 'Presets',    'key' => 'presets',   'icon' => '◈'],
    ];
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?> — <?= htmlspecialchars($appName) ?> Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= $root ?>/assets/admin.css">
</head>
<body>
<div class="admin-shell">
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <h2><?= htmlspecialchars($appName) ?></h2>
            <p>Admin Panel</p>
        </div>
        <nav class="sidebar-nav">
            <div class="nav-section">Main</div>
            <?php foreach ($nav as $item): ?>
                <a href="<?= $item['href'] ?>" class="<?= $activeNav === $item['key'] ? 'active' : '' ?>">
                    <?= $item['label'] ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-footer">
            Signed in as <strong><?= htmlspecialchars($user) ?></strong><br>
            <a href="<?= $root ?>/logout.php">Sign out</a>
        </div>
    </aside>

    <div class="main-content">
        <header class="topbar">
            <h1><?= htmlspecialchars($title) ?></h1>
            <div class="topbar-actions" id="topbar-actions">
                <!-- Page-specific actions injected here -->
            </div>
        </header>
        <main class="page-body">
    <?php
}

function layoutFooter(): void {
    ?>
        </main>
    </div>
</div>
</body>
</html>
    <?php
}
