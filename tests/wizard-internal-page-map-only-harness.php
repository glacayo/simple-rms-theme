<?php
/**
 * Map-only identity mapping is metadata-only: it must never set
 * current_step/step_status, never mark complete/running/failed, never revoke
 * grandfathered completion, and never change card completion counts.
 *
 * Usage: php tests/wizard-internal-page-map-only-harness.php
 */
if ( PHP_SAPI !== 'cli' ) {
	fwrite( STDERR, "CLI only.\n" );
	exit( 1 );
}

require_once __DIR__ . '/wizard-internal-page-activation-bootstrap.php';

use Inc\Wizard\State_Manager;
use Inc\Wizard\Step_Controller;
use Inc\Wizard\Step_Internal_Page_Builder;
use Inc\Wizard\Wizard_Mutation_Fence;

$passed = 0;

function rms_maponly_seed( array $overrides = array() ): void {
	rms_ipa_reset();
	$GLOBALS['_posts'][40] = new WP_Post( 40 );
	$GLOBALS['_posts'][40]->post_name = 'history';
	$sm = new State_Manager();
	$st = $sm->get_state();
	$st['generated_pages'] = array( array( 'id' => 40, 'slug' => 'history', 'role' => '' ) );
	foreach ( $overrides as $key => $value ) {
		$st[ $key ] = $value;
	}
	$sm->save_state( $st );
}

function rms_maponly_payload(): array {
	return array(
		'map_pages'         => array( array( 'post_id' => 40, 'type' => 'about' ) ),
		'confirm_map'       => true,
		'confirm_map_types' => array( 'about' ),
	);
}

// 1. Grandfathered completed site: map-only stays optional, completion preserved.
rms_maponly_seed();
$sm = new State_Manager();
$st = $sm->get_state();
foreach ( Step_Controller::get_required_steps() as $step ) {
	if ( 'internal-page-builder' === $step ) {
		continue;
	}
	$st['step_status'][ $step ] = 'complete';
}
$sm->save_state( $st );
$sm->mark_completed();
$unlock = ( new Step_Controller() )->execute_step( 'unlock', array() );
rms_ipa_assert( ! is_wp_error( $unlock ), 'unlock succeeds on grandfathered site' );
$before = $sm->get_state();
$resp = ( new Step_Controller() )->execute_step( 'internal-page-builder', rms_maponly_payload() );
rms_ipa_assert( ! is_wp_error( $resp ), 'grandfathered map-only succeeds' );
rms_ipa_assert( 'mapped' === ( $resp['result']['action'] ?? '' ), 'map-only response action is mapped' );
rms_ipa_assert( array( 'about' ) === ( $resp['result']['page_types_assigned'] ?? array() ), 'map-only reports assigned types' );
$after = ( new State_Manager() )->get_state();
rms_ipa_assert( true === $sm->has_completion_flag(), 'grandfathered completion flag preserved' );
rms_ipa_assert( '' === (string) ( $after['step_status']['internal-page-builder'] ?? '' ), 'map-only does not set step status' );
rms_ipa_assert( $before['current_step'] === $after['current_step'], 'map-only does not mutate current_step' );
$contract = Step_Controller::completion_contract( $after );
rms_ipa_assert( true === $contract['grandfathered_internal_pages'], 'map-only keeps ninth step grandfathered optional' );
rms_ipa_assert( 'Wizard complete' === $contract['progress_text'], 'map-only keeps Wizard complete' );
echo "PASS map-only-grandfathered-stays-optional\n";
++$passed;

// 2. Fresh pending site: map-only stays pending, no current_step mutation.
rms_maponly_seed();
$sm = new State_Manager();
$st = $sm->get_state();
$st['step_status']['internal-page-builder'] = 'pending';
$sm->save_state( $st );
$before = $sm->get_state();
$resp = ( new Step_Controller() )->execute_step( 'internal-page-builder', rms_maponly_payload() );
rms_ipa_assert( ! is_wp_error( $resp ), 'fresh pending map-only succeeds' );
rms_ipa_assert( 'mapped' === ( $resp['result']['action'] ?? '' ), 'fresh map-only response action is mapped' );
$after = ( new State_Manager() )->get_state();
rms_ipa_assert( 'pending' === (string) ( $after['step_status']['internal-page-builder'] ?? '' ), 'fresh pending stays pending after map-only' );
rms_ipa_assert( $before['current_step'] === $after['current_step'], 'fresh map-only does not mutate current_step' );
rms_ipa_assert( 'about' === ( $after['generated_pages'][0]['type'] ?? '' ), 'map-only persists the identity type' );
echo "PASS map-only-fresh-pending-stays-pending\n";
++$passed;

// 3. Prior failed site: map-only stays failed.
rms_maponly_seed();
$sm = new State_Manager();
$st = $sm->get_state();
$st['step_status']['internal-page-builder'] = 'failed';
$sm->save_state( $st );
$resp = ( new Step_Controller() )->execute_step( 'internal-page-builder', rms_maponly_payload() );
rms_ipa_assert( ! is_wp_error( $resp ), 'prior failed map-only succeeds' );
$after = ( new State_Manager() )->get_state();
rms_ipa_assert( 'failed' === (string) ( $after['step_status']['internal-page-builder'] ?? '' ), 'prior failed stays failed after map-only' );
echo "PASS map-only-prior-failed-stays-failed\n";
++$passed;

// 4. Card completion counts unchanged: 0/N stays 0/N after mapping all shells.
rms_maponly_seed();
$GLOBALS['_posts'][12] = new WP_Post( 12 );
$GLOBALS['_posts'][12]->post_name = 'about';
$GLOBALS['_posts'][18] = new WP_Post( 18 );
$GLOBALS['_posts'][18]->post_name = 'blog';
$sm = new State_Manager();
$st = $sm->get_state();
$st['generated_pages'] = array(
	array( 'id' => 12, 'slug' => 'about', 'type' => 'about', 'role' => '' ),
	array( 'id' => 18, 'slug' => 'blog', 'type' => 'blog', 'role' => 'blog' ),
	array( 'id' => 40, 'slug' => 'history', 'role' => '' ),
);
$st['internal_pages']['about'] = array_merge( State_Manager::INTERNAL_PAGE_ENTRY, array( 'post_id' => 12, 'status' => 'complete' ) );
$st['internal_pages']['blog']  = array_merge( State_Manager::INTERNAL_PAGE_ENTRY, array( 'post_id' => 18, 'status' => 'pending' ) );
$sm->save_state( $st );
$resp = ( new Step_Controller() )->execute_step(
	'internal-page-builder',
	array(
		'map_pages'         => array( array( 'post_id' => 40, 'type' => 'testimonials' ) ),
		'confirm_map'       => true,
		'confirm_map_types' => array( 'testimonials' ),
	)
);
rms_ipa_assert( ! is_wp_error( $resp ), 'map-only with existing shells succeeds' );
$after = ( new State_Manager() )->get_state();
rms_ipa_assert( 'complete' === (string) ( $after['internal_pages']['about']['status'] ?? '' ), 'existing complete plan entry unchanged' );
rms_ipa_assert( 'pending' === (string) ( $after['internal_pages']['blog']['status'] ?? '' ), 'existing pending plan entry unchanged' );
rms_ipa_assert( '' === (string) ( $after['step_status']['internal-page-builder'] ?? '' ), 'map-only leaves step status untouched' );
$preview = \Inc\Wizard\Internal_Page_Identity::preview_plan( $after );
$resolved = 0;
foreach ( $preview['types'] as $entry ) {
	if ( ! empty( $entry['available'] ) ) {
		++$resolved;
	}
}
rms_ipa_assert( 3 === $resolved && 0 === count( $preview['unmapped'] ), 'all three shells resolved after mapping' );
rms_ipa_assert( 0 === $GLOBALS['_page_writes'], 'map-only writes no pages' );
echo "PASS map-only-card-counts-unchanged\n";
++$passed;

// 5. Builder-level map-only: distinct action, no step status, no writes.
rms_maponly_seed();
$sm = new State_Manager();
$st = $sm->get_state();
$st['step_status']['internal-page-builder'] = 'pending';
$sm->save_state( $st );
$builder = new Step_Internal_Page_Builder();
$fence   = new Wizard_Mutation_Fence();
$owner   = $fence->acquire();
$builder->accept_mutation_owner( (string) $owner );
$fence->authorize_agent( $builder, (string) $owner );
$meta_before = $GLOBALS['_post_meta'];
$options_before = $GLOBALS['_options'];
$resp = $builder->run( rms_maponly_payload() );
rms_ipa_assert( ! is_wp_error( $resp ), 'builder map-only succeeds' );
rms_ipa_assert( 'mapped' === ( $resp['action'] ?? '' ), 'builder map-only action is mapped' );
rms_ipa_assert( ! isset( $resp['done'] ) && ! isset( $resp['status'] ), 'builder map-only never returns done or step status' );
$after = ( new State_Manager() )->get_state();
rms_ipa_assert( 'pending' === (string) ( $after['step_status']['internal-page-builder'] ?? '' ), 'builder map-only leaves step status pending' );
rms_ipa_assert( $meta_before === $GLOBALS['_post_meta'], 'builder map-only writes no ACF or template meta' );
rms_ipa_assert( ( $options_before[ \Inc\Wizard\Canonical_Section_Store::OPTION_KEY ] ?? null ) === ( $GLOBALS['_options'][ \Inc\Wizard\Canonical_Section_Store::OPTION_KEY ] ?? null ), 'builder map-only writes no canonical' );
rms_ipa_assert( 0 === $GLOBALS['_page_writes'], 'builder map-only writes no pages' );
rms_ipa_assert( array() === ( new \Inc\Wizard\Logger() )->all(), 'builder map-only writes no logs' );
$fence->clear_agent( (string) $owner );
$fence->release( (string) $owner );
echo "PASS map-only-builder-distinct-action-no-writes\n";
++$passed;

// 6. Negative control: without the map-only branch (action start), the step
// would be marked running/complete — proving the branch is what protects it.
rms_maponly_seed();
$sm = new State_Manager();
$st = $sm->get_state();
$st['step_status']['internal-page-builder'] = 'pending';
$sm->save_state( $st );
$resp = ( new Step_Controller() )->execute_step(
	'internal-page-builder',
	array(
		'action'            => 'start',
		'map_pages'         => array( array( 'post_id' => 40, 'type' => 'about' ) ),
		'confirm_map'       => true,
		'confirm_map_types' => array( 'about' ),
	)
);
rms_ipa_assert( ! is_wp_error( $resp ), 'start with map payload still runs' );
$after = ( new State_Manager() )->get_state();
rms_ipa_assert( 'running' === (string) ( $after['step_status']['internal-page-builder'] ?? '' ), 'start path writes running (negative control: map-only branch is what skips it)' );
rms_ipa_assert( 'internal-page-builder' === (string) ( $after['current_step'] ?? '' ), 'start path mutates current_step (negative control)' );
echo "PASS map-only-negative-control-start-writes-progress\n";
++$passed;

echo 'Harness passed: ' . $passed . " scenarios.\n";
