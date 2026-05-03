<?php
/**
 * Quick-create a client via AJAX from the invoice form.
 * Returns JSON — never renders a full page.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

verifyCsrf();

$name  = trim(input('name'));
$email = trim(input('email'));
$phone = trim(input('phone'));

if ($name === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Client name is required.']);
    exit;
}

if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Please enter a valid email address.']);
    exit;
}

try {
    $pdo = getDb();
    $stmt = $pdo->prepare("
        INSERT INTO lms_clients (name, email, phone, address, notes)
        VALUES (?, ?, ?, '', '')
    ");
    $stmt->execute([$name, $email ?: null, $phone ?: null]);
    $newId = (int) $pdo->lastInsertId();

    echo json_encode([
        'success' => true,
        'client'  => ['id' => $newId, 'name' => $name],
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not save client. Please try again.']);
}
