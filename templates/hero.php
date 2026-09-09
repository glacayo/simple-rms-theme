<?php
/**
 * Hero Section
 *
 * Reads ACF flexible content sub-fields and falls back to generic content
 * when fields are empty. The optional `hero_form_shortcode` renders a managed
 * contact form (e.g. the Contact Form 7 landing form); the value is
 * string-normalized, so a whitespace-only value renders no form column. The
 * two-column grid is applied only when a form exists. The template never
 * creates forms and never injects the generated shortcode by itself.
 */

$hero_bg_image      = get_sub_field('hero_bg_image');
$hero_reviews_label = get_sub_field('hero_reviews_label');
$hero_title         = get_sub_field('hero_title');
$hero_description   = get_sub_field('hero_description');
$hero_form_shortcode = trim( (string) get_sub_field('hero_form_shortcode') );
$has_hero_form       = '' !== $hero_form_shortcode;

$bg_url            = !empty($hero_bg_image) ? esc_url($hero_bg_image) : 'https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?w=1920&q=80';
$reviews_label     = !empty($hero_reviews_label) ? esc_html($hero_reviews_label) : '5.0 Google Reviews';
$title             = !empty($hero_title) ? esc_html($hero_title) : 'Professional Services You Can Trust';
$description       = !empty($hero_description) ? wp_kses_post($hero_description) : 'We deliver high-quality services for residential and commercial properties. Our experienced team ensures durable, reliable results backed by industry-leading warranties. From first inspection to full completion, we handle every project with precision and care.';
?>

<!-- Hero Section -->
<section class="hero" style="--hero-bg: url('<?php echo $bg_url; ?>');">
    <div class="hero__overlay hero__overlay--dark"></div>
    <div class="container">
        <div class="<?php echo esc_attr( $has_hero_form ? 'grid-2' : 'grid-1' ); ?>">

            <!-- Left Column -->
            <div class="hero__col-left">
                <div class="hero__reviews">
                    <span class="hero__reviews-stars" aria-label="5 out of 5 stars"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg></span>
                    <span class="hero__reviews-label"><?php echo $reviews_label; ?></span>
                </div>
                <h1 class="hero__title"><?php echo $title; ?></h1>
                <div class="hero__text">
                    <?php echo $description; ?>
                </div>
            </div>

            <?php if ( $has_hero_form ) : ?>
                <!-- Right Column — Managed Contact Form -->
                <div class="hero__col-right">
                    <div class="hero__form-card">
                        <?php echo do_shortcode($hero_form_shortcode); ?>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </div>
</section>
