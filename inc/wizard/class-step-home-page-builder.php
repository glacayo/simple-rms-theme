<?php
/**
 * Wizard Home page builder step service.
 *
 * @package Simple_RMS_Theme
 */

namespace Inc\Wizard;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-section-assembler.php';

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
	private $canonical_store;
	private $section_assembler;

	public function __construct( ?Logger $logger = null, ?State_Manager $state_manager = null, ?Content_Builder $content_builder = null, ?Flexible_Content_Layouts $layout_repository = null, ?AI_Content_Harness $harness = null, ?AI_Content_Reviewer $reviewer = null, ?Canonical_Section_Store $canonical_store = null ) {
		$this->logger            = $logger ?? new Logger();
		$this->state_manager     = $state_manager ?? new State_Manager();
		$this->content_builder   = $content_builder ?? new Content_Builder( $this->logger, $this->state_manager );
		$this->layout_repository = $layout_repository ?? new Flexible_Content_Layouts();
		$this->harness           = $harness ?? new AI_Content_Harness();
		$this->reviewer          = $reviewer;
		$this->canonical_store   = $canonical_store ?? new Canonical_Section_Store();
		$this->section_assembler = new Section_Assembler( $this->harness );
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

			$seo_intent = $this->harness->normalize_home_seo_intent( $payload );

			if ( \is_wp_error( $seo_intent ) ) {
				$this->state_manager->set_step_status( self::STEP, 'failed' );

				return $seo_intent;
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
				$overrides           = $this->generate_section_overrides( $section_key, $item_count, $client_context, $ai_config, $prior_section_payloads, $seo_intent );
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

			$seo_state = $this->harness->persist_home_seo_intent( $seo_intent );
			$fresh     = $this->state_manager->get_state();
			$fresh['selected_home_sections'] = $sections;
			$fresh['home_section_rows']      = $section_rows;
			$fresh['home_sections']          = $prepared_sections;
			$fresh['home_seo_targeting']     = $seo_state;
			$fresh['canonical_sections']     = $this->first_write_canonical_sections( $prepared_sections );

			$this->state_manager->save_state( $fresh );
			$this->state_manager->set_step_status( self::STEP, 'complete' );
			$this->maybe_mark_completed();
			$this->logger->log( 'info', 'Wizard Home page sections built.', [ 'post_id' => $post_id, 'sections' => $sections ] );

			return [ 'post_id' => $post_id, 'sections' => $sections, 'seo_targeting' => $seo_state ];
		}

	/**
	 * First-write reusable prepared rows into the canonical store.
	 *
	 * Skips keyword layouts (hero / seo-content). Never overwrites existing entries.
	 *
	 * @param array<int,array<string,mixed>> $prepared_sections Prepared ACF rows.
	 *
	 * @return array<string,array{has_payload:bool,generated_at:string}>
	 */
	private function first_write_canonical_sections( array $prepared_sections ): array {
		foreach ( $prepared_sections as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$layout = \sanitize_key( (string) ( $row['acf_fc_layout'] ?? '' ) );

			if ( '' === $layout || ! $this->harness->is_reusable_layout( $layout ) ) {
				continue;
			}

			$this->canonical_store->set_if_empty( $layout, $row );
		}

		return $this->canonical_store->summary();
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

		private function generate_section_overrides( string $section_key, int $item_count, array $client_context, array $ai_config, array $prior_section_payloads = [], array $seo_intent = [] ): array {
			$fillable = $this->harness->get_fillable_fields( $section_key );

			// Layouts with no AI-editable text fields intentionally skip the provider call.
			if ( [] === $fillable ) {
				$this->logger->log( 'info', 'Skipping AI generation for layout with no fillable fields.', [ 'section' => $section_key ] );

				return [];
			}

			$keywords      = $this->harness->home_seo_keywords_for_layout( $seo_intent, $section_key );
			$review_priors = $this->harness->is_keyword_layout( $section_key )
				? $prior_section_payloads
				: $this->harness->filter_neutral_priors( $prior_section_payloads );
			$provider      = \sanitize_key( (string) $ai_config['provider'] );
			$model         = \sanitize_text_field( (string) $ai_config['model'] );
			$system        = $this->harness->get_layer1() . "\n\n" . $this->harness->get_layer2( AI_Content_Harness::PAGE_HOME );
			$prompt        = $this->harness->get_layer3( $section_key, $item_count, $client_context, AI_Content_Harness::PAGE_HOME, $keywords );
			$result        = AI_Provider_Registry::make_provider( $provider )->generate(
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

			$reviewed = $this->review_section_content( $section_key, $decoded, $review_priors, $ai_config, $client_context, $item_count, $keywords );

			return $this->harness->validate_fields( $section_key, $reviewed );
		}

		private function review_section_content( string $section_key, array $decoded, array $prior_section_payloads, array $ai_config, array $client_context, int $item_count, array $keywords = [] ): array {
			if ( ! $this->is_review_enabled() ) {
				return $decoded;
			}

			$review_config                   = $ai_config;
			$review_config['client_context'] = $client_context;
			$review_config['item_count']     = $item_count;

			if ( '' !== trim( (string) ( $keywords['primary_keyword'] ?? '' ) ) ) {
				$review_config['keyword_intent'] = $keywords;
			}

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
		return $this->section_assembler->section_data( $section_key, $client_data, $copy, $item_count );
	}

	private function maybe_mark_completed(): void {
		$state    = $this->state_manager->get_state();
		// Shared source of truth via Step_Controller::get_required_steps().
		$required = Step_Controller::get_required_steps();

		foreach ( $required as $step ) {
			if ( 'complete' !== ( $state['step_status'][ $step ] ?? '' ) ) {
				return;
			}
		}

		$this->state_manager->mark_completed();
	}

}
