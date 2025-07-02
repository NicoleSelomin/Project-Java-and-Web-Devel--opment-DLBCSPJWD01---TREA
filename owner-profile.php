<?php
/*
|--------------------------------------------------------------------------
| owner-profile.php
|--------------------------------------------------------------------------
| Displays the profile details of the logged-in property owner.
| Includes options for managing notifications, editing profile information,
| viewing service requests, property listings, and tracking fees.
|
| - Responsive design using Bootstrap 5.3.6
|--------------------------------------------------------------------------
*/

// Required files for session check and database connection
require_once 'check-user-session.php';
require 'db_connect.php';

// Retrieve owner's profile details from the database
$userId = $_SESSION['owner_id'];

// Fetch unread notification count
$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM notifications 
     WHERE recipient_id = ? AND recipient_type = 'property_owner' AND is_read = 0"
);
$stmt->execute([$userId]);
$unreadCount = $stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT u.profile_picture, u.full_name
    FROM owners o
    JOIN users u ON o.user_id = u.user_id
    WHERE o.owner_id = ?
");
$stmt->execute([$userId]);
$owner = $stmt->fetch(PDO::FETCH_ASSOC);
$stmt = $pdo->prepare("
    SELECT 'Inspection' AS meeting_type, r.inspection_datetime AS meeting_time, 
           r.property_name, r.request_id AS reference_id
      FROM owner_service_requests r
     WHERE r.owner_id = ? AND r.inspection_datetime IS NOT NULL

    UNION ALL

    SELECT 'Owner Contract Signing', r.owner_contract_meeting, 
           r.property_name, r.request_id
      FROM owner_service_requests r
     WHERE r.owner_id = ? AND r.owner_contract_meeting IS NOT NULL

    UNION ALL

    SELECT 'Rental Contract Discussion', rc.contract_discussion_datetime, 
           p.property_name, rc.contract_id
      FROM rental_contracts rc
     JOIN client_claims cc ON rc.claim_id = cc.claim_id
     JOIN properties p ON cc.property_id = p.property_id
     WHERE p.owner_id = ? AND rc.contract_discussion_datetime IS NOT NULL

    UNION ALL

    SELECT 'Initial Inspection', cc.meeting_datetime, 
           p.property_name, cc.claim_id
      FROM client_claims cc
     JOIN properties p ON cc.property_id = p.property_id
     WHERE p.owner_id = ? AND cc.meeting_datetime IS NOT NULL

    UNION ALL

    SELECT 'Final Inspection', cc.final_inspection_datetime, 
           p.property_name, cc.claim_id
      FROM client_claims cc
     JOIN properties p ON cc.property_id = p.property_id
     WHERE p.owner_id = ? AND cc.final_inspection_datetime IS NOT NULL

    ORDER BY meeting_time ASC
");
$stmt->execute([$userId, $userId, $userId, $userId, $userId]);
$allMeetings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Only keep meetings from now on
$allMeetings = array_filter($allMeetings, function($m) {
    return strtotime($m['meeting_time']) >= time();
});

// If you want to show soonest first (just in case)
usort($allMeetings, function($a, $b){
    return strtotime($a['meeting_time']) <=> strtotime($b['meeting_time']);
});

// Set fallback values for profile name and picture
$fullName = $_SESSION['user_name'] ?? 'Unknown Owner';
$profilePicturePath = (!empty($owner['profile_picture']) && file_exists($owner['profile_picture']))
    ? $owner['profile_picture']
    : 'images/default.png';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Owner Dashboard - TREA</title>

    <!-- Bootstrap CSS (5.3.6) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-4Q6Gf2aSP4eDXB8Miphtr37CMZZQ5oXLH2yaXMJ2w8e2ZtHTl7GptT4jmndRuHDT" crossorigin="anonymous">
    <link rel="stylesheet" href="styles.css?v=<?= time() ?>">
</head>
<body class="d-flex flex-column min-vh-100 bg-light">

<!-- Include Header -->
<?php include 'header.php'; ?>

<!-- Main content container -->
<div class="container-fluid">
    <div class="row">

        <!-- Responsive Sidebar -->
        <nav class="col-12 col-md-3 mb-3">
            <button class="btn btn-sm d-md-none mb-3 custom-btn" type="button" data-bs-toggle="collapse"
                    data-bs-target="#sidebarCollapse" aria-expanded="false">
                Open Menu
            </button>

            <div class="collapse d-md-block" id="sidebarCollapse">
                <div class="sidebar text-center">
                    <div class="profile-summary">
                        <img src="<?= htmlspecialchars($profilePicturePath) ?>" alt="Profile Picture" class="mb-3">
                        <p><strong><?= htmlspecialchars($fullName) ?></strong></p>
                        <p>ID: <?= htmlspecialchars($userId) ?></p>
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

                        <a href="edit-owner-profile.php" class="btn btn-light w-100 mt-3">Edit Profile</a>
                        <a href="user-logout.php" class="btn btn-light text-danger w-100 mt-3 mb-4">Logout</a>
                    </div>
                            <!-- For owner to see scheduled events -->
                             <div class="card mb-4">
    <div class="card-header bg-light text-dark">
        <strong>Your Upcoming Meetings</strong>
    </div>
    <div class="card-body">
        <?php if (!empty($allMeetings)): ?>
        <ul class="list-group">
<?php foreach ($allMeetings as $meet): ?>
    <li class="list-group-item d-flex justify-content-between align-items-center">
        <div>
            <strong><?= htmlspecialchars($meet['meeting_type']) ?></strong><br>
            <?= htmlspecialchars($meet['property_name']) ?>
        </div>
        <span class="badge bg-info text-dark">
            <?= date('Y-m-d H:i', strtotime($meet['meeting_time'])) ?>
        </span>
    </li>
<?php endforeach; ?>
</ul>
<?php else: ?>
            <div class="alert alert-warning mb-0">
                No meetings scheduled yet.
            </div>
        <?php endif; ?>
    </div>
</div>
                </div>
            </div>
        </nav>

        <!-- Main Profile Content -->
        <main class="col-12 col-md-9">
            <div class="p-3 mb-4 border rounded shadow-sm main-title">
                <h3>Welcome to Your Profile</h3>
                <p>Select an option to manage your activities:</p>
            </div>

            <div class="text-center">
                <a href="services.php" class="btn custom-btn mb-4">Request New Service</a>
            </div>

            <div class="row mt-4 g-3">
                <div class="col-12 col-md-6">
                <a href="owner-service-requests.php" class="btn btn-outline-dark w-100 py-3 custom-btn">
                    View Service Requests
                </a>
                </div>

                <div class="col-12 col-md-6">
                <a href="owner-listed-brokerage.php" class="btn btn-outline-dark w-100 py-3 custom-btn">
                    View Brokerage Listings and Claims
                </a>
                </div>

                <div class="col-12 col-md-6">
                <a href="owner-rental-management-properties.php" class="btn btn-outline-dark w-100 py-3 custom-btn">
                    View Rental Management Properties
                </a>
                </div>

                <div class="col-12 col-md-6">
                <a href="owner-fees.php" class="btn btn-outline-dark w-100 py-3 custom-btn">
                    Track Owner Fees
                </a>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Include Footer -->
<?php include 'footer.php'; ?>

<!-- Bootstrap JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-j1CDi7MgGQ12Z7Qab0qlWQ/Qqz24Gc6BM0thvEMVjHnfYGF0rmFCozFSxQBxwHKO"
        crossorigin="anonymous"></script>

<!-- Script to close main navbar on small screen-->
<script src="navbar-close.js?v=1"></script>

</body>
</html>
