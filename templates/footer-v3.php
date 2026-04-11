<!-- Footer V3 — Minimal split footer with contact emphasis -->
<footer class="footer-v3" role="contentinfo">
    <div class="container footer-v3__main">
        <div class="footer-v3__left">
            <div class="footer-v3__logo-wrap">
                <?php if (has_custom_logo()) : ?>
                    <?php the_custom_logo(); ?>
                <?php else : ?>
                    <a href="<?php echo esc_url(home_url('/')); ?>" class="footer-v3__logo-placeholder"><?php echo esc_html(get_bloginfo('name')); ?></a>
                <?php endif; ?>
            </div>

            <p class="footer-v3__about">
                We provide dependable roofing and exterior solutions built for long-term protection, honest service, and local expertise homeowners can trust.
            </p>

            <div class="footer-v3__social" aria-label="Social media links">
                <a href="#" class="footer-v3__social-link" aria-label="Facebook">FB</a>
                <a href="#" class="footer-v3__social-link" aria-label="Instagram">IG</a>
                <a href="#" class="footer-v3__social-link" aria-label="LinkedIn">LK</a>
            </div>
        </div>

        <div class="footer-v3__right">
            <div class="footer-v3__info-row">
                <span class="footer-v3__label">Phone</span>
                <a href="tel:+14075550199" class="footer-v3__value">(407) 555-0199</a>
            </div>
            <div class="footer-v3__info-row">
                <span class="footer-v3__label">Email</span>
                <a href="mailto:hello@example.com" class="footer-v3__value">hello@example.com</a>
            </div>
            <div class="footer-v3__info-row">
                <span class="footer-v3__label">Address</span>
                <span class="footer-v3__value">123 Contractor Ave, Orlando, FL</span>
            </div>
            <div class="footer-v3__info-row">
                <span class="footer-v3__label">Hours</span>
                <span class="footer-v3__value">Mon – Fri: 8:00 AM – 6:00 PM | Sat: 9:00 AM – 2:00 PM | Sun: Closed</span>
            </div>
            <div class="footer-v3__info-row">
                <span class="footer-v3__label">Languages</span>
                <span class="footer-v3__value">English, Spanish</span>
            </div>
        </div>
    </div>

    <div class="container footer-v3__meta">
        <div class="footer-v3__meta-block">
            <h2 class="footer-v3__meta-title">Services</h2>
            <ul class="footer-v3__inline-list">
                <li><a href="#">Roof Installation</a></li>
                <li><a href="#">Roof Repair</a></li>
                <li><a href="#">Roof Replacement</a></li>
                <li><a href="#">Roof Inspection</a></li>
                <li><a href="#">Gutter Installation</a></li>
                <li><a href="#">Emergency Services</a></li>
            </ul>
        </div>

        <div class="footer-v3__meta-block">
            <h2 class="footer-v3__meta-title">Important Links</h2>
            <ul class="footer-v3__inline-list">
                <li><a href="#">Home</a></li>
                <li><a href="#">About Us</a></li>
                <li><a href="#">Services</a></li>
                <li><a href="#">Blog</a></li>
                <li><a href="#">Contact</a></li>
            </ul>
        </div>
    </div>

    <div class="footer-v3__copyright">
        <div class="container">
            <p>© 2026 Simple RMS Theme. All rights reserved.</p>
        </div>
    </div>
</footer>
