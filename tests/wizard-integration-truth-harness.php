<?php
/**
 * Combined integration proof for merged #22 / #25 / #26 / #27 client + controller.
 *
 * Proves a normal dependency failure is not success, a healthy landing
 * `running` response is success/progress and never displays "Step completed",
 * API-key clearing still occurs in the same merged client, Home SEO drafts
 * survive other-step success, Home success resets targeting, and homepage
 * keyword intent stays Hero/SEO only (out of canonical/landing).
 *
 * Usage: php tests/wizard-integration-truth-harness.php
 */

if ( PHP_SAPI !== 'cli' ) {
	fwrite( STDERR, "CLI only.\n" );
	exit( 1 );
}

/**
 * @param mixed $condition
 */
function rms_integration_assert( $condition, string $message ): void {
	if ( ! $condition ) {
		fwrite( STDERR, $message . "\n" );
		exit( 1 );
	}
}

$theme_root = dirname( __DIR__ );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', $theme_root . '/' );
}

require_once $theme_root . '/inc/wizard/class-step-controller.php';
require_once $theme_root . '/inc/wizard/class-ai-content-harness.php';

$passed = 0;

rms_integration_assert(
	false === \Inc\Wizard\Step_Controller::response_success_from_status( 'failed', true ),
	'dependency/terminal failure must not be success'
);
rms_integration_assert(
	false === \Inc\Wizard\Step_Controller::response_success_from_status( 'pending', true ),
	'pending dependency status must not be success'
);
echo "PASS dependency-failure-not-success\n";
$passed++;

rms_integration_assert(
	true === \Inc\Wizard\Step_Controller::response_success_from_status( 'running', true ),
	'healthy persisted running must stay success:true'
);
rms_integration_assert(
	true === \Inc\Wizard\Step_Controller::response_success_from_status( '', false ),
	'landing start/process (no progress write) must stay success:true'
);
echo "PASS landing-running-is-success\n";
$passed++;

$controller = file_get_contents( $theme_root . '/inc/wizard/class-step-controller.php' );
rms_integration_assert( is_string( $controller ), 'controller source missing' );
rms_integration_assert(
	false !== strpos( $controller, "in_array( \$landing_action, [ 'start', 'process' ], true )" ),
	'controller must keep #27 start/process lock/lease routing'
);
rms_integration_assert(
	false !== strpos( $controller, 'response_success_from_status' ),
	'controller must keep #22 truthful success mapping'
);
rms_integration_assert(
	false !== strpos( $controller, 'get_public_run' ),
	'controller must expose #27 public landing run state'
);
rms_integration_assert(
	false !== strpos( $controller, 'is_landing_skip_all' ),
	'controller must skip the running write for skip-all'
);
rms_integration_assert(
	false !== strpos( $controller, 'Wizard_Mutation_Fence' ),
	'controller must hold the site-wide mutation fence across execute_step'
);
rms_integration_assert(
	false !== strpos( $controller, '$mutation_fence->acquire()' ),
	'controller must acquire the mutation fence before status writes'
);
rms_integration_assert(
	false !== strpos( $controller, '$mutation_fence->release( (string) $fence_owner )' ),
	'controller must release the mutation fence in finally'
);
$exec_pos    = strpos( $controller, 'function execute_step' );
$complete_at = strpos( $controller, 'function complete(' );
$exec_src    = ( false !== $exec_pos && false !== $complete_at ) ? substr( $controller, $exec_pos, $complete_at - $exec_pos ) : '';
$finally_at  = strpos( $exec_src, '} finally {' );
$finally_src = false === $finally_at ? '' : substr( $exec_src, $finally_at );
$lock_at     = strpos( $finally_src, 'release_lock' );
$fence_at    = strpos( $finally_src, '$mutation_fence->release' );
$owner_at    = strpos( $finally_src, '$this->mutation_fence_owner = \'\'' );
rms_integration_assert(
	substr_count( $finally_src, 'finally' ) >= 2,
	'execute_step must nest fence release so legacy lock failures cannot leak the fence'
);
rms_integration_assert(
	false !== $lock_at && false !== $fence_at && $lock_at < $fence_at,
	'execute_step must clear the legacy lock before releasing the mutation fence'
);
rms_integration_assert(
	false !== $owner_at && false !== $fence_at && $fence_at < $owner_at,
	'execute_step must keep the instance owner until after the CAS release'
);
$complete_pos  = strpos( $controller, 'function complete(' );
$complete_acq  = false === $complete_pos ? false : strpos( $controller, '$mutation_fence->acquire()', $complete_pos );
$complete_mark = false === $complete_pos ? false : strpos( $controller, 'mark_completed()', $complete_pos );
rms_integration_assert(
	false !== $complete_acq && false !== $complete_mark && $complete_acq < $complete_mark,
	'complete() must acquire the mutation fence before writing completed'
);
rms_integration_assert(
	false !== strpos( $controller, 'with_public_landing_run' ),
	'resume state must gate stale lease recovery behind the mutation fence'
);
echo "PASS controller-merge-wiring\n";
$passed++;

$landing_builder = file_get_contents( $theme_root . '/inc/wizard/class-step-landing-page-builder.php' );
rms_integration_assert( is_string( $landing_builder ), 'landing builder source missing' );
rms_integration_assert(
	false !== strpos( $landing_builder, 'allows_skip_all' ),
	'skip-all must consult the blocking-run/active-lease guard'
);
rms_integration_assert(
	false !== strpos( $landing_builder, "'status' => 409" ),
	'skip-all conflict must remain a deterministic 409'
);
$orchestrator = file_get_contents( $theme_root . '/inc/wizard/class-landing-run-orchestrator.php' );
rms_integration_assert( is_string( $orchestrator ), 'orchestrator source missing' );
rms_integration_assert(
	false !== strpos( $orchestrator, 'RUN_SKIPPED' ),
	'orchestrator must treat skipped like completed for skip-all permission'
);
rms_integration_assert(
	false !== strpos( $orchestrator, 'function allows_skip_all' ),
	'orchestrator must expose allows_skip_all'
);
rms_integration_assert(
	false !== strpos( $orchestrator, 'has_active_start_fence' ),
	'allows_skip_all must consult the active start fence'
);
echo "PASS skip-all-blocking-run-guard\n";
$passed++;

$wizard = file_get_contents( $theme_root . '/src/ts/admin/wizard.ts' );
rms_integration_assert( is_string( $wizard ), 'wizard.ts missing' );
rms_integration_assert( false !== strpos( $wizard, "from './wizard-helpers'" ), 'merged client must import wizard-helpers' );
rms_integration_assert( false !== strpos( $wizard, "from './landing-run-helpers'" ), 'merged client must import landing-run-helpers' );
rms_integration_assert( false !== strpos( $wizard, "from './wizard-home-seo'" ), 'merged client must import homepage SEO helpers' );
rms_integration_assert( false !== strpos( $wizard, 'presentStepOutcome' ), 'merged client must present truthful outcomes' );
rms_integration_assert( false !== strpos( $wizard, 'applyApiKeyInputSafety' ), 'merged client must clear credentials through the helper' );
rms_integration_assert( false !== strpos( $wizard, 'is still in progress' ), 'merged client must keep running as progress copy' );
rms_integration_assert( false !== strpos( $wizard, 'resolveLandingClientRequest' ), 'merged client must keep #27 request routing' );
rms_integration_assert( false !== strpos( $wizard, 'applyStepOutcomePresentation' ), 'merged client must share one outcome presenter' );
rms_integration_assert( false !== strpos( $wizard, 'shouldReplaceHomeSeoOnStepFinish' ), 'merged client must use Home SEO replacement helper' );
rms_integration_assert( false !== strpos( $wizard, 'createHomeSeoValidationError' ), 'merged client must throw explicit Home SEO validation errors' );
echo "PASS merged-client-wiring\n";
$passed++;

$state_manager = file_get_contents( $theme_root . '/inc/wizard/class-state-manager.php' );
$wizard_init   = file_get_contents( $theme_root . '/inc/wizard/wizard-init.php' );
rms_integration_assert( is_string( $state_manager ), 'state manager source missing' );
rms_integration_assert( false !== strpos( $state_manager, "'home_seo_targeting'" ), 'state defaults must include home_seo_targeting' );
rms_integration_assert( false !== strpos( $state_manager, "'landing_run'" ), 'state defaults must keep landing_run' );
rms_integration_assert( is_string( $wizard_init ), 'wizard-init source missing' );
rms_integration_assert( false !== strpos( $wizard_init, 'data-wizard-landing-run-progress' ), 'wizard-init must keep landing progress UI' );
rms_integration_assert( false !== strpos( $wizard_init, 'data-wizard-home-seo-enabled' ), 'wizard-init must expose Home SEO controls' );
rms_integration_assert( false !== strpos( $wizard_init, 'data-wizard-home-seo-primary-error' ), 'wizard-init must expose Home SEO primary error' );
echo "PASS merged-state-and-ui-defaults\n";
$passed++;

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) {
		$str = preg_replace( '/[\r\n\t]+/', ' ', (string) $str );
		$str = preg_replace( '/\s+/', ' ', is_string( $str ) ? $str : '' );

		return trim( strip_tags( is_string( $str ) ? $str : '' ) );
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) ) );
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data, $options = 0 ) {
		return json_encode( $data, $options );
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = null ) {
		unset( $domain );

		return $text;
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook, $value ) {
		unset( $hook );

		return $value;
	}
}

if ( ! class_exists( 'WP_Error', false ) ) {
	class WP_Error {
		public $code;
		public $message;
		public $data;

		public function __construct( $code = '', $message = '', $data = '' ) {
			$this->code    = $code;
			$this->message = $message;
			$this->data    = $data;
		}

		public function get_error_code() {
			return $this->code;
		}

		public function get_error_message() {
			return $this->message;
		}
	}
}

$harness = new \Inc\Wizard\AI_Content_Harness();
$enabled = $harness->normalize_home_seo_intent(
	[
		'seo_targeting' => [
			'enabled'         => true,
			'primary_keyword' => 'deck builder',
		],
	]
);
rms_integration_assert( ! is_wp_error( $enabled ), 'homepage SEO intent should normalize' );
$hero_keywords  = $harness->home_seo_keywords_for_layout( $enabled, 'hero' );
$about_keywords = $harness->home_seo_keywords_for_layout( $enabled, 'about-us' );
$client         = [ 'company_name' => 'Acme Builders' ];
$hero_prompt    = $harness->get_layer3( 'hero', 1, $client, \Inc\Wizard\AI_Content_Harness::PAGE_HOME, $hero_keywords );
$about_prompt   = $harness->get_layer3( 'about-us', 1, $client, \Inc\Wizard\AI_Content_Harness::PAGE_HOME, $hero_keywords );
$landing_hero   = $harness->get_layer3( 'hero', 1, $client, \Inc\Wizard\AI_Content_Harness::PAGE_LANDING, [ 'primary_keyword' => 'kitchen remodel near me' ] );
rms_integration_assert( 'deck builder' === ( $hero_keywords['primary_keyword'] ?? '' ), 'hero must receive homepage keyword' );
rms_integration_assert( [] === $about_keywords, 'non-hero/seo layouts must not receive homepage keywords' );
rms_integration_assert( false !== strpos( $hero_prompt, 'KEYWORD INTENT (editorial only, this section only)' ), 'home hero must use homepage keyword contract' );
rms_integration_assert( false === strpos( $about_prompt, 'deck builder' ), 'about-us must stay free of homepage keywords' );
rms_integration_assert( false !== strpos( $landing_hero, 'KEYWORD CONTEXT (mandatory for this section only)' ), 'landing keyword contract must stay unchanged' );
rms_integration_assert( false === strpos( $landing_hero, 'KEYWORD INTENT (editorial only' ), 'landing must not pick up homepage keyword contract' );
rms_integration_assert( ! $harness->is_reusable_layout( 'hero' ), 'hero must stay out of canonical' );
rms_integration_assert( ! $harness->is_reusable_layout( 'seo-content' ), 'seo-content must stay out of canonical' );
$landing_builder = file_get_contents( $theme_root . '/inc/wizard/class-step-landing-page-builder.php' );
rms_integration_assert( is_string( $landing_builder ) && false === strpos( $landing_builder, 'normalize_home_seo_intent' ), 'landing builder must not depend on homepage SEO intent' );
echo "PASS home-keyword-hero-seo-only\n";
$passed++;

$node = getenv( 'RMS_HARNESS_NODE' );
if ( ! is_string( $node ) || '' === $node ) {
	$node = 'node';
}

$client_script = $theme_root . '/tests/wizard-integration-client-truth.mjs';
$cmd           = '"' . $node . '" ' . escapeshellarg( $client_script );
$output        = array();
$code          = 0;
exec( $cmd, $output, $code );
$text = implode( "\n", $output );
rms_integration_assert( 0 === $code, "merged client helper proof failed:\n{$text}" );
echo $text . "\n";
echo "PASS merged-client-runtime\n";
$passed++;

echo 'Harness passed: ' . $passed . " scenarios.\n";
exit( 0 );
