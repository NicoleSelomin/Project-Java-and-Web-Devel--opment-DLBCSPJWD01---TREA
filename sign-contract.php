<?php
session_start();
require 'db_connect.php';

// ---- 1. AUTHENTICATION & USER TYPE ----
$user_id    = $_SESSION['user_id'] ?? null;
$staff_id   = $_SESSION['staff_id'] ?? null;
$user_type  = strtolower(trim($_SESSION['user_type'] ?? ''));
$staff_role = strtolower(trim($_SESSION['role'] ?? ''));

$is_owner   = ($user_type === 'property_owner' || $user_type === 'owner');
$is_client  = ($user_type === 'client');
$is_gm      = ($staff_role === 'general manager');
$is_pm      = ($staff_role === 'property manager');

if (!$user_id && !$staff_id) {
    header('Location: user-login.php');
    exit();
}

if (!$is_owner && !$is_client && !$is_gm && !$is_pm) {
    http_response_code(403);
    exit('Access denied.');
}

if ($is_owner)         $role = 'owner';
elseif ($is_client)    $role = 'client';
elseif ($is_gm)        $role = 'general manager';
elseif ($is_pm)        $role = 'property manager';
else                   $role = '';

// ---- 2. GET CONTRACT RECORD ----
$request_id = $_GET['contract_id'] ?? null;
if (!$request_id) die('No contract ID specified.');

$stmt = $pdo->prepare("SELECT r.*, s.slug FROM owner_service_requests r JOIN services s ON r.service_id = s.service_id WHERE r.request_id = ?");
$stmt->execute([$request_id]);
$contract = $stmt->fetch();

if (!$contract) die('Contract not found.');
if (empty($contract['contract_locked'])) die('Contract not yet ready for signing.');

$slug = $contract['slug'] ?? '';

// ---- 3. DYNAMIC SIGNATURE BLOCKS ----
$signature_blocks = [];
if ($slug === 'rental_property_management') {
    $signature_blocks = [
        'OWNER_SIGNATURE_BLOCK' => [
            'db_column' => 'owner_signature',
            'label'     => 'Owner',
            'show_for'  => 'owner'
        ],
        'CLIENT_SIGNATURE_BLOCK' => [
            'db_column' => 'client_signature',
            'label'     => 'Client',
            'show_for'  => 'client'
        ]
    ];
} else {
    $signature_blocks = [
        'OWNER_SIGNATURE_BLOCK'  => [
            'db_column' => 'owner_signature',
            'label'     => 'Owner',
            'show_for'  => 'owner'
        ],
        'AGENCY_SIGNATURE_BLOCK' => [
            'db_column' => 'agency_signature',
            'label'     => 'Agency/Manager',
            'show_for'  => 'general manager'
        ]
    ];
}

// ---- 4. DETERMINE WHO CAN SIGN ----
$user_sign_column = '';
$user_sign_label  = '';
foreach ($signature_blocks as $block) {
    if ($role === $block['show_for'] && empty($contract[$block['db_column']])) {
        $user_sign_column = $block['db_column'];
        $user_sign_label = $block['label'];
        break;
    }
}

// ---- 5. HANDLE SIGNATURE SUBMISSION ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user_sign_column) {
    $signature_data = $_POST['signature_data'] ?? '';
    if (!$signature_data) die('No signature data provided.');
    $timestamp_col = $user_sign_column . '_signed_at';
    $stmt = $pdo->prepare("UPDATE owner_service_requests SET $user_sign_column = ?, $timestamp_col = NOW() WHERE request_id = ?");
    $stmt->execute([$signature_data, $request_id]);
    header("Location: sign-contract.php?contract_id=$request_id&signed=1");
    exit();
}

// ---- 6. BUILD CONTRACT HTML WITH SIGNATURE BLOCKS ----
$contract_path = $contract['owner_contract_path'] ?? '';
$replacements = [];

if ($contract_path && file_exists(__DIR__ . '/' . $contract_path)) {
    $contract_html = file_get_contents(__DIR__ . '/' . $contract_path);

    foreach ($signature_blocks as $block => $data) {
        $block_col = $data['db_column'];
        if (!empty($contract[$block_col])) {
            $replacements["{{{$block}}}"] = '<img src="' . htmlspecialchars($contract[$block_col]) . '" style="max-height:90px;">';
        } elseif ($user_sign_column === $block_col) {
            $replacements["{{{$block}}}"] = <<<HTML
<canvas id="signature-pad" width="250" height="90" style="border:1px solid #aaa;"></canvas>
<br>
<button type="button" id="clear-btn" class="btn btn-outline-danger btn-sm mt-1 me-2">Clear</button>
<button type="button" id="sign-btn" class="btn custom-btn btn-sm mt-1">Sign as {$data['label']}</button>
HTML;
        } else {
            $replacements["{{{$block}}}"] = '<span class="text-muted">Pending signature...</span>';
        }
    }
    $content = strtr($contract_html, $replacements);
} else {
    $content = '<div class="alert alert-danger">Contract file missing.</div>';
}

$page_title = 'Service Agreement';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign <?= htmlspecialchars($page_title) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css?v=<?= time() ?>">
</head>
<body class="bg-light d-flex flex-column min-vh-100">

<?php include 'header.php'; ?>

<div class="container-fluid flex-grow-1 py-4">
    <div class="row">
    <main class="col-12 col-md-11 ms-lg-5">

<div class="container py-5">
    <h2><?= htmlspecialchars($page_title) ?></h2>

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

    <div class="border p-3 bg-white" style="min-height:600px"><?= $content ?></div>

    <?php if ($user_sign_column): ?>
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

    <?php
    $all_signed = true;
    foreach ($signature_blocks as $data) {
        if (empty($contract[$data['db_column']])) {
            $all_signed = false;
            break;
        }
    }
    if ($all_signed): ?>
        <a href="download-contract.php?contract_id=<?= $request_id ?>" class="btn btn-outline-primary mt-2">Download Signed PDF</a>
    <?php endif; ?>
</div>
    </main>
    </div>
</div>

<?php include 'footer.php'; ?>
<script src="navbar-close.js?v=1"></script>
</body>
</html>
