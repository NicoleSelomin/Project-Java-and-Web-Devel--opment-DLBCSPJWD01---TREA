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

// -------------------------
// Pagination calculation
// -------------------------
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 9;
$offset = ($page - 1) * $perPage;

// -------------------------
// Count total available sale listings
// -------------------------
$countStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM properties p
    JOIN owner_service_requests r ON p.request_id = r.request_id
    JOIN sale_property_management_details s ON r.request_id = s.request_id
    WHERE p.listing_type = 'sale' AND p.availability = 'available' $filterSql
");
$countStmt->execute($filterParams);
$totalResults = $countStmt->fetchColumn();
$totalPages = ceil($totalResults / $perPage);

// -------------------------
// Fetch paginated property results
// -------------------------
$dataStmt = $pdo->prepare("
    SELECT p.*, s.slug AS service_slug
    FROM properties p
    LEFT JOIN services s ON p.service_id = s.service_id
    WHERE p.listing_type = 'sale' AND p.availability = 'available' $filterSql
    ORDER BY p.created_at DESC
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

<?php
// -------------------------
// Site-wide header & filter bar
// -------------------------
include 'header.php';
include 'filter-bar.php';
?>

<main class="container py-5 flex-grow-1">
    <!-- Main heading -->
    <div class="mb-4 p-3 border rounded shadow-sm text-center main-title">
        <h2 class="mb-4">Available Properties for Sale</h2>
    </div>

    <!-- Responsive cards grid -->
    <div class="row row-cols-1 row-cols-md-3 g-4">
        <?php foreach ($properties as $property): ?>
            <div class="col">
                <div class="card h-100">
                    <!-- Clickable image -->
                    <a href="view-property.php?id=<?= $property['property_id'] ?>">
                        <img src="<?= htmlspecialchars($property['image'] ?? 'uploads/properties/default.jpg') ?>"
                             class="card-img-top" alt="Property Image">
                    </a>
                    <div class="card-body">
                        <!-- Title with badge -->
                        <a href="view-property.php?id=<?= $property['property_id'] ?>" class="text-decoration-none text-dark">
                            <h5 class="card-title mb-2">
                                <?= htmlspecialchars($property['property_name']) ?>
                                <?php if ($property['service_slug'] === 'sale_property_management'): ?>
                                    <span class="badge bg-success float-end">Managed</span>
                                <?php elseif ($property['service_slug'] === 'brokerage'): ?>
                                    <span class="badge bg-secondary float-end">Brokerage</span>
                                <?php endif; ?>
                            </h5>
                        </a>
                        <!-- Description and price/location -->
                        <p class="card-text"><?= htmlspecialchars($property['property_description']) ?></p>
                        <p class="text-muted mb-1"><?= htmlspecialchars($property['location']) ?> | CFA<?= number_format($property['price']) ?></p>

                        <!-- Add to Cart button -->
                        <form action="add-to-cart.php" method="GET" class="mb-0">
                            <input type="hidden" name="id" value="<?= $property['property_id'] ?>">
                            <button type="submit" class="btn custom-btn btn-sm">Add to Cart</button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
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

<?php include 'footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>
