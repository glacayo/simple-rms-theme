<?php
$headline   = get_sub_field('seo_headline') ?: 'Content Section Built for SEO and Flexibility';
$subheadline = get_sub_field('seo_subheadline') ?: 'Reusable layout prepared for ACF integration';
$text       = get_sub_field('seo_text');
$image      = get_sub_field('seo_image');
$modifier   = get_sub_field('seo_modifier') ?: 'default';
$bg_style   = get_sub_field('seo_bg_style') ?: 'light';
$bg_image   = get_sub_field('seo_bg_image');

// Build section classes
$classes = 'seo-content';
if ($modifier === 'reverse') $classes .= ' seo-content--reverse';
if ($bg_style === 'dark') $classes .= ' seo-content--bg-dark';
elseif ($bg_style === 'light') $classes .= ' seo-content--bg-light';
if ($bg_image) $classes .= ' seo-content--has-bg-image seo-content--overlay-dark';

$bg_style_attr = $bg_image ? "--seo-bg-image: url('" . esc_url($bg_image) . "');" : '';
?>
<!-- SEO Content -- Reusable Two-Column Section -->
<!--
  Modifier classes available:
    .seo-content--reverse         -> swap columns (image left / text right)
    .seo-content--bg-light        -> light gray background ($color-gray-50)
    .seo-content--bg-dark         -> dark background ($color-gray-900)
    .seo-content--has-bg-image    -> background image via CSS variable --seo-bg-image
    .seo-content--overlay-dark    -> dark overlay on top of background image

  CSS custom properties:
    --seo-bg-image: url('...')    -> background image when using .seo-content--has-bg-image
    --seo-bg-color: #hex          -> override solid background color

  Heading block is optional -- remove .seo-content__header entirely and the section still works.

  Usage examples:
    1. Default light background:
       <section class="seo-content seo-content--bg-light">
    2. Reversed columns, dark background:
       <section class="seo-content seo-content--reverse seo-content--bg-dark">
    3. Background image with overlay:
       <section class="seo-content seo-content--has-bg-image seo-content--overlay-dark" style="--seo-bg-image: url('https://placehold.co/1600x900');">
-->
<section class="<?php echo esc_attr($classes); ?>" aria-labelledby="seo-content-heading"<?php if ($bg_style_attr) echo ' style="' . esc_attr($bg_style_attr) . '"'; ?>>
    <div class="container">

        <!-- Optional heading block -- remove this div if no heading is needed -->
        <div class="seo-content__header">
            <h2 id="seo-content-heading" class="seo-content__headline"><?php echo esc_html($headline); ?></h2>
            <h3 class="seo-content__subheadline"><?php echo esc_html($subheadline); ?></h3>
        </div>

        <div class="seo-content__grid grid-2">
            <!-- Text column -->
            <div class="seo-content__text">
                <?php if ($text) : ?>
                    <?php echo wp_kses_post($text); ?>
                <?php else : ?>
                    <p>Our company is built around dependable workmanship, clear communication, and long-term protection for every property we serve. We approach each project with a detailed process that starts with understanding the client's goals and ends with a result that adds value, improves durability, and gives homeowners confidence that their investment is protected against changing weather conditions year after year.</p>

                    <p>We work with residential and commercial clients who need practical solutions, honest recommendations, and materials selected for performance instead of shortcuts. Every system we install is planned with attention to structure, drainage, ventilation, and finish quality so the final result looks professional, performs reliably, and supports the long-term condition of the entire property with fewer avoidable maintenance issues.</p>

                    <p>Whether the need is a replacement, a repair, ongoing maintenance, or storm recovery, we focus on delivering steady results through organized timelines, transparent estimates, and responsive service. That combination helps clients move forward with clarity, avoid surprises during the process, and feel confident that the work was completed by a team committed to quality, accountability, and lasting performance from day one.</p>
                <?php endif; ?>
            </div>

            <!-- Image column -->
            <div class="seo-content__media">
                <img
                    class="seo-content__image"
                    src="<?php echo $image ? esc_url($image) : 'https://placehold.co/700x500'; ?>"
                    alt="Professional roofing team working on a residential project"
                    width="700"
                    height="500"
                    loading="lazy"
                >
            </div>
        </div>

    </div>
</section>
