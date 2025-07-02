<?php
/**
 * Agent: Submit Inspection Report Handler
 * - Saves initial/final inspection report and items
 * - Locks the report (status = 'locked')
 * - No signatures collected here!
 */

session_start();
require_once 'libs/dompdf/autoload.inc.php';
require 'db_connect.php';

use Dompdf\Dompdf;
use Dompdf\Options;

error_log("INSPECTION HANDLER REACHED: " . json_encode($_POST));

// --- 1. Validate session and POST data ---
$agent_id = $_SESSION['staff_id'] ?? null;
if (!$agent_id) die("Unauthorized");

$claim_id = (int)($_POST['claim_id'] ?? 0);
$type = ($_POST['inspection_type'] === 'final') ? 'final' : 'initial';
$items = $_POST['items'] ?? [];

if (!$claim_id || !$items) {
    die("Missing required input.");
}

// 2. Fetch property AND client info for folder path
$stmt = $pdo->prepare("
    SELECT cc.property_id, p.property_name, c.client_id, u_c.full_name AS client_name
    FROM client_claims cc
    JOIN properties p ON cc.property_id = p.property_id
    JOIN clients c ON cc.client_id = c.client_id
    JOIN users u_c ON c.user_id = u_c.user_id
    WHERE cc.claim_id = ?
");
$stmt->execute([$claim_id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) die("Invalid claim.");

// Use safe_name for client name and property name
function safe_name($str) {
    return strtolower(preg_replace('/[^a-zA-Z0-9_\-]/', '_', $str));
}
$property_id   = $row['property_id'];
$property_name = safe_name($row['property_name']);
$client_id     = $row['client_id'];
$client_name   = safe_name($row['client_name']);

$folder = "uploads/clients/{$client_id}_{$client_name}/reserved_properties/{$property_id}_{$property_name}/";
if (!is_dir($folder)) mkdir($folder, 0777, true);


$report_file = $type === 'initial' ? "initial_report.pdf" : "final_report.pdf";
$pdf_path = $folder . $report_file;

// --- 3. Insert main report and items ---
try {
    $pdo->beginTransaction();

    $now = date('Y-m-d H:i:s');
    // Create inspection report (status = locked, NO signatures)
    $stmt = $pdo->prepare("
        INSERT INTO inspection_reports 
        (claim_id, inspection_type, submitted_by, submitted_at, status, pdf_path)
        VALUES (?, ?, ?, ?, 'locked', ?)
    ");
    $stmt->execute([
        $claim_id, $type, $agent_id, $now, $pdf_path
    ]);
    $report_id = $pdo->lastInsertId();

    // Insert report items
    if ($type === 'initial') {
        foreach ($items as $ord => $row) {
            $stmt2 = $pdo->prepare("
                INSERT INTO inspection_report_items (report_id, item_name, initial_comment, order_no) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt2->execute([$report_id, $row['name'], $row['comment'], $ord]);
        }
    } else { // final
    // 1. Find the initial inspection report id for this claim
    $stmt = $pdo->prepare("SELECT report_id FROM inspection_reports WHERE claim_id=? AND inspection_type='initial' ORDER BY submitted_at DESC LIMIT 1");
    $stmt->execute([$claim_id]);
    $initialReportId = $stmt->fetchColumn();
    if (!$initialReportId) die("No initial inspection report found.");

    // 2. Update each item's final_comment by order_no (or use item_id if you pass it)
    foreach ($items as $ord => $row) {
        $stmt2 = $pdo->prepare("
            UPDATE inspection_report_items
            SET final_comment = ?
            WHERE report_id = ? AND order_no = ?
        ");
        $stmt2->execute([
            $row['final_comment'],
            $initialReportId,
            $ord
        ]);
    }
}

    // --- 4. Generate blank PDF (no signatures yet) ---
    // Fetch details for PDF
    $report_stmt = $pdo->prepare("
        SELECT ir.*, cc.property_id, p.property_name, p.location,
               u_c.full_name AS client_name, u_o.full_name AS owner_name
        FROM inspection_reports ir
        JOIN client_claims cc ON ir.claim_id = cc.claim_id
        JOIN properties p ON cc.property_id = p.property_id
        JOIN clients c ON cc.client_id = c.client_id
        JOIN users u_c ON c.user_id = u_c.user_id
        JOIN owners o ON p.owner_id = o.owner_id
        JOIN users u_o ON o.user_id = u_o.user_id
        WHERE ir.report_id = ?
    ");
    $report_stmt->execute([$report_id]);
    $report = $report_stmt->fetch(PDO::FETCH_ASSOC);

    $items_stmt = $pdo->prepare("SELECT * FROM inspection_report_items WHERE report_id = ? ORDER BY order_no ASC, item_id ASC");
    $items_stmt->execute([$report_id]);
    $item_rows = $items_stmt->fetchAll(PDO::FETCH_ASSOC);

    // HTML for PDF
    ob_start();
    ?>
    <h2><?= strtoupper($type) ?> INSPECTION REPORT</h2>
    <p><strong>Date:</strong> <?= date('Y-m-d H:i', strtotime($report['submitted_at'])) ?></p>
    <p>
        <strong>Property:</strong> <?= htmlspecialchars($report['property_name']) ?> <br>
        <strong>Location:</strong> <?= htmlspecialchars($report['location']) ?> <br>
        <strong>Client:</strong> <?= htmlspecialchars($report['client_name']) ?> <br>
        <strong>Owner:</strong> <?= htmlspecialchars($report['owner_name']) ?>
    </p>
    <h3>Inspection Details</h3>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Item/Room</th>
                <th>Initial Condition</th>
                <?php if ($type === 'final'): ?>
                    <th>Condition After Client</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($item_rows as $k => $item): ?>
            <tr>
                <td><?= $k+1 ?></td>
                <td><?= htmlspecialchars($item['item_name']) ?></td>
                <td><?= htmlspecialchars($item['initial_comment']) ?></td>
                <?php if ($type === 'final'): ?>
                    <td><?= htmlspecialchars($item['final_comment']) ?></td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <br>
    <p style="color: #666; font-size: 11px;">Generated by TREA</p>
    <?php
    $html = ob_get_clean();

    // Dompdf setup
    $options = new Options();
    $options->set('isRemoteEnabled', true);
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    file_put_contents($pdf_path, $dompdf->output());

    $pdo->commit();
    header("Location: agent-assignments.php?success=1");
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    die("Failed to save inspection report: " . $e->getMessage());
}
?>
