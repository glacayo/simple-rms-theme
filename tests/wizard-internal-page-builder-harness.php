<?php
/**
 * Internal page builder proofs.
 *
 * Usage: php tests/wizard-internal-page-builder-harness.php
 */
if ( PHP_SAPI !== 'cli' ) { fwrite( STDERR, "CLI only.\n" ); exit( 1 ); }
$theme_root = dirname( __DIR__ );
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', $theme_root . '/' ); }
if ( ! class_exists( 'WP_Error', false ) ) {
	class WP_Error { public $code; public $message; public $data; public function __construct( $c = '', $m = '', $d = '' ) { $this->code = $c; $this->message = $m; $this->data = $d; } }
}
if ( ! class_exists( 'WP_Post', false ) ) {
	class WP_Post { public $ID; public $post_type = 'page'; public $post_content = ''; public $post_name = ''; public function __construct( $id = 0 ) { $this->ID = (int) $id; } }
}
$GLOBALS['_options'] = $GLOBALS['_posts'] = $GLOBALS['_post_meta'] = $GLOBALS['_build_log'] = array();
$GLOBALS['_next_id'] = 20;
if ( ! function_exists( 'sanitize_text_field' ) ) { function sanitize_text_field( $s ) { return trim( preg_replace( '/\s+/', ' ', (string) $s ) ); } }
if ( ! function_exists( 'sanitize_key' ) ) { function sanitize_key( $k ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ) ); } }
if ( ! function_exists( 'sanitize_title' ) ) { function sanitize_title( $v ) { return strtolower( preg_replace( '/[^a-z0-9-]+/', '-', (string) $v ) ); } }
if ( ! function_exists( '__' ) ) { function __( $t, $d = null ) { unset( $d ); return $t; } }
if ( ! function_exists( 'wp_kses_post' ) ) { function wp_kses_post( $v ) { return (string) $v; } }
if ( ! function_exists( 'wp_json_encode' ) ) { function wp_json_encode( $d, $o = 0 ) { return json_encode( $d, $o ); } }
if ( ! function_exists( 'absint' ) ) { function absint( $v ) { return abs( (int) $v ); } }
if ( ! function_exists( 'current_time' ) ) { function current_time( $t, $g = false ) { unset( $t, $g ); return '2026-08-26 00:00:00'; } }
if ( ! function_exists( 'get_option' ) ) { function get_option( $n, $d = false ) { return $GLOBALS['_options'][ $n ] ?? $d; } }
if ( ! function_exists( 'update_option' ) ) { function update_option( $n, $v, $a = null ) { unset( $a ); $GLOBALS['_options'][ $n ] = $v; return true; } }
if ( ! function_exists( 'get_post' ) ) { function get_post( $id ) { return $GLOBALS['_posts'][ (int) $id ] ?? null; } }
if ( ! function_exists( 'get_page_by_path' ) ) {
	function get_page_by_path( $s, $o = null, $t = 'page' ) {
		unset( $o, $t );
		return $GLOBALS['_page_by_path'][ (string) $s ] ?? null;
	}
}
if ( ! function_exists( 'get_posts' ) ) { function get_posts( $a = array() ) { unset( $a ); return array(); } }
if ( ! function_exists( 'wp_delete_post' ) ) { function wp_delete_post( $id, $force = false ) { unset( $id, $force ); return true; } }
if ( ! function_exists( 'esc_html' ) ) { function esc_html( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8' ); } }
if ( ! defined( 'OBJECT' ) ) { define( 'OBJECT', 'OBJECT' ); }
if ( ! function_exists( 'get_post_meta' ) ) { function get_post_meta( $id, $k, $s = false ) { unset( $s ); return $GLOBALS['_post_meta'][ (int) $id ][ $k ] ?? ''; } }
if ( ! function_exists( 'is_wp_error' ) ) { function is_wp_error( $t ) { return $t instanceof WP_Error; } }
if ( ! function_exists( 'get_template_directory_uri' ) ) { function get_template_directory_uri() { return 'https://example.test/theme'; } }
if ( ! function_exists( 'trailingslashit' ) ) { function trailingslashit( $v ) { return rtrim( (string) $v, '/\\' ) . '/'; } }
if ( ! function_exists( 'maybe_serialize' ) ) { function maybe_serialize( $v ) { return is_array( $v ) || is_object( $v ) ? serialize( $v ) : $v; } }
if ( ! function_exists( 'maybe_unserialize' ) ) {
	function maybe_unserialize( $v ) {
		if ( ! is_string( $v ) ) { return $v; }
		$d = @unserialize( $v );
		return false === $d && 'b:0;' !== $v ? $v : $d;
	}
}
if ( ! function_exists( 'wp_cache_delete' ) ) { function wp_cache_delete( $k, $g = '' ) { unset( $k, $g ); return true; } }
if ( ! function_exists( 'wp_generate_uuid4' ) ) { function wp_generate_uuid4() { return 'owner-' . ( $GLOBALS['_wpdb_inserts'] + 1 ); } }
$GLOBALS['_db_options'] = array();
$GLOBALS['_wpdb_inserts'] = 0;
$GLOBALS['_fence_owner'] = '';
if ( ! isset( $GLOBALS['wpdb'] ) ) {
	$GLOBALS['wpdb'] = new class {
		public $options = 'wp_options';
		public function prepare( $q, ...$a ) { return array( 'sql' => (string) $q, 'args' => $a ); }
		public function get_var( $q ) {
			$name = is_array( $q ) ? (string) ( $q['args'][0] ?? '' ) : '';
			return $GLOBALS['_db_options'][ $name ] ?? null;
		}
		public function query( $q ) {
			$sql  = is_array( $q ) ? (string) $q['sql'] : (string) $q;
			$args = is_array( $q ) ? $q['args'] : array();
			$name = (string) ( $args[0] ?? '' );
			if ( false !== stripos( $sql, 'INSERT' ) ) {
				if ( isset( $GLOBALS['_db_options'][ $name ] ) ) { return 0; }
				$GLOBALS['_db_options'][ $name ] = (string) ( $args[1] ?? '' );
				$GLOBALS['_wpdb_inserts']++;
				return 1;
			}
			if ( false !== stripos( $sql, 'DELETE' ) ) {
				$raw = (string) ( $args[1] ?? '' );
				if ( ( $GLOBALS['_db_options'][ $name ] ?? null ) === $raw ) {
					unset( $GLOBALS['_db_options'][ $name ] );
					return 1;
				}
				return 0;
			}
			return 0;
		}
	};
}
foreach ( array( 'class-logger.php', 'class-state-manager.php', 'class-wizard-mutation-fence.php', 'class-step-controller.php', 'class-ai-content-harness.php', 'class-canonical-section-store.php', 'class-yoast-meta-writer.php', 'class-content-builder.php', 'class-section-assembler.php', 'class-placeholder-provenance-store.php', 'class-internal-page-blueprints.php', 'class-internal-page-identity.php', 'class-step-internal-page-builder.php', 'class-step-generate-pages.php' ) as $f ) {
	require_once $theme_root . '/inc/wizard/' . $f;
}
use Inc\Wizard\AI_Content_Harness;
use Inc\Wizard\Canonical_Section_Store;
use Inc\Wizard\Content_Builder;
use Inc\Wizard\Internal_Page_Blueprints;
use Inc\Wizard\Logger;
use Inc\Wizard\Placeholder_Provenance_Store;
use Inc\Wizard\State_Manager;
use Inc\Wizard\Step_Controller;
use Inc\Wizard\Step_Generate_Pages;
use Inc\Wizard\Step_Internal_Page_Builder;
use Inc\Wizard\Wizard_Mutation_Fence;
function rms_ipb_release_fence(): void {
	$owner = (string) ( $GLOBALS['_fence_owner'] ?? '' );
	if ( '' === $owner ) { return; }
	$fence = new Wizard_Mutation_Fence();
	$fence->clear_agent( $owner );
	$fence->release( $owner );
	$GLOBALS['_fence_owner'] = '';
}
function rms_ipb_authorize_instance( Step_Internal_Page_Builder $builder ): void {
	rms_ipb_release_fence();
	$fence = new Wizard_Mutation_Fence();
	$owner = $fence->acquire();
	rms_ipb_assert( ! is_wp_error( $owner ) && is_string( $owner ) && '' !== $owner, 'harness fence acquire' );
	$builder->accept_mutation_owner( $owner );
	rms_ipb_assert( $fence->authorize_agent( $builder, $owner ), 'harness authorize agent' );
	$GLOBALS['_fence_owner'] = $owner;
}
class RMS_Internal_Fake_Builder extends Content_Builder {
	public function build_page( array $page ): int {
		if ( ! empty( $GLOBALS['_fail_build'] ) ) { return 0; }
		$id = absint( $page['id'] ?? 0 );
		if ( $id <= 0 ) { $GLOBALS['_next_id'] = ( $GLOBALS['_next_id'] ?? 20 ) + 1; $id = $GLOBALS['_next_id']; }
		$GLOBALS['_build_log'][] = $page;
		if ( $id > 0 && isset( $page['sections'] ) ) { $GLOBALS['_post_meta'][ $id ]['page_sections'] = $page['sections']; }
		if ( $id > 0 && isset( $page['meta_input']['_wp_page_template'] ) ) { $GLOBALS['_post_meta'][ $id ]['_wp_page_template'] = $page['meta_input']['_wp_page_template']; }
		return $id;
	}
}
function rms_ipb_assert( $c, string $m ): void { if ( ! $c ) { fwrite( STDERR, $m . "\n" ); exit( 1 ); } }
function rms_ipb_reset(): void {
	rms_ipb_release_fence();
	$GLOBALS['_options'] = $GLOBALS['_posts'] = $GLOBALS['_post_meta'] = $GLOBALS['_build_log'] = array();
	$GLOBALS['_fail_build'] = false;
	$GLOBALS['_next_id'] = 20;
	$GLOBALS['_page_by_path'] = array();
	$GLOBALS['_db_options'] = array();
	$GLOBALS['_wpdb_inserts'] = 0;
}
function rms_ipb_builder(): Step_Internal_Page_Builder {
	$l = new Logger(); $s = new State_Manager();
	$builder = new Step_Internal_Page_Builder( $l, $s, new RMS_Internal_Fake_Builder( $l, $s ), new AI_Content_Harness(), new Canonical_Section_Store() );
	rms_ipb_authorize_instance( $builder );
	return $builder;
}
function rms_ipb_seed_about(): void {
	$GLOBALS['_posts'][12] = new WP_Post( 12 ); $GLOBALS['_posts'][12]->post_name = 'about'; $sm = new State_Manager(); $st = $sm->get_state();
	$st['generated_pages'] = array( array( 'id' => 12, 'slug' => 'about', 'role' => '' ), array( 'id' => 99, 'slug' => 'home', 'role' => 'home' ) );
	$sm->save_state( $st );
}
$passed = 0;
rms_ipb_reset();
$GLOBALS['_posts'][12] = new WP_Post( 12 ); $GLOBALS['_posts'][12]->post_name = 'about';
$GLOBALS['_posts'][13] = new WP_Post( 13 ); $GLOBALS['_posts'][13]->post_name = 'services';
$GLOBALS['_post_meta'][13]['page_sections'] = array( array( 'acf_fc_layout' => 'services-v1', 'services_v1_headline' => 'Do not touch' ) );
$sm = new State_Manager(); $st = $sm->get_state();
$st['generated_pages'] = array(
	array( 'id' => 12, 'slug' => 'about', 'type' => 'about' ),
	array( 'id' => 13, 'slug' => 'services', 'type' => 'services' ),
);
$sm->save_state( $st );
$b = rms_ipb_builder();
$b->run( array( 'action' => 'start' ) );
$about = $b->run( array( 'action' => 'process' ) );
rms_ipb_assert( 'about' === ( $about['processed'] ?? '' ) && 'complete' === ( $about['status'] ?? '' ), 'about processed' );
rms_ipb_assert( array( 12 ) === array_map( 'intval', array_column( $GLOBALS['_build_log'], 'id' ) ), 'only about was written' );
rms_ipb_assert( 'Do not touch' === ( $GLOBALS['_post_meta'][13]['page_sections'][0]['services_v1_headline'] ?? '' ), 'services content preserved' );
$raw = new Step_Internal_Page_Builder( new Logger(), new State_Manager(), new RMS_Internal_Fake_Builder( new Logger(), new State_Manager() ) );
$unfenced = $raw->run( array( 'action' => 'process' ) );
rms_ipb_assert( is_wp_error( $unfenced ) && 'rms_wizard_mutation_unfenced' === $unfenced->code, 'unauthorized instance rejected while fence held' );
rms_ipb_assert( array( 12 ) === array_map( 'intval', array_column( $GLOBALS['_build_log'], 'id' ) ), 'unauthorized instance wrote nothing' );
rms_ipb_assert( false === method_exists( $b, 'acquire' ) && false === method_exists( $b, 'acquire_lock' ), 'builder has no fence acquire' );
$stolen = (string) $GLOBALS['_fence_owner'];
$forged = new Step_Internal_Page_Builder( new Logger(), new State_Manager(), new RMS_Internal_Fake_Builder( new Logger(), new State_Manager() ) );
$forged->accept_mutation_owner( $stolen );
rms_ipb_assert( false === ( new Wizard_Mutation_Fence() )->authorize_agent( $forged, $stolen ), 'second instance cannot take the agent slot' );
$forged_run = $forged->run( array( 'action' => 'process' ) );
rms_ipb_assert( is_wp_error( $forged_run ) && 'rms_wizard_mutation_unfenced' === $forged_run->code, 'forged owner on other instance rejected' );
$again = $b->run( array( 'action' => 'process' ) );
rms_ipb_assert( ! is_wp_error( $again ) && 'services' === ( $again['processed'] ?? '' ), 'same authorized instance can continue' );
echo "PASS about-only-preserves-and-instance-bound-fence\n"; ++$passed;
rms_ipb_reset(); $skip = rms_ipb_builder()->run( array( 'skip_all' => true ) );
rms_ipb_assert( ! is_wp_error( $skip ) && ! empty( $skip['skipped'] ) && array() === $GLOBALS['_build_log'], 'skip-all' );
echo "PASS skip-all-no-mutation\n"; ++$passed;
rms_ipb_reset(); rms_ipb_seed_about(); $b = rms_ipb_builder(); $b->run( array( 'action' => 'start' ) ); $built = $b->run( array( 'action' => 'process' ) );
$page = $GLOBALS['_build_log'][0];
rms_ipb_assert( 'complete' === ( $built['status'] ?? '' ) && 'pages/about-us.php' === ( $page['meta_input']['_wp_page_template'] ?? '' ), 'happy path' );
rms_ipb_assert( array( 'about-us', 'vision-mission-v2' ) === array_column( $page['sections'], 'acf_fc_layout' ), 'layouts' );
rms_ipb_assert( AI_Content_Harness::PAGE_ABOUT === Internal_Page_Blueprints::all()['about']['page_type'], 'PAGE_ABOUT' );
echo "PASS about-happy-path-blueprint\n"; ++$passed;
rms_ipb_reset(); ( new State_Manager() )->save_state( array( 'generated_pages' => array( array( 'id' => 99, 'slug' => 'home', 'role' => 'home' ) ) ) );
$GLOBALS['_posts'][99] = new WP_Post( 99 ); $missing = rms_ipb_builder()->run( array( 'action' => 'process' ) );
rms_ipb_assert( array() === $GLOBALS['_build_log'] && ( 'unavailable' === ( $missing['reason'] ?? '' ) || 'skipped' === ( $missing['status'] ?? '' ) ), 'missing shell' );
echo "PASS missing-shell-not-created\n"; ++$passed;
rms_ipb_reset(); rms_ipb_seed_about(); $c = new Canonical_Section_Store();
$c->set_if_empty( 'about-us', array( 'acf_fc_layout' => 'about-us', 'about_headline' => 'Canonical About' ) ); $before = $c->get( 'about-us' );
$b = rms_ipb_builder(); $b->run( array( 'action' => 'start' ) ); $b->run( array( 'action' => 'process' ) );
rms_ipb_assert( $before === ( new Canonical_Section_Store() )->get( 'about-us' ) && 'Canonical About' === ( $GLOBALS['_build_log'][0]['sections'][0]['about_headline'] ?? '' ), 'canonical copy' );
echo "PASS canonical-copy-unchanged\n"; ++$passed;
rms_ipb_reset(); rms_ipb_seed_about(); $b = rms_ipb_builder(); $b->run( array( 'action' => 'start' ) ); $b->run( array( 'action' => 'process' ) );
rms_ipb_assert( false === ( new Canonical_Section_Store() )->has( 'about-us' ) && count( ( new Placeholder_Provenance_Store() )->query( 12 ) ) >= 1, 'placeholders' );
echo "PASS placeholders-provenance-not-canonical\n"; ++$passed;
rms_ipb_reset(); rms_ipb_seed_about();
$GLOBALS['_post_meta'][12]['page_sections'] = array( array( 'acf_fc_layout' => 'about-us', 'about_headline' => 'Editor edit' ) );
$sm = new State_Manager(); $st = $sm->get_state();
$st['internal_pages']['about'] = array_merge( State_Manager::INTERNAL_PAGE_ENTRY, array( 'post_id' => 12, 'status' => 'complete' ) ); $sm->save_state( $st );
$noop = rms_ipb_builder()->run( array( 'action' => 'process' ) );
rms_ipb_assert( array() === $GLOBALS['_build_log'] && 'complete' === ( $noop['status'] ?? '' ) && 'Editor edit' === ( $GLOBALS['_post_meta'][12]['page_sections'][0]['about_headline'] ?? '' ), 'preserve' );
echo "PASS preserve-edit-and-complete-noop\n"; ++$passed;
rms_ipb_reset(); rms_ipb_seed_about();
$GLOBALS['_post_meta'][12]['page_sections'] = array( array( 'acf_fc_layout' => 'about-us', 'about_headline' => 'Editor edit' ) );
$sm = new State_Manager(); $st = $sm->get_state();
$st['internal_pages']['about'] = array_merge( State_Manager::INTERNAL_PAGE_ENTRY, array( 'post_id' => 12, 'status' => 'complete' ) ); $sm->save_state( $st );
$over = rms_ipb_builder()->run( array( 'action' => 'process', 'overwrite' => array( 'about' ) ) );
rms_ipb_assert( 1 === count( $GLOBALS['_build_log'] ) && 'complete' === ( $over['status'] ?? '' ), 'overwrite' );
echo "PASS explicit-overwrite-regenerates\n"; ++$passed;
rms_ipb_reset(); $GLOBALS['_posts'][12] = new WP_Post( 12 ); $GLOBALS['_posts'][12]->post_content = 'Legacy about body';
$sm = new State_Manager(); $st = $sm->get_state(); $st['generated_pages'] = array( array( 'id' => 12, 'slug' => 'about', 'role' => '' ) ); $sm->save_state( $st );
$b = rms_ipb_builder(); $b->run( array( 'action' => 'start' ) ); $legacy = $b->run( array( 'action' => 'process' ) );
rms_ipb_assert( 'skipped' === ( $legacy['status'] ?? '' ) && array() === $GLOBALS['_build_log'], 'legacy skip' );
$conv = $b->run( array( 'action' => 'process', 'convert_legacy' => array( 'about' ) ) );
rms_ipb_assert( 'complete' === ( $conv['status'] ?? '' ) && 1 === count( $GLOBALS['_build_log'] ), 'legacy convert' );
echo "PASS legacy-unconfirmed-then-convert\n"; ++$passed;
rms_ipb_reset(); rms_ipb_seed_about(); $GLOBALS['_fail_build'] = true;
$b = rms_ipb_builder(); $b->run( array( 'action' => 'start' ) ); $fail = $b->run( array( 'action' => 'process' ) );
rms_ipb_assert( 'failed' === ( $fail['status'] ?? '' ) && 'persist_failed' === ( $fail['reason'] ?? '' ) && ! is_wp_error( $fail ), 'isolated fail' );
$GLOBALS['_fail_build'] = false; $retried = $b->run( array( 'action' => 'process', 'retry_failed' => true ) );
rms_ipb_assert( 'complete' === ( $retried['status'] ?? '' ), 'retry' );
echo "PASS failure-isolation-and-retry\n"; ++$passed;
rms_ipb_reset();
$l = new Logger(); $sm = new State_Manager();
$gen = new Step_Generate_Pages( $l, $sm, new RMS_Internal_Fake_Builder( $l, $sm ) );
$out = $gen->run( array( 'pages' => array( 'home' => array( 'type' => 'home', 'slug' => 'home', 'title' => 'Home', 'role' => 'home', 'generate' => true ), 'our-company' => array( 'type' => 'about', 'slug' => 'our-company', 'title' => 'Our Company', 'generate' => true ) ), 'confirm_cleanup' => true ) );
rms_ipb_assert( ! is_wp_error( $out ), 'generate-pages run' );
$by = array();
foreach ( $out['generated_pages'] as $row ) { $by[ (string) $row['slug'] ] = $row; }
rms_ipb_assert( 'about' === ( $by['our-company']['type'] ?? '' ), 'result type' );
rms_ipb_assert( 'about' === ( $sm->get_state()['generated_pages'][1]['type'] ?? '' ), 'state type' );
$GLOBALS['_posts'][ (int) $by['our-company']['id'] ] = new WP_Post( (int) $by['our-company']['id'] );
$GLOBALS['_build_log'] = array();
$b = rms_ipb_builder(); $b->run( array( 'action' => 'start' ) ); $proc = $b->run( array( 'action' => 'process' ) );
rms_ipb_assert( 'complete' === ( $proc['status'] ?? '' ) && 1 === count( $GLOBALS['_build_log'] ), 'about processes our-company' );
echo "PASS our-company-type-about-generate-and-build\n"; ++$passed;
rms_ipb_reset(); $GLOBALS['_posts'][12] = new WP_Post( 12 );
$sm = new State_Manager(); $st = $sm->get_state();
$st['generated_pages'] = array( array( 'id' => 12, 'slug' => 'about', 'type' => 'evil', 'role' => '' ) ); $sm->save_state( $st );
$coerced = rms_ipb_builder()->run( array( 'action' => 'process' ) );
rms_ipb_assert( array() === $GLOBALS['_build_log'] && ( 'unavailable' === ( $coerced['reason'] ?? '' ) || 'skipped' === ( $coerced['status'] ?? '' ) ), 'unknown type not coerced' );
echo "PASS unknown-type-not-coerced\n"; ++$passed;
rms_ipb_reset(); $GLOBALS['_posts'][12] = new WP_Post( 12 );
$sm = new State_Manager(); $st = $sm->get_state();
$st['generated_pages'] = array( array( 'id' => 12, 'slug' => 'about-us', 'role' => '' ) ); $sm->save_state( $st );
$b = rms_ipb_builder(); $b->run( array( 'action' => 'start' ) ); $legacy_alias = $b->run( array( 'action' => 'process' ) );
rms_ipb_assert( 'complete' === ( $legacy_alias['status'] ?? '' ) && 1 === count( $GLOBALS['_build_log'] ), 'legacy about-us alias' );
echo "PASS legacy-about-us-alias\n"; ++$passed;
rms_ipb_reset();
foreach ( array( 12 => 'about', 13 => 'services', 14 => 'contact', 15 => 'projects', 16 => 'testimonials' ) as $id => $type ) {
	$GLOBALS['_posts'][ $id ] = new WP_Post( $id );
}
$sm = new State_Manager(); $st = $sm->get_state();
$st['generated_pages'] = array(
	array( 'id' => 12, 'slug' => 'about', 'type' => 'about' ),
	array( 'id' => 13, 'slug' => 'what-we-do', 'type' => 'services' ),
	array( 'id' => 14, 'slug' => 'contact', 'type' => 'contact' ),
	array( 'id' => 15, 'slug' => 'our-work', 'type' => 'projects' ),
	array( 'id' => 16, 'slug' => 'reviews', 'type' => 'testimonials' ),
);
$sm->save_state( $st );
$b = rms_ipb_builder(); $b->run( array( 'action' => 'start' ) );
$got = array();
for ( $i = 0; $i < 5; $i++ ) {
	$r = $b->run( array( 'action' => 'process' ) );
	$got[ (string) ( $r['processed'] ?? '' ) ] = $r;
}
rms_ipb_assert( array( 'about', 'services', 'contact', 'projects', 'testimonials' ) === array_keys( $got ), 'five types processed' );
$by_id = array();
foreach ( $GLOBALS['_build_log'] as $call ) { $by_id[ (int) ( $call['id'] ?? 0 ) ] = $call; }
rms_ipb_assert( 'pages/services.php' === ( $by_id[13]['meta_input']['_wp_page_template'] ?? '' ), 'services custom slug' );
rms_ipb_assert( array( 'services-v1', 'cta-v2' ) === array_column( $by_id[13]['sections'] ?? array(), 'acf_fc_layout' ), 'services layouts' );
rms_ipb_assert( 'pages/contact-us.php' === ( $by_id[14]['meta_input']['_wp_page_template'] ?? '' ), 'contact template' );
rms_ipb_assert( array( 'gallery-grid' ) === array_column( $by_id[15]['sections'] ?? array(), 'acf_fc_layout' ), 'projects layouts' );
rms_ipb_assert( 'pages/testimonials.php' === ( $by_id[16]['meta_input']['_wp_page_template'] ?? '' ), 'testimonials template' );
rms_ipb_assert( array( 'testimonials-v1' ) === array_column( $by_id[16]['sections'] ?? array(), 'acf_fc_layout' ), 'testimonials layouts' );
$tick = $b->run( array( 'action' => 'process' ) );
rms_ipb_assert( 'complete' === ( $tick['status'] ?? '' ) && empty( $tick['unavailable'] ) && array() === array_column( array_slice( $GLOBALS['_build_log'], 5 ), 'id' ), 'extra process is complete no-op' );
echo "PASS remaining-ready-types-and-custom-slugs\n"; ++$passed;
rms_ipb_reset();
$GLOBALS['_posts'][13] = new WP_Post( 13 );
$sm = new State_Manager(); $st = $sm->get_state();
$st['generated_pages'] = array( array( 'id' => 13, 'slug' => 'services', 'type' => 'services' ) ); $sm->save_state( $st );
$b = rms_ipb_builder(); $b->run( array( 'action' => 'start' ) ); $b->run( array( 'action' => 'process' ) );
$st = ( new State_Manager() )->get_state();
rms_ipb_assert( 'skipped' === ( $st['internal_pages']['testimonials']['status'] ?? '' ) && 'unavailable' === ( $st['internal_pages']['testimonials']['reason'] ?? '' ), 'unselected testimonials skipped' );
$GLOBALS['_build_log'] = array();
$noop = $b->run( array( 'action' => 'process' ) );
rms_ipb_assert( array() === $GLOBALS['_build_log'] && 'complete' === ( $noop['status'] ?? '' ), 'complete is no-op' );
$over = $b->run( array( 'action' => 'process', 'overwrite' => array( 'services' ) ) );
rms_ipb_assert( 1 === count( $GLOBALS['_build_log'] ) && 'complete' === ( $over['status'] ?? '' ), 'overwrite services' );
echo "PASS missing-shell-preserve-overwrite-remaining\n"; ++$passed;
rms_ipb_reset();
$GLOBALS['_posts'][13] = new WP_Post( 13 );
$sm = new State_Manager(); $st = $sm->get_state();
$st['generated_pages'] = array( array( 'id' => 13, 'slug' => 'services', 'type' => 'services' ) );
$st['client_data'] = array( 'company_name' => 'Acme', 'company_services' => array( array( 'service_name' => 'Roofing', 'service_short_description' => 'We roof.' ) ) );
$sm->save_state( $st );
$b = rms_ipb_builder(); $b->run( array( 'action' => 'start' ) ); $b->run( array( 'action' => 'process' ) );
$prov = ( new Placeholder_Provenance_Store() )->query( 13 );
rms_ipb_assert( ! isset( $prov['0:services_v1_services'] ), 'company services not placeholder' );
rms_ipb_assert( isset( $prov['0:services_v1_headline'] ), 'placeholder headline recorded' );
rms_ipb_reset();
$GLOBALS['_posts'][12] = new WP_Post( 12 );
( new Canonical_Section_Store() )->set_if_empty( 'about-us', array( 'acf_fc_layout' => 'about-us', 'about_headline' => 'Canonical About' ) );
$sm = new State_Manager(); $st = $sm->get_state();
$st['generated_pages'] = array( array( 'id' => 12, 'slug' => 'about', 'type' => 'about' ) ); $sm->save_state( $st );
$b = rms_ipb_builder(); $b->run( array( 'action' => 'start' ) ); $b->run( array( 'action' => 'process' ) );
$about_prov = ( new Placeholder_Provenance_Store() )->query( 12 );
rms_ipb_assert( ! isset( $about_prov['0:about_headline'] ), 'canonical row not recorded' );
rms_ipb_assert( isset( $about_prov['1:vm_v2_headline'] ), 'non-canonical placeholder recorded' );
echo "PASS real-facts-not-recorded-as-placeholders\n"; ++$passed;
rms_ipb_reset(); rms_ipb_seed_about();
$b = rms_ipb_builder(); $b->run( array( 'action' => 'start' ) );
$bad_type = $b->run( array( 'action' => 'process', 'page_type' => 'services' ) );
rms_ipb_assert( is_wp_error( $bad_type ) && 'rms_wizard_internal_identity' === $bad_type->code && array() === $GLOBALS['_build_log'], 'forged type' );
$bad_id = $b->run( array( 'action' => 'process', 'post_id' => 99 ) );
rms_ipb_assert( is_wp_error( $bad_id ) && array() === $GLOBALS['_build_log'], 'forged post id' );
$bad_slug = $b->run( array( 'action' => 'process', 'slug' => 'home' ) );
rms_ipb_assert( is_wp_error( $bad_slug ) && array() === $GLOBALS['_build_log'], 'forged slug' );
$matched = $b->run( array( 'action' => 'process', 'page_type' => 'about', 'post_id' => 12, 'slug' => 'about' ) );
rms_ipb_assert( ! is_wp_error( $matched ) && 'complete' === ( $matched['status'] ?? '' ), 'matching identity' );
echo "PASS forged-identity-rejected\n"; ++$passed;
rms_ipb_reset();
$GLOBALS['_posts'][12] = new WP_Post( 12 ); $GLOBALS['_posts'][13] = new WP_Post( 13 );
$sm = new State_Manager(); $st = $sm->get_state();
$st['generated_pages'] = array(
	array( 'id' => 12, 'slug' => 'about', 'type' => 'about' ),
	array( 'id' => 13, 'slug' => 'services', 'type' => 'services' ),
);
$sm->save_state( $st );
$GLOBALS['_fail_build'] = true;
$b = rms_ipb_builder(); $b->run( array( 'action' => 'start' ) ); $first = $b->run( array( 'action' => 'process' ) );
rms_ipb_assert( 'failed' === ( $first['status'] ?? '' ) && 'about' === ( $first['processed'] ?? '' ), 'about failed first' );
$GLOBALS['_fail_build'] = false; $GLOBALS['_build_log'] = array();
$second = $b->run( array( 'action' => 'process' ) );
rms_ipb_assert( 'services' === ( $second['processed'] ?? '' ) && 'complete' === ( $second['status'] ?? '' ), 'failed page does not block next' );
$plan = ( new State_Manager() )->get_state()['internal_pages'];
rms_ipb_assert( 'failed' === ( $plan['about']['status'] ?? '' ) && 'complete' === ( $plan['services']['status'] ?? '' ), 'about stays failed' );
$step = (string) ( ( new State_Manager() )->get_state()['step_status']['internal-page-builder'] ?? '' );
rms_ipb_assert( 'failed' === $step, 'failed about plus complete services does not complete the step' );
$stuck = $b->run( array( 'action' => 'start' ) );
rms_ipb_assert( 'failed' === ( $stuck['status'] ?? '' ) && 'failed' === ( ( new State_Manager() )->get_state()['step_status']['internal-page-builder'] ?? '' ), 'start with only failures is not complete' );
$retry_start = $b->run( array( 'action' => 'start', 'retry_failed' => true ) );
rms_ipb_assert( 'running' === ( $retry_start['status'] ?? '' ), 'retry start is running' );
$final = $b->run( array( 'action' => 'process', 'retry_failed' => true ) );
rms_ipb_assert( 'complete' === ( $final['status'] ?? '' ) && 'complete' === ( ( new State_Manager() )->get_state()['step_status']['internal-page-builder'] ?? '' ), 'final retry completes the step' );
echo "PASS failed-page-does-not-block-next\n"; ++$passed;
rms_ipb_reset(); rms_ipb_seed_about();
$GLOBALS['_posts'][12]->post_name = 'our-story';
$b = rms_ipb_builder(); $b->run( array( 'action' => 'start' ) );
$stale = $b->run( array( 'action' => 'process', 'slug' => 'about' ) );
rms_ipb_assert( is_wp_error( $stale ) && array() === $GLOBALS['_build_log'], 'stale stored slug rejected' );
$absent = $b->run( array( 'action' => 'process' ) );
rms_ipb_assert( ! is_wp_error( $absent ) && 'complete' === ( $absent['status'] ?? '' ), 'absent slug is valid against live identity' );
rms_ipb_reset(); rms_ipb_seed_about();
$GLOBALS['_posts'][12]->post_name = 'our-story';
$b = rms_ipb_builder(); $b->run( array( 'action' => 'start' ) );
$live = $b->run( array( 'action' => 'process', 'page_type' => 'about', 'post_id' => 12, 'slug' => 'our-story' ) );
rms_ipb_assert( ! is_wp_error( $live ) && 'complete' === ( $live['status'] ?? '' ) && 1 === count( $GLOBALS['_build_log'] ), 'live permalink slug accepted' );
echo "PASS live-permalink-identity\n"; ++$passed;
rms_ipb_reset();
foreach ( array( 12 => 'about', 13 => 'services', 14 => 'contact', 15 => 'projects' ) as $id => $type ) {
	$GLOBALS['_posts'][ $id ] = new WP_Post( $id );
}
$sm = new State_Manager(); $st = $sm->get_state();
$st['generated_pages'] = array(
	array( 'id' => 12, 'slug' => 'about', 'type' => 'about' ),
	array( 'id' => 13, 'slug' => 'services', 'type' => 'services' ),
	array( 'id' => 14, 'slug' => 'contact', 'type' => 'contact' ),
	array( 'id' => 15, 'slug' => 'projects', 'type' => 'projects' ),
);
$sm->save_state( $st );
$b = rms_ipb_builder();
$b->run( array( 'action' => 'start' ) );
$b->run( array( 'action' => 'process' ) );
$b->run( array( 'action' => 'process' ) );
$writes_after_two = count( $GLOBALS['_build_log'] );
$plan = ( new State_Manager() )->get_state()['internal_pages'];
rms_ipb_assert( 'complete' === ( $plan['about']['status'] ?? '' ) && 'complete' === ( $plan['services']['status'] ?? '' ), 'two pages complete before interrupt' );
$b2 = rms_ipb_builder();
$b2->run( array( 'action' => 'start' ) );
$b2->run( array( 'action' => 'process' ) );
$b2->run( array( 'action' => 'process' ) );
$plan = ( new State_Manager() )->get_state()['internal_pages'];
rms_ipb_assert( 'complete' === ( $plan['contact']['status'] ?? '' ) && 'complete' === ( $plan['projects']['status'] ?? '' ), 'remaining pages processed on resume' );
rms_ipb_assert( 2 === count( array_slice( $GLOBALS['_build_log'], $writes_after_two ) ), 'completed pages were not rebuilt after interrupt' );
echo "PASS run-resumes-after-interruption\n"; ++$passed;
rms_ipb_reset();
$GLOBALS['_posts'][15] = new WP_Post( 15 );
$sm = new State_Manager(); $st = $sm->get_state();
$st['generated_pages'] = array( array( 'id' => 15, 'slug' => 'projects', 'type' => 'projects' ) );
$sm->save_state( $st );
rms_ipb_assert( false === ( new Canonical_Section_Store() )->has( 'gallery-grid' ), 'canonical empty before first write' );
$b = rms_ipb_builder(); $b->run( array( 'action' => 'start' ) ); $b->run( array( 'action' => 'process' ) );
rms_ipb_assert( true === ( new Canonical_Section_Store() )->has( 'gallery-grid' ), 'empty canonical first-written' );
echo "PASS empty-canonical-first-write\n"; ++$passed;
rms_ipb_reset();
$GLOBALS['_posts'][12] = new WP_Post( 12 );
$c = new Canonical_Section_Store();
$c->set_if_empty( 'about-us', array( 'acf_fc_layout' => 'about-us', 'about_headline' => 'Keep Canonical' ) );
$before = $c->get( 'about-us' );
$sm = new State_Manager(); $st = $sm->get_state();
$st['generated_pages'] = array( array( 'id' => 12, 'slug' => 'about', 'type' => 'about' ) );
$st['internal_pages']['about'] = array_merge( State_Manager::INTERNAL_PAGE_ENTRY, array( 'post_id' => 12, 'status' => 'complete' ) );
$sm->save_state( $st );
$GLOBALS['_post_meta'][12]['page_sections'] = array( array( 'acf_fc_layout' => 'about-us', 'about_headline' => 'Old page' ) );
$over = rms_ipb_builder()->run( array( 'action' => 'process', 'overwrite' => array( 'about' ) ) );
rms_ipb_assert( 'complete' === ( $over['status'] ?? '' ) && 1 === count( $GLOBALS['_build_log'] ), 'confirmed overwrite regenerates page' );
rms_ipb_assert( $before === ( new Canonical_Section_Store() )->get( 'about-us' ), 'canonical unchanged after overwrite' );
echo "PASS confirmed-overwrite-canonical-unchanged\n"; ++$passed;
rms_ipb_reset();
$GLOBALS['_posts'][18] = new WP_Post( 18 );
$GLOBALS['_posts_count'] = 2;
if ( ! function_exists( 'wp_count_posts' ) ) {
	function wp_count_posts( $type = 'post' ) {
		unset( $type );
		$n = (int) ( $GLOBALS['_posts_count'] ?? 0 );
		return (object) array( 'publish' => $n );
	}
}
$sm = new State_Manager(); $st = $sm->get_state();
$st['generated_pages'] = array( array( 'id' => 18, 'slug' => 'blog', 'type' => 'blog', 'role' => 'blog' ) );
$sm->save_state( $st );
$before_posts = (int) wp_count_posts( 'post' )->publish;
$b = rms_ipb_builder(); $b->run( array( 'action' => 'start' ) ); $b->run( array( 'action' => 'process' ) );
rms_ipb_assert( $before_posts === (int) wp_count_posts( 'post' )->publish, 'blog blueprint does not generate posts' );
echo "PASS blog-post-count-invariance\n"; ++$passed;
rms_ipb_reset();
$store = new Placeholder_Provenance_Store();
$store->record( 12, 'about-us', 0, 'about_headline', 'missing_client_fact', 'PLACEHOLDER HEADLINE' );
$harness = new AI_Content_Harness();
$ctx = $harness->compose_factual_context(
	array( 'company_name' => 'Acme' ),
	array( 'about_headline' => 'PLACEHOLDER HEADLINE', 'invented_stat' => '12 years' )
);
rms_ipb_assert( 'Acme' === ( $ctx['company_name'] ?? '' ), 'client facts remain' );
rms_ipb_assert( ! isset( $ctx['about_headline'] ), 'placeholder excluded from later factual context' );
echo "PASS placeholder-not-reused-as-factual-context\n"; ++$passed;
rms_ipb_reset();
$GLOBALS['_posts'][50] = new WP_Post( 50 );
$GLOBALS['_posts'][50]->post_name = 'about';
$l = new Logger(); $sm = new State_Manager();
$GLOBALS['_page_by_path'] = array( 'about' => $GLOBALS['_posts'][50] );
$gen = new Step_Generate_Pages( $l, $sm, new class( $l, $sm ) extends Content_Builder {
	public function build_page( array $page ): int {
		$id = absint( $page['id'] ?? 0 );
		if ( $id <= 0 ) {
			$GLOBALS['_next_id'] = ( $GLOBALS['_next_id'] ?? 80 ) + 1;
			$id = $GLOBALS['_next_id'];
		}
		$GLOBALS['_build_log'][] = $page;
		if ( isset( $page['meta_input']['_wp_page_template'] ) ) {
			$GLOBALS['_post_meta'][ $id ]['_wp_page_template'] = $page['meta_input']['_wp_page_template'];
		}
		return $id;
	}
} );
$out = $gen->run( array(
	'pages' => array(
		'home'  => array( 'type' => 'home', 'slug' => 'home', 'title' => 'Home', 'role' => 'home', 'generate' => true ),
		'about' => array( 'type' => 'about', 'slug' => 'about', 'title' => 'About', 'generate' => true ),
	),
	'confirm_cleanup' => true,
) );
rms_ipb_assert( ! is_wp_error( $out ), 'generate existing run' );
$by = array();
foreach ( $out['generated_pages'] as $row ) {
	$by[ (string) $row['slug'] ] = (int) $row['id'];
}
rms_ipb_assert( 50 === (int) ( $by['about'] ?? 0 ), 'existing blueprinted page updated in place' );
rms_ipb_assert( 2 === count( $GLOBALS['_build_log'] ), 'home created and about updated without extras' );
rms_ipb_assert( 'pages/about-us.php' === ( $GLOBALS['_post_meta'][50]['_wp_page_template'] ?? '' ), 'blueprint template applied on update' );
echo "PASS existing-blueprinted-page-update-no-duplicate\n"; ++$passed;
echo 'Harness passed: ' . $passed . " scenarios.\n";
