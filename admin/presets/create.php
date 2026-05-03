<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';

requireAuth();

$pdo    = getDb();
$root   = getAdminRoot();
$errors = [];
$values = ['description' => '', 'default_rate' => '', 'sort_order' => '0', 'is_active' => '1'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $values['description']  = input('description');
    $values['default_rate'] = input('default_rate');
    $values['sort_order']   = input('sort_order');
    $values['is_active']    = isset($_POST['is_active']) ? '1' : '0';

    if ($values['description'] === '') {
        $errors[] = 'Description is required.';
    }

    if (!is_numeric($values['default_rate']) || (float)$values['default_rate'] < 0) {
        $errors[] = 'Default rate must be a positive number.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("
            INSERT INTO lms_presets (description, default_rate, sort_order, is_active)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $values['description'],
            (float) $values['default_rate'],
            (int)   $values['sort_order'],
            (int)   $values['is_active'],
        ]);

        flashMessage('success', 'Preset created successfully.');
        redirect($root . '/presets/index.php');
    }
}

layoutHeader('New Preset', 'presets');
?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error"><?= implode('<br>', array_map('e', $errors)) ?></div>
<?php endif; ?>

<div class="card" style="max-width:480px;">
    <div class="card-header"><h2>New Lesson Preset</h2></div>
    <div class="card-body">
        <form method="POST" action="">
            <?php csrfField(); ?>
            <div class="form-grid single">
                <div class="form-group">
                    <label for="description">Description *</label>
                    <input type="text" id="description" name="description"
                           value="<?= e($values['description']) ?>"
                           placeholder="e.g. 30 Minute Music Lesson" required autofocus>
                </div>

                <div class="form-group">
                    <label for="default_rate">Default Rate (R) *</label>
                    <input type="number" id="default_rate" name="default_rate"
                           value="<?= e($values['default_rate']) ?>"
                           min="0" step="0.01" required>
                </div>

                <div class="form-group">
                    <label for="sort_order">Sort Order</label>
                    <input type="number" id="sort_order" name="sort_order"
                           value="<?= e($values['sort_order']) ?>" min="0" step="1">
                    <span class="form-hint">Lower numbers appear first in the preset list.</span>
                </div>

                <div class="form-group">
                    <label>
                        <input type="checkbox" name="is_active" value="1" <?= $values['is_active'] ? 'checked' : '' ?>>
                        Active (available when creating invoices)
                    </label>
                </div>
            </div>

            <div class="form-actions" style="margin-top:20px;">
                <button type="submit" class="btn btn-primary">Save Preset</button>
                <a href="<?= $root ?>/presets/index.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php layoutFooter(); ?>
