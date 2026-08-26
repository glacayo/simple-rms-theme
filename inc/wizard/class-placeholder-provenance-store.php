<?php
/**
 * Placeholder provenance store for wizard-built internal pages.
 *
 * @package Simple_RMS_Theme
 */

namespace Inc\Wizard;

defined( 'ABSPATH' ) || exit;

/**
 * Records unmarked placeholder fields so owners can find and replace them.
 *
 * Backed by a non-autoloaded option. sync() is intentionally not implemented here.
 */
final class Placeholder_Provenance_Store {
	public const OPTION_KEY = 'rms_wizard_placeholder_provenance';

	/**
	 * @var array<int,array<string,array<string,mixed>>>|null
	 */
	private $entries = null;

	/**
	 * Persist one placeholder field on a page.
	 *
	 * @param array<string,mixed> $value Field value used to compute value_hash.
	 */
	public function record( int $post_id, string $layout, int $row, string $field, string $reason, $value ): bool {
		$post_id = \absint( $post_id );
		$layout  = \sanitize_key( $layout );
		$field   = \sanitize_key( $field );
		$reason  = \sanitize_key( $reason );
		$row     = max( 0, $row );

		if ( $post_id <= 0 || '' === $layout || '' === $field ) {
			return false;
		}

		$key = $row . ':' . $field;
		$this->ensure_loaded();

		$entries = is_array( $this->entries ) ? $this->entries : [];
		$page    = is_array( $entries[ $post_id ] ?? null ) ? $entries[ $post_id ] : [];

		$page[ $key ] = [
			'layout'     => $layout,
			'row'        => $row,
			'field'      => $field,
			'reason'     => '' !== $reason ? $reason : 'missing_client_fact',
			'value_hash' => sha1( (string) \wp_json_encode( $value ) ),
			'written_at' => \current_time( 'mysql', true ),
		];

		$entries[ $post_id ] = $page;

		return $this->persist( $entries );
	}

	/**
	 * Return provenance entries for a page, optionally one field key.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function query( int $post_id, string $field = '' ): array {
		$post_id = \absint( $post_id );
		$this->ensure_loaded();
		$page = is_array( ( $this->entries[ $post_id ] ?? null ) ) ? $this->entries[ $post_id ] : [];

		if ( '' === $field ) {
			return $page;
		}

		$field   = \sanitize_key( $field );
		$matches = [];

		foreach ( $page as $key => $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			if ( $field === (string) ( $entry['field'] ?? '' ) ) {
				$matches[ $key ] = $entry;
			}
		}

		return $matches;
	}

	/**
	 * Outstanding placeholders across all pages.
	 *
	 * @return array<int,array{post_id:int,key:string,layout:string,row:int,field:string,reason:string}>
	 */
	public function queue(): array {
		$this->ensure_loaded();
		$queue = [];

		foreach ( is_array( $this->entries ) ? $this->entries : [] as $post_id => $page ) {
			if ( ! is_array( $page ) ) {
				continue;
			}

			foreach ( $page as $key => $entry ) {
				if ( ! is_array( $entry ) ) {
					continue;
				}

				$queue[] = [
					'post_id' => (int) $post_id,
					'key'     => (string) $key,
					'layout'  => (string) ( $entry['layout'] ?? '' ),
					'row'     => (int) ( $entry['row'] ?? 0 ),
					'field'   => (string) ( $entry['field'] ?? '' ),
					'reason'  => (string) ( $entry['reason'] ?? '' ),
				];
			}
		}

		return $queue;
	}

	private function ensure_loaded(): void {
		if ( null !== $this->entries ) {
			return;
		}

		$stored = \get_option( self::OPTION_KEY, [] );
		$this->entries = is_array( $stored ) ? $stored : [];
	}

	/**
	 * @param array<int,array<string,array<string,mixed>>> $entries
	 */
	private function persist( array $entries ): bool {
		$saved = \update_option( self::OPTION_KEY, $entries, false );

		if ( $saved ) {
			$this->entries = $entries;

			return true;
		}

		$stored = \get_option( self::OPTION_KEY, [] );

		if ( is_array( $stored ) && $stored === $entries ) {
			$this->entries = $entries;

			return true;
		}

		return false;
	}
}
