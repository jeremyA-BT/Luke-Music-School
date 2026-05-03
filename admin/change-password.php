<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/layout.php';

requireAuth();

$pdo    = getDb();
$root   = getAdminRoot();
$errors = [];
$done   = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword     = $_POST['new_password']     ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // Fetch current admin record
    $adminId = (int) $_SESSION['admin_id'];
    $stmt    = $pdo->prepare("SELECT * FROM lms_admins WHERE id = ?");
    $stmt->execute([$adminId]);
    $admin = $stmt->fetch();

    if (!$admin || !password_verify($currentPassword, $admin['password_hash'])) {
        $errors[] = 'Current password is incorrect.';
    }

    if (strlen($newPassword) < 8) {
        $errors[] = 'New password must be at least 8 characters.';
    }

    if ($newPassword !== $confirmPassword) {
        $errors[] = 'New passwords do not match.';
    }

    if (empty($errors)) {
        $hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
        $pdo->prepare("UPDATE lms_admins SET password_hash = ? WHERE id = ?")
            ->execute([$hash, $adminId]);
        $done = true;
        flashMessage('success', 'Password updated successfully.');
    }
}

layoutHeader('Change Password', 'settings');
?>

<?php renderFlash(); ?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error"><?= implode('<br>', array_map('e', $errors)) ?></div>
<?php endif; ?>

<div class="card" style="max-width:480px;">
    <div class="card-header">
        <h2><i class="fa-solid fa-lock" style="color:var(--color-accent);margin-right:6px;"></i> Change Password</h2>
    </div>
    <div class="card-body">
        <form method="POST" action="">
            <?php csrfField(); ?>
            <div class="form-grid">
                <div class="form-group full">
                    <label for="current_password">Current Password</label>
                    <input type="password" id="current_password" name="current_password"
                           autocomplete="current-password" required>
                </div>
                <div class="form-group full">
                    <label for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password"
                           autocomplete="new-password" minlength="8" required>
                    <span class="form-hint">Minimum 8 characters.</span>
                </div>
                <div class="form-group full">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password"
                           autocomplete="new-password" minlength="8" required>
                </div>
            </div>
            <div class="form-actions" style="margin-top:20px;">
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-floppy-disk"></i> Update Password
                </button>
                <a href="<?= $root ?>/settings.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php layoutFooter(); ?>
