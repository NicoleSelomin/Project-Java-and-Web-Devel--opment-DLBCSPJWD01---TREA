<?php
/**
 *==========================================================================
 * header.php
 *---------------------------------------------------------------------------
 *
 * Site-wide navigation header.
 * - Displays agency branding, navigation links, animated tagline, and cart icon with item count.
 * - Supports both logged-in client carts (DB) and guest carts (session).
 * - Responsive: works on all Bootstrap 5.3.6 layouts.
 * --------------------------------------------------------------------------
 */

if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'db_connect.php';

$cartCount = 0;
// -----------------------------------------------------------------------------
// Cart count: for logged-in client, fetch from DB; for guest, count session cart
// -----------------------------------------------------------------------------
if (isset($_SESSION['client_id'])) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM client_cart WHERE client_id = ?");
    $stmt->execute([$_SESSION['client_id']]);
    $cartCount = (int)$stmt->fetchColumn();
} elseif (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    $cartCount = count($_SESSION['cart']);
}
?>

<header class="fixed-top border-bottom shadow-sm">
  <nav class="navbar navbar-expand-lg custom-navbar px-3" style="background-color: #0056b3;">
    <div class="container-fluid">

      <!-- LEFT: Agency Logo & Name -->
      <a class="navbar-brand d-flex align-items-center" href="index.php">
        <img src="images/logo.png" alt="TREA Logo" class="me-2" style="height: 40px;">
        <span class="d-none d-md-inline text-white fw-bold">
          Trusted Real Estate Agency (TREA)
        </span>
      </a>

      <!-- Mobile Nav Toggler -->
      <button class="navbar-toggler text-white bg-light" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar">
        <span class="navbar-toggler-icon"></span>
      </button>

      <!-- CENTER: Tagline (hidden on sm) -->
      <div class="text-center mx-auto d-none d-lg-block">
        <span class="animate-text">Trusted • Reliable • Elegant • Authentic</span>
      </div>

      <!-- RIGHT: Navigation links and Cart icon -->
      <div class="collapse navbar-collapse justify-content-between align-items-center" id="mainNavbar">
        <ul class="navbar-nav ms-auto d-flex flex-column flex-lg-row align-items-start align-items-lg-center gap-lg-3">

          <li class="nav-item">
            <a class="nav-link text-white" href="index.php">Home</a>
          </li>
          <li class="nav-item">
            <a class="nav-link text-white" href="about-us.php">About Us</a>
          </li>
          <li class="nav-item">
            <a class="nav-link text-white" href="news.php">News</a>
          </li>
          <li class="nav-item">
            <a class="nav-link text-white" href="services.php">List your Property</a>
          </li>
          <li class="nav-item">
            <a class="nav-link text-white" href="user-login.php">User Account</a>
          </li>

          <!-- Cart Button with Badge (shows for all users) -->
          <?php if (!isset($_SESSION['staff_id']) && !isset($_SESSION['owner_id'])): ?>
          <li class="nav-item">
            <a href="view-cart.php" class="btn position-relative" style="font-size: 1.2rem;">
              🧺
              <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger cart-count-badge">
                <?= $cartCount > 0 ? $cartCount : '' ?>
              </span>
            </a>
          </li>
          <?php endif; ?>
        </ul>
      </div>
    </div>
  </nav>
</header>
