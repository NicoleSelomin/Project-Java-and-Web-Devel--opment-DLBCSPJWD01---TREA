<?php
/**
 * -----------------------------------------------------------------------------------
 * edit-service.php
 * -----------------------------------------------------------------------------------
 * 
 * Edit Service (General Manager Only)
 *
 * Allows the general manager to update the name and description of an existing service.
 * Features:
 * - Loads current service data for the form
 * - Updates the services table if valid data is submitted
 * - Handles all redirects and access control
 * - Provides sidebar with staff profile info and navigation
 *
 * Dependencies:
 * - db_connect.php: Provides $pdo (PDO database connection)
 * - staff-login.php: Redirects if not logged in or unauthorized
 * - Bootstrap 5: For layout and responsive design
 */

session_start();
require 'db_connect.php';

// -----------------------------------------------------------------------------
// 1. ACCESS CONTROL: Only 'general manager' can access this page
// -----------------------------------------------------------------------------
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'general manager') {
    header("Location: staff-login.php");
    exit();
}

// -----------------------------------------------------------------------------
// 2. REQUIRE SERVICE ID TO EDIT; REDIRECT IF MISSING
// -----------------------------------------------------------------------------
if (!isset($_GET['id'])) {
    header("Location: services-dashboard.php");
    exit();
}
$service_id = intval($_GET['id']);

// -----------------------------------------------------------------------------
// 3. HANDLE FORM SUBMISSION: UPDATE SERVICE ON POST
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $service_name = trim($_POST['service_name']);
    $description = trim($_POST['description']);

    // Validate required fields
    if (!empty($service_name) && !empty($description)) {
        $stmt = $pdo->prepare("UPDATE services SET service_name = ?, description = ? WHERE service_id = ?");
        if ($stmt->execute([$service_name, $description, $service_id])) {
            // Redirect to dashboard on successful update
            header("Location: services-dashboard.php?updated=1");
            exit();
        } else {
            // If update fails, show error (could also log error for debugging)
            echo "Update failed.";
        }
    } else {
        // Simple error if form is incomplete
        echo "All fields are required.";
    }
} else {
    // -------------------------------------------------------------------------
    // 4. LOAD EXISTING SERVICE DATA FOR EDIT FORM (GET REQUEST)
    // -------------------------------------------------------------------------
    $stmt = $pdo->prepare("SELECT service_name, description FROM services WHERE service_id = ?");
    $stmt->execute([$service_id]);
    $service = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$service) {
        // If no such service, abort
        echo "Service not found.";
        exit();
    }

    // -------------------------------------------------------------------------
    // 5. LOAD STAFF PROFILE INFO FOR SIDEBAR
    // -------------------------------------------------------------------------
    $fullName = $_SESSION['full_name'];
    $role = $_SESSION['role'] ?? '';
    $userId = $_SESSION['staff_id'];

    $stmt = $pdo->prepare("SELECT s.profile_picture FROM staff s WHERE s.staff_id = ?");
    $stmt->execute([$userId]);
    $staff = $stmt->fetch(PDO::FETCH_ASSOC);

    $profilePicturePath = (!empty($staff['profile_picture']) && file_exists($staff['profile_picture']))
        ? $staff['profile_picture']
        : 'default.png';
    ?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Edit Service</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
  <link rel="stylesheet" href="styles.css?v=<?= time() ?>">
</head>
<body class="d-flex flex-column min-vh-100 bg-light">
<?php include 'header.php'; ?>

<div class="container-fluid">
  <div class="row">
    <!-- Sidebar: Responsive, with staff profile and links -->
    <div class="col-12 col-md-3 mb-3">
      <button class="btn btn-sm d-md-none mb-3 custom-btn" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarCollapse" aria-expanded="false" aria-controls="sidebarCollapse">
        Open Menu
      </button>
      <div class="collapse d-md-block" id="sidebarCollapse">
        <div class="sidebar text-center">
          <div class="profile-summary text-center">
            <img src="<?= htmlspecialchars($profilePicturePath) ?>" alt="Profile Picture" class="mb-3">
            <p><strong><?= htmlspecialchars($fullName) ?></strong></p>
            <p>ID: <?= htmlspecialchars($userId) ?></p>
            <a href="notifications.php" class="btn mt-3 bg-light">View Notifications</a><br>
            <a href="edit-staff-profile.php" class="btn mt-3 bg-light">Edit Profile</a>
            <a href="staff-logout.php" class="btn text-danger mt-3 d-block bg-light">Logout</a>
          </div>
          <div>
            <h5 class="mt-5">Calendar</h5>
            <iframe src="https://calendar.google.com/calendar/embed?mode=MONTH" frameborder="0" scrolling="no"></iframe>
          </div>
        </div>
      </div>
    </div>

    <!-- Main Content: Service Edit Form -->
    <main class="col-12 col-md-9">
      <div class="mb-4 p-3 border rounded shadow-sm main-title">
        <h2>Edit Service</h2>
      </div>
      <form method="POST">
        <div class="mb-3">
          <label for="service_name" class="form-label">Service Name:</label>
          <input type="text" name="service_name" id="service_name" class="form-control" value="<?= htmlspecialchars($service['service_name']) ?>">
        </div>
        <div class="mb-3">
          <label for="description" class="form-label">Description:</label>
          <textarea name="description" id="description" rows="4" class="form-control"><?= htmlspecialchars($service['description']) ?></textarea>
        </div>
        <button type="submit" class="btn mt-4 custom-btn">Update Service</button>
      </form>
      <p><a href="services-dashboard.php">← Back to Services</a></p>
    </main>
  </div>
</div>

<?php include 'footer.php'; ?>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>

<?php
} // end else GET
?>
