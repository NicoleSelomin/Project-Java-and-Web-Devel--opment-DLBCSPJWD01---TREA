<?php
session_start();
require_once 'check-user-session.php';

$old   = $_SESSION['form_data'] ?? [];
$error = $_SESSION['form_error'] ?? '';
unset($_SESSION['form_data'], $_SESSION['form_error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Rental Property Management Application</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css?v=<?= time() ?>">
    <style>
      .remove-property-btn { position:absolute; top:0; right:0; }
      .property-section { position:relative; padding:1.5rem 1.5rem 1rem 1.5rem; border:1px solid #ccc; border-radius:1rem; margin-bottom:2rem; background:#fff; }
      .property-section:not(:first-child) { margin-top:2rem; }
    </style>
</head>
<body class="d-flex flex-column min-vh-100 bg-light text-dark">
<?php include 'header.php'; ?>

<main class="container py-5 flex-grow-1">
    <div class="mb-4 p-3 border rounded shadow-sm main-title">
        <h2>Rental Property Management Application</h2>
        <p>You can submit multiple properties in a single application.</p>
    </div>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form action="submit-rental-management.php" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate id="multi-property-form">
        <div id="property-list">
          <!-- Property Section Template (first one) -->
          <div class="property-section">
            <button type="button" class="btn btn-danger btn-sm remove-property-btn d-none">Remove</button>
            <div class="mb-3">
                <label class="form-label">Property Name</label>
                <input type="text" name="properties[0][property_name]" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Location</label>
                <input type="text" name="properties[0][location]" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Property Type</label>
                <select class="form-select" name="properties[0][property_type]" required>
                    <option value="">-- Select Type --</option>
                    <option value="House">House</option>
                    <option value="Land">Land</option>
                    <option value="Apartment">Apartment</option>
                    <option value="Office">Office</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Property Description</label>
                <textarea name="properties[0][property_description]" class="form-control" rows="3" required></textarea>
            </div>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Number of Bedrooms</label>
                    <input type="number" name="properties[0][number_of_bedrooms]" class="form-control">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Number of Bathrooms</label>
                    <input type="number" name="properties[0][number_of_bathrooms]" class="form-control">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Floor Count</label>
                    <input type="number" name="properties[0][floor_count]" class="form-control">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Use for the property</label>
                <input type="text" name="properties[0][use_for_the_property]" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Land Size (sq m)</label>
                <input type="text" name="properties[0][land_size]" class="form-control">
            </div>
            <div class="mb-3">
                <label class="form-label">Image of the Property <span class="text-danger">*</span></label>
                <input type="file" class="form-control" name="properties[0][property_image]" accept="image/*" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Ownership Proof (Required)</label>
                <input type="file" name="properties[0][ownership_proof]" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Upload Additional Documents (optional)</label>
                <input type="file" name="properties[0][additional_documents][]" class="form-control" multiple>
            </div>
            <div class="mb-3">
                <label class="form-label">Tenancy History (optional)</label>
                <textarea name="properties[0][tenancy_history]" class="form-control" rows="2"></textarea>
            </div>
            <div class="mb-3">
                <label class="form-label">Expected Monthly Rent (required)</label>
                <input type="number" name="properties[0][rental_expectation]" class="form-control" step="0.01" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Proposed Lease Terms (Required)</label>
                <textarea name="properties[0][lease_terms]" class="form-control" rows="2" required></textarea>
            </div>
            <div class="mb-3">
                <label class="form-label">Additional Comments (optional)</label>
                <textarea name="properties[0][comments]" class="form-control" rows="2"></textarea>
            </div>
            <div class="mb-3">
                <label class="form-label">Service Level</label>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="properties[0][service_level]" id="level1_0" value="management_only" required>
                    <label class="form-check-label" for="level1_0">Management Only</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="properties[0][service_level]" id="level2_0" value="management_maintenance">
                    <label class="form-check-label" for="level2_0">Management + Maintenance</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="properties[0][service_level]" id="level3_0" value="full_service">
                    <label class="form-check-label" for="level3_0">Full Service (Management + Maintenance + Tax Handling)</label>
                </div>
            </div>
          </div>
        </div>
        <div class="mb-4">
            <button type="button" class="btn btn-secondary" id="add-property-btn">+ Add Another Property</button>
        </div>
        <!-- Urgency and Service Level: one per application -->
        <div class="form-check mb-3">
            <input type="checkbox" name="urgent" class="form-check-input" id="urgentCheck">
            <label class="form-check-label" for="urgentCheck">This is an urgent request</label>
        </div>        
        <div class="alert alert-info">
            Once submitted, an invoice will be generated. Wait for the invoice before making payment.
        </div>
        <div class="d-flex justify-content-center gap-3">
            <button type="submit" class="btn custom-btn">Submit Application</button>
            <a href="owner-profile.php" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Bootstrap validation
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
// Add/remove property section
let propertyIndex = 1;
document.getElementById('add-property-btn').addEventListener('click', function() {
    const propList = document.getElementById('property-list');
    const firstSection = propList.children[0];
    const clone = firstSection.cloneNode(true);
    // Reset inputs in cloned section & update all name/index/IDs
    Array.from(clone.querySelectorAll('input, textarea, select')).forEach(el => {
        // New names/IDs for this property
        if (el.name) el.name = el.name.replace(/\[\d+\]/, `[${propertyIndex}]`);
        if (el.id) el.id = el.id.replace(/\d+$/, propertyIndex);
        // Uncheck all radios/checkboxes, reset value
        if (el.type === 'checkbox' || el.type === 'radio') {
            el.checked = false;
        } else {
            el.value = '';
        }
    });
    // Update for unique IDs on radio/checkbox labels too
    clone.querySelectorAll('label[for]').forEach(lbl => {
        lbl.htmlFor = lbl.htmlFor.replace(/\d+$/, propertyIndex);
    });
    // Show remove button
    clone.querySelector('.remove-property-btn').classList.remove('d-none');
    // Add remove handler
    clone.querySelector('.remove-property-btn').onclick = function() {
        clone.remove();
    };
    propList.appendChild(clone);
    propertyIndex++;
});
</script>
<script src="navbar-close.js?v=1"></script>
</body>
</html>