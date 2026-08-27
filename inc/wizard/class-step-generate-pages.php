<?php
/**
 * Wizard page generation step service.
 *
 * @package Simple_RMS_Theme
 */

namespace Inc\Wizard;

defined( 'ABSPATH' ) || exit;

/**
 * Creates the wizard-selected pages and stores Home/Blog assignments.
 */
class Step_Generate_Pages {
	private const STEP = 'generate-pages';

	private $logger;
	private $state_manager;
	private $content_builder;

	public function __construct( ?Logger $logger = null, ?State_Manager $state_manager = null, ?Content_Builder $content_builder = null ) {
		$this->logger          = $logger ?? new Logger();
		$this->state_manager   = $state_manager ?? new State_Manager();
		$this->content_builder = $content_builder ?? new Content_Builder( $this->logger, $this->state_manager );
	}

	/**
	 * Run page generation.
	 *
	 * @param array<string,mixed> $payload Step payload.
	 *
	 * @return array<string,mixed>|\WP_Error
	 */
	public function run( array $payload ) {
		$pages = $this->selected_pages( $payload );

		if ( [] === $pages ) {
			$this->state_manager->set_step_status( self::STEP, 'failed' );

			return new \WP_Error( 'rms_wizard_pages_required', \__( 'Select at least one page to generate.', 'simple-rms-theme' ), [ 'status' => 400 ] );
		}

		$roles = $this->resolve_roles( $pages, $payload );

		if ( \is_wp_error( $roles ) ) {
			$this->state_manager->set_step_status( self::STEP, 'failed' );

			return $roles;
		}

		// Fail closed: never select/update/assign residual SEO/Ads landings via generate-pages.
		$landing_conflict = $this->reject_selected_landing_slugs( array_keys( $pages ) );

		if ( \is_wp_error( $landing_conflict ) ) {
			$this->state_manager->set_step_status( self::STEP, 'failed' );

			return $landing_conflict;
		}

		if ( ! $this->confirmed_cleanup( $payload ) ) {
			$this->state_manager->set_step_status( self::STEP, 'pending' );

			return new \WP_Error( 'rms_wizard_page_cleanup_confirmation_required', \__( 'Existing pages not in your selection will be permanently deleted. This cannot be undone.', 'simple-rms-theme' ), [ 'status' => 400 ] );
		}

		$this->delete_unselected_pages( array_keys( $pages ) );

		$state           = $this->state_manager->get_state();
		$client_data     = is_array( $state['client_data'] ?? null ) ? $state['client_data'] : [];
		$ai_config       = is_array( $state['ai_config'] ?? null ) ? $state['ai_config'] : [];
		$generated_pages = [];
		$created_posts   = [];

		foreach ( $pages as $slug => $page ) {
			$existing = \get_page_by_path( $slug, \OBJECT, 'page' );

			// Defense-in-depth: residual landings must never be updated/published here.
			if ( $existing && $this->is_landing_page( (int) $existing->ID ) ) {
				$this->state_manager->set_step_status( self::STEP, 'failed' );

				return $this->landing_slug_conflict_error( $slug, (int) $existing->ID );
			}

			$post_id = $this->content_builder->build_page(
				[
					'id'      => $existing ? (int) $existing->ID : 0,
					'title'   => $page['title'],
					'slug'    => $slug,
					'status'  => 'publish',
					'content' => $this->generate_page_content( $page['title'], $slug, $client_data, $ai_config ),
				]
			);

			if ( $post_id <= 0 ) {
				$this->state_manager->set_step_status( self::STEP, 'failed' );

				return new \WP_Error( 'rms_wizard_page_create_failed', \__( 'One or more selected pages could not be created.', 'simple-rms-theme' ), [ 'status' => 500 ] );
			}

			if ( $this->is_landing_page( $post_id ) ) {
				$this->state_manager->set_step_status( self::STEP, 'failed' );

				return $this->landing_slug_conflict_error( $slug, $post_id );
			}

			$role = '';
			if ( $slug === $roles['home_slug'] ) {
				$role = 'home';
			} elseif ( '' !== $roles['blog_slug'] && $slug === $roles['blog_slug'] ) {
				$role = 'blog';
			}

			$created_posts[]   = $post_id;
			$generated_pages[] = [
				'id'    => $post_id,
				'title' => $page['title'],
				'slug'  => $slug,
				'role'  => $role,
				'type'  => \sanitize_title( (string) ( $page['type'] ?? '' ) ),
			];
		}

		$reading = $this->assign_reading_pages( $generated_pages, $roles['home_slug'], $roles['blog_slug'] );

		if ( \is_wp_error( $reading ) ) {
			$this->state_manager->set_step_status( self::STEP, 'failed' );

			return $reading;
		}

		$fresh                    = $this->state_manager->get_state();
		$fresh['created_posts']   = $created_posts;
		$fresh['generated_pages'] = $generated_pages;
		$fresh['home_page_slug']  = $roles['home_slug'];
		$fresh['blog_page_slug']  = $roles['blog_slug'];

		$this->state_manager->save_state( $fresh );
		$this->state_manager->set_step_status( self::STEP, 'complete' );
		$this->logger->log( 'info', 'Wizard pages generated.', [ 'count' => count( $generated_pages ), 'home' => $roles['home_slug'], 'blog' => $roles['blog_slug'] ] );

		return [
			'generated_pages' => $generated_pages,
			'home_page_slug'  => $roles['home_slug'],
			'blog_page_slug'  => $roles['blog_slug'],
		];
	}

	private function selected_pages( array $payload ): array {
		$raw       = is_array( $payload['pages'] ?? null ) ? $payload['pages'] : ( is_array( $payload['selected_pages'] ?? null ) ? $payload['selected_pages'] : [] );
		$available = $this->available_pages();
		$selected  = [];

		foreach ( $raw as $key => $value ) {
			$config = is_array( $value ) ? $value : [];

			if ( is_array( $value ) && array_key_exists( 'generate', $value ) && ! $this->truthy( $value['generate'] ) ) {
				continue;
			}

			if ( ! is_array( $value ) && ! is_string( $value ) && ! $this->truthy( $value ) ) {
				continue;
			}

			if ( is_string( $value ) ) {
				$slug = \sanitize_title( $value );
			} elseif ( is_string( $key ) ) {
				$slug = \sanitize_title( $config['slug'] ?? $key );
			} else {
				$slug = \sanitize_title( $config['slug'] ?? '' );
			}

			if ( '' === $slug ) {
				continue;
			}

			$type = $this->resolve_page_type( $config, is_string( $key ) ? $key : '', $slug, $available );

			$selected[ $slug ] = [
				'title' => \sanitize_text_field( (string) ( $config['title'] ?? $available[ $type ] ?? $available[ $slug ] ?? ucwords( str_replace( '-', ' ', $slug ) ) ) ),
				'role'  => \sanitize_key( (string) ( $config['role'] ?? '' ) ),
				'type'  => $type,
			];
		}

		return $selected;
	}

	/**
	 * Prefer a valid explicit type; ignore unknown types; fall back to legacy keys/slugs.
	 *
	 * @param array<string,mixed>      $config    Page payload item.
	 * @param array<string,string>     $available Catalog keyed by immutable type.
	 */
	private function resolve_page_type( array $config, string $key, string $slug, array $available ): string {
		$explicit = \sanitize_title( (string) ( $config['type'] ?? '' ) );

		if ( '' !== $explicit && isset( $available[ $explicit ] ) ) {
			return $explicit;
		}

		$from_key = \sanitize_title( $key );

		if ( '' !== $from_key && isset( $available[ $from_key ] ) ) {
			return $from_key;
		}

		return isset( $available[ $slug ] ) ? $slug : '';
	}

	private function resolve_roles( array $pages, array $payload ) {
		$home_slug = \sanitize_title( (string) ( $payload['home_slug'] ?? '' ) );
		$blog_slug = \sanitize_title( (string) ( $payload['blog_slug'] ?? '' ) );
		$home_roles = [];
		$blog_roles = [];

		foreach ( $pages as $slug => $page ) {
			if ( 'home' === $page['role'] ) {
				$home_roles[] = $slug;
			}

			if ( 'blog' === $page['role'] ) {
				$blog_roles[] = $slug;
			}
		}

		if ( '' === $home_slug && 1 === count( $home_roles ) ) {
			$home_slug = $home_roles[0];
		}

		if ( '' === $home_slug || count( $home_roles ) > 1 || ( [] !== $home_roles && ! in_array( $home_slug, $home_roles, true ) ) || ! isset( $pages[ $home_slug ] ) ) {
			return new \WP_Error( 'rms_wizard_home_page_required', \__( 'Please mark one page as Home', 'simple-rms-theme' ), [ 'status' => 400 ] );
		}

		if ( '' === $blog_slug && 1 === count( $blog_roles ) ) {
			$blog_slug = $blog_roles[0];
		} elseif ( '' === $blog_slug && isset( $pages['blog'] ) ) {
			$blog_slug = 'blog';
		}

		if ( count( $blog_roles ) > 1 || ( '' !== $blog_slug && [] !== $blog_roles && ! in_array( $blog_slug, $blog_roles, true ) ) || ( '' !== $blog_slug && ! isset( $pages[ $blog_slug ] ) ) ) {
			return new \WP_Error( 'rms_wizard_blog_page_invalid', \__( 'Blog page must be one of the selected pages.', 'simple-rms-theme' ), [ 'status' => 400 ] );
		}

		return [ 'home_slug' => $home_slug, 'blog_slug' => $blog_slug ];
	}

	private function confirmed_cleanup( array $payload ): bool {
		return $this->truthy( $payload['confirm_cleanup'] ?? false );
	}

	private function truthy( $value ): bool {
		return true === $value || 1 === $value || '1' === $value || 'true' === $value || 'yes' === $value;
	}

	private function delete_unselected_pages( array $selected_slugs ): void {
		$protected_slugs = $this->protected_landing_slugs();
		$page_ids        = \get_posts(
			[
				'post_type'      => 'page',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			]
		);

		foreach ( $page_ids as $page_id ) {
			$post = \get_post( (int) $page_id );

			if ( ! $post || in_array( $post->post_name, $selected_slugs, true ) ) {
				continue;
			}

			// Primary guard: never hard-delete wizard landing pages on generate-pages cleanup.
			if ( $this->is_landing_page( (int) $page_id ) ) {
				continue;
			}

			// Defense-in-depth: protect slugs still tracked in state.landing_pages.
			if ( in_array( $post->post_name, $protected_slugs, true ) ) {
				continue;
			}

			\wp_delete_post( (int) $page_id, true );
		}
	}

	/**
	 * Reject payload slugs that already resolve to residual SEO/Ads landing pages.
	 *
	 * @param string[] $slugs
	 *
	 * @return true|\WP_Error
	 */
	private function reject_selected_landing_slugs( array $slugs ) {
		foreach ( $slugs as $slug ) {
			$slug = \sanitize_title( (string) $slug );

			if ( '' === $slug ) {
				continue;
			}

			$existing = \get_page_by_path( $slug, \OBJECT, 'page' );

			if ( $existing && $this->is_landing_page( (int) $existing->ID ) ) {
				return $this->landing_slug_conflict_error( $slug, (int) $existing->ID );
			}
		}

		return true;
	}

	/**
	 * Whether a page is a wizard SEO/Ads landing (post meta rms_landing_type).
	 */
	private function is_landing_page( int $post_id ): bool {
		if ( $post_id <= 0 ) {
			return false;
		}

		$landing_type = \sanitize_key( (string) \get_post_meta( $post_id, 'rms_landing_type', true ) );

		return in_array( $landing_type, [ 'seo', 'ads' ], true );
	}

	/**
	 * Operator-facing error when generate-pages collides with a landing page.
	 */
	private function landing_slug_conflict_error( string $slug, int $post_id ): \WP_Error {
		$this->logger->log(
			'error',
			'Generate-pages rejected slug that belongs to an existing landing page.',
			[
				'slug'    => $slug,
				'post_id' => $post_id,
			]
		);

		return new \WP_Error(
			'rms_wizard_page_slug_is_landing',
			sprintf(
				/* translators: %s: page slug. */
				\__( 'Slug "%s" belongs to an existing landing page and cannot be selected, updated, or assigned as Home/Blog by generate-pages.', 'simple-rms-theme' ),
				$slug
			),
			[
				'status'  => 400,
				'slug'    => $slug,
				'post_id' => $post_id,
			]
		);
	}

	/**
	 * Slugs from state.landing_pages that must survive generate-pages cleanup.
	 *
	 * @return string[]
	 */
	private function protected_landing_slugs(): array {
		$state    = $this->state_manager->get_state();
		$landings = is_array( $state['landing_pages'] ?? null ) ? $state['landing_pages'] : [];
		$slugs    = [];

		foreach ( $landings as $landing ) {
			if ( ! is_array( $landing ) ) {
				continue;
			}

			$slug = \sanitize_title( (string) ( $landing['slug'] ?? '' ) );

			if ( '' !== $slug ) {
				$slugs[] = $slug;
			}
		}

		return array_values( array_unique( $slugs ) );
	}

	private function generate_page_content( string $title, string $slug, array $client_data, array $ai_config ): string {
		$provider = \sanitize_key( (string) ( $ai_config['provider'] ?? '' ) );
		$model    = \sanitize_text_field( (string) ( $ai_config['model'] ?? '' ) );

		if ( '' !== $provider && '' !== $model && AI_Provider_Registry::provider_exists( $provider ) ) {
			$prompt = sprintf(
				"Write concise HTML body copy for the %s page of a contractor website. Use two short paragraphs. Client data JSON: %s",
				$title,
				(string) \wp_json_encode( $client_data )
			);
			$result = AI_Provider_Registry::make_provider( $provider )->generate( $model, $prompt, [ 'page_slug' => $slug, 'client_data' => $client_data ] );

			if ( ! empty( $result['success'] ) && ! empty( $result['content'] ) ) {
				return \wp_kses_post( (string) $result['content'] );
			}

			$this->logger->log( 'warning', 'Wizard page AI generation failed; fallback content used.', [ 'slug' => $slug, 'error' => $result['error'] ?? '' ] );
		} else {
			$this->logger->log( 'warning', 'Wizard page AI generation skipped; fallback content used.', [ 'slug' => $slug ] );
		}

		$company = \sanitize_text_field( (string) ( $client_data['company_name'] ?? \__( 'Your local service team', 'simple-rms-theme' ) ) );

		return '<p>' . \esc_html( $company ) . ' provides reliable, professional service with clear communication from start to finish.</p><p>This ' . \esc_html( $title ) . ' page was generated by the setup wizard and can be refined after launch.</p>';
	}

	/**
	 * Assign Reading settings. Never promote a landing page to Home/Blog.
	 *
	 * @param array<int,array<string,mixed>> $generated_pages
	 *
	 * @return true|\WP_Error
	 */
	private function assign_reading_pages( array $generated_pages, string $home_slug, string $blog_slug ) {
		$ids_by_slug = [];

		foreach ( $generated_pages as $page ) {
			$ids_by_slug[ $page['slug'] ] = (int) $page['id'];
		}

		if ( ! empty( $ids_by_slug[ $home_slug ] ) ) {
			$home_id = (int) $ids_by_slug[ $home_slug ];

			if ( $this->is_landing_page( $home_id ) ) {
				return $this->landing_slug_conflict_error( $home_slug, $home_id );
			}

			\update_option( 'show_on_front', 'page', false );
			\update_option( 'page_on_front', $home_id, false );
		}

		if ( '' !== $blog_slug && ! empty( $ids_by_slug[ $blog_slug ] ) ) {
			$blog_id = (int) $ids_by_slug[ $blog_slug ];

			if ( $this->is_landing_page( $blog_id ) ) {
				return $this->landing_slug_conflict_error( $blog_slug, $blog_id );
			}

			\update_option( 'page_for_posts', $blog_id, false );
		} else {
			\update_option( 'page_for_posts', 0, false );
		}

		return true;
	}

	private function available_pages(): array {
		return [
			'home'         => \__( 'Home', 'simple-rms-theme' ),
			'about'        => \__( 'About', 'simple-rms-theme' ),
			'services'     => \__( 'Services', 'simple-rms-theme' ),
			'blog'         => \__( 'Blog', 'simple-rms-theme' ),
			'contact'      => \__( 'Contact', 'simple-rms-theme' ),
			'projects'     => \__( 'Projects', 'simple-rms-theme' ),
			'testimonials' => \__( 'Testimonials', 'simple-rms-theme' ),
		];
	}
}
