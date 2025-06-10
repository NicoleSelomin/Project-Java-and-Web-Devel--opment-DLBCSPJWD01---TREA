<?php
/**
 * ============================================================================
 * submit-rental-management.php — TREA Real Estate Platform
 * ------------------------------------------------
 * ----------------------------------------------------------------------------
 * Handles backend processing for owner rental property management service 
 * applications on the TREA platform.
 *
 * Steps:
 *  - Collects and validates POSTed form fields for the rental property.
 *  - Creates a database placeholder for the service request.
 *  - Creates a unique folder for uploaded files using a consistent structure.
 *  - Saves all file uploads (ownership proof, property images, additional docs).
 *  - Updates the service request with file/folder info.
 *  - Inserts all specific rental details in rental_property_management_details.
 *  - Creates a pending application payment record (offline by default).
 *  - Notifies relevant staff for review/processing.
 *  - Redirects back to owner profile with success flag.
 *
 * Requirements:
 *  - User must be a logged-in property owner (see check-user-session.php).
 *  - Utilizes helper utilities for uploads, notification, and DB access.
 *  - Follows upload structure: 
 *      /uploads/owner/{owner_id}_{owner_name}/applications/{service_id}_{service_slug}/request_{request_id}/
 *  - All file names and structure are consistent across the platform.
 * ============================================================================
 */

// ----------------- SETUP & DEPENDENCIES -------------------
require_once 'check-user-session.php';                 // Session + access control for owner
require 'service-upload-helper.php';                    // File/folder helpers
require_once 'send-service-request-notifications.php';  // Notification system

// ----------------- COLLECT & VALIDATE FORM DATA -------------------
$property_name        = trim($_POST['property_name'] ?? '');
$location             = trim($_POST['location'] ?? '');
$description          = trim($_POST['property_description'] ?? '');
$number_of_bedrooms   = trim($_POST['number_of_bedrooms'] ?? '');
$number_of_bathrooms  = trim($_POST['number_of_bathrooms'] ?? '');
$floor_count          = trim($_POST['floor_count'] ?? '');
$land_size            = trim($_POST['land_size'] ?? '');
$property_type        = $_POST['property_type'] ?? 'N/A';
$tenancy_history      = trim($_POST['tenancy_history'] ?? '');
$rental_expectation   = $_POST['rental_expectation'] ?? null;
$lease_terms          = trim($_POST['lease_terms'] ?? '');
$comments             = trim($_POST['comments'] ?? '');
$urgent_rental        = isset($_POST['urgent_rental']) ? 1 : 0;
$service_level        = $_POST['service_level'] ?? 'management_only';

// Simple required fields validation (server-side backup for client JS validation)
if (
    !$property_name || !$location || !$description || !$property_type ||
    !$rental_expectation || !$tenancy_history || !$lease_terms || !$service_level
) {
    echo "Missing required fields.";
    exit();
}

// ----------------- SESSION & META DATA -------------------
$owner_id    = $_SESSION['owner_id'];
$owner_name  = $_SESSION['user_name'];
$submitted_at = date('Y-m-d H:i:s');

// ----------------- SERVICE INFO -------------------
// Lookup service_id/slug for rental property management
$slug        = 'rental_property_management';
$service     = getServiceInfo($slug, $pdo);
$service_id  = $service['service_id'];
$service_slug= $service['safe_name'];

// ----------------- 1. INSERT BLANK SERVICE REQUEST -------------------
$stmt = $pdo->prepare("INSERT INTO owner_service_requests (
    owner_id, service_id, property_name, location, property_description, 
    number_of_bedrooms, number_of_bathrooms, floor_count, land_size, submitted_at
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->execute([
    $owner_id, $service_id, $property_name, $location, $description,
    $number_of_bedrooms, $number_of_bathrooms, $floor_count, $land_size, $submitted_at
]);
$request_id = $pdo->lastInsertId();

// ----------------- 2. CREATE UPLOAD FOLDER STRUCTURE -------------------
$base = createApplicationFolder($owner_id, $owner_name, $service_id, $service_slug, $request_id); // With trailing slash

// ----------------- 3. HANDLE FILE UPLOADS -------------------
// Save ownership proof and property image (required/optional)
$ownership_proof_path = saveFile(
    $_FILES['ownership_proof'], 
    $base . 'ownership_proof.' . pathinfo($_FILES['ownership_proof']['name'], PATHINFO_EXTENSION)
);
$site_image_path = saveFile(
    $_FILES['property_image'], 
    $base . 'site_image.' . pathinfo($_FILES['property_image']['name'], PATHINFO_EXTENSION)
);

// Save additional documents (optional, as JSON array)
$additional_docs = saveMultipleFiles($_FILES['additional_documents'] ?? [], $base, 'extra');
$additional_json = !empty($additional_docs) ? json_encode($additional_docs) : null;

// ----------------- 4. UPDATE owner_service_requests WITH FILE DATA -------------------
$update = $pdo->prepare("UPDATE owner_service_requests 
    SET application_folder = ?, additional_documents = ? 
    WHERE request_id = ?");
$update->execute([$base, $additional_json, $request_id]);

// ----------------- 5. INSERT RENTAL PROPERTY DETAILS -------------------
$stmt2 = $pdo->prepare("INSERT INTO rental_property_management_details (
    request_id, ownership_proof_path, site_image_path, 
    property_type, tenancy_history, rental_expectation, lease_terms, urgent, comments, service_level
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt2->execute([
    $request_id,
    $ownership_proof_path,
    $site_image_path,
    $property_type,
    $tenancy_history,
    $rental_expectation,
    $lease_terms,
    $urgent_rental,
    $comments,
    $service_level
]);

// ----------------- 6. INSERT PENDING APPLICATION PAYMENT -------------------
$payment_method = 'offline'; // All handled offline by default
$insertPayment = $pdo->prepare("
    INSERT INTO service_request_payments 
        (request_id, payment_type, payment_method, payment_status, created_at)
    VALUES (?, 'application', ?, 'pending', NOW())
");
$insertPayment->execute([$request_id, $payment_method]);

// ----------------- 7. NOTIFY STAFF -------------------
sendServiceRequestNotifications(
    $pdo, $owner_id, $owner_name, $service, $property_name, $slug
);

// ----------------- 8. REDIRECT TO OWNER PROFILE -------------------
header("Location: owner-profile.php?submitted=rental");
exit();
