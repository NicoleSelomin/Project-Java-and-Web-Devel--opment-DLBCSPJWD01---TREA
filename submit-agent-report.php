<?php
/**
 * ============================================================================
 * submit-renagenttal-report.php — TREA Real Estate Platform
 * ------------------------------------------------ 
 * Field Agent report submission page for owner service requests (inspection, visit, or claim meeting).
 * 
 * - Only accessible to logged-in field agents.
 * - Allows agent to submit a property inspection report and result.
 * - Generates and saves a PDF report using Dompdf.
 * - Saves PDF to structured owner service request folder.
 * - Updates database with feedback, result, and report path.
 * - Responsive Bootstrap 5.3.6 layout.
 */

require_once 'libs/dompdf/autoload.inc.php';
require 'db_connect.php';
session_start();

use Dompdf\Dompdf;

// Enforce agent authentication
if (!isset($_SESSION['staff_id']) || strtolower($_SESSION['role']) !== 'field agent') {
    header("Location: staff-login.php");
    exit();
}

$agentId = $_SESSION['staff_id'];
$type = $_GET['type'] ?? '';
$request_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$allowedTypes = ['owner_inspection', 'client_visit', 'claim_meeting'];
if (!in_array($type, $allowedTypes) || $request_id <= 0) {
    echo "Invalid or missing parameters.";
    exit();
}

// Fetch the relevant service request assigned to this agent
$stmt = $pdo->prepare("
    SELECT r.*, u.full_name AS owner_name, s.service_name, s.service_id, s.slug AS service_slug 
    FROM owner_service_requests r
    JOIN owners o ON r.owner_id = o.owner_id
    JOIN users u ON o.user_id = u.user_id
    JOIN services s ON r.service_id = s.service_id
    WHERE r.request_id = ? AND r.assigned_agent_id = ?
");
$stmt->execute([$request_id, $agentId]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$data) {
    echo "Unauthorized or data not found.";
    exit();
}

// Values to repopulate on error
$old_feedback = "";
$old_result = "";
$error = "";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $feedback = trim($_POST['agent_feedback'] ?? '');
    $result = $_POST['result'] ?? null;
    $old_feedback = $feedback;
    $old_result = $result;

    // Required validation
    if (!$feedback || !$result) {
        $error = "Both inspection result and feedback are required.";
    } else {
        // Save agent feedback/result to DB
        $update = $pdo->prepare("UPDATE owner_service_requests SET inspection_result = ?, agent_feedback = ? WHERE request_id = ?");
        $update->execute([$result, $feedback, $request_id]);

        // Generate PDF using Dompdf
        $dompdf = new Dompdf();
        $html = "
            <h2>Inspection Report</h2>
            <p><strong>Agent:</strong> " . htmlspecialchars($_SESSION['full_name']) . "</p>
            <p><strong>Owner:</strong> " . htmlspecialchars($data['owner_name']) . "</p>
            <p><strong>Service:</strong> " . htmlspecialchars($data['service_name']) . "</p>
            <p><strong>Property:</strong> " . htmlspecialchars($data['property_name']) . " - " . htmlspecialchars($data['location']) . "</p>
            <p><strong>Inspection Date:</strong> " . htmlspecialchars($data['inspection_date']) . "</p>
            <p><strong>Result:</strong> " . htmlspecialchars($result) . "</p>
            <p><strong>Feedback:</strong><br>" . nl2br(htmlspecialchars($feedback)) . "</p>
        ";
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        // Prepare and ensure folder
        $owner_id = $data['owner_id'];
        $owner_name = preg_replace('/[^a-zA-Z0-9_]/', '_', strtolower($data['owner_name']));
        $service_id = $data['service_id'];
        $service_slug = preg_replace('/[^a-zA-Z0-9_]/', '_', strtolower($data['service_slug']));
        $request_folder = "uploads/owner/{$owner_id}_{$owner_name}/applications/{$service_id}_{$service_slug}/request_{$request_id}/";

        if (!is_dir($request_folder)) {
            mkdir($request_folder, 0755, true);
        }

        // Save the PDF
        $filePath = $request_folder . "agent_report.pdf";
        file_put_contents($filePath, $dompdf->output());

        // Update DB with PDF path
        $stmt = $pdo->prepare("UPDATE owner_service_requests SET agent_report_path = ? WHERE request_id = ?");
        $stmt->execute([$filePath, $request_id]);

        // Redirect to assignments with success
        header("Location: agent-assignments.php?submitted=1");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Meta and title -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Submit Inspection Report</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css?v=<?= time() ?>">
</head>
<body class="d-flex flex-column min-vh-100 bg-light">
<?php include 'header.php'; ?>

<main class="container py-5 flex-grow-1">
    <div class="row justify-content-center">
        <div class="col-12 col-md-10 col-lg-8 col-xl-7 p-4 border rounded shadow-sm bg-white">
            <div class="mb-4">
                <h2 class="mb-0">Submit Property Inspection Report</h2>
            </div>
            <div class="mb-4">
                <p><strong>Owner:</strong> <?= htmlspecialchars($data['owner_name']) ?></p>
                <p><strong>Service:</strong> <?= htmlspecialchars($data['service_name']) ?></p>
                <p><strong>Property:</strong> <?= htmlspecialchars($data['property_name']) ?> - <?= htmlspecialchars($data['location']) ?></p>
                <p><strong>Inspection Date:</strong>
                    <?= $data['inspection_date'] ? date('F j, Y g:i A', strtotime($data['inspection_date'])) : 'Not Set' ?>
                </p>
            </div>

            <!-- Error message, if any -->
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" class="mb-2">
                <div class="mb-3">
                    <label class="form-label mb-1">Inspection Result <span class="text-danger">*</span></label>
                    <div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="result" id="result-passed" value="passed" required <?= $old_result === "passed" ? "checked" : "" ?>>
                            <label class="form-check-label" for="result-passed">Passed</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="result" id="result-failed" value="failed" required <?= $old_result === "failed" ? "checked" : "" ?>>
                            <label class="form-check-label" for="result-failed">Failed</label>
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label mb-1">Agent Feedback <span class="text-danger">*</span></label>
                    <textarea name="agent_feedback" rows="6" class="form-control" required><?= htmlspecialchars($old_feedback) ?></textarea>
                </div>
                <button type="submit" class="btn btn-primary w-100">Submit Report</button>
            </form>

            <a href="agent-assignments.php" class="btn btn-outline-secondary w-100 mt-2">Back to Assignments</a>
        </div>
    </div>
</main>

<?php include 'footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
