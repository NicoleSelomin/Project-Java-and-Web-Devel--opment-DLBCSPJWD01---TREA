<?php
/**
 * ---------------------------------------------------------------------------------
 * confirm-application-payment.php
 * ---------------------------------------------------------------------------------
 * 
 * Confirm Application Fee Payments (Accountant)
 *
 * This script allows accountants to manage and confirm application fee payments for owner service requests.
 * Accountants can:
 * - Upload invoices for the application fee (PDF, JPG, PNG)
 * - View payment proofs uploaded by owners
 * - Confirm the payment once both invoice and proof exist
 * - See all owner service requests requiring fee confirmation
 *
 * Dependencies:
 * - db_connect.php: PDO connection to the database
 * - Bootstrap 5.3: for responsive layout and styling
 * - Session and access control: Only accessible to staff with the 'accountant' role
 * 
 * _______________________________________________________________________________________________________
 */

session_start();
require 'db_connect.php';

// ---------- Access Control (Staff Role) ----------

// Ensure only logged-in accountants and general manager can access this page
if (!isset($_SESSION['staff_id']) || !in_array(strtolower($_SESSION['role']), ['accountant', 'general manager'])) {
    header("Location: staff-login.php");
    exit();
}

// Retrieve accountant's profile info from session
$fullName = $_SESSION['full_name'] ?? 'Staff';
$userId = $_SESSION['staff_id'] ?? '';

// ---------- Handle Invoice Upload and Payment Confirmation ----------

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'])) {
    $requestId = $_POST['request_id'];
    $staffId = $_SESSION['staff_id']; // Who is confirming

    // --- Invoice Upload ---
    if (isset($_FILES['invoice_file']) && $_FILES['invoice_file']['error'] === UPLOAD_ERR_OK) {
        // Fetch necessary info to construct the target upload directory for invoice
        $infoStmt = $pdo->prepare("
            SELECT r.owner_id, u.full_name, s.service_id, s.slug
            FROM owner_service_requests r
            JOIN owners o ON r.owner_id = o.owner_id
            JOIN users u ON o.user_id = u.user_id
            JOIN services s ON r.service_id = s.service_id
            WHERE r.request_id = ?
        ");
        $infoStmt->execute([$requestId]);
        $info = $infoStmt->fetch();

        if (!$info) {
            // Fail-safe: If the service request is invalid, abort operation
            die("Invalid request data");
        }

        // Sanitize owner name for filesystem safety
        $ownerFolder = $info['owner_id'] . '_' . preg_replace('/[^a-z0-9_]/i', '_', $info['full_name']);
        // Create a service-specific subfolder
        $serviceFolder = $info['service_id'] . '_' . $info['slug'];
        // Directory for this particular request
        $targetDir = "uploads/owner/{$ownerFolder}/applications/{$serviceFolder}/request_{$requestId}/";
        if (!is_dir($targetDir)) {
            // Recursively create the directory if it doesn't exist
            mkdir($targetDir, 0777, true);
        }

        // Save the invoice file using a consistent name and preserve file extension
        $ext = pathinfo($_FILES['invoice_file']['name'], PATHINFO_EXTENSION);
        $invoicePath = $targetDir . 'invoice.' . $ext;
        move_uploaded_file($_FILES['invoice_file']['tmp_name'], $invoicePath);

        // Update the payment record with invoice path and timestamp
        $update = $pdo->prepare("
            UPDATE service_request_payments 
            SET invoice_path = ?, updated_at = NOW() 
            WHERE request_id = ? AND payment_type = 'application'
        ");
        $update->execute([$invoicePath, $requestId]);

        // Flash message for user feedback
        $_SESSION['confirmation_success'] = "Invoice uploaded successfully.";
    }
    // --- Payment Confirmation ---
    elseif (isset($_POST['confirm_only'])) {
        // Confirm the application payment by updating status and logging confirmer
        $update = $pdo->prepare("
            UPDATE service_request_payments 
            SET payment_status = 'confirmed', confirmed_at = NOW(), confirmed_by = ? 
            WHERE request_id = ? AND payment_type = 'application'
        ");
        $update->execute([$staffId, $requestId]);
        $_SESSION['confirmation_success'] = "Payment confirmed.";
    }

    // Always redirect to prevent form resubmission and preserve feedback message
    header("Location: confirm-application-payment.php");
    exit();
}

// ---------- Retrieve All Service Requests with Payment Status ----------

// Select all owner service requests along with invoice/payment status for display in table
$invoiceUploads = $pdo->query("
    SELECT 
        r.request_id, r.property_name, r.location, r.submitted_at,
        s.service_name, s.slug,
        u.full_name AS owner_name, 
        p.invoice_path, p.payment_proof, p.payment_status
    FROM owner_service_requests r
    JOIN owners o ON r.owner_id = o.owner_id
    JOIN users u ON o.user_id = u.user_id
    JOIN services s ON r.service_id = s.service_id
    JOIN service_request_payments p ON r.request_id = p.request_id AND p.payment_type = 'application'
    ORDER BY r.submitted_at DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Confirm Application Fees</title>
  <!-- Bootstrap CSS for responsive design -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-4Q6Gf2aSP4eDXB8Miphtr37CMZZQ5oXLH2yaXMJ2w8e2ZtHTl7GptT4jmndRuHDT" crossorigin="anonymous">
  <!-- Custom stylesheet (cache busting with version) -->
  <link rel="stylesheet" href="styles.css?v=<?= time() ?>">
</head>
<body class="d-flex flex-column min-vh-100 bg-light">
<?php include 'header.php'; ?>

<div class="container-fluid">
  <div class="row">

    <!-- Main Content Area -->
    <main class="col-12 col-md-11 ms-lg-5">
      <div class="mb-4 p-3 border rounded shadow-sm main-title">
        <h2>Confirm Application Fee Payments</h2>
      </div>

      <?php if (isset($_SESSION['confirmation_success'])): ?>
        <div class="alert alert-success"><?= $_SESSION['confirmation_success'] ?></div>
        <?php unset($_SESSION['confirmation_success']); ?>
      <?php endif; ?>

      <div class="table-responsive">
        <table class="table table-bordered table-striped align-middle">
          <thead class="table-dark">
            <tr>
              <th>Owner</th>
              <th>Service</th>
              <th>Property</th>
              <th>Location</th>
              <th>Application Form</th>
              <th>Submitted</th>
              <th>Invoice</th>
              <th>Payment Proof</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($invoiceUploads): ?>
              <?php foreach ($invoiceUploads as $row): ?>
                <tr>
                  <td><?= htmlspecialchars($row['owner_name']) ?></td>
                  <td><?= htmlspecialchars($row['service_name']) ?> <span class="text-muted small">(<?= htmlspecialchars($row['slug']) ?>)</span></td>
                  <td><?= htmlspecialchars($row['property_name']) ?></td>
                  <td><?= htmlspecialchars($row['location']) ?></td>
                  <td><a href="generate-application-pdf.php?id=<?= ($row['request_id']) ?>" target="_blank">PDF</a></td>
                  <td><?= date('Y-m-d H:i', strtotime($row['submitted_at'])) ?></td>

                  <!-- INVOICE COLUMN -->
                  <td>
                    <?php if (empty($row['invoice_path'])): ?>
                      <!-- No invoice generated yet: link to edit-invoice -->
                      <a href="edit-invoice.php?request_id=<?= $row['request_id'] ?>&type=service" class="btn btn-sm custom-btn">Generate Invoice</a>
                    <?php else: ?>
                      <!-- Invoice exists: view/download -->
                       <div class="d-flex">
                      <a href="<?= htmlspecialchars($row['invoice_path']) ?>" target="_blank" class="btn btn-sm custom-btn">View</a>
                      <br>
                      <a href="edit-invoice.php?request_id=<?= $row['request_id'] ?>&type=service" class="btn btn-sm btn-outline-danger ms-2">Edit</a>
                    </div>
                    <?php endif; ?>
                  </td>

                  <!-- PAYMENT PROOF -->
                  <td>
                    <?php if (!empty($row['payment_proof'])): ?>
                      <a href="<?= htmlspecialchars($row['payment_proof']) ?>" target="_blank" class="btn btn-sm custom-btn">View</a>
                    <?php else: ?>
                      <span class="text-muted">Awaiting proof</span>
                    <?php endif; ?>
                  </td>

                  <!-- ACTION (Confirm) -->
                  <td>
                    <?php if (
                      !empty($row['invoice_path']) &&
                      !empty($row['payment_proof']) &&
                      $row['payment_status'] !== 'confirmed'
                    ): ?>
                      <form method="POST">
                        <input type="hidden" name="request_id" value="<?= $row['request_id'] ?>">
                        <input type="hidden" name="confirm_only" value="1">
                        <button class="btn btn-sm btn-success">Confirm</button>
                      </form>
                    <?php elseif ($row['payment_status'] === 'confirmed'): ?>
                      <span class="badge bg-success">Confirmed</span>
                    <?php else: ?>
                      <span class="text-muted">Waiting</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="8" class="text-muted text-center">No applications found.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

    <a href="staff-profile.php" class="btn bg-dark text-white fw-bold">🡰 Back to dashboard</a>

    </main>
  </div>
</div>

<?php include 'footer.php'; ?>

<!-- Bootstrap JS (required for responsive features and sidebar toggling) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js" integrity="sha384-j1CDi7MgGQ12Z7Qab0qlWQ/Qqz24Gc6BM0thvEMVjHnfYGF0rmFCozFSxQBxwHKO" crossorigin="anonymous"></script>

<!-- Script to close main navbar on small screen-->
<script src="navbar-close.js?v=1"></script>

</body>
</html>
