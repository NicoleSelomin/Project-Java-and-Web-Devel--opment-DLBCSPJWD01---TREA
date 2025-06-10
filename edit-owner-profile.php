<!-- edit-owner-profile.php -->
<?php
/**
 * Edit Owner Profile
 *
 * Allows a logged-in property owner to update their phone number and profile picture.
 * Features:
 *  - Loads current owner details via JOIN (users + owners)
 *  - Handles phone/profile picture update on POST
 *  - Stores images under a unique owner folder in /uploads/owner/{owner_id}_{name}/
 *  - Updates session to reflect new picture immediately
 *
 * Dependencies:
 *  - check-user-session.php: Session and owner login check
 *  - db_connect.php: Provides $pdo PDO connection
 *  - Bootstrap 5: For form/UI styling
 */

session_start();
require_once 'check-user-session.php';
require 'db_connect.php';

// -----------------------------------------------------------------------------
// 1. LOAD OWNER PROFILE DATA (USERS + OWNERS JOIN)
// -----------------------------------------------------------------------------
$owner_id = $_SESSION['owner_id'];

// Retrieve owner user data for the form (full_name needed for path, etc.)
$stmt = $pdo->prepare("
    SELECT u.user_id, u.full_name, u.phone_number, u.profile_picture 
    FROM users u
    JOIN owners o ON o.user_id = u.user_id 
    WHERE o.owner_id = ?
");
$stmt->execute([$owner_id]);
$user = $stmt->fetch();

if (!$user) {
    // If not found, do not proceed
    die("User not found.");
}

// -----------------------------------------------------------------------------
// 2. HANDLE PROFILE UPDATE SUBMISSION (POST)
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = $_POST['phone_number'];           // New phone number from input
    $picture = $_FILES['profile_picture'];     // Uploaded file, if any
    $profilePicPath = $user['profile_picture']; // Default to current path

    // Build/ensure unique upload folder for this owner
    $cleanName = preg_replace('/[^a-zA-Z0-9]/', '_', $user['full_name']);
    $ownerFolder = "uploads/owner/{$owner_id}_{$cleanName}/";
    if (!is_dir($ownerFolder)) mkdir($ownerFolder, 0777, true);

    // If a new picture was uploaded, move it to the owner's folder
    if (!empty($picture['name']) && $picture['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($picture['name'], PATHINFO_EXTENSION);
        $profilePicPath = $ownerFolder . 'profile.' . $ext;
        move_uploaded_file($picture['tmp_name'], $profilePicPath);
    }

    // Update users table with new phone and/or picture
    $stmt = $pdo->prepare("UPDATE users SET phone_number = ?, profile_picture = ? WHERE user_id = ?");
    $stmt->execute([$phone, $profilePicPath, $user['user_id']]);

    // Update session so profile pic changes show immediately
    $_SESSION['profile_picture'] = $profilePicPath;

    // Redirect to profile page with update flag (avoids resubmission)
    header("Location: owner-profile.php?updated=1");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Edit Owner Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container py-5">
    <h2>Edit Your Profile</h2>
    <form method="POST" enctype="multipart/form-data">
        <!-- Phone Number Input -->
        <div class="mb-3">
            <label for="phone_number" class="form-label">Phone Number:</label>
            <input type="text" class="form-control" name="phone_number" value="<?= htmlspecialchars($user['phone_number']) ?>">
        </div>
        <!-- Profile Picture Upload & Preview -->
        <div class="mb-3">
            <label for="profile_picture" class="form-label">Profile Picture:</label><br>
            <?php
            // Show current picture or fallback to default if missing
            $pic = (!empty($user['profile_picture']) && file_exists($user['profile_picture']))
                ? $user['profile_picture']
                : 'images/default.png';
            ?>
            <img src="<?= htmlspecialchars($pic) ?>" width="100" class="mb-2 rounded-circle"><br>
            <input type="file" name="profile_picture" class="form-control">
        </div>
        <button type="submit" class="btn btn-success">Save Changes</button>
        <a href="owner-profile.php" class="btn btn-secondary">Cancel</a>
    </form>
</body>
</html>
