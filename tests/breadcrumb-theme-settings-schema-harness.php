<?php
/**
 * Focused schema + sanitization harness for issue #125, slice 1: Breadcrumb
 * Theme Settings style contract (tab + optional fields, preserved tab order,
 * PHP/JSON allowlist parity, sanitized resolver with neutral defaults).
 *
 * Usage: php tests/breadcrumb-theme-settings-schema-harness.php
 */

if ( PHP_SAPI !== 'cli' ) { fwrite( STDERR, "CLI only.\n" ); exit( 1 ); }
$theme_root = dirname( __DIR__ );
// WP runtime helpers the shared support does not stub.
if ( ! function_exists( 'wp_parse_url' ) ) {
    function wp_parse_url( $url, $component = -1 ) { return -1 === $component ? parse_url( $url ) : parse_url( $url, $component ); }
}
if ( ! function_exists( 'esc_url_raw' ) ) {
    function esc_url_raw( $url ) { return trim( (string) $url ); }
}

require __DIR__ . '/support/header-cta-support.php';

$group  = json_decode( (string) file_get_contents( $theme_root . '/acf-json/group_rms_theme_settings.json' ), true );
$fields = is_array( $group ) ? ( $group['fields'] ?? array() ) : array();
/** Find a top-level ACF field by name. */
function rms_bc_field( array $fields, string $name ): ?array {
    foreach ( $fields as $field ) { if ( is_array( $field ) && $name === ( $field['name'] ?? '' ) ) { return $field; } }
    return null;
}

// name => [expected type, requires conditional_logic on the background-type field]
$contract = array(
    'company_breadcrumb_background_type'  => array( 'select', false ),
    'company_breadcrumb_background_image' => array( 'image', true ),
    'company_breadcrumb_solid_color'      => array( 'color_picker', true ),
    'company_breadcrumb_gradient_start'   => array( 'color_picker', true ),
    'company_breadcrumb_gradient_end'     => array( 'color_picker', true ),
    'company_breadcrumb_gradient_angle'   => array( 'select', true ),
    'company_breadcrumb_text_color'       => array( 'color_picker', false ),
);

// ─── Schema: Breadcrumb tab + field contract ───────────────────────────────
$tab_i = null;
foreach ( $fields as $i => $field ) {
    if ( is_array( $field ) && 'tab' === ( $field['type'] ?? '' ) && 'Breadcrumb' === ( $field['label'] ?? '' ) ) { $tab_i = $i; }
}
rms_cta_assert( null !== $tab_i, 'test_breadcrumb_tab_exists', 'no Breadcrumb tab' );
rms_cta_assert( null !== $tab_i && 'field_rms_tab_breadcrumb' === ( $fields[ $tab_i ]['key'] ?? '' ), 'test_breadcrumb_tab_key', 'Breadcrumb tab key must be field_rms_tab_breadcrumb' );

foreach ( $contract as $name => $spec ) {
    $field = rms_bc_field( $fields, $name );
    rms_cta_assert( null !== $field, "test_field_exists_{$name}", "missing ACF field {$name}" );
    if ( null === $field ) { continue; }
    rms_cta_assert( $spec[0] === ( $field['type'] ?? '' ), "test_field_type_{$name}", "{$name} must be {$spec[0]}" );
    rms_cta_assert( empty( $field['required'] ), "test_field_optional_{$name}", "{$name} must be optional" );
    rms_cta_assert( "field_rms_{$name}" === ( $field['key'] ?? '' ), "test_field_key_{$name}", "{$name} key mismatch" );
    rms_cta_assert( null !== $tab_i && array_search( $field, $fields, true ) > $tab_i, "test_field_after_tab_{$name}", "{$name} must follow the Breadcrumb tab" );
    if ( $spec[1] ) {
        $l = $field['conditional_logic'][0][0] ?? null;
        rms_cta_assert(
            is_array( $l ) && 'field_rms_company_breadcrumb_background_type' === ( $l['field'] ?? '' ) && '==' === ( $l['operator'] ?? '' )
                && in_array( $l['value'] ?? '', array( 'image', 'solid', 'gradient' ), true ),
            "test_field_conditional_{$name}", "{$name} must be conditional on the background type field"
        );
    }
}

$image = rms_bc_field( $fields, 'company_breadcrumb_background_image' );
rms_cta_assert( null !== $image && 'url' === ( $image['return_format'] ?? '' ), 'test_image_url_return_format', 'image field must return a URL' );

$original_tabs = array( 'General', 'Contact', 'Social Media', 'Branding', 'Layout', 'Footer', 'Header', 'Business', 'Schema', 'Contact Form 7' );
$pos = 0;
foreach ( $fields as $field ) {
    if ( is_array( $field ) && 'tab' === ( $field['type'] ?? '' ) && $pos < count( $original_tabs ) && $original_tabs[ $pos ] === ( $field['label'] ?? '' ) ) { $pos++; }
}
rms_cta_assert( $pos === count( $original_tabs ), 'test_existing_tab_order_preserved', 'existing tabs must keep their original relative order' );

// ─── PHP allowlists must mirror the JSON choices/defaults ────────────────────
$ready = function_exists( 'rms_get_breadcrumb_style' ) && function_exists( 'rms_get_breadcrumb_style_options' )
    && function_exists( 'rms_sanitize_breadcrumb_background_type' ) && function_exists( 'rms_sanitize_breadcrumb_angle' )
    && function_exists( 'rms_sanitize_breadcrumb_image_url' );
rms_cta_assert( $ready, 'test_breadcrumb_resolver_functions_exist', 'resolver + sanitizers must be defined' );

if ( $ready ) {
    $options     = rms_get_breadcrumb_style_options();
    $type_field  = rms_bc_field( $fields, 'company_breadcrumb_background_type' );
    $angle_field = rms_bc_field( $fields, 'company_breadcrumb_gradient_angle' );
    rms_cta_assert(
        $options['types'] === array_keys( $type_field['choices'] ?? array() ) && $options['default_type'] === ( $type_field['default_value'] ?? '' ),
        'test_type_allowlist_json_parity', 'PHP type allowlist/default must match JSON'
    );
    rms_cta_assert(
        array_map( 'strval', $options['angles'] ) === array_map( 'strval', array_keys( $angle_field['choices'] ?? array() ) )
            && (string) $options['default_angle'] === (string) ( $angle_field['default_value'] ?? '' ),
        'test_angle_allowlist_json_parity', 'PHP angle allowlist/default must match JSON'
    );

    // Missing input keeps the neutral compiled defaults (no URL, no overlay).
    rms_cta_setup( array() );
    $d = rms_get_breadcrumb_style();
    rms_cta_assert(
        'image' === $d['type'] && '' === $d['image_url'] && false === $d['has_image'] && false === $d['overlay'] && 180 === $d['angle']
            && null === $d['solid_color'] && null === $d['gradient_start'] && null === $d['gradient_end'] && null === $d['text_color'],
        'test_resolver_defaults_are_neutral', 'missing values must keep the neutral compiled defaults with no URL/overlay'
    );

    // Usable image: trimmed, overlay true, text color passed through.
    rms_cta_setup( array( 'company_breadcrumb_background_type' => 'image', 'company_breadcrumb_background_image' => "  https://cdn.example.test/hero.jpg  ", 'company_breadcrumb_text_color' => '#FFFFFF' ) );
    $s = rms_get_breadcrumb_style();
    rms_cta_assert(
        'image' === $s['type'] && 'https://cdn.example.test/hero.jpg' === $s['image_url'] && true === $s['has_image'] && true === $s['overlay'] && '#FFFFFF' === $s['text_color'],
        'test_resolver_usable_image_enables_overlay', 'usable image must be trimmed, keep overlay, and pass text color through'
    );

    // Unsafe/malformed images: no URL, no has_image, no overlay.
    $bad_images = array( 'javascript:alert(1)', 'data:text/html;base64,PHNjcmlwdD4=', '<img src=x onerror=alert(1)>', 'https://example.test/a b.jpg', "https://example.test/a'b.jpg", 'https://example.test/a"b.jpg', 'https://example.test/a(b).jpg', 'ftp://example.test/hero.jpg', 'https:///no-host.jpg', '../relative.jpg', '#', '', array( 'not', 'a string' ) );
    foreach ( $bad_images as $i => $bad ) {
        rms_cta_setup( array( 'company_breadcrumb_background_type' => 'image', 'company_breadcrumb_background_image' => $bad ) );
        $b = rms_get_breadcrumb_style();
        rms_cta_assert( '' === $b['image_url'] && false === $b['has_image'] && false === $b['overlay'], "test_resolver_rejects_image_{$i}", 'unsafe image must emit no URL/has_image/overlay' );
    }

    // Solid mode: color sanitized, image URL dropped, overlay false.
    rms_cta_setup( array( 'company_breadcrumb_background_type' => 'solid', 'company_breadcrumb_solid_color' => '#0F172A', 'company_breadcrumb_background_image' => 'https://cdn.example.test/hero.jpg' ) );
    $solid = rms_get_breadcrumb_style();
    rms_cta_assert( 'solid' === $solid['type'] && '#0F172A' === $solid['solid_color'] && '' === $solid['image_url'] && false === $solid['overlay'], 'test_resolver_solid_mode', 'solid mode must sanitize color, drop image URL, overlay false' );

    // Gradient mode: both stops and the allowlisted angle sanitized.
    rms_cta_setup( array( 'company_breadcrumb_background_type' => 'gradient', 'company_breadcrumb_gradient_start' => '#2563eb', 'company_breadcrumb_gradient_end' => '#f59e0b', 'company_breadcrumb_gradient_angle' => '45' ) );
    $g = rms_get_breadcrumb_style();
    rms_cta_assert( 'gradient' === $g['type'] && '#2563eb' === $g['gradient_start'] && '#f59e0b' === $g['gradient_end'] && 45 === $g['angle'], 'test_resolver_gradient_mode', 'gradient mode must sanitize both stops and the angle' );

    // Angle allowlist, including the valid 0, and rejection of everything else.
    rms_cta_setup( array( 'company_breadcrumb_gradient_angle' => '0' ) );
    rms_cta_assert( 0 === rms_get_breadcrumb_style()['angle'], 'test_angle_zero_allowed', '0 must be an allowlisted angle' );
    foreach ( array( '30', '360', '180;background:url(x)', 'ninety', -45, 180.5 ) as $i => $bad ) {
        rms_cta_setup( array( 'company_breadcrumb_gradient_angle' => $bad ) );
        rms_cta_assert( 180 === rms_get_breadcrumb_style()['angle'], "test_angle_rejects_{$i}", 'non-allowlisted angle must fall back to 180' );
    }

    // Strict hex only (CSS/markup/javascript injection rejected).
    foreach ( array( 'red', '#GGG', '#12345', 'url(https://example.test/x)', '#fff;background:url(x)', array( '#fff' ) ) as $i => $bad ) {
        rms_cta_setup( array( 'company_breadcrumb_solid_color' => $bad, 'company_breadcrumb_text_color' => $bad ) );
        $h = rms_get_breadcrumb_style();
        rms_cta_assert( null === $h['solid_color'] && null === $h['text_color'], "test_hex_rejects_{$i}", 'non-strict color must resolve to null' );
    }

    // Allowlisted background type only.
    foreach ( array( 'url(x)', 'solid;background:red', array( 'image' ) ) as $i => $bad ) {
        rms_cta_setup( array( 'company_breadcrumb_background_type' => $bad ) );
        rms_cta_assert( 'image' === rms_get_breadcrumb_style()['type'], "test_type_rejects_{$i}", 'non-allowlisted type must fall back to image' );
    }
}

if ( $failures > 0 ) { fwrite( STDERR, "Breadcrumb theme settings schema harness failed: {$failures} check(s).\n" ); exit( 1 ); }
echo "Breadcrumb theme settings schema harness passed: tab + field contract, allowlist parity, and sanitized resolver.\n";
exit( 0 );