<?php
/*
|--------------------------------------------------------------------------
| owner-request-check.php
|--------------------------------------------------------------------------
| Redirects property owners to the correct service request form based on
| provided service ID. Ensures the user is logged in as a property owner;
| if not, redirects to the login page and retains intended destination.
|
| Standards:
| - Consistent and responsive structure using Bootstrap 5.3.6
|--------------------------------------------------------------------------
*/

// Initialize session and include necessary files
session_start();
require_once 'check-user-session.php';
require 'db_connect.php';

// Retrieve service ID from URL parameters or redirect if absent
$service_id = $_GET['service_id'] ?? null;
$redirect = $_GET['redirect'] ?? 'services.php';

if (!$service_id) {
    header("Location: $redirect");
    exit();
}

// Query database to fetch service name based on provided ID
$stmt = $pdo->prepare("SELECT service_name FROM services WHERE service_id = ?");
$stmt->execute([$service_id]);
$service = $stmt->fetch(PDO::FETCH_ASSOC);

// Redirect if service does not exist
if (!$service) {
    header("Location: $redirect");
    exit();
}

// Generate form page URL based on sanitized service name
$serviceName = strtolower(str_replace([' ', '_', '/'], ['-', '-', ''], $service['service_name']));
$formPage = "request-{$serviceName}.php?service_id=" . urlencode($service_id);

// Verify user session; redirect to login if not authenticated as property owner
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'property_owner') {
    $redirectAfterLogin = urlencode($_SERVER['REQUEST_URI']);
    header("Location: user-login.php?redirect=$redirectAfterLogin");
    exit();
}

// Redirect authenticated property owners directly to the service request form
header("Location: $formPage");
exit();
