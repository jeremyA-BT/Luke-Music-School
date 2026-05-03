<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';

requireAuth();

$pdo     = getDb();
$root    = getAdminRoot();
$id      = inputInt('id', 'get');
$errors  = [];

$stmt = $pdo->prepare("SELECT * FROM lms_invoices WHERE id = ?");
$stmt->execute([$id]);
$invoice = $stmt->fetch();

if (!$invoice) {
    flashMessage('error', 'Invoice not found.');
    redirect($root . '/invoices/index.php');
}

$clients = $pdo->query("SELECT id, name FROM lms_clients ORDER BY name ASC")->fetchAll();
$presets = $pdo->query("SELECT * FROM lms_presets WHERE is_active = 1 ORDER BY sort_order ASC, id ASC")->fetchAll();

$itemStmt = $pdo->prepare("SELECT * FROM lms_invoice_items WHERE invoice_id = ? ORDER BY sort_order ASC, id ASC");
$itemStmt->execute([$id]);
$existingItems = $itemStmt->fetchAll();

$values = [
    'invoice_number' => (string) $invoice['invoice_number'],
    'client_id'      => (string) $invoice['client_id'],
    'invoice_date'   => $invoice['invoice_date'],
    'discount'       => (string) $invoice['discount'],
    'status'         => $invoice['status'],
    'notes'          => $invoice['notes'] ?? '',
];

$lineItems = $existingItems;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $values['invoice_number'] = input('invoice_number');
    $values['client_id']      = input('client_id');
    $values['invoice_date']   = input('invoice_date');
    $values['discount']       = input('discount');
    $values['status']         = input('status');
    $values['notes']          = input('notes');

    $descriptions = $_POST['item_description'] ?? [];
    $quantities   = $_POST['item_quantity']    ?? [];
    $rates        = $_POST['item_rate']        ?? [];

    $lineItems = [];
    foreach ($descriptions as $i => $desc) {
        $desc = trim($desc);
        if ($desc === '') continue;
        $qty  = (float) ($quantities[$i] ?? 1);
        $rate = (float) ($rates[$i]      ?? 0);
        $lineItems[] = [
            'description' => $desc,
            'quantity'    => $qty,
            'rate'        => $rate,
            'amount'      => round($qty * $rate, 2),
        ];
    }

    if ($values['invoice_number'] === '' || !ctype_digit($values['invoice_number'])) {
        $errors[] = 'Invoice number must be a positive whole number.';
    } else {
        $existing = $pdo->prepare("SELECT id FROM lms_invoices WHERE invoice_number = ? AND id != ?");
        $existing->execute([(int) $values['invoice_number'], $id]);
        if ($existing->fetch()) {
            $errors[] = 'Invoice #' . $values['invoice_number'] . ' already exists.';
        }
    }

    if ($values['client_id'] === '') {
        $errors[] = 'Please select a client.';
    }

    if ($values['invoice_date'] === '') {
        $errors[] = 'Invoice date is required.';
    }

    if (empty($lineItems)) {
        $errors[] = 'At least one line item is required.';
    }

    if (empty($errors)) {
        $subtotal = array_sum(array_column($lineItems, 'amount'));
        $discount = (float) $values['discount'];
        $total    = max(0, $subtotal - $discount);

        $pdo->beginTransaction();
        try {
            $pdo->prepare("
                UPDATE lms_invoices
                SET invoice_number=?, client_id=?, invoice_date=?, discount=?, subtotal=?, total=?, status=?, notes=?
                WHERE id=?
            ")->execute([
                (int)   $values['invoice_number'],
                (int)   $values['client_id'],
                        $values['invoice_date'],
                        $discount,
                        $subtotal,
                        $total,
                        $values['status'],
                        $values['notes'] ?: null,
                        $id,
            ]);

            $pdo->prepare("DELETE FROM lms_invoice_items WHERE invoice_id = ?")->execute([$id]);

            $itemStmt2 = $pdo->prepare("
                INSERT INTO lms_invoice_items (invoice_id, description, quantity, rate, amount, sort_order)
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            foreach ($lineItems as $order => $item) {
                $itemStmt2->execute([$id, $item['description'], $item['quantity'], $item['rate'], $item['amount'], $order]);
            }

            $pdo->commit();
            flashMessage('success', 'Invoice #' . $values['invoice_number'] . ' updated.');
            redirect($root . '/invoices/view.php?id=' . $id);
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Failed to save changes. Please try again.';
        }
    }
}

layoutHeader('Edit Invoice #' . $invoice['invoice_number'], 'invoices');
?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error"><?= implode('<br>', array_map('e', $errors)) ?></div>
<?php endif; ?>

<form method="POST" action="" id="invoice-form">
    <?php csrfField(); ?>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;">
        <div class="card">
            <div class="card-header"><h2>Invoice Details</h2></div>
            <div class="card-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="invoice_number">Invoice Number *</label>
                        <input type="number" id="invoice_number" name="invoice_number"
                               value="<?= e($values['invoice_number']) ?>" min="1" step="1" required>
                    </div>
                    <div class="form-group">
                        <label for="invoice_date">Date *</label>
                        <input type="date" id="invoice_date" name="invoice_date"
                               value="<?= e($values['invoice_date']) ?>" required>
                    </div>
                    <div class="form-group full">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="draft" <?= $values['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
                            <option value="sent"  <?= $values['status'] === 'sent'  ? 'selected' : '' ?>>Sent</option>
                            <option value="paid"  <?= $values['status'] === 'paid'  ? 'selected' : '' ?>>Paid</option>
                        </select>
                    </div>
                    <div class="form-group full">
                        <label for="notes">Notes (internal)</label>
                        <textarea id="notes" name="notes" rows="2"><?= e($values['notes']) ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2>Client</h2></div>
            <div class="card-body">
                <div class="form-group">
                    <label for="client_id">Select Client *</label>
                    <select id="client_id" name="client_id" required>
                        <option value="">— Choose a client —</option>
                        <?php foreach ($clients as $client): ?>
                            <option value="<?= $client['id'] ?>"
                                <?= (string)$client['id'] === $values['client_id'] ? 'selected' : '' ?>>
                                <?= e($client['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="card" style="margin-bottom:20px;">
        <div class="card-header">
            <h2>Line Items</h2>
            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                <?php if (!empty($presets)): ?>
                    <select id="preset-picker" style="width:auto;">
                        <option value="">Add from preset…</option>
                        <?php foreach ($presets as $preset): ?>
                            <option value="<?= e($preset['description']) ?>" data-rate="<?= e((string)$preset['default_rate']) ?>">
                                <?= e($preset['description']) ?> — <?= formatRand((float)$preset['default_rate']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" class="btn btn-secondary btn-sm" id="add-preset-btn">Add</button>
                <?php endif; ?>
                <button type="button" class="btn btn-secondary btn-sm" id="add-blank-btn">+ Blank Row</button>
            </div>
        </div>
        <div class="card-body">
            <table class="line-items-table">
                <thead>
                    <tr>
                        <th class="col-desc">Description</th>
                        <th class="col-qty">Qty / Lessons</th>
                        <th class="col-rate">Rate (R)</th>
                        <th class="col-amt">Amount</th>
                        <th class="col-del"></th>
                    </tr>
                </thead>
                <tbody id="line-items-body">
                    <?php foreach ($lineItems as $item): ?>
                        <tr class="line-item-row">
                            <td class="col-desc">
                                <input type="text" name="item_description[]" value="<?= e($item['description']) ?>" required>
                            </td>
                            <td class="col-qty">
                                <input type="number" name="item_quantity[]" value="<?= e((string)$item['quantity']) ?>"
                                       min="0" step="0.5" class="qty-input" required>
                            </td>
                            <td class="col-rate">
                                <input type="number" name="item_rate[]" value="<?= e((string)$item['rate']) ?>"
                                       min="0" step="0.01" class="rate-input" required>
                            </td>
                            <td class="col-amt">
                                <div class="amount-display"><?= formatRand((float)$item['amount']) ?></div>
                            </td>
                            <td class="col-del">
                                <button type="button" class="btn btn-danger btn-icon btn-sm remove-row-btn">&times;</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="invoice-totals" style="margin-top:16px;">
                <div class="total-row">
                    <span class="total-label">Subtotal</span>
                    <span class="total-value" id="subtotal-display">R 0.00</span>
                </div>
                <div class="total-row">
                    <label class="total-label" for="discount">Discount (R)</label>
                    <input type="number" id="discount" name="discount" value="<?= e($values['discount']) ?>"
                           min="0" step="0.01" style="width:110px;text-align:right;">
                </div>
                <div class="total-row grand-total">
                    <span class="total-label">Total</span>
                    <span class="total-value" id="total-display">R 0.00</span>
                </div>
            </div>
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Save Changes</button>
        <a href="<?= $root ?>/invoices/view.php?id=<?= $id ?>" class="btn btn-secondary">Cancel</a>
    </div>
</form>

<script>
(function () {
    function formatR(n) {
        return 'R ' + parseFloat(n || 0).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    }
    function recalcRow(row) {
        const qty  = parseFloat(row.querySelector('.qty-input').value)  || 0;
        const rate = parseFloat(row.querySelector('.rate-input').value) || 0;
        const amt  = qty * rate;
        row.querySelector('.amount-display').textContent = formatR(amt);
        return amt;
    }
    function recalcTotals() {
        let subtotal = 0;
        document.querySelectorAll('.line-item-row').forEach(function(row) { subtotal += recalcRow(row); });
        const discount = parseFloat(document.getElementById('discount').value) || 0;
        const total    = Math.max(0, subtotal - discount);
        document.getElementById('subtotal-display').textContent = formatR(subtotal);
        document.getElementById('total-display').textContent    = formatR(total);
    }
    function makeRow(description, rate) {
        const tr = document.createElement('tr');
        tr.className = 'line-item-row';
        tr.innerHTML = `<td class="col-desc"><input type="text" name="item_description[]" value="${description}" placeholder="Description" required></td><td class="col-qty"><input type="number" name="item_quantity[]" value="1" min="0" step="0.5" class="qty-input" required></td><td class="col-rate"><input type="number" name="item_rate[]" value="${rate}" min="0" step="0.01" class="rate-input" required></td><td class="col-amt"><div class="amount-display">R 0.00</div></td><td class="col-del"><button type="button" class="btn btn-danger btn-icon btn-sm remove-row-btn">&times;</button></td>`;
        return tr;
    }
    const body = document.getElementById('line-items-body');
    body.addEventListener('input', recalcTotals);
    body.addEventListener('click', function(e) {
        if (e.target.classList.contains('remove-row-btn')) {
            if (body.querySelectorAll('.line-item-row').length > 1) { e.target.closest('tr').remove(); recalcTotals(); }
        }
    });
    document.getElementById('add-blank-btn').addEventListener('click', function() { body.appendChild(makeRow('', '')); recalcTotals(); });
    const presetBtn = document.getElementById('add-preset-btn');
    if (presetBtn) {
        presetBtn.addEventListener('click', function() {
            const picker = document.getElementById('preset-picker');
            const opt = picker.options[picker.selectedIndex];
            if (!opt.value) return;
            body.appendChild(makeRow(opt.value, opt.dataset.rate || ''));
            picker.selectedIndex = 0;
            recalcTotals();
        });
    }
    document.getElementById('discount').addEventListener('input', recalcTotals);
    recalcTotals();
}());
</script>

<?php layoutFooter(); ?>
