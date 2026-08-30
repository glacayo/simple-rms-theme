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

/**
 * Format a phone number as a sanitized `tel:` URI fragment.
 *
 * Keeps digits and, when present, a single leading `+` (international dialing
 * prefix). All other characters are dropped so the value is safe to place in a
 * `tel:` href after attribute escaping.
 *
 * @param string $phone Raw phone number from Theme Options.
 * @return string Digits (optionally preceded by a single leading `+`), or ''.
 */
function rms_format_tel_uri(string $phone): string {
    $raw    = trim($phone);
    $digits = preg_replace('/[^0-9+]/', '', $raw);
    $digits = is_string($digits) ? $digits : '';

    $has_leading_plus = ('' !== $digits && '+' === $digits[0]);
    $digits           = str_replace('+', '', $digits);

    return ($has_leading_plus ? '+' : '') . $digits;
}

/**
 * Resolve the Contact page permalink for header estimate CTAs.
 *
 * Looks up the known contact slugs via the established `get_page_by_path`
 * pattern and returns the first resolvable permalink. When no Contact page
 * exists, falls back to the on-page `#contact` anchor on the site home URL.
 *
 * @return string Resolved URL (permalink or fallback). Never empty.
 */
function rms_get_contact_page_url(): string {
    if (function_exists('get_page_by_path') && function_exists('get_permalink')) {
        foreach (array('contact-us', 'contact') as $slug) {
            $page = get_page_by_path($slug);
            if (is_object($page) && !empty($page->ID)) {
                $url = get_permalink($page->ID);
                if (is_string($url) && '' !== $url) {
                    return $url;
                }
            }
        }
    }

    return home_url('/#contact');
}

/**
 * Resolve the site-wide Header Primary CTA (link) from Theme Options.
 *
 * Reads the optional `company_header_primary_cta` ACF link field (array return
 * format) and returns a normalized `[url, title, target]` contract. Empty or
 * malformed link data falls back to the Contact page permalink (or
 * `home_url('/#contact')`) with the default "Get a Free Estimate" label and
 * `_self` target. The target is restricted to `_self`/`_blank`; any other value
 * becomes `_self`.
 *
 * @return array{url:string,title:string,target:string}
 */
function rms_get_header_primary_cta(): array {
    $default = array(
        'url'    => rms_get_contact_page_url(),
        'title'  => __('Get a Free Estimate', 'simple-rms-theme'),
        'target' => '_self',
    );

    if (!function_exists('get_field')) {
        return $default;
    }

    $link = get_field('company_header_primary_cta', 'option');

    if (!is_array($link)) {
        return $default;
    }

    $url = isset($link['url']) && is_string($link['url']) ? trim($link['url']) : '';

    // A bare fragment is a dead link; treat it like an empty value.
    if ('' === $url || '#' === $url) {
        return $default;
    }

    $title = isset($link['title']) && is_string($link['title']) && '' !== trim($link['title'])
        ? trim($link['title'])
        : $default['title'];

    $target = isset($link['target']) && is_string($link['target']) ? $link['target'] : '';
    if ('_blank' !== $target && '_self' !== $target) {
        $target = '_self';
    }

    return array(
        'url'    => $url,
        'title'  => $title,
        'target' => $target,
    );
}

// ─── Company Palette → CSS Custom Properties Bridge ────────────────────────

/**
 * Compiled per-field palette defaults.
 *
 * These match the shipped CSS literals so empty or invalid ACF values stay
 * visually inert:
 *   1 -> #0f172a (slate-900, body text + dark surface backgrounds)
 *   2 -> #2563eb (blue-600, primary CTA / button accent)
 *   3 -> #f59e0b (amber-500, review stars + secondary highlights)
 *   4 -> #ffffff (white, light surfaces + on-dark foreground)
 *
 * @return array{0:string,1:string,2:string,3:string}
 */
function rms_get_palette_defaults(): array {
    return ['#0f172a', '#2563eb', '#f59e0b', '#ffffff'];
}

/**
 * Sanitize a palette value to a strict hex color.
 *
 * Accepts only #RGB or #RRGGBB after trim. Invalid, empty, non-string, and
 * malicious values return null so callers can apply the stable default.
 * The raw input is never logged or echoed.
 *
 * @param mixed $value Candidate color from ACF/options.
 */
function rms_sanitize_palette_hex($value): ?string {
    if (!is_string($value)) {
        return null;
    }

    $value = trim($value);
    if ($value === '') {
        return null;
    }

    if (function_exists('sanitize_hex_color')) {
        $sanitized = sanitize_hex_color($value);
        if (!is_string($sanitized) || $sanitized === '') {
            return null;
        }
        $value = $sanitized;
    }

    if (!preg_match('/^#(?:[A-Fa-f0-9]{3}){1,2}$/', $value)) {
        return null;
    }

    return $value;
}

/**
 * Resolve the optional per-slide text color overrides to sanitized hex values.
 *
 * Reads the three optional slider color fields and accepts only strict hex
 * values (via rms_sanitize_palette_hex()). Invalid, empty, non-string, and
 * malicious values resolve to null so callers emit no override.
 *
 * @param array<string,mixed> $slide ACF slider row.
 * @return array{subheadline:?string,headline:?string,text:?string}
 */
function rms_get_slide_text_colors(array $slide): array {
    return [
        'subheadline' => rms_sanitize_palette_hex($slide['slide_subheadline_color'] ?? null),
        'headline'    => rms_sanitize_palette_hex($slide['slide_headline_color'] ?? null),
        'text'        => rms_sanitize_palette_hex($slide['slide_text_color'] ?? null),
    ];
}

/**
 * Build the scoped inline-style fragment for a slide's text color overrides.
 *
 * Emits CSS custom properties only for valid hex values so each slide's text
 * elements can consume them without leaking to other slides. Returns an empty
 * string when no valid override exists, preserving the palette/default chain.
 *
 * @param array<string,mixed> $slide ACF slider row.
 * @return string Inline style fragment (may be empty).
 */
function rms_get_slide_color_style(array $slide): string {
    $colors = rms_get_slide_text_colors($slide);
    $parts  = [];

    if (null !== $colors['subheadline']) {
        $parts[] = '--slide-subheadline-color:' . $colors['subheadline'];
    }
    if (null !== $colors['headline']) {
        $parts[] = '--slide-headline-color:' . $colors['headline'];
    }
    if (null !== $colors['text']) {
        $parts[] = '--slide-text-color:' . $colors['text'];
    }

    return [] === $parts ? '' : implode(';', $parts) . ';';
}

/**
 * Resolve the four ACF company_palette_color_* fields to sanitized hex values.
 *
 * Values are read through rms_get_option() and accepted only when they are
 * strict hex. Each field falls back independently to its compiled default.
 *
 * @return array{0:string,1:string,2:string,3:string} Four sanitized hex colors.
 */
function rms_get_palette_colors(): array {
    $defaults = rms_get_palette_defaults();

    return [
        rms_sanitize_palette_hex(rms_get_option('company_palette_color_1')) ?? $defaults[0],
        rms_sanitize_palette_hex(rms_get_option('company_palette_color_2')) ?? $defaults[1],
        rms_sanitize_palette_hex(rms_get_option('company_palette_color_3')) ?? $defaults[2],
        rms_sanitize_palette_hex(rms_get_option('company_palette_color_4')) ?? $defaults[3],
    ];
}

/**
 * Build the root custom-property block from sanitized palette colors.
 *
 * Only already-validated hex values are interpolated. This string is safe to
 * attach with wp_add_inline_style().
 */
function rms_get_palette_inline_css(): string {
    $defaults = rms_get_palette_defaults();
    $colors   = rms_get_palette_colors();
    $safe     = [];

    foreach ($defaults as $i => $fallback) {
        $candidate = is_string($colors[$i] ?? null) ? $colors[$i] : $fallback;
        $safe[$i]  = rms_sanitize_palette_hex($candidate) ?? $fallback;
    }

    return sprintf(
        ':root{--rms-color-primary:%s;--rms-color-accent:%s;--rms-color-accent-2:%s;--rms-color-surface:%s;}',
        $safe[0],
        $safe[1],
        $safe[2],
        $safe[3]
    );
}

/**
 * Enqueue the authored palette bridge stylesheet and emit sanitized root tokens.
 *
 * The bridge file (assets/css/rms-palette.css) is authored, non-generated
 * source. It is enqueued with the active header handle as a dependency so it
 * lands in <head> after the compiled theme CSS, letting source order and
 * equal specificity win for the targeted semantic overrides. No database writes.
 *
 * When ACF is unavailable the setup-safe shell does not call wp_head, and this
 * consumer also refuses to read palette settings or enqueue the bridge.
 */
function rms_enqueue_palette_bridge(): void {
    if (!function_exists('get_field')) {
        return;
    }

    $handle   = 'rms-palette';
    $css_uri  = get_template_directory_uri() . '/assets/css/rms-palette.css';
    $css_path = get_template_directory() . '/assets/css/rms-palette.css';

    if (!is_readable($css_path)) {
        return;
    }

    // The active header stylesheet is enqueued in header.php under a handle
    // matching its version slug (e.g. "header-two"). Declaring it as a
    // dependency guarantees our bridge prints after it. Invalid/empty values
    // fall back to header-one so the dependency list never contains junk.
    $header_raw     = rms_get_option('company_header_version');
    $header_version = is_string($header_raw) ? sanitize_key($header_raw) : '';
    if ($header_version === '') {
        $header_version = 'header-one';
    }

    $version = filemtime($css_path);
    $version = $version ? (string) $version : null;

    wp_enqueue_style($handle, $css_uri, [$header_version], $version);
    wp_add_inline_style($handle, rms_get_palette_inline_css());
}

add_action('wp_enqueue_scripts', 'rms_enqueue_palette_bridge', 20);
