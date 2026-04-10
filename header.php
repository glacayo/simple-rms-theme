<!DOCTYPE html>
<html lang="<?php language_attributes(); ?>">
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php
    $vite = Vite_Icons_Integration::get_instance();

    // Critical CSS — inlined to eliminate render-blocking requests
    echo $vite->get_critical_css('src/scss/main.scss', 'critical-base');

    // Page-specific critical CSS (e.g., hero on front page)
    if (is_front_page()) {
        echo $vite->get_critical_css('src/scss/sections/hero.scss', 'critical-hero');

        // Above the fold — eager load
        foreach (['slider', 'badges'] as $section) {
            $css = $vite->get_asset('src/scss/sections/' . $section . '.scss');
            if ($css) {
                wp_enqueue_style('section-' . $section, $css, [], null);
            }
        }

        // Below the fold — deferred (non-render-blocking)
        foreach (['about-us', 'services-v1', 'services-v2', 'services-v3', 'cta-v1', 'cta-v2', 'cta-v3', 'portfolio-v1', 'portfolio-v2', 'portfolio-v3'] as $section) {
            $vite->get_deferred_style('section-' . $section, 'src/scss/sections/' . $section . '.scss');
        }

        // Slider JS (auto-advance + dot navigation)
        $slider_js = $vite->get_asset('src/ts/slider.ts');
        if ($slider_js) {
            wp_enqueue_script('slider-js', $slider_js, [], null, true);
        }
    }

    // Header — loaded as separate <link> (not inline)
    $header_css = $vite->get_asset('src/scss/layout/header-one.scss');
    if ($header_css) {
        wp_enqueue_style('header-one', $header_css, [], null);
    }

    // Header menu JS
    $menu_js = $vite->get_asset('src/ts/header-one-menu.ts');
    if ($menu_js) {
        wp_enqueue_script('header-one-menu', $menu_js, [], null, true);
    }
    ?>
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>

<?php get_template_part('templates/header-one'); ?>