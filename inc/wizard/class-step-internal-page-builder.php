<?php
/**
 * Wizard internal page builder — About plus remaining ready types.
 *
 * @package Simple_RMS_Theme
 */

namespace Inc\Wizard;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-section-assembler.php';
require_once __DIR__ . '/class-placeholder-provenance-store.php';
require_once __DIR__ . '/class-internal-page-identity.php';

/** Start/process under execute_step. Does not acquire the fence. */
class Step_Internal_Page_Builder {
	private const STEP        = 'internal-page-builder';
	private const READY_TYPES = Internal_Page_Identity::READY_TYPES;
	private $logger;
	private $state_manager;
	private $content_builder;
	private $harness;
	private $canonical_store;
		private $section_assembler;
		private $provenance;
		private $mutation_owner = '';

	public function __construct(
		?Logger $logger = null,
		?State_Manager $state_manager = null,
		?Content_Builder $content_builder = null,
		?AI_Content_Harness $harness = null,
		?Canonical_Section_Store $canonical_store = null,
		?Section_Assembler $section_assembler = null,
		?Placeholder_Provenance_Store $provenance = null
	) {
		$this->logger            = $logger ?? new Logger();
		$this->state_manager     = $state_manager ?? new State_Manager();
		$this->content_builder   = $content_builder ?? new Content_Builder( $this->logger, $this->state_manager );
		$this->harness           = $harness ?? new AI_Content_Harness();
		$this->canonical_store   = $canonical_store ?? new Canonical_Section_Store();
		$this->section_assembler = $section_assembler ?? new Section_Assembler( $this->harness );
		$this->provenance        = $provenance ?? new Placeholder_Provenance_Store();
	}

	/**
	 * Remember the live fence owner for this instance. Authorization still
	 * requires Wizard_Mutation_Fence::authorize_agent() for this object.
	 */
	public function accept_mutation_owner( string $owner ): void {
		$this->mutation_owner = $owner;
	}

	/**
	 * Read-only preview/resume plan. GET and state hydration must not write.
	 *
	 * @param array<string,mixed> $state
	 * @return array{types:array<string,array<string,mixed>>,unmapped:array<int,array<string,mixed>>}
	 */
	public function preview_plan( array $state ): array {
		return Internal_Page_Identity::preview_plan( $state );
	}

	/** @param array<string,mixed> $payload @return array<string,mixed>|\WP_Error */
	public function run( array $payload ) {
		$fence = new Wizard_Mutation_Fence();
		if ( ! $fence->agent_is_authorized( $this, $this->mutation_owner ) ) {
			return new \WP_Error(
				'rms_wizard_mutation_unfenced',
				\__( 'Internal page builder mutations must run under the wizard mutation fence.', 'simple-rms-theme' ),
				[ 'status' => 409 ]
			);
		}

		if ( $this->truthy( $payload['skip_all'] ?? false ) ) {
			return $this->run_skip_all();
		}

		$action = \sanitize_key( (string) ( $payload['action'] ?? '' ) );

		if ( 'start' === $action ) {
			return $this->start( $payload );
		}

		if ( 'process' === $action ) {
			return $this->process( $payload );
		}

		return new \WP_Error(
			'rms_wizard_internal_action_required',
			\__( 'An action (start or process) is required.', 'simple-rms-theme' ),
			[ 'status' => 400 ]
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	private function run_skip_all(): array {
		$fresh = $this->state_manager->get_state();
		$plan  = is_array( $fresh['internal_pages'] ?? null ) ? $fresh['internal_pages'] : [];

		foreach ( $plan as $type => $entry ) {
			if ( ! is_array( $entry ) || in_array( (string) ( $entry['status'] ?? '' ), [ 'complete', 'skipped' ], true ) ) {
				continue;
			}
			$entry['status']     = 'skipped';
			$entry['reason']     = 'skip_all';
			$entry['updated_at'] = \current_time( 'mysql', true );
			$plan[ $type ]       = $entry;
		}

		$fresh['internal_pages'] = $plan;
		$this->state_manager->save_state( $fresh );
		$this->state_manager->set_step_status( self::STEP, 'complete' );

		return [ 'skipped' => true, 'internal_pages' => $plan, 'canonical_wrote' => false ];
	}

	/**
	 * @param array<string,mixed> $payload
	 * @return array<string,mixed>|\WP_Error
	 */
	private function start( array $payload ) {
		$fresh = $this->apply_identity_writes( $this->state_manager->get_state(), $payload );
		if ( \is_wp_error( $fresh ) ) {
			return $fresh;
		}
		$plan  = $this->ensure_plan( $fresh, $this->truthy( $payload['retry_failed'] ?? false ) );
		$fresh['internal_pages'] = $plan;
		$this->state_manager->save_state( $fresh );
		$status = $this->is_pending( $plan ) || null !== $this->next_actionable_type( $plan, $payload )
			? 'running'
			: $this->step_status_from_plan( $plan );
		$this->state_manager->set_step_status( self::STEP, $status );

		return [ 'internal_pages' => $plan, 'status' => $status ];
	}

	/**
	 * @param array<string,mixed> $payload
	 * @return array<string,mixed>|\WP_Error
	 */
	private function process( array $payload ) {
		$fresh = $this->apply_identity_writes( $this->state_manager->get_state(), $payload );
		if ( \is_wp_error( $fresh ) ) {
			return $fresh;
		}
		$plan  = $this->ensure_plan( $fresh, $this->truthy( $payload['retry_failed'] ?? false ) );
		$type  = $this->next_actionable_type( $plan, $payload );
		$mismatch = $this->identity_mismatch( $payload, $type, $plan, is_array( $fresh['generated_pages'] ?? null ) ? $fresh['generated_pages'] : [] );
		if ( $mismatch ) {
			return $mismatch;
		}

		if ( null === $type ) {
			$fresh['internal_pages'] = $plan;
			$this->state_manager->save_state( $fresh );
			$step_status = $this->step_status_from_plan( $plan );
			$this->state_manager->set_step_status( self::STEP, $step_status );

			foreach ( self::READY_TYPES as $ready ) {
				if ( 'complete' === (string) ( ( is_array( $plan[ $ready ] ?? null ) ? $plan[ $ready ] : [] )['status'] ?? '' ) ) {
					return [ 'internal_pages' => $plan, 'status' => $step_status ];
				}
			}

			return [
				'internal_pages' => $plan,
				'status'         => $step_status,
				'unavailable'    => 'complete' === $step_status,
				'reason'         => 'complete' === $step_status ? 'unavailable' : '',
			];
		}

		$entry         = is_array( $plan[ $type ] ?? null ) ? $plan[ $type ] : State_Manager::INTERNAL_PAGE_ENTRY;
		$entry         = $this->process_page(
			$type,
			$entry,
			$this->type_requested( $payload['overwrite'] ?? [], $type ),
			$this->type_requested( $payload['convert_legacy'] ?? [], $type )
		);
		$plan[ $type ] = $entry;
		$fresh                   = $this->state_manager->get_state();
		$fresh['internal_pages'] = $plan;
		$this->state_manager->save_state( $fresh );
		$this->state_manager->set_step_status( self::STEP, $this->step_status_from_plan( $plan ) );

		return [
			'internal_pages' => $plan,
			'processed'      => $type,
			'status'         => (string) ( $entry['status'] ?? '' ),
			'reason'         => (string) ( $entry['reason'] ?? '' ),
		];
	}

	/**
	 * @param array<string,mixed> $state
	 * @return array<string,array<string,mixed>>
	 */
	private function ensure_plan( array $state, bool $retry_failed = false ): array {
		$plan  = is_array( $state['internal_pages'] ?? null ) ? $state['internal_pages'] : [];
		$pages = is_array( $state['generated_pages'] ?? null ) ? $state['generated_pages'] : [];

		foreach ( self::READY_TYPES as $type ) {
			$shell = Internal_Page_Identity::find_shell( $type, $pages, $plan );
			$entry = is_array( $plan[ $type ] ?? null )
				? array_merge( State_Manager::INTERNAL_PAGE_ENTRY, $plan[ $type ] )
				: State_Manager::INTERNAL_PAGE_ENTRY;

			if ( null === $shell ) {
				$entry['post_id']    = 0;
				$entry['status']     = 'skipped';
				$entry['reason']     = 'unavailable';
				$entry['updated_at'] = \current_time( 'mysql', true );
				$plan[ $type ]       = $entry;
				continue;
			}

			$entry['post_id'] = (int) $shell['id'];
			if ( $retry_failed && 'failed' === (string) ( $entry['status'] ?? '' ) ) {
				$entry['status'] = 'pending';
				$entry['reason'] = '';
			}
			if ( '' === (string) ( $entry['status'] ?? '' ) ) {
				$entry['status'] = 'pending';
			}
			$plan[ $type ] = $entry;
		}

		return $plan;
	}

	/**
	 * Persist resolved types and explicit maps. Never called from GET/preview.
	 *
	 * @param array<string,mixed> $state
	 * @param array<string,mixed> $payload
	 * @return array<string,mixed>|\WP_Error
	 */
	private function apply_identity_writes( array $state, array $payload ) {
		$pages = is_array( $state['generated_pages'] ?? null ) ? $state['generated_pages'] : [];
		$plan  = is_array( $state['internal_pages'] ?? null ) ? $state['internal_pages'] : [];
		$map   = is_array( $payload['map_pages'] ?? null ) ? $payload['map_pages'] : [];
		if ( [] !== $map ) {
			$pages = Internal_Page_Identity::apply_map( $pages, $map, $plan, $payload );
			if ( \is_wp_error( $pages ) ) {
				return $pages;
			}
		}
		$resolved = [];
		foreach ( self::READY_TYPES as $type ) {
			$shell = Internal_Page_Identity::find_shell( $type, $pages, $plan );
			if ( ! $shell ) {
				continue;
			}
			if ( in_array( (string) $shell['source'], [ 'legacy_slug', 'template', 'role' ], true ) ) {
				$resolved[ (int) $shell['id'] ] = $type;
			}
		}
		$state['generated_pages'] = Internal_Page_Identity::persist_types( $pages, $resolved, $plan );

		return $state;
	}

	/**
	 * @param array<string,mixed> $entry
	 * @return array<string,mixed>
	 */
	private function process_page( string $type, array $entry, bool $overwrite = false, bool $convert = false ): array {
		$post_id = \absint( $entry['post_id'] ?? 0 );
		$now     = \current_time( 'mysql', true );

		if ( $post_id <= 0 ) {
			$entry['status']     = 'skipped';
			$entry['reason']     = 'unavailable';
			$entry['updated_at'] = $now;

			return $entry;
		}

		if ( 'complete' === (string) ( $entry['status'] ?? '' ) && ! $overwrite ) {
			$entry['reason']     = 'unchanged';
			$entry['updated_at'] = $now;

			return $entry;
		}

		$sections = $this->current_sections( $post_id );
		$post     = \get_post( $post_id );
		$content  = $post ? (string) $post->post_content : '';

		if ( [] !== $sections && ! $overwrite ) {
			$entry['status']     = 'complete';
			$entry['reason']     = 'preserved';
			$entry['updated_at'] = $now;

			return $entry;
		}

		if ( [] === $sections && '' !== trim( $content ) && ! $convert ) {
			$entry['status']     = 'skipped';
			$entry['reason']     = 'legacy_unconfirmed';
			$entry['updated_at'] = $now;

			return $entry;
		}

		$all       = Internal_Page_Blueprints::all();
		$blueprint = is_array( $all[ $type ] ?? null ) ? $all[ $type ] : null;
		if ( ! is_array( $blueprint ) ) {
			$entry['status']     = 'skipped';
			$entry['reason']     = 'unavailable';
			$entry['updated_at'] = $now;

			return $entry;
		}

		$built = $this->assemble_rows( $blueprint, $post_id );
		$saved = $this->content_builder->build_page(
			[
				'id'           => $post_id,
				'section_only' => true,
				'sections'     => $built['rows'],
				'meta_input'   => [ '_wp_page_template' => (string) $blueprint['template'] ],
			]
		);

		if ( $saved <= 0 ) {
			$entry['status']     = 'failed';
			$entry['reason']     = 'persist_failed';
			$entry['updated_at'] = $now;

			return $entry;
		}

		$entry['status']     = 'complete';
		$entry['reason']     = '';
		$entry['layouts']    = $built['layouts'];
		$entry['post_id']    = $post_id;
		$entry['updated_at'] = $now;

		return $entry;
	}

	/**
	 * @param array{template:string,layouts:array<int,string>,page_type:string,canonical:string} $blueprint
	 * @return array{rows:array<int,array<string,mixed>>,layouts:array<int,string>}
	 */
	private function assemble_rows( array $blueprint, int $post_id ): array {
		$client  = is_array( $this->state_manager->get_state()['client_data'] ?? null ) ? $this->state_manager->get_state()['client_data'] : [];
		$rows    = [];
		$layouts = [];

		foreach ( is_array( $blueprint['layouts'] ?? null ) ? $blueprint['layouts'] : [] as $index => $layout ) {
			$layout = \sanitize_key( (string) $layout );
			if ( '' === $layout ) {
				continue;
			}
			$layouts[] = $layout;
			if ( 'copy' === (string) ( $blueprint['canonical'] ?? '' ) && $this->canonical_store->has( $layout ) ) {
				$payload = $this->canonical_store->get( $layout );
				if ( [] !== $payload ) {
					$payload['acf_fc_layout'] = $layout;
					$rows[]                   = $this->content_builder->prepare_image_fallbacks( $payload );
					continue;
				}
			}
			$count        = $this->harness->has_fillable_fields( $layout ) ? ( 'vision-mission-v2' === $layout ? 3 : 1 ) : 0;
			$placeholders = $this->section_assembler->placeholder_fields( $layout, $client, $count );
			$row          = $this->content_builder->prepare_image_fallbacks( $this->section_assembler->section_data( $layout, $client, [], $count ) );
			$rows[]       = $row;
			foreach ( $placeholders as $field => $_unused ) {
				$field = (string) $field;
				if ( isset( $row[ $field ] ) ) {
					$this->provenance->record( $post_id, $layout, (int) $index, $field, 'missing_client_fact', $row[ $field ] );
				}
			}
		}

		return [ 'rows' => $rows, 'layouts' => $layouts ];
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	private function current_sections( int $post_id ): array {
		$rows = function_exists( 'get_field' ) ? \get_field( 'page_sections', $post_id ) : \get_post_meta( $post_id, 'page_sections', true );

		return is_array( $rows ) ? $rows : [];
	}

	/**
	 * @param array<string,array<string,mixed>> $plan
	 */
	private function is_pending( array $plan ): bool {
		return null !== $this->next_pending_type( $plan );
	}

	/**
	 * @param array<string,array<string,mixed>> $plan
	 */
	private function has_failed( array $plan ): bool {
		foreach ( self::READY_TYPES as $type ) {
			$status = (string) ( ( is_array( $plan[ $type ] ?? null ) ? $plan[ $type ] : [] )['status'] ?? '' );
			if ( 'failed' === $status ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Step is complete only when nothing is pending or failed.
	 *
	 * @param array<string,array<string,mixed>> $plan
	 */
	private function step_status_from_plan( array $plan ): string {
		if ( $this->is_pending( $plan ) ) {
			return 'running';
		}

		if ( $this->has_failed( $plan ) ) {
			return 'failed';
		}

		return 'complete';
	}

	/**
	 * @param array<string,array<string,mixed>> $plan
	 */
	private function next_pending_type( array $plan ): ?string {
		foreach ( self::READY_TYPES as $type ) {
			$status = (string) ( ( is_array( $plan[ $type ] ?? null ) ? $plan[ $type ] : [] )['status'] ?? '' );
			if ( 'pending' === $status ) {
				return $type;
			}
		}

		return null;
	}

	/**
	 * Reject forged page_type or post_id. Optional slug is a live post_name hint only.
	 *
	 * @param array<string,mixed>               $payload
	 * @param array<string,array<string,mixed>> $plan
	 * @param array<int,array<string,mixed>>    $generated_pages
	 * @return \WP_Error|null
	 */
	private function identity_mismatch( array $payload, ?string $type, array $plan, array $generated_pages ) {
		unset( $generated_pages );
		$declared_type = \sanitize_key( (string) ( $payload['page_type'] ?? '' ) );
		if ( '' !== $declared_type && $declared_type !== (string) $type ) {
			return $this->identity_error();
		}
		if ( null === $type ) {
			return null;
		}
		$entry = is_array( $plan[ $type ] ?? null ) ? $plan[ $type ] : [];
		if ( array_key_exists( 'post_id', $payload ) && \absint( $payload['post_id'] ) !== \absint( $entry['post_id'] ?? 0 ) ) {
			return $this->identity_error();
		}
		if ( array_key_exists( 'slug', $payload ) ) {
			$supplied = \sanitize_title( (string) $payload['slug'] );
			if ( '' !== $supplied ) {
				$post = \get_post( \absint( $entry['post_id'] ?? 0 ) );
				$live = $post ? \sanitize_title( (string) ( $post->post_name ?? '' ) ) : '';
				if ( $supplied !== $live ) {
					return $this->identity_error();
				}
			}
		}

		return null;
	}

	/**
	 * @return \WP_Error
	 */
	private function identity_error() {
		return new \WP_Error(
			'rms_wizard_internal_identity',
			\__( 'That internal page action does not match the generated page identity.', 'simple-rms-theme' ),
			[ 'status' => 400 ]
		);
	}

	/**
	 * @param array<string,array<string,mixed>> $plan
	 * @param array<string,mixed>               $payload
	 */
	private function next_actionable_type( array $plan, array $payload ): ?string {
		$pending = $this->next_pending_type( $plan );
		if ( null !== $pending ) {
			return $pending;
		}

		foreach ( self::READY_TYPES as $type ) {
			$entry  = is_array( $plan[ $type ] ?? null ) ? $plan[ $type ] : [];
			$status = (string) ( $entry['status'] ?? '' );
			$reason = (string) ( $entry['reason'] ?? '' );
			if ( 'complete' === $status && $this->type_requested( $payload['overwrite'] ?? [], $type ) ) {
				return $type;
			}
			if ( 'skipped' === $status && 'legacy_unconfirmed' === $reason && $this->type_requested( $payload['convert_legacy'] ?? [], $type ) ) {
				return $type;
			}
		}

		return null;
	}

	/**
	 * @param mixed $list
	 */
	private function type_requested( $list, string $type ): bool {
		if ( ! is_array( $list ) ) {
			return false;
		}
		foreach ( $list as $item ) {
			if ( $type === \sanitize_key( (string) $item ) ) {
				return true;
			}
		}

		return false;
	}

	private function truthy( $value ): bool {
		return true === $value || 1 === $value || '1' === $value || 'true' === $value || 'yes' === $value || 'on' === $value;
	}
}
