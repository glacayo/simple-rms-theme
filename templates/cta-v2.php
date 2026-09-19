<!-- CTA V2 — Split Layout with Professional Feel -->
<?php
$headline       = get_sub_field('cta_v2_headline') ?: "Let's Build Something Great Together";
$text           = get_sub_field('cta_v2_text') ?: 'Contact us today to discuss your project and receive a detailed free estimate within 24 hours.';
$primary_text   = get_sub_field('cta_v2_primary_text') ?: 'Free Estimate';
$primary_url    = get_sub_field('cta_v2_primary_url') ?: '#contact';
$secondary_text = get_sub_field('cta_v2_secondary_text') ?: 'Call Now';

// The secondary control is a real click-to-call only when its destination is
// a structurally valid tel: target. An empty value derives a real fallback
// from the primary Theme Options phone; when no usable phone exists the
// secondary control is omitted rather than shipping a placeholder number.
$secondary_url = get_sub_field('cta_v2_secondary_url');
$secondary_url = is_string($secondary_url) ? trim($secondary_url) : '';

if ('' === $secondary_url && function_exists('rms_get_primary_phone') && function_exists('rms_format_tel_uri')) {
    $secondary_tel = rms_format_tel_uri(rms_get_primary_phone());
    if ('' !== $secondary_tel) {
        $secondary_url = 'tel:' . $secondary_tel;
    }
}

$secondary_href   = ('' !== $secondary_url) ? esc_url($secondary_url) : '';
$secondary_is_tel = ('' !== $secondary_href && 1 === preg_match('/^tel:\+?[0-9][0-9()\-\s.]*$/i', $secondary_url));
?>
<section class="cta-v2">
    <div class="container cta-v2__inner">
        <div class="cta-v2__content">
            <h2 class="cta-v2__headline"><?php echo esc_html($headline); ?></h2>
            <p class="cta-v2__text"><?php echo esc_html($text); ?></p>
        </div>
        <div class="cta-v2__actions">
            <a href="<?php echo esc_url($primary_url); ?>" class="btn cta-v2__button cta-v2__button--primary" data-raven-cta><?php echo esc_html($primary_text); ?></a>
            <?php if ('' !== $secondary_href) : ?>
            <a href="<?php echo esc_url($secondary_url); ?>" class="btn btn--outline cta-v2__button cta-v2__button--outline"<?php if ($secondary_is_tel) : ?> data-raven-call<?php endif; ?>><?php echo esc_html($secondary_text); ?></a>
            <?php endif; ?>
        </div>
    </div>
</section>
