<?php
/**
 * notification-helper.php
 * Robust notification utility for TREA.
 * Usage: require_once this file and call notify(...)
 */

function notify(
    $pdo,
    $recipient_id, $recipient_type,
    $type_key, $replacements = [],
    $link = '#',
    $send_email = true,
    $sender_id = null, $sender_type = null, $sender_name = null
) {
    $jsonPath = __DIR__ . '/notifications.json';
    if (!file_exists($jsonPath)) {
        error_log("Notification template not found: $jsonPath");
        return false;
    }
    $templates = json_decode(file_get_contents($jsonPath), true);

    // Get templates
    if (empty($templates[$type_key])) {
        error_log("Notification type '$type_key' not found in templates.");
        return false;
    }
    $message = strtr($templates[$type_key], $replacements);
    $title_key = $type_key . '_title';
    $title = isset($templates[$title_key])
        ? strtr($templates[$title_key], $replacements)
        : ucfirst(str_replace('_', ' ', $type_key));

    // -- Sender label logic for in-app (prepends if not in template) --
    // Only add "Sent by: ..." if the template does not already include {sender_name}
// Only prepend "Sent by:" if {sender_name} is not present in the template and not in $message already
if ($sender_name && strpos($templates[$type_key], '{sender_name}') === false && strpos($message, 'Sent by:') === false) {
    $senderLabel = $sender_name;
    if ($sender_type === 'client') {
        $senderLabel .= " (tenant)";
    } elseif ($sender_type === 'property_owner') {
        $senderLabel .= " (Owner)";
    } // Staff remains just as is ("TREA")
    $message = "Sent by: $senderLabel\n\n" . $message;
}

    // Check for unreplaced placeholders
    if (preg_match('/\{[a-z0-9_]+\}/i', $message)) {
        error_log("Some placeholders were not replaced in notification of type '$type_key'. Message: $message");
    }

    // Insert or get notification type id
    $type_id = null;
    $typeQuery = $pdo->prepare("SELECT type_id FROM notification_types WHERE type_key = ?");
    $typeQuery->execute([$type_key]);
    $typeRow = $typeQuery->fetch(PDO::FETCH_ASSOC);
    if ($typeRow) {
        $type_id = $typeRow['type_id'];
    } else {
        $insType = $pdo->prepare("INSERT INTO notification_types (type_key, title) VALUES (?, ?)");
        $insType->execute([$type_key, $title]);
        $type_id = $pdo->lastInsertId();
    }

    // -- Insert in-app notification, with sender/recipient info --
    $insNotif = $pdo->prepare(
        "INSERT INTO notifications 
         (recipient_id, recipient_type, type_id, title, message, link, is_read, created_at, sender_id, sender_type, sender_name)
         VALUES (?, ?, ?, ?, ?, ?, 0, NOW(), ?, ?, ?)"
    );
    $insNotif->execute([
        $recipient_id,
        $recipient_type,
        $type_id,
        $title,
        $message,
        $link,
        $sender_id,
        $sender_type,
        $sender_name
    ]);

// --- EMAIL NOTIFICATION ---
if ($send_email) {
    $recipient_email = null;
    $sender_email = null;

    // Get recipient email
    if ($recipient_type == 'client') {
        $userStmt = $pdo->prepare("SELECT u.email FROM users u JOIN clients c ON c.user_id = u.user_id WHERE c.client_id = ?");
        $userStmt->execute([$recipient_id]);
        $row = $userStmt->fetch(PDO::FETCH_ASSOC);
        if ($row) $recipient_email = $row['email'];
    } elseif ($recipient_type == 'property_owner') {
        $userStmt = $pdo->prepare("SELECT u.email FROM users u JOIN owners o ON o.user_id = u.user_id WHERE o.owner_id = ?");
        $userStmt->execute([$recipient_id]);
        $row = $userStmt->fetch(PDO::FETCH_ASSOC);
        if ($row) $recipient_email = $row['email'];
    } else { // staff
        // Use central email for notifications to managers
        $recipient_email = 'property-manager@trea.com';
    }

    // Get sender email
    if ($sender_type == 'client') {
        $senderStmt = $pdo->prepare("SELECT u.email FROM users u JOIN clients c ON c.user_id = u.user_id WHERE c.client_id = ?");
        $senderStmt->execute([$sender_id]);
        $srow = $senderStmt->fetch(PDO::FETCH_ASSOC);
        $sender_email = $srow && !empty($srow['email']) ? $srow['email'] : 'noreply@trea.com';
    } else {
        $sender_email = 'noreply@trea.com';
    }

    if ($recipient_email) {
        // For client → staff, use client's real email as FROM
        if ($sender_type == 'client' && $recipient_type == 'staff' && $sender_email !== 'noreply@trea.com') {
            $headers = "From: {$sender_email}\r\nReply-To: {$sender_email}\r\n";
        } else {
            $headers = "From: noreply@trea.com\r\nReply-To: noreply@trea.com\r\n";
        }
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        @mail($recipient_email, $title, $message, $headers);
    }
}
    return true;
}
?>