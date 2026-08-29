<?php
/**
 * Shared stubs for Wizard User-Friendly Content Flow Phase 4 harnesses.
 *
 * Mirrors the isolated-stub pattern used by wizard-internal-page-* harnesses:
 * WordPress functions are faked against $GLOBALS-backed state so step
 * services and Menu_Builder can be exercised deterministically from the CLI.
 *
 * @package Simple_RMS_Theme
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
		public function get_error_message() {
			return $this->message;
		}
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
		public function __construct( $id = 0 ) {
			$this->ID = (int) $id;
		}
	}
}

$GLOBALS['_options']        = array();
$GLOBALS['_posts']          = array();
$GLOBALS['_post_meta']      = array();
$GLOBALS['_page_by_path']   = array();
$GLOBALS['_theme_mods']     = array();
$GLOBALS['_deleted_posts']  = array();
$GLOBALS['_next_id']        = 100;
$GLOBALS['_build_log']      = array();
$GLOBALS['_nav_menus']      = array();
$GLOBALS['_nav_menu_items'] = array();
$GLOBALS['_nav_create_log'] = array();
$GLOBALS['_nav_item_log']   = array();

if ( ! function_exists( 'sanitize_key' ) ) { function sanitize_key( $k ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ) ); } }
if ( ! function_exists( 'sanitize_title' ) ) { function sanitize_title( $v ) { return strtolower( preg_replace( '/[^a-z0-9-]+/', '-', (string) $v ) ); } }
if ( ! function_exists( 'sanitize_text_field' ) ) { function sanitize_text_field( $s ) { return trim( preg_replace( '/\s+/', ' ', (string) $s ) ); } }
if ( ! function_exists( '__' ) ) { function __( $t, $d = null ) { unset( $d ); return $t; } }
if ( ! function_exists( 'absint' ) ) { function absint( $v ) { return abs( (int) $v ); } }
if ( ! function_exists( 'current_time' ) ) { function current_time( $t, $g = false ) { unset( $t, $g ); return '2026-08-28 00:00:00'; } }
if ( ! function_exists( 'get_option' ) ) { function get_option( $n, $d = false ) { return $GLOBALS['_options'][ $n ] ?? $d; } }
if ( ! function_exists( 'update_option' ) ) { function update_option( $n, $v, $a = null ) { unset( $a ); $GLOBALS['_options'][ $n ] = $v; return true; } }
if ( ! function_exists( 'delete_option' ) ) { function delete_option( $n ) { unset( $GLOBALS['_options'][ $n ] ); return true; } }
if ( ! function_exists( 'get_post' ) ) { function get_post( $id ) { return $GLOBALS['_posts'][ (int) $id ] ?? null; } }
if ( ! function_exists( 'get_post_type' ) ) { function get_post_type( $id ) { $p = get_post( $id ); return $p ? $p->post_type : false; } }
if ( ! function_exists( 'get_post_meta' ) ) { function get_post_meta( $id, $k, $s = false ) { unset( $s ); return $GLOBALS['_post_meta'][ (int) $id ][ $k ] ?? ''; } }
if ( ! function_exists( 'get_page_by_path' ) ) {
	function get_page_by_path( $s, $o = null, $t = 'page' ) {
		unset( $o, $t );
		return $GLOBALS['_page_by_path'][ (string) $s ] ?? null;
	}
}
if ( ! function_exists( 'get_posts' ) ) { function get_posts( $a = array() ) { unset( $a ); return $GLOBALS['_all_page_ids'] ?? array(); } }
if ( ! function_exists( 'wp_delete_post' ) ) { function wp_delete_post( $id, $force = false ) { unset( $force ); $GLOBALS['_deleted_posts'][] = (int) $id; return true; } }
if ( ! function_exists( 'is_wp_error' ) ) { function is_wp_error( $t ) { return $t instanceof WP_Error; } }
if ( ! function_exists( 'wp_kses_post' ) ) { function wp_kses_post( $v ) { return (string) $v; } }
if ( ! function_exists( 'wp_json_encode' ) ) { function wp_json_encode( $d, $o = 0 ) { return json_encode( $d, $o ); } }
if ( ! function_exists( 'get_template_directory_uri' ) ) { function get_template_directory_uri() { return 'https://example.test/wp-content/themes/simple-rms-theme'; } }
if ( ! function_exists( 'get_template_directory' ) ) { function get_template_directory() { return dirname( __DIR__ ); } }
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
if ( ! function_exists( 'clean_term_cache' ) ) { function clean_term_cache( $k, $g = '' ) { unset( $k, $g ); return true; } }
if ( ! function_exists( 'esc_html' ) ) { function esc_html( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8' ); } }
if ( ! function_exists( 'esc_attr' ) ) { function esc_attr( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8' ); } }
if ( ! function_exists( 'error_log' ) ) { function error_log( $m ) { unset( $m ); return true; } }
if ( ! function_exists( 'wp_salt' ) ) { function wp_salt( $k = '' ) { unset( $k ); return 'test-salt'; } }
if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $tag, $value, ...$args ) {
		unset( $args );
		return $value;
	}
}
if ( ! function_exists( 'get_theme_mod' ) ) { function get_theme_mod( $k, $d = false ) { return $GLOBALS['_theme_mods'][ $k ] ?? $d; } }
if ( ! function_exists( 'set_theme_mod' ) ) { function set_theme_mod( $k, $v ) { $GLOBALS['_theme_mods'][ $k ] = $v; return true; } }
if ( ! function_exists( 'wp_get_nav_menu_object' ) ) {
	function wp_get_nav_menu_object( $name ) {
		return $GLOBALS['_nav_menus'][ (string) $name ] ?? null;
	}
}
if ( ! function_exists( 'wp_create_nav_menu' ) ) {
	function wp_create_nav_menu( $name ) {
		$GLOBALS['_nav_create_log'][] = (string) $name;
		if ( ! empty( $GLOBALS['_fail_menu_create'] ) ) {
			return new WP_Error( 'menu_create_failed', 'forced menu creation failure' );
		}
		$id                       = $GLOBALS['_next_menu_id'] ?? 50;
		$GLOBALS['_next_menu_id'] = $id + 1;
		$menu                     = (object) array(
			'term_id' => $id,
			'name'    => (string) $name,
			'slug'    => sanitize_title( $name ),
		);
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
		$GLOBALS['_nav_item_log'][] = array_merge( array( 'menu_id' => (int) $menu_id ), $args );
		if ( ! empty( $GLOBALS['_fail_item_create'] ) ) {
			return new WP_Error( 'item_create_failed', 'forced item creation failure' );
		}
		$id                          = $GLOBALS['_next_item_id'] ?? 200;
		$GLOBALS['_next_item_id']    = $id + 1;
		$GLOBALS['_nav_menu_items'][] = array_merge( array( 'menu_id' => (int) $menu_id ), $args, array( 'ID' => $id ) );
		return $id;
	}
}
if ( ! function_exists( 'wp_get_nav_menu_items' ) ) {
	function wp_get_nav_menu_items( $menu_id ) {
		$items = array();
		foreach ( $GLOBALS['_nav_menu_items'] as $entry ) {
			if ( (int) ( $entry['menu_id'] ?? 0 ) !== (int) $menu_id ) {
				continue;
			}
			$item           = new WP_Post( (int) ( $entry['ID'] ?? 0 ) );
			$item->object_id = (int) ( $entry['menu-item-object-id'] ?? 0 );
			$item->type      = (string) ( $entry['menu-item-type'] ?? '' );
			$item->object    = (string) ( $entry['menu-item-object'] ?? '' );
			$items[]         = $item;
		}
		return $items;
	}
}
if ( ! defined( 'OBJECT' ) ) { define( 'OBJECT', 'OBJECT' ); }

$theme_root = dirname( __DIR__ );
foreach ( array(
	'class-logger.php',
	'class-state-manager.php',
	'class-internal-page-blueprints.php',
	'class-ai-content-harness.php',
	'class-ai-credential-store.php',
	'class-ai-provider-registry.php',
	'class-canonical-section-store.php',
	'class-yoast-meta-writer.php',
	'class-content-builder.php',
	'class-section-assembler.php',
	'class-flexible-content-layouts.php',
	'class-menu-builder.php',
	'class-step-generate-pages.php',
	'class-step-menu-setup.php',
	'class-step-home-page-builder.php',
	'class-step-controller.php',
) as $file ) {
	require_once $theme_root . '/inc/wizard/' . $file;
}

if ( ! function_exists( 'rms_wufc_assert' ) ) {
	function rms_wufc_assert( $c, string $m ): void {
		if ( ! $c ) {
			fwrite( STDERR, $m . "\n" );
			exit( 1 );
		}
	}
}

if ( ! function_exists( 'rms_wufc_reset' ) ) {
	function rms_wufc_reset(): void {
		$GLOBALS['_options']        = array();
		$GLOBALS['_posts']          = array();
		$GLOBALS['_post_meta']      = array();
		$GLOBALS['_page_by_path']   = array();
		$GLOBALS['_theme_mods']     = array();
		$GLOBALS['_deleted_posts']  = array();
		$GLOBALS['_next_id']        = 100;
		$GLOBALS['_build_log']      = array();
		$GLOBALS['_nav_menus']      = array();
		$GLOBALS['_nav_menu_items'] = array();
		$GLOBALS['_nav_create_log'] = array();
		$GLOBALS['_nav_item_log']   = array();
		$GLOBALS['_all_page_ids']   = array();
		$GLOBALS['_fail_menu_create'] = false;
		$GLOBALS['_fail_item_create'] = false;
		$GLOBALS['_next_menu_id']   = 50;
		$GLOBALS['_next_item_id']   = 200;
	}
}

if ( ! function_exists( 'rms_wufc_seed_page' ) ) {
	function rms_wufc_seed_page( int $id, string $slug, string $title = '' ): void {
		$post                    = new WP_Post( $id );
		$post->post_name         = $slug;
		$post->post_title        = '' !== $title ? $title : ucwords( str_replace( '-', ' ', $slug ) );
		$GLOBALS['_posts'][ $id ] = $post;
		$GLOBALS['_page_by_path'][ $slug ] = $post;
	}
}
