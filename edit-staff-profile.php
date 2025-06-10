<?php
/**
 * ----------------------------------------------------------------------------
 * edit-staff-profile.php
 * -----------------------------------------------------------------------------
 *
 * Allows logged-in staff to update their phone number and profile picture.
 * Features:
 *  - Fetches current staff data for editing
 *  - Handles file upload for profile pictures with unique folder per staff
 *  - Updates the staff table and session on save
 *
 * Dependencies:
 *  - db_connect.php: PDO database connection
 *  - staff-login.php: Redirects if not logged in
 */

session_start();
require 'db_connect.php';

// -----------------------------------------------------------------------------
// 1. ACCESS CONTROL: Staff must be logged in
// -----------------------------------------------------------------------------
if (!isset($_SESSION['staff_id'])) {
    header("Location: staff-login.php");
    exit();
}

$staff_id = $_SESSION['staff_id'];

// -----------------------------------------------------------------------------
// 2. FETCH CURRENT STAFF DATA FOR THE FORM
// -----------------------------------------------------------------------------
$stmt = $pdo->prepare("SELECT full_name, phone_number, profile_picture FROM staff WHERE staff_id = ?");
$stmt->execute([$staff_id]);
$staff = $stmt->fetch();

// -----------------------------------------------------------------------------
// 3. HANDLE FORM SUBMISSION (POST)
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone   = $_POST['phone_number'];
    $picture = $_FILES['profile_picture'];

    // Build safe staff folder path for uploads
    $cleanName   = preg_replace('/[^a-zA-Z0-9]/', '_', $staff['full_name']);
    $staffFolder = "uploads/staff/{$staff_id}_{$cleanName}/";

    if (!is_dir($staffFolder)) {
        mkdir($staffFolder, 0777, true);
    }

    // Fallback to the existing profile picture if no new one is uploaded
    $profilePicPath = $staff['profile_picture'];

    // If a new file was uploaded, save it to the staff folder
    if (!empty($picture['name']) && $picture['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($picture['name'], PATHINFO_EXTENSION);
        $profilePicPath = $staffFolder . 'profile.' . $ext;
        move_uploaded_file($picture['tmp_name'], $profilePicPath);
    }

    // Update the staff table with new info
    $stmt = $pdo->prepare("
        UPDATE staff 
        SET phone_number = ?, profile_picture = ?
        WHERE staff_id = ?
    ");
    $stmt->execute([$phone, $profilePicPath, $staff_id]);

    // Update session variable for live UI feedback
    $_SESSION['profile_picture'] = $profilePicPath;

    // Redirect to profile with update flag (avoids double POST)
    header("Location: staff-profile.php?updated=1");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Edit Profile</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Consistent Bootstrap 5.3.6 and style include -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <link rel="stylesheet" href="styles.css?v=<?= time() ?>">
</head>
<body class="container py-5">
    <h2>Edit Your Profile</h2>
    <form method="POST" enctype="multipart/form-data">
        <!-- Phone Number Input -->
        <div class="mb-3">
            <label for="phone_number" class="form-label">Phone Number:</label>
            <input type="text" class="form-control" name="phone_number" value="<?= htmlspecialchars($staff['phone_number']) ?>">
        </div>
        <!-- Profile Picture Preview and Upload -->
        <div class="mb-3">
            <label for="profile_picture" class="form-label">Profile Picture:</label><br>
            <?php
            $pic = (!empty($staff['profile_picture']) && file_exists($staff['profile_picture']))
                ? $staff['profile_picture']
                : 'images/default.png';
            ?>
            <img src="<?= htmlspecialchars($pic) ?>" width="100" class="mb-2 rounded-circle"><br>
            <input type="file" name="profile_picture" class="form-control">
        </div>
        <button type="submit" class="btn btn-success">Save Changes</button>
        <a href="staff-profile.php" class="btn btn-secondary">Cancel</a>
    </form>
    <!-- Consistent Bootstrap JS include -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>