<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAuth();

$pdo  = getDb();
$root = getAdminRoot();
$id   = inputInt('id', 'get');

ensureClientNotesColumn($pdo);

$stmt = $pdo->prepare("SELECT * FROM lms_invoices WHERE id = ?");
$stmt->execute([$id]);
$source = $stmt->fetch();

if (!$source) {
    flashMessage('error', 'Invoice not found.');
    redirect($root . '/invoices/index.php');
}

$itemStmt = $pdo->prepare("SELECT * FROM lms_invoice_items WHERE invoice_id = ? ORDER BY sort_order ASC, id ASC");
$itemStmt->execute([$id]);
$items = $itemStmt->fetchAll();

$newNumber = getNextInvoiceNumber($pdo);

$pdo->beginTransaction();
try {
    $pdo->prepare("
        INSERT INTO lms_invoices
            (invoice_number, client_id, invoice_date, discount, subtotal, total, status, notes, client_notes)
        VALUES (?, ?, ?, ?, ?, ?, 'draft', ?, ?)
    ")->execute([
        $newNumber,
        $source['client_id'],
        date('Y-m-d'),
        $source['discount'],
        $source['subtotal'],
        $source['total'],
        $source['notes'],
        $source['client_notes'] ?? null,
    ]);

    $newId    = (int) $pdo->lastInsertId();
    $copyStmt = $pdo->prepare("
        INSERT INTO lms_invoice_items (invoice_id, description, quantity, rate, amount, sort_order)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    foreach ($items as $item) {
        $copyStmt->execute([$newId, $item['description'], $item['quantity'], $item['rate'], $item['amount'], $item['sort_order']]);
    }

    $pdo->commit();

    // Clear the manual next_invoice_number override after use
    $configured = getSetting($pdo, 'next_invoice_number', '');
    if ($configured !== '') {
        setSetting($pdo, 'next_invoice_number', '');
    }

    flashMessage('success', 'Invoice duplicated as #' . $newNumber . '. It has been saved as a draft.');
    redirect($root . '/invoices/edit.php?id=' . $newId);
} catch (Exception $e) {
    $pdo->rollBack();
    flashMessage('error', 'Failed to duplicate invoice. Please try again.');
    redirect($root . '/invoices/view.php?id=' . $id);
}
