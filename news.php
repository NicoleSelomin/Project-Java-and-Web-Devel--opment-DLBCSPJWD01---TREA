<!-- news.php - Real Estate News Page (TREA) -->
<?php
// No PHP processing needed except for includes and cache-busting
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>News - TREA</title>
  <!-- Bootstrap 5.3.6 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-4Q6Gf2aSP4eDXB8Miphtr37CMZZQ5oXLH2yaXMJ2w8e2ZtHTl7GptT4jmndRuHDT" crossorigin="anonymous">
  <!-- Custom Styles (with cache busting for updates) -->
  <link rel="stylesheet" href="styles.css?v=<?= time() ?>">
</head>
<body class="d-flex flex-column min-vh-100">

<?php
// Include the uniform responsive header for all pages
include 'header.php';
?>

<div class="container-fluid">
  <div class="row">
    <!-- Responsive Sidebar (mobile collapses, always visible on desktop) -->
    <aside class="col-12 col-md-3 mb-3">
      <button class="btn btn-sm d-md-none mb-3 custom-btn" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarCollapse" aria-expanded="false" aria-controls="sidebarCollapse">
        Open Sidebar
      </button>
      <div class="collapse d-md-block" id="sidebarCollapse">
        <div class="sidebar text-center bg-primary p-3 rounded shadow-sm">
          <h5 class="text-white">Shop your ideal property like you shop for your favorite food.</h5>
          <img src="about-us-image.png" alt="TREA About Us" class="mb-3 img-fluid rounded">
          <p class="text-white small">Your trusted real estate agents—here to provide you only with the best and most affordable properties.</p>
        </div>
      </div>
    </aside>

    <!-- Main Content: News Feed -->
    <main class="col-12 col-md-9">
      <!-- Page Title and Intro -->
      <div class="mb-4 p-3 border rounded shadow-sm main-title bg-white">
        <h2 class="fw-bold text-dark">Real Estate News and Updates</h2>
        <p>Stay informed with the latest developments in the real estate world, updates from our agency, and legal changes that may affect your properties.</p>
      </div>

      <!-- News Article 1 -->
      <section class="mb-4 p-3 border rounded shadow-sm bg-white">
        <article class="news-item">
          <h3 class="h5">New Property Tax Laws Enacted in 2025</h3>
          <p>
            The government has introduced revised property tax laws aimed at
            <span id="moreText1" class="collapse"> regulating urban expansion. Owners must now register all properties for taxation by July 2025.</span>
          </p>
          <small class="text-muted">Posted on: April 10, 2025</small>
          <div class="text-end">
            <!-- Expand/collapse button for more text -->
            <button class="btn btn-sm toggle-btn mt-2 custom-btn" type="button" data-bs-toggle="collapse" data-bs-target="#moreText1" aria-expanded="false">Read more</button>
          </div>
        </article>
      </section>

      <!-- News Article 2 -->
      <section class="mb-4 p-3 border rounded shadow-sm bg-white">
        <article class="news-item">
          <h3 class="h5">TREA Launches Online Property Registration System</h3>
          <p>
            Trusted Real Estate Agency now allows property owners to submit
            <span id="moreText2" class="collapse"> registration applications online. This improves processing speed and user convenience.</span>
          </p>
          <small class="text-muted">Posted on: March 22, 2025</small>
          <div class="text-end">
            <button class="btn btn-sm toggle-btn mt-2 custom-btn" type="button" data-bs-toggle="collapse" data-bs-target="#moreText2" aria-expanded="false">Read more</button>
          </div>
        </article>
      </section>

      <!-- News Article 3 -->
      <section class="mb-4 p-3 border rounded shadow-sm bg-white">
        <article class="news-item">
          <h3 class="h5">Construction Permit Reforms Benefit New Home Builders</h3>
          <p>
            The Ministry of Housing has simplified the process of obtaining building permits,
            <span id="moreText3" class="collapse"> cutting wait times by 40% and easing document requirements for small projects.</span>
          </p>
          <small class="text-muted">Posted on: February 15, 2025</small>
          <div class="text-end">
            <button class="btn btn-sm toggle-btn mt-2 custom-btn" type="button" data-bs-toggle="collapse" data-bs-target="#moreText3" aria-expanded="false">Read more</button>
          </div>
        </article>
      </section>
    </main>
  </div>
</div>

<?php
// Include the uniform footer for all pages
include 'footer.php';
?>

<!-- Bootstrap Bundle JS (for collapse and all components) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js" integrity="sha384-j1CDi7MgGQ12Z7Qab0qlWQ/Qqz24Gc6BM0thvEMVjHnfYGF0rmFCozFSxQBxwHKO" crossorigin="anonymous"></script>
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
</body>
</html>
