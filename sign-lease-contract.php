<?php
/**
 * Lease Contract Signing Page (Updated for Extension)
 * - Handles signature by owner/client only if contract is locked.
 * - If contract is unlocked (for extension), both can view but NOT sign.
 * - Preserves existing signatures during extension window.
 */

session_start();
require 'db_connect.php';

// 1. AUTHENTICATION & USER TYPE
$user_id    = $_SESSION['user_id'] ?? null;
$staff_id   = $_SESSION['staff_id'] ?? null;
$user_type  = strtolower(trim($_SESSION['user_type'] ?? ''));
$staff_role = strtolower(trim($_SESSION['role'] ?? ''));

$is_owner   = ($user_type === 'property_owner' || $user_type === 'owner');
$is_client  = ($user_type === 'client');
$is_gm      = ($staff_role === 'general manager');
$is_pm      = ($staff_role === 'property manager');

$signing_party = '';
if ($is_owner)         $signing_party = 'owner';
elseif ($is_client)    $signing_party = 'client';
elseif ($is_gm)        $signing_party = 'general manager';
elseif ($is_pm)        $signing_party = 'property manager';

// ================================
// 2. GET CONTRACT RECORD
// ================================
$claim_id = $_GET['claim_id'] ?? null;
if (!$claim_id) die('No claim ID specified.');

$stmt = $pdo->prepare("
    SELECT rc.*, cc.property_id, cc.client_id, p.owner_id, p.property_name,
           uo.full_name AS owner_name, uc.full_name AS client_name
      FROM rental_contracts rc
      JOIN client_claims cc ON rc.claim_id = cc.claim_id
      JOIN properties p ON cc.property_id = p.property_id
      JOIN owners o ON p.owner_id = o.owner_id
      JOIN users uo ON o.user_id = uo.user_id
      JOIN clients cl ON cc.client_id = cl.client_id
      JOIN users uc ON cl.user_id = uc.user_id
     WHERE rc.claim_id = ?
");
$stmt->execute([$claim_id]);
$contract = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$contract) die('Lease contract not found.');

// --- IMPORTANT: Only allow signing if contract is locked ---
// - During unlocked extension window: both parties see the contract (including their prior signatures), but cannot sign.
$is_locked = !empty($contract['locked']);

// ================================
// 3. SIGNATURE BLOCKS CONFIG
// ================================
$signature_blocks = [
    'OWNER_SIGNATURE_BLOCK' => [
        'db_column'      => 'owner_signature',
        'label'          => 'Owner',
        'show_for'       => 'owner',
        'timestamp_col'  => 'owner_signed_at'
    ],
    'CLIENT_SIGNATURE_BLOCK' => [
        'db_column'      => 'client_signature',
        'label'          => 'Client',
        'show_for'       => 'client',
        'timestamp_col'  => 'client_signed_at'
    ]
];

// ================================
// 4. DETERMINE WHO CAN SIGN
// Only allow if locked AND this user hasn't already signed
// ================================
$user_sign_column = '';
$user_sign_label  = '';
if ($is_locked) {
    foreach ($signature_blocks as $block => $data) {
        if ($signing_party === $data['show_for'] && empty($contract[$data['db_column']])) {
            $user_sign_column = $data['db_column'];
            $user_sign_label  = $data['label'];
            break;
        }
    }
}

// ================================
// 5. HANDLE SIGNATURE SUBMISSION
// ================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user_sign_column) {
    $signature_data = $_POST['signature_data'] ?? '';
    if (!$signature_data) die('No signature data provided.');

    // Find correct timestamp column
    $timestamp_col = '';
    foreach ($signature_blocks as $block => $data) {
        if ($data['db_column'] === $user_sign_column) {
            $timestamp_col = $data['timestamp_col'];
            break;
        }
    }
    if (!$timestamp_col) die('Timestamp column not found.');

    $stmt = $pdo->prepare("UPDATE rental_contracts SET $user_sign_column = ?, $timestamp_col = NOW() WHERE claim_id = ?");
    $stmt->execute([$signature_data, $claim_id]);
    header("Location: sign-lease-contract.php?claim_id=$claim_id&signed=1");
    exit();
}

// ================================
// 6. BUILD CONTRACT HTML WITH SIGNATURE BLOCKS
// ================================
function slugify($string) {
    return preg_replace('/[^a-zA-Z0-9_\-]/', '_', strtolower(trim($string)));
}

$owner_id   = $contract['owner_id'];
$owner_name = slugify($contract['owner_name']);
$property_id = $contract['property_id'];
$property_name = slugify($contract['property_name']);
$client_id  = $contract['client_id'];
$client_name = slugify($contract['client_name']);

$owner_contract_dir  = __DIR__ . "/uploads/owner/{$owner_id}_{$owner_name}/listed_properties/{$property_id}_{$property_name}/";
$client_contract_dir = __DIR__ . "/uploads/clients/{$client_id}_{$client_name}/reserved_properties/{$property_id}_{$property_name}/";
$owner_contract_html = $owner_contract_dir . "contract.html";
$client_contract_html = $client_contract_dir . "contract.html";

$template_path = __DIR__ . '/lease-contract-template-en.html';
if (!file_exists($template_path)) die('Lease contract template file missing.');
$contract_html = file_get_contents($template_path);

$placeholders = [
    '{{OWNER_NAME}}'           => htmlspecialchars($contract['owner_name']),
    '{{OWNER_PHONE}}'          => htmlspecialchars($contract['owner_phone'] ?? ''),
    '{{OWNER_EMAIL}}'          => htmlspecialchars($contract['owner_email'] ?? ''),
    '{{CLIENT_NAME}}'          => htmlspecialchars($contract['client_name']),
    '{{CLIENT_PHONE}}'         => htmlspecialchars($contract['client_phone'] ?? ''),
    '{{CLIENT_EMAIL}}'         => htmlspecialchars($contract['client_email'] ?? ''),
    '{{PROPERTY_LOCATION}}'    => htmlspecialchars($contract['property_location'] ?? ''),
    '{{PROPERTY_TYPE}}'        => htmlspecialchars($contract['property_type'] ?? ''),
    '{{USE_FOR_THE_PROPERTY}}' => htmlspecialchars($contract['property_use'] ?? ''),
    '{{AGENCY_NAME}}'          => 'TRUSTED REAL ESTATE AGENCY - TREA',
    '{{AGENCY_ADDRESS}}'       => 'Aibatin 2, Cotonou, Benin',
    '{{MANAGER_NAME}}'         => 'Selomin Nicole',
    '{{AGENCY_PHONE}}'         => '(+229) 0100000000',
    '{{AGENCY_EMAIL}}'         => 'info@trea.com',
    '{{CONTRACT_START_DATE}}'  => htmlspecialchars($contract['contract_start_date'] ?? ''),
    '{{CONTRACT_END_DATE}}'    => htmlspecialchars($contract['contract_end_date'] ?? ''),
    '{{CONTRACT_DURATION}}'    => htmlspecialchars(($contract['contract_start_date'] ?? '').' to '.($contract['contract_end_date'] ?? '')),
    '{{ADVANCE_MONTHS}}'       => htmlspecialchars($contract['advance_months'] ?? '3'),
    '{{ADVANCE_AMOUNT}}'       => htmlspecialchars($contract['advance_amount'] ?? ''),
    '{{DEPOSIT_AMOUNT}}'       => htmlspecialchars($contract['deposit_amount'] ?? ''),
    '{{TOTAL_AMOUNT}}'         => htmlspecialchars($contract['total_amount'] ?? ''),
    '{{PAYMENT_FREQUENCY}}'    => htmlspecialchars($contract['payment_frequency'] ?? 'monthly'),
    '{{MONTHLY_RENT}}'         => htmlspecialchars($contract['monthly_rent'] ?? ''),
    '{{PENALTY_RATE}}'         => htmlspecialchars($contract['penalty_rate'] ?? '0.1'),
    '{{GRACE_PERIOD_DAYS}}'    => htmlspecialchars($contract['grace_period_days'] ?? '10'),
    '{{NOTICE_PERIOD}}'        => htmlspecialchars($contract['notice_period'] ?? '1'),
    '{{PENALTY_AMOUNT}}'       => htmlspecialchars($contract['penalty_amount'] ?? '500'),
    '{{REVISION_FREQUENCY}}'   => htmlspecialchars($contract['revision_frequency'] ?? '1 year'),
    '{{PRONOUN}}'              => 'they',
    '{{PROPERTY_NAME}}'        => htmlspecialchars($contract['property_name'] ?? ''),
    '{{REVISION_UNIT}}'        => 'year',
];

$content = strtr($contract_html, $placeholders);

// -- Signatures --
$signature_replacements = [];
foreach ($signature_blocks as $block => $data) {
    $block_col = $data['db_column'];
    if (!empty($contract[$block_col])) {
        $signature_replacements["{{{$block}}}"] =
            '<div class="signature-block mb-2"><img src="' . htmlspecialchars($contract[$block_col]) . '" style="max-height:90px; max-width:95%; display:block; margin:auto;"></div>';
    } elseif ($user_sign_column === $block_col && $is_locked) {
        // Show signature pad only if contract is locked AND user can sign
        $signature_replacements["{{{$block}}}"] = <<<HTML
<div class="signature-block mb-2">
  <canvas id="signature-pad" width="330" height="100" class="signature-pad-canvas"></canvas>
</div>
<div class="mb-2 d-flex justify-content-center gap-2">
  <button type="button" id="clear-btn" class="btn btn-outline-danger btn-sm">Clear</button>
  <button type="button" id="sign-btn" class="btn custom-btn btn-sm">Sign as {$data['label']}</button>
</div>
HTML;
    } else {
        $signature_replacements["{{{$block}}}"] =
            '<div class="signature-block mb-2 d-flex align-items-center justify-content-center text-muted" style="font-size:1.1rem;"><span>Pending signature...</span></div>';
    }
}

$signature_replacements['{{OWNER_SIGNATURE_DATE}}']  = $contract['owner_signed_at'] 
    ? date('Y-m-d H:i', strtotime($contract['owner_signed_at'])) 
    : '<span class="text-muted">Not yet signed</span>';
$signature_replacements['{{CLIENT_SIGNATURE_DATE}}'] = $contract['client_signed_at'] 
    ? date('Y-m-d H:i', strtotime($contract['client_signed_at'])) 
    : '<span class="text-muted">Not yet signed</span>';

$content = strtr($content, $signature_replacements);

// ================================
// 7. SAVE CONTRACT HTML ONCE SIGNED BY BOTH
// ================================
$all_signed = true;
foreach ($signature_blocks as $data) {
    if (empty($contract[$data['db_column']])) {
        $all_signed = false;
        break;
    }
}

if ($all_signed) {
    if (!is_dir($owner_contract_dir)) mkdir($owner_contract_dir, 0777, true);
    if (!is_dir($client_contract_dir)) mkdir($client_contract_dir, 0777, true);
    file_put_contents($owner_contract_html, $content);
    file_put_contents($client_contract_html, $content);
    $relative_client_contract_path = "uploads/clients/{$client_id}_{$client_name}/reserved_properties/{$property_id}_{$property_name}/contract.html";
    $stmt = $pdo->prepare("UPDATE rental_contracts SET contract_signed_path = ? WHERE claim_id = ?");
    $stmt->execute([$relative_client_contract_path, $claim_id]);
}

$page_title = 'Lease Agreement';
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Sign <?= htmlspecialchars($page_title) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css?v=<?= time() ?>">
</head>
<body class="bg-light d-flex flex-column min-vh-100">
<?php include 'header.php'; ?>

<main class="flex-shrink-0 flex-grow-1 py-4">
    <div class="container">
        <h2 class="mb-3"><?= htmlspecialchars($page_title) ?></h2>

        <ul class="list-inline mb-3">
        <?php foreach ($signature_blocks as $block => $data): ?>
            <li class="list-inline-item">
                <?= $data['label'] ?>:
                <?= !empty($contract[$data['db_column']]) ? "<span class='text-success'>✔️</span>" : "<span class='text-muted'>⏳</span>" ?>
            </li>
        <?php endforeach; ?>
        </ul>

        <?php if (isset($_GET['signed'])): ?>
            <div class="alert alert-success">Your signature has been saved.</div>
        <?php endif; ?>

        <?php if (!$is_locked): ?>
            <div class="alert alert-warning mb-3">
                <b>Contract is currently being revised for extension.</b> You may only <b>view</b> the contract and signatures.<br>
                Signing is disabled until contract revision is complete and contract is locked for signatures again.
            </div>
        <?php endif; ?>

        <div class="contract-card bg-white rounded-4 p-4 mb-4 border">
            <?= $content ?>
        </div>

        <?php if ($user_sign_column && $is_locked): ?>
            <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.5/dist/signature_pad.umd.min.js"></script>
            <script>
            var signaturePad = new SignaturePad(document.getElementById('signature-pad'));
            document.getElementById('clear-btn').onclick = function() { signaturePad.clear(); };
            document.getElementById('sign-btn').onclick = function() {
                if (signaturePad.isEmpty()) {
                    alert('Please sign before submitting.');
                    return false;
                }
                var dataUrl = signaturePad.toDataURL();
                var form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="signature_data" value="'+dataUrl+'">';
                document.body.appendChild(form);
                form.submit();
            };
            </script>
        <?php endif; ?>

        <?php if ($all_signed): ?>
            <a href="download-lease-contract.php?claim_id=<?= $claim_id ?>" class="btn btn-outline-primary mt-2">Download Signed PDF</a>
        <?php endif; ?>
    </div>
</main>

<?php include 'footer.php'; ?>
<script src="navbar-close.js?v=1"></script>
</body>
</html>
