<?php
/**
 * Canonical reusable section store.
 *
 * @package Simple_RMS_Theme
 */

namespace Inc\Wizard;

defined( 'ABSPATH' ) || exit;

/**
 * First-write store for neutral reusable section payloads.
 *
 * Backed by a dedicated option so full payloads stay out of wizard state.
 */
class Canonical_Section_Store {
	public const OPTION_KEY = 'rms_wizard_canonical_sections';

	/**
	 * Layouts that must never be stored as canonical content.
	 *
	 * @var string[]
	 */
	private const EXCLUDED_LAYOUTS = [
		'hero',
		'seo-content',
	];

	/**
	 * Lazy-loaded option payload.
	 *
	 * @var array<string,array<string,mixed>>|null
	 */
	private $sections = null;

	/**
	 * Whether the in-memory cache has been loaded from the option.
	 *
	 * @var bool
	 */
	private $loaded = false;

	/**
	 * Return whether a layout has a non-empty canonical payload.
	 */
	public function has( string $layout ): bool {
		$layout = $this->normalize_layout( $layout );

		if ( '' === $layout || $this->is_excluded_layout( $layout ) ) {
			return false;
		}

		$entry = $this->get_entry( $layout );

		return is_array( $entry['payload'] ?? null ) && [] !== $entry['payload'];
	}

	/**
	 * Return the canonical payload for a layout, or an empty array.
	 *
	 * @return array<string,mixed>
	 */
	public function get( string $layout ): array {
		$layout = $this->normalize_layout( $layout );

		if ( '' === $layout || $this->is_excluded_layout( $layout ) ) {
			return [];
		}

		$entry   = $this->get_entry( $layout );
		$payload = $entry['payload'] ?? null;

		return is_array( $payload ) ? $payload : [];
	}

	/**
	 * Write a layout payload only when the store is empty for that layout.
	 *
	 * @param string               $layout Layout key.
	 * @param array<string,mixed>  $row    Prepared ACF flexible-content row.
	 *
	 * @return bool True when a first-write occurred.
	 */
	public function set_if_empty( string $layout, array $row ): bool {
		$layout = $this->normalize_layout( $layout );

		if ( '' === $layout || $this->is_excluded_layout( $layout ) || [] === $row ) {
			return false;
		}

		if ( $this->has( $layout ) ) {
			return false;
		}

		return $this->write_entry( $layout, $row );
	}

	/**
	 * Explicitly replace an existing (or empty) canonical payload.
	 *
	 * Callers must gate this behind user confirmation.
	 *
	 * @param string               $layout Layout key.
	 * @param array<string,mixed>  $row    Prepared ACF flexible-content row.
	 *
	 * @return bool True when the entry was written.
	 */
	public function replace( string $layout, array $row ): bool {
		$layout = $this->normalize_layout( $layout );

		if ( '' === $layout || $this->is_excluded_layout( $layout ) || [] === $row ) {
			return false;
		}

		return $this->write_entry( $layout, $row );
	}

	/**
	 * Return a compact summary for wizard state (no full payloads).
	 *
	 * @return array<string,array{has_payload:bool,generated_at:string}>
	 */
	public function summary(): array {
		$sections = $this->all();
		$summary  = [];

		foreach ( $sections as $layout => $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			$payload = is_array( $entry['payload'] ?? null ) ? $entry['payload'] : [];

			if ( [] === $payload ) {
				continue;
			}

			$summary[ $layout ] = [
				'has_payload'  => true,
				'generated_at' => (string) ( $entry['generated_at'] ?? '' ),
			];
		}

		return $summary;
	}

	/**
	 * Return the full store map (lazy-loaded).
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function all(): array {
		$this->ensure_loaded();

		return is_array( $this->sections ) ? $this->sections : [];
	}

	/**
	 * Drop the in-memory cache so the next read hits the option again.
	 */
	public function reset_cache(): void {
		$this->sections = null;
		$this->loaded   = false;
	}

	/**
	 * Lazy-load the option once per request/instance.
	 */
	private function ensure_loaded(): void {
		if ( $this->loaded ) {
			return;
		}

		$stored = \get_option( self::OPTION_KEY, [] );

		if ( ! is_array( $stored ) ) {
			$stored = [];
		}

		$normalized = [];

		foreach ( $stored as $layout => $entry ) {
			$layout = $this->normalize_layout( (string) $layout );

			if ( '' === $layout || $this->is_excluded_layout( $layout ) || ! is_array( $entry ) ) {
				continue;
			}

			$payload = is_array( $entry['payload'] ?? null ) ? $entry['payload'] : [];

			if ( [] === $payload ) {
				continue;
			}

			$normalized[ $layout ] = [
				'payload'      => $payload,
				'generated_at' => (string) ( $entry['generated_at'] ?? '' ),
			];
		}

		$this->sections = $normalized;
		$this->loaded   = true;
	}

	/**
	 * @return array<string,mixed>
	 */
	private function get_entry( string $layout ): array {
		$sections = $this->all();
		$entry    = $sections[ $layout ] ?? [];

		return is_array( $entry ) ? $entry : [];
	}

	/**
	 * Persist one layout entry and refresh the local cache only after success.
	 *
	 * Success requires the full stored entry (payload + generated_at) to match
	 * what we intended to write. Post-state reads are authoritative because
	 * update_option() can return false for identical values.
	 *
	 * @param string              $layout Layout key.
	 * @param array<string,mixed> $row    Prepared row payload.
	 */
	private function write_entry( string $layout, array $row ): bool {
		$this->ensure_loaded();

		$entry = [
			'payload'      => $row,
			'generated_at' => \current_time( 'mysql', true ),
		];

		$sections            = is_array( $this->sections ) ? $this->sections : [];
		$sections[ $layout ] = $entry;

		// Never mutate the in-memory cache before a real persistence outcome.
		$saved = \update_option( self::OPTION_KEY, $sections, false );

		if ( $saved ) {
			$this->sections = $sections;
			$this->loaded   = true;

			return true;
		}

		// update_option() returns false when the DB value is already identical.
		// Reload and require the full normalized entry to match (not payload alone).
		$this->reset_cache();
		$persisted = $this->get_entry( $layout );

		return is_array( $persisted )
			&& ( $persisted['payload'] ?? null ) === $entry['payload']
			&& (string) ( $persisted['generated_at'] ?? '' ) === (string) $entry['generated_at'];
	}

	private function normalize_layout( string $layout ): string {
		return \sanitize_key( $layout );
	}

	private function is_excluded_layout( string $layout ): bool {
		return in_array( $layout, self::EXCLUDED_LAYOUTS, true );
	}
}
