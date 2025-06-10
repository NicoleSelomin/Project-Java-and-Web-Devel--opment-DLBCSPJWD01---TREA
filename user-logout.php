<?php
/**
 * ============================================================================
 * user-logout.php
 * ----------------------------------------------------------------------------
 * TREA User Logout Script
 * ----------------------------------------------------------------------------
 * Destroys the current user session and redirects the user to the login page.
 * ============================================================================
 */

session_start();

// Destroy all session data
session_destroy();

// Redirect to the user login page
header("Location: user-login.php");
exit();
?>
