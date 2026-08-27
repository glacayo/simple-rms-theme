<?php
/**
 * Generate Pages page-type identity contract.
 *
 * Usage: php tests/wizard-page-type-contract-harness.php
 */

if ( PHP_SAPI !== 'cli' ) {
	fwrite( STDERR, "CLI only.\n" );
	exit( 1 );
}

$theme_root = dirname( __DIR__ );
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', $theme_root . '/' );
}

if ( ! function_exists( '__' ) ) {
	function __( $t, $d = null ) { unset( $d ); return $t; }
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

if ( ! class_exists( 'Inc\\Wizard\\Logger', false ) ) {
	eval( 'namespace Inc\\Wizard; class Logger { public function log( $l = "", $m = "", $c = array() ) {} } class State_Manager {} class Content_Builder { public function __construct( $a = null, $b = null ) {} }' );
}

require_once $theme_root . '/inc/wizard/class-step-generate-pages.php';

function rms_type_assert( $c, string $m ): void {
	if ( ! $c ) {
		fwrite( STDERR, $m . "\n" );
		exit( 1 );
	}
}

$gp  = new \Inc\Wizard\Step_Generate_Pages( new \Inc\Wizard\Logger(), new \Inc\Wizard\State_Manager(), new \Inc\Wizard\Content_Builder() );
$sel = new ReflectionMethod( \Inc\Wizard\Step_Generate_Pages::class, 'selected_pages' );
$sel->setAccessible( true );

$ui = $sel->invoke(
	$gp,
	array(
		'pages' => array(
			'about-us' => array(
				'type'     => 'about',
				'slug'     => 'about-us',
				'title'    => 'About Us',
				'generate' => true,
				'role'     => '',
			),
		),
	)
);
rms_type_assert( isset( $ui['about-us'] ) && 'about' === $ui['about-us']['type'], 'UI payload keeps about type' );
echo "PASS ui-explicit-type-custom-slug\n";

$tampered = $sel->invoke(
	$gp,
	array(
		'pages' => array(
			'about-us' => array(
				'type'     => '../header',
				'slug'     => 'about-us',
				'title'    => 'About Us',
				'generate' => true,
			),
		),
	)
);
rms_type_assert( isset( $tampered['about-us'] ) && '' === $tampered['about-us']['type'], 'unknown type ignored' );
echo "PASS unknown-type-ignored\n";

$legacy = $sel->invoke(
	$gp,
	array(
		'pages' => array(
			'about' => array(
				'slug'  => 'about-us',
				'title' => 'About Us',
			),
		),
	)
);
rms_type_assert( isset( $legacy['about-us'] ) && 'about' === $legacy['about-us']['type'], 'legacy key still maps about' );
echo "PASS legacy-key-fallback\n";

$default = $sel->invoke( $gp, array( 'pages' => array( 'services' => true ) ) );
rms_type_assert( isset( $default['services'] ) && 'services' === $default['services']['type'], 'legacy slug catalog key' );
echo "PASS legacy-slug-catalog\n";

$custom = $sel->invoke(
	$gp,
	array(
		'pages' => array(
			'our-company' => array(
				'type'     => 'about',
				'slug'     => 'our-company',
				'title'    => 'Our Company',
				'generate' => true,
			),
		),
	)
);
rms_type_assert( isset( $custom['our-company'] ) && 'about' === $custom['our-company']['type'], 'our-company keeps about type' );
echo "PASS our-company-explicit-about-type\n";

echo "Harness passed: 5 scenarios.\n";
