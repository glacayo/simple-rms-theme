<?php
/**
 * Vision/Mission V1 generation and validation contract harness (issue #127, slice 1).
 *
 * Guards the fixed three-card generation/validation boundary shared by the AI
 * harness and the Home and Landing builders:
 *   - fixed item count on Home, Landing, and layer 3 prompts
 *   - exact ordered AI titles plus claim prohibitions
 *   - raw-before-sanitize and post-sanitize all-or-nothing card validation,
 *     including malformed extra rows and card_highlight stripping
 *
 * Single-process harness: these boundaries share one deterministic runtime and
 * need no function-definition isolation. It deliberately does not load
 * wizard-init.php, so it passes against the pre-slice wizard default count.
 *
 * Usage: php tests/vision-mission-v1-generation-contract-harness.php
 *
 * @package Simple_RMS_Theme
 */

if ( PHP_SAPI !== 'cli' ) {
	fwrite( STDERR, "CLI only.\n" );
	exit( 1 );
}

require_once __DIR__ . '/wizard-landing-phase4-bootstrap.php';

use Inc\Wizard\AI_Content_Harness;
use Inc\Wizard\Logger;
use Inc\Wizard\Step_Home_Page_Builder;
use Inc\Wizard\Step_Landing_Page_Builder;

function rms_vm_gen_assert( $condition, string $message ): void {
	if ( ! $condition ) {
		fwrite( STDERR, $message . "\n" );
		exit( 1 );
	}
}

/**
 * @param string[] $needles
 */
function rms_vm_gen_contains( string $haystack, array $needles, bool $should = true ): void {
	foreach ( $needles as $needle ) {
		rms_vm_gen_assert(
			( false !== strpos( $haystack, $needle ) ) === $should,
			( $should ? 'Expected' : 'Did not expect' ) . ' [' . $needle . ']'
		);
	}
}

/**
 * @return string[]
 */
function rms_vm_gen_titles(): array {
	return array( 'Our Vision', 'Our Mission', 'Why Choose Us' );
}

/**
 * @return array<int,array<string,string>>
 */
function rms_vm_gen_valid_cards(): array {
	return array(
		array( 'card_title' => 'Our Vision', 'card_text' => 'Grounded vision copy.' ),
		array( 'card_title' => 'Our Mission', 'card_text' => 'Grounded mission copy.' ),
		array( 'card_title' => 'Why Choose Us', 'card_text' => 'Grounded reason copy.' ),
	);
}

/**
 * Validate one vision-mission-v1 payload and return its cleaned card rows.
 *
 * @param array<string,mixed> $payload
 *
 * @return array<int,array<string,string>>
 */
function rms_vm_gen_validate_cards( array $payload ): array {
	$clean = ( new AI_Content_Harness() )->validate_fields( 'vision-mission-v1', $payload );

	return is_array( $clean['vm_v1_cards'] ?? null ) ? $clean['vm_v1_cards'] : array();
}

function rms_vm_gen_fixed_item_count(): void {
	$harness = new AI_Content_Harness();
	rms_vm_gen_assert( $harness->has_fixed_item_count( 'vision-mission-v1' ), 'vision-mission-v1 is a fixed-item-count layout' );
	rms_vm_gen_assert( 3 === $harness->get_fixed_item_count( 'vision-mission-v1' ), 'fixed item count is 3' );
	rms_vm_gen_assert( 0 === $harness->get_fixed_item_count( 'about-us' ), 'about-us has no fixed item count' );
	rms_vm_gen_assert( 3 === $harness->resolve_item_count( 'vision-mission-v1', 2 ), 'resolve floors requested count 2 to 3' );
	rms_vm_gen_assert( 3 === $harness->resolve_item_count( 'vision-mission-v1', 0 ), 'resolve floors requested count 0 to 3' );

	foreach ( array( 0, 1, 2, 5, 12 ) as $requested ) {
		$prompt = $harness->get_layer3( 'vision-mission-v1', $requested, array( 'company_name' => 'Acme' ) );
		rms_vm_gen_assert( false !== strpos( $prompt, 'Requested item count: 3' ), 'layer3 requests exactly three rows for count ' . $requested );
			rms_vm_gen_assert( false !== strpos( $prompt, 'return exactly 3 value-card rows' ), 'layer3 card rule requests three rows for count ' . $requested );
	}

	// Builders force the fixed count even when the payload asks for another value.
	rms_lpb_reset();
	$sm              = rms_lpb_seed_landing_state();
	$landing_builder = new Step_Landing_Page_Builder( new Logger(), $sm );
	$landing_count   = new ReflectionMethod( Step_Landing_Page_Builder::class, 'item_count' );
	$landing_count->setAccessible( true );
	rms_vm_gen_assert( 3 === $landing_count->invoke( $landing_builder, 'vision-mission-v1', 2 ), 'landing builder forces three rows' );
	rms_vm_gen_assert( 3 === $landing_count->invoke( $landing_builder, 'vision-mission-v1', 0 ), 'landing builder default is three rows' );

	$home_builder = new Step_Home_Page_Builder( new Logger(), $sm );
	$home_count   = new ReflectionMethod( Step_Home_Page_Builder::class, 'item_count' );
	$home_count->setAccessible( true );
	rms_vm_gen_assert( 3 === $home_count->invoke( $home_builder, 'vision-mission-v1', 4 ), 'home builder forces three rows' );
	rms_vm_gen_assert( 3 === $home_count->invoke( $home_builder, 'vision-mission-v1', 0 ), 'home builder default is three rows' );
	echo "fixed three-row count on every source\n";
}

function rms_vm_gen_layer3_guidance(): void {
	$harness = new AI_Content_Harness();
	$prompt  = $harness->get_layer3( 'vision-mission-v1', 2, array( 'company_name' => 'Acme' ) );

	$positions = array();
	foreach ( rms_vm_gen_titles() as $title ) {
		$position = strpos( $prompt, '"' . $title . '"' );
		rms_vm_gen_assert( false !== $position, 'prompt names the required title ' . $title );
		$positions[] = $position;
	}
	rms_vm_gen_assert( $positions[0] < $positions[1] && $positions[1] < $positions[2], 'required titles appear in contract order' );

	rms_vm_gen_contains( $prompt, array( 'exact order', 'Do not invent credentials', 'industry-specific' ) );
	rms_vm_gen_contains( $prompt, array( 'Do not return card_highlight' ) );
	rms_vm_gen_assert( false === strpos( $prompt, '{{item_count}}' ), 'no unresolved item count token' );
	echo "layer 3 guidance pins the ordered titles and prohibitions\n";
}

function rms_vm_gen_validation_contract(): void {
	$harness = new AI_Content_Harness();

	$clean = $harness->validate_fields( 'vision-mission-v1', array( 'vm_v1_headline' => 'Grounded headline', 'vm_v1_cards' => rms_vm_gen_valid_cards() ) );
	rms_vm_gen_assert( isset( $clean['vm_v1_cards'] ) && 3 === count( $clean['vm_v1_cards'] ), 'valid three-card payload survives validation' );
	rms_vm_gen_assert( 'Our Vision' === ( $clean['vm_v1_cards'][0]['card_title'] ?? '' ), 'validated row order preserved' );

	$with_blocked                     = rms_vm_gen_valid_cards();
	$with_blocked[0]['card_highlight'] = true;
	$clean_blocked                    = $harness->validate_fields( 'vision-mission-v1', array( 'vm_v1_cards' => $with_blocked ) );
	rms_vm_gen_assert( isset( $clean_blocked['vm_v1_cards'] ), 'card_highlight does not invalidate a card payload' );
	rms_vm_gen_assert( ! isset( $clean_blocked['vm_v1_cards'][0]['card_highlight'] ), 'card_highlight is stripped from card rows' );

	$two       = array_slice( rms_vm_gen_valid_cards(), 0, 2 );
	$clean_two = $harness->validate_fields( 'vision-mission-v1', array( 'vm_v1_headline' => 'Keep me', 'vm_v1_cards' => $two ) );
	rms_vm_gen_assert( ! isset( $clean_two['vm_v1_cards'] ), 'two-row payload discarded atomically' );
	rms_vm_gen_assert( 'Keep me' === ( $clean_two['vm_v1_headline'] ?? '' ), 'unrelated fields survive a discarded card set' );

	$four                = rms_vm_gen_valid_cards();
	$four[]              = array( 'card_title' => 'Extra', 'card_text' => 'Extra copy.' );
	$reordered           = rms_vm_gen_valid_cards();
	$reordered[1]        = array( 'card_title' => 'Our Vision', 'card_text' => 'Duplicate title copy.' );
	$wrong_title         = rms_vm_gen_valid_cards();
	$wrong_title[2]['card_title'] = 'Why Us';
	$empty_text          = rms_vm_gen_valid_cards();
	$empty_text[1]['card_text'] = '   ';
	$tag_only            = rms_vm_gen_valid_cards();
	$tag_only[0]['card_text'] = '<p></p>';
	$missing_text        = rms_vm_gen_valid_cards();
	unset( $missing_text[2]['card_text'] );

	$discarded = array(
		'four-row payload'   => $four,
		'duplicate titles'   => $reordered,
		'renamed title'      => $wrong_title,
		'empty card text'    => $empty_text,
		'tag-only card text' => $tag_only,
		'missing card text'  => $missing_text,
		'non-array payload'  => 'not rows',
	);

	foreach ( $discarded as $label => $payload ) {
		rms_vm_gen_assert( array() === rms_vm_gen_validate_cards( array( 'vm_v1_cards' => $payload ) ), $label . ' discards the whole set' );
	}

	// Regression: a malformed extra row that row sanitization would drop must
	// not collapse the payload to an accepted three-card set.
	$valid_plus_scalar    = array_merge( rms_vm_gen_valid_cards(), array( 'malformed' ) );
	$valid_plus_empty     = array_merge( rms_vm_gen_valid_cards(), array( array() ) );
	$valid_plus_highlight = array_merge( rms_vm_gen_valid_cards(), array( array( 'card_highlight' => true ) ) );
	rms_vm_gen_assert( array() === rms_vm_gen_validate_cards( array( 'vm_v1_cards' => $valid_plus_scalar ) ), 'three valid rows plus a scalar fourth row discards the whole set' );
	rms_vm_gen_assert( array() === rms_vm_gen_validate_cards( array( 'vm_v1_cards' => $valid_plus_empty ) ), 'three valid rows plus an empty fourth row discards the whole set' );
	rms_vm_gen_assert( array() === rms_vm_gen_validate_cards( array( 'vm_v1_cards' => $valid_plus_highlight ) ), 'three valid rows plus a blocked-key-only fourth row discards the whole set' );

	$clean_blocked_field = $harness->validate_fields( 'vision-mission-v1', array( 'card_highlight' => true ) );
	rms_vm_gen_assert( ! isset( $clean_blocked_field['card_highlight'] ), 'top-level card_highlight stays blocked' );
	echo "deterministic validation accepts only the exact ordered three-card set\n";
}

$rms_vm_gen_scenarios = array(
	'fixed-item-count'    => 'rms_vm_gen_fixed_item_count',
	'layer3-guidance'     => 'rms_vm_gen_layer3_guidance',
	'validation-contract' => 'rms_vm_gen_validation_contract',
);

foreach ( $rms_vm_gen_scenarios as $rms_vm_gen_name => $rms_vm_gen_runner ) {
	$rms_vm_gen_runner();
	echo 'PASS ' . $rms_vm_gen_name . "\n";
}

echo 'Harness passed: ' . count( $rms_vm_gen_scenarios ) . " scenarios.\n";