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
$values = ['name' => '', 'address' => '', 'email' => '', 'phone' => '', 'notes' => ''];

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
            INSERT INTO lms_clients (name, address, email, phone, notes)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $values['name'],
            $values['address'] ?: null,
            $values['email']   ?: null,
            $values['phone']   ?: null,
            $values['notes']   ?: null,
        ]);

        flashMessage('success', 'Client "' . $values['name'] . '" created successfully.');
        redirect($root . '/clients/index.php');
    }
}

layoutHeader('New Client', 'clients');
?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error"><?= implode('<br>', array_map('e', $errors)) ?></div>
<?php endif; ?>

<div class="card" style="max-width:640px;">
    <div class="card-header"><h2>Client Details</h2></div>
    <div class="card-body">
        <form method="POST" action="">
            <?php csrfField(); ?>
            <div class="form-grid">
                <div class="form-group full">
                    <label for="name">Full Name *</label>
                    <input type="text" id="name" name="name" value="<?= e($values['name']) ?>" required autofocus>
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
                    <span class="form-hint">Not visible on invoices.</span>
                </div>
            </div>

            <div class="form-actions" style="margin-top:20px;">
                <button type="submit" class="btn btn-primary">Save Client</button>
                <a href="<?= $root ?>/clients/index.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php layoutFooter(); ?>
