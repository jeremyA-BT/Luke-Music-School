<?php
/**
 * Shared invoice PDF generation library.
 *
 * Included by invoice-pdf.php (download), email-invoice.php (email attachment),
 * and cron.php (scheduled sending). One design, three uses.
 *
 * Usage:
 *   require_once __DIR__ . '/includes/invoice-pdf-lib.php';
 *   $bytes    = generateInvoicePdf($invoice, $items, $settings);           // returns string
 *   generateInvoicePdf($invoice, $items, $settings, 'D', 'Invoice-1.pdf'); // forces browser download
 */

require_once dirname(__DIR__) . '/lib/fpdf/fpdf.php';

class InvoicePDF extends FPDF {
    public string $businessName  = '';
    public string $ownerName     = '';
    public string $addressLine1  = '';
    public string $addressLine2  = '';
    public string $phone         = '';
    public string $email         = '';

    public function Header(): void {
        $this->SetFillColor(26, 61, 28);
        $this->Rect(0, 0, 210, 8, 'F');

        $this->SetY(12);

        $this->SetFont('Helvetica', 'B', 14);
        $this->SetTextColor(26, 61, 28);
        $this->Cell(100, 7, $this->businessName, 0, 0, 'L');

        $this->SetFont('Helvetica', 'B', 22);
        $this->SetTextColor(44, 95, 46);
        $this->Cell(90, 10, 'INVOICE', 0, 1, 'R');

        $this->SetFont('Helvetica', '', 9);
        $this->SetTextColor(80, 80, 80);
        $this->Cell(100, 5, $this->ownerName, 0, 0, 'L');
        $this->Ln(5);
        $this->Cell(100, 5, $this->addressLine1, 0, 0, 'L');
        $this->Ln(5);
        $this->Cell(100, 5, $this->addressLine2, 0, 0, 'L');
        $this->Ln(5);
        $this->Cell(100, 5, $this->phone . ($this->email !== '' ? '   ' . $this->email : ''), 0, 0, 'L');
        $this->Ln(8);

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

/**
 * Build an invoice PDF.
 *
 * @param array  $invoice   Invoice DB row — must include client_name; optionally client_address, client_notes
 * @param array  $items     Line items from lms_invoice_items
 * @param array  $settings  All settings from getAllSettings()
 * @param string $dest      'S' → return as string  ·  'D' → browser download  ·  'I' → inline browser
 * @param string $filename  Used for 'D' / 'I' modes; auto-generated from invoice number if empty
 * @return string  PDF bytes when $dest='S', empty string for 'D'/'I'
 */
function generateInvoicePdf(
    array  $invoice,
    array  $items,
    array  $settings,
    string $dest     = 'S',
    string $filename = ''
): string {
    $pdf = new InvoicePDF('P', 'mm', 'A4');
    $pdf->businessName = $settings['business_name']  ?? '';
    $pdf->ownerName    = $settings['owner_name']     ?? '';
    $pdf->addressLine1 = $settings['address_line1']  ?? '';
    $pdf->addressLine2 = $settings['address_line2']  ?? '';
    $pdf->phone        = $settings['phone']          ?? '';
    $pdf->email        = $settings['email']          ?? '';

    $pdf->SetMargins(10, 10, 10);
    $pdf->SetAutoPageBreak(true, 20);
    $pdf->AddPage();

    // ---- Invoice meta (number + date, right-aligned) ----
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

    // ---- Bill To ----
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

    // ---- Line items table ----
    $colW = [100, 22, 30, 38];

    $pdf->SetFillColor(26, 61, 28);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Helvetica', 'B', 9);
    $pdf->Cell($colW[0], 8, 'Description', 1, 0, 'L', true);
    $pdf->Cell($colW[1], 8, 'Lessons',     1, 0, 'C', true);
    $pdf->Cell($colW[2], 8, 'Rate',        1, 0, 'R', true);
    $pdf->Cell($colW[3], 8, 'Amount',      1, 1, 'R', true);

    $pdf->SetFont('Helvetica', '', 9);
    $pdf->SetTextColor(26, 26, 24);
    $fill = false;
    foreach ($items as $item) {
        $pdf->SetFillColor(248, 247, 244);
        $qty = rtrim(rtrim(number_format((float) $item['quantity'], 2, '.', ''), '0'), '.');
        $pdf->Cell($colW[0], 7, $item['description'],                                          'LR', 0, 'L', $fill);
        $pdf->Cell($colW[1], 7, $qty,                                                          'LR', 0, 'C', $fill);
        $pdf->Cell($colW[2], 7, 'R ' . number_format((float) $item['rate'],   2, '.', ' '),   'LR', 0, 'R', $fill);
        $pdf->Cell($colW[3], 7, 'R ' . number_format((float) $item['amount'], 2, '.', ' '),   'LR', 1, 'R', $fill);
        $fill = !$fill;
    }

    $pdf->SetDrawColor(200, 200, 200);
    $pdf->Cell(array_sum($colW), 0, '', 'T', 1);
    $pdf->Ln(4);

    // ---- Totals ----
    $totalsX = 120;
    $totalsW = [50, 30];

    if ((float) $invoice['subtotal'] !== (float) $invoice['total']) {
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->SetTextColor(80, 80, 80);
        $pdf->SetX($totalsX);
        $pdf->Cell($totalsW[0], 6, 'Subtotal', 0, 0, 'R');
        $pdf->Cell($totalsW[1], 6, 'R ' . number_format((float) $invoice['subtotal'], 2, '.', ' '), 0, 1, 'R');

        if ((float) $invoice['discount'] > 0) {
            $pdf->SetX($totalsX);
            $pdf->Cell($totalsW[0], 6, 'Discount', 0, 0, 'R');
            $pdf->Cell($totalsW[1], 6, '- R ' . number_format((float) $invoice['discount'], 2, '.', ' '), 0, 1, 'R');
        }
        $pdf->Ln(1);
    }

    $pdf->SetFont('Helvetica', 'B', 11);
    $pdf->SetTextColor(26, 61, 28);
    $pdf->SetX($totalsX);
    $pdf->Cell($totalsW[0], 8, 'TOTAL', 0, 0, 'R');
    $pdf->Cell($totalsW[1], 8, 'R ' . number_format((float) $invoice['total'], 2, '.', ' '), 0, 1, 'R');
    $pdf->Ln(8);

    // ---- Banking details (two-column box) ----
    $bankDetails = [
        ['Bank',           $settings['bank_name']        ?? ''],
        ['Branch',         $settings['bank_branch']      ?? ''],
        ['Branch Code',    $settings['bank_branch_code'] ?? ''],
        ['Account Holder', $settings['account_holder']   ?? ''],
        ['Account Number', $settings['account_number']   ?? ''],
        ['Account Type',   $settings['account_type']     ?? ''],
    ];

    $hasBank = array_filter($bankDetails, fn($r) => $r[1] !== '');
    if (!empty($hasBank)) {
        $bankY = $pdf->GetY();

        $pdf->SetFont('Helvetica', 'B', 8);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->Cell(0, 6, 'ACCOUNT DETAILS', 0, 1, 'L');

        $bankY   = $pdf->GetY();
        $boxH    = 36;
        $pdf->SetFillColor(248, 247, 244);
        $pdf->SetDrawColor(220, 216, 210);
        $pdf->Rect(10, $bankY - 2, 190, $boxH, 'DF');

        $rowY          = $bankY + 2;
        $leftBankItems = array_slice($bankDetails, 0, 3);
        $rightBankItems = array_slice($bankDetails, 3, 3);

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

        $pdf->SetY($bankY + $boxH + 2);
    }

    // ---- Client-facing notes + payment terms ----
    $clientNotes  = trim($invoice['client_notes']  ?? '');
    $paymentTerms = trim($settings['payment_terms'] ?? '');

    if ($clientNotes !== '' || $paymentTerms !== '') {
        $pdf->Ln(4);
        $pdf->SetFont('Helvetica', 'B', 8);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->Cell(0, 5, 'NOTES', 0, 1, 'L');
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->SetTextColor(26, 26, 24);

        if ($clientNotes !== '') {
            foreach (explode("\n", $clientNotes) as $noteLine) {
                $noteLine = trim($noteLine);
                if ($noteLine !== '') {
                    $pdf->MultiCell(0, 5, $noteLine, 0, 'L');
                }
            }
        }

        if ($paymentTerms !== '') {
            if ($clientNotes !== '') $pdf->Ln(2);
            $pdf->SetFont('Helvetica', 'I', 8.5);
            $pdf->SetTextColor(80, 80, 80);
            $pdf->MultiCell(0, 5, $paymentTerms, 0, 'L');
        }
    }

    // ---- Paid stamp ----
    if (($invoice['status'] ?? '') === 'paid') {
        $pdf->SetTextColor(34, 139, 34);
        $pdf->SetFont('Helvetica', 'B', 40);
        $pdf->SetXY(110, 100);
        $pdf->Cell(80, 20, 'PAID', 0, 0, 'C');
        $pdf->SetTextColor(0, 0, 0);
    }

    if ($dest === 'D' && $filename === '') {
        $clientSlug = preg_replace('/[^a-zA-Z0-9]/', '-', $invoice['client_name'] ?? 'client');
        $filename   = 'Invoice-' . $invoice['invoice_number'] . '-' . $clientSlug . '.pdf';
    }

    $result = $pdf->Output($dest, $filename);
    return is_string($result) ? $result : '';
}
