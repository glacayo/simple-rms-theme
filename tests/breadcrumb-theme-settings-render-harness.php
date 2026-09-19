<?php
/**
 * Render + style-contract harness for issue #125, slice 2: the full breadcrumb
 * template consumes the sanitized Theme Settings style contract and emits only
 * scoped CSS custom properties, one resolved mode class, and a conditional
 * image overlay. It also locks the CSS source contract and the byte-identical
 * slim template.
 *
 * Usage: php tests/breadcrumb-theme-settings-render-harness.php
 */

if ( PHP_SAPI !== 'cli' ) { fwrite( STDERR, "CLI only.\n" ); exit( 1 ); }
$theme_root = dirname( __DIR__ );
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', $theme_root . '/' ); }

// Template-time WP helpers the shared support does not stub.
if ( ! function_exists( 'is_single' ) ) { function is_single() { return false; } }
if ( ! function_exists( 'is_page_template' ) ) { function is_page_template( $t = '' ) { unset( $t ); return false; } }
if ( ! function_exists( 'get_the_title' ) ) { function get_the_title( $id = 0 ) { unset( $id ); return 'Page Title'; } }
if ( ! function_exists( 'wp_parse_url' ) ) { function wp_parse_url( $u, $c = -1 ) { return -1 === $c ? parse_url( $u ) : parse_url( $u, $c ); } }
if ( ! function_exists( 'esc_url_raw' ) ) { function esc_url_raw( $u ) { return trim( (string) $u ); } }
// Attribute-safe escaping: proves the output boundary calls esc_url().
if ( ! function_exists( 'esc_url' ) ) {
    function esc_url( $u ) {
        $u = trim( (string) $u );
        if ( '' === $u || preg_match( '#^(javascript|data):#i', $u ) ) { return ''; }
        return str_replace( '&', '&#038;', $u );
    }
}

require __DIR__ . '/support/header-cta-support.php';

/** Render the full breadcrumb with a given style-option set. */
function rms_bcr_render( array $fields ): string {
    rms_cta_setup( $fields );
    return rms_cta_render( 'breadcrumb.php' );
}

// ─── Image mode: mode class, esc_url() var, text var, overlay ────────────────
$out = rms_bcr_render( array(
    'company_breadcrumb_background_type'  => 'image',
    'company_breadcrumb_background_image' => 'https://cdn.example.test/hero.jpg?a=1&b=2',
    'company_breadcrumb_text_color'       => '#FFFFFF',
) );
rms_cta_assert( false !== strpos( $out, 'class="breadcrumb-page breadcrumb-page--image"' ), 'test_image_mode_class', 'image mode must emit exactly one resolved mode class' );
rms_cta_assert( false !== strpos( $out, "--breadcrumb-image: url('https://cdn.example.test/hero.jpg?a=1&#038;b=2')" ), 'test_image_var_uses_esc_url', 'image URL must reach the attribute through esc_url()' );
rms_cta_assert( false !== strpos( $out, '--breadcrumb-text: #FFFFFF' ), 'test_text_color_var', 'resolved text color must be emitted as a scoped var' );
rms_cta_assert( 1 === substr_count( $out, 'breadcrumb-page__overlay' ), 'test_image_overlay_present', 'a usable image must render exactly one overlay' );
rms_cta_assert( false === strpos( $out, 'placehold.co' ), 'test_no_placeholder_image', 'image mode must not fall back to a placeholder host' );

// ─── Image without a usable URL: no URL var, no overlay ──────────────────────
$out = rms_bcr_render( array( 'company_breadcrumb_background_type' => 'image', 'company_breadcrumb_background_image' => 'javascript:alert(1)' ) );
rms_cta_assert( false !== strpos( $out, 'breadcrumb-page--image' ) && false === strpos( $out, '--breadcrumb-image' ), 'test_image_invalid_url_no_var', 'an unusable image must emit no image var' );
rms_cta_assert( false === strpos( $out, 'breadcrumb-page__overlay' ), 'test_image_invalid_url_no_overlay', 'an unusable image must not render the overlay' );

// ─── Solid mode: color var, never an image or overlay ────────────────────────
$out = rms_bcr_render( array( 'company_breadcrumb_background_type' => 'solid', 'company_breadcrumb_solid_color' => '#0F172A', 'company_breadcrumb_background_image' => 'https://cdn.example.test/hero.jpg' ) );
rms_cta_assert( false !== strpos( $out, 'class="breadcrumb-page breadcrumb-page--solid"' ), 'test_solid_mode_class', 'solid mode must emit the solid mode class' );
rms_cta_assert( false !== strpos( $out, '--breadcrumb-solid: #0F172A' ), 'test_solid_color_var', 'solid color must be emitted as a scoped var' );
rms_cta_assert( false === strpos( $out, '--breadcrumb-image' ) && false === strpos( $out, 'breadcrumb-page__overlay' ), 'test_solid_no_image_or_overlay', 'solid mode must never show an image or overlay' );

// ─── Gradient mode: from/to/angle vars, never an image or overlay ────────────
$out = rms_bcr_render( array( 'company_breadcrumb_background_type' => 'gradient', 'company_breadcrumb_gradient_start' => '#2563eb', 'company_breadcrumb_gradient_end' => '#f59e0b', 'company_breadcrumb_gradient_angle' => '45' ) );
rms_cta_assert( false !== strpos( $out, 'class="breadcrumb-page breadcrumb-page--gradient"' ), 'test_gradient_mode_class', 'gradient mode must emit the gradient mode class' );
rms_cta_assert( false !== strpos( $out, '--breadcrumb-gradient-from: #2563eb' ) && false !== strpos( $out, '--breadcrumb-gradient-to: #f59e0b' ) && false !== strpos( $out, '--breadcrumb-gradient-angle: 45deg' ), 'test_gradient_vars', 'gradient stops and the allowlisted angle must be emitted' );
rms_cta_assert( false === strpos( $out, '--breadcrumb-image' ) && false === strpos( $out, 'breadcrumb-page__overlay' ), 'test_gradient_no_image_or_overlay', 'gradient mode must never show an image or overlay' );

// ─── Empty / invalid: neutral image mode, no vars, no overlay, no placeholder ─
$out = rms_bcr_render( array() );
rms_cta_assert( false !== strpos( $out, 'class="breadcrumb-page breadcrumb-page--image"' ), 'test_empty_defaults_to_image_class', 'missing options must resolve to the neutral image mode class' );
rms_cta_assert( false === strpos( $out, '--breadcrumb-' ) && false === strpos( $out, 'breadcrumb-page__overlay' ), 'test_empty_emits_no_vars_or_overlay', 'missing options must emit no custom properties or overlay' );
rms_cta_assert( false === strpos( $out, 'placehold.co' ), 'test_empty_no_placeholder', 'missing options must not fall back to a placeholder host' );

$out = rms_bcr_render( array( 'company_breadcrumb_background_type' => 'solid', 'company_breadcrumb_solid_color' => 'red' ) );
rms_cta_assert( false !== strpos( $out, 'breadcrumb-page--solid' ) && false === strpos( $out, '--breadcrumb-solid' ), 'test_solid_invalid_color_no_var', 'an invalid solid color must keep the compiled fallback and emit no var' );

// ─── Triangulate: allowlisted 0°, missing stops, invalid text color ──────────
$out = rms_bcr_render( array( 'company_breadcrumb_background_type' => 'gradient', 'company_breadcrumb_gradient_angle' => '0' ) );
rms_cta_assert( false !== strpos( $out, '--breadcrumb-gradient-angle: 0deg' ) && false === strpos( $out, '--breadcrumb-gradient-from' ) && false === strpos( $out, '--breadcrumb-gradient-to' ), 'test_gradient_zero_angle_and_neutral_stops', 'the allowlisted 0 angle must survive and missing stops must fall back to compiled neutrals' );

$out = rms_bcr_render( array( 'company_breadcrumb_background_type' => 'image', 'company_breadcrumb_background_image' => 'https://cdn.example.test/hero.jpg', 'company_breadcrumb_text_color' => 'not-a-color' ) );
rms_cta_assert( false !== strpos( $out, '--breadcrumb-image' ) && 1 === substr_count( $out, 'breadcrumb-page__overlay' ) && false === strpos( $out, '--breadcrumb-text' ), 'test_invalid_text_color_keeps_image', 'an invalid text color must drop only the text var, not the image or overlay' );

// ─── Escaping: hostile image input cannot break the attribute boundary ───────
$out = rms_bcr_render( array( 'company_breadcrumb_background_type' => 'image', 'company_breadcrumb_background_image' => 'https://cdn.example.test/x.jpg"><script>alert(1)</script>' ) );
rms_cta_assert( false === strpos( $out, '<script>' ) && false === strpos( $out, '"><' ) && false === strpos( $out, '--breadcrumb-image' ), 'test_hostile_url_escaped', 'hostile image input must not break out of the attribute' );

// ─── CSS source contract ─────────────────────────────────────────────────────
$scss = (string) file_get_contents( $theme_root . '/src/scss/templates/breadcrumb.scss' );
rms_cta_assert( false !== strpos( $scss, 'var(--breadcrumb-image' ) && false !== strpos( $scss, 'var(--breadcrumb-solid' ), 'test_scss_consumes_background_vars', 'SCSS must consume the image and solid vars' );
rms_cta_assert( false !== strpos( $scss, 'var(--breadcrumb-gradient-from' ) && false !== strpos( $scss, 'var(--breadcrumb-gradient-to' ) && false !== strpos( $scss, 'var(--breadcrumb-gradient-angle' ), 'test_scss_consumes_gradient_vars', 'SCSS must consume the gradient from/to/angle vars' );
rms_cta_assert( false !== strpos( $scss, 'var(--breadcrumb-text' ), 'test_scss_consumes_text_var', 'SCSS must apply the optional text color var' );
rms_cta_assert( false !== strpos( $scss, 'breadcrumb-page--slim' ), 'test_scss_keeps_slim_modifier', 'SCSS must keep the compact slim modifier' );
rms_cta_assert( false !== strpos( $scss, '.breadcrumb-page--solid .breadcrumb-page__overlay' ) && false !== strpos( $scss, '.breadcrumb-page--gradient .breadcrumb-page__overlay' ), 'test_scss_hides_overlay_for_non_image_modes', 'SCSS must hide the overlay for solid and gradient modes' );
rms_cta_assert( false !== strpos( $scss, 'var(--breadcrumb-solid, ' ) && false !== strpos( $scss, 'var(--breadcrumb-text, ' ), 'test_scss_neutral_compiled_fallbacks', 'SCSS vars must carry neutral compiled fallbacks' );

// ── Slim template: byte-identical and option-independent ────────────────────
$slim_path = $theme_root . '/templates/breadcrumb-slim.php';
rms_cta_assert( hash_file( 'sha256', $slim_path ) === '7ea3319c985a58eef840432580c43f6dfba1d0fd5e96ee8572fe0319d885ddbb', 'test_slim_template_byte_identical', 'breadcrumb-slim.php changed from the slice 1 bytes' );
rms_cta_setup( array( 'company_breadcrumb_background_type' => 'image', 'company_breadcrumb_background_image' => 'https://cdn.example.test/hero.jpg' ) );
$slim_out = rms_cta_render( 'breadcrumb-slim.php' );
rms_cta_assert( false !== strpos( $slim_out, 'breadcrumb-page--slim' ) && false === strpos( $slim_out, '--breadcrumb' ) && false === strpos( $slim_out, 'breadcrumb-page__overlay' ), 'test_slim_render_option_independent', 'slim render must stay a compact bar with no hero vars or overlay regardless of options' );

if ( $failures > 0 ) { fwrite( STDERR, "Breadcrumb theme settings render harness failed: {$failures} check(s).\n" ); exit( 1 ); }
echo "Breadcrumb theme settings render harness passed: modes, escaping, overlay truth, CSS contract, and byte-identical slim template.\n";
exit( 0 );