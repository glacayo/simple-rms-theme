<?php
/**
 * Wizard navigation menu builder service.
 *
 * @package Simple_RMS_Theme
 */

namespace Inc\Wizard;

defined( 'ABSPATH' ) || exit;

/**
 * Wraps WordPress menu APIs for wizard-generated menus.
 */
class Menu_Builder {
	private $logger;

	public function __construct( ?Logger $logger = null ) {
		$this->logger = $logger ?? new Logger();
	}

	/**
	 * Create a menu or return the existing menu ID for the provided name.
	 */
	public function ensure_menu( string $name ): int {
		$name = \sanitize_text_field( $name );

		if ( '' === $name ) {
			return 0;
		}

		$existing = \wp_get_nav_menu_object( $name );

		if ( $existing && ! \is_wp_error( $existing ) ) {
			return (int) $existing->term_id;
		}

		$menu_id = \wp_create_nav_menu( $name );

		if ( \is_wp_error( $menu_id ) ) {
			$this->logger->log( 'error', 'Wizard menu creation failed.', [ 'name' => $name, 'error' => $menu_id->get_error_message() ] );

			return 0;
		}

		$this->logger->log( 'info', 'Wizard menu created.', [ 'name' => $name, 'menu_id' => (int) $menu_id ] );

		return (int) $menu_id;
	}

	/**
	 * Replace all menu items in a menu with page links.
	 *
	 * @param int        $menu_id  Menu term ID.
	 * @param array<int> $page_ids Page post IDs in display order.
	 *
	 * @return array<int,int> Created menu item IDs.
	 */
	public function replace_menu_items( int $menu_id, array $page_ids ): array {
		$this->delete_menu_items( $menu_id );

		$item_ids = [];
		$position = 1;

		foreach ( $page_ids as $page_id ) {
			$page_id = \absint( $page_id );

			if ( $page_id <= 0 || 'page' !== \get_post_type( $page_id ) ) {
				continue;
			}

			$item_id = \wp_update_nav_menu_item(
				$menu_id,
				0,
				[
					'menu-item-object-id' => $page_id,
					'menu-item-object'    => 'page',
					'menu-item-type'      => 'post_type',
					'menu-item-status'    => 'publish',
					'menu-item-position'  => $position,
				]
			);

			if ( \is_wp_error( $item_id ) ) {
				$this->logger->log( 'error', 'Wizard menu item creation failed.', [ 'menu_id' => $menu_id, 'page_id' => $page_id, 'error' => $item_id->get_error_message() ] );
				continue;
			}

			$item_ids[] = (int) $item_id;
			$position++;
		}

		$this->logger->log( 'info', 'Wizard menu items replaced.', [ 'menu_id' => $menu_id, 'item_count' => count( $item_ids ) ] );

		return $item_ids;
	}

	/**
	 * Assign a menu to one theme location.
	 */
	public function assign_location( string $location, int $menu_id ): bool {
		$location = \sanitize_key( $location );
		$menu_id  = \absint( $menu_id );

		if ( '' === $location || $menu_id <= 0 ) {
			return false;
		}

		$locations = \get_theme_mod( 'nav_menu_locations', [] );
		$locations = is_array( $locations ) ? $locations : [];
		$locations[ $location ] = $menu_id;

		\set_theme_mod( 'nav_menu_locations', $locations );
		$this->logger->log( 'info', 'Wizard menu location assigned.', [ 'location' => $location, 'menu_id' => $menu_id ] );

		return true;
	}

	/**
	 * Clear every nav menu location assignment.
	 */
	public function clear_menu_locations(): void {
		\set_theme_mod( 'nav_menu_locations', [] );
		$this->logger->log( 'info', 'Wizard menu location assignments cleared.' );
	}

	/**
	 * Delete all existing WordPress nav menus.
	 *
	 * @return array<int,int> Deleted menu IDs.
	 */
	public function delete_all_menus(): array {
		$deleted = [];

		foreach ( \wp_get_nav_menus() as $menu ) {
			$menu_id = \absint( $menu->term_id ?? 0 );

			if ( $menu_id <= 0 ) {
				continue;
			}

			$result = \wp_delete_nav_menu( $menu_id );

			if ( \is_wp_error( $result ) ) {
				$this->logger->log( 'error', 'Wizard menu deletion failed.', [ 'menu_id' => $menu_id, 'error' => $result->get_error_message() ] );
				continue;
			}

			$deleted[] = $menu_id;
		}

		$this->logger->log( 'info', 'Wizard menus deleted.', [ 'menu_ids' => $deleted ] );

		return $deleted;
	}

	/**
	 * Append page links to a menu without duplicating existing page items.
	 *
	 * Best-effort for SEO eligibility paths: failures are reported in the
	 * structured result (and logged) so callers can warn without fail-closing.
	 * Ads/ineligible removal remains fail-closed via `remove_page_items()`.
	 *
	 * @param int        $menu_id  Menu term ID.
	 * @param array<int> $page_ids Page post IDs to ensure are present.
	 *
	 * @return array{
	 *   created: array<int,int>,
	 *   already_present: array<int,int>,
	 *   failed_page_ids: array<int,int>,
	 *   verified: bool
	 * }
	 */
	public function append_page_items( int $menu_id, array $page_ids ): array {
		$menu_id = \absint( $menu_id );
		$empty   = [
			'created'          => [],
			'already_present'  => [],
			'failed_page_ids'  => [],
			'verified'         => true,
		];

		if ( $menu_id <= 0 ) {
			return $empty;
		}

		$existing_page_ids = $this->menu_page_ids( $menu_id );
		$created           = [];
		$already_present   = [];
		$create_failed     = [];
		$requested         = [];
		$position          = count( $existing_page_ids ) + 1;

		foreach ( $page_ids as $page_id ) {
			$page_id = \absint( $page_id );

			if ( $page_id <= 0 || 'page' !== \get_post_type( $page_id ) ) {
				continue;
			}

			$requested[] = $page_id;

			if ( in_array( $page_id, $existing_page_ids, true ) ) {
				$already_present[] = $page_id;
				continue;
			}

			$item_id = \wp_update_nav_menu_item(
				$menu_id,
				0,
				[
					'menu-item-object-id' => $page_id,
					'menu-item-object'    => 'page',
					'menu-item-type'      => 'post_type',
					'menu-item-status'    => 'publish',
					'menu-item-position'  => $position,
				]
			);

			if ( \is_wp_error( $item_id ) ) {
				$create_failed[] = $page_id;
				$this->logger->log(
					'error',
					'Wizard menu item append failed.',
					[
						'menu_id'       => $menu_id,
						'page_id'       => $page_id,
						'error_code'    => $item_id->get_error_code(),
						'error_message' => $item_id->get_error_message(),
					]
				);
				continue;
			}

			$created[]           = (int) $item_id;
			$existing_page_ids[] = $page_id;
			$position++;
		}

		// Bust object cache so read-back reflects freshly written items.
		if ( function_exists( 'clean_term_cache' ) ) {
			\clean_term_cache( $menu_id, 'nav_menu' );
		}

		$present_after = $this->menu_page_ids( $menu_id );
		$failed        = [];

		foreach ( array_values( array_unique( $requested ) ) as $page_id ) {
			if ( ! in_array( $page_id, $present_after, true ) ) {
				$failed[] = $page_id;
			}
		}

		// Prefer create-time failures when read-back also missed them.
		$failed = array_values( array_unique( array_merge( $create_failed, $failed ) ) );
		$ok     = [] === $failed;

		if ( [] !== $created ) {
			$this->logger->log(
				'info',
				'Wizard menu items appended.',
				[
					'menu_id'    => $menu_id,
					'item_count' => count( $created ),
					'created'    => $created,
				]
			);
		}

		if ( ! $ok ) {
			$this->logger->log(
				'warning',
				'Wizard menu item append incomplete after verification.',
				[
					'menu_id'          => $menu_id,
					'failed_page_ids'  => $failed,
					'already_present'  => array_values( array_unique( $already_present ) ),
					'created'          => $created,
					'requested'        => array_values( array_unique( $requested ) ),
				]
			);
		}

		return [
			'created'         => $created,
			'already_present' => array_values( array_unique( $already_present ) ),
			'failed_page_ids' => $failed,
			'verified'        => $ok,
		];
	}

	/**
	 * Remove nav items that point at the given page IDs.
	 *
	 * Verifies via read-back that target page IDs are no longer present.
	 *
	 * @param int        $menu_id  Menu term ID.
	 * @param array<int> $page_ids Page post IDs to remove from the menu.
	 *
	 * @return array{deleted:array<int,int>,failed_page_ids:array<int,int>,verified:bool}
	 */
	public function remove_page_items( int $menu_id, array $page_ids ): array {
		$menu_id  = \absint( $menu_id );
		$page_ids = array_values(
			array_unique(
				array_filter(
					array_map( 'absint', $page_ids )
				)
			)
		);

		$empty = [
			'deleted'          => [],
			'failed_page_ids'  => [],
			'verified'         => true,
		];

		if ( $menu_id <= 0 || [] === $page_ids ) {
			return $empty;
		}

		$items = \wp_get_nav_menu_items( $menu_id );

		if ( empty( $items ) || ! is_array( $items ) ) {
			// Nothing to remove — already verified absent.
			return $empty;
		}

		$deleted = [];

		foreach ( $items as $item ) {
			$object_id = (int) ( $item->object_id ?? 0 );
			$type      = (string) ( $item->type ?? '' );
			$object    = (string) ( $item->object ?? '' );

			if ( 'post_type' !== $type || 'page' !== $object ) {
				continue;
			}

			if ( ! in_array( $object_id, $page_ids, true ) ) {
				continue;
			}

			$item_id = (int) ( $item->ID ?? 0 );

			if ( $item_id <= 0 ) {
				continue;
			}

			$result = \wp_delete_post( $item_id, true );

			if ( $result ) {
				$deleted[] = $item_id;
			} else {
				$this->logger->log(
					'error',
					'Wizard menu item delete failed.',
					[
						'menu_id'  => $menu_id,
						'item_id'  => $item_id,
						'page_id'  => $object_id,
					]
				);
			}
		}

		// Bust nav menu item cache so read-back is not stale.
		\wp_cache_delete( $menu_id, 'nav_menu_items' );
		if ( function_exists( 'clean_term_cache' ) ) {
			\clean_term_cache( $menu_id, 'nav_menu' );
		}

		$still_present = array_values(
			array_intersect( $this->menu_page_ids( $menu_id ), $page_ids )
		);
		$verified      = [] === $still_present;

		if ( [] !== $deleted ) {
			$this->logger->log(
				'info',
				'Wizard menu items removed for pages.',
				[
					'menu_id'    => $menu_id,
					'page_ids'   => $page_ids,
					'item_count' => count( $deleted ),
					'verified'   => $verified,
				]
			);
		}

		if ( ! $verified ) {
			$this->logger->log(
				'error',
				'Wizard menu page removal failed read-back verification.',
				[
					'menu_id'         => $menu_id,
					'page_ids'        => $page_ids,
					'failed_page_ids' => $still_present,
					'deleted_items'   => $deleted,
				]
			);
		}

		return [
			'deleted'         => $deleted,
			'failed_page_ids' => $still_present,
			'verified'        => $verified,
		];
	}

	/**
	 * Final-state landing menu reconciliation across configured menus.
	 *
	 * Eligible SEO landings are present idempotently; Ads / ineligible landings are removed.
	 * Removal is verified via read-back; failures are reported in the return payload.
	 * SEO append is best-effort: `append_failed_page_ids` is reported but does not
	 * flip `verified` (Ads removal remains fail-closed).
	 *
	 * @param array<int>                        $menu_ids       Menu term IDs to reconcile.
	 * @param array<int,array<string,mixed>>    $landing_pages  Landing state rows.
	 *
	 * @return array{
	 *   appended:array<int,int>,
	 *   removed:array<int,int>,
	 *   removal_failed_page_ids:array<int,int>,
	 *   append_failed_page_ids:array<int,int>,
	 *   verified:bool
	 * }
	 */
	public function reconcile_landing_menu_items( array $menu_ids, array $landing_pages ): array {
		$eligible_ids   = [];
		$ineligible_ids = [];

		foreach ( $landing_pages as $landing ) {
			if ( ! is_array( $landing ) ) {
				continue;
			}

			$page_id = \absint( $landing['id'] ?? 0 );

			if ( $page_id <= 0 || 'page' !== \get_post_type( $page_id ) ) {
				continue;
			}

			$type          = \sanitize_key( (string) ( $landing['landing_type'] ?? '' ) );
			$menu_eligible = array_key_exists( 'menu_eligible', $landing )
				? (bool) $landing['menu_eligible']
				: ( 'seo' === $type );

			if ( 'seo' === $type && $menu_eligible ) {
				$eligible_ids[] = $page_id;
			} else {
				$ineligible_ids[] = $page_id;
			}
		}

		$eligible_ids   = array_values( array_unique( $eligible_ids ) );
		$ineligible_ids = array_values( array_unique( $ineligible_ids ) );
		$appended       = [];
		$removed        = [];
		$failed_pages   = [];
		$append_failed  = [];

		foreach ( $menu_ids as $menu_id ) {
			$menu_id = \absint( $menu_id );

			if ( $menu_id <= 0 ) {
				continue;
			}

			if ( [] !== $ineligible_ids ) {
				$removal      = $this->remove_page_items( $menu_id, $ineligible_ids );
				$removed      = array_merge( $removed, is_array( $removal['deleted'] ?? null ) ? $removal['deleted'] : [] );
				$failed       = is_array( $removal['failed_page_ids'] ?? null ) ? $removal['failed_page_ids'] : [];
				$failed_pages = array_merge( $failed_pages, $failed );
			}

			if ( [] !== $eligible_ids ) {
				$append_result = $this->append_page_items( $menu_id, $eligible_ids );
				$created       = is_array( $append_result['created'] ?? null ) ? $append_result['created'] : [];
				$failed_append = is_array( $append_result['failed_page_ids'] ?? null ) ? $append_result['failed_page_ids'] : [];
				$appended      = array_merge( $appended, $created );
				$append_failed = array_merge( $append_failed, $failed_append );
			}
		}

		$failed_pages  = array_values( array_unique( array_map( 'absint', $failed_pages ) ) );
		$append_failed = array_values( array_unique( array_map( 'absint', $append_failed ) ) );
		// verified tracks Ads/ineligible removal only (fail-closed). SEO append is best-effort.
		$verified = [] === $failed_pages;

		if ( [] !== $append_failed ) {
			$this->logger->log(
				'warning',
				'Landing menu reconciliation SEO append incomplete (best-effort; Ads removal still authoritative).',
				[
					'menu_ids'               => array_values( array_map( 'absint', $menu_ids ) ),
					'append_failed_page_ids' => $append_failed,
					'removal_failed_page_ids'=> $failed_pages,
				]
			);
		}

		return [
			'appended'                 => $appended,
			'removed'                  => $removed,
			'removal_failed_page_ids'  => $failed_pages,
			'append_failed_page_ids'   => $append_failed,
			'verified'                 => $verified,
		];
	}

	/**
	 * Page object IDs currently linked in a menu.
	 *
	 * @return array<int,int>
	 */
	private function menu_page_ids( int $menu_id ): array {
		$items = \wp_get_nav_menu_items( $menu_id );

		if ( empty( $items ) || ! is_array( $items ) ) {
			return [];
		}

		$page_ids = [];

		foreach ( $items as $item ) {
			if ( 'post_type' !== (string) ( $item->type ?? '' ) || 'page' !== (string) ( $item->object ?? '' ) ) {
				continue;
			}

			$page_id = (int) ( $item->object_id ?? 0 );

			if ( $page_id > 0 ) {
				$page_ids[] = $page_id;
			}
		}

		return array_values( array_unique( $page_ids ) );
	}

	private function delete_menu_items( int $menu_id ): void {
		$items = \wp_get_nav_menu_items( $menu_id );

		if ( empty( $items ) || ! is_array( $items ) ) {
			return;
		}

		foreach ( $items as $item ) {
			\wp_delete_post( (int) $item->ID, true );
		}
	}
}
