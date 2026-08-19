<?php
/**
 * Wizard client data field definitions.
 *
 * @package Simple_RMS_Theme
 */

namespace Inc\Wizard;

defined( 'ABSPATH' ) || exit;

/**
 * Reads the Theme Settings ACF JSON and exposes only wizard-safe client fields.
 */
class Client_Data_Fields {
	private const FIELD_GROUP_FILE = 'acf-json/group_rms_theme_settings.json';

	private const INCLUDED_SECTIONS = [
		'General',
		'Contact',
		'Social Media',
		'Branding',
		'Layout',
		'Business',
	];

	private const SUPPORTED_TYPES = [
		'color_picker',
		'email',
		'image',
		'repeater',
		'select',
		'text',
		'textarea',
		'time_picker',
		'true_false',
		'url',
	];

	/**
	 * Get non-schema Theme Settings sections and fields for the Client Data step.
	 *
	 * @return array<int,array{label:string,slug:string,fields:array<int,array<string,mixed>>}>
	 */
	public function get_sections(): array {
		$sections        = [];
		$current_section = '';

		foreach ( $this->load_fields() as $field ) {
			$type = (string) ( $field['type'] ?? '' );

			if ( 'tab' === $type ) {
				$label = (string) ( $field['label'] ?? '' );

				if ( 'Schema' === $label ) {
					$current_section = '';
					continue;
				}

				if ( ! in_array( $label, self::INCLUDED_SECTIONS, true ) ) {
					$current_section = '';
					continue;
				}

				$current_section = $label;
				$sections[ $label ] = [
					'label'  => $label,
					'slug'   => \sanitize_title( $label ),
					'fields' => [],
				];

				continue;
			}

			if ( '' === $current_section ) {
				continue;
			}

			$normalized = $this->normalize_field( $field );

			if ( [] === $normalized ) {
				continue;
			}

			$sections[ $current_section ]['fields'][] = $normalized;
		}

		return array_values(
			array_filter(
				$sections,
				static function ( array $section ): bool {
					return ! empty( $section['fields'] );
				}
			)
		);
	}

	/**
	 * Get a top-level field map keyed by ACF field name.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function get_field_map(): array {
		$field_map = [];

		foreach ( $this->get_sections() as $section ) {
			foreach ( $section['fields'] as $field ) {
				$field_map[ (string) $field['name'] ] = $field;
			}
		}

		return $field_map;
	}

	/**
	 * Normalize and filter one ACF field definition.
	 *
	 * @param array<string,mixed> $field Raw ACF JSON field.
	 *
	 * @return array<string,mixed>
	 */
	private function normalize_field( array $field ): array {
		$name = (string) ( $field['name'] ?? '' );
		$type = (string) ( $field['type'] ?? '' );

		if ( '' === $name || 0 === strpos( $name, 'schema_' ) ) {
			return [];
		}

		if ( ! in_array( $type, self::SUPPORTED_TYPES, true ) ) {
			$type = 'text';
		}

		$field['type'] = $type;

		if ( 'repeater' === $type ) {
			$sub_fields = [];

			foreach ( (array) ( $field['sub_fields'] ?? [] ) as $sub_field ) {
				if ( ! is_array( $sub_field ) ) {
					continue;
				}

				$normalized_sub_field = $this->normalize_field( $sub_field );

				if ( [] !== $normalized_sub_field ) {
					$sub_fields[] = $normalized_sub_field;
				}
			}

			$field['sub_fields'] = $sub_fields;
		}

		return $field;
	}

	/**
	 * Load raw fields from the ACF JSON field group.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function load_fields(): array {
		$path = \trailingslashit( \get_template_directory() ) . self::FIELD_GROUP_FILE;

		if ( ! is_readable( $path ) ) {
			return [];
		}

		$contents = file_get_contents( $path );

		if ( false === $contents ) {
			return [];
		}

		$decoded = json_decode( $contents, true );

		if ( ! is_array( $decoded ) || ! is_array( $decoded['fields'] ?? null ) ) {
			return [];
		}

		return $decoded['fields'];
	}
}
