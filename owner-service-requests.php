<?php
/*
|--------------------------------------------------------------------------
| owner-service-requests.php
|--------------------------------------------------------------------------
| For property owners to view, track, and manage their service requests.
| Includes payment proof uploads, invoice viewing, contract signing,
| and tracks status and listing.
|
| Features:
| - 2 tabs: "Active (Listed)" and "Inactive (Not Yet Listed)"
| - Consistent, responsive Bootstrap 5.3.6 layout
| - Clean session-based flash messages
| - File upload handling (payment proof)
|--------------------------------------------------------------------------
*/

// Session and includes
session_start();
require_once 'check-user-session.php';
require 'db_connect.php';

// ------------- Owner Info -------------
$userId = $_SESSION['owner_id'];
$fullName = $_SESSION['user_name'] ?? 'Unknown Owner';

// ------------- Handle Payment Proof Upload -------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['payment_proof'])) {
    $requestId = $_POST['request_id'] ?? null;
    $paymentType = 'application';

    if (empty($requestId)) {
        $_SESSION['error_message'] = "Missing request ID.";
        header("Location: " . $_SERVER['REQUEST_URI']); exit;
    }

    // Get folder info for upload path
    $infoStmt = $pdo->prepare("
        SELECT r.owner_id, u.full_name, r.service_id, s.slug
        FROM owner_service_requests r
        JOIN owners o ON r.owner_id = o.owner_id
        JOIN users u ON o.user_id = u.user_id
        JOIN services s ON r.service_id = s.service_id
        WHERE r.request_id = ?
    ");
    $infoStmt->execute([$requestId]);
    $info = $infoStmt->fetch(PDO::FETCH_ASSOC);

    if (!$info) {
        $_SESSION['error_message'] = "Request info not found.";
        header("Location: " . $_SERVER['REQUEST_URI']); exit;
    }

    // Build structured folder path
    $ownerFolder = $info['owner_id'] . '_' . preg_replace('/[^a-z0-9_]/i', '_', $info['full_name']);
    $serviceFolder = $info['service_id'] . '_' . $info['slug'];
    $targetDir = "uploads/owner/{$ownerFolder}/applications/{$serviceFolder}/request_{$requestId}/";
    if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);

    // Save file
    $originalName = basename($_FILES['payment_proof']['name']);
    $timestamp = time();
    $sanitizedFile = "{$paymentType}_payment_proof_{$timestamp}_" . preg_replace('/[^a-zA-Z0-9\._-]/', '_', $originalName);
    $targetPath = $targetDir . $sanitizedFile;

    if (move_uploaded_file($_FILES['payment_proof']['tmp_name'], $targetPath)) {
        $stmt = $pdo->prepare("
            UPDATE service_request_payments 
            SET payment_proof = ?, payment_status = 'pending', updated_at = NOW()
            WHERE request_id = ? AND payment_type = ?
        ");
        $stmt->execute([$targetPath, $requestId, $paymentType]);
        $_SESSION['success_message'] = "Payment proof uploaded successfully!";
    } else {
        $_SESSION['error_message'] = "File upload failed.";
    }
    header("Location: " . $_SERVER['REQUEST_URI']); exit;
}

// ------------- Fetch Service Requests -------------
$stmt = $pdo->prepare("
SELECT r.*, s.service_name, s.slug,
    app.invoice_path AS application_invoice_path,
    app.payment_proof AS application_payment_proof,
    app.payment_status AS application_payment_status
FROM owner_service_requests r
JOIN services s ON r.service_id = s.service_id
LEFT JOIN service_request_payments app ON app.request_id = r.request_id AND app.payment_type = 'application'
WHERE r.owner_id = ?
ORDER BY r.submitted_at DESC
");
$stmt->execute([$userId]);
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ------------- Split Requests for Tabs -------------
$activeRequests = [];
$inactiveRequests = [];
foreach ($requests as $row) {
    if (!empty($row['listed']) && $row['listed'] == 1) {
        $inactiveRequests[] = $row;
    } else {
        $activeRequests[] = $row;
    }
}

// ------------- Preload Contract Data for Buttons -------------
$requestIds = array_column($requests, 'request_id');

$contracts = [];
if ($requestIds) {
    $placeholders = implode(',', array_fill(0, count($requestIds), '?'));
    $contractStmt = $pdo->prepare("SELECT * FROM owner_service_requests WHERE request_id IN ($placeholders)");
    $contractStmt->execute($requestIds);
    foreach ($contractStmt->fetchAll(PDO::FETCH_ASSOC) as $c) {
        $contracts[$c['request_id']] = $c;
    }
}

// ------------- Progress Stage Helper -------------
function getOwnerProgress($row) {
    if (empty($row['application_invoice_path'])) 
        return ['Awaiting Invoice', 10];
    if (empty($row['application_payment_proof'])) 
        return ['Awaiting Payment', 25];
    if (!empty($row['application_payment_proof']) && strtolower($row['application_payment_status']) !== 'confirmed') 
        return ['Payment Submitted', 50];
    if (strtolower($row['application_payment_status']) === 'confirmed' && strtolower($row['status']) !== 'approved') 
        return ['Payment Confirmed', 65];
    if (strtolower($row['status']) === 'approved' && (strtolower($row['application_payment_status']) === 'confirmed')) {
        if (empty($row['listed'])) return ['Approved - Awaiting Listing', 80];
        return ['Listed', 100];
    }
    if (strtolower($row['status']) === 'rejected') 
        return ['Rejected', 0];
    return ['Processing', 30];
}

// ------------- Helper to Render Table -------------
function renderServiceRequestsTable($requests, $contracts) {
    if (!is_array($requests)) $requests = [];
    foreach ($requests as $r): ?>
    <tr>
        <td><?= htmlspecialchars($r['service_name']) ?></td>
        <td><?= htmlspecialchars($r['property_name'] ?? '-') ?></td>
        <td><?= htmlspecialchars($r['location'] ?? '-') ?></td>
        <td>
            <?php if (!empty($r['application_invoice_path'])): ?>
                <a href="<?= htmlspecialchars($r['application_invoice_path']) ?>" target="_blank">📄 View</a>
            <?php else: ?>
                <span class="text-muted">Pending</span>
            <?php endif; ?>
        </td>
        <td>
            <?php if (!empty($r['application_payment_proof'])): ?>
                <a href="<?= htmlspecialchars($r['application_payment_proof']) ?>" target="_blank">🧾 Uploaded</a>
            <?php elseif (!empty($r['application_invoice_path'])): ?>
                <form method="POST" action="owner-service-requests.php" enctype="multipart/form-data" style="min-width:160px;">
                    <input type="hidden" name="request_id" value="<?= $r['request_id'] ?>">
                    <input type="file" name="payment_proof" class="form-control form-control-sm mb-1" required>
                    <button class="btn btn-sm custom-btn">Upload</button>
                </form>
            <?php else: ?>
                <span class="text-muted">Not Yet Invoiced</span>
            <?php endif; ?>
        </td>
        <td>
            <?php
                $payStatus = strtolower($r['application_payment_status'] ?? '');
                if ($payStatus === 'confirmed') {
                    echo "<span class='badge bg-success'>Confirmed</span>";
                } elseif ($payStatus === 'pending' && !empty($r['application_payment_proof'])) {
                    echo "<span class='badge bg-warning text-dark'>Awaiting Confirmation</span>";
                } else {
                    echo "<span class='text-muted'>-</span>";
                }
            ?>
        </td>
        <td>
            <?php
                $stat = strtolower($r['status']);
                if ($stat === 'approved') echo "<span class='badge bg-success'>Approved</span>";
                elseif ($stat === 'rejected') echo "<span class='badge bg-danger'>Rejected</span>";
                else echo "<span class='badge bg-secondary'>Pending</span>";
            ?>
        </td>
        <td>
            <?php if (
                !empty($r['application_payment_status']) &&
                strtolower($r['application_payment_status']) === 'confirmed' &&
                !empty($r['owner_contract_meeting'])
            ): ?>
            <?= date('Y-m-d H:i', strtotime($r['owner_contract_meeting'])) ?>
            <?php else: ?>
                <span class="text-muted">Not set</span>
                <?php endif; ?>
        </td>

        <td>
            <?php
            $contract = $contracts[$r['request_id']] ?? null;
            if ($contract) {
                if (empty($contract['contract_locked'])) {
                    echo "<span class='text-muted'>Drafting contract…</span>";
                } else {
                    // Get role for signature
                    $role = '';
                    if (isset($_SESSION['role'])) {
                        $role = strtolower(trim($_SESSION['role']));
                    } elseif (isset($_SESSION['user_type'])) {
                        $role = strtolower(trim($_SESSION['user_type']));
                        if ($role === 'property owner' || $role === 'property_owner') $role = 'owner';
                    }
                    $user_sign_column = '';
                    if ($role === 'owner') $user_sign_column = 'owner_signature';
                    if ($role === 'general manager') $user_sign_column = 'agency_signature';
                    $user_has_signed = $user_sign_column && !empty($contract[$user_sign_column]);
                    $all_signed = !empty($contract['owner_signature']) && !empty($contract['agency_signature']);
                    if (!$user_has_signed && $user_sign_column) {
                        echo '<a href="sign-contract.php?contract_id=' . $r['request_id'] . '" class="btn btn-sm btn-outline-primary">View & Sign</a><br>';
                    } else {
                        echo '<a href="sign-contract.php?contract_id=' . $r['request_id'] . '" class="btn btn-sm btn-outline-secondary">View Contract</a><br>';
                    }
                    if ($all_signed) {
                        echo "<br><a href='download-contract.php?contract_id={$r['request_id']}' class='btn btn-sm custom-btn mt-1'>Download PDF</a>";
                    } else {
                        echo '<br><span class="text-muted small">Awaiting all signatures…</span>';
                    }
                }
            } else {
                echo "<span class='text-muted'>Not available yet</span>";
            }
            ?>
        </td>
        <td>
            <?php
            if (!empty($r['listed']) && $r['listed'] == 1) {
                echo "<span class='badge bg-primary'>Listed</span>";
            } else {
                echo "<span class='badge bg-secondary'>Pending</span>";
            }
            ?>
        </td>
        <td style="min-width:170px;">
            <?php
            list($label, $percent) = getOwnerProgress($r);
            $color = ($percent === 0) ? 'bg-danger' : (($percent < 50) ? 'bg-warning' : (($percent < 90) ? 'bg-info' : 'bg-success'));
            ?>
            <div class="progress" style="height:22px;">
                <div class="progress-bar <?= $color ?> text-dark fw-bold" role="progressbar" style="width:<?= $percent ?>%;">
                    <?= htmlspecialchars($label) ?>
                </div>
            </div>
        </td>
    </tr>
    <?php endforeach;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">    
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Owner Service Requests - TREA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css?v=<?= time() ?>">
</head>
<body class="d-flex flex-column min-vh-100 bg-light">
<?php include 'header.php'; ?>

<div class="container-fluid flex-grow-1 py-4">
    <div class="row">
        <main class="col-12 col-md-11 ms-lg-5">

            <!-- Page Title -->
            <div class="p-3 mb-4 border rounded shadow-sm main-title">
                <h2>Service Requests – <?= htmlspecialchars($fullName) ?></h2>
            </div>

            <!-- Flash Messages -->
            <?php if (!empty($_SESSION['success_message'])): ?>
                <div class="alert alert-success"><?= $_SESSION['success_message'] ?></div>
                <?php unset($_SESSION['success_message']); ?>
            <?php elseif (!empty($_SESSION['error_message'])): ?>
                <div class="alert alert-danger"><?= $_SESSION['error_message'] ?></div>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>

            <!-- Bootstrap Tabs for Active/Inactive Requests -->
            <ul class="nav nav-tabs mb-3" id="serviceTabs" role="tablist">
              <li class="nav-item" role="presentation">
                <button class="nav-link active custom-btn" id="active-tab" data-bs-toggle="tab" data-bs-target="#active" type="button" role="tab" aria-controls="active" aria-selected="true">
                  Active (Listed)
                </button>
              </li>
              <li class="nav-item" role="presentation">
                <button class="nav-link custom-btn" id="inactive-tab" data-bs-toggle="tab" data-bs-target="#inactive" type="button" role="tab" aria-controls="inactive" aria-selected="false">
                  Inactive (Not Yet Listed)
                </button>
              </li>
            </ul>

            <!-- Tab Panes -->
            <div class="tab-content" id="serviceTabsContent">
              <!-- Active Requests Tab -->
              <div class="tab-pane fade show active" id="active" role="tabpanel" aria-labelledby="active-tab">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>Service</th>
                                <th>Property</th>
                                <th>Location</th>
                                <th>Application Invoice</th>
                                <th>Proof of Payment</th>
                                <th>Payment Confirmation</th>
                                <th>Status</th>
                                <th>Meeting Date and Time</th>
                                <th>Owner Contract Discussion Meeting</th>
                                <th>Contract & Signatures</th>
                                <th>Listing Status</th>
                                <th>Progress</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php renderServiceRequestsTable($activeRequests, $contracts); ?>
                        </tbody>
                    </table>
                    <?php if (empty($activeRequests)): ?>
                        <div class="alert alert-info text-center my-4">No active (listed) service requests yet.</div>
                    <?php endif; ?>
                </div>
              </div>

              <!-- Inactive Requests Tab -->
              <div class="tab-pane fade" id="inactive" role="tabpanel" aria-labelledby="inactive-tab">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>Service</th>
                                <th>Property</th>
                                <th>Location</th>
                                <th>Application Invoice</th>
                                <th>Proof of Payment</th>
                                <th>Payment Confirmation</th>
                                <th>Status</th>
                                <th>Meeting Date and Time</th>
                                <th>Owner Contract Discussion Meeting</th>
                                <th>Contract & Signatures</th>
                                <th>Listing Status</th>
                                <th>Progress</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php renderServiceRequestsTable($inactiveRequests, $contracts); ?>
                        </tbody>
                    </table>
                    <?php if (empty($inactiveRequests)): ?>
                        <div class="alert alert-info text-center my-4">No inactive (not yet listed) service requests.</div>
                    <?php endif; ?>
                </div>
              </div>
            </div>

            <a href="owner-profile.php" class="mt-4 btn bg-dark text-white fw-bold">🡰 Back to dashboard</a>
        </main>
    </div>
</div>

<?php include 'footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
<script src="navbar-close.js?v=1"></script>
</body>
</html>
