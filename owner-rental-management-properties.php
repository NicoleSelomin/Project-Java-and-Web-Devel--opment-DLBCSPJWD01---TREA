<?php
/*
|--------------------------------------------------------------------------
| owner-rental-management-properties.php
|--------------------------------------------------------------------------
| Provides property owners with comprehensive details about properties
| managed under rental management, including claims, contracts, deposit
| payments, recurring rent payments, inspection reports, and any related
| warnings.
|
| Standards:
| - Consistent use of Bootstrap 5.3.6 for responsive design
|--------------------------------------------------------------------------
*/

// Session initialization and database connection
session_start();
require 'db_connect.php';

// Redirect to login if user session is not set
if (!isset($_SESSION['owner_id'])) {
    header("Location: user-login.php");
    exit();
}

// Fetch owner details from session
$owner_id = $_SESSION['owner_id'];
$fullName = $_SESSION['user_name'] ?? 'Unknown Owner';

// Database query to retrieve property and claim details
$sql = "
SELECT p.property_id, p.property_name, p.location, p.image,
       s.slug AS service_slug,
       cc.claim_id, cc.client_id, cc.claimed_at, cc.claim_type, cc.claim_source,
       u.full_name AS client_name,
       rc.contract_signed_path, rc.contract_start_date, rc.contract_end_date, rc.contract_discussion_datetime,
       rc.actual_end_date, rc.renewed_contract_path, rc.renewed_contract_end_date, rc.contract_end_manual,
       ri.payment_frequency, ri.invoice_date,
       cc.meeting_datetime, rc.renewal_meeting_datetime,
       cc.meeting_report_path,
       rcp.payment_type, rcp.invoice_path, rcp.payment_proof, rcp.payment_status,
       rw.warning_type, rw.message, rw.sent_at
FROM properties p
JOIN owner_service_requests osr ON osr.request_id = p.request_id
JOIN services s ON osr.service_id = s.service_id
LEFT JOIN client_claims cc ON cc.property_id = p.property_id
LEFT JOIN clients c ON cc.client_id = c.client_id
LEFT JOIN users u ON c.user_id = u.user_id
LEFT JOIN rental_contracts rc ON cc.claim_id = rc.claim_id
LEFT JOIN rental_recurring_invoices ri ON cc.claim_id = ri.claim_id
LEFT JOIN rental_claim_payments rcp ON cc.claim_id = rcp.claim_id
LEFT JOIN rent_warnings rw ON cc.claim_id = rw.claim_id
WHERE p.owner_id = ? AND s.slug = 'rental_property_management' AND p.listing_type = 'rent'
ORDER BY p.property_id DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$owner_id]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Organize data by property
$claims = [];
foreach ($rows as $row) {
    $pid = $row['property_id'];
    $claims[$pid]['info'] = $row;
    if ($row['claim_id']) {
        $claims[$pid]['payments'][] = $row;
        if ($row['warning_type']) {
            $claims[$pid]['warnings'][] = $row;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Rental Management Properties - TREA</title>

    <!-- Bootstrap CSS (5.3.6) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css?v=<?= time() ?>">
</head>
<body class="bg-light">

<?php include 'header.php'; ?>

<main class="container py-5">
    <h2 class="text-primary mb-4">Your Rental-Managed Properties</h2>

    <?php foreach ($claims as $property_id => $data):
        $info = $data['info']; ?>
        <div class="card mb-4 shadow-sm">
            <div class="card-header bg-light">
                <h5><?= htmlspecialchars($info['property_name']) ?></h5>
                <small><?= htmlspecialchars($info['location']) ?></small>
                <a href="view-property.php?property_id=<?= $info['property_id'] ?>" class="btn btn-outline-primary btn-sm ms-2">View Property</a>
            </div>

            <div class="card-body">
                <?php if ($info['claim_id']): ?>
                    <p><strong>Claimed by:</strong> <?= htmlspecialchars($info['client_name']) ?> (<?= date('Y-m-d', strtotime($info['claimed_at'])) ?>)</p>
                    <p><strong>First Inspection:</strong> <?= htmlspecialchars($info['meeting_datetime'] ?? 'Not set') ?></p>

                    <hr>
                    <h6>Deposit Payment</h6>
                    <?php foreach ($data['payments'] as $pay): if ($pay['payment_type'] !== 'deposit') continue; ?>
                        <p>Invoice: <?= $pay['invoice_path'] ? '<a href="'.$pay['invoice_path'].'">View</a>' : 'N/A' ?>,
                           Proof: <?= $pay['payment_proof'] ? '<a href="'.$pay['payment_proof'].'">View</a>' : 'Pending' ?>,
                           Status: <?= $pay['payment_status'] ?></p>
                    <?php endforeach; ?>

                    <?php if ($info['contract_signed_path']): ?>
                        <hr>
                        <h6>Rental Contract</h6>
                        <p>Period: <?= $info['contract_start_date'] ?> to <?= $info['contract_end_date'] ?></p>
                        <a href="<?= $info['contract_signed_path'] ?>">View Contract</a>
                    <?php endif; ?>

                    <?php if (!empty($data['warnings'])): ?>
                        <hr>
                        <h6 class="text-danger">Warnings</h6>
                        <?php foreach ($data['warnings'] as $w): ?>
                            <p><?= htmlspecialchars($w['message']) ?> (<?= $w['sent_at'] ?>)</p>
                        <?php endforeach; ?>
                    <?php endif; ?>

                <?php else: ?>
                    <p class="text-muted">This property has not yet been claimed.</p>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>

    <a href="owner-profile.php" class="btn btn-secondary">Back to dashboard</a>
</main>

<?php include 'footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
