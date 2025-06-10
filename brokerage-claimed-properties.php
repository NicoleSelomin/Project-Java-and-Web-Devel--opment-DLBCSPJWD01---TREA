<?php
// -----------------------------------------------------------------------------
// brokerage-claimed-properties.php
// -----------------------------------------------------------------------------
// Displays a table of claimed properties under the Brokerage service.
// Lets staff view claim/payment status, set meetings/agents, and mark claims complete.
// -----------------------------------------------------------------------------

session_start();
require 'db_connect.php';

if (!isset($_SESSION['staff_id'])) {
    header("Location: staff-login.php");
    exit();
}

$fullName = $_SESSION['full_name'] ?? 'Staff';
$userId = $_SESSION['staff_id'] ?? '';
$profilePicture = $_SESSION['profile_picture_path'] ?? 'default.png';

// Fetch all field agents for selection in meeting assignment
$agentsStmt = $pdo->query("SELECT staff_id, full_name FROM staff WHERE role = 'Field Agent'");
$agents = $agentsStmt->fetchAll(PDO::FETCH_ASSOC);

// Get service_id for 'brokerage'
$brokerageServiceStmt = $pdo->prepare("SELECT service_id FROM services WHERE slug = 'brokerage'");
$brokerageServiceStmt->execute();
$brokerageServiceId = $brokerageServiceStmt->fetchColumn();

// Fetch all brokerage-claimed properties (joined to necessary related info)
$stmt = $pdo->prepare("
    SELECT cc.*, u.full_name AS client_name, p.property_name, p.location, p.price, p.listing_type,
           bcp.confirmed_by
    FROM client_claims cc
    JOIN clients cl ON cc.client_id = cl.client_id
    JOIN users u ON cl.user_id = u.user_id
    JOIN properties p ON cc.property_id = p.property_id
    JOIN owner_service_requests osr ON p.request_id = osr.request_id
    JOIN brokerage_claim_payments bcp ON cc.claim_id = bcp.claim_id
    WHERE osr.service_id = ? AND cc.claim_status = 'claimed'
    ORDER BY cc.claimed_at DESC
");
$stmt->execute([$brokerageServiceId]);
$claims_brokerage = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Brokerage Claimed Properties</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-4Q6Gf2aSP4eDXB8Miphtr37CMZZQ5oXLH2yaXMJ2w8e2ZtHTl7GptT4jmndRuHDT" crossorigin="anonymous">
    <link rel="stylesheet" href="styles.css?v=<?= time() ?>">
</head>
<body class="d-flex flex-column min-vh-100">
<?php include 'header.php'; ?>

<div class="container-fluid flex-grow-1">
    <div class="row">
        <main class="p-3">
            <div class="mb-4 p-3 border rounded shadow-sm main-title">
                <h2>Brokerage Claimed Properties</h2>
            </div>

            <!-- Claimed Properties Table -->
            <div class="table-responsive">
            <table class="table table-bordered table-hover small align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Client</th>
                        <th>Property</th>
                        <th>Type</th>
                        <th>Payment</th>
                        <th>Meeting</th>
                        <th>Agent</th>
                        <th>Report</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($claims_brokerage as $claim): ?>
                    <tr>
                        <!-- Client name -->
                        <td><?= htmlspecialchars($claim['client_name']) ?></td>
                        <!-- Property name and location -->
                        <td><?= htmlspecialchars($claim['property_name']) ?> (<?= htmlspecialchars($claim['location']) ?>)</td>
                        <!-- Claim type (Sale/Rent) -->
                        <td><?= ucfirst($claim['claim_type']) ?></td>
                        <!-- Payment status -->
                        <td>
                            <?php if ($claim['confirmed_by']): ?>
                                <span class="text-success">Paid</span>
                            <?php else: ?>
                                <span class="text-warning">Pending</span>
                            <?php endif; ?>
                        </td>
                        <!-- Meeting scheduling or display -->
                        <td>
                            <?php if ($claim['meeting_datetime']): ?>
                                <?= date('Y-m-d H:i', strtotime($claim['meeting_datetime'])) ?>
                            <?php elseif ($claim['confirmed_by']): ?>
                                <!-- Allow setting meeting after payment is confirmed -->
                                <form method="post" action="submit-claim-updates.php" class="d-flex flex-column gap-2">
                                    <input type="hidden" name="claim_id" value="<?= $claim['claim_id'] ?>">
                                    <div class="input-group input-group-sm mb-1">
                                        <input type="datetime-local" name="meeting_datetime" class="form-control" required>
                                    </div>
                                    <select name="meeting_agent_id" class="form-select form-select-sm mb-1" required>
                                        <option value="">Select Agent</option>
                                        <?php foreach ($agents as $agent): ?>
                                            <option value="<?= $agent['staff_id'] ?>"><?= htmlspecialchars($agent['full_name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" name="set_meeting_agent" value="1" class="btn btn-sm custom-btn">Set Meeting</button>
                                </form>
                            <?php else: ?>
                                <span class="text-muted">Awaiting payment</span>
                            <?php endif; ?>
                        </td>
                        <!-- Meeting agent name -->
                        <td>
                            <?php
                            if ($claim['meeting_agent_id']) {
                                $agentNameStmt = $pdo->prepare("SELECT full_name FROM staff WHERE staff_id = ?");
                                $agentNameStmt->execute([$claim['meeting_agent_id']]);
                                echo htmlspecialchars($agentNameStmt->fetchColumn());
                            } else {
                                echo '<span class="text-muted">Pending</span>';
                            }
                            ?>
                        </td>
                        <!-- Meeting report (view only if uploaded) -->
                        <td>
                            <?php if ($claim['meeting_report_path']): ?>
                                <a href="<?= htmlspecialchars($claim['meeting_report_path']) ?>" target="_blank">View</a>
                            <?php else: ?>
                                <span class="text-muted">Pending</span>
                            <?php endif; ?>
                        </td>
                        <!-- Complete action if all steps are done -->
                        <td>
                            <?php
                            if ($claim['final_status'] === 'completed') {
                                echo '<span class="text-success">Completed</span>';
                            } elseif ($claim['confirmed_by'] && $claim['meeting_report_path']) {
                                echo '<form method="post" action="submit-claim-updates.php">
                                        <input type="hidden" name="claim_id" value="' . $claim['claim_id'] . '">
                                        <input type="hidden" name="complete_claim" value="1">
                                        <button class="btn btn-sm custom-btn">Mark Complete</button>
                                    </form>';
                            } else {
                                echo '<span class="text-muted">Pending Steps</span>';
                            }
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <a href="staff-profile.php" class="btn custom-btn mb-3">Back to Profile</a>
        </main>
    </div>
</div>

<?php include 'footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js" integrity="sha384-j1CDi7MgGQ12Z7Qab0qlWQ/Qqz24Gc6BM0thvEMVjHnfYGF0rmFCozFSxQBxwHKO" crossorigin="anonymous"></script>
</body>
</html>
