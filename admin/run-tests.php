<?php
/**
 * System health check and email tests.
 * Protected — requires admin login.
 * DELETE or restrict this file after initial testing.
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/invoice-pdf-lib.php';
require_once __DIR__ . '/includes/layout.php';

requireAuth();

$pdo  = getDb();
$root = getAdminRoot();

// Email address that receives all test emails (not Luke's real inbox)
const TEST_RECIPIENT = 'jeremy@adamstribe.org';

$results = [];

// -------------------------------------------------------
// Helper
// -------------------------------------------------------
function testPass(string $name, string $detail = ''): array {
    return ['name' => $name, 'pass' => true, 'detail' => $detail];
}
function testFail(string $name, string $detail = ''): array {
    return ['name' => $name, 'pass' => false, 'detail' => $detail];
}

$runAll = isset($_GET['run']);

if ($runAll) {

    // ---- TEST 1: Database connection ----
    try {
        $count = (int) $pdo->query("SELECT COUNT(*) FROM lms_invoices")->fetchColumn();
        $results[] = testPass('Database connection', $count . ' invoice(s) in database');
    } catch (Exception $e) {
        $results[] = testFail('Database connection', $e->getMessage());
    }

    // ---- TEST 2: Settings load ----
    try {
        $settings = getAllSettings($pdo);
        $fromEmail = $settings['email_from'] ?? '(not set)';
        $replyTo   = $settings['email_reply_to'] ?? '(not set)';
        $results[] = testPass(
            'Settings loaded',
            'From: ' . $fromEmail . ' · Reply-To: ' . $replyTo
        );
    } catch (Exception $e) {
        $results[] = testFail('Settings loaded', $e->getMessage());
        $settings  = [];
    }

    // ---- TEST 3: PDF generation ----
    try {
        $dummyInvoice = [
            'id'             => 0,
            'invoice_number' => 9999,
            'invoice_date'   => date('Y-m-d'),
            'client_name'    => 'Test Client',
            'client_address' => '1 Test Street, Sandton',
            'client_notes'   => 'Test notes',
            'discount'       => '0.00',
            'subtotal'       => '350.00',
            'total'          => '350.00',
            'status'         => 'draft',
        ];
        $dummyItems = [[
            'description' => '60 Minute Music Lesson',
            'quantity'    => '1',
            'rate'        => '350.00',
            'amount'      => '350.00',
        ]];
        $pdf = generateInvoicePdf($dummyInvoice, $dummyItems, $settings, 'S');
        if (strlen($pdf) > 1000 && str_starts_with($pdf, '%PDF')) {
            $results[] = testPass('PDF generation', number_format(strlen($pdf)) . ' bytes — valid PDF header');
        } else {
            $results[] = testFail('PDF generation', 'Output was ' . strlen($pdf) . ' bytes and did not start with %PDF');
        }
    } catch (Exception $e) {
        $results[] = testFail('PDF generation', $e->getMessage());
        $pdf = '';
    }

    // ---- TEST 4: Plain email (contact form simulation) ----
    try {
        $subject = '[LMS TEST] Contact form email — ' . date('H:i:s');
        $html = '<p style="font-family:sans-serif;">This is a test of the contact form email delivery from <strong>luke-higgins.co.za</strong>.</p>'
              . '<p style="font-family:sans-serif;">Sent at: ' . date('Y-m-d H:i:s') . '</p>'
              . '<p style="font-family:sans-serif;">From address: <code>hello@luke-higgins.co.za</code></p>';

        $headers  = 'From: Luke Higgins Music <hello@luke-higgins.co.za>' . "\r\n";
        $headers .= 'Reply-To: lukesterhi@gmail.com' . "\r\n";
        $headers .= 'MIME-Version: 1.0' . "\r\n";
        $headers .= 'Content-Type: text/html; charset=UTF-8' . "\r\n";

        $sent = mail(TEST_RECIPIENT, $subject, $html, $headers);
        if ($sent) {
            $results[] = testPass(
                'Contact form email (plain HTML)',
                'mail() returned true — check ' . TEST_RECIPIENT . ' for the message'
            );
        } else {
            $results[] = testFail(
                'Contact form email (plain HTML)',
                'mail() returned false — check server mail config and that hello@luke-higgins.co.za exists'
            );
        }
    } catch (Exception $e) {
        $results[] = testFail('Contact form email', $e->getMessage());
    }

    // ---- TEST 5: Invoice email with PDF attachment ----
    try {
        if (strlen($pdf ?? '') < 100) {
            $results[] = testFail('Invoice email with PDF', 'Skipped — PDF generation failed in Test 3');
        } else {
            // Use a real invoice if one exists, otherwise use the dummy
            $realInvoice = $pdo->query("
                SELECT i.*, c.name AS client_name, c.address AS client_address
                FROM lms_invoices i
                LEFT JOIN lms_clients c ON c.id = i.client_id
                ORDER BY i.id DESC LIMIT 1
            ")->fetch();

            if ($realInvoice) {
                $itemStmt = $pdo->prepare("SELECT * FROM lms_invoice_items WHERE invoice_id = ? ORDER BY sort_order ASC, id ASC");
                $itemStmt->execute([$realInvoice['id']]);
                $realItems  = $itemStmt->fetchAll();
                $testInvoice = $realInvoice;
                $testItems   = $realItems;
                $source      = 'real invoice #' . $realInvoice['invoice_number'];
            } else {
                $testInvoice = $dummyInvoice;
                $testItems   = $dummyItems;
                $source      = 'dummy invoice (no real invoices in DB yet)';
            }

            $testPdf = generateInvoicePdf($testInvoice, $testItems, $settings, 'S');

            // Override reply-to for the test so it goes to the test address
            $testSettings             = $settings;
            $testSettings['email_reply_to'] = TEST_RECIPIENT;

            $sent = sendInvoiceEmail(
                TEST_RECIPIENT,
                $testInvoice['client_name'],
                $testInvoice,
                $testPdf,
                $testSettings,
                'This is an automated test email from the LMS run-tests script. Please ignore.'
            );

            if ($sent) {
                $results[] = testPass(
                    'Invoice email with PDF attachment',
                    'Sent to ' . TEST_RECIPIENT . ' using ' . $source . ' — check inbox for PDF'
                );
            } else {
                $results[] = testFail(
                    'Invoice email with PDF attachment',
                    'mail() returned false. Check Settings → Email and that ' . ($settings['email_from'] ?? '(email_from not set)') . ' exists in DirectAdmin.'
                );
            }
        }
    } catch (Exception $e) {
        $results[] = testFail('Invoice email with PDF attachment', $e->getMessage());
    }

    // ---- TEST 6: Cron job dry run ----
    try {
        ensureSchedulingColumns($pdo);
        $due = $pdo->query("
            SELECT i.invoice_number, i.scheduled_date, c.name AS client_name, c.email AS client_email
            FROM lms_invoices i
            LEFT JOIN lms_clients c ON c.id = i.client_id
            WHERE i.status = 'draft'
              AND i.scheduled_date IS NOT NULL
              AND i.scheduled_date <= CURDATE()
              AND i.email_sent_at IS NULL
              AND c.email IS NOT NULL AND c.email != ''
        ")->fetchAll();

        $soon = $pdo->query("
            SELECT i.invoice_number, i.scheduled_date, c.name AS client_name
            FROM lms_invoices i
            LEFT JOIN lms_clients c ON c.id = i.client_id
            WHERE i.status = 'draft'
              AND i.scheduled_date > CURDATE()
              AND i.scheduled_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
              AND i.email_sent_at IS NULL
        ")->fetchAll();

        $detail = count($due) . ' overdue · ' . count($soon) . ' due within 7 days';
        if (!empty($due)) {
            $detail .= ' · OVERDUE: ' . implode(', ', array_map(fn($r) => '#' . $r['invoice_number'] . ' (' . $r['client_name'] . ')', $due));
        }
        $results[] = testPass('Cron job dry run', $detail);
    } catch (Exception $e) {
        $results[] = testFail('Cron job dry run', $e->getMessage());
    }

} // end $runAll

layoutHeader('System Tests', 'settings');
?>

<?php renderFlash(); ?>

<div style="max-width:700px;">

    <div class="card">
        <div class="card-header">
            <h2><i class="fa-solid fa-flask" style="color:var(--color-accent);"></i> System Health &amp; Email Tests</h2>
        </div>
        <div class="card-body">
            <?php if (!$runAll): ?>
                <p style="color:var(--color-muted);margin:0 0 20px;font-size:.875rem;line-height:1.6;">
                    Running all tests will send real emails to
                    <strong><?= TEST_RECIPIENT ?></strong>
                    to confirm delivery works end-to-end.
                    No real invoice data will be modified.
                </p>
                <div style="background:#fef3c7;border:1px solid #fcd34d;padding:12px 16px;border-radius:var(--radius);margin-bottom:20px;font-size:.8125rem;color:#92400e;">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <strong>Delete or restrict this file after testing</strong> — it exposes system information.
                    Remove it from the server once you're satisfied everything works.
                </div>
                <a href="?run=1" class="btn btn-primary">
                    <i class="fa-solid fa-play"></i> Run All Tests
                </a>
            <?php else: ?>
                <?php
                $passed = count(array_filter($results, fn($r) => $r['pass']));
                $total  = count($results);
                $allOk  = $passed === $total;
                ?>
                <div style="background:<?= $allOk ? '#d1fae5' : '#fef3c7' ?>;border:1px solid <?= $allOk ? '#6ee7b7' : '#fcd34d' ?>;color:<?= $allOk ? '#065f46' : '#92400e' ?>;padding:14px 18px;border-radius:var(--radius);margin-bottom:24px;font-size:.9rem;">
                    <?php if ($allOk): ?>
                        <i class="fa-solid fa-circle-check"></i>
                        <strong>All <?= $total ?> tests passed.</strong>
                        Check <strong><?= TEST_RECIPIENT ?></strong> for the test emails now.
                    <?php else: ?>
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        <strong><?= $passed ?> / <?= $total ?> passed.</strong>
                        See the failed tests below for details.
                    <?php endif; ?>
                </div>

                <table style="width:100%;border-collapse:collapse;">
                    <thead>
                        <tr style="background:var(--color-surface-alt,#f8f7f4);">
                            <th style="padding:10px 14px;text-align:left;font-size:.75rem;text-transform:uppercase;letter-spacing:.04em;color:var(--color-muted);">Test</th>
                            <th style="padding:10px 14px;text-align:center;font-size:.75rem;text-transform:uppercase;letter-spacing:.04em;color:var(--color-muted);width:80px;">Result</th>
                            <th style="padding:10px 14px;text-align:left;font-size:.75rem;text-transform:uppercase;letter-spacing:.04em;color:var(--color-muted);">Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results as $i => $r): ?>
                            <tr style="border-top:1px solid var(--color-border);<?= $i % 2 === 1 ? 'background:var(--color-surface-alt,#f8f7f4);' : '' ?>">
                                <td style="padding:12px 14px;font-weight:600;font-size:.875rem;"><?= htmlspecialchars($r['name'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td style="padding:12px 14px;text-align:center;">
                                    <?php if ($r['pass']): ?>
                                        <span style="background:#d1fae5;color:#065f46;padding:2px 10px;border-radius:999px;font-size:.75rem;font-weight:700;">PASS</span>
                                    <?php else: ?>
                                        <span style="background:#fee2e2;color:#991b1b;padding:2px 10px;border-radius:999px;font-size:.75rem;font-weight:700;">FAIL</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding:12px 14px;font-size:.8125rem;color:var(--color-muted);"><?= htmlspecialchars($r['detail'], ENT_QUOTES, 'UTF-8') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div style="margin-top:24px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
                    <a href="?run=1" class="btn btn-secondary btn-sm">
                        <i class="fa-solid fa-rotate-right"></i> Run Again
                    </a>
                    <a href="<?= $root ?>/settings.php?tab=email" class="btn btn-secondary btn-sm">
                        <i class="fa-solid fa-gear"></i> Email Settings
                    </a>
                    <span style="font-size:.78rem;color:var(--color-muted);">
                        <i class="fa-solid fa-triangle-exclamation" style="color:#d97706;"></i>
                        Delete <code>admin/run-tests.php</code> from the server once done.
                    </span>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php layoutFooter(); ?>
