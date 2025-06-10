<?php
/*
|--------------------------------------------------------------------------
| assign-agent.php
|--------------------------------------------------------------------------
| Assigns a field agent to a task (inspection, client visit, meeting, etc.)
| - Supports: client onsite visit, owner property inspection, claim meeting,
|   initial/final rental inspection.
| - Handles validation, updates DB, and notifies the agent.
| - Only accessible to authorized staff roles.
|
| --------------------------------------------------------------------------
*/

// Start session and include DB/notification logic
session_start();
require 'db_connect.php';
require 'notification-helper.php';

// Only managers, legal officer, or supervision manager may assign
if (
    !isset($_SESSION['staff_id']) ||
    !in_array(strtolower($_SESSION['role']), [
        'general manager', 'property manager', 'plan and supervision manager', 'legal officer'
    ])
) {
    header("Location: staff-login.php");
    exit();
}

// --- Collect request data ---
$type = $_POST['type'] ?? '';
// Accept a visit_id, request_id, or claim_id (all are mutually exclusive by type)
$request_id = (int) ($_POST['visit_id'] ?? $_POST['request_id'] ?? $_POST['claim_id'] ?? 0);
$agent_id = (int) ($_POST['agent_id'] ?? 0);
// Inspection date, meeting datetime, or similar
$inspection_date = trim($_POST['inspection_date'] ?? $_POST['meeting_datetime'] ?? '');

// Default redirect fallback (should always be set in blocks below)
$redirect = "index.php";

// Variables for notification
$task_description = '';
$assigned_date = '';

// ---------------------------------------------------------------
// 1. Assign agent to client onsite visit
if ($type === 'client_visit') {
    if (!$request_id || !$agent_id) {
        header("Location: manage-client-visits.php?error=missing_fields");
        exit();
    }

    $stmt = $pdo->prepare("UPDATE client_onsite_visits SET assigned_agent_id = ? WHERE visit_id = ?");
    $stmt->execute([$agent_id, $request_id]);

    $redirect = "manage-client-visits.php?assigned=success";
    $task_description = 'Client onsite visit';
    $assigned_date = date('Y-m-d');

// ---------------------------------------------------------------
// 2. Assign agent for owner property inspection (with future date check)
} elseif ($type === 'owner_inspection') {
    if (!$request_id || !$agent_id || !$inspection_date) {
        header("Location: manage-service-requests.php?error=missing_fields");
        exit();
    }
    // Must be at least 2 hours in the future
    if (strtotime($inspection_date) < strtotime('+2 hours')) {
        header("Location: manage-service-requests.php?error=invalid_datetime");
        exit();
    }

    $stmt = $pdo->prepare("UPDATE owner_service_requests SET assigned_agent_id = ?, inspection_date = ? WHERE request_id = ?");
    $stmt->execute([$agent_id, $inspection_date, $request_id]);

    $redirect = "manage-service-requests.php?assigned=success";
    $task_description = 'Owner property inspection';
    $assigned_date = $inspection_date;

// ---------------------------------------------------------------
// 3. Assign agent for claim meeting (sale or brokerage claim)
} elseif ($type === 'claim_meeting') {
    if (!$request_id || !$agent_id) {
        header("Location: manage-claimed-properties.php?error=missing_fields");
        exit();
    }

    $stmt = $pdo->prepare("UPDATE client_claims SET meeting_agent_id = ? WHERE claim_id = ?");
    $stmt->execute([$agent_id, $request_id]);

    $redirect = "manage-claimed-properties.php?assigned=success";
    $task_description = 'Client–Owner meeting';
    $assigned_date = date('Y-m-d');

// ---------------------------------------------------------------
// 4. Assign agent for final inspection in rental claim
} elseif ($type === 'final_inspection') {
    if (!$request_id || !$agent_id || !$inspection_date) {
        header("Location: rental-management-claimed-properties.php?error=missing_fields");
        exit();
    }
    // Must be at least 2 hours in the future
    if (strtotime($inspection_date) < strtotime('+2 hours')) {
        header("Location: rental-management-claimed-properties.php?error=invalid_datetime");
        exit();
    }

    $stmt = $pdo->prepare("UPDATE client_claims SET final_inspection_agent_id = ?, final_inspection_datetime = ? WHERE claim_id = ?");
    $stmt->execute([$agent_id, $inspection_date, $request_id]);

    $redirect = "rental-management-claimed-properties.php?assigned=final_inspection_success";
    $task_description = 'Final rental inspection';
    $assigned_date = $inspection_date;

// ---------------------------------------------------------------
// 5. Assign agent for initial rental inspection meeting
} elseif ($type === 'rental_check') {
    if (!$request_id || !$agent_id || !$inspection_date) {
        header("Location: rental-management-claimed-properties.php?error=missing_fields");
        exit();
    }
    // Must be at least 2 hours in the future
    if (strtotime($inspection_date) < strtotime('+2 hours')) {
        header("Location: rental-management-claimed-properties.php?error=invalid_datetime");
        exit();
    }

    $stmt = $pdo->prepare("UPDATE client_claims SET meeting_agent_id = ?, meeting_datetime = ? WHERE claim_id = ?");
    $stmt->execute([$agent_id, $inspection_date, $request_id]);

    $redirect = "rental-management-claimed-properties.php?assigned=initial_inspection_success";
    $task_description = 'Initial rental inspection';
    $assigned_date = $inspection_date;

// ---------------------------------------------------------------
// 6. Unexpected/invalid type (fallback)
} else {
    header("Location: $redirect?error=invalid_type");
    exit();
}

// --- Notify the agent of their assignment (using helper) ---
notify($pdo, $agent_id, 'staff', 'agent_assigned', [
    '{task}' => $task_description,
    '{date}' => $assigned_date
], 'agent-assignments.php');

// --- Redirect to the relevant dashboard/management page ---
header("Location: $redirect");
exit();
?>
