<?php
/**
 * Focused contract harness for issue #126 (Contact Map URL separation) —
 * slice 1 of the Feature Branch Chain.
 *
 * Proves, without a framework:
 *   1. ACF schema: `company_gbp_share_url` / `field_rms_company_gbp_share_url`
 *      exists exactly once, is type `url`, and sits immediately after the
 *      map embed field with a distinct, self-describing label/instructions/
 *      placeholder.
 *   2. `rms_get_gbp_share_url()` trims a configured value and never falls back
 *      to the embed URL when the share field is empty or malformed.
 *   3. Every embed/share combination renders the correct map body and only
 *      renders Get Directions when the trimmed share URL is non-empty.
 *   4. When both are configured they render as distinct URLs (iframe src vs
 *      link href).
 *   5. The directions control lives inside `.contact-map`, after the embed or
 *      placeholder, and keeps safe external-link output attributes.
 *   6. The placeholder markup is preserved byte-for-byte in its stable parts.
 *
 * Ships with the ACF schema, `rms_get_gbp_share_url()` helper, and
 * `templates/contact-map.php` production changes. The additive SCSS and
 * header-enqueue coverage live in
 * tests/contact-map-overlay-assets-harness.php (slice 2).
 *
 * Reuses tests/support/header-cta-support.php for the shared WP/ACF/template
 * stubs and rms_cta_* render/assert helpers.
 *
 * Usage: php tests/gbp-share-url-contract-harness.php
 */

if ( PHP_SAPI !== 'cli' ) {
    fwrite( STDERR, "CLI only.\n" );
    exit( 1 );
}

$theme_root = dirname( __DIR__ );

require __DIR__ . '/support/header-cta-support.php';

// ─── Fixtures ──────────────────────────────────────────────────────────────

$embed_url = 'https://maps.google.com/maps?q=Raven&output=embed';
$share_url = 'https://g.page/r/TestBusiness/review';
$raw_share = '  ' . $share_url . "  \n";

/**
 * Render the contact map template with a field map.
 */
function rms_gbp_render( array $fields ): string {
    rms_cta_setup( $fields );
    return rms_cta_render( 'contact-map.php' );
}

/**
 * Return the substring between the contact-map section open and close tags.
 */
function rms_gbp_section( string $html ): string {
    $open  = strpos( $html, '<section class="contact-map">' );
    $close = strpos( $html, '</section>' );
    if ( false === $open || false === $close || $close < $open ) {
        return '';
    }
    return substr( $html, $open, ( $close - $open ) + strlen( '</section>' ) );
}

// ─── 1. Schema: uniqueness, order, type, distinguishing copy ────────────────

$acf_raw = file_get_contents( $theme_root . '/acf-json/group_rms_theme_settings.json' );
$acf_raw = is_string( $acf_raw ) ? $acf_raw : '';
$acf     = json_decode( $acf_raw, true );

rms_cta_assert(
    1 === substr_count( $acf_raw, '"field_rms_company_gbp_share_url"' ),
    'test_schema_key_exactly_once',
    'ACF JSON must contain field_rms_company_gbp_share_url exactly once'
);
rms_cta_assert(
    1 === substr_count( $acf_raw, '"company_gbp_share_url"' ),
    'test_schema_name_exactly_once',
    'ACF JSON must contain company_gbp_share_url exactly once'
);

$fields      = is_array( $acf ) ? ( $acf['fields'] ?? array() ) : array();
$embed_index = null;
$gbp_field   = null;
foreach ( $fields as $index => $field ) {
    if ( ( $field['name'] ?? '' ) === 'company_google_maps_url' ) {
        $embed_index = $index;
        $embed_field = $field;
    }
    if ( ( $field['name'] ?? '' ) === 'company_gbp_share_url' ) {
        $gbp_field = $field;
    }
}

rms_cta_assert(
    is_array( $gbp_field ),
    'test_schema_gbp_field_present',
    'company_gbp_share_url field must exist in the ACF JSON'
);
rms_cta_assert(
    null !== $embed_index && $gbp_field === ( $fields[ $embed_index + 1 ] ?? null ),
    'test_schema_immediately_after_embed',
    'company_gbp_share_url must be the field immediately after company_google_maps_url'
);
rms_cta_assert(
    ( $gbp_field['type'] ?? '' ) === 'url',
    'test_schema_type_url',
    'company_gbp_share_url must be type url'
);

$gbp_label        = (string) ( $gbp_field['label'] ?? '' );
$gbp_instructions = (string) ( $gbp_field['instructions'] ?? '' );
$gbp_placeholder  = (string) ( $gbp_field['placeholder'] ?? '' );
$embed_label      = (string) ( $embed_field['label'] ?? '' );
$embed_instr      = (string) ( $embed_field['instructions'] ?? '' );
$embed_placeholder = (string) ( $embed_field['placeholder'] ?? '' );

rms_cta_assert(
    '' !== $gbp_label && strtolower( $gbp_label ) !== strtolower( $embed_label ),
    'test_schema_distinct_label',
    'the GBP share field must carry a distinct, non-empty label'
);
rms_cta_assert(
    '' !== $gbp_placeholder && $gbp_placeholder !== $embed_placeholder,
    'test_schema_distinct_placeholder',
    'the GBP share field must carry a distinct placeholder'
);
rms_cta_assert(
    false !== stripos( $gbp_instructions, 'directions' ) || false !== stripos( $gbp_instructions, 'profile' ),
    'test_schema_gbp_instructions_describe_share_link',
    'the GBP share instructions must describe the profile/share link'
);
rms_cta_assert(
    false !== stripos( $embed_instr, 'embed' ) || false !== stripos( $embed_instr, 'iframe' ),
    'test_schema_embed_instructions_mark_iframe_only',
    'the embed field instructions must state it feeds the map iframe'
);

// ── 2. Helper: trim + never fall back to the embed ────────────────────────

rms_cta_assert(
    function_exists( 'rms_get_gbp_share_url' ),
    'test_helper_exists',
    'inc/acf-theme-options.php must define rms_get_gbp_share_url()'
);

if ( function_exists( 'rms_get_gbp_share_url' ) ) {
    rms_cta_setup( array( 'company_gbp_share_url' => $raw_share ) );
    rms_cta_assert(
        $share_url === rms_get_gbp_share_url(),
        'test_helper_trims_value',
        'rms_get_gbp_share_url() must return the trimmed share URL'
    );

    rms_cta_setup( array( 'company_google_maps_url' => $embed_url ) );
    rms_cta_assert(
        '' === rms_get_gbp_share_url(),
        'test_helper_no_embed_fallback_when_unset',
        'rms_get_gbp_share_url() must not fall back to the embed URL'
    );

    rms_cta_setup( array( 'company_google_maps_url' => $embed_url, 'company_gbp_share_url' => '   ' ) );
    rms_cta_assert(
        '' === rms_get_gbp_share_url(),
        'test_helper_whitespace_is_empty',
        'a whitespace-only share value must resolve to an empty string'
    );

    rms_cta_setup( array( 'company_google_maps_url' => $embed_url, 'company_gbp_share_url' => array( 'x' ) ) );
    rms_cta_assert(
        '' === rms_get_gbp_share_url(),
        'test_helper_non_string_is_empty',
        'a non-string share value must resolve to an empty string'
    );
} else {
    rms_cta_fail( 'test_helper_trims_value', 'rms_get_gbp_share_url() is not defined' );
    rms_cta_fail( 'test_helper_no_embed_fallback_when_unset', 'rms_get_gbp_share_url() is not defined' );
    rms_cta_fail( 'test_helper_whitespace_is_empty', 'rms_get_gbp_share_url() is not defined' );
    rms_cta_fail( 'test_helper_non_string_is_empty', 'rms_get_gbp_share_url() is not defined' );
}

// ── 3 + 4. Four combinations + distinct URLs ──────────────────────────────

$btn_marker   = 'contact-map__directions-btn';
$embed_marker = 'contact-map__iframe';
$ph_marker    = 'contact-map__placeholder';

$both = rms_gbp_render( array(
    'company_google_maps_url' => $embed_url,
    'company_gbp_share_url'   => $share_url,
) );
rms_cta_assert( false !== strpos( $both, $embed_marker ), 'test_both_renders_iframe', 'embed+share must render the iframe' );
rms_cta_assert( false !== strpos( $both, $btn_marker ), 'test_both_renders_directions', 'embed+share must render Get Directions' );
rms_cta_assert( false !== strpos( $both, 'src="' . $embed_url . '"' ), 'test_both_iframe_src_is_embed', 'iframe src must be the embed URL' );
rms_cta_assert( false !== strpos( $both, 'href="' . $share_url . '"' ), 'test_both_href_is_share', 'directions href must be the share URL' );

$embed_only = rms_gbp_render( array( 'company_google_maps_url' => $embed_url ) );
rms_cta_assert( false !== strpos( $embed_only, $embed_marker ), 'test_embed_only_renders_iframe', 'embed-only must render the iframe' );
rms_cta_assert( false === strpos( $embed_only, $btn_marker ), 'test_embed_only_hides_directions', 'embed-only must NOT render Get Directions' );

$share_only = rms_gbp_render( array( 'company_gbp_share_url' => $share_url ) );
rms_cta_assert( false === strpos( $share_only, $embed_marker ), 'test_share_only_has_no_iframe', 'share-only must not render an iframe' );
rms_cta_assert( false !== strpos( $share_only, $ph_marker ), 'test_share_only_keeps_placeholder', 'share-only must preserve the placeholder' );
rms_cta_assert( false !== strpos( $share_only, $btn_marker ), 'test_share_only_renders_directions', 'share-only must still render Get Directions' );
rms_cta_assert( false !== strpos( $share_only, 'href="' . $share_url . '"' ), 'test_share_only_href_is_share', 'share-only href must be the share URL' );

$neither = rms_gbp_render( array() );
rms_cta_assert( false === strpos( $neither, $embed_marker ), 'test_neither_has_no_iframe', 'empty config must not render an iframe' );
rms_cta_assert( false !== strpos( $neither, $ph_marker ), 'test_neither_keeps_placeholder', 'empty config must keep the placeholder' );
rms_cta_assert( false === strpos( $neither, $btn_marker ), 'test_neither_hides_directions', 'empty config must NOT render Get Directions' );

$whitespace_share = rms_gbp_render( array(
    'company_google_maps_url' => $embed_url,
    'company_gbp_share_url'   => "   \n",
) );
rms_cta_assert( false === strpos( $whitespace_share, $btn_marker ), 'test_whitespace_share_hides_directions', 'whitespace-only share must NOT render Get Directions' );

// ── 5. Overlay placement + safe output attributes ─────────────────────────

rms_cta_assert(
    false !== strpos( $both, '<section class="contact-map">' ),
    'test_section_always_rendered',
    'the contact-map section must always render'
);

$section = rms_gbp_section( $both );
rms_cta_assert( '' !== $section, 'test_section_bounds_found', 'could not locate the contact-map section bounds' );

$open_pos   = strpos( $both, '<section class="contact-map">' );
$close_pos  = strpos( $both, '</section>' );
$btn_pos    = strpos( $both, $btn_marker );
$content_pos = strpos( $both, '<div class="contact-map__embed">' );

rms_cta_assert(
    false !== $btn_pos && $btn_pos > $content_pos && $btn_pos < $close_pos && $open_pos < $btn_pos,
    'test_directions_inside_section_after_content',
    'Get Directions must sit inside .contact-map after the embed/placeholder block'
);

rms_cta_assert( false !== strpos( $both, 'target="_blank"' ), 'test_link_target_blank', 'the directions link must keep target="_blank"' );
rms_cta_assert( false !== strpos( $both, 'rel="noopener noreferrer"' ), 'test_link_rel_security', 'the directions link must keep rel="noopener noreferrer"' );
rms_cta_assert( false !== strpos( $both, 'aria-hidden="true"' ), 'test_icon_decorative', 'the icon SVG must be decorative with aria-hidden="true"' );

$js = rms_gbp_render( array(
    'company_google_maps_url' => $embed_url,
    'company_gbp_share_url'   => 'javascript:alert(1)',
) );
rms_cta_assert( false === strpos( $js, 'javascript:' ), 'test_js_protocol_neutralized', 'a javascript: share URL must not survive into the rendered href' );

// ── 6. Placeholder preserved ──────────────────────────────────────────────

rms_cta_assert( false !== strpos( $neither, '<p>Map unavailable</p>' ), 'test_placeholder_copy_preserved', 'the placeholder copy must be preserved' );
rms_cta_assert(
    false !== strpos( $neither, 'M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z' ),
    'test_placeholder_svg_preserved',
    'the placeholder SVG path must be preserved'
);

// ─── Summary ───────────────────────────────────────────────────────────────

if ( $failures > 0 ) {
    fwrite( STDERR, "GBP share URL contract harness failed: {$failures} check(s).\n" );
    exit( 1 );
}

echo "GBP share URL contract harness passed: schema, helper, render combinations, distinct URLs, overlay placement, attributes, malicious protocol, and placeholder.\n";
exit( 0 );