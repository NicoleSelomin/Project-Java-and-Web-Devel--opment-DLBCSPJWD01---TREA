<?php
/*
|--------------------------------------------------------------------------
| remove-from-cart.php
|--------------------------------------------------------------------------
| Handles removal of a property from the user's shopping cart.
| - If the user is logged in as a client, removes the item from the database.
| - If the user is a guest, removes the item from the session-based cart array.
| - Redirects back to the cart view after processing.
|--------------------------------------------------------------------------
*/

// Session and database initialization
session_start();
require 'db_connect.php';

// ------------------------------------------------------------------
// Retrieve and validate the property ID from POST data
// ------------------------------------------------------------------
$property_id = intval($_POST['property_id'] ?? 0);
if (!$property_id) {
    // Invalid property, redirect back to cart
    header("Location: view-cart.php");
    exit();
}

// ------------------------------------------------------------------
// Remove property from cart (DB for clients, session for guests)
// ------------------------------------------------------------------
if (isset($_SESSION['client_id'])) {
    // Logged-in client: remove from client_cart table in DB
    $stmt = $pdo->prepare("DELETE FROM client_cart WHERE client_id = ? AND property_id = ?");
    $stmt->execute([$_SESSION['client_id'], $property_id]);
} else {
    // Guest: remove from cart array stored in session
    if (isset($_SESSION['cart'])) {
        $_SESSION['cart'] = array_filter(
            $_SESSION['cart'],
            function ($id) use ($property_id) {
                return $id != $property_id;
            }
        );
        // Reindex to keep array tidy
        $_SESSION['cart'] = array_values($_SESSION['cart']);
    }
}

// ------------------------------------------------------------------
// Redirect back to the cart page
// ------------------------------------------------------------------
header("Location: view-cart.php");
exit();
