<?php
// view-all-invoices.php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['staff_id']) || !in_array(strtolower($_SESSION['role']), ['accountant', 'general manager'])) {
    header("Location: staff-login.php");
    exit();
}

// Optional filters
$where = "1";
$params = [];

if (!empty($_GET['client_name'])) {
    $where .= " AND u.full_name LIKE ?";
    $params[] = '%' . $_GET['client_name'] . '%';
}
if (!empty($_GET['property_name'])) {
    $where .= " AND p.property_name LIKE ?";
    $params[] = '%' . $_GET['property_name'] . '%';
}

$stmt = $pdo->prepare("
    SELECT ri.*, u.full_name AS client_name, p.property_name, p.location, rc.amount AS monthly_rent
    FROM rental_recurring_invoices ri
    JOIN client_claims cc ON ri.claim_id = cc.claim_id
    JOIN clients c ON cc.client_id = c.client_id
    JOIN users u ON c.user_id = u.user_id
    JOIN properties p ON cc.property_id = p.property_id
    JOIN rental_contracts rc ON cc.claim_id = rc.claim_id
    WHERE $where
    ORDER BY ri.invoice_date DESC
    LIMIT 200
");
$stmt->execute($params);
$invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>All Rent Invoices</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css">
</head>
<body>
<?php include 'header.php'; ?>
<div class="container py-5">
    <h2 class="text-primary mb-4">All Recurring Rent Invoices</h2>
    <form class="row g-2 mb-4" method="get">
        <div class="col-md-4">
            <input type="text" name="client_name" class="form-control" placeholder="Filter by Client" value="<?= htmlspecialchars($_GET['client_name'] ?? '') ?>">
        </div>
        <div class="col-md-4">
            <input type="text" name="property_name" class="form-control" placeholder="Filter by Property" value="<?= htmlspecialchars($_GET['property_name'] ?? '') ?>">
        </div>
        <div class="col-md-2">
            <button class="btn btn-outline-primary w-100">Filter</button>
        </div>
        <div class="col-md-2">
            <a href="view-all-invoices.php" class="btn btn-secondary w-100">Reset</a>
        </div>
    </form>
    <div class="table-responsive">
        <table class="table table-bordered align-middle">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Client</th>
                    <th>Property</th>
                    <th>Invoice Date</th>
                    <th>Due Date</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Proof</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($invoices as $i => $inv): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= htmlspecialchars($inv['client_name']) ?></td>
                    <td>
                        <?= htmlspecialchars($inv['property_name']) ?>
                        <small class="text-muted"><?= htmlspecialchars($inv['location']) ?></small>
                    </td>
                    <td><?= htmlspecialchars($inv['invoice_date']) ?></td>
                    <td><?= htmlspecialchars($inv['due_date']) ?></td>
                    <td><?= number_format($inv['amount']) ?></td>
                    <td>
                        <?php if ($inv['payment_status'] === 'confirmed'): ?>
                            <span class="badge bg-success">Paid</span>
                        <?php else: ?>
                            <span class="badge bg-warning text-dark">Pending</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($inv['payment_proof']): ?>
                            <a href="<?= htmlspecialchars($inv['payment_proof']) ?>" target="_blank">View</a>
                        <?php else: ?>
                            <span class="text-muted">None</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="generate-rent-invoice.php?invoice_id=<?= $inv['invoice_id'] ?>" target="_blank" class="btn btn-outline-primary btn-sm">View/Download</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!count($invoices)): ?>
                <tr><td colspan="9" class="text-center text-muted">No invoices found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <a href="staff-profile.php" class="btn btn-secondary mt-4">← Back to Dashboard</a>
</div>
</body>
</html>
