<?php
// -----------------------------------------------------------------------------
// client-book-visit.php
// -----------------------------------------------------------------------------
// Books an onsite property visit for a client and blocks the agent's slot
// -----------------------------------------------------------------------------
//
// - Checks login, property and agent availability
// - Blocks agent slot (status = 'blocked') to prevent double-booking
// - Prevents duplicate bookings per client/property
// - Removes property from client cart after booking
// -----------------------------------------------------------------------------

session_start();
require_once 'check-user-session.php';
require 'db_connect.php';

// 1. Ensure client is logged in
if (!isset($_SESSION['client_id'])) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header("Location: user-login.php");
    exit();
}

// 2. Get input: property_id and slot datetime
$client_id   = $_SESSION['client_id'];
$property_id = $_POST['property_id'] ?? $_GET['property_id'] ?? null;
$slot        = $_POST['visit_slot'] ?? $_GET['visit_slot'] ?? null;

if (!$property_id || !$slot) {
    die("Invalid booking request.");
}

// 3. Confirm property is still available
$stmt = $pdo->prepare("SELECT * FROM properties WHERE property_id = ? AND availability = 'available'");
$stmt->execute([$property_id]);
$property = $stmt->fetch();
if (!$property) {
    die("This property is no longer available.");
}

// 4. Confirm inspection agent is assigned for the property
$stmt = $pdo->prepare("SELECT assigned_agent_id FROM owner_service_requests WHERE property_id = ?");
$stmt->execute([$property_id]);
$inspectionAgentId = $stmt->fetchColumn();
if (!$inspectionAgentId) {
    die("No agent assigned for this property.");
}

// 5. Validate slot is in the future
$timestamp = strtotime($slot);
if (!$timestamp || $timestamp < time()) {
    die("Invalid or past visit slot.");
}
$visit_date = date('Y-m-d', $timestamp);
$visit_time = date('H:i:s', $timestamp);

// 6. Prevent duplicate bookings (client can't book same property twice)
$stmt = $pdo->prepare("SELECT COUNT(*) FROM client_onsite_visits WHERE client_id = ? AND property_id = ?");
$stmt->execute([$client_id, $property_id]);
if ($stmt->fetchColumn() > 0) {
    die("You’ve already booked this visit.");
}

// 7. Atomically check and block agent's slot (ensure it's still available)
$pdo->beginTransaction();

try {
    // Confirm slot is still available
    $stmt = $pdo->prepare("
        SELECT * FROM agent_schedule
        WHERE agent_id = ? AND start_time = ? AND status = 'available'
        FOR UPDATE
    ");
    $stmt->execute([$inspectionAgentId, $slot]);
    $slotRow = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$slotRow) {
        $pdo->rollBack();
        die("That slot is no longer available.");
    }

    // 8. Save new onsite visit
    $stmt = $pdo->prepare("
        INSERT INTO client_onsite_visits (client_id, property_id, visit_date, visit_time, assigned_agent_id)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$client_id, $property_id, $visit_date, $visit_time, $inspectionAgentId]);

    // 9. Block the agent’s slot
    $stmt = $pdo->prepare("
        UPDATE agent_schedule 
        SET status = 'blocked', notes = CONCAT(COALESCE(notes, ''), ' | Booked by client #$client_id') 
        WHERE agent_id = ? AND start_time = ?
    ");
    $stmt->execute([$inspectionAgentId, $slot]);

    // 10. Remove property from client cart
    $removeCart = $pdo->prepare("
        DELETE FROM client_cart WHERE client_id = ? AND property_id = ?
    ");
    $removeCart->execute([$client_id, $property_id]);

    $pdo->commit();

    // 11. Redirect client to profile with confirmation
    header("Location: client-profile.php?visit_booked=1");
    exit();

} catch (Exception $e) {
    $pdo->rollBack();
    die("An error occurred while booking your visit. Please try again.");
}
?>
