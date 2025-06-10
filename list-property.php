<?php
/**
 * --------------------------------------------------------------------------------
 * list-property.php
 * --------------------------------------------------------------------------------
 * This script publishes an owner's approved property request as an available listing.
 * It supports both brokerage and rental property management service types.
 * After successful listing, it marks the service request as "listed".
 * Redirects back to manage-service-requests.php on completion.
 * 
 * Requirements:
 * - db_connect.php (PDO connection)
 * - All input and outputs are server-side (no user form here)
 * 
 * --------------------------------------------------------------------------------
 */

// Include database connection
require 'db_connect.php';

// Step 1: Get and validate request ID from URL
$request_id = $_GET['request_id'] ?? null;
if (!$request_id) {
    // If the request ID is missing, halt execution with an error
    die("Missing request ID");
}

// Step 2: Fetch core service request data, including owner, property, and service slug
$stmt = $pdo->prepare("
  SELECT r.owner_id, r.service_id, r.request_id, r.property_name, r.location, r.property_description, s.slug,
         r.number_of_bedrooms, r.number_of_bathrooms, r.floor_count, r.land_size
  FROM owner_service_requests r
  JOIN services s ON r.service_id = s.service_id
  WHERE r.request_id = ?
");
$stmt->execute([$request_id]);
$data = $stmt->fetch();

if (!$data) {
    // If request not found in DB, exit with message
    die("Request not found");
}

// Step 3: Service type logic and details fetch
$slug = $data['slug'];
$details = []; // Will hold extra info depending on the service

if ($slug === 'brokerage') {
    // If the listing is via Brokerage, get more details from brokerage_details
    $stmt = $pdo->prepare("
        SELECT property_type, estimated_price, site_image_path, brokerage_purpose AS listing_type
        FROM brokerage_details
        WHERE request_id = ?
    ");
    $stmt->execute([$request_id]);
    $details = $stmt->fetch();

    // Determine listing type, price, and image
    $listing_type = $details['listing_type'] ?? 'sale';
    $price        = $details['estimated_price'] ?? 0;
    $property_type= $details['property_type'] ?? null;
    $image        = $details['site_image_path'] ?? null;

} elseif ($slug === 'rental_property_management') {
    // If the listing is a rental management, get details from rental_property_management_details
    $stmt = $pdo->prepare("
        SELECT property_type, rental_expectation AS estimated_price, listing_type, site_image_path
        FROM rental_property_management_details
        WHERE request_id = ?
    ");
    $stmt->execute([$request_id]);
    $details = $stmt->fetch();

    // Always set listing_type to 'rent' for rentals
    $listing_type = 'rent';
    $price        = $details['estimated_price'] ?? 0;
    $property_type= $details['property_type'] ?? null;
    $image        = $details['site_image_path'] ?? null;

} else {
    // If service type is unsupported, halt
    die("Unsupported service type for listing.");
}

// Step 4: Insert the property into the properties table as 'available'
$insert = $pdo->prepare("
    INSERT INTO properties 
    (owner_id, service_id, request_id, property_name, property_description, location, price, listing_type, 
    property_type, image, number_of_bedrooms, number_of_bathrooms, floor_count, size_sq_m, availability)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'available')
");
$insert->execute([
    $data['owner_id'],             // Property owner
    $data['service_id'],           // Service (brokerage/rental)
    $data['request_id'],           // Link back to service request
    $data['property_name'],        // Property name/title
    $data['property_description'], // Description
    $data['location'],             // Location
    $price,                        // Price, from service-specific table
    $listing_type,                 // 'rent' or 'sale'
    $property_type,                // e.g., house, apartment
    $image,                        // Main property image
    $data['number_of_bedrooms'],   // Bedrooms count
    $data['number_of_bathrooms'],  // Bathrooms count
    $data['floor_count'],          // Floors
    $data['land_size']             // Land size (size_sq_m)
]);

// Step 5: Mark the original service request as 'listed'
$update = $pdo->prepare("UPDATE owner_service_requests SET listed = 1 WHERE request_id = ?");
$update->execute([$request_id]);

// Step 6: Redirect to service management dashboard with success flag
header("Location: manage-service-requests.php?listed=success");
exit();
