<?php
// -----------------------------------------------------------------------------
// cancel-visit.php
// -----------------------------------------------------------------------------
// Allows a logged-in client to cancel an onsite visit if it is more than
// 24 hours before the scheduled time. Deletes the visit record and unblocks
// the agent's slot in agent_schedule if valid.
// -----------------------------------------------------------------------------

session_start();
require_once 'check-user-session.php';
require 'db_connect.php';

// 1. Get and validate visit_id
$visit_id = isset($_POST['visit_id']) ? intval($_POST['visit_id']) : 0;
if ($visit_id <= 0) {
    echo "Invalid request.";
    exit();
}

// 2. Fetch visit details (make sure client owns it)
$stmt = $pdo->prepare("SELECT * FROM client_onsite_visits WHERE visit_id = ? AND client_id = ?");
$stmt->execute([$visit_id, $_SESSION['client_id']]);
$visit = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$visit) {
    echo "Visit not found.";
    exit();
}

// 3. Check if cancellation is at least 24 hours before the visit
$visit_datetime = strtotime($visit['visit_date'] . ' ' . $visit['visit_time']);
if ($visit_datetime - time() < 86400) {
    echo "You can only cancel visits at least 24 hours in advance.";
    exit();
}

try {
    $pdo->beginTransaction();

    // 4. Unblock the agent's slot
    $update = $pdo->prepare("
        UPDATE agent_schedule
        SET status = 'available', notes = CONCAT(COALESCE(notes, ''), ' | Slot unblocked by client cancellation (visit #$visit_id)')
        WHERE agent_id = ? AND start_time = ? AND status = 'blocked'
    ");
    $update->execute([
        $visit['assigned_agent_id'],
        $visit['visit_date'] . ' ' . $visit['visit_time']
    ]);

    // 5. Delete the visit
    $delete = $pdo->prepare("DELETE FROM client_onsite_visits WHERE visit_id = ?");
    $delete->execute([$visit_id]);

    $pdo->commit();

    // 6. Redirect client with confirmation
    header("Location: client-profile.php?visit_cancelled=1");
    exit();

} catch (Exception $e) {
    $pdo->rollBack();
    echo "An error occurred. Please try again.";
    exit();
}
?>
