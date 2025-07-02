<?php
/**
 * ----------------------------------------------------------------------
 * services.php (Property Owner Entry)
 * ----------------------------------------------------------------------
 * Public page: For property owners (and prospective owners) to view
 * all available and upcoming TREA services. Owners can click to request 
 * a service (login/signup required later in flow).
 * ----------------------------------------------------------------------
 */

session_start();
require 'db_connect.php';

// Fetch all active (currently offered) services from DB
$stmt = $pdo->query("SELECT * FROM services WHERE active = 1 ORDER BY service_name ASC");
$services = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Manually define upcoming services (not yet in DB)
$coming_soon_services = [
    [
        'service_name' => 'Sale Property Management',
        'description'  => 'We help you manage the entire sale process of your property—from marketing to negotiation to legal transfer. Hassle-free and transparent.',
    ],
    [
        'service_name' => 'Architecture Design',
        'description'  => 'Get professional architecture services for your new projects or renovations, with plans tailored to your needs and local standards.',
    ],
    [
        'service_name' => 'Legal Assistance',
        'description'  => 'Expert property lawyers help you with contracts, disputes, and regulatory compliance—so you have peace of mind.',
    ],
    [
        'service_name' => 'Construction Supervision',
        'description'  => 'Our engineers and supervisors ensure your building project is delivered safely, on time, and to specification.',
    ],
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Meta and Title -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>List Your Property & Owner Services - TREA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css?v=<?= time() ?>">
</head>
<body class="d-flex flex-column min-vh-100 bg-light">
<?php include 'header.php'; ?>

<!-- Owner Services Page Section -->
<div class="container-fluid py-4">
    <div class="row">
        <!-- Sidebar: Property Partner Promotion -->
        <aside class="col-12 col-md-3 mb-4 mb-md-0">
            <div class="card shadow-sm h-100 border-0 bg-gradient p-3">
                <h4 class="fw-bold mb-3">Become a TREA Property Partner</h4>
                <img src="images/sidebar.png" alt="TREA Partner" class="img-fluid rounded mb-3 border">
                <p>List your property, access professional management, and enjoy hassle-free rental income or property sales.<br>
                <span class="fw-bold">Ready to get started?</span> <span class="fst-italic">Choose a service below</span> to begin!
                </p>
            </div>
        </aside>
        <!-- Main Content: Services -->
        <main class="col-12 col-md-9">
            <!-- Owner Services Header -->
            <section class="mb-4">
                <div class="bg-white p-4 rounded shadow-sm border mb-4">
                    <h1 class="mb-1 fw-bold">Owner Services</h1>
                    <p class="lead text-muted mb-0">Select the service you need to list, manage, or improve your property. All services are provided by trusted professionals and TREA experts.</p>
                </div>
            </section>

            <!-- Available Services -->
            <section class="mb-5">
                <h3 class="fw-bold mb-3" style="color:#333;">Available Services</h3>
                <div class="row g-4">
                    <?php foreach ($services as $service): ?>
                        <?php
                            $params = [
                                'service_id' => $service['service_id'],
                                'redirect'   => $_SERVER['REQUEST_URI'],
                                'service_name' => $service['service_name']
                            ];
                            $link = 'owner-request-check.php?' . http_build_query($params);
                        ?>
                        <div class="col-12 col-md-6">
                            <div class="card h-100 shadow-sm border-0 bg-light">
                                <div class="card-body d-flex flex-column text-center">
                                    <h5 class="card-title fw-bold mb-3"><?= htmlspecialchars($service['service_name']) ?></h5>
                                    <p class="card-text flex-grow-1"><?= nl2br(htmlspecialchars($service['description'])) ?></p>
                                    <a href="<?= $link ?>" class="btn btn-lg mt-3" style="background:#ec38bc; color:white; font-weight:bold;">Request This Service</a>
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
            </section>

            <!-- Coming Soon -->
            <section>
                <h3 class="fw-bold mb-3" style="color:#333;">Coming Soon</h3>
                <div class="row g-4">
                    <?php
                    $comingSoon = [
                        'Sale Property Management',
                        'Architecture Design',
                        'Legal Assistance',
                        'Construction Supervision'
                    ];
                    foreach ($comingSoon as $service): ?>
                        <div class="col-12 col-md-6">
                            <div class="card h-100 shadow-sm border-0 bg-secondary-subtle">
                                <div class="card-body text-center">
                                    <h6 class="card-title fw-bold mb-2" style="color:#7303c0;"><?= htmlspecialchars($service) ?></h6>
                                    <span class="badge bg-warning text-dark">Coming Soon</span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        </main>
    </div>
</div>

<?php include 'footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
<script src="navbar-close.js?v=1"></script>
</body>
</html>
