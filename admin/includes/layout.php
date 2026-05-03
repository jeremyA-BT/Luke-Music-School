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
        ['href' => $root . '/dashboard.php',       'label' => 'Dashboard',  'key' => 'dashboard', 'icon' => 'fa-gauge-high'],
        ['href' => $root . '/invoices/index.php',   'label' => 'Invoices',   'key' => 'invoices',  'icon' => 'fa-file-invoice'],
        ['href' => $root . '/clients/index.php',    'label' => 'Clients',    'key' => 'clients',   'icon' => 'fa-users'],
        ['href' => $root . '/presets/index.php',    'label' => 'Presets',    'key' => 'presets',   'icon' => 'fa-list-check'],
        ['href' => $root . '/settings.php',         'label' => 'Settings',   'key' => 'settings',  'icon' => 'fa-gear'],
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
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
                    <i class="fa-solid <?= $item['icon'] ?> nav-icon"></i>
                    <?= $item['label'] ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-footer">
            Signed in as <strong><?= htmlspecialchars($user) ?></strong><br>
            <a href="<?= $root ?>/change-password.php">Change password</a>
            &nbsp;·&nbsp;
            <a href="<?= $root ?>/logout.php">Sign out</a>
        </div>
    </aside>

    <!-- Sidebar overlay (mobile) -->
    <div class="sidebar-overlay" id="sidebar-overlay"></div>

    <div class="main-content">
        <header class="topbar">
            <button class="sidebar-toggle" id="sidebar-toggle" aria-label="Open navigation">
                <i class="fa-solid fa-bars"></i>
            </button>
            <h1><?= htmlspecialchars($title) ?></h1>
            <div class="topbar-actions" id="topbar-actions"></div>
        </header>
        <main class="page-body">
    <?php
}

function layoutFooter(): void {
    ?>
        </main>
    </div>
</div>
<script>
(function () {
    var toggle  = document.getElementById('sidebar-toggle');
    var sidebar = document.getElementById('sidebar');
    var overlay = document.getElementById('sidebar-overlay');
    if (!toggle || !sidebar || !overlay) return;

    function openSidebar() {
        sidebar.classList.add('open');
        overlay.classList.add('visible');
        document.body.style.overflow = 'hidden';
    }
    function closeSidebar() {
        sidebar.classList.remove('open');
        overlay.classList.remove('visible');
        document.body.style.overflow = '';
    }

    toggle.addEventListener('click', function () {
        sidebar.classList.contains('open') ? closeSidebar() : openSidebar();
    });

    overlay.addEventListener('click', closeSidebar);

    // Close on nav link click (navigates away anyway, but prevents flash on back)
    sidebar.querySelectorAll('a').forEach(function (link) {
        link.addEventListener('click', closeSidebar);
    });
}());
</script>
</body>
</html>
    <?php
}
