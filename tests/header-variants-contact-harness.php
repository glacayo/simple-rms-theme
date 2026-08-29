<?php
/**
 * Focused regression harness for issue #55 (header variants contact wiring).
 *
 * Proves the shipped header variants consume existing Theme Options contact
 * facts instead of hardcoded demo literals, and that estimate CTAs resolve to
 * the Contact page permalink (or a safe fallback) rather than a dead `href="#"`.
 *
 * No framework. Single process; `get_field` and the page resolver are stubbed
 * via globals and toggled between scenarios.
 *
 * Usage: php tests/header-variants-contact-harness.php
 */

if ( PHP_SAPI !== 'cli' ) {
    fwrite( STDERR, "CLI only.\n" );
    exit( 1 );
}

$theme_root = dirname( __DIR__ );
$failures   = 0;

function rms_header_fail( string $name, string $detail ): void {
    global $failures;
    $failures++;
    fwrite( STDERR, "FAIL {$name}: {$detail}\n" );
}

function rms_header_pass( string $name ): void {
    fwrite( STDOUT, "PASS {$name}\n" );
}

function rms_header_assert( $condition, string $name, string $detail ): void {
    if ( $condition ) {
        rms_header_pass( $name );
        return;
    }
    rms_header_fail( $name, $detail );
}

function rms_header_render( string $template ): string {
    global $theme_root;
    $path = $theme_root . '/templates/' . $template;
    if ( ! is_readable( $path ) ) {
        return '';
    }
    ob_start();
    include $path;
    $out = ob_get_clean();
    return is_string( $out ) ? $out : '';
}

function rms_header_setup( array $fields, array $pages = array(), array $permalinks = array() ): void {
    $GLOBALS['rms_header_fields']     = $fields;
    $GLOBALS['rms_header_pages']      = $pages;
    $GLOBALS['rms_header_permalinks'] = $permalinks;
}

// ─── WordPress + ACF stubs ─────────────────────────────────────────────────

if ( ! function_exists( '__' ) ) {
    function __( $text, $domain = 'default' ) {
        return $text;
    }
}

if ( ! function_exists( 'add_action' ) ) {
    function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
        return true;
    }
}

if ( ! function_exists( 'add_filter' ) ) {
    function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
        return true;
    }
}

if ( ! function_exists( 'sanitize_key' ) ) {
    function sanitize_key( $key ) {
        $key = strtolower( (string) $key );
        return preg_replace( '/[^a-z0-9_\-]/', '', $key );
    }
}

if ( ! function_exists( 'get_field' ) ) {
    function get_field( $selector, $post_id = false ) {
        return $GLOBALS['rms_header_fields'][ $selector ] ?? null;
    }
}

if ( ! function_exists( 'get_page_by_path' ) ) {
    function get_page_by_path( $slug, $output = null, $post_type = 'page' ) {
        $id = $GLOBALS['rms_header_pages'][ $slug ] ?? null;
        if ( null === $id ) {
            return null;
        }
        return (object) array( 'ID' => $id );
    }
}

if ( ! function_exists( 'get_permalink' ) ) {
    function get_permalink( $id ) {
        return $GLOBALS['rms_header_permalinks'][ $id ] ?? '';
    }
}

if ( ! function_exists( 'home_url' ) ) {
    function home_url( $path = '/' ) {
        return 'https://example.test' . $path;
    }
}

if ( ! function_exists( 'esc_url' ) ) {
    function esc_url( $url ) {
        $url = trim( (string) $url );
        if ( '' === $url || preg_match( '#^(javascript|data):#i', $url ) ) {
            return '';
        }
        return $url;
    }
}

if ( ! function_exists( 'esc_attr' ) ) {
    function esc_attr( $text ) {
        return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
    }
}

if ( ! function_exists( 'esc_html' ) ) {
    function esc_html( $text ) {
        return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
    }
}

if ( ! function_exists( 'esc_attr_e' ) ) {
    function esc_attr_e( $text, $domain = 'default' ) {
        echo esc_attr( $text );
    }
}

if ( ! function_exists( 'esc_html_e' ) ) {
    function esc_html_e( $text, $domain = 'default' ) {
        echo esc_html( $text );
    }
}

if ( ! function_exists( 'esc_attr__' ) ) {
    function esc_attr__( $text, $domain = 'default' ) {
        return esc_attr( $text );
    }
}

if ( ! function_exists( 'esc_html__' ) ) {
    function esc_html__( $text, $domain = 'default' ) {
        return esc_html( $text );
    }
}

if ( ! function_exists( 'get_bloginfo' ) ) {
    function get_bloginfo( $show = '' ) {
        return 'name' === $show ? 'Simple RMS' : ( 'charset' === $show ? 'UTF-8' : '' );
    }
}

if ( ! function_exists( 'bloginfo' ) ) {
    function bloginfo( $show = '' ) {
        echo get_bloginfo( $show );
    }
}

if ( ! function_exists( 'has_custom_logo' ) ) {
    function has_custom_logo() {
        return false;
    }
}

if ( ! function_exists( 'the_custom_logo' ) ) {
    function the_custom_logo() {}
}

if ( ! function_exists( 'wp_nav_menu' ) ) {
    function wp_nav_menu( $args = array() ) {
        return '';
    }
}

if ( ! class_exists( 'RMS_Walker_Nav_Primary' ) ) {
    class RMS_Walker_Nav_Primary {}
}
if ( ! class_exists( 'RMS_Walker_Nav_Mobile' ) ) {
    class RMS_Walker_Nav_Mobile {}
}
if ( ! class_exists( 'RMS_Walker_Nav_V2_Desktop' ) ) {
    class RMS_Walker_Nav_V2_Desktop {}
}
if ( ! class_exists( 'RMS_Walker_Nav_V2_Mobile' ) ) {
    class RMS_Walker_Nav_V2_Mobile {}
}
if ( ! class_exists( 'RMS_Walker_Nav_V3_Desktop' ) ) {
    class RMS_Walker_Nav_V3_Desktop {}
}
if ( ! class_exists( 'RMS_Walker_Nav_V3_Mobile' ) ) {
    class RMS_Walker_Nav_V3_Mobile {}
}

require $theme_root . '/inc/acf-theme-options.php';

// ─── Scenario 1: configured contact facts are consumed ─────────────────────

rms_header_setup(
    array(
        'company_phones'         => array( array( 'phone_number' => '(407) 555-0100' ) ),
        'company_emails'         => array( array( 'email_address' => 'contact@example.com' ) ),
        'company_social_media'   => array(),
        'company_address_line_1' => '1 Test Way',
        'company_address_line_2' => '',
        'company_city'           => 'Testville',
        'company_state'          => 'TX',
        'company_postal_code'    => '75001',
    ),
    array( 'contact-us' => 42 ),
    array( 42 => 'https://example.test/contact-us/' )
);

$h1 = rms_header_render( 'header-one.php' );
$h2 = rms_header_render( 'header-two.php' );
$h3 = rms_header_render( 'header-three.php' );

// 1. Phone consumes rms_get_primary_phone() in every variant; old literals gone.
rms_header_assert( false !== strpos( $h1, 'tel:4075550100' ), 'test_header_variants_phone_consume_theme_options (header-one tel)', 'header-one missing sanitized tel URI for configured phone' );
rms_header_assert( false !== strpos( $h1, '(407) 555-0100' ), 'test_header_variants_phone_consume_theme_options (header-one text)', 'header-one missing configured phone text' );
rms_header_assert( false === strpos( $h1, '(414) 246-8257' ), 'test_header_variants_phone_consume_theme_options (header-one no old literal)', 'header-one still shows hardcoded (414) 246-8257' );

rms_header_assert( false !== strpos( $h2, 'tel:4075550100' ), 'test_header_variants_phone_consume_theme_options (header-two tel)', 'header-two missing sanitized tel URI' );
rms_header_assert( false !== strpos( $h2, '(407) 555-0100' ), 'test_header_variants_phone_consume_theme_options (header-two text)', 'header-two missing configured phone text' );
rms_header_assert( false === strpos( $h2, '(407) 555-0199' ), 'test_header_variants_phone_consume_theme_options (header-two no old literal)', 'header-two still shows hardcoded (407) 555-0199' );

rms_header_assert( 2 === substr_count( $h3, 'tel:4075550100' ), 'test_header_variants_phone_consume_theme_options (header-three desktop+mobile)', 'header-three must render tel:4075550100 exactly twice' );
rms_header_assert( false === strpos( $h3, '(407) 555-0199' ), 'test_header_variants_phone_consume_theme_options (header-three no old literal)', 'header-three still shows hardcoded (407) 555-0199' );
rms_header_assert( false === strpos( $h3, 'tel:+14075550199' ), 'test_header_variants_phone_consume_theme_options (header-three no old href)', 'header-three still shows hardcoded tel:+14075550199' );

// 2. Header-two email consumes rms_get_primary_email().
rms_header_assert( false !== strpos( $h2, 'mailto:contact@example.com' ), 'test_header_v2_email_consume_theme_options (mailto)', 'header-two missing mailto URI for configured email' );
rms_header_assert( false !== strpos( $h2, 'contact@example.com' ), 'test_header_v2_email_consume_theme_options (text)', 'header-two missing configured email text' );
rms_header_assert( false === strpos( $h2, 'hello@example.com' ), 'test_header_v2_email_consume_theme_options (no old literal)', 'header-two still shows hardcoded hello@example.com' );

// 3. Header-one address consumes company address option fields.
rms_header_assert( false !== strpos( $h1, '1 Test Way, Testville, TX, 75001' ), 'test_header_one_address_consume_theme_options', 'header-one missing assembled address from option fields' );
rms_header_assert( false === strpos( $h1, '1234 Oak Ridge Ave' ), 'test_header_one_address_consume_theme_options (no old literal)', 'header-one still shows hardcoded 1234 Oak Ridge Ave' );

// 6. Estimate CTAs resolve to the Contact permalink; never href="#".
rms_header_assert( false !== strpos( $h1, 'https://example.test/contact-us/' ), 'test_header_one_and_v2_estimate_cta_resolves (header-one permalink)', 'header-one CTA did not resolve to contact permalink' );
rms_header_assert( false !== strpos( $h2, 'https://example.test/contact-us/' ), 'test_header_one_and_v2_estimate_cta_resolves (header-two permalink)', 'header-two CTA did not resolve to contact permalink' );
rms_header_assert( false === strpos( $h1, 'href="#"' ), 'test_header_one_and_v2_estimate_cta_resolves (header-one no dead href)', 'header-one CTA still has dead href="#"' );
rms_header_assert( false === strpos( $h2, 'href="#"' ), 'test_header_one_and_v2_estimate_cta_resolves (header-two no dead href)', 'header-two CTA still has dead href="#"' );

// ─── Scenario 2: empty contact values omit matching items ──────────────────

rms_header_setup(
    array(
        'company_phones'       => array(),
        'company_emails'       => array(),
        'company_social_media' => array(),
    ),
    array( 'contact-us' => 42 ),
    array( 42 => 'https://example.test/contact-us/' )
);

$h1 = rms_header_render( 'header-one.php' );
$h2 = rms_header_render( 'header-two.php' );
$h3 = rms_header_render( 'header-three.php' );

// 4. Empty phone/email/address omit the corresponding header nodes.
rms_header_assert( false === strpos( $h1, 'tel:' ), 'test_header_variants_empty_contact_values_omit_items (header-one phone omitted)', 'header-one rendered a phone item with empty phone' );
rms_header_assert( false === strpos( $h1, 'Testville' ) && false === strpos( $h1, '1234 Oak Ridge Ave' ), 'test_header_variants_empty_contact_values_omit_items (header-one address omitted)', 'header-one rendered an address item with empty address' );

rms_header_assert( false === strpos( $h2, 'rms-header-v2__phone' ), 'test_header_variants_empty_contact_values_omit_items (header-two phone omitted)', 'header-two rendered phone with empty phone' );
rms_header_assert( false === strpos( $h2, 'rms-header-v2__email' ), 'test_header_variants_empty_contact_values_omit_items (header-two email omitted)', 'header-two rendered email with empty email' );
rms_header_assert( false === strpos( $h2, 'mailto:' ), 'test_header_variants_empty_contact_values_omit_items (header-two no mailto)', 'header-two rendered a mailto link with empty email' );

rms_header_assert( false === strpos( $h3, 'rms-header-v3__phone' ), 'test_header_variants_empty_contact_values_omit_items (header-three desktop omitted)', 'header-three rendered desktop phone with empty phone' );
rms_header_assert( false === strpos( $h3, 'rms-header-v3__mobile-phone' ), 'test_header_variants_empty_contact_values_omit_items (header-three mobile omitted)', 'header-three rendered mobile phone with empty phone' );
rms_header_assert( false === strpos( $h3, 'tel:' ), 'test_header_variants_empty_contact_values_omit_items (header-three no tel)', 'header-three rendered a tel link with empty phone' );

// ─── Scenario 3: sanitized + escaped protocols ─────────────────────────────

rms_header_setup(
    array(
        'company_phones'         => array( array( 'phone_number' => '+1 (407) 555-0100' ) ),
        'company_emails'         => array( array( 'email_address' => 'contact@example.com' ) ),
        'company_social_media'   => array(),
        'company_address_line_1' => '1 <b>Test</b> Way',
        'company_city'           => 'Testville',
        'company_state'          => 'TX',
        'company_postal_code'    => '75001',
    ),
    array(),
    array()
);

$h1 = rms_header_render( 'header-one.php' );
$h2 = rms_header_render( 'header-two.php' );
$h3 = rms_header_render( 'header-three.php' );

// 5a. Leading plus preserved, non-digits stripped: tel:+14075550100.
rms_header_assert( false !== strpos( $h1, 'tel:+14075550100' ), 'test_header_variants_contact_links_use_sanitized_protocols (header-one leading plus)', 'header-one tel URI did not preserve leading + and digits' );
rms_header_assert( 2 === substr_count( $h3, 'tel:+14075550100' ), 'test_header_variants_contact_links_use_sanitized_protocols (header-three leading plus)', 'header-three tel URI did not preserve leading + and digits in both surfaces' );

// 5b. Visible text escaped; address HTML escaped.
rms_header_assert( false === strpos( $h1, '<b>Test</b>' ), 'test_header_variants_contact_links_use_sanitized_protocols (address escaped)', 'header-one address HTML was not escaped' );
rms_header_assert( false !== strpos( $h1, '&lt;b&gt;Test&lt;/b&gt;' ), 'test_header_variants_contact_links_use_sanitized_protocols (address entities)', 'header-one address did not emit escaped entities' );

// 5c. Injection payload is stripped from href and escaped in text.
rms_header_setup(
    array(
        'company_phones'       => array( array( 'phone_number' => "(407) 555-0100\"><script>alert('x')</script>" ) ),
        'company_emails'       => array( array( 'email_address' => 'contact@example.com' ) ),
        'company_social_media' => array(),
    ),
    array(),
    array()
);
$h1 = rms_header_render( 'header-one.php' );
rms_header_assert( false !== strpos( $h1, 'tel:4075550100' ), 'test_header_variants_contact_links_use_sanitized_protocols (injection stripped from href)', 'header-one tel URI retained non-digit payload' );
rms_header_assert( false === strpos( $h1, '<script>alert' ), 'test_header_variants_contact_links_use_sanitized_protocols (no raw script)', 'header-one emitted unescaped <script>' );
rms_header_assert( false !== strpos( $h1, '&lt;script&gt;' ), 'test_header_variants_contact_links_use_sanitized_protocols (script escaped)', 'header-one did not escape <script> in visible text' );

// ─── Scenario 4: CTA fallback + contact slug alias ─────────────────────────

// No Contact page: fallback to home_url('/#contact').
rms_header_setup(
    array(
        'company_phones'       => array( array( 'phone_number' => '(407) 555-0100' ) ),
        'company_emails'       => array( array( 'email_address' => 'contact@example.com' ) ),
        'company_social_media' => array(),
    ),
    array(),
    array()
);
$h1 = rms_header_render( 'header-one.php' );
$h2 = rms_header_render( 'header-two.php' );
rms_header_assert( false !== strpos( $h1, 'https://example.test/#contact' ), 'test_header_one_and_v2_estimate_cta_resolves (header-one fallback)', 'header-one CTA did not fall back to home_url(/#contact)' );
rms_header_assert( false !== strpos( $h2, 'https://example.test/#contact' ), 'test_header_one_and_v2_estimate_cta_resolves (header-two fallback)', 'header-two CTA did not fall back to home_url(/#contact)' );
rms_header_assert( false === strpos( $h1, 'href="#"' ) && false === strpos( $h2, 'href="#"' ), 'test_header_one_and_v2_estimate_cta_resolves (fallback no dead href)', 'CTA still has dead href="#"' );

// Legacy 'contact' slug resolves too.
rms_header_setup(
    array(
        'company_phones'       => array( array( 'phone_number' => '(407) 555-0100' ) ),
        'company_emails'       => array(),
        'company_social_media' => array(),
    ),
    array( 'contact' => 7 ),
    array( 7 => 'https://example.test/contact/' )
);
$h1 = rms_header_render( 'header-one.php' );
rms_header_assert( false !== strpos( $h1, 'https://example.test/contact/' ), 'test_header_one_and_v2_estimate_cta_resolves (contact slug alias)', 'header-one CTA did not resolve the legacy contact slug' );

// ─── Scenario 5: social links stay dynamic ─────────────────────────────────

rms_header_setup(
    array(
        'company_phones'       => array( array( 'phone_number' => '(407) 555-0100' ) ),
        'company_emails'       => array( array( 'email_address' => 'contact@example.com' ) ),
        'company_social_media' => array(
            array(
                'social_is_active' => true,
                'social_url'       => 'https://facebook.com/raven',
                'social_platform'  => 'facebook',
                'social_label'     => 'Facebook',
            ),
        ),
    ),
    array(),
    array()
);
$h1 = rms_header_render( 'header-one.php' );
$h2 = rms_header_render( 'header-two.php' );
$h3 = rms_header_render( 'header-three.php' );
rms_header_assert( false !== strpos( $h1, 'https://facebook.com/raven' ), 'test_header_variants_social_links_remain_dynamic (header-one)', 'header-one social links are no longer dynamic' );
rms_header_assert( false !== strpos( $h2, 'https://facebook.com/raven' ), 'test_header_variants_social_links_remain_dynamic (header-two)', 'header-two social links are no longer dynamic' );
rms_header_assert( false !== strpos( $h3, 'https://facebook.com/raven' ), 'test_header_variants_social_links_remain_dynamic (header-three)', 'header-three social links are no longer dynamic' );

// ─── Summary ───────────────────────────────────────────────────────────────

if ( $failures > 0 ) {
    fwrite( STDERR, "Harness failed: {$failures} check(s).\n" );
    exit( 1 );
}

echo "Harness passed: all header contact + CTA checks.\n";
exit( 0 );
