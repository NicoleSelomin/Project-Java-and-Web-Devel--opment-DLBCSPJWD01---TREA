<?php
/**
 * view-property.php
 * ---------------------------------------------------------
 * Displays all details for a single property listing.
 * Action buttons vary for client, guest, owner, staff.
 * ---------------------------------------------------------
 */
require 'db_connect.php';
session_start();

// Validate property ID and fetch details
if (!isset($_GET['property_id'])) {
    echo "Property not found.";
    exit();
}
$property_id = intval($_GET['property_id']);
$stmt = $pdo->prepare("SELECT * FROM properties WHERE property_id = ?");
$stmt->execute([$property_id]);
$property = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$property) {
    echo "Property not found.";
    exit();
}

// ---------- WHO IS VIEWING? ----------
$isClient = isset($_SESSION['client_id']);
$isOwner  = isset($_SESSION['owner_id']);
$isStaff  = isset($_SESSION['staff_id']);
$isGuest  = !$isClient && !$isOwner && !$isStaff;

// Prepare variables for action area
$actionHTML = '';

if ($isClient) {
    $client_id = $_SESSION['client_id'];
    // Is this property in the client's cart?
    $cartStmt = $pdo->prepare("SELECT 1 FROM client_cart WHERE client_id = ? AND property_id = ?");
    $cartStmt->execute([$client_id, $property_id]);
    $inCart = (bool)$cartStmt->fetchColumn();

    // Has the client already booked a visit for this property?
    $visitStmt = $pdo->prepare("SELECT visit_id, visit_date, visit_time, status FROM client_onsite_visits WHERE client_id = ? AND property_id = ?");
    $visitStmt->execute([$client_id, $property_id]);
    $visit = $visitStmt->fetch(PDO::FETCH_ASSOC);

    if ($visit) {
        // Already booked a visit: show cancel button only
        $actionHTML = <<<HTML
        <form action="cancel-client-visit.php" method="POST" class="mt-3">
            <input type="hidden" name="visit_id" value="{$visit['visit_id']}">
            <button type="submit" class="btn btn-danger">Cancel Visit</button>
        </form>
        <div class="text-success small mt-2">Visit booked for this property.</div>
        HTML;
    } elseif ($inCart) {
        // Property is in cart, show booking and remove options

        // Fetch agent assigned during inspection and their available slots
        $stmtAgent = $pdo->prepare("SELECT assigned_agent_id FROM owner_service_requests WHERE property_id = ?");
        $stmtAgent->execute([$property_id]);
        $agentId = $stmtAgent->fetchColumn();
        $slots = [];
        if ($agentId) {
            $stmtSlots = $pdo->prepare("SELECT start_time, end_time FROM agent_schedule
                WHERE agent_id = ? AND status = 'available' AND start_time > NOW()
                ORDER BY start_time ASC LIMIT 20");
            $stmtSlots->execute([$agentId]);
            $slots = $stmtSlots->fetchAll(PDO::FETCH_ASSOC);
        }

        // Build the select dropdown
        ob_start();
        ?>
        <form action="client-book-visit.php" method="POST" class="mt-3">
            <input type="hidden" name="property_id" value="<?= $property_id ?>">
            <label for="visit_slot_<?= $property_id ?>" class="form-label">
                Select Visit Date/Time:
            </label>
            <select name="visit_slot" class="form-select mb-2" id="visit_slot_<?= $property_id ?>" required <?= !$agentId ? 'disabled' : '' ?>>
                <?php if (!$agentId): ?>
                    <option>No agent assigned yet</option>
                <?php elseif (empty($slots)): ?>
                    <option>No available slots for this agent</option>
                <?php else: ?>
                    <?php foreach ($slots as $slot): ?>
                        <option value="<?= htmlspecialchars($slot['start_time']) ?>">
                            <?= date('D, M j Y, H:i', strtotime($slot['start_time'])) ?> -
                            <?= date('H:i', strtotime($slot['end_time'])) ?>
                        </option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
            <button type="submit"
                    class="btn custom-btn"
                    <?= !$agentId || empty($slots) ? 'disabled' : '' ?>>
                Book Visit
            </button>
        </form>
        <form action="remove-from-cart.php" method="POST" class="mt-2">
            <input type="hidden" name="property_id" value="<?= $property_id ?>">
            <button type="submit" class="btn btn-outline-danger">Remove from Cart</button>
        </form>
        <?php
        $actionHTML = ob_get_clean();
    } else {
        // Not in cart, not booked – show Add to Cart only
        $actionHTML = <<<HTML
        <form action="add-to-cart.php" method="GET" class="mt-4">
            <input type="hidden" name="id" value="{$property_id}">
            <button type="submit" class="btn custom-btn">Add to Cart</button>
        </form>
        HTML;
    }

// ---------- GUEST LOGIC ----------
} elseif ($isGuest) {
    $inCart = isset($_SESSION['cart']) && is_array($_SESSION['cart']) && in_array($property_id, $_SESSION['cart']);
    if ($inCart) {
        // Fetch agent assigned during inspection and their available slots
        $stmtAgent = $pdo->prepare("SELECT assigned_agent_id FROM owner_service_requests WHERE property_id = ?");
        $stmtAgent->execute([$property_id]);
        $agentId = $stmtAgent->fetchColumn();
        $slots = [];
        if ($agentId) {
            $stmtSlots = $pdo->prepare("SELECT start_time, end_time FROM agent_schedule
                WHERE agent_id = ? AND status = 'available' AND start_time > NOW()
                ORDER BY start_time ASC LIMIT 20");
            $stmtSlots->execute([$agentId]);
            $slots = $stmtSlots->fetchAll(PDO::FETCH_ASSOC);
        }

        ob_start();
        ?>
        <form action="client-book-visit.php" method="POST" class="mt-3">
            <input type="hidden" name="property_id" value="<?= $property_id ?>">
            <label for="visit_slot_<?= $property_id ?>" class="form-label">
                Select Visit Date/Time:
            </label>
            <select name="visit_slot" class="form-select mb-2" id="visit_slot_<?= $property_id ?>" required <?= !$agentId ? 'disabled' : '' ?>>
                <?php if (!$agentId): ?>
                    <option>No agent assigned yet</option>
                <?php elseif (empty($slots)): ?>
                    <option>No available slots for this agent</option>
                <?php else: ?>
                    <?php foreach ($slots as $slot): ?>
                        <option value="<?= htmlspecialchars($slot['start_time']) ?>">
                            <?= date('D, M j Y, H:i', strtotime($slot['start_time'])) ?> -
                            <?= date('H:i', strtotime($slot['end_time'])) ?>
                        </option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
            <button type="submit"
                    class="btn custom-btn"
                    <?= !$agentId || empty($slots) ? 'disabled' : '' ?>>
                Book Visit
            </button>
        </form>
        <form action="remove-from-cart.php" method="POST" class="mt-2">
            <input type="hidden" name="property_id" value="<?= $property_id ?>">
            <button type="submit" class="btn btn-outline-danger">Remove from Cart</button>
        </form>
        <div class="small text-muted mt-1">You’ll be asked to sign up or log in to book a visit.</div>
        <?php
        $actionHTML = ob_get_clean();
    } else {
        $actionHTML = <<<HTML
        <form action="add-to-cart.php" method="GET" class="mt-4">
            <input type="hidden" name="id" value="{$property_id}">
            <button type="submit" class="btn custom-btn">Add to Cart</button>
        </form>
        HTML;
    }

// ---------- OWNER & STAFF ----------
} else {
    // No actions for owner or staff
    $actionHTML = <<<HTML
    <div class="alert alert-info mt-4">
        Log in as a client to add to cart or book a visit.
    </div>
    HTML;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($property['property_name']) ?> - TREA</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="styles.css?v=<?= time() ?>">
</head>
<body class="d-flex flex-column min-vh-100 bg-light">

<?php include 'header.php'; ?>

<div class="container-fluid flex-grow-1 py-4">
    <div class="row justify-content-center">
        <main class="container py-5 flex-grow-1">
            <div class="row align-items-center g-5">
                <!-- Property Image -->
                <div class="col-md-6">
                    <img src="<?= htmlspecialchars($property['image'] ?? 'uploads/properties/default.jpg') ?>"
                         class="img-fluid rounded shadow-sm w-100"
                         alt="Property Image">
                </div>
                <!-- Property Details -->
                <div class="col-md-6">
                    <h2 class="mb-3"><?= htmlspecialchars($property['property_name']) ?></h2>
                    <p><strong>Listing Type:</strong> <?= htmlspecialchars(ucfirst($property['listing_type'])) ?></p>
                    <p><strong>Property Type:</strong> <?= htmlspecialchars(ucfirst($property['property_type'])) ?></p>
                    <p><strong>Location:</strong> <?= htmlspecialchars($property['location']) ?></p>
                    <p><strong>Size:</strong> <?= htmlspecialchars($property['size_sq_m']) ?> m²</p>
                    <p><strong>Bedrooms:</strong> <?= $property['number_of_bedrooms'] ?? '—' ?></p>
                    <p><strong>Bathrooms:</strong> <?= $property['number_of_bathrooms'] ?? '—' ?></p>
                    <p><strong>Floors:</strong> <?= $property['floor_count'] ?? '—' ?></p>
                    <p class="lead text-success"><strong>Price:</strong> CFA<?= number_format($property['price']) ?></p>
                    <p><strong>Description:</strong><br><?= nl2br(htmlspecialchars($property['property_description'])) ?></p>

                    <!-- ---------- ACTION BUTTON AREA ----------- -->
                    <?= $actionHTML ?>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include 'footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
<script src="navbar-close.js?v=1"></script>
</body>
</html>
