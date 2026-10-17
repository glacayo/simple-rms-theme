<?php
/**
 * Focused regression harness for issue #165 (landing assets per stored layout).
 *
 * Drives header.php with deterministic WordPress/Vite stubs and proves:
 *   1. For every page_sections layout, a landing page that stores it defers
 *      exactly that layout's stylesheet (plus the unchanged lightbox and
 *      breadcrumb-slim critical CSS), from a Vite build input.
 *   2. Interactive layouts enqueue the same script handles they use on the
 *      home page, once each even when several rows share a script.
 *   3. A landing page with no stored sections keeps the pinned legacy list
 *      that matches its hardcoded fallback order.
 *   4. Invalid layout names never become stylesheet requests.
 *
 * Home, the Testimonials template and default pages stay pinned by
 * tests/landing-testimonials-styles-harness.php.
 *
 * Usage: php tests/landing-dynamic-assets-harness.php
 */

if ( PHP_SAPI !== 'cli' ) {
	fwrite( STDERR, "CLI only.\n" );
	exit( 1 );
}

$theme_root = dirname( __DIR__ );
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', $theme_root . '/' );
}


$GLOBALS['rms_ld_log']         = array();
$GLOBALS['rms_ld_deferred']    = array();
$GLOBALS['rms_ld_styles']      = array();
$GLOBALS['rms_ld_scripts']     = array();
$GLOBALS['rms_ld_front']       = false;
$GLOBALS['rms_ld_home']        = false;
$GLOBALS['rms_ld_single']      = false;
$GLOBALS['rms_ld_template']    = '';
$GLOBALS['rms_ld_queried_id']  = 0;
$GLOBALS['rms_ld_failures']    = 0;
$GLOBALS['rms_ld_scenarios']   = 0;

// ─── WordPress + Vite stubs ────────────────────────────────────────────────

if ( ! function_exists( 'language_attributes' ) ) { function language_attributes() { echo 'lang="en"'; } }
if ( ! function_exists( 'bloginfo' ) ) { function bloginfo( $show = '' ) { unset( $show ); } }
if ( ! function_exists( 'body_class' ) ) { function body_class( $class = '' ) { unset( $class ); } }
if ( ! function_exists( 'wp_head' ) ) { function wp_head() { $GLOBALS['rms_ld_log'][] = 'wp_head'; } }
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
if ( ! function_exists( 'is_front_page' ) ) { function is_front_page() { return (bool) $GLOBALS['rms_ld_front']; } }
if ( ! function_exists( 'is_home' ) ) { function is_home() { return (bool) $GLOBALS['rms_ld_home']; } }
if ( ! function_exists( 'is_single' ) ) { function is_single() { return (bool) $GLOBALS['rms_ld_single']; } }
if ( ! function_exists( 'is_page_template' ) ) {
	function is_page_template( $template = '' ) {
		$current = (string) $GLOBALS['rms_ld_template'];
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
if ( ! function_exists( 'get_queried_object_id' ) ) { function get_queried_object_id() { return (int) $GLOBALS['rms_ld_queried_id']; } }
$GLOBALS['rms_ld_rows'] = array();
if ( ! function_exists( 'get_field' ) ) {
	function get_field( $selector, $post_id = false ) {
		return 'page_sections' === $selector ? ( $GLOBALS['rms_ld_rows'][ (int) $post_id ] ?? null ) : null;
	}
}
if ( ! function_exists( 'get_post_meta' ) ) { function get_post_meta( $post_id, $key = '', $single = false ) { unset( $post_id, $key, $single ); return ''; } }
if ( ! function_exists( 'rms_get_option' ) ) { function rms_get_option( $name, $default = null ) { unset( $name ); return $default; } }
if ( ! function_exists( 'rms_get_footer_version' ) ) { function rms_get_footer_version() { return 'footer-v2'; } }
if ( ! function_exists( 'get_template_part' ) ) { function get_template_part( $slug, $name = null, $args = array() ) { unset( $slug, $name, $args ); } }
if ( ! function_exists( 'wp_enqueue_style' ) ) {
	function wp_enqueue_style( $handle = '', $src = '', $deps = array(), $ver = false ) {
		$GLOBALS['rms_ld_styles'][] = array(
			'handle' => (string) $handle,
			'src'    => (string) $src,
			'deps'   => $deps,
			'ver'    => $ver,
		);
		$GLOBALS['rms_ld_log'][] = 'style:' . (string) $handle;
	}
}
if ( ! function_exists( 'wp_enqueue_script' ) ) {
	function wp_enqueue_script( $handle = '', $src = '', $deps = array(), $ver = false, $footer = false ) {
		$GLOBALS['rms_ld_scripts'][] = array(
			'handle' => (string) $handle,
			'src'    => (string) $src,
			'deps'   => $deps,
			'ver'    => $ver,
			'footer' => (bool) $footer,
		);
		$GLOBALS['rms_ld_log'][] = 'script:' . (string) $handle;
	}
}

if ( ! class_exists( 'Vite_Icons_Integration' ) ) {
	class Vite_Icons_Integration {
		public static function get_instance() {
			if ( ! isset( $GLOBALS['rms_ld_vite'] ) || ! ( $GLOBALS['rms_ld_vite'] instanceof self ) ) {
				$GLOBALS['rms_ld_vite'] = new self();
			}
			return $GLOBALS['rms_ld_vite'];
		}
		public function get_critical_css( $entry, $id = '' ) {
			unset( $entry );
			$GLOBALS['rms_ld_log'][] = 'critical:' . (string) $id;
			return '';
		}
		public function get_asset( $entry ) {
			$GLOBALS['rms_ld_log'][] = 'asset:' . (string) $entry;
			return 'https://example.test/dist/' . (string) $entry;
		}
		public function get_deferred_style( $handle, $entry ): void {
			// Mirror the real method: skip the link when the asset is missing.
			$css = $this->get_asset( $entry );
			if ( ! $css ) {
				return;
			}
			$GLOBALS['rms_ld_deferred'][] = array(
				'handle' => (string) $handle,
				'entry'  => (string) $entry,
			);
			$GLOBALS['rms_ld_log'][] = 'deferred:' . (string) $handle;
		}
	}
}

require_once $theme_root . '/inc/acf-template-boundary.php';

// ─── Harness helpers ───────────────────────────────────────────────────────

function rms_ld_check( $condition, string $message ): void {
	if ( ! $condition ) {
		$GLOBALS['rms_ld_failures']++;
		fwrite( STDERR, 'FAIL ' . $message . "\n" );
		return;
	}
	echo 'PASS ' . $message . "\n";
}

function rms_ld_reset(): void {
	$GLOBALS['rms_ld_log']        = array();
	$GLOBALS['rms_ld_deferred']   = array();
	$GLOBALS['rms_ld_styles']     = array();
	$GLOBALS['rms_ld_scripts']    = array();
	$GLOBALS['rms_ld_front']      = false;
	$GLOBALS['rms_ld_home']       = false;
	$GLOBALS['rms_ld_single']     = false;
	$GLOBALS['rms_ld_template']   = '';
	$GLOBALS['rms_ld_queried_id'] = 0;
}

function rms_ld_run_header( array $context, string $header = '' ): void {
	rms_ld_reset();
	$GLOBALS['rms_ld_front']      = (bool) ( $context['front'] ?? false );
	$GLOBALS['rms_ld_home']       = (bool) ( $context['home'] ?? false );
	$GLOBALS['rms_ld_single']     = (bool) ( $context['single'] ?? false );
	$GLOBALS['rms_ld_template']   = (string) ( $context['template'] ?? '' );
	$GLOBALS['rms_ld_queried_id'] = (int) ( $context['queried_id'] ?? 0 );
	ob_start();
	include '' !== $header ? $header : dirname( __DIR__ ) . '/header.php';
	ob_end_clean();
}

/**
 * Every deferred/enqueued record of one header render, in order.
 */
function rms_ld_capture( array $context, string $header = '' ): array {
	rms_ld_run_header( $context, $header );
	return array(
		'deferred' => $GLOBALS['rms_ld_deferred'],
		'styles'   => $GLOBALS['rms_ld_styles'],
		'scripts'  => $GLOBALS['rms_ld_scripts'],
		'log'      => $GLOBALS['rms_ld_log'],
	);
}

function rms_ld_handles( array $records ): array {
	return array_column( $records['deferred'], 'handle' );
}

function rms_ld_landing( array $layouts ): array {
	$GLOBALS['rms_ld_rows'] = array(
		41 => array_map(
			static function ( $layout ) {
				return array( 'acf_fc_layout' => $layout );
			},
			$layouts
		),
	);
	rms_ld_run_header( array( 'template' => 'pages/landing-page.php', 'queried_id' => 41 ) );
	return array(
		'deferred' => array_column( $GLOBALS['rms_ld_deferred'], 'handle' ),
		'entries'  => array_column( $GLOBALS['rms_ld_deferred'], 'entry', 'handle' ),
		'scripts'  => array_column( $GLOBALS['rms_ld_scripts'], 'handle' ),
		'log'      => $GLOBALS['rms_ld_log'],
	);
}

$group   = json_decode( (string) file_get_contents( $theme_root . '/acf-json/group_rms_page_sections.json' ), true );
$layouts = array_values( array_column( $group['fields'][0]['layouts'] ?? array(), 'name' ) );
$vite    = (string) file_get_contents( $theme_root . '/vite.config.ts' );
rms_ld_check( 27 === count( $layouts ), 'page_sections exposes 27 layouts (got ' . count( $layouts ) . ')' );

$scripts_for = array(
	'slider'       => array( 'slider-js' ),
	'portfolio-v3' => array( 'portfolio-filter-js' ),
	'faq-v1'       => array( 'faq-js' ),
	'faq-v2'       => array( 'faq-js' ),
	'video-v1'     => array( 'video-v1-js' ),
	'video-v2'     => array( 'video-v2-js' ),
);

// ── 1/2. Every layout loads its own stylesheet and, if any, its script ────

foreach ( $layouts as $layout ) {
	$r     = rms_ld_landing( array( $layout ) );
	$entry = 'src/scss/templates/' . $layout . '.scss';
	rms_ld_check(
		array( 'section-' . $layout, 'lightbox', 'layout-footer-v2' ) === $r['deferred'],
		"landing with a {$layout} row defers exactly section-{$layout} + lightbox + footer (got " . implode( ', ', $r['deferred'] ) . ')'
	);
	rms_ld_check( $entry === ( $r['entries'][ 'section-' . $layout ] ?? '' ), "section-{$layout} loads {$entry}" );
	rms_ld_check(
		is_readable( $theme_root . '/' . $entry )
			&& 1 === preg_match( "/'?" . preg_quote( $layout, '/' ) . "'?\s*:\s*path\.resolve\(__dirname, '" . preg_quote( $entry, '/' ) . "'\)/", $vite ),
		"{$entry} exists and is a Vite build input"
	);
	$expected_scripts = array_merge( array( 'lightbox-js' ), $scripts_for[ $layout ] ?? array(), array( 'header-one-menu' ) );
	rms_ld_check(
		$expected_scripts === $r['scripts'],
		"landing with a {$layout} row enqueues exactly " . implode( ', ', $expected_scripts ) . ' (got ' . implode( ', ', $r['scripts'] ) . ')'
	);
	rms_ld_check( in_array( 'critical:critical-breadcrumb-slim', $r['log'], true ), "landing with a {$layout} row still inlines breadcrumb-slim critical CSS" );
}

// ── 2. Shared scripts and repeated rows load once ─────────────────────────

$mixed = rms_ld_landing( array( 'hero', 'faq-v1', 'faq-v2', 'faq-v1', 'slider', 'video-v1', 'video-v2', 'portfolio-v3' ) );
rms_ld_check(
	array( 'section-hero', 'section-faq-v1', 'section-faq-v2', 'section-slider', 'section-video-v1', 'section-video-v2', 'section-portfolio-v3', 'lightbox', 'layout-footer-v2' ) === $mixed['deferred'],
	'mixed rows defer each stylesheet once, in row order'
);
rms_ld_check(
	array( 'lightbox-js', 'faq-js', 'slider-js', 'video-v1-js', 'video-v2-js', 'portfolio-filter-js', 'header-one-menu' ) === $mixed['scripts'],
	'mixed rows enqueue each script once (got ' . implode( ', ', $mixed['scripts'] ) . ')'
);

// ── 3. No stored sections keeps the legacy fallback list ──────────────────

$legacy = array(
	'section-hero', 'section-seo-content', 'section-vision-mission-v1', 'section-badges', 'section-portfolio-v1',
	'section-testimonials-v1', 'section-testimonials-v2', 'section-testimonials-v3', 'lightbox', 'layout-footer-v2',
);
$empty = rms_ld_landing( array() );
rms_ld_check( $legacy === $empty['deferred'], 'landing with no stored sections defers the pinned legacy list' );
rms_ld_check( array( 'lightbox-js', 'header-one-menu' ) === $empty['scripts'], 'landing with no stored sections enqueues only lightbox-js and the header menu' );

// ── 4. Invalid layout names are ignored ───────────────────────────────────

$invalid = rms_ld_landing( array( '../wp-config', 'Bad Layout!', '' ) );
rms_ld_check(
	array() === array_filter( $invalid['entries'], static function ( $e ) { return false !== strpos( $e, '..' ) || false !== strpos( $e, ' ' ); } ),
	'invalid layout names never reach a stylesheet entry'
);

// ── Summary ────────────────────────────────────────────────────────────────

if ( $GLOBALS['rms_ld_failures'] > 0 ) {
	fwrite( STDERR, "\nLanding dynamic assets harness failed: {$GLOBALS['rms_ld_failures']} check(s).\n" );
	exit( 1 );
}

echo "\nLanding dynamic assets harness passed.\n";
exit( 0 );
