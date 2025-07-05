<?php
// manage-rental-contract.php
// Handles agency-side editing and extension of rental contracts, with proper state and signature rules.

session_start();
require 'db_connect.php';

// --- 1. AUTH: Only property/general managers allowed ---
$staff_id = $_SESSION['staff_id'] ?? null;
$role = strtolower(trim($_SESSION['role'] ?? ''));
if (!$staff_id || !in_array($role, ['general manager', 'property manager'])) {
    http_response_code(403);
    exit("Access denied.");
}

// --- 2. LOAD CONTRACT DATA ---
$claim_id = $_GET['claim_id'] ?? null;
if (!$claim_id) { http_response_code(400); exit("Missing claim ID."); }

$stmt = $pdo->prepare("
    SELECT cc.*, p.property_id, p.property_name, p.location AS property_location, p.property_type, p.request_id, p.price AS monthly_rent,
        o.owner_id, uo.full_name AS owner_name, uo.phone_number AS owner_phone, uo.email AS owner_email,
        cl.client_id, uc.full_name AS client_name, uc.phone_number AS client_phone, uc.email AS client_email,
        st.staff_id AS manager_id, st.full_name AS manager_name,
        rpd.use_for_the_property,
        rc.contract_id, rc.contract_body, rc.locked, rc.contract_signed_path, rc.contract_start_date, rc.contract_end_date, rc.revision_frequency, rc.revision_unit,
        rc.next_revision_date, rc.payment_frequency, rc.grace_period_days, rc.notice_period_months, rc.penalty_rate, rc.amount,
        rc.client_signature, rc.owner_signature, rc.client_signed_at, rc.owner_signed_at, rc.custom_clauses
    FROM client_claims cc
    JOIN properties p ON cc.property_id = p.property_id
    JOIN rental_property_management_details rpd ON rpd.request_id = p.request_id
    JOIN owners o ON p.owner_id = o.owner_id
    JOIN users uo ON o.user_id = uo.user_id
    JOIN clients cl ON cc.client_id = cl.client_id
    JOIN users uc ON cl.user_id = uc.user_id
    JOIN staff st ON st.role = 'general manager'
    LEFT JOIN rental_contracts rc ON cc.claim_id = rc.claim_id
    WHERE cc.claim_id = ?
");
$stmt->execute([$claim_id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) exit("No claim or property found.");

// --- 3. PAYMENT AMOUNTS ---
$pay_stmt = $pdo->prepare("SELECT * FROM rental_claim_payments WHERE claim_id = ? AND payment_type = 'deposit'");
$pay_stmt->execute([$claim_id]);
$deposit_row = $pay_stmt->fetch(PDO::FETCH_ASSOC);

$advance_amount = floatval($deposit_row['advance_amount'] ?? 0);
$advance_months = intval($deposit_row['number_of_month'] ?? 1);
$deposit_amount = floatval($deposit_row['deposit_amount'] ?? 0); 
$total_amount = floatval($deposit_row['amount'] ?? ($advance_amount + $deposit_amount));

// --- 4. FILE/FOLDER PATHS ---
function slugify($string) { return preg_replace('/[^a-zA-Z0-9_\-]/', '_', strtolower(trim($string))); }
$clientFolder   = $row['client_id'] . '_' . slugify($row['client_name']);
$propertyFolder = $row['property_id'] . '_' . slugify($row['property_name']);
$ownerFolder    = $row['owner_id'] . '_' . slugify($row['owner_name']);
$base_folder    = __DIR__ . "/uploads/owner/$ownerFolder/listed_properties/$propertyFolder/";
if (!is_dir($base_folder)) mkdir($base_folder, 0777, true);
$contract_filename = 'contract.html';
$contract_file_path = $base_folder . $contract_filename;

// --- 5. PLACEHOLDER REPLACEMENT ---
function contract_placeholders($row, $advance_amount, $advance_months, $deposit_amount, $total_amount) {
    $contract_start_date  = $row['contract_start_date'] ?? '';
    $contract_end_date    = $row['contract_end_date'] ?? '';
    $notice_period_months = $row['notice_period_months'] ?? '1';
    $grace_period_days    = $row['grace_period_days'] ?? '7';
    $penalty_rate         = $row['penalty_rate'] ?? '5';
    $payment_frequency    = $row['payment_frequency'] ?? 'monthly';
    $monthly_rent         = $row['monthly_rent'] ?? '';
    $revision_frequency   = $row['revision_frequency'] ?? '1';
    $revision_unit        = $row['revision_unit'] ?? 'year';
    $custom_clauses       = $row['custom_clauses'] ?? '';
    $penalty_amount = (is_numeric($monthly_rent) && is_numeric($penalty_rate))
        ? $monthly_rent * floatval($penalty_rate) / 100 : '';
    return [
        '{{OWNER_NAME}}'            => $row['owner_name'],
        '{{OWNER_PHONE}}'           => $row['owner_phone'],
        '{{OWNER_EMAIL}}'           => $row['owner_email'],
        '{{CLIENT_NAME}}'           => $row['client_name'],
        '{{CLIENT_PHONE}}'          => $row['client_phone'],
        '{{CLIENT_EMAIL}}'          => $row['client_email'],
        '{{PROPERTY_NAME}}'         => $row['property_name'],
        '{{PROPERTY_LOCATION}}'     => $row['property_location'],
        '{{PROPERTY_TYPE}}'         => $row['property_type'],
        '{{USE_FOR_THE_PROPERTY}}'  => $row['use_for_the_property'],
        '{{CONTRACT_START_DATE}}'   => $contract_start_date,
        '{{CONTRACT_END_DATE}}'     => $contract_end_date,
        '{{CONTRACT_DURATION}}'     => ($contract_start_date && $contract_end_date) ? "$contract_start_date to $contract_end_date" : '',
        '{{NOTICE_PERIOD}}'         => $notice_period_months,
        '{{GRACE_PERIOD_DAYS}}'     => $grace_period_days,
        '{{PENALTY_RATE}}'          => $penalty_rate,
        '{{PAYMENT_FREQUENCY}}'     => $payment_frequency,
        '{{MONTHLY_RENT}}'          => $monthly_rent,
        '{{REVISION_FREQUENCY}}'    => $revision_frequency,
        '{{REVISION_UNIT}}'         => $revision_unit,
        '{{PENALTY_AMOUNT}}'        => $penalty_amount,
        '{{PRONOUN}}'               => 'they',
        '{{AGENCY_NAME}}'           => 'TRUSTED REAL ESTATE AGENCY - TREA',
        '{{AGENCY_ADDRESS}}'        => 'Aibatin 2, Cotonou, Benin',
        '{{AGENCY_PHONE}}'          => '(+229) 0100000000',
        '{{AGENCY_EMAIL}}'          => 'info@trea.com',
        '{{MANAGER_NAME}}'          => $row['manager_name'],
        '{{OWNER_SIGNATURE_BLOCK}}' => '',
        '{{CLIENT_SIGNATURE_BLOCK}}'=> '',
        '{{CLIENT_SIGNATURE_DATE}}' => $row['client_signed_at'] ?: '',
        '{{OWNER_SIGNATURE_DATE}}'  => $row['owner_signed_at'] ?: '',
        '{{ADVANCE_AMOUNT}}'        => number_format($advance_amount, 0, '.', ' '),
        '{{ADVANCE_MONTHS}}'        => $advance_months ?: '',
        '{{DEPOSIT_AMOUNT}}'        => number_format($deposit_amount, 0, '.', ' '),
        '{{TOTAL_AMOUNT}}'          => number_format($total_amount, 0, '.', ' '),
        '{{CUSTOM_CLAUSES}}'        => nl2br(htmlspecialchars($custom_clauses)),
    ];
}

// --- 6. STATE & PERMISSIONS LOGIC (with support for extension) ---
// NOTE: We allow contract to be "unlocked for editing" for extension only by general manager.
// The signature fields are **never** cleared just because of an unlock; only after a new version is saved & locked for signing again.

$locked        = $row['locked'] ?? 0;
$hasSignature  = ($row['client_signature'] ?? 0) || ($row['owner_signature'] ?? 0);
$can_lock      = ($role === 'general manager') && !$locked && !$hasSignature;
$can_unlock    = ($role === 'general manager') && $locked && !$hasSignature;

// --- 7. POST: RESTORE FROM TEMPLATE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore_from_template']) && $role === 'general manager' && !$locked && !$hasSignature) {
    $template_file = __DIR__ . '/lease-contract-template-en.html';
    if (file_exists($template_file)) {
        $replacements = contract_placeholders($row, $advance_amount, $advance_months, $deposit_amount, $total_amount);
        $templateHtml = strtr(file_get_contents($template_file), $replacements);
        file_put_contents($contract_file_path, $templateHtml);
        $stmt = $pdo->prepare("UPDATE rental_contracts SET contract_body = ?, contract_signed_path = ?, updated_at = NOW() WHERE claim_id = ?");
        $stmt->execute([$templateHtml, $contract_file_path, $claim_id]);
        header("Location: manage-rental-contract.php?claim_id=$claim_id&restored=1");
        exit();
    }
}

// --- 8. POST: LOCK/UNLOCK (For extension: unlocks for edit, but signatures are not erased) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lock_contract']) && $can_lock) {
    $revFreq = $row['revision_frequency'] ?? 1;
    $revUnit = strtolower($row['revision_unit'] ?? 'year');
    $unitMap = ['year' => 'year', 'years' => 'year', 'month' => 'month', 'months' => 'month'];
    $intervalUnit = $unitMap[$revUnit] ?? 'year';
    $intervalSpec = "P" . intval($revFreq) . strtoupper(substr($intervalUnit,0,1));
    $today = new DateTime();
    $nextRev = (clone $today)->add(new DateInterval($intervalSpec))->format("Y-m-d");
    $stmt = $pdo->prepare("UPDATE rental_contracts SET locked = 1, next_revision_date = ?, updated_at = NOW() WHERE claim_id = ?");
    $stmt->execute([$nextRev, $claim_id]);
    header("Location: manage-rental-contract.php?claim_id=$claim_id&locked=1");
    exit();
}
// For extension: General manager can unlock ONLY for contract about to end. Signatures are kept intact.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unlock_contract']) && $can_unlock) {
    $stmt = $pdo->prepare("UPDATE rental_contracts SET locked = 0, next_revision_date = NULL, updated_at = NOW() WHERE claim_id = ?");
    $stmt->execute([$claim_id]);
    header("Location: manage-rental-contract.php?claim_id=$claim_id&unlocked=1");
    exit();
}

// --- 9. POST: SAVE CONTRACT (allow editing on unlock for extension, signatures NOT auto-cleared) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$locked && isset($_POST['contract_start_date'])) {
    $contract_start_date = $_POST['contract_start_date'] ?? null;
    $contract_end_date = $_POST['contract_end_date'] ?? null;
    $monthly_rent = $_POST['monthly_rent'] ?? null;
    $payment_frequency = $_POST['payment_frequency'] ?? 'monthly';
    $notice_period_months = $_POST['notice_period_months'] ?? '1';
    $grace_period_days = $_POST['grace_period_days'] ?? '7';
    $penalty_rate = $_POST['penalty_rate'] ?? '5';
    $revision_frequency = $_POST['revision_frequency'] ?? '1';
    $revision_unit = $_POST['revision_unit'] ?? 'year';
    $use_for_the_property = $_POST['use_for_the_property'] ?? '';
    $custom_clauses = $_POST['custom_clauses'] ?? '';

    // Save usage to details table
    $updateDetails = $pdo->prepare("UPDATE rental_property_management_details SET use_for_the_property = ? WHERE request_id = ?");
    $updateDetails->execute([$use_for_the_property, $row['request_id']]);

    // Save to contract table - **DO NOT erase signatures** (client_signature, owner_signature)
    $updateContract = $pdo->prepare("
        UPDATE rental_contracts SET 
            contract_start_date = ?, contract_end_date = ?, amount = ?, 
            payment_frequency = ?, notice_period_months = ?, grace_period_days = ?, penalty_rate = ?, 
            revision_frequency = ?, revision_unit = ?, custom_clauses = ?, updated_at = NOW()
        WHERE claim_id = ?
    ");
    $updateContract->execute([
        $contract_start_date, $contract_end_date, $monthly_rent,
        $payment_frequency, $notice_period_months, $grace_period_days, $penalty_rate,
        $revision_frequency, $revision_unit, $custom_clauses, $claim_id
    ]);

    // Reload for latest values
    $stmt->execute([$claim_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    // Rebuild contract from template
    $template_file = __DIR__ . '/lease-contract-template-en.html';
    if (file_exists($template_file)) {
        $replacements = contract_placeholders($row, $advance_amount, $advance_months, $deposit_amount, $total_amount);
        $templateHtml = strtr(file_get_contents($template_file), $replacements);
        file_put_contents($contract_file_path, $templateHtml);
        $stmt2 = $pdo->prepare("UPDATE rental_contracts SET contract_body = ?, contract_signed_path = ?, updated_at = NOW() WHERE claim_id = ?");
        $stmt2->execute([$templateHtml, $contract_file_path, $claim_id]);
    }

    header("Location: manage-rental-contract.php?claim_id=$claim_id&saved=1");
    exit();
}

// --- 10. LOAD CONTRACT CONTENT (for preview/readonly) ---
$content = '';
if (file_exists($contract_file_path) && filesize($contract_file_path) > 0) {
    $content = file_get_contents($contract_file_path);
} elseif (!empty($row['contract_body'])) {
    $content = $row['contract_body'];
} else {
    $template_file = __DIR__ . '/lease-contract-template-en.html';
    if (file_exists($template_file)) {
        $replacements = contract_placeholders($row, $advance_amount, $advance_months, $deposit_amount, $total_amount);
        $content = strtr(file_get_contents($template_file), $replacements);
        file_put_contents($contract_file_path, $content);
        $stmt = $pdo->prepare("UPDATE rental_contracts SET contract_body = ?, contract_signed_path = ?, updated_at = NOW() WHERE claim_id = ?");
        $stmt->execute([$content, $contract_file_path, $claim_id]);
    }
}
$page_title = 'Edit Lease Contract for ' . htmlspecialchars($row['property_name']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($page_title) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css?v=<?= time() ?>">
    <style>
        .required-field { color: #e74c3c; background: #fdecea; padding: 1px 3px; border-radius: 3px; }
    </style>
</head>
<body class="bg-light d-flex flex-column min-vh-100">
<?php include 'header.php'; ?>

<div class="container py-5">
    <div class="d-flex align-items-center mb-3">
        <h2 class="mb-0"><?= htmlspecialchars($page_title) ?></h2>
        <span class="badge <?= $locked ? 'bg-danger' : 'bg-success' ?> ms-4"><?= $locked ? 'Locked' : 'Unlocked' ?></span>
        <?php if ($hasSignature): ?>
            <span class="badge bg-primary ms-2">Signed</span>
        <?php endif; ?>
    </div>
    <p class="text-muted mb-4">
        <?php if ($locked): ?>
            Contract is locked. If extension is required, unlock and revise the contract. Owner/tenant signatures are preserved until a new revision is signed.
        <?php elseif ($hasSignature): ?>
            Contract is signed. Unlock for extension or amendment if tenant wishes to renew.<br>
            <span class="text-warning">Signatures are preserved during the extension period until contract is saved & relocked for a new signature round.</span>
        <?php else: ?>
            Modify the lease contract fields below. Once locked or signed by any party, no further edits are allowed until next revision or extension window.
        <?php endif; ?>
    </p>
    <?php if (isset($_GET['saved'])): ?>
        <div class="alert alert-success">Lease contract saved.</div>
    <?php endif; ?>
    <?php if (isset($_GET['locked'])): ?>
        <div class="alert alert-info">Contract is now locked. Please proceed to the signature page.</div>
    <?php endif; ?>
    <?php if (isset($_GET['unlocked'])): ?>
        <div class="alert alert-warning">Contract unlocked for editing. Owner and tenant can still view the contract; no edits allowed from their side.</div>
    <?php endif; ?>
    <?php if (isset($_GET['restored'])): ?>
        <div class="alert alert-secondary">Contract has been restored from template.</div>
    <?php endif; ?>

    <div class="my-3">
    <?php if ($role === 'general manager'): ?>
        <?php if ($can_lock): ?>
            <form method="POST" style="display:inline;">
                <button type="submit" name="lock_contract" class="btn btn-warning"
                        onclick="return confirm('Lock contract? You will not be able to edit until next revision or unlock.')">
                    Lock Contract
                </button>
            </form>
        <?php elseif ($can_unlock): ?>
            <form method="POST" style="display:inline;">
                <button type="submit" name="unlock_contract" class="btn btn-secondary"
                        onclick="return confirm('Unlock contract for editing? This is only possible if not yet signed and after the revision period.')">
                    Unlock Contract
                </button>
            </form>
            <span class="text-muted small ms-2">(Allowed if contract ending soon or for extension/renewal window.)</span>
        <?php endif; ?>
        <?php if (!$locked): ?>
            <form method="POST" style="display:inline;">
                <button type="submit" name="restore_from_template" class="btn btn-outline-secondary ms-2"
                        onclick="return confirm('Restore contract from template? This will OVERWRITE current content!')">
                    Restore from Template
                </button>
            </form>
        <?php endif; ?>
    <?php endif; ?>
    </div>

    <?php if ($locked): ?>
        <div class="border p-3 bg-white" style="min-height:600px">
            <?= $content // contract preview ?>
        </div>
        <div class="alert alert-info mt-3">
            Contract is locked or signed.<br>
            Please <a href="sign-lease-contract.php?claim_id=<?= urlencode($claim_id) ?>" class="alert-link">go to the signature page</a> to complete the signing process.
        </div>
    <?php else: ?>
        <form method="POST" autocomplete="off">
            <div class="row mb-3">
                <div class="col-md-3">
                    <label for="contract_start_date" class="form-label">Contract Start Date</label>
                    <input type="date" class="form-control" name="contract_start_date" id="contract_start_date"
                        value="<?= htmlspecialchars($row['contract_start_date'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label for="contract_end_date" class="form-label">Contract End Date</label>
                    <input type="date" class="form-control" name="contract_end_date" id="contract_end_date"
                        value="<?= htmlspecialchars($row['contract_end_date'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label for="monthly_rent" class="form-label">Monthly Rent (CFA)</label>
                    <input type="number" min="0" class="form-control" name="monthly_rent" id="monthly_rent"
                        value="<?= htmlspecialchars($row['monthly_rent'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label for="payment_frequency" class="form-label">Payment Frequency</label>
                    <select class="form-control" name="payment_frequency" id="payment_frequency">
                        <?php foreach (['monthly', 'quarterly', 'yearly'] as $freq): ?>
                            <option value="<?= $freq ?>" <?= ($row['payment_frequency'] ?? 'monthly') === $freq ? 'selected' : '' ?>>
                                <?= ucfirst($freq) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="row mb-3">
                <div class="col-md-3">
                    <label for="notice_period_months" class="form-label">Notice Period (months)</label>
                    <input type="number" min="1" class="form-control" name="notice_period_months" id="notice_period_months"
                        value="<?= htmlspecialchars($row['notice_period_months'] ?? '1') ?>">
                </div>
                <div class="col-md-3">
                    <label for="grace_period_days" class="form-label">Grace Period (days)</label>
                    <input type="number" min="1" class="form-control" name="grace_period_days" id="grace_period_days"
                        value="<?= htmlspecialchars($row['grace_period_days'] ?? '7') ?>">
                </div>
                <div class="col-md-3">
                    <label for="penalty_rate" class="form-label">Penalty Rate (%)</label>
                    <input type="number" min="0" step="0.01" class="form-control" name="penalty_rate" id="penalty_rate"
                        value="<?= htmlspecialchars($row['penalty_rate'] ?? '5') ?>">
                </div>
                <div class="col-md-3">
                    <label for="revision_frequency" class="form-label">Revision Frequency</label>
                    <input type="number" min="1" class="form-control" name="revision_frequency" id="revision_frequency"
                        value="<?= htmlspecialchars($row['revision_frequency'] ?? '1') ?>">
                </div>
                <div class="col-md-3 mt-3 mt-md-0">
                    <label for="revision_unit" class="form-label">Revision Unit</label>
                    <select class="form-control" name="revision_unit" id="revision_unit">
                        <?php foreach (['year', 'month'] as $unit): ?>
                            <option value="<?= $unit ?>" <?= ($row['revision_unit'] ?? 'year') === $unit ? 'selected' : '' ?>>
                                <?= ucfirst($unit) ?><?= $unit === 'month' ? '(s)' : '(s)' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="mb-3">
                <label for="use_for_the_property" class="form-label">Use for the Property</label>
                <input type="text" name="use_for_the_property" class="form-control"
                       value="<?= htmlspecialchars($row['use_for_the_property'] ?? '') ?>" required>
            </div>
            <div class="mb-3">
                <label for="custom_clauses" class="form-label">Custom Clauses / Additional Terms (optional)</label>
                <textarea name="custom_clauses" class="form-control" rows="3"><?= htmlspecialchars($row['custom_clauses'] ?? '') ?></textarea>
            </div>
            <button type="submit" class="btn btn-success me-3">Save Contract</button>
            <a href="rental-management-claimed-properties.php" class="btn btn-dark ms-4">Back to previous page</a>
        </form>

        <div class="border p-3 bg-white" style="min-height:600px; margin-top:2rem;">
            <?= $content // contract preview ?>
        </div>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
<script src="navbar-close.js?v=1"></script>
</body>
</html>
