<?php
/**
 * ============================================================================
 * user-login.php
 * ----------------------------------------------------------------------------
 * TREA User Login Page
 * ----------------------------------------------------------------------------
 * Provides the user login interface for TREA clients and property owners.
 * - Accepts email and password
 * - Optionally supports "redirect after login" via GET parameter
 * - Responsive Bootstrap 5.3.6 design
 * - All authentication handled by handle-user-login.php
 * ----------------------------------------------------------------------------
 * Session flash messages ('error', 'success') are shown as Bootstrap alerts.
 * ============================================================================
 */

session_start();

// If a redirect destination is passed, remember it for after login.
if (isset($_GET['redirect'])) {
    $_SESSION['redirect_after_login'] = $_GET['redirect'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">  
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>User Login - TREA</title>
  <!-- Bootstrap 5.3.6 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-4Q6Gf2aSP4eDXB8Miphtr37CMZZQ5oXLH2yaXMJ2w8e2ZtHTl7GptT4jmndRuHDT" crossorigin="anonymous">
  <!-- Custom styles (versioned for cache busting) -->
  <link rel="stylesheet" href="styles.css?v=<?= time() ?>">
</head>
<body class="d-flex flex-column min-vh-100 bg-light">

<!-- Include site-wide header -->
<?php include 'header.php'; ?>

<div class="container-fluid flex-grow-1 py-4">
    <div class="row">

<main class="container py-5 flex-grow-1">
  <div class="mb-4 p-3 border rounded shadow-sm main-title">
    <h2 class="text-center mb-4">Welcome to TREA</h2>
    <p class="lead text-center mb-5">Your trusted partner in property management, legal documentation, construction, and planning services.</p>
  </div>

  <div class="row justify-content-center">
    <div class="col-md-6">
      <!-- Card holds the login form -->
      <div class="card shadow-sm">
        <div class="card-body">

          <h4 class="text-center mb-3">Login</h4>

          <!-- 
            Display error/success messages using Bootstrap alerts. 
            These should be set as $_SESSION variables by the backend (handle-user-login.php).
          -->
          <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
              <?= htmlspecialchars($_SESSION['error']) ?>
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
          <?php endif; ?>

          <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
              <?= htmlspecialchars($_SESSION['success']) ?>
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
          <?php endif; ?>

          <form action="handle-user-login.php" method="POST" autocomplete="off">

            <!-- Email Address Field -->
            <div class="mb-3">
              <label for="email" class="form-label">Email address</label>
              <input 
                type="email" 
                name="email" 
                class="form-control" 
                id="email" 
                required
                autofocus
                value="<?= isset($_SESSION['old_email']) ? htmlspecialchars($_SESSION['old_email']) : '' ?>"
              >
            </div>

            <!-- Password Field with Show/Hide toggle -->
            <div class="mb-3">
              <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
              <input 
                type="password" 
                name="password" 
                id="password" 
                class="form-control" 
                required
              >
            </div>

            <div class="form-check mb-3">
              <input type="checkbox" class="form-check-input" id="toggle-password-login">
              <label class="form-check-label" for="toggle-password-login">Show Password</label>
            </div>

            <!-- Submit Button (Bootstrap custom color via 'custom-btn' in your stylesheet) -->
            <button type="submit" class="btn custom-btn w-100">Log In</button>
          </form>

          <div class="d-flex justify-content-between align-items-center mt-3">
            <a href="reset-password.php" class="small">Forgot Password?</a>
            <div class="mt-3 text-center">  
              <span class="small">Don't have an account? </span>  
              <a href="client-signup.php" class="small">Sign up as Client</a>   
              <a href="owner-signup.php" class="small">Sign up as Owner</a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>
</div>
</div>

<?php include 'footer.php'; ?>

<!-- JavaScript to toggle password visibility -->
<script>
  // Password toggle for login form
  document.addEventListener("DOMContentLoaded", function () {
    const toggle = document.getElementById('toggle-password-login');
    const password = document.getElementById('password');
    if (toggle && password) {
      toggle.addEventListener('change', function() {
        password.type = this.checked ? 'text' : 'password';
      });
    }
  });
</script>

<!-- Script to close main navbar on small screen-->
<script src="navbar-close.js?v=1"></script>

<!-- Bootstrap JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js" integrity="sha384-j1CDi7MgGQ12Z7Qab0qlWQ/Qqz24Gc6BM0thvEMVjHnfYGF0rmFCozFSxQBxwHKO" crossorigin="anonymous"></script>
</body>
</html>
