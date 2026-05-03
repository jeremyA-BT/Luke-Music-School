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
 */
function getNextInvoiceNumber(PDO $pdo): int {
    $max = $pdo->query("SELECT MAX(invoice_number) FROM lms_invoices")->fetchColumn();
    return $max ? (int) $max + 1 : 1;
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
