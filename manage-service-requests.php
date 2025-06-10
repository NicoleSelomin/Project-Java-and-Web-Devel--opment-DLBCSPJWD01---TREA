<?php
// -----------------------------------------------------------------------------
// manage-service-requests.php
// -----------------------------------------------------------------------------
// Staff-facing page for managing all owner service applications on the TREA platform.
// Role-based access. Provides workflow and post-approval contract management..
// -----------------------------------------------------------------------------

session_start();
require 'db_connect.php';

// ---------------------------------------------
// Access Control: Only specific staff roles allowed
// ---------------------------------------------
if (!isset($_SESSION['staff_id']) || !in_array(strtolower($_SESSION['role']), [
    'general manager', 'property manager', 'plan and supervision manager', 'legal officer'
])) {
    header("Location: staff-login.php");
    exit();
}

$role = strtolower($_SESSION['role']);
$fullName = $_SESSION['full_name'] ?? 'Staff';
$userId = $_SESSION['staff_id'] ?? '';
$profilePicture = $_SESSION['profile_picture_path'] ?? '';

// ---------------------------------------------
// Role-based Service Filtering
// Each role only sees their relevant service types
// ---------------------------------------------
$allowedServices = [
    'general manager' => [], // GM sees all
    'property manager' => ['sale_property_management', 'rental_property_management', 'brokerage'],
    'plan and supervision manager' => ['construction_supervision'],
    'legal officer' => ['legal_assistance']
];

// ---------------------------------------------
// Build query with JOINs for all required service details
// ---------------------------------------------
$sql = "
SELECT 
    a.request_id, a.property_name, a.location, a.submitted_at, 
    u.full_name AS applicant_name, s.service_name, s.slug,
    a.assigned_agent_id, a.inspection_date, a.agent_report_path, 
    a.review_pdf_path, a.status, a.final_status,
    sa.full_name AS assigned_agent, a.listed,
    pay.payment_status AS application_payment_status,
    pay.confirmed_at AS application_payment_confirmed_at,
    a.owner_contract_path, a.owner_contract_meeting,
    cs.building_type AS cs_building_type, cs.current_stage AS cs_current_stage, cs.supervision_needs AS cs_needs,
    ar.building_type AS ar_building_type, ar.land_size AS ar_land_size, ar.design_preferences AS ar_design,
    br.brokerage_purpose, br.property_type AS br_type, br.estimated_price AS br_price, br.urgent_sale,
    sa2.estimated_price AS sa_price,
    le.request_type AS le_type, le.subject_property AS le_property, le.issue_description AS le_issue
FROM owner_service_requests a
JOIN owners o ON a.owner_id = o.owner_id
JOIN users u ON o.user_id = u.user_id
JOIN services s ON a.service_id = s.service_id
LEFT JOIN staff sa ON a.assigned_agent_id = sa.staff_id
LEFT JOIN service_request_payments pay ON a.request_id = pay.request_id AND pay.payment_type = 'application'
LEFT JOIN construction_supervision_details cs ON a.request_id = cs.request_id
LEFT JOIN architecture_plan_details ar ON a.request_id = ar.request_id
LEFT JOIN brokerage_details br ON a.request_id = br.request_id
LEFT JOIN sale_property_management_details sa2 ON a.request_id = sa2.request_id
LEFT JOIN legal_assistance_details le ON a.request_id = le.request_id
";

// ---------------------------------------------
// Add filtering for role-based services
// ---------------------------------------------
if (!empty($allowedServices[$role])) {
    $placeholders = implode(',', array_fill(0, count($allowedServices[$role]), '?'));
    $sql .= " WHERE s.slug IN ($placeholders)";
}
$sql .= "
ORDER BY 
  CASE 
    WHEN a.final_status IS NULL OR a.final_status = '' THEN 0 
    ELSE 1 
  END ASC,
  a.submitted_at DESC
";

// ---------------------------------------------
// Fetch the requests based on role
// ---------------------------------------------
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
    error_log("Query error: " . $e->getMessage());
}

// ---------------------------------------------
// Prepare profile picture (ensure default if not found)
// ---------------------------------------------
$profilePicturePath = (!empty($profilePicture) && file_exists($profilePicture))
    ? $profilePicture
    : 'default.png';

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

<!-- -------------------- Flash Messages (Bootstrap alerts) --------------------- -->
<?php if (isset($_SESSION['contract_uploaded'])): ?>
  <div class="alert alert-success alert-dismissible fade show mt-3" role="alert">
    Owner contract uploaded successfully.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
  <?php unset($_SESSION['contract_uploaded']); ?>
<?php endif; ?>
<?php if (isset($_SESSION['meeting_saved'])): ?>
  <div class="alert alert-info alert-dismissible fade show mt-3" role="alert">
    Owner contract meeting scheduled.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
  <?php unset($_SESSION['meeting_saved']); ?>
<?php endif; ?>
<?php if (isset($_SESSION['error'])): ?>
  <div class="alert alert-danger alert-dismissible fade show mt-3" role="alert">
    <?= htmlspecialchars($_SESSION['error']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
  <?php unset($_SESSION['error']); ?>
<?php endif; ?>

<div class="container-fluid">
  <div class="row">

    <!-- -------------------- Responsive Sidebar ---------------------- -->
    <aside class="col-12 col-md-3 mb-3">
      <button class="btn btn-sm d-md-none mb-3 custom-btn" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarCollapse">
        Open Menu
      </button>
      <div class="collapse d-md-block" id="sidebarCollapse">
        <div class="sidebar text-center">
          <div class="profile-summary text-center">
            <img src="<?= htmlspecialchars($profilePicturePath) ?>" alt="Profile Picture" class="mb-3">
            <p><strong><?= htmlspecialchars($fullName) ?></strong></p>
            <p>ID: <?= htmlspecialchars($userId) ?></p>
            <a href="notifications.php" class="btn mt-3 bg-light w-100">View Notifications</a>
            <a href="edit-staff-profile.php" class="btn mt-3 bg-light w-100">Edit Profile</a>
            <a href="staff-logout.php" class="btn text-danger mt-3 d-block bg-light w-100">Logout</a>
          </div>
          <div>
            <h5 class="mt-5">Calendar</h5>
            <iframe src="https://calendar.google.com/calendar/embed?mode=MONTH" frameborder="0" scrolling="no"></iframe>
          </div>
        </div>
      </div>
    </aside>

    <!-- -------------------- Main Content --------------------------- -->
    <main class="col-12 col-md-9">
      <div class="mb-4 p-3 border rounded shadow-sm main-title">
        <h2>Manage Service Requests</h2>
      </div>

      <!-- ----------- Tabbed Interface: Workflow & Outcome -------------- -->
      <ul class="nav nav-tabs" id="serviceTabs" role="tablist">
        <li class="nav-item">
          <button class="custom-btn nav-link active" id="progress-tab" data-bs-toggle="tab" data-bs-target="#progress" type="button">Application Progress</button>
        </li>
        <li class="nav-item">
          <button class="custom-btn nav-link" id="outcome-tab" data-bs-toggle="tab" data-bs-target="#outcome" type="button">Service Outcome &amp; Owner Contract</button>
        </li>
      </ul>
      <div class="tab-content mt-3">

        <!-- ----------- Tab 1: Application Progress/Workflow ------------ -->
        <div class="tab-pane fade show active" id="progress">
          <div class="table-responsive">
            <table class="table table-bordered">
              <thead class="table-light">
                <tr>
                  <th>Applicant</th>
                  <th>Service</th>
                  <th>Application Form</th>
                  <th>Submitted</th>
                  <th>Details</th>
                  <th>Manager</th>
                  <th>Application Fees</th>
                  <th>Assign</th>
                  <th>Agent Report</th>
                  <th>Review</th>
                  <th>Final Status</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($requests as $row): ?>
                  <tr>
                    <td><?= htmlspecialchars($row['applicant_name']) ?></td>
                    <td><?= htmlspecialchars($row['service_name']) ?></td>
                    <td>
                      <a href="generate-application-pdf.php?id=<?= $row['request_id'] ?>" target="_blank">View PDF</a>
                    </td>
                    <td><?= date('Y-m-d', strtotime($row['submitted_at'])) ?></td>
                    <td>
                      <?php
                      // Render service-specific short summary details
                      switch ($row['slug']) {
                        case 'construction_supervision':
                          echo "<strong>Type:</strong> " . htmlspecialchars($row['cs_building_type']) . "<br>";
                          echo "<strong>Stage:</strong> " . htmlspecialchars($row['cs_current_stage']) . "<br>";
                          echo "<strong>Needs:</strong> " . nl2br(htmlspecialchars($row['cs_needs']));
                          break;
                        case 'architecture_plan_drawing':
                          echo "<strong>Type:</strong> " . htmlspecialchars($row['ar_building_type']) . "<br>";
                          echo "<strong>Land Size:</strong> " . htmlspecialchars($row['ar_land_size']) . "<br>";
                          echo "<strong>Preferences:</strong> " . nl2br(htmlspecialchars($row['ar_design']));
                          break;
                        case 'brokerage':
                          echo "<strong>Purpose:</strong> " . htmlspecialchars($row['brokerage_purpose']) . "<br>";
                          echo "<strong>Type:</strong> " . htmlspecialchars($row['br_type']) . "<br>";
                          echo "<strong>Price:</strong> " . number_format($row['br_price'], 2) . "<br>";
                          if ($row['urgent_sale']) echo "<strong class='text-danger'>Urgent Sale</strong>";
                          break;
                        case 'sale_property_management':
                          echo "<strong>Price:</strong> " . number_format($row['sa_price'], 2);
                          break;
                        case 'legal_assistance':
                          echo "<strong>Type:</strong> " . htmlspecialchars($row['le_type']) . "<br>";
                          echo "<strong>Property:</strong> " . htmlspecialchars($row['le_property']) . "<br>";
                          echo "<strong>Issue:</strong> " . nl2br(htmlspecialchars($row['le_issue']));
                          break;
                        default:
                          echo '<span class="text-muted">–</span>';
                      }
                      ?>
                    </td>
                    <td>
                      <?php
                      // Map role to the staff type responsible for this service
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
                      // Payment status logic (confirmed, pending, rejected, or blank)
                      if ($row['application_payment_status'] === 'confirmed') {
                          echo '<span class="text-success">Confirmed</span>';
                          if (!empty($row['application_payment_confirmed_at'])) {
                              echo '<br><small class="text-muted">' . date('Y-m-d', strtotime($row['application_payment_confirmed_at'])) . '</small>';
                          }
                      } elseif ($row['application_payment_status'] === 'pending') {
                          echo '<span class="text-warning">Pending Confirmation</span>';
                      } elseif ($row['application_payment_status'] === 'rejected') {
                          echo '<span class="text-danger">Rejected</span>';
                      } else {
                          echo '<span class="text-muted">—</span>';
                      }
                      ?>
                    </td>
                    <td>
                      <?php if ($row['application_payment_status'] !== 'confirmed'): ?>
                        <span class="text-muted">Waiting for payment</span>
                      <?php elseif (!$row['assigned_agent_id']): ?>
                        <!-- Assignment form (agent + inspection date) -->
                        <form method="POST" action="assign-agent.php" class="d-flex gap-2">
                          <input type="hidden" name="type" value="owner_inspection">
                          <input type="hidden" name="request_id" value="<?= $row['request_id'] ?>">
                          <select name="agent_id" class="form-select form-select-sm" required>
                            <option value="">Select Agent</option>
                            <?php
                            // List all field agents for assignment
                            $agents = $pdo->query("SELECT staff_id, full_name FROM staff WHERE role = 'Field Agent'")->fetchAll();
                            foreach ($agents as $agent):
                            ?>
                              <option value="<?= $agent['staff_id'] ?>"><?= htmlspecialchars($agent['full_name']) ?></option>
                            <?php endforeach; ?>
                          </select>
                          <?php $minDateTime = date('Y-m-d\TH:i', strtotime('+24 hours')); ?>
                          <input type="datetime-local" name="inspection_date" min="<?= $minDateTime ?>" required>
                          <button class="btn btn-sm btn-success">Assign</button>
                        </form>
                      <?php else: ?>
                        <?= htmlspecialchars($row['assigned_agent']) ?><br>
                        <?= $row['inspection_date'] ? date("Y-m-d H:i", strtotime($row['inspection_date'])) : '' ?>
                      <?php endif; ?>
                    </td>
                    <td>
                      <?php if ($row['agent_report_path']): ?>
                        <a href="<?= htmlspecialchars($row['agent_report_path']) ?>" target="_blank" aria-label="Agent Report">View</a>
                      <?php else: ?>
                        <span class="text-muted">Pending</span>
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
                      if ($row['final_status'] === 'approved') echo '<span class="text-success">Approved</span>';
                      elseif ($row['final_status'] === 'rejected') echo '<span class="text-danger">Rejected</span>';
                      else echo '<span class="text-muted">Pending</span>';
                      ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- ----------- Tab 2: Service Outcome & Owner Contract --------- -->
        <div class="tab-pane fade" id="outcome">
          <div class="table-responsive">
            <table class="table table-bordered">
              <thead class="table-light">
                <tr>
                  <th>Applicant</th>
                  <th>Service</th>
                  <th>Final Status</th>
                  <th>Owner Meeting</th>
                  <th>Owner Contract</th>
                  <th>Listing</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($requests as $row): ?>
                  <?php if ($row['final_status'] === 'approved' || $row['final_status'] === 'rejected'): ?>
                  <tr>
                    <td><?= htmlspecialchars($row['applicant_name']) ?></td>
                    <td><?= htmlspecialchars($row['service_name']) ?></td>
                    <td>
                      <?php
                      if ($row['final_status'] === 'approved') echo '<span class="text-success">Approved</span>';
                      elseif ($row['final_status'] === 'rejected') echo '<span class="text-danger">Rejected</span>';
                      else echo '<span class="text-muted">Pending</span>';
                      ?>
                    </td>
                    <td>
                      <?php if (!$row['owner_contract_meeting']): ?>
                        <!-- Owner contract meeting scheduling form -->
                        <form method="POST" action="upload-contract.php">
                          <input type="hidden" name="request_id" value="<?= $row['request_id'] ?>">
                          <label><strong>Meeting:</strong></label>
                          <input type="datetime-local" name="owner_contract_meeting" min="<?= date('Y-m-d\TH:i', strtotime('+2 hours')) ?>" required>
                          <button type="submit" class="btn btn-sm btn-outline-primary custom-btn">Save</button>
                        </form>
                      <?php else: ?>
                        <span class="text-success">Scheduled</span><br>
                        <small><?= date('Y-m-d H:i', strtotime($row['owner_contract_meeting'])) ?></small>
                      <?php endif; ?>
                    </td>
                    <td>
                      <?php if ($row['owner_contract_meeting'] && !$row['owner_contract_path']): ?>
                        <!-- Upload signed contract form -->
                        <form method="POST" action="upload-contract.php" enctype="multipart/form-data">
                          <input type="hidden" name="request_id" value="<?= $row['request_id'] ?>">
                          <label><strong>Signed Contract (PDF):</strong></label>
                          <input type="file" name="owner_contract_file" accept="application/pdf" required>
                          <button type="submit" class="btn btn-sm custom-btn">Upload</button>
                        </form>
                      <?php elseif (!empty($row['owner_contract_path'])): ?>
                        <a href="<?= htmlspecialchars($row['owner_contract_path']) ?>" target="_blank">View Contract</a>
                      <?php else: ?>
                        <span class="text-muted">Pending Meeting</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <?php if ($row['final_status'] === 'approved'): ?>
                        <?php if ($row['owner_contract_path'] && !$row['listed']): ?>
                          <a href="list-property.php?request_id=<?= $row['request_id'] ?>" class="btn btn-sm btn-success">List Property</a>
                        <?php elseif ($row['listed']): ?>
                          <span class="text-success">Listed</span>
                        <?php else: ?>
                          <span class="text-muted">Contract not uploaded</span>
                        <?php endif; ?>
                      <?php elseif ($row['final_status'] === 'rejected'): ?>
                        <span class="text-danger">N/A</span>
                      <?php else: ?>
                        <span class="text-muted">Pending</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                  <?php endif; ?>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
      <a href="staff-profile.php" class="btn custom-btn mb-4">Back to Profile</a>
    </main>
  </div>
</div>

<?php include 'footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
