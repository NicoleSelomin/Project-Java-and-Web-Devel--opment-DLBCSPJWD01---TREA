<?php
/**
 * manage-owner-fees.php
 * -------------------------------------
 * Accountant view for all owner rental fees (by invoice/period).
 * - Accountant sets maintenance & tax fees if applicable.
 * - Management fee is fetched from the owner-agency contract as a percentage of rent.
 * - Net to owner is rent minus all applicable fees.
 * - Lets accountant filter by owner, property, month.
 * - Shows all receipts and transfer docs.
 * - Upload/replace docs for each fee.
 * - DOES NOT send notifications from this page—only from the submission handler.
 * Security: Staff login as accountant or general manager required.
 */

session_start();
require 'db_connect.php';

// ---- Security: Only accountant/general manager allowed ----
if (!isset($_SESSION['staff_id']) || !in_array(strtolower($_SESSION['role']), ['general manager', 'accountant'])) {
    $_SESSION['redirect_after_login'] = 'manage-owner-fees.php';
    header("Location: staff-login.php");
    exit();
}

// ---- Step 1: Ensure all recurring invoices are present in owner_rental_fees ----
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

// ---- Step 2: Fetch filtered fees for display ----

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
} else {
    $current_month = date('Y-m');
    $where[] = "f.invoice_month = ?";
    $params[] = $current_month;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// ---- Main fee data query ----
$stmt = $pdo->prepare("
    SELECT 
        f.*, 
        p.property_name, p.property_id,
        o.owner_id, u.full_name AS owner_name, u.phone_number AS owner_phone,
        o.bank_name, o.account_number AS bank_account_number, o.account_holder_name, o.payment_mode,
        oac.management_fee_percent,
        c.client_id, cu.full_name AS client_name, cu.phone_number AS client_phone,
        inv.due_date, inv.payment_status AS client_payment_status
    FROM owner_rental_fees f
    LEFT JOIN client_claims cc ON f.claim_id = cc.claim_id
    LEFT JOIN clients c ON cc.client_id = c.client_id
    LEFT JOIN users cu ON c.user_id = cu.user_id
    LEFT JOIN properties p ON cc.property_id = p.property_id
    LEFT JOIN owners o ON p.owner_id = o.owner_id
    LEFT JOIN users u ON o.user_id = u.user_id
    LEFT JOIN owner_agency_contracts oac ON o.owner_id = oac.owner_id
    LEFT JOIN rental_recurring_invoices inv ON f.invoice_id = inv.invoice_id
    $whereSql
    ORDER BY u.full_name, f.start_period DESC, p.property_name
");
$stmt->execute($params);
$fees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ---- Step 3: Handle fee edits (POST) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_fee_id'])) {
    $fee_id = (int)$_POST['edit_fee_id'];
    $maintenance_fee = isset($_POST['maintenance_fee']) ? floatval($_POST['maintenance_fee']) : 0;
    $tax_fee = isset($_POST['tax_fee']) ? floatval($_POST['tax_fee']) : 0;
    // Recalculate net_transfer after edit
    // Fetch fee and management percent
    $stmt = $pdo->prepare("
        SELECT f.*, oac.management_fee_percent
        FROM owner_rental_fees f
        LEFT JOIN client_claims cc ON f.claim_id = cc.claim_id
        LEFT JOIN properties p ON cc.property_id = p.property_id
        LEFT JOIN owner_agency_contracts oac ON p.owner_id = oac.owner_id
        WHERE f.fee_id = ?
    ");
    $stmt->execute([$fee_id]);
    $fee = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($fee) {
        $mgmt_percent = floatval($fee['management_fee_percent'] ?? 0);
        $mgmt_fee = round($fee['rent_received'] * $mgmt_percent / 100, 2);
        $net_transfer = $fee['rent_received'] - $mgmt_fee - $maintenance_fee - $tax_fee;
        // Save changes
        $up = $pdo->prepare("UPDATE owner_rental_fees SET maintenance_fee = ?, tax_fee = ?, net_transfer = ? WHERE fee_id = ?");
        $up->execute([$maintenance_fee, $tax_fee, $net_transfer, $fee_id]);
    }
    header("Location: manage-owner-fees.php?" . http_build_query($_GET));
    exit();
}

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
            <a href="manage-owner-fees.php" class="btn btn-link">Reset</a>
        </div>
    </form>
    
    <div class="table-responsive">
    <table class="table table-bordered table-hover bg-white align-middle">
<thead class="table-dark">
<tr>
    <th>Owner (Contact & Bank)</th>
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
        $rowClass = 'table-light';
    }
    $period = date('M Y', strtotime($fee['start_period']));
    $mgmt_percent = floatval($fee['management_fee_percent'] ?? 0);
    $mgmt_fee = round($fee['rent_received'] * $mgmt_percent / 100, 2);

    // --- Service level: Is maintenance or tax applicable? ---
    $maintenance_enabled = isset($fee['maintenance_included']) && $fee['maintenance_included'];
    $tax_enabled = isset($fee['tax_included']) && $fee['tax_included'];
    // If not enabled, treat as zero
    $maintenance_fee = $maintenance_enabled ? floatval($fee['maintenance_fee']) : 0;
    $tax_fee = $tax_enabled ? floatval($fee['tax_fee']) : 0;
    $net_transfer = $fee['rent_received'] - $mgmt_fee - $maintenance_fee - $tax_fee;
?>
<tr class="<?= $rowClass ?>">
    <td>
        <strong><?= htmlspecialchars($fee['owner_name'] ?? '-') ?></strong>
        <br><span class="text-muted small"><?= htmlspecialchars($fee['owner_phone'] ?? '-') ?></span>
        <br><span class="text-muted small"><?= htmlspecialchars($fee['bank_name'] ?? '-') ?></span>
        <br><span class="text-muted small">Acct#: <?= htmlspecialchars($fee['bank_account_number'] ?? '-') ?></span>
        <br><span class="text-muted small"><?= htmlspecialchars($fee['account_holder_name'] ?? '-') ?></span>
        <br><em><?= htmlspecialchars($fee['payment_mode'] ?? '-') ?></em>
    </td>
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
<!-- Management fee always calculated and displayed -->
<td>
    <?php
        $mgmt_percent = floatval($fee['management_fee_percent'] ?? 0);
        $mgmt_fee = round($fee['rent_received'] * $mgmt_percent / 100, 2);
        echo number_format($mgmt_fee, 2) . " ({$mgmt_percent}%)";
        // Update db if not set or changed
        if ($mgmt_fee != floatval($fee['management_fee'])) {
            $up = $pdo->prepare("UPDATE owner_rental_fees SET management_fee = ? WHERE fee_id = ?");
            $up->execute([$mgmt_fee, $fee['fee_id']]);
        }
    ?>
    <?php if ($fee['receipt_management']): ?>
        <br><a href="<?= $fee['receipt_management'] ?>" target="_blank">View</a>
    <?php else: ?>
        <br><span class="text-muted small">Not uploaded</span>
    <?php endif; ?>
</td>

<!-- Maintenance Fee -->
<td>
    <form method="post" style="min-width: 120px;">
        <input type="hidden" name="edit_fee_id" value="<?= $fee['fee_id'] ?>">
        <input type="number" step="0.01" min="0" name="maintenance_fee" class="form-control form-control-sm mb-1"
            value="<?= htmlspecialchars($fee['maintenance_fee']) ?>" required>
        <button class="btn btn-sm btn-outline-secondary" type="submit">Save</button>
    </form>
    <?php if ($fee['receipt_maintenance']): ?>
        <br><a href="<?= $fee['receipt_maintenance'] ?>" target="_blank">View</a>
    <?php else: ?>
        <br><span class="text-muted small">Not uploaded</span>
    <?php endif; ?>
</td>
<!-- Tax Fee -->
<td>
    <form method="post" style="min-width: 120px;">
        <input type="hidden" name="edit_fee_id" value="<?= $fee['fee_id'] ?>">
        <input type="number" step="0.01" min="0" name="tax_fee" class="form-control form-control-sm mb-1"
            value="<?= htmlspecialchars($fee['tax_fee']) ?>" required>
        <button class="btn btn-sm btn-outline-secondary" type="submit">Save</button>
    </form>
    <?php if ($fee['tax_receipt']): ?>
        <br><a href="<?= $fee['tax_receipt'] ?>" target="_blank">View</a>
    <?php else: ?>
        <br><span class="text-muted small">Not uploaded</span>
    <?php endif; ?>
</td>

    <td>
        <?php if ($tax_enabled): ?>
            <form method="post" style="min-width: 120px;">
                <input type="hidden" name="edit_fee_id" value="<?= $fee['fee_id'] ?>">
                <input type="number" step="0.01" min="0" name="tax_fee" class="form-control form-control-sm mb-1"
                    value="<?= htmlspecialchars($tax_fee) ?>" required>
                <button class="btn btn-sm btn-outline-secondary" type="submit">Save</button>
            </form>
        <?php else: ?>
            <span class="text-muted">Not applicable</span>
        <?php endif; ?>
        <?php if ($fee['tax_receipt']): ?>
            <br><a href="<?= $fee['tax_receipt'] ?>" target="_blank">View</a>
        <?php else: ?>
            <br><span class="text-muted small">Not uploaded</span>
        <?php endif; ?>
    </td>
<td>
    <?php
        $total_deductions = $mgmt_fee + floatval($fee['maintenance_fee']) + floatval($fee['tax_fee']);
        $net_transfer = $fee['rent_received'] - $total_deductions;
        // Save deductions and net to db if needed
        if ($total_deductions != floatval($fee['total_deductions']) || $net_transfer != floatval($fee['net_transfer'])) {
            $up = $pdo->prepare("UPDATE owner_rental_fees SET total_deductions = ?, net_transfer = ? WHERE fee_id = ?");
            $up->execute([$total_deductions, $net_transfer, $fee['fee_id']]);
        }
        echo '<span class="fw-bold">' . number_format($net_transfer,2) . '</span>';
    ?>
</td>

    <td>
        <?php if ($fee['receipt_management']): ?>
            <<a href="edit-management-fee-receipt.php?fee_id=<?= $fee['fee_id'] ?>" class="btn btn-sm btn-outline-primary">
    Edit Receipts/Fees
</a>
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
            } else {
                echo '<span class="badge bg-success">Completed</span>';
            }
        ?>
    </td>
    <td>
        <a href="manage-owner-fee.php?fee_id=<?= $fee['fee_id'] ?>" class="btn btn-sm btn-outline-primary">
            Upload/Replace Docs
        </a>
        <?php if (!$fee['submitted_to_owner']): ?>
            <form action="submit-owner-fee.php" method="post" style="display:inline;">
                <input type="hidden" name="fee_id" value="<?= $fee['fee_id'] ?>">
                <button class="btn btn-sm btn-success ms-2" type="submit"
                onclick="return confirm('Submit all receipts to owner? Notification will be sent and records locked.');">
                    Submit to Owner
                </button>
            </form>
        <?php else: ?>
            <span class="badge bg-secondary ms-2">Submitted to Owner<br><?= htmlspecialchars($fee['submitted_at']) ?></span>
        <?php endif; ?>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
    </table>
    </div>
    <p class="mt-3 text-muted">Legend: 
        <span class="badge bg-success">Confirmed</span> 
        <span class="badge bg-info">Transferred</span> 
        <span class="badge bg-warning text-dark">Awaiting Client</span> 
        <span class="badge bg-danger">Action Needed</span>
    </p>
</div>

<?php include 'footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
