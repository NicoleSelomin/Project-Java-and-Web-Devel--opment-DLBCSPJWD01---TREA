<?php
/**
 * ----------------------------------------------------------------------
 * staff-login.php
 * ----------------------------------------------------------------------
 * Staff login page for TREA platform.
 * - Handles login via POST (email & password).
 * - Checks credentials, sets staff session.
 * - Redirects to allowed staff-only dashboard/pages.
 * ----------------------------------------------------------------------
 */
ob_start();
session_start();
require 'db_connect.php';

// ----------------------------------------------------------------------
// Handle POST: Staff login attempt
// ----------------------------------------------------------------------
$error = '';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    $sql = "SELECT * FROM staff WHERE email = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['staff_id']   = $user['staff_id'];
        $_SESSION['full_name']  = $user['full_name'];
        $_SESSION['role']       = strtolower(trim($user['role']));

        // Redirect logic
        $redirect = $_SESSION['redirect_after_login'] ?? 'staff-profile.php';
        unset($_SESSION['redirect_after_login']);
        $allowed_redirects = [
            'manage-agent-assignments.php',
            'staff-profile.php',
            'confirm-claim-payment.php',
            'agent-assignments.php',
            'manage-claimed-properties.php'
        ];
        if (!in_array($redirect, $allowed_redirects)) {
            $redirect = 'staff-profile.php';
        }

        header("Location: $redirect");
        exit();
    } else {
        $error = "Invalid email or password.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head> 
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Staff Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css?v=<?= time() ?>">
</head>
<body class="d-flex flex-column min-vh-100 bg-light"> 

<?php include 'header.php'; ?>

<main class="flex-grow-1 py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-sm-10 col-md-8 col-lg-6">
                <div class="card shadow-sm border-0">
                    <div class="card-body">
                        <h1 class="h4 text-center mb-4">Staff Login</h1>

                        <!-- Display error message if set -->
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger mb-3"><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>

                        <!-- Staff login form -->
                        <form action="staff-login.php" method="POST" autocomplete="off" novalidate>
                            <div class="mb-3">
                                <label for="email" class="form-label">Email address</label>
                                <input type="email" name="email" class="form-control" id="email" required autofocus>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" name="password" class="form-control" id="password" required>
                            </div>
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="toggle-password-login">
                                <label for="toggle-password-login" class="form-check-label">Show Password</label>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Log In</button>
                        </form>

                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include 'footer.php'; ?>

<!-- Password visibility toggle -->
<script>
    const togglePasswordLogin = document.getElementById('toggle-password-login');
    const passwordFieldLogin = document.getElementById('password');
    togglePasswordLogin.addEventListener('change', function() {
        passwordFieldLogin.type = this.checked ? 'text' : 'password';
    });
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php ob_end_flush(); ?>
