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
		$required = [ 'dependencies', 'acf-import', 'client-data', 'ai-generation', 'content-creation' ];

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

			case 'ai-generation':
				return $this->generate_ai_content( $payload );

			case 'content-creation':
				$pages = is_array( $payload['pages'] ?? null ) ? $payload['pages'] : [];
				return ( new Content_Builder( $this->logger, $this->state_manager ) )->build_pages( $pages );

			default:
				$this->state_manager->set_step_status( $step, 'failed' );
				return new \WP_Error( 'rms_wizard_unknown_step', \__( 'Unknown setup wizard step.', 'simple-rms-theme' ), [ 'status' => 400 ] );
		}
	}

	/**
	 * Generate one content section through the existing AI adapter service.
	 *
	 * @param array $payload Request payload.
	 *
	 * @return array<string,mixed>|\WP_Error
	 */
	private function generate_ai_content( array $payload ) {
		$provider = \sanitize_key( (string) ( $payload['provider'] ?? AI_Provider_Registry::default_provider() ) );
		$api_key  = AI_Credential_Store::normalize_api_key( (string) ( $payload['api_key'] ?? '' ) );
		$prompt   = \sanitize_textarea_field( (string) ( $payload['prompt'] ?? '' ) );
		$model    = \sanitize_text_field( (string) ( $payload['model'] ?? '' ) );
		$context  = is_array( $payload['context'] ?? null ) ? $payload['context'] : [];

		if ( ! AI_Provider_Registry::provider_exists( $provider ) ) {
			$this->state_manager->set_step_status( 'ai-generation', 'failed' );
			return new \WP_Error( 'rms_wizard_unknown_ai_provider', \__( 'Unknown AI provider selected.', 'simple-rms-theme' ), [ 'status' => 400 ] );
		}

		if ( '' === $prompt || '' === $model ) {
			$this->state_manager->set_step_status( 'ai-generation', 'failed' );
			return new \WP_Error( 'rms_wizard_missing_ai_payload', \__( 'The AI generation step requires a model and prompt.', 'simple-rms-theme' ), [ 'status' => 400 ] );
		}

		$result = AI_Provider_Registry::make_provider( $provider, $api_key )->generate( $model, $prompt, $context );
		$this->state_manager->set_step_status( 'ai-generation', ! empty( $result['success'] ) ? 'complete' : 'failed' );

		if ( ! empty( $result['success'] ) && '' !== $api_key ) {
			try {
				AI_Credential_Store::save( $provider, $api_key );
			} catch ( \Throwable $error ) {
				$this->state_manager->set_step_status( 'ai-generation', 'failed' );
				return new \WP_Error( 'rms_wizard_ai_key_save_failed', \__( 'The API key could not be encrypted and saved.', 'simple-rms-theme' ), [ 'status' => 500 ] );
			}
		}

		if ( ! empty( $result['success'] ) && ! empty( $context['section_key'] ) && ! empty( $context['session_id'] ) ) {
			$key   = 'rms_wizard_section_' . md5( (string) $context['session_id'] . ':' . (string) $context['section_key'] );
			$state = $this->state_manager->get_state();
			$state['generated'][ \sanitize_key( (string) $context['section_key'] ) ] = $key;
			$this->state_manager->save_state( $state );
		}

		return $result;
	}

	private function normalize_step( string $step ): string {
		$step = \sanitize_key( $step );

		$aliases = [
			'acf_import'       => 'acf-import',
			'client_data'      => 'client-data',
			'ai_generation'    => 'ai-generation',
			'content_creation' => 'content-creation',
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
