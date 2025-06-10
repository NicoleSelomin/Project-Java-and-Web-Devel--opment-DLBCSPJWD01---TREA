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

// Retrieve profile picture path
$stmt = $pdo->prepare("
    SELECT u.profile_picture 
    FROM clients c
    JOIN users u ON c.user_id = u.user_id
    WHERE c.client_id = ?
");
$stmt->execute([$userId]);
$client = $stmt->fetch(PDO::FETCH_ASSOC);

// Default to placeholder if not found
$profilePicturePath = (!empty($client['profile_picture']) && file_exists($client['profile_picture']))
    ? $client['profile_picture']
    : 'default.png';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Client Profile</title>
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
                        <a href="notifications.php" class="btn mt-3 bg-light w-100">View Notifications</a>
                        <a href="edit-client-profile.php" class="btn mt-3 bg-light w-100">Edit Profile</a>
                        <a href="user-logout.php" class="btn text-danger mt-3 d-block bg-light w-100">Logout</a>
                    </div>
                    <div>
                        <h5 class="mt-5">Calendar</h5>
                        <iframe src="https://calendar.google.com/calendar/embed?mode=MONTH" frameborder="0" scrolling="no" style="width:100%; min-height:300px;"></iframe>
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
                        View Claimed Brokerage Properties
                    </a>
                </div>
                <div class="col-12 col-md-6">
                    <a href="client-claimed-sale-management.php" class="btn btn-outline-dark w-100 py-3 custom-btn">
                        View Claimed Sale Management Properties
                    </a>
                </div>
                <div class="col-12 col-md-6">
                    <a href="client-claimed-rental-management.php" class="btn btn-outline-dark w-100 py-3 custom-btn">
                        View Claimed Rental Management Properties
                    </a>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include 'footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js" integrity="sha384-j1CDi7MgGQ12Z7Qab0qlWQ/Qqz24Gc6BM0thvEMVjHnfYGF0rmFCozFSxQBxwHKO" crossorigin="anonymous"></script>
</body>
</html>
