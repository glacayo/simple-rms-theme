<?php
/**
 * Simple RMS Theme — Functions
 *
 * This file loads theme components in order.
 * Configuration lives in inc/setup.php, cleanup in inc/optimize.php.
 */

// ─── Theme Setup (supports, menus, image sizes) ─────────────────────
require_once get_template_directory() . '/inc/setup.php';

// ─── WordPress Cleanup & Optimization ───────────────────────────────
require_once get_template_directory() . '/inc/optimize.php';

// ─── Vite Asset Integration ─────────────────────────────────────────
require_once get_template_directory() . '/inc/vite-integration.php';

// ─── Structured Data (Schema) — AEO/GEO ─────────────────────────────
require_once get_template_directory() . '/inc/schema.php';

// ─── TGM Plugin Activation ────────────────────────────────────────
require_once get_template_directory() . '/inc/tgmpa.php';

