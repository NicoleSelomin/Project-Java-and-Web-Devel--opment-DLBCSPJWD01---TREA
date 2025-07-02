<?php
/*
|--------------------------------------------------------------------------
| confirm-brokerage-claim-payments.php
|--------------------------------------------------------------------------
| Accountant: Manage brokerage claim invoices and payment confirmations
| - Only for claims with properties where services.slug = 'brokerage'
| - Show: Claim type (rent/client, sale/owner), Edit Invoice (via template), View proof, Confirm payment
| - No file upload for invoice! Edit via edit-invoice.php
|--------------------------------------------------------------------------
*/

session_start();
require 'db_connect.php';

// --- 1. Access control (Accountant and general manager only) ---
if (!isset($_SESSION['staff_id']) || !in_array(strtolower($_SESSION['role']), ['accountant', 'general manager'])) {
    header("Location: staff-login.php");
    exit();
}

// --- 2. Handle payment confirmation ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_claim_id'])) {
    $claim_id = $_POST['confirm_claim_id'];
    $payment_type = $_POST['payment_type']; // 'client' or 'owner'
    $confirmed_by = $_SESSION['staff_id'];

    // Confirm payment in brokerage_claim_payments
    $stmt = $pdo->prepare("UPDATE brokerage_claim_payments
        SET payment_status = 'confirmed', confirmed_by = ?, confirmed_at = NOW()
        WHERE claim_id = ? AND payment_type = ?");
    $stmt->execute([$confirmed_by, $claim_id, $payment_type]);
    $_SESSION['success_message'] = "Payment confirmed!";
    header("Location: confirm-brokerage-claim-payments.php");
    exit();
}

// --- 3. Fetch all brokerage claims with invoice/payment info ---
$stmt = $pdo->query("
    SELECT 
        cc.claim_id, cc.claim_type, cc.claim_status, cc.claim_source,
        p.property_name, p.location, p.property_id,
        s.service_name, s.slug,
        cu.full_name AS client_name,
        o.owner_id, ou.full_name AS owner_name,
        cb.invoice_path, cb.payment_proof, cb.payment_status, cb.payment_type,
        osr.request_id
    FROM client_claims cc
    JOIN properties p ON cc.property_id = p.property_id
    JOIN services s ON p.service_id = s.service_id
    LEFT JOIN clients c ON cc.client_id = c.client_id
    LEFT JOIN users cu ON c.user_id = cu.user_id
    LEFT JOIN owners o ON p.owner_id = o.owner_id
    LEFT JOIN users ou ON o.user_id = ou.user_id
    LEFT JOIN brokerage_claim_payments cb ON cc.claim_id = cb.claim_id
    LEFT JOIN owner_service_requests osr ON p.request_id = osr.request_id    
    WHERE s.slug = 'brokerage'
    ORDER BY cc.claimed_at DESC
");
$claims = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group claims by claim_type
$rentalClaims = [];
$saleClaims = [];
foreach ($claims as $row) {
    if ($row['claim_type'] === 'rent') $rentalClaims[] = $row;
    elseif ($row['claim_type'] === 'sale') $saleClaims[] = $row;
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Confirm Brokerage Reservation Payments</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="styles.css?v=<?= time() ?>">
</head>
<body class="d-flex flex-column min-vh-100 bg-light">
<?php include 'header.php'; ?>

<div class="container py-4 flex-grow-1">
    <h2 class="mb-4">Confirm Brokerage Claim Payments</h2>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success"><?= $_SESSION['success_message']; unset($_SESSION['success_message']); ?></div>
    <?php endif; ?>

    <ul class="nav nav-tabs mb-3" id="claimTabs" role="tablist">
      <li class="nav-item" role="presentation">
        <button class="nav-link active custom-btn" id="rent-tab" data-bs-toggle="tab" data-bs-target="#rent" type="button" role="tab">Reserved Rental Properties (Client)</button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link custom-btn" id="sale-tab" data-bs-toggle="tab" data-bs-target="#sale" type="button" role="tab">Reserved Sale properties (Owner)</button>
      </li>
    </ul>
    <div class="tab-content" id="claimTabsContent">

      <!-- RENT CLAIMS: Invoice for Client -->
      <div class="tab-pane fade show active" id="rent" role="tabpanel">
        <div class="table-responsive">
        <table class="table table-bordered align-middle">
            <thead class="table-light">
                <tr>
                    <th>Property</th>
                    <th>Client</th>
                    <th>Invoice</th>
                    <th>Payment Proof</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rentalClaims)): ?>
                  <tr><td colspan="6" class="text-center text-muted">No reserved rental properties found.</td></tr>
                <?php else: ?>
                  <?php foreach ($rentalClaims as $claim): ?>
                    <tr>
                      <td>
                        <strong><?= htmlspecialchars($claim['property_name']) ?></strong><br>
                        <small><?= htmlspecialchars($claim['location']) ?></small>
                      </td>
                      <td><?= htmlspecialchars($claim['client_name']) ?></td>
                      <td>
                        <?php if ($claim['invoice_path']): ?>
                          <a href="<?= $claim['invoice_path'] ?>" target="_blank" class="btn btn-sm btn-outline-primary">View Invoice</a>
                        <?php else: ?>
                          <a href="edit-invoice.php?request_id=<?= $claim['claim_id'] ?>&type=client" class="btn btn-sm custom-btn">Edit/Issue Invoice</a>
                        <?php endif; ?>
                      </td>
                      <td>
                        <?php if ($claim['payment_proof']): ?>
                          <a href="<?= $claim['payment_proof'] ?>" target="_blank">View</a>
                        <?php else: ?>
                          <span class="text-muted">Waiting for proof</span>
                        <?php endif; ?>
                      </td>
                      <td>
                        <?php
                          if (!$claim['invoice_path']) echo '<span class="badge bg-secondary status-badge">Not Issued</span>';
                          elseif (!$claim['payment_proof']) echo '<span class="badge bg-warning status-badge">Awaiting Payment</span>';
                          elseif ($claim['payment_status'] === 'confirmed') echo '<span class="badge bg-success status-badge">Confirmed</span>';
                          else echo '<span class="badge bg-info status-badge">Awaiting Confirmation</span>';
                        ?>
                      </td>
                      <td>
                        <?php if (
                          $claim['invoice_path'] && $claim['payment_proof'] && $claim['payment_status'] !== 'confirmed'
                        ): ?>
                          <form method="POST" style="display:inline;">
                            <input type="hidden" name="confirm_claim_id" value="<?= $claim['claim_id'] ?>">
                            <input type="hidden" name="payment_type" value="client">
                            <button class="btn btn-success btn-sm">Confirm Payment</button>
                          </form>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        </div>
      </div>

      <!-- SALE CLAIMS: Invoice for Owner -->
      <div class="tab-pane fade" id="sale" role="tabpanel">
        <div class="table-responsive">
        <table class="table table-bordered align-middle">
            <thead class="table-light">
                <tr>
                    <th>Property</th>
                    <th>Owner</th>
                    <th>Invoice</th>
                    <th>Payment Proof</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($saleClaims)): ?>
                  <tr><td colspan="6" class="text-center text-muted">No reserved sale properties found.</td></tr>
                <?php else: ?>
                  <?php foreach ($saleClaims as $claim): ?>
                    <tr>
                      <td>
                        <strong><?= htmlspecialchars($claim['property_name']) ?></strong><br>
                        <small><?= htmlspecialchars($claim['location']) ?></small>
                      </td>
                      <td><?= htmlspecialchars($claim['owner_name']) ?></td>
                      <td>
                        <?php if ($claim['invoice_path']): ?>
                          <a href="<?= $claim['invoice_path'] ?>" target="_blank" class="btn btn-sm btn-outline-primary">View Invoice</a>
                        <?php else: ?>
                          <a href="edit-invoice.php?request_id=<?= $claim['claim_id'] ?>&type=owner" class="btn btn-sm custom-btn">Edit/Issue Invoice</a>
                        <?php endif; ?>
                      </td>
                      <td>
                        <?php if ($claim['payment_proof']): ?>
                          <a href="<?= $claim['payment_proof'] ?>" target="_blank">View</a>
                        <?php else: ?>
                          <span class="text-muted">Waiting for proof</span>
                        <?php endif; ?>
                      </td>
                      <td>
                        <?php
                          if (!$claim['invoice_path']) echo '<span class="badge bg-secondary status-badge">Not Issued</span>';
                          elseif (!$claim['payment_proof']) echo '<span class="badge bg-warning status-badge">Awaiting Payment</span>';
                          elseif ($claim['payment_status'] === 'confirmed') echo '<span class="badge bg-success status-badge">Confirmed</span>';
                          else echo '<span class="badge bg-info status-badge">Awaiting Confirmation</span>';
                        ?>
                      </td>
                      <td>
                        <?php if (
                          $claim['invoice_path'] && $claim['payment_proof'] && $claim['payment_status'] !== 'confirmed'
                        ): ?>
                          <form method="POST" style="display:inline;">
                            <input type="hidden" name="confirm_claim_id" value="<?= $claim['claim_id'] ?>">
                            <input type="hidden" name="payment_type" value="owner">
                            <button class="btn btn-success btn-sm">Confirm Payment</button>
                          </form>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        </div>
      </div>

    </div> <!-- /.tab-content -->
    <a href="confirm-claim-payment.php" class="btn btn-dark fw-bold mt-4">🡰 Back to previous page</a>
</div>

<?php include 'footer.php'; ?>

<script>
// --- Remember active tab across reloads ---
document.addEventListener("DOMContentLoaded", function() {
    // Set last active tab on click
    document.querySelectorAll('button[data-bs-toggle="tab"]').forEach(function(tabBtn) {
        tabBtn.addEventListener('shown.bs.tab', function (e) {
            localStorage.setItem('active-claim-tab', e.target.id);
        });
    });

    // Restore last tab (if any)
    var lastTab = localStorage.getItem('active-claim-tab');
    if (lastTab) {
        var trigger = document.getElementById(lastTab);
        if (trigger) {
            var tab = new bootstrap.Tab(trigger);
            tab.show();
        }
    }
});
</script>

<!-- Script to close main navbar on small screen-->
<script src="navbar-close.js?v=1"></script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
