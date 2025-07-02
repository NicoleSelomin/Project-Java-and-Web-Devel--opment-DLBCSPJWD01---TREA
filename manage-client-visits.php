<?php
/**
 * manage-client-visits.php
 * ------------------------
 * Page for managers to view and manage all client onsite property visits.
 * Features:
 *   - View list of all scheduled client visits.
 *   - Assign field agents to visits.
 *   - View uploaded agent reports and reviews.
 *   - Track final status and claim status of each visit.
 * 
 * Access:
 *   - Only staff with role 'general manager' or 'property manager'.
 */

session_start();
require 'db_connect.php';

// Redirect if staff not authorized
if (!isset($_SESSION['staff_id']) || !in_array(strtolower($_SESSION['role']), ['general manager', 'property manager'])) {
    header("Location: staff-login.php");
    exit();
}

// Fetch all client onsite visits, with property, client, and agent info
$sql = "
SELECT 
    v.visit_id, v.visit_date, v.visit_time, v.assigned_agent_id, v.final_status, 
    v.agent_feedback, v.visit_report_path, v.review_pdf_path, v.report_result,
    u.full_name AS client_name, c.client_id,
    p.property_name, p.property_id, p.listing_type,
    s.full_name AS assigned_agent,
    osr.assigned_agent_id AS inspection_agent_id,
    s2.full_name AS inspection_agent_name
FROM client_onsite_visits v
JOIN clients c ON v.client_id = c.client_id
JOIN users u ON c.user_id = u.user_id
JOIN properties p ON v.property_id = p.property_id
LEFT JOIN owner_service_requests osr ON osr.request_id = p.request_id
LEFT JOIN staff s2 ON osr.assigned_agent_id = s2.staff_id
LEFT JOIN staff s ON v.assigned_agent_id = s.staff_id
ORDER BY v.visit_date DESC, v.visit_time DESC
";

$stmt = $pdo->query($sql);
$visits = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Auto-assign inspection agent to any visit missing an assigned agent
foreach ($visits as &$row) {
    if (!$row['assigned_agent_id'] && $row['inspection_agent_id']) {
        $update = $pdo->prepare("UPDATE client_onsite_visits SET assigned_agent_id = ? WHERE visit_id = ?");
        $update->execute([$row['inspection_agent_id'], $row['visit_id']]);
        $row['assigned_agent_id'] = $row['inspection_agent_id'];
        $row['assigned_agent'] = $row['inspection_agent_name'];
    }
}
unset($row);

?>

<!DOCTYPE html>
<html lang="en">
<head> 
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage Client Visits</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-4Q6Gf2aSP4eDXB8Miphtr37CMZZQ5oXLH2yaXMJ2w8e2ZtHTl7GptT4jmndRuHDT" crossorigin="anonymous">
    <link rel="stylesheet" href="styles.css?v=<?= time() ?>">
</head>
<body class="d-flex flex-column min-vh-100 bg-light">

<?php include 'header.php'; ?>

<div class="container-fluid d-flex flex-grow-1 flex-column p-0">
    <div class="row flex-grow-1 g-0" style="flex: 1 0 auto; min-height: calc(100vh - 120px);">

        <!-- Main content area -->
        <main class="col-12 p-4">
            <div class="mb-4 p-3 border rounded shadow-sm main-title bg-white">
                <h2>Manage Client Visits</h2>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered table-hover small bg-white">
                    <thead class="table-dark">
                        <tr>
                            <th>Client</th>
                            <th>Property</th>
                            <th>Visit Date</th>
                            <th>Assign Agent</th>
                            <th>Agent Report</th>
                            <th>Manager Review</th>
                            <th>Final Status</th>
                            <th>Reservation Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($visits as $row): ?>
                        <?php
                        // Prepare folder name for possible download paths (if needed)
                        $clientSlug = preg_replace('/[^a-zA-Z0-9_]/', '_', $row['client_name']);
                        $basePath = "uploads/clients/{$row['client_id']}_{$clientSlug}/visits/visit_{$row['visit_id']}";
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($row['client_name']) ?></td>
                            <td><?= htmlspecialchars($row['property_name']) ?> (<?= htmlspecialchars($row['listing_type']) ?>)</td>
                            <td><?= date('Y-m-d H:i', strtotime($row['visit_date'] . ' ' . $row['visit_time'])) ?></td>
                            <td>
                                <?php if ($row['assigned_agent_id']): ?>
                                    <?= htmlspecialchars($row['assigned_agent'] ?? $row['inspection_agent_name']) ?>
                                    <?php elseif ($row['inspection_agent_id']): ?>
                                        <?= htmlspecialchars($row['inspection_agent_name']) ?>
                                        <?php else: ?>
                                            <span class="text-muted">Not Assigned</span>
                                            <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($row['visit_report_path']): ?>
                                    <a href="<?= htmlspecialchars($row['visit_report_path']) ?>" target="_blank" aria-label="Agent Report">View Report</a>
                                <?php else: ?>
                                    <span class="text-muted">Pending</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($row['review_pdf_path']): ?>
                                    <a href="<?= htmlspecialchars($row['review_pdf_path']) ?>" target="_blank">View Review</a>
                                <?php elseif ($row['visit_report_path']): ?>
                                    <a href="review-client-visit.php?id=<?= $row['visit_id'] ?>" class="btn btn-sm custom-btn">Review</a>
                                <?php else: ?>
                                    <span class="text-muted">Awaiting report</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                if ($row['final_status'] === 'approved') {
                                    echo '<span class="text-success">Approved</span>';
                                } elseif ($row['final_status'] === 'rejected') {
                                    echo '<span class="text-danger">Rejected</span>';
                                } else {
                                    echo '<span class="text-muted">Pending</span>';
                                }
                                ?>
                            </td>
                            <td>
                                <?php
                                // Fetch claim status for this visit 
                                $stmt2 = $pdo->prepare("SELECT claim_status FROM client_claims WHERE visit_id = ?");
                                $stmt2->execute([$row['visit_id']]);
                                $claim = $stmt2->fetch();
                                echo $claim ? htmlspecialchars(ucfirst($claim['claim_status'])) : '—';
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <a href="staff-profile.php" class="btn btn-outline-secondary mb-4">Back to Profile</a>
        </main>
    </div>
</div>

<?php include 'footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js" integrity="sha384-j1CDi7MgGQ12Z7Qab0qlWQ/Qqz24Gc6BM0thvEMVjHnfYGF0rmFCozFSxQBxwHKO" crossorigin="anonymous"></script>

<!-- Script to close main navbar on small screen-->
<script src="navbar-close.js?v=1"></script>

</body>
</html>
