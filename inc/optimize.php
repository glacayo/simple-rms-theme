<?php
/**
 * WordPress Cleanup & Optimization
 * Removes bloat and unnecessary defaults for performance.
 */

// ─── Remove WordPress Head Bloat ──────────────────────────────────────────

// Remove WordPress version from head
remove_action('wp_head', 'wp_generator');

// Remove WLW Manifest link
remove_action('wp_head', 'wlwmanifest_link');

// Remove RSD link (Really Simple Discovery)
remove_action('wp_head', 'rsd_link');

// Remove shortlink
remove_action('wp_head', 'wp_shortlink_wp_head');

// Remove WordPress emoji scripts (browsers handle this natively now)
remove_action('wp_head', 'print_emoji_detection_script', 7);
remove_action('wp_print_styles', 'print_emoji_styles');
add_filter('emoji_svg_url', '__return_false');

// Remove REST API link from head
remove_action('wp_head', 'rest_output_link_wp_head', 10);
remove_action('wp_head', 'wp_oembed_add_discovery_links', 10);

// Remove WordPress embed script
remove_action('wp_head', 'wp_oembed_add_host_js');

// ─── Disable Unnecessary Features ─────────────────────────────────────────

// Disable XML-RPC (security + performance)
add_filter('xmlrpc_enabled', '__return_false');

// Remove XML-RPC from head
remove_action('wp_head', 'rsd_link');

// Disable self-pingbacks
add_action('wp_head', function () {
    remove_action('wp_head', 'wp_shortlink_wp_head', 10, 0);
}, 0);

// Disable WordPress JSON API for non-authenticated users (optional)
// add_filter('rest_authentication_errors', function ($result) {
//     if (!is_user_logged_in()) {
//         return new WP_Error('rest_disabled', __('REST API is disabled.'), ['status' => 403]);
//     }
//     return $result;
// });

// ─── Dequeue Unnecessary Styles ───────────────────────────────────────────

function simple_rms_dequeue_styles() {
    // Remove block library CSS (not using Gutenberg editor)
    wp_dequeue_style('wp-block-library');
    wp_dequeue_style('wp-block-library-theme');

    // Remove global styles (theme.json CSS) if not using block theme
    wp_dequeue_style('global-styles');

    // Remove dashicons on frontend for non-logged users
    if (!is_admin() && !is_user_logged_in()) {
        wp_dequeue_style('dashicons');
    }
}
add_action('wp_enqueue_scripts', 'simple_rms_dequeue_styles', 100);

// ─── Dequeue Unnecessary Scripts ──────────────────────────────────────────

function simple_rms_dequeue_scripts() {
    // Remove jQuery migrate (not needed with modern jQuery)
    if (!is_admin()) {
        wp_deregister_script('jquery');
    }
}
add_action('wp_enqueue_scripts', 'simple_rms_dequeue_scripts', 100);

// ─── Preload Critical Fonts (example) ─────────────────────────────────────
// Uncomment and customize when you load custom fonts

// add_action('wp_head', function () {
//     echo '<link rel="preconnect" href="https://fonts.googleapis.com">';
//     echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
//     echo '<link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">';
// }, 1);
