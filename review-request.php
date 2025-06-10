<?php
/**
 * --------------------------------------------------------------------
 * review-request.php - Manager/Staff Page
 * --------------------------------------------------------------------
 * This page allows a staff user to review a service application request
 * submitted by a property owner. The reviewer can approve or reject the
 * application. A PDF summary of the review is generated and saved.
 * Only accessible to logged-in staff members.
 * 
 * Features:
 * - Displays all key service request information.
 * - Allows review decision (approve/reject) with comments.
 * - Saves review as a PDF in the correct owner/service folder.
 * - Updates request status and redirects upon completion.
 * --------------------------------------------------------------------
 */

require_once 'libs/dompdf/autoload.inc.php';
session_start();
require 'db_connect.php';

use Dompdf\Dompdf;

// ------------------------------------------------------------------
// Authentication: Block if not logged in as staff
// ------------------------------------------------------------------
if (!isset($_SESSION['staff_id']) || !isset($_SESSION['role'])) {
    header("Location: staff-login.php");
    exit();
}

// ------------------------------------------------------------------
// User Session Data
// ------------------------------------------------------------------
$staff_id = $_SESSION['staff_id'];
$fullName = $_SESSION['full_name'] ?? 'Staff';
$userId = $_SESSION['staff_id'] ?? '';
$profilePicturePath = $_SESSION['profile_picture_path'] ?? 'default.png';

// ------------------------------------------------------------------
// Validate and Sanitize Request ID
// ------------------------------------------------------------------
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "No request selected.";
    exit();
}
$request_id = intval($_GET['id']);

// ------------------------------------------------------------------
// Fetch Main Request Details (joins: services, owners, users)
// ------------------------------------------------------------------
$stmt = $pdo->prepare("
    SELECT r.*, s.service_name, s.slug, u.full_name AS owner_name, o.owner_id
    FROM owner_service_requests r
    JOIN services s ON r.service_id = s.service_id
    JOIN owners o ON r.owner_id = o.owner_id
    JOIN users u ON o.user_id = u.user_id
    WHERE r.request_id = ?
");
$stmt->execute([$request_id]);
$request = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$request) {
    echo "Service request not found.";
    exit();
}

// ------------------------------------------------------------------
// Fetch Service-Specific Details (by service slug)
// ------------------------------------------------------------------
$service_specific = [];
$tableMap = [
    'brokerage' => 'brokerage_details',
    'sale_property_management' => 'sale_property_management_details',
    'architecture_plan_drawing' => 'architecture_plan_details',
    'legal_assistance' => 'legal_assistance_details',
    'construction_supervision' => 'construction_supervision_details'
];

if (isset($tableMap[$request['slug']])) {
    $stmt2 = $pdo->prepare("SELECT * FROM {$tableMap[$request['slug']]} WHERE request_id = ?");
    $stmt2->execute([$request_id]);
    $service_specific = $stmt2->fetch(PDO::FETCH_ASSOC) ?: [];
}

// ------------------------------------------------------------------
// Only allow review if inspection report (if required) is submitted
// ------------------------------------------------------------------
if ($request['slug'] !== 'architecture_plan_drawing' && $request['inspection_result'] === 'pending') {
    echo "<div class='alert alert-warning m-4'>You can't review this request until the agent submits their inspection report.</div>";
    exit();
}

// ------------------------------------------------------------------
// Handle Review Submission (POST)
// ------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $status = $_POST['status'];
    $reviewed_at = date('Y-m-d H:i:s');
    $rejection_reason = trim($_POST['rejection_reason'] ?? '');

    // Get reviewer full name
    $reviewerStmt = $pdo->prepare("SELECT full_name FROM staff WHERE staff_id = ?");
    $reviewerStmt->execute([$staff_id]);
    $reviewer = $reviewerStmt->fetchColumn();

    // Generate PDF summary of review
    $dompdf = new Dompdf();
    $html = "
        <h2>Service Request Review</h2>
        <p><strong>Request ID:</strong> $request_id</p>
        <p><strong>Owner:</strong> ".htmlspecialchars($request['owner_name'])."</p>
        <p><strong>Service:</strong> ".htmlspecialchars($request['service_name'])."</p>
        <p><strong>Property:</strong> ".htmlspecialchars($request['property_name'])." - ".htmlspecialchars($request['location'])."</p>
        <p><strong>Decision:</strong> " . ucfirst($status) . "</p>
        <p><strong>Reviewed At:</strong> $reviewed_at</p>
        <p><strong>Reviewer:</strong> ".htmlspecialchars($reviewer)."</p>
    ";
    if ($status === 'rejected') {
        $html .= "<p><strong>Rejection Reason:</strong><br>" . nl2br(htmlspecialchars($rejection_reason)) . "</p>";
    }
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    // Prepare save directory
    $owner_id = $request['owner_id'];
    $owner_name = preg_replace('/[^a-zA-Z0-9_]/', '_', strtolower($request['owner_name']));
    $service_id = $request['service_id'];
    $service_slug = preg_replace('/[^a-zA-Z0-9_]/', '_', strtolower($request['slug']));
    $request_folder = "uploads/owner/{$owner_id}_{$owner_name}/applications/{$service_id}_{$service_slug}/request_{$request_id}/";
    if (!is_dir($request_folder)) {
        mkdir($request_folder, 0777, true);
    }
    $filePath = $request_folder . "manager_review.pdf";
    file_put_contents($filePath, $dompdf->output());

    // Update owner_service_requests table
    if ($status === 'approved') {
        $stmt = $pdo->prepare("
            UPDATE owner_service_requests 
            SET status = 'approved', final_status = 'approved', reviewed_at = ?, reviewed_by = ?, review_pdf_path = ?
            WHERE request_id = ?
        ");
        $stmt->execute([$reviewed_at, $staff_id, $filePath, $request_id]);
    } else {
        $stmt = $pdo->prepare("
            UPDATE owner_service_requests 
            SET status = 'rejected', final_status = 'rejected', reviewed_at = ?, reviewed_by = ?, review_pdf_path = ?, agent_feedback = ?
            WHERE request_id = ?
        ");
        $stmt->execute([$reviewed_at, $staff_id, $filePath, $rejection_reason, $request_id]);
    }

    header("Location: manage-service-requests.php?reviewed=1");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Standard Meta and Title -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Review Service Request</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css?v=<?= time() ?>">
</head>
<body class="d-flex flex-column min-vh-100 bg-light">

<?php include 'header.php'; ?>

<div class="container-fluid flex-grow-1">
    <div class="row">
        <!-- Sidebar (Responsive, uniform look) -->
        <aside class="col-12 col-md-3 mb-3">
            <button class="btn btn-sm d-md-none mb-3 custom-btn" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarCollapse" aria-expanded="false" aria-controls="sidebarCollapse">
                Menu
            </button>
            <div class="collapse d-md-block" id="sidebarCollapse">
                <div class="sidebar text-center py-3 px-2 border bg-white rounded shadow-sm">
                    <div class="profile-summary mb-4">
                        <img src="<?= htmlspecialchars($profilePicturePath) ?>" alt="Profile Picture" class="rounded-circle mb-2" width="80" height="80">
                        <div><strong><?= htmlspecialchars($fullName) ?></strong></div>
                        <small class="text-muted">ID: <?= htmlspecialchars($userId) ?></small>
                    </div>
                    <a href="notifications.php" class="btn btn-outline-secondary w-100 mb-2">View Notifications</a>
                    <a href="edit-staff-profile.php" class="btn btn-outline-secondary w-100 mb-2">Edit Profile</a>
                    <a href="staff-logout.php" class="btn btn-outline-danger w-100">Logout</a>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="col-12 col-md-9">
            <div class="card border-0 shadow-sm mt-3 mb-4">
                <div class="card-body">
                    <h2 class="h4 mb-4">Review Service Request</h2>
                    <div class="mb-3">
                        <strong>Owner:</strong> <?= htmlspecialchars($request['owner_name']) ?><br>
                        <strong>Service:</strong> <?= htmlspecialchars($request['service_name']) ?><br>
                        <strong>Property Name:</strong> <?= htmlspecialchars($request['property_name']) ?><br>
                        <strong>Location:</strong> <?= htmlspecialchars($request['location']) ?><br>
                        <strong>Description:</strong><br>
                        <?= nl2br(htmlspecialchars($request['property_description'] ?? $service_specific['property_description'] ?? '—')) ?>
                    </div>
                    <?php if (!empty($request['supporting_documents'])): ?>
                        <div class="mb-3">
                            <strong>Uploaded Documents:</strong>
                            <ul class="mb-0">
                            <?php foreach (json_decode($request['supporting_documents'], true) as $doc): ?>
                                <li><a href="<?= htmlspecialchars($doc) ?>" target="_blank"><?= basename($doc) ?></a></li>
                            <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <!-- Review Form -->
                    <form method="POST" class="mb-4">
                        <div class="mb-2">
                            <label class="form-label fw-semibold">Decision:</label><br>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="status" value="approved" id="status-approved" required>
                                <label class="form-check-label" for="status-approved">Approve</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="status" value="rejected" id="status-rejected" required>
                                <label class="form-check-label" for="status-rejected">Reject</label>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="rejection_reason" class="form-label">Rejection Reason (if rejecting):</label>
                            <textarea name="rejection_reason" id="rejection_reason" class="form-control" rows="3"></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Submit Decision</button>
                        <a href="manage-agent-assignments.php" class="btn btn-link">Back to Assignments</a>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include 'footer.php'; ?>
<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
