<header class="rms-header" role="banner">
    <!-- Top Bar -->
    <div class="rms-header__top-bar">
        <div class="container">
            <div class="rms-header__top-bar-inner">
                <div class="rms-header__top-bar-left">
                    <a href="#" class="rms-header__cta-btn">GET A FREE ESTIMATE</a>
                </div>
                <div class="rms-header__top-bar-right">
                    <span class="rms-header__social-label">Follow us on:</span>
                    <ul class="rms-header__social-list" aria-label="Social media links">
                        <li><a href="#" aria-label="Facebook"><svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg></a></li>
                        <li><a href="#" aria-label="Twitter"><svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg></a></li>
                        <li><a href="#" aria-label="Instagram"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/></svg></a></li>
                        <li><a href="#" aria-label="LinkedIn"><svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"/><rect x="2" y="9" width="4" height="12"/><circle cx="4" cy="4" r="2"/></svg></a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Bar -->
    <div class="rms-header__main-bar">
        <div class="container">
            <div class="rms-header__main-bar-inner">
                <div class="rms-header__logo">
                    <a href="<?php echo esc_url(home_url('/')); ?>" aria-label="Home">
                        <?php if (has_custom_logo()) : ?>
                            <?php the_custom_logo(); ?>
                        <?php else : ?>
                            <img
                                src="https://placehold.co/200x100"
                                alt="<?php bloginfo('name'); ?>"
                                width="200"
                                height="100"
                                fetchpriority="high"
                            >
                        <?php endif; ?>
                    </a>
                </div>
                <div class="rms-header__contact-info">
                    <ul class="rms-header__contact-list">
                        <li>
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.82 12a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.73 1h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 8.91a16 16 0 0 0 5.78 5.78l.91-.91a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                            <span>(414) 246-8257</span>
                        </li>
                        <li>
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                            <span>1234 Oak Ridge Ave, Milwaukee, WI 53211</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Nav Bar -->
    <div class="rms-header__nav-bar">
        <div class="container">
            <div class="rms-header__nav-bar-inner">
                <button
                    class="rms-header__menu-toggle"
                    type="button"
                    aria-label="<?php esc_attr_e('Open menu', 'simple-rms-theme'); ?>"
                    aria-expanded="false"
                    aria-controls="mobile-menu"
                >
                    <span class="rms-header__menu-icon">
                        <span></span>
                        <span></span>
                        <span></span>
                    </span>
                </button>

                <!-- Desktop Nav — Primary Menu -->
                <nav class="rms-header__nav" id="desktop-nav" aria-label="<?php esc_attr_e('Main navigation', 'simple-rms-theme'); ?>">
                    <?php
                    wp_nav_menu([
                        'theme_location' => 'primary',
                        'container'      => false,
                        'menu_class'     => 'rms-header__nav-list',
                        'walker'         => new RMS_Walker_Nav_Primary(),
                        'fallback_cb'    => false,
                        'depth'          => 3,
                    ]);
                    ?>
                </nav>
            </div>
        </div>
    </div>

    <!-- Mobile Menu Overlay -->
    <div class="rms-header__overlay" aria-hidden="true"></div>

    <!-- Mobile Menu Panel — Mobile Menu -->
    <nav
        class="rms-header__mobile-menu"
        id="mobile-menu"
        aria-label="<?php esc_attr_e('Mobile navigation', 'simple-rms-theme'); ?>"
        aria-hidden="true"
    >
        <div class="rms-header__mobile-menu-header">
            <span class="rms-header__mobile-menu-title"><?php esc_html_e('Menu', 'simple-rms-theme'); ?></span>
            <button
                class="rms-header__menu-close"
                type="button"
                aria-label="<?php esc_attr_e('Close menu', 'simple-rms-theme'); ?>"
            >
                &times;
            </button>
        </div>
        <?php
        wp_nav_menu([
            'theme_location' => 'mobile',
            'container'      => false,
            'menu_class'     => 'rms-header__mobile-nav-list',
            'walker'         => new RMS_Walker_Nav_Mobile(),
            'fallback_cb'    => false,
            'depth'          => 3,
        ]);
        ?>
    </nav>
</header>
