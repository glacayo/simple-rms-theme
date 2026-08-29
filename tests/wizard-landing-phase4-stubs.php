<?php
/**
 * Shared WordPress runtime stubs for Wizard Landing Page Builder harnesses.
 *
 * Isolated stubs backing CLI execution for landing harnesses: WP classes,
 * global option/post store, fake WPDB with lease fences, menu stubs,
 * template stubs, and deterministic AI provider registry.
 *
 * @package Simple_RMS_Theme
 */

if ( PHP_SAPI !== 'cli' ) {
	fwrite( STDERR, "CLI only.\n" );
	exit( 1 );
}

if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', dirname( __DIR__ ) . '/' ); }
if ( ! defined( 'OBJECT' ) ) { define( 'OBJECT', 'OBJECT' ); }
if ( ! defined( 'ARRAY_A' ) ) { define( 'ARRAY_A', 'ARRAY_A' ); }
if ( ! defined( 'DAY_IN_SECONDS' ) ) { define( 'DAY_IN_SECONDS', 86400 ); }

// Core WP classes.
if ( ! class_exists( 'WP_Error', false ) ) {
	class WP_Error {
		public $code;
		public $message;
		public $data;
		public function __construct( $c = '', $m = '', $d = '' ) { $this->code = $c; $this->message = $m; $this->data = $d; }
		public function get_error_code() { return $this->code; }
		public function get_error_message() { return $this->message; }
		public function get_error_data() { return $this->data; }
		public function add_data( $data ) { $this->data = $data; }
	}
}

if ( ! class_exists( 'WP_Post', false ) ) {
	class WP_Post {
		public $ID;
		public $post_type    = 'page';
		public $post_content = '';
		public $post_name    = '';
		public $post_title   = '';
		public $post_status  = 'publish';
		public $object_id    = 0;
		public $type         = '';
		public $object       = '';
		public function __construct( $id = 0 ) { $this->ID = (int) $id; }
	}
}

if ( ! class_exists( 'WP_Query', false ) ) {
	class WP_Query {
		public $posts = array();
		public function __construct( $args = array() ) { unset( $args ); $this->posts = $GLOBALS['_wp_query_posts'] ?? array(); }
	}
}

/**
 * Atomic wp_options lease/fence fake backed by the unique option_name index.
 */
class RMS_LPB_Fake_WPDB {
	public $options = 'wp_options';
	public function prepare( $query, ...$args ) { return array( 'sql' => (string) $query, 'args' => $args ); }
	public function get_var( $query ) {
		$name = is_array( $query ) ? (string) ( $query['args'][0] ?? '' ) : '';
		if ( false !== stripos( (string) ( is_array( $query ) ? $query['sql'] : $query ), 'SELECT' ) ) {
			return $GLOBALS['_db_options'][ $name ] ?? null;
		}
		return null;
	}
	public function query( $query ) {
		$sql  = is_array( $query ) ? (string) $query['sql'] : (string) $query;
		$args = is_array( $query ) ? $query['args'] : array();
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
}

function rms_lpb_init_globals(): void {
	$GLOBALS['_options']           = array();
	$GLOBALS['_options_added']     = array();
	$GLOBALS['_posts']             = array();
	$GLOBALS['_post_meta']         = array();
	$GLOBALS['_page_by_path']      = array();
	$GLOBALS['_deleted_posts']     = array();
	$GLOBALS['_next_id']           = 100;
	$GLOBALS['_next_menu_id']      = 50;
	$GLOBALS['_next_item_id']      = 200;
	$GLOBALS['_nav_menus']         = array();
	$GLOBALS['_nav_menu_items']    = array();
	$GLOBALS['_theme_mods']        = array();
	$GLOBALS['_can']               = true;
	$GLOBALS['_user_id']           = 1;
	$GLOBALS['_ai_fail']           = false;
	$GLOBALS['_ai_prompt_log']     = array();
	$GLOBALS['_db_options']        = array();
	$GLOBALS['_wpdb_inserts']      = 0;
	$GLOBALS['_wp_query_posts']    = array();
	$GLOBALS['_actions']           = array();
	$GLOBALS['_filters']           = array();
	$GLOBALS['_wp_page_template']  = '';
	$GLOBALS['_is_page']           = false;
	$GLOBALS['_queried_object_id'] = 0;
	$GLOBALS['_wpdb']              = new RMS_LPB_Fake_WPDB();
	$GLOBALS['wpdb']               = $GLOBALS['_wpdb'];
	$GLOBALS['_now']               = time();
}

rms_lpb_init_globals();

// WP function stubs.
if ( ! function_exists( 'sanitize_key' ) ) { function sanitize_key( $k ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ) ); } }
if ( ! function_exists( 'sanitize_title' ) ) { function sanitize_title( $v ) { return strtolower( preg_replace( '/[^a-z0-9-]+/', '-', (string) $v ) ); } }
if ( ! function_exists( 'sanitize_text_field' ) ) { function sanitize_text_field( $s ) { return trim( preg_replace( '/\s+/', ' ', (string) $s ) ); } }
if ( ! function_exists( 'sanitize_textarea_field' ) ) { function sanitize_textarea_field( $s ) { return trim( (string) $s ); } }
if ( ! function_exists( 'sanitize_html_class' ) ) { function sanitize_html_class( $c ) { return trim( (string) preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $c ) ); } }
if ( ! function_exists( 'wp_unslash' ) ) { function wp_unslash( $v ) { return is_string( $v ) ? stripslashes( $v ) : $v; } }
if ( ! function_exists( 'is_plugin_active' ) ) { function is_plugin_active( $p ) { unset( $p ); return ! empty( $GLOBALS['_yoast_active'] ); } }
if ( ! function_exists( 'absint' ) ) { function absint( $v ) { return abs( (int) $v ); } }
if ( ! function_exists( '__' ) ) { function __( $t, $d = null ) { unset( $d ); return $t; } }
if ( ! function_exists( '_n' ) ) { function _n( $s, $p, $n, $d = null ) { unset( $d ); return 1 === (int) $n ? $s : $p; } }
if ( ! function_exists( 'esc_html' ) ) { function esc_html( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8' ); } }
if ( ! function_exists( 'esc_attr' ) ) { function esc_attr( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8' ); } }
if ( ! function_exists( 'esc_url' ) ) { function esc_url( $u ) { return (string) $u; } }
if ( ! function_exists( 'esc_url_raw' ) ) { function esc_url_raw( $u ) { return (string) $u; } }
if ( ! function_exists( 'current_time' ) ) { function current_time( $t, $g = false ) { unset( $t, $g ); return '2026-08-28 00:00:00'; } }
if ( ! function_exists( 'wp_json_encode' ) ) { function wp_json_encode( $d, $o = 0 ) { return json_encode( $d, $o ); } }
if ( ! function_exists( 'wp_kses_post' ) ) { function wp_kses_post( $v ) { return (string) $v; } }
if ( ! function_exists( 'is_wp_error' ) ) { function is_wp_error( $t ) { return $t instanceof WP_Error; } }
if ( ! function_exists( 'maybe_serialize' ) ) { function maybe_serialize( $v ) { return is_array( $v ) || is_object( $v ) ? serialize( $v ) : $v; } }
if ( ! function_exists( 'maybe_unserialize' ) ) {
	function maybe_unserialize( $v ) {
		if ( ! is_string( $v ) ) { return $v; }
		$d = @unserialize( $v );
		return false === $d && 'b:0;' !== $v ? $v : $d;
	}
}
if ( ! function_exists( 'wp_cache_delete' ) ) { function wp_cache_delete( $k, $g = '' ) { unset( $k, $g ); return true; } }
if ( ! function_exists( 'clean_term_cache' ) ) { function clean_term_cache( $k, $g = '' ) { unset( $k, $g ); return true; } }
if ( ! function_exists( 'error_log' ) ) { function error_log( $m ) { unset( $m ); return true; } }
if ( ! function_exists( 'wp_salt' ) ) { function wp_salt( $k = '' ) { unset( $k ); return 'test-salt'; } }
if ( ! function_exists( 'wp_generate_uuid4' ) ) { function wp_generate_uuid4() { return 'uuid-' . ( $GLOBALS['_wpdb_inserts'] + 1 ) . '-' . mt_rand( 1000, 9999 ); } }
if ( ! function_exists( 'get_template_directory_uri' ) ) { function get_template_directory_uri() { return 'https://example.test/wp-content/themes/simple-rms-theme'; } }
if ( ! function_exists( 'get_template_directory' ) ) { function get_template_directory() { return dirname( __DIR__ ); } }
if ( ! function_exists( 'trailingslashit' ) ) { function trailingslashit( $v ) { return rtrim( (string) $v, '/\\' ) . '/'; } }
if ( ! function_exists( 'get_bloginfo' ) ) { function get_bloginfo( $k = 'name' ) { unset( $k ); return 'Test Site'; } }
if ( ! function_exists( 'get_field' ) ) { function get_field( $k, $id = false ) { unset( $k, $id ); return null; } }
if ( ! function_exists( 'update_field' ) ) { function update_field( $k, $v, $id = false ) { unset( $k ); \update_post_meta( (int) $id, 'page_sections', $v ); return true; } }
if ( ! function_exists( 'apply_filters' ) ) { function apply_filters( $tag, $value, ...$args ) { unset( $args ); $GLOBALS['_filters'][] = $tag; return $value; } }
if ( ! function_exists( 'add_filter' ) ) { function add_filter( $tag, $cb, $p = 10, $a = 1 ) { $GLOBALS['_filters'][] = array( $tag, $cb, $p, $a ); return true; } }
if ( ! function_exists( 'add_action' ) ) { function add_action( $tag, $cb, $p = 10, $a = 1 ) { $GLOBALS['_actions'][] = array( $tag, $cb, $p, $a ); return true; } }
if ( ! function_exists( 'spl_autoload_register' ) ) { function spl_autoload_register( $cb ) { unset( $cb ); return true; } }

// Options stubs.
if ( ! function_exists( 'get_option' ) ) { function get_option( $n, $d = false ) { return $GLOBALS['_options'][ $n ] ?? $d; } }
if ( ! function_exists( 'update_option' ) ) { function update_option( $n, $v, $a = null ) { unset( $a ); $GLOBALS['_options'][ $n ] = $v; return true; } }
if ( ! function_exists( 'add_option' ) ) {
	function add_option( $n, $v, $d = '', $a = 'yes' ) {
		unset( $d, $a );
		if ( isset( $GLOBALS['_options'][ $n ] ) ) { return false; }
		$GLOBALS['_options'][ $n ] = $v;
		$GLOBALS['_options_added'][ $n ] = true;
		return true;
	}
}
if ( ! function_exists( 'delete_option' ) ) { function delete_option( $n ) { unset( $GLOBALS['_options'][ $n ], $GLOBALS['_options_added'][ $n ] ); return true; } }
if ( ! function_exists( 'set_transient' ) ) { function set_transient( $k, $v, $e = 0 ) { unset( $e ); $GLOBALS['_options'][ '_transient_' . $k ] = $v; return true; } }

// Post CRUD stubs.
if ( ! function_exists( 'get_post' ) ) { function get_post( $id ) { return $GLOBALS['_posts'][ (int) $id ] ?? null; } }
if ( ! function_exists( 'get_post_type' ) ) { function get_post_type( $id ) { $p = get_post( $id ); return $p ? $p->post_type : false; } }
if ( ! function_exists( 'get_post_status' ) ) { function get_post_status( $id ) { $p = get_post( $id ); return $p ? $p->post_status : false; } }
if ( ! function_exists( 'get_post_meta' ) ) { function get_post_meta( $id, $k, $s = false ) { unset( $s ); return $GLOBALS['_post_meta'][ (int) $id ][ $k ] ?? ''; } }
if ( ! function_exists( 'update_post_meta' ) ) {
	function update_post_meta( $id, $k, $v ) {
		if ( ! empty( $GLOBALS['_fail_landing_meta'] ) && in_array( $k, array( '_wp_page_template', 'rms_landing_type', '_yoast_wpseo_meta-robots-noindex' ), true ) ) {
			$GLOBALS['_post_meta'][ (int) $id ][ $k ] = 'invalid-meta';
			return true;
		}
		$GLOBALS['_post_meta'][ (int) $id ][ $k ] = $v;
		return true;
	}
}
if ( ! function_exists( 'delete_post_meta' ) ) { function delete_post_meta( $id, $k, $v = '' ) { unset( $v ); unset( $GLOBALS['_post_meta'][ (int) $id ][ $k ] ); return true; } }
if ( ! function_exists( 'get_page_by_path' ) ) { function get_page_by_path( $s, $o = null, $t = 'page' ) { unset( $o, $t ); return $GLOBALS['_page_by_path'][ (string) $s ] ?? null; } }
if ( ! function_exists( 'get_posts' ) ) { function get_posts( $a = array() ) { unset( $a ); return $GLOBALS['_all_page_ids'] ?? array(); } }
if ( ! function_exists( 'wp_insert_post' ) ) {
	function wp_insert_post( $args, $err = false ) {
		unset( $err );
		if ( ! empty( $GLOBALS['_fail_build'] ) ) { return new WP_Error( 'persist_failed', 'forced persistence failure' ); }
		$id                 = (int) ( $args['ID'] ?? 0 );
		$id                 = $id > 0 ? $id : ++$GLOBALS['_next_id'];
		$post               = new WP_Post( $id );
		$post->post_name    = (string) ( $args['post_name'] ?? '' );
		$post->post_title   = (string) ( $args['post_title'] ?? '' );
		$post->post_status  = (string) ( $args['post_status'] ?? 'publish' );
		$post->post_content = (string) ( $args['post_content'] ?? '' );
		$GLOBALS['_posts'][ $id ]                     = $post;
		$GLOBALS['_page_by_path'][ $post->post_name ] = $post;
		foreach ( is_array( $args['meta_input'] ?? null ) ? $args['meta_input'] : array() as $mk => $mv ) {
			$GLOBALS['_post_meta'][ $id ][ $mk ] = $mv;
		}
		return $id;
	}
}
if ( ! function_exists( 'wp_update_post' ) ) {
	function wp_update_post( $args, $err = false ) {
		unset( $err );
		if ( ! empty( $GLOBALS['_fail_build'] ) ) { return new WP_Error( 'persist_failed', 'forced persistence failure' ); }
		$id   = (int) ( $args['ID'] ?? 0 );
		$post = get_post( $id );
		if ( ! $post ) { return new WP_Error( 'update_failed', 'missing post' ); }
		if ( isset( $args['post_title'] ) ) { $post->post_title = (string) $args['post_title']; }
		if ( isset( $args['post_name'] ) ) {
			unset( $GLOBALS['_page_by_path'][ $post->post_name ] );
			$post->post_name = (string) $args['post_name'];
			$GLOBALS['_page_by_path'][ $post->post_name ] = $post;
		}
		if ( isset( $args['post_status'] ) ) { $post->post_status = (string) $args['post_status']; }
		foreach ( is_array( $args['meta_input'] ?? null ) ? $args['meta_input'] : array() as $mk => $mv ) {
			$GLOBALS['_post_meta'][ $id ][ $mk ] = $mv;
		}
		return $id;
	}
}
if ( ! function_exists( 'wp_delete_post' ) ) {
	function wp_delete_post( $id, $force = false ) {
		unset( $force );
		if ( ! empty( $GLOBALS['_fail_menu_item_delete'] ) && (int) $id >= 200 ) { return false; }
		$GLOBALS['_deleted_posts'][] = (int) $id;
		foreach ( $GLOBALS['_nav_menu_items'] as $index => $entry ) {
			if ( (int) ( $entry['ID'] ?? 0 ) === (int) $id ) {
				unset( $GLOBALS['_nav_menu_items'][ $index ] );
			}
		}
		$GLOBALS['_nav_menu_items'] = array_values( $GLOBALS['_nav_menu_items'] );
		return true;
	}
}
if ( ! function_exists( 'current_user_can' ) ) { function current_user_can( $c ) { unset( $c ); return ! empty( $GLOBALS['_can'] ); } }
if ( ! function_exists( 'get_current_user_id' ) ) { function get_current_user_id() { return (int) ( $GLOBALS['_user_id'] ?? 0 ); } }
if ( ! function_exists( 'wp_verify_nonce' ) ) { function wp_verify_nonce( $n, $a ) { unset( $a ); return 'valid-nonce' === $n; } }

// Nav menu stubs.
if ( ! function_exists( 'get_theme_mod' ) ) { function get_theme_mod( $k, $d = false ) { return $GLOBALS['_theme_mods'][ $k ] ?? $d; } }
if ( ! function_exists( 'set_theme_mod' ) ) { function set_theme_mod( $k, $v ) { $GLOBALS['_theme_mods'][ $k ] = $v; return true; } }
if ( ! function_exists( 'wp_get_nav_menu_object' ) ) { function wp_get_nav_menu_object( $name ) { return $GLOBALS['_nav_menus'][ (string) $name ] ?? null; } }
if ( ! function_exists( 'wp_create_nav_menu' ) ) {
	function wp_create_nav_menu( $name ) {
		if ( ! empty( $GLOBALS['_fail_menu_create'] ) ) { return new WP_Error( 'menu_create_failed', 'forced menu creation failure' ); }
		$id                                     = $GLOBALS['_next_menu_id']++;
		$menu                                   = (object) array( 'term_id' => $id, 'name' => (string) $name, 'slug' => sanitize_title( $name ) );
		$GLOBALS['_nav_menus'][ (string) $name ] = $menu;
		return $id;
	}
}
if ( ! function_exists( 'wp_get_nav_menus' ) ) { function wp_get_nav_menus() { return array_values( $GLOBALS['_nav_menus'] ); } }
if ( ! function_exists( 'wp_delete_nav_menu' ) ) {
	function wp_delete_nav_menu( $menu_id ) {
		foreach ( $GLOBALS['_nav_menus'] as $name => $menu ) {
			if ( (int) ( $menu->term_id ?? 0 ) === (int) $menu_id ) {
				unset( $GLOBALS['_nav_menus'][ $name ] );
				return true;
			}
		}
		return new WP_Error( 'menu_not_found', 'menu not found' );
	}
}
if ( ! function_exists( 'wp_update_nav_menu_item' ) ) {
	function wp_update_nav_menu_item( $menu_id, $item_id, $args ) {
		if ( ! empty( $GLOBALS['_fail_item_create'] ) ) { return new WP_Error( 'item_create_failed', 'forced item creation failure' ); }
		$id                           = $GLOBALS['_next_item_id']++;
		$GLOBALS['_nav_menu_items'][] = array_merge( array( 'menu_id' => (int) $menu_id ), $args, array( 'ID' => $id ) );
		return $id;
	}
}
if ( ! function_exists( 'wp_get_nav_menu_items' ) ) {
	function wp_get_nav_menu_items( $menu_id ) {
		$items = array();
		foreach ( $GLOBALS['_nav_menu_items'] as $entry ) {
			if ( (int) ( $entry['menu_id'] ?? 0 ) !== (int) $menu_id ) { continue; }
			$item            = new WP_Post( (int) ( $entry['ID'] ?? 0 ) );
			$item->object_id = (int) ( $entry['menu-item-object-id'] ?? 0 );
			$item->type      = (string) ( $entry['menu-item-type'] ?? '' );
			$item->object    = (string) ( $entry['menu-item-object'] ?? '' );
			$items[]         = $item;
		}
		return $items;
	}
}

// Front-end robots / template helpers.
if ( ! function_exists( 'is_page' ) ) { function is_page() { return ! empty( $GLOBALS['_is_page'] ); } }
if ( ! function_exists( 'get_queried_object_id' ) ) { function get_queried_object_id() { return (int) ( $GLOBALS['_queried_object_id'] ?? 0 ); } }
if ( ! function_exists( 'get_page_template_slug' ) ) { function get_page_template_slug( $id = 0 ) { unset( $id ); return (string) ( $GLOBALS['_wp_page_template'] ?? '' ); } }
if ( ! function_exists( 'get_header' ) ) { function get_header() { $GLOBALS['_headers'] = ( $GLOBALS['_headers'] ?? 0 ) + 1; } }
if ( ! function_exists( 'get_footer' ) ) { function get_footer() { $GLOBALS['_footers'] = ( $GLOBALS['_footers'] ?? 0 ) + 1; } }
if ( ! function_exists( 'get_the_title' ) ) { function get_the_title( $id = 0 ) { unset( $id ); return 'Landing'; } }
if ( ! function_exists( 'the_content' ) ) { function the_content() { echo 'Content'; } }
if ( ! function_exists( 'have_posts' ) ) { function have_posts() { return ! empty( $GLOBALS['_have_posts'] ); } }
if ( ! function_exists( 'the_post' ) ) { function the_post() { $GLOBALS['_have_posts'] = false; } }
if ( ! function_exists( 'post_class' ) ) { function post_class( $c = '' ) { echo 'class="' . esc_attr( (string) $c ) . '"'; } }
if ( ! function_exists( 'have_rows' ) ) {
	function have_rows( $sel, $post_id = false ) {
		unset( $post_id );
		if ( 'page_sections' !== $sel ) { return false; }
		return $GLOBALS['_loop_started'] ? $GLOBALS['_loop_index'] < count( $GLOBALS['_loop_rows'] ) : count( $GLOBALS['_loop_rows'] ) > 0;
	}
}
if ( ! function_exists( 'the_row' ) ) { function the_row() { $GLOBALS['_loop_started'] = true; $GLOBALS['_loop_index']++; return true; } }
if ( ! function_exists( 'get_row_layout' ) ) { function get_row_layout() { return (string) ( $GLOBALS['_loop_rows'][ $GLOBALS['_loop_index'] - 1 ] ?? '' ); } }
if ( ! function_exists( 'get_sub_field' ) ) { function get_sub_field( $s = null ) { unset( $s ); return null; } }
if ( ! function_exists( 'get_template_part' ) ) { function get_template_part( $slug, $name = null ) { unset( $name ); $GLOBALS['_template_parts'][] = (string) $slug; } }
if ( ! function_exists( 'locate_template' ) ) { function locate_template( $t, $l = false, $r = true ) { unset( $l, $r ); return ''; } }

// AI provider registry stubs.
if ( ! class_exists( 'Inc\Wizard\AI_Credential_Store' ) ) {
	eval( 'namespace Inc\Wizard; class AI_Credential_Store { public static function has($p){ return "test" === $p; } public static function save($p,$k){ return true; } public static function status($p){ return ["has_key"=>true,"status"=>"saved"]; } public static function mask_status($p){ return "saved"; } public static function normalize_api_key($k){ return (string) $k; } }' );
}

if ( ! class_exists( 'Inc\Wizard\AI_Provider' ) ) {
	eval( 'namespace Inc\Wizard; class AI_Provider { protected $api_key; public function __construct(string $api_key = "") { $this->api_key = $api_key; } public function get_api_key(): string { return $this->api_key; } }' );
}

if ( ! class_exists( 'Inc\Wizard\AI_Provider_Registry' ) ) {
	eval(
		'namespace Inc\Wizard; class AI_Provider_Registry { ' .
		'public static function make_provider($p,$k=""){ return new class extends AI_Provider { ' .
		'public function generate($model, $prompt, $context = [], $system = "") { $GLOBALS["_ai_prompt_log"][] = ["model"=>$model,"prompt"=>$prompt,"context"=>$context,"system"=>$system]; if ( ! empty( $GLOBALS["_ai_fail"] ) ) { return ["success"=>false,"content"=>"","error"=>"HTTP 500 provider error"]; } return ["success"=>true,"content"=>json_encode(["hero_title"=>"Hero Headline","hero_description"=>"Hero body copy for the landing hero.","seo_headline"=>"SEO Headline","seo_subheadline"=>"SEO subheadline","seo_text"=>"SEO body copy.","headline"=>"Section Headline","subheadline"=>"Section subheadline","text"=>"Section body copy.","verdict"=>"pass","diagnoses"=>[]])]; } }; } ' .
		'public static function provider_exists($p){ return "test" === $p; } ' .
		'public static function default_provider(){ return "test"; } ' .
		'public static function get_provider_label($p){ return "Test"; } ' .
		'public static function list_providers(){ return [["slug"=>"test","label"=>"Test"]]; } }'
	);
}
