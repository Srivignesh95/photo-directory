        <footer class="bg-dark text-light pt-5 pb-3">
            <div class="container">
                <div class="row g-4">
                    <!-- Logo -->
                    <div class="col-md-3 col-sm-6 text-center">
                        <a href="https://sttimothy.ca">
                            <img src="assets/images/footer-logo.png" alt="St Timothy Logo" class="img-fluid" width="180">
                        </a>
                    </div>

                    <!-- Contact Info -->
                    <div class="col-md-3 col-sm-6">
                        <h5 class="text-white">Contact Info</h5>
                        <p class="mb-1">Address: 100 Old Orchard Grove<br>Toronto, ON M5M 2E2</p>
                        <p class="mb-1">Phone: <a href="tel:4164880079" class="text-light">416-488-0079</a></p>
                        <p class="mb-0">Email: <a href="mailto:office@sttimothy.ca" class="text-light">office@sttimothy.ca</a></p>
                    </div>

                    <!-- Google Map -->
                    <div class="col-md-3 col-sm-6">
                        <h5 class="text-white">Our Location</h5>
                        <div class="ratio ratio-4x3">
                            <iframe loading="lazy" src="https://maps.google.com/maps?q=St%20Timothy%27s%20Anglican%20Church%20100%20Old%20Orchard%20Grove&amp;t=m&amp;z=11&amp;output=embed&amp;iwloc=near" title="St Timothy's Anglican Church" allowfullscreen></iframe>
                        </div>
                    </div>

                    <!-- Logos -->
                    <div class="col-md-3 col-sm-6 text-center">
                        <img src="assets/images/footer-diocese-logo.png" alt="Diocese Logo" class="img-fluid mb-3" width="160">
                        <img src="assets/images/footer-anglican-church-logo.png" alt="Anglican Church Logo" class="img-fluid" width="160">
                    </div>
                </div>

                <hr class="my-4 border-light">

                <div class="text-center small">
                    <p class="mb-0">© Copyright 2025 | St. Timothy Anglican Church, Toronto</p>
                    <p>Design by <a href="https://svkzone.com" class="text-light" target="_blank" rel="noopener">SVK Zone</a></p>
                </div>
            </div>
        </footer>

        <script src="assets/js/bootstrap.bundle.min.js"></script>
        <script>
            // Show dropdown on hover
            const aboutMenu = document.querySelector('li.position-relative');
            aboutMenu.addEventListener('mouseenter', () => {
                aboutMenu.querySelector('ul').style.display = 'block';
            });
            aboutMenu.addEventListener('mouseleave', () => {
                aboutMenu.querySelector('ul').style.display = 'none';
            });
        </script>
    </body>
</html>