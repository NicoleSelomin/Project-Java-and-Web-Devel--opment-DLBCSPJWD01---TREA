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

// --- Handle password reset form submission (Step 2) ---
$token = $_GET['token'] ?? null;
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Step 2: Handle password update
    if (isset($_POST['password'], $_POST['token'])) {
        $token = $_POST['token'];
        $new_password = $_POST['password'];

        // Look up token in resets table
        $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE token = ?");
        $stmt->execute([$token]);
        $reset = $stmt->fetch();

        if (!$reset || new DateTime() > new DateTime($reset['expires_at'])) {
            $error = "Invalid or expired token.";
        } else {
            $email = $reset['email'];
            // Update password in users table
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password = ? WHERE email = ?")
                ->execute([$hashed, $email]);
            // Remove token
            $pdo->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);
            $success = "Password updated successfully. <a href='user-login.php'>Log in</a>.";
        }
    }

    // Step 1: Handle email for reset link
    if (isset($_POST['email'])) {
        $email = trim($_POST['email']);
        // Find user by email
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            $error = "Email not found.";
        } else {
            // Determine user type
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

            if (!$user_type) {
                $error = "Account type not recognized.";
            } else {
                // Generate and store reset token
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
                $pdo->prepare("INSERT INTO password_resets (email, token, user_type, expires_at) VALUES (?, ?, ?, ?)")
                    ->execute([$email, $token, $user_type, $expires]);

                // Compose reset link
                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
                $domain = $_SERVER['HTTP_HOST'] ?? 'yourdomain.com';
                $path = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/reset-password.php';
                $link = "$scheme://$domain$path?token=$token";

                // Send reset email
                $subject = "Password Reset";
                $message = "To reset your password, click the link below (valid for 1 hour):\n\n$link\n\nIf you didn't request this, please ignore this email.";
                $headers = "From: no-reply@$domain";

                mail($email, $subject, $message, $headers);
                $success = "A reset link was sent to your email.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Password Reset | TREA</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .reset-container {
            max-width: 420px;
            margin: 60px auto;
            padding: 2rem 2.5rem;
            background: #fff;
            border-radius: 1rem;
            box-shadow: 0 0.25rem 1rem rgba(0,0,0,0.08);
        }
    </style>
</head>
<body class="bg-light">
    <div class="reset-container">
        <h3 class="mb-4 text-primary">Password Reset</h3>

        <!-- Success/Error Messages -->
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
                    <div class="form-text">Password must be at least 8 characters.</div>
                </div>
                <button type="submit" class="btn btn-primary w-100">Reset Password</button>
            </form>
        <?php else: ?>
            <!-- Email Request Form (Step 1) -->
            <form method="POST" novalidate>
                <div class="mb-3">
                    <label for="email" class="form-label">Account Email</label>
                    <input type="email" id="email" name="email" class="form-control" required autofocus>
                </div>
                <button type="submit" class="btn btn-primary w-100">Send Reset Link</button>
            </form>
        <?php endif; ?>

        <div class="mt-4 text-center">
            <a href="user-login.php" class="link-secondary">Back to login</a>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
