<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAuth();

$pdo  = getDb();
$root = getAdminRoot();
$id   = inputInt('id', 'get');

$stmt = $pdo->prepare("SELECT * FROM lms_invoices WHERE id = ?");
$stmt->execute([$id]);
$invoice = $stmt->fetch();

if (!$invoice) {
    flashMessage('error', 'Invoice not found.');
    redirect($root . '/invoices/index.php');
}

// Paid invoices are locked from deletion.
if ($invoice['status'] === 'paid') {
    flashMessage('error', 'Invoice #' . $invoice['invoice_number'] . ' cannot be deleted — paid invoices are part of your financial record. Mark it as sent or draft first if you really need to delete it.');
    redirect($root . '/invoices/view.php?id=' . $id);
}

// Line items are deleted via ON DELETE CASCADE on the FK
$pdo->prepare("DELETE FROM lms_invoices WHERE id = ?")->execute([$id]);

flashMessage('success', 'Invoice #' . $invoice['invoice_number'] . ' deleted.');
redirect($root . '/invoices/index.php');
