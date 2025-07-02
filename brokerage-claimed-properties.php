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
    SELECT cc.*, u.full_name AS client_name, p.property_name, p.location, p.price, p.listing_type, p.request_id AS request_id,
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

function getAvailableAgentSlots($pdo, $agentId, $slotMinutes = 120, $daysAhead = 7) {
    $slots = [];
    $startHour = 9;  // 9 AM
    $endHour = 19;   // 7 PM
    $now = new DateTime();
    $today = clone $now;
    $today->setTime($startHour, 0);

    for ($d = 0; $d < $daysAhead; $d++) {
        $date = clone $today;
        $date->modify("+$d day");
        for ($h = $startHour; $h <= $endHour - ($slotMinutes / 60); $h++) {
            $slotStart = clone $date;
            $slotStart->setTime($h, 0);
            $slotEnd = clone $slotStart;
            $slotEnd->modify("+$slotMinutes minutes");

            // Check for overlaps in agent_schedule
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM agent_schedule
                WHERE agent_id = ? AND status = 'booked'
                AND (
                    (start_time < ? AND end_time > ?) -- overlap
                )
            ");
            $stmt->execute([$agentId, $slotEnd->format('Y-m-d H:i:s'), $slotStart->format('Y-m-d H:i:s')]);
            $overlap = $stmt->fetchColumn();
            if (!$overlap && $slotStart > $now) {
                $slots[] = [
                    'start' => $slotStart->format('Y-m-d H:i:s'),
                    'end' => $slotEnd->format('Y-m-d H:i:s'),
                ];
            }
        }
    }
    return $slots;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Brokerage Reserved Properties</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-4Q6Gf2aSP4eDXB8Miphtr37CMZZQ5oXLH2yaXMJ2w8e2ZtHTl7GptT4jmndRuHDT" crossorigin="anonymous">
    <link rel="stylesheet" href="styles.css?v=<?= time() ?>">
</head>
<body class="d-flex flex-column min-vh-100">
<?php include 'header.php'; ?>

<div class="container-fluid flex-grow-1">
    <div class="row">
        <main class="p-3">
            <div class="mb-4 p-3 border rounded shadow-sm main-title">
                <h2>Brokerage Reserved Properties</h2>
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
                        <th>Client - Owner Meeting</th>
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
    <?php
    // Fetch inspection agent for this property (default meeting agent)
    $inspectionAgentStmt = $pdo->prepare("
        SELECT assigned_agent_id FROM owner_service_requests WHERE request_id = ? LIMIT 1
    ");
    $inspectionAgentStmt->execute([$claim['request_id']]);
    $inspectionAgentId = $inspectionAgentStmt->fetchColumn();

    // Which agent to display?
    $displayAgentId = $claim['meeting_agent_id'] ?: $inspectionAgentId;
    $agentName = '';
    if ($displayAgentId) {
        $nameStmt = $pdo->prepare("SELECT full_name FROM staff WHERE staff_id = ?");
        $nameStmt->execute([$displayAgentId]);
        $agentName = $nameStmt->fetchColumn();
    }

    // Fetch available agent slots if agent assigned and no meeting yet
    $agentSlots = [];
    if ($inspectionAgentId && !$claim['meeting_datetime'] && $claim['confirmed_by']) {
        $agentSlots = getAvailableAgentSlots($pdo, $inspectionAgentId, 120, 7);
    }
    ?>

    <?php if ($claim['meeting_datetime']): ?>
        <div>
            <span class="fw-semibold"><?= date('Y-m-d H:i', strtotime($claim['meeting_datetime'])) ?></span>
            <br>
            <span class="text-muted small"><?= $agentName ? 'Agent: ' . htmlspecialchars($agentName) : 'Agent: Pending' ?></span>
        </div>
    <?php elseif ($claim['confirmed_by'] && $inspectionAgentId): ?>
        <form method="post" action="submit-claim-updates.php" class="d-flex flex-column gap-2">
            <input type="hidden" name="claim_id" value="<?= $claim['claim_id'] ?>">
            <input type="hidden" name="meeting_agent_id" value="<?= $inspectionAgentId ?>">
            <div class="mb-1 small text-muted">
                Agent: <strong><?= htmlspecialchars($agentName) ?></strong>
            </div>
            <select name="meeting_datetime" class="form-select form-select-sm mb-1" required>
                <option value="">Select Available Slot</option>
                <?php foreach ($agentSlots as $slot): ?>
                <option value="<?= htmlspecialchars($slot['start']) ?>">
                    <?= date('D, M j Y, H:i', strtotime($slot['start'])) ?> -
                    <?= date('H:i', strtotime($slot['end'])) ?>
                </option>
                <?php endforeach; ?>
                <?php if (empty($agentSlots)): ?>
                <option disabled>No available slots</option>
                <?php endif; ?>
            </select>
            <button type="submit" name="set_meeting_agent" value="1" class="btn btn-sm custom-btn" <?= empty($agentSlots) ? 'disabled' : '' ?>>Set Meeting</button>
        </form>
    <?php elseif (!$claim['confirmed_by']): ?>
        <span class="text-muted">Awaiting payment</span>
    <?php else: ?>
        <span class="text-muted">No agent assigned yet</span>
    <?php endif; ?>
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

<!-- Script to close main navbar on small screen-->
<script src="navbar-close.js?v=1"></script>
</body>
</html>
