<?php
// -----------------------------------------------------------------------------
// client-claimed-sale-management.php
// Shows all sale-managed property claims for the logged-in client, including
// all payment, meeting, contract, and legal assistance status
// -----------------------------------------------------------------------------

session_start();
require_once 'check-user-session.php';
require 'db_connect.php';

// -----------------------------------------------------------------------------
// 1. Get current client ID
// -----------------------------------------------------------------------------
$client_id = $_SESSION['client_id'];

// -----------------------------------------------------------------------------
// 2. Fetch all claims (sale management properties) for this client
//    - Join property, contract, payment, agent, etc.
// -----------------------------------------------------------------------------
$stmt = $pdo->prepare("
    SELECT cc.claim_id, cc.property_id, cc.claim_status, 
           cc.meeting_datetime, cc.meeting_agent_id, cc.meeting_report_path, cc.meeting_report_summary,
           p.property_name, p.image, p.price, p.owner_id, p.listing_type,
           sc.contract_file,
           sp.advance_invoice, sp.advance_proof, sp.advance_status,
           sp.balance_invoice, sp.balance_proof, sp.balance_status,
           sp.legal_invoice, sp.legal_status, sp.legal_started_at, sp.legal_completed_at,
           s.full_name AS agent_name
    FROM client_claims cc
    JOIN properties p ON cc.property_id = p.property_id
    LEFT JOIN sale_contracts sc ON cc.claim_id = sc.claim_id
    LEFT JOIN sale_claim_payments sp ON cc.claim_id = sp.claim_id
    LEFT JOIN staff s ON cc.meeting_agent_id = s.staff_id
    WHERE cc.client_id = ? AND p.service_id IS NOT NULL
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
    <title>My Sale Management Claims</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css?v=<?= time() ?>"> <!-- force latest CSS -->
</head>
<body class="d-flex flex-column min-vh-100 bg-dark">
<?php include 'header.php'; ?>

<div class="container-fluid d-flex flex-grow-1 flex-column p-0">
    <div class="row flex-grow-1 g-0">
<main class="col-md-10 p-4 w-100">
    <div class="mb-4 p-3 border rounded shadow-sm main-title">
        <h2>My Claimed Sale-Managed Properties</h2>
    </div>

    <?php if (empty($claims)): ?>
        <!-- No claims yet for this client -->
        <div class="alert alert-info">You have not claimed any sale-managed properties yet.</div>
    <?php else: ?>
        <?php foreach ($claims as $claim): ?>
            <div class="card mb-4 shadow-sm">
                <div class="row g-0">
                    <!-- Property Image -->
                    <div class="col-md-4">
                        <img src="<?= file_exists($claim['image']) ? $claim['image'] : 'images/default.png' ?>" class="img-fluid" alt="Property Image">
                    </div>
                    <div class="col-md-8">
                        <div class="card-body">
                            <!-- Property Info -->
                            <h5><?= htmlspecialchars($claim['property_name']) ?></h5>
                            <p>
                                <strong>Price:</strong> CFA <?= number_format($claim['price']) ?><br>
                                <strong>Listing Type:</strong> <?= ucfirst($claim['listing_type']) ?><br>
                                <strong>Status:</strong> <?= ucfirst($claim['claim_status']) ?><br>
                                <strong>Meeting:</strong>
                                <?= $claim['meeting_datetime'] ? date('d M Y H:i', strtotime($claim['meeting_datetime'])) : 'Pending' ?><br>
                                <?php if ($claim['agent_name']): ?>
                                    <strong>Agent:</strong> <?= htmlspecialchars($claim['agent_name']) ?><br>
                                <?php endif; ?>
                            </p>

                            <hr>
                            <!-- 1. Advance Payment Section -->
                            <h6>📄 Advance Payment</h6>
                            <?php if ($claim['advance_status'] === 'confirmed'): ?>
                                <span class="text-success">✔ Confirmed</span>
                            <?php elseif ($claim['advance_invoice']): ?>
                                <p>Invoice: <a href="<?= $claim['advance_invoice'] ?>" target="_blank" class="btn btn-sm btn-outline-primary">View</a></p>
                                <?php if (!$claim['advance_proof']): ?>
                                    <!-- Upload advance payment proof form -->
                                    <form method="POST" enctype="multipart/form-data">
                                        <input type="hidden" name="claim_id" value="<?= $claim['claim_id'] ?>">
                                        <input type="hidden" name="type" value="advance">
                                        <input type="file" name="payment_proof" class="form-control form-control-sm mb-2" required>
                                        <button class="btn btn-sm btn-success">📤 Upload Payment Proof</button>
                                    </form>
                                <?php else: ?>
                                    <p>Payment Proof: <a href="<?= $claim['advance_proof'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary">View</a></p>
                                    <span class="text-warning">⏳ Awaiting Confirmation</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted">Invoice not issued yet</span>
                            <?php endif; ?>

                            <hr>
                            <!-- 2. Contract Section -->
                            <h6>📜 Signed Contract</h6>
                            <?php if ($claim['contract_file']): ?>
                                <p><a href="<?= $claim['contract_file'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary">Download Contract</a></p>
                            <?php else: ?>
                                <span class="text-muted">Not uploaded yet</span>
                            <?php endif; ?>

                            <hr>
                            <!-- 3. Balance Payment Section -->
                            <h6>💳 Balance Payment</h6>
                            <?php if ($claim['balance_status'] === 'confirmed'): ?>
                                <span class="text-success">✔ Confirmed</span>
                            <?php elseif ($claim['balance_invoice']): ?>
                                <p>Invoice: <a href="<?= $claim['balance_invoice'] ?>" target="_blank" class="btn btn-sm btn-outline-primary">View</a></p>
                                <?php if (!$claim['balance_proof']): ?>
                                    <!-- Upload balance payment proof form -->
                                    <form method="POST" enctype="multipart/form-data">
                                        <input type="hidden" name="claim_id" value="<?= $claim['claim_id'] ?>">
                                        <input type="hidden" name="type" value="balance">
                                        <input type="file" name="payment_proof" class="form-control form-control-sm mb-2" required>
                                        <button class="btn btn-sm btn-success">📤 Upload Payment Proof</button>
                                    </form>
                                <?php else: ?>
                                    <p>Payment Proof: <a href="<?= $claim['balance_proof'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary">View</a></p>
                                    <span class="text-warning">⏳ Awaiting Confirmation</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted">Invoice not issued yet</span>
                            <?php endif; ?>

                            <hr>
                            <!-- 4. Legal Assistance Section -->
                            <h6>⚖️ Legal Assistance</h6>
                            <?php if ($claim['legal_status'] === 'completed'): ?>
                                <span class="text-success">✔ Legal Process Completed</span>
                                <?php if ($claim['legal_completed_at']): ?>
                                    <p><strong>Completed On:</strong> <?= date('d M Y H:i', strtotime($claim['legal_completed_at'])) ?></p>
                                <?php endif; ?>
                            <?php elseif ($claim['legal_status'] === 'in_progress' || $claim['legal_started_at']): ?>
                                <span class="text-warning">⏳ Legal Process In Progress</span>
                                <?php if ($claim['legal_started_at']): ?>
                                    <p><strong>Started On:</strong> <?= date('d M Y H:i', strtotime($claim['legal_started_at'])) ?></p>
                                <?php endif; ?>
                            <?php elseif ($claim['legal_invoice']): ?>
                                <p>Legal Invoice: <a href="<?= $claim['legal_invoice'] ?>" target="_blank" class="btn btn-sm btn-outline-primary">View</a></p>
                                <span class="text-muted">Awaiting Legal Start</span>
                            <?php else: ?>
                                <span class="text-muted">Not Started</span>
                            <?php endif; ?>

                            <div class="mt-3">
                                <a href="view-property.php?id=<?= $claim['property_id'] ?>" class="btn btn-outline-primary btn-sm">🔍 View Property</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <div class="mt-4">
        <a href="client-profile.php" class="btn custom-btn">← Back to Profile</a>
    </div>
</main>
</div>
</div>

<?php include 'footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
