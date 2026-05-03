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

$sql    = "SELECT c.*, (SELECT COUNT(*) FROM lms_invoices WHERE client_id = c.id) AS invoice_count
           FROM lms_clients c";
$params = [];

if ($search !== '') {
    $sql   .= " WHERE c.name LIKE ? OR c.email LIKE ? OR c.phone LIKE ?";
    $params = ["%{$search}%", "%{$search}%", "%{$search}%"];
}

$sql    .= " ORDER BY c.name ASC";
$stmt   = $pdo->prepare($sql);
$stmt->execute($params);
$clients = $stmt->fetchAll();

layoutHeader('Clients', 'clients');
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
    <form method="GET" class="filter-bar" style="margin-bottom:0;">
        <input type="text" name="q" placeholder="Search clients…" value="<?= e($search) ?>">
        <button type="submit" class="btn btn-secondary btn-sm">Search</button>
        <?php if ($search): ?>
            <a href="<?= $root ?>/clients/index.php" class="btn btn-secondary btn-sm">Clear</a>
        <?php endif; ?>
    </form>
    <a href="<?= $root ?>/clients/create.php" class="btn btn-primary">+ New Client</a>
</div>

<?php renderFlash(); ?>

<div class="card">
    <div class="card-body" style="padding:0;">
        <?php if (empty($clients)): ?>
            <div class="empty-state">
                <p><?= $search ? 'No clients match your search.' : 'No clients yet.' ?></p>
                <?php if (!$search): ?>
                    <a href="<?= $root ?>/clients/create.php" class="btn btn-primary">Add your first client</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table>
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
                            <tr>
                                <td><strong><?= e($client['name']) ?></strong></td>
                                <td><?= $client['email'] ? e($client['email']) : '<span style="color:var(--color-muted)">—</span>' ?></td>
                                <td><?= $client['phone'] ? e($client['phone']) : '<span style="color:var(--color-muted)">—</span>' ?></td>
                                <td><?= (int)$client['invoice_count'] ?></td>
                                <td>
                                    <div class="td-actions">
                                        <a href="<?= $root ?>/clients/edit.php?id=<?= $client['id'] ?>" class="btn btn-secondary btn-sm">Edit</a>
                                        <?php if ((int)$client['invoice_count'] === 0): ?>
                                            <a href="<?= $root ?>/clients/delete.php?id=<?= $client['id'] ?>" class="btn btn-danger btn-sm"
                                               onclick="return confirm('Delete this client? This cannot be undone.')">Delete</a>
                                        <?php endif; ?>
                                        <a href="<?= $root ?>/invoices/create.php?client_id=<?= $client['id'] ?>" class="btn btn-secondary btn-sm">+ Invoice</a>
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
