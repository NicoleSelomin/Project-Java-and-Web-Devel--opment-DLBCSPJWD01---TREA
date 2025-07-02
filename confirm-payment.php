<?php
/**
 * confirm-payment.php
 *
 * This script is used by accountants to confirm a rental claim payment.
 * - Ensures only logged-in accountants can access this endpoint.
 * - Expects a POST request with 'payment_id' to identify the claim payment.
 * - Updates the payment status to 'confirmed', records who and when it was confirmed.
 * - Redirects back to the confirmation page with a status message.
 *
 * Dependencies:
 * - db_connect.php: Provides PDO connection as $pdo.
 * - Standard session setup for staff authentication.
 */

// -----------------------------------------------------------------------------
// 1. SESSION AND ACCESS CONTROL
// -----------------------------------------------------------------------------

session_start(); // Start session for user authentication

require 'db_connect.php'; // Database connection

// Only allow access for logged-in accountants and general manager
if (!isset($_SESSION['staff_id']) || !in_array(strtolower($_SESSION['role']), ['accountant', 'general manager'])) {
    // Redirect to login if not authorized
    header("Location: staff-login.php");
    exit();
}

// -----------------------------------------------------------------------------
// 2. HANDLE PAYMENT CONFIRMATION POST REQUEST
// -----------------------------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['payment_id'])) {
    // Get the payment ID from POST and staff info from session
    $payment_id   = $_POST['payment_id'];           // The rental claim payment to confirm
    $confirmed_by = $_SESSION['staff_id'];          // Staff ID for auditing
    $confirmed_at = date('Y-m-d H:i:s');            // Current timestamp

    // Update payment record: set status to 'confirmed', record confirmer and timestamp
    $stmt = $pdo->prepare("
        UPDATE rental_claim_payments
        SET payment_status = 'confirmed',
            confirmed_by = ?,
            confirmed_at = ?
        WHERE payment_id = ?
    ");
    $stmt->execute([$confirmed_by, $confirmed_at, $payment_id]);

    // Redirect back to confirmation page with a success flag (avoids resubmission)
    header("Location: confirm-rental-claim-payments.php?confirmed=1");
    exit();

} else {
    // If not a valid POST or missing payment_id, redirect with error flag
    header("Location: confirm-rental-claim-payments.php?error=1");
    exit();
}
?>
