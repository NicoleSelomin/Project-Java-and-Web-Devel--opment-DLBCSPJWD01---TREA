<?php
/**
 * ----------------------------------------------------------------------------------
 * generate-recurring-invoices.php
 * ----------------------------------------------------------------------------------
 *
 * Script to auto-generate new recurring rent invoices for all active claims.
 * - Runs as a scheduled (cron) task or manually as needed.
 * - Checks all claims with active recurring invoicing.
 * - For each, generates the next invoice if within contract period and not already created.
 *
 * Dependencies:
 * - db_connect.php (PDO $pdo)
 * - rental_contracts table (for contract_end_date)
 * - rental_recurring_invoices table (stores invoice records and settings)
 *_____________________________________________________________________________________
 */

require 'db_connect.php';

$now = new DateTime();
$invoicesGenerated = 0;

// -----------------------------------------------------------------------------
// Fetch all claim IDs where recurring invoicing is active
// -----------------------------------------------------------------------------
$stmt = $pdo->query("
    SELECT DISTINCT claim_id
    FROM rental_recurring_invoices
    WHERE recurring_active = 1
");
$claimIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

foreach ($claimIds as $claim_id) {
    // -------------------------------------------------------------------------
    // Fetch contract end date for this claim (skip if missing or ended)
    // -------------------------------------------------------------------------
    $contractStmt = $pdo->prepare("SELECT contract_end_date FROM rental_contracts WHERE claim_id = ?");
    $contractStmt->execute([$claim_id]);
    $contract = $contractStmt->fetch(PDO::FETCH_ASSOC);
    if (!$contract || !$contract['contract_end_date']) continue;

    $end_date = new DateTime($contract['contract_end_date']);
    if ($now > $end_date) continue; // Contract already ended

    // -------------------------------------------------------------------------
    // Fetch the latest (most recent) invoice for this claim
    // -------------------------------------------------------------------------
    $invStmt = $pdo->prepare("
        SELECT * FROM rental_recurring_invoices
        WHERE claim_id = ?
        ORDER BY invoice_date DESC
        LIMIT 1
    ");
    $invStmt->execute([$claim_id]);
    $lastInvoice = $invStmt->fetch(PDO::FETCH_ASSOC);
    if (!$lastInvoice) continue; // No recurring settings or prior invoice

    // -------------------------------------------------------------------------
    // Determine the frequency interval and calculate next invoice date
    // -------------------------------------------------------------------------
    $last_invoice_date = new DateTime($lastInvoice['invoice_date']);
    if ($last_invoice_date >= $end_date) continue; // Last invoice is after contract

    $frequency = $lastInvoice['payment_frequency'];
    $interval = match ($frequency) {
        'daily'     => new DateInterval('P1D'),
        'monthly'   => new DateInterval('P1M'),
        'quarterly' => new DateInterval('P3M'),
        'yearly'    => new DateInterval('P1Y'),
        default     => null,
    };
    if (!$interval) continue; // Unknown frequency

    $amount           = $lastInvoice['amount'];
    $penalty          = $lastInvoice['penalty_rate'];
    $due_days         = (new DateTime($lastInvoice['due_date']))->diff(new DateTime($lastInvoice['invoice_date']))->days ?? 7; // Fallback to 7 days
    $recurring_active = $lastInvoice['recurring_active'];

    // -------------------------------------------------------------------------
    // Generate next invoice(s) if needed (one or more, if script is run late)
    // -------------------------------------------------------------------------
    $nextDate = (clone $last_invoice_date)->add($interval);

    // Only generate invoices with a date >= today and <= contract end date
    while ($nextDate <= $end_date && $nextDate >= $now) {
        $invoice_date = $nextDate->format('Y-m-d');

        // Prevent duplicate invoices for the same date
        $check = $pdo->prepare("SELECT COUNT(*) FROM rental_recurring_invoices WHERE claim_id = ? AND invoice_date = ?");
        $check->execute([$claim_id, $invoice_date]);
        if ($check->fetchColumn() > 0) {
            $nextDate->add($interval);
            continue;
        }

        $due_date = (clone $nextDate)->modify("+{$due_days} days")->format('Y-m-d');

        // Create the invoice record
        $insert = $pdo->prepare("
            INSERT INTO rental_recurring_invoices
                (claim_id, invoice_date, due_date, payment_frequency, amount, penalty_rate, payment_status, payment_proof, created_at, recurring_active)
            VALUES
                (?, ?, ?, ?, ?, ?, 'pending', NULL, NOW(), ?)
        ");
        $insert->execute([$claim_id, $invoice_date, $due_date, $frequency, $amount, $penalty, $recurring_active]);
        $invoicesGenerated++;

        $nextDate->add($interval); // Prepare for next (if script run late, generate all missed)
    }
}

// -----------------------------------------------------------------------------
// Output summary (for manual runs/logs)
// -----------------------------------------------------------------------------
echo "Recurring invoices generated: $invoicesGenerated";

?>
