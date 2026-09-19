<?php
/**
 * Focused contract harness for issue #128 slice I128-02 (Raven CTA/call
 * selector markers).
 *
 * Proves, without a framework:
 *   1. every existing explicit frontend CTA button carries exactly one bare,
 *      value-free data-raven-cta marker, and templates without a CTA carry none;
 *   2. every click-to-call anchor carries a bare data-raven-call marker on a
 *      real tel: anchor, while mailto/social/home anchors stay unmarked;
 *   3. CTA V2 resolves its secondary control from a real phone (configured tel,
 *      configured non-tel, empty-with-phone, empty-without-phone) and never
 *      ships the removed tel:+1234567890 placeholder;
 *   4. no production template carries data-raven-share or data-related, because
 *      neither share nor related UI exists.
 *
 * Reuses tests/support/header-cta-support.php for the shared rms_cta_* helpers
 * and the inc/acf-theme-options.php getters under test.
 *
 * Usage: php tests/raven-selector-markers-harness.php
 */

if ( PHP_SAPI !== 'cli' ) {
    fwrite( STDERR, "CLI only.\n" );
    exit( 1 );
}

$theme_root = dirname( __DIR__ );

require __DIR__ . '/support/header-cta-support.php';

// ─── ACF flexible-content stub (get_sub_field) ─────────────────────────────

$GLOBALS['rms_raven_sub_fields'] = array();

if ( ! function_exists( 'get_sub_field' ) ) {
    function get_sub_field( $selector, $post_id = false ) {
        return $GLOBALS['rms_raven_sub_fields'][ $selector ] ?? null;
    }
}

// ─── Structural helpers ────────────────────────────────────────────────────

/**
 * Return the closing '>' index starting at $from, skipping PHP close tags
 * (`?>`) so multi-attribute anchors with embedded PHP parse correctly.
 */
function rms_raven_tag_close( string $src, int $from ): int {
    $len = strlen( $src );
    for ( $i = $from; $i < $len; $i++ ) {
        if ( '>' !== $src[ $i ] ) {
            continue;
        }
        if ( $i > 0 && '?' === $src[ $i - 1 ] ) {
            continue;
        }
        return $i;
    }
    return -1;
}

/**
 * Return every <a ...> opening tag that contains the bare token $needle.
 */
function rms_raven_marked_tags( string $src, string $needle ): array {
    $tags   = array();
    $offset = 0;
    $step   = strlen( $needle );
    while ( false !== ( $pos = strpos( $src, $needle, $offset ) ) ) {
        $open = strrpos( substr( $src, 0, $pos ), '<a' );
        $ok   = false !== $open && isset( $src[ $open + 2 ] ) && 1 === preg_match( '/\s/', $src[ $open + 2 ] );
        if ( $ok ) {
            $close = rms_raven_tag_close( $src, $pos );
            if ( $close > $open ) {
                $tags[] = substr( $src, $open, $close - $open + 1 );
                $offset = $close + 1;
                continue;
            }
        }
        $offset = $pos + $step;
    }
    return $tags;
}

function rms_raven_read( string $file ): string {
    global $theme_root;
    $path = $theme_root . '/templates/' . $file;
    $src  = is_readable( $path ) ? file_get_contents( $path ) : '';
    return is_string( $src ) ? $src : '';
}

/**
 * Locate anchors containing $needle and prove none of them carries a marker.
 */
function rms_raven_assert_unmarked( string $src, string $needle, string $name, string $detail ): void {
    $tags = rms_raven_marked_tags( $src, $needle );
    rms_cta_assert( count( $tags ) >= 1, $name . '_located', $detail . ': no anchor matched ' . $needle );
    foreach ( $tags as $tag ) {
        rms_cta_assert( false === strpos( $tag, 'data-raven' ), $name, $detail . ' got ' . $tag );
    }
}

// ─── 1. data-raven-cta coverage ────────────────────────────────────────────

$cta_map = array(
    'header-one.php'        => 'rms-header__cta-btn',
    'header-two.php'        => 'rms-header-v2__cta-btn',
    'footer-v2.php'         => 'footer-v2__cta-button',
    'cta-v1.php'            => 'cta-v1__button',
    'cta-v2.php'            => 'cta-v2__button--primary',
    'cta-v3.php'            => 'cta-v3__button',
    'slider.php'            => 'slider__cta',
    'blog-v1.php'           => 'blog-v1__cta',
    'video-v1.php'          => 'video-v1__cta',
    'services-v1.php'       => 'services-v1__cta-btn',
    'services-v2.php'       => 'services-v2__cta-btn',
    'services-v3.php'       => 'services-v3__cta-btn',
    'area-coverage-v1.php'  => 'area-coverage-v1__cta',
    'vision-mission-v1.php' => 'vision-mission-v1__cta',
    'vision-mission-v2.php' => 'vision-mission-v2__cta',
);

foreach ( $cta_map as $file => $class ) {
    $src = rms_raven_read( $file );
    rms_cta_assert( 1 === substr_count( $src, 'data-raven-cta' ), 'test_cta_single_marker_' . $file, $file . ' must carry exactly one data-raven-cta marker' );
    rms_cta_assert( false === strpos( $src, 'data-raven-cta=' ), 'test_cta_bare_marker_' . $file, $file . ' must use a bare, value-free data-raven-cta marker' );
    $tags = rms_raven_marked_tags( $src, 'data-raven-cta' );
    rms_cta_assert( 1 === count( $tags ) && false !== strpos( $tags[0], $class ), 'test_cta_marker_on_button_' . $file, $file . ' marker must bind to .' . $class );
}

foreach ( array( 'header-three.php', 'footer-v1.php' ) as $file ) {
    rms_cta_assert( 0 === substr_count( rms_raven_read( $file ), 'data-raven-cta' ), 'test_cta_absent_' . $file, $file . ' has no explicit CTA button and must not carry data-raven-cta' );
}

// ─── 2. data-raven-call coverage ───────────────────────────────────────────

$call_map = array(
    'header-one.php'   => 1,
    'header-two.php'   => 1,
    'header-three.php' => 2,
    'footer-v1.php'    => 1,
    'footer-v2.php'    => 1,
    'contact-info.php' => 2,
    'cta-v2.php'       => 1, // conditional; the marker only renders for a valid tel target
);

foreach ( $call_map as $file => $count ) {
    $src = rms_raven_read( $file );
    rms_cta_assert( $count === substr_count( $src, 'data-raven-call' ), 'test_call_marker_count_' . $file, $file . ' must carry ' . $count . ' data-raven-call marker(s)' );
    rms_cta_assert( false === strpos( $src, 'data-raven-call=' ), 'test_call_bare_marker_' . $file, $file . ' must use a bare, value-free data-raven-call marker' );
    if ( 'cta-v2.php' === $file ) {
        continue;
    }
    $tags = rms_raven_marked_tags( $src, 'data-raven-call' );
    rms_cta_assert( $count === count( $tags ), 'test_call_marker_on_anchor_' . $file, $file . ' markers must bind to anchors' );
    foreach ( $tags as $tag ) {
        rms_cta_assert( false !== strpos( $tag, 'tel:' ), 'test_call_marker_requires_tel_' . $file, $file . ' data-raven-call must sit on a real tel: anchor, got ' . $tag );
    }
}

$call_absent = array(
    'cta-v1.php', 'cta-v3.php', 'slider.php', 'blog-v1.php', 'video-v1.php',
    'services-v1.php', 'services-v2.php', 'services-v3.php',
    'area-coverage-v1.php', 'vision-mission-v1.php', 'vision-mission-v2.php',
);
foreach ( $call_absent as $file ) {
    rms_cta_assert( 0 === substr_count( rms_raven_read( $file ), 'data-raven-call' ), 'test_call_absent_' . $file, $file . ' has no click-to-call anchor' );
}

// Negatives: mailto, social profiles, home/logo, and wordmark anchors stay unmarked.
rms_raven_assert_unmarked( rms_raven_read( 'header-two.php' ), 'mailto:', 'test_mailto_unmarked_header_two', 'header-two email anchor must stay unmarked' );
rms_raven_assert_unmarked( rms_raven_read( 'contact-info.php' ), 'mailto:', 'test_mailto_unmarked_contact_info', 'contact-info email anchors must stay unmarked' );
rms_raven_assert_unmarked( rms_raven_read( 'footer-v2.php' ), 'mailto:', 'test_mailto_unmarked_footer_v2', 'footer-v2 email anchors must stay unmarked' );
rms_raven_assert_unmarked( rms_raven_read( 'header-one.php' ), 'target="_blank"', 'test_social_unmarked_header_one', 'header-one social anchors must stay unmarked' );
rms_raven_assert_unmarked( rms_raven_read( 'header-two.php' ), 'target="_blank"', 'test_social_unmarked_header_two', 'header-two social anchors must stay unmarked' );
rms_raven_assert_unmarked( rms_raven_read( 'header-three.php' ), 'rms-header-v3__social-link', 'test_social_unmarked_header_three', 'header-three social anchors must stay unmarked' );
rms_raven_assert_unmarked( rms_raven_read( 'footer-v1.php' ), 'footer-v1__social-link', 'test_social_unmarked_footer_v1', 'footer-v1 social anchors must stay unmarked' );
rms_raven_assert_unmarked( rms_raven_read( 'footer-v2.php' ), 'footer-v2__social-link', 'test_social_unmarked_footer_v2', 'footer-v2 social anchors must stay unmarked' );
rms_raven_assert_unmarked( rms_raven_read( 'header-one.php' ), 'aria-label="Home"', 'test_logo_unmarked_header_one', 'header-one logo anchor must stay unmarked' );
rms_raven_assert_unmarked( rms_raven_read( 'header-two.php' ), 'aria-label="Home"', 'test_logo_unmarked_header_two', 'header-two logo anchor must stay unmarked' );
rms_raven_assert_unmarked( rms_raven_read( 'footer-v2.php' ), 'footer-v2__logo-placeholder', 'test_logo_unmarked_footer_v2', 'footer-v2 wordmark anchor must stay unmarked' );

// ─── 3. CTA V2 secondary resolution ────────────────────────────────────────

$v2_src = rms_raven_read( 'cta-v2.php' );
rms_cta_assert( false === strpos( $v2_src, 'tel:+1234567890' ), 'test_cta_v2_no_fake_tel_fallback', 'cta-v2 must not keep the fake tel:+1234567890 fallback' );
rms_cta_assert( false !== strpos( $v2_src, 'rms_get_primary_phone' ) && false !== strpos( $v2_src, 'rms_format_tel_uri' ), 'test_cta_v2_derives_real_fallback', 'cta-v2 must derive its empty secondary from the real primary phone' );

// 3a. Configured tel URL renders and is marked.
$GLOBALS['rms_raven_sub_fields'] = array( 'cta_v2_secondary_text' => 'Call Now', 'cta_v2_secondary_url' => 'tel:+14145550100' );
$GLOBALS['rms_header_fields']    = array();
$html = rms_cta_render( 'cta-v2.php' );
rms_cta_assert( false !== strpos( $html, 'href="tel:+14145550100"' ), 'test_cta_v2_configured_tel_href', 'a configured tel URL must be preserved verbatim' );
$call_tags = rms_raven_marked_tags( $html, 'data-raven-call' );
rms_cta_assert( 1 === count( $call_tags ) && false !== strpos( $call_tags[0], 'cta-v2__button--outline' ) && false !== strpos( $call_tags[0], 'tel:+14145550100' ), 'test_cta_v2_configured_tel_marked', 'a configured tel secondary must carry data-raven-call' );
rms_cta_assert( 1 === substr_count( $html, 'data-raven-cta' ), 'test_cta_v2_primary_marked', 'cta-v2 primary must carry exactly one data-raven-cta' );

// 3b. Configured non-tel URL still renders, unmarked.
$GLOBALS['rms_raven_sub_fields'] = array( 'cta_v2_secondary_text' => 'Call Now', 'cta_v2_secondary_url' => 'https://example.test/call' );
$html = rms_cta_render( 'cta-v2.php' );
rms_cta_assert( false !== strpos( $html, 'href="https://example.test/call"' ), 'test_cta_v2_configured_non_tel_href', 'a configured non-tel URL must still render' );
rms_cta_assert( false !== strpos( $html, 'cta-v2__button--outline' ) && 0 === substr_count( $html, 'data-raven-call' ), 'test_cta_v2_configured_non_tel_unmarked', 'a configured non-tel secondary must render without data-raven-call' );

// 3c. Empty URL with a usable phone derives a real tel target and marker.
$GLOBALS['rms_raven_sub_fields'] = array();
$GLOBALS['rms_header_fields']    = array( 'company_phones' => array( array( 'phone_number' => '(414) 555-0100' ) ) );
$html = rms_cta_render( 'cta-v2.php' );
rms_cta_assert( false !== strpos( $html, 'href="tel:4145550100"' ) && 1 === substr_count( $html, 'data-raven-call' ), 'test_cta_v2_empty_derives_phone', 'an empty secondary must derive tel:4145550100 from the primary phone and carry data-raven-call' );

// 3d. Empty URL without a usable phone omits the secondary control.
$GLOBALS['rms_header_fields'] = array( 'company_phones' => array() );
$html = rms_cta_render( 'cta-v2.php' );
rms_cta_assert( false === strpos( $html, 'cta-v2__button--outline' ) && 0 === substr_count( $html, 'data-raven-call' ), 'test_cta_v2_empty_without_phone_omits_secondary', 'no usable phone must omit the secondary control' );
rms_cta_assert( false !== strpos( $html, 'cta-v2__button--primary' ) && 1 === substr_count( $html, 'data-raven-cta' ), 'test_cta_v2_primary_survives_omission', 'the primary CTA must still render when the secondary is omitted' );
rms_cta_assert( false === strpos( $html, '+1234567890' ), 'test_cta_v2_no_placeholder_rendered', 'rendered cta-v2 must not contain the removed placeholder number' );

// ─── 4. No placeholder number and no false share/related markers ───────────

foreach ( glob( $theme_root . '/templates/*.php' ) as $path ) {
    $src  = is_readable( $path ) ? file_get_contents( $path ) : '';
    $src  = is_string( $src ) ? $src : '';
    $base = basename( $path );
    rms_cta_assert( false === strpos( $src, '+1234567890' ), 'test_no_placeholder_phone_' . $base, $base . ' must not ship the removed placeholder number' );
    rms_cta_assert( false === strpos( $src, 'data-raven-share' ), 'test_no_share_marker_' . $base, $base . ' must not carry data-raven-share because no share UI exists' );
    rms_cta_assert( false === strpos( $src, 'data-related' ), 'test_no_related_marker_' . $base, $base . ' must not carry data-related because no related UI exists' );
}

// ── Summary ───────────────────────────────────────────────────────────────

if ( $failures > 0 ) {
    fwrite( STDERR, "Raven selector markers harness failed: {$failures} check(s).\n" );
    exit( 1 );
}

echo "Raven selector markers harness passed: CTA/call marker coverage, real tel resolution, no placeholder number, and no false share/related labels.\n";
exit( 0 );