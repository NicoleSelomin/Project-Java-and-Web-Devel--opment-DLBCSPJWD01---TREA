<!-- edit-rent-invoice-settings.php: Update recurring rent invoice settings and regenerate invoices -->

<?php
/**
 * Edit Recurring Rent Invoice Settings
 *
 * Allows the accountant to update recurring invoice settings for a rental claim.
 * - Updates the settings in `rental_recurring_invoices` for a given claim
 * - Deletes all existing pending recurring invoices for the claim
 * - Regenerates future invoices based on new settings and contract end date
 * - Uses safe prepared statements and robust error handling
 *
 * Dependencies:
 *  - db_connect.php: Provides $pdo PDO connection
 *  - Only accessible to staff with 'accountant' role
 */

require 'db_connect.php';
session_start();

// -----------------------------------------------------------------------------
// 1. ACCESS CONTROL: Only accountant role can use this script
// -----------------------------------------------------------------------------
if (!isset($_SESSION['staff_id']) || strtolower($_SESSION['role']) !== 'accountant') {
    header("Location: staff-login.php");
    exit();
}

// -----------------------------------------------------------------------------
// 2. HANDLE POST: VALIDATE & SANITIZE INPUTS
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and fetch input fields from POST
    $claimId     = $_POST['claim_id'] ?? null;
    $invoiceDate = $_POST['invoice_date'] ?? null;
    $frequency   = $_POST['payment_frequency'] ?? null;
    $dueDays     = isset($_POST['due_date']) ? (int)$_POST['due_date'] : null;
    $penalty     = isset($_POST['penalty_rate']) ? (float)$_POST['penalty_rate'] : null;
    $amount      = isset($_POST['amount']) ? (float)$_POST['amount'] : null;

    // Validate all required fields are present and non-empty
    if (
        empty($claimId) || empty($invoiceDate) || empty($frequency) ||
        !isset($dueDays, $penalty, $amount)
    ) {
        $_SESSION['message'] = "All fields are required.";
        $_SESSION['message_type'] = "danger";
        header("Location: confirm-rental-management-invoices.php");
        exit();
    }

    try {
        // ---------------------------------------------------------------------
        // 3. UPDATE INVOICE SETTINGS IN DB
        // ---------------------------------------------------------------------
        $stmt = $pdo->prepare("
            UPDATE rental_recurring_invoices
            SET invoice_date = ?, payment_frequency = ?, due_date = ?, penalty_rate = ?, amount = ?
            WHERE claim_id = ?
        ");
        $stmt->execute([$invoiceDate, $frequency, $dueDays, $penalty, $amount, $claimId]);

        // ---------------------------------------------------------------------
        // 4. DELETE ALL EXISTING PENDING RECURRING INVOICES FOR THIS CLAIM
        // ---------------------------------------------------------------------
        $pdo->prepare("DELETE FROM rental_recurring_invoices WHERE claim_id = ? AND payment_status = 'pending'")
            ->execute([$claimId]);

        // ---------------------------------------------------------------------
        // 5. FETCH CONTRACT END DATE TO GENERATE NEW INVOICES
        // ---------------------------------------------------------------------
        $contractEndStmt = $pdo->prepare("SELECT contract_end_date FROM rental_contracts WHERE claim_id = ?");
        $contractEndStmt->execute([$claimId]);
        $contract = $contractEndStmt->fetch(PDO::FETCH_ASSOC);

        if ($contract && $contract['contract_end_date']) {
            $start = new DateTime($invoiceDate);
            $end = new DateTime($contract['contract_end_date']);
            $now = new DateTime();

            // Only generate future invoices (from today to contract end)
            if ($now < $end) {
                // Determine DateInterval based on selected frequency
                $interval = match (strtolower($frequency)) {
                    'daily'     => new DateInterval('P1D'),
                    'monthly'   => new DateInterval('P1M'),
                    'quarterly' => new DateInterval('P3M'),
                    'yearly'    => new DateInterval('P1Y'),
                    default     => null,
                };

                if ($interval) {
                    // Create all dates for invoice generation (up to and including contract end)
                    $period = new DatePeriod($start, $interval, (clone $end)->modify('+1 day'));

                    // Prepare insert statement for new invoices
                    $insert = $pdo->prepare("
                        INSERT INTO rental_recurring_invoices 
                        (claim_id, invoice_date, due_date, amount, penalty_rate, payment_frequency, payment_status)
                        VALUES (?, ?, ?, ?, ?, ?, 'pending')
                    ");

                    foreach ($period as $date) {
                        // Skip dates before today (don't create past invoices)
                        if ($date < $now) continue;

                        $invoiceDateStr = $date->format('Y-m-d');
                        $dueDateStr = (clone $date)->modify("+{$dueDays} days")->format('Y-m-d');

                        // Insert each invoice
                        $insert->execute([$claimId, $invoiceDateStr, $dueDateStr, $amount, $penalty, $frequency]);
                    }
                }
            }
        }

        $_SESSION['message'] = "Recurring invoice settings updated and regenerated successfully.";
        $_SESSION['message_type'] = "success";

    } catch (PDOException $e) {
        // Log for debugging, but show generic error to user
        error_log("Recurring invoice update error: " . $e->getMessage());
        $_SESSION['message'] = "Database error. Please try again.";
        $_SESSION['message_type'] = "danger";
    }

    // Redirect back to invoice management
    header("Location: confirm-rental-management-invoices.php");
    exit();
}
?>
