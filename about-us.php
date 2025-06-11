<!--
|--------------------------------------------------------------------------
| about-us.php
|--------------------------------------------------------------------------
| TREA "About Us" Page
| - Responsive layout with Bootstrap 5.
| - Sidebar for a quick brand message and visual.
| - Main content: About section, Mission, Vision, Timeline, Team, and Policies.
| - Collapsible "Read more" sections for concise presentation.
| - Includes header and footer PHP includes for site-wide consistency.
|
| Instructions:
| - Edit sections as needed for company changes.
| - Sidebar image can be replaced.
| - Policy links must match existing pages.
|
-->

<!DOCTYPE html>
<html lang="en">
<head>
  <!-- Standard meta tags and Bootstrap 5 stylesheet for responsiveness -->
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>About Us - TREA</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-4Q6Gf2aSP4eDXB8Miphtr37CMZZQ5oXLH2yaXMJ2w8e2ZtHTl7GptT4jmndRuHDT" crossorigin="anonymous">
  <link rel="stylesheet" href="styles.css?v=<?= time() ?>">
</head>
<body class="text-light d-flex flex-column min-vh-100">

<?php include 'header.php'; ?>

<div class="container-fluid">
  <div class="row">
    <!-- Sidebar for brand message and image (collapsible on mobile) -->
    <div class="col-12 col-md-3 mb-3">
      <button class="btn btn-sm d-md-none animate-text custom-btn" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarCollapse" aria-expanded="false" aria-controls="sidebarCollapse">
        Open Sidebar
      </button>
      <div class="collapse d-md-block" id="sidebarCollapse">
        <div class="sidebar text-center">
          <h5 class="text-white">Shop your ideal property like you shop for your favorite food.</h5>
          <!-- Company illustration/logo -->
          <img src="images/about-us-image.png" alt="TREA About Us" class="mb-3 img-fluid">
          <p class="text-white small">YOUR TRUSTED REAL ESTATE AGENTS HERE TO PROVIDE YOU ONLY WITH THE BEST AND MOST AFFORDABLE</p>
        </div>
      </div>
    </div>

    <!-- Main page content -->
    <main class="col-12 col-md-9">
      <!-- Section: Page title -->
      <div class="text-center mb-5 px-3 px-md-0 border rounded shadow-sm main-title">
        <h1 class="fs-2 fs-md-1 fw-bold text-dark">Know more about us</h1>
      </div>

      <!-- Section: About -->
      <section class="mb-4 p-3 border rounded shadow-sm">
        <h2>About TREA</h2>
        <p class="text-dark">
          Trusted Real Estate Agency (TREA) is dedicated to offering high-quality real estate services including property management,
          legal documentation assistance, construction supervision,
          <span id="moreText1" class="collapse"> and architectural planning. We serve property owners and renters and buyers, providing efficient and secure property transactions.</span>
        </p>
        <div class="text-end">
          <!-- Button toggles the "read more" collapsed section -->
          <button class="btn btn-sm toggle-btn" type="button" data-bs-toggle="collapse" data-bs-target="#moreText1">Read more</button>
        </div>
      </section>

      <!-- Section: Mission and Vision -->
      <section class="mb-4 p-3 border rounded shadow-sm">
        <h3>Our Mission</h3>
        <p class="text-dark">
          To be the most reliable and innovative real estate agency, ensuring satisfaction and security for all
          stakeholders involved in property management and development.
        </p>
        <h3 class="mt-4">Our Vision</h3>
        <p class="text-dark">To empower property owners and clients with seamless and trustworthy real estate solutions across the region.</p>
      </section>

      <!-- Section: Timeline -->
      <section class="mb-4 p-3 border rounded shadow-sm">
        <h3>Our Timeline</h3>
        <ul class="text-dark">
          <li><strong>2023:</strong> TREA founded to transform the real estate industry.</li>
          <li><strong>2024:</strong> Successfully onboarded over 100 properties.</li>
          <span id="moreText3" class="collapse">
            <li><strong>2025:</strong> Launch of digital service platform for online applications and contracts.</li>
          </span>
        </ul>
        <div class="text-end">
          <!-- Toggle more/less timeline -->
          <button class="btn btn-sm toggle-btn" type="button" data-bs-toggle="collapse" data-bs-target="#moreText3">Read more</button>
        </div>
      </section>

      <!-- Section: Meet Our Team -->
      <section class="mb-4 p-3 border rounded shadow-sm">
        <h3>Meet Our Team</h3>
        <ul class="text-dark">
          <li><strong>General Manager:</strong> Oversees operations and decision-making.</li>
          <li><strong>Property Manager:</strong> Coordinates property listings and maintenance.</li>
          <span id="moreText4" class="collapse">
            <li><strong>Legal Officer:</strong> Ensures property documents and contracts comply with legal standards.</li>
            <li><strong>Accountant:</strong> Manages financial records and transactions.</li>
            <li><strong>Field Agents:</strong> Assist clients and inspect listed properties.</li>
          </span>
        </ul>
        <div class="text-end">
          <!-- Toggle more/less team roles -->
          <button class="btn btn-sm toggle-btn" type="button" data-bs-toggle="collapse" data-bs-target="#moreText4">Read more</button>
        </div>
      </section>

      <!-- Section: Policies (links to policy pages) -->
      <section class="mb-4 p-3 border rounded shadow-sm">
        <h3>TREA Policies</h3>
        <ul class="text-dark">
          <li><a href="privacy-policy.php"><strong class="text-light">Privacy policy</strong></a></li>
          <li><a href="terms-of-use.php"><strong class="text-light">Terms of use</strong></a></li>
          <li><a href="refund-and-cancellatio.php"><strong class="text-light">Refund and cancellation policy</strong></a></li>
          <li><a href="servive-request.php"><strong class="text-light">Service request policy</strong></a></li>
          <span id="moreText5" class="collapse">
            <li><a href="property-claim.php"><strong class="text-light">Property claim policy</strong></a></li>
            <li><a href="propery-listing.php"><strong class="text-light">Property listing policy</strong></a></li>
            <li><a href="property-managemnt.php"><strong class="text-light">Property management policy</strong></a></li>
            <li><a href="brokerage.php"><strong class="text-light">Brokerage policy</strong></a></li>
          </span>
        </ul>
        <div class="text-end">
          <!-- Toggle more/less policy links -->
          <button class="btn btn-sm toggle-btn" type="button" data-bs-toggle="collapse" data-bs-target="#moreText5">Read more</button>
        </div>
      </section>
    </main>
  </div>
</div>

<?php include 'footer.php'; ?>

<!-- Bootstrap JS and custom script for toggling read more/less buttons -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js" integrity="sha384-j1CDi7MgGQ12Z7Qab0qlWQ/Qqz24Gc6BM0thvEMVjHnfYGF0rmFCozFSxQBxwHKO" crossorigin="anonymous"></script>
<script>
// Change button text on collapse toggle (Read more/Read less)
document.querySelectorAll('.toggle-btn').forEach(btn => {
  const targetId = btn.getAttribute('data-bs-target');
  const target = document.querySelector(targetId);

  target.addEventListener('shown.bs.collapse', () => {
    btn.textContent = 'Read less';
  });
  target.addEventListener('hidden.bs.collapse', () => {
    btn.textContent = 'Read more';
  });
});
</script>

</body>
</html>
