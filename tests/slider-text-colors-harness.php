<?php
/**
 * Focused harness for issue #65 (per-slide text color controls).
 *
 * Proves, causally and without a framework:
 *   1. The PHP ACF registration exposes exactly three optional `color_picker`
 *      fields on each slider row: slide_subheadline_color, slide_headline_color,
 *      slide_text_color (correct names, labels, optional/empty defaults).
 *   2. The generated acf-json/group_rms_page_sections.json is synchronized
 *      exactly with the PHP registration (byte-equal modulo line endings +
 *      the generated `modified` timestamp).
 *   3. Valid saved colors render scoped CSS custom properties and affect only
 *      their matching text element (subheadline / headline / body).
 *   4. Empty and invalid colors emit no override and preserve the palette /
 *      compiled-default fallback chain.
 *   5. Different slides remain isolated (a color on slide 1 never leaks to
 *      slide 2).
 *   6. Existing slides render unchanged when the color fields are absent
 *      (both ACF rows without the keys and the hardcoded default slides).
 *   7. The color fields are absent from the AI content mappings (fillable,
 *      text-repeater, editorial rules), the review validation strips them, and
 *      the wizard generic builder emits empty values (no invented copy).
 *   8. Sanitization rejects style injection and only strict hex survives.
 *
 * Reuses tests/support/header-cta-support.php for the shared WP/ACF/template
 * stubs and the rms_cta_* render/assert helpers (the repo's canonical
 * standalone-render stub). Slider/wizard-specific stubs are declared inline.
 *
 * Usage: php tests/slider-text-colors-harness.php
 */

if ( PHP_SAPI !== 'cli' ) {
    fwrite( STDERR, "CLI only.\n" );
    exit( 1 );
}

$theme_root = dirname( __DIR__ );

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', $theme_root . '/' );
}

// Shared stubs + rms_cta_* helpers. Also requires inc/acf-theme-options.php,
// which provides rms_sanitize_palette_hex() and the new rms_get_slide_* helpers
// under test.
require __DIR__ . '/support/header-cta-support.php';

// ─── Slider-template + wizard stubs not covered by the shared support ──────

if ( ! function_exists( 'get_sub_field' ) ) {
    function get_sub_field( $selector, $format_value = true ) {
        return $GLOBALS['rms_slider_sub'][ $selector ] ?? null;
    }
}

if ( ! function_exists( 'wp_kses_post' ) ) {
    function wp_kses_post( $data ) {
        return (string) $data;
    }
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
    function sanitize_text_field( $value ) {
        return is_scalar( $value ) ? (string) $value : '';
    }
}

if ( ! function_exists( 'esc_url_raw' ) ) {
    function esc_url_raw( $url ) {
        return (string) $url;
    }
}

if ( ! function_exists( 'acf_add_local_field_group' ) ) {
    function acf_add_local_field_group( array $field_group ): void {
        $GLOBALS['rms_captured_group'] = $field_group;
    }
}

// ─── Helpers ────────────────────────────────────────────────────────────────

function rms_slider_setup( array $slides ): void {
    $GLOBALS['rms_slider_sub'] = array( 'slider_slides' => $slides );
}

function rms_slide_row( array $overrides = array() ): array {
    return array_merge(
        array(
            'slide_bg_image'    => 'https://example.test/bg.jpg',
            'slide_subheadline' => 'Trusted Roofing Experts',
            'slide_headline'    => 'Protecting What Matters Most',
            'slide_text'        => '<p>We deliver premium roofing solutions.</p>',
            'slide_cta_text'    => 'Get Free Estimate',
            'slide_cta_url'     => '#estimate',
        ),
        $overrides
    );
}

/**
 * Extract the slider repeater's color_picker sub-fields from a field group,
 * keyed by field name. Works for both the captured PHP group and the decoded
 * ACF JSON group (identical structure).
 */
function rms_slider_color_fields( array $group ): array {
    $layouts  = $group['fields'][0]['layouts'] ?? array();
    $repeater = $layouts['layout_slider']['sub_fields'][0] ?? null;
    $subs     = is_array( $repeater['sub_fields'] ?? null ) ? $repeater['sub_fields'] : array();
    $colors   = array();

    foreach ( $subs as $field ) {
        if ( ! is_array( $field ) || 'color_picker' !== ( $field['type'] ?? '' ) ) {
            continue;
        }
        $colors[ (string) $field['name'] ] = $field;
    }

    return $colors;
}

$color_names = array( 'slide_subheadline_color', 'slide_headline_color', 'slide_text_color' );

// ─── 1. PHP registration exposes exactly 3 optional color fields ────────────

$GLOBALS['rms_captured_group'] = null;
require_once $theme_root . '/inc/acf-flexible-content.php';
rms_register_acf_page_sections();
$php_group = is_array( $GLOBALS['rms_captured_group'] ) ? $GLOBALS['rms_captured_group'] : array();

rms_cta_assert(
    is_array( $php_group ) && ( $php_group['key'] ?? '' ) === 'group_rms_page_sections',
    'test_php_registration_captured',
    'the page sections field group must be captured from PHP registration'
);

$php_colors = rms_slider_color_fields( $php_group );

rms_cta_assert(
    3 === count( $php_colors ),
    'test_php_registration_three_color_fields',
    'the slider row must expose exactly three color_picker fields'
);
rms_cta_assert(
    $color_names === array_keys( $php_colors ),
    'test_php_registration_exact_names',
    'the color fields must be named exactly slide_subheadline_color, slide_headline_color, slide_text_color'
);
rms_cta_assert(
    'Subheadline Color' === ( $php_colors['slide_subheadline_color']['label'] ?? '' ),
    'test_php_registration_subheadline_label',
    'the subheadline color label must be "Subheadline Color"'
);
rms_cta_assert(
    'Headline Color' === ( $php_colors['slide_headline_color']['label'] ?? '' ),
    'test_php_registration_headline_label',
    'the headline color label must be "Headline Color"'
);
rms_cta_assert(
    'Text Color' === ( $php_colors['slide_text_color']['label'] ?? '' ),
    'test_php_registration_text_label',
    'the body text color label must be "Text Color"'
);

foreach ( $php_colors as $name => $field ) {
    rms_cta_assert(
        empty( $field['required'] ) && '' === ( $field['default_value'] ?? '' ),
        'test_php_registration_optional_' . $name,
        "$name must be optional (not required) with an empty default"
    );
}

// ─── 2. Generated ACF JSON is synchronized exactly with PHP ─────────────────

$json_raw   = file_get_contents( $theme_root . '/acf-json/group_rms_page_sections.json' );
$json_group = is_string( $json_raw ) ? json_decode( $json_raw, true ) : array();
$json_colors = is_array( $json_group ) ? rms_slider_color_fields( $json_group ) : array();

rms_cta_assert(
    3 === count( $json_colors ) && $color_names === array_keys( $json_colors ),
    'test_json_three_color_fields',
    'the generated JSON must expose the same three color fields as the PHP registration'
);
rms_cta_assert(
    $php_colors === $json_colors,
    'test_json_color_fields_match_php_exactly',
    'each JSON color field must match its PHP definition exactly (keys, labels, types, defaults)'
);

// Byte-level sync: re-encode the captured group exactly as the generator does,
// normalizing the on-disk file to LF so CRLF checkout does not false-negative.
$php_group['modified'] = $json_group['modified'] ?? 0;
$regenerated = json_encode( $php_group, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n";
$disk_lf     = str_replace( "\r\n", "\n", (string) $json_raw );

rms_cta_assert(
    $regenerated === $disk_lf,
    'test_json_sync_exact',
    're-running the generator must reproduce the committed JSON byte-for-byte (line-ending agnostic)'
);

// ─── 3. Valid saved colors render scoped variables, only intended element ───

$palette_css = (string) file_get_contents( $theme_root . '/assets/css/rms-palette.css' );

rms_slider_setup( array(
    rms_slide_row( array(
        'slide_subheadline_color' => '#ff0000',
        'slide_headline_color'    => '#00ff00',
        'slide_text_color'        => '#0000ff',
    ) ),
) );
$html = rms_cta_render( 'slider.php' );

rms_cta_assert( false !== strpos( $html, '--slide-subheadline-color:#ff0000' ), 'test_valid_subheadline_color_rendered', 'a valid subheadline color must emit its scoped custom property' );
rms_cta_assert( false !== strpos( $html, '--slide-headline-color:#00ff00' ), 'test_valid_headline_color_rendered', 'a valid headline color must emit its scoped custom property' );
rms_cta_assert( false !== strpos( $html, '--slide-text-color:#0000ff' ), 'test_valid_text_color_rendered', 'a valid body text color must emit its scoped custom property' );

// Only the matching element consumes each variable (single consumer, no leakage).
rms_cta_assert( false !== strpos( $palette_css, '.slider__subheadline' ) && false !== strpos( $palette_css, 'var(--slide-subheadline-color, var(--rms-color-accent-2))' ), 'test_subheadline_var_scoped_to_subheadline', 'the subheadline variable must resolve only on .slider__subheadline' );
rms_cta_assert( false !== strpos( $palette_css, '.slider__headline' ) && false !== strpos( $palette_css, 'var(--slide-headline-color, #ffffff)' ), 'test_headline_var_scoped_to_headline', 'the headline variable must resolve only on .slider__headline' );
rms_cta_assert( false !== strpos( $palette_css, '.slider__text' ) && false !== strpos( $palette_css, 'var(--slide-text-color, #cbd5e1)' ), 'test_text_var_scoped_to_text', 'the body text variable must resolve only on .slider__text' );
rms_cta_assert( 1 === substr_count( $palette_css, '--slide-headline-color' ) && 1 === substr_count( $palette_css, '--slide-subheadline-color' ) && 1 === substr_count( $palette_css, '--slide-text-color' ), 'test_color_vars_single_consumer', 'each color variable must have exactly one CSS consumer' );

// ─── 4. Empty / invalid colors emit no override, preserve fallback chain ────

rms_slider_setup( array(
    rms_slide_row( array(
        'slide_subheadline_color' => '',
        'slide_headline_color'    => '',
        'slide_text_color'        => '',
    ) ),
) );
$html = rms_cta_render( 'slider.php' );
rms_cta_assert( false === strpos( $html, '--slide-subheadline-color' ) && false === strpos( $html, '--slide-headline-color' ) && false === strpos( $html, '--slide-text-color' ), 'test_empty_colors_emit_no_override', 'empty color values must emit no scoped custom properties' );

rms_slider_setup( array(
    rms_slide_row( array(
        'slide_subheadline_color' => 'red',
        'slide_headline_color'    => 'rgb(0,0,0)',
        'slide_text_color'        => '#12',
    ) ),
) );
$html = rms_cta_render( 'slider.php' );
rms_cta_assert( false === strpos( $html, '--slide-subheadline-color' ) && false === strpos( $html, '--slide-headline-color' ) && false === strpos( $html, '--slide-text-color' ), 'test_invalid_colors_emit_no_override', 'invalid (non-hex) color values must emit no scoped custom properties' );

// Fallback chain: subheadline keeps the live palette token; headline/text keep
// the compiled literals when no per-slide value exists.
rms_cta_assert( false !== strpos( $palette_css, 'var(--slide-subheadline-color, var(--rms-color-accent-2))' ), 'test_subheadline_fallback_keeps_palette', 'subheadline must fall back to the live palette accent-2 token' );
rms_cta_assert( false !== strpos( $palette_css, 'var(--slide-headline-color, #ffffff)' ), 'test_headline_fallback_compiled_literal', 'headline must fall back to the compiled #ffffff literal' );
rms_cta_assert( false !== strpos( $palette_css, 'var(--slide-text-color, #cbd5e1)' ), 'test_text_fallback_compiled_literal', 'body text must fall back to the compiled #cbd5e1 literal' );

// ─── 5. Different slides remain isolated ────────────────────────────────────

rms_slider_setup( array(
    rms_slide_row( array( 'slide_headline_color' => '#ff0000' ) ),
    rms_slide_row(),
) );
$html = rms_cta_render( 'slider.php' );

$slide_markup = explode( 'class="slider__slide"', $html );
rms_cta_assert( 3 === count( $slide_markup ), 'test_two_slide_markup_split', 'two slides must render two .slider__slide elements' );
rms_cta_assert( false !== strpos( $slide_markup[1] ?? '', '--slide-headline-color:#ff0000' ), 'test_slide1_has_headline_color', 'slide 1 must carry its own headline color override' );
rms_cta_assert( false === strpos( $slide_markup[2] ?? '', '--slide-headline-color' ) && false === strpos( $slide_markup[2] ?? '', '--slide-subheadline-color' ) && false === strpos( $slide_markup[2] ?? '', '--slide-text-color' ), 'test_slide2_has_no_color_override', 'slide 2 must not inherit any color override from slide 1' );

// ─── 6. Existing slides render unchanged when fields are absent ─────────────

// ACF slide without any color keys (original six fields only).
rms_slider_setup( array( rms_slide_row() ) );
$html = rms_cta_render( 'slider.php' );
rms_cta_assert( false === strpos( $html, '--slide-subheadline-color' ) && false === strpos( $html, '--slide-headline-color' ) && false === strpos( $html, '--slide-text-color' ), 'test_acf_slide_without_colors_unchanged', 'an ACF slide without color fields must render no color overrides' );
rms_cta_assert( false !== strpos( $html, 'class="slider__slide" style="--slide-bg: url(' ), 'test_acf_slide_bg_style_intact', 'the slide background inline style must remain intact' );

// Empty repeater -> hardcoded default slides (no migration path touches them).
rms_slider_setup( array() );
$html = rms_cta_render( 'slider.php' );
rms_cta_assert( false === strpos( $html, '--slide-subheadline-color' ) && false === strpos( $html, '--slide-headline-color' ) && false === strpos( $html, '--slide-text-color' ), 'test_default_slides_no_color_vars', 'default slides must render no color overrides' );
rms_cta_assert( false !== strpos( $html, 'Protecting What Matters Most to Your Family' ), 'test_default_slide_headline_preserved', 'the default slide headline must remain unchanged' );
rms_cta_assert( false !== strpos( $html, 'slider__subheadline' ) && false !== strpos( $html, 'slider__cta' ), 'test_default_slide_structure_preserved', 'the default slide subheadline and CTA structure must remain intact' );

// ─── 7. Color fields absent from AI mappings / review / canonical copy ──────

require_once $theme_root . '/inc/wizard/class-ai-content-harness.php';
require_once $theme_root . '/inc/wizard/class-flexible-content-layouts.php';

$harness         = new \Inc\Wizard\AI_Content_Harness();
$fillable        = $harness->get_fillable_fields( 'slider' );
$text_repeaters  = $harness->get_text_repeater_fields( 'slider' );
$editorial       = \Inc\Wizard\AI_Content_Harness::get_editorial_rules( 'slider' );
$editorial_keys  = array_keys( $editorial['fields'] ?? array() );
$slider_subs     = $text_repeaters['slider_slides'] ?? array();

foreach ( $color_names as $name ) {
    rms_cta_assert( ! in_array( $name, $fillable, true ), 'test_ai_fillable_excludes_' . $name, "$name must not be an AI fillable field" );
    rms_cta_assert( ! in_array( $name, $slider_subs, true ), 'test_ai_text_repeater_excludes_' . $name, "$name must not be an AI text-repeater subfield" );
    rms_cta_assert( ! in_array( $name, $editorial_keys, true ), 'test_ai_editorial_excludes_' . $name, "$name must not appear in AI editorial rules" );
}

// Review pipeline strips a color field even if a row smuggles it in.
$validated = $harness->validate_fields( 'slider', array(
    'slider_slides' => array(
        array( 'slide_headline' => 'Hi', 'slide_headline_color' => '#ff0000' ),
    ),
) );
rms_cta_assert(
    ! array_key_exists( 'slide_headline_color', $validated['slider_slides'][0] ?? array() ),
    'test_ai_validate_strips_color_field',
    'validate_fields must strip a color field from slider rows'
);

// Generic builder emits empty values for color fields (no invented copy), so
// wizard generation and canonical section rows never carry AI text in them.
$layouts = new \Inc\Wizard\Flexible_Content_Layouts();
$section = $layouts->build_generic_section( 'slider', array( 'company_name' => 'Acme Concrete' ), array() );
$row     = $section['slider_slides'][0] ?? array();
rms_cta_assert( '' === ( $row['slide_subheadline_color'] ?? null ), 'test_generic_builder_empty_subheadline_color', 'the generic builder must emit an empty subheadline color' );
rms_cta_assert( '' === ( $row['slide_headline_color'] ?? null ), 'test_generic_builder_empty_headline_color', 'the generic builder must emit an empty headline color' );
rms_cta_assert( '' === ( $row['slide_text_color'] ?? null ), 'test_generic_builder_empty_text_color', 'the generic builder must emit an empty body text color' );

// ─── 8. Escaping / sanitization prevents style injection ────────────────────

rms_cta_assert( null === rms_sanitize_palette_hex( 'red; background: url(javascript:alert(1))' ), 'test_sanitizer_rejects_css_injection', 'the sanitizer must reject CSS property injection' );
rms_cta_assert( null === rms_sanitize_palette_hex( '</style><script>alert(1)</script>' ), 'test_sanitizer_rejects_markup', 'the sanitizer must reject markup injection' );
rms_cta_assert( null === rms_sanitize_palette_hex( 'rgb(0,0,0)' ), 'test_sanitizer_rejects_rgb', 'the sanitizer must reject non-hex formats' );
rms_cta_assert( null === rms_sanitize_palette_hex( '#12345' ), 'test_sanitizer_rejects_bad_hex', 'the sanitizer must reject malformed hex' );
rms_cta_assert( '#ff0000' === rms_sanitize_palette_hex( '#ff0000' ), 'test_sanitizer_accepts_hex', 'the sanitizer must accept valid hex' );

rms_slider_setup( array(
    rms_slide_row( array(
        'slide_subheadline_color' => 'red; background: url(javascript:alert(1))',
        'slide_headline_color'    => '</style><script>alert(1)</script>',
        'slide_text_color'        => '" onload="alert(1)',
    ) ),
) );
$html = rms_cta_render( 'slider.php' );
rms_cta_assert( false === strpos( $html, '--slide-subheadline-color' ) && false === strpos( $html, '--slide-headline-color' ) && false === strpos( $html, '--slide-text-color' ), 'test_injection_emits_no_override', 'malicious color values must emit no scoped custom properties' );
rms_cta_assert( false === stripos( $html, '<script>' ) && false === stripos( $html, 'javascript:' ) && false === stripos( $html, 'onload=' ), 'test_injection_no_raw_payload', 'malicious payloads must not survive into the rendered markup' );

// ─── Summary ───────────────────────────────────────────────────────────────

if ( $failures > 0 ) {
    fwrite( STDERR, "Slider text colors harness failed: {$failures} check(s).\n" );
    exit( 1 );
}

echo "Slider text colors harness passed: registration, JSON sync, scoped rendering, fallback chain, isolation, no-regression, AI exclusion, and injection safety.\n";
exit( 0 );
