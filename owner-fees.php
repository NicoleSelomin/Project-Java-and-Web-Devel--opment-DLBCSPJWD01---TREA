<?php
/*
|--------------------------------------------------------------------------
| owner-fees.php
|--------------------------------------------------------------------------
| Provides property owners with a detailed breakdown of rental-related fees,
| including rent received, management, maintenance, tax fees, and proof of
| payments or transfers. Owners can also confirm receipt of payments.
|
| - Bootstrap 5.3.6 for responsive and uniform styling
|--------------------------------------------------------------------------
*/

// Required files for authentication and database connection
require 'check-user-session.php';
require 'db_connect.php';

// Retrieve the current owner's ID from session
$owner_id = $_SESSION['owner_id'];

// Fetch rental fees from the database for logged-in owner
$stmt = $pdo->prepare("
    SELECT f.*, p.property_name, rpd.service_level, osr.owner_contract_path
    FROM owner_rental_fees f
    JOIN client_claims cc ON f.claim_id = cc.claim_id
    JOIN owner_service_requests osr ON cc.claim_id = osr.request_id
    JOIN properties p ON cc.property_id = p.property_id
    JOIN rental_property_management_details rpd ON f.detail_id = rpd.detail_id
    WHERE p.owner_id = ?
    ORDER BY f.created_at DESC
");
$stmt->execute([$owner_id]);
$fees = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Rental Property Fees - TREA</title>

    <!-- Bootstrap CSS (v5.3.6) for consistent UI styling -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-4Q6Gf2aSP4eDXB8Miphtr37CMZZQ5oXLH2yaXMJ2w8e2ZtHTl7GptT4jmndRuHDT" crossorigin="anonymous">

    <!-- Custom Styles -->
    <link rel="stylesheet" href="styles.css?v=<?= time() ?>">
</head>
<body class="d-flex flex-column min-vh-100 bg-light">

<!-- Include site-wide header -->
<?php include 'header.php'; ?>

<div class="container-fluid flex-grow-1 py-4">
    <div class="row">

<!-- Main Content Container -->
<main class="col-12 col-md-11 ms-lg-5">

    <!-- Page Heading -->
    <h2 class="text-primary mb-4">Rental Property Fee Breakdown</h2>

    <?php if (empty($fees)): ?>
        <!-- Alert shown if no fees are available -->
        <div class="alert alert-info text-center">No fee breakdowns available yet.</div>
    <?php else: ?>
        <!-- Fees Table Container -->
        <div class="table-responsive">
            <table class="table table-bordered table-hover">

                <!-- Table Header -->
                <thead class="table-secondary">
                    <tr>
                        <th>Property</th>
                        <th>Service Level</th>
                        <th>Contract</th>
                        <th>Rent Received</th>
                        <th>Management Fee</th>
                        <th>Mgmt Receipt</th>
                        <th>Maintenance Fee</th>
                        <th>Maint. Receipt</th>
                        <th>Tax Fee</th>
                        <th>Tax Receipt</th>
                        <th>Transfer Amount</th>
                        <th>Transfer Proof</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <!-- Table Body with Fee Details -->
                <tbody>
                <?php foreach ($fees as $fee): ?>
                    <tr>
                        <!-- Property Name -->
                        <td><?= htmlspecialchars($fee['property_name']) ?></td>

                        <!-- Service Level (formatted clearly) -->
                        <td><?= ucfirst(str_replace('_', ' + ', $fee['service_level'])) ?></td>

                        <!-- Contract Link -->
                        <td><a href="<?= htmlspecialchars($fee['owner_contract_path']) ?>" target="_blank">View</a></td>

                        <!-- Rent Received -->
                        <td><?= number_format($fee['rent_received']) ?> FCFA</td>

                        <!-- Management Fee and Receipt -->
                        <td><?= number_format($fee['management_fee']) ?> FCFA</td>
                        <td>
                            <?php if ($fee['management_receipt']): ?>
                                <a href="<?= htmlspecialchars($fee['management_receipt']) ?>" target="_blank">View</a>
                            <?php endif; ?>
                        </td>

                        <!-- Maintenance Fee and Receipt -->
                        <td><?= number_format($fee['maintenance_fee']) ?> FCFA</td>
                        <td>
                            <?php if ($fee['maintenance_receipt']): ?>
                                <a href="<?= htmlspecialchars($fee['maintenance_receipt']) ?>" target="_blank">View</a>
                            <?php endif; ?>
                        </td>

                        <!-- Tax Fee and Receipt -->
                        <td><?= number_format($fee['tax_fee']) ?> FCFA</td>
                        <td>
                            <?php if ($fee['tax_receipt']): ?>
                                <a href="<?= htmlspecialchars($fee['tax_receipt']) ?>" target="_blank">View</a>
                            <?php endif; ?>
                        </td>

                        <!-- Transfer Amount and Proof -->
                        <td class="fw-bold"><?= number_format($fee['net_transfer']) ?> FCFA</td>
                        <td>
                            <?php if ($fee['transfer_proof']): ?>
                                <a href="<?= htmlspecialchars($fee['transfer_proof']) ?>" target="_blank">View</a>
                            <?php endif; ?>
                        </td>

                        <!-- Confirmation Status -->
                        <td><?= $fee['owner_confirmed'] ? 'Confirmed' : 'Pending' ?></td>

                        <!-- Action: Confirmation Button -->
                        <td>
                            <?php if (!$fee['owner_confirmed']): ?>
                                <form method="POST" action="confirm-owner-fee.php" onsubmit="return confirm('Confirm receipt?');">
                                    <input type="hidden" name="fee_id" value="<?= $fee['fee_id'] ?>">
                                    <button type="submit" class="btn btn-success btn-sm">Confirm</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <a href="cowner-profile.php" class="btn bg-dark text-white fw-bold">🡰 Back to dashboard</a>

</main>
</div>
</div>

<!-- Include site-wide footer -->
<?php include 'footer.php'; ?>

<!-- Bootstrap JavaScript Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-j1CDi7MgGQ12Z7Qab0qlWQ/Qqz24Gc6BM0thvEMVjHnfYGF0rmFCozFSxQBxwHKO"
        crossorigin="anonymous"></script>

<!-- Script to close main navbar on small screen-->
<script src="navbar-close.js?v=1"></script>

</body>
</html>
