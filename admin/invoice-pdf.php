<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireAuth();

$pdo = getDb();
$id  = inputInt('id', 'get');

$stmt = $pdo->prepare("
    SELECT i.*, c.name AS client_name, c.address AS client_address
    FROM lms_invoices i
    JOIN lms_clients c ON c.id = i.client_id
    WHERE i.id = ?
");
$stmt->execute([$id]);
$invoice = $stmt->fetch();

if (!$invoice) {
    http_response_code(404);
    die('Invoice not found.');
}

$itemStmt = $pdo->prepare("
    SELECT * FROM lms_invoice_items WHERE invoice_id = ? ORDER BY sort_order ASC, id ASC
");
$itemStmt->execute([$id]);
$lineItems = $itemStmt->fetchAll();

require_once __DIR__ . '/lib/fpdf/fpdf.php';

// -------------------------------------------------------
// Custom FPDF class with header/footer
// -------------------------------------------------------
class InvoicePDF extends FPDF {
    public string $businessName  = '';
    public string $ownerName     = '';
    public string $addressLine1  = '';
    public string $addressLine2  = '';
    public string $phone         = '';
    public string $email         = '';

    public function Header(): void {
        // Decorative top bar
        $this->SetFillColor(26, 61, 28);   // --color-accent-dk
        $this->Rect(0, 0, 210, 8, 'F');

        $this->SetY(12);

        // Business name (left)
        $this->SetFont('Helvetica', 'B', 14);
        $this->SetTextColor(26, 61, 28);
        $this->Cell(100, 7, $this->businessName, 0, 0, 'L');

        // "INVOICE" title (right)
        $this->SetFont('Helvetica', 'B', 22);
        $this->SetTextColor(44, 95, 46);   // --color-accent
        $this->Cell(90, 10, 'INVOICE', 0, 1, 'R');

        // Sub info (left)
        $this->SetFont('Helvetica', '', 9);
        $this->SetTextColor(80, 80, 80);
        $this->Cell(100, 5, $this->ownerName, 0, 0, 'L');
        $this->Ln(5);
        $this->Cell(100, 5, $this->addressLine1, 0, 0, 'L');
        $this->Ln(5);
        $this->Cell(100, 5, $this->addressLine2, 0, 0, 'L');
        $this->Ln(5);
        $this->Cell(100, 5, $this->phone . '   ' . $this->email, 0, 0, 'L');
        $this->Ln(8);

        // Separator
        $this->SetDrawColor(220, 216, 210);
        $this->SetLineWidth(0.4);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(4);
    }

    public function Footer(): void {
        $this->SetY(-15);
        $this->SetFont('Helvetica', 'I', 8);
        $this->SetTextColor(150, 150, 150);
        $this->Cell(0, 10, 'Thank you for your business!', 0, 0, 'C');
    }
}

$pdf = new InvoicePDF('P', 'mm', 'A4');
$pdf->businessName = defined('INVOICE_BUSINESS_NAME') ? INVOICE_BUSINESS_NAME : "Luke's Music Lessons";
$pdf->ownerName    = defined('INVOICE_OWNER_NAME')    ? INVOICE_OWNER_NAME    : 'Luke Higgins';
$pdf->addressLine1 = defined('INVOICE_ADDRESS_LINE1') ? INVOICE_ADDRESS_LINE1 : '';
$pdf->addressLine2 = defined('INVOICE_ADDRESS_LINE2') ? INVOICE_ADDRESS_LINE2 : '';
$pdf->phone        = defined('INVOICE_PHONE')         ? INVOICE_PHONE         : '';
$pdf->email        = defined('INVOICE_EMAIL')         ? INVOICE_EMAIL         : '';

$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(true, 20);
$pdf->AddPage();

// -------------------------------------------------------
// Invoice meta (number + date, right side)
// -------------------------------------------------------
$startY = $pdf->GetY();
$pdf->SetFont('Helvetica', 'B', 10);
$pdf->SetTextColor(26, 61, 28);
$pdf->Cell(130, 6, '', 0, 0);
$pdf->Cell(60, 6, 'Invoice #' . $invoice['invoice_number'], 0, 1, 'R');
$pdf->SetFont('Helvetica', '', 9);
$pdf->SetTextColor(80, 80, 80);
$pdf->Cell(130, 5, '', 0, 0);
$invoiceDate = DateTime::createFromFormat('Y-m-d', $invoice['invoice_date']);
$pdf->Cell(60, 5, 'Date: ' . ($invoiceDate ? $invoiceDate->format('j F Y') : $invoice['invoice_date']), 0, 1, 'R');
$pdf->Ln(2);

// -------------------------------------------------------
// Bill To section
// -------------------------------------------------------
$pdf->SetFont('Helvetica', 'B', 8);
$pdf->SetTextColor(100, 100, 100);
$pdf->Cell(0, 5, 'BILL TO', 0, 1, 'L');

$pdf->SetFont('Helvetica', 'B', 10);
$pdf->SetTextColor(26, 26, 24);
$pdf->Cell(0, 6, $invoice['client_name'], 0, 1, 'L');

if (!empty($invoice['client_address'])) {
    $pdf->SetFont('Helvetica', '', 9);
    $pdf->SetTextColor(80, 80, 80);
    foreach (explode("\n", $invoice['client_address']) as $line) {
        $line = trim($line);
        if ($line !== '') {
            $pdf->Cell(0, 5, $line, 0, 1, 'L');
        }
    }
}

$pdf->Ln(6);

// -------------------------------------------------------
// Line items table
// -------------------------------------------------------
$colW = [100, 22, 30, 38];  // description, qty, rate, amount

// Table header
$pdf->SetFillColor(26, 61, 28);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Helvetica', 'B', 9);
$pdf->Cell($colW[0], 8, 'Description', 1, 0, 'L', true);
$pdf->Cell($colW[1], 8, 'Lessons',     1, 0, 'C', true);
$pdf->Cell($colW[2], 8, 'Rate',        1, 0, 'R', true);
$pdf->Cell($colW[3], 8, 'Amount',      1, 1, 'R', true);

// Table rows
$pdf->SetFont('Helvetica', '', 9);
$pdf->SetTextColor(26, 26, 24);
$fill = false;
foreach ($lineItems as $item) {
    $pdf->SetFillColor(248, 247, 244);
    $qty = rtrim(rtrim(number_format((float)$item['quantity'], 2, '.', ''), '0'), '.');
    $pdf->Cell($colW[0], 7, $item['description'], 'LR', 0, 'L', $fill);
    $pdf->Cell($colW[1], 7, $qty,                 'LR', 0, 'C', $fill);
    $pdf->Cell($colW[2], 7, 'R ' . number_format((float)$item['rate'],   2, '.', ' '), 'LR', 0, 'R', $fill);
    $pdf->Cell($colW[3], 7, 'R ' . number_format((float)$item['amount'], 2, '.', ' '), 'LR', 1, 'R', $fill);
    $fill = !$fill;
}

// Close table bottom border
$tableWidth = array_sum($colW);
$pdf->SetDrawColor(200, 200, 200);
$pdf->Cell($tableWidth, 0, '', 'T', 1);
$pdf->Ln(4);

// -------------------------------------------------------
// Totals
// -------------------------------------------------------
$totalsX = 120;
$totalsW = [50, 30];

if ((float)$invoice['subtotal'] !== (float)$invoice['total']) {
    $pdf->SetFont('Helvetica', '', 9);
    $pdf->SetTextColor(80, 80, 80);
    $pdf->SetX($totalsX);
    $pdf->Cell($totalsW[0], 6, 'Subtotal', 0, 0, 'R');
    $pdf->Cell($totalsW[1], 6, 'R ' . number_format((float)$invoice['subtotal'], 2, '.', ' '), 0, 1, 'R');

    if ((float)$invoice['discount'] > 0) {
        $pdf->SetX($totalsX);
        $pdf->Cell($totalsW[0], 6, 'Discount', 0, 0, 'R');
        $pdf->Cell($totalsW[1], 6, '- R ' . number_format((float)$invoice['discount'], 2, '.', ' '), 0, 1, 'R');
    }
    $pdf->Ln(1);
}

$pdf->SetFont('Helvetica', 'B', 11);
$pdf->SetTextColor(26, 61, 28);
$pdf->SetX($totalsX);
$pdf->Cell($totalsW[0], 8, 'TOTAL', 0, 0, 'R');
$pdf->Cell($totalsW[1], 8, 'R ' . number_format((float)$invoice['total'], 2, '.', ' '), 0, 1, 'R');
$pdf->Ln(8);

// -------------------------------------------------------
// Banking details box
// -------------------------------------------------------
$pdf->SetFillColor(248, 247, 244);
$pdf->SetDrawColor(220, 216, 210);
$pdf->SetFont('Helvetica', 'B', 8);
$pdf->SetTextColor(100, 100, 100);
$pdf->Cell(0, 6, 'ACCOUNT DETAILS', 0, 1, 'L');

$pdf->SetFont('Helvetica', '', 9);
$pdf->SetFillColor(248, 247, 244);
$bankX = $pdf->GetX();
$bankY = $pdf->GetY();

$bankDetails = [
    ['Bank',           defined('INVOICE_BANK_NAME')        ? INVOICE_BANK_NAME        : ''],
    ['Branch',         defined('INVOICE_BANK_BRANCH')      ? INVOICE_BANK_BRANCH      : ''],
    ['Branch Code',    defined('INVOICE_BANK_BRANCH_CODE') ? INVOICE_BANK_BRANCH_CODE : ''],
    ['Account Holder', defined('INVOICE_ACCOUNT_HOLDER')   ? INVOICE_ACCOUNT_HOLDER   : ''],
    ['Account Number', defined('INVOICE_ACCOUNT_NUMBER')   ? INVOICE_ACCOUNT_NUMBER   : ''],
    ['Account Type',   defined('INVOICE_ACCOUNT_TYPE')     ? INVOICE_ACCOUNT_TYPE     : ''],
];

$col   = 0;
$colXs = [10, 75, 140];
$rowY  = $bankY;

foreach ($bankDetails as $i => [$label, $value]) {
    $pdf->SetXY($colXs[$col], $rowY + ($col > 0 ? ($i % 2) * 10 : ($i % 3) * 10));
    $pdf->SetFont('Helvetica', 'B', 8);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->Cell(55, 5, strtoupper($label), 0, 0, 'L');
    $pdf->Ln();
    $pdf->SetX($colXs[$col]);
    $pdf->SetFont('Helvetica', '', 9);
    $pdf->SetTextColor(26, 26, 24);
    $pdf->Cell(55, 5, $value, 0, 0, 'L');
}

// Cleaner layout for banking details — two columns
$pdf->SetY($bankY);
$leftBankItems  = array_slice($bankDetails, 0, 3);
$rightBankItems = array_slice($bankDetails, 3, 3);

// Reset and do a two-column layout properly
$pdf->SetY($bankY);

// Draw banking box
$boxH = 36;
$pdf->SetFillColor(248, 247, 244);
$pdf->Rect(10, $bankY - 2, 190, $boxH, 'DF');

$rowY = $bankY + 2;
foreach ($leftBankItems as $idx => [$label, $value]) {
    $pdf->SetXY(14, $rowY + $idx * 10);
    $pdf->SetFont('Helvetica', 'B', 7.5);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->Cell(80, 4, strtoupper($label), 0, 2, 'L');
    $pdf->SetX(14);
    $pdf->SetFont('Helvetica', '', 9);
    $pdf->SetTextColor(26, 26, 24);
    $pdf->Cell(80, 5, $value, 0, 0, 'L');
}

foreach ($rightBankItems as $idx => [$label, $value]) {
    $pdf->SetXY(110, $rowY + $idx * 10);
    $pdf->SetFont('Helvetica', 'B', 7.5);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->Cell(80, 4, strtoupper($label), 0, 2, 'L');
    $pdf->SetX(110);
    $pdf->SetFont('Helvetica', '', 9);
    $pdf->SetTextColor(26, 26, 24);
    $pdf->Cell(80, 5, $value, 0, 0, 'L');
}

// Output
$filename = 'Invoice-' . $invoice['invoice_number'] . '-' . preg_replace('/[^a-zA-Z0-9]/', '-', $invoice['client_name']) . '.pdf';
$pdf->Output('D', $filename);
