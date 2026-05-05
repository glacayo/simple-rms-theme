<!-- CTA V1 — Bold Gradient Bar -->
<?php
$headline    = get_sub_field('cta_v1_headline') ?: 'Ready to Protect Your Home?';
$text        = get_sub_field('cta_v1_text') ?: 'Get a free inspection and estimate today. No obligations, no pressure.';
$button_text = get_sub_field('cta_v1_button_text') ?: 'Get Your Free Estimate';
$button_url  = get_sub_field('cta_v1_button_url') ?: '#contact';
?>
<section class="cta-v1">
    <div class="container">
        <h2 class="cta-v1__headline"><?php echo esc_html($headline); ?></h2>
        <p class="cta-v1__text"><?php echo esc_html($text); ?></p>
        <a href="<?php echo esc_url($button_url); ?>" class="btn cta-v1__button"><?php echo esc_html($button_text); ?></a>
    </div>
</section>
