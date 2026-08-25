<?php
/**
 * Site-wide atomic mutation fence for wizard step execution.
 *
 * @package Simple_RMS_Theme
 */

namespace Inc\Wizard;

defined( 'ABSPATH' ) || exit;

/**
 * Serializes every mutating execute_step() dispatch.
 *
 * Uses a direct INSERT IGNORE against the unique option_name index plus
 * exact serialized-value compare-and-delete. WordPress add_option() cannot
 * be used as a mutex because its duplicate-key update may report success
 * to two concurrent contenders.
 *
 * Owner tokens never appear in public state, REST payloads, or logs.
 */
class Wizard_Mutation_Fence {

	public const OPTION_NAME = 'rms_wizard_mutation_fence';

	/**
	 * Maximum PHP execution budget used by long wizard steps (seconds).
	 */
	public const EXECUTION_BUDGET = 1200;

	/**
	 * Recovery margin beyond the execution budget (seconds).
	 */
	public const MARGIN = 60;

	/**
	 * Finite fence TTL. Strictly greater than EXECUTION_BUDGET + MARGIN so a
	 * live worker cannot be overtaken by stale recovery while still running.
	 */
	public const TTL = 1320;

	/**
	 * Acquire the site-wide mutation fence.
	 *
	 * @return string|\WP_Error Owner token on success. Never include the token in responses or logs.
	 */
	public function acquire() {
		$option_name = self::OPTION_NAME;
		$existing    = $this->read_record( $option_name );

		if ( null !== $existing ) {
			$expires = is_array( $existing['value'] ) ? (int) ( $existing['value']['expires_at'] ?? 0 ) : 0;

			if ( $expires > time() || 0 === $expires ) {
				return $this->busy_error();
			}

			if ( ! $this->delete_record( $option_name, $existing['raw'] ) ) {
				return $this->busy_error();
			}
		}

		$owner   = $this->mint_owner_token();
		$now     = time();
		$payload = [
			'owner'       => $owner,
			'acquired_at' => $now,
			'expires_at'  => $now + self::TTL,
		];

		if ( ! $this->insert_record( $option_name, $payload ) ) {
			return $this->busy_error();
		}

		return $owner;
	}

	/**
	 * Release the fence only when the exact owner still owns the stored row.
	 *
	 * A stale or foreign owner cannot delete a replacement fence.
	 */
	public function release( string $owner ): void {
		if ( '' === $owner ) {
			return;
		}

		$option_name = self::OPTION_NAME;
		$existing    = $this->read_record( $option_name );

		if (
			null !== $existing
			&& is_array( $existing['value'] )
			&& (string) ( $existing['value']['owner'] ?? '' ) === $owner
		) {
			$this->delete_record( $option_name, $existing['raw'] );
		}
	}

	/**
	 * Whether a non-expired fence row is currently held.
	 */
	public function is_held(): bool {
		$existing = $this->read_record( self::OPTION_NAME );

		if ( null === $existing || ! is_array( $existing['value'] ) ) {
			return false;
		}

		$expires = (int) ( $existing['value']['expires_at'] ?? 0 );

		return $expires > time() || 0 === $expires;
	}

	/**
	 * @return \WP_Error
	 */
	private function busy_error() {
		return new \WP_Error(
			'rms_wizard_busy',
			\__( 'Another setup wizard action is already running.', 'simple-rms-theme' ),
			[ 'status' => 409 ]
		);
	}

	/**
	 * Read the exact serialized fence row from wp_options, bypassing option cache.
	 *
	 * @return array{raw:string,value:mixed}|null
	 */
	private function read_record( string $option_name ): ?array {
		global $wpdb;

		$raw = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
				$option_name
			)
		);

		if ( null === $raw ) {
			return null;
		}

		return [
			'raw'   => (string) $raw,
			'value' => \maybe_unserialize( $raw ),
		];
	}

	/**
	 * Atomically insert a fence row. The unique option_name index admits one owner.
	 */
	private function insert_record( string $option_name, array $payload ): bool {
		global $wpdb;

		$inserted = $wpdb->query(
			$wpdb->prepare(
				"INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, %s)",
				$option_name,
				\maybe_serialize( $payload ),
				'no'
			)
		);

		if ( 1 !== $inserted ) {
			return false;
		}

		\wp_cache_delete( $option_name, 'options' );

		return true;
	}

	/**
	 * Delete only the exact fence value previously read (compare-and-delete).
	 */
	private function delete_record( string $option_name, string $raw_value ): bool {
		global $wpdb;

		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s",
				$option_name,
				$raw_value
			)
		);

		if ( 1 !== $deleted ) {
			return false;
		}

		\wp_cache_delete( $option_name, 'options' );

		return true;
	}

	private function mint_owner_token(): string {
		if ( function_exists( 'wp_generate_uuid4' ) ) {
			return \wp_generate_uuid4();
		}

		return uniqid( 'owner_', true );
	}
}
