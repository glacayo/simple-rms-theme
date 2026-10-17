<?php
/**
 * Focused regression harness for issue #162 (landing Testimonials styles).
 *
 * Drives header.php with deterministic WordPress/Vite stubs and proves:
 *   1. The SEO landing template defers the Testimonials V1, V2 and V3
 *      stylesheets exactly once each, through get_deferred_style() (which
 *      emits the media=print link plus the noscript fallback).
 *   2. Landing keeps every other asset it requested on main (sections,
 *      breadcrumb critical CSS, lightbox) and gains only V2 and V3.
 *   3. Home and the dedicated Testimonials template request exactly what
 *      they requested on main.
 *
 * Stubs mirror tests/landing-lightbox-assets-harness.php.
 *
 * Usage: php tests/landing-testimonials-styles-harness.php
 */

if ( PHP_SAPI !== 'cli' ) {
	fwrite( STDERR, "CLI only.\n" );
	exit( 1 );
}

$theme_root = dirname( __DIR__ );
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', $theme_root . '/' );
}


$GLOBALS['rms_lt_log']         = array();
$GLOBALS['rms_lt_deferred']    = array();
$GLOBALS['rms_lt_styles']      = array();
$GLOBALS['rms_lt_scripts']     = array();
$GLOBALS['rms_lt_front']       = false;
$GLOBALS['rms_lt_home']        = false;
$GLOBALS['rms_lt_single']      = false;
$GLOBALS['rms_lt_template']    = '';
$GLOBALS['rms_lt_queried_id']  = 0;
$GLOBALS['rms_lt_failures']    = 0;
$GLOBALS['rms_lt_scenarios']   = 0;

// ─── WordPress + Vite stubs ────────────────────────────────────────────────

if ( ! function_exists( 'language_attributes' ) ) { function language_attributes() { echo 'lang="en"'; } }
if ( ! function_exists( 'bloginfo' ) ) { function bloginfo( $show = '' ) { unset( $show ); } }
if ( ! function_exists( 'body_class' ) ) { function body_class( $class = '' ) { unset( $class ); } }
if ( ! function_exists( 'wp_head' ) ) { function wp_head() { $GLOBALS['rms_lt_log'][] = 'wp_head'; } }
if ( ! function_exists( 'wp_body_open' ) ) { function wp_body_open() {} }
if ( ! function_exists( 'add_filter' ) ) { function add_filter( $h, $cb, $p = 10, $a = 1 ) { unset( $h, $cb, $p, $a ); return true; } }
if ( ! function_exists( 'get_template_directory' ) ) { function get_template_directory() { return dirname( __DIR__ ); } }
if ( ! function_exists( 'trailingslashit' ) ) { function trailingslashit( $v ) { return rtrim( (string) $v, '/\\' ) . '/'; } }
if ( ! function_exists( 'esc_url' ) ) { function esc_url( $u ) { return (string) $u; } }
if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) ) );
	}
}
if ( ! function_exists( 'is_front_page' ) ) { function is_front_page() { return (bool) $GLOBALS['rms_lt_front']; } }
if ( ! function_exists( 'is_home' ) ) { function is_home() { return (bool) $GLOBALS['rms_lt_home']; } }
if ( ! function_exists( 'is_single' ) ) { function is_single() { return (bool) $GLOBALS['rms_lt_single']; } }
if ( ! function_exists( 'is_page_template' ) ) {
	function is_page_template( $template = '' ) {
		$current = (string) $GLOBALS['rms_lt_template'];
		if ( '' === $template ) {
			return '' !== $current;
		}
		foreach ( is_array( $template ) ? $template : array( $template ) as $candidate ) {
			if ( $current === $candidate ) {
				return true;
			}
		}
		return false;
	}
}
if ( ! function_exists( 'get_queried_object_id' ) ) { function get_queried_object_id() { return (int) $GLOBALS['rms_lt_queried_id']; } }
if ( ! function_exists( 'get_post_meta' ) ) { function get_post_meta( $post_id, $key = '', $single = false ) { unset( $post_id, $key, $single ); return ''; } }
if ( ! function_exists( 'rms_get_option' ) ) { function rms_get_option( $name, $default = null ) { unset( $name ); return $default; } }
if ( ! function_exists( 'rms_get_footer_version' ) ) { function rms_get_footer_version() { return 'footer-v2'; } }
if ( ! function_exists( 'get_template_part' ) ) { function get_template_part( $slug, $name = null, $args = array() ) { unset( $slug, $name, $args ); } }
if ( ! function_exists( 'wp_enqueue_style' ) ) {
	function wp_enqueue_style( $handle = '', $src = '', $deps = array(), $ver = false ) {
		$GLOBALS['rms_lt_styles'][] = array(
			'handle' => (string) $handle,
			'src'    => (string) $src,
			'deps'   => $deps,
			'ver'    => $ver,
		);
		$GLOBALS['rms_lt_log'][] = 'style:' . (string) $handle;
	}
}
if ( ! function_exists( 'wp_enqueue_script' ) ) {
	function wp_enqueue_script( $handle = '', $src = '', $deps = array(), $ver = false, $footer = false ) {
		$GLOBALS['rms_lt_scripts'][] = array(
			'handle' => (string) $handle,
			'src'    => (string) $src,
			'deps'   => $deps,
			'ver'    => $ver,
			'footer' => (bool) $footer,
		);
		$GLOBALS['rms_lt_log'][] = 'script:' . (string) $handle;
	}
}

if ( ! class_exists( 'Vite_Icons_Integration' ) ) {
	class Vite_Icons_Integration {
		public static function get_instance() {
			if ( ! isset( $GLOBALS['rms_lt_vite'] ) || ! ( $GLOBALS['rms_lt_vite'] instanceof self ) ) {
				$GLOBALS['rms_lt_vite'] = new self();
			}
			return $GLOBALS['rms_lt_vite'];
		}
		public function get_critical_css( $entry, $id = '' ) {
			unset( $entry );
			$GLOBALS['rms_lt_log'][] = 'critical:' . (string) $id;
			return '';
		}
		public function get_asset( $entry ) {
			$GLOBALS['rms_lt_log'][] = 'asset:' . (string) $entry;
			return 'https://example.test/dist/' . (string) $entry;
		}
		public function get_deferred_style( $handle, $entry ): void {
			// Mirror the real method: skip the link when the asset is missing.
			$css = $this->get_asset( $entry );
			if ( ! $css ) {
				return;
			}
			$GLOBALS['rms_lt_deferred'][] = array(
				'handle' => (string) $handle,
				'entry'  => (string) $entry,
			);
			$GLOBALS['rms_lt_log'][] = 'deferred:' . (string) $handle;
		}
	}
}

require_once $theme_root . '/inc/acf-template-boundary.php';

// ─── Harness helpers ───────────────────────────────────────────────────────

function rms_lt_check( $condition, string $message ): void {
	if ( ! $condition ) {
		$GLOBALS['rms_lt_failures']++;
		fwrite( STDERR, 'FAIL ' . $message . "\n" );
		return;
	}
	echo 'PASS ' . $message . "\n";
}

function rms_lt_reset(): void {
	$GLOBALS['rms_lt_log']        = array();
	$GLOBALS['rms_lt_deferred']   = array();
	$GLOBALS['rms_lt_styles']     = array();
	$GLOBALS['rms_lt_scripts']    = array();
	$GLOBALS['rms_lt_front']      = false;
	$GLOBALS['rms_lt_home']       = false;
	$GLOBALS['rms_lt_single']     = false;
	$GLOBALS['rms_lt_template']   = '';
	$GLOBALS['rms_lt_queried_id'] = 0;
}

function rms_lt_run_header( array $context, string $header = '' ): void {
	rms_lt_reset();
	$GLOBALS['rms_lt_front']      = (bool) ( $context['front'] ?? false );
	$GLOBALS['rms_lt_home']       = (bool) ( $context['home'] ?? false );
	$GLOBALS['rms_lt_single']     = (bool) ( $context['single'] ?? false );
	$GLOBALS['rms_lt_template']   = (string) ( $context['template'] ?? '' );
	$GLOBALS['rms_lt_queried_id'] = (int) ( $context['queried_id'] ?? 0 );
	ob_start();
	include '' !== $header ? $header : dirname( __DIR__ ) . '/header.php';
	ob_end_clean();
}

/**
 * Every deferred/enqueued record of one header render, in order.
 */
function rms_lt_capture( array $context, string $header = '' ): array {
	rms_lt_run_header( $context, $header );
	return array(
		'deferred' => $GLOBALS['rms_lt_deferred'],
		'styles'   => $GLOBALS['rms_lt_styles'],
		'scripts'  => $GLOBALS['rms_lt_scripts'],
		'log'      => $GLOBALS['rms_lt_log'],
	);
}

function rms_lt_handles( array $records ): array {
	return array_column( $records['deferred'], 'handle' );
}

$variants = array( 'testimonials-v1', 'testimonials-v2', 'testimonials-v3' );
$landing  = array( 'template' => 'pages/landing-page.php' );

// Baseline header.php from main for "unchanged behavior" comparisons.
$baseline = tempnam( sys_get_temp_dir(), 'rms-header-main-' ) . '.php';
exec( 'git -C ' . escapeshellarg( $theme_root ) . ' show main:header.php 2>/dev/null', $lines, $rc );
file_put_contents( $baseline, implode( "\n", $lines ) . "\n" );
rms_lt_check( 0 === $rc && filesize( $baseline ) > 0, 'baseline header.php from main is available' );

// ── 1. Landing defers all three Testimonials variants ─────────────────────

$now = rms_lt_capture( $landing );
foreach ( $variants as $variant ) {
	$matches = array_values( array_filter(
		$now['deferred'],
		static function ( $item ) use ( $variant ) {
			return 'section-' . $variant === $item['handle'];
		}
	) );
	rms_lt_check(
		1 === count( $matches ) && 'src/scss/templates/' . $variant . '.scss' === $matches[0]['entry'],
		"landing defers section-{$variant} exactly once from src/scss/templates/{$variant}.scss"
	);
	rms_lt_check(
		is_readable( $theme_root . '/src/scss/templates/' . $variant . '.scss' ),
		"{$variant} stylesheet source exists"
	);
	rms_lt_check(
		1 === preg_match( "/'" . preg_quote( $variant, '/' ) . "'\s*:\s*path\.resolve\(__dirname, 'src\/scss\/templates\/" . preg_quote( $variant, '/' ) . "\.scss'\)/", (string) file_get_contents( $theme_root . '/vite.config.ts' ) ),
		"{$variant} is a Vite build input, so the manifest can resolve it"
	);
}
rms_lt_check(
	array() === array_diff_assoc( rms_lt_handles( $now ), array_unique( rms_lt_handles( $now ) ) ),
	'landing defers no duplicate stylesheet handle'
);

// The real deferred mechanism carries the noscript fallback.
$vite_source = (string) file_get_contents( $theme_root . '/inc/vite-integration.php' );
rms_lt_check(
	1 === preg_match( '/function get_deferred_style\([^)]*\)[^{]*\{[^}]*media="print"[^}]*<noscript><link rel="stylesheet"/s', $vite_source ),
	'get_deferred_style emits the print-media link and the noscript fallback'
);

// ── 2. Landing gains exactly V2 and V3, nothing else changes ──────────────

$before = rms_lt_capture( $landing, $baseline );
$added  = array_values( array_diff( rms_lt_handles( $now ), rms_lt_handles( $before ) ) );
rms_lt_check(
	array( 'section-testimonials-v2', 'section-testimonials-v3' ) === $added,
	'landing adds exactly section-testimonials-v2 and section-testimonials-v3 (got ' . implode( ', ', $added ) . ')'
);
rms_lt_check(
	array() === array_values( array_diff( rms_lt_handles( $before ), rms_lt_handles( $now ) ) ),
	'landing keeps every stylesheet it requested on main, including testimonials-v1 and lightbox'
);
rms_lt_check(
	$before['styles'] === $now['styles'] && $before['scripts'] === $now['scripts'],
	'landing enqueued styles and scripts are unchanged from main'
);
rms_lt_check(
	in_array( 'critical:critical-breadcrumb-slim', $now['log'], true ),
	'landing still inlines the breadcrumb-slim critical CSS'
);

// ── 3. Home and the dedicated Testimonials template are untouched ─────────

foreach ( array(
	'front page'            => array( 'front' => true ),
	'testimonials template' => array( 'template' => 'pages/testimonials.php', 'queried_id' => 7 ),
	'default page'          => array(),
) as $label => $context ) {
	rms_lt_check(
		rms_lt_capture( $context, $baseline ) === rms_lt_capture( $context ),
		"{$label} requests exactly the same assets as on main"
	);
}
$home = rms_lt_capture( array( 'front' => true ) );
foreach ( $variants as $variant ) {
	rms_lt_check( in_array( 'section-' . $variant, rms_lt_handles( $home ), true ), "front page still defers section-{$variant}" );
}

unlink( $baseline );

// ── Summary ────────────────────────────────────────────────────────────────

if ( $GLOBALS['rms_lt_failures'] > 0 ) {
	fwrite( STDERR, "\nLanding testimonials styles harness failed: {$GLOBALS['rms_lt_failures']} check(s).\n" );
	exit( 1 );
}

echo "\nLanding testimonials styles harness passed.\n";
exit( 0 );
