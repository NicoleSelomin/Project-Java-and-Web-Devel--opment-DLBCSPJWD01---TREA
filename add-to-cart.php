<?php
/*
|--------------------------------------------------------------------------
| add-to-cart.php
|--------------------------------------------------------------------------
| Handles adding a property to a client's cart (DB or session-based).
| - If client is logged in: adds to `client_cart` table (one per property).
| - If guest: adds property_id to session cart array.
| - Checks property is available before adding.
| - Redirects back to the referring page.
|
*/

session_start();
require 'db_connect.php';

header('Content-Type: application/json');

// Helper: respond as JSON and exit
function respond($success, $msg = '', $extra = []) {
    echo json_encode(array_merge(['success'=>$success, 'message'=>$msg], $extra));
    exit();
}

// Get property ID
$property_id = intval($_GET['id'] ?? 0);
if (!$property_id) respond(false, "Invalid property.");

// 1. Check property exists and is available
$stmt = $pdo->prepare("SELECT * FROM properties WHERE property_id = ? AND availability = 'available'");
$stmt->execute([$property_id]);
$property = $stmt->fetch();
if (!$property) respond(false, "Property is no longer available.");

// 2. Client cart logic
if (isset($_SESSION['client_id'])) {
    require_once 'check-user-session.php';
    $client_id = $_SESSION['client_id'];
    $stmt = $pdo->prepare("SELECT 1 FROM client_cart WHERE client_id = ? AND property_id = ?");
    $stmt->execute([$client_id, $property_id]);
    if ($stmt->fetch()) respond(false, "Already in cart.");
    $insert = $pdo->prepare("INSERT INTO client_cart (client_id, property_id) VALUES (?, ?)");
    $insert->execute([$client_id, $property_id]);
    respond(true, "Added to cart!");
} else {
    // Guest cart (session)
    if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
    if (in_array($property_id, $_SESSION['cart'])) respond(false, "Already in cart.");
    $_SESSION['cart'][] = $property_id;
    respond(true, "Added to cart!");
}
?>
