<?php
/**
 * Wizard menu setup step service.
 *
 * @package Simple_RMS_Theme
 */

namespace Inc\Wizard;

defined( 'ABSPATH' ) || exit;

/**
 * Replaces theme menus with menus built from wizard-generated pages.
 */
class Step_Menu_Setup {
	private const STEP = 'menu-setup';

	private $logger;
	private $state_manager;
	private $menu_builder;

	public function __construct( ?Logger $logger = null, ?State_Manager $state_manager = null, ?Menu_Builder $menu_builder = null ) {
		$this->logger        = $logger ?? new Logger();
		$this->state_manager = $state_manager ?? new State_Manager();
		$this->menu_builder  = $menu_builder ?? new Menu_Builder( $this->logger );
	}

	/**
	 * Run menu setup.
	 *
	 * @param array<string,mixed> $payload Step payload.
	 *
	 * @return array<string,mixed>|\WP_Error
	 */
	public function run( array $payload ) {
		$state           = $this->state_manager->get_state();
		$generated_pages = is_array( $state['generated_pages'] ?? null ) ? $state['generated_pages'] : [];

		if ( [] === $generated_pages ) {
			$this->state_manager->set_step_status( self::STEP, 'failed' );

			return new \WP_Error( 'rms_wizard_no_generated_pages', \__( 'No pages found. Please complete the Generate Pages step first', 'simple-rms-theme' ), [ 'status' => 400 ] );
		}

		$primary_input    = is_array( $payload['primary'] ?? null ) ? $payload['primary'] : ( is_array( $payload['primary_page_ids'] ?? null ) ? $payload['primary_page_ids'] : [] );
		$mobile_input     = is_array( $payload['mobile'] ?? null ) ? $payload['mobile'] : ( is_array( $payload['mobile_page_ids'] ?? null ) ? $payload['mobile_page_ids'] : [] );
		$primary_page_ids = $this->resolve_page_ids( $primary_input, $generated_pages );
		$mobile_page_ids  = $this->resolve_page_ids( $mobile_input, $generated_pages );

		if ( [] === $primary_page_ids ) {
			$this->state_manager->set_step_status( self::STEP, 'failed' );

			return new \WP_Error( 'rms_wizard_primary_menu_required', \__( 'Primary menu requires at least one page', 'simple-rms-theme' ), [ 'status' => 400 ] );
		}

		if ( ! $this->truthy( $payload['confirm_cleanup'] ?? false ) ) {
			$this->state_manager->set_step_status( self::STEP, 'pending' );

			return new \WP_Error( 'rms_wizard_menu_cleanup_confirmation_required', \__( 'Existing menus and location assignments will be removed and replaced. This cannot be undone.', 'simple-rms-theme' ), [ 'status' => 400 ] );
		}

		$this->menu_builder->clear_menu_locations();
		$deleted_menu_ids = $this->menu_builder->delete_all_menus();

		$primary_menu_id = $this->menu_builder->ensure_menu( \__( 'Primary Menu', 'simple-rms-theme' ) );

		if ( $primary_menu_id <= 0 ) {
			$this->state_manager->set_step_status( self::STEP, 'failed' );

			return new \WP_Error( 'rms_wizard_primary_menu_create_failed', \__( 'Primary menu could not be created.', 'simple-rms-theme' ), [ 'status' => 500 ] );
		}

		$this->menu_builder->replace_menu_items( $primary_menu_id, $primary_page_ids );
		$this->menu_builder->assign_location( 'primary', $primary_menu_id );

		$mobile_menu_id = $primary_menu_id;

		if ( [] !== $mobile_page_ids ) {
			$mobile_menu_id = $this->menu_builder->ensure_menu( \__( 'Mobile Menu', 'simple-rms-theme' ) );

			if ( $mobile_menu_id <= 0 ) {
				$this->state_manager->set_step_status( self::STEP, 'failed' );

				return new \WP_Error( 'rms_wizard_mobile_menu_create_failed', \__( 'Mobile menu could not be created.', 'simple-rms-theme' ), [ 'status' => 500 ] );
			}

			$this->menu_builder->replace_menu_items( $mobile_menu_id, $mobile_page_ids );
		}

		$this->menu_builder->assign_location( 'mobile', $mobile_menu_id );

		$menu_config = [
			'primary_menu_id'  => $primary_menu_id,
			'mobile_menu_id'   => $mobile_menu_id,
			'locations'        => [ 'primary' => $primary_menu_id, 'mobile' => $mobile_menu_id ],
			'deleted_menu_ids' => $deleted_menu_ids,
		];

		$state['menu_config'] = $menu_config;
		$this->state_manager->save_state( $state );
		$this->state_manager->set_step_status( self::STEP, 'complete' );
		$this->logger->log( 'info', 'Wizard menus configured.', $menu_config );

		return $menu_config;
	}

	private function resolve_page_ids( array $selected, array $generated_pages ): array {
		$generated = [];

		foreach ( $generated_pages as $page ) {
			if ( ! is_array( $page ) || empty( $page['id'] ) ) {
				continue;
			}

			$id = \absint( $page['id'] );

			if ( $id <= 0 ) {
				continue;
			}

			$generated[ (string) $id ] = $id;

			if ( ! empty( $page['slug'] ) ) {
				$generated[ \sanitize_title( (string) $page['slug'] ) ] = $id;
			}
		}

		$page_ids = [];

		foreach ( $selected as $item ) {
			$key = is_numeric( $item ) ? (string) \absint( $item ) : \sanitize_title( (string) $item );

			if ( isset( $generated[ $key ] ) && ! in_array( $generated[ $key ], $page_ids, true ) ) {
				$page_ids[] = $generated[ $key ];
			}
		}

		return $page_ids;
	}

	private function truthy( $value ): bool {
		return true === $value || 1 === $value || '1' === $value || 'true' === $value || 'yes' === $value;
	}
}
