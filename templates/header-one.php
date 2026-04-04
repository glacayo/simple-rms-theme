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
                        <img
                            src="https://placehold.co/200x100"
                            alt="<?php bloginfo('name'); ?>"
                            width="200"
                            height="100"
                            fetchpriority="high"
                        >
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
                    aria-label="Open menu"
                    aria-expanded="false"
                    aria-controls="mobile-menu"
                >
                    <span class="rms-header__menu-icon">
                        <span></span>
                        <span></span>
                        <span></span>
                    </span>
                </button>

                <nav class="rms-header__nav" id="desktop-nav" aria-label="Main navigation">
                    <ul class="rms-header__nav-list">
                        <li class="rms-header__nav-item">
                            <a href="#" class="rms-header__nav-link">Home</a>
                        </li>
                        <li class="rms-header__nav-item">
                            <a href="#" class="rms-header__nav-link">About</a>
                        </li>
                        <li class="rms-header__nav-item rms-header__nav-item--has-dropdown">
                            <a href="#" class="rms-header__nav-link" aria-haspopup="true" aria-expanded="false">Services</a>
                            <!-- Level 1 dropdown -->
                            <ul class="rms-header__dropdown">
                                <li><a href="#">Service 1</a></li>
                                <li class="rms-header__nav-item--has-dropdown">
                                    <a href="#" aria-haspopup="true">Service 2</a>
                                    <!-- Level 2 dropdown -->
                                    <ul class="rms-header__dropdown--submenu">
                                        <li><a href="#">Service 2.1</a></li>
                                        <li><a href="#">Service 2.2</a></li>
                                        <li><a href="#">Service 2.3</a></li>
                                    </ul>
                                </li>
                                <li><a href="#">Service 3</a></li>
                            </ul>
                        </li>
                        <li class="rms-header__nav-item">
                            <a href="#" class="rms-header__nav-link">Contact</a>
                        </li>
                    </ul>
                </nav>
            </div>
        </div>
    </div>

    <!-- Mobile Menu Overlay -->
    <div class="rms-header__overlay" aria-hidden="true"></div>

    <!-- Mobile Menu Panel -->
    <nav
        class="rms-header__mobile-menu"
        id="mobile-menu"
        aria-label="Mobile navigation"
        aria-hidden="true"
    >
        <div class="rms-header__mobile-menu-header">
            <span class="rms-header__mobile-menu-title">Menu</span>
            <button
                class="rms-header__menu-close"
                type="button"
                aria-label="Close menu"
            >
                &times;
            </button>
        </div>
        <ul class="rms-header__mobile-nav-list">
            <li><a href="#">Home</a></li>
            <li><a href="#">About</a></li>
            <li class="rms-header__mobile-nav-item--has-submenu">
                <a href="#" aria-expanded="false" aria-haspopup="true">Services</a>
                <ul class="rms-header__mobile-submenu">
                    <li><a href="#">Service 1</a></li>
                    <li class="rms-header__mobile-nav-item--has-submenu">
                        <a href="#" aria-expanded="false" aria-haspopup="true">Service 2</a>
                        <ul class="rms-header__mobile-submenu">
                            <li><a href="#">Service 2.1</a></li>
                            <li><a href="#">Service 2.2</a></li>
                            <li><a href="#">Service 2.3</a></li>
                        </ul>
                    </li>
                    <li><a href="#">Service 3</a></li>
                </ul>
            </li>
            <li><a href="#">Contact</a></li>
        </ul>
    </nav>
</header>
