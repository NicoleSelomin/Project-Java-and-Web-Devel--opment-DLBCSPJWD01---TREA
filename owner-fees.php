<?php
/**
 * owner-fees.php
 * -------------------------------------------
 * Owner view of rental income and deductions.
 * Shows each rental period/month, all deductions (mgmt/maintenance/tax),
 * receipts, and transfer proof. Owner can only view, not edit.
 * 
 * Security: Requires owner login.
 */

session_start();
require 'db_connect.php';

// ---- Auth: Only property owners allowed ----
if (empty($_SESSION['owner_id'])) {
    $_SESSION['redirect_after_login'] = 'owner-fees.php';
    header('Location: owner-login.php');
    exit();
}

$owner_id = intval($_SESSION['owner_id']);

// ---- Fetch all rental fee records for this owner ----
// Joins with all needed tables for property/client/receipts info.
$stmt = $pdo->prepare("
    SELECT
        f.*, 
        p.property_id, p.property_name,
        cc.claim_id,
        c.client_id, ucli.full_name AS client_name,
        inv.invoice_date, inv.due_date,
        oac.management_fee_percent
    FROM owner_rental_fees f
    JOIN client_claims cc ON f.claim_id = cc.claim_id
    JOIN properties p ON cc.property_id = p.property_id
    JOIN owners o ON p.owner_id = o.owner_id
    LEFT JOIN owner_agency_contracts oac ON o.owner_id = oac.owner_id
    LEFT JOIN clients c ON cc.client_id = c.client_id
    LEFT JOIN users ucli ON c.user_id = ucli.user_id
    LEFT JOIN rental_recurring_invoices inv ON f.invoice_id = inv.invoice_id
    WHERE o.owner_id = ?
    ORDER BY f.start_period DESC, p.property_name
");
$stmt->execute([$owner_id]);
$fees = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = "My Rental Income & Deductions";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">    
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($page_title) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css?v=<?= time() ?>">
</head>
<body class="bg-light d-flex flex-column min-vh-100">
<?php include 'header.php'; ?>

<div class="container py-4 flex-grow-1">
    <h2 class="mb-4"><?= htmlspecialchars($page_title) ?></h2>
    <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle bg-white">
            <thead class="table-dark">
                <tr>
                    <th>Month/Period</th>
                    <th>Property</th>
                    <th>Client</th>
                    <th>Rent Paid</th>
                    <th>Mgmt Fee</th>
                    <th>Maint. Fee</th>
                    <th>Tax Fee</th>
                    <th>Net to Owner</th>
                    <th>Receipts</th>
                    <th>Transfer Proof</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($fees as $fee): ?>
                    <?php
                    // Row coloring for quick status visualization
                    $rowClass = '';
                    if (empty($fee['transfer_proof'])) {
                        $rowClass = 'table-warning';
                    } elseif (!empty($fee['is_final_transfer']) && $fee['is_final_transfer']) {
                        $rowClass = 'table-success';
                    } else {
                        $rowClass = 'table-light';
                    }
                    $period = date('M Y', strtotime($fee['start_period']));
                    ?>
                    <tr class="<?= $rowClass ?>">
                        <td><?= $period ?></td>
                        <td>
                            <?= htmlspecialchars($fee['property_name']) ?>
                            <br><span class="text-muted small">Property ID: <?= $fee['property_id'] ?></span>
                        </td>
                        <td><?= htmlspecialchars($fee['client_name'] ?? '-') ?></td>
                        <td><?= number_format($fee['rent_received'],2) ?></td>
                        <td>
                            <?= number_format($fee['management_fee'],2) ?>
                            <?php if ($fee['management_fee'] && $fee['receipt_management']): ?>
                                <br><a href="<?= htmlspecialchars($fee['receipt_management']) ?>" target="_blank">Mgmt Receipt</a>
                            <?php endif; ?>
                            <br><span class="text-muted small"><?= $fee['management_fee_percent'] ?>%</span>
                        </td>
                        <td>
                            <?= number_format($fee['maintenance_fee'],2) ?>
                            <?php if ($fee['maintenance_fee'] && $fee['receipt_maintenance']): ?>
                                <br><a href="<?= htmlspecialchars($fee['receipt_maintenance']) ?>" target="_blank">Maint Receipt</a>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= number_format($fee['tax_fee'],2) ?>
                            <?php if ($fee['tax_fee'] && $fee['tax_receipt']): ?>
                                <br><a href="<?= htmlspecialchars($fee['tax_receipt']) ?>" target="_blank">Tax Receipt</a>
                            <?php endif; ?>
                        </td>
                        <td>
                            <b><?= number_format($fee['net_transfer'],2) ?></b>
                        </td>
                        <td>
                            <?php if ($fee['receipt_management']): ?>
                                <a href="<?= htmlspecialchars($fee['receipt_management']) ?>" target="_blank" class="badge bg-info">Mgmt</a>
                            <?php endif; ?>
                            <?php if ($fee['receipt_maintenance']): ?>
                                <a href="<?= htmlspecialchars($fee['receipt_maintenance']) ?>" target="_blank" class="badge bg-info">Maint</a>
                            <?php endif; ?>
                            <?php if ($fee['tax_receipt']): ?>
                                <a href="<?= htmlspecialchars($fee['tax_receipt']) ?>" target="_blank" class="badge bg-info">Tax</a>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($fee['transfer_proof']): ?>
                                <a href="<?= htmlspecialchars($fee['transfer_proof']) ?>" target="_blank" class="badge bg-primary">Proof</a>
                            <?php else: ?>
                                <span class="text-danger small">Not yet transferred</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            if (!empty($fee['is_final_transfer']) && $fee['is_final_transfer']) {
                                echo '<span class="badge bg-success">Final Transfer</span>';
                            } elseif (empty($fee['transfer_proof'])) {
                                echo '<span class="badge bg-warning text-dark">Awaiting Transfer</span>';
                            } else {
                                echo '<span class="badge bg-info">Transferred</span>';
                            }
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (empty($fees)): ?>
            <div class="alert alert-warning mt-3">
                No rental fee records found for your properties.
            </div>
        <?php endif; ?>
    </div>
    <a href="owner-profile.php" class="btn btn-outline-secondary mt-3">Back to My Profile</a>
</div>
<!-- Include Footer -->
<?php include 'footer.php'; ?>

<!-- Bootstrap JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-j1CDi7MgGQ12Z7Qab0qlWQ/Qqz24Gc6BM0thvEMVjHnfYGF0rmFCozFSxQBxwHKO"
        crossorigin="anonymous"></script>

<!-- Script to close main navbar on small screen-->
<script src="navbar-close.js?v=1"></script>
</body>
</html>
