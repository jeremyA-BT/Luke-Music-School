<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

startAdminSession();

if (isLoggedIn()) {
    redirect(getAdminRoot() . '/dashboard.php');
} else {
    redirect(getAdminRoot() . '/login.php');
}
