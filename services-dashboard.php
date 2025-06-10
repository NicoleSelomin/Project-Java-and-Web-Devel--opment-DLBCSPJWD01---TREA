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
        <!-- Sidebar -->
        <aside class="col-12 col-md-3 mb-3">
            <button class="btn btn-sm d-md-none mb-3 custom-btn" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarCollapse" aria-expanded="false" aria-controls="sidebarCollapse">
                Menu
            </button>
            <div class="collapse d-md-block" id="sidebarCollapse">
                <div class="sidebar text-center py-3 px-2 border bg-white rounded shadow-sm">
                    <div class="profile-summary mb-4">
                        <img src="<?= htmlspecialchars($profilePicturePath) ?>" alt="Profile Picture" class="rounded-circle mb-2" width="80" height="80">
                        <div><strong><?= htmlspecialchars($fullName) ?></strong></div>
                        <small class="text-muted">ID: <?= htmlspecialchars($userId) ?></small>
                    </div>
                    <a href="notifications.php" class="btn btn-outline-secondary w-100 mb-2">View Notifications</a>
                    <a href="edit-staff-profile.php" class="btn btn-outline-secondary w-100 mb-2">Edit Profile</a>
                    <a href="staff-logout.php" class="btn btn-outline-danger w-100">Logout</a>
                </div>
            </div>
        </aside>

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
                            <button type="submit" class="btn btn-primary">Add Service</button>
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
                                                <td class="text-center">
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
        </main>
    </div>
</div>

<?php include 'footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
