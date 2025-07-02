<?php
// send-contract-reminders.php
require 'db_connect.php';
require 'notification-helper.php';

// Find contracts ending in 90 days (reminder threshold)
$stmt = $pdo->prepare("
    SELECT rc.claim_id, rc.contract_end_date, cc.client_id, p.property_name
    FROM rental_contracts rc
    JOIN client_claims cc ON rc.claim_id = cc.claim_id
    JOIN properties p ON cc.property_id = p.property_id
    WHERE DATEDIFF(rc.contract_end_date, CURDATE()) = 90
      AND rc.contract_status = 'active'
");
$stmt->execute();
$contracts = $stmt->fetchAll(PDO::FETCH_ASSOC);

$sent_count = 0;

foreach ($contracts as $contract) {
    $client_id     = $contract['client_id'];
    $property_name = $contract['property_name'];
    $contract_end  = $contract['contract_end_date'];

    // Prepare replacements for the template
    $replacements = [
        '{property}' => $property_name,
        '{date}'     => date('F j, Y', strtotime($contract_end))
    ];

    // System sender for scheduled tasks
    $system_sender = 'System'; // Or 'TREA Platform'
    
    // Send notification (and email) to client
    notify(
        $pdo,
        $client_id,
        'client',
        'contract_end_reminder',
        $replacements,
        '#',
        true,
        null,         // sender_id (system)
        'system',     // sender_type
        $system_sender
    );

    $sent_count++;
}

echo "Reminders sent: $sent_count\n";
?>
