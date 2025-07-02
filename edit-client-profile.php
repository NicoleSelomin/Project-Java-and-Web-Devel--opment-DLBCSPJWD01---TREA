<?php
/**
 * edit-client-profile.php
 *
 * Lets a logged-in client update their phone number and profile picture.
 * - Loads current client data (JOIN users + clients)
 * - Handles file upload and/or Cropper.js base64 image for profile picture
 * - Stores profile pictures in /uploads/clients/{client_id}_{name}/
 * - Updates session variable for instant feedback
 */

session_start();
require_once 'check-user-session.php';
require 'db_connect.php';

// Load client data
$client_id = $_SESSION['client_id'];
$stmt = $pdo->prepare("
    SELECT u.user_id, u.full_name, u.phone_number, u.profile_picture 
    FROM users u
    JOIN clients c ON c.user_id = u.user_id 
    WHERE c.client_id = ?
");
$stmt->execute([$client_id]);
$user = $stmt->fetch();

if (!$user) die("User not found.");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = $_POST['phone_number'];
    $picture = $_FILES['profile_picture'] ?? null;
    $profilePicPath = $user['profile_picture'];

    // Build upload directory: /uploads/clients/{client_id}_{name}/
    $cleanName = preg_replace('/[^a-zA-Z0-9]/', '_', $user['full_name']);
    $clientFolder = "uploads/clients/{$client_id}_{$cleanName}/";
    if (!is_dir($clientFolder)) mkdir($clientFolder, 0755, true);

    // 1. File upload (if any)
    if ($picture && !empty($picture['name']) && $picture['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($picture['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) $ext = 'jpg';
        $profilePicPath = $clientFolder . 'profile.' . $ext;
        move_uploaded_file($picture['tmp_name'], $profilePicPath);
    }

    // 2. Cropped image from Cropper.js (overrides above)
    if (!empty($_POST['cropped_image'])) {
        $croppedData = $_POST['cropped_image'];
        if (preg_match('/^data:image\/(\w+);base64,/', $croppedData, $type)) {
            $croppedData = substr($croppedData, strpos($croppedData, ',') + 1);
            $croppedData = base64_decode($croppedData);
            $ext = $type[1];
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) $ext = 'jpg';
            $profilePicPath = $clientFolder . 'profile.' . $ext;
            file_put_contents($profilePicPath, $croppedData);
        }
    }

    // Update users table with new info
    $stmt = $pdo->prepare("UPDATE users SET phone_number = ?, profile_picture = ? WHERE user_id = ?");
    $stmt->execute([$phone, $profilePicPath, $user['user_id']]);

    $_SESSION['profile_picture'] = $profilePicPath;

    header("Location: client-profile.php?updated=1");
    exit();
}

// Show current picture or fallback to default
$pic = (!empty($user['profile_picture']) && file_exists($user['profile_picture']))
    ? $user['profile_picture']
    : 'images/default.png';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Client Profile - TREA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css?v=<?= time() ?>">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css" rel="stylesheet"/>
</head>
<body class="d-flex flex-column min-vh-100 bg-light">
    <?php include 'header.php'; ?>

    <main class="container py-5 flex-grow-1">
        <div class="row justify-content-center">
            <div class="col-12 col-sm-10 col-md-8 col-lg-6 p-4 border rounded shadow-sm bg-white">

                <div class="mb-4">
                    <h2>Edit Your Profile</h2>
                </div>

                <form method="POST" enctype="multipart/form-data" autocomplete="off">
                    <div class="mb-3">
                        <label for="phone_number" class="form-label">Phone Number:</label>
                        <input type="text" class="form-control" name="phone_number" id="phone_number" value="<?= htmlspecialchars($user['phone_number'] ?? '') ?>">
                    </div>

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
                    <a href="client-profile.php" class="btn btn-secondary">Cancel</a>
                </form>
            </div>
        </div>
    </main>

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
