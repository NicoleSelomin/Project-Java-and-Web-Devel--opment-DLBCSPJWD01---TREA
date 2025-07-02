<?php
/**
 * ============================================================================
 * submit-agent-report.php — TREA Real Estate Platform
 * ------------------------------------------------ 
 * Field Agent report submission page for owner service requests (inspection, visit, or claim meeting).
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

// Only field agents
if (!isset($_SESSION['staff_id']) || strtolower($_SESSION['role']) !== 'field agent') {
    header("Location: staff-login.php");
    exit();
}

$agentId = $_SESSION['staff_id'];
$type = $_GET['type'] ?? '';
$request_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$allowedTypes = ['owner_inspection', 'client_visit', 'claim_meeting'];
if (!in_array($type, $allowedTypes) || $request_id <= 0) {
    echo "Invalid or missing parameters."; exit();
}

// Fetch the relevant service request and application data
$stmt = $pdo->prepare("
    SELECT r.*, u.full_name AS owner_name, s.service_name, s.service_id, s.slug AS service_slug,
    br.site_image_path AS br_site_image_path, br.property_type AS br_property_type,
    rd.site_image_path AS rd_site_image_path, rd.property_type AS rd_property_type
    FROM owner_service_requests r
    JOIN owners o ON r.owner_id = o.owner_id
    JOIN users u ON o.user_id = u.user_id
    JOIN services s ON r.service_id = s.service_id
    LEFT JOIN brokerage_details br ON br.request_id = r.request_id
    LEFT JOIN rental_property_management_details rd ON rd.request_id = r.request_id
    WHERE r.request_id = ? AND r.assigned_agent_id = ?
");
$stmt->execute([$request_id, $agentId]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$data) { echo "Unauthorized or data not found."; exit(); }

// Define field labels (used everywhere)
$fields = [
    'property_name'        => 'Property Name',
    'location'             => 'Location',
    'property_type'        => 'Property Type',
    'property_description' => 'Description',
    'number_of_bedrooms'   => 'Number of Bedrooms',
    'number_of_bathrooms'  => 'Number of Bathrooms',
    'floor_count'          => 'Floor Count',
    'land_size'            => 'Land Size (sq m)',
    'site_image_path'      => 'Property Image',
];

// Map correct field source per request
$service_type = $data['service_slug'];
$is_brokerage = ($service_type === 'brokerage');
$is_rental    = ($service_type === 'rental_property_management');

$field_sources = [];
foreach ($fields as $key => $label) {
    if ($is_brokerage && isset($data['br_' . $key])) {
        $field_sources[$key] = $data['br_' . $key];
    } elseif ($is_rental && isset($data['rd_' . $key])) {
        $field_sources[$key] = $data['rd_' . $key];
    } else {
        $field_sources[$key] = $data[$key] ?? '';
    }
}

$old_feedback = "";
$old_result = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = $_POST['result'] ?? null;
    $feedback = trim($_POST['agent_feedback'] ?? '');
    $old_result = $result;
    $old_feedback = $feedback;

    // Build up agent check result per field
    $field_results = [];
    foreach ($fields as $key => $label) {
        $match = $_POST['match_' . $key] ?? 'yes';
        $onsite = trim($_POST['onsite_' . $key] ?? '');
        $field_results[$key] = [
            'label' => $label,
            'application' => $field_sources[$key], // always consistent
            'match' => $match,
            'onsite' => $onsite,
        ];
    }
    $field_results_json = json_encode($field_results);

    if (!$result || !$feedback) {
        $error = "Both inspection result and feedback are required.";
    } else {
        // Save agent result/feedback and checks
        $update = $pdo->prepare("UPDATE owner_service_requests SET inspection_result = ?, agent_feedback = ?, agent_report_path = ? WHERE request_id = ?");
        $update->execute([$result, $feedback, $field_results_json, $request_id]);

        // Generate PDF
$dompdf = new Dompdf();

// CSS and header
$html = <<<HTML
<style>
    body { font-family: Arial, sans-serif; font-size: 11px; }
    table { width: 100%; border-collapse: collapse; font-size: 10px; table-layout: fixed; word-break: break-word; }
    th, td { border: 1px solid #333; padding: 5px; text-align: left; vertical-align: top; }
    th { background: #f2f2f2; }
    img { max-width: 80px; max-height: 60px; display: block; margin: 2px auto; }
</style>
<h2>Inspection Report</h2>
<p><strong>Agent:</strong> {$_SESSION['full_name']}</p>
<p><strong>Owner:</strong> {$data['owner_name']}</p>
<p><strong>Service:</strong> {$data['service_name']}</p>
<p><strong>Property:</strong> {$data['property_name']} - {$data['location']}</p>
<p><strong>Inspection Date:</strong> {date('Y-m-d H:i')}</p>
<h4>Field Comparison</h4>
<table>
    <tr>
        <th style="width:19%;">Field</th>
        <th style="width:19%;">Application</th>
        <th style="width:38%;">Onsite</th>
        <th style="width:24%;">Match?</th>
    </tr>
HTML;

// Table rows
foreach ($field_results as $r) {
    $match_txt = '';
    if (isset($r['match']) && $r['match'] === 'yes') {
        $match_txt = '✔️';
    } elseif (isset($r['match']) && $r['match'] === 'no') {
        $match_txt = '❌';
        if (!empty($r['onsite']) && $r['onsite'] !== $r['application']) {
            $match_txt .= '<br><small>' . htmlspecialchars($r['onsite']) . '</small>';
        }
    }
    // Special image handling
    if (strpos(strtolower($r['label']), 'image') !== false && !empty($r['application']) && @getimagesize($r['application'])) {
        $app_val = "<img src=\"{$r['application']}\" alt=\"Image\">";
    } else {
        $app_val = htmlspecialchars($r['application']);
    }
    $onsite_val = htmlspecialchars($r['onsite'] ?: $r['application']);

    $html .= "<tr>
        <td>" . htmlspecialchars($r['label']) . "</td>
        <td>$app_val</td>
        <td>$onsite_val</td>
        <td align=\"center\">$match_txt</td>
    </tr>";
}

$html .= "</table>";
$html .= "<p><strong>Result:</strong> " . htmlspecialchars($result) . "</p>";
$html .= "<p><strong>Agent Feedback:</strong><br>" . nl2br(htmlspecialchars($feedback)) . "</p>";

$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

        // Folder & save
        $owner_id = $data['owner_id'];
        $owner_name = preg_replace('/[^a-zA-Z0-9_]/', '_', strtolower($data['owner_name']));
        $service_id = $data['service_id'];
        $service_slug = preg_replace('/[^a-zA-Z0-9_]/', '_', strtolower($data['service_slug']));
        $request_folder = "uploads/owner/{$owner_id}_{$owner_name}/applications/{$service_id}_{$service_slug}/request_{$request_id}/";
        if (!is_dir($request_folder)) mkdir($request_folder, 0755, true);

        $filePath = $request_folder . "agent_report.pdf";
        file_put_contents($filePath, $dompdf->output());

        // Save report path
        $stmt = $pdo->prepare("UPDATE owner_service_requests SET agent_report_path = ? WHERE request_id = ?");
        $stmt->execute([$filePath, $request_id]);

        header("Location: agent-assignments.php?submitted=1");
        exit();
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Submit Inspection Report</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css?v=<?= time() ?>">
</head>
<body class="d-flex flex-column min-vh-100 bg-light">
<?php include 'header.php'; ?>

<div class="container-fluid flex-grow-1 py-4">
    <div class="row">

<main class="container py-5 flex-grow-1">
    <div class="row justify-content-center">
        <div class="col-12 col-md-10 col-lg-10 col-xl-10 p-4 border rounded shadow-sm bg-white">
            <h2 class="mb-3">Submit Property Inspection Report</h2>
            <div class="mb-3"><strong>Owner:</strong> <?= htmlspecialchars($data['owner_name']) ?></div>
            <div class="mb-3"><strong>Service:</strong> <?= htmlspecialchars($data['service_name']) ?></div>
            <div class="mb-3"><strong>Property:</strong> <?= htmlspecialchars($data['property_name']) ?> - <?= htmlspecialchars($data['location']) ?></div>
            <div class="mb-3"><strong>Application Form:</strong> <a href="generate-application-pdf.php?id=<?= $data['request_id'] ?>" target="_blank">PDF</a></div>
            <div class="mb-3"><strong>Inspection Date:</strong> <?= $data['inspection_date'] ? date('F j, Y g:i A', strtotime($data['inspection_date'])) : 'Not Set' ?></div>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <form method="POST" class="mb-2">
                <h5>Check against the Request Form</h5>
                <div class="table-responsive">
                    <table class="table table-bordered align-middle">
                        <thead class="table-light">
                        <tr>
                            <th>Field</th>
                            <th>Application</th>
                            <th>Matches?</th>
                            <th>If No, Actual Onsite</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($fields as $key => $label): ?>
                            <tr>
                                <td><?= htmlspecialchars($label) ?></td>

                                <td>
                                    <?php if ($key === 'site_image_path' && !empty($field_sources[$key])): ?>
                                        <img src="<?= htmlspecialchars($field_sources[$key]) ?>" alt="Property Image" style="max-width:120px;max-height:80px;object-fit:cover;">
                                        <?php else: ?>
                                            <?= htmlspecialchars($field_sources[$key] ?? '') ?>
                                            <?php endif; ?>
                                </td>
                                <td>
                                    <select name="match_<?= $key ?>" class="form-select" required>    
                                        <option value="yes" <?= (($_POST["match_$key"] ?? '') === 'yes' ? 'selected' : '') ?>>Yes</option>
                                        <option value="no" <?= (($_POST["match_$key"] ?? '') === 'no' ? 'selected' : '') ?>>No</option>
                                    </select>
                                </td>
                                <td>
                                    <input type="text" name="onsite_<?= $key ?>" class="form-control" value="<?= htmlspecialchars($_POST["onsite_$key"] ?? '') ?>" placeholder="If different, enter actual value">
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="mb-3">
                    <label class="form-label mb-1">Inspection Result <span class="text-danger">*</span></label>
                    <div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="result" id="result-passed" value="passed" <?= ($old_result === 'passed' ? 'checked' : '') ?> required>
                            <label class="form-check-label" for="result-passed">Passed</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="result" id="result-failed" value="failed" <?= ($old_result === 'failed' ? 'checked' : '') ?> required>
                            <label class="form-check-label" for="result-failed">Failed</label>
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label mb-1">Agent Feedback <span class="text-danger">*</span></label>
                    <textarea name="agent_feedback" rows="6" class="form-control" required><?= htmlspecialchars($old_feedback) ?></textarea>
                </div>
                <button type="submit" class="btn custom-btn w-100">Submit Report</button>
            </form>
            <a href="agent-assignments.php" class="btn bg-dark text-white fw-bold">🡰Back to Assignments</a>
        </div>
    </div>
</main>
</div>
</div>
<?php include 'footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>

<!-- Script to close main navbar on small screen-->
<script src="navbar-close.js?v=1"></script>
</body>
</html>
