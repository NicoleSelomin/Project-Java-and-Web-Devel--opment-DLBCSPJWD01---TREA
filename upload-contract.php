<?php
/**
 * ================================================================
 * upload-contract.php — TREA Real Estate Platform
 * ------------------------------------------------
 * Handles uploading and recording of the signed owner contract
 * for a specific owner service request.
 * - Validates request and permissions.
 * - Accepts only PDF files.
 * - Saves file to structured uploads/ directory.
 * - Records meeting datetime and file path in DB.
 * - Sets session success or error for Bootstrap feedback.
 * - Always redirects to manage-service-requests.php.
 *
 * All output/errors handled via session and managed in the
 * destination page. Consistent with Bootstrap 5.3 usage.
 * ================================================================
 */

session_start();
require_once 'db_connect.php';

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = "Invalid request method.";
    header("Location: manage-service-requests.php");
    exit();
}

// Gather and validate input
$requestId = $_POST['request_id'] ?? null;
$meeting   = trim($_POST['owner_contract_meeting'] ?? '');
$uploadedContract = $_FILES['owner_contract_file'] ?? null;

if (!$requestId) {
    $_SESSION['error'] = "Missing request ID.";
    header("Location: manage-service-requests.php");
    exit();
}

try {
    // ------------------------------------------------------------
    // 1. Fetch owner, service, and user information
    // ------------------------------------------------------------
    $stmt = $pdo->prepare("
        SELECT r.owner_id, s.service_id, s.slug, u.full_name
        FROM owner_service_requests r
        JOIN services s ON r.service_id = s.service_id
        JOIN owners o ON r.owner_id = o.owner_id
        JOIN users u ON o.user_id = u.user_id
        WHERE r.request_id = ?
    ");
    $stmt->execute([$requestId]);
    $info = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$info) {
        $_SESSION['error'] = "Request not found.";
        header("Location: manage-service-requests.php");
        exit();
    }

    // ------------------------------------------------------------
    // 2. Build the upload target directory
    // ------------------------------------------------------------
    $ownerFolder   = $info['owner_id'] . '_' . preg_replace('/[^a-z0-9_]/i', '_', $info['full_name']);
    $serviceFolder = $info['service_id'] . '_' . $info['slug'];
    $targetDir     = "uploads/owner/{$ownerFolder}/applications/{$serviceFolder}/request_{$requestId}/";

    if (!is_dir($targetDir) && !mkdir($targetDir, 0777, true)) {
        $_SESSION['error'] = "Failed to create upload folder.";
        header("Location: manage-service-requests.php");
        exit();
    }

    // ------------------------------------------------------------
    // 3. Update meeting datetime if provided
    // ------------------------------------------------------------
    if ($meeting) {
        $stmtMeeting = $pdo->prepare("UPDATE owner_service_requests SET owner_contract_meeting = ? WHERE request_id = ?");
        $stmtMeeting->execute([$meeting, $requestId]);
    }

    // ------------------------------------------------------------
    // 4. Handle contract file upload (PDF only)
    // ------------------------------------------------------------
    if (
        $uploadedContract
        && $uploadedContract['error'] === UPLOAD_ERR_OK
        && $uploadedContract['size'] > 0
    ) {
        $ext = strtolower(pathinfo($uploadedContract['name'], PATHINFO_EXTENSION));
        if ($ext !== 'pdf') {
            $_SESSION['error'] = "Only PDF files are allowed for contract upload.";
            header("Location: manage-service-requests.php");
            exit();
        }

        $contractFileName = 'owner_contract.pdf';
        $destination      = $targetDir . $contractFileName;

        // Move the uploaded file
        if (!move_uploaded_file($uploadedContract['tmp_name'], $destination)) {
            $_SESSION['error'] = "Failed to save the uploaded contract file.";
            header("Location: manage-service-requests.php");
            exit();
        }

        // Update DB with relative path to contract file
        $stmtFile = $pdo->prepare("UPDATE owner_service_requests SET owner_contract_path = ? WHERE request_id = ?");
        $stmtFile->execute([$destination, $requestId]);

        $_SESSION['contract_uploaded'] = true;
    }

} catch (PDOException $e) {
    error_log("Upload contract error (request_id $requestId): " . $e->getMessage());
    $_SESSION['error'] = "A database error occurred while uploading the contract.";
}

// Always redirect for UX/UI consistency
header("Location: manage-service-requests.php");
exit();
