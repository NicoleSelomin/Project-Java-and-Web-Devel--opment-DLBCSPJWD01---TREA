<?php
/*
|--------------------------------------------------------------------------
| request-rental-property-management.php
|--------------------------------------------------------------------------
| Owner Application Form: Rental Property Management Service
| - Property owners use this to submit properties for managed rental.
| - Handles post-login auto-submit, sticky values, and form errors.
| - Bootstrap 5.3.6, responsive, and accessible UI.
| - Clean, well-commented, and minimal structure—ready for production.
|--------------------------------------------------------------------------
*/

session_start();
require_once 'check-user-session.php';

// Handle auto-submit after login/signup with form data restoration
if (!empty($_SESSION['auto_submit'])) {
    $_POST  = $_SESSION['pending_form_data'] ?? $_POST;
    $_FILES = $_SESSION['pending_files'] ?? $_FILES;
    unset($_SESSION['auto_submit'], $_SESSION['pending_form_data'], $_SESSION['pending_files']);
    require 'submit-rental-management.php';
    exit();
}

// Sticky form and error message handling
$old   = $_SESSION['form_data'] ?? [];
$error = $_SESSION['form_error'] ?? '';
unset($_SESSION['form_data'], $_SESSION['form_error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Rental Property Management Application</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css?v=<?= time() ?>">
</head>
<body class="d-flex flex-column min-vh-100 bg-light text-dark">

<?php include 'header.php'; ?>

<main class="container py-5 flex-grow-1">
    <!-- Title and Error -->
    <div class="mb-4 p-3 border rounded shadow-sm main-title">
        <h2>Rental Property Management Application</h2>
    </div>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Rental Management Application Form -->
    <form action="submit-rental-management.php" method="POST" enctype="multipart/form-data"
          class="needs-validation" novalidate>
        <!-- Property Details -->
        <div class="mb-3">
            <label class="form-label">Property Name</label>
            <input type="text" name="property_name" class="form-control" value="<?= htmlspecialchars($old['property_name'] ?? '') ?>" required>
            <div class="invalid-feedback">This field is required.</div>
        </div>
        <div class="mb-3">
            <label class="form-label">Location</label>
            <input type="text" name="location" class="form-control" value="<?= htmlspecialchars($old['location'] ?? '') ?>" required>
            <div class="invalid-feedback">This field is required.</div>
        </div>
        <div class="mb-3">
            <label class="form-label">Property Type</label>
            <select class="form-select" name="property_type" required>
                <option value="">-- Select Type --</option>
                <option value="House"     <?= ($old['property_type'] ?? '') == 'House'     ? 'selected' : '' ?>>House</option>
                <option value="Land"      <?= ($old['property_type'] ?? '') == 'Land'      ? 'selected' : '' ?>>Land</option>
                <option value="Apartment" <?= ($old['property_type'] ?? '') == 'Apartment' ? 'selected' : '' ?>>Apartment</option>
                <option value="Office"    <?= ($old['property_type'] ?? '') == 'Office'    ? 'selected' : '' ?>>Office</option>
                <option value="Other"     <?= ($old['property_type'] ?? '') == 'Other'     ? 'selected' : '' ?>>Other</option>
            </select>
            <div class="invalid-feedback">This field is required.</div>
        </div>
        <div class="mb-3">
            <label class="form-label">Property Description</label>
            <textarea name="property_description" class="form-control" rows="3" required><?= htmlspecialchars($old['property_description'] ?? '') ?></textarea>
            <div class="invalid-feedback">This field is required.</div>
        </div>
        <!-- Optional property attributes -->
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
        <div class="mb-3">
            <label class="form-label">Land Size (sq m)</label>
            <input type="text" name="land_size" class="form-control" value="<?= htmlspecialchars($old['land_size'] ?? '') ?>">
        </div>
        <!-- Upload fields -->
        <div class="mb-3">
            <label class="form-label">Image of the Property <span class="text-danger">*</span></label>
            <input type="file" class="form-control" name="property_image" accept="image/*" required>
            <div class="invalid-feedback">This field is required.</div>
        </div>
        <div class="mb-3">
            <label class="form-label">Ownership Proof (Required)</label>
            <input type="file" name="ownership_proof" class="form-control" required>
            <div class="invalid-feedback">This field is required.</div>
        </div>
        <div class="mb-3">
            <label class="form-label">Upload Additional Documents (optional)</label>
            <input type="file" name="additional_documents[]" class="form-control" multiple>
        </div>
        <!-- Rental/Lease Information -->
        <div class="mb-3">
            <label class="form-label">Tenancy History (optional)</label>
            <textarea name="tenancy_history" class="form-control" rows="2"><?= htmlspecialchars($old['tenancy_history'] ?? '') ?></textarea>
        </div>
        <div class="mb-3">
            <label class="form-label">Expected Monthly Rent</label>
            <input type="number" name="rental_expectation" class="form-control" step="0.01" value="<?= htmlspecialchars($old['rental_expectation'] ?? '') ?>">
        </div>
        <div class="mb-3">
            <label class="form-label">Proposed Lease Terms (optional)</label>
            <textarea name="lease_terms" class="form-control" rows="2"><?= htmlspecialchars($old['lease_terms'] ?? '') ?></textarea>
        </div>
        <div class="mb-3">
            <label class="form-label">Additional Comments (optional)</label>
            <textarea name="comments" class="form-control" rows="2"><?= htmlspecialchars($old['comments'] ?? '') ?></textarea>
        </div>
        <!-- Urgency and Service Level -->
        <div class="form-check mb-3">
            <input type="checkbox" name="urgent_rental" class="form-check-input" id="urgentCheck" <?= !empty($old['urgent_rental']) ? 'checked' : '' ?>>
            <label class="form-check-label" for="urgentCheck">This is an urgent request</label>
        </div>
        <div class="mb-3">
            <label class="form-label">Service Level</label>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="service_level" id="level1" value="management_only" required <?= ($old['service_level'] ?? '') == 'management_only' ? 'checked' : '' ?>>
                <label class="form-check-label" for="level1">
                    Management Only (no maintenance or tax handling)
                </label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="service_level" id="level2" value="management_maintenance" <?= ($old['service_level'] ?? '') == 'management_maintenance' ? 'checked' : '' ?>>
                <label class="form-check-label" for="level2">
                    Management + Maintenance
                </label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="service_level" id="level3" value="full_service" <?= ($old['service_level'] ?? '') == 'full_service' ? 'checked' : '' ?>>
                <label class="form-check-label" for="level3">
                    Full Service (Management + Maintenance + Tax Handling)
                </label>
            </div>
            <div class="invalid-feedback">Please select a service level.</div>
        </div>
        <!-- Instructions & Actions -->
        <div class="alert alert-info">
            Once submitted, an invoice will be generated. Wait for the invoice before making payment.
        </div>
        <p class="text-muted">Please pay the application fee once you see the invoice to process your application.</p>
        <div class="d-flex justify-content-center gap-3">
            <button type="submit" class="btn custom-btn">Submit Application</button>
            <a href="owner-profile.php" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</main>

<?php include 'footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Bootstrap client-side validation
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
</body>
</html>
