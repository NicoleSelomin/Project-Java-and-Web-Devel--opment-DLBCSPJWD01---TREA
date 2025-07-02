<!--
|--------------------------------------------------------------------------
| about-us.php
|--------------------------------------------------------------------------
|
| TREA "About Us" Page 
| - Responsive layout using Bootstrap 5. 
| - A sidebar for a quick picture and message about your brand.
| - The main parts are the About section, the Mission, the Vision, the Timeline, the Team, and the Rules.
| - "Read more" sections that can be hidden to make the presentation shorter. 
| - Uses PHP includes for the header and footer to keep the site consistent.
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
<!-- Sidebar images and text for branding - collapsible on mobile -->
    <aside class="col-12 col-md-2 bg-light mb-3 mb-md-0">
      <button class="btn btn-sm d-md-none mb-3 custom-btn" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarCollapse" aria-expanded="false" aria-controls="sidebarCollapse">
        Open Sidebar
      </button>
      <div class="collapse d-md-block" id="sidebarCollapse">
        <div class="sidebar text-center bg-primary p-3 rounded shadow-sm">
          <h5 class="text-white">Shop your ideal property like you shop for your favorite food.</h5>
          <img src="images/sidebar.png" alt="TREA About Us" class="mb-3 img-fluid rounded">
          <p class="text-white small">Your trusted real estate agents—here to provide you only with the best and most affordable properties.</p>
        </div>
      </div>
    </aside>

    <!-- Main content --> 
    <main class="col-12 col-md-9 ms-lg-5">
      <!-- Section: Page title -->
      <div class="text-center mb-5 px-3 px-md-0 border rounded shadow-sm main-title">
        <h1 class="fs-2 fs-md-1 fw-bold text-dark">Learn more about us</h1>
      </div>

      <!-- Section: About -->
      <section class="mb-4 p-3 border rounded shadow-sm">
        <h2>About TREA</h2>
        <p class="text-dark">
          Trusted Real Estate Agency (TREA) is committed to providing excellent real estate services. 
          Trusted Real Estate Agency (TREA) is dedicated to delivering outstanding real estate services.
          <span id="moreText1" class="collapse"> 
            Right now, they only offer property management and brokerage services.             
            We will be adding new services like property management for sales, construction supervision, legal help, and architectural design. 
            We help people who want to buy or rent properties and property owners do so in a safe and easy way.
          </span>
        </p>
        <div class="text-end">
          <!-- Toggle 'Read more button' -->
          <button class="btn btn-sm toggle-btn" type="button" data-bs-toggle="collapse" data-bs-target="#moreText1">Read more</button>
        </div>
      </section>

      <!-- Section: Mission and Vision -->
      <section class="mb-4 p-3 border rounded shadow-sm">
        <h3>Mission</h3>
        <p class="text-dark">
          To be the most reliable and innovative real estate agency, ensuring that everybody involved in property management and development is satisfied and secure
        </p>
        <h3 class="mt-4">Vision</h3>
        <p class="text-dark">To give property owners and clients real estate solutions that are easy to use and reliable.</p>
      </section>

      <!-- Section: Timeline -->
      <section class="mb-4 p-3 border rounded shadow-sm">
        <h3>TREA Timeline</h3>
        <ul class="text-dark">
          <li><strong>2023:</strong> TREA was started to change the real estate business.</li>
          <li><strong>2024:</strong> Added more than 100 properties to the system successfully.</li>
          <span id="moreText3" class="collapse">
            <li><strong>2025:</strong> Starting a digital service platform for online  applications and process tracking.</li>
          </span>
        </ul>
        <div class="text-end">
          <!-- toggle read more button -->
          <button class="btn btn-sm toggle-btn" type="button" data-bs-toggle="collapse" data-bs-target="#moreText3">Read more</button>
        </div>
      </section>

      <!-- Section: Meet Our Team -->
      <section class="mb-4 p-3 border rounded shadow-sm">
        <h3>Meet the TREA Team</h3>
        <ul class="text-dark">
          <li><strong>General Manager:</strong> Responsible for operations and making decisions.</li>
          <li><strong>Property Manager:</strong> Coordinates property listings and upkeep.</li>
          <span id="moreText4" class="collapse">
            <li><strong>Legal Officer:</strong> Makes sure that property documents and contracts meet legal requirements</li>
            <li><strong>Accountant:</strong> Keeps track of all the financial transactions.</li>
            <li><strong>Field Agents:</strong> Help clients and look at the properties that are for sale.</li>
          </span>
        </ul>
        <div class="text-end">
          <!-- read more toggle button  -->
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
          <li><a href="servive-request-policy.php"><strong class="text-light">Service request policy</strong></a></li>
          <span id="moreText5" class="collapse">
            <li><a href="property-claim-policy.php"><strong class="text-light">Property claim policy</strong></a></li>
            <li><a href="propery-listing-policy.php"><strong class="text-light">Property listing policy</strong></a></li>
            <li><a href="property-managemnt-policy.php"><strong class="text-light">Property management policy</strong></a></li>
            <li><a href="brokerage-policy.php"><strong class="text-light">Brokerage policy</strong></a></li>
          </span>
        </ul>
        <div class="text-end">
          <!-- toggle read more -->
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

<!-- Script to close main navbar on small screen-->
<script src="navbar-close.js?v=1"></script>

</body>
</html>