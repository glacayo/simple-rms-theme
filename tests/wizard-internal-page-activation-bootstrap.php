<?php
/**
 * Shared stubs for Internal Page Builder activation harnesses.
 */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}
if ( ! class_exists( 'WP_Error', false ) ) {
	class WP_Error {
		public $code;
		public $message;
		public $data;
		public function __construct( $c = '', $m = '', $d = '' ) {
			$this->code    = $c;
			$this->message = $m;
			$this->data    = $d;
		}
		public function get_error_code() {
			return $this->code;
		}
	}
}
if ( ! class_exists( 'WP_Post', false ) ) {
	class WP_Post {
		public $ID;
		public $post_type    = 'page';
		public $post_content = '';
		public $post_name    = '';
		public function __construct( $id = 0 ) {
			$this->ID = (int) $id;
		}
	}
}

$GLOBALS['_options']      = array();
$GLOBALS['_db_options']   = array();
$GLOBALS['_can']          = true;
$GLOBALS['_wpdb_inserts'] = 0;
$GLOBALS['_posts']        = array();
$GLOBALS['_post_meta']    = array();
$GLOBALS['_page_writes']  = 0;

if ( ! function_exists( 'sanitize_key' ) ) { function sanitize_key( $k ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ) ); } }
if ( ! function_exists( 'sanitize_title' ) ) { function sanitize_title( $v ) { return strtolower( preg_replace( '/[^a-z0-9-]+/', '-', (string) $v ) ); } }
if ( ! function_exists( 'sanitize_text_field' ) ) { function sanitize_text_field( $s ) { return trim( (string) $s ); } }
if ( ! function_exists( '__' ) ) { function __( $t, $d = null ) { unset( $d ); return $t; } }
if ( ! function_exists( 'absint' ) ) { function absint( $v ) { return abs( (int) $v ); } }
if ( ! function_exists( 'current_time' ) ) { function current_time( $t, $g = false ) { unset( $t, $g ); return '2026-08-27 00:00:00'; } }
if ( ! function_exists( 'get_option' ) ) { function get_option( $n, $d = false ) { return $GLOBALS['_options'][ $n ] ?? $d; } }
if ( ! function_exists( 'update_option' ) ) { function update_option( $n, $v, $a = null ) { unset( $a ); $GLOBALS['_options'][ $n ] = $v; return true; } }
if ( ! function_exists( 'delete_option' ) ) { function delete_option( $n ) { unset( $GLOBALS['_options'][ $n ] ); return true; } }
if ( ! function_exists( 'current_user_can' ) ) { function current_user_can( $c ) { unset( $c ); return ! empty( $GLOBALS['_can'] ); } }
if ( ! function_exists( 'get_current_user_id' ) ) { function get_current_user_id() { return 1; } }
if ( ! function_exists( 'is_wp_error' ) ) { function is_wp_error( $t ) { return $t instanceof WP_Error; } }
if ( ! function_exists( 'wp_json_encode' ) ) { function wp_json_encode( $d, $o = 0 ) { return json_encode( $d, $o ); } }
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
if ( ! function_exists( 'get_post' ) ) { function get_post( $id ) { return $GLOBALS['_posts'][ (int) $id ] ?? null; } }
if ( ! function_exists( 'get_post_meta' ) ) { function get_post_meta( $id, $k, $s = false ) { unset( $s ); return $GLOBALS['_post_meta'][ (int) $id ][ $k ] ?? ''; } }
if ( ! function_exists( 'wp_update_post' ) ) {
	function wp_update_post( $data, $wp_error = false ) {
		unset( $wp_error );
		$GLOBALS['_page_writes']++;
		$id = absint( $data['ID'] ?? 0 );
		return $id > 0 ? $id : new WP_Error( 'update_failed', 'missing id' );
	}
}

if ( ! isset( $GLOBALS['wpdb'] ) ) {
	$GLOBALS['wpdb'] = new class {
		public $options = 'wp_options';
		public function prepare( $q, ...$a ) {
			return array( 'sql' => (string) $q, 'args' => $a );
		}
		public function get_var( $q ) {
			$name = is_array( $q ) ? (string) ( $q['args'][0] ?? '' ) : '';
			return $GLOBALS['_db_options'][ $name ] ?? null;
		}
		public function query( $q ) {
			$sql  = is_array( $q ) ? (string) $q['sql'] : (string) $q;
			$args = is_array( $q ) ? $q['args'] : array();
			$name = (string) ( $args[0] ?? '' );
			if ( false !== stripos( $sql, 'INSERT' ) ) {
				if ( isset( $GLOBALS['_db_options'][ $name ] ) ) {
					return 0;
				}
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

$theme_root = dirname( __DIR__ );
foreach ( array(
	'class-logger.php',
	'class-state-manager.php',
	'class-wizard-mutation-fence.php',
	'class-wizard-unlock-controller.php',
	'class-step-controller.php',
	'class-internal-page-blueprints.php',
	'class-ai-content-harness.php',
	'class-canonical-section-store.php',
	'class-yoast-meta-writer.php',
	'class-content-builder.php',
	'class-section-assembler.php',
	'class-placeholder-provenance-store.php',
	'class-step-internal-page-builder.php',
) as $file ) {
	require_once $theme_root . '/inc/wizard/' . $file;
}

if ( ! function_exists( 'rms_ipa_assert' ) ) {
	function rms_ipa_assert( $c, string $m ): void {
		if ( ! $c ) {
			fwrite( STDERR, $m . "\n" );
			exit( 1 );
		}
	}
}

if ( ! function_exists( 'rms_ipa_reset' ) ) {
	function rms_ipa_reset(): void {
		$GLOBALS['_options']      = array();
		$GLOBALS['_db_options']   = array();
		$GLOBALS['_wpdb_inserts'] = 0;
		$GLOBALS['_posts']        = array();
		$GLOBALS['_post_meta']    = array();
		$GLOBALS['_page_writes']  = 0;
		$GLOBALS['_can']          = true;
	}
}

if ( ! function_exists( 'rms_ipa_seed_about' ) ) {
	function rms_ipa_seed_about(): void {
		$post             = new WP_Post( 12 );
		$post->post_name  = 'about';
		$GLOBALS['_posts'][12] = $post;
		$GLOBALS['_post_meta'][12]['page_sections'] = array(
			array(
				'acf_fc_layout'  => 'about-us',
				'about_headline' => 'Keep this copy',
			),
		);
		$sm = new \Inc\Wizard\State_Manager();
		$st = $sm->get_state();
		$st['generated_pages'] = array(
			array(
				'id'   => 12,
				'slug' => 'about',
				'type' => 'about',
			),
		);
		$st['internal_pages']['about'] = array_merge(
			\Inc\Wizard\State_Manager::INTERNAL_PAGE_ENTRY,
			array(
				'post_id' => 12,
				'status'  => 'pending',
			)
		);
		$sm->save_state( $st );
	}
}

if ( ! class_exists( 'Inc\\Wizard\\Landing_Run_Orchestrator', false ) ) {
	eval( 'namespace Inc\\Wizard; class Landing_Run_Orchestrator { public function __construct( ...$a ) {} public function get_public_run() { return null; } public function recover_stale_lease() {} }' );
}
