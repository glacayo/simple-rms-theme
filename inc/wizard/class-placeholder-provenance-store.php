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
 * Backed by a non-autoloaded option. sync() matches layout+field+hash, not row index.
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
	 * @param mixed $value Field value used to compute value_hash.
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
			'value_hash' => $this->value_hash( $value ),
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

	public function value_hash( $value ): string {
		$json = \wp_json_encode( $this->canonicalize( $value ) );

		return sha1( false === $json ? '' : $json );
	}

	public function is_placeholder_payload( $value, string $hash ): bool {
		return '' !== $hash && $this->value_hash( $value ) === $hash;
	}

	/**
	 * Register the ACF save adapter. Invoked from wizard-init.php.
	 */
	public static function register(): void {
		\add_action( 'acf/save_post', [ self::class, 'handle_acf_save_post' ], 20, 1 );
	}

	/**
	 * `acf/save_post` adapter (priority 20). Never syncs without a complete snapshot.
	 *
	 * @param mixed $post_id ACF save target (post ID, "options", "user_n", "term_n").
	 */
	public static function handle_acf_save_post( $post_id ): bool {
		static $running = false;
		if ( $running ) {
			return false;
		}
		if ( ! is_numeric( $post_id ) ) {
			return false;
		}
		$id = \absint( $post_id );
		if ( $id <= 0 ) {
			return false;
		}
		if ( \defined( 'DOING_AUTOSAVE' ) && \DOING_AUTOSAVE ) {
			return false;
		}
		if ( function_exists( 'wp_is_post_autosave' ) && \wp_is_post_autosave( $id ) ) {
			return false;
		}
		if ( function_exists( 'wp_is_post_revision' ) && \wp_is_post_revision( $id ) ) {
			return false;
		}
		if ( ! function_exists( 'get_field_object' ) || ! function_exists( 'get_post' ) ) {
			return false;
		}
		$post = \get_post( $id );
		if ( ! $post || 'page' !== ( $post->post_type ?? '' ) ) {
			return false;
		}
		$sections = self::complete_page_sections_snapshot( $id );
		if ( null === $sections ) {
			return false;
		}
		$running = true;
		try {
			return ( new self() )->sync( $id, $sections );
		} finally {
			$running = false;
		}
	}

	/**
	 * Formatted `page_sections` snapshot from a complete ACF field object.
	 *
	 * `get_field( 'page_sections' )` returns false for both an empty flexible-content
	 * value and a failed read. A valid field object with loaded/formatted `value === false`
	 * is a complete empty snapshot and becomes `[]`. Failed/incomplete reads return null.
	 *
	 * @return array<int,array<string,mixed>>|null
	 */
	private static function complete_page_sections_snapshot( int $post_id ): ?array {
		$field = \get_field_object( 'page_sections', $post_id, true, true );
		if ( ! is_array( $field ) || ! array_key_exists( 'value', $field ) ) {
			return null;
		}

		$value = $field['value'];
		if ( false === $value ) {
			return [];
		}
		if ( ! is_array( $value ) ) {
			return null;
		}

		return $value;
	}

	/**
	 * Reconcile provenance with a complete `page_sections` snapshot.
	 *
	 * Compatible with `acf/save_post` (priority 20). Invalid snapshots are a no-op.
	 * A valid empty list clears this page only.
	 *
	 * @param array<int,array<string,mixed>> $sections
	 */
	public function sync( int $post_id, array $sections ): bool {
		$post_id = \absint( $post_id );
		if ( $post_id <= 0 || ! $this->is_valid_snapshot( $sections ) ) {
			return false;
		}

		$this->ensure_loaded();
		$entries = is_array( $this->entries ) ? $this->entries : [];
		$page    = is_array( $entries[ $post_id ] ?? null ) ? $entries[ $post_id ] : [];
		$pool    = $this->occurrence_pool( $sections );
		$kept    = [];

		foreach ( $page as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			$layout = \sanitize_key( (string) ( $entry['layout'] ?? '' ) );
			$field  = \sanitize_key( (string) ( $entry['field'] ?? '' ) );
			$hash   = (string) ( $entry['value_hash'] ?? '' );
			$token  = $layout . "\0" . $field . "\0" . $hash;

			if ( [] === ( $pool[ $token ] ?? [] ) ) {
				continue;
			}

			$new_row          = (int) array_shift( $pool[ $token ] );
			$entry['row']     = $new_row;
			$kept[ $new_row . ':' . $field ] = $entry;
		}

		if ( [] === $kept ) {
			unset( $entries[ $post_id ] );
		} else {
			$entries[ $post_id ] = $kept;
		}

		return $this->persist( $entries );
	}

	/**
	 * @param array<int,mixed> $sections
	 */
	private function is_valid_snapshot( array $sections ): bool {
		if ( ! $this->is_list( $sections ) ) {
			return false;
		}

		foreach ( $sections as $row ) {
			if ( ! is_array( $row ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * @param array<int,array<string,mixed>> $sections
	 * @return array<string,array<int,int>>
	 */
	private function occurrence_pool( array $sections ): array {
		$pool = [];

		foreach ( $sections as $index => $row ) {
			$layout = \sanitize_key( (string) ( $row['acf_fc_layout'] ?? '' ) );
			if ( '' === $layout ) {
				continue;
			}

			foreach ( $row as $field => $value ) {
				$field = \sanitize_key( (string) $field );
				if ( '' === $field || 'acf_fc_layout' === $field ) {
					continue;
				}

				$token            = $layout . "\0" . $field . "\0" . $this->value_hash( $value );
				$pool[ $token ][] = (int) $index;
			}
		}

		return $pool;
	}

	private function canonicalize( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}

		if ( $this->is_list( $value ) ) {
			$out = [];
			foreach ( $value as $item ) {
				$out[] = $this->canonicalize( $item );
			}

			return $out;
		}

		$keys = array_keys( $value );
		sort( $keys, SORT_STRING );
		$out = [];
		foreach ( $keys as $key ) {
			$out[ $key ] = $this->canonicalize( $value[ $key ] );
		}

		return $out;
	}

	private function is_list( array $value ): bool {
		$expected = 0;
		foreach ( $value as $key => $_item ) {
			if ( $expected !== $key ) {
				return false;
			}
			++$expected;
		}

		return true;
	}

	private function ensure_loaded(): void {
		if ( null !== $this->entries ) {
			return;
		}

		$stored        = \get_option( self::OPTION_KEY, [] );
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
