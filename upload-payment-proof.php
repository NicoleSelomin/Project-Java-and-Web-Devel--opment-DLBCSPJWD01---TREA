<?php
/**
 * ================================================================
 * upload-payment-proof.php — TREA Real Estate Platform
 * ------------------------------------------------
 * Rental Claim Payment Proof Upload Handler
 * ----------------------------------------
 * Handles secure upload of client payment proof (PDF or image)
 * for rental claim payments in the TREA platform.
 *
 * - Accepts POST only (with payment_id and file)
 * - Saves proof under /uploads/payment_proofs/
 * - Updates the rental_claim_payments table
 * - Sets a message for client feedback
 * - Redirects back to the relevant claim management page
 */

session_start();
require 'db_connect.php';

// ------------------------------------------
// 1. Only process POST with expected fields
// ------------------------------------------
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_FILES['payment_proof'], $_POST['payment_id'])
) {
    $payment_id = (int) $_POST['payment_id'];

    // -------------------------------
    // 2. Validate and Save Uploaded File
    // -------------------------------
    $uploadDir = 'uploads/payment_proofs/';

    // Ensure the upload directory exists (recursively)
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0775, true);
    }

    $originalName = basename($_FILES['payment_proof']['name']);
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    // Optional: Restrict allowed file types for security (PDF, JPG, PNG)
    $allowed = ['pdf', 'jpg', 'jpeg', 'png'];
    if (!in_array($ext, $allowed)) {
        $_SESSION['message'] = "File type not allowed. Only PDF, JPG, PNG accepted.";
        header("Location: client-claimed-rental-management.php");
        exit();
    }

    // Generate unique file name (timestamp + sanitized original name)
    $fileName = time() . '_' . preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $originalName);
    $targetPath = $uploadDir . $fileName;

    // Attempt to move uploaded file
    if (move_uploaded_file($_FILES['payment_proof']['tmp_name'], $targetPath)) {
        // ---------------------------
        // 3. Update payment record in DB
        // ---------------------------
        $stmt = $pdo->prepare(
            "UPDATE rental_claim_payments SET payment_proof = ? WHERE payment_id = ?"
        );
        $stmt->execute([$targetPath, $payment_id]);
        $_SESSION['message'] = "Payment proof uploaded successfully.";
    } else {
        $_SESSION['message'] = "Failed to upload payment proof. Please try again.";
    }

    // ---------------------------
    // 4. Redirect for client feedback
    // ---------------------------
    header("Location: client-claimed-rental-management.php");
    exit();
}

// ------------------------------------------
// 5. Fallback for unexpected access
// ------------------------------------------
// Optionally redirect or show an error (no direct access allowed)
header("Location: client-claimed-rental-management.php");
exit();

?>
