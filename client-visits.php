<?php
/**
 * -----------------------------------------------------------------------------------
 * client-visits.php 
 * -----------------------------------------------------------------------------------
 * 
 * Client Property Visits Page
 *
 * Shows all property onsite visits booked by the logged-in client.
 * Displays visit details, assigned agent, and status.
 * Allows client to claim a property after visit approval.
 *
 * Features:
 * - Responsive table listing all client visits
 * - Displays property details, agent feedback, report results
 * - Allows property claiming if manager approves
 *
 * Dependencies:
 * - check-user-session.php (ensures user is logged in)
 * - db_connect.php (database connection)
 * - Bootstrap 5.3 for responsive UI
 * 
 * --------------------------------------------------------------------------------------
 */

session_start();
require_once 'check-user-session.php';
require 'db_connect.php';

// Get client_id from session
$client_id = $_SESSION['client_id'];

// Fetch all onsite property visits for the client
$stmt = $pdo->prepare("
    SELECT v.visit_id, v.property_id, v.visit_date, v.visit_time, v.status AS visit_status,
           v.assigned_agent_id, v.agent_feedback, v.report_result,
           p.property_name, p.image, p.price, p.listing_type,
           s.full_name AS agent_name
    FROM client_onsite_visits v
    JOIN properties p ON v.property_id = p.property_id
    LEFT JOIN staff s ON v.assigned_agent_id = s.staff_id
    WHERE v.client_id = ?
    ORDER BY v.visit_date DESC, v.visit_time DESC
");
$stmt->execute([$client_id]);
$visits = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>My Property Visits</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-4Q6Gf2aSP4eDXB8Miphtr37CMZZQ5oXLH2yaXMJ2w8e2ZtHTl7GptT4jmndRuHDT" crossorigin="anonymous">
  <link rel="stylesheet" href="styles.css?v=<?= time() ?>">
</head>
<body class="d-flex flex-column min-vh-100 bg-light">
<?php include 'header.php'; ?>

<div class="container-fluid flex-grow-1 d-flex flex-column">
  <div class="row flex-grow-1">
    <!-- Main Content -->
    <main class="col-12 col-md-9 w-100">
      <div class="mb-4 p-3 border rounded shadow-sm main-title">
        <h2 class="mb-4">My Property Visits</h2>
      </div>

      <?php if (empty($visits)): ?>
        <div class="alert alert-info">You have not booked any onsite visits yet.</div>
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
              <?php foreach ($visits as $visit): ?>
                <tr>
                  <td>
                    <!-- Property image with fallback -->
                    <img src="<?= !empty($visit['image']) && file_exists($visit['image']) ? $visit['image'] : 'images/default.png' ?>" width="100" height="75" style="object-fit: cover;" alt="Property Image">
                    <a href="view-property.php?id=<?= $visit['property_id'] ?>" class="btn btn-sm btn-outline-primary mb-1">View</a>
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
                    // Check if this property has already been claimed by the client
                    $claimCheckStmt = $pdo->prepare("
                        SELECT COUNT(*) FROM client_claims 
                        WHERE client_id = ? AND property_id = ?
                    ");
                    $claimCheckStmt->execute([$client_id, $visit['property_id']]);
                    $alreadyClaimed = $claimCheckStmt->fetchColumn() > 0;

                    // Check if manager has approved this visit for claim
                    $claimPermissionStmt = $pdo->prepare("
                        SELECT final_status FROM client_onsite_visits
                        WHERE visit_id = ? AND client_id = ?
                    ");
                    $claimPermissionStmt->execute([$visit['visit_id'], $client_id]);
                    $managerReview = $claimPermissionStmt->fetchColumn();

                    // Show claim form if not yet claimed and manager approved
                    if (!$alreadyClaimed && strtolower($managerReview) === 'approved'): ?>
                      <form action="claim-property.php" method="POST" class="mt-1">
                        <input type="hidden" name="property_id" value="<?= $visit['property_id'] ?>">
                        <input type="hidden" name="visit_id" value="<?= $visit['visit_id'] ?>">
                        <button type="submit" class="btn btn-sm btn-success">Claim</button>
                      </form>
                    <?php elseif ($alreadyClaimed): ?>
                      <div class="text-success mt-1 fw-semibold">Claimed</div>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>

      <div class="mt-4">
        <a href="client-profile.php" class="btn custom-btn">Back to Profile</a>
      </div>
    </main>
  </div>
</div>
<?php include 'footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js" integrity="sha384-j1CDi7MgGQ12Z7Qab0qlWQ/Qqz24Gc6BM0thvEMVjHnfYGF0rmFCozFSxQBxwHKO" crossorigin="anonymous"></script>
</body>
</html>
