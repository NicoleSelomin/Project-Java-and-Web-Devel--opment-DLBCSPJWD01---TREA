<?php
/*
|--------------------------------------------------------------------------
| review-client-visit.php
|--------------------------------------------------------------------------
| Allows authorized staff (manager) to review an onsite client visit.
| - Displays visit details and agent's submitted report.
| - Lets manager approve or reject the visit with optional reason.
| - Generates a signed PDF review and saves to client folder.
| - Updates visit status in the database.
| - Uses Bootstrap 5.3.6 for consistent, responsive UI.
|--------------------------------------------------------------------------
*/

require_once 'libs/dompdf/autoload.inc.php';
session_start();
require 'db_connect.php';

use Dompdf\Dompdf;

// --- Section 1: Access control ---
if (!isset($_SESSION['staff_id']) || !isset($_SESSION['role'])) {
    header("Location: staff-login.php");
    exit();
}
$staff_id = $_SESSION['staff_id'];

// --- Section 2: Get visit info ---
if (!isset($_GET['id'])) {
    echo "No visit selected.";
    exit();
}
$visit_id = intval($_GET['id']);

$stmt = $pdo->prepare("SELECT v.*, u.full_name AS client_name, p.property_name, p.location
                        FROM client_onsite_visits v
                        JOIN clients c ON v.client_id = c.client_id
                        JOIN users u ON c.user_id = u.user_id
                        JOIN properties p ON v.property_id = p.property_id
                        WHERE v.visit_id = ?");
$stmt->execute([$visit_id]);
$visit = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$visit) {
    echo "Visit not found.";
    exit();
}

// --- Section 3: Check agent report submission ---
if (empty($visit['visit_report_path'])) {
    echo "<div class='alert alert-warning m-5'>Agent report has not been submitted yet.</div>";
    exit();
}

// --- Section 4: Handle manager review submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $status = $_POST['status'] ?? '';
    $reviewed_at = date('Y-m-d H:i:s');
    $rejection_reason = trim($_POST['rejection_reason'] ?? '');

    // Get reviewer name
    $reviewerStmt = $pdo->prepare("SELECT full_name FROM staff WHERE staff_id = ?");
    $reviewerStmt->execute([$staff_id]);
    $reviewer = $reviewerStmt->fetchColumn();

    // --- Generate PDF of review ---
    $dompdf = new Dompdf();
    $html = "
        <h2>Client Visit Review</h2>
        <p><strong>Visit ID:</strong> $visit_id</p>
        <p><strong>Client:</strong> ".htmlspecialchars($visit['client_name'])."</p>
        <p><strong>Property:</strong> ".htmlspecialchars($visit['property_name'])." - ".htmlspecialchars($visit['location'])."</p>
        <p><strong>Decision:</strong> " . ucfirst($status) . "</p>
        <p><strong>Reviewed At:</strong> $reviewed_at</p>
        <p><strong>Reviewer:</strong> ".htmlspecialchars($reviewer)."</p>
    ";
    if ($status === 'rejected') {
        $html .= "<p><strong>Rejection Reason:</strong><br>" . nl2br(htmlspecialchars($rejection_reason)) . "</p>";
    }
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    // --- Save PDF to client-specific folder ---
    $client_id = $visit['client_id'];
    $client_name = preg_replace('/[^a-zA-Z0-9_]/', '_', strtolower($visit['client_name']));
    $folder = "uploads/clients/{$client_id}_{$client_name}/visit_{$visit_id}/";
    if (!is_dir($folder)) {
        mkdir($folder, 0777, true);
    }
    $filePath = $folder . "manager_review.pdf";
    file_put_contents($filePath, $dompdf->output());

    // --- Update visit status in DB ---
    $stmt = $pdo->prepare("UPDATE client_onsite_visits
                          SET final_status = ?, reviewed_at = ?, reviewed_by = ?, review_pdf_path = ?
                          WHERE visit_id = ?");
    $stmt->execute([$status, $reviewed_at, $staff_id, $filePath, $visit_id]);

    header("Location: manage-client-visits.php?reviewed=1");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">    
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Review Client Visit</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css?v=<?= time() ?>">
</head>
<body class="d-flex flex-column min-vh-100 bg-light text-dark">
<?php include 'header.php'; ?>

<div class="container d-flex flex-column justify-content-center align-items-center flex-grow-1 py-5">
    <main class="w-100" style="max-width: 560px;">
        <div class="mb-4 p-3 border rounded shadow-sm main-title bg-white">
            <h2>Review Client Visit</h2>
        </div>

        <!-- Section: Visit info -->
        <div class="mb-4 bg-white rounded shadow-sm p-3">
            <p><strong>Client:</strong> <?= htmlspecialchars($visit['client_name']) ?></p>
            <p><strong>Property:</strong> <?= htmlspecialchars($visit['property_name']) ?></p>
            <p><strong>Location:</strong> <?= htmlspecialchars($visit['location']) ?></p>
            <p><strong>Date & Time:</strong> <?= date('Y-m-d H:i', strtotime($visit['visit_date'] . ' ' . $visit['visit_time'])) ?></p>
            <p><strong>Agent Report:</strong>
                <?php if (!empty($visit['visit_report_path']) && file_exists($visit['visit_report_path'])): ?>
                    <a href="<?= htmlspecialchars($visit['visit_report_path']) ?>" target="_blank">View Report</a>
                <?php else: ?>
                    <span class="text-muted">No report uploaded.</span>
                <?php endif; ?>
            </p>
        </div>

        <!-- Section: Decision form -->
        <form method="POST" class="bg-white border rounded p-4 shadow-sm needs-validation" novalidate>
            <div class="mb-3">
                <label class="form-label">Decision</label>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="status" value="approved" required>
                    <label class="form-check-label">Approve</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="status" value="rejected" required>
                    <label class="form-check-label">Reject</label>
                </div>
                <div class="invalid-feedback">Please select an option.</div>
            </div>
            <div class="mb-3">
                <label for="rejection_reason" class="form-label">Feedback</label>
                <textarea class="form-control" name="rejection_reason" id="rejection_reason" rows="4"></textarea>
            </div>
            <div class="d-flex gap-3">
                <button type="submit" class="btn custom-btn">Submit Decision</button>
                <a href="manage-client-visits.php" class="btn btn-outline-danger">Cancel</a>
            </div>
        </form>
        <div class="mt-4">
            <a href="staff-profile.php" class="btn btn-dark">Back to Profile</a>
        </div>
    </main>
</div>

<?php include 'footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Bootstrap client-side validation
(() => {
    'use strict';
    var forms = document.querySelectorAll('.needs-validation');
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
})();
</script>

<!-- Script to close main navbar on small screen-->
<script src="navbar-close.js?v=1"></script>
</body>
</html>
