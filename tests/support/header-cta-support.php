<?php
/**
 * Shared deterministic WP/ACF/template stubs + assertion helpers for the #56
 * header primary CTA harness family. Not executable on its own; it is required
 * by tests/header-primary-cta-{schema,behavior,regression}-harness.php.
 *
 * Establishes:
 *   - `get_field`, page resolver, escaping, and template stubs keyed on globals
 *     (rms_header_fields / rms_header_pages / rms_header_permalinks).
 *   - rms_cta_assert/pass/fail, rms_cta_render, and rms_cta_setup helpers.
 *   - A single `require` of inc/acf-theme-options.php (production getter under
 *     test), so no harness re-declares the theme helpers.
 *
 * `$theme_root` must be defined by the requiring harness before this include.
 *
 * No framework. Single process; stubs are toggled between scenarios via
 * rms_cta_setup().
 */

$failures = 0;

function rms_cta_fail( string $name, string $detail ): void {
    global $failures;
    $failures++;
    fwrite( STDERR, "FAIL {$name}: {$detail}\n" );
}

function rms_cta_pass( string $name ): void {
    fwrite( STDOUT, "PASS {$name}\n" );
}

function rms_cta_assert( $condition, string $name, string $detail ): void {
    if ( $condition ) {
        rms_cta_pass( $name );
        return;
    }
    rms_cta_fail( $name, $detail );
}

function rms_cta_render( string $template ): string {
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

function rms_cta_setup( array $fields, array $pages = array(), array $permalinks = array() ): void {
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
