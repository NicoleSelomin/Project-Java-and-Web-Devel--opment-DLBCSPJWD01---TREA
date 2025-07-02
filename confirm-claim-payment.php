<?php
/**
 * -----------------------------------------------------------------------------
 * confirm-claim-payment.php 
 * -----------------------------------------------------------------------------
 * 
 * Confirm Claim Payments - Category Selection Page
 *
 * Allows staff (General Manager, Accountant) to access different categories
 * of claim payment confirmation workflows.
 * Features:
 * - Access control (only GM and Accountant)
 * - Redirects unauthorized staff to login
 * - Presents a menu of claim payment categories for further management
 *
 * Dependencies:
 * - db_connect.php: PDO connection required for future expansion, included for consistency
 * - Bootstrap 5.3: Responsive layout and styling
 * - Session and user role validation
 */

session_start();
require 'db_connect.php';

// -----------------------------------------------------------------------------
// 1. ACCESS CONTROL: Only General Manager or Accountant allowed
// -----------------------------------------------------------------------------

// Check if staff is logged in and has the appropriate role
if (
    !isset($_SESSION['staff_id']) || 
    !in_array(strtolower($_SESSION['role']), ['general manager', 'accountant'])
) {
    // Save intended destination for post-login redirect
    $_SESSION['redirect_after_login'] = 'confirm-claim-payments.php';
    header("Location: staff-login.php");
    exit();
}

?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Confirm Reservation Payments</title>
    <!-- Bootstrap for responsive design -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-4Q6Gf2aSP4eDXB8Miphtr37CMZZQ5oXLH2yaXMJ2w8e2ZtHTl7GptT4jmndRuHDT" crossorigin="anonymous">
    <!-- Local stylesheet with cache-busting -->
    <link rel="stylesheet" href="styles.css?v=<?= time() ?>">
</head>
<body class="d-flex flex-column min-vh-100 bg-light">

<?php include 'header.php'; ?>

<!-- Main container ensures content stretches full height between header and footer -->
<div class="container py-5 flex-grow-1">
  <div class="row justify-content-center">
    <!-- Main content -->
    <main class="col-12 col-md-10 col-lg-8">
      <!-- Section title -->
      <div class="mb-4 p-3 border rounded shadow-sm main-title text-center bg-white">
        <h2 class="mb-2">Confirm Reservation Payments</h2>
        <h5 class="fw-light text-secondary mb-0">Choose the category of reservation to manage:</h5>
      </div>

      <!-- List of claim categories as cards -->
       <section>
      <div class="row g-4">
        <div class="col-12 col-md-6">
          <a href="confirm-brokerage-claim-payments.php" class="card h-100 shadow-sm text-decoration-none text-dark">
            <div class="card-body">
              <h5 class="card-title mb-2">Reserved Brokerage-Based Properties</h5>
              <div class="card-text small">
                Sale or Rent processed via brokerage listing
              </div>
            </div>
          </a>
        </div>
        <div class="col-12 col-md-6">
          <a href="confirm-rental-claim-payments.php" class="card h-100 shadow-sm text-decoration-none text-dark">
            <div class="card-body">
              <h5 class="card-title mb-2">Rental Property Management Reservations</h5>
              <div class="card-text small">
                For rental listings managed by agency
              </div>
            </div>
          </a>
        </div>
        <div class="col-12 col-md-6">
          <a href="confirm-rental-management-invoices.php" class="card h-100 shadow-sm text-decoration-none text-dark">
            <div class="card-body">
              <h5 class="card-title mb-2">Recurring Rent Payments</h5>
              <div class="card-text small">
                Manage client recurring rent payments for managed rentals
              </div>
            </div>
          </a>
        </div>
      </div>
      </section>

      <!-- Back to staff dashboard navigation -->
      <p class="mt-5 text-center">
        <a href="staff-profile.php" class="btn btn-dark fw-bold mt-4">🡰 Back to dashboard</a>
      </p>
    </main>
  </div>
</div>


<?php include 'footer.php'; ?>
<!-- Bootstrap JS (for responsive UI and collapse) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js" integrity="sha384-j1CDi7MgGQ12Z7Qab0qlWQ/Qqz24Gc6BM0thvEMVjHnfYGF0rmFCozFSxQBxwHKO" crossorigin="anonymous"></script>


<!-- Script to close main navbar on small screen-->
<script src="navbar-close.js?v=1"></script>

</body>
</html>
