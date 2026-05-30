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
	}

	public function permission_callback(): bool {
		return $this->step_controller->can_access();
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
}
