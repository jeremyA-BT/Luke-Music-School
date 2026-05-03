<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/layout.php';

requireAuth();

$pdo  = getDb();
$root = getAdminRoot();

// Period filter
$period = input('period', 'get');
if (!in_array($period, ['month', 'quarter', 'year', 'all'])) {
    $period = 'month';
}

$periodLabels = [
    'month'   => 'This Month',
    'quarter' => 'Last 3 Months',
    'year'    => 'This Year',
    'all'     => 'All Time',
];

switch ($period) {
    case 'month':
        $dateFilter  = "AND invoice_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')";
        $periodDesc  = 'since the start of ' . date('F Y');
        break;
    case 'quarter':
        $dateFilter  = "AND invoice_date >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)";
        $periodDesc  = 'in the last 3 months';
        break;
    case 'year':
        $dateFilter  = "AND invoice_date >= DATE_FORMAT(CURDATE(), '%Y-01-01')";
        $periodDesc  = 'since 1 Jan ' . date('Y');
        break;
    default: // all
        $dateFilter  = '';
        $periodDesc  = 'all time';
        break;
}

// Stats for selected period
$totalInvoices = (int) $pdo->query("SELECT COUNT(*) FROM lms_invoices WHERE 1=1 {$dateFilter}")->fetchColumn();
$totalPaid     = (float) $pdo->query("SELECT COALESCE(SUM(total),0) FROM lms_invoices WHERE status='paid' {$dateFilter}")->fetchColumn();
$countSent     = (int) $pdo->query("SELECT COUNT(*) FROM lms_invoices WHERE status='sent' {$dateFilter}")->fetchColumn();
$countDraft    = (int) $pdo->query("SELECT COUNT(*) FROM lms_invoices WHERE status='draft' {$dateFilter}")->fetchColumn();
$awaitingAmt   = (float) $pdo->query("SELECT COALESCE(SUM(total),0) FROM lms_invoices WHERE status='sent' {$dateFilter}")->fetchColumn();

// Monthly revenue (last 6 months of paid invoices — always last 6 months regardless of period)
$monthlyRevenue = $pdo->query("
    SELECT DATE_FORMAT(invoice_date, '%Y-%m') AS month_key,
           DATE_FORMAT(invoice_date, '%b %Y')  AS month_label,
           COALESCE(SUM(total), 0)              AS revenue,
           COUNT(*)                             AS invoice_count
    FROM lms_invoices
    WHERE status = 'paid'
      AND invoice_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY month_key, month_label
    ORDER BY month_key ASC
")->fetchAll();

// Scheduled invoices due today or overdue (draft, not yet emailed)
ensureSchedulingColumns($pdo);
$scheduledDue = $pdo->query("
    SELECT i.*, c.name AS client_name, c.email AS client_email
    FROM lms_invoices i
    LEFT JOIN lms_clients c ON c.id = i.client_id
    WHERE i.status = 'draft'
      AND i.scheduled_date IS NOT NULL
      AND i.scheduled_date <= CURDATE()
      AND i.email_sent_at IS NULL
    ORDER BY i.scheduled_date ASC
")->fetchAll();

// Upcoming scheduled invoices (next 7 days)
$scheduledSoon = $pdo->query("
    SELECT i.*, c.name AS client_name
    FROM lms_invoices i
    LEFT JOIN lms_clients c ON c.id = i.client_id
    WHERE i.status = 'draft'
      AND i.scheduled_date IS NOT NULL
      AND i.scheduled_date > CURDATE()
      AND i.scheduled_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    ORDER BY i.scheduled_date ASC
")->fetchAll();

// Scheduled invoices that can't send because the client has no email
$scheduledNoEmail = $pdo->query("
    SELECT i.invoice_number, i.id, c.name AS client_name, i.scheduled_date
    FROM lms_invoices i
    LEFT JOIN lms_clients c ON c.id = i.client_id
    WHERE i.status = 'draft'
      AND i.scheduled_date IS NOT NULL
      AND i.email_sent_at IS NULL
      AND (c.email IS NULL OR c.email = '')
    ORDER BY i.scheduled_date ASC
")->fetchAll();

// Total active clients (not archived)
$totalClients = (int) $pdo->query("SELECT COUNT(*) FROM lms_clients WHERE archived_at IS NULL")->fetchColumn();

// Recent invoices
$recentInvoices = $pdo->query("
    SELECT i.*, c.name AS client_name
    FROM lms_invoices i
    JOIN lms_clients c ON c.id = i.client_id
    ORDER BY i.invoice_date DESC, i.id DESC
    LIMIT 8
")->fetchAll();

layoutHeader('Dashboard', 'dashboard');
?>

<?php renderFlash(); ?>

<?php if (!empty($scheduledDue)): ?>
<div class="alert" style="background:#fef3c7;border:1px solid #fcd34d;color:#92400e;border-radius:var(--radius);padding:14px 18px;margin-bottom:20px;display:flex;gap:12px;align-items:flex-start;">
    <i class="fa-solid fa-calendar-exclamation" style="font-size:1.1rem;margin-top:2px;flex-shrink:0;"></i>
    <div style="flex:1;">
        <strong><?= count($scheduledDue) ?> scheduled invoice<?= count($scheduledDue) !== 1 ? 's' : '' ?> due to send</strong>
        <span style="font-size:.82rem;margin-left:6px;">— the cron job should handle this automatically. If you see this message, check the cron job is configured in DirectAdmin.</span>
        <div style="margin-top:8px;display:flex;flex-wrap:wrap;gap:8px;">
            <?php foreach ($scheduledDue as $sd): ?>
                <span style="display:flex;align-items:center;gap:6px;">
                    <a href="<?= $root ?>/invoices/view.php?id=<?= $sd['id'] ?>"
                       style="font-size:.8rem;font-weight:600;color:#92400e;text-decoration:underline;">
                        #<?= e((string)$sd['invoice_number']) ?> <?= e($sd['client_name']) ?>
                    </a>
                    <?php if (!empty($sd['client_email'])): ?>
                        <a href="<?= $root ?>/email-invoice.php?id=<?= $sd['id'] ?>"
                           style="background:#92400e;color:#fff;border:none;font-size:.72rem;padding:2px 8px;border-radius:4px;text-decoration:none;font-weight:600;">
                            Send now
                        </a>
                    <?php endif; ?>
                </span>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($scheduledSoon)): ?>
<div style="background:var(--color-surface);border:1px solid var(--color-border);border-radius:var(--radius);padding:12px 16px;margin-bottom:20px;font-size:.8125rem;color:var(--color-muted);">
    <i class="fa-solid fa-calendar-days" style="color:var(--color-accent);"></i>
    <strong style="color:var(--color-text);">Sending soon:</strong>
    <?php foreach ($scheduledSoon as $ss): ?>
        <a href="<?= $root ?>/invoices/view.php?id=<?= $ss['id'] ?>"
           style="color:var(--color-text);margin:0 6px;white-space:nowrap;">
            #<?= e((string)$ss['invoice_number']) ?> <?= e($ss['client_name']) ?>
            <span style="color:var(--color-muted);">(<?= htmlspecialchars(date('j M', strtotime($ss['scheduled_date'])), ENT_QUOTES, 'UTF-8') ?>)</span>
        </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (!empty($scheduledNoEmail)): ?>
<div class="alert" style="background:#fef2f2;border:1px solid #fca5a5;color:#991b1b;border-radius:var(--radius);padding:14px 18px;margin-bottom:20px;display:flex;gap:12px;align-items:flex-start;">
    <i class="fa-solid fa-envelope-circle-check" style="font-size:1.1rem;margin-top:2px;flex-shrink:0;text-decoration:line-through;"></i>
    <div>
        <strong><?= count($scheduledNoEmail) ?> scheduled invoice<?= count($scheduledNoEmail) !== 1 ? 's' : '' ?> can't be sent</strong>
        — the client has no email address on file.
        <div style="margin-top:6px;display:flex;flex-wrap:wrap;gap:6px;">
            <?php foreach ($scheduledNoEmail as $ne): ?>
                <span>
                    <a href="<?= $root ?>/invoices/view.php?id=<?= $ne['id'] ?>"
                       style="font-weight:600;color:#991b1b;text-decoration:underline;">
                        #<?= e((string)$ne['invoice_number']) ?> <?= e($ne['client_name']) ?>
                    </a>
                    <span style="font-size:.8rem;">(<?= htmlspecialchars(date('j M', strtotime($ne['scheduled_date'])), ENT_QUOTES, 'UTF-8') ?>)</span>
                </span>
            <?php endforeach; ?>
        </div>
        <p style="margin:6px 0 0;font-size:.8rem;">Add an email address to each client's profile to fix this.</p>
    </div>
</div>
<?php endif; ?>

<!-- Period tabs -->
<div class="period-header-row" style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:10px;">
    <div class="period-tabs">
        <?php foreach ($periodLabels as $key => $label): ?>
            <a href="?period=<?= $key ?>" class="period-tab <?= $period === $key ? 'active' : '' ?>">
                <?= $label ?>
            </a>
        <?php endforeach; ?>
    </div>
    <span style="font-size:.82rem;color:var(--color-muted);">
        <i class="fa-solid fa-clock-rotate-left"></i>
        Showing stats for <strong><?= $periodLabels[$period] ?></strong>
        (<?= $periodDesc ?>)
    </span>
</div>

<!-- Stats grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-label"><i class="fa-solid fa-file-invoice"></i> Invoices</div>
        <div class="stat-value"><?= $totalInvoices ?></div>
        <div class="stat-sub"><?= $countDraft ?> draft · <?= $countSent ?> awaiting payment</div>
    </div>
    <div class="stat-card">
        <div class="stat-label"><i class="fa-solid fa-users"></i> Active Clients</div>
        <div class="stat-value"><?= $totalClients ?></div>
        <div class="stat-sub"><a href="<?= $root ?>/clients/index.php?status=archived" style="color:var(--color-muted);font-size:.8rem;">View archived</a></div>
    </div>
    <div class="stat-card">
        <div class="stat-label"><i class="fa-solid fa-circle-check" style="color:var(--color-success)"></i> Revenue Collected</div>
        <div class="stat-value" style="font-size:1.4rem;"><?= formatRand($totalPaid) ?></div>
        <div class="stat-sub"><?= $periodLabels[$period] ?> · paid invoices</div>
    </div>
    <div class="stat-card">
        <div class="stat-label"><i class="fa-solid fa-clock" style="color:#1d4ed8"></i> Awaiting Payment</div>
        <div class="stat-value" style="font-size:1.4rem;"><?= formatRand($awaitingAmt) ?></div>
        <div class="stat-sub"><?= $countSent ?> sent invoice<?= $countSent !== 1 ? 's' : '' ?></div>
    </div>
</div>

<!-- Monthly revenue -->
<?php if (!empty($monthlyRevenue)): ?>
<div class="card" style="margin-bottom:24px;">
    <div class="card-header">
        <h2><i class="fa-solid fa-chart-bar" style="color:var(--color-accent);margin-right:6px;"></i> Monthly Revenue</h2>
        <span style="font-size:.8rem;color:var(--color-muted);">Paid invoices · last 6 months</span>
    </div>
    <div class="card-body" style="padding:0;">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Month</th>
                        <th style="text-align:center;">Invoices Paid</th>
                        <th class="td-right">Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($monthlyRevenue as $row): ?>
                        <tr>
                            <td><?= e($row['month_label']) ?></td>
                            <td style="text-align:center;"><?= (int)$row['invoice_count'] ?></td>
                            <td class="td-right" style="font-weight:600;"><?= formatRand((float)$row['revenue']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Recent invoices -->
<div class="card">
    <div class="card-header">
        <h2><i class="fa-solid fa-clock-rotate-left" style="color:var(--color-accent);margin-right:6px;"></i> Recent Invoices</h2>
        <a href="<?= $root ?>/invoices/create.php" class="btn btn-primary btn-sm">
            <i class="fa-solid fa-plus"></i> New Invoice
        </a>
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
                                <td class="td-right" style="font-weight:600;"><?= formatRand((float)$inv['total']) ?></td>
                                <td><?= statusBadge($inv['status']) ?></td>
                                <td>
                                    <a href="<?= $root ?>/invoices/view.php?id=<?= $inv['id'] ?>"
                                       class="btn btn-secondary btn-sm">
                                        <i class="fa-solid fa-eye"></i> View
                                    </a>
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
