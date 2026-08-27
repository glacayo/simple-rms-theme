<?php
/**
 * Integration harness for landing run orchestration — calls public
 * Step_Landing_Page_Builder::run() with deterministic injected stubs.
 *
 * Run with: php scripts/test-landing-run-orchestrator.php
 *
 * Tests execute the real public run() path (start, repeated process),
 * not direct helper calls. A true fatal cannot be caught in-process;
 * we model the post-fatal persisted facts (item running/interrupted +
 * post exists + no checkpoint) then invoke the real next public process.
 */

namespace {
	if ( ! defined( 'ABSPATH' ) ) {
		define( 'ABSPATH', true );
	}
	if ( ! defined( 'OBJECT' ) ) {
		define( 'OBJECT', 'OBJECT' );
	}
	if ( ! defined( 'WP_Error' ) ) {
		// WP_Error class is defined below.
	}

	function __( $text, $domain = 'default' ) { return $text; }
	function sanitize_key( $key ) { return strtolower( preg_replace( '/[^a-z0-9_-]+/', '-', (string) $key ) ); }
	function sanitize_text_field( $value ) { return is_scalar( $value ) ? (string) $value : ''; }
	function sanitize_title( $value ) { return strtolower( preg_replace( '/[^a-z0-9-]+/', '-', (string) $value ) ); }
	function absint( $value ) { return abs( (int) $value ); }
	function current_time( $type, $gmt = false ) { return date( 'Y-m-d H:i:s' ); }
	function wp_generate_uuid4() { return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x', mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0x0fff)|0x4000, mt_rand(0,0x3fff)|0x8000, mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff) ); }
	function get_option( $name, $default = false ) { return $GLOBALS['_options'][$name] ?? $default; }
	function update_option( $name, $value, $autoload = null ) {
		// Simulate add_option uniqueness: return false if option exists and this is add.
		if ( isset( $GLOBALS['_options'][$name] ) && ( func_get_arg(2) ?? null ) === false && ! isset( $GLOBALS['_options_added'][$name] ) ) {
			// This is a raw update, not add_option.
		}
		if ( empty( $GLOBALS['_update_option_reentering'] ) && is_callable( $GLOBALS['_update_option_hook'] ?? null ) ) {
			$GLOBALS['_update_option_reentering'] = true;
			try {
				$GLOBALS['_update_option_hook']( $name, $value, $autoload );
			} finally {
				$GLOBALS['_update_option_reentering'] = false;
			}
		}
		$GLOBALS['_options'][$name] = $value;
		return true;
	}
	function add_option( $name, $value, $deprecated = '', $autoload = 'yes' ) {
		if ( isset( $GLOBALS['_options'][$name] ) ) {
			return false; // Atomic: option already exists.
		}
		$GLOBALS['_options'][$name] = $value;
		$GLOBALS['_options_added'][$name] = true;
		return true;
	}
	function delete_option( $name ) { unset( $GLOBALS['_options'][$name], $GLOBALS['_options_added'][$name] ); return true; }
	function wp_kses_post( $value ) { return (string) $value; }
	function get_post_type( $id ) { return isset( $GLOBALS['_posts'][$id] ) ? 'page' : false; }
	function get_post_meta( $id, $key, $single = false ) { return $GLOBALS['_post_meta'][$id][$key] ?? ''; }
	function update_post_meta( $id, $key, $value ) {
		if ( ! empty( $GLOBALS['_fail_landing_meta'] ) ) {
			$GLOBALS['_post_meta'][ $id ][ $key ] = 'invalid-meta';
			return true;
		}
		$GLOBALS['_post_meta'][$id][$key] = $value;
		return true;
	}
	function delete_post_meta( $id, $key, $value = '' ) { unset( $GLOBALS['_post_meta'][$id][$key] ); return true; }
	function get_post_status( $id ) { return isset( $GLOBALS['_posts'][$id] ) ? 'publish' : false; }
	function get_page_by_path( $slug, $output = \OBJECT, $type = 'page' ) { return $GLOBALS['_pages_by_slug'][$slug] ?? null; }
	function get_post( $id ) {
		if ( ! isset( $GLOBALS['_posts'][ $id ] ) ) {
			return null;
		}

		$stored = $GLOBALS['_posts'][ $id ];
		if ( $stored instanceof \WP_Post ) {
			return $stored;
		}

		if ( ! is_array( $stored ) ) {
			$stored = [];
		}

		return new \WP_Post( [
			'ID'           => (int) $id,
			'post_type'    => (string) ( $stored['post_type'] ?? 'page' ),
			'post_title'   => (string) ( $stored['post_title'] ?? '' ),
			'post_name'    => (string) ( $stored['post_name'] ?? '' ),
			'post_status'  => (string) ( $stored['post_status'] ?? 'publish' ),
			'post_content' => (string) ( $stored['post_content'] ?? '' ),
		] );
	}
	function wp_update_post( $args, $err = false ) {
		$id = (int) ( $args['ID'] ?? 0 );
		$GLOBALS['_updated_posts'][] = $id;

		if ( $id > 0 ) {
			$current = isset( $GLOBALS['_posts'][ $id ] ) && is_array( $GLOBALS['_posts'][ $id ] )
				? $GLOBALS['_posts'][ $id ]
				: [ 'ID' => $id, 'post_type' => 'page', 'post_content' => '' ];
			$GLOBALS['_posts'][ $id ] = array_merge(
				$current,
				[
					'post_title'   => $args['post_title'] ?? ( $current['post_title'] ?? '' ),
					'post_name'    => $args['post_name'] ?? ( $current['post_name'] ?? '' ),
					'post_status'  => $args['post_status'] ?? ( $current['post_status'] ?? 'publish' ),
					'post_content' => $args['post_content'] ?? ( $current['post_content'] ?? '' ),
					'post_type'    => 'page',
				]
			);
		}

		return $id;
	}
	function is_wp_error( $thing ) { return $thing instanceof \WP_Error; }
	function wp_insert_post( $args, $err = false, $return = 'WP_Error' ) {
		$GLOBALS['_insert_post_calls'] = ( $GLOBALS['_insert_post_calls'] ?? 0 ) + 1;
		$GLOBALS['_post_counter'] = ( $GLOBALS['_post_counter'] ?? 1000 ) + 1;
		$id = $GLOBALS['_post_counter'];
		$GLOBALS['_posts'][$id] = [
			'ID'           => $id,
			'post_type'    => 'page',
			'post_title'   => (string) ( $args['post_title'] ?? '' ),
			'post_name'    => (string) ( $args['post_name'] ?? '' ),
			'post_status'  => (string) ( $args['post_status'] ?? 'publish' ),
			'post_content' => (string) ( $args['post_content'] ?? '' ),
		];
		$GLOBALS['_pages_by_slug'][ $args['post_name'] ?? '' ] = new \WP_Post( [
			'ID'           => $id,
			'post_type'    => 'page',
			'post_title'   => (string) ( $args['post_title'] ?? '' ),
			'post_name'    => (string) ( $args['post_name'] ?? '' ),
			'post_status'  => (string) ( $args['post_status'] ?? 'publish' ),
			'post_content' => (string) ( $args['post_content'] ?? '' ),
		] );
		$GLOBALS['_post_meta'][$id]['rms_landing_type'] = $args['meta_input']['rms_landing_type'] ?? 'seo';
		$GLOBALS['_post_meta'][$id]['_wp_page_template'] = $args['meta_input']['_wp_page_template'] ?? '';
		return $id;
	}
	function get_page_template_slug( $id ) { return $GLOBALS['_post_meta'][$id]['_wp_page_template'] ?? ''; }
	function get_theme_mod( $name, $default = false ) { return $default; }
	function get_field( $name, $id, $raw = false ) { return $GLOBALS['_acf_fields'][$id][$name] ?? false; }
	function wp_create_nonce( $action = -1 ) { return 'nonce'; }
	function wp_verify_nonce( $nonce, $action = -1 ) { return true; }
	function current_user_can( $cap ) { return true; }
	function esc_html( $v ) { return (string) $v; }
	function esc_attr( $v ) { return (string) $v; }
	function esc_url( $v ) { return (string) $v; }
	function esc_url_raw( $v ) { return (string) $v; }
	function esc_textarea( $v ) { return (string) $v; }
	function selected( $a, $b, $echo = true ) { return $a === $b ? ' selected' : ''; }
	function checked( $a, $b, $echo = true ) { return $a === $b ? ' checked' : ''; }
	function sanitize_hex_color( $v ) { return $v; }
	function wp_get_attachment_image_url( $id, $size ) { return ''; }
	function trailingslashit( $v ) { return rtrim( $v, '/\\' ) . '/'; }
	function get_template_directory() { return __DIR__ . '/..'; }
	function get_template_directory_uri() { return ''; }
	function rest_url( $v = '' ) { return ''; }
	function apply_filters( $tag, $value ) { return $value; }
	function do_action( $tag, ...$args ) {}
	function get_the_ID() { return 0; }
	function is_page() { return false; }
	function get_queried_object_id() { return 0; }
	function add_query_arg( ...$args ) { return ''; }
	function admin_url( $v = '' ) { return ''; }
	function wp_safe_redirect( $v ) {}
	function wp_die( $v ) { throw new \Exception( (string) $v ); }
	function submit_button( $v, $type = '', $name = '', $echo = true ) { return ''; }
	function wp_nonce_field( $action, $name = null ) {}
	function wp_enqueue_script( ...$args ) {}
	function wp_enqueue_style( ...$args ) {}
	function wp_enqueue_media( ...$args ) {}
	function wp_script_is( ...$args ) { return false; }
	function wp_add_inline_script( ...$args ) {}
	function wp_json_encode( $data, $flags = 0 ) { return \json_encode( $data, $flags ); }
	function maybe_serialize( $value ) { return is_array( $value ) || is_object( $value ) ? serialize( $value ) : (string) $value; }
	function maybe_unserialize( $value ) {
		if ( ! is_string( $value ) ) { return $value; }
		$decoded = @unserialize( $value );
		return false === $decoded && 'b:0;' !== $value ? $value : $decoded;
	}
	function wp_cache_delete( $key, $group = '' ) { return true; }
	function get_permalink( $id ) { return ''; }
	function add_theme_page( ...$args ) {}
	function add_action( ...$args ) { $GLOBALS['_wp_actions'][] = $args; }
	function add_filter( ...$args ) {}
	function register_rest_route( ...$args ) {}

	$GLOBALS['_options'] = [];
	$GLOBALS['_wp_actions'] = [];
	$GLOBALS['_options_added'] = [];
	$GLOBALS['_posts'] = [];
	$GLOBALS['_updated_posts'] = [];
	$GLOBALS['_post_meta'] = [];
	$GLOBALS['_pages_by_slug'] = [];
	$GLOBALS['_acf_fields'] = [];
	$GLOBALS['_post_counter'] = 1000;
	$GLOBALS['_insert_post_calls'] = 0;
	$GLOBALS['_fail_landing_meta'] = false;
	$GLOBALS['_ai_fail'] = false;
	$GLOBALS['_update_option_hook'] = null;
	$GLOBALS['_wpdb_delete_hook'] = null;
	$GLOBALS['_update_option_reentering'] = false;

	class Fake_WPDB {
		public $options = 'wp_options';

		public function prepare( $query, ...$args ) {
			return [ 'query' => $query, 'args' => $args ];
		}

		public function get_var( $prepared ) {
			$name = (string) ( $prepared['args'][0] ?? '' );
			return array_key_exists( $name, $GLOBALS['_options'] )
				? maybe_serialize( $GLOBALS['_options'][ $name ] )
				: null;
		}

		public function query( $prepared ) {
			$query = (string) ( $prepared['query'] ?? '' );
			$args  = $prepared['args'] ?? [];
			$name  = (string) ( $args[0] ?? '' );

			if ( 0 === stripos( ltrim( $query ), 'INSERT IGNORE' ) ) {
				if ( array_key_exists( $name, $GLOBALS['_options'] ) ) {
					return 0;
				}

				$GLOBALS['_options'][ $name ] = maybe_unserialize( (string) ( $args[1] ?? '' ) );
				return 1;
			}

			if ( 0 === stripos( ltrim( $query ), 'DELETE FROM' ) ) {
				if ( ! array_key_exists( $name, $GLOBALS['_options'] ) ) {
					return 0;
				}

				$current = maybe_serialize( $GLOBALS['_options'][ $name ] );
				if ( $current !== (string) ( $args[1] ?? '' ) ) {
					return 0;
				}

				unset( $GLOBALS['_options'][ $name ] );
				if ( is_callable( $GLOBALS['_wpdb_delete_hook'] ?? null ) ) {
					$GLOBALS['_wpdb_delete_hook']( $name );
				}
				return 1;
			}

			return 0;
		}
	}

	$GLOBALS['wpdb'] = new Fake_WPDB();

	class WP_Post {
		public $ID;
		public $post_type = 'page';
		public $post_title = '';
		public $post_name = '';
		public $post_status = 'publish';
		public $post_content = '';

		public function __construct( $data = [] ) {
			foreach ( (array) $data as $key => $value ) {
				$this->{$key} = $value;
			}
		}
	}

	class WP_Error {
		private $code;
		private $message;
		private $data;
		public function __construct( $code = '', $message = '', $data = '' ) {
			$this->code = $code;
			$this->message = $message;
			$this->data = $data;
		}
		public function get_error_code() { return $this->code; }
		public function get_error_message() { return $this->message; }
		public function get_error_data() { return $this->data; }
		public function add_data( $data ) { $this->data = $data; }
	}

	class WP_REST_Server { const READABLE = 'GET'; const CREATABLE = 'POST'; }
	class WP_REST_Request implements \ArrayAccess {
		private $data = [];
		public $json = [];
		public function __construct( $data = [] ) {
			$this->data = is_array( $data ) ? $data : [];
			$this->json = $this->data;
		}
		public function get_json_params() { return $this->json; }
		public function get_params() { return $this->data; }
		public function offsetExists( mixed $offset ): bool { return array_key_exists( $offset, $this->data ); }
		public function offsetGet( mixed $offset ): mixed { return $this->data[ $offset ] ?? null; }
		public function offsetSet( mixed $offset, mixed $value ): void {
			if ( null === $offset ) {
				$this->data[] = $value;
				return;
			}
			$this->data[ $offset ] = $value;
		}
		public function offsetUnset( mixed $offset ): void { unset( $this->data[ $offset ] ); }
	}
	class WP_REST_Response {
		public $data;
		public $status;
		public function __construct( $data, $code = 200 ) {
			$this->data   = $data;
			$this->status = (int) $code;
		}
	}
	class WP_Query {
		public $posts = [];
		public function __construct( $args = [] ) {}
	}

	class TestRunner {
		private $pass = 0;
		private $fail = 0;
		public function assert( $condition, string $label ): void {
			if ( $condition ) { echo "  PASS: {$label}\n"; $this->pass++; }
			else { echo "  FAIL: {$label}\n"; $this->fail++; }
		}
		public function assertEqual( $expected, $actual, string $label ): void {
			$this->assert( $expected === $actual, "{$label} (expected: " . json_encode( $expected ) . ", actual: " . json_encode( $actual ) . ")" );
		}
		public function results(): void {
			echo "\n  Results: {$this->pass} passed, {$this->fail} failed\n";
			if ( $this->fail > 0 ) { exit( 1 ); }
		}
	}

	function reset_state(): void {
		global $_options, $_options_added, $_posts, $_updated_posts, $_post_meta, $_pages_by_slug, $_acf_fields, $_post_counter, $_insert_post_calls, $_fail_landing_meta, $_ai_fail, $_update_option_hook, $_wpdb_delete_hook, $_update_option_reentering;
		$_options = [];
		$_options_added = [];
		$_posts = [];
		$_updated_posts = [];
		$_post_meta = [];
		$_pages_by_slug = [];
		$_acf_fields = [];
		$_post_counter = 1000;
		$_insert_post_calls = 0;
		$_fail_landing_meta = false;
		$_ai_fail = false;
		$_update_option_hook = null;
		$_wpdb_delete_hook = null;
		$_update_option_reentering = false;
	}

	function is_legacy_lock_clear_save( $name, $value ): bool {
		if ( 'rms_wizard_state' !== $name || ! is_array( $value ) ) {
			return false;
		}

		$previous = $GLOBALS['_options']['rms_wizard_state'] ?? [];
		$had_lock = is_array( $previous ) && ! empty( $previous['locks']['step_execution'] );

		return $had_lock && empty( $value['locks']['step_execution'] );
	}

	function make_landing_payload_item( string $key, string $title, int $id = 0 ): array {
		return [
			'id' => $id > 0 ? (string) $id : '',
			'landing_key' => $key,
			'title' => $title,
			'slug' => sanitize_title( $title ),
			'landing_type' => 'seo',
			'primary_keyword' => $title . ' keyword',
			'subkeywords' => '',
			// Use only non-keyword sections to avoid AI generation in integration tests.
			// hero and seo-content are keyword layouts that require AI; badges and portfolio-v1 are reusable.
			'sections' => [
				[ 'layout' => 'badges' ],
				[ 'layout' => 'portfolio-v1' ],
			],
		];
	}

	function make_existing_entry( string $key, string $title, int $id ): array {
		return [
			'id' => $id,
			'landing_key' => $key,
			'title' => $title,
			'slug' => sanitize_title( $title ),
			'landing_type' => 'seo',
			'menu_eligible' => true,
			'primary_keyword' => $title . ' keyword',
			'subkeywords' => [],
			'generated_at' => date( 'Y-m-d H:i:s' ),
		];
	}

	function make_existing_entry_no_sections( string $key, string $title, int $id ): array {
		return make_existing_entry( $key, $title, $id );
	}

	function landing_skip_snapshot( $sm ): array {
		$state = $sm->get_state();

		return [
			'run'  => $state['landing_run'] ?? null,
			'step' => (string) ( $state['step_status']['landing-page-builder'] ?? '' ),
		];
	}

	function wizard_identity_snapshot( $sm ): array {
		$state = $sm->get_state();

		return [
			'current_step'  => (string) ( $state['current_step'] ?? '' ),
			'step_status'   => is_array( $state['step_status'] ?? null ) ? $state['step_status'] : [],
			'landing_run'   => $state['landing_run'] ?? null,
			'landing_pages' => is_array( $state['landing_pages'] ?? null ) ? $state['landing_pages'] : [],
		];
	}

	function error_exposes_owner( $error ): bool {
		if ( ! is_wp_error( $error ) ) {
			return false;
		}

		$data    = $error->get_error_data();
		$encoded = strtolower( $error->get_error_code() . ' ' . $error->get_error_message() . ' ' . wp_json_encode( $data ) );

		if ( is_array( $data ) && ( isset( $data['owner'] ) || isset( $data['lease_owner'] ) ) ) {
			return true;
		}

		return false !== strpos( $encoded, 'owner_' ) || false !== strpos( $encoded, 'lease_owner' );
	}

	function payload_exposes_owner( $payload ): bool {
		if ( is_wp_error( $payload ) ) {
			return error_exposes_owner( $payload );
		}

		if ( $payload instanceof \WP_REST_Response ) {
			$payload = $payload->data;
		}

		$encoded = strtolower( (string) wp_json_encode( $payload ) );

		return false !== strpos( $encoded, '"owner"' )
			|| false !== strpos( $encoded, 'lease_owner' )
			|| false !== strpos( $encoded, 'owner_' );
	}

	function seed_required_steps_complete( $sm ): void {
		$status = [];

		foreach ( \Inc\Wizard\Step_Controller::get_required_steps() as $step ) {
			$status[ $step ] = 'complete';
		}

		$sm->save_state( [ 'step_status' => $status ] );
	}

	function seed_stale_running_item( $sm ): array {
		$sm->save_state( [
			'ai_config'   => [ 'provider' => 'test', 'model' => 'test-model', 'has_credentials' => true ],
			'client_data' => [ 'company_name' => 'Test Company' ],
		] );
		$builder = new \Inc\Wizard\Step_Landing_Page_Builder( new \Inc\Wizard\Logger(), $sm );
		$start   = $builder->run( [
			'landing_action'    => 'start',
			'landings'          => [
				make_landing_payload_item( 'lk_stale_a', 'Stale One' ),
				make_landing_payload_item( 'lk_stale_b', 'Stale Two' ),
			],
			'replace_canonical' => [],
		] );

		if ( is_wp_error( $start ) ) {
			return [ 'error' => $start ];
		}

		$orch = new \Inc\Wizard\Landing_Run_Orchestrator( $sm, new \Inc\Wizard\Logger() );
		$next = $orch->get_next_item();

		if ( ! is_array( $next ) ) {
			return [ 'error' => 'no-next-item' ];
		}

		$orch->mark_item_running( (string) $next['key'] );
		$state       = $sm->get_state();
		$option_name = 'rms_landing_lease_' . sanitize_key( (string) ( $state['landing_run']['run_id'] ?? '' ) );
		$lease       = get_option( $option_name, false );

		if ( is_array( $lease ) ) {
			$lease['expires_at'] = time() - 100;
			update_option( $option_name, $lease );
		}

		return [
			'item_key'    => (string) $next['key'],
			'option_name' => $option_name,
			'run'         => $sm->get_state()['landing_run'] ?? null,
		];
	}

	function wp_error_http_status( $error ): int {
		if ( ! is_wp_error( $error ) ) {
			return 0;
		}
		$data = $error->get_error_data();

		return is_array( $data ) ? (int) ( $data['status'] ?? 0 ) : 0;
	}

	function seed_incomplete_landing_run( $sm, string $status = 'pending' ) {
		$sm->save_state( [
			'ai_config'    => [ 'provider' => 'test', 'model' => 'test', 'has_credentials' => true ],
			'client_data'  => [ 'company_name' => 'Test' ],
			'step_status'  => [ 'landing-page-builder' => 'running' ],
		] );
		$orch = new \Inc\Wizard\Landing_Run_Orchestrator( $sm, new \Inc\Wizard\Logger() );
		$orch->start_run(
			[ [ 'landing_key' => 'lk_skip_race', 'id' => 0, 'title' => 'Skip Race', 'slug' => 'skip-race', 'landing_type' => 'seo', 'menu_eligible' => true, 'primary_keyword' => 'kw', 'subkeywords' => [], 'sections' => [ [ 'layout' => 'badges' ] ] ] ],
			[],
			[]
		);
		$state = $sm->get_state();
		$state['landing_run']['status'] = $status;
		$state['step_status']['landing-page-builder'] = 'running';
		$sm->save_state( $state );

		return $orch;
	}

	function rest_skip_all( $sm ) {
		$rest    = new \Inc\Wizard\Rest_Controller( new \Inc\Wizard\Step_Controller( $sm, new \Inc\Wizard\Logger() ) );
		$request = new \WP_REST_Request( [
			'step'     => 'landing-page-builder',
			'skip_all' => true,
		] );
		$request->json = [ 'skip_all' => true ];

		return $rest->run_step( $request );
	}

	// Include required classes.
	require_once __DIR__ . '/../inc/wizard/class-logger.php';
	require_once __DIR__ . '/../inc/wizard/class-state-manager.php';
	require_once __DIR__ . '/../inc/wizard/class-wizard-mutation-fence.php';
	require_once __DIR__ . '/../inc/wizard/class-landing-run-orchestrator.php';
	require_once __DIR__ . '/../inc/wizard/class-canonical-section-store.php';
	require_once __DIR__ . '/../inc/wizard/class-flexible-content-layouts.php';
	require_once __DIR__ . '/../inc/wizard/class-ai-content-harness.php';
	require_once __DIR__ . '/../inc/wizard/class-ai-content-reviewer.php';
	require_once __DIR__ . '/../inc/wizard/class-yoast-meta-writer.php';
	require_once __DIR__ . '/../inc/wizard/class-menu-builder.php';
	require_once __DIR__ . '/../inc/wizard/class-content-builder.php';
	require_once __DIR__ . '/../inc/wizard/class-section-assembler.php';
	require_once __DIR__ . '/../inc/wizard/class-placeholder-provenance-store.php';
	\Inc\Wizard\Placeholder_Provenance_Store::register();
	$provenance_hook = false;
	foreach ( $GLOBALS['_wp_actions'] as $args ) {
		if ( 'acf/save_post' === ( $args[0] ?? '' )
			&& array( \Inc\Wizard\Placeholder_Provenance_Store::class, 'handle_acf_save_post' ) === ( $args[1] ?? null )
			&& 20 === ( $args[2] ?? 0 )
			&& 1 === ( $args[3] ?? 0 )
		) {
			$provenance_hook = true;
			break;
		}
	}
	if ( ! $provenance_hook ) {
		fwrite( STDERR, "FAIL provenance acf/save_post registration was not recorded by add_action fake\n" );
		exit( 1 );
	}

	// Stubs for AI/provider classes that are needed but not included.
	if ( ! class_exists( 'Inc\Wizard\AI_Provider_Registry' ) ) {
		eval( 'namespace Inc\Wizard; class AI_Provider_Registry { public static function make_provider($p,$k=""){return new class{ function generate(){ if ( ! empty( $GLOBALS["_ai_fail"] ) ) { return ["success"=>false,"content"=>"","error"=>"HTTP 500 provider error"]; } return ["success"=>true,"content"=>json_encode(["headline"=>"Test","subheadline"=>"Test","body"=>"Test","hero_title"=>"Test","hero_description"=>"Test body copy for the landing hero."])]; } function list_models(){return [];} }; } public static function provider_exists($p){return true;} public static function default_provider(){return "test";} public static function get_provider_label($p){return "Test";} public static function list_providers(){return [["slug"=>"test","label"=>"Test"]];} }' );
	}
	if ( ! class_exists( 'Inc\Wizard\AI_Credential_Store' ) ) {
		eval( 'namespace Inc\Wizard; class AI_Credential_Store { public static function has($p){return true;} public static function save($p,$k){return true;} public static function status($p){return ["has_key"=>true,"status"=>"saved"];} public static function mask_status($p){return "saved";} public static function normalize_api_key($k){return $k;} }' );
	}
	if ( ! class_exists( 'Inc\Wizard\AI_Provider' ) ) {
		eval( 'namespace Inc\Wizard; class AI_Provider {}' );
	}
	if ( ! class_exists( 'Inc\Wizard\Step_Controller' ) ) {
		require_once __DIR__ . '/../inc/wizard/class-step-controller.php';
	}
	if ( ! class_exists( 'Inc\Wizard\Rest_Controller' ) ) {
		require_once __DIR__ . '/../inc/wizard/class-rest-controller.php';
	}
	if ( ! class_exists( 'Inc\Wizard\Wizard_Unlock_Controller' ) ) {
		eval( 'namespace Inc\Wizard; class Wizard_Unlock_Controller { public static function is_force_unlocked(){return false;} public static function is_controlled_unlock_enabled(){return false;} public static function is_unlocked(){return false;} public static function has_unlock_marker(){return false;} const UNLOCKED_AT_OPTION=""; const UNLOCKED_BY_OPTION=""; const UNLOCK_ACTION=""; const RELOCK_ACTION=""; const NONCE_ACTION=""; public static function verify_admin_nonce($n){return true;} }' );
	}

	require_once __DIR__ . '/../inc/wizard/class-step-landing-page-builder.php';

	use Inc\Wizard\Logger;
	use Inc\Wizard\State_Manager;
	use Inc\Wizard\Landing_Run_Orchestrator;
	use Inc\Wizard\Step_Landing_Page_Builder;
	use Inc\Wizard\Step_Controller;
	use Inc\Wizard\Rest_Controller;
	use Inc\Wizard\Wizard_Mutation_Fence;
	use Inc\Wizard\Canonical_Section_Store;
	use Inc\Wizard\Flexible_Content_Layouts;
	use Inc\Wizard\AI_Content_Harness;
	use Inc\Wizard\Content_Builder;

	/**
	 * Create a Step_Landing_Page_Builder with stubbed build_one_landing.
	 * The stub deterministically creates a post and returns an entry,
	 * simulating the real build flow without AI calls.
	 */
	function make_builder( ?State_Manager $sm = null, ?callable $build_override = null ): Step_Landing_Page_Builder {
		$sm = $sm ?? new State_Manager();
		$logger = new Logger();

		// Set up AI config and client data in state.
		$sm->save_state( [
			'ai_config' => [ 'provider' => 'test', 'model' => 'test-model', 'has_credentials' => true ],
			'client_data' => [ 'company_name' => 'Test Company' ],
		] );

		// Create a builder with a stub that overrides build_one_landing via reflection.
		$builder = new Step_Landing_Page_Builder( $logger, $sm );

		if ( null !== $build_override ) {
			// Use reflection to replace build_one_landing behavior is complex.
			// Instead, we set up the environment so the real build_one_landing works
			// with stubbed AI providers (already done via AI_Provider_Registry stub).
		}

		return $builder;
	}

	$t = new TestRunner();

	// === TEST 1: Plan persisted before processing (via public run start) ===
	echo "\nTest 1: Plan persisted before processing (public run start)\n";
	reset_state();
	$sm = new State_Manager();
	$sm->save_state( [
		'ai_config' => [ 'provider' => 'test', 'model' => 'test-model', 'has_credentials' => true ],
		'client_data' => [ 'company_name' => 'Test Company' ],
	] );
	$builder = new Step_Landing_Page_Builder( new Logger(), $sm );

	$payload = [
		'landing_action' => 'start',
		'landings' => [
			make_landing_payload_item( 'lk_1', 'Landing One' ),
			make_landing_payload_item( 'lk_2', 'Landing Two' ),
		],
		'replace_canonical' => [],
	];

	$result = $builder->run( $payload );
	$t->assert( ! is_wp_error( $result ), 'start does not return WP_Error' . ( is_wp_error( $result ) ? ': ' . $result->get_error_message() : '' ) );
	if ( is_wp_error( $result ) ) {
		$t->results();
		return;
	}
	$t->assert( isset( $result['landing_run'] ), 'Result has landing_run' );
	$t->assertEqual( 2, $result['total'], 'Total is 2' );

	// Verify plan persisted in state before processing.
	$state = $sm->get_state();
	$t->assert( isset( $state['landing_run'] ), 'Plan persisted in state' );
	$t->assertEqual( 2, count( $state['landing_run']['items'] ), 'Plan has 2 items' );

	// === TEST 2: Nine-item run processes one per request ===
	echo "\nTest 2: Nine-item run processes one per request\n";
	reset_state();
	$sm = new State_Manager();
	$sm->save_state( [
		'ai_config' => [ 'provider' => 'test', 'model' => 'test-model', 'has_credentials' => true ],
		'client_data' => [ 'company_name' => 'Test Company' ],
	] );
	$builder = new Step_Landing_Page_Builder( new Logger(), $sm );

	$landings = [];
	for ( $i = 1; $i <= 9; $i++ ) {
		$landings[] = make_landing_payload_item( "lk_{$i}", "Landing {$i}" );
	}

	$result = $builder->run( [ 'landing_action' => 'start', 'landings' => $landings, 'replace_canonical' => [] ] );
	$t->assertEqual( 9, $result['total'], 'Nine-item total is 9' );

	// After start, one item should have been processed (completed=1).
	$t->assertEqual( 1, $result['completed'], 'One item completed after start+process' );

	// Now call process repeatedly — each should complete one more.
	$completed = 1;
	for ( $req = 2; $req <= 9; $req++ ) {
		$result = $builder->run( [ 'landing_action' => 'process' ] );
		if ( is_wp_error( $result ) ) {
			break;
		}
		$completed = $result['completed'];
	}
	$t->assertEqual( 9, $completed, 'All 9 completed after 9 process requests' );
	$t->assertEqual( 'completed', $result['landing_run']['status'], 'Run status is completed' );

	// Verify 9 unique landing pages.
	$state = $sm->get_state();
	$t->assertEqual( 9, count( $state['landing_pages'] ), '9 unique landing pages in state' );
	$t->assertEqual( 9, count( $result['landing_pages'] ?? [] ), 'Finalize HTTP response includes all persisted landing pages' );
	$t->assertEqual(
		array_values( array_map( static fn( $page ) => (string) ( $page['landing_key'] ?? '' ), $state['landing_pages'] ) ),
		array_values( array_map( static fn( $page ) => (string) ( $page['landing_key'] ?? '' ), $result['landing_pages'] ?? [] ) ),
		'Finalize response landing_pages keys match persisted state'
	);

	// === TEST 3: Four pre-existing unchanged + five new ===
	echo "\nTest 3: Four pre-existing unchanged skipped; five pending\n";
	reset_state();
	$sm = new State_Manager();
	$existing = [];
	for ( $i = 1; $i <= 4; $i++ ) {
		$existing[ "lk_{$i}" ] = make_existing_entry_no_sections( "lk_{$i}", "Landing {$i}", 100 + $i );
	}
	$sm->save_state( [
		'ai_config' => [ 'provider' => 'test', 'model' => 'test-model', 'has_credentials' => true ],
		'client_data' => [ 'company_name' => 'Test Company' ],
		'landing_pages' => array_values( $existing ),
	] );
	$builder = new Step_Landing_Page_Builder( new Logger(), $sm );

	$landings = [];
	for ( $i = 1; $i <= 9; $i++ ) {
		$landings[] = make_landing_payload_item( "lk_{$i}", "Landing {$i}", $i <= 4 ? 100 + $i : 0 );
	}

	$result = $builder->run( [ 'landing_action' => 'start', 'landings' => $landings, 'replace_canonical' => [] ] );
	$t->assertEqual( 9, $result['total'], 'Total is 9' );

	// After start, 4 unchanged are completed + 1 new item processed = 5 total.
	// But the plan BEFORE the first process had 4 completed. Verify via run items.
	$state = $sm->get_state();
	$completed_at_start = 0;
	foreach ( $state['landing_run']['items'] as $item ) {
		if ( 'completed' === $item['status'] && (int) $item['post_id'] > 0 && (int) $item['id'] > 0 ) {
			$completed_at_start++;
		}
	}
	// The 4 pre-existing items had id > 0 and were classified completed at plan time.
	// The start then processed lk_5 (id=0, new), which is now also completed.
	// So we check that lk_1 through lk_4 are all completed.
	$lk1_to_4_completed = true;
	foreach ( $state['landing_run']['items'] as $item ) {
		if ( in_array( $item['key'], [ 'lk_1', 'lk_2', 'lk_3', 'lk_4' ], true ) && 'completed' !== $item['status'] ) {
			$lk1_to_4_completed = false;
			break;
		}
	}
	$t->assert( $lk1_to_4_completed, 'Four pre-existing unchanged items (lk_1..lk_4) are completed' );

	// Next pending item should be lk_6 (lk_5 was already processed by start).
	$orch = new Landing_Run_Orchestrator( $sm, new Logger() );
	$next = $orch->get_next_item();
	$t->assertEqual( 'lk_6', $next['key'], 'Next pending is lk_6 (lk_5 processed by start)' );

	// === TEST 4: Forced interruption after four preserves plan ===
	echo "\nTest 4: Forced interruption after four preserves plan\n";
	reset_state();
	$sm = new State_Manager();
	$sm->save_state( [
		'ai_config' => [ 'provider' => 'test', 'model' => 'test-model', 'has_credentials' => true ],
		'client_data' => [ 'company_name' => 'Test Company' ],
	] );
	$builder = new Step_Landing_Page_Builder( new Logger(), $sm );

	$landings = [];
	for ( $i = 1; $i <= 9; $i++ ) {
		$landings[] = make_landing_payload_item( "lk_{$i}", "Landing {$i}" );
	}
	$builder->run( [ 'landing_action' => 'start', 'landings' => $landings, 'replace_canonical' => [] ] );

	// Process 3 more items (total 4 completed).
	for ( $i = 2; $i <= 4; $i++ ) {
		$builder->run( [ 'landing_action' => 'process' ] );
	}

	$orch = new Landing_Run_Orchestrator( $sm, new Logger() );
	$run = $orch->get_run();
	$t->assertEqual( 4, $run['completed'], 'Four completed before interruption' );

	// Simulate fatal: mark item 5 as running, then expire the mutex.
	$orch->mark_item_running( 'lk_5' );
	$state = $sm->get_state();
	// Expire the mutex option.
	$option_name = 'rms_landing_lease_' . sanitize_key( $state['landing_run']['run_id'] );
	$lease = get_option( $option_name, false );
	if ( is_array( $lease ) ) {
		$lease['expires_at'] = time() - 100;
		update_option( $option_name, $lease );
	}

	// === TEST 5: Reload hydrates plan ===
	echo "\nTest 5: Reload hydrates plan\n";
	$sm2 = new State_Manager();
	$orch2 = new Landing_Run_Orchestrator( $sm2, new Logger() );
	$run = $orch2->get_run();
	$t->assert( null !== $run, 'Plan hydrated after reload' );
	$t->assertEqual( 9, $run['total'], 'Plan total is 9 after reload' );

	// === TEST 8: Stale lease recovers ===
	echo "\nTest 8: Stale lease recovers\n";
	$orch2->recover_stale_lease();
	$run = $orch2->get_run();
	$item5 = null;
	foreach ( $run['items'] as $item ) {
		if ( 'lk_5' === $item['key'] ) { $item5 = $item; break; }
	}
	$t->assertEqual( 'interrupted', $item5['status'], 'Item 5 is interrupted after stale recovery' );
	$t->assertEqual( 'interrupted', $run['status'], 'Expired lease makes the run resumable interrupted' );
	$t->assertEqual( 4, $run['completed'], 'Four items still completed after recovery' );

	// === TEST 6: Resume starts fifth (via public run process) ===
	echo "\nTest 6: Resume starts fifth via public process\n";
	$builder2 = new Step_Landing_Page_Builder( new Logger(), $sm2 );
	$result = $builder2->run( [ 'landing_action' => 'process' ] );
	$t->assert( ! is_wp_error( $result ), 'Process after interruption succeeds' );
	$t->assertEqual( 5, $result['completed'], 'Fifth item completed after resume' );

	// === TEST 9: Concurrent call rejected (atomic mutex) ===
	echo "\nTest 9: Concurrent call rejected\n";
	reset_state();
	$sm = new State_Manager();
	$sm->save_state( [
		'ai_config' => [ 'provider' => 'test', 'model' => 'test-model', 'has_credentials' => true ],
		'client_data' => [ 'company_name' => 'Test Company' ],
	] );
	$builder = new Step_Landing_Page_Builder( new Logger(), $sm );
	$start_result = $builder->run( [ 'landing_action' => 'start', 'landings' => [ make_landing_payload_item( 'lk_a', 'A' ), make_landing_payload_item( 'lk_b', 'B' ) ], 'replace_canonical' => [] ] );

	// Check run state after start.
	$state_after = $sm->get_state();
	$run_after = $state_after['landing_run'] ?? null;

	// If start processed both items (run completed), start a 3-item run.
	if ( null === $run_after || 'completed' === ( $run_after['status'] ?? '' ) ) {
		reset_state();
		$sm = new State_Manager();
		$sm->save_state( [
			'ai_config' => [ 'provider' => 'test', 'model' => 'test-model', 'has_credentials' => true ],
			'client_data' => [ 'company_name' => 'Test Company' ],
		] );
		$builder = new Step_Landing_Page_Builder( new Logger(), $sm );
		$builder->run( [ 'landing_action' => 'start', 'landings' => [
			make_landing_payload_item( 'lk_a', 'Alpha' ),
			make_landing_payload_item( 'lk_b', 'Beta' ),
			make_landing_payload_item( 'lk_c', 'Gamma' ),
			make_landing_payload_item( 'lk_d', 'Delta' ),
		], 'replace_canonical' => [] ] );
		$state_after = $sm->get_state();
		$run_after = $state_after['landing_run'] ?? null;
	}

	$t->assert( null !== $run_after && 'completed' !== ( $run_after['status'] ?? '' ), 'Run exists and is not completed after start' );

	// Manually acquire lease to simulate active worker.
	$orch = new Landing_Run_Orchestrator( $sm, new Logger() );
	$owner = $orch->acquire_lease();
	$t->assert( ! is_wp_error( $owner ), 'First worker acquires lease' . ( is_wp_error( $owner ) ? ': ' . $owner->get_error_message() : '' ) );

	if ( is_wp_error( $owner ) ) {
		echo "  Skipping remaining concurrent tests (no run available)\n";
	} else {
		$active_item = $orch->get_next_item();
		if ( is_array( $active_item ) ) {
			$orch->mark_item_running( (string) $active_item['key'] );
			$orch->recover_stale_lease();
			$polled_run  = $orch->get_run();
			$polled_item = null;
			foreach ( $polled_run['items'] as $candidate ) {
				if ( $candidate['key'] === $active_item['key'] ) { $polled_item = $candidate; break; }
			}
			$t->assertEqual( 'running', $polled_item['status'], 'State polling does not interrupt an item with an active lease' );
		}

		// Second worker — should be rejected.
		$owner2 = $orch->acquire_lease();
		$t->assert( is_wp_error( $owner2 ), 'Second worker is rejected' );
		$orch->release_lease( 'not-the-owner' );
		$owner_after_wrong_release = $orch->acquire_lease();
		$t->assert( is_wp_error( $owner_after_wrong_release ), 'Non-owner cannot release the active lease' );

		// Release and verify third can acquire.
		$orch->release_lease( (string) $owner );
		$owner3 = $orch->acquire_lease();
		$t->assert( ! is_wp_error( $owner3 ), 'Third worker acquires after release' );
		$orch->release_lease( (string) $owner );
		$owner_after_stale_release = $orch->acquire_lease();
		$t->assert( is_wp_error( $owner_after_stale_release ), 'Stale owner cannot delete a replacement lease' );
		$orch->release_lease( (string) $owner3 );
	}

	// === TEST 7: Post-created-before-checkpoint reconciles via public process ===
	echo "\nTest 7: Post-created-before-checkpoint reconciles\n";
	reset_state();
	$sm = new State_Manager();
	$sm->save_state( [
		'ai_config' => [ 'provider' => 'test', 'model' => 'test-model', 'has_credentials' => true ],
		'client_data' => [ 'company_name' => 'Test Company' ],
	] );
	$builder = new Step_Landing_Page_Builder( new Logger(), $sm );

	// Create a 2-item run so start processes one and leaves one pending.
	$builder->run( [ 'landing_action' => 'start', 'landings' => [
		make_landing_payload_item( 'lk_rec', 'Reconcile Landing' ),
		make_landing_payload_item( 'lk_pend', 'Pending Landing' ),
	], 'replace_canonical' => [] ] );

	// Model the post-fatal state: lk_rec was processed by start (completed),
	// but we simulate a fatal before checkpoint by:
	// 1. Reset lk_rec to interrupted with post_id=0.
	// 2. Keep the post in the DB (it was created by the stub wp_insert_post).
	// 3. Remove it from landing_pages (no checkpoint happened).
	// 4. Expire any mutex.
	$state = $sm->get_state();
	$run = $state['landing_run'];

	// Find lk_rec and mark it interrupted.
	$orch = new Landing_Run_Orchestrator( $sm, new Logger() );
	$orch->mark_item_error( 'lk_rec', 'interrupted', 'fatal_before_checkpoint', 'Process died before checkpoint' );

	// Clear post_id and remove from landing_pages.
	$state = $sm->get_state();
	foreach ( $state['landing_run']['items'] as &$item ) {
		if ( 'lk_rec' === $item['key'] ) {
			$item['post_id'] = 0;
			$item['id'] = 0;
			break;
		}
	}
	unset( $item );
	$state['landing_pages'] = []; // No checkpoint happened.
	$state['landing_run']['status'] = 'interrupted';
	$sm->save_state( $state );

	// Expire any mutex.
	$option_name = 'rms_landing_lease_' . sanitize_key( $state['landing_run']['run_id'] );
	delete_option( $option_name );

	// Now invoke public process — it should find the existing WP_Post by slug and reconcile.
	$posts_before = count( $GLOBALS['_posts'] );
	$rec_slug     = '';
	foreach ( $state['landing_run']['items'] as $item ) {
		if ( 'lk_rec' === ( $item['key'] ?? '' ) ) {
			$rec_slug = (string) ( $item['slug'] ?? '' );
			break;
		}
	}
	$existing_post = '' !== $rec_slug ? get_page_by_path( $rec_slug ) : null;
	$t->assert( $existing_post instanceof \WP_Post, 'Reconciliation target WP_Post exists before process' );
	$result = $builder->run( [ 'landing_action' => 'process' ] );
	$t->assert( ! is_wp_error( $result ), 'Process reconciles without error' . ( is_wp_error( $result ) ? ': ' . $result->get_error_message() : '' ) );
	$t->assertEqual( $posts_before, count( $GLOBALS['_posts'] ), 'Reconciliation does not create a duplicate post' );

	$state = $sm->get_state();
	$run = $state['landing_run'];
	// Find lk_rec.
	$rec_item = null;
	foreach ( $run['items'] as $item ) {
		if ( 'lk_rec' === $item['key'] ) { $rec_item = $item; break; }
	}
	$t->assertEqual( 'completed', $rec_item['status'], 'Reconciled item is completed' );
	$expected_post_id = $existing_post instanceof \WP_Post ? (int) $existing_post->ID : 0;
	$t->assertEqual( $expected_post_id, (int) ( $rec_item['post_id'] ?? 0 ), 'Reconciled item keeps the existing WP_Post ID' );

	// Verify no duplicate post creation.
	$pages = $state['landing_pages'];
	// lk_rec should be in landing_pages now (checkpointed by reconciliation).
	$rec_in_pages = false;
	foreach ( $pages as $page ) {
		if ( 'lk_rec' === ( $page['landing_key'] ?? '' ) ) { $rec_in_pages = true; break; }
	}
	$t->assert( $rec_in_pages, 'Reconciled landing is in landing_pages' );

	// === TEST 10: Final nine unique pages complete ===
	echo "\nTest 10: Final nine unique pages complete\n";
	reset_state();
	$sm = new State_Manager();
	$sm->save_state( [
		'ai_config' => [ 'provider' => 'test', 'model' => 'test-model', 'has_credentials' => true ],
		'client_data' => [ 'company_name' => 'Test Company' ],
	] );
	$builder = new Step_Landing_Page_Builder( new Logger(), $sm );
	$landings = [];
	for ( $i = 1; $i <= 9; $i++ ) {
		$landings[] = make_landing_payload_item( "lk_{$i}", "Landing {$i}" );
	}
	$result = $builder->run( [ 'landing_action' => 'start', 'landings' => $landings, 'replace_canonical' => [] ] );
	for ( $req = 2; $req <= 9; $req++ ) {
		$result = $builder->run( [ 'landing_action' => 'process' ] );
	}
	$state = $sm->get_state();
	$page_ids = [];
	foreach ( $state['landing_pages'] as $page ) {
		$page_ids[] = (int) ( $page['id'] ?? 0 );
	}
	$t->assertEqual( 9, count( $page_ids ), 'Nine landing pages were checkpointed' );
	$t->assertEqual( 9, count( array_unique( $page_ids ) ), 'Nine landing pages have unique post IDs' );
	$t->assertEqual( 'completed', $state['landing_run']['status'] ?? '', 'Nine-item run completed' );

	// === TEST 11: Provider error attribution through public process ===
	echo "\nTest 11: Provider error attribution through public process\n";
	reset_state();
	$sm = new State_Manager();
	$sm->save_state( [
		'ai_config' => [ 'provider' => 'test', 'model' => 'test-model', 'has_credentials' => true ],
		'client_data' => [ 'company_name' => 'Test Company' ],
	] );
	$GLOBALS['_ai_fail'] = true;
	$builder = new Step_Landing_Page_Builder( new Logger(), $sm );
	$failed_item = make_landing_payload_item( 'lk_err', 'Error Landing' );
	$failed_item['sections'] = [ [ 'layout' => 'hero' ] ];
	$result = $builder->run( [ 'landing_action' => 'start', 'landings' => [ $failed_item ], 'replace_canonical' => [] ] );
	$t->assert( is_wp_error( $result ), 'Public start/process returns WP_Error on provider failure' );
	$t->assertEqual( 'rms_wizard_landing_keyword_ai_failed', $result->get_error_code(), 'Public process uses keyword AI error code' );
	$error_data = $result->get_error_data();
	$t->assert( is_array( $error_data ) && isset( $error_data['attribution'] ), 'Public process attaches attribution' );
	$t->assert( false !== strpos( (string) ( $error_data['attribution'] ?? '' ), 'provider_error' ), 'Attribution names provider_error' );
	$t->assert( false !== strpos( (string) ( $error_data['attribution'] ?? '' ), 'test' ), 'Attribution names the provider' );
	$orch = new Landing_Run_Orchestrator( $sm, new Logger() );
	$run = $orch->get_run();
	$t->assertEqual( 'failed', $run['items'][0]['status'] ?? '', 'Failed item status is persisted' );
	$t->assertEqual( 'rms_wizard_landing_keyword_ai_failed', $run['items'][0]['error_code'] ?? '', 'Provider error code remains on the persisted item' );
	$t->assertEqual( 'failed', $run['status'] ?? '', 'Run status is failed (not interrupted)' );
	$original_run_id = (string) ( $run['run_id'] ?? '' );

	$partial = $builder->run( [ 'landing_action' => 'start', 'landings' => [ make_landing_payload_item( 'lk_partial', 'Partial Form' ) ], 'replace_canonical' => [] ] );
	$t->assert( is_wp_error( $partial ), 'start refuses to replace a failed run from a partial form' );
	$t->assertEqual( 'rms_wizard_landing_run_active', $partial->get_error_code(), 'Partial start uses run_active code' );
	$after_partial = $orch->get_run();
	$t->assertEqual( $original_run_id, (string) ( $after_partial['run_id'] ?? '' ), 'Failed run identity is preserved' );
	$t->assertEqual( 'lk_err', $after_partial['items'][0]['key'] ?? '', 'Persisted plan still has the original failed item' );
	$t->assertEqual( 'rms_wizard_landing_keyword_ai_failed', $after_partial['items'][0]['error_code'] ?? '', 'Provider error stays visible until Resume' );

	$resume_fail = $builder->run( [ 'landing_action' => 'process' ] );
	$t->assert( is_wp_error( $resume_fail ), 'Explicit Resume retries the same failed item while the provider is still down' );
	$t->assertEqual( 'lk_err', $orch->get_run()['items'][0]['key'] ?? '', 'Resume retries the same item key' );
	$t->assertEqual( 1, (int) ( $orch->get_run()['total'] ?? 0 ), 'Resume does not rebuild a new plan' );

	$GLOBALS['_ai_fail'] = false;
	$state = $sm->get_state();
	foreach ( $state['landing_run']['items'] as &$item ) {
		if ( 'lk_err' === ( $item['key'] ?? '' ) ) {
			$item['sections'] = [
				[ 'layout' => 'badges' ],
				[ 'layout' => 'portfolio-v1' ],
			];
		}
	}
	unset( $item );
	$sm->save_state( $state );
	$resume_ok = $builder->run( [ 'landing_action' => 'process' ] );
	$t->assert( ! is_wp_error( $resume_ok ), 'Resume succeeds for the same item after it is processable again' . ( is_wp_error( $resume_ok ) ? ': ' . $resume_ok->get_error_message() : '' ) );
	$t->assertEqual( 'lk_err', $orch->get_run()['items'][0]['key'] ?? '', 'Successful Resume still targets the original item key' );
	$t->assertEqual( 'completed', $orch->get_run()['items'][0]['status'] ?? '', 'Same item completes after explicit Resume' );
	$t->assertEqual( '', $orch->get_run()['items'][0]['error_code'] ?? 'missing', 'Successful checkpoint clears provider error attribution' );

	// === TEST 12: Failed item prevents finalize ===
	echo "\nTest 12: Failed item prevents finalize\n";
	reset_state();
	$sm = new State_Manager();
	$sm->save_state( [ 'ai_config' => [ 'provider' => 'test', 'model' => 'test', 'has_credentials' => true ], 'client_data' => [ 'company_name' => 'Test' ] ] );
	$orch = new Landing_Run_Orchestrator( $sm, new Logger() );
	$orch->start_run( [ [ 'landing_key' => 'lk_e', 'id' => 0, 'title' => 'E', 'slug' => 'e', 'landing_type' => 'seo', 'menu_eligible' => true, 'primary_keyword' => 'kw', 'subkeywords' => [], 'sections' => [ [ 'layout' => 'badges' ] ] ] ], [], [] );
	$orch->mark_item_running( 'lk_e' );
	$orch->mark_item_error( 'lk_e', 'failed', 'rms_wizard_landing_keyword_ai_failed', 'AI generation failed' );
	$t->assert( ! $orch->is_run_complete(), 'Run with failed item is not complete' );

	// === TEST 13: Skip-all ===
	echo "\nTest 13: Skip-all via public run\n";
	reset_state();
	$sm = new State_Manager();
	$builder = new Step_Landing_Page_Builder( new Logger(), $sm );
	$orch = new Landing_Run_Orchestrator( $sm, new Logger() );
	$t->assert( $orch->allows_skip_all(), 'No-run permits skip-all' );
	$result = $builder->run( [ 'skip_all' => true ] );
	$t->assert( ! is_wp_error( $result ), 'Skip-all does not error' );
	$t->assert( ! empty( $result['skipped'] ), 'Skip-all returns skipped=true' );
	$t->assertEqual( 'complete', (string) ( $sm->get_state()['step_status']['landing-page-builder'] ?? '' ), 'No-run skip-all marks the step complete' );

	// === TEST 14: No landing_action without skip_all → 400 ===
	echo "\nTest 14: No landing_action without skip_all → 400 contract error\n";
	reset_state();
	$sm = new State_Manager();
	$builder = new Step_Landing_Page_Builder( new Logger(), $sm );
	$result = $builder->run( [ 'landings' => [ make_landing_payload_item( 'lk_x', 'X' ) ] ] );
	$t->assert( is_wp_error( $result ), 'No action returns WP_Error' );
	$t->assertEqual( 'rms_wizard_landing_action_required', $result->get_error_code(), 'Error code is landing_action_required' );

	// === TEST 15: Sections change invalidation ===
	echo "\nTest 15: Sections change invalidation\n";
	reset_state();
	$sm = new State_Manager();
	$existing = [ 'lk_1' => make_existing_entry_no_sections( 'lk_1', 'Landing 1', 101 ) ];
	// Add sections to the existing entry to test section comparison.
	$existing['lk_1']['sections'] = [ [ 'layout' => 'badges', 'item_count' => 1, 'override_canonical' => false ] ];
	$existing['lk_1']['generated_at'] = '2000-01-01 00:00:00';
	$sm->save_state( [
		'ai_config' => [ 'provider' => 'test', 'model' => 'test', 'has_credentials' => true ],
		'client_data' => [ 'company_name' => 'Test' ],
		'landing_pages' => array_values( $existing ),
	] );

	// Register the existing post so build_one_landing can update it.
	$GLOBALS['_posts'][101] = [
		'ID'           => 101,
		'post_type'    => 'page',
		'post_title'   => 'Landing 1',
		'post_name'    => 'landing-1',
		'post_status'  => 'publish',
		'post_content' => 'existing landing content',
	];
	$GLOBALS['_pages_by_slug']['landing-1'] = new \WP_Post( [
		'ID'           => 101,
		'post_type'    => 'page',
		'post_title'   => 'Landing 1',
		'post_name'    => 'landing-1',
		'post_status'  => 'publish',
		'post_content' => 'existing landing content',
	] );
	$GLOBALS['_post_meta'][101]['rms_landing_type'] = 'seo';
	$GLOBALS['_post_meta'][101]['_wp_page_template'] = 'pages/landing-page.php';

	$builder = new Step_Landing_Page_Builder( new Logger(), $sm );

	// Submit with different sections (non-keyword layouts to avoid AI).
	$landings = [ make_landing_payload_item( 'lk_1', 'Landing 1', 101 ) ];
	$landings[0]['sections'] = [ [ 'layout' => 'badges' ], [ 'layout' => 'testimonials-v1' ] ];

	$result = $builder->run( [ 'landing_action' => 'start', 'landings' => $landings, 'replace_canonical' => [] ] );
	if ( is_wp_error( $result ) ) {
		$t->assert( false, 'Sections change test failed: ' . $result->get_error_message() );
	} else {
		// The item had changed sections, so it should NOT be classified as completed at plan time.
		// After start, the first pending item is processed (completed=1).
		// But we can verify the item was NOT pre-classified as completed by checking
		// that it went through the build path (post_id > 0 in landing_pages).
		$state = $sm->get_state();
		$lk1_item = null;
		foreach ( $state['landing_run']['items'] as $item ) {
			if ( 'lk_1' === $item['key'] ) { $lk1_item = $item; break; }
		}
		// If the item was classified as completed at plan time (unchanged),
		// it would have post_id=101 and completed_at set but NO entry in landing_pages
		// beyond what was already there. If it was pending and processed,
		// it would have been built and checkpointed.
		// The key indicator: was the item pending at plan time?
		// Since start processes it, we can't directly check. But we can verify
		// the item went through build by checking if landing_pages has a fresh entry.
		$t->assert( null !== $lk1_item, 'Item lk_1 found in run plan' );

		// Verify the plan had the item as pending (not pre-completed) by checking
		// that the run needed processing. If it was unchanged, start would not
		// have called process and completed would be 1 with status=completed.
		// If it was changed, start would process it and completed would be 1
		// but with a new post_id (not 101).
		$t->assert( (int) $lk1_item['post_id'] > 0, 'Item was processed (has post_id)' );
		$t->assert( 'completed' === $lk1_item['status'], 'Item is completed after processing' );
		$t->assertEqual( 2, count( $lk1_item['sections'] ?? [] ), 'Plan stored the changed two-section list' );
		$fresh_generated_at = '';
		foreach ( $state['landing_pages'] as $page ) {
			if ( 'lk_1' === ( $page['landing_key'] ?? '' ) ) {
				$fresh_generated_at = (string) ( $page['generated_at'] ?? '' );
				break;
			}
		}
		$t->assert( '2000-01-01 00:00:00' !== $fresh_generated_at && '' !== $fresh_generated_at, 'Changed sections rebuilt the landing entry instead of pre-completing it' );
		$t->assert( in_array( 101, $GLOBALS['_updated_posts'], true ) || (int) $lk1_item['post_id'] !== 101, 'Section invalidation produced an observable rebuild write' );
	}

	// === TEST 16: Reload-safe public view ===
	echo "\nTest 16: Reload-safe public view\n";
	reset_state();
	$sm = new State_Manager();
	$sm->save_state( [ 'ai_config' => [ 'provider' => 'test', 'model' => 'test', 'has_credentials' => true ], 'client_data' => [ 'company_name' => 'Test' ] ] );
	$builder = new Step_Landing_Page_Builder( new Logger(), $sm );
	$builder->run( [ 'landing_action' => 'start', 'landings' => [
		make_landing_payload_item( 'lk_a', 'Alpha' ),
		make_landing_payload_item( 'lk_b', 'Beta' ),
	], 'replace_canonical' => [] ] );

	// Simulate reload.
	$sm2 = new State_Manager();
	$builder2 = new Step_Landing_Page_Builder( new Logger(), $sm2 );
	// The state already has the run plan. Call process to see it hydrates.
	$result = $builder2->run( [ 'landing_action' => 'process' ] );
	$t->assert( ! is_wp_error( $result ), 'Process after reload succeeds' );
	$t->assert( isset( $result['landing_run']['items'] ), 'Public view has items' );
	$t->assertEqual( 2, count( $result['landing_run']['items'] ), 'All 2 items in public view' );
	$t->assert( ! array_key_exists( 'lease_owner', $result['landing_run'] ), 'Public process view omits lease_owner' );

	// === TEST 17: Active run cannot be replaced by start ===
	echo "\nTest 17: Active run cannot be replaced by start\n";
	reset_state();
	$sm = new State_Manager();
	$sm->save_state( [
		'ai_config' => [ 'provider' => 'test', 'model' => 'test-model', 'has_credentials' => true ],
		'client_data' => [ 'company_name' => 'Test Company' ],
	] );
	$builder = new Step_Landing_Page_Builder( new Logger(), $sm );
	$builder->run( [ 'landing_action' => 'start', 'landings' => [
		make_landing_payload_item( 'lk_keep_1', 'Keep One' ),
		make_landing_payload_item( 'lk_keep_2', 'Keep Two' ),
		make_landing_payload_item( 'lk_keep_3', 'Keep Three' ),
	], 'replace_canonical' => [] ] );
	$orch = new Landing_Run_Orchestrator( $sm, new Logger() );
	$original_run_id = (string) ( $orch->get_run()['run_id'] ?? '' );
	$owner = $orch->acquire_lease();
	$t->assert( ! is_wp_error( $owner ), 'Worker holds a valid lease before start overwrite attempt' );
	$start_again = $builder->run( [ 'landing_action' => 'start', 'landings' => [
		make_landing_payload_item( 'lk_other', 'Other' ),
	], 'replace_canonical' => [] ] );
	$t->assert( is_wp_error( $start_again ), 'start refuses to overwrite an active run' );
	$t->assertEqual( 'rms_wizard_landing_run_active', $start_again->get_error_code(), 'start overwrite uses run_active code' );
	$after = $orch->get_run();
	$t->assertEqual( $original_run_id, (string) ( $after['run_id'] ?? '' ), 'Active run_id is unchanged after start' );
	$t->assertEqual( 3, (int) ( $after['total'] ?? 0 ), 'Original 3-item plan is preserved' );
	$step_status = $sm->get_state()['step_status']['landing-page-builder'] ?? '';
	$t->assertEqual( 'running', $step_status, 'Blocked start does not mark the step failed' );
	$orch->release_lease( (string) $owner );

	// === TEST 18: Process lease conflict preserves running ===
	echo "\nTest 18: Process lease conflict preserves running status\n";
	$owner = $orch->acquire_lease();
	$t->assert( ! is_wp_error( $owner ), 'First worker re-acquires lease' );
	$conflict = $builder->run( [ 'landing_action' => 'process' ] );
	$t->assert( is_wp_error( $conflict ), 'Second process is rejected' );
	$t->assertEqual( 'rms_wizard_landing_lease_active', $conflict->get_error_code(), 'Conflict code is lease_active' );
	$state = $sm->get_state();
	$t->assertEqual( 'running', $state['step_status']['landing-page-builder'] ?? '', 'Lease conflict keeps step status running' );
	$t->assertEqual( $original_run_id, (string) ( $state['landing_run']['run_id'] ?? '' ), 'Lease conflict does not replace the run' );
	$public = $orch->get_public_run();
	$t->assertEqual( true, $public['processing_active'] ?? false, 'Active polling reports processing_active' );
	$t->assert( ! array_key_exists( 'lease_owner', $public ), 'Public state strips lease_owner' );
	$raw = $orch->get_run();
	$t->assert( isset( $raw['lease_owner'] ), 'Internal run still stores lease_owner' );
	$t->assertEqual( 'running', $raw['status'] ?? '', 'Active polling keeps run status running' );
	$orch->release_lease( (string) $owner );

	// === TEST 19: Persist complete plan before canonical bootstrap ===
	echo "\nTest 19: Persist complete plan before canonical bootstrap\n";
	$source = (string) file_get_contents( __DIR__ . '/../inc/wizard/class-step-landing-page-builder.php' );
	$start_fn = strpos( $source, 'function orchestrate_start' );
	$t->assert( false !== $start_fn, 'orchestrate_start is present' );
	if ( false !== $start_fn ) {
		$chunk = substr( $source, $start_fn, 3500 );
		$persist_at = strpos( $chunk, 'start_run(' );
		$bootstrap_at = strpos( $chunk, 'ensure_canonical_reusables(' );
		$t->assert( false !== $persist_at && false !== $bootstrap_at && $persist_at < $bootstrap_at, 'start_run persists the plan before ensure_canonical_reusables' );
	}

	// === TEST 20: Public view after 4/9 keeps pending plan rows ===
	echo "\nTest 20: Public view after 4/9 keeps pending plan rows\n";
	reset_state();
	$sm = new State_Manager();
	$sm->save_state( [
		'ai_config' => [ 'provider' => 'test', 'model' => 'test-model', 'has_credentials' => true ],
		'client_data' => [ 'company_name' => 'Test Company' ],
	] );
	$builder = new Step_Landing_Page_Builder( new Logger(), $sm );
	$landings = [];
	for ( $i = 1; $i <= 9; $i++ ) {
		$landings[] = make_landing_payload_item( "lk_{$i}", "Landing {$i}" );
	}
	$builder->run( [ 'landing_action' => 'start', 'landings' => $landings, 'replace_canonical' => [] ] );
	for ( $i = 2; $i <= 4; $i++ ) {
		$builder->run( [ 'landing_action' => 'process' ] );
	}
	$public = ( new Landing_Run_Orchestrator( $sm, new Logger() ) )->get_public_run();
	$t->assertEqual( 9, count( $public['items'] ?? [] ), 'Public view still has all 9 plan rows after 4 completions' );
	$t->assertEqual( 4, (int) ( $public['completed'] ?? 0 ), 'Four items are completed' );
	$pending = 0;
	foreach ( $public['items'] as $item ) {
		if ( 'pending' === ( $item['status'] ?? '' ) ) {
			$pending++;
		}
	}
	$t->assertEqual( 5, $pending, 'Five pending plan rows remain visible for hydration' );

	// === TEST 21: Found post + failed finalization never inserts a duplicate ===
	echo "\nTest 21: Reconciliation finalization failure does not call wp_insert_post\n";
	reset_state();
	$sm = new State_Manager();
	$sm->save_state( [
		'ai_config' => [ 'provider' => 'test', 'model' => 'test-model', 'has_credentials' => true ],
		'client_data' => [ 'company_name' => 'Test Company' ],
	] );
	$builder = new Step_Landing_Page_Builder( new Logger(), $sm );
	$builder->run( [ 'landing_action' => 'start', 'landings' => [
		make_landing_payload_item( 'lk_dup', 'Duplicate Guard' ),
		make_landing_payload_item( 'lk_hold', 'Hold Second' ),
	], 'replace_canonical' => [] ] );
	$state = $sm->get_state();
	$orch = new Landing_Run_Orchestrator( $sm, new Logger() );
	$orch->mark_item_error( 'lk_dup', 'interrupted', 'fatal_before_checkpoint', 'Process died before checkpoint' );
	$state = $sm->get_state();
	foreach ( $state['landing_run']['items'] as &$item ) {
		if ( 'lk_dup' === ( $item['key'] ?? '' ) ) {
			$item['post_id'] = 0;
			$item['id'] = 0;
			break;
		}
	}
	unset( $item );
	$state['landing_pages'] = [];
	$state['landing_run']['status'] = 'interrupted';
	$sm->save_state( $state );
	delete_option( 'rms_landing_lease_' . sanitize_key( $state['landing_run']['run_id'] ) );
	$posts_before = count( $GLOBALS['_posts'] );
	$inserts_before = (int) $GLOBALS['_insert_post_calls'];
	$GLOBALS['_fail_landing_meta'] = true;
	$result = $builder->run( [ 'landing_action' => 'process' ] );
	$GLOBALS['_fail_landing_meta'] = false;
	$t->assert( is_wp_error( $result ), 'Process records an error when found-post finalization fails' );
	$t->assertEqual( $posts_before, count( $GLOBALS['_posts'] ), 'Failed finalization does not create another post' );
	$t->assertEqual( $inserts_before, (int) $GLOBALS['_insert_post_calls'], 'wp_insert_post is not called after a found post fails finalization' );
	$failed_item = null;
	foreach ( $orch->get_run()['items'] as $item ) {
		if ( 'lk_dup' === ( $item['key'] ?? '' ) ) {
			$failed_item = $item;
			break;
		}
	}
	$t->assertEqual( 'failed', $failed_item['status'] ?? '', 'Found-post finalization failure marks the same item failed' );

	// === TEST 22: Concurrent starts share one plan identity; stale release cannot steal the fence ===
	echo "\nTest 22: Concurrent start fence serializes plan identity\n";
	reset_state();
	$sm = new State_Manager();
	$sm->save_state( [
		'ai_config' => [ 'provider' => 'test', 'model' => 'test-model', 'has_credentials' => true ],
		'client_data' => [ 'company_name' => 'Test Company' ],
	] );
	$orch = new Landing_Run_Orchestrator( $sm, new Logger() );
	$owner_a = $orch->acquire_start_fence();
	$t->assert( ! is_wp_error( $owner_a ), 'First starter acquires the initialization fence' );
	$owner_b = $orch->acquire_start_fence();
	$t->assert( is_wp_error( $owner_b ), 'Second starter loses the initialization fence' );
	$t->assertEqual( 'rms_wizard_landing_start_fence_active', is_wp_error( $owner_b ) ? $owner_b->get_error_code() : '', 'Fence contention uses start_fence_active' );
	$builder = new Step_Landing_Page_Builder( new Logger(), $sm );
	$blocked = $builder->run( [ 'landing_action' => 'start', 'landings' => [
		make_landing_payload_item( 'lk_race_1', 'Race One' ),
		make_landing_payload_item( 'lk_race_2', 'Race Two' ),
	], 'replace_canonical' => [] ] );
	$t->assert( is_wp_error( $blocked ), 'Public start cannot persist a plan while another starter holds the fence' );
	$t->assert( null === $orch->get_run(), 'Losing starter does not persist a run' );
	$orch->release_start_fence( 'not-the-owner' );
	$owner_after_wrong = $orch->acquire_start_fence();
	$t->assert( is_wp_error( $owner_after_wrong ), 'Non-owner cannot release the start fence' );
	$orch->release_start_fence( (string) $owner_a );
	$winner = $builder->run( [ 'landing_action' => 'start', 'landings' => [
		make_landing_payload_item( 'lk_win_1', 'Winner One' ),
		make_landing_payload_item( 'lk_win_2', 'Winner Two' ),
		make_landing_payload_item( 'lk_win_3', 'Winner Three' ),
	], 'replace_canonical' => [] ] );
	$t->assert( ! is_wp_error( $winner ), 'Winning starter persists after the fence is released' . ( is_wp_error( $winner ) ? ': ' . $winner->get_error_message() : '' ) );
	$winning_run = $orch->get_run();
	$winning_id = (string) ( $winning_run['run_id'] ?? '' );
	$t->assertEqual( 3, (int) ( $winning_run['total'] ?? 0 ), 'Winning start persisted the full three-item plan' );
	$loser = $builder->run( [ 'landing_action' => 'start', 'landings' => [ make_landing_payload_item( 'lk_other', 'Other' ) ], 'replace_canonical' => [] ] );
	$t->assert( is_wp_error( $loser ), 'Second start cannot replace the winning run identity' );
	$t->assertEqual( $winning_id, (string) ( $orch->get_run()['run_id'] ?? '' ), 'Only one run identity remains' );
	$t->assertEqual( 3, (int) ( $orch->get_run()['total'] ?? 0 ), 'Original plan is not replaced by a later start' );
	$replacement = $orch->acquire_start_fence();
	$t->assert( ! is_wp_error( $replacement ), 'A later starter can acquire a free start fence' );
	$orch->release_start_fence( (string) $owner_a );
	$after_stale = $orch->acquire_start_fence();
	$t->assert( is_wp_error( $after_stale ), 'Stale/non-owner release cannot clear replacement start-fence ownership' );
	$orch->release_start_fence( (string) $replacement );

	// === TEST 23: Skip-all refuses live/incomplete runs on service + REST paths ===
	echo "\nTest 23: Skip-all 409 guard preserves run/step status\n";
	foreach ( [ 'pending', 'running', 'interrupted', 'failed' ] as $blocking_status ) {
		reset_state();
		$sm      = new State_Manager();
		$orch    = seed_incomplete_landing_run( $sm, $blocking_status );
		$builder = new Step_Landing_Page_Builder( new Logger(), $sm );
		$before  = landing_skip_snapshot( $sm );
		$t->assert( ! $orch->allows_skip_all(), "Incomplete {$blocking_status} run does not permit skip-all" );

		$service = $builder->run( [ 'skip_all' => true ] );
		$t->assert( is_wp_error( $service ), "Service skip-all refuses {$blocking_status} run" );
		$t->assertEqual( 'rms_wizard_landing_run_active', is_wp_error( $service ) ? $service->get_error_code() : '', "Service skip-all {$blocking_status} uses run_active" );
		$t->assertEqual( 409, wp_error_http_status( $service ), "Service skip-all {$blocking_status} is HTTP 409" );
		$t->assertEqual( $before, landing_skip_snapshot( $sm ), "Service skip-all {$blocking_status} preserves run/step status" );

		$start_skip = $builder->run( [ 'landing_action' => 'start', 'skip_all' => true ] );
		$t->assert( is_wp_error( $start_skip ), "start+skip_all refuses {$blocking_status} run" );
		$t->assertEqual( 409, wp_error_http_status( $start_skip ), "start+skip_all {$blocking_status} is HTTP 409" );
		$t->assertEqual( $before, landing_skip_snapshot( $sm ), "start+skip_all {$blocking_status} preserves run/step status" );

		$rest = rest_skip_all( $sm );
		$t->assert( is_wp_error( $rest ), "REST skip-all refuses {$blocking_status} run" );
		$t->assertEqual( 'rms_wizard_landing_run_active', is_wp_error( $rest ) ? $rest->get_error_code() : '', "REST skip-all {$blocking_status} uses run_active" );
		$t->assertEqual( 409, wp_error_http_status( $rest ), "REST skip-all {$blocking_status} is HTTP 409" );
		$t->assertEqual( $before, landing_skip_snapshot( $sm ), "REST skip-all {$blocking_status} preserves run/step status" );
	}

	reset_state();
	$sm   = new State_Manager();
	$orch = seed_incomplete_landing_run( $sm, 'running' );
	$owner = $orch->acquire_lease();
	$t->assert( ! is_wp_error( $owner ), 'Active-lease fixture acquires a lease' );
	$builder = new Step_Landing_Page_Builder( new Logger(), $sm );
	$before  = landing_skip_snapshot( $sm );
	$service = $builder->run( [ 'skip_all' => true ] );
	$t->assert( is_wp_error( $service ) && 409 === wp_error_http_status( $service ), 'Service skip-all refuses an active lease' );
	$t->assertEqual( $before, landing_skip_snapshot( $sm ), 'Active-lease skip-all preserves run/step status' );
	$rest = rest_skip_all( $sm );
	$t->assert( is_wp_error( $rest ) && 409 === wp_error_http_status( $rest ), 'REST skip-all refuses an active lease' );
	$t->assertEqual( $before, landing_skip_snapshot( $sm ), 'REST active-lease skip-all preserves run/step status' );
	$orch->release_lease( (string) $owner );

	reset_state();
	$sm   = new State_Manager();
	$orch = seed_incomplete_landing_run( $sm, 'completed' );
	$state = $sm->get_state();
	$state['landing_run']['items'][0]['status'] = 'completed';
	$state['landing_run']['status'] = 'completed';
	$state['step_status']['landing-page-builder'] = 'complete';
	$sm->save_state( $state );
	$t->assert( $orch->allows_skip_all(), 'Completed run permits skip-all' );
	$builder = new Step_Landing_Page_Builder( new Logger(), $sm );
	$completed_skip = $builder->run( [ 'skip_all' => true ] );
	$t->assert( ! is_wp_error( $completed_skip ), 'Skip-all succeeds against a completed run' . ( is_wp_error( $completed_skip ) ? ': ' . $completed_skip->get_error_message() : '' ) );
	$t->assert( ! empty( $completed_skip['skipped'] ), 'Completed-run skip-all still returns skipped=true' );

	reset_state();
	$sm   = new State_Manager();
	$orch = seed_incomplete_landing_run( $sm, 'skipped' );
	$state = $sm->get_state();
	$state['landing_run']['status'] = 'skipped';
	$state['landing_run']['items'][0]['status'] = 'completed';
	$sm->save_state( $state );
	$t->assert( $orch->allows_skip_all(), 'Skipped run permits skip-all' );
	$skipped_skip = ( new Step_Landing_Page_Builder( new Logger(), $sm ) )->run( [ 'skip_all' => true ] );
	$t->assert( ! is_wp_error( $skipped_skip ), 'Skip-all succeeds against a skipped run' . ( is_wp_error( $skipped_skip ) ? ': ' . $skipped_skip->get_error_message() : '' ) );

	reset_state();
	$sm = new State_Manager();
	$rest_ok = rest_skip_all( $sm );
	$t->assert( ! is_wp_error( $rest_ok ), 'REST skip-all succeeds when no run exists' . ( is_wp_error( $rest_ok ) ? ': ' . $rest_ok->get_error_message() : '' ) );
	$t->assert( ! empty( $rest_ok->data['result']['skipped'] ), 'REST no-run skip-all returns skipped in the production envelope' );
	$t->assertEqual( 200, (int) $rest_ok->status, 'REST no-run skip-all is HTTP 200' );

	// === TEST 24: Site-wide mutation fence serializes all mutating execute_step() work ===
	echo "\nTest 24: Mutation fence serializes landing vs Home/Generate/Menu/IA and start vs skip-all\n";
	$t->assert( Wizard_Mutation_Fence::TTL > Wizard_Mutation_Fence::EXECUTION_BUDGET + Wizard_Mutation_Fence::MARGIN, 'Fence TTL is strictly greater than max PHP budget plus margin' );
	$t->assertEqual( 'rms_wizard_mutation_fence', Wizard_Mutation_Fence::OPTION_NAME, 'Fence option name is fixed' );

	reset_state();
	$sm    = new State_Manager();
	$sm->save_state( [
		'current_step' => 'menu-setup',
		'step_status'  => [ 'menu-setup' => 'complete', 'landing-page-builder' => 'pending' ],
		'landing_run'  => null,
		'ai_config'    => [ 'provider' => 'test', 'model' => 'test', 'has_credentials' => true ],
	] );
	$fence = new Wizard_Mutation_Fence();
	$owner = $fence->acquire();
	$t->assert( ! is_wp_error( $owner ) && is_string( $owner ) && '' !== $owner, 'First mutation fence acquire succeeds' );
	$t->assert( $fence->is_held(), 'Acquired mutation fence is held' );

	$contender = $fence->acquire();
	$t->assert( is_wp_error( $contender ), 'Second mutation-fence contender is rejected' );
	$t->assertEqual( 'rms_wizard_busy', is_wp_error( $contender ) ? $contender->get_error_code() : '', 'Fence contention uses rms_wizard_busy' );
	$t->assertEqual( 409, wp_error_http_status( $contender ), 'Fence contention is HTTP 409' );
	$t->assert( ! error_exposes_owner( $contender ), 'Fence conflict does not expose an owner token' );

	$controller = new Step_Controller( $sm, new Logger() );
	$before     = wizard_identity_snapshot( $sm );
	$blocked_steps = [
		'home-page-builder'    => [],
		'generate-pages'       => [ 'pages' => [ 'home' ] ],
		'menu-setup'           => [ 'primary' => [ 1 ], 'confirm_cleanup' => true ],
		'ia-generation'        => [ 'provider' => 'test', 'model' => 'test' ],
		'landing-page-builder' => [ 'landing_action' => 'start', 'landings' => [ make_landing_payload_item( 'lk_fence', 'Fence' ) ] ],
	];
	foreach ( $blocked_steps as $blocked_step => $payload ) {
		$result = $controller->execute_step( $blocked_step, $payload );
		$t->assert( is_wp_error( $result ), "execute_step {$blocked_step} cannot run while a landing process fence is held" );
		$t->assertEqual( 'rms_wizard_busy', is_wp_error( $result ) ? $result->get_error_code() : '', "execute_step {$blocked_step} fence conflict uses rms_wizard_busy" );
		$t->assertEqual( 409, wp_error_http_status( $result ), "execute_step {$blocked_step} fence conflict is HTTP 409" );
		$t->assertEqual( $before, wizard_identity_snapshot( $sm ), "execute_step {$blocked_step} fence conflict mutates no current_step/step_status/run" );
		$t->assert( ! error_exposes_owner( $result ), "execute_step {$blocked_step} fence conflict does not expose owner" );
	}

	$landing_process = $controller->execute_step( 'landing-page-builder', [ 'landing_action' => 'process' ] );
	$t->assert( is_wp_error( $landing_process ) && 409 === wp_error_http_status( $landing_process ), 'Landing process cannot run while a normal-step fence is held' );
	$t->assertEqual( $before, wizard_identity_snapshot( $sm ), 'Landing process fence conflict mutates no current_step/step_status/run' );

	$skip_during_fence = $controller->execute_step( 'landing-page-builder', [ 'skip_all' => true ] );
	$t->assert( is_wp_error( $skip_during_fence ) && 409 === wp_error_http_status( $skip_during_fence ), 'Controller fence rejects skip-all during an in-flight start/process' );
	$t->assertEqual( $before, wizard_identity_snapshot( $sm ), 'Controller skip-all fence conflict is byte-identical for run/step state' );
	$t->assertEqual( 'pending', (string) ( $sm->get_state()['step_status']['landing-page-builder'] ?? '' ), 'Fence conflict cannot paint landing failed' );

	$fence->release( 'not-the-owner' );
	$after_wrong = $fence->acquire();
	$t->assert( is_wp_error( $after_wrong ), 'Non-owner cannot release the mutation fence' );

	$fence->release( (string) $owner );
	$t->assert( ! $fence->is_held(), 'Exact owner releases the mutation fence' );

	$replacement = $fence->acquire();
	$t->assert( ! is_wp_error( $replacement ), 'A later caller can acquire a free mutation fence' );
	$fence->release( (string) $owner );
	$after_stale = $fence->acquire();
	$t->assert( is_wp_error( $after_stale ), 'Stale owner cannot release a replacement mutation fence' );
	$fence->release( (string) $replacement );

	$expired_owner = 'expired-owner-token';
	$GLOBALS['_options'][ Wizard_Mutation_Fence::OPTION_NAME ] = [
		'owner'       => $expired_owner,
		'acquired_at' => time() - 2000,
		'expires_at'  => time() - 10,
	];
	$recovered = $fence->acquire();
	$t->assert( ! is_wp_error( $recovered ), 'Expiry recovers a stale mutation fence' );
	$fence->release( $expired_owner );
	$after_expired_release = $fence->acquire();
	$t->assert( is_wp_error( $after_expired_release ), 'Expired owner cannot release the recovered replacement fence' );
	$fence->release( (string) $recovered );

	reset_state();
	$sm   = new State_Manager();
	$orch = new Landing_Run_Orchestrator( $sm, new Logger() );
	$sm->save_state( [
		'current_step' => 'landing-page-builder',
		'step_status'  => [ 'landing-page-builder' => 'pending' ],
	] );
	$start_owner = $orch->acquire_start_fence();
	$t->assert( ! is_wp_error( $start_owner ), 'Start-fence fixture acquires' );
	$t->assert( $orch->has_active_start_fence(), 'has_active_start_fence is true while the start fence is held' );
	$t->assert( ! $orch->allows_skip_all(), 'allows_skip_all treats an active start fence as blocking' );
	$builder = new Step_Landing_Page_Builder( new Logger(), $sm );
	$before  = wizard_identity_snapshot( $sm );
	$service = $builder->run( [ 'skip_all' => true ] );
	$t->assert( is_wp_error( $service ), 'Start-fence guard rejects skip-all' );
	$t->assertEqual( 'rms_wizard_landing_run_active', is_wp_error( $service ) ? $service->get_error_code() : '', 'Start-fence skip-all uses run_active' );
	$t->assertEqual( 409, wp_error_http_status( $service ), 'Start-fence skip-all is HTTP 409' );
	$t->assertEqual( $before, wizard_identity_snapshot( $sm ), 'Start-fence skip-all preserves byte-identical run/step state' );
	$start_skip = $builder->run( [ 'landing_action' => 'start', 'skip_all' => true ] );
	$t->assert( is_wp_error( $start_skip ) && 409 === wp_error_http_status( $start_skip ), 'start+skip_all is rejected while the start fence is held' );
	$t->assertEqual( $before, wizard_identity_snapshot( $sm ), 'start+skip_all start-fence conflict preserves run/step state' );
	$orch->release_start_fence( (string) $start_owner );
	$t->assert( $orch->allows_skip_all(), 'Skip-all is permitted after the start fence is released' );

	// === TEST 25: Finalize pages, /complete fence, GET stale recovery serialization ===
	echo "\nTest 25: Finalize response pages, complete conflict, GET stale recovery, execute_step run state\n";

	reset_state();
	$sm = new State_Manager();
	$sm->save_state( [
		'ai_config'   => [ 'provider' => 'test', 'model' => 'test-model', 'has_credentials' => true ],
		'client_data' => [ 'company_name' => 'Test Company' ],
	] );
	$controller = new Step_Controller( $sm, new Logger() );
	$rest       = new Rest_Controller( $controller );
	$start_req  = new \WP_REST_Request( [
		'step'           => 'landing-page-builder',
		'landing_action' => 'start',
		'landings'       => [
			make_landing_payload_item( 'lk_final_a', 'Final One' ),
			make_landing_payload_item( 'lk_final_b', 'Final Two' ),
		],
		'replace_canonical' => [],
	] );
	$start_req->json = [
		'landing_action'    => 'start',
		'landings'          => [
			make_landing_payload_item( 'lk_final_a', 'Final One' ),
			make_landing_payload_item( 'lk_final_b', 'Final Two' ),
		],
		'replace_canonical' => [],
	];
	$start_res = $rest->run_step( $start_req );
	$t->assert(
		$start_res instanceof \WP_REST_Response,
		'execute_step start returns a REST envelope' . ( is_wp_error( $start_res ) ? ': ' . $start_res->get_error_code() . ' ' . $start_res->get_error_message() : '' )
	);
	$t->assert( ! empty( $start_res->data['success'] ), 'execute_step start stays success:true' );
	$t->assertEqual( 2, (int) ( $start_res->data['state']['landing_run']['total'] ?? 0 ), 'execute_step start state includes run total' );
	$t->assertEqual( 1, (int) ( $start_res->data['state']['landing_run']['completed'] ?? 0 ), 'execute_step start state includes completed count' );
	$t->assert( ! payload_exposes_owner( $start_res ), 'execute_step start response keeps owner opaque' );

	$process_req = new \WP_REST_Request( [
		'step'           => 'landing-page-builder',
		'landing_action' => 'process',
	] );
	$process_req->json = [ 'landing_action' => 'process' ];
	$final_res         = $rest->run_step( $process_req );
	$t->assert( $final_res instanceof \WP_REST_Response, 'execute_step process finalize returns a REST envelope' );
	$persisted_pages = $sm->get_state()['landing_pages'] ?? [];
	$t->assertEqual( 2, count( $persisted_pages ), 'Two landing pages persisted after finalize' );
	$t->assertEqual( 2, count( $final_res->data['result']['landing_pages'] ?? [] ), 'Finalize execute_step result includes all persisted pages' );
	$t->assertEqual( 2, count( $final_res->data['state']['landing_pages'] ?? [] ), 'Finalize execute_step state includes all persisted pages' );
	$t->assertEqual( 'completed', (string) ( $final_res->data['state']['landing_run']['status'] ?? '' ), 'execute_step finalize state keeps completed run status' );
	$t->assert( ! payload_exposes_owner( $final_res ), 'execute_step finalize response keeps owner opaque' );

	reset_state();
	$sm = new State_Manager();
	seed_required_steps_complete( $sm );
	$sm->save_state( array_merge( $sm->get_state(), [ 'current_step' => 'landing-page-builder' ] ) );
	$t->assert( ! $sm->has_completion_flag(), 'Completion flag starts unset' );
	$controller = new Step_Controller( $sm, new Logger() );
	$rest       = new Rest_Controller( $controller );
	$incomplete = $controller->complete();
	$t->assert( ! is_wp_error( $incomplete ), 'complete() succeeds when every required step is complete' . ( is_wp_error( $incomplete ) ? ': ' . $incomplete->get_error_message() : '' ) );
	$t->assert( $sm->has_completion_flag(), 'Successful complete() sets the completion flag' );
	$t->assert( ! payload_exposes_owner( $incomplete ), 'complete() success keeps owner opaque' );

	reset_state();
	$sm = new State_Manager();
	$controller = new Step_Controller( $sm, new Logger() );
	$blocked_incomplete = $controller->complete();
	$t->assert( is_wp_error( $blocked_incomplete ), 'complete() still validates all required steps' );
	$t->assertEqual( 'rms_wizard_incomplete', is_wp_error( $blocked_incomplete ) ? $blocked_incomplete->get_error_code() : '', 'Incomplete complete() uses rms_wizard_incomplete' );
	$t->assertEqual( 400, wp_error_http_status( $blocked_incomplete ), 'Incomplete complete() is HTTP 400' );
	$t->assert( ! $sm->has_completion_flag(), 'Incomplete complete() does not set the completion flag' );
	$t->assert( ! payload_exposes_owner( $blocked_incomplete ), 'Incomplete complete() keeps owner opaque' );

	reset_state();
	$sm = new State_Manager();
	seed_required_steps_complete( $sm );
	$sm->save_state( array_merge( $sm->get_state(), [ 'current_step' => 'landing-page-builder' ] ) );
	$before_conflict = wizard_identity_snapshot( $sm );
	$fence           = new Wizard_Mutation_Fence();
	$owner           = $fence->acquire();
	$t->assert( ! is_wp_error( $owner ), 'Complete-conflict fixture acquires the mutation fence' );
	$controller = new Step_Controller( $sm, new Logger() );
	$rest       = new Rest_Controller( $controller );
	$conflict   = $rest->complete();
	$t->assert( is_wp_error( $conflict ), '/complete is rejected while the mutation fence is held' );
	$t->assertEqual( 'rms_wizard_busy', is_wp_error( $conflict ) ? $conflict->get_error_code() : '', '/complete fence conflict uses rms_wizard_busy' );
	$t->assertEqual( 409, wp_error_http_status( $conflict ), '/complete fence conflict is HTTP 409' );
	$t->assert( ! $sm->has_completion_flag(), '/complete fence conflict does not set the completion flag' );
	$t->assertEqual( $before_conflict, wizard_identity_snapshot( $sm ), '/complete fence conflict mutates no current_step/step_status/run/pages' );
	$t->assert( ! payload_exposes_owner( $conflict ), '/complete fence conflict keeps owner opaque' );
	$t->assert( false === strpos( strtolower( (string) wp_json_encode( is_wp_error( $conflict ) ? $conflict->get_error_data() : $conflict ) ), strtolower( (string) $owner ) ), '/complete conflict does not leak the fence owner token' );
	$fence->release( (string) $owner );

	reset_state();
	$sm    = new State_Manager();
	$stale = seed_stale_running_item( $sm );
	$t->assert( empty( $stale['error'] ), 'Stale-lease GET fixture is ready' . ( isset( $stale['error'] ) && is_wp_error( $stale['error'] ) ? ': ' . $stale['error']->get_error_message() : '' ) );
	$before_run = $sm->get_state()['landing_run'] ?? null;
	$t->assertEqual( 'running', (string) ( $before_run['status'] ?? '' ), 'Stale fixture run is still running before GET recovery' );
	$controller = new Step_Controller( $sm, new Logger() );
	$rest       = new Rest_Controller( $controller );
	$recovered  = $rest->get_state();
	$after_run  = $sm->get_state()['landing_run'] ?? null;
	$t->assert( $recovered instanceof \WP_REST_Response, 'Standalone GET returns public state' );
	$t->assertEqual( 'interrupted', (string) ( $after_run['status'] ?? '' ), 'Standalone GET recovers a stale lease' );
	$item_status = '';
	foreach ( ( $after_run['items'] ?? [] ) as $item ) {
		if ( ( $stale['item_key'] ?? '' ) === ( $item['key'] ?? '' ) ) {
			$item_status = (string) ( $item['status'] ?? '' );
			break;
		}
	}
	$t->assertEqual( 'interrupted', $item_status, 'Standalone GET marks the stale running item interrupted' );
	$t->assertEqual( 'interrupted', (string) ( $recovered->data['landing_run']['status'] ?? '' ), 'Standalone GET public state reflects recovered run' );
	$t->assert( ! ( new Wizard_Mutation_Fence() )->is_held(), 'Standalone GET releases its scoped fence owner' );
	$t->assert( ! payload_exposes_owner( $recovered ), 'Standalone GET keeps owner opaque' );

	reset_state();
	$sm    = new State_Manager();
	$stale = seed_stale_running_item( $sm );
	$t->assert( empty( $stale['error'] ), 'Busy GET fixture is ready' );
	$before_busy = wizard_identity_snapshot( $sm );
	$fence       = new Wizard_Mutation_Fence();
	$owner       = $fence->acquire();
	$t->assert( ! is_wp_error( $owner ), 'Busy GET fixture acquires the mutation fence' );
	$controller = new Step_Controller( $sm, new Logger() );
	$busy_get   = ( new Rest_Controller( $controller ) )->get_state();
	$t->assert( $busy_get instanceof \WP_REST_Response, 'Busy GET still returns current public state' );
	$t->assertEqual( $before_busy, wizard_identity_snapshot( $sm ), 'Busy GET does not mutate run/step/pages' );
	$t->assertEqual( 'running', (string) ( $sm->get_state()['landing_run']['status'] ?? '' ), 'Busy GET does not recover a stale lease' );
	$t->assertEqual( 'running', (string) ( $busy_get->data['landing_run']['status'] ?? '' ), 'Busy GET public view stays unrecovered' );
	$t->assert( ! payload_exposes_owner( $busy_get ), 'Busy GET keeps owner opaque' );
	$t->assert( false === strpos( strtolower( (string) wp_json_encode( $busy_get->data ) ), strtolower( (string) $owner ) ), 'Busy GET does not leak the fence owner token' );
	$fence->release( (string) $owner );

	// === TEST 26: execute_step cleanup keeps the fence until legacy lock save finishes ===
	echo "\nTest 26: Cleanup order keeps the mutation fence until legacy lock save finishes\n";

	reset_state();
	$sm         = new State_Manager();
	$sm->save_state( [
		'ai_config'   => [ 'provider' => 'test', 'model' => 'test', 'has_credentials' => true ],
		'client_data' => [ 'company_name' => 'Test' ],
	] );
	$controller = new Step_Controller( $sm, new Logger() );
	$probe      = new Step_Controller( $sm, new Logger() );
	$events     = [];
	$owner_during_lock_save = null;
	$landing_during         = null;
	$probe_during           = null;

	$GLOBALS['_update_option_hook'] = static function ( $name, $value ) use ( $controller, $probe, &$events, &$owner_during_lock_save, &$landing_during, &$probe_during ) {
		if ( ! is_legacy_lock_clear_save( $name, $value ) ) {
			return;
		}

		$events[] = 'legacy_lock_save';
		$owner_prop = new \ReflectionProperty( Step_Controller::class, 'mutation_fence_owner' );
		$owner_prop->setAccessible( true );
		$owner_during_lock_save = (string) $owner_prop->getValue( $controller );

		$fence_probe = new Wizard_Mutation_Fence();
		$probe_during = $fence_probe->acquire();
		$events[]     = is_wp_error( $probe_during ) ? 'probe_blocked' : 'probe_acquired';
		if ( ! is_wp_error( $probe_during ) ) {
			$fence_probe->release( (string) $probe_during );
		}

		$landing_during = $probe->execute_step(
			'landing-page-builder',
			[
				'landing_action' => 'start',
				'landings'       => [ make_landing_payload_item( 'lk_cleanup_race', 'Cleanup Race' ) ],
			]
		);
		$events[] = ( is_wp_error( $landing_during ) && 409 === wp_error_http_status( $landing_during ) )
			? 'landing_blocked'
			: 'landing_wrote';
	};
	$GLOBALS['_wpdb_delete_hook'] = static function ( $name ) use ( &$events ) {
		if ( Wizard_Mutation_Fence::OPTION_NAME === $name ) {
			$events[] = 'fence_release';
		}
	};

	$ia = $controller->execute_step( 'ia-generation', [ 'provider' => 'test', 'model' => 'test' ] );
	$t->assert( ! is_wp_error( $ia ), 'IA configure holds the legacy lock and completes' . ( is_wp_error( $ia ) ? ': ' . $ia->get_error_message() : '' ) );
	$t->assert( in_array( 'legacy_lock_save', $events, true ), 'Cleanup performed the legacy lock save' );
	$t->assert( in_array( 'fence_release', $events, true ), 'Cleanup released the mutation fence' );
	$lock_event  = array_search( 'legacy_lock_save', $events, true );
	$fence_event = array_search( 'fence_release', $events, true );
	$t->assert( false !== $lock_event && false !== $fence_event && $lock_event < $fence_event, 'Legacy lock save happens before fence release' );
	$t->assertEqual( [ 'legacy_lock_save', 'probe_blocked', 'landing_blocked', 'fence_release' ], $events, 'Interleaved landing write cannot acquire until the lock save finishes' );
	$t->assert( is_string( $owner_during_lock_save ) && '' !== $owner_during_lock_save, 'Instance owner stays valid through the legacy lock save' );
	$t->assert( is_wp_error( $probe_during ) && 'rms_wizard_busy' === $probe_during->get_error_code(), 'Fence acquire during lock save is rms_wizard_busy' );
	$t->assert( is_wp_error( $landing_during ) && 409 === wp_error_http_status( $landing_during ), 'Landing start during lock save is HTTP 409' );
	$t->assertEqual( null, $sm->get_state()['landing_run'] ?? null, 'Blocked landing start leaves no run for release_lock to overwrite' );
	$t->assert( ! ( new Wizard_Mutation_Fence() )->is_held(), 'Fence is free after execute_step cleanup' );
	$owner_after = new \ReflectionProperty( Step_Controller::class, 'mutation_fence_owner' );
	$owner_after->setAccessible( true );
	$t->assertEqual( '', (string) $owner_after->getValue( $controller ), 'Instance owner is cleared after the CAS release' );

	$after_start = $controller->execute_step(
		'landing-page-builder',
		[
			'landing_action' => 'start',
			'landings'       => [ make_landing_payload_item( 'lk_after_cleanup', 'After Cleanup' ) ],
		]
	);
	$t->assert( ! is_wp_error( $after_start ), 'Landing start can acquire after cleanup' . ( is_wp_error( $after_start ) ? ': ' . $after_start->get_error_message() : '' ) );
	$t->assertEqual( 1, (int) ( $sm->get_state()['landing_run']['total'] ?? 0 ), 'Landing start after cleanup persists the run' );

	reset_state();
	$sm         = new State_Manager();
	$controller = new Step_Controller( $sm, new Logger() );
	$saw_lock   = false;
	$GLOBALS['_update_option_hook'] = static function ( $name, $value ) use ( &$saw_lock ) {
		if ( 'rms_wizard_state' === $name && is_array( $value ) && ! empty( $value['locks']['step_execution'] ) ) {
			$saw_lock = true;
		}
	};
	$sm->save_state( [
		'ai_config'   => [ 'provider' => 'test', 'model' => 'test', 'has_credentials' => true ],
		'client_data' => [ 'company_name' => 'Test' ],
	] );
	$start_no_lock = $controller->execute_step(
		'landing-page-builder',
		[
			'landing_action' => 'start',
			'landings'       => [
				make_landing_payload_item( 'lk_no_legacy_a', 'Alpha Unique' ),
				make_landing_payload_item( 'lk_no_legacy_b', 'Beta Unique' ),
			],
		]
	);
	$t->assert( ! is_wp_error( $start_no_lock ), 'Landing start still succeeds without the legacy lock' . ( is_wp_error( $start_no_lock ) ? ': ' . $start_no_lock->get_error_message() : '' ) );
	$t->assert( ! $saw_lock, 'Landing start does not acquire the legacy state lock' );
	$process_no_lock = $controller->execute_step( 'landing-page-builder', [ 'landing_action' => 'process' ] );
	$t->assert( ! is_wp_error( $process_no_lock ), 'Landing process still succeeds without the legacy lock' . ( is_wp_error( $process_no_lock ) ? ': ' . $process_no_lock->get_error_message() : '' ) );
	$t->assert( ! $saw_lock, 'Landing process does not acquire the legacy state lock' );
	$t->assert( empty( $sm->get_state()['locks']['step_execution'] ), 'Landing start/process leave no leftover legacy lock' );

	reset_state();
	$sm         = new State_Manager();
	$controller = new Step_Controller( $sm, new Logger() );
	$GLOBALS['_update_option_hook'] = static function ( $name, $value ) {
		if ( is_legacy_lock_clear_save( $name, $value ) ) {
			throw new \RuntimeException( 'legacy-lock-save-boom' );
		}
	};
	$threw = false;
	try {
		$controller->execute_step( 'ia-generation', [ 'provider' => 'test', 'model' => 'test' ] );
	} catch ( \RuntimeException $exception ) {
		$threw = 'legacy-lock-save-boom' === $exception->getMessage();
	}
	$t->assert( $threw, 'Legacy lock save exceptions still propagate' );
	$t->assert( ! ( new Wizard_Mutation_Fence() )->is_held(), 'Nested finally releases the fence when legacy lock save throws' );
	$owner_prop = new \ReflectionProperty( Step_Controller::class, 'mutation_fence_owner' );
	$owner_prop->setAccessible( true );
	$t->assertEqual( '', (string) $owner_prop->getValue( $controller ), 'Instance owner is cleared after a failed legacy lock save' );

	echo "\n";
	$t->results();
}
