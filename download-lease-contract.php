<?php
session_start();
require 'db_connect.php';
require_once 'libs/dompdf/autoload.inc.php';

use Dompdf\Dompdf;

// 1. AUTHORIZATION
$allowed_roles = ['client', 'owner', 'general manager', 'property manager'];
$user_role = strtolower($_SESSION['role'] ?? $_SESSION['user_type'] ?? '');
if ((!isset($_SESSION['user_id']) && !isset($_SESSION['staff_id'])) || !in_array($user_role, $allowed_roles)) {
    http_response_code(403);
    exit('Unauthorized access.');
}

// 2. INPUT
$claim_id = intval($_GET['claim_id'] ?? 0);
if (!$claim_id) {
    http_response_code(400);
    exit('Missing claim ID.');
}

// 3. FETCH CONTRACT
$stmt = $pdo->prepare("
    SELECT rc.*, cc.client_id, cu.full_name AS client_name, p.property_name, cc.property_id, p.owner_id, uo.full_name AS owner_name
    FROM rental_contracts rc
    JOIN client_claims cc ON rc.claim_id = cc.claim_id
    JOIN clients c ON cc.client_id = c.client_id
    JOIN users cu ON c.user_id = cu.user_id
    JOIN properties p ON cc.property_id = p.property_id
    JOIN owners o ON p.owner_id = o.owner_id
    JOIN users uo ON o.user_id = uo.user_id
    WHERE rc.claim_id = ?
");
$stmt->execute([$claim_id]);
$contract = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$contract) {
    http_response_code(404);
    exit('Lease contract not found.');
}

// 4. CHECK REQUIRED SIGNATURES
if (empty($contract['owner_signature']) || empty($contract['client_signature'])) {
    exit('Both owner and client signatures are required before downloading.');
}

// --- Sanitize names for paths ---
function slugify($string) {
    return preg_replace('/[^a-zA-Z0-9_\-]/', '_', strtolower(trim($string)));
}
$client_id   = $contract['client_id'];
$client_name = slugify($contract['client_name']);
$owner_id    = $contract['owner_id'];
$owner_name  = slugify($contract['owner_name']);
$property_id = $contract['property_id'];
$property_name = slugify($contract['property_name']);

// --- Find the contract HTML file ---
$contract_html_path = '';
if (!empty($contract['contract_signed_path']) && file_exists(__DIR__ . '/' . $contract['contract_signed_path'])) {
    $contract_html_path = __DIR__ . '/' . $contract['contract_signed_path'];
} else {
    // Try to reconstruct
    $try_path = __DIR__ . "/uploads/clients/{$client_id}_{$client_name}/reserved_properties/{$property_id}_{$property_name}/contract.html";
    if (file_exists($try_path)) {
        $contract_html_path = $try_path;
    }
}
if (!$contract_html_path || !file_exists($contract_html_path)) {
    http_response_code(404);
    exit('Signed contract file is missing. Please contact support.');
}
$contract_html = file_get_contents($contract_html_path);

// --- Double check: replace signatures and dates if needed ---
$signature_blocks = [
    'OWNER_SIGNATURE_BLOCK'  => 'owner_signature',
    'CLIENT_SIGNATURE_BLOCK' => 'client_signature'
];
foreach ($signature_blocks as $block => $col) {
    $sig = !empty($contract[$col])
        ? '<img src="' . htmlspecialchars($contract[$col]) . '" style="max-height:100px;">'
        : '<span>Pending signature...</span>';
    $contract_html = str_replace('{{'.$block.'}}', $sig, $contract_html);
}
$contract_html = str_replace('{{OWNER_SIGNATURE_DATE}}',
    $contract['owner_signed_at'] ? date('Y-m-d', strtotime($contract['owner_signed_at'])) : 'Not yet signed',
    $contract_html);
$contract_html = str_replace('{{CLIENT_SIGNATURE_DATE}}',
    $contract['client_signed_at'] ? date('Y-m-d', strtotime($contract['client_signed_at'])) : 'Not yet signed',
    $contract_html);

// 7. GENERATE PDF
$dompdf = new Dompdf();
$dompdf->loadHtml($contract_html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// 8. SAVE PDF TO FILESYSTEM (owner and client folders)
$client_pdf_dir = __DIR__ . "/uploads/clients/{$client_id}_{$client_name}/reserved_properties/{$property_id}_{$property_name}/";
$owner_pdf_dir  = __DIR__ . "/uploads/owner/{$owner_id}_{$owner_name}/listed_properties/{$property_id}_{$property_name}/";
if (!is_dir($client_pdf_dir)) mkdir($client_pdf_dir, 0777, true);
if (!is_dir($owner_pdf_dir))  mkdir($owner_pdf_dir, 0777, true);
$client_pdf_filename = "lease-contract-claim_{$claim_id}.pdf";
$owner_pdf_filename  = "lease-contract-claim_{$claim_id}.pdf";
$client_pdf_path = $client_pdf_dir . $client_pdf_filename;
$owner_pdf_path  = $owner_pdf_dir . $owner_pdf_filename;
file_put_contents($client_pdf_path, $dompdf->output());
file_put_contents($owner_pdf_path, $dompdf->output());

// 9. UPDATE DB PATH (store relative path for client)
$relative_client_pdf_path = "uploads/clients/{$client_id}_{$client_name}/reserved_properties/{$property_id}_{$property_name}/{$client_pdf_filename}";
$stmt = $pdo->prepare("UPDATE rental_contracts SET contract_signed_path = ? WHERE claim_id = ?");
$stmt->execute([$relative_client_pdf_path, $claim_id]);

// 10. OUTPUT PDF TO USER
header("Content-Type: application/pdf");
header("Content-Disposition: attachment; filename=\"{$client_pdf_filename}\"");
header("Content-Length: " . filesize($client_pdf_path));
readfile($client_pdf_path);
exit();
?>
