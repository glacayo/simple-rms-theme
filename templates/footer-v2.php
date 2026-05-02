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
                if (!empty($socials)) :
                ?>
                <div class="footer-v2__social" aria-label="Social media links">
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
                        <a href="<?php echo esc_url($social['url']); ?>" class="footer-v2__social-link" aria-label="<?php echo esc_attr($social['label']); ?>" target="_blank" rel="noopener noreferrer"><?php echo $svg; ?></a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
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
                    $phones = get_field('company_phones', 'option');
                    $emails = get_field('company_emails', 'option');
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
                    $methods = get_field('company_payment_methods', 'option');
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
