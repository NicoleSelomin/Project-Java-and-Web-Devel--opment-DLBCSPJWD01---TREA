<?php
/**
 * client-visits.php
 * Shows all property onsite visits booked by the logged-in client, split into
 * Not Reserved (not yet claimed) and Reserved (already claimed) tabs.
 */
session_start();
require_once 'check-user-session.php';
require 'db_connect.php';

// Get client info
$fullName = $_SESSION['user_name'] ?? 'Unknown client';
$userId   = $_SESSION['client_id'];

// Fetch all onsite visits for this client
$stmt = $pdo->prepare("
    SELECT v.visit_id, v.property_id, v.visit_date, v.visit_time, v.status AS visit_status,
           v.assigned_agent_id, v.agent_feedback, v.report_result, v.final_status,
           p.property_name, p.image, p.price, p.listing_type,
           s.full_name AS agent_name
      FROM client_onsite_visits v
      JOIN properties p ON v.property_id = p.property_id
      LEFT JOIN staff s ON v.assigned_agent_id = s.staff_id
     WHERE v.client_id = ?
     ORDER BY v.visit_date DESC, v.visit_time DESC
");
$stmt->execute([$userId]);
$visits = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Separate into not claimed and claimed
$notClaimedVisits = [];
$claimedVisits = [];
foreach ($visits as $visit) {
    // Check if property has already been claimed by the client
    $claimCheckStmt = $pdo->prepare("SELECT COUNT(*) FROM client_claims WHERE client_id = ? AND property_id = ?");
    $claimCheckStmt->execute([$userId, $visit['property_id']]);
    $alreadyClaimed = $claimCheckStmt->fetchColumn() > 0;

    if ($alreadyClaimed) {
        $claimedVisits[] = $visit;
    } else {
        $notClaimedVisits[] = $visit;
    }
}

// Sort by visit date/time DESC (most recent first)
usort($notClaimedVisits, function($a, $b) {
    return strtotime($b['visit_date'].' '.$b['visit_time']) <=> strtotime($a['visit_date'].' '.$a['visit_time']);
});
usort($claimedVisits, function($a, $b) {
    return strtotime($b['visit_date'].' '.$b['visit_time']) <=> strtotime($a['visit_date'].' '.$a['visit_time']);
});

// Profile picture (optional)
$profilePicturePath = 'images/default.png';
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>My Property Visits</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="styles.css?v=<?= time() ?>">
</head>
<body class="d-flex flex-column min-vh-100 bg-light">
<?php include 'header.php'; ?>

<div class="container-fluid flex-grow-1 d-flex flex-column">
  <div class="row flex-grow-1">
    <!-- Sidebar -->
    <aside class="col-12 col-md-3 mb-3">
      <button class="btn btn-sm d-md-none mb-3 custom-btn" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarCollapse">
        Open Sidebar
      </button>
      <div class="collapse d-md-block" id="sidebarCollapse">
        <div class="sidebar text-center">
          <div class="profile-summary text-center">
            <img src="<?= htmlspecialchars($profilePicturePath) ?>" alt="Profile Picture" class="mb-3 img-fluid rounded-circle" style="max-width:120px;">
            <p><strong><?= htmlspecialchars($fullName) ?></strong></p>
            <p>ID: <?= htmlspecialchars($userId) ?></p>
            <a href="notifications.php" class="btn mt-3 bg-light w-100">View Notifications</a>
            <a href="edit-client-profile.php" class="btn mt-3 bg-light w-100">Edit Profile</a>
            <a href="user-logout.php" class="btn text-danger mt-3 d-block bg-light w-100">Logout</a>
          </div>
          <div>
            <h5 class="mt-5">Calendar</h5>
            <iframe src="https://calendar.google.com/calendar/embed?mode=MONTH" frameborder="0" scrolling="no" style="width:100%; min-height:300px;"></iframe>
          </div>
        </div>
      </div>
    </aside>

    <!-- Main Content -->
    <main class="col-12 col-md-9">
      <div class="mb-4 p-3 border rounded shadow-sm main-title">
        <h2>My Property Visits</h2>
      </div>

      <ul class="nav nav-tabs mb-4" id="visitTabs" role="tablist">
        <li class="nav-item">
          <button class="nav-link active custom-btn" id="notclaimed-tab" data-bs-toggle="tab" data-bs-target="#notclaimed" type="button" role="tab">Not Reserved</button>
        </li>
        <li class="nav-item">
          <button class="nav-link custom-btn" id="claimed-tab" data-bs-toggle="tab" data-bs-target="#claimed" type="button" role="tab">Reserved</button>
        </li>
      </ul>
      <div class="tab-content" id="visitTabsContent">

        <!-- Not Reserved Tab -->
        <div class="tab-pane fade show active" id="notclaimed" role="tabpanel">
          <?php if (empty($notClaimedVisits)): ?>
            <div class="alert alert-info">No unreserved property visits found.</div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table table-bordered table-striped align-middle">
                <thead class="table-dark">
                  <tr>
                    <th>Image</th>
                    <th>Property</th>
                    <th>Type</th>
                    <th>Price (CFA)</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Status</th>
                    <th>Agent</th>
                    <th>Feedback</th>
                    <th>Result</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                <?php foreach ($notClaimedVisits as $visit): ?>
                  <tr>
                    <td>
                      <img src="<?= !empty($visit['image']) && file_exists($visit['image']) ? $visit['image'] : 'images/default.png' ?>" width="100" height="75" style="object-fit: cover;" alt="Property Image">
                      <a href="view-property.php?property_id=<?= $visit['property_id'] ?>" class="btn btn-sm btn-outline-primary mb-1 custom-btn">View</a>
                    </td>
                    <td><?= htmlspecialchars($visit['property_name']) ?></td>
                    <td><?= ucfirst($visit['listing_type']) ?></td>
                    <td><?= number_format($visit['price']) ?></td>
                    <td><?= date('d M Y', strtotime($visit['visit_date'])) ?></td>
                    <td><?= date('H:i', strtotime($visit['visit_time'])) ?></td>
                    <td><?= ucfirst($visit['visit_status']) ?></td>
                    <td><?= $visit['agent_name'] ?? '—' ?></td>
                    <td><?= $visit['agent_feedback'] ?? '—' ?></td>
                    <td><?= $visit['report_result'] ?? '—' ?></td>
                    <td>
                      <?php
                      // Check if manager has approved this visit for claim
                      $managerReview = $visit['final_status'] ?? '';
                      if (strtolower($managerReview) === 'approved'): ?>
                        <form action="claim-property.php" method="POST" class="mt-1">
                          <input type="hidden" name="property_id" value="<?= $visit['property_id'] ?>">
                          <input type="hidden" name="visit_id" value="<?= $visit['visit_id'] ?>">
                          <button type="submit" class="btn btn-sm btn-success">Reserve</button>
                        </form>
                      <?php else: ?>
                        <span class="text-muted">Awaiting manager approval</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>

        <!-- Reserved Tab -->
        <div class="tab-pane fade" id="claimed" role="tabpanel">
          <?php if (empty($claimedVisits)): ?>
            <div class="alert alert-info">No reserved properties yet.</div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table table-bordered table-striped align-middle">
                <thead class="table-dark">
                  <tr>
                    <th>Image</th>
                    <th>Property</th>
                    <th>Type</th>
                    <th>Price (CFA)</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Status</th>
                    <th>Agent</th>
                    <th>Feedback</th>
                    <th>Result</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                <?php foreach ($claimedVisits as $visit): ?>
                  <tr>
                    <td>
                      <img src="<?= !empty($visit['image']) && file_exists($visit['image']) ? $visit['image'] : 'images/default.png' ?>" width="100" height="75" style="object-fit: cover;" alt="Property Image">
                      <a href="view-property.php?property_id=<?= $visit['property_id'] ?>" class="btn btn-sm btn-outline-primary mb-1 custom-btn">View</a>
                    </td>
                    <td><?= htmlspecialchars($visit['property_name']) ?></td>
                    <td><?= ucfirst($visit['listing_type']) ?></td>
                    <td><?= number_format($visit['price']) ?></td>
                    <td><?= date('d M Y', strtotime($visit['visit_date'])) ?></td>
                    <td><?= date('H:i', strtotime($visit['visit_time'])) ?></td>
                    <td><?= ucfirst($visit['visit_status']) ?></td>
                    <td><?= $visit['agent_name'] ?? '—' ?></td>
                    <td><?= $visit['agent_feedback'] ?? '—' ?></td>
                    <td><?= $visit['report_result'] ?? '—' ?></td>
                    <td>
                      <div class="text-success fw-semibold">Reserved</div>
                    </td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <div class="mt-4">
        <a href="client-profile.php" class="btn bg-dark text-white fw-bold">🡰 Back to dashboard</a>
      </div>
    </main>
  </div>
</div>

<?php include 'footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
<script src="navbar-close.js?v=1"></script>
</body>
</html>
