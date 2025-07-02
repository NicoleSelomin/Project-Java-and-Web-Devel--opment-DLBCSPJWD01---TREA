// navbar-close.js
document.addEventListener("click", function (event) {
    const navbar = document.getElementById("mainNavbar");
    const toggler = document.querySelector(".navbar-toggler");
    if (
        navbar && toggler &&
        navbar.classList.contains("show") &&
        !navbar.contains(event.target) &&
        !toggler.contains(event.target)
    ) {
        new bootstrap.Collapse(navbar).hide();
    }
});
