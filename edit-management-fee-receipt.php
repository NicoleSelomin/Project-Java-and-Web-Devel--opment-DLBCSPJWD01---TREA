<?php
// edit-management-fee-receipt.php

session_start();
require 'db_connect.php';

// Security: Only accountant or general manager
if (!isset($_SESSION['staff_id']) || !in_array(strtolower($_SESSION['role']), ['accountant', 'general manager'])) {
    header('Location: staff-login.php');
    exit();
}

require_once 'generate-management-fee-receipt.php'; // your helper function

$fee_id = $_GET['fee_id'] ?? null;
if (!$fee_id) die('No fee ID specified.');

// Fetch the fee and all related info
$stmt = $pdo->prepare("
    SELECT 
        f.*, 
        p.property_name, p.property_id,
        o.owner_id, u.full_name AS owner_name,
        oac.management_fee_percent
    FROM owner_rental_fees f
    LEFT JOIN client_claims cc ON f.claim_id = cc.claim_id
    LEFT JOIN properties p ON cc.property_id = p.property_id
    LEFT JOIN owners o ON p.owner_id = o.owner_id
    LEFT JOIN users u ON o.user_id = u.user_id
    LEFT JOIN owner_agency_contracts oac ON o.owner_id = oac.owner_id
    WHERE f.fee_id = ?
");
$stmt->execute([$fee_id]);
$fee = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$fee) die('Fee not found');

$errors = [];
$success = false;

// --- Handle POST submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $maintenance_fee = isset($_POST['maintenance_fee']) ? floatval($_POST['maintenance_fee']) : 0;
    $tax_fee = isset($_POST['tax_fee']) ? floatval($_POST['tax_fee']) : 0;

    $mgmt_percent = floatval($fee['management_fee_percent'] ?? 0);
    $mgmt_fee = round($fee['rent_received'] * $mgmt_percent / 100, 2);

    // Handle uploads for tax and maintenance receipts
    $upload_dir = __DIR__ . '/uploads/owner/receipts/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

    $receipt_maintenance = $fee['receipt_maintenance'];
    $receipt_tax = $fee['tax_receipt'];

    if (!empty($_FILES['receipt_maintenance']['tmp_name'])) {
        $fn = "fee_{$fee_id}_maint_" . time() . "." . pathinfo($_FILES['receipt_maintenance']['name'], PATHINFO_EXTENSION);
        $target = $upload_dir . $fn;
        if (move_uploaded_file($_FILES['receipt_maintenance']['tmp_name'], $target)) {
            $receipt_maintenance = 'uploads/owner/receipts/' . $fn;
        }
    }
    if (!empty($_FILES['tax_receipt']['tmp_name'])) {
        $fn = "fee_{$fee_id}_tax_" . time() . "." . pathinfo($_FILES['tax_receipt']['name'], PATHINFO_EXTENSION);
        $target = $upload_dir . $fn;
        if (move_uploaded_file($_FILES['tax_receipt']['tmp_name'], $target)) {
            $receipt_tax = 'uploads/owner/receipts/' . $fn;
        }
    }

    // Generate the management fee receipt and save path
    $fee['management_fee'] = $mgmt_fee; // set so generator is correct
    $receipt_management = generate_management_fee_receipt($fee);

    $net_transfer = $fee['rent_received'] - $mgmt_fee - $maintenance_fee - $tax_fee;

    // Save everything
    $stmt = $pdo->prepare("UPDATE owner_rental_fees SET 
        maintenance_fee=?, 
        tax_fee=?, 
        management_fee=?, 
        receipt_management=?, 
        receipt_maintenance=?, 
        tax_receipt=?,
        net_transfer=?
        WHERE fee_id = ?");
    $stmt->execute([
        $maintenance_fee,
        $tax_fee,
        $mgmt_fee,
        $receipt_management,
        $receipt_maintenance,
        $receipt_tax,
        $net_transfer,
        $fee_id
    ]);

    $success = true;
    // Reload data for display
    header("Location: edit-management-fee-receipt.php?fee_id=" . $fee_id . "&success=1");
    exit();
}

// Regenerate latest receipt if not available
if (!$fee['receipt_management'] || !file_exists(__DIR__ . '/' . $fee['receipt_management'])) {
    $fee['management_fee'] = round($fee['rent_received'] * floatval($fee['management_fee_percent']) / 100, 2);
    $fee['receipt_management'] = generate_management_fee_receipt($fee);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Owner Fee Receipts</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css">
    <style>
        .box { max-width:540px; margin:auto; border-radius:14px; border:1px solid #2563eb; background:#f8faff; padding:2em 2em; }
    </style>
</head>
<body class="bg-light">
<?php include 'header.php'; ?>
<div class="container py-4">
    <div class="box mt-4 shadow-sm">
        <h3 class="mb-3">Edit Owner Fee Receipts</h3>
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">Fee details updated and receipts saved!</div>
        <?php endif; ?>

        <dl class="row">
            <dt class="col-5">Owner</dt>
            <dd class="col-7"><?= htmlspecialchars($fee['owner_name']) ?></dd>
            <dt class="col-5">Property</dt>
            <dd class="col-7"><?= htmlspecialchars($fee['property_name']) ?></dd>
            <dt class="col-5">Rent Period</dt>
            <dd class="col-7"><?= date('M Y', strtotime($fee['start_period'])) ?></dd>
            <dt class="col-5">Rent Received</dt>
            <dd class="col-7"><?= number_format($fee['rent_received'],2) ?> CFA</dd>
            <dt class="col-5">Mgmt Fee (<?= $fee['management_fee_percent'] ?>%)</dt>
            <dd class="col-7"><?= number_format($fee['management_fee'],2) ?> CFA</dd>
        </dl>

        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="edit_fee_id" value="<?= $fee['fee_id'] ?>">
            <div class="mb-2">
                <label>Maintenance Fee (CFA):</label>
                <input type="number" step="0.01" min="0" name="maintenance_fee" class="form-control"
                    value="<?= htmlspecialchars($fee['maintenance_fee']) ?>" required>
                <?php if ($fee['receipt_maintenance']): ?>
                    <a href="<?= $fee['receipt_maintenance'] ?>" class="badge bg-info mt-1" target="_blank">View Maintenance Receipt</a>
                <?php endif; ?>
                <input type="file" name="receipt_maintenance" class="form-control mt-1" accept="application/pdf,image/*">
            </div>
            <div class="mb-2">
                <label>Tax Fee (CFA):</label>
                <input type="number" step="0.01" min="0" name="tax_fee" class="form-control"
                    value="<?= htmlspecialchars($fee['tax_fee']) ?>" required>
                <?php if ($fee['tax_receipt']): ?>
                    <a href="<?= $fee['tax_receipt'] ?>" class="badge bg-info mt-1" target="_blank">View Tax Receipt</a>
                <?php endif; ?>
                <input type="file" name="tax_receipt" class="form-control mt-1" accept="application/pdf,image/*">
            </div>
            <div class="mb-3">
                <label class="form-label">Management Fee Receipt (auto-generated):</label><br>
                <a href="<?= $fee['receipt_management'] ?>" target="_blank" class="badge bg-success">View Receipt</a>
            </div>
            <div>
                <button type="submit" class="btn btn-primary">Save All Receipts & Fees</button>
                <a href="manage-owner-fees.php" class="btn btn-secondary ms-2">Back</a>
            </div>
        </form>
    </div>
</div>
<?php include 'footer.php'; ?>
</body>
</html>
