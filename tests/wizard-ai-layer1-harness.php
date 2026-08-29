<?php
/**
 * Layer 1 editorial contract and guide-absent prompt proofs.
 *
 * Usage: php tests/wizard-ai-layer1-harness.php
 */
if ( PHP_SAPI !== 'cli' ) {
	fwrite( STDERR, "CLI only.\n" );
	exit( 1 );
}

$theme_root = dirname( __DIR__ );
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', $theme_root . '/' );
}
if ( ! function_exists( 'sanitize_key' ) ) { function sanitize_key( $k ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ) ); } }
if ( ! function_exists( 'sanitize_text_field' ) ) { function sanitize_text_field( $s ) { return trim( (string) $s ); } }
if ( ! function_exists( 'wp_kses_post' ) ) { function wp_kses_post( $v ) { return (string) $v; } }
if ( ! function_exists( 'wp_json_encode' ) ) { function wp_json_encode( $d, $o = 0 ) { return json_encode( $d, $o ); } }
if ( ! function_exists( '__' ) ) { function __( $t, $d = null ) { unset( $d ); return $t; } }

require_once $theme_root . '/inc/wizard/class-ai-content-harness.php';

use Inc\Wizard\AI_Content_Harness;

function rms_l1_assert( $c, string $m ): void {
	if ( ! $c ) {
		fwrite( STDERR, $m . "\n" );
		exit( 1 );
	}
}

$passed = 0;
$harness = new AI_Content_Harness();
$layer1 = $harness->get_layer1();
$layer2 = $harness->get_layer2( AI_Content_Harness::PAGE_ABOUT );
$layer3 = $harness->get_layer3( 'about-us', 1, array( 'company_name' => 'Acme' ), AI_Content_Harness::PAGE_ABOUT );
rms_l1_assert( is_string( $layer1 ) && '' !== $layer1 && is_string( $layer2 ) && is_string( $layer3 ), 'all three layers returned' );
echo "PASS prompts-without-guide-file\n";
++$passed;

rms_l1_assert( false !== stripos( $layer1, 'headline' ) && false !== strpos( $layer1, '6 to 12 words' ), 'headline word-count ranges present' );
rms_l1_assert( false !== stripos( $layer1, 'paragraph' ), 'paragraph density rules present' );
rms_l1_assert( false !== stripos( $layer1, 'CTA' ) || false !== stripos( $layer1, 'action verb' ), 'CTA conventions present' );
rms_l1_assert( false !== stripos( $layer1, 'Do not invent' ) && false !== stripos( $layer1, 'NO-INVENTION' ), 'no-invention constraints present' );
echo "PASS layer1-editorial-standards\n";
++$passed;

echo 'Harness passed: ' . $passed . " scenarios.\n";
