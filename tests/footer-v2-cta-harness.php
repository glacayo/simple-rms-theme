<?php
/**
 * Focused harness for issue #118 (Footer V2 managed CTA strip).
 * Proves optional Theme Settings headline/label/URL fields, helper
 * `rms_get_footer_cta()`, contact-page URL fallback instead of a template
 * hash fragment, and escaped Footer V2 rendering. Footer V1 stays unchanged.
 * Usage: php tests/footer-v2-cta-harness.php
 */

if ( PHP_SAPI !== 'cli' ) { fwrite( STDERR, "CLI only.\n" ); exit( 1 ); }
$GLOBALS['rms_fcta_theme_root'] = dirname( __DIR__ );
$GLOBALS['rms_fcta_fields']     = array();
$GLOBALS['rms_fcta_pages']      = array();
$GLOBALS['rms_fcta_permalinks'] = array();
$failures = 0;

function rms_fcta_assert( bool $condition, string $name, string $message ): void {
	global $failures;
	if ( $condition ) { fwrite( STDOUT, "PASS {$name}\n" ); }
	else { $failures++; fwrite( STDERR, "FAIL {$name}: {$message}\n" ); }
}

if ( ! function_exists( '__' ) ) { function __( $text, $domain = 'default' ) { return $text; } }
if ( ! function_exists( 'esc_html' ) ) { function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); } }
if ( ! function_exists( 'esc_attr' ) ) { function esc_attr( $text ) { return esc_html( $text ); } }
if ( ! function_exists( 'esc_url' ) ) { function esc_url( $url ) { $url = trim( (string) $url ); return ( '' === $url || preg_match( '#^(javascript|data):#i', $url ) ) ? '' : $url; } }
if ( ! function_exists( 'esc_attr__' ) ) { function esc_attr__( $text, $domain = 'default' ) { return esc_attr( $text ); } }
if ( ! function_exists( 'home_url' ) ) { function home_url( $path = '/' ) { return 'https://example.test' . $path; } }
if ( ! function_exists( 'get_bloginfo' ) ) { function get_bloginfo( $show = '' ) { return 'name' === $show ? 'RMS Test Co' : ''; } }
if ( ! function_exists( 'add_action' ) ) { function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) { return true; } }
if ( ! function_exists( 'get_field' ) ) { function get_field( $selector, $post_id = false ) { return $GLOBALS['rms_fcta_fields'][ $selector ] ?? null; } }
if ( ! function_exists( 'has_custom_logo' ) ) { function has_custom_logo() { return false; } }
if ( ! function_exists( 'the_custom_logo' ) ) { function the_custom_logo() {} }
if ( ! function_exists( 'has_nav_menu' ) ) { function has_nav_menu( $location ) { return false; } }
if ( ! function_exists( 'wp_nav_menu' ) ) { function wp_nav_menu( $args = array() ) { return ''; } }
if ( ! function_exists( 'wp_date' ) ) { function wp_date( $format, $timestamp = null, $timezone = null ) { return '2026'; } }
if ( ! function_exists( 'get_page_by_path' ) ) {
	function get_page_by_path( $page_path, $output = null, $post_type = 'page' ) {
		return $GLOBALS['rms_fcta_pages'][ $page_path ] ?? null;
	}
}
if ( ! function_exists( 'get_permalink' ) ) {
	function get_permalink( $post = 0 ) {
		$id = is_object( $post ) ? (int) ( $post->ID ?? 0 ) : (int) $post;
		return $GLOBALS['rms_fcta_permalinks'][ $id ] ?? false;
	}
}

require $GLOBALS['rms_fcta_theme_root'] . '/inc/acf-theme-options.php';

function rms_fcta_get(): array {
	if ( ! function_exists( 'rms_get_footer_cta' ) ) {
		return array( 'headline' => '', 'title' => '', 'url' => '' );
	}
	// pi-lens-ignore: intelephense:P1010
	return rms_get_footer_cta();
}

function rms_fcta_render( array $fields, array $pages = array(), array $permalinks = array() ): string {
	$GLOBALS['rms_fcta_fields']     = $fields;
	$GLOBALS['rms_fcta_pages']      = $pages;
	$GLOBALS['rms_fcta_permalinks'] = $permalinks;
	ob_start();
	include $GLOBALS['rms_fcta_theme_root'] . '/templates/footer-v2.php';
	return (string) ob_get_clean();
}

$group  = json_decode( (string) file_get_contents( $GLOBALS['rms_fcta_theme_root'] . '/acf-json/group_rms_theme_settings.json' ), true );
$fields = is_array( $group ) ? ( $group['fields'] ?? array() ) : array();
$by_name = array();
$copyright_idx = null;
$header_tab_idx = null;
foreach ( $fields as $i => $field ) {
	if ( ! is_array( $field ) ) { continue; }
	if ( isset( $field['name'] ) && '' !== (string) $field['name'] ) { $by_name[ $field['name'] ] = array( 'field' => $field, 'idx' => $i ); }
	if ( 'company_footer_copyright' === ( $field['name'] ?? '' ) ) { $copyright_idx = $i; }
	if ( 'tab' === ( $field['type'] ?? '' ) && 'Header' === ( $field['label'] ?? '' ) ) { $header_tab_idx = $i; }
}

foreach ( array( 'company_footer_cta_headline' => 'text', 'company_footer_cta_label' => 'text', 'company_footer_cta_url' => 'url' ) as $name => $type ) {
	rms_fcta_assert( isset( $by_name[ $name ] ), 'test_schema_has_' . $name, 'missing Theme Settings field ' . $name );
	if ( isset( $by_name[ $name ] ) ) {
		$field = $by_name[ $name ]['field'];
		rms_fcta_assert( $type === ( $field['type'] ?? '' ), 'test_schema_type_' . $name, $name . ' must be type ' . $type );
		rms_fcta_assert( empty( $field['required'] ), 'test_schema_optional_' . $name, $name . ' must be optional' );
	}
}
if ( isset( $copyright_idx, $header_tab_idx, $by_name['company_footer_cta_headline'], $by_name['company_footer_cta_label'], $by_name['company_footer_cta_url'] ) ) {
	$h = $by_name['company_footer_cta_headline']['idx'];
	$l = $by_name['company_footer_cta_label']['idx'];
	$u = $by_name['company_footer_cta_url']['idx'];
	rms_fcta_assert( $copyright_idx < $h && $h < $l && $l < $u && $u < $header_tab_idx, 'test_schema_footer_tab_order', 'CTA fields must sit after copyright and before the Header tab' );
}

rms_fcta_assert( function_exists( 'rms_get_footer_cta' ), 'test_helper_exists', 'rms_get_footer_cta() must exist' );

$contact_pages = array( 'contact-us' => (object) array( 'ID' => 42 ) );
$contact_urls  = array( 42 => 'https://example.test/contact-us/' );

$GLOBALS['rms_fcta_fields']     = array(
	'company_footer_cta_headline' => '  Ready to paint?  ',
	'company_footer_cta_label'    => '  Book now  ',
	'company_footer_cta_url'      => 'https://example.test/book/',
);
$GLOBALS['rms_fcta_pages']      = $contact_pages;
$GLOBALS['rms_fcta_permalinks'] = $contact_urls;
$cta = rms_fcta_get();
rms_fcta_assert( array( 'headline' => 'Ready to paint?', 'title' => 'Book now', 'url' => 'https://example.test/book/' ) === $cta, 'test_helper_returns_trimmed_configured_values', 'configured CTA must return trimmed values, got ' . json_encode( $cta ) );

$GLOBALS['rms_fcta_fields'] = array(
	'company_footer_cta_headline' => " \t\n ",
	'company_footer_cta_label'    => '   ',
	'company_footer_cta_url'      => '  #  ',
);
$cta = rms_fcta_get();
rms_fcta_assert( 'Need a Free Estimate?' === $cta['headline'] && 'Get a Free Estimate' === $cta['title'] && 'https://example.test/contact-us/' === $cta['url'], 'test_helper_whitespace_falls_back_to_contact', 'whitespace/hash URL must use contact permalink and default copy, got ' . json_encode( $cta ) );

$GLOBALS['rms_fcta_fields']     = array();
$GLOBALS['rms_fcta_pages']      = array();
$GLOBALS['rms_fcta_permalinks'] = array();
$cta = rms_fcta_get();
rms_fcta_assert( 'https://example.test/#contact' === $cta['url'], 'test_helper_missing_contact_uses_home_hash', 'missing Contact page must use home_url(/#contact), got ' . json_encode( $cta['url'] ) );

$v1_src = (string) file_get_contents( $GLOBALS['rms_fcta_theme_root'] . '/templates/footer-v1.php' );
$v2_src = (string) file_get_contents( $GLOBALS['rms_fcta_theme_root'] . '/templates/footer-v2.php' );
rms_fcta_assert( false === strpos( $v1_src, 'rms_get_footer_cta' ) && false === strpos( $v1_src, 'Need a Free Estimate?' ), 'test_footer_v1_unchanged', 'Footer V1 must not consume the Footer V2 CTA helper' );
rms_fcta_assert( false !== strpos( $v2_src, 'rms_get_footer_cta()' ) && false === strpos( $v2_src, 'href="#contact"' ) && false === strpos( $v2_src, 'Need a Free Estimate?' ), 'test_footer_v2_has_no_hardcoded_cta', 'Footer V2 must render CTA through the helper without literals' );

$html = rms_fcta_render(
	array(
		'company_footer_cta_headline' => 'Ask <b>us</b>',
		'company_footer_cta_label'    => 'Go & see',
		'company_footer_cta_url'      => 'https://example.test/go/',
	)
);
rms_fcta_assert( false !== strpos( $html, 'Ask &lt;b&gt;us&lt;/b&gt;' ) && false === strpos( $html, '<b>us</b>' ), 'test_footer_v2_escapes_headline', 'headline must be escaped' );
rms_fcta_assert( false !== strpos( $html, 'Go &amp; see' ) && false !== strpos( $html, 'href="https://example.test/go/"' ), 'test_footer_v2_renders_configured_button', 'button label and URL must render escaped' );

$html = rms_fcta_render( array(), $contact_pages, $contact_urls );
rms_fcta_assert( false !== strpos( $html, 'Need a Free Estimate?' ) && false !== strpos( $html, 'Get a Free Estimate' ) && false !== strpos( $html, 'href="https://example.test/contact-us/"' ) && false === strpos( $html, 'href="#contact"' ), 'test_footer_v2_empty_uses_contact_permalink', 'empty fields must use contact permalink, not a template hash' );

if ( $failures > 0 ) { fwrite( STDERR, "\n{$failures} footer V2 CTA check(s) failed.\n" ); exit( 1 ); }
fwrite( STDOUT, "\nAll footer V2 CTA checks passed.\n" );
exit( 0 );
