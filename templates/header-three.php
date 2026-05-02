<header class="rms-header-v3" role="banner">
    <div class="rms-header-v3__inner">
        <!-- Logo Area -->
        <div class="rms-header-v3__logo-area">
            <?php
        $header_logo = rms_get_option('company_logo_header');
        if (!empty($header_logo)) :
            ?>
            <a href="/" class="rms-header-v3__logo-link" aria-label="Home">
                <img src="<?php echo esc_url($header_logo); ?>" alt="Simple RMS" width="200" height="60" fetchpriority="high">
            </a>
            <?php
        else :
            ?>
            <a href="/" class="rms-header-v3__logo-link" aria-label="Home">
                <img src="https://placehold.co/200x60" alt="Simple RMS" width="200" height="60" fetchpriority="high">
            </a>
            <?php
        endif;
        ?>
        </div>

        <!-- Menu Area -->
        <div class="rms-header-v3__menu-area">
            <!-- Meta row: phone + socials -->
            <div class="rms-header-v3__meta">
                <a href="tel:+14075550199" class="rms-header-v3__phone">
                    <svg class="rms-header-v3__icon" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                    </svg>
                    <span>(407) 555-0199</span>
                </a>
                <?php
                        $socials = rms_get_social_links();
                        if (!empty($socials)) :
                        ?>
                        <div class="rms-header-v3__social">
                            <?php foreach ($socials as $platform => $social) : ?>
                                <?php
                                $svg = '';
                                switch ($platform) {
                                    case 'facebook':
                                        $svg = '<svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg>';
                                        break;
                                    case 'twitter':
                                    case 'x':
                                        $svg = '<svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>';
                                        break;
                                    case 'instagram':
                                        $svg = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/></svg>';
                                        break;
                                    case 'linkedin':
                                        $svg = '<svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"/><rect x="2" y="9" width="4" height="12"/><circle cx="4" cy="4" r="2"/></svg>';
                                        break;
                                    default:
                                        $svg = '';
                                }
                                if (!empty($svg)) :
                                ?>
                                <a href="<?php echo esc_url($social['url']); ?>" class="rms-header-v3__social-link" aria-label="<?php echo esc_attr($social['label']); ?>" target="_blank" rel="noopener noreferrer"><?php echo $svg; ?></a>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
            </div>

            <!-- Nav row: links + mobile toggle -->
            <div class="rms-header-v3__nav-row">
                <nav class="rms-header-v3__nav" aria-label="Main navigation">
                    <?php
                    wp_nav_menu([
                        'theme_location' => 'primary',
                        'container'      => false,
                        'menu_class'     => 'rms-header-v3__nav-list',
                        'walker'         => new RMS_Walker_Nav_V3_Desktop(),
                        'fallback_cb'    => false,
                        'depth'          => 3,
                    ]);
                    ?>
                </nav>
                <button
                    class="rms-header-v3__menu-toggle"
                    type="button"
                    aria-label="Open menu"
                    aria-expanded="false"
                    aria-controls="rms-header-v3-mobile-menu"
                >
                    <span class="rms-header-v3__hamburger">
                        <span></span>
                        <span></span>
                        <span></span>
                    </span>
                </button>
            </div>
        </div>
    </div>

    <!-- Dark gradient overlay — sits behind content, provides contrast over hero -->
    <div class="rms-header-v3__gradient" aria-hidden="true"></div>

    <!-- Mobile Menu Overlay -->
    <div class="rms-header-v3__overlay" aria-hidden="true"></div>

    <!-- Mobile Menu — Full-screen panel -->
    <nav class="rms-header-v3__mobile-menu" id="rms-header-v3-mobile-menu" aria-label="Mobile navigation" aria-hidden="true">
        <div class="rms-header-v3__mobile-header">
            <span class="rms-header-v3__mobile-title">Menu</span>
            <button class="rms-header-v3__mobile-close" type="button" aria-label="Close menu">
                <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
        <?php
        wp_nav_menu([
            'theme_location' => 'mobile',
            'container'      => false,
            'menu_class'     => 'rms-header-v3__mobile-nav-list',
            'walker'         => new RMS_Walker_Nav_V3_Mobile(),
            'fallback_cb'    => false,
            'depth'          => 3,
        ]);
        ?>
        <div class="rms-header-v3__mobile-footer">
            <a href="tel:+14075550199" class="rms-header-v3__mobile-phone">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                </svg>
                <span>(407) 555-0199</span>
            </a>
            <?php
                            $socials = rms_get_social_links();
                            if (!empty($socials)) :
                            ?>
                            <div class="rms-header-v3__mobile-social">
                                <?php foreach ($socials as $platform => $social) : ?>
                                    <?php
                                    $svg = '';
                                    switch ($platform) {
                                        case 'facebook':
                                            $svg = '<svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg>';
                                            break;
                                        case 'twitter':
                                        case 'x':
                                            $svg = '<svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>';
                                            break;
                                        case 'instagram':
                                            $svg = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/></svg>';
                                            break;
                                        case 'linkedin':
                                            $svg = '<svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"/><rect x="2" y="9" width="4" height="12"/><circle cx="4" cy="4" r="2"/></svg>';
                                            break;
                                        default:
                                            $svg = '';
                                    }
                                    if (!empty($svg)) :
                                    ?>
                                    <a href="<?php echo esc_url($social['url']); ?>" aria-label="<?php echo esc_attr($social['label']); ?>" target="_blank" rel="noopener noreferrer"><?php echo $svg; ?></a>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
        </div>
    </nav>
</header>
