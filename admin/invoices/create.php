<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';

requireAuth();

$pdo     = getDb();
$root    = getAdminRoot();
$errors  = [];

ensureClientNotesColumn($pdo);
ensureSchedulingColumns($pdo);

ensureClientArchivedColumn($pdo);
$clients = $pdo->query("SELECT id, name FROM lms_clients WHERE archived_at IS NULL ORDER BY name ASC")->fetchAll();
$presets = $pdo->query("SELECT * FROM lms_presets WHERE is_active = 1 ORDER BY sort_order ASC, id ASC")->fetchAll();

$nextNumber    = getNextInvoiceNumber($pdo);
$prefillClient = inputInt('client_id', 'get');

$values = [
    'invoice_number' => (string) $nextNumber,
    'client_id'      => $prefillClient ?: '',
    'invoice_date'   => date('Y-m-d'),
    'discount'       => '0',
    'status'         => 'draft',
    'notes'               => '',
    'client_notes'        => '',
    'scheduled_date'      => '',
    'recurrence'          => 'none',
    'recurrence_end_date' => '',
];

// Line items from POST or default one empty row
$lineItems = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $values['invoice_number'] = input('invoice_number');
    $values['client_id']      = input('client_id');
    $values['invoice_date']   = input('invoice_date');
    $values['discount']       = input('discount');
    $values['status']         = input('status');
    $values['notes']          = input('notes');
    $values['client_notes']   = input('client_notes');
    $values['scheduled_date']      = input('scheduled_date');
    $values['recurrence']          = input('recurrence') ?: 'none';
    $values['recurrence_end_date'] = input('recurrence_end_date');

    // Rebuild line items from POST arrays
    $descriptions = $_POST['item_description'] ?? [];
    $quantities   = $_POST['item_quantity']    ?? [];
    $rates        = $_POST['item_rate']        ?? [];

    foreach ($descriptions as $i => $desc) {
        $desc = trim($desc);
        if ($desc === '') continue;
        $qty = (float) ($quantities[$i] ?? 1);
        $rate = (float) ($rates[$i] ?? 0);
        $lineItems[] = [
            'description' => $desc,
            'quantity'    => $qty,
            'rate'        => $rate,
            'amount'      => round($qty * $rate, 2),
        ];
    }

    // Validation
    if ($values['invoice_number'] === '' || !ctype_digit($values['invoice_number'])) {
        $errors[] = 'Invoice number must be a positive whole number.';
    } else {
        $existing = $pdo->prepare("SELECT id FROM lms_invoices WHERE invoice_number = ?");
        $existing->execute([(int) $values['invoice_number']]);
        if ($existing->fetch()) {
            $errors[] = 'Invoice #' . $values['invoice_number'] . ' already exists. Choose a different number.';
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
            $stmt = $pdo->prepare("
                INSERT INTO lms_invoices
                    (invoice_number, client_id, invoice_date, discount, subtotal, total,
                     status, notes, client_notes, scheduled_date, recurrence, recurrence_end_date)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $scheduledDate     = $values['scheduled_date']      !== '' ? $values['scheduled_date']      : null;
            $recurrenceEndDate = $values['recurrence_end_date']  !== '' ? $values['recurrence_end_date'] : null;
            $stmt->execute([
                (int)   $values['invoice_number'],
                (int)   $values['client_id'],
                        $values['invoice_date'],
                        $discount,
                        $subtotal,
                        $total,
                        $values['status'],
                        $values['notes'] ?: null,
                        $values['client_notes'] ?: null,
                        $scheduledDate,
                        $values['recurrence'] ?: 'none',
                        $recurrenceEndDate,
            ]);

            $invoiceId = (int) $pdo->lastInsertId();

            $itemStmt = $pdo->prepare("
                INSERT INTO lms_invoice_items (invoice_id, description, quantity, rate, amount, sort_order)
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            foreach ($lineItems as $order => $item) {
                $itemStmt->execute([
                    $invoiceId,
                    $item['description'],
                    $item['quantity'],
                    $item['rate'],
                    $item['amount'],
                    $order,
                ]);
            }

            $pdo->commit();

            // Always advance next_invoice_number so the create form pre-fills correctly next time.
            setSetting($pdo, 'next_invoice_number', (string) ((int) $values['invoice_number'] + 1));

            flashMessage('success', 'Invoice #' . $values['invoice_number'] . ' created successfully.');
            redirect($root . '/invoices/view.php?id=' . $invoiceId);
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Failed to save invoice. Please try again.';
        }
    }
} else {
    // Default single empty line item
    $lineItems = [['description' => '', 'quantity' => 1, 'rate' => '', 'amount' => 0]];
}

layoutHeader('New Invoice', 'invoices');
?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error"><?= implode('<br>', array_map('e', $errors)) ?></div>
<?php endif; ?>

<form method="POST" action="" id="invoice-form">
    <?php csrfField(); ?>

    <div class="invoice-meta-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;">

        <!-- Left: Invoice meta -->
        <div class="card">
            <div class="card-header"><h2>Invoice Details</h2></div>
            <div class="card-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="invoice_number">Invoice Number *</label>
                        <input type="number" id="invoice_number" name="invoice_number"
                               value="<?= e($values['invoice_number']) ?>" min="1" step="1" required>
                        <span class="form-hint">Auto-set to next available. Override for historical entries.</span>
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
                        <label for="scheduled_date">
                            Schedule to Send
                            <span class="field-tag" style="background:#fef3c7;color:#92400e;">
                                <i class="fa-solid fa-calendar-clock"></i> auto-emails on this date
                            </span>
                        </label>
                        <input type="date" id="scheduled_date" name="scheduled_date"
                               value="<?= e($values['scheduled_date']) ?>">
                        <span class="form-hint">Leave blank to send manually. Set a date to auto-email at 8 AM on that day.</span>
                        <span id="past-date-warn" class="form-hint" style="display:none;color:#92400e;">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                            This date is in the past — the invoice will be sent on the next cron run.
                        </span>
                    </div>

                    <div class="form-group full" id="recurrence-group" style="<?= $values['scheduled_date'] === '' ? 'display:none;' : '' ?>">
                        <label for="recurrence">
                            Repeat
                            <span class="field-tag" style="background:#fef3c7;color:#92400e;">
                                <i class="fa-solid fa-rotate"></i> creates next invoice automatically
                            </span>
                        </label>
                        <select id="recurrence" name="recurrence">
                            <option value="none"        <?= $values['recurrence'] === 'none'        ? 'selected' : '' ?>>No repeat — send once</option>
                            <option value="weekly"      <?= $values['recurrence'] === 'weekly'      ? 'selected' : '' ?>>Weekly</option>
                            <option value="fortnightly" <?= $values['recurrence'] === 'fortnightly' ? 'selected' : '' ?>>Fortnightly (every 2 weeks)</option>
                            <option value="monthly"     <?= $values['recurrence'] === 'monthly'     ? 'selected' : '' ?>>Monthly</option>
                        </select>
                    </div>

                    <div class="form-group full" id="recurrence-end-group" style="<?= ($values['recurrence'] === 'none' || $values['recurrence'] === '') ? 'display:none;' : '' ?>">
                        <label for="recurrence_end_date">Stop Repeating After</label>
                        <input type="date" id="recurrence_end_date" name="recurrence_end_date"
                               value="<?= e($values['recurrence_end_date']) ?>">
                        <span class="form-hint">Optional. Leave blank to repeat indefinitely.</span>
                    </div>

                    <div class="form-group full">
                        <label for="notes">
                            Private Notes
                            <span class="field-tag field-tag-private"><i class="fa-solid fa-lock"></i> Admin only · never printed</span>
                        </label>
                        <textarea id="notes" name="notes" rows="2"><?= e($values['notes']) ?></textarea>
                    </div>

                    <div class="form-group full">
                        <label for="client_notes">
                            Invoice Notes
                            <span class="field-tag field-tag-visible"><i class="fa-solid fa-eye"></i> Printed on PDF · client sees this</span>
                        </label>
                        <textarea id="client_notes" name="client_notes" rows="2"><?= e($values['client_notes']) ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right: Client -->
        <div class="card">
            <div class="card-header">
                <h2>Client</h2>
                <button type="button" class="btn btn-secondary btn-sm" id="open-quick-client-btn">
                    <i class="fa-solid fa-user-plus"></i> New Client
                </button>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label for="client_id">Select Client *</label>
                    <select id="client_id" name="client_id" required>
                        <option value="">— Choose a client —</option>
                        <?php foreach ($clients as $client): ?>
                            <option value="<?= $client['id'] ?>"
                                <?= (string)$client['id'] === (string)$values['client_id'] ? 'selected' : '' ?>>
                                <?= e($client['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if (empty($clients)): ?>
                    <div class="alert alert-warn" style="margin-top:12px;">
                        No clients yet — use the <strong>New Client</strong> button above.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Line items -->
    <div class="card" style="margin-bottom:20px;">
        <div class="card-header">
            <h2>Line Items</h2>
        </div>
        <div class="card-body">
            <?php if (!empty($presets)): ?>
            <div class="preset-chips" id="preset-chips">
                <?php foreach ($presets as $preset): ?>
                    <button type="button" class="preset-chip"
                            data-desc="<?= e($preset['description']) ?>"
                            data-rate="<?= e((string)$preset['default_rate']) ?>">
                        <?= e($preset['description']) ?>
                        <span class="chip-rate"><?= formatRand((float)$preset['default_rate']) ?></span>
                    </button>
                <?php endforeach; ?>
                <button type="button" class="preset-chip preset-chip-add" id="add-blank-btn">+ Blank row</button>
            </div>
            <?php else: ?>
            <div style="margin-bottom:12px;">
                <button type="button" class="btn btn-secondary btn-sm" id="add-blank-btn">+ Add Row</button>
            </div>
            <?php endif; ?>
            <div class="table-wrap">
            <table class="line-items-table" id="line-items-table">
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
                    <?php foreach ($lineItems as $i => $item): ?>
                        <tr class="line-item-row">
                            <td class="col-desc">
                                <input type="text" name="item_description[]"
                                       value="<?= e($item['description']) ?>"
                                       placeholder="Description" required>
                            </td>
                            <td class="col-qty">
                                <input type="number" name="item_quantity[]"
                                       value="<?= e((string)$item['quantity']) ?>"
                                       min="0" step="0.5" class="qty-input" required>
                            </td>
                            <td class="col-rate">
                                <input type="number" name="item_rate[]"
                                       value="<?= $item['rate'] !== '' ? e((string)$item['rate']) : '' ?>"
                                       min="0" step="0.01" class="rate-input" required>
                            </td>
                            <td class="col-amt">
                                <div class="amount-display" id="amount-<?= $i ?>">
                                    <?= $item['amount'] > 0 ? formatRand($item['amount']) : 'R 0.00' ?>
                                </div>
                            </td>
                            <td class="col-del">
                                <button type="button" class="btn btn-danger btn-icon btn-sm remove-row-btn" title="Remove row">&times;</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div><!-- /.table-wrap -->

            <div class="invoice-totals" style="margin-top:16px;">
                <div class="total-row">
                    <span class="total-label">Subtotal</span>
                    <span class="total-value" id="subtotal-display">R 0.00</span>
                </div>
                <div class="total-row">
                    <label class="total-label" for="discount">Discount (R)</label>
                    <input type="number" id="discount" name="discount"
                           value="<?= e($values['discount']) ?>"
                           min="0" step="0.01"
                           style="width:110px;text-align:right;">
                </div>
                <div class="total-row grand-total">
                    <span class="total-label">Total</span>
                    <span class="total-value" id="total-display">R 0.00</span>
                </div>
            </div>
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Save Invoice</button>
        <a href="<?= $root ?>/invoices/index.php" class="btn btn-secondary">Cancel</a>
    </div>
</form>

<!-- Quick-add client modal — must be in DOM before the script block below -->
<div id="quick-client-modal" class="qc-modal-overlay" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="qc-modal-title">
    <div class="qc-modal">
        <div class="qc-modal-header">
            <h3 id="qc-modal-title"><i class="fa-solid fa-user-plus"></i> New Client</h3>
            <button type="button" id="qc-close-btn" class="qc-close" aria-label="Close">&times;</button>
        </div>
        <form id="quick-client-form">
            <?php csrfField(); ?>
            <div class="qc-modal-body">
                <div id="qc-error" class="alert alert-error" style="display:none;margin-bottom:12px;"></div>
                <div class="form-group">
                    <label for="qc-name">Name <span style="color:var(--color-danger)">*</span></label>
                    <input type="text" id="qc-name" name="name" required autocomplete="off" placeholder="e.g. Sipho Dlamini">
                </div>
                <div class="form-group">
                    <label for="qc-email">Email</label>
                    <input type="email" id="qc-email" name="email" autocomplete="off" placeholder="student@example.com">
                    <span class="form-hint">Needed to email invoices.</span>
                </div>
                <div class="form-group">
                    <label for="qc-phone">Phone</label>
                    <input type="text" id="qc-phone" name="phone" autocomplete="off" placeholder="+27 82 000 0000">
                </div>
                <p class="form-hint" style="margin-top:4px;">You can add address and notes on the client's full profile later.</p>
            </div>
            <div class="qc-modal-footer">
                <button type="button" id="qc-cancel-btn" class="btn btn-secondary">Cancel</button>
                <button type="submit" id="qc-save-btn" class="btn btn-primary">Save Client</button>
            </div>
        </form>
    </div>
</div>

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
        document.querySelectorAll('.line-item-row').forEach(function (row) {
            subtotal += recalcRow(row);
        });
        const discount = parseFloat(document.getElementById('discount').value) || 0;
        const total    = Math.max(0, subtotal - discount);
        document.getElementById('subtotal-display').textContent = formatR(subtotal);
        document.getElementById('total-display').textContent    = formatR(total);
    }

    function makeRow(description, rate) {
        const tr = document.createElement('tr');
        tr.className = 'line-item-row';
        tr.innerHTML = `
            <td class="col-desc"><input type="text" name="item_description[]" value="${description}" placeholder="Description" required></td>
            <td class="col-qty"><input type="number" name="item_quantity[]" value="1" min="0" step="0.5" class="qty-input" required></td>
            <td class="col-rate"><input type="number" name="item_rate[]" value="${rate}" min="0" step="0.01" class="rate-input" required></td>
            <td class="col-amt"><div class="amount-display">R 0.00</div></td>
            <td class="col-del"><button type="button" class="btn btn-danger btn-icon btn-sm remove-row-btn">&times;</button></td>
        `;
        return tr;
    }

    const body = document.getElementById('line-items-body');

    body.addEventListener('input', recalcTotals);

    body.addEventListener('click', function (e) {
        if (e.target.classList.contains('remove-row-btn')) {
            if (body.querySelectorAll('.line-item-row').length > 1) {
                e.target.closest('tr').remove();
                recalcTotals();
            }
        }
    });

    document.getElementById('add-blank-btn').addEventListener('click', function () {
        body.appendChild(makeRow('', ''));
        recalcTotals();
        body.lastElementChild.querySelector('input').focus();
    });

    // Preset chip clicks
    document.querySelectorAll('.preset-chip[data-desc]').forEach(function (chip) {
        chip.addEventListener('click', function () {
            body.appendChild(makeRow(chip.dataset.desc, chip.dataset.rate || ''));
            recalcTotals();
        });
    });

    document.getElementById('discount').addEventListener('input', recalcTotals);

    recalcTotals();
}());

// Show/hide recurrence options based on whether a scheduled date is set
(function () {
    const dateInput      = document.getElementById('scheduled_date');
    const recurGroup     = document.getElementById('recurrence-group');
    const recurEndGroup  = document.getElementById('recurrence-end-group');
    const recurSelect    = document.getElementById('recurrence');

    function toggleRecurrence() {
        const hasDate = dateInput && dateInput.value !== '';
        if (recurGroup) recurGroup.style.display = hasDate ? '' : 'none';
        if (!hasDate && recurSelect) recurSelect.value = 'none';
        toggleEndDate();
    }

    function toggleEndDate() {
        const isRecurring = recurSelect && recurSelect.value !== 'none';
        if (recurEndGroup) recurEndGroup.style.display = isRecurring ? '' : 'none';
    }

    function checkPastDate() {
        const today = new Date().toISOString().split('T')[0];
        const warn  = document.getElementById('past-date-warn');
        if (warn) warn.style.display = dateInput && dateInput.value && dateInput.value < today ? '' : 'none';
    }
    if (dateInput) {
        dateInput.addEventListener('change', function () { toggleRecurrence(); checkPastDate(); });
        checkPastDate();
    }
    if (recurSelect) recurSelect.addEventListener('change', toggleEndDate);
}());

// Quick-add client modal
(function () {
    const openBtn   = document.getElementById('open-quick-client-btn');
    const modal     = document.getElementById('quick-client-modal');
    const closeBtn  = document.getElementById('qc-close-btn');
    const cancelBtn = document.getElementById('qc-cancel-btn');
    const form      = document.getElementById('quick-client-form');
    const errBox    = document.getElementById('qc-error');
    const saveBtn   = document.getElementById('qc-save-btn');

    function openModal() {
        form.reset();
        errBox.textContent = '';
        errBox.style.display = 'none';
        modal.style.display = 'flex';
        document.getElementById('qc-name').focus();
    }
    function closeModal() { modal.style.display = 'none'; }

    if (openBtn)  openBtn.addEventListener('click', openModal);
    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
    modal.addEventListener('click', function (e) { if (e.target === modal) closeModal(); });

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        errBox.style.display = 'none';
        saveBtn.disabled = true;
        saveBtn.textContent = 'Saving…';

        const data = new FormData(form);

        fetch('<?= $root ?>/clients/create-quick.php', {
            method: 'POST',
            body: data,
        })
        .then(function (res) { return res.json(); })
        .then(function (json) {
            if (!json.success) {
                errBox.textContent = json.error || 'An error occurred.';
                errBox.style.display = 'block';
                return;
            }
            // Add new client to the dropdown and select it.
            const select = document.getElementById('client_id');
            const opt    = document.createElement('option');
            opt.value    = json.client.id;
            opt.textContent = json.client.name;
            opt.selected = true;
            select.appendChild(opt);
            closeModal();
        })
        .catch(function () {
            errBox.textContent = 'Network error — please try again.';
            errBox.style.display = 'block';
        })
        .finally(function () {
            saveBtn.disabled = false;
            saveBtn.textContent = 'Save Client';
        });
    });
}());
</script>

<?php layoutFooter(); ?>
