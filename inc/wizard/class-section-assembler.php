<?php
/**
 * Shared wizard section payload assembler.
 *
 * @package Simple_RMS_Theme
 */

namespace Inc\Wizard;

defined( 'ABSPATH' ) || exit;

/**
 * Builds ACF flexible-content rows from placeholders, AI copy, and service facts.
 *
 * Extracted from the identical Home/Landing assembly pair so both builders delegate
 * without changing generated output.
 */
final class Section_Assembler {
	private $harness;

	public function __construct( AI_Content_Harness $harness ) {
		$this->harness = $harness;
	}

	/**
	 * Assemble one flexible-content row for a layout.
	 *
	 * @param array<string,mixed> $client_data Wizard client data.
	 * @param array<string,mixed> $copy        AI or override copy.
	 *
	 * @return array<string,mixed>
	 */
	public function section_data( string $section_key, array $client_data, array $copy, int $item_count ): array {
		$section = array_merge( [ 'acf_fc_layout' => $section_key ], $this->placeholder_copy( $section_key, $client_data, $item_count ) );
		$allowed = array_flip( $this->harness->get_fillable_fields( $section_key ) );

		foreach ( $copy as $field => $value ) {
			$field = (string) $field;

			if ( false !== strpos( $field, '_services' ) ) {
				continue;
			}

			if ( isset( $allowed[ $field ] ) ) {
				$section[ $field ] = $this->section_value( $value );
			}
		}

		$service_rows = $this->service_rows( $section_key, $client_data, $copy, $item_count );

		if ( [] !== $service_rows ) {
			$section[ $service_rows['field'] ] = $service_rows['rows'];
		}

		return $section;
	}

	private function placeholder_copy( string $section_key, array $client_data, int $item_count ): array {
		// Layouts with no fillable fields must not produce invented fallback copy.
		if ( ! $this->harness->has_fillable_fields( $section_key ) ) {
			return [];
		}

		$company        = $this->text( $client_data['company_name'] ?? \__( 'Your Company', 'simple-rms-theme' ) );
		$text_repeaters = $this->harness->get_text_repeater_fields( $section_key );
		$copy           = [];

		foreach ( $this->harness->get_fillable_fields( $section_key ) as $field ) {
			if ( false !== strpos( $field, '_services' ) || isset( $text_repeaters[ $field ] ) ) {
				continue;
			}

			$copy[ $field ] = $this->placeholder_field_value( $field, $company );
		}

		foreach ( $text_repeaters as $field => $sub_fields ) {
			$copy[ $field ] = $this->placeholder_repeater_rows( $sub_fields, $company, $item_count );
		}

		return $copy;
	}

	private function placeholder_repeater_rows( array $sub_fields, string $company, int $item_count ): array {
		$rows  = [];
		$count = max( 1, min( 12, $item_count ) );

		for ( $index = 0; $index < $count; $index++ ) {
			$row = [];

			foreach ( $sub_fields as $sub_field ) {
				$sub_field         = (string) $sub_field;
				$row[ $sub_field ] = $this->placeholder_field_value( $sub_field, $company );
			}

			if ( [] !== $row ) {
				$rows[] = $row;
			}
		}

		return $rows;
	}

	private function placeholder_field_value( string $field, string $company ): string {
		if ( false !== strpos( $field, 'headline' ) || false !== strpos( $field, 'title' ) || false !== strpos( $field, 'question' ) ) {
			return sprintf( /* translators: %s: company name. */ \__( '%s Services You Can Trust', 'simple-rms-theme' ), $company );
		}

		if ( false !== strpos( $field, 'subheadline' ) || false !== strpos( $field, 'eyebrow' ) || false !== strpos( $field, 'label' ) ) {
			return \__( 'Dependable service and clear communication', 'simple-rms-theme' );
		}

		if ( false !== strpos( $field, 'cta' ) || false !== strpos( $field, 'button' ) ) {
			return \__( 'Get an Estimate', 'simple-rms-theme' );
		}

		return sprintf( /* translators: %s: company name. */ \__( '%s provides reliable service with careful attention to each project.', 'simple-rms-theme' ), $company );
	}

	/**
	 * @return array{field:string,rows:array<int,array<string,string>>}|array{}
	 */
	private function service_rows( string $section_key, array $client_data, array $copy, int $item_count ): array {
		$contracts = [
			'services-v1' => [ 'field' => 'services_v1_services', 'name' => 'service_title', 'description' => 'service_text' ],
			'services-v2' => [ 'field' => 'services_v2_services', 'name' => 'service_title', 'description' => 'service_text' ],
			'services-v3' => [ 'field' => 'services_v3_services', 'name' => 'service_name', 'description' => 'service_overlay_text' ],
		];

		if ( ! isset( $contracts[ $section_key ] ) ) {
			return [];
		}

		$contract = $contracts[ $section_key ];
		$ai_rows  = is_array( $copy[ $contract['field'] ] ?? null ) ? array_values( $copy[ $contract['field'] ] ) : [];
		$rows     = [];

		foreach ( array_slice( is_array( $client_data['company_services'] ?? null ) ? $client_data['company_services'] : [], 0, $item_count ) as $index => $service ) {
			if ( ! is_array( $service ) || empty( $service['service_name'] ) ) {
				continue;
			}

			$ai_row      = is_array( $ai_rows[ $index ] ?? null ) ? $ai_rows[ $index ] : [];
			$description = $ai_row[ $contract['description'] ] ?? $service['service_short_description'] ?? '';

			$rows[] = [
				$contract['name']        => $this->text( $service['service_name'] ),
				$contract['description'] => $this->html( $description ),
			];
		}

		return [ 'field' => $contract['field'], 'rows' => $rows ];
	}

	private function section_value( $value ) {
		if ( is_array( $value ) ) {
			return array_map( [ $this, 'section_value' ], $value );
		}

		return $this->html( $value );
	}

	private function text( $value ): string {
		return \sanitize_text_field( (string) $value );
	}

	private function html( $value ): string {
		return \wp_kses_post( (string) $value );
	}
}
