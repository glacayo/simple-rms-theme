<?php
/**
 * Menu_Builder creation and deletion contracts (Phase 4 task 4.1).
 *
 * Proves the wrappers over WordPress menu APIs used by Step_Menu_Setup:
 * ensure_menu() reuses an existing menu by name, replace_menu_items()
 * creates page items in display order and skips non-pages, assign_location()
 * updates theme mods, clear_menu_locations() empties them, and
 * delete_all_menus() removes every registered menu.
 *
 * Usage: php tests/wizard-menu-builder-harness.php
 */
if ( PHP_SAPI !== 'cli' ) {
	fwrite( STDERR, "CLI only.\n" );
	exit( 1 );
}

require_once __DIR__ . '/wizard-user-friendly-content-flow-bootstrap.php';

use Inc\Wizard\Logger;
use Inc\Wizard\Menu_Builder;

$passed = 0;

// --- ensure_menu creates and reuses --------------------------------------
rms_wufc_reset();
$mb = new Menu_Builder( new Logger() );

$created = $mb->ensure_menu( 'Primary Menu' );
rms_wufc_assert( $created > 0, 'ensure_menu creates a menu and returns its ID' );
rms_wufc_assert( array( 'Primary Menu' ) === $GLOBALS['_nav_create_log'], 'menu was created with the exact name' );

$reused = $mb->ensure_menu( 'Primary Menu' );
rms_wufc_assert( $reused === $created, 'ensure_menu reuses an existing menu by name (no duplicate)' );
rms_wufc_assert( 1 === count( $GLOBALS['_nav_create_log'] ), 'no second create call for an existing name' );

$empty = $mb->ensure_menu( '   ' );
rms_wufc_assert( 0 === $empty, 'ensure_menu returns 0 for an empty name' );
echo "PASS ensure-menu-create-reuse\n";
++$passed;

// --- replace_menu_items order, filtering, and pre-clean --------------------
rms_wufc_reset();
$mb    = new Menu_Builder( new Logger() );
$menu  = $mb->ensure_menu( 'Primary Menu' );
rms_wufc_seed_page( 11, 'home', 'Home' );
rms_wufc_seed_page( 12, 'about', 'About' );
$not_page              = new WP_Post( 13 );
$not_page->post_name   = 'news-item';
$not_page->post_type   = 'post';
$GLOBALS['_posts'][13] = $not_page;
$GLOBALS['_nav_menu_items'] = array(
	array( 'menu_id' => $menu, 'ID' => 300, 'menu-item-object-id' => 99, 'menu-item-type' => 'post_type', 'menu-item-object' => 'page' ),
);

$items = $mb->replace_menu_items( $menu, array( 11, 13, 12 ) );
rms_wufc_assert( array( 11, 12 ) === array_map( 'intval', array_column( $GLOBALS['_nav_item_log'], 'menu-item-object-id' ) ), 'items created in display order for valid pages only' );
rms_wufc_assert( array( 200, 201 ) === array_map( 'intval', $items ), 'returned item IDs match created items' );
rms_wufc_assert( array( 300 ) === array_map( 'intval', $GLOBALS['_deleted_posts'] ), 'replace deletes the pre-existing item first (no duplicates)' );
rms_wufc_assert( array( 1, 2 ) === array_map( 'intval', array_column( $GLOBALS['_nav_item_log'], 'menu-item-position' ) ), 'positions are sequential' );
echo "PASS replace-menu-items-order-and-filter\n";
++$passed;

// --- assign_location / clear_menu_locations --------------------------------
rms_wufc_reset();
$mb   = new Menu_Builder( new Logger() );
$menu = $mb->ensure_menu( 'Primary Menu' );
rms_wufc_assert( true === $mb->assign_location( 'primary', $menu ), 'assign_location returns true' );
rms_wufc_assert( array( 'primary' => $menu ) === $GLOBALS['_theme_mods']['nav_menu_locations'] ?? null, 'theme mod stores primary assignment' );
rms_wufc_assert( false === $mb->assign_location( '', $menu ), 'assign_location rejects empty location' );
rms_wufc_assert( false === $mb->assign_location( 'primary', 0 ), 'assign_location rejects zero menu id' );
$mb->clear_menu_locations();
rms_wufc_assert( array() === $GLOBALS['_theme_mods']['nav_menu_locations'] ?? null, 'clear_menu_locations empties all assignments' );
echo "PASS menu-locations-assign-clear\n";
++$passed;

// --- delete_all_menus removes every registered menu ------------------------
rms_wufc_reset();
$mb = new Menu_Builder( new Logger() );
$a  = $mb->ensure_menu( 'Primary Menu' );
$b  = $mb->ensure_menu( 'Mobile Menu' );
rms_wufc_assert( 2 === count( $GLOBALS['_nav_menus'] ), 'two menus registered' );
$deleted = $mb->delete_all_menus();
rms_wufc_assert( array( $a, $b ) === array_map( 'intval', $deleted ), 'delete_all_menus returns deleted IDs' );
rms_wufc_assert( array() === $GLOBALS['_nav_menus'], 'no menus remain after delete_all_menus' );
rms_wufc_assert( array() === $mb->delete_all_menus(), 'second delete is a no-op' );
echo "PASS delete-all-menus\n";
++$passed;

// --- replace_menu_items failure isolation ----------------------------------
rms_wufc_reset();
$mb   = new Menu_Builder( new Logger() );
$menu = $mb->ensure_menu( 'Primary Menu' );
rms_wufc_seed_page( 21, 'services', 'Services' );
rms_wufc_seed_page( 22, 'contact', 'Contact' );
$GLOBALS['_fail_item_create'] = true;
$items = $mb->replace_menu_items( $menu, array( 21, 22 ) );
rms_wufc_assert( array() === $items, 'replace_menu_items returns [] when every item fails' );
rms_wufc_assert( 2 === count( $GLOBALS['_nav_item_log'] ), 'both item attempts were made' );
echo "PASS replace-menu-items-failure-isolated\n";
++$passed;

echo 'Harness passed: ' . $passed . " scenarios.\n";
