<?php
/**
 * Wizard Landing page builder step service.
 *
 * @package Simple_RMS_Theme
 */

namespace Inc\Wizard;

defined( 'ABSPATH' ) || exit;

/**
 * Builds N SEO/Ads landing pages from canonical reusable sections + keyword copy.
 *
 * Includes identity preflight, lazy canonical bootstrap, page upsert with
 * template/type meta, Yoast/noindex final-state sync, and menu reconciliation.
 */
class Step_Landing_Page_Builder {
	private const STEP     = 'landing-page-builder';
	private const TEMPLATE = 'pages/landing-page.php';

	/**
	 * Default section order aligned with pages/landing-page.php.
	 *
	 * @var array<int,array{layout:string}>
	 */
	private const DEFAULT_SECTIONS = [
		[ 'layout' => 'hero' ],
		[ 'layout' => 'seo-content' ],
		[ 'layout' => 'vision-mission-v1' ],
		[ 'layout' => 'badges' ],
		[ 'layout' => 'portfolio-v1' ],
		[ 'layout' => 'seo-content' ],
		[ 'layout' => 'testimonials-v1' ],
		[ 'layout' => 'seo-content' ],
	];

	private $logger;
	private $state_manager;
	private $content_builder;
	private $layout_repository;
	private $harness;
	private $reviewer;
	private $canonical_store;
	private $menu_builder;
	private $yoast_meta_writer;

	public function __construct(
		?Logger $logger = null,
		?State_Manager $state_manager = null,
		?Content_Builder $content_builder = null,
		?Flexible_Content_Layouts $layout_repository = null,
		?AI_Content_Harness $harness = null,
		?AI_Content_Reviewer $reviewer = null,
		?Canonical_Section_Store $canonical_store = null,
		?Menu_Builder $menu_builder = null,
		?Yoast_Meta_Writer $yoast_meta_writer = null
	) {
		$this->logger            = $logger ?? new Logger();
		$this->state_manager     = $state_manager ?? new State_Manager();
		$this->content_builder   = $content_builder ?? new Content_Builder( $this->logger, $this->state_manager );
		$this->layout_repository = $layout_repository ?? new Flexible_Content_Layouts();
		$this->harness           = $harness ?? new AI_Content_Harness();
		$this->reviewer          = $reviewer;
		$this->canonical_store   = $canonical_store ?? new Canonical_Section_Store();
		$this->menu_builder      = $menu_builder ?? new Menu_Builder( $this->logger );
		$this->yoast_meta_writer = $yoast_meta_writer ?? new Yoast_Meta_Writer( $this->logger );
	}

	/**
	 * Run the Landing page builder.
	 *
	 * @param array<string,mixed> $payload Step payload.
	 *
	 * @return array<string,mixed>|\WP_Error
	 */
	public function run( array $payload ) {
		if ( $this->truthy( $payload['skip_all'] ?? false ) ) {
			$state             = $this->state_manager->get_state();
			$existing_landings = $this->normalize_state_landings(
				is_array( $state['landing_pages'] ?? null ) ? $state['landing_pages'] : []
			);

			// Existing landings must still receive final-state robots/menu reconciliation.
			// No existing landings → skip-all is a pure no-op complete.
			if ( [] !== $existing_landings ) {
				foreach ( $existing_landings as $landing_key => $row ) {
					if ( ! is_array( $row ) ) {
						continue;
					}

					$post_id     = (int) ( $row['id'] ?? 0 );
					$slug        = (string) ( $row['slug'] ?? '' );
					$log_context = [
						'landing_key' => (string) ( $row['landing_key'] ?? $landing_key ),
						'slug'        => $slug,
						'skip_all'    => true,
					];

					if ( $post_id <= 0 || 'page' !== \get_post_type( $post_id ) ) {
						$post_id = $this->recover_build_page_post_id( $post_id, $slug );
					}

					if ( $post_id <= 0 || 'page' !== \get_post_type( $post_id ) ) {
						$this->mark_step_status( 'failed' );

						return new \WP_Error(
							'rms_wizard_landing_skip_all_missing_post',
							sprintf(
								/* translators: %s: landing key or slug. */
								\__( 'Skip-all could not reconcile landing "%s": page not found.', 'simple-rms-theme' ),
								'' !== (string) ( $row['landing_key'] ?? '' )
									? (string) $row['landing_key']
									: ( '' !== $slug ? $slug : (string) $landing_key )
							),
							array_merge( [ 'status' => 500 ], $log_context )
						);
					}

					$final_state = $this->sync_landing_final_state( $post_id, $row, $log_context );

					if ( \is_wp_error( $final_state ) ) {
						$this->mark_step_status( 'failed' );
						$this->logger->log(
							'error',
							'Skip-all final-state sync failed; step not marked complete.',
							array_merge(
								$log_context,
								[
									'post_id'       => $post_id,
									'error_code'    => $final_state->get_error_code(),
									'error_message' => $final_state->get_error_message(),
								]
							)
						);

						return $final_state;
					}
				}
			}

			if ( ! $this->mark_step_status( 'complete' ) ) {
				return new \WP_Error(
					'rms_wizard_landing_status_persist_failed',
					\__( 'Landing step completed but status could not be persisted.', 'simple-rms-theme' ),
					[ 'status' => 500 ]
				);
			}
			$this->maybe_mark_completed();
			$state = $this->state_manager->get_state();

			return [
				'skipped'       => true,
				'landing_pages' => is_array( $state['landing_pages'] ?? null ) ? $state['landing_pages'] : [],
			];
		}

		$state     = $this->state_manager->get_state();
		$ai_config = is_array( $state['ai_config'] ?? null ) ? $state['ai_config'] : [];

		if ( ! $this->has_ai_config( $ai_config ) ) {
			$this->mark_step_status( 'failed' );

			return new \WP_Error( 'rms_wizard_ai_config_required', \__( 'AI configuration required. Complete the IA Generation step first.', 'simple-rms-theme' ), [ 'status' => 400 ] );
		}

		$client_data = is_array( $state['client_data'] ?? null ) ? $state['client_data'] : [];
		$missing     = $this->harness->validate_required_context( $client_data );

		if ( [] !== $missing ) {
			$this->mark_step_status( 'failed' );

			return new \WP_Error(
				'rms_wizard_landing_required_client_data_missing',
				sprintf(
					/* translators: %s: comma-separated missing client data keys. */
					\__( 'Missing required client data: %s. Complete your client profile before generating.', 'simple-rms-theme' ),
					implode( ', ', $missing )
				),
				[ 'status' => 400 ]
			);
		}

		$existing_landings = $this->normalize_state_landings( is_array( $state['landing_pages'] ?? null ) ? $state['landing_pages'] : [] );
		$parsed            = $this->parse_landings_payload( $payload, $existing_landings );

		if ( \is_wp_error( $parsed ) ) {
			$this->mark_step_status( 'failed' );

			return $parsed;
		}

		/** @var array{rows:array<int,array<string,mixed>>,replace_map:array<string,bool>} $parsed */
		$rows        = $parsed['rows'];
		$replace_map = $parsed['replace_map'];

		if ( [] === $rows ) {
			$this->mark_step_status( 'failed' );

			return new \WP_Error(
				'rms_wizard_landings_required',
				\__( 'Add at least one landing page or use skip-all to complete without landings.', 'simple-rms-theme' ),
				[ 'status' => 400 ]
			);
		}

		$required_layouts = $this->collect_required_reusable_layouts( $rows );
		$bootstrap        = $this->ensure_canonical_reusables( $required_layouts, $state, $client_data, $ai_config );

		if ( \is_wp_error( $bootstrap ) ) {
			$this->mark_step_status( 'failed' );

			return $bootstrap;
		}

		$client_context = $this->harness->get_harness_context( $client_data );
		$landing_pages  = $existing_landings;
		$built          = [];
		$prior_payloads = [];
		$built_ids      = [];

		foreach ( $rows as $row ) {
			try {
				$result = $this->build_one_landing( $row, $replace_map, $client_data, $client_context, $ai_config, $landing_pages, $prior_payloads );
			} catch ( \Throwable $exception ) {
				// Convert thrown failures so partial persistence still runs before returning.
				$landing_key = (string) ( $row['landing_key'] ?? '' );
				$slug        = (string) ( $row['slug'] ?? '' );

				$this->logger->log(
					'error',
					'Wizard landing build threw an exception.',
					[
						'landing_key'     => $landing_key,
						'slug'            => $slug,
						'exception_class' => get_class( $exception ),
						'message'         => $exception->getMessage(),
					]
				);

				$result = new \WP_Error(
					'rms_wizard_landing_build_exception',
					sprintf(
						/* translators: 1: landing slug or key, 2: exception message. */
						\__( 'Landing "%1$s" failed unexpectedly: %2$s', 'simple-rms-theme' ),
						'' !== $slug ? $slug : $landing_key,
						$exception->getMessage()
					),
					[
						'status'          => 500,
						'landing_key'     => $landing_key,
						'slug'            => $slug,
						'exception_class' => get_class( $exception ),
					]
				);
			}

			if ( \is_wp_error( $result ) ) {
				// Persist successful landings before failing so partial progress is not lost.
				$this->persist_partial_landing_progress( $state, $landing_pages, $built_ids, $result );

				return $result;
			}

			$landing_pages[ $result['landing_key'] ] = $result['entry'];
			$built[]                                 = $result['entry'];
			$built_ids[]                             = (int) ( $result['entry']['id'] ?? 0 );
			$prior_payloads                          = array_merge( $prior_payloads, $result['prior_payloads'] );
		}

		$state['landing_pages']      = array_values( $landing_pages );
		$state['canonical_sections'] = $this->canonical_store->summary();

		if ( ! $this->persist_wizard_state( $state, 'full success' ) ) {
			$this->mark_step_status( 'failed' );

			return new \WP_Error(
				'rms_wizard_landing_state_persist_failed',
				\__( 'Landing pages were built but wizard state could not be persisted.', 'simple-rms-theme' ),
				[
					'status'    => 500,
					'post_ids'  => array_values( array_filter( $built_ids ) ),
					'built'     => count( $built ),
				]
			);
		}

		if ( ! $this->mark_step_status( 'complete' ) ) {
			return new \WP_Error(
				'rms_wizard_landing_status_persist_failed',
				\__( 'Landing pages were saved but step status could not be marked complete.', 'simple-rms-theme' ),
				[ 'status' => 500, 'post_ids' => array_values( array_filter( $built_ids ) ) ]
			);
		}

		$this->maybe_mark_completed();
		$this->logger->log(
			'info',
			'Wizard landing pages built.',
			[
				'count'    => count( $built ),
				'post_ids' => array_values( array_filter( $built_ids ) ),
			]
		);

		return [
			'landing_pages'      => array_values( $landing_pages ),
			'built'              => $built,
			'canonical_sections' => $state['canonical_sections'],
		];
	}

	/**
	 * @param array<string,mixed>              $payload
	 * @param array<string,array<string,mixed>> $existing_landings Keyed by landing_key.
	 *
	 * @return array{rows:array<int,array<string,mixed>>,replace_map:array<string,bool>}|\WP_Error
	 */
	private function parse_landings_payload( array $payload, array $existing_landings ) {
		$raw_landings = is_array( $payload['landings'] ?? null ) ? $payload['landings'] : [];
		$replace_raw  = is_array( $payload['replace_canonical'] ?? null ) ? $payload['replace_canonical'] : [];
		$confirm      = $this->truthy( $payload['confirm_replace_canonical'] ?? false );
		$replace_map  = [];

		foreach ( $replace_raw as $layout => $flag ) {
			$layout = $this->normalize_layout( (string) $layout );

			if ( '' !== $layout && $this->truthy( $flag ) ) {
				if ( ! $confirm ) {
					return new \WP_Error(
						'rms_wizard_replace_canonical_unconfirmed',
						\__( 'Confirm replace canonical before overwriting shared reusable sections.', 'simple-rms-theme' ),
						[ 'status' => 400 ]
					);
				}

				$replace_map[ $layout ] = true;
			}
		}

		$rows          = [];
		$seen_ids      = [];
		$seen_keys     = [];
		$seen_slugs    = [];
		$claimed_ids   = [];
		$claimed_keys  = [];
		$claimed_slugs = [];

		foreach ( $existing_landings as $entry ) {
			$id  = (int) ( $entry['id'] ?? 0 );
			$key = (string) ( $entry['landing_key'] ?? '' );
			$slug = \sanitize_title( (string) ( $entry['slug'] ?? '' ) );

			if ( $id > 0 ) {
				$claimed_ids[ $id ] = $key;
			}

			if ( '' !== $key ) {
				$claimed_keys[ $key ] = $entry;
			}

			if ( '' !== $slug ) {
				$claimed_slugs[ $slug ] = $key;
			}
		}

		foreach ( $raw_landings as $raw ) {
			if ( ! is_array( $raw ) || $this->truthy( $raw['skipped'] ?? false ) ) {
				continue;
			}

			$title = \sanitize_text_field( (string) ( $raw['title'] ?? '' ) );
			$slug  = \sanitize_title( (string) ( $raw['slug'] ?? $title ) );
			$type  = \sanitize_key( (string) ( $raw['landing_type'] ?? 'seo' ) );
			$type  = in_array( $type, [ 'seo', 'ads' ], true ) ? $type : 'seo';

			$keywords = $this->harness->normalize_keywords(
				[
					'primary_keyword' => $raw['primary_keyword'] ?? '',
					'subkeywords'     => $raw['subkeywords'] ?? [],
				]
			);

			if ( '' === $keywords['primary_keyword'] ) {
				return new \WP_Error(
					'rms_wizard_landing_keyword_required',
					\__( 'Each landing requires a non-empty primary keyword.', 'simple-rms-theme' ),
					[ 'status' => 400 ]
				);
			}

			if ( '' === $title || '' === $slug ) {
				return new \WP_Error(
					'rms_wizard_landing_identity_required',
					\__( 'Each landing requires a title and slug.', 'simple-rms-theme' ),
					[ 'status' => 400 ]
				);
			}

			$id  = isset( $raw['id'] ) && '' !== (string) $raw['id'] && null !== $raw['id'] ? \absint( $raw['id'] ) : 0;
			$key = \sanitize_key( (string) ( $raw['landing_key'] ?? '' ) );

			if ( $id > 0 ) {
				if ( isset( $seen_ids[ $id ] ) ) {
					return new \WP_Error( 'rms_wizard_landing_duplicate_id', \__( 'Duplicate landing id values are not allowed in one run.', 'simple-rms-theme' ), [ 'status' => 400 ] );
				}

				$seen_ids[ $id ] = true;
			}

			if ( '' !== $key ) {
				if ( isset( $seen_keys[ $key ] ) ) {
					return new \WP_Error( 'rms_wizard_landing_duplicate_key', \__( 'Duplicate landing_key values are not allowed in one run.', 'simple-rms-theme' ), [ 'status' => 400 ] );
				}

				$seen_keys[ $key ] = true;
			}

			if ( isset( $seen_slugs[ $slug ] ) ) {
				return new \WP_Error( 'rms_wizard_landing_duplicate_slug', \__( 'Duplicate landing slugs are not allowed in one run.', 'simple-rms-theme' ), [ 'status' => 400 ] );
			}

			$seen_slugs[ $slug ] = true;

			$match = $this->match_existing_landing( $id, $key, $slug, $existing_landings, $claimed_ids, $claimed_keys, $claimed_slugs );

			if ( \is_wp_error( $match ) ) {
				return $match;
			}

			$matched_key = (string) ( $match['landing_key'] ?? '' );
			$matched_id  = (int) ( $match['id'] ?? 0 );

			// Rename collision: slug belongs to a different landing in state.
			if ( isset( $claimed_slugs[ $slug ] ) && $claimed_slugs[ $slug ] !== $matched_key && '' !== $matched_key ) {
				return new \WP_Error(
					'rms_wizard_landing_slug_collision',
					sprintf(
						/* translators: %s: page slug. */
						\__( 'Landing slug "%s" collides with another landing page.', 'simple-rms-theme' ),
						$slug
					),
					[ 'status' => 400 ]
				);
			}

			// Page collision when creating or claiming by slug (non-landing or other landing).
			$slug_page = \get_page_by_path( $slug, \OBJECT, 'page' );

			if ( $slug_page ) {
				$slug_page_id    = (int) $slug_page->ID;
				$slug_is_landing = $this->page_has_landing_type( $slug_page_id );

				if ( $matched_id > 0 && $slug_page_id !== $matched_id ) {
					return new \WP_Error(
						'rms_wizard_landing_slug_collision',
						sprintf(
							/* translators: %s: page slug. */
							\__( 'Landing slug "%s" collides with an existing page.', 'simple-rms-theme' ),
							$slug
						),
						[ 'status' => 400 ]
					);
				}

				if ( $matched_id <= 0 && ! $slug_is_landing ) {
					return new \WP_Error(
						'rms_wizard_landing_slug_collision',
						sprintf(
							/* translators: %s: page slug. */
							\__( 'Landing slug "%s" collides with an existing non-landing page.', 'simple-rms-theme' ),
							$slug
						),
						[ 'status' => 400 ]
					);
				}

				// Slug fallback may only resolve when the existing page is a landing.
				if ( $matched_id <= 0 && $slug_is_landing ) {
					$matched_id = $slug_page_id;
				}
			}

			if ( '' === $matched_key ) {
				$matched_key = '' !== $key ? $key : $this->mint_landing_key();
			}

			$sections = $this->normalize_section_rows( is_array( $raw['sections'] ?? null ) ? $raw['sections'] : [] );

			$rows[] = [
				'id'              => $matched_id,
				'landing_key'     => $matched_key,
				'title'           => $title,
				'slug'            => $slug,
				'landing_type'    => $type,
				'menu_eligible'   => 'seo' === $type,
				'primary_keyword' => $keywords['primary_keyword'],
				'subkeywords'     => $keywords['subkeywords'],
				'sections'        => $sections,
			];
		}

		return [
			'rows'        => $rows,
			'replace_map' => $replace_map,
		];
	}

	/**
	 * Deterministic identity match order: id → landing_key → slug.
	 * When both id and landing_key are non-empty they MUST refer to the same state row.
	 *
	 * @param array<string,array<string,mixed>> $existing_landings
	 * @param array<int,string>                 $claimed_ids
	 * @param array<string,array<string,mixed>> $claimed_keys
	 * @param array<string,string>              $claimed_slugs
	 *
	 * @return array{id:int,landing_key:string}|\WP_Error
	 */
	private function match_existing_landing( int $id, string $key, string $slug, array $existing_landings, array $claimed_ids, array $claimed_keys, array $claimed_slugs ) {
		unset( $existing_landings ); // Claim maps are the authoritative index.

		// Stale cross-pair: payload pairs id+key that point at different rows.
		if ( $id > 0 && '' !== $key ) {
			$id_key     = $claimed_ids[ $id ] ?? null;
			$key_entry  = $claimed_keys[ $key ] ?? null;
			$key_id     = is_array( $key_entry ) ? (int) ( $key_entry['id'] ?? 0 ) : 0;

			if ( null !== $id_key && $id_key !== $key ) {
				return new \WP_Error(
					'rms_wizard_landing_identity_mismatch',
					\__( 'Landing id and landing_key refer to different existing landings.', 'simple-rms-theme' ),
					[ 'status' => 400, 'id' => $id, 'landing_key' => $key, 'id_landing_key' => $id_key ]
				);
			}

			if ( $key_id > 0 && $key_id !== $id ) {
				return new \WP_Error(
					'rms_wizard_landing_identity_mismatch',
					\__( 'Landing id and landing_key refer to different existing landings.', 'simple-rms-theme' ),
					[ 'status' => 400, 'id' => $id, 'landing_key' => $key, 'key_id' => $key_id ]
				);
			}
		}

		// 1) Match by id (strongest).
		if ( $id > 0 ) {
			if ( isset( $claimed_ids[ $id ] ) ) {
				$matched_key = $claimed_ids[ $id ];

				if ( '' !== $key && $key !== $matched_key ) {
					return new \WP_Error(
						'rms_wizard_landing_identity_mismatch',
						\__( 'Landing id and landing_key refer to different existing landings.', 'simple-rms-theme' ),
						[ 'status' => 400, 'id' => $id, 'landing_key' => $key, 'id_landing_key' => $matched_key ]
					);
				}

				return [
					'id'          => $id,
					'landing_key' => $matched_key,
				];
			}

			// ID supplied but not a known landing in state — only accept if page has landing meta.
			if ( ! $this->page_has_landing_type( $id ) ) {
				return new \WP_Error(
					'rms_wizard_landing_unknown_id',
					\__( 'Landing id does not match an existing landing page.', 'simple-rms-theme' ),
					[ 'status' => 400 ]
				);
			}

			// Key must not already belong to a different post id.
			if ( '' !== $key && isset( $claimed_keys[ $key ] ) ) {
				$other_id = (int) ( $claimed_keys[ $key ]['id'] ?? 0 );

				if ( $other_id > 0 && $other_id !== $id ) {
					return new \WP_Error(
						'rms_wizard_landing_identity_mismatch',
						\__( 'Landing id and landing_key refer to different existing landings.', 'simple-rms-theme' ),
						[ 'status' => 400, 'id' => $id, 'landing_key' => $key, 'key_id' => $other_id ]
					);
				}
			}

			return [
				'id'          => $id,
				'landing_key' => '' !== $key ? $key : $this->mint_landing_key(),
			];
		}

		// 2) Match by landing_key.
		if ( '' !== $key && isset( $claimed_keys[ $key ] ) ) {
			$entry = $claimed_keys[ $key ];

			return [
				'id'          => (int) ( $entry['id'] ?? 0 ),
				'landing_key' => $key,
			];
		}

		// 3) Match by slug (landing state only; non-landing collisions handled by caller).
		if ( '' !== $slug && isset( $claimed_slugs[ $slug ] ) ) {
			$matched_key = $claimed_slugs[ $slug ];
			$entry       = $claimed_keys[ $matched_key ] ?? [];

			return [
				'id'          => (int) ( $entry['id'] ?? 0 ),
				'landing_key' => $matched_key,
			];
		}

		return [
			'id'          => 0,
			'landing_key' => $key,
		];
	}

	/**
	 * @param array<int,array<string,mixed>> $rows
	 *
	 * @return string[]
	 */
	private function collect_required_reusable_layouts( array $rows ): array {
		$layouts = [];

		foreach ( $rows as $row ) {
			foreach ( is_array( $row['sections'] ?? null ) ? $row['sections'] : [] as $section ) {
				$layout = $this->normalize_layout( (string) ( $section['layout'] ?? '' ) );

				if ( '' === $layout || ! $this->harness->is_reusable_layout( $layout ) ) {
					continue;
				}

				// Override rows still need a bootstrap fallback if generation fails.
				$layouts[ $layout ] = true;
			}
		}

		return array_keys( $layouts );
	}

	/**
	 * Lazy bootstrap: state.home_sections → Home page_sections → neutral generation.
	 *
	 * @param string[]            $required_layouts
	 * @param array<string,mixed> $state
	 * @param array<string,mixed> $client_data
	 * @param array<string,mixed> $ai_config
	 *
	 * @return true|\WP_Error
	 */
	private function ensure_canonical_reusables( array $required_layouts, array $state, array $client_data, array $ai_config ) {
		$missing = [];

		foreach ( $required_layouts as $layout ) {
			if ( ! $this->canonical_store->has( $layout ) ) {
				$missing[] = $layout;
			}
		}

		if ( [] === $missing ) {
			return true;
		}

		// (1) state.home_sections prepared reusable rows.
		$this->seed_from_section_rows( is_array( $state['home_sections'] ?? null ) ? $state['home_sections'] : [], $missing );
		$missing = $this->still_missing( $missing );

		if ( [] === $missing ) {
			return true;
		}

		// (2) Home page ACF page_sections.
		$home_id = $this->home_page_id( $state );

		if ( $home_id > 0 ) {
			$home_sections = $this->read_page_sections( $home_id );
			$this->seed_from_section_rows( $home_sections, $missing );
			$missing = $this->still_missing( $missing );
		}

		if ( [] === $missing ) {
			return true;
		}

		// (3) Neutral generation (PAGE_HOME, no keywords).
		$client_context = $this->harness->get_harness_context( $client_data );

		foreach ( $missing as $layout ) {
			if ( ! $this->layout_repository->has_layout( $layout ) ) {
				continue;
			}

			$item_count = $this->item_count( $layout, 0 );
			$overrides  = $this->generate_section_copy(
				$layout,
				$item_count,
				$client_context,
				$ai_config,
				AI_Content_Harness::PAGE_HOME,
				[],
				[],
				false,
				[ 'layout' => $layout, 'bootstrap' => true ]
			);
			$overrides  = \is_wp_error( $overrides ) ? [] : $overrides;
			$row        = $this->content_builder->prepare_image_fallbacks( $this->section_data( $layout, $client_data, $overrides, $item_count ) );

			if ( [] === $row || empty( $row['acf_fc_layout'] ) ) {
				continue;
			}

			if ( ! $this->canonical_store->set_if_empty( $layout, $row ) && ! $this->canonical_store->has( $layout ) ) {
				$this->logger->log(
					'error',
					'Canonical bootstrap first-write persistence failed.',
					[ 'layout' => $layout ]
				);
			}
		}

		$missing = $this->still_missing( $missing );

		if ( [] !== $missing ) {
			return new \WP_Error(
				'rms_wizard_canonical_bootstrap_failed',
				sprintf(
					/* translators: %s: comma-separated layout keys. */
					\__( 'Could not prepare shared reusable sections (%s). Re-run Home Page Builder or fix the Home page sections, then try again.', 'simple-rms-theme' ),
					implode( ', ', $missing )
				),
				[ 'status' => 400, 'missing_layouts' => $missing ]
			);
		}

		return true;
	}

	/**
	 * @param array<int,array<string,mixed>> $rows
	 * @param string[]                       $missing
	 */
	private function seed_from_section_rows( array $rows, array $missing ): void {
		$want = array_fill_keys( $missing, true );

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$layout = $this->normalize_layout( (string) ( $row['acf_fc_layout'] ?? $row['layout'] ?? '' ) );

			if ( '' === $layout || ! isset( $want[ $layout ] ) || ! $this->harness->is_reusable_layout( $layout ) ) {
				continue;
			}

			if ( empty( $row['acf_fc_layout'] ) ) {
				$row['acf_fc_layout'] = $layout;
			}

			if ( ! $this->is_valid_section_row( $row ) ) {
				continue;
			}

			$this->canonical_store->set_if_empty( $layout, $row );
		}
	}

	/**
	 * @param string[] $layouts
	 *
	 * @return string[]
	 */
	private function still_missing( array $layouts ): array {
		$missing = [];

		foreach ( $layouts as $layout ) {
			if ( ! $this->canonical_store->has( $layout ) ) {
				$missing[] = $layout;
			}
		}

		return $missing;
	}

	/**
	 * @param array<string,mixed>               $row
	 * @param array<string,bool>                $replace_map
	 * @param array<string,mixed>               $client_data
	 * @param array<string,mixed>               $client_context
	 * @param array<string,mixed>               $ai_config
	 * @param array<string,array<string,mixed>> $landing_pages
	 * @param array<int,array<string,mixed>>    $prior_payloads
	 *
	 * @return array{entry:array<string,mixed>,landing_key:string,prior_payloads:array<int,array<string,mixed>>}|\WP_Error
	 */
	private function build_one_landing( array $row, array $replace_map, array $client_data, array $client_context, array $ai_config, array $landing_pages, array $prior_payloads ) {
		unset( $landing_pages );

		$keywords = [
			'primary_keyword' => (string) $row['primary_keyword'],
			'subkeywords'     => is_array( $row['subkeywords'] ?? null ) ? $row['subkeywords'] : [],
		];

		$log_context = [
			'landing_key'  => (string) $row['landing_key'],
			'slug'         => (string) $row['slug'],
			'title'        => (string) $row['title'],
			'landing_type' => (string) $row['landing_type'],
		];

		$prepared     = [];
		$local_priors = $prior_payloads;
		$section_rows = is_array( $row['sections'] ?? null ) ? $row['sections'] : [];
		$existing_id  = (int) $row['id'];

		foreach ( $section_rows as $section_row ) {
			$layout   = $this->normalize_layout( (string) ( $section_row['layout'] ?? '' ) );
			$override = $this->truthy( $section_row['override_canonical'] ?? false );
			$count    = $this->item_count( $layout, (int) ( $section_row['item_count'] ?? 0 ) );

			if ( '' === $layout || ! $this->layout_repository->has_layout( $layout ) ) {
				continue;
			}

			$section_context            = $log_context;
			$section_context['layout']  = $layout;

			if ( $this->harness->is_keyword_layout( $layout ) ) {
				$copy = $this->generate_section_copy(
					$layout,
					$count,
					$client_context,
					$ai_config,
					AI_Content_Harness::PAGE_LANDING,
					$keywords,
					$local_priors,
					true,
					$section_context
				);

				if ( \is_wp_error( $copy ) ) {
					// Preserve existing landing content instead of publishing keyword placeholders.
					$preserved = $this->try_preserve_existing_landing( $existing_id, $row, $log_context, $copy );

					if ( \is_wp_error( $preserved ) ) {
						return $preserved;
					}

					if ( null !== $preserved ) {
						return $preserved;
					}

					return $copy;
				}

				$section = $this->content_builder->prepare_image_fallbacks( $this->section_data( $layout, $client_data, $copy, $count ) );
			} else {
				$section = $this->resolve_reusable_section(
					$layout,
					$count,
					$override,
					! empty( $replace_map[ $layout ] ),
					$client_data,
					$client_context,
					$ai_config,
					$local_priors,
					$section_context
				);
			}

			if ( ! $this->is_valid_section_row( $section ) ) {
				return new \WP_Error(
					'rms_wizard_landing_section_failed',
					sprintf(
						/* translators: %s: layout key. */
						\__( 'Landing section "%s" could not be prepared.', 'simple-rms-theme' ),
						$layout
					),
					array_merge( [ 'status' => 500 ], $section_context )
				);
			}

			$prepared[]     = $section;
			$local_priors[] = [
				'layout'     => $layout,
				'item_count' => $count,
				'payload'    => $section,
			];
		}

		if ( [] === $prepared ) {
			return new \WP_Error( 'rms_wizard_landing_sections_required', \__( 'Each landing requires at least one valid section.', 'simple-rms-theme' ), [ 'status' => 400 ] );
		}

		// Local catch: build_page may create/update the post before a later throwable.
		// Convert to WP_Error so outer partial recovery can still persist prior successes.
		try {
			$post_id = $this->content_builder->build_page(
				[
					'id'           => $existing_id,
					'title'        => (string) $row['title'],
					'slug'         => (string) $row['slug'],
					'status'       => 'publish',
					'section_only' => $existing_id > 0,
					'sections'     => $prepared,
					'meta_input'   => [
						'_wp_page_template' => self::TEMPLATE,
						'rms_landing_type'  => (string) $row['landing_type'],
					],
				]
			);
		} catch ( \Throwable $exception ) {
			$recovered_id = $this->recover_build_page_post_id( $existing_id, (string) $row['slug'] );

			$this->logger->log(
				'error',
				'Wizard landing build_page threw after possible create/update.',
				array_merge(
					$log_context,
					[
						'post_id'         => $recovered_id > 0 ? $recovered_id : null,
						'exception_class' => get_class( $exception ),
						'message'         => $exception->getMessage(),
					]
				)
			);

			// Ads may already be published — best-effort noindex/menu cleanup before failing.
			if ( $recovered_id > 0 ) {
				$this->protect_ads_final_state_best_effort( $recovered_id, $row, $log_context );
			}

			$error_data = array_merge(
				[
					'status'          => 500,
					'exception_class' => get_class( $exception ),
				],
				$log_context
			);

			if ( $recovered_id > 0 ) {
				$error_data['post_id'] = $recovered_id;
			}

			return new \WP_Error(
				'rms_wizard_landing_build_page_exception',
				sprintf(
					/* translators: 1: landing title or slug, 2: exception message. */
					\__( 'Landing "%1$s" could not be saved: %2$s', 'simple-rms-theme' ),
					'' !== (string) $row['title'] ? (string) $row['title'] : (string) $row['slug'],
					$exception->getMessage()
				),
				$error_data
			);
		}

		if ( $post_id <= 0 ) {
			$recovered_id = $this->recover_build_page_post_id( $existing_id, (string) $row['slug'] );

			if ( $recovered_id > 0 ) {
				$this->protect_ads_final_state_best_effort( $recovered_id, $row, $log_context );
			}

			return new \WP_Error(
				'rms_wizard_landing_save_failed',
				\__( 'Landing page could not be saved.', 'simple-rms-theme' ),
				array_merge(
					[ 'status' => 500 ],
					$log_context,
					$recovered_id > 0 ? [ 'post_id' => $recovered_id ] : []
				)
			);
		}

		$meta_ok = $this->ensure_landing_meta( $post_id, (string) $row['landing_type'], $log_context );

		if ( \is_wp_error( $meta_ok ) ) {
			// Published page without verified meta — protect Ads before surfacing failure.
			$this->protect_ads_final_state_best_effort( $post_id, $row, $log_context );

			return $meta_ok;
		}

		// Final-state Yoast + robots + menu reconciliation (every create/update).
		$final_state = $this->sync_landing_final_state( $post_id, $row, $log_context );

		if ( \is_wp_error( $final_state ) ) {
			return $final_state;
		}

		$entry = [
			'id'              => $post_id,
			'landing_key'     => (string) $row['landing_key'],
			'title'           => (string) $row['title'],
			'slug'            => (string) $row['slug'],
			'landing_type'    => (string) $row['landing_type'],
			'menu_eligible'   => ! empty( $row['menu_eligible'] ),
			'primary_keyword' => (string) $row['primary_keyword'],
			'subkeywords'     => is_array( $row['subkeywords'] ?? null ) ? array_values( $row['subkeywords'] ) : [],
			'keywords'        => [
				'primary_keyword' => (string) $row['primary_keyword'],
				'subkeywords'     => is_array( $row['subkeywords'] ?? null ) ? array_values( $row['subkeywords'] ) : [],
			],
			'generated_at'    => \current_time( 'mysql', true ),
		];

		return [
			'entry'          => $entry,
			'landing_key'    => (string) $row['landing_key'],
			'prior_payloads' => $local_priors,
		];
	}

	/**
	 * Apply final-state menu + robots + Yoast title/metadesc for one landing.
	 *
	 * SEO: clear noindex; append to configured menus idempotently when eligible.
	 * Ads: write noindex + read-back (fail closed); remove from configured menus.
	 *
	 * @param array<string,mixed> $row
	 * @param array<string,mixed> $log_context
	 *
	 * @return true|\WP_Error
	 */
	private function sync_landing_final_state( int $post_id, array $row, array $log_context ) {
		$landing_type    = \sanitize_key( (string) ( $row['landing_type'] ?? 'seo' ) );
		$landing_type    = in_array( $landing_type, [ 'seo', 'ads' ], true ) ? $landing_type : 'seo';
		$primary_keyword = (string) ( $row['primary_keyword'] ?? '' );
		$title           = (string) ( $row['title'] ?? '' );
		$menu_eligible   = array_key_exists( 'menu_eligible', $row )
			? (bool) $row['menu_eligible']
			: ( 'seo' === $landing_type );

		// Optional Yoast title/metadesc (skip+log when Yoast absent — never fails the step).
		$this->yoast_meta_writer->write_landing_seo( $post_id, $primary_keyword, $landing_type, $title );

		// Robots final state.
		if ( 'ads' === $landing_type ) {
			$noindex = $this->yoast_meta_writer->set_noindex( $post_id, true );

			if ( \is_wp_error( $noindex ) ) {
				return $noindex;
			}
		} else {
			$clear = $this->yoast_meta_writer->set_noindex( $post_id, false );

			if ( \is_wp_error( $clear ) ) {
				return $clear;
			}
		}

		// Menu final state against currently configured theme menus.
		$menu_ids = $this->configured_menu_ids();

		if ( [] === $menu_ids ) {
			$this->logger->log(
				'info',
				'Landing final-state menu sync skipped; no configured menus yet.',
				array_merge( [ 'post_id' => $post_id, 'landing_type' => $landing_type ], $log_context )
			);

			return true;
		}

		$seo_append_failed = [];

		if ( 'seo' === $landing_type && $menu_eligible ) {
			// SEO menu append is best-effort: log failures with detail, do not fail-close.
			// Ads menu removal below remains fail-closed.
			foreach ( $menu_ids as $menu_id ) {
				$append = $this->menu_builder->append_page_items( $menu_id, [ $post_id ] );

				if ( empty( $append['verified'] ) ) {
					$failed = is_array( $append['failed_page_ids'] ?? null )
						? array_values( $append['failed_page_ids'] )
						: [ $post_id ];
					$seo_append_failed[] = [
						'menu_id'         => $menu_id,
						'failed_page_ids' => $failed,
						'created'         => is_array( $append['created'] ?? null ) ? $append['created'] : [],
						'already_present' => is_array( $append['already_present'] ?? null ) ? $append['already_present'] : [],
					];

					$this->logger->log(
						'warning',
						'Landing final-state SEO menu append failed (best-effort; step continues). Ads removal remains fail-closed.',
						array_merge(
							[
								'post_id'          => $post_id,
								'menu_id'          => $menu_id,
								'landing_type'     => $landing_type,
								'failed_page_ids'  => $failed,
								'created'          => $append['created'] ?? [],
								'already_present'  => $append['already_present'] ?? [],
							],
							$log_context
						)
					);
				}
			}
		} else {
			// Ads / ineligible: fail-closed if menu removal cannot be verified.
			foreach ( $menu_ids as $menu_id ) {
				$removal = $this->menu_builder->remove_page_items( $menu_id, [ $post_id ] );

				if ( empty( $removal['verified'] ) ) {
					$failed = is_array( $removal['failed_page_ids'] ?? null )
						? array_values( $removal['failed_page_ids'] )
						: [ $post_id ];

					$this->logger->log(
						'error',
						'Landing final-state menu removal failed verification.',
						array_merge(
							[
								'post_id'          => $post_id,
								'menu_id'          => $menu_id,
								'landing_type'     => $landing_type,
								'failed_page_ids'  => $failed,
							],
							$log_context
						)
					);

					return new \WP_Error(
						'rms_wizard_landing_menu_remove_failed',
						sprintf(
							/* translators: %s: landing title or slug. */
							\__( 'Landing "%s" could not be removed from menus. Final state was not applied.', 'simple-rms-theme' ),
							'' !== $title ? $title : (string) ( $row['slug'] ?? $post_id )
						),
						array_merge(
							[
								'status'          => 500,
								'post_id'         => $post_id,
								'menu_id'         => $menu_id,
								'failed_page_ids' => $failed,
							],
							$log_context
						)
					);
				}
			}
		}

		$this->logger->log(
			'info',
			'Landing final-state menu/robots sync applied.',
			array_merge(
				[
					'post_id'            => $post_id,
					'landing_type'       => $landing_type,
					'menu_eligible'      => $menu_eligible,
					'menu_ids'           => $menu_ids,
					'seo_append_best_effort' => 'seo' === $landing_type && $menu_eligible,
					'seo_append_failed'  => $seo_append_failed,
				],
				$log_context
			)
		);

		return true;
	}

	/**
	 * Best-effort Ads final-state protection after a post-publish failure.
	 *
	 * Does not change the caller's failure outcome — only attempts noindex + menu
	 * removal when landing_type is ads and a post ID is known. Logs success/failure.
	 *
	 * @param array<string,mixed> $row
	 * @param array<string,mixed> $log_context
	 */
	private function protect_ads_final_state_best_effort( int $post_id, array $row, array $log_context ): void {
		$post_id      = \absint( $post_id );
		$landing_type = \sanitize_key( (string) ( $row['landing_type'] ?? 'seo' ) );
		$landing_type = in_array( $landing_type, [ 'seo', 'ads' ], true ) ? $landing_type : 'seo';
		$landing_key  = (string) ( $row['landing_key'] ?? ( $log_context['landing_key'] ?? '' ) );
		$slug         = (string) ( $row['slug'] ?? ( $log_context['slug'] ?? '' ) );

		if ( $post_id <= 0 || 'ads' !== $landing_type ) {
			return;
		}

		// Ensure Ads path in sync (menu_eligible false).
		$protection_row                   = $row;
		$protection_row['landing_type']   = 'ads';
		$protection_row['menu_eligible']  = false;

		$protection = $this->sync_landing_final_state( $post_id, $protection_row, $log_context );
		$ok         = ! \is_wp_error( $protection );

		$this->logger->log(
			$ok ? 'info' : 'error',
			$ok
				? 'Ads best-effort final-state protection succeeded after build failure.'
				: 'Ads best-effort final-state protection failed after build failure.',
			array_merge(
				[
					'post_id'           => $post_id,
					'landing_key'       => $landing_key,
					'slug'              => $slug,
					'protection_ok'     => $ok,
					'protection_error'  => $ok ? null : $protection->get_error_code(),
					'protection_message'=> $ok ? null : $protection->get_error_message(),
				],
				$log_context
			)
		);
	}

	/**
	 * Menu term IDs currently assigned to theme locations / stored menu_config.
	 *
	 * @return array<int,int>
	 */
	private function configured_menu_ids(): array {
		$ids   = [];
		$state = $this->state_manager->get_state();
		$config = is_array( $state['menu_config'] ?? null ) ? $state['menu_config'] : [];

		foreach ( [ 'primary_menu_id', 'mobile_menu_id' ] as $key ) {
			$id = \absint( $config[ $key ] ?? 0 );

			if ( $id > 0 ) {
				$ids[] = $id;
			}
		}

		if ( is_array( $config['locations'] ?? null ) ) {
			foreach ( $config['locations'] as $menu_id ) {
				$id = \absint( $menu_id );

				if ( $id > 0 ) {
					$ids[] = $id;
				}
			}
		}

		$locations = \get_theme_mod( 'nav_menu_locations', [] );

		if ( is_array( $locations ) ) {
			foreach ( $locations as $menu_id ) {
				$id = \absint( $menu_id );

				if ( $id > 0 ) {
					$ids[] = $id;
				}
			}
		}

		return array_values( array_unique( array_filter( $ids ) ) );
	}

	/**
	 * @param array<string,mixed>            $client_data
	 * @param array<string,mixed>            $client_context
	 * @param array<string,mixed>            $ai_config
	 * @param array<int,array<string,mixed>> $prior_payloads
	 * @param array<string,mixed>            $log_context
	 *
	 * @return array<string,mixed>
	 */
	private function resolve_reusable_section(
		string $layout,
		int $item_count,
		bool $override,
		bool $replace_canonical,
		array $client_data,
		array $client_context,
		array $ai_config,
		array $prior_payloads,
		array $log_context = []
	): array {
		$canonical = $this->canonical_store->get( $layout );
		// Keep canonical/reusable generation neutral: never feed keyword-driven priors.
		$neutral_priors = $this->filter_neutral_priors( $prior_payloads );

		if ( $override ) {
			// Override stays keyword-neutral (PAGE_HOME, empty keywords).
			$copy = $this->generate_section_copy(
				$layout,
				$item_count,
				$client_context,
				$ai_config,
				AI_Content_Harness::PAGE_HOME,
				[],
				$neutral_priors,
				false,
				$log_context
			);
			$copy = \is_wp_error( $copy ) ? [] : $copy;
			$row  = $this->content_builder->prepare_image_fallbacks( $this->section_data( $layout, $client_data, $copy, $item_count ) );

			if ( ! $this->is_valid_section_row( $row ) ) {
				$this->logger->log(
					'warning',
					'Landing override generation failed; falling back to canonical.',
					array_merge( [ 'layout' => $layout ], $log_context )
				);

				return $canonical;
			}

			// Overrides must never write the canonical store.
			return $row;
		}

		if ( $replace_canonical ) {
			$copy = $this->generate_section_copy(
				$layout,
				$item_count,
				$client_context,
				$ai_config,
				AI_Content_Harness::PAGE_HOME,
				[],
				$neutral_priors,
				false,
				$log_context
			);
			$copy = \is_wp_error( $copy ) ? [] : $copy;
			$row  = $this->content_builder->prepare_image_fallbacks( $this->section_data( $layout, $client_data, $copy, $item_count ) );

			if ( $this->is_valid_section_row( $row ) ) {
				if ( ! $this->canonical_store->replace( $layout, $row ) ) {
					// replace() may return false when DB already holds identical payload.
					$persisted = $this->canonical_store->get( $layout );

					if ( $persisted !== $row ) {
						$this->logger->log(
							'error',
							'Canonical replace persistence failed; keeping previous canonical when available.',
							array_merge( [ 'layout' => $layout ], $log_context )
						);

						if ( $this->is_valid_section_row( $canonical ) ) {
							return $canonical;
						}
					}
				}

				return $row;
			}

			$this->logger->log(
				'warning',
				'Canonical replace generation failed; keeping existing canonical.',
				array_merge( [ 'layout' => $layout ], $log_context )
			);
		}

		if ( $this->is_valid_section_row( $canonical ) ) {
			return $canonical;
		}

		// First-write path when store empty after bootstrap race.
		$copy = $this->generate_section_copy(
			$layout,
			$item_count,
			$client_context,
			$ai_config,
			AI_Content_Harness::PAGE_HOME,
			[],
			$neutral_priors,
			false,
			$log_context
		);
		$copy = \is_wp_error( $copy ) ? [] : $copy;
		$row  = $this->content_builder->prepare_image_fallbacks( $this->section_data( $layout, $client_data, $copy, $item_count ) );

		if ( $this->is_valid_section_row( $row ) ) {
			if ( ! $this->canonical_store->set_if_empty( $layout, $row ) && ! $this->canonical_store->has( $layout ) ) {
				$this->logger->log(
					'error',
					'Canonical first-write persistence failed.',
					array_merge( [ 'layout' => $layout ], $log_context )
				);
			}
		}

		return $row;
	}

	/**
	 * @param array<string,mixed>            $client_context
	 * @param array<string,mixed>            $ai_config
	 * @param array<string,mixed>            $keywords
	 * @param array<int,array<string,mixed>> $prior_section_payloads
	 * @param array<string,mixed>            $log_context
	 *
	 * @return array<string,mixed>|\WP_Error
	 */
	private function generate_section_copy(
		string $section_key,
		int $item_count,
		array $client_context,
		array $ai_config,
		string $page_type,
		array $keywords,
		array $prior_section_payloads,
		bool $require_ai = false,
		array $log_context = []
	) {
		$fillable = $this->harness->get_fillable_fields( $section_key );

		if ( [] === $fillable ) {
			return [];
		}

		$context = array_merge(
			[
				'layout'    => $section_key,
				'page_type' => $page_type,
			],
			$log_context
		);

		// Reusable/canonical review must not see keyword-driven prior sections.
		$review_priors = $require_ai
			? $prior_section_payloads
			: $this->filter_neutral_priors( $prior_section_payloads );

		$provider = \sanitize_key( (string) $ai_config['provider'] );
		$model    = \sanitize_text_field( (string) $ai_config['model'] );
		$system   = $this->harness->get_layer1() . "\n\n" . $this->harness->get_layer2( $page_type );
		$prompt   = $this->harness->get_layer3( $section_key, $item_count, $client_context, $page_type, $keywords );
		$result   = AI_Provider_Registry::make_provider( $provider )->generate(
			$model,
			$prompt,
			[
				'section_key' => $section_key,
				'client_data' => $client_context,
				'item_count'  => $item_count,
				'page_type'   => $page_type,
			],
			$system
		);

		if ( empty( $result['success'] ) || empty( $result['content'] ) ) {
			$this->logger->log(
				$require_ai ? 'error' : 'warning',
				$require_ai
					? 'Wizard landing keyword section AI generation failed.'
					: 'Wizard landing section AI generation failed; fallback content used.',
				array_merge( $context, [ 'error' => $result['error'] ?? '' ] )
			);

			if ( $require_ai ) {
				return new \WP_Error(
					'rms_wizard_landing_keyword_ai_failed',
					sprintf(
						/* translators: %s: layout key. */
						\__( 'Keyword section "%s" could not be generated. Landing was not published with placeholder copy.', 'simple-rms-theme' ),
						$section_key
					),
					array_merge( [ 'status' => 502 ], $context )
				);
			}

			return [];
		}

		$decoded = $this->decode_json_content( (string) $result['content'] );

		if ( [] === $decoded ) {
			$this->logger->log(
				$require_ai ? 'error' : 'warning',
				'Wizard landing AI returned success but content was invalid JSON.',
				array_merge(
					$context,
					[
						'content_preview' => substr( trim( (string) $result['content'] ), 0, 180 ),
					]
				)
			);

			if ( $require_ai ) {
				return new \WP_Error(
					'rms_wizard_landing_keyword_json_failed',
					sprintf(
						/* translators: %s: layout key. */
						\__( 'Keyword section "%s" returned invalid JSON. Landing was not published with placeholder copy.', 'simple-rms-theme' ),
						$section_key
					),
					array_merge( [ 'status' => 502 ], $context )
				);
			}

			return [];
		}

		$reviewed = $this->review_section_content(
			$section_key,
			$decoded,
			$review_priors,
			$ai_config,
			$client_context,
			$item_count,
			$require_ai,
			$context
		);

		if ( \is_wp_error( $reviewed ) ) {
			return $reviewed;
		}

		$validated = $this->harness->validate_fields( $section_key, $reviewed );

		if ( $require_ai && [] === $validated ) {
			$this->logger->log(
				'error',
				'Wizard landing keyword section validated to empty payload after AI success.',
				$context
			);

			return new \WP_Error(
				'rms_wizard_landing_keyword_empty',
				sprintf(
					/* translators: %s: layout key. */
					\__( 'Keyword section "%s" produced no usable fields. Landing was not published with placeholder copy.', 'simple-rms-theme' ),
					$section_key
				),
				array_merge( [ 'status' => 502 ], $context )
			);
		}

		return $validated;
	}

	/**
	 * @param array<string,mixed>            $decoded
	 * @param array<int,array<string,mixed>> $prior_section_payloads
	 * @param array<string,mixed>            $ai_config
	 * @param array<string,mixed>            $client_context
	 * @param array<string,mixed>            $log_context
	 *
	 * @return array<string,mixed>|\WP_Error
	 */
	private function review_section_content(
		string $section_key,
		array $decoded,
		array $prior_section_payloads,
		array $ai_config,
		array $client_context,
		int $item_count,
		bool $require_ai = false,
		array $log_context = []
	) {
		if ( ! $this->is_review_enabled() ) {
			return $decoded;
		}

		$review_config                   = $ai_config;
		$review_config['client_context'] = $client_context;
		$review_config['item_count']     = $item_count;

		try {
			$result = $this->reviewer()->review( $section_key, $decoded, $prior_section_payloads, $review_config );
		} catch ( \Throwable $error ) {
			$this->logger->log(
				'error',
				'Wizard landing content reviewer threw an exception.',
				array_merge(
					[
						'layout'  => $section_key,
						'message' => $error->getMessage(),
					],
					$log_context
				)
			);

			if ( $require_ai ) {
				return new \WP_Error(
					'rms_wizard_landing_keyword_review_failed',
					sprintf(
						/* translators: %s: layout key. */
						\__( 'Keyword section "%s" review failed. Landing was not published with placeholder copy.', 'simple-rms-theme' ),
						$section_key
					),
					array_merge( [ 'status' => 502, 'layout' => $section_key ], $log_context )
				);
			}

			return $decoded;
		}

		$payload = $result['payload'] ?? null;

		return is_array( $payload ) ? $payload : $decoded;
	}

	/**
	 * @param array<int,mixed> $raw
	 *
	 * @return array<int,array{layout:string,item_count:int,override_canonical:bool}>
	 */
	private function normalize_section_rows( array $raw ): array {
		$rows = [];

		foreach ( $raw as $value ) {
			if ( is_array( $value ) ) {
				$layout = $this->normalize_layout( (string) ( $value['layout'] ?? $value['section_key'] ?? $value['key'] ?? '' ) );
				$count  = \absint( $value['item_count'] ?? 0 );
				$over   = $this->truthy( $value['override_canonical'] ?? false );
			} else {
				$layout = $this->normalize_layout( (string) $value );
				$count  = 0;
				$over   = false;
			}

			if ( '' === $layout || ! $this->layout_repository->has_layout( $layout ) ) {
				continue;
			}

			$rows[] = [
				'layout'             => $layout,
				'item_count'         => $this->item_count( $layout, $count ),
				'override_canonical' => $over,
			];
		}

		if ( [] === $rows ) {
			foreach ( self::DEFAULT_SECTIONS as $default ) {
				$layout = $this->normalize_layout( (string) $default['layout'] );

				if ( ! $this->layout_repository->has_layout( $layout ) ) {
					continue;
				}

				$rows[] = [
					'layout'             => $layout,
					'item_count'         => $this->item_count( $layout, 0 ),
					'override_canonical' => false,
				];
			}
		}

		return $rows;
	}

	/**
	 * @param array<int,mixed> $landings
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function normalize_state_landings( array $landings ): array {
		$normalized = [];

		foreach ( $landings as $landing ) {
			if ( ! is_array( $landing ) ) {
				continue;
			}

			$key = \sanitize_key( (string) ( $landing['landing_key'] ?? '' ) );

			if ( '' === $key ) {
				$key = $this->mint_landing_key();
			}

			$landing['landing_key'] = $key;
			$landing['id']          = \absint( $landing['id'] ?? 0 );
			$landing['slug']        = \sanitize_title( (string) ( $landing['slug'] ?? '' ) );
			$normalized[ $key ]     = $landing;
		}

		return $normalized;
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
				$row[ (string) $sub_field ] = $this->placeholder_field_value( (string) $sub_field, $company );
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

	/**
	 * @return array<int,array<string,mixed>>
	 */
	private function read_page_sections( int $post_id ): array {
		if ( function_exists( 'get_field' ) ) {
			$sections = \get_field( 'page_sections', $post_id );

			if ( is_array( $sections ) ) {
				return $sections;
			}
		}

		$meta = \get_post_meta( $post_id, 'page_sections', true );

		return is_array( $meta ) ? $meta : [];
	}

	private function is_valid_section_row( array $row ): bool {
		$layout = $this->normalize_layout( (string) ( $row['acf_fc_layout'] ?? '' ) );

		return '' !== $layout;
	}

	private function page_has_landing_type( int $post_id ): bool {
		if ( $post_id <= 0 ) {
			return false;
		}

		$type = \sanitize_key( (string) \get_post_meta( $post_id, 'rms_landing_type', true ) );

		return in_array( $type, [ 'seo', 'ads' ], true );
	}

	private function mint_landing_key(): string {
		if ( function_exists( 'wp_generate_uuid4' ) ) {
			return 'lk_' . str_replace( '-', '', \wp_generate_uuid4() );
		}

		return 'lk_' . \sanitize_key( uniqid( '', true ) );
	}

	private function normalize_layout( string $layout ): string {
		$layout = \sanitize_key( $layout );

		return 'cta-bar' === $layout ? 'cta-v1' : $layout;
	}

	private function has_ai_config( array $ai_config ): bool {
		$provider        = \sanitize_key( (string) ( $ai_config['provider'] ?? '' ) );
		$model           = \sanitize_text_field( (string) ( $ai_config['model'] ?? '' ) );
		$credential      = is_array( $ai_config['credential'] ?? null ) ? $ai_config['credential'] : [];
		$has_credentials = ! empty( $ai_config['has_credentials'] ) || ! empty( $credential['has_key'] ) || ( '' !== $provider && AI_Credential_Store::has( $provider ) );

		return '' !== $provider && '' !== $model && $has_credentials && AI_Provider_Registry::provider_exists( $provider );
	}

	private function decode_json_content( string $content ): array {
		$content = trim( preg_replace( '/^```(?:json)?|```$/m', '', $content ) ?? $content );
		$data    = json_decode( $content, true );

		return is_array( $data ) ? $data : [];
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

	private function truthy( $value ): bool {
		return true === $value || 1 === $value || '1' === $value || 'true' === $value || 'yes' === $value || 'on' === $value;
	}

	private function maybe_mark_completed(): void {
		$state    = $this->state_manager->get_state();
		$required = Step_Controller::get_required_steps();

		foreach ( $required as $step ) {
			if ( 'complete' !== ( $state['step_status'][ $step ] ?? '' ) ) {
				return;
			}
		}

		$this->state_manager->mark_completed();
	}

	/**
	 * Persist wizard state and verify landing_pages post-state when critical.
	 *
	 * update_option() can return false for identical values, so we re-read.
	 *
	 * @param array<string,mixed> $state
	 */
	private function persist_wizard_state( array $state, string $reason ): bool {
		$expected = is_array( $state['landing_pages'] ?? null ) ? array_values( $state['landing_pages'] ) : [];
		$saved    = $this->state_manager->save_state( $state );

		if ( $saved ) {
			return true;
		}

		$reloaded = $this->state_manager->get_state();
		$actual   = is_array( $reloaded['landing_pages'] ?? null ) ? array_values( $reloaded['landing_pages'] ) : [];

		if ( $this->landing_pages_equivalent( $expected, $actual ) ) {
			return true;
		}

		$this->logger->log(
			'error',
			'Wizard landing state persistence failed.',
			[
				'reason'           => $reason,
				'expected_count'   => count( $expected ),
				'actual_count'     => count( $actual ),
				'expected_post_ids'=> $this->collect_landing_post_ids( $expected ),
				'actual_post_ids'  => $this->collect_landing_post_ids( $actual ),
			]
		);

		return false;
	}

	/**
	 * On multi-landing partial failure: save successful landings, then mark failed.
	 *
	 * @param array<string,mixed>               $state
	 * @param array<string,array<string,mixed>> $landing_pages
	 * @param int[]                             $built_ids
	 * @param \WP_Error                         $error
	 */
	private function persist_partial_landing_progress( array $state, array $landing_pages, array $built_ids, \WP_Error $error ): void {
		$successful = array_values( array_filter( $built_ids ) );
		$state['landing_pages']      = array_values( $landing_pages );
		$state['canonical_sections'] = $this->canonical_store->summary();

		$persisted = $this->persist_wizard_state( $state, 'partial failure recovery' );

		$this->logger->log(
			$persisted ? 'warning' : 'error',
			$persisted
				? 'Wizard landing run failed after partial success; successful landings persisted.'
				: 'Wizard landing run failed after partial success; could not persist successful landings.',
			[
				'successful_count' => count( $successful ),
				'post_ids'         => $successful,
				'error_code'       => $error->get_error_code(),
				'error_message'    => $error->get_error_message(),
			]
		);

		// Mark failed only after persistence is attempted.
		if ( ! $this->mark_step_status( 'failed' ) ) {
			$this->logger->log(
				'error',
				'Wizard landing could not mark step status failed after partial failure.',
				[
					'post_ids'   => $successful,
					'error_code' => $error->get_error_code(),
				]
			);
		}
	}

	/**
	 * @param string $status pending|running|complete|failed
	 */
	private function mark_step_status( string $status ): bool {
		$saved = $this->state_manager->set_step_status( self::STEP, $status );

		if ( $saved ) {
			return true;
		}

		$state  = $this->state_manager->get_state();
		$actual = (string) ( $state['step_status'][ self::STEP ] ?? '' );

		if ( $actual === $status ) {
			return true;
		}

		$this->logger->log(
			'error',
			'Wizard landing step status persistence failed.',
			[
				'expected' => $status,
				'actual'   => $actual,
			]
		);

		return false;
	}

	/**
	 * Safety-net meta writes with post-state verification.
	 *
	 * @param array<string,mixed> $log_context
	 *
	 * @return true|\WP_Error
	 */
	private function ensure_landing_meta( int $post_id, string $landing_type, array $log_context ) {
		\update_post_meta( $post_id, '_wp_page_template', self::TEMPLATE );
		\update_post_meta( $post_id, 'rms_landing_type', $landing_type );

		$template = (string) \get_post_meta( $post_id, '_wp_page_template', true );
		$type     = \sanitize_key( (string) \get_post_meta( $post_id, 'rms_landing_type', true ) );

		if ( self::TEMPLATE !== $template || $landing_type !== $type ) {
			$this->logger->log(
				'error',
				'Wizard landing meta safety-net verification failed.',
				array_merge(
					$log_context,
					[
						'post_id'           => $post_id,
						'expected_template' => self::TEMPLATE,
						'actual_template'   => $template,
						'expected_type'     => $landing_type,
						'actual_type'       => $type,
					]
				)
			);

			return new \WP_Error(
				'rms_wizard_landing_meta_persist_failed',
				\__( 'Landing page meta could not be verified after save.', 'simple-rms-theme' ),
				array_merge( [ 'status' => 500, 'post_id' => $post_id ], $log_context )
			);
		}

		return true;
	}

	/**
	 * When keyword AI fails on an update, preserve the existing landing page payload.
	 *
	 * @param array<string,mixed> $row
	 * @param array<string,mixed> $log_context
	 * @param \WP_Error           $error
	 *
	 * @return array{entry:array<string,mixed>,landing_key:string,prior_payloads:array<int,array<string,mixed>>}|\WP_Error|null
	 *         array on preserved success, WP_Error when preserve update fails, null when preserve is not applicable.
	 */
	private function try_preserve_existing_landing( int $existing_id, array $row, array $log_context, \WP_Error $error ) {
		if ( $existing_id <= 0 || ! $this->page_has_landing_type( $existing_id ) ) {
			return null;
		}

		$sections = $this->read_page_sections( $existing_id );
		$valid    = false;

		foreach ( $sections as $section ) {
			if ( is_array( $section ) && $this->is_valid_section_row( $section ) ) {
				$valid = true;
				break;
			}
		}

		if ( ! $valid ) {
			return null;
		}

		$this->logger->log(
			'warning',
			'Keyword AI failed; preserving existing landing page payload instead of placeholders.',
			array_merge(
				$log_context,
				[
					'post_id'       => $existing_id,
					'error_code'    => $error->get_error_code(),
					'error_message' => $error->get_error_message(),
				]
			)
		);

		// Refresh title/slug/type meta without rewriting sections.
		$updated = \wp_update_post(
			[
				'ID'         => $existing_id,
				'post_title' => (string) $row['title'],
				'post_name'  => (string) $row['slug'],
			],
			true
		);

		if ( \is_wp_error( $updated ) ) {
			$this->logger->log(
				'error',
				'Failed to preserve existing landing page after keyword AI failure (wp_update_post error).',
				array_merge(
					$log_context,
					[
						'post_id'       => $existing_id,
						'error_code'    => $updated->get_error_code(),
						'error_message' => $updated->get_error_message(),
					]
				)
			);

			// Best-effort Ads protection before returning the original preserve failure.
			$this->protect_ads_final_state_best_effort( $existing_id, $row, $log_context );

			return new \WP_Error(
				'rms_wizard_landing_preserve_failed',
				\__( 'Keyword generation failed and the existing landing page could not be preserved.', 'simple-rms-theme' ),
				array_merge(
					[
						'status'             => 500,
						'post_id'            => $existing_id,
						'update_error_code'  => $updated->get_error_code(),
						'keyword_error_code' => $error->get_error_code(),
					],
					$log_context
				)
			);
		}

		if ( ! is_int( $updated ) || $updated <= 0 ) {
			$this->logger->log(
				'error',
				'Failed to preserve existing landing page after keyword AI failure (invalid wp_update_post result).',
				array_merge(
					$log_context,
					[
						'post_id'       => $existing_id,
						'update_result' => $updated,
					]
				)
			);

			// Best-effort Ads protection before returning the original preserve failure.
			$this->protect_ads_final_state_best_effort( $existing_id, $row, $log_context );

			return new \WP_Error(
				'rms_wizard_landing_preserve_failed',
				\__( 'Keyword generation failed and the existing landing page could not be preserved.', 'simple-rms-theme' ),
				array_merge(
					[
						'status'             => 500,
						'post_id'            => $existing_id,
						'keyword_error_code' => $error->get_error_code(),
					],
					$log_context
				)
			);
		}

		$meta_ok = $this->ensure_landing_meta( $existing_id, (string) $row['landing_type'], $log_context );

		if ( \is_wp_error( $meta_ok ) ) {
			$this->logger->log(
				'error',
				'Failed to preserve existing landing meta after keyword AI failure.',
				array_merge(
					$log_context,
					[
						'post_id'       => $existing_id,
						'error_code'    => $meta_ok->get_error_code(),
						'error_message' => $meta_ok->get_error_message(),
					]
				)
			);

			// Best-effort Ads protection before returning the original meta failure.
			$this->protect_ads_final_state_best_effort( $existing_id, $row, $log_context );

			return $meta_ok;
		}

		// Type flips / robots / menu still reconcile even when sections are preserved.
		$final_state = $this->sync_landing_final_state( $existing_id, $row, $log_context );

		if ( \is_wp_error( $final_state ) ) {
			return $final_state;
		}

		$entry = [
			'id'              => $existing_id,
			'landing_key'     => (string) $row['landing_key'],
			'title'           => (string) $row['title'],
			'slug'            => (string) $row['slug'],
			'landing_type'    => (string) $row['landing_type'],
			'menu_eligible'   => ! empty( $row['menu_eligible'] ),
			'primary_keyword' => (string) $row['primary_keyword'],
			'subkeywords'     => is_array( $row['subkeywords'] ?? null ) ? array_values( $row['subkeywords'] ) : [],
			'keywords'        => [
				'primary_keyword' => (string) $row['primary_keyword'],
				'subkeywords'     => is_array( $row['subkeywords'] ?? null ) ? array_values( $row['subkeywords'] ) : [],
			],
			'generated_at'    => \current_time( 'mysql', true ),
			'preserved'       => true,
		];

		return [
			'entry'          => $entry,
			'landing_key'    => (string) $row['landing_key'],
			'prior_payloads' => [],
		];
	}

	/**
	 * Strip keyword-governed priors so reusable/canonical generation stays neutral.
	 *
	 * @param array<int,array<string,mixed>> $priors
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function filter_neutral_priors( array $priors ): array {
		$neutral = [];

		foreach ( $priors as $prior ) {
			if ( ! is_array( $prior ) ) {
				continue;
			}

			$layout = $this->normalize_layout( (string) ( $prior['layout'] ?? '' ) );

			if ( '' === $layout || $this->harness->is_keyword_layout( $layout ) ) {
				continue;
			}

			$neutral[] = $prior;
		}

		return $neutral;
	}

	/**
	 * Compare meaningful persisted landing state (not only identity fields).
	 *
	 * Used when save_state() returns false so identical-but-unordered or
	 * stale/partial snapshots are not treated as successful persistence.
	 *
	 * @param array<int,array<string,mixed>> $left
	 * @param array<int,array<string,mixed>> $right
	 */
	private function landing_pages_equivalent( array $left, array $right ): bool {
		if ( count( $left ) !== count( $right ) ) {
			return false;
		}

		$normalize = function ( array $rows ): array {
			$map = [];

			foreach ( $rows as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}

				$key = \sanitize_key( (string) ( $row['landing_key'] ?? '' ) );

				if ( '' === $key ) {
					$key = 'id_' . (int) ( $row['id'] ?? 0 );
				}

				$keywords       = is_array( $row['keywords'] ?? null ) ? $row['keywords'] : [];
				$primary        = (string) ( $row['primary_keyword'] ?? ( $keywords['primary_keyword'] ?? '' ) );
				$subkeywords    = is_array( $row['subkeywords'] ?? null )
					? $row['subkeywords']
					: ( is_array( $keywords['subkeywords'] ?? null ) ? $keywords['subkeywords'] : [] );
				$subkeywords    = array_values(
					array_filter(
						array_map(
							static function ( $value ): string {
								return \sanitize_text_field( (string) $value );
							},
							$subkeywords
						),
						static function ( string $value ): bool {
							return '' !== $value;
						}
					)
				);
				// Order-insensitive keyword bag — payload order is not meaningful state.
				sort( $subkeywords, SORT_STRING );

				$map[ $key ] = [
					'id'              => (int) ( $row['id'] ?? 0 ),
					'landing_key'     => \sanitize_key( (string) ( $row['landing_key'] ?? '' ) ),
					'title'           => \sanitize_text_field( (string) ( $row['title'] ?? '' ) ),
					'slug'            => \sanitize_title( (string) ( $row['slug'] ?? '' ) ),
					'landing_type'    => \sanitize_key( (string) ( $row['landing_type'] ?? '' ) ),
					'menu_eligible'   => ! empty( $row['menu_eligible'] ),
					'primary_keyword' => \sanitize_text_field( $primary ),
					'subkeywords'     => $subkeywords,
					'generated_at'    => \sanitize_text_field( (string) ( $row['generated_at'] ?? '' ) ),
					'preserved'       => ! empty( $row['preserved'] ),
				];
			}

			ksort( $map );

			return $map;
		};

		return $normalize( $left ) === $normalize( $right );
	}

	/**
	 * Best-effort post ID recovery when build_page throws after create/update.
	 */
	private function recover_build_page_post_id( int $existing_id, string $slug ): int {
		if ( $existing_id > 0 ) {
			return $existing_id;
		}

		$slug = \sanitize_title( $slug );

		if ( '' === $slug ) {
			return 0;
		}

		$page = \get_page_by_path( $slug, OBJECT, 'page' );

		if ( $page instanceof \WP_Post ) {
			return (int) $page->ID;
		}

		return 0;
	}

	/**
	 * @param array<int,array<string,mixed>> $landings
	 *
	 * @return int[]
	 */
	private function collect_landing_post_ids( array $landings ): array {
		$ids = [];

		foreach ( $landings as $landing ) {
			if ( ! is_array( $landing ) ) {
				continue;
			}

			$id = (int) ( $landing['id'] ?? 0 );

			if ( $id > 0 ) {
				$ids[] = $id;
			}
		}

		return $ids;
	}
}

