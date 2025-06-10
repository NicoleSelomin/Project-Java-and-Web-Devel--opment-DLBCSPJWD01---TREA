<!-- client-claimed-rental-management.php -->

<?php
// -----------------------------------------------------------------------------
// client-claimed-rental-management.php
// Shows all rental management claims and contract/payment/warning info for client
// -----------------------------------------------------------------------------

session_start();
require 'db_connect.php';

// 1. Require client login
if (!isset($_SESSION['client_id'])) {
    header("Location: user-login.php");
    exit();
}

$client_id = $_SESSION['client_id'];

// -----------------------------------------------------------------------------
// 2. Fetch all claims for rental management (for this client)
//    - Each claim row contains info from client_claims, property, rental_contracts
// -----------------------------------------------------------------------------
$claimStmt = $pdo->prepare("
    SELECT cc.claim_id, cc.property_id, cc.claimed_at, cc.claim_type, cc.claim_source,
           p.property_name, p.location, p.image,
           rc.contract_signed_path, rc.contract_start_date, rc.contract_end_date, rc.contract_discussion_datetime,
           rc.renewed_contract_path, rc.renewed_contract_end_date, rc.actual_end_date, rc.contract_end_manual,
           cc.meeting_datetime, cc.meeting_report_path, rc.renewal_requested_datetime, rc.renewal_meeting_datetime,
           cc.final_inspection_datetime, cc.final_inspection_report_path
    FROM client_claims cc
    JOIN properties p ON cc.property_id = p.property_id
    LEFT JOIN rental_contracts rc ON cc.claim_id = rc.claim_id
    WHERE cc.client_id = ? AND cc.claim_type = 'rent' AND cc.claim_source = 'rental_property_management'
    ORDER BY cc.claimed_at DESC
");
$claimStmt->execute([$client_id]);
$claimRows = $claimStmt->fetchAll(PDO::FETCH_ASSOC);

// -----------------------------------------------------------------------------
// 3. Fetch all payment records (deposit, claim, etc.) for all client rental claims
// -----------------------------------------------------------------------------
$payStmt = $pdo->prepare("
    SELECT * FROM rental_claim_payments WHERE claim_id IN (
        SELECT claim_id FROM client_claims 
        WHERE client_id = ? AND claim_type = 'rent' AND claim_source = 'rental_property_management'
    )
");
$payStmt->execute([$client_id]);
$allPayments = $payStmt->fetchAll(PDO::FETCH_ASSOC);

// -----------------------------------------------------------------------------
// 4. Fetch all recurring rent invoices (for ongoing rent payment schedule)
// -----------------------------------------------------------------------------
$recurringStmt = $pdo->prepare("
    SELECT * FROM rental_recurring_invoices WHERE claim_id IN (
        SELECT claim_id FROM client_claims 
        WHERE client_id = ? AND claim_type = 'rent' AND claim_source = 'rental_property_management'
    )
");
$recurringStmt->execute([$client_id]);
$allRecurring = $recurringStmt->fetchAll(PDO::FETCH_ASSOC);

// -----------------------------------------------------------------------------
// 5. Fetch all warning messages sent for these claims (late rent, etc.)
// -----------------------------------------------------------------------------
$warnStmt = $pdo->prepare("
    SELECT * FROM rent_warnings WHERE claim_id IN (
        SELECT claim_id FROM client_claims 
        WHERE client_id = ? AND claim_type = 'rent' AND claim_source = 'rental_property_management'
    )
");
$warnStmt->execute([$client_id]);
$allWarnings = $warnStmt->fetchAll(PDO::FETCH_ASSOC);

// -----------------------------------------------------------------------------
// 6. Build a clean $claims array structure:
//    - $claims[claim_id]['info']       = claim/property/contract info
//    - $claims[claim_id]['payments'][] = each payment record
//    - $claims[claim_id]['recurring'][]= each recurring rent invoice
//    - $claims[claim_id]['warnings'][] = each warning
// -----------------------------------------------------------------------------
$claims = [];
foreach ($claimRows as $row) {
    $claims[$row['claim_id']]['info'] = $row;
    $claims[$row['claim_id']]['payments'] = [];
    $claims[$row['claim_id']]['recurring'] = [];
    $claims[$row['claim_id']]['warnings'] = [];
}
foreach ($allPayments as $pay) {
    $claims[$pay['claim_id']]['payments'][] = $pay;
}
foreach ($allRecurring as $r) {
    $claims[$r['claim_id']]['recurring'][] = $r;
}
foreach ($allWarnings as $w) {
    $claims[$w['claim_id']]['warnings'][] = [
        'type' => $w['warning_type'],
        'message' => $w['message'],
        'sent_at' => $w['sent_at'],
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Claimed Rental Properties</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css?v=<?= time() ?>">
</head>
<body class="bg-light text-dark d-flex flex-column min-vh-100">
<?php include 'header.php'; ?>

<div class="container-fluid flex-grow-1">
    <main class="p-3">
        <!-- Title -->
        <div class="mb-4 p-3 border rounded shadow-sm" style="border-color: #FF69B4;">
            <h2 class="highlight">Your Rental Management Claims</h2>
        </div>

        <!-- Loop through each claim -->
        <?php foreach ($claims as $claim_id => $data): 
            $info = $data['info'];
            $payments = $data['payments'];
        ?>
        <div class="card mb-4 shadow-sm">
            <div class="card-header bg-white text-dark fw-bold">
                <?= htmlspecialchars($info['property_name']) ?> — <?= htmlspecialchars($info['location']) ?>
            </div>
            <div class="card-body">
                <!-- Date claimed and property image -->
                <p><strong>Claimed On:</strong> <?= date('Y-m-d', strtotime($info['claimed_at'])) ?></p>
                <?php if ($info['image']): ?>
                    <div class="mb-3">
                        <img src="<?= $info['image'] ?>" class="img-thumbnail" style="max-width: 120px;">
                    </div>
                <?php endif; ?>
                <a href="view-property.php?property_id=<?= $info['property_id'] ?>" class="btn btn-outline-dark btn-sm mb-3">View Property</a>

                <hr>
                <!-- Initial inspection visit and report -->
                <?php if ($info['meeting_datetime'] || $info['meeting_report_path']): ?>
                    <h5 class="section-title">Initial Property Visit</h5>
                    <?php if ($info['meeting_datetime']): ?>
                        <p><strong>Scheduled On:</strong> <?= $info['meeting_datetime'] ?></p>
                    <?php endif; ?>
                    <?php if ($info['meeting_report_path']): ?>
                        <a href="<?= $info['meeting_report_path'] ?>" target="_blank">View Initial Report</a>
                    <?php endif; ?>
                <?php endif; ?>

                <hr>

                <!-- Contract signing meeting -->
                <?php if ($info['contract_discussion_datetime']): ?>
                    <p><strong>Contract Signing Meeting:</strong> <?= $info['contract_discussion_datetime'] ?></p>
                <?php endif; ?>

                <!-- Signed contract, show start/end date and link -->
                <?php if ($info['contract_signed_path']): ?>
                    <h5 class="section-title">Rental Contract</h5>
                    <p>
                        <strong>Start:</strong> <?= $info['contract_start_date'] ?>
                        — <strong>End:</strong> <?= $info['contract_end_date'] ?>
                    </p>
                    <a href="<?= $info['contract_signed_path'] ?>" target="_blank">View Contract</a>
                <?php endif; ?>
                <?php if ($info['contract_end_manual']): ?>
                    <div class="alert alert-warning mt-2">Contract was manually ended.</div>
                <?php endif; ?>

                <hr>

                <!-- Payments (claim, deposit, etc.) -->
                <h5 class="section-title">Payments & Invoices</h5>
                <table class="table table-bordered table-sm">
                    <thead class="table-light">
                        <tr><th>Type</th><th>Invoice</th><th>Upload Proof</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $pay): ?>
                            <tr>
                                <td><?= ucfirst($pay['payment_type']) ?></td>
                                <td>
                                    <?= $pay['invoice_path'] ? '<a href="'.$pay['invoice_path'].'" target="_blank">View</a>' : '<span class="text-muted">Not issued</span>' ?>
                                </td>
                                <td>
                                    <?php if (!$pay['payment_proof'] && $pay['invoice_path']): ?>
                                        <!-- Payment proof upload form, only if invoice is present and no proof yet -->
                                        <form method="POST" action="upload-payment-proof.php" enctype="multipart/form-data">
                                            <input type="hidden" name="payment_id" value="<?= $pay['payment_id'] ?>">
                                            <input type="file" name="payment_proof" required class="form-control form-control-sm">
                                            <button class="btn btn-sm btn-secondary mt-1">Upload</button>
                                        </form>
                                    <?php elseif ($pay['payment_proof']): ?>
                                        <a href="<?= $pay['payment_proof'] ?>" target="_blank">Proof</a>
                                    <?php else: ?>
                                        <span class="text-muted">Waiting invoice</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= $pay['payment_status'] === 'confirmed' ? '<span class="text-success">Confirmed</span>' : '<span class="text-warning">Pending</span>' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Renewal section -->
                <?php if ($info['renewed_contract_path']): ?>
                    <h5 class="section-title">Contract Renewal</h5>
                    <p><strong>New End Date:</strong> <?= $info['renewed_contract_end_date'] ?></p>
                    <a href="<?= $info['renewed_contract_path'] ?>" target="_blank">View Renewal</a>
                <?php endif; ?>

                <?php if ($info['actual_end_date']): ?>
                    <div class="alert alert-danger mt-2">Contract ended on <?= $info['actual_end_date'] ?></div>
                <?php endif; ?>

                <!-- Trigger for renewal form if close to end date -->
                <?php 
                $today = date('Y-m-d');
                $renewal_trigger_date = date('Y-m-d', strtotime("-3 months", strtotime($info['contract_end_date'])));
                if (!$info['renewal_requested_datetime'] && $today >= $renewal_trigger_date && !$info['actual_end_date']): ?>
                    <form method="POST" action="request-renewal.php" class="mt-3">
                        <input type="hidden" name="claim_id" value="<?= $claim_id ?>">
                        <button class="btn btn-warning">Request Contract Renewal</button>
                    </form>
                <?php elseif ($info['renewal_requested_datetime']): ?>
                    <div class="alert alert-info mt-3">Renewal request submitted.</div>
                <?php endif; ?>

                <?php if ($info['renewal_meeting_datetime']): ?>
                    <p><strong>Renewal Meeting:</strong> <?= $info['renewal_meeting_datetime'] ?></p>
                <?php endif; ?>

                <!-- Final inspection info -->
                <?php if ($info['final_inspection_datetime']): ?>
                    <h5 class="section-title">Final Property Inspection</h5>
                    <p>Scheduled On: <?= $info['final_inspection_datetime'] ?></p>
                    <?php if ($info['final_inspection_report_path']): ?>
                        <a href="<?= $info['final_inspection_report_path'] ?>" target="_blank">View Final Report</a>
                    <?php endif; ?>
                <?php endif; ?>

                <!-- All warnings for this claim -->
                <?php if (!empty($data['warnings'])): ?>
                    <h5 class="section-title">Warnings</h5>
                    <ul class="list-group">
                        <?php foreach ($data['warnings'] as $warn): ?>
                            <li class="list-group-item">
                                <strong><?= ucfirst($warn['type']) ?>:</strong> <?= htmlspecialchars($warn['message']) ?><br>
                                <small class="text-muted">Sent: <?= $warn['sent_at'] ?></small>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>

        <a href="client-profile.php" class="btn btn-dark mt-4">← Back to Dashboard</a>
    </main>
</div>

<?php include 'footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
