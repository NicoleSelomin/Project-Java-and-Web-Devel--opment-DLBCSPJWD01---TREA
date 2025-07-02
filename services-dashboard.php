<?php
/**
 * ----------------------------------------------------------------------
 * services-dashboard.php
 * ----------------------------------------------------------------------
 * Dashboard for the General Manager to manage platform services.
 * - Add new service (form)
 * - List all services (table and card layout)
 * - Edit/delete actions
 * - Accessible only to the General Manager
 * ----------------------------------------------------------------------
 */

session_start();
require 'db_connect.php';

// ----------------------------------------------------------------------
// Authentication: Only General Manager can access
// ----------------------------------------------------------------------
if (!isset($_SESSION['role']) || strtolower(trim($_SESSION['role'])) !== 'general manager') {
    header("Location: staff-login.php");
    exit();
}

// ----------------------------------------------------------------------
// Fetch All Services
// ----------------------------------------------------------------------
$services = [];
try {
    $stmt = $pdo->query("SELECT * FROM services");
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// ----------------------------------------------------------------------
// Staff Session Info
// ----------------------------------------------------------------------
$fullName = $_SESSION['full_name'] ?? '';
$role = $_SESSION['role'] ?? '';
$userId = $_SESSION['staff_id'] ?? '';

$stmt = $pdo->prepare("SELECT profile_picture FROM staff WHERE staff_id = ?");
$stmt->execute([$userId]);
$staff = $stmt->fetch(PDO::FETCH_ASSOC);

$profilePicturePath = (!empty($staff['profile_picture']) && file_exists($staff['profile_picture']))
    ? $staff['profile_picture']
    : 'default.png';

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Meta and Title -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Service Dashboard</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css?v=<?= time() ?>">
</head>
<body class="d-flex flex-column min-vh-100 bg-light">
<?php include 'header.php'; ?>

<div class="container-fluid flex-grow-1">
    <div class="row">
        <!-- Sidebar (collapsible on mobile) -->
        <nav class="col-12 col-md-3 mb-4 mb-md-0">
            <!-- Mobile collapse toggle (hidden on md+) -->
            <button class="btn btn-outline-secondary btn-sm d-md-none mb-3 w-100 custom-btn"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#sidebarMenu"
                aria-expanded="false"
                aria-controls="sidebarMenu">
                Menu
            </button>
            <div class="collapse d-md-block" id="sidebarMenu">
                <div class="bg-white rounded shadow-sm py-4 px-3">
                    <!-- Profile Image and Summary -->
                    <div class="text-center mb-4">
                        <img src="<?= htmlspecialchars($profilePicturePath) ?>" 
                             alt="Profile Picture"
                             class="rounded-circle mb-3"
                             style="width:110px; height:110px; object-fit:cover; border:2px solid #e9ecef;">
                        <div class="fw-semibold"><?= htmlspecialchars($fullName) ?></div>
                        <div class="text-muted small mb-2">Staff ID: <?= htmlspecialchars($userId) ?></div>
                        <!-- Profile Actions -->
                        <a href="notifications.php" class="btn btn-outline-primary btn-sm w-100 mb-2 profile-btn" style="background-color: #FF6EC7;">Notifications🔔</a>
                        <a href="edit-staff-profile.php" class="btn btn-outline-secondary btn-sm w-100 mb-2 profile-btn" style="background-color: #E021BA;">Edit Profile</a>
                        <a href="staff-logout.php" class="btn btn-outline-danger btn-sm w-100 profile-btn" style="background-color: #C154C1;">Logout</a>
                    </div>
                </div>
            </div>
        </nav>
        <!-- End Sidebar -->

        <!-- Main Content -->
        <main class="col-12 col-md-9">
            <div class="mb-4 p-3 border rounded shadow-sm">
                <h1 class="h3 mb-0">Service Management</h1>
            </div>

            <!-- Add New Service Form -->
            <section class="mb-4">
                <div class="card">
                    <div class="card-body">
                        <h2 class="h5 mb-3">Add New Service</h2>
                        <form action="add-service.php" method="post">
                            <div class="mb-3">
                                <label for="service_name" class="form-label">Service Name <span class="text-danger">*</span></label>
                                <input type="text" name="service_name" id="service_name" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label for="description" class="form-label">Description <span class="text-danger">*</span></label>
                                <textarea name="description" id="description" rows="4" class="form-control" required></textarea>
                            </div>
                            <button type="submit" class="btn custom-btn">Add Service</button>
                        </form>
                    </div>
                </div>
            </section>

            <!-- Existing Services Table (Desktop) -->
            <section>
                <div class="card mb-4">
                    <div class="card-body">
                        <h2 class="h5 mb-3">Existing Services</h2>
                        <div class="table-responsive d-none d-md-block">
                            <table class="table table-bordered align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Service Name</th>
                                        <th>Description</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($services)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center text-muted">No services found.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($services as $row): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($row['service_id']) ?></td>
                                                <td><?= htmlspecialchars($row['service_name']) ?></td>
                                                <td><?= htmlspecialchars($row['description']) ?></td>
                                                <td class="text-center d-flex">
                                                    <a href="edit-service.php?id=<?= $row['service_id'] ?>" class="btn btn-sm btn-outline-primary me-2">Edit</a>
                                                    <a href="delete-service.php?id=<?= $row['service_id'] ?>" onclick="return confirm('Delete this service?');" class="btn btn-sm btn-outline-danger">Delete</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Card Layout for Mobile -->
                        <div class="d-md-none">
                            <?php if (empty($services)): ?>
                                <div class="text-center text-muted">No services found.</div>
                            <?php else: ?>
                                <?php foreach ($services as $row): ?>
                                    <div class="card mb-3">
                                        <div class="card-body">
                                            <p><strong>ID:</strong> <?= htmlspecialchars($row['service_id']) ?></p>
                                            <p><strong>Service Name:</strong> <?= htmlspecialchars($row['service_name']) ?></p>
                                            <p><strong>Description:</strong> <?= htmlspecialchars($row['description']) ?></p>
                                            <div class="mt-2">
                                                <a href="edit-service.php?id=<?= $row['service_id'] ?>" class="btn btn-sm btn-outline-primary me-2">Edit</a>
                                                <a href="delete-service.php?id=<?= $row['service_id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this service?');">Delete</a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </section>

            <a href="staff-profile.php" class="btn bg-dark text-white fw-bold">🡰 Back to dashboard</a>
        </main>
    </div>
</div>

<?php include 'footer.php'; ?>

<!-- Script to close main navbar on small screen-->
<script src="navbar-close.js?v=1"></script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
