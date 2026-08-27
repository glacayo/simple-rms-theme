<?php
/**
 * acf/save_post provenance adapter guards.
 *
 * Usage: php tests/wizard-provenance-hook-harness.php
 */

if ( PHP_SAPI !== 'cli' ) {
	fwrite( STDERR, "CLI only.\n" );
	exit( 1 );
}

$theme_root = dirname( __DIR__ );
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', $theme_root . '/' );
}

$GLOBALS['_options']            = array();
$GLOBALS['_option_autoloads']   = array();
$GLOBALS['_option_writes']      = array();
$GLOBALS['_wp_actions']         = array();
$GLOBALS['_nested_save_post']   = null;
$GLOBALS['_nested_save_result'] = null;
$GLOBALS['_posts']              = array();
$GLOBALS['_field_objects']      = array();
$GLOBALS['_field_object_calls'] = array();
$GLOBALS['_autosave']           = array();
$GLOBALS['_revision']           = array();

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $k ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ) ); }
}
if ( ! function_exists( 'absint' ) ) {
	function absint( $v ) { return abs( (int) $v ); }
}
if ( ! function_exists( 'current_time' ) ) {
	function current_time( $t, $g = false ) { unset( $t, $g ); return '2026-08-27 00:00:00'; }
}
if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $d, $o = 0 ) { return json_encode( $d, $o ); }
}
if ( ! function_exists( 'get_option' ) ) {
	function get_option( $n, $d = false ) { return $GLOBALS['_options'][ $n ] ?? $d; }
}
if ( ! function_exists( 'update_option' ) ) {
	function update_option( $n, $v, $a = null ) {
		$GLOBALS['_options'][ $n ] = $v;
		$GLOBALS['_option_autoloads'][ $n ] = $a;
		$GLOBALS['_option_writes'][] = $n;
		$nested = $GLOBALS['_nested_save_post'];
		$GLOBALS['_nested_save_post'] = null;
		if ( null !== $nested ) {
			$GLOBALS['_nested_save_result'] = \Inc\Wizard\Placeholder_Provenance_Store::handle_acf_save_post( $nested );
		}
		return true;
	}
}
if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['_wp_actions'][] = array( $hook, $callback, $priority, $accepted_args );
		return true;
	}
}
if ( ! function_exists( 'get_post' ) ) {
	function get_post( $id ) { return $GLOBALS['_posts'][ (int) $id ] ?? null; }
}
if ( ! function_exists( 'get_field_object' ) ) {
	function get_field_object( $selector, $post_id = false, $format_value = true, $load_value = true ) {
		$GLOBALS['_field_object_calls'][] = array( $selector, $post_id, $format_value, $load_value );
		$id = (int) $post_id;
		if ( ! array_key_exists( $id, $GLOBALS['_field_objects'] ) || ! array_key_exists( $selector, $GLOBALS['_field_objects'][ $id ] ) ) {
			return false;
		}
		return $GLOBALS['_field_objects'][ $id ][ $selector ];
	}
}
if ( ! function_exists( 'wp_is_post_autosave' ) ) {
	function wp_is_post_autosave( $id ) { return ! empty( $GLOBALS['_autosave'][ (int) $id ] ); }
}
if ( ! function_exists( 'wp_is_post_revision' ) ) {
	function wp_is_post_revision( $id ) { return ! empty( $GLOBALS['_revision'][ (int) $id ] ); }
}

require_once $theme_root . '/inc/wizard/class-placeholder-provenance-store.php';

use Inc\Wizard\Placeholder_Provenance_Store;

function rms_hook_assert( $c, string $m ): void {
	if ( ! $c ) {
		fwrite( STDERR, $m . "\n" );
		exit( 1 );
	}
}

function rms_hook_set_sections( int $id, $value ): void {
	$GLOBALS['_field_objects'][ $id ]['page_sections'] = array(
		'name'  => 'page_sections',
		'type'  => 'flexible_content',
		'value' => $value,
	);
}

function rms_hook_fresh_query( int $id ): array {
	return ( new Placeholder_Provenance_Store() )->query( $id );
}

function rms_hook_provenance_writes(): int {
	$n = 0;
	foreach ( $GLOBALS['_option_writes'] as $key ) {
		if ( Placeholder_Provenance_Store::OPTION_KEY === $key ) {
			++$n;
		}
	}
	return $n;
}

function rms_hook_last_field_object_call(): array {
	$calls = $GLOBALS['_field_object_calls'];
	return is_array( $calls ) && [] !== $calls ? $calls[ count( $calls ) - 1 ] : array();
}

Placeholder_Provenance_Store::register();
$reg = $GLOBALS['_wp_actions'][0] ?? null;
rms_hook_assert(
	is_array( $reg )
	&& 'acf/save_post' === $reg[0]
	&& array( Placeholder_Provenance_Store::class, 'handle_acf_save_post' ) === $reg[1]
	&& 20 === $reg[2]
	&& 1 === $reg[3],
	'runtime register acf/save_post priority 20 args 1'
);
echo "PASS hook-registered-priority-20\n";
$passed = 1;

$store = new Placeholder_Provenance_Store();
$store->record( 50, 'about-us', 0, 'about_headline', 'missing_client_fact', 'Hello' );
$GLOBALS['_posts'][50] = (object) array( 'post_type' => 'page' );
$snapshot = array( array( 'acf_fc_layout' => 'about-us', 'about_headline' => 'Hello' ) );
rms_hook_set_sections( 50, $snapshot );
$GLOBALS['_option_writes'] = array();
rms_hook_assert( Placeholder_Provenance_Store::handle_acf_save_post( 50 ), 'valid page save' );
rms_hook_assert( isset( rms_hook_fresh_query( 50 )['0:about_headline'] ), 'valid save keeps named-key match' );
rms_hook_assert( 1 === rms_hook_provenance_writes(), 'nonempty snapshot syncs once' );
rms_hook_assert( array( 'page_sections', 50, true, true ) === rms_hook_last_field_object_call(), 'formatted loaded field object' );
echo "PASS valid-full-save-sync\n";
++$passed;

$before = rms_hook_fresh_query( 50 );
rms_hook_assert( false === Placeholder_Provenance_Store::handle_acf_save_post( 'options' ), 'options skipped' );
rms_hook_assert( false === Placeholder_Provenance_Store::handle_acf_save_post( 'user_1' ), 'user skipped' );
rms_hook_assert( false === Placeholder_Provenance_Store::handle_acf_save_post( 'term_3' ), 'term skipped' );
$GLOBALS['_posts'][51] = (object) array( 'post_type' => 'post' );
rms_hook_set_sections( 51, array() );
rms_hook_assert( false === Placeholder_Provenance_Store::handle_acf_save_post( 51 ), 'non-page skipped' );
$GLOBALS['_autosave'][50] = true;
rms_hook_assert( false === Placeholder_Provenance_Store::handle_acf_save_post( 50 ), 'autosave skipped' );
unset( $GLOBALS['_autosave'][50] );
$GLOBALS['_revision'][50] = true;
rms_hook_assert( false === Placeholder_Provenance_Store::handle_acf_save_post( 50 ), 'revision skipped' );
unset( $GLOBALS['_revision'][50] );
unset( $GLOBALS['_field_objects'][50]['page_sections'] );
rms_hook_assert( false === Placeholder_Provenance_Store::handle_acf_save_post( 50 ) && $before === rms_hook_fresh_query( 50 ), 'missing field object no-op' );
rms_hook_set_sections( 50, array( 'about_headline' => 'Hello' ) );
rms_hook_assert( false === Placeholder_Provenance_Store::handle_acf_save_post( 50 ) && $before === rms_hook_fresh_query( 50 ), 'malformed snapshot no-op' );
echo "PASS hook-guards-no-op\n";
++$passed;

rms_hook_set_sections( 50, $snapshot );
$store->record( 60, 'contact-info', 0, 'contact_info_headline', 'missing_client_fact', 'Hi' );
$GLOBALS['_posts'][60] = (object) array( 'post_type' => 'page' );
rms_hook_set_sections( 60, array( array( 'acf_fc_layout' => 'contact-info', 'contact_info_headline' => 'Changed' ) ) );
Placeholder_Provenance_Store::handle_acf_save_post( 60 );
rms_hook_assert( array() === rms_hook_fresh_query( 60 ) && isset( rms_hook_fresh_query( 50 )['0:about_headline'] ), 'page isolation' );
echo "PASS page-isolation-and-replace\n";
++$passed;

$store->record( 70, 'about-us', 0, 'about_headline', 'missing_client_fact', 'Keep' );
$GLOBALS['_posts'][70] = (object) array( 'post_type' => 'page' );
rms_hook_set_sections( 70, array( array( 'acf_fc_layout' => 'about-us', 'about_headline' => 'Keep' ) ) );
rms_hook_set_sections( 50, false );
$GLOBALS['_option_writes'] = array();
rms_hook_assert( true === Placeholder_Provenance_Store::handle_acf_save_post( 50 ), 'empty complete save' );
rms_hook_assert( array() === rms_hook_fresh_query( 50 ), 'empty value=false clears this page' );
rms_hook_assert( isset( rms_hook_fresh_query( 70 )['0:about_headline'] ), 'empty save is page-scoped' );
rms_hook_assert( 1 === rms_hook_provenance_writes(), 'empty snapshot syncs once' );
echo "PASS empty-field-object-clears-only-that-page\n";
++$passed;

( new Placeholder_Provenance_Store() )->record( 50, 'about-us', 0, 'about_headline', 'missing_client_fact', 'Hello' );
$kept = rms_hook_fresh_query( 50 );
$GLOBALS['_field_objects'][50]['page_sections'] = false;
rms_hook_assert( false === Placeholder_Provenance_Store::handle_acf_save_post( 50 ) && $kept === rms_hook_fresh_query( 50 ), 'false field object does not clear' );
echo "PASS field-object-read-failure-untouched\n";
++$passed;

$GLOBALS['_field_objects'][50]['page_sections'] = array(
	'name' => 'page_sections',
	'type' => 'flexible_content',
);
rms_hook_assert( false === Placeholder_Provenance_Store::handle_acf_save_post( 50 ) && $kept === rms_hook_fresh_query( 50 ), 'missing value does not clear' );
rms_hook_set_sections( 50, array( 'about_headline' => 'Hello' ) );
rms_hook_assert( false === Placeholder_Provenance_Store::handle_acf_save_post( 50 ) && $kept === rms_hook_fresh_query( 50 ), 'malformed nonempty does not clear' );
echo "PASS missing-or-malformed-value-untouched\n";
++$passed;

rms_hook_set_sections( 50, $snapshot );
$GLOBALS['_option_writes'] = array();
rms_hook_assert( Placeholder_Provenance_Store::handle_acf_save_post( 50 ), 'nonempty complete save' );
rms_hook_assert( isset( rms_hook_fresh_query( 50 )['0:about_headline'] ), 'nonempty named keys still match' );
rms_hook_assert( 1 === rms_hook_provenance_writes(), 'nonempty full snapshot syncs once' );
echo "PASS nonempty-full-snapshot-syncs-once\n";
++$passed;

( new Placeholder_Provenance_Store() )->record( 80, 'about-us', 0, 'about_headline', 'missing_client_fact', 'Nested' );
$GLOBALS['_posts'][80] = (object) array( 'post_type' => 'page' );
rms_hook_set_sections( 80, false );
$kept80 = rms_hook_fresh_query( 80 );
rms_hook_set_sections( 50, $snapshot );
$GLOBALS['_option_writes']      = array();
$GLOBALS['_nested_save_post']   = 80;
$GLOBALS['_nested_save_result'] = null;
rms_hook_assert( Placeholder_Provenance_Store::handle_acf_save_post( 50 ), 'outer save during nested attempt' );
rms_hook_assert( false === $GLOBALS['_nested_save_result'], 'nested handle refused' );
rms_hook_assert( 1 === rms_hook_provenance_writes(), 'nested attempt does not write again' );
rms_hook_assert( $kept80 === rms_hook_fresh_query( 80 ), 'nested empty snapshot does not clear other page' );
rms_hook_assert( isset( rms_hook_fresh_query( 50 )['0:about_headline'] ), 'outer page still synced' );
echo "PASS nested-handle-reentrancy\n";
++$passed;

echo 'Harness passed: ' . $passed . " scenarios.\n";
