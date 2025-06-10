<?php
/**
 * ============================================================================
 * manage-claimed-properties.php — TREA Real Estate Platform
 * ------------------------------------------------
 * Manage Claimed Properties
 *
 * For General Manager and Property Manager.
 * - Allows navigation to all claimed property management pages.
 * - Consistent layout and session handling.
 * Dependencies:
 * - db_connect.php
 * - header.php, footer.php
 * - Bootstrap 5.3.6
 */

session_start();
require 'db_connect.php';

// Role check: Only for general/property manager
if (
    !isset($_SESSION['staff_id']) ||
    !in_array(strtolower($_SESSION['role']), ['general manager', 'property manager'])
) {
    header("Location: staff-login.php");
    exit();
}

$fullName = $_SESSION['full_name'] ?? 'Staff';
$userId = $_SESSION['staff_id'] ?? '';
$profilePicture = $_SESSION['profile_picture_path'] ?? 'default.png';
$profilePicturePath = (!empty($profilePicture) && file_exists($profilePicture))
    ? $profilePicture
    : 'images/default.png';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Manage Claimed Properties</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Unified Bootstrap version -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css?v=<?= time() ?>">
</head>
<body class="d-flex flex-column min-vh-100 bg-light text-dark">
<?php include 'header.php'; ?>

<div class="container-fluid flex-grow-1 d-flex flex-column p-0">
    <div class="row flex-grow-1 g-0">

        <!-- Sidebar: consistent profile summary and menu -->
        <aside class="col-12 col-md-3 bg-white border-end py-4 sidebar">
            <div class="profile-summary text-center mb-4">
                <img src="<?= htmlspecialchars($profilePicturePath) ?>" alt="Profile Picture" class="mb-3 rounded-circle" style="width: 90px; height: 90px; object-fit: cover;">
                <div>
                    <p class="mb-1 fw-bold"><?= htmlspecialchars($fullName) ?></p>
                    <p class="mb-1 text-muted">ID: <?= htmlspecialchars($userId) ?></p>
                    <a href="notifications.php" class="btn btn-light w-100 my-2">View Notifications</a>
                    <a href="edit-staff-profile.php" class="btn btn-light w-100 my-2">Edit Profile</a>
                    <a href="staff-logout.php" class="btn btn-outline-danger w-100 mt-2">Logout</a>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="col-12 col-md-9 p-4">
            <div class="border rounded shadow-sm p-4 mb-4 main-title bg-white">
                <h2 class="mb-3 text-primary">Manage Claimed Properties</h2>
                <p>Select the category of claimed properties you want to manage:</p>
            </div>

            <div class="list-group">
                <a href="brokerage-claimed-properties.php" class="list-group-item list-group-item-action">
                    Brokerage Claims
                </a>
                <a href="sale-management-claimed-properties.php" class="list-group-item list-group-item-action">
                    Sale Property Management Claims
                </a>
                <a href="rental-management-claimed-properties.php" class="list-group-item list-group-item-action">
                    Rental Property Management Claims
                </a>
            </div>
        </main>
    </div>
</div>

<?php include 'footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
