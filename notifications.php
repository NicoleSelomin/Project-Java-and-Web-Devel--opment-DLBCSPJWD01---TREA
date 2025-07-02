<?php
/*
|--------------------------------------------------------------------------
| notifications.php - User Notifications Page
|--------------------------------------------------------------------------
| - Shows all notifications for the currently logged-in client or property owner.
| - Ensures user authentication and loads notifications from the database.
| - Responsive design, consistent with Bootstrap 5.3.6.
|---------------------------------------------------------------------------
*/

// Start session and include DB connection
session_start();
require 'db_connect.php';

$user_id = null;
$user_type = null;

// Client or owner (users table)
if (isset($_SESSION['user_id']) && isset($_SESSION['user_type'])) {
    $user_id = $_SESSION['user_id'];
    $user_type = $_SESSION['user_type'];
}
// Staff (staff table)
elseif (isset($_SESSION['staff_id'])) {
    $user_id = $_SESSION['staff_id'];
    $user_type = 'staff';
} else {
    header("Location: user-login.php");
    exit();
}

$stmt = $pdo->prepare(
    "SELECT * FROM notifications 
     WHERE recipient_id = ? AND recipient_type = ?
     ORDER BY created_at DESC"
);
$stmt->execute([$user_id, $user_type]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Basic page setup -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Your Notifications - TREA</title>
    <!-- Uniform Bootstrap 5.3.6 for consistent UI/UX -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet" 
          integrity="sha384-4Q6Gf2aSP4eDXB8Miphtr37CMZZQ5oXLH2yaXMJ2w8e2ZtHTl7GptT4jmndRuHDT" crossorigin="anonymous">
    <!-- Always include latest custom styles for consistency -->
    <link rel="stylesheet" href="styles.css?v=<?= time() ?>">
</head>

<body class="d-flex flex-column min-vh-100 bg-light text-dark">
    <!-- Site-wide header -->
    <?php include 'header.php'; ?>

    <div class="container-fluid flex-grow-1 py-4">
    <div class="row">

    <!-- Main notification container (responsive) -->
    <main class="container py-5 flex-grow-1">
        <div class="row justify-content-center">
            <div class="col-12 col-md-8 col-lg-9">
                <h2 class="text-center mb-4">Your Notifications</h2>

                <?php if (empty($notifications)): ?>
                    <!-- Show message if there are no notifications -->
                    <div class="alert alert-info text-center">
                        You have no notifications.
                    </div>
                <?php else: ?>
                    <!-- List of notifications, using Bootstrap list-group for uniform appearance -->
<div class="list-group shadow-sm">
<?php foreach ($notifications as $note): ?>
    <a href="read-notification.php?id=<?= $note['notification_id'] ?>&redirect=<?= urlencode($note['link'] ?? '#') ?>"
       class="list-group-item list-group-item-action d-flex justify-content-between align-items-center
           <?= !$note['is_read'] ? 'list-group-item-warning' : '' ?>">
        <div>
            <!-- Notification type badge -->
            <?php if (isset($note['type'])): ?>
                <?php if ($note['type'] === 'termination'): ?>
                    <span class="badge bg-danger me-2">Termination Notice</span>
                <?php elseif ($note['type'] === 'reminder'): ?>
                    <span class="badge bg-info me-2">Contract End Reminder</span>
                <?php endif; ?>
            <?php endif; ?>

            <!-- Notification title -->
            <h5 class="mb-1"><?= htmlspecialchars($note['title']) ?></h5>

            <!-- Sender name, with fallback -->
            <span class="text-muted small">
                Sent by: <?= htmlspecialchars($note['sender_name'] ?? 'System') ?>
            </span>

            <!-- Message preview -->
            <?php
                $msg = strip_tags($note['message']);
                $preview = mb_strlen($msg) > 70 ? mb_substr($msg, 0, 70) . '…' : $msg;
            ?>
            <p class="mb-1 text-muted small"><?= htmlspecialchars($preview) ?></p>
            <small class="text-muted"><?= date('F j, Y, g:i a', strtotime($note['created_at'])) ?></small>
        </div>
        <?php if (!$note['is_read']): ?>
            <span class="badge custom-btn ms-2">New</span>
        <?php endif; ?>
    </a>
<?php endforeach; ?>
</div>

                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
    </div>
    </div>

    <!-- Site-wide footer for uniformity -->
    <?php include 'footer.php'; ?>

    <!-- Bootstrap JS for responsiveness and interactivity -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"
            integrity="sha384-j1CDi7MgGQ12Z7Qab0qlWQ/Qqz24Gc6BM0thvEMVjHnfYGF0rmFCozFSxQBxwHKO" crossorigin="anonymous"></script>

            <!-- Script to close main navbar on small screen-->
<script src="navbar-close.js?v=1"></script>

</body>
</html>