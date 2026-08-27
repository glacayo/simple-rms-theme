<?php
/**
 * Wizard content builder service.
 *
 * @package Simple_RMS_Theme
 */

namespace Inc\Wizard;

defined( 'ABSPATH' ) || exit;

/**
 * Creates pages and populates ACF flexible content for wizard-generated sites.
 */
class Content_Builder {
	private const STEP = 'content-creation';

	private $logger;
	private $state_manager;
	private $yoast_meta_writer;

	public function __construct( ?Logger $logger = null, ?State_Manager $state_manager = null, ?Yoast_Meta_Writer $yoast_meta_writer = null ) {
		$this->logger            = $logger ?? new Logger();
		$this->state_manager     = $state_manager ?? new State_Manager();
		$this->yoast_meta_writer = $yoast_meta_writer ?? new Yoast_Meta_Writer();
	}

	/**
	 * Build all supplied pages.
	 *
	 * @param array<int,array<string,mixed>> $pages Page definitions.
	 *
	 * @return array<int,int> Created or updated page IDs.
	 */
	public function build_pages( array $pages ): array {
		$post_ids = [];

		foreach ( $pages as $page ) {
			$post_id = $this->build_page( $page );

			if ( $post_id > 0 ) {
				$post_ids[] = $post_id;
			}
		}

		$this->state_manager->merge_state( [ 'created_posts' => $post_ids ] );
		$this->state_manager->set_step_status( self::STEP, empty( $pages ) || count( $post_ids ) !== count( $pages ) ? 'failed' : 'complete' );

		return $post_ids;
	}

	/**
	 * Post meta keys allowed through build_page() meta_input.
	 *
	 * @var string[]
	 */
	private const META_INPUT_WHITELIST = [
		'_wp_page_template',
		'rms_landing_type',
	];

	/**
	 * Build one page from a definition array.
	 *
	 * @param array<string,mixed> $page Page definition.
	 *
	 * @return int Page ID, or 0 on failure.
	 */
	public function build_page( array $page ): int {
		$post_id      = \absint( $page['id'] ?? 0 );
		$section_only = $post_id > 0 && ! empty( $page['section_only'] );
		$existing     = $post_id > 0 ? \get_post( $post_id ) : null;

		if ( $section_only && ! $existing ) {
			$this->logger->log( 'error', 'Wizard section-only page update failed because the page was not found.', [ 'post_id' => $post_id ] );

			return 0;
		}

		$title   = \sanitize_text_field( (string) ( $page['title'] ?? ( $section_only ? $existing->post_title : '' ) ) );
		$slug    = \sanitize_title( (string) ( $page['slug'] ?? ( $section_only ? $existing->post_name : $title ) ) );
		$status  = \sanitize_key( (string) ( $page['status'] ?? ( $section_only ? $existing->post_status : 'publish' ) ) );
		$content = \wp_kses_post( (string) ( $page['content'] ?? ( $section_only ? $existing->post_content : '' ) ) );

		$post_data = [
			'post_type'    => 'page',
			'post_status'  => $status,
			'post_title'   => $title,
			'post_name'    => $slug,
			'post_content' => $content,
		];

		$meta_input = $this->whitelisted_meta_input( is_array( $page['meta_input'] ?? null ) ? $page['meta_input'] : [] );

		if ( [] !== $meta_input ) {
			$post_data['meta_input'] = $meta_input;
		}

		if ( $post_id > 0 ) {
			$post_data['ID'] = $post_id;
		}

		$result = $post_id > 0 ? \wp_update_post( $post_data, true ) : \wp_insert_post( $post_data, true );

		if ( \is_wp_error( $result ) ) {
			$this->logger->log( 'error', 'Wizard page creation failed.', [ 'title' => $title, 'error' => $result->get_error_message() ] );

			return 0;
		}

		$post_id = (int) $result;

		if ( array_key_exists( 'sections', $page ) ) {
			$sections = $this->prepare_sections( is_array( $page['sections'] ) ? $page['sections'] : [] );
			$this->save_page_sections( $post_id, $sections );
		}

		if ( is_array( $page['seo'] ?? null ) ) {
			$this->yoast_meta_writer->write( $post_id, $page['seo'] );
		}

		$this->logger->log( 'info', 'Wizard page built.', [ 'post_id' => $post_id, 'title' => $title ] );

		return $post_id;
	}

	/**
	 * Return the bundled placeholder URL for image fallbacks.
	 */
	public function fallback_image_url(): string {
		return \trailingslashit( \get_template_directory_uri() ) . 'assets/images/wizard-placeholder.svg';
	}

	/**
	 * Sanitize and whitelist meta_input for insert/update.
	 *
	 * Only `_wp_page_template` and `rms_landing_type` are accepted.
	 *
	 * @param array<string,mixed> $meta Raw meta_input map.
	 *
	 * @return array<string,string>
	 */
	private function whitelisted_meta_input( array $meta ): array {
		$clean = [];

		foreach ( self::META_INPUT_WHITELIST as $key ) {
			if ( ! array_key_exists( $key, $meta ) ) {
				continue;
			}

			if ( '_wp_page_template' === $key ) {
				$value = \sanitize_text_field( (string) $meta[ $key ] );

				if ( '' !== $value ) {
					$clean[ $key ] = $value;
				}

				continue;
			}

			if ( 'rms_landing_type' === $key ) {
				$value = \sanitize_key( (string) $meta[ $key ] );

				if ( in_array( $value, [ 'seo', 'ads' ], true ) ) {
					$clean[ $key ] = $value;
				}
			}
		}

		return $clean;
	}

	private function save_page_sections( int $post_id, array $sections ): void {
		if ( function_exists( 'update_field' ) ) {
			\update_field( 'page_sections', $sections, $post_id );
			return;
		}

		\update_post_meta( $post_id, 'page_sections', $sections );
	}

	private function prepare_sections( array $sections ): array {
		foreach ( $sections as $index => $section ) {
			if ( ! is_array( $section ) ) {
				unset( $sections[ $index ] );
				continue;
			}

			$sections[ $index ] = $this->prepare_image_fallbacks( $section );
		}

		return array_values( $sections );
	}

	public function prepare_image_fallbacks( array $value ): array {
		foreach ( $value as $key => $child_value ) {
			if ( is_array( $child_value ) ) {
				$value[ $key ] = $this->prepare_image_fallbacks( $child_value );
				continue;
			}

			if ( is_string( $key ) && $this->is_image_fallback_field( $key ) && '' === (string) $child_value ) {
				$value[ $key ] = $this->fallback_image_url();
			}
		}

		return $value;
	}

	private function is_image_fallback_field( string $key ): bool {
		$exact_image_fields = [ 'gallery_full' ];

		if ( in_array( $key, $exact_image_fields, true ) ) {
			return true;
		}

		foreach ( [ 'image', 'thumbnail', 'avatar', 'poster', 'photo', 'logo' ] as $marker ) {
			if ( false !== strpos( $key, $marker ) ) {
				return true;
			}
		}

		return false;
	}
}
