<?php
/**
 * Step_Generate_Pages destructive cleanup + Step_Home_Page_Builder section
 * assembly contracts (Phase 4 task 4.2).
 *
 * Generate Pages: confirmation gating, non-selected page deletion, landing
 * page protection, Home/Blog role assignment, reading settings, and state.
 * Home Page Builder: AI-config dependency, section order, cta-bar alias,
 * unknown layout rejection, AI-unavailable fallback copy, image placeholders,
 * section-only save via build_page(), and canonical first-write.
 *
 * Usage: php tests/wizard-content-flow-steps-harness.php
 */
if ( PHP_SAPI !== 'cli' ) {
	fwrite( STDERR, "CLI only.\n" );
	exit( 1 );
}

require_once __DIR__ . '/wizard-user-friendly-content-flow-bootstrap.php';

require_once dirname( __DIR__ ) . '/inc/wizard/class-ai-provider.php';
require_once dirname( __DIR__ ) . '/inc/wizard/class-ollama-provider.php';

use Inc\Wizard\AI_Content_Harness;
use Inc\Wizard\Canonical_Section_Store;
use Inc\Wizard\Content_Builder;
use Inc\Wizard\Flexible_Content_Layouts;
use Inc\Wizard\Logger;
use Inc\Wizard\State_Manager;
use Inc\Wizard\Step_Generate_Pages;
use Inc\Wizard\Step_Home_Page_Builder;

$passed = 0;

/**
 * Fake Content_Builder that records build_page() calls instead of writing.
 */
class RMS_WUFC_Fake_Builder extends Content_Builder {
	public function build_page( array $page ): int {
		if ( ! empty( $GLOBALS['_fail_build'] ) ) {
			return 0;
		}
		$id = absint( $page['id'] ?? 0 );
		if ( $id <= 0 ) {
			$GLOBALS['_next_id'] = ( $GLOBALS['_next_id'] ?? 100 ) + 1;
			$id                  = $GLOBALS['_next_id'];
		}
		$GLOBALS['_build_log'][] = $page;
		if ( $id > 0 && isset( $page['sections'] ) ) {
			$GLOBALS['_post_meta'][ $id ]['page_sections'] = $page['sections'];
		}
		return $id;
	}
}

// ===========================================================================
// Step_Generate_Pages
// ===========================================================================

// --- cleanup confirmation required ----------------------------------------
rms_wufc_reset();
$l   = new Logger();
$sm  = new State_Manager();
$gp  = new Step_Generate_Pages( $l, $sm, new RMS_WUFC_Fake_Builder( $l, $sm ) );
$out = $gp->run(
	array(
		'pages'     => array(
			'home' => array( 'type' => 'home', 'slug' => 'home', 'title' => 'Home', 'role' => 'home', 'generate' => true ),
		),
		'home_slug' => 'home',
	)
);
rms_wufc_assert( is_wp_error( $out ) && 'rms_wizard_page_cleanup_confirmation_required' === $out->code, 'cleanup confirmation is required before destructive page cleanup' );
rms_wufc_assert( array() === $GLOBALS['_build_log'], 'no pages created without confirmation' );
rms_wufc_assert( 'pending' === ( $sm->get_state()['step_status']['generate-pages'] ?? '' ), 'step stays pending without confirmation' );
echo "PASS generate-pages-confirmation-gated\n";
++$passed;

// --- confirmed cleanup deletes unselected pages only -----------------------
rms_wufc_reset();
rms_wufc_seed_page( 1, 'old-page', 'Old Page' );   // existing, not selected -> deleted
rms_wufc_seed_page( 2, 'home', 'Home' );           // selected by slug -> updated, not deleted
rms_wufc_seed_page( 3, 'about', 'About' );         // selected -> updated
$GLOBALS['_all_page_ids'] = array( 1, 2, 3 );
$l   = new Logger();
$sm  = new State_Manager();
$gp  = new Step_Generate_Pages( $l, $sm, new RMS_WUFC_Fake_Builder( $l, $sm ) );
$out = $gp->run(
	array(
		'pages'           => array(
			'home'  => array( 'type' => 'home', 'slug' => 'home', 'title' => 'Home', 'role' => 'home', 'generate' => true ),
			'about' => array( 'type' => 'about', 'slug' => 'about', 'title' => 'About', 'generate' => true ),
		),
		'home_slug'       => 'home',
		'confirm_cleanup' => true,
	)
);
rms_wufc_assert( ! is_wp_error( $out ), 'confirmed cleanup run succeeds' );
rms_wufc_assert( array( 1 ) === array_map( 'intval', $GLOBALS['_deleted_posts'] ), 'only the unselected page is hard-deleted' );
$by_slug = array();
foreach ( $out['generated_pages'] as $row ) { $by_slug[ $row['slug'] ] = $row; }
rms_wufc_assert( 'home' === ( $by_slug['home']['role'] ?? '' ) && 'home' === ( $by_slug['home']['type'] ?? '' ), 'home role and type assigned' );
rms_wufc_assert( 'about' === ( $by_slug['about']['type'] ?? '' ), 'about type assigned' );
rms_wufc_assert( 'page' === (string) get_option( 'show_on_front' ) && (string) $by_slug['home']['id'] === (string) get_option( 'page_on_front' ), 'front page reading settings updated' );
$state = $sm->get_state();
rms_wufc_assert( 'home' === ( $state['home_page_slug'] ?? '' ), 'home_page_slug persisted' );
rms_wufc_assert( 'complete' === ( $state['step_status']['generate-pages'] ?? '' ), 'step marked complete' );
$shell = array_values( array_filter( $GLOBALS['_build_log'], static function ( $p ) { return 'about' === ( $p['slug'] ?? '' ); } ) );
rms_wufc_assert( 'pages/about-us.php' === ( $shell[0]['meta_input']['_wp_page_template'] ?? '' ), 'about page gets the shell template' );
echo "PASS generate-pages-confirmed-cleanup-and-roles\n";
++$passed;

// --- landing pages are never deleted by cleanup ----------------------------
rms_wufc_reset();
rms_wufc_seed_page( 5, 'kitchen-remodel', 'Kitchen Remodel' );
$GLOBALS['_post_meta'][5]['rms_landing_type'] = 'seo';
rms_wufc_seed_page( 6, 'home', 'Home' );
$GLOBALS['_all_page_ids'] = array( 5, 6 );
$l   = new Logger();
$sm  = new State_Manager();
$gp  = new Step_Generate_Pages( $l, $sm, new RMS_WUFC_Fake_Builder( $l, $sm ) );
$out = $gp->run(
	array(
		'pages'           => array(
			'home' => array( 'type' => 'home', 'slug' => 'home', 'title' => 'Home', 'role' => 'home', 'generate' => true ),
		),
		'home_slug'       => 'home',
		'confirm_cleanup' => true,
	)
);
rms_wufc_assert( ! is_wp_error( $out ), 'run with a residual landing present succeeds' );
rms_wufc_assert( array() === $GLOBALS['_deleted_posts'], 'landing pages are never deleted by generate-pages cleanup' );
echo "PASS generate-pages-landing-protected\n";
++$passed;

// --- home required ----------------------------------------------------------
rms_wufc_reset();
$l   = new Logger();
$sm  = new State_Manager();
$gp  = new Step_Generate_Pages( $l, $sm, new RMS_WUFC_Fake_Builder( $l, $sm ) );
$out = $gp->run(
	array(
		'pages'           => array(
			'about' => array( 'type' => 'about', 'slug' => 'about', 'title' => 'About', 'generate' => true ),
		),
		'confirm_cleanup' => true,
	)
);
rms_wufc_assert( is_wp_error( $out ) && 'rms_wizard_home_page_required' === $out->code, 'home role is required' );
rms_wufc_assert( array() === $GLOBALS['_build_log'], 'no pages created when home missing' );
echo "PASS generate-pages-home-required\n";
++$passed;

// --- blog optional: no blog_slug -> page_for_posts reset to 0 --------------
rms_wufc_reset();
rms_wufc_seed_page( 7, 'home', 'Home' );
$l   = new Logger();
$sm  = new State_Manager();
$gp  = new Step_Generate_Pages( $l, $sm, new RMS_WUFC_Fake_Builder( $l, $sm ) );
$out = $gp->run(
	array(
		'pages'           => array(
			'home' => array( 'type' => 'home', 'slug' => 'home', 'title' => 'Home', 'role' => 'home', 'generate' => true ),
		),
		'home_slug'       => 'home',
		'confirm_cleanup' => true,
	)
);
rms_wufc_assert( ! is_wp_error( $out ), 'blog-less run succeeds' );
rms_wufc_assert( '' === ( $out['blog_page_slug'] ?? '' ), 'no blog slug in result' );
rms_wufc_assert( 0 === (int) get_option( 'page_for_posts' ), 'page_for_posts reset when no blog selected' );
echo "PASS generate-pages-blog-optional\n";
++$passed;

// ===========================================================================
// Step_Home_Page_Builder
// ===========================================================================

// --- ai config required -----------------------------------------------------
rms_wufc_reset();
rms_wufc_seed_page( 21, 'home', 'Home' );
$l  = new Logger();
$sm = new State_Manager();
$st = $sm->get_state();
$st['generated_pages'] = array( array( 'id' => 21, 'slug' => 'home', 'role' => 'home' ) );
$st['home_page_slug']  = 'home';
$st['client_data']     = array( 'company_name' => 'Acme Concrete' );
$sm->save_state( $st );
$hp  = new Step_Home_Page_Builder( $l, $sm, new RMS_WUFC_Fake_Builder( $l, $sm ), new Flexible_Content_Layouts(), new AI_Content_Harness() );
$out = $hp->run( array( 'sections' => array( 'hero' ) ) );
rms_wufc_assert( is_wp_error( $out ) && 'rms_wizard_ai_config_required' === $out->code, 'HPB blocks without AI configuration' );
rms_wufc_assert( array() === $GLOBALS['_build_log'], 'nothing written without AI config' );
echo "PASS home-builder-ai-config-required\n";
++$passed;

// --- empty sections blocked -------------------------------------------------
rms_wufc_reset();
rms_wufc_seed_page( 21, 'home', 'Home' );
$l  = new Logger();
$sm = new State_Manager();
$st = $sm->get_state();
$st['generated_pages'] = array( array( 'id' => 21, 'slug' => 'home', 'role' => 'home' ) );
$st['home_page_slug']  = 'home';
$st['client_data']     = array( 'company_name' => 'Acme Concrete' );
$st['ai_config']       = array( 'provider' => 'ollama-cloud', 'model' => 'test-model', 'has_credentials' => true );
$sm->save_state( $st );
$hp  = new Step_Home_Page_Builder( $l, $sm, new RMS_WUFC_Fake_Builder( $l, $sm ), new Flexible_Content_Layouts(), new AI_Content_Harness() );
$out = $hp->run( array( 'sections' => array() ) );
rms_wufc_assert( is_wp_error( $out ) && 'rms_wizard_home_sections_required' === $out->code, 'HPB blocks with zero sections' );
echo "PASS home-builder-empty-sections-blocked\n";
++$passed;

// --- section assembly, alias, fallback, placeholders, canonical first-write --
rms_wufc_reset();
rms_wufc_seed_page( 21, 'home', 'Home' );
$l  = new Logger();
$sm = new State_Manager();
$st = $sm->get_state();
$st['generated_pages'] = array( array( 'id' => 21, 'slug' => 'home', 'role' => 'home' ) );
$st['home_page_slug']  = 'home';
$st['client_data']     = array( 'company_name' => 'Acme Concrete' );
$st['ai_config']       = array( 'provider' => 'ollama-cloud', 'model' => 'test-model', 'has_credentials' => true );
$sm->save_state( $st );
$fake = new RMS_WUFC_Fake_Builder( $l, $sm );
$hp   = new Step_Home_Page_Builder( $l, $sm, $fake, new Flexible_Content_Layouts(), new AI_Content_Harness() );
$out  = $hp->run(
	array(
		'sections' => array(
			array( 'layout' => 'cta-bar' ),   // alias -> cta-v1
			array( 'layout' => 'about-us', 'item_count' => 1 ),
			array( 'layout' => 'gallery-grid' ), // no fillable fields
			array( 'layout' => 'not-a-layout' ), // unknown -> skipped
		),
	)
);
rms_wufc_assert( ! is_wp_error( $out ), 'HPB run succeeds with mixed layouts' );
$call = $GLOBALS['_build_log'][0] ?? array();
rms_wufc_assert( 21 === (int) ( $call['id'] ?? 0 ) && true === ( $call['section_only'] ?? false ), 'saves section-only to the Home post ID' );
rms_wufc_assert( array( 'cta-v1', 'about-us', 'gallery-grid' ) === array_column( $call['sections'] ?? array(), 'acf_fc_layout' ), 'sections keep payload order; alias normalized; unknown dropped' );
rms_wufc_assert( array( 'cta-v1', 'about-us', 'gallery-grid' ) === ( $out['sections'] ?? array() ), 'result reports the same ordered layouts' );
$cta = $call['sections'][0];
rms_wufc_assert( '' !== ( $cta['cta_v1_headline'] ?? '' ), 'cta-v1 fallback headline populated from client data' );
$about = $call['sections'][1];
rms_wufc_assert( false !== strpos( (string) ( $about['about_headline'] ?? '' ), 'Acme Concrete' ), 'about fallback copy uses the company name' );
rms_wufc_assert( ! array_key_exists( 'about_image', $about ), 'blocked image field is never emitted by the assembler' );
$gallery = $call['sections'][2];
rms_wufc_assert( array() === array_diff( array_keys( $gallery ), array( 'acf_fc_layout' ) ), 'gallery-grid (no fillable fields) gets no invented copy' );
// Image placeholder fallback contract: empty image-like values become the bundled placeholder URL.
$prepared = $fake->prepare_image_fallbacks(
	array(
		'acf_fc_layout'  => 'hero',
		'hero_bg_image'  => '',
		'slide_bg_image' => '',
	)
);
rms_wufc_assert( false !== strpos( (string) ( $prepared['hero_bg_image'] ?? '' ), 'wizard-placeholder.svg' ), 'empty image field gets the wizard placeholder URL' );
rms_wufc_assert( false !== strpos( (string) ( $prepared['slide_bg_image'] ?? '' ), 'wizard-placeholder.svg' ), 'nested image keys are also replaced' );
$state = $sm->get_state();
rms_wufc_assert( 'complete' === ( $state['step_status']['home-page-builder'] ?? '' ), 'HPB step marked complete' );
rms_wufc_assert( array( 'cta-v1', 'about-us', 'gallery-grid' ) === ( $state['selected_home_sections'] ?? array() ), 'selected_home_sections persisted' );
rms_wufc_assert( isset( $state['home_section_rows'] ) && 3 === count( $state['home_section_rows'] ), 'home_section_rows persisted' );
$canonical = new Canonical_Section_Store();
rms_wufc_assert( $canonical->has( 'cta-v1' ) && $canonical->has( 'about-us' ), 'reusable layouts first-written to the canonical store' );
rms_wufc_assert( array( 'acf_fc_layout' ) === array_keys( $canonical->get( 'gallery-grid' ) ), 'canonical row for a no-copy layout holds no invented copy' );
echo "PASS home-builder-section-assembly-and-fallbacks\n";
++$passed;

// --- home page missing -------------------------------------------------------
rms_wufc_reset();
$l  = new Logger();
$sm = new State_Manager();
$st = $sm->get_state();
$st['client_data'] = array( 'company_name' => 'Acme Concrete' );
$st['ai_config']   = array( 'provider' => 'ollama-cloud', 'model' => 'test-model', 'has_credentials' => true );
$sm->save_state( $st );
$hp  = new Step_Home_Page_Builder( $l, $sm, new RMS_WUFC_Fake_Builder( $l, $sm ), new Flexible_Content_Layouts(), new AI_Content_Harness() );
$out = $hp->run( array( 'sections' => array( 'hero' ) ) );
rms_wufc_assert( is_wp_error( $out ) && 'rms_wizard_home_page_not_found' === $out->code, 'HPB blocks when the Home page is missing' );
echo "PASS home-builder-home-not-found\n";
++$passed;

// --- required client data enforced -------------------------------------------
rms_wufc_reset();
rms_wufc_seed_page( 21, 'home', 'Home' );
$l  = new Logger();
$sm = new State_Manager();
$st = $sm->get_state();
$st['generated_pages'] = array( array( 'id' => 21, 'slug' => 'home', 'role' => 'home' ) );
$st['home_page_slug']  = 'home';
$st['ai_config']       = array( 'provider' => 'ollama-cloud', 'model' => 'test-model', 'has_credentials' => true );
$sm->save_state( $st );
$hp  = new Step_Home_Page_Builder( $l, $sm, new RMS_WUFC_Fake_Builder( $l, $sm ), new Flexible_Content_Layouts(), new AI_Content_Harness() );
$out = $hp->run( array( 'sections' => array( 'hero' ) ) );
rms_wufc_assert( is_wp_error( $out ) && 'rms_wizard_home_required_client_data_missing' === $out->code, 'HPB blocks without required client data' );
echo "PASS home-builder-client-data-required\n";
++$passed;

// --- item_count clamped 1..12 -------------------------------------------------
rms_wufc_reset();
rms_wufc_seed_page( 21, 'home', 'Home' );
$l  = new Logger();
$sm = new State_Manager();
$st = $sm->get_state();
$st['generated_pages'] = array( array( 'id' => 21, 'slug' => 'home', 'role' => 'home' ) );
$st['home_page_slug']  = 'home';
$st['client_data']     = array( 'company_name' => 'Acme Concrete' );
$st['ai_config']       = array( 'provider' => 'ollama-cloud', 'model' => 'test-model', 'has_credentials' => true );
$sm->save_state( $st );
$fake = new RMS_WUFC_Fake_Builder( $l, $sm );
$hp   = new Step_Home_Page_Builder( $l, $sm, $fake, new Flexible_Content_Layouts(), new AI_Content_Harness() );
$out  = $hp->run( array( 'sections' => array( array( 'layout' => 'slider', 'item_count' => 99 ) ) ) );
rms_wufc_assert( ! is_wp_error( $out ), 'HPB run with oversized item count succeeds' );
$slider = $GLOBALS['_build_log'][0]['sections'][0] ?? array();
rms_wufc_assert( 12 === count( $slider['slider_slides'] ?? array() ), 'slider repeater item count clamped to 12' );
echo "PASS home-builder-item-count-clamped\n";
++$passed;

// --- AI success path: section_data honors fillable-only copy ------------------
rms_wufc_reset();
$l      = new Logger();
$sm     = new State_Manager();
$fake   = new RMS_WUFC_Fake_Builder( $l, $sm );
$hp     = new Step_Home_Page_Builder( $l, $sm, $fake, new Flexible_Content_Layouts(), new AI_Content_Harness() );
$method = new ReflectionMethod( Step_Home_Page_Builder::class, 'section_data' );
$method->setAccessible( true );
$row = $method->invoke(
	$hp,
	'about-us',
	array( 'company_name' => 'Acme Concrete' ),
	array(
		'about_headline' => 'AI Headline',
		'about_image'    => 'https://evil.example/x.png', // blocked field must be dropped
		'invented_key'   => 'drop me',
	),
	1
);
rms_wufc_assert( 'AI Headline' === ( $row['about_headline'] ?? '' ), 'fillable AI copy applied to the section row' );
rms_wufc_assert( ! array_key_exists( 'about_image', $row ) || '' === (string) $row['about_image'] || false !== strpos( (string) $row['about_image'], 'wizard-placeholder.svg' ), 'blocked image copy is dropped (never injected)' );
rms_wufc_assert( ! array_key_exists( 'invented_key', $row ), 'unknown fields are dropped' );
echo "PASS home-builder-ai-copy-whitelist\n";
++$passed;

echo 'Harness passed: ' . $passed . " scenarios.\n";
