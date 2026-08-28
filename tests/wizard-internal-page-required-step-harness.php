<?php
/**
 * Ninth-step REQUIRED_STEPS proofs. These 2 cases require
 * internal-page-builder in Step_Controller::REQUIRED_STEPS.
 *
 * Usage: php tests/wizard-internal-page-required-step-harness.php
 */
if ( PHP_SAPI !== 'cli' ) {
	fwrite( STDERR, "CLI only.\n" );
	exit( 1 );
}

require_once __DIR__ . '/wizard-internal-page-activation-bootstrap.php';

use Inc\Wizard\Step_Controller;
use Inc\Wizard\State_Manager;

$passed = 0;
$required = Step_Controller::get_required_steps();
$landing_at = array_search( 'landing-page-builder', $required, true );
$internal_at = array_search( 'internal-page-builder', $required, true );
rms_ipa_assert( false !== $landing_at && false !== $internal_at && $internal_at === $landing_at + 1, 'internal follows landing' );
echo "PASS required-order-after-landing\n";
++$passed;

rms_ipa_reset();
$sm = new State_Manager();
$st = $sm->get_state();
foreach ( Step_Controller::get_required_steps() as $step ) {
	$st['step_status'][ $step ] = 'complete';
}
$st['step_status']['internal-page-builder'] = 'failed';
$sm->save_state( $st );
$incomplete = ( new Step_Controller() )->complete();
rms_ipa_assert( is_wp_error( $incomplete ) && 'rms_wizard_incomplete' === $incomplete->get_error_code(), 'complete wizard refuses failed internal step' );
rms_ipa_assert( false === ( new State_Manager() )->has_completion_flag(), 'failed step does not lock the wizard' );
$st['step_status']['internal-page-builder'] = 'complete';
$sm->save_state( $st );
$done = ( new Step_Controller() )->complete();
rms_ipa_assert( ! is_wp_error( $done ) && ! empty( $done['success'] ), 'explicit complete wizard succeeds when every step is complete' );
rms_ipa_assert( true === ( new State_Manager() )->has_completion_flag(), 'explicit complete sets the completion flag' );
echo "PASS explicit-complete-wizard\n";
++$passed;

echo 'Harness passed: ' . $passed . " scenarios.\n";
