<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';

requireAuth();

$pdo    = getDb();
$root   = getAdminRoot();
$search = input('q', 'get');
$status = input('status', 'get');
$from   = input('from', 'get');
$to     = input('to', 'get');

$where  = [];
$params = [];

if ($search !== '') {
    $where[]  = "(c.name LIKE ? OR i.invoice_number LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

if ($status !== '') {
    $where[]  = "i.status = ?";
    $params[] = $status;
}

if ($from !== '') {
    $where[]  = "i.invoice_date >= ?";
    $params[] = $from;
}

if ($to !== '') {
    $where[]  = "i.invoice_date <= ?";
    $params[] = $to;
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "
    SELECT i.*, c.name AS client_name
    FROM lms_invoices i
    JOIN lms_clients c ON c.id = i.client_id
    {$whereClause}
    ORDER BY i.invoice_date DESC, i.id DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$invoices = $stmt->fetchAll();

layoutHeader('Invoices', 'invoices');
?>

<?php renderFlash(); ?>

<div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
    <form method="GET" class="filter-bar" style="margin-bottom:0;">
        <input type="text" name="q" placeholder="Search client or invoice #…" value="<?= e($search) ?>">
        <select name="status">
            <option value="">All statuses</option>
            <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Draft</option>
            <option value="sent"  <?= $status === 'sent'  ? 'selected' : '' ?>>Sent</option>
            <option value="paid"  <?= $status === 'paid'  ? 'selected' : '' ?>>Paid</option>
        </select>
        <input type="date" name="from" value="<?= e($from) ?>" title="From date">
        <input type="date" name="to"   value="<?= e($to) ?>"   title="To date">
        <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
        <?php if ($search || $status || $from || $to): ?>
            <a href="<?= $root ?>/invoices/index.php" class="btn btn-secondary btn-sm">Clear</a>
        <?php endif; ?>
    </form>
    <a href="<?= $root ?>/invoices/create.php" class="btn btn-primary">+ New Invoice</a>
</div>

<div class="card">
    <div class="card-body" style="padding:0;">
        <?php if (empty($invoices)): ?>
            <div class="empty-state">
                <p><?= ($search || $status || $from || $to) ? 'No invoices match your filters.' : 'No invoices yet.' ?></p>
                <?php if (!$search && !$status && !$from && !$to): ?>
                    <a href="<?= $root ?>/invoices/create.php" class="btn btn-primary">Create your first invoice</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Client</th>
                            <th>Date</th>
                            <th class="td-right">Total</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($invoices as $inv): ?>
                            <tr>
                                <td class="td-mono">#<?= e((string)$inv['invoice_number']) ?></td>
                                <td><?= e($inv['client_name']) ?></td>
                                <td><?= formatDate($inv['invoice_date']) ?></td>
                                <td class="td-right"><?= formatRand((float)$inv['total']) ?></td>
                                <td><?= statusBadge($inv['status']) ?></td>
                                <td>
                                    <div class="td-actions">
                                        <a href="<?= $root ?>/invoices/view.php?id=<?= $inv['id'] ?>" class="btn btn-secondary btn-sm">View</a>
                                        <a href="<?= $root ?>/invoices/edit.php?id=<?= $inv['id'] ?>" class="btn btn-secondary btn-sm">Edit</a>
                                        <a href="<?= $root ?>/invoice-pdf.php?id=<?= $inv['id'] ?>" class="btn btn-secondary btn-sm" target="_blank">PDF</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php layoutFooter(); ?>
