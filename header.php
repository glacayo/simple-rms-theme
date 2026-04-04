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