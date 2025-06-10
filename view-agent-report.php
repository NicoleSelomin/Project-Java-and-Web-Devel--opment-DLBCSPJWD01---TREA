<?php
/**
 * view-agent-report.php
 * ----------------------------------------------------------
 * Generates a PDF report for either a client onsite visit or
 * a property inspection, using Dompdf.
 * 
 * Input (via GET):
 *   - type:     ('visit' | 'inspection')
 *   - id:       (integer, required) report or request ID
 * 
 * Output:
 *   - PDF is rendered in browser (not force-download)
 * 
 * Usage:
 *   - Called from view/report pages to export visit or inspection details as PDF.
 * 
 * Styling and output structure are designed for clarity and consistency
 * with TREA platform documentation standards.
 * 
 * ----------------------------------------------------------
 */

require 'db_connect.php';
require 'libs/dompdf/autoload.inc.php';

use Dompdf\Dompdf;

// --------------- Input Validation ---------------

$type = $_GET['type'] ?? '';
$id = intval($_GET['id'] ?? 0);

if (!$type || !$id) {
    // Invalid or missing parameters
    die("Invalid request.");
}

// --------------- Dompdf Initialization ---------------

$dompdf = new Dompdf();
$html = '';
$title = '';

// --------------- Fetch Data & Build HTML ---------------

if ($type === 'visit') {
    // ----------- Client Onsite Visit Report -----------

    $stmt = $pdo->prepare("
        SELECT v.*, c.full_name AS client_name, p.property_name, p.location
        FROM client_onsite_visits v
        JOIN clients c ON v.client_id = c.client_id
        JOIN properties p ON v.property_id = p.property_id
        WHERE v.visit_id = ?
    ");
    $stmt->execute([$id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$data) die("Visit not found.");

    $title = "Client Visit Report";

    // Use Bootstrap 5 minimal structure for consistency, but avoid unnecessary markup/icons
    $html = "
        <style>
            body { font-family: Arial, sans-serif; font-size: 15px; }
            h2 { margin-top: 0; font-size: 1.6rem; }
            .section { margin-bottom: 18px; }
            strong { font-weight: 600; }
        </style>
        <h2>Client Visit Report</h2>
        <div class='section'><strong>Client:</strong> {$data['client_name']}</div>
        <div class='section'><strong>Property:</strong> {$data['property_name']} - {$data['location']}</div>
        <div class='section'><strong>Visit Date:</strong> {$data['visit_date']}</div>
        <div class='section'><strong>Assigned Agent:</strong> {$data['assigned_agent_id']}</div>
        <div class='section'><strong>Status:</strong> " . ucfirst($data['status']) . "</div>
        <div class='section'><strong>Agent Feedback:</strong><br>" . nl2br(htmlspecialchars($data['agent_feedback'] ?? '')) . "</div>
    ";

} elseif ($type === 'inspection') {
    // ----------- Property Inspection Report -----------

    $stmt = $pdo->prepare("
        SELECT r.*, o.full_name AS owner_name, s.service_name
        FROM owner_service_requests r
        JOIN owners o ON r.owner_id = o.owner_id
        JOIN services s ON r.service_id = s.service_id
        WHERE r.request_id = ?
    ");
    $stmt->execute([$id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$data) die("Inspection not found.");

    $title = "Inspection Report";

    $html = "
        <style>
            body { font-family: Arial, sans-serif; font-size: 15px; }
            h2 { margin-top: 0; font-size: 1.6rem; }
            .section { margin-bottom: 18px; }
            strong { font-weight: 600; }
        </style>
        <h2>Property Inspection Report</h2>
        <div class='section'><strong>Owner:</strong> {$data['owner_name']}</div>
        <div class='section'><strong>Service:</strong> {$data['service_name']}</div>
        <div class='section'><strong>Property:</strong> {$data['property_name']} - {$data['location']}</div>
        <div class='section'><strong>Inspection Date:</strong> {$data['inspection_date']}</div>
        <div class='section'><strong>Result:</strong> " . ucfirst($data['inspection_result']) . "</div>
        <div class='section'><strong>Agent Feedback:</strong><br>" . nl2br(htmlspecialchars($data['inspection_report'] ?? '')) . "</div>
    ";

} else {
    // Unknown report type
    die("Unknown report type.");
}

// --------------- Render PDF ---------------

// Load HTML content into Dompdf
$dompdf->loadHtml($html);

// Set PDF paper size (A4, portrait)
$dompdf->setPaper('A4', 'portrait');

// Render PDF in memory
$dompdf->render();

// Output PDF inline in browser (change Attachment to true to force download)
$dompdf->stream($title, ["Attachment" => false]);

exit;
