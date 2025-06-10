<?php
/**
 * ================================================================
 * submit-user-signup.php — TREA Real Estate Platform
 * ------------------------------------------------
 * Handles registration for both Clients and Property Owners.
 * - Validates POST data and file upload.
 * - Enforces unique email constraint.
 * - Hashes password securely.
 * - Creates user upload directories and saves profile picture.
 * - Populates `users`, `clients` or `owners` tables.
 * - Starts session and redirects user to their profile/dashboard.
 * 
 * Requirements:
 * - Bootstrap 5.3+ for all output.
 * - Uses header.php and footer.php for consistency.
 * - Responsive
 * ================================================================
 */

session_start();
require_once 'db_connect.php';

// Uniform include for header
require_once 'header.php';

// Helper function: print Bootstrap error and footer, then exit
function showError($msg) {
    echo '<div class="container py-5">';
    echo '<div class="alert alert-danger" role="alert">' . htmlspecialchars($msg) . '</div>';
    echo '</div>';
    require_once 'footer.php';
    exit;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    showError('Invalid request method.');
}

// Gather and sanitize input
$user_type = $_POST['user_type'] ?? '';
$name      = trim($_POST['full_name'] ?? '');
$email     = trim($_POST['email'] ?? '');
$phone     = trim($_POST['phone_number'] ?? '');
$password  = $_POST['password'] ?? '';

if (!in_array($user_type, ['client', 'property_owner'], true)) {
    showError('Invalid user type.');
}
if ($name === '' || $email === '' || $phone === '' || $password === '') {
    showError('All fields are required.');
}

// Use consistent folder names
$folderBase = $user_type === 'client' ? 'clients' : 'owner';

// Check if email is already registered
$check = $pdo->prepare("SELECT 1 FROM users WHERE email = ?");
$check->execute([$email]);
if ($check->fetchColumn()) {
    showError('Email already registered. Please <a href="login.php">log in</a> or use a different email.');
}

// Hash password securely
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// Safe folder name
$safeName = preg_replace('/[^a-zA-Z0-9_-]/', '', strtolower(str_replace(' ', '_', $name)));

// Create user record
$stmt = $pdo->prepare("INSERT INTO users (full_name, email, password, phone_number, user_type, created_at, updated_at)
                       VALUES (?, ?, ?, ?, ?, NOW(), NOW())");
$stmt->execute([$name, $email, $hashed_password, $phone, $user_type]);
$user_id = $pdo->lastInsertId();

// Set up upload folder (uploads/clients/{id}_{name}/ or uploads/owner/{id}_{name}/)
$finalFolder = "uploads/{$folderBase}/{$user_id}_{$safeName}/";
if (!is_dir($finalFolder) && !mkdir($finalFolder, 0755, true)) {
    showError('Failed to create upload directory. Please contact support.');
}

// Handle optional profile picture upload
if (!empty($_FILES['profile_picture']['tmp_name']) && is_uploaded_file($_FILES['profile_picture']['tmp_name'])) {
    $profilePath = $finalFolder . "profile.jpg";
    // Optionally: validate file type/size here
    if (!move_uploaded_file($_FILES['profile_picture']['tmp_name'], $profilePath)) {
        showError('Failed to save profile picture.');
    }
    // Save the path (relative) in the DB
    $pdo->prepare("UPDATE users SET profile_picture = ? WHERE user_id = ?")
        ->execute([$profilePath, $user_id]);
}

// Insert into the correct table (clients or owners)
if ($user_type === 'client') {
    $pdo->prepare("INSERT INTO clients (client_id, user_id) VALUES (?, ?)")
        ->execute([$user_id, $user_id]);
    $_SESSION['client_id'] = $user_id;
} else {
    $pdo->prepare("INSERT INTO owners (owner_id, user_id) VALUES (?, ?)")
        ->execute([$user_id, $user_id]);
    $_SESSION['owner_id'] = $user_id;
}

// Set common session vars for user
$_SESSION['user_id']   = $user_id;
$_SESSION['user_type'] = $user_type;
$_SESSION['user_name'] = $name;

// Smart redirect — back to intended page if available, else profile
$redirect = $_SESSION['redirect_after_login'] ??
            ($user_type === 'client' ? 'client-profile.php' : 'owner-profile.php');
unset($_SESSION['redirect_after_login']);

// Footer is not needed on redirect
header("Location: $redirect");
exit;

// Fallback for accidental direct access
showError('Invalid request.');
?>
