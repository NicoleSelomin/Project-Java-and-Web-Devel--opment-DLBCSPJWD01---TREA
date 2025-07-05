<?php
require_once 'check-user-session.php';
require 'service-upload-helper.php';
require_once 'notification-helper.php';

$properties = $_POST['properties'] ?? [];
$urgent = isset($_POST['urgent']) ? 1 : 0;
$service_level = $_POST['service_level'] ?? 'management_only';

if (empty($properties) || !$service_level) {
    $_SESSION['form_error'] = "Missing required fields.";
    header('Location: request-rental-property-management.php');
    exit;
}

$owner_id   = $_SESSION['owner_id'];
$owner_name = $_SESSION['user_name'];
$submitted_at = date('Y-m-d H:i:s');

// Lookup service_id/slug for rental property management
$slug        = 'rental_property_management';
$service     = getServiceInfo($slug, $pdo);
$service_id  = $service['service_id'];
$service_slug= $service['safe_name'];

// 1. Insert new application (multi-property group)
$stmt = $pdo->prepare("INSERT INTO owner_service_requests (
    owner_id, service_id, urgent, service_level, submitted_at
) VALUES (?, ?, ?, ?, ?)");
$stmt->execute([$owner_id, $service_id, $urgent, $service_level, $submitted_at]);
$request_id = $pdo->lastInsertId();

// --- 2. Loop through properties ---
$property_counter = 0;
foreach ($properties as $i => $prop) {
    $property_counter++;

    $property_name        = trim($prop['property_name'] ?? '');
    $location             = trim($prop['location'] ?? '');
    $description          = trim($prop['property_description'] ?? '');
    $number_of_bedrooms   = trim($prop['number_of_bedrooms'] ?? '');
    $number_of_bathrooms  = trim($prop['number_of_bathrooms'] ?? '');
    $floor_count          = trim($prop['floor_count'] ?? '');
    $use_for_the_property = trim($prop['use_for_the_property'] ?? '');
    $land_size            = trim($prop['land_size'] ?? '');
    $property_type        = $prop['property_type'] ?? '';
    $tenancy_history      = trim($prop['tenancy_history'] ?? '');
    $rental_expectation   = $prop['rental_expectation'] ?? null;
    $lease_terms          = trim($prop['lease_terms'] ?? '');
    $comments             = trim($prop['comments'] ?? '');

    // Validate required
    if (
        !$property_name || !$location || !$description || !$property_type ||
        !$rental_expectation || !$lease_terms || !$use_for_the_property
    ) {
        $_SESSION['form_error'] = "Missing required property fields.";
        header('Location: request-rental-property-management.php');
        exit;
    }

    // --- 2.1 Folder ---
    $base = createApplicationFolder($owner_id, $owner_name, $service_id, $service_slug, $request_id);
    $property_folder = $base . "property_$i/";
    if (!is_dir($property_folder)) mkdir($property_folder, 0775, true);

    // --- 2.2 File uploads ---
    $ownership_proof_path = saveFile(
        $_FILES['properties']['tmp_name'][$i]['ownership_proof'],
        $property_folder . 'ownership_proof.' . pathinfo($_FILES['properties']['name'][$i]['ownership_proof'], PATHINFO_EXTENSION)
    );
    $site_image_path = saveFile(
        $_FILES['properties']['tmp_name'][$i]['property_image'],
        $property_folder . 'site_image.' . pathinfo($_FILES['properties']['name'][$i]['property_image'], PATHINFO_EXTENSION)
    );
    // Additional docs (as JSON array)
    $additional_docs = [];
    if (!empty($_FILES['properties']['name'][$i]['additional_documents'])) {
        foreach ($_FILES['properties']['name'][$i]['additional_documents'] as $k => $docName) {
            if ($docName && is_uploaded_file($_FILES['properties']['tmp_name'][$i]['additional_documents'][$k])) {
                $docPath = $property_folder . 'extra_' . $k . '_' . $docName;
                move_uploaded_file($_FILES['properties']['tmp_name'][$i]['additional_documents'][$k], $docPath);
                $additional_docs[] = $docPath;
            }
        }
    }
    $additional_json = !empty($additional_docs) ? json_encode($additional_docs) : null;

    // --- 2.3 Insert rental property details ---
    $stmt2 = $pdo->prepare("INSERT INTO rental_property_management_details (
        request_id, property_name, location, property_description,
        number_of_bedrooms, number_of_bathrooms, floor_count, land_size, 
        ownership_proof_path, site_image_path, property_type, tenancy_history, rental_expectation, lease_terms, use_for_the_property, comments, service_level, additional_documents, property_folder
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt2->execute([
        $request_id, $property_name, $location, $description,
        $number_of_bedrooms, $number_of_bathrooms, $floor_count, $land_size,
        $ownership_proof_path, $site_image_path, $property_type, $tenancy_history,
        $rental_expectation, $lease_terms, $use_for_the_property, $comments,
        $service_level, $additional_json, $property_folder
    ]);
}

// 3. Insert pending application payment
$payment_method = 'offline';
$insertPayment = $pdo->prepare("
    INSERT INTO service_request_payments 
        (request_id, payment_type, payment_method, payment_status, created_at)
    VALUES (?, 'application', ?, 'pending', NOW())
");
$insertPayment->execute([$request_id, $payment_method]);

// 4. Notify staff and owner
notify(
    $pdo,
    $owner_id,
    'property_owner',
    'service_request_received',
    [
        '{service_name}'  => $service['display_name'] ?? 'rental_property_management',
        '{property_name}' => $property_name,
    ],
    "owner-service-requests.php?request_id=$request_id"
);

// Notify Property Manager or General Manager

$staff_stmt = $pdo->query("SELECT staff_id FROM staff WHERE role IN ('General Manager', 'Property Manager')");
foreach ($staff_stmt as $row) {
    notify(
        $pdo,
        $row['staff_id'],
        'staff',
        'service_request_submitted',
        [
            '{service_name}'  => $service['display_name'] ?? 'rental_proeprty_management',
            '{property_name}' => $property_name,
        ],
        "manage-service-requests.php?request_id=$request_id",
        true,
        $owner_id,
        'property_owner',
        $owner_name
    );
}
// 5. Redirect
header("Location: owner-profile.php?submitted=rental");
exit;
?>
