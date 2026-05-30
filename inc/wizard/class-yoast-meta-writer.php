<?php
/**
 * Wizard Yoast metadata writer.
 *
 * @package Simple_RMS_Theme
 */

namespace Inc\Wizard;

defined( 'ABSPATH' ) || exit;

/**
 * Writes Yoast SEO title and description meta to generated pages.
 */
class Yoast_Meta_Writer {
	public const TITLE_KEY       = '_yoast_wpseo_title';
	public const DESCRIPTION_KEY = '_yoast_wpseo_metadesc';

	private $logger;

	public function __construct( ?Logger $logger = null ) {
		$this->logger = $logger ?? new Logger();
	}

	/**
	 * Write Yoast title and description meta for a page.
	 *
	 * @param int   $post_id Page ID.
	 * @param array $meta    SEO meta values.
	 *
	 * @return bool True when at least one supported meta key was written.
	 */
	public function write( int $post_id, array $meta ): bool {
		$written = false;
		$title   = $meta['title'] ?? $meta['seo_title'] ?? '';
		$desc    = $meta['description'] ?? $meta['seo_description'] ?? $meta['metadesc'] ?? '';

		if ( '' !== (string) $title ) {
			\update_post_meta( $post_id, self::TITLE_KEY, \sanitize_text_field( (string) $title ) );
			$written = true;
		}

		if ( '' !== (string) $desc ) {
			\update_post_meta( $post_id, self::DESCRIPTION_KEY, \sanitize_textarea_field( (string) $desc ) );
			$written = true;
		}

		$this->logger->log( $written ? 'info' : 'warning', $written ? 'Yoast metadata written.' : 'No Yoast metadata values supplied.', [ 'post_id' => $post_id ] );

		return $written;
	}
}
