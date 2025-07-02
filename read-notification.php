<?php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: user-login.php');
    exit();
}

$notification_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Check that the notification belongs to this user
$stmt = $pdo->prepare("SELECT * FROM notifications WHERE notification_id = ? AND recipient_id = ?");
$stmt->execute([$notification_id, $_SESSION['user_id']]);
$notification = $stmt->fetch(PDO::FETCH_ASSOC);

if ($notification) {
    // Mark as read
    $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = ?")->execute([$notification_id]);
} else {
    // Just go back to notifications page
    header('Location: notifications.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Notification Details</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css?v=<?= time() ?>">
</head>
<body class="bg-light d-flex flex-column min-vh-100">
<?php include 'header.php'; ?>
<main class="container my-5 flex-grow-1">
    <div class="row justify-content-center">
        <div class="col-12 col-md-8 col-lg-7">
            <div class="card shadow">
                <div class="card-header bg-dark text-white">
                    <h4 class="mb-0"><?= htmlspecialchars($notification['title']) ?></h4>
                </div>
                <div class="card-body">
                    <p class="mb-3"><?= nl2br(htmlspecialchars($notification['message'])) ?></p>
                    <small class="text-muted">
                        Sent: <?= date('F j, Y, g:i a', strtotime($notification['created_at'])) ?>
                    </small>
                    <?php if (!empty($notification['link']) && $notification['link'] !== '#'): ?>
                        <div class="mt-4">
                            <a href="<?= htmlspecialchars($notification['link']) ?>" class="btn custom-btn">
                                Go to Related Page
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="card-footer text-end">
                    <a href="notifications.php" class="btn btn-link">🡰 Back to All Notifications</a>
                </div>
            </div>
        </div>
    </div>
</main>
<?php include 'footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
