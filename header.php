<!DOCTYPE html>
<html lang="<?php language_attributes(); ?>">
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php wp_title(); ?></title>
    <?php 
    // Inyectamos el CSS del Hero directamente para que el LCP sea instantáneo
    if (is_front_page()) {
        echo Vite_Icons_Integration::get_instance()->get_critical_css('src/scss/sections/hero.scss');
    }
    ?>
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
    
