// This file can be used for site-wide JavaScript functions,
// like navigation, shared modal logic, etc.
// Currently, it's empty based on the last provided state.
// If you had common JS here previously, it should be restored
// if that functionality is still needed across your site.

document.addEventListener('DOMContentLoaded', function() {
    // Hamburger menu functionality
    const hamburgerMenu = document.querySelector('.hamburger-menu');
    const mainNav = document.querySelector('.main-nav');

    if (hamburgerMenu && mainNav) {
        hamburgerMenu.addEventListener('click', function() {
            mainNav.classList.toggle('active');
            // Toggle aria-expanded attribute for accessibility
            const isExpanded = mainNav.classList.contains('active');
            hamburgerMenu.setAttribute('aria-expanded', isExpanded);
        });

        // Close menu when clicking outside
        document.addEventListener('click', function(event) {
            if (!hamburgerMenu.contains(event.target) && !mainNav.contains(event.target)) {
                mainNav.classList.remove('active');
                hamburgerMenu.setAttribute('aria-expanded', 'false');
            }
        });

        // Close menu when window is resized above mobile breakpoint
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                mainNav.classList.remove('active');
                hamburgerMenu.setAttribute('aria-expanded', 'false');
            }
        });
    }
});
