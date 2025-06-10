<!-- edit-client-profile.php -->
<?php
/**
 * Edit Client Profile
 *
 * Allows a logged-in client to edit their phone number and profile picture.
 * Features:
 *  - Loads existing client data from users/clients tables
 *  - Updates phone number and profile picture on form submission
 *  - Stores profile pictures in a dedicated client folder
 *  - Updates session variable for immediate feedback in UI
 *
 * Dependencies:
 *  - check-user-session.php: Validates client session
 *  - db_connect.php: PDO database connection ($pdo)
 *  - Bootstrap 5: Responsive UI
 */

session_start();
require_once 'check-user-session.php';
require 'db_connect.php';

// -----------------------------------------------------------------------------
// 1. LOAD CLIENT DATA (JOIN users and clients)
// -----------------------------------------------------------------------------

$client_id = $_SESSION['client_id'];

// Fetch user info (join users and clients by user_id for this client)
$stmt = $pdo->prepare("
    SELECT u.user_id, u.full_name, u.phone_number, u.profile_picture 
    FROM users u
    JOIN clients c ON c.user_id = u.user_id 
    WHERE c.client_id = ?
");
$stmt->execute([$client_id]);
$user = $stmt->fetch();

if (!$user) {
    // If no such user, abort
    die("User not found.");
}

// -----------------------------------------------------------------------------
// 2. HANDLE PROFILE UPDATE (FORM SUBMISSION)
// -----------------------------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = $_POST['phone_number'];
    $picture = $_FILES['profile_picture'];
    $profilePicPath = $user['profile_picture']; // Default to current

    // Build client upload directory: /uploads/clients/{client_id}_{name}/
    $cleanName = preg_replace('/[^a-zA-Z0-9]/', '_', $user['full_name']);
    $clientFolder = "uploads/clients/{$client_id}_{$cleanName}/";

    if (!is_dir($clientFolder)) mkdir($clientFolder, 0755, true);

    // If a new profile picture was uploaded, store it
    if (!empty($picture['name']) && $picture['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($picture['name'], PATHINFO_EXTENSION);
        $profilePicPath = $clientFolder . 'profile.' . $ext;
        move_uploaded_file($picture['tmp_name'], $profilePicPath);
    }

    // Update phone number and (potentially) new profile picture path in users table
    $stmt = $pdo->prepare("UPDATE users SET phone_number = ?, profile_picture = ? WHERE user_id = ?");
    $stmt->execute([$phone, $profilePicPath, $user['user_id']]);

    // Update session variable so UI updates immediately for current user
    $_SESSION['profile_picture'] = $profilePicPath;

    // Redirect with update flag (prevents form resubmission)
    header("Location: client-profile.php?updated=1");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Edit Client Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container py-5">
    <h2>Edit Your Profile</h2>

    <form method="POST" enctype="multipart/form-data">
        <div class="mb-3">
            <label for="phone_number" class="form-label">Phone Number:</label>
            <input type="text" class="form-control" name="phone_number" value="<?= htmlspecialchars($user['phone_number'] ?? '') ?>">
        </div>

        <div class="mb-3">
            <label for="profile_picture" class="form-label">Profile Picture:</label><br>
            <?php
            // Show the profile picture if available; otherwise, show default placeholder
            $pic = (!empty($user['profile_picture']) && file_exists($user['profile_picture']))
                ? $user['profile_picture']
                : 'images/default.png';
            ?>
            <img src="<?= htmlspecialchars($pic) ?>" width="100" class="mb-2 rounded-circle"><br>
            <input type="file" name="profile_picture" class="form-control">
        </div>

        <button type="submit" class="btn custom-btn">Save Changes</button>
        <a href="client-profile.php" class="btn custom-btn">Cancel</a>
    </form>
</body>
</html>
