<?php
/**
 * Focused regression harness for issue #162 (landing Testimonials styles).
 *
 * Drives header.php with deterministic WordPress/Vite stubs and proves:
 *   1. The SEO landing template defers the Testimonials V1, V2 and V3
 *      stylesheets exactly once each, through get_deferred_style() (which
 *      emits the media=print link plus the noscript fallback).
 *   2. Landing requests exactly the pinned asset list: every asset it had
 *      before #162 (sections, breadcrumb critical CSS, lightbox) plus V2/V3.
 *   3. Home, the dedicated Testimonials template and a default page request
 *      exactly their pinned pre-#162 asset lists.
 *
 * Expected lists are literals (not a git baseline) so the harness stays
 * meaningful after merge and works in checkouts without a `main` ref.
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

// ── 2. Landing requests exactly its pinned asset list ────────────────────

rms_lt_check(
	array(
		'section-hero',
		'section-seo-content',
		'section-vision-mission-v1',
		'section-badges',
		'section-portfolio-v1',
		'section-testimonials-v1',
		'section-testimonials-v2',
		'section-testimonials-v3',
		'lightbox',
		'layout-footer-v2',
	) === rms_lt_handles( $now ),
	'landing defers exactly the pre-#162 sections plus testimonials-v2/v3, in order (got ' . implode( ', ', rms_lt_handles( $now ) ) . ')'
);
rms_lt_check(
	array( 'header-one' ) === array_column( $now['styles'], 'handle' )
		&& array( 'lightbox-js', 'header-one-menu' ) === array_column( $now['scripts'], 'handle' ),
	'landing enqueued styles and scripts are unchanged'
);
rms_lt_check(
	in_array( 'critical:critical-breadcrumb-slim', $now['log'], true ),
	'landing still inlines the breadcrumb-slim critical CSS'
);

// ── 3. Home, Testimonials template and default pages are untouched ────────

$pinned = array(
	'front page' => array(
		'context'  => array( 'front' => true ),
		'deferred' => array(
			'section-about-us', 'section-services-v1', 'section-services-v2', 'section-services-v3',
			'section-cta-v1', 'section-cta-v2', 'section-cta-v3',
			'section-portfolio-v1', 'section-portfolio-v2', 'section-portfolio-v3',
			'section-testimonials-v1', 'section-testimonials-v2', 'section-testimonials-v3',
			'section-blog-v1', 'section-area-coverage-v1', 'section-vision-mission-v1', 'section-vision-mission-v2',
			'section-seo-content', 'lightbox', 'section-faq-v1', 'section-faq-v2', 'section-video-v1', 'section-video-v2',
			'layout-footer-v2',
		),
		'styles'   => array( 'section-slider', 'section-badges', 'header-one' ),
		'scripts'  => array( 'slider-js', 'lightbox-js', 'portfolio-filter-js', 'faq-js', 'video-v1-js', 'video-v2-js', 'header-one-menu' ),
	),
	'testimonials template' => array(
		'context'  => array( 'template' => 'pages/testimonials.php', 'queried_id' => 7 ),
		'deferred' => array( 'section-testimonials-v1', 'layout-footer-v2' ),
		'styles'   => array( 'header-one' ),
		'scripts'  => array( 'header-one-menu' ),
	),
	'default page' => array(
		'context'  => array(),
		'deferred' => array( 'layout-footer-v2' ),
		'styles'   => array( 'header-one' ),
		'scripts'  => array( 'header-one-menu' ),
	),
);
foreach ( $pinned as $label => $expected ) {
	$got = rms_lt_capture( $expected['context'] );
	rms_lt_check(
		$expected['deferred'] === rms_lt_handles( $got )
			&& $expected['styles'] === array_column( $got['styles'], 'handle' )
			&& $expected['scripts'] === array_column( $got['scripts'], 'handle' ),
		"{$label} requests exactly its pinned pre-#162 assets"
	);
}

// ── Summary ────────────────────────────────────────────────────────────────

if ( $GLOBALS['rms_lt_failures'] > 0 ) {
	fwrite( STDERR, "\nLanding testimonials styles harness failed: {$GLOBALS['rms_lt_failures']} check(s).\n" );
	exit( 1 );
}

echo "\nLanding testimonials styles harness passed.\n";
exit( 0 );
