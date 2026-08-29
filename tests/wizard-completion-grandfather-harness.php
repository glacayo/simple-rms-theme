<?php
/**
 * Grandfathered completion and skip/re-lock proofs.
 *
 * Usage: php tests/wizard-completion-grandfather-harness.php
 */
if ( PHP_SAPI !== 'cli' ) {
	fwrite( STDERR, "CLI only.\n" );
	exit( 1 );
}

require_once __DIR__ . '/wizard-internal-page-activation-bootstrap.php';

use Inc\Wizard\State_Manager;
use Inc\Wizard\Step_Controller;
use Inc\Wizard\Wizard_Mutation_Fence;

$passed = 0;

function rms_gf_seed_pre_ninth_complete(): void {
	rms_ipa_reset();
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
}

rms_gf_seed_pre_ninth_complete();
$resume = ( new Step_Controller() )->get_resume_state();
$contract = $resume['completion_contract'];
rms_ipa_assert( true === $resume['completed_flag'] && true === $resume['locked'], 'completed site stays complete and locked' );
rms_ipa_assert( true === $contract['grandfathered_internal_pages'], 'ninth step is grandfathered' );
rms_ipa_assert( 'Wizard complete' === $contract['progress_text'], 'progress is not 8/9 incomplete' );
rms_ipa_assert( 8 === (int) $contract['required_count'], 'required count excludes the ninth step' );
rms_ipa_assert( false === $contract['incomplete_notice'], 'no incompleteness notice flag' );
$options_before = $GLOBALS['_options'];
( new Step_Controller() )->get_resume_state();
rms_ipa_assert( $options_before === $GLOBALS['_options'], 'resume GET writes no options' );
echo "PASS no-retroactive-incompleteness-notice\n";
++$passed;

rms_ipa_reset();
$sm = new State_Manager();
$st = $sm->get_state();
foreach ( Step_Controller::get_required_steps() as $step ) {
	if ( 'internal-page-builder' === $step ) {
		continue;
	}
	$st['step_status'][ $step ] = 'complete';
}
$sm->save_state( $st );
$fresh = Step_Controller::completion_contract( $sm->get_state() );
rms_ipa_assert( false === $fresh['grandfathered_internal_pages'], 'fresh sites are not grandfathered' );
rms_ipa_assert( 9 === (int) $fresh['required_count'], 'fresh sites still require nine steps' );
rms_ipa_assert( false !== strpos( (string) $fresh['progress_text'], 'of 9' ), 'fresh progress uses nine steps' );
echo "PASS fresh-site-still-requires-nine\n";
++$passed;

rms_gf_seed_pre_ninth_complete();
$locked = ( new Step_Controller() )->execute_step( 'internal-page-builder', array( 'skip_all' => true ) );
rms_ipa_assert( is_wp_error( $locked ) && 'rms_wizard_locked' === $locked->get_error_code(), 'locked grandfathered site cannot mutate' );
$unlock = ( new Step_Controller() )->execute_step( 'unlock', array() );
rms_ipa_assert( ! is_wp_error( $unlock ) && ! empty( $unlock['success'] ), 'unlock succeeds' );
$skip = ( new Step_Controller() )->execute_step( 'internal-page-builder', array( 'skip_all' => true ) );
rms_ipa_assert( ! is_wp_error( $skip ) && ! empty( $skip['success'] ), 'skip after unlock succeeds' );
$relock = ( new Step_Controller() )->execute_step( 'relock', array() );
rms_ipa_assert( ! is_wp_error( $relock ) && ! empty( $relock['success'] ), 'relock succeeds' );
rms_ipa_assert( true === ( new State_Manager() )->has_completion_flag(), 'completion flag preserved' );
rms_ipa_assert( true === ( new State_Manager() )->is_completed(), 're-lock restores read-only' );
rms_ipa_assert( false === ( new Wizard_Mutation_Fence() )->is_held(), 'fence released after skip/relock' );
echo "PASS combined-skip-relock-completion-preservation\n";
++$passed;

echo 'Harness passed: ' . $passed . " scenarios.\n";
