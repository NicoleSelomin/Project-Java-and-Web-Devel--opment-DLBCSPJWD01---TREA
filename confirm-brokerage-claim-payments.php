<?php
/**
 * ----------------------------------------------------------------------------
 * confirm-brokerage-claim-payments.php
 * ----------------------------------------------------------------------------
 * 
 * Confirm Application Fee Payments (Accountant)
 *
 * Allows accountants to upload invoices for owner service requests and confirm payments.
 * Features:
 * - Upload invoice for application fee (PDF, JPG, PNG)
 * - View payment proof uploaded by owner
 * - Confirm payment if invoice and proof both exist
 * - Lists all owner service requests with relevant info
 *
 * Dependencies:
 * - db_connect.php
 * - Bootstrap 5.3
 * - Standard session and user checks
 * 
 * -------------------------------------------------------------------------------
 */

session_start(); // Start the session for authentication and user data
require 'db_connect.php'; // Include database connection (PDO as $pdo)

// -----------------------------------------------------------------------------
// 1. ACCESS CONTROL AND STAFF INFO
// -----------------------------------------------------------------------------

// Restrict page access to logged-in staff with role 'accountant'
if (!isset($_SESSION['staff_id']) || strtolower($_SESSION['role']) !== 'accountant') {
    // Redirect to login if not authorized
    header("Location: staff-login.php");
    exit();
}

// Get current accountant's name, ID, and profile picture from session (with fallbacks)
$fullName = $_SESSION['full_name'] ?? 'Staff';
$userId = $_SESSION['staff_id'] ?? '';
$profilePicture = $_SESSION['profile_picture_path'] ?? 'default.png';

// -----------------------------------------------------------------------------
// 2. HANDLE POST REQUESTS: INVOICE UPLOAD AND PAYMENT CONFIRMATION
// -----------------------------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'])) {
    // The service request we are working with
    $requestId = $_POST['request_id'];
    $staffId = $_SESSION['staff_id']; // The accountant's ID for tracking who confirmed

    // -------------------------------------------------------------
    // 2.1. HANDLE INVOICE FILE UPLOAD
    // -------------------------------------------------------------
    if (isset($_FILES['invoice_file']) && $_FILES['invoice_file']['error'] === UPLOAD_ERR_OK) {
        // Fetch owner and service info for folder structure
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

        // If the request_id is invalid, halt and notify
        if (!$info) {
            die("Invalid request data");
        }

        // Build directory path for saving the invoice
        // Clean up the owner full name to ensure a safe folder name
        $ownerFolder = $info['owner_id'] . '_' . preg_replace('/[^a-z0-9_]/i', '_', $info['full_name']);
        // Service folder uses service ID and slug for clarity and uniqueness
        $serviceFolder = $info['service_id'] . '_' . $info['slug'];
        // Each request gets its own folder
        $targetDir = "uploads/owner/{$ownerFolder}/applications/{$serviceFolder}/request_{$requestId}/";

        // Create the directory recursively if it does not exist
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        // Determine the file extension and create a standard filename
        $ext = pathinfo($_FILES['invoice_file']['name'], PATHINFO_EXTENSION);
        $invoicePath = $targetDir . 'invoice.' . $ext;
        // Move the uploaded file from temporary location to the target directory
        move_uploaded_file($_FILES['invoice_file']['tmp_name'], $invoicePath);

        // Update the payment record in the database with the new invoice path
        $update = $pdo->prepare("UPDATE service_request_payments 
            SET invoice_path = ?, updated_at = NOW() 
            WHERE request_id = ? AND payment_type = 'application'");
        $update->execute([$invoicePath, $requestId]);

        // Store a success message in the session for display after redirect
        $_SESSION['confirmation_success'] = "Invoice uploaded successfully.";
    }
    // -------------------------------------------------------------
    // 2.2. HANDLE PAYMENT CONFIRMATION (AFTER INVOICE AND PROOF)
    // -------------------------------------------------------------
    elseif (isset($_POST['confirm_only'])) {
        // Set payment status as 'confirmed', record who and when
        $update = $pdo->prepare("UPDATE service_request_payments 
            SET payment_status = 'confirmed', confirmed_at = NOW(), confirmed_by = ? 
            WHERE request_id = ? AND payment_type = 'application'");
        $update->execute([$staffId, $requestId]);
        // Success message for user feedback
        $_SESSION['confirmation_success'] = "Payment confirmed.";
    }

    // Always redirect after POST to avoid double submissions (PRG pattern)
    header("Location: confirm-application-payment.php");
    exit();
}

// -----------------------------------------------------------------------------
// 3. FETCH ALL OWNER SERVICE REQUESTS AND PAYMENT STATUS FOR DISPLAY
// -----------------------------------------------------------------------------

// Get a list of all owner service requests and their application payment status
$invoiceUploads = $pdo->query("
    SELECT 
        r.request_id, r.property_name, r.location, 
        s.service_name, u.full_name AS owner_name, 
        r.submitted_at,
        p.invoice_path, p.payment_proof, p.payment_status
    FROM owner_service_requests r
    JOIN owners o ON r.owner_id = o.owner_id
    JOIN users u ON o.user_id = u.user_id
    JOIN services s ON r.service_id = s.service_id
    JOIN service_request_payments p ON r.request_id = p.request_id AND p.payment_type = 'application'
    ORDER BY r.submitted_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Use default placeholder if profile picture file is missing or not set
$profilePicturePath = (!empty($profilePicture) && file_exists($profilePicture))
    ? $profilePicture
    : 'default.png';
?>

<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Confirm Application Fees</title>
  <!-- Bootstrap for responsive design -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-4Q6Gf2aSP4eDXB8Miphtr37CMZZQ5oXLH2yaXMJ2w8e2ZtHTl7GptT4jmndRuHDT" crossorigin="anonymous">
  <!-- Local stylesheet, cache-busted per refresh -->
  <link rel="stylesheet" href="styles.css?v=<?= time() ?>">
</head>
<body class="d-flex flex-column min-vh-100 bg-light">
<?php include 'header.php'; ?>

<div class="container-fluid">
  <div class="row">

    <!-- =======================================================================
         SIDEBAR: ACCOUNTANT PROFILE AND NAVIGATION
         ======================================================================= -->
    <div class="col-12 col-md-3 mb-3">
      <!-- Mobile menu toggle -->
      <button class="btn btn-sm d-md-none mb-3 custom-btn" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarCollapse" aria-expanded="false" aria-controls="sidebarCollapse">
        Open Menu
      </button>
      <!-- Sidebar contents (always visible on desktop, collapsible on mobile) -->
      <div class="collapse d-md-block" id="sidebarCollapse">
        <div class="sidebar text-center">
          <!-- Profile info block -->
          <div class="profile-summary text-center">
            <img src="<?= htmlspecialchars($profilePicturePath) ?>" alt="Profile Picture" class="mb-3">
            <p><strong><?= htmlspecialchars($fullName) ?></strong></p>
            <p>ID: <?= htmlspecialchars($userId) ?></p>
            <!-- Navigation shortcuts -->
            <a href="notifications.php" class="btn mt-3 bg-light w-100">View Notifications</a>
            <a href="edit-staff-profile.php" class="btn mt-3 bg-light w-100">Edit Profile</a>
            <a href="staff-logout.php" class="btn text-danger mt-3 d-block bg-light w-100">Logout</a>
          </div>
          <!-- Optional: Embedded Google Calendar -->
          <div>
            <h5 class="mt-5">Calendar</h5>
            <iframe src="https://calendar.google.com/calendar/embed?mode=MONTH" frameborder="0" scrolling="no"></iframe>
          </div>
        </div>
      </div>
    </div>

    <!-- =======================================================================
         MAIN CONTENT AREA
         ======================================================================= -->
    <main class="col-12 col-md-9">
      <!-- Page title / section -->
      <div class="mb-4 p-3 border rounded shadow-sm main-title">
        <h2>Confirm Application Fee Payments</h2>
      </div>

      <!-- Session feedback (success/error messages after POST actions) -->
      <?php if (isset($_SESSION['confirmation_success'])): ?>
        <div class="alert alert-success"><?= $_SESSION['confirmation_success'] ?></div>
        <?php unset($_SESSION['confirmation_success']); ?>
      <?php endif; ?>

      <!-- Section header for invoice upload -->
      <div class="mb-4 p-3 border rounded shadow-sm">
        <h4>Upload Invoices</h4>
      </div>

      <!-- Table listing all service requests that can be actioned -->
      <div class="table-responsive">
        <table class="table table-bordered">
          <thead class="table-light">
            <tr>
              <th>Owner</th>
              <th>Service</th>
              <th>Property</th>
              <th>Location</th>
              <th>Submitted</th>
              <th>Invoice</th>
              <th>Payment Proof</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <!-- Show table rows for each application (if available) -->
            <?php if ($invoiceUploads): ?>
              <?php foreach ($invoiceUploads as $row): ?>
                <tr>
                  <!-- Owner's name (from users table via join) -->
                  <td><?= htmlspecialchars($row['owner_name']) ?></td>
                  <!-- Name of the service being requested -->
                  <td><?= htmlspecialchars($row['service_name']) ?></td>
                  <!-- Property name submitted by owner -->
                  <td><?= htmlspecialchars($row['property_name']) ?></td>
                  <!-- Location information as recorded -->
                  <td><?= htmlspecialchars($row['location']) ?></td>
                  <!-- Date request was submitted -->
                  <td><?= date('Y-m-d', strtotime($row['submitted_at'])) ?></td>
                  <!-- INVOICE UPLOAD/VIEW: Show file upload if not present, otherwise show view link -->
                  <td>
                    <?php if (empty($row['invoice_path'])): ?>
                      <!-- If invoice not uploaded, show upload form -->
                      <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="request_id" value="<?= $row['request_id'] ?>">
                        <input type="file" name="invoice_file" accept=".pdf,.jpg,.jpeg,.png" class="form-control form-control-sm mb-1" required>
                        <button type="submit" class="btn btn-sm btn-primary">Upload</button>
                      </form>
                    <?php else: ?>
                      <!-- If invoice exists, show download/view link -->
                      <a href="<?= htmlspecialchars($row['invoice_path']) ?>" target="_blank">View Invoice</a>
                    <?php endif; ?>
                  </td>
                  <!-- PAYMENT PROOF: Uploaded by owner -->
                  <td>
                    <?php if (!empty($row['payment_proof'])): ?>
                      <a href="<?= htmlspecialchars($row['payment_proof']) ?>" target="_blank">View Proof</a>
                    <?php else: ?>
                      <span class="text-muted">Awaiting proof</span>
                    <?php endif; ?>
                  </td>
                  <!-- ACTION: Show confirm button if ready, badge if confirmed, or waiting otherwise -->
                  <td>
                    <?php if (
                      !empty($row['invoice_path']) &&
                      !empty($row['payment_proof']) &&
                      $row['payment_status'] !== 'confirmed'
                    ): ?>
                      <!-- Show confirmation form if both files uploaded, but not yet confirmed -->
                      <form method="POST">
                        <input type="hidden" name="request_id" value="<?= $row['request_id'] ?>">
                        <input type="hidden" name="confirm_only" value="1">
                        <button class="btn btn-sm btn-success">Confirm</button>
                      </form>
                    <?php elseif ($row['payment_status'] === 'confirmed'): ?>
                      <!-- Already confirmed, show badge -->
                      <span class="badge bg-success">Confirmed</span>
                    <?php else: ?>
                      <!-- Not ready to confirm -->
                      <span class="text-muted">Waiting</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <!-- If no applications found, show a muted placeholder -->
              <tr><td colspan="8" class="text-muted text-center">No applications found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </main>
  </div>
</div>

<?php include 'footer.php'; ?>
<!-- Bootstrap JS (for sidebar and UI) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js" integrity="sha384-j1CDi7MgGQ12Z7Qab0qlWQ/Qqz24Gc6BM0thvEMVjHnfYGF0rmFCozFSxQBxwHKO" crossorigin="anonymous"></script>
</body>
</html>
