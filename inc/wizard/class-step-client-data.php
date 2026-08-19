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
		$field_map = ( new Client_Data_Fields() )->get_field_map();
		$sanitized = $this->sanitize_data( $data, $field_map );

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

	/**
	 * Sanitize client data against the non-schema ACF Theme Settings field map.
	 *
	 * @param array<string,mixed> $data      Raw client data.
	 * @param array<string,array<string,mixed>> $field_map Allowed field map.
	 *
	 * @return array<string,mixed>
	 */
	private function sanitize_data( array $data, array $field_map ): array {
		$sanitized = [];

		foreach ( $field_map as $field_name => $field ) {
			$field_name = \sanitize_key( (string) $field_name );

			if ( '' === $field_name || ! array_key_exists( $field_name, $data ) ) {
				continue;
			}

			$sanitized[ $field_name ] = $this->sanitize_value( $data[ $field_name ], $field );
		}

		return $sanitized;
	}

	/**
	 * Sanitize a field value based on its ACF type.
	 *
	 * @param mixed               $value Raw value.
	 * @param array<string,mixed> $field ACF field definition.
	 *
	 * @return mixed
	 */
	private function sanitize_value( $value, array $field ) {
		$type = (string) ( $field['type'] ?? 'text' );

		switch ( $type ) {
			case 'repeater':
				return $this->sanitize_repeater_value( $value, is_array( $field['sub_fields'] ?? null ) ? $field['sub_fields'] : [] );

			case 'true_false':
				return ! empty( $value ) && '0' !== (string) $value ? 1 : 0;

			case 'image':
				$attachment_id = \absint( $value );
				return $attachment_id > 0 ? $attachment_id : '';

			case 'email':
				return \sanitize_email( (string) $value );

			case 'url':
				return \esc_url_raw( (string) $value );

			case 'textarea':
				return \sanitize_textarea_field( (string) $value );

			case 'color_picker':
				$color = \sanitize_hex_color( (string) $value );
				return is_string( $color ) ? $color : '';

			case 'select':
				return $this->sanitize_select_value( $value, is_array( $field['choices'] ?? null ) ? $field['choices'] : [] );

			case 'time_picker':
				return preg_match( '/^\d{2}:\d{2}$/', (string) $value ) ? (string) $value : \sanitize_text_field( (string) $value );

			case 'text':
			default:
				return \sanitize_text_field( (string) $value );
		}
	}

	/**
	 * Sanitize ACF repeater rows.
	 *
	 * @param mixed $value Raw repeater value.
	 * @param array<int,array<string,mixed>> $sub_fields Repeater sub fields.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function sanitize_repeater_value( $value, array $sub_fields ): array {
		if ( ! is_array( $value ) ) {
			return [];
		}

		$sub_field_map = [];

		foreach ( $sub_fields as $sub_field ) {
			$sub_name = \sanitize_key( (string) ( $sub_field['name'] ?? '' ) );

			if ( '' !== $sub_name ) {
				$sub_field_map[ $sub_name ] = $sub_field;
			}
		}

		$rows = [];

		foreach ( $value as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$sanitized_row = [];

			foreach ( $sub_field_map as $sub_name => $sub_field ) {
				$sanitized_row[ $sub_name ] = $this->sanitize_value( $row[ $sub_name ] ?? '', $sub_field );
			}

			if ( ! $this->is_empty_repeater_row( $sanitized_row ) ) {
				$rows[] = $sanitized_row;
			}
		}

		return $rows;
	}

	/**
	 * Sanitize a select value and reject values outside the ACF choices list.
	 *
	 * @param mixed $value Raw value.
	 * @param array<string,string> $choices Allowed choices.
	 *
	 * @return string
	 */
	private function sanitize_select_value( $value, array $choices ): string {
		$value = \sanitize_text_field( (string) $value );

		if ( [] === $choices || '' === $value ) {
			return $value;
		}

		return array_key_exists( $value, $choices ) ? $value : '';
	}

	/**
	 * Determine whether a sanitized repeater row has meaningful data.
	 *
	 * @param array<string,mixed> $row Sanitized row.
	 */
	private function is_empty_repeater_row( array $row ): bool {
		foreach ( $row as $value ) {
			if ( is_array( $value ) && ! $this->is_empty_repeater_row( $value ) ) {
				return false;
			}

			if ( ! is_array( $value ) && '' !== $value && null !== $value && 0 !== $value && false !== $value ) {
				return false;
			}
		}

		return true;
	}
}
