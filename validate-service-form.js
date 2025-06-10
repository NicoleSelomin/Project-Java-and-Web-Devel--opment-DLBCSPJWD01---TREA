/**
 * validate-service-form.js
 *
 * -----------------------------------------
 * Client-side validation for service application forms on the TREA platform.
 * Ensures all required fields are filled before submission, using Bootstrap 5.3.6 validation styling.
 *
 * - Reads a comma-separated list of required fields from the form's `data-required` attribute.
 * - Highlights missing fields with Bootstrap's `is-invalid` class.
 * - Special handling for file uploads and payment sections (online/offline).
 * - Alerts user to missing information before form submission.
 *
 * Usage:
 *  - Include this file on any page with a service application form.
 *  - Set `data-required="field1,field2,field3"` on the `<form>`.
 *  - Required payment fields: `payment_method`, `payment_proof` (if offline).
 *
 * Structure and UI:
 *  - Fully compatible with Bootstrap 5.3.6.
 * -----------------------------------------
 */

document.addEventListener('DOMContentLoaded', () => {
  // Locate the first form on the page; exit if not found
  const form = document.querySelector('form');
  if (!form) return;

  form.addEventListener('submit', function (e) {
    /**
     * 1. Gather the list of required fields from the form's data-required attribute.
     *    Expects a comma-separated string, e.g., "full_name,email,phone".
     */
    const requiredFields = (form.dataset.required || "")
      .split(',')
      .map(f => f.trim())
      .filter(Boolean);

    let missing = []; // Track missing fields for error focus

    // 2. Remove any previous Bootstrap validation error classes
    requiredFields.forEach(name => {
      const field = form.elements[name];
      if (field) field.classList.remove('is-invalid');
    });

    /**
     * 3. Validate all required fields.
     *    - If a field is a file input, ensure at least one file is selected.
     *    - For other inputs, ensure value is not empty/whitespace.
     */
    requiredFields.forEach(name => {
      const field = form.elements[name];
      if (!field) return;

      // Support for radio/checkbox groups if needed in the future
      const isFile = field.type === 'file';
      const isMissing = isFile ? !field.files.length : !field.value.trim();

      if (isMissing) {
        missing.push(name);
        field.classList.add('is-invalid');
      }
    });

    /**
     * 4. Payment section validation:
     *    - payment_method: must be selected.
     *    - If payment_method is 'offline', payment_proof file is required.
     *    - Applies Bootstrap's is-invalid class and alerts as needed.
     */
    const paymentMethod = form.payment_method?.value;
    const paymentProof = form.payment_proof;

    if (!paymentMethod) {
      if (form.payment_method) form.payment_method.classList.add('is-invalid');
      alert("Please select a payment method.");
      e.preventDefault();
      return;
    }

    if (paymentMethod === 'offline' && (!paymentProof || !paymentProof.files.length)) {
      if (paymentProof) paymentProof.classList.add('is-invalid');
      alert("Please upload your payment proof.");
      e.preventDefault();
      return;
    }

    // 5. If any required fields are missing, alert and prevent submission
    if (missing.length) {
      alert("Please fill all required fields.");
      // Focus first missing field for user convenience
      form.elements[missing[0]]?.focus();
      e.preventDefault();
    }
  });
});
