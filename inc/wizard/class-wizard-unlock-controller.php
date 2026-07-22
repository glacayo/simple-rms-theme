<?php
/**
 * Controlled unlock controller for completed wizard sites.
 *
 * @package Simple_RMS_Theme
 */

namespace Inc\Wizard;

defined( 'ABSPATH' ) || exit;

/**
 * Reversibly re-opens a completed wizard without destroying completion state.
 */
class Wizard_Unlock_Controller {
	public const UNLOCKED_AT_OPTION = 'rms_wizard_unlocked_at';
	public const UNLOCKED_BY_OPTION = 'rms_wizard_unlocked_by';
	public const NONCE_ACTION       = 'rms_wizard_controlled_unlock';
	public const UNLOCK_ACTION      = 'rms_wizard_unlock';
	public const RELOCK_ACTION      = 'rms_wizard_relock';

	/**
	 * Whether user-facing controlled unlock is enabled.
	 *
	 * Enabled together with the generate-pages landing deletion guard (Phase 2 task 2.6).
	 */
	public const CONTROLLED_UNLOCK_ENABLED = true;

	private $state_manager;
	private $logger;

	public function __construct( ?State_Manager $state_manager = null, ?Logger $logger = null ) {
		$this->state_manager = $state_manager ?? new State_Manager();
		$this->logger        = $logger ?? new Logger();
	}

	/**
	 * Whether user-facing controlled unlock UI/actions are available.
	 */
	public static function is_controlled_unlock_enabled(): bool {
		return true === self::CONTROLLED_UNLOCK_ENABLED;
	}

	/**
	 * Whether RMS_WIZARD_FORCE bypasses the completed lock.
	 */
	public static function is_force_unlocked(): bool {
		return \defined( 'RMS_WIZARD_FORCE' ) && true === \RMS_WIZARD_FORCE;
	}

	/**
	 * Whether a stored unlock marker exists (raw option presence).
	 *
	 * Used for relock cleanup of stale markers. Does not imply an effective unlock.
	 */
	public static function has_unlock_marker(): bool {
		$unlocked_at = \get_option( self::UNLOCKED_AT_OPTION, '' );

		return is_string( $unlocked_at ) && '' !== $unlocked_at;
	}

	/**
	 * Whether unlock effectively bypasses the completed lock.
	 *
	 * Ignores stale/manual `rms_wizard_unlocked_at` while controlled unlock is disabled.
	 * `RMS_WIZARD_FORCE` is handled separately via `is_force_unlocked()` / callers.
	 */
	public static function is_unlocked(): bool {
		if ( ! self::is_controlled_unlock_enabled() ) {
			return false;
		}

		return self::has_unlock_marker();
	}

	/**
	 * Unlock a completed wizard for editing.
	 *
	 * Does not modify `rms_wizard_completed` or other wizard state.
	 * Inactive while CONTROLLED_UNLOCK_ENABLED is false (REST/admin both hit this gate).
	 *
	 * @return array<string,mixed>|\WP_Error
	 */
	public function unlock() {
		if ( ! \current_user_can( 'manage_options' ) ) {
			return new \WP_Error(
				'rms_wizard_forbidden',
				\__( 'You do not have permission to unlock the setup wizard.', 'simple-rms-theme' ),
				[ 'status' => 403 ]
			);
		}

		if ( ! self::is_controlled_unlock_enabled() ) {
			return new \WP_Error(
				'rms_wizard_unlock_unavailable',
				\__( 'Controlled unlock is not available until landing page protection is enabled.', 'simple-rms-theme' ),
				[ 'status' => 503 ]
			);
		}

		// State_Manager is the single source of truth for the completion flag.
		if ( ! $this->state_manager->has_completion_flag() ) {
			return new \WP_Error(
				'rms_wizard_not_completed',
				\__( 'The setup wizard is not completed, so unlock is not required.', 'simple-rms-theme' ),
				[ 'status' => 400 ]
			);
		}

		if ( self::has_unlock_marker() ) {
			return [
				'success'     => true,
				'action'      => 'unlock',
				'already'     => true,
				'unlocked_at' => (string) \get_option( self::UNLOCKED_AT_OPTION, '' ),
				'unlocked_by' => (int) \get_option( self::UNLOCKED_BY_OPTION, 0 ),
				'completed'   => true,
			];
		}

		$unlocked_at = \current_time( 'mysql', true );
		$user_id     = (int) \get_current_user_id();

		\update_option( self::UNLOCKED_AT_OPTION, $unlocked_at, false );
		\update_option( self::UNLOCKED_BY_OPTION, $user_id, false );

		// Post-state is authoritative: update_option() can return false for identical values.
		$persisted_at = (string) \get_option( self::UNLOCKED_AT_OPTION, '' );
		$persisted_by = (int) \get_option( self::UNLOCKED_BY_OPTION, 0 );
		$at_ok        = $persisted_at === $unlocked_at;
		$by_ok        = $persisted_by === $user_id;

		if ( ! $at_ok || ! $by_ok || ! self::has_unlock_marker() ) {
			$this->logger->log(
				'error',
				'Unlock persistence failed; rolling back partial unlock markers.',
				[
					'expected_at'  => $unlocked_at,
					'expected_by'  => $user_id,
					'persisted_at' => $persisted_at,
					'persisted_by' => $persisted_by,
					'at_ok'        => $at_ok,
					'by_ok'        => $by_ok,
				]
			);

			// Best-effort rollback so a partial write does not look unlocked.
			\delete_option( self::UNLOCKED_AT_OPTION );
			\delete_option( self::UNLOCKED_BY_OPTION );

			if ( self::has_unlock_marker() ) {
				$this->logger->log(
					'error',
					'Unlock rollback left a residual unlock marker.',
					[
						'unlocked_at' => (string) \get_option( self::UNLOCKED_AT_OPTION, '' ),
						'unlocked_by' => (int) \get_option( self::UNLOCKED_BY_OPTION, 0 ),
					]
				);
			}

			return new \WP_Error(
				'rms_wizard_unlock_persist_failed',
				\__( 'The setup wizard could not be unlocked because the unlock state failed to persist.', 'simple-rms-theme' ),
				[ 'status' => 500 ]
			);
		}

		$this->logger->log(
			'info',
			'Setup wizard unlocked for editing.',
			[
				'unlocked_at' => $persisted_at,
				'unlocked_by' => $persisted_by,
			]
		);

		return [
			'success'     => true,
			'action'      => 'unlock',
			'already'     => false,
			'unlocked_at' => $persisted_at,
			'unlocked_by' => $persisted_by,
			'completed'   => true,
		];
	}

	/**
	 * Restore read-only state without destroying completion.
	 *
	 * Allowed while controlled unlock is disabled so stale markers can be cleared.
	 *
	 * @return array<string,mixed>|\WP_Error
	 */
	public function relock() {
		if ( ! \current_user_can( 'manage_options' ) ) {
			return new \WP_Error(
				'rms_wizard_forbidden',
				\__( 'You do not have permission to re-lock the setup wizard.', 'simple-rms-theme' ),
				[ 'status' => 403 ]
			);
		}

		// State_Manager is the single source of truth for the completion flag.
		if ( ! $this->state_manager->has_completion_flag() ) {
			return new \WP_Error(
				'rms_wizard_not_completed',
				\__( 'The setup wizard is not completed, so re-lock is not applicable.', 'simple-rms-theme' ),
				[ 'status' => 400 ]
			);
		}

		// Clear stale markers even when controlled unlock is disabled.
		$had_marker = self::has_unlock_marker();

		if ( $had_marker ) {
			\delete_option( self::UNLOCKED_AT_OPTION );
			\delete_option( self::UNLOCKED_BY_OPTION );
		}

		// Confirm next request would no longer see a stored unlock marker.
		if ( self::has_unlock_marker() ) {
			$this->logger->log(
				'error',
				'Relock persistence failed; unlock marker still present after delete_option.',
				[
					'unlocked_at' => (string) \get_option( self::UNLOCKED_AT_OPTION, '' ),
					'unlocked_by' => (int) \get_option( self::UNLOCKED_BY_OPTION, 0 ),
					'user_id'     => (int) \get_current_user_id(),
				]
			);

			return new \WP_Error(
				'rms_wizard_relock_persist_failed',
				\__( 'The setup wizard could not be re-locked because the unlock state failed to clear.', 'simple-rms-theme' ),
				[ 'status' => 500 ]
			);
		}

		if ( $had_marker ) {
			$this->logger->log(
				'info',
				'Setup wizard re-locked.',
				[
					'was_unlocked' => true,
					'user_id'      => (int) \get_current_user_id(),
				]
			);
		}

		return [
			'success'   => true,
			'action'    => 'relock',
			'already'   => ! $had_marker,
			'completed' => true,
			'locked'    => true,
		];
	}

	/**
	 * Verify a nonce for admin unlock/relock form posts.
	 */
	public static function verify_admin_nonce( string $nonce ): bool {
		return (bool) \wp_verify_nonce( $nonce, self::NONCE_ACTION );
	}
}
