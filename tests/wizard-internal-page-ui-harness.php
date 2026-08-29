<?php
/**
 * Internal Page Builder admin registration and server-rendered form.
 *
 * Usage: php tests/wizard-internal-page-ui-harness.php
 */
if ( PHP_SAPI !== 'cli' ) {
	fwrite( STDERR, "CLI only.\n" );
	exit( 1 );
}

$theme_root = dirname( __DIR__ );
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', $theme_root . '/' );
}

$GLOBALS['_actions'] = array();

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $k ) {
		return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ) );
	}
}
if ( ! function_exists( '__' ) ) {
	function __( $t, $d = null ) {
		unset( $d );
		return $t;
	}
}
if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $t ) {
		return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8' );
	}
}
if ( ! function_exists( 'esc_html_e' ) ) {
	function esc_html_e( $t, $d = null ) {
		unset( $d );
		echo esc_html( $t );
	}
}
if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $d, $o = 0 ) {
		return json_encode( $d, $o );
	}
}
if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $t ) {
		return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8' );
	}
}
if ( ! function_exists( 'esc_attr_e' ) ) {
	function esc_attr_e( $t, $d = null ) {
		unset( $d );
		echo esc_attr( $t );
	}
}
if ( ! function_exists( 'trailingslashit' ) ) {
	function trailingslashit( $v ) {
		return rtrim( (string) $v, '/\\' ) . '/';
	}
}
if ( ! function_exists( 'get_template_directory_uri' ) ) {
	function get_template_directory_uri() {
		return 'https://example.test/theme';
	}
}
if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $cb, $pri = 10, $args = 1 ) {
		$GLOBALS['_actions'][] = array( $hook, $cb, $pri, $args );
		return true;
	}
}
if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook, $cb, $pri = 10, $args = 1 ) {
		return add_action( $hook, $cb, $pri, $args );
	}
}

require_once $theme_root . '/inc/wizard/class-ai-content-harness.php';
require_once $theme_root . '/inc/wizard/class-internal-page-blueprints.php';
require_once $theme_root . '/inc/wizard/wizard-init.php';

function rms_ipui_assert( $c, string $m ): void {
	if ( ! $c ) {
		fwrite( STDERR, $m . "\n" );
		exit( 1 );
	}
}

$passed = 0;
$hooks  = array();
foreach ( $GLOBALS['_actions'] as $row ) {
	$hooks[] = (string) $row[0];
}
rms_ipui_assert( in_array( 'admin_menu', $hooks, true ), 'admin_menu registered' );
rms_ipui_assert( in_array( 'rest_api_init', $hooks, true ), 'REST routes registered' );
rms_ipui_assert( in_array( 'acf/save_post', $hooks, true ), 'acf/save_post registered' );
rms_ipui_assert( function_exists( 'rms_wizard_render_internal_page_builder_form' ), 'form renderer exists' );
echo "PASS production-registration\n";
++$passed;

ob_start();
rms_wizard_render_internal_page_builder_form();
$markup = (string) ob_get_clean();
rms_ipui_assert( false !== strpos( $markup, 'data-wizard-internal-page-builder-form' ), 'form hook' );
rms_ipui_assert( false !== strpos( $markup, 'data-wizard-internal-skip-all' ), 'skip-all control' );
rms_ipui_assert( false !== strpos( $markup, 'data-wizard-page-type' ), 'stable type attribute' );
rms_ipui_assert( false !== strpos( $markup, 'role="list"' ), 'list semantics' );
rms_ipui_assert( false !== strpos( $markup, 'aria-live="polite"' ), 'live progress region' );
rms_ipui_assert( false !== strpos( $markup, 'About' ) && false !== strpos( $markup, 'Services' ), 'escaped blueprint labels' );
rms_ipui_assert( false !== strpos( $markup, 'data-wizard-internal-preview' ), 'preview plan payload' );
rms_ipui_assert( false !== strpos( $markup, 'data-wizard-internal-map-type' ), 'explicit mapping control' );
rms_ipui_assert( false !== strpos( $markup, 'data-wizard-internal-map-confirm' ), 'mapping confirmation copy' );
rms_ipui_assert( false !== strpos( $markup, 'Assign internal page type' ), 'accessible mapping label' );
rms_ipui_assert( false !== strpos( $markup, 'data-wizard-internal-map-dialog' ), 'independent mapping dialog node' );
rms_ipui_assert( false !== strpos( $markup, 'data-wizard-internal-map-dialog-accept' ), 'mapping dialog accept control' );
rms_ipui_assert( false !== strpos( $markup, 'data-wizard-internal-map-dialog-cancel' ), 'mapping dialog cancel control' );
rms_ipui_assert( false !== strpos( $markup, 'Confirm page type assignment' ), 'mapping dialog distinct title' );
rms_ipui_assert( false !== strpos( $markup, 'Assign page types' ), 'mapping dialog distinct confirm label' );
rms_ipui_assert( false !== strpos( $markup, 'aria-modal="true"' ), 'mapping dialog modal semantics' );
echo "PASS server-rendered-form-accessibility\n";
++$passed;

echo 'Harness passed: ' . $passed . " scenarios.\n";
