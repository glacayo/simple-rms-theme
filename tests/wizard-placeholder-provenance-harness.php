<?php
/**
 * Provenance store record/query/queue proofs.
 *
 * Usage: php tests/wizard-placeholder-provenance-harness.php
 */

if ( PHP_SAPI !== 'cli' ) {
	fwrite( STDERR, "CLI only.\n" );
	exit( 1 );
}

$theme_root = dirname( __DIR__ );
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', $theme_root . '/' );
}

$GLOBALS['_options'] = array();

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) ) );
	}
}
if ( ! function_exists( 'absint' ) ) {
	function absint( $value ) {
		return abs( (int) $value );
	}
}
if ( ! function_exists( 'current_time' ) ) {
	function current_time( $type, $gmt = false ) {
		unset( $type, $gmt );
		return '2026-08-26 00:00:00';
	}
}
if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data, $options = 0 ) {
		return json_encode( $data, $options );
	}
}
if ( ! function_exists( 'get_option' ) ) {
	function get_option( $name, $default = false ) {
		return $GLOBALS['_options'][ $name ] ?? $default;
	}
}
if ( ! function_exists( 'update_option' ) ) {
	function update_option( $name, $value, $autoload = null ) {
		unset( $autoload );
		$GLOBALS['_options'][ $name ] = $value;
		return true;
	}
}

require_once $theme_root . '/inc/wizard/class-placeholder-provenance-store.php';

use Inc\Wizard\Placeholder_Provenance_Store;

function rms_prov_assert( $condition, string $message ): void {
	if ( ! $condition ) {
		fwrite( STDERR, $message . "\n" );
		exit( 1 );
	}
}

$passed = 0;
$store  = new Placeholder_Provenance_Store();

rms_prov_assert( false === $store->record( 0, 'about-us', 0, 'about_headline', 'missing_client_fact', 'x' ), 'invalid post_id rejected' );
rms_prov_assert( $store->record( 21, 'about-us', 0, 'about_headline', 'missing_client_fact', 'Hello' ), 'record page 21' );
rms_prov_assert( $store->record( 21, 'about-us', 0, 'about_text', 'missing_client_fact', 'Body' ), 'second field same page' );
rms_prov_assert( $store->record( 22, 'contact-info', 1, 'contact_headline', 'missing_client_fact', 'Hi' ), 'record page 22' );
echo "PASS provenance-record\n";
++$passed;

$page21 = $store->query( 21 );
rms_prov_assert( 2 === count( $page21 ) && isset( $page21['0:about_headline'] ), 'query returns page 21 fields' );
rms_prov_assert( 'about-us' === ( $page21['0:about_headline']['layout'] ?? '' ), 'layout stored' );
rms_prov_assert( 'missing_client_fact' === ( $page21['0:about_headline']['reason'] ?? '' ), 'reason stored' );
$headline = $store->query( 21, 'about_headline' );
rms_prov_assert( 1 === count( $headline ) && isset( $headline['0:about_headline'] ), 'query by field' );
$page22 = $store->query( 22 );
rms_prov_assert( 1 === count( $page22 ) && ! isset( $page22['0:about_headline'] ), 'query is page-scoped' );
echo "PASS provenance-query-by-page\n";
++$passed;

$queue = $store->queue();
rms_prov_assert( 3 === count( $queue ), 'queue lists all outstanding fields' );
$ids = array_values( array_unique( array_map( static function ( $row ) { return (int) $row['post_id']; }, $queue ) ) );
sort( $ids );
rms_prov_assert( array( 21, 22 ) === $ids, 'queue names both pages' );
echo "PASS provenance-queue\n";
++$passed;

echo 'Harness passed: ' . $passed . " scenarios.\n";
