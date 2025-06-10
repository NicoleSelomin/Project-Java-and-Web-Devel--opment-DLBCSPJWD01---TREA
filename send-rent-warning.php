<?php
/**
 * ============================================================================
 * send-rent-warning.php — TREA Real Estate Platform
 * ---------------------------------------------------------------------
 * Issue Manual Rent Warning (POST Handler)
 * ---------------------------------------------------------------------
 * Allows an accountant to submit a manual rent warning for a specific
 * rental claim. The warning is inserted into the rent_warnings table.
 * 
 * - Only accessible to staff with role 'accountant'
 * - Validates claim ID and message
 * - Saves warning type as 'manual'
 * - Redirects back to the rent claim confirmation page
 * ---------------------------------------------------------------------
 */

session_start();
require 'db_connect.php';

// ------------------------------------------------------------------
// Authentication: Only allow logged-in accountants
// ------------------------------------------------------------------
if (!isset($_SESSION['staff_id']) || strtolower($_SESSION['role']) !== 'accountant') {
    header("Location: staff-login.php");
    exit();
}

// ------------------------------------------------------------------
// Validate and Sanitize Input
// ------------------------------------------------------------------
$claim_id = isset($_POST['claim_id']) ? intval($_POST['claim_id']) : 0;
$message = trim($_POST['message'] ?? '');
$staff_id = $_SESSION['staff_id'];

if ($claim_id <= 0 || $message === '') { 
    // Prevent empty messages or invalid claim IDs
    header("Location: confirm-rental-claim-payments.php?error=invalid_input");
    exit();
}

// ------------------------------------------------------------------
// Insert Warning into rent_warnings Table
// ------------------------------------------------------------------
$stmt = $pdo->prepare(
    "INSERT INTO rent_warnings (claim_id, warning_type, message, notified_by) VALUES (?, 'manual', ?, ?)"
);
$stmt->execute([$claim_id, $message, $staff_id]);

// ------------------------------------------------------------------
// Redirect back to the confirmation page
// ------------------------------------------------------------------
header("Location: confirm-rental-claim-payments.php?warning=sent");
exit();
?>
