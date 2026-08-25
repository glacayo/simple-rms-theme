<?php
/**
 * Footer V1 — compact identity rail.
 *
 * Renders logo/name, concise contact details, social links, and copyright.
 * Omits invented marketing copy, menus, and service lists.
 */

$identity_name = rms_get_option('company_name') ?: get_bloginfo('name');
$identity_name = is_string($identity_name) ? trim($identity_name) : '';
$footer_logo   = rms_get_option('company_logo_footer');
$phone         = rms_get_primary_phone();
$email         = rms_get_primary_email();
$socials       = rms_get_social_links();
$year          = (int) gmdate('Y');

$addr_line1 = rms_get_option('company_address_line_1');
$addr_city  = rms_get_option('company_city');
$addr_state = rms_get_option('company_state');
$address    = implode(', ', array_filter([
    is_string($addr_line1) ? $addr_line1 : '',
    is_string($addr_city) ? $addr_city : '',
    is_string($addr_state) ? $addr_state : '',
]));

$phone_clean = is_string($phone) ? preg_replace('/[^0-9+]/', '', $phone) : '';
$has_contact = ($phone !== '' && $phone_clean !== '') || $email !== '' || $address !== '';

$social_icons = [
    'facebook'  => '<svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg>',
    'instagram' => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/></svg>',
    'linkedin'  => '<svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"/><rect x="2" y="9" width="4" height="12"/><circle cx="4" cy="4" r="2"/></svg>',
    'x'         => '<svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>',
    'twitter'   => '<svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>',
    'youtube'   => '<svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M23.5 6.2a3 3 0 0 0-2.1-2.1C19.5 3.6 12 3.6 12 3.6s-7.5 0-9.4.5A3 3 0 0 0 .5 6.2 31.5 31.5 0 0 0 0 12a31.5 31.5 0 0 0 .5 5.8 3 3 0 0 0 2.1 2.1c1.9.5 9.4.5 9.4.5s7.5 0 9.4-.5a3 3 0 0 0 2.1-2.1A31.5 31.5 0 0 0 24 12a31.5 31.5 0 0 0-.5-5.8zM9.75 15.57V8.43L15.84 12z"/></svg>',
    'tiktok'    => '<svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M14.5 3h2.1c.2 1.8 1.2 3.4 2.8 4.4 1 .6 2.1.9 3.2 1v2.3c-1.5 0-3-.4-4.3-1.2v6.7A6.8 6.8 0 1 1 10 9.5v2.4a4.4 4.4 0 1 0 4.5 4.4V3z"/></svg>',
    'pinterest' => '<svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M12 2C6.5 2 2 6.5 2 12c0 4.2 2.6 7.8 6.3 9.2-.1-.8-.2-2 0-2.8l2.1-8.8s-.5-1.1-.5-2.6c0-2.5 1.4-4.3 3.2-4.3 1.5 0 2.3 1.1 2.3 2.5 0 1.5-1 3.8-1.5 5.9-.4 1.8.9 3.2 2.6 3.2 3.2 0 5.3-4.1 5.3-8.9 0-3.7-2.5-6.4-7-6.4A7.3 7.3 0 0 0 5.7 11c.5 1.1 1.4 2.9 1.4 2.9s-.5 2.1-.6 2.6A10 10 0 1 0 12 2z"/></svg>',
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
?>
<footer class="footer-v1" role="contentinfo">
    <div class="container footer-v1__inner">
        <div class="footer-v1__brand">
            <?php if (has_custom_logo()) : ?>
                <?php the_custom_logo(); ?>
            <?php elseif (!empty($footer_logo)) : ?>
                <a class="footer-v1__logo-link" href="<?php echo esc_url(home_url('/')); ?>">
                    <img
                        class="footer-v1__logo"
                        src="<?php echo esc_url($footer_logo); ?>"
                        alt="<?php echo esc_attr($identity_name !== '' ? $identity_name : __('Home', 'simple-rms-theme')); ?>"
                        width="140"
                        height="42"
                    >
                </a>
            <?php elseif ($identity_name !== '') : ?>
                <a class="footer-v1__wordmark" href="<?php echo esc_url(home_url('/')); ?>">
                    <?php echo esc_html($identity_name); ?>
                </a>
            <?php endif; ?>
        </div>

        <?php if ($has_contact) : ?>
            <ul class="footer-v1__contact">
                <?php if ($phone !== '' && $phone_clean !== '') : ?>
                    <li>
                        <a class="footer-v1__contact-link" href="<?php echo esc_url('tel:' . $phone_clean); ?>">
                            <span class="footer-v1__sr-only"><?php esc_html_e('Phone', 'simple-rms-theme'); ?></span>
                            <?php echo esc_html($phone); ?>
                        </a>
                    </li>
                <?php endif; ?>
                <?php if ($email !== '') : ?>
                    <li>
                        <a class="footer-v1__contact-link" href="<?php echo esc_url('mailto:' . $email); ?>">
                            <span class="footer-v1__sr-only"><?php esc_html_e('Email', 'simple-rms-theme'); ?></span>
                            <?php echo esc_html($email); ?>
                        </a>
                    </li>
                <?php endif; ?>
                <?php if ($address !== '') : ?>
                    <li>
                        <span class="footer-v1__address">
                            <span class="footer-v1__sr-only"><?php esc_html_e('Address', 'simple-rms-theme'); ?></span>
                            <?php echo esc_html($address); ?>
                        </span>
                    </li>
                <?php endif; ?>
            </ul>
        <?php endif; ?>

        <?php if ($social_items !== []) : ?>
            <nav class="footer-v1__social" aria-label="<?php echo esc_attr__('Social media', 'simple-rms-theme'); ?>">
                <?php foreach ($social_items as $social_item) : ?>
                    <a
                        class="footer-v1__social-link"
                        href="<?php echo esc_url($social_item['url']); ?>"
                        aria-label="<?php echo esc_attr($social_item['label']); ?>"
                        target="_blank"
                        rel="noopener noreferrer"
                    ><?php echo $social_item['icon']; ?></a>
                <?php endforeach; ?>
            </nav>
        <?php endif; ?>

        <p class="footer-v1__copyright">
            <?php if ($identity_name !== '') : ?>
                <?php echo esc_html('© ' . $year . ' ' . $identity_name . '. All rights reserved.'); ?>
            <?php else : ?>
                <?php echo esc_html('© ' . $year . '. All rights reserved.'); ?>
            <?php endif; ?>
        </p>
    </div>
</footer>
