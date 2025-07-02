<?php
/*
|--------------------------------------------------------------------------
| index.php 
|--------------------------------------------------------------------------
| Home page for TREA platform.
| - Loads and filters available property listings (rent and sale)
| - Shows service summary cards
| - Responsive and mobile-first using Bootstrap 5
| - Sections for "Our Services", "Properties for Rent", and "Properties for Sale"
|-------------------------------------------------------------------------------
*/

require 'db_connect.php';
$skipPropertyFetch = true;
require 'process-filters.php';

// Fetch all available properties with optional filters, joining services for badges
$stmt = $pdo->prepare("SELECT p.*, s.slug AS service_slug, osr.urgent
FROM properties p 
LEFT JOIN services s ON p.service_id = s.service_id
LEFT JOIN owner_service_requests osr ON p.request_id = osr.request_id
WHERE p.availability = 'available'
$whereSql
ORDER BY 
 osr.urgent DESC,    -- urgent ones first!
 p.created_at DESC");

$stmt->execute($filterParams);
$allProperties = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Determine current page number from GET (default = 1)
$page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 
    ? (int)$_GET['page'] 
    : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Only run filtered search if there are filters set
$isFiltered = (
    !empty($_GET['search']) ||
    !empty($_GET['property_type']) ||
    !empty($_GET['listing_type']) ||
    !empty($_GET['min_price']) ||
    !empty($_GET['max_price'])
);

if ($isFiltered) {
    // Get total count of matching properties
    $countSql = "SELECT COUNT(*) FROM properties p WHERE p.availability = 'available' $whereSql";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($filterParams);
    $filteredCount = $countStmt->fetchColumn();

    // Get properties for this page
    $filterSql = "SELECT p.*, s.slug AS service_slug, osr.urgent
        FROM properties p 
        LEFT JOIN services s ON p.service_id = s.service_id
        LEFT JOIN owner_service_requests osr ON p.request_id = osr.request_id
        WHERE p.availability = 'available' $whereSql
        ORDER BY osr.urgent DESC, p.created_at DESC
        LIMIT $perPage OFFSET $offset";
    $filterStmt = $pdo->prepare($filterSql);
    $filterStmt->execute($filterParams);
    $filteredProperties = $filterStmt->fetchAll(PDO::FETCH_ASSOC);

    $totalPages = ceil($filteredCount / $perPage);
}


// Split property list into rentals and sales, managed and brokerage
$rentals = array_filter($allProperties, fn($p) => $p['listing_type'] === 'rent');
$sales = array_filter($allProperties, fn($p) => $p['listing_type'] === 'sale');
$brokerage = array_filter($allProperties, fn($p) => $p['service_slug'] === 'brokerage');
$managed = array_filter($allProperties, fn($p) => $p['service_slug'] === 'rental_property_management');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Meta and Bootstrap 5 setup for responsive layout -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>TREA - Home</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-4Q6Gf2aSP4eDXB8Miphtr37CMZZQ5oXLH2yaXMJ2w8e2ZtHTl7GptT4jmndRuHDT" crossorigin="anonymous">
    <link rel="stylesheet" href="styles.css?v=<?= time() ?>">
</head>

<body class="d-flex flex-column min-vh-100">
<?php include 'header.php'; ?>
<?php include 'filter-bar.php'; ?>

<main class="container py-5 flex-grow-1">

    <!-- Welcome Message (main banner) -->
    <div class="text-center mb-5 px-3 px-md-0 border rounded shadow-sm main-title">
        <h1 class="fs-2 fs-md-1 fw-bold text-dark">Welcome to TREA</h1>
        <p class="lead fs-6 fs-md-5">
            Your trusted partner in property management, legal documentation, construction, and planning services.
        </p>
    </div>

    <div class="alert alert-info d-flex align-items-center small mb-4" role="alert">
  <svg class="bi flex-shrink-0 me-2" width="18" height="18" role="img" aria-label="Info:"><use xlink:href="#info-fill"/></svg>
  <div>
    <b>What do these badges mean?</b><br>
    <span class="badge bg-success me-1">Managed</span> properties: TREA oversees the entire process from contract to handover.<br>
    <span class="badge bg-secondary me-1">Brokerage</span> properties: TREA connects you to the owner, but the agency is not involved after introduction.
  </div>
</div>


    <section class="mb-5 text-light">
        <?php if ($isFiltered): ?>
    <section class="mb-5">
        <h3 class="mb-3">Matching Properties (<?= $filteredCount ?> found)</h3>
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-4 g-4">
            <?php if (!$filteredCount): ?>
                <p class="text-muted">No properties found. Try different filters.</p>
            <?php else: ?>
                <?php foreach ($filteredProperties as $property): ?>
                    <div class="col">
                        <div class="card h-100 shadow-sm">
                            <a href="view-property.php?property_id=<?= $property['property_id'] ?>">
                                <img src="<?= htmlspecialchars($property['image'] ?? 'uploads/properties/default.jpg') ?>" class="card-img-top img-fluid" style="height: 180px; object-fit: cover;" alt="Property Image">
                            </a>
                            <div class="card-body">
                                <a href="view-property.php?property_id=<?= $property['property_id'] ?>" class="text-decoration-none text-dark">
                                    <h5 class="card-title text-dark mb-2">
                                        <?= htmlspecialchars($property['property_name']) ?>
                                        <?php if ($property['service_slug'] === 'rental_property_management'): ?>
                                            <span class="badge bg-success float-end">Managed</span>
                                        <?php elseif ($property['service_slug'] === 'brokerage'): ?>
                                            <span class="badge bg-secondary float-end">Brokerage</span>
                                        <?php endif; ?>
                                    </h5>
                                </a>
                                <p class="card-text text-muted mb-1"><?= htmlspecialchars($property['location']) ?></p>
                                <p class="fw-bold text-success mb-2">CFA<?= number_format($property['price']) ?></p>
                                <form class="add-to-cart-form" data-property="<?= $property['property_id'] ?>">    
                                    <button type="button" class="btn btn-sm custom-btn add-to-cart-btn" data-id="<?= $property['property_id'] ?>">Add to Cart</button>
                                </form>

                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if ($totalPages > 1): ?>
            <!-- Pagination UI -->
            <nav class="mt-4">
                <ul class="pagination justify-content-center">
                    <?php
                    $query = $_GET;
                    $visibleLinks = 7; // How many numbered buttons to show in the center
                    $start = max(1, $page - floor($visibleLinks/2));
                    $end = min($totalPages, $start + $visibleLinks - 1);
                    $start = max(1, $end - $visibleLinks + 1);

                    // Previous button
                    if ($page > 1) {
                        $query['page'] = $page - 1;
                        echo '<li class="page-item"><a class="page-link" href="?' . http_build_query($query) . '">&laquo; Prev</a></li>';
                    }

                    // Numbered buttons
                    for ($i = $start; $i <= $end; $i++) {
                        $query['page'] = $i;
                        $active = ($i == $page) ? 'active' : '';
                        echo "<li class='page-item $active'><a class='page-link' href='?".http_build_query($query)."'>$i</a></li>";
                    }

                    // Next button
                    if ($page < $totalPages) {
                        $query['page'] = $page + 1;
                        echo '<li class="page-item"><a class="page-link" href="?' . http_build_query($query) . '">Next &raquo;</a></li>';
                    }
                    ?>
                </ul>
            </nav>
        <?php endif; ?>
    </section>
<?php endif; ?>
                </section>

<?php if (!$isFiltered): ?> <!--To hide other section when filtering-->
<!-- Managed only properties (up to 4 in one row) -->
<section class="mb-5 text-light">
    <h3 class="mb-3 text-light">Managed by TREA</h3>
    <div class="row g-4">
        <?php
        $managedToShow = array_slice($managed, 0, 4); // show max 4
        if (count($managedToShow) === 0): ?>
            <p>No managed properties currently available.</p>
        <?php else:
            foreach ($managedToShow as $property): ?>
                <div class="col-12 col-md-6 col-lg-3">
                        <div class="card h-100 shadow-sm">
                            <!-- Property image as card link -->
                            <a href="view-property.php?property_id=<?= $property['property_id'] ?>">
                                <img src="<?= htmlspecialchars($property['image'] ?? 'uploads/properties/default.jpg') ?>" class="card-img-top img-fluid" style="height: 180px; object-fit: cover;" alt="Property Image">
                            </a>
                            <div class="card-body">
                                <!-- Property name and service badge -->
                                <a href="view-property.php?property_id=<?= $property['property_id'] ?>" class="text-decoration-none text-dark">
                                    <h5 class="card-title text-dark mb-2">
                                        <?= htmlspecialchars($property['property_name']) ?>
                                        <?php if ($property['service_slug'] === 'rental_property_management'): ?>
                                            <span class="badge bg-success float-end">Managed</span>
                                        <?php endif; ?>
                                    </h5>
                                </a>
                                <!-- Property location and price -->
                                <p class="card-text text-muted mb-1"><?= htmlspecialchars($property['location']) ?></p>
                                <p class="fw-bold text-success mb-2">CFA<?= number_format($property['price']) ?></p>
                                <!-- Add to Cart Form -->
                                <form class="add-to-cart-form" data-property="<?= $property['property_id'] ?>">    
                                    <button type="button" class="btn btn-sm custom-btn add-to-cart-btn" data-id="<?= $property['property_id'] ?>">Add to Cart</button>
                                </form>

                            </div>
                        </div>
                </div>
            <?php endforeach;
        endif; ?>
    </div>
    <div class="text-end mt-3">
        <a href="property-managed.php" class="btn btn-sm custom-btn">View More</a>
    </div>
</section>

<!-- Properties for Rent (up to 4 in one row) -->
<section class="mb-5 text-light">
    <h3 class="mb-3 text-light">Properties for Rent</h3>
    <div class="row g-4">
        <?php
        $rentalsToShow = array_slice($rentals, 0, 4);
        if (count($rentalsToShow) === 0): ?>
            <p>No rental properties currently available.</p>
        <?php else:
            foreach ($rentalsToShow as $property): ?>
                <div class="col-12 col-md-6 col-lg-3">
                        <div class="card h-100 shadow-sm">
                            <!-- Property image as card link -->
                            <a href="view-property.php?property_id=<?= $property['property_id'] ?>">
                                <img src="<?= htmlspecialchars($property['image'] ?? 'uploads/properties/default.jpg') ?>" class="card-img-top img-fluid" style="height: 180px; object-fit: cover;" alt="Property Image">
                            </a>
                            <div class="card-body">
                                <!-- Property name and service badge -->
                                <a href="view-property.php?property_id=<?= $property['property_id'] ?>" class="text-decoration-none text-dark">
                                    <h5 class="card-title text-dark mb-2">
                                        <?= htmlspecialchars($property['property_name']) ?>
                                        <?php if ($property['service_slug'] === 'rental_property_management'): ?>
                                            <span class="badge bg-success float-end">Managed</span>
                                        <?php elseif ($property['service_slug'] === 'brokerage'): ?>
                                            <span class="badge bg-secondary float-end">Brokerage</span>
                                        <?php endif; ?>
                                    </h5>
                                </a>
                                <!-- Property location and price -->
                                <p class="card-text text-muted mb-1"><?= htmlspecialchars($property['location']) ?></p>
                                <p class="fw-bold text-success mb-2">CFA<?= number_format($property['price']) ?></p>
                                <!-- Add to Cart Form -->
                                <form class="add-to-cart-form" data-property="<?= $property['property_id'] ?>">    
                                    <button type="button" class="btn btn-sm custom-btn add-to-cart-btn" data-id="<?= $property['property_id'] ?>">Add to Cart</button>
                                </form>

                            </div>
                        </div>
                </div>
            <?php endforeach;
        endif; ?>
    </div>
    <div class="text-end mt-3">
        <a href="property-rent.php" class="btn btn-sm custom-btn">View More</a>
    </div>
</section>

<!-- Properties for Sale (up to 4 in one row) -->
<section class="mb-5 text-light">
    <h3 class="mb-3 text-light">Properties for Sale</h3>
    <div class="row g-4">
        <?php
        $salesToShow = array_slice($sales, 0, 4);
        if (count($salesToShow) === 0): ?>
            <p>No properties for sale currently available.</p>
        <?php else:
            foreach ($salesToShow as $property): ?>
                <div class="col-12 col-md-6 col-lg-3">
                        <div class="card h-100 shadow-sm">
                            <!-- Property image as card link -->
                            <a href="view-property.php?property_id=<?= $property['property_id'] ?>">
                                <img src="<?= htmlspecialchars($property['image'] ?? 'uploads/properties/default.jpg') ?>" class="card-img-top" alt="Property Image">
                            </a>
                            <div class="card-body">
                                <!-- Property name and badges -->
                                <a href="view-property.php?property_id=<?= $property['property_id'] ?>" class="text-decoration-none text-dark">
                                    <h5 class="card-title text-dark mb-2">
                                        <?= htmlspecialchars($property['property_name']) ?>
                                        <?php if (in_array($property['service_slug'], ['sale_property_management', 'rental_property_management'])): ?>
                                            <span class="badge bg-success float-end">Managed</span>
                                        <?php endif; ?>
                                        <?php if ($property['service_slug'] === 'brokerage'): ?>
                                            <span class="badge bg-secondary float-end">Brokerage</span>
                                        <?php endif; ?>
                                    </h5>
                                </a>
                                <!-- Property location and price -->
                                <p class="card-text text-muted mb-1"><?= htmlspecialchars($property['location']) ?></p>
                                <p class="fw-bold text-success mb-2">CFA<?= number_format($property['price']) ?></p>
                                <!-- Add to Cart Form -->
                                <form class="add-to-cart-form" data-property="<?= $property['property_id'] ?>">    
                                    <button type="button" class="btn btn-sm custom-btn add-to-cart-btn" data-id="<?= $property['property_id'] ?>">Add to Cart</button>
                                </form>

                            </div>
                        </div>
                </div>
            <?php endforeach;
        endif; ?>
    </div>
    <div class="text-end mt-3">
        <a href="property-sale.php" class="btn btn-sm custom-btn">View More</a>
    </div>
</section>

<!-- Brokerage only properties (up to 4 in one row) -->
<section class="mb-5 text-light">
    <h3 class="mb-3 text-light">Brokerage Properties</h3>
    <div class="row g-4">
        <?php
        $brokerageToShow = array_slice($brokerage, 0, 4);
        if (count($brokerageToShow) === 0): ?>
            <p>No brokerage properties currently available.</p>
        <?php else:
            foreach ($brokerageToShow as $property): ?>
                <div class="col-12 col-md-6 col-lg-3">
                        <div class="card h-100 shadow-sm">
                            <!-- Property image as card link -->
                            <a href="view-property.php?property_id=<?= $property['property_id'] ?>">
                                <img src="<?= htmlspecialchars($property['image'] ?? 'uploads/properties/default.jpg') ?>" class="card-img-top img-fluid" style="height: 180px; object-fit: cover;" alt="Property Image">
                            </a>
                            <div class="card-body">
                                <!-- Property name and service badge -->
                                <a href="view-property.php?property_id=<?= $property['property_id'] ?>" class="text-decoration-none text-dark">
                                    <h5 class="card-title text-dark mb-2">
                                        <?= htmlspecialchars($property['property_name']) ?>
                                        <?php if ($property['service_slug'] === 'brokerage'): ?>
                                            <span class="badge bg-secondary float-end">Brokerage</span>
                                        <?php endif; ?>
                                    </h5>
                                </a>
                                <!-- Property location and price -->
                                <p class="card-text text-muted mb-1"><?= htmlspecialchars($property['location']) ?></p>
                                <p class="fw-bold text-success mb-2">CFA<?= number_format($property['price']) ?></p>
                                <!-- Add to Cart Form -->
                                <form class="add-to-cart-form" data-property="<?= $property['property_id'] ?>">    
                                    <button type="button" class="btn btn-sm custom-btn add-to-cart-btn" data-id="<?= $property['property_id'] ?>">Add to Cart</button>
                                </form>

                            </div>
                        </div>
                </div>
            <?php endforeach;
        endif; ?>
    </div>
    <div class="text-end mt-3">
        <a href="property-brokerage.php" class="btn btn-sm custom-btn">View More</a>
    </div>
</section>
    <?php endif; ?>

</main>

<!-- toast popoup for added items to cart -->
<div id="cart-toast" class="toast position-fixed bottom-0 end-0 m-3" style="z-index:9999;" data-bs-delay="1800">
  <div class="toast-body text-success fw-bold">
    Property added to cart!
  </div>
</div>

<?php include 'footer.php'; ?>

<!-- Bootstrap JS and custom responsive menu script -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js" integrity="sha384-j1CDi7MgGQ12Z7Qab0qlWQ/Qqz24Gc6BM0thvEMVjHnfYGF0rmFCozFSxQBxwHKO" crossorigin="anonymous"></script>

<!-- Script to close main navbar on small screen-->
<script src="navbar-close.js?v=1"></script>

        <!-- to add item to cart without refreshing the page and going back to the top-->
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
</script>

</body>
</html>
