<?php
/**
 * Owner Rental-Managed Properties (TREA)
 * - Shows all owner rental-managed properties, claimed or not
 * - Shows thumbnail, links to property, contracts, meetings, payments, invoices, latest "notice to vacate"
 * - Bootstrap 5.3.6, responsive, well-commented
 */
session_start();
require 'db_connect.php';

// Security: Only owners allowed
if (!isset($_SESSION['owner_id'])) {
    header("Location: user-login.php");
    exit();
}
$owner_id = $_SESSION['owner_id'];
$fullName = $_SESSION['user_name'] ?? 'Unknown Owner';

// Fetch all relevant property/claim/payment data
$sql = "
SELECT
    p.property_id, p.property_name, p.location, p.image,
    s.slug AS service_slug,
    cc.claim_id, cc.client_id, cc.claimed_at, cc.claim_type, cc.claim_source,
    cc.meeting_datetime,
    u.full_name AS client_name,
    rc.contract_signed_path, rc.contract_start_date, rc.contract_end_date, rc.contract_discussion_datetime,
    rc.actual_end_date, rc.allow_termination, rc.payment_frequency, rc.amount, rc.grace_period_days, rc.locked,
    rcp.payment_id, rcp.payment_type, rcp.invoice_path, rcp.payment_proof, rcp.payment_status,
    ri.invoice_id, ri.invoice_date, ri.due_date, ri.payment_proof AS rent_proof, ri.payment_status AS rent_status
FROM properties p
    JOIN owner_service_requests osr ON osr.request_id = p.request_id
    JOIN services s ON osr.service_id = s.service_id
    LEFT JOIN client_claims cc ON cc.property_id = p.property_id
    LEFT JOIN clients c ON cc.client_id = c.client_id
    LEFT JOIN users u ON c.user_id = u.user_id
    LEFT JOIN rental_contracts rc ON cc.claim_id = rc.claim_id
    LEFT JOIN rental_claim_payments rcp ON cc.claim_id = rcp.claim_id
    LEFT JOIN rental_recurring_invoices ri ON cc.claim_id = ri.claim_id
WHERE
    p.owner_id = ?
    AND s.slug = 'rental_property_management'
    AND p.listing_type = 'rent'
ORDER BY p.property_id DESC, cc.claimed_at DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$owner_id]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Organize data by property, then by claim
$properties = [];
foreach ($rows as $row) {
    $pid = $row['property_id'];
    if (!isset($properties[$pid])) {
        $properties[$pid]['info'] = [
            'property_id'   => $row['property_id'],
            'property_name' => $row['property_name'],
            'location'      => $row['location'],
            'image'         => $row['image'],
            'service_slug'  => $row['service_slug'],
        ];
    }
    if ($row['claim_id']) {
        $claim_id = $row['claim_id'];
        if (!isset($properties[$pid]['claims'][$claim_id])) {
            $properties[$pid]['claims'][$claim_id] = [
                'claim_id'    => $row['claim_id'],
                'client_id'   => $row['client_id'],
                'client_name' => $row['client_name'],
                'claimed_at'  => $row['claimed_at'],
                'claim_type'  => $row['claim_type'],
                'claim_source'=> $row['claim_source'],
                'meeting_datetime'   => $row['meeting_datetime'],
                'contract' => [
                    'contract_signed_path'         => $row['contract_signed_path'],
                    'contract_start_date'          => $row['contract_start_date'],
                    'contract_end_date'            => $row['contract_end_date'],
                    'contract_discussion_datetime' => $row['contract_discussion_datetime'],
                    'actual_end_date'              => $row['actual_end_date'],
                    'allow_termination'            => $row['allow_termination'],
                    'locked'                       => $row['locked'],
                ],
                'payments'      => [],
                'rent_invoices' => [],
                'notice'        => null, // placeholder for notice
            ];
        }
        if ($row['payment_id']) {
            $properties[$pid]['claims'][$claim_id]['payments'][$row['payment_id']] = [
                'payment_type'   => $row['payment_type'],
                'invoice_path'   => $row['invoice_path'],
                'payment_proof'  => $row['payment_proof'],
                'payment_status' => $row['payment_status'],
            ];
        }
        if ($row['invoice_id']) {
            $properties[$pid]['claims'][$claim_id]['rent_invoices'][$row['invoice_id']] = [
                'invoice_date'      => $row['invoice_date'],
                'due_date'          => $row['due_date'],
                'amount'            => $row['amount'],
                'payment_frequency' => $row['payment_frequency'],
                'payment_proof'     => $row['rent_proof'],
                'payment_status'    => $row['rent_status'],
            ];
        }
    }
}

// Fetch latest notice for each claim, if any
foreach ($properties as $pid => &$prop) {
    if (!empty($prop['claims'])) {
        foreach ($prop['claims'] as $cid => &$claim) {
            $noticeStmt = $pdo->prepare("
                SELECT * FROM rent_notices
                WHERE contract_id = ?
                ORDER BY sent_at DESC LIMIT 1
            ");
            $noticeStmt->execute([$claim['claim_id']]);
            $notice = $noticeStmt->fetch(PDO::FETCH_ASSOC);
            $claim['notice'] = $notice ?: null;
        }
        unset($claim);
    }
}
unset($prop);

// Split into claimed / not claimed
$claimed = [];
$not_claimed = [];
foreach ($properties as $prop_id => $prop) {
    if (!empty($prop['claims'])) {
        $claimed[$prop_id] = $prop;
    } else {
        $not_claimed[$prop_id] = $prop;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Rental-Managed Properties - TREA</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css?v=<?= time() ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="d-flex flex-column min-vh-100 bg-light">
<?php include 'header.php'; ?>
<div class="container-fluid flex-grow-1 py-4">
    <main class="container">
        <div class="p-3 mb-4 border rounded shadow-sm main-title">
            <h2>Rental-Managed Properties <small class="text-muted fs-6">for <?= htmlspecialchars($fullName) ?></small></h2>
        </div>
        <!-- Tabs -->
        <ul class="nav nav-tabs mb-4" id="propertyTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active custom-btn" id="claimed-tab" data-bs-toggle="tab" data-bs-target="#claimed" type="button" role="tab" aria-controls="claimed" aria-selected="true">
                    Reserved Properties (<?= count($claimed) ?>)
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link custom-btn" id="notclaimed-tab" data-bs-toggle="tab" data-bs-target="#notclaimed" type="button" role="tab" aria-controls="notclaimed" aria-selected="false">
                    Not Reserved (<?= count($not_claimed) ?>)
                </button>
            </li>
        </ul>
        <div class="tab-content" id="propertyTabsContent">

            <!-- Claimed Properties -->
            <div class="tab-pane fade show active" id="claimed" role="tabpanel" aria-labelledby="claimed-tab">
                <?php if (empty($claimed)): ?>
                    <div class="alert alert-info my-4">No Reserved properties yet.</div>
                <?php else: ?>
                    <?php foreach ($claimed as $property_id => $data):
                        $info = $data['info'];
                        $img_src = $info['image'] ? htmlspecialchars($info['image']) : 'uploads/properties/default.jpg';
                    ?>
                    <div class="card mb-4 shadow-sm">
                        <div class="card-header bg-light d-flex align-items-center">
                            <a href="view-property.php?property_id=<?= $info['property_id'] ?>" target="_blank" class="me-3">
                                <img src="<?= $img_src ?>" alt="Property" width="46" height="46" class="rounded border" style="object-fit:cover;">
                            </a>
                            <div>
                                <h5 class="mb-0"><?= htmlspecialchars($info['property_name']) ?></h5>
                                <small class="text-muted"><?= htmlspecialchars($info['location']) ?></small>
                            </div>
                        </div>
                        <div class="card-body">
                        <?php foreach ($data['claims'] as $claim): 
                            // Fetch all inspection reports for a claim
                            $stmt = $pdo->prepare("SELECT * FROM inspection_reports WHERE claim_id = ?");
                            $stmt->execute([$claim['claim_id']]);
                            $inspection_reports = [];
                            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                $inspection_reports[$row['inspection_type']] = $row;
                            }
                        ?>
                        <div class="mb-3 pb-3 border-bottom">
                            <p>
                                <strong>Claimed by:</strong> <?= htmlspecialchars($claim['client_name'] ?? 'Client') ?>
                                (<?= $claim['claimed_at'] ? date('Y-m-d', strtotime($claim['claimed_at'])) : 'N/A' ?>)
                            </p>
                            <!-- MEETING SCHEDULES -->
                            <div class="mb-2">
                                <h6 class="section-title">Meetings & Inspections</h6>
                                <strong>Initial Inspection:</strong>
                                <?= !empty($claim['meeting_datetime']) ? date('Y-m-d H:i', strtotime($claim['meeting_datetime'])) : '<span class="text-muted">Not scheduled</span>' ?>
                                <br>
                                <strong>Contract Discussion Meeting:</strong>
                                <?= !empty($claim['contract']['contract_discussion_datetime']) ? date('Y-m-d H:i', strtotime($claim['contract']['contract_discussion_datetime'])) : '<span class="text-muted">Not scheduled</span>' ?>
                                <br>
                                <strong>Final Inspection:</strong>
                                <?= !empty($claim['contract']['final_inspection_datetime']) ? date('Y-m-d H:i', strtotime($claim['contract']['final_inspection_datetime'])) : '<span class="text-muted">Not scheduled</span>' ?>
                            </div>
                            <!-- Inspection Reports -->
                            <h6 class="mt-4">Inspection Reports</h6>
                            <div class="row">
                                <?php foreach (['initial'=>'Initial Inspection', 'final'=>'Final Inspection'] as $type => $label): 
                                    $r = $inspection_reports[$type] ?? null;
                                ?>
                                <div class="col-md-6 mb-3">
                                    <div class="border rounded p-3 h-100">
                                        <strong><?= $label ?></strong><br>
                                        <?php if ($r): ?>
                                            <div class="mb-2">
                                                <span>Report:
                                                    <?= $r['pdf_path'] 
                                                        ? '<a href="'.htmlspecialchars($r['pdf_path']).'" target="_blank">View PDF</a>'
                                                        : '<span class="text-muted">Not uploaded</span>' ?>
                                                </span>
                                            </div>
                                            <div>
                                                <strong>Signatures:</strong>
                                                Client: <?= $r['client_signed_at'] ? '<span class="text-success">Signed</span>' : '<span class="text-warning">Pending</span>' ?>
                                                |
                                                Owner: <?= $r['owner_signed_at'] ? '<span class="text-success">Signed</span>' : '<span class="text-warning">Pending</span>' ?>
                                            </div>
                                            <div class="mt-2">
                                                <?php if (!$r['owner_signed_at'] && $r['pdf_path']): ?>
                                                    <button 
                                                        class="btn btn-sm custom-btn" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#signModal" 
                                                        data-report-id="<?= $r['report_id'] ?>"
                                                    >Sign as Owner</button>
                                                <?php elseif ($r['owner_signed_at']): ?>
                                                    <span class="badge bg-success">You have signed</span>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">No report submitted for this inspection yet.</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <hr>
<!-- CONTRACT SIGNING -->
<h6 class="mt-3">Rental Contract</h6>
<?php
$contractSignedPath = $claim['contract']['contract_signed_path'] ?? null;
$contractStart      = $claim['contract']['contract_start_date'] ?? '';
$contractEnd        = $claim['contract']['contract_end_date'] ?? '';
$contractLocked     = $claim['contract']['locked'] ?? 0;

// Get signature columns from rental_contracts for this claim
$contractRow = [];
if ($claim['claim_id']) {
    $contractStmt = $pdo->prepare("SELECT owner_signature, client_signature, owner_signed_at, client_signed_at FROM rental_contracts WHERE claim_id = ?");
    $contractStmt->execute([$claim['claim_id']]);
    $contractRow = $contractStmt->fetch(PDO::FETCH_ASSOC) ?: [];
}
$isOwnerSigned  = !empty($contractRow['owner_signature']);
$isClientSigned = !empty($contractRow['client_signature']);
$signUrl        = 'sign-lease-contract.php?claim_id=' . urlencode($claim['claim_id']);
$downloadUrl    = 'download-lease-contract.php?claim_id=' . urlencode($claim['claim_id']);
?>
<div>
    <div>
        <strong>Contract Period:</strong>
        <?= htmlspecialchars($contractStart) ?> to <?= htmlspecialchars($contractEnd) ?>
    </div>
    <div class="mt-2">
        <span class="badge bg-info">Owner signature: <?= $isOwnerSigned ? '✔️' : 'Pending' ?></span>
        <span class="badge bg-info ms-2">Client signature: <?= $isClientSigned ? '✔️' : 'Pending' ?></span>
    </div>
    <?php if ($contractLocked && !$isOwnerSigned): ?>
        <a href="<?= $signUrl ?>" class="btn custom-btn mt-2">View/Sign Contract</a>
    <?php endif; ?>
    <?php if ($isOwnerSigned && $isClientSigned && $contractSignedPath && file_exists($contractSignedPath)): ?>
        <a href="<?= $downloadUrl ?>" target="_blank">Download Signed Contract</a>
    <?php elseif ($isOwnerSigned || $isClientSigned): ?>
        <div class="alert alert-info mt-2">Waiting for all parties to sign before contract is downloadable.</div>
    <?php else: ?>
        <div class="alert alert-secondary mt-2">Contract will be available after signature by all parties.</div>
    <?php endif; ?>
</div>
<hr>

<?php
$depositPayment = null;
foreach ($claim['payments'] as $pay) {
    if ($pay['payment_type'] === 'deposit') {
        $depositPayment = $pay;
        break;
    }
}
?>

<?php if ($depositPayment): ?>
    <div class="mb-2">
        <strong>Deposit Payment Proof:</strong>
        <?php if (!empty($depositPayment['payment_proof'])): ?>
            <a href="<?= htmlspecialchars($depositPayment['payment_proof']) ?>" target="_blank">
                View Proof
            </a>
        <?php else: ?>
            <span class="text-muted">Not uploaded yet.</span>
        <?php endif; ?>
        <!-- Optional: Show payment status -->
        <?php if ($depositPayment['payment_status'] === 'confirmed'): ?>
            <span class="badge bg-success ms-2">Confirmed</span>
        <?php elseif ($depositPayment['payment_status'] === 'pending'): ?>
            <span class="badge bg-warning ms-2 text-dark">Pending</span>
        <?php else: ?>
            <span class="badge bg-secondary ms-2"><?= htmlspecialchars($depositPayment['payment_status']) ?></span>
        <?php endif; ?>
    </div>
<?php endif; ?>

<hr>
<!-- RECURRING RENT INVOICES -->
<h6 class="mt-4">Recurring Rent Invoices</h6>
<?php if (!empty($claim['rent_invoices'])): ?>
    <div class="table-responsive">
    <?php
    $currentUnpaid = null;
    $lastConfirmed = null;
    foreach ($claim['rent_invoices'] as $inv) {
        if ($inv['payment_status'] !== 'confirmed' && !$currentUnpaid) $currentUnpaid = $inv;
    }
    $confirmed = array_filter($claim['rent_invoices'], fn($i) => $i['payment_status'] === 'confirmed');
    if (!empty($confirmed)) $lastConfirmed = end($confirmed);
    ?>
        <table class="table table-bordered table-sm align-middle">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Due</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Payment Proof</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($currentUnpaid): ?>
                <tr>
                    <td><?= htmlspecialchars($currentUnpaid['invoice_date']) ?></td>
                    <td><?= htmlspecialchars($currentUnpaid['due_date']) ?></td>
                    <td><?= number_format($currentUnpaid['amount'],2) ?></td>
                    <td><span class="badge bg-warning text-dark">Pending</span></td>
                    <td>
                        <?= $currentUnpaid['payment_proof']
                            ? '<a href="'.htmlspecialchars($currentUnpaid['payment_proof']).'" target="_blank">View</a>'
                            : '<span class="text-muted">Not Paid</span>' ?>
                    </td>
                </tr>
                <?php endif; ?>

                <?php if ($lastConfirmed): ?>
                <tr>
                    <td><?= htmlspecialchars($lastConfirmed['invoice_date']) ?></td>
                    <td><?= htmlspecialchars($lastConfirmed['due_date']) ?></td>
                    <td><?= number_format($lastConfirmed['amount'],2) ?></td>
                    <td><span class="badge bg-success">Confirmed</span></td>
                    <td>
                        <?= $lastConfirmed['payment_proof']
                            ? '<a href="'.htmlspecialchars($lastConfirmed['payment_proof']).'" target="_blank">View</a>'
                            : '<span class="text-muted">No Proof</span>' ?>
                    </td>
                </tr>
                <?php endif; ?>

                <?php if (!$currentUnpaid && !$lastConfirmed): ?>
                    <tr><td colspan="5" class="text-muted">No invoices available.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php else: ?>
    <p class="text-muted">No recurring rent invoices yet.</p>
<?php endif; ?>

<!-- NOTICE TO VACATE (if any) -->
<?php if (!empty($claim['notice'])): 
    $n = $claim['notice'];
    $isCancelled = !empty($n['cancelled_at']);
    $sentBy = $n['sent_by'] == 'manager'
        ? '<span class="badge bg-primary">Manager</span>'
        : '<span class="badge bg-secondary">Client</span>';
?>
    <div class="alert <?= $isCancelled ? 'alert-info' : 'alert-danger' ?> mt-4">
        <b>Notice to Vacate</b>
        <br>
        <span>
            <?php if ($isCancelled): ?>
                <i class="bi bi-x-circle-fill text-success"></i>
                <span class="text-success"><b>Notice Cancelled</b> (<?= date('Y-m-d H:i', strtotime($n['cancelled_at'])) ?>)</span>
            <?php else: ?>
                <i class="bi bi-exclamation-triangle-fill text-danger"></i>
                <span class="text-danger"><b>Active Notice</b> issued on <?= date('Y-m-d H:i', strtotime($n['sent_at'])) ?></span>
            <?php endif; ?>
            <br>
            <span>Sent by: <?= $sentBy ?></span>
            <?php if (!empty($n['message'])): ?>
                <br>
                <span class="small text-muted"><?= nl2br(htmlspecialchars($n['message'])) ?></span>
            <?php endif; ?>
        </span>
    </div>
<?php endif; ?>

                        </div> <!-- /.mb-3 -->
                        <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Not Claimed Tab -->
            <div class="tab-pane fade" id="notclaimed" role="tabpanel" aria-labelledby="notclaimed-tab">
                <?php if (empty($not_claimed)): ?>
                    <div class="alert alert-info my-4">All your properties have been Reserved.</div>
                <?php else: ?>
                    <?php foreach ($not_claimed as $property_id => $data):
                        $info = $data['info'];
                        $img_src = $info['image'] ? htmlspecialchars($info['image']) : 'uploads/properties/default.jpg';
                    ?>
                    <div class="card mb-4 shadow-sm">
                        <div class="card-header bg-light d-flex align-items-center">
                            <a href="view-property.php?property_id=<?= $info['property_id'] ?>" target="_blank" class="me-3">
                                <img src="<?= $img_src ?>" alt="Property" width="46" height="46" class="rounded border" style="object-fit:cover;">
                            </a>
                            <div>
                                <h5 class="mb-0"><?= htmlspecialchars($info['property_name']) ?></h5>
                                <small class="text-muted"><?= htmlspecialchars($info['location']) ?></small>
                            </div>
                        </div>
                        <div class="card-body">
                            <p class="text-muted">This property has <b>not yet been reserved</b> by any client.</p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <a href="owner-profile.php" class="btn bg-dark text-white fw-bold mt-4">🡰 Back to dashboard</a>
    </main>
</div>

<!-- Signature Modal (kept outside loop, filled by JS on demand) -->
<div class="modal fade" id="signModal" tabindex="-1" aria-labelledby="signModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form id="signForm" method="POST" action="sign-inspection-report.php">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="signModalLabel">Sign Inspection Report</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="report_id" id="sign-report-id">
          <input type="hidden" name="signature_data" id="signature_data">
          <p>Draw your signature below:</p>
          <canvas id="signature-pad" width="350" height="120" style="border:1px solid #aaa; width:100%;"></canvas>
          <div class="mt-2">
            <button type="button" class="btn btn-sm btn-outline-danger" id="clear-signature">Clear</button>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn custom-btn">Submit Signature</button>
        </div>
      </div>
    </form>
  </div>
</div>

<?php include 'footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Keep active tab on reload
    if(window.location.hash && document.querySelector('button[data-bs-target="' + window.location.hash + '"]')) {
        let triggerEl = document.querySelector('button[data-bs-target="' + window.location.hash + '"]');
        bootstrap.Tab.getOrCreateInstance(triggerEl).show();
    }
    document.querySelectorAll('button[data-bs-toggle="tab"]').forEach(function(btn) {
        btn.addEventListener('shown.bs.tab', function (e) {
            if(history.pushState) {
                history.replaceState(null, null, e.target.getAttribute('data-bs-target'));
            } else {
                location.hash = e.target.getAttribute('data-bs-target');
            }
        });
    });

    // Signature pad modal logic
    var signModal = document.getElementById('signModal');
    var signaturePad;
    signModal.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget;
        var reportId = button.getAttribute('data-report-id');
        document.getElementById('sign-report-id').value = reportId;
        var canvas = document.getElementById('signature-pad');
        signaturePad = new SignaturePad(canvas);
        signaturePad.clear();
    });
    document.getElementById('clear-signature').onclick = function() {
        if(signaturePad) signaturePad.clear();
    };
    document.getElementById('signForm').onsubmit = function(e) {
        if (!signaturePad || signaturePad.isEmpty()) {
            alert('Please provide a signature.');
            e.preventDefault();
            return false;
        }
        document.getElementById('signature_data').value = signaturePad.toDataURL();
    };
});
</script>
<script src="navbar-close.js?v=1"></script>
<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.6/dist/signature_pad.umd.min.js"></script>
</body>
</html>
