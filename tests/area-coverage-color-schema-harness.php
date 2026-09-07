<?php
/**
 * Focused harness for issue #101, part 1: area coverage color schema.
 *
 * Proves, causally and without a framework:
 *   1. The PHP ACF registration exposes exactly three optional `color_picker`
 *      fields on the area-coverage-v1 layout: area_eyebrow_color,
 *      area_headline_color, area_text_color (correct names, labels,
 *      optional/empty defaults).
 *   2. The generated acf-json/group_rms_page_sections.json is synchronized
 *      exactly with the PHP registration (byte-equal modulo line endings +
 *      the generated `modified` timestamp).
 *   3. The color fields are excluded from the AI content mappings (fillable,
 *      blocked, editorial rules), review validation strips them, the generic
 *      builder and section assembler never write them (no invented copy), and
 *      the canonical store preserves editor values across reruns via the
 *      existing first-write architecture.
 *
 * Rendering, sanitization, and template behavior stay with the second stacked
 * PR: tests/area-coverage-text-colors-harness.php.
 *
 * Reuses tests/support/header-cta-support.php for the shared WP/ACF stubs and
 * the rms_cta_assert helpers. Wizard/canonical-store stubs are declared
 * inline.
 *
 * Usage: php tests/area-coverage-color-schema-harness.php
 */

if ( PHP_SAPI !== 'cli' ) {
    fwrite( STDERR, "CLI only.\n" );
    exit( 1 );
}

$theme_root = dirname( __DIR__ );

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', $theme_root . '/' );
}

// Shared stubs + rms_cta_* helpers. Also requires inc/acf-theme-options.php.
require __DIR__ . '/support/header-cta-support.php';

// ─── Wizard/canonical-store stubs not covered by the shared support ─────────

if ( ! function_exists( 'acf_add_local_field_group' ) ) {
    function acf_add_local_field_group( array $field_group ): void {
        $GLOBALS['rms_captured_group'] = $field_group;
    }
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
    function sanitize_text_field( $value ) {
        return is_scalar( $value ) ? trim( (string) $value ) : '';
    }
}

if ( ! function_exists( 'wp_kses_post' ) ) {
    function wp_kses_post( $data ) {
        return (string) $data;
    }
}

if ( ! function_exists( 'esc_url_raw' ) ) {
    function esc_url_raw( $url ) {
        return (string) $url;
    }
}

// Canonical store persistence stubs (option-backed, deterministic).
if ( ! function_exists( 'get_option' ) ) {
    function get_option( $name, $default = false ) {
        return $GLOBALS['rms_canonical_option_store'][ $name ] ?? $default;
    }
}

if ( ! function_exists( 'update_option' ) ) {
    function update_option( $name, $value, $autoload = null ) {
        $GLOBALS['rms_canonical_option_store'][ $name ] = $value;
        return true;
    }
}

if ( ! function_exists( 'current_time' ) ) {
    function current_time( $type, $gmt = 0 ) {
        return '2026-01-01 00:00:00';
    }
}

/**
 * Extract the area-coverage-v1 layout's color_picker sub-fields from a field
 * group, keyed by field name. Works for both the captured PHP group and the
 * decoded ACF JSON group (identical structure).
 */
function rms_area_color_fields( array $group ): array {
    $layouts = $group['fields'][0]['layouts'] ?? array();
    $layout  = $layouts['layout_area_coverage_v1'] ?? null;
    $subs    = is_array( $layout['sub_fields'] ?? null ) ? $layout['sub_fields'] : array();
    $colors  = array();

    foreach ( $subs as $field ) {
        if ( ! is_array( $field ) || 'color_picker' !== ( $field['type'] ?? '' ) ) {
            continue;
        }
        $colors[ (string) $field['name'] ] = $field;
    }

    return $colors;
}

$color_names = array( 'area_eyebrow_color', 'area_headline_color', 'area_text_color' );

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

$php_colors = rms_area_color_fields( $php_group );

rms_cta_assert(
    3 === count( $php_colors ),
    'test_php_registration_three_color_fields',
    'the area-coverage-v1 layout must expose exactly three color_picker fields'
);
rms_cta_assert(
    $color_names === array_keys( $php_colors ),
    'test_php_registration_exact_names',
    'the color fields must be named exactly area_eyebrow_color, area_headline_color, area_text_color'
);
rms_cta_assert(
    'Eyebrow Color' === ( $php_colors['area_eyebrow_color']['label'] ?? '' ),
    'test_php_registration_eyebrow_label',
    'the eyebrow color label must be "Eyebrow Color"'
);
rms_cta_assert(
    'Headline Color' === ( $php_colors['area_headline_color']['label'] ?? '' ),
    'test_php_registration_headline_label',
    'the headline color label must be "Headline Color"'
);
rms_cta_assert(
    'Text Color' === ( $php_colors['area_text_color']['label'] ?? '' ),
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

$json_raw    = file_get_contents( $theme_root . '/acf-json/group_rms_page_sections.json' );
$json_group  = is_string( $json_raw ) ? json_decode( $json_raw, true ) : array();
$json_colors = is_array( $json_group ) ? rms_area_color_fields( $json_group ) : array();

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
$regenerated           = json_encode( $php_group, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n";
$disk_lf               = str_replace( "\r\n", "\n", (string) $json_raw );

rms_cta_assert(
    $regenerated === $disk_lf,
    'test_json_sync_exact',
    're-running the generator must reproduce the committed JSON byte-for-byte (line-ending agnostic)'
);

// ─── 3. Color fields excluded from AI mappings; canonical rerun preservation ─

require_once $theme_root . '/inc/wizard/class-ai-content-harness.php';
require_once $theme_root . '/inc/wizard/class-flexible-content-layouts.php';

$harness        = new \Inc\Wizard\AI_Content_Harness();
$fillable       = $harness->get_fillable_fields( 'area-coverage-v1' );
$blocked        = $harness->get_blocked_fields( 'area-coverage-v1' );
$editorial      = \Inc\Wizard\AI_Content_Harness::get_editorial_rules( 'area-coverage-v1' );
$editorial_keys = array_keys( $editorial['fields'] ?? array() );

foreach ( $color_names as $name ) {
    rms_cta_assert( ! in_array( $name, $fillable, true ), 'test_ai_fillable_excludes_' . $name, "$name must not be an AI fillable field" );
    rms_cta_assert( ! in_array( $name, $editorial_keys, true ), 'test_ai_editorial_excludes_' . $name, "$name must not appear in AI editorial rules" );
    rms_cta_assert( in_array( $name, $blocked, true ), 'test_ai_blocked_includes_' . $name, "$name must be listed in the AI blocked fields" );
}

// Review pipeline strips a color field even if the payload smuggles it in.
$validated = $harness->validate_fields( 'area-coverage-v1', array(
    'area_headline'       => 'Hi',
    'area_headline_color' => '#ff0000',
) );
rms_cta_assert(
    ! array_key_exists( 'area_headline_color', $validated ),
    'test_ai_validate_strips_color_field',
    'validate_fields must strip a color field from the area-coverage payload'
);

// Generic builder and section assembler never write color values (no invented
// copy), so wizard generation and canonical rows never carry AI text in them.
$layouts = new \Inc\Wizard\Flexible_Content_Layouts();
$generic = $layouts->build_generic_section( 'area-coverage-v1', array( 'company_name' => 'Acme Concrete' ), array() );
foreach ( $color_names as $name ) {
    rms_cta_assert( ! array_key_exists( $name, $generic ), 'test_generic_builder_omits_' . $name, 'the generic builder must not emit ' . $name );
}

require_once $theme_root . '/inc/wizard/class-section-assembler.php';
$assembler = new \Inc\Wizard\Section_Assembler( new \Inc\Wizard\AI_Content_Harness() );
$assembled = $assembler->section_data( 'area-coverage-v1', array( 'company_name' => 'Acme Concrete' ), array(), 4 );
foreach ( $color_names as $name ) {
    rms_cta_assert( ! array_key_exists( $name, $assembled ), 'test_assembler_omits_' . $name, 'the section assembler must not write ' . $name );
}

// Canonical rerun preservation: the existing first-write store carries editor
// color values through wizard reruns (generated rows never overwrite them).
rms_cta_assert( true === $harness->is_reusable_layout( 'area-coverage-v1' ), 'test_layout_is_reusable', 'area-coverage-v1 must be canonical-reusable so seed_from_section_rows can carry editor rows' );

require_once $theme_root . '/inc/wizard/class-canonical-section-store.php';
$store          = new \Inc\Wizard\Canonical_Section_Store();
$editorial_row  = array( 'acf_fc_layout' => 'area-coverage-v1', 'area_headline' => 'Editor Headline', 'area_eyebrow_color' => '#123456', 'area_headline_color' => '#654321', 'area_text_color' => '#abcdef' );
rms_cta_assert( true === $store->set_if_empty( 'area-coverage-v1', $editorial_row ), 'test_canonical_first_write_accepts_editor_row', 'the canonical store must accept the editor row with color values on first write' );
$generated_row  = array( 'acf_fc_layout' => 'area-coverage-v1', 'area_headline' => 'Generated Headline' );
rms_cta_assert( false === $store->set_if_empty( 'area-coverage-v1', $generated_row ), 'test_canonical_rerun_does_not_overwrite', 'a generated rerun row must not overwrite the stored editorial row' );
$persisted      = $store->get( 'area-coverage-v1' );
rms_cta_assert( '#123456' === ( $persisted['area_eyebrow_color'] ?? null ) && '#654321' === ( $persisted['area_headline_color'] ?? null ) && '#abcdef' === ( $persisted['area_text_color'] ?? null ), 'test_canonical_preserves_editor_colors', 'the canonical store must preserve editor color values across reruns' );
rms_cta_assert( 'Editor Headline' === ( $persisted['area_headline'] ?? '' ), 'test_canonical_preserves_editor_copy', 'the stored editorial payload must remain the first write, not the rerun row' );
$store->reset_cache();

// ─── Summary ─────────────────────────────────────────────────────────────────

if ( $failures > 0 ) {
    fwrite( STDERR, "Area coverage color schema harness failed: {$failures} check(s).\n" );
    exit( 1 );
}

echo "Area coverage color schema harness passed: three optional color fields, PHP/JSON parity, AI exclusion, and canonical rerun preservation.\n";
exit( 0 );