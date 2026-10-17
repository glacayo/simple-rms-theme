<?php
/**
 * Focused contract harness for issue #166 (optional Services V1 title links).
 *
 * Proves, without a framework:
 *   1. Each services_v1_services row gains an optional `service_url` URL
 *      sub-field after the existing title/description fields, existing ACF
 *      keys are unchanged, and the generated ACF JSON matches the PHP source.
 *   2. A valid full http(s) URL renders an anchor inside the existing h4,
 *      with escaped URL and title; nothing else on the card changes.
 *   3. Missing, empty, `#`, relative, malformed or non-http(s) URLs keep the
 *      plain title, byte-identical to the pinned pre-#166 output, as do the
 *      default examples and the section CTA (with data-raven-cta).
 *   4. The AI wizard never fills `service_url`.
 *   5. The title link inherits the title styling and has a visible focus ring.
 *
 * Usage: php tests/services-v1-title-links-harness.php
 */

if ( PHP_SAPI !== 'cli' ) {
    fwrite( STDERR, "CLI only.\n" );
    exit( 1 );
}

$theme_root = dirname( __DIR__ );

// Wizard classes bail out (exit 0) without ABSPATH, which would hide failures.
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', dirname( $theme_root, 3 ) . '/' );
}

// Escaping stubs defined first so they win over the shared support versions:
// esc_url must HTML-escape like core so the escaping checks are meaningful.
function esc_html( $text ) {
    return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}
function esc_attr( $text ) {
    return esc_html( $text );
}
function esc_url( $url ) {
    $url = trim( (string) $url );
    return ( '' === $url || preg_match( '#^(javascript|data):#i', $url ) ) ? '' : esc_html( $url );
}

// Shared stubs + rms_cta_* helpers. Also requires inc/acf-theme-options.php.
require __DIR__ . '/support/header-cta-support.php';

if ( ! function_exists( 'acf_add_local_field_group' ) ) {
    function acf_add_local_field_group( array $field_group ): void {
        $GLOBALS['rms_captured_group'] = $field_group;
    }
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
    function sanitize_text_field( $value ) {
        return is_scalar( $value ) ? trim( (string) $value ) : '';
    }
}
if ( ! function_exists( 'wp_kses_post' ) ) {
    function wp_kses_post( $data ) {
        return (string) $data;
    }
}
if ( ! function_exists( 'esc_url_raw' ) ) {
    function esc_url_raw( $url ) {
        return (string) $url;
    }
}
if ( ! function_exists( 'get_sub_field' ) ) {
    function get_sub_field( $selector ) {
        return $GLOBALS['rms_sv1_fields'][ $selector ] ?? null;
    }
}

function rms_sv1_render( array $fields ): string {
    global $theme_root;
    $GLOBALS['rms_sv1_fields'] = $fields;
    ob_start();
    include $theme_root . '/templates/services-v1.php';
    return (string) ob_get_clean();
}

function rms_sv1_rows( array $urls ): array {
    $titles = array( 'Roof Repair', 'Gutters & Drains' );
    $texts  = array( 'Fast repairs.', 'Clean water flow.' );
    $rows   = array();
    foreach ( $titles as $i => $title ) {
        $row = array( 'service_title' => $title, 'service_text' => $texts[ $i ] );
        if ( array_key_exists( $i, $urls ) ) {
            $row['service_url'] = $urls[ $i ];
        }
        $rows[] = $row;
    }
    return array(
        'services_v1_services' => $rows,
        'services_v1_cta_text' => 'See All',
        'services_v1_cta_url'  => 'https://example.test/services/',
    );
}

// Exact output of templates/services-v1.php before #166, pinned so plain
// titles, default examples and the CTA are checked against a fixed baseline.
$rms_sv1_expected_placeholder = <<<'PRE166'
<!-- Services V1 -- Cards with Icons on Image Background -->
<section class="services-v1">
    <div class="services-v1__overlay"></div>
    <div class="container">
        <h2 class="services-v1__headline">Our Professional Services</h2>
        <h3 class="services-v1__subheadline">Quality Solutions for Every Need</h3>

        <div class="grid-3">
                            <div class="services-v1__card">
                    <span class="services-v1__icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2 5 5 0 0 1-5 5 5 5 0 0 1-5-5 2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></span>
                    <h4 class="services-v1__card-title">Roof Installation</h4>
                    <p class="services-v1__card-text">Complete new roof installation using premium materials with industry-leading warranties.</p>
                </div>
                <div class="services-v1__card">
                    <span class="services-v1__icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2 5 5 0 0 1-5 5 5 5 0 0 1-5-5 2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></span>
                    <h4 class="services-v1__card-title">Roof Repair</h4>
                    <p class="services-v1__card-text">Fast and reliable repairs for leaks, storm damage, and wear before they become costly problems.</p>
                </div>
                <div class="services-v1__card">
                    <span class="services-v1__icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2 5 5 0 0 1-5 5 5 5 0 0 1-5-5 2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></span>
                    <h4 class="services-v1__card-title">Roof Replacement</h4>
                    <p class="services-v1__card-text">Full roof replacement services with minimal disruption and maximum long-term protection.</p>
                </div>
                <div class="services-v1__card">
                    <span class="services-v1__icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2 5 5 0 0 1-5 5 5 5 0 0 1-5-5 2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></span>
                    <h4 class="services-v1__card-title">Roof Inspection</h4>
                    <p class="services-v1__card-text">Thorough inspections to identify issues early and extend the life of your existing roof.</p>
                </div>
                <div class="services-v1__card">
                    <span class="services-v1__icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2 5 5 0 0 1-5 5 5 5 0 0 1-5-5 2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></span>
                    <h4 class="services-v1__card-title">Gutter Installation</h4>
                    <p class="services-v1__card-text">Professional gutter systems that protect your foundation from water damage year-round.</p>
                </div>
                <div class="services-v1__card">
                    <span class="services-v1__icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2 5 5 0 0 1-5 5 5 5 0 0 1-5-5 2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></span>
                    <h4 class="services-v1__card-title">Emergency Services</h4>
                    <p class="services-v1__card-text">24/7 emergency response for storm damage and urgent roofing needs when you need us most.</p>
                </div>
                    </div>

        <div class="services-v1__cta">
            <a href="#" class="btn btn--outline-white services-v1__cta-btn" data-raven-cta>View All Services</a>
        </div>
    </div>
</section>
PRE166;
$rms_sv1_expected_placeholder .= "\n";
$rms_sv1_expected_rows = <<<'PRE166'
<!-- Services V1 -- Cards with Icons on Image Background -->
<section class="services-v1">
    <div class="services-v1__overlay"></div>
    <div class="container">
        <h2 class="services-v1__headline">Our Professional Services</h2>
        <h3 class="services-v1__subheadline">Quality Solutions for Every Need</h3>

        <div class="grid-3">
                                                <div class="services-v1__card">
                        <span class="services-v1__icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2 5 5 0 0 1-5 5 5 5 0 0 1-5-5 2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></span>
                        <h4 class="services-v1__card-title">Roof Repair</h4>
                        <p class="services-v1__card-text">Fast repairs.</p>
                    </div>
                                    <div class="services-v1__card">
                        <span class="services-v1__icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2 5 5 0 0 1-5 5 5 5 0 0 1-5-5 2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></span>
                        <h4 class="services-v1__card-title">Gutters &amp; Drains</h4>
                        <p class="services-v1__card-text">Clean water flow.</p>
                    </div>
                                    </div>

        <div class="services-v1__cta">
            <a href="https://example.test/services/" class="btn btn--outline-white services-v1__cta-btn" data-raven-cta>See All</a>
        </div>
    </div>
</section>
PRE166;
$rms_sv1_expected_rows .= "\n";

// ─── 1. Schema ─────────────────────────────────────────────────────────────

function rms_sv1_row_fields( array $group ): array {
    $layout = $group['fields'][0]['layouts']['layout_services_v1'] ?? array();
    foreach ( $layout['sub_fields'] ?? array() as $field ) {
        if ( 'services_v1_services' === ( $field['name'] ?? '' ) ) {
            return $field['sub_fields'] ?? array();
        }
    }
    return array();
}

$GLOBALS['rms_captured_group'] = null;
require_once $theme_root . '/inc/acf-flexible-content.php';
rms_register_acf_page_sections();
$php_group = is_array( $GLOBALS['rms_captured_group'] ) ? $GLOBALS['rms_captured_group'] : array();
$php_rows  = rms_sv1_row_fields( $php_group );
$by_name   = array_column( $php_rows, null, 'name' );
$url_field = $by_name['service_url'] ?? array();

rms_cta_assert(
    array( 'service_title', 'service_text', 'service_url' ) === array_column( $php_rows, 'name' ),
    'schema-row-order',
    'service rows must be title, description, url'
);
rms_cta_assert(
    'field_rms_services_v1_title' === ( $by_name['service_title']['key'] ?? '' ) && 'field_rms_services_v1_text' === ( $by_name['service_text']['key'] ?? '' ),
    'schema-existing-keys',
    'existing title/description field keys must not change'
);
rms_cta_assert( 'url' === ( $url_field['type'] ?? '' ), 'schema-url-type', 'service_url must be a url field' );
rms_cta_assert( empty( $url_field['required'] ) && '' === ( $url_field['default_value'] ?? '' ), 'schema-url-optional', 'service_url must be optional with no default' );
rms_cta_assert( 'field_rms_services_v1_url' === ( $url_field['key'] ?? '' ), 'schema-url-key', 'service_url must use key field_rms_services_v1_url' );
rms_cta_assert( 'field_rms_services_v1_services' === ( $url_field['parent_repeater'] ?? '' ), 'schema-url-parent', 'service_url must belong to the services repeater' );

$json_group = json_decode( (string) file_get_contents( $theme_root . '/acf-json/group_rms_page_sections.json' ), true );
rms_cta_assert(
    is_array( $json_group ) && rms_sv1_row_fields( $json_group ) === $php_rows,
    'schema-json-in-sync',
    'acf-json/group_rms_page_sections.json must match the PHP services-v1 rows (run scripts/generate-acf-json.php)'
);

// ─── 2. Valid URLs link only the title ─────────────────────────────────────

$linked = rms_sv1_render( rms_sv1_rows( array( 'https://example.test/roof-repair/', 'http://example.test/gutters/' ) ) );
rms_cta_assert(
    false !== strpos( $linked, '<h4 class="services-v1__card-title"><a class="services-v1__card-link" href="https://example.test/roof-repair/">Roof Repair</a></h4>' ),
    'render-https-link',
    'an https URL must link the title inside the existing h4'
);
rms_cta_assert(
    false !== strpos( $linked, '<h4 class="services-v1__card-title"><a class="services-v1__card-link" href="http://example.test/gutters/">Gutters &amp; Drains</a></h4>' ),
    'render-http-link-escaped-title',
    'an http URL must link the title, with the title escaped'
);
rms_cta_assert(
    str_replace(
        array( '<a class="services-v1__card-link" href="https://example.test/roof-repair/">', '<a class="services-v1__card-link" href="http://example.test/gutters/">', 'Roof Repair</a>', 'Drains</a>' ),
        array( '', '', 'Roof Repair', 'Drains' ),
        $linked
    ) === $rms_sv1_expected_rows,
    'render-link-changes-only-title',
    'apart from the title anchors, linked cards must match the pre-#166 markup exactly'
);

$escaped = rms_sv1_render( array(
    'services_v1_services' => array(
        array( 'service_title' => '<b>Roof</b> "Pro"', 'service_text' => 'x', 'service_url' => 'https://example.test/?a="b"&c=<d>' ),
    ),
) );
rms_cta_assert(
    false !== strpos( $escaped, 'href="https://example.test/?a=&quot;b&quot;&amp;c=&lt;d&gt;">&lt;b&gt;Roof&lt;/b&gt; &quot;Pro&quot;</a>' )
        && false === strpos( $escaped, '<b>Roof' ),
    'render-escapes-url-and-title',
    'the link URL and title must be escaped'
);

// ─── 3. Invalid or missing URLs keep the plain title ───────────────────────

rms_cta_assert( rms_sv1_render( rms_sv1_rows( array() ) ) === $rms_sv1_expected_rows, 'render-legacy-rows-identical', 'rows saved before #166 (no service_url key) must render byte-identical to the pre-#166 template' );

$invalid = array(
    'empty'        => '',
    'whitespace'   => '   ',
    'hash'         => '#',
    'relative'     => '/services/roof/',
    'protocol-rel' => '//example.test/roof/',
    'mailto'       => 'mailto:info@example.test',
    'ftp'          => 'ftp://example.test/file',
    'javascript'   => 'javascript:alert(1)',
    'no-host'      => 'https://',
    'not-a-url'    => 'roof repair page',
    'null'         => null,
    'array'        => array( 'url' => 'https://example.test/' ),
);
foreach ( $invalid as $label => $value ) {
    rms_cta_assert(
        rms_sv1_render( rms_sv1_rows( array( $value, $value ) ) ) === $rms_sv1_expected_rows,
        "render-invalid-{$label}-plain",
        "a {$label} service_url must keep the plain title exactly as before #166"
    );
}

rms_cta_assert( rms_sv1_render( array() ) === $rms_sv1_expected_placeholder, 'render-default-examples-identical', 'the default example cards and CTA must stay byte-identical to the pre-#166 template' );
rms_cta_assert(
    false !== strpos( $linked, '<a href="https://example.test/services/" class="btn btn--outline-white services-v1__cta-btn" data-raven-cta>See All</a>' ),
    'render-cta-unchanged',
    'the section CTA keeps its URL, classes and data-raven-cta attribute'
);

// ─── 4. AI wizard never fills the title URL ────────────────────────────────

require_once $theme_root . '/inc/wizard/class-ai-content-harness.php';
require_once $theme_root . '/inc/wizard/class-flexible-content-layouts.php';
$ai = new \Inc\Wizard\AI_Content_Harness();
rms_cta_assert( in_array( 'service_url', $ai->get_blocked_fields( 'services-v1' ), true ), 'ai-url-blocked', 'service_url must be an AI-blocked services-v1 field' );
$clean = $ai->validate_fields( 'services-v1', array(
    'services_v1_services' => array( array( 'service_text' => 'Fast repairs.', 'service_url' => 'https://invented.example/' ) ),
) );
rms_cta_assert(
    array( array( 'service_text' => 'Fast repairs.' ) ) === ( $clean['services_v1_services'] ?? null ),
    'ai-validate-strips-url',
    'validate_fields must keep only service_text in services-v1 rows'
);

// ─── 5. Styles ─────────────────────────────────────────────────────────────

$scss = (string) file_get_contents( $theme_root . '/src/scss/templates/services-v1.scss' );
rms_cta_assert(
    1 === preg_match( '/\.services-v1__card-link\s*\{[^}]*color:\s*inherit;/', $scss ),
    'scss-link-inherits-title',
    'the title link must inherit the title color'
);
rms_cta_assert(
    1 === preg_match( '/\.services-v1__card-link\s*\{.*?&:focus-visible\s*\{[^}]*outline:/s', $scss ),
    'scss-link-focus-ring',
    'the title link must have a visible :focus-visible outline'
);

// ─── Summary ───────────────────────────────────────────────────────────────

if ( $failures > 0 ) {
    fwrite( STDERR, "Services V1 title links harness failed: {$failures} check(s).\n" );
    exit( 1 );
}

echo "Services V1 title links harness passed: optional URL field, PHP/JSON parity, title links, unchanged fallback and CTA, AI exclusion, and styles.\n";
exit( 0 );
