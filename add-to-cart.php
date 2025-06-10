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

// Get property ID from GET params and validate as integer
$property_id = intval($_GET['id'] ?? 0);
if (!$property_id) {
    header("Location: index.php");
    exit();
}

// 1. Check if property is available before adding to cart
$stmt = $pdo->prepare("SELECT * FROM properties WHERE property_id = ? AND availability = 'available'");
$stmt->execute([$property_id]);
$property = $stmt->fetch();

if (!$property) {
    // Stop if property does not exist or is unavailable
    exit("This property is no longer available.");
}

// 2. If client is logged in, add property to database cart
if (isset($_SESSION['client_id'])) {
    require_once 'check-user-session.php';
    $client_id = $_SESSION['client_id'];

    // Check if property is already in client's cart
    $stmt = $pdo->prepare("SELECT 1 FROM client_cart WHERE client_id = ? AND property_id = ?");
    $stmt->execute([$client_id, $property_id]);

    // If not in cart, insert into client_cart table
    if (!$stmt->fetch()) {
        $insert = $pdo->prepare("INSERT INTO client_cart (client_id, property_id) VALUES (?, ?)");
        $insert->execute([$client_id, $property_id]);
    }
} else {
    // 3. If guest (not logged in), use session cart array
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    // Only add if not already in the session cart
    if (!in_array($property_id, $_SESSION['cart'])) {
        $_SESSION['cart'][] = $property_id;
    }
}

// 4. Redirect back to the previous page (HTTP referer)
header("Location: " . $_SERVER['HTTP_REFERER']);
exit();
?>
