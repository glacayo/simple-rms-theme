<?php
/**
 * Simple RMS Theme — Functions
 *
 * This file loads theme components in order.
 * Configuration lives in inc/setup.php, cleanup in inc/optimize.php.
 */

// ─── Theme Setup (supports, menus, image sizes) ─────────────────────
require_once get_template_directory() . '/inc/setup.php';

// ─── ACF Theme Options ─────────────────────────────────────────────────
require_once get_template_directory() . '/inc/acf-theme-options.php';

// ─── ACF Flexible Content — Page Sections ───────────────────────────────
require_once get_template_directory() . '/inc/acf-flexible-content.php';

// ─── WordPress Cleanup & Optimization ───────────────────────────────
require_once get_template_directory() . '/inc/optimize.php';

// ─── Vite Asset Integration ─────────────────────────────────────────
require_once get_template_directory() . '/inc/vite-integration.php';

// ─── Structured Data (Schema) — AEO/GEO ─────────────────────────────
require_once get_template_directory() . '/inc/schema.php';

// ─── TGM Plugin Activation ────────────────────────────────────────
require_once get_template_directory() . '/inc/tgmpa.php';

// ─── Favicon Filter ──────────────────────────────────────────────
add_filter('get_site_icon_url', 'rms_acf_favicon_filter', 20);

function rms_acf_favicon_filter($url) {
    $acf_favicon = rms_get_option('company_favicon');
    if (!empty($acf_favicon)) {
        return esc_url($acf_favicon);
    }
    return $url;
}

