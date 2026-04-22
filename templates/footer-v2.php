<!-- Footer V2 — Brand-heavy footer with CTA strip -->
<footer class="footer-v2" role="contentinfo">
    <div class="footer-v2__cta-strip">
        <div class="container footer-v2__cta-inner">
            <p class="footer-v2__cta-text">Need a Free Estimate?</p>
            <a href="#contact" class="btn footer-v2__cta-button">Get a Free Estimate</a>
        </div>
    </div>

    <div class="footer-v2__main">
        <div class="container footer-v2__grid">

            <div class="footer-v2__column footer-v2__column--brand">
                <div class="footer-v2__logo-wrap">
                    <?php if (has_custom_logo()) : ?>
                        <?php the_custom_logo(); ?>
                    <?php else : ?>
                        <a href="<?php echo esc_url(home_url('/')); ?>" class="footer-v2__logo-placeholder"><?php echo esc_html(get_bloginfo('name')); ?></a>
                    <?php endif; ?>
                </div>
                <p class="footer-v2__about">
                    We provide dependable roofing and exterior solutions built for long-term protection, honest service, and local expertise homeowners can trust.
                </p>
                <div class="footer-v2__social" aria-label="Social media links">
                    <a href="#" class="footer-v2__social-link" aria-label="Facebook">FB</a>
                    <a href="#" class="footer-v2__social-link" aria-label="Twitter">TW</a>
                    <a href="#" class="footer-v2__social-link" aria-label="Instagram">IG</a>
                    <a href="#" class="footer-v2__social-link" aria-label="LinkedIn">LK</a>
                </div>
            </div>

            <div class="footer-v2__column">
                <h2 class="footer-v2__heading">Menu</h2>
                <ul class="footer-v2__list">
                    <li><a href="#">Home</a></li>
                    <li><a href="#">About Us</a></li>
                    <li><a href="#">Blog</a></li>
                    <li><a href="#">Contact</a></li>
                </ul>
            </div>

            <div class="footer-v2__column">
                <h2 class="footer-v2__heading">Services</h2>
                <ul class="footer-v2__list">
                    <li><a href="#">Roof Installation</a></li>
                    <li><a href="#">Roof Repair</a></li>
                    <li><a href="#">Roof Replacement</a></li>
                    <li><a href="#">Roof Inspection</a></li>
                    <li><a href="#">Gutter Installation</a></li>
                    <li><a href="#">Emergency Services</a></li>
                </ul>
            </div>

            <div class="footer-v2__column footer-v2__column--info">
                <h2 class="footer-v2__heading">Company Info</h2>
                <ul class="footer-v2__list footer-v2__list--info">
                    <li><a href="tel:+14075550199">(407) 555-0199</a></li>
                    <li><a href="mailto:hello@example.com">hello@example.com</a></li>
                    <li>123 Contractor Ave, Orlando, FL</li>
                </ul>
                <p class="footer-v2__meta"><strong>Languages:</strong> English, Spanish</p>
                <p class="footer-v2__meta"><strong>Payment Methods:</strong> Visa, Mastercard, Discover, Cash</p>
            </div>

        </div>
    </div>

    <div class="footer-v2__copyright">
        <div class="container">
            <p>© 2026 Simple RMS Theme. All rights reserved.</p>
        </div>
    </div>
</footer>
