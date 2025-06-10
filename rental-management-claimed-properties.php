<?php
/*
|--------------------------------------------------------------------------
| rental-management-claimed-properties.php
|--------------------------------------------------------------------------
| Staff dashboard for managing claimed rental-managed properties.
| - Only accessible to general manager and property manager roles.
| - Shows complete claim lifecycle: inspections, payments, contracts,
|   renewals, warnings, and actions.
| - Uniform Bootstrap 5.3.6 responsive UI.
|--------------------------------------------------------------------------
*/

// ---------------------- Initialization & Permissions ----------------------
session_start();
require 'db_connect.php'; 

// Only allow access to authorized staff roles
if (!isset($_SESSION['staff_id']) || !in_array(strtolower($_SESSION['role']), ['general manager', 'property manager'])) {
    header("Location: staff-login.php");
    exit();
}

// Store staff ID for later use
$staff_id = $_SESSION['staff_id'];

// ------------------ Load field agents for dropdown assignments -------------
$field_stmt = $pdo->query("SELECT staff_id, full_name FROM staff WHERE role = 'Field Agent'");
$field_agents = $field_stmt->fetchAll(PDO::FETCH_ASSOC);

// --------------- Query all relevant claim & contract details ---------------
$sql = "
SELECT 
    cc.claim_id, cc.client_id, cc.property_id, cc.visit_id,
    cc.meeting_datetime, cc.meeting_agent_id, cc.meeting_report_path,
    cc.final_status, cc.claim_status,
    cc.final_inspection_datetime, cc.final_inspection_agent_id, cc.final_inspection_report_path,

    fu.full_name AS final_agent_name,
    p.property_name, p.listing_type,
    cu.full_name AS client_name,
    ou.full_name AS owner_name,
    v.visit_date,
    au.full_name AS agent_name,

    rc.contract_discussion_datetime,
    rc.contract_signed_path,
    rc.contract_start_date,
    rc.contract_end_date,
    rc.actual_end_date,
    rc.contract_end_manual,
    rc.renewal_requested_datetime,
    rc.renewal_meeting_datetime,
    rc.renewed_contract_path,
    rc.renewed_contract_end_date,
    rc.key_handover_done,

    claim.invoice_path AS claim_invoice,
    claim.payment_proof AS claim_proof,
    claim.payment_status AS claim_status,

    deposit.invoice_path AS deposit_invoice,
    deposit.payment_proof AS deposit_proof,
    deposit.payment_status AS deposit_status,

    rent.invoice_path AS rent_invoice,
    rent.payment_proof AS rent_proof,
    rent.payment_status AS rent_status

FROM client_claims cc
JOIN clients c ON cc.client_id = c.client_id
JOIN users cu ON c.user_id = cu.user_id
JOIN properties p ON cc.property_id = p.property_id
JOIN owners o ON p.owner_id = o.owner_id
JOIN users ou ON o.user_id = ou.user_id
LEFT JOIN client_onsite_visits v ON cc.visit_id = v.visit_id
LEFT JOIN users au ON cc.meeting_agent_id = au.user_id
LEFT JOIN users fu ON cc.final_inspection_agent_id = fu.user_id
LEFT JOIN rental_contracts rc ON cc.claim_id = rc.claim_id

LEFT JOIN (SELECT * FROM rental_claim_payments WHERE payment_type = 'claim') AS claim ON cc.claim_id = claim.claim_id
LEFT JOIN (SELECT * FROM rental_claim_payments WHERE payment_type = 'deposit') AS deposit ON cc.claim_id = deposit.claim_id
LEFT JOIN (SELECT * FROM rental_claim_payments WHERE payment_type = 'rent') AS rent ON cc.claim_id = rent.claim_id

WHERE cc.claim_type = 'rent' 
  AND cc.claim_source = 'rental_property_management'
";

$stmt = $pdo->query($sql);
$claims = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ------------------- Load all warnings per claim for fast lookup -----------
$warningStmt = $pdo->prepare("SELECT claim_id, warning_type, message, sent_at FROM rent_warnings WHERE claim_id = ? ORDER BY sent_at DESC");
foreach ($claims as &$claim) {
    $warningStmt->execute([$claim['claim_id']]);
    $claim['warnings'] = $warningStmt->fetchAll(PDO::FETCH_ASSOC);
}
unset($claim);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Head and responsive meta -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Rental Management Claimed Properties</title>
    <!-- Bootstrap 5.3.6 and custom CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css?v=<?= time() ?>">
</head>
<body class="d-flex flex-column min-vh-100 bg-light">
<?php include 'header.php'; ?>

<div class="container-fluid flex-grow-1">
    <div class="row">
        <div class="col-12">
            <main class="p-3">
                <div class="mb-4 p-3 border rounded shadow-sm main-title">
                    <h2 class="mb-4">Rental-Managed Properties – Claimed</h2>
                </div>
                <!-- Table: One row per rental-managed claim -->
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle small">
                        <thead class="table-light">
                            <tr>
                                <th>Client</th>
                                <th>Property</th>
                                <th>Visit Date</th>
                                <th>Initial Inspection</th>
                                <th>Report</th>
                                <th>Deposit</th>
                                <th>Contract Signing</th>
                                <th>Contract</th>
                                <th>Renewal</th>
                                <th>Warnings</th>
                                <th>Actions</th>
                                <th>Final Inspection</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($claims as $row): ?>
                            <tr>
                                <!-- Client -->
                                <td><?= htmlspecialchars($row['client_name']) ?></td>
                                <td><?= htmlspecialchars($row['property_name']) ?> (<?= $row['listing_type'] ?>)</td>
                                <td><?= $row['visit_date'] ?? '—' ?></td>

                                <!-- Initial Inspection assignment and display -->
                                <td>
                                    <?php if (
                                        !$row['meeting_datetime'] && $row['claim_status'] === 'confirmed' 
                                        && $row['claim_invoice'] && $row['claim_proof']
                                    ): ?>
                                        <!-- Assign agent and schedule -->
                                        <form method="POST" action="assign-agent.php" class="d-flex gap-2 align-items-center">
                                            <input type="hidden" name="claim_id" value="<?= $row['claim_id'] ?>">
                                            <input type="hidden" name="type" value="rental_check">
                                            <input type="datetime-local" name="meeting_datetime" class="form-control form-control-sm" min="<?= date('Y-m-d\TH:i', strtotime('+2 hours')) ?>" required>
                                            <select name="agent_id" class="form-select form-select-sm" required>
                                                <option value="">Select Agent</option>
                                                <?php foreach ($field_agents as $agent): ?>
                                                    <option value="<?= $agent['staff_id'] ?>"><?= htmlspecialchars($agent['full_name']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit" class="btn btn-sm custom-btn">Set Meeting</button>
                                        </form>
                                    <?php else: ?>
                                        <?= $row['meeting_datetime'] ? date('Y-m-d H:i', strtotime($row['meeting_datetime'])) : 'Pending' ?><br>
                                        Agent: <?= $row['agent_name'] ?? '—' ?>
                                    <?php endif; ?>
                                </td>

                                <!-- Initial Inspection report -->
                                <td>
                                    <?= $row['meeting_report_path'] && file_exists($row['meeting_report_path']) 
                                        ? '<a href="'.$row['meeting_report_path'].'" target="_blank">View</a>' 
                                        : '<span class="text-muted">Pending</span>' ?>
                                </td>

                                <!-- Deposit payment status -->
                                <td>
                                    <?php if ($row['deposit_invoice'] && $row['deposit_proof'] && $row['deposit_status'] === 'confirmed'): ?>
                                        <span class="text-success">Confirmed</span>
                                    <?php elseif ($row['deposit_invoice'] || $row['deposit_proof']): ?>
                                        <span class="text-warning">Pending</span>
                                    <?php else: ?>
                                        <span class="text-muted">Not available</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Contract signing meeting -->
                                <td>
                                    <?php if (
                                        !$row['contract_discussion_datetime'] && $row['meeting_report_path'] && 
                                        $row['deposit_invoice'] && $row['deposit_proof'] && $row['deposit_status'] === 'confirmed'
                                    ): ?>
                                        <form method="POST" action="upload-rental-contract.php">
                                            <input type="hidden" name="claim_id" value="<?= $row['claim_id'] ?>">
                                            <input type="datetime-local" name="contract_discussion_datetime" min="<?= date('Y-m-d H:i', strtotime('+2 hours')) ?>" required>
                                            <button class="btn btn-sm btn-outline-primary">Save</button>
                                        </form>
                                    <?php elseif ($row['contract_discussion_datetime']): ?>
                                        <?= date('Y-m-d H:i', strtotime($row['contract_discussion_datetime'])) ?>
                                    <?php endif; ?>
                                </td>

                                <!-- Contract upload/display -->
                                <td>
                                    <?php if (!$row['contract_signed_path']): ?>
                                        <?php if ($row['contract_discussion_datetime']): ?>
                                            <form method="POST" action="upload-rental-contract.php" enctype="multipart/form-data" class="d-grid gap-1">
                                                <input type="hidden" name="claim_id" value="<?= $row['claim_id'] ?>">
                                                <label class="form-label small mb-0">Start Date</label>
                                                <input type="date" name="contract_start_date" class="form-control form-control-sm" required>
                                                <label class="form-label small mb-0">End Date</label>
                                                <input type="date" name="contract_end_date" class="form-control form-control-sm" required>
                                                <label class="form-label small mb-0">Upload Contract (PDF)</label>
                                                <input type="file" name="claim_contract_file" accept="application/pdf" class="form-control form-control-sm" required>
                                                <button class="btn btn-sm btn-primary mt-1">Submit</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-muted">Awaiting contract discussions and signing</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <a href="<?= $row['contract_signed_path'] ?>" target="_blank">View Contract</a><br>
                                        <?= $row['contract_start_date'] ?> to <?= $row['contract_end_date'] ?>
                                        <?php if (!empty($row['actual_end_date'])): ?>
                                            <div class="text-danger">Ended early: <?= $row['actual_end_date'] ?></div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>

                                <!-- Renewal logic (requests, meetings, new contract) -->
                                <td>
                                    <?php if ($row['renewal_requested_datetime']): ?>
                                        Request: <?= $row['renewal_requested_datetime'] ?><br>
                                        <?php if (!$row['renewal_meeting_datetime'] && !$row['renewal_status']): ?>
                                            <form method="POST" action="handle-renewal-decision.php" class="d-flex gap-1 mt-2">
                                                <input type="hidden" name="claim_id" value="<?= $row['claim_id'] ?>">
                                                <button name="decision" value="accepted" class="btn btn-sm btn-success">Accept</button>
                                                <button name="decision" value="rejected" class="btn btn-sm btn-danger">Reject</button>
                                            </form>
                                        <?php elseif ($row['renewal_status'] === 'accepted'): ?>
                                            <span class="text-success">Accepted</span><br>
                                            <?php if (!$row['renewal_meeting_datetime']): ?>
                                                <a href="schedule-renewal-meeting.php?claim_id=<?= $row['claim_id'] ?>" class="btn btn-sm btn-primary mt-1">Set Meeting</a>
                                            <?php else: ?>
                                                Meeting: <?= $row['renewal_meeting_datetime'] ?><br>
                                            <?php endif; ?>
                                        <?php elseif ($row['renewal_status'] === 'rejected'): ?>
                                            <span class="text-danger">Rejected</span>
                                        <?php endif; ?>
                                        <?php if ($row['renewed_contract_path']): ?>
                                            <a href="<?= $row['renewed_contract_path'] ?>" target="_blank">New Contract</a><br>
                                            End: <?= $row['renewed_contract_end_date'] ?>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>

                                <!-- Warnings (collapsible) -->
                                <td>
                                    <?php if (!empty($row['warnings'])): ?>
                                        <button class="btn btn-sm btn-danger" data-bs-toggle="collapse" data-bs-target="#warnings<?= $row['claim_id'] ?>">Warnings (<?= count($row['warnings']) ?>)</button>
                                        <div class="collapse mt-1" id="warnings<?= $row['claim_id'] ?>">
                                            <ul class="list-group">
                                                <?php foreach ($row['warnings'] as $w): ?>
                                                    <li class="list-group-item small">
                                                        <strong><?= ucfirst($w['warning_type']) ?>:</strong>
                                                        <?= htmlspecialchars($w['message']) ?><br>
                                                        <small><?= $w['sent_at'] ?></small>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">None</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Manual actions (end contract) -->
                                <td>
                                    <?php if ($row['contract_signed_path'] && !$row['actual_end_date']): ?>
                                        <form method="POST" action="end-contract.php">
                                            <input type="hidden" name="claim_id" value="<?= $row['claim_id'] ?>">
                                            <button class="btn btn-sm btn-outline-danger">End Contract</button>
                                        </form>
                                    <?php endif; ?>
                                </td>

                                <!-- Final inspection scheduling -->
                                <td>
                                    <?php
                                    $contractEnded = (
                                        (!empty($row['contract_end_date']) && $row['contract_end_date'] <= date('Y-m-d')) ||
                                        (!empty($row['renewed_contract_end_date']) && $row['renewed_contract_end_date'] <= date('Y-m-d'))
                                    );
                                    ?>
                                    <?php if (!$row['final_inspection_datetime'] && $contractEnded): ?>
                                        <form method="GET" action="assign-agent.php">
                                            <input type="hidden" name="claim_id" value="<?= $row['claim_id'] ?>">
                                            <input type="hidden" name="type" value="final_inspection">
                                            <button class="btn btn-sm btn-warning">Set Final Inspection</button>
                                        </form>
                                    <?php elseif ($row['final_inspection_datetime']): ?>
                                        <?= date('Y-m-d H:i', strtotime($row['final_inspection_datetime'])) ?><br>
                                        Agent: <?= $row['final_agent_name'] ?? '—' ?>
                                    <?php else: ?>
                                        <span class="text-muted">Not due</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <a href="staff-profile.php" class="btn btn-secondary mt-4">Back to dashboard</a>
            </main>
        </div>
    </div>
</div>
<?php include 'footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
