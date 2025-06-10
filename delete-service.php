<?php
/**
 * -----------------------------------------------------------------
 * delete-service.php
 * ------------------------------------------------------------------
 * 
 * Delete Service Handler (General Manager Only)
 *
 * Allows the general manager to delete a service by service_id.
 * - Access control: Only 'general manager' role can perform this action.
 * - Deletes from the 'services' table based on provided ID.
 * - Redirects to services dashboard on completion or error.
 *
 * Dependencies:
 * - db_connect.php: Provides $pdo (PDO connection).
 * - staff-login.php: Staff login/auth.
 * - services-dashboard.php: Redirect after delete.
 */

session_start();
require 'db_connect.php';

// -----------------------------------------------------------------------------
// 1. ACCESS CONTROL: Only General Manager Allowed
// -----------------------------------------------------------------------------
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'general manager') {
    // Redirect unauthorized users to staff login
    header("Location: staff-login.php");
    exit();
}

// -----------------------------------------------------------------------------
// 2. DELETE SERVICE IF 'id' IS PROVIDED IN GET
// -----------------------------------------------------------------------------
if (isset($_GET['id'])) {
    $service_id = intval($_GET['id']);  // Sanitize/validate input to integer

    // Prepare and execute the delete statement
    $stmt = $pdo->prepare("DELETE FROM services WHERE service_id = ?");
    if ($stmt->execute([$service_id])) {
        // Success: redirect with deleted flag
        header("Location: services-dashboard.php?deleted=1");
        exit();
    } else {
        // Failure: show simple error message (could be improved with logging)
        echo "Failed to delete service.";
    }
} else {
    // No service ID provided: redirect to dashboard
    header("Location: services-dashboard.php");
    exit();
}
?>
