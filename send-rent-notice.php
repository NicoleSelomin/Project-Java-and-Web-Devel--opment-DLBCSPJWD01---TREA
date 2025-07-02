<?php
/**
 * send-rent-notice.php
 * Handles sending of rental contract termination or general notices (from staff or client).
 */

session_start();
require 'db_connect.php';
require 'notification-helper.php';

// ----- SESSION & AUTHENTICATION -----
if (!isset($_SESSION['staff_id']) && !isset($_SESSION['client_id'])) {
    header("Location: user-login.php");
    exit();
}

// Set sender info
$is_staff    = isset($_SESSION['staff_id']);
$is_client   = isset($_SESSION['client_id']);
$sender_id   = $is_staff ? $_SESSION['staff_id'] : $_SESSION['client_id'];
$sender_type = $is_staff ? 'staff' : 'client';
// Enforce sender name
if ($is_staff) {
    $sender_name = 'TREA';
} else {
    // Always use client's full name (NO fallback to TREA, ever!)
    $sender_name = trim($_SESSION['user_name'] ?? '');
    if ($sender_name === '' || strtolower($sender_name) === 'trea') {
        $sender_name = 'Client Tenant';
    }
}

// ----- HANDLE POST -----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $claim_id    = intval($_POST['claim_id'] ?? 0);
    $contract_id = intval($_POST['contract_id'] ?? 0);
    $notice_type = $is_client ? 'period' : ($_POST['notice_type'] ?? 'period'); // Client can only send 'period'
    $reason      = trim($_POST['reason'] ?? '');

    if (!$claim_id || !$contract_id) {
        $_SESSION['message'] = "Invalid data. Please try again.";
        $_SESSION['message_type'] = "danger";
        header("Location: client-claimed-rental-management.php");
        exit();
    }

    // Prevent duplicate active notices
    $stmt = $pdo->prepare("SELECT 1 FROM rent_notices WHERE contract_id = ? AND status = 'active' LIMIT 1");
    $stmt->execute([$contract_id]);
    if ($stmt->fetchColumn()) {
        $_SESSION['message'] = "An active notice has already been sent.";
        $_SESSION['message_type'] = "info";
        header("Location: client-claimed-rental-management.php");
        exit();
    }

    // Fetch contract, client, property info
    $stmt = $pdo->prepare("
        SELECT rc.*, cc.client_id, p.property_id, p.property_name,
               o.owner_id, u.email AS client_email, u.full_name
        FROM rental_contracts rc
        JOIN client_claims cc ON rc.claim_id = cc.claim_id
        JOIN properties p ON cc.property_id = p.property_id
        JOIN owners o ON p.owner_id = o.owner_id
        JOIN clients c ON cc.client_id = c.client_id
        JOIN users u ON c.user_id = u.user_id
        WHERE rc.claim_id = ? LIMIT 1
    ");
    $stmt->execute([$claim_id]);
    $contract = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$contract) {
        $_SESSION['message'] = "Rental contract not found.";
        $_SESSION['message_type'] = "danger";
        header("Location: client-claimed-rental-management.php");
        exit();
    }

    // ----- RECIPIENTS -----
    if ($is_staff) {
        $recipient_type = 'client';
        $recipient_ids = [$contract['client_id']];
    } else { // Client sends to all property/general managers
        $recipient_type = 'staff';
        $stmt = $pdo->prepare("SELECT staff_id FROM staff WHERE role IN ('General Manager', 'Property Manager')");
        $stmt->execute();
        $recipient_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if (!$recipient_ids) {
            $_SESSION['message'] = "No staff found to notify.";
            $_SESSION['message_type'] = "danger";
            header("Location: client-claimed-rental-management.php");
            exit();
        }
    }

    // ----- NOTICE TYPE/KEY FOR TEMPLATES -----
    if ($notice_type === 'immediate') {
        $notif_type = 'immediate_termination';
    } elseif ($notice_type === 'period' && $reason && stripos($reason, 'overdue') !== false) {
        $notif_type = 'termination_due_to_overdue';
    } elseif ($is_client) {
        $notif_type = 'termination_notice_general_client';
    } else {
        $notif_type = 'termination_notice_general_manager';
    }

    // ----- PLACEHOLDERS FOR TEMPLATE -----
    $today = date('Y-m-d');
    $property_name = $contract['property_name'] ?? '';
    $notice_period_months = intval($contract['notice_period_months'] ?? 1);
    $notice_period_days   = $notice_period_months * 30;
    $notice_end           = date('Y-m-d', strtotime("+$notice_period_days days"));

    $replacements = [
        '{property}'      => $property_name,
        '{sender_name}'   => $is_client ? $sender_name . ' (tenant)' : $sender_name,
        '{notice_period}' => $notice_period_days,
        '{reason}'        => $reason ?: 'No specific reason provided',
        '{date}'          => $notice_end,
    ];

    // ----- INSERT NOTICE RECORDS AND SEND NOTIFICATION -----
    foreach ($recipient_ids as $recipient_id) {
        // Save in audit table
        $stmt = $pdo->prepare("
            INSERT INTO rent_notices (
                contract_id, notice_type, message, sent_by, sent_by_type, sender_name,
                recipient_id, recipient_type,
                notice_issued_date, notice_end_date, sent_at, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'active')
        ");
        // Always use the *actual* message as sent (with replacements)
        $template_message = strtr(getNotificationTemplate($notif_type), $replacements);
        $stmt->execute([
            $contract_id, $notice_type, $template_message, $sender_id, $sender_type, $sender_name,
            $recipient_id, $recipient_type,
            $today, $notice_end
        ]);

        // Send in-app and email notification
        notify(
            $pdo,
            $recipient_id,     // the correct recipient(s)
            $recipient_type,   // 'client' or 'staff'
            $notif_type,
            $replacements,
            '#',
            true,
            $sender_id,        // sender
            $sender_type,
            $sender_name
        );
    }

    // ----- UPDATE CONTRACT STATUS -----
    $stmt = $pdo->prepare("UPDATE rental_contracts SET contract_status = 'termination_notice' WHERE claim_id = ?");
    $stmt->execute([$claim_id]);

    $_SESSION['message'] = "Notice sent successfully.";
    $_SESSION['message_type'] = "success";
    header("Location: " . ($is_client ? "client-claimed-rental-management.php" : "rental-management-claimed-properties.php"));
    exit();
}

// ----------- Helper: Fetch Template Only -----------
function getNotificationTemplate($type_key) {
    $jsonPath = __DIR__ . '/notifications.json';
    if (!file_exists($jsonPath)) return '';
    $templates = json_decode(file_get_contents($jsonPath), true);
    return $templates[$type_key] ?? '';
}
?>
