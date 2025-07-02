<?php
// -----------------------------------------------------------------------------
// manage-service-requests.php
// -----------------------------------------------------------------------------
// Staff-facing page for managing all owner service applications on the TREA platform.
// Role-based access. Provides workflow and post-approval contract management..
// -----------------------------------------------------------------------------

session_start();
require 'db_connect.php';

// ------------- Access Control -------------
if (!isset($_SESSION['staff_id']) || !in_array(strtolower($_SESSION['role']), [
    'general manager', 'property manager'
])) {
    header("Location: staff-login.php"); exit();
}

$role = strtolower($_SESSION['role'] ?? '');
if (!$role) {
    header("Location: staff-login.php"); exit();
}

$fullName = $_SESSION['full_name'] ?? 'Staff';
$userId = $_SESSION['staff_id'] ?? '';

// agent availability slot
function createAgentAvailableSlots($pdo, $agentId, $daysAhead = 7, $slotMinutes = 120, $startHour = 9, $endHour = 19) {
    $now = new DateTime();
    for ($d = 0; $d < $daysAhead; $d++) {
        $date = (clone $now)->modify("+$d days");
        for ($h = $startHour; $h <= $endHour - ($slotMinutes / 60); $h++) {
            $slotStart = (clone $date)->setTime($h, 0);
            $slotEnd = (clone $slotStart)->modify("+$slotMinutes minutes");
            // Avoid duplicate slots
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM agent_schedule WHERE agent_id = ? AND start_time = ?");
            $stmt->execute([$agentId, $slotStart->format('Y-m-d H:i:s')]);
            if (!$stmt->fetchColumn()) {
                $insert = $pdo->prepare("INSERT INTO agent_schedule (agent_id, start_time, end_time, status, event_type, notes) VALUES (?, ?, ?, 'available', 'available', 'Auto-generated slot')");
                $insert->execute([$agentId, $slotStart->format('Y-m-d H:i:s'), $slotEnd->format('Y-m-d H:i:s')]);
            }
        }
    }
}

// ------------- Handle Agent Assignment (with schedule block) -------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['agent_id'], $_POST['inspection_datetime'], $_POST['request_id'])) {
    $agentId = $_POST['agent_id'];
    $inspectionDatetime = $_POST['inspection_datetime'];
    $requestId = $_POST['request_id'];
  
// Check if the agent needs fresh available slots
$stmt = $pdo->prepare("SELECT COUNT(*) FROM agent_schedule WHERE agent_id = ? AND status = 'available' AND start_time >= NOW()");
$stmt->execute([$agentId]);
$availableSlotsCount = $stmt->fetchColumn();

if ($availableSlotsCount < 5) { // arbitrary low number triggers fresh slot creation
    createAgentAvailableSlots($pdo, $agentId);
}

    // Check for agent conflicts (blocked slots)
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM agent_schedule
    WHERE agent_id = ?
      AND status = 'booked'
      AND (
        (start_time < DATE_ADD(?, INTERVAL 2 HOUR) AND end_time > ?)
      )
");
$stmt->execute([$agentId, $inspectionDatetime, $inspectionDatetime]);
$conflicts = $stmt->fetchColumn();

    if ($conflicts) {
        $_SESSION['assignment_success'] = "This agent is not available at that time!";
    } else {
        // Update request assignment
        $assign = $pdo->prepare("UPDATE owner_service_requests SET assigned_agent_id = ?, inspection_datetime = ? WHERE request_id = ?");
        $assign->execute([$agentId, $inspectionDatetime, $requestId]);

        // Add to agent_schedule (duration 2 hours)
        $endTime = date('Y-m-d H:i:s', strtotime($inspectionDatetime . " +2 hours"));
        $insert = $pdo->prepare("INSERT INTO agent_schedule
        (agent_id, property_id, event_type, start_time, end_time, status, notes)
        VALUES (?, (SELECT property_id FROM owner_service_requests WHERE request_id = ?), 'inspection', ?, ?, 'booked', ?)");

        $insert->execute([$agentId, $requestId, $inspectionDatetime, $endTime, 'Initial inspection for request #' . $requestId]);

        createAgentAvailableSlots($pdo, $agentId);

        $_SESSION['assignment_success'] = "Agent assigned!";
    }

    header("Location: manage-service-requests.php");
    exit();
}

// ------------- Handle Owner–Client Meeting Scheduling -------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_meeting_request_id'], $_POST['owner_contract_meeting'])) {
    $requestId = $_POST['set_meeting_request_id'];
    $meetingDate = $_POST['owner_contract_meeting'];

    $setMeeting = $pdo->prepare("UPDATE owner_service_requests SET owner_contract_meeting = ? WHERE request_id = ?");
    $setMeeting->execute([$meetingDate, $requestId]);
    $_SESSION['assignment_success'] = "Owner–Client Meeting Scheduled!";

    header("Location: manage-service-requests.php");
    exit();
}

// ------------- Role-Based Service Filter -------------
$allowedServices = [
    'general manager' => [],
    'property manager' => ['rental_property_management', 'brokerage'],
];

// ------------- Fetch Requests for Display -------------
$sql = "
SELECT 
    r.request_id, r.property_name, r.location, r.submitted_at,
    u.full_name AS applicant_name, s.service_name, s.slug,
    r.reviewed_by, mgr.full_name AS manager_name,
    r.assigned_agent_id, ag.full_name AS agent_name, r.inspection_datetime, r.agent_report_path,
    r.review_pdf_path, r.status, r.final_status,
    p.payment_status AS application_payment_status, p.confirmed_at AS payment_confirmed_at,
    r.owner_contract_meeting, r.owner_contract_path, r.listed
FROM owner_service_requests r
JOIN owners o ON r.owner_id = o.owner_id
JOIN users u ON o.user_id = u.user_id
JOIN services s ON r.service_id = s.service_id
LEFT JOIN staff mgr ON r.reviewed_by = mgr.staff_id
LEFT JOIN staff ag ON r.assigned_agent_id = ag.staff_id
LEFT JOIN service_request_payments p ON r.request_id = p.request_id AND p.payment_type = 'application'
";

if (!empty($allowedServices[$role])) {
    $placeholders = implode(',', array_fill(0, count($allowedServices[$role]), '?'));
    $sql .= " WHERE s.slug IN ($placeholders)";
}
$sql .= " ORDER BY 
  CASE WHEN r.final_status IS NULL OR r.final_status = '' THEN 0 ELSE 1 END ASC,
  r.submitted_at DESC
";
try {
    if (!empty($allowedServices[$role])) {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($allowedServices[$role]);
    } else {
        $stmt = $pdo->query($sql);
    }
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $requests = [];
}

// --------- Progress Stage Helper ----------
function getProgressStage($row) {
    if (empty($row['application_payment_status'])) return "Application Received";
    if ($row['application_payment_status'] === 'pending') return "Awaiting Payment";
    if ($row['application_payment_status'] === 'confirmed' && empty($row['assigned_agent_id'])) return "Payment Confirmed";
    if ($row['assigned_agent_id'] && empty($row['agent_report_path'])) return "Inspection Assigned";
    if ($row['agent_report_path'] && empty($row['review_pdf_path'])) return "Agent Report Submitted";
    if ($row['review_pdf_path'] && empty($row['owner_contract_meeting'])) return "Manager Review";
    if ($row['owner_contract_meeting'] && empty($row['owner_contract_path'])) return "Meeting Scheduled";
    if ($row['owner_contract_path'] && !$row['listed']) return "Contract Uploaded";
    if ($row['listed']) return "Listed";
    return "Processing";
}

// --------- Split requests for UI display ----------
$processingRequests = [];
$postContractRequests = []; 
foreach ($requests as $row) {
    if (!empty($row['review_pdf_path'])) {
        $postContractRequests[] = $row;
    } else {
        $processingRequests[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Manage Service Requests</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="styles.css?v=<?= time() ?>">
</head>
<body class="d-flex flex-column min-vh-100 bg-light">
<?php include 'header.php'; ?>

<div class="container-fluid flex-grow-1 py-4">
    <div class="row">

    <?php if (!empty($_SESSION['assignment_success'])): ?>
        <div class="alert alert-success">
            <?= $_SESSION['assignment_success']; unset($_SESSION['assignment_success']); ?>
        </div>
    <?php endif; ?>

        <!-- Main Content -->
        <main class="col-12 col-md-11 ms-lg-5">
            <div class="mb-4 p-3 border rounded shadow-sm main-title">
                <h2>Manage Service Requests</h2>
            </div>

<ul class="nav nav-tabs mb-3" id="serviceTabs" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link custom-btn active" id="processing-tab" data-bs-toggle="tab" data-bs-target="#processing" type="button" role="tab" aria-controls="processing" aria-selected="true">
      Application Processing
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link custom-btn" id="contract-tab" data-bs-toggle="tab" data-bs-target="#contract" type="button" role="tab" aria-controls="contract" aria-selected="false">
      Contract & Listing
    </button>
  </li>
</ul>

<div class="tab-content">
  <div class="tab-pane fade show active" id="processing" role="tabpanel" aria-labelledby="processing-tab">

    <!-- APPLICATION PROCESSING TABLE -->
    <h4 class="mb-3">1. Application Processing</h4>
    <div class="table-responsive mb-5">
      <table class="table table-bordered table-striped align-middle small">
        <thead class="table-light">
          <tr> 
            <th>Applicant</th>
            <th>Service</th>
            <th>Application Form</th>
            <th>Submitted</th>
            <th>Manager</th>
            <th>Application Fees</th>
            <th>Assign</th>
            <th>Agent Report</th>
            <th>Review</th>
            <th>Progress</th>
          </tr>
        </thead>
        <tbody>
          <?php if (count($processingRequests)): ?>
            <?php foreach ($processingRequests as $row): ?>
              <tr>
                <td><?= htmlspecialchars($row['applicant_name']) ?></td>
                <td><?= htmlspecialchars($row['service_name']) ?></td>
                <td>
                  <a href="generate-application-pdf.php?id=<?= $row['request_id'] ?>" target="_blank">PDF</a>
                </td>
                <td><?= date('Y-m-d H:i', strtotime($row['submitted_at'])) ?></td>
                <td>
                  <?php
                  $map = [
                      'sale_property_management' => 'Property Manager',
                      'rental_property_management' => 'Property Manager',
                      'brokerage' => 'Property Manager',
                      'legal_assistance' => 'Legal Officer',
                      'construction_supervision' => 'Plan and Supervision Manager'
                  ];
                  echo $map[$row['slug']] ?? 'General Manager';
                  ?>
                </td>
                <td>
                  <?php
                  if ($row['application_payment_status'] === 'confirmed') {
                      echo '<span class="badge bg-success">Confirmed</span>';
                      if ($row['payment_confirmed_at']) echo "<br><small>".date('Y-m-d', strtotime($row['payment_confirmed_at']))."</small>";
                  } elseif ($row['application_payment_status'] === 'pending') {
                      echo '<span class="badge bg-warning text-dark">Pending</span>';
                  } elseif ($row['application_payment_status'] === 'rejected') {
                      echo '<span class="badge bg-danger">Rejected</span>';
                  } else {
                      echo '<span class="text-muted">—</span>';
                  }
                  ?>
                </td>
                <td>
                  <?php if ($row['application_payment_status'] !== 'confirmed'): ?>
                    <span class="text-muted">Wait payment</span>
                  <?php elseif (!$row['assigned_agent_id']): ?>
                    <form method="POST" class="d-flex gap-2">
                        <input type="hidden" name="request_id" value="<?= $row['request_id'] ?>">
                        <select name="agent_id" id="agent_id_<?= $row['request_id'] ?>" class="form-select form-select-sm" required onchange="showAgentSchedule(this.value, <?= $row['request_id'] ?>)">
                          <option value="">Select Agent</option>
                          <?php
                          $agents = $pdo->query("SELECT staff_id, full_name FROM staff WHERE role = 'Field Agent'")->fetchAll();
                          foreach ($agents as $agent):
                          ?>
                          <option value="<?= $agent['staff_id'] ?>"><?= htmlspecialchars($agent['full_name']) ?></option>
                          <?php endforeach; ?>
                        </select>
                        <div id="agent_schedule_<?= $row['request_id'] ?>" class="text-muted small mb-1"></div>
                        <input type="datetime-local" name="inspection_datetime" min="<?= date('Y-m-d\TH:i', strtotime('+12 hours')) ?>" required>
                        <button class="btn btn-sm custom-btn">Assign</button>
                    </form>
                  <?php else: ?>
                    <b><?= htmlspecialchars($row['agent_name']) ?></b>
                    <br><?= $row['inspection_date'] ? date("Y-m-d H:i", strtotime($row['inspection_date'])) : '' ?>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ($row['agent_report_path']): ?>
                    <a href="<?= htmlspecialchars($row['agent_report_path']) ?>" target="_blank">View</a>
                  <?php elseif ($row['assigned_agent_id']): ?>
                    <span class="text-muted">Pending</span>
                  <?php else: ?>
                    <span class="text-muted">No Agent</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ($row['review_pdf_path']): ?>
                    <a href="<?= htmlspecialchars($row['review_pdf_path']) ?>" target="_blank">View</a>
                  <?php elseif ($row['agent_report_path']): ?>
                    <a href="review-request.php?id=<?= $row['request_id'] ?>" class="btn btn-sm btn-outline-primary">Review</a>
                  <?php else: ?>
                    <span class="text-muted">Awaiting report</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php
                  $stage = getProgressStage($row);
                  $badge = match ($stage) {
                      "Application Received" => "bg-secondary",
                      "Awaiting Payment" => "bg-warning text-dark",
                      "Payment Confirmed" => "bg-info text-dark",
                      "Inspection Assigned" => "bg-primary",
                      "Agent Report Submitted" => "bg-success",
                      "Manager Review" => "bg-dark",
                      default => "bg-secondary"
                  };
                  ?>
                  <span class="badge <?= $badge ?>"><?= $stage ?></span>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="10" class="text-center text-muted">No applications currently in processing phase.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

  </div>
  <div class="tab-pane fade" id="contract" role="tabpanel" aria-labelledby="contract-tab">

    <!-- CONTRACT & LISTING TABLE -->
    <?php if (count($postContractRequests)): ?>
    <h4 class="mb-3">2. Contract & Listing</h4>
    <div class="table-responsive">
      <table class="table table-bordered table-striped align-middle small">
        <thead class="table-light">
          <tr>
            <th>Applicant</th>
            <th>Service</th>
            <th>Meeting</th>
            <th>Owner Contract</th>
            <th>Listing</th>
            <th>Progress</th>
            <th>Final Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($postContractRequests as $row): ?>
            <tr>
              <td><?= htmlspecialchars($row['applicant_name']) ?></td>
              <td><?= htmlspecialchars($row['service_name']) ?></td>
              <td>
                <?php if ($row['owner_contract_meeting']): ?>
                  <?= date('Y-m-d H:i', strtotime($row['owner_contract_meeting'])) ?>
                <?php elseif ($row['review_pdf_path'] && empty($row['owner_contract_meeting']) && empty($row['owner_contract_path'])): ?>
                  <form method="post" action="manage-service-requests.php" class="d-flex gap-2">
                    <input type="hidden" name="set_meeting_request_id" value="<?= $row['request_id'] ?>">
                    <input type="datetime-local" name="owner_contract_meeting" min="<?= date('Y-m-d\TH:i', strtotime('+12 hours')) ?>" required>
                    <button type="submit" class="btn btn-sm custom-btn">Set Meeting</button>
                  </form>
                <?php else: ?>
                  <span class="text-muted">Pending</span>
                <?php endif; ?>
              </td>
              <td>
                <?php
                // Fetch the contract details (already done above, so only fetch if not available)
                $contract_stmt = $pdo->prepare("SELECT * FROM owner_service_requests WHERE request_id = ?");
                $contract_stmt->execute([$row['request_id']]);
                $contract = $contract_stmt->fetch();
                if (!$row['owner_contract_meeting']) {
                  echo '<span class="text-muted">Wait meeting</span>';
                }
                // If meeting set but no contract, allow GM to create/edit contract
                elseif (!$contract) {
                  if ($role === 'general manager') {
                    echo '<a href="edit-contract.php?request_id=' . $row['request_id'] . '&type=' . htmlspecialchars($row['slug']) . '" class="btn btn-sm btn-primary">Draft Contract</a>';
                  } else {
                    echo '<span class="text-muted">Drafting…</span>';
                  }
                }
                // If contract exists and not locked, allow GM or Property Manager to edit
                elseif (empty($contract['contract_locked'])) {
                  if ($role === 'general manager' || $role === 'property manager') {
                    echo '<a href="edit-contract.php?request_id=' . $row['request_id'] . '" class="btn btn-sm btn-warning">Edit Contract</a>';
                  } else {
                    echo '<span class="text-muted">Drafting…</span>';
                  }
                }
                // If contract locked, handle signatures/views
                else {
                  // Signature logic (GM or Owner can sign)
                  $user_sign_column = '';
                  if ($role === 'owner') $user_sign_column = 'owner_signature';
                  if ($role === 'general manager') $user_sign_column = 'agency_signature';
                  $user_has_signed = $user_sign_column && !empty($contract[$user_sign_column]);
                  $all_signed = !empty($contract['owner_signature']) && !empty($contract['agency_signature']);
                  // Show relevant buttons/links
                  if (!$user_has_signed && $user_sign_column) {
                    echo '<a href="sign-contract.php?contract_id=' . $row['request_id'] . '" class="btn btn-sm btn-outline-primary">View & Sign</a><br>';
                  } else {
                    echo '<a href="sign-contract.php?contract_id=' . $row['request_id'] . '" class="btn btn-sm btn-outline-secondary">View Contract</a><br>';
                  }
                  if ($all_signed) {
                    echo "<br><a href='download-contract.php?contract_id={$row['request_id']}' class='btn btn-sm custom-btn mt-1'>Download PDF</a>";
                  } else {
                    echo '<br><span class="text-muted small">Awaiting all signatures…</span>';
                  }
                }
                ?>
                </td>
                <td>
                <?php
                if ($row['final_status'] === 'approved') {
                    if ($row['owner_contract_path'] && !$row['listed']) {
                        echo '<a href="list-property.php?request_id=' . $row['request_id'] . '" class="btn btn-sm btn-success">List Property</a>';
                    } elseif ($row['listed']) {
                        echo '<span class="badge bg-success">Listed</span>';
                    } else {
                        echo '<span class="text-muted">Contract needed</span>';
                    }
                } elseif ($row['final_status'] === 'rejected') {
                    echo '<span class="text-danger">N/A</span>';
                } else {
                    echo '<span class="text-muted">Pending</span>';
                }
                ?>
              </td>
              <td>
                <span class="badge bg-info text-dark"><?= getProgressStage($row) ?></span>
              </td>
              <td>
                <?php
                if ($row['final_status'] === 'approved') echo '<span class="badge bg-success">Approved</span>';
                elseif ($row['final_status'] === 'rejected') echo '<span class="badge bg-danger">Rejected</span>';
                else echo '<span class="badge bg-secondary">Pending</span>';
                ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

  </div>
</div>
<a href="staff-profile.php" class="btn bg-dark text-white fw-bold my-4">🡰 Back to dashboard</a>
</main>
</div>
</div>
<?php include 'footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>

<!-- AJAX script to fetch agent schedule -->
<script>
function showAgentSchedule(agentId, reqId) {
    if (!agentId) {
        document.getElementById('agent_schedule_' + reqId).innerText = "";
        return;
    }
    fetch('get-agent-schedule.php?agent_id=' + agentId)
        .then(response => response.json())
        .then(data => {
            let html = '<b>Upcoming:</b><ul>';
            if (data.length === 0) {
                html += '<li>Available</li>';
            } else {
                data.forEach(item => {
                    html += `<li>${item.event_type} on ${item.start_time.replace('T', ' ')} – ${item.end_time.replace('T', ' ')}</li>`;
                });
            }
            html += '</ul>';
            document.getElementById('agent_schedule_' + reqId).innerHTML = html;
        });
}
</script>

<!-- tabs to switch only on click -->
<script>
// On tab show, update the URL hash
document.querySelectorAll('a[data-bs-toggle="tab"]').forEach(tab => {
  tab.addEventListener('shown.bs.tab', function (e) {
    history.replaceState(null, null, e.target.getAttribute('href'));
  });
});

// On page load, activate the tab in hash if present
document.addEventListener("DOMContentLoaded", function() {
  var hash = window.location.hash;
  if (hash) {
    var tabTrigger = document.querySelector('[href="' + hash + '"]');
    if (tabTrigger) {
      var tab = new bootstrap.Tab(tabTrigger);
      tab.show();
    }
  }
});
</script>

<!-- Script to close main navbar on small screen-->
<script src="navbar-close.js?v=1"></script>

</body>
</html>
