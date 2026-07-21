<?php
/**
 * Wizard step orchestration controller.
 *
 * @package Simple_RMS_Theme
 */

namespace Inc\Wizard;

defined( 'ABSPATH' ) || exit;

/**
 * Coordinates wizard step services, resume state, access checks, and locks.
 */
class Step_Controller {
	private const LOCK_NAME = 'step_execution';

	/**
	 * Pseudo-steps allowed while the wizard is completed/locked.
	 * These must never write current_step or step_status.
	 *
	 * @var string[]
	 */
	private const COMPLETED_GATE_ALLOWLIST = [
		'unlock',
		'relock',
	];

	/**
	 * Single source of truth for currently required wizard steps.
	 * Consumed by complete() and step services (e.g. maybe_mark_completed).
	 *
	 * PR2 keeps the existing 7-step completion surface. `landing-page-builder`
	 * backend class exists, but required/dispatch/UI activation is deferred to
	 * Phase 3 (menu/SEO/noindex + admin UI) so Ads landings cannot go live
	 * without final-state controls and the UI cannot show 7/7 while an 8th
	 * invisible step is required.
	 *
	 * @var string[]
	 */
	private const REQUIRED_STEPS = [
		'dependencies',
		'acf-import',
		'client-data',
		'generate-pages',
		'menu-setup',
		'ia-generation',
		'home-page-builder',
	];

	/**
	 * Steps that may be dispatched by execute_step() right now.
	 * Must stay in sync with dispatch_step() cases.
	 *
	 * PR2: `landing-page-builder` is intentionally NOT dispatchable until
	 * Phase 3 wires UI + noindex/menu final-state sync. The dispatch case and
	 * `Step_Landing_Page_Builder` class remain for that activation.
	 *
	 * @var string[]
	 */
	private const DISPATCHABLE_STEPS = [
		'dependencies',
		'acf-import',
		'client-data',
		'generate-pages',
		'menu-setup',
		'ia-generation',
		'home-page-builder',
		'unlock',
		'relock',
	];

	private $state_manager;
	private $logger;

	public function __construct( ?State_Manager $state_manager = null, ?Logger $logger = null ) {
		$this->state_manager = $state_manager ?? new State_Manager();
		$this->logger        = $logger ?? new Logger();
	}

	/**
	 * Shared required-step list used for completion checks.
	 *
	 * @return string[]
	 */
	public static function get_required_steps(): array {
		return self::REQUIRED_STEPS;
	}

	/**
	 * Verify the current user can access the wizard.
	 */
	public function can_access(): bool {
		return \current_user_can( 'manage_options' );
	}

	/**
	 * Return the resumable wizard state with lock and log metadata.
	 *
	 * @return array<string,mixed>
	 */
	public function get_resume_state(): array {
		$state                         = $this->state_manager->get_state();
		$state['locked']               = $this->state_manager->is_completed();
		$state['completed_flag']       = $this->state_manager->has_completion_flag();
		$state['force_unlocked']       = Wizard_Unlock_Controller::is_force_unlocked();
		$state['controlled_unlock_ui'] = Wizard_Unlock_Controller::is_controlled_unlock_enabled();
		$state['unlocked']             = Wizard_Unlock_Controller::is_unlocked();
		$state['has_unlock_marker']    = Wizard_Unlock_Controller::has_unlock_marker();
		$state['unlocked_at']          = (string) \get_option( Wizard_Unlock_Controller::UNLOCKED_AT_OPTION, '' );
		$state['unlocked_by']          = (int) \get_option( Wizard_Unlock_Controller::UNLOCKED_BY_OPTION, 0 );
		$state['logs']                 = $this->logger->all();

		return $state;
	}

	/**
	 * Execute a wizard step action.
	 *
	 * @param string $step    Step slug.
	 * @param array  $payload Request payload.
	 *
	 * @return array<string,mixed>|\WP_Error
	 */
	public function execute_step( string $step, array $payload = [] ) {
		if ( ! $this->can_access() ) {
			return new \WP_Error( 'rms_wizard_forbidden', \__( 'You do not have permission to execute the setup wizard.', 'simple-rms-theme' ), [ 'status' => 403 ] );
		}

		$step = $this->normalize_step( $step );

		// Reject unknown/unimplemented steps before lock or status writes so
		// premature aliases (e.g. landing-page-builder before Phase 2 runtime)
		// cannot pollute current_step / step_status.
		if ( ! $this->is_dispatchable_step( $step ) ) {
			return new \WP_Error( 'rms_wizard_unknown_step', \__( 'Unknown setup wizard step.', 'simple-rms-theme' ), [ 'status' => 400 ] );
		}

		// unlock/relock are the only pseudo-steps allowed through the completed gate.
		if ( $this->state_manager->is_completed() && ! $this->is_completed_gate_allowlisted( $step ) ) {
			return new \WP_Error( 'rms_wizard_locked', \__( 'The setup wizard is already complete.', 'simple-rms-theme' ), [ 'status' => 423 ] );
		}

		if ( ! $this->state_manager->acquire_lock( self::LOCK_NAME ) ) {
			return new \WP_Error( 'rms_wizard_busy', \__( 'Another setup wizard action is already running.', 'simple-rms-theme' ), [ 'status' => 409 ] );
		}

		$progress_status_written = false;

		try {
			// Pseudo-steps (unlock/relock) must not pollute current_step or step_status.
			if ( ! $this->is_completed_gate_allowlisted( $step ) ) {
				$this->state_manager->set_current_step( $step );
				$this->state_manager->set_step_status( $step, 'running' );
				$progress_status_written = true;
			}

			$result = $this->dispatch_step( $step, $payload );

			if ( \is_wp_error( $result ) ) {
				return $result;
			}

			return [
				'success' => true,
				'step'    => $step,
				'result'  => $result,
				'state'   => $this->get_resume_state(),
			];
		} catch ( \Throwable $throwable ) {
			// Progress steps must not remain "running" after an unexpected failure.
			// Unlock/relock never set progress status, so they skip this path.
			if ( $progress_status_written ) {
				$failed_saved = $this->state_manager->set_step_status( $step, 'failed' );

				if ( ! $failed_saved ) {
					$this->logger->log(
						'error',
						'Setup wizard could not mark step status as failed after unexpected error.',
						[
							'step'    => $step,
							'message' => $throwable->getMessage(),
						]
					);
				}
			}

			$this->logger->log(
				'error',
				'Setup wizard step threw an unexpected error.',
				[
					'step'    => $step,
					'message' => $throwable->getMessage(),
				]
			);

			return new \WP_Error(
				'rms_wizard_step_exception',
				\__( 'The setup wizard step failed unexpectedly.', 'simple-rms-theme' ),
				[ 'status' => 500 ]
			);
		} finally {
			$this->state_manager->release_lock( self::LOCK_NAME );
		}
	}

	/**
	 * Mark the wizard complete when all required steps are done.
	 *
	 * @return array<string,mixed>|\WP_Error
	 */
	public function complete() {
		if ( ! $this->can_access() ) {
			return new \WP_Error( 'rms_wizard_forbidden', \__( 'You do not have permission to complete the setup wizard.', 'simple-rms-theme' ), [ 'status' => 403 ] );
		}

		$state    = $this->state_manager->get_state();
		$required = self::get_required_steps();

		foreach ( $required as $step ) {
			if ( 'complete' !== ( $state['step_status'][ $step ] ?? '' ) ) {
				return new \WP_Error( 'rms_wizard_incomplete', \__( 'The setup wizard cannot be completed until every step succeeds.', 'simple-rms-theme' ), [ 'status' => 400 ] );
			}
		}

		$this->state_manager->mark_completed();
		$this->logger->log( 'info', 'Setup wizard marked complete.' );

		return [ 'success' => true, 'state' => $this->get_resume_state() ];
	}

	private function dispatch_step( string $step, array $payload ) {
		switch ( $step ) {
			case 'dependencies':
				$service = new Step_Dependencies( $this->logger, $this->state_manager );
				$result  = ! empty( $payload['install'] ) ? $service->install_missing() : $service->get_status();

				if ( empty( $payload['install'] ) ) {
					$this->state_manager->set_step_status( 'dependencies', $this->dependencies_are_active( $result ) ? 'complete' : 'pending' );
				}

				return $result;

			case 'acf-import':
				return ( new Step_ACF_Import( $this->logger, $this->state_manager ) )->import();

			case 'client-data':
				$data = is_array( $payload['client_data'] ?? null ) ? $payload['client_data'] : $payload;
				return ( new Step_Client_Data( $this->logger, $this->state_manager ) )->save( $data );

			case 'generate-pages':
				return ( new Step_Generate_Pages( $this->logger, $this->state_manager ) )->run( $payload );

			case 'menu-setup':
				return ( new Step_Menu_Setup( $this->logger, $this->state_manager ) )->run( $payload );

			case 'ia-generation':
				return $this->configure_ai_provider( $payload );

			case 'home-page-builder':
				return ( new Step_Home_Page_Builder( $this->logger, $this->state_manager ) )->run( $payload );

			// Kept for Phase 3 activation. Not reachable until REQUIRED_STEPS +
			// DISPATCHABLE_STEPS + UI re-include `landing-page-builder` together.
			case 'landing-page-builder':
				return ( new Step_Landing_Page_Builder( $this->logger, $this->state_manager ) )->run( $payload );

			case 'unlock':
				return ( new Wizard_Unlock_Controller( $this->state_manager, $this->logger ) )->unlock();

			case 'relock':
				return ( new Wizard_Unlock_Controller( $this->state_manager, $this->logger ) )->relock();

			default:
				// Defense in depth: execute_step() already rejects non-dispatchable
				// steps before status writes. Never mark unknown steps failed here.
				return new \WP_Error( 'rms_wizard_unknown_step', \__( 'Unknown setup wizard step.', 'simple-rms-theme' ), [ 'status' => 400 ] );
		}
	}

	/**
	 * Save AI provider configuration without generating content.
	 *
	 * @param array $payload Request payload.
	 *
	 * @return array<string,mixed>|\WP_Error
	 */
	private function configure_ai_provider( array $payload ) {
		$provider = \sanitize_key( (string) ( $payload['provider'] ?? AI_Provider_Registry::default_provider() ) );
		$api_key  = AI_Credential_Store::normalize_api_key( (string) ( $payload['api_key'] ?? '' ) );
		$model    = \sanitize_text_field( (string) ( $payload['model'] ?? '' ) );

		if ( ! AI_Provider_Registry::provider_exists( $provider ) ) {
			$this->state_manager->set_step_status( 'ia-generation', 'failed' );
			return new \WP_Error( 'rms_wizard_unknown_ai_provider', \__( 'Unknown AI provider selected.', 'simple-rms-theme' ), [ 'status' => 400 ] );
		}

		if ( '' === $model ) {
			$this->state_manager->set_step_status( 'ia-generation', 'failed' );
			return new \WP_Error( 'rms_wizard_missing_ai_config', \__( 'The IA Generation step requires a provider and model.', 'simple-rms-theme' ), [ 'status' => 400 ] );
		}

		if ( '' !== $api_key ) {
			/*
			 * Validate a newly supplied key via live model listing before persisting it.
			 * A successful list_models() response counts as explicit credential
			 * validation per the wizard-ai-providers spec (Provider Setup Gating).
			 * No new validate() method is added in v1 — list_models() is the contract.
			 */
			$validation = AI_Provider_Registry::make_provider( $provider, $api_key )->list_models();

			if ( \is_wp_error( $validation ) ) {
				$this->state_manager->set_step_status( 'ia-generation', 'failed' );

				return new \WP_Error(
					'rms_wizard_ai_key_invalid',
					\sprintf(
						/* translators: %s: provider validation failure message. */
						\__( 'The API key could not be validated: %s', 'simple-rms-theme' ),
						$validation->get_error_message()
					),
					[ 'status' => 400 ]
				);
			}

			try {
				AI_Credential_Store::save( $provider, $api_key );
			} catch ( \Throwable $error ) {
				$this->state_manager->set_step_status( 'ia-generation', 'failed' );
				return new \WP_Error( 'rms_wizard_ai_key_save_failed', \__( 'The API key could not be encrypted and saved.', 'simple-rms-theme' ), [ 'status' => 500 ] );
			}
		}

		$credential = AI_Credential_Store::status( $provider );

		if ( empty( $credential['has_key'] ) ) {
			$this->state_manager->set_step_status( 'ia-generation', 'failed' );
			return new \WP_Error( 'rms_wizard_missing_ai_credentials', \__( 'The IA Generation step requires saved provider credentials.', 'simple-rms-theme' ), [ 'status' => 400 ] );
		}

		$state              = $this->state_manager->get_state();
		$state['ai_config'] = [
			'provider'         => $provider,
			'provider_label'   => AI_Provider_Registry::get_provider_label( $provider ),
			'model'            => $model,
			'credential'       => $credential,
			'has_credentials'  => ! empty( $credential['has_key'] ),
			'configured_at'    => \current_time( 'mysql', true ),
		];

		$this->state_manager->save_state( $state );
		$this->state_manager->set_step_status( 'ia-generation', 'complete' );
		$this->logger->log( 'info', 'Wizard AI configuration saved.', [ 'provider' => $provider, 'model' => $model, 'has_credentials' => ! empty( $credential['has_key'] ) ] );

		return [ 'ai_config' => $state['ai_config'] ];
	}

	private function normalize_step( string $step ): string {
		$step = \sanitize_key( $step );

		$aliases = [
			'acf_import'        => 'acf-import',
			'client_data'       => 'client-data',
			'ai-generation'     => 'ia-generation',
			'ai_generation'     => 'ia-generation',
			'ia_generation'     => 'ia-generation',
			'generate_pages'    => 'generate-pages',
			'menu_setup'        => 'menu-setup',
			'home_page_builder' => 'home-page-builder',
			// Phase 3 activation: re-enable `landing_page_builder` alias together
			// with DISPATCHABLE_STEPS, REQUIRED_STEPS, dispatch case, and admin UI.
			// Alias alone is safe (unknown-step reject runs before status writes)
			// but must not be treated as "live" until that full activation set lands.
			'landing_page_builder' => 'landing-page-builder',
		];

		return $aliases[ $step ] ?? $step;
	}

	/**
	 * Whether a step may bypass the completed/locked gate.
	 */
	private function is_completed_gate_allowlisted( string $step ): bool {
		return in_array( $step, self::COMPLETED_GATE_ALLOWLIST, true );
	}

	/**
	 * Whether a normalized step has a live dispatch path.
	 */
	private function is_dispatchable_step( string $step ): bool {
		return in_array( $step, self::DISPATCHABLE_STEPS, true );
	}

	private function dependencies_are_active( array $status ): bool {
		if ( [] === $status ) {
			return false;
		}

		foreach ( $status as $plugin_status ) {
			if ( empty( $plugin_status['active'] ) ) {
				return false;
			}
		}

		return true;
	}
}
