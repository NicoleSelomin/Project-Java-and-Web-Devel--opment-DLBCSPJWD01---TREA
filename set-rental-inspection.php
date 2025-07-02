<?php
/**
 * set-rental-inspection.php
 * --------------------------------------------------------
 * Handles scheduling an initial or final rental inspection
 * - Receives claim_id, inspection_type (initial/final), and meeting_datetime (start_time)
 * - Automatically uses the assigned agent from manage-service-requests.php
 * - Blocks the chosen slot (2hrs) in agent_schedule
 * - Updates the client_claims table
 * --------------------------------------------------------
 */

session_start();
require 'db_connect.php';

// Staff-only access
if (!isset($_SESSION['staff_id']) || !in_array(strtolower($_SESSION['role']), ['general manager', 'property manager'])) {
    header("Location: staff-login.php");
    exit();
}

// Collect and validate POST
$claim_id         = $_POST['claim_id']         ?? null;
$inspection_type  = $_POST['inspection_type']  ?? null; // 'initial' or 'final'
$meeting_datetime = $_POST['inspection_datetime'] ?? null; // Y-m-d H:i:s or datetime-local

if (!$claim_id || !$inspection_type || !$meeting_datetime) {
    $_SESSION['message'] = "Missing required data.";
    header("Location: rental-management-claimed-properties.php");
    exit();
}

if (!in_array($inspection_type, ['initial', 'final'])) {
    $_SESSION['message'] = "Invalid inspection type.";
    header("Location: rental-management-claimed-properties.php");
    exit();
}

// Get property_id from claim
$stmt = $pdo->prepare("SELECT property_id FROM client_claims WHERE claim_id = ?");
$stmt->execute([$claim_id]);
$property_id = $stmt->fetchColumn();

if (!$property_id) {
    $_SESSION['message'] = "Invalid claim selected.";
    header("Location: rental-management-claimed-properties.php");
    exit();
}

// Get assigned agent from owner_service_requests
$stmt = $pdo->prepare("
    SELECT osr.assigned_agent_id, s.full_name
    FROM properties p
    JOIN owner_service_requests osr ON p.request_id = osr.request_id
    JOIN staff s ON osr.assigned_agent_id = s.staff_id
    WHERE p.property_id = ?
");
$stmt->execute([$property_id]);
$agentRow = $stmt->fetch(PDO::FETCH_ASSOC);
$agent_id = $agentRow['assigned_agent_id'] ?? null;

if (!$agent_id) {
    $_SESSION['message'] = "No agent assigned for this property.";
    header("Location: rental-management-claimed-properties.php");
    exit();
}

// Check if slot is already booked
$slotStmt = $pdo->prepare("
    SELECT * FROM agent_schedule
    WHERE agent_id = ? AND start_time = ? AND status IN ('booked','blocked')
");
$slotStmt->execute([$agent_id, $meeting_datetime]);
$slot = $slotStmt->fetch(PDO::FETCH_ASSOC);

if ($slot) {
    $_SESSION['message'] = "That slot is already booked.";
    header("Location: rental-management-claimed-properties.php");
    exit();
}

// Calculate end_time
$start_ts = strtotime($meeting_datetime);
$end_time = date('Y-m-d H:i:s', $start_ts + 2 * 3600);

// Insert booked slot (since it doesn't exist)
$insertStmt = $pdo->prepare("
    INSERT INTO agent_schedule
    (agent_id, property_id, scheduled_by, event_type, event_title, start_time, end_time, status, notes, created_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, 'booked', ?, NOW())
");
$insertStmt->execute([
    $agent_id,
    $property_id,
    $_SESSION['staff_id'],
    $inspection_type, // or something like "inspection"
    "Rental $inspection_type inspection, claim #$claim_id",
    $meeting_datetime,
    $end_time,
    "Booked for rental $inspection_type inspection, claim #$claim_id"
]);

// Update client_claims with inspection data
if ($inspection_type === 'initial') {
    $claimUpdate = $pdo->prepare("
        UPDATE client_claims
        SET meeting_datetime = ?, meeting_agent_id = ?
        WHERE claim_id = ?
    ");
    $claimUpdate->execute([$meeting_datetime, $agent_id, $claim_id]);
} else {
    $claimUpdate = $pdo->prepare("
        UPDATE client_claims
        SET final_inspection_datetime = ?, final_inspection_agent_id = ?
        WHERE claim_id = ?
    ");
    $claimUpdate->execute([$meeting_datetime, $agent_id, $claim_id]);
}

$_SESSION['message'] = "Inspection scheduled successfully for agent {$agentRow['full_name']}.";
header("Location: rental-management-claimed-properties.php");
exit();
