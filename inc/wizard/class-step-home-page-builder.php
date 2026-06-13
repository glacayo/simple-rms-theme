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
	private $harness;
	private $reviewer;

	public function __construct( ?Logger $logger = null, ?State_Manager $state_manager = null, ?Content_Builder $content_builder = null, ?Flexible_Content_Layouts $layout_repository = null, ?AI_Content_Harness $harness = null, ?AI_Content_Reviewer $reviewer = null ) {
		$this->logger            = $logger ?? new Logger();
		$this->state_manager     = $state_manager ?? new State_Manager();
		$this->content_builder   = $content_builder ?? new Content_Builder( $this->logger, $this->state_manager );
		$this->layout_repository = $layout_repository ?? new Flexible_Content_Layouts();
		$this->harness           = $harness ?? new AI_Content_Harness();
		$this->reviewer          = $reviewer;
	}

	/**
	 * Run the Home page builder.
	 *
	 * @param array<string,mixed> $payload Step payload.
	 *
	 * @return array<string,mixed>|\WP_Error
	 */
	public function run( array $payload ) {
		$section_rows = $this->selected_section_rows( $payload );
		$sections     = array_column( $section_rows, 'layout' );

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

		$client_data = is_array( $state['client_data'] ?? null ) ? $state['client_data'] : [];
		$missing     = $this->harness->validate_required_context( $client_data );

		if ( [] !== $missing ) {
			$this->state_manager->set_step_status( self::STEP, 'failed' );

			return new \WP_Error(
				'rms_wizard_home_required_client_data_missing',
				sprintf(
					/* translators: %s: comma-separated missing client data keys. */
					\__( 'Missing required client data: %s. Complete your client profile before generating.', 'simple-rms-theme' ),
					implode( ', ', $missing )
				),
				[ 'status' => 400 ]
			);
		}

		$client_context         = $this->harness->get_harness_context( $client_data );
		$prepared_sections      = [];
		$prior_section_payloads = [];

		foreach ( $section_rows as $row ) {
			$section_key         = (string) $row['layout'];
			$item_count          = (int) $row['item_count'];
			$overrides           = $this->generate_section_overrides( $section_key, $item_count, $client_context, $ai_config, $prior_section_payloads );
			$prepared_sections[] = $this->content_builder->prepare_image_fallbacks( $this->section_data( $section_key, $client_data, $overrides, $item_count ) );
			$prior_section_payloads[] = [
				'layout'     => $section_key,
				'item_count' => $item_count,
				'payload'    => $overrides,
			];
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
		$state['home_section_rows']      = $section_rows;
		$state['home_sections']          = $prepared_sections;

		$this->state_manager->save_state( $state );
		$this->state_manager->set_step_status( self::STEP, 'complete' );
		$this->maybe_mark_completed();
		$this->logger->log( 'info', 'Wizard Home page sections built.', [ 'post_id' => $post_id, 'sections' => $sections ] );

		return [ 'post_id' => $post_id, 'sections' => $sections ];
	}

	private function selected_section_rows( array $payload ): array {
		$raw  = is_array( $payload['sections'] ?? null ) ? $payload['sections'] : ( is_array( $payload['selected_sections'] ?? null ) ? $payload['selected_sections'] : [] );
		$rows = [];

		foreach ( $raw as $key => $value ) {
			$item_count = 0;

			if ( is_array( $value ) ) {
				$section_key = $this->normalize_section_key( (string) ( $value['layout'] ?? $value['section_key'] ?? $value['key'] ?? '' ) );
				$item_count  = \absint( $value['item_count'] ?? 0 );
			} elseif ( is_string( $key ) && ! is_string( $value ) ) {
				if ( ! $value ) {
					continue;
				}

				$section_key = $this->normalize_section_key( $key );
			} else {
				$section_key = $this->normalize_section_key( (string) $value );
			}

			if ( $this->layout_repository->has_layout( $section_key ) ) {
				$rows[] = [
					'layout'     => $section_key,
					'item_count' => $this->item_count( $section_key, $item_count ),
				];
			}
		}

		return $rows;
	}

	private function item_count( string $section_key, int $requested ): int {
		if ( ! $this->harness->has_fillable_fields( $section_key ) ) {
			return 0;
		}

		$defaults = [
			'slider'            => 2,
			'area-coverage-v1'  => 4,
			'badges'            => 4,
			'cta-v3'            => 3,
			'faq-v1'            => 4,
			'faq-v2'            => 4,
			'gallery-grid'      => 6,
			'portfolio-v1'      => 3,
			'portfolio-v2'      => 3,
			'portfolio-v3'      => 6,
			'services-v1'       => 3,
			'services-v2'       => 3,
			'services-v3'       => 3,
			'testimonials-v1'   => 3,
			'testimonials-v2'   => 3,
			'testimonials-v3'   => 3,
			'video-v2'          => 2,
			'vision-mission-v1' => 2,
			'vision-mission-v2' => 3,
		];

		$count = $requested > 0 ? $requested : ( $defaults[ $section_key ] ?? 1 );

		return max( 1, min( 12, $count ) );
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

	private function generate_section_overrides( string $section_key, int $item_count, array $client_context, array $ai_config, array $prior_section_payloads = [] ): array {
		$fillable = $this->harness->get_fillable_fields( $section_key );

		// Layouts with no AI-editable text fields intentionally skip the provider call.
		if ( [] === $fillable ) {
			$this->logger->log( 'info', 'Skipping AI generation for layout with no fillable fields.', [ 'section' => $section_key ] );

			return [];
		}

		$provider = \sanitize_key( (string) $ai_config['provider'] );
		$model    = \sanitize_text_field( (string) $ai_config['model'] );
		$system   = $this->harness->get_layer1() . "\n\n" . $this->harness->get_layer2( AI_Content_Harness::PAGE_HOME );
		$prompt   = $this->harness->get_layer3( $section_key, $item_count, $client_context );
		$result   = AI_Provider_Registry::make_provider( $provider )->generate(
			$model,
			$prompt,
			[
				'section_key' => $section_key,
				'client_data' => $client_context,
				'item_count'  => $item_count,
			],
			$system
		);

		if ( empty( $result['success'] ) || empty( $result['content'] ) ) {
			$this->logger->log( 'warning', 'Wizard Home section AI generation failed; fallback content used.', [ 'section' => $section_key, 'error' => $result['error'] ?? '' ] );

			return [];
		}

		$decoded = $this->decode_json_content( (string) $result['content'] );

		if ( [] === $decoded ) {
			return [];
		}

		$reviewed = $this->review_section_content( $section_key, $decoded, $prior_section_payloads, $ai_config, $client_context, $item_count );

		return $this->harness->validate_fields( $section_key, $reviewed );
	}

	private function review_section_content( string $section_key, array $decoded, array $prior_section_payloads, array $ai_config, array $client_context, int $item_count ): array {
		if ( ! $this->is_review_enabled() ) {
			return $decoded;
		}

		$review_config                   = $ai_config;
		$review_config['client_context'] = $client_context;
		$review_config['item_count']     = $item_count;

		try {
			$result = $this->reviewer()->review( $section_key, $decoded, $prior_section_payloads, $review_config );
		} catch ( \Throwable $error ) {
			unset( $error );
			$this->log_review_result(
				$section_key,
				[
					'status'     => 'fallback',
					'iterations' => 0,
					'report'     => null,
				]
			);

			return $decoded;
		}

		$this->log_review_result( $section_key, $result );

		$payload = $result['payload'] ?? null;

		return is_array( $payload ) ? $payload : $decoded;
	}

	private function log_review_result( string $section_key, array $result ): void {
		$context = [
			'section'    => $section_key,
			'status'     => $this->review_status( $result['status'] ?? '' ),
			'iterations' => max( 0, (int) ( $result['iterations'] ?? 0 ) ),
		];

		if ( $this->is_debug_mode() && is_array( $result['report'] ?? null ) ) {
			$context['report'] = $result['report'];
		}

		$this->logger->log( 'info', 'Wizard Home section AI review completed.', $context );
	}

	private function review_status( $status ): string {
		$status  = \sanitize_key( (string) $status );
		$allowed = [ 'pass', 'rewritten', 'fallback', 'skipped', 'budget_exhausted' ];

		return in_array( $status, $allowed, true ) ? $status : 'fallback';
	}

	private function is_debug_mode(): bool {
		return \defined( 'WP_DEBUG' ) && true === \constant( 'WP_DEBUG' );
	}

	private function reviewer(): AI_Content_Reviewer {
		if ( ! $this->reviewer instanceof AI_Content_Reviewer ) {
			$this->reviewer = new AI_Content_Reviewer( $this->harness );
		}

		return $this->reviewer;
	}

	private function is_review_enabled(): bool {
		if ( ! \defined( 'WIZARD_REVIEW_ENABLED' ) ) {
			return true;
		}

		$value = \constant( 'WIZARD_REVIEW_ENABLED' );

		if ( is_bool( $value ) ) {
			return $value;
		}

		if ( is_int( $value ) ) {
			return 0 !== $value;
		}

		if ( is_string( $value ) ) {
			$value = strtolower( trim( $value ) );

			if ( in_array( $value, [ '0', 'false', 'off', 'no' ], true ) ) {
				return false;
			}

			if ( in_array( $value, [ '1', 'true', 'on', 'yes' ], true ) ) {
				return true;
			}
		}

		return (bool) $value;
	}

	private function decode_json_content( string $content ): array {
		$content = trim( preg_replace( '/^```(?:json)?|```$/m', '', $content ) ?? $content );
		$data    = json_decode( $content, true );

		return is_array( $data ) ? $data : [];
	}

	private function section_data( string $section_key, array $client_data, array $copy, int $item_count ): array {
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
				$sub_field        = (string) $sub_field;
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
