<?php
/**
 * Vision/Mission V1 UI default, assembler, and render contract harness (issue #127, PR 2).
 *
 * Guards the wizard default count plus the rendering boundary that must agree
 * with the generation contract:
 *   - wizard default item count for vision-mission-v1
 *   - Section_Assembler neutral ordered three-card fallback and override guard
 *   - template valid and legacy render: one loop, iconless, uniform accent
 *   - neutral top-level default copy and helper-absent CTA resolution
 *
 * Child slice: it relies on the AI harness APIs introduced by PR 1 and runs
 * as a single process; the helper-present CTA and class-absent isolation
 * scenarios live in the PR 3 integration harness.
 *
 * Usage: php tests/vision-mission-v1-render-contract-harness.php
 *
 * @package Simple_RMS_Theme
 */

if ( PHP_SAPI !== 'cli' ) {
	fwrite( STDERR, "CLI only.\n" );
	exit( 1 );
}

$GLOBALS['rms_vm_rnd_sub_fields'] = array();

if ( ! function_exists( 'get_sub_field' ) ) {
	function get_sub_field( $selector = null ) {
		return $GLOBALS['rms_vm_rnd_sub_fields'][ (string) $selector ] ?? null;
	}
}

if ( ! function_exists( 'home_url' ) ) {
	function home_url( $path = '' ) {
		return 'https://example.test' . (string) $path;
	}
}

require_once __DIR__ . '/wizard-landing-phase4-bootstrap.php';
require_once dirname( __DIR__ ) . '/inc/wizard/wizard-init.php';

use Inc\Wizard\AI_Content_Harness;
use Inc\Wizard\Section_Assembler;

function rms_vm_rnd_assert( $condition, string $message ): void {
	if ( ! $condition ) {
		fwrite( STDERR, $message . "\n" );
		exit( 1 );
	}
}

/**
 * @param string[] $needles
 */
function rms_vm_rnd_contains( string $haystack, array $needles, bool $should = true ): void {
	foreach ( $needles as $needle ) {
		rms_vm_rnd_assert(
			( false !== strpos( $haystack, $needle ) ) === $should,
			( $should ? 'Expected' : 'Did not expect' ) . ' [' . $needle . ']'
		);
	}
}

/**
 * Render the vision-mission-v1 template against the current sub-field fixture.
 */
function rms_vm_rnd_render_template(): string {
	ob_start();
	include dirname( __DIR__ ) . '/templates/vision-mission-v1.php';

	return (string) ob_get_clean();
}

/**
 * @return string[]
 */
function rms_vm_rnd_titles(): array {
	return array( 'Our Vision', 'Our Mission', 'Why Choose Us' );
}

/**
 * @return array<int,array<string,string>>
 */
function rms_vm_rnd_valid_cards(): array {
	return array(
		array( 'card_title' => 'Our Vision', 'card_text' => 'Grounded vision copy.' ),
		array( 'card_title' => 'Our Mission', 'card_text' => 'Grounded mission copy.' ),
		array( 'card_title' => 'Why Choose Us', 'card_text' => 'Grounded reason copy.' ),
	);
}

/**
 * Neutral top-level fallback copy the template must always protect.
 *
 * @return array{headline:string,intro:string,cta:string}
 */
function rms_vm_rnd_neutral_defaults(): array {
	return array(
		'headline' => 'Our Vision, Mission and Values',
		'intro'    => 'These principles guide how we work with our clients every day.',
		'cta'      => 'Contact Us',
	);
}

/**
 * Legacy industry/availability-specific fallback strings that must never return.
 *
 * @return string[]
 */
function rms_vm_rnd_legacy_default_strings(): array {
	return array( 'Homeowners', 'protect every home', 'Free Estimate' );
}

/**
 * @return string[]
 */
function rms_vm_rnd_unsupported_needles(): array {
	return array( 'roof', 'licens', 'insur', 'warrant', 'guarantee', 'years', 'availab', 'certif', 'duration' );
}

/**
 * Assert $text contains none of the unsupported-claim needles.
 */
function rms_vm_rnd_assert_no_unsupported_claims( string $text, string $context ): void {
	$lowered = strtolower( $text );

	foreach ( rms_vm_rnd_unsupported_needles() as $needle ) {
		rms_vm_rnd_assert( false === strpos( $lowered, $needle ), $context . ' avoids unsupported claim [' . $needle . ']' );
	}
}

/**
 * Assert the three contract titles render in order inside $html.
 */
function rms_vm_rnd_assert_title_order( string $html, string $context ): void {
	$positions = array();

	foreach ( rms_vm_rnd_titles() as $title ) {
		$position = strpos( $html, '>' . $title . '</h3>' );
		rms_vm_rnd_assert( false !== $position, $context . ' title ' . $title );
		$positions[] = $position;
	}

	rms_vm_rnd_assert( $positions[0] < $positions[1] && $positions[1] < $positions[2], $context . ' titles keep contract order' );
}

function rms_vm_rnd_wizard_default_count(): void {
	rms_vm_rnd_assert( 3 === rms_wizard_home_section_default_item_count( 'vision-mission-v1' ), 'wizard default item count is 3' );
	echo "wizard default count is three\n";
}

function rms_vm_rnd_assembler_fallback(): void {
	$harness   = new AI_Content_Harness();
	$assembler = new Section_Assembler( $harness );
	$client    = array( 'company_name' => 'Acme' );

	$fallback = $assembler->section_data( 'vision-mission-v1', $client, array(), 2 );
	$rows     = $fallback['vm_v1_cards'] ?? array();
	rms_vm_rnd_assert( 3 === count( $rows ), 'empty copy produces exactly three fallback cards' );
	rms_vm_rnd_assert( rms_vm_rnd_titles() === array_column( $rows, 'card_title' ), 'fallback titles match the ordered contract' );

	foreach ( $rows as $row ) {
		$text = (string) ( $row['card_text'] ?? '' );

		rms_vm_rnd_assert( '' !== trim( $text ), 'fallback card text is nonempty' );
		rms_vm_rnd_assert_no_unsupported_claims( $text, 'fallback copy' );
	}
	rms_vm_rnd_assert( ! isset( $fallback['card_highlight'] ), 'fallback never emits card_highlight' );

	$over = $assembler->section_data( 'vision-mission-v1', $client, array( 'vm_v1_cards' => rms_vm_rnd_valid_cards() ), 3 );
	rms_vm_rnd_assert( rms_vm_rnd_valid_cards() === ( $over['vm_v1_cards'] ?? array() ), 'valid copy replaces the fallback set' );

	$partial = $assembler->section_data( 'vision-mission-v1', $client, array( 'vm_v1_cards' => array_slice( rms_vm_rnd_valid_cards(), 0, 2 ) ), 3 );
	rms_vm_rnd_assert( 3 === count( $partial['vm_v1_cards'] ?? array() ), 'invalid copy never leaves a partial provider set' );
	rms_vm_rnd_assert( rms_vm_rnd_titles() === array_column( $partial['vm_v1_cards'] ?? array(), 'card_title' ), 'invalid copy falls back to the neutral set' );

	$five = $assembler->section_data( 'vision-mission-v1', $client, array(), 5 );
	rms_vm_rnd_assert( 3 === count( $five['vm_v1_cards'] ?? array() ), 'fallback ignores an inflated requested count' );
	echo "assembler fallback is a safe neutral ordered three-card set\n";
}

function rms_vm_rnd_template_valid_render(): void {
	$cards                      = rms_vm_rnd_valid_cards();
	$cards[0]['card_highlight'] = true;
	$cards[1]['card_highlight'] = false;

	$GLOBALS['rms_vm_rnd_sub_fields'] = array(
		'vm_v1_cards'    => $cards,
		'vm_v1_cta_url'  => '',
		'vm_v1_cta_text' => 'Talk To Us',
	);

	$html = rms_vm_rnd_render_template();

	rms_vm_rnd_assert( 3 === substr_count( $html, '<article' ), 'valid rows render exactly three cards' );
	rms_vm_rnd_assert( 3 === substr_count( $html, 'vision-mission-v1__card--highlight' ), 'accent modifier applies to all three cards' );
	rms_vm_rnd_assert( 3 === substr_count( $html, 'class="vision-mission-v1__card vision-mission-v1__card--highlight"' ), 'all cards share one class contract' );
	rms_vm_rnd_assert( false === strpos( $html, '<svg' ), 'hardcoded card icons removed' );
	rms_vm_rnd_assert( false === strpos( $html, 'vision-mission-v1__icon' ), 'no card icon markup remains' );
	rms_vm_rnd_contains( $html, array( 'Grounded vision copy.', 'Grounded mission copy.', 'Grounded reason copy.' ) );
	rms_vm_rnd_assert_title_order( $html, 'rendered' );
	rms_vm_rnd_assert( false === strpos( $html, 'href="#contact"' ), 'empty CTA URL never falls back to bare #contact' );
	echo "valid rows render three ordered accented cards\n";
}

function rms_vm_rnd_template_legacy_fallback(): void {
	$GLOBALS['rms_vm_rnd_sub_fields'] = array(
		'vm_v1_cards' => array(
			array( 'card_title' => 'Our Vision', 'card_text' => 'Legacy vision copy.' ),
			array( 'card_title' => 'Our Mission', 'card_text' => 'Legacy mission copy.' ),
		),
	);

	$html = rms_vm_rnd_render_template();

	rms_vm_rnd_assert( false === strpos( $html, 'Legacy vision copy.' ), 'legacy two-row payload is not rendered' );
	rms_vm_rnd_assert( 3 === substr_count( $html, '<article' ), 'legacy rows render exactly three fallback cards' );
	rms_vm_rnd_assert( 3 === substr_count( $html, 'vision-mission-v1__card--highlight' ), 'fallback cards keep the shared accent' );
	rms_vm_rnd_assert( false === strpos( $html, '<svg' ), 'fallback renders without card icons' );
	rms_vm_rnd_assert_title_order( $html, 'fallback' );
	rms_vm_rnd_assert_no_unsupported_claims( $html, 'fallback markup' );
	echo "legacy rows render the same neutral ordered fallback set\n";
}

function rms_vm_rnd_template_neutral_defaults(): void {
	rms_vm_rnd_assert( false === function_exists( 'rms_get_contact_page_url' ), 'contact helper intentionally unavailable' );

	$GLOBALS['rms_vm_rnd_sub_fields'] = array();

	$html = rms_vm_rnd_render_template();

	rms_vm_rnd_contains( $html, array_values( rms_vm_rnd_neutral_defaults() ) );
	rms_vm_rnd_contains( $html, rms_vm_rnd_legacy_default_strings(), false );
	rms_vm_rnd_assert( 3 === substr_count( $html, '<article' ), 'empty fields render exactly three neutral fallback cards' );
	rms_vm_rnd_assert_title_order( $html, 'neutral fallback' );
	rms_vm_rnd_assert_no_unsupported_claims( $html, 'neutral defaults' );
	rms_vm_rnd_assert( false !== strpos( $html, 'href="https://example.test/#contact"' ), 'missing helper falls back to the absolute home contact anchor' );
	rms_vm_rnd_assert( false === strpos( $html, 'href="#contact"' ), 'never a bare #contact href' );
	echo "empty fields render neutral, claim-free fallback copy with an absolute home CTA\n";
}

$rms_vm_rnd_scenarios = array(
	'wizard-default-count'      => 'rms_vm_rnd_wizard_default_count',
	'assembler-fallback'        => 'rms_vm_rnd_assembler_fallback',
	'template-valid-render'     => 'rms_vm_rnd_template_valid_render',
	'template-legacy-fallback'  => 'rms_vm_rnd_template_legacy_fallback',
	'template-neutral-defaults' => 'rms_vm_rnd_template_neutral_defaults',
);

foreach ( $rms_vm_rnd_scenarios as $rms_vm_rnd_name => $rms_vm_rnd_runner ) {
	$rms_vm_rnd_runner();
	echo 'PASS ' . $rms_vm_rnd_name . "\n";
}

echo 'Harness passed: ' . count( $rms_vm_rnd_scenarios ) . " scenarios.\n";