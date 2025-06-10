<?php
/*
|--------------------------------------------------------------------------
| property-rent.php
|--------------------------------------------------------------------------
| Lists all available rental properties with dynamic filter and pagination.
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
    WHERE p.listing_type = 'rent' AND p.availability = 'available' $filterSql
");
$countStmt->execute($filterParams);
$totalResults = $countStmt->fetchColumn();
$totalPages = ceil($totalResults / $perPage);

// Fetch paginated property data
$dataStmt = $pdo->prepare("
    SELECT p.*, s.slug AS service_slug
    FROM properties p
    LEFT JOIN services s ON p.service_id = s.service_id
    WHERE p.listing_type = 'rent' AND p.availability = 'available'
    $filterSql
    ORDER BY p.created_at DESC
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
    <title>Properties for Rent</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <link rel="stylesheet" href="styles.css?v=<?= time() ?>">
</head>
<body class="d-flex flex-column min-vh-100 bg-light">

<?php
// Site header and filter bar (filters handled in included file)
include 'header.php';
include 'filter-bar.php';
?>

<div class="container-fluid flex-grow-1">
    <div class="row">
        <main class="container py-5 flex-grow-1">
            <!-- Main Title/Heading -->
            <div class="mb-4 p-3 border rounded shadow-sm text-center main-title">
                <h2 class="mb-4">Available Properties for Rent</h2>
            </div>

            <!-- Property Cards Grid -->
            <div class="row row-cols-1 row-cols-md-3 g-4">
                <?php foreach ($properties as $property): ?>
                    <div class="col">
                        <div class="card h-100">
                            <!-- Property Image -->
                            <a href="view-property.php?id=<?= $property['property_id'] ?>">
                                <img src="<?= htmlspecialchars(!empty($property['image']) ? $property['image'] : 'uploads/properties/default.jpg') ?>"
                                     class="card-img-top" alt="Property Image">
                            </a>

                            <div class="card-body">
                                <!-- Property Name and Service Badge -->
                                <a href="view-property.php?id=<?= $property['property_id'] ?>" class="text-decoration-none text-dark">
                                    <h5 class="card-title mb-2">
                                        <?= htmlspecialchars($property['property_name']) ?>
                                        <?php if ($property['service_slug'] === 'brokerage'): ?>
                                            <span class="badge bg-secondary float-end ms-1">Brokerage</span>
                                        <?php elseif ($property['service_slug'] === 'rental_property_management'): ?>
                                            <span class="badge bg-success float-end ms-1">Managed</span>
                                        <?php endif; ?>
                                    </h5>
                                </a>

                                <!-- Short Description & Location -->
                                <p class="card-text"><?= htmlspecialchars($property['property_description']) ?></p>
                                <p class="text-muted mb-1"><?= htmlspecialchars($property['location']) ?> | CFA<?= number_format($property['price']) ?></p>

                                <!-- Add to Cart Button -->
                                <form action="add-to-cart.php" method="GET" class="mb-0">
                                    <input type="hidden" name="id" value="<?= $property['property_id'] ?>">
                                    <button type="submit" class="btn custom-btn btn-sm">Add to Cart</button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

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
    </div>
</div>

<?php include 'footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>
