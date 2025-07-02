<?php
/*
|--------------------------------------------------------------------------
| agent-assignments.php
|--------------------------------------------------------------------------
| Agent Assignments Dashboard
| - Displays all tasks assigned to a field agent.
| - Tabs: Inspections, Client Visits, Rental Meetings, Brokerage Meetings.
|--------------------------------------------------------------------------
*/

session_start();
require 'db_connect.php';

// ---------------------------------------------
// 1. Auth Check: Only Field Agents and General manager are Allowed
// ---------------------------------------------
if (!isset($_SESSION['staff_id']) || !in_array(strtolower($_SESSION['role']), ['field agent', 'general manager'])) {
    header("Location: staff-login.php");
    exit();
}
$agent_id = $_SESSION['staff_id'];
$fullName = $_SESSION['full_name'];
$role = $_SESSION['role'] ?? '';
$userId = $_SESSION['staff_id'];

// ---------------------------------------------
// 2. Fetch Assignments (4 Types) for Agent
// ---------------------------------------------

// (a) Owner Service Request Inspections
$inspections = $pdo->prepare("SELECT r.request_id, r.property_name, r.location, r.inspection_datetime, r.agent_report_path, r.status, u.full_name AS owner_name, s.service_name, s.slug
    FROM owner_service_requests r
    JOIN owners o ON r.owner_id = o.owner_id
    JOIN users u ON o.user_id = u.user_id
    JOIN services s ON r.service_id = s.service_id
    WHERE r.assigned_agent_id = ?
    ORDER BY r.inspection_datetime DESC");
$inspections->execute([$agent_id]);
$inspectionTasks = $inspections->fetchAll(PDO::FETCH_ASSOC);

// (b) Client Onsite Visit Tasks
$visits = $pdo->prepare("SELECT v.*, p.property_name, p.location, u.full_name AS client_name
    FROM client_onsite_visits v
    JOIN properties p ON v.property_id = p.property_id
    JOIN clients c ON v.client_id = c.client_id
    JOIN users u ON c.user_id = u.user_id
    WHERE v.assigned_agent_id = ?
    ORDER BY v.visit_date DESC, v.visit_time DESC");
$visits->execute([$agent_id]);
$clientVisits = $visits->fetchAll(PDO::FETCH_ASSOC);

// (c) Rental Management Meetings (initial, handover, final) for agent
$rentalMeetings = $pdo->prepare("
    SELECT cc.claim_id, p.property_name, p.location, u.full_name AS client_name,
        cc.meeting_datetime, cc.meeting_agent_id,
        cc.final_inspection_datetime, cc.final_inspection_agent_id
    FROM client_claims cc
    JOIN properties p ON cc.property_id = p.property_id
    JOIN clients c ON cc.client_id = c.client_id
    JOIN users u ON c.user_id = u.user_id
    WHERE cc.claim_type = 'rent'
    AND cc.claim_source = 'rental_property_management'
    AND (
        cc.meeting_agent_id = :meet_id
        OR cc.final_inspection_agent_id = :final_id
    )
    ORDER BY cc.claim_id DESC
");
$rentalMeetings->execute([
    'meet_id' => $agent_id,
    'final_id' => $agent_id
]);

// (d) Brokerage Claim Meetings
$brokerageMeetings = $pdo->prepare("
    SELECT cc.claim_id, cc.meeting_datetime, cc.meeting_report_path,
           u.full_name AS client_name, p.property_name, p.location
    FROM client_claims cc
    JOIN clients c ON cc.client_id = c.client_id
    JOIN users u ON c.user_id = u.user_id
    JOIN properties p ON cc.property_id = p.property_id
    WHERE cc.claim_source = 'brokerage' AND cc.meeting_agent_id = ?
    ORDER BY cc.meeting_datetime DESC
");
$brokerageMeetings->execute([$agent_id]);
$brokerageClaimMeetings = $brokerageMeetings->fetchAll(PDO::FETCH_ASSOC);

// Get upcoming booked slots for sidebar (limit to next 7)
$bookedStmt = $pdo->prepare("
    SELECT start_time, end_time, notes
    FROM agent_schedule
    WHERE agent_id = ? AND status = 'booked' AND start_time >= NOW()
    ORDER BY start_time ASC
    LIMIT 7
");
$bookedStmt->execute([$agent_id]);
$bookedSlots = $bookedStmt->fetchAll(PDO::FETCH_ASSOC);

function getInspectionReport($pdo, $claim_id, $type) {
    $stmt = $pdo->prepare("SELECT pdf_path FROM inspection_reports WHERE claim_id = ? AND inspection_type = ? ORDER BY submitted_at DESC LIMIT 1");
    $stmt->execute([$claim_id, $type]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Required Meta Tags and Bootstrap 5 -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Agent Assignments</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css?v=<?= time() ?>">
</head>
<body class="d-flex flex-column min-vh-100 bg-light">
<?php include 'header.php'; ?>

<div class="container-fluid flex-grow-1 d-flex flex-column">
    <div class="row flex-grow-1"> 

        <main class="col-12 p-4">
            <div class="mb-4 p-3 border rounded shadow-sm main-title">
                <h2>Your Assigned Tasks</h2>
            </div>
            <!-- TABS: Inspections | Client Visits | Rental Meetings | Brokerage Meetings -->
            <ul class="nav nav-tabs" id="assignmentTabs" role="tablist">
                <li class="nav-item">
                    <button class="nav-link custom-btn active"
                        data-bs-toggle="tab" data-bs-target="#inspections"
                        type="button" role="tab" aria-controls="inspections">Inspections</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link custom-btn"
                        data-bs-toggle="tab" data-bs-target="#visits"
                        type="button" role="tab" aria-controls="visits">Client Visits</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link custom-btn"
                        data-bs-toggle="tab" data-bs-target="#rentalMeetings"
                        type="button" role="tab" aria-controls="rentalMeetings">Rental Mgmt. Meetings</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link custom-btn"
                        data-bs-toggle="tab" data-bs-target="#brokerageMeetings"
                        type="button" role="tab" aria-controls="brokerageMeetings">Brokerage Meetings</button>
                </li>
            </ul>
            <div class="tab-content mt-4">
                <!-- Inspections Tab -->
                <div class="tab-pane fade show active" id="inspections" role="tabpanel">
                    <table class="table table-bordered table-hover small">
                        <thead class="table-dark">
                            <tr>
                                <th>Service</th>
                                <th>Property</th>
                                <th>Owner</th>
                                <th>Application form</th>                                
                                <th>Date</th>
                                <th>Report</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($inspectionTasks as $task): ?>
                            <tr>
                                <td><?= htmlspecialchars($task['service_name']) ?></td>
                                <td><?= htmlspecialchars($task['property_name']) ?> (<?= $task['location'] ?>)</td>
                                <td><?= htmlspecialchars($task['owner_name']) ?></td>
                                <td class="mb-3"><a href="generate-application-pdf.php?id=<?= $task['request_id'] ?>" target="_blank">PDF</a></td>
                                <td><?= htmlspecialchars($task['inspection_datetime']) ?></td>
                                <td>
                                    <?php if ($task['agent_report_path']): ?>
                                        <a href="<?= htmlspecialchars($task['agent_report_path']) ?>" target="_blank">View</a>
                                    <?php else: ?>
                                        <a href="submit-agent-report.php?type=owner_inspection&id=<?= $task['request_id'] ?>"
                                           class="btn btn-sm custom-btn">Submit</a>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($task['status']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <!-- Client Visits Tab -->
                <div class="tab-pane fade" id="visits" role="tabpanel">
                    <table class="table table-bordered table-hover small">
                        <thead class="table-dark">
                            <tr>
                                <th>Client</th>
                                <th>Property</th>
                                <th>Location</th>
                                <th>Date & Time</th>
                                <th>Report</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($clientVisits as $v): ?>
                            <tr>
                                <td><?= htmlspecialchars($v['client_name']) ?></td>
                                <td><?= htmlspecialchars($v['property_name']) ?></td>
                                <td><?= htmlspecialchars($v['location']) ?></td>
                                <td><?= date('Y-m-d H:i', strtotime($v['visit_date'] . ' ' . $v['visit_time'])) ?></td>
                                <td>
                                    <?php if ($v['visit_report_path']): ?>
                                        <a href="<?= $v['visit_report_path'] ?>" target="_blank">View</a>
                                    <?php else: ?>
                                        <a href="submit-agent-visit-report.php?id=<?= $v['visit_id'] ?>"
                                           class="btn btn-sm custom-btn">Submit</a>
                                    <?php endif; ?>
                                </td>
                                <td><?= $v['agent_feedback'] ? 'Done' : 'Pending' ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <!-- Rental Meetings Tab -->
                <div class="tab-pane fade" id="rentalMeetings" role="tabpanel">
                    <table class="table table-bordered table-hover small">
                        <thead class="table-dark">
                            <tr>
                                <th>Client</th>
                                <th>Property</th>
                                <th>Location</th>
                                <th>Initial Inspection</th>
                                <th>Initial Report</th>
                                <th>Final Inspection</th>
                                <th>Final Report</th>
                            </tr>
                        </thead>
                        <tbody>
<?php foreach ($rentalMeetings as $m): ?>
    <?php
        // Fetch initial and final report paths
        $initialReport = getInspectionReport($pdo, $m['claim_id'], 'initial');
        $finalReport   = getInspectionReport($pdo, $m['claim_id'], 'final');
    ?>
    <tr>
        <td><?= htmlspecialchars($m['client_name']) ?></td>
        <td><?= htmlspecialchars($m['property_name']) ?></td>
        <td><?= htmlspecialchars($m['location']) ?></td>
        
        <!-- Initial Inspection Date/Time -->
        <td>
            <?php
            if ($m['meeting_datetime'] && $m['meeting_agent_id'] == $agent_id) {
                echo '<span class="fw-bold">'.date('D, d M Y H:i', strtotime($m['meeting_datetime'])).'</span>';
            } elseif ($m['meeting_datetime']) {
                echo date('D, d M Y H:i', strtotime($m['meeting_datetime']));
            } else {
                echo '<span class="text-muted">Not set</span>';
            }
            ?>
        </td>
        
        <!-- Initial Report (submit/view) -->
        <td>
            <?php
            if ($m['meeting_agent_id'] == $agent_id && $m['meeting_datetime']) {
                if ($initialReport && !empty($initialReport['pdf_path'])) {
                    echo '<a href="' . htmlspecialchars($initialReport['pdf_path']) . '" target="_blank" class="btn btn-sm btn-outline-primary">View PDF</a>';
                } else {
                    echo '<a href="submit-inspection-report.php?claim_id=' . $m['claim_id'] . '&type=initial" class="btn btn-sm custom-btn">Submit</a>';
                }
            } else {
                echo '<span class="text-muted">—</span>';
            }
            ?>
        </td>

        <!-- Final Inspection Date/Time -->
        <td>
            <?php
            if ($m['final_inspection_datetime'] && $m['final_inspection_agent_id'] == $agent_id) {
                echo '<span class="fw-bold">'.date('D, d M Y H:i', strtotime($m['final_inspection_datetime'])).'</span>';
            } elseif ($m['final_inspection_datetime']) {
                echo date('D, d M Y H:i', strtotime($m['final_inspection_datetime']));
            } else {
                echo '<span class="text-muted">Not set</span>';
            }
            ?>
        </td>
        
        <!-- Final Report (submit/view) -->
        <td>
            <?php
            if ($m['final_inspection_agent_id'] == $agent_id && $m['final_inspection_datetime']) {
                if ($finalReport && !empty($finalReport['pdf_path'])) {
                    echo '<a href="' . htmlspecialchars($finalReport['pdf_path']) . '" target="_blank" class="btn btn-sm btn-outline-primary">View PDF</a>';
                } elseif ($finalReport && !empty($finalReport['pdf_path'])) {
                    echo '<a href="submit-inspection-report.php?claim_id=' . $m['claim_id'] . '&type=final" class="btn btn-sm custom-btn">Submit</a>';
                } else {
                    echo '<span class="text-muted">Wait for final</span>';
                }
            } else {
                echo '<span class="text-muted">—</span>';
            }
            ?>
        </td>
    </tr>
<?php endforeach; ?>
</tbody>

                    </table>
                </div>
                <!-- Brokerage Meetings Tab -->
                <div class="tab-pane fade" id="brokerageMeetings" role="tabpanel">
                    <table class="table table-bordered table-hover small">
                        <thead class="table-dark">
                            <tr>
                                <th>Client</th><th>Property</th><th>Location</th>
                                <th>Meeting Time</th><th>Report</th><th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($brokerageClaimMeetings as $m): ?>
                            <tr>
                                <td><?= htmlspecialchars($m['client_name']) ?></td>
                                <td><?= htmlspecialchars($m['property_name']) ?></td>
                                <td><?= htmlspecialchars($m['location']) ?></td>
                                <td><?= $m['meeting_datetime'] ? date('Y-m-d H:i', strtotime($m['meeting_datetime'])) : 'Not Set' ?></td>
                                <td>
                                    <?php if ($m['meeting_report_path']): ?>
                                        <a href="<?= $m['meeting_report_path'] ?>" target="_blank">View</a>
                                    <?php else: ?>
                                        <a href="submit-meeting-report.php?id=<?= $m['claim_id'] ?>" class="btn btn-sm custom-btn">Submit</a>
                                    <?php endif; ?>
                                </td>
                                <td><?= $m['meeting_report_path'] ? 'Done' : 'Pending' ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <a href="staff-profile.php" class="btn custom-btn btn-sm mt-4">Back to Dashboard</a>
        </main>
    </div>
</div>

<?php include 'footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // Restore last active tab if present
    var lastTab = localStorage.getItem('agent-assignments-active-tab');
    if (lastTab) {
        var trigger = document.querySelector('button[data-bs-target="' + lastTab + '"]');
        if (trigger) new bootstrap.Tab(trigger).show();
    }
    // Save tab change to localStorage
    document.querySelectorAll('#assignmentTabs button[data-bs-toggle="tab"]').forEach(function(tabBtn) {
        tabBtn.addEventListener('shown.bs.tab', function(e) {
            localStorage.setItem('agent-assignments-active-tab', e.target.getAttribute('data-bs-target'));
        });
    });
});
</script>
<!-- Navbar-close logic -->
<script src="navbar-close.js?v=1"></script>
</body>
</html>
