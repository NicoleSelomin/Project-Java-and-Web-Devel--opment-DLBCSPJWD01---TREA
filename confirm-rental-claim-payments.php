<?php
/**
 * confirm-rental-claim-payments.php (Accountant)
 *
 * Accountants use this page to:
 *  - Upload claim/deposit invoices to the system.
 *  - Confirm rental claim/deposit payments once both invoice and payment proof are uploaded.
 *  - Ensure related contract and property status are updated after confirmation.
 *  - See a summary of all relevant claims.
 *
 * Features:
 *  - Handles both invoice upload and payment confirmation logic via POST.
 *  - Only accessible to users with the 'accountant' staff role.
 *  - Shows claim and deposit payments from rental property management workflow.
 *
 * Dependencies:
 *  - db_connect.php (PDO connection)
 *  - header.php/footer.php (UI structure)
 *  - views/confirm-rental-claim-payments.view.php (table rendering)
 */

session_start();
require 'db_connect.php';

// -----------------------------------------------------------------------------
// 1. ACCESS CONTROL: Only allow accountants
// -----------------------------------------------------------------------------
if (!isset($_SESSION['staff_id']) || !in_array(strtolower($_SESSION['role']), ['accountant', 'general manager'])) {
    header("Location: staff-login.php");
    exit();
}

$staff_id = $_SESSION['staff_id'];

// -----------------------------------------------------------------------------
// 2. HANDLE INVOICE FILE UPLOAD
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['invoice_file'], $_POST['payment_id'])) {
    $payment_id = intval($_POST['payment_id']);

    // Validate that the payment exists
    $check = $pdo->prepare("SELECT payment_id FROM rental_claim_payments WHERE payment_id = ?");
    $check->execute([$payment_id]);
    if (!$check->fetch()) {
        // If not, set error message and redirect
        $_SESSION['message'] = "Invalid payment ID.";
        $_SESSION['message_type'] = "danger";
        header("Location: confirm-rental-claim-payments.php");
        exit();
    }

    // Set up upload directory and ensure it exists
    $uploadDir = 'uploads/invoices/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0775, true);
    }

    // Sanitize and generate a unique file name for the invoice
    $fileName = time() . '_' . preg_replace("/[^A-Za-z0-9_\.-]/", "_", basename($_FILES['invoice_file']['name']));
    $targetPath = $uploadDir . $fileName;

    // Move uploaded invoice to the target directory and update DB
    if (move_uploaded_file($_FILES['invoice_file']['tmp_name'], $targetPath)) {
        $stmt = $pdo->prepare("UPDATE rental_claim_payments SET invoice_path = ? WHERE payment_id = ?");
        $stmt->execute([$targetPath, $payment_id]);
        $_SESSION['message'] = "Invoice uploaded successfully.";
        $_SESSION['message_type'] = "success";
    } else {
        $_SESSION['message'] = "Failed to upload invoice.";
        $_SESSION['message_type'] = "danger";
    }
    // Always redirect after POST (PRG pattern)
    header("Location: confirm-rental-claim-payments.php");
    exit();
}

// -----------------------------------------------------------------------------
// 3. HANDLE PAYMENT CONFIRMATION
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['payment_id']) && !isset($_FILES['invoice_file'])) {
    $payment_id = intval($_POST['payment_id']);

    // Fetch invoice and proof paths and status for validation
    $stmt = $pdo->prepare("SELECT invoice_path, payment_proof, payment_status FROM rental_claim_payments WHERE payment_id = ?");
    $stmt->execute([$payment_id]);
    $payment = $stmt->fetch();

    if (!$payment) {
        // Payment record missing
        $_SESSION['message'] = "Payment record not found.";
        $_SESSION['message_type'] = "danger";
    } elseif (empty($payment['invoice_path']) || empty($payment['payment_proof'])) {
        // Cannot confirm if invoice or proof is missing
        $_SESSION['message'] = "Cannot confirm: invoice or payment proof missing.";
        $_SESSION['message_type'] = "danger";
    } elseif ($payment['payment_status'] === 'confirmed') {
        // Already confirmed, nothing to do
        $_SESSION['message'] = "Payment already confirmed.";
        $_SESSION['message_type'] = "info";
    } else {
        // Confirm payment and record staff and time
        $update = $pdo->prepare("UPDATE rental_claim_payments SET payment_status = 'confirmed', confirmed_by = ?, confirmed_at = NOW() WHERE payment_id = ?");
        $update->execute([$staff_id, $payment_id]);

        // Check if this is a 'claim' type payment (not a deposit)
        $claimQuery = $pdo->prepare("
            SELECT rcp.claim_id, rcp.payment_type, cc.property_id
            FROM rental_claim_payments rcp
            JOIN client_claims cc ON rcp.claim_id = cc.claim_id
            WHERE rcp.payment_id = ?
        ");
        $claimQuery->execute([$payment_id]);
        $claimData = $claimQuery->fetch(PDO::FETCH_ASSOC);

        // If it's a claim payment, ensure rental_contract exists, and mark property unavailable
        if ($claimData && $claimData['payment_type'] === 'claim') {
            // Ensure a rental_contract record exists for this claim
            $check = $pdo->prepare("SELECT 1 FROM rental_contracts WHERE claim_id = ?");
            $check->execute([$claimData['claim_id']]);
            if (!$check->fetch()) {
                $insert = $pdo->prepare("INSERT INTO rental_contracts (claim_id, contract_status) VALUES (?, 'pending')");
                $insert->execute([$claimData['claim_id']]);
            }
            // Set property to 'unavailable' after claim payment is confirmed
            $updateProp = $pdo->prepare("UPDATE properties SET availability = 'unavailable' WHERE property_id = ?");
            $updateProp->execute([$claimData['property_id']]);
        }

        $_SESSION['message'] = "Payment confirmed successfully.";
        $_SESSION['message_type'] = "success";
    }

    // Always redirect after POST
    header("Location: confirm-rental-claim-payments.php");
    exit();
}

// -----------------------------------------------------------------------------
// 4. FETCH CLAIM & DEPOSIT PAYMENTS FOR LISTING
// -----------------------------------------------------------------------------

/*
 * Pulls all 'rent' type, 'rental_property_management' source claims and their associated claim/deposit payments.
 * Also fetches related contract and property details for display.
 */
$sql = "
SELECT cc.claim_id, cc.client_id, cc.property_id, cc.claimed_at, cc.claim_source, cc.claim_type,
       u.full_name AS client_name, p.property_name, p.location, p.image,
       rc.contract_signed_path, rc.contract_start_date, rc.contract_end_date,
       rc.actual_end_date,
       cc.meeting_report_path,
       rcp.payment_id, rcp.payment_type, rcp.invoice_path, rcp.payment_proof,
       rcp.payment_status, rcp.confirmed_by, rcp.confirmed_at
FROM client_claims cc
JOIN clients c ON cc.client_id = c.client_id
JOIN users u ON c.user_id = u.user_id
JOIN properties p ON cc.property_id = p.property_id
LEFT JOIN rental_contracts rc ON cc.claim_id = rc.claim_id
LEFT JOIN rental_claim_payments rcp 
    ON cc.claim_id = rcp.claim_id AND rcp.payment_type IN ('claim', 'deposit')
WHERE cc.claim_type = 'rent' AND cc.claim_source = 'rental_property_management'
ORDER BY cc.claimed_at DESC, rcp.payment_type ASC
";
$stmt = $pdo->query($sql);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Restructure rows by claim for grouped display (info + all payments)
$claims = [];
foreach ($rows as $row) {
    $claims[$row['claim_id']]['info'] = $row;
    $claims[$row['claim_id']]['payments'][] = $row;
}

// Fetch initial inspection report status for each claim
$reportStmt = $pdo->prepare("
    SELECT status, pdf_path FROM inspection_reports
    WHERE claim_id = ? AND inspection_type = 'initial'
    LIMIT 1
");
foreach ($claims as $claim_id => &$claimRow) {
    $reportStmt->execute([$claim_id]);
    $report = $reportStmt->fetch(PDO::FETCH_ASSOC);
    $claimRow['initial_report_status'] = $report['status'] ?? null;
    $claimRow['initial_report_pdf'] = $report['pdf_path'] ?? null;
}
unset($claimRow);
?>

<!DOCTYPE html>
<html> 
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Confirm Reservation Payments</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="styles.css?v=<?= time() ?>">
</head>
<body class="bg-light d-flex flex-column min-vh-100">
<?php include 'header.php'; ?>

<div class="container-fluid d-flex flex-grow-1 flex-column p-0">
  <div class="row flex-grow-1 g-0">
    <main class="container py-5">
      <h2 class="mb-4 text-primary">Confirm Reservation & Deposit Payments</h2>

      <!-- Table and forms rendered in a separate view for maintainability -->
      <?php include 'views/confirm-rental-claim-payments.view.php'; ?>

      <p class="mt-4">
        <a href="confirm-claim-payment.php" class="btn btn-dark fw-bold mt-4">🡰 Back to previous page</a>
      </p>
    </main>
  </div> 
</div>

<?php include 'footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>

<!-- Script to close main navbar on small screen-->
<script src="navbar-close.js?v=1"></script>

</body>
</html>
