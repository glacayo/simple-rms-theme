<?php
/**
 * Wizard ACF JSON import step service.
 *
 * @package Simple_RMS_Theme
 */

namespace Inc\Wizard;

defined( 'ABSPATH' ) || exit;

/**
 * Imports ACF field groups from the theme acf-json directory.
 */
class Step_ACF_Import {
	private const STEP = 'acf-import';

	private $logger;
	private $state_manager;
	private $json_path;

	public function __construct( ?Logger $logger = null, ?State_Manager $state_manager = null, string $json_path = '' ) {
		$this->logger        = $logger ?? new Logger();
		$this->state_manager = $state_manager ?? new State_Manager();
		$this->json_path     = $json_path ?: \trailingslashit( \get_template_directory() ) . 'acf-json';
	}

	/**
	 * Import available ACF JSON field groups, skipping existing groups.
	 *
	 * @return array<string,array<int,array<string,string>>>
	 */
	public function import(): array {
		$result = [ 'imported' => [], 'skipped' => [], 'failed' => [] ];

		if ( ! function_exists( 'acf_import_field_group' ) ) {
			$this->logger->log( 'error', 'ACF import API is not available.' );
			$this->state_manager->set_step_status( self::STEP, 'failed' );

			return $result;
		}

		foreach ( glob( \trailingslashit( $this->json_path ) . '*.json' ) ?: [] as $file ) {
			$group = json_decode( (string) file_get_contents( $file ), true );

			if ( ! is_array( $group ) || empty( $group['key'] ) ) {
				$result['failed'][] = [ 'file' => basename( $file ), 'reason' => 'invalid json' ];
				continue;
			}

			$key = (string) $group['key'];

			if ( $this->field_group_exists( $key ) ) {
				$result['skipped'][] = [ 'key' => $key, 'reason' => 'skipped: already exists' ];
				$this->logger->log( 'warning', 'ACF field group skipped: already exists.', [ 'key' => $key ] );
				continue;
			}

			\acf_import_field_group( $group );
			$result['imported'][] = [ 'key' => $key, 'title' => (string) ( $group['title'] ?? $key ) ];
			$this->logger->log( 'info', 'ACF field group imported.', [ 'key' => $key ] );
		}

		$this->state_manager->set_step_status( self::STEP, empty( $result['failed'] ) ? 'complete' : 'failed' );

		return $result;
	}

	private function field_group_exists( string $key ): bool {
		if ( function_exists( 'acf_get_field_group' ) && \acf_get_field_group( $key ) ) {
			return true;
		}

		$existing = \get_posts(
			[
				'post_type'      => 'acf-field-group',
				'name'           => $key,
				'post_status'    => [ 'publish', 'acf-disabled' ],
				'posts_per_page' => 1,
				'fields'         => 'ids',
			]
		);

		return ! empty( $existing );
	}
}
