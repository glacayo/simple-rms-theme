<?php
/**
 * Focused contract harness for issue #152 (Theme Settings "Tracking code" tab
 * for administrator-managed Google Tag Manager snippets).
 *
 * Proves, without a framework:
 *   1. acf-json registers a "Tracking code" tab with separate head and body
 *      textarea fields, with no default values and no real container IDs.
 *   2. Empty fields render nothing in either placement point.
 *   3. Configured fragments render byte-exact (unescaped) and exactly once
 *      per placement, even if the hooks fire more than once.
 *   4. header.php calls wp_body_open() immediately after <body> opens, and
 *      the dataLayer bootstrap still precedes wp_head().
 *   5. Only users with unfiltered_html see or change the fields; a save from
 *      any other user keeps the stored value.
 *   6. functions.php loads the module and the docs describe migration away
 *      from a hardcoded installation.
 *
 * Usage: php tests/tracking-code-harness.php
 */

if ( PHP_SAPI !== 'cli' ) {
    fwrite( STDERR, "CLI only.\n" );
    exit( 1 );
}

$theme_root = dirname( __DIR__ );
$failures   = 0;

function rms_tc_assert( $condition, string $name, string $detail ): void {
    global $failures;
    if ( $condition ) {
        fwrite( STDOUT, "PASS {$name}\n" );
        return;
    }
    $failures++;
    fwrite( STDERR, "FAIL {$name}: {$detail}\n" );
}

// ─── WordPress + ACF stubs ─────────────────────────────────────────────────

$GLOBALS['rms_tc_hooks']   = array();
$GLOBALS['rms_tc_options'] = array();
$GLOBALS['rms_tc_caps']    = array();

function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
    $GLOBALS['rms_tc_hooks'][] = array( $hook, $callback, $priority, $accepted_args );
    return true;
}

function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
    return add_action( $hook, $callback, $priority, $accepted_args );
}

function get_field( $selector, $post_id = false, $format_value = true ) {
    if ( false !== $format_value ) {
        // Formatted reads would let ACF alter the markup; the module must read raw.
        return 'FORMATTED';
    }
    return $GLOBALS['rms_tc_options'][ $selector ] ?? null;
}

function current_user_can( $capability ) {
    return in_array( $capability, $GLOBALS['rms_tc_caps'], true );
}

function rms_tc_hook( string $hook ): array {
    foreach ( $GLOBALS['rms_tc_hooks'] as $entry ) {
        if ( $entry[0] === $hook ) {
            return $entry;
        }
    }
    return array();
}

function rms_tc_fire( string $hook ): string {
    $entry = rms_tc_hook( $hook );
    if ( ! $entry ) {
        return '';
    }
    ob_start();
    call_user_func( $entry[1] );
    return (string) ob_get_clean();
}

$module = $theme_root . '/inc/tracking-code.php';
if ( ! is_readable( $module ) ) {
    rms_tc_assert( false, 'module-exists', 'inc/tracking-code.php is missing' );
    exit( 1 );
}
require $module;

// ─── 1. ACF schema ─────────────────────────────────────────────────────────

$json   = (string) file_get_contents( $theme_root . '/acf-json/group_rms_theme_settings.json' );
$group  = json_decode( $json, true );
$fields = array();
foreach ( $group['fields'] ?? array() as $field ) {
    $fields[ $field['key'] ] = $field;
}

$tab = $fields['field_rms_tab_tracking'] ?? array();
rms_tc_assert(
    ( $tab['type'] ?? '' ) === 'tab' && ( $tab['label'] ?? '' ) === 'Tracking code',
    'schema-tab',
    'expected tab field_rms_tab_tracking labelled "Tracking code"'
);

foreach ( array( 'head', 'body' ) as $slot ) {
    $field = $fields[ "field_rms_tracking_code_{$slot}" ] ?? array();
    rms_tc_assert(
        ( $field['type'] ?? '' ) === 'textarea' && ( $field['name'] ?? '' ) === "tracking_code_{$slot}",
        "schema-{$slot}-field",
        "expected textarea named tracking_code_{$slot}"
    );
    rms_tc_assert(
        '' === ( $field['default_value'] ?? '' ) && '' === ( $field['new_lines'] ?? '' ),
        "schema-{$slot}-raw",
        'field must have no default value and no new_lines formatting'
    );
}

$keys      = array_keys( $fields );
$tab_index = array_search( 'field_rms_tab_tracking', $keys, true );
rms_tc_assert(
    false !== $tab_index
        && ( $keys[ $tab_index + 1 ] ?? '' ) === 'field_rms_tracking_code_head'
        && ( $keys[ $tab_index + 2 ] ?? '' ) === 'field_rms_tracking_code_body',
    'schema-tab-order',
    'head and body fields must directly follow the Tracking code tab'
);

rms_tc_assert(
    ! preg_match( '/GTM-[A-Z0-9]{4,}/', $json ),
    'schema-no-container-id',
    'acf-json must not commit a real GTM container ID'
);

// ─── 2. Empty fields render nothing ────────────────────────────────────────

$head_hook = rms_tc_hook( 'wp_head' );
$body_hook = rms_tc_hook( 'wp_body_open' );
rms_tc_assert( (bool) $head_hook, 'hook-wp-head', 'expected a wp_head callback' );
rms_tc_assert( (bool) $body_hook, 'hook-wp-body-open', 'expected a wp_body_open callback' );
rms_tc_assert( 1 === ( $head_hook[2] ?? null ), 'hook-wp-head-early', 'wp_head callback must run at priority 1' );

$GLOBALS['rms_tc_options'] = array( 'tracking_code_head' => "  \n ", 'tracking_code_body' => null );
rms_tc_assert( '' === rms_tc_fire( 'wp_head' ), 'empty-head', 'whitespace-only head field must render nothing' );
rms_tc_assert( '' === rms_tc_fire( 'wp_body_open' ), 'empty-body', 'empty body field must render nothing' );

// ─── 3. Configured fragments render raw, exactly once ──────────────────────

$head_snippet = "<!-- Google Tag Manager -->\n<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});})(window,document,'script','dataLayer','GTM-XXXXXXX');</script>\n<!-- End Google Tag Manager -->";
$body_snippet = '<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-XXXXXXX" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>';

$GLOBALS['rms_tc_options'] = array(
    'tracking_code_head' => "\n" . $head_snippet . "\n",
    'tracking_code_body' => $body_snippet,
);

$head_out = rms_tc_fire( 'wp_head' );
$body_out = rms_tc_fire( 'wp_body_open' );
rms_tc_assert( 1 === substr_count( $head_out, $head_snippet ), 'head-raw', 'head snippet must render byte-exact once' );
rms_tc_assert( 1 === substr_count( $body_out, $body_snippet ), 'body-raw', 'body snippet must render byte-exact once' );
rms_tc_assert( false === strpos( $head_out . $body_out, '&lt;' ), 'not-escaped', 'snippets must not be HTML-escaped' );

rms_tc_assert( '' === rms_tc_fire( 'wp_head' ), 'head-once', 'second wp_head must not duplicate the snippet' );
rms_tc_assert( '' === rms_tc_fire( 'wp_body_open' ), 'body-once', 'second wp_body_open must not duplicate the snippet' );

// ─── 4. Placement in header.php ────────────────────────────────────────────

$header    = (string) file_get_contents( $theme_root . '/header.php' );
$bootstrap = strpos( $header, '<script>window.dataLayer = window.dataLayer || [];</script>' );
$wp_head   = strpos( $header, 'wp_head();' );
rms_tc_assert(
    false !== $bootstrap && false !== $wp_head && $bootstrap < $wp_head,
    'header-bootstrap-before-wp-head',
    'dataLayer bootstrap must still precede wp_head()'
);
rms_tc_assert(
    1 === preg_match_all( '/<body <\?php body_class\(\); \?>>\s*<\?php wp_body_open\(\); \?>/', $header ),
    'header-wp-body-open',
    'wp_body_open() must be called once, immediately after <body>'
);

// ─── 5. Capability gate ────────────────────────────────────────────────────

foreach ( array( 'head', 'body' ) as $slot ) {
    $name    = "tracking_code_{$slot}";
    $prepare = rms_tc_hook( "acf/prepare_field/name={$name}" );
    $update  = rms_tc_hook( "acf/update_value/name={$name}" );
    $option  = rms_tc_hook( "pre_update_option_options_{$name}" );
    rms_tc_assert(
        (bool) $prepare && (bool) $update && (bool) $option,
        "gate-{$slot}-hooks",
        'expected prepare_field, update_value, and pre_update_option filters'
    );
    if ( ! $prepare || ! $update || ! $option ) {
        continue;
    }

    $field                     = array( 'name' => $name );
    $GLOBALS['rms_tc_options'] = array( $name => 'STORED' );

    $GLOBALS['rms_tc_caps'] = array( 'manage_options' );
    rms_tc_assert( false === call_user_func( $prepare[1], $field ), "gate-{$slot}-hidden", 'field must be hidden without unfiltered_html' );
    rms_tc_assert(
        'STORED' === call_user_func( $update[1], '<script>evil()</script>', 'options', $field ),
        "gate-{$slot}-update-refused",
        'save without unfiltered_html must keep the stored value'
    );
    rms_tc_assert(
        'STORED' === call_user_func( $option[1], '<script>evil()</script>', 'STORED' ),
        "gate-{$slot}-option-refused",
        'direct option write without unfiltered_html must keep the stored value'
    );

    $GLOBALS['rms_tc_caps'] = array( 'manage_options', 'unfiltered_html' );
    rms_tc_assert( $field === call_user_func( $prepare[1], $field ), "gate-{$slot}-visible", 'field must be visible with unfiltered_html' );
    rms_tc_assert(
        'NEW' === call_user_func( $update[1], 'NEW', 'options', $field ),
        "gate-{$slot}-update-allowed",
        'save with unfiltered_html must persist the new value'
    );
    rms_tc_assert(
        'NEW' === call_user_func( $option[1], 'NEW', 'STORED' ),
        "gate-{$slot}-option-allowed",
        'direct option write with unfiltered_html must persist the new value'
    );
}

// ─── 6. Loader and docs ────────────────────────────────────────────────────

$functions = (string) file_get_contents( $theme_root . '/functions.php' );
rms_tc_assert(
    false !== strpos( $functions, "require_once get_template_directory() . '/inc/tracking-code.php';" ),
    'functions-loads-module',
    'functions.php must require inc/tracking-code.php'
);

$docs = (string) file_get_contents( $theme_root . '/docs/analytics-hooks.md' );
rms_tc_assert(
    false !== stripos( $docs, 'Tracking code' ) && false !== stripos( $docs, 'hardcoded' ) && false !== stripos( $docs, 'twice' ),
    'docs-migration',
    'docs/analytics-hooks.md must document migrating from a hardcoded install without loading the container twice'
);

if ( $failures > 0 ) {
    fwrite( STDERR, "\n{$failures} failure(s).\n" );
    exit( 1 );
}

fwrite( STDOUT, "\nAll tracking code assertions passed.\n" );
exit( 0 );
