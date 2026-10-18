<?php
/**
 * Focused regression harness for issue #174 (Branding logos ignore Media
 * Library alt text).
 *
 * Proves, without a framework:
 *   1. header-one, header-two, header-three and footer-v2 render the selected
 *      Branding logo with its attachment's current alt text, escaped.
 *   2. Editing the attachment alt text is reflected on the next render
 *      without re-saving Branding.
 *   3. The footer alt follows the same source as its image: footer logo
 *      first, then the header logo.
 *   4. Without alt text, each template keeps its previous fallback; logo
 *      source precedence (WordPress Custom Logo first in header-one),
 *      placeholders, dimensions and links are unchanged.
 *
 * Usage: php tests/branding-logo-alt-harness.php
 */

if ( PHP_SAPI !== 'cli' ) {
    fwrite( STDERR, "CLI only.\n" );
    exit( 1 );
}

$theme_root = dirname( __DIR__ );

// ─── Stubs defined first so they win over the shared support versions ─────

$GLOBALS['rms_tlo_options']     = array(); // option name => attachment ID (raw ACF value)
$GLOBALS['rms_tlo_attachments'] = array(); // ID => array( url, alt )
$GLOBALS['rms_tlo_custom_logo'] = false;

function get_field( $selector, $post_id = false, $format_value = true ) {
    if ( ! array_key_exists( $selector, $GLOBALS['rms_tlo_options'] ) ) {
        return $GLOBALS['rms_header_fields'][ $selector ] ?? null;
    }
    $id = $GLOBALS['rms_tlo_options'][ $selector ];
    if ( ! $format_value ) {
        return $id;
    }
    // Image fields use return_format "url".
    return $GLOBALS['rms_tlo_attachments'][ (int) $id ]['url'] ?? false;
}

function get_post_meta( $post_id, $key = '', $single = false ) {
    if ( '_wp_attachment_image_alt' !== $key ) {
        return $single ? '' : array();
    }
    return $GLOBALS['rms_tlo_attachments'][ (int) $post_id ]['alt'] ?? '';
}

function attachment_url_to_postid( $url ) {
    foreach ( $GLOBALS['rms_tlo_attachments'] as $id => $attachment ) {
        if ( $attachment['url'] === $url ) {
            return (int) $id;
        }
    }
    return 0;
}

function esc_attr( $text ) {
    return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

function has_custom_logo() {
    return (bool) $GLOBALS['rms_tlo_custom_logo'];
}

function the_custom_logo() {
    echo '<a class="custom-logo-link"><img class="custom-logo" alt="Custom Logo Alt"></a>';
}

// Shared stubs + rms_cta_* helpers. Also requires inc/acf-theme-options.php.
require __DIR__ . '/support/header-cta-support.php';

if ( ! function_exists( 'get_template_part' ) ) {
    function get_template_part( ...$args ) {}
}
if ( ! function_exists( 'wp_body_open' ) ) {
    function wp_body_open() {}
}

/**
 * Render a template with the given logo attachments.
 */
function rms_tlo_render( string $template, array $options, array $attachments, bool $custom_logo = false ): string {
    $GLOBALS['rms_tlo_options']     = $options;
    $GLOBALS['rms_tlo_attachments'] = $attachments;
    $GLOBALS['rms_tlo_custom_logo'] = $custom_logo;
    rms_cta_setup( array() );
    $html = rms_cta_render( $template );
    return $html;
}

/**
 * Every <img> tag whose src is $src.
 */
function rms_tlo_imgs( string $html, string $src ): array {
    preg_match_all( '#<img\b[^>]*\bsrc="' . preg_quote( $src, '#' ) . '"[^>]*>#s', $html, $m );
    return $m[0];
}

function rms_tlo_attr( string $tag, string $attr ): ?string {
    return 1 === preg_match( '#\b' . preg_quote( $attr, '#' ) . '="([^"]*)"#', $tag, $m ) ? $m[1] : null;
}

$header_url = 'https://example.test/uploads/header-logo.png';
$footer_url = 'https://example.test/uploads/footer-logo.png';

$headers = array(
    'header-one.php'   => array( 'fallback' => 'Simple RMS', 'width' => '200', 'height' => '100' ),
    'header-two.php'   => array( 'fallback' => 'Simple RMS', 'width' => '200', 'height' => '60' ),
    'header-three.php' => array( 'fallback' => 'Simple RMS', 'width' => '200', 'height' => '60' ),
);

// ─── 1/2/4. Header variants ────────────────────────────────────────────────

foreach ( $headers as $template => $expect ) {
    $options = array( 'company_logo_header' => 11 );

    $html = rms_tlo_render( $template, $options, array( 11 => array( 'url' => $header_url, 'alt' => 'Header logo description' ) ) );
    $imgs = rms_tlo_imgs( $html, $header_url );
    rms_cta_assert( 1 === count( $imgs ), "{$template}-renders-logo-once", 'the Branding header logo must render once' );
    $img = $imgs[0] ?? '';
    rms_cta_assert( 'Header logo description' === rms_tlo_attr( $img, 'alt' ), "{$template}-uses-attachment-alt", 'the logo alt must be the attachment alt text (got ' . var_export( rms_tlo_attr( $img, 'alt' ), true ) . ')' );
    rms_cta_assert(
        $expect['width'] === rms_tlo_attr( $img, 'width' ) && $expect['height'] === rms_tlo_attr( $img, 'height' ),
        "{$template}-keeps-dimensions",
        "the logo must keep width {$expect['width']} and height {$expect['height']}"
    );

    $edited = rms_tlo_render( $template, $options, array( 11 => array( 'url' => $header_url, 'alt' => 'Edited header alt' ) ) );
    rms_cta_assert( 'Edited header alt' === rms_tlo_attr( rms_tlo_imgs( $edited, $header_url )[0] ?? '', 'alt' ), "{$template}-reflects-alt-edit", 'an alt text edit must show on the next render' );

    $escaped = rms_tlo_render( $template, $options, array( 11 => array( 'url' => $header_url, 'alt' => 'A "quoted" <b>logo</b> & co' ) ) );
    rms_cta_assert(
        1 === preg_match( '#alt="A &quot;quoted&quot; &lt;b&gt;logo&lt;/b&gt; &amp; co"#', $escaped ) && false === strpos( $escaped, '<b>logo' ),
        "{$template}-escapes-alt",
        'the alt text must be escaped for the attribute'
    );

    $no_alt = rms_tlo_render( $template, $options, array( 11 => array( 'url' => $header_url, 'alt' => '   ' ) ) );
    rms_cta_assert( $expect['fallback'] === rms_tlo_attr( rms_tlo_imgs( $no_alt, $header_url )[0] ?? '', 'alt' ), "{$template}-empty-alt-fallback", "without alt text the logo keeps the previous \"{$expect['fallback']}\" alt" );

    $placeholder = rms_tlo_render( $template, array(), array() );
    rms_cta_assert(
        1 === count( rms_tlo_imgs( $placeholder, 'https://placehold.co/200x' . $expect['height'] ) )
            && $expect['fallback'] === rms_tlo_attr( rms_tlo_imgs( $placeholder, 'https://placehold.co/200x' . $expect['height'] )[0] ?? '', 'alt' ),
        "{$template}-placeholder-unchanged",
        'without a Branding logo the placeholder image and its alt are unchanged'
    );
}

// Header-one keeps the WordPress Custom Logo first.
$custom = rms_tlo_render( 'header-one.php', array( 'company_logo_header' => 11 ), array( 11 => array( 'url' => $header_url, 'alt' => 'Header logo description' ) ), true );
rms_cta_assert(
    false !== strpos( $custom, 'alt="Custom Logo Alt"' ) && array() === rms_tlo_imgs( $custom, $header_url ),
    'header-one.php-custom-logo-precedence',
    'a WordPress Custom Logo must still win over the Branding logo in header-one'
);

// ─── 1/3/4. Footer V2 ──────────────────────────────────────────────────────

$both = array(
    11 => array( 'url' => $header_url, 'alt' => 'Header logo description' ),
    22 => array( 'url' => $footer_url, 'alt' => 'Footer logo description' ),
);

$footer = rms_tlo_render( 'footer-v2.php', array( 'company_logo_header' => 11, 'company_logo_footer' => 22 ), $both );
$fimg   = rms_tlo_imgs( $footer, $footer_url )[0] ?? '';
rms_cta_assert( 'Footer logo description' === rms_tlo_attr( $fimg, 'alt' ), 'footer-v2-uses-footer-alt', 'the footer logo alt must be the footer attachment alt text' );
rms_cta_assert( '200' === rms_tlo_attr( $fimg, 'width' ) && '60' === rms_tlo_attr( $fimg, 'height' ), 'footer-v2-keeps-dimensions', 'the footer logo keeps 200x60' );
rms_cta_assert( 1 === preg_match( '#<a href="https://example\.test/">\s*<img[^>]*src="' . preg_quote( $footer_url, '#' ) . '"#', $footer ), 'footer-v2-keeps-home-link', 'the footer logo stays linked to home' );

$edited_footer = rms_tlo_render( 'footer-v2.php', array( 'company_logo_header' => 11, 'company_logo_footer' => 22 ), array( 11 => $both[11], 22 => array( 'url' => $footer_url, 'alt' => 'Edited footer alt' ) ) );
rms_cta_assert( 'Edited footer alt' === rms_tlo_attr( rms_tlo_imgs( $edited_footer, $footer_url )[0] ?? '', 'alt' ), 'footer-v2-reflects-alt-edit', 'a footer alt text edit must show on the next render' );

$header_only = rms_tlo_render( 'footer-v2.php', array( 'company_logo_header' => 11 ), $both );
rms_cta_assert(
    'Header logo description' === rms_tlo_attr( rms_tlo_imgs( $header_only, $header_url )[0] ?? '', 'alt' ),
    'footer-v2-header-fallback-alt',
    'when the footer falls back to the header logo it uses the header alt text'
);

$no_alt_footer = rms_tlo_render( 'footer-v2.php', array( 'company_logo_footer' => 22 ), array( 22 => array( 'url' => $footer_url, 'alt' => '' ) ) );
rms_cta_assert( 'Simple RMS' === rms_tlo_attr( rms_tlo_imgs( $no_alt_footer, $footer_url )[0] ?? '', 'alt' ), 'footer-v2-empty-alt-fallback', 'without alt text the footer logo keeps the site-name alt' );

$no_logo_footer = rms_tlo_render( 'footer-v2.php', array(), array() );
rms_cta_assert(
    1 === preg_match( '#<a href="https://example\.test/" class="footer-v2__logo-placeholder">Simple RMS</a>#', $no_logo_footer ),
    'footer-v2-placeholder-unchanged',
    'without logos the footer keeps the linked site-name placeholder'
);

// ─── Summary ───────────────────────────────────────────────────────────────

if ( $failures > 0 ) {
    fwrite( STDERR, "\nBranding logo alt harness failed: {$failures} check(s).\n" );
    exit( 1 );
}

echo "\nBranding logo alt harness passed.\n";
exit( 0 );
