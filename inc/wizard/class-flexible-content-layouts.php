<?php
/**
 * ACF flexible content layout repository for wizard steps.
 *
 * @package Simple_RMS_Theme
 */

namespace Inc\Wizard;

defined( 'ABSPATH' ) || exit;

/**
 * Reads the Page Sections ACF JSON and builds safe layout defaults.
 */
class Flexible_Content_Layouts {
	private const FIELD_GROUP_PATH = 'acf-json/group_rms_page_sections.json';

	/**
	 * Layout keys that should appear as quick-start Home page sections.
	 */
	private const COMMON_LAYOUTS = [ 'slider', 'cta-v1', 'about-us', 'services-v1', 'gallery-grid', 'testimonials-v1', 'contact-info' ];

	/**
	 * Cached layout definitions.
	 *
	 * @var array<string,array<string,mixed>>|null
	 */
	private $layouts = null;

	/**
	 * Return all available flexible content layouts keyed by layout name.
	 *
	 * @return array<string,array{key:string,name:string,label:string,description:string,sub_fields:array<int,array<string,mixed>>}>
	 */
	public function get_layouts(): array {
		if ( null !== $this->layouts ) {
			return $this->layouts;
		}

		$layouts = $this->read_layouts_from_json();

		if ( [] === $layouts ) {
			$layouts = $this->fallback_layouts();
		}

		$this->layouts = $layouts;

		return $this->layouts;
	}

	/**
	 * Return common quick-start layouts that exist in the ACF JSON.
	 *
	 * @return array<string,array{key:string,name:string,label:string,description:string,sub_fields:array<int,array<string,mixed>>}>
	 */
	public function get_common_layouts(): array {
		$layouts = $this->get_layouts();
		$common  = [];

		foreach ( self::COMMON_LAYOUTS as $layout_key ) {
			if ( isset( $layouts[ $layout_key ] ) ) {
				$common[ $layout_key ] = $layouts[ $layout_key ];
			}
		}

		return $common;
	}

	public function has_layout( string $layout_key ): bool {
		$layout_key = $this->normalize_layout_key( $layout_key );

		return isset( $this->get_layouts()[ $layout_key ] );
	}

	/**
	 * Build safe fallback data for a layout that does not have a richer mapper.
	 *
	 * @param string              $layout_key  ACF flexible content layout key.
	 * @param array<string,mixed> $client_data Wizard client data.
	 * @param array<string,mixed> $copy        AI copy payload.
	 *
	 * @return array<string,mixed>
	 */
	public function build_generic_section( string $layout_key, array $client_data, array $copy = [] ): array {
		$layout_key = $this->normalize_layout_key( $layout_key );
		$layout     = $this->get_layouts()[ $layout_key ] ?? null;
		$section    = [ 'acf_fc_layout' => $layout_key ];
		$harness    = new AI_Content_Harness();

		// If the layout has no AI-fillable fields, do not populate any invented defaults.
		if ( ! $harness->has_fillable_fields( $layout_key ) ) {
			return $section;
		}

		$fillable = array_flip( $harness->get_fillable_fields( $layout_key ) );

		if ( ! is_array( $layout ) ) {
			return $section;
		}

		foreach ( is_array( $layout['sub_fields'] ?? null ) ? $layout['sub_fields'] : [] as $field ) {
			if ( ! is_array( $field ) || empty( $field['name'] ) ) {
				continue;
			}

			$name = (string) $field['name'];

			if ( ! isset( $fillable[ $name ] ) ) {
				continue;
			}

			$section[ $name ] = $this->generic_field_value( $field, $client_data, $copy );
		}

		return $section;
	}

	/**
	 * @return array<string,array{key:string,name:string,label:string,description:string,sub_fields:array<int,array<string,mixed>>}>
	 */
	private function read_layouts_from_json(): array {
		$path = $this->acf_json_path();

		if ( ! is_readable( $path ) ) {
			return [];
		}

		$decoded = json_decode( (string) file_get_contents( $path ), true );

		if ( ! is_array( $decoded ) || ! is_array( $decoded['fields'] ?? null ) ) {
			return [];
		}

		foreach ( $decoded['fields'] as $field ) {
			if ( ! is_array( $field ) || 'page_sections' !== ( $field['name'] ?? '' ) || ! is_array( $field['layouts'] ?? null ) ) {
				continue;
			}

			$layouts = [];

			foreach ( $field['layouts'] as $layout ) {
				if ( ! is_array( $layout ) || empty( $layout['name'] ) ) {
					continue;
				}

				$name = $this->normalize_layout_key( (string) $layout['name'] );

				if ( '' === $name ) {
					continue;
				}

				$label = \sanitize_text_field( (string) ( $layout['label'] ?? $name ) );

				$layouts[ $name ] = [
					'key'         => \sanitize_key( (string) ( $layout['key'] ?? $name ) ),
					'name'        => $name,
					'label'       => $label,
					'description' => sprintf( /* translators: %s: ACF layout key. */ \__( 'Adds the %s flexible content layout.', 'simple-rms-theme' ), $name ),
					'sub_fields'  => is_array( $layout['sub_fields'] ?? null ) ? $layout['sub_fields'] : [],
				];
			}

			return $layouts;
		}

		return [];
	}

	private function acf_json_path(): string {
		if ( function_exists( 'get_template_directory' ) ) {
			return \trailingslashit( \get_template_directory() ) . self::FIELD_GROUP_PATH;
		}

		return dirname( __DIR__, 2 ) . '/' . self::FIELD_GROUP_PATH;
	}

	/**
	 * @return array<string,array{key:string,name:string,label:string,description:string,sub_fields:array<int,array<string,mixed>>}>
	 */
	private function fallback_layouts(): array {
		return [
			'slider'          => [ 'key' => 'layout_slider', 'name' => 'slider', 'label' => \__( 'Slider', 'simple-rms-theme' ), 'description' => \__( 'Hero-style slides with headline, copy, and CTA.', 'simple-rms-theme' ), 'sub_fields' => [] ],
			'cta-v1'          => [ 'key' => 'layout_cta_v1', 'name' => 'cta-v1', 'label' => \__( 'CTA V1', 'simple-rms-theme' ), 'description' => \__( 'A focused call-to-action band using the CTA V1 layout.', 'simple-rms-theme' ), 'sub_fields' => [] ],
			'about-us'        => [ 'key' => 'layout_about_us', 'name' => 'about-us', 'label' => \__( 'About Us', 'simple-rms-theme' ), 'description' => \__( 'Business introduction with trust-focused copy.', 'simple-rms-theme' ), 'sub_fields' => [] ],
			'services-v1'     => [ 'key' => 'layout_services_v1', 'name' => 'services-v1', 'label' => \__( 'Services V1', 'simple-rms-theme' ), 'description' => \__( 'Service cards generated from the saved services list.', 'simple-rms-theme' ), 'sub_fields' => [] ],
			'gallery-grid'    => [ 'key' => 'layout_gallery_grid', 'name' => 'gallery-grid', 'label' => \__( 'Gallery Grid', 'simple-rms-theme' ), 'description' => \__( 'Visual proof grid with placeholder images.', 'simple-rms-theme' ), 'sub_fields' => [] ],
			'testimonials-v1' => [ 'key' => 'layout_testimonials_v1', 'name' => 'testimonials-v1', 'label' => \__( 'Testimonials V1', 'simple-rms-theme' ), 'description' => \__( 'Customer proof and review excerpts.', 'simple-rms-theme' ), 'sub_fields' => [] ],
			'contact-info'    => [ 'key' => 'layout_contact_info', 'name' => 'contact-info', 'label' => \__( 'Contact Info', 'simple-rms-theme' ), 'description' => \__( 'Contact prompt and optional form shortcode area.', 'simple-rms-theme' ), 'sub_fields' => [] ],
		];
	}

	/**
	 * @param array<string,mixed> $field
	 * @param array<string,mixed> $client_data
	 * @param array<string,mixed> $copy
	 *
	 * @return mixed
	 */
	private function generic_field_value( array $field, array $client_data, array $copy ) {
		$type = \sanitize_key( (string) ( $field['type'] ?? '' ) );
		$name = (string) ( $field['name'] ?? '' );

		if ( array_key_exists( $name, $copy ) ) {
			return $this->sanitize_by_type( $copy[ $name ], $type );
		}

		switch ( $type ) {
			case 'image':
			case 'file':
			case 'url':
			case 'number':
			case 'range':
			case 'select':
			case 'radio':
				return '';

			case 'true_false':
				return false;

			case 'checkbox':
				return [];

			case 'repeater':
				return $this->generic_repeater_rows( $field, $client_data, $copy );

			case 'textarea':
			case 'wysiwyg':
				return $this->generic_paragraph( $field, $client_data, $copy );

			case 'text':
			default:
				return $this->generic_text( $field, $client_data, $copy );
		}
	}

	/**
	 * @param array<string,mixed> $field
	 * @param array<string,mixed> $client_data
	 * @param array<string,mixed> $copy
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function generic_repeater_rows( array $field, array $client_data, array $copy ): array {
		$name       = (string) ( $field['name'] ?? '' );
		$copy_items = is_array( $copy[ $name ] ?? null ) ? $copy[ $name ] : [];
		$source     = [] !== $copy_items ? $copy_items : [ [] ];
		$rows       = [];

		foreach ( array_slice( $source, 0, 3 ) as $item ) {
			$item = is_array( $item ) ? $item : [];
			$row  = [];

			foreach ( is_array( $field['sub_fields'] ?? null ) ? $field['sub_fields'] : [] as $sub_field ) {
				if ( ! is_array( $sub_field ) || empty( $sub_field['name'] ) ) {
					continue;
				}

				$row[ (string) $sub_field['name'] ] = $this->generic_field_value( $sub_field, $client_data, $item );
			}

			if ( [] !== $row ) {
				$rows[] = $row;
			}
		}

		return $rows;
	}

	/**
	 * @param array<string,mixed> $field
	 * @param array<string,mixed> $client_data
	 * @param array<string,mixed> $copy
	 */
	private function generic_text( array $field, array $client_data, array $copy ): string {
		$name    = (string) ( $field['name'] ?? '' );
		$label   = \sanitize_text_field( (string) ( $field['label'] ?? '' ) );
		$company = \sanitize_text_field( (string) ( $client_data['company_name'] ?? \__( 'Your Company', 'simple-rms-theme' ) ) );

		foreach ( [ 'headline', 'title' ] as $marker ) {
			if ( false !== strpos( $name, $marker ) ) {
				return \sanitize_text_field( (string) ( $copy['headline'] ?? sprintf( /* translators: %s: company name. */ \__( '%s Services You Can Trust', 'simple-rms-theme' ), $company ) ) );
			}
		}

		if ( false !== strpos( $name, 'subheadline' ) || false !== strpos( $name, 'eyebrow' ) ) {
			return \sanitize_text_field( (string) ( $copy['subheadline'] ?? \__( 'Trusted local experts', 'simple-rms-theme' ) ) );
		}

		if ( false !== strpos( $name, 'cta' ) || false !== strpos( $name, 'button' ) ) {
			return \sanitize_text_field( (string) ( $copy['cta_text'] ?? \__( 'Get a Free Estimate', 'simple-rms-theme' ) ) );
		}

		return '' !== $label ? $label : \__( 'Placeholder content', 'simple-rms-theme' );
	}

	/**
	 * @param array<string,mixed> $field
	 * @param array<string,mixed> $client_data
	 * @param array<string,mixed> $copy
	 */
	private function generic_paragraph( array $field, array $client_data, array $copy ): string {
		$company  = \sanitize_text_field( (string) ( $client_data['company_name'] ?? \__( 'Your Company', 'simple-rms-theme' ) ) );
		$location = implode( ', ', array_filter( array_map( 'sanitize_text_field', [ $client_data['company_city'] ?? '', $client_data['company_state'] ?? '' ] ) ) );
		$text     = $copy['text'] ?? sprintf( /* translators: 1: company name, 2: optional location. */ \__( '%1$s provides dependable service%2$s with clear communication and quality workmanship.', 'simple-rms-theme' ), $company, '' !== $location ? ' in ' . $location : '' );

		return \wp_kses_post( (string) $text );
	}

	/**
	 * @param mixed  $value
	 * @param string $type
	 *
	 * @return mixed
	 */
	private function sanitize_by_type( $value, string $type ) {
		if ( is_array( $value ) ) {
			return array_map(
				function ( $item ) use ( $type ) {
					return $this->sanitize_by_type( $item, $type );
				},
				$value
			);
		}

		if ( 'wysiwyg' === $type || 'textarea' === $type ) {
			return \wp_kses_post( (string) $value );
		}

		if ( 'url' === $type ) {
			$value = (string) $value;

			return 0 === strpos( $value, '#' ) || 0 === strpos( $value, '/' ) ? \sanitize_text_field( $value ) : \esc_url_raw( $value );
		}

		return \sanitize_text_field( (string) $value );
	}

	private function normalize_layout_key( string $layout_key ): string {
		$layout_key = \sanitize_key( $layout_key );

		return 'cta-bar' === $layout_key ? 'cta-v1' : $layout_key;
	}
}
