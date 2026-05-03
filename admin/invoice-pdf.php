<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/invoice-pdf-lib.php';

requireAuth();

$pdo = getDb();
$id  = inputInt('id', 'get');

ensureClientNotesColumn($pdo);

$stmt = $pdo->prepare("
    SELECT i.*, c.name AS client_name, c.address AS client_address
    FROM lms_invoices i
    JOIN lms_clients c ON c.id = i.client_id
    WHERE i.id = ?
");
$stmt->execute([$id]);
$invoice = $stmt->fetch();

if (!$invoice) {
    http_response_code(404);
    die('Invoice not found.');
}

$itemStmt = $pdo->prepare("
    SELECT * FROM lms_invoice_items WHERE invoice_id = ? ORDER BY sort_order ASC, id ASC
");
$itemStmt->execute([$id]);
$lineItems = $itemStmt->fetchAll();

$settings = getAllSettings($pdo);

$filename = 'Invoice-' . $invoice['invoice_number']
    . '-' . preg_replace('/[^a-zA-Z0-9]/', '-', $invoice['client_name'])
    . '.pdf';

generateInvoicePdf($invoice, $lineItems, $settings, 'D', $filename);
