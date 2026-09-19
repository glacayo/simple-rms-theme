<?php
/**
 * Focused contract harness for issue #128 slice I128-01 (Raven GTM/dataLayer
 * bootstrap and canonical analytics contract docs).
 *
 * Proves, without a framework:
 *   1. header.php emits the dataLayer bootstrap exactly once, byte-exact,
 *      inside a <script> tag in <head>, and before the wp_head() call.
 *   2. The bootstrap is harmless without GTM: it pushes nothing and loads no
 *      external script; header.php contains no GTM/GA container ID, gtag()
 *      call, or external analytics URL.
 *   3. docs/analytics-hooks.md documents the stable selector attributes, the
 *      form lifecycle events, the payload allowlist + PII prohibition, the
 *      once-per-form / confirmed-success rules, GTM channel/attribution
 *      ownership, the no-container-ID posture, the absence policy, real
 *      tel/download URLs, and the canonical route slugs with a permalink
 *      prefix caveat.
 *
 * Ships with the header.php bootstrap and docs/analytics-hooks.md contract.
 * Instrumenting CTA/call surfaces (I128-02) and emitting form events
 * (I128-03) are later slices and are intentionally not asserted here.
 *
 * Reuses tests/support/header-cta-support.php only for the shared
 * rms_cta_assert/pass/fail helpers.
 *
 * Usage: php tests/raven-analytics-bootstrap-harness.php
 */

if ( PHP_SAPI !== 'cli' ) {
    fwrite( STDERR, "CLI only.\n" );
    exit( 1 );
}

$theme_root = dirname( __DIR__ );

require __DIR__ . '/support/header-cta-support.php';

/**
 * Return the full <script>...</script> block that contains a needle, or ''.
 */
function rms_raven_script_block( string $html, string $needle ): string {
    $pos = strpos( $html, $needle );
    if ( false === $pos ) {
        return '';
    }
    $open = strrpos( substr( $html, 0, $pos ), '<script' );
    if ( false === $open ) {
        return '';
    }
    $open_end = strpos( $html, '>', $open );
    if ( false === $open_end ) {
        return '';
    }
    $close = strpos( $html, '</script>', $open_end );
    if ( false === $close ) {
        return '';
    }
    return substr( $html, $open, ( $close - $open ) + strlen( '</script>' ) );
}

/**
 * Assert an artifact contains every required clause token; report the gaps.
 */
function rms_raven_contains( string $artifact, array $needles, string $name, string $detail ): void {
    $haystack = preg_replace( '/\s+/', ' ', $artifact );
    $missing  = array();
    foreach ( $needles as $needle ) {
        $probe = preg_replace( '/\s+/', ' ', $needle );
        if ( false === strpos( $haystack, $probe ) ) {
            $missing[] = $needle;
        }
    }
    rms_cta_assert(
        array() === $missing,
        $name,
        $detail . ' Missing: ' . implode( ', ', $missing )
    );
}

// ─── 1. Bootstrap exactness, count, and <head>/wp_head() order ────────────

$bootstrap = 'window.dataLayer = window.dataLayer || [];';

$header = file_get_contents( $theme_root . '/header.php' );
$header = is_string( $header ) ? $header : '';

rms_cta_assert(
    1 === substr_count( $header, $bootstrap ),
    'test_bootstrap_emitted_exactly_once',
    'header.php must contain the byte-exact dataLayer bootstrap exactly once'
);

$block = rms_raven_script_block( $header, $bootstrap );
rms_cta_assert(
    '' !== $block,
    'test_bootstrap_wrapped_in_script_tag',
    'the bootstrap must live inside a <script> tag'
);

$inner        = '';
$block_open   = strpos( $block, '>' );
$block_close  = strpos( $block, '</script>' );
if ( false !== $block_open && false !== $block_close ) {
    $inner = substr( $block, $block_open + 1, $block_close - $block_open - 1 );
}
rms_cta_assert(
    $bootstrap === trim( $inner ),
    'test_bootstrap_script_content_exact',
    'the bootstrap <script> body must be exactly the bootstrap statement, no extra code'
);

rms_cta_assert(
    '' !== $block && '<script>' === substr( $block, 0, $block_open + 1 ),
    'test_bootstrap_script_tag_bare_inline',
    'the bootstrap must be a bare inline <script> tag with no async/defer/src attributes'
);

$head_open     = strpos( $header, '<head>' );
$head_close    = strpos( $header, '</head>' );
$bootstrap_pos = strpos( $header, $bootstrap );
$wp_head_pos   = strpos( $header, 'wp_head();' );

rms_cta_assert(
    false !== $bootstrap_pos && false !== $wp_head_pos && $bootstrap_pos < $wp_head_pos,
    'test_bootstrap_precedes_wp_head',
    'the bootstrap must be emitted before the wp_head() call'
);

rms_cta_assert(
    false !== $head_open && false !== $head_close && $head_open < $bootstrap_pos && $bootstrap_pos < $head_close,
    'test_bootstrap_inside_document_head',
    'the bootstrap must sit between <head> and </head>'
);

// ─── 2. Harmless without GTM: no push, no external script, no IDs/URLs ─────

rms_cta_assert(
    false === strpos( $block, '.push' ),
    'test_bootstrap_pushes_no_events',
    'this slice must not push any dataLayer event'
);

rms_cta_assert(
    false === stripos( $block, 'src=' ),
    'test_bootstrap_loads_no_external_script',
    'the bootstrap script must not load an external resource'
);

$forbidden = array(
    'test_header_no_gtag_call'        => 'gtag(',
    'test_header_no_gtm_container_id' => 'GTM-',
    'test_header_no_gtag_config_id'   => 'G-',
    'test_header_no_ads_id'           => 'AW-',
    'test_header_no_legacy_ga_id'     => 'UA-',
    'test_header_no_googletagmanager' => 'googletagmanager.com',
    'test_header_no_google_analytics' => 'google-analytics.com',
    'test_header_no_analytics_google' => 'analytics.google.com',
    'test_header_no_dataLayer_push'   => 'dataLayer.push',
);

foreach ( $forbidden as $name => $token ) {
    rms_cta_assert(
        false === strpos( $header, $token ),
        $name,
        'header.php must not contain the analytics token "' . $token . '"'
    );
}

// ── 3. docs/analytics-hooks.md canonical contract ────────────────────────

$doc_path = $theme_root . '/docs/analytics-hooks.md';
$doc      = is_readable( $doc_path ) ? file_get_contents( $doc_path ) : '';
$doc      = is_string( $doc ) ? $doc : '';

rms_raven_contains(
    $doc,
    array( 'data-raven-cta', 'data-raven-call', 'data-raven-share', 'data-related' ),
    'test_docs_stable_selector_attributes',
    'docs must document every stable Raven selector attribute'
);

rms_raven_contains(
    $doc,
    array( 'form_start', 'form_success' ),
    'test_docs_form_lifecycle_events',
    'docs must document both form lifecycle events'
);

rms_raven_contains(
    $doc,
    array( 'Payload allowlist', 'event', 'form_id', 'form_name' ),
    'test_docs_payload_allowlist',
    'docs must document the payload allowlist and its three keys'
);

rms_raven_contains(
    $doc,
    array( 'Prohibited PII', 'email', 'phone', 'message' ),
    'test_docs_pii_prohibition',
    'docs must explicitly prohibit PII in payloads'
);

rms_raven_contains(
    $doc,
    array( 'once per form' ),
    'test_docs_once_per_form_start',
    'docs must state form_start fires once per form'
);

rms_raven_contains(
    $doc,
    array( 'confirmed success' ),
    'test_docs_confirmed_success_only',
    'docs must state form_success fires only on confirmed success'
);

rms_raven_contains(
    $doc,
    array( 'does not compute channels' ),
    'test_docs_theme_does_not_compute_channels',
    'docs must state the theme does not compute channels'
);

rms_raven_contains(
    $doc,
    array( 'GTM owns attribution' ),
    'test_docs_gtm_owns_attribution',
    'docs must state GTM owns attribution/macros'
);

rms_raven_contains(
    $doc,
    array( 'ships no container ID' ),
    'test_docs_no_base_theme_container_id',
    'docs must state the base theme ships no container ID'
);

rms_raven_contains(
    $doc,
    array( 'Absence policy', 'social profiles', 'navigation' ),
    'test_docs_absence_policy',
    'docs must state the absence policy for share/related UI'
);

rms_raven_contains(
    $doc,
    array( 'tel:', 'download' ),
    'test_docs_real_tel_and_download_urls',
    'docs must require real tel: and download URLs'
);

rms_raven_contains(
    $doc,
    array( 'contact-us', 'services', 'projects', 'thank-you' ),
    'test_docs_canonical_route_slugs',
    'docs must document the canonical route slugs'
);

rms_raven_contains(
    $doc,
    array( 'permalink prefixes may vary' ),
    'test_docs_permalink_prefix_caveat',
    'docs must note that WordPress permalink prefixes may vary'
);

// ── Summary ───────────────────────────────────────────────────────────────

if ( $failures > 0 ) {
    fwrite( STDERR, "Raven analytics bootstrap harness failed: {$failures} check(s).\n" );
    exit( 1 );
}

echo "Raven analytics bootstrap harness passed: pre-wp_head dataLayer bootstrap, no IDs/snippets/external URLs, and the canonical analytics contract docs.\n";
exit( 0 );