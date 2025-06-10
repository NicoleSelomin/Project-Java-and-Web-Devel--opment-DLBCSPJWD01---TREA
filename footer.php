<!-- 
 ------------------------------------------------------
 footer.php
 ------------------------------------------------------

 Standard footer
 ---------------------------------------

 Features:
 - 3-column-layout: Agency Infor, Quick Links, Contact
 - Responsive (Bootstrap grid), text-centered on mobile
 - Uses Bootstrap 5 spacing and color classes
 - Uses white text for links and copyright
 ------------------------------------------

 Usage: 
 - include at the buttom of all main templates
 
 ----------------------------------------------------------------------
 -->

 <footer class="mt-5 border-top small pt-4 pb-2 bg-primary text-white">
    <div class="container">
        <div class="row gy-4 text-center text-md-start">
<!-- Agency Info Column -->
 <div class="col-12 col-md-4">
    <h6 class="fw-bold text-white">TREA</h6>
    <p class="mb-0">
        Your trusted real estate partner in legal, architectural and property solutions.
    </p>
</div>

<!-- Quick Links Column-->
 <div class="col-12 col-md-3">
    <h6 class="fw-bold text-white">Quick Links</h6>
    <ul class="list-unstyled mb-0">
        <li><a href="index.php" class="text-white text-decoration-none d-block py-1">Home</a></li>
        <li><a href="about-us.php" class="text-white text-decoration-none d-block py-1">About Us</a></li>
        <li><a href="services.php" class="text-white text-decoration-none d-block py-1">Services</a></li>
        <li><a href="user-login.php" class="text-white text-decoration-none d-block py-1">User Account</a></li>
    </ul>
 </div>

 <!-- Contact Column -->
  <div class="col-12 col-md-4">
    <h6 class="fw-bold text-white"> Contact</h6>
    <p class="mb-1">Email: <a href="mailto:info@trea.com" class="text-white text-decoration-underline">info@trea.com</a></p>
    <p class="mb-1">Phone: (+229) 0100000000</p>
    <p class="mb-0">Adress: Aibatin 2, Cotonou, Benin</p>
  </div>
 </div>

<!-- Divider and Copyright -->
 <hr class="border-light my-4">
 <div class="text-center">
    <p class="text-white mb-0">&copy;<?= date('Y') ?>Trusted Real Estate Agency. All rights reserved</p>
 </div>
        </div>
    </div>
</footer>