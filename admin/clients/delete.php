<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAuth();

$pdo  = getDb();
$root = getAdminRoot();
$id   = inputInt('id', 'get');

$stmt = $pdo->prepare("SELECT * FROM lms_clients WHERE id = ?");
$stmt->execute([$id]);
$client = $stmt->fetch();

if (!$client) {
    flashMessage('error', 'Client not found.');
    redirect($root . '/clients/index.php');
}

// Block deletion if the client has invoices (FK constraint would catch this too, but give a nicer message)
$invoiceCount = (int) $pdo->prepare("SELECT COUNT(*) FROM lms_invoices WHERE client_id = ?")->execute([$id]);
$stmt2 = $pdo->prepare("SELECT COUNT(*) FROM lms_invoices WHERE client_id = ?");
$stmt2->execute([$id]);
$invoiceCount = (int) $stmt2->fetchColumn();

if ($invoiceCount > 0) {
    flashMessage('error', 'Cannot delete "' . $client['name'] . '" — they have ' . $invoiceCount . ' invoice(s) on record. Use Archive instead to hide them from the active client list.');
    redirect($root . '/clients/edit.php?id=' . $id);
}

$pdo->prepare("DELETE FROM lms_clients WHERE id = ?")->execute([$id]);
flashMessage('success', 'Client "' . $client['name'] . '" deleted.');
redirect($root . '/clients/index.php');
