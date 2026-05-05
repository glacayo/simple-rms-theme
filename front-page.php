<?php get_header(); ?>

<?php
if (have_rows('page_sections')) {
    get_template_part('templates/flexible-content');
} else {
    // Fallback to current hardcoded order
    get_template_part('templates/slider');
    get_template_part('templates/badges');
    get_template_part('templates/about-us');
    get_template_part('templates/services-v1');
    get_template_part('templates/cta-v2');
    get_template_part('templates/portfolio-v2');
    get_template_part('templates/testimonials-v3');
    get_template_part('templates/blog-v1');
    get_template_part('templates/area-coverage-v1');
    get_template_part('templates/vision-mission-v1');
    get_template_part('templates/vision-mission-v2');
    get_template_part('templates/seo-content');
    get_template_part('templates/faq-v1');
    get_template_part('templates/video-v1');
}
?>

<?php get_footer(); ?>
