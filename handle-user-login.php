<?php
/**
 * handle-user-login.php
 *
 * Handles user login for both clients and property owners.
 * - Validates credentials against the users table.
 * - Sets session variables based on user type (owner/client).
 * - Redirects to appropriate dashboard or intended page after login.
 *
 * Dependencies: db_connect.php, users/owners/clients tables, session.
 */

session_start();
require 'db_connect.php';

// -----------------------------------------------------------------------------
// Handle login POST request
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // -------------------------------------------------------------------------
    // Fetch user by email
    // -------------------------------------------------------------------------
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // -------------------------------------------------------------------------
    // If user exists and password is valid
    // -------------------------------------------------------------------------
    if ($user && password_verify($password, $user['password'])) {
        $user_id = $user['user_id'];
        $_SESSION['user_id']   = $user_id;
        $_SESSION['user_name'] = $user['full_name'];

        // ---------------------------------------------------------------------
        // Check if user is a property owner
        // ---------------------------------------------------------------------
        $stmt = $pdo->prepare("SELECT owner_id FROM owners WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $owner = $stmt->fetch();

        if ($owner) {
            $_SESSION['owner_id']  = $owner['owner_id'];
            $_SESSION['user_type'] = 'property_owner';

            // Redirect to previous page or owner dashboard
            $redirect = $_SESSION['redirect_after_login'] ?? 'owner-profile.php';
            unset($_SESSION['redirect_after_login']);
            header("Location: $redirect");
            exit;
        }

        // ---------------------------------------------------------------------
        // Check if user is a client
        // ---------------------------------------------------------------------
        $stmt = $pdo->prepare("SELECT client_id FROM clients WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $client = $stmt->fetch();

        if ($client) {
            $_SESSION['client_id']  = $client['client_id'];
            $_SESSION['user_type']  = 'client';

            // Redirect to previous page or client dashboard
            $redirect = $_SESSION['redirect_after_login'] ?? 'client-profile.php';
            unset($_SESSION['redirect_after_login']);
            header("Location: $redirect");
            exit;
        }

        // ---------------------------------------------------------------------
        // User has no owner or client role assigned
        // ---------------------------------------------------------------------
        $_SESSION['error'] = "Your account exists, but no role is assigned. Please contact support.";
        header('Location: user-login.php');
        exit;
    }

    // -------------------------------------------------------------------------
    // Invalid credentials
    // -------------------------------------------------------------------------
    $_SESSION['error'] = "Invalid email or password.";
    header('Location: user-login.php');
    exit;
}

// -----------------------------------------------------------------------------
// Block direct GET access
// -----------------------------------------------------------------------------
die("Invalid request.");
