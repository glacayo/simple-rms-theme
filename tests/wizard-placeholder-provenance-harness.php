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

$GLOBALS['_options']          = array();
$GLOBALS['_option_autoloads'] = array();

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
		$GLOBALS['_options'][ $name ]          = $value;
		$GLOBALS['_option_autoloads'][ $name ] = $autoload;
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

$autoload = $GLOBALS['_option_autoloads'][ Placeholder_Provenance_Store::OPTION_KEY ] ?? null;
rms_prov_assert( false === $autoload, 'provenance option must be written with autoload=false' );
echo "PASS provenance-option-autoload-false\n";
++$passed;

$store = new Placeholder_Provenance_Store();
rms_prov_assert( $store->is_placeholder_payload( 'Hello', $store->value_hash( 'Hello' ) ), 'hash matches placeholder' );
rms_prov_assert( ! $store->is_placeholder_payload( 'Real', $store->value_hash( 'Hello' ) ), 'changed value is not placeholder' );
$assoc_a = array( 'b' => 1, 'a' => 2 );
$assoc_b = array( 'a' => 2, 'b' => 1 );
rms_prov_assert( $store->value_hash( $assoc_a ) === $store->value_hash( $assoc_b ), 'assoc key order stable' );
rms_prov_assert( $store->value_hash( array( 'x', 'y' ) ) !== $store->value_hash( array( 'y', 'x' ) ), 'list order changes hash' );
$nested = array( array( 'quote' => 'Hi', 'author' => 'A' ) );
$store->record( 30, 'testimonials-v1', 0, 'testimonials_v1_items', 'missing_client_fact', $nested );
$store->record( 30, 'about-us', 1, 'about_headline', 'missing_client_fact', 'Hello' );
$store->record( 30, 'about-us', 1, 'about_text', 'missing_client_fact', 'Body' );
$store->record( 31, 'contact-info', 0, 'contact_info_headline', 'missing_client_fact', 'Hi' );
$reordered = array(
	array( 'acf_fc_layout' => 'about-us', 'about_headline' => 'Hello', 'about_text' => 'Body' ),
	array( 'acf_fc_layout' => 'testimonials-v1', 'testimonials_v1_items' => $nested ),
);
rms_prov_assert( $store->sync( 30, $reordered ), 'reorder sync' );
$page30 = $store->query( 30 );
rms_prov_assert( isset( $page30['0:about_headline'] ) && 0 === (int) $page30['0:about_headline']['row'] && isset( $page30['1:testimonials_v1_items'] ) && 1 === (int) $page30['1:testimonials_v1_items']['row'], 'reorder reindexes' );
$before = $page30;
rms_prov_assert( $store->sync( 30, $reordered ) && $before === $store->query( 30 ), 'sync idempotent' );
$inserted = array(
	array( 'acf_fc_layout' => 'cta-v2', 'cta_v2_headline' => 'New' ),
	array( 'acf_fc_layout' => 'about-us', 'about_headline' => 'Hello', 'about_text' => 'Body' ),
	array( 'acf_fc_layout' => 'testimonials-v1', 'testimonials_v1_items' => $nested ),
);
rms_prov_assert( $store->sync( 30, $inserted ), 'insert sync' );
$page30 = $store->query( 30 );
rms_prov_assert( isset( $page30['1:about_headline'] ) && isset( $page30['2:testimonials_v1_items'] ) && 3 === count( $page30 ), 'insert shifts rows' );
$deleted = array(
	array( 'acf_fc_layout' => 'about-us', 'about_headline' => 'Hello', 'about_text' => 'Body' ),
);
rms_prov_assert( $store->sync( 30, $deleted ), 'delete sync' );
$page30 = $store->query( 30 );
rms_prov_assert( isset( $page30['0:about_headline'] ) && isset( $page30['0:about_text'] ) && ! isset( $page30['1:testimonials_v1_items'] ) && ! isset( $page30['2:testimonials_v1_items'] ), 'deleted layout cleared' );
$other = $store->query( 31 );
rms_prov_assert( isset( $other['0:contact_info_headline'] ), 'other page preserved' );
$snapshot = $store->query( 30 );
rms_prov_assert( false === $store->sync( 30, array( 'about_headline' => 'Hello' ) ) && $snapshot === $store->query( 30 ), 'malformed assoc no-op' );
rms_prov_assert( false === $store->sync( 30, array( 'not-a-row' ) ) && $snapshot === $store->query( 30 ), 'malformed scalar row no-op' );
rms_prov_assert( $store->sync( 30, array() ) && array() === $store->query( 30 ), 'valid empty snapshot clears page' );
rms_prov_assert( isset( $store->query( 31 )['0:contact_info_headline'] ), 'empty snapshot is page-scoped' );
rms_prov_assert( false === ( $GLOBALS['_option_autoloads'][ Placeholder_Provenance_Store::OPTION_KEY ] ?? null ), 'sync keeps autoload false' );
echo "PASS provenance-sync-reorder-hash-malformed-empty\n";
++$passed;

$dup = new Placeholder_Provenance_Store();
rms_prov_assert( $dup->record( 40, 'about-us', 0, 'about_headline', 'missing_client_fact', 'Dup' ), 'dup row 0' );
rms_prov_assert( $dup->record( 40, 'about-us', 1, 'about_headline', 'missing_client_fact', 'Dup' ), 'dup row 1' );
rms_prov_assert( $dup->record( 41, 'contact-info', 0, 'contact_info_headline', 'missing_client_fact', 'Keep' ), 'other page' );
rms_prov_assert( 2 === count( $dup->query( 40, 'about_headline' ) ), 'two identical occurrences recorded' );
$dup_snap = array(
	array( 'acf_fc_layout' => 'about-us', 'about_headline' => 'Replaced' ),
	array( 'acf_fc_layout' => 'about-us', 'about_headline' => 'Dup' ),
);
rms_prov_assert( $dup->sync( 40, $dup_snap ), 'dup sync' );
$page40 = $dup->query( 40 );
rms_prov_assert( 1 === count( $page40 ) && isset( $page40['1:about_headline'] ) && 1 === (int) $page40['1:about_headline']['row'], 'exactly one occurrence at current row' );
$q40 = array_values( array_filter( $dup->queue(), static function ( $row ) { return 40 === (int) $row['post_id']; } ) );
rms_prov_assert( 1 === count( $q40 ) && 'about_headline' === ( $q40[0]['field'] ?? '' ) && 1 === (int) $q40[0]['row'], 'queue has one remaining' );
rms_prov_assert( isset( $dup->query( 41 )['0:contact_info_headline'] ), 'other page untouched' );
$after = $dup->query( 40 );
rms_prov_assert( $dup->sync( 40, $dup_snap ) && $after === $dup->query( 40 ), 'dup sync idempotent' );
echo "PASS provenance-sync-duplicate-occurrence-multiset\n";
++$passed;

echo 'Harness passed: ' . $passed . " scenarios.\n";
