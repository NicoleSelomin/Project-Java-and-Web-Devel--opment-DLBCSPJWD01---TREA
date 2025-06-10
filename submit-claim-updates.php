<?php
/**
 * ============================================================================
 * submit-claim-update.php — TREA Real Estate Platform
 * -----------------------------------------------------------------------------
 * Manage Claimed Property Actions (TREA Platform)
 * -----------------------------------------------------------------------------
 * Handles staff actions for claimed brokerage properties:
 * 1. Set meeting date/time and assign agent for a claim.
 * 2. Mark a claim as completed (after all required steps).
 * - Updates relevant records and provides feedback via $_SESSION['message'].
 * - Secured for logged-in staff only.
 * -----------------------------------------------------------------------------
 */

session_start();
require 'db_connect.php';

// 1. Enforce authentication: Only staff may access this page
if (!isset($_SESSION['staff_id'])) {
    header("Location: staff-login.php");
    exit();
}

$staff_id = $_SESSION['staff_id'];

// ----------------------------------------------------------------------------
// 1. SET MEETING DATE/TIME + ASSIGN AGENT
// ----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_meeting_agent'])) {
    // Required POST values
    $claim_id         = $_POST['claim_id'] ?? null;
    $meeting_datetime = $_POST['meeting_datetime'] ?? null;
    $meeting_agent_id = $_POST['meeting_agent_id'] ?? null;

    // Basic validation
    if ($claim_id && $meeting_datetime && $meeting_agent_id) {
        $stmt = $pdo->prepare("
            UPDATE client_claims
            SET meeting_datetime = ?, meeting_agent_id = ?
            WHERE claim_id = ?
        ");
        $stmt->execute([$meeting_datetime, $meeting_agent_id, $claim_id]);

        // Redirect with update flag
        header("Location: brokerage-claimed-properties.php?updated=1");
        exit();
    } else {
        $_SESSION['message'] = "All meeting and agent fields are required.";
        header("Location: manage-claimed-properties.php");
        exit();
    }
}

// ----------------------------------------------------------------------------
// 2. MARK CLAIM AS COMPLETED (after all workflow steps are finished)
// ----------------------------------------------------------------------------
if (isset($_POST['complete_claim'], $_POST['claim_id'])) {
    $claim_id = intval($_POST['claim_id']);

    // Check all required steps are done
    $checkStmt = $pdo->prepare("
        SELECT cc.meeting_datetime, cc.meeting_agent_id, cc.meeting_report_path, bcp.confirmed_by
        FROM client_claims cc
        JOIN brokerage_claim_payments bcp ON bcp.claim_id = cc.claim_id
        WHERE cc.claim_id = ?
    ");
    $checkStmt->execute([$claim_id]);
    $claim = $checkStmt->fetch(PDO::FETCH_ASSOC);

    // All must be non-empty
    if (
        $claim &&
        $claim['meeting_datetime'] &&
        $claim['meeting_agent_id'] &&
        $claim['meeting_report_path'] &&
        $claim['confirmed_by']
    ) {
        // Mark as completed in client_claims
        $update = $pdo->prepare("
            UPDATE client_claims SET final_status = 'completed' WHERE claim_id = ?
        ");
        $update->execute([$claim_id]);

        // Set property as unavailable
        $hide = $pdo->prepare("
            UPDATE properties
            SET availability = 'unavailable'
            WHERE property_id = (SELECT property_id FROM client_claims WHERE claim_id = ?)
        ");
        $hide->execute([$claim_id]);

        $_SESSION['message'] = "Claim marked as completed.";
    } else {
        $_SESSION['message'] = "All steps must be completed first.";
    }

    header("Location: manage-claimed-properties.php");
    exit();
}

// ----------------------------------------------------------------------------
// 3. INVALID ACTION HANDLING
// ----------------------------------------------------------------------------
$_SESSION['message'] = "Invalid action.";
header("Location: manage-claimed-properties.php");
exit();
?>
