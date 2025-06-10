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
$stmt = $pdo->prepare("
    SELECT u.profile_picture, u.full_name
    FROM owners o
    JOIN users u ON o.user_id = u.user_id
    WHERE o.owner_id = ?
");
$stmt->execute([$userId]);
$owner = $stmt->fetch(PDO::FETCH_ASSOC);

// Set fallback values for profile name and picture
$fullName = $_SESSION['user_name'] ?? 'Unknown Owner';
$profilePicturePath = (!empty($owner['profile_picture']) && file_exists($owner['profile_picture']))
    ? $owner['profile_picture']
    : 'default.png';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Owner Profile - TREA</title>

    <!-- Bootstrap CSS (5.3.6) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-4Q6Gf2aSP4eDXB8Miphtr37CMZZQ5oXLH2yaXMJ2w8e2ZtHTl7GptT4jmndRuHDT" crossorigin="anonymous">
    <link rel="stylesheet" href="styles.css?v=<?= time() ?>">
</head>
<body class="d-flex flex-column min-vh-100 bg-light">

<!-- Include Header -->
<?php include 'header.php'; ?>

<!-- Main content container -->
<div class="container-fluid flex-grow-1">
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
                        <a href="notifications.php" class="btn btn-light w-100 mt-3">View Notifications</a>
                        <a href="edit-owner-profile.php" class="btn btn-light w-100 mt-3">Edit Profile</a>
                        <a href="user-logout.php" class="btn btn-light text-danger w-100 mt-3">Logout</a>
                    </div>

                    <div class="mt-5">
                        <h5>Calendar</h5>
                        <iframe src="https://calendar.google.com/calendar/embed?mode=MONTH" frameborder="0"
                                scrolling="no"></iframe>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Main Profile Content -->
        <main class="col-12 col-md-9">
            <div class="p-3 mb-4 border rounded shadow-sm">
                <h3>Welcome to Your Profile</h3>
                <p>Select an option to manage your activities:</p>
            </div>

            <div class="text-center">
                <a href="services.php" class="btn custom-btn mb-4">Request New Service</a>
            </div>

            <div class="list-group">
                <a href="owner-service-requests.php" class="list-group-item list-group-item-action">
                    View Service Requests
                </a>
                <a href="owner-listed-brokerage.php" class="list-group-item list-group-item-action">
                    View Brokerage Listings and Claims
                </a>
                <a href="owner-sale-management-properties.php" class="list-group-item list-group-item-action">
                    View Sale Management Properties
                </a>
                <a href="owner-rental-management-properties.php" class="list-group-item list-group-item-action">
                    View Rental Management Properties
                </a>
                <a href="owner-fees.php" class="list-group-item list-group-item-action">
                    Track Owner Fees
                </a>
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
</body>
</html>
