<?php
/*
|--------------------------------------------------------------------------
| owner-service-requests.php
|--------------------------------------------------------------------------
| Enables property owners to view, track, and manage service requests
| submitted through the platform. Owners can upload payment proofs,
| check application statuses, view invoices, and download relevant
| documents.
|
| Standards:
| - Consistent and responsive layout with Bootstrap 5.3.6
|--------------------------------------------------------------------------
*/

// Session initialization and required file inclusions
session_start();
require_once 'check-user-session.php';
require 'db_connect.php';

// Retrieve current user details
$userId = $_SESSION['owner_id'];
$fullName = $_SESSION['user_name'] ?? 'Unknown Owner';

// Handle payment proof upload submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['payment_proof'])) {
    $requestId = $_POST['request_id'] ?? null;
    $paymentType = in_array($_POST['payment_type'] ?? '', ['application', 'service']) ? $_POST['payment_type'] : 'application';

    if (empty($requestId)) {
        $_SESSION['error_message'] = "Missing request ID.";
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }

    // Retrieve service request details for file upload structure
    $infoStmt = $pdo->prepare("
        SELECT r.owner_id, u.full_name, r.service_id, s.slug
        FROM owner_service_requests r
        JOIN owners o ON r.owner_id = o.owner_id
        JOIN users u ON o.user_id = u.user_id
        JOIN services s ON r.service_id = s.service_id
        WHERE r.request_id = ?
    ");
    $infoStmt->execute([$requestId]);
    $info = $infoStmt->fetch(PDO::FETCH_ASSOC);

    if (!$info) {
        $_SESSION['error_message'] = "Request info not found.";
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }

    // Define and create upload directory
    $ownerFolder = $info['owner_id'] . '_' . preg_replace('/[^a-z0-9_]/i', '_', $info['full_name']);
    $serviceFolder = $info['service_id'] . '_' . $info['slug'];
    $targetDir = "uploads/owner/{$ownerFolder}/applications/{$serviceFolder}/request_{$requestId}/";

    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    // Save uploaded file securely
    $originalName = basename($_FILES['payment_proof']['name']);
    $timestamp = time();
    $sanitizedFile = "{$paymentType}_payment_proof_{$timestamp}_" . preg_replace('/[^a-zA-Z0-9\._-]/', '_', $originalName);
    $targetPath = $targetDir . $sanitizedFile;

    if (move_uploaded_file($_FILES['payment_proof']['tmp_name'], $targetPath)) {
        $stmt = $pdo->prepare("
            UPDATE service_request_payments 
            SET payment_proof = ?, payment_status = 'pending', updated_at = NOW()
            WHERE request_id = ? AND payment_type = ?
        ");
        $stmt->execute([$targetPath, $requestId, $paymentType]);

        $_SESSION['success_message'] = ucfirst($paymentType) . " payment proof uploaded successfully.";
    } else {
        $_SESSION['error_message'] = "File upload failed.";
    }

    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}

// Retrieve service request data
$stmt = $pdo->prepare("SELECT r.*, s.service_name, s.slug,
    app.invoice_path AS application_invoice_path,
    app.payment_proof AS application_payment_proof,
    app.payment_status AS application_payment_status,
    serv.invoice_path AS service_invoice_path,
    serv.payment_proof AS service_payment_proof,
    serv.payment_status AS service_payment_status
FROM owner_service_requests r
JOIN services s ON r.service_id = s.service_id
LEFT JOIN service_request_payments app ON app.request_id = r.request_id AND app.payment_type = 'application'
LEFT JOIN service_request_payments serv ON serv.request_id = r.request_id AND serv.payment_type = 'service'
WHERE r.owner_id = ?
ORDER BY r.submitted_at DESC");
$stmt->execute([$userId]);
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Profile picture setup
$profilePicturePath = (!empty($owner['profile_picture']) && file_exists($owner['profile_picture']))
    ? $owner['profile_picture']
    : 'default.png';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Owner Service Requests - TREA</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css?v=<?= time() ?>">
</head>
<body class="bg-light">

<?php include 'header.php'; ?>

<main class="container py-5">
    <h2 class="text-primary mb-4">Service Requests – <?= htmlspecialchars($fullName) ?></h2>

    <!-- Display session messages -->
    <?php if (!empty($_SESSION['success_message'])): ?>
        <div class="alert alert-success"><?= $_SESSION['success_message'] ?></div>
        <?php unset($_SESSION['success_message']); ?>
    <?php elseif (!empty($_SESSION['error_message'])): ?>
        <div class="alert alert-danger"><?= $_SESSION['error_message'] ?></div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <!-- Service Requests Table -->
    <div class="table-responsive">
        <table class="table table-bordered">
            <thead>
                <tr><th>Service</th><th>Application Invoice</th><th>Status</th></tr>
            </thead>
            <tbody>
                <?php foreach ($requests as $r): ?>
                    <tr>
                        <td><?= htmlspecialchars($r['service_name']) ?></td>
                        <td><?= $r['application_invoice_path'] ? '<a href="' . htmlspecialchars($r['application_invoice_path']) . '">View</a>' : 'Pending' ?></td>
                        <td><?= htmlspecialchars(ucfirst($r['application_payment_status'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <a href="owner-profile.php" class="btn btn-secondary mt-3">Back to Profile</a>
</main>

<?php include 'footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>