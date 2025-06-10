<?php
/**
 * ============================================================================
 * user-signup.php
 * ----------------------------------------------------------------------------
 * TREA User Signup Page
 * ----------------------------------------------------------------------------
 * Allows new users to register as either a "Client" or "Property Owner".
 * - Responsive, Bootstrap 5.3.6-based layout for all screens
 * - Presents two signup roles; only reveals the form after selection
 * - Accepts full name, email, phone, profile picture, and password
 * - Preserves redirect destination after signup if provided
 * - Displays Bootstrap alerts for backend error/success (via $_SESSION)
 * ----------------------------------------------------------------------------
 */

session_start();

// Handle "redirect after signup" logic (e.g., user attempted protected action)
if (isset($_GET['redirect'])) {
    $_SESSION['redirect_after_login'] = $_GET['redirect'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>User Signup - TREA</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Bootstrap 5.3.6 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-4Q6Gf2aSP4eDXB8Miphtr37CMZZQ5oXLH2yaXMJ2w8e2ZtHTl7GptT4jmndRuHDT" crossorigin="anonymous">
  <!-- Project custom styles -->
  <link rel="stylesheet" href="styles.css?v=<?= time() ?>">
</head>
<body class="d-flex flex-column min-vh-100 bg-light text-dark">
<?php include 'header.php'; ?> 

<main class="container py-5 flex-grow-1">
  <div class="row justify-content-center">
    <div class="col-12 col-sm-10 col-md-8 col-lg-6 p-4 border rounded shadow-sm">

      <!-- Title & intro -->
      <div class="mb-4 p-3 border rounded shadow-sm main-title">
        <h2 class="mb-2">Create Your TREA Account</h2>
        <p>Choose your account type to get started.</p>
      </div>

      <!-- Bootstrap alerts for error/success messages from backend -->
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

      <!-- Choose user role (Client or Property Owner) -->
      <div class="mb-4">
        <div class="row g-2">
          <div class="col-12 col-md-6">
            <button id="btn-client" onclick="setRole('client')" type="button" class="btn btn-outline-primary w-100">
              Sign up as Client
            </button>
          </div>
          <div class="col-12 col-md-6">
            <button id="btn-owner" onclick="setRole('property_owner')" type="button" class="btn btn-outline-primary w-100">
              Sign up as Property Owner
            </button>
          </div>
        </div>
      </div>

      <!-- Signup Form (hidden until role selected) -->
      <div class="row justify-content-center">
        <div class="col-md-12">
          <form id="signupForm" action="submit-user-signup.php" method="POST" style="display: none;" enctype="multipart/form-data" autocomplete="off">
            <input type="hidden" name="user_type" id="userType">

            <!-- Full Name -->
            <div class="mb-3">
              <label class="form-label">Full Name <span class="text-danger">*</span></label>
              <input type="text" name="full_name" class="form-control" required>
            </div>

            <!-- Email Address -->
            <div class="mb-3">
              <label class="form-label">Email Address <span class="text-danger">*</span></label>
              <input type="email" name="email" class="form-control" required>
            </div>

            <!-- Phone Number -->
            <div class="mb-3">
              <label class="form-label">Phone Number <span class="text-danger">*</span></label>
              <input type="tel" name="phone_number" class="form-control" required>
            </div>

            <!-- Profile Picture -->
            <div class="mb-3">
              <label class="form-label">Profile Picture</label>
              <input type="file" name="profile_picture" class="form-control">
            </div>

            <!-- Password -->
            <div class="mb-3">
              <label class="form-label">Create Password <span class="text-danger">*</span></label>
              <input type="password" name="password" id="signup-password" class="form-control" required>
            </div>

            <!-- Toggle to show/hide password -->
            <div class="form-check mb-3">
              <input type="checkbox" id="toggle-password-signup" class="form-check-input">
              <label class="form-check-label" for="toggle-password-signup">Show Password</label>
            </div>

            <!-- Submit button -->
            <button type="submit" name="submit" class="btn custom-btn w-100">Sign Up</button>
          </form>
        </div>
      </div>

      <div class="mt-4 text-center">
        <span>Already have an account? <a href="user-login.php">Login here</a></span>
      </div>

    </div>
  </div>
</main>

<?php include 'footer.php'; ?>

<!-- Password show/hide JS -->
<script>
  document.addEventListener("DOMContentLoaded", function () {
    const togglePasswordSignup = document.getElementById('toggle-password-signup');
    const passwordFieldSignup = document.getElementById('signup-password');
    if (togglePasswordSignup && passwordFieldSignup) {
      togglePasswordSignup.addEventListener('change', function() {
        passwordFieldSignup.type = this.checked ? 'text' : 'password';
      });
    }
  });
</script>

<!-- User type selection, button style toggle, and form show/hide -->
<script>
  document.addEventListener("DOMContentLoaded", function () {
    window.setRole = function(role) {
      // Set hidden input value and show the signup form
      document.getElementById('userType').value = role;
      document.getElementById('signupForm').style.display = 'block';

      // Button color toggling for clarity
      const clientBtn = document.getElementById('btn-client');
      const ownerBtn = document.getElementById('btn-owner');
      if (role === 'client') {
        clientBtn.classList.replace('btn-outline-primary', 'btn-primary');
        ownerBtn.classList.replace('btn-primary', 'btn-outline-primary');
      } else {
        ownerBtn.classList.replace('btn-outline-primary', 'btn-primary');
        clientBtn.classList.replace('btn-primary', 'btn-outline-primary');
      }
    };

    // Prevent submission if no role selected
    document.getElementById('signupForm').addEventListener('submit', function(e) {
      const role = document.getElementById('userType').value;
      if (!role || (role !== 'client' && role !== 'property_owner')) {
        e.preventDefault();
        alert("Please select an account type before submitting.");
      }
    });
  });
</script>

<!-- Bootstrap 5.3.6 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js" integrity="sha384-j1CDi7MgGQ12Z7Qab0qlWQ/Qqz24Gc6BM0thvEMVjHnfYGF0rmFCozFSxQBxwHKO" crossorigin="anonymous"></script>
</body>
</html>
