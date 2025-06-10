<?php
// -----------------------------------------------------------------------------
// cancel-visit.php
// -----------------------------------------------------------------------------
// Allows a logged-in client to cancel an onsite visit if it is more than
// 24 hours before the scheduled time. Deletes the visit record if valid.
// -----------------------------------------------------------------------------

session_start();
require_once 'check-user-session.php'; // Ensure client is logged in
require 'db_connect.php';

// Get visit ID from POST and validate
$visit_id = isset($_POST['visit_id']) ? intval($_POST['visit_id']) : 0;
if ($visit_id <= 0) {
    echo "Invalid request.";
    exit();
}

// Fetch visit details for this client
$stmt = $pdo->prepare("SELECT * FROM client_onsite_visits WHERE visit_id = ? AND client_id = ?");
$stmt->execute([$visit_id, $_SESSION['client_id']]);
$visit = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$visit) {
    echo "Visit not found.";
    exit();
}

// Check if cancellation is at least 24 hours before the scheduled visit
$visit_time = strtotime($visit['visit_date']);
$current_time = time();
if ($visit_time - $current_time < 86400) {
    echo "You can only cancel visits at least 24 hours in advance.";
    exit();
}

// Delete the visit
$delete = $pdo->prepare("DELETE FROM client_onsite_visits WHERE visit_id = ?");
$delete->execute([$visit_id]);

// Redirect client with cancellation confirmation
header("Location: client-profile.php?visit_cancelled=1");
exit();
?>
