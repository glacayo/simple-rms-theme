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
                        <li><a href="#" aria-label="Facebook">FB</a></li>
                        <li><a href="#" aria-label="Twitter">TW</a></li>
                        <li><a href="#" aria-label="Instagram">IG</a></li>
                        <li><a href="#" aria-label="LinkedIn">LK</a></li>
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
                            <i class="rms-icon-phone" aria-hidden="true"></i>
                            <span>+1234567890</span>
                        </li>
                        <li>
                            <i class="rms-icon-map-marker" aria-hidden="true"></i>
                            <span>Address</span>
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
