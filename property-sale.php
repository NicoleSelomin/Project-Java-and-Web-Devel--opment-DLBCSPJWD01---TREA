<?php
/*
|--------------------------------------------------------------------------
| property-sale.php
|--------------------------------------------------------------------------
| Displays all available properties for sale with filter and pagination
| - Loads and applies dynamic filters (search, type, price, etc.) via process-filters.php
| - Shows only properties listed for sale and currently available
| - Responsive card grid layout with Bootstrap 5.3.6
|--------------------------------------------------------------------------
*/

require 'db_connect.php';
require 'process-filters.php';

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 9;
$offset = ($page - 1) * $perPage;

// Count total available sale listings
$countStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM properties p
    LEFT JOIN services s ON p.service_id = s.service_id
    WHERE p.listing_type = 'sale' AND p.availability = 'available'
    $whereSql
");
$countStmt->execute($filterParams);
$totalResults = $countStmt->fetchColumn();
$totalPages = ceil($totalResults / $perPage);

// Fetch paginated property results
$dataStmt = $pdo->prepare("
    SELECT p.*, s.slug AS service_slug, osr.urgent
    FROM properties p
    LEFT JOIN services s ON p.service_id = s.service_id
    LEFT JOIN owner_service_requests osr ON p.request_id = osr.request_id
    WHERE p.listing_type = 'sale' AND p.availability = 'available'
    $whereSql
    ORDER BY osr.urgent DESC, p.created_at DESC
    LIMIT $perPage OFFSET $offset
");
$dataStmt->execute($filterParams);
$properties = $dataStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Page meta & CSS -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Properties for Sale</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <link rel="stylesheet" href="styles.css?v=<?= time() ?>">
</head>
<body class="d-flex flex-column min-vh-100 bg-light text-dark">

<?php include 'header.php'; include 'filter-bar.php'; ?>

<div class="container-fluid flex-grow-1">
    <div class="row">
        <main class="container py-5 flex-grow-1">

    <!-- Main Title/Heading -->
    <div class="mb-4 p-3 border rounded shadow-sm text-center main-title bg-white">
        <h2 class="mb-2">Available Properties for Sale</h2>
        <p class="text-muted mb-0 fs-6">Browse managed and brokerage sale listings.</p>
    </div>

    <!-- Info Box for Badge Explanation -->
    <div class="alert alert-info small d-flex align-items-center mb-4" style="max-width: 900px; margin:auto;">
        <svg class="bi flex-shrink-0 me-2" width="20" height="20" fill="currentColor" aria-label="info" viewBox="0 0 16 16">
            <path d="M8 15A7 7 0 1 0 8 1a7 7 0 0 0 0 14zm.93-10.412-2.29.287-.082.38.45.083c.294.07.352.176.288.469l-.738 3.468c-.194.897.105 1.319.808 1.319.545 0 .876-.252.988-.598l.088-.416c.06-.282.106-.346.356-.346h.554l.082-.38h-.55c-.257 0-.285-.126-.23-.346l.738-3.468c.194-.897-.105-1.319-.808-1.319-.545 0-.876.252-.988.598l-.088.416zm-2.29 7.036c0 .345.297.532.678.532.376 0 .678-.187.678-.532 0-.346-.302-.532-.678-.532-.381 0-.678.186-.678.532z"/>
        </svg>
        <div>
            <strong>Badge Guide:</strong>
            <span class="badge bg-success">Managed</span> means TREA handles everything from visit to contract to support.
            <span class="badge bg-secondary">Brokerage</span> means TREA simply connects you with the owner—rest is between you and the owner.
        </div>
    </div>    

            <!-- Properties Grid -->
            <div class="row row-cols-1 row-cols-md-3 g-4">
                <?php if (empty($properties)): ?>
                    <div class="col">
                        <div class="alert alert-warning text-center">No properties for sale available.</div>
                    </div>
                <?php else: foreach ($properties as $property): ?>
                    <div class="col">
                        <div class="card h-100 shadow-sm">
                            <a href="view-property.php?property_id=<?= $property['property_id'] ?>">
                                <img src="<?= htmlspecialchars($property['image'] ?? 'uploads/properties/default.jpg') ?>"
                                     class="card-img-top property-img" alt="Image of <?= htmlspecialchars($property['property_name']) ?>">
                            </a>
                            <div class="card-body">
                                <a href="view-property.php?property_id=<?= $property['property_id'] ?>" class="text-decoration-none text-dark">
                                    <h5 class="card-title mb-2 d-flex align-items-center justify-content-between">
                                        <span><?= htmlspecialchars($property['property_name']) ?></span>
                                        <?php if ($property['service_slug'] === 'sale_property_management'): ?>
                                            <span class="badge bg-success ms-1" data-bs-toggle="tooltip" data-bs-title="Managed by TREA">Managed</span>
                                        <?php elseif ($property['service_slug'] === 'brokerage'): ?>
                                            <span class="badge bg-secondary ms-1" data-bs-toggle="tooltip" data-bs-title="Brokerage (owner direct)">Brokerage</span>
                                        <?php endif; ?>
                                    </h5>
                                </a>
                                <p class="card-text truncate-2"><?= htmlspecialchars($property['property_description']) ?></p>
                                <p class="text-muted mb-1">
                                    <?= htmlspecialchars($property['location']) ?> | <span class="fw-bold text-success">CFA<?= number_format($property['price']) ?></span>
                                </p>
                                <form class="add-to-cart-form" data-property="<?= $property['property_id'] ?>">    
                                    <button type="button" class="btn btn-sm custom-btn add-to-cart-btn" data-id="<?= $property['property_id'] ?>">Add to Cart</button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; endif; ?>
            </div>

            <!-- Pagination controls -->
            <?php if ($totalPages > 1): ?>
                <nav class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </main>
    </div>
</div>

<!-- Toast for Add to Cart -->
<div id="cart-toast" class="toast position-fixed bottom-0 end-0 m-3" style="z-index:9999;" data-bs-delay="1800">
  <div class="toast-body text-success fw-bold">
    Property added to cart!
  </div>
</div>

<?php include 'footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
<script>
    // Bootstrap tooltips for badges
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.forEach(function (tooltipTriggerEl) {
      new bootstrap.Tooltip(tooltipTriggerEl)
    });

    // Add to Cart button logic (no page reload)
    document.querySelectorAll('.add-to-cart-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const propertyId = this.dataset.id;
            const originalText = btn.textContent;
            btn.disabled = true;
            fetch('add-to-cart.php?id=' + propertyId)
                .then(response => response.text())
                .then(() => {
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

                          // Show toast
                          const toastEl = document.getElementById('cart-toast');
                          if (toastEl) {
                            const toast = new bootstrap.Toast(toastEl);
                            toast.show();
                          }
                          btn.textContent = "Added!";
                          btn.classList.remove("custom-btn");
                          btn.classList.add("btn-success");
                          setTimeout(() => {
                              btn.textContent = originalText;
                              btn.classList.remove("btn-success");
                              btn.classList.add("custom-btn");
                              btn.disabled = false;
                          }, 1300);
                      });
                });
        });
    });
</script>
<script src="navbar-close.js?v=1"></script>
</body>
</html>
