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
    'architecture_plan_drawing'  => 'architecture_plan_details',
    'legal_assistance'           => 'legal_assistance_details',
    'construction_supervision'   => 'construction_supervision_details',
    'rental_property_management' => 'rental_property_management_details',
    'sale_property_management'   => 'sale_property_management_details'
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
    'number_of_bedrooms', 'number_of_bathrooms', 'floor_count'
];
foreach ($sharedFields as $field) {
    $label = ucwords(str_replace('_', ' ', $field));
    $html .= displayRow($label, $field, $data, $service_specific);
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
        $html .= "<tr><th>Urgent Sale</th><td>" . (!empty($service_specific['urgent_sale']) ? 'Yes' : 'No') . "</td></tr>";
        break;
    case 'architecture_plan_drawing':
        $html .= displayRow("Project Title", "project_title", $service_specific);
        $html .= displayRow("Land Size", "land_size", $service_specific);
        $html .= displayRow("Building Type", "building_type", $service_specific);
        $html .= displayRow("Design Preferences", "design_preferences", $service_specific);
        break;
    case 'legal_assistance':
        $html .= displayRow("Request Type", "request_type", $service_specific);
        $html .= displayRow("Subject Property", "subject_property", $service_specific);
        $html .= displayRow("Location", "location", $service_specific);
        $html .= displayRow("Issue Description", "issue_description", $service_specific);
        break;
    case 'construction_supervision':
        $html .= displayRow("Building Type", "building_type", $service_specific);
        $html .= displayRow("Current Stage", "current_stage", $service_specific);
        $html .= displayRow("Supervision Needs", "supervision_needs", $service_specific);
        break;
    case 'rental_property_management':
        $html .= displayRow("Property Type", "property_type", $service_specific);
        $html .= displayRow("Tenancy History", "tenancy_history", $service_specific);
        $html .= displayRow("Service Level", "service_level", $service_specific);
        $html .= displayRow("Proposed Lease Terms", "lease_terms", $service_specific);
        $html .= displayRow("Estimated Price", "rental_expectation", $service_specific);
        $html .= "<tr><th>Urgent Request</th><td>" . (!empty($service_specific['urgent']) ? 'Yes' : 'No') . "</td></tr>";
        break;
    case 'sale_property_management':
        $html .= displayRow("Property Condition", "property_condition", $service_specific);
        $html .= displayRow("Ownership Type", "ownership_type", $service_specific);
        break;
}
$html .= "</table></body></html>";

// -----------------------------------------------------------------------------
// Step 6: Render and stream PDF
// -----------------------------------------------------------------------------
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// Prevent any stray output before streaming PDF
if (ob_get_length()) ob_end_clean();

$dompdf->stream("application_form_{$request_id}.pdf", ["Attachment" => false]);
exit;
