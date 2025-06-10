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
?>

<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>My Brokerage Claims</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="styles.css?v=<?= time() ?>">
</head>
<body class="d-flex flex-column min-vh-100 bg-dark">
<?php include 'header.php'; ?>

<!-- Show upload status message if present -->
<?php if (isset($_SESSION['success_message'])): ?>
  <div class="alert alert-success"><?= $_SESSION['success_message'] ?></div>
  <?php unset($_SESSION['success_message']); ?>
<?php endif; ?>

<div class="container-fluid d-flex flex-grow-1 flex-column p-0">
  <div class="row flex-grow-1 g-0">
    <main class="col-md-10 p-4 w-100">
      <div class="mb-4 p-3 border rounded shadow-sm main-title">
        <h2 class="mb-4">My Claimed Brokerage Properties</h2>
      </div>

      <!-- If no claims, show message -->
      <?php if (empty($claims)): ?>
        <div class="alert alert-info">You have no claimed brokerage properties yet.</div>
      <?php else: ?>
        <!-- Loop over all claims -->
        <?php foreach ($claims as $claim): 
          $status = ($claim['final_status'] === 'completed') ? 'Completed' : 'Pending';
        ?>
        <div class="card mb-4 shadow">
          <div class="row g-0">
            <div class="col-md-4">
              <!-- Property image, fallback if missing -->
              <img src="<?= file_exists($claim['image']) ? $claim['image'] : 'images/default.png' ?>" class="img-fluid h-100 object-fit-cover" alt="Property Image">
            </div>
            <div class="col-md-8">
              <div class="card-body">
                <h5 class="card-title"><?= htmlspecialchars($claim['property_name']) ?></h5>
                <p>
                  <strong>Type:</strong> <?= ucfirst($claim['listing_type']) ?><br>
                  <strong>Price:</strong> CFA <?= number_format($claim['price']) ?><br>
                  <strong>Claim Type:</strong> <?= ucfirst($claim['claim_type']) ?><br>
                  <strong>Meeting:</strong> <?= $claim['meeting_datetime'] ? date('d M Y, H:i', strtotime($claim['meeting_datetime'])) : 'Pending' ?><br>
                  <?php if ($claim['agent_name']): ?>
                    <strong>Agent:</strong> <?= htmlspecialchars($claim['agent_name']) ?><br>
                  <?php endif; ?>
                  <?php if ($claim['meeting_report_summary']): ?>
                    <strong>Agent Report:</strong> <?= htmlspecialchars($claim['meeting_report_summary']) ?><br>
                  <?php endif; ?>
                  <strong>Status:</strong> 
                  <span class="badge <?= $status === 'Completed' ? 'bg-success' : 'bg-warning' ?>">
                    <?= htmlspecialchars($status) ?>
                  </span>
                </p>

                <hr>

                <!-- Payment upload logic for rent claims -->
                <?php if (
                  $claim['claim_type'] === 'rent' &&
                  $claim['claim_source'] === 'brokerage' &&
                  $claim['claim_status'] === 'claimed'
                ): ?>
                  <h6>Claim Payment (Client)</h6>
                  <?php if ($claim['payment_status'] === 'confirmed'): ?>
                    <span class="text-success">Payment Confirmed</span>
                  <?php elseif ($claim['invoice_path']): ?>
                    <p>Invoice: <a href="<?= $claim['invoice_path'] ?>" target="_blank" class="btn btn-sm btn-outline-primary">View</a></p>
                    <?php if (!$claim['payment_proof']): ?>
                      <form method="POST" action="client-claimed-brokerage.php" enctype="multipart/form-data">
                        <input type="hidden" name="claim_id" value="<?= $claim['claim_id'] ?>">
                        <input type="hidden" name="type" value="client">
                        <input type="file" name="payment_proof" accept="image/*,application/pdf" required class="form-control form-control-sm mb-2">
                        <button class="btn btn-sm custom-btn">Upload Payment Proof</button>
                      </form>
                    <?php else: ?>
                      <p>Payment Proof: <a href="<?= $claim['payment_proof'] ?>" target="_blank" class="btn btn-sm btn-outline-primary">View</a></p>
                      <span class="text-warning">Awaiting Confirmation</span>
                    <?php endif; ?>
                  <?php else: ?>
                    <span class="text-danger">Invoice not yet issued. Kindly pay the claim fees once invoice is available. A meeting will be booked for you and the landlord for the next step.</span>
                  <?php endif; ?>
                <!-- For sale claims, meeting only -->
                <?php elseif (
                  $claim['claim_type'] === 'sale' &&
                  $claim['claim_source'] === 'brokerage' &&
                  $claim['claim_status'] === 'claimed'
                ): ?>
                  <h6>Claim Payment</h6>
                  <span class="text-success fw-bold">A meeting date and time will be arranged for the next step. Kindly don't miss your appointment.</span>
                <?php endif; ?>

                <!-- View property link -->
                <div class="mt-3">
                  <a href="view-property.php?id=<?= $claim['property_id'] ?>" class="btn btn-outline-primary btn-sm">View Property</a>
                </div>
              </div>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>

      <!-- Back to profile -->
      <div class="mt-4">
        <a href="client-profile.php" class="btn custom-btn">Back to Profile</a>
      </div>
    </main>
  </div>
</div>

<?php include 'footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
