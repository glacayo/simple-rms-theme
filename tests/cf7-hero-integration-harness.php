<?php
/**
 * Focused runtime harness for issue #121 — PR3 Hero + ACF control integration.
 * One isolated child process per scenario: ACF JSON tab+message schema and
 * template/SCSS source contracts; sanitizer-independent empty-message load
 * path with a dedicated admin render hook; readonly input + button form
 * association; Hero shortcode/whitespace/no-form paths with conditional grid.
 * Read-only over theme assets; never builds dist or creates forms.
 * Usage: php tests/cf7-hero-integration-harness.php
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

/** Render a callable and return the buffered output. */
function rms_harness_capture( callable $render ): string {
	ob_start();
	$render();
	return (string) ob_get_clean();
}

$scenario      = getenv( 'RMS_HERO_SCENARIO' );
$root          = dirname( __DIR__ );
$json_path     = $root . '/acf-json/group_rms_theme_settings.json';
$scss_path     = $root . '/src/scss/templates/hero.scss';
$template_path = $root . '/templates/hero.php';
$kernel_path   = $root . '/inc/cf7-landing-form.php';

if ( false === $scenario || '' === $scenario ) {
	// ─── Parent: one isolated child process per scenario ────────────────
	$failed = 0;
	foreach ( array( 'schema', 'hero', 'control-active', 'control-inactive' ) as $name ) {
		putenv( "RMS_HERO_SCENARIO={$name}" );
		exec( '"' . PHP_BINARY . '" ' . escapeshellarg( __FILE__ ), $output, $code );
		if ( 0 !== $code ) { fwrite( STDERR, "FAIL {$name}\n" . implode( "\n", $output ) . "\n" ); $failed++; continue; }
		echo "PASS {$name}\n" . implode( "\n", array_filter( $output ) ) . "\n";
	}
	putenv( 'RMS_HERO_SCENARIO' );
	if ( $failed > 0 ) { fwrite( STDERR, "Harness failed: {$failed} scenario(s).\n" ); exit( 1 ); }
	echo "Harness passed: 4 scenarios.\n";
	exit( 0 );
}

switch ( $scenario ) {

	// ─── ACF JSON schema + template/SCSS source contracts ────────────────
	case 'schema':
		$group = json_decode( (string) file_get_contents( $json_path ), true );
		rms_harness_assert( is_array( $group ) && isset( $group['fields'] ) && is_array( $group['fields'] ), 'group JSON must decode with a fields array' );
		$messages = array(); $editable = array(); $tab = null; $tab_at = null; $control = null; $control_at = null;
		foreach ( $group['fields'] as $i => $field ) {
			$key = (string) ( $field['key'] ?? '' ); $name = (string) ( $field['name'] ?? '' ); $type = (string) ( $field['type'] ?? '' );
			if ( 'message' === $type ) { $messages[] = $field; }
			if ( 'field_rms_tab_cf7' === $key ) { $tab = $field; $tab_at = $i; }
			if ( 'field_rms_cf7_landing_form_control' === $key ) { $control = $field; $control_at = $i; }
			if ( 'hero_form_shortcode' === $name ) { $editable[] = $field; }
		}
		rms_harness_assert( 1 === count( $messages ), 'exactly one message field may exist in Theme Settings' );
		rms_harness_assert( null !== $control, 'the CF7 control field must exist' );
		rms_harness_assert( null !== $tab && 'tab' === $tab['type'] && 'Contact Form 7' === $tab['label'] && $tab_at + 1 === $control_at, 'a clear Contact Form 7 tab must immediately precede the control field' );
		rms_harness_assert( 'message' === $control['type'], 'the control must be a read-only message field, not an editable shortcode option' );
		rms_harness_assert( array() === $editable, 'the editable hero_form_shortcode option must not live in Theme Settings' );
		rms_harness_assert( '' === trim( (string) ( $control['message'] ?? 'x' ) ), 'the stored message must default to empty so rendering never depends on ACF sanitization' );

		$scss   = (string) file_get_contents( $scss_path );
		$marker = strpos( $scss, 'Contact Form 7 — Managed Landing Form' );
		rms_harness_assert( false !== $marker, 'hero SCSS must contain the CF7 scoped block' );
		$block = (string) substr( $scss, (int) $marker );
		rms_harness_contains( $block, array( '.hero__form-card', '.wpcf7 {', '.wpcf7-form-control-wrap', '.wpcf7-not-valid-tip', '.wpcf7-response-output', '.wpcf7-spinner', '.wpcf7-not-valid', '$color-error', 'focus-ring' ) );
		rms_harness_contains( strtolower( $block ), array( 'hero-name', 'roofing' ), false );

		$template = (string) file_get_contents( $template_path );
		rms_harness_contains( $template, array( 'trim(', '$has_hero_form', "'grid-2'", "'grid-1'", 'hero_form_shortcode', 'do_shortcode' ) );
		rms_harness_contains( strtolower( $template ), array( 'roofing', '<form', 'hero-name', 'hero-email', 'hero-phone', 'hero-zip', 'rms_cf7_landing_form_shortcode', 'rms_cf7_landing_form_id', 'get_option' ), false );
		break;

	// ─── Hero template runtime paths ─────────────────────────────────────
	case 'hero':
		function esc_url( $url ) { return (string) $url; } function esc_html( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8' ); }
		function esc_attr( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8' ); } function wp_kses_post( $t ) { return (string) $t; }
		function get_sub_field( $selector ) { return $GLOBALS['rms_hero_fields'][ $selector ] ?? ''; }
		function do_shortcode( $content = '' ) { return '<div class="wpcf7" data-harness-form="1"></div>'; }
		$GLOBALS['rms_template'] = $template_path;
		$render_hero             = function(): string {
			ob_start();
			require $GLOBALS['rms_template'];
			return (string) ob_get_clean();
		};

		// Form present: two-column grid renders the managed form card.
		$GLOBALS['rms_hero_fields'] = array( 'hero_bg_image' => '', 'hero_reviews_label' => '', 'hero_title' => '', 'hero_description' => '', 'hero_form_shortcode' => '[contact-form-7 id="7" title="RMS Landing Form" html_class="hero__form"]' );
		$html = $render_hero();
		rms_harness_contains( $html, array( 'hero__col-left', 'hero__col-right', 'hero__form-card', 'grid-2', 'data-harness-form="1"' ) );

		// Whitespace-only shortcode must behave as empty: single column, no card.
		$GLOBALS['rms_hero_fields']['hero_form_shortcode'] = "  \n\t ";
		$html = $render_hero();
		rms_harness_contains( $html, array( 'hero__col-right', 'hero__form-card', 'grid-2', 'data-harness-form="1"' ), false );

		// Missing field: single column, no card, hero content intact.
		unset( $GLOBALS['rms_hero_fields']['hero_form_shortcode'] );
		$html = $render_hero();
		rms_harness_contains( $html, array( 'hero__col-right', 'hero__form-card', 'grid-2', 'data-harness-form="1"' ), false );
		rms_harness_contains( $html, array( 'hero__col-left', 'hero__title', 'grid-1' ) );
		break;

	// ─── ACF control: active CF7 (ready + existing states) ───────────────
	case 'control-active':
		rms_harness_wp_stubs();
		rms_harness_install_cf7();
		require $kernel_path; $h =& $GLOBALS['rms_harness'];
		rms_harness_assert( isset( $h['hooks']['acf/load_field/key=field_rms_cf7_landing_form_control'], $h['hooks']['acf/render_field/key=field_rms_cf7_landing_form_control'] ), 'load filter and dedicated render hook must be registered in admin' );

		// Hostile stored markup must never reach the message value.
		$clean = rms_cf7_landing_form_control_field( array( 'key' => 'field_rms_cf7_landing_form_control', 'message' => '<button onclick="alert(1)">Generate</button><input type="text" value="stale" /><p>stale</p>' ) );
		rms_harness_assert( is_array( $clean ) && '' === $clean['message'], 'the load filter must force an empty harmless message, never sanitizer-dependent markup' );

		// Ready state: enabled primary Generate targeting the separate footer form.
		$out = rms_harness_capture( 'rms_cf7_landing_form_control_render' );
		rms_harness_contains( $out, array( 'Generate RMS Landing Form', 'button-primary', 'form="rms-cf7-landing-form-generate"', 'type="submit"' ) );
		rms_harness_contains( $out, array( 'disabled', 'readonly', 'color:#b32d2e' ), false );
		rms_harness_assert( 0 === $h['save_calls'] && 0 === $h['option_writes'] && 0 === $h['meta_stamps'], 'ready render must not create or persist anything' );

		// Existing state: escaped readonly shortcode, enabled button, no overwrite.
		$h['posts'][1] = array( 'status' => 'publish', 'type' => 'wpcf7_contact_form', 'meta' => array( '_rms_theme_managed' => 'landing_form' ), 'title' => 'RMS Landing Form' );
		$h['options']['rms_cf7_landing_form_id'] = 1;
		$out = rms_harness_capture( 'rms_cf7_landing_form_control_render' );
		rms_harness_contains( $out, array( 'readonly', 'aria-label="Managed hero form shortcode"', 'html_class=&quot;hero__form&quot;', 'id=&quot;1&quot;', 'Generate RMS Landing Form', 'form="rms-cf7-landing-form-generate"' ) );
		rms_harness_contains( $out, array( 'disabled', '<button onclick' ), false );
		break;

	// ─── ACF control: CF7 inactive (red warning + disabled generate) ─────
	case 'control-inactive':
		rms_harness_wp_stubs();
		require $kernel_path; $h =& $GLOBALS['rms_harness'];
		rms_harness_assert( isset( $h['hooks']['acf/render_field/key=field_rms_cf7_landing_form_control'] ), 'the render hook must be registered even when CF7 is inactive' );
		$clean = rms_cf7_landing_form_control_field( array( 'key' => 'field_rms_cf7_landing_form_control', 'message' => '<p>stale</p>' ) );
		rms_harness_assert( '' === $clean['message'], 'the inactive load path must also force an empty message' );
		$out = rms_harness_capture( 'rms_cf7_landing_form_control_render' );
		rms_harness_contains( $out, array( 'Enable the Contact Form 7 plugin before adding the theme form.', 'color:#b32d2e', 'disabled', 'form="rms-cf7-landing-form-generate"', 'Generate RMS Landing Form' ) );
		rms_harness_contains( $out, array( 'readonly', 'button-primary' ), false );
		rms_harness_assert( 0 === $h['save_calls'] && 0 === $h['option_writes'] && 0 === $h['meta_stamps'], 'inactive render must not create anything' );
		break;

	default:
		fwrite( STDERR, "Unknown scenario: {$scenario}\n" );
		exit( 1 );
}

function rms_harness_wp_stubs(): void {
	if ( ! defined( 'ABSPATH' ) ) {
		define( 'ABSPATH', __DIR__ . '/' );
	}

	$GLOBALS['rms_harness'] = array(
		'options' => array(), 'posts' => array(), 'save_calls' => 0, 'option_writes' => 0, 'meta_stamps' => 0, 'hooks' => array(),
	);

	function get_option( $name, $default = false ) { return $GLOBALS['rms_harness']['options'][ $name ] ?? $default; }
	function update_option( $name, $value, $autoload = null ) { $GLOBALS['rms_harness']['option_writes']++; $GLOBALS['rms_harness']['options'][ $name ] = $value; return true; }
	function get_post_meta( $post_id, $key, $single = false ) { return $GLOBALS['rms_harness']['posts'][ (int) $post_id ]['meta'][ $key ] ?? ''; }
	function add_post_meta( $post_id, $key, $value, $unique = false ) { $GLOBALS['rms_harness']['meta_stamps']++; $GLOBALS['rms_harness']['posts'][ (int) $post_id ]['meta'][ $key ] = $value; return true; }
	function get_post_status( $post_id ) { return $GLOBALS['rms_harness']['posts'][ (int) $post_id ]['status'] ?? ''; }
	function get_post_type( $post_id ) { return $GLOBALS['rms_harness']['posts'][ (int) $post_id ]['type'] ?? ''; }
	function get_posts( $args ) { $ids = array(); foreach ( $GLOBALS['rms_harness']['posts'] as $id => $post ) { if ( ( $args['post_status'] ?? '' ) !== $post['status'] || ( $args['post_type'] ?? '' ) !== $post['type'] || ( $post['meta'][ $args['meta_key'] ?? '' ] ?? '' ) !== ( $args['meta_value'] ?? '' ) ) { continue; } $ids[] = $id; } return $ids; }
	function get_locale() { return 'en_US'; } function is_admin() { return true; } function __( $text, $domain = 'default' ) { return $text; }
	function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); } function esc_attr( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
	function esc_url( $url ) { return (string) $url; } function wp_unslash( $value ) { return $value; }
	function add_filter( $tag, $callback ) { $GLOBALS['rms_harness']['hooks'][ $tag ] = $callback; return true; } function add_action( $tag, $callback, $priority = 10, $accepted_args = 1 ) { $GLOBALS['rms_harness']['hooks'][ $tag ] = $callback; return true; }
	function sanitize_key( $key ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $key ) ); }
	function admin_url( $path = '' ) { return 'https://example.com/wp-admin/' . $path; }
	function add_query_arg( $args, $url = '' ) { return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . http_build_query( $args ); }
	function wp_nonce_field( $action = -1, $name = '_wpnonce', $referer = true, $echo = true ) { $html = '<input type="hidden" name="' . $name . '" value="rms-nonce-token" />'; if ( $echo ) { echo $html; } return $html; }
	function check_admin_referer( $action = -1, $query_arg = false ) { $key = $query_arg ? $query_arg : '_wpnonce'; if ( ( $_POST[ $key ] ?? '' ) !== 'rms-nonce-token' ) { throw new Exception( 'bad nonce' ); } return true; }
	function current_user_can( $capability ) { return true; }
	function wp_die( $message = '', $title = '', $args = array() ) { throw new Exception( 'wp_die' ); } function wp_safe_redirect( $url ) { throw new Exception( 'redirect ' . $url ); }
}

function rms_harness_install_cf7(): void {
	class WPCF7_ContactForm {
		public $harness_id = 0; public $harness_title = '';
		public function __construct( int $id, string $title ) { $this->harness_id = $id; $this->harness_title = $title; }
		public function id(): int { return $this->harness_id; }
		public function title(): string { return $this->harness_title; }
	}

	function wpcf7_save_contact_form( $args = array(), $context = 'save' ) { $h =& $GLOBALS['rms_harness']; $h['save_calls']++; $id = (int) ( $args['id'] ?? -1 ); if ( $id < 1 ) { $ids = array_keys( $h['posts'] ); $id = $ids ? max( $ids ) + 1 : 1; $h['posts'][ $id ] = array( 'title' => (string) ( $args['title'] ?? '' ), 'status' => 'publish', 'type' => 'wpcf7_contact_form', 'meta' => array() ); } return new WPCF7_ContactForm( $id, (string) ( $args['title'] ?? '' ) ); }

	function wpcf7_contact_form( $id ) { $post = $GLOBALS['rms_harness']['posts'][ (int) $id ] ?? null; $valid = null !== $post && 'wpcf7_contact_form' === $post['type'] && 'publish' === $post['status']; return $valid ? new WPCF7_ContactForm( (int) $id, $post['title'] ) : false; }
}
