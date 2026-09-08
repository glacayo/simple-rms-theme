<?php
/**
 * Focused behavior harness for issue #100 (Footer V2 dynamic settings).
 * Proves: a `Footer` tab owning `company_footer_version` + optional plain
 * textarea `company_footer_about`; `footer-menu`/`footer-services` replacing
 * the legacy unconsumed `footer` location; About rendering only when
 * configured (trimmed, escaped, no fallback); branding precedence footer
 * logo -> header logo -> linked site name (has_custom_logo() excluded);
 * Menu/Services columns rendering only when assigned via wp_nav_menu
 * (container=false, fallback_cb=false, footer-v2__list, depth 1) and failing
 * open otherwise; preserved social/contact/CTA/payment/language/copyright;
 * and that the wizard never assigns footer locations or maps
 * company_services to URLs. Usage: php tests/footer-v2-nav-schema-harness.php
 */

if ( PHP_SAPI !== 'cli' ) { fwrite( STDERR, "CLI only.\n" ); exit( 1 ); }
$GLOBALS['rms_v2h_theme_root'] = dirname( __DIR__ );
$GLOBALS['rms_v2h_custom_logo'] = false;
$failures = 0;
function rms_v2h_assert( bool $condition, string $name, string $message ): void {
    global $failures;
    if ( $condition ) { fwrite( STDOUT, "PASS {$name}\n" ); }
    else { $failures++; fwrite( STDERR, "FAIL {$name}: {$message}\n" ); }
}

// ─── Minimal WordPress stubs (guarded) ─────────────────────────────────────
if ( ! function_exists( '__' ) ) { function __( $text, $domain = 'default' ) { return $text; } }
if ( ! function_exists( 'esc_html' ) ) { function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); } }
if ( ! function_exists( 'esc_attr' ) ) { function esc_attr( $text ) { return esc_html( $text ); } }
if ( ! function_exists( 'esc_url' ) ) { function esc_url( $url ) { $url = trim( (string) $url ); return ( '' === $url || preg_match( '#^(javascript|data):#i', $url ) ) ? '' : $url; } }
if ( ! function_exists( 'esc_attr__' ) ) { function esc_attr__( $text, $domain = 'default' ) { return esc_attr( $text ); } }
if ( ! function_exists( 'esc_html__' ) ) { function esc_html__( $text, $domain = 'default' ) { return esc_html( $text ); } }
if ( ! function_exists( 'home_url' ) ) { function home_url( $path = '/' ) { return 'https://example.test' . $path; } }
if ( ! function_exists( 'get_bloginfo' ) ) { function get_bloginfo( $show = '' ) { if ( 'name' === $show ) { return (string) ( $GLOBALS['rms_v2h_fields']['blogname'] ?? 'RMS Test Co' ); } return 'charset' === $show ? 'UTF-8' : ''; } }
if ( ! function_exists( 'add_action' ) ) { function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) { return true; } }
if ( ! function_exists( 'add_filter' ) ) { function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) { return true; } }
if ( ! function_exists( 'add_theme_support' ) ) { function add_theme_support( $feature, ...$args ) { return true; } }
if ( ! function_exists( 'add_image_size' ) ) { function add_image_size( $name, $width = 0, $height = 0, $crop = false ) { return true; } }
if ( ! function_exists( 'sanitize_key' ) ) { function sanitize_key( $key ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) ); } }
if ( ! function_exists( 'register_nav_menus' ) ) { function register_nav_menus( $locations = array() ) { $GLOBALS['rms_v2h_registered_menus'] = is_array( $locations ) ? $locations : array(); return true; } }
if ( ! function_exists( 'get_field' ) ) { function get_field( $selector, $post_id = false ) { return $GLOBALS['rms_v2h_fields'][ $selector ] ?? null; } }
if ( ! function_exists( 'has_nav_menu' ) ) { function has_nav_menu( $location ) { return is_array( $GLOBALS['rms_v2h_nav_menus'] ?? null ) && array_key_exists( $location, $GLOBALS['rms_v2h_nav_menus'] ); } }
if ( ! function_exists( 'wp_nav_menu' ) ) { function wp_nav_menu( $args = array() ) { $GLOBALS['rms_v2h_nav_menu_args'][] = $args; $class = is_array( $args ) ? (string) ( $args['menu_class'] ?? '' ) : ''; echo '<ul class="' . esc_attr( $class ) . '"><li><a href="https://example.test/footer-page/">Footer Page</a></li></ul>'; return ''; } }
if ( ! function_exists( 'has_custom_logo' ) ) { function has_custom_logo() { return ! empty( $GLOBALS['rms_v2h_custom_logo'] ); } }
if ( ! function_exists( 'the_custom_logo' ) ) { function the_custom_logo() { echo '<span class="custom-logo-marker">CUSTOM LOGO</span>'; } }
if ( ! class_exists( 'Walker_Nav_Menu' ) ) { class Walker_Nav_Menu {} }

require $GLOBALS['rms_v2h_theme_root'] . '/inc/acf-theme-options.php';
require $GLOBALS['rms_v2h_theme_root'] . '/inc/setup.php';

/** Render templates/footer-v2.php with the given options + menu assignment. */
function rms_v2h_render_footer( array $fields, array $menus = array() ): string {
    $GLOBALS['rms_v2h_fields'] = $fields;
    $GLOBALS['rms_v2h_nav_menus'] = $menus;
    $GLOBALS['rms_v2h_nav_menu_args'] = array();
    ob_start();
    include $GLOBALS['rms_v2h_theme_root'] . '/templates/footer-v2.php';
    return (string) ob_get_clean();
}

// ─── 1. Schema: Footer tab owns footer version + about textarea ───────────
$group = json_decode( (string) file_get_contents( $GLOBALS['rms_v2h_theme_root'] . '/acf-json/group_rms_theme_settings.json' ), true );
$fields = is_array( $group ) ? ( $group['fields'] ?? array() ) : array();
$rms_v2h_footer_tab = null;
$rms_v2h_about_field = null;
foreach ( $fields as $rms_v2h_i => $rms_v2h_field ) {
    if ( ! is_array( $rms_v2h_field ) ) { continue; }
    if ( 'tab' === ( $rms_v2h_field['type'] ?? '' ) && 'Footer' === ( $rms_v2h_field['label'] ?? '' ) ) { $rms_v2h_footer_tab = $rms_v2h_field; $rms_v2h_footer_tab_i = $rms_v2h_i; }
    if ( 'company_footer_about' === ( $rms_v2h_field['name'] ?? '' ) ) { $rms_v2h_about_field = $rms_v2h_field; $rms_v2h_about_idx = $rms_v2h_i; }
    if ( 'company_footer_version' === ( $rms_v2h_field['name'] ?? '' ) ) { $rms_v2h_version_idx = $rms_v2h_i; }
    if ( 'tab' === ( $rms_v2h_field['type'] ?? '' ) && 'Header' === ( $rms_v2h_field['label'] ?? '' ) ) { $rms_v2h_header_tab_i = $rms_v2h_i; }
}
rms_v2h_assert( null !== $rms_v2h_footer_tab, 'test_theme_settings_footer_tab_exists', 'group_rms_theme_settings.json has no Footer tab' );
rms_v2h_assert( null !== $rms_v2h_about_field, 'test_theme_settings_footer_about_field_exists', 'no company_footer_about field in group_rms_theme_settings.json' );
if ( null !== $rms_v2h_about_field ) {
    rms_v2h_assert( 'textarea' === ( $rms_v2h_about_field['type'] ?? '' ), 'test_footer_about_is_plain_textarea', 'company_footer_about must be a plain textarea, got ' . json_encode( $rms_v2h_about_field['type'] ?? null ) );
    rms_v2h_assert( empty( $rms_v2h_about_field['required'] ), 'test_footer_about_is_optional', 'company_footer_about must be optional (required falsy)' );
}
if ( isset( $rms_v2h_footer_tab_i, $rms_v2h_version_idx, $rms_v2h_about_idx, $rms_v2h_header_tab_i ) ) {
    rms_v2h_assert( $rms_v2h_footer_tab_i < $rms_v2h_version_idx && $rms_v2h_version_idx < $rms_v2h_about_idx && $rms_v2h_about_idx < $rms_v2h_header_tab_i,
        'test_footer_tab_owns_footer_fields_in_order', 'Footer tab must precede company_footer_version then company_footer_about, all before the Header tab' );
}

// ─── 2. Menu registration replaces the legacy unconsumed location ─────────
$GLOBALS['rms_v2h_registered_menus'] = array();
// pi-lens-ignore: intelephense:P1010
simple_rms_setup();
$rms_v2h_menus = $GLOBALS['rms_v2h_registered_menus'];
rms_v2h_assert( ( $rms_v2h_menus['footer-menu'] ?? null ) === 'Footer Menu', 'test_menu_locations_register_footer_menu', 'register_nav_menus must register footer-menu with label "Footer Menu", got ' . json_encode( $rms_v2h_menus ) );
rms_v2h_assert( ( $rms_v2h_menus['footer-services'] ?? null ) === 'Services Footer', 'test_menu_locations_register_footer_services', 'register_nav_menus must register footer-services with label "Services Footer"' );
rms_v2h_assert( ! array_key_exists( 'footer', $rms_v2h_menus ), 'test_legacy_footer_location_removed', 'the legacy unconsumed "footer" location must be removed' );
rms_v2h_assert( count( array_unique( array_values( $rms_v2h_menus ) ) ) === count( $rms_v2h_menus ), 'test_menu_labels_are_unique', 'menu location labels must be unique' );

// ─── 3. About: configured/trimmed/escaped; empty hides; no roofing copy ───
$GLOBALS['rms_v2h_nav_menus'] = array();
$rms_v2h_about_html = rms_v2h_render_footer( array( 'company_footer_about' => "  Trusted exterior company with honest local service.  " ) );
rms_v2h_assert( (bool) preg_match( '/<p class="footer-v2__about">Trusted exterior company with honest local service\.<\/p>/', $rms_v2h_about_html ),
    'test_footer_about_renders_trimmed', 'configured About text must render trimmed inside <p class="footer-v2__about">' );
$rms_v2h_escape_html = rms_v2h_render_footer( array( 'company_footer_about' => '<b>Bold</b> & more' ) );
rms_v2h_assert( false !== strpos( $rms_v2h_escape_html, '&lt;b&gt;Bold&lt;/b&gt;' ) && false === strpos( $rms_v2h_escape_html, '<b>Bold</b>' ),
    'test_footer_about_is_escaped_plain_text', 'About must render as escaped plain text (no raw HTML)' );
$rms_v2h_blank_html = rms_v2h_render_footer( array( 'company_footer_about' => "  \t\n  " ) );
rms_v2h_assert( false === strpos( $rms_v2h_blank_html, 'footer-v2__about' ) && false === stripos( $rms_v2h_blank_html, 'roofing' ),
    'test_footer_about_hidden_when_blank', 'whitespace-only About must render no paragraph and no hardcoded roofing copy' );
$rms_v2h_empty_html = rms_v2h_render_footer( array() );
rms_v2h_assert( false === strpos( $rms_v2h_empty_html, 'footer-v2__about' ) && false === stripos( $rms_v2h_empty_html, 'roofing' ),
    'test_footer_about_hidden_when_unconfigured', 'unconfigured About must render no paragraph and no invented fallback copy' );

// ─── 4. Logo precedence: footer option -> header option -> site name ──────
$rms_v2h_footer_logo_html = rms_v2h_render_footer( array( 'company_logo_footer' => 'https://example.test/footer-logo.png' ) );
rms_v2h_assert( false !== strpos( $rms_v2h_footer_logo_html, 'src="https://example.test/footer-logo.png"' ),
    'test_footer_logo_precedence_footer_option_wins', 'a configured footer logo must render' );
$rms_v2h_header_logo_html = rms_v2h_render_footer( array( 'company_logo_header' => 'https://example.test/header-logo.png' ) );
rms_v2h_assert( false !== strpos( $rms_v2h_header_logo_html, 'src="https://example.test/header-logo.png"' ),
    'test_footer_logo_precedence_header_option_fallback', 'with no footer logo, the header logo option must be used' );
$rms_v2h_name_html = rms_v2h_render_footer( array( 'blogname' => 'RMS Test Co' ) );
rms_v2h_assert( (bool) preg_match( '/<a href="https:\/\/example\.test\/" class="footer-v2__logo-placeholder">RMS Test Co<\/a>/', $rms_v2h_name_html ) && false === strpos( $rms_v2h_name_html, '<img' ),
    'test_footer_logo_precedence_site_name_fallback', 'with no logo options, an accessible linked site name must render (no img)' );
$GLOBALS['rms_v2h_custom_logo'] = true;
$rms_v2h_custom_html = rms_v2h_render_footer( array( 'company_logo_footer' => 'https://example.test/footer-logo.png' ) );
$GLOBALS['rms_v2h_custom_logo'] = false;
rms_v2h_assert( false === strpos( $rms_v2h_custom_html, 'custom-logo-marker' ) && false !== strpos( $rms_v2h_custom_html, 'src="https://example.test/footer-logo.png"' ),
    'test_footer_logo_negative_control_custom_logo_loses', 'has_custom_logo() must not win over the Theme Options footer logo' );

// ─── 5. Menu/Services columns: assigned dynamic, unassigned fail-open ─────
$rms_v2h_assigned_html = rms_v2h_render_footer( array(), array( 'footer-menu' => 12, 'footer-services' => 34 ) );
rms_v2h_assert( false !== strpos( $rms_v2h_assigned_html, '>Menu</h2>' ) && false !== strpos( $rms_v2h_assigned_html, '>Services</h2>' ),
    'test_footer_columns_render_headings_when_assigned', 'assigned locations must render the Menu and Services headings' );
$rms_v2h_menu_args = $GLOBALS['rms_v2h_nav_menu_args'];
rms_v2h_assert( 2 === count( $rms_v2h_menu_args ), 'test_footer_columns_use_wp_nav_menu_twice', 'wp_nav_menu must be called exactly once per assigned location, got ' . count( $rms_v2h_menu_args ) );
$rms_v2h_locations_seen = array();
foreach ( $rms_v2h_menu_args as $rms_v2h_args ) {
    $rms_v2h_locations_seen[] = $rms_v2h_args['theme_location'] ?? '';
    rms_v2h_assert( false === $rms_v2h_args['fallback_cb'] && false === $rms_v2h_args['container'] && 'footer-v2__list' === $rms_v2h_args['menu_class'] && 1 === $rms_v2h_args['depth'],
        'test_footer_wp_nav_menu_args_contract (' . ( $rms_v2h_args['theme_location'] ?? '?' ) . ')',
        'wp_nav_menu args must be container=false, fallback_cb=false, menu_class=footer-v2__list, depth=1, got ' . json_encode( $rms_v2h_args ) );
}
sort( $rms_v2h_locations_seen );
rms_v2h_assert( array( 'footer-menu', 'footer-services' ) === $rms_v2h_locations_seen, 'test_footer_columns_use_registered_locations',
    'wp_nav_menu must target exactly the footer-menu and footer-services locations' );
rms_v2h_assert( 2 === substr_count( $rms_v2h_assigned_html, '<ul class="footer-v2__list">' ) && false === strpos( $rms_v2h_assigned_html, 'href="#"' ) && false === stripos( $rms_v2h_assigned_html, 'Roof Installation' ),
    'test_footer_columns_dynamic_lists_no_placeholders', 'assigned columns must render two dynamic lists with no dead hrefs or roofing placeholders' );
$rms_v2h_unassigned_html = rms_v2h_render_footer( array() );
rms_v2h_assert( false === strpos( $rms_v2h_unassigned_html, '>Menu</h2>' ) && false === strpos( $rms_v2h_unassigned_html, '>Services</h2>' ),
    'test_footer_columns_suppressed_when_unassigned', 'unassigned locations must suppress the orphan Menu/Services headings' );
rms_v2h_assert( false === strpos( $rms_v2h_unassigned_html, 'class="footer-v2__list"' ) && false === strpos( $rms_v2h_unassigned_html, 'href="#"' ),
    'test_footer_columns_fail_open_no_orphans', 'unassigned columns must leave no orphan list or dead href' );
rms_v2h_assert( 1 === substr_count( strtolower( $rms_v2h_unassigned_html ), '<footer' ),
    'test_footer_renders_one_footer_when_unassigned', 'the footer must still render exactly once when no menus are assigned (fail-open)' );

// ─── 6. Preserved sections: social, contact, CTA, meta, copyright ─────────
$rms_v2h_kept_html = rms_v2h_render_footer( array(
    'company_social_media' => array( array( 'social_is_active' => 1, 'social_platform' => 'facebook', 'social_url' => 'https://example.test/facebook', 'social_label' => 'Facebook' ) ),
    'company_phones' => array( array( 'phone_number' => '+15551234567', 'phone_label' => 'Office' ) ),
    'company_language' => 'English, Spanish',
    'company_payment_methods' => array( array( 'payment_method_name' => 'Visa' ) ),
) );
rms_v2h_assert( 1 === substr_count( strtolower( $rms_v2h_kept_html ), '<nav' ) && false !== strpos( $rms_v2h_kept_html, 'footer-v2__social-link' ),
    'test_footer_preserves_social_nav', 'the social nav must render exactly once with footer-v2__social-link' );
rms_v2h_assert( false !== strpos( $rms_v2h_kept_html, 'tel:+15551234567' ) && false !== strpos( $rms_v2h_kept_html, 'Languages:' ) && false !== strpos( $rms_v2h_kept_html, 'Payment Methods:' ) && false !== strpos( $rms_v2h_kept_html, 'Visa' ),
    'test_footer_preserves_contact_and_meta', 'phones, languages and payment methods must keep rendering' );
rms_v2h_assert( false !== strpos( $rms_v2h_kept_html, 'footer-v2__cta-button' ) && false !== strpos( $rms_v2h_kept_html, 'Get a Free Estimate' ) && false !== strpos( $rms_v2h_kept_html, 'footer-v2__copyright' ),
    'test_footer_preserves_cta_and_copyright', 'the estimate CTA and copyright block must keep rendering' );

// ─── 7. Wizard never synthesizes footer links ─────────────────────────────
$rms_v2h_wizard_guard = array();
foreach ( glob( $GLOBALS['rms_v2h_theme_root'] . '/inc/wizard/*.php' ) as $rms_v2h_wizard_file ) {
    $rms_v2h_wizard_src = (string) file_get_contents( $rms_v2h_wizard_file );
    $rms_v2h_wizard_name = basename( $rms_v2h_wizard_file );
    if ( preg_match_all( '/assign_location\s*\(\s*[\'"]([^\'"]+)[\'"]/', $rms_v2h_wizard_src, $rms_v2h_loc_matches ) ) {
        foreach ( $rms_v2h_loc_matches[1] as $rms_v2h_loc ) {
            if ( ! in_array( $rms_v2h_loc, array( 'primary', 'mobile' ), true ) ) { $rms_v2h_wizard_guard[] = "{$rms_v2h_wizard_name} assigns unexpected location '{$rms_v2h_loc}'"; }
        }
    }
    if ( false !== strpos( $rms_v2h_wizard_src, 'footer-menu' ) || false !== strpos( $rms_v2h_wizard_src, 'footer-services' ) ) { $rms_v2h_wizard_guard[] = "{$rms_v2h_wizard_name} references footer locations"; }
    $rms_v2h_writes_menus = false !== strpos( $rms_v2h_wizard_src, 'wp_update_nav_menu_item' ) || false !== strpos( $rms_v2h_wizard_src, 'wp_nav_menu' );
    if ( $rms_v2h_writes_menus && false !== strpos( $rms_v2h_wizard_src, 'company_services' ) ) { $rms_v2h_wizard_guard[] = "{$rms_v2h_wizard_name} mixes company_services into menu synthesis"; }
}
rms_v2h_assert( array() === $rms_v2h_wizard_guard, 'test_wizard_does_not_synthesize_footer_links', implode( '; ', $rms_v2h_wizard_guard ) );

// ─── Summary ───────────────────────────────────────────────────────────────
if ( $failures > 0 ) { fwrite( STDERR, "\n{$failures} footer V2 nav/schema check(s) failed.\n" ); exit( 1 ); }
fwrite( STDOUT, "\nAll footer V2 nav/schema checks passed.\n" );
exit( 0 );