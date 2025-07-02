<?php
/*
|--------------------------------------------------------------------------
| reset-password.php
|--------------------------------------------------------------------------
| Secure password reset page for TREA platform users.
| 1. Accepts an email to send a password reset link (with unique token).
| 2. Allows password reset via valid token.
| 3. Uses Bootstrap 5.3.6 for a clean, responsive UI.
| 4. Enforces one-hour token expiration and clears tokens after use.
|--------------------------------------------------------------------------
*/

session_start();
require 'db_connect.php';

$token = $_GET['token'] ?? null;
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Password Reset (Step 2)
    if (isset($_POST['password'], $_POST['password2'], $_POST['token'])) {
        $token = $_POST['token'];
        $new_password = $_POST['password'];
        $confirm_password = $_POST['password2'];

        if ($new_password !== $confirm_password) {
            $error = "Passwords do not match.";
        } elseif (strlen($new_password) < 8) {
            $error = "Password must be at least 8 characters.";
        } else {
            $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE token = ?");
            $stmt->execute([$token]);
            $reset = $stmt->fetch();

            $expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));

            if (!$reset || new DateTime() > new DateTime($reset['expires_at'])) {
                $error = "Invalid or expired token.";
            } else {
                $email = $reset['email'];
                $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                $pdo->prepare("UPDATE users SET password = ? WHERE email = ?")->execute([$hashed, $email]);
                $pdo->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);
                $success = "Password updated successfully. <a href='user-login.php'>Log in</a>.";
            }
        }
    }

// Request Reset Link (Step 1)
if (isset($_POST['email']) && empty($error)) {
    $email = trim($_POST['email']);
    $success = "If the email exists, a reset link was sent to your email.";
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        $user_id = $user['user_id'];
        $user_type = null;
        $checkClient = $pdo->prepare("SELECT 1 FROM clients WHERE user_id = ?");
        $checkClient->execute([$user_id]);
        if ($checkClient->fetch()) $user_type = 'client';
        else {
            $checkOwner = $pdo->prepare("SELECT 1 FROM owners WHERE user_id = ?");
            $checkOwner->execute([$user_id]);
            if ($checkOwner->fetch()) $user_type = 'property_owner';
        }

        // Remove old tokens, insert new with 15 min expiry
        $pdo->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));
        $pdo->prepare("INSERT INTO password_resets (email, token, user_type, expires_at) VALUES (?, ?, ?, ?)")
            ->execute([$email, $token, $user_type, $expires]);

        // Send email
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
        $domain = $_SERVER['HTTP_HOST'] ?? 'yourdomain.com';
        $path = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/reset-password.php';
        $link = "$scheme://$domain$path?token=$token";
        $subject = "TREA Password Reset";
        $message = "To reset your password, click the link below (valid for 15 minutes):\n\n$link\n\nIf you didn't request this, you can ignore this email.";
        $headers = "From: TREA <noreply@$trea.com>";

        @mail($email, $subject, $message, $headers);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">    
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Password Reset | TREA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css?v=<?= time() ?>">
</head>
<body class="d-flex flex-column min-vh-100 bg-light">
<?php include 'header.php'; ?>
<div class="container-fluid flex-grow-1 py-4">
    <div class="row justify-content-center">
        <div class="col-lg-5 col-md-8 col-12">
            <div class="reset-container mt-5 p-4 bg-white rounded shadow-sm border">
                <h3 class="mb-4">Password Reset</h3>

                <?php if ($success): ?>
                    <div class="alert alert-success"><?= $success ?></div>
                <?php elseif ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <!-- Show nothing else after success -->
                <?php elseif ($token && empty($success)): ?>
                    <!-- Password Reset Form (Step 2) -->
                    <form method="POST" novalidate>
                        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                        <div class="mb-3">
                            <label for="password" class="form-label">New Password</label>
                            <input type="password" id="password" name="password" class="form-control" minlength="8" required>
                        </div>
                        <div class="mb-3">
                            <label for="password2" class="form-label">Confirm New Password</label>
                            <input type="password" id="password2" name="password2" class="form-control" minlength="8" required>
                        </div>
                        <button type="submit" class="btn custom-btn w-100">Reset Password</button>
                    </form>
                <?php else: ?>
                    <!-- Email Request Form (Step 1) -->
                    <form method="POST" novalidate>
                        <div class="mb-3">
                            <label for="email" class="form-label">Account Email</label>
                            <input type="email" id="email" name="email" class="form-control" required autofocus>
                        </div>
                        <button type="submit" class="btn custom-btn w-100">Send Reset Link</button>
                    </form>
                <?php endif; ?>

                <div class="mt-4 text-center">
                    <a href="user-login.php" class="link-secondary">Back to login</a>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include 'footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Password confirm 
document.addEventListener("DOMContentLoaded", function() {
    var pwd = document.getElementById('password');
    var pwd2 = document.getElementById('password2');
    var form = pwd && pwd2 && pwd.closest('form');
    if (form) {
        form.addEventListener('submit', function(e) {
            if (pwd.value !== pwd2.value) {
                alert('Passwords do not match.');
                e.preventDefault();
                pwd2.focus();
            }
        });
    }
});
</script>
<script src="navbar-close.js?v=1"></script>
</body>
</html>
