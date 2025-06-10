<?php
/**
 * view-cart.php
 * ---------------------------------------------------------
 * Displays the logged-in (or guest) user's property cart.
 * - Logged-in clients: cart loaded from database.
 * - Guests: cart loaded from session.
 * - Properties are displayed with images, details, and options
 *   to remove from cart or book a visit.
 * - Visit booking is only allowed if logged in; guests prompted
 *   to log in or sign up via a modal.
 *
 * UI is styled with Bootstrap 5.3.6.
 * ---------------------------------------------------------
 */

session_start();
require 'db_connect.php';

// ----------------- Cart Data Fetch -----------------

$cart = [];

// Check if client is logged in and load cart accordingly
if (isset($_SESSION['client_id'])) {
    // Logged-in client: get property IDs from DB
    $stmt = $pdo->prepare("SELECT property_id FROM client_cart WHERE client_id = ?");
    $stmt->execute([$_SESSION['client_id']]);
    $cart = $stmt->fetchAll(PDO::FETCH_COLUMN);
} else {
    // Guest: use session-based cart
    $cart = $_SESSION['cart'] ?? [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Client Cart - TREA</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="styles.css?v=<?= time() ?>">
</head>
<body class="d-flex flex-column min-vh-100 bg-light">
<?php include 'header.php'; ?>

<main class="container py-5 flex-grow-1">
  <h3 class="mb-4">Your Cart</h3>

  <?php if (empty($cart)): ?>
    <!-- Empty Cart Message -->
    <p class="text-muted">Your cart is empty.</p>
  <?php else: ?>
    <?php
      // Fetch property details for all properties in the cart
      $placeholders = implode(',', array_fill(0, count($cart), '?'));
      $stmt = $pdo->prepare("SELECT * FROM properties WHERE property_id IN ($placeholders)");
      $stmt->execute($cart);
      $properties = $stmt->fetchAll(PDO::FETCH_ASSOC);
    ?>
    <div class="row g-4">
      <?php foreach ($properties as $property): ?>
        <div class="col-md-6 col-lg-4">
          <div class="card h-100 shadow-sm">
            <img src="<?= htmlspecialchars($property['image']) ?>"
                 class="card-img-top"
                 style="height: 180px; object-fit: cover;"
                 alt="Property image">
            <div class="card-body d-flex flex-column">
              <h5 class="card-title"><?= htmlspecialchars($property['property_name']) ?></h5>
              <p class="text-muted mb-1"><?= htmlspecialchars($property['location']) ?></p>
              <p class="fw-bold text-success mb-2">CFA<?= number_format($property['price']) ?></p>

              <!-- Remove from Cart Form -->
              <form action="remove-from-cart.php" method="POST" class="mb-3">
                <input type="hidden" name="property_id" value="<?= $property['property_id'] ?>">
                <button type="submit" class="btn btn-outline-danger btn-sm">Remove</button>
              </form>

              <!-- Visit Booking Form -->
              <form action="client-book-visit.php" method="POST"
                    class="book-visit-form mt-auto"
                    data-property-id="<?= $property['property_id'] ?>">
                <input type="hidden" name="property_id" value="<?= $property['property_id'] ?>">
                <label for="visit_date_<?= $property['property_id'] ?>" class="form-label">
                  Select Visit Date/Time:
                </label>
                <input type="datetime-local"
                       name="visit_datetime"
                       class="form-control mb-2 visit-date"
                       id="visit_date_<?= $property['property_id'] ?>"
                       required
                       min="<?= date('Y-m-d\TH:i') ?>">
                <button type="button"
                        class="btn custom-btn btn-sm book-btn w-100"
                        data-property-id="<?= $property['property_id'] ?>">
                  Book Visit
                </button>
              </form>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Login/Signup Modal (appears for guests on booking attempt) -->
    <div class="modal fade" id="loginSignupModal" tabindex="-1" aria-labelledby="loginSignupModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="loginSignupModalLabel">Login or Sign Up to Book</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body text-center">
            <p class="mb-3">To complete your booking, please log in or sign up.</p>
            <button class="btn btn-outline-primary me-2 login-redirect-btn" data-action="login">Login</button>
            <button class="btn btn-outline-success signup-redirect-btn" data-action="signup">Sign Up</button>
          </div>
        </div>
      </div>
    </div>
  <?php endif; ?>
</main>

<?php include 'footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
<script>
/**
 * Handles booking button clicks for each property in the cart.
 * - If not logged in, opens modal and sets login/signup redirect URLs.
 * - If logged in, submits the form.
 */
document.querySelectorAll('.book-btn').forEach(button => {
  button.addEventListener('click', function () {
    const propertyId = this.dataset.propertyId;
    const form = document.querySelector(`form[data-property-id='${propertyId}']`);
    const datetimeInput = form.querySelector('.visit-date');
    const visitDate = datetimeInput.value;

    // Validate date/time selection
    if (!visitDate) {
      alert("Please select a visit date and time.");
      datetimeInput.classList.add('is-invalid');
      datetimeInput.focus();
      return;
    }

    <?php if (isset($_SESSION['client_id'])): ?>
      // If logged in, submit the form directly
      form.submit();
    <?php else: ?>
      // If not logged in, show login/signup modal
      const modal = new bootstrap.Modal(document.getElementById('loginSignupModal'));
      modal.show();

      // Set up redirect on modal buttons
      document.querySelectorAll('[data-action]').forEach(btn => {
        btn.onclick = function () {
          const action = this.dataset.action;
          const target = action === 'login' ? 'user-login.php' : 'user-signup.php';
          const redirectUrl = `client-book-visit.php?property_id=${propertyId}&visit_datetime=${encodeURIComponent(visitDate)}`;
          window.location.href = `${target}?redirect=${encodeURIComponent(redirectUrl)}`;
        };
      });
    <?php endif; ?>
  });
});
</script>
</body>
</html>
