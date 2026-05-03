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

ensureClientNotesColumn($pdo);
ensureSchedulingColumns($pdo);

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

$s = getAllSettings($pdo);

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

// Handle recurring series actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['recurring_action'])) {
    verifyCsrf();
    $recurAction = input('recurring_action');

    if ($recurAction === 'stop') {
        $pdo->prepare("UPDATE lms_invoices SET recurrence='none', recurrence_end_date=NULL WHERE id=?")
            ->execute([$id]);
        flashMessage('success', 'Recurring series stopped. This invoice will send once on its scheduled date, then no further invoices will be created.');
        redirect($root . '/invoices/view.php?id=' . $id);
    }

    if ($recurAction === 'skip') {
        $recurrence = $invoice['recurrence'] ?? 'none';
        $nextDate   = nextRecurrenceDate($invoice['scheduled_date'], $recurrence);
        $endDate    = $invoice['recurrence_end_date'] ?? null;

        if ($nextDate && (!$endDate || $nextDate <= $endDate)) {
            $pdo->prepare("UPDATE lms_invoices SET scheduled_date=? WHERE id=?")->execute([$nextDate, $id]);
            flashMessage('success', 'Occurrence skipped. Next send date: ' . date('j M Y', strtotime($nextDate)) . '.');
        } else {
            // Past end date — cancel the series
            $pdo->prepare("UPDATE lms_invoices SET scheduled_date=NULL, recurrence='none', recurrence_end_date=NULL WHERE id=?")
                ->execute([$id]);
            flashMessage('info', 'This was the last occurrence in the series. Invoice removed from schedule.');
        }
        redirect($root . '/invoices/view.php?id=' . $id);
    }
}

layoutHeader('Invoice #' . $invoice['invoice_number'], 'invoices');
?>

<?php renderFlash(); ?>

<!-- Action bar -->
<div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:20px;flex-wrap:wrap;gap:10px;">
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
        <a href="<?= $root ?>/invoices/edit.php?id=<?= $id ?>"
           class="btn btn-secondary btn-sm"><i class="fa-solid fa-pencil"></i> Edit</a>
        <a href="<?= $root ?>/email-invoice.php?id=<?= $id ?>"
           class="btn btn-primary btn-sm"><i class="fa-solid fa-paper-plane"></i> Email Invoice</a>
        <a href="<?= $root ?>/invoice-pdf.php?id=<?= $id ?>"
           class="btn btn-secondary btn-sm" target="_blank"><i class="fa-solid fa-download"></i> PDF</a>
        <a href="<?= $root ?>/invoices/duplicate.php?id=<?= $id ?>"
           class="btn btn-ghost btn-sm" title="Duplicate invoice"
           onclick="return confirm('Duplicate this invoice?')"><i class="fa-solid fa-copy"></i></a>
        <?php if ($invoice['status'] === 'paid'): ?>
            <span class="btn btn-danger btn-sm" title="Paid invoices cannot be deleted"
                  style="opacity:.4;cursor:not-allowed;" onclick="alert('Paid invoices cannot be deleted — they are part of your financial record.')">
                <i class="fa-solid fa-trash"></i>
            </span>
        <?php elseif ($invoice['status'] === 'sent'): ?>
            <a href="<?= $root ?>/invoices/delete.php?id=<?= $id ?>"
               class="btn btn-danger btn-sm" title="Delete invoice"
               onclick="return confirm('Invoice #<?= $invoice['invoice_number'] ?> has already been sent to the client. Deleting it will permanently remove it from your records — the client may still have their copy. Are you sure?')">
                <i class="fa-solid fa-trash"></i>
            </a>
        <?php else: ?>
            <a href="<?= $root ?>/invoices/delete.php?id=<?= $id ?>"
               class="btn btn-danger btn-sm" title="Delete invoice"
               onclick="return confirm('Delete Invoice #<?= $invoice['invoice_number'] ?>? This cannot be undone.')">
                <i class="fa-solid fa-trash"></i>
            </a>
        <?php endif; ?>
    </div>
</div>

<?php
$recurringOnView = $invoice['recurrence'] ?? 'none';
if ($recurringOnView !== 'none' && $recurringOnView !== '' && $invoice['status'] === 'draft' && !empty($invoice['scheduled_date'])):
    $nextOccurrence = nextRecurrenceDate($invoice['scheduled_date'], $recurringOnView);
?>
<div class="alert" style="background:#ede9fe;border:1px solid #c4b5fd;color:#5b21b6;border-radius:var(--radius);padding:12px 18px;margin-bottom:20px;display:flex;gap:12px;align-items:flex-start;flex-wrap:wrap;">
    <i class="fa-solid fa-rotate" style="margin-top:2px;flex-shrink:0;"></i>
    <div style="flex:1;">
        <strong>Recurring invoice</strong> — repeats <?= e(strtolower($recurringOnView)) ?>,
        next send: <strong><?= htmlspecialchars(date('j M Y', strtotime($invoice['scheduled_date'])), ENT_QUOTES, 'UTF-8') ?></strong>.
        <?php if ($nextOccurrence): ?>
            After sending, a new invoice will be automatically created for
            <strong><?= htmlspecialchars(date('j M Y', strtotime($nextOccurrence)), ENT_QUOTES, 'UTF-8') ?></strong>.
        <?php endif; ?>
    </div>
    <div style="display:flex;gap:8px;flex-shrink:0;">
        <form method="POST" action="" style="display:inline;">
            <?php csrfField(); ?>
            <input type="hidden" name="recurring_action" value="skip">
            <button type="submit" class="btn btn-secondary btn-sm"
                    onclick="return confirm('Skip this occurrence? The invoice will be rescheduled to <?= $nextOccurrence ? htmlspecialchars(date('j M Y', strtotime($nextOccurrence)), ENT_QUOTES, 'UTF-8') : 'the next date' ?>.')">
                <i class="fa-solid fa-forward-step"></i> Skip
            </button>
        </form>
        <form method="POST" action="" style="display:inline;">
            <?php csrfField(); ?>
            <input type="hidden" name="recurring_action" value="stop">
            <button type="submit" class="btn btn-ghost btn-sm"
                    onclick="return confirm('Stop the recurring series? This invoice will still send once on its scheduled date, but no further invoices will be created after that.')">
                <i class="fa-solid fa-stop"></i> Stop Series
            </button>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Invoice preview -->
<div class="invoice-view">
    <!-- Header -->
    <div class="invoice-view-header">
        <div class="invoice-view-from">
            <h2><?= e($s['business_name'] ?? '') ?></h2>
            <p><?= e($s['owner_name'] ?? '') ?></p>
            <?php if (!empty($s['address_line1'])): ?><p><?= e($s['address_line1']) ?></p><?php endif; ?>
            <?php if (!empty($s['address_line2'])): ?><p><?= e($s['address_line2']) ?></p><?php endif; ?>
            <?php if (!empty($s['phone'])): ?><p><?= e($s['phone']) ?></p><?php endif; ?>
            <?php if (!empty($s['email'])): ?><p><?= e($s['email']) ?></p><?php endif; ?>
        </div>
        <div class="invoice-view-title">
            <h1>Invoice</h1>
            <p><strong>Invoice #<?= e((string)$invoice['invoice_number']) ?></strong></p>
            <p>Date: <?= formatDate($invoice['invoice_date']) ?></p>
            <p style="margin-top:8px;"><?= statusBadge($invoice['status']) ?></p>
            <?php if (!empty($invoice['email_sent_at'])): ?>
                <p style="margin-top:6px;font-size:.78rem;color:var(--color-muted);">
                    <i class="fa-solid fa-envelope-circle-check" style="color:var(--color-accent);"></i>
                    Emailed <?= htmlspecialchars(date('j M Y, H:i', strtotime($invoice['email_sent_at'])), ENT_QUOTES, 'UTF-8') ?>
                </p>
            <?php endif; ?>
            <?php if (!empty($invoice['scheduled_date']) && $invoice['status'] === 'draft'): ?>
                <p style="margin-top:6px;font-size:.78rem;color:var(--color-muted);">
                    <i class="fa-solid fa-calendar-clock" style="color:#d97706;"></i>
                    Scheduled for <?= htmlspecialchars(date('j M Y', strtotime($invoice['scheduled_date'])), ENT_QUOTES, 'UTF-8') ?>
                </p>
            <?php endif; ?>
            <?php
            $recurrence = $invoice['recurrence'] ?? 'none';
            if ($recurrence !== 'none' && $recurrence !== ''):
            ?>
                <p style="margin-top:6px;font-size:.78rem;color:var(--color-muted);">
                    <i class="fa-solid fa-rotate" style="color:#d97706;"></i>
                    Repeats <?= e(strtolower($recurrence)) ?>
                    <?php if (!empty($invoice['recurrence_end_date'])): ?>
                        until <?= htmlspecialchars(date('j M Y', strtotime($invoice['recurrence_end_date'])), ENT_QUOTES, 'UTF-8') ?>
                    <?php else: ?>
                        indefinitely
                    <?php endif; ?>
                </p>
            <?php endif; ?>
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
                <div class="banking-val"><?= e($s['bank_name'] ?? '') ?></div>
            </div>
            <div class="banking-item">
                <div class="banking-key">Branch</div>
                <div class="banking-val"><?= e($s['bank_branch'] ?? '') ?></div>
            </div>
            <div class="banking-item">
                <div class="banking-key">Branch Code</div>
                <div class="banking-val"><?= e($s['bank_branch_code'] ?? '') ?></div>
            </div>
            <div class="banking-item">
                <div class="banking-key">Account Holder</div>
                <div class="banking-val"><?= e($s['account_holder'] ?? '') ?></div>
            </div>
            <div class="banking-item">
                <div class="banking-key">Account Number</div>
                <div class="banking-val"><?= e($s['account_number'] ?? '') ?></div>
            </div>
            <div class="banking-item">
                <div class="banking-key">Account Type</div>
                <div class="banking-val"><?= e($s['account_type'] ?? '') ?></div>
            </div>
        </div>
    </div>

    <?php if (!empty($invoice['client_notes'])): ?>
    <div class="invoice-view-client-notes">
        <h3>Notes</h3>
        <p><?= nl2br(e($invoice['client_notes'])) ?></p>
    </div>
    <?php endif; ?>

    <div class="invoice-view-thankyou">Thank you for your business!</div>
</div>

<?php layoutFooter(); ?>
