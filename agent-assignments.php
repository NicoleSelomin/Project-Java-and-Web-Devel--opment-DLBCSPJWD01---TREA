<?php
/*
|--------------------------------------------------------------------------
| agent-assignments.php
|--------------------------------------------------------------------------
| Agent Assignments Dashboard
| - Displays all tasks assigned to a field agent.
| - Inspections, client visits, supervision, sale/rental/brokerage meetings.
| - All task data is fetched by agent ID.
| - Allows submission of reports or marking completion.
|
*/

session_start();
require 'db_connect.php';

// Ensure the user is logged in and is a field agent
if (!isset($_SESSION['staff_id']) || strtolower($_SESSION['role']) !== 'field agent') {
    header("Location: staff-login.php");
    exit();
}

$agent_id = $_SESSION['staff_id'];

// Utility: Map service slug to staff role (may be used elsewhere)
function getPersonInChargeRole($slug) {
    return match ($slug) {
        'sale_property_management', 'rental_property_management', 'brokerage' => 'Property Manager',
        'legal_assistance' => 'Legal Officer',
        'architectural_design', 'construction_supervision' => 'Plan and Supervision Manager',
        default => 'General Manager'
    };
}

// 1. Inspection Tasks assigned to this agent
$inspections = $pdo->prepare("
    SELECT r.request_id, r.property_name, r.location, r.inspection_date, r.agent_report_path,
           r.status, u.full_name AS owner_name, s.service_name, s.slug
    FROM owner_service_requests r
    JOIN owners o ON r.owner_id = o.owner_id
    JOIN users u ON o.user_id = u.user_id
    JOIN services s ON r.service_id = s.service_id
    WHERE r.assigned_agent_id = ?
    ORDER BY r.inspection_date DESC
");
$inspections->execute([$agent_id]);
$inspectionTasks = $inspections->fetchAll(PDO::FETCH_ASSOC);

// 2. Client Onsite Visit Tasks
$visits = $pdo->prepare("
    SELECT v.*, p.property_name, p.location, u.full_name AS client_name
    FROM client_onsite_visits v
    JOIN properties p ON v.property_id = p.property_id
    JOIN clients c ON v.client_id = c.client_id
    JOIN users u ON c.user_id = u.user_id
    WHERE v.assigned_agent_id = ?
    ORDER BY v.visit_date DESC, v.visit_time DESC
");
$visits->execute([$agent_id]);
$clientVisits = $visits->fetchAll(PDO::FETCH_ASSOC);

// 3. Construction Supervision Reports assigned to this agent
$supervision = $pdo->prepare("
    SELECT r.request_id, r.property_name, r.location, r.status, s.service_name, s.slug,
           csr.report_id, csr.report_date, csr.progress_summary, csr.site_images
    FROM owner_service_requests r
    JOIN services s ON r.service_id = s.service_id
    JOIN construction_supervision_reports csr ON r.request_id = csr.request_id
    WHERE csr.agent_id = ?
    ORDER BY csr.report_date DESC
");
$supervision->execute([$agent_id]);
$supervisionReports = $supervision->fetchAll(PDO::FETCH_ASSOC);

// 4. Sale Management Claim Meetings assigned to this agent
$saleMeetings = $pdo->prepare("
    SELECT cc.claim_id, cc.meeting_datetime, cc.meeting_report_path,
           u.full_name AS client_name, p.property_name, p.location
    FROM client_claims cc
    JOIN clients c ON cc.client_id = c.client_id
    JOIN users u ON c.user_id = u.user_id
    JOIN properties p ON cc.property_id = p.property_id
    WHERE cc.claim_type = 'sale' AND cc.meeting_agent_id = ?
    ORDER BY cc.meeting_datetime DESC
");
$saleMeetings->execute([$agent_id]);
$saleMgmtMeetings = $saleMeetings->fetchAll(PDO::FETCH_ASSOC);

// 5. Rental Management Meetings assigned to this agent (initial, handover, final)
$rentalMeetings = $pdo->prepare("
    SELECT cc.claim_id, p.property_name, p.location, u.full_name AS client_name,
        cc.meeting_datetime, cc.meeting_report_path, cc.meeting_agent_id,
        cc.key_handover_datetime, cc.key_handover_status, cc.key_handover_agent_id,
        cc.final_inspection_datetime, cc.final_inspection_report_path, cc.final_inspection_agent_id
    FROM client_claims cc
    JOIN properties p ON cc.property_id = p.property_id
    JOIN clients c ON cc.client_id = c.client_id
    JOIN users u ON c.user_id = u.user_id
    WHERE cc.claim_type = 'rent'
    AND cc.claim_source = 'rental_property_management'
    AND (
        cc.meeting_agent_id = :meet_id
        OR cc.key_handover_agent_id = :key_id
        OR cc.final_inspection_agent_id = :final_id
    )
    ORDER BY cc.claim_id DESC
");
$rentalMeetings->execute([
    'meet_id' => $agent_id,
    'key_id' => $agent_id,
    'final_id' => $agent_id
]);

// 6. Brokerage Claim Meetings assigned to this agent
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
?>

<!DOCTYPE html>
<html>
<head>
    <!-- Responsive layout and Bootstrap 5 -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Agent Assignments</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-4Q6Gf2aSP4eDXB8Miphtr37CMZZQ5oXLH2yaXMJ2w8e2ZtHTl7GptT4jmndRuHDT" crossorigin="anonymous">
    <link rel="stylesheet" href="styles.css?v=<?= time() ?>">
</head>
<body class="d-flex flex-column min-vh-100 bg-light">
    <?php include 'header.php'; ?>

    <div class="container-fluid flex-grow-1 d-flex flex-column">
        <div class="row flex-grow-1">

            <!-- Main Content: Agent's Assignments by Tab -->
            <main class="col-12 col-md-9 w-100">
                <div class="mb-4 p-3 border rounded shadow-sm main-title">
                    <h2>Your Assigned Tasks</h2>
                </div>

                <!-- Tab navigation for each assignment category -->
                <ul class="nav nav-tabs" id="assignmentTabs" role="tablist">
                    <li class="nav-item"><button class="nav-link custom-btn active" data-bs-toggle="tab" data-bs-target="#inspections">Inspections</button></li>
                    <li class="nav-item"><button class="nav-link custom-btn" data-bs-toggle="tab" data-bs-target="#visits">Client Visits</button></li>
                    <li class="nav-item"><button class="nav-link custom-btn" data-bs-toggle="tab" data-bs-target="#saleMeetings">Sale Mgmt. Meetings</button></li>
                    <li class="nav-item"><button class="nav-link custom-btn" data-bs-toggle="tab" data-bs-target="#rentalMeetings">Rental Mgmt. Meetings</button></li>
                    <li class="nav-item"><button class="nav-link custom-btn" data-bs-toggle="tab" data-bs-target="#supervision">Supervision</button></li>
                    <li class="nav-item"><button class="nav-link custom-btn" data-bs-toggle="tab" data-bs-target="#brokerageMeetings">Brokerage Meetings</button></li>
                </ul>

                <!-- Tab content for each assignment category -->
                <div class="tab-content mt-4">
                    <!-- Service Request Inspections -->
                    <div class="tab-pane fade show active" id="inspections">
                        <table class="table table-bordered table-hover small">
                            <thead class="table-dark">
                                <tr><th>Service</th><th>Property</th><th>Owner</th><th>Date</th><th>Report</th><th>Status</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($inspectionTasks as $task): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($task['service_name']) ?></td>
                                        <td><?= htmlspecialchars($task['property_name']) ?> (<?= $task['location'] ?>)</td>
                                        <td><?= htmlspecialchars($task['owner_name']) ?></td>
                                        <td><?= htmlspecialchars($task['inspection_date']) ?></td>
                                        <td>
                                            <?php if ($task['agent_report_path']): ?>
                                                <a href="<?= htmlspecialchars($task['agent_report_path']) ?>" target="_blank">View</a>
                                            <?php else: ?>
                                                <a href="submit-agent-report.php?type=owner_inspection&id=<?= $task['request_id'] ?>" class="btn btn-sm custom-btn">Submit</a>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($task['status']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Client Onsite Visit Assignments -->
                    <div class="tab-pane fade" id="visits">
                        <table class="table table-bordered table-hover small">
                            <thead class="table-light">
                                <tr><th>Client</th><th>Property</th><th>Location</th><th>Date & Time</th><th>Report</th><th>Status</th></tr>
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
                                                <a href="submit-agent-visit-report.php?id=<?= $v['visit_id'] ?>" class="btn btn-sm custom-btn">Submit</a>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= $v['agent_feedback'] ? 'Done' : 'Pending' ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Sale Management Claim Meetings -->
                    <div class="tab-pane fade" id="saleMeetings">
                        <table class="table table-bordered table-hover small">
                            <thead class="table-light">
                                <tr><th>Client</th><th>Property</th><th>Location</th><th>Meeting Time</th><th>Report</th><th>Status</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($saleMgmtMeetings as $m): ?>
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

                    <!-- Rental Management Claim Meetings (Initial, Handover, Final) -->
                    <div class="tab-pane fade" id="rentalMeetings">
                        <h5 class="mt-3">Rental Management Meetings</h5>
                        <table class="table table-bordered table-hover small">
                            <thead class="table-light">
                                <tr>
                                    <th>Client</th>
                                    <th>Property</th>
                                    <th>Location</th>
                                    <th>Initial Inspection</th>
                                    <th>Key Handover</th>
                                    <th>Final Inspection</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($rentalMeetings as $m): ?>
                                <?php
                                $client = htmlspecialchars($m['client_name']);
                                $property = htmlspecialchars($m['property_name']);
                                $location = htmlspecialchars($m['location']);
                                ?>
                                <tr>
                                    <td><?= $client ?></td>
                                    <td><?= $property ?></td>
                                    <td><?= $location ?></td>
                                    <!-- Initial Inspection -->
                                    <td>
                                        <?php if ($m['meeting_agent_id'] == $agent_id): ?>
                                            <div><?= $m['meeting_datetime'] ? date('Y-m-d H:i', strtotime($m['meeting_datetime'])) : 'Not Set' ?></div>
                                            <?php if ($m['meeting_report_path']): ?>
                                                <a href="<?= $m['meeting_report_path'] ?>" target="_blank">View</a>
                                            <?php else: ?>
                                                <!-- Inline form for uploading inspection report -->
                                                <form method="POST" action="upload-inspection-report.php" enctype="multipart/form-data" class="d-flex flex-column gap-1 mt-1">
                                                    <input type="hidden" name="claim_id" value="<?= $m['claim_id'] ?>">
                                                    <input type="hidden" name="inspection_type" value="initial">
                                                    <input type="file" name="report_file" accept="application/pdf" required>
                                                    <button type="submit" class="btn btn-sm custom-btn">Upload</button>
                                                </form>
                                                <span class="text-warning">Pending</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">Not Assigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <!-- Key Handover -->
                                    <td>
                                        <?php if ($m['key_handover_agent_id'] == $agent_id): ?>
                                            <div><?= $m['key_handover_datetime'] ? date('Y-m-d H:i', strtotime($m['key_handover_datetime'])) : 'Not Set' ?></div>
                                            <?php if ($m['key_handover_status'] === 'completed'): ?>
                                                <span class="text-success">Completed</span>
                                            <?php else: ?>
                                                <a href="mark-key-handover.php?id=<?= $m['claim_id'] ?>" class="btn btn-sm btn-success mt-1">Mark Completed</a>
                                                <span class="text-warning">Pending</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">Not Assigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <!-- Final Inspection -->
                                    <td>
                                        <?php if ($m['final_inspection_agent_id'] == $agent_id): ?>
                                            <div><?= $m['final_inspection_datetime'] ? date('Y-m-d H:i', strtotime($m['final_inspection_datetime'])) : 'Not Set' ?></div>
                                            <?php if ($m['final_inspection_report_path']): ?>
                                                <a href="<?= $m['final_inspection_report_path'] ?>" target="_blank">View</a>
                                                <span class="text-success">Done</span>
                                            <?php else: ?>
                                                <a href="submit-agent-report.php?type=final_inspection&id=<?= $m['claim_id'] ?>" class="btn btn-sm btn-primary mt-1">Submit</a>
                                                <span class="text-warning">Pending</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">Not Assigned</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Construction Supervision Reports -->
                    <div class="tab-pane fade" id="supervision">
                        <table class="table table-bordered table-hover small">
                            <thead class="table-light">
                                <tr><th>Service</th><th>Property</th><th>Location</th><th>Report Date</th><th>Summary</th><th>Site Images</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($supervisionReports as $s): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($s['service_name']) ?></td>
                                        <td><?= htmlspecialchars($s['property_name']) ?></td>
                                        <td><?= htmlspecialchars($s['location']) ?></td>
                                        <td><?= htmlspecialchars($s['report_date']) ?></td>
                                        <td><?= nl2br(htmlspecialchars($s['progress_summary'])) ?></td>
                                        <td>
                                            <?php
                                            // Display image links if site_images field is set
                                            if (!empty($s['site_images'])):
                                                foreach (json_decode($s['site_images'], true) as $img):
                                            ?>
                                                <a href="<?= htmlspecialchars($img) ?>" target="_blank">View</a>
                                            <?php
                                                endforeach;
                                            else:
                                                echo '<span class="text-muted">None</span>';
                                            endif;
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Brokerage Claim Meetings -->
                    <div class="tab-pane fade" id="brokerageMeetings">
                        <table class="table table-bordered table-hover small">
                            <thead class="table-light">
                                <tr><th>Client</th><th>Property</th><th>Location</th><th>Meeting Time</th><th>Report</th><th>Status</th></tr>
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

                <!-- Back to staff dashboard link -->
                <a href="staff-profile.php" class="btn custom-btn btn-sm mt-4">Back to Dashboard</a>
            </main>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js" integrity="sha384-j1CDi7MgGQ12Z7Qab0qlWQ/Qqz24Gc6BM0thvEMVjHnfYGF0rmFCozFSxQBxwHKO" crossorigin="anonymous"></script>
</body>
</html>
