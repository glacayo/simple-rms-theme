<?php
/**
 * Shared bootstrap and helpers for Wizard Landing Page Builder Phase 4 harnesses.
 *
 * Loads isolated runtime stubs, requires wizard domain classes, and provides
 * deterministic fixtures/assertions for landing test suites.
 *
 * @package Simple_RMS_Theme
 */

if ( PHP_SAPI !== 'cli' ) {
	fwrite( STDERR, "CLI only.\n" );
	exit( 1 );
}

require_once __DIR__ . '/wizard-landing-phase4-stubs.php';

// Theme classes required by the landing change.
$rms_lpb_theme_root = dirname( __DIR__ );
foreach ( array(
	'class-logger.php',
	'class-state-manager.php',
	'class-internal-page-blueprints.php',
	'class-ai-content-harness.php',
	'class-ai-content-reviewer.php',
	'class-canonical-section-store.php',
	'class-yoast-meta-writer.php',
	'class-content-builder.php',
	'class-section-assembler.php',
	'class-flexible-content-layouts.php',
	'class-placeholder-provenance-store.php',
	'class-menu-builder.php',
	'class-wizard-mutation-fence.php',
	'class-wizard-unlock-controller.php',
	'class-landing-run-orchestrator.php',
	'class-step-generate-pages.php',
	'class-step-menu-setup.php',
	'class-step-home-page-builder.php',
	'class-step-landing-page-builder.php',
	'class-step-internal-page-builder.php',
	'class-step-controller.php',
) as $rms_file ) {
	require_once $rms_lpb_theme_root . '/inc/wizard/' . $rms_file;
}

/**
 * Fake Content_Builder that records build_page() calls deterministically.
 */
class RMS_LPB_Fake_Builder extends \Inc\Wizard\Content_Builder {
	public function build_page( array $page ): int {
		if ( ! empty( $GLOBALS['_fail_build'] ) ) {
			return 0;
		}
		$id = absint( $page['id'] ?? 0 );
		if ( $id <= 0 ) {
			$id = ++$GLOBALS['_next_id'];
		}
		$post                                         = new WP_Post( $id );
		$post->post_name                              = sanitize_title( (string) ( $page['slug'] ?? '' ) );
		$post->post_title                             = sanitize_text_field( (string) ( $page['title'] ?? '' ) );
		$post->post_status                            = (string) ( $page['status'] ?? 'publish' );
		$GLOBALS['_posts'][ $id ]                     = $post;
		$GLOBALS['_page_by_path'][ $post->post_name ] = $post;
		$GLOBALS['_build_log'][]                      = $page;
		if ( isset( $page['meta_input'] ) && is_array( $page['meta_input'] ) ) {
			foreach ( $page['meta_input'] as $mk => $mv ) {
				$GLOBALS['_post_meta'][ $id ][ $mk ] = $mv;
			}
		}
		if ( isset( $page['sections'] ) ) {
			$GLOBALS['_post_meta'][ $id ]['page_sections'] = $page['sections'];
		}
		return $id;
	}
}

if ( ! function_exists( 'rms_lpb_assert' ) ) {
	function rms_lpb_assert( $condition, string $message ): void {
		if ( ! $condition ) {
			fwrite( STDERR, $message . "\n" );
			exit( 1 );
		}
	}
}

if ( ! function_exists( 'rms_lpb_reset' ) ) {
	function rms_lpb_reset(): void {
		rms_lpb_init_globals();
		$GLOBALS['_all_page_ids']          = array();
		$GLOBALS['_fail_build']            = false;
		$GLOBALS['_fail_landing_meta']     = false;
		$GLOBALS['_fail_menu_create']      = false;
		$GLOBALS['_fail_item_create']      = false;
		$GLOBALS['_fail_menu_item_delete'] = false;
		$GLOBALS['_yoast_active']          = false;
		$GLOBALS['_headers']               = 0;
		$GLOBALS['_footers']               = 0;
		$GLOBALS['_template_parts']        = array();
		$GLOBALS['_loop_rows']             = array();
		$GLOBALS['_loop_index']            = 0;
		$GLOBALS['_loop_started']          = false;
		$GLOBALS['_have_posts']            = false;
		$GLOBALS['_build_log']             = array();
	}
}

if ( ! function_exists( 'rms_lpb_seed_page' ) ) {
	function rms_lpb_seed_page( int $id, string $slug, string $title = '' ): void {
		$post                             = new WP_Post( $id );
		$post->post_name                  = $slug;
		$post->post_title                 = '' !== $title ? $title : ucwords( str_replace( '-', ' ', $slug ) );
		$GLOBALS['_posts'][ $id ]         = $post;
		$GLOBALS['_page_by_path'][ $slug ] = $post;
	}
}

if ( ! function_exists( 'rms_lpb_menu_page_ids' ) ) {
	function rms_lpb_menu_page_ids( int $menu_id ): array {
		$ids = array();
		foreach ( \wp_get_nav_menu_items( $menu_id ) as $item ) {
			if ( 'post_type' === $item->type && 'page' === $item->object ) {
				$ids[] = (int) $item->object_id;
			}
		}
		return $ids;
	}
}

if ( ! function_exists( 'rms_lpb_seed_landing_state' ) ) {
	function rms_lpb_seed_landing_state( array $landings = array(), array $menu_ids = array() ): \Inc\Wizard\State_Manager {
		$sm                = new \Inc\Wizard\State_Manager();
		$st                = $sm->get_state();
		$st['ai_config']   = array( 'provider' => 'test', 'model' => 'test-model', 'has_credentials' => true );
		$st['client_data'] = array( 'company_name' => 'Acme Concrete' );
		if ( array() !== $landings ) {
			$st['landing_pages'] = $landings;
		}
		if ( array() !== $menu_ids ) {
			$st['menu_config'] = array( 'primary_menu_id' => (int) $menu_ids[0] );
		}
		$sm->save_state( $st );
		return $sm;
	}
}

if ( ! function_exists( 'rms_lpb_landing_payload_item' ) ) {
	function rms_lpb_landing_payload_item( string $key, string $slug, array $extra = array() ): array {
		return array_merge(
			array(
				'id'              => null,
				'landing_key'     => $key,
				'title'           => ucwords( str_replace( '-', ' ', $slug ) ),
				'slug'            => $slug,
				'landing_type'    => 'seo',
				'primary_keyword' => 'concrete repair',
				'subkeywords'     => array( 'driveway', 'patio' ),
				'sections'        => array( array( 'layout' => 'hero' ), array( 'layout' => 'seo-content' ), array( 'layout' => 'about-us' ) ),
			),
			$extra
		);
	}
}

if ( ! function_exists( 'rms_lpb_seed_configured_menu' ) ) {
	function rms_lpb_seed_configured_menu( string $name = 'Primary Menu' ): int {
		$menu_id                                      = \wp_create_nav_menu( $name );
		$GLOBALS['_theme_mods']['nav_menu_locations'] = array( 'primary' => $menu_id );
		$sm                                           = new \Inc\Wizard\State_Manager();
		$st                                           = $sm->get_state();
		$st['menu_config']                            = array( 'primary_menu_id' => $menu_id );
		$sm->save_state( $st );
		return $menu_id;
	}
}
