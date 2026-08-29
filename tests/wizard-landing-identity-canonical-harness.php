<?php
/**
 * Wizard Landing Page Builder: Identity & Canonical Store proofs (Phase 4 task 4.3).
 *
 * Exercises:
 *  - Keyword scope: PAGE_LANDING + KEYWORD CONTEXT only for hero/seo-content;
 *    reusable rows stay neutral
 *  - Subkeyword normalization: clamping (0-10) and whitespace cleanup
 *  - Canonical section store: first-write, replace, exclusion of keyword layouts
 *  - Identity preflight: duplicate id/key/slug rejection
 *  - Non-landing slug collisions & landing-only fallback claims
 *  - id + landing_key cross-pair mismatch rejection
 *  - Rename collisions across distinct state rows
 *  - Required keyword validation & explicit replace confirmation gate
 *
 * Usage: php tests/wizard-landing-identity-canonical-harness.php
 *
 * @package Simple_RMS_Theme
 */

require_once __DIR__ . '/wizard-landing-phase4-bootstrap.php';

use Inc\Wizard\AI_Content_Harness;
use Inc\Wizard\Canonical_Section_Store;
use Inc\Wizard\Logger;
use Inc\Wizard\Step_Landing_Page_Builder;

function rms_run_landing_identity_canonical_tests(): int {
	$passed = 0;

	// =========================================================================
	// 1. Keyword scope: PAGE_LANDING + KEYWORD CONTEXT only for hero/seo-content
	// =========================================================================

	rms_lpb_reset();
	$harness = new AI_Content_Harness();
	rms_lpb_assert( $harness->is_keyword_layout( 'hero' ) && $harness->is_keyword_layout( 'seo-content' ), 'hero/seo-content are keyword layouts' );
	rms_lpb_assert( ! $harness->is_keyword_layout( 'about-us' ) && ! $harness->is_keyword_layout( 'vision-mission-v1' ), 'reusable layouts are not keyword layouts' );
	rms_lpb_assert( $harness->is_reusable_layout( 'about-us' ) && ! $harness->is_reusable_layout( 'hero' ), 'reusable classification excludes keyword layouts' );

	$landing_prompt = $harness->get_layer3(
		'hero',
		1,
		array( 'company_name' => 'Acme' ),
		AI_Content_Harness::PAGE_LANDING,
		array(
			'primary_keyword' => 'concrete repair',
			'subkeywords'     => array( 'driveway', 'patio' ),
		)
	);
	rms_lpb_assert( false !== strpos( $landing_prompt, 'KEYWORD CONTEXT (mandatory for this section only)' ), 'landing hero prompt carries KEYWORD CONTEXT' );
	rms_lpb_assert( false !== strpos( $landing_prompt, '- Primary keyword: concrete repair' ), 'landing prompt resolves the primary keyword value' );
	rms_lpb_assert( false !== strpos( $landing_prompt, '- Subkeywords: driveway, patio' ), 'landing prompt resolves the subkeyword values' );
	rms_lpb_assert( false === strpos( $landing_prompt, '{{primary_keyword}}' ) && false === strpos( $landing_prompt, '{{subkeywords}}' ), 'landing prompt contains no literal keyword tokens' );

	$neutral_prompt = $harness->get_layer3(
		'about-us',
		1,
		array( 'company_name' => 'Acme' ),
		AI_Content_Harness::PAGE_LANDING,
		array(
			'primary_keyword' => 'concrete repair',
			'subkeywords'     => array( 'driveway' ),
		)
	);
	rms_lpb_assert( false === strpos( $neutral_prompt, 'KEYWORD CONTEXT' ), 'reusable landing section stays keyword-neutral' );

	$home_prompt = $harness->get_layer3( 'hero', 1, array( 'company_name' => 'Acme' ), AI_Content_Harness::PAGE_HOME, array( 'primary_keyword' => 'concrete repair' ) );
	rms_lpb_assert( false === strpos( $home_prompt, 'KEYWORD CONTEXT (mandatory' ), 'PAGE_HOME hero does not use the landing mandatory block' );
	echo "PASS keyword-scope-landing-vs-neutral\n";
	++$passed;

	// Subkeyword count bounded (normalize_keywords clamps subkeywords to 0-10 and drops empties).
	$normalized = $harness->normalize_keywords(
		array(
			'primary_keyword' => '  deck  ',
			'subkeywords'     => array( 'one', '', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten', 'eleven' ),
		)
	);
	rms_lpb_assert( 'deck' === $normalized['primary_keyword'], 'primary keyword trimmed' );
	rms_lpb_assert( 10 === count( $normalized['subkeywords'] ), 'subkeywords clamped to 10' );
	$empty = $harness->normalize_keywords( array( 'primary_keyword' => '   ', 'subkeywords' => array() ) );
	rms_lpb_assert( '' === $empty['primary_keyword'] && array() === $empty['subkeywords'], 'empty keyword payload normalizes to empty' );
	echo "PASS subkeyword-count-bounded\n";
	++$passed;

	// =========================================================================
	// 2. Canonical store: first-write, replace, override, exclusion
	// =========================================================================

	rms_lpb_reset();
	$store = new Canonical_Section_Store();
	rms_lpb_assert( ! $store->has( 'about-us' ), 'empty store reports no canonical' );
	rms_lpb_assert( array() === $store->get( 'about-us' ), 'empty store get returns []' );
	rms_lpb_assert( $store->set_if_empty( 'about-us', array( 'acf_fc_layout' => 'about-us', 'about_headline' => 'First' ) ), 'first-write succeeds' );
	rms_lpb_assert( ! $store->set_if_empty( 'about-us', array( 'acf_fc_layout' => 'about-us', 'about_headline' => 'Second' ) ), 'set_if_empty refuses overwrite' );
	rms_lpb_assert( 'First' === ( $store->get( 'about-us' )['about_headline'] ?? '' ), 'first-write payload preserved' );
	rms_lpb_assert( $store->has( 'about-us' ), 'has() true after write' );

	rms_lpb_assert( $store->replace( 'about-us', array( 'acf_fc_layout' => 'about-us', 'about_headline' => 'Replaced' ) ), 'replace overwrites' );
	rms_lpb_assert( 'Replaced' === ( $store->get( 'about-us' )['about_headline'] ?? '' ), 'replace payload applied' );

	rms_lpb_assert( ! $store->set_if_empty( 'hero', array( 'acf_fc_layout' => 'hero', 'hero_title' => 'x' ) ), 'keyword layout never stored as canonical' );
	rms_lpb_assert( ! $store->has( 'hero' ) && array() === $store->get( 'hero' ), 'keyword layout excluded from store' );
	rms_lpb_assert( ! $store->replace( 'seo-content', array( 'acf_fc_layout' => 'seo-content' ) ), 'keyword layout replace refused' );

	$summary = $store->summary();
	rms_lpb_assert( isset( $summary['about-us']['has_payload'] ) && true === $summary['about-us']['has_payload'], 'summary reports payload presence' );
	echo "PASS canonical-first-write-replace-exclusion\n";
	++$passed;

	// =========================================================================
	// 3. Identity preflight + collision rejection
	// =========================================================================

	rms_lpb_reset();
	rms_lpb_seed_page( 101, 'existing-one', 'Existing One' );
	rms_lpb_seed_page( 102, 'existing-two', 'Existing Two' );
	$GLOBALS['_post_meta'][101]['rms_landing_type'] = 'seo';
	$GLOBALS['_post_meta'][102]['rms_landing_type'] = 'seo';
	$sm = rms_lpb_seed_landing_state(
		array(
			array( 'id' => 101, 'landing_key' => 'lk_1', 'slug' => 'existing-one', 'landing_type' => 'seo', 'menu_eligible' => true ),
			array( 'id' => 102, 'landing_key' => 'lk_2', 'slug' => 'existing-two', 'landing_type' => 'seo', 'menu_eligible' => true ),
		)
	);
	$builder = new Step_Landing_Page_Builder( new Logger(), $sm );

	// Duplicate id within payload.
	$dupe_id = $builder->run(
		array(
			'landing_action' => 'start',
			'landings'       => array(
				rms_lpb_landing_payload_item( 'lk_1', 'existing-one', array( 'id' => 101 ) ),
				rms_lpb_landing_payload_item( 'lk_2', 'existing-two', array( 'id' => 101 ) ),
			),
		)
	);
	rms_lpb_assert( is_wp_error( $dupe_id ) && 'rms_wizard_landing_duplicate_id' === $dupe_id->get_error_code(), 'duplicate id rejected before writes' );
	rms_lpb_assert( 'failed' === ( $sm->get_state()['step_status']['landing-page-builder'] ?? '' ), 'duplicate id marks step failed' );

	// Duplicate landing_key.
	$dupe_key = $builder->run(
		array(
			'landing_action' => 'start',
			'landings'       => array(
				rms_lpb_landing_payload_item( 'lk_1', 'one' ),
				rms_lpb_landing_payload_item( 'lk_1', 'two' ),
			),
		)
	);
	rms_lpb_assert( is_wp_error( $dupe_key ) && 'rms_wizard_landing_duplicate_key' === $dupe_key->get_error_code(), 'duplicate key rejected' );

	// Duplicate slug within payload.
	$dupe_slug = $builder->run(
		array(
			'landing_action' => 'start',
			'landings'       => array(
				rms_lpb_landing_payload_item( 'lk_1', 'same-slug' ),
				rms_lpb_landing_payload_item( 'lk_2', 'same-slug' ),
			),
		)
	);
	rms_lpb_assert( is_wp_error( $dupe_slug ) && 'rms_wizard_landing_duplicate_slug' === $dupe_slug->get_error_code(), 'duplicate slug rejected' );
	echo "PASS identity-dup-id-key-slug-rejected\n";
	++$passed;

	// Slug collision with an existing NON-landing page.
	rms_lpb_reset();
	rms_lpb_seed_page( 77, 'services', 'Services' );
	$sm = rms_lpb_seed_landing_state();
	$builder = new Step_Landing_Page_Builder( new Logger(), $sm );
	$collision = $builder->run(
		array(
			'landing_action' => 'start',
			'landings'       => array( rms_lpb_landing_payload_item( 'lk_1', 'services' ) ),
		)
	);
	rms_lpb_assert( is_wp_error( $collision ) && 'rms_wizard_landing_slug_collision' === $collision->get_error_code(), 'slug colliding with non-landing page rejected' );

	// Slug fallback resolves ONLY to an existing landing page (claim by slug).
	rms_lpb_reset();
	rms_lpb_seed_page( 88, 'kitchen-remodel', 'Kitchen Remodel' );
	$GLOBALS['_post_meta'][88]['rms_landing_type'] = 'seo';
	$sm = rms_lpb_seed_landing_state();
	$builder = new Step_Landing_Page_Builder( new Logger(), $sm );
	$claim = $builder->run(
		array(
			'landing_action' => 'start',
			'landings'       => array(
				rms_lpb_landing_payload_item( 'lk_1', 'kitchen-remodel', array( 'id' => null ) ),
			),
		)
	);
	rms_lpb_assert( ! is_wp_error( $claim ), 'slug fallback to an existing landing page is allowed' );
	$run = $sm->get_state()['landing_run'];
	rms_lpb_assert( 88 === (int) ( $run['items'][0]['id'] ?? 0 ), 'slug fallback matched the landing page id' );
	echo "PASS slug-collision-and-landing-fallback\n";
	++$passed;

	// id + landing_key cross-pair mismatch.
	rms_lpb_reset();
	rms_lpb_seed_page( 101, 'existing-landing', 'Existing Landing' );
	rms_lpb_seed_page( 102, 'other', 'Other' );
	$GLOBALS['_post_meta'][101]['rms_landing_type'] = 'seo';
	$GLOBALS['_post_meta'][102]['rms_landing_type'] = 'seo';
	$sm = rms_lpb_seed_landing_state(
		array(
			array( 'id' => 101, 'landing_key' => 'lk_alpha', 'slug' => 'existing-landing', 'landing_type' => 'seo', 'menu_eligible' => true ),
			array( 'id' => 102, 'landing_key' => 'lk_beta', 'slug' => 'other', 'landing_type' => 'seo', 'menu_eligible' => true ),
		)
	);
	$builder = new Step_Landing_Page_Builder( new Logger(), $sm );
	$mismatch = $builder->run(
		array(
			'landing_action' => 'start',
			'landings'       => array(
				rms_lpb_landing_payload_item( 'lk_beta', 'existing-landing', array( 'id' => 101 ) ),
			),
		)
	);
	rms_lpb_assert( is_wp_error( $mismatch ) && 'rms_wizard_landing_identity_mismatch' === $mismatch->get_error_code(), 'id+key cross-pair mismatch rejected' );
	echo "PASS identity-cross-pair-mismatch\n";
	++$passed;

	// Rename collision: new slug belongs to a DIFFERENT landing in state.
	rms_lpb_reset();
	rms_lpb_seed_page( 111, 'landing-a', 'Landing A' );
	rms_lpb_seed_page( 112, 'landing-b', 'Landing B' );
	$GLOBALS['_post_meta'][111]['rms_landing_type'] = 'seo';
	$GLOBALS['_post_meta'][112]['rms_landing_type'] = 'seo';
	$sm = rms_lpb_seed_landing_state(
		array(
			array( 'id' => 111, 'landing_key' => 'lk_a', 'slug' => 'landing-a', 'landing_type' => 'seo', 'menu_eligible' => true ),
			array( 'id' => 112, 'landing_key' => 'lk_b', 'slug' => 'landing-b', 'landing_type' => 'seo', 'menu_eligible' => true ),
		)
	);
	$builder = new Step_Landing_Page_Builder( new Logger(), $sm );
	$rename = $builder->run(
		array(
			'landing_action' => 'start',
			'landings'       => array(
				rms_lpb_landing_payload_item( 'lk_a', 'landing-b', array( 'id' => 111 ) ),
			),
		)
	);
	rms_lpb_assert( is_wp_error( $rename ) && 'rms_wizard_landing_slug_collision' === $rename->get_error_code(), 'rename into another landing slug rejected' );
	echo "PASS rename-collision-rejected\n";
	++$passed;

	// Empty primary keyword rejected & replace canonical requires confirmation.
	rms_lpb_reset();
	$sm = rms_lpb_seed_landing_state();
	$builder = new Step_Landing_Page_Builder( new Logger(), $sm );
	$no_kw = $builder->run(
		array(
			'landing_action' => 'start',
			'landings'       => array( rms_lpb_landing_payload_item( 'lk_1', 'no-keyword', array( 'primary_keyword' => '   ' ) ) ),
		)
	);
	rms_lpb_assert( is_wp_error( $no_kw ) && 'rms_wizard_landing_keyword_required' === $no_kw->get_error_code(), 'empty primary keyword rejected' );

	$unconfirmed = $builder->run(
		array(
			'landing_action'    => 'start',
			'landings'          => array( rms_lpb_landing_payload_item( 'lk_1', 'landing-a' ) ),
			'replace_canonical' => array( 'about-us' => true ),
		)
	);
	rms_lpb_assert( is_wp_error( $unconfirmed ) && 'rms_wizard_replace_canonical_unconfirmed' === $unconfirmed->get_error_code(), 'replace canonical requires modal confirmation' );
	echo "PASS keyword-required-and-replace-confirmation\n";
	++$passed;

	return $passed;
}

if ( basename( $argv[0] ?? '' ) === basename( __FILE__ ) ) {
	$count = rms_run_landing_identity_canonical_tests();
	echo 'Harness passed: ' . $count . " scenarios.\n";
}
