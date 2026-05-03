<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';

requireAuth();

$pdo     = getDb();
$root    = getAdminRoot();
$presets = $pdo->query("SELECT * FROM lms_presets ORDER BY sort_order ASC, id ASC")->fetchAll();

layoutHeader('Lesson Presets', 'presets');
?>

<?php renderFlash(); ?>

<div style="display:flex;justify-content:flex-end;margin-bottom:20px;">
    <a href="<?= $root ?>/presets/create.php" class="btn btn-primary">+ New Preset</a>
</div>

<div class="card">
    <div class="card-header">
        <h2>Lesson Type Presets</h2>
    </div>
    <div class="card-body" style="padding-bottom:8px;">
        <p style="color:var(--color-muted);font-size:0.875rem;margin:0 0 16px;">
            Presets pre-fill the line item description and rate when creating invoices. You can still edit them per invoice.
        </p>
    </div>
    <div class="card-body" style="padding:0;">
        <?php if (empty($presets)): ?>
            <div class="empty-state">
                <p>No presets defined yet.</p>
                <a href="<?= $root ?>/presets/create.php" class="btn btn-primary">Add your first preset</a>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Description</th>
                            <th class="td-right">Default Rate</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($presets as $preset): ?>
                            <tr>
                                <td><?= e($preset['description']) ?></td>
                                <td class="td-right"><?= formatRand((float)$preset['default_rate']) ?></td>
                                <td>
                                    <?php if ($preset['is_active']): ?>
                                        <span class="badge badge-paid">Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-draft">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="td-actions">
                                        <a href="<?= $root ?>/presets/edit.php?id=<?= $preset['id'] ?>" class="btn btn-secondary btn-sm">Edit</a>
                                        <a href="<?= $root ?>/presets/delete.php?id=<?= $preset['id'] ?>" class="btn btn-danger btn-sm"
                                           onclick="return confirm('Delete this preset?')">Delete</a>
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
