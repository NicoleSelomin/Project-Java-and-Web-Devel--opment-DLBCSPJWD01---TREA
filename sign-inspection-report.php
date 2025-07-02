<?php
session_start();
require 'db_connect.php';
require_once 'generate-inspection-report-pdf.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // === POST: Process signature submission ===
    if (!isset($_POST['report_id'], $_POST['signature_data'], $_SESSION['user_type'], $_SESSION['user_id'])) {
        die('Missing data');
    }

    $report_id = (int)$_POST['report_id'];
    $sig       = trim($_POST['signature_data']);
    $user_type = strtolower($_SESSION['user_type']); // 'client' or 'owner'
    $user_id   = (int)$_SESSION['user_id'];
    $now       = date('Y-m-d H:i:s');

    // Check signature isn't empty
    if (!$sig || $sig === 'null' || $sig === 'undefined') {
        die('Signature required.');
    }

    // Check if already signed by this role
    if ($user_type === 'client') {
        $stmt = $pdo->prepare("SELECT client_signature FROM inspection_reports WHERE report_id=?");
        $stmt->execute([$report_id]);
        $existing = $stmt->fetchColumn();
        if ($existing) {
            die('Already signed as client');
        }
        $pdo->prepare("UPDATE inspection_reports SET client_signature=?, client_signed_at=?, signed_by_client_id=? WHERE report_id=?")
            ->execute([$sig, $now, $user_id, $report_id]);
    } elseif ($user_type === 'property_owner') {
        $stmt = $pdo->prepare("SELECT owner_signature FROM inspection_reports WHERE report_id=?");
        $stmt->execute([$report_id]);
        $existing = $stmt->fetchColumn();
        if ($existing) {
            die('Already signed as owner');
        }
        $pdo->prepare("UPDATE inspection_reports SET owner_signature=?, owner_signed_at=?, signed_by_owner_id=? WHERE report_id=?")
            ->execute([$sig, $now, $user_id, $report_id]);
    } else {
        die('Unauthorized role');
    }

    // Update status if both signatures present
    $stmt = $pdo->prepare("SELECT client_signature, owner_signature FROM inspection_reports WHERE report_id=?");
    $stmt->execute([$report_id]);
    $row = $stmt->fetch();

    if ($row && $row['client_signature'] && $row['owner_signature']) {
        $pdo->prepare("UPDATE inspection_reports SET status='fully_signed' WHERE report_id=?")->execute([$report_id]);
    } elseif ($row && $row['client_signature']) {
        $pdo->prepare("UPDATE inspection_reports SET status='signed_by_client' WHERE report_id=?")->execute([$report_id]);
    } elseif ($row && $row['owner_signature']) {
        $pdo->prepare("UPDATE inspection_reports SET status='signed_by_owner' WHERE report_id=?")->execute([$report_id]);
    }

    // === REGENERATE PDF WITH NEW SIGNATURES ===
    generateInspectionReportPDF($report_id, $pdo);

    // Redirect to previous page or index.php
    header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'index.php'));
    exit;

} else {
    // === GET: Show signature form ===
    if (!isset($_GET['report_id'])) {
        die('Missing report');
    }
    $report_id = (int)$_GET['report_id'];
    $user_type = strtolower($_SESSION['user_type'] ?? '');
    if (!in_array($user_type, ['property_owner', 'client'])) {
        die('Not allowed');
    }

    // Optionally: Check if already signed, and prevent signing again
    $stmt = $pdo->prepare("SELECT owner_signature, client_signature FROM inspection_reports WHERE report_id=?");
    $stmt->execute([$report_id]);
    $report = $stmt->fetch(PDO::FETCH_ASSOC);

    if (
        ($user_type === 'property_owner' && !empty($report['owner_signature'])) ||
        ($user_type === 'client' && !empty($report['client_signature']))
    ) {
        die('You have already signed this report.');
    }
    ?>

    <!DOCTYPE html>
    <html>
    <head>
        <title>Sign Inspection Report</title>
        <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.6/dist/signature_pad.umd.min.js"></script>
            <!-- Bootstrap 5.3.6 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css?v=<?= time() ?>">
    </head>
    <body>
        <h2>Sign Inspection Report</h2>
        <form id="sig-form" method="POST" action="sign-inspection-report.php">
            <input type="hidden" name="report_id" value="<?= htmlspecialchars($report_id) ?>">
            <input type="hidden" name="signature_data" id="signature_data">
            <p>Draw your signature below:</p>
            <canvas id="sig-canvas" width="400" height="150"></canvas><br>
            <button type="button" onclick="clearSig()">Clear</button>
            <button type="submit">Sign</button>
        </form>
        <script>
            var canvas = document.getElementById('sig-canvas');
            var signaturePad = new SignaturePad(canvas);

            function clearSig() {
                signaturePad.clear();
            }

            document.getElementById('sig-form').addEventListener('submit', function(e){
                if (signaturePad.isEmpty()) {
                    alert('Please provide a signature.');
                    e.preventDefault();
                    return false;
                }
                document.getElementById('signature_data').value = signaturePad.toDataURL();
            });
        </script>
    </body>
    </html>
    <?php
}
?>
