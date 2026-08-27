<?php
/**
 * Internal page flexible-loop and shell-template proofs.
 *
 * Usage: php tests/wizard-internal-page-templates-harness.php
 */

if ( PHP_SAPI !== 'cli' ) {
	fwrite( STDERR, "CLI only.\n" );
	exit( 1 );
}

$theme_root = dirname( __DIR__ );
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', $theme_root . '/' );
}

$GLOBALS['rms_loop_rows']    = array();
$GLOBALS['rms_loop_index']   = 0;
$GLOBALS['rms_loop_started'] = false;
$GLOBALS['rms_parts']        = array();
$GLOBALS['rms_build_pages']  = array();

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8' ); }
}
if ( ! function_exists( 'sanitize_title' ) ) {
	function sanitize_title( $v ) { return strtolower( preg_replace( '/[^a-z0-9-]+/', '-', (string) $v ) ); }
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $s ) { return trim( (string) $s ); }
}
if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $k ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ) ); }
}
if ( ! function_exists( 'absint' ) ) {
	function absint( $v ) { return abs( (int) $v ); }
}
if ( ! function_exists( 'locate_template' ) ) {
	function locate_template( $templates, $load = false, $require_once = true ) {
		unset( $load, $require_once );
		foreach ( is_array( $templates ) ? $templates : array( $templates ) as $rel ) {
			$path = dirname( __DIR__ ) . '/' . ltrim( (string) $rel, '/' );
			if ( is_readable( $path ) ) { return $path; }
		}
		return '';
	}
}
if ( ! function_exists( 'get_template_part' ) ) { function get_template_part( $slug, $name = null ) { unset( $name ); $GLOBALS['rms_parts'][] = $slug; } }
if ( ! function_exists( 'have_rows' ) ) {
	function have_rows( $selector, $post_id = false ) {
		unset( $post_id );
		if ( 'page_sections' !== $selector ) { return false; }
		return $GLOBALS['rms_loop_started'] ? $GLOBALS['rms_loop_index'] < count( $GLOBALS['rms_loop_rows'] ) : count( $GLOBALS['rms_loop_rows'] ) > 0;
	}
}
if ( ! function_exists( 'the_row' ) ) { function the_row() { $GLOBALS['rms_loop_started'] = true; $GLOBALS['rms_loop_index']++; return true; } }
if ( ! function_exists( 'get_row_layout' ) ) { function get_row_layout() { return (string) ( $GLOBALS['rms_loop_rows'][ $GLOBALS['rms_loop_index'] - 1 ] ?? '' ); } }
if ( ! function_exists( 'get_sub_field' ) ) { function get_sub_field( $selector, $format_value = true ) { unset( $selector, $format_value ); return null; } }

function rms_tpl_assert( $c, string $m ): void {
	if ( ! $c ) {
		fwrite( STDERR, $m . "\n" );
		exit( 1 );
	}
}

function rms_tpl_run_loop( array $rows ): array {
	$GLOBALS['rms_loop_rows']    = $rows;
	$GLOBALS['rms_loop_index']   = 0;
	$GLOBALS['rms_loop_started'] = false;
	$GLOBALS['rms_parts']        = array();
	require dirname( __DIR__ ) . '/templates/page-sections-loop.php';
	return $GLOBALS['rms_parts'];
}

$passed = 0;

$ordered = rms_tpl_run_loop( array( 'about-us', 'vision-mission-v2', 'cta-v2' ) );
rms_tpl_assert( array( 'templates/about-us', 'templates/vision-mission-v2', 'templates/cta-v2' ) === $ordered, 'rows render in stored order' );
echo "PASS stored-sections-render-in-order\n";
++$passed;

$skipped = rms_tpl_run_loop( array( 'about-us', 'not-a-real-layout', 'cta-v2' ) );
rms_tpl_assert( array( 'templates/about-us', 'templates/cta-v2' ) === $skipped, 'unknown layout skipped' );
echo "PASS unknown-layout-skipped-safely\n";
++$passed;

foreach ( array( '../header', '..\\header', 'templates/hero', 'about.us', 'about_us', '..', 'header.php' ) as $bad ) {
	$parts = rms_tpl_run_loop( array( 'about-us', $bad, 'cta-v2' ) );
	rms_tpl_assert( array( 'templates/about-us', 'templates/cta-v2' ) === $parts, 'rejected layout: ' . $bad );
}
echo "PASS traversal-and-malformed-layouts-rejected\n";
++$passed;

$empty = rms_tpl_run_loop( array() );
rms_tpl_assert( array() === $empty, 'empty sections emit no parts' );
echo "PASS empty-sections-no-error\n";
++$passed;

$services = file_get_contents( dirname( __DIR__ ) . '/pages/services.php' );
rms_tpl_assert( is_string( $services ) && false === strpos( $services, 'services-page' ), 'services does not use services-page' );
rms_tpl_assert( false !== strpos( $services, 'page-sections-loop' ), 'services uses loop' );
echo "PASS services-independent-of-static-demo\n";
++$passed;

$contact = file_get_contents( dirname( __DIR__ ) . '/pages/contact-us.php' );
rms_tpl_assert( is_string( $contact ) && false !== strpos( $contact, 'page-sections-loop' ), 'contact uses loop' );
rms_tpl_assert( false !== strpos( $contact, 'contact-map' ), 'contact keeps map chrome' );
$loop_pos = strpos( $contact, 'page-sections-loop' );
$map_pos  = strpos( $contact, 'contact-map' );
rms_tpl_assert( false !== $loop_pos && false !== $map_pos && $loop_pos < $map_pos, 'map chrome after loop' );
echo "PASS contact-map-chrome-outside-loop\n";
++$passed;

$projects = file_get_contents( dirname( __DIR__ ) . '/pages/projects.php' );
rms_tpl_assert( is_string( $projects ) && false !== strpos( $projects, 'page-sections-loop' ), 'projects uses loop' );
rms_tpl_assert( false === strpos( $projects, 'gallery-grid' ), 'projects does not hardcode gallery-grid' );
echo "PASS projects-uses-flexible-loop\n";
++$passed;

require_once dirname( __DIR__ ) . '/inc/wizard/class-internal-page-blueprints.php';
require_once dirname( __DIR__ ) . '/inc/wizard/class-ai-content-harness.php';
$all = \Inc\Wizard\Internal_Page_Blueprints::all();
rms_tpl_assert( 'pages/about-us.php' === $all['about']['template'], 'about registry template' );
rms_tpl_assert( 'pages/services.php' === $all['services']['template'], 'services registry template' );
rms_tpl_assert( array( 'services-v1', 'cta-v2' ) === $all['services']['layouts'], 'services layouts' );
rms_tpl_assert( 'pages/contact-us.php' === $all['contact']['template'], 'contact registry template' );
rms_tpl_assert( 'pages/projects.php' === $all['projects']['template'], 'projects registry template' );
$ready = \Inc\Wizard\Internal_Page_Blueprints::shell_ready_types();
rms_tpl_assert( array( 'about', 'services', 'contact', 'projects' ) === $ready && ! in_array( 'testimonials', $ready, true ) && ! in_array( 'blog', $ready, true ), 'shell-ready types' );
echo "PASS blueprint-templates-and-shell-ready\n";
++$passed;

if ( ! class_exists( 'WP_Error', false ) ) {
	class WP_Error {
		public $code; public $message; public $data;
		public function __construct( $c = '', $m = '', $d = '' ) { $this->code = $c; $this->message = $m; $this->data = $d; }
		public function get_error_message() { return $this->message; }
	}
}
if ( ! defined( 'OBJECT' ) ) { define( 'OBJECT', 'OBJECT' ); }
foreach ( array(
	'get_option' => static function ( $n, $d = false ) { return $GLOBALS['_options'][ $n ] ?? $d; },
	'update_option' => static function ( $n, $v, $a = null ) { unset( $a ); $GLOBALS['_options'][ $n ] = $v; return true; },
	'current_time' => static function ( $t, $g = false ) { unset( $t, $g ); return '2026-08-26 00:00:00'; },
	'wp_kses_post' => static function ( $v ) { return (string) $v; },
	'__' => static function ( $t, $d = null ) { unset( $d ); return $t; },
	'is_wp_error' => static function ( $t ) { return $t instanceof WP_Error; },
	'get_page_by_path' => static function ( $s, $o = null, $ty = 'page' ) { unset( $s, $o, $ty ); return null; },
	'get_posts' => static function ( $a = array() ) { unset( $a ); return array(); },
	'get_post' => static function ( $id ) { return $GLOBALS['_posts'][ (int) $id ] ?? null; },
	'get_post_meta' => static function ( $id, $k, $s = false ) { unset( $s ); return $GLOBALS['_post_meta'][ (int) $id ][ $k ] ?? ''; },
	'wp_update_post' => static function ( $data, $e = false ) { unset( $e ); return (int) ( $data['ID'] ?? 0 ); },
	'wp_insert_post' => static function ( $d, $e = false ) { unset( $d, $e ); return 21; },
) as $fn => $cb ) {
	if ( ! function_exists( $fn ) ) {
		eval( 'function ' . $fn . '() { return call_user_func_array( $GLOBALS["rms_fn"][' . var_export( $fn, true ) . '], func_get_args() ); }' );
		$GLOBALS['rms_fn'][ $fn ] = $cb;
	}
}
if ( ! function_exists( 'update_post_meta' ) ) {
	function update_post_meta( $id, $k, $v ) {
		$GLOBALS['rms_meta_writes'][] = $k;
		$GLOBALS['_post_meta'][ (int) $id ][ $k ] = $v;
		return true;
	}
}

$GLOBALS['_options'] = $GLOBALS['_posts'] = $GLOBALS['_post_meta'] = array();
$GLOBALS['rms_meta_writes'] = array();
foreach ( array( 'class-logger.php', 'class-state-manager.php', 'class-yoast-meta-writer.php', 'class-content-builder.php', 'class-step-generate-pages.php' ) as $f ) {
	require_once dirname( __DIR__ ) . '/inc/wizard/' . $f;
}

$logger = new \Inc\Wizard\Logger();
$sm     = new \Inc\Wizard\State_Manager();
$GLOBALS['_posts'][21] = (object) array( 'ID' => 21, 'post_type' => 'page', 'post_title' => 'About', 'post_name' => 'about', 'post_status' => 'publish', 'post_content' => '' );
$GLOBALS['_post_meta'][21]['page_sections'] = array( array( 'acf_fc_layout' => 'about-us', 'about_headline' => 'Keep me' ) );
( new \Inc\Wizard\Content_Builder( $logger, $sm ) )->build_page( array( 'id' => 21, 'title' => 'About', 'slug' => 'about' ) );
rms_tpl_assert( ! in_array( 'page_sections', $GLOBALS['rms_meta_writes'], true ) && 'Keep me' === ( $GLOBALS['_post_meta'][21]['page_sections'][0]['about_headline'] ?? '' ), 'no section save' );
echo "PASS content-builder-skips-section-save\n";
++$passed;

class RMS_Gen_Spy_Builder extends \Inc\Wizard\Content_Builder {
	public $calls = array();
	public function build_page( array $page ): int {
		$this->calls[] = $page;
		return absint( $page['id'] ?? 0 ) ?: 12;
	}
}
$spy = new RMS_Gen_Spy_Builder( $logger, $sm );
$gp  = new \Inc\Wizard\Step_Generate_Pages( $logger, $sm, $spy );
$sel = new ReflectionMethod( \Inc\Wizard\Step_Generate_Pages::class, 'selected_pages' );
$sel->setAccessible( true );
$picked = $sel->invoke( $gp, array( 'pages' => array( 'about-us' => array( 'type' => 'about', 'slug' => 'about-us', 'title' => 'About Us', 'generate' => true ) ) ) );
rms_tpl_assert( isset( $picked['about-us'] ) && 'about' === ( $picked['about-us']['type'] ?? '' ), 'UI payload keeps about type' );
echo "PASS generate-pages-explicit-type-custom-slug\n";
++$passed;

$result = $gp->run( array( 'pages' => array( 'home' => array( 'type' => 'home', 'slug' => 'home', 'title' => 'Home', 'role' => 'home', 'generate' => true ), 'about-us' => array( 'type' => 'about', 'slug' => 'about-us', 'title' => 'About Us', 'generate' => true ), 'testimonials' => array( 'type' => 'testimonials', 'slug' => 'testimonials', 'title' => 'Testimonials', 'generate' => true ), 'blog' => array( 'type' => 'blog', 'slug' => 'blog', 'title' => 'Blog', 'role' => 'blog', 'generate' => true ) ), 'confirm_cleanup' => true ) );
rms_tpl_assert( ! is_wp_error( $result ), 'generate-pages run succeeds' );
$by_slug = array();
foreach ( $spy->calls as $call ) { $by_slug[ (string) ( $call['slug'] ?? '' ) ] = $call; }
rms_tpl_assert( 'pages/about-us.php' === ( $by_slug['about-us']['meta_input']['_wp_page_template'] ?? '' ), 'about-us slug gets About template' );
rms_tpl_assert( ! isset( $by_slug['about-us']['sections'] ), 'about shell has no sections key' );
rms_tpl_assert( ! isset( $by_slug['testimonials']['meta_input']['_wp_page_template'] ) && ! isset( $by_slug['blog']['meta_input']['_wp_page_template'] ) && ! isset( $by_slug['home']['meta_input']['_wp_page_template'] ), 'deferred and unblueprinted' );
echo "PASS generate-pages-runtime-template-and-no-sections\n";
++$passed;

echo 'Harness passed: ' . $passed . " scenarios.\n";
