<?php
/**
 * Focused contract harness for issue #158 (logo image per badge).
 *
 * Proves, without a framework:
 *   1. The Badges repeater exposes an optional `badge_logo` image sub-field
 *      (return format ID) as the first field of each row, the Icon field says
 *      it is the fallback, and the generated ACF JSON matches the PHP source.
 *   2. A badge with a logo renders the attachment image (medium size, lazy,
 *      async, empty alt) inside `badges__icon badges__icon--logo` instead of
 *      the text icon.
 *   3. A badge without a logo, or whose attachment no longer resolves,
 *      renders byte-identical to the pinned pre-#158 template output.
 *   4. The AI wizard never fills `badge_logo`.
 *   5. The logo tile keeps its light background over the palette override.
 *
 * Usage: php tests/badges-logo-harness.php
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
        return $GLOBALS['rms_badges_sub_fields'][ $selector ] ?? null;
    }
}
if ( ! function_exists( 'wp_get_attachment_image' ) ) {
    function wp_get_attachment_image( $attachment_id, $size = 'thumbnail', $icon = false, $attr = '' ) {
        $GLOBALS['rms_badges_image_calls'][] = array( $attachment_id, $size, $icon, $attr );
        if ( ! in_array( (int) $attachment_id, $GLOBALS['rms_badges_attachments'] ?? array(), true ) ) {
            return '';
        }
        $attrs = '';
        foreach ( (array) $attr as $name => $value ) {
            $attrs .= ' ' . $name . '="' . esc_attr( $value ) . '"';
        }
        return '<img src="https://example.test/logo-' . (int) $attachment_id . '.png" width="300" height="150" srcset="https://example.test/logo-' . (int) $attachment_id . '.png 300w"' . $attrs . ' />';
    }
}

function rms_badges_render( string $template_path, array $items ): string {
    $GLOBALS['rms_badges_sub_fields'] = array(
        'badges_label' => 'Trusted & Verified On',
        'badges_items' => $items,
    );
    $GLOBALS['rms_badges_image_calls'] = array();
    ob_start();
    include $template_path;
    return (string) ob_get_clean();
}

// ─── 1. Schema ─────────────────────────────────────────────────────────────

function rms_badges_row_fields( array $group ): array {
    $layout = $group['fields'][0]['layouts']['layout_badges'] ?? array();
    foreach ( $layout['sub_fields'] ?? array() as $field ) {
        if ( 'badges_items' === ( $field['name'] ?? '' ) ) {
            return $field['sub_fields'] ?? array();
        }
    }
    return array();
}

$GLOBALS['rms_captured_group'] = null;
require_once $theme_root . '/inc/acf-flexible-content.php';
rms_register_acf_page_sections();
$php_group = is_array( $GLOBALS['rms_captured_group'] ) ? $GLOBALS['rms_captured_group'] : array();
$php_rows  = rms_badges_row_fields( $php_group );
$logo      = $php_rows[0] ?? array();

rms_cta_assert( 'badge_logo' === ( $logo['name'] ?? '' ), 'schema-logo-first', 'badge_logo must be the first sub-field of each badge row' );
rms_cta_assert( 'image' === ( $logo['type'] ?? '' ), 'schema-logo-image', 'badge_logo must be an image field' );
rms_cta_assert( 'id' === ( $logo['return_format'] ?? '' ), 'schema-logo-return-id', 'badge_logo must return the attachment ID' );
rms_cta_assert( empty( $logo['required'] ), 'schema-logo-optional', 'badge_logo must be optional' );
rms_cta_assert( 'Logo' === ( $logo['label'] ?? '' ), 'schema-logo-label', 'badge_logo must be labelled "Logo"' );
rms_cta_assert(
    'field_rms_badge_items' === ( $logo['parent_repeater'] ?? '' ),
    'schema-logo-parent',
    'badge_logo must belong to the badges repeater'
);
rms_cta_assert(
    array( 'badge_logo', 'badge_icon', 'badge_name', 'badge_url' ) === array_column( $php_rows, 'name' ),
    'schema-row-order',
    'badge rows must be logo, icon, name, url'
);
$icon = $php_rows[1] ?? array();
rms_cta_assert(
    false !== stripos( (string) ( $icon['instructions'] ?? '' ), 'no logo' ),
    'schema-icon-fallback-note',
    'the Icon instructions must say it is used when no logo is set'
);

$json_group = json_decode( (string) file_get_contents( $theme_root . '/acf-json/group_rms_page_sections.json' ), true );
rms_cta_assert(
    is_array( $json_group ) && rms_badges_row_fields( $json_group ) === $php_rows,
    'schema-json-in-sync',
    'acf-json/group_rms_page_sections.json must match the PHP badge row definition (run scripts/generate-acf-json.php)'
);

// ─── 2/3. Rendering ────────────────────────────────────────────────────────

$template = $theme_root . '/templates/badges.php';

// Exact output of templates/badges.php before #158 (commit 329c3c4), pinned
// so no-logo badges are checked against a fixed baseline on any checkout.
$rms_badges_expected_two_rows = <<<'PRE158'
<!-- Badges / Directories Section -->
<section class="badges">
    <div class="container">
        <p class="badges__label">Trusted &amp; Verified On</p>
        <div class="badges__list">
                                                                        <a class="badges__item" href="https://www.bbb.org/x" target="_blank" rel="noopener noreferrer" aria-label="BBB Accredited">
                                            <span class="badges__icon">BBB</span>
                        <span class="badges__name">BBB Accredited</span>
                                            </a>
                                                                                <div class="badges__item">
                                            <span class="badges__icon">YP</span>
                        <span class="badges__name">Yellow Pages</span>
                                            </div>
                                                        </div>
    </div>
</section>
PRE158;
$rms_badges_expected_two_rows .= "\n";
$rms_badges_expected_one_row = <<<'PRE158'
<!-- Badges / Directories Section -->
<section class="badges">
    <div class="container">
        <p class="badges__label">Trusted &amp; Verified On</p>
        <div class="badges__list">
                                                                        <a class="badges__item" href="https://www.bbb.org/x" target="_blank" rel="noopener noreferrer" aria-label="BBB Accredited">
                                            <span class="badges__icon">BBB</span>
                        <span class="badges__name">BBB Accredited</span>
                                            </a>
                                                        </div>
    </div>
</section>
PRE158;
$rms_badges_expected_one_row .= "\n";
$rms_badges_expected_placeholder = <<<'PRE158'
<!-- Badges / Directories Section -->
<section class="badges">
    <div class="container">
        <p class="badges__label">Trusted &amp; Verified On</p>
        <div class="badges__list">
                            <a class="badges__item" href="#" target="_blank" rel="noopener noreferrer" aria-label="Home Advisor">
                    <span class="badges__icon">HA</span>
                    <span class="badges__name">Home Advisor</span>
                </a>

                <a class="badges__item" href="#" target="_blank" rel="noopener noreferrer" aria-label="Yellow Pages">
                    <span class="badges__icon">YP</span>
                    <span class="badges__name">Yellow Pages</span>
                </a>

                <a class="badges__item" href="#" target="_blank" rel="noopener noreferrer" aria-label="Cilex">
                    <span class="badges__icon">CL</span>
                    <span class="badges__name">Cilex</span>
                </a>

                <a class="badges__item" href="#" target="_blank" rel="noopener noreferrer" aria-label="Yelp">
                    <span class="badges__icon">YL</span>
                    <span class="badges__name">Yelp</span>
                </a>

                <a class="badges__item" href="#" target="_blank" rel="noopener noreferrer" aria-label="BBB Accredited">
                    <span class="badges__icon">BBB</span>
                    <span class="badges__name">BBB Accredited</span>
                </a>
                    </div>
    </div>
</section>
PRE158;
$rms_badges_expected_placeholder .= "\n";

$GLOBALS['rms_badges_attachments'] = array( 42 );

$legacy_rows = array(
    array( 'badge_icon' => 'BBB', 'badge_name' => 'BBB Accredited', 'badge_url' => 'https://www.bbb.org/x' ),
    array( 'badge_icon' => 'YP', 'badge_name' => 'Yellow Pages', 'badge_url' => '' ),
);
rms_cta_assert(
    rms_badges_render( $template, $legacy_rows ) === $rms_badges_expected_two_rows,
    'render-legacy-identical',
    'rows saved before #158 (no badge_logo key) must render byte-identical to the pre-#158 template'
);

$empty_logo_rows = array(
    array( 'badge_logo' => '', 'badge_icon' => 'BBB', 'badge_name' => 'BBB Accredited', 'badge_url' => 'https://www.bbb.org/x' ),
    array( 'badge_logo' => false, 'badge_icon' => 'YP', 'badge_name' => 'Yellow Pages', 'badge_url' => '' ),
);
rms_cta_assert(
    rms_badges_render( $template, $empty_logo_rows ) === $rms_badges_expected_two_rows,
    'render-empty-logo-identical',
    'rows with an empty logo must render byte-identical to the pre-#158 template'
);
rms_cta_assert(
    rms_badges_render( $template, array() ) === $rms_badges_expected_placeholder,
    'render-placeholder-identical',
    'the no-items placeholder must stay byte-identical to the pre-#158 template'
);

$html  = rms_badges_render( $template, array(
    array( 'badge_logo' => 42, 'badge_icon' => 'HA', 'badge_name' => 'Home Advisor', 'badge_url' => 'https://www.homeadvisor.com/x' ),
    array( 'badge_logo' => '', 'badge_icon' => 'YP', 'badge_name' => 'Yellow Pages', 'badge_url' => '' ),
) );
$calls = $GLOBALS['rms_badges_image_calls'];
rms_cta_assert( 1 === count( $calls ), 'render-logo-image-once', 'wp_get_attachment_image must be called once, only for the row with a logo' );
rms_cta_assert(
    isset( $calls[0] ) && 42 === (int) $calls[0][0] && 'medium' === $calls[0][1] && false === $calls[0][2],
    'render-logo-medium',
    'the logo must render the attachment at the medium size'
);
$attr = $calls[0][3] ?? array();
rms_cta_assert(
    is_array( $attr ) && 'badges__logo' === ( $attr['class'] ?? null ) && '' === ( $attr['alt'] ?? null )
        && 'lazy' === ( $attr['loading'] ?? null ) && 'async' === ( $attr['decoding'] ?? null ),
    'render-logo-attrs',
    'the logo must use class badges__logo, empty alt, lazy loading and async decoding'
);
rms_cta_assert(
    1 === preg_match( '#<span class="badges__icon badges__icon--logo"><img [^>]*class="badges__logo"[^>]*/></span>#', $html ),
    'render-logo-tile',
    'the logo must sit inside badges__icon badges__icon--logo'
);
rms_cta_assert(
    false === strpos( $html, '>HA<' ) && false !== strpos( $html, '<span class="badges__icon">YP</span>' ),
    'render-logo-replaces-text-only-for-its-row',
    'the logo replaces the text icon for its row only'
);
rms_cta_assert(
    false !== strpos( $html, 'aria-label="Home Advisor"' ) && false !== strpos( $html, '<span class="badges__name">Home Advisor</span>' ),
    'render-logo-keeps-name',
    'a logo badge keeps its aria-label and visible name'
);

$missing = rms_badges_render( $template, array(
    array( 'badge_logo' => 999, 'badge_icon' => 'BBB', 'badge_name' => 'BBB Accredited', 'badge_url' => 'https://www.bbb.org/x' ),
) );
rms_cta_assert(
    $missing === $rms_badges_expected_one_row,
    'render-missing-attachment-fallback',
    'a logo whose attachment no longer resolves must fall back to the text icon exactly as before #158'
);
rms_badges_render( $template, array(
    array( 'badge_logo' => array( 'ID' => 42 ), 'badge_icon' => 'A', 'badge_name' => 'Array Logo', 'badge_url' => '' ),
    array( 'badge_logo' => 'logo.png', 'badge_icon' => 'S', 'badge_name' => 'String Logo', 'badge_url' => '' ),
) );
rms_cta_assert(
    array() === $GLOBALS['rms_badges_image_calls'],
    'render-non-numeric-logo-ignored',
    'array or non-numeric logo values must fall back to the text icon without loading any attachment'
);

// ─── 4. AI wizard never fills the logo ─────────────────────────────────────

require_once $theme_root . '/inc/wizard/class-ai-content-harness.php';
require_once $theme_root . '/inc/wizard/class-flexible-content-layouts.php';
$ai = new \Inc\Wizard\AI_Content_Harness();
rms_cta_assert( in_array( 'badge_logo', $ai->get_blocked_fields( 'badges' ), true ), 'ai-logo-blocked', 'badge_logo must be an AI-blocked badges field' );
rms_cta_assert( ! in_array( 'badge_logo', $ai->get_fillable_fields( 'badges' ), true ), 'ai-logo-not-fillable', 'badge_logo must not be AI-fillable' );
$clean = $ai->validate_fields( 'badges', array( 'badges_label' => 'Trusted On', 'badge_logo' => 42 ) );
rms_cta_assert(
    ! array_key_exists( 'badge_logo', $clean ) && 'Trusted On' === ( $clean['badges_label'] ?? null ),
    'ai-validate-strips-logo',
    'validate_fields must drop badge_logo and keep badges_label'
);

// ─── 5. Logo tile styling ──────────────────────────────────────────────────

$scss = (string) file_get_contents( $theme_root . '/src/scss/templates/badges.scss' );
rms_cta_assert(
    1 === preg_match( '/\.badges__icon\.badges__icon--logo\s*\{[^}]*background-color:\s*\$color-white;/', $scss ),
    'scss-logo-tile-light',
    'the logo tile must use a doubled selector with a white background to beat rms-palette.css'
);
rms_cta_assert(
    1 === preg_match( '/\.badges__logo\s*\{[^}]*object-fit:\s*contain;/', $scss ),
    'scss-logo-contained',
    'the logo image must be contained inside its tile'
);

// ─── Summary ─────────────────────────────────────────────────────────────────────────

if ( $failures > 0 ) {
    fwrite( STDERR, "Badges logo harness failed: {$failures} check(s).\n" );
    exit( 1 );
}

echo "Badges logo harness passed: optional logo field, PHP/JSON parity, logo rendering, unchanged fallback, and AI exclusion.\n";
exit( 0 );
