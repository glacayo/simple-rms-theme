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
		$landing_pages   = is_array( $state['landing_pages'] ?? null ) ? $state['landing_pages'] : [];

		// Pool may come from generated_pages and/or menu-eligible SEO landings.
		// Do not fail solely because generated_pages is empty when landings exist.
		// Ads remain excluded from the pool.
		$page_pool = $this->build_page_pool( $generated_pages, $landing_pages );

		if ( [] === $page_pool ) {
			$this->state_manager->set_step_status( self::STEP, 'failed' );

			return new \WP_Error( 'rms_wizard_no_generated_pages', \__( 'No pages found. Please complete the Generate Pages step first', 'simple-rms-theme' ), [ 'status' => 400 ] );
		}

		$primary_input    = is_array( $payload['primary'] ?? null ) ? $payload['primary'] : ( is_array( $payload['primary_page_ids'] ?? null ) ? $payload['primary_page_ids'] : [] );
		$mobile_input     = is_array( $payload['mobile'] ?? null ) ? $payload['mobile'] : ( is_array( $payload['mobile_page_ids'] ?? null ) ? $payload['mobile_page_ids'] : [] );
		$primary_page_ids = $this->resolve_page_ids( $primary_input, $page_pool );
		$mobile_page_ids  = $this->resolve_page_ids( $mobile_input, $page_pool );

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

		// Mandatory safety net after destructive replace: eligible SEO present, Ads removed.
		$configured_menu_ids = array_values(
			array_unique(
				array_filter(
					[
						$primary_menu_id,
						$mobile_menu_id,
					]
				)
			)
		);
		$landing_reconcile = $this->menu_builder->reconcile_landing_menu_items( $configured_menu_ids, $landing_pages );

		// Fail-closed when Ads / ineligible landings could not be verified removed.
		if ( empty( $landing_reconcile['verified'] ) ) {
			$failed = is_array( $landing_reconcile['removal_failed_page_ids'] ?? null )
				? array_values( $landing_reconcile['removal_failed_page_ids'] )
				: [];

			$this->state_manager->set_step_status( self::STEP, 'failed' );
			$this->logger->log(
				'error',
				'Menu setup landing reconciliation failed Ads/ineligible removal verification.',
				[
					'menu_ids'                 => $configured_menu_ids,
					'removal_failed_page_ids'  => $failed,
					'landing_reconcile'        => $landing_reconcile,
				]
			);

			return new \WP_Error(
				'rms_wizard_menu_ads_removal_failed',
				\__( 'Menu setup could not verify removal of Ads or ineligible landing pages from menus.', 'simple-rms-theme' ),
				[
					'status'                  => 500,
					'removal_failed_page_ids' => $failed,
					'landing_reconcile'       => $landing_reconcile,
				]
			);
		}

		// SEO append is best-effort: surface incomplete appends without failing the step.
		$append_failed = is_array( $landing_reconcile['append_failed_page_ids'] ?? null )
			? array_values( $landing_reconcile['append_failed_page_ids'] )
			: [];

		if ( [] !== $append_failed ) {
			$this->logger->log(
				'warning',
				'Menu setup SEO landing menu append incomplete (best-effort; Ads removal verified).',
				[
					'menu_ids'               => $configured_menu_ids,
					'append_failed_page_ids' => $append_failed,
					'landing_reconcile'      => $landing_reconcile,
				]
			);
		}

		$menu_config = [
			'primary_menu_id'    => $primary_menu_id,
			'mobile_menu_id'     => $mobile_menu_id,
			'locations'          => [ 'primary' => $primary_menu_id, 'mobile' => $mobile_menu_id ],
			'deleted_menu_ids'   => $deleted_menu_ids,
			'landing_reconcile'  => $landing_reconcile,
		];

		$fresh                = $this->state_manager->get_state();
		$fresh['menu_config'] = $menu_config;
		$this->state_manager->save_state( $fresh );
		$this->state_manager->set_step_status( self::STEP, 'complete' );
		$this->logger->log( 'info', 'Wizard menus configured.', $menu_config );

		return $menu_config;
	}

	/**
	 * Build id/slug lookup from generated pages + menu-eligible SEO landings.
	 *
	 * Ads / menu_eligible=false landings are never added to the pool.
	 *
	 * @param array<int,array<string,mixed>> $generated_pages
	 * @param array<int,array<string,mixed>> $landing_pages
	 *
	 * @return array<string,int>
	 */
	private function build_page_pool( array $generated_pages, array $landing_pages ): array {
		$pool = [];

		foreach ( $generated_pages as $page ) {
			if ( ! is_array( $page ) || empty( $page['id'] ) ) {
				continue;
			}

			$id = \absint( $page['id'] );

			if ( $id <= 0 ) {
				continue;
			}

			$pool[ (string) $id ] = $id;

			if ( ! empty( $page['slug'] ) ) {
				$pool[ \sanitize_title( (string) $page['slug'] ) ] = $id;
			}
		}

		foreach ( $landing_pages as $landing ) {
			if ( ! is_array( $landing ) || empty( $landing['id'] ) ) {
				continue;
			}

			$id   = \absint( $landing['id'] );
			$type = \sanitize_key( (string) ( $landing['landing_type'] ?? '' ) );
			$eligible = array_key_exists( 'menu_eligible', $landing )
				? (bool) $landing['menu_eligible']
				: ( 'seo' === $type );

			// Ads and ineligible landings must never join the menu pool.
			if ( $id <= 0 || 'seo' !== $type || ! $eligible ) {
				continue;
			}

			if ( 'page' !== \get_post_type( $id ) ) {
				continue;
			}

			$pool[ (string) $id ] = $id;

			if ( ! empty( $landing['slug'] ) ) {
				$pool[ \sanitize_title( (string) $landing['slug'] ) ] = $id;
			}
		}

		return $pool;
	}

	/**
	 * @param array<int|string,mixed> $selected
	 * @param array<string,int>       $pool
	 *
	 * @return array<int,int>
	 */
	private function resolve_page_ids( array $selected, array $pool ): array {
		$page_ids = [];

		foreach ( $selected as $item ) {
			$key = is_numeric( $item ) ? (string) \absint( $item ) : \sanitize_title( (string) $item );

			if ( isset( $pool[ $key ] ) && ! in_array( $pool[ $key ], $page_ids, true ) ) {
				$page_ids[] = $pool[ $key ];
			}
		}

		return $page_ids;
	}

	private function truthy( $value ): bool {
		return true === $value || 1 === $value || '1' === $value || 'true' === $value || 'yes' === $value;
	}
}
