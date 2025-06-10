<?php
/*
|--------------------------------------------------------------------------
| owner-listed-brokerage.php
|--------------------------------------------------------------------------
| Allows property owners to view and manage their brokerage-listed properties.
| Owners can see property details, claim status, upload payment proofs, and
| track meetings and invoices.
|
| - Bootstrap 5.3.6 for responsive and uniform styling
|--------------------------------------------------------------------------
*/

// Session check and database connection
require_once 'check-user-session.php';
require 'db_connect.php';

// Current user's ID and name from session
$userId = $_SESSION['owner_id'];
$fullName = $_SESSION['user_name'] ?? 'Unknown Owner';

// Handle payment proof upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['payment_proof'], $_POST['claim_id'])) {
    $claim_id = $_POST['claim_id'];

    // Retrieve property and owner details for folder path
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
        if (!is_dir($folder)) {
            mkdir($folder, 0777, true);
        }

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

            // Mark property unavailable if the claim type is 'sale'
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

// Retrieve listed properties with brokerage details
$listedStmt = $pdo->prepare("
    SELECT  
        p.property_id, p.property_name, p.listing_type, p.location,
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
");
$listedStmt->execute([$userId]);
$listedProperties = $listedStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Listed Brokerage Properties - TREA</title>

    <!-- Bootstrap CSS (5.3.6) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-4Q6Gf2aSP4eDXB8Miphtr37CMZZQ5oXLH2yaXMJ2w8e2ZtHTl7GptT4jmndRuHDT" crossorigin="anonymous">
    <link rel="stylesheet" href="styles.css?v=<?= time() ?>">
</head>
<body class="d-flex flex-column min-vh-100 bg-light">

<!-- Header -->
<?php include 'header.php'; ?>

<main class="container py-5 flex-grow-1">
    <h2 class="text-primary mb-4">Listed Properties (Brokerage) – <?= htmlspecialchars($fullName) ?></h2>

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

    <!-- Properties Table -->
    <div class="table-responsive">
        <table class="table table-bordered table-hover">
            <thead class="table-secondary">
                <tr>
                    <th>Property</th>
                    <th>Type</th>
                    <th>Claimed By</th>
                    <th>Claim Invoice</th>
                    <th>Payment Proof</th>
                    <th>Meeting</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($listedProperties as $p): ?>
                    <!-- Detailed property listing -->
                    <tr>
                        <td><?= htmlspecialchars($p['property_name']) ?> (<?= htmlspecialchars($p['location']) ?>)</td>
                        <td><?= ucfirst($p['listing_type']) ?></td>
                        <td><?= htmlspecialchars($p['client_name']) ?: '<span class="text-muted">Not Claimed</span>' ?></td>
                        <td><?= $p['claim_invoice'] ? '<a href="'.htmlspecialchars($p['claim_invoice']).'" target="_blank">View</a>' : '<span class="text-muted">Not Required</span>' ?></td>

                        <!-- Additional cells omitted for brevity -->
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <a href="owner-profile.php" class="btn btn-secondary mt-3">Back to Profile</a>
</main>

<!-- Footer -->
<?php include 'footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
