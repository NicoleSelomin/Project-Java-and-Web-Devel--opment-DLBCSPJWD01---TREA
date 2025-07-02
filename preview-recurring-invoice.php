<?php
require 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') exit;
$claimId = intval($_POST['claim_id'] ?? 0);
$startDate = $_POST['start_date'] ?? null;
if (!$claimId || !$startDate) exit('Missing info.');

// Fetch contract & client details
$stmt = $pdo->prepare("
    SELECT cc.claim_id, u.full_name AS client_name, p.property_name, rc.amount AS monthly_rent, 
           rc.penalty_rate, rc.payment_frequency, rc.contract_start_date, rc.contract_end_date, rc.grace_period_days
    FROM client_claims cc
    JOIN clients c ON cc.client_id = c.client_id
    JOIN users u ON c.user_id = u.user_id
    JOIN properties p ON cc.property_id = p.property_id
    JOIN rental_contracts rc ON cc.claim_id = rc.claim_id
    WHERE cc.claim_id = ?
    LIMIT 1
");
$stmt->execute([$claimId]);
$info = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$info) exit('Contract not found.');

// Assign variables
$agency_name   = "Trusted Real Estate Agency (TREA)";
$invoice_number = "PREVIEW";
$invoice_date   = htmlspecialchars($startDate);
$client_name    = htmlspecialchars($info['client_name']);
$property_name  = htmlspecialchars($info['property_name']);
$claim_id       = $info['claim_id'];
$frequency      = ucfirst($info['payment_frequency']);
$period_months  = match (strtolower($info['payment_frequency'])) {
    'monthly' => 1,
    'quarterly' => 3,
    'yearly' => 12,
    default => 1,
};
$base_rent      = number_format($info['monthly_rent'], 2);
$total          = $info['monthly_rent'] * $period_months;
$penalty_rate   = (float)$info['penalty_rate'];
$grace_days     = intval($info['grace_period_days']);
$start_contract = $info['contract_start_date'];
$end_contract   = $info['contract_end_date'];

// --- Calculate due date (INCLUSIVE) ---
$due_date = null;
if ($grace_days > 0) {
    $due_date = (new DateTime($invoice_date))->modify("+" . ($grace_days) . " days")->format('Y-m-d');
} else {
    $due_date = $invoice_date; // fallback
}

// --- Penalty/overdue logic: only for display, not used in preview ---
$isOverdue = false;
$penaltyAmount = 0;
$overdue_html = "";
if ($isOverdue && $penalty_rate > 0) {
    $penaltyAmount = $total * ($penalty_rate / 100);
    $totalWithPenalty = $total + $penaltyAmount;
    $overdue_html = "
    <tr>
      <td>Penalty Rate</td>
      <td align='right'>".number_format($penalty_rate,2)."%</td>
    </tr>
    <tr>
      <td>Penalty Amount</td>
      <td align='right' class='penalty'>".number_format($penaltyAmount,2)."</td>
    </tr>
    <tr>
      <td><b>Total With Penalty</b></td>
      <td align='right' class='amount'>".number_format($totalWithPenalty,2)."</td>
    </tr>
    ";
}

// --- Fill and output the template ---
$template = file_get_contents('invoice-rent-recurring.html');
$filled = str_replace([
    '{{AGENCY_NAME}}', '{{INVOICE_NUMBER}}', '{{INVOICE_DATE}}', '{{CLIENT_NAME}}',
    '{{PROPERTY_NAME}}', '{{CLAIM_ID}}', '{{PAYMENT_FREQUENCY}}', '{{START_DATE}}',
    '{{END_DATE}}', '{{BASE_RENT}}', '{{MONTHS}}', '{{TOTAL}}', '{{PENALTY_RATE}}',
    '{{DUE_DATE}}', '{{OVERDUE_SECTION}}'
], [
    $agency_name, $invoice_number, $invoice_date, $client_name,
    $property_name, $claim_id, $frequency, $start_contract,
    $end_contract, $base_rent, $period_months, number_format($total,2), number_format($penalty_rate,2),
    $due_date, $overdue_html
], $template);

echo $filled;
