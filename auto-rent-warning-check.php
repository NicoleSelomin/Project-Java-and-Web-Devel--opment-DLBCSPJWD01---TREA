<?php
require 'db_connect.php';
define('SYSTEM_STAFF_ID', 9999);

// Fetch all overdue, unpaid recurring invoices (no payment proof)
$sql = "
    SELECT i.invoice_id, i.claim_id, i.due_date, i.payment_proof
    FROM rental_recurring_invoices i
    WHERE i.payment_status != 'confirmed'
      AND i.due_date < CURDATE()
      AND (i.payment_proof IS NULL OR i.payment_proof = '')
";
$stmt = $pdo->query($sql);
$invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($invoices as $inv) {
    $invoice_id = $inv['invoice_id'];
    $claim_id   = $inv['claim_id'];

    // Skip if warning for this invoice already exists
    $warnStmt = $pdo->prepare(
        "SELECT 1 FROM rent_warnings WHERE invoice_id = ? LIMIT 1"
    );
    $warnStmt->execute([$invoice_id]);
    if ($warnStmt->fetch()) continue;

    // Skip if payment proof is now present
    if (!empty($inv['payment_proof'])) continue;

    // Insert automatic warning for this overdue invoice
    $msg = "Reminder: Your rent payment for this period is overdue. Please pay immediately. Penalties may apply.";
    $stmtIns = $pdo->prepare(
        "INSERT INTO rent_warnings (claim_id, invoice_id, warning_type, message, notified_by)
         VALUES (?, ?, 'automatic', ?, ?)"
    );
    $stmtIns->execute([$claim_id, $invoice_id, $msg, 5]);
}
?>
