<?php
/**
 * Scheduled invoice auto-sender.
 *
 * Run daily via DirectAdmin Cron Jobs:
 *   Command:  php /home/lukehigg/domains/luke-higgins.co.za/public_html/admin/cron.php
 *   Schedule: 0 8 * * *   (08:00 every day)
 *
 * Security: This script only runs from the CLI.
 * Do NOT remove that check — otherwise anyone could trigger emails by visiting the URL.
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('403 Forbidden — this script must be run from the command line.');
}

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/invoice-pdf-lib.php';

$pdo      = getDb();
$settings = getAllSettings($pdo);

ensureClientNotesColumn($pdo);
ensureSchedulingColumns($pdo);

$today = date('Y-m-d');

$stmt = $pdo->prepare("
    SELECT i.*, c.name AS client_name, c.email AS client_email, c.address AS client_address
    FROM lms_invoices i
    LEFT JOIN lms_clients c ON c.id = i.client_id
    WHERE i.status = 'draft'
      AND i.scheduled_date IS NOT NULL
      AND i.scheduled_date <= :today
      AND i.email_sent_at IS NULL
      AND c.email IS NOT NULL
      AND c.email != ''
    ORDER BY i.scheduled_date ASC
");
$stmt->execute([':today' => $today]);
$due = $stmt->fetchAll();

if (empty($due)) {
    echo '[' . date('Y-m-d H:i:s') . '] No scheduled invoices due today.' . PHP_EOL;
    exit(0);
}

echo '[' . date('Y-m-d H:i:s') . '] Found ' . count($due) . ' invoice(s) to send.' . PHP_EOL;

foreach ($due as $invoice) {
    $invoiceId = (int) $invoice['id'];
    echo '  → Invoice #' . $invoice['invoice_number'] . ' to ' . $invoice['client_email'] . ' ... ';

    $itemStmt = $pdo->prepare("
        SELECT * FROM lms_invoice_items WHERE invoice_id = ? ORDER BY sort_order ASC, id ASC
    ");
    $itemStmt->execute([$invoiceId]);
    $items = $itemStmt->fetchAll();

    $pdfBytes = generateInvoicePdf($invoice, $items, $settings, 'S');

    $sent = sendInvoiceEmail(
        $invoice['client_email'],
        $invoice['client_name'],
        $invoice,
        $pdfBytes,
        $settings
    );

    if ($sent) {
        $pdo->prepare("UPDATE lms_invoices SET status = 'sent', email_sent_at = NOW() WHERE id = ?")
            ->execute([$invoiceId]);

        // Schedule the next occurrence if this is a recurring invoice.
        $recurrence = $invoice['recurrence'] ?? 'none';
        if ($recurrence !== 'none' && $recurrence !== '') {
            $nextDate    = nextRecurrenceDate($invoice['scheduled_date'], $recurrence);
            $endDate     = $invoice['recurrence_end_date'] ?? null;
            $shouldCreate = $nextDate !== null
                && ($endDate === null || $endDate === '' || $nextDate <= $endDate);

            if ($shouldCreate) {
                // Fetch next available invoice number from settings.
                $nextNum = (int) ($settings['next_invoice_number'] ?? 1);

                $dupStmt = $pdo->prepare("
                    INSERT INTO lms_invoices
                        (invoice_number, client_id, invoice_date, discount, subtotal, total,
                         status, notes, client_notes, scheduled_date, recurrence, recurrence_end_date)
                    VALUES (?, ?, ?, ?, ?, ?, 'draft', ?, ?, ?, ?, ?)
                ");
                $dupStmt->execute([
                    $nextNum,
                    $invoice['client_id'],
                    $nextDate,
                    $invoice['discount'],
                    $invoice['subtotal'],
                    $invoice['total'],
                    $invoice['notes'],
                    $invoice['client_notes'],
                    $nextDate,
                    $recurrence,
                    $endDate ?: null,
                ]);
                $newInvoiceId = (int) $pdo->lastInsertId();

                // Copy line items.
                $lineItems = $pdo->prepare("SELECT * FROM lms_invoice_items WHERE invoice_id = ? ORDER BY sort_order ASC, id ASC");
                $lineItems->execute([$invoiceId]);
                $lineItemInsert = $pdo->prepare("
                    INSERT INTO lms_invoice_items (invoice_id, description, quantity, rate, amount, sort_order)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                foreach ($lineItems->fetchAll() as $item) {
                    $lineItemInsert->execute([
                        $newInvoiceId,
                        $item['description'],
                        $item['quantity'],
                        $item['rate'],
                        $item['amount'],
                        $item['sort_order'],
                    ]);
                }

                // Bump the stored next_invoice_number setting.
                $pdo->prepare("UPDATE lms_settings SET setting_value = ? WHERE setting_key = 'next_invoice_number'")
                    ->execute([$nextNum + 1]);

                echo 'OK (next #' . $nextNum . ' scheduled for ' . $nextDate . ')' . PHP_EOL;
            } else {
                echo 'OK (recurring series ended)' . PHP_EOL;
            }
        } else {
            echo 'OK' . PHP_EOL;
        }
    } else {
        echo 'FAILED' . PHP_EOL;
    }
}

echo '[' . date('Y-m-d H:i:s') . '] Done.' . PHP_EOL;
