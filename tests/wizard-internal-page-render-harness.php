<?php
/**
 * Runtime rendering for internal templates, Blog index, and unmarked placeholders.
 *
 * Usage: php tests/wizard-internal-page-render-harness.php
 */
if ( PHP_SAPI !== 'cli' ) {
	fwrite( STDERR, "CLI only.\n" );
	exit( 1 );
}

$theme_root = dirname( __DIR__ );
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', $theme_root . '/' );
}

$GLOBALS['rms_loop_rows'] = array();
$GLOBALS['rms_loop_index'] = 0;
$GLOBALS['rms_loop_started'] = false;
$GLOBALS['rms_fields'] = array();
$GLOBALS['rms_have_posts'] = false;
$GLOBALS['rms_posts'] = array();
$GLOBALS['rms_post_i'] = 0;
$GLOBALS['rms_headers'] = 0;
$GLOBALS['rms_footers'] = 0;
$GLOBALS['rms_parts'] = array();

if ( ! function_exists( 'esc_html' ) ) { function esc_html( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8' ); } }
if ( ! function_exists( 'esc_html__' ) ) { function esc_html__( $t, $d = null ) { unset( $d ); return (string) $t; } }
if ( ! function_exists( 'esc_url' ) ) { function esc_url( $u ) { return (string) $u; } }
if ( ! function_exists( 'esc_attr' ) ) { function esc_attr( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8' ); } }
if ( ! function_exists( 'wp_kses_post' ) ) { function wp_kses_post( $v ) { return (string) $v; } }
if ( ! function_exists( 'absint' ) ) { function absint( $v ) { return abs( (int) $v ); } }
if ( ! function_exists( 'sanitize_key' ) ) { function sanitize_key( $k ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ) ); } }
if ( ! function_exists( 'get_the_title' ) ) { function get_the_title( $id = 0 ) { unset( $id ); return 'Page'; } }
if ( ! function_exists( 'get_permalink' ) ) { function get_permalink() { return '/post'; } }
if ( ! function_exists( 'post_class' ) ) { function post_class() { echo 'class="post"'; } }
if ( ! function_exists( 'the_excerpt' ) ) { function the_excerpt() { echo 'Excerpt'; } }
if ( ! function_exists( 'the_posts_pagination' ) ) { function the_posts_pagination() { echo '<nav class="pagination"></nav>'; } }
if ( ! function_exists( 'get_option' ) ) { function get_option( $n, $d = false ) { return $GLOBALS['_options'][ $n ] ?? $d; } }
if ( ! function_exists( 'set_query_var' ) ) { function set_query_var( $k, $v ) { $GLOBALS['_q'][ $k ] = $v; } }
if ( ! function_exists( 'get_query_var' ) ) { function get_query_var( $k ) { return $GLOBALS['_q'][ $k ] ?? ''; } }
if ( ! function_exists( 'have_posts' ) ) {
	function have_posts() {
		return $GLOBALS['rms_post_i'] < count( $GLOBALS['rms_posts'] );
	}
}
if ( ! function_exists( 'the_post' ) ) {
	function the_post() {
		$GLOBALS['rms_post_i']++;
	}
}
if ( ! function_exists( 'get_header' ) ) { function get_header() { $GLOBALS['rms_headers']++; } }
if ( ! function_exists( 'get_footer' ) ) { function get_footer() { $GLOBALS['rms_footers']++; } }
if ( ! function_exists( 'have_rows' ) ) {
	function have_rows( $sel, $post_id = false ) {
		unset( $post_id );
		if ( 'page_sections' !== $sel ) {
			return false;
		}
		return $GLOBALS['rms_loop_started'] ? $GLOBALS['rms_loop_index'] < count( $GLOBALS['rms_loop_rows'] ) : count( $GLOBALS['rms_loop_rows'] ) > 0;
	}
}
if ( ! function_exists( 'the_row' ) ) { function the_row() { $GLOBALS['rms_loop_started'] = true; $GLOBALS['rms_loop_index']++; return true; } }
if ( ! function_exists( 'get_row_layout' ) ) { function get_row_layout() { return (string) ( $GLOBALS['rms_loop_rows'][ $GLOBALS['rms_loop_index'] - 1 ] ?? '' ); } }
if ( ! function_exists( 'get_sub_field' ) ) { function get_sub_field( $s = null ) { return $GLOBALS['rms_fields'][ (string) $s ] ?? null; } }
if ( ! function_exists( 'locate_template' ) ) {
	function locate_template( $templates, $load = false, $require_once = true ) {
		unset( $load, $require_once );
		foreach ( is_array( $templates ) ? $templates : array( $templates ) as $rel ) {
			$path = dirname( __DIR__ ) . '/' . ltrim( (string) $rel, '/' );
			if ( is_readable( $path ) ) {
				return $path;
			}
		}
		return '';
	}
}
if ( ! function_exists( 'get_template_part' ) ) {
	function get_template_part( $slug, $name = null, $args = array() ) {
		unset( $name );
		$GLOBALS['rms_parts'][] = $slug;
		if ( in_array( $slug, array( 'templates/breadcrumb', 'templates/contact-map' ), true ) ) {
			return;
		}
		if ( is_array( $args ) && isset( $args['post_id'] ) ) {
			$GLOBALS['_q']['rms_page_sections_post_id'] = (int) $args['post_id'];
		}
		$path = dirname( __DIR__ ) . '/' . ltrim( (string) $slug, '/' ) . '.php';
		if ( is_readable( $path ) ) {
			include $path;
		}
	}
}
if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta( $id, $k, $s = false ) {
		unset( $s );
		return $GLOBALS['_post_meta'][ (int) $id ][ $k ] ?? '';
	}
}
if ( ! function_exists( 'get_template_directory' ) ) { function get_template_directory() { return dirname( __DIR__ ); } }
if ( ! function_exists( 'trailingslashit' ) ) { function trailingslashit( $v ) { return rtrim( (string) $v, '/\\' ) . '/'; } }

require_once $theme_root . '/inc/wizard/class-ai-content-harness.php';
require_once $theme_root . '/inc/wizard/class-internal-page-blueprints.php';
require_once $theme_root . '/inc/wizard/class-internal-page-identity.php';

function rms_render_assert( $c, string $m ): void {
	if ( ! $c ) {
		fwrite( STDERR, $m . "\n" );
		exit( 1 );
	}
}

function rms_render_reset(): void {
	$GLOBALS['rms_loop_rows'] = array();
	$GLOBALS['rms_loop_index'] = 0;
	$GLOBALS['rms_loop_started'] = false;
	$GLOBALS['rms_fields'] = array();
	$GLOBALS['rms_parts'] = array();
	$GLOBALS['rms_headers'] = 0;
	$GLOBALS['rms_footers'] = 0;
	$GLOBALS['rms_posts'] = array();
	$GLOBALS['rms_post_i'] = 0;
	$GLOBALS['_q'] = array();
	$GLOBALS['_options'] = array();
	$GLOBALS['_post_meta'] = array();
}

$passed = 0;
rms_render_reset();
$GLOBALS['_post_meta'][7]['_wp_page_template'] = 'pages/contact-us.php';
$resolved = \Inc\Wizard\Internal_Page_Identity::resolve_assigned_template( 7 );
rms_render_assert( 'pages/contact-us.php' === $resolved, 'first render resolves blueprint template' );
ob_start();
include $theme_root . '/' . $resolved;
$contact_out = (string) ob_get_clean();
rms_render_assert( false === strpos( $resolved, 'page.php' ), 'assigned template is not page.php' );
rms_render_assert( 1 === $GLOBALS['rms_headers'] && in_array( 'templates/page-sections-loop', $GLOBALS['rms_parts'], true ), 'contact template executed' );
echo "PASS first-render-blueprint-template\n";
++$passed;

rms_render_reset();
$GLOBALS['_post_meta'][8]['_wp_page_template'] = 'pages/about-us.php';
$about_resolved = \Inc\Wizard\Internal_Page_Identity::resolve_assigned_template( 8 );
rms_render_assert( 'pages/about-us.php' === $about_resolved && 'page.php' !== $about_resolved, 'complete internal page does not fall back to page.php' );
echo "PASS built-page-never-page-php\n";
++$passed;

rms_render_reset();
$GLOBALS['rms_loop_rows'] = array( 'services-v1' );
$GLOBALS['rms_fields'] = array(
	'services_v1_headline' => 'Stored Services Headline',
	'services_v1_subheadline' => 'Stored sub',
	'services_v1_services' => array( array( 'service_title' => 'Repair', 'service_text' => 'We repair.' ) ),
	'services_v1_cta_text' => 'Call',
	'services_v1_cta_url' => '/contact',
);
ob_start();
include $theme_root . '/pages/services.php';
$services_out = (string) ob_get_clean();
rms_render_assert( false !== strpos( $services_out, 'Stored Services Headline' ), 'services stored row rendered' );
rms_render_assert( false === strpos( $services_out, 'Roof Installation' ), 'static demo services markup absent when rows exist' );
echo "PASS services-renders-stored-rows\n";
++$passed;

rms_render_reset();
$GLOBALS['rms_loop_rows'] = array( 'testimonials-v1' );
$GLOBALS['rms_fields'] = array(
	'testimonials_v1_headline' => 'Stored Testimonials',
	'testimonials_v1_subheadline' => 'From clients',
	'testimonials_v1_items' => array( array( 'testimonial_quote' => 'Great work.', 'testimonial_author' => 'Alex', 'testimonial_role' => 'Owner', 'testimonial_stars' => 5 ) ),
);
ob_start();
include $theme_root . '/pages/testimonials.php';
$tm_out = (string) ob_get_clean();
rms_render_assert( false !== strpos( $tm_out, 'Stored Testimonials' ) && false !== strpos( $tm_out, 'Great work.' ), 'testimonial rows rendered' );
rms_render_assert( false === strpos( $tm_out, 'Maria Johnson' ), 'hardcoded fallback testimonials absent when rows exist' );
echo "PASS testimonial-rows-render\n";
++$passed;

rms_render_reset();
$GLOBALS['rms_loop_rows'] = array( 'about-us' );
$GLOBALS['rms_fields'] = array( 'about_headline' => 'Placeholder Company Services You Can Trust' );
ob_start();
include $theme_root . '/templates/about-us.php';
$ph_out = (string) ob_get_clean();
rms_render_assert( false !== strpos( $ph_out, 'Placeholder Company Services You Can Trust' ), 'placeholder value renders' );
rms_render_assert( false === stripos( $ph_out, 'placeholder-label' ) && false === stripos( $ph_out, 'badge-placeholder' ) && false === stripos( $ph_out, 'unverified placeholder' ), 'placeholder renders unmarked' );
echo "PASS placeholder-renders-unmarked\n";
++$passed;

rms_render_reset();
$GLOBALS['_options']['page_for_posts'] = 9;
$GLOBALS['rms_loop_rows'] = array( 'blog-v1' );
$GLOBALS['rms_fields'] = array( 'blog_headline' => 'Latest Updates', 'blog_cta_text' => 'Read', 'blog_cta_url' => '/blog' );
$GLOBALS['rms_posts'] = array( array( 'title' => 'One' ) );
ob_start();
include $theme_root . '/home.php';
$blog_out = (string) ob_get_clean();
rms_render_assert( false !== strpos( $blog_out, 'class="blog-index"' ) && false !== strpos( $blog_out, 'Latest Updates' ), 'home.php chrome and loop executed' );
rms_render_assert( false !== strpos( $blog_out, '<article ' ), 'posts loop rendered' );
echo "PASS blog-index-chrome-and-loop\n";
++$passed;

rms_render_reset();
$GLOBALS['_options']['page_for_posts'] = 9;
$GLOBALS['rms_loop_rows'] = array( 'blog-v1' );
$GLOBALS['rms_fields'] = array( 'blog_headline' => 'Latest Updates' );
$GLOBALS['rms_posts'] = array();
ob_start();
include $theme_root . '/home.php';
$empty_out = (string) ob_get_clean();
rms_render_assert( false !== strpos( $empty_out, 'blog-index__empty' ), 'zero-post empty state rendered' );
echo "PASS blog-empty-index-empty-state\n";
++$passed;

echo 'Harness passed: ' . $passed . " scenarios.\n";
