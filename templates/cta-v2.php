<!-- CTA V2 — Split Layout with Professional Feel -->
<?php
$headline       = get_sub_field('cta_v2_headline') ?: "Let's Build Something Great Together";
$text           = get_sub_field('cta_v2_text') ?: 'Contact us today to discuss your project and receive a detailed free estimate within 24 hours.';
$primary_text   = get_sub_field('cta_v2_primary_text') ?: 'Free Estimate';
$primary_url    = get_sub_field('cta_v2_primary_url') ?: '#contact';
$secondary_text = get_sub_field('cta_v2_secondary_text') ?: 'Call Now';
$secondary_url  = get_sub_field('cta_v2_secondary_url') ?: 'tel:+1234567890';
?>
<section class="cta-v2">
    <div class="container cta-v2__inner">
        <div class="cta-v2__content">
            <h2 class="cta-v2__headline"><?php echo esc_html($headline); ?></h2>
            <p class="cta-v2__text"><?php echo esc_html($text); ?></p>
        </div>
        <div class="cta-v2__actions">
            <a href="<?php echo esc_url($primary_url); ?>" class="btn cta-v2__button cta-v2__button--primary"><?php echo esc_html($primary_text); ?></a>
            <a href="<?php echo esc_url($secondary_url); ?>" class="btn btn--outline cta-v2__button cta-v2__button--outline"><?php echo esc_html($secondary_text); ?></a>
        </div>
    </div>
</section>
