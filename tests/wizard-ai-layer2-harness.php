<?php
/**
 * Layer 2 page-type contexts and blocked factual collections.
 *
 * Usage: php tests/wizard-ai-layer2-harness.php
 */

if ( PHP_SAPI !== 'cli' ) {
	fwrite( STDERR, "CLI only.\n" );
	exit( 1 );
}

$theme_root = dirname( __DIR__ );
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', $theme_root . '/' );
}
if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $k ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ) ); }
}
if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $d, $o = 0 ) { return json_encode( $d, $o ); }
}
if ( ! function_exists( 'wp_kses_post' ) ) {
	function wp_kses_post( $t ) { return (string) $t; }
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $t ) { return trim( (string) $t ); }
}

require_once $theme_root . '/inc/wizard/class-ai-content-harness.php';
require_once $theme_root . '/inc/wizard/class-internal-page-blueprints.php';

use Inc\Wizard\AI_Content_Harness;
use Inc\Wizard\Internal_Page_Blueprints;

function rms_l2_assert( $c, string $m ): void {
	if ( ! $c ) {
		fwrite( STDERR, $m . "\n" );
		exit( 1 );
	}
}

$h       = new AI_Content_Harness();
$passed  = 0;
$home    = $h->get_layer2();
$landing = $h->get_layer2( AI_Content_Harness::PAGE_LANDING );
rms_l2_assert( 0 === strpos( $home, 'PAGE CONTEXT: Home Page' ), 'default HOME' );
rms_l2_assert( 0 === strpos( $landing, 'PAGE CONTEXT: Landing Page' ), 'LANDING unchanged' );
rms_l2_assert( $home === $h->get_layer2( AI_Content_Harness::PAGE_HOME ), 'HOME explicit' );
echo "PASS home-landing-layer2-unchanged\n";
++$passed;

foreach ( array( AI_Content_Harness::PAGE_ABOUT => 'About', AI_Content_Harness::PAGE_SERVICE => 'Services', AI_Content_Harness::PAGE_CONTACT => 'Contact', AI_Content_Harness::PAGE_BLOG => 'Blog index', AI_Content_Harness::PAGE_PROJECTS => 'Projects', AI_Content_Harness::PAGE_TESTIMONIALS => 'Testimonials' ) as $type => $needle ) {
	$ctx = $h->get_layer2( $type );
	rms_l2_assert( false !== strpos( $ctx, $needle ) && 0 !== strpos( $ctx, 'PAGE CONTEXT: Home Page' ), 'dedicated ' . $type );
}
$blog = $h->get_layer2( AI_Content_Harness::PAGE_BLOG );
rms_l2_assert( false !== strpos( $blog, 'chrome only' ) && false !== strpos( $blog, 'Do not write post bodies' ), 'blog chrome only' );
echo "PASS internal-layer2-dedicated-contexts\n";
++$passed;

$unknown = $h->get_layer2( 'PAGE_UNKNOWN' );
rms_l2_assert( $home === $unknown, 'unknown falls back to HOME' );
echo "PASS unknown-type-falls-back-home\n";
++$passed;

$fill_t = $h->get_fillable_fields( 'testimonials-v1' );
$block_t = $h->get_blocked_fields( 'testimonials-v1' );
rms_l2_assert( in_array( 'testimonials_v1_headline', $fill_t, true ) && in_array( 'testimonials_v1_items', $block_t, true ), 'testimonial items blocked' );
rms_l2_assert( array() === $h->get_fillable_fields( 'gallery-grid' ) && in_array( 'gallery_items', $h->get_blocked_fields( 'gallery-grid' ), true ), 'projects gallery blocked' );
$clean = $h->validate_fields( 'testimonials-v1', array( 'testimonials_v1_headline' => 'Hi', 'testimonials_v1_items' => array( array( 'testimonial_quote' => 'Nope' ) ) ) );
rms_l2_assert( isset( $clean['testimonials_v1_headline'] ) && ! isset( $clean['testimonials_v1_items'] ), 'validate strips items' );
echo "PASS blocked-factual-collections\n";
++$passed;

$l3 = $h->get_layer3( 'about-us', 1, array( 'company_name' => 'Acme' ), AI_Content_Harness::PAGE_ABOUT, array( 'primary_keyword' => 'roof repair' ) );
rms_l2_assert( false === strpos( $l3, 'KEYWORD CONTEXT' ), 'internal types keyword-neutral' );
$all = Internal_Page_Blueprints::all();
rms_l2_assert( AI_Content_Harness::PAGE_PROJECTS === $all['projects']['page_type'] && AI_Content_Harness::PAGE_TESTIMONIALS === $all['testimonials']['page_type'], 'registry constants' );
echo "PASS keyword-neutral-and-registry\n";
++$passed;

echo 'Harness passed: ' . $passed . " scenarios.\n";
