<?php
/**
 * Landing run orchestrator — resumable, one-item-per-request execution.
 *
 * @package Simple_RMS_Theme
 */

namespace Inc\Wizard;

defined( 'ABSPATH' ) || exit;

/**
 * Manages the persisted landing run plan and bounded per-item processing.
 *
 * Orchestration state only — does not duplicate state.landing_pages.
 * Owns: normalized run plan, per-item status, atomic mutex lease,
 * stale recovery, and atomic checkpoint coordination.
 */
class Landing_Run_Orchestrator {

		private const STATE_KEY      = 'landing_run';
		private const SCHEMA_VERSION  = 1;
		private const ITEM_EXECUTION_BUDGET = 1200;
		private const LEASE_MARGIN           = 60;
		private const START_FENCE_OPTION     = 'rms_landing_start_fence';
		private const START_FENCE_TTL        = 60;

	public const ITEM_PENDING     = 'pending';
	public const ITEM_RUNNING     = 'running';
	public const ITEM_COMPLETED   = 'completed';
	public const ITEM_INTERRUPTED = 'interrupted';
	public const ITEM_FAILED      = 'failed';

	public const RUN_PENDING     = 'pending';
	public const RUN_RUNNING     = 'running';
	public const RUN_COMPLETED   = 'completed';
	public const RUN_INTERRUPTED = 'interrupted';
	public const RUN_FAILED      = 'failed';

	/** Items that still need work (not completed). */
	public const ACTIVE_STATUSES = [ self::ITEM_PENDING, self::ITEM_RUNNING, self::ITEM_INTERRUPTED, self::ITEM_FAILED ];
	/** Items that can be picked up by get_next_item. */
	public const PROCESSABLE_STATUSES = [ self::ITEM_PENDING, self::ITEM_INTERRUPTED ];

	private $state_manager;
	private $logger;

	public function __construct(
		?State_Manager $state_manager = null,
		?Logger $logger = null
	) {
		$this->state_manager = $state_manager ?? new State_Manager();
		$this->logger        = $logger ?? new Logger();
	}

	/**
	 * Get the current persisted run plan (normalized), or null when absent.
	 *
	 * @return array<string,mixed>|null
	 */
		public function get_run(): ?array {
			$state = $this->state_manager->get_state();
			$run   = is_array( $state[ self::STATE_KEY ] ?? null ) ? $state[ self::STATE_KEY ] : null;

			if ( null === $run ) {
				return null;
			}

			return $this->normalize_run( $run );
		}

		/**
		 * Whether a valid execution lease is currently held.
		 */
		public function has_active_lease( ?array $run = null ): bool {
			return null !== $this->active_lease_expires_at( $run );
		}

		/**
		 * Expiry timestamp of a still-valid lease, or null when none is active.
		 *
		 * @param array<string,mixed>|null $run
		 */
		public function active_lease_expires_at( ?array $run = null ): ?int {
			$run = is_array( $run ) ? $run : $this->get_run();

			if ( null === $run ) {
				return null;
			}

			$run_id = (string) ( $run['run_id'] ?? '' );

			if ( '' === $run_id ) {
				return null;
			}

			$lease = $this->read_lease_record( $this->lease_option_name( $run_id ) );

			if ( null === $lease || ! is_array( $lease['value'] ) ) {
				return null;
			}

			$expires = (int) ( $lease['value']['expires_at'] ?? 0 );

			if ( $expires > time() || 0 === $expires ) {
				return $expires;
			}

			return null;
		}

		/**
		 * Whether start must refuse because a valid lease or unfinished run exists.
		 *
		 * Only a completed run (or no run) may be replaced by a genuinely new start.
		 * Pending, running, interrupted, and failed runs must be resumed.
		 */
		public function has_blocking_run(): bool {
			if ( $this->has_active_lease() ) {
				return true;
			}

			return $this->has_incomplete_run();
		}

		/**
		 * Whether a persisted run still has work and must not be replaced.
		 */
		public function has_incomplete_run(): bool {
			$run = $this->get_run();

			if ( null === $run ) {
				return false;
			}

			$items  = is_array( $run['items'] ?? null ) ? $run['items'] : [];
			$status = (string) ( $run['status'] ?? '' );

			if ( self::RUN_COMPLETED === $status ) {
				return $this->has_active_items( $items );
			}

			if ( in_array( $status, [ self::RUN_PENDING, self::RUN_RUNNING, self::RUN_INTERRUPTED, self::RUN_FAILED ], true ) ) {
				return true;
			}

			return $this->has_active_items( $items );
		}

		/**
		 * Public run view for the admin client. Never includes lease_owner.
		 *
		 * @return array<string,mixed>|null
		 */
		public function get_public_run(): ?array {
			$run = $this->get_run();

			if ( null === $run ) {
				return null;
			}

			return $this->public_run_view( $run );
		}

		/**
		 * Build a public view of a run plan.
		 *
		 * @param array<string,mixed> $run
		 *
		 * @return array<string,mixed>
		 */
		public function public_run_view( array $run ): array {
			$expires = $this->active_lease_expires_at( $run );

			return [
				'run_id'            => (string) ( $run['run_id'] ?? '' ),
				'status'            => (string) ( $run['status'] ?? '' ),
				'total'             => (int) ( $run['total'] ?? 0 ),
				'completed'         => (int) ( $run['completed'] ?? 0 ),
				'current_index'     => (int) ( $run['current_index'] ?? -1 ),
				'processing_active' => null !== $expires,
				'lease_expires_at'  => $expires,
				'items'             => array_map(
					static function ( $item ): array {
						$item = is_array( $item ) ? $item : [];

						return [
							'key'             => (string) ( $item['key'] ?? '' ),
							'landing_key'     => (string) ( $item['landing_key'] ?? $item['key'] ?? '' ),
							'id'              => (int) ( $item['post_id'] ?? $item['id'] ?? 0 ),
							'title'           => (string) ( $item['title'] ?? '' ),
							'slug'            => (string) ( $item['slug'] ?? '' ),
							'landing_type'    => (string) ( $item['landing_type'] ?? 'seo' ),
							'menu_eligible'   => ! empty( $item['menu_eligible'] ),
							'primary_keyword' => (string) ( $item['primary_keyword'] ?? '' ),
							'subkeywords'     => is_array( $item['subkeywords'] ?? null ) ? array_values( $item['subkeywords'] ) : [],
							'sections'        => is_array( $item['sections'] ?? null ) ? $item['sections'] : [],
							'status'          => (string) ( $item['status'] ?? 'pending' ),
							'post_id'         => (int) ( $item['post_id'] ?? 0 ),
							'error_code'      => (string) ( $item['error_code'] ?? '' ),
							'error_message'   => (string) ( $item['error_message'] ?? '' ),
						];
					},
					is_array( $run['items'] ?? null ) ? $run['items'] : []
				),
			];
		}

		/**
		 * Start a new run plan from submitted landing rows.
		 *
		 * Classifies existing unchanged entries as already complete.
		 * Persists the full plan before any AI work begins.
		 * Serializes check + persist behind an atomic start fence so two
		 * concurrent starts cannot both mint a plan. Incomplete runs are
		 * never overwritten; recovery goes through process/Resume.
		 *
		 * @param array<int,array<string,mixed>>         $rows             Parsed landing rows.
		 * @param array<string,array<string,mixed>>       $existing_landings Keyed by landing_key.
		 * @param array<string,bool>                      $replace_map       Per-layout replacement choices.
		 *
		 * @return array<string,mixed>|\WP_Error
		 */
		public function start_run( array $rows, array $existing_landings, array $replace_map ) {
			$fence_owner = $this->acquire_start_fence();

			if ( \is_wp_error( $fence_owner ) ) {
				return $fence_owner;
			}

			try {
				if ( $this->has_blocking_run() ) {
					return new \WP_Error(
						'rms_wizard_landing_run_active',
						\__( 'A landing run is already active or incomplete. Use Resume to continue the persisted plan.', 'simple-rms-theme' ),
						[ 'status' => 409 ]
					);
				}

				$run_id = $this->mint_run_id();
				$items  = [];
				$now    = time();

		foreach ( $rows as $row ) {
			$key          = (string) ( $row['landing_key'] ?? '' );
			$existing_id  = (int) ( $row['id'] ?? 0 );
			$existing_row = '' !== $key ? ( $existing_landings[ $key ] ?? null ) : null;

			$status = self::ITEM_PENDING;

			// Classify unchanged existing entries as already complete.
			// Per-item replace check: only layouts in replace_map that the item uses invalidate it.
			$item_replace_active = $this->item_has_replace( $row, $replace_map );

			if ( is_array( $existing_row ) && $existing_id > 0 && $this->landing_unchanged( $row, $existing_row ) && ! $item_replace_active ) {
				$status = self::ITEM_COMPLETED;
			}

			$items[] = [
				'key'             => $key,
				'landing_key'      => $key,
				'id'              => $existing_id,
				'title'           => (string) ( $row['title'] ?? '' ),
				'slug'            => (string) ( $row['slug'] ?? '' ),
				'landing_type'    => (string) ( $row['landing_type'] ?? 'seo' ),
				'menu_eligible'   => ! empty( $row['menu_eligible'] ),
				'primary_keyword' => (string) ( $row['primary_keyword'] ?? '' ),
				'subkeywords'     => is_array( $row['subkeywords'] ?? null ) ? array_values( $row['subkeywords'] ) : [],
				'sections'        => is_array( $row['sections'] ?? null ) ? $row['sections'] : [],
				'status'          => $status,
				'post_id'         => $existing_id,
				'error_code'      => '',
				'error_message'   => '',
				'started_at'      => null,
				'completed_at'    => self::ITEM_COMPLETED === $status ? $now : null,
			];
		}

		$completed = count( array_filter( $items, static fn( $i ): bool => self::ITEM_COMPLETED === $i['status'] ) );

		$run = [
			'run_id'         => $run_id,
			'schema_version' => self::SCHEMA_VERSION,
			'status'          => self::RUN_PENDING,
			'items'           => $items,
			'total'           => count( $items ),
			'completed'       => $completed,
			'current_index'    => $this->first_pending_index( $items ),
			'replace_map'      => $replace_map,
			'created_at'      => $now,
			'updated_at'      => $now,
		];

		if ( ! $this->persist_run( $run ) ) {
			return new \WP_Error(
				'rms_wizard_landing_run_persist_failed',
				\__( 'The landing run plan could not be persisted before processing.', 'simple-rms-theme' ),
				[ 'status' => 500 ]
			);
		}

				$this->logger->log(
					'info',
					'Landing run plan persisted.',
					[ 'run_id' => $run_id, 'total' => $run['total'], 'completed' => $run['completed'] ]
				);

				return $run;
			} finally {
				$this->release_start_fence( (string) $fence_owner );
			}
		}

	/**
	 * Acquire an atomic mutex for processing one item.
	 *
	 * Uses a direct INSERT IGNORE against the unique option_name index.
	 * WordPress add_option() cannot be used as a mutex because its duplicate-key
	 * update may report success to two concurrent contenders.
	 *
	 * @return bool|\WP_Error
	 */
	public function acquire_lease() {
		$run = $this->get_run();

		if ( null === $run ) {
			return new \WP_Error(
				'rms_wizard_landing_no_run',
				\__( 'No landing run is active. Start a run first.', 'simple-rms-theme' ),
				[ 'status' => 409 ]
			);
		}

		if ( self::RUN_COMPLETED === $run['status'] ) {
			return new \WP_Error(
				'rms_wizard_landing_run_complete',
				\__( 'The landing run is already complete.', 'simple-rms-theme' ),
				[ 'status' => 409 ]
			);
		}

		$option_name = $this->lease_option_name( $run['run_id'] );

		// Clean up an expired mutex from a previous dead worker using a
		// compare-and-delete, so a contender cannot delete a replacement lease.
		$existing = $this->read_lease_record( $option_name );

		if ( null !== $existing ) {
			$expires = is_array( $existing['value'] ) ? (int) ( $existing['value']['expires_at'] ?? 0 ) : 0;

			if ( $expires > time() || 0 === $expires ) {
				return new \WP_Error(
					'rms_wizard_landing_lease_active',
					\__( 'Another landing process request is already running. Wait for it to finish or expire.', 'simple-rms-theme' ),
					[ 'status' => 409 ]
				);
			}

			if ( ! $this->delete_lease_record( $option_name, $existing['raw'] ) ) {
				return new \WP_Error(
					'rms_wizard_landing_lease_active',
					\__( 'Another landing process request acquired the execution lease.', 'simple-rms-theme' ),
					[ 'status' => 409 ]
				);
			}
		}

		$budget = $this->configure_execution_budget();

		if ( \is_wp_error( $budget ) ) {
			return $budget;
		}

		$owner   = $this->mint_owner_token();
		$ttl     = $budget + self::LEASE_MARGIN;
		$now     = time();
		$payload = [
			'owner'        => $owner,
			'acquired_at'  => $now,
			'expires_at'   => $now + $ttl,
		];

		$acquired = $this->insert_lease_record( $option_name, $payload );

		if ( ! $acquired ) {
			// Race lost — another worker got the mutex.
			return new \WP_Error(
				'rms_wizard_landing_lease_active',
				\__( 'Another landing process request is already running. Wait for it to finish or expire.', 'simple-rms-theme' ),
				[ 'status' => 409 ]
			);
		}

		// Mirror safe metadata into run state (atomic option is authority).
		$run['lease_owner']     = $owner;
		$run['lease_expires_at'] = $now + $ttl;
		$run['updated_at']      = $now;
		$this->persist_run( $run );

		return $owner;
	}

	/**
	 * Release the mutex lease — only by the owner.
	 *
	 * @param string $owner The owner token from acquire_lease.
	 */
	public function release_lease( string $owner ): void {
		$run = $this->get_run();

		if ( null === $run ) {
			return;
		}

		$option_name = $this->lease_option_name( $run['run_id'] );
		$existing    = $this->read_lease_record( $option_name );

		if (
			null !== $existing
			&& is_array( $existing['value'] )
			&& (string) ( $existing['value']['owner'] ?? '' ) === $owner
			&& $this->delete_lease_record( $option_name, $existing['raw'] )
		) {
			unset( $run['lease_owner'], $run['lease_expires_at'] );
			$run['updated_at'] = time();
			$this->persist_run( $run );
		}
	}

	/**
	 * Acquire the site-wide start fence so check + plan persist are serialized.
	 *
	 * Uses the same INSERT IGNORE / exact-value CAS delete as the processing lease.
	 * TTL is finite; a crashed starter cannot block new starts forever.
	 *
	 * @return string|\WP_Error Owner token on success.
	 */
	public function acquire_start_fence() {
		$option_name = self::START_FENCE_OPTION;
		$existing    = $this->read_lease_record( $option_name );

		if ( null !== $existing ) {
			$expires = is_array( $existing['value'] ) ? (int) ( $existing['value']['expires_at'] ?? 0 ) : 0;

			if ( $expires > time() || 0 === $expires ) {
				return new \WP_Error(
					'rms_wizard_landing_start_fence_active',
					\__( 'Another landing start request is already initializing a run.', 'simple-rms-theme' ),
					[ 'status' => 409 ]
				);
			}

			if ( ! $this->delete_lease_record( $option_name, $existing['raw'] ) ) {
				return new \WP_Error(
					'rms_wizard_landing_start_fence_active',
					\__( 'Another landing start request acquired the initialization fence.', 'simple-rms-theme' ),
					[ 'status' => 409 ]
				);
			}
		}

		$owner   = $this->mint_owner_token();
		$now     = time();
		$payload = [
			'owner'       => $owner,
			'acquired_at' => $now,
			'expires_at'  => $now + self::START_FENCE_TTL,
		];

		if ( ! $this->insert_lease_record( $option_name, $payload ) ) {
			return new \WP_Error(
				'rms_wizard_landing_start_fence_active',
				\__( 'Another landing start request is already initializing a run.', 'simple-rms-theme' ),
				[ 'status' => 409 ]
			);
		}

		return $owner;
	}

	/**
	 * Release the start fence — only by the exact owner token.
	 */
	public function release_start_fence( string $owner ): void {
		if ( '' === $owner ) {
			return;
		}

		$option_name = self::START_FENCE_OPTION;
		$existing    = $this->read_lease_record( $option_name );

		if (
			null !== $existing
			&& is_array( $existing['value'] )
			&& (string) ( $existing['value']['owner'] ?? '' ) === $owner
		) {
			$this->delete_lease_record( $option_name, $existing['raw'] );
		}
	}

	/**
	 * Recover stale/expired lease: convert running items to interrupted.
	 *
	 * Called on state load / process entry to clean up after a dead process.
	 * Checks the atomic mutex option, not run state mirrors.
	 */
	public function recover_stale_lease(): void {
		$run = $this->get_run();

		if ( null === $run ) {
			return;
		}

		$now     = time();
		$changed  = false;
		$mutex_is_gone = false;
		$run_id   = (string) ( $run['run_id'] ?? '' );

		// Check the atomic mutex — only recover if it has expired.
		if ( '' !== $run_id ) {
			$option_name = $this->lease_option_name( $run_id );
			$lease       = $this->read_lease_record( $option_name );

			if ( null !== $lease && is_array( $lease['value'] ) ) {
				$expires = (int) ( $lease['value']['expires_at'] ?? 0 );

				if ( $expires > 0 && $expires <= $now && $this->delete_lease_record( $option_name, $lease['raw'] ) ) {
					unset( $run['lease_owner'], $run['lease_expires_at'] );
					$changed = true;
					$mutex_is_gone = true;
				}
			} elseif ( null === $lease ) {
				$mutex_is_gone = true;
				// No mutex option — clear stale mirror if present.
				if ( isset( $run['lease_owner'] ) || isset( $run['lease_expires_at'] ) ) {
					unset( $run['lease_owner'], $run['lease_expires_at'] );
					$changed = true;
				}
			}
		}

			// A live mutex means a worker is still authoritative. Only recover item
			// state after the mutex is proven absent or its exact expired value was removed.
			if ( $mutex_is_gone ) {
				$interrupted_running = false;

				foreach ( $run['items'] as &$item ) {
					if ( self::ITEM_RUNNING === $item['status'] ) {
						$item['status']        = self::ITEM_INTERRUPTED;
						$item['error_message'] = \__( 'The previous process request was interrupted.', 'simple-rms-theme' );
						$changed               = true;
						$interrupted_running   = true;
					}
				}
				unset( $item );

				if ( $interrupted_running && in_array( (string) ( $run['status'] ?? '' ), [ self::RUN_PENDING, self::RUN_RUNNING ], true ) ) {
					$run['status'] = self::RUN_INTERRUPTED;
					$changed       = true;
				}
			}

		// Update run status based on remaining items.
		if ( self::RUN_RUNNING === $run['status'] ) {
			$has_active = $this->has_active_items( $run['items'] );

			if ( ! $has_active && $run['completed'] !== $run['total'] ) {
				$run['status'] = self::RUN_INTERRUPTED;
				$changed       = true;
			}
		}

		if ( $changed ) {
			$run['updated_at'] = $now;
			$this->persist_run( $run );

			$this->logger->log(
				'warning',
				'Landing run stale lease recovered; running items marked interrupted.',
				[ 'run_id' => $run_id ]
			);
		}
	}

	/**
	 * Mark an item as running before work begins.
	 *
	 * @param string $item_key Landing key of the item.
	 *
	 * @return array<string,mixed>|\WP_Error The item to process, or WP_Error.
	 */
	public function mark_item_running( string $item_key ) {
		$run = $this->get_run();

		if ( null === $run ) {
			return new \WP_Error( 'rms_wizard_landing_no_run', \__( 'No landing run is active.', 'simple-rms-theme' ), [ 'status' => 409 ] );
		}

		$found = false;

		foreach ( $run['items'] as &$item ) {
			if ( $item['key'] === $item_key ) {
				if ( self::ITEM_COMPLETED === $item['status'] ) {
					return new \WP_Error(
						'rms_wizard_landing_item_completed',
						\__( 'This landing item is already completed.', 'simple-rms-theme' ),
						[ 'status' => 409 ]
					);
				}

				$item['status']     = self::ITEM_RUNNING;
				$item['started_at'] = time();
				$found              = true;
				break;
			}
		}
		unset( $item );

		if ( ! $found ) {
			return new \WP_Error( 'rms_wizard_landing_item_not_found', \__( 'The landing item was not found in the run plan.', 'simple-rms-theme' ), [ 'status' => 404 ] );
		}

		$run['status']        = self::RUN_RUNNING;
		$run['current_index']  = $this->index_of( $run['items'], $item_key );
		$run['updated_at']     = time();

		if ( ! $this->persist_run( $run ) ) {
			return new \WP_Error( 'rms_wizard_landing_run_persist_failed', \__( 'Could not persist the running item status.', 'simple-rms-theme' ), [ 'status' => 500 ] );
		}

		return $run['items'][ $run['current_index'] ];
	}

	/**
	 * Checkpoint a completed item: persist entry to state.landing_pages and mark item complete.
	 *
	 * Atomically saves run + landing_pages in one save_state() call.
	 *
	 * @param string                 $item_key      Landing key.
	 * @param array<string,mixed>    $entry         Landing page entry.
	 * @param array<string,array<string,mixed>> $landing_pages Current state.landing_pages keyed by landing_key.
	 *
	 * @return array<string,mixed>|\WP_Error Updated landing_pages (keyed), or WP_Error.
	 */
	public function checkpoint_item( string $item_key, array $entry, array $landing_pages ) {
		$run = $this->get_run();

		if ( null === $run ) {
			return new \WP_Error( 'rms_wizard_landing_no_run', \__( 'No landing run is active.', 'simple-rms-theme' ), [ 'status' => 409 ] );
		}

		$now = time();

		foreach ( $run['items'] as &$item ) {
			if ( $item['key'] === $item_key ) {
				$item['status']       = self::ITEM_COMPLETED;
				$item['post_id']       = (int) ( $entry['id'] ?? 0 );
				$item['completed_at']  = $now;
				$item['error_code']    = '';
				$item['error_message'] = '';
				break;
			}
		}
		unset( $item );

		$run['completed']    = count( array_filter( $run['items'], static fn( $i ): bool => self::ITEM_COMPLETED === $i['status'] ) );
		$run['current_index'] = $this->first_pending_index( $run['items'] );

		if ( ! $this->has_active_items( $run['items'] ) && $run['completed'] === $run['total'] ) {
			$run['status'] = self::RUN_COMPLETED;
		}

		$run['updated_at'] = $now;

		// Persist run plan + landing_pages atomically in one save.
		$state                     = $this->state_manager->get_state();
		$landing_pages[ $item_key ] = $entry;
		$state['landing_pages']    = array_values( $landing_pages );
		$state[ self::STATE_KEY ]  = $run;

		$saved = $this->state_manager->save_state( $state );

		if ( ! $saved ) {
			// Verify by re-reading and comparing normalized content.
			$reloaded = $this->state_manager->get_state();
			$actual_run = is_array( $reloaded[ self::STATE_KEY ] ?? null ) ? $reloaded[ self::STATE_KEY ] : [];
			$actual_pages = is_array( $reloaded['landing_pages'] ?? null ) ? $reloaded['landing_pages'] : [];

			if ( ! $this->checkpoint_matches( $run, $actual_run, $state['landing_pages'], $actual_pages ) ) {
				return new \WP_Error( 'rms_wizard_landing_checkpoint_failed', \__( 'The landing checkpoint could not be persisted.', 'simple-rms-theme' ), [ 'status' => 500 ] );
			}
		}

		return $landing_pages;
	}

	/**
	 * Mark an item as failed or interrupted (distinguished by $status).
	 *
	 * @param string $item_key      Landing key.
	 * @param string $status        interrupted | failed.
	 * @param string $error_code    Error code.
	 * @param string $error_message Error message.
	 *
	 * @return bool
	 */
	public function mark_item_error( string $item_key, string $status, string $error_code, string $error_message ): bool {
		$run = $this->get_run();

		if ( null === $run ) {
			return false;
		}

		$valid_status = in_array( $status, [ self::ITEM_INTERRUPTED, self::ITEM_FAILED ], true ) ? $status : self::ITEM_FAILED;

		foreach ( $run['items'] as &$item ) {
			if ( $item['key'] === $item_key ) {
				$item['status']       = $valid_status;
				$item['error_code']    = $error_code;
				$item['error_message'] = $error_message;
				break;
			}
		}
		unset( $item );

		// Run status: failed if any item failed, interrupted otherwise.
		$run['status']     = self::ITEM_FAILED === $valid_status ? self::RUN_FAILED : self::RUN_INTERRUPTED;
		$run['updated_at'] = time();

		return $this->persist_run( $run );
	}

	/**
	 * Transition failed items back to interrupted so Resume retries the same keys.
	 *
	 * Does not rebuild the persisted plan. Error attribution stays on the item
	 * until a later successful checkpoint.
	 */
	public function rearm_failed_items_for_resume(): bool {
		$run = $this->get_run();

		if ( null === $run ) {
			return false;
		}

		$changed = false;

		foreach ( $run['items'] as &$item ) {
			if ( self::ITEM_FAILED === ( $item['status'] ?? '' ) ) {
				$item['status'] = self::ITEM_INTERRUPTED;
				$changed        = true;
			}
		}
		unset( $item );

		if ( ! $changed ) {
			return true;
		}

		if ( in_array( (string) ( $run['status'] ?? '' ), [ self::RUN_FAILED, self::RUN_COMPLETED ], true ) ) {
			$run['status'] = self::RUN_INTERRUPTED;
		}

		$run['updated_at'] = time();

		return $this->persist_run( $run );
	}

	/**
	 * Get the first pending or interrupted item to process.
	 *
	 * @return array<string,mixed>|null
	 */
	public function get_next_item(): ?array {
		$run = $this->get_run();

		if ( null === $run ) {
			return null;
		}

		foreach ( $run['items'] as $item ) {
			if ( in_array( $item['status'], self::PROCESSABLE_STATUSES, true ) ) {
				return $item;
			}
		}

		return null;
	}

	/**
	 * Determine whether the run is truly complete: all items completed, no active items.
	 */
	public function is_run_complete(): bool {
		$run = $this->get_run();

		if ( null === $run ) {
			return false;
		}

		if ( self::RUN_COMPLETED !== $run['status'] ) {
			return false;
		}

		// Defense in depth: verify no active items remain.
		return ! $this->has_active_items( $run['items'] );
	}

	/**
	 * Determine whether any items are still pending/running/interrupted/failed.
	 *
	 * @param array<int,array<string,mixed>> $items
	 */
	private function has_active_items( array $items ): bool {
		foreach ( $items as $item ) {
			if ( in_array( $item['status'], self::ACTIVE_STATUSES, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get the index of the first pending/interrupted item, or -1.
	 *
	 * @param array<int,array<string,mixed>> $items
	 */
	private function first_pending_index( array $items ): int {
		foreach ( $items as $index => $item ) {
			if ( in_array( $item['status'], self::PROCESSABLE_STATUSES, true ) ) {
				return $index;
			}
		}

		return -1;
	}

	/**
	 * Get the index of an item by key.
	 *
	 * @param array<int,array<string,mixed>> $items
	 */
	private function index_of( array $items, string $key ): int {
		foreach ( $items as $index => $item ) {
			if ( $item['key'] === $key ) {
				return $index;
			}
		}

		return -1;
	}

	/**
	 * Compare a submitted row against an existing state entry.
	 *
	 * Returns true when identity/type/title/slug/keyword/sections/menu inputs are unchanged.
	 * Entries without sections (e.g. manually recovered) compare unchanged when
	 * identity/type/title/slug/keywords match and the row has default sections.
	 *
	 * @param array<string,mixed> $row    Submitted row.
	 * @param array<string,mixed> $existing Existing state entry.
	 */
	private function landing_unchanged( array $row, array $existing ): bool {
		$fields = [ 'title', 'slug', 'landing_type', 'primary_keyword' ];

		foreach ( $fields as $field ) {
			if ( (string) ( $row[ $field ] ?? '' ) !== (string) ( $existing[ $field ] ?? '' ) ) {
				return false;
			}
		}

		// Compare subkeywords (order-insensitive bag).
		$row_sub      = is_array( $row['subkeywords'] ?? null ) ? array_values( $row['subkeywords'] ) : [];
		$existing_sub = is_array( $existing['subkeywords'] ?? null ) ? array_values( $existing['subkeywords'] ) : [];

		sort( $row_sub, SORT_STRING );
		sort( $existing_sub, SORT_STRING );

		if ( $row_sub !== $existing_sub ) {
			return false;
		}

		// Compare menu_eligible.
		$row_menu    = ! empty( $row['menu_eligible'] );
		$existing_menu = ! empty( $existing['menu_eligible'] );

		if ( $row_menu !== $existing_menu ) {
			return false;
		}

		// Compare normalized sections when both sides have them.
		// Recovered entries may lack sections — treat as unchanged when absent.
		$row_sections      = is_array( $row['sections'] ?? null ) ? $row['sections'] : [];
		$existing_sections = is_array( $existing['sections'] ?? null ) ? $existing['sections'] : null;

		if ( null !== $existing_sections && [] !== $existing_sections ) {
			$row_norm      = $this->normalize_sections_for_compare( $row_sections );
			$existing_norm = $this->normalize_sections_for_compare( $existing_sections );

			if ( $row_norm !== $existing_norm ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Normalize section rows for order-sensitive comparison.
	 *
	 * @param array<int,array<string,mixed>> $sections
	 *
	 * @return array<int,array{layout:string,item_count:int,override_canonical:bool}>
	 */
	private function normalize_sections_for_compare( array $sections ): array {
		$norm = [];

		foreach ( $sections as $section ) {
			if ( ! is_array( $section ) ) {
				continue;
			}

			$norm[] = [
				'layout'             => (string) ( $section['layout'] ?? '' ),
				'item_count'         => (int) ( $section['item_count'] ?? 0 ),
				'override_canonical' => ! empty( $section['override_canonical'] ),
			];
		}

		return $norm;
	}

	/**
	 * Check whether a row uses any layout in the replace_map (per-item invalidation).
	 *
	 * @param array<string,mixed> $row
	 * @param array<string,bool>  $replace_map
	 */
	private function item_has_replace( array $row, array $replace_map ): bool {
		if ( empty( $replace_map ) ) {
			return false;
		}

		$sections = is_array( $row['sections'] ?? null ) ? $row['sections'] : [];

		foreach ( $sections as $section ) {
			if ( ! is_array( $section ) ) {
				continue;
			}

			$layout = (string) ( $section['layout'] ?? '' );

			if ( '' !== $layout && ! empty( $replace_map[ $layout ] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Normalize a persisted run for backward compatibility.
	 * Validates items shape; logs and discards unsupported schemas.
	 */
	private function normalize_run( array $run ): ?array {
		$version = (int) ( $run['schema_version'] ?? 0 );

		if ( $version > self::SCHEMA_VERSION ) {
			$this->logger->log(
				'error',
				'Landing run schema version is newer than supported; run discarded.',
				[ 'schema_version' => $version, 'max_supported' => self::SCHEMA_VERSION ]
			);

			return null;
		}

		$defaults = [
			'run_id'          => '',
			'schema_version'  => self::SCHEMA_VERSION,
			'status'          => self::RUN_PENDING,
			'items'           => [],
			'total'           => 0,
			'completed'       => 0,
			'current_index'   => -1,
			'replace_map'     => [],
			'created_at'      => 0,
			'updated_at'      => 0,
		];

		$normalized = array_replace_recursive( $defaults, $run );

		// Validate items shape.
		if ( ! is_array( $normalized['items'] ) ) {
			$this->logger->log( 'error', 'Landing run items is not an array; run discarded.', [ 'run_id' => $normalized['run_id'] ] );
			return null;
		}

		$valid_items = [];
		foreach ( $normalized['items'] as $index => $item ) {
			if ( ! is_array( $item ) || ! isset( $item['key'] ) || '' === (string) $item['key'] ) {
				$this->logger->log( 'error', 'Landing run item has no key; item skipped.', [ 'run_id' => $normalized['run_id'], 'index' => $index ] );
				continue;
			}

			$valid_items[] = $item;
		}

		$normalized['items'] = $valid_items;
		$normalized['total'] = count( $valid_items );
		$normalized['completed'] = count(
			array_filter(
				$valid_items,
				static fn( array $item ): bool => self::ITEM_COMPLETED === ( $item['status'] ?? '' )
			)
		);

		return $normalized;
	}

	/**
	 * Persist the run plan into wizard state.
	 */
	private function persist_run( array $run ): bool {
		$state                 = $this->state_manager->get_state();
		$state[ self::STATE_KEY ] = $run;

		return $this->state_manager->save_state( $state );
	}

	/**
	 * Enforce a finite execution budget for one landing request.
	 *
	 * A finite environment limit is never increased. Unlimited environments are
	 * reduced to the bounded item budget so the lease can safely outlive the worker.
	 *
	 * @return int|\WP_Error Effective execution budget in seconds.
	 */
	private function configure_execution_budget() {
		$max_exec = 0;

		if ( function_exists( 'ini_get' ) ) {
			$raw = \ini_get( 'max_execution_time' );
			$max_exec = false !== $raw ? (int) $raw : 0;
		}

		$budget = $max_exec > 0
			? min( $max_exec, self::ITEM_EXECUTION_BUDGET )
			: self::ITEM_EXECUTION_BUDGET;

		if ( function_exists( 'set_time_limit' ) ) {
			$result = @\set_time_limit( $budget );

			if ( false === $result && $max_exec <= 0 ) {
				return new \WP_Error(
					'rms_wizard_landing_execution_budget_unavailable',
					\__( 'Landing processing requires a finite PHP execution limit to guarantee safe recovery.', 'simple-rms-theme' ),
					[ 'status' => 500 ]
				);
			}
		} elseif ( $max_exec <= 0 ) {
			return new \WP_Error(
				'rms_wizard_landing_execution_budget_unavailable',
				\__( 'Landing processing requires a finite PHP execution limit to guarantee safe recovery.', 'simple-rms-theme' ),
				[ 'status' => 500 ]
			);
		}

		return $budget;
	}

	/**
	 * Read the exact serialized lease row from wp_options, bypassing option cache.
	 *
	 * @return array{raw:string,value:mixed}|null
	 */
	private function read_lease_record( string $option_name ): ?array {
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
	 * Atomically insert a lease. The unique option_name index admits one owner.
	 */
	private function insert_lease_record( string $option_name, array $payload ): bool {
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
	 * Delete only the exact lease value previously read (compare-and-delete).
	 */
	private function delete_lease_record( string $option_name, string $raw_value ): bool {
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

	/**
	 * Generate a stable run identifier.
	 */
	private function mint_run_id(): string {
		if ( function_exists( 'wp_generate_uuid4' ) ) {
			return 'run_' . str_replace( '-', '', \wp_generate_uuid4() );
		}

		return 'run_' . \sanitize_key( uniqid( '', true ) );
	}

	/**
	 * Generate a unique owner token for the lease.
	 */
	private function mint_owner_token(): string {
		if ( function_exists( 'wp_generate_uuid4' ) ) {
			return \wp_generate_uuid4();
		}

		return uniqid( 'owner_', true );
	}

	/**
	 * Build the wp_options name for a run's atomic mutex.
	 */
	private function lease_option_name( string $run_id ): string {
		return 'rms_landing_lease_' . \sanitize_key( $run_id );
	}

	/**
	 * Verify that a checkpoint was persisted correctly by comparing
	 * normalized content and keys, not just count.
	 *
	 * @param array<string,mixed> $expected_run
	 * @param array<string,mixed> $actual_run
	 * @param array<int,array<string,mixed>> $expected_pages
	 * @param array<int,array<string,mixed>> $actual_pages
	 */
	private function checkpoint_matches( array $expected_run, array $actual_run, array $expected_pages, array $actual_pages ): bool {
		// Compare run item statuses (the critical state that changed).
		$expected_statuses = [];
		foreach ( is_array( $expected_run['items'] ?? [] ) as $item ) {
			if ( is_array( $item ) && isset( $item['key'] ) ) {
				$expected_statuses[ $item['key'] ] = $item['status'] ?? '';
			}
		}

		$actual_statuses = [];
		foreach ( is_array( $actual_run['items'] ?? [] ) as $item ) {
			if ( is_array( $item ) && isset( $item['key'] ) ) {
				$actual_statuses[ $item['key'] ] = $item['status'] ?? '';
			}
		}

		if ( $expected_statuses !== $actual_statuses ) {
			return false;
		}

		// Compare landing page keys.
		$expected_keys = [];
		foreach ( $expected_pages as $page ) {
			if ( is_array( $page ) && isset( $page['landing_key'] ) ) {
				$expected_keys[ $page['landing_key'] ] = (int) ( $page['id'] ?? 0 );
			}
		}

		$actual_keys = [];
		foreach ( $actual_pages as $page ) {
			if ( is_array( $page ) && isset( $page['landing_key'] ) ) {
				$actual_keys[ $page['landing_key'] ] = (int) ( $page['id'] ?? 0 );
			}
		}

		return $expected_keys === $actual_keys;
	}
}
