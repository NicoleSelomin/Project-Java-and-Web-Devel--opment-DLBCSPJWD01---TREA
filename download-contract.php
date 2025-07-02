<?php
session_start();
require 'db_connect.php';
require_once 'libs/dompdf/autoload.inc.php';

use Dompdf\Dompdf;

// Ensure the user is authenticated (owner or client)
if (!isset($_SESSION['user_id'], $_SESSION['role']) || !in_array($_SESSION['role'], ['owner', 'client', 'general manager', 'property manager'])) {
    http_response_code(403);
    exit('Unauthorized access.');
}

// Get request ID
$request_id = $_GET['contract_id'] ?? null;
if (!$request_id) {
    http_response_code(400);
    exit('Missing contract ID.');
}

// Fetch contract details with service slug
$stmt = $pdo->prepare("SELECT r.*, s.slug, u.full_name, c.client_id, c.client_name FROM owner_service_requests r JOIN services s ON r.service_id = s.service_id JOIN properties p ON r.request_id = p.request_id JOIN client_claims cl ON cl.property_id = p.property_id JOIN clients c ON cl.client_id = c.client_id JOIN users u ON c.user_id = u.user_id WHERE r.request_id = ?");
$stmt->execute([$request_id]);
$contract = $stmt->fetch();

if (!$contract) {
    http_response_code(404);
    exit('Contract not found.');
}

$slug = $contract['slug'] ?? '';
$client_id = $contract['client_id'] ?? null;
$client_name = $contract['client_name'] ?? null;
$claim_id = $contract['claim_id'] ?? null;

// Check required signatures
$owner_signed  = !empty($contract['owner_signature']);
$client_signed = !empty($contract['client_signature']);
$agency_signed = !empty($contract['agency_signature']);

if ($slug === 'rental_property_management') {
    if (!$owner_signed || !$client_signed) {
        exit('Both owner and client signatures are required before downloading.');
    }
} else {
    if (!$owner_signed || !$agency_signed) {
        exit('Owner and agency signatures are required before downloading.');
    }
}

// Load contract HTML
$contract_path = $contract['owner_contract_path'];
if (!$contract_path || !file_exists($contract_path)) {
    exit('Contract document is missing.');
}
$contract_html = file_get_contents($contract_path);

// Replace signature placeholders
$contract_html = str_replace('{{OWNER_SIGNATURE_BLOCK}}', '<img src="' . htmlspecialchars($contract['owner_signature']) . '" style="max-height:100px;">', $contract_html);
if ($slug === 'rental_property_management') {
    $contract_html = str_replace('{{CLIENT_SIGNATURE_BLOCK}}', '<img src="' . htmlspecialchars($contract['client_signature']) . '" style="max-height:100px;">', $contract_html);
} else {
    $contract_html = str_replace('{{AGENCY_SIGNATURE_BLOCK}}', '<img src="' . htmlspecialchars($contract['agency_signature']) . '" style="max-height:100px;">', $contract_html);
}

// Generate PDF
$dompdf = new Dompdf();
$dompdf->loadHtml($contract_html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// Save PDF path
if ($slug === 'rental_property_management' && $client_id && $client_name && $claim_id) {
    $safe_name = preg_replace("/[^a-zA-Z0-9_-]/", "_", $client_name);
    $pdf_dir = __DIR__ . "/uploads/clients/{$client_id}_{$safe_name}/rental_claims/";
    if (!is_dir($pdf_dir)) mkdir($pdf_dir, 0777, true);
    $pdf_filename = "claim_{$claim_id}-contract.pdf";
    $pdf_path = $pdf_dir . $pdf_filename;
} else {
    $pdf_dir = dirname($contract_path) . '/';
    $pdf_filename = "final-signed-contract-request-{$request_id}.pdf";
    $pdf_path = $pdf_dir . $pdf_filename;
}

file_put_contents($pdf_path, $dompdf->output());

// Update database
$stmt = $pdo->prepare("UPDATE owner_service_requests SET signed_contract_path = ? WHERE request_id = ?");
$stmt->execute([$pdf_path, $request_id]);

// Serve the PDF file
header("Content-Type: application/pdf");
header("Content-Disposition: attachment; filename=\"{$pdf_filename}\"");
readfile($pdf_path);
exit();
