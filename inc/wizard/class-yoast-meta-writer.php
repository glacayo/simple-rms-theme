<?php
/**
 * Wizard Yoast metadata writer.
 *
 * @package Simple_RMS_Theme
 */

namespace Inc\Wizard;

defined( 'ABSPATH' ) || exit;

/**
 * Writes Yoast SEO title, description, and robots meta for wizard pages.
 */
class Yoast_Meta_Writer {
	public const TITLE_KEY       = '_yoast_wpseo_title';
	public const DESCRIPTION_KEY = '_yoast_wpseo_metadesc';
	public const NOINDEX_KEY     = '_yoast_wpseo_meta-robots-noindex';

	/** Common SERP-friendly max length for SEO titles. */
	public const TITLE_MAX_LENGTH = 60;

	/** Common SERP-friendly max length for meta descriptions. */
	public const DESCRIPTION_MAX_LENGTH = 155;

	private $logger;

	/** @var bool Whether missing-Yoast was already logged this request. */
	private static $missing_yoast_logged = false;

	public function __construct( ?Logger $logger = null ) {
		$this->logger = $logger ?? new Logger();
	}

	/**
	 * Whether Yoast SEO appears active for optional title/metadesc writes.
	 */
	public static function is_yoast_active(): bool {
		if ( defined( 'WPSEO_VERSION' ) ) {
			return true;
		}

		if ( ! function_exists( 'is_plugin_active' ) ) {
			$plugin_php = \ABSPATH . 'wp-admin/includes/plugin.php';

			if ( is_readable( $plugin_php ) ) {
				require_once $plugin_php;
			}
		}

		// Yoast package slug is wordpress-seo/wp-seo.php.
		return function_exists( 'is_plugin_active' ) && \is_plugin_active( 'wordpress-seo/wp-seo.php' );
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

	/**
	 * Write per-landing Yoast title/metadesc from keyword + type when Yoast is active.
	 *
	 * When Yoast is absent, skips title/metadesc writes and logs once per request.
	 *
	 * @param int    $post_id         Landing page ID.
	 * @param string $primary_keyword Primary keyword.
	 * @param string $landing_type    seo|ads.
	 * @param string $title           Optional page title for fallback copy.
	 *
	 * @return bool True when title/metadesc were written.
	 */
	public function write_landing_seo( int $post_id, string $primary_keyword, string $landing_type, string $title = '' ): bool {
		$post_id         = \absint( $post_id );
		$primary_keyword = \sanitize_text_field( $primary_keyword );
		$landing_type    = in_array( $landing_type, [ 'seo', 'ads' ], true ) ? $landing_type : 'seo';
		$title           = \sanitize_text_field( $title );

		if ( $post_id <= 0 || '' === $primary_keyword ) {
			return false;
		}

		if ( ! self::is_yoast_active() ) {
			if ( ! self::$missing_yoast_logged ) {
				$this->logger->log(
					'info',
					'Yoast SEO is not active; skipping landing title/metadesc writes.',
					[ 'post_id' => $post_id ]
				);
				self::$missing_yoast_logged = true;
			}

			return false;
		}

		$company = '';

		if ( function_exists( 'get_field' ) ) {
			$company = \sanitize_text_field( (string) \get_field( 'company_name', 'option' ) );
		}

		if ( '' === $company ) {
			$company = \sanitize_text_field( (string) \get_bloginfo( 'name' ) );
		}

		$suffix = 'ads' === $landing_type
			? \__( 'Get a free estimate', 'simple-rms-theme' )
			: \__( 'Trusted local experts', 'simple-rms-theme' );

		$meta_title = trim(
			sprintf(
				/* translators: 1: primary keyword, 2: company or site name. */
				\__( '%1$s | %2$s', 'simple-rms-theme' ),
				$primary_keyword,
				'' !== $company ? $company : ( '' !== $title ? $title : \get_bloginfo( 'name' ) )
			)
		);

		$meta_desc = trim(
			sprintf(
				/* translators: 1: primary keyword, 2: short CTA/trust phrase, 3: company name. */
				\__( 'Learn about %1$s. %2$s%3$s', 'simple-rms-theme' ),
				$primary_keyword,
				$suffix,
				'' !== $company ? ' — ' . $company : ''
			)
		);

		// Keep within common SERP-friendly bounds.
		if ( function_exists( 'mb_substr' ) ) {
			$meta_title = \mb_substr( $meta_title, 0, self::TITLE_MAX_LENGTH );
			$meta_desc  = \mb_substr( $meta_desc, 0, self::DESCRIPTION_MAX_LENGTH );
		} else {
			$meta_title = substr( $meta_title, 0, self::TITLE_MAX_LENGTH );
			$meta_desc  = substr( $meta_desc, 0, self::DESCRIPTION_MAX_LENGTH );
		}

		return $this->write(
			$post_id,
			[
				'title'       => $meta_title,
				'description' => $meta_desc,
			]
		);
	}

	/**
	 * Set or clear Ads noindex meta and verify read-back.
	 *
	 * Always writes the Yoast noindex post meta key (storage), independent of Yoast being active.
	 * wp_robots in wizard-init.php provides the second protection layer for Ads landings.
	 *
	 * @param int  $post_id  Landing page ID.
	 * @param bool $noindex  True to force noindex=1, false to clear.
	 *
	 * @return true|\WP_Error
	 */
	public function set_noindex( int $post_id, bool $noindex ) {
		$post_id = \absint( $post_id );

		if ( $post_id <= 0 ) {
			return new \WP_Error(
				'rms_wizard_noindex_invalid_post',
				\__( 'Invalid landing page for noindex sync.', 'simple-rms-theme' ),
				[ 'status' => 500 ]
			);
		}

		if ( $noindex ) {
			\update_post_meta( $post_id, self::NOINDEX_KEY, '1' );
			$read_back = (string) \get_post_meta( $post_id, self::NOINDEX_KEY, true );

			if ( '1' !== $read_back ) {
				$this->logger->log(
					'error',
					'Ads landing noindex meta failed read-back.',
					[
						'post_id'   => $post_id,
						'read_back' => $read_back,
					]
				);

				return new \WP_Error(
					'rms_wizard_ads_noindex_failed',
					\__( 'Ads landing noindex meta could not be verified. The landing was not marked complete.', 'simple-rms-theme' ),
					[
						'status'  => 500,
						'post_id' => $post_id,
					]
				);
			}

			$this->logger->log( 'info', 'Ads landing noindex meta written and verified.', [ 'post_id' => $post_id ] );

			return true;
		}

		\delete_post_meta( $post_id, self::NOINDEX_KEY );
		$read_back = (string) \get_post_meta( $post_id, self::NOINDEX_KEY, true );

		if ( '1' === $read_back ) {
			$this->logger->log(
				'error',
				'SEO landing noindex meta could not be cleared.',
				[ 'post_id' => $post_id ]
			);

			return new \WP_Error(
				'rms_wizard_seo_noindex_clear_failed',
				\__( 'SEO landing noindex meta could not be cleared.', 'simple-rms-theme' ),
				[
					'status'  => 500,
					'post_id' => $post_id,
				]
			);
		}

		$this->logger->log( 'info', 'SEO landing noindex meta cleared.', [ 'post_id' => $post_id ] );

		return true;
	}

	/**
	 * Read whether noindex meta is currently set to 1.
	 */
	public function has_noindex( int $post_id ): bool {
		return '1' === (string) \get_post_meta( \absint( $post_id ), self::NOINDEX_KEY, true );
	}
}
