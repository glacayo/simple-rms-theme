<!-- Footer V1 — Classic multi-column contractor footer -->
<footer class="footer-v1" role="contentinfo">
    <div class="container">
        <div class="footer-v1__grid">
            <div class="footer-v1__column footer-v1__column--about">
                <div class="footer-v1__logo-wrap">
                    <?php if (has_custom_logo()) : ?>
                        <?php the_custom_logo(); ?>
                    <?php else : ?>
                        <a href="<?php echo esc_url(home_url('/')); ?>" class="footer-v1__logo-placeholder"><?php echo esc_html(get_bloginfo('name')); ?></a>
                    <?php endif; ?>
                </div>

                <p class="footer-v1__about">
                    We provide dependable roofing and exterior solutions built for long-term protection, honest service, and local expertise homeowners can trust.
                </p>

                <p class="footer-v1__languages">
                    <strong>Languages:</strong> English, Spanish
                </p>
            </div>

            <div class="footer-v1__column">
                <h2 class="footer-v1__heading">Services</h2>
                <ul class="footer-v1__list">
                    <li><a href="#">Roof Installation</a></li>
                    <li><a href="#">Roof Repair</a></li>
                    <li><a href="#">Roof Replacement</a></li>
                    <li><a href="#">Roof Inspection</a></li>
                    <li><a href="#">Gutter Installation</a></li>
                    <li><a href="#">Emergency Services</a></li>
                </ul>
            </div>

            <div class="footer-v1__column">
                <h2 class="footer-v1__heading">Important Links</h2>
                <ul class="footer-v1__list">
                    <li><a href="#">Home</a></li>
                    <li><a href="#">About Us</a></li>
                    <li><a href="#">Services</a></li>
                    <li><a href="#">Blog</a></li>
                    <li><a href="#">Contact</a></li>
                </ul>
            </div>

            <div class="footer-v1__column footer-v1__column--contact">
                <h2 class="footer-v1__heading">Contact</h2>
                <ul class="footer-v1__list footer-v1__list--contact">
                    <li><a href="tel:+14075550199">(407) 555-0199</a></li>
                    <li><a href="mailto:hello@example.com">hello@example.com</a></li>
                    <li>123 Contractor Ave, Orlando, FL</li>
                </ul>

                <h3 class="footer-v1__subheading">Business Hours</h3>
                <ul class="footer-v1__hours">
                    <li><span>Mon – Fri</span><span>8:00 AM – 6:00 PM</span></li>
                    <li><span>Sat</span><span>9:00 AM – 2:00 PM</span></li>
                    <li><span>Sun</span><span>Closed</span></li>
                </ul>

                <a href="#" class="footer-v1__cta">Free Estimate</a>
            </div>
        </div>
    </div>

    <div class="footer-v1__copyright">
        <div class="container">
            <p>© 2026 Simple RMS Theme. All rights reserved.</p>
        </div>
    </div>
</footer>
