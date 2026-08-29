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
                    <?php
                $footer_logo = rms_get_option('company_logo_footer');
                if (has_custom_logo()) :
                    the_custom_logo();
                elseif (!empty($footer_logo)) :
                    ?>
                    <a href="<?php echo esc_url(home_url('/')); ?>">
                        <img src="<?php echo esc_url($footer_logo); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>" width="200" height="60">
                    </a>
                    <?php
                else :
                    ?>
                    <a href="<?php echo esc_url(home_url('/')); ?>" class="footer-v2__logo-placeholder"><?php echo esc_html(get_bloginfo('name')); ?></a>
                    <?php
                endif;
                ?>
                </div>
                <p class="footer-v2__about">
                    We provide dependable roofing and exterior solutions built for long-term protection, honest service, and local expertise homeowners can trust.
                </p>
                <?php
                $socials = rms_get_social_links();
                $social_icons = [
                    'facebook'  => '<svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg>',
                    'instagram' => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/></svg>',
                    'linkedin'  => '<svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"/><rect x="2" y="9" width="4" height="12"/><circle cx="4" cy="4" r="2"/></svg>',
                    'x'         => '<svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>',
                    'twitter'   => '<svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>',
                    'youtube'   => '<svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M23.5 6.2a3 3 0 0 0-2.1-2.1C19.5 3.6 12 3.6 12 3.6s-7.5 0-9.4.5A3 3 0 0 0 .5 6.2 31.5 31.5 0 0 0 0 12a31.5 31.5 0 0 0 .5 5.8 3 3 0 0 0 2.1 2.1c1.9.5 9.4.5 9.4.5s7.5 0 9.4-.5a3 3 0 0 0 2.1-2.1A31.5 31.5 0 0 0 24 12a31.5 31.5 0 0 0-.5-5.8zM9.75 15.57V8.43L15.84 12z"/></svg>',
                    'tiktok'    => '<svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M14.5 3h2.1c.2 1.8 1.2 3.4 2.8 4.4 1 .6 2.1.9 3.2 1v2.3c-1.5 0-3-.4-4.3-1.2v6.7A6.8 6.8 0 1 1 10 9.5v2.4a4.4 4.4 0 1 0 4.5 4.4V3z"/></svg>',
                    'pinterest' => '<svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M12 2C6.5 2 2 6.5 2 12c0 4.2 2.6 7.8 6.3 9.2-.1-.8-.2-2 0-2.8l2.1-8.8s-.5-1.1-.5-2.6c0-2.5 1.4-4.3 3.2-4.3 1.5 0 2.3 1.1 2.3 2.5 0 1.5-1 3.8-1.5 5.9-.4 1.8.9 3.2 2.6 3.2 3.2 0 5.3-4.1 5.3-8.9 0-3.7-2.5-6.4-7-6.4A7.3 7.3 0 0 0 5.7 11c.5 1.1 1.4 2.9 1.4 2.9s-.5 2.1-.6 2.6A10 10 0 1 0 12 2z"/></svg>',
                    'google_business' => '<svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M12.48 10.92v3.28h7.84c-.24 1.84-.853 3.187-1.787 4.133-1.147 1.147-2.933 2.4-6.053 2.4-4.827 0-8.6-3.893-8.6-8.72s3.773-8.72 8.6-8.72c2.6 0 4.507 1.027 5.907 2.347l2.307-2.307C18.747 1.44 16.133 0 12.48 0 5.867 0 .307 5.387.307 12s5.56 12 12.173 12c3.573 0 6.267-1.173 8.373-3.36 2.16-2.16 2.84-5.213 2.84-7.667 0-.76-.053-1.467-.173-2.053H12.48z"/></svg>',
                    'other'     => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 4h6v6"/><path d="M10 14 20 4"/><path d="M20 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1h5"/></svg>',
                ];
                $social_items = [];
                if (is_array($socials)) {
                    foreach ($socials as $platform => $social) {
                        $url = is_array($social) ? ($social['url'] ?? '') : '';
                        $url = is_string($url) ? trim($url) : '';
                        if ($url === '' || esc_url($url) === '') {
                            continue;
                        }

                        $label = is_array($social) && !empty($social['label'])
                            ? (string) $social['label']
                            : (string) $platform;

                        $social_items[] = [
                            'url'   => $url,
                            'label' => $label,
                            'icon'  => $social_icons[$platform] ?? $social_icons['other'],
                        ];
                    }
                }
                if ($social_items !== []) :
                ?>
                <nav class="footer-v2__social" aria-label="<?php echo esc_attr__('Social media', 'simple-rms-theme'); ?>">
                    <?php foreach ($social_items as $social_item) : ?>
                        <a
                            href="<?php echo esc_url($social_item['url']); ?>"
                            class="footer-v2__social-link"
                            aria-label="<?php echo esc_attr($social_item['label']); ?>"
                            target="_blank"
                            rel="noopener noreferrer"
                        ><?php echo $social_item['icon']; ?></a>
                    <?php endforeach; ?>
                </nav>
                <?php endif; ?>
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
                <?php
                    $phones = rms_get_option('company_phones');
                    $emails = rms_get_option('company_emails');
                    $addr_line1 = rms_get_option('company_address_line_1');
                    $addr_line2 = rms_get_option('company_address_line_2');
                    $addr_city  = rms_get_option('company_city');
                    $addr_state = rms_get_option('company_state');
                    ?>
                <ul class="footer-v2__list footer-v2__list--info">
                    <?php if (is_array($phones) && !empty($phones)) : ?>
                        <?php foreach ($phones as $phone) : ?>
                            <?php
                            $phone_num = $phone['phone_number'] ?? '';
                            $phone_clean = preg_replace('/[^0-9+]/', '', $phone_num);
                            $phone_label = esc_html($phone['phone_label'] ?? '');
                            ?>
                            <li>
                                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.82 12a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.73 1h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 8.91a16 16 0 0 0 5.78 5.78l.91-.91a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                                <a href="tel:<?php echo esc_attr($phone_clean); ?>"><?php echo esc_html($phone_num); ?></a>
                                <?php if (!empty($phone_label)) : ?>
                                    <span class="footer-v2__item-label">(<?php echo esc_html($phone_label); ?>)</span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <?php if (is_array($emails) && !empty($emails)) : ?>
                        <?php foreach ($emails as $email) : ?>
                            <?php
                            $email_addr = $email['email_address'] ?? '';
                            $email_label = esc_html($email['email_label'] ?? '');
                            ?>
                            <li>
                                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                                <a href="mailto:<?php echo esc_attr($email_addr); ?>"><?php echo esc_html($email_addr); ?></a>
                                <?php if (!empty($email_label)) : ?>
                                    <span class="footer-v2__item-label">(<?php echo esc_html($email_label); ?>)</span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <?php
                    $address_parts = array_filter([
                        $addr_line1,
                        $addr_line2,
                        $addr_city,
                        $addr_state,
                    ]);
                    if (!empty($address_parts)) :
                        $full_address = implode(', ', $address_parts);
                        ?>
                        <li>
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                            <span><?php echo esc_html($full_address); ?></span>
                        </li>
                    <?php endif; ?>
                </ul>
<?php
                    $lang = rms_get_option('company_language');
                    if ($lang) : ?>
                    <p class="footer-v2__meta">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                        <strong>Languages:</strong> <?php echo esc_html($lang); ?>
                    </p>
                    <?php endif; ?>
                <p class="footer-v2__meta">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                    <strong>Payment Methods:</strong>
                    <?php
                    $methods = rms_get_option('company_payment_methods');
                    if (!empty($methods) && is_array($methods)) {
                        $labels = array_filter(array_column($methods, 'payment_method_name'));
                        if (!empty($labels)) {
                            echo esc_html(implode(', ', $labels));
                        } else {
                            echo 'None specified';
                        }
                    } else {
                        echo 'None specified';
                    }
                    ?>
                </p>
            </div>

        </div>
    </div>

    <div class="footer-v2__copyright">
        <div class="container">
            <p>© 2026 Simple RMS Theme. All rights reserved.</p>
        </div>
    </div>
</footer>
