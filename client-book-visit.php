<?php
// -----------------------------------------------------------------------------
// client-book-visit.php
// -----------------------------------------------------------------------------
// Handles booking an onsite property visit for a client.
// - Checks login, validates data and property status
// - Prevents duplicate bookings
// - Inserts visit and removes property from client cart
// -----------------------------------------------------------------------------

session_start();
require_once 'check-user-session.php';
require 'db_connect.php';

// --- 1. Ensure client is logged in ---
if (!isset($_SESSION['client_id'])) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header("Location: client-login.php");
    exit();
}

// --- 2. Gather input: property_id and visit datetime (support POST or GET) ---
$client_id      = $_SESSION['client_id'];
$property_id    = $_POST['property_id'] ?? $_GET['property_id'] ?? null;
$datetime_input = $_POST['visit_datetime'] ?? $_GET['visit_datetime'] ?? null;

if (!$property_id || !$datetime_input) {
    echo "Invalid booking request.";
    exit();
}

// --- 3. Check property is still available ---
$stmt = $pdo->prepare("SELECT * FROM properties WHERE property_id = ? AND availability = 'available'");
$stmt->execute([$property_id]);
$property = $stmt->fetch();

if (!$property) {
    echo "This property is no longer available.";
    exit();
}

// --- 4. Validate visit datetime (must be in the future) ---
$timestamp = strtotime($datetime_input);
if (!$timestamp || $timestamp < time()) {
    echo "Invalid or past visit date.";
    exit();
}

$visit_date = date('Y-m-d', $timestamp);
$visit_time = date('H:i:s', $timestamp);

// --- 5. Prevent duplicate bookings (client can book a visit ONCE per property) ---
$stmt = $pdo->prepare("SELECT COUNT(*) FROM client_onsite_visits WHERE client_id = ? AND property_id = ?");
$stmt->execute([$client_id, $property_id]);
if ($stmt->fetchColumn() > 0) {
    echo "You’ve already booked this visit.";
    exit();
}

// --- 6. Save new onsite visit for client ---
$stmt = $pdo->prepare("
    INSERT INTO client_onsite_visits (client_id, property_id, visit_date, visit_time)
    VALUES (?, ?, ?, ?)
");
$stmt->execute([$client_id, $property_id, $visit_date, $visit_time]);

// --- 7. Remove property from client cart after booking ---
$removeCart = $pdo->prepare("
    DELETE FROM client_cart WHERE client_id = ? AND property_id = ?
");
$removeCart->execute([$client_id, $property_id]);

// --- 8. Redirect client to profile with confirmation ---
header("Location: client-profile.php?visit_booked=1");
exit();
?>
