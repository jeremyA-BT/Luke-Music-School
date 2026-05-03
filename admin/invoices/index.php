<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';

requireAuth();

$pdo      = getDb();
$root     = getAdminRoot();

ensureClientNotesColumn($pdo);
ensureSchedulingColumns($pdo);
$search   = input('q', 'get');
$status   = input('status', 'get');
$from     = input('from', 'get');
$to       = input('to', 'get');
$clientId = inputInt('client_id', 'get');

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

if ($clientId > 0) {
    $where[]  = "i.client_id = ?";
    $params[] = $clientId;
}

$whereClause  = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$hasFilters   = ($search || $status || $from || $to || $clientId);

$perPage = 50;
$page    = max(1, inputInt('page', 'get'));
$offset  = ($page - 1) * $perPage;

$countSql  = "SELECT COUNT(*) FROM lms_invoices i JOIN lms_clients c ON c.id = i.client_id {$whereClause}";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalCount = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalCount / $perPage));

$sql = "
    SELECT i.*, c.name AS client_name
    FROM lms_invoices i
    JOIN lms_clients c ON c.id = i.client_id
    {$whereClause}
    ORDER BY i.invoice_date DESC, i.id DESC
    LIMIT {$perPage} OFFSET {$offset}
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$invoices = $stmt->fetchAll();

// Resolve client name for the filter tag if filtering by client
$filterClientName = '';
if ($clientId > 0) {
    $row = $pdo->prepare("SELECT name FROM lms_clients WHERE id = ?");
    $row->execute([$clientId]);
    $r = $row->fetch();
    $filterClientName = $r['name'] ?? '';
}

// Handle mark-as-paid POST action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && input('action') === 'mark_paid') {
    verifyCsrf();
    $markId = inputInt('invoice_id');
    if ($markId > 0) {
        $pdo->prepare("UPDATE lms_invoices SET status='paid' WHERE id=?")->execute([$markId]);
        flashMessage('success', 'Invoice marked as paid.');
    }
    $qs = http_build_query(array_filter(['q' => $search, 'status' => $status, 'from' => $from, 'to' => $to, 'client_id' => $clientId ?: null, 'page' => $page > 1 ? $page : null]));
    redirect($root . '/invoices/index.php' . ($qs ? '?' . $qs : ''));
}

$pageTitle = $filterClientName ? 'Invoices — ' . $filterClientName : 'Invoices';
layoutHeader($pageTitle, 'invoices');
?>

<?php renderFlash(); ?>

<!-- Filter panel -->
<form method="GET" id="filter-form">
    <div class="filter-panel">
        <div class="filter-panel-row">

            <div class="filter-field filter-search">
                <label for="q"><i class="fa-solid fa-magnifying-glass"></i> &nbsp;Search</label>
                <div class="input-wrap">
                    <i class="fa-solid fa-magnifying-glass input-icon"></i>
                    <input type="text" id="q" name="q" value="<?= e($search) ?>"
                           placeholder="Client name or invoice #"
                           autocomplete="off">
                </div>
            </div>

            <div class="filter-field filter-status">
                <label for="status"><i class="fa-solid fa-tag"></i> &nbsp;Status</label>
                <select id="status" name="status" onchange="document.getElementById('filter-form').submit()">
                    <option value="">All statuses</option>
                    <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>⬜ Draft</option>
                    <option value="sent"  <?= $status === 'sent'  ? 'selected' : '' ?>>🔵 Sent</option>
                    <option value="paid"  <?= $status === 'paid'  ? 'selected' : '' ?>>✅ Paid</option>
                </select>
            </div>

            <div class="filter-field filter-dates">
                <label for="from"><i class="fa-solid fa-calendar"></i> &nbsp;From</label>
                <input type="date" id="from" name="from" value="<?= e($from) ?>"
                       onchange="document.getElementById('filter-form').submit()">
            </div>

            <div class="filter-field filter-dates">
                <label for="to"><i class="fa-solid fa-calendar"></i> &nbsp;To</label>
                <input type="date" id="to" name="to" value="<?= e($to) ?>"
                       onchange="document.getElementById('filter-form').submit()">
            </div>

            <?php if ($clientId): ?>
                <input type="hidden" name="client_id" value="<?= $clientId ?>">
            <?php endif; ?>

            <div class="filter-actions">
                <button type="submit" class="btn btn-secondary btn-sm" title="Apply search">
                    <i class="fa-solid fa-magnifying-glass"></i> Search
                </button>
            </div>
        </div>

        <?php if ($hasFilters): ?>
        <div class="filter-active-bar">
            <span>Active filters:</span>
            <?php if ($filterClientName): ?>
                <span class="filter-tag"><i class="fa-solid fa-user"></i> <?= e($filterClientName) ?></span>
            <?php endif; ?>
            <?php if ($search): ?>
                <span class="filter-tag"><i class="fa-solid fa-magnifying-glass"></i> "<?= e($search) ?>"</span>
            <?php endif; ?>
            <?php if ($status): ?>
                <span class="filter-tag"><i class="fa-solid fa-tag"></i> <?= ucfirst($status) ?></span>
            <?php endif; ?>
            <?php if ($from || $to): ?>
                <span class="filter-tag">
                    <i class="fa-solid fa-calendar"></i>
                    <?= $from ? e($from) : '…' ?> → <?= $to ? e($to) : '…' ?>
                </span>
            <?php endif; ?>
            <button type="button" class="filter-clear-all" onclick="window.location='<?= $root ?>/invoices/index.php'">
                <i class="fa-solid fa-xmark"></i> Clear all filters
            </button>
        </div>
        <?php endif; ?>
    </div>
</form>

<!-- Header row -->
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
    <p style="margin:0;font-size:.88rem;color:var(--color-muted);">
        <?= $totalCount ?> invoice<?= $totalCount !== 1 ? 's' : '' ?>
        <?= $hasFilters ? 'matching filters' : 'total' ?>
    </p>
    <a href="<?= $root ?>/invoices/create.php" class="btn btn-primary">
        <i class="fa-solid fa-plus"></i> New Invoice
    </a>
</div>

<div class="card">
    <div class="card-body" style="padding:0;">
        <?php if (empty($invoices)): ?>
            <div class="empty-state">
                <p><?= $hasFilters ? 'No invoices match your filters.' : 'No invoices yet.' ?></p>
                <?php if (!$hasFilters): ?>
                    <a href="<?= $root ?>/invoices/create.php" class="btn btn-primary">Create your first invoice</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="mobile-cards">
                    <thead>
                        <tr>
                            <th style="width:80px">#</th>
                            <th>Client</th>
                            <th>Date</th>
                            <th class="td-right">Total</th>
                            <th style="width:90px">Status</th>
                            <th style="width:200px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($invoices as $inv): ?>
                            <tr>
                                <td class="td-mono" data-label="Invoice">#<?= e((string)$inv['invoice_number']) ?></td>
                                <td data-label="Client">
                                    <a href="<?= $root ?>/clients/edit.php?id=<?= $inv['client_id'] ?>"
                                       style="color:var(--color-text);font-weight:500;">
                                        <?= e($inv['client_name']) ?>
                                    </a>
                                </td>
                                <td data-label="Date"><?= formatDate($inv['invoice_date']) ?></td>
                                <td data-label="Total" style="font-weight:600;"><?= formatRand((float)$inv['total']) ?></td>
                                <td data-label="Status">
                                    <?php if ($inv['status'] === 'draft' && !empty($inv['scheduled_date'])): ?>
                                        <span class="status-badge status-scheduled"
                                              title="Scheduled to send <?= htmlspecialchars(date('j M Y', strtotime($inv['scheduled_date'])), ENT_QUOTES, 'UTF-8') ?>">
                                            <i class="fa-solid fa-calendar-clock"></i>
                                            <?= htmlspecialchars(date('j M', strtotime($inv['scheduled_date'])), ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                        <?php if (!empty($inv['recurrence']) && $inv['recurrence'] !== 'none'): ?>
                                            <span class="status-badge status-recurring" title="Repeats <?= e($inv['recurrence']) ?>">
                                                <i class="fa-solid fa-rotate"></i>
                                                <?= e(ucfirst($inv['recurrence'])) ?>
                                            </span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <?= statusBadge($inv['status']) ?>
                                        <?php if (!empty($inv['recurrence']) && $inv['recurrence'] !== 'none'): ?>
                                            <span class="status-badge status-recurring" title="Repeats <?= e($inv['recurrence']) ?>">
                                                <i class="fa-solid fa-rotate"></i>
                                            </span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td class="td-actions-cell">
                                    <div class="row-actions">

                                        <a href="<?= $root ?>/invoices/view.php?id=<?= $inv['id'] ?>"
                                           class="btn btn-secondary btn-sm" title="View invoice">
                                            <i class="fa-solid fa-eye"></i> View
                                        </a>

                                        <div class="btn-group btn-mobile-hide">
                                            <a href="<?= $root ?>/invoices/edit.php?id=<?= $inv['id'] ?>"
                                               class="btn-act" title="Edit invoice">
                                                <i class="fa-solid fa-pencil"></i>
                                            </a>
                                            <a href="<?= $root ?>/invoice-pdf.php?id=<?= $inv['id'] ?>"
                                               class="btn-act" target="_blank" title="Download PDF">
                                                <i class="fa-solid fa-file-pdf"></i>
                                            </a>
                                            <a href="<?= $root ?>/email-invoice.php?id=<?= $inv['id'] ?>"
                                               class="btn-act" title="Email invoice">
                                                <i class="fa-solid fa-envelope"></i>
                                            </a>
                                        </div>

                                        <?php if ($inv['status'] !== 'paid'): ?>
                                        <form method="POST" action="" style="display:contents;">
                                            <?php csrfField(); ?>
                                            <input type="hidden" name="action" value="mark_paid">
                                            <input type="hidden" name="invoice_id" value="<?= $inv['id'] ?>">
                                            <div class="btn-group">
                                                <button type="submit" class="btn-act btn-act-success"
                                                        title="Mark as paid"
                                                        onclick="return confirm('Mark invoice #<?= $inv['invoice_number'] ?> as paid?')">
                                                    <i class="fa-solid fa-check"></i>
                                                </button>
                                            </div>
                                        </form>
                                        <?php endif; ?>

                                        <div class="btn-group btn-mobile-hide">
                                            <a href="<?= $root ?>/invoices/duplicate.php?id=<?= $inv['id'] ?>"
                                               class="btn-act" title="Duplicate invoice"
                                               onclick="return confirm('Duplicate invoice #<?= $inv['invoice_number'] ?>?')">
                                                <i class="fa-solid fa-copy"></i>
                                            </a>
                                            <?php if ($inv['status'] === 'paid'): ?>
                                                <span class="btn-act btn-act-danger" title="Paid invoices cannot be deleted"
                                                      style="opacity:.35;cursor:not-allowed;"
                                                      onclick="alert('Paid invoices cannot be deleted.')">
                                                    <i class="fa-solid fa-trash"></i>
                                                </span>
                                            <?php elseif ($inv['status'] === 'sent'): ?>
                                                <a href="<?= $root ?>/invoices/delete.php?id=<?= $inv['id'] ?>"
                                                   class="btn-act btn-act-danger" title="Delete sent invoice"
                                                   onclick="return confirm('Invoice #<?= $inv['invoice_number'] ?> has already been sent to the client. Delete it permanently?')">
                                                    <i class="fa-solid fa-trash"></i>
                                                </a>
                                            <?php else: ?>
                                                <a href="<?= $root ?>/invoices/delete.php?id=<?= $inv['id'] ?>"
                                                   class="btn-act btn-act-danger" title="Delete invoice"
                                                   onclick="return confirm('Delete invoice #<?= $inv['invoice_number'] ?>? This cannot be undone.')">
                                                    <i class="fa-solid fa-trash"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>

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

<?php if ($totalPages > 1): ?>
<div class="pagination">
    <?php if ($page > 1): ?>
        <a href="?<?= http_build_query(array_filter(['q' => $search, 'status' => $status, 'from' => $from, 'to' => $to, 'client_id' => $clientId ?: null, 'page' => $page - 1])) ?>"
           class="btn btn-secondary btn-sm"><i class="fa-solid fa-chevron-left"></i> Prev</a>
    <?php endif; ?>
    <span class="pagination-info">Page <?= $page ?> of <?= $totalPages ?></span>
    <?php if ($page < $totalPages): ?>
        <a href="?<?= http_build_query(array_filter(['q' => $search, 'status' => $status, 'from' => $from, 'to' => $to, 'client_id' => $clientId ?: null, 'page' => $page + 1])) ?>"
           class="btn btn-secondary btn-sm">Next <i class="fa-solid fa-chevron-right"></i></a>
    <?php endif; ?>
</div>
<?php endif; ?>

<script>
(function () {
    // Auto-submit search on Enter (already default), debounce typing
    var searchInput = document.getElementById('q');
    if (searchInput) {
        var timer;
        searchInput.addEventListener('input', function () {
            clearTimeout(timer);
            timer = setTimeout(function () {
                document.getElementById('filter-form').submit();
            }, 600);
        });
    }
}());
</script>

<?php layoutFooter(); ?>
