<?php
/**
 * Wizard Landing Page template ACF-degraded render proof (Phase 4 task 4.4).
 *
 * Runs WITHOUT stubbing have_rows/the_row/get_row_layout/get_sub_field so
 * function_exists() is false, exactly like a real environment where ACF Pro
 * is not loaded. Proves pages/landing-page.php renders a minimal safe fallback
 * instead of loading template parts that would fatal on get_sub_field().
 *
 * Usage: php tests/wizard-landing-acf-missing-harness.php
 */
if ( PHP_SAPI !== 'cli' ) {
	fwrite( STDERR, "CLI only.\n" );
	exit( 1 );
}

$theme_root = dirname( __DIR__ );
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', $theme_root . '/' );
}

$GLOBALS['_template_parts'] = array();
$GLOBALS['_headers']        = 0;
$GLOBALS['_footers']        = 0;
$GLOBALS['_have_posts']     = true;

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
if ( ! function_exists( 'get_template_part' ) ) {
	function get_template_part( $slug, $name = null ) {
		unset( $name );
		$GLOBALS['_template_parts'][] = (string) $slug;
	}
}

function rms_lma_assert( $c, string $m ): void {
	if ( ! $c ) {
		fwrite( STDERR, $m . "\n" );
		exit( 1 );
	}
}

$passed = 0;

rms_lma_assert( ! function_exists( 'have_rows' ) && ! function_exists( 'get_sub_field' ), 'ACF helpers are genuinely absent in this harness' );

ob_start();
include $theme_root . '/pages/landing-page.php';
$out = (string) ob_get_clean();

rms_lma_assert( 1 === $GLOBALS['_headers'] && 1 === $GLOBALS['_footers'], 'degraded path still calls header/footer' );
rms_lma_assert( false !== strpos( $out, 'landing-page--acf-missing' ), 'acf-missing fallback wrapper rendered' );
rms_lma_assert( false !== strpos( $out, 'landing-page__title' ), 'acf-missing fallback renders the page title' );
rms_lma_assert( false !== strpos( $out, 'entry-content' ), 'acf-missing fallback renders content' );
rms_lma_assert( array() === $GLOBALS['_template_parts'], 'degraded path loads NO template parts (would fatal on get_sub_field)' );
echo "PASS acf-missing-safe-fallback\n";
++$passed;

echo 'Harness passed: ' . $passed . " scenarios.\n";
