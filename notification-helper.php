<?php
/**
 * notification-helper.php
 * 
 * Loads notification templates from a JSON file, replaces placeholders,
 * and inserts a new notification into the notifications table.
 *
 * @param PDO $pdo                   PDO database connection
 * @param int $recipient_id          Recipient user/staff ID
 * @param string $recipient_type     'client', 'property_owner', 'staff', etc.
 * @param string $type_key           Notification type key as in notifications.json
 * @param array $replacements        Placeholder values, e.g. ['{file}' => 'invoice', '{entity}' => 'property']
 * @param string $link               Notification link (default '#')
 */
function notify($pdo, $recipient_id, $recipient_type, $type_key, $replacements = [], $link = '#')
{
    // Step 1: Load notification templates from JSON (cache if in production)
    $jsonPath = __DIR__ . '/notifications.json';
    if (!file_exists($jsonPath)) {
        // Fail silently if file is missing
        return;
    }
    $templates = json_decode(file_get_contents($jsonPath), true);

    // Step 2: Get the template for this notification type
    if (empty($templates[$type_key])) {
        // No template found for this key
        return;
    }
    $message = $templates[$type_key];

    // Step 3: Replace placeholders (e.g., {file} => "invoice", {entity} => "property")
    $message = strtr($message, $replacements);

    // Step 4: Insert notification into the database
    $stmt = $pdo->prepare(
        "INSERT INTO notifications 
         (recipient_id, recipient_type, title, message, link, is_read, created_at, type)
         VALUES (?, ?, ?, ?, ?, 0, NOW(), ?)"
    );
    $title = ucfirst(str_replace('_', ' ', $type_key)); // Example title
    $stmt->execute([
        $recipient_id,
        $recipient_type,
        $title,
        $message,
        $link,
        $type_key
    ]);
}
