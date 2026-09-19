<?php
/**
 * Vision/Mission V1 canonical reuse integration harness (issue #127, PR 3).
 *
 * Guards the reuse boundary only: an accepted ordered three-card payload is
 * stored atomically, read back unchanged, replaced atomically, and reused
 * unchanged by every landing while Hero/SEO content stays out of the canonical
 * store.
 *
 * It also owns the template integration isolation scenarios moved out of the
 * PR 2 render harness: the helper-present CTA path and the class-absent render
 * that proves the public template stands alone without the wizard/AI class.
 *
 * Child slice: it relies on the AI harness APIs introduced by PR 1 and the
 * assembler/render behavior introduced by PR 2.
 *
 * Usage: php tests/vision-mission-v1-canonical-reuse-harness.php
 *
 * @package Simple_RMS_Theme
 */

if ( PHP_SAPI !== 'cli' ) {
	fwrite( STDERR, "CLI only.\n" );
	exit( 1 );
}

// Fixture-aware WordPress/ACF stubs for the template integration scenarios.
// They must be defined before the shared bootstrap, whose guarded null stubs
// would otherwise win. The isolated class-absent child defines its own
// WordPress stubs because it must not load the wizard at all.
$GLOBALS['rms_vm_can_sub_fields'] = array();

if ( ! function_exists( 'get_sub_field' ) ) {
	function get_sub_field( $selector = null ) {
		return $GLOBALS['rms_vm_can_sub_fields'][ (string) $selector ] ?? null;
	}
}

if ( ! function_exists( 'home_url' ) ) {
	function home_url( $path = '' ) {
		return 'https://example.test' . (string) $path;
	}
}

/**
 * Isolated child proving the public template renders three neutral cards with
 * no wizard autoloader and no AI harness class loaded. It runs before the
 * bootstrap so `class_exists( ..., false )` stays a true isolation check.
 */
if ( 'template-class-absent' === getenv( 'RMS_VM_CAN_SCENARIO' ) ) {
	if ( ! function_exists( 'esc_html' ) ) {
		function esc_html( $text ) {
			return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
		}
	}

	if ( ! function_exists( 'esc_url' ) ) {
		function esc_url( $url ) {
			return htmlspecialchars( (string) $url, ENT_QUOTES, 'UTF-8' );
		}
	}

	if ( ! function_exists( 'esc_attr' ) ) {
		function esc_attr( $text ) {
			return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
		}
	}

	if ( ! function_exists( 'wp_kses_post' ) ) {
		function wp_kses_post( $text ) {
			return (string) $text;
		}
	}

	$GLOBALS['rms_vm_can_sub_fields'] = array();

	if ( class_exists( 'Inc\\Wizard\\AI_Content_Harness', false ) ) {
		fwrite( STDERR, "wizard AI harness was loaded before the class-absent render\n" );
		exit( 1 );
	}

	ob_start();
	include dirname( __DIR__ ) . '/templates/vision-mission-v1.php';
	$rms_vm_can_html = (string) ob_get_clean();

	if ( class_exists( 'Inc\\Wizard\\AI_Content_Harness', false ) ) {
		fwrite( STDERR, "template render loaded the wizard AI harness\n" );
		exit( 1 );
	}

	if ( false !== stripos( $rms_vm_can_html, 'fatal error' ) ) {
		fwrite( STDERR, "class-absent render emitted a fatal error\n" );
		exit( 1 );
	}

	if ( 3 !== substr_count( $rms_vm_can_html, '<article' ) ) {
		fwrite( STDERR, "class-absent render did not produce exactly three cards\n" );
		exit( 1 );
	}

	$rms_vm_can_positions = array();

	foreach ( array( 'Our Vision', 'Our Mission', 'Why Choose Us' ) as $rms_vm_can_title ) {
		$rms_vm_can_position = strpos( $rms_vm_can_html, '>' . $rms_vm_can_title . '</h3>' );

		if ( false === $rms_vm_can_position ) {
			fwrite( STDERR, 'class-absent render missing title ' . $rms_vm_can_title . "\n" );
			exit( 1 );
		}

		$rms_vm_can_positions[] = $rms_vm_can_position;
	}

	if ( ! ( $rms_vm_can_positions[0] < $rms_vm_can_positions[1] && $rms_vm_can_positions[1] < $rms_vm_can_positions[2] ) ) {
		fwrite( STDERR, "class-absent render broke the ordered contract\n" );
		exit( 1 );
	}

	foreach ( array( 'To be a company clients can rely on', 'To deliver dependable service', 'Clients choose us for straightforward communication' ) as $rms_vm_can_needle ) {
		if ( false === strpos( $rms_vm_can_html, $rms_vm_can_needle ) ) {
			fwrite( STDERR, 'class-absent render missing neutral copy [' . $rms_vm_can_needle . "]\n" );
			exit( 1 );
		}
	}

	foreach ( array( 'Homeowners', 'protect every home', 'Free Estimate' ) as $rms_vm_can_legacy ) {
		if ( false !== strpos( $rms_vm_can_html, $rms_vm_can_legacy ) ) {
			fwrite( STDERR, 'class-absent render leaked legacy copy [' . $rms_vm_can_legacy . "]\n" );
			exit( 1 );
		}
	}

	echo "class-absent template renders three neutral ordered cards without the wizard\n";
	exit( 0 );
}

require_once __DIR__ . '/wizard-landing-phase4-bootstrap.php';

use Inc\Wizard\AI_Content_Harness;
use Inc\Wizard\Canonical_Section_Store;
use Inc\Wizard\Logger;
use Inc\Wizard\Section_Assembler;
use Inc\Wizard\Step_Landing_Page_Builder;

/**
 * Render the vision-mission-v1 template against the current sub-field fixture.
 */
function rms_vm_can_render_template(): string {
	ob_start();
	include dirname( __DIR__ ) . '/templates/vision-mission-v1.php';

	return (string) ob_get_clean();
}

/**
 * Isolated child: the helper-present CTA path only.
 *
 * rms_get_contact_page_url() cannot be undefined after definition, so this
 * branch runs in its own process while the canonical scenario stays
 * helper-absent.
 */
if ( 'cta-helper-present' === getenv( 'RMS_VM_CAN_SCENARIO' ) ) {
	if ( ! function_exists( 'rms_get_contact_page_url' ) ) {
		function rms_get_contact_page_url() {
			return 'https://example.test/contact-us/';
		}
	}

	$GLOBALS['rms_vm_can_sub_fields'] = array(
		'vm_v1_cards'   => rms_vm_can_valid_cards(),
		'vm_v1_cta_url' => '',
	);

	$rms_vm_can_html = rms_vm_can_render_template();

	if ( false === strpos( $rms_vm_can_html, 'href="https://example.test/contact-us/"' ) ) {
		fwrite( STDERR, "helper-present CTA did not resolve through the Contact helper\n" );
		exit( 1 );
	}

	if ( false !== strpos( $rms_vm_can_html, 'href="#contact"' ) ) {
		fwrite( STDERR, "helper-present CTA fell back to bare #contact\n" );
		exit( 1 );
	}

	echo "helper-present CTA resolves through the Contact helper\n";
	exit( 0 );
}

function rms_vm_can_assert( $condition, string $message ): void {
	if ( ! $condition ) {
		fwrite( STDERR, $message . "\n" );
		exit( 1 );
	}
}

/**
 * @return string[]
 */
function rms_vm_can_titles(): array {
	return array( 'Our Vision', 'Our Mission', 'Why Choose Us' );
}

/**
 * @return array<int,array<string,string>>
 */
function rms_vm_can_valid_cards(): array {
	return array(
		array( 'card_title' => 'Our Vision', 'card_text' => 'Grounded vision copy.' ),
		array( 'card_title' => 'Our Mission', 'card_text' => 'Grounded mission copy.' ),
		array( 'card_title' => 'Why Choose Us', 'card_text' => 'Grounded reason copy.' ),
	);
}

function rms_vm_can_reuse(): void {
	// CLI runs with max_execution_time=0; the landing orchestrator requires a
	// finite budget, so emulate the bounded web request before driving a run.
	ini_set( 'max_execution_time', '30' );
	rms_lpb_reset();

	$harness    = new AI_Content_Harness();
	$assembler  = new Section_Assembler( $harness );
	$client     = array( 'company_name' => 'Acme' );
	$vision_row = $assembler->section_data( 'vision-mission-v1', $client, array( 'vm_v1_cards' => rms_vm_can_valid_cards() ), 3 );
	rms_vm_can_assert( rms_vm_can_titles() === array_column( $vision_row['vm_v1_cards'] ?? array(), 'card_title' ), 'canonical vision payload carries the ordered contract' );

	$about_row = array( 'acf_fc_layout' => 'about-us', 'about_headline' => 'Canonical About' );
	$store     = new Canonical_Section_Store();
	rms_vm_can_assert( $store->set_if_empty( 'vision-mission-v1', $vision_row ), 'vision-mission-v1 canonical first write' );
	rms_vm_can_assert( $store->set_if_empty( 'about-us', $about_row ), 'about-us canonical first write' );

	$reader = new Canonical_Section_Store();
	rms_vm_can_assert( $reader->get( 'vision-mission-v1' ) === $vision_row, 'stored payload reads back unchanged' );

	$alternate                 = rms_vm_can_valid_cards();
	$alternate[0]['card_text'] = 'Replaced vision copy.';
	$alternate_row             = $assembler->section_data( 'vision-mission-v1', $client, array( 'vm_v1_cards' => $alternate ), 3 );
	rms_vm_can_assert( $store->replace( 'vision-mission-v1', $alternate_row ), 'canonical replace succeeds' );
	rms_vm_can_assert( $store->get( 'vision-mission-v1' ) === $alternate_row, 'replace swaps only the target entry' );
	rms_vm_can_assert( $store->get( 'about-us' ) === $about_row, 'replace leaves unrelated canonical untouched' );
	rms_vm_can_assert( ! $store->set_if_empty( 'vision-mission-v1', $vision_row ), 'first-write refuses to overwrite after replace' );
	rms_vm_can_assert( $store->get( 'vision-mission-v1' ) === $alternate_row, 'refused overwrite leaves the replaced entry intact' );
	$store->replace( 'vision-mission-v1', $vision_row );

	rms_lpb_seed_page( 21, 'home', 'Home' );
	$sm                    = rms_lpb_seed_landing_state();
	$st                    = $sm->get_state();
	$st['generated_pages'] = array( array( 'id' => 21, 'slug' => 'home', 'role' => 'home' ) );
	$st['home_page_slug']  = 'home';
	$sm->save_state( $st );

	$fake     = new RMS_LPB_Fake_Builder( new Logger(), $sm );
	$builder  = new Step_Landing_Page_Builder( new Logger(), $sm, $fake );
	$sections = array(
		array( 'layout' => 'hero' ),
		array( 'layout' => 'seo-content' ),
		array( 'layout' => 'vision-mission-v1' ),
		array( 'layout' => 'about-us' ),
	);

	$start = $builder->run(
		array(
			'landing_action' => 'start',
			'landings'       => array(
				rms_lpb_landing_payload_item( 'lk_alpha', 'alpha-page', array( 'primary_keyword' => 'concrete repair', 'sections' => $sections ) ),
				rms_lpb_landing_payload_item( 'lk_beta', 'beta-page', array( 'primary_keyword' => 'deck building', 'sections' => $sections ) ),
			),
		)
	);
	rms_vm_can_assert( ! is_wp_error( $start ), 'canonical reuse landing start succeeds' . ( is_wp_error( $start ) ? ' [' . $start->get_error_code() . ': ' . $start->get_error_message() . ']' : '' ) );

	$safety = 0;
	while ( true ) {
		$run = $sm->get_state()['landing_run'];

		if ( is_array( $run ) && 'completed' === ( $run['status'] ?? '' ) ) {
			break;
		}

		if ( ++$safety > 12 ) {
			break;
		}

		$tick = $builder->run( array( 'landing_action' => 'process' ) );
		rms_vm_can_assert( ! is_wp_error( $tick ), 'canonical reuse process tick succeeds' );
	}

	$state = $sm->get_state();
	rms_vm_can_assert( 'completed' === ( $state['landing_run']['status'] ?? '' ), 'canonical reuse run completes' );

	$pages = $state['landing_pages'];
	rms_vm_can_assert( 2 === count( $pages ), 'two landings persisted' );

	$vision_rows = array();
	$hero_titles = array();

	foreach ( $pages as $page ) {
		$id              = (int) ( $page['id'] ?? 0 );
		$stored_sections = is_array( $GLOBALS['_post_meta'][ $id ]['page_sections'] ?? null ) ? $GLOBALS['_post_meta'][ $id ]['page_sections'] : array();

		foreach ( $stored_sections as $row ) {
			$layout = (string) ( $row['acf_fc_layout'] ?? '' );

			if ( 'vision-mission-v1' === $layout ) {
				$vision_rows[] = $row;
			}

			if ( 'about-us' === $layout ) {
				rms_vm_can_assert( $row === $about_row, 'canonical about-us reused unchanged' );
			}

			if ( 'hero' === $layout ) {
				$hero_titles[] = (string) ( $row['hero_title'] ?? '' );
			}
		}
	}

	rms_vm_can_assert( 2 === count( $vision_rows ), 'both landings carry a vision-mission-v1 section' );
	rms_vm_can_assert( $vision_rows[0] === $vision_row, 'first landing reuses the canonical payload unchanged' );
	rms_vm_can_assert( $vision_rows[1] === $vision_row, 'second landing reuses the same canonical payload unchanged' );
	rms_vm_can_assert( 2 === count( array_unique( $hero_titles ) ), 'keyword hero copy still generates per landing' );

	$after = new Canonical_Section_Store();
	rms_vm_can_assert( $after->get( 'vision-mission-v1' ) === $vision_row, 'canonical vision payload unchanged after reuse' );
	rms_vm_can_assert( $after->get( 'about-us' ) === $about_row, 'unrelated canonical unchanged after reuse' );
	rms_vm_can_assert( ! $after->has( 'hero' ) && ! $after->has( 'seo-content' ), 'Hero/SEO content is never stored or altered as canonical' );
	echo "one validated payload is stored and reused unchanged without touching Hero/SEO\n";
}

// Spawn the two isolation children: the class-absent template render and the
// helper-present CTA path. Each runs in its own process because the shared
// bootstrap and the Contact helper cannot both be absent in one process.
$rms_vm_can_children = array( 'template-class-absent', 'cta-helper-present' );

foreach ( $rms_vm_can_children as $rms_vm_can_scenario ) {
	$rms_vm_can_child_output = array();
	$rms_vm_can_child_code   = 0;
	putenv( 'RMS_VM_CAN_SCENARIO=' . $rms_vm_can_scenario );
	exec( '"' . PHP_BINARY . '" ' . escapeshellarg( __FILE__ ), $rms_vm_can_child_output, $rms_vm_can_child_code );
	putenv( 'RMS_VM_CAN_SCENARIO' );
	rms_vm_can_assert( 0 === $rms_vm_can_child_code, $rms_vm_can_scenario . ' child failed: ' . implode( ' | ', $rms_vm_can_child_output ) );
	echo 'PASS ' . $rms_vm_can_scenario . "\n";
}

rms_vm_can_reuse();
echo "PASS canonical-reuse\n";
echo 'Harness passed: ' . ( count( $rms_vm_can_children ) + 1 ) . " scenarios.\n";