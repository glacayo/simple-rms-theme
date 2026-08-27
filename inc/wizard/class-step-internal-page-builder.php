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

/** Start/process under execute_step. Does not acquire the fence. */
class Step_Internal_Page_Builder {
	private const STEP        = 'internal-page-builder';
	private const READY_TYPES = [ 'about', 'services', 'contact', 'projects', 'testimonials' ];
	private const LEGACY_SLUGS = [
		'about' => [ 'about', 'about-us' ],
	];
	private $logger;
	private $state_manager;
	private $content_builder;
	private $harness;
	private $canonical_store;
	private $section_assembler;
	private $provenance;

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

	/** @param array<string,mixed> $payload @return array<string,mixed>|\WP_Error */
	public function run( array $payload ) {
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
	 * @return array<string,mixed>
	 */
	private function start( array $payload ): array {
		$fresh = $this->state_manager->get_state();
		$plan  = $this->ensure_plan( $fresh, $this->truthy( $payload['retry_failed'] ?? false ) );
		$fresh['internal_pages'] = $plan;
		$this->state_manager->save_state( $fresh );
		$pending = $this->is_pending( $plan );
		$this->state_manager->set_step_status( self::STEP, $pending ? 'running' : 'complete' );

		return [ 'internal_pages' => $plan, 'status' => $pending ? 'running' : 'complete' ];
	}

	/**
	 * @param array<string,mixed> $payload
	 * @return array<string,mixed>
	 */
	private function process( array $payload ): array {
		$fresh = $this->state_manager->get_state();
		$plan  = $this->ensure_plan( $fresh, $this->truthy( $payload['retry_failed'] ?? false ) );
		$type  = $this->next_actionable_type( $plan, $payload );

		if ( null === $type ) {
			$fresh['internal_pages'] = $plan;
			$this->state_manager->save_state( $fresh );
			$this->state_manager->set_step_status( self::STEP, 'complete' );

			return [ 'internal_pages' => $plan, 'status' => 'complete', 'unavailable' => true, 'reason' => 'unavailable' ];
		}

		$entry         = is_array( $plan[ $type ] ?? null ) ? $plan[ $type ] : State_Manager::INTERNAL_PAGE_ENTRY;
		$entry         = $this->process_page(
			$type,
			$entry,
			$this->type_requested( $payload['overwrite'] ?? [], $type ),
			$this->type_requested( $payload['convert_legacy'] ?? [], $type )
		);
		$plan[ $type ] = $entry;
		$fresh                    = $this->state_manager->get_state();
		$fresh['internal_pages']  = $plan;
		$this->state_manager->save_state( $fresh );
		$pending = $this->is_pending( $plan );
		$done    = in_array( (string) ( $entry['status'] ?? '' ), [ 'complete', 'skipped' ], true ) && ! $pending;
		$this->state_manager->set_step_status( self::STEP, $done ? 'complete' : ( $pending ? 'running' : 'pending' ) );

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
			$shell = $this->find_shell( $type, $pages );
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
	 * @param array<int,array<string,mixed>> $generated_pages
	 * @return array{id:int,slug:string}|null
	 */
	private function find_shell( string $want, array $generated_pages ) {
		$aliases = self::LEGACY_SLUGS[ $want ] ?? [ $want ];

		foreach ( $generated_pages as $page ) {
			if ( ! is_array( $page ) ) {
				continue;
			}
			$slug = \sanitize_title( (string) ( $page['slug'] ?? '' ) );
			$type = \sanitize_title( (string) ( $page['type'] ?? '' ) );
			if ( '' !== $type && $want !== $type ) {
				continue;
			}
			if ( '' === $type && ! in_array( $slug, $aliases, true ) ) {
				continue;
			}
			$id = \absint( $page['id'] ?? 0 );
			if ( $id <= 0 ) {
				continue;
			}
			$post = \get_post( $id );
			if ( $post && 'page' === $post->post_type ) {
				return [ 'id' => $id, 'slug' => $slug ];
			}
		}

		return null;
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
	private function next_pending_type( array $plan ): ?string {
		foreach ( self::READY_TYPES as $type ) {
			$status = (string) ( ( is_array( $plan[ $type ] ?? null ) ? $plan[ $type ] : [] )['status'] ?? '' );
			if ( in_array( $status, [ 'pending', 'failed' ], true ) ) {
				return $type;
			}
		}

		return null;
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
