<?php
/**
 * ================================================================
 * upload-payment-proof.php — TREA Real Estate Platform
 * ------------------------------------------------
 * Handles secure upload of client payment proof (PDF or image)
 * - Stores proof in: uploads/clients/{client_id}_{client_name}/reserved_properties/{property_id}_{property_name}/
 * - Updates rental_claim_payments (or rental_recurring_invoices if invoice_id is present)
 * - Redirects to client-claimed-rental-management.php
 */

session_start();
require 'db_connect.php';

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_FILES['payment_proof']) &&
    (isset($_POST['payment_id']) || isset($_POST['invoice_id']))
) {
    // Get user info for folder structure
    $client_id = $_SESSION['client_id'] ?? 0;
    $client_name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $_SESSION['user_name'] ?? 'client');
    $property_id = '';
    $property_name = '';
    $target_table = '';
    $target_id_field = '';
    $target_id_value = 0;

    // --- 1. Determine upload target and fetch property info ---
    if (isset($_POST['payment_id'])) {
        // Payment proof for claim payment
        $target_table = 'rental_claim_payments';
        $target_id_field = 'payment_id';
        $target_id_value = (int)$_POST['payment_id'];
        // Get claim info for folder naming
        $stmt = $pdo->prepare("SELECT rcp.*, cc.property_id, p.property_name
            FROM rental_claim_payments rcp
            JOIN client_claims cc ON rcp.claim_id = cc.claim_id
            JOIN properties p ON cc.property_id = p.property_id
            WHERE rcp.payment_id = ?");
        $stmt->execute([$target_id_value]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $property_id = $row['property_id'];
            $property_name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $row['property_name']);
        }
    } elseif (isset($_POST['invoice_id'])) {
        // Payment proof for recurring invoice
        $target_table = 'rental_recurring_invoices';
        $target_id_field = 'invoice_id';
        $target_id_value = (int)$_POST['invoice_id'];
        $stmt = $pdo->prepare("SELECT ri.*, cc.property_id, p.property_name
            FROM rental_recurring_invoices ri
            JOIN client_claims cc ON ri.claim_id = cc.claim_id
            JOIN properties p ON cc.property_id = p.property_id
            WHERE ri.invoice_id = ?");
        $stmt->execute([$target_id_value]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $property_id = $row['property_id'];
            $property_name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $row['property_name']);
        }
    }

    if (!$property_id || !$property_name || !$client_id) {
        $_SESSION['message'] = "Upload failed: property/client info missing.";
        header("Location: client-claimed-rental-management.php");
        exit();
    }

    // --- 2. Directory Structure ---
    $uploadDir = "uploads/clients/{$client_id}_{$client_name}/reserved_properties/{$property_id}_{$property_name}/";
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    // --- 3. Validate & Save File ---
    $originalName = basename($_FILES['payment_proof']['name']);
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $allowed = ['pdf', 'jpg', 'jpeg', 'png'];
    if (!in_array($ext, $allowed)) {
        $_SESSION['message'] = "File type not allowed. Only PDF, JPG, PNG accepted.";
        header("Location: client-claimed-rental-management.php");
        exit();
    }

    $fileName = 'payment_proof_' . date('Ymd_His') . '_' . uniqid() . '.' . $ext;
    $targetPath = $uploadDir . $fileName;

    if (move_uploaded_file($_FILES['payment_proof']['tmp_name'], $targetPath)) {
        // Store relative path for serving in browser
        $relativePath = $targetPath;
        // --- 4. Update DB ---
        $sql = "UPDATE `$target_table` SET payment_proof = ? WHERE $target_id_field = ?";
        $pdo->prepare($sql)->execute([$relativePath, $target_id_value]);
        $_SESSION['message'] = "Payment proof uploaded successfully.";
    } else {
        $_SESSION['message'] = "Failed to upload payment proof. Please try again.";
    }

    header("Location: client-claimed-rental-management.php");
    exit();
}

// Fallback
header("Location: client-claimed-rental-management.php");
exit();
?>
