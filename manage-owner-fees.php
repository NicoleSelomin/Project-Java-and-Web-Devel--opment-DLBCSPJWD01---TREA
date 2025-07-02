<?php
/**
 * manage-owner-fee.php
 * -------------------------------------
 * Allows the accountant to upload PDF receipts for management, maintenance, tax deduction,
 * and proof of rent transfer for a specific owner rental fee.
 * Only accessible by staff with role 'accountant'.
 * Sends notifications to owner and staff on successful upload.
 * 
 * Requires:
 *   - db_connect.php for database connection
 *   - send-upload-notification.php for notification logic
 *   - Staff must be authenticated as 'accountant'
 *   - GET parameter fee_id 
 */

session_start();
require 'db_connect.php';

// Auth check
if (!isset($_SESSION['staff_id']) || !in_array(strtolower($_SESSION['role']), ['general manager', 'accountant'])) {
    $_SESSION['redirect_after_login'] = 'manage-owner-rental-fees.php';
    header("Location: staff-login.php");
    exit();
}

$stmt = $pdo->prepare("
    SELECT 
        inv.invoice_id, inv.claim_id, inv.invoice_date, inv.due_date, inv.amount,
        cc.property_id
    FROM rental_recurring_invoices inv
    JOIN client_claims cc ON inv.claim_id = cc.claim_id
    WHERE inv.recurring_active = 1
");
$stmt->execute();
$activeInvoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($activeInvoices as $inv) {
    $check = $pdo->prepare("SELECT 1 FROM owner_rental_fees WHERE claim_id = ? AND invoice_id = ?");
    $check->execute([$inv['claim_id'], $inv['invoice_id']]);
    if (!$check->fetchColumn()) {
        $insert = $pdo->prepare("
            INSERT INTO owner_rental_fees 
            (claim_id, invoice_id, start_period, end_period, invoice_month, rent_received)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $start_period = $inv['invoice_date'];
        $end_period = $inv['due_date'];
        $invoice_month = date('Y-m', strtotime($inv['invoice_date']));
        $rent_received = $inv['amount'];
        $insert->execute([
            $inv['claim_id'],
            $inv['invoice_id'],
            $start_period,
            $end_period,
            $invoice_month,
            $rent_received
        ]);
    }
}

// 2. Now fetch for display!
$where = [];
$params = [];
if (!empty($_GET['owner_id'])) {
    $where[] = "o.owner_id = ?";
    $params[] = (int)$_GET['owner_id'];
}
if (!empty($_GET['property_id'])) {
    $where[] = "p.property_id = ?";
    $params[] = (int)$_GET['property_id'];
}
if (!empty($_GET['month'])) {
    $where[] = "f.invoice_month = ?";
    $params[] = $_GET['month'];
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare("
    SELECT 
        f.*, 
        p.property_name, p.property_id,
        o.owner_id, u.full_name AS owner_name, u.phone_number AS owner_phone,
        o.bank_name, o.account_number AS bank_account_number, o.account_holder_name, o.payment_mode,
        c.client_id, cu.full_name AS client_name, cu.phone_number AS client_phone,
        inv.due_date, inv.payment_status AS client_payment_status
    FROM owner_rental_fees f
    JOIN client_claims cc ON f.claim_id = cc.claim_id
    JOIN clients c ON cc.client_id = c.client_id
    JOIN users cu ON c.user_id = cu.user_id
    JOIN properties p ON cc.property_id = p.property_id
    JOIN owners o ON p.owner_id = o.owner_id
    JOIN users u ON o.user_id = u.user_id
    LEFT JOIN rental_recurring_invoices inv ON f.invoice_id = inv.invoice_id
    $whereSql
    ORDER BY f.start_period DESC, p.property_name
");
$stmt->execute($params);
$fees = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">    
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage Owner Rental Fees</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css?v=<?= time() ?>">
</head>
<body class="bg-light d-flex flex-column min-vh-100">
<?php include 'header.php'; ?>

<div class="container py-4 flex-grow-1">
    <h3 class="mb-4">Manage Owner Rental Fees</h3>
    
    <!-- Filter/Search -->
    <form class="row g-2 mb-3" method="get">
        <div class="col-auto">
            <input type="text" class="form-control" name="owner_id" placeholder="Owner ID" value="<?= htmlspecialchars($_GET['owner_id'] ?? '') ?>">
        </div>
        <div class="col-auto">
            <input type="text" class="form-control" name="property_id" placeholder="Property ID" value="<?= htmlspecialchars($_GET['property_id'] ?? '') ?>">
        </div>
        <div class="col-auto">
            <input type="month" class="form-control" name="month" placeholder="Month" value="<?= htmlspecialchars($_GET['month'] ?? '') ?>">
        </div>
        <div class="col-auto">
            <button class="btn btn-outline-primary">Filter</button>
            <a href="manage-owner-rental-fees.php" class="btn btn-link">Reset</a>
        </div>
    </form>
    
    <div class="table-responsive">
    <table class="table table-bordered table-hover bg-white align-middle">
<thead class="table-light">
<tr>
    <th>Month</th>
    <th>Property</th>
    <th>Client</th>
    <th>Client Paid?</th>
    <th>Rent</th>
    <th>Mgmt Fee</th>
    <th>Maint. Fee</th>
    <th>Tax Fee</th>
    <th>Net to Owner</th>
    <th>Receipts</th>
    <th>Transfer Proof</th>
    <th>Status</th>
    <th>Owner Bank & Contact</th>
    <th>Actions</th>
</tr>
</thead>
<tbody>
<?php foreach ($fees as $fee): ?>
<?php
    $rowClass = "";
    if ($fee['client_payment_status'] == 'unpaid') {
        $rowClass = 'table-warning';
    } elseif (!$fee['transfer_proof']) {
        $rowClass = 'table-danger';
    } else {
        $rowClass = 'table-success';
    }
    $period = date('M Y', strtotime($fee['start_period']));
?>
<tr class="<?= $rowClass ?>">
    <td><?= $period ?></td>
    <td>
        <?= htmlspecialchars($fee['property_name']) ?>
        <br><span class="text-muted small">ID: <?= $fee['property_id'] ?></span>
    </td>
    <td>
        <?= htmlspecialchars($fee['client_name']) ?><br>
        <span class="text-muted small"><?= htmlspecialchars($fee['client_phone'] ?? '-') ?></span>
    </td>
    <td>
        <?php
            if ($fee['client_payment_status'] == 'paid') {
                echo '<span class="badge bg-success">Yes</span>';
            } else {
                echo '<span class="badge bg-danger">No</span>';
            }
        ?>
    </td>
    <td><?= number_format($fee['rent_received'],2) ?></td>
    <td>
        <?= number_format($fee['management_fee'],2) ?>
        <?php if ($fee['receipt_management']): ?>
            <br><a href="<?= $fee['receipt_management'] ?>" target="_blank">View</a>
        <?php else: ?>
            <br><span class="text-muted small">Not uploaded</span>
        <?php endif; ?>
    </td>
    <td>
        <?= number_format($fee['maintenance_fee'],2) ?>
        <?php if ($fee['receipt_maintenance']): ?>
            <br><a href="<?= $fee['receipt_maintenance'] ?>" target="_blank">View</a>
        <?php else: ?>
            <br><span class="text-muted small">Not uploaded</span>
        <?php endif; ?>
    </td>
    <td>
        <?= number_format($fee['tax_fee'],2) ?>
        <?php if ($fee['tax_receipt']): ?>
            <br><a href="<?= $fee['tax_receipt'] ?>" target="_blank">View</a>
        <?php else: ?>
            <br><span class="text-muted small">Not uploaded</span>
        <?php endif; ?>
    </td>
    <td>
        <?= number_format($fee['net_transfer'],2) ?>
    </td>
    <td>
        <?php if ($fee['receipt_management']): ?>
            <a href="<?= $fee['receipt_management'] ?>" target="_blank" class="badge bg-info">Mgmt</a>
        <?php endif; ?>
        <?php if ($fee['receipt_maintenance']): ?>
            <a href="<?= $fee['receipt_maintenance'] ?>" target="_blank" class="badge bg-info">Maint</a>
        <?php endif; ?>
        <?php if ($fee['tax_receipt']): ?>
            <a href="<?= $fee['tax_receipt'] ?>" target="_blank" class="badge bg-info">Tax</a>
        <?php endif; ?>
    </td>
    <td>
        <?php if ($fee['transfer_proof']): ?>
            <a href="<?= $fee['transfer_proof'] ?>" target="_blank" class="badge bg-primary">Proof</a>
        <?php else: ?>
            <span class="text-danger small">Not uploaded</span>
        <?php endif; ?>
    </td>
    <td>
        <?php
            if (!$fee['client_payment_status'] || $fee['client_payment_status'] == 'unpaid') {
                echo '<span class="badge bg-warning text-dark">Awaiting Client Payment</span>';
            } elseif (!$fee['transfer_proof']) {
                echo '<span class="badge bg-danger">Awaiting Transfer</span>';
            } elseif ($fee['owner_confirmation']) {
                echo '<span class="badge bg-success">Confirmed</span>';
            } else {
                echo '<span class="badge bg-info">Transferred, Awaiting Owner</span>';
            }
        ?>
    </td>
    <td>
        <div class="small">
            <strong><?= htmlspecialchars($fee['owner_name'] ?? '-') ?></strong>
            <br><?= htmlspecialchars($fee['owner_phone'] ?? '-') ?>
            <br><?= htmlspecialchars($fee['bank_name'] ?? '-') ?>
            <br><?= htmlspecialchars($fee['bank_account_number'] ?? '-') ?>
            <br><?= htmlspecialchars($fee['account_holder_name'] ?? '-') ?>
            <br><em><?= htmlspecialchars($fee['payment_mode'] ?? '-') ?></em>
        </div>
    </td>
    <td>
        <a href="manage-owner-fee.php?fee_id=<?= $fee['fee_id'] ?>" class="btn btn-sm btn-outline-primary">
            Upload/Replace Docs
        </a>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
    </table>
    </div>
    <p class="mt-3 text-muted">Legend: <span class="badge bg-success">Confirmed</span> <span class="badge bg-info">Transferred</span> <span class="badge bg-warning text-dark">Awaiting Client</span> <span class="badge bg-danger">Action Needed</span></p>
</div>

<?php include 'footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
