<?php
/**
 * manage-client-claims.php
 * ------------------------
 * Page for staff to review and confirm property claims submitted by clients.
 * Accessible to staff with role 'general manager' or 'property manager'.
 * 
 * Requirements:
 *   - User must be logged in as staff with correct role.
 *   - Displays table of all client property claims.
 *   - Allows confirming claim payments (if proof uploaded).
 * 
 * Related Files:
 *   - db_connect.php
 *   - header.php, footer.php
 *   - confirm-claim-payment.php (for confirmation action)
 */

// Start session and check user authentication/authorization
session_start();
require 'db_connect.php';

// Redirect to login if staff not authenticated or not allowed role
if (!isset($_SESSION['staff_id']) || !in_array(strtolower($_SESSION['role']), ['general manager', 'property manager'])) {
    $_SESSION['redirect_after_login'] = 'manage-client-claims.php';
    header("Location: staff-login.php");
    exit();
}

// Fetch session info for sidebar
$fullName = $_SESSION['full_name'] ?? 'Staff';
$userId = $_SESSION['staff_id'] ?? '';
$profilePicture = $_SESSION['profile_picture_path'] ?? '';

// Fetch all client claims with client and property info
$claims = $pdo->query("
    SELECT cc.*, u.full_name AS client_name, p.property_name, p.image
    FROM client_claims cc
    JOIN clients c ON cc.client_id = c.client_id
    JOIN users u ON c.user_id = u.user_id
    JOIN properties p ON cc.property_id = p.property_id 
    ORDER BY cc.claimed_at DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Manage Client Claims</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Uniform Bootstrap version and CSS -->
  <link rel="stylesheet" href="styles.css?v=<?= time() ?>">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="d-flex flex-column min-vh-100 bg-light">

<?php include 'header.php'; ?>

<div class="container-fluid d-flex flex-grow-1 flex-column p-0">
    <div class="row flex-grow-1 g-0" style="flex: 1 0 auto; min-height: calc(100vh - 120px);">

        <!-- Sidebar with profile summary -->
        <div class="col-md-2 sidebar bg-white border-end">
            <div class="profile-summary text-center p-3">
                <?php
                $profilePicturePath = (!empty($profilePicture) && file_exists($profilePicture)) 
                    ? $profilePicture : 'images/default.png'; // Default profile picture path
                ?>
                <img src="<?= htmlspecialchars($profilePicturePath) ?>" alt="Profile Picture" class="mb-3 rounded-circle" style="width: 80px; height: 80px; object-fit: cover;">
                <p><strong><?= htmlspecialchars($fullName) ?></strong></p>
                <p>ID: <?= htmlspecialchars($userId) ?></p>
                <a href="notifications.php" class="btn btn-sm btn-outline-primary mt-3 w-100">View Notifications</a>
                <a href="edit-staff-profile.php" class="btn btn-sm btn-outline-secondary mt-2 w-100">Edit Profile</a>
                <a href="staff-logout.php" class="btn btn-sm btn-outline-danger mt-2 w-100">Logout</a>
            </div>
            <!-- Optional calendar widget (can be removed if not needed) -->
            <div class="p-3 d-none d-md-block">
                <h6>Calendar</h6>
                <iframe src="https://calendar.google.com/calendar/embed?mode=MONTH" style="border: 0; width: 100%; height: 200px;" frameborder="0" scrolling="no"></iframe>
            </div>
        </div>

        <!-- Main content area -->
        <main class="col-md-10 p-4">
            <h2 class="mb-4 text-primary">Manage Property Claims</h2>

            <?php if (isset($_GET['confirmed'])): ?>
                <div class="alert alert-success">Claim payment has been confirmed.</div>
            <?php endif; ?>

            <?php if ($claims): ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle bg-white">
                        <thead class="table-light">
                            <tr>
                                <th>Client</th>
                                <th>Property</th>
                                <th>Claim Type</th>
                                <th>Payment Proof</th>
                                <th>Claimed At</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($claims as $c): ?>
                            <tr>
                                <td><?= htmlspecialchars($c['client_name']) ?></td>
                                <td><?= htmlspecialchars($c['property_name']) ?></td>
                                <td><?= ucfirst($c['claim_type']) ?></td>
                                <td>
                                    <?php if ($c['payment_proof']): ?>
                                        <a href="<?= htmlspecialchars($c['payment_proof']) ?>" target="_blank">View</a>
                                    <?php else: ?>
                                        <span class="text-muted">Not uploaded</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= date('Y-m-d H:i', strtotime($c['claimed_at'])) ?></td>
                                <td><?= ucfirst($c['claim_status']) ?></td>
                                <td>
                                    <?php if ($c['claim_status'] === 'pending' && $c['payment_proof']): ?>
                                        <!-- Payment confirmation form -->
                                        <form method="POST" action="confirm-claim-payment.php" class="d-flex gap-2">
                                            <input type="hidden" name="claim_id" value="<?= $c['claim_id'] ?>">
                                            <button class="btn btn-sm btn-success" onclick="return confirm('Confirm payment?');">Confirm</button>
                                        </form>
                                    <?php elseif ($c['claim_status'] === 'approved'): ?>
                                        <span class="text-success fw-bold">Confirmed</span>
                                    <?php else: ?>
                                        <span class="text-muted">Pending</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p>No claims submitted yet.</p>
            <?php endif; ?>
        </main>
    </div>
</div>

<?php include 'footer.php'; ?>
<!-- Bootstrap JS for responsiveness -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
