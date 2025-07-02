<?php
// cancel-rent-notice.php

session_start();
require 'db_connect.php';
require_once 'notification-helper.php';

if (
    !isset($_SESSION['staff_id']) ||
    !in_array(strtolower($_SESSION['role']), ['general manager', 'property manager', 'accountant'])
) {
    header("Location: staff-login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['notice_id'])) {
    $notice_id = intval($_POST['notice_id']);
    $staff_id  = $_SESSION['staff_id'];

    // 1. Find the notice
    $stmt = $pdo->prepare("SELECT * FROM rent_notices WHERE notice_id = ? AND status = 'active'");
    $stmt->execute([$notice_id]);
    $notice = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$notice) {
        $_SESSION['message'] = "Active notice not found or already cancelled.";
        $_SESSION['message_type'] = "danger";
        header("Location: rental-management-claimed-properties.php");
        exit();
    }

    $contract_id = $notice['contract_id'];

    // 2. Find the contract/claim for this notice
    $stmt = $pdo->prepare("
        SELECT rc.claim_id, cc.client_id, p.property_name
        FROM rental_contracts rc
        JOIN client_claims cc ON rc.claim_id = cc.claim_id
        JOIN properties p ON cc.property_id = p.property_id
        WHERE rc.contract_id = ?
    ");
    $stmt->execute([$contract_id]);
    $contract = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$contract) {
        $_SESSION['message'] = "Associated contract not found.";
        $_SESSION['message_type'] = "danger";
        header("Location: rental-management-claimed-properties.php");
        exit();
    }

    $claim_id   = $contract['claim_id'];
    $client_id  = $contract['client_id'];
    $property   = $contract['property_name'];
    $sender_name = $_SESSION['staff_name'] ?? 'TREA';

    // 3. Cancel the notice (update status, record cancellation date/by)
    $stmt = $pdo->prepare("UPDATE rent_notices SET status = 'cancelled', cancelled_at = NOW(), cancelled_by = ? WHERE notice_id = ?");
    $stmt->execute([$staff_id, $notice_id]);

    // 4. Set contract status back to 'active'
    $stmt = $pdo->prepare("UPDATE rental_contracts SET contract_status = 'active' WHERE contract_id = ?");
    $stmt->execute([$contract_id]);

    // 5. Notify client in-app and by email
    $replacements = [
        '{property}'    => $property,
        '{sender_name}' => $sender_name
    ];
    notify(
        $pdo,
        $client_id,
        'client',
        'termination_notice_cancelled', // match your notifications.json key
        $replacements,
        '#',
        true,
        $staff_id,
        'staff',
        $sender_name
    );

    $_SESSION['message'] = "Notice cancelled and contract is active again.";
    $_SESSION['message_type'] = "success";
    header("Location: rental-management-claimed-properties.php");
    exit();
}

// If not POST, or no notice_id
header("Location: rental-management-claimed-properties.php");
exit();
?>
