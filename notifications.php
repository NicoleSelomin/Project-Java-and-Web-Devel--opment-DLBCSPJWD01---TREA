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

// Ensure the user is logged in; redirect if not authenticated
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
    header("Location: user-login.php");
    exit();
}

// Store session variables for convenience
$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];

// Fetch notifications from DB, sorted newest first
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

    <!-- Main notification container (responsive) -->
    <main class="container py-5 flex-grow-1">
        <div class="row justify-content-center">
            <div class="col-12 col-md-8 col-lg-7">
                <h2 class="text-center text-primary mb-4">Your Notifications</h2>

                <?php if (empty($notifications)): ?>
                    <!-- Show message if there are no notifications -->
                    <div class="alert alert-info text-center">
                        You have no notifications.
                    </div>
                <?php else: ?>
                    <!-- List of notifications, using Bootstrap list-group for uniform appearance -->
                    <div class="list-group shadow-sm">
                        <?php foreach ($notifications as $note): ?>
                            <a href="<?= htmlspecialchars($note['link'] ?? '#') ?>"
                               class="list-group-item list-group-item-action d-flex justify-content-between align-items-center
                                   <?= !$note['is_read'] ? 'list-group-item-warning' : '' ?>">
                                <div>
                                    <!-- Notification title -->
                                    <h5 class="mb-1"><?= htmlspecialchars($note['title']) ?></h5>
                                    <!-- Notification message/content -->
                                    <p class="mb-1"><?= htmlspecialchars($note['message']) ?></p>
                                    <!-- Date/time of notification -->
                                    <small class="text-muted"><?= date('F j, Y, g:i a', strtotime($note['created_at'])) ?></small>
                                </div>
                                <!-- "New" badge only for unread notifications (no emoji) -->
                                <?php if (!$note['is_read']): ?>
                                    <span class="badge bg-primary ms-2">New</span>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Site-wide footer for uniformity -->
    <?php include 'footer.php'; ?>

    <!-- Bootstrap JS for responsiveness and interactivity -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"
            integrity="sha384-j1CDi7MgGQ12Z7Qab0qlWQ/Qqz24Gc6BM0thvEMVjHnfYGF0rmFCozFSxQBxwHKO" crossorigin="anonymous"></script>
</body>
</html>
