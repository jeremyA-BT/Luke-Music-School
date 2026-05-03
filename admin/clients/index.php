<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';

requireAuth();

$pdo = getDb();
$root = getAdminRoot();

ensureClientArchivedColumn($pdo);

$search = input('q', 'get');
$status = input('status', 'get'); // 'active' (default), 'archived', 'all'
if (!in_array($status, ['active', 'archived', 'all'])) {
    $status = 'active';
}

$conditions = [];
$params     = [];

if ($status === 'active') {
    $conditions[] = 'c.archived_at IS NULL';
} elseif ($status === 'archived') {
    $conditions[] = 'c.archived_at IS NOT NULL';
}

if ($search !== '') {
    $conditions[] = '(c.name LIKE ? OR c.email LIKE ? OR c.phone LIKE ?)';
    $params[]     = "%{$search}%";
    $params[]     = "%{$search}%";
    $params[]     = "%{$search}%";
}

$whereClause = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';

$perPage    = 50;
$page       = max(1, inputInt('page', 'get'));
$offset     = ($page - 1) * $perPage;

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM lms_clients c" . $whereClause);
$countStmt->execute($params);
$totalCount = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalCount / $perPage));

$sql = "SELECT c.*, (SELECT COUNT(*) FROM lms_invoices WHERE client_id = c.id) AS invoice_count
        FROM lms_clients c
        {$whereClause}
        ORDER BY c.name ASC
        LIMIT {$perPage} OFFSET {$offset}";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$clients = $stmt->fetchAll();

layoutHeader('Clients', 'clients');
?>

<form method="GET" id="client-filter-form">
    <div class="filter-panel">
        <div class="filter-panel-row">
            <div class="filter-field filter-search">
                <label for="q"><i class="fa-solid fa-magnifying-glass"></i> &nbsp;Search</label>
                <div class="input-wrap">
                    <i class="fa-solid fa-magnifying-glass input-icon"></i>
                    <input type="text" id="q" name="q" value="<?= e($search) ?>"
                           placeholder="Name, email or phone…" autocomplete="off">
                </div>
            </div>
            <div class="filter-field">
                <label>Status</label>
                <div class="period-tabs" style="margin-top:2px;">
                    <a href="?<?= http_build_query(array_filter(['q' => $search, 'status' => 'active'])) ?>"
                       class="period-tab <?= $status === 'active' ? 'active' : '' ?>">Active</a>
                    <a href="?<?= http_build_query(array_filter(['q' => $search, 'status' => 'archived'])) ?>"
                       class="period-tab <?= $status === 'archived' ? 'active' : '' ?>">Archived</a>
                    <a href="?<?= http_build_query(array_filter(['q' => $search, 'status' => 'all'])) ?>"
                       class="period-tab <?= $status === 'all' ? 'active' : '' ?>">All</a>
                </div>
            </div>
            <div class="filter-actions">
                <?php if ($search): ?>
                    <a href="<?= $root ?>/clients/index.php?status=<?= e($status) ?>" class="btn btn-ghost btn-sm">
                        <i class="fa-solid fa-xmark"></i> Clear
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</form>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
    <p style="margin:0;font-size:.88rem;color:var(--color-muted);">
        <?= $totalCount ?> <?= $status === 'archived' ? 'archived ' : ($status === 'active' ? 'active ' : '') ?>client<?= $totalCount !== 1 ? 's' : '' ?>
    </p>
    <a href="<?= $root ?>/clients/create.php" class="btn btn-primary">
        <i class="fa-solid fa-plus"></i> New Client
    </a>
</div>

<?php renderFlash(); ?>
<script>
(function () {
    var input = document.getElementById('q');
    if (input) {
        var timer;
        input.addEventListener('input', function () {
            clearTimeout(timer);
            timer = setTimeout(function () { document.getElementById('client-filter-form').submit(); }, 600);
        });
    }
}());
</script>

<div class="card">
    <div class="card-body" style="padding:0;">
        <?php if (empty($clients)): ?>
            <div class="empty-state">
                <p>
                    <?php if ($search): ?>
                        No clients match your search.
                    <?php elseif ($status === 'archived'): ?>
                        No archived clients.
                    <?php else: ?>
                        No clients yet.
                    <?php endif; ?>
                </p>
                <?php if (!$search && $status === 'active'): ?>
                    <a href="<?= $root ?>/clients/create.php" class="btn btn-primary">Add your first client</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="mobile-cards">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Invoices</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clients as $client): ?>
                            <?php $isArchived = !empty($client['archived_at']); ?>
                            <tr <?= $isArchived ? 'style="opacity:.6;"' : '' ?>>
                                <td data-label="Name">
                                    <strong><?= e($client['name']) ?></strong>
                                    <?php if ($isArchived): ?>
                                        <span class="status-badge" style="background:#f3f4f6;color:#6b7280;margin-left:4px;">
                                            <i class="fa-solid fa-box-archive"></i> Archived
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Email"><?= $client['email'] ? '<a href="mailto:' . e($client['email']) . '" style="color:var(--color-muted)">' . e($client['email']) . '</a>' : '<span style="color:var(--color-muted)">—</span>' ?></td>
                                <td data-label="Phone"><?= $client['phone'] ? e($client['phone']) : '<span style="color:var(--color-muted)">—</span>' ?></td>
                                <td data-label="Invoices">
                                    <?php if ((int)$client['invoice_count'] > 0): ?>
                                        <a href="<?= $root ?>/invoices/index.php?client_id=<?= $client['id'] ?>"
                                           style="font-weight:600;color:var(--color-accent);" title="View invoices">
                                            <?= (int)$client['invoice_count'] ?>
                                            <i class="fa-solid fa-arrow-up-right-from-square" style="font-size:.65rem;opacity:.6;"></i>
                                        </a>
                                    <?php else: ?>
                                        <span style="color:var(--color-muted)">0</span>
                                    <?php endif; ?>
                                </td>
                                <td class="td-actions-cell">
                                    <a href="<?= $root ?>/clients/edit.php?id=<?= $client['id'] ?>"
                                       class="btn btn-secondary btn-sm" title="Edit client">
                                        <i class="fa-solid fa-pencil"></i> Edit
                                    </a>
                                    <?php if (!$isArchived): ?>
                                        <a href="<?= $root ?>/invoices/create.php?client_id=<?= $client['id'] ?>"
                                           class="btn btn-primary btn-sm btn-mobile-hide" title="New invoice">
                                            <i class="fa-solid fa-plus"></i> Invoice
                                        </a>
                                        <?php
                                        // Check for active recurring invoices to build appropriate confirm message
                                        $recurCheck = $pdo->prepare("SELECT COUNT(*) FROM lms_invoices WHERE client_id = ? AND status = 'draft' AND recurrence != 'none' AND recurrence != '' AND scheduled_date IS NOT NULL");
                                        $recurCheck->execute([$client['id']]);
                                        $hasRecurring = (int)$recurCheck->fetchColumn() > 0;
                                        $archiveMsg = 'Archive ' . $client['name'] . '? They will be hidden from the active client list.'
                                            . ($hasRecurring ? ' Their recurring invoice schedule will also be cancelled.' : '');
                                        ?>
                                        <a href="<?= $root ?>/clients/archive.php?id=<?= $client['id'] ?>&action=archive"
                                           class="btn btn-ghost btn-sm btn-mobile-hide" title="Archive client"
                                           onclick="return confirm(<?= e(json_encode($archiveMsg)) ?>)">
                                            <i class="fa-solid fa-box-archive"></i>
                                        </a>
                                        <?php if ((int)$client['invoice_count'] === 0): ?>
                                            <a href="<?= $root ?>/clients/delete.php?id=<?= $client['id'] ?>"
                                               class="btn btn-danger btn-sm btn-icon-only btn-mobile-hide" title="Delete client"
                                               onclick="return confirm('Permanently delete <?= e($client['name']) ?>? This cannot be undone.')">
                                                <i class="fa-solid fa-trash"></i>
                                            </a>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <a href="<?= $root ?>/clients/archive.php?id=<?= $client['id'] ?>&action=unarchive"
                                           class="btn btn-secondary btn-sm" title="Restore client"
                                           onclick="return confirm('Restore <?= e($client['name']) ?> to active clients?')">
                                            <i class="fa-solid fa-rotate-left"></i> Restore
                                        </a>
                                    <?php endif; ?>
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
        <a href="?<?= http_build_query(array_filter(['q' => $search, 'status' => $status, 'page' => $page - 1])) ?>"
           class="btn btn-secondary btn-sm">← Prev</a>
    <?php endif; ?>
    <span class="pagination-info">Page <?= $page ?> of <?= $totalPages ?> (<?= $totalCount ?> clients)</span>
    <?php if ($page < $totalPages): ?>
        <a href="?<?= http_build_query(array_filter(['q' => $search, 'status' => $status, 'page' => $page + 1])) ?>"
           class="btn btn-secondary btn-sm">Next →</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php layoutFooter(); ?>
