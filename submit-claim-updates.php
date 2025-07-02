<?php
/**
 * ============================================================================
 * submit-claim-update.php — TREA Real Estate Platform
 * -----------------------------------------------------------------------------
 * Manages claimed brokerage property actions (staff only):
 * - Set meeting date/time and assign agent for a claim (blocks slot)
 * - Mark claim as completed (after all required steps)
 * -----------------------------------------------------------------------------
 */

session_start();
require 'db_connect.php';

// Staff only
if (!isset($_SESSION['staff_id'])) {
    header("Location: staff-login.php");
    exit();
}

$staff_id = $_SESSION['staff_id'];

// ----------------------------------------------------------------------------
// 1. SET MEETING DATE/TIME + ASSIGN AGENT
// ----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_meeting_agent'])) {
    $claim_id         = $_POST['claim_id'] ?? null;
    $meeting_datetime = $_POST['meeting_datetime'] ?? null;
    $meeting_agent_id = $_POST['meeting_agent_id'] ?? null;

    if ($claim_id && $meeting_datetime && $meeting_agent_id) {
        // 1. Update client_claims with meeting info
        $stmt = $pdo->prepare("
            UPDATE client_claims
            SET meeting_datetime = ?, meeting_agent_id = ?
            WHERE claim_id = ?
        ");
        $stmt->execute([$meeting_datetime, $meeting_agent_id, $claim_id]);

        // 2. Block this slot in agent_schedule
        $stmt2 = $pdo->prepare("
            UPDATE agent_schedule
            SET status = 'blocked', notes = CONCAT(IFNULL(notes,''), ' | Booked for claim #$claim_id')
            WHERE agent_id = ? AND start_time = ?
        ");
        $stmt2->execute([$meeting_agent_id, $meeting_datetime]);

        $_SESSION['message'] = "Meeting scheduled and agent assigned.";
        header("Location: brokerage-claimed-properties.php?saved=1");
        exit;
    } else {
        $_SESSION['message'] = "All meeting and agent fields are required.";
        header("Location: brokerage-claimed-properties.php?error=1");
        exit;
    }
}

// ----------------------------------------------------------------------------
// 2. MARK CLAIM AS COMPLETED (after all workflow steps)
// ----------------------------------------------------------------------------
if (isset($_POST['complete_claim'], $_POST['claim_id'])) {
    $claim_id = intval($_POST['claim_id']);

    // Ensure all steps are done
    $checkStmt = $pdo->prepare("
        SELECT cc.meeting_datetime, cc.meeting_agent_id, cc.meeting_report_path, bcp.confirmed_by, cc.property_id
        FROM client_claims cc
        JOIN brokerage_claim_payments bcp ON bcp.claim_id = cc.claim_id
        WHERE cc.claim_id = ?
    ");
    $checkStmt->execute([$claim_id]);
    $claim = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (
        $claim &&
        $claim['meeting_datetime'] &&
        $claim['meeting_agent_id'] &&
        $claim['meeting_report_path'] &&
        $claim['confirmed_by']
    ) {
        // 1. Mark as completed
        $pdo->prepare("UPDATE client_claims SET final_status = 'completed' WHERE claim_id = ?")
            ->execute([$claim_id]);
        // 2. Make property unavailable
        $pdo->prepare("UPDATE properties SET availability = 'unavailable' WHERE property_id = ?")
            ->execute([$claim['property_id']]);
        $_SESSION['message'] = "Reservation marked as completed.";
    } else {
        $_SESSION['message'] = "All steps must be completed first.";
    }
    header("Location: brokerage-claimed-properties.php");
    exit;
}

// ----------------------------------------------------------------------------
// 3. INVALID ACTION HANDLING
// ----------------------------------------------------------------------------
$_SESSION['message'] = "Invalid action.";
header("Location: brokerage-claimed-properties.php");
exit;
?>
