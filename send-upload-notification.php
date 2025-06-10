<?php
/**
 * ---------------------------------------------------------------------
 * send-upload-notification.php
 * ---------------------------------------------------------------------
 * Utility for sending notifications when a file is uploaded by any actor.
 * Notifies both the recipient (so they can act or view) and the uploader
 * (confirmation of their action).
 * 
 * Usage:
 *   sendUploadNotification($pdo, $actorId, $actorType, $uploadType, $fileLabel,
 *                         $relatedEntity, $receiverType, $receiverId, $link);
 * ---------------------------------------------------------------------
 */

require_once 'notification-helper.php';

/**
 * Sends notifications when a file is uploaded by any actor.
 *
 * @param PDO    $pdo           Database connection
 * @param int    $actorId       ID of the user uploading the file
 * @param string $actorType     'staff', 'property_owner', 'client', 'agent'
 * @param string $uploadType    E.g. 'invoice', 'payment_proof', 'receipt', 'contract', 'report'
 * @param string $fileLabel     E.g. 'Agency Invoice', 'Signed Contract'
 * @param string $relatedEntity E.g. 'Service Request', 'Claim', 'Inspection'
 * @param string $receiverType  'staff', 'property_owner', 'client', 'agent'
 * @param int    $receiverId    The ID of the notification recipient
 * @param string $link          Link the receiver should follow
 * @return void
 */
function sendUploadNotification(
    PDO $pdo,
    int $actorId,
    string $actorType,
    string $uploadType, 
    string $fileLabel,
    string $relatedEntity,
    string $receiverType,
    int $receiverId,
    string $link
) {
    // -----------------------------------------------------------------
    // Notify the recipient (someone who needs to act/view the upload)
    // -----------------------------------------------------------------
    notify(
        $pdo,
        $receiverId,
        $receiverType,
        'file_uploaded',
        [
            '{file}'   => $fileLabel,
            '{entity}' => $relatedEntity
        ],
        $link
    );

    // -----------------------------------------------------------------
    // Notify the uploader (confirmation of their upload)
    // -----------------------------------------------------------------
    notify(
        $pdo,
        $actorId,
        $actorType,
        'file_upload_confirmation',
        [
            '{file}'   => $fileLabel,
            '{entity}' => $relatedEntity
        ],
        $link
    );
}
