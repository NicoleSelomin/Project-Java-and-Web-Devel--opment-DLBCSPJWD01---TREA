<?php
/**
 * ================================================================
 * upload-inspection-report.php — TREA Real Estate Platform
 * ------------------------------------------------
 * Agent Rental Claim Inspection Report Upload Handler
 * --------------------------------------------------
 * For the TREA real estate platform.
 * 
 * Handles secure upload of PDF inspection reports (initial/final)
 * by field agents for rental claim assignments.
 * 
 * - Accepts POST only, with claim_id, inspection_type, and PDF file.
 * - Validates session (field agent only).
 * - Checks fields, file type, and errors.
 * - Saves to structured upload directory.
 * - Updates appropriate column in client_claims table.
 * - Redirects with appropriate status.
 */

session_start();
require 'db_connect.php';

// -------------------------
// 1. ACCESS CONTROL
// Only logged-in field agents can use this handler.
// -------------------------
if (!isset($_SESSION['staff_id']) || strtolower($_SESSION['role']) !== 'field agent') {
    header("Location: staff-login.php");
    exit();
}

// -------------------------
// 2. PROCESS FILE UPLOAD (POST only)
// -------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Extract and sanitize expected POST fields
    $claim_id = isset($_POST['claim_id']) ? (int) $_POST['claim_id'] : 0;
    $inspection_type = $_POST['inspection_type'] ?? '';
    $file = $_FILES['report_file'] ?? null;

    // Validate essential fields and values
    if (
        !$claim_id ||
        !$file ||
        !in_array($inspection_type, ['initial', 'final'], true)
    ) {
        header("Location: agent-assignments.php?error=missing_fields");
        exit();
    }

    // Check for upload errors and enforce PDF-only uploads
    if (
        $file['error'] !== UPLOAD_ERR_OK ||
        strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'pdf' ||
        $file['type'] !== 'application/pdf'
    ) {
        header("Location: agent-assignments.php?error=invalid_file");
        exit();
    }

    // -------------------------
    // 3. SAVE FILE TO STRUCTURED FOLDER
    // (Platform: /uploads/agent_reports/rental_claim_{claim_id}/)
    // -------------------------
    $upload_dir = "uploads/agent_reports/rental_claim_$claim_id/";

    // Ensure target directory exists (recursive, wide permissions for demo—tighten for production)
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    // Generate unique, informative filename
    $filename = sprintf('%s_inspection_report_%d.pdf', $inspection_type, time());
    $destination = $upload_dir . $filename;

    // Move the uploaded file to its destination
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        header("Location: agent-assignments.php?error=upload_failed");
        exit();
    }

    // -------------------------
    // 4. UPDATE DATABASE
    // Store path in correct client_claims column
    // initial => meeting_report_path, final => final_inspection_report_path
    // -------------------------
    $column = ($inspection_type === 'initial')
        ? 'meeting_report_path'
        : 'final_inspection_report_path';

    $stmt = $pdo->prepare("UPDATE client_claims SET $column = ? WHERE claim_id = ?");
    $stmt->execute([$destination, $claim_id]);

    // Redirect back to assignments page with success flag
    header("Location: agent-assignments.php?upload=success");
    exit();
}

// -------------------------
// 5. INVALID ACCESS METHOD
// Redirect all other requests safely
// -------------------------
header("Location: agent-assignments.php");
exit();

?>
