<?php
/**
 * Focused schema harness for issue #56 (Theme Settings Header tab + Primary CTA
 * field contract).
 *
 * Proves the shipped ACF field group exposes:
 *   - a `Header` tab, and
 *   - an optional ACF `link` field named `company_header_primary_cta` with
 *     `array` return format and label "Header Primary CTA",
 *   and that the Header tab precedes the field in schema ordering.
 *
 * No framework. Single process; reads acf-json/group_rms_theme_settings.json
 * directly (no ACF runtime required).
 *
 * Usage: php tests/header-primary-cta-schema-harness.php
 */

if ( PHP_SAPI !== 'cli' ) {
    fwrite( STDERR, "CLI only.\n" );
    exit( 1 );
}

$theme_root = dirname( __DIR__ );

require __DIR__ . '/support/header-cta-support.php';

/**
 * Return the field-group array parsed from the shipped ACF JSON.
 *
 * @return array<string,mixed>
 */
function rms_cta_field_group(): array {
    global $theme_root;
    $path = $theme_root . '/acf-json/group_rms_theme_settings.json';
    if ( ! is_readable( $path ) ) {
        return array();
    }
    $raw = file_get_contents( $path );
    $data = json_decode( $raw, true );
    return is_array( $data ) ? $data : array();
}

/**
 * Find a top-level field by a matching predicate.
 *
 * @param callable $predicate
 * @return array<string,mixed>|null
 */
function rms_cta_find_field( array $fields, callable $predicate ) {
    foreach ( $fields as $field ) {
        if ( is_array( $field ) && $predicate( $field ) ) {
            return $field;
        }
    }
    return null;
}

// ─── Test 1: Header tab + link field schema ────────────────────────────────

$group  = rms_cta_field_group();
$fields = $group['fields'] ?? array();

$header_tab = rms_cta_find_field(
    $fields,
    function ( $f ) {
        return 'tab' === ( $f['type'] ?? '' ) && 'Header' === ( $f['label'] ?? '' );
    }
);

rms_cta_assert(
    null !== $header_tab,
    'test_theme_settings_header_tab_exposes_primary_cta_link (Header tab)',
    'group_rms_theme_settings.json has no Header tab'
);

$cta_field = rms_cta_find_field(
    $fields,
    function ( $f ) {
        return 'company_header_primary_cta' === ( $f['name'] ?? '' );
    }
);

rms_cta_assert(
    null !== $cta_field,
    'test_theme_settings_header_tab_exposes_primary_cta_link (field exists)',
    'no company_header_primary_cta field in group_rms_theme_settings.json'
);

if ( null !== $cta_field ) {
    rms_cta_assert(
        'link' === ( $cta_field['type'] ?? '' ),
        'test_theme_settings_header_tab_exposes_primary_cta_link (ACF link type)',
        'company_header_primary_cta is not an ACF link field'
    );

    rms_cta_assert(
        'array' === ( $cta_field['return_format'] ?? '' ),
        'test_theme_settings_header_tab_exposes_primary_cta_link (array return)',
        'company_header_primary_cta return_format is not array'
    );

    rms_cta_assert(
        'Header Primary CTA' === ( $cta_field['label'] ?? '' ),
        'test_theme_settings_header_tab_exposes_primary_cta_link (label)',
        'company_header_primary_cta label mismatch'
    );

    rms_cta_assert(
        empty( $cta_field['required'] ),
        'test_theme_settings_header_tab_exposes_primary_cta_link (optional)',
        'company_header_primary_cta must be optional (required falsy)'
    );
}

// Header tab must precede the CTA field (schema ordering).
if ( null !== $header_tab && null !== $cta_field ) {
    $tab_index = array_search( $header_tab, $fields, true );
    $field_index = array_search( $cta_field, $fields, true );
    rms_cta_assert(
        false !== $tab_index && false !== $field_index && $tab_index < $field_index,
        'test_theme_settings_header_tab_exposes_primary_cta_link (tab precedes field)',
        'Header tab must precede the company_header_primary_cta field'
    );
}

// ─── Summary ───────────────────────────────────────────────────────────────

if ( $failures > 0 ) {
    fwrite( STDERR, "Schema harness failed: {$failures} check(s).\n" );
    exit( 1 );
}

echo "Schema harness passed: header tab + primary CTA field contract.\n";
exit( 0 );
