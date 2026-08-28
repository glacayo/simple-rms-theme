<?php
/**
 * Hidden-dispatch activation proofs. These 8 cases must pass whether or not
 * internal-page-builder is in REQUIRED_STEPS.
 *
 * Usage: php tests/wizard-internal-page-activation-harness.php
 */
if ( PHP_SAPI !== 'cli' ) {
	fwrite( STDERR, "CLI only.\n" );
	exit( 1 );
}

require_once __DIR__ . '/wizard-internal-page-activation-bootstrap.php';

use Inc\Wizard\Step_Controller;
use Inc\Wizard\State_Manager;
use Inc\Wizard\Step_Internal_Page_Builder;
use Inc\Wizard\Wizard_Mutation_Fence;

$passed = 0;

$GLOBALS['_can'] = false;
$denied = ( new Step_Controller() )->execute_step( 'internal-page-builder', array( 'skip_all' => true ) );
rms_ipa_assert( is_wp_error( $denied ) && 'rms_wizard_forbidden' === $denied->get_error_code(), 'capability gate' );
$GLOBALS['_can'] = true;
echo "PASS capability-forbidden\n";
++$passed;

$unknown = ( new Step_Controller() )->execute_step( 'not-a-real-step', array() );
rms_ipa_assert( is_wp_error( $unknown ) && 'rms_wizard_unknown_step' === $unknown->get_error_code(), 'unknown step' );
echo "PASS unknown-step-rejected\n";
++$passed;

rms_ipa_reset();
( new State_Manager() )->mark_completed();
$locked = ( new Step_Controller() )->execute_step( 'internal-page-builder', array( 'skip_all' => true ) );
rms_ipa_assert( is_wp_error( $locked ) && 'rms_wizard_locked' === $locked->get_error_code(), 'completed sites stay locked' );
$unlock = ( new Step_Controller() )->execute_step( 'unlock', array() );
rms_ipa_assert( ! is_wp_error( $unlock ) && ! empty( $unlock['success'] ), 'unlock via execute_step' );
$after_unlock = ( new Step_Controller() )->execute_step( 'internal-page-builder', array( 'skip_all' => true ) );
rms_ipa_assert( ! is_wp_error( $after_unlock ) && ! empty( $after_unlock['success'] ), 'unlocked completed site can skip-all' );
echo "PASS completed-site-locked-until-unlock\n";
++$passed;

rms_ipa_reset();
rms_ipa_seed_about();
$meta_before  = $GLOBALS['_post_meta'];
$pages_before = ( new State_Manager() )->get_state()['generated_pages'];
$direct       = ( new Step_Internal_Page_Builder() )->run( array( 'skip_all' => true ) );
rms_ipa_assert( is_wp_error( $direct ) && 'rms_wizard_mutation_unfenced' === $direct->get_error_code(), 'direct run rejected' );
rms_ipa_assert( $meta_before === $GLOBALS['_post_meta'], 'direct skip-all writes no ACF' );
rms_ipa_assert( $pages_before === ( new State_Manager() )->get_state()['generated_pages'], 'direct skip-all writes no pages' );
rms_ipa_assert( 0 === $GLOBALS['_page_writes'], 'direct skip-all writes no posts' );
echo "PASS direct-builder-unfenced\n";
++$passed;

$skip = ( new Step_Controller() )->execute_step( 'internal-page-builder', array( 'skip_all' => true ) );
rms_ipa_assert( ! is_wp_error( $skip ) && ! empty( $skip['success'] ) && ! empty( $skip['result']['skipped'] ), 'skip-all through execute_step' );
rms_ipa_assert( 'complete' === ( ( new State_Manager() )->get_state()['step_status']['internal-page-builder'] ?? '' ), 'skip-all completes step' );
rms_ipa_assert( $meta_before === $GLOBALS['_post_meta'], 'seeded skip-all writes no ACF' );
rms_ipa_assert( $pages_before === ( new State_Manager() )->get_state()['generated_pages'], 'seeded skip-all writes no generated pages' );
rms_ipa_assert( 0 === $GLOBALS['_page_writes'], 'seeded skip-all writes no posts' );
rms_ipa_assert( $GLOBALS['_wpdb_inserts'] > 0, 'mutation fence acquired' );
$held = ( new Wizard_Mutation_Fence() )->is_held();
rms_ipa_assert( false === $held, 'fence released after skip-all' );
echo "PASS skip-all-via-controller-fence\n";
++$passed;

rms_ipa_reset();
rms_ipa_seed_about();
$start = ( new Step_Controller() )->execute_step( 'internal-page-builder', array( 'action' => 'start' ) );
rms_ipa_assert( ! is_wp_error( $start ) && 'running' === ( $start['state']['step_status']['internal-page-builder'] ?? '' ), 'authorized start is running' );
$forged = ( new Step_Controller() )->execute_step(
	'internal-page-builder',
	array(
		'action'    => 'process',
		'page_type' => 'services',
		'post_id'   => 99,
		'slug'      => 'home',
	)
);
rms_ipa_assert( is_wp_error( $forged ) && 'rms_wizard_internal_identity' === $forged->get_error_code(), 'forged identity rejected' );
rms_ipa_assert( 0 === $GLOBALS['_page_writes'], 'forged identity writes no posts' );
rms_ipa_assert( 'Keep this copy' === ( $GLOBALS['_post_meta'][12]['page_sections'][0]['about_headline'] ?? '' ), 'forged identity writes no ACF' );
$after_fail = ( new Wizard_Mutation_Fence() )->is_held();
rms_ipa_assert( false === $after_fail, 'fence released after identity failure' );
$stale_owner = ( new Wizard_Mutation_Fence() );
$stale_owner->release( 'forged-owner' );
rms_ipa_assert( false === ( new Wizard_Mutation_Fence() )->is_held(), 'stale owner cannot keep the fence' );
echo "PASS forged-identity-zero-writes\n";
++$passed;

foreach ( array( 'pending', 'failed', 'complete' ) as $prior ) {
	rms_ipa_reset();
	rms_ipa_seed_about();
	$sm = new State_Manager();
	$st = $sm->get_state();
	$st['step_status']['internal-page-builder'] = $prior;
	if ( 'complete' === $prior ) {
		$st['internal_pages']['about']['status'] = 'complete';
	}
	if ( 'failed' === $prior ) {
		$st['internal_pages']['about']['status'] = 'failed';
		$st['internal_pages']['about']['reason'] = 'persist_failed';
	}
	$sm->save_state( $st );
	$meta_before = $GLOBALS['_post_meta'];
	$err         = ( new Step_Controller() )->execute_step(
		'internal-page-builder',
		array(
			'action'    => 'process',
			'page_type' => 'services',
			'post_id'   => 99,
		)
	);
	$actual = (string) ( ( new State_Manager() )->get_state()['step_status']['internal-page-builder'] ?? '' );
	rms_ipa_assert( is_wp_error( $err ) && 'rms_wizard_internal_identity' === $err->get_error_code(), 'identity error for prior ' . $prior );
	rms_ipa_assert( $prior === $actual, 'identity restores ' . $prior . ' not running' );
	rms_ipa_assert( 0 === $GLOBALS['_page_writes'] && $meta_before === $GLOBALS['_post_meta'], 'identity keeps pages for ' . $prior );
	rms_ipa_assert( false === ( new Wizard_Mutation_Fence() )->is_held(), 'fence released after identity ' . $prior );
}
echo "PASS identity-restores-prior-status\n";
++$passed;

rms_ipa_reset();
$held_owner = ( new Wizard_Mutation_Fence() )->acquire();
rms_ipa_assert( ! is_wp_error( $held_owner ), 'outer fence acquired' );
$nested = ( new Step_Controller() )->execute_step( 'internal-page-builder', array( 'skip_all' => true ) );
rms_ipa_assert( is_wp_error( $nested ) && 'rms_wizard_busy' === $nested->get_error_code(), 'nested execute_step is busy' );
rms_ipa_assert( ! isset( ( new State_Manager() )->get_state()['step_status']['internal-page-builder'] ) || 'complete' !== ( ( new State_Manager() )->get_state()['step_status']['internal-page-builder'] ?? '' ), 'nested busy writes no complete status' );
( new Wizard_Mutation_Fence() )->release( (string) $held_owner );
echo "PASS nested-fence-zero-writes\n";
++$passed;

echo 'Harness passed: ' . $passed . " scenarios.\n";
