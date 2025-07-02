<?php
// client-claimed-rental-management.php
// TREA client: rental management — warnings, notices, termination

session_start();
require 'db_connect.php';

if (!isset($_SESSION['client_id'])) {
    header("Location: user-login.php");
    exit();
}
$userId = $_SESSION['client_id'];
$fullName = $_SESSION['user_name'] ?? 'Unknown Client';

// Fetch all rental management claims for this client
$stmt = $pdo->prepare("
    SELECT cc.claim_id, cc.property_id, cc.claimed_at, p.property_name, p.location, p.image,
           rc.contract_id, rc.contract_signed_path, rc.contract_start_date, rc.contract_end_date,
           rc.locked, rc.contract_discussion_datetime, rc.client_signature, rc.owner_signature,
           rc.client_signed_at, rc.owner_signed_at, rc.notice_period_months, rc.actual_end_date,
           rc.termination_type, rc.termination_reason
      FROM client_claims cc
      JOIN properties p ON cc.property_id = p.property_id
 LEFT JOIN rental_contracts rc ON cc.claim_id = rc.claim_id
     WHERE cc.client_id = ? AND cc.claim_type = 'rent' AND cc.claim_source = 'rental_property_management'
  ORDER BY cc.claimed_at DESC
");
$stmt->execute([$userId]);
$claims = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $claims[$row['claim_id']] = [
        'info' => $row,
        'payments' => [],
        'recurring' => [],
        'latest_warning' => null,
        'latest_notice' => null,
        'inspection_reports' => [],
    ];
}

// Build a map: claim_id => contract_id and collect contractIds
$claimToContract = [];
$contractIds = [];
foreach ($claims as $claim_id => $cdata) {
    $cid = $cdata['info']['contract_id'] ?? null;
    if ($cid) {
        $claimToContract[$claim_id] = $cid;
        $contractIds[] = $cid;
    }
}

// Fetch latest notice by contract_id
// Fetch all notices per contract for display
$allNoticesByContract = [];
if ($contractIds) {
    $qMarks2 = implode(',', array_fill(0, count($contractIds), '?'));
    $noticeStmt = $pdo->prepare("SELECT * FROM rent_notices WHERE contract_id IN ($qMarks2) ORDER BY sent_at DESC");
    $noticeStmt->execute($contractIds);
    foreach ($noticeStmt->fetchAll(PDO::FETCH_ASSOC) as $n) {
        $allNoticesByContract[$n['contract_id']][] = $n;
    }
    // Assign to each claim
    foreach ($claims as $claim_id => &$cdata) {
        $cid = $cdata['info']['contract_id'] ?? null;
        if ($cid && isset($allNoticesByContract[$cid])) {
            $cdata['all_notices'] = $allNoticesByContract[$cid];
        } else {
            $cdata['all_notices'] = [];
        }
    }
    unset($cdata);
}


// Now fetch other related data in batches as before
if ($claims) {
    $claimIds = array_keys($claims);
    $qMarks = implode(',', array_fill(0, count($claimIds), '?'));

    // Recurring invoices
    $stmt = $pdo->prepare("SELECT * FROM rental_recurring_invoices WHERE claim_id IN ($qMarks) ORDER BY due_date ASC");
    $stmt->execute($claimIds);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $claims[$r['claim_id']]['recurring'][] = $r;
    }

    // Warnings (latest per claim, only if unpaid and no proof)
    $warnStmt = $pdo->prepare("SELECT * FROM rent_warnings WHERE claim_id IN ($qMarks) ORDER BY sent_at DESC");
    $warnStmt->execute($claimIds);
    foreach ($warnStmt->fetchAll(PDO::FETCH_ASSOC) as $w) {
        $recs = $claims[$w['claim_id']]['recurring'];
        $hasUnpaid = false;
        foreach ($recs as $inv) {
            if ($inv['payment_status'] !== 'confirmed' && !$inv['payment_proof']) {
                $hasUnpaid = true;
                break;
            }
        }
        if ($hasUnpaid && !$claims[$w['claim_id']]['latest_warning']) {
            $claims[$w['claim_id']]['latest_warning'] = $w;
        }
    }

    // Inspection reports
    $stmt = $pdo->prepare("SELECT * FROM inspection_reports WHERE claim_id IN ($qMarks)");
    $stmt->execute($claimIds);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $ir) {
        $claims[$ir['claim_id']]['inspection_reports'][$ir['inspection_type']] = $ir;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Your Rental-Managed Properties</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css?v=<?= time() ?>">
</head>
<body class="d-flex flex-column min-vh-100 bg-light">
<?php include 'header.php'; ?>

<div class="container-fluid flex-grow-1 py-4">
<main class="container">
    <div class="mb-4 p-3 border rounded shadow-sm main-title">
        <h2>Your Reserved Rental Management Properties</h2>
    </div>

    <div class="text-end mb-4">
        <a href="client-profile.php" class="btn bg-dark text-white fw-bold mt-4">🡰 Back to dashboard</a>
    </div>
    <?php if (empty($claims)): ?>
        <div class="alert alert-info">No reserved rental management properties yet.</div>
    <?php else: foreach ($claims as $claim_id => $data):
        $i = $data['info'];
        $inspection_reports = $data['inspection_reports'] ?? [];
        $recs = $data['recurring'];
        // Find first unpaid rent invoice for warnings
        $firstUnpaid = null;
        foreach ($recs as $inv) {
            if (!$firstUnpaid && $inv['payment_status'] !== 'confirmed') $firstUnpaid = $inv;
        }
        // Warnings: show only if latest AND for unpaid invoice w/ no proof
        $showWarning = $data['latest_warning'] && $firstUnpaid && !$firstUnpaid['payment_proof'];

        // Contract termination logic
        $terminated = false;
        $terminationType = '';
        $terminationDate = '';
        if (!empty($i['actual_end_date'])) {
            $terminated = true;
            $terminationDate = date('d M Y', strtotime($i['actual_end_date']));
            // Source of termination_type/reason is rental_contracts only
            if ($i['termination_type'] === 'immediate') {
                $terminationType = 'Immediate Termination';
            } elseif ($i['termination_type'] === 'notice') {
                $terminationType = 'Terminated via Notice Period';
            } elseif ($i['termination_type'] === 'expiry') {
                $terminationType = 'Expired (Reached contract end date)';
            } else {
                $terminationType = 'Terminated';
            }
        }
    ?>
    <div class="card mb-4 shadow-sm">
        <div class="card-header bg-light">
            <b><?= htmlspecialchars($i['property_name']) ?></b> — <?= htmlspecialchars($i['location']) ?>
        </div>
        <div class="card-body">
            <p><strong>Reserved On:</strong> <?= date('Y-m-d', strtotime($i['claimed_at'])) ?></p>
            <?php if ($i['image']): ?>
                <img src="<?= htmlspecialchars($i['image']) ?>" class="img-thumbnail mb-2" style="max-width:120px;">
            <?php endif; ?>
            <a href="view-property.php?property_id=<?= $i['property_id'] ?>" class="btn btn-outline-dark btn-sm mb-3">View Property</a>
            <hr>
            <!-- Meetings -->
            <div class="mb-2">
                <h6>Meetings & Inspections</h6>
                <ul>
                    <?php if ($i['contract_discussion_datetime']): ?>
                        <li><b>Contract Signing Meeting:</b> <?= date('d M Y H:i', strtotime($i['contract_discussion_datetime'])) ?></li>
                    <?php endif; ?>
                    <?php if ($i['locked'] && $i['contract_start_date']): ?>
                        <li><b>Lease Start:</b> <?= date('d M Y', strtotime($i['contract_start_date'])) ?></li>
                        <li><b>Lease End:</b> <?= date('d M Y', strtotime($i['contract_end_date'])) ?></li>
                    <?php endif; ?>
                </ul>
            </div>
            <hr>
            <!-- Inspection Reports -->
            <h6>Inspection Reports</h6>
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
                                Client:
                                <?php if ($r['client_signed_at']): ?>
                                    <span class="text-success">Signed</span>
                                <?php else: ?>
                                    <span class="text-warning">Pending</span>
                                    <button class="btn btn-sm custom-btn ms-2"
                                            data-bs-toggle="modal"
                                            data-bs-target="#signModal"
                                            data-report-id="<?= $r['report_id'] ?>"
                                            data-role="client"
                                    >Sign as Client</button>
                                <?php endif; ?>
                                |
                                Owner:
                                <?php if ($r['owner_signed_at']): ?>
                                    <span class="text-success">Signed</span>
                                <?php else: ?>
                                    <span class="text-warning">Pending</span>
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
            <!-- Contract -->
            <h6>Rental Contract</h6>
            <?php if ($i['locked']): ?>
                <p>
                    <b>Start:</b> <?= htmlspecialchars($i['contract_start_date']) ?>
                    — <b>End:</b> <?= htmlspecialchars($i['contract_end_date']) ?><br>
                    <?php if (!$i['client_signature']): ?>
                        <a href="sign-lease-contract.php?claim_id=<?= $claim_id ?>" class="btn custom-btn btn-sm ms-2">View/Sign Contract</a>
                    <?php elseif (!$i['owner_signature']): ?>
                        <span class="text-info ms-2">You have signed. Waiting for owner signature.</span>
                    <?php else: ?>
                        <a href="download-lease-contract.php?claim_id=<?= $claim_id ?>">Download Signed Contract</a>
                    <?php endif; ?>
                </p>
            <?php else: ?>
                <span class="text-muted">Contract will be available here once locked by manager.</span>
            <?php endif; ?>

            <hr>
            <!-- TERMINATION STATUS (from rental_contracts only) -->
            <?php if ($terminated): ?>
                <div class="alert alert-secondary mt-2">
                    <b>Contract Terminated:</b> <?= htmlspecialchars($terminationType) ?><br>
                    <?php if ($i['termination_reason']): ?>
                        <span>Reason: <?= htmlspecialchars($i['termination_reason']) ?></span><br>
                    <?php endif; ?>
                    <span class="text-muted">On <?= $terminationDate ?></span>
                </div>
            <?php endif; ?>

            <hr>
<!-- SEND TERMINATION NOTICE (form, only if active and locked) -->
<?php
// 1. Find active notice
$active_notice = null;
foreach ($data['all_notices'] as $n) {
    if (($n['status'] ?? 'active') === 'active') {
        $active_notice = $n;
        break;
    }
}
if (!$active_notice && $i['locked'] && !$terminated): ?>
    <form method="POST" action="send-rent-notice.php" class="mt-3">
        <input type="hidden" name="claim_id" value="<?= $claim_id ?>">
        <input type="hidden" name="contract_id" value="<?= $i['contract_id'] ?>">
        <input type="hidden" name="notice_type" value="period">
        <div class="mb-2">
            <label for="reason<?= $claim_id ?>">Notice Message / Reason</label>
            <textarea name="reason" id="reason<?= $claim_id ?>" class="form-control" required></textarea>
        </div>
        <div class="mb-2">
            <label>Termination Type:</label><br>
            <span class="badge bg-warning text-dark">
                Notice Period: <?= intval($i['notice_period_months']) ?> months
            </span>
        </div>
        <button class="btn btn-danger btn-sm">Send Termination Notice</button>
        <div class="form-text text-muted">
            Contact the Agency for immediate termination.
        </div>
    </form>
<?php endif; ?>

<hr>
<!-- WARNING (only latest, only if unpaid, only if no proof) -->
<?php if ($showWarning): ?>
    <div class="alert alert-danger mt-3">
        <b>Payment Reminder:</b> <?= htmlspecialchars($data['latest_warning']['message']) ?><br>
        <small class="text-muted"><?= date('d M Y H:i', strtotime($data['latest_warning']['sent_at'])) ?></small>
    </div>
<?php endif; ?>

<hr>
<!-- NOTICE (only latest) -->
<?php if ($data['latest_notice']): ?>
    <?php
        $n = $data['latest_notice'];
        $notice_period_months = isset($i['notice_period_months']) ? intval($i['notice_period_months']) : 0;
        $notice_period_days = $notice_period_months * 30;
        $immediate_termination = isset($n['immediate_termination']) ? $n['immediate_termination'] : 0;
        $sent_at = isset($n['sent_at']) ? $n['sent_at'] : null;
        $contract_notice_end_date = ($sent_at && $notice_period_days)
            ? date('d M Y', strtotime($sent_at . ' +' . $notice_period_days . ' days'))
            : 'N/A';
    ?>
    <div class="alert alert-info mt-2">
        <b>Notice:</b> <?= htmlspecialchars($n['message'] ?? '') ?><br>
        <small>
            Sent by: <b><?= htmlspecialchars($n['sender_name'] ?? $n['sent_by'] ?? '') ?></b>
            <?= $sent_at ? date('d M Y H:i', strtotime($sent_at)) : '' ?><br>
            <?php if ($immediate_termination): ?>
                <span class="badge bg-danger">Immediate Termination</span>
            <?php else: ?>
                <span class="badge bg-warning text-dark responsive-badge">
                    Notice Period: <?= $notice_period_days ?> days,<br>
                    Contract ends: <?= $contract_notice_end_date ?>
                </span>
            <?php endif; ?>
        </small>
    </div>
<?php endif; ?>

<?php if (!empty($data['all_notices'])): ?>
    <div class="alert alert-info mt-2">
        <b>Contract Notices:</b><br>
        <ul class="mb-0">
<?php foreach ($data['all_notices'] as $n): 
    $notice_period_months = isset($i['notice_period_months']) ? intval($i['notice_period_months']) : 0;
    $notice_period_days = $notice_period_months * 30;
    $immediate_termination = isset($n['immediate_termination']) ? $n['immediate_termination'] : 0;
    $sent_at = isset($n['sent_at']) ? $n['sent_at'] : null;
    $contract_notice_end_date = ($sent_at && $notice_period_days)
        ? date('d M Y', strtotime($sent_at . ' +' . $notice_period_days . ' days'))
        : 'N/A';
?>
    <li>
        <?= htmlspecialchars($n['message'] ?? '') ?><br>
        <small>
            Sent by: <b><?= htmlspecialchars($n['sender_name'] ?? $n['sent_by'] ?? '') ?></b>
            <?= $sent_at ? date('d M Y H:i', strtotime($sent_at)) : '' ?> |
            <?php if ($immediate_termination): ?>
                <span class="badge bg-danger">Immediate Termination</span>
            <?php else: ?>
                <span class="badge bg-warning text-dark responsive-badge">
                    Notice Period: <?= $notice_period_days ?> days,
                    Contract ends: <?= $contract_notice_end_date ?>
                </span>
            <?php endif; ?>
            <?php if (($n['status'] ?? '') === 'cancelled'): ?>
                <span class="badge bg-secondary">Cancelled</span>
            <?php endif; ?>
        </small>
    </li>
<?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

            <hr>
            <!-- Payments and recurring rent table (not shown for brevity) -->
            <!-- ... -->
        </div>
        <div class="bg-dark text-light text-center">End for this property</div>
    </div>
    <?php endforeach; endif; ?>
</main>
</div>
<?php include 'footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
<!-- Signature Modal (as before, not repeated for brevity) -->
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
<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.6/dist/signature_pad.umd.min.js"></script>
<script>
let signaturePad;
document.addEventListener("DOMContentLoaded", function() {
    var signModal = document.getElementById('signModal');
    signModal.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget;
        var reportId = button.getAttribute('data-report-id');
        document.getElementById('sign-report-id').value = reportId;
        if (!signaturePad) {
            var canvas = document.getElementById('signature-pad');
            signaturePad = new SignaturePad(canvas);
        } else {
            signaturePad.clear();
        }
    });
    document.getElementById('clear-signature').onclick = function() {
        signaturePad.clear();
    };
    document.getElementById('signForm').onsubmit = function(e) {
        if (signaturePad.isEmpty()) {
            alert('Please provide a signature.');
            e.preventDefault();
            return false;
        }
        document.getElementById('signature_data').value = signaturePad.toDataURL();
    };
});
</script>
<script src="navbar-close.js?v=1"></script>
</body>
</html>
