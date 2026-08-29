<?php
/**
 * Wizard Landing Page Builder controller proofs (Phase 4 task 4.4).
 *
 * Exercises the committed Step_Controller contracts around the landing step:
 *  - Completed/locked sites reject landing mutations until unlock
 *  - unlock/relock are the only completed-gate pseudo-steps
 *  - unlock/relock never write current_step / step_status (no pollution)
 *  - landing skip-all works after unlock and preserves completion state
 *  - landing start/process skip progress-status writes (no running pollution)
 *  - REQUIRED_STEPS / DISPATCHABLE_STEPS parity with the landing step
 *  - unknown steps rejected before any status write
 *
 * Usage: php tests/wizard-landing-controller-harness.php
 */
require_once __DIR__ . '/wizard-landing-phase4-bootstrap.php';

use Inc\Wizard\State_Manager;
use Inc\Wizard\Step_Controller;
use Inc\Wizard\Wizard_Mutation_Fence;

$passed = 0;

function rms_lc_seed_complete(): void {
	rms_lpb_reset();
	$sm = new State_Manager();
	$st = $sm->get_state();
	foreach ( Step_Controller::get_required_steps() as $step ) {
		$st['step_status'][ $step ] = 'complete';
	}
	$sm->save_state( $st );
	$sm->mark_completed();
}

// ===========================================================================
// 1. Required / dispatchable step parity includes landing + unlock/relock
// ===========================================================================

rms_lpb_reset();
$required = Step_Controller::get_required_steps();
rms_lpb_assert( in_array( 'landing-page-builder', $required, true ), 'landing-page-builder is a required step' );
rms_lpb_assert( ! in_array( 'unlock', $required, true ) && ! in_array( 'relock', $required, true ), 'unlock/relock are not required steps' );

$ref = new ReflectionClass( Step_Controller::class );
$dispatch = $ref->getConstant( 'DISPATCHABLE_STEPS' );
rms_lpb_assert( in_array( 'landing-page-builder', $dispatch, true ), 'landing-page-builder is dispatchable' );
rms_lpb_assert( in_array( 'unlock', $dispatch, true ) && in_array( 'relock', $dispatch, true ), 'unlock/relock are dispatchable' );
foreach ( $required as $step ) {
	rms_lpb_assert( in_array( $step, $dispatch, true ), 'required step ' . $step . ' is dispatchable' );
}
$allowlist = $ref->getConstant( 'COMPLETED_GATE_ALLOWLIST' );
rms_lpb_assert( array( 'unlock', 'relock' ) === array_values( $allowlist ), 'completed-gate allowlist is exactly unlock + relock' );
echo "PASS required-dispatchable-parity\n";
++$passed;

// ===========================================================================
// 2. Completed site rejects landing mutation until unlock; unlock persists
// ===========================================================================

rms_lc_seed_complete();
$locked = ( new Step_Controller() )->execute_step( 'landing-page-builder', array( 'skip_all' => true ) );
rms_lpb_assert( is_wp_error( $locked ) && 'rms_wizard_locked' === $locked->get_error_code(), 'locked completed site rejects landing skip-all' );

$before = $GLOBALS['_options'];
$unlock = ( new Step_Controller() )->execute_step( 'unlock', array() );
rms_lpb_assert( ! is_wp_error( $unlock ) && ! empty( $unlock['success'] ), 'unlock succeeds on completed site' );
rms_lpb_assert( '' !== (string) get_option( 'rms_wizard_unlocked_at', '' ), 'unlock writes rms_wizard_unlocked_at' );
rms_lpb_assert( 1 === (int) get_option( 'rms_wizard_unlocked_by', 0 ), 'unlock writes rms_wizard_unlocked_by' );
rms_lpb_assert( true === ( new State_Manager() )->has_completion_flag(), 'unlock preserves the completion flag' );
rms_lpb_assert( false === ( new State_Manager() )->is_completed(), 'unlock makes the site editable' );
echo "PASS completed-locked-until-unlock\n";
++$passed;

// ===========================================================================
// 3. unlock/relock never write current_step / step_status (no pollution)
// ===========================================================================

rms_lc_seed_complete();
$sm = new State_Manager();
$st = $sm->get_state();
$st['current_step'] = 'menu-setup';
$sm->save_state( $st );

$unlock_result = ( new Step_Controller() )->execute_step( 'unlock', array() );
rms_lpb_assert( ! is_wp_error( $unlock_result ), 'unlock dispatch succeeds' );
$after_unlock = $sm->get_state();
rms_lpb_assert( 'menu-setup' === ( $after_unlock['current_step'] ?? '' ), 'unlock does not change current_step' );
rms_lpb_assert( ! isset( $after_unlock['step_status']['unlock'] ), 'unlock writes no step_status entry' );
rms_lpb_assert( ! isset( $after_unlock['step_status']['relock'] ), 'unlock writes no relock status' );

$relock_result = ( new Step_Controller() )->execute_step( 'relock', array() );
rms_lpb_assert( ! is_wp_error( $relock_result ), 'relock dispatch succeeds' );
$after_relock = $sm->get_state();
rms_lpb_assert( 'menu-setup' === ( $after_relock['current_step'] ?? '' ), 'relock does not change current_step' );
rms_lpb_assert( ! isset( $after_relock['step_status']['unlock'] ) && ! isset( $after_relock['step_status']['relock'] ), 'relock writes no pseudo-step status' );
rms_lpb_assert( true === ( new State_Manager() )->has_completion_flag(), 'relock preserves completion flag' );
rms_lpb_assert( true === ( new State_Manager() )->is_completed(), 'relock restores read-only lock' );
echo "PASS unlock-relock-no-status-pollution\n";
++$passed;

// ===========================================================================
// 4. Skip-all after unlock: no-op completion, completion preserved
// ===========================================================================

rms_lc_seed_complete();
$unlock = ( new Step_Controller() )->execute_step( 'unlock', array() );
rms_lpb_assert( ! is_wp_error( $unlock ), 'unlock succeeds' );

$skip = ( new Step_Controller() )->execute_step( 'landing-page-builder', array( 'skip_all' => true ) );
rms_lpb_assert( ! is_wp_error( $skip ) && ! empty( $skip['success'] ), 'skip-all after unlock succeeds' );
rms_lpb_assert( ! empty( $skip['result']['skipped'] ), 'skip-all result reports skipped' );
$state = ( new State_Manager() )->get_state();
rms_lpb_assert( 'complete' === ( $state['step_status']['landing-page-builder'] ?? '' ), 'skip-all marks landing step complete' );
rms_lpb_assert( true === ( new State_Manager() )->has_completion_flag(), 'skip-all preserves completion flag' );
rms_lpb_assert( false === ( new Wizard_Mutation_Fence() )->is_held(), 'fence released after skip-all' );
echo "PASS skip-all-after-unlock\n";
++$passed;

// ===========================================================================
// 5. Landing start/process never write step-status running (no pollution)
// ===========================================================================

rms_lpb_reset();
$sm = rms_lpb_seed_landing_state();
$st = $sm->get_state();
$st['current_step'] = 'home-page-builder';
$sm->save_state( $st );
$controller = new Step_Controller( $sm, new Inc\Wizard\Logger() );

$start = $controller->execute_step(
	'landing-page-builder',
	array(
		'landing_action' => 'start',
		'landings'       => array( rms_lpb_landing_payload_item( 'lk_1', 'start-page' ) ),
	)
);
rms_lpb_assert( ! is_wp_error( $start ), 'landing start via execute_step succeeds' );
$state_after_start = $sm->get_state();
rms_lpb_assert( 'home-page-builder' === ( $state_after_start['current_step'] ?? '' ), 'landing start does not pollute current_step' );
rms_lpb_assert( '' === (string) ( $state_after_start['step_status']['landing-page-builder'] ?? '' ) || 'complete' === (string) ( $state_after_start['step_status']['landing-page-builder'] ?? '' ), 'landing start never writes running status' );

$process = $controller->execute_step( 'landing-page-builder', array( 'landing_action' => 'process' ) );
// A single-item run completes on start; a follow-up process must 409 (run_complete),
// never re-run work or mutate state.
rms_lpb_assert( is_wp_error( $process ) && 'rms_wizard_landing_run_complete' === $process->get_error_code(), 'process after a completed run 409s without re-running' );
$state_after_process = $sm->get_state();
rms_lpb_assert( 'home-page-builder' === ( $state_after_process['current_step'] ?? '' ), 'landing process does not pollute current_step' );
rms_lpb_assert( false === in_array( 'running', array_values( $state_after_process['step_status'] ), true ), 'landing start/process leave no running status' );
rms_lpb_assert( 'complete' === (string) ( $state_after_process['step_status']['landing-page-builder'] ?? '' ), 'single-item run reached complete status' );
echo "PASS landing-start-process-no-status-pollution\n";
++$passed;

// ===========================================================================
// 6. Unknown / non-dispatchable step rejected before any status write
// ===========================================================================

rms_lpb_reset();
$sm = new State_Manager();
$st = $sm->get_state();
$st['current_step'] = 'dependencies';
$sm->save_state( $st );
$controller = new Step_Controller( $sm, new Inc\Wizard\Logger() );

$unknown = $controller->execute_step( 'not-a-real-step', array() );
rms_lpb_assert( is_wp_error( $unknown ) && 'rms_wizard_unknown_step' === $unknown->get_error_code(), 'unknown step rejected' );
$state_after = $sm->get_state();
rms_lpb_assert( 'dependencies' === ( $state_after['current_step'] ?? '' ), 'unknown step does not pollute current_step' );
rms_lpb_assert( ! isset( $state_after['step_status']['not-a-real-step'] ), 'unknown step writes no status' );
echo "PASS unknown-step-no-pollution\n";
++$passed;

// ===========================================================================
// 7. Locked site rejects landing before any write
// ===========================================================================

rms_lpb_reset();
$sm = new State_Manager();
$st = $sm->get_state();
foreach ( Step_Controller::get_required_steps() as $step ) {
	if ( 'landing-page-builder' === $step ) {
		continue; // Landing left untouched/pending so pollution is detectable.
	}
	$st['step_status'][ $step ] = 'complete';
}
$sm->save_state( $st );
$sm->mark_completed();
$controller = new Step_Controller( $sm, new Inc\Wizard\Logger() );
$landing = $controller->execute_step( 'landing-page-builder', array( 'landing_action' => 'start', 'landings' => array() ) );
rms_lpb_assert( is_wp_error( $landing ) && 'rms_wizard_locked' === $landing->get_error_code(), 'locked site rejects landing start' );
$state = $sm->get_state();
rms_lpb_assert( ! isset( $state['step_status']['landing-page-builder'] ) || '' === (string) $state['step_status']['landing-page-builder'], 'locked rejection writes no landing status' );
rms_lpb_assert( false === ( new Wizard_Mutation_Fence() )->is_held(), 'fence released after locked rejection' );
echo "PASS locked-rejection-no-write\n";
++$passed;

echo 'Harness passed: ' . $passed . " scenarios.\n";
