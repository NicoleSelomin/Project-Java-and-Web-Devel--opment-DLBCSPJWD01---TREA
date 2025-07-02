<?php
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
$claim_id   = isset($_POST['claim_id'])   ? intval($_POST['claim_id'])   : 0;
$invoice_id = isset($_POST['invoice_id']) ? intval($_POST['invoice_id']) : 0;
$message    = trim($_POST['message'] ?? '');
$staff_id   = $_SESSION['staff_id'];

// Provide a default message if none submitted (rare, but safe)
$default_message = "Final Warning:\n\nThis is your final reminder regarding your overdue rent payment. "
    . "If payment proof is not submitted immediately, a formal notice to vacate will be issued as required by your lease agreement.\n\n"
    . "Please act now to avoid further consequences.";

if ($claim_id <= 0 || $invoice_id <= 0) {
    header("Location: confirm-rental-management-invoices.php?error=invalid_input");
    exit();
}
if ($message === '') $message = $default_message;

// ------------------------------------------------------------------
// Prevent duplicate manual/final warnings for same invoice (recommended)
// ------------------------------------------------------------------
$stmtCheck = $pdo->prepare(
    "SELECT COUNT(*) FROM rent_warnings WHERE invoice_id = ? AND warning_type = 'manual'"
);
$stmtCheck->execute([$invoice_id]);
if ($stmtCheck->fetchColumn() > 0) {
    header("Location: confirm-rental-management-invoices.php?error=already_final_warning");
    exit();
}

// ------------------------------------------------------------------
// Insert Manual Warning into rent_warnings Table
// ------------------------------------------------------------------
$stmt = $pdo->prepare(
    "INSERT INTO rent_warnings (claim_id, invoice_id, warning_type, message, notified_by) VALUES (?, ?, 'manual', ?, ?)"
);
$stmt->execute([$claim_id, $invoice_id, $message, $staff_id]);

// ------------------------------------------------------------------
// Redirect back to the confirmation page
// ------------------------------------------------------------------
header("Location: confirm-rental-management-invoices.php?warning=sent");
exit();
?>
