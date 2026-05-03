<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';

requireAuth();

$pdo    = getDb();
$root   = getAdminRoot();
$id     = inputInt('id', 'get');
$errors = [];

ensureClientArchivedColumn($pdo);

$stmt   = $pdo->prepare("SELECT * FROM lms_clients WHERE id = ?");
$stmt->execute([$id]);
$client = $stmt->fetch();

if (!$client) {
    flashMessage('error', 'Client not found.');
    redirect($root . '/clients/index.php');
}

$values = [
    'name'    => $client['name'],
    'address' => $client['address'] ?? '',
    'email'   => $client['email']   ?? '',
    'phone'   => $client['phone']   ?? '',
    'notes'   => $client['notes']   ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $values['name']    = input('name');
    $values['address'] = input('address');
    $values['email']   = input('email');
    $values['phone']   = input('phone');
    $values['notes']   = input('notes');

    if ($values['name'] === '') {
        $errors[] = 'Client name is required.';
    }

    if ($values['email'] !== '' && !filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("
            UPDATE lms_clients SET name=?, address=?, email=?, phone=?, notes=? WHERE id=?
        ");
        $stmt->execute([
            $values['name'],
            $values['address'] ?: null,
            $values['email']   ?: null,
            $values['phone']   ?: null,
            $values['notes']   ?: null,
            $id,
        ]);

        flashMessage('success', 'Client updated successfully.');
        redirect($root . '/clients/edit.php?id=' . $id);
    }
}

$icStmt = $pdo->prepare("SELECT COUNT(*) FROM lms_invoices WHERE client_id = ?");
$icStmt->execute([$id]);
$invoiceCount = (int) $icStmt->fetchColumn();

$isArchived = !empty($client['archived_at']);

// Check for active recurring invoices (for archive confirm message)
$recurCheck = $pdo->prepare("SELECT COUNT(*) FROM lms_invoices WHERE client_id = ? AND status = 'draft' AND recurrence != 'none' AND recurrence != '' AND scheduled_date IS NOT NULL");
$recurCheck->execute([$id]);
$hasRecurring = (int) $recurCheck->fetchColumn() > 0;

layoutHeader('Edit Client', 'clients');
?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error"><?= implode('<br>', array_map('e', $errors)) ?></div>
<?php endif; ?>

<?php if ($isArchived): ?>
<div class="alert" style="background:#f3f4f6;border:1px solid var(--color-border);color:var(--color-muted);border-radius:var(--radius);padding:12px 16px;margin-bottom:16px;display:flex;gap:10px;align-items:center;">
    <i class="fa-solid fa-box-archive"></i>
    <span>This client is <strong>archived</strong> — they are hidden from the active client list and invoice dropdowns.
        <a href="<?= $root ?>/clients/archive.php?id=<?= $id ?>&action=unarchive&return=<?= urlencode($root . '/clients/edit.php?id=' . $id) ?>"
           style="color:var(--color-accent);font-weight:600;"
           onclick="return confirm('Restore <?= e($values['name']) ?> to active clients?')">Restore now</a>
    </span>
</div>
<?php endif; ?>

<div class="card" style="max-width:640px;">
    <div class="card-header">
        <h2><?= e($values['name']) ?></h2>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="<?= $root ?>/invoices/index.php?client_id=<?= $id ?>"
               class="btn btn-secondary btn-sm" title="View all invoices for this client">
                <i class="fa-solid fa-file-invoice"></i>
                Invoices
                <?php if ($invoiceCount > 0): ?>
                    <span style="background:var(--color-accent);color:#fff;border-radius:100px;padding:1px 7px;font-size:.72rem;font-weight:700;"><?= $invoiceCount ?></span>
                <?php endif; ?>
            </a>
            <?php if (!$isArchived): ?>
            <a href="<?= $root ?>/invoices/create.php?client_id=<?= $id ?>"
               class="btn btn-primary btn-sm">
                <i class="fa-solid fa-plus"></i> New Invoice
            </a>
            <?php $archiveMsg = 'Archive ' . $values['name'] . '? They will be hidden from the active client list.'
                . ($hasRecurring ? ' Their recurring invoice schedule will also be cancelled.' : ''); ?>
            <a href="<?= $root ?>/clients/archive.php?id=<?= $id ?>&action=archive&return=<?= urlencode($root . '/clients/index.php') ?>"
               class="btn btn-ghost btn-sm"
               onclick="return confirm(<?= e(json_encode($archiveMsg)) ?>)">
                <i class="fa-solid fa-box-archive"></i> Archive
            </a>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body">
        <form method="POST" action="">
            <?php csrfField(); ?>
            <div class="form-grid">
                <div class="form-group full">
                    <label for="name">Full Name *</label>
                    <input type="text" id="name" name="name" value="<?= e($values['name']) ?>" required>
                </div>

                <div class="form-group full">
                    <label for="address">Address</label>
                    <textarea id="address" name="address" rows="3"><?= e($values['address']) ?></textarea>
                </div>

                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" value="<?= e($values['email']) ?>">
                    <span class="form-hint">Used for future invoice email delivery.</span>
                </div>

                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="text" id="phone" name="phone" value="<?= e($values['phone']) ?>">
                </div>

                <div class="form-group full">
                    <label for="notes">Internal Notes</label>
                    <textarea id="notes" name="notes" rows="2"><?= e($values['notes']) ?></textarea>
                </div>
            </div>

            <div class="form-actions" style="margin-top:20px;">
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-floppy-disk"></i> Save Changes
                </button>
                <a href="<?= $root ?>/clients/index.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php layoutFooter(); ?>
