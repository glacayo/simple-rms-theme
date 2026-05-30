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
	 * Build one page from a definition array.
	 *
	 * @param array<string,mixed> $page Page definition.
	 *
	 * @return int Page ID, or 0 on failure.
	 */
	public function build_page( array $page ): int {
		$title   = \sanitize_text_field( (string) ( $page['title'] ?? '' ) );
		$post_id = (int) ( $page['id'] ?? 0 );

		$post_data = [
			'post_type'    => 'page',
			'post_status'  => \sanitize_key( (string) ( $page['status'] ?? 'publish' ) ),
			'post_title'   => $title,
			'post_name'    => \sanitize_title( (string) ( $page['slug'] ?? $title ) ),
			'post_content' => \wp_kses_post( (string) ( $page['content'] ?? '' ) ),
		];

		if ( $post_id > 0 ) {
			$post_data['ID'] = $post_id;
		}

		$result = $post_id > 0 ? \wp_update_post( $post_data, true ) : \wp_insert_post( $post_data, true );

		if ( \is_wp_error( $result ) ) {
			$this->logger->log( 'error', 'Wizard page creation failed.', [ 'title' => $title, 'error' => $result->get_error_message() ] );

			return 0;
		}

		$post_id  = (int) $result;
		$sections = $this->prepare_sections( is_array( $page['sections'] ?? null ) ? $page['sections'] : [] );

		$this->save_page_sections( $post_id, $sections );

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

	private function prepare_image_fallbacks( array $value ): array {
		foreach ( $value as $key => $child_value ) {
			if ( is_array( $child_value ) ) {
				$value[ $key ] = $this->prepare_image_fallbacks( $child_value );
				continue;
			}

			if ( is_string( $key ) && false !== strpos( $key, 'image' ) && '' === (string) $child_value ) {
				$value[ $key ] = $this->fallback_image_url();
			}
		}

		return $value;
	}
}
