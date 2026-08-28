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
	 * Phase 8 activates `internal-page-builder` atomically with admin UI.
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
		'landing-page-builder',
		'internal-page-builder',
	];

	/**
	 * Steps that may be dispatched by execute_step() right now.
	 * Must stay in sync with dispatch_step() cases.
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
		'landing-page-builder',
		'internal-page-builder',
		'unlock',
		'relock',
	];

	private $state_manager;
	private $logger;

	/**
	 * Opaque mutation-fence owner for this controller instance, if any.
	 * Never expose this token in responses, errors, or logs.
	 */
	private $mutation_fence_owner = '';

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
	 * Map the persisted step status to the REST `success` flag.
	 *
	 * Normal terminal outcomes:
	 * - complete → true
	 * - failed / pending / unknown → false
	 *
	 * Non-terminal / special:
	 * - no progress write (unlock/relock, landing start/process) → true
	 * - running → true (healthy in-progress; issue #27 must keep this)
	 *
	 * The client must show "Step completed" only when status is complete.
	 * A `running` + success:true response is progress, never green-complete
	 * and never a false failure.
	 */
	public static function response_success_from_status( string $status, bool $progress_status_written ): bool {
		if ( ! $progress_status_written ) {
			return true;
		}

		return in_array( $status, [ 'complete', 'running' ], true );
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

		return $this->with_public_landing_run( $state );
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

		// Site-wide mutation fence is the concurrency authority for every
		// mutating execute_step() dispatch, including landing start/process
		// and long Home/Generate/Menu/IA work. Acquire before any status or
		// lock writes so a conflict cannot paint a step failed.
		$mutation_fence = new Wizard_Mutation_Fence();
		$fence_owner    = $mutation_fence->acquire();

		if ( \is_wp_error( $fence_owner ) ) {
			return $fence_owner;
		}

		$this->enter_authorized_mutation_scope( (string) $fence_owner );

		// Orchestrated landing actions keep the per-run lease for run ownership.
		// The legacy UI state lock is compatibility-only and is not acquired for
		// start/process so it cannot expire mid-build or overwrite newer state.
		$is_landing_orchestrated = false;
		$is_landing_skip_all     = false;
		$legacy_lock_held        = false;

		if ( 'landing-page-builder' === $step ) {
			$landing_action = \sanitize_key( (string) ( $payload['landing_action'] ?? '' ) );

			if ( in_array( $landing_action, [ 'start', 'process' ], true ) ) {
				$is_landing_orchestrated = true;
			}

			// Skip-all must not write running before the blocking-run guard.
			// Direct REST skip_all has no landing_action; start+skip_all is
			// already orchestrated. Process never skip-alls.
			if ( $this->payload_is_truthy( $payload['skip_all'] ?? false ) && 'process' !== $landing_action ) {
				$is_landing_skip_all = true;
			}
		}

		$is_internal_skip_all = 'internal-page-builder' === $step
			&& $this->payload_is_truthy( $payload['skip_all'] ?? false );

		if ( ! $is_landing_orchestrated ) {
			if ( ! $this->state_manager->acquire_lock( self::LOCK_NAME ) ) {
				$this->leave_authorized_mutation_scope( $mutation_fence, (string) $fence_owner );

				return new \WP_Error( 'rms_wizard_busy', \__( 'Another setup wizard action is already running.', 'simple-rms-theme' ), [ 'status' => 409 ] );
			}

			$legacy_lock_held = true;
		}

		$progress_status_written = false;
		$prior_step_status       = (string) ( $this->state_manager->get_state()['step_status'][ $step ] ?? 'pending' );
		if ( '' === $prior_step_status ) {
			$prior_step_status = 'pending';
		}

		try {
			// Pseudo-steps (unlock/relock) must not pollute current_step or step_status.
			// Landing start/process persist their own run/public state.
			// Skip-all either 409s without mutation or marks complete itself.
			if ( ! $this->is_completed_gate_allowlisted( $step ) && ! $is_landing_orchestrated && ! $is_landing_skip_all && ! $is_internal_skip_all ) {
				$this->state_manager->set_current_step( $step );
				$this->state_manager->set_step_status( $step, 'running' );
				$progress_status_written = true;
			}

			$result = $this->dispatch_step( $step, $payload );

			if ( \is_wp_error( $result ) ) {
				if ( $progress_status_written && 'internal-page-builder' === $step ) {
					$this->state_manager->set_step_status( $step, $prior_step_status );
				}

				return $result;
			}

			/*
			 * Top-level success must reflect the authoritative terminal step
			 * status, not merely that the HTTP request completed. A step that
			 * returned a result array but failed (e.g. dependencies with missing
			 * plugins) must not emit success:true or the frontend will show a
			 * false "Step completed" message.
			 *
			 * Merge invariant for issue #27: a healthy landing-page-builder
			 * bounded request may finish this HTTP call still `running`. That
			 * is in-progress work, not a terminal failure. Do not coerce it to
			 * success:false here, and never treat `running` as `complete`.
			 * Landing start/process skip the progress write, so they stay
			 * success:true even when the persisted run is still running.
			 */
			$final_status = $progress_status_written
				? (string) ( $this->state_manager->get_state()['step_status'][ $step ] ?? '' )
				: '';
			$success      = self::response_success_from_status( $final_status, $progress_status_written );

			return [
				'success' => $success,
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
			// release_lock() still does a whole-state get/save. Keep the
			// mutation fence (and this instance owner) until that write
			// finishes so a landing start/process cannot acquire and then
			// be overwritten by a stale snapshot.
			try {
				if ( $legacy_lock_held ) {
					$this->state_manager->release_lock( self::LOCK_NAME );
				}
			} finally {
				$mutation_fence->clear_agent( (string) $fence_owner );
				$mutation_fence->release( (string) $fence_owner );
				$this->mutation_fence_owner = '';
			}
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

		$mutation_fence = new Wizard_Mutation_Fence();
		$fence_owner    = $mutation_fence->acquire();

		if ( \is_wp_error( $fence_owner ) ) {
			return $fence_owner;
		}

		$this->mutation_fence_owner = (string) $fence_owner;

		try {
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
		} finally {
			$mutation_fence->release( (string) $fence_owner );
			$this->mutation_fence_owner = '';
		}
	}

	/**
	 * Overlay the public landing run. Recover a stale lease only while this
	 * controller already owns the mutation fence, or after a short scoped acquire.
	 * A busy fence returns the current public view without recovery or mutation.
	 *
	 * @param array<string,mixed> $state
	 *
	 * @return array<string,mixed>
	 */
	private function with_public_landing_run( array $state ): array {
		if ( ! class_exists( __NAMESPACE__ . '\\Landing_Run_Orchestrator' ) ) {
			return $state;
		}

		$fence        = new Wizard_Mutation_Fence();
		$scoped_owner = '';

		if ( '' === $this->mutation_fence_owner ) {
			$acquired = $fence->acquire();

			if ( \is_wp_error( $acquired ) ) {
				return $this->overlay_public_landing_run( $state );
			}

			$scoped_owner               = (string) $acquired;
			$this->mutation_fence_owner = $scoped_owner;
		}

		try {
			$orchestrator = new Landing_Run_Orchestrator( $this->state_manager, $this->logger );
			$orchestrator->recover_stale_lease();

			return $this->overlay_public_landing_run( $state );
		} finally {
			if ( '' !== $scoped_owner ) {
				$fence->release( $scoped_owner );

				if ( $this->mutation_fence_owner === $scoped_owner ) {
					$this->mutation_fence_owner = '';
				}
			}
		}
	}

	/**
	 * Replace landing_run with the public view. Never includes lease_owner.
	 *
	 * @param array<string,mixed> $state
	 *
	 * @return array<string,mixed>
	 */
	private function overlay_public_landing_run( array $state ): array {
		$orchestrator = new Landing_Run_Orchestrator( $this->state_manager, $this->logger );
		$run          = $orchestrator->get_public_run();

		if ( null !== $run ) {
			$state['landing_run'] = $run;
		}

		return $state;
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

			case 'landing-page-builder':
				return ( new Step_Landing_Page_Builder( $this->logger, $this->state_manager ) )->run( $payload );

			case 'internal-page-builder':
				$builder = new Step_Internal_Page_Builder( $this->logger, $this->state_manager );
				$this->authorize_internal_builder( $builder );
				return $builder->run( $payload );

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

		$ai_config = [
			'provider'         => $provider,
			'provider_label'   => AI_Provider_Registry::get_provider_label( $provider ),
			'model'            => $model,
			'credential'       => $credential,
			'has_credentials'  => ! empty( $credential['has_key'] ),
			'configured_at'    => \current_time( 'mysql', true ),
		];

		$fresh              = $this->state_manager->get_state();
		$fresh['ai_config'] = $ai_config;
		$this->state_manager->save_state( $fresh );
		$this->state_manager->set_step_status( 'ia-generation', 'complete' );
		$this->logger->log( 'info', 'Wizard AI configuration saved.', [ 'provider' => $provider, 'model' => $model, 'has_credentials' => ! empty( $credential['has_key'] ) ] );

		return [ 'ai_config' => $ai_config ];
	}

	/**
	 * Truthy payload values used by skip_all and similar flags.
	 *
	 * @param mixed $value
	 */
	private function payload_is_truthy( $value ): bool {
		return true === $value || 1 === $value || '1' === $value || 'true' === $value || 'yes' === $value || 'on' === $value;
	}

	private function enter_authorized_mutation_scope( string $owner ): void {
		$this->mutation_fence_owner = $owner;
	}

	private function leave_authorized_mutation_scope( Wizard_Mutation_Fence $fence, string $owner ): void {
		$fence->clear_agent( $owner );
		$fence->release( $owner );

		if ( $this->mutation_fence_owner === $owner ) {
			$this->mutation_fence_owner = '';
		}
	}

	private function authorize_internal_builder( Step_Internal_Page_Builder $builder ): void {
		$owner = $this->mutation_fence_owner;
		if ( '' === $owner ) {
			return;
		}

		$builder->accept_mutation_owner( $owner );
		( new Wizard_Mutation_Fence() )->authorize_agent( $builder, $owner );
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
			'landing_page_builder' => 'landing-page-builder',
			'internal_page_builder' => 'internal-page-builder',
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
