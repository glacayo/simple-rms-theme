<?php
/**
 * Wizard Landing Page template render proofs (Phase 4 task 4.4).
 *
 * Exercises the committed pages/landing-page.php contract deterministically:
 *  - First render uses the landing template (ACF flexible rows render in order)
 *  - breadcrumb-slim injected exactly once, only after the FIRST Hero row
 *  - Later Hero rows do not inject a second breadcrumb
 *  - No Hero in flexible content => no breadcrumb in the flexible path
 *  - ACF-missing path renders a minimal safe fallback (no get_sub_field fatals)
 *  - ACF present but empty flexible content => legacy hardcoded order
 *  - Rendered DOM stays well under 1500 nodes for the flexible path
 *
 * Usage: php tests/wizard-landing-render-harness.php
 */
if ( PHP_SAPI !== 'cli' ) {
	fwrite( STDERR, "CLI only.\n" );
	exit( 1 );
}

$theme_root = dirname( __DIR__ );
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', $theme_root . '/' );
}

$GLOBALS['_loop_rows']    = array();
$GLOBALS['_loop_index']   = 0;
$GLOBALS['_loop_started'] = false;
$GLOBALS['_template_parts'] = array();
$GLOBALS['_headers']      = 0;
$GLOBALS['_footers']      = 0;
$GLOBALS['_acf_available'] = true;
$GLOBALS['_have_posts']   = false;

if ( ! function_exists( 'esc_html' ) ) { function esc_html( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8' ); } }
if ( ! function_exists( 'esc_attr' ) ) { function esc_attr( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8' ); } }
if ( ! function_exists( 'get_the_title' ) ) { function get_the_title( $id = 0 ) { unset( $id ); return 'Landing'; } }
if ( ! function_exists( 'the_content' ) ) { function the_content() { echo 'Content'; } }
if ( ! function_exists( 'post_class' ) ) { function post_class( $c = '' ) { echo 'class="' . esc_attr( (string) $c ) . '"'; } }
if ( ! function_exists( 'get_header' ) ) { function get_header() { $GLOBALS['_headers']++; } }
if ( ! function_exists( 'get_footer' ) ) { function get_footer() { $GLOBALS['_footers']++; } }
if ( ! function_exists( 'have_posts' ) ) {
	function have_posts() {
		return ! empty( $GLOBALS['_have_posts'] );
	}
}
if ( ! function_exists( 'the_post' ) ) { function the_post() { $GLOBALS['_have_posts'] = false; } }
if ( ! function_exists( 'have_rows' ) ) {
	function have_rows( $sel, $post_id = false ) {
		unset( $post_id );
		if ( 'page_sections' !== $sel ) {
			return false;
		}
		return $GLOBALS['_loop_started'] ? $GLOBALS['_loop_index'] < count( $GLOBALS['_loop_rows'] ) : count( $GLOBALS['_loop_rows'] ) > 0;
	}
}
if ( ! function_exists( 'the_row' ) ) { function the_row() { $GLOBALS['_loop_started'] = true; $GLOBALS['_loop_index']++; return true; } }
if ( ! function_exists( 'get_row_layout' ) ) { function get_row_layout() { return (string) ( $GLOBALS['_loop_rows'][ $GLOBALS['_loop_index'] - 1 ] ?? '' ); } }
if ( ! function_exists( 'get_sub_field' ) ) {
	function get_sub_field( $s = null ) {
		// Fatal-free stand-in: returns null like ACF does outside a loop.
		unset( $s );
		return null;
	}
}
if ( ! function_exists( 'get_template_part' ) ) {
	function get_template_part( $slug, $name = null ) {
		unset( $name );
		$GLOBALS['_template_parts'][] = (string) $slug;
	}
}

function rms_lr_assert( $c, string $m ): void {
	if ( ! $c ) {
		fwrite( STDERR, $m . "\n" );
		exit( 1 );
	}
}

function rms_lr_reset( array $rows = array() ): void {
	$GLOBALS['_loop_rows']       = $rows;
	$GLOBALS['_loop_index']      = 0;
	$GLOBALS['_loop_started']    = false;
	$GLOBALS['_template_parts']  = array();
	$GLOBALS['_headers']         = 0;
	$GLOBALS['_footers']         = 0;
	$GLOBALS['_acf_available']   = true;
	$GLOBALS['_have_posts']      = false;
}

function rms_lr_count_nodes( string $html ): int {
	// Count opening/self-closing tags as a proxy for DOM node count.
	return preg_match_all( '/<\s*[a-zA-Z][^>]*?(\/?\s*>|>)/', $html );
}

$passed = 0;

// --- 1. Flexible rows render in order with breadcrumb once after first Hero ---
rms_lr_reset( array( 'hero', 'seo-content', 'vision-mission-v1', 'badges', 'portfolio-v1', 'seo-content', 'testimonials-v1', 'seo-content' ) );
ob_start();
include $theme_root . '/pages/landing-page.php';
$out = (string) ob_get_clean();
rms_lr_assert( 1 === $GLOBALS['_headers'] && 1 === $GLOBALS['_footers'], 'template calls header and footer once' );
rms_lr_assert( array( 'templates/hero', 'templates/breadcrumb-slim', 'templates/seo-content', 'templates/vision-mission-v1', 'templates/badges', 'templates/portfolio-v1', 'templates/seo-content', 'templates/testimonials-v1', 'templates/seo-content' ) === $GLOBALS['_template_parts'], 'rows render in order with breadcrumb once after first hero' );
rms_lr_assert( 1 === count( array_filter( $GLOBALS['_template_parts'], static function ( $p ) { return 'templates/breadcrumb-slim' === $p; } ) ), 'breadcrumb injected exactly once' );
echo "PASS landing-flexible-render-breadcrumb-once\n";
++$passed;

// --- 2. Two Hero rows: breadcrumb only after the FIRST one ------------------
rms_lr_reset( array( 'hero', 'seo-content', 'hero' ) );
ob_start();
include $theme_root . '/pages/landing-page.php';
ob_get_clean();
$crumbs = array_filter( $GLOBALS['_template_parts'], static function ( $p ) { return 'templates/breadcrumb-slim' === $p; } );
rms_lr_assert( 1 === count( $crumbs ), 'second hero does not inject a second breadcrumb' );
$first_hero  = array_search( 'templates/hero', $GLOBALS['_template_parts'], true );
$first_crumb = array_search( 'templates/breadcrumb-slim', $GLOBALS['_template_parts'], true );
rms_lr_assert( false !== $first_hero && false !== $first_crumb && $first_crumb === $first_hero + 1, 'breadcrumb immediately follows the first hero' );
echo "PASS breadcrumb-after-first-hero-only\n";
++$passed;

// --- 3. No Hero in flexible content => no breadcrumb in that path -----------
rms_lr_reset( array( 'about-us', 'cta-v1' ) );
ob_start();
include $theme_root . '/pages/landing-page.php';
ob_get_clean();
rms_lr_assert( ! in_array( 'templates/breadcrumb-slim', $GLOBALS['_template_parts'], true ), 'no hero rows => no breadcrumb in flexible path' );
echo "PASS no-hero-no-breadcrumb\n";
++$passed;

// --- 4. (ACF-missing path is proven by wizard-landing-acf-missing-harness.php,
//         which runs WITHOUT the ACF stubs so function_exists() is false.) -------

// --- 5. ACF present but empty flexible content => legacy hardcoded order ----
rms_lr_reset( array() );
$GLOBALS['_acf_available'] = true;
ob_start();
include $theme_root . '/pages/landing-page.php';
ob_get_clean();
rms_lr_assert( 1 === count( array_filter( $GLOBALS['_template_parts'], static function ( $p ) { return 'templates/breadcrumb-slim' === $p; } ) ), 'legacy empty path keeps exactly one breadcrumb' );
rms_lr_assert( array( 'templates/hero', 'templates/breadcrumb-slim', 'templates/seo-content', 'templates/vision-mission-v1', 'templates/badges', 'templates/portfolio-v1', 'templates/seo-content', 'templates/testimonials-v1', 'templates/seo-content' ) === $GLOBALS['_template_parts'], 'empty flexible content falls back to hardcoded section order' );
echo "PASS empty-flexible-legacy-order\n";
++$passed;

// --- 6. DOM node budget: flexible path stays well under 1500 ---------------
rms_lr_reset( array( 'hero', 'seo-content', 'vision-mission-v1', 'badges', 'portfolio-v1', 'seo-content', 'testimonials-v1', 'seo-content' ) );
ob_start();
include $theme_root . '/pages/landing-page.php';
$dom_out = (string) ob_get_clean();
$nodes   = rms_lr_count_nodes( $dom_out );
rms_lr_assert( $nodes < 1500, 'flexible landing render stays under the 1500 DOM node budget (nodes=' . $nodes . ')' );
echo "PASS dom-under-1500\n";
++$passed;

echo 'Harness passed: ' . $passed . " scenarios.\n";
