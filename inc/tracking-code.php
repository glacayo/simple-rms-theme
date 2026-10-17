<?php
/**
 * Tracking Code (Google Tag Manager snippets)
 *
 * Renders the administrator-managed head script and body noscript fragments
 * stored in Theme Settings → Tracking code. Fragments are output verbatim:
 * escaping them would break GTM, so the control point is the capability gate
 * (only users with unfiltered_html can see or change the fields).
 *
 * @since 1.1.0
 */

/**
 * Retrieve a stored tracking fragment, trimmed.
 *
 * @param string $slot Either 'head' or 'body'.
 *
 * @return string Empty string when unset or ACF is unavailable.
 */
function rms_get_tracking_code(string $slot): string {
    if (!function_exists('get_field')) {
        return '';
    }

    $value = get_field("tracking_code_{$slot}", 'option', false);

    return is_string($value) ? trim($value) : '';
}

/**
 * Print a tracking fragment at most once per request.
 *
 * @param string $slot Either 'head' or 'body'.
 */
function rms_print_tracking_code(string $slot): void {
    static $printed = [];

    if (isset($printed[$slot])) {
        return;
    }

    $code = rms_get_tracking_code($slot);
    if ($code === '') {
        return;
    }
    $printed[$slot] = true;

    // Intentionally unescaped: privileged, capability-gated markup.
    echo $code . "\n";
}

/**
 * Head fragment. Priority 1 keeps it as early as practical inside wp_head(),
 * after the dataLayer bootstrap printed by header.php.
 */
function rms_render_tracking_head(): void {
    rms_print_tracking_code('head');
}
add_action('wp_head', 'rms_render_tracking_head', 1);

/**
 * Body fragment, immediately after <body> opens.
 */
function rms_render_tracking_body(): void {
    rms_print_tracking_code('body');
}
add_action('wp_body_open', 'rms_render_tracking_body', 1);

/**
 * Hide the tracking fields from users who cannot post unfiltered HTML.
 *
 * @param array $field ACF field.
 *
 * @return array|false
 */
function rms_tracking_code_prepare_field($field) {
    return current_user_can('unfiltered_html') ? $field : false;
}

/**
 * Refuse tracking field changes from users who cannot post unfiltered HTML,
 * keeping the stored value instead.
 *
 * @param mixed      $value   Submitted value.
 * @param int|string $post_id ACF post ID ('options').
 * @param array      $field   ACF field.
 *
 * @return mixed
 */
function rms_tracking_code_update_value($value, $post_id, $field) {
    if (current_user_can('unfiltered_html')) {
        return $value;
    }

    return get_field($field['name'], $post_id, false);
}

/**
 * Refuse direct option writes (e.g. wp-admin/options.php, which bypasses ACF
 * filters) from users who cannot post unfiltered HTML.
 *
 * @param mixed $value     New option value.
 * @param mixed $old_value Stored option value.
 *
 * @return mixed
 */
function rms_tracking_code_pre_update_option($value, $old_value) {
    return current_user_can('unfiltered_html') ? $value : $old_value;
}

foreach (['tracking_code_head', 'tracking_code_body'] as $rms_tracking_field) {
    add_filter("acf/prepare_field/name={$rms_tracking_field}", 'rms_tracking_code_prepare_field');
    add_filter("acf/update_value/name={$rms_tracking_field}", 'rms_tracking_code_update_value', 10, 3);
    add_filter("pre_update_option_options_{$rms_tracking_field}", 'rms_tracking_code_pre_update_option', 10, 2);
}
unset($rms_tracking_field);
