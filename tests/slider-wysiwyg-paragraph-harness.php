<?php
/**
 * Focused DOM harness for issue #65 follow-up: WYSIWYG paragraph breakout.
 *
 * The slider body renderer used to wrap WYSIWYG content in a `<p class="slider__text">`.
 * Paragraphs cannot legally nest, so a real HTML parser closes the outer `<p>` before
 * the editor-generated inner `<p>`, exposing the visible text as a classless sibling —
 * which the `.slider__text` color rule then misses. This harness proves, with a real
 * DOM parser (PHP DOMDocument/libxml), that the wrapper is now a non-paragraph container
 * and that the editor paragraph stays its descendant.
 *
 * Covers:
 *   1. Outer `.slider__text` is a non-paragraph container (div, not p).
 *   2. Real multi-paragraph WYSIWYG content keeps its editor paragraphs as descendants.
 *   3. No classless sibling breakout under `.slider__content`.
 *   4. Body color reaches the inner paragraph by inheritance (tag-agnostic `.slider__text`
 *      selector sets color; the inner paragraph carries no inline color override).
 *   5. Two slides stay isolated (distinct WYSIWYG text and per-slide colors, no bleed).
 *   6. Absent and invalid text colors emit no override and preserve the fallback wrapper.
 *   7. Existing subheadline / headline / CTA markup is unchanged.
 *   8. Negative control: the pre-fix `<p class="slider__text"><p>…</p></p>` markup
 *      demonstrably breaks under DOM parsing (empty outer p + classless sibling).
 *
 * Reuses tests/support/header-cta-support.php for the shared WP/ACF/template stubs and
 * the rms_cta_render/rms_cta_assert helpers. Slider-specific stubs are declared inline.
 *
 * Usage: php tests/slider-wysiwyg-paragraph-harness.php
 */

if ( PHP_SAPI !== 'cli' ) {
    fwrite( STDERR, "CLI only.\n" );
    exit( 1 );
}

$theme_root = dirname( __DIR__ );

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', $theme_root . '/' );
}

require __DIR__ . '/support/header-cta-support.php';

// ─── Slider-template stubs not covered by the shared support ────────────────

if ( ! function_exists( 'get_sub_field' ) ) {
    function get_sub_field( $selector, $format_value = true ) {
        return $GLOBALS['rms_slider_sub'][ $selector ] ?? null;
    }
}

if ( ! function_exists( 'wp_kses_post' ) ) {
    function wp_kses_post( $data ) {
        return (string) $data;
    }
}

// ─── Helpers ────────────────────────────────────────────────────────────────

function rms_slider_setup( array $slides ): void {
    $GLOBALS['rms_slider_sub'] = array( 'slider_slides' => $slides );
}

function rms_slide_row( array $overrides = array() ): array {
    return array_merge(
        array(
            'slide_bg_image'    => 'https://example.test/bg.jpg',
            'slide_subheadline' => 'Trusted Roofing Experts',
            'slide_headline'    => 'Protecting What Matters Most',
            'slide_text'        => '<p>We deliver premium roofing solutions.</p><p>Our certified team handles every project with care.</p>',
            'slide_cta_text'    => 'Get Free Estimate',
            'slide_cta_url'     => '#estimate',
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

// ─── CSS evidence: the color rule must be tag-agnostic and inherit ──────────

$palette_css = (string) file_get_contents( $theme_root . '/assets/css/rms-palette.css' );
$slider_scss = (string) file_get_contents( $theme_root . '/src/scss/templates/slider.scss' );

rms_cta_assert( false !== strpos( $palette_css, '.slider__text' ) && false !== strpos( $palette_css, 'color: var(--slide-text-color, #cbd5e1)' ), 'test_css_text_color_rule_present', 'the palette override must set the body text color on .slider__text' );
rms_cta_assert( false !== strpos( $slider_scss, '.slider__text' ) && false !== strpos( $slider_scss, '$color-gray-300' ), 'test_css_compiled_color_rule_present', 'the compiled slider CSS must set a body text color on .slider__text' );
rms_cta_assert( false === stripos( $palette_css, 'p.slider__text' ) && false === stripos( $slider_scss, 'p.slider__text' ), 'test_css_selector_tag_agnostic', 'the .slider__text selector must not be element-qualified (no p.slider__text)' );

// ─── 1. Wrapper is non-paragraph; editor paragraph is its descendant ───────

rms_slider_setup( array(
    rms_slide_row( array( 'slide_text_color' => '#0000ff' ) ),
) );
$html = rms_cta_render( 'slider.php' );
$xpath = rms_dom_parse( $html );

$text_nodes = $xpath->query( '//*[' . rms_cls( 'slider__text' ) . ']' );
rms_cta_assert( 1 === $text_nodes->length, 'test_single_text_wrapper', 'one slide must render exactly one .slider__text wrapper' );

$wrapper_tag = $text_nodes->length ? strtolower( $text_nodes->item( 0 )->tagName ) : '';
rms_cta_assert( 'div' === $wrapper_tag, 'test_wrapper_is_non_paragraph', 'the .slider__text wrapper must be a non-paragraph container (div, not p)' );

$inner_p = $xpath->query( '//*[' . rms_cls( 'slider__text' ) . ']/p' );
rms_cta_assert( 2 === $inner_p->length, 'test_editor_paragraphs_are_descendants', 'both editor-generated paragraphs must remain descendants of .slider__text' );
rms_cta_assert( false !== strpos( $inner_p->length ? $inner_p->item( 0 )->textContent : '', 'We deliver premium roofing solutions.' ), 'test_first_editor_paragraph_preserved', 'the first WYSIWYG paragraph text must survive as a descendant' );

// ─── 2. No classless sibling breakout under the content container ──────────

$breakout = $xpath->query( '//div[' . rms_cls( 'slider__content' ) . ']/p[not(@class)]' );
rms_cta_assert( 0 === $breakout->length, 'test_no_classless_sibling_breakout', 'no classless sibling paragraph may appear under .slider__content' );

// ─── 3. Body color reaches the inner paragraph by inheritance ──────────────

$inline_styled_p = $xpath->query( '//*[' . rms_cls( 'slider__text' ) . ']/p[@style]' );
rms_cta_assert( 0 === $inline_styled_p->length, 'test_inner_paragraph_no_inline_color', 'the editor paragraph must carry no inline style, so color inherits from .slider__text' );

$slide_style = $xpath->query( '//*[' . rms_cls( 'slider__slide' ) . ']' )->item( 0 )->getAttribute( 'style' );
rms_cta_assert( false !== strpos( $slide_style, '--slide-text-color:#0000ff' ), 'test_text_color_scoped_on_slide', 'the per-slide text color must be scoped to the slide that carries it' );

// ─── 4. Two slides remain isolated ─────────────────────────────────────────

rms_slider_setup( array(
    rms_slide_row( array( 'slide_text' => '<p>First slide text.</p>', 'slide_text_color' => '#111111' ) ),
    rms_slide_row( array( 'slide_text' => '<p>Second slide text.</p>', 'slide_text_color' => '#222222' ) ),
) );
$html = rms_cta_render( 'slider.php' );
$xpath = rms_dom_parse( $html );

$slides = $xpath->query( '//*[' . rms_cls( 'slider__slide' ) . ']' );
rms_cta_assert( 2 === $slides->length, 'test_two_slides_rendered', 'two slides must render two .slider__slide elements' );

$texts = $xpath->query( '//*[' . rms_cls( 'slider__text' ) . ']' );
rms_cta_assert( 2 === $texts->length, 'test_two_text_wrappers', 'two slides must render two .slider__text wrappers' );
rms_cta_assert( false !== strpos( $texts->item( 0 )->textContent, 'First slide text.' ) && false === strpos( $texts->item( 0 )->textContent, 'Second slide text.' ), 'test_slide1_text_isolated', 'slide 1 must contain only its own body text' );
rms_cta_assert( false !== strpos( $texts->item( 1 )->textContent, 'Second slide text.' ) && false === strpos( $texts->item( 1 )->textContent, 'First slide text.' ), 'test_slide2_text_isolated', 'slide 2 must contain only its own body text' );

$slide1_style = $slides->item( 0 )->getAttribute( 'style' );
$slide2_style = $slides->item( 1 )->getAttribute( 'style' );
rms_cta_assert( false !== strpos( $slide1_style, '--slide-text-color:#111111' ) && false === strpos( $slide1_style, '#222222' ), 'test_slide1_color_isolated', 'slide 1 color must not leak to slide 2' );
rms_cta_assert( false !== strpos( $slide2_style, '--slide-text-color:#222222' ) && false === strpos( $slide2_style, '#111111' ), 'test_slide2_color_isolated', 'slide 2 color must not leak from slide 1' );

// ─── 5. Absent / invalid text colors preserve fallback, no override ────────

rms_slider_setup( array(
    rms_slide_row( array( 'slide_text_color' => '' ) ),
) );
$html = rms_cta_render( 'slider.php' );
$xpath = rms_dom_parse( $html );
$empty_style = $xpath->query( '//*[' . rms_cls( 'slider__slide' ) . ']' )->item( 0 )->getAttribute( 'style' );
rms_cta_assert( false === strpos( $empty_style, '--slide-text-color' ), 'test_empty_color_no_override', 'an empty text color must emit no scoped custom property' );
rms_cta_assert( 1 === $xpath->query( '//*[' . rms_cls( 'slider__text' ) . ']' )->length, 'test_empty_color_wrapper_preserved', 'the .slider__text wrapper must still render when the color is empty' );

rms_slider_setup( array(
    rms_slide_row( array( 'slide_text_color' => 'red' ) ),
) );
$html = rms_cta_render( 'slider.php' );
$xpath = rms_dom_parse( $html );
$invalid_style = $xpath->query( '//*[' . rms_cls( 'slider__slide' ) . ']' )->item( 0 )->getAttribute( 'style' );
rms_cta_assert( false === strpos( $invalid_style, '--slide-text-color' ), 'test_invalid_color_no_override', 'an invalid (non-hex) text color must emit no scoped custom property' );

// ─── 6. Subheadline / headline / CTA markup unchanged ──────────────────────

rms_slider_setup( array( rms_slide_row() ) );
$html = rms_cta_render( 'slider.php' );
$xpath = rms_dom_parse( $html );

rms_cta_assert( 1 === $xpath->query( '//p[' . rms_cls( 'slider__subheadline' ) . ']' )->length, 'test_subheadline_preserved', 'the subheadline must remain a p.slider__subheadline' );
rms_cta_assert( 1 === $xpath->query( '//*[' . rms_cls( 'slider__headline' ) . ']' )->length && 'h1' === strtolower( $xpath->query( '//*[' . rms_cls( 'slider__headline' ) . ']' )->item( 0 )->tagName ), 'test_headline_preserved', 'the first slide headline must remain an h1.slider__headline' );
$cta = $xpath->query( '//a[' . rms_cls( 'slider__cta' ) . ']' );
rms_cta_assert( 1 === $cta->length && false !== strpos( $cta->item( 0 )->textContent, 'Get Free Estimate' ), 'test_cta_preserved', 'the CTA must remain an a.slider__cta with its label intact' );

// ─── 7. Negative control: pre-fix nested-paragraph markup breaks ───────────

$pre_fix = '<div class="slider__content container"><p class="slider__text"><p>We deliver premium roofing solutions.</p></p></div>';
$neg = rms_dom_parse( $pre_fix );

$neg_wrapper = $neg->query( '//*[' . rms_cls( 'slider__text' ) . ']' );
rms_cta_assert( 'p' === strtolower( $neg_wrapper->item( 0 )->tagName ), 'test_negative_old_wrapper_is_p', 'negative control: the pre-fix wrapper parses as a p' );
rms_cta_assert( 0 === $neg->query( '//*[' . rms_cls( 'slider__text' ) . ']/p' )->length, 'test_negative_old_paragraph_escaped', 'negative control: the pre-fix editor paragraph is not a descendant of .slider__text' );
rms_cta_assert( 1 === $neg->query( '//div[' . rms_cls( 'slider__content' ) . ']/p[not(@class)]' )->length, 'test_negative_old_classless_breakout', 'negative control: the pre-fix markup produces a classless sibling paragraph under DOM parsing' );

// ─── Summary ───────────────────────────────────────────────────────────────

if ( $failures > 0 ) {
    fwrite( STDERR, "Slider WYSIWYG paragraph harness failed: {$failures} check(s).\n" );
    exit( 1 );
}

echo "Slider WYSIWYG paragraph harness passed: non-paragraph wrapper, descendant editor paragraphs, no classless breakout, inherited color, slide isolation, fallback, no-regression, and negative control.\n";
exit( 0 );
