<?php
/*
|--------------------------------------------------------------------------
| request-brokerage.php
|--------------------------------------------------------------------------
| Property Owner Application Form: Brokerage Service
| Allows owners to request brokerage (sale or rental) services.
| - Handles auto-submit after login/signup (with form data restoration)
| - Client-side validation and clear error feedback
| - Bootstrap 5.3.6, clean and responsive
|--------------------------------------------------------------------------
*/

session_start();
require_once 'check-user-session.php';

// Clear old error state (if any)
unset($_SESSION['form_error']);

// Handle auto-submit if redirected after login/signup
if (!empty($_SESSION['auto_submit'])) {
    $_POST  = $_SESSION['pending_form_data'] ?? $_POST;
    $_FILES = $_SESSION['pending_files'] ?? $_FILES;
    unset($_SESSION['auto_submit'], $_SESSION['pending_form_data'], $_SESSION['pending_files']);
    require 'submit-brokerage.php';
    exit();
}

// Prepare error and old form data for sticky forms
$old   = $_SESSION['form_data'] ?? [];
$error = $_SESSION['form_error'] ?? '';
unset($_SESSION['form_data'], $_SESSION['form_error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Brokerage Service Application</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css?v=<?= time() ?>">
</head>
<body class="d-flex flex-column min-vh-100 bg-light text-dark">

<?php include 'header.php'; ?>

<main class="container py-5 flex-grow-1">
    <!-- Title & Error -->
    <div class="mb-4 p-3 border rounded shadow-sm main-title">
        <h2>Brokerage Service Application</h2>
    </div>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Brokerage Application Form -->
    <form action="submit-brokerage.php" method="POST" enctype="multipart/form-data"
          class="needs-validation" novalidate>

        <!-- Property Name -->
        <div class="mb-3">
            <label class="form-label">Property Name</label>
            <input type="text" name="property_name" class="form-control" value="<?= htmlspecialchars($old['property_name'] ?? '') ?>" required>
            <div class="invalid-feedback">This field is required.</div>
        </div>
        <!-- Location -->
        <div class="mb-3">
            <label class="form-label">Location</label>
            <input type="text" name="location" class="form-control" value="<?= htmlspecialchars($old['location'] ?? '') ?>" required>
            <div class="invalid-feedback">This field is required.</div>
        </div>
        <!-- Property Type -->
        <div class="mb-3">
            <label class="form-label">Property Type</label>
            <select name="property_type" class="form-select" required>
                <option value="">-- Select --</option>
                <option value="house" <?= ($old['property_type'] ?? '') == 'house' ? 'selected' : '' ?>>House</option>
                <option value="land" <?= ($old['property_type'] ?? '') == 'land' ? 'selected' : '' ?>>Land</option>
                <option value="apartment" <?= ($old['property_type'] ?? '') == 'apartment' ? 'selected' : '' ?>>Apartment</option>
                <option value="office" <?= ($old['property_type'] ?? '') == 'office' ? 'selected' : '' ?>>Office</option>
                <option value="other" <?= ($old['property_type'] ?? '') == 'other' ? 'selected' : '' ?>>Other</option>
            </select>
            <div class="invalid-feedback">This field is required.</div>
        </div>
        <!-- Brokerage Purpose -->
        <div class="mb-3">
            <label class="form-label">Are you looking for clients to:</label>
            <select name="brokerage_purpose" class="form-select" required>
                <option value="">-- Select --</option>
                <option value="rent" <?= ($old['brokerage_purpose'] ?? '') == 'rent' ? 'selected' : '' ?>>Rent the Property</option>
                <option value="sale" <?= ($old['brokerage_purpose'] ?? '') == 'sale' ? 'selected' : '' ?>>Sell the Property</option>
            </select>
            <div class="invalid-feedback">This field is required.</div>
        </div>
        <!-- Property Description -->
        <div class="mb-3">
            <label class="form-label">Property Description</label>
            <textarea name="property_description" class="form-control" required><?= htmlspecialchars($old['property_description'] ?? '') ?></textarea>
            <div class="invalid-feedback">This field is required.</div>
        </div>
        <!-- Bedrooms, Bathrooms, Floor Count -->
        <div class="row">
            <div class="col-md-4 mb-3">
                <label class="form-label">Number of Bedrooms</label>
                <input type="number" name="number_of_bedrooms" class="form-control" value="<?= htmlspecialchars($old['number_of_bedrooms'] ?? '') ?>">
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">Number of Bathrooms</label>
                <input type="number" name="number_of_bathrooms" class="form-control" value="<?= htmlspecialchars($old['number_of_bathrooms'] ?? '') ?>">
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">Floor Count</label>
                <input type="number" name="floor_count" class="form-control" value="<?= htmlspecialchars($old['floor_count'] ?? '') ?>">
            </div>
        </div>
        <!-- Land Size -->
        <div class="mb-3">
            <label class="form-label">Land Size (sq m)</label>
            <input type="text" name="land_size" class="form-control" value="<?= htmlspecialchars($old['land_size'] ?? '') ?>">
        </div>
        <!-- Estimated Price -->
        <div class="mb-3">
            <label class="form-label">Estimated Price (CFA)</label>
            <input type="number" name="estimated_price" step="0.01" class="form-control" value="<?= htmlspecialchars($old['estimated_price'] ?? '') ?>" required>
            <div class="invalid-feedback">This field is required.</div>
        </div>
        <!-- Reason for Sale/Rental -->
        <div class="mb-3">
            <label class="form-label">Reason for Sale/Rental (optional)</label>
            <textarea name="reason_for_sale" class="form-control"><?= htmlspecialchars($old['reason_for_sale'] ?? '') ?></textarea>
        </div>
        <!-- Property Image -->
        <div class="mb-3">
            <label class="form-label">Upload Property Image <span class="text-danger">*</span></label>
            <input type="file" name="property_image" class="form-control" accept="image/*" required>
            <div class="invalid-feedback">This field is required.</div>
        </div>
        <!-- Additional Documents -->
        <div class="mb-3">
            <label class="form-label">Upload Additional Documents (optional)</label>
            <input type="file" name="additional_documents[]" class="form-control" multiple>
        </div>
        <!-- Urgent Sale/Rent Checkbox -->
        <div class="form-check mb-3">
            <input type="checkbox" name="urgent" class="form-check-input" id="urgentCheck" <?= !empty($old['urgent']) ? 'checked' : '' ?>>
            <label class="form-check-label" for="urgentCheck">This is an urgent request</label>
        </div>
        <!-- Comments -->
        <div class="mb-3">
            <label class="form-label">Additional Comments</label>
            <textarea name="comments" class="form-control"><?= htmlspecialchars($old['comments'] ?? '') ?></textarea>
        </div>
        <!-- Instructions -->
        <div class="alert alert-info">
            <strong>Note:</strong> Once submitted, an invoice will be generated. Please wait for the invoice before making any payment.
        </div>
        <p class="text-muted">Please pay the application fee once you see the invoice to have your application processed.</p>
        <!-- Form Actions -->
        <div class="d-flex justify-content-center gap-3">
            <button type="submit" class="btn custom-btn">Submit Application</button>
            <a href="owner-profile.php" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</main>

<?php include 'footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Client-side Bootstrap validation
(function () {
    'use strict';
    var forms = document.querySelectorAll('.needs-validation');
    Array.prototype.slice.call(forms).forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
})();
</script>

<!-- Script to close main navbar on small screen-->
<script src="navbar-close.js?v=1"></script>

</body>
</html>
