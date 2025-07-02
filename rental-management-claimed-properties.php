<?php
/*
|--------------------------------------------------------------------------
| rental-management-claimed-properties.php
|--------------------------------------------------------------------------
| Staff dashboard for managing claimed rental-managed properties.
| - All notice-related fields are from rent_notices only!
|--------------------------------------------------------------------------
*/ 
session_start();
require 'db_connect.php';

if (!isset($_SESSION['staff_id']) || !in_array(strtolower($_SESSION['role']), ['general manager', 'property manager'])) {
    header("Location: staff-login.php");
    exit();
}

// Load field agents
$field_agents = $pdo->query("SELECT staff_id, full_name FROM staff WHERE role = 'Field Agent'")->fetchAll(PDO::FETCH_ASSOC);

// Load all claims and related info (add contract_id for lookups)
$sql = "
SELECT 
    cc.*, 
    p.property_name, p.listing_type, p.request_id,
    cu.full_name AS client_name,
    ou.full_name AS owner_name,
    rc.contract_id, rc.locked AS contract_locked, rc.client_signature, rc.owner_signature,
    rc.contract_start_date, rc.contract_end_date, rc.next_revision_date,
    rc.notice_period_months, rc.actual_end_date, rc.termination_type, rc.termination_reason,
    cc.meeting_datetime, cc.final_inspection_datetime, rc.contract_discussion_datetime,
    claim.invoice_path AS claim_invoice, claim.payment_proof AS claim_proof, claim.payment_status AS claim_status,
    deposit.invoice_path AS deposit_invoice, deposit.payment_proof AS deposit_proof, deposit.payment_status AS deposit_status
FROM client_claims cc
JOIN clients c ON cc.client_id = c.client_id
JOIN users cu ON c.user_id = cu.user_id
JOIN properties p ON cc.property_id = p.property_id
JOIN owners o ON p.owner_id = o.owner_id
JOIN users ou ON o.user_id = ou.user_id
LEFT JOIN rental_contracts rc ON cc.claim_id = rc.claim_id
LEFT JOIN rental_claim_payments claim ON cc.claim_id = claim.claim_id AND claim.payment_type = 'claim'
LEFT JOIN rental_claim_payments deposit ON cc.claim_id = deposit.claim_id AND deposit.payment_type = 'deposit'
WHERE cc.claim_type = 'rent' AND cc.claim_source = 'rental_property_management'
";
$claims = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// Load assigned agents for each request
$assignedAgents = [];
foreach ($claims as $claim) {
    $request_id = $claim['request_id'];
    if (!isset($assignedAgents[$request_id])) {
        $stmt = $pdo->prepare("
            SELECT s.staff_id, s.full_name
            FROM owner_service_requests osr
            JOIN staff s ON osr.assigned_agent_id = s.staff_id
            WHERE osr.request_id = ?
        ");
        $stmt->execute([$request_id]);
        $agent = $stmt->fetch(PDO::FETCH_ASSOC);
        $assignedAgents[$request_id] = $agent;
    }
}

// Inspection reports for each claim
$reportStmt = $pdo->prepare("SELECT * FROM inspection_reports WHERE claim_id = ? AND inspection_type = ?");
foreach ($claims as &$claim) {
    $reportStmt->execute([$claim['claim_id'], 'initial']);
    $claim['initial_report'] = $reportStmt->fetch(PDO::FETCH_ASSOC);
    $reportStmt->execute([$claim['claim_id'], 'final']);
    $claim['final_report'] = $reportStmt->fetch(PDO::FETCH_ASSOC);
}
unset($claim);

// Warnings for each claim (track count, type)
$warningStmt = $pdo->prepare("SELECT warning_type, message, sent_at FROM rent_warnings WHERE claim_id = ? ORDER BY sent_at DESC");
foreach ($claims as &$claim) {
    $warningStmt->execute([$claim['claim_id']]);
    $claim['warnings'] = $warningStmt->fetchAll(PDO::FETCH_ASSOC);
    $claim['warning_count'] = count($claim['warnings']);
}
unset($claim);

// Notices for each contract (active and history)
$contractIds = array_values(array_filter(
    array_column($claims, 'contract_id'),
    function($v) { return is_numeric($v) && $v > 0; }
));
$noticeByContract = $allNoticesByContract = [];
if (!empty($contractIds)) {
    $qMarks = implode(',', array_fill(0, count($contractIds), '?'));
    $stmt = $pdo->prepare("SELECT * FROM rent_notices WHERE contract_id IN ($qMarks) ORDER BY sent_at DESC");
    $stmt->execute($contractIds);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $n) {
        if (!isset($noticeByContract[$n['contract_id']]) && $n['status'] === 'active') {
            $noticeByContract[$n['contract_id']] = $n;
        }
        $allNoticesByContract[$n['contract_id']][] = $n;
    }
}

// Helper: should an auto-notice be triggered?
function shouldAutoNotice($claim, $activeNotice) {
    // If exactly 3 warnings and no active notice, auto-notice is triggered.
    return ($claim['warning_count'] === 3 && empty($activeNotice));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reserved Rental Management Properties</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css?v=<?= time() ?>">
    <style>
        .slot-calendar-container { min-width: 400px; }
        @media (max-width: 700px) { .slot-calendar-container { min-width: 0; } }
    </style>
</head>
<body class="d-flex flex-column min-vh-100 bg-light">
<?php include 'header.php'; ?>

<div class="container-fluid flex-grow-1">
    <div class="row">
        <main class="col-12 p-4">
            <div class="mb-4 p-3 border rounded shadow-sm">
                <h2 class="mb-4">Rental-Managed Properties – Reserved</h2>
            </div>
            <div class="text-end mb-4"><a href="staff-profile.php" class="btn btn-dark">Back to Dashboard</a></div>
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>Client</th>
                            <th>Property</th>
                            <th>Initial Inspection</th>
                            <th>Contract Signing</th>
                            <th>Contract Period</th>
                            <th>Final Inspection</th>
                            <th>Warnings</th>
                            <th>Notice</th>
                            <th>Actions</th>                            
                        </tr>
                    </thead>
                    <tbody>
 <?php foreach ($claims as $claim): ?>
    <?php
    // Get agent names for initial/final inspection
$initialAgentName = '';
$finalAgentName = '';
if (!empty($claim['meeting_agent_id'])) {
    $stmt = $pdo->prepare("SELECT full_name FROM staff WHERE staff_id = ?");
    $stmt->execute([$claim['meeting_agent_id']]);
    $initialAgentName = $stmt->fetchColumn() ?: '';
}
if (!empty($claim['final_inspection_agent_id'])) {
    $stmt = $pdo->prepare("SELECT full_name FROM staff WHERE staff_id = ?");
    $stmt->execute([$claim['final_inspection_agent_id']]);
    $finalAgentName = $stmt->fetchColumn() ?: '';
}
    $contractId = $claim['contract_id'];
    $activeNotice = $noticeByContract[$contractId] ?? null;
    $noticeActive = $activeNotice && $activeNotice['status'] === 'active';
    $noticeHistory = $allNoticesByContract[$contractId] ?? [];

    $requestId = $claim['request_id'];
    $agentRow = $assignedAgents[$requestId] ?? null;
    $agentId = $agentRow['staff_id'] ?? null;
    $initialInspectionSet = !empty($claim['meeting_datetime']);
    $finalInspectionSet = !empty($claim['final_inspection_datetime']);
    // Final inspection only if active notice exists
    $finalInspectionAvailable = $noticeActive;
    ?>
    <tr>
                        <td><?= htmlspecialchars($claim['client_name']) ?></td>
                        <td><?= htmlspecialchars($claim['property_name']) ?> (<?= htmlspecialchars($claim['listing_type']) ?>)</td>
                        <!-- Initial Inspection -->
                        <td>
                            <?php if ($claim['claim_status'] === 'confirmed' && !$initialInspectionSet && $agentId): ?>
                                <div id="slot-daypicker-<?= $claim['claim_id'] ?>-initial"
                                    class="slot-daypicker-container"
                                    data-agent-id="<?= $agentId ?>"
                                    data-claim-id="<?= $claim['claim_id'] ?>"
                                    data-inspection-type="initial"></div>
                            <?php elseif ($initialInspectionSet): ?>
<span class="text-success"><?= htmlspecialchars($claim['meeting_datetime']) ?></span>
<?php if ($initialAgentName): ?>
    <br><span class="text-muted">Agent: <?= htmlspecialchars($initialAgentName) ?></span>
<?php endif; ?>
<?php if (!empty($claim['initial_report']['pdf_path'])): ?>
    <br>
    <a href="<?= htmlspecialchars($claim['initial_report']['pdf_path']) ?>" target="_blank">View Report</a>
<?php else: ?>
    <br><span class="text-muted">Report not uploaded</span>
<?php endif; ?>

                            <?php else: ?>
                                <span class="text-warning">Pending Payment</span>
                            <?php endif; ?>
                        </td>
                        <!-- Contract Signing -->
                        <td>
                            <?php if ($claim['deposit_status'] === 'confirmed'): ?>
                                <?php if (empty($claim['contract_discussion_datetime'])): ?>
                                    <form method="post" action="set-contract-discussion.php" class="d-flex flex-column gap-1">
                                        <input type="hidden" name="claim_id" value="<?= $claim['claim_id'] ?>">
                                        <label>
                                            Set Contract Discussion Meeting:
                                            <input type="datetime-local" name="contract_discussion_datetime" class="form-control form-control-sm" required>
                                        </label>
                                        <button type="submit" class="btn btn-sm custom-btn mt-1">Schedule Meeting</button>
                                    </form>
                                <?php else: ?>
                                    <div>
                                        <span class="fw-bold text-success">
                                            <?= htmlspecialchars($claim['contract_discussion_datetime']) ?>
                                        </span>
                                    </div>
                                    <?php if (!empty($claim['contract_id'])): 
                                        $isLocked = ($claim['contract_locked'] ?? 0);
                                        $isFullySigned = (($claim['client_signature'] ?? 0) && ($claim['owner_signature'] ?? 0));
                                    ?>
                                        <div>
                                            <span class="badge bg-<?= $isLocked ? 'secondary' : 'info' ?>">
                                                <?= $isLocked ? 'Locked' : 'Editable' ?>
                                            </span>
                                            <?php if ($isFullySigned): ?>
                                                <span class="badge bg-success ms-1">Fully Signed</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning ms-1">Pending Signature</span>
                                            <?php endif; ?>
                                        </div>
                                        <a href="manage-rental-contract.php?claim_id=<?= $claim['claim_id'] ?>">
                                            Edit Contract
                                        </a>
                                        <div>
                                        <a href="sign-lease-contract.php?claim_id=<?= $claim['claim_id'] ?>">
                                            View Contract
                                        </a>                                            
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">No contract created yet</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted">Wait for deposit</span>
                            <?php endif; ?>
                        </td>
                        <!-- Contract Period -->
                        <td>
                            <?php if (!empty($claim['contract_start_date']) && !empty($claim['contract_end_date'])): ?>
                                <?= htmlspecialchars($claim['contract_start_date']) ?> <br>to<br> <?= htmlspecialchars($claim['contract_end_date']) ?>
                            <?php else: ?>
                                <span class="text-muted">Not set</span>
                            <?php endif; ?>
                        </td>
<!-- Final Inspection -->
<td>
<?php
    $finalReportExists = !empty($claim['final_report']) && !empty($claim['final_report']['pdf_path']);
    $finalInspectionSet = !empty($claim['final_inspection_datetime']);
    $showFinalSlotPicker = false;

    // Slot picker is available if contract ending soon OR notice in history, AND no final inspection yet
    if (!$finalInspectionSet) {
        if (!empty($claim['contract_end_date'])) {
            $now = new DateTime();
            $contractEnd = new DateTime($claim['contract_end_date']);
            $interval = $now->diff($contractEnd);
            $monthsDiff = ($interval->y * 12) + $interval->m + ($interval->d > 0 ? 1 : 0);
            // Within 3 months (in past or future)
            if ($contractEnd <= $now || $monthsDiff <= 3) {
                $showFinalSlotPicker = true;
            }
        }
        // Any notice in history
        if (!$showFinalSlotPicker && !empty($noticeHistory)) {
            $showFinalSlotPicker = true;
        }
    }
?>

<?php if ($finalReportExists): ?>
    <span class="text-success"><?= htmlspecialchars($claim['final_inspection_datetime']) ?></span>
    <?php if ($finalAgentName): ?>
        <br><span class="text-muted">Agent: <?= htmlspecialchars($finalAgentName) ?></span>
    <?php endif; ?>
    <br>
    <a href="<?= htmlspecialchars($claim['final_report']['pdf_path']) ?>" target="_blank">
        View Report
    </a>
<?php elseif ($showFinalSlotPicker && $agentId): ?>
    <div id="slot-daypicker-<?= $claim['claim_id'] ?>-final"
        class="slot-daypicker-container"
        data-agent-id="<?= $agentId ?>"
        data-claim-id="<?= $claim['claim_id'] ?>"
        data-inspection-type="final"></div>
<?php elseif ($showFinalSlotPicker): ?>
    <span class="text-warning">Pending final inspection</span>
<?php else: ?>
    <span class="text-muted">Final inspection not available</span>
<?php endif; ?>
</td>

                        <!-- Reminder for oeverdue rent-->
                        <td>
            <?php if (!empty($claim['warnings'])): ?>
                <button class="btn btn-sm btn-danger" data-bs-toggle="collapse" data-bs-target="#warnings<?= $claim['claim_id'] ?>">
                    Reminders (<?= $claim['warning_count'] ?>)
                </button>
                <div class="collapse mt-1" id="warnings<?= $claim['claim_id'] ?>">
                    <ul class="list-group">
                        <?php foreach ($claim['warnings'] as $warning): ?>
                            <li class="list-group-item small">
                                <strong><?= htmlspecialchars(ucfirst($warning['warning_type'])) ?>:</strong>
                                <?= htmlspecialchars($warning['message']) ?><br>
                                <small><?= htmlspecialchars($warning['sent_at']) ?></small>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php else: ?>
                <span class="text-muted">None</span>
            <?php endif; ?>
        </td>
        <td>
            <!-- Current active notice -->
            <?php if ($activeNotice): ?>
                <div class="alert alert-info p-1 mb-1">
                    <b><?= $activeNotice['notice_type'] === 'immediate' ? 'Immediate Termination Notice' : 'Notice Period' ?></b><br>
                    <?= htmlspecialchars($activeNotice['message']) ?><br>
                    <small>
                        Sent by: <?= htmlspecialchars($activeNotice['sent_by']) ?> at <?= date('d M Y H:i', strtotime($activeNotice['sent_at'])) ?><br>
                        <?php if ($activeNotice['notice_type'] === 'immediate'): ?>
                            <span class="badge bg-danger">Immediate Termination</span>
                        <?php else: ?>
                            <span class="badge bg-warning text-dark">
                                Notice Period: <?= intval($claim['notice_period_months']) ?> months<br>
                                Vacate by: <?= date('d M Y', strtotime($activeNotice['sent_at'] . ' +' . intval($claim['notice_period_months']) . ' months')) ?>
                            </span>
                        <?php endif; ?>
                    </small>
                    <form method="post" action="cancel-rent-notice.php" class="mt-1">
                        <input type="hidden" name="notice_id" value="<?= $activeNotice['notice_id'] ?>">
                        <button class="btn btn-sm btn-outline-danger">Cancel Notice</button>
                    </form>
                </div>
            <?php else: ?>
                <span class="text-muted">No active notice</span>
                <!-- If shouldAutoNotice($claim, $activeNotice), manager should be prompted to confirm/send auto-notice -->
            <?php endif; ?>
            <!-- Show history if wanted -->
            <?php if (!empty($noticeHistory)): ?>
                <details class="mt-2">
                    <summary>Notice History</summary>
                    <ul class="small">
                    <?php foreach ($noticeHistory as $n): ?>
                        <li>
                            [<?= date('d M Y H:i', strtotime($n['sent_at'])) ?>] 
                            <?= htmlspecialchars($n['notice_type'] === 'immediate' ? 'Immediate' : 'Notice Period') ?>: 
                            <?= htmlspecialchars($n['message']) ?> 
                            <?php if ($n['status'] !== 'active'): ?>
                                <span class="badge bg-secondary ms-2"><?= htmlspecialchars(ucfirst($n['status'])) ?></span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                    </ul>
                </details>
            <?php endif; ?>
        </td>
                        <!-- Actions: manual notice, immediate notice -->
<td>
    <?php if ($noticeActive): ?>
        <!-- Active notice exists: show only cancel button for manager -->
        <form method="post" action="cancel-rent-notice.php" class="mt-1">
            <input type="hidden" name="notice_id" value="<?= $activeNotice['notice_id'] ?>">
            <button class="btn btn-sm btn-outline-danger">Cancel Notice</button>
        </form>
    <?php elseif ($claim['warning_count'] >= 3): ?>
        <!-- No active notice and warning count >= 3: show send notice buttons -->
        <?php if (shouldAutoNotice($claim, $activeNotice)): ?>
            <div class="alert alert-warning p-1 mb-1">
                <b>Notice Pending:</b> This client has reached 3 warnings.
                <form method="post" action="send-rent-notice.php" class="d-inline ms-2">
                    <input type="hidden" name="claim_id" value="<?= $claim['claim_id'] ?>">
                    <input type="hidden" name="contract_id" value="<?= $contractId ?>">
                    <input type="hidden" name="notice_type" value="period">
                    <input type="hidden" name="auto" value="1">
                    <input type="hidden" name="reason" value="Auto-notice due to repeated warnings">
                    <button class="btn btn-sm btn-outline-warning btn-light text-danger">Send Notice</button>
                </form>
            </div>
        <?php endif; ?>
        <!-- Manager manual notice (notice period) -->
        <form method="post" action="send-rent-notice.php" class="mb-2">
            <input type="hidden" name="claim_id" value="<?= $claim['claim_id'] ?>">
            <input type="hidden" name="contract_id" value="<?= $contractId ?>">
            <input type="hidden" name="notice_type" value="period">
            <div class="mb-1">
                <input type="text" name="reason" class="form-control form-control-sm" placeholder="Reason/message" required>
            </div>
            <button class="btn btn-sm btn-warning">Send Notice (with Notice Period)</button>
        </form>
        <!-- Manager immediate notice -->
        <form method="post" action="send-rent-notice.php">
            <input type="hidden" name="claim_id" value="<?= $claim['claim_id'] ?>">
            <input type="hidden" name="contract_id" value="<?= $contractId ?>">
            <input type="hidden" name="notice_type" value="immediate">
            <div class="mb-1">
                <input type="text" name="reason" class="form-control form-control-sm" placeholder="Reason/message" required>
            </div>
            <button class="btn btn-sm btn-danger">Send Immediate Termination Notice</button>
        </form>
    <?php endif; ?>
</td>
                       
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</div>

<?php include 'footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
<script>
// SLOT CALENDAR for all inspection pickers
document.querySelectorAll('.slot-daypicker-container').forEach(function(container) {
    const agentId = container.dataset.agentId;
    const claimId = container.dataset.claimId;
    const inspectionType = container.dataset.inspectionType;
    let currentDay = new Date();
    currentDay.setHours(0,0,0,0);
    const today = new Date(currentDay);
    const maxDaysAhead = 90;
    let maxDay = new Date(today);
    maxDay.setDate(maxDay.getDate() + maxDaysAhead);

    function formatDate(d) {
        const year = d.getFullYear();
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }
    function renderDayLabel() {
        return currentDay.toLocaleDateString(undefined, {weekday:'long', year:'numeric', month:'short', day:'numeric'});
    }
    function loadSlots() {
        container.innerHTML = `<div class="text-center py-4"><div class="spinner-border"></div></div>`;
        fetch(`get-agent-available-slots.php?agent_id=${agentId}&week_start=${formatDate(currentDay)}&num_days=1`)
            .then(resp => resp.json())
            .then(slots => renderDay(slots));
    }
    function renderDay(slots) {
        let html = `
        <div class="d-flex justify-content-between align-items-center mb-2">
            <button class="btn btn-outline-secondary btn-sm custom-btn" id="prev-day-${claimId}-${inspectionType}"${currentDay <= today ? ' disabled' : ''}>&lt;</button>
            <span class="fw-bold">${renderDayLabel()}</span>
            <button class="btn btn-outline-secondary btn-sm custom-btn" id="next-day-${claimId}-${inspectionType}"${currentDay >= maxDay ? ' disabled' : ''}>&gt;</button>
        </div>
        <div class="mb-2">
            <input type="date" class="form-control form-control-sm w-auto" id="date-jump-${claimId}-${inspectionType}" value="${formatDate(currentDay)}" min="${formatDate(today)}" max="${formatDate(maxDay)}">
        </div>
        <div class="d-flex flex-wrap gap-2">`;
        let any = false;
        slots.forEach(slot => {
            if (slot.available) {
                any = true;
                html += `
                    <form method="POST" action="set-rental-inspection.php">
                        <input type="hidden" name="claim_id" value="${claimId}">
                        <input type="hidden" name="inspection_type" value="${inspectionType}">
                        <input type="hidden" name="inspection_datetime" value="${slot.start}">
                        <button type="submit" class="btn btn-outline-primary btn-sm">
                            ${new Date(slot.start).toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'})} – ${new Date(slot.end).toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'})}
                        </button>
                    </form>
                `;
            }
        });
        if (!any) {
            html += `<span class="text-muted">No slots</span>`;
        }
        html += `</div>`;
        container.innerHTML = html;

        // Add listeners
        document.getElementById(`prev-day-${claimId}-${inspectionType}`).onclick = function() {
            if (currentDay > today) {
                currentDay.setDate(currentDay.getDate() - 1);
                loadSlots();
            }
        };
        document.getElementById(`next-day-${claimId}-${inspectionType}`).onclick = function() {
            if (currentDay < maxDay) {
                currentDay.setDate(currentDay.getDate() + 1);
                loadSlots();
            }
        };
        // Date jump
        document.getElementById(`date-jump-${claimId}-${inspectionType}`).onchange = function() {
            const picked = new Date(this.value);
            if (!isNaN(picked.getTime())) {
                currentDay = picked;
                currentDay.setHours(0,0,0,0);
                loadSlots();
            }
        };
    }
    loadSlots();
});
</script>
<script src="navbar-close.js?v=1"></script>
</body>
</html>
