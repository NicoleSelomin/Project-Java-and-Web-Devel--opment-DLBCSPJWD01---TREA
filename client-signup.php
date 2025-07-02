<?php

/**
 * ============================================================================
 * client-signup.php
 * ----------------------------------------------------------------------------
 * TREA client Signup Page
 * ----------------------------------------------------------------------------
 * Allows new clients to register as either a "Client".
 * - Responsive, Bootstrap 5.3.6-based layout for all screens
 * - Presents two signup roles; only reveals the form after selection
 * - Accepts full name, email, phone, profile picture, and password
 * - Preserves redirect destination after signup if provided
 * - Displays Bootstrap alerts for backend error/success (via $_SESSION)
 * ----------------------------------------------------------------------------
 */

session_start();
// Handle redirect after signup
if (isset($_GET['redirect'])) {
    $_SESSION['redirect_after_login'] = $_GET['redirect'];
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
    <!-- Cropper.js CSS to resize the profile picture-->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css" rel="stylesheet"/>
</head>
<body class="d-flex flex-column min-vh-100 bg-light">

<!-- Include site-wide header -->
<?php include 'header.php'; ?>
<div class="container py-5 flex-grow-1">
    <div class="row justify-content-center">
        <div class="col-12 col-sm-10 col-md-8 col-lg-6 p-4 border rounded shadow-sm bg-white">
            <div class="mb-4 p-3 border rounded shadow-sm main-title">
                <h2 class="mb-2">Sign Up as a Client</h2>
                <p>Fill in your details to create a TREA client account.</p>
            </div>
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
            <?php endif; ?>
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
            <?php endif; ?>
            <form action="submit-client-signup.php" method="POST" enctype="multipart/form-data" autocomplete="off">
                <input type="hidden" name="user_type" value="client">
                <div class="mb-3">
                    <label class="form-label">Full Name <span class="text-danger">*</span></label>
                    <input type="text" name="full_name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Email Address <span class="text-danger">*</span></label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Phone Number <span class="text-danger">*</span></label>
                    <input type="tel" name="phone_number" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Residential Address <span class="text-danger">*</span></label>
                    <input type="text" name="address" class="form-control" required>
                </div>
                <!-- Password -->
                 <div class="mb-3">
                    <label class="form-label">Create Password <span class="text-danger">*</span></label>
                    <input type="password" name="password" id="signup-password" class="form-control" required>
                </div>
                <!-- Toggle to show/hide password -->
                 <div class="form-check mb-3">
                    <input type="checkbox" id="toggle-password-signup" class="form-check-input">
                    <label class="form-check-label" for="toggle-password-signup">Show Password</label>
                </div>
                <!-- Profile Picture -->             
                 <div class="mb-3">              
                    <label class="form-label">Profile Picture</label>
                    <input type="file" name="profile_picture" id="profile_picture" class="form-control" accept="image/*">
                    <div class="d-flex justify-content-center">
                        <div class="profile-pic-wrapper mt-4" style="width: 200px; height: 200px; border-radius: 50%; overflow: hidden; border: 1px solid #C154C1; margin-bottom:1rem;">
                            <img id="preview" src="placeholder.jpg" style="width:100%;height:100%;object-fit:cover;display:none;">
                        </div>
                    </div>
                    <div class="mt-2 text-center">
                        <button type="button" id="crop-btn" class="btn" style="display:none; background-color: rgb(218, 137, 137);">Crop Image</button>
                        <button type="button" id="crop-done-btn" class="btn" style="display:none; background-color: rgb(218, 137, 137);">Done</button>
                    </div>
                    <input type="hidden" id="cropped_image" name="cropped_image">
                </div>
            </div>
                <button type="submit" class="btn custom-btn w-100">Sign Up</button>
            </form>
            <div class="mt-4 text-center">
                <span>Already have an account? <a href="user-login.php">Login here</a></span>
            </div>
        </div>
    </div>
</div>
<?php include 'footer.php'; ?>

<!-- Password show/hide JS -->
<script>
  document.addEventListener("DOMContentLoaded", function () {
    const togglePasswordSignup = document.getElementById('toggle-password-signup');
    const passwordFieldSignup = document.getElementById('signup-password');
    if (togglePasswordSignup && passwordFieldSignup) {
      togglePasswordSignup.addEventListener('change', function() {
        passwordFieldSignup.type = this.checked ? 'text' : 'password';
      });
    }
  });
</script>

<script>
  // Toggle button label ("Read more"/"Read less") for each news article
  document.querySelectorAll('.toggle-btn').forEach(btn => {
    const targetId = btn.getAttribute('data-bs-target');
    const target = document.querySelector(targetId);

    if (!target) return;
    target.addEventListener('shown.bs.collapse', () => {
      btn.textContent = 'Read less';
    });
    target.addEventListener('hidden.bs.collapse', () => {
      btn.textContent = 'Read more';
    });
  });
</script>

<!-- Cropper.js JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>

<!-- Preview and crop images -->
<script>
let cropper;
let img = document.getElementById('preview');
const fileInput = document.getElementById('profile_picture');
const cropBtn = document.getElementById('crop-btn');
const doneBtn = document.getElementById('crop-done-btn');
const hiddenCropped = document.getElementById('cropped_image');

let currentObjectURL = null;

// Show preview only on upload
fileInput.addEventListener('change', function(e) {
  const file = e.target.files[0];
  if (!file) {
    img.style.display = "none";
    cropBtn.style.display = "none";
    doneBtn.style.display = "none";
    hiddenCropped.value = '';
    if (cropper) { cropper.destroy(); cropper = null; }
    if (currentObjectURL) { URL.revokeObjectURL(currentObjectURL); currentObjectURL = null; }
    return;
  }

  hiddenCropped.value = '';
  img.style.display = "block";
  cropBtn.style.display = "inline-block";
  doneBtn.style.display = "none";

  if (cropper) { cropper.destroy(); cropper = null; }
  if (currentObjectURL) { URL.revokeObjectURL(currentObjectURL); currentObjectURL = null; }

  currentObjectURL = URL.createObjectURL(file);
  img.src = currentObjectURL;
});

// Crop button initializes cropper
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
      // Hide square crop box border for a circular look
      document.querySelector('.cropper-crop-box').style.borderRadius = '50%';
    }
  });
  doneBtn.style.display = "inline-block";
  cropBtn.style.display = "none";
});

// Done button finalizes crop
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

<!-- Script to close main navbar on small screen-->
<script src="navbar-close.js?v=1"></script>

<!-- Bootstrap 5.3.6 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js" integrity="sha384-j1CDi7MgGQ12Z7Qab0qlWQ/Qqz24Gc6BM0thvEMVjHnfYGF0rmFCozFSxQBxwHKO" crossorigin="anonymous"></script>
</body>
</html>
