<?php
/**
 * ============================================================================
 * upload-rental-contract.php
 * ----------------------------------------------------------------------------
 * Handles the upload of signed rental contract files by staff, and updates the 
 * associated contract dates and meeting times for a rental claim.
 *
 * Workflow:
 *   - Ensures the staff member is authenticated.
 *   - Receives POST data: claim ID, contract meeting date, contract start/end, 
 *     and a contract file (PDF only).
 *   - Validates and persists the contract discussion/meeting datetime, contract 
 *     start/end dates, and saves the contract file to the appropriate folder.
 *   - Updates the relevant database fields in the rental_contracts table.
 *   - All operations are atomic and respond with clear session messages.
 *   - Redirects user back to rental-management-claimed-properties.php on finish.
 * ----------------------------------------------------------------------------
 * Requirements:
 *   - Bootstrap 5.3.6 classes used on all message output (handled by target page).
 * ============================================================================
 */

session_start();
require 'db_connect.php';

// -----------------------------------------------------------------------------
// Enforce staff authentication before proceeding.
// -----------------------------------------------------------------------------
if (!isset($_SESSION['staff_id'])) {
    header("Location: staff-login.php");
    exit();
}

// -----------------------------------------------------------------------------
// Only handle POST requests.
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // -------------------------------------------------------------------------
    // 1. Gather and validate input
    // -------------------------------------------------------------------------
    $claimId           = $_POST['claim_id'] ?? null;
    $contractMeeting   = trim($_POST['contract_discussion_datetime'] ?? '');
    $contractStart     = trim($_POST['contract_start_date'] ?? '');
    $contractEnd       = trim($_POST['contract_end_date'] ?? '');
    $uploadedContract  = $_FILES['claim_contract_file'] ?? null;

    if (!$claimId) {
        $_SESSION['error'] = "Missing claim ID.";
        header("Location: rental-management-claimed-properties.php");
        exit();
    }

    try {
        // ---------------------------------------------------------------------
        // 2. Ensure a rental_contracts row exists for this claim
        // ---------------------------------------------------------------------
        $exists = $pdo->prepare("SELECT 1 FROM rental_contracts WHERE claim_id = ?");
        $exists->execute([$claimId]);
        if (!$exists->fetchColumn()) {
            $insert = $pdo->prepare("INSERT INTO rental_contracts (claim_id) VALUES (?)");
            $insert->execute([$claimId]);
        }

        // ---------------------------------------------------------------------
        // 3. Update contract meeting datetime if provided
        // ---------------------------------------------------------------------
        if ($contractMeeting) {
            $update = $pdo->prepare("
                UPDATE rental_contracts 
                SET contract_discussion_datetime = ? 
                WHERE claim_id = ?
            ");
            $update->execute([$contractMeeting, $claimId]);
            $_SESSION['success'] = "Meeting date saved.";
        }

        // ---------------------------------------------------------------------
        // 4. Update contract start/end dates if both provided
        // ---------------------------------------------------------------------
        if ($contractStart && $contractEnd) {
            $update = $pdo->prepare("
                UPDATE rental_contracts 
                SET contract_start_date = ?, contract_end_date = ? 
                WHERE claim_id = ?
            ");
            $update->execute([$contractStart, $contractEnd, $claimId]);
            $_SESSION['success'] = "Contract dates saved.";
        }

        // ---------------------------------------------------------------------
        // 5. Handle PDF contract upload (if file provided)
        // ---------------------------------------------------------------------
        if ($uploadedContract && $uploadedContract['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($uploadedContract['name'], PATHINFO_EXTENSION));
            if ($ext !== 'pdf') {
                $_SESSION['error'] = "Only PDF files are allowed.";
                header("Location: rental-management-claimed-properties.php");
                exit();
            }

            // Consistent path: /uploads/clients/claims/{claimId}/rental_contract_signed.pdf
            $uploadDir = __DIR__ . "/uploads/clients/claims/{$claimId}/";
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $filePath = $uploadDir . "rental_contract_signed.pdf";
            if (!move_uploaded_file($uploadedContract['tmp_name'], $filePath)) {
                $_SESSION['error'] = "File upload failed. Please try again.";
                header("Location: rental-management-claimed-properties.php");
                exit();
            }

            // Store path relative to project root for consistency in DB
            $dbFilePath = "uploads/clients/claims/{$claimId}/rental_contract_signed.pdf";
            $update = $pdo->prepare("
                UPDATE rental_contracts 
                SET contract_signed_path = ? 
                WHERE claim_id = ?
            ");
            $update->execute([$dbFilePath, $claimId]);

            $_SESSION['success'] = "Contract uploaded successfully.";
        }

    } catch (PDOException $e) {
        // Log error for debugging (never display raw error to user)
        error_log("Rental contract upload error: " . $e->getMessage());
        $_SESSION['error'] = "Database error. Please contact technical support.";
    }

    // -------------------------------------------------------------------------
    // Always redirect to claimed properties page for consistent Bootstrap output
    // -------------------------------------------------------------------------
    header("Location: rental-management-claimed-properties.php");
    exit();
}

// No direct GET access permitted.
header("Location: rental-management-claimed-properties.php");
exit();
?>
