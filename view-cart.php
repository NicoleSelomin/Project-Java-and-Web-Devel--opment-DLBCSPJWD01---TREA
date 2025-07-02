<?php
/**
 * view-cart.php
 * ---------------------------------------------------------
 * Shows client's or guest's cart. View description opens modal (no redirect).
 * ---------------------------------------------------------
 */
session_start();
require 'db_connect.php';

// Load cart property IDs
if (isset($_SESSION['client_id'])) {
    $stmt = $pdo->prepare("SELECT property_id FROM client_cart WHERE client_id = ?");
    $stmt->execute([$_SESSION['client_id']]);
    $cart = $stmt->fetchAll(PDO::FETCH_COLUMN);
} else {
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

<div class="container-fluid flex-grow-1 py-4">
  <main class="container py-5 flex-grow-1">
    <h3 class="mb-4 text-center">Your Cart</h3>

    <!-- Info Box for Badge Explanation -->
    <div class="alert alert-info small d-flex align-items-center mb-4" role="alert" style="max-width:720px; margin:auto;">
        <svg class="bi flex-shrink-0 me-2" width="20" height="20" fill="currentColor" aria-label="info" viewBox="0 0 16 16">
            <path d="M8 15A7 7 0 1 0 8 1a7 7 0 0 0 0 14zm.93-10.412-2.29.287-.082.38.45.083c.294.07.352.176.288.469l-.738 3.468c-.194.897.105 1.319.808 1.319.545 0 .876-.252.988-.598l.088-.416c.06-.282.106-.346.356-.346h.554l.082-.38h-.55c-.257 0-.285-.126-.23-.346l.738-3.468c.194-.897-.105-1.319-.808-1.319-.545 0-.876.252-.988.598l-.088.416zm-2.29 7.036c0 .345.297.532.678.532.376 0 .678-.187.678-.532 0-.346-.302-.532-.678-.532-.381 0-.678.186-.678.532z"/>
        </svg>
        <div class="small text-muted mt-2">
        <strong>Managed:</strong> Full TREA agency support &nbsp; | &nbsp;
        <strong>Brokerage:</strong> Owner-to-client (TREA as connector)
      </div>
    </div> 

    <?php if (empty($cart)): ?>
      <div class="alert alert-info text-center my-5 py-5 rounded-3 shadow-sm">
        <h5 class="mb-3">Your cart is empty.</h5>
        <p class="mb-0">Add properties you like and easily schedule a visit!</p>
      </div>
    <?php else: ?>
      <?php
        $placeholders = implode(',', array_fill(0, count($cart), '?'));
        $stmt = $pdo->prepare("SELECT p.*, s.slug AS service_slug
                               FROM properties p
                               LEFT JOIN services s ON p.service_id = s.service_id
                               WHERE p.property_id IN ($placeholders)");
        $stmt->execute($cart);
        $properties = $stmt->fetchAll(PDO::FETCH_ASSOC);
      ?> 
      <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
        <?php foreach ($properties as $property): ?>
          <div class="col">
            <div class="card h-100 shadow-sm">
              <img src="<?= htmlspecialchars($property['image'] ?? 'uploads/properties/default.jpg') ?>"
                   class="card-img-top property-img"
                   alt="Property image">
              <div class="card-body d-flex flex-column">
                <h5 class="card-title mb-2 d-flex align-items-center justify-content-between truncate-2">
                  <span><?= htmlspecialchars($property['property_name']) ?></span>
                  <?php if (in_array($property['service_slug'], ['rental_property_management', 'sale_property_management'])): ?>
                    <span class="badge bg-success ms-2" data-bs-toggle="tooltip" data-bs-title="TREA is responsible">Managed</span>
                  <?php elseif ($property['service_slug'] === 'brokerage'): ?>
                    <span class="badge bg-secondary ms-2" data-bs-toggle="tooltip" data-bs-title="TREA connects you only">Brokerage</span>
                  <?php endif; ?>
                </h5>
                <p class="text-muted mb-1"><?= htmlspecialchars($property['location']) ?></p>
                <p class="fw-bold text-success mb-2">CFA<?= number_format($property['price']) ?></p>

                <!-- View Description Button (opens modal) -->
                <button type="button"
                        class="btn btn-outline-primary btn-sm mb-2 w-100 view-desc-btn"
                        data-property-id="<?= $property['property_id'] ?>">
                  View Description
                </button>

                <!-- Hidden data for modal -->
                <div class="d-none property-desc-data"
                     id="desc-data-<?= $property['property_id'] ?>"
                     data-image="<?= htmlspecialchars($property['image'] ?? 'uploads/properties/default.jpg') ?>"
                     data-name="<?= htmlspecialchars($property['property_name'], ENT_QUOTES) ?>"
                     data-location="<?= htmlspecialchars($property['location'], ENT_QUOTES) ?>"
                     data-type="<?= htmlspecialchars(ucfirst($property['property_type'])) ?>"
                     data-listing="<?= htmlspecialchars(ucfirst($property['listing_type'])) ?>"
                     data-size="<?= htmlspecialchars($property['size_sq_m']) ?>"
                     data-bed="<?= $property['number_of_bedrooms'] ?>"
                     data-bath="<?= $property['number_of_bathrooms'] ?>"
                     data-floor="<?= $property['floor_count'] ?>"
                     data-price="<?= number_format($property['price']) ?>"
                     data-service="<?= $property['service_slug'] ?>"
                     data-desc="<?= htmlspecialchars($property['property_description'], ENT_QUOTES) ?>">
                </div>

                <!-- Remove from Cart Form -->
                <form action="remove-from-cart.php" method="POST" class="mb-3 remove-cart-form">
                  <input type="hidden" name="property_id" value="<?= $property['property_id'] ?>">
                  <button type="submit" class="btn btn-outline-danger btn-sm w-100">Remove</button>
                </form>

                <!-- Visit Booking Form -->
                <form action="client-book-visit.php" method="POST"
                      class="book-visit-form mt-auto"
                      data-property-id="<?= $property['property_id'] ?>">
                  <input type="hidden" name="property_id" value="<?= $property['property_id'] ?>">
                  <?php
                  // Fetch agent assigned during inspection
                  $stmtAgent = $pdo->prepare("SELECT assigned_agent_id FROM owner_service_requests WHERE property_id = ?");
                  $stmtAgent->execute([$property['property_id']]);
                  $agentId = $stmtAgent->fetchColumn();
                  $slots = [];
                  if ($agentId) {
                    $stmtSlots = $pdo->prepare("SELECT start_time, end_time FROM agent_schedule
                      WHERE agent_id = ? AND status = 'available' AND start_time > NOW()
                      ORDER BY start_time ASC LIMIT 20");
                    $stmtSlots->execute([$agentId]);
                    $slots = $stmtSlots->fetchAll(PDO::FETCH_ASSOC);
                  }
                  ?>
                  <label for="visit_slot_<?= $property['property_id'] ?>" class="form-label">
                    Select Visit Date/Time:
                  </label>
                  <select name="visit_slot" class="form-select mb-2" id="visit_slot_<?= $property['property_id'] ?>" required <?= !$agentId ? 'disabled' : '' ?>>
                    <?php if (!$agentId): ?>
                      <option>No agent assigned yet</option>
                    <?php elseif (empty($slots)): ?>
                      <option>No available slots for this agent</option>
                    <?php else: ?>
                      <?php foreach ($slots as $slot): ?>
                        <option value="<?= htmlspecialchars($slot['start_time']) ?>">
                          <?= date('D, M j Y, H:i', strtotime($slot['start_time'])) ?> -
                          <?= date('H:i', strtotime($slot['end_time'])) ?>
                        </option>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </select>
                  <button type="submit"
                          class="btn custom-btn btn-sm book-btn w-100"
                          <?= !$agentId || empty($slots) ? 'disabled' : '' ?>>
                    Book Visit
                  </button>
                </form>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- Property Description Modal -->
      <div class="modal fade" id="propertyDescModal" tabindex="-1" aria-labelledby="propertyDescLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title" id="propertyDescLabel">Property Details</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
              <div class="row g-4 align-items-center">
                <div class="col-md-5 text-center">
                  <img id="desc-img" src="" alt="Property" class="img-fluid rounded shadow-sm mb-3" style="max-height:220px;object-fit:cover;">
                </div>
                <div class="col-md-7">
                  <h4 id="desc-name"></h4>
                  <p class="mb-1"><strong>Type:</strong> <span id="desc-type"></span> &nbsp;
                     <strong>Listing:</strong> <span id="desc-listing"></span>
                     <span id="desc-badge"></span>
                  </p>
                  <p class="mb-1"><strong>Location:</strong> <span id="desc-location"></span></p>
                  <p class="mb-1"><strong>Size:</strong> <span id="desc-size"></span> m²</p>
                  <p class="mb-1"><strong>Bedrooms:</strong> <span id="desc-bed"></span>
                     &nbsp; <strong>Bathrooms:</strong> <span id="desc-bath"></span>
                     &nbsp; <strong>Floors:</strong> <span id="desc-floor"></span>
                  </p>
                  <p class="fw-bold text-success mb-2"><strong>Price:</strong> CFA<span id="desc-price"></span></p>
                  <div class="mb-2"><strong>Description:</strong><br>
                    <span id="desc-desc"></span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Login/Signup Modal (for guest booking) -->
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

    <div class="text-center">
      <a href="index.php" class="btn mt-4 custom-btn text-white fw-bold">Continue Shopping</a>
    </div>
  </main>
</div>

<?php include 'footer.php'; ?>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Modal: Show property description from hidden div
document.querySelectorAll('.view-desc-btn').forEach(btn => {
  btn.addEventListener('click', function () {
    const pid = this.dataset.propertyId;
    const dataDiv = document.getElementById('desc-data-' + pid);
    if (!dataDiv) return;
    // Fill modal fields
    document.getElementById('desc-img').src = dataDiv.dataset.image || 'uploads/properties/default.jpg';
    document.getElementById('desc-name').textContent = dataDiv.dataset.name;
    document.getElementById('desc-type').textContent = dataDiv.dataset.type;
    document.getElementById('desc-listing').textContent = dataDiv.dataset.listing;
    document.getElementById('desc-location').textContent = dataDiv.dataset.location;
    document.getElementById('desc-size').textContent = dataDiv.dataset.size || '-';
    document.getElementById('desc-bed').textContent = dataDiv.dataset.bed || '-';
    document.getElementById('desc-bath').textContent = dataDiv.dataset.bath || '-';
    document.getElementById('desc-floor').textContent = dataDiv.dataset.floor || '-';
    document.getElementById('desc-price').textContent = dataDiv.dataset.price;

    // Show Managed/Brokerage badge
    let badge = '';
    if (['rental_property_management', 'sale_property_management'].includes(dataDiv.dataset.service))
      badge = '<span class="badge bg-success ms-2">Managed</span>';
    else if (dataDiv.dataset.service === 'brokerage')
      badge = '<span class="badge bg-secondary ms-2">Brokerage</span>';
    document.getElementById('desc-badge').innerHTML = badge;

    document.getElementById('desc-desc').textContent = dataDiv.dataset.desc;

    // Show modal
    new bootstrap.Modal(document.getElementById('propertyDescModal')).show();
  });
});

// -- Remove from cart (AJAX so badge updates instantly) --
document.querySelectorAll('.remove-cart-form').forEach(form => {
  form.addEventListener('submit', function(e) {
    e.preventDefault();
    const propertyId = this.querySelector('[name="property_id"]').value;
    fetch('remove-from-cart.php', {
      method: 'POST',
      body: new URLSearchParams({ property_id: propertyId })
    }).then(() => {
      // Remove card from DOM
      this.closest('.col-md-6, .col-lg-4').remove();
      // Update badge count
      fetch('cart-count.php')
        .then(res => res.json())
        .then(data => {
          let badge = document.querySelector('.cart-count-badge');
          if (!badge) {
              const cartBtn = document.querySelector('a[href="view-cart.php"]');
              badge = document.createElement('span');
              badge.className = "position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger cart-count-badge";
              cartBtn.appendChild(badge);
          }
          badge.textContent = data.cartCount > 0 ? data.cartCount : '';
        });
    });
  });
});

// -- Book Visit: same as before --
document.querySelectorAll('.book-btn').forEach(button => {
  button.addEventListener('click', function () {
    const propertyId = this.dataset.propertyId;
    const form = document.querySelector(`form[data-property-id='${propertyId}']`);
    const datetimeInput = form.querySelector('.visit-date');
    const visitDate = datetimeInput.value;
    if (!visitDate) {
      alert("Please select a visit date and time.");
      datetimeInput.classList.add('is-invalid');
      datetimeInput.focus();
      return;
    }
    <?php if (isset($_SESSION['client_id'])): ?>
      form.submit();
    <?php else: ?>
      const modal = new bootstrap.Modal(document.getElementById('loginSignupModal'));
      modal.show();
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
<script src="navbar-close.js?v=1"></script>
</body>
</html>
