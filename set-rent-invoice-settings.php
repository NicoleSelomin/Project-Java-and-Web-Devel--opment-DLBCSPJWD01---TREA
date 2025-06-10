<?php
/**
 * ----------------------------------------------------------------------
 * set-rent-invoice-settings.php
 * ----------------------------------------------------------------------
 * Handles POST request from accountant to set recurring rent invoice
 * parameters for a rental claim, and generates future invoices.
 *
 * - Accessible only to staff with the 'accountant' role.
 * - Updates invoice settings for the claim.
 * - Generates future invoices based on frequency and contract end date.
 * - Redirects back with session messages.
 * ----------------------------------------------------------------------
 */

session_start();
require 'db_connect.php';

// ----------------------------------------------------------------------
// Authentication: Only allow accountants
// ----------------------------------------------------------------------
if (!isset($_SESSION['staff_id']) || strtolower($_SESSION['role']) !== 'accountant') {
    header("Location: staff-login.php");
    exit();
}

// ----------------------------------------------------------------------
// Handle POST: Validate, process, and update invoice settings
// ----------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $claimId     = $_POST['claim_id'] ?? null;
    $invoiceDate = $_POST['invoice_date'] ?? null;
    $frequency   = $_POST['payment_frequency'] ?? null;
    $dueDays     = isset($_POST['due_date']) ? (int)$_POST['due_date'] : null;
    $penalty     = isset($_POST['penalty_rate']) ? (float)$_POST['penalty_rate'] : null;
    $amount      = isset($_POST['rent_fee']) ? (float)$_POST['rent_fee'] : null;

    // Basic required field validation
    if (!$claimId || !$invoiceDate || !$frequency || !$dueDays || !$penalty || !$amount) {
        $_SESSION['message'] = "Please fill all required fields.";
        $_SESSION['message_type'] = "danger";
        header("Location: confirm-rental-management-invoices.php");
        exit();
    }

    try {
        // ------------------------------------------------------------------
        // 1. Update the invoice settings for the claim
        // ------------------------------------------------------------------
        $stmt = $pdo->prepare("
            UPDATE rental_recurring_invoices
            SET invoice_date = ?, payment_frequency = ?, due_date = ?, penalty_rate = ?, amount = ?
            WHERE claim_id = ?
        ");
        $stmt->execute([$invoiceDate, $frequency, $dueDays, $penalty, $amount, $claimId]);

        // ------------------------------------------------------------------
        // 2. Get contract end date to calculate invoice schedule
        // ------------------------------------------------------------------
        $contractEndStmt = $pdo->prepare("SELECT contract_end_date FROM rental_contracts WHERE claim_id = ?");
        $contractEndStmt->execute([$claimId]);
        $contract = $contractEndStmt->fetch(PDO::FETCH_ASSOC);

        if ($contract && $contract['contract_end_date']) {
            $start = new DateTime($invoiceDate);
            $end   = new DateTime($contract['contract_end_date']);
            $now   = new DateTime();

            // ------------------------------------------------------------------
            // Only generate future invoices up to contract end date
            // ------------------------------------------------------------------
            if ($now < $end) {
                $interval = match (strtolower($frequency)) {
                    'daily'     => new DateInterval('P1D'),
                    'monthly'   => new DateInterval('P1M'),
                    'quarterly' => new DateInterval('P3M'),
                    'yearly'    => new DateInterval('P1Y'),
                    default     => null,
                };

                if ($interval) {
                    $period = new DatePeriod($start, $interval, (clone $end)->modify('+1 day'));

                    // Insert each invoice record (skip if already passed)
                    $insert = $pdo->prepare("
                        INSERT INTO rental_recurring_invoices
                        (claim_id, invoice_date, due_date, amount, penalty_rate, payment_frequency, payment_status)
                        VALUES (?, ?, ?, ?, ?, ?, 'pending')
                    ");
                    foreach ($period as $date) {
                        if ($date < $now) continue; // Only future invoices
                        $invoiceDateStr = $date->format('Y-m-d');
                        $dueDateStr     = (clone $date)->modify("+{$dueDays} days")->format('Y-m-d');
                        $insert->execute([$claimId, $invoiceDateStr, $dueDateStr, $amount, $penalty, $frequency]);
                    }
                }
            }
        }

        $_SESSION['message'] = "Recurring invoice settings saved and invoices generated.";
        $_SESSION['message_type'] = "success";

    } catch (PDOException $e) {
        error_log("Recurring invoice setup error: " . $e->getMessage());
        $_SESSION['message'] = "Database error while saving invoice settings.";
        $_SESSION['message_type'] = "danger";
    }

    header("Location: confirm-rental-management-invoices.php");
    exit();
}
