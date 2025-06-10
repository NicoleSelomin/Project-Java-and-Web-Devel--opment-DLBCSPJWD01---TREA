<?php
/**
 * --------------------------------------------------------------------------------
 * confirm-owner-fee.php
 * ---------------------------------------------------------------------------------
 * 
 * Owner Rental Fee Confirmation Handler
 *
 * This script processes POST requests from property owners confirming receipt of rental fee transfers.
 * - Ensures only authenticated owners can access (via check-user-session.php).
 * - Updates the relevant fee record in the database to mark it as confirmed by the owner.
 * - Redirects to owner-profile.php upon completion.
 *
 * Dependencies:
 * - check-user-session.php: Handles session and access control for owners.
 * - db_connect.php: Provides PDO database connection as $pdo.
 */

// -----------------------------------------------------------------------------
// 1. SESSION AND ACCESS CONTROL
// -----------------------------------------------------------------------------

require_once 'check-user-session.php'; // Verify owner is logged in and has a valid session
require 'db_connect.php'; // Establish database connection ($pdo)

// -----------------------------------------------------------------------------
// 2. HANDLE FEE CONFIRMATION POST REQUEST
// -----------------------------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fee_id'])) {
    // Sanitize and validate fee_id to ensure it is an integer
    $fee_id = intval($_POST['fee_id']);

    // -------------------------------------------------------------------------
    // Update the rental fee record: mark as confirmed by the owner and set timestamp
    // -------------------------------------------------------------------------
    $stmt = $pdo->prepare("
        UPDATE owner_rental_fees 
        SET owner_confirmed = 1, confirmed_at = NOW() 
        WHERE fee_id = ?
    ");
    $stmt->execute([$fee_id]);

    // -------------------------------------------------------------------------
    // Redirect to owner profile with a success flag to prevent form resubmission
    // -------------------------------------------------------------------------
    header("Location: owner-profile.php?confirmed=1");
    exit();
}
?>
