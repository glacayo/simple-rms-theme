<?php
/**
 * Controller persistence-failure fence release.
 *
 * Usage: php tests/wizard-internal-page-controller-coverage-harness.php
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
rms_ipa_reset();
$post = new WP_Post( 12 );
$post->post_name = 'about';
$GLOBALS['_posts'][12] = $post;
$sm = new State_Manager();
$st = $sm->get_state();
$st['generated_pages'] = array( array( 'id' => 12, 'slug' => 'about', 'type' => 'about' ) );
$sm->save_state( $st );
$start = ( new Step_Controller() )->execute_step( 'internal-page-builder', array( 'action' => 'start' ) );
rms_ipa_assert( ! is_wp_error( $start ), 'start succeeds' );
$GLOBALS['_fail_build'] = true;
$fail = ( new Step_Controller() )->execute_step( 'internal-page-builder', array( 'action' => 'process' ) );
rms_ipa_assert( ! is_wp_error( $fail ), 'persistence failure is a page result not a transport error' );
rms_ipa_assert( 'failed' === ( $fail['result']['status'] ?? '' ) && 'persist_failed' === ( $fail['result']['reason'] ?? '' ), 'page is failed after persist' );
rms_ipa_assert( false === ( new Wizard_Mutation_Fence() )->is_held(), 'fence released after persistence failure' );
$locks = ( new State_Manager() )->get_state()['locks'] ?? array();
rms_ipa_assert( empty( $locks ), 'state lock released after persistence failure' );
echo "PASS lock-released-after-persistence-failure\n";
++$passed;
echo 'Harness passed: ' . $passed . " scenarios.\n";
