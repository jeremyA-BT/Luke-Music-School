<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';

requireAuth();

$pdo  = getDb();
$root = getAdminRoot();
$id   = inputInt('id', 'get');

$stmt = $pdo->prepare("
    SELECT i.*, c.name AS client_name, c.address AS client_address, c.email AS client_email
    FROM lms_invoices i
    JOIN lms_clients c ON c.id = i.client_id
    WHERE i.id = ?
");
$stmt->execute([$id]);
$invoice = $stmt->fetch();

if (!$invoice) {
    flashMessage('error', 'Invoice not found.');
    redirect($root . '/invoices/index.php');
}

$items = $pdo->prepare("SELECT * FROM lms_invoice_items WHERE invoice_id = ? ORDER BY sort_order ASC, id ASC");
$items->execute([$id]);
$lineItems = $items->fetchAll();

// Handle status change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_status'])) {
    verifyCsrf();
    $newStatus = input('new_status');
    if (in_array($newStatus, ['draft', 'sent', 'paid'])) {
        $sentAt = ($newStatus === 'sent' && $invoice['status'] !== 'sent') ? date('Y-m-d H:i:s') : $invoice['sent_at'];
        $pdo->prepare("UPDATE lms_invoices SET status=?, sent_at=? WHERE id=?")->execute([$newStatus, $sentAt, $id]);
        flashMessage('success', 'Invoice status updated to ' . ucfirst($newStatus) . '.');
        redirect($root . '/invoices/view.php?id=' . $id);
    }
}

layoutHeader('Invoice #' . $invoice['invoice_number'], 'invoices');
?>

<?php renderFlash(); ?>

<!-- Action bar -->
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:10px;">
    <a href="<?= $root ?>/invoices/index.php" class="btn btn-secondary btn-sm">← Back to Invoices</a>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <!-- Quick status change -->
        <form method="POST" action="" style="display:inline-flex;gap:6px;">
            <?php csrfField(); ?>
            <input type="hidden" name="change_status" value="1">
            <select name="new_status" style="width:auto;">
                <option value="draft" <?= $invoice['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
                <option value="sent"  <?= $invoice['status'] === 'sent'  ? 'selected' : '' ?>>Sent</option>
                <option value="paid"  <?= $invoice['status'] === 'paid'  ? 'selected' : '' ?>>Paid</option>
            </select>
            <button type="submit" class="btn btn-secondary btn-sm">Update Status</button>
        </form>
        <a href="<?= $root ?>/invoices/edit.php?id=<?= $id ?>" class="btn btn-secondary btn-sm">Edit</a>
        <a href="<?= $root ?>/invoice-pdf.php?id=<?= $id ?>" class="btn btn-primary btn-sm" target="_blank">Download PDF</a>
    </div>
</div>

<!-- Invoice preview -->
<div class="invoice-view">
    <!-- Header -->
    <div class="invoice-view-header">
        <div class="invoice-view-from">
            <h2><?= e(INVOICE_BUSINESS_NAME) ?></h2>
            <p><?= e(INVOICE_OWNER_NAME) ?></p>
            <p><?= e(INVOICE_ADDRESS_LINE1) ?></p>
            <p><?= e(INVOICE_ADDRESS_LINE2) ?></p>
            <p><?= e(INVOICE_PHONE) ?></p>
            <p><?= e(INVOICE_EMAIL) ?></p>
        </div>
        <div class="invoice-view-title">
            <h1>Invoice</h1>
            <p><strong>Invoice #<?= e((string)$invoice['invoice_number']) ?></strong></p>
            <p>Date: <?= formatDate($invoice['invoice_date']) ?></p>
            <p style="margin-top:8px;"><?= statusBadge($invoice['status']) ?></p>
        </div>
    </div>

    <!-- Bill to -->
    <div class="invoice-view-parties">
        <div class="invoice-view-bill-to">
            <h3>Bill To</h3>
            <p><strong><?= e($invoice['client_name']) ?></strong></p>
            <?php if ($invoice['client_address']): ?>
                <?php foreach (explode("\n", $invoice['client_address']) as $line): ?>
                    <?php if (trim($line) !== ''): ?>
                        <p><?= e(trim($line)) ?></p>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
            <?php if ($invoice['client_email']): ?>
                <p><?= e($invoice['client_email']) ?></p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Line items -->
    <div class="invoice-view-items">
        <table>
            <thead>
                <tr>
                    <th style="width:50%;">Description</th>
                    <th style="width:16%;text-align:center;">Lessons</th>
                    <th style="width:17%;text-align:right;">Rate</th>
                    <th style="width:17%;text-align:right;">Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($lineItems as $item): ?>
                    <tr>
                        <td><?= e($item['description']) ?></td>
                        <td style="text-align:center;"><?= rtrim(rtrim(number_format((float)$item['quantity'], 2), '0'), '.') ?></td>
                        <td style="text-align:right;"><?= formatRand((float)$item['rate']) ?></td>
                        <td style="text-align:right;"><?= formatRand((float)$item['amount']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Totals -->
    <div class="invoice-view-totals">
        <table>
            <tbody>
                <tr>
                    <td>Subtotal</td>
                    <td style="text-align:right;"><?= formatRand((float)$invoice['subtotal']) ?></td>
                </tr>
                <?php if ((float)$invoice['discount'] > 0): ?>
                    <tr>
                        <td>Discount</td>
                        <td style="text-align:right;">− <?= formatRand((float)$invoice['discount']) ?></td>
                    </tr>
                <?php endif; ?>
                <tr class="total-final">
                    <td>Total</td>
                    <td style="text-align:right;"><?= formatRand((float)$invoice['total']) ?></td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Banking details -->
    <div class="invoice-view-banking">
        <h3>Account Details</h3>
        <div class="banking-grid">
            <div class="banking-item">
                <div class="banking-key">Bank</div>
                <div class="banking-val"><?= e(INVOICE_BANK_NAME) ?></div>
            </div>
            <div class="banking-item">
                <div class="banking-key">Branch</div>
                <div class="banking-val"><?= e(INVOICE_BANK_BRANCH) ?></div>
            </div>
            <div class="banking-item">
                <div class="banking-key">Branch Code</div>
                <div class="banking-val"><?= e(INVOICE_BANK_BRANCH_CODE) ?></div>
            </div>
            <div class="banking-item">
                <div class="banking-key">Account Holder</div>
                <div class="banking-val"><?= e(INVOICE_ACCOUNT_HOLDER) ?></div>
            </div>
            <div class="banking-item">
                <div class="banking-key">Account Number</div>
                <div class="banking-val"><?= e(INVOICE_ACCOUNT_NUMBER) ?></div>
            </div>
            <div class="banking-item">
                <div class="banking-key">Account Type</div>
                <div class="banking-val"><?= e(INVOICE_ACCOUNT_TYPE) ?></div>
            </div>
        </div>
    </div>

    <div class="invoice-view-thankyou">Thank you for your business!</div>
</div>

<?php layoutFooter(); ?>
