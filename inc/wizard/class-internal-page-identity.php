<?php
/**
 * Stable identity and read-only preview for generated internal pages.
 *
 * @package Simple_RMS_Theme
 */

namespace Inc\Wizard;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-internal-page-blueprints.php';

/**
 * Resolves blueprint types from persisted evidence. GET/preview never writes.
 */
final class Internal_Page_Identity {
	public const READY_TYPES = [ 'about', 'services', 'contact', 'projects', 'testimonials', 'blog' ];

	/**
	 * Known historical default slugs only. Custom slugs never guess a type.
	 *
	 * @var array<string,string[]>
	 */
	public const LEGACY_SLUGS = [
		'about'        => [ 'about', 'about-us' ],
		'services'     => [ 'services' ],
		'contact'      => [ 'contact', 'contact-us' ],
		'projects'     => [ 'projects' ],
		'testimonials' => [ 'testimonials' ],
		'blog'         => [ 'blog' ],
	];

	/**
	 * @param array<int,array<string,mixed>> $generated_pages
	 * @return array{id:int,slug:string,source:string}|null
	 */
	public static function find_shell( string $want, array $generated_pages, array $plan = [] ) {
		$want = \sanitize_key( $want );
		if ( ! in_array( $want, self::READY_TYPES, true ) ) {
			return null;
		}

		$by_type = self::match_page(
			$generated_pages,
			static function ( array $page ) use ( $want ): bool {
				return $want === \sanitize_title( (string) ( $page['type'] ?? '' ) );
			},
			'type'
		);
		if ( null !== $by_type ) {
			return $by_type;
		}

		if ( 'blog' === $want ) {
			$by_role = self::match_page(
				$generated_pages,
				static function ( array $page ): bool {
					return 'blog' === \sanitize_key( (string) ( $page['role'] ?? '' ) );
				},
				'role'
			);
			if ( null !== $by_role ) {
				return $by_role;
			}
		}

		$blueprint = Internal_Page_Blueprints::all()[ $want ] ?? null;
		$template  = is_array( $blueprint ) ? (string) ( $blueprint['template'] ?? '' ) : '';
		if ( '' !== $template ) {
			$by_template = self::match_page(
				$generated_pages,
				static function ( array $page ) use ( $template ): bool {
					$id = \absint( $page['id'] ?? 0 );

					return $id > 0 && $template === (string) \get_post_meta( $id, '_wp_page_template', true );
				},
				'template'
			);
			if ( null !== $by_template ) {
				return $by_template;
			}
		}

		$planned_id = \absint( ( is_array( $plan[ $want ] ?? null ) ? $plan[ $want ] : [] )['post_id'] ?? 0 );
		if ( $planned_id > 0 ) {
			$by_plan = self::match_page(
				$generated_pages,
				static function ( array $page ) use ( $planned_id ): bool {
					return $planned_id === \absint( $page['id'] ?? 0 );
				},
				'plan'
			);
			if ( null !== $by_plan ) {
				return $by_plan;
			}
		}

		$aliases = self::LEGACY_SLUGS[ $want ] ?? [ $want ];

		return self::match_page(
			$generated_pages,
			static function ( array $page ) use ( $aliases ): bool {
				if ( '' !== \sanitize_title( (string) ( $page['type'] ?? '' ) ) ) {
					return false;
				}

				return in_array( \sanitize_title( (string) ( $page['slug'] ?? '' ) ), $aliases, true );
			},
			'legacy_slug'
		);
	}

	/**
	 * Read-only preview. Never persists type, plan, or options.
	 *
	 * @param array<string,mixed> $state
	 * @return array{types:array<string,array<string,mixed>>,unmapped:array<int,array<string,mixed>>,plan:array<string,array<string,mixed>>}
	 */
	public static function preview_plan( array $state ): array {
		$pages = is_array( $state['generated_pages'] ?? null ) ? $state['generated_pages'] : [];
		$plan  = is_array( $state['internal_pages'] ?? null ) ? $state['internal_pages'] : [];
		$types = [];
		$used  = [];

		foreach ( self::READY_TYPES as $type ) {
			$shell = self::find_shell( $type, $pages, $plan );
			$entry = is_array( $plan[ $type ] ?? null )
				? array_merge( State_Manager::INTERNAL_PAGE_ENTRY, $plan[ $type ] )
				: State_Manager::INTERNAL_PAGE_ENTRY;
			$blueprint = Internal_Page_Blueprints::all()[ $type ] ?? [];
			$status    = (string) ( $entry['status'] ?? '' );
			$reason    = (string) ( $entry['reason'] ?? '' );
			$post_id   = $shell ? (int) $shell['id'] : \absint( $entry['post_id'] ?? 0 );
			if ( null === $shell ) {
				$status = '' !== $status ? $status : 'skipped';
				$reason = '' !== $reason ? $reason : 'unavailable';
			} elseif ( '' === $status ) {
				$status = 'pending';
			}
			if ( $shell ) {
				$used[ (int) $shell['id'] ] = $type;
			}
			$types[ $type ] = [
				'post_id'           => $post_id,
				'slug'              => $shell ? (string) $shell['slug'] : '',
				'status'            => $status,
				'reason'            => $reason,
				'identity_source'   => $shell ? (string) $shell['source'] : 'none',
				'layouts'           => is_array( $entry['layouts'] ?? null ) && [] !== $entry['layouts']
					? $entry['layouts']
					: ( is_array( $blueprint['layouts'] ?? null ) ? $blueprint['layouts'] : [] ),
				'available'         => null !== $shell,
				'mapping_needed'    => false,
				'legacy_unconfirmed' => 'skipped' === $status && 'legacy_unconfirmed' === $reason,
			];
		}

		$unmapped = [];
		foreach ( $pages as $page ) {
			if ( ! is_array( $page ) ) {
				continue;
			}
			$id = \absint( $page['id'] ?? 0 );
			if ( $id <= 0 || isset( $used[ $id ] ) ) {
				continue;
			}
			$role = \sanitize_key( (string) ( $page['role'] ?? '' ) );
			if ( in_array( $role, [ 'home', 'blog' ], true ) ) {
				continue;
			}
			$type = \sanitize_title( (string) ( $page['type'] ?? '' ) );
			if ( in_array( $type, self::READY_TYPES, true ) ) {
				continue;
			}
			$post = \get_post( $id );
			if ( ! $post || 'page' !== $post->post_type ) {
				continue;
			}
			$unmapped[] = [
				'post_id'        => $id,
				'slug'           => \sanitize_title( (string) ( $page['slug'] ?? '' ) ),
				'title'          => (string) ( $page['title'] ?? '' ),
				'mapping_needed' => true,
			];
		}

		return [
			'types'    => $types,
			'unmapped' => $unmapped,
			'plan'     => $plan,
		];
	}

	/**
	 * Persist resolved type + post ID onto generated_pages. Mutation path only.
	 * Skips assignments that would break one-to-one identity.
	 *
	 * @param array<int,array<string,mixed>> $generated_pages
	 * @param array<int,string>              $id_to_type
	 * @param array<string,array<string,mixed>> $plan
	 * @return array<int,array<string,mixed>>
	 */
	public static function persist_types( array $generated_pages, array $id_to_type, array $plan = [] ): array {
		$id_to_type = self::exclusive_assignments( $generated_pages, $id_to_type, $plan );
		foreach ( $generated_pages as $index => $page ) {
			if ( ! is_array( $page ) ) {
				continue;
			}
			$id = \absint( $page['id'] ?? 0 );
			if ( $id <= 0 || ! isset( $id_to_type[ $id ] ) ) {
				continue;
			}
			$existing = \sanitize_title( (string) ( $page['type'] ?? '' ) );
			$type     = \sanitize_key( (string) $id_to_type[ $id ] );
			if ( '' !== $existing || ! in_array( $type, self::READY_TYPES, true ) ) {
				continue;
			}
			$generated_pages[ $index ]['type'] = $type;
		}

		return $generated_pages;
	}

	/**
	 * Explicit admin mapping. Atomic: any conflict returns WP_Error and the caller must write nothing.
	 *
	 * @param array<int,array<string,mixed>>        $generated_pages
	 * @param array<int,array<string,mixed>>        $map
	 * @param array<string,array<string,mixed>>     $plan
	 * @param array<string,mixed>                   $payload Confirmation flags.
	 * @return array<int,array<string,mixed>>|\WP_Error
	 */
	public static function apply_map( array $generated_pages, array $map, array $plan = [], array $payload = [] ) {
		$normalized = self::normalize_map( $map );
		if ( \is_wp_error( $normalized ) ) {
			return $normalized;
		}
		if ( [] === $normalized ) {
			return $generated_pages;
		}
		$confirmed = self::mapping_confirmed( $normalized, $payload );
		if ( ! $confirmed ) {
			return self::map_error(
				'rms_wizard_internal_map_confirmation_required',
				\__( 'Assigning a page type requires an explicit confirmation for each selected type.', 'simple-rms-theme' )
			);
		}
		$assignments = self::validate_map( $generated_pages, $normalized, $plan );
		if ( \is_wp_error( $assignments ) ) {
			return $assignments;
		}
		foreach ( $generated_pages as $index => $page ) {
			if ( ! is_array( $page ) ) {
				continue;
			}
			$id = \absint( $page['id'] ?? 0 );
			if ( isset( $assignments[ $id ] ) ) {
				$generated_pages[ $index ]['type'] = $assignments[ $id ];
			}
		}

		return $generated_pages;
	}

	/**
	 * @param array<int,array<string,mixed>> $map
	 * @return array<int,array{post_id:int,type:string}>|\WP_Error
	 */
	public static function normalize_map( array $map ) {
		$normalized = [];
		foreach ( $map as $row ) {
			if ( ! is_array( $row ) ) {
				return self::map_error(
					'rms_wizard_internal_map_invalid',
					\__( 'Each mapping row must name a generated page and a page type.', 'simple-rms-theme' )
				);
			}
			$id   = \absint( $row['post_id'] ?? 0 );
			$type = \sanitize_key( (string) ( $row['type'] ?? '' ) );
			if ( $id <= 0 || ! in_array( $type, self::READY_TYPES, true ) ) {
				return self::map_error(
					'rms_wizard_internal_map_invalid',
					\__( 'Each mapping row must name a generated page and a page type.', 'simple-rms-theme' )
				);
			}
			$normalized[] = [
				'post_id' => $id,
				'type'    => $type,
			];
		}

		return $normalized;
	}

	/**
	 * @param array<int,array{post_id:int,type:string}> $normalized
	 * @param array<string,mixed>                       $payload
	 */
	public static function mapping_confirmed( array $normalized, array $payload ): bool {
		$flag = $payload['confirm_map'] ?? false;
		if ( true !== $flag && 1 !== $flag && '1' !== (string) $flag ) {
			return false;
		}
		$expected = [];
		foreach ( $normalized as $row ) {
			$expected[] = $row['type'];
		}
		$expected = array_values( array_unique( $expected ) );
		sort( $expected );
		$got = [];
		foreach ( is_array( $payload['confirm_map_types'] ?? null ) ? $payload['confirm_map_types'] : [] as $type ) {
			$type = \sanitize_key( (string) $type );
			if ( '' !== $type ) {
				$got[] = $type;
			}
		}
		$got = array_values( array_unique( $got ) );
		sort( $got );

		return $expected === $got && [] !== $expected;
	}

	/**
	 * @param array<int,array<string,mixed>>            $generated_pages
	 * @param array<int,array{post_id:int,type:string}> $normalized
	 * @param array<string,array<string,mixed>>         $plan
	 * @return array<int,string>|\WP_Error
	 */
	public static function validate_map( array $generated_pages, array $normalized, array $plan ) {
		$ids   = [];
		$types = [];
		foreach ( $normalized as $row ) {
			$id   = $row['post_id'];
			$type = $row['type'];
			if ( isset( $ids[ $id ] ) ) {
				return self::map_error(
					'rms_wizard_internal_map_conflict',
					\__( 'Each generated page can map to only one internal page type.', 'simple-rms-theme' )
				);
			}
			if ( isset( $types[ $type ] ) ) {
				return self::map_error(
					'rms_wizard_internal_map_conflict',
					\__( 'Each internal page type can map to only one generated page.', 'simple-rms-theme' )
				);
			}
			$ids[ $id ]     = $type;
			$types[ $type ] = $id;
		}

		$pages_by_id = [];
		$type_owners = [];
		foreach ( $generated_pages as $page ) {
			if ( ! is_array( $page ) ) {
				continue;
			}
			$id = \absint( $page['id'] ?? 0 );
			if ( $id <= 0 ) {
				continue;
			}
			$pages_by_id[ $id ] = $page;
			$existing             = \sanitize_title( (string) ( $page['type'] ?? '' ) );
			if ( in_array( $existing, self::READY_TYPES, true ) ) {
				$type_owners[ $existing ] = $id;
			}
		}

		$plan_id_by_type = [];
		$plan_type_by_id = [];
		foreach ( $plan as $plan_type => $entry ) {
			$plan_type = \sanitize_key( (string) $plan_type );
			$pid       = \absint( is_array( $entry ) ? ( $entry['post_id'] ?? 0 ) : 0 );
			if ( $pid <= 0 || ! in_array( $plan_type, self::READY_TYPES, true ) ) {
				continue;
			}
			$plan_id_by_type[ $plan_type ] = $pid;
			$plan_type_by_id[ $pid ]       = $plan_type;
		}

		foreach ( $normalized as $row ) {
			$id   = $row['post_id'];
			$type = $row['type'];
			if ( ! isset( $pages_by_id[ $id ] ) ) {
				return self::map_error(
					'rms_wizard_internal_map_invalid',
					\__( 'Mapping is limited to generated pages that still exist.', 'simple-rms-theme' )
				);
			}
			$page = $pages_by_id[ $id ];
			$role = \sanitize_key( (string) ( $page['role'] ?? '' ) );
			if ( in_array( $role, [ 'home', 'blog' ], true ) && $type !== $role ) {
				return self::map_error(
					'rms_wizard_internal_map_invalid',
					\__( 'Home and Blog roles cannot be remapped to a different internal type.', 'simple-rms-theme' )
				);
			}
			$post = \get_post( $id );
			if ( ! $post || 'page' !== $post->post_type ) {
				return self::map_error(
					'rms_wizard_internal_map_invalid',
					\__( 'Each mapped page must be a live WordPress page.', 'simple-rms-theme' )
				);
			}
			$existing = \sanitize_title( (string) ( $page['type'] ?? '' ) );
			if ( '' !== $existing && $existing !== $type ) {
				return self::map_error(
					'rms_wizard_internal_map_conflict',
					\__( 'That generated page is already assigned to a different internal type.', 'simple-rms-theme' )
				);
			}
			if ( isset( $type_owners[ $type ] ) && $type_owners[ $type ] !== $id ) {
				return self::map_error(
					'rms_wizard_internal_map_conflict',
					\__( 'That internal page type is already assigned to another generated page.', 'simple-rms-theme' )
				);
			}
			if ( isset( $plan_id_by_type[ $type ] ) && $plan_id_by_type[ $type ] !== $id ) {
				return self::map_error(
					'rms_wizard_internal_map_conflict',
					\__( 'That internal page type is already bound to another page in the builder plan.', 'simple-rms-theme' )
				);
			}
			if ( isset( $plan_type_by_id[ $id ] ) && $plan_type_by_id[ $id ] !== $type ) {
				return self::map_error(
					'rms_wizard_internal_map_conflict',
					\__( 'That generated page is already bound to a different type in the builder plan.', 'simple-rms-theme' )
				);
			}
		}

		return $ids;
	}

	/**
	 * @param array<int,array<string,mixed>>    $generated_pages
	 * @param array<int,string>                 $id_to_type
	 * @param array<string,array<string,mixed>> $plan
	 * @return array<int,string>
	 */
	private static function exclusive_assignments( array $generated_pages, array $id_to_type, array $plan ): array {
		$owned_types = [];
		$owned_ids   = [];
		foreach ( $generated_pages as $page ) {
			if ( ! is_array( $page ) ) {
				continue;
			}
			$id   = \absint( $page['id'] ?? 0 );
			$type = \sanitize_title( (string) ( $page['type'] ?? '' ) );
			if ( $id > 0 && in_array( $type, self::READY_TYPES, true ) ) {
				$owned_types[ $type ] = $id;
				$owned_ids[ $id ]     = $type;
			}
		}
		foreach ( $plan as $type => $entry ) {
			$type = \sanitize_key( (string) $type );
			$pid  = \absint( is_array( $entry ) ? ( $entry['post_id'] ?? 0 ) : 0 );
			if ( $pid <= 0 || ! in_array( $type, self::READY_TYPES, true ) ) {
				continue;
			}
			if ( ! isset( $owned_types[ $type ] ) ) {
				$owned_types[ $type ] = $pid;
			}
			if ( ! isset( $owned_ids[ $pid ] ) ) {
				$owned_ids[ $pid ] = $type;
			}
		}
		$type_counts = array_count_values( array_map( 'strval', array_values( $id_to_type ) ) );
		$clean       = [];
		foreach ( $id_to_type as $id => $type ) {
			$id   = \absint( $id );
			$type = \sanitize_key( (string) $type );
			if ( $id <= 0 || ! in_array( $type, self::READY_TYPES, true ) ) {
				continue;
			}
			if ( ( $type_counts[ $type ] ?? 0 ) > 1 ) {
				continue;
			}
			if ( isset( $owned_types[ $type ] ) && $owned_types[ $type ] !== $id ) {
				continue;
			}
			if ( isset( $owned_ids[ $id ] ) && $owned_ids[ $id ] !== $type ) {
				continue;
			}
			$clean[ $id ] = $type;
		}

		return $clean;
	}

	/**
	 * @return \WP_Error
	 */
	private static function map_error( string $code, string $message ) {
		return new \WP_Error(
			$code,
			$message,
			[ 'status' => 400 ]
		);
	}

	/**
	 * WordPress-like page template resolution for an assigned blueprint template.
	 */
	public static function resolve_assigned_template( int $post_id ): string {
		$assigned = (string) \get_post_meta( $post_id, '_wp_page_template', true );
		$candidates = '' !== $assigned ? [ $assigned, 'page.php' ] : [ 'page.php' ];
		if ( ! function_exists( 'locate_template' ) ) {
			return $assigned !== '' ? $assigned : 'page.php';
		}
		$path = (string) \locate_template( $candidates, false, false );
		if ( '' === $path ) {
			return 'page.php';
		}
		$root = function_exists( 'get_template_directory' ) ? \trailingslashit( (string) \get_template_directory() ) : '';
		if ( '' !== $root && 0 === strpos( str_replace( '\\', '/', $path ), str_replace( '\\', '/', $root ) ) ) {
			return ltrim( str_replace( '\\', '/', substr( str_replace( '\\', '/', $path ), strlen( str_replace( '\\', '/', $root ) ) ) ), '/' );
		}

		return $assigned !== '' ? $assigned : 'page.php';
	}

	/**
	 * @param array<int,array<string,mixed>> $generated_pages
	 * @param callable(array<string,mixed>):bool $predicate
	 * @return array{id:int,slug:string,source:string}|null
	 */
	private static function match_page( array $generated_pages, callable $predicate, string $source ) {
		foreach ( $generated_pages as $page ) {
			if ( ! is_array( $page ) || ! $predicate( $page ) ) {
				continue;
			}
			$id = \absint( $page['id'] ?? 0 );
			if ( $id <= 0 ) {
				continue;
			}
			$post = \get_post( $id );
			if ( $post && 'page' === $post->post_type ) {
				return [
					'id'     => $id,
					'slug'   => \sanitize_title( (string) ( $page['slug'] ?? '' ) ),
					'source' => $source,
				];
			}
		}

		return null;
	}
}
