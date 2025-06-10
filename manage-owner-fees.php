<?php
/**
 * manage-owner-fee.php
 * -------------------------------------
 * Allows the accountant to upload PDF receipts for management, maintenance, tax deduction,
 * and proof of rent transfer for a specific owner rental fee.
 * Only accessible by staff with role 'accountant'.
 * Sends notifications to owner and staff on successful upload.
 * 
 * Requires:
 *   - db_connect.php for database connection
 *   - send-upload-notification.php for notification logic
 *   - Staff must be authenticated as 'accountant'
 *   - GET parameter fee_id
 */

session_start();
require 'db_connect.php';
require_once 'send-upload-notification.php';

// Access control: only 'accountant' can use this page
if (!isset($_SESSION['staff_id']) || strtolower($_SESSION['role']) !== 'accountant') {
    header("Location: staff-login.php");
    exit();
}

// Validate fee_id
if (!isset($_GET['fee_id'])) {
    die("Fee ID missing.");
}
$fee_id = (int) $_GET['fee_id'];

// Fetch fee details, including property and owner info
$stmt = $pdo->prepare("
    SELECT f.*, p.property_name, o.owner_id, u.full_name AS owner_name
    FROM owner_rental_fees f
    JOIN client_claims cc ON f.claim_id = cc.claim_id
    JOIN properties p ON cc.property_id = p.property_id
    JOIN owners o ON p.owner_id = o.owner_id
    JOIN users u ON o.user_id = u.user_id
    WHERE f.fee_id = ?
");
$stmt->execute([$fee_id]);
$fee = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$fee) {
    die("Fee not found.");
}

// Prepare upload directory, ensuring it exists
$owner_id = $fee['owner_id'];
$owner_name = preg_replace('/[^a-z0-9]/i', '_', strtolower($fee['owner_name']));
$property_name = preg_replace('/[^a-z0-9]/i', '_', strtolower($fee['property_name']));
$uploadDir = "uploads/owner/{$owner_id}_{$owner_name}/rental_fees/{$property_name}/";
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Define which fields are uploaded (and their user-facing labels)
$fields = [
    'receipt_management'  => 'Receipt for Management Fee',
    'receipt_maintenance' => 'Receipt for Maintenance Fee',
    'tax_receipt'         => 'Tax Deduction Receipt',
    'transfer_proof'      => 'Proof of Rent Transfer'
];

// Handle form submission: process all file uploads
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($fields as $field => $label) {
        if (!empty($_FILES[$field]) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
            $tmpPath = $_FILES[$field]['tmp_name'];
            $fileName = basename($_FILES[$field]['name']);
            $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            // Enforce PDF-only upload
            if ($ext !== 'pdf') {
                die("Only PDF files are allowed for {$label}.");
            }

            // Move uploaded file to the correct location
            $destPath = $uploadDir . $field . ".pdf";
            if (move_uploaded_file($tmpPath, $destPath)) {
                // Update database with the new file path
                $update = $pdo->prepare("UPDATE owner_rental_fees SET {$field} = ? WHERE fee_id = ?");
                $update->execute([$destPath, $fee_id]);

                // Send notification to owner and accountant (self)
                sendUploadNotification(
                    $pdo,
                    $_SESSION['staff_id'],
                    'staff',
                    'receipt',
                    $label,
                    'Rental Fee Transfer',
                    'property_owner',
                    $owner_id,
                    'owner-rental-management-properties.php'
                );
            }
        }
    }
    // Redirect to manage page after successful upload(s)
    header("Location: manage-owner-rental-fees.php?updated=1");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Upload Rental Fee Documents</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Consistent Bootstrap version -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex flex-column min-vh-100">
    <?php include 'header.php'; ?>
    <main class="container py-5 flex-grow-1">
        <h4 class="mb-4 text-primary">
            Upload Fee Documents for <?= htmlspecialchars($fee['property_name']) ?>
        </h4>
        <form method="POST" enctype="multipart/form-data" class="bg-white p-4 rounded shadow-sm">
            <?php foreach ($fields as $field => $label): ?>
                <div class="mb-3">
                    <label class="form-label">Upload <?= $label ?> (PDF only)</label>
                    <input type="file" name="<?= $field ?>" accept="application/pdf" class="form-control">
                </div>
            <?php endforeach; ?>
            <button type="submit" class="btn btn-success">Submit Documents</button>
            <a href="manage-owner-rental-fees.php" class="btn btn-secondary ms-2">Cancel</a>
        </form>
    </main>
    <?php include 'footer.php'; ?>
    <!-- Bootstrap JS for modal/file/inputs -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
