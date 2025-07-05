<?php
/**
 * ------------------------------------------------------------------------
 * generate-application-pdf.php
 * ------------------------------------------------------------------------
 * 
 * Generate Application PDF
 *
 * Generates a styled PDF for an owner service request, using Dompdf.
 * - Validates and fetches request/application info
 * - Loads service-specific and shared fields
 * - Renders as a HTML table
 * - Outputs PDF to browser
 *
 * Dependencies:
 * - Dompdf (autoloaded)
 * - db_connect.php (PDO $pdo)
 */

require_once 'libs/dompdf/autoload.inc.php';
require 'db_connect.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// -----------------------------------------------------------------------------
// Step 1: Validate and fetch request ID from query string
// -----------------------------------------------------------------------------
if (!isset($_GET['id'])) {
    die("Request ID is required.");
}
$request_id = (int) $_GET['id'];

// -----------------------------------------------------------------------------
// Step 2: Fetch general service request data (joins with owner, user, service)
// -----------------------------------------------------------------------------
$stmt = $pdo->prepare("
    SELECT r.*, u.full_name AS owner_name, u.user_id, s.service_name, s.slug AS service_slug
    FROM owner_service_requests r
    JOIN owners o ON r.owner_id = o.owner_id
    JOIN users u ON o.user_id = u.user_id
    JOIN services s ON r.service_id = s.service_id
    WHERE r.request_id = ?
");
$stmt->execute([$request_id]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$data) {
    die("Application not found.");
}

// -----------------------------------------------------------------------------
// Step 3: Fetch service-specific details (based on service_slug)
// -----------------------------------------------------------------------------
$service_slug = $data['service_slug'];
$service_specific = [];
$tableMap = [
    'brokerage'                  => 'brokerage_details',
    'rental_property_management' => 'rental_property_management_details',
];

if (isset($tableMap[$service_slug])) {
    $stmt2 = $pdo->prepare("SELECT * FROM {$tableMap[$service_slug]} WHERE request_id = ?");
    $stmt2->execute([$request_id]);
    $service_specific = $stmt2->fetch(PDO::FETCH_ASSOC) ?: [];
}

// -----------------------------------------------------------------------------
// Helper: Renders one table row (tries service-specific, then general)
// -----------------------------------------------------------------------------
function displayRow($label, $field, $primary = [], $fallback = []) {
    $value = $primary[$field] ?? $fallback[$field] ?? '—';
    return "<tr><th>{$label}</th><td>" . htmlspecialchars($value) . "</td></tr>";
}

// -----------------------------------------------------------------------------
// Step 4: Build the HTML for the PDF (styled table)
// -----------------------------------------------------------------------------
$html = <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
    body { font-family: Arial, sans-serif; }
    h2 { color: #0056b3; }
    table { width: 100%; border-collapse: collapse; margin-top: 15px; }
    td, th { border: 1px solid #ccc; padding: 8px; text-align: left; }
    th { background-color: #f0f0f0; width: 30%; }
</style>
</head>
<body>
<h2>Service Application Form</h2>
<table>
HTML;

// General info
$html .= displayRow("Owner", "owner_name", $data);
$html .= displayRow("Service", "service_name", $data);
$html .= displayRow("Submitted At", "submitted_at", $data);
$html .= displayRow("Payment Method", "payment_method", $data);
$html .= displayRow("Application Fee", "application_fee", $data);

// Shared property fields
$sharedFields = [
    'property_name', 'location', 'property_description', 'land_size', 'comments',
    'number_of_bedrooms', 'number_of_bathrooms', 'floor_count', 'urgent'
];
foreach ($sharedFields as $field) {
    $label = ucwords(str_replace('_', ' ', $field));
    $html .= displayRow($label, $field, $data, $service_specific);
}

if ($service_slug === 'rental_property_management' && is_array($service_specific) && count($service_specific) > 0) {
    foreach ($service_specific as $idx => $property) {
        $html .= "<h3 style='margin-top:24px;'>Property " . ($idx+1) . "</h3>";
        $html .= "<table>";
        $propertyFields = [
            'property_name' => 'Property Name',
            'location' => 'Location',
            'property_type' => 'Property Type',
            'property_description' => 'Description',
            'number_of_bedrooms' => 'Number of Bedrooms',
            'number_of_bathrooms' => 'Number of Bathrooms',
            'floor_count' => 'Floor Count',
            'use_for_the_property' => 'Use',
            'land_size' => 'Land Size (sq m)',
            'tenancy_history' => 'Tenancy History',
            'rental_expectation' => 'Expected Monthly Rent',
            'lease_terms' => 'Lease Terms',
            'comments' => 'Additional Comments',
            'service_level' => 'Service Level',
            'urgent' => 'Urgent',
        ];
        foreach ($propertyFields as $field => $label) {
            $val = isset($property[$field]) ? htmlspecialchars($property[$field]) : '—';
            // Prettify "urgent"
            if ($field === 'urgent') $val = $val == 1 ? "Yes" : "No";
            $html .= "<tr><th>$label</th><td>$val</td></tr>";
        }
        $html .= "</table>";
    }
} else {
    // fallback: display a single property (legacy, or brokerage, or other service)
    foreach ($sharedFields as $field) {
        $label = ucwords(str_replace('_', ' ', $field));
        $html .= displayRow($label, $field, $data, $service_specific[0] ?? []);
    }
// -----------------------------------------------------------------------------
// Step 5: Service-specific fields
// -----------------------------------------------------------------------------
switch ($service_slug) {
    case 'brokerage':
        $html .= displayRow("Property Type", "property_type", $service_specific);
        $html .= displayRow("Brokerage Purpose", "brokerage_purpose", $service_specific);
        $html .= displayRow("Estimated Price", "estimated_price", $service_specific);
        $html .= displayRow("Reason for Sale", "reason_for_sale", $service_specific);
        break;
    case 'rental_property_management':
        $html .= displayRow("Property Type", "property_type", $service_specific);
        $html .= displayRow("Tenancy History", "tenancy_history", $service_specific);
        $html .= displayRow("Service Level", "service_level", $service_specific);
        $html .= displayRow("Proposed Lease Terms", "lease_terms", $service_specific);
        $html .= displayRow("Estimated Price", "rental_expectation", $service_specific);
        break;
}
$html .= "</table></body></html>";}