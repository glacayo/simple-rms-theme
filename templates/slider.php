<?php
/**
 * Hero Slider — CSS Scroll Snap (zero JS)
 *
 * Reads ACF flexible content sub-fields and falls back to default
 * hardcoded slides when the repeater is empty.
 */

$slider_slides = get_sub_field('slider_slides');

$default_slides = [
    [
        'bg'           => 'https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?w=1920&q=80',
        'subheadline'  => 'Trusted Roofing Experts',
        'headline'     => 'Protecting What Matters Most to Your Family',
        'text'         => 'We deliver premium roofing solutions with unmatched craftsmanship and industry-leading warranties. Our certified team handles everything from minor repairs to complete roof replacements with precision and care every time.',
        'cta_text'     => 'Get Free Estimate',
        'cta_url'      => '#estimate',
    ],
    [
        'bg'           => 'https://images.unsplash.com/photo-1504307651254-35680f356dfd?w=1920&q=80',
        'subheadline'  => 'Quality You Can Count On',
        'headline'     => 'Residential Roofing Built to Last a Lifetime',
        'text'         => 'From asphalt shingles to metal roofing, we use only the highest quality materials installed by experienced professionals. Every project comes with comprehensive warranties and a commitment to your complete satisfaction.',
        'cta_text'     => 'Explore Services',
        'cta_url'      => '#services',
    ],
    [
        'bg'           => 'https://images.unsplash.com/photo-1558618666-fcd25c85f82e?w=1920&q=80',
        'subheadline'  => 'Emergency Response',
        'headline'     => 'Storm Damage Repair Available 24/7',
        'text'         => 'When disaster strikes, our emergency team responds fast to protect your property from further damage. We work directly with insurance companies to streamline your claim and get your roof restored quickly.',
        'cta_text'     => 'Call Now',
        'cta_url'      => '#contact',
    ],
];

$slides = (!empty($slider_slides) && is_array($slider_slides)) ? $slider_slides : [];
$has_acf_slides = !empty($slides);
$slides_to_render = $has_acf_slides ? $slides : $default_slides;
$slide_count = count($slides_to_render);
?>

<!-- Hero Slider — CSS Scroll Snap (zero JS) -->
<section class="slider" aria-label="Hero slider">
    <div class="slider__track">

        <?php foreach ($slides_to_render as $index => $slide) : ?>
            <?php
            if ($has_acf_slides) {
                $bg          = !empty($slide['slide_bg_image']) ? esc_url($slide['slide_bg_image']) : 'https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?w=1920&q=80';
                $subheadline = !empty($slide['slide_subheadline']) ? esc_html($slide['slide_subheadline']) : '';
                $headline    = !empty($slide['slide_headline']) ? esc_html($slide['slide_headline']) : '';
                $text        = !empty($slide['slide_text']) ? wp_kses_post($slide['slide_text']) : '';
                $cta_text    = !empty($slide['slide_cta_text']) ? esc_html($slide['slide_cta_text']) : '';
                $cta_url     = !empty($slide['slide_cta_url']) ? esc_url($slide['slide_cta_url']) : '#';
            } else {
                $bg          = esc_url($slide['bg']);
                $subheadline = esc_html($slide['subheadline']);
                $headline    = esc_html($slide['headline']);
                $text        = esc_html($slide['text']);
                $cta_text    = esc_html($slide['cta_text']);
                $cta_url     = esc_url($slide['cta_url']);
            }

            $tag = ($index === 0) ? 'h1' : 'h2';
            ?>
            <!-- Slide <?php echo (int) $index + 1; ?><?php echo ($index === 0) ? ' — only h1 on the page' : ''; ?> -->
            <div class="slider__slide" style="--slide-bg: url('<?php echo $bg; ?>');">
                <div class="slider__overlay slider__overlay--dark"></div>
                <div class="slider__content container">
                    <p class="slider__subheadline"><?php echo $subheadline; ?></p>
                    <<?php echo $tag; ?> class="slider__headline"><?php echo $headline; ?></<?php echo $tag; ?>>
                    <p class="slider__text"><?php echo $text; ?></p>
                    <a href="<?php echo $cta_url; ?>" class="btn btn--outline-white slider__cta"><?php echo $cta_text; ?></a>
                </div>
            </div>
        <?php endforeach; ?>

    </div>

    <!-- Scroll indicators -->
    <div class="slider__dots">
        <?php for ($i = 0; $i < $slide_count; $i++) : ?>
            <button class="slider__dot<?php echo ($i === 0) ? ' slider__dot--active' : ''; ?>" type="button" aria-label="Go to slide <?php echo (int) $i + 1; ?>" data-slide="<?php echo (int) $i; ?>"></button>
        <?php endfor; ?>
    </div>

    <!-- Bottom shape — transitions to next section -->
    <div class="slider__shape" aria-hidden="true">
        <svg viewBox="0 0 1440 120" preserveAspectRatio="none">
            <path d="M0,80 C360,120 720,0 1080,80 C1260,120 1380,40 1440,80 L1440,120 L0,120 Z" fill="#0f172a"/>
        </svg>
    </div>
</section>
