<?php
/**
 * view-property.php
 * ---------------------------------------------------------
 * Displays all details for a single property listing.
 * - Fetches property by ID from database.
 * - Shows all key property details, large image, and description.
 * - Includes "Add to Cart" button (GET to add-to-cart.php).
 * - Responsive, Bootstrap 5.3.6.
 * ---------------------------------------------------------
 */

require 'db_connect.php';

// Validate and fetch property
if (!isset($_GET['id'])) {
    echo "Property not found.";
    exit();
}

$propertyId = intval($_GET['id']);
$stmt = $pdo->prepare("SELECT * FROM properties WHERE property_id = ?");
$stmt->execute([$propertyId]);
$property = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$property) {
    echo "Property not found.";
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($property['property_name']) ?> - TREA</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="styles.css?v=<?= time() ?>">
</head>
<body class="d-flex flex-column min-vh-100 bg-light">
  <?php include 'header.php'; ?>

  <main class="container py-5 flex-grow-1">
    <div class="row align-items-center g-5">
      <!-- Property Image -->
      <div class="col-md-6">
        <img src="<?= htmlspecialchars($property['image'] ?? 'uploads/properties/default.jpg') ?>"
             class="img-fluid rounded shadow-sm w-100"
             alt="Property Image">
      </div>

      <!-- Property Details -->
      <div class="col-md-6">
        <h2 class="mb-3"><?= htmlspecialchars($property['property_name']) ?></h2>
        <p><strong>Listing Type:</strong> <?= htmlspecialchars(ucfirst($property['listing_type'])) ?></p>
        <p><strong>Property Type:</strong> <?= htmlspecialchars(ucfirst($property['property_type'])) ?></p>
        <p><strong>Location:</strong> <?= htmlspecialchars($property['location']) ?></p>
        <p><strong>Size:</strong> <?= htmlspecialchars($property['size_sq_m']) ?> m²</p>
        <p><strong>Bedrooms:</strong> <?= $property['number_of_bedrooms'] ?? '—' ?></p>
        <p><strong>Bathrooms:</strong> <?= $property['number_of_bathrooms'] ?? '—' ?></p>
        <p><strong>Floors:</strong> <?= $property['floor_count'] ?? '—' ?></p>
        <p class="lead text-success"><strong>Price:</strong> CFA<?= number_format($property['price']) ?></p>
        <p><strong>Description:</strong><br><?= nl2br(htmlspecialchars($property['property_description'])) ?></p>

        <!-- Add to Cart Form -->
        <form action="add-to-cart.php" method="GET" class="mt-4">
          <input type="hidden" name="id" value="<?= $property['property_id'] ?>">
          <button type="submit" class="btn btn-primary">
            Add to Cart
          </button>
        </form>
      </div>
    </div>
  </main>

  <?php include 'footer.php'; ?>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
