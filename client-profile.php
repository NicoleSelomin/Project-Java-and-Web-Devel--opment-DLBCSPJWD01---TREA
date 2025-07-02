<?php
/**
 * -------------------------------------------------------------------------------------
 * client-profile.php 
 * -------------------------------------------------------------------------------------
 * 
 * Client Profile Page
 * 
 * This page displays the logged-in client's profile summary and main navigation links.
 * Features:
 * - Shows profile picture, name, and ID
 * - Sidebar with navigation to notifications, profile editing, and logout
 * - Main area with links to all claim/booking pages
 * - Responsive layout using Bootstrap
 * 
 * Dependencies:
 * - check-user-session.php
 * - db_connect.php
 * - header.php, footer.php, styles.css
 * 
 * -----------------------------------------------------------------------------------------
 */

// Ensure user session and DB connection
require_once 'check-user-session.php';
require 'db_connect.php';

// Get client name and ID from session
$fullName = $_SESSION['user_name'] ?? 'Unknown client';
$userId = $_SESSION['client_id'];

// Fetch unread notification count
$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM notifications 
     WHERE recipient_id = ? AND recipient_type = 'client' AND is_read = 0"
);
$stmt->execute([$userId]);
$unreadCount = $stmt->fetchColumn();

// Retrieve profile picture path
$stmt = $pdo->prepare("
    SELECT u.profile_picture 
    FROM clients c
    JOIN users u ON c.user_id = u.user_id
    WHERE c.client_id = ?
");
$stmt->execute([$userId]);
$client = $stmt->fetch(PDO::FETCH_ASSOC);

// Get upcoming scheduled events
$eventSql = "
    SELECT 'Booked Visit' AS event_type, v.visit_date AS event_date, v.visit_time AS event_time, 
           CONCAT(v.visit_date, ' ', v.visit_time) AS dt,
           p.property_name
      FROM client_onsite_visits v
      JOIN properties p ON v.property_id = p.property_id
     WHERE v.client_id = ? AND v.visit_date >= CURDATE()
     
    UNION ALL

    SELECT 'Initial Inspection', cc.meeting_datetime, NULL, cc.meeting_datetime, p.property_name
      FROM client_claims cc
      JOIN properties p ON cc.property_id = p.property_id
     WHERE cc.client_id = ? AND cc.meeting_datetime IS NOT NULL AND cc.meeting_datetime >= NOW()
     
    UNION ALL

    SELECT 'Final Inspection', cc.final_inspection_datetime, NULL, cc.final_inspection_datetime, p.property_name
      FROM client_claims cc
      JOIN properties p ON cc.property_id = p.property_id
     WHERE cc.client_id = ? AND cc.final_inspection_datetime IS NOT NULL AND cc.final_inspection_datetime >= NOW()

    UNION ALL

    SELECT 'Contract Discussion', rc.contract_discussion_datetime, NULL, rc.contract_discussion_datetime, p.property_name
      FROM rental_contracts rc
      JOIN client_claims cc ON rc.claim_id = cc.claim_id
      JOIN properties p ON cc.property_id = p.property_id
     WHERE cc.client_id = ? AND rc.contract_discussion_datetime IS NOT NULL AND rc.contract_discussion_datetime >= NOW()
    
    ORDER BY dt ASC
    LIMIT 8
";
$eventStmt = $pdo->prepare($eventSql);
$eventStmt->execute([$userId, $userId, $userId, $userId]);
$upcomingEvents = $eventStmt->fetchAll(PDO::FETCH_ASSOC);

// Default to placeholder if not found
$profilePicturePath = (!empty($client['profile_picture']) && file_exists($client['profile_picture']))
    ? $client['profile_picture']
    : 'images/default.png';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Client Dashboard - TREA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-4Q6Gf2aSP4eDXB8Miphtr37CMZZQ5oXLH2yaXMJ2w8e2ZtHTl7GptT4jmndRuHDT" crossorigin="anonymous">
    <link rel="stylesheet" href="styles.css?v=<?= time() ?>">
</head>
<body class="d-flex flex-column min-vh-100 bg-light">
<?php include 'header.php'; ?>

<div class="container-fluid"> 
    <div class="row">
        <!-- Sidebar -->
        <aside class="col-12 col-md-3 mb-3">
            <button class="btn btn-sm d-md-none mb-3 custom-btn" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarCollapse" aria-expanded="false" aria-controls="sidebarCollapse">
                Open Sidebar
            </button>
            <div class="collapse d-md-block" id="sidebarCollapse">
                <div class="sidebar text-center">
                    <div class="profile-summary text-center">
                        <img src="<?= htmlspecialchars($profilePicturePath) ?>" alt="Profile Picture" class="mb-3 img-fluid rounded-circle" style="max-width:120px;">
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


                        <a href="edit-client-profile.php" class="btn mt-3 bg-light w-100">Edit Profile</a>
                        <a href="user-logout.php" class="btn text-danger mt-3 mb-4 d-block bg-light w-100">Logout</a>
                    </div>
                    <div class="card mb-4">
    <div class="card-header bg-primary text-white py-2">
        <strong>Upcoming Events</strong>
    </div>
    <div class="card-body p-2">
        <?php if (!empty($upcomingEvents)): ?>
            <ul class="list-group">
                <?php foreach ($upcomingEvents as $ev): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center py-2 px-2">
                        <div>
                            <span class="fw-semibold"><?= htmlspecialchars($ev['event_type']) ?></span><br>
                            <span class="small"><?= htmlspecialchars($ev['property_name']) ?></span>
                        </div>
                        <span class="badge bg-info text-dark ms-1">
                            <?php
                                if ($ev['event_time']) {
                                    echo date('Y-m-d', strtotime($ev['event_date'])) . '<br>' . date('H:i', strtotime($ev['event_time']));
                                } else {
                                    echo date('Y-m-d H:i', strtotime($ev['event_date']));
                                }
                            ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <div class="alert alert-light mb-0 p-2 small">
                No upcoming events.
            </div>
        <?php endif; ?>
    </div>
</div>

                </div>
            </div>
        </aside>

        <!-- Main Content Area -->
        <main class="col-12 col-md-9">
            <div class="mb-4 p-3 border rounded shadow-sm main-title">
                <h2>Welcome, <?= htmlspecialchars($fullName) ?></h2>
                <p class="text-muted">Use the links below to manage your activity on the platform.</p>
            </div>
            <div class="row mt-4 g-3">
                <div class="col-12 col-md-6">
                    <a href="client-visits.php" class="btn btn-outline-dark w-100 py-3 custom-btn">
                        View Properties & Visits
                    </a>
                </div>
                <div class="col-12 col-md-6">
                    <a href="client-claimed-brokerage.php" class="btn btn-outline-dark w-100 py-3 custom-btn">
                        View Reserved Brokerage Properties
                    </a>
                </div>
                <div class="col-12 col-md-6">
                    <a href="client-claimed-rental-management.php" class="btn btn-outline-dark w-100 py-3 custom-btn">
                        View Reserved Rental Management Properties
                    </a>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include 'footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js" integrity="sha384-j1CDi7MgGQ12Z7Qab0qlWQ/Qqz24Gc6BM0thvEMVjHnfYGF0rmFCozFSxQBxwHKO" crossorigin="anonymous"></script>

<!-- Script to close main navbar on small screen-->
<script src="navbar-close.js?v=1"></script>

</body>
</html>
