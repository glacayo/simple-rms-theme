<!DOCTYPE html>
<html lang="<?php language_attributes(); ?>">
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php
    // Critical CSS — inlined to eliminate render-blocking requests
    // Base styles (reset, typography, utilities)
    echo Vite_Icons_Integration::get_instance()->get_critical_css('src/scss/main.scss', 'critical-base');

    // Page-specific critical CSS (e.g., hero on front page)
    if (is_front_page()) {
        echo Vite_Icons_Integration::get_instance()->get_critical_css('src/scss/sections/hero.scss', 'critical-hero');
    }
    ?>
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
