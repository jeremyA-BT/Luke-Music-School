<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAuth();

$pdo    = getDb();
$root   = getAdminRoot();
$id     = inputInt('id', 'get');
$action = input('action', 'get'); // 'archive' or 'unarchive'

ensureClientArchivedColumn($pdo);

$stmt = $pdo->prepare("SELECT * FROM lms_clients WHERE id = ?");
$stmt->execute([$id]);
$client = $stmt->fetch();

if (!$client || !in_array($action, ['archive', 'unarchive'])) {
    flashMessage('error', 'Invalid request.');
    redirect($root . '/clients/index.php');
}

if ($action === 'archive') {
    // Cancel any active recurring invoice schedules for this client.
    $recurStmt = $pdo->prepare("
        SELECT COUNT(*) FROM lms_invoices
        WHERE client_id = ? AND status = 'draft' AND recurrence != 'none' AND recurrence != '' AND scheduled_date IS NOT NULL
    ");
    $recurStmt->execute([$id]);
    $recurCount = (int) $recurStmt->fetchColumn();

    if ($recurCount > 0) {
        $pdo->prepare("
            UPDATE lms_invoices SET recurrence = 'none', recurrence_end_date = NULL
            WHERE client_id = ? AND status = 'draft' AND recurrence != 'none'
        ")->execute([$id]);
    }

    $pdo->prepare("UPDATE lms_clients SET archived_at = NOW() WHERE id = ?")->execute([$id]);

    $msg = '"' . $client['name'] . '" archived.';
    if ($recurCount > 0) {
        $msg .= ' ' . $recurCount . ' recurring invoice schedule' . ($recurCount !== 1 ? 's were' : ' was') . ' cancelled.';
    }
    flashMessage('success', $msg);

} else {
    $pdo->prepare("UPDATE lms_clients SET archived_at = NULL WHERE id = ?")->execute([$id]);
    flashMessage('success', '"' . $client['name'] . '" restored to active clients.');
}

$returnTo = input('return', 'get');
redirect($returnTo ?: $root . '/clients/index.php');
