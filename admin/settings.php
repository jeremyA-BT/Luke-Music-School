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

// Load current settings (auto-seeds from config.php if first run)
$settings = getAllSettings($pdo);

$activeTab = input('tab', 'get') ?: 'business';

// -------------------------------------------------------
// Handle POST
// -------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = input('action');

    // --- Change password ---
    if ($action === 'change_password') {
        $current  = $_POST['current_password']  ?? '';
        $new      = $_POST['new_password']       ?? '';
        $confirm  = $_POST['confirm_password']   ?? '';
        $activeTab = 'account';

        $stmt = $pdo->prepare("SELECT * FROM lms_admins WHERE id = ?");
        $stmt->execute([$_SESSION['admin_id']]);
        $admin = $stmt->fetch();

        if (!password_verify($current, $admin['password_hash'])) {
            $errors[] = 'Current password is incorrect.';
        } elseif (strlen($new) < 8) {
            $errors[] = 'New password must be at least 8 characters.';
        } elseif ($new !== $confirm) {
            $errors[] = 'New passwords do not match.';
        } else {
            $hash = password_hash($new, PASSWORD_BCRYPT, ['cost' => 12]);
            $pdo->prepare("UPDATE lms_admins SET password_hash = ? WHERE id = ?")
                ->execute([$hash, $_SESSION['admin_id']]);
            flashMessage('success', 'Password changed successfully.');
            redirect($root . '/settings.php?tab=account');
        }
    }

    // --- Save business details ---
    if ($action === 'save_business') {
        $activeTab = 'business';
        $fields = ['business_name', 'owner_name', 'address_line1', 'address_line2', 'phone', 'email'];
        foreach ($fields as $field) {
            setSetting($pdo, $field, input($field));
        }
        $settings = getAllSettings($pdo);
        flashMessage('success', 'Business details saved.');
        redirect($root . '/settings.php?tab=business');
    }

    // --- Save banking details ---
    if ($action === 'save_banking') {
        $activeTab = 'banking';
        $fields = ['bank_name', 'bank_branch', 'bank_branch_code', 'account_holder', 'account_number', 'account_type'];
        foreach ($fields as $field) {
            setSetting($pdo, $field, input($field));
        }
        $settings = getAllSettings($pdo);
        flashMessage('success', 'Banking details saved.');
        redirect($root . '/settings.php?tab=banking');
    }

    // --- Save email settings ---
    if ($action === 'save_email') {
        $activeTab = 'email';
        setSetting($pdo, 'email_from_name', input('email_from_name'));
        setSetting($pdo, 'email_from',      input('email_from'));
        setSetting($pdo, 'email_reply_to',  input('email_reply_to'));
        $settings = getAllSettings($pdo);
        flashMessage('success', 'Email settings saved.');
        redirect($root . '/settings.php?tab=email');
    }

    // --- Save invoice settings ---
    if ($action === 'save_invoices') {
        $activeTab = 'invoices';
        $nextNum = inputInt('next_invoice_number');
        if ($nextNum < 0) $nextNum = 0;
        setSetting($pdo, 'next_invoice_number', $nextNum > 0 ? (string) $nextNum : '');
        setSetting($pdo, 'payment_terms', input('payment_terms'));
        $settings = getAllSettings($pdo);
        flashMessage('success', 'Invoice settings saved.');
        redirect($root . '/settings.php?tab=invoices');
    }
}

layoutHeader('Settings', 'settings');
?>

<?php renderFlash(); ?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error"><?= implode('<br>', array_map('e', $errors)) ?></div>
<?php endif; ?>

<!-- Tab nav -->
<div class="settings-tabs-wrap">
<div style="display:flex;gap:4px;margin-bottom:0;border-bottom:2px solid var(--color-border);padding-bottom:0;min-width:max-content;">
    <?php foreach (['business' => 'Business Details', 'banking' => 'Banking Details', 'invoices' => 'Invoices', 'email' => 'Email', 'account' => 'Account'] as $tab => $label): ?>
        <a href="?tab=<?= $tab ?>"
           style="padding:10px 20px;font-size:.875rem;font-weight:600;text-decoration:none;border-bottom:2px solid <?= $activeTab === $tab ? 'var(--color-accent)' : 'transparent' ?>;margin-bottom:-2px;color:<?= $activeTab === $tab ? 'var(--color-accent-dk)' : 'var(--color-muted)' ?>;">
            <?= $label ?>
        </a>
    <?php endforeach; ?>
</div>
</div>

<!-- ====== BUSINESS DETAILS ====== -->
<?php if ($activeTab === 'business'): ?>
<div class="card" style="max-width:600px;">
    <div class="card-header"><h2>Business Details</h2></div>
    <div class="card-body">
        <p style="color:var(--color-muted);font-size:.875rem;margin:0 0 20px;">
            These details appear in the header of every generated invoice.
        </p>
        <form method="POST" action="">
            <?php csrfField(); ?>
            <input type="hidden" name="action" value="save_business">
            <div class="form-grid single">
                <div class="form-group">
                    <label for="business_name">Business Name</label>
                    <input type="text" id="business_name" name="business_name"
                           value="<?= e($settings['business_name'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="owner_name">Owner / Your Name</label>
                    <input type="text" id="owner_name" name="owner_name"
                           value="<?= e($settings['owner_name'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="address_line1">Address Line 1</label>
                    <input type="text" id="address_line1" name="address_line1"
                           value="<?= e($settings['address_line1'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="address_line2">Address Line 2</label>
                    <input type="text" id="address_line2" name="address_line2"
                           value="<?= e($settings['address_line2'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="phone">Phone</label>
                    <input type="text" id="phone" name="phone"
                           value="<?= e($settings['phone'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email"
                           value="<?= e($settings['email'] ?? '') ?>">
                </div>
            </div>
            <div class="form-actions" style="margin-top:20px;">
                <button type="submit" class="btn btn-primary">Save Business Details</button>
            </div>
        </form>
    </div>
</div>

<!-- ====== BANKING DETAILS ====== -->
<?php elseif ($activeTab === 'banking'): ?>
<div class="card" style="max-width:600px;">
    <div class="card-header"><h2>Banking Details</h2></div>
    <div class="card-body">
        <p style="color:var(--color-muted);font-size:.875rem;margin:0 0 20px;">
            These appear in the payment section at the bottom of every invoice.
        </p>
        <form method="POST" action="">
            <?php csrfField(); ?>
            <input type="hidden" name="action" value="save_banking">
            <div class="form-grid">
                <div class="form-group">
                    <label for="bank_name">Bank Name</label>
                    <input type="text" id="bank_name" name="bank_name"
                           value="<?= e($settings['bank_name'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="bank_branch">Branch Name</label>
                    <input type="text" id="bank_branch" name="bank_branch"
                           value="<?= e($settings['bank_branch'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="bank_branch_code">Branch Code</label>
                    <input type="text" id="bank_branch_code" name="bank_branch_code"
                           value="<?= e($settings['bank_branch_code'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="account_type">Account Type</label>
                    <input type="text" id="account_type" name="account_type"
                           value="<?= e($settings['account_type'] ?? '') ?>">
                </div>
                <div class="form-group full">
                    <label for="account_holder">Account Holder Name</label>
                    <input type="text" id="account_holder" name="account_holder"
                           value="<?= e($settings['account_holder'] ?? '') ?>">
                </div>
                <div class="form-group full">
                    <label for="account_number">Account Number</label>
                    <input type="text" id="account_number" name="account_number"
                           value="<?= e($settings['account_number'] ?? '') ?>">
                </div>
            </div>
            <div class="form-actions" style="margin-top:20px;">
                <button type="submit" class="btn btn-primary">Save Banking Details</button>
            </div>
        </form>
    </div>
</div>

<!-- ====== INVOICES ====== -->
<?php elseif ($activeTab === 'invoices'): ?>
<div class="card" style="max-width:600px;">
    <div class="card-header"><h2>Invoice Settings</h2></div>
    <div class="card-body">
        <form method="POST" action="">
            <?php csrfField(); ?>
            <input type="hidden" name="action" value="save_invoices">
            <div class="form-grid single">
                <div class="form-group">
                    <label for="next_invoice_number">Next Invoice Number</label>
                    <input type="number" id="next_invoice_number" name="next_invoice_number"
                           value="<?= e($settings['next_invoice_number'] ?? '') ?>"
                           min="1" step="1" placeholder="Auto">
                    <span class="form-hint">Leave blank to auto-increment from the highest existing invoice. Set a number here to override — useful after importing historical invoices.</span>
                </div>
                <div class="form-group">
                    <label for="payment_terms">Payment Terms (printed on invoices)</label>
                    <textarea id="payment_terms" name="payment_terms" rows="3"
                              placeholder="e.g. Payment due within 7 days of invoice date."><?= e($settings['payment_terms'] ?? '') ?></textarea>
                    <span class="form-hint">Appears at the bottom of every generated PDF. Leave blank to omit.</span>
                </div>
            </div>
            <div class="form-actions" style="margin-top:20px;">
                <button type="submit" class="btn btn-primary">Save Invoice Settings</button>
            </div>
        </form>
    </div>
</div>

<!-- ====== EMAIL ====== -->
<?php elseif ($activeTab === 'email'): ?>
<div class="card" style="max-width:600px;">
    <div class="card-header"><h2><i class="fa-solid fa-envelope"></i> Email Settings</h2></div>
    <div class="card-body">
        <p style="color:var(--color-muted);font-size:.875rem;margin:0 0 20px;">
            Configure how invoice emails are sent to clients. Emails are sent from your hosting
            server using PHP mail. For replies to reach you reliably, set the Reply-To below.
        </p>
        <form method="POST" action="">
            <?php csrfField(); ?>
            <input type="hidden" name="action" value="save_email">
            <div class="form-grid single">
                <div class="form-group">
                    <label for="email_from_name">Sender Name <span class="field-tag field-tag-visible">shown to client</span></label>
                    <input type="text" id="email_from_name" name="email_from_name"
                           value="<?= e($settings['email_from_name'] ?? "Luke's Music Lessons") ?>"
                           placeholder="Luke's Music Lessons">
                    <span class="form-hint">The name clients see in their inbox — e.g. "Luke's Music Lessons".</span>
                </div>
                <div class="form-group">
                    <label for="email_from">From Email Address <span class="field-tag field-tag-visible">shown to client</span></label>
                    <input type="email" id="email_from" name="email_from"
                           value="<?= e($settings['email_from'] ?? 'invoices@luke-higgins.co.za') ?>"
                           placeholder="invoices@luke-higgins.co.za">
                    <span class="form-hint">Must be an email address on your hosting domain (luke-higgins.co.za) — create it in DirectAdmin → Email Accounts first. Recommended: <strong>invoices@luke-higgins.co.za</strong>.</span>
                </div>
                <div class="form-group">
                    <label for="email_reply_to">Reply-To Address <span class="field-tag field-tag-private">your inbox</span></label>
                    <input type="email" id="email_reply_to" name="email_reply_to"
                           value="<?= e($settings['email_reply_to'] ?? '') ?>"
                           placeholder="lukesterhi@gmail.com">
                    <span class="form-hint">
                        When a client hits Reply, their email goes here — your personal inbox.
                        Set this to <strong>lukesterhi@gmail.com</strong> so you never miss a reply.
                    </span>
                </div>
            </div>
            <div class="form-actions" style="margin-top:20px;">
                <button type="submit" class="btn btn-primary">Save Email Settings</button>
            </div>
        </form>
    </div>
</div>

<!-- ====== ACCOUNT ====== -->
<?php elseif ($activeTab === 'account'): ?>
<div class="card" style="max-width:480px;">
    <div class="card-header"><h2>Change Password</h2></div>
    <div class="card-body">
        <form method="POST" action="?tab=account">
            <?php csrfField(); ?>
            <input type="hidden" name="action" value="change_password">
            <div class="form-grid single">
                <div class="form-group">
                    <label for="current_password">Current Password</label>
                    <input type="password" id="current_password" name="current_password" required autocomplete="current-password">
                </div>
                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password" required autocomplete="new-password" minlength="8">
                    <span class="form-hint">Minimum 8 characters.</span>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required autocomplete="new-password">
                </div>
            </div>
            <div class="form-actions" style="margin-top:20px;">
                <button type="submit" class="btn btn-primary">Change Password</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php layoutFooter(); ?>
