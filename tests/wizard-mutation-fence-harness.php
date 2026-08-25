<?php
/**
 * Source + constant proof for the site-wide wizard mutation fence.
 *
 * Production-path execute_step / start-vs-skip / cleanup-order proofs live in
 * scripts/test-landing-run-orchestrator.php Tests 24-26.
 *
 * Usage: php tests/wizard-mutation-fence-harness.php
 */

if ( PHP_SAPI !== 'cli' ) {
	fwrite( STDERR, "CLI only.\n" );
	exit( 1 );
}

/**
 * @param mixed $condition
 */
function rms_fence_assert( $condition, string $message ): void {
	if ( ! $condition ) {
		fwrite( STDERR, $message . "\n" );
		exit( 1 );
	}
}

$theme_root = dirname( __DIR__ );

$fence_src       = file_get_contents( $theme_root . '/inc/wizard/class-wizard-mutation-fence.php' );
$controller_src  = file_get_contents( $theme_root . '/inc/wizard/class-step-controller.php' );
$orchestrator_src = file_get_contents( $theme_root . '/inc/wizard/class-landing-run-orchestrator.php' );
$home_src        = file_get_contents( $theme_root . '/inc/wizard/class-step-home-page-builder.php' );
$generate_src    = file_get_contents( $theme_root . '/inc/wizard/class-step-generate-pages.php' );
$menu_src        = file_get_contents( $theme_root . '/inc/wizard/class-step-menu-setup.php' );

rms_fence_assert( is_string( $fence_src ), 'mutation fence class missing' );
rms_fence_assert( false !== strpos( $fence_src, 'INSERT IGNORE' ), 'fence must use INSERT IGNORE' );
rms_fence_assert( false !== strpos( $fence_src, 'option_name = %s AND option_value = %s' ), 'fence must CAS-delete the exact serialized value' );
rms_fence_assert( false !== strpos( $fence_src, 'wp_cache_delete' ), 'fence must invalidate the option cache' );
rms_fence_assert( false !== strpos( $fence_src, "OPTION_NAME = 'rms_wizard_mutation_fence'" ), 'fence option name must be fixed' );
rms_fence_assert( false !== strpos( $fence_src, 'TTL = 1320' ), 'fence TTL must stay strictly greater than 1200+60' );
rms_fence_assert( false !== strpos( $fence_src, 'rms_wizard_busy' ), 'fence conflict must keep rms_wizard_busy' );
rms_fence_assert( false === strpos( $fence_src, 'error_log' ), 'fence must not log owner tokens' );
echo "PASS fence-primitive-contract\n";

rms_fence_assert( is_string( $controller_src ), 'controller source missing' );
rms_fence_assert( false !== strpos( $controller_src, 'new Wizard_Mutation_Fence()' ), 'controller must construct the mutation fence' );
rms_fence_assert( false !== strpos( $controller_src, '$mutation_fence->acquire()' ), 'controller must acquire the fence before status writes' );
rms_fence_assert( false !== strpos( $controller_src, '$mutation_fence->release( (string) $fence_owner )' ), 'controller must release only the opaque owner token' );
rms_fence_assert( false !== strpos( $controller_src, "in_array( \$landing_action, [ 'start', 'process' ], true )" ), 'controller must keep #27 start/process routing' );
rms_fence_assert( false !== strpos( $controller_src, 'acquire_lock( self::LOCK_NAME )' ), 'legacy UI state lock may remain for compatibility' );
$exec_pos     = strpos( $controller_src, 'function execute_step' );
$complete_at  = strpos( $controller_src, 'function complete(' );
$exec_src     = ( false !== $exec_pos && false !== $complete_at ) ? substr( $controller_src, $exec_pos, $complete_at - $exec_pos ) : '';
$finally_at   = strpos( $exec_src, '} finally {' );
$finally_src  = false === $finally_at ? '' : substr( $exec_src, $finally_at );
$lock_at      = strpos( $finally_src, 'release_lock' );
$fence_at     = strpos( $finally_src, '$mutation_fence->release' );
$owner_at     = strpos( $finally_src, '$this->mutation_fence_owner = \'\'' );
rms_fence_assert( substr_count( $finally_src, 'finally' ) >= 2, 'execute_step must nest fence release in a fail-safe finally' );
rms_fence_assert( false !== $lock_at && false !== $fence_at && $lock_at < $fence_at, 'execute_step must release the legacy lock before the mutation fence' );
rms_fence_assert( false !== $owner_at && false !== $fence_at && $fence_at < $owner_at, 'execute_step must keep the instance owner until after the CAS release' );
$complete_pos = strpos( $controller_src, 'function complete(' );
$complete_acq = false === $complete_pos ? false : strpos( $controller_src, '$mutation_fence->acquire()', $complete_pos );
$complete_mark = false === $complete_pos ? false : strpos( $controller_src, 'mark_completed()', $complete_pos );
rms_fence_assert( false !== $complete_acq && false !== $complete_mark && $complete_acq < $complete_mark, 'complete() must acquire the fence before writing the completion flag' );
rms_fence_assert( false !== strpos( $controller_src, 'with_public_landing_run' ), 'GET resume must gate stale recovery behind the mutation fence' );
$resume_pos = strpos( $controller_src, 'function get_resume_state' );
$resume_end = false === $resume_pos ? false : strpos( $controller_src, 'function execute_step', $resume_pos );
$resume_body = ( false !== $resume_pos && false !== $resume_end ) ? substr( $controller_src, $resume_pos, $resume_end - $resume_pos ) : '';
rms_fence_assert( '' !== $resume_body && false === strpos( $resume_body, 'recover_stale_lease' ), 'standalone get_resume_state() must not recover a stale lease before owning the fence' );
rms_fence_assert( false !== strpos( $controller_src, 'recover_stale_lease' ), 'stale recovery must still exist on the owned-fence path' );
echo "PASS controller-fence-hold\n";

rms_fence_assert( is_string( $orchestrator_src ), 'orchestrator source missing' );
rms_fence_assert( false !== strpos( $orchestrator_src, 'has_active_start_fence' ), 'skip-all must treat an active start fence as blocking' );
rms_fence_assert( false !== strpos( $orchestrator_src, 'function allows_skip_all' ), 'orchestrator must keep allows_skip_all' );
echo "PASS start-fence-skip-guard\n";

rms_fence_assert( is_string( $home_src ) && false !== strpos( $home_src, 'save_state( $fresh )' ), 'Home must re-read state before save' );
rms_fence_assert( is_string( $generate_src ) && false !== strpos( $generate_src, 'save_state( $fresh )' ), 'Generate must re-read state before save' );
rms_fence_assert( is_string( $menu_src ) && false !== strpos( $menu_src, 'save_state( $fresh )' ), 'Menu must re-read state before save' );
echo "PASS owned-key-fresh-save\n";

$passed = 4;
echo 'Harness passed: ' . $passed . " scenarios.\n";
exit( 0 );
