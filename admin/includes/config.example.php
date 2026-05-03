<?php
/**
 * Database and application configuration.
 * Copy this file to config.php and fill in your values.
 * NEVER commit config.php to version control.
 */

// Database credentials (from DirectAdmin > MySQL Databases)
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_database_name');
define('DB_USER', 'your_database_user');
define('DB_PASS', 'your_database_password');
define('DB_CHARSET', 'utf8mb4');

// Application settings
define('APP_NAME', "Luke's Music Lessons");
define('APP_URL', 'https://yourdomain.co.za/admin');

// Session cookie name (change to something non-obvious)
define('SESSION_NAME', 'lms_admin_sess');

// Max failed login attempts before lockout (per session)
define('MAX_LOGIN_ATTEMPTS', 5);

// Business details used in PDF invoices
define('INVOICE_BUSINESS_NAME', "Luke's Music Lessons");
define('INVOICE_OWNER_NAME', 'Luke Higgins');
define('INVOICE_ADDRESS_LINE1', '91 Lachlan Road, Glenferness');
define('INVOICE_ADDRESS_LINE2', 'Fourways, Sandton, Gauteng, 2191');
define('INVOICE_PHONE', '076 670 7711');
define('INVOICE_EMAIL', 'lukesterhi@gmail.com');

// Banking details for invoice footer
define('INVOICE_BANK_NAME', 'Standard Bank');
define('INVOICE_BANK_BRANCH', 'Woodmead');
define('INVOICE_BANK_BRANCH_CODE', '1255');
define('INVOICE_ACCOUNT_HOLDER', 'MR LUKE LE HIGGINS');
define('INVOICE_ACCOUNT_NUMBER', '10 09 581 994 9');
define('INVOICE_ACCOUNT_TYPE', 'Current');
