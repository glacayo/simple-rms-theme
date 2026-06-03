<?php
/**
 * Wizard Home page builder step service.
 *
 * @package Simple_RMS_Theme
 */

namespace Inc\Wizard;

defined( 'ABSPATH' ) || exit;

/**
 * Builds selected ACF sections on the wizard-generated Home page.
 */
class Step_Home_Page_Builder {
	private const STEP = 'home-page-builder';

	private $logger;
	private $state_manager;
	private $content_builder;
	private $layout_repository;

	public function __construct( ?Logger $logger = null, ?State_Manager $state_manager = null, ?Content_Builder $content_builder = null, ?Flexible_Content_Layouts $layout_repository = null ) {
		$this->logger            = $logger ?? new Logger();
		$this->state_manager     = $state_manager ?? new State_Manager();
		$this->content_builder   = $content_builder ?? new Content_Builder( $this->logger, $this->state_manager );
		$this->layout_repository = $layout_repository ?? new Flexible_Content_Layouts();
	}

	/**
	 * Run the Home page builder.
	 *
	 * @param array<string,mixed> $payload Step payload.
	 *
	 * @return array<string,mixed>|\WP_Error
	 */
	public function run( array $payload ) {
		$sections = $this->selected_sections( $payload );

		if ( [] === $sections ) {
			$this->state_manager->set_step_status( self::STEP, 'failed' );

			return new \WP_Error( 'rms_wizard_home_sections_required', \__( 'Select at least one section for the Home page', 'simple-rms-theme' ), [ 'status' => 400 ] );
		}

		$state     = $this->state_manager->get_state();
		$ai_config = is_array( $state['ai_config'] ?? null ) ? $state['ai_config'] : [];

		if ( ! $this->has_ai_config( $ai_config ) ) {
			$this->state_manager->set_step_status( self::STEP, 'failed' );

			return new \WP_Error( 'rms_wizard_ai_config_required', \__( 'AI configuration required. Complete the IA Generation step first.', 'simple-rms-theme' ), [ 'status' => 400 ] );
		}

		$home_page_id = $this->home_page_id( $state );

		if ( $home_page_id <= 0 ) {
			$this->state_manager->set_step_status( self::STEP, 'failed' );

			return new \WP_Error( 'rms_wizard_home_page_not_found', \__( 'Home page not found. Regenerate pages before building the Home page.', 'simple-rms-theme' ), [ 'status' => 400 ] );
		}

		$client_data       = is_array( $state['client_data'] ?? null ) ? $state['client_data'] : [];
		$prepared_sections = [];

		foreach ( $sections as $section_key ) {
			$overrides           = $this->generate_section_overrides( $section_key, $client_data, $ai_config );
			$prepared_sections[] = $this->content_builder->prepare_image_fallbacks( $this->section_data( $section_key, $client_data, $overrides ) );
		}

		$post_id = $this->content_builder->build_page(
			[
				'id'           => $home_page_id,
				'section_only' => true,
				'sections'     => $prepared_sections,
			]
		);

		if ( $post_id <= 0 ) {
			$this->state_manager->set_step_status( self::STEP, 'failed' );

			return new \WP_Error( 'rms_wizard_home_page_save_failed', \__( 'Home page sections could not be saved.', 'simple-rms-theme' ), [ 'status' => 500 ] );
		}

		$state['selected_home_sections'] = $sections;
		$state['home_sections']          = $prepared_sections;

		$this->state_manager->save_state( $state );
		$this->state_manager->set_step_status( self::STEP, 'complete' );
		$this->maybe_mark_completed();
		$this->logger->log( 'info', 'Wizard Home page sections built.', [ 'post_id' => $post_id, 'sections' => $sections ] );

		return [ 'post_id' => $post_id, 'sections' => $sections ];
	}

	private function selected_sections( array $payload ): array {
		$raw       = is_array( $payload['sections'] ?? null ) ? $payload['sections'] : ( is_array( $payload['selected_sections'] ?? null ) ? $payload['selected_sections'] : [] );
		$sections  = [];

		foreach ( $raw as $key => $value ) {
			if ( is_string( $key ) && ! is_string( $value ) ) {
				if ( ! $value ) {
					continue;
				}

				$section_key = $this->normalize_section_key( $key );
			} else {
				$section_key = $this->normalize_section_key( (string) $value );
			}

			if ( $this->layout_repository->has_layout( $section_key ) ) {
				$sections[] = $section_key;
			}
		}

		return $sections;
	}

	private function normalize_section_key( string $section_key ): string {
		$section_key = \sanitize_key( $section_key );

		return 'cta-bar' === $section_key ? 'cta-v1' : $section_key;
	}

	private function has_ai_config( array $ai_config ): bool {
		$provider        = \sanitize_key( (string) ( $ai_config['provider'] ?? '' ) );
		$model           = \sanitize_text_field( (string) ( $ai_config['model'] ?? '' ) );
		$credential      = is_array( $ai_config['credential'] ?? null ) ? $ai_config['credential'] : [];
		$has_credentials = ! empty( $ai_config['has_credentials'] ) || ! empty( $credential['has_key'] ) || ( '' !== $provider && AI_Credential_Store::has( $provider ) );

		return '' !== $provider && '' !== $model && $has_credentials && AI_Provider_Registry::provider_exists( $provider );
	}

	private function home_page_id( array $state ): int {
		$home_slug       = \sanitize_title( (string) ( $state['home_page_slug'] ?? '' ) );
		$generated_pages = is_array( $state['generated_pages'] ?? null ) ? $state['generated_pages'] : [];

		foreach ( $generated_pages as $page ) {
			if ( ! is_array( $page ) || $home_slug !== \sanitize_title( (string) ( $page['slug'] ?? '' ) ) ) {
				continue;
			}

			$post = \get_post( \absint( $page['id'] ?? 0 ) );

			if ( $post && 'page' === $post->post_type ) {
				return (int) $post->ID;
			}
		}

		if ( '' === $home_slug ) {
			return 0;
		}

		$post = \get_page_by_path( $home_slug, \OBJECT, 'page' );

		return $post ? (int) $post->ID : 0;
	}

	private function generate_section_overrides( string $section_key, array $client_data, array $ai_config ): array {
		$provider = \sanitize_key( (string) $ai_config['provider'] );
		$model    = \sanitize_text_field( (string) $ai_config['model'] );
		$prompt   = sprintf(
			"Return compact JSON copy for the %s section of a contractor Home page. Use keys like headline, subheadline, text, cta_text, cta_url, items. Client data JSON: %s",
			$section_key,
			(string) \wp_json_encode( $client_data )
		);
		$result   = AI_Provider_Registry::make_provider( $provider )->generate( $model, $prompt, [ 'section_key' => $section_key, 'client_data' => $client_data ] );

		if ( empty( $result['success'] ) || empty( $result['content'] ) ) {
			$this->logger->log( 'warning', 'Wizard Home section AI generation failed; fallback content used.', [ 'section' => $section_key, 'error' => $result['error'] ?? '' ] );

			return [];
		}

		$decoded = $this->decode_json_content( (string) $result['content'] );

		return [] !== $decoded ? $decoded : [ 'raw' => (string) $result['content'] ];
	}

	private function decode_json_content( string $content ): array {
		$content = trim( preg_replace( '/^```(?:json)?|```$/m', '', $content ) ?? $content );
		$data    = json_decode( $content, true );

		return is_array( $data ) ? $data : [];
	}

	private function section_data( string $section_key, array $client_data, array $copy ): array {
		$company  = $this->text( $client_data['company_name'] ?? 'Your Company' );
		$location = $this->location( $client_data );
		$services = $this->services( $client_data );
		$raw      = $this->text( $copy['raw'] ?? '' );
		$text     = $this->html( $copy['text'] ?? ( '' !== $raw ? $raw : sprintf( '%s provides dependable service for homeowners and businesses%s.', $company, '' !== $location ? ' in ' . $location : '' ) ) );

			switch ( $section_key ) {
			case 'slider':
				return [
					'acf_fc_layout' => 'slider',
					'slider_slides' => [
						[
							'slide_bg_image'     => '',
							'slide_subheadline'  => $this->text( $copy['subheadline'] ?? 'Trusted Local Experts' ),
							'slide_headline'     => $this->text( $copy['headline'] ?? sprintf( '%s Services You Can Trust', $company ) ),
							'slide_text'         => $text,
							'slide_cta_text'     => $this->text( $copy['cta_text'] ?? 'Get a Free Estimate' ),
							'slide_cta_url'      => $this->url( $copy['cta_url'] ?? '#contact' ),
						],
					],
				];

			case 'about-us':
				return [
					'acf_fc_layout'         => 'about-us',
					'about_headline'        => $this->text( $copy['headline'] ?? sprintf( 'About %s', $company ) ),
					'about_subheadline'     => $this->text( $copy['subheadline'] ?? 'Built on quality, service, and trust' ),
					'about_text'            => $text,
					'about_image'           => '',
					'about_badge_years'     => $this->text( $copy['badge_years'] ?? '25' ),
					'about_badge_label'     => $this->text( $copy['badge_label'] ?? 'Years Of Experience' ),
				];

			case 'services-v1':
				return [
					'acf_fc_layout'              => 'services-v1',
					'services_v1_headline'       => $this->text( $copy['headline'] ?? 'Our Professional Services' ),
					'services_v1_subheadline'    => $this->text( $copy['subheadline'] ?? 'Quality solutions for every need' ),
					'services_v1_bg_image'       => '',
					'services_v1_services'       => $services,
					'services_v1_cta_text'       => $this->text( $copy['cta_text'] ?? 'View All Services' ),
					'services_v1_cta_url'        => $this->url( $copy['cta_url'] ?? '/services/' ),
				];

			case 'gallery-grid':
				return [
					'acf_fc_layout' => 'gallery-grid',
					'gallery_items' => array_map(
						static function ( array $service ): array {
							return [ 'gallery_thumbnail' => '', 'gallery_full' => '', 'gallery_label' => $service['service_title'] ];
						},
						array_slice( $services, 0, 6 )
					),
				];

			case 'testimonials-v1':
				return [
					'acf_fc_layout'                 => 'testimonials-v1',
					'testimonials_v1_headline'      => $this->text( $copy['headline'] ?? 'What Our Clients Say' ),
					'testimonials_v1_subheadline'   => $this->text( $copy['subheadline'] ?? 'Real reviews from real customers' ),
					'testimonials_v1_items'         => $this->testimonials( $copy, $company ),
				];

			case 'contact-info':
				return [
					'acf_fc_layout'                => 'contact-info',
					'contact_info_headline'        => $this->text( $copy['headline'] ?? 'Get in Touch' ),
					'contact_info_intro'           => $text,
					'contact_info_form_shortcode'  => '',
				];

			case 'cta-v1':
				return [
					'acf_fc_layout'       => 'cta-v1',
					'cta_v1_headline'     => $this->text( $copy['headline'] ?? 'Ready to Start Your Project?' ),
					'cta_v1_text'         => $this->text( $copy['text'] ?? 'Contact us today for a clear estimate and dependable service.' ),
					'cta_v1_button_text'  => $this->text( $copy['cta_text'] ?? 'Get Your Free Estimate' ),
					'cta_v1_button_url'   => $this->url( $copy['cta_url'] ?? '#contact' ),
				];

			default:
				return $this->layout_repository->build_generic_section( $section_key, $client_data, $copy );
		}
	}

	private function services( array $client_data ): array {
		$services = [];

		foreach ( is_array( $client_data['company_services'] ?? null ) ? $client_data['company_services'] : [] as $service ) {
			if ( ! is_array( $service ) || empty( $service['service_name'] ) ) {
				continue;
			}

			$services[] = [
				'service_title' => $this->text( $service['service_name'] ),
				'service_text'  => $this->text( $service['service_short_description'] ?? 'Professional service delivered with care and attention to detail.' ),
			];
		}

		return [] !== $services ? $services : [
			[ 'service_title' => 'Installation', 'service_text' => 'Quality installation work using dependable materials and proven methods.' ],
			[ 'service_title' => 'Repair', 'service_text' => 'Fast repairs that address problems before they become costly issues.' ],
			[ 'service_title' => 'Maintenance', 'service_text' => 'Preventive care that helps protect your property for the long term.' ],
		];
	}

	private function testimonials( array $copy, string $company ): array {
		$items = is_array( $copy['items'] ?? null ) ? $copy['items'] : [];

		if ( [] === $items ) {
			$items = [ [ 'quote' => sprintf( '%s delivered exactly what they promised. The team was professional, responsive, and easy to work with.', $company ), 'author' => 'Happy Customer', 'role' => 'Homeowner' ] ];
		}

		return array_map(
			function ( array $item ): array {
				return [
					'testimonial_quote'  => $this->text( $item['quote'] ?? $item['testimonial_quote'] ?? '' ),
					'testimonial_author' => $this->text( $item['author'] ?? $item['testimonial_author'] ?? 'Happy Customer' ),
					'testimonial_role'   => $this->text( $item['role'] ?? $item['testimonial_role'] ?? 'Customer' ),
					'testimonial_stars'  => max( 1, min( 5, \absint( $item['stars'] ?? $item['testimonial_stars'] ?? 5 ) ) ),
				];
			},
			array_slice( $items, 0, 3 )
		);
	}

	private function location( array $client_data ): string {
		return implode( ', ', array_filter( array_map( [ $this, 'text' ], [ $client_data['company_city'] ?? '', $client_data['company_state'] ?? '', $client_data['company_country'] ?? '' ] ) ) );
	}

	private function text( $value ): string {
		return \sanitize_text_field( (string) $value );
	}

	private function html( $value ): string {
		return \wp_kses_post( (string) $value );
	}

	private function url( $value ): string {
		$value = (string) $value;

		return 0 === strpos( $value, '#' ) || 0 === strpos( $value, '/' ) ? $this->text( $value ) : \esc_url_raw( $value );
	}

	private function maybe_mark_completed(): void {
		$state    = $this->state_manager->get_state();
		$required = [ 'dependencies', 'acf-import', 'client-data', 'generate-pages', 'menu-setup', 'ia-generation', 'home-page-builder' ];

		foreach ( $required as $step ) {
			if ( 'complete' !== ( $state['step_status'][ $step ] ?? '' ) ) {
				return;
			}
		}

		$this->state_manager->mark_completed();
	}

}
