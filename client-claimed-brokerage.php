<?php
// -----------------------------------------------------------------------------
// client-claimed-brokerage.php
// -----------------------------------------------------------------------------
// Displays and handles all claimed brokerage properties for a client.
// - Allows payment proof upload
// - Shows claim/payment status, meeting, agent, and agent report
// -----------------------------------------------------------------------------

session_start();
require_once 'check-user-session.php';
require 'db_connect.php';

// Get client_id from session
$fullName = $_SESSION['user_name'] ?? 'Unknown client';
$userId = $_SESSION['client_id'];

// --- 1. Handle payment proof upload (POST) ---
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_FILES['payment_proof'], $_POST['claim_id'], $_POST['type'])
) {
    $claim_id = $_POST['claim_id'];
    $type = $_POST['type']; // 'client' or 'owner'

    // Fetch property and owner info for destination folder
    $stmt = $pdo->prepare("
        SELECT p.property_id, p.property_name, p.owner_id, ou.full_name AS owner_name
        FROM client_claims cc
        JOIN properties p ON cc.property_id = p.property_id
        JOIN owners o ON p.owner_id = o.owner_id
        JOIN users ou ON o.user_id = ou.user_id
        WHERE cc.claim_id = ?
    ");
    $stmt->execute([$claim_id]);
    $claim_info = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($claim_info) {
        // Prepare destination folder for uploaded file
        $owner_id = $claim_info['owner_id'];
        $owner_name = preg_replace('/[^a-zA-Z0-9_]/', '_', $claim_info['owner_name']);
        $property_id = $claim_info['property_id'];
        $property_name = preg_replace('/[^a-zA-Z0-9_]/', '_', $claim_info['property_name']);
        $folder = "uploads/owner/{$owner_id}_{$owner_name}/listed_properties/{$property_id}_{$property_name}/";
        if (!is_dir($folder)) {
            mkdir($folder, 0777, true);
        }

        // Store payment proof with a unique filename
        $ext = pathinfo($_FILES['payment_proof']['name'], PATHINFO_EXTENSION);
        $filename = "payment_proof_{$type}_" . time() . '.' . $ext;
        $destination = $folder . $filename;

        // Move uploaded file to destination
        if (move_uploaded_file($_FILES['payment_proof']['tmp_name'], $destination)) {
            $stmt = $pdo->prepare(
                "UPDATE brokerage_claim_payments 
                 SET payment_proof = ?, payment_status = 'pending' 
                 WHERE claim_id = ? AND payment_type = ?"
            );
            $stmt->execute([$destination, $claim_id, $type]);
            $_SESSION['success_message'] = "Payment proof uploaded successfully.";
            header("Location: client-claimed-brokerage.php");
            exit();
        } else {
            echo "<div class='alert alert-danger'>Failed to upload payment proof.</div>";
        }
    }
}

// --- 2. Fetch all client brokerage claims ---
$client_id = $_SESSION['client_id'];
$stmt = $pdo->prepare("
    SELECT cc.claim_id, cc.property_id, cc.claim_type, cc.claim_status,
           p.property_name, p.image, p.price, p.owner_id, p.listing_type,
           cc.meeting_datetime, cc.meeting_agent_id, cc.meeting_report_path, cc.meeting_report_summary, cc.final_status,
           cb.invoice_path, cb.payment_proof, cb.payment_status,
           s.full_name AS agent_name,
           cc.claim_source
    FROM client_claims cc
    JOIN properties p ON cc.property_id = p.property_id
    JOIN services sv ON p.service_id = sv.service_id
    LEFT JOIN brokerage_claim_payments cb ON cc.claim_id = cb.claim_id AND cb.payment_type = 'client'
    LEFT JOIN staff s ON cc.meeting_agent_id = s.staff_id
    WHERE cc.client_id = ? AND sv.slug = 'brokerage'
    ORDER BY cc.claimed_at DESC
");
$stmt->execute([$client_id]);
$claims = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Divide claims into two groups for tabs: Active (not completed), Completed
$activeClaims = [];
$completedClaims = [];
foreach ($claims as $c) {
    if ($c['final_status'] === 'completed') $completedClaims[] = $c;
    else $activeClaims[] = $c;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>My Reserved Brokerage Properties</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="styles.css?v=<?= time() ?>">
</head>
<body class="d-flex flex-column min-vh-100 bg-light">
<?php include 'header.php'; ?>

<!-- Alert -->
<?php if (isset($_SESSION['success_message'])): ?>
  <div class="alert alert-success text-center"><?= $_SESSION['success_message']; unset($_SESSION['success_message']); ?></div>
<?php endif; ?>

<div class="container py-4 flex-grow-1">
  <div class="row">

    <!-- Main Content -->
    <main class="col-12 col-md-8">
      <div class="mb-4 section-title">My Reserved Brokerage Properties</div>

      <!-- Tabs for Active/Completed -->
      <ul class="nav nav-tabs mb-3" id="claimTabs" role="tablist">
        <li class="nav-item" role="presentation">
          <button class="nav-link active custom-btn" id="active-tab" data-bs-toggle="tab" data-bs-target="#active" type="button" role="tab">Active</button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link custom-btn" id="completed-tab" data-bs-toggle="tab" data-bs-target="#completed" type="button" role="tab">Completed</button>
        </li>
      </ul>
      <div class="tab-content" id="claimTabsContent">
        <!-- Active claims tab -->
        <div class="tab-pane fade show active" id="active" role="tabpanel">
          <?php if (empty($activeClaims)): ?>
            <div class="no-claims text-center py-5">No active reserved brokerage proeprties yet.<br></div>
          <?php else: ?>
            <?php foreach ($activeClaims as $claim): ?>
              <div class="card mb-4 property-card shadow-sm">
                <div class="row g-0">
                  <div class="col-md-5 col-12">
                    <img src="<?= file_exists($claim['image']) ? $claim['image'] : 'images/default.png' ?>" class="img-fluid rounded-start property-img w-100 h-100">
                  </div>
                  <div class="col-md-7 col-12">
                    <div class="card-body">
                      <h5 class="card-title"><?= htmlspecialchars($claim['property_name']) ?></h5>
                      <p class="mb-1">
                        <span class="badge rounded-pill bg-info"><?= ucfirst($claim['listing_type']) ?></span>
                        <span class="ms-2 text-success">CFA <?= number_format($claim['price']) ?></span>
                      </p>
                      <div>
                        <small class="text-muted">Type:</small> <?= ucfirst($claim['claim_type']) ?><br>
                        <small class="text-muted">Meeting:</small> <?= $claim['meeting_datetime'] ? date('d M Y, H:i', strtotime($claim['meeting_datetime'])) : '<em>Pending</em>' ?><br>
                        <?php if ($claim['agent_name']): ?>
                          <small class="text-muted">Agent:</small> <?= htmlspecialchars($claim['agent_name']) ?><br>
                        <?php endif; ?>
                        <?php if ($claim['meeting_report_summary']): ?>
                          <small class="text-muted">Agent Report:</small> <?= htmlspecialchars($claim['meeting_report_summary']) ?><br>
                        <?php endif; ?>
                      </div>
                      <div class="my-2">
                        <span class="badge badge-status <?= $claim['final_status'] === 'completed' ? 'bg-success' : 'bg-warning' ?>" 
                              data-bs-toggle="tooltip" title="<?= $claim['final_status'] === 'completed' ? 'All steps done' : 'In progress' ?>">
                          <?= $claim['final_status'] === 'completed' ? 'Completed' : 'Active' ?>
                        </span>
                      </div>
                      <hr>
                      <!-- Payment Proof Logic -->
                      <?php if (
                        $claim['claim_type'] === 'rent' &&
                        $claim['claim_source'] === 'brokerage' &&
                        $claim['claim_status'] === 'claimed'
                      ): ?>
                        <h6 class="mb-2">Reserve Payment (Client)</h6>
                        <?php if ($claim['payment_status'] === 'confirmed'): ?>
                          <span class="text-success">Payment Confirmed <i class="bi bi-check-circle"></i></span>
                        <?php elseif ($claim['invoice_path']): ?>
                          <div class="mb-2">
                            <a href="<?= $claim['invoice_path'] ?>" target="_blank" class="btn btn-sm btn-outline-primary">View Invoice</a>
                          </div>
                          <?php if (!$claim['payment_proof']): ?>
                            <form method="POST" action="" enctype="multipart/form-data" class="d-flex flex-column gap-2">
                              <input type="hidden" name="claim_id" value="<?= $claim['claim_id'] ?>">
                              <input type="hidden" name="type" value="client">
                              <input type="file" name="payment_proof" accept="image/*,application/pdf" required class="form-control form-control-sm">
                              <button class="btn btn-sm custom-btn">Upload Payment Proof</button>
                            </form>
                          <?php else: ?>
                            <div class="mb-1">
                              <span>Payment Proof: <a href="<?= $claim['payment_proof'] ?>" target="_blank">View</a></span>
                            </div>
                            <span class="text-warning">Awaiting Confirmation</span>
                          <?php endif; ?>
                        <?php else: ?>
                          <span class="text-danger">Invoice not yet issued.<br>You'll be able to pay once available.</span>
                        <?php endif; ?>
                      <?php elseif (
                        $claim['claim_type'] === 'sale' &&
                        $claim['claim_source'] === 'brokerage' &&
                        $claim['claim_status'] === 'claimed'
                      ): ?>
                        <div class="alert alert-info py-2 mb-2">
                          A meeting will be arranged for you and the property owner.<br>
                          Please check your notifications and don't miss your appointment.
                        </div>
                      <?php endif; ?>

                      <a href="view-property.php?id=<?= $claim['property_id'] ?>" class="btn btn-outline-primary btn-sm mt-3">View Property</a>
                    </div>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
        <!-- Completed claims tab -->
        <div class="tab-pane fade" id="completed" role="tabpanel">
          <?php if (empty($completedClaims)): ?>
            <div class="no-claims text-center py-5">No completed reservations yet.</div>
          <?php else: ?>
            <?php foreach ($completedClaims as $claim): ?>
              <div class="card mb-4 property-card shadow-sm border-success border-2">
                <div class="row g-0">
                  <div class="col-md-5 col-12">
                    <img src="<?= file_exists($claim['image']) ? $claim['image'] : 'images/default.png' ?>" class="img-fluid rounded-start property-img w-100 h-100">
                  </div>
                  <div class="col-md-7 col-12">
                    <div class="card-body">
                      <h5 class="card-title"><?= htmlspecialchars($claim['property_name']) ?></h5>
                      <div class="mb-2">
                        <span class="badge rounded-pill bg-success">Completed</span>
                      </div>
                      <div>
                        <small class="text-muted">Type:</small> <?= ucfirst($claim['listing_type']) ?><br>
                        <small class="text-muted">Price:</small> CFA <?= number_format($claim['price']) ?><br>
                        <small class="text-muted">Meeting:</small> <?= $claim['meeting_datetime'] ? date('d M Y, H:i', strtotime($claim['meeting_datetime'])) : '<em>n/a</em>' ?><br>
                        <?php if ($claim['agent_name']): ?>
                          <small class="text-muted">Agent:</small> <?= htmlspecialchars($claim['agent_name']) ?><br>
                        <?php endif; ?>
                        <?php if ($claim['meeting_report_summary']): ?>
                          <small class="text-muted">Agent Report:</small> <?= htmlspecialchars($claim['meeting_report_summary']) ?><br>
                        <?php endif; ?>
                      </div>
                      <a href="view-property.php?id=<?= $claim['property_id'] ?>" class="btn btn-outline-primary btn-sm mt-3">View Property</a>
                    </div>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
      <a href="client-profile.php" class="btn btn-dark fw-bold mt-4">🡰 Back to dashboard</a>
    </main>
  </div>
</div>
<?php include 'footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
<!-- Activate tooltips and keep tabs sticky after reload -->
<script>
document.addEventListener('DOMContentLoaded', function () {
  var triggerTooltipList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
  triggerTooltipList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
  });

  // Persist tab across reloads
  var tabKey = "client-claimed-brokerage-tab";
  var urlTab = window.location.hash;
  var lastTab = localStorage.getItem(tabKey) || (urlTab ? urlTab.replace('#', '') : 'active');
  var targetTab = document.querySelector('[data-bs-target="#' + lastTab + '"]');
  if (targetTab) new bootstrap.Tab(targetTab).show();
  document.querySelectorAll('#claimTabs button[data-bs-toggle="tab"]').forEach(function(tabBtn) {
    tabBtn.addEventListener('shown.bs.tab', function(e) {
      localStorage.setItem(tabKey, e.target.getAttribute('data-bs-target').replace('#', ''));
    });
  });
});
</script>
</body>
</html>