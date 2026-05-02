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
