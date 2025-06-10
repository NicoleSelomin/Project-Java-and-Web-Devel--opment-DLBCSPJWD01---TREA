<?php
// -----------------------------------------------------------------------------
// auto-rent-warning.php
// -----------------------------------------------------------------------------
// Scans for overdue rental recurring invoices (unpaid past due date), and
// inserts an "automatic" warning in rent_warnings table for each claim/invoice
// that hasn't already received such a warning.
// This script can be run as a scheduled cron job (e.g., daily).
// -----------------------------------------------------------------------------

require 'db_connect.php';

// 1. Fetch all unpaid rental invoices past their due date and only for approved claims.
$sql = "
    SELECT i.invoice_id, i.claim_id, i.due_date, i.payment_status
    FROM rental_recurring_invoices i
    JOIN client_claims c ON i.claim_id = c.claim_id
    WHERE i.payment_status != 'confirmed'
      AND i.due_date < CURDATE()
      AND c.claim_status = 'approved'
";

$stmt = $pdo->query($sql);
$invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 2. Loop through overdue invoices to issue warnings (if not already sent).
foreach ($invoices as $inv) {
    // Check if an automatic warning has already been sent for this claim/invoice.
    $check = $pdo->prepare("
        SELECT COUNT(*) FROM rent_warnings
        WHERE claim_id = ? AND warning_type = 'automatic'
    ");
    $check->execute([$inv['claim_id']]);
    if ($check->fetchColumn() > 0) continue; // Skip if warning exists

    // Insert a new automatic warning for this claim
    $insert = $pdo->prepare("
        INSERT INTO rent_warnings (claim_id, warning_type)
        VALUES (?, 'automatic')
    ");
    $insert->execute([$inv['claim_id']]);
}

// End of script.
?>
