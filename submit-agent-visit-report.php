<?php
/**
 * ============================================================================
 * submit-agent-visit-report.php — TREA Real Estate Platform
 * ------------------------------------------------
 * Allows field agents to submit a report on a client onsite property visit.
 * - Restricts access to logged-in field agents.
 * - Displays details of the assigned visit.
 * - Handles submission, PDF generation (Dompdf), file storage, and DB update.
 * - Responsive, Bootstrap-based design.
 * -------------------------------------------------------------------------------
 */

require_once 'libs/dompdf/autoload.inc.php';
require 'db_connect.php';
session_start();

use Dompdf\Dompdf;

// 1. Access Control: Restrict to field agents only
if (
    !isset($_SESSION['staff_id']) ||
    strtolower($_SESSION['role']) !== 'field agent'
) {
    header("Location: staff-login.php");
    exit();
}

$agentId = $_SESSION['staff_id'];

// 2. Validate visit ID from query
$visit_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($visit_id <= 0) {
    echo "Invalid or missing parameters.";
    exit();
}

// 3. Fetch visit details for this agent and visit
$stmt = $pdo->prepare("
    SELECT v.*, u.full_name AS client_name, p.property_name, p.location
    FROM client_onsite_visits v
    JOIN clients c ON v.client_id = c.client_id
    JOIN users u ON c.user_id = u.user_id
    JOIN properties p ON v.property_id = p.property_id
    WHERE v.visit_id = ? AND v.assigned_agent_id = ?
    LIMIT 1
");
$stmt->execute([$visit_id, $agentId]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$data) {
    echo "Unauthorized access or visit not found.";
    exit();
}

// 4. Handle POST: Submit report
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $feedback = trim($_POST['agent_feedback'] ?? '');
    $result   = $_POST['result'] ?? '';

    // Simple server-side validation
    if ($feedback === '' || $result === '') {
        $error = "Feedback and result are required.";
    } else {
        // 4a. Generate a clean PDF using Dompdf
        $dompdf = new Dompdf();
        $html = '
            <style>
                body { font-family: Arial, sans-serif; }
                h2 { margin-bottom: 20px; }
                p { margin-bottom: 10px; }
                .label { font-weight: bold; }
            </style>
            <h2>Client Visit Report</h2>
            <p><span class="label">Agent:</span> ' . htmlspecialchars($_SESSION['full_name']) . '</p>
            <p><span class="label">Client:</span> ' . htmlspecialchars($data['client_name']) . '</p>
            <p><span class="label">Property:</span> ' . htmlspecialchars($data['property_name']) . ' - ' . htmlspecialchars($data['location']) . '</p>
            <p><span class="label">Visit Date:</span> ' . htmlspecialchars($data['visit_date']) . ' at ' . htmlspecialchars($data['visit_time']) . '</p>
            <p><span class="label">Result:</span> ' . htmlspecialchars(ucfirst($result)) . '</p>
            <p><span class="label">Feedback:</span><br>' . nl2br(htmlspecialchars($feedback)) . '</p>
        ';
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        // 4b. Store PDF in correct client folder
        $client_id   = $data['client_id'];
        $client_name = preg_replace('/[^a-zA-Z0-9_]/', '_', strtolower($data['client_name']));
        $visit_folder = "uploads/clients/{$client_id}_{$client_name}/visit_{$visit_id}/";
        if (!is_dir($visit_folder)) {
            mkdir($visit_folder, 0777, true);
        }
        $report_path = $visit_folder . "agent_report.pdf";
        file_put_contents($report_path, $dompdf->output());

        // 4c. Update visit record
        $update = $pdo->prepare("
            UPDATE client_onsite_visits
            SET agent_feedback = ?, report_result = ?, visit_report_path = ?
            WHERE visit_id = ?
        ");
        $update->execute([$feedback, $result, $report_path, $visit_id]);

        // 4d. Redirect back to assignments
        header("Location: agent-assignments.php?submitted=1");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!--
        Submit Client Visit Report | TREA Platform
        Responsive, clean, Bootstrap 5.3.6
    -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Client Visit Report</title>
    <!-- Bootstrap 5.3.6 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Main stylesheet (with cache-busting) -->
    <link rel="stylesheet" href="styles.css?v=<?= time() ?>">
</head>
<body class="d-flex flex-column min-vh-100">
    <?php include 'header.php'; ?>

    <main class="container py-5">
        <!-- Main Title -->
        <div class="mb-4 p-3 border rounded shadow-sm main-title bg-white">
            <h2 class="mb-0 fs-3">Submit Client Visit Report</h2>
        </div>

        <!-- Visit details summary -->
        <div class="mb-4">
            <p class="mb-1"><strong>Client:</strong> <?= htmlspecialchars($data['client_name']) ?></p>
            <p class="mb-1"><strong>Property:</strong> <?= htmlspecialchars($data['property_name']) ?> - <?= htmlspecialchars($data['location']) ?></p>
            <p class="mb-1"><strong>Visit Date:</strong> <?= htmlspecialchars($data['visit_date']) ?> at <?= htmlspecialchars($data['visit_time']) ?></p>
        </div>

        <!-- Error alert -->
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Report form -->
        <form method="POST" class="bg-white p-4 rounded border shadow-sm" autocomplete="off" novalidate>
            <div class="mb-3">
                <label class="form-label fw-bold">Visit Result:</label>
                <div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="result" id="resultPositive" value="positive" required>
                        <label class="form-check-label" for="resultPositive">Positive</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="result" id="resultNegative" value="negative" required>
                        <label class="form-check-label" for="resultNegative">Negative</label>
                    </div>
                </div>
            </div>
            <div class="mb-4">
                <label for="agent_feedback" class="form-label fw-bold">Agent Feedback:</label>
                <textarea name="agent_feedback" id="agent_feedback" rows="6" class="form-control" required></textarea>
            </div>
            <button type="submit" class="btn custom-btn px-4">Submit Report</button>
        </form>

        <!-- Back link -->
        <div class="mt-4">
            <a href="agent-assignments.php" class="btn btn-outline-secondary btn-sm">← Back to Assignments</a>
        </div>
    </main>

    <?php include 'footer.php'; ?>
    <!-- Bootstrap Bundle JS (Popper + Bootstrap) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Script to close main navbar on small screen-->
<script src="navbar-close.js?v=1"></script>
</body>
</html>
