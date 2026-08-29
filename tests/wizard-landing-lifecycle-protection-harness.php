<?php
/**
 * Wizard Landing Page Builder: Lifecycle & Protection proofs (Phase 4 task 4.3).
 *
 * Exercises:
 *  - SEO + Ads final-state: menu append/omit, noindex write/read-back, real AI harness keyword prompts
 *  - Type flips: SEO→Ads / Ads→SEO menu + robots reconciliation
 *  - Yoast title/metadesc writing when plugin active; skip+log when absent
 *  - Ads noindex double protection: scoped wp_robots filter (wizard-init.php)
 *  - Ads sitemap exclusion for WordPress Core and Yoast SEO sitemaps
 *  - Step_Generate_Pages deletion guard: landing pages never hard-deleted during site cleanup
 *
 * Usage: php tests/wizard-landing-lifecycle-protection-harness.php
 *
 * @package Simple_RMS_Theme
 */

require_once __DIR__ . '/wizard-landing-phase4-bootstrap.php';

use Inc\Wizard\Canonical_Section_Store;
use Inc\Wizard\Logger;
use Inc\Wizard\Step_Generate_Pages;
use Inc\Wizard\Step_Landing_Page_Builder;
use Inc\Wizard\Yoast_Meta_Writer;

function rms_run_landing_lifecycle_protection_tests(): int {
	$passed = 0;

	// =========================================================================
	// 1. End-to-end landing creation: SEO and Ads final state
	// =========================================================================

	rms_lpb_reset();
	rms_lpb_seed_page( 21, 'home', 'Home' );
	$sm                    = rms_lpb_seed_landing_state();
	$st                    = $sm->get_state();
	$st['generated_pages'] = array( array( 'id' => 21, 'slug' => 'home', 'role' => 'home' ) );
	$st['home_page_slug']  = 'home';
	$sm->save_state( $st );
	$fake    = new RMS_LPB_Fake_Builder( new Logger(), $sm );
	$builder = new Step_Landing_Page_Builder( new Logger(), $sm, $fake );

	$menu_id = rms_lpb_seed_configured_menu( 'Primary Menu' );

	// Seed canonical reusables so bootstrap does not need to generate them.
	$store = new Canonical_Section_Store();
	$store->set_if_empty( 'about-us', array( 'acf_fc_layout' => 'about-us', 'about_headline' => 'Canonical About', 'about_text' => 'Trusted local concrete work.' ) );
	$store->set_if_empty( 'vision-mission-v1', array( 'acf_fc_layout' => 'vision-mission-v1', 'vision_headline' => 'Vision', 'mission_headline' => 'Mission' ) );

	$payload = array(
		'landing_action' => 'start',
		'landings'       => array(
			rms_lpb_landing_payload_item( 'lk_seo', 'kitchen-remodel', array( 'landing_type' => 'seo' ) ),
			rms_lpb_landing_payload_item( 'lk_ads', 'ad-campaign-x', array( 'landing_type' => 'ads', 'primary_keyword' => 'emergency roof patch', 'subkeywords' => array() ) ),
		),
	);
	$result = $builder->run( $payload );
	rms_lpb_assert( ! is_wp_error( $result ), 'landing start with two rows succeeds' );

	$safety = 0;
	while ( true ) {
		$run = $sm->get_state()['landing_run'];
		if ( is_array( $run ) && 'completed' === ( $run['status'] ?? '' ) ) {
			break;
		}
		if ( ++$safety > 12 ) {
			break;
		}
		$res = $builder->run( array( 'landing_action' => 'process' ) );
		rms_lpb_assert( ! is_wp_error( $res ), 'process step succeeds' );
	}

	$state  = $sm->get_state();
	$pages  = $state['landing_pages'];
	$by_key = array();
	foreach ( $pages as $row ) {
		$by_key[ $row['landing_key'] ] = $row;
	}
	rms_lpb_assert( 2 === count( $pages ), 'two landings persisted' );
	rms_lpb_assert( 'complete' === ( $state['step_status']['landing-page-builder'] ?? '' ), 'landing step marked complete' );

	$seo = $by_key['lk_seo'];
	$ads = $by_key['lk_ads'];
	rms_lpb_assert( 'seo' === $seo['landing_type'] && true === $seo['menu_eligible'], 'SEO row type + menu_eligible' );
	rms_lpb_assert( 'ads' === $ads['landing_type'] && false === $ads['menu_eligible'], 'Ads row type + menu_eligible false' );
	rms_lpb_assert( 'pages/landing-page.php' === ( $GLOBALS['_post_meta'][ (int) $seo['id'] ]['_wp_page_template'] ?? '' ), 'SEO page uses landing template' );
	rms_lpb_assert( 'pages/landing-page.php' === ( $GLOBALS['_post_meta'][ (int) $ads['id'] ]['_wp_page_template'] ?? '' ), 'Ads page uses landing template' );

	// Menu final state: SEO present, Ads absent.
	$menu_page_ids = rms_lpb_menu_page_ids( $menu_id );
	rms_lpb_assert( in_array( (int) $seo['id'], $menu_page_ids, true ), 'SEO landing appended to configured menu' );
	rms_lpb_assert( ! in_array( (int) $ads['id'], $menu_page_ids, true ), 'Ads landing absent from configured menu' );

	// Robots final state: SEO noindex cleared; Ads noindex=1 with read-back.
	rms_lpb_assert( '1' !== (string) ( $GLOBALS['_post_meta'][ (int) $seo['id'] ]['_yoast_wpseo_meta-robots-noindex'] ?? '' ), 'SEO landing has no noindex meta' );
	rms_lpb_assert( '1' === (string) ( $GLOBALS['_post_meta'][ (int) $ads['id'] ]['_yoast_wpseo_meta-robots-noindex'] ?? '' ), 'Ads landing noindex meta = 1' );
	rms_lpb_assert( ( new Yoast_Meta_Writer() )->has_noindex( (int) $ads['id'] ), 'Ads noindex read-back verified' );

	// Keyword sections received AI copy through the real harness pipeline.
	$keyword_calls = 0;
	foreach ( $GLOBALS['_ai_prompt_log'] as $entry ) {
		if ( false !== strpos( (string) ( $entry['prompt'] ?? '' ), 'KEYWORD CONTEXT (mandatory' ) ) {
			$keyword_calls++;
		}
	}
	rms_lpb_assert( $keyword_calls >= 2, 'hero/seo-content keyword prompts flowed through the real harness' );
	echo "PASS seo-ads-final-state-menu-robots\n";
	++$passed;

	// =========================================================================
	// 2. Type flip: SEO → Ads and Ads → SEO reconcile menu + robots
	// =========================================================================

	rms_lpb_reset();
	rms_lpb_seed_page( 21, 'home', 'Home' );
	$sm = rms_lpb_seed_landing_state(
		array(
			array( 'id' => 201, 'landing_key' => 'lk_flip', 'slug' => 'flip-page', 'landing_type' => 'seo', 'menu_eligible' => true, 'primary_keyword' => 'concrete repair', 'subkeywords' => array() ),
		)
	);
	$st                    = $sm->get_state();
	$st['generated_pages'] = array( array( 'id' => 21, 'slug' => 'home', 'role' => 'home' ) );
	$st['home_page_slug']  = 'home';
	$sm->save_state( $st );
	rms_lpb_seed_page( 201, 'flip-page', 'Flip Page' );
	$GLOBALS['_post_meta'][201]['rms_landing_type'] = 'seo';
	$GLOBALS['_post_meta'][201]['_wp_page_template'] = 'pages/landing-page.php';
	$menu_id = rms_lpb_seed_configured_menu( 'Primary Menu' );
	$GLOBALS['_nav_menu_items'][] = array( 'menu_id' => $menu_id, 'menu-item-object-id' => 201, 'menu-item-object' => 'page', 'menu-item-type' => 'post_type', 'menu-item-status' => 'publish', 'ID' => 200 );

	$fake    = new RMS_LPB_Fake_Builder( new Logger(), $sm );
	$builder = new Step_Landing_Page_Builder( new Logger(), $sm, $fake );
	$store   = new Canonical_Section_Store();
	$store->set_if_empty( 'about-us', array( 'acf_fc_layout' => 'about-us', 'about_headline' => 'Canonical About' ) );

	// Flip to Ads: matched by id, unchanged identity except type.
	$res = $builder->run(
		array(
			'landing_action' => 'start',
			'landings'       => array(
				rms_lpb_landing_payload_item( 'lk_flip', 'flip-page', array( 'id' => 201, 'landing_type' => 'ads' ) ),
			),
		)
	);
	rms_lpb_assert( ! is_wp_error( $res ), 'ads flip start succeeds' );
	$safety = 0;
	while ( true ) {
		$run = $sm->get_state()['landing_run'];
		if ( is_array( $run ) && 'completed' === ( $run['status'] ?? '' ) ) {
			break;
		}
		if ( ++$safety > 8 ) {
			break;
		}
		$builder->run( array( 'landing_action' => 'process' ) );
	}

	rms_lpb_assert( 'ads' === (string) ( $GLOBALS['_post_meta'][201]['rms_landing_type'] ?? '' ), 'type flip persisted rms_landing_type=ads' );
	rms_lpb_assert( '1' === (string) ( $GLOBALS['_post_meta'][201]['_yoast_wpseo_meta-robots-noindex'] ?? '' ), 'flip to ads writes noindex' );
	rms_lpb_assert( ! in_array( 201, rms_lpb_menu_page_ids( $menu_id ), true ), 'flip to ads removes menu item' );

	// Flip back to SEO: noindex cleared, menu item appended idempotently.
	$res2 = $builder->run(
		array(
			'landing_action' => 'start',
			'landings'       => array(
				rms_lpb_landing_payload_item( 'lk_flip', 'flip-page', array( 'id' => 201, 'landing_type' => 'seo' ) ),
			),
		)
	);
	rms_lpb_assert( ! is_wp_error( $res2 ), 'seo flip-back start succeeds' );
	$safety = 0;
	while ( true ) {
		$run = $sm->get_state()['landing_run'];
		if ( is_array( $run ) && 'completed' === ( $run['status'] ?? '' ) ) {
			break;
		}
		if ( ++$safety > 8 ) {
			break;
		}
		$builder->run( array( 'landing_action' => 'process' ) );
	}
	rms_lpb_assert( 'seo' === (string) ( $GLOBALS['_post_meta'][201]['rms_landing_type'] ?? '' ), 'flip-back persisted seo type' );
	rms_lpb_assert( '1' !== (string) ( $GLOBALS['_post_meta'][201]['_yoast_wpseo_meta-robots-noindex'] ?? '' ), 'flip-back cleared noindex' );
	rms_lpb_assert( 1 === count( array_filter( rms_lpb_menu_page_ids( $menu_id ), static function ( $id ) { return 201 === $id; } ) ), 'flip-back appends SEO to menu' );
	echo "PASS type-flip-seo-ads-reconcile\n";
	++$passed;

	// =========================================================================
	// 3. Yoast title/metadesc when active; skip+log when absent
	// =========================================================================

	rms_lpb_reset();
	$writer = new Yoast_Meta_Writer();
	rms_lpb_seed_page( 301, 'yoast-page', 'Yoast Page' );
	$GLOBALS['_yoast_active'] = true;
	$written = $writer->write_landing_seo( 301, 'concrete repair', 'seo', 'Concrete' );
	rms_lpb_assert( $written, 'Yoast active writes title/metadesc' );
	rms_lpb_assert( '' !== (string) ( $GLOBALS['_post_meta'][301]['_yoast_wpseo_title'] ?? '' ), 'Yoast title meta written' );
	rms_lpb_assert( '' !== (string) ( $GLOBALS['_post_meta'][301]['_yoast_wpseo_metadesc'] ?? '' ), 'Yoast metadesc meta written' );

	rms_lpb_reset();
	rms_lpb_seed_page( 302, 'no-yoast-page', 'No Yoast' );
	$GLOBALS['_yoast_active'] = false;
	$skipped = $writer->write_landing_seo( 302, 'concrete repair', 'seo', 'Concrete' );
	rms_lpb_assert( false === $skipped, 'Yoast absent skips title/metadesc' );
	rms_lpb_assert( '' === (string) ( $GLOBALS['_post_meta'][302]['_yoast_wpseo_title'] ?? '' ), 'no Yoast meta written when absent' );
	echo "PASS yoast-active-writes-absent-skips\n";
	++$passed;

	// =========================================================================
	// 4. Ads noindex double protection: scoped wp_robots filter (wizard-init.php)
	// =========================================================================

	rms_lpb_reset();
	require_once dirname( __DIR__ ) . '/inc/wizard/wizard-init.php';

	// Ads landing on the landing template -> noindex + nofollow.
	$GLOBALS['_is_page']           = true;
	$GLOBALS['_queried_object_id'] = 401;
	$GLOBALS['_wp_page_template']  = 'pages/landing-page.php';
	$GLOBALS['_post_meta'][401]['rms_landing_type'] = 'ads';
	$robots = rms_wizard_ads_landing_wp_robots( array() );
	rms_lpb_assert( ! empty( $robots['noindex'] ) && ! empty( $robots['nofollow'] ), 'ads landing emits noindex+nofollow via wp_robots' );

	// SEO landing on the same template -> untouched.
	$GLOBALS['_post_meta'][401]['rms_landing_type'] = 'seo';
	$robots_seo = rms_wizard_ads_landing_wp_robots( array() );
	rms_lpb_assert( empty( $robots_seo['noindex'] ), 'seo landing on same template not noindexed' );

	// Non-landing template -> untouched.
	$GLOBALS['_wp_page_template']  = 'pages/contact-us.php';
	$GLOBALS['_post_meta'][401]['rms_landing_type'] = 'ads';
	$robots_tpl = rms_wizard_ads_landing_wp_robots( array() );
	rms_lpb_assert( empty( $robots_tpl['noindex'] ), 'ads on non-landing template not noindexed by filter' );

	// Not a page -> untouched.
	$GLOBALS['_is_page'] = false;
	$robots_not_page = rms_wizard_ads_landing_wp_robots( array() );
	rms_lpb_assert( empty( $robots_not_page['noindex'] ), 'non-page request untouched' );
	echo "PASS wp-robots-scoped-ads-only\n";
	++$passed;

	// =========================================================================
	// 5. Ads sitemap exclusion (WP + Yoast)
	// =========================================================================

	rms_lpb_reset();
	$args = rms_wizard_exclude_ads_landings_from_wp_sitemap( array(), 'page' );
	rms_lpb_assert( ! empty( $args['meta_query'] ), 'wp sitemap query gains meta_query' );
	$mq = $args['meta_query'][0] ?? array();
	rms_lpb_assert( 'OR' === ( $mq['relation'] ?? '' ), 'wp sitemap meta_query OR relation' );
	$args_other = rms_wizard_exclude_ads_landings_from_wp_sitemap( array(), 'post' );
	rms_lpb_assert( empty( $args_other['meta_query'] ), 'wp sitemap non-page untouched' );

	// Yoast sitemap entry filter drops ads entries.
	$GLOBALS['_post_meta'][501]['rms_landing_type'] = 'ads';
	$entry  = array( 'loc' => 'https://example.test/ad-page/' );
	$object = new WP_Post( 501 );
	$object->post_type = 'page';
	rms_lpb_assert( false === rms_wizard_filter_yoast_sitemap_entry( $entry, 'page', $object ), 'yoast sitemap drops ads entry' );

	$GLOBALS['_post_meta'][502]['rms_landing_type'] = 'seo';
	$object2 = new WP_Post( 502 );
	$object2->post_type = 'page';
	$kept = rms_wizard_filter_yoast_sitemap_entry( $entry, 'page', $object2 );
	rms_lpb_assert( $kept === $entry, 'yoast sitemap keeps seo entry' );
	echo "PASS ads-sitemap-exclusion\n";
	++$passed;

	// =========================================================================
	// 6. Step_Generate_Pages deletion guard (data-loss protection)
	// =========================================================================

	rms_lpb_reset();
	rms_lpb_seed_page( 601, 'old-page', 'Old Page' );
	rms_lpb_seed_page( 602, 'kitchen-remodel', 'Kitchen Remodel' );
	$GLOBALS['_post_meta'][602]['rms_landing_type'] = 'seo';
	rms_lpb_seed_page( 603, 'home', 'Home' );
	$GLOBALS['_all_page_ids'] = array( 601, 602, 603 );

	$sm   = rms_lpb_seed_landing_state();
	$fake = new RMS_LPB_Fake_Builder( new Logger(), $sm );
	$gp   = new Step_Generate_Pages( new Logger(), $sm, $fake );
	$out  = $gp->run(
		array(
			'pages'           => array(
				'home' => array( 'type' => 'home', 'slug' => 'home', 'title' => 'Home', 'role' => 'home', 'generate' => true ),
			),
			'home_slug'       => 'home',
			'confirm_cleanup' => true,
		)
	);
	rms_lpb_assert( ! is_wp_error( $out ), 'generate-pages run with a residual landing succeeds' );
	rms_lpb_assert( array( 601 ) === array_map( 'intval', $GLOBALS['_deleted_posts'] ), 'unselected non-landing page deleted; landing preserved' );
	rms_lpb_assert( ! in_array( 602, array_map( 'intval', $GLOBALS['_deleted_posts'] ), true ), 'landing page never hard-deleted' );

	// Defense-in-depth: state.landing_pages slug protection even without meta.
	rms_lpb_reset();
	rms_lpb_seed_page( 701, 'slug-only-landing', 'Slug Only' );
	rms_lpb_seed_page( 702, 'home', 'Home' );
	$GLOBALS['_all_page_ids'] = array( 701, 702 );
	$sm = rms_lpb_seed_landing_state(
		array( array( 'id' => 701, 'landing_key' => 'lk_s', 'slug' => 'slug-only-landing', 'landing_type' => 'seo', 'menu_eligible' => true ) )
	);
	$fake = new RMS_LPB_Fake_Builder( new Logger(), $sm );
	$gp   = new Step_Generate_Pages( new Logger(), $sm, $fake );
	$out2 = $gp->run(
		array(
			'pages'           => array(
				'home' => array( 'type' => 'home', 'slug' => 'home', 'title' => 'Home', 'role' => 'home', 'generate' => true ),
			),
			'home_slug'       => 'home',
			'confirm_cleanup' => true,
		)
	);
	rms_lpb_assert( ! is_wp_error( $out2 ), 'slug-protected run succeeds' );
	rms_lpb_assert( array() === $GLOBALS['_deleted_posts'], 'state-tracked landing slug protected even without meta' );
	echo "PASS generate-pages-landing-deletion-guard\n";
	++$passed;

	return $passed;
}

if ( basename( $argv[0] ?? '' ) === basename( __FILE__ ) ) {
	$count = rms_run_landing_lifecycle_protection_tests();
	echo 'Harness passed: ' . $count . " scenarios.\n";
}
