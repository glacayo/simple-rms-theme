<?php
/**
 * ACF Theme Options
 *
 * Registers the ACF Options Page and all theme settings field groups.
 * Uses acf_add_local_field_group for code-based field registration.
 *
 * @since 1.0.0
 */

/**
 * Register ACF theme options after ACF is initialized.
 */
function rms_register_acf_theme_options(): void {
    if (!function_exists('acf_add_options_page') || !function_exists('acf_add_local_field_group')) {
        return;
    }

    // ─── Options Page Registration ─────────────────────────────────────────────

    acf_add_options_page([
        'page_title' => __('Theme Settings', 'simple-rms-theme'),
        'menu_title' => __('Theme Settings', 'simple-rms-theme'),
        'menu_slug'  => 'rms-theme-settings',
        'capability' => 'manage_options',
        'position'   => 80, // below Settings in admin menu
        'redirect'    => false,
    ]);

    // NOTE: Field group is now registered via acf-json/group_rms_theme_settings.json
    // ACF Pro JSON Sync handles automatic detection and database sync.
    // The JSON file must be present in the theme's acf-json/ directory.

}

add_action('acf/init', 'rms_register_acf_theme_options');

// ─── Helper Functions ─────────────────────────────────────────────────────

/**
 * Retrieve an ACF option value safely.
 *
 * @param string $field_name The option field name (without 'field_rms_' prefix).
 * @param mixed  $default    Default value if ACF is unavailable or field is empty.
 *
 * @return mixed
 */
function rms_get_option(string $field_name, $default = null) {
    if (!function_exists('get_field')) {
        return $default;
    }

    $value = get_field($field_name, 'option');

    return $value ?: $default;
}

/**
 * Map stored footer version values to canonical slugs.
 *
 * Offered choices are footer-v1 and footer-v2. Legacy aliases remain
 * readable so existing option rows still resolve after the rename.
 *
 * @return array<string,string>
 */
function rms_get_footer_version_aliases(): array {
    return [
        'footer-v1'  => 'footer-v1',
        'footer-v2'  => 'footer-v2',
        'footer-one' => 'footer-v1',
        'footer-two' => 'footer-v2',
    ];
}

/**
 * Whether a footer slug has both a PHP template and an authored stylesheet.
 */
function rms_footer_version_assets_exist(string $slug, string $theme_root = ''): bool {
    if ($slug === '') {
        return false;
    }

    $root = $theme_root !== ''
        ? $theme_root
        : (function_exists('get_template_directory') ? get_template_directory() : '');

    if ($root === '') {
        return false;
    }

    $template = $root . '/templates/' . $slug . '.php';
    $styles   = $root . '/src/scss/layout/' . $slug . '.scss';

    return is_readable($template) && is_readable($styles);
}

/**
 * Normalize a stored footer version to a verified, renderable slug.
 *
 * Empty, unknown, or incomplete assets fall back to footer-v2. The fallback
 * is returned only when its own PHP + SCSS exist; otherwise the resolver
 * fails closed with an empty string so callers can skip rendering.
 */
function rms_resolve_footer_version(?string $raw, string $theme_root = ''): string {
    $fallback  = 'footer-v2';
    $aliases   = rms_get_footer_version_aliases();
    $candidate = $raw ? sanitize_key($raw) : '';

    if ($candidate !== '' && isset($aliases[$candidate])) {
        $candidate = $aliases[$candidate];
    } else {
        $candidate = $fallback;
    }

    if (rms_footer_version_assets_exist($candidate, $theme_root)) {
        return $candidate;
    }

    if ($candidate !== $fallback && rms_footer_version_assets_exist($fallback, $theme_root)) {
        return $fallback;
    }

    return rms_footer_version_assets_exist($fallback, $theme_root) ? $fallback : '';
}

/**
 * Resolve the active footer version for header and footer dispatch.
 */
function rms_get_footer_version(): string {
    $raw = rms_get_option('company_footer_version');

    return rms_resolve_footer_version(is_string($raw) ? $raw : '');
}

/**
 * Present legacy footer aliases as the current ACF choices without changing the field key.
 *
 * @param mixed $value
 * @return mixed
 */
function rms_migrate_footer_version_value($value) {
    if (!is_string($value) || $value === '') {
        return $value;
    }

    $aliases = rms_get_footer_version_aliases();
    $key     = sanitize_key($value);

    return $aliases[$key] ?? $value;
}

if (function_exists('add_filter')) {
    add_filter('acf/load_value/key=field_rms_company_footer_version', 'rms_migrate_footer_version_value');
}

/**
 * Get the primary phone number from theme options.
 *
 * @return string
 */
function rms_get_primary_phone(): string {
    if (!function_exists('get_field')) {
        return '';
    }

    $phones = get_field('company_phones', 'option');

    if (!is_array($phones) || empty($phones)) {
        return '';
    }

    // Return the first phone number in the repeater
    return $phones[0]['phone_number'] ?? '';
}

/**
 * Get the primary email address from theme options.
 *
 * @return string
 */
function rms_get_primary_email(): string {
    if (!function_exists('get_field')) {
        return '';
    }

    $emails = get_field('company_emails', 'option');

    if (!is_array($emails) || empty($emails)) {
        return '';
    }

    // Return the first email address in the repeater
    return $emails[0]['email_address'] ?? '';
}

/**
 * Get active social media links from theme options.
 *
 * @return array Array of active social links, keyed by platform.
 */
function rms_get_social_links(): array {
    if (!function_exists('get_field')) {
        return [];
    }

    $socials = get_field('company_social_media', 'option');

    if (!is_array($socials) || empty($socials)) {
        return [];
    }

    $active = [];

    foreach ($socials as $social) {
        if (!empty($social['social_is_active']) && !empty($social['social_url'])) {
            $platform = $social['social_platform'] ?? 'other';
            $active[$platform] = [
                'url'   => $social['social_url'],
                'label' => $social['social_label'] ?? ucfirst($platform),
            ];
        }
    }

    return $active;
}
