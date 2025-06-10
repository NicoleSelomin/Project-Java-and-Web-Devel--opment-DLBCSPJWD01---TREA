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
    <title>Confirm Claim Payments</title>
    <!-- Bootstrap for responsive design -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-4Q6Gf2aSP4eDXB8Miphtr37CMZZQ5oXLH2yaXMJ2w8e2ZtHTl7GptT4jmndRuHDT" crossorigin="anonymous">
    <!-- Local stylesheet with cache-busting -->
    <link rel="stylesheet" href="styles.css?v=<?= time() ?>">
</head>
<body class="d-flex flex-column min-vh-100 bg-light">

<?php include 'header.php'; ?>

<!-- Main container ensures content stretches full height between header and footer -->
<div class="container-fluid">
  <div class="row">

    <!-- Main Content Column (centered on desktop) -->
    <main class="col-12 col-md-9">

      <!-- Section title -->
      <div class="mb-4 p-3 border rounded shadow-sm main-title">
        <h2 class="mb-4">Confirm Claim Payments</h2>
      </div>

      <!-- User instruction -->
      <h3 class="fw-bold">Choose the category of claims to manage:</h3>

      <!-- List of claim categories with navigation links -->
      <div class="list-group">
        <!-- Brokerage claims: Sale or Rent processed via brokerage listing -->
        <a href="confirm-brokerage-claim-payments.php" class="list-group-item list-group-item-action">
            Brokerage-Based Claims (Sale or Rent listed via Brokerage Service)
        </a>
        <!-- Sale property management claims: For sale listings managed by agency -->
        <a href="confirm-sale-claim-payments.php" class="list-group-item list-group-item-action">
            Sale Property Management Claims
        </a>
        <!-- Rental property management claims: For rental listings managed by agency -->
        <a href="confirm-rental-claim-payments.php" class="list-group-item list-group-item-action">
            Rental Property Management Claims
        </a>
        <!-- Recurring rent invoices for managed rentals -->
        <a href="confirm-rental-management-invoices.php" class="dashboard-card">
            Manage Client Recurring Rent Payments
        </a>
      </div>

      <!-- Back to staff dashboard navigation -->
      <p class="mt-4">
        <a href="staff-profile.php">&larr; Back to Dashboard</a>
      </p>

    </main>
  </div>
</div>

<?php include 'footer.php'; ?>
<!-- Bootstrap JS (for responsive UI and collapse) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js" integrity="sha384-j1CDi7MgGQ12Z7Qab0qlWQ/Qqz24Gc6BM0thvEMVjHnfYGF0rmFCozFSxQBxwHKO" crossorigin="anonymous"></script>
</body>
</html>
