<?php
/**
 * ============================================================================
 * submit-brokerage.php — TREA Real Estate Platform
 * ------------------------------------------------
 * Handles brokerage service request submissions for property owners:
 * - Validates required input.
 * - Inserts request into owner_service_requests and brokerage_details.
 * - Uploads/organizes documents and images in a structured folder.
 * - Creates a default pending offline payment.
 * - Sends notification to staff.
 * - Redirects owner to profile on success.
 * -------------------------------------------------------------------------------
 */

require_once 'check-user-session.php';                 // Owner authentication/session
require_once 'send-service-request-notifications.php';  // Staff notification utility
require_once 'service-upload-helper.php';               // File/folder helper functions

// 1. Fetch session/user data
$owner_id   = $_SESSION['owner_id'];
$owner_name = $_SESSION['user_name'];
$submitted_at = date('Y-m-d H:i:s');

// 2. Lookup service info by slug ('brokerage')
$slug = 'brokerage';
$service = getServiceInfo($slug, $pdo);
$service_id   = $service['service_id'];
$service_slug = $service['safe_name'];

// 3. Gather POST fields
$property_name        = trim($_POST['property_name'] ?? '');
$location             = trim($_POST['location'] ?? '');
$property_type        = trim($_POST['property_type'] ?? '');
$property_description = trim($_POST['property_description'] ?? '');
$number_of_bedrooms   = trim($_POST['number_of_bedrooms'] ?? '');
$number_of_bathrooms  = trim($_POST['number_of_bathrooms'] ?? '');
$floor_count          = trim($_POST['floor_count'] ?? '');
$land_size            = trim($_POST['land_size'] ?? '');
$brokerage_purpose    = trim($_POST['brokerage_purpose'] ?? '');
$estimated_price      = floatval($_POST['estimated_price'] ?? 0);
$reason_for_sale      = trim($_POST['reason_for_sale'] ?? '');
$urgent_sale          = isset($_POST['urgent_sale']) ? 1 : 0;
$comments             = trim($_POST['comments'] ?? '');

// 4. Validate required fields (server-side)
if (
    !$property_name ||
    !$location ||
    !$property_type ||
    !$brokerage_purpose ||
    !$property_description ||
    $estimated_price <= 0
) {
    echo "Please fill in all required fields.";
    exit();
}

// 5. Insert into owner_service_requests table (base/general)
$stmt = $pdo->prepare("
    INSERT INTO owner_service_requests (
        owner_id, service_id, property_name, location, property_description,
        number_of_bedrooms, number_of_bathrooms, floor_count, land_size, submitted_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");
$stmt->execute([
    $owner_id,
    $service_id,
    $property_name,
    $location,
    $property_description,
    $number_of_bedrooms,
    $number_of_bathrooms,
    $floor_count,
    $land_size,
    $submitted_at
]);

$request_id = $pdo->lastInsertId();

// 6. Create unique application folder using helper
$base = createApplicationFolder($owner_id, $owner_name, $service_id, $service_slug, $request_id);

// 7. Handle file uploads (uses helpers, places files in $base)
$site_image_path = saveFile(
    $_FILES['property_image'],
    $base . 'site_image.' . pathinfo($_FILES['property_image']['name'], PATHINFO_EXTENSION)
);

// Allow for multiple optional docs (stored as array, then JSON)
$additional_docs = saveMultipleFiles($_FILES['additional_documents'], $base, 'extra');
$additional_json = !empty($additional_docs) ? json_encode($additional_docs) : null;

// 8. Update application record with folder path and doc info
$update = $pdo->prepare("
    UPDATE owner_service_requests
    SET application_folder = ?, additional_documents = ?
    WHERE request_id = ?
");
$update->execute([$base, $additional_json, $request_id]);

// 9. Insert details into brokerage_details table
$stmt2 = $pdo->prepare("
    INSERT INTO brokerage_details (
        request_id, property_type, brokerage_purpose,
        estimated_price, reason_for_sale, urgent_sale, comments, site_image_path
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
");
$stmt2->execute([
    $request_id,
    $property_type,
    $brokerage_purpose,
    $estimated_price,
    $reason_for_sale,
    $urgent_sale,
    $comments,
    $site_image_path
]);

// 10. Add default 'offline' pending application payment (always offline here)
$insertPayment = $pdo->prepare("
    INSERT INTO service_request_payments 
    (request_id, payment_type, payment_method, payment_status, created_at)
    VALUES (?, 'application', ?, 'pending', NOW())
");
$insertPayment->execute([$request_id, 'offline']);

// 11. Notify staff (email, dashboard, etc)
sendServiceRequestNotifications(
    $pdo, $owner_id, $owner_name, $service, $property_name, $slug
);

// 12. Redirect owner to profile (with success param)
header("Location: owner-profile.php?submitted=brokerage");
exit();
?>
