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
	private const REQUIRED_STEPS = [
		'dependencies',
		'acf-import',
		'client-data',
		'generate-pages',
		'menu-setup',
		'ia-generation',
		'home-page-builder',
	];

	private $state_manager;
	private $logger;

	public function __construct( ?State_Manager $state_manager = null, ?Logger $logger = null ) {
		$this->state_manager = $state_manager ?? new State_Manager();
		$this->logger        = $logger ?? new Logger();
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
		$state           = $this->state_manager->get_state();
		$state['locked'] = $this->state_manager->is_completed();
		$state['logs']   = $this->logger->all();

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

		if ( $this->state_manager->is_completed() && 'state' !== $step ) {
			return new \WP_Error( 'rms_wizard_locked', \__( 'The setup wizard is already complete.', 'simple-rms-theme' ), [ 'status' => 423 ] );
		}

		if ( ! $this->state_manager->acquire_lock( self::LOCK_NAME ) ) {
			return new \WP_Error( 'rms_wizard_busy', \__( 'Another setup wizard action is already running.', 'simple-rms-theme' ), [ 'status' => 409 ] );
		}

		try {
			$this->state_manager->set_current_step( $step );
			$this->state_manager->set_step_status( $step, 'running' );

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
		$required = self::REQUIRED_STEPS;

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

			default:
				$this->state_manager->set_step_status( $step, 'failed' );
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
			'acf_import'       => 'acf-import',
			'client_data'      => 'client-data',
			'ai-generation'    => 'ia-generation',
			'ai_generation'    => 'ia-generation',
			'ia_generation'    => 'ia-generation',
			'generate_pages'    => 'generate-pages',
			'menu_setup'        => 'menu-setup',
			'home_page_builder' => 'home-page-builder',
		];

		return $aliases[ $step ] ?? $step;
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
