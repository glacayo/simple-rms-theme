<?php
/**
 * Focused harness for issue #121 (CF7 landing form service kernel).
 *
 * No framework. Parent process runs isolated child scenarios because
 * CF7 stubs cannot be toggled in one process. Each stub is defined
 * exactly once per child process.
 *
 * Usage: php tests/cf7-landing-form-service-harness.php
 */

if ( PHP_SAPI !== 'cli' ) {
	fwrite( STDERR, "CLI only.\n" );
	exit( 1 );
}

$scenario = getenv( 'RMS_HARNESS_SCENARIO' );

if ( false === $scenario || '' === $scenario ) {
	$scenarios = array( 'inactive-safe', 'create-once', 'reuse-idempotent', 'stale-recovery', 'unmarked-ignored', 'shortcode-fail-closed' );
	$failed    = 0;
	foreach ( $scenarios as $name ) {
		putenv( 'RMS_HARNESS_SCENARIO=' . $name );
		$output = array();
		$code   = 0;
		exec( '"' . PHP_BINARY . '" ' . escapeshellarg( __FILE__ ), $output, $code );
		$text = implode( "\n", $output );
		if ( 0 !== $code ) {
			fwrite( STDERR, "FAIL {$name}\n{$text}\n" );
			$failed++;
			continue;
		}
		echo "PASS {$name}\n" . ( '' !== $text ? $text . "\n" : '' );
	}
	putenv( 'RMS_HARNESS_SCENARIO' );

	if ( $failed > 0 ) {
		fwrite( STDERR, "Harness failed: {$failed} scenario(s).\n" );
		exit( 1 );
	}

	echo 'Harness passed: ' . count( $scenarios ) . " scenarios.\n";
	exit( 0 );
}

function rms_harness_assert( $condition, string $message ): void {
	if ( ! $condition ) {
		fwrite( STDERR, $message . "\n" );
		exit( 1 );
	}
}

function rms_harness_wp_stubs(): void {
	if ( ! defined( 'ABSPATH' ) ) {
		define( 'ABSPATH', __DIR__ . '/' );
	}

	if ( ! class_exists( 'WP_Post' ) ) { class WP_Post { public $ID = 0; public function __construct( int $id ) { $this->ID = $id; } } }

	$GLOBALS['rms_harness'] = array(
		'options'             => array(),
		'posts'               => array(),
		'save_calls'          => 0,
		'last_save_args'      => null,
		'contact_form_loads'  => 0,
		'update_option_calls' => 0,
		'add_post_meta_calls' => 0,
		'break_contact_form'  => false,
	);

	function get_option( $name, $default = false ) { return $GLOBALS['rms_harness']['options'][ $name ] ?? $default; }
	function update_option( $name, $value, $autoload = null ) { $GLOBALS['rms_harness']['update_option_calls']++; $GLOBALS['rms_harness']['options'][ $name ] = $value; return true; }
	function get_post_meta( $post_id, $key, $single = false ) { return $GLOBALS['rms_harness']['posts'][ $post_id ]['meta'][ $key ] ?? ''; }
	function add_post_meta( $post_id, $key, $value, $unique = false ) { $GLOBALS['rms_harness']['add_post_meta_calls']++; if ( $unique && isset( $GLOBALS['rms_harness']['posts'][ $post_id ]['meta'][ $key ] ) ) { return false; } $GLOBALS['rms_harness']['posts'][ $post_id ]['meta'][ $key ] = $value; return true; }
	function get_post_status( $post_id ) { return $GLOBALS['rms_harness']['posts'][ $post_id ]['status'] ?? ''; }
	function get_post_type( $post_id ) { return $GLOBALS['rms_harness']['posts'][ $post_id ]['type'] ?? ''; }
	function get_posts( $args ) { $ids = array(); foreach ( $GLOBALS['rms_harness']['posts'] as $id => $post ) { $key = $args['meta_key'] ?? ''; if ( ( $args['post_status'] ?? '' ) !== $post['status'] || ( $args['post_type'] ?? '' ) !== $post['type'] || ( '' !== $key && ( $post['meta'][ $key ] ?? '' ) !== ( $args['meta_value'] ?? '' ) ) ) { continue; } $ids[] = $id; } // WP contract: WP_Post[] by default, bare IDs only when fields is ids.
		return 'ids' === ( $args['fields'] ?? '' ) ? $ids : array_map( static function ( $id ) { return new WP_Post( (int) $id ); }, $ids ); }
	function get_locale() { return 'en_US'; }
	function __( $text, $domain = 'default' ) { return $text; }
	function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
}

function rms_harness_install_cf7(): void {
	if ( ! class_exists( 'WPCF7_ContactForm' ) ) {
		class WPCF7_ContactForm {
			public $harness_id = 0;
			public $harness_title = '';
			public function __construct( int $id, string $title ) { $this->harness_id = $id; $this->harness_title = $title; }
			public function id(): int { return $this->harness_id; }
			public function title(): string { return $this->harness_title; }
		}
	}

	if ( ! function_exists( 'wpcf7_save_contact_form' ) ) {
		function wpcf7_save_contact_form( $args = array(), $context = 'save' ) {
			$GLOBALS['rms_harness']['save_calls']++;
			$GLOBALS['rms_harness']['last_save_args'] = $args;
			$id = (int) ( $args['id'] ?? -1 );
			if ( $id < 1 ) {
				$ids = array_keys( $GLOBALS['rms_harness']['posts'] );
				$id  = $ids ? max( $ids ) + 1 : 1;
				$GLOBALS['rms_harness']['posts'][ $id ] = array(
					'id'     => $id,
					'title'  => (string) ( $args['title'] ?? '' ),
					'status' => 'publish',
					'type'   => 'wpcf7_contact_form',
					'meta'   => array(),
				);
			}
			return new WPCF7_ContactForm( $id, (string) ( $args['title'] ?? '' ) );
		}
	}

	if ( ! function_exists( 'wpcf7_contact_form' ) ) {
		function wpcf7_contact_form( $id ) {
			$GLOBALS['rms_harness']['contact_form_loads']++;
			$post  = $GLOBALS['rms_harness']['posts'][ (int) $id ] ?? null;
			$valid = null !== $post && 'wpcf7_contact_form' === $post['type'] && 'publish' === $post['status'];
			return ! empty( $GLOBALS['rms_harness']['break_contact_form'] ) || ! $valid ? false : new WPCF7_ContactForm( (int) $id, $post['title'] );
		}
	}
}

function rms_harness_seed_post( int $id, string $title, array $meta = array() ): void {
	$GLOBALS['rms_harness']['posts'][ $id ] = array( 'id' => $id, 'title' => $title, 'status' => 'publish', 'type' => 'wpcf7_contact_form', 'meta' => $meta );
}

$kernel = dirname( __DIR__ ) . '/inc/cf7-landing-form.php';
$option = 'rms_cf7_landing_form_id';
$marker = '_rms_theme_managed';

switch ( $scenario ) {
	case 'inactive-safe':
		rms_harness_wp_stubs();
		require $kernel;
		rms_harness_assert( false === rms_cf7_landing_form_available(), 'CF7 must be reported unavailable' );
		rms_harness_assert( 0 === rms_cf7_landing_form_id(), 'id must fail closed to 0' );
		rms_harness_assert( '' === rms_cf7_landing_form_shortcode(), 'shortcode must fail closed to empty' );
		rms_harness_assert( 0 === $GLOBALS['rms_harness']['update_option_calls'] && false === get_option( $option ), 'nothing may persist while inactive' );
		break;

	case 'create-once':
		rms_harness_wp_stubs();
		rms_harness_install_cf7();
		require $kernel;
		$first = rms_cf7_landing_form_id();
		rms_harness_assert( $first > 0, 'first call must create a form' );
		rms_harness_assert( 1 === $GLOBALS['rms_harness']['save_calls'], 'save must run exactly once' );
		$args = $GLOBALS['rms_harness']['last_save_args'];
		rms_harness_assert( -1 === (int) ( $args['id'] ?? 0 ) && 'RMS Landing Form' === ( $args['title'] ?? '' ) && 'en_US' === ( $args['locale'] ?? '' ), 'creation must use id -1, the canonical title, and the site locale' );

		$markup = (string) ( $args['form'] ?? '' );
		rms_harness_assert( false !== strpos( $markup, 'hero__form-group' ) && false !== strpos( $markup, 'hero__form-input' ) && false !== strpos( $markup, 'hero__form-submit' ), 'markup must carry hero classes' );
		rms_harness_assert( false !== strpos( $markup, '[email* your-email' ) && false !== strpos( $markup, '[tel* your-phone' ) && false !== strpos( $markup, '[text* your-service' ) && false === stripos( $markup, 'roof' ), 'email, phone, and service must use typed generic required fields without roofing literals' );

		$body = (string) ( $args['mail']['body'] ?? '' );
		foreach ( array( 'your-name', 'your-email', 'your-phone', 'your-service', 'your-summary' ) as $tag ) {
			rms_harness_assert( false !== strpos( $markup, " {$tag}" ) && false !== strpos( $body, "[{$tag}]" ), "mail must use the matching {$tag} tag" );
		}
		rms_harness_assert( false === stripos( $body, 'roof' ), 'mail must not contain roofing literals' );

		rms_harness_assert( 'landing_form' === get_post_meta( $first, $marker, true ), 'created post must carry the marker meta' );
		rms_harness_assert( 1 === $GLOBALS['rms_harness']['add_post_meta_calls'], 'meta must be stamped exactly once' );
		rms_harness_assert( $first === (int) get_option( $option ), 'managed id must be persisted' );
		rms_harness_assert( 1 === $GLOBALS['rms_harness']['update_option_calls'], 'option must be persisted exactly once' );
		rms_harness_assert( $GLOBALS['rms_harness']['contact_form_loads'] >= 1, 'created form must be reloaded via wpcf7_contact_form' );

		$shortcode = rms_cf7_landing_form_shortcode();
		rms_harness_assert( false !== strpos( $shortcode, "[contact-form-7 id=\"{$first}\"" ) && false !== strpos( $shortcode, 'title="RMS Landing Form"' ) && false !== strpos( $shortcode, 'html_class="hero__form"' ), 'shortcode must carry the id, canonical title, and hero class' );

		$second = rms_cf7_landing_form_id();
		rms_harness_assert( $first === $second, 'second call must reuse the same id' );
		rms_harness_assert( 1 === $GLOBALS['rms_harness']['save_calls'] && 1 === $GLOBALS['rms_harness']['add_post_meta_calls'] && 1 === $GLOBALS['rms_harness']['update_option_calls'], 'second call must not save, restamp, or overwrite' );
		break;

	case 'reuse-idempotent':
		rms_harness_wp_stubs();
		rms_harness_install_cf7();
		rms_harness_seed_post( 7, 'My Custom Title', array( $marker => 'landing_form' ) );
		$GLOBALS['rms_harness']['options'][ $option ] = 7;
		require $kernel;
		rms_harness_assert( 7 === rms_cf7_landing_form_id() && 7 === rms_cf7_landing_form_id(), 'both calls must return the stored managed id' );
		rms_harness_assert( 0 === $GLOBALS['rms_harness']['save_calls'] && 0 === $GLOBALS['rms_harness']['update_option_calls'] && 0 === $GLOBALS['rms_harness']['add_post_meta_calls'], 'reuse must never save, restamp, or overwrite' );
		rms_harness_assert( 'My Custom Title' === $GLOBALS['rms_harness']['posts'][7]['title'], 'reuse must not modify the title' );
		break;

	case 'stale-recovery':
		rms_harness_wp_stubs();
		rms_harness_install_cf7();
		rms_harness_seed_post( 5, 'RMS Landing Form', array( $marker => 'landing_form' ) );
		$GLOBALS['rms_harness']['options'][ $option ] = 99;
		require $kernel;
		$id = rms_cf7_landing_form_id();
		rms_harness_assert( 5 === $id, 'stale option must recover the marked form' );
		rms_harness_assert( 0 === $GLOBALS['rms_harness']['save_calls'] && 0 === $GLOBALS['rms_harness']['add_post_meta_calls'], 'recovery must never create or stamp' );
		rms_harness_assert( 1 === $GLOBALS['rms_harness']['update_option_calls'] && 5 === (int) get_option( $option ), 'recovered id must be persisted once' );
		rms_harness_assert( false !== strpos( rms_cf7_landing_form_shortcode(), 'id="5"' ), 'shortcode must target the recovered form' );
		break;

	case 'unmarked-ignored':
		rms_harness_wp_stubs();
		rms_harness_install_cf7();
		rms_harness_seed_post( 3, 'RMS Landing Form' );
		rms_harness_seed_post( 4, 'RMS Landing Form', array( $marker => 'other' ) );
		require $kernel;
		$id = rms_cf7_landing_form_id();
		rms_harness_assert( $id > 0 && 3 !== $id && 4 !== $id, 'unmarked or wrong-marker forms must be ignored' );
		rms_harness_assert( 1 === $GLOBALS['rms_harness']['save_calls'], 'kernel must create its own form' );
		rms_harness_assert( array() === $GLOBALS['rms_harness']['posts'][3]['meta'] && 'other' === $GLOBALS['rms_harness']['posts'][4]['meta'][ $marker ], 'same-title unmarked and wrong-marker forms must stay untouched' );
		break;

	case 'shortcode-fail-closed':
		rms_harness_wp_stubs();
		rms_harness_install_cf7();
		rms_harness_seed_post( 11, 'RMS Landing Form', array( $marker => 'landing_form' ) );
		$GLOBALS['rms_harness']['options'][ $option ] = 11;
		$GLOBALS['rms_harness']['break_contact_form'] = true;
		require $kernel;
		rms_harness_assert( '' === rms_cf7_landing_form_shortcode(), 'shortcode must fail closed when reload fails' );
		rms_harness_assert( 0 === $GLOBALS['rms_harness']['save_calls'], 'fail-closed path must not create forms' );
		break;

	default:
		fwrite( STDERR, "Unknown scenario: {$scenario}\n" );
		exit( 1 );
}

exit( 0 );