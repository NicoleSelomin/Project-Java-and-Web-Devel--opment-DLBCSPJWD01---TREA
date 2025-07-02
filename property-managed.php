<?php
/*
|--------------------------------------------------------------------------
| property-managed.php
|--------------------------------------------------------------------------
| Lists all available properties under property management service with dynamic filter and pagination.
| - Loads and applies filters (search, type, price, etc.) via process-filters.php.
| - Responsive Bootstrap 5.3.6 cards, grid, and pagination.
|--------------------------------------------------------------------------
*/

require 'db_connect.php';
require 'process-filters.php';

// Pagination setup
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 9;
$offset = ($page - 1) * $perPage;

// Total result count for pagination
$countStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM properties p
    LEFT JOIN services s ON p.service_id = s.service_id
    WHERE s.slug = 'rental_property_management' AND p.availability = 'available' 
    $whereSql
");
$countStmt->execute($filterParams);
$totalResults = $countStmt->fetchColumn();
$totalPages = ceil($totalResults / $perPage);

// Fetch paginated property data
$dataStmt = $pdo->prepare("
    SELECT p.*, s.slug AS service_slug, osr.urgent
    FROM properties p
    LEFT JOIN services s ON p.service_id = s.service_id
    LEFT JOIN owner_service_requests osr ON p.request_id = osr.request_id
    WHERE s.slug = 'rental_property_management' AND p.availability = 'available'
    $whereSql
    ORDER BY 
    osr.urgent DESC,
    p.created_at DESC
    LIMIT $perPage OFFSET $offset
");
$dataStmt->execute($filterParams);
$properties = $dataStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Meta, Title, and Bootstrap CSS -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Managed Properties | TREA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css?v=<?= time() ?>">
</head>
<body class="d-flex flex-column min-vh-100 bg-light">

<?php include 'header.php'; ?>
<?php include 'filter-bar.php'; ?>

<main class="container py-5 flex-grow-1">

    <!-- Main Title/Heading -->
    <div class="mb-4 p-3 border rounded shadow-sm text-center main-title bg-white">
        <h2 class="mb-2">Available Rental Properties Managed by TREA</h2>
        <p class="text-muted mb-0 fs-6">TREA is responsible for all processes and support with these rentals.</p>
    </div>

    <!-- Info Box for Badge Explanation -->
    <div class="alert alert-success small d-flex align-items-center mb-4" role="alert" style="max-width:720px; margin:auto;">
        <svg class="bi flex-shrink-0 me-2" width="20" height="20" fill="currentColor" aria-label="info" viewBox="0 0 16 16">
            <path d="M8 15A7 7 0 1 0 8 1a7 7 0 0 0 0 14zm.93-10.412-2.29.287-.082.38.45.083c.294.07.352.176.288.469l-.738 3.468c-.194.897.105 1.319.808 1.319.545 0 .876-.252.988-.598l.088-.416c.06-.282.106-.346.356-.346h.554l.082-.38h-.55c-.257 0-.285-.126-.23-.346l.738-3.468c.194-.897-.105-1.319-.808-1.319-.545 0-.876.252-.988.598l-.088.416zm-2.29 7.036c0 .345.297.532.678.532.376 0 .678-.187.678-.532 0-.346-.302-.532-.678-.532-.381 0-.678.186-.678.532z"/>
        </svg>
        <div>
            <strong>What does <span class="badge bg-success">Managed</span> mean?</strong>
            For managed properties, TREA handles every step—viewings, contract signing, payment, handover, and ongoing support—for your peace of mind.
        </div>
    </div>    

    <!-- Property Cards Grid -->
    <?php if (empty($properties)): ?>
        <div class="alert alert-warning text-center">No managed rental properties found. Try adjusting your filters or check back soon!</div>
    <?php else: ?>
    <div class="row row-cols-1 row-cols-md-3 g-4">
        <?php foreach ($properties as $property): ?>
            <div class="col">
                <div class="card h-100 shadow-sm">
                    <!-- Property Image -->
                    <a href="view-property.php?property_id=<?= $property['property_id'] ?>">
                        <img src="<?= htmlspecialchars(!empty($property['image']) ? $property['image'] : 'uploads/properties/default.jpg') ?>"
                             class="card-img-top property-img" alt="Photo of <?= htmlspecialchars($property['property_name']) ?>">
                    </a>
                    <div class="card-body d-flex flex-column">
                        <a href="view-property.php?property_id=<?= $property['property_id'] ?>" class="text-decoration-none text-dark">
                            <h5 class="card-title mb-1">
                                <?= htmlspecialchars($property['property_name']) ?>
                                <span class="badge bg-success ms-1" data-bs-toggle="tooltip" title="Managed: TREA handles the whole process.">Managed</span>
                            </h5>
                        </a>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted small"><?= htmlspecialchars($property['location']) ?></span>
                            <?php if (!empty($property['property_type'])): ?>
                                <span class="badge bg-light text-dark border"><?= htmlspecialchars($property['property_type']) ?></span>
                            <?php endif; ?>
                        </div>
                        <p class="fw-bold text-success mb-2">CFA<?= number_format($property['price']) ?></p>
                        <p class="card-text truncate-2 mb-2"><?= htmlspecialchars($property['property_description']) ?></p>
                        <div class="mt-auto">
                            <form class="add-to-cart-form" data-property="<?= $property['property_id'] ?>">    
                                <button type="button" class="btn btn-sm custom-btn add-to-cart-btn w-100" data-id="<?= $property['property_id'] ?>">Add to Cart</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Pagination Controls -->
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

<!-- toast popup for added items to cart -->
<div id="cart-toast" class="toast position-fixed bottom-0 end-0 m-3" style="z-index:9999;" data-bs-delay="1800">
  <div class="toast-body text-success fw-bold">
    Property added to cart!
  </div>
</div>

<?php include 'footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
<!-- Enable Bootstrap tooltips -->
<script>
document.querySelectorAll('.add-to-cart-btn').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        const propertyId = this.dataset.id;
        const originalText = btn.textContent;
        btn.disabled = true;

        fetch('add-to-cart.php?id=' + propertyId)
            .then(response => response.text())
            .then(() => {
                // Update cart count badge
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

                      // Show Bootstrap toast for feedback
                      const toastEl = document.getElementById('cart-toast');
                      if (toastEl) {
                        const toast = new bootstrap.Toast(toastEl);
                        toast.show();
                      }

                      // Button feedback
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
// Enable tooltips
var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
tooltipTriggerList.map(function (el) {
    return new bootstrap.Tooltip(el);
});
</script>

<script src="navbar-close.js?v=1"></script>
</body>
</html>
