<?php

/*
|--------------------------------------------------------------------------
| Invoice Editing Page (CLEAN VERSION)
|--------------------------------------------------------------------------
| - For type=client or owner & claim_id: Show brokerage claim invoice (invoice-claim.html)
| - For type=claim or deposit & claim_id: Show rental claim/deposit invoice (claim: invoice-claim.html, deposit: invoice-rent-pre-contract.html)
| - Otherwise (no type/other type): Show owner service/application invoice (invoice-service-requests.html)
| - POST saves invoice and updates the correct table/fields
|--------------------------------------------------------------------------
*/

require 'db_connect.php';
session_start();

$type = $_GET['type'] ?? 'application'; // claim, deposit, client, owner, etc
$id   = $_GET['request_id'] ?? null;

if (!$id) die('Invalid request');

$invoiceHtml   = '';
$amount        = 0;
$invoiceNumber = '';
$template      = '';
$data          = [];
$backUrl       = '';
$invoicePath   = '';
$showDepositForm = false;

// ----------------------
// 1. BROKERAGE CLAIM PAYMENTS (type=client/owner)
// ----------------------
if ($type === 'client' || $type === 'owner') {
    $stmt = $pdo->prepare("
        SELECT cb.*, cc.claim_id, cc.claim_type, cc.property_id, cc.client_id, cc.visit_id, 
               p.property_name, p.location, o.owner_id, u.full_name AS owner_name,
               cu.full_name AS client_name
        FROM brokerage_claim_payments cb
        JOIN client_claims cc ON cb.claim_id = cc.claim_id
        JOIN properties p ON cc.property_id = p.property_id
        JOIN owners o ON p.owner_id = o.owner_id
        JOIN users u ON o.user_id = u.user_id
        LEFT JOIN clients c ON cc.client_id = c.client_id
        LEFT JOIN users cu ON c.user_id = cu.user_id
        WHERE cb.claim_id = ? AND cb.payment_type = ?
        LIMIT 1
    ");
    $stmt->execute([$id, $type]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$data) die('Claim invoice not found.');

    $billTo = $data['claim_type'] === 'sale' ? $data['owner_name'] : $data['client_name'];
    $amount = $data['amount'] ?? 0;
    $invoiceNumber = 'CLAIM-' . $data['claim_id'] . '-' . strtoupper($type);
    $template = file_get_contents('invoice-claim.html');

    $client_id   = $data['client_id'];
    $client_name = preg_replace('/[^a-zA-Z0-9_]/', '_', strtolower($data['client_name']));
    $visit_id    = $data['visit_id'] ?? null;
    if (!$visit_id) die('Visit ID not found for claim invoice');
    $targetDir   = "uploads/clients/{$client_id}_{$client_name}/visit_{$visit_id}/";
    if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
    $invoicePath = $targetDir . "invoice-claim.html";
    $backUrl     = "confirm-brokerage-claim-payments.php";

    // Handle POST: Save
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $amount = $_POST['amount'] ?? $amount;
        $stmtSave = $pdo->prepare("UPDATE brokerage_claim_payments SET amount = ?, invoice_path = ? WHERE claim_id = ? AND payment_type = ?");
        $stmtSave->execute([$amount, $invoicePath, $id, $type]);
        // Regenerate invoice HTML
        $tokens = [
            '{{AGENCY_NAME}}'    => 'TREA',
            '{{INVOICE_NUMBER}}' => $invoiceNumber,
            '{{INVOICE_DATE}}'   => date('Y-m-d'),
            '{{INVOICE_BILL_NAME}}' => htmlspecialchars($billTo),
            '{{CLIENT_NAME}}'    => htmlspecialchars($data['client_name'] ?? ''),
            '{{OWNER_NAME}}'     => htmlspecialchars($data['owner_name']),
            '{{PROPERTY_NAME}}'  => htmlspecialchars($data['property_name']),
            '{{LOCATION}}'       => htmlspecialchars($data['location']),
            '{{AMOUNT}}'         => number_format($amount, 2),
            '{{AGENCY_EMAIL}}'   => 'info@trea.com',
            '{{AGENCY_PHONE}}'   => '+229 0112 345 678',
            '{{PAYMENT_TYPE}}'   => ucfirst($type),
        ];
        $invoiceHtml = str_replace(array_keys($tokens), array_values($tokens), $template);
        file_put_contents($invoicePath, $invoiceHtml);
        $_SESSION['success'] = "Claim invoice updated!";
        header("Location: edit-invoice.php?request_id=$id&type=$type");
        exit;
    }
    // Preview
    $tokens = [
        '{{AGENCY_NAME}}'    => 'TREA',
        '{{INVOICE_NUMBER}}' => $invoiceNumber,
        '{{INVOICE_DATE}}'   => date('Y-m-d'),
        '{{INVOICE_BILL_NAME}}' => htmlspecialchars($billTo),
        '{{CLIENT_NAME}}'    => htmlspecialchars($data['client_name'] ?? ''),
        '{{OWNER_NAME}}'     => htmlspecialchars($data['owner_name']),
        '{{PROPERTY_NAME}}'  => htmlspecialchars($data['property_name']),
        '{{LOCATION}}'       => htmlspecialchars($data['location']),
        '{{AMOUNT}}'         => number_format($amount, 2),
        '{{AGENCY_EMAIL}}'   => 'info@trea.com',
        '{{AGENCY_PHONE}}'   => '+229 0112 345 678',
        '{{PAYMENT_TYPE}}'   => ucfirst($type),
    ];
    $invoiceHtml = str_replace(array_keys($tokens), array_values($tokens), $template);

// ----------------------
// 2. RENTAL CLAIM or DEPOSIT PAYMENTS
// ----------------------
} elseif ($type === 'claim' || $type === 'deposit') {
    $stmt = $pdo->prepare("
        SELECT rcp.*, cc.client_id, cc.property_id, cc.claim_type, cc.claim_source, cc.visit_id,
               p.property_name, p.location, p.price, o.owner_id, u.full_name AS owner_name,
               cu.full_name AS client_name
        FROM rental_claim_payments rcp
        JOIN client_claims cc ON rcp.claim_id = cc.claim_id
        JOIN properties p ON cc.property_id = p.property_id
        JOIN owners o ON p.owner_id = o.owner_id
        JOIN users u ON o.user_id = u.user_id
        LEFT JOIN clients c ON cc.client_id = c.client_id
        LEFT JOIN users cu ON c.user_id = cu.user_id
        WHERE rcp.claim_id = ? AND rcp.payment_type = ?
        LIMIT 1
    ");
    $stmt->execute([$id, $type]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$data) die('Rental claim invoice not found.');
    $billTo = $data['client_name'];
    $client_id     = $data['client_id'];
    $client_name   = preg_replace('/[^a-zA-Z0-9_]/', '_', strtolower($data['client_name']));
    $property_id   = $data['property_id'];
    $property_name = preg_replace('/[^a-zA-Z0-9_]/', '_', strtolower($data['property_name']));
    // Deposit: multi-fields. Claim: single field
    if ($type === 'deposit') {
        $showDepositForm = true;
        $number_of_month = $data['number_of_month'] ?? 1;
        $advance_amount  = $data['advance_amount'] ?? 0;
        $deposit_amount  = $data['deposit_amount'] ?? ($price * $number_of_month);
        $due_date        = $data['due_date'] ?? date('Y-m-d', strtotime('+7 days'));
        $amount          = $data['amount'] ?? ($advance_amount + $deposit_amount);

        $targetDir     = "uploads/clients/{$client_id}_{$client_name}/reserved_properties/{$property_id}_{$property_name}/";
        if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
        $invoicePath   = $targetDir . "invoice-deposit.html";
        $invoiceNumber = 'DEPOSIT-' . $data['claim_id'];
        $backUrl       = "confirm-rental-claim-payments.php";

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $number_of_month = intval($_POST['number_of_month'] ?? 1);
            $advance_amount  = $price * $number_of_month;
            $deposit_amount  = floatval($_POST['deposit_amount'] ?? 0);
            $due_date        = $_POST['due_date'] ?? date('Y-m-d', strtotime('+7 days'));
            $amount          = $advance_amount + $deposit_amount;

            $stmtSave = $pdo->prepare("
                UPDATE rental_claim_payments 
                SET number_of_month = ?, advance_amount = ?, deposit_amount = ?, due_date = ?, amount = ?, invoice_path = ? 
                WHERE claim_id = ? AND payment_type = ?
            ");
            $stmtSave->execute([
                $number_of_month, $advance_amount, $deposit_amount, $due_date, $amount, $invoicePath, $id, $type
            ]);
            $template = file_get_contents('invoice-rent-pre-contract.html');
            $tokens = [
                '{{AGENCY_NAME}}'      => 'TREA',
                '{{INVOICE_NUMBER}}'   => $invoiceNumber,
                '{{INVOICE_DATE}}'     => date('Y-m-d'),
                '{{CLIENT_NAME}}'      => htmlspecialchars($billTo),
                '{{PROPERTY_NAME}}'    => htmlspecialchars($data['property_name']),
                '{{NUMBER_OF_MONTH}}'  => $number_of_month,
                '{{ADVANCE_AMOUNT}}'   => number_format($advance_amount, 2),
                '{{DEPOSIT_AMOUNT}}'   => number_format($deposit_amount, 2),
                '{{DEPOSIT}}'          => number_format($amount, 2),
                '{{DUE_DATE}}'         => htmlspecialchars($due_date),
            ];
            $invoiceHtml = str_replace(array_keys($tokens), array_values($tokens), $template);
            file_put_contents($invoicePath, $invoiceHtml);

            $_SESSION['success'] = "Deposit invoice updated!";
            header("Location: edit-invoice.php?request_id=$id&type=$type");
            exit;
        }
        // Preview
        $template = file_get_contents('invoice-rent-pre-contract.html');
        $tokens = [
            '{{AGENCY_NAME}}'      => 'TREA',
            '{{INVOICE_NUMBER}}'   => $invoiceNumber,
            '{{INVOICE_DATE}}'     => date('Y-m-d'),
            '{{CLIENT_NAME}}'      => htmlspecialchars($billTo),
            '{{PROPERTY_NAME}}'    => htmlspecialchars($data['property_name']),
            '{{NUMBER_OF_MONTH}}'  => $number_of_month,
            '{{ADVANCE_AMOUNT}}'   => number_format($advance_amount, 2),
            '{{DEPOSIT_AMOUNT}}'   => number_format($deposit_amount, 2),
            '{{DEPOSIT}}'          => number_format($amount, 2),
            '{{DUE_DATE}}'         => htmlspecialchars($due_date),
        ];
        $invoiceHtml = str_replace(array_keys($tokens), array_values($tokens), $template);
    } else {
        // Rental claim (single amount field, same as brokerage)
        $amount = $data['amount'] ?? 0;
        $invoiceNumber = 'CLAIM-' . $data['claim_id'];
        $template = file_get_contents('invoice-claim.html');
        $visit_id  = $data['visit_id'] ?? $data['property_id'];
        $targetDir = "uploads/clients/{$client_id}_{$client_name}/visit_{$visit_id}/";
        if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
        $invoicePath = $targetDir . "invoice-claim.html";
        $backUrl     = "confirm-rental-claim-payments.php";

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $amount = $_POST['amount'] ?? $amount;
            $stmtSave = $pdo->prepare("UPDATE rental_claim_payments SET amount = ?, invoice_path = ? WHERE claim_id = ? AND payment_type = ?");
            $stmtSave->execute([$amount, $invoicePath, $id, $type]);
            $tokens = [
                '{{AGENCY_NAME}}'    => 'TREA',
                '{{INVOICE_NUMBER}}' => $invoiceNumber,
                '{{INVOICE_DATE}}'   => date('Y-m-d'),
                '{{INVOICE_BILL_NAME}}' => htmlspecialchars($billTo),
                '{{CLIENT_NAME}}'    => htmlspecialchars($data['client_name']),
                '{{PROPERTY_NAME}}'  => htmlspecialchars($data['property_name']),
                '{{LOCATION}}'       => htmlspecialchars($data['location']),
                '{{AMOUNT}}'         => number_format($amount, 2),
                '{{AGENCY_EMAIL}}'   => 'info@trea.com',
                '{{AGENCY_PHONE}}'   => '+229 0112 345 678',
                '{{PAYMENT_TYPE}}'   => ucfirst($type),
            ];
            $invoiceHtml = str_replace(array_keys($tokens), array_values($tokens), $template);
            file_put_contents($invoicePath, $invoiceHtml);

            $_SESSION['success'] = "Claim invoice updated!";
            header("Location: edit-invoice.php?request_id=$id&type=$type");
            exit;
        }
        // Preview
        $tokens = [
            '{{AGENCY_NAME}}'    => 'TREA',
            '{{INVOICE_NUMBER}}' => $invoiceNumber,
            '{{INVOICE_DATE}}'   => date('Y-m-d'),
            '{{INVOICE_BILL_NAME}}' => htmlspecialchars($billTo),
            '{{CLIENT_NAME}}'    => htmlspecialchars($data['client_name']),
            '{{PROPERTY_NAME}}'  => htmlspecialchars($data['property_name']),
            '{{LOCATION}}'       => htmlspecialchars($data['location']),
            '{{AMOUNT}}'         => number_format($amount, 2),
            '{{AGENCY_EMAIL}}'   => 'info@trea.com',
            '{{AGENCY_PHONE}}'   => '+229 0112 345 678',
            '{{PAYMENT_TYPE}}'   => ucfirst($type),
        ];
        $invoiceHtml = str_replace(array_keys($tokens), array_values($tokens), $template);
    }

// ----------------------
// 3. OWNER SERVICE REQUESTS (default)
// ----------------------
} else {
    $stmt = $pdo->prepare("
        SELECT r.*, s.service_name, s.slug, u.full_name AS owner_name, o.owner_id
        FROM owner_service_requests r
        JOIN owners o ON r.owner_id = o.owner_id
        JOIN users u ON o.user_id = u.user_id
        JOIN services s ON r.service_id = s.service_id
        WHERE r.request_id = ?
    ");
    $stmt->execute([$id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$data) die('Request not found');

    $amount = $data['application_fee'] ?? 0;
    $invoiceNumber = 'INV-' . $id;
    $type = 'application';
    $ownerFolder = $data['owner_id'] . '_' . preg_replace('/[^a-z0-9_]/i', '_', $data['owner_name']);
    $serviceFolder = $data['service_id'] . '_' . $data['slug'];
    $targetDir = "uploads/owner/{$ownerFolder}/applications/{$serviceFolder}/request_{$id}/";
    if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
    $invoicePath = $targetDir . "invoice.html";
    $backUrl = "confirm-application-payment.php";

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $amount = $_POST['amount'] ?? $amount;
        $stmtSave = $pdo->prepare("
            REPLACE INTO service_request_payments (request_id, payment_type, amount, invoice_path, invoice_number)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmtSave->execute([
            $id, $type, $amount, $invoicePath, $invoiceNumber
        ]);
        $template = file_get_contents('invoice-service-requests.html');
        $isUrgent = !empty($data['urgent']) && ($data['urgent'] == 1 || strtolower($data['urgent']) === 'yes');
        $urgentLabel = $isUrgent 
            ? '<span style="color: #fff; background: #d9534f; padding: 2px 10px; border-radius: 6px; font-size: 13px;">URGENT</span>'
            : '';
        $tokens = [
            '{{AGENCY_NAME}}'    => 'TREA',
            '{{INVOICE_NUMBER}}' => $invoiceNumber,
            '{{INVOICE_DATE}}'   => date('Y-m-d'),
            '{{OWNER_NAME}}'     => htmlspecialchars($data['owner_name']),
            '{{SERVICE_TYPE}}'   => htmlspecialchars($data['service_name']),
            '{{AMOUNT}}'         => number_format($amount, 2),
            '{{AGENCY_EMAIL}}'   => 'info@trea.com',
            '{{AGENCY_PHONE}}'   => '+229 0112 345 678',
            '{{URGENT_LABEL}}'   => $urgentLabel,
        ];
        $invoiceHtml = str_replace(array_keys($tokens), array_values($tokens), $template);
        file_put_contents($invoicePath, $invoiceHtml);
        $_SESSION['success'] = "Invoice generated!";
        header("Location: edit-invoice.php?request_id=$id");
        exit;
    }
    // Preview
    $template = file_get_contents('invoice-service-requests.html');
    $isUrgent = !empty($data['urgent']) && ($data['urgent'] == 1 || strtolower($data['urgent']) === 'yes');
    $urgentLabel = $isUrgent 
        ? '<span style="color: #fff; background: #d9534f; padding: 2px 10px; border-radius: 6px; font-size: 13px;">URGENT</span>'
        : '';
    $tokens = [
        '{{AGENCY_NAME}}'    => 'TREA',
        '{{INVOICE_NUMBER}}' => $invoiceNumber,
        '{{INVOICE_DATE}}'   => date('Y-m-d'),
        '{{OWNER_NAME}}'     => htmlspecialchars($data['owner_name']),
        '{{SERVICE_TYPE}}'   => htmlspecialchars($data['service_name']),
        '{{AMOUNT}}'         => number_format($amount, 2),
        '{{AGENCY_EMAIL}}'   => 'info@trea.com',
        '{{AGENCY_PHONE}}'   => '+229 0112 345 678',
        '{{URGENT_LABEL}}'   => $urgentLabel,
    ];
    $invoiceHtml = str_replace(array_keys($tokens), array_values($tokens), $template);
}
    $invoiceIsSaved = file_exists($invoicePath) && filesize($invoicePath) > 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Edit Invoice</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="styles.css?v=<?= time() ?>">
</head>
<body class="d-flex flex-column min-vh-100 bg-light">
<?php include 'header.php'; ?>
<div class="container-fluid flex-grow-1 py-4">
    <div class="row">
        <div class="container py-4">
            <h2>Edit Invoice (Preview)</h2>
            <?php if (!empty($_SESSION['success'])): ?>
                <div class="alert alert-success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
            <?php endif; ?>

            <?php if (!$invoiceIsSaved): ?>
            <?php if ($showDepositForm): ?>
            <form method="POST" class="row g-3">
                <div class="col-md-3">
                    <label>Advance Period (months):</label>
                    <input type="number" min="1" name="number_of_month" class="form-control" value="<?= htmlspecialchars($number_of_month) ?>">
                </div>
                <div class="col-md-3">
                    <label>Utility Deposit (CFA):</label>
                    <input type="number" min="0" step="0.01" name="deposit_amount" class="form-control" value="<?= htmlspecialchars($deposit_amount) ?>">
                </div>
                <div class="col-md-3">
                    <label>Due Date:</label>
                    <input type="date" name="due_date" class="form-control" value="<?= htmlspecialchars($due_date) ?>">
                </div>
                <div class="col-md-3">
                    <label>Total (Auto):</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($advance_amount + $deposit_amount) ?>" readonly>
                </div>
                <div class="col-12 mt-2">
                    <button class="btn custom-btn">Save Invoice</button>
                </div>
            </form>
            <?php else: ?>
                <form method="POST" class="mb-3">
                    <div class="mb-2">
                        <label>Amount (CFA):</label>
                        <input type="number" class="form-control" name="amount" value="<?= htmlspecialchars($amount) ?>">
                    </div>
                    <button class="btn custom-btn">Save Invoice</button>
                </form>
                <?php endif; ?>
                <?php else: ?>
                    <div class="alert alert-info mb-2">
                        Invoice has already been generated and saved. <br>
                        <a href="<?= $invoicePath ?>" target="_blank" class="fw-bold">View Invoice</a>
                    </div>
                    <?php endif; ?>

            <a href="<?= $backUrl ?>" class="btn bg-dark text-white fw-bold mt-3">🡰 Back to previous page</a>
            <h5 class="mt-4">Invoice Preview:</h5>
            <div class="invoice-preview">
                <?= $invoiceHtml ?>
            </div>
        </div>
    </div>
</div>
<?php include 'footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
<script src="navbar-close.js?v=1"></script>
</body>
</html>
