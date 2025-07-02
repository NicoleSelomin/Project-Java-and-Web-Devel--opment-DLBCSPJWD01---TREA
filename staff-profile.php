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

// Fetch unread notification count
$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM notifications 
     WHERE recipient_id = ? AND recipient_type = 'staff' AND is_read = 0"
);
$stmt->execute([$userId]);
$unreadCount = $stmt->fetchColumn();

// Fetch staff profile picture
$stmt = $pdo->prepare("
    SELECT s.profile_picture 
    FROM staff s
    WHERE s.staff_id = ?
");
$stmt->execute([$userId]);
$staff = $stmt->fetch(PDO::FETCH_ASSOC);

// --- Upcoming events array
$scheduledEvents = [];

switch (strtolower($role)) {
    case 'general manager':
        // Contract meetings for sale and rental, rental contract next revision
        $sql = "
            SELECT 'Rental Contract Revision' AS event_type, rc.next_revision_date AS dt, p.property_name
              FROM rental_contracts rc
              JOIN client_claims cc ON rc.claim_id = cc.claim_id
              JOIN properties p ON cc.property_id = p.property_id
             WHERE rc.next_revision_date IS NOT NULL AND rc.next_revision_date >= NOW()
            UNION ALL
            SELECT 'Contract Meeting', rc.contract_discussion_datetime, p.property_name
              FROM rental_contracts rc
              JOIN client_claims cc ON rc.claim_id = cc.claim_id
              JOIN properties p ON cc.property_id = p.property_id
             WHERE rc.contract_discussion_datetime IS NOT NULL AND rc.contract_discussion_datetime >= NOW()
            ORDER BY dt ASC
            LIMIT 8
        ";
        $stmt = $pdo->query($sql);
        $scheduledEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);
        break;

    case 'property manager':
        $sql = "
            SELECT 'Property Inspection' AS event_type, osr.inspection_datetime AS dt, osr.property_name
              FROM owner_service_requests osr
             WHERE osr.inspection_datetime IS NOT NULL AND osr.inspection_datetime >= NOW()
            UNION ALL
            SELECT 'Initial Inspection', cc.meeting_datetime, p.property_name
              FROM client_claims cc
              JOIN properties p ON cc.property_id = p.property_id
             WHERE cc.meeting_datetime IS NOT NULL AND cc.meeting_datetime >= NOW()
            UNION ALL
            SELECT 'Final Inspection', cc.final_inspection_datetime, p.property_name
              FROM client_claims cc
              JOIN properties p ON cc.property_id = p.property_id
             WHERE cc.final_inspection_datetime IS NOT NULL AND cc.final_inspection_datetime >= NOW()
            UNION ALL
            SELECT 'Contract Meeting', rc.contract_discussion_datetime, p.property_name
              FROM rental_contracts rc
              JOIN client_claims cc ON rc.claim_id = cc.claim_id
              JOIN properties p ON cc.property_id = p.property_id
             WHERE rc.contract_discussion_datetime IS NOT NULL AND rc.contract_discussion_datetime >= NOW()
            ORDER BY dt ASC
            LIMIT 8
        ";
        $stmt = $pdo->query($sql);
        $scheduledEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);
        break;

    case 'field agent':
    // Show all booked future slots from agent_schedule for this agent
    $sql = "
        SELECT
            'Booked Slot' AS event_type,
            start_time AS dt,
            notes AS property_name -- Or description, or build a nicer summary if you want
        FROM agent_schedule
        WHERE agent_id = ? AND status = 'booked' AND start_time >= NOW()
        ORDER BY start_time ASC
        LIMIT 8
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId]);
    $scheduledEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    break;
}

// Use default image if no profile picture is set or file is missing
$profilePicturePath = (!empty($staff['profile_picture']) && file_exists($staff['profile_picture']))
    ? $staff['profile_picture']
    : 'images/default.png';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Meta & Title -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Staff Dashboard</title>
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
            <button class="btn btn-outline-secondary btn-sm d-md-none mb-3 w-100 custom-btn"
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
<a href="notifications.php" class="btn btn-light w-100 mt-3 position-relative" title="Notifications"> View Notifications
    <span style="font-size:1.5rem;">🔔</span>
    <?php if ($unreadCount > 0): ?>
        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"
              style="font-size:.8em;">
            <?= $unreadCount ?>
            <span class="visually-hidden">unread notifications</span>
        </span>
    <?php endif; ?>
</a>
                        <a href="edit-staff-profile.php" class="btn btn-outline-secondary btn-sm w-100 mb-2 profile-btn" style="background-color: #E021BA;">Edit Profile</a>
                        <a href="staff-logout.php" class="btn btn-outline-danger btn-sm w-100 profile-btn" style="background-color: #C154C1;">Logout</a>
                    </div>
                    <?php if (!empty($scheduledEvents)): ?>
    <div class="card mb-4">
        <div class="card-header bg-primary text-white py-2">
            <strong>Upcoming Schedules</strong>
        </div>
        <div class="card-body p-2">
            <ul class="list-group">
                <?php foreach ($scheduledEvents as $ev): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center py-2 px-2">
                        <div>
                            <span class="fw-semibold"><?= htmlspecialchars($ev['event_type']) ?></span><br>
                            <span class="small"><?= htmlspecialchars($ev['property_name']) ?></span>
                        </div>
                        <span class="badge bg-info text-dark ms-1">
                            <?= date('Y-m-d H:i', strtotime($ev['dt'])) ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
<?php else: ?>
    <div class="card mb-4">
        <div class="card-header bg-primary text-white py-2">
            <strong>Upcoming Schedules</strong>
        </div>
        <div class="card-body p-2 small text-muted">
            No scheduled events.
        </div>
    </div>
<?php endif; ?>

                </div>
            </div>
        </nav>
        <!-- End Sidebar -->

        <!-- Main Dashboard Content -->
        <main class="col-12 col-md-8 mx-lg-5 mx-sm-2">
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
                    echo '<a href="'.htmlspecialchars($href).'" class="d-block p-4 mb-0 h-100 dashboard-card">';
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
                        dashboard_card("confirm-application-payment.php", "Confirm Application Fees");
                        dashboard_card("confirm-claim-payment.php", "Confirm Claim Payments");
                        dashboard_card("agent-assignments.php", "My Assignments");
                        dashboard_card("manage-owner-fees.php", "Manage Owner Fees");
                        break;

                    case 'property manager':
                        dashboard_card("manage-service-requests.php", "Manage Service Requests");
                        dashboard_card("manage-client-visits.php", "Manage Client Visits");
                        dashboard_card("manage-claimed-properties.php", "Claimed Properties");
                        dashboard_card("manage-client-claims.php", "Client Claims");
                        break;

                    case 'accountant':
                        dashboard_card("confirm-application-payment.php", "Confirm Application Fees");
                        dashboard_card("confirm-claim-payment.php", "Confirm Claim Payments");
                        dashboard_card("manage-owner-fees.php", "Manage Owner Fees");
                        break;

                    case 'field agent':
                        dashboard_card("agent-assignments.php", "My Assignments");
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

<!-- Script to close main navbar on small screen-->
<script src="navbar-close.js?v=1"></script>

<!-- Bootstrap JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
