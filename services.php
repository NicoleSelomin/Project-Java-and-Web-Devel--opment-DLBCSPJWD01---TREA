<?php
/**
 * ----------------------------------------------------------------------
 * services.php
 * ----------------------------------------------------------------------
 * Public page: Displays all available services on the TREA platform.
 * Users can view and request any service. No login required.
 * ----------------------------------------------------------------------
 */

session_start();
require 'db_connect.php';

// ----------------------------------------------------------------------
// Fetch all available services
// ----------------------------------------------------------------------
$stmt = $pdo->query("SELECT * FROM services ORDER BY service_name ASC");
$services = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Meta and Title -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Our Services - TREA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css?v=<?= time() ?>">
</head>

<body class="d-flex flex-column min-vh-100 bg-light">
<?php include 'header.php'; ?>

<div class="container-fluid flex-grow-1">
    <div class="row">
        <!-- Sidebar (About/brand blurb) -->
        <aside class="col-12 col-md-3 mb-3">
            <button class="btn btn-sm d-md-none animate-text custom-btn" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarCollapse" aria-expanded="false" aria-controls="sidebarCollapse">
                Open Sidebar
            </button>
            <div class="collapse d-md-block" id="sidebarCollapse">
                <div class="sidebar text-center py-4 px-2 bg-dark rounded shadow-sm h-100">
                    <h5 class="text-white mb-3">Shop your ideal property like you shop for your favorite food.</h5>
                    <img src="about-us-image.png" alt="TREA About Us" class="mb-3 img-fluid rounded">
                    <p class="text-white small">Your trusted real estate agents, here to provide only the best and most affordable options.</p>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="col-12 col-md-9">
            <div class="text-center mb-5 px-3 px-md-0 border rounded shadow-sm main-title">
                <h1 class="fs-2 fs-md-1 fw-bold text-dark">Our Services</h1>
            </div>

            <div class="row row-cols-1 row-cols-md-2 g-4">
                <?php foreach ($services as $service): ?>
                    <?php
                        $params = [
                            'service_id' => $service['service_id'],
                            'redirect'   => $_SERVER['REQUEST_URI'],
                            'service_name' => $service['service_name']
                        ];
                        $link = 'owner-request-check.php?' . http_build_query($params);
                    ?>
                    <div class="col">
                        <div class="card h-100 shadow-sm">
                            <div class="card-body d-flex flex-column text-center">
                                <h2 class="h5 card-title mb-3 text-dark fw-bold"><?= htmlspecialchars($service['service_name']) ?></h2>
                                <p class="card-text flex-grow-1"><?= nl2br(htmlspecialchars($service['description'])) ?></p>
                                <a href="<?= $link ?>" class="btn custom-btn mt-3 align-self-start">Request This Service</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($services)): ?>
                    <div class="col">
                        <div class="alert alert-secondary text-center">No services are currently available.</div>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>

<?php include 'footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
