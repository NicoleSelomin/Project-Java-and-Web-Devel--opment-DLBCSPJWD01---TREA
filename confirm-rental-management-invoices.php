<?php
/**
 * confirm-rental-management-invoices.php
 * ----------------------------------------------------------
 * Accountant interface to:
 * - View/manage recurring rent invoice per claim
 * - Activate/deactivate recurring invoicing
 * - Confirm payment proofs
 * - Show only latest invoice with statement link
 * ----------------------------------------------------------
 */
session_start();
require 'db_connect.php';

if (!isset($_SESSION['staff_id']) || !in_array(strtolower($_SESSION['role']), ['accountant', 'general manager'])) {
    header("Location: staff-login.php");
    exit();
}

$staff_id = $_SESSION['staff_id'];

// ------------------ RECURRING TOGGLE HANDLER ------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_recurring'], $_POST['claim_id'], $_POST['recurring_status'])) {
    $toggleClaimId = intval($_POST['claim_id']);
    $currentStatus = intval($_POST['recurring_status']);
    $newStatus = $currentStatus ? 0 : 1;
    $pdo->prepare("UPDATE rental_recurring_invoices SET recurring_active = ? WHERE claim_id = ?")->execute([$newStatus, $toggleClaimId]);
    $_SESSION['message'] = $newStatus ? "Recurring invoice activated for claim." : "Recurring invoice deactivated for claim.";
    $_SESSION['message_type'] = "info";
    header("Location: confirm-rental-management-invoices.php");
    exit();
}

// ------------------ PAYMENT CONFIRMATION HANDLER ------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['invoice_id'])) {
    $invoice_id = intval($_POST['invoice_id']);
    $stmt = $pdo->prepare("SELECT * FROM rental_recurring_invoices WHERE invoice_id = ?");
    $stmt->execute([$invoice_id]);
    $invoice = $stmt->fetch();

    if (!$invoice) {
        $_SESSION['message'] = "Invoice not found.";
        $_SESSION['message_type'] = "danger";
    } elseif (empty($invoice['payment_proof'])) {
        $_SESSION['message'] = "Cannot confirm: payment proof missing.";
        $_SESSION['message_type'] = "danger";
    } elseif ($invoice['payment_status'] === 'confirmed') {
        $_SESSION['message'] = "Already confirmed.";
        $_SESSION['message_type'] = "info";
    } else {
        $pdo->prepare("UPDATE rental_recurring_invoices SET payment_status = 'confirmed', confirmed_by = ?, confirmed_at = NOW() WHERE invoice_id = ?")
            ->execute([$staff_id, $invoice_id]);
        $_SESSION['message'] = "Rent payment confirmed.";
        $_SESSION['message_type'] = "success";
    }
    header("Location: confirm-rental-management-invoices.php");
    exit();
}

// ------------------ INVOICE GENERATION HANDLER ------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_invoices'], $_POST['claim_id'], $_POST['start_date'])) {
    $claimId = intval($_POST['claim_id']);
    $startDate = $_POST['start_date'];

    $stmt = $pdo->prepare("SELECT contract_start_date, contract_end_date, amount, payment_frequency, penalty_rate, grace_period_days FROM rental_contracts WHERE claim_id = ?");
    $stmt->execute([$claimId]);
    $contract = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$contract) {
        $_SESSION['message'] = "Rental contract not found.";
        $_SESSION['message_type'] = "danger";
        header("Location: confirm-rental-management-invoices.php");
        exit();
    }

    $contract_start_date = $contract['contract_start_date'];
    $contract_end_date = $contract['contract_end_date'];
    $monthly_rent = $contract['amount'];
    $frequency = $contract['payment_frequency'];
    $penalty = $contract['penalty_rate'];
    $grace_period_days = intval($contract['grace_period_days']);

    $intervalMonths = match (strtolower($frequency)) {
        'monthly' => 1,
        'quarterly' => 3,
        'yearly' => 12,
        default => 1,
    };
    $amount = $monthly_rent * $intervalMonths;

    $start = new DateTime($contract_start_date);
    $end = new DateTime($contract_end_date);
    $interval = new DateInterval('P' . $intervalMonths . 'M');
    $period = new DatePeriod($start, $interval, $end);

    $stmtInsert = $pdo->prepare("
        INSERT INTO rental_recurring_invoices
        (claim_id, invoice_date, start_period_date, end_period_date, due_date, amount, payment_status, created_at, recurring_active)
        VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW(), 1)
    ");

    foreach ($period as $date) {
        $invoice_date = $date->format('Y-m-d');
        $start_period_date = $invoice_date;
        $end_period = (clone $date)->modify('+' . ($intervalMonths - 1) . ' months');
        $end_period_date = $end_period->format('Y-m-t');
        $due_date = (clone $date)->modify("+{$grace_period_days} days")->format('Y-m-d');

        $stmtInsert->execute([$claimId, $invoice_date, $start_period_date, $end_period_date, $due_date, $amount]);
    }

    $_SESSION['message'] = "Recurring invoices generated and activated.";
    $_SESSION['message_type'] = "success";
    header("Location: confirm-rental-management-invoices.php");
    exit();
}

// ------------------ FETCH DATA FOR DISPLAY ------------------
$stmt = $pdo->query("
SELECT cc.claim_id, cc.client_id, u.full_name AS client_name, p.property_name, p.location,
       rc.contract_signed_path, rc.contract_start_date, rc.contract_end_date, rc.amount AS monthly_rent,
       rc.penalty_rate, rc.payment_frequency, rc.grace_period_days,
       ri.invoice_id, ri.invoice_date, ri.due_date, ri.amount as invoice_amount, ri.payment_proof,
       ri.payment_status, ri.recurring_active
FROM client_claims cc
JOIN clients c ON cc.client_id = c.client_id
JOIN users u ON c.user_id = u.user_id
JOIN properties p ON cc.property_id = p.property_id
JOIN rental_contracts rc ON cc.claim_id = rc.claim_id
LEFT JOIN rental_recurring_invoices ri ON ri.claim_id = cc.claim_id
WHERE cc.claim_type = 'rent' AND cc.claim_source = 'rental_property_management'
ORDER BY cc.claim_id, ri.invoice_date
");

$invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group invoices by claim_id
$grouped = [];
foreach ($invoices as $inv) {
    $cid = $inv['claim_id'];
    if (!isset($grouped[$cid]['contract'])) {
        $grouped[$cid]['contract'] = $inv;
    }
    if ($inv['invoice_id']) {
        $grouped[$cid]['invoices'][] = [
            'invoice_id'      => $inv['invoice_id'],
            'invoice_date'    => $inv['invoice_date'],
            'due_date'        => $inv['due_date'],
            'amount'          => $inv['invoice_amount'],
            'penalty_rate'    => $inv['penalty_rate'],
            'payment_frequency' => $inv['payment_frequency'],
            'payment_proof'   => $inv['payment_proof'],
            'payment_status'  => $inv['payment_status'],
            'recurring_active'=> $inv['recurring_active'],
        ];
    } else {
        $grouped[$cid]['invoices'] = $grouped[$cid]['invoices'] ?? [];
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Recurring Rent Invoices</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css?v=<?= time() ?>">
</head>
<body class="d-flex flex-column min-vh-100">
<?php include 'header.php'; ?>

<div class="container-fluid d-flex flex-grow-1 flex-column p-0">
<div class="row flex-grow-1 g-0">
<main class="container py-5">
    <div class="container py-4">
  <h2>Recurring Rent Invoice Management</h2>

  <div class="text-end mb-4">
    <a href="staff-profile.php" class="btn btn-dark mt-4">← Back to Dashboard</a>
  </div>

  <?php if (isset($_SESSION['message'])): ?>
    <div class="alert alert-<?= htmlspecialchars($_SESSION['message_type'] ?? 'info') ?>">
      <?= htmlspecialchars($_SESSION['message']); unset($_SESSION['message'], $_SESSION['message_type']); ?>
    </div>
  <?php endif; ?>

<?php foreach ($grouped as $claim_id => $data): 
    $info = $data['contract'];
    $invoicesForClaim = isset($data['invoices']) && is_array($data['invoices']) ? $data['invoices'] : [];
    $active = isset($invoicesForClaim[0]) ? $invoicesForClaim[0]['recurring_active'] : 0;
?>
<div class="card mb-4">
  <div class="card-header">
    <strong><?= htmlspecialchars($info['property_name']) ?></strong> - <?= htmlspecialchars($info['location']) ?>
  </div>
  <div class="card-body">
    <p><strong>Client:</strong> <?= htmlspecialchars($info['client_name']) ?></p>
    <p><strong>Contract Period:</strong> <?= htmlspecialchars($info['contract_start_date']) ?> to <?= htmlspecialchars($info['contract_end_date']) ?></p>
    <p>
      <strong>Payment Frequency:</strong> <?= ucfirst(htmlspecialchars($info['payment_frequency'])) ?><br>
      <strong>Penalty Rate:</strong> <?= number_format($info['penalty_rate'],2) ?>%
    </p>

    <?php if (!count($invoicesForClaim)): ?>
      <!-- If no invoice yet, show activation form -->
      <form method="POST" class="row g-2 mb-2" id="invoice-settings-form-<?= $claim_id ?>">
          <input type="hidden" name="generate_invoices" value="1">
          <input type="hidden" name="claim_id" value="<?= $claim_id ?>">
          <div class="col-md-4">
              <label for="start_date_<?= $claim_id ?>">First Invoice Start Date</label>
              <input type="date" name="start_date" id="start_date_<?= $claim_id ?>" class="form-control"
                  value="<?= htmlspecialchars($info['contract_start_date']) ?>" required>
          </div>
          <div class="col-md-2 d-flex align-items-end">
              <button type="button" class="btn btn-outline-primary w-100 me-2" onclick="previewInvoice(<?= $claim_id ?>)">Preview Invoice</button>
          </div>
          <div class="col-md-2 d-flex align-items-end">
              <button class="btn btn-success w-100" name="generate_invoices" value="1">Activate & Generate Invoices</button>
          </div>
      </form>
      <div id="invoice-preview-<?= $claim_id ?>" class="mt-3"></div>
      <div class="alert alert-warning mt-3 small">No recurring invoices have been generated for this contract. This is typically done when the contract is signed.</div>
    <?php else: ?>

<?php
// 1. Sort invoices by due date ASC
usort($invoicesForClaim, function($a, $b) {
    return strtotime($a['due_date']) <=> strtotime($b['due_date']);
});

// 2. Find the first pending (not confirmed) invoice
$currentInvoice = null;
foreach ($invoicesForClaim as $inv) {
    if ($inv['payment_status'] !== 'confirmed') {
        $currentInvoice = $inv;
        break;
    }
}
// 3. If all are confirmed, show latest confirmed invoice
if (!$currentInvoice && !empty($invoicesForClaim)) {
    usort($invoicesForClaim, fn($a, $b) => strtotime($b['invoice_date']) <=> strtotime($a['invoice_date']));
    $currentInvoice = $invoicesForClaim[0];
}

$currentInvoiceId = $currentInvoice['invoice_id'] ?? 0;

// Fetch warnings for this invoice
$warnings = [];
$manualWarningExists = false;
$autoWarnings = 0;

if ($currentInvoiceId) {
    $warnStmt = $pdo->prepare("SELECT * FROM rent_warnings WHERE claim_id = ? ORDER BY sent_at DESC");
    $warnStmt->execute([$claim_id]);
    $warnings = $warnStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($warnings as $w) {
        if ($w['warning_type'] === 'manual') $manualWarningExists = true;
        if ($w['warning_type'] === 'automatic') $autoWarnings++;
    }
    // HIDE warnings if payment proof is uploaded
    if (!empty($currentInvoice['payment_proof'])) $warnings = [];
}

$warningCount = count($warnings);
$now = date('Y-m-d');
$canSendManualWarning = (
    $currentInvoice['payment_status'] !== 'confirmed'
    && $currentInvoice['due_date'] < $now
    && empty($currentInvoice['payment_proof'])
    && !$manualWarningExists
    && $autoWarnings >= 2
);
?>

<!-- Always show warning count button (if at least one warning) -->
<div class="mb-2">
    <?php if ($warningCount > 0): ?>
        <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="collapse" data-bs-target="#warnings-<?= $currentInvoiceId ?>">
            Warnings (<?= $warningCount ?>)
        </button>
        <div class="collapse mt-1" id="warnings-<?= $currentInvoiceId ?>">
            <ul class="list-group">
            <?php foreach ($warnings as $w): ?>
                <li class="list-group-item small">
                    <b><?= ucfirst($w['warning_type']) ?>:</b>
                    <?= nl2br(htmlspecialchars($w['message'])) ?>
                    <span class="text-muted">(<?= date('d M Y H:i', strtotime($w['sent_at'])) ?>)</span>
                </li>
            <?php endforeach; ?>
            </ul>
        </div>
    <?php else: ?>
        <span class="text-muted">No warnings</span>
    <?php endif; ?>
</div>

<!-- Show manual warning input when 2 auto-warnings, no manual, and no payment proof -->
<?php if ($canSendManualWarning): ?>
    <form method="POST" action="send-rent-warning.php" class="mt-2">
        <input type="hidden" name="claim_id" value="<?= $claim_id ?>">
        <input type="hidden" name="invoice_id" value="<?= $currentInvoiceId ?>">
        <div class="mb-2">
            <label for="final-warning-<?= $currentInvoiceId ?>"><b>Send Final Reminder to Client</b></label>
            <textarea name="message" id="final-warning-<?= $currentInvoiceId ?>" class="form-control" rows="4" required>
Final Reminder:

This is your final reminder regarding your overdue rent payment. If payment proof is not submitted immediately, a formal notice to vacate will be issued as required by your lease agreement.

Please act now to avoid further consequences.
            </textarea>
        </div>
        <button class="btn btn-danger" type="submit">Send Final Reminder</button>
    </form>
<?php elseif ($manualWarningExists): ?>
    <div class="alert alert-danger mt-2">A final reminder has already been sent for this invoice.</div>
<?php endif; ?>

<?php if ($currentInvoice): ?>
    <h6 class="mt-4">Current Invoice</h6>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Invoice Date</th>
                <th>Due Date</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Proof</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><?= htmlspecialchars($currentInvoice['invoice_date']) ?></td>
                <td><?= htmlspecialchars($currentInvoice['due_date']) ?></td>
                <td><?= number_format($currentInvoice['amount'],2) ?></td>
                <td><?= ucfirst(htmlspecialchars($currentInvoice['payment_status'])) ?></td>
                <td>
                    <small class="text-muted">Invoice #<?= $currentInvoice['invoice_id'] ?></small><br>
                    <?php if (!empty($currentInvoice['payment_proof'])): ?>
                        <a href="<?= htmlspecialchars($currentInvoice['payment_proof']) ?>" target="_blank">View</a>
                    <?php else: ?>
                        <span class="text-muted">None</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($currentInvoice['payment_status'] !== 'confirmed' && !empty($currentInvoice['payment_proof'])): ?>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="invoice_id" value="<?= $currentInvoice['invoice_id'] ?>">
                            <button class="btn btn-sm btn-success">Confirm</button>
                        </form>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </td>
            </tr>
        </tbody>
    </table>

    <?php if (!empty($warnings)): ?>
        <div class="mt-2">
            <b>Recent Warnings for This Invoice:</b>
            <ul class="list-group">
                <?php foreach (array_slice($warnings, 0, 3) as $w): ?>
                    <li class="list-group-item small">
                        <b><?= ucfirst($w['warning_type']) ?>:</b>
                        <?= nl2br(htmlspecialchars($w['message'])) ?>
                        <span class="text-muted">(<?= date('d M Y H:i', strtotime($w['sent_at'])) ?>)</span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <a href="generate-recurring-invoices.php?invoice_id=<?= $currentInvoice['invoice_id'] ?>" class="btn btn-outline-primary btn-sm mb-1" target="_blank">
      Download Invoice PDF
    </a>
    <a href="view-all-invoices.php?claim_id=<?= $claim_id ?>" class="btn btn-link btn-sm">View Full Statement</a>
    <!-- Recurring toggle -->
    <form method="POST" style="display:inline">
      <input type="hidden" name="toggle_recurring" value="1">
      <input type="hidden" name="claim_id" value="<?= $claim_id ?>">
      <input type="hidden" name="recurring_status" value="<?= $active ?>">
      <button class="btn btn-outline-<?= $active ? 'danger':'success' ?> btn-sm" type="submit">
          <?= $active ? 'Deactivate' : 'Activate' ?>
      </button>
    </form>
    <span class="badge bg-<?= $active ? 'success':'danger' ?> ms-2">
      <?= $active ? 'Active' : 'Inactive' ?>
    </span>
<?php else: ?>
  <div class="alert alert-warning">No invoices available for this contract.</div>
<?php endif; ?>

    <?php endif; ?>
  </div>
  <div class="bg-dark text-center text-light">End for this property</div>
</div>
<?php endforeach; ?>
</main>
</div>
</div>

<script>
function previewInvoice(claimId) {
    const startDateInput = document.getElementById('start_date_' + claimId);
    const startDate = startDateInput.value;
    if (!startDate) {
        alert('Please select a start date');
        return;
    }
    fetch('preview-recurring-invoice.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'claim_id=' + encodeURIComponent(claimId) + '&start_date=' + encodeURIComponent(startDate)
    })
    .then(response => response.text())
    .then(html => {
        document.getElementById('invoice-preview-' + claimId).innerHTML = html;
    })
    .catch(err => {
        document.getElementById('invoice-preview-' + claimId).innerHTML = '<div class="alert alert-danger">Could not load preview.</div>';
    });
}

// Listen to date change and trigger preview automatically
document.addEventListener("DOMContentLoaded", function() {
    <?php foreach ($grouped as $claim_id => $data): ?>
        const input<?= $claim_id ?> = document.getElementById('start_date_<?= $claim_id ?>');
        if (input<?= $claim_id ?>) {
            input<?= $claim_id ?>.addEventListener('change', function() {
                previewInvoice(<?= $claim_id ?>);
            });
        }
    <?php endforeach; ?>
});
</script>

<?php include 'footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
<script src="navbar-close.js?v=1"></script>
</body>
</html>
