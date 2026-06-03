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
