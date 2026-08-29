<?php
/**
 * Focused harness for issue #64 (Google Business Profile social platform).
 *
 * Proves, causally and without a framework:
 *   1. The `social_platform` ACF schema includes `google_business` labeled
 *      exactly "Google Business Profile", and only once.
 *   2. A configured row survives rms_get_social_links() normalization keyed
 *      as `google_business` (never coerced to `other`).
 *   3. The dedicated Google "G" icon renders in header-one, header-two,
 *      header-three desktop + mobile, and footer-v2 — not the generic `other`.
 *   4. The link keeps its accessible label (escaped), `target="_blank"` /
 *      `rel="noopener noreferrer"` security, and a decorative aria-hidden SVG;
 *      a `javascript:` URL is neutralized by esc_url().
 *   5. Inactive and empty-URL rows are omitted everywhere.
 *   6. Existing representative platforms (facebook, other) remain unchanged.
 *
 * Reuses tests/support/header-cta-support.php for the shared WP/ACF/template
 * stubs and the rms_cta_* render/assert helpers (the module is the repo's
 * canonical standalone-render stub). No duplicated stubs here.
 *
 * Usage: php tests/google-business-social-harness.php
 */

if ( PHP_SAPI !== 'cli' ) {
    fwrite( STDERR, "CLI only.\n" );
    exit( 1 );
}

$theme_root = dirname( __DIR__ );

require __DIR__ . '/support/header-cta-support.php';

// Distinctive path fragments identifying the dedicated Google "G" mark versus
// the generic "other" (external-link) icon used as the footer fallback.
$gbp_g_fragment = 'M12.48 10.92v3.28h7.84';
$other_fragment = 'M14 4h6v6';

$gbp_url   = 'https://g.page/r/TestBusiness';
$gbp_label = 'Google Business Profile';

function rms_gbp_social_row( string $platform, string $url, string $label, bool $active = true ): array {
    return array(
        'social_is_active' => $active,
        'social_platform'  => $platform,
        'social_url'       => $url,
        'social_label'     => $label,
    );
}

// ─── 1. Schema: exact key + label, exactly once ────────────────────────────

$acf_raw = file_get_contents( $theme_root . '/acf-json/group_rms_theme_settings.json' );
$acf_raw = is_string( $acf_raw ) ? $acf_raw : '';

rms_cta_assert(
    1 === substr_count( $acf_raw, '"google_business"' ),
    'test_schema_google_business_key_exactly_once',
    'ACF JSON must contain the google_business key exactly once'
);

$acf            = json_decode( $acf_raw, true );
$social_choices = array();
if ( is_array( $acf ) ) {
    foreach ( $acf['fields'] ?? array() as $field ) {
        if ( ( $field['name'] ?? '' ) !== 'company_social_media' ) {
            continue;
        }
        foreach ( $field['sub_fields'] ?? array() as $sub ) {
            if ( ( $sub['name'] ?? '' ) === 'social_platform' && is_array( $sub['choices'] ?? null ) ) {
                $social_choices = $sub['choices'];
            }
        }
    }
}

rms_cta_assert(
    isset( $social_choices['google_business'] ) && 'Google Business Profile' === $social_choices['google_business'],
    'test_schema_google_business_label_exact',
    'social_platform must map google_business to the exact label "Google Business Profile"'
);

// ─── 2. Getter normalization ───────────────────────────────────────────────

rms_cta_setup( array(
    'company_social_media' => array(
        rms_gbp_social_row( 'google_business', $gbp_url, $gbp_label ),
    ),
) );

$links = rms_get_social_links();
rms_cta_assert(
    isset( $links['google_business'] )
        && $links['google_business']['url'] === $gbp_url
        && $links['google_business']['label'] === $gbp_label,
    'test_getter_normalizes_google_business',
    'rms_get_social_links() must key the row as google_business with url + label intact'
);
rms_cta_assert(
    ! isset( $links['other'] ),
    'test_getter_does_not_coerce_to_other',
    'google_business must never be coerced to the other platform'
);

// ─── 3. Dedicated icon in every required surface ───────────────────────────

rms_cta_setup( array(
    'company_social_media' => array(
        rms_gbp_social_row( 'google_business', $gbp_url, $gbp_label ),
    ),
) );

$h1 = rms_cta_render( 'header-one.php' );
$h2 = rms_cta_render( 'header-two.php' );
$h3 = rms_cta_render( 'header-three.php' );
$f2 = rms_cta_render( 'footer-v2.php' );

rms_cta_assert( false !== strpos( $h1, $gbp_g_fragment ), 'test_header_one_dedicated_gbp_icon', 'header-one did not render the dedicated Google G icon' );
rms_cta_assert( false !== strpos( $h2, $gbp_g_fragment ), 'test_header_two_dedicated_gbp_icon', 'header-two did not render the dedicated Google G icon' );
rms_cta_assert( 2 === substr_count( $h3, $gbp_g_fragment ), 'test_header_three_desktop_and_mobile_gbp_icon', 'header-three must render the Google G icon in both desktop and mobile social surfaces' );
rms_cta_assert( false !== strpos( $f2, $gbp_g_fragment ), 'test_footer_v2_dedicated_gbp_icon', 'footer-v2 did not render the dedicated Google G icon' );
rms_cta_assert( false === strpos( $f2, $other_fragment ), 'test_footer_v2_not_generic_other', 'footer-v2 must not fall back to the generic other icon for google_business' );

// ─── 4. Escaping + decorative accessibility + link security ────────────────

rms_cta_setup( array(
    'company_social_media' => array(
        rms_gbp_social_row( 'google_business', $gbp_url, 'Google "Business" Profile' ),
    ),
) );

$f2 = rms_cta_render( 'footer-v2.php' );
rms_cta_assert( false !== strpos( $f2, 'aria-label="Google &quot;Business&quot; Profile"' ), 'test_label_escaped', 'the configured label must be attribute-escaped' );
rms_cta_assert( false !== strpos( $f2, 'target="_blank"' ), 'test_link_target_blank', 'the external link must keep target="_blank"' );
rms_cta_assert( false !== strpos( $f2, 'rel="noopener noreferrer"' ), 'test_link_rel_security', 'the external link must keep rel="noopener noreferrer"' );
rms_cta_assert( false !== strpos( $f2, 'aria-hidden="true"' ), 'test_svg_decorative', 'the icon SVG must be decorative with aria-hidden="true"' );

rms_cta_setup( array(
    'company_social_media' => array(
        rms_gbp_social_row( 'google_business', 'javascript:alert(1)', $gbp_label ),
    ),
) );

$f2 = rms_cta_render( 'footer-v2.php' );
rms_cta_assert( false === strpos( $f2, 'javascript:' ), 'test_url_escaping_strips_js_protocol', 'a javascript: URL must not survive into the rendered href' );
rms_cta_assert( false === strpos( $f2, 'footer-v2__social-link' ), 'test_js_url_row_omitted', 'a javascript: URL must omit the social row entirely' );

// ─── 5. Inactive / empty-URL rows omitted ──────────────────────────────────

rms_cta_setup( array(
    'company_social_media' => array(
        rms_gbp_social_row( 'google_business', $gbp_url, $gbp_label, false ),
    ),
) );
rms_cta_assert( array() === rms_get_social_links(), 'test_inactive_row_omitted', 'an inactive row must be dropped by the getter' );

rms_cta_setup( array(
    'company_social_media' => array(
        rms_gbp_social_row( 'google_business', '', $gbp_label ),
    ),
) );
rms_cta_assert( array() === rms_get_social_links(), 'test_empty_url_row_omitted', 'an empty-URL row must be dropped by the getter' );

$f2 = rms_cta_render( 'footer-v2.php' );
rms_cta_assert( false === strpos( $f2, 'footer-v2__social-link' ), 'test_empty_inactive_render_omitted', 'footer-v2 must render no social link for empty/inactive rows' );

// ─── 6. Existing representative platforms unchanged ────────────────────────

rms_cta_setup( array(
    'company_social_media' => array(
        rms_gbp_social_row( 'facebook', 'https://facebook.com/raven', 'Facebook' ),
        rms_gbp_social_row( 'other', 'https://example.test/custom', 'Custom' ),
    ),
) );

$h1 = rms_cta_render( 'header-one.php' );
$f2 = rms_cta_render( 'footer-v2.php' );

rms_cta_assert( false !== strpos( $h1, 'https://facebook.com/raven' ), 'test_facebook_unchanged_header_one', 'header-one facebook rendering regressed' );
rms_cta_assert( false !== strpos( $f2, 'https://facebook.com/raven' ), 'test_facebook_unchanged_footer_v2', 'footer-v2 facebook rendering regressed' );
rms_cta_assert( false !== strpos( $f2, $other_fragment ), 'test_other_platform_still_renders_footer_v2', 'footer-v2 generic other platform rendering regressed' );

// ─── Summary ───────────────────────────────────────────────────────────────

if ( $failures > 0 ) {
    fwrite( STDERR, "Google Business harness failed: {$failures} check(s).\n" );
    exit( 1 );
}

echo "Google Business harness passed: schema, getter, icon surfaces, escaping, omission, and no regressions.\n";
exit( 0 );
