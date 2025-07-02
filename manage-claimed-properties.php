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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage Reserved Properties</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css?v=<?= time() ?>">
</head>
<body class="d-flex flex-column min-vh-100 bg-light text-dark">
<?php include 'header.php'; ?>

<div class="container flex-grow-1 py-5">
    <div class="mb-4 text-center">
        <h2 class="mb-2 text-primary fw-bold">Manage reserved Properties</h2>
        <p class="text-muted">Select a category of reserved properties to manage:</p>
    </div>

    <div class="row g-4 justify-content-center mb-5">
        <div class="col-md-4 col-12">
            <a href="brokerage-claimed-properties.php" class="btn btn-outline-primary w-100 py-4 category-btn d-flex flex-column align-items-center custom-btn">
                Reserved Brokerage Properties
            </a>
        </div>
        <div class="col-md-4 col-12">
            <a href="rental-management-claimed-properties.php" class="btn btn-outline-warning w-100 py-4 category-btn d-flex flex-column align-items-center custom-btn">
                Reserved Rental Property Management Properties
            </a>
        </div>
    </div>
    <div class="text-center">
        <a href="staff-profile.php" class="btn btn-dark fw-bold px-4 py-2">🡰 Back to dashboard</a>
    </div>
</div>

<?php include 'footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
<script src="navbar-close.js?v=1"></script>
</body>
</html>
