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
$stmt = $pdo->prepare("SELECT p.*, s.slug AS service_slug
FROM properties p 
LEFT JOIN services s ON p.service_id = s.service_id
WHERE p.availability = 'available'
$whereSql
ORDER BY p.created_at DESC");

$stmt->execute($filterParams);
$allProperties = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Split property list into rentals and sales
$rentals = array_filter($allProperties, fn($p) => $p['listing_type'] === 'rent');
$sales = array_filter($allProperties, fn($p) => $p['listing_type'] === 'sale');
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

    <!-- Our Services (static cards for top service types) -->
    <section class="mb-5 text-light">
        <h3 class="mb-4 text-light">Our Services</h3>
        <div class="row g-3">
            <?php
            // List the agency's offered services (static for home page)
            $services = [
                'Property Management', 
                'Legal Document Assistance', 
                'Construction Supervision', 
                'Architecture Plan Drawing', 
                'Brokerage'
            ];
            foreach ($services as $service): ?>
                <div class="col-12 col-sm-6 col-lg-4">
                    <div class="card h-100 border-0 shadow-sm service-card">
                        <div class="card-body text-center">
                            <h6 class="card-title fw-bold text-dark"><?= htmlspecialchars($service) ?></h6>
                            <a href="services.php" class="btn mt-2 custom-btn">Learn More</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Properties for Rent (up to 8) -->
    <section class="mb-5 text-light">
        <h3 class="mb-3 text-light">Properties for Rent</h3>
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-4 g-4">
            <?php if (count($rentals) === 0): ?>
                <p>No rental properties currently available.</p>
            <?php else:
                // Show up to 8 rental properties
                foreach (array_slice($rentals, 0, 8) as $property): ?>
                    <div class="col">
                        <div class="card h-100 shadow-sm">
                            <!-- Property image as card link -->
                            <a href="view-property.php?id=<?= $property['property_id'] ?>">
                                <img src="<?= htmlspecialchars($property['image'] ?? 'uploads/properties/default.jpg') ?>" class="card-img-top img-fluid" style="height: 180px; object-fit: cover;" alt="Property Image">
                            </a>
                            <div class="card-body">
                                <!-- Property name and service badge -->
                                <a href="view-property.php?id=<?= $property['property_id'] ?>" class="text-decoration-none text-dark">
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
                                <form action="add-to-cart.php" method="GET">
                                    <input type="hidden" name="id" value="<?= $property['property_id'] ?>">
                                    <button type="submit" class="btn btn-sm custom-btn">Add to Cart</button>
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

    <!-- Properties for Sale (up to 8) -->
    <section class="mb-5 text-light">
        <h3 class="mb-3 text-light">Properties for Sale</h3>
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-4 g-4">
            <?php if (count($sales) === 0): ?>
                <p>No properties for sale currently available.</p>
            <?php else:
                // Show up to 8 properties for sale
                foreach (array_slice($sales, 0, 8) as $property): ?>
                    <div class="col">
                        <div class="card h-100 shadow-sm">
                            <!-- Property image as card link -->
                            <a href="view-property.php?id=<?= $property['property_id'] ?>">
                                <img src="<?= htmlspecialchars($property['image'] ?? 'uploads/properties/default.jpg') ?>" class="card-img-top" alt="Property Image">
                            </a>
                            <div class="card-body">
                                <!-- Property name and badges -->
                                <a href="view-property.php?id=<?= $property['property_id'] ?>" class="text-decoration-none text-dark">
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
                                <form action="add-to-cart.php" method="GET">
                                    <input type="hidden" name="id" value="<?= $property['property_id'] ?>">
                                    <button type="submit" class="btn btn-sm custom-btn">Add to Cart</button>
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

</main>

<?php include 'footer.php'; ?>

<!-- Bootstrap JS and custom responsive menu script -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js" integrity="sha384-j1CDi7MgGQ12Z7Qab0qlWQ/Qqz24Gc6BM0thvEMVjHnfYGF0rmFCozFSxQBxwHKO" crossorigin="anonymous"></script>
<script>
// Responsive Navbar: close the navbar when clicking outside on mobile
document.addEventListener("click", function (event) {
    const navbar = document.getElementById("mainNavbar");
    const toggler = document.querySelector(".navbar-toggler");

    // Collapse navbar if open and click is outside
    if (
        navbar && toggler &&
        navbar.classList.contains("show") &&
        !navbar.contains(event.target) &&
        !toggler.contains(event.target)
    ) {
        new bootstrap.Collapse(navbar).hide();
    }
});
</script>
</body>
</html>
