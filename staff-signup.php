<?php
/**
 * ============================================================================
 * staff-signup.php — TREA Real Estate Platform
 * ------------------------------------------------
 * Staff registration page for TREA Platform.
 * 
 * - Handles new staff signup, including required file uploads.
 * - Checks for duplicate email and required fields.
 * - Creates user-specific upload directory.
 * - Saves all uploaded files to unique staff folder.
 * - Redirects to profile after success, or shows error.
 * - Uses consistent Bootstrap 5.3.6 layout.
 */

session_start();
require_once 'db_connect.php';

// Default: no error
$error = "";

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get and sanitize user input
    $name = trim($_POST['full_name'] ?? "");
    $email = filter_var($_POST['email'] ?? "", FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? "";
    $phone = trim($_POST['phone_number'] ?? "");
    $role = $_POST['role'] ?? "";

    // Preserve form values
    $old = [
        'full_name' => $name,
        'email' => $email,
        'phone_number' => $phone,
        'role' => $role
    ];

    // Required fields check (except files, which are checked on file array)
    if ($name && $email && $password && $phone && $role 
        && isset($_FILES['cv']) && $_FILES['cv']['error'] === UPLOAD_ERR_OK
        && isset($_FILES['recommendation_letter']) && $_FILES['recommendation_letter']['error'] === UPLOAD_ERR_OK
    ) {
        // Email must be unique
        $check = $pdo->prepare("SELECT COUNT(*) FROM staff WHERE email = ?");
        $check->execute([$email]);
        if ($check->fetchColumn() > 0) {
            $error = "Email already exists. Please use a different email.";
        } else {
            // Password hashing for security
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Insert staff (without file paths for now)
            $sql = "INSERT INTO staff (full_name, email, password, phone_number, role) VALUES (?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$name, $email, $hashed_password, $phone, $role]);

            // Prepare user folder for uploads
            $user_id = $pdo->lastInsertId();
            $cleanName = preg_replace('/[^a-zA-Z0-9]/', '_', $name);
            $folderName = $user_id . '_' . $cleanName;
            $staffFolder = "uploads/staff/$folderName/";

            if (!is_dir($staffFolder)) {
                mkdir($staffFolder, 0755, true);
            }

            // File upload handler: saves and returns path if upload is valid
            function handleUpload($field, $filename, $folder) {
                if (isset($_FILES[$field]) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
                    $ext = pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION);
                    $safeExt = preg_replace('/[^a-zA-Z0-9]/', '', $ext);
                    $path = $folder . $filename . ($safeExt ? '.' . $safeExt : '');
                    move_uploaded_file($_FILES[$field]['tmp_name'], $path);
                    return $path;
                }
                return null;
            }

            // Upload files (required and optional)
            $profilePicPath    = handleUpload('profile_picture', 'profile', $staffFolder);
            $cvPath            = handleUpload('cv', 'cv', $staffFolder);
            $recommendationPath= handleUpload('recommendation_letter', 'recommendation', $staffFolder);
            $bachelorPath      = handleUpload('bachelor_certificate', 'bachelor', $staffFolder);
            $masterPath        = handleUpload('master_certificate', 'master', $staffFolder);
            $otherDocPath      = handleUpload('other_documents', 'other_documents', $staffFolder);

            // Update staff with document file paths
            $update = $pdo->prepare("UPDATE staff SET 
                profile_picture = ?, 
                cv = ?, 
                recommendation_letter = ?, 
                bachelor_certificate = ?, 
                master_certificate = ?, 
                other_documents = ? 
                WHERE staff_id = ?");
            $update->execute([
                $profilePicPath, $cvPath, $recommendationPath,
                $bachelorPath, $masterPath, $otherDocPath,
                $user_id
            ]);

            // Session setup
            $_SESSION['staff_id'] = $user_id;
            $_SESSION['email'] = $email;
            $_SESSION['role'] = $role;
            $_SESSION['full_name'] = $name;
            $_SESSION['profile_picture'] = $profilePicPath;

            // Redirect to staff profile
            header("Location: staff-profile.php");
            exit();
        }
    } else {
        $error = "Please fill in all required fields and upload required documents.";
        // Keep $old to refill form fields
    }
} else {
    // Set $old as empty to prevent undefined index
    $old = ['full_name'=>'', 'email'=>'', 'phone_number'=>'', 'role'=>''];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Meta tags and Title -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Signup - TREA</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- TREA Custom Styles -->
    <link rel="stylesheet" href="styles.css?v=<?= time() ?>">
</head>
<body class="d-flex flex-column min-vh-100 bg-light">
    <?php include 'header.php'; ?>

    <main class="container py-5 flex-grow-1">
        <div class="row justify-content-center">
            <div class="col-12 col-sm-10 col-md-8 col-lg-6 p-4 border rounded shadow-sm bg-white">

                <div class="mb-4">
                    <h2 class="mb-0">Create Staff Account</h2>
                </div>

                <!-- Error output (if any) -->
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <!-- Signup Form -->
                <form id="signupForm" action="staff-signup.php" method="POST" enctype="multipart/form-data" novalidate>
                    <div class="mb-3">
                        <label class="form-label">Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="full_name" class="form-control" required
                            value="<?= htmlspecialchars($old['full_name']) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email Address <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control" required
                            value="<?= htmlspecialchars($old['email']) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone Number <span class="text-danger">*</span></label>
                        <input type="tel" name="phone_number" class="form-control" required
                            value="<?= htmlspecialchars($old['phone_number']) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role <span class="text-danger">*</span></label>
                        <select name="role" class="form-select" required>
                            <option value="">-- Select Role --</option>
                            <option value="General Manager" <?= $old['role']=="General Manager"?'selected':''; ?>>General Manager</option>
                            <option value="Accountant" <?= $old['role']=="Accountant"?'selected':''; ?>>Accountant</option>
                            <option value="Property Manager" <?= $old['role']=="Property Manager"?'selected':''; ?>>Property Manager</option>
                            <option value="Plan and Supervision Manager" <?= $old['role']=="Plan and Supervision Manager"?'selected':''; ?>>Plan and Supervision Manager</option>
                            <option value="Legal Officer" <?= $old['role']=="Legal Officer"?'selected':''; ?>>Legal Officer</option>
                            <option value="Field Agent" <?= $old['role']=="Field Agent"?'selected':''; ?>>Field Agent</option>
                            <option value="Board Member" <?= $old['role']=="Board Member"?'selected':''; ?>>Board Member</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Profile Picture</label>
                        <input type="file" name="profile_picture" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">CV <span class="text-danger">*</span></label>
                        <input type="file" name="cv" accept=".pdf" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Recommendation Letter <span class="text-danger">*</span></label>
                        <input type="file" name="recommendation_letter" accept=".pdf" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Bachelor Certificate</label>
                        <input type="file" name="bachelor_certificate" accept=".pdf" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Master Certificate</label>
                        <input type="file" name="master_certificate" accept=".pdf" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Other Documents</label>
                        <input type="file" name="other_documents" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Create Password <span class="text-danger">*</span></label>
                        <input type="password" name="password" id="signup-password" class="form-control" required>
                    </div>
                    <div class="form-check mb-3">
                        <input type="checkbox" class="form-check-input" id="toggle-password-signup">
                        <label class="form-check-label" for="toggle-password-signup">Show Password</label>
                    </div>
                    <button type="submit" name="submit" class="btn btn-primary w-100">Sign Up</button>
                </form>

                <p class="text-center mt-3 mb-0">
                    Already have an account?
                    <a href="staff-login.php">Login here</a>
                </p>
            </div>
        </div>
    </main>

    <?php include 'footer.php'; ?>

    <!-- Show/hide password JS -->
    <script>
        const togglePasswordSignup = document.getElementById('toggle-password-signup');
        const passwordFieldSignup = document.getElementById('signup-password');
        if (togglePasswordSignup && passwordFieldSignup) {
            togglePasswordSignup.addEventListener('change', function () {
                passwordFieldSignup.type = this.checked ? 'text' : 'password';
            });
        }
    </script>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
