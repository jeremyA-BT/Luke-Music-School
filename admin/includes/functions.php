<?php
/**
 * Shared utility functions for the admin panel.
 */

/**
 * Escape a value for safe HTML output.
 */
function e(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Format a decimal as South African Rand: R 1 200.00
 */
function formatRand(float $amount): string {
    return 'R ' . number_format($amount, 2);
}

/**
 * Format a DATE string (Y-m-d) for display: 20 January 2026
 */
function formatDate(string $dateStr): string {
    $dt = DateTime::createFromFormat('Y-m-d', $dateStr);
    return $dt ? $dt->format('j F Y') : e($dateStr);
}

/**
 * Return the next available invoice number.
 * Respects the manually configured next_invoice_number setting when set.
 */
function getNextInvoiceNumber(PDO $pdo): int {
    $configured = getSetting($pdo, 'next_invoice_number', '');
    if ($configured !== '' && (int) $configured > 0) {
        return (int) $configured;
    }
    $max = $pdo->query("SELECT MAX(invoice_number) FROM lms_invoices")->fetchColumn();
    return $max ? (int) $max + 1 : 1;
}

/**
 * Ensure lms_clients has the archived_at column for soft-delete/archive support.
 */
function ensureClientArchivedColumn(PDO $pdo): void {
    static $checked = false;
    if ($checked) return;
    try { $pdo->exec("ALTER TABLE lms_clients ADD COLUMN archived_at DATETIME NULL DEFAULT NULL"); } catch (Exception $e) {}
    $checked = true;
}

/**
 * Ensure the lms_invoices table has the client_notes column.
 * Safe to call multiple times; silently ignores "column already exists" errors.
 */
function ensureClientNotesColumn(PDO $pdo): void {
    static $checked = false;
    if ($checked) return;
    try {
        $pdo->exec("ALTER TABLE lms_invoices ADD COLUMN client_notes TEXT NULL AFTER notes");
    } catch (Exception $e) {
        // Column already exists — no action needed
    }
    $checked = true;
}

/**
 * Ensure lms_invoices has the scheduled_date, email_sent_at, recurrence, and
 * recurrence_end_date columns.
 */
function ensureSchedulingColumns(PDO $pdo): void {
    static $checked = false;
    if ($checked) return;
    try { $pdo->exec("ALTER TABLE lms_invoices ADD COLUMN scheduled_date DATE NULL AFTER client_notes"); }         catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE lms_invoices ADD COLUMN email_sent_at DATETIME NULL AFTER scheduled_date"); }    catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE lms_invoices ADD COLUMN recurrence VARCHAR(20) NOT NULL DEFAULT 'none' AFTER email_sent_at"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE lms_invoices ADD COLUMN recurrence_end_date DATE NULL AFTER recurrence"); }      catch (Exception $e) {}
    $checked = true;
}

/**
 * Given a date string and a recurrence interval, return the next scheduled date.
 * Returns null if recurrence is 'none' or unrecognised.
 */
function nextRecurrenceDate(string $fromDate, string $recurrence): ?string {
    try {
        $dt = new DateTime($fromDate);
    } catch (Exception $e) {
        return null;
    }
    switch ($recurrence) {
        case 'weekly':      $dt->modify('+7 days');  break;
        case 'fortnightly': $dt->modify('+14 days'); break;
        case 'monthly':     $dt->modify('+1 month'); break;
        default:            return null;
    }
    return $dt->format('Y-m-d');
}

/**
 * Build and send an invoice email with the PDF attached.
 *
 * @param string $toEmail    Recipient email address
 * @param string $toName     Recipient display name
 * @param array  $invoice    Invoice row from the DB
 * @param string $pdfContent Raw PDF bytes (from FPDF Output('S'))
 * @param array  $settings   All settings from getAllSettings()
 * @param string $extraMsg   Optional personal message to include in the email body
 * @return bool
 */
function sendInvoiceEmail(
    string $toEmail,
    string $toName,
    array  $invoice,
    string $pdfContent,
    array  $settings,
    string $extraMsg = ''
): bool {
    $fromName   = $settings['email_from_name'] ?? $settings['business_name'] ?? "Luke's Music Lessons";
    $fromEmail  = $settings['email_from']      ?? 'invoices@luke-higgins.co.za';
    $replyTo    = $settings['email_reply_to']  ?? '';
    $payTerms   = $settings['payment_terms']    ?? '';

    $invNum     = (int) $invoice['invoice_number'];
    $invDate    = DateTime::createFromFormat('Y-m-d', $invoice['invoice_date']);
    $invDateStr = $invDate ? $invDate->format('j F Y') : $invoice['invoice_date'];
    $total      = 'R ' . number_format((float) $invoice['total'], 2);
    $clientName = htmlspecialchars($toName, ENT_QUOTES, 'UTF-8');
    $pdfName    = 'Invoice-' . $invNum . '.pdf';

    $subject    = 'Invoice #' . $invNum . ' from ' . $fromName;

    // ---- HTML body ----
    $extraHtml = $extraMsg !== ''
        ? '<p style="background:#f1f5f9;padding:14px 18px;border-radius:6px;margin:20px 0 0;">'
          . nl2br(htmlspecialchars($extraMsg, ENT_QUOTES, 'UTF-8')) . '</p>'
        : '';

    $replyLine = $replyTo !== ''
        ? '<p style="margin:12px 0 0;font-size:13px;color:#555;">To reply or ask a question, simply reply to this email'
          . ' or write to <a href="mailto:' . htmlspecialchars($replyTo, ENT_QUOTES, 'UTF-8') . '">'
          . htmlspecialchars($replyTo, ENT_QUOTES, 'UTF-8') . '</a>.</p>'
        : '';

    $termsHtml = $payTerms !== ''
        ? '<p style="font-size:12px;color:#888;margin-top:20px;border-top:1px solid #e5e5e5;padding-top:12px;">'
          . htmlspecialchars($payTerms, ENT_QUOTES, 'UTF-8') . '</p>'
        : '';

    $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f8f7f4;font-family:Arial,Helvetica,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f8f7f4;padding:32px 16px;">
    <tr><td align="center">
      <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08);">
        <!-- Header -->
        <tr><td style="background:#1a3d1c;padding:24px 32px;">
          <p style="margin:0;color:#ffffff;font-size:22px;font-weight:700;">{$fromName}</p>
          <p style="margin:6px 0 0;color:rgba(255,255,255,.7);font-size:13px;">Invoice #{$invNum}</p>
        </td></tr>
        <!-- Body -->
        <tr><td style="padding:32px 32px 24px;">
          <p style="margin:0 0 20px;font-size:16px;color:#1a1a18;">Hi {$clientName},</p>
          <p style="margin:0 0 20px;color:#555;">Please find your invoice attached. Here is a summary:</p>
          <table cellpadding="0" cellspacing="0" style="width:100%;border:1px solid #e5e2da;border-radius:6px;overflow:hidden;margin-bottom:20px;">
            <tr style="background:#f8f7f4;">
              <td style="padding:10px 16px;font-size:13px;color:#888;font-weight:600;text-transform:uppercase;letter-spacing:.04em;">Invoice #</td>
              <td style="padding:10px 16px;font-size:14px;font-weight:600;color:#1a1a18;text-align:right;">#{$invNum}</td>
            </tr>
            <tr>
              <td style="padding:10px 16px;font-size:13px;color:#888;font-weight:600;text-transform:uppercase;letter-spacing:.04em;border-top:1px solid #e5e2da;">Date</td>
              <td style="padding:10px 16px;font-size:14px;color:#1a1a18;text-align:right;border-top:1px solid #e5e2da;">{$invDateStr}</td>
            </tr>
            <tr style="background:#f8f7f4;">
              <td style="padding:10px 16px;font-size:13px;color:#888;font-weight:600;text-transform:uppercase;letter-spacing:.04em;border-top:1px solid #e5e2da;">Total Due</td>
              <td style="padding:10px 16px;font-size:16px;font-weight:700;color:#1a3d1c;text-align:right;border-top:1px solid #e5e2da;">{$total}</td>
            </tr>
          </table>
          {$extraHtml}
          {$replyLine}
          {$termsHtml}
        </td></tr>
        <!-- Footer -->
        <tr><td style="background:#f8f7f4;padding:16px 32px;border-top:1px solid #e5e2da;">
          <p style="margin:0;font-size:12px;color:#aaa;">The PDF invoice is attached to this email.</p>
        </td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;

    // ---- MIME multipart with PDF attachment ----
    $boundary = '==MIME_' . md5(uniqid('', true));

    $headers  = 'From: ' . $fromName . ' <' . $fromEmail . '>' . "\r\n";
    $headers .= 'MIME-Version: 1.0' . "\r\n";
    $headers .= 'Content-Type: multipart/mixed; boundary="' . $boundary . '"' . "\r\n";
    if ($replyTo !== '') {
        $headers .= 'Reply-To: ' . $replyTo . "\r\n";
    }

    $body  = '--' . $boundary . "\r\n";
    $body .= 'Content-Type: text/html; charset=UTF-8' . "\r\n";
    $body .= 'Content-Transfer-Encoding: quoted-printable' . "\r\n\r\n";
    $body .= quoted_printable_encode($html) . "\r\n";

    $body .= '--' . $boundary . "\r\n";
    $body .= 'Content-Type: application/pdf; name="' . $pdfName . '"' . "\r\n";
    $body .= 'Content-Disposition: attachment; filename="' . $pdfName . '"' . "\r\n";
    $body .= 'Content-Transfer-Encoding: base64' . "\r\n\r\n";
    $body .= chunk_split(base64_encode($pdfContent)) . "\r\n";

    $body .= '--' . $boundary . '--';

    return mail($toEmail, $subject, $body, $headers);
}

/**
 * Return a badge HTML string for an invoice status.
 */
function statusBadge(string $status): string {
    $map = [
        'draft' => ['label' => 'Draft',  'class' => 'badge-draft'],
        'sent'  => ['label' => 'Sent',   'class' => 'badge-sent'],
        'paid'  => ['label' => 'Paid',   'class' => 'badge-paid'],
    ];
    $entry = $map[$status] ?? ['label' => ucfirst($status), 'class' => 'badge-draft'];
    return '<span class="badge ' . $entry['class'] . '">' . $entry['label'] . '</span>';
}

/**
 * Redirect to a URL and exit.
 */
function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

/**
 * Store a one-time flash message in the session.
 */
function flashMessage(string $type, string $message): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/**
 * Retrieve and clear the flash message from the session.
 * Returns null if no flash message is set.
 */
function getFlash(): ?array {
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Render the flash message HTML if one exists.
 */
function renderFlash(): void {
    $flash = getFlash();
    if (!$flash) return;
    $class = $flash['type'] === 'success' ? 'alert-success' : 'alert-error';
    echo '<div class="alert ' . $class . '">' . e($flash['message']) . '</div>';
}

/**
 * Sanitise and trim a string from POST/GET input.
 */
function input(string $key, string $source = 'post'): string {
    $data = $source === 'get' ? $_GET : $_POST;
    return trim($data[$key] ?? '');
}

/**
 * Return a float from POST/GET input, defaulting to 0.
 */
function inputFloat(string $key, string $source = 'post'): float {
    return (float) input($key, $source);
}

/**
 * Return an int from POST/GET input, defaulting to 0.
 */
function inputInt(string $key, string $source = 'post'): int {
    return (int) input($key, $source);
}

/**
 * Verify a CSRF token from POST data against the session token.
 */
function verifyCsrf(): void {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        die('Invalid CSRF token.');
    }
}

/**
 * Generate and store a CSRF token in the session; return it.
 */
function getCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Render a hidden CSRF input field.
 */
function csrfField(): void {
    echo '<input type="hidden" name="csrf_token" value="' . e(getCsrfToken()) . '">';
}

// -------------------------------------------------------
// Settings helpers (lms_settings key/value store)
// -------------------------------------------------------

/**
 * Ensure the lms_settings table exists and is seeded with defaults on first use.
 */
function ensureSettingsTable(PDO $pdo): void {
    static $ensured = false;
    if ($ensured) return;

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS lms_settings (
            `key`       VARCHAR(100) NOT NULL PRIMARY KEY,
            `value`     TEXT NOT NULL DEFAULT '',
            updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $count = (int) $pdo->query("SELECT COUNT(*) FROM lms_settings")->fetchColumn();

    if ($count === 0) {
        $defaults = [
            'business_name'       => defined('INVOICE_BUSINESS_NAME') ? INVOICE_BUSINESS_NAME : "Luke's Music Lessons",
            'owner_name'          => defined('INVOICE_OWNER_NAME')    ? INVOICE_OWNER_NAME    : 'Luke Higgins',
            'address_line1'       => defined('INVOICE_ADDRESS_LINE1') ? INVOICE_ADDRESS_LINE1 : '',
            'address_line2'       => defined('INVOICE_ADDRESS_LINE2') ? INVOICE_ADDRESS_LINE2 : '',
            'phone'               => defined('INVOICE_PHONE')         ? INVOICE_PHONE         : '',
            'email'               => defined('INVOICE_EMAIL')         ? INVOICE_EMAIL         : '',
            'bank_name'           => defined('INVOICE_BANK_NAME')         ? INVOICE_BANK_NAME         : '',
            'bank_branch'         => defined('INVOICE_BANK_BRANCH')       ? INVOICE_BANK_BRANCH       : '',
            'bank_branch_code'    => defined('INVOICE_BANK_BRANCH_CODE')  ? INVOICE_BANK_BRANCH_CODE  : '',
            'account_holder'      => defined('INVOICE_ACCOUNT_HOLDER')    ? INVOICE_ACCOUNT_HOLDER    : '',
            'account_number'      => defined('INVOICE_ACCOUNT_NUMBER')    ? INVOICE_ACCOUNT_NUMBER    : '',
            'account_type'        => defined('INVOICE_ACCOUNT_TYPE')      ? INVOICE_ACCOUNT_TYPE      : '',
            'next_invoice_number' => '',
            'payment_terms'       => '',
            'email_from'          => 'invoices@luke-higgins.co.za',
            'email_from_name'     => "Luke's Music Lessons",
            'email_reply_to'      => 'lukesterhi@gmail.com',
        ];

        $stmt = $pdo->prepare("INSERT IGNORE INTO lms_settings (`key`, `value`) VALUES (?, ?)");
        foreach ($defaults as $key => $value) {
            $stmt->execute([$key, $value]);
        }
    }

    $ensured = true;
}

/**
 * Read a setting value, falling back to $default if not set.
 */
function getSetting(PDO $pdo, string $key, string $default = ''): string {
    try {
        ensureSettingsTable($pdo);
        $stmt = $pdo->prepare("SELECT `value` FROM lms_settings WHERE `key` = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        return ($row && $row['value'] !== '') ? $row['value'] : $default;
    } catch (Exception $e) {
        return $default;
    }
}

/**
 * Write a setting value (upsert).
 */
function setSetting(PDO $pdo, string $key, string $value): void {
    ensureSettingsTable($pdo);
    $stmt = $pdo->prepare("
        INSERT INTO lms_settings (`key`, `value`) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE `value` = ?
    ");
    $stmt->execute([$key, $value, $value]);
}

/**
 * Read all settings as an associative array.
 */
function getAllSettings(PDO $pdo): array {
    try {
        ensureSettingsTable($pdo);
        $rows = $pdo->query("SELECT `key`, `value` FROM lms_settings")->fetchAll();
        $out  = [];
        foreach ($rows as $row) {
            $out[$row['key']] = $row['value'];
        }
        return $out;
    } catch (Exception $e) {
        return [];
    }
}
