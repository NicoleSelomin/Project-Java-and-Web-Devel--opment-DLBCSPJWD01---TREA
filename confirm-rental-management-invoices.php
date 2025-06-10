<!-- confirm-rental-management-invoices.php-->

<?php
/**
 * Confirm Rental Management Recurring Invoices (Accountant)
 *
 * - Accountants view/manage all recurring rent invoices from the rental management workflow.
 * - Accountants can:
 *     - Confirm recurring rent payments.
 *     - Send late-payment/warning messages.
 *     - Set and update recurring invoice settings per contract.
 * - Shows per-claim summary: client, property, contract, invoices, warnings.
 *
 * Dependencies:
 * - db_connect.php: Database connection.
 * - header.php/footer.php: Layout.
 * - Bootstrap 5.3: Responsive UI.
 */

session_start();
require 'db_connect.php';

// -----------------------------------------------------------------------------
// 1. ACCESS CONTROL: Only allow accountants
// -----------------------------------------------------------------------------
if (!isset($_SESSION['staff_id']) || strtolower($_SESSION['role']) !== 'accountant') {
    // Redirect unauthorized users to staff login
    header("Location: staff-login.php");
    exit();
}

$staff_id = $_SESSION['staff_id'];

// -----------------------------------------------------------------------------
// 2. HANDLE WARNING SUBMISSION (Late/Other Notices)
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['claim_id'], $_POST['warning_type'])) {
    $claim_id = intval($_POST['claim_id']);
    $warning_type = trim($_POST['warning_type']);           // E.g. "late", "reminder"
    $message = trim($_POST['message'] ?? '');               // Optional custom message

    // Insert a warning record for this claim
    $insert = $pdo->prepare("INSERT INTO rent_warnings (claim_id, warning_type, message, notified_by, sent_at) VALUES (?, ?, ?, ?, NOW())");
    $insert->execute([$claim_id, $warning_type, $message, $staff_id]);

    $_SESSION['message'] = "Warning sent successfully.";
    $_SESSION['message_type'] = "warning";
    header("Location: confirm-rental-management-invoices.php");
    exit();
}

// -----------------------------------------------------------------------------
// 3. HANDLE RENT PAYMENT CONFIRMATION (Mark Invoice as Confirmed)
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['invoice_id'])) {
    $invoice_id = intval($_POST['invoice_id']);

    // Fetch the invoice for validation
    $stmt = $pdo->prepare("SELECT * FROM rental_recurring_invoices WHERE invoice_id = ?");
    $stmt->execute([$invoice_id]);
    $invoice = $stmt->fetch();

    if (!$invoice) {
        // Invalid invoice
        $_SESSION['message'] = "Invoice not found.";
        $_SESSION['message_type'] = "danger";
    } elseif (empty($invoice['payment_proof'])) {
        // Cannot confirm with no proof uploaded
        $_SESSION['message'] = "Cannot confirm: payment proof missing.";
        $_SESSION['message_type'] = "danger";
    } elseif ($invoice['payment_status'] === 'confirmed') {
        // Already confirmed
        $_SESSION['message'] = "Already confirmed.";
        $_SESSION['message_type'] = "info";
    } else {
        // Update DB: mark invoice as confirmed
        $update = $pdo->prepare("UPDATE rental_recurring_invoices SET payment_status = 'confirmed' WHERE invoice_id = ?");
        $update->execute([$invoice_id]);

        $_SESSION['message'] = "Rent payment confirmed.";
        $_SESSION['message_type'] = "success";
    }

    header("Location: confirm-rental-management-invoices.php");
    exit();
}

// -----------------------------------------------------------------------------
// 4. FETCH DATA: All claims + recurring invoices + related contract & warnings
// -----------------------------------------------------------------------------

$sql = "
SELECT ri.*, cc.client_id, u.full_name AS client_name, p.property_name, p.location, p.image,
       rc.contract_signed_path, rc.contract_start_date, rc.contract_end_date, rc.actual_end_date,
       rc.renewed_contract_path, rc.renewed_contract_end_date, rc.claim_id AS rc_claim_id
FROM rental_recurring_invoices ri
JOIN client_claims cc ON ri.claim_id = cc.claim_id
JOIN clients c ON cc.client_id = c.client_id
JOIN users u ON c.user_id = u.user_id
JOIN properties p ON cc.property_id = p.property_id
LEFT JOIN rental_contracts rc ON cc.claim_id = rc.claim_id
WHERE cc.claim_type = 'rent' AND cc.claim_source = 'rental_property_management'
ORDER BY ri.invoice_date DESC
";
$stmt = $pdo->query($sql);
$invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group invoices by claim_id for per-contract display
$grouped = [];
foreach ($invoices as $inv) {
    $grouped[$inv['claim_id']]['contract'] = $inv;
    $grouped[$inv['claim_id']]['invoices'][] = $inv;
}

// For each contract, fetch any warnings (late notices etc)
$warningStmt = $pdo->prepare("SELECT * FROM rent_warnings WHERE claim_id = ? ORDER BY sent_at DESC");
foreach ($grouped as $claim_id => &$entry) {
    $warningStmt->execute([$claim_id]);
    $entry['warnings'] = $warningStmt->fetchAll(PDO::FETCH_ASSOC);
}
unset($entry); // Prevent accidental reference in future loops
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Recurring Rent Invoices</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-4Q6Gf2aSP4eDXB8Miphtr37CMZZQ5oXLH2yaXMJ2w8e2ZtHTl7GptT4jmndRuHDT" crossorigin="anonymous">
    <!-- App Styles -->
    <link rel="stylesheet" href="styles.css?v=<?= time() ?>">
</head>
<body class="d-flex flex-column min-vh-100">
<?php include 'header.php'; ?>

<div class="container-fluid d-flex flex-grow-1 flex-column p-0">
<div class="row flex-grow-1 g-0">
<main class="container py-5">
    <h2 class="mb-4 text-primary">Recurring Rent Invoice Management</h2>

    <!-- Flash message: confirmation, error, or warning -->
    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?= $_SESSION['message_type'] ?? 'info' ?>">
            <?= $_SESSION['message']; unset($_SESSION['message'], $_SESSION['message_type']); ?>
        </div>
    <?php endif; ?>

    <!-- Main Loop: One contract section per claim -->
    <?php foreach ($grouped as $claim_id => $data): $info = $data['contract']; ?>
    <div class="card mb-4">
        <div class="card-header bg-light">
            <strong><?= htmlspecialchars($info['property_name']) ?></strong> - <?= htmlspecialchars($info['location']) ?>
        </div>
        <div class="card-body">
            <p><strong>Client:</strong> <?= htmlspecialchars($info['client_name']) ?></p>
            <p><strong>Contract:</strong>
                <?php if ($info['contract_signed_path']): ?>
                    <a href="<?= $info['contract_signed_path'] ?>" target="_blank">View</a>
                <?php else: ?>
                    <span class="text-muted">Not available</span>
                <?php endif; ?>
            </p>

            <!-- If contract exists but recurring not set, show settings form -->
            <?php if ($info['contract_signed_path'] && !$info['invoice_date']): ?>
                <h6 class="mt-3">Set Recurring Invoice Settings</h6>
                <form method="POST" action="set-rent-invoice-settings.php" class="row g-2 align-items-end">
                    <input type="hidden" name="claim_id" value="<?= $claim_id ?>">
                    <div class="col-md-3">
                        <label class="form-label">Start Date</label>
                        <input type="date" name="invoice_date" class="form-control" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Frequency</label>
                        <select name="payment_frequency" class="form-select" required>
                            <option value="">Choose</option>
                            <option value="daily">Daily</option>
                            <option value="monthly">Monthly</option>
                            <option value="quarterly">Quarterly</option>
                            <option value="yearly">Yearly</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Due Days</label>
                        <input type="number" name="due_date" class="form-control" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Penalty %</label>
                        <input type="number" name="penalty_rate" class="form-control" step="0.01" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Amount</label>
                        <input type="number" name="rent_fee" class="form-control" step="0.01" required>
                    </div>
                    <div class="col-md-1">
                        <button class="btn btn-sm btn-primary w-100">Save</button>
                    </div>
                </form>
            <?php elseif ($info['invoice_date']): ?>
                <!-- If recurring is set, display info and show edit option -->
                <div class="alert alert-info mt-3">
                    Recurring invoices started from <?= $info['invoice_date'] ?> (<?= ucfirst($info['payment_frequency']) ?>).
                    <?php if ($info['actual_end_date']): ?>
                        <br><strong class="text-danger">Contract ended on <?= $info['actual_end_date'] ?></strong>
                    <?php endif; ?>
                    <button class="btn btn-sm btn-outline-primary mb-2" data-bs-toggle="collapse" data-bs-target="#editRecurring<?= $claim_id ?>">
                        Edit Recurring Invoice
                    </button>
                    <div class="collapse" id="editRecurring<?= $claim_id ?>">
                        <form method="POST" action="edit-rent-invoice-settings.php" class="row g-2 align-items-end border p-3 rounded bg-light">
                            <input type="hidden" name="claim_id" value="<?= $claim_id ?>">
                            <div class="col-md-3">
                                <label class="form-label">Start Date</label>
                                <input type="date" name="invoice_date" class="form-control" value="<?= $info['invoice_date'] ?>" required>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Frequency</label>
                                <select name="payment_frequency" class="form-select" required>
                                    <option value="">Choose</option>
                                    <option value="daily" <?= $info['payment_frequency'] === 'daily' ? 'selected' : '' ?>>Daily</option>
                                    <option value="monthly" <?= $info['payment_frequency'] === 'monthly' ? 'selected' : '' ?>>Monthly</option>
                                    <option value="quarterly" <?= $info['payment_frequency'] === 'quarterly' ? 'selected' : '' ?>>Quarterly</option>
                                    <option value="yearly" <?= $info['payment_frequency'] === 'yearly' ? 'selected' : '' ?>>Yearly</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Due Days</label>
                                <input type="number" name="due_date" class="form-control" value="<?= $info['due_date'] ?>" required>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Penalty %</label>
                                <input type="number" step="0.01" name="penalty_rate" class="form-control" value="<?= $info['penalty_rate'] ?>" required>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Amount</label>
                                <input type="number" step="0.01" name="amount" class="form-control" value="<?= $info['amount'] ?>" required>
                            </div>
                            <div class="col-md-1">
                                <button class="btn btn-sm btn-success w-100">Update</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Additional: Insert recurring invoice table, warnings UI, etc., as in your view file -->

        </div>
    </div>
    <?php endforeach; ?>

    <a href="staff-profile.php" class="btn btn-secondary mt-4">← Back to Dashboard</a>
</main>
</div>
</div>
<?php include 'footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>
