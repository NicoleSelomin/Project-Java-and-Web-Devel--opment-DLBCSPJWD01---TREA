<?php
session_start();
require 'db_connect.php';

$staff_id = $_SESSION['staff_id'] ?? null;
$role = strtolower($_SESSION['role'] ?? '');
if (!$staff_id || !in_array($role, ['general manager', 'property manager'])) {
    http_response_code(403); exit("Access denied.");
}

$owner_id = $_GET['owner_id'] ?? null;
if (!$owner_id) exit("Missing owner ID.");

// Fetch or create contract for this owner
$stmt = $pdo->prepare("SELECT * FROM owner_agency_contracts WHERE owner_id = ? AND contract_status IN ('pending','active') LIMIT 1");
$stmt->execute([$owner_id]);
$contract = $stmt->fetch();

if (!$contract) {
    $pdo->prepare("INSERT INTO owner_agency_contracts (owner_id, contract_status, management_fee_percent) VALUES (?, 'pending', 10.0)")->execute([$owner_id]);
    $contract_id = $pdo->lastInsertId();
    $contract = $pdo->query("SELECT * FROM owner_agency_contracts WHERE contract_id = $contract_id")->fetch();
} else {
    $contract_id = $contract['contract_id'];
}

// --- Prepare placeholders ---
$owner_row = $pdo->query("SELECT o.*, u.full_name, u.email, u.phone_number FROM owners o JOIN users u ON o.user_id = u.user_id WHERE o.owner_id = $owner_id")->fetch();

$agency_defaults = [
    '{{AGENCY_NAME}}'          => 'TRUSTED REAL ESTATE AGENCY - TREA',
    '{{AGENCY_ADDRESS}}'       => 'Aibatin 2, Cotonou, Benin',
    '{{AGENCY_PHONE}}'         => '(+229) 0100000000',
    '{{AGENCY_EMAIL}}'         => 'info@trea.com',
    '{{MANAGER_NAME}}'         => 'Nicole HouinatoI',
];

$placeholders = array_merge($agency_defaults, [
    '{{OWNER_NAME}}'           => htmlspecialchars($owner_row['full_name'] ?? ''),
    '{{OWNER_EMAIL}}'          => htmlspecialchars($owner_row['email'] ?? ''),
    '{{OWNER_PHONE}}'          => htmlspecialchars($owner_row['phone_number'] ?? ''),
    '{{OWNER_ADDRESS}}'        => htmlspecialchars($owner_row['address'] ?? ''),
    '{{MANAGEMENT_FEE_PERCENT}}' => htmlspecialchars($contract['management_fee_percent'] ?? 10.0),
    '{{CONTRACT_DURATION}}'    => '1 year',
    '{{CONTRACT_START_DATE}}'  => htmlspecialchars($contract['contract_start_date'] ?? ''),
    '{{NOTICE_PERIOD}}'        => '30 days',
    '{{JURISDICTION}}'         => 'Cotonou, Benin',
    '{{SIGNING_LOCATION}}'     => 'Cotonou',
    '{{CONTRACT_SIGNING_DATE}}' => date('Y-m-d'),
    '{{REPAIR_APPROVAL_LIMIT}}'=> '50,000 FCFA',
    '{{PROPERTIES_LIST}}'      => '',
]);

// Build {{PROPERTIES_LIST}} HTML
$properties = $pdo->query("SELECT property_name, property_type, address FROM properties WHERE owner_id = $owner_id")->fetchAll();
if ($properties) {
    $html = "<ul>";
    foreach ($properties as $prop) {
        $html .= "<li>{$prop['property_name']} ({$prop['property_type']}), {$prop['address']}</li>";
    }
    $html .= "</ul>";
    $placeholders['{{PROPERTIES_LIST}}'] = $html;
} else {
    $placeholders['{{PROPERTIES_LIST}}'] = '<em>No properties linked yet.</em>';
}

// -- Load contract content --
$template_file = 'rental-management-agreement-template-en.html';
if (!empty($contract['contract_file_path']) && file_exists($contract['contract_file_path'])) {
    $content = file_get_contents($contract['contract_file_path']);
} elseif (file_exists($template_file)) {
    $content = strtr(file_get_contents($template_file), $placeholders);
} else {
    $content = '<div class="alert alert-danger">No contract template found.</div>';
}

// --- Save on POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $management_fee = floatval($_POST['management_fee_percent'] ?? 10.0);
    $start_date     = $_POST['contract_start_date'] ?? null;
    $end_date       = $_POST['contract_end_date'] ?? null;
    $html           = $_POST['contract_html'] ?? '';

    // Save contract HTML
    $base_folder = __DIR__ . "/uploads/owner_contracts/$owner_id/";
    if (!is_dir($base_folder)) mkdir($base_folder, 0777, true);
    $file_path = $base_folder . "contract_$contract_id.html";
    file_put_contents($file_path, $html);

    $stmt = $pdo->prepare("UPDATE owner_agency_contracts SET management_fee_percent=?, contract_start_date=?, contract_end_date=?, contract_file_path=?, updated_at=NOW() WHERE contract_id=?");
    $stmt->execute([$management_fee, $start_date, $end_date, $file_path, $contract_id]);
    header("Location: edit-owner-agency-contract.php?owner_id=$owner_id&saved=1");
    exit();
}
$page_title = "Edit Owner-Agency Contract";
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($page_title) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
</head>
<body>
<div class="container py-5">
    <h2><?= htmlspecialchars($page_title) ?></h2>
    <?php if (isset($_GET['saved'])): ?>
        <div class="alert alert-success">Contract saved.</div>
    <?php endif; ?>
    <form method="POST">
        <div class="mb-2">
            <label>Management Fee (%)</label>
            <input type="number" step="0.1" name="management_fee_percent" value="<?= htmlspecialchars($contract['management_fee_percent'] ?? 10.0) ?>" class="form-control" required>
        </div>
        <div class="mb-2">
            <label>Contract Start Date</label>
            <input type="date" name="contract_start_date" value="<?= htmlspecialchars($contract['contract_start_date'] ?? '') ?>" class="form-control" required>
        </div>
        <div class="mb-2">
            <label>Contract End Date</label>
            <input type="date" name="contract_end_date" value="<?= htmlspecialchars($contract['contract_end_date'] ?? '') ?>" class="form-control">
        </div>
        <div class="mb-2">
            <label>Contract Content</label>
            <div id="editor" style="height:400px"><?= $content ?></div>
            <input type="hidden" id="contract_html" name="contract_html">
        </div>
        <button type="submit" class="btn btn-primary" onclick="document.getElementById('contract_html').value = quill.root.innerHTML;">Save Contract</button>
    </form>
</div>
<script src="https://cdn.quilljs.com/1.3.7/quill.js"></script>
<script>
    var quill = new Quill('#editor', {theme:'snow'});
</script>
</body>
</html>
