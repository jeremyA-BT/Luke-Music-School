<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/layout.php';

requireAuth();

$pdo = getDb();

$totalInvoices  = (int) $pdo->query("SELECT COUNT(*) FROM lms_invoices")->fetchColumn();
$totalClients   = (int) $pdo->query("SELECT COUNT(*) FROM lms_clients")->fetchColumn();
$totalPaid      = (float) $pdo->query("SELECT COALESCE(SUM(total),0) FROM lms_invoices WHERE status='paid'")->fetchColumn();
$countDraft     = (int) $pdo->query("SELECT COUNT(*) FROM lms_invoices WHERE status='draft'")->fetchColumn();
$countSent      = (int) $pdo->query("SELECT COUNT(*) FROM lms_invoices WHERE status='sent'")->fetchColumn();

$recentInvoices = $pdo->query("
    SELECT i.*, c.name AS client_name
    FROM lms_invoices i
    JOIN lms_clients c ON c.id = i.client_id
    ORDER BY i.invoice_date DESC, i.id DESC
    LIMIT 8
")->fetchAll();

$root = getAdminRoot();

layoutHeader('Dashboard', 'dashboard');
?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-label">Total Invoices</div>
        <div class="stat-value"><?= $totalInvoices ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Clients</div>
        <div class="stat-value"><?= $totalClients ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Revenue (Paid)</div>
        <div class="stat-value" style="font-size:1.4rem;"><?= formatRand($totalPaid) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Awaiting Payment</div>
        <div class="stat-value"><?= $countSent ?></div>
        <div class="stat-sub"><?= $countDraft ?> draft<?= $countDraft !== 1 ? 's' : '' ?></div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2>Recent Invoices</h2>
        <a href="<?= $root ?>/invoices/create.php" class="btn btn-primary btn-sm">+ New Invoice</a>
    </div>
    <div class="card-body" style="padding:0;">
        <?php if (empty($recentInvoices)): ?>
            <div class="empty-state">
                <p>No invoices yet.</p>
                <a href="<?= $root ?>/invoices/create.php" class="btn btn-primary">Create your first invoice</a>
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
                        <?php foreach ($recentInvoices as $inv): ?>
                            <tr>
                                <td class="td-mono">#<?= e((string)$inv['invoice_number']) ?></td>
                                <td><?= e($inv['client_name']) ?></td>
                                <td><?= formatDate($inv['invoice_date']) ?></td>
                                <td class="td-right"><?= formatRand((float)$inv['total']) ?></td>
                                <td><?= statusBadge($inv['status']) ?></td>
                                <td>
                                    <div class="td-actions">
                                        <a href="<?= $root ?>/invoices/view.php?id=<?= $inv['id'] ?>" class="btn btn-secondary btn-sm">View</a>
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
