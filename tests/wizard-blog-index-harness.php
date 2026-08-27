<?php
/**
 * Blog index (`home.php`) chrome, posts loop, and asset gating proofs.
 *
 * Usage: php tests/wizard-blog-index-harness.php
 */

if ( PHP_SAPI !== 'cli' ) {
	fwrite( STDERR, "CLI only.\n" );
	exit( 1 );
}

$theme_root = dirname( __DIR__ );
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', $theme_root . '/' );
}

function rms_blog_assert( $c, string $m ): void {
	if ( ! $c ) {
		fwrite( STDERR, $m . "\n" );
		exit( 1 );
	}
}

$passed = 0;
$home   = file_get_contents( $theme_root . '/home.php' );
rms_blog_assert( is_string( $home ) && false !== strpos( $home, "get_option( 'page_for_posts' )" ), 'chrome uses page_for_posts' );
rms_blog_assert( false !== strpos( $home, 'page-sections-loop' ), 'chrome uses shared loop' );
rms_blog_assert( false === strpos( $home, 'wp_insert_post' ) && false === strpos( $home, 'wp_update_post' ), 'no post writes' );
rms_blog_assert( false !== strpos( $home, 'have_posts' ) && false !== strpos( $home, 'blog-index__empty' ), 'posts loop and empty state' );
rms_blog_assert( false !== strpos( $home, 'the_posts_pagination' ), 'pagination path' );
echo "PASS home-php-posts-page-chrome-and-loop\n";
++$passed;

$header = file_get_contents( $theme_root . '/header.php' );
rms_blog_assert( is_string( $header ) && false === strpos( $header, 'services-page.scss' ), 'services-page asset not gated on Services' );
rms_blog_assert( false !== strpos( $header, "pages/services.php" ) && false !== strpos( $header, 'rms_page_section_layouts' ), 'Services uses stored layouts' );
rms_blog_assert( false !== strpos( $header, 'is_home()' ) && false !== strpos( $header, "get_option('page_for_posts')" ), 'blog assets on is_home posts page' );
rms_blog_assert( false !== strpos( $header, '$rms_internal_templates' ) && false === strpos( $header, "is_page() && !is_front_page()" ), 'no generic is_page internal CSS' );
rms_blog_assert( 1 === preg_match( "/\\\$rms_internal_templates = \\[(.*?)\\]/s", $header, $m ) && false === strpos( $m[1], 'thank-you.php' ) && false === strpos( $m[1], 'page.php' ), 'Thank You/default excluded from internal list' );
rms_blog_assert( false !== strpos( $header, "pages/blog.php" ) && false !== strpos( $header, '!is_home()' ), 'pages/blog.php not used for posts index' );
echo "PASS services-blog-asset-gating\n";
++$passed;

$blog_v1 = file_get_contents( $theme_root . '/templates/blog-v1.php' );
rms_blog_assert( is_string( $blog_v1 ) && false !== strpos( $blog_v1, 'blog_headline' ), 'blog-v1 uses ACF headline' );
rms_blog_assert( false === strpos( $blog_v1, 'placehold.co' ) && false === strpos( $blog_v1, 'blog-v1__featured' ) && false === strpos( $blog_v1, 'blog-v1__card' ), 'blog-v1 has no demo cards' );
rms_blog_assert( false === strpos( $blog_v1, 'have_posts' ) && false === strpos( $blog_v1, 'the_post' ) && false !== strpos( $home, 'have_posts' ), 'home.php owns the sole posts loop' );
echo "PASS blog-v1-chrome-only\n";
++$passed;

$loop = file_get_contents( $theme_root . '/templates/page-sections-loop.php' );
rms_blog_assert( is_string( $loop ) && false !== strpos( $loop, "args['post_id']" ) && false !== strpos( $loop, 'have_rows( \'page_sections\', $rms_acf_id )' ), 'loop accepts explicit post id' );
echo "PASS loop-explicit-post-id-contract\n";
++$passed;

if ( ! function_exists( 'esc_html' ) ) { function esc_html( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8' ); } }
if ( ! function_exists( 'esc_url' ) ) { function esc_url( $u ) { $u = trim( (string) $u ); return ( '' === $u || 0 === stripos( $u, 'javascript:' ) ) ? '' : $u; } }
if ( ! function_exists( 'absint' ) ) { function absint( $v ) { return abs( (int) $v ); } }
if ( ! function_exists( 'sanitize_key' ) ) { function sanitize_key( $k ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ) ); } }
if ( ! function_exists( 'get_the_title' ) ) { function get_the_title( $id = 0 ) { return 'Title ' . (int) $id; } }
if ( ! function_exists( 'get_query_var' ) ) { function get_query_var( $k ) { return $GLOBALS['_q'][ $k ] ?? ''; } }
if ( ! function_exists( 'have_rows' ) ) {
	function have_rows( $sel, $post_id = false ) {
		$GLOBALS['rms_loop_ids'][] = $post_id;
		$rows = $GLOBALS['rms_loop_rows'] ?? array();
		if ( 'page_sections' !== $sel ) { return false; }
		return empty( $GLOBALS['rms_loop_started'] ) ? count( $rows ) > 0 : $GLOBALS['rms_loop_index'] < count( $rows );
	}
}
if ( ! function_exists( 'the_row' ) ) { function the_row() { $GLOBALS['rms_loop_started'] = true; $GLOBALS['rms_loop_index']++; return true; } }
if ( ! function_exists( 'get_row_layout' ) ) { function get_row_layout() { return (string) ( $GLOBALS['rms_loop_rows'][ $GLOBALS['rms_loop_index'] - 1 ] ?? '' ); } }
if ( ! function_exists( 'get_sub_field' ) ) { function get_sub_field( $s = null ) { return is_array( $GLOBALS['rms_blog_fields'] ?? null ) ? ( $GLOBALS['rms_blog_fields'][ (string) $s ] ?? '' ) : null; } }
if ( ! function_exists( 'locate_template' ) ) {
	function locate_template( $t, $l = false, $r = true ) {
		unset( $l, $r );
		$rel = is_array( $t ) ? (string) ( $t[0] ?? '' ) : (string) $t;
		$path = dirname( __DIR__ ) . '/' . ltrim( $rel, '/' );
		return is_readable( $path ) ? $path : '';
	}
}
if ( ! function_exists( 'get_template_part' ) ) { function get_template_part( $slug, $name = null ) { unset( $name ); $GLOBALS['rms_parts'][] = $slug; } }

$GLOBALS['rms_loop_rows'] = array( 'blog-v1' );
$GLOBALS['rms_loop_index'] = 0;
$GLOBALS['rms_loop_started'] = false;
$GLOBALS['rms_loop_ids'] = array();
$GLOBALS['rms_parts'] = array();
$args = array( 'post_id' => 88 );
require $theme_root . '/templates/page-sections-loop.php';
rms_blog_assert( array() !== $GLOBALS['rms_loop_ids'] && array() === array_diff( $GLOBALS['rms_loop_ids'], array( 88 ) ), 'explicit id reaches have_rows' );
rms_blog_assert( array( 'templates/blog-v1' ) === $GLOBALS['rms_parts'], 'chrome layout from posts page id' );
echo "PASS sections-from-posts-page-id-not-loop-post\n";
++$passed;

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $t = '', $c = null, $p = 10, $a = 1 ) { unset( $t, $c, $p, $a ); return true; }
}
if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta( $id, $k, $s = false ) { unset( $s ); return $GLOBALS['_meta'][ (int) $id ][ $k ] ?? ''; }
}
require_once $theme_root . '/inc/acf-template-boundary.php';
$GLOBALS['_meta'][9]['page_sections'] = array( array( 'acf_fc_layout' => 'blog-v1' ), array( 'acf_fc_layout' => 'cta-v2' ) );
rms_blog_assert( array( 'blog-v1', 'cta-v2' ) === rms_page_section_layouts( 9 ), 'layout helper reads stored rows' );
rms_blog_assert( array() === rms_page_section_layouts( 0 ), 'zero id empty' );
echo "PASS stored-layout-helper\n";
++$passed;

$render = static function ( array $fields ) use ( $theme_root ): string {
	$GLOBALS['rms_blog_fields'] = $fields;
	ob_start();
	include $theme_root . '/templates/blog-v1.php';
	return (string) ob_get_clean();
};
$ok = $render( array( 'blog_headline' => 'Latest', 'blog_cta_text' => 'Read more', 'blog_cta_url' => 'https://example.test/blog' ) );
rms_blog_assert( false !== strpos( $ok, 'blog-v1__cta' ) && false !== strpos( $ok, 'https://example.test/blog' ), 'complete CTA renders' );
rms_blog_assert( false === strpos( $render( array( 'blog_cta_text' => 'Read more', 'blog_cta_url' => '' ) ), 'blog-v1__cta' ), 'text without URL omitted' );
rms_blog_assert( false === strpos( $render( array( 'blog_cta_text' => '', 'blog_cta_url' => 'https://example.test/blog' ) ), 'blog-v1__cta' ), 'URL without text omitted' );
echo "PASS blog-v1-cta-requires-text-and-url\n";
++$passed;

echo 'Harness passed: ' . $passed . " scenarios.\n";
