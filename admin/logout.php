<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

startAdminSession();
logoutAdmin();
redirect(getAdminRoot() . '/login.php');
