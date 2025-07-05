<?php
// generate-recurring-invoice.php

require 'db_connect.php';
require_once 'libs/dompdf/autoload.inc.php';

use Dompdf\Dompdf;

$invoice_id = isset($_GET['invoice_id']) ? intval($_GET['invoice_id']) : 0;
if (!$invoice_id) exit('Invoice not specified.');

// 1. Fetch invoice & related details
$stmt = $pdo->prepare("
    SELECT ri.*, cc.claim_id, c.client_id, u.full_name AS client_name, u.email, 
           p.property_id, p.property_name, p.location, 
           rc.amount AS monthly_rent, rc.contract_start_date, rc.contract_end_date, rc.penalty_rate, 
           rc.payment_frequency, rc.grace_period_days
    FROM rental_recurring_invoices ri
    JOIN client_claims cc ON ri.claim_id = cc.claim_id
    JOIN clients c ON cc.client_id = c.client_id
    JOIN users u ON c.user_id = u.user_id
    JOIN properties p ON cc.property_id = p.property_id
    JOIN rental_contracts rc ON cc.claim_id = rc.claim_id
    WHERE ri.invoice_id = ?
    LIMIT 1
");
$stmt->execute([$invoice_id]);
$invoice = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$invoice) exit('Invoice not found.');

// Use the correct period covered for prepaid (next month/quarter/etc.)
$periodStart = $invoice['start_period_date'] ?? $invoice['invoice_date'];
$periodEnd   = $invoice['end_period_date'] ?? $invoice['due_date'];

// Due date is stored in DB, don't recalc
$dueDate = $invoice['due_date'];

// Agency/static info
$agencyName = "Trusted Real Estate Agency (TREA)";
$invoiceNumber = "INV-" . str_pad($invoice['invoice_id'], 6, "0", STR_PAD_LEFT);
$invoiceDate = $invoice['invoice_date'];
$clientName = $invoice['client_name'];
$propertyName = $invoice['property_name'];
$claimId = $invoice['claim_id'];
$frequency = ucfirst($invoice['payment_frequency']);

// Rent and penalty
$baseRent = number_format($invoice['monthly_rent'], 2);
$months = match (strtolower($invoice['payment_frequency'])) {
    'monthly' => 1, 'quarterly' => 3, 'yearly' => 12, default => 1,
};
$total = number_format($invoice['monthly_rent'] * $months, 2);
$penaltyRate = number_format($invoice['penalty_rate'], 2);

// Penalty/Overdue logic
$now = new DateTime();
$due_date_dt = new DateTime($dueDate);
$is_overdue = $now > $due_date_dt && $invoice['payment_status'] !== 'confirmed';
$overdueHtml = "";
if ($is_overdue && $invoice['penalty_rate'] > 0) {
    $days_late = $due_date_dt->diff($now)->days;
    $penalty_total = $invoice['monthly_rent'] * $months * $invoice['penalty_rate'] / 100;
    $overdueHtml = "
      <tr>
        <td style='color:red;'><b>Overdue ({$days_late} days)</b></td>
        <td align='right'><span style='color:red;'>Penalty: " . number_format($penalty_total, 2) . "</span></td>
      </tr>
      <tr>
        <td><b>Total with Penalty</b></td>
        <td align='right' class='amount'>" . number_format(($invoice['monthly_rent'] * $months) + $penalty_total, 2) . "</td>
      </tr>
    ";
}

// 3. Load and fill the template
$template_path = 'invoice-rent-recurring.html';
if (!file_exists($template_path)) {
    exit('Invoice template not found.');
}
$template = file_get_contents($template_path);
$filled = str_replace([
    '{{AGENCY_NAME}}', '{{INVOICE_NUMBER}}', '{{INVOICE_DATE}}', '{{CLIENT_NAME}}',
    '{{PROPERTY_NAME}}', '{{CLAIM_ID}}', '{{PAYMENT_FREQUENCY}}', '{{START_DATE}}',
    '{{END_DATE}}', '{{BASE_RENT}}', '{{MONTHS}}', '{{TOTAL}}', '{{PENALTY_RATE}}',
    '{{DUE_DATE}}', '{{OVERDUE_SECTION}}'
], [
    $agencyName, $invoiceNumber, $invoiceDate, $clientName,
    $propertyName, $claimId, $frequency, $periodStart,
    $periodEnd, $baseRent, $months, $total, $penaltyRate,
    $dueDate, $overdueHtml
], $template);

// 4. Generate PDF (On Demand, Don't Save to Disk Unless For Archive/Admin)
$dompdf = new Dompdf(['chroot' => __DIR__]);
$dompdf->loadHtml($filled);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

function safe_slug($text) {
    return strtolower(trim(preg_replace('/[^A-Za-z0-9_]/', '', str_replace(' ', '_', $text))));
}
$client_folder = "uploads/clients/" . $invoice['client_id'] . "_" . safe_slug($invoice['client_name']);
$property_folder = $client_folder . "/reserved_properties/" . $invoice['property_id'] . "_" . safe_slug($invoice['property_name']);
$pdfPath = $property_folder . "/invoice_" . $invoice['invoice_id'] . ".pdf";
if (!is_dir($property_folder)) {
    mkdir($property_folder, 0777, true);
}
file_put_contents($pdfPath, $dompdf->output());
if (empty($invoice['invoice_path']) || $invoice['invoice_path'] !== $pdfPath) {
    $stmt = $pdo->prepare("UPDATE rental_recurring_invoices SET invoice_path = ? WHERE invoice_id = ?");
    $stmt->execute([$pdfPath, $invoice_id]);
}

// 5. Browser preview? (for debug, AJAX, or admin preview)
if (isset($_GET['preview']) && $_GET['preview'] == 1) {
    echo $filled;
    exit;
}

// 6. PDF download in browser (for admin/staff/user direct download/view)
$dompdf->stream("Rent-Invoice-{$invoiceNumber}.pdf", ["Attachment" => false]);
exit;
?>
