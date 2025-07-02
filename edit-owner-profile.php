<?php
/**
 * edit-owner-profile.php
 * 
 * Lets a logged-in property owner update their phone number and profile picture.
 * Features:
 *  - Loads current owner details via JOIN (users + owners)
 *  - Handles phone/profile picture update on POST (file upload and Cropper.js base64)
 *  - Stores images under /uploads/owner/{owner_id}_{name}/
 *  - Updates session to reflect new picture immediately
 * 
 * Dependencies:
 *  - check-user-session.php: Session and owner login check
 *  - db_connect.php: Provides $pdo PDO connection
 *  - Bootstrap 5: For form/UI styling
 */

session_start();
require_once 'check-user-session.php';
require 'db_connect.php';

// -----------------------------------------------------------------------------
$owner_id = $_SESSION['owner_id'];

// Fetch owner user data for the form (full_name needed for path, etc.)
$stmt = $pdo->prepare("
    SELECT u.user_id, u.full_name, u.phone_number, u.profile_picture 
    FROM users u
    JOIN owners o ON o.user_id = u.user_id 
    WHERE o.owner_id = ?
");
$stmt->execute([$owner_id]);
$user = $stmt->fetch();

if (!$user) die("User not found.");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = $_POST['phone_number'];
    $picture = $_FILES['profile_picture'] ?? null;
    $profilePicPath = $user['profile_picture']; // Default: existing

    // Build/ensure unique upload folder for this owner
    $cleanName = preg_replace('/[^a-zA-Z0-9]/', '_', $user['full_name']);
    $ownerFolder = "uploads/owner/{$owner_id}_{$cleanName}/";
    if (!is_dir($ownerFolder)) mkdir($ownerFolder, 0777, true);

    // 1. Handle file upload (if any)
    if ($picture && !empty($picture['name']) && $picture['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($picture['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) $ext = 'jpg';
        $profilePicPath = $ownerFolder . 'profile.' . $ext;
        move_uploaded_file($picture['tmp_name'], $profilePicPath);
    }

    // 2. Handle cropped image (from Cropper.js)
    if (!empty($_POST['cropped_image'])) {
        $croppedData = $_POST['cropped_image'];
        if (preg_match('/^data:image\/(\w+);base64,/', $croppedData, $type)) {
            $croppedData = substr($croppedData, strpos($croppedData, ',') + 1);
            $croppedData = base64_decode($croppedData);

            $ext = $type[1]; // jpg, png, etc.
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) $ext = 'jpg';
            $profilePicPath = $ownerFolder . 'profile.' . $ext;
            file_put_contents($profilePicPath, $croppedData);
        }
    }

    // Update users table with new info
    $stmt = $pdo->prepare("UPDATE users SET phone_number = ?, profile_picture = ? WHERE user_id = ?");
    $stmt->execute([$phone, $profilePicPath, $user['user_id']]);

    $_SESSION['profile_picture'] = $profilePicPath;

    header("Location: owner-profile.php?updated=1");
    exit();
}

// Set profile pic for preview (on GET)
$pic = (!empty($user['profile_picture']) && file_exists($user['profile_picture']))
    ? $user['profile_picture']
    : 'images/default.png';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Owner Profile - TREA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css?v=<?= time() ?>">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css" rel="stylesheet"/>
</head>
<body class="d-flex flex-column min-vh-100 bg-light">
    <?php include 'header.php'; ?>

<div class="container-fluid flex-grow-1 py-4">
    <div class="row">

    <main class="col-12 col-md-11 ms-lg-5">

        <div class="row justify-content-center">
            <div class="col-12 col-sm-10 col-md-8 col-lg-6 p-4 border rounded shadow-sm bg-white">
                <div class="mb-4">
                    <h2>Edit Your Profile</h2>
                </div>
                <form method="POST" enctype="multipart/form-data" autocomplete="off">
                    <!-- Phone Number Input -->
                    <div class="mb-3">
                        <label for="phone_number" class="form-label">Phone Number:</label>
                        <input type="text" class="form-control" name="phone_number" id="phone_number" value="<?= htmlspecialchars($user['phone_number']) ?>">
                    </div>
                    <!-- Profile Picture Preview and Upload -->
                    <div class="mb-3">
                        <label for="profile_picture" class="form-label">Profile Picture:</label>
                        <div class="d-flex justify-content-center">            
                            <div class="profile-pic-wrapper mt-2">
                                <img id="preview" src="<?= htmlspecialchars($pic) ?>">
                            </div>
                        </div>
                        <input type="file" id="profile_picture" name="profile_picture" class="form-control mt-2" accept="image/*">

                        <div class="mt-3 text-center">
                            <button type="button" id="crop-btn" class="btn" style="display:none; background-color: rgb(218,137,137); color: #fff;">Crop Image</button>
                            <button type="button" id="crop-done-btn" class="btn" style="display:none; background-color: rgb(218,137,137); color: #fff;">Done</button>
                        </div>
                        <input type="hidden" id="cropped_image" name="cropped_image">
                    </div>

                    <button type="submit" class="btn btn-success">Save Changes</button>
                    <a href="owner-profile.php" class="btn btn-secondary">Cancel</a>
                </form>
            </div>
        </div>
    </main>
    </div>
    </div>

    <?php include 'footer.php'; ?>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>
    <script>
    let cropper;
    let img = document.getElementById('preview');
    const fileInput = document.getElementById('profile_picture');
    const cropBtn = document.getElementById('crop-btn');
    const doneBtn = document.getElementById('crop-done-btn');
    const hiddenCropped = document.getElementById('cropped_image');
    let currentObjectURL = null;

    // On file input change: preview image, enable crop button
    fileInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (!file) {
            cropBtn.style.display = "none";
            doneBtn.style.display = "none";
            hiddenCropped.value = '';
            if (cropper) { cropper.destroy(); cropper = null; }
            if (currentObjectURL) { URL.revokeObjectURL(currentObjectURL); currentObjectURL = null; }
            return;
        }
        // Remove previous crop
        hiddenCropped.value = '';
        cropBtn.style.display = "inline-block";
        doneBtn.style.display = "none";
        if (cropper) { cropper.destroy(); cropper = null; }
        if (currentObjectURL) { URL.revokeObjectURL(currentObjectURL); currentObjectURL = null; }

        currentObjectURL = URL.createObjectURL(file);
        img.src = currentObjectURL;
    });

    // Crop button: start Cropper.js
    cropBtn.addEventListener('click', function() {
        if (cropper) cropper.destroy();
        cropper = new Cropper(img, {
            aspectRatio: 1,
            viewMode: 1,
            autoCropArea: 1,
            dragMode: 'move',
            background: false,
            guides: false,
            movable: true,
            zoomable: true,
            rotatable: false,
            scalable: false,
            cropBoxMovable: true,
            cropBoxResizable: false,
            ready() {
                // Make cropping area circular
                document.querySelector('.cropper-crop-box').style.borderRadius = '50%';
            }
        });
        doneBtn.style.display = "inline-block";
        cropBtn.style.display = "none";
    });

    // Done button: finalize cropping
    doneBtn.addEventListener('click', function() {
        if (cropper) {
            const canvas = cropper.getCroppedCanvas({ width: 300, height: 300 });
            const croppedDataUrl = canvas.toDataURL('image/jpeg');
            img.src = croppedDataUrl;
            hiddenCropped.value = croppedDataUrl;
            doneBtn.style.display = "none";
            cropBtn.style.display = "inline-block";
            cropper.destroy();
            cropper = null;
            if (currentObjectURL) {
                URL.revokeObjectURL(currentObjectURL);
                currentObjectURL = null;
            }
        }
    });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>

<!-- Script to close main navbar on small screen-->
<script src="navbar-close.js?v=1"></script>

</body>
</html>
