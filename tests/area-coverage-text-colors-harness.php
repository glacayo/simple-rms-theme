<?php
/**
 * Focused harness for issue #101, part 2: area coverage text color rendering.
 *
 * Proves, causally and without a framework:
 *   1. Valid saved colors render scoped CSS custom properties on the
 *      .area-coverage-v1 section element only (single consumer per variable;
 *      cities, map card, radius badge, and CTA never receive them).
 *   2. The palette bridge consumes the variables with the accessible fallback
 *      chain: eyebrow -> var(--rms-color-surface), headline -> #ffffff,
 *      body -> #e2e8f0. Absent or invalid values stay inert.
 *   3. The WYSIWYG description wrapper is a non-paragraph container (div, not
 *      p) so editor paragraphs remain descendants and the body color reaches
 *      them by inheritance (negative control: pre-fix nested-p markup breaks).
 *   4. Radius badge regression evidence: no `.area-coverage-v1__description ~`
 *      sibling selector may exist (it would recolor the radius badge, a <p>
 *      sibling), and the radius badge markup + bridge color rules stay intact.
 *   5. Empty / invalid colors emit no override and preserve the fallback
 *      chain; two consecutive sections stay isolated.
 *   6. Sanitization rejects style injection; only strict hex survives.
 *
 * Schema, JSON parity, and AI exclusion/preservation stay with the first
 * stacked PR: tests/area-coverage-color-schema-harness.php.
 *
 * Reuses tests/support/header-cta-support.php for the shared WP/ACF/template
 * stubs and the rms_cta_render/assert helpers. Area-coverage template stubs
 * and the DOM helpers (pattern from tests/slider-wysiwyg-paragraph-harness.php)
 * are declared inline.
 *
 * Usage: php tests/area-coverage-text-colors-harness.php
 */

if ( PHP_SAPI !== 'cli' ) {
    fwrite( STDERR, "CLI only.\n" );
    exit( 1 );
}

$theme_root = dirname( __DIR__ );

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', $theme_root . '/' );
}

// Shared stubs + rms_cta_* helpers. Also requires inc/acf-theme-options.php,
// which provides rms_get_area_coverage_color_style()/rms_sanitize_palette_hex()
// (production helpers under test).
require __DIR__ . '/support/header-cta-support.php';

// ─── Area-coverage-template stubs not covered by the shared support ─────────

if ( ! function_exists( 'get_sub_field' ) ) {
    function get_sub_field( $selector, $format_value = true ) {
        return $GLOBALS['rms_area_sub'][ $selector ] ?? null;
    }
}

if ( ! function_exists( 'wp_kses_post' ) ) {
    function wp_kses_post( $data ) {
        return (string) $data;
    }
}

// ─── Helpers ────────────────────────────────────────────────────────────────

function rms_area_setup( array $fields ): void {
    $GLOBALS['rms_area_sub'] = $fields;
}

function rms_area_row( array $overrides = array() ): array {
    return array_merge(
        array(
            'area_eyebrow'     => 'Regional Coverage',
            'area_headline'    => 'Areas We Proudly Serve',
            'area_description' => '<p>We deliver premium roofing solutions across the region.</p><p>Our certified local crews respond quickly.</p>',
            'area_radius'      => '50 Miles',
            'area_cities'      => array(
                array( 'city_name' => 'Orlando' ),
                array( 'city_name' => 'Kissimmee' ),
            ),
            'area_cta_text'    => 'Check Your Location',
            'area_cta_url'     => '#estimate',
            'area_map_image'   => 'https://example.test/map.jpg',
        ),
        $overrides
    );
}

/** @return DOMXPath Parsed document (html/body wrapper). */
function rms_dom_parse( string $html ): DOMXPath {
    $doc = new DOMDocument();
    $prev = libxml_use_internal_errors( true );
    $doc->loadHTML( '<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING );
    libxml_clear_errors();
    libxml_use_internal_errors( $prev );
    return new DOMXPath( $doc );
}

/** XPath predicate matching an element by a single space-separated class token. */
function rms_cls( string $cls ): string {
    return "contains(concat(' ', normalize-space(@class), ' '), ' $cls ')";
}

$palette_css = (string) file_get_contents( $theme_root . '/assets/css/rms-palette.css' );

// ─── 1. Valid saved colors render scoped variables on the section only ──────

rms_area_setup( rms_area_row( array(
    'area_eyebrow_color'  => '#ff0000',
    'area_headline_color' => '#00ff00',
    'area_text_color'     => '#0000ff',
) ) );
$html = rms_cta_render( 'area-coverage-v1.php' );
$xpath = rms_dom_parse( $html );

$sections = $xpath->query( '//*[' . rms_cls( 'area-coverage-v1' ) . ']' );
rms_cta_assert( 1 === $sections->length, 'test_single_section_rendered', 'one row must render exactly one .area-coverage-v1 section' );

$section_style = $sections->length ? $sections->item( 0 )->getAttribute( 'style' ) : '';
rms_cta_assert( false !== strpos( $section_style, '--area-eyebrow-color:#ff0000' ), 'test_valid_eyebrow_color_rendered', 'a valid eyebrow color must emit its scoped custom property on the section' );
rms_cta_assert( false !== strpos( $section_style, '--area-headline-color:#00ff00' ), 'test_valid_headline_color_rendered', 'a valid headline color must emit its scoped custom property on the section' );
rms_cta_assert( false !== strpos( $section_style, '--area-text-color:#0000ff' ), 'test_valid_text_color_rendered', 'a valid body text color must emit its scoped custom property on the section' );

// The custom properties must live ONLY on the section element: no cities, map,
// radius, CTA, or inner text element may carry them (single scoping point).
$styled = $xpath->query( '//*[' . rms_cls( 'area-coverage-v1' ) . ']//*[@style]' );
rms_cta_assert( 1 === $styled->length, 'test_color_vars_single_scoping_point', 'the section must be the only styled element inside area-coverage-v1 (map card keeps its own --coverage-image style outside this assertion scope)' );
$map_card = $xpath->query( '//*[' . rms_cls( 'area-coverage-v1__map-card' ) . ']' );
rms_cta_assert( 1 === $map_card->length && false !== strpos( (string) $map_card->item( 0 )->getAttribute( 'style' ), '--coverage-image' ) && false === strpos( (string) $map_card->item( 0 )->getAttribute( 'style' ), '--area-' ), 'test_map_card_style_untouched', 'the map card must keep only its --coverage-image style' );

// Only the matching element consumes each variable (single consumer, no leakage).
rms_cta_assert( false !== strpos( $palette_css, '.area-coverage-v1__eyebrow' ) && false !== strpos( $palette_css, 'color: var(--area-eyebrow-color, var(--rms-color-surface))' ), 'test_eyebrow_var_scoped_to_eyebrow', 'the eyebrow variable must resolve on .area-coverage-v1__eyebrow with the surface-token fallback' );
rms_cta_assert( false !== strpos( $palette_css, '.area-coverage-v1__headline' ) && false !== strpos( $palette_css, 'color: var(--area-headline-color, #ffffff)' ), 'test_headline_var_scoped_to_headline', 'the headline variable must resolve on .area-coverage-v1__headline with the #ffffff fallback' );
rms_cta_assert( false !== strpos( $palette_css, '.area-coverage-v1__description' ) && false !== strpos( $palette_css, 'color: var(--area-text-color, #e2e8f0)' ), 'test_text_var_scoped_to_description', 'the body text variable must resolve on .area-coverage-v1__description (and descendants) with the #e2e8f0 fallback' );
rms_cta_assert( 1 === substr_count( $palette_css, 'var(--area-eyebrow-color' ) && 1 === substr_count( $palette_css, 'var(--area-headline-color' ) && 1 === substr_count( $palette_css, 'var(--area-text-color' ), 'test_color_vars_single_consumer', 'each color variable must have exactly one CSS consumer' );

// Source order: the eyebrow override must come after the existing surface-token
// eyebrow rule so the same-specificity cascade resolves to the override.
$last_eyebrow_block = substr( $palette_css, (int) strrpos( $palette_css, '.area-coverage-v1__eyebrow' ) );
rms_cta_assert( false !== strpos( $last_eyebrow_block, 'var(--area-eyebrow-color, var(--rms-color-surface))' ), 'test_eyebrow_override_wins_source_order', 'the eyebrow override rule must be the last .area-coverage-v1__eyebrow block in the bridge cascade' );

// ─── 2. Radius badge regression evidence (no sibling selector, badge intact) ─

rms_cta_assert( false === strpos( $palette_css, '__description ~' ) && false === stripos( $palette_css, '__description~' ), 'test_no_description_sibling_selector_in_bridge', 'the bridge must not use a .area-coverage-v1__description ~ sibling selector (it would recolor the radius badge <p> sibling)' );

$area_scss = (string) file_get_contents( $theme_root . '/src/scss/templates/area-coverage-v1.scss' );
rms_cta_assert( false === strpos( $area_scss, '__description ~' ) && false === stripos( $area_scss, '__description~' ), 'test_no_description_sibling_selector_in_scss', 'the compiled template SCSS must not use a description sibling selector either' );

rms_cta_assert( false !== strpos( $palette_css, '.area-coverage-v1__radius' ) && false !== strpos( $palette_css, 'border-color: var(--rms-color-accent)' ), 'test_radius_badge_bridge_border_intact', 'the radius badge bridge accent border rule must remain unchanged' );
rms_cta_assert( false !== strpos( $palette_css, '.area-coverage-v1__radius' ) && substr_count( $palette_css, 'background-color: var(--rms-color-primary-strong)' ) >= 3, 'test_radius_badge_bridge_background_intact', 'the radius badge bridge background rule must remain among the strong-surface rules' );

rms_area_setup( rms_area_row( array(
    'area_eyebrow_color'  => '#ff0000',
    'area_headline_color' => '#00ff00',
    'area_text_color'     => '#0000ff',
) ) );
$html = rms_cta_render( 'area-coverage-v1.php' );
$xpath = rms_dom_parse( $html );
$radius = $xpath->query( '//p[' . rms_cls( 'area-coverage-v1__radius' ) . ']' );
rms_cta_assert( 1 === $radius->length, 'test_radius_badge_is_paragraph', 'the radius badge must remain a <p> sibling (that is exactly why no ~ p selector may exist)' );
rms_cta_assert( 1 === $radius->length && 'status' === (string) $radius->item( 0 )->getAttribute( 'role' ) && false !== strpos( (string) $radius->item( 0 )->textContent, '50 Miles' ), 'test_radius_badge_markup_unchanged', 'the radius badge role, label, and text must remain unchanged' );

// ─── 3. Empty / invalid colors emit no override, fallback chain preserved ────

rms_area_setup( rms_area_row( array(
    'area_eyebrow_color'  => '',
    'area_headline_color' => '',
    'area_text_color'     => '',
) ) );
$html = rms_cta_render( 'area-coverage-v1.php' );
$xpath = rms_dom_parse( $html );
$empty_sections = $xpath->query( '//*[' . rms_cls( 'area-coverage-v1' ) . ']' );
rms_cta_assert( 1 === $empty_sections->length && '' === (string) $empty_sections->item( 0 )->getAttribute( 'style' ), 'test_empty_colors_emit_no_override', 'empty color values must emit no style attribute on the section' );

rms_area_setup( rms_area_row( array(
    'area_eyebrow_color'  => 'red',
    'area_headline_color' => 'rgb(0,0,0)',
    'area_text_color'     => '#12',
) ) );
$html = rms_cta_render( 'area-coverage-v1.php' );
$xpath = rms_dom_parse( $html );
$invalid_sections = $xpath->query( '//*[' . rms_cls( 'area-coverage-v1' ) . ']' );
rms_cta_assert( 1 === $invalid_sections->length && '' === (string) $invalid_sections->item( 0 )->getAttribute( 'style' ), 'test_invalid_colors_emit_no_override', 'invalid (non-hex) color values must emit no style attribute on the section' );

// Fallback chain: eyebrow keeps the live surface token; headline/body keep the
// compiled literals when no per-section value exists.
rms_cta_assert( false !== strpos( $palette_css, 'var(--area-eyebrow-color, var(--rms-color-surface))' ), 'test_eyebrow_fallback_keeps_surface_token', 'the eyebrow must fall back to the live --rms-color-surface token' );
rms_cta_assert( false !== strpos( $palette_css, 'var(--area-headline-color, #ffffff)' ), 'test_headline_fallback_compiled_literal', 'the headline must fall back to the compiled #ffffff literal' );
rms_cta_assert( false !== strpos( $palette_css, 'var(--area-text-color, #e2e8f0)' ), 'test_text_fallback_compiled_literal', 'the body text must fall back to the compiled #e2e8f0 literal' );

// Existing sections render unchanged when the color fields are absent entirely
// (ACF rows saved before the fields existed).
rms_area_setup( rms_area_row() );
$html = rms_cta_render( 'area-coverage-v1.php' );
$xpath = rms_dom_parse( $html );
$legacy_sections = $xpath->query( '//*[' . rms_cls( 'area-coverage-v1' ) . ']' );
rms_cta_assert( 1 === $legacy_sections->length && '' === (string) $legacy_sections->item( 0 )->getAttribute( 'style' ), 'test_legacy_row_without_colors_unchanged', 'an area-coverage row without color keys must render no color overrides' );

// ─── 4. WYSIWYG wrapper is a div; editor paragraphs inherit the body color ──

rms_area_setup( rms_area_row( array( 'area_text_color' => '#0000ff' ) ) );
$html = rms_cta_render( 'area-coverage-v1.php' );
$xpath = rms_dom_parse( $html );

$descriptions = $xpath->query( '//*[' . rms_cls( 'area-coverage-v1__description' ) . ']' );
rms_cta_assert( 1 === $descriptions->length, 'test_single_description_wrapper', 'one section must render exactly one .area-coverage-v1__description wrapper' );

$wrapper_tag = $descriptions->length ? strtolower( (string) $descriptions->item( 0 )->tagName ) : '';
rms_cta_assert( 'div' === $wrapper_tag, 'test_wrapper_is_non_paragraph', 'the .area-coverage-v1__description wrapper must be a non-paragraph container (div, not p)' );

$inner_p = $xpath->query( '//*[' . rms_cls( 'area-coverage-v1__description' ) . ']/p' );
rms_cta_assert( 2 === $inner_p->length, 'test_editor_paragraphs_are_descendants', 'both editor-generated paragraphs must remain descendants of .area-coverage-v1__description' );
rms_cta_assert( false !== strpos( $inner_p->length ? (string) $inner_p->item( 0 )->textContent : '', 'We deliver premium roofing solutions across the region.' ), 'test_first_editor_paragraph_preserved', 'the first WYSIWYG paragraph text must survive as a descendant' );

// No classless sibling breakout under the content container.
$breakout = $xpath->query( '//*[' . rms_cls( 'area-coverage-v1__content' ) . ']/p[not(@class)]' );
rms_cta_assert( 0 === $breakout->length, 'test_no_classless_sibling_breakout', 'no classless sibling paragraph may appear under .area-coverage-v1__content' );

// Inheritance path: the editor paragraphs carry no inline color override.
$inline_styled_p = $xpath->query( '//*[' . rms_cls( 'area-coverage-v1__description' ) . ']//p[@style]' );
rms_cta_assert( 0 === $inline_styled_p->length, 'test_inner_paragraph_no_inline_color', 'the editor paragraphs must carry no inline style, so color inherits from .area-coverage-v1__description' );

// Fallback description branch (no ACF description) still renders its copy.
rms_area_setup( rms_area_row( array( 'area_description' => '' ) ) );
$html = rms_cta_render( 'area-coverage-v1.php' );
$xpath = rms_dom_parse( $html );
$fallback_desc = $xpath->query( '//*[' . rms_cls( 'area-coverage-v1__description' ) . ']' );
rms_cta_assert( 1 === $fallback_desc->length && 'div' === strtolower( (string) $fallback_desc->item( 0 )->tagName ) && false !== strpos( (string) $fallback_desc->item( 0 )->textContent, 'greater Orlando region' ), 'test_fallback_description_div_rendered', 'the fallback description branch must render as a div with its default copy intact' );

// Negative control: the pre-fix nested-paragraph markup breaks under DOM parsing.
$pre_fix = '<div class="area-coverage-v1__content"><p class="area-coverage-v1__description"><p>We deliver premium roofing solutions.</p></p></div>';
$neg     = rms_dom_parse( $pre_fix );

$neg_wrapper = $neg->query( '//*[' . rms_cls( 'area-coverage-v1__description' ) . ']' );
rms_cta_assert( 'p' === strtolower( (string) $neg_wrapper->item( 0 )->tagName ), 'test_negative_old_wrapper_is_p', 'negative control: the pre-fix wrapper parses as a p' );
rms_cta_assert( 0 === $neg->query( '//*[' . rms_cls( 'area-coverage-v1__description' ) . ']/p' )->length, 'test_negative_old_paragraph_escaped', 'negative control: the pre-fix editor paragraph is not a descendant of .area-coverage-v1__description' );
rms_cta_assert( 1 === $neg->query( '//*[' . rms_cls( 'area-coverage-v1__content' ) . ']/p[not(@class)]' )->length, 'test_negative_old_classless_breakout', 'negative control: the pre-fix markup produces a classless sibling paragraph under DOM parsing' );

// ─── 5. Unchanged structure: eyebrow, headline, cities, map, CTA; isolation ──

rms_area_setup( rms_area_row( array(
    'area_eyebrow_color'  => '#ff0000',
    'area_headline_color' => '#00ff00',
    'area_text_color'     => '#0000ff',
) ) );
$html = rms_cta_render( 'area-coverage-v1.php' );
$xpath = rms_dom_parse( $html );

rms_cta_assert( 1 === $xpath->query( '//p[' . rms_cls( 'area-coverage-v1__eyebrow' ) . ']' )->length, 'test_eyebrow_preserved', 'the eyebrow must remain a p.area-coverage-v1__eyebrow' );
$headline = $xpath->query( '//*[' . rms_cls( 'area-coverage-v1__headline' ) . ']' );
rms_cta_assert( 1 === $headline->length && 'h2' === strtolower( (string) $headline->item( 0 )->tagName ) && 'area-coverage-v1-heading' === (string) $headline->item( 0 )->getAttribute( 'id' ), 'test_headline_preserved', 'the headline must remain an h2.area-coverage-v1__headline with its aria-labelledby target id' );
rms_cta_assert( 2 === $xpath->query( '//li[' . rms_cls( 'area-coverage-v1__city' ) . ']' )->length, 'test_cities_unchanged', 'the provided cities must render one li.area-coverage-v1__city each' );
rms_cta_assert( 1 === $xpath->query( '//*[' . rms_cls( 'area-coverage-v1__map' ) . ']' )->length && 1 === $xpath->query( '//*[' . rms_cls( 'area-coverage-v1__map-shape' ) . ']' )->length, 'test_map_unchanged', 'the map card, image, and shape must remain unchanged' );
$cta = $xpath->query( '//a[' . rms_cls( 'area-coverage-v1__cta' ) . ']' );
rms_cta_assert( 1 === $cta->length && 'Check Your Location' === (string) $cta->item( 0 )->textContent, 'test_cta_unchanged', 'the CTA link and label must remain unchanged' );

// Two consecutive sections stay isolated: section A carries its overrides,
// section B (no colors) carries none.
rms_area_setup( rms_area_row( array( 'area_headline_color' => '#ff0000' ) ) );
$html_a = rms_cta_render( 'area-coverage-v1.php' );
rms_area_setup( rms_area_row() );
$html_b = rms_cta_render( 'area-coverage-v1.php' );
$both = rms_dom_parse( $html_a . $html_b );

$both_sections = $both->query( '//*[' . rms_cls( 'area-coverage-v1' ) . ']' );
rms_cta_assert( 2 === $both_sections->length, 'test_two_sections_rendered', 'two consecutive rows must render two .area-coverage-v1 sections' );
rms_cta_assert( false !== strpos( (string) $both_sections->item( 0 )->getAttribute( 'style' ), '--area-headline-color:#ff0000' ), 'test_section_a_carries_color', 'section A must carry its own headline color override' );
rms_cta_assert( '' === (string) $both_sections->item( 1 )->getAttribute( 'style' ), 'test_section_b_has_no_color_override', 'section B must not inherit any color override from section A' );

// ─── 6. Sanitization prevents style injection ────────────────────────────────

rms_cta_assert( null === rms_sanitize_palette_hex( 'red; background: url(javascript:alert(1))' ), 'test_sanitizer_rejects_css_injection', 'the sanitizer must reject CSS property injection' );
rms_cta_assert( null === rms_sanitize_palette_hex( '</style><script>alert(1)</script>' ), 'test_sanitizer_rejects_markup', 'the sanitizer must reject markup injection' );
rms_cta_assert( null === rms_sanitize_palette_hex( 'rgb(0,0,0)' ), 'test_sanitizer_rejects_rgb', 'the sanitizer must reject non-hex formats' );
rms_cta_assert( null === rms_sanitize_palette_hex( '#12345' ), 'test_sanitizer_rejects_bad_hex', 'the sanitizer must reject malformed hex' );
rms_cta_assert( '#ff0000' === rms_sanitize_palette_hex( '#ff0000' ), 'test_sanitizer_accepts_hex', 'the sanitizer must accept valid hex' );

rms_area_setup( rms_area_row( array(
    'area_eyebrow_color'  => 'red; background: url(javascript:alert(1))',
    'area_headline_color' => '</style><script>alert(1)</script>',
    'area_text_color'     => '" onload="alert(1)',
) ) );
$html = rms_cta_render( 'area-coverage-v1.php' );
rms_cta_assert( false === strpos( $html, '--area-eyebrow-color' ) && false === strpos( $html, '--area-headline-color' ) && false === strpos( $html, '--area-text-color' ), 'test_injection_emits_no_override', 'malicious color values must emit no scoped custom properties' );
rms_cta_assert( false === stripos( $html, '<script>' ) && false === stripos( $html, 'javascript:' ) && false === stripos( $html, 'onload=' ), 'test_injection_no_raw_payload', 'malicious payloads must not survive into the rendered markup' );

// ─── Summary ─────────────────────────────────────────────────────────────────

if ( $failures > 0 ) {
    fwrite( STDERR, "Area coverage text colors harness failed: {$failures} check(s).\n" );
    exit( 1 );
}

echo "Area coverage text colors harness passed: scoped rendering, accessible fallbacks, non-paragraph wrapper, radius-badge sibling-selector regression evidence, fallback chain, section isolation, and injection safety.\n";
exit( 0 );