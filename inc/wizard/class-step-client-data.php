<?php
/**
 * Wizard client data step service.
 *
 * @package Simple_RMS_Theme
 */

namespace Inc\Wizard;

defined( 'ABSPATH' ) || exit;

/**
 * Persists client data into ACF theme options.
 */
class Step_Client_Data {
	private const STEP        = 'client-data';
	private const OPTION_POST = 'option';

	private $logger;
	private $state_manager;

	public function __construct( ?Logger $logger = null, ?State_Manager $state_manager = null ) {
		$this->logger        = $logger ?? new Logger();
		$this->state_manager = $state_manager ?? new State_Manager();
	}

	/**
	 * Save sanitized client data to ACF options and wizard state.
	 *
	 * @param array $data Raw client data.
	 *
	 * @return array<string,mixed>
	 */
	public function save( array $data ): array {
		$sanitized = $this->sanitize_data( $data );

		foreach ( $sanitized as $field_name => $value ) {
			if ( function_exists( 'update_field' ) ) {
				\update_field( $field_name, $value, self::OPTION_POST );
			} else {
				\update_option( 'options_' . $field_name, $value, false );
			}
		}

		$this->state_manager->merge_state( [ 'client_data' => $sanitized ] );
		$this->state_manager->set_step_status( self::STEP, 'complete' );
		$this->logger->log( 'info', 'Client data saved to ACF theme options.', [ 'fields' => array_keys( $sanitized ) ] );

		return $sanitized;
	}

	private function sanitize_data( array $data ): array {
		$sanitized = [];

		foreach ( $data as $key => $value ) {
			$field_name = \sanitize_key( (string) $key );

			if ( '' === $field_name ) {
				continue;
			}

			$sanitized[ $field_name ] = $this->sanitize_value( $value );
		}

		return $sanitized;
	}

	private function sanitize_value( $value ) {
		if ( is_array( $value ) ) {
			$sanitized = [];

			foreach ( $value as $key => $child_value ) {
				$sanitized[ is_int( $key ) ? $key : \sanitize_key( (string) $key ) ] = $this->sanitize_value( $child_value );
			}

			return $sanitized;
		}

		if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) || null === $value ) {
			return $value;
		}

		$value = (string) $value;

		if ( filter_var( $value, FILTER_VALIDATE_URL ) ) {
			return \esc_url_raw( $value );
		}

		if ( \is_email( $value ) ) {
			return \sanitize_email( $value );
		}

		return \sanitize_textarea_field( $value );
	}
}
