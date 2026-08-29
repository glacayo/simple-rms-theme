<?php
/**
 * Wizard REST controller.
 *
 * @package Simple_RMS_Theme
 */

namespace Inc\Wizard;

defined( 'ABSPATH' ) || exit;

class Rest_Controller {
	public const NAMESPACE = 'rms-wizard/v1';

	private $step_controller;

	public function __construct( ?Step_Controller $step_controller = null ) {
		$this->step_controller = $step_controller ?? new Step_Controller();
	}

	public function register_routes(): void {
		\register_rest_route(
			self::NAMESPACE,
			'/state',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_state' ],
				'permission_callback' => [ $this, 'permission_callback' ],
			]
		);

		\register_rest_route(
			self::NAMESPACE,
			'/steps/(?P<step>[a-z0-9_-]+)/run',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'run_step' ],
				'permission_callback' => [ $this, 'permission_callback' ],
				'args'                => [
					'step' => [
						'required'          => true,
						'sanitize_callback' => 'sanitize_key',
					],
				],
			]
		);

		\register_rest_route(
			self::NAMESPACE,
			'/complete',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'complete' ],
				'permission_callback' => [ $this, 'permission_callback' ],
			]
		);

		\register_rest_route(
			self::NAMESPACE,
			'/ai/models',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'list_ai_models' ],
				'permission_callback' => [ $this, 'permission_callback' ],
			]
		);
	}

		public function permission_callback( $request = null ): bool {
			if ( ! $this->step_controller->can_access() ) {
				return false;
			}

			if ( function_exists( 'wp_verify_nonce' ) ) {
				return $this->rest_nonce_is_valid( $request );
			}

			return true;
		}

		/**
		 * Cookie REST nonce check. Never accepts a nonce without wp_verify_nonce().
		 *
		 * @param mixed $request Optional REST request.
		 */
		public function rest_nonce_is_valid( $request = null ): bool {
			if ( ! function_exists( 'wp_verify_nonce' ) ) {
				return false;
			}

			$nonce = '';
			if ( is_object( $request ) && method_exists( $request, 'get_header' ) ) {
				$nonce = (string) $request->get_header( 'X-WP-Nonce' );
			}
			if ( '' === $nonce && isset( $_SERVER['HTTP_X_WP_NONCE'] ) ) {
				$nonce = (string) $_SERVER['HTTP_X_WP_NONCE'];
			}

			return false !== \wp_verify_nonce( $nonce, 'wp_rest' );
		}

	public function get_state(): \WP_REST_Response {
		return new \WP_REST_Response( $this->step_controller->get_resume_state(), 200 );
	}

	public function run_step( \WP_REST_Request $request ) {
		$payload = $request->get_json_params();

		if ( ! is_array( $payload ) ) {
			$payload = $request->get_params();
			unset( $payload['step'] );
		}

		$result = $this->step_controller->execute_step( (string) $request['step'], $payload );

		if ( \is_wp_error( $result ) ) {
			return $result;
		}

		return new \WP_REST_Response( $result, 200 );
	}

	public function complete() {
		$result = $this->step_controller->complete();

		if ( \is_wp_error( $result ) ) {
			return $result;
		}

		return new \WP_REST_Response( $result, 200 );
	}

	public function list_ai_models( \WP_REST_Request $request ) {
		$payload = $request->get_json_params();

		if ( ! is_array( $payload ) ) {
			$payload = $request->get_params();
		}

		$provider = \sanitize_key( (string) ( $payload['provider'] ?? AI_Provider_Registry::default_provider() ) );
		$api_key  = AI_Credential_Store::normalize_api_key( (string) ( $payload['api_key'] ?? '' ) );

		if ( ! AI_Provider_Registry::provider_exists( $provider ) ) {
			return new \WP_Error( 'rms_wizard_unknown_ai_provider', \__( 'Unknown AI provider selected.', 'simple-rms-theme' ), [ 'status' => 400 ] );
		}

		$service = AI_Provider_Registry::make_provider( $provider, $api_key );
		$models  = $service->list_models();

		if ( \is_wp_error( $models ) ) {
			return $models;
		}

		if ( '' !== $api_key ) {
			try {
				AI_Credential_Store::save( $provider, $api_key );
			} catch ( \Throwable $error ) {
				return new \WP_Error( 'rms_wizard_ai_key_save_failed', \__( 'The API key could not be encrypted and saved.', 'simple-rms-theme' ), [ 'status' => 500 ] );
			}
		}

		return new \WP_REST_Response(
			[
				'success'    => true,
				'provider'   => $provider,
				'models'     => $models,
				'credential' => AI_Credential_Store::status( $provider ),
			],
			200
		);
	}
}
