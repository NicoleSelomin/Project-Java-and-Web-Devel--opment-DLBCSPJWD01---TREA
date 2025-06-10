<?php
/**
 * ============================================================================
 * staff-profile.php — TREA Real Estate Platform
 * ------------------------------------------------
 * Staff profile dashboard for the TREA platform.
 * 
 * Responsibilities:
 * - Displays staff profile, role, and navigation dashboard.
 * - Shows current profile picture (or default if missing).
 * - Provides navigation links according to staff role.
 * - Responsive, consistent Bootstrap 5.3.6 layout.
 * - Enforces session-based authentication.
 * 
 * Expected context: staff session (staff_id, full_name, role).
 */

session_start();
require 'db_connect.php';

// Redirect to login if not authenticated
if (!isset($_SESSION['staff_id'])) {
    header("Location: staff-login.php");
    exit();
}

$fullName = $_SESSION['full_name'];
$role = $_SESSION['role'] ?? '';
$userId = $_SESSION['staff_id'];

// Fetch staff profile picture
$stmt = $pdo->prepare("
    SELECT s.profile_picture 
    FROM staff s
    WHERE s.staff_id = ?
");
$stmt->execute([$userId]);
$staff = $stmt->fetch(PDO::FETCH_ASSOC);

// Use default image if no profile picture is set or file is missing
$profilePicturePath = (!empty($staff['profile_picture']) && file_exists($staff['profile_picture']))
    ? $staff['profile_picture']
    : 'default.png';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Meta & Title -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Staff Profile</title>
    <!-- Bootstrap 5.3.6 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- TREA Custom Styles (forced refresh) -->
    <link rel="stylesheet" href="styles.css?v=<?= time() ?>">
</head>
<body class="d-flex flex-column min-vh-100 bg-light">

<?php include 'header.php'; ?>

<!-- Page Content -->
<div class="container-fluid flex-grow-1 py-4">
    <div class="row">

        <!-- Sidebar (collapsible on mobile) -->
        <nav class="col-12 col-md-3 mb-4 mb-md-0">
            <!-- Mobile collapse toggle (hidden on md+) -->
            <button class="btn btn-outline-secondary btn-sm d-md-none mb-3 w-100"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#sidebarMenu"
                aria-expanded="false"
                aria-controls="sidebarMenu">
                Menu
            </button>
            <div class="collapse d-md-block" id="sidebarMenu">
                <div class="bg-white rounded shadow-sm py-4 px-3">
                    <!-- Profile Image and Summary -->
                    <div class="text-center mb-4">
                        <img src="<?= htmlspecialchars($profilePicturePath) ?>" 
                             alt="Profile Picture"
                             class="rounded-circle mb-3"
                             style="width:110px; height:110px; object-fit:cover; border:2px solid #e9ecef;">
                        <div class="fw-semibold"><?= htmlspecialchars($fullName) ?></div>
                        <div class="text-muted small mb-2">Staff ID: <?= htmlspecialchars($userId) ?></div>
                        <!-- Profile Actions -->
                        <a href="notifications.php" class="btn btn-outline-primary btn-sm w-100 mb-2">View Notifications</a>
                        <a href="edit-staff-profile.php" class="btn btn-outline-secondary btn-sm w-100 mb-2">Edit Profile</a>
                        <a href="staff-logout.php" class="btn btn-outline-danger btn-sm w-100">Logout</a>
                    </div>
                </div>
            </div>
        </nav>
        <!-- End Sidebar -->

        <!-- Main Dashboard Content -->
        <main class="col-12 col-md-9">
            <div class="mb-4 p-4 bg-white border rounded shadow-sm">
                <h2 class="mb-1 fs-3">Welcome, <?= htmlspecialchars($fullName) ?></h2>
                <span class="text-secondary"><?= htmlspecialchars($role) ?></span>
            </div>

            <!-- Role-Based Dashboard Links -->
            <section class="row g-3">
                <?php
                // Helper to print a consistent dashboard card
                function dashboard_card($href, $text) {
                    echo '<div class="col-12 col-sm-6 col-lg-4">';
                    echo '<a href="'.htmlspecialchars($href).'" class="d-block p-4 mb-0 bg-light border rounded text-decoration-none text-dark h-100 dashboard-card">';
                    echo '<span class="fw-medium">'.htmlspecialchars($text).'</span>';
                    echo '</a>';
                    echo '</div>';
                }

                // Output dashboard links based on role
                switch (strtolower($role)) {
                    case 'general manager':
                        dashboard_card("services-dashboard.php", "Services Dashboard");
                        dashboard_card("manage-service-requests.php", "Manage Service Requests");
                        dashboard_card("manage-client-visits.php", "Manage Client Visits");
                        dashboard_card("manage-claimed-properties.php", "Manage Claimed Properties");
                        dashboard_card("manage-client-claims.php", "Manage Client Claims");
                        dashboard_card("sale-claim-details.php", "Sale Claim Details");
                        dashboard_card("confirm-application-payment.php", "Confirm Application Fees");
                        dashboard_card("confirm-service-payment.php", "Confirm Service Fee Payments");
                        dashboard_card("confirm-claim-payment.php", "Confirm Claim Payments");
                        dashboard_card("agent-assignments.php", "My Assignments");
                        dashboard_card("architecture-drawing-requests.php", "Manage Architecture Design Requests");
                        dashboard_card("construction-supervision-requests.php", "Manage Supervision Requests");
                        dashboard_card("legal-assistance-requests.php", "Legal Requests");
                        break;

                    case 'property manager':
                        dashboard_card("manage-service-requests.php", "Manage Service Requests");
                        dashboard_card("manage-client-visits.php", "Manage Client Visits");
                        dashboard_card("manage-claimed-properties.php", "Claimed Properties");
                        dashboard_card("manage-client-claims.php", "Client Claims");
                        dashboard_card("sale-claim-details.php", "Sale Claim Details");
                        break;

                    case 'accountant':
                        dashboard_card("confirm-application-payment.php", "Confirm Application Fees");
                        dashboard_card("confirm-service-payment.php", "Confirm Service Fee Payments");
                        dashboard_card("confirm-claim-payment.php", "Confirm Claim Payments");
                        dashboard_card("manage-owner-fees.php", "Manage Owner Fees");
                        break;

                    case 'field agent':
                        dashboard_card("agent-assignments.php", "My Assignments");
                        break;

                    case 'plan and supervision manager':
                        dashboard_card("manage-service-requests.php", "Manage Supervision Applications");
                        dashboard_card("architecture-drawing-requests.php", "Manage Architecture Design Requests");
                        dashboard_card("construction-supervision-requests.php", "Manage Supervision Requests");
                        break;

                    case 'legal officer':
                        dashboard_card("manage-service-requests.php", "Manage Service Requests");
                        dashboard_card("legal-assistance-requests.php", "Legal Assistance");
                        dashboard_card("internal-legal-assistance.php", "Internal Legal Assistance");
                        break;
                }
                ?>
            </section>
            <!-- End Dashboard Links -->
        </main>
        <!-- End Main Content -->

    </div>
</div>
<!-- End Container -->

<?php include 'footer.php'; ?>

<!-- Bootstrap JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
