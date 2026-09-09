<?php
/**
 * Focused runtime harness for issue #121 — Theme Settings CF7 landing form control.
 * Parent runs two isolated child scenarios (inactive/active); CF7 availability cannot be
 * toggled inside one process. ACF schema/SCSS/hero artifact proofs: PR3 harness.
 * Usage: php tests/cf7-theme-settings-control-harness.php
 */

if ( PHP_SAPI !== 'cli' ) {
	fwrite( STDERR, "CLI only.\n" );
	exit( 1 );
}

function rms_harness_assert( $condition, string $message ): void {
	if ( ! $condition ) { fwrite( STDERR, $message . "\n" ); exit( 1 ); }
}

/** Every needle must appear in $haystack, or must not when $should is false. */
function rms_harness_contains( string $haystack, array $needles, bool $should = true ): void {
	foreach ( $needles as $needle ) {
		rms_harness_assert( ( false !== strpos( $haystack, $needle ) ) === $should, ( $should ? 'Expected' : 'Did not expect' ) . " [{$needle}]" );
	}
}

final class RMS_Harness_Redirect extends Exception {}

/** Run the generate handler expecting exactly one outcome: a safe redirect containing every needle, or a die with $die_message. */
function rms_harness_expect( ?array $redirect_needles = null, ?string $die_message = null ): void {
	try {
		rms_cf7_landing_form_handle_generate();
		rms_harness_assert( false, 'handler must redirect or die' );
	} catch ( RMS_Harness_Redirect $e ) {
		rms_harness_assert( null !== $redirect_needles, 'handler redirected when a die was expected: ' . $e->getMessage() );
		rms_harness_contains( $e->getMessage(), $redirect_needles );
	} catch ( Exception $e ) {
		rms_harness_assert( null !== $die_message && $die_message === $e->getMessage(), "expected die [{$die_message}], got [{$e->getMessage()}]" );
	}
}

/** Render a callback and return the buffered output. */
function rms_harness_capture( callable $render ): string {
	ob_start();
	$render();
	return (string) ob_get_clean();
}

$scenario = getenv( 'RMS_CONTROL_SCENARIO' ); $root = dirname( __DIR__ );
$kernel_path = $root . '/inc/cf7-landing-form.php'; $option = 'rms_cf7_landing_form_id';

if ( false === $scenario || '' === $scenario ) {
	// ─── Parent: two isolated child scenarios ────────────────────────
	$failed = 0;
	foreach ( array( 'inactive', 'active' ) as $name ) {
		putenv( "RMS_CONTROL_SCENARIO={$name}" );
		$output = array(); $code = 0;
		exec( '"' . PHP_BINARY . '" ' . escapeshellarg( __FILE__ ), $output, $code );
		$text = implode( "\n", $output );
		if ( 0 !== $code ) {
			fwrite( STDERR, "FAIL {$name}\n{$text}\n" );
			$failed++;
			continue;
		}
		echo "PASS {$name}\n" . ( '' !== $text ? $text . "\n" : '' );
	}
	putenv( 'RMS_CONTROL_SCENARIO' );

	if ( $failed > 0 ) { fwrite( STDERR, "Harness failed: {$failed} scenario(s).\n" ); exit( 1 ); }
	echo "Harness passed: 2 scenarios.\n";
	exit( 0 );
}

function rms_harness_wp_stubs(): void {
	if ( ! defined( 'ABSPATH' ) ) {
		define( 'ABSPATH', __DIR__ . '/' );
	}

	$GLOBALS['rms_harness'] = array(
		'options' => array(), 'posts' => array(), 'save_calls' => 0, 'option_writes' => 0, 'meta_stamps' => 0,
		'break_save' => false, 'corrupt' => false, 'can_manage' => true, 'hooks' => array(),
	);

	function get_option( $name, $default = false ) { return $GLOBALS['rms_harness']['options'][ $name ] ?? $default; }
	function update_option( $name, $value, $autoload = null ) { $GLOBALS['rms_harness']['option_writes']++; $GLOBALS['rms_harness']['options'][ $name ] = $value; return true; }
	function get_post_meta( $post_id, $key, $single = false ) { return $GLOBALS['rms_harness']['posts'][ (int) $post_id ]['meta'][ $key ] ?? ''; }
	function add_post_meta( $post_id, $key, $value, $unique = false ) { $GLOBALS['rms_harness']['meta_stamps']++; $GLOBALS['rms_harness']['posts'][ (int) $post_id ]['meta'][ $key ] = $value; return true; }
	function get_post_status( $post_id ) { return ! empty( $GLOBALS['rms_harness']['corrupt'] ) ? 'trash' : ( $GLOBALS['rms_harness']['posts'][ (int) $post_id ]['status'] ?? '' ); }
	function get_post_type( $post_id ) { return $GLOBALS['rms_harness']['posts'][ (int) $post_id ]['type'] ?? ''; }
	function get_posts( $args ) { $wanted = ! empty( $GLOBALS['rms_harness']['corrupt'] ) ? 'trash' : ( $args['post_status'] ?? '' ); $ids = array(); foreach ( $GLOBALS['rms_harness']['posts'] as $id => $post ) { if ( $wanted !== $post['status'] || ( $args['post_type'] ?? '' ) !== $post['type'] || ( $post['meta'][ $args['meta_key'] ?? '' ] ?? '' ) !== ( $args['meta_value'] ?? '' ) ) { continue; } $ids[] = $id; } return $ids; }
	function get_locale() { return 'en_US'; } function is_admin() { return true; } function __( $text, $domain = 'default' ) { return $text; }
	function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); } function esc_attr( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
	function esc_url( $url ) { return (string) $url; } function wp_unslash( $value ) { return $value; }
	function add_filter( $tag, $callback ) { $GLOBALS['rms_harness']['hooks'][ $tag ] = $callback; return true; } function add_action( $tag, $callback, $priority = 10, $accepted_args = 1 ) { $GLOBALS['rms_harness']['hooks'][ $tag ] = $callback; return true; }
	function sanitize_key( $key ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $key ) ); }
	function admin_url( $path = '' ) { return 'https://example.com/wp-admin/' . $path; }
	function add_query_arg( $args, $url = '' ) { return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . http_build_query( $args ); }
	function wp_nonce_field( $action = -1, $name = '_wpnonce', $referer = true, $echo = true ) { $html = '<input type="hidden" name="' . $name . '" value="rms-nonce-token" />'; if ( $echo ) { echo $html; } return $html; }
	function check_admin_referer( $action = -1, $query_arg = false ) { $key = $query_arg ? $query_arg : '_wpnonce'; if ( ( $_POST[ $key ] ?? '' ) !== 'rms-nonce-token' ) { throw new Exception( 'bad nonce' ); } return true; }
	function current_user_can( $capability ) { return ! empty( $GLOBALS['rms_harness']['can_manage'] ); }
	function wp_die( $message = '', $title = '', $args = array() ) { throw new Exception( 'wp_die' ); } function wp_safe_redirect( $url ) { throw new RMS_Harness_Redirect( (string) $url ); }
}

function rms_harness_install_cf7(): void {
	class WPCF7_ContactForm {
		public $harness_id = 0; public $harness_title = '';
		public function __construct( int $id, string $title ) { $this->harness_id = $id; $this->harness_title = $title; }
		public function id(): int { return $this->harness_id; }
		public function title(): string { return $this->harness_title; }
	}

	function wpcf7_save_contact_form( $args = array(), $context = 'save' ) {
		$h =& $GLOBALS['rms_harness'];
		$h['save_calls']++;
		if ( ! empty( $h['break_save'] ) ) { return false; }
		$id = (int) ( $args['id'] ?? -1 );
		if ( $id < 1 ) {
			$ids = array_keys( $h['posts'] );
			$id  = $ids ? max( $ids ) + 1 : 1;
			$h['posts'][ $id ] = array( 'title' => (string) ( $args['title'] ?? '' ), 'status' => 'publish', 'type' => 'wpcf7_contact_form', 'meta' => array() );
		}
		return new WPCF7_ContactForm( $id, (string) ( $args['title'] ?? '' ) );
	}

	function wpcf7_contact_form( $id ) {
		$h     =& $GLOBALS['rms_harness'];
		$post  = $h['posts'][ (int) $id ] ?? null;
		$valid = null !== $post && 'wpcf7_contact_form' === $post['type'] && 'publish' === $post['status'];
		return $valid ? new WPCF7_ContactForm( (int) $id, $post['title'] ) : false;
	}
}

switch ( $scenario ) {
	case 'inactive':
		rms_harness_wp_stubs();
		require $kernel_path; $h =& $GLOBALS['rms_harness'];
		rms_harness_assert( isset( $h['hooks']['acf/load_field/key=field_rms_cf7_landing_form_control'], $h['hooks']['admin_post_rms_cf7_generate_landing_form'], $h['hooks']['admin_footer'], $h['hooks']['admin_notices'] ), 'control filter, handler, footer form, and notices must be hooked' );

		$msg = rms_harness_capture( 'rms_cf7_landing_form_control_render' );
		rms_harness_contains( $msg, array( 'Enable the Contact Form 7 plugin before adding the theme form.', 'Generate RMS Landing Form', 'form="rms-cf7-landing-form-generate"', 'disabled', 'color:#b32d2e' ) );
		rms_harness_contains( $msg, array( 'readonly', '[contact-form-7' ), false );
		rms_harness_assert( 0 === $h['save_calls'] && 0 === $h['option_writes'] && 0 === $h['meta_stamps'], 'inactive render must not create or persist anything' );

		$_GET['page'] = 'rms-theme-settings'; $_POST['rms_cf7_nonce'] = 'rms-nonce-token';
		rms_harness_expect( array( 'admin.php?page=rms-theme-settings', 'rms_cf7_status=inactive' ) );
		rms_harness_assert( 0 === $h['save_calls'], 'inactive handler must recheck CF7 before any kernel call' );

		$_GET['rms_cf7_status'] = 'inactive';
		rms_harness_contains( rms_harness_capture( 'rms_cf7_landing_form_admin_notice' ), array( 'notice-error', 'Enable the Contact Form 7 plugin before adding the theme form.' ) );

		rms_harness_contains( rms_harness_capture( 'rms_cf7_landing_form_control_hidden_form' ), array( '<form id="rms-cf7-landing-form-generate"', 'https://example.com/wp-admin/admin-post.php', 'name="action" value="rms_cf7_generate_landing_form"', 'name="rms_cf7_nonce" value="rms-nonce-token"' ) );
		break;

	case 'active':
		rms_harness_wp_stubs();
		rms_harness_install_cf7();
		require $kernel_path; $h =& $GLOBALS['rms_harness'];

		// Ready state: enabled primary button, no warning, no shortcode, no creation.
		$msg = rms_harness_capture( 'rms_cf7_landing_form_control_render' );
		rms_harness_contains( $msg, array( 'Generate RMS Landing Form', 'form="rms-cf7-landing-form-generate"', 'button-primary' ) );
		rms_harness_contains( $msg, array( 'disabled', 'readonly', 'Enable the Contact Form 7' ), false );
		rms_harness_assert( 0 === $h['save_calls'] && 0 === $h['option_writes'] && 0 === $h['meta_stamps'], 'Theme Settings render must not create a form' );

		// Handler guards: capability, then the dedicated nonce; both must abort before the kernel.
		$h['can_manage'] = false;
		rms_harness_expect( null, 'wp_die' );
		rms_harness_assert( 0 === $h['save_calls'], 'denied handler must not reach the kernel' );

		$h['can_manage'] = true; $_POST['rms_cf7_nonce'] = 'wrong';
		rms_harness_expect( null, 'bad nonce' );
		rms_harness_assert( 0 === $h['save_calls'], 'unverified handler must not reach the kernel' );

		// Happy path: create exactly one marked form and persist its id.
		$_POST['rms_cf7_nonce'] = 'rms-nonce-token'; $_GET['page'] = 'rms-theme-settings';
		rms_harness_expect( array( 'admin.php?page=rms-theme-settings', 'rms_cf7_status=success' ) );
		$id = (int) get_option( $option );
		rms_harness_assert( $id > 0 && 1 === $h['save_calls'] && 1 === $h['meta_stamps'] && 'landing_form' === get_post_meta( $id, '_rms_theme_managed', true ), 'kernel must create exactly one marked form and persist it' );

		// Existing state: escaped readonly shortcode, enabled button, no overwrite.
		$msg = rms_harness_capture( 'rms_cf7_landing_form_control_render' );
		rms_harness_contains( $msg, array( 'readonly', 'html_class=&quot;hero__form&quot;', 'id=&quot;' . $id . '&quot;', 'Generate RMS Landing Form', 'form="rms-cf7-landing-form-generate"' ) );
		rms_harness_contains( $msg, array( 'disabled' ), false );
		rms_harness_assert( 1 === $h['save_calls'] && 1 === $h['meta_stamps'], 'existing-state render must not overwrite or restamp the form' );

		$form_html = rms_harness_capture( 'rms_cf7_landing_form_control_hidden_form' );
		rms_harness_contains( $form_html, array( '<form id="rms-cf7-landing-form-generate"', 'https://example.com/wp-admin/admin-post.php', 'name="action" value="rms_cf7_generate_landing_form"', 'name="rms_cf7_nonce" value="rms-nonce-token"' ) );
		rms_harness_assert( 1 === substr_count( $form_html, '<form' ), 'the POST target must be exactly one separate form' );

		// Notices: allowlisted status on the settings page only; unknown status and wrong page stay silent.
		$_GET['rms_cf7_status'] = 'success';
		rms_harness_contains( rms_harness_capture( 'rms_cf7_landing_form_admin_notice' ), array( 'notice-success' ) );
		$_GET['rms_cf7_status'] = 'bogus';
		rms_harness_assert( '' === trim( rms_harness_capture( 'rms_cf7_landing_form_admin_notice' ) ), 'unknown statuses must render nothing' );
		$_GET['page'] = 'tools.php'; $_GET['rms_cf7_status'] = 'success';
		rms_harness_assert( '' === trim( rms_harness_capture( 'rms_cf7_landing_form_admin_notice' ) ), 'notices must render only on the settings page' );
		$_GET['page'] = 'rms-theme-settings';

		// Kernel failure maps to an error redirect; recovery then reuses the same form without overwriting.
		$h['corrupt'] = $h['break_save'] = true; $h['options'][ $option ] = 999;
		rms_harness_expect( array( 'rms_cf7_status=error' ) );
		$h['corrupt'] = $h['break_save'] = false; $h['save_calls'] = 0;
		rms_harness_expect( array( 'rms_cf7_status=success' ) );
		rms_harness_assert( 0 === $h['save_calls'] && $id === (int) get_option( $option ) && 1 === $h['meta_stamps'], 'second generate must reuse the same form without overwriting' );
		break;

	default:
		fwrite( STDERR, "Unknown scenario: {$scenario}\n" );
		exit( 1 );
}

exit( 0 );