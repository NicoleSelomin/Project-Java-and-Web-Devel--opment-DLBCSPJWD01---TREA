<?php
// -----------------------------------------------------------------------------
// check-user-session.php
// -----------------------------------------------------------------------------
// Ensures user is logged in, checks if user exists, and if user type matches
// a valid property owner or client. Redirects to login if checks fail.
// Saves pending form data and files for restoration after login.
// -----------------------------------------------------------------------------

if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'db_connect.php';

// If not logged in, save form data and redirect to login
if (!isset($_SESSION['user_id'], $_SESSION['user_type'], $_SESSION['user_name'])) {
    // Save POST data and uploaded files for restoration after login
    $_SESSION['pending_form_data'] = $_POST;
    $_SESSION['pending_files'] = $_FILES;

    // Save the intended destination to return after login
    if (!isset($_SESSION['redirect_after_login'])) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    }

    header("Location: user-login.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
    exit();
}

// Store session values
$user_id   = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];
$user_name = preg_replace('/[^a-zA-Z0-9]/', '_', strtolower($_SESSION['user_name']));

// Confirm user record exists in users table
$userCheck = $pdo->prepare("SELECT COUNT(*) FROM users WHERE user_id = ?");
$userCheck->execute([$user_id]);
if ($userCheck->fetchColumn() == 0) {
    session_unset();
    session_destroy();
    header("Location: user-login.php?error=user_removed");
    exit();
}

// Type-specific session and table validation
if ($user_type === 'property_owner') {
    // Owner session variable must be set
    if (!isset($_SESSION['owner_id'])) {
        session_unset();
        session_destroy();
        header("Location: user-login.php?error=missing_owner_session");
        exit();
    }

    $owner_id = $_SESSION['owner_id'];

    // Confirm the owner exists and is linked to the correct user_id
    $check = $pdo->prepare("SELECT COUNT(*) FROM owners WHERE owner_id = ? AND user_id = ?");
    $check->execute([$owner_id, $user_id]);
    if ($check->fetchColumn() == 0) {
        session_unset();
        session_destroy();
        header("Location: user-login.php?error=invalid_owner_record");
        exit();
    }

    // Used for owner upload directory naming
    $user_folder_name = "owner_{$owner_id}_{$user_name}";
}
elseif ($user_type === 'client') {
    // Client session variable must be set
    if (!isset($_SESSION['client_id'])) {
        session_unset();
        session_destroy();
        header("Location: user-login.php?error=missing_client_session");
        exit();
    }

    $client_id = $_SESSION['client_id'];

    // Confirm the client exists and is linked to the correct user_id
    $check = $pdo->prepare("SELECT COUNT(*) FROM clients WHERE client_id = ? AND user_id = ?");
    $check->execute([$client_id, $user_id]);
    if ($check->fetchColumn() == 0) {
        session_unset();
        session_destroy();
        header("Location: user-login.php?error=invalid_client_record");
        exit();
    }

    // Used for client upload directory naming
    $user_folder_name = "client_{$client_id}_{$user_name}";
}

// $user_folder_name is set for use in upload handling
?>
