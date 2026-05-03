<?php
/**
 * Future feature: Email invoice PDF to client.
 *
 * This stub is scaffolded for future implementation.
 * To enable:
 *   1. Install PHPMailer (upload vendor/ folder or single-file drop-in)
 *   2. Add SMTP credentials to admin/includes/config.php
 *   3. Implement the sendInvoiceEmail() function below
 *   4. Wire the "Send Email" button in invoices/view.php
 *
 * Required config.php additions when implementing:
 *   define('SMTP_HOST',     'smtp.yourdomain.co.za');
 *   define('SMTP_PORT',     587);
 *   define('SMTP_USER',     'noreply@yourdomain.co.za');
 *   define('SMTP_PASS',     'your-smtp-password');
 *   define('SMTP_FROM',     'noreply@yourdomain.co.za');
 *   define('SMTP_FROM_NAME', "Luke's Music Lessons");
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireAuth();

$root = getAdminRoot();

// TODO(future): Implement this function using PHPMailer or similar
function sendInvoiceEmail(int $invoiceId, string $toEmail, string $toName): bool {
    // 1. Generate PDF bytes using FPDF (call invoice-pdf logic as a function, not as a download)
    // 2. Compose email with PDF as attachment
    // 3. Send via SMTP (PHPMailer recommended over PHP mail())
    // 4. On success: UPDATE lms_invoices SET status='sent', sent_at=NOW() WHERE id=?
    // 5. Return true on success, false on failure

    throw new RuntimeException('Email sending is not yet implemented.');
}

// TODO(future): Handle POST with invoice_id and send
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    flashMessage('error', 'Email sending is not yet available. Please download the PDF and send it manually.');
    $invoiceId = inputInt('invoice_id');
    redirect($root . '/invoices/view.php?id=' . $invoiceId);
}

redirect($root . '/invoices/index.php');
