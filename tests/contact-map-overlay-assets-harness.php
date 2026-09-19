<?php
/**
 * Focused overlay/assets harness for issue #126 (Contact Map URL separation) —
 * slice 2 of the Feature Branch Chain.
 *
 * Proves, without a framework:
 *   1. The SCSS adds an additive overlay/button with non-zero line-height,
 *      keyboard focus, contrast, and responsive padding.
 *   2. The existing 400px / 550px embed heights and iframe rules are untouched.
 *   3. `header.php` still scopes the contact-map stylesheet to the Contact Us
 *      template exactly once (off landing pages).
 *
 * Ships with the additive `src/scss/templates/contact-map.scss` slice. The
 * schema/helper/template contract coverage lives in
 * tests/gbp-share-url-contract-harness.php (slice 1).
 *
 * Reuses tests/support/header-cta-support.php only for the shared
 * rms_cta_assert/pass/fail helpers. That support module is read-only here.
 *
 * Usage: php tests/contact-map-overlay-assets-harness.php
 */

if ( PHP_SAPI !== 'cli' ) {
    fwrite( STDERR, "CLI only.\n" );
    exit( 1 );
}

$theme_root = dirname( __DIR__ );

require __DIR__ . '/support/header-cta-support.php';

/**
 * Extract the brace-balanced body of a selector from SCSS source.
 */
function rms_gbp_scss_block( string $source, string $selector ): string {
    $start = strpos( $source, $selector );
    if ( false === $start ) {
        return '';
    }
    $open = strpos( $source, '{', $start );
    if ( false === $open ) {
        return '';
    }
    $depth = 0;
    $len   = strlen( $source );
    for ( $i = $open; $i < $len; $i++ ) {
        if ( '{' === $source[ $i ] ) {
            $depth++;
        } elseif ( '}' === $source[ $i ] ) {
            $depth--;
            if ( 0 === $depth ) {
                return substr( $source, $open, ( $i - $open ) + 1 );
            }
        }
    }
    return '';
}

// ─── 1. Additive SCSS ──────────────────────────────────────────────────────

$scss = file_get_contents( $theme_root . '/src/scss/templates/contact-map.scss' );
$scss = is_string( $scss ) ? $scss : '';

$overlay_block = rms_gbp_scss_block( $scss, '.contact-map__link' );
$button_block  = rms_gbp_scss_block( $scss, '.contact-map__directions-btn' );

rms_cta_assert( false !== strpos( $scss, '.contact-map__link' ), 'test_scss_has_overlay_selector', 'SCSS must style .contact-map__link' );
rms_cta_assert( false !== strpos( $overlay_block, 'position: absolute' ), 'test_scss_overlay_absolute', 'the overlay must be positioned over the map' );
rms_cta_assert( false !== strpos( $overlay_block, 'bottom:' ), 'test_scss_overlay_bottom', 'the overlay must anchor to the bottom' );
rms_cta_assert(
    false !== strpos( $button_block, 'line-height:' ) && false === strpos( $button_block, 'line-height: 0' ),
    'test_scss_nonzero_line_height',
    'the button block must declare an explicit non-zero line-height'
);
rms_cta_assert( false !== strpos( $button_block, 'focus-ring' ), 'test_scss_keyboard_focus', 'the button must include the keyboard focus-ring mixin' );
rms_cta_assert( false !== strpos( $overlay_block, '@include breakpoint' ), 'test_scss_responsive', 'the overlay must use the responsive breakpoint mixin for padding' );
rms_cta_assert( false !== strpos( $overlay_block, 'padding' ), 'test_scss_overlay_padding', 'the overlay must declare padding' );
rms_cta_assert(
    false !== strpos( $button_block, '$color-primary' ) && false !== strpos( $button_block, '$color-white' ),
    'test_scss_contrast_tokens',
    'the button must use the brand primary/white contrast tokens'
);

rms_cta_assert( false !== strpos( $scss, 'height: 400px' ), 'test_scss_400px_preserved', 'the 400px embed height must be preserved' );
rms_cta_assert( false !== strpos( $scss, 'height: 550px' ), 'test_scss_550px_preserved', 'the 550px embed height must be preserved' );
rms_cta_assert( false !== strpos( $scss, '.contact-map__iframe' ), 'test_scss_iframe_rules_preserved', 'the iframe rules must remain' );

// ─── 2. Header enqueue scoping preserved ───────────────────────────────────

$header = file_get_contents( $theme_root . '/header.php' );
$header = is_string( $header ) ? $header : '';

rms_cta_assert(
    1 === substr_count( $header, "'section-contact-map'" ),
    'test_header_contact_map_enqueued_once',
    'header.php must enqueue the contact-map stylesheet exactly once'
);

$map_line = strpos( $header, "'section-contact-map'" );
$prefix   = false !== $map_line ? substr( $header, 0, $map_line ) : '';
$last_gate       = strrpos( $prefix, 'is_page_template(' );
$contact_gate    = strrpos( $prefix, "is_page_template('pages/contact-us.php')" );
$landing_gate    = strrpos( $prefix, 'is_page_template(\'pages/landing' );
rms_cta_assert(
    false !== $map_line && false !== $contact_gate && $last_gate === $contact_gate,
    'test_header_contact_map_scoped_to_contact_template',
    'the nearest preceding gate for the contact-map enqueue must be the Contact Us template'
);
rms_cta_assert(
    false === $landing_gate || $landing_gate < $contact_gate,
    'test_header_contact_map_not_on_landing',
    'the contact-map stylesheet must not be enqueued under a landing-page gate'
);

// ─── Summary ───────────────────────────────────────────────────────────────

if ( $failures > 0 ) {
    fwrite( STDERR, "Contact map overlay assets harness failed: {$failures} check(s).\n" );
    exit( 1 );
}

echo "Contact map overlay assets harness passed: additive SCSS overlay, preserved heights/iframe, focus/contrast/responsive, and Contact-only header scoping.\n";
exit( 0 );
