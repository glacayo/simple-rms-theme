<?php
/**
 * Theme Setup
 * Registers supports, menus, image sizes, and other theme configuration.
 */

function simple_rms_setup() {
    // ─── Theme Supports ─────────────────────────────────────────────
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('automatic-feed-links');
    add_theme_support('html5', [
        'search-form',
        'comment-form',
        'comment-list',
        'gallery',
        'caption',
        'style',
        'script',
    ]);
    add_theme_support('custom-logo', [
        'height'      => 60,
        'width'       => 200,
        'flex-height' => true,
        'flex-width'  => true,
    ]);
    add_theme_support('responsive-embeds');
    add_theme_support('align-wide');

    // ─── Navigation Menus ──────────────────────────────────────────
    register_nav_menus([
        'primary'   => __('Primary Menu', 'simple-rms-theme'),
        'footer'    => __('Footer Menu', 'simple-rms-theme'),
        'mobile'    => __('Mobile Menu', 'simple-rms-theme'),
    ]);

    // ─── Custom Image Sizes ────────────────────────────────────────
    add_image_size('hero-desktop', 1920, 1080, true);
    add_image_size('hero-mobile', 768, 600, true);
    add_image_size('card-thumbnail', 600, 400, true);
    add_image_size('project-thumbnail', 800, 600, true);
    add_image_size('testimonial-avatar', 96, 96, true);

    // Make sizes available in media library
    add_filter('image_size_names_choose', function ($sizes) {
        return array_merge($sizes, [
            'hero-desktop'      => __('Hero Desktop', 'simple-rms-theme'),
            'hero-mobile'       => __('Hero Mobile', 'simple-rms-theme'),
            'card-thumbnail'    => __('Card Thumbnail', 'simple-rms-theme'),
            'project-thumbnail' => __('Project Thumbnail', 'simple-rms-theme'),
        ]);
    });

    // ─── Content Width ─────────────────────────────────────────────
    global $content_width;
    if (!isset($content_width)) {
        $content_width = 1280;
    }
}
add_action('after_setup_theme', 'simple_rms_setup');

// ─── Widget Areas ────────────────────────────────────────────────────────
function simple_rms_widgets_init() {
    register_sidebar([
        'name'          => __('Footer Widgets', 'simple-rms-theme'),
        'id'            => 'footer-widgets',
        'before_widget' => '<div class="footer-widget %2$s">',
        'after_widget'  => '</div>',
        'before_title'  => '<h3 class="footer-widget__title">',
        'after_title'   => '</h3>',
    ]);
}
add_action('widgets_init', 'simple_rms_widgets_init');
