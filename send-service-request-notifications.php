<?php
/**
 * ---------------------------------------------------------------------
 * send-service-request-notifications.php
 * ---------------------------------------------------------------------
 * Utility function to send notifications when an owner submits a
 * service request. Notifies both the owner (confirmation) and the
 * responsible staff (based on service type).
 * 
 * @param PDO    $pdo           PDO database connection
 * @param int    $owner_id      Owner user ID
 * @param string $owner_name    Owner's full name
 * @param array  $service       Service row (should include 'service_name')
 * @param string $property_name Name of the relevant property
 * @param string $slug          Service slug (for staff assignment)
 * ---------------------------------------------------------------------
 */

require_once 'notification-helper.php';

/**
 * Sends notifications to both owner and assigned staff for a service request.
 */
function sendServiceRequestNotifications($pdo, $owner_id, $owner_name, $service, $property_name, $slug)
{
    // --------------------------------------------------------------
    // 1. Notify Owner: Confirmation of their submission
    // --------------------------------------------------------------
    notify(
        $pdo,
        $owner_id,
        'property_owner',
        'service_request_submitted',
        [
            '{service}'  => $service['service_name'],
            '{property}' => $property_name
        ],
        'owner-profile.php'
    );

    // --------------------------------------------------------------
    // 2. Determine Responsible Staff Role
    // --------------------------------------------------------------
    $staffRole = match ($slug) {
        'legal_assistance'                          => 'Legal Officer',
        'architectural_design', 'construction_supervision' => 'Plan and Supervision Manager',
        default                                     => 'Property Manager'
    };

    // --------------------------------------------------------------
    // 3. Find the First Staff Member with This Role
    // --------------------------------------------------------------
    $managerStmt = $pdo->prepare(
        "SELECT staff_id FROM staff WHERE LOWER(role) = LOWER(?) LIMIT 1"
    );
    $managerStmt->execute([$staffRole]);
    $manager = $managerStmt->fetch();

    // --------------------------------------------------------------
    // 4. Notify Responsible Staff
    // --------------------------------------------------------------
    if ($manager) {
        notify(
            $pdo,
            $manager['staff_id'],
            'staff',
            'new_service_request',
            [
                '{owner}'   => $owner_name,
                '{service}' => $service['service_name'],
                '{property}'=> $property_name
            ],
            'manage-service-requests.php'
        );
    }
}
