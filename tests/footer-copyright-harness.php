<?php
/**
 * Focused harness for issue #116 (shared footer copyright).
 * Proves: optional Theme Settings text field `company_footer_copyright`;
 * helper `rms_get_footer_copyright()` returns the trimmed managed value when
 * present and otherwise a safe fallback from wp_date('Y') plus site identity;
 * Footer V1 and Footer V2 both render through that helper with no independent
 * literals or duplicated formatting. Usage: php tests/footer-copyright-harness.php
 */

if ( PHP_SAPI !== 'cli' ) { fwrite( STDERR, "CLI only.\n" ); exit( 1 ); }
$GLOBALS['rms_copy_theme_root'] = dirname( __DIR__ );
$GLOBALS['rms_copy_fields']     = array();
$GLOBALS['rms_copy_year']       = '2031';
$failures = 0;

function rms_copy_assert( bool $condition, string $name, string $message ): void {
	global $failures;
	if ( $condition ) { fwrite( STDOUT, "PASS {$name}\n" ); }
	else { $failures++; fwrite( STDERR, "FAIL {$name}: {$message}\n" ); }
}

if ( ! function_exists( '__' ) ) { function __( $text, $domain = 'default' ) { return $text; } }
if ( ! function_exists( 'esc_html' ) ) { function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); } }
if ( ! function_exists( 'esc_attr' ) ) { function esc_attr( $text ) { return esc_html( $text ); } }
if ( ! function_exists( 'esc_url' ) ) { function esc_url( $url ) { $url = trim( (string) $url ); return ( '' === $url || preg_match( '#^(javascript|data):#i', $url ) ) ? '' : $url; } }
if ( ! function_exists( 'esc_attr__' ) ) { function esc_attr__( $text, $domain = 'default' ) { return esc_attr( $text ); } }
if ( ! function_exists( 'home_url' ) ) { function home_url( $path = '/' ) { return 'https://example.test' . $path; } }
if ( ! function_exists( 'get_bloginfo' ) ) { function get_bloginfo( $show = '' ) { if ( 'name' === $show ) { return (string) ( $GLOBALS['rms_copy_fields']['blogname'] ?? '' ); } return ''; } }
if ( ! function_exists( 'add_action' ) ) { function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) { return true; } }
if ( ! function_exists( 'get_field' ) ) { function get_field( $selector, $post_id = false ) { return $GLOBALS['rms_copy_fields'][ $selector ] ?? null; } }
if ( ! function_exists( 'has_custom_logo' ) ) { function has_custom_logo() { return false; } }
if ( ! function_exists( 'the_custom_logo' ) ) { function the_custom_logo() {} }
if ( ! function_exists( 'has_nav_menu' ) ) { function has_nav_menu( $location ) { return false; } }
if ( ! function_exists( 'wp_nav_menu' ) ) { function wp_nav_menu( $args = array() ) { return ''; } }
if ( ! function_exists( 'wp_date' ) ) { function wp_date( $format, $timestamp = null, $timezone = null ) { return (string) ( $GLOBALS['rms_copy_year'] ?? gmdate( (string) $format ) ); } }

require $GLOBALS['rms_copy_theme_root'] . '/inc/acf-theme-options.php';

function rms_copy_copyright(): string {
	if ( ! function_exists( 'rms_get_footer_copyright' ) ) {
		return '';
	}
	// pi-lens-ignore: intelephense:P1010
	return rms_get_footer_copyright();
}

function rms_copy_render( string $template, array $fields ): string {
	$GLOBALS['rms_copy_fields'] = $fields;
	ob_start();
	include $GLOBALS['rms_copy_theme_root'] . '/templates/' . $template;
	return (string) ob_get_clean();
}

// ─── 1. Schema: optional Footer copyright text field ─────────────────────────
$group  = json_decode( (string) file_get_contents( $GLOBALS['rms_copy_theme_root'] . '/acf-json/group_rms_theme_settings.json' ), true );
$fields = is_array( $group ) ? ( $group['fields'] ?? array() ) : array();
$copyright_field = null;
$about_idx       = null;
$copyright_idx   = null;
$header_tab_idx  = null;
foreach ( $fields as $i => $field ) {
	if ( ! is_array( $field ) ) { continue; }
	if ( 'company_footer_about' === ( $field['name'] ?? '' ) ) { $about_idx = $i; }
	if ( 'company_footer_copyright' === ( $field['name'] ?? '' ) ) { $copyright_field = $field; $copyright_idx = $i; }
	if ( 'tab' === ( $field['type'] ?? '' ) && 'Header' === ( $field['label'] ?? '' ) ) { $header_tab_idx = $i; }
}
rms_copy_assert( null !== $copyright_field, 'test_theme_settings_footer_copyright_field_exists', 'group_rms_theme_settings.json has no company_footer_copyright field' );
if ( null !== $copyright_field ) {
	rms_copy_assert( 'text' === ( $copyright_field['type'] ?? '' ), 'test_footer_copyright_is_text', 'company_footer_copyright must be a text field, got ' . json_encode( $copyright_field['type'] ?? null ) );
	rms_copy_assert( empty( $copyright_field['required'] ), 'test_footer_copyright_is_optional', 'company_footer_copyright must be optional' );
}
if ( isset( $about_idx, $copyright_idx, $header_tab_idx ) ) {
	rms_copy_assert( $about_idx < $copyright_idx && $copyright_idx < $header_tab_idx, 'test_footer_copyright_lives_on_footer_tab', 'company_footer_copyright must sit after Footer About and before the Header tab' );
}

// ─── 2. Helper contract ──────────────────────────────────────────────────────
rms_copy_assert( function_exists( 'rms_get_footer_copyright' ), 'test_helper_exists', 'rms_get_footer_copyright() must exist' );

$GLOBALS['rms_copy_fields'] = array( 'company_footer_copyright' => '  © 2024 Acme Painting. Custom line.  ' );
rms_copy_assert( '© 2024 Acme Painting. Custom line.' === rms_copy_copyright(), 'test_helper_returns_trimmed_configured_value', 'configured copyright must return trimmed managed text, got ' . json_encode( rms_copy_copyright() ) );

$GLOBALS['rms_copy_fields'] = array( 'company_footer_copyright' => "  \t\n  ", 'company_name' => 'Acme Painting', 'blogname' => 'Ignored Site' );
rms_copy_assert( '© 2031 Acme Painting. All rights reserved.' === rms_copy_copyright(), 'test_helper_whitespace_falls_back', 'whitespace-only copyright must use the dynamic fallback, got ' . json_encode( rms_copy_copyright() ) );

$GLOBALS['rms_copy_fields'] = array( 'company_name' => 'Acme Painting' );
rms_copy_assert( '© 2031 Acme Painting. All rights reserved.' === rms_copy_copyright(), 'test_helper_blank_uses_company_name', 'blank copyright must use wp_date year plus company_name, got ' . json_encode( rms_copy_copyright() ) );

$GLOBALS['rms_copy_fields'] = array( 'blogname' => 'RMS Test Co' );
rms_copy_assert( '© 2031 RMS Test Co. All rights reserved.' === rms_copy_copyright(), 'test_helper_blank_uses_site_name', 'missing company_name must fall back to the site name, got ' . json_encode( rms_copy_copyright() ) );

$GLOBALS['rms_copy_fields'] = array();
rms_copy_assert( '© 2031. All rights reserved.' === rms_copy_copyright(), 'test_helper_missing_site_name', 'missing site identity must omit the name, got ' . json_encode( rms_copy_copyright() ) );

$GLOBALS['rms_copy_year']   = '1999';
$GLOBALS['rms_copy_fields'] = array( 'company_name' => 'Year Probe' );
rms_copy_assert( '© 1999 Year Probe. All rights reserved.' === rms_copy_copyright(), 'test_helper_uses_wp_date_year', 'fallback year must come from wp_date(), got ' . json_encode( rms_copy_copyright() ) );
$GLOBALS['rms_copy_year'] = '2031';

$v1_src = (string) file_get_contents( $GLOBALS['rms_copy_theme_root'] . '/templates/footer-v1.php' );
$v2_src = (string) file_get_contents( $GLOBALS['rms_copy_theme_root'] . '/templates/footer-v2.php' );
rms_copy_assert( false !== strpos( $v1_src, 'rms_get_footer_copyright()' ) && false === strpos( $v1_src, 'All rights reserved.' ), 'test_footer_v1_uses_helper_only', 'footer-v1.php must render copyright only through the helper' );
rms_copy_assert( false !== strpos( $v2_src, 'rms_get_footer_copyright()' ) && false === strpos( $v2_src, 'Simple RMS Theme' ) && false === strpos( $v2_src, '© 2026' ), 'test_footer_v2_has_no_literal_copyright', 'footer-v2.php must not keep a hardcoded copyright literal' );

// ─── 3. Both templates render the helper output, escaped ─────────────────────
$configured = array( 'company_footer_copyright' => 'Custom <script> line & more' );
$v1_html    = rms_copy_render( 'footer-v1.php', $configured );
$v2_html    = rms_copy_render( 'footer-v2.php', $configured );
rms_copy_assert( false !== strpos( $v1_html, 'Custom &lt;script&gt; line &amp; more' ) && false === strpos( $v1_html, '<script>' ), 'test_footer_v1_renders_escaped_configured_copyright', 'Footer V1 must escape the managed copyright' );
rms_copy_assert( false !== strpos( $v2_html, 'Custom &lt;script&gt; line &amp; more' ) && false === strpos( $v2_html, '<script>' ), 'test_footer_v2_renders_escaped_configured_copyright', 'Footer V2 must escape the managed copyright' );

$fallback = array( 'company_name' => 'Acme Painting' );
$v1_fb    = rms_copy_render( 'footer-v1.php', $fallback );
$v2_fb    = rms_copy_render( 'footer-v2.php', $fallback );
rms_copy_assert( false !== strpos( $v1_fb, '© 2031 Acme Painting. All rights reserved.' ), 'test_footer_v1_renders_dynamic_fallback', 'Footer V1 must render the shared fallback when the field is empty' );
rms_copy_assert( false !== strpos( $v2_fb, '© 2031 Acme Painting. All rights reserved.' ) && false === strpos( $v2_fb, 'Simple RMS Theme' ), 'test_footer_v2_renders_dynamic_fallback', 'Footer V2 must render the shared fallback instead of the theme-branded literal' );

if ( $failures > 0 ) { fwrite( STDERR, "\n{$failures} footer copyright check(s) failed.\n" ); exit( 1 ); }
fwrite( STDOUT, "\nAll footer copyright checks passed.\n" );
exit( 0 );
