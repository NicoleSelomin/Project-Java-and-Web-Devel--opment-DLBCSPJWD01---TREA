<?php
/**
 * ----------------------------------------------------------------------------
 * handle-renewal-decision.php
 * ----------------------------------------------------------------------------
 * 
 * Handle Rental Contract Renewal Decision
 *
 * Allows general manager or property manager to accept or reject
 * rental contract renewal requests.
 * - Updates the 'renewal_status' field in rental_contracts.
 * - Redirects back to the claimed properties page after action.
 *
 * Access: General Manager, Property Manager only.
 */

session_start();
require 'db_connect.php';

// -----------------------------------------------------------------------------
// Access Control: Only allow general manager or property manager
// -----------------------------------------------------------------------------
if (
    !isset($_SESSION['staff_id']) ||
    !in_array(strtolower($_SESSION['role']), ['general manager', 'property manager'])
) {
    header("Location: staff-login.php");
    exit();
}

// -----------------------------------------------------------------------------
// Handle POST: Process renewal decision (accept or reject)
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $claim_id = $_POST['claim_id'] ?? null;
    $decision = $_POST['decision'] ?? null;

    if ($claim_id && in_array($decision, ['accepted', 'rejected'])) {
        // Update renewal_status in rental_contracts
        $stmt = $pdo->prepare("
            UPDATE rental_contracts 
            SET renewal_status = ? 
            WHERE claim_id = ?
        ");
        $stmt->execute([$decision, $claim_id]);
    }
}

// -----------------------------------------------------------------------------
// Redirect to claimed rental management properties list
// -----------------------------------------------------------------------------
header("Location: rental-management-claimed-properties.php");
exit();
