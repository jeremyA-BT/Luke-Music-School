<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/invoice-pdf-lib.php';

requireAuth();

$pdo  = getDb();
$root = getAdminRoot();

ensureClientNotesColumn($pdo);
ensureSchedulingColumns($pdo);

$invoiceId = inputInt('id');
if (!$invoiceId) {
    redirect($root . '/invoices/index.php');
}

$stmt = $pdo->prepare("
    SELECT i.*, c.name AS client_name, c.email AS client_email, c.address AS client_address
    FROM lms_invoices i
    LEFT JOIN lms_clients c ON c.id = i.client_id
    WHERE i.id = ?
");
$stmt->execute([$invoiceId]);
$invoice = $stmt->fetch();

if (!$invoice) {
    flashMessage('error', 'Invoice not found.');
    redirect($root . '/invoices/index.php');
}

$settings = getAllSettings($pdo);

// -------------------------------------------------------
// Handle POST — send the email
// -------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $toEmail = trim(input('to_email'));
    $message = trim(input('message'));

    if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        flashMessage('error', 'Please enter a valid email address.');
        redirect($root . '/email-invoice.php?id=' . $invoiceId);
    }

    $itemStmt = $pdo->prepare("SELECT * FROM lms_invoice_items WHERE invoice_id = ? ORDER BY sort_order ASC, id ASC");
    $itemStmt->execute([$invoiceId]);
    $items = $itemStmt->fetchAll();

    $pdfBytes = generateInvoicePdf($invoice, $items, $settings, 'S');

    $sent = sendInvoiceEmail(
        $toEmail,
        $invoice['client_name'],
        $invoice,
        $pdfBytes,
        $settings,
        $message
    );

    if ($sent) {
        $pdo->prepare("UPDATE lms_invoices SET email_sent_at = NOW() WHERE id = ?")
            ->execute([$invoiceId]);
        if ($invoice['status'] === 'draft') {
            $pdo->prepare("UPDATE lms_invoices SET status = 'sent' WHERE id = ?")
                ->execute([$invoiceId]);
        }
        flashMessage('success', 'Invoice #' . $invoice['invoice_number'] . ' emailed to ' . $toEmail . '.');
        redirect($root . '/invoices/view.php?id=' . $invoiceId);
    } else {
        flashMessage('error', 'Mail could not be sent. Check that your From email address is configured under Settings → Email and exists in DirectAdmin.');
        redirect($root . '/email-invoice.php?id=' . $invoiceId);
    }
}

// -------------------------------------------------------
// GET — confirmation form
// -------------------------------------------------------
$preEmail = $invoice['client_email'] ?? '';

layoutHeader('Email Invoice #' . $invoice['invoice_number'], 'invoices');
?>

<?php renderFlash(); ?>

<div style="max-width:580px;">
    <div style="margin-bottom:16px;">
        <a href="<?= $root ?>/invoices/view.php?id=<?= $invoiceId ?>"
           class="btn btn-secondary btn-sm">
            <i class="fa-solid fa-arrow-left"></i> Back to Invoice
        </a>
    </div>

    <div class="card">
        <div class="card-header">
            <h2><i class="fa-solid fa-envelope"></i> Email Invoice #<?= e((string)$invoice['invoice_number']) ?></h2>
            <span style="color:var(--color-muted);font-size:.875rem;"><?= e($invoice['client_name']) ?></span>
        </div>
        <div class="card-body">

            <?php if (empty($settings['email_from'])): ?>
                <div class="alert alert-error">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    No From address configured. Go to
                    <a href="<?= $root ?>/settings.php?tab=email">Settings → Email</a> first.
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <?php csrfField(); ?>

                <div class="form-grid single">
                    <div class="form-group">
                        <label for="to_email">Send To</label>
                        <input type="email" id="to_email" name="to_email"
                               value="<?= e($preEmail) ?>"
                               required placeholder="client@example.com">
                        <span class="form-hint">
                            The invoice PDF will be attached. Replies go directly to
                            <strong><?= e($settings['email_reply_to'] ?? 'your configured reply address') ?></strong>.
                        </span>
                    </div>
                    <div class="form-group">
                        <label for="message">
                            Personal Message
                            <span style="color:var(--color-muted);font-weight:400;">(optional)</span>
                        </label>
                        <textarea id="message" name="message" rows="4"
                                  placeholder="Hi <?= e($invoice['client_name']) ?>, please find your invoice attached…"></textarea>
                        <span class="form-hint">Shown in the email body above the invoice summary.</span>
                    </div>
                </div>

                <div style="background:var(--color-surface-alt,#f8f7f4);border:1px solid var(--color-border);border-radius:var(--radius);padding:14px 16px;margin:16px 0;font-size:.8125rem;color:var(--color-muted);">
                    <strong><i class="fa-solid fa-circle-info" style="color:var(--color-accent);"></i> What the client will receive:</strong>
                    <ul style="margin:8px 0 0 16px;line-height:1.8;">
                        <li>An HTML email with the invoice summary</li>
                        <li>Invoice #<?= e((string)$invoice['invoice_number']) ?> as a PDF attachment</li>
                        <li>From: <strong><?= e($settings['email_from_name'] ?? '') ?></strong>
                            &lt;<?= e($settings['email_from'] ?? '') ?>&gt;</li>
                        <li>Reply-To: <strong><?= e($settings['email_reply_to'] ?? 'not set — configure in Settings') ?></strong></li>
                    </ul>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-paper-plane"></i> Send Invoice Email
                    </button>
                    <a href="<?= $root ?>/invoices/view.php?id=<?= $invoiceId ?>"
                       class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php layoutFooter(); ?>
