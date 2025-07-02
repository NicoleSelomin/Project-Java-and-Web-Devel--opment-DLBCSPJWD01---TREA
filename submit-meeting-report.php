<?php
/**
 * ============================================================================
 * submit-meeting-report.php — TREA Real Estate Platform
 * ------------------------------------------------
 * Purpose: 
 *   Allows a field agent to submit a summary report for a scheduled meeting 
 *   between a client and property owner for a claimed property. Generates a 
 *   PDF with Dompdf, saves it, and updates the claim record.
 * 
 * Usage/Workflow:
 *   - Only accessible to logged-in staff users with 'field agent' role.
 *   - Validates claim ownership/assignment to the agent.
 *   - Submits and saves meeting summary as PDF under 
 *     /uploads/clients/{client_id}_{client_name}/meetings/claim_{claim_id}/.
 *   - Updates the claim record with PDF path and meeting timestamp.
 *   - Returns agent to assignments page upon success.
 * 
 * Dependencies:
 *   - Bootstrap 5.3.6 for responsive UI
 *   - Dompdf for PDF generation
 *   - Relies on TREA session/auth and shared header/footer includes
 *   - Requires database connectivity via db_connect.php
 */

// Start session and include required files/libraries
session_start();
require_once 'libs/dompdf/autoload.inc.php';
require 'db_connect.php';
use Dompdf\Dompdf;

// --- Access Control: Ensure field agent is logged in ---
if (!isset($_SESSION['staff_id']) || strtolower($_SESSION['role']) !== 'field agent') {
    header("Location: staff-login.php");
    exit();
}

$agent_id = $_SESSION['staff_id'];
$claim_id = $_GET['id'] ?? null;

// --- Validate claim ID ---
if (!$claim_id || !is_numeric($claim_id)) {
    echo "Invalid claim ID.";
    exit();
}

// --- Fetch claim info and ensure agent assignment ---
$stmt = $pdo->prepare("
    SELECT cc.*, u.full_name AS client_name, cl.client_id, p.property_name
    FROM client_claims cc
    JOIN clients cl ON cc.client_id = cl.client_id
    JOIN users u ON cl.user_id = u.user_id
    JOIN properties p ON cc.property_id = p.property_id
    WHERE cc.claim_id = :claim_id AND cc.meeting_agent_id = :agent_id
");
$stmt->execute([
    'claim_id' => $claim_id,
    'agent_id' => $agent_id
]);
$claim = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$claim) {
    echo "Meeting assignment not found or unauthorized.";
    exit();
}

$client_id = $claim['client_id'];
// Clean client name for use in folder paths
$client_name_clean = preg_replace("/[^a-zA-Z0-9]+/", "_", strtolower($claim['client_name']));

$error = "";

// --- Handle Form Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $report_text = trim($_POST['report_text'] ?? '');
    $report_date = date('Y-m-d H:i:s');

    // --- Validate ---
    if (!$report_text) {
        $error = "Please enter a report summary.";
    } else {
        // --- Define file/folder paths for storing PDF ---
        $folderPath = "uploads/clients/{$client_id}_{$client_name_clean}/meetings/claim_{$claim_id}/";
        if (!is_dir($folderPath)) {
            mkdir($folderPath, 0777, true); // recursive create
        }
        $filePath = $folderPath . "meeting-report.pdf";

        // --- Generate PDF using Dompdf ---
        $dompdf = new Dompdf();
        $html = "
            <h2>Owner–Client Meeting Report</h2>
            <p><strong>Date:</strong> {$report_date}</p>
            <p><strong>Agent:</strong> " . htmlspecialchars($_SESSION['full_name']) . "</p>
            <p><strong>Property:</strong> " . htmlspecialchars($claim['property_name']) . "</p>
            <p><strong>Client:</strong> " . htmlspecialchars($claim['client_name']) . "</p>
            <h4>Summary:</h4>
            <p>" . nl2br(htmlspecialchars($report_text)) . "</p>
        ";
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        file_put_contents($filePath, $dompdf->output());

        // --- Update claim record with report path and timestamp ---
        $stmt = $pdo->prepare("
            UPDATE client_claims 
            SET meeting_report_path = ?, meeting_datetime = ? 
            WHERE claim_id = ?
        ");
        $stmt->execute([$filePath, $report_date, $claim_id]);

        // --- Redirect to assignments page with success indicator ---
        header("Location: agent-assignments.php?report=success");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Standard meta and Bootstrap for responsive layout -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Submit Meeting Report | TREA Platform</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <link rel="stylesheet" href="styles.css?v=<?= time() ?>">
</head>
<body class="d-flex flex-column min-vh-100 bg-light">
<?php include 'header.php'; ?>

<div class="container py-4 flex-grow-1">
    <div class="row justify-content-center">
        <main class="col-12 col-md-10 col-lg-8">
            <!-- Card-style block for main form area -->
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h2 class="card-title mb-4">Submit Owner–Client Meeting Report</h2>

                    <!-- Display key meeting details -->
                    <div class="mb-3">
                        <p><strong>Property:</strong> <?= htmlspecialchars($claim['property_name']) ?></p>
                        <p><strong>Client:</strong> <?= htmlspecialchars($claim['client_name']) ?></p>
                        <p><strong>Meeting Date:</strong>
                            <?= $claim['meeting_datetime'] ? date('Y-m-d H:i', strtotime($claim['meeting_datetime'])) : 'Not set' ?>
                        </p>
                    </div>

                    <!-- Error message, if any -->
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <!-- Meeting summary report form -->
                    <form method="POST" novalidate>
                        <div class="mb-3">
                            <label for="report_text" class="form-label">Meeting Summary <span class="text-danger">*</span></label>
                            <textarea name="report_text" id="report_text" class="form-control" rows="7" required><?= htmlspecialchars($_POST['report_text'] ?? '') ?></textarea>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn custom-btn">Submit Report</button>
                            <a href="agent-assignments.php" class="btn btn-outline-danger">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include 'footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>

<!-- Script to close main navbar on small screen-->
<script src="navbar-close.js?v=1"></script>
</body>
</html>
