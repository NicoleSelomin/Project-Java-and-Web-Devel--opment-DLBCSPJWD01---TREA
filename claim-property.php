<?php
// -----------------------------------------------------------------------------
// claim-property.php
// -----------------------------------------------------------------------------
// Handles a client claim for a property after a manager-approved visit.
// - Checks validity and prevents duplicate claims
// - Creates claim records and initial payment entries as needed
// - Redirects user based on claim source (brokerage, rental mgmt, etc.)
// -----------------------------------------------------------------------------

session_start();
require_once 'check-user-session.php';
require 'db_connect.php';

// --- Get required input values ---
$client_id   = $_SESSION['client_id'] ?? null;
$visit_id    = $_POST['visit_id'] ?? null;
$property_id = $_POST['property_id'] ?? null;

if (!$client_id || !$visit_id || !$property_id) {
    die("Invalid request.");
}

// --- 1. Ensure the visit is manager-approved before claiming ---
$visitStmt = $pdo->prepare("
    SELECT final_status 
    FROM client_onsite_visits 
    WHERE visit_id = ? AND client_id = ?
");
$visitStmt->execute([$visit_id, $client_id]);
$visit = $visitStmt->fetch(PDO::FETCH_ASSOC);

if (!$visit || strtolower($visit['final_status']) !== 'approved') {
    die("Only visits approved by the manager can be claimed.");
}

// --- 2. Fetch property details, including listing type and service slug ---
$propertyStmt = $pdo->prepare("
    SELECT p.listing_type, s.slug 
    FROM properties p 
    JOIN services s ON p.service_id = s.service_id 
    WHERE p.property_id = ?
");
$propertyStmt->execute([$property_id]);
$property = $propertyStmt->fetch(PDO::FETCH_ASSOC);

if (!$property) {
    die("Property not found.");
}

$listing_type = strtolower($property['listing_type']); // 'rent' or 'sale'
$claim_source = strtolower($property['slug']);         // e.g. 'brokerage', 'rental_property_management'

// --- 3. Prevent duplicate claim by this client for this property/visit ---
$existingClaim = $pdo->prepare("
    SELECT claim_id 
    FROM client_claims 
    WHERE client_id = ? AND property_id = ? AND visit_id = ?
");
$existingClaim->execute([$client_id, $property_id, $visit_id]);
if ($existingClaim->fetch()) {
    header("Location: client-visits.php?already_claimed=1");
    exit();
}

// --- 4. Insert claim record ---
$insert = $pdo->prepare("
    INSERT INTO client_claims (client_id, property_id, visit_id, claim_type, claim_source, claim_status)
    VALUES (?, ?, ?, ?, ?, 'claimed')
");
$insert->execute([
    $client_id,
    $property_id,
    $visit_id,
    $listing_type,   // 'rent' or 'sale'
    $claim_source    // 'brokerage', etc.
]);
$claim_id = $pdo->lastInsertId();

// --- 5. Auto-create payment records based on claim source/type ---

// For brokerage properties: create claim payment for client or owner
if ($claim_source === 'brokerage') {
    $payment_type = ($listing_type === 'rent') ? 'client' : 'owner';

    $insertPay = $pdo->prepare("
        INSERT INTO brokerage_claim_payments (claim_id, payment_type, payment_status) 
        VALUES (?, ?, 'pending')
    ");
    $insertPay->execute([$claim_id, $payment_type]);
}

// For managed rentals: create payment records for claim, deposit, and rent
if ($claim_source === 'rental_property_management' && $listing_type === 'rent') {
    $insertRentalPayments = $pdo->prepare("
        INSERT INTO rental_claim_payments (claim_id, payment_type, payment_status)
        VALUES 
            (?, 'claim', 'pending'),
            (?, 'deposit', 'pending'),
            (?, 'rent', 'pending')
    ");
    $insertRentalPayments->execute([$claim_id, $claim_id, $claim_id]);
}

// --- 6. Redirect user based on claim source ---
switch ($claim_source) {
    case 'brokerage':
        header("Location: client-claimed-brokerage.php?claimed=1");
        break;
    case 'sale_property_management':
        header("Location: client-claimed-sale-management.php?claimed=1");
        break;
    case 'rental_property_management':
        header("Location: client-claimed-rental-management.php?claimed=1");
        break;
    default:
        header("Location: client-profile.php?claimed=1");
}
exit();
?>
