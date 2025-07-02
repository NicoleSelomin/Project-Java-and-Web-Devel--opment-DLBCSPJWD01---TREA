<?php
/*
|--------------------------------------------------------------------------
| owner-listed-brokerage.php
|--------------------------------------------------------------------------
| For owners to view/manage their brokerage-listed properties.
| - Shows thumbnail image & name (linked to view-property.php)
| - Displays claim status, meeting, invoices, and allows upload of payment proof
| - Bootstrap 5.3.6 for clean, responsive UI
|--------------------------------------------------------------------------
*/

require_once 'check-user-session.php'; // Owner session check
require 'db_connect.php';

// Get logged-in owner info
$ownerId = $_SESSION['owner_id'];
$ownerName = $_SESSION['user_name'] ?? 'Unknown Owner';

// Handle owner payment proof upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['payment_proof'], $_POST['claim_id'])) {
    $claim_id = $_POST['claim_id'];

    // Get property & owner details for correct folder path
    $stmt = $pdo->prepare("
        SELECT p.property_id, p.property_name, p.owner_id, u.full_name AS owner_name, cc.claim_type
        FROM client_claims cc
        JOIN properties p ON cc.property_id = p.property_id
        JOIN owners o ON p.owner_id = o.owner_id
        JOIN users u ON o.user_id = u.user_id
        WHERE cc.claim_id = ?
    ");
    $stmt->execute([$claim_id]);
    $info = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($info) {
        $owner_id = $info['owner_id'];
        $owner_name = preg_replace('/[^a-zA-Z0-9_]/', '_', $info['owner_name']);
        $property_id = $info['property_id'];
        $property_name = preg_replace('/[^a-zA-Z0-9_]/', '_', $info['property_name']);
        $claim_type = $info['claim_type'];

        $folder = "uploads/owner/{$owner_id}_{$owner_name}/listed_properties/{$property_id}_{$property_name}/";
        if (!is_dir($folder)) mkdir($folder, 0777, true);

        $ext = pathinfo($_FILES['payment_proof']['name'], PATHINFO_EXTENSION);
        $filename = "payment_proof_owner_" . time() . "." . $ext;
        $destination = $folder . $filename;

        if (move_uploaded_file($_FILES['payment_proof']['tmp_name'], $destination)) {
            $update = $pdo->prepare("
                UPDATE brokerage_claim_payments 
                SET payment_proof = ?, payment_status = 'pending' 
                WHERE claim_id = ? AND payment_type = 'owner'
            ");
            $update->execute([$destination, $claim_id]);

            // If sale, mark property unavailable
            if ($claim_type === 'sale') {
                $markUnavailable = $pdo->prepare("
                    UPDATE properties SET availability = 'unavailable' WHERE property_id = ?
                ");
                $markUnavailable->execute([$property_id]);
            }

            header("Location: " . $_SERVER['PHP_SELF'] . "?uploaded=1");
            exit();
        } else {
            echo "<div class='alert alert-danger'>Upload failed.</div>";
        }
    }
}

// Get owner's brokerage-listed properties with claim/invoice/payment info
$listedStmt = $pdo->prepare("
    SELECT  
        p.property_id, p.property_name, p.image, p.listing_type, p.location,
        cc.claim_id, cc.claim_type, cc.claim_source, cc.meeting_datetime, cc.final_status,
        u.full_name AS client_name, bcp.invoice_path AS claim_invoice,
        bcp.payment_proof, bcp.payment_status
    FROM properties p
    LEFT JOIN services s ON p.service_id = s.service_id
    LEFT JOIN client_claims cc ON cc.property_id = p.property_id AND cc.claim_source = 'brokerage'
    LEFT JOIN clients c ON cc.client_id = c.client_id
    LEFT JOIN users u ON c.user_id = u.user_id
    LEFT JOIN brokerage_claim_payments bcp ON bcp.claim_id = cc.claim_id AND bcp.payment_type = 'owner'
    WHERE p.owner_id = ? AND s.slug = 'brokerage'
    ORDER BY p.created_at DESC
");
$listedStmt->execute([$ownerId]);
$listedProperties = $listedStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Your Brokerage Properties - TREA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-4Q6Gf2aSP4eDXB8Miphtr37CMZZQ5oXLH2yaXMJ2w8e2ZtHTl7GptT4jmndRuHDT" crossorigin="anonymous">
    <link rel="stylesheet" href="styles.css?v=<?= time() ?>">
</head>
<body class="d-flex flex-column min-vh-100 bg-light">
<?php include 'header.php'; ?>

<div class="container-fluid flex-grow-1 py-4">
    <main class="container">
        <div class="p-3 mb-4 border rounded shadow-sm main-title">
            <h2>Brokerage Properties <small class="text-muted fs-6">for <?= htmlspecialchars($ownerName) ?></small></h2>
        </div>

        <?php if (isset($_GET['uploaded'])): ?>
            <div class="alert alert-success text-center">Payment proof uploaded successfully.</div>
            <script>
                setTimeout(() => {
                    const url = new URL(window.location);
                    url.searchParams.delete('uploaded');
                    window.history.replaceState(null, '', url);
                }, 3000);
            </script>
        <?php endif; ?>

        <div class="table-responsive">
            <table class="table table-bordered table-striped align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>Property</th>
                        <th>Type</th>
                        <th>Reserved By</th>
                        <th>reservation Invoice</th>
                        <th>Payment Proof</th>
                        <th>Meeting</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($listedProperties as $p): ?>
                    <tr>
                        <!-- Image and property link -->
                        <td>
                            <a href="view-property.php?property_id=<?= $p['property_id'] ?>" target="_blank"
                               class="d-flex align-items-center text-decoration-none text-dark">
                                <img src="<?= htmlspecialchars($p['image'] ?? 'uploads/properties/default.jpg') ?>"
                                     alt="Property"
                                     class="me-2 rounded border"
                                     style="width:36px; height:36px; object-fit:cover;">
                                <span><?= htmlspecialchars($p['property_name']) ?></span>
                            </a>
                            <div class="small text-muted"><?= htmlspecialchars($p['location']) ?></div>
                        </td>
                        <td><?= ucfirst($p['listing_type']) ?></td>
                        <td>
                            <?= $p['client_name'] ? htmlspecialchars($p['client_name']) : '<span class="text-muted">Not Claimed</span>' ?>
                        </td>
                        <td>
                            <?php if ($p['claim_invoice']): ?>
                                <a href="<?= htmlspecialchars($p['claim_invoice']) ?>" target="_blank">View</a>
                            <?php else: ?>
                                <span class="text-muted">Not Required</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($p['claim_invoice'] && !$p['payment_proof']): ?>
                                <!-- Upload payment proof if invoice present and not yet uploaded -->
                                <form method="POST" enctype="multipart/form-data" class="d-flex flex-column gap-1">
                                    <input type="hidden" name="claim_id" value="<?= $p['claim_id'] ?>">
                                    <input type="file" name="payment_proof" accept="image/*,application/pdf" required
                                           class="form-control form-control-sm">
                                    <button type="submit" class="btn btn-sm custom-btn">Upload Proof</button>
                                </form>
                            <?php elseif ($p['payment_proof']): ?>
                                <a href="<?= htmlspecialchars($p['payment_proof']) ?>" target="_blank">View</a>
                                <?php if ($p['payment_status'] === 'confirmed'): ?>
                                    <span class="badge bg-success ms-1">Confirmed</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark ms-1">Pending</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted">Not Required</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($p['meeting_datetime']): ?>
                                <?= date('d M Y, H:i', strtotime($p['meeting_datetime'])) ?>
                            <?php else: ?>
                                <span class="text-muted">Not Set</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                                if ($p['final_status']) {
                                    $badge = '';
                                    switch ($p['final_status']) {
                                        case 'completed': $badge = '<span class="badge bg-success">Completed</span>'; break;
                                        case 'cancelled': $badge = '<span class="badge bg-danger">Cancelled</span>'; break;
                                        case 'pending':   $badge = '<span class="badge bg-warning text-dark">Pending</span>'; break;
                                        default:          $badge = '<span class="badge bg-secondary">'.htmlspecialchars($p['final_status']).'</span>';
                                    }
                                    echo $badge;
                                } else {
                                    echo '<span class="text-muted">—</span>';
                                }
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <a href="owner-profile.php" class="btn bg-dark text-white fw-bold mt-4">🡰 Back to dashboard</a>
    </main>
</div>

<?php include 'footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
<script src="navbar-close.js?v=1"></script>
</body>
</html>
